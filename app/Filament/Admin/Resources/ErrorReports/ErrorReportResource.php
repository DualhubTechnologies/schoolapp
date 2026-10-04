<?php

namespace App\Filament\Admin\Resources\ErrorReports;

use App\Filament\Admin\Resources\ErrorReports\Pages\ListErrorReports;
use App\Filament\Admin\Resources\ErrorReports\Pages\ViewErrorReport;
use App\Models\ErrorOccurrence;
use App\Models\ErrorReport;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Js;
use Illuminate\Support\Str;

/**
 * The platform owner's list of errors across every school: one row per
 * distinct error, with how often it has happened and where. Search by the
 * reference a user quotes (e.g. "E-7K3Q9P") to find exactly what they hit.
 * Recorded by App\Support\ErrorRecorder.
 */
class ErrorReportResource extends Resource
{
    protected static ?string $model = ErrorReport::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static string|\UnitEnum|null $navigationGroup = 'Platform Management';

    protected static ?string $navigationLabel = 'Error reports';

    protected static ?string $modelLabel = 'Error report';

    protected static ?string $slug = 'error-reports';

    protected static ?int $navigationSort = 7;

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

    /** Open errors, shown on the menu item. */
    public static function getNavigationBadge(): ?string
    {
        $open = static::getModel()::whereNull('resolved_at')->count();

        return $open > 0 ? (string) $open : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('last_seen_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('lastSchool'))
            ->columns([
                TextColumn::make('status')
                    ->label('')
                    ->badge()
                    ->state(fn (ErrorReport $record): string => $record->isResolved() ? 'Resolved' : 'Open')
                    ->color(fn (string $state): string => $state === 'Open' ? 'danger' : 'success'),
                TextColumn::make('message')
                    ->label('Error')
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->where(fn (Builder $q) => $q
                        ->where('message', 'like', "%{$search}%")
                        ->orWhere('exception_class', 'like', "%{$search}%")
                        ->orWhereHas('occurrenceLog', fn (Builder $o) => $o->where('reference', strtoupper(trim($search))))))
                    ->limit(90)
                    ->tooltip(fn (ErrorReport $record): string => $record->message)
                    ->description(fn (ErrorReport $record): string => class_basename($record->exception_class).' · '.$record->shortFile().':'.$record->line)
                    ->wrap(),
                TextColumn::make('occurrences')
                    ->label('Times')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('last_seen_at')
                    ->label('Last seen')
                    ->since()
                    ->dateTimeTooltip('d M Y H:i')
                    ->sortable(),
                TextColumn::make('lastSchool.name')
                    ->label('Last school')
                    ->placeholder('—')
                    ->limit(30),
                TextColumn::make('first_seen_at')
                    ->label('First seen')
                    ->date('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('open')
                    ->label('Status')
                    ->placeholder('All')
                    ->trueLabel('Open')
                    ->falseLabel('Resolved')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNull('resolved_at'),
                        false: fn (Builder $query) => $query->whereNotNull('resolved_at'),
                    )
                    ->default(true),
            ])
            ->recordActions([
                ViewAction::make(),
                static::copyAction(),
                static::resolveAction(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('resolve')
                        ->label('Mark resolved')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->deselectRecordsAfterCompletion()
                        ->action(fn (Collection $records) => ErrorReport::whereKey($records->pluck('id')->all())->update(['resolved_at' => now()])),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No errors')
            ->emptyStateDescription('Unexpected errors from any school appear here, with the reference the user was shown.')
            ->emptyStateIcon('heroicon-o-check-badge');
    }

    /**
     * Copy the whole error (message, where, page, stack trace) to the
     * clipboard in the browser, with no round trip to the server.
     */
    public static function copyAction(): Action
    {
        return Action::make('copy')
            ->label('Copy error')
            ->icon('heroicon-o-clipboard-document')
            ->color('gray')
            ->alpineClickHandler(fn (ErrorReport $record): string => 'window.navigator.clipboard.writeText('.Js::from($record->copyText()).')'
                .'.then(() => $tooltip('.Js::from('Copied').', { theme: $store.theme, timeout: 2000 }))');
    }

    /** Mark an open error resolved, or reopen a resolved one. */
    public static function resolveAction(): Action
    {
        return Action::make('resolve')
            ->label(fn (ErrorReport $record): string => $record->isResolved() ? 'Reopen' : 'Mark resolved')
            ->icon(fn (ErrorReport $record): string => $record->isResolved() ? 'heroicon-o-arrow-uturn-left' : 'heroicon-o-check-circle')
            ->color(fn (ErrorReport $record): string => $record->isResolved() ? 'gray' : 'success')
            ->action(fn (ErrorReport $record) => $record->update(['resolved_at' => $record->isResolved() ? null : now()]));
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->schema([
                    TextEntry::make('message')
                        ->label(fn (ErrorReport $record): string => class_basename($record->exception_class))
                        ->columnSpanFull(),
                    Grid::make(['default' => 2, 'md' => 4])->schema([
                        TextEntry::make('status')
                            ->badge()
                            ->state(fn (ErrorReport $record): string => $record->isResolved() ? 'Resolved '.$record->resolved_at?->format('d M Y') : 'Open')
                            ->color(fn (ErrorReport $record): string => $record->isResolved() ? 'success' : 'danger'),
                        TextEntry::make('occurrences')->label('Times it happened'),
                        TextEntry::make('first_seen_at')->label('First seen')->dateTime('d M Y H:i'),
                        TextEntry::make('last_seen_at')->label('Last seen')->dateTime('d M Y H:i'),
                    ]),
                    TextEntry::make('where')
                        ->label('Where')
                        ->state(fn (ErrorReport $record): string => $record->shortFile().':'.$record->line)
                        ->fontFamily('mono')
                        ->copyable(),
                    TextEntry::make('exception_class')->label('Exception')->fontFamily('mono'),
                    TextEntry::make('last_url')->label('Last page')->placeholder('—')->columnSpanFull(),
                ])
                ->columns(2),

            Section::make('Latest times it happened')
                ->description('Kept for 60 days. The reference is what the user was shown.')
                ->schema([
                    RepeatableEntry::make('recentOccurrences')
                        ->hiddenLabel()
                        ->state(fn (ErrorReport $record): array => $record->occurrenceLog()->with(['school', 'user'])->limit(20)->get()
                            ->map(fn (ErrorOccurrence $o): array => [
                                'reference' => $o->reference,
                                'when' => $o->created_at?->format('d M Y H:i'),
                                'who' => collect([$o->user?->name, $o->school?->name])->filter()->implode(' · ') ?: 'Not signed in',
                                'page' => Str::limit(trim(($o->method ?? '').' '.($o->url ?? '')), 120),
                            ])->all())
                        ->schema([
                            TextEntry::make('reference')->label('Reference')->fontFamily('mono')->copyable(),
                            TextEntry::make('when')->label('When'),
                            TextEntry::make('who')->label('Who'),
                            TextEntry::make('page')->label('Page'),
                        ])
                        ->columns(4),
                ]),

            Section::make('Stack trace')
                ->collapsible()
                ->collapsed()
                ->schema([
                    TextEntry::make('trace')
                        ->hiddenLabel()
                        ->fontFamily('mono')
                        ->size('xs')
                        ->formatStateUsing(fn (?string $state): string => nl2br(e(str_replace(base_path().'/', '', (string) $state))))
                        ->html(),
                ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListErrorReports::route('/'),
            'view' => ViewErrorReport::route('/{record}'),
        ];
    }
}
