<?php

namespace App\Filament\App\Resources\Billing\Tables;

use App\Filament\Pages\StudentAccount;
use App\Models\Term;
use App\Support\OwnSchool;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\Summarizers\Summarizer;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

class BillingTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // One block per student -- their charges underneath, with a
            // subtotal -- instead of a flat list of fee lines.
            ->groups([
                Group::make('student_id')
                    ->label('Student')
                    ->getTitleFromRecordUsing(fn ($record) => collect([
                        $record->student?->name ?: 'No name',
                        $record->student?->admission_no,
                        $record->student?->schoolClass?->name,
                    ])->filter()->implode('  ·  '))
                    ->collapsible(),
                Group::make('description')
                    ->label('Charge')
                    ->collapsible(),
            ])
            ->defaultGroup('student_id')
            ->columns([
                TextColumn::make('charged_on')
                    ->label('Date')
                    ->date()
                    ->sortable(),

                TextColumn::make('student.name')
                    ->label('Student')
                    ->searchable(['name', 'admission_no'])
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('student.admission_no')
                    ->label('Adm. No.')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('student.schoolClass.name')
                    ->label('Class')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('student.residencyType.name')
                    ->label('Residency')
                    ->badge()
                    ->color(fn ($record) => $record->student?->residencyType?->badgeColor() ?? 'gray')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('description')
                    ->label('Charge')
                    ->searchable()
                    ->wrap(),

                TextColumn::make('term.name')
                    ->label('Term')
                    ->badge()
                    ->toggleable(),

                TextColumn::make('amount')
                    ->numeric()
                    ->alignEnd()
                    ->sortable()
                    ->summarize(Sum::make()->label('Charged')->numeric()),

                TextColumn::make('discount_amount')
                    ->label('Discount')
                    ->numeric()
                    ->alignEnd()
                    ->color('success')
                    ->summarize(Sum::make()->label('Discounts')->numeric())
                    ->placeholder('—')
                    ->description(fn ($record) => $record->discount_reason)
                    ->toggleable(),

                TextColumn::make('net')
                    ->label('Net (UGX)')
                    ->state(fn ($record) => $record->netAmount())
                    ->numeric()
                    ->alignEnd()
                    ->weight('bold')
                    ->summarize(
                        Summarizer::make()
                            ->label('Net')
                            ->numeric()
                            ->using(fn (QueryBuilder $query) => $query->sum(DB::raw('amount - discount_amount'))),
                    ),
            ])
            ->defaultSort('charged_on', 'desc')
            ->filters([
                SelectFilter::make('term_id')
                    ->label('Term')
                    ->options(fn (): array => Term::query()
                        ->where('school_id', auth()->user()?->school_id)
                        ->with('academicYear')
                        ->get()
                        ->mapWithKeys(fn (Term $t) => [$t->id => $t->label()])
                        ->toArray())
                    ->default(fn () => Term::current()?->getKey()),

                SelectFilter::make('school_class')
                    ->label('Class')
                    ->relationship('student.schoolClass', 'name', fn (Builder $query) => OwnSchool::scope($query))
                    ->preload(),

                SelectFilter::make('residency')
                    ->label('Residency')
                    ->relationship('student.residencyType', 'name', fn (Builder $query) => OwnSchool::scope($query))
                    ->preload(),
            ])
            ->recordActions([
                Action::make('account')
                    ->label('Account')
                    ->icon('heroicon-o-book-open')
                    ->color('gray')
                    ->iconButton()
                    ->tooltip('Open student account')
                    ->url(fn ($record): string => StudentAccount::getUrl(['student' => $record->student_id])),

                // Deleting a charge reduces what the student was charged.
                // That is how a mistaken billing run is corrected, so it is
                // available -- but it moves the balance, hence the warning.
                DeleteAction::make()
                    ->iconButton()
                    ->tooltip('Remove this charge')
                    ->requiresConfirmation()
                    ->modalDescription('This removes the charge from the student\'s account and reduces their balance.'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->requiresConfirmation()
                        ->modalDescription('This removes the charges and reduces those students\' balances.'),
                ]),
            ])
            ->emptyStateHeading('Nothing billed for this term yet')
            ->emptyStateDescription('Use "Bill a term" to charge every student the fees set up for the term.')
            ->emptyStateIcon('heroicon-o-document-text')
            ->paginationPageOptions([25, 50, 100])
            ->defaultPaginationPageOption(50);
    }
}
