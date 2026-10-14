<?php

namespace App\Filament\Pages\Reports;

use App\Filament\App\Pages\TransportCollections;
use App\Filament\App\Resources\FeeBalances\FeeBalanceResource;
use App\Filament\App\Resources\PayrollPeriods\PayrollPeriodResource;
use App\Filament\Concerns\PlacedInMenu;
use App\Filament\Pages\AttendanceReport;
use App\Filament\Pages\ClassResults;
use App\Filament\Pages\FeeStructureSheet;
use App\Filament\Pages\IncomeExpenditure;
use App\Filament\Pages\MarksProgress;
use App\Filament\Pages\TermBudget;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;

/**
 * Every report the school can print or download, in one place, by area.
 * Each card opens the report; a user sees only the reports their role
 * and modules let them open.
 */
class AllReports extends Page
{
    use PlacedInMenu;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentChartBar;

    protected static ?string $title = 'Reports';

    protected static ?string $slug = 'reports';

    protected string $view = 'filament.pages.reports.all';

    /**
     * Area => [report page or resource => [title, what it shows, icon]].
     *
     * @var array<string, array<class-string<Page>|class-string<resource>, array{0: string, 1: string, 2: Heroicon}>>
     */
    public const REPORTS = [
        'Students' => [
            EnrolmentReport::class => ['Enrolment', 'Learners per class and stream: boys and girls, day and boarding, new learners.', Heroicon::OutlinedUserGroup],
            AttendanceReport::class => ['Attendance', 'Each learner\'s days present, absent and late over a period, lowest first.', Heroicon::OutlinedPresentationChartLine],
        ],
        'Fees' => [
            FeeCollectionsReport::class => ['Fee collections', 'Fees received day by day, by cash, mobile money, bank and SchoolPay, and by class.', Heroicon::OutlinedBanknotes],
            FeesByClassReport::class => ['Fees by class', 'For a term: each class\'s fees billed, paid and still owed, and who owes.', Heroicon::OutlinedChartBar],
            FeeBalanceResource::class => ['Balances and defaulters', 'Every learner\'s balance, filtered to those owing or below a share paid.', Heroicon::OutlinedWallet],
            FeeStructureSheet::class => ['Fees structure', 'The fees per term, on admission and other charges, as published to parents.', Heroicon::OutlinedPrinter],
            TransportCollections::class => ['Transport collections', 'Van fares billed, paid and still owed, per route.', Heroicon::OutlinedTruck],
        ],
        'Finance' => [
            IncomeExpenditure::class => ['Income vs expenditure', 'Money in and out over a term, month or year, by category, and the surplus.', Heroicon::OutlinedScale],
            TermBudget::class => ['Budget vs actual', 'The term\'s budget per category against what was actually spent.', Heroicon::OutlinedCalculator],
        ],
        'Exams & Results' => [
            ClassResults::class => ['Results and broadsheet', 'A class\'s marks, totals, grades and positions for an exam or the term.', Heroicon::OutlinedTableCells],
            MarksProgress::class => ['Marks progress', 'Which subjects and classes still have marks to enter.', Heroicon::OutlinedClipboardDocumentCheck],
        ],
        'Staff & Payroll' => [
            PayrollPeriodResource::class => ['Payroll', 'Each month\'s payroll, payslips, and the PAYE and NSSF schedules.', Heroicon::OutlinedWallet],
        ],
    ];

    public static function canAccess(): bool
    {
        return filled(auth()->user()?->school_id) && static::available() !== [];
    }

    /**
     * The reports this user can open, by area.
     *
     * @return array<string, array<int, array{title: string, about: string, icon: Heroicon, url: string}>>
     */
    public static function available(): array
    {
        $available = [];

        foreach (self::REPORTS as $area => $reports) {
            foreach ($reports as $class => [$title, $about, $icon]) {
                if ($class::canAccess()) {
                    $available[$area][] = ['title' => $title, 'about' => $about, 'icon' => $icon, 'url' => $class::getUrl()];
                }
            }
        }

        return $available;
    }
}
