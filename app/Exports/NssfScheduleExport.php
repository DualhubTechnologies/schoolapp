<?php

namespace App\Exports;

use App\Models\PayrollPeriod;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class NssfScheduleExport implements FromArray, WithColumnWidths, WithHeadings, WithStyles, WithTitle
{
    protected PayrollPeriod $period;

    public function __construct(PayrollPeriod $period)
    {
        $this->period = $period;
    }

    public function title(): string
    {
        return 'NSSF Schedule';
    }

    public function headings(): array
    {
        $school = $this->period->school;

        return [
            ['NATIONAL SOCIAL SECURITY FUND — CONTRIBUTION SCHEDULE'],
            [''],
            ['Employer Name:', $school->name, '', 'NSSF Employer No:', $school->nssf_employer_number ?? 'NOT SET'],
            ['TIN Number:', $school->tin_number ?? 'NOT SET', '', 'Period:', $this->period->period_label],
            [''],
            ['No.', 'Employee Name', 'NSSF Number', 'Gross Salary', 'Employee 5%', 'Employer 10%', 'Total 15%'],
        ];
    }

    public function array(): array
    {
        $entries = $this->period->entries()
            ->with('staff')
            ->where('status', 'included')
            ->get();

        $rows = [];
        $totalGross = 0;
        $totalEmployee = 0;
        $totalEmployer = 0;
        $totalContribution = 0;

        foreach ($entries as $index => $entry) {
            $contribution = $entry->nssf_employee + $entry->nssf_employer;
            $totalGross += $entry->gross_pay;
            $totalEmployee += $entry->nssf_employee;
            $totalEmployer += $entry->nssf_employer;
            $totalContribution += $contribution;

            $rows[] = [
                $index + 1,
                $entry->staff->name,
                $entry->staff->nssf_number ?? '',
                round($entry->gross_pay, 0),
                round($entry->nssf_employee, 0),
                round($entry->nssf_employer, 0),
                round($contribution, 0),
            ];
        }

        $rows[] = ['', '', '', '', '', '', ''];
        $rows[] = ['', '', 'GRAND TOTAL', round($totalGross, 0), round($totalEmployee, 0), round($totalEmployer, 0), round($totalContribution, 0)];
        $rows[] = [''];
        $rows[] = ['', '', 'Total Employee Contributions (5%):', round($totalEmployee, 0)];
        $rows[] = ['', '', 'Total Employer Contributions (10%):', round($totalEmployer, 0)];
        $rows[] = ['', '', 'Total Payable to NSSF (15%):', round($totalContribution, 0)];

        return $rows;
    }

    public function columnWidths(): array
    {
        return [
            'A' => 6,
            'B' => 30,
            'C' => 18,
            'D' => 18,
            'E' => 18,
            'F' => 18,
            'G' => 18,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $lastDataRow = $this->period->entries()->where('status', 'included')->count() + 7;

        return [
            1 => ['font' => ['bold' => true, 'size' => 14]],
            3 => ['font' => ['bold' => true]],
            4 => ['font' => ['bold' => true]],
            6 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '1F4E78']]],
            ($lastDataRow + 1) => ['font' => ['bold' => true]],
        ];
    }
}
