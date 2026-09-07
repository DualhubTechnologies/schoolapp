<?php

namespace App\Filament\Resources\Staff\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AllowancesRelationManager extends RelationManager
{
    protected static string $relationship = 'allowances';

    protected static ?string $title = 'Allowances';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('allowance_type_id')
                    ->label('Allowance type')
                    ->relationship(
                        'allowanceType',
                        'name',
                        modifyQueryUsing: fn ($query) => $query->where('school_id', $this->getOwnerRecord()->school_id)->where('is_active', true)
                    )
                    ->required()
                    ->preload(),
                TextInput::make('amount')
                    ->label('Monthly amount')
                    ->required()
                    ->numeric()
                    ->prefix('UGX'),
                Toggle::make('is_active')
                    ->default(true),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('amount')
            ->columns([
                TextColumn::make('allowanceType.name')
                    ->label('Allowance')
                    ->sortable(),
                TextColumn::make('amount')
                    ->money('UGX')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Add allowance'),
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
}