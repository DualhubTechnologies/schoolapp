<?php

namespace App\Filament\App\Resources\StudentDiscounts\Schemas;

use App\Models\AcademicYear;
use App\Models\Student;
use App\Models\StudentDiscount;
use App\Models\Term;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class StudentDiscountForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Who gets the award')
                    ->columns(2)
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

                        Select::make('school_id')
                            ->label('School')
                            ->relationship('school', 'name')
                            ->default(fn () => auth()->user()->school_id)
                            ->disabled(fn () => ! auth()->user()->hasRole('Super Admin'))
                            ->dehydrated()
                            ->required(),

                        Select::make('reason')
                            ->label('Reason')
                            ->options(StudentDiscount::REASONS)
                            ->required()
                            ->native(false),

                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Inactive awards are ignored when billing.'),
                    ]),

                Section::make('The award')
                    ->columns(2)
                    ->schema([
                        // Picking full or half fills in the value, since those
                        // are fixed levels. Partial leaves it to the bursar.
                        Select::make('award_level')
                            ->label('Award level')
                            ->options(StudentDiscount::AWARD_LEVELS)
                            ->default('partial')
                            ->live()
                            ->required()
                            ->native(false)
                            ->afterStateUpdated(function (Set $set, ?string $state): void {
                                if ($state === 'full') {
                                    $set('type', 'percentage');
                                    $set('value', 100);
                                    $set('fee_structure_id', null);
                                }

                                if ($state === 'half') {
                                    $set('type', 'percentage');
                                    $set('value', 50);
                                }
                            })
                            ->helperText(fn (Get $get) => $get('award_level') === 'full'
                                ? 'Covers every fee in full — tuition, boarding, admission, everything.'
                                : null),

                        Select::make('type')
                            ->label('Discount type')
                            ->options(StudentDiscount::TYPES)
                            ->default('percentage')
                            ->live()
                            ->required()
                            ->native(false)
                            ->disabled(fn (Get $get) => $get('award_level') === 'full')
                            ->dehydrated()
                            ->helperText(fn (Get $get) => $get('type') === 'fixed'
                                ? 'A fixed amount comes off the total bill once.'
                                : 'A percentage comes off every fee it covers.'),

                        TextInput::make('value')
                            ->label(fn (Get $get) => $get('type') === 'fixed' ? 'Amount off' : 'Percentage off')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(fn (Get $get) => $get('type') === 'percentage' ? 100 : null)
                            ->prefix(fn (Get $get) => $get('type') === 'fixed' ? 'UGX' : null)
                            ->suffix(fn (Get $get) => $get('type') === 'percentage' ? '%' : null)
                            ->disabled(fn (Get $get) => $get('award_level') === 'full')
                            ->dehydrated()
                            ->required(),

                        Select::make('fee_structure_id')
                            ->label('What it covers')
                            ->relationship(
                                'feeStructure',
                                'name',
                                fn (Builder $query) => $query
                                    ->where('school_id', auth()->user()?->school_id)
                                    ->where('is_active', true),
                            )
                            ->searchable()
                            ->preload()
                            ->placeholder('The whole bill')
                            ->disabled(fn (Get $get) => $get('award_level') === 'full')
                            ->dehydrated()
                            // Most Ugandan awards are tuition-only, so this
                            // is worth stating rather than leaving to chance:
                            // blank quietly discounts boarding and lunch too.
                            ->helperText(fn (Get $get) => $get('award_level') === 'full'
                                ? 'A full bursary covers every fee.'
                                : 'Most scholarships cover tuition only — choose the fee. Leave blank to discount the whole bill.'),
                    ]),

                Section::make('How long it runs')
                    ->description('Awards are usually granted for a year and renewed only if grades, conduct and need still hold.')
                    ->columns(2)
                    ->schema([
                        Select::make('scope')
                            ->label('Runs for')
                            ->options(StudentDiscount::SCOPES)
                            ->default('year')
                            ->live()
                            ->required()
                            ->native(false),

                        Select::make('term_id')
                            ->label('Term')
                            ->options(fn (): array => Term::query()
                                ->where('school_id', auth()->user()?->school_id)
                                ->with('academicYear')
                                ->get()
                                ->mapWithKeys(fn (Term $t) => [$t->id => $t->label()])
                                ->toArray())
                            ->default(fn () => Term::current()?->getKey())
                            ->native(false)
                            ->visible(fn (Get $get) => $get('scope') === 'term')
                            ->required(fn (Get $get) => $get('scope') === 'term'),

                        Select::make('academic_year_id')
                            ->label('Academic year')
                            ->options(fn (): array => AcademicYear::query()
                                ->where('school_id', auth()->user()?->school_id)
                                ->orderByDesc('start_date')
                                ->pluck('name', 'id')
                                ->toArray())
                            ->default(fn () => AcademicYear::current()?->getKey())
                            ->native(false)
                            ->visible(fn (Get $get) => $get('scope') === 'year')
                            ->required(fn (Get $get) => $get('scope') === 'year')
                            ->helperText('The award stops at the end of this year. Grant a new one to renew it.'),

                        Textarea::make('notes')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
