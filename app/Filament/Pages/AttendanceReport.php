<?php

namespace App\Filament\Pages;

use App\Models\AttendanceRecord;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\Term;
use App\Services\Attendance\AttendanceSummary;
use App\Services\Attendance\LearnerAttendance;
use App\Support\Modules;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Attendance over a period (this term by default): today's registers
 * across the school, and each learner's days present, absent, late and
 * excused for a class -- lowest attendance first, so the learners to
 * follow up stand out.
 *
 * @property-read Collection<int, LearnerAttendance> $rows
 */
class AttendanceReport extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartLine;

    protected static string|\UnitEnum|null $navigationGroup = 'Students';

    protected static ?int $navigationSort = 21;

    protected static ?string $title = 'Attendance Report';

    protected string $view = 'filament.pages.attendance-report';

    public ?int $classId = null;

    public ?int $sectionId = null;

    public string $from = '';

    public string $to = '';

    public static function canAccess(): bool
    {
        return Modules::allows('attendance');
    }

    public function mount(): void
    {
        $term = Term::current();
        $this->from = ($term->start_date ?? now()->startOfMonth())->toDateString();
        $this->to = min(now(), $term->end_date ?? now())->toDateString();
        $this->classId = request()->integer('class') ?: null;
    }

    public function updatedClassId(): void
    {
        $this->sectionId = null;
    }

    /** @return Collection<int, string> */
    public function classOptions(): Collection
    {
        return SchoolClass::where('school_id', auth()->user()?->school_id)->orderBy('level')->orderBy('name')->pluck('name', 'id');
    }

    /** @return Collection<int, string> */
    public function sectionOptions(): Collection
    {
        return $this->classId ? Section::where('school_class_id', $this->classId)->orderBy('name')->pluck('name', 'id') : collect();
    }

    protected function date(string $value, CarbonImmutable $fallback): CarbonImmutable
    {
        try {
            return $value === '' ? $fallback : CarbonImmutable::parse($value);
        } catch (\Throwable) {
            return $fallback;
        }
    }

    public function fromDate(): CarbonImmutable
    {
        return $this->date($this->from, CarbonImmutable::today()->startOfMonth());
    }

    public function toDate(): CarbonImmutable
    {
        return $this->date($this->to, CarbonImmutable::today());
    }

    /**
     * Today, class by class: learners, how many present, absent, and
     * whether the register has been taken.
     *
     * @return Collection<int, array{class: string, id: int, learners: int, taken: int, present: int, absent: int}>
     */
    public function today(): Collection
    {
        $schoolId = auth()->user()?->school_id;

        $learners = Student::where('school_id', $schoolId)->where('status', 'active')
            ->selectRaw('school_class_id, count(*) as total')->groupBy('school_class_id')->pluck('total', 'school_class_id');

        $records = AttendanceRecord::where('school_id', $schoolId)->whereDate('date', today()->toDateString())
            ->selectRaw('school_class_id, status, count(*) as total')->groupBy('school_class_id', 'status')->get();

        return SchoolClass::where('school_id', $schoolId)->orderBy('level')->orderBy('name')->get(['id', 'name'])
            ->filter(fn (SchoolClass $c) => ($learners[$c->id] ?? 0) > 0)
            ->map(function (SchoolClass $class) use ($learners, $records): array {
                $mine = $records->where('school_class_id', $class->id)->pluck('total', 'status')->map(fn ($n) => (int) $n);

                return [
                    'class' => (string) $class->name,
                    'id' => $class->id,
                    'learners' => (int) $learners[$class->id],
                    'taken' => (int) $mine->sum(),
                    'present' => (int) (($mine['present'] ?? 0) + ($mine['late'] ?? 0)),
                    'absent' => (int) ($mine['absent'] ?? 0),
                ];
            })
            ->values()
            ->toBase();
    }

    /**
     * @return Collection<int, LearnerAttendance>
     */
    #[Computed]
    public function rows(): Collection
    {
        if (! $this->classId || ! $this->classOptions()->has($this->classId)) {
            return collect();
        }

        $students = Student::where('school_id', auth()->user()?->school_id)
            ->where('school_class_id', $this->classId)
            ->where('status', 'active')
            ->when($this->sectionId, fn ($q) => $q->where('section_id', $this->sectionId))
            ->orderBy('name')
            ->get();

        $summary = app(AttendanceSummary::class)->forStudents($students->modelKeys(), $this->fromDate(), $this->toDate());

        return $students->toBase()
            ->map(fn (Student $student) => new LearnerAttendance(
                student: $student,
                days: $summary[$student->id]['days'],
                present: $summary[$student->id]['present'],
                late: $summary[$student->id]['late'],
                absent: $summary[$student->id]['absent'],
                excused: $summary[$student->id]['excused'],
                rate: $summary[$student->id]['rate'],
            ))
            ->sortBy(fn (LearnerAttendance $row) => [$row->rate ?? 101, $row->student->name])
            ->values();
    }

    public function exportCsv(): ?StreamedResponse
    {
        $rows = $this->rows;

        if ($rows->isEmpty()) {
            return null;
        }

        $class = $this->classOptions()[$this->classId] ?? 'class';
        $filename = str("{$class} attendance {$this->fromDate()->toDateString()} to {$this->toDate()->toDateString()}")->slug().'.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');

            if ($out === false) {
                return;
            }

            fputcsv($out, ['Adm. No.', 'Name', 'Days recorded', 'Present', 'Late', 'Absent', 'Excused', 'Attendance %']);

            foreach ($rows as $row) {
                fputcsv($out, [$row->student->admission_no, $row->student->name, $row->days, $row->present, $row->late, $row->absent, $row->excused, $row->rate]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
