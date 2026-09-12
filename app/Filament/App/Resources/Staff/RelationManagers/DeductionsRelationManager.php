<?php

namespace App\Filament\App\Resources\Staff\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DeductionsRelationManager extends RelationManager
{
    protected static string $relationship = 'deductions';

    protected static ?string $title = 'Deductions';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('deduction_type_id')
                    ->label('Deduction type')
                    ->relationship(
                        'deductionType',
                        'name',
                        modifyQueryUsing: fn ($query) => $query->where('school_id', $this->getOwnerRecord()->school_id)->where('is_active', true)->where('is_statutory', false)
                    )
                    ->required()
                    ->preload()
                    ->live()
                    ->helperText('Statutory deductions (NSSF, PAYE) are calculated automatically — only custom deductions are assigned here.'),
                TextInput::make('amount')
                    ->label('Monthly deduction amount')
                    ->numeric()
                    ->prefix('UGX')
                    ->visible(fn (Get $get) => self::isFixedType($get)),
                TextInput::make('rate')
                    ->label('Deduction rate')
                    ->numeric()
                    ->suffix('%')
                    ->visible(fn (Get $get) => self::isPercentageType($get)),
                Toggle::make('is_recurring')
                    ->default(true)
                    ->live(),
                DatePicker::make('start_date')
                    ->required()
                    ->default(now()),
                DatePicker::make('end_date')
                    ->helperText('Leave blank for ongoing deductions.'),
                Placeholder::make('loan_heading')
                    ->label('Loan tracking')
                    ->content('Enter the total loan balance and any amount already recovered.')
                    ->visible(fn (Get $get) => self::isLoanType($get)),
                TextInput::make('total_amount')
                    ->label('Total loan amount')
                    ->numeric()
                    ->prefix('UGX')
                    ->helperText('System auto-tracks recovery and stops deducting when fully repaid.')
                    ->visible(fn (Get $get) => self::isLoanType($get)),
                TextInput::make('amount_recovered')
                    ->label('Already recovered')
                    ->numeric()
                    ->prefix('UGX')
                    ->default(0)
                    ->helperText('Amount already deducted from previous months (if transferring from another system).')
                    ->visible(fn (Get $get) => self::isLoanType($get)),
                Toggle::make('is_active')
                    ->default(true),
                TextInput::make('notes')
                    ->placeholder('e.g. Staff car loan approved by board Jan 2026'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('amount')
            ->columns([
                TextColumn::make('deductionType.name')
                    ->label('Deduction')
                    ->sortable(),
                TextColumn::make('amount')
                    ->money('UGX')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('rate')
                    ->suffix('%')
                    ->placeholder('—')
                    ->sortable(),
                IconColumn::make('is_recurring')
                    ->label('Recurring')
                    ->boolean(),
                TextColumn::make('start_date')
                    ->label('From')
                    ->date()
                    ->sortable(),
                TextColumn::make('end_date')
                    ->label('Until')
                    ->date()
                    ->placeholder('Ongoing')
                    ->sortable(),
                TextColumn::make('total_amount')
                    ->label('Loan balance')
                    ->money('UGX')
                    ->placeholder('—'),
                TextColumn::make('amount_recovered')
                    ->label('Recovered')
                    ->money('UGX')
                    ->placeholder('—'),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Add deduction'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    private static function isFixedType(Get $get): bool
    {
        $typeId = $get('deduction_type_id');
        if (! $typeId) return true;
        $type = \App\Models\DeductionType::find($typeId);
        return $type && $type->calculation_method === 'fixed';
    }

    private static function isPercentageType(Get $get): bool
    {
        $typeId = $get('deduction_type_id');
        if (! $typeId) return false;
        $type = \App\Models\DeductionType::find($typeId);
        return $type && $type->calculation_method === 'percentage';
    }

    private static function isLoanType(Get $get): bool
    {
        $typeId = $get('deduction_type_id');
        if (! $typeId) return false;
        $type = \App\Models\DeductionType::find($typeId);
        return $type && in_array($type->name, ['Staff Loan', 'Salary Advance', 'Damage/Loss Recovery']);
    }
}