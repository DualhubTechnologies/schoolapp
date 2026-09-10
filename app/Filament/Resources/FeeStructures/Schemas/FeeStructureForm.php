<?php
 
namespace App\Filament\Resources\FeeStructures\Schemas;
 
use App\Models\FeeStructure;
use App\Models\SchoolClass;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
 
class FeeStructureForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
 
                // ── Section 1: What & where ──
                Section::make('Fee details')
                    ->description('Name the fee and choose which classes it applies to.')
                    ->icon('heroicon-o-banknotes')
                    ->columns(2)
                    ->schema([
                        Select::make('school_id')
                            ->relationship('school', 'name')
                            ->default(fn () => auth()->user()->school_id)
                            ->disabled(fn () => ! auth()->user()->hasRole('Super Admin'))
                            ->dehydrated()
                            ->live()
                            ->required(),
 
                        Select::make('class_ids')
                            ->label('Classes')
                            ->helperText('One record is created per class, at the amount below. Adjust any afterward.')
                            ->multiple()
                            ->options(fn (Get $get) => static::classOptions($get('school_id')))
                            ->searchable()
                            ->preload()
                            ->required()
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
                            ->visibleOn('edit'),
 
                        TextInput::make('name')
                            ->label('Fee name')
                            ->placeholder('e.g. Tuition, Admission, Ski trip')
                            ->required()
                            ->maxLength(100)
                            ->columnSpanFull(),
                    ]),
 
                // ── Section 2: How it's charged ──
                Section::make('Charging rules')
                    ->description('How often the fee is charged and who it applies to.')
                    ->icon('heroicon-o-arrow-path')
                    ->columns(2)
                    ->schema([
                        ToggleButtons::make('frequency')
                            ->label('Frequency')
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
 
                        ToggleButtons::make('applies_to')
                            ->label('Applies to')
                            ->options(FeeStructure::APPLIES_TO)
                            ->icons([
                                'all' => 'heroicon-o-users',
                                'new_only' => 'heroicon-o-user-plus',
                            ])
                            ->colors([
                                'all' => 'gray',
                                'new_only' => 'warning',
                            ])
                            ->default('all')
                            ->inline()
                            ->required()
                            ->columnSpanFull(),
 
                        // Term & year only apply to recurring per-term fees.
                        Select::make('term')
                            ->options([
                                'Term 1' => 'Term 1',
                                'Term 2' => 'Term 2',
                                'Term 3' => 'Term 3',
                            ])
                            ->default('Term 1')
                            ->required(fn (Get $get) => $get('frequency') === 'per_term')
                            ->visible(fn (Get $get) => $get('frequency') === 'per_term'),
 
                        TextInput::make('academic_year')
                            ->label('Academic year')
                            ->default(fn () => (string) now()->year)
                            ->maxLength(9)
                            ->required(fn (Get $get) => $get('frequency') === 'per_term')
                            ->visible(fn (Get $get) => $get('frequency') === 'per_term'),
                    ]),
 
                // ── Section 3: Amount ──
                Section::make('Amount')
                    ->icon('heroicon-o-currency-dollar')
                    ->columns(2)
                    ->schema([
                        TextInput::make('amount')
                            ->label('Fee amount')
                            ->numeric()
                            ->prefix('UGX')
                            ->required()
                            ->minValue(0),
 
                        \Filament\Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->helperText('Inactive fees are ignored when generating invoices.')
                            ->default(true),
 
                        Textarea::make('description')
                            ->placeholder('Optional notes — what the fee covers')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),
            ]);
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
 
    protected static function scopeClasses(Builder $query, $schoolId): Builder
    {
        $schoolId = $schoolId ?: auth()->user()?->school_id;
 
        if ($schoolId) {
            $query->where('school_id', $schoolId);
        }
 
        return $query->orderBy('level')->orderBy('name');
    }
}