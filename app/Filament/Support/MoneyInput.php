<?php

namespace App\Filament\Support;

use Filament\Forms\Components\TextInput;
use Filament\Support\RawJs;

/**
 * A box for an amount in shillings. It shows 23,000 while it is typed, so a
 * missing or extra zero is easy to spot, and saves the plain number 23000.
 */
class MoneyInput extends TextInput
{
    /** The Alpine mask: thousands separated by commas, no decimals. */
    public const MASK = '$money($input, \'.\', \',\', 0)';

    protected function setUp(): void
    {
        parent::setUp();

        $this->mask(RawJs::make(self::MASK))
            ->stripCharacters(',')
            ->numeric();
    }
}
