<?php

namespace App\Filament\App\Resources\Users;

use App\Filament\App\Resources\Users\Pages\CreateUser;
use App\Filament\App\Resources\Users\Pages\EditUser;
use App\Filament\App\Resources\Users\Pages\ListUsers;
use App\Filament\App\Resources\Users\Schemas\UserForm;
use App\Filament\App\Resources\Users\Tables\UsersTable;
use App\Models\User;
use App\Support\Modules;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UserResource extends Resource
{
    // No GatedByModule here: canViewAny() below is already the full,
    // correct rule (School Admin or Super Admin only) -- the trait's
    // module check would only narrow it further and wrongly excludes
    // Super Admin, who has no school modules of its own.

    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return UserForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UsersTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $user = auth()->user();

        if ($user && ! $user->hasRole('Super Admin')) {
            $query->where('school_id', $user->school_id)
                ->whereDoesntHave('roles', fn ($q) => $q->where('name', 'Super Admin'));
        }

        return $query;
    }

    /**
     * Logins decide who can open what, so only School Admins (and the
     * platform owner) manage them - never someone given the Settings module.
     */
    public static function canViewAny(): bool
    {
        return Modules::hasFullAccess(auth()->user());
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): ?string
    {
        if (auth()->user()?->hasRole('Super Admin')) {
            return 'Platform Management';
        }

        return 'Settings';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
