<?php

namespace App\Filament\App\Resources\AuditTrail;

use App\Filament\App\Resources\AuditTrail\Pages\ListActivities;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Models\Activity;

class AuditTrailResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Audit Trail';

    protected static ?string $modelLabel = 'Activity';

    protected static ?string $pluralModelLabel = 'Audit Trail';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('When')
                    ->dateTime('d M Y H:i')
                    ->sortable(),

                TextColumn::make('causer.name')
                    ->label('User')
                    ->default('System')
                    ->searchable(),

                TextColumn::make('description')
                    ->label('Action')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'created' => 'success',
                        'updated' => 'warning',
                        'deleted' => 'danger',
                        default => 'gray',
                    }),

                // subject_type is a full class name (App\Models\Student).
                // Show just the model name — the namespace is noise.
                TextColumn::make('subject_type')
                    ->label('Record type')
                    ->formatStateUsing(fn (?string $state): string => $state ? class_basename($state) : '—')
                    ->searchable(),

                TextColumn::make('subject_id')
                    ->label('Record ID')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('event')
                    ->label('Event')
                    ->badge()
                    ->color('gray')
                    ->toggleable(),

                TextColumn::make('properties.ip')
                    ->label('IP')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('properties.device')
                    ->label('Device')
                    ->limit(40)
                    ->tooltip(fn ($state) => $state)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                // Built from what is actually in the log, so it grows by
                // itself as more models get the Auditable trait.
                SelectFilter::make('subject_type')
                    ->label('Record type')
                    ->options(fn (): array => Activity::query()
                        ->whereNotNull('subject_type')
                        ->distinct()
                        ->pluck('subject_type', 'subject_type')
                        ->map(fn ($class) => class_basename($class))
                        ->toArray()),

                SelectFilter::make('event')
                    ->label('Event')
                    ->options(fn (): array => Activity::query()
                        ->whereNotNull('event')
                        ->distinct()
                        ->pluck('event', 'event')
                        ->map(fn ($event) => ucfirst(str_replace('_', ' ', (string) $event)))
                        ->toArray()),

                Filter::make('logged_between')
                    ->schema([
                        DatePicker::make('from')->label('From'),
                        DatePicker::make('until')->label('Until'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
                        ->when($data['until'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '<=', $date))),
            ])
            ->paginationPageOptions([10, 25, 50, 100])
            ->defaultPaginationPageOption(25);
    }

    /**
     * Scope the log to the signed-in user's school. Activity rows don't
     * carry school_id themselves, so this filters by who caused them —
     * a School Admin sees activity from users in their own school only.
     * Super Admin sees the whole system.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $user = auth()->user();

        if ($user && ! $user->hasRole('Super Admin')) {
            $query->whereHasMorph(
                'causer',
                [\App\Models\User::class],
                fn (Builder $q) => $q->where('school_id', $user->school_id)
            );
        }

        return $query;
    }

    // An audit trail nobody can edit is the entire point. These three
    // guards keep it read-only even for Super Admin.
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

    public static function getPages(): array
    {
        return [
            'index' => ListActivities::route('/'),
        ];
    }
}
