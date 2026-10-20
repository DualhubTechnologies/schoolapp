<?php

namespace App\Filament\App\Resources\LibraryLoans;

use App\Filament\App\Resources\LibraryLoans\Pages\ManageLibraryLoans;
use App\Filament\Concerns\GatedByModule;
use App\Filament\Concerns\PlacedInMenu;
use App\Filament\Support\MoneyInput;
use App\Models\LibraryCopy;
use App\Models\LibraryLoan;
use App\Models\SchoolClass;
use App\Services\Library\Lending;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * Lending: every book out with a learner or member of staff, overdue ones
 * in red. A book comes back with "Returned", or is declared lost, which
 * bills its price to the learner's fees.
 */
class LibraryLoanResource extends Resource
{
    use GatedByModule;
    use PlacedInMenu;

    protected static ?string $model = LibraryLoan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static ?string $modelLabel = 'issued book';

    protected static ?string $pluralModelLabel = 'Issued books';

    protected static ?string $slug = 'library/lending';

    public static function getNavigationBadge(): ?string
    {
        $overdue = LibraryLoan::outstanding()
            ->where('school_id', auth()->user()?->school_id)
            ->whereDate('due_on', '<', today())
            ->count();

        return $overdue ? (string) $overdue : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Books overdue';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('school_id', auth()->user()?->school_id)
            ->with(['copy.book', 'student.schoolClass', 'staff', 'charge']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('issued_on', 'desc')
            ->columns([
                TextColumn::make('copy.book.title')
                    ->label('Book')
                    ->weight('semibold')
                    ->description(fn (LibraryLoan $record): string => $record->copy->accession_no)
                    ->searchable(query: fn (Builder $query, string $search) => $query->whereHas('copy', fn (Builder $c) => $c
                        ->where('accession_no', 'like', "%{$search}%")
                        ->orWhereHas('book', fn (Builder $b) => $b->where('title', 'like', "%{$search}%"))))
                    ->wrap(),
                TextColumn::make('borrower')
                    ->label('Issued to')
                    ->state(fn (LibraryLoan $record): string => $record->borrowerName())
                    ->searchable(query: fn (Builder $query, string $search) => $query->where(fn (Builder $q) => $q
                        ->whereHas('student', fn (Builder $s) => $s->where('name', 'like', "%{$search}%")->orWhere('admission_no', 'like', "%{$search}%"))
                        ->orWhereHas('staff', fn (Builder $s) => $s->where('name', 'like', "%{$search}%")))),
                TextColumn::make('issued_on')->label('Date issued')->date('j M Y')->sortable(),
                TextColumn::make('due_on')->label('Return date')->date('j M Y')->sortable()->placeholder('—')
                    ->color(fn (LibraryLoan $record): ?string => $record->state() === 'overdue' ? 'danger' : null),
                TextColumn::make('state')
                    ->label('Status')
                    ->state(fn (LibraryLoan $record): string => $record->state())
                    ->formatStateUsing(fn (string $state, LibraryLoan $record): string => match ($state) {
                        'returned' => 'Returned '.$record->returned_on?->format('j M'),
                        'lost' => $record->charge ? 'Lost · billed UGX '.number_format((float) $record->charge->amount) : 'Lost',
                        default => LibraryLoan::STATES[$state],
                    })
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'overdue', 'lost' => 'danger',
                        'returned' => 'success',
                        default => 'warning',
                    }),
            ])
            ->filters([
                SelectFilter::make('state')
                    ->label('Show')
                    ->options(['out' => 'Not yet returned', 'overdue' => 'Overdue', 'returned' => 'Returned', 'lost' => 'Lost', 'all' => 'Everything'])
                    ->default('out')
                    ->query(fn (Builder $query, array $data) => static::whereState($query, $data['value'] ?? 'out')),
                SelectFilter::make('class')
                    ->label('Class')
                    ->options(fn (): array => SchoolClass::where('school_id', auth()->user()?->school_id)->orderBy('id')->pluck('name', 'id')->all())
                    ->query(fn (Builder $query, array $data) => filled($data['value'] ?? null)
                        ? $query->whereHas('student', fn (Builder $s) => $s->where('school_class_id', $data['value']))
                        : $query),
                SelectFilter::make('who')
                    ->label('Issued to')
                    ->options(['learners' => 'Learners', 'staff' => 'Staff'])
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        'learners' => $query->whereNotNull('student_id'),
                        'staff' => $query->whereNotNull('staff_id'),
                        default => $query,
                    }),
            ])
            ->recordActions([
                Action::make('return')
                    ->label('Returned')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('success')
                    ->visible(fn (LibraryLoan $record): bool => in_array($record->state(), ['out', 'overdue'], true))
                    ->modalHeading(fn (LibraryLoan $record): string => $record->copy->label().' back from '.$record->borrowerName())
                    ->modalWidth('md')
                    ->schema([
                        Select::make('condition')->label('Condition now')->options(LibraryCopy::CONDITIONS)->default(fn (LibraryLoan $record): string => $record->copy->condition)->required()->native(false),
                        DatePicker::make('returned_on')->label('Returned on')->default(today())->maxDate(today())->required()->native(false),
                    ])
                    ->action(fn (LibraryLoan $record, array $data, Lending $lending) => $lending->return($record, $data['condition'], Carbon::parse($data['returned_on']))),
                Action::make('lost')
                    ->label('Lost')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->color('danger')
                    ->visible(fn (LibraryLoan $record): bool => in_array($record->state(), ['out', 'overdue'], true))
                    ->modalHeading(fn (LibraryLoan $record): string => $record->copy->label().' is lost')
                    ->modalDescription(fn (LibraryLoan $record): string => $record->student
                        ? 'The copy is marked lost. You can bill its price to '.$record->student->name.'\'s fees for this term; it then shows on their balance, invoice and statement.'
                        : 'The copy is marked lost. Staff are not billed through fees.')
                    ->modalWidth('md')
                    ->schema([
                        Toggle::make('bill')
                            ->label('Bill it to the learner\'s fees')
                            ->default(true)
                            ->live()
                            ->visible(fn (LibraryLoan $record): bool => $record->student !== null),
                        MoneyInput::make('amount')
                            ->label('Amount (UGX)')
                            ->numeric()
                            ->minValue(0)
                            ->default(fn (LibraryLoan $record) => $record->copy->book->price)
                            ->helperText('The book\'s replacement price; change it if needed.')
                            ->required(fn (Get $get): bool => (bool) $get('bill'))
                            ->visible(fn (Get $get, LibraryLoan $record): bool => $record->student !== null && (bool) $get('bill')),
                    ])
                    ->action(function (LibraryLoan $record, array $data, Lending $lending): void {
                        $amount = ($data['bill'] ?? false) ? (float) ($data['amount'] ?? 0) : null;
                        $lending->markLost($record, $amount);

                        Notification::make()
                            ->title('Marked lost')
                            ->body($record->fresh()->charge ? 'UGX '.number_format((float) $amount).' added to '.$record->student->name.'\'s fees.' : null)
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkAction::make('returnSelected')
                    ->label('Returned')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription('Mark every selected book as returned today, in the condition it went out.')
                    ->action(function (Collection $records, Lending $lending): void {
                        $open = LibraryLoan::whereKey($records->pluck('id')->all())->outstanding()->get();
                        $open->each(fn (LibraryLoan $loan) => $lending->return($loan));
                        $back = $open->count();

                        Notification::make()->title($back.' '.str('book')->plural($back).' returned')->success()->send();
                    })
                    ->deselectRecordsAfterCompletion(),
            ])
            ->emptyStateHeading('No books issued out')
            ->emptyStateDescription('Issue a book with "Issue a book" above, or issue a textbook to a whole class from Library → Books.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageLibraryLoans::route('/'),
        ];
    }

    /**
     * @param  Builder<LibraryLoan>  $query
     * @return Builder<LibraryLoan>
     */
    protected static function whereState(Builder $query, string $state): Builder
    {
        return match ($state) {
            'overdue' => $query->outstanding()->whereDate('due_on', '<', today()),
            'returned' => $query->whereNotNull('returned_on'),
            'lost' => $query->whereNotNull('lost_on'),
            'all' => $query,
            default => $query->outstanding(),
        };
    }
}
