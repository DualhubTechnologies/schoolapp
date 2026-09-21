<?php

namespace App\Filament\App\Resources\StudentDiscounts;

use App\Filament\App\Resources\StudentDiscounts\Pages\CreateStudentDiscount;
use App\Filament\App\Resources\StudentDiscounts\Pages\EditStudentDiscount;
use App\Filament\App\Resources\StudentDiscounts\Pages\ListStudentDiscounts;
use App\Filament\App\Resources\StudentDiscounts\Schemas\StudentDiscountForm;
use App\Filament\App\Resources\StudentDiscounts\Tables\StudentDiscountsTable;
use App\Models\StudentDiscount;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StudentDiscountResource extends Resource
{
    protected static ?string $model = StudentDiscount::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static string|\UnitEnum|null $navigationGroup = 'Fees';

    protected static ?int $navigationSort = 6;

    protected static ?string $navigationLabel = 'Discounts & Bursaries';

    protected static ?string $modelLabel = 'discount';

    public static function form(Schema $schema): Schema
    {
        return StudentDiscountForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StudentDiscountsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['student', 'feeStructure', 'term']);

        $user = auth()->user();

        if ($user && ! $user->hasRole('Super Admin')) {
            $query->where('school_id', $user->school_id);
        }

        return $query;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStudentDiscounts::route('/'),
            'create' => CreateStudentDiscount::route('/create'),
            'edit' => EditStudentDiscount::route('/{record}/edit'),
        ];
    }
}
