<?php

use App\Support\PrivateFiles;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Student photos, head teachers' signatures and finance receipts were
 * saved on the public disk, reachable by anyone with the link. Move them
 * to the private 'uploads' disk under the same paths, so the stored
 * paths stay valid. School logos stay public.
 */
return new class extends Migration
{
    /** table => column */
    protected const COLUMNS = [
        'students' => 'photo',
        'schools' => 'hm_signature',
        'finance_entries' => 'attachment',
    ];

    public function up(): void
    {
        $this->move(Storage::disk('public'), Storage::disk(PrivateFiles::DISK));
    }

    public function down(): void
    {
        $this->move(Storage::disk(PrivateFiles::DISK), Storage::disk('public'));
    }

    protected function move($from, $to): void
    {
        foreach (self::COLUMNS as $table => $column) {
            DB::table($table)->whereNotNull($column)->where($column, '!=', '')->orderBy('id')
                ->pluck($column)
                ->each(function (string $path) use ($from, $to): void {
                    if (! $from->exists($path)) {
                        return;
                    }

                    if (! $to->exists($path)) {
                        $to->put($path, $from->get($path));
                    }

                    if ($to->exists($path)) {
                        $from->delete($path);
                    }
                });
        }
    }
};
