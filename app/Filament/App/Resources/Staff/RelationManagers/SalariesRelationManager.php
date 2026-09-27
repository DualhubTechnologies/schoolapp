<?php

namespace App\Filament\App\Resources\Staff\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SalariesRelationManager extends RelationManager
{
    protected static string $relationship = 'salaries';

    protected static ?string $title = 'Salary';

    protected static ?string $modelLabel = 'salary';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('base_salary')
                    ->label('Monthly base salary')
                    ->required()
                    ->numeric()
                    ->prefix('UGX'),
                DatePicker::make('effective_from')
                    ->required()
                    ->default(now()),
                DatePicker::make('effective_to')
                    ->label('Effective until')
                    ->helperText('Leave blank for current salary.'),
                TextInput::make('notes')
                    ->placeholder('e.g. Annual increment, Promotion to HOD'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('base_salary')
            ->defaultSort('effective_from', 'desc')
            ->columns([
                TextColumn::make('base_salary')
                    ->money('UGX')
                    ->sortable(),
                TextColumn::make('effective_from')
                    ->label('From')
                    ->date()
                    ->sortable(),
                TextColumn::make('effective_to')
                    ->label('Until')
                    ->date()
                    ->placeholder('Current')
                    ->sortable(),
                TextColumn::make('notes')
                    ->placeholder('—'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['school_id'] = $this->getOwnerRecord()->school_id;

                        return $data;
                    }),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
