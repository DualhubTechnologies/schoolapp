<?php

namespace App\Filament\Support;

use App\Models\Student;
use App\Services\FeeReminderService;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Text;
use Illuminate\Support\Collection;

/**
 * The fee-reminder actions, shared by every table that lists students who
 * owe: SMS the guardians, or print reminder letters.
 */
class FeeReminderActions
{
    public static function smsBulk(): BulkAction
    {
        return BulkAction::make('smsReminder')
            ->label('Send SMS reminder')
            ->icon('heroicon-o-chat-bubble-left-ellipsis')
            ->color('warning')
            ->modalHeading('Send fee reminders by SMS')
            ->modalDescription('Each selected student who owes money gets one SMS to their guardian. Students who owe nothing, or have no valid phone number, are skipped.')
            ->modalSubmitActionLabel('Send')
            ->schema(static::smsForm())
            ->deselectRecordsAfterCompletion()
            ->action(fn (Collection $records, array $data) => static::sendSms($records, $data));
    }

    public static function smsSingle(): Action
    {
        return Action::make('smsReminder')
            ->label('SMS reminder')
            ->icon('heroicon-o-chat-bubble-left-ellipsis')
            ->color('warning')
            ->modalHeading(fn (Student $record) => "Remind {$record->guardian?->name} by SMS")
            ->modalSubmitActionLabel('Send')
            ->schema(static::smsForm())
            ->action(fn (Student $record, array $data) => static::sendSms(collect([$record]), $data));
    }

    /**
     * For pages about one student that is not the action's record, such as
     * the Student Account page.
     *
     * @param  \Closure(): ?Student  $student
     */
    public static function smsFor(\Closure $student): Action
    {
        return Action::make('smsReminder')
            ->label('SMS reminder')
            ->icon('heroicon-o-chat-bubble-left-ellipsis')
            ->color('warning')
            ->modalHeading('Send a fee reminder by SMS')
            ->modalSubmitActionLabel('Send')
            ->schema(static::smsForm())
            ->action(fn (array $data) => ($s = $student()) ? static::sendSms(collect([$s]), $data) : null);
    }

    public static function lettersBulk(): BulkAction
    {
        return BulkAction::make('reminderLetters')
            ->label('Print reminder letters')
            ->icon('heroicon-o-printer')
            ->color('gray')
            ->modalHeading('Print fee reminder letters')
            ->modalDescription('One letter per student who owes money, ready to send home with the student.')
            ->modalSubmitActionLabel('Open letters')
            ->schema([
                DatePicker::make('deadline')
                    ->label('Ask parents to pay by')
                    ->native(false)
                    ->displayFormat('j M Y')
                    ->default(now()->addWeeks(2))
                    ->minDate(today()),
            ])
            ->deselectRecordsAfterCompletion()
            ->action(function (Collection $records, array $data, $livewire) {
                $url = route('filament.app.fees.letters', [
                    'students' => $records->pluck('id')->implode(','),
                    'deadline' => $data['deadline'] ?? null,
                    'print' => 1,
                ]);

                $livewire->js('window.open('.json_encode($url).', "_blank")');
            });
    }

    protected static function smsForm(): array
    {
        return [
            Textarea::make('template')
                ->label('Message')
                ->default(FeeReminderService::DEFAULT_TEMPLATE)
                ->rows(4)
                ->required()
                ->maxLength(459) // three SMS parts
                ->helperText('Placeholders: '.implode('  ', array_keys(FeeReminderService::PLACEHOLDERS)).'. Keep it under 160 characters for a single SMS.'),

            DatePicker::make('deadline')
                ->label('Pay-by date ({deadline})')
                ->native(false)
                ->displayFormat('j M Y')
                ->default(now()->addWeeks(2))
                ->minDate(today()),

            Toggle::make('skip_recent')
                ->label('Skip guardians reminded in the last 3 days')
                ->default(true),

            Text::make(fn () => config('sms.driver') === 'log'
                ? 'Test mode: SMS are recorded but not delivered. Set SMS_DRIVER=africastalking in .env to send for real.'
                : null),
        ];
    }

    protected static function sendSms(Collection $students, array $data): void
    {
        $result = app(FeeReminderService::class)->sendSms(
            $students,
            $data['template'] ?? null,
            $data['deadline'] ?? null,
            ($data['skip_recent'] ?? true) ? 3 : 0,
        );

        $skipped = collect([
            $result['not_owing'] ? "{$result['not_owing']} owe nothing" : null,
            $result['no_phone'] ? "{$result['no_phone']} have no valid phone" : null,
            $result['recently'] ? "{$result['recently']} were reminded recently" : null,
            $result['failed'] ? "{$result['failed']} failed to send" : null,
        ])->filter()->implode(', ');

        Notification::make()
            ->title($result['sent'].' '.str('reminder')->plural($result['sent']).' sent')
            ->body($skipped ? "Skipped: {$skipped}. See Fee Reminders for details." : null)
            ->{$result['failed'] ? 'warning' : 'success'}()
            ->send();
    }
}
