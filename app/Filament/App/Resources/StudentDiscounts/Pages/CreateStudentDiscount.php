<?php

namespace App\Filament\App\Resources\StudentDiscounts\Pages;

use App\Filament\App\Resources\StudentDiscounts\StudentDiscountResource;
use Filament\Resources\Pages\CreateRecord;

class CreateStudentDiscount extends CreateRecord
{
    protected static string $resource = StudentDiscountResource::class;
}
