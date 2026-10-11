<?php

use App\Support\LogoBackground;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Logos uploaded before LogoBackground existed still show a white box on
 * report cards and receipts: take it off them too. A logo whose edge is
 * not a light background is left as it is.
 */
return new class extends Migration
{
    public function up(): void
    {
        $disk = Storage::disk('public');

        DB::table('schools')->whereNotNull('logo')->where('logo', '!=', '')->orderBy('id')
            ->each(function (object $school) use ($disk): void {
                if (! $disk->exists($school->logo)) {
                    return;
                }

                $path = LogoBackground::clear($disk, $school->logo);

                if ($path !== $school->logo) {
                    DB::table('schools')->where('id', $school->id)->update(['logo' => $path]);
                }
            });
    }

    public function down(): void
    {
        // The original pictures are not kept.
    }
};
