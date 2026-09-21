<?php

namespace App\Filament\App\Resources\FeeReminders;

use App\Filament\App\Resources\FeeReminders\Pages\ListFeeReminders;
use App\Filament\Pages\StudentAccount;
use App\Models\FeeReminder;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * History of fee reminders sent to guardians, and the place to send new
 * ones to everyone who owes.
 */
class FeeReminderResource extends Resource
{
    protected static ?string $model = FeeReminder::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBellAlert;

    protected static string|\UnitEnum|null $navigationGroup = 'Fees';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Fee Reminders';

    protected static ?string $modelLabel = 'fee reminder';

    protected static ?string $pluralModelLabel = 'Fee Reminders';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['student.schoolClass'])
            ->where('school_id', auth()->user()?->school_id);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Sent')
                    ->dateTime('j M Y, H:i')
                    ->sortable(),
                TextColumn::make('student.name')
                    ->label('Student')
                    ->description(fn (FeeReminder $record) => $record->student?->admission_no)
                    ->searchable(['name', 'admission_no']),
                TextColumn::make('student.schoolClass.name')
                    ->label('Class')
                    ->badge(),
                TextColumn::make('channel')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => FeeReminder::CHANNELS[$state] ?? $state)
                    ->color(fn (string $state) => $state === 'sms' ? 'info' : 'gray'),
                TextColumn::make('phone')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('balance')
                    ->label('Owed then (UGX)')
                    ->numeric()
                    ->alignEnd(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => FeeReminder::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'sent' => 'success',
                        'failed' => 'danger',
                        default => 'gray',
                    })
                    ->description(fn (FeeReminder $record) => $record->error),
                TextColumn::make('message')
                    ->wrap()
                    ->limit(90)
                    ->tooltip(fn (FeeReminder $record) => $record->message)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('sent_by')
                    ->label('By')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('channel')->options(FeeReminder::CHANNELS),
                SelectFilter::make('status')->options(FeeReminder::STATUSES),
                SelectFilter::make('class')
                    ->label('Class')
                    ->relationship('student.schoolClass', 'name')
                    ->preload(),
            ])
            ->recordActions([
                Action::make('account')
                    ->label('Account')
                    ->icon('heroicon-o-book-open')
                    ->color('gray')
                    ->url(fn (FeeReminder $record) => StudentAccount::getUrl(['student' => $record->student_id])),
            ])
            ->emptyStateHeading('No reminders sent yet')
            ->emptyStateDescription('Use "Send reminders" to text or write to the guardians of students who owe.')
            ->emptyStateIcon('heroicon-o-bell-alert')
            ->paginationPageOptions([25, 50, 100])
            ->defaultPaginationPageOption(25);
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasRole(['School Admin', 'Accountant', 'Bursar']) ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFeeReminders::route('/'),
        ];
    }
}
