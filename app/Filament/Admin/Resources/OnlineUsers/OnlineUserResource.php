<?php

namespace App\Filament\Admin\Resources\OnlineUsers;

use App\Filament\Admin\Resources\OnlineUsers\Pages\ListOnlineUsers;
use App\Models\School;
use App\Models\UserSession;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Super Admin: who is signed in to SchoolHub right now, from which school
 * and on what device. Built on the database session driver.
 */
class OnlineUserResource extends Resource
{
    protected static ?string $model = UserSession::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSignal;

    protected static string|\UnitEnum|null $navigationGroup = 'Platform Management';

    protected static ?string $navigationLabel = 'Online users';

    protected static ?string $modelLabel = 'Online user';

    protected static ?string $pluralModelLabel = 'Online users';

    protected static ?string $slug = 'online-users';

    protected static ?int $navigationSort = 6;

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasRole('Super Admin') ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    /**
     * How many people are active now, on the sidebar item.
     */
    public static function getNavigationBadge(): ?string
    {
        if (config('session.driver') !== 'database') {
            return null;
        }

        $active = UserSession::activeNow()->distinct()->count('user_id');

        return $active ? (string) $active : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Active in the last '.UserSession::ACTIVE_MINUTES.' minutes';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->signedIn()
            ->with(['user.school', 'user.roles']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('last_activity', 'desc')
            ->poll('30s')
            ->columns([
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->state(fn (UserSession $record): string => $record->isActiveNow() ? 'Active now' : 'Idle')
                    ->color(fn (string $state): string => $state === 'Active now' ? 'success' : 'gray'),
                TextColumn::make('user.name')
                    ->label('User')
                    ->weight('semibold')
                    ->description(fn (UserSession $record): ?string => $record->user?->email)
                    ->searchable(),
                TextColumn::make('school')
                    ->label('School')
                    ->state(fn (UserSession $record): string => $record->user?->school?->name
                        ?? ($record->user?->hasRole('Super Admin') ? 'Platform' : '—')),
                TextColumn::make('role')
                    ->label('Role')
                    ->badge()
                    ->color('gray')
                    ->state(fn (UserSession $record): string => $record->user?->roles->pluck('name')->join(', ') ?: '—'),
                TextColumn::make('last_activity')
                    ->label('Last seen')
                    ->formatStateUsing(fn (UserSession $record): string => $record->lastSeen()->diffForHumans())
                    ->description(fn (UserSession $record): string => $record->lastSeen()->format('d M Y H:i'))
                    ->sortable(),
                TextColumn::make('device')
                    ->label('Device')
                    ->state(fn (UserSession $record): string => $record->device())
                    ->tooltip(fn (UserSession $record): ?string => $record->user_agent),
                TextColumn::make('ip_address')
                    ->label('IP')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                TernaryFilter::make('active_now')
                    ->label('Status')
                    ->placeholder('Everyone signed in')
                    ->trueLabel('Active now')
                    ->falseLabel('Idle')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->activeNow(),
                        false: fn (Builder $query): Builder => $query->where('last_activity', '<', now()->subMinutes(UserSession::ACTIVE_MINUTES)->getTimestamp()),
                    ),
                SelectFilter::make('school')
                    ->label('School')
                    ->options(fn (): array => School::orderBy('name')->pluck('name', 'id')->all())
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $q, $schoolId) => $q->whereHas('user', fn (Builder $u) => $u->where('school_id', $schoolId)),
                    )),
            ])
            ->paginationPageOptions([25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading(config('session.driver') === 'database'
                ? 'Nobody is signed in right now'
                : 'Online users needs the database session driver')
            ->emptyStateDescription(config('session.driver') === 'database'
                ? null
                : 'Set SESSION_DRIVER=database in the server\'s .env to see who is signed in.')
            ->emptyStateIcon('heroicon-o-signal');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOnlineUsers::route('/'),
        ];
    }
}
