<?php

namespace App\Filament\Pages;

use App\Models\IdCardTemplate;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Services\IdCardService;
use App\Support\Modules;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;

/**
 * Preview and print student ID cards, front and back: one student, or a
 * whole class at once. Every card is checked against IdCardService first --
 * a card missing something it needs is flagged here, and Print / Export
 * PDF stay switched off until every card in the batch is complete, so a
 * school never finds out a card is blank on the back only after cutting it.
 *
 * The front carries each learner's details; the back is the same on
 * every card. How the cards look -- orientation, colours, validity, the
 * rules on the back -- is the school's Template (IdCardTemplate), edited
 * here and remembered for every later preview, print and export.
 *
 * @property Collection<int, Student> $students
 * @property Collection<int, array<string, mixed>> $cards
 * @property IdCardTemplate $cardTemplate
 */
class IdCards extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static string|\UnitEnum|null $navigationGroup = 'Students';

    protected static ?string $navigationLabel = 'ID Cards';

    protected static ?int $navigationSort = 3;

    protected static ?string $title = 'ID Cards';

    protected string $view = 'filament.pages.id-cards';

    public ?int $classId = null;

    public ?int $sectionId = null;

    /** Set when opened for a single learner (the row action on Students). */
    public ?int $studentId = null;

    /** Set when opened for a chosen set of learners (the bulk action on Students). */
    public string $studentIds = '';

    public function mount(): void
    {
        $this->studentId = request()->integer('student') ?: null;
        $this->studentIds = (string) request()->query('students', '');
        $this->classId = request()->integer('class') ?: null;
        $this->sectionId = request()->integer('section') ?: null;
    }

    public static function canAccess(): bool
    {
        return Modules::allows('students');
    }

    public function updatedClassId(): void
    {
        $this->sectionId = null;
        unset($this->students, $this->cards);
    }

    public function updatedSectionId(): void
    {
        unset($this->students, $this->cards);
    }

    /** Only those who manage the school's settings change its template. */
    public function canEditTemplate(): bool
    {
        return Modules::allows('settings');
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('template')
                ->label('Template')
                ->icon('heroicon-o-swatch')
                ->color('gray')
                ->visible(fn (): bool => $this->canEditTemplate())
                ->modalHeading('ID card template')
                ->modalDescription('How your school\'s ID cards look. Saved for the school: every preview, print and export uses it until you change it.')
                ->modalSubmitActionLabel('Save template')
                ->fillForm(fn (): array => [
                    ...IdCardTemplate::DEFAULTS,
                    ...$this->cardTemplate->only(['orientation', 'primary_color', 'accent_color', 'validity', 'validity_months', 'back_notes']),
                    'back_notes' => $this->cardTemplate->back_notes ?? IdCardTemplate::DEFAULT_BACK_NOTES,
                ])
                ->schema([
                    ToggleButtons::make('orientation')
                        ->options(IdCardTemplate::ORIENTATIONS)
                        ->icons(['landscape' => 'heroicon-o-rectangle-group', 'portrait' => 'heroicon-o-device-phone-mobile'])
                        ->inline()
                        ->required(),
                    Grid::make(2)->schema([
                        ColorPicker::make('primary_color')
                            ->label('Main colour')
                            ->helperText('Header, footer and name.')
                            ->regex('/^#[0-9a-fA-F]{6}$/')
                            ->required(),
                        ColorPicker::make('accent_color')
                            ->label('Accent colour')
                            ->helperText('Photo frame and trim.')
                            ->regex('/^#[0-9a-fA-F]{6}$/')
                            ->required(),
                    ]),
                    Grid::make(2)->schema([
                        Select::make('validity')
                            ->label('Card valid')
                            ->options(IdCardTemplate::VALIDITY)
                            ->native(false)
                            ->live()
                            ->required(),
                        TextInput::make('validity_months')
                            ->label('Months')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(60)
                            ->visible(fn (Get $get): bool => $get('validity') === 'months')
                            ->required(fn (Get $get): bool => $get('validity') === 'months'),
                    ]),
                    Textarea::make('back_notes')
                        ->label('Rules on the back')
                        ->helperText('One rule per line; up to five are printed. The back is the same on every card.')
                        ->rows(4)
                        ->maxLength(600),
                ])
                ->action(function (array $data): void {
                    $schoolId = auth()->user()?->school_id;

                    if (! $schoolId || ! $this->canEditTemplate()) {
                        return;
                    }

                    IdCardTemplate::updateOrCreate(['school_id' => $schoolId], [
                        'orientation' => $data['orientation'],
                        'primary_color' => strtolower($data['primary_color']),
                        'accent_color' => strtolower($data['accent_color']),
                        'validity' => $data['validity'],
                        'validity_months' => (int) ($data['validity_months'] ?? 12) ?: 12,
                        'back_notes' => trim((string) ($data['back_notes'] ?? '')) ?: null,
                    ]);

                    unset($this->cardTemplate, $this->cards);

                    Notification::make()->title('Template saved')->body('Your school\'s ID cards will use it from now on.')->success()->send();
                }),
        ];
    }

    /** The school's saved template, or the defaults until it saves one. */
    #[Computed]
    public function cardTemplate(): IdCardTemplate
    {
        return IdCardTemplate::forSchool((int) auth()->user()?->school_id);
    }

    /**
     * What every card shares: school details for the header and back.
     *
     * @return array<string, mixed>|null
     */
    public function shared(): ?array
    {
        $school = auth()->user()?->school;

        return $school ? app(IdCardService::class)->schoolData($school, $this->cardTemplate) : null;
    }

    /** @return array<string, string> */
    public function design(): array
    {
        return app(IdCardService::class)->design($this->cardTemplate);
    }

    /** Clear the single-student / chosen-set filter to browse by class instead. */
    public function clearStudent(): void
    {
        $this->studentId = null;
        $this->studentIds = '';
        unset($this->students, $this->cards);
    }

    /** @return array<int, int> */
    protected function explicitStudentIds(): array
    {
        return collect(explode(',', $this->studentIds))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /** @return Collection<int, string> */
    public function classOptions(): Collection
    {
        return SchoolClass::where('school_id', auth()->user()?->school_id)
            ->orderBy('level')
            ->orderBy('name')
            ->pluck('name', 'id');
    }

    /** @return Collection<int, string> */
    public function sectionOptions(): Collection
    {
        return $this->classId
            ? Section::where('school_class_id', $this->classId)->orderBy('name')->pluck('name', 'id')
            : collect();
    }

    /** @return Collection<int, Student> */
    #[Computed]
    public function students(): Collection
    {
        $schoolId = auth()->user()?->school_id;

        if ($this->studentId) {
            return Student::where('school_id', $schoolId)->whereKey($this->studentId)->get();
        }

        if ($ids = $this->explicitStudentIds()) {
            return Student::where('school_id', $schoolId)
                ->whereKey($ids)
                ->with(['school', 'guardian', 'schoolClass', 'section', 'house', 'residencyType'])
                ->orderBy('name')
                ->get();
        }

        if (! $this->classId) {
            return collect();
        }

        return Student::where('school_id', $schoolId)
            ->where('status', 'active')
            ->where('school_class_id', $this->classId)
            ->when($this->sectionId, fn ($q) => $q->where('section_id', $this->sectionId))
            ->with(['school', 'guardian', 'schoolClass', 'section', 'house', 'residencyType'])
            ->orderBy('name')
            ->get();
    }

    /**
     * Each student paired with its card data and readiness.
     *
     * @return Collection<int, array<string, mixed>>
     */
    #[Computed]
    public function cards(): Collection
    {
        return app(IdCardService::class)->cardsFor($this->students, $this->cardTemplate);
    }

    public function notReadyCount(): int
    {
        return $this->cards->where('ready', false)->count();
    }

    public function canPrint(): bool
    {
        return $this->cards->isNotEmpty() && $this->notReadyCount() === 0;
    }

    protected function studentIdsParam(): string
    {
        return $this->students->pluck('id')->implode(',');
    }

    public function printUrl(): string
    {
        return route('filament.app.students.id-cards.print', ['students' => $this->studentIdsParam(), 'print' => 1]);
    }

    public function exportUrl(): string
    {
        return route('filament.app.students.id-cards.export', ['students' => $this->studentIdsParam()]);
    }
}
