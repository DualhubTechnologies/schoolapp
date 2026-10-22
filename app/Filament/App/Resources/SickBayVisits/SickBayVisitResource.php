<?php

namespace App\Filament\App\Resources\SickBayVisits;

use App\Filament\App\Resources\SickBayVisits\Pages\ManageSickBayVisits;
use App\Filament\Concerns\GatedByModule;
use App\Filament\Concerns\PlacedInMenu;
use App\Filament\Support\PeopleSearch;
use App\Models\SchoolClass;
use App\Models\SickBayMedicine;
use App\Models\SickBayVisit;
use App\Models\Student;
use App\Services\ParentMessages;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * The sick bay register, as the school nurse keeps it: who came and when,
 * the complaint and temperature, what was found and given, and what
 * happened next. Parents are texted when a learner is sent home or
 * referred (or whenever the nurse chooses). Medicines given can be taken
 * from the sick bay's stock, if the school keeps one.
 */
class SickBayVisitResource extends Resource
{
    use GatedByModule;
    use PlacedInMenu;

    protected static ?string $model = SickBayVisit::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHeart;

    protected static ?string $modelLabel = 'sick bay visit';

    protected static ?string $slug = 'sick-bay';

    public static function getNavigationBadge(): ?string
    {
        $resting = SickBayVisit::inSickBay()->where('school_id', auth()->user()?->school_id)->count();

        return $resting ? (string) $resting : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Resting in the sick bay now';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('school_id', auth()->user()?->school_id)
            ->with(['student.schoolClass', 'staff', 'dispensed.medicine']);
    }

    public static function form(Schema $schema): Schema
    {
        $keepsStock = fn (): bool => SickBayMedicine::where('school_id', auth()->user()?->school_id)->where('is_active', true)->exists();

        return $schema->columns(2)->components([
            ToggleButtons::make('who')
                ->label('Patient')
                ->options(['learner' => 'A learner', 'staff' => 'A member of staff'])
                ->default('learner')
                ->inline()
                ->live()
                ->dehydrated(false)
                ->afterStateHydrated(fn (ToggleButtons $component, ?SickBayVisit $record) => $component->state($record?->staff_id ? 'staff' : 'learner'))
                ->columnSpanFull(),
            Select::make('student_id')
                ->label('Learner')
                ->searchable()
                ->getSearchResultsUsing(fn (string $search): array => PeopleSearch::learners($search))
                ->getOptionLabelUsing(fn ($value): ?string => PeopleSearch::learnerLabel($value))
                ->live()
                ->visible(fn (Get $get): bool => $get('who') !== 'staff')
                ->required(fn (Get $get): bool => $get('who') !== 'staff')
                ->columnSpanFull(),
            TextEntry::make('medical')
                ->label('Medical notes on record')
                ->state(fn (Get $get): ?string => filled($get('student_id')) ? (Student::whereKey($get('student_id'))->value('medical_notes') ?: 'None recorded') : null)
                ->color(fn (Get $get): string => filled($get('student_id')) && filled(Student::whereKey($get('student_id'))->value('medical_notes')) ? 'danger' : 'gray')
                ->visible(fn (Get $get): bool => $get('who') !== 'staff' && filled($get('student_id')))
                ->columnSpanFull(),
            Select::make('staff_id')
                ->label('Staff member')
                ->searchable()
                ->getSearchResultsUsing(fn (string $search): array => PeopleSearch::staff($search))
                ->getOptionLabelUsing(fn ($value): ?string => PeopleSearch::staffLabel($value))
                ->visible(fn (Get $get): bool => $get('who') === 'staff')
                ->required(fn (Get $get): bool => $get('who') === 'staff')
                ->columnSpanFull(),
            DateTimePicker::make('visited_at')->label('Came in at')->default(now())->seconds(false)->maxDate(now()->addMinutes(5))->required()->native(false),
            TextInput::make('temperature')->label('Temperature (°C)')->numeric()->minValue(30)->maxValue(45)->step(0.1)->placeholder('e.g. 37.8'),
            TextInput::make('complaint')
                ->label('Complaint')
                ->datalist(SickBayVisit::COMPLAINTS)
                ->required()
                ->maxLength(255)
                ->placeholder('e.g. Headache, fever')
                ->columnSpanFull(),
            TextInput::make('diagnosis')->label('What the nurse found')->maxLength(255)->placeholder('Optional, e.g. Suspected malaria')->columnSpanFull(),
            Textarea::make('treatment')->label('Treatment given')->rows(2)->placeholder('What was done or given, e.g. Paracetamol 2 tabs, rested 1 hour')->columnSpanFull(),
            Section::make('Medicines from the sick bay\'s stock')
                ->description('Taken out of stock automatically.')
                ->compact()
                ->collapsible()
                ->visible($keepsStock)
                ->columnSpanFull()
                ->schema([
                    Repeater::make('dispensed')
                        ->hiddenLabel()
                        ->relationship()
                        ->table([
                            Repeater\TableColumn::make('Medicine'),
                            Repeater\TableColumn::make('Quantity')->width('7rem'),
                            Repeater\TableColumn::make('Dosage'),
                        ])
                        ->schema([
                            Select::make('sick_bay_medicine_id')
                                ->options(fn (): array => SickBayMedicine::where('school_id', auth()->user()?->school_id)->where('is_active', true)->orderBy('name')->get()
                                    ->mapWithKeys(fn (SickBayMedicine $m): array => [$m->id => "{$m->name} ({$m->stock} {$m->unit} left)"])->all())
                                ->required()
                                ->native(false),
                            TextInput::make('quantity')->numeric()->minValue(1)->default(1)->required(),
                            TextInput::make('dosage')->placeholder('e.g. 2 tabs, 3 times a day')->maxLength(255),
                        ])
                        ->defaultItems(0)
                        ->addActionLabel('Add a medicine'),
                ]),
            ToggleButtons::make('outcome')
                ->label('What happened next')
                ->options(SickBayVisit::OUTCOMES)
                ->colors(['returned_to_class' => 'success', 'resting' => 'info', 'sent_home' => 'warning', 'referred' => 'danger'])
                ->default('returned_to_class')
                ->inline()
                ->required()
                ->live()
                ->afterStateUpdated(fn (?string $state, Set $set) => $set('notify_parent', in_array($state, SickBayVisit::SERIOUS, true)))
                ->columnSpanFull(),
            TextInput::make('referred_to')
                ->label('Referred to')
                ->placeholder('e.g. Wakiso Health Centre IV')
                ->maxLength(255)
                ->visible(fn (Get $get): bool => $get('outcome') === 'referred')
                ->columnSpanFull(),
            Toggle::make('notify_parent')
                ->label('Text the parent now')
                ->helperText('Sends an SMS to the parent\'s phone. On by default when a learner is sent home or referred.')
                ->default(false)
                ->visible(fn (Get $get, ?SickBayVisit $record): bool => $get('who') !== 'staff' && ! $record?->parent_notified_at)
                ->columnSpanFull(),
            Textarea::make('notes')->rows(2)->placeholder('Anything else to note')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('visited_at', 'desc')
            ->columns([
                TextColumn::make('visited_at')->label('When')->dateTime('j M, H:i')->sortable(),
                TextColumn::make('patient')
                    ->label('Patient')
                    ->state(fn (SickBayVisit $record): string => $record->patientName())
                    ->weight('semibold')
                    ->searchable(query: fn (Builder $query, string $search) => $query->where(fn (Builder $q) => $q
                        ->whereHas('student', fn (Builder $s) => $s->where('name', 'like', "%{$search}%")->orWhere('admission_no', 'like', "%{$search}%"))
                        ->orWhereHas('staff', fn (Builder $s) => $s->where('name', 'like', "%{$search}%")))),
                TextColumn::make('complaint')
                    ->description(fn (SickBayVisit $record): ?string => $record->diagnosis ? Str::limit($record->diagnosis, 40) : null)
                    ->tooltip(fn (SickBayVisit $record): ?string => strlen((string) $record->diagnosis) > 40 ? $record->diagnosis : null)
                    ->searchable()
                    ->extraCellAttributes(['style' => 'min-width: 9rem'])
                    ->wrap(),
                TextColumn::make('temperature')
                    ->label('Temp.')
                    ->formatStateUsing(fn ($state): string => $state.' °C')
                    ->color(fn (SickBayVisit $record): ?string => $record->hasFever() ? 'danger' : null)
                    ->placeholder('-'),
                // Medicines from stock one per line, the nurse's note in
                // short underneath; the full note on hover.
                TextColumn::make('treatment')
                    ->label('Given')
                    ->state(fn (SickBayVisit $record): array => $record->dispensed->isNotEmpty()
                        ? $record->dispensed->map(fn ($dispense): string => $dispense->medicine?->name.' × '.$dispense->quantity)->all()
                        : array_filter([Str::limit((string) $record->treatment, 40)]))
                    ->listWithLineBreaks()
                    ->description(fn (SickBayVisit $record): ?string => $record->dispensed->isNotEmpty() && filled($record->treatment)
                        ? Str::limit((string) $record->treatment, 40)
                        : null)
                    ->tooltip(fn (SickBayVisit $record): ?string => strlen((string) $record->treatment) > 40 ? $record->treatment : null)
                    ->placeholder('-')
                    ->extraCellAttributes(['style' => 'min-width: 12rem'])
                    ->wrap()
                    ->toggleable(),
                TextColumn::make('outcome')
                    ->label('Outcome')
                    ->formatStateUsing(fn (string $state, SickBayVisit $record): string => $state === 'resting' && $record->discharged_at
                        ? 'Rested, left '.$record->discharged_at->format('H:i')
                        : (SickBayVisit::OUTCOMES[$state] ?? $state))
                    ->badge()
                    ->description(fn (SickBayVisit $record): ?string => $record->parent_notified_at ? 'Parent texted '.$record->parent_notified_at->format($record->parent_notified_at->isSameDay($record->visited_at) ? 'H:i' : 'j M, H:i') : null)
                    ->color(fn (string $state, SickBayVisit $record): string => match (true) {
                        $state === 'resting' && ! $record->discharged_at => 'info',
                        $state === 'sent_home' => 'warning',
                        $state === 'referred' => 'danger',
                        default => 'success',
                    }),
            ])
            ->filters([
                Filter::make('in_sick_bay')
                    ->label('Resting in the sick bay now')
                    ->query(fn (Builder $query) => $query->where('outcome', 'resting')->whereNull('discharged_at')),
                SelectFilter::make('period')
                    ->label('When')
                    ->options(['today' => 'Today', 'week' => 'This week', 'month' => 'This month'])
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        'today' => $query->whereDate('visited_at', today()),
                        'week' => $query->where('visited_at', '>=', now()->startOfWeek()),
                        'month' => $query->where('visited_at', '>=', now()->startOfMonth()),
                        default => $query,
                    }),
                SelectFilter::make('outcome')->options(SickBayVisit::OUTCOMES),
                SelectFilter::make('class')
                    ->label('Class')
                    ->options(fn (): array => SchoolClass::where('school_id', auth()->user()?->school_id)->orderBy('id')->pluck('name', 'id')->all())
                    ->query(fn (Builder $query, array $data) => filled($data['value'] ?? null)
                        ? $query->whereHas('student', fn (Builder $s) => $s->where('school_class_id', $data['value']))
                        : $query),
            ])
            ->recordActions([
                Action::make('discharge')
                    ->label('Discharge')
                    ->tooltip('The patient has left the sick bay')
                    ->modalHeading(fn (SickBayVisit $record): string => $record->patientName().' has left the sick bay')
                    ->icon('heroicon-o-arrow-right-start-on-rectangle')
                    ->color('success')
                    ->visible(fn (SickBayVisit $record): bool => $record->outcome === 'resting' && ! $record->discharged_at)
                    ->modalWidth('md')
                    ->schema([
                        ToggleButtons::make('outcome')
                            ->label('Where to')
                            ->options(['returned_to_class' => 'Back to class or dormitory', 'sent_home' => 'Sent home', 'referred' => 'Referred to hospital / clinic'])
                            ->default('returned_to_class')
                            ->live()
                            ->required(),
                        TextInput::make('referred_to')->label('Referred to')->visible(fn (Get $get): bool => $get('outcome') === 'referred'),
                        Toggle::make('notify_parent')
                            ->label('Text the parent now')
                            ->default(false)
                            ->visible(fn (Get $get, SickBayVisit $record): bool => $record->student_id !== null && in_array($get('outcome'), SickBayVisit::SERIOUS, true)),
                    ])
                    ->action(function (SickBayVisit $record, array $data, ParentMessages $messages): void {
                        $record->update([
                            'discharged_at' => now(),
                            'outcome' => $data['outcome'],
                            'referred_to' => $data['referred_to'] ?? $record->referred_to,
                        ]);

                        $problem = ($data['notify_parent'] ?? false) ? $messages->sendSickBayVisit($record) : null;

                        Notification::make()
                            ->title($record->patientName().' has left the sick bay')
                            ->body($problem)
                            ->status($problem ? 'warning' : 'success')
                            ->send();
                    }),
                ActionGroup::make([
                    Action::make('textParent')
                        ->label('Text the parent')
                        ->icon('heroicon-o-chat-bubble-left-ellipsis')
                        ->visible(fn (SickBayVisit $record): bool => $record->student_id !== null)
                        ->requiresConfirmation()
                        ->modalHeading('Text the parent')
                        ->modalDescription(fn (SickBayVisit $record, ParentMessages $messages): string => $messages->sickBayVisit($record))
                        ->action(function (SickBayVisit $record, ParentMessages $messages): void {
                            $problem = $messages->sendSickBayVisit($record);

                            Notification::make()->title($problem ? 'Not sent' : 'Parent texted')->body($problem)->status($problem ? 'danger' : 'success')->send();
                        }),
                    EditAction::make()
                        ->modalWidth('2xl')
                        ->mutateDataUsing(function (array $data): array {
                            static::$textParent = (bool) ($data['notify_parent'] ?? false);
                            unset($data['notify_parent']);

                            return $data;
                        })
                        ->after(fn (SickBayVisit $record) => static::textParentIfAsked($record)),
                    DeleteAction::make()->modalDescription('Any medicine taken from stock for this visit is put back.'),
                ]),
            ])
            ->emptyStateHeading('No sick bay visits yet')
            ->emptyStateDescription('Record each learner or member of staff who comes to the sick bay with "New visit".');
    }

    /** Whether the form just saved asked to text the parent (it is not a column). */
    public static bool $textParent = false;

    /** After a visit is saved: text the parent if the nurse asked to. */
    public static function textParentIfAsked(SickBayVisit $visit): void
    {
        if (! static::$textParent || ! $visit->student_id || $visit->parent_notified_at) {
            return;
        }

        static::$textParent = false;
        $problem = app(ParentMessages::class)->sendSickBayVisit($visit);

        Notification::make()
            ->title($problem ? 'The parent was not texted' : 'Parent texted')
            ->body($problem)
            ->status($problem ? 'warning' : 'success')
            ->send();
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSickBayVisits::route('/'),
        ];
    }
}
