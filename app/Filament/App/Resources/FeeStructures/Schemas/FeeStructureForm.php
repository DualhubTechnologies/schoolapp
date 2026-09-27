<?php

namespace App\Filament\App\Resources\FeeStructures\Schemas;

use App\Models\FeeStructure;
use App\Models\ResidencyType;
use App\Models\SchoolClass;
use App\Models\Term;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

/**
 * Two columns on wide screens: what the fee is and how it is charged on
 * the left, the amount and a plain-language summary on the right. Stacks
 * into one column on small screens.
 */
class FeeStructureForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(['default' => 1, 'lg' => 3])
            ->components([

                Group::make([

                    // ── What & where ──
                    Section::make('Fee details')
                        ->description('What the fee is called and which classes pay it.')
                        ->icon('heroicon-o-banknotes')
                        ->columns(2)
                        ->schema([
                            // Only a Super Admin works across schools; everyone
                            // else is fixed to their own (set on create).
                            Select::make('school_id')
                                ->relationship('school', 'name')
                                ->default(fn () => auth()->user()->school_id)
                                ->visible(fn () => auth()->user()->hasRole('Super Admin'))
                                ->live()
                                ->required()
                                ->columnSpanFull(),

                            TextInput::make('name')
                                ->label('Fee name')
                                ->placeholder('e.g. Tuition, Boarding, Admission, Development')
                                ->required()
                                ->maxLength(100)
                                ->live(onBlur: true),

                            Select::make('class_ids')
                                ->label('Classes')
                                ->helperText('A separate fee is created for each class, so you can adjust one later.')
                                ->multiple()
                                ->options(fn (Get $get) => static::classOptions($get('school_id')))
                                ->searchable()
                                ->preload()
                                ->required()
                                ->live()
                                ->dehydrated(false)
                                ->visibleOn('create'),

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

                    // ── How it's charged ──
                    Section::make('Charging rules')
                        ->description('How often the fee is charged, and to whom.')
                        ->icon('heroicon-o-arrow-path')
                        ->columns(2)
                        ->schema([
                            ToggleButtons::make('frequency')
                                ->hintIcon('heroicon-m-question-mark-circle', tooltip: 'Every term: billed each term. Once: billed a single time, e.g. admission. On demand: charged to a learner only when you add it, e.g. a lost textbook.')
                                ->label('How often')
                                ->options(FeeStructure::FREQUENCIES)
                                ->icons([
                                    'per_term' => 'heroicon-o-arrow-path',
                                    'once' => 'heroicon-o-check-badge',
                                    'on_demand' => 'heroicon-o-hand-raised',
                                ])
                                ->colors([
                                    'per_term' => 'success',
                                    'once' => 'warning',
                                    'on_demand' => 'gray',
                                ])
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
                                ->helperText('Charged this term and every term after. To change the amount later, add the same fee again from the term the new amount starts.'),

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
                                ->required(),

                            // Null means everyone pays it, so a school can
                            // either price tuition separately per residency,
                            // or keep one tuition and add a boarding-only
                            // charge on top. Their choice, not ours.
                            Select::make('residency_type_id')
                                ->label('Residency')
                                ->options(fn (Get $get) => static::residencyOptions($get('school_id')))
                                ->placeholder('All students')
                                ->native(false)
                                ->live()
                                ->helperText('Leave as "All students" unless only boarders or only day students pay this.')
                                ->columnSpanFull(),
                        ]),

                ])->columnSpan(['default' => 1, 'lg' => 2]),

                Group::make([

                    // ── Amount ──
                    Section::make('Amount')
                        ->icon('heroicon-o-currency-dollar')
                        ->schema([
                            TextInput::make('amount')
                                ->label('Fee amount')
                                ->numeric()
                                ->prefix('UGX')
                                ->required()
                                ->minValue(0)
                                ->live(onBlur: true)
                                ->extraInputAttributes(['class' => 'text-lg font-semibold']),

                            Toggle::make('is_active')
                                ->label('Active')
                                ->helperText('Inactive fees are left out of billing.')
                                ->default(true)
                                ->live(),

                            Textarea::make('description')
                                ->label('Notes')
                                ->placeholder('Optional — what the fee covers')
                                ->rows(3),
                        ]),

                    // ── Plain-language read-back of the choices above ──
                    Section::make('Summary')
                        ->icon('heroicon-o-document-check')
                        ->schema([
                            Text::make(fn (Get $get, string $operation) => static::summary($get, $operation)),
                        ]),

                ])->columnSpan(1),
            ]);
    }

    /**
     * "Tuition — UGX 850,000, charged every term from Term 1 — 2026 to
     * all boarders in S1 and S2."
     */
    protected static function summary(Get $get, string $operation): HtmlString
    {
        $e = fn ($value) => e((string) $value);

        $name = trim((string) $get('name')) ?: 'This fee';
        $amount = is_numeric($get('amount')) ? 'UGX '.number_format((float) $get('amount')) : 'an amount not yet set';

        $classIds = $operation === 'create' ? (array) $get('class_ids') : array_filter([$get('school_class_id')]);
        $classNames = SchoolClass::whereKey($classIds)->orderBy('name')->pluck('name')->all();
        $classes = match (count($classNames)) {
            0 => 'the classes you choose',
            1 => $classNames[0],
            default => implode(', ', array_slice($classNames, 0, -1)).' and '.last($classNames),
        };

        $residency = $get('residency_type_id')
            ? mb_strtolower((string) ResidencyType::whereKey($get('residency_type_id'))->value('name')).' students'
            : 'students';

        $who = ($get('applies_to') === 'new_only' ? 'new ' : 'all ').$residency;

        $when = match ($get('frequency')) {
            'per_term' => ($termId = $get('term_id'))
                ? 'every term from <strong>'.$e(Term::with('academicYear')->find($termId)?->label()).'</strong> onwards'
                : 'every term (choose the starting term)',
            'once' => 'once per student, the first time they are billed',
            'on_demand' => 'only when you bill it to a class or student from Bill Students',
            default => '',
        };

        $html = '<p class="text-sm leading-6 text-gray-700 dark:text-gray-300">'
            .'<strong>'.$e($name).'</strong> — <strong>'.$e($amount).'</strong>, charged '
            .$when.' to '.$e($who).' in <strong>'.$e($classes).'</strong>.</p>';

        if (! $get('is_active')) {
            $html .= '<p class="mt-2 text-sm text-amber-700 dark:text-amber-400">Inactive — it won\'t be billed until switched on.</p>';
        }

        return new HtmlString($html);
    }

    protected static function classOptions($schoolId): array
    {
        $schoolId = $schoolId ?: auth()->user()?->school_id;

        if (! $schoolId) {
            return [];
        }

        return SchoolClass::where('school_id', $schoolId)
            ->orderBy('level')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }

    protected static function termOptions($schoolId): array
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
            ->toArray();
    }

    protected static function residencyOptions($schoolId): array
    {
        $schoolId = $schoolId ?: auth()->user()?->school_id;

        if (! $schoolId) {
            return [];
        }

        return ResidencyType::where('school_id', $schoolId)
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }

    protected static function scopeClasses(Builder $query, $schoolId): Builder
    {
        $schoolId = $schoolId ?: auth()->user()?->school_id;

        if ($schoolId) {
            $query->where('school_id', $schoolId);
        }

        return $query->orderBy('level')->orderBy('name');
    }
}
