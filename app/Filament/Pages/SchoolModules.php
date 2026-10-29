<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\PlacedInMenu;
use App\Support\Modules;
use BackedEnum;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * The school administrator chooses which parts of SchoolHub the school
 * uses. A module switched off leaves the menu, dashboard and Insights for
 * everyone; its records stay, and switching it back on brings them back.
 *
 * @property-read Schema $form
 */
class SchoolModules extends Page implements HasForms
{
    use InteractsWithForms;
    use PlacedInMenu;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static ?string $title = 'Modules';

    protected static ?string $slug = 'modules';

    protected string $view = 'filament.pages.school-modules';

    /** @var array{on?: list<string>} */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->school_id !== null && Modules::hasFullAccess(auth()->user());
    }

    public function mount(): void
    {
        $school = auth()->user()->school;

        $this->form->fill(['on' => array_values(array_diff(array_keys(Modules::switchable($school)), Modules::switchedOff($school)))]);
    }

    public function form(Schema $schema): Schema
    {
        $school = auth()->user()?->school;

        return $schema
            ->components([
                CheckboxList::make('on')
                    ->label('Modules this school uses')
                    ->helperText('Learners, admissions, school set-up and settings are always on.')
                    ->options(Modules::switchable($school))
                    ->descriptions(collect(Modules::descriptions())->only(array_keys(Modules::switchable($school)))->all())
                    ->bulkToggleable()
                    ->columns(2),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $school = auth()->user()->school;
        $on = (array) ($this->form->getState()['on'] ?? []);

        $school->forceFill(['modules_off' => array_values(array_diff(array_keys(Modules::switchable($school)), $on))])->save();

        Notification::make()
            ->title('Modules saved')
            ->body('Modules switched off are hidden for everyone. Their records stay, ready if you switch them back on.')
            ->success()
            ->send();

        $this->redirect(static::getUrl());
    }
}
