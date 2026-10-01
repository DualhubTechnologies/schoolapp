<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A licence key entered in the Windows app. The dates here are only for
 * listing: the app always re-checks the key's signature and reads the
 * dates from the key itself (DesktopLicence), so editing this table does
 * not extend anything.
 *
 * @property int $id
 * @property string $licence_no
 * @property string $key
 * @property Carbon $starts_on
 * @property Carbon $ends_on
 */
class LicenceKeyRecord extends Model
{
    protected $table = 'licence_keys';

    protected $fillable = ['licence_no', 'key', 'starts_on', 'ends_on'];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }
}
