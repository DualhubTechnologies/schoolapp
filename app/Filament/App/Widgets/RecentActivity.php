<?php

namespace App\Filament\App\Widgets;

use App\Filament\App\Resources\AuditTrail\AuditTrailResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Spatie\Activitylog\Models\Activity;

/**
 * Super Admin: the latest audit-log entries from every school — sign-ins,
 * failed sign-ins and record changes — newest first, with the school each
 * one came from. The full log is the Audit Trail.
 */
class RecentActivity extends TableWidget
{
    protected static ?int $sort = 10;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Recent activity';

    /** event => [label, colour] */
    protected const EVENTS = [
        'login' => ['Signed in', 'info'],
        'logout' => ['Signed out', 'gray'],
        'login_failed' => ['Failed sign-in', 'danger'],
        'created' => ['Created', 'success'],
        'updated' => ['Updated', 'warning'],
        'deleted' => ['Deleted', 'danger'],
    ];

    public static function canView(): bool
    {
        return auth()->user()?->hasRole('Super Admin') ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Activity::query()->with([
                    'causer' => fn (MorphTo $morphTo) => $morphTo->morphWith([User::class => ['school']]),
                ])
            )
            ->defaultSort(fn (Builder $query) => $query->orderByDesc('created_at')->orderByDesc('id'))
            ->columns([
                TextColumn::make('created_at')
                    ->label('When')
                    ->since()
                    ->dateTimeTooltip('d M Y H:i'),
                TextColumn::make('causer.name')
                    ->label('User')
                    // Failed sign-ins have no user, only the email that was tried.
                    ->state(fn (Activity $record): string => $record->causer?->name
                        ?? data_get($record->properties, 'email')
                        ?? 'System')
                    ->description(fn (Activity $record): ?string => $record->causer?->email),
                TextColumn::make('school')
                    ->label('School')
                    ->state(fn (Activity $record): string => $record->causer?->school?->name
                        ?? ($record->causer?->hasRole('Super Admin') ? 'Platform' : '—')),
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
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('event')
                    ->label('Action')
                    ->options(collect(self::EVENTS)->map(fn (array $event): string => $event[0])->all()),
            ])
            ->headerActions([
                Action::make('viewAll')
                    ->label('Full audit trail')
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    ->link()
                    ->url(fn (): string => AuditTrailResource::getUrl(panel: 'app')),
            ])
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10)
            ->emptyStateHeading('No activity logged yet')
            ->emptyStateIcon('heroicon-o-clipboard-document-list');
    }
}
