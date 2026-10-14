<?php

namespace App\Filament\Pages\Reports;

use App\Exports\ReportExport;
use App\Filament\Concerns\PlacedInMenu;
use App\Support\Modules;
use Filament\Pages\Page;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * A school report: filters at the top, then the report itself on a page
 * with the school's letterhead, ready to print (A4) or download as an
 * Excel sheet. Each report says what it shows (on All Reports and under
 * its title) and which module opens it (Modules::CLASS_MAP).
 */
abstract class ReportPage extends Page
{
    use PlacedInMenu;

    /** One line on what the report shows, for All Reports. */
    abstract public static function about(): string;

    /** What the report covers right now, e.g. "Term 3 — 2026" or the dates. */
    abstract public function coverage(): string;

    /**
     * The report as a table for Excel: headings, then rows (totals last).
     *
     * @return array{0: array<int, string>, 1: array<int, array<int, string|int|float|null>>}
     */
    abstract public function sheet(): array;

    public static function canAccess(): bool
    {
        return filled(auth()->user()?->school_id) && Modules::allowsClass(static::class);
    }

    public function getSubheading(): ?string
    {
        return static::about();
    }

    public function downloadExcel(): BinaryFileResponse
    {
        [$headings, $rows] = $this->sheet();
        $school = (string) auth()->user()?->school?->name;
        $title = static::$title ?? class_basename(static::class);

        return Excel::download(
            new ReportExport($school, $title, $this->coverage(), $headings, $rows),
            preg_replace('/[^A-Za-z0-9]+/', '_', trim("{$title} {$this->coverage()}")).'.xlsx',
        );
    }
}
