<?php

namespace App\Filament\App\Resources\Guardians\Tables;

use App\Filament\Support\ConfirmWithPassword;
use App\Models\Guardian;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class GuardiansTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->emptyStateHeading('No parents or guardians yet')
            ->emptyStateDescription('They are added with each learner, or here. Their phone numbers receive receipts and fee reminders.')
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('relationship')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => Guardian::RELATIONSHIPS[$state] ?? $state)
                    ->color(fn (?string $state): string => match ($state) {
                        'father' => 'info',
                        'mother' => 'warning',
                        'guardian' => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('phone')
                    ->searchable(),
                TextColumn::make('email')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('students_count')
                    ->label('Children')
                    ->counts('students')
                    ->badge(),
                TextColumn::make('occupation')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('school.name')
                    ->label('School')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('relationship')
                    ->options(Guardian::RELATIONSHIPS),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    ConfirmWithPassword::on(DeleteBulkAction::make()),
                ]),
            ])
            ->paginationPageOptions([5, 10, 25, 50])
            ->defaultPaginationPageOption(5);
    }
}
