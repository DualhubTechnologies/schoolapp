<?php

namespace App\Filament\Support\Pages;

use Filament\Resources\Pages\Page;
use Illuminate\Contracts\View\View;
use Livewire\Livewire;

/**
 * The render-hook scope shared by the house create and edit pages, and the
 * "back to the list" link rendered into it.
 */
final class RecordFormScope
{
    public const NAME = 'sh.record-form';

    public static function backLink(): ?View
    {
        $page = Livewire::current();

        if (! $page instanceof Page) {
            return null;
        }

        $resource = $page::getResource();

        return view('filament.partials.back-to-list', [
            'url' => $resource::getUrl('index'),
            'label' => $resource::getNavigationLabel(),
        ]);
    }
}
