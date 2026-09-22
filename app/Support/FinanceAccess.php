<?php

namespace App\Support;

/**
 * Who handles the school's money: administrators, accountants and bursars.
 */
class FinanceAccess
{
    public const ROLES = ['School Admin', 'Accountant', 'Bursar'];

    public static function allowed(): bool
    {
        return Modules::allows('finance');
    }
}
