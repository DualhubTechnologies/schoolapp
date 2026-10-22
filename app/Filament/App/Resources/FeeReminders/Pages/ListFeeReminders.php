<?php

namespace App\Filament\App\Resources\FeeReminders\Pages;

use App\Filament\App\Resources\FeeBalances\FeeBalanceResource;
use App\Filament\App\Resources\FeeReminders\FeeReminderResource;
use App\Filament\Support\MoneyInput;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\FeeReminderService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;

class ListFeeReminders extends ListRecords
{
    protected static string $resource = FeeReminderResource::class;

    public function getSubheading(): ?string
    {
        return 'Remind guardians of outstanding fees by SMS or printed letter. Every reminder is recorded here.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('send')
                ->label('Send reminders')
                ->icon('heroicon-o-paper-airplane')
                ->modalHeading('Send fee reminders')
                ->modalDescription('Choose who to remind. Only students who owe at least the amount below are included.')
                ->modalWidth('2xl')
                ->modalSubmitActionLabel('Send')
                ->schema([
                    Select::make('class_ids')
                        ->label('Classes')
                        ->placeholder('All classes')
                        ->multiple()
                        ->options(fn () => SchoolClass::where('school_id', auth()->user()?->school_id)->orderBy('name')->pluck('name', 'id'))
                        ->live(),

                    MoneyInput::make('min_balance')
                        ->label('Owing at least')
                        ->prefix('UGX')
                        ->numeric()
                        ->default(1)
                        ->minValue(1)
                        ->live(onBlur: true),

                    TextInput::make('paid_below')
                        ->label('Paid less than')
                        ->helperText('Optional. E.g. 50 for learners who have paid under half of their fees.')
                        ->numeric()
                        ->suffix('% of their fees')
                        ->minValue(1)
                        ->maxValue(100)
                        ->placeholder('Any')
                        ->live(onBlur: true),

                    ToggleButtons::make('channel')
                        ->label('Send as')
                        ->options(['sms' => 'SMS to guardian', 'letter' => 'Printed letters'])
                        ->icons(['sms' => 'heroicon-o-chat-bubble-left-ellipsis', 'letter' => 'heroicon-o-printer'])
                        ->default('sms')
                        ->inline()
                        ->live()
                        ->required(),

                    Text::make(fn (Get $get) => $this->audienceSummary($get('class_ids'), $get('min_balance'), $get('paid_below'))),

                    Textarea::make('template')
                        ->label('Message')
                        ->default(fn (): string => FeeReminderService::defaultTemplate())
                        ->rows(4)
                        ->maxLength(459)
                        ->required(fn (Get $get) => $get('channel') === 'sms')
                        ->visible(fn (Get $get) => $get('channel') === 'sms')
                        ->helperText('Placeholders: '.implode('  ', array_keys(FeeReminderService::PLACEHOLDERS))),

                    DatePicker::make('deadline')
                        ->label('Ask parents to pay by')
                        ->native(false)
                        ->displayFormat('j M Y')
                        ->default(now()->addWeeks(2))
                        ->minDate(today()),

                    Toggle::make('skip_recent')
                        ->label('Skip guardians already texted in the last 3 days')
                        ->default(true)
                        ->visible(fn (Get $get) => $get('channel') === 'sms'),
                ])
                ->action(function (array $data) {
                    $students = $this->debtors($data['class_ids'] ?? [], (float) ($data['min_balance'] ?? 1), $this->percent($data['paid_below'] ?? null));

                    if ($students->isEmpty()) {
                        Notification::make()->title('No one to remind')->body('No student in those classes matches what you chose.')->info()->send();

                        return;
                    }

                    if ($data['channel'] === 'letter') {
                        $url = route('filament.app.fees.letters', [
                            'students' => $students->pluck('id')->implode(','),
                            'deadline' => $data['deadline'] ?? null,
                            'print' => 1,
                        ]);
                        $this->js('window.open('.json_encode($url).', "_blank")');

                        return;
                    }

                    $result = app(FeeReminderService::class)->sendSms(
                        $students,
                        $data['template'] ?? null,
                        $data['deadline'] ?? null,
                        ($data['skip_recent'] ?? true) ? 3 : 0,
                    );

                    $skipped = collect([
                        $result['no_phone'] ? "{$result['no_phone']} have no valid phone" : null,
                        $result['recently'] ? "{$result['recently']} were texted recently" : null,
                        $result['failed'] ? "{$result['failed']} failed" : null,
                    ])->filter()->implode(', ');

                    Notification::make()
                        ->title("{$result['sent']} ".str('reminder')->plural($result['sent']).' sent')
                        ->body($skipped ? "Skipped: {$skipped}." : null)
                        ->{$result['failed'] ? 'warning' : 'success'}()
                        ->send();
                }),

            Action::make('balances')
                ->label('Student accounts')
                ->icon('heroicon-o-wallet')
                ->color('gray')
                ->url(FeeBalanceResource::getUrl()),
        ];
    }

    /**
     * Active students in the chosen classes who owe at least $minBalance,
     * and, if a percentage is given, have paid less than that share of
     * what they were charged.
     *
     * @return Collection<int, Student>
     */
    protected function debtors(?array $classIds, float $minBalance, ?float $paidBelowPercent = null): Collection
    {
        $charged = FeeBalanceResource::chargedSql();
        $paid = FeeBalanceResource::paidSql();

        return Student::query()
            ->where('school_id', auth()->user()?->school_id)
            ->where('status', 'active')
            ->when($classIds, fn (Builder $q) => $q->whereIn('school_class_id', $classIds))
            ->select('students.*')
            ->selectRaw('('.FeeBalanceResource::chargedSql().' - '.FeeBalanceResource::paidSql().') as balance_owing')
            // "? + 0": SQLite (the Windows app) compares a bound number as
            // text, and every number is "less" than text there.
            ->whereRaw('('.FeeBalanceResource::chargedSql().' - '.FeeBalanceResource::paidSql().') >= ? + 0', [max($minBalance, 1)])
            ->when($paidBelowPercent !== null, fn (Builder $q) => $q
                ->whereRaw("{$charged} > 0")
                ->whereRaw("{$paid} < {$charged} * (? + 0)", [$paidBelowPercent / 100]))
            ->with(['guardian', 'schoolClass', 'school'])
            ->get();
    }

    /** The "paid less than" box: a percentage from 1 to 100, or none. */
    protected function percent(mixed $value): ?float
    {
        return is_numeric($value) && (float) $value > 0 ? min((float) $value, 100.0) : null;
    }

    protected function audienceSummary(?array $classIds, $minBalance, mixed $paidBelow = null): HtmlString
    {
        $students = $this->debtors($classIds, (float) ($minBalance ?: 1), $this->percent($paidBelow));
        $service = app(FeeReminderService::class);
        $withPhone = $students->filter(fn (Student $s) => $service->phoneFor($s) !== null)->count();
        $owed = (float) $students->sum('balance_owing');

        return new HtmlString(
            '<div style="padding:.65rem .85rem;border-radius:8px;background:#eff6ff;color:#1e3a5f;font-size:.875rem">'
            .'<strong>'.number_format($students->count()).'</strong> '.str('student')->plural($students->count()).' owe a total of '
            .'<strong>UGX '.number_format($owed).'</strong>. '
            .number_format($withPhone).' have a guardian phone number for SMS.'
            .'</div>'
        );
    }
}
