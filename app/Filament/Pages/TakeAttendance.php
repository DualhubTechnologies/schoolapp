<?php

namespace App\Filament\Pages;

use App\Models\AttendanceRecord;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Services\ParentMessages;
use App\Support\AcademicAccess;
use App\Support\Modules;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;

/**
 * The daily class register: pick the day, class and stream, mark each
 * learner present, absent, late or excused, and save. Parents of the
 * absent can be texted straight from here.
 *
 * Class teachers see only their own streams; everyone else with the
 * Attendance module (and School Admins) can take any class's register.
 *
 * @property-read Collection<int, Student> $students
 */
class TakeAttendance extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCheckCircle;

    protected static string|\UnitEnum|null $navigationGroup = 'Students';

    protected static ?int $navigationSort = 20;

    protected static ?string $title = 'Class Register';

    protected static ?string $navigationLabel = 'Attendance register';

    protected string $view = 'filament.pages.take-attendance';

    public string $date = '';

    public ?int $classId = null;

    public ?int $sectionId = null;

    /** @var array<int, string> student id => status */
    public array $statuses = [];

    /** @var array<int, string|null> */
    public array $notes = [];

    public bool $alreadyTaken = false;

    public static function canAccess(): bool
    {
        return Modules::allows('attendance');
    }

    public function mount(): void
    {
        $this->date = now()->toDateString();

        $own = $this->ownStreams();

        if ($own) {
            $this->sectionId = (int) array_key_first($own);
            $this->classId = $own[$this->sectionId];
            $this->loadRegister();
        }
    }

    /**
     * Streams the user is limited to (section id => class id), or [] when
     * they may take any class's register.
     *
     * @return array<int, int>
     */
    public function ownStreams(): array
    {
        return Modules::hasFullAccess() ? [] : AcademicAccess::classTeacherStreams();
    }

    public function updatedDate(): void
    {
        $this->loadRegister();
    }

    public function updatedClassId(): void
    {
        $this->sectionId = null;
        $this->loadRegister();
    }

    public function updatedSectionId(): void
    {
        $this->loadRegister();
    }

    /** @return Collection<int, string> */
    public function classOptions(): Collection
    {
        $own = $this->ownStreams();

        return SchoolClass::where('school_id', auth()->user()?->school_id)
            ->when($own, fn ($q) => $q->whereIn('id', array_values($own)))
            ->orderBy('level')
            ->orderBy('name')
            ->pluck('name', 'id');
    }

    /** @return Collection<int, string> */
    public function sectionOptions(): Collection
    {
        $own = $this->ownStreams();

        return $this->classId
            ? Section::where('school_class_id', $this->classId)
                ->when($own, fn ($q) => $q->whereIn('id', array_keys($own)))
                ->orderBy('name')
                ->pluck('name', 'id')
            : collect();
    }

    public function day(): CarbonImmutable
    {
        try {
            return CarbonImmutable::parse($this->date ?: 'today')->startOfDay();
        } catch (\Throwable) {
            return CarbonImmutable::today();
        }
    }

    /**
     * Active learners in the chosen class (and stream), if the user may
     * take its register.
     */
    /**
     * @return Collection<int, Student>
     */
    #[Computed]
    public function students(): Collection
    {
        $own = $this->ownStreams();

        if (! $this->classId || ! $this->classOptions()->has($this->classId)) {
            return collect();
        }

        // A class teacher must pick one of their own streams.
        if ($own && ! isset($own[(int) $this->sectionId])) {
            return collect();
        }

        return Student::where('school_id', auth()->user()?->school_id)
            ->where('school_class_id', $this->classId)
            ->where('status', 'active')
            ->when($this->sectionId, fn ($q) => $q->where('section_id', $this->sectionId))
            ->orderBy('name')
            ->get();
    }

    protected function loadRegister(): void
    {
        unset($this->students);
        $this->statuses = $this->notes = [];

        $ids = $this->students->pluck('id');
        $records = AttendanceRecord::whereIn('student_id', $ids)
            ->whereDate('date', $this->day()->toDateString())
            ->get()
            ->keyBy('student_id');

        $this->alreadyTaken = $records->isNotEmpty();

        foreach ($ids as $id) {
            $this->statuses[$id] = $records->get($id)->status ?? 'present';
            $this->notes[$id] = $records->get($id)?->note;
        }
    }

    public function markAll(string $status): void
    {
        if (! isset(AttendanceRecord::STATUSES[$status])) {
            return;
        }

        foreach (array_keys($this->statuses) as $id) {
            $this->statuses[$id] = $status;
        }
    }

    public function save(): void
    {
        $day = $this->day();

        if ($day->isFuture()) {
            Notification::make()->title('That day has not come yet')->body('Registers can be taken for today or an earlier day.')->danger()->send();

            return;
        }

        $students = $this->students;

        if ($students->isEmpty()) {
            return;
        }

        $schoolId = (int) auth()->user()?->school_id;

        DB::transaction(function () use ($students, $day, $schoolId) {
            foreach ($students as $student) {
                $status = $this->statuses[$student->id] ?? 'present';

                AttendanceRecord::updateOrCreate(
                    ['student_id' => $student->id, 'date' => $day->toDateString()],
                    [
                        'school_id' => $schoolId,
                        'school_class_id' => $student->school_class_id,
                        'status' => isset(AttendanceRecord::STATUSES[$status]) ? $status : 'present',
                        'note' => trim((string) ($this->notes[$student->id] ?? '')) ?: null,
                        'recorded_by' => auth()->id(),
                    ],
                );
            }
        });

        $this->alreadyTaken = true;
        $counts = collect($this->statuses)->only($students->pluck('id')->all())->countBy();

        Notification::make()
            ->title('Register saved')
            ->body(($counts['present'] ?? 0) + ($counts['late'] ?? 0).' present, '.($counts['absent'] ?? 0).' absent'.(($counts['excused'] ?? 0) ? ', '.$counts['excused'].' excused' : '').'.')
            ->success()
            ->send();
    }

    /**
     * Absent records of the saved register for the chosen day.
     *
     * @return Collection<int, AttendanceRecord>
     */
    protected function absentRecords(): Collection
    {
        return AttendanceRecord::whereIn('student_id', $this->students->pluck('id'))
            ->whereDate('date', $this->day()->toDateString())
            ->where('status', 'absent')
            ->with(['student.guardian', 'student.school'])
            ->get();
    }

    public function textAbsentParentsAction(): Action
    {
        return Action::make('textAbsentParents')
            ->label('Text parents of absent learners')
            ->icon('heroicon-o-chat-bubble-left-right')
            ->color('gray')
            ->visible(fn (): bool => $this->alreadyTaken && $this->day()->isToday())
            ->requiresConfirmation()
            ->modalDescription(function (): string {
                $absent = $this->absentRecords();
                $waiting = $absent->whereNull('parent_texted_at')->count();

                return $absent->isEmpty()
                    ? 'Nobody is marked absent in the saved register.'
                    : "One SMS to the family of each of the {$waiting} absent ".str('learner')->plural($waiting).' not yet texted today. Save the register first if you have changed it.';
            })
            ->modalSubmitActionLabel('Send texts')
            ->action(function (): void {
                $result = app(ParentMessages::class)->sendAbsences($this->absentRecords());

                Notification::make()
                    ->title("{$result['sent']} ".str('parent')->plural($result['sent']).' texted')
                    ->body(collect([
                        $result['no_phone'] ? "{$result['no_phone']} without a phone number" : null,
                        $result['failed'] ? "{$result['failed']} failed" : null,
                        $result['already'] ? "{$result['already']} already texted" : null,
                    ])->filter()->implode(', ') ?: null)
                    ->success()
                    ->send();
            });
    }
}
