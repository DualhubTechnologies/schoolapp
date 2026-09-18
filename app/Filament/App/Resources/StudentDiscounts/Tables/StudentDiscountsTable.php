<?php

namespace App\Filament\App\Resources\StudentDiscounts\Tables;

use App\Models\StudentDiscount;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class StudentDiscountsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('student.name')
                    ->label('Student')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('student.admission_no')
                    ->label('Adm. No.')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('reason')
                    ->label('Reason')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => StudentDiscount::REASONS[$state] ?? $state),

                TextColumn::make('value')
                    ->label('Discount')
                    ->state(fn (StudentDiscount $record): string => $record->type === 'percentage'
                        ? rtrim(rtrim(number_format((float) $record->value, 2), '0'), '.') . '%'
                        : 'UGX ' . number_format((float) $record->value, 0))
                    ->weight('bold'),

                TextColumn::make('feeStructure.name')
                    ->label('Applies to')
                    ->placeholder('All fees'),

                TextColumn::make('term.name')
                    ->label('Term')
                    ->placeholder('Every term')
                    ->badge(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('reason')
                    ->options(StudentDiscount::REASONS),

                SelectFilter::make('is_active')
                    ->label('Status')
                    ->options([
                        1 => 'Active',
                        0 => 'Inactive',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->paginationPageOptions([10, 25, 50])
            ->defaultPaginationPageOption(25);
    }
}
