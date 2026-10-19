<?php

namespace App\Filament\App\Resources\LibraryBooks\Pages;

use App\Filament\App\Resources\LibraryBooks\LibraryBookResource;
use App\Models\LibraryBook;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Facades\DB;

class ManageLibraryBooks extends ManageRecords
{
    protected static string $resource = LibraryBookResource::class;

    public function getSubheading(): ?string
    {
        return 'Every title in the library and its numbered copies. Lend books under Library → Lending, or lend a textbook to a whole class from here.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('New book')
                ->using(function (array $data): LibraryBook {
                    $copies = (int) ($data['copies'] ?? 0);
                    unset($data['copies']);

                    return DB::transaction(function () use ($data, $copies): LibraryBook {
                        $book = LibraryBook::create($data + ['school_id' => auth()->user()?->school_id]);
                        $book->addCopies($copies);

                        return $book;
                    });
                }),
        ];
    }
}
