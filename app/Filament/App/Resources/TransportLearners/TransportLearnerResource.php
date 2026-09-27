<?php

namespace App\Filament\App\Resources\TransportLearners;

use App\Filament\App\Resources\TransportLearners\Pages\ManageTransportLearners;
use App\Filament\Concerns\GatedByModule;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\TransportRoute;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * The Transport module's list of learners on the school van: who rides
 * which route, one way or both. Learners are added, moved and taken off
 * here; the student record itself stays about the learner.
 */
class TransportLearnerResource extends Resource
{
    use GatedByModule;

    protected static ?string $model = Student::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|\UnitEnum|null $navigationGroup = 'Transport';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Learners on the van';

    protected static ?string $modelLabel = 'learner';

    protected static ?string $pluralModelLabel = 'learners on the van';

    protected static ?string $slug = 'transport-learners';

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
        return parent::getEloquentQuery()
            ->where('school_id', auth()->user()?->school_id)
            ->where('status', 'active')
            ->whereNotNull('transport_route_id')
            ->with(['schoolClass', 'section', 'transportRoute', 'guardian']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Learner')
                    ->weight('semibold')
                    ->description(fn (Student $record): string => $record->admission_no)
                    ->searchable(['name', 'admission_no'])
                    ->sortable(),
                TextColumn::make('schoolClass.name')
                    ->label('Class')
                    ->badge()
                    ->formatStateUsing(fn (?string $state, Student $record): string => $record->section
                        ? "{$state} · {$record->section->name}"
                        : (string) $state),
                TextColumn::make('transportRoute.name')
                    ->label('Route')
                    ->weight('semibold')
                    ->sortable(),
                TextColumn::make('transport_trip')
                    ->label('Uses the van')
                    ->formatStateUsing(fn (?string $state): string => TransportRoute::TRIPS[$state] ?? '—'),
                TextColumn::make('fare')
                    ->label('Fare per term (UGX)')
                    ->state(fn (Student $record): ?float => $record->transportRoute?->fareFor($record->transport_trip))
                    ->numeric(),
                TextColumn::make('guardian.name')
                    ->label('Parent / guardian')
                    ->placeholder('Not linked')
                    ->description(fn (Student $record): ?string => $record->guardian?->phone),
            ])
            ->filters([
                SelectFilter::make('transport_route_id')
                    ->label('Route')
                    ->options(fn (): array => static::routeOptions()),
                SelectFilter::make('school_class_id')
                    ->label('Class')
                    ->options(fn (): array => SchoolClass::where('school_id', auth()->user()?->school_id)->orderBy('level')->pluck('name', 'id')->all()),
            ])
            ->recordActions([
                Action::make('changeRoute')
                    ->label('Move')
                    ->modalHeading('Move to another route')
                    ->modalSubmitActionLabel('Move')
                    ->icon('heroicon-o-arrows-right-left')
                    ->fillForm(fn (Student $record): array => [
                        'transport_route_id' => $record->transport_route_id,
                        'transport_trip' => $record->transport_trip,
                    ])
                    ->schema(static::routeFields())
                    ->action(fn (Student $record, array $data) => $record->update($data)),
                Action::make('takeOff')
                    ->label('Remove')
                    ->modalHeading('Take off the van')
                    ->modalSubmitActionLabel('Take off the van')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('From next term\'s billing, this learner is brought by a parent and pays nothing for transport. Transport already billed stays on their account.')
                    ->action(fn (Student $record) => $record->update(['transport_route_id' => null])),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('moveRoute')
                        ->label('Move to another route')
                        ->icon('heroicon-o-arrows-right-left')
                        ->schema(static::routeFields())
                        ->action(function (Collection $records, array $data): void {
                            static::updateEach($records, $data);

                            Notification::make()->title($records->count().' learners moved')->success()->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('takeOffVan')
                        ->label('Take off the van')
                        ->icon('heroicon-o-x-mark')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            static::updateEach($records, ['transport_route_id' => null]);

                            Notification::make()->title($records->count().' learners taken off the van')->success()->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->emptyStateHeading('No learners on the van yet')
            ->emptyStateDescription('Press "Add to route" to put learners on a van route.')
            ->emptyStateIcon('heroicon-o-truck');
    }

    /**
     * Route and trip, shared by adding, moving and changing a learner.
     *
     * @return list<Select>
     */
    public static function routeFields(): array
    {
        return [
            Select::make('transport_route_id')
                ->label('Route')
                ->options(fn (): array => static::routeOptions())
                ->required(),
            Select::make('transport_trip')
                ->label('Uses the van')
                ->options(TransportRoute::TRIPS)
                ->default('both')
                ->selectablePlaceholder(false)
                ->required(),
        ];
    }

    /**
     * "Aisha Nakato — P.4 (ADM-001)", for picking learners.
     */
    public static function learnerLabel(Student $student): string
    {
        return $student->name.' — '.($student->schoolClass->name ?? 'no class').' ('.$student->admission_no.')';
    }

    /**
     * The school's routes in use, as options with their fares.
     *
     * @return array<int, string>
     */
    protected static function routeOptions(): array
    {
        return TransportRoute::where('school_id', auth()->user()?->school_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (TransportRoute $route): array => [$route->id => $route->name.' — UGX '.number_format((float) $route->fare)])
            ->all();
    }

    /**
     * Save each learner (not a mass update), so the audit trail records it.
     *
     * @param  Collection<int, Model>  $records
     * @param  array<string, mixed>  $data
     */
    protected static function updateEach(Collection $records, array $data): void
    {
        foreach ($records as $student) {
            if ($student instanceof Student && $student->school_id === auth()->user()?->school_id) {
                $student->update($data);
            }
        }
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageTransportLearners::route('/'),
        ];
    }
}
