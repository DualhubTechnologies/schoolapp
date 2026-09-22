<?php

namespace App\Filament\Admin\Resources\DemoRequests\Pages;

use App\Filament\Admin\Resources\DemoRequests\DemoRequestResource;
use Filament\Resources\Pages\ManageRecords;

class ManageDemoRequests extends ManageRecords
{
    protected static string $resource = DemoRequestResource::class;

    protected ?string $subheading = 'Demo bookings and enquiries from the landing page. Each one is also emailed to the contact address.';
}
