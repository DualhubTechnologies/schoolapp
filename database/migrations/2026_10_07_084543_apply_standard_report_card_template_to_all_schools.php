<?php

use App\Models\ReportCardTemplate;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Every school's report card uses the standard template: the classic
 * design, plain type, no border, and the default parts of the card
 * (ReportCardTemplate::SECTIONS). A school's own colours, title, header
 * note and footer text are kept. Schools that never saved a template
 * already get the standard one.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('report_card_templates')->update([
            'design' => ReportCardTemplate::DEFAULTS['design'],
            'font' => ReportCardTemplate::DEFAULTS['font'],
            'border' => ReportCardTemplate::DEFAULTS['border'],
            'watermark' => ReportCardTemplate::DEFAULTS['watermark'],
            'show' => null,
            'updated_at' => now(),
        ]);
    }

    /** The schools' earlier choices are not kept, so there is nothing to restore. */
    public function down(): void {}
};
