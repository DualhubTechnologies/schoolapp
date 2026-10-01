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
                TextColumn::make('licence_no')->label('Licence')->fontFamily('mono')->weight('bold')->searchable(),
                TextColumn::make('school_name')->label('School')->searchable()->description(fn (IssuedLicence $r) => $r->school_code),
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
                    ->label('Show key')
                    ->icon('heroicon-o-key')
                    ->modalHeading(fn (IssuedLicence $r) => "Licence key for {$r->school_name}")
                    ->modalDescription('Send the whole key to the school (WhatsApp or email). They paste it on their Licence page; no internet is needed there.')
                    ->modalContent(fn (IssuedLicence $r) => new HtmlString(
                        '<textarea readonly rows="6" onclick="this.select()" style="width:100%;font-family:ui-monospace,Consolas,monospace;font-size:.8rem;border:1px solid #cbd5e1;border-radius:8px;padding:.6rem">'.e($r->key).'</textarea>'
                    ))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
                static::issueAction('renew')
                    ->label('Renew')
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->modalHeading(fn (IssuedLicence $r) => "Renew {$r->school_name}")
                    ->fillForm(function (IssuedLicence $r): array {
                        $starts = CarbonImmutable::parse($r->ends_on)->addDay();

                        return [
                            'school_name' => $r->school_name,
                            'school_code' => $r->school_code,
                            'plan_id' => $r->plan_id,
                            'max_students' => $r->max_students,
                            'max_users' => $r->max_users,
                            'cycle' => $r->cycle,
                            'starts_on' => $starts->toDateString(),
                            'ends_on' => LicenceIssuer::defaultEnd($starts, $r->cycle)->toDateString(),
                            'amount' => $r->plan?->priceFor($r->cycle),
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

                if (in_array($get('cycle'), ['term', 'year'], true)) {
                    $set('amount', $plan->priceFor($get('cycle')));
                }
            }
        };

        $ends = function (Get $get, Set $set): void {
            if ($get('starts_on') && in_array($get('cycle'), ['term', 'year'], true)) {
                $set('ends_on', LicenceIssuer::defaultEnd(CarbonImmutable::parse($get('starts_on')), $get('cycle'))->toDateString());
            }
        };

        return Action::make($name)
            ->label('Issue licence')
            ->icon('heroicon-o-plus')
            ->modalHeading('Issue a Windows licence')
            ->modalDescription('Enter the school name and code exactly as shown on the school\'s Licence page: the key only works for that school.')
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
                Section::make('School')->schema([
                    Grid::make(2)->schema([
                        TextInput::make('school_name')->label('School name')->required()->maxLength(150),
                        TextInput::make('school_code')->label('School code')->required()->maxLength(20)->placeholder('SH12345'),
                    ]),
                ]),
                Section::make('Licence')->schema([
                    Grid::make(2)->schema([
                        Select::make('plan_id')->label('Plan')->options(fn () => Plan::options())->live()->afterStateUpdated($fromPlan)->required(),
                        ToggleButtons::make('cycle')->options(IssuedLicence::CYCLES)->inline()->live()->required()
                            ->afterStateUpdated(function (Get $get, Set $set) use ($fromPlan, $ends): void {
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
                        'school_name' => (string) $data['school_name'],
                        'school_code' => (string) $data['school_code'],
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
                    ->body('Click "Show key" on it to copy the key for the school.')
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
