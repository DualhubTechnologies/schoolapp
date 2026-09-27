<?php

namespace App\Filament\App\Resources\StudentDiscounts\Tables;

use App\Models\StudentDiscount;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StudentDiscountsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->emptyStateHeading('No discounts or bursaries')
            ->emptyStateDescription('Give a learner a bursary, staff-child or sibling discount; it is taken off their fees when they are billed.')
            ->columns([
                TextColumn::make('student.name')
                    ->label('Student')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('student.admission_no')
                    ->label('Adm. No.')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('award_level')
                    ->label('Award')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'full' => 'Full bursary',
                        'half' => 'Half bursary',
                        'partial' => 'Partial',
                        default => '—',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'full' => 'success',
                        'half' => 'info',
                        default => 'gray',
                    }),

                TextColumn::make('reason')
                    ->label('Reason')
                    ->formatStateUsing(fn (?string $state) => StudentDiscount::REASONS[$state] ?? $state)
                    ->toggleable(),

                TextColumn::make('value')
                    ->label('Discount')
                    ->state(fn (StudentDiscount $record): string => $record->award_level === 'full'
                        ? '100%'
                        : ($record->type === 'percentage'
                            ? rtrim(rtrim(number_format((float) $record->value, 2), '0'), '.').'%'
                            : 'UGX '.number_format((float) $record->value, 0)))
                    ->weight('bold'),

                TextColumn::make('feeStructure.name')
                    ->label('Covers')
                    ->state(fn (StudentDiscount $record): string => $record->award_level === 'full'
                        ? 'Every fee'
                        : ($record->feeStructure?->name ?? 'Whole bill')),

                TextColumn::make('scope')
                    ->label('Runs for')
                    ->badge()
                    ->state(fn (StudentDiscount $record): string => $record->scopeLabel())
                    // A lapsed year award is the thing worth spotting: it has
                    // stopped applying, and nobody was told.
                    ->color(fn (StudentDiscount $record): string => $record->hasLapsed() ? 'danger' : 'gray')
                    ->description(fn (StudentDiscount $record): ?string => $record->hasLapsed()
                        ? 'Lapsed — renew if it should continue'
                        : null),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('award_level')
                    ->label('Award level')
                    ->options(StudentDiscount::AWARD_LEVELS),

                SelectFilter::make('reason')
                    ->options(StudentDiscount::REASONS),

                SelectFilter::make('scope')
                    ->label('Runs for')
                    ->options(StudentDiscount::SCOPES),

                SelectFilter::make('is_active')
                    ->label('Status')
                    ->options([
                        1 => 'Active',
                        0 => 'Inactive',
                    ]),

                // Year awards belonging to a year that is no longer current.
                // These have quietly stopped applying and need reviewing.
                Filter::make('lapsed')
                    ->label('Needs review (lapsed)')
                    ->query(fn (Builder $query): Builder => $query
                        ->where('scope', 'year')
                        ->whereNotNull('academic_year_id')
                        ->whereHas('academicYear', fn (Builder $q) => $q->where('is_current', false))),
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
