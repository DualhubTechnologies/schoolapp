<?php

namespace App\Filament\App\Resources\LibraryLoans\Pages;

use App\Filament\App\Resources\LibraryLoans\LibraryLoanResource;
use App\Filament\Support\PeopleSearch;
use App\Models\LibraryCopy;
use App\Models\Student;
use App\Services\Library\Lending;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class ManageLibraryLoans extends ManageRecords
{
    protected static string $resource = LibraryLoanResource::class;

    public function getSubheading(): ?string
    {
        return 'Books out with learners and staff. Overdue ones show in red; at the end of term, AI Insights names the learners who still have books.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('lend')
                ->label('Lend a book')
                ->icon('heroicon-o-book-open')
                ->modalWidth('lg')
                ->schema([
                    Select::make('library_copy_id')
                        ->label('Book')
                        ->helperText('Type the number inside the book (e.g. LIB-0042) or the title.')
                        ->searchable()
                        ->getSearchResultsUsing(fn (string $search): array => LibraryCopy::query()
                            ->with('book')
                            ->where('school_id', auth()->user()?->school_id)
                            ->where('status', LibraryCopy::AVAILABLE)
                            ->where(fn (Builder $q) => $q->where('accession_no', 'like', "%{$search}%")->orWhereHas('book', fn (Builder $b) => $b->where('title', 'like', "%{$search}%")))
                            ->orderBy('accession_no')
                            ->limit(30)
                            ->get()
                            ->mapWithKeys(fn (LibraryCopy $copy): array => [$copy->id => $copy->label()])
                            ->all())
                        ->getOptionLabelUsing(fn ($value): ?string => LibraryCopy::with('book')->where('school_id', auth()->user()?->school_id)->whereKey($value)->first()?->label())
                        ->required(),
                    ToggleButtons::make('who')
                        ->label('Borrowed by')
                        ->options(['learner' => 'A learner', 'staff' => 'A member of staff'])
                        ->default('learner')
                        ->inline()
                        ->live(),
                    Select::make('student_id')
                        ->label('Learner')
                        ->searchable()
                        ->getSearchResultsUsing(fn (string $search): array => PeopleSearch::learners($search))
                        ->getOptionLabelUsing(fn ($value): ?string => PeopleSearch::learnerLabel($value))
                        ->visible(fn (Get $get): bool => $get('who') !== 'staff')
                        ->required(fn (Get $get): bool => $get('who') !== 'staff'),
                    Select::make('staff_id')
                        ->label('Staff member')
                        ->searchable()
                        ->getSearchResultsUsing(fn (string $search): array => PeopleSearch::staff($search))
                        ->getOptionLabelUsing(fn ($value): ?string => PeopleSearch::staffLabel($value))
                        ->visible(fn (Get $get): bool => $get('who') === 'staff')
                        ->required(fn (Get $get): bool => $get('who') === 'staff'),
                    DatePicker::make('due_on')
                        ->label('Bring back by')
                        ->default(today()->addWeeks(2))
                        ->minDate(today())
                        ->native(false),
                    TextInput::make('notes')->maxLength(255)->placeholder('Optional'),
                ])
                ->action(function (array $data, Lending $lending): void {
                    $copy = LibraryCopy::where('school_id', auth()->user()?->school_id)->whereKey($data['library_copy_id'])->firstOrFail();
                    $student = $data['who'] !== 'staff' ? Student::where('school_id', auth()->user()?->school_id)->whereKey($data['student_id'])->first() : null;

                    $loan = $lending->issue($copy, $student, $data['who'] === 'staff' ? (int) $data['staff_id'] : null, filled($data['due_on'] ?? null) ? Carbon::parse($data['due_on']) : null, $data['notes'] ?? null);

                    Notification::make()
                        ->title($copy->label().' lent')
                        ->body('To '.$loan->fresh()->borrowerName().($loan->due_on ? ', due back '.$loan->due_on->format('j M') : '').'.')
                        ->success()
                        ->send();
                }),
        ];
    }
}
