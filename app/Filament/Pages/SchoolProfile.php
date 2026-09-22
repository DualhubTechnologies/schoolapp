<?php

namespace App\Filament\Pages;

use App\Filament\Admin\Resources\Schools\Schemas\SchoolForm;
use App\Models\School;
use BackedEnum;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class SchoolProfile extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'School Profile';

    protected string $view = 'filament.pages.school-profile';

    public ?array $data = [];

    public function mount(): void
    {
        $school = auth()->user()->school;

        abort_if(! $school, 404, 'School not found.');

        $this->form->fill($school->toArray());
    }

    public function form(Schema $schema): Schema
    {
        return SchoolForm::configure($schema)
            ->statePath('data');
    }

    public function save(): void
    {
        $school = auth()->user()->school;

        abort_if(! $school, 404, 'School not found.');

        $school->update($this->form->getState());

        Notification::make()
            ->title('School profile updated')
            ->body('Your school information has been saved successfully.')
            ->success()
            ->send();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function canAccess(): bool
    {
        return \App\Support\Modules::allows('settings');
    }
}
