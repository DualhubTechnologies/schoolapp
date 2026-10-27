<?php

namespace App\Filament\Support;

use App\Filament\App\Resources\Students\Schemas\StudentForm;
use App\Filament\App\Resources\Students\StudentResource;
use App\Models\Student;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;

/**
 * Makes a learner's "Not linked" parent clickable: pick the family (a
 * parent or a brother or sister already at the school) or type a new
 * parent in, and they are linked there and then.
 */
class LinkGuardian
{
    public static function column(TextColumn $column): TextColumn
    {
        return $column
            // Only those who may edit learners can add one; others just see that none is linked.
            ->default(fn (Student $record): string => StudentResource::canEdit($record) ? 'Add parent' : 'No parent linked')
            ->icon(fn (Student $record): ?string => $record->guardian_id || ! StudentResource::canEdit($record) ? null : 'heroicon-o-plus-circle')
            ->color(fn (Student $record): ?string => $record->guardian_id ? null : (StudentResource::canEdit($record) ? 'primary' : 'gray'))
            ->tooltip(fn (Student $record): ?string => $record->guardian_id || ! StudentResource::canEdit($record) ? null : 'No parent linked yet. Click to add one.')
            ->disabledClick(fn (Student $record): bool => $record->guardian_id !== null || ! StudentResource::canEdit($record))
            ->action(static::action());
    }

    public static function action(): Action
    {
        return Action::make('linkGuardian')
            ->modalHeading(fn (Student $record): string => 'Add the parent of '.$record->name)
            ->modalDescription('Their phone receives receipts, fee reminders and SMS from the school.')
            ->modalWidth('lg')
            ->modalSubmitActionLabel('Link parent')
            ->schema(StudentForm::parentFields())
            ->action(function (Student $record, array $data): void {
                $data = StudentForm::resolveQuickGuardian($data, (int) $record->school_id);
                $record->update(['guardian_id' => $data['guardian_id']]);

                Notification::make()
                    ->title('Parent linked')
                    ->body($record->fresh()?->guardian?->name.' is now the parent of '.$record->name.'.')
                    ->success()
                    ->send();
            });
    }
}
