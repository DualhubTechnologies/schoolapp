<?php

namespace App\Filament\Admin\Resources\ActivationCodes;

use App\Filament\Admin\Resources\ActivationCodes\Pages\ManageActivationCodes;
use App\Filament\Support\MoneyInput;
use App\Models\Plan;
use App\Models\SubscriptionActivationCode;
use App\Models\SubscriptionPayment;
use App\Services\Subscriptions\SubscriptionManager;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Every activation code the platform owner has ever made, whether it is
 * already tied to a school or still spare stock waiting to be handed out.
 * The "Generate codes" action is how you top up that stock -- keep a
 * screenshot or note of the unused ones on your phone, and hand one out
 * the moment a school pays, without needing to be at a computer.
 */
class ActivationCodeResource extends Resource
{
    protected static ?string $model = SubscriptionActivationCode::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static string|\UnitEnum|null $navigationGroup = 'Platform Management';

    protected static ?string $navigationLabel = 'Activation Codes';

    protected static ?int $navigationSort = 3;

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasRole('Super Admin') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('code')->fontFamily('mono')->copyable()->weight('bold')
                    ->description(fn (SubscriptionActivationCode $record): ?string => $record->is_gift ? 'Gift voucher' : null),
                TextColumn::make('plan.name')->label('Plan'),
                TextColumn::make('amount')->label('Price')->numeric()->prefix('UGX '),
                TextColumn::make('school.name')->label('School')->placeholder('- not yet assigned -')->searchable(),
                TextColumn::make('status')
                    ->state(fn (SubscriptionActivationCode $record) => ucfirst($record->status()))
                    ->badge()
                    ->color(fn (SubscriptionActivationCode $record) => match ($record->status()) {
                        'active' => 'success',
                        'used' => 'gray',
                        'expired', 'revoked' => 'danger',
                    }),
                TextColumn::make('expires_at')->date('j M Y'),
                TextColumn::make('used_at')->label('Used')->dateTime('j M Y, g:ia')->placeholder('Not yet')->toggleable(),
                TextColumn::make('used_by')->label('Used by')->toggleable(),
                TextColumn::make('created_by')->label('Issued by')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'active' => 'Active (unused)',
                        'used' => 'Used',
                        'expired' => 'Expired',
                        'revoked' => 'Revoked',
                    ])
                    ->default('active')
                    ->query(function (Builder $query, array $data) {
                        return match ($data['value'] ?? null) {
                            'active' => $query->whereNull('used_at')->whereNull('revoked_at')->where('expires_at', '>=', today()),
                            'used' => $query->whereNotNull('used_at'),
                            'revoked' => $query->whereNotNull('revoked_at'),
                            'expired' => $query->whereNull('used_at')->whereNull('revoked_at')->where('expires_at', '<', today()),
                            default => $query,
                        };
                    }),
                SelectFilter::make('plan_id')->label('Plan')->relationship('plan', 'name'),
                Filter::make('unassigned')
                    ->label('Not yet assigned to a school')
                    ->query(fn (Builder $query) => $query->whereNull('school_id')),
                Filter::make('gift')
                    ->label('Gift vouchers only')
                    ->query(fn (Builder $query) => $query->where('is_gift', true)),
            ])
            ->toolbarActions([
                BulkAction::make('print')
                    ->label('Print as vouchers')
                    ->icon('heroicon-o-printer')
                    ->deselectRecordsAfterCompletion()
                    ->action(function (Collection $records, $livewire): void {
                        $usable = SubscriptionActivationCode::whereKey($records->pluck('id')->all())->get()
                            ->filter(fn (SubscriptionActivationCode $code): bool => $code->status() === 'active');

                        if ($usable->isEmpty()) {
                            Notification::make()->title('Nothing to print')->body('Only codes not yet used, revoked or expired can be printed.')->warning()->send();

                            return;
                        }

                        $livewire->js('window.open('.json_encode(static::voucherUrl($usable->pluck('id')->all())).', "_blank")');
                    }),
            ])
            ->recordActions([
                Action::make('revoke')
                    ->label('Revoke')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (SubscriptionActivationCode $record) => $record->status() === 'active')
                    ->action(function (SubscriptionActivationCode $record): void {
                        $record->update(['revoked_at' => now()]);
                        Notification::make()->title('Code revoked')->success()->send();
                    }),
            ]);
    }

    /** @param  array<int, int>  $ids */
    public static function voucherUrl(array $ids): string
    {
        return route('filament.admin.gift-vouchers', ['codes' => implode(',', $ids)]);
    }

    /**
     * Gift vouchers to hand out (visits, head teachers' meetings): free
     * codes that start after the school's free term, printed four to a
     * page straight away.
     */
    public static function giftAction(): Action
    {
        return Action::make('gift')
            ->label('Make gift vouchers')
            ->icon('heroicon-o-gift')
            ->color('warning')
            ->modalHeading('Make gift vouchers')
            ->modalDescription('Free codes to hand out. A school that types one in gets the period free, starting when its free term ends. Nothing is recorded as paid.')
            ->fillForm(fn (): array => [
                'plan_id' => Plan::where('is_active', true)->where('is_trial', false)->orderBy('sort_order')->value('id'),
                'cycle' => 'term',
                'count' => 8,
                'valid_days' => 120,
            ])
            ->schema([
                Grid::make(2)->schema([
                    Select::make('plan_id')->label('Plan')->options(fn () => Plan::options())->required()->selectablePlaceholder(false),
                    Select::make('cycle')->label('Free for')->options(['term' => 'One term', 'year' => 'One year'])->required()->selectablePlaceholder(false),
                    TextInput::make('count')->label('How many vouchers')->numeric()->minValue(1)->maxValue(100)->required()->helperText('Printed four to an A4 page.'),
                    TextInput::make('valid_days')->label('Usable for (days)')->numeric()->minValue(7)->maxValue(365)->required()->helperText('Printed on the voucher as "Use before".'),
                    TextInput::make('notes')->label('Given out at')->placeholder('e.g. Wakiso head teachers\' meeting')->maxLength(150)->columnSpanFull()->helperText('So you know which event or visit brought each school in.'),
                ]),
            ])
            ->action(function (array $data, $livewire): void {
                $codes = SubscriptionManager::issueGiftVouchers(
                    Plan::whereKey($data['plan_id'])->firstOrFail(),
                    $data['cycle'],
                    (int) $data['count'],
                    (int) $data['valid_days'],
                    $data['notes'] ?? null,
                );

                Notification::make()->title($codes->count().' gift '.str('voucher')->plural($codes->count()).' made')->body('Opening them to print.')->success()->send();

                $livewire->js('window.open('.json_encode(static::voucherUrl($codes->pluck('id')->all())).', "_blank")');
            });
    }

    /** Mint a batch of unassigned codes for the same plan/cycle -- spare stock to keep on hand. */
    public static function generateAction(): Action
    {
        $price = function (Get $get, Set $set): void {
            $plan = Plan::find($get('plan_id'));

            if ($plan && in_array($get('cycle'), ['term', 'year'], true)) {
                $set('amount', $plan->priceFor($get('cycle')));
            }
        };

        return Action::make('generate')
            ->label('Generate codes')
            ->icon('heroicon-o-plus')
            ->color('primary')
            ->modalHeading('Generate activation codes')
            ->modalDescription('A batch of spare codes for one plan, not tied to any school yet. Keep the list on your phone and hand one out the moment a school pays - whoever types it in first claims it for their school.')
            ->fillForm(function () {
                $plan = Plan::where('is_active', true)->where('is_trial', false)->orderBy('sort_order')->first();

                return [
                    'plan_id' => $plan?->getKey(),
                    'cycle' => 'term',
                    'amount' => $plan?->price_per_term,
                    'count' => 10,
                    'valid_days' => 30,
                    'method' => 'mobile_money',
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
                        ->options(['term' => 'One school term', 'year' => 'One year'])
                        ->required()
                        ->selectablePlaceholder(false)
                        ->live()
                        ->afterStateUpdated($price),
                    MoneyInput::make('amount')
                        ->label('Price of the period (UGX)')
                        ->numeric()
                        ->minValue(0)
                        ->required(),
                    Select::make('method')
                        ->label('Assumed payment method')
                        ->options(SubscriptionPayment::METHODS)
                        ->helperText('Recorded when a code is redeemed. Edit the payment afterwards if it turns out different.')
                        ->required(),
                    TextInput::make('count')
                        ->label('How many codes')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(50)
                        ->required(),
                    TextInput::make('valid_days')
                        ->label('Each code valid for (days)')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(180)
                        ->required()
                        ->helperText('Unused codes stop working after this many days.'),
                ]),
            ])
            ->action(function (array $data): void {
                $codes = SubscriptionManager::issueStockCodes(
                    Plan::findOrFail($data['plan_id']),
                    $data['cycle'],
                    (float) $data['amount'],
                    (int) $data['count'],
                    ['amount' => (float) $data['amount'], 'method' => $data['method']],
                    (int) $data['valid_days'],
                );

                Notification::make()
                    ->title(count($codes).' '.str('code')->plural(count($codes)).' generated')
                    ->body($codes->pluck('code')->implode("\n"))
                    ->success()
                    ->persistent()
                    ->send();
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageActivationCodes::route('/'),
        ];
    }
}
