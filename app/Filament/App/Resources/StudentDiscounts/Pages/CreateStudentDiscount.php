<?php

namespace App\Filament\App\Resources\StudentDiscounts\Pages;

use App\Filament\App\Resources\StudentDiscounts\StudentDiscountResource;
use App\Filament\Support\Pages\CreateRecordPage;

class CreateStudentDiscount extends CreateRecordPage
{
    protected static string $resource = StudentDiscountResource::class;
}
