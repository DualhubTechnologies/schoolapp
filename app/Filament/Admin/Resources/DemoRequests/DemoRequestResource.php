<?php

namespace App\Filament\Admin\Resources\DemoRequests;

use App\Filament\Admin\Resources\DemoRequests\Pages\ManageDemoRequests;
use App\Models\DemoRequest;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Demo bookings from the landing page, for the platform owner to follow up.
 */
class DemoRequestResource extends Resource
{
    protected static ?string $model = DemoRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|\UnitEnum|null $navigationGroup = 'Platform Management';

    protected static ?string $navigationLabel = 'Demo requests';

    protected static ?int $navigationSort = 3;

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasRole('Super Admin') ?? false;
    }

    public static function canCreate(): bool
    {
        return false;   // they come from the landing page
    }

    public static function getNavigationBadge(): ?string
    {
        $new = DemoRequest::where('status', 'new')->count();

        return $new ? (string) $new : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    /** Only the follow-up is edited; what the school sent stays as sent. */
    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('status')
                ->options(DemoRequest::STATUSES)
                ->required()
                ->native(false),
            Textarea::make('notes')
                ->label('Your notes')
                ->rows(4)
                ->columnSpanFull(),
        ]);
    }

    /** Everything the visitor sent, with the full message. */
    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(['default' => 1, 'sm' => 2])->schema([
                TextEntry::make('school_name')->label('School'),
                TextEntry::make('name')->label('Contact person'),
                TextEntry::make('phone')->copyable(),
                TextEntry::make('email')->placeholder('—')->copyable(),
                TextEntry::make('preferred_contact')
                    ->label('Prefers')
                    ->formatStateUsing(fn (string $state): string => DemoRequest::CONTACT_METHODS[$state] ?? $state),
                TextEntry::make('preferred_date')->label('Preferred date')->date('D j M Y')->placeholder('Any time'),
                TextEntry::make('learners')
                    ->formatStateUsing(fn (?string $state): string => DemoRequest::LEARNERS[$state] ?? (string) $state)
                    ->placeholder('—'),
                TextEntry::make('created_at')->label('Received')->dateTime('j M Y, H:i'),
            ]),
            TextEntry::make('message')->placeholder('No message')->columnSpanFull(),
            TextEntry::make('notes')->label('Your notes')->placeholder('None yet')->columnSpanFull(),
        ]);
    }

    /** One click to move a request on, without opening the follow-up form. */
    protected static function statusAction(string $status, string $label, string|Heroicon $icon, string $color): Action
    {
        return Action::make('mark_'.$status)
            ->label($label)
            ->icon($icon)
            ->color($color)
            ->visible(fn (DemoRequest $record): bool => $record->status !== $status)
            ->action(fn (DemoRequest $record) => $record->update(['status' => $status]));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('school_name')
                    ->label('School')
                    ->weight('bold')
                    ->searchable()
                    ->description(fn (DemoRequest $record) => $record->name),
                TextColumn::make('phone')
                    ->searchable()
                    ->description(fn (DemoRequest $record) => $record->email),
                TextColumn::make('preferred_contact')
                    ->label('Prefers')
                    ->formatStateUsing(fn (string $state) => DemoRequest::CONTACT_METHODS[$state] ?? $state)
                    ->description(fn (DemoRequest $record): string => $record->preferred_date?->format('D j M') ?? 'Any time'),
                TextColumn::make('learners')
                    ->formatStateUsing(fn (?string $state) => DemoRequest::LEARNERS[$state] ?? $state)
                    ->placeholder('—'),
                TextColumn::make('message')
                    ->limit(70)
                    ->wrap()
                    ->width('18rem')
                    ->tooltip(fn (DemoRequest $record) => $record->message)
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => DemoRequest::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'new' => 'warning',
                        'contacted' => 'info',
                        'booked' => 'success',
                        'spam' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('created_at')
                    ->label('Received')
                    ->since()
                    ->dateTimeTooltip('j M Y, H:i')
                    ->sortable(),
            ])
            ->recordAction('view')
            ->filters([
                SelectFilter::make('status')->options(DemoRequest::STATUSES),
                // Sales pitches are filed as spam; they stay out of the way
                // unless asked for.
                TernaryFilter::make('spam')
                    ->label('Spam')
                    ->placeholder('Show all')
                    ->trueLabel('Spam only')
                    ->falseLabel('Hide spam')
                    ->queries(
                        true: fn (Builder $query) => $query->where('status', 'spam'),
                        false: fn (Builder $query) => $query->where('status', '!=', 'spam'),
                    )
                    ->default(false),
            ])
            ->recordActions([
                ViewAction::make()->label('Open'),
                Action::make('whatsapp')
                    ->label('WhatsApp')
                    ->icon(Heroicon::OutlinedChatBubbleLeftRight)
                    ->color('success')
                    ->url(fn (DemoRequest $record) => $record->whatsappUrl(), shouldOpenInNewTab: true)
                    ->hidden(fn (DemoRequest $record): bool => $record->status === 'spam'),
                ActionGroup::make([
                    static::statusAction('contacted', 'Mark contacted', Heroicon::OutlinedPhone, 'info'),
                    static::statusAction('booked', 'Mark demo booked', Heroicon::OutlinedCalendarDays, 'success'),
                    static::statusAction('closed', 'Close', Heroicon::OutlinedCheckCircle, 'gray'),
                    static::statusAction('spam', 'Mark as spam', Heroicon::OutlinedNoSymbol, 'danger'),
                    static::statusAction('new', 'Not spam', Heroicon::OutlinedArrowUturnLeft, 'gray')
                        ->visible(fn (DemoRequest $record): bool => $record->status === 'spam'),
                    EditAction::make()->label('Add notes'),
                    DeleteAction::make(),
                ]),
            ])
            ->emptyStateHeading('No demo requests')
            ->emptyStateDescription('Requests from the "Book a demo" form on the website appear here.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageDemoRequests::route('/'),
        ];
    }
}
