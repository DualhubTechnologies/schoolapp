<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Any report as an Excel sheet: the school and report on the first lines,
 * then the column headings (bold) and the rows, totals included.
 */
class ReportExport implements FromArray, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    /**
     * @param  array<int, string>  $headings
     * @param  array<int, array<int, string|int|float|null>>  $rows
     */
    public function __construct(
        protected string $school,
        protected string $report,
        protected string $subtitle,
        protected array $headings,
        protected array $rows,
    ) {}

    public function title(): string
    {
        return mb_substr($this->report, 0, 31);
    }

    /** @return array<int, array<int, string>> */
    public function headings(): array
    {
        return [
            [$this->school],
            [$this->report.($this->subtitle !== '' ? ' — '.$this->subtitle : '')],
            [''],
            $this->headings,
        ];
    }

    /** @return array<int, array<int, string|int|float|null>> */
    public function array(): array
    {
        return $this->rows;
    }

    /** @return array<int|string, array<string, mixed>> */
    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 13]],
            2 => ['font' => ['bold' => true]],
            4 => ['font' => ['bold' => true]],
        ];
    }
}
