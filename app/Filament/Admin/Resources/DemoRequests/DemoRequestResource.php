<?php

namespace App\Filament\Admin\Resources\DemoRequests;

use App\Filament\Admin\Resources\DemoRequests\Pages\ManageDemoRequests;
use App\Models\DemoRequest;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

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
                    ->formatStateUsing(fn (string $state) => DemoRequest::CONTACT_METHODS[$state] ?? $state),
                TextColumn::make('preferred_date')
                    ->label('Preferred date')
                    ->date('D j M Y')
                    ->placeholder('Any time'),
                TextColumn::make('learners')
                    ->formatStateUsing(fn (?string $state) => DemoRequest::LEARNERS[$state] ?? $state)
                    ->placeholder('—'),
                TextColumn::make('message')
                    ->limit(60)
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
                        default => 'gray',
                    }),
                TextColumn::make('created_at')
                    ->label('Received')
                    ->since()
                    ->dateTimeTooltip('j M Y, H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(DemoRequest::STATUSES),
            ])
            ->recordActions([
                Action::make('whatsapp')
                    ->label('WhatsApp')
                    ->icon(Heroicon::OutlinedChatBubbleLeftRight)
                    ->color('success')
                    ->url(fn (DemoRequest $record) => $record->whatsappUrl(), shouldOpenInNewTab: true),
                EditAction::make()->label('Follow up'),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageDemoRequests::route('/'),
        ];
    }
}
