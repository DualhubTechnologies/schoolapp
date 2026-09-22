<?php

namespace App\Filament\App\Resources\PayrollPeriods;

use App\Filament\App\Resources\PayrollPeriods\Pages\CreatePayrollPeriod;
use App\Filament\App\Resources\PayrollPeriods\Pages\ListPayrollPeriods;
use App\Filament\App\Resources\PayrollPeriods\Pages\ViewPayrollPeriod;
use App\Filament\App\Resources\PayrollPeriods\Schemas\PayrollPeriodForm;
use App\Filament\App\Resources\PayrollPeriods\Tables\PayrollPeriodsTable;
use App\Models\PayrollPeriod;
use BackedEnum;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Monthly payroll runs: draft → approved → paid.
 */
class PayrollPeriodResource extends Resource
{
    use \App\Filament\Concerns\GatedByModule;

    protected static ?string $model = PayrollPeriod::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWallet;

    protected static ?string $navigationLabel = 'Payroll';

    protected static ?string $modelLabel = 'payroll run';

    protected static ?string $pluralModelLabel = 'Payroll';

    protected static ?int $navigationSort = 2;

    /**
     * "September 2026".
     */
    public static function getRecordTitle(?Model $record): string|Htmlable|null
    {
        return $record ? 'Payroll — ' . $record->period_label : null;
    }

    public static function form(Schema $schema): Schema
    {
        return PayrollPeriodForm::configure($schema);
    }

    /**
     * The run at a glance: what staff take home, and what goes to URA,
     * NSSF and the local government.
     */
    public static function infolist(Schema $schema): Schema
    {
        $money = fn ($state) => 'UGX ' . number_format((float) $state);

        return $schema->components([
            Section::make()
                ->schema([
                    Grid::make(['default' => 2, 'md' => 4])->schema([
                        TextEntry::make('status')
                            ->badge()
                            ->formatStateUsing(fn (string $state) => PayrollPeriod::STATUSES[$state] ?? $state)
                            ->color(fn (string $state) => match ($state) {
                                'draft' => 'gray',
                                'approved' => 'warning',
                                'paid' => 'success',
                                default => 'gray',
                            }),
                        TextEntry::make('staff_count')->label('Staff paid'),
                        TextEntry::make('total_gross')->label('Gross pay')->formatStateUsing($money),
                        TextEntry::make('total_net')
                            ->label('Net pay (to staff)')
                            ->formatStateUsing($money)
                            ->weight(FontWeight::Bold)
                            ->size(TextSize::Large)
                            ->color('success'),
                    ]),
                ]),

            Section::make('Statutory remittances')
                ->description('What the school must pay to URA, NSSF and the local government for this month.')
                ->icon('heroicon-o-building-library')
                ->collapsible()
                ->schema([
                    Grid::make(['default' => 2, 'md' => 4])->schema([
                        TextEntry::make('total_paye')->label('PAYE → URA')->formatStateUsing($money),
                        TextEntry::make('nssf_total')
                            ->label('NSSF → NSSF (15%)')
                            ->state(fn (PayrollPeriod $record) => (float) $record->total_nssf_employee + (float) $record->total_employer_nssf)
                            ->formatStateUsing($money)
                            ->helperText(fn (PayrollPeriod $record) => 'Staff 5%: ' . number_format((float) $record->total_nssf_employee) . ' · School 10%: ' . number_format((float) $record->total_employer_nssf)),
                        TextEntry::make('total_lst')->label('LST → Local government')->formatStateUsing($money),
                        TextEntry::make('employer_cost')
                            ->label('Total cost to school')
                            ->state(fn (PayrollPeriod $record) => (float) $record->total_gross + (float) $record->total_employer_nssf)
                            ->formatStateUsing($money)
                            ->helperText('Gross pay + employer NSSF'),
                    ]),
                ]),

            Section::make('Approval & payment')
                ->icon('heroicon-o-check-badge')
                ->collapsible()
                ->collapsed(fn (PayrollPeriod $record) => $record->isDraft())
                ->schema([
                    Grid::make(['default' => 2, 'md' => 4])->schema([
                        TextEntry::make('approvedBy.name')->label('Approved by')->placeholder('Not yet'),
                        TextEntry::make('approved_at')->label('Approved on')->dateTime('j M Y, H:i')->placeholder('—'),
                        TextEntry::make('payment_date')->label('Paid on')->date('j M Y')->placeholder('Not yet'),
                        TextEntry::make('payment_method')
                            ->label('Paid by')
                            ->formatStateUsing(fn (?string $state) => PayrollPeriod::PAYMENT_METHODS[$state] ?? $state)
                            ->helperText(fn (PayrollPeriod $record) => $record->payment_reference)
                            ->placeholder('—'),
                    ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return PayrollPeriodsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $user = auth()->user();

        if ($user && ! $user->hasRole('Super Admin')) {
            $query->where('school_id', $user->school_id);
        }

        return $query;
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\PayrollEntriesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPayrollPeriods::route('/'),
            'create' => CreatePayrollPeriod::route('/create'),
            'view' => ViewPayrollPeriod::route('/{record}'),
        ];
    }

    // A run's month is fixed once created; figures change only by
    // recalculating the draft.
    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return $record->status === 'draft';
    }

    public static function shouldRegisterNavigation(): bool
    {
        return \App\Support\PayrollAccess::allowed();
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Human Resources';
    }

    /**
     * Staff pay is confidential: School Admin and Accountant only.
     */
    public static function canViewAny(): bool
    {
        return \App\Support\PayrollAccess::allowed();
    }
}
