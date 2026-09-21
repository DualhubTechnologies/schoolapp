<?php

namespace App\Filament\App\Resources\Payments;

use App\Filament\App\Resources\Payments\Pages\ListPayments;
use App\Filament\App\Resources\Payments\Tables\PaymentsTable;
use App\Models\StudentPayment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The receipt book: every payment received, including voided receipts
 * (shown struck through), so the sequence of receipt numbers has no gaps.
 */
class PaymentResource extends Resource
{
    protected static ?string $model = StudentPayment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|\UnitEnum|null $navigationGroup = 'Fees';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Payments';

    protected static ?string $modelLabel = 'payment';

    protected static ?string $pluralModelLabel = 'Payments';

    protected static ?string $slug = 'payments';

    public static function getRecordTitle(?Model $record): string|Htmlable|null
    {
        return $record ? "Receipt {$record->receipt_no}" : null;
    }

    public static function table(Table $table): Table
    {
        return PaymentsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return StudentPayment::withVoided()
            ->with(['student.schoolClass', 'term'])
            ->when(
                ! auth()->user()?->hasRole('Super Admin'),
                fn (Builder $q) => $q->where('student_payments.school_id', auth()->user()?->school_id),
            );
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasRole(['Super Admin', 'School Admin', 'Accountant', 'Bursar']) ?? false;
    }

    // Payments are taken on the Receive Payment page and never edited or
    // deleted -- a wrong receipt is voided from the table.
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
            'index' => ListPayments::route('/'),
        ];
    }
}
