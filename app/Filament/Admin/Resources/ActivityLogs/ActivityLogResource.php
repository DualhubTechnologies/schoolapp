<?php

namespace App\Filament\Admin\Resources\ActivityLogs;

use App\Filament\Admin\Resources\ActivityLogs\Pages\ListActivityLogs;
use App\Models\School;
use App\Models\User;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Spatie\Activitylog\Models\Activity;

/**
 * The platform owner's view of the audit log: every school's sign-ins,
 * failed sign-ins and record changes, with the school each came from.
 * Read-only, like the schools' own Audit Trail.
 */
class ActivityLogResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|\UnitEnum|null $navigationGroup = 'Platform Management';

    protected static ?string $navigationLabel = 'Activity logs';

    protected static ?string $modelLabel = 'Activity';

    protected static ?string $pluralModelLabel = 'Activity logs';

    protected static ?string $slug = 'activity-logs';

    protected static ?int $navigationSort = 5;

    /** event => [label, colour] */
    public const EVENTS = [
        'login' => ['Signed in', 'info'],
        'logout' => ['Signed out', 'gray'],
        'login_failed' => ['Failed sign-in', 'danger'],
        'created' => ['Created', 'success'],
        'updated' => ['Updated', 'warning'],
        'deleted' => ['Deleted', 'danger'],
    ];

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasRole('Super Admin') ?? false;
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
        return parent::getEloquentQuery()->with([
            'causer' => fn (Relation $causer) => $causer instanceof MorphTo ? $causer->morphWith([User::class => ['school']]) : $causer,
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort(fn (Builder $query) => $query->orderByDesc('created_at')->orderByDesc('id'))
            ->columns([
                TextColumn::make('created_at')
                    ->label('When')
                    ->dateTime('d M Y H:i')
                    ->description(fn (Activity $record): string => $record->created_at->diffForHumans()),
                TextColumn::make('causer.name')
                    ->label('User')
                    // Failed sign-ins have no user, only the email that was tried.
                    ->state(fn (Activity $record): string => self::user($record)->name
                        ?? data_get($record->properties, 'email')
                        ?? 'System')
                    ->description(fn (Activity $record): ?string => self::user($record)?->email),
                TextColumn::make('school')
                    ->label('School')
                    ->state(fn (Activity $record): string => self::user($record)->school->name
                        ?? (self::user($record)?->hasRole('Super Admin') ? 'Platform' : '—')),
                TextColumn::make('event')
                    ->label('Action')
                    ->badge()
                    ->state(fn (Activity $record): string => $record->event ?? $record->description)
                    ->formatStateUsing(fn (string $state): string => self::EVENTS[$state][0] ?? ucfirst(str_replace('_', ' ', $state)))
                    ->color(fn (string $state): string => self::EVENTS[$state][1] ?? 'gray'),
                TextColumn::make('subject_type')
                    ->label('Record')
                    ->formatStateUsing(fn (?string $state): string => $state ? class_basename($state) : '—')
                    ->description(fn (Activity $record): ?string => $record->subject_id ? "#{$record->subject_id}" : null)
                    ->placeholder('—'),
                TextColumn::make('properties.ip')
                    ->label('IP')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('properties.device')
                    ->label('Device')
                    ->limit(40)
                    ->tooltip(fn ($state) => $state)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('event')
                    ->label('Action')
                    ->options(collect(self::EVENTS)->map(fn (array $event): string => $event[0])->all()),
                SelectFilter::make('school')
                    ->label('School')
                    ->options(fn (): array => School::orderBy('name')->pluck('name', 'id')->all())
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $q, $schoolId) => $q->whereHasMorph('causer', [User::class], fn (Builder $u) => $u->where('school_id', $schoolId)),
                    )),
                Filter::make('logged_between')
                    ->schema([
                        DatePicker::make('from')->label('From'),
                        DatePicker::make('until')->label('Until'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
                        ->when($data['until'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '<=', $date))),
            ])
            ->paginationPageOptions([25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading('No activity logged yet');
    }

    /**
     * The user behind an entry; failed sign-ins and system jobs have none.
     */
    protected static function user(Activity $record): ?User
    {
        return $record->causer instanceof User ? $record->causer : null;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListActivityLogs::route('/'),
        ];
    }
}
