<?php

namespace App\Filament\App\Resources\StudentDiscounts\Schemas;

use App\Models\Student;
use App\Models\StudentDiscount;
use App\Models\Term;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class StudentDiscountForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Who gets the discount')
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
                            ->helperText('Inactive discounts are ignored when invoices are generated.'),
                    ]),

                Section::make('How much comes off')
                    ->columns(2)
                    ->schema([
                        Select::make('type')
                            ->label('Discount type')
                            ->options(StudentDiscount::TYPES)
                            ->default('percentage')
                            ->live()
                            ->required()
                            ->native(false),

                        TextInput::make('value')
                            ->label(fn (Get $get) => $get('type') === 'fixed' ? 'Amount off' : 'Percentage off')
                            ->numeric()
                            ->minValue(0)
                            ->prefix(fn (Get $get) => $get('type') === 'fixed' ? 'UGX' : null)
                            ->suffix(fn (Get $get) => $get('type') === 'percentage' ? '%' : null)
                            ->required(),
                    ]),

                Section::make('What it applies to')
                    ->description('Leave both blank for a discount on the whole bill, every term.')
                    ->columns(2)
                    ->schema([
                        Select::make('fee_structure_id')
                            ->label('Specific fee')
                            ->relationship(
                                'feeStructure',
                                'name',
                                fn (Builder $query) => $query->where('school_id', auth()->user()?->school_id),
                            )
                            ->searchable()
                            ->preload()
                            ->placeholder('All fees')
                            ->helperText('Restrict the discount to one fee, e.g. tuition only.'),

                        Select::make('term_id')
                            ->label('Specific term')
                            ->options(fn (): array => Term::query()
                                ->where('school_id', auth()->user()?->school_id)
                                ->with('academicYear')
                                ->get()
                                ->mapWithKeys(fn (Term $t) => [$t->id => $t->label()])
                                ->toArray())
                            ->placeholder('Every term')
                            ->helperText('Leave blank for an ongoing discount.')
                            ->native(false),

                        Textarea::make('notes')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
