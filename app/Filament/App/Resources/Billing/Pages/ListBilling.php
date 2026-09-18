<?php

namespace App\Filament\App\Resources\Billing\Pages;

use App\Filament\App\Resources\Billing\BillingResource;
use App\Models\FeeStructure;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Term;
use App\Services\BillingService;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Database\Eloquent\Builder;

class ListBilling extends ListRecords
{
    protected static string $resource = BillingResource::class;

    public function getTitle(): string
    {
        return 'Bill Students';
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->billTermAction(),
            $this->billClassAction(),
            $this->billStudentAction(),
        ];
    }

    /**
     * The routine term bill: charge every applicable fee to every student.
     * Safe to run repeatedly — it only adds what is missing, so it is also
     * how you apply a fee created after the first run.
     */
    protected function billTermAction(): Action
    {
        return Action::make('billTerm')
            ->label('Bill a term')
            ->icon('heroicon-o-document-plus')
            ->color('primary')
            ->schema([
                Select::make('term_id')
                    ->label('Term')
                    ->options(fn (): array => static::termOptions())
                    ->default(fn () => Term::current()?->getKey())
                    ->required()
                    ->native(false),

                Select::make('class_ids')
                    ->label('Classes')
                    ->multiple()
                    ->options(fn (): array => static::classOptions())
                    ->placeholder('All classes')
                    ->helperText('Leave empty to cover every class.')
                    ->searchable()
                    ->preload(),
            ])
            ->modalHeading('Bill a term')
            ->modalDescription('Charges the fee structures set up for that term. Fees already on a student are never charged twice, so run this again whenever you add a new fee.')
            ->modalSubmitActionLabel('Bill')
            ->action(function (array $data): void {
                $term = Term::find($data['term_id']);

                if (! $term) {
                    Notification::make()->title('Term not found')->danger()->send();

                    return;
                }

                $result = app(BillingService::class)->billTerm(
                    $term,
                    $data['class_ids'] ?: null,
                );

                Notification::make()
                    ->title($result['charges_added'] > 0
                        ? "{$result['charges_added']} charge(s) added"
                        : 'Everything already up to date')
                    ->body("{$result['students_examined']} student(s) checked, {$result['students_changed']} affected.")
                    ->success()
                    ->send();
            });
    }

    /**
     * A group charge — trip, transport, exam levy. Whole class by default,
     * with individual students selectable when the charge is optional.
     */
    protected function billClassAction(): Action
    {
        return Action::make('billClass')
            ->label('Bill a class')
            ->icon('heroicon-o-user-group')
            ->color('gray')
            ->schema([
                Select::make('term_id')
                    ->label('Term')
                    ->options(fn (): array => static::termOptions())
                    ->default(fn () => Term::current()?->getKey())
                    ->required()
                    ->native(false),

                Radio::make('source')
                    ->label('What are you charging?')
                    ->options([
                        'fee' => 'An existing fee',
                        'custom' => 'A one-off charge I will type in',
                    ])
                    ->default('fee')
                    ->live()
                    ->required(),

                Select::make('fee_structure_id')
                    ->label('Fee')
                    ->options(fn (): array => FeeStructure::query()
                        ->where('school_id', auth()->user()?->school_id)
                        ->where('is_active', true)
                        ->with(['schoolClass', 'residencyType'])
                        ->orderBy('name')
                        ->get()
                        ->mapWithKeys(fn (FeeStructure $f) => [
                            $f->id => $f->name . ' — ' . $f->formattedAmount()
                                . ($f->schoolClass?->name ? " ({$f->schoolClass->name}" : '')
                                . ($f->residencyType?->name ? ", {$f->residencyType->name})" : ($f->schoolClass?->name ? ')' : '')),
                        ])
                        ->toArray())
                    ->searchable()
                    ->visible(fn (Get $get) => $get('source') === 'fee')
                    ->required(fn (Get $get) => $get('source') === 'fee'),

                TextInput::make('description')
                    ->label('What is the charge for?')
                    ->placeholder('e.g. Kampala trip, Van transport')
                    ->maxLength(150)
                    ->visible(fn (Get $get) => $get('source') === 'custom')
                    ->required(fn (Get $get) => $get('source') === 'custom'),

                TextInput::make('amount')
                    ->label('Amount per student')
                    ->numeric()
                    ->prefix('UGX')
                    ->minValue(0)
                    ->visible(fn (Get $get) => $get('source') === 'custom')
                    ->required(fn (Get $get) => $get('source') === 'custom'),

                Select::make('class_id')
                    ->label('Class')
                    ->options(fn (): array => static::classOptions())
                    ->searchable()
                    ->live()
                    ->required(),

                Select::make('student_ids')
                    ->label('Students')
                    ->multiple()
                    ->options(fn (Get $get): array => $get('class_id')
                        ? Student::query()
                            ->where('school_id', auth()->user()?->school_id)
                            ->where('school_class_id', $get('class_id'))
                            ->where('status', 'active')
                            ->orderBy('name')
                            ->get()
                            ->mapWithKeys(fn (Student $s) => [$s->id => "{$s->name} ({$s->admission_no})"])
                            ->toArray()
                        : [])
                    ->placeholder('Everyone in the class')
                    ->helperText('Leave empty to charge the whole class. Pick students only when the charge is optional, like a trip.')
                    ->searchable()
                    ->visible(fn (Get $get) => filled($get('class_id'))),
            ])
            ->modalHeading('Bill a class')
            ->modalDescription('Adds the charge to each student\'s account for that term. Students who already carry it are skipped.')
            ->modalSubmitActionLabel('Bill')
            ->action(function (array $data): void {
                $term = Term::find($data['term_id']);

                if (! $term) {
                    Notification::make()->title('Term not found')->danger()->send();

                    return;
                }

                $studentIds = $data['student_ids'] ?: Student::query()
                    ->where('school_id', auth()->user()?->school_id)
                    ->where('school_class_id', $data['class_id'])
                    ->where('status', 'active')
                    ->pluck('id')
                    ->all();

                if ($studentIds === []) {
                    Notification::make()
                        ->title('No students to charge')
                        ->body('That class has no active students.')
                        ->warning()
                        ->send();

                    return;
                }

                $result = app(BillingService::class)->billStudents(
                    term: $term,
                    studentIds: $studentIds,
                    fee: ($data['source'] ?? 'fee') === 'fee'
                        ? FeeStructure::find($data['fee_structure_id'])
                        : null,
                    description: $data['description'] ?? null,
                    amount: isset($data['amount']) ? (float) $data['amount'] : null,
                );

                Notification::make()
                    ->title("{$result['charged']} student(s) charged")
                    ->body($result['skipped'] > 0
                        ? "{$result['skipped']} skipped — they already carry this charge."
                        : 'Added to every selected student.')
                    ->success()
                    ->send();
            });
    }

    /**
     * One charge, one student — lost book, replacement tie.
     */
    protected function billStudentAction(): Action
    {
        return Action::make('billStudent')
            ->label('Bill one student')
            ->icon('heroicon-o-plus-circle')
            ->color('gray')
            ->schema([
                Select::make('student_id')
                    ->label('Student')
                    ->getSearchResultsUsing(fn (string $search): array => Student::query()
                        ->where('school_id', auth()->user()?->school_id)
                        ->where(fn (Builder $q) => $q
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('admission_no', 'like', "%{$search}%"))
                        ->limit(30)
                        ->get()
                        ->mapWithKeys(fn (Student $s) => [$s->id => "{$s->name} ({$s->admission_no})"])
                        ->toArray())
                    ->getOptionLabelUsing(fn ($value): ?string => Student::find($value)?->name)
                    ->searchable()
                    ->required(),

                Select::make('term_id')
                    ->label('Term')
                    ->options(fn (): array => static::termOptions())
                    ->default(fn () => Term::current()?->getKey())
                    ->required()
                    ->native(false),

                TextInput::make('description')
                    ->label('What is the charge for?')
                    ->placeholder('e.g. Lost textbook, Replacement tie')
                    ->required()
                    ->maxLength(150),

                TextInput::make('amount')
                    ->label('Amount')
                    ->numeric()
                    ->prefix('UGX')
                    ->minValue(0)
                    ->required(),
            ])
            ->modalHeading('Bill one student')
            ->modalDescription('Adds a single charge to that student\'s account.')
            ->modalSubmitActionLabel('Bill')
            ->action(function (array $data): void {
                $student = Student::find($data['student_id']);
                $term = Term::find($data['term_id']);

                if (! $student || ! $term) {
                    Notification::make()->title('Student or term not found')->danger()->send();

                    return;
                }

                app(BillingService::class)->addCharge(
                    $student,
                    $term,
                    $data['description'],
                    (float) $data['amount'],
                );

                Notification::make()
                    ->title('Charge added')
                    ->body("{$data['description']} added to {$student->name}'s account.")
                    ->success()
                    ->send();
            });
    }

    // ── Shared option lists ──

    protected static function termOptions(): array
    {
        return Term::query()
            ->where('school_id', auth()->user()?->school_id)
            ->with('academicYear')
            ->get()
            ->mapWithKeys(fn (Term $t) => [$t->id => $t->label()])
            ->toArray();
    }

    protected static function classOptions(): array
    {
        return SchoolClass::query()
            ->where('school_id', auth()->user()?->school_id)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }
}
