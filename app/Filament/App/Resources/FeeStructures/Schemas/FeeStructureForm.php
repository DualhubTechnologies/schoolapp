<?php

namespace App\Filament\App\Resources\FeeStructures\Schemas;

use App\Filament\Support\MoneyInput;
use App\Models\ClassLevel;
use App\Models\FeeStructure;
use App\Models\ResidencyType;
use App\Models\SchoolClass;
use App\Models\Term;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

/**
 * Adding a fee goes in the order a bursar thinks: which fee (a tap on a
 * common one fills in sensible settings), which classes, when it is
 * charged, and the amount - the same for everyone, different for Day and
 * Boarding, or for one residency only. Beside it, a live preview of the
 * fees that will be added, and any already set that will be skipped.
 *
 * Editing works on one fee (one class, one residency), so it shows that
 * fee's class, residency and amount instead of the steps.
 */
class FeeStructureForm
{
    /**
     * Fees Ugandan schools commonly charge: name => [how often, who pays
     * what]. "each" = Day and Boarding pay different amounts; "day" and
     * "boarding" = only that residency (matched by name).
     *
     * @var array<string, array{0: string, 1: string}>
     */
    public const COMMON_FEES = [
        'Tuition' => ['per_term', 'each'],
        'Boarding' => ['per_term', 'boarding'],
        'Lunch' => ['per_term', 'day'],
        'Medical' => ['per_term', 'all'],
        'Computer' => ['per_term', 'all'],
        'Laboratory' => ['per_term', 'all'],
        'Admission' => ['once', 'all'],
        'Development' => ['once', 'all'],
        'Uniform' => ['once', 'each'],
        'ID card' => ['once', 'all'],
        'UNEB registration' => ['on_demand', 'all'],
    ];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(['default' => 1, 'lg' => 3])
            ->components([

                Group::make([

                    // ── 1. The fee ──
                    Section::make(fn (string $operation): string => $operation === 'create' ? '1. The fee' : 'Fee')
                        ->description(fn (string $operation): ?string => $operation === 'create' ? 'Tap a common fee, or type the name of your own.' : null)
                        ->icon('heroicon-o-banknotes')
                        ->schema([
                            // Only a Super Admin works across schools; everyone
                            // else is fixed to their own (set on create).
                            Select::make('school_id')
                                ->relationship('school', 'name')
                                ->default(fn () => auth()->user()->school_id)
                                ->visible(fn () => auth()->user()->hasRole('Super Admin'))
                                ->live()
                                ->required(),

                            ToggleButtons::make('common_fee')
                                ->hiddenLabel()
                                ->options(array_combine(array_keys(self::COMMON_FEES), array_keys(self::COMMON_FEES)))
                                ->inline()
                                ->live()
                                ->dehydrated(false)
                                ->visibleOn('create')
                                ->afterStateUpdated(fn (?string $state, Set $set, Get $get) => static::applyCommonFee($state, $set, $get)),

                            TextInput::make('name')
                                ->label('Fee name')
                                ->placeholder('e.g. Tuition, Lunch, Admission, Development')
                                ->required()
                                ->maxLength(100)
                                ->live(onBlur: true)
                                ->afterStateUpdated(function (?string $state, Set $set, Get $get): void {
                                    if ($state !== $get('common_fee')) {
                                        $set('common_fee', null);
                                    }
                                }),

                            // Editing is one fee for one class.
                            Select::make('school_class_id')
                                ->label('Class')
                                ->relationship(
                                    'schoolClass',
                                    'name',
                                    fn (Builder $query, Get $get) => static::scopeClasses($query, $get('school_id')),
                                )
                                ->searchable()
                                ->preload()
                                ->required()
                                ->live()
                                ->visibleOn('edit'),
                        ]),

                    // ── 2. Classes ──
                    Section::make('2. Classes')
                        ->description('Tick every class that pays this fee at the same amount. Classes that pay a different amount are added in another go.')
                        ->icon('heroicon-o-academic-cap')
                        ->visibleOn('create')
                        ->schema([
                            CheckboxList::make('class_ids')
                                ->hiddenLabel()
                                ->options(fn (Get $get) => static::classOptions($get('school_id')))
                                ->columns(['default' => 3, 'sm' => 4, 'xl' => 6])
                                ->gridDirection('row')
                                ->bulkToggleable()
                                ->required()
                                ->validationMessages(['required' => 'Tick at least one class.'])
                                ->live()
                                ->dehydrated(false)
                                ->hintActions(static::levelActions(null)),
                        ]),

                    // ── 3. When it is charged ──
                    Section::make(fn (string $operation): string => $operation === 'create' ? '3. When it is charged' : 'When it is charged')
                        ->icon('heroicon-o-arrow-path')
                        ->columns(['default' => 1, 'md' => 2])
                        ->schema([
                            ToggleButtons::make('frequency')
                                ->label('How often')
                                ->options(FeeStructure::FREQUENCIES)
                                ->icons([
                                    'per_term' => 'heroicon-o-arrow-path',
                                    'once' => 'heroicon-o-user-plus',
                                    'on_demand' => 'heroicon-o-hand-raised',
                                ])
                                ->colors([
                                    'per_term' => 'success',
                                    'once' => 'warning',
                                    'on_demand' => 'gray',
                                ])
                                ->helperText(fn (Get $get): string => match ($get('frequency')) {
                                    'once' => 'Paid once by new learners, in their first term (admission, development, uniform). Continuing learners never pay it.',
                                    'on_demand' => 'Never billed by itself: you bill it to the learners it concerns from Billing (UNEB registration, a trip).',
                                    default => 'Billed to the learners every term, from the term you choose (tuition, lunch, medical).',
                                })
                                ->default('per_term')
                                ->live()
                                ->inline()
                                ->required()
                                ->columnSpanFull(),

                            // For a termly fee this is the FIRST term it is
                            // charged in; it carries on into later terms by
                            // itself (see FeeStructure::termlyFor).
                            Select::make('term_id')
                                ->label('Starting from')
                                ->options(fn (Get $get) => static::termOptions($get('school_id')))
                                ->default(fn () => Term::current()?->getKey())
                                ->searchable()
                                ->native(false)
                                ->live()
                                ->required(fn (Get $get) => $get('frequency') === 'per_term')
                                ->visible(fn (Get $get) => $get('frequency') === 'per_term')
                                ->helperText('Charged this term and every term after, until you set a new amount.'),

                            ToggleButtons::make('applies_to')
                                ->label('Who pays')
                                ->options(FeeStructure::APPLIES_TO)
                                ->icons([
                                    'all' => 'heroicon-o-users',
                                    'new_only' => 'heroicon-o-user-plus',
                                ])
                                ->colors([
                                    'all' => 'info',
                                    'new_only' => 'warning',
                                ])
                                ->default('all')
                                ->live()
                                ->inline()
                                ->required()
                                // An admission fee is always for new learners,
                                // and a fee billed by hand goes to whoever it
                                // is billed to: the choice is for termly fees.
                                ->visible(fn (Get $get): bool => $get('frequency') === 'per_term'),
                        ]),

                    // ── 4. Amount ──
                    Section::make(fn (string $operation): string => $operation === 'create' ? '4. Amount' : 'Amount')
                        ->icon('heroicon-o-currency-dollar')
                        ->schema([
                            // Who pays what: everyone the same, Day and
                            // Boarding each their own, or one residency.
                            ToggleButtons::make('amount_for')
                                ->label('Who pays it')
                                ->options(fn (Get $get): array => static::amountForOptions($get('school_id')))
                                ->default('all')
                                ->inline()
                                ->live()
                                ->dehydrated(false)
                                ->visible(fn (Get $get, string $operation): bool => $operation === 'create' && static::residencyOptions($get('school_id')) !== []),

                            // Editing: one fee, for everyone or one residency.
                            Select::make('residency_type_id')
                                ->label('Residency')
                                ->options(fn (Get $get) => static::residencyOptions($get('school_id')))
                                ->placeholder('All learners')
                                ->native(false)
                                ->live()
                                ->helperText('Leave as "All learners" unless only boarders or only day scholars pay this.')
                                ->visibleOn('edit'),

                            MoneyInput::make('amount')
                                ->label(fn (Get $get): string => static::amountLabel($get))
                                ->numeric()
                                ->prefix('UGX')
                                ->required(fn (Get $get): bool => $get('amount_for') !== 'each')
                                ->minValue(0)
                                ->live(onBlur: true)
                                ->hidden(fn (Get $get): bool => $get('amount_for') === 'each')
                                ->extraInputAttributes(['class' => 'text-lg font-semibold']),

                            Grid::make(['default' => 1, 'sm' => 2])
                                ->schema(fn (Get $get): array => collect(static::residencyOptions($get('school_id')))
                                    ->map(fn (string $name, int $id) => MoneyInput::make("residency_amounts.{$id}")
                                        ->label("{$name} learners pay")
                                        ->prefix('UGX')
                                        ->minValue(0)
                                        ->placeholder('Empty if they do not pay it')
                                        ->live(onBlur: true)
                                        ->extraInputAttributes(['class' => 'text-lg font-semibold']))
                                    ->values()
                                    ->all())
                                ->visible(fn (Get $get): bool => $get('amount_for') === 'each'),

                            Toggle::make('is_active')
                                ->label('Active')
                                ->helperText('Inactive fees are left out of billing.')
                                ->default(true)
                                ->live()
                                ->visibleOn('edit'),
                        ]),

                ])->columnSpan(['default' => 1, 'lg' => 2]),

                // ── Beside the steps: what will be added ──
                Group::make([
                    Section::make(fn (string $operation): string => $operation === 'create' ? 'What will be added' : 'Summary')
                        ->icon('heroicon-o-document-check')
                        ->schema([
                            Text::make(fn (Get $get, string $operation) => $operation === 'create'
                                ? static::preview($get)
                                : static::summary($get)),
                        ]),

                    Section::make('Notes')
                        ->icon('heroicon-o-pencil-square')
                        ->collapsible()
                        ->collapsed(fn (string $operation): bool => $operation === 'create')
                        ->compact()
                        ->schema([
                            Textarea::make('description')
                                ->hiddenLabel()
                                ->placeholder('Optional: what the fee covers. Not printed for parents.')
                                ->rows(3),
                        ]),
                ])
                    ->columnSpan(1)
                    ->extraAttributes(['class' => 'sh-fee-aside']),
            ]);
    }

    /**
     * Tapping a common fee fills in its name, how often it is charged, and
     * who pays what. Everything can still be changed.
     */
    protected static function applyCommonFee(?string $fee, Set $set, Get $get): void
    {
        if (! $fee || ! isset(self::COMMON_FEES[$fee])) {
            return;
        }

        [$frequency, $payers] = self::COMMON_FEES[$fee];

        $set('name', $fee);
        $set('frequency', $frequency);
        $set('applies_to', 'all');

        if ($frequency === 'per_term' && blank($get('term_id'))) {
            $set('term_id', Term::current()?->getKey());
        }

        $residencies = static::residencyOptions($get('school_id'));

        $set('amount_for', match ($payers) {
            'each' => count($residencies) > 1 ? 'each' : 'all',
            'day', 'boarding' => ($id = collect($residencies)->search(fn (string $name): bool => str_contains(mb_strtolower($name), $payers === 'day' ? 'day' : 'board')))
                ? "only:{$id}"
                : 'all',
            default => 'all',
        });
    }

    /**
     * @return array<string, string> e.g. all => Everyone, each => Day and Boarding differently, only:3 => Only Boarding
     */
    protected static function amountForOptions(int|string|null $schoolId): array
    {
        $residencies = static::residencyOptions($schoolId);

        $options = ['all' => 'Everyone, same amount'];

        if (count($residencies) > 1) {
            $options['each'] = static::residencyList($schoolId).' pay differently';
        }

        foreach ($residencies as $id => $name) {
            $options["only:{$id}"] = "Only {$name} learners";
        }

        return $options;
    }

    protected static function amountLabel(Get $get): string
    {
        $only = static::onlyResidency($get('amount_for'));

        return $only ? 'Amount for '.(static::residencyOptions($get('school_id'))[$only] ?? 'them') : 'Amount';
    }

    /** The residency picked under "Only ... learners", or null. */
    public static function onlyResidency(mixed $amountFor): ?int
    {
        return is_string($amountFor) && str_starts_with($amountFor, 'only:') ? (int) substr($amountFor, 5) : null;
    }

    /**
     * The fees that saving will add, as the bursar will see them on the
     * list: per residency, its amount and the classes; plus any already
     * set, which are skipped.
     */
    protected static function preview(Get $get): HtmlString
    {
        $e = fn ($value): string => e((string) $value);
        $schoolId = $get('school_id') ?: auth()->user()?->school_id;
        $name = trim((string) $get('name'));
        $classIds = array_values(array_filter((array) $get('class_ids')));
        $residencies = static::residencyOptions($schoolId);

        if ($name === '' || $classIds === []) {
            return new HtmlString('<p class="sh-fee-hint">Choose the fee and tick its classes to see what will be added.</p>');
        }

        // Who pays what: residency id (null = everyone) => amount.
        $amounts = match (true) {
            $get('amount_for') === 'each' => collect((array) $get('residency_amounts'))
                ->filter(fn ($amount): bool => is_numeric(str_replace(',', '', (string) $amount)))
                ->mapWithKeys(fn ($amount, $id): array => [(int) $id => (float) str_replace(',', '', (string) $amount)])
                ->all(),
            default => [static::onlyResidency($get('amount_for')) ?? 0 => is_numeric(str_replace(',', '', (string) $get('amount'))) ? (float) str_replace(',', '', (string) $get('amount')) : null],
        };

        $frequency = $get('frequency');
        $termId = $frequency === 'per_term' ? $get('term_id') : null;
        $classes = SchoolClass::inOrder($schoolId);

        $existing = FeeStructure::query()
            ->where('school_id', $schoolId)
            ->whereIn('school_class_id', $classIds)
            ->where('name', $name)
            ->where('term_id', $termId)
            ->get(['school_class_id', 'residency_type_id']);

        $rows = '';
        $added = 0;
        $skipped = [];

        foreach ($amounts as $residencyId => $amount) {
            $taken = $existing->filter(fn (FeeStructure $fee): bool => (int) $fee->residency_type_id === (int) $residencyId)->pluck('school_class_id')->all();
            $new = array_values(array_diff($classIds, $taken));
            $who = $residencyId ? ($residencies[$residencyId] ?? 'Residency') : 'Everyone';

            if ($taken !== []) {
                $skipped[] = SchoolClass::joinNames($classes, $taken).($residencyId ? ' ('.$who.')' : '');
            }

            if ($new === []) {
                continue;
            }

            $added += count($new);
            $rows .= '<tr><td>'.$e($who).'<span>'.$e(SchoolClass::joinNames($classes, $new)).'</span></td>'
                .'<td>'.($amount === null ? '<em>amount?</em>' : 'UGX '.number_format($amount)).'</td></tr>';
        }

        $when = match ($frequency) {
            'once' => 'Paid once, by new learners in their first term',
            'on_demand' => 'Billed only when you bill it',
            default => ($termId ? 'Every term from '.$e(Term::with('academicYear')->whereKey($termId)->first()?->label()) : 'Every term')
                .($get('applies_to') === 'new_only' ? ', new learners only' : ''),
        };

        $html = '<div class="sh-fee-preview">'
            .'<p class="sh-fee-name">'.$e($name).'</p>'
            .'<p class="sh-fee-when">'.$when.'</p>';

        $html .= $rows !== ''
            ? '<table>'.$rows.'</table><p class="sh-fee-count">'.$added.' '.($added === 1 ? 'fee' : 'fees').' will be added.</p>'
            : '<p class="sh-fee-hint">'.($amounts === [] ? 'Type at least one amount.' : 'Nothing new to add.').'</p>';

        if ($skipped !== []) {
            $html .= '<p class="sh-fee-skip"><strong>Already set, will be skipped:</strong> '.$e(implode('; ', $skipped)).'. To change its amount, open it from Fee Setup, or update it when you start the term.</p>';
        }

        return new HtmlString($html.'</div>');
    }

    /**
     * Editing one fee: "Tuition - UGX 850,000, charged every term from
     * Term 1 - 2026 to all day learners in S.1."
     */
    protected static function summary(Get $get): HtmlString
    {
        $e = fn ($value) => e((string) $value);

        $name = trim((string) $get('name')) ?: 'This fee';
        $amount = is_numeric($get('amount')) ? 'UGX '.number_format((float) $get('amount')) : 'an amount not yet set';
        $class = SchoolClass::whereKey($get('school_class_id'))->value('name') ?? 'the class';

        $residency = $get('residency_type_id')
            ? mb_strtolower((string) ResidencyType::whereKey($get('residency_type_id'))->value('name')).' learners'
            : 'learners';

        $who = ($get('frequency') === 'once' || ($get('frequency') === 'per_term' && $get('applies_to') === 'new_only') ? 'new ' : 'all ').$residency;

        $when = match ($get('frequency')) {
            'per_term' => ($termId = $get('term_id'))
                ? 'every term from <strong>'.$e(Term::with('academicYear')->whereKey($termId)->first()?->label()).'</strong> onwards'
                : 'every term (choose the starting term)',
            'once' => 'once, on admission,',
            'on_demand' => 'only when you bill it to a class or learner from Billing',
            default => '',
        };

        $html = '<p class="text-sm leading-6 text-gray-700 dark:text-gray-300">'
            .'<strong>'.$e($name).'</strong> - <strong>'.$e($amount).'</strong>, charged '
            .$when.' to '.$e($who).' in <strong>'.$e($class).'</strong>.</p>';

        if (! $get('is_active')) {
            $html .= '<p class="mt-2 text-sm text-amber-700 dark:text-amber-400">Inactive - it won\'t be billed until switched on.</p>';
        }

        return new HtmlString($html);
    }

    /**
     * "All O-Level", "All A-Level"... beside the classes: one tap ticks a
     * whole section of the school.
     *
     * @return array<int, Action>
     */
    protected static function levelActions(int|string|null $schoolId): array
    {
        $schoolId = $schoolId ?: auth()->user()?->school_id;

        return ClassLevel::query()
            ->where('school_id', $schoolId)
            ->orderBy('sort_order')
            ->get()
            ->map(function (ClassLevel $level) use ($schoolId): ?Action {
                $ids = SchoolClass::where('school_id', $schoolId)->where('class_level_id', $level->getKey())->pluck('id')->all();

                return $ids === [] ? null : Action::make("level{$level->getKey()}")
                    ->label("All {$level->name}")
                    ->link()
                    ->action(fn (Set $set) => $set('class_ids', $ids));
            })
            ->filter()
            ->values()
            ->all();
    }

    /** "Day and Boarding", for the per-residency choice. */
    protected static function residencyList(int|string|null $schoolId): string
    {
        $names = array_values(static::residencyOptions($schoolId));

        return count($names) > 1
            ? implode(', ', array_slice($names, 0, -1)).' and '.last($names)
            : 'Each residency';
    }

    /** @return array<int, string> */
    protected static function classOptions(int|string|null $schoolId): array
    {
        $schoolId = $schoolId ?: auth()->user()?->school_id;

        if (! $schoolId) {
            return [];
        }

        return SchoolClass::inOrder($schoolId)->pluck('name', 'id')->all();
    }

    /** @return array<int, string> */
    protected static function termOptions(int|string|null $schoolId): array
    {
        $schoolId = $schoolId ?: auth()->user()?->school_id;

        if (! $schoolId) {
            return [];
        }

        return Term::where('school_id', $schoolId)
            ->with('academicYear')
            ->get()
            ->sortBy(fn (Term $t) => $t->sortKey())
            ->mapWithKeys(fn (Term $t) => [$t->id => $t->label()])
            ->all();
    }

    /** @return array<int, string> */
    public static function residencyOptions(int|string|null $schoolId): array
    {
        $schoolId = $schoolId ?: auth()->user()?->school_id;

        if (! $schoolId) {
            return [];
        }

        return ResidencyType::where('school_id', $schoolId)
            ->where('is_active', true)
            ->orderBy('id')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @param  Builder<SchoolClass>  $query
     * @return Builder<SchoolClass>
     */
    protected static function scopeClasses(Builder $query, int|string|null $schoolId): Builder
    {
        $schoolId = $schoolId ?: auth()->user()?->school_id;

        if ($schoolId) {
            $query->where('school_id', $schoolId);
        }

        return $query->orderBy('level')->orderBy('name');
    }
}
