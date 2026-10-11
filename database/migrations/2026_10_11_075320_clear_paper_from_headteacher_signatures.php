<?php

use App\Support\LogoBackground;
use App\Support\PrivateFiles;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Headteacher signatures uploaded before LogoBackground cleaned them still
 * carry their paper (a white or grey box on report cards): take it off.
 */
return new class extends Migration
{
    public function up(): void
    {
        $disk = Storage::disk(PrivateFiles::DISK);

        DB::table('schools')->whereNotNull('hm_signature')->where('hm_signature', '!=', '')->orderBy('id')
            ->each(function (object $school) use ($disk): void {
                if (! $disk->exists($school->hm_signature)) {
                    return;
                }

                $path = LogoBackground::clear($disk, $school->hm_signature, paper: true);

                if ($path !== $school->hm_signature) {
                    DB::table('schools')->where('id', $school->id)->update(['hm_signature' => $path]);
                }
            });
    }

    public function down(): void
    {
        // The original pictures are not kept.
    }
};
