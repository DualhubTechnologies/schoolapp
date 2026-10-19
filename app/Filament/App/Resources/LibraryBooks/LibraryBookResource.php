<?php

namespace App\Filament\App\Resources\LibraryBooks;

use App\Filament\App\Resources\LibraryBooks\Pages\ManageLibraryBooks;
use App\Filament\App\Resources\LibraryLoans\LibraryLoanResource;
use App\Filament\Concerns\GatedByModule;
use App\Filament\Concerns\PlacedInMenu;
use App\Filament\Support\MoneyInput;
use App\Models\LibraryBook;
use App\Models\LibraryCopy;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Term;
use App\Services\Library\Lending;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * The library's books: each title with its numbered copies (LIB-0001…)
 * and what a lost copy costs. From here a copy of a textbook can be lent
 * to every learner in a class in one go.
 */
class LibraryBookResource extends Resource
{
    use GatedByModule;
    use PlacedInMenu;

    protected static ?string $model = LibraryBook::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static ?string $modelLabel = 'book';

    protected static ?string $slug = 'library/books';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('school_id', auth()->user()?->school_id)
            ->withCount([
                'copies',
                'copies as available_count' => fn (Builder $q) => $q->where('status', LibraryCopy::AVAILABLE),
                'copies as out_count' => fn (Builder $q) => $q->where('status', LibraryCopy::ON_LOAN),
            ]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('title')->required()->maxLength(255)->columnSpanFull()->placeholder('e.g. MK Primary Mathematics Bk 6'),
            TextInput::make('author')->maxLength(255)->placeholder('Author or publisher\'s series'),
            Select::make('category')->options(LibraryBook::CATEGORIES)->default('textbook')->required()->native(false),
            TextInput::make('subject')->maxLength(100)->placeholder('e.g. Biology'),
            TextInput::make('class_level')->label('For class')->maxLength(50)->placeholder('e.g. S.2 or P.5 – P.7'),
            MoneyInput::make('price')
                ->label('Replacement price (UGX)')
                ->numeric()
                ->minValue(0)
                ->helperText('Billed to a learner\'s fees if they lose a copy.'),
            TextInput::make('shelf')->maxLength(50)->placeholder('e.g. Shelf B3'),
            TextInput::make('isbn')->label('ISBN')->maxLength(30),
            TextInput::make('publisher')->maxLength(255),
            TextInput::make('copies')
                ->label('How many copies?')
                ->helperText('Each copy gets its own number (LIB-0001, LIB-0002…) to write or stamp inside it.')
                ->numeric()
                ->minValue(0)
                ->maxValue(1000)
                ->default(1)
                ->required()
                ->visibleOn('create'),
            Textarea::make('notes')->rows(2)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('title')
            ->columns([
                TextColumn::make('title')
                    ->weight('semibold')
                    ->searchable(['title', 'author', 'subject', 'isbn'])
                    ->sortable()
                    ->description(fn (LibraryBook $record): ?string => collect([$record->author, $record->subject, $record->class_level])->filter()->implode(' · ') ?: null)
                    ->wrap(),
                TextColumn::make('category')
                    ->formatStateUsing(fn (string $state): string => explode(' (', LibraryBook::CATEGORIES[$state] ?? $state)[0])
                    ->badge()
                    ->color('gray'),
                TextColumn::make('copies_count')->label('Copies')->numeric()->sortable()->alignCenter(),
                TextColumn::make('available_count')->label('On the shelf')->badge()->color(fn (int $state): string => $state > 0 ? 'success' : 'danger')->alignCenter(),
                TextColumn::make('out_count')->label('Borrowed')->badge()->color(fn (int $state): string => $state > 0 ? 'warning' : 'gray')->alignCenter(),
                TextColumn::make('price')->label('Price (UGX)')->numeric()->placeholder('—')->toggleable(),
                TextColumn::make('shelf')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('category')->options(LibraryBook::CATEGORIES),
            ])
            ->recordActions([
                Action::make('issueToClass')
                    ->label('Lend to a class')
                    ->icon('heroicon-o-user-group')
                    ->color('primary')
                    ->visible(fn (LibraryBook $record): bool => $record->available_count > 0)
                    ->modalHeading(fn (LibraryBook $record): string => 'Lend "'.$record->title.'" to a class')
                    ->modalDescription(fn (LibraryBook $record): string => "A copy to each learner who has none yet, in name order, while the {$record->available_count} on the shelf last.")
                    ->schema([
                        Select::make('school_class_id')
                            ->label('Class')
                            ->options(fn (): array => SchoolClass::where('school_id', auth()->user()?->school_id)->orderBy('id')->pluck('name', 'id')->all())
                            ->required()
                            ->live()
                            ->native(false),
                        Select::make('section_id')
                            ->label('Stream')
                            ->placeholder('The whole class')
                            ->options(fn (Get $get): array => Section::where('school_class_id', $get('school_class_id'))->pluck('name', 'id')->all())
                            ->visible(fn (Get $get): bool => filled($get('school_class_id')) && Section::where('school_class_id', $get('school_class_id'))->exists())
                            ->native(false),
                        DatePicker::make('due_on')
                            ->label('Bring back by')
                            ->default(fn () => Term::current(auth()->user()?->school_id)?->end_date)
                            ->helperText('Usually the end of term for textbooks.')
                            ->native(false),
                    ])
                    ->action(function (LibraryBook $record, array $data, Lending $lending): void {
                        $result = $lending->issueToClass($record, (int) $data['school_class_id'], $data['section_id'] ?? null, filled($data['due_on'] ?? null) ? Carbon::parse($data['due_on']) : null);

                        Notification::make()
                            ->title($result['issued'].' '.str('copy')->plural($result['issued']).' lent')
                            ->body(collect([
                                $result['already'] ? $result['already'].' already had one' : null,
                                $result['short'] ? $result['short'].' learners got none: not enough copies on the shelf' : null,
                            ])->filter()->implode('. ') ?: null)
                            ->status($result['short'] ? 'warning' : 'success')
                            ->send();
                    }),
                ActionGroup::make([
                    Action::make('addCopies')
                        ->label('Add copies')
                        ->icon('heroicon-o-plus')
                        ->schema([
                            TextInput::make('count')->label('How many?')->numeric()->minValue(1)->maxValue(1000)->default(1)->required(),
                            Select::make('condition')->options(LibraryCopy::CONDITIONS)->default('good')->required()->native(false),
                        ])
                        ->action(function (LibraryBook $record, array $data): void {
                            $made = $record->addCopies((int) $data['count'], $data['condition']);

                            Notification::make()
                                ->title(count($made).' '.str('copy')->plural(count($made)).' added')
                                ->body('Numbered '.$made[0]->accession_no.(count($made) > 1 ? ' to '.end($made)->accession_no : '').'. Write or stamp each number inside its copy.')
                                ->success()
                                ->send();
                        }),
                    EditAction::make('copies')
                        ->label('Copies')
                        ->icon('heroicon-o-queue-list')
                        ->modalHeading(fn (LibraryBook $record): string => 'Copies of "'.$record->title.'"')
                        ->schema([
                            Repeater::make('copies')
                                ->hiddenLabel()
                                ->relationship(modifyQueryUsing: fn (Builder $q) => $q->orderBy('accession_no'))
                                ->table([
                                    Repeater\TableColumn::make('Number'),
                                    Repeater\TableColumn::make('Condition'),
                                    Repeater\TableColumn::make('Status'),
                                ])
                                ->schema([
                                    TextInput::make('accession_no')->required()->maxLength(50),
                                    Select::make('condition')->options(LibraryCopy::CONDITIONS)->required()->native(false),
                                    Select::make('status')
                                        ->options(fn (?LibraryCopy $record): array => in_array($record?->status, [LibraryCopy::ON_LOAN, LibraryCopy::LOST], true)
                                            ? LibraryCopy::STATUSES
                                            : [LibraryCopy::AVAILABLE => LibraryCopy::STATUSES[LibraryCopy::AVAILABLE], LibraryCopy::WITHDRAWN => LibraryCopy::STATUSES[LibraryCopy::WITHDRAWN]])
                                        ->disabled(fn (?LibraryCopy $record): bool => in_array($record?->status, [LibraryCopy::ON_LOAN, LibraryCopy::LOST], true))
                                        ->default(LibraryCopy::AVAILABLE)
                                        ->required()
                                        ->native(false),
                                ])
                                ->mutateRelationshipDataBeforeCreateUsing(fn (array $data): array => $data + ['school_id' => auth()->user()?->school_id])
                                ->addActionLabel('Add a copy')
                                ->deletable(fn (): bool => false),
                        ]),
                    Action::make('history')
                        ->label('Who has it')
                        ->icon('heroicon-o-clock')
                        ->url(fn (LibraryBook $record): string => LibraryLoanResource::getUrl('index', ['search' => $record->title])),
                    EditAction::make(),
                    DeleteAction::make()
                        ->visible(fn (LibraryBook $record): bool => ! $record->loans()->exists())
                        ->modalDescription('The book and all its copies will be removed. Books that have ever been lent cannot be deleted, to keep the record.'),
                ]),
            ])
            ->emptyStateHeading('No books yet')
            ->emptyStateDescription('Add each title with how many copies you have, e.g. "Comprehensive Biology Bk 2", 40 copies. Each copy gets a number to stamp inside it.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageLibraryBooks::route('/'),
        ];
    }
}
