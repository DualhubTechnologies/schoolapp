<?php

namespace App\Filament\App\Resources\Students;

use App\Filament\App\Resources\Students\Pages\EditStudent;
use App\Filament\App\Resources\Students\Pages\ListStudents;
use App\Filament\App\Resources\Students\Schemas\StudentForm;
use App\Filament\App\Resources\Students\Tables\StudentsTable;
use App\Filament\Concerns\GatedByModule;
use App\Models\Student;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class StudentResource extends Resource
{
    use GatedByModule;

    protected static ?string $model = Student::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 1;

    /**
     * "Allan Kato (ADM-0001)".
     */
    public static function getRecordTitle(?Model $record): string|Htmlable|null
    {
        return $record ? trim("{$record->name} ({$record->admission_no})") : null;
    }

    public static function form(Schema $schema): Schema
    {
        return StudentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StudentsTable::configure($table);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return ! auth()->user()?->hasRole('Super Admin');
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Students';
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getEloquentQuery()->where('status', 'active')->count();
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

    public static function getPages(): array
    {
        return [
            'index' => ListStudents::route('/'),
            'edit' => EditStudent::route('/{record}/edit'),
        ];
    }
}
