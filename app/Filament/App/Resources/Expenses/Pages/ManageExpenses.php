<?php

namespace App\Filament\App\Resources\Expenses\Pages;

use App\Filament\App\Resources\Expenses\ExpenseResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageExpenses extends ManageRecords
{
    protected static string $resource = ExpenseResource::class;

    public function getSubheading(): ?string
    {
        return 'Every payment the school makes, with a numbered voucher. Salaries are counted automatically from approved payroll — do not enter them here.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Record expense')
                ->mutateDataUsing(fn (array $data) => $data + ['school_id' => auth()->user()->school_id, 'type' => 'expense']),
        ];
    }
}
