<?php

namespace App\Filament\App\Resources\SchoolPayTransactions;

use App\Filament\App\Resources\SchoolPayTransactions\Pages\ListSchoolPayTransactions;
use App\Filament\Concerns\GatedByModule;
use App\Filament\Pages\ReceivePayment;
use App\Models\SchoolPayTransaction;
use App\Models\Student;
use App\Services\SchoolPay\SchoolPayPayments;
use App\Support\Modules;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * Every payment SchoolPay reported for the school: the ones already turned
 * into receipts, school fees still waiting for the bursar to pick the
 * learner, and other fees (uniform, trips...) listed for reference.
 * Recorded by App\Services\SchoolPay\SchoolPayPayments.
 */
class SchoolPayTransactionResource extends Resource
{
    use GatedByModule;

    protected static ?string $model = SchoolPayTransaction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static string|\UnitEnum|null $navigationGroup = 'Fees';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'SchoolPay payments';

    protected static ?string $modelLabel = 'SchoolPay payment';

    protected static ?string $pluralModelLabel = 'SchoolPay payments';

    protected static ?string $slug = 'schoolpay-payments';

    public static function canViewAny(): bool
    {
        return Modules::allows('fees') && filled(auth()->user()?->school_id);
    }

    /** In the menu once the school has turned SchoolPay on. */
    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny() && (bool) auth()->user()?->school?->schoolpay_enabled;
    }

    /** Payments still waiting for a learner, on the menu item. */
    public static function getNavigationBadge(): ?string
    {
        $waiting = SchoolPayTransaction::where('school_id', auth()->user()?->school_id)->where('status', 'unmatched')->count();

        return $waiting > 0 ? (string) $waiting : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Waiting for you to pick the learner';
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

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('school_id', auth()->user()?->school_id)
            ->with(['student.schoolClass', 'payment']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('paid_at', 'desc')
            ->columns([
                TextColumn::make('paid_at')
                    ->label('Paid')
                    ->dateTime('j M Y H:i')
                    ->sortable(),

                TextColumn::make('student_name')
                    ->label('Name on SchoolPay')
                    ->weight(FontWeight::SemiBold)
                    ->description(fn (SchoolPayTransaction $record): string => collect([
                        $record->student_payment_code ? "Code {$record->student_payment_code}" : null,
                        $record->student_registration_number ? "Reg. {$record->student_registration_number}" : null,
                    ])->filter()->implode(' · '))
                    ->searchable(['student_name', 'student_payment_code', 'student_registration_number', 'receipt_number']),

                TextColumn::make('amount')
                    ->label('Amount (UGX)')
                    ->numeric(decimalPlaces: 0)
                    ->alignEnd()
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => SchoolPayTransaction::STATUSES[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'recorded' => 'success',
                        'unmatched' => 'warning',
                        default => 'gray',
                    })
                    ->description(fn (SchoolPayTransaction $record): ?string => $record->status === 'other_fees' ? $record->fee_description : null),

                TextColumn::make('student.name')
                    ->label('Learner in SchoolHub')
                    ->description(fn (SchoolPayTransaction $record): ?string => $record->payment?->receipt_no ? "Receipt {$record->payment->receipt_no}" : null)
                    ->placeholder('—'),

                TextColumn::make('channel')
                    ->label('Paid through')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('receipt_number')
                    ->label('SchoolPay receipt')
                    ->fontFamily('mono')
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(SchoolPayTransaction::STATUSES),
            ])
            ->recordActions([
                static::matchAction(),
                Action::make('receipt')
                    ->label('Receipt')
                    ->icon('heroicon-o-receipt-percent')
                    ->color('gray')
                    ->url(fn (SchoolPayTransaction $record): ?string => $record->student_payment_id ? ReceivePayment::receiptUrl($record->student_payment_id) : null)
                    ->visible(fn (SchoolPayTransaction $record): bool => $record->student_payment_id !== null),
            ])
            ->emptyStateHeading('No SchoolPay payments yet')
            ->emptyStateDescription('Payments parents make through SchoolPay appear here, and are recorded on the learner\'s fees by themselves.');
    }

    /** Pick the learner for a school-fees payment SchoolPay could not match. */
    public static function matchAction(): Action
    {
        return Action::make('match')
            ->label('Pick learner')
            ->icon('heroicon-o-user-plus')
            ->color('warning')
            ->visible(fn (SchoolPayTransaction $record): bool => $record->status === 'unmatched')
            ->modalHeading(fn (SchoolPayTransaction $record): string => 'Whose payment is this? UGX '.number_format((float) $record->amount))
            ->modalDescription(fn (SchoolPayTransaction $record): string => 'SchoolPay says: '.($record->student_name ?: 'no name')
                .($record->student_payment_code ? ", code {$record->student_payment_code}" : '')
                .($record->student_registration_number ? ", reg. {$record->student_registration_number}" : '').'.')
            ->schema([
                Select::make('student_id')
                    ->label('Learner')
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search): array => Student::query()
                        ->where('school_id', auth()->user()?->school_id)
                        ->where(fn (Builder $q) => $q
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('admission_no', 'like', "%{$search}%")
                            ->orWhere('schoolpay_code', 'like', "%{$search}%"))
                        ->limit(30)
                        ->get()
                        ->mapWithKeys(fn (Student $s): array => [$s->id => "{$s->name} ({$s->admission_no})"])
                        ->all())
                    ->getOptionLabelUsing(fn ($value): ?string => Student::where('school_id', auth()->user()?->school_id)->find($value)?->name)
                    ->required(),
                Toggle::make('remember_code')
                    ->label('Save this SchoolPay code on the learner, so their next payments are recorded by themselves')
                    ->default(true)
                    ->visible(fn (SchoolPayTransaction $record): bool => filled($record->student_payment_code)),
            ])
            ->modalSubmitActionLabel('Record payment')
            ->action(function (SchoolPayTransaction $record, array $data, SchoolPayPayments $payments): void {
                $student = Student::where('school_id', auth()->user()?->school_id)->findOrFail($data['student_id']);

                try {
                    $payment = $payments->assign($record, $student, (bool) ($data['remember_code'] ?? false));
                } catch (RuntimeException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()
                    ->title("Receipt {$payment->receipt_no} issued")
                    ->body('UGX '.number_format((float) $payment->amount)." recorded for {$student->name}.")
                    ->success()
                    ->send();
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSchoolPayTransactions::route('/'),
        ];
    }
}
