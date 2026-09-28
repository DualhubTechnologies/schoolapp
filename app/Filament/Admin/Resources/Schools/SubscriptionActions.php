<?php

namespace App\Filament\Admin\Resources\Schools;

use App\Filament\Pages\Auth\VerifyEmail;
use App\Models\Plan;
use App\Models\School;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Notifications\SchoolRejected;
use App\Services\Subscriptions\SubscriptionManager;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Throwable;

/**
 * The platform owner's subscription actions on a school, shared by the
 * schools table and the school's edit page.
 */
class SubscriptionActions
{
    /** @return list<Action> */
    public static function all(): array
    {
        return [static::approve(), static::reject(), static::renew(), static::activationCode(), static::changePlan(), static::extend(), static::suspend()];
    }

    /**
     * Let in a school that registered itself. Its trial starts today and
     * its administrator gets the welcome email (or gets it on confirming
     * their email, if they have not yet).
     */
    public static function approve(): Action
    {
        return Action::make('approve')
            ->label('Approve')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->visible(fn (School $record): bool => in_array($record->status, ['pending', 'rejected'], true))
            ->requiresConfirmation()
            ->modalHeading(fn (School $record) => "Approve {$record->name}?")
            ->modalDescription(fn (School $record) => "Registered by {$record->contact_person}, {$record->phone}, {$record->email}. "
                .'Approving opens SchoolHub to the school and starts its '.config('subscriptions.trial_days', 30).'-day free trial today.')
            ->action(function (School $record): void {
                SubscriptionManager::approve($record);

                $record->users()
                    ->whereNotNull('email_verified_at')
                    ->get()
                    ->filter(fn (User $user) => $user->hasRole('School Admin'))
                    ->each(fn (User $user) => VerifyEmail::welcome($user));

                Notification::make()->title("{$record->name} approved — its free trial has started")->success()->send();
            });
    }

    /** Turn down a school that registered itself, telling its administrator why. */
    public static function reject(): Action
    {
        return Action::make('reject')
            ->label('Reject')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->visible(fn (School $record): bool => $record->status === 'pending')
            ->modalHeading(fn (School $record) => "Reject {$record->name}?")
            ->modalDescription('The school stays locked out and its administrator is emailed the reason. You can still approve it later.')
            ->schema([
                Textarea::make('reason')
                    ->label('Reason (sent to the school)')
                    ->placeholder('e.g. We could not confirm this school exists.')
                    ->maxLength(255)
                    ->rows(3),
            ])
            ->action(function (School $record, array $data): void {
                SubscriptionManager::reject($record, $data['reason'] ?? null);

                try {
                    $record->users()->get()
                        ->filter(fn (User $user) => $user->hasRole('School Admin'))
                        ->each(fn (User $user) => $user->notify(new SchoolRejected($record)));
                } catch (Throwable $e) {
                    report($e);
                }

                Notification::make()->title("{$record->name} rejected")->success()->send();
            });
    }

    /** Record a payment and add the period it pays for. */
    public static function renew(): Action
    {
        $price = function (Get $get, Set $set): void {
            $plan = Plan::find($get('plan_id'));

            // Nothing to price until both are chosen; a custom period is priced by hand.
            if ($plan && in_array($get('cycle'), ['term', 'year'], true)) {
                $set('amount', $plan->priceFor($get('cycle')));
                $set('paid', $plan->priceFor($get('cycle')));
            }
        };

        return Action::make('renew')
            ->label('Record payment')
            ->icon('heroicon-o-banknotes')
            ->color('success')
            ->modalHeading(fn (School $record) => "Record payment — {$record->name}")
            ->modalDescription(function (School $record) {
                $st = SubscriptionManager::status($record);

                return 'Now: '.($st['plan']?->name ?? 'no plan').', '.$st['state']
                    .($st['ends_on'] ? ', ends '.$st['ends_on']->format('j M Y') : '')
                    .'. A renewal made early starts the day after the current period ends.';
            })
            ->fillForm(function (School $record) {
                $plan = SubscriptionManager::current($record)?->plan;
                $plan = $plan && ! $plan->is_trial ? $plan : Plan::where('is_active', true)->where('is_trial', false)->orderBy('sort_order')->first();

                return [
                    'plan_id' => $plan?->getKey(),
                    'cycle' => 'term',
                    'amount' => $plan?->price_per_term,
                    'paid' => $plan?->price_per_term,
                    'method' => 'mobile_money',
                    'paid_on' => today(),
                ];
            })
            ->schema([
                Grid::make(2)->schema([
                    Select::make('plan_id')
                        ->label('Plan')
                        ->options(fn () => Plan::options())
                        ->required()
                        ->selectablePlaceholder(false)
                        ->live()
                        ->afterStateUpdated($price),
                    Select::make('cycle')
                        ->label('Paying for')
                        ->options(['term' => 'One term ('.config('subscriptions.cycle_months.term').' months)', 'year' => 'One year', 'custom' => 'Custom period'])
                        ->required()
                        ->selectablePlaceholder(false)
                        ->live()
                        ->afterStateUpdated($price),
                    DatePicker::make('ends_on')
                        ->label('Ends on')
                        ->native(false)
                        ->displayFormat('d M Y')
                        ->visible(fn (Get $get) => $get('cycle') === 'custom')
                        ->required(fn (Get $get) => $get('cycle') === 'custom'),
                    TextInput::make('amount')
                        ->label('Price of the period (UGX)')
                        ->numeric()
                        ->minValue(0)
                        ->required(),
                    TextInput::make('paid')
                        ->label('Amount received (UGX)')
                        ->helperText('0 to add the period without a payment (e.g. on credit).')
                        ->numeric()
                        ->minValue(0)
                        ->required(),
                    Select::make('method')
                        ->options(SubscriptionPayment::METHODS)
                        ->required(),
                    TextInput::make('reference')
                        ->label('Transaction ID / reference')
                        ->maxLength(100),
                    DatePicker::make('paid_on')
                        ->label('Paid on')
                        ->native(false)
                        ->displayFormat('d M Y')
                        ->maxDate(today())
                        ->required(),
                    TextInput::make('notes')
                        ->maxLength(255)
                        ->columnSpanFull(),
                ]),
            ])
            ->action(function (School $record, array $data): void {
                $sub = SubscriptionManager::renew(
                    $record,
                    Plan::findOrFail($data['plan_id']),
                    $data['cycle'],
                    (float) $data['amount'],
                    [
                        'amount' => (float) $data['paid'],
                        'method' => $data['method'],
                        'reference' => $data['reference'] ?? null,
                        'paid_on' => $data['paid_on'],
                        'notes' => $data['notes'] ?? null,
                    ],
                    $data['ends_on'] ?? null,
                    $data['notes'] ?? null,
                );

                Notification::make()
                    ->title('Subscription renewed')
                    ->body("{$record->name}: {$sub->plan->name}, {$sub->periodLabel()}.")
                    ->success()
                    ->send();
            });
    }

    /**
     * Generate a one-time code for a school that has already paid (phone,
     * WhatsApp, in person). Give them the code; typing it into their own
     * Subscription page applies this exact renewal -- no visit needed.
     */
    public static function activationCode(): Action
    {
        $price = function (Get $get, Set $set): void {
            $plan = Plan::find($get('plan_id'));

            if ($plan && in_array($get('cycle'), ['term', 'year'], true)) {
                $set('amount', $plan->priceFor($get('cycle')));
                $set('paid', $plan->priceFor($get('cycle')));
            }
        };

        return Action::make('activationCode')
            ->label('Generate activation code')
            ->icon('heroicon-o-key')
            ->color('gray')
            ->modalHeading(fn (School $record) => "Generate activation code — {$record->name}")
            ->modalDescription('For a school that has already paid you directly. Give them the code; entering it on their Subscription page applies this renewal immediately.')
            ->fillForm(function (School $record) {
                $plan = SubscriptionManager::current($record)?->plan;
                $plan = $plan && ! $plan->is_trial ? $plan : Plan::where('is_active', true)->where('is_trial', false)->orderBy('sort_order')->first();

                return [
                    'plan_id' => $plan?->getKey(),
                    'cycle' => 'term',
                    'amount' => $plan?->price_per_term,
                    'paid' => $plan?->price_per_term,
                    'method' => 'mobile_money',
                    'valid_days' => 14,
                ];
            })
            ->schema([
                Grid::make(2)->schema([
                    Select::make('plan_id')
                        ->label('Plan')
                        ->options(fn () => Plan::options())
                        ->required()
                        ->selectablePlaceholder(false)
                        ->live()
                        ->afterStateUpdated($price),
                    Select::make('cycle')
                        ->label('Paying for')
                        ->options(['term' => 'One term ('.config('subscriptions.cycle_months.term').' months)', 'year' => 'One year', 'custom' => 'Custom period'])
                        ->required()
                        ->selectablePlaceholder(false)
                        ->live()
                        ->afterStateUpdated($price),
                    DatePicker::make('ends_on')
                        ->label('Ends on')
                        ->native(false)
                        ->displayFormat('d M Y')
                        ->visible(fn (Get $get) => $get('cycle') === 'custom')
                        ->required(fn (Get $get) => $get('cycle') === 'custom'),
                    TextInput::make('amount')
                        ->label('Price of the period (UGX)')
                        ->numeric()
                        ->minValue(0)
                        ->required(),
                    TextInput::make('paid')
                        ->label('Amount they paid (UGX)')
                        ->helperText('0 if you just want to issue the period without recording a payment.')
                        ->numeric()
                        ->minValue(0)
                        ->required(),
                    Select::make('method')
                        ->options(SubscriptionPayment::METHODS)
                        ->required(),
                    TextInput::make('reference')
                        ->label('Transaction ID / reference')
                        ->maxLength(100),
                    TextInput::make('valid_days')
                        ->label('Code valid for (days)')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(90)
                        ->required()
                        ->helperText('The code stops working if not used within this many days.'),
                    TextInput::make('notes')
                        ->maxLength(255)
                        ->columnSpanFull(),
                ]),
            ])
            ->action(function (School $record, array $data): void {
                $code = SubscriptionManager::issueActivationCode(
                    $record,
                    Plan::findOrFail($data['plan_id']),
                    $data['cycle'],
                    (float) $data['amount'],
                    [
                        'amount' => (float) $data['paid'],
                        'method' => $data['method'],
                        'reference' => $data['reference'] ?? null,
                        'notes' => $data['notes'] ?? null,
                    ],
                    $data['ends_on'] ?? null,
                    $data['notes'] ?? null,
                    (int) $data['valid_days'],
                );

                Notification::make()
                    ->title('Activation code generated')
                    ->body("Give {$record->name} this code — it works on their Subscription page and expires {$code->expires_at->format('j M Y')}:\n\n{$code->code}")
                    ->success()
                    ->persistent()
                    ->send();
            });
    }

    /** Upgrade or downgrade today; limits change immediately. */
    public static function changePlan(): Action
    {
        return Action::make('changePlan')
            ->label('Change plan')
            ->icon('heroicon-o-arrows-up-down')
            ->color('gray')
            ->fillForm(fn (School $record) => ['plan_id' => SubscriptionManager::current($record)?->plan_id])
            ->schema([
                Select::make('plan_id')
                    ->label('Plan')
                    ->options(fn () => Plan::options(includeTrial: true))
                    ->helperText('Takes effect today; the dates stay the same. Record a payment for any difference in price.')
                    ->required(),
            ])
            ->visible(fn (School $record) => SubscriptionManager::current($record) !== null)
            ->action(function (School $record, array $data): void {
                SubscriptionManager::changePlan($record, Plan::findOrFail($data['plan_id']));
                Notification::make()->title('Plan changed')->success()->send();
            });
    }

    /** Give extra days (a longer trial, a goodwill extension). */
    public static function extend(): Action
    {
        return Action::make('extend')
            ->label('Extend')
            ->icon('heroicon-o-calendar-days')
            ->color('gray')
            ->schema([
                TextInput::make('days')
                    ->label('Add days')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(365)
                    ->default(14)
                    ->required(),
            ])
            ->action(function (School $record, array $data): void {
                $record->subscriptions()->exists()
                    ? SubscriptionManager::extend($record, (int) $data['days'])
                    : SubscriptionManager::startTrial($record);

                $st = SubscriptionManager::status($record);
                Notification::make()->title('Extended')->body('Now ends '.$st['ends_on']?->format('j M Y').'.')->success()->send();
            });
    }

    public static function suspend(): Action
    {
        return Action::make('suspend')
            ->label(fn (School $record) => $record->status === 'suspended' ? 'Reactivate' : 'Suspend')
            ->icon(fn (School $record) => $record->status === 'suspended' ? 'heroicon-o-play' : 'heroicon-o-pause')
            ->color(fn (School $record) => $record->status === 'suspended' ? 'success' : 'danger')
            ->hidden(fn (School $record): bool => in_array($record->status, ['pending', 'rejected'], true))
            ->requiresConfirmation()
            ->modalDescription(fn (School $record) => $record->status === 'suspended'
                ? 'The school can use SchoolHub again straight away.'
                : 'Everyone at the school is locked out (their records are kept) until you reactivate it.')
            ->action(function (School $record): void {
                $record->update(['status' => $record->status === 'suspended' ? 'active' : 'suspended']);
                Notification::make()->title($record->status === 'suspended' ? 'School suspended' : 'School reactivated')->success()->send();
            });
    }

    /** Badge colour for a subscription state. */
    public static function stateColor(string $state): string
    {
        return match ($state) {
            'active' => 'success',
            'trial' => 'info',
            'grace', 'pending' => 'warning',
            default => 'danger',
        };
    }

    public static function stateLabel(string $state): string
    {
        return [
            'trial' => 'Trial',
            'active' => 'Active',
            'grace' => 'Overdue',
            'expired' => 'Expired',
            'none' => 'No plan',
            'suspended' => 'Suspended',
            'pending' => 'Awaiting approval',
            'rejected' => 'Rejected',
        ][$state] ?? $state;
    }
}
