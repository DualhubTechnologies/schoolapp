<?php

namespace App\Filament\Admin\Resources\WindowsLicences;

use App\Filament\Admin\Resources\WindowsLicences\Pages\ManageWindowsLicences;
use App\Models\IssuedLicence;
use App\Models\Plan;
use App\Support\Licensing\LicenceIssuer;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use RuntimeException;

/**
 * Licences for the Windows app (platform owner, online only). A school
 * pays, sends its school name and code from its Licence page, and gets
 * back the key issued here. Renewing issues the next period's key.
 */
class WindowsLicenceResource extends Resource
{
    protected static ?string $model = IssuedLicence::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedComputerDesktop;

    protected static string|\UnitEnum|null $navigationGroup = 'Platform Management';

    protected static ?string $navigationLabel = 'Windows licences';

    protected static ?string $modelLabel = 'Windows licence';

    protected static ?int $navigationSort = 4;

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
                TextColumn::make('short_code')->label('Licence code')->fontFamily('mono')->weight('bold')->copyable()->searchable()
                    ->description(fn (IssuedLicence $r) => $r->licence_no),
                TextColumn::make('school_name')->label('School')->searchable()->placeholder('Any school (first to enter it)')
                    ->description(fn (IssuedLicence $r) => $r->school_code),
                TextColumn::make('activated_at')->label('Used')->badge()
                    ->state(fn (IssuedLicence $r) => $r->activated_at ? 'Used '.$r->activated_at->format('j M Y') : 'Not used yet')
                    ->color(fn (IssuedLicence $r) => $r->activated_at ? 'success' : 'gray'),
                TextColumn::make('plan_name')->label('Plan'),
                TextColumn::make('cycle')->formatStateUsing(fn (string $state) => IssuedLicence::CYCLES[$state] ?? $state),
                TextColumn::make('ends_on')->label('Valid')->formatStateUsing(fn (IssuedLicence $r) => $r->starts_on->format('j M Y').' – '.$r->ends_on->format('j M Y'))
                    ->color(fn (IssuedLicence $r) => $r->ends_on->isPast() ? 'danger' : null),
                TextColumn::make('amount')->numeric()->prefix('UGX '),
                TextColumn::make('payment_reference')->label('Payment ref.')->toggleable(),
                TextColumn::make('issuer.name')->label('Issued by')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                Action::make('showKey')
                    ->label('Show code')
                    ->icon('heroicon-o-key')
                    ->modalHeading(fn (IssuedLicence $r) => 'Licence code'.($r->school_name ? " for {$r->school_name}" : ''))
                    ->modalDescription('Send this code to the school (WhatsApp or SMS). They type it on their Licence page; entering it needs the internet once.')
                    ->modalContent(fn (IssuedLicence $r) => new HtmlString(
                        '<div style="text-align:center;font-family:ui-monospace,Consolas,monospace;font-size:1.6rem;font-weight:700;letter-spacing:.08em;padding:1rem;border:1px dashed #cbd5e1;border-radius:10px;user-select:all">'.e((string) $r->short_code).'</div>'
                        .($r->key ? '<details style="margin-top:1rem;font-size:.8rem;color:#475569"><summary style="cursor:pointer">Long key, for a school that can never get online</summary><textarea readonly rows="5" onclick="this.select()" style="margin-top:.5rem;width:100%;font-family:ui-monospace,Consolas,monospace;font-size:.75rem;border:1px solid #cbd5e1;border-radius:8px;padding:.5rem">'.e($r->key).'</textarea></details>' : '')
                    ))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
                static::issueAction('renew')
                    ->label('Renew')
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->visible(fn (IssuedLicence $r) => filled($r->school_code))
                    ->modalHeading(fn (IssuedLicence $r) => "Renew {$r->school_name}")
                    ->fillForm(function (IssuedLicence $r): array {
                        $starts = CarbonImmutable::parse($r->ends_on)->addDay();

                        return [
                            'school_name' => $r->school_name,
                            'school_code' => $r->school_code,
                            'plan_id' => $r->plan_id,
                            'max_students' => $r->max_students,
                            'max_users' => $r->max_users,
                            // After a trial, a paid term.
                            'cycle' => $r->cycle === 'trial' ? 'term' : $r->cycle,
                            'starts_on' => $starts->toDateString(),
                            'ends_on' => LicenceIssuer::defaultEnd($starts, $r->cycle === 'trial' ? 'term' : $r->cycle)->toDateString(),
                            'amount' => $r->plan?->priceFor($r->cycle === 'trial' ? 'term' : $r->cycle),
                        ];
                    }),
            ]);
    }

    /** "Issue licence" (and, prefilled, "Renew"). */
    public static function issueAction(string $name = 'issue'): Action
    {
        $fromPlan = function (Get $get, Set $set): void {
            $plan = filled($get('plan_id')) ? Plan::query()->whereKey((int) $get('plan_id'))->first() : null;

            if ($plan) {
                $set('max_students', $plan->max_students);
                $set('max_users', $plan->max_users);
                $set('amount', $get('cycle') === 'trial' ? 0 : $plan->priceFor((string) $get('cycle')));
            }
        };

        // Choosing "Free trial" picks the trial plan; leaving it, the first paid plan.
        $trialPlan = function (Get $get, Set $set): void {
            $trial = $get('cycle') === 'trial';
            $current = filled($get('plan_id')) ? Plan::query()->whereKey((int) $get('plan_id'))->first() : null;

            if (! $current || $current->is_trial !== $trial) {
                $plan = Plan::where('is_active', true)->where('is_trial', $trial)->orderBy('sort_order')->first();
                $set('plan_id', $plan?->getKey());
            }
        };

        $ends = function (Get $get, Set $set): void {
            if ($get('starts_on') && array_key_exists((string) $get('cycle'), LicenceIssuer::CYCLES)) {
                $set('ends_on', LicenceIssuer::defaultEnd(CarbonImmutable::parse($get('starts_on')), $get('cycle'))->toDateString());
            }
        };

        return Action::make($name)
            ->label('Issue licence')
            ->icon('heroicon-o-plus')
            ->modalHeading('Issue a Windows licence')
            ->modalDescription('Gives a licence code (like ABCD-EFGH-2345-JKLM) to send the school. Leave the school blank and the code belongs to whichever school enters it first; either way it works for one school only.')
            ->modalSubmitActionLabel('Issue licence')
            ->fillForm(function () {
                $plan = Plan::where('is_active', true)->where('is_trial', false)->orderBy('sort_order')->first();
                $starts = CarbonImmutable::today();

                return [
                    'plan_id' => $plan?->getKey(),
                    'max_students' => $plan?->max_students,
                    'max_users' => $plan?->max_users,
                    'cycle' => 'term',
                    'starts_on' => $starts->toDateString(),
                    'ends_on' => LicenceIssuer::defaultEnd($starts, 'term')->toDateString(),
                    'amount' => $plan?->price_per_term,
                ];
            })
            ->schema([
                Section::make('School (optional)')
                    ->description('Only to tie the code to one school in advance, by its name and code from School Profile. Usually left blank.')
                    ->collapsed()
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('school_name')->label('School name')->maxLength(150)->requiredWith('school_code'),
                            TextInput::make('school_code')->label('School code')->maxLength(20)->placeholder('SH12345')->requiredWith('school_name'),
                        ]),
                    ]),
                Section::make('Licence')->schema([
                    Grid::make(2)->schema([
                        Select::make('plan_id')->label('Plan')->options(fn () => Plan::options())->live()->afterStateUpdated($fromPlan)->required(),
                        ToggleButtons::make('cycle')->label('Licence')->options(IssuedLicence::CYCLES)->inline()->live()->required()
                            ->afterStateUpdated(function (Get $get, Set $set) use ($fromPlan, $ends, $trialPlan): void {
                                $trialPlan($get, $set);
                                $fromPlan($get, $set);
                                $ends($get, $set);
                            }),
                        TextInput::make('max_students')->label('Learners (blank = unlimited)')->numeric()->minValue(0),
                        TextInput::make('max_users')->label('Staff logins (blank = unlimited)')->numeric()->minValue(0),
                        DatePicker::make('starts_on')->label('Starts')->required()->live()->afterStateUpdated($ends),
                        DatePicker::make('ends_on')->label('Ends')->required()->afterOrEqual('starts_on'),
                    ]),
                ]),
                Section::make('Payment')->schema([
                    Grid::make(2)->schema([
                        TextInput::make('amount')->label('Amount paid (UGX)')->numeric()->minValue(0),
                        TextInput::make('payment_reference')->label('Payment reference')->maxLength(100)->placeholder('Mobile money or bank reference'),
                    ]),
                    Textarea::make('notes')->rows(2)->maxLength(1000),
                ]),
            ])
            ->action(function (array $data, Action $action): void {
                try {
                    $licence = app(LicenceIssuer::class)->issue([
                        'school_name' => $data['school_name'] ?? null,
                        'school_code' => $data['school_code'] ?? null,
                        'plan_id' => $data['plan_id'] ?? null,
                        'max_students' => $data['max_students'] ?? null,
                        'max_users' => $data['max_users'] ?? null,
                        'cycle' => (string) $data['cycle'],
                        'starts_on' => (string) $data['starts_on'],
                        'ends_on' => (string) $data['ends_on'],
                        'amount' => $data['amount'] ?? null,
                        'payment_reference' => $data['payment_reference'] ?? null,
                        'notes' => $data['notes'] ?? null,
                    ], auth()->user());
                } catch (RuntimeException $e) {
                    Notification::make()->title('Licence not issued')->body($e->getMessage())->danger()->send();
                    $action->halt();

                    return;
                }

                Notification::make()
                    ->title("Licence {$licence->licence_no} issued")
                    ->body("Licence code: {$licence->short_code}. Send it to the school; \"Show code\" shows it again.")
                    ->persistent()
                    ->success()
                    ->send();
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageWindowsLicences::route('/'),
        ];
    }
}
