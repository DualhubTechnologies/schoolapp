<?php

namespace App\Filament\Support;

use Filament\Livewire\Topbar;
use Illuminate\Contracts\View\View;

/**
 * Filament's own top bar, drawn empty. SchoolHub draws its own bar
 * (filament.partials.topbar) at the top of every page, with the search,
 * the attention bell and the user menu; Filament's was only hidden, and
 * still carried a second search box and user menu nobody could see.
 * Keeping an empty one, rather than none, leaves Filament's layout as
 * designed.
 */
class HiddenTopbar extends Topbar
{
    public function render(): View
    {
        return view('filament.partials.hidden-topbar');
    }
}
