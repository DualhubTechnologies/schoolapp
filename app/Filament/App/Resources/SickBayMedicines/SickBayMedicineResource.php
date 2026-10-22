<?php

namespace App\Filament\App\Resources\SickBayMedicines;

use App\Filament\App\Resources\SickBayMedicines\Pages\ManageSickBayMedicines;
use App\Filament\Concerns\GatedByModule;
use App\Filament\Concerns\PlacedInMenu;
use App\Models\SickBayMedicine;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;

/**
 * The sick bay's medicines and how much of each is left. Optional: a
 * school that adds none here records treatment in words only. Stock goes
 * down as the nurse gives medicine at a visit, and up with "Receive stock".
 */
class SickBayMedicineResource extends Resource
{
    use GatedByModule;
    use PlacedInMenu;

    protected static ?string $model = SickBayMedicine::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBeaker;

    protected static ?string $modelLabel = 'medicine';

    protected static ?string $slug = 'sick-bay/medicines';

    public static function getNavigationBadge(): ?string
    {
        $low = SickBayMedicine::where('school_id', auth()->user()?->school_id)
            ->where('is_active', true)
            ->whereNotNull('reorder_level')
            ->whereColumn('stock', '<=', 'reorder_level')
            ->count();

        return $low ? (string) $low : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Running low';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('school_id', auth()->user()?->school_id);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')
                ->required()
                ->maxLength(255)
                ->placeholder('e.g. Paracetamol 500mg')
                ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule) => $rule->where('school_id', auth()->user()?->school_id))
                ->validationMessages(['unique' => 'This medicine is already on the list.'])
                ->columnSpanFull(),
            TextInput::make('unit')->default('tablets')->required()->maxLength(30)->placeholder('tablets, bottles, sachets…'),
            TextInput::make('stock')->label('In stock now')->numeric()->minValue(0)->default(0)->required()->visibleOn('create'),
            TextInput::make('reorder_level')
                ->label('Warn when down to')
                ->numeric()
                ->minValue(0)
                ->placeholder('e.g. 50')
                ->helperText('The menu shows a warning when stock reaches this.'),
            Toggle::make('is_active')->label('In use')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->weight('semibold')->searchable()->sortable(),
                TextColumn::make('stock')
                    ->label('In stock')
                    ->formatStateUsing(fn (int $state, SickBayMedicine $record): string => number_format($state).' '.$record->unit)
                    ->badge()
                    ->color(fn (SickBayMedicine $record): string => $record->stock <= 0 ? 'danger' : ($record->isLow() ? 'warning' : 'success'))
                    ->sortable(),
                TextColumn::make('reorder_level')->label('Warn at')->placeholder('-'),
                IconColumn::make('is_active')->label('In use')->boolean(),
            ])
            ->recordActions([
                Action::make('receive')
                    ->label('Receive stock')
                    ->icon('heroicon-o-plus-circle')
                    ->color('success')
                    ->modalWidth('sm')
                    ->schema([
                        TextInput::make('quantity')->label(fn (SickBayMedicine $record): string => 'How many '.$record->unit.'?')->numeric()->minValue(1)->required(),
                    ])
                    ->action(function (SickBayMedicine $record, array $data): void {
                        $record->increment('stock', (int) $data['quantity']);

                        Notification::make()->title($record->name.': '.number_format($record->stock).' '.$record->unit.' in stock')->success()->send();
                    }),
                Action::make('count')
                    ->label('Correct the count')
                    ->icon('heroicon-o-pencil-square')
                    ->color('gray')
                    ->modalDescription('After counting what is on the shelf, enter the true number.')
                    ->modalWidth('sm')
                    ->schema([
                        TextInput::make('stock')->label('Counted')->numeric()->minValue(0)->default(fn (SickBayMedicine $record): int => $record->stock)->required(),
                    ])
                    ->action(fn (SickBayMedicine $record, array $data) => $record->update(['stock' => (int) $data['stock']])),
                EditAction::make(),
                DeleteAction::make()
                    ->visible(fn (SickBayMedicine $record): bool => ! $record->dispensed()->exists())
                    ->modalDescription('Medicines already given at visits cannot be deleted; switch them off ("In use") instead.'),
            ])
            ->emptyStateHeading('Not keeping medicine stock?')
            ->emptyStateDescription('That is fine: the nurse can write what was given in words. To have stock counted down as medicine is given, add each medicine here with how much you have.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSickBayMedicines::route('/'),
        ];
    }
}
