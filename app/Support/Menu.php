<?php

namespace App\Support;

use App\Filament\App\Pages\TransportCollections;
use App\Filament\App\Resources\AcademicYears\AcademicYearResource;
use App\Filament\App\Resources\AllowanceTypes\AllowanceTypeResource;
use App\Filament\App\Resources\Assessments\AssessmentResource;
use App\Filament\App\Resources\Assets\AssetResource;
use App\Filament\App\Resources\AuditTrail\AuditTrailResource;
use App\Filament\App\Resources\BankAccounts\BankAccountResource;
use App\Filament\App\Resources\BankStatementLines\BankStatementLineResource;
use App\Filament\App\Resources\Billing\BillingResource;
use App\Filament\App\Resources\ClassLevels\ClassLevelResource;
use App\Filament\App\Resources\Combinations\CombinationResource;
use App\Filament\App\Resources\DeductionTypes\DeductionTypeResource;
use App\Filament\App\Resources\Expenses\ExpenseResource;
use App\Filament\App\Resources\FeeBalances\FeeBalanceResource;
use App\Filament\App\Resources\FeeReminders\FeeReminderResource;
use App\Filament\App\Resources\FeeStructures\FeeStructureResource;
use App\Filament\App\Resources\FinanceCategories\FinanceCategoryResource;
use App\Filament\App\Resources\GradingScales\GradingScaleResource;
use App\Filament\App\Resources\Guardians\GuardianResource;
use App\Filament\App\Resources\Houses\HouseResource;
use App\Filament\App\Resources\Incomes\IncomeResource;
use App\Filament\App\Resources\LibraryBooks\LibraryBookResource;
use App\Filament\App\Resources\LibraryLoans\LibraryLoanResource;
use App\Filament\App\Resources\Payments\PaymentResource;
use App\Filament\App\Resources\PayrollPeriods\PayrollPeriodResource;
use App\Filament\App\Resources\ResidencyTypes\ResidencyTypeResource;
use App\Filament\App\Resources\SalaryArrears\SalaryArrearResource;
use App\Filament\App\Resources\SchoolClasses\SchoolClassResource;
use App\Filament\App\Resources\SchoolPayTransactions\SchoolPayTransactionResource;
use App\Filament\App\Resources\Sections\SectionResource;
use App\Filament\App\Resources\SickBayMedicines\SickBayMedicineResource;
use App\Filament\App\Resources\SickBayVisits\SickBayVisitResource;
use App\Filament\App\Resources\Staff\StaffResource;
use App\Filament\App\Resources\StoreItems\StoreItemResource;
use App\Filament\App\Resources\StoreMovements\StoreMovementResource;
use App\Filament\App\Resources\StudentDiscounts\StudentDiscountResource;
use App\Filament\App\Resources\Students\StudentResource;
use App\Filament\App\Resources\Subjects\SubjectResource;
use App\Filament\App\Resources\SyllabusTopics\SyllabusTopicResource;
use App\Filament\App\Resources\Terms\TermResource;
use App\Filament\App\Resources\TransportLearners\TransportLearnerResource;
use App\Filament\App\Resources\TransportRoutes\TransportRouteResource;
use App\Filament\Pages\AssessTopics;
use App\Filament\Pages\AttendanceReport;
use App\Filament\Pages\Backups;
use App\Filament\Pages\ClassResults;
use App\Filament\Pages\EnterMarks;
use App\Filament\Pages\FeeStructureSheet;
use App\Filament\Pages\IncomeExpenditure;
use App\Filament\Pages\Licence;
use App\Filament\Pages\MarksProgress;
use App\Filament\Pages\PromoteStudents;
use App\Filament\Pages\ReceivePayment;
use App\Filament\Pages\ReportCards;
use App\Filament\Pages\Reports\AllReports;
use App\Filament\Pages\Reports\EnrolmentReport;
use App\Filament\Pages\Reports\FeeCollectionsReport;
use App\Filament\Pages\Reports\FeesByClassReport;
use App\Filament\Pages\SchoolPaySettings;
use App\Filament\Pages\SchoolProfile;
use App\Filament\Pages\SchoolSubscription;
use App\Filament\Pages\SendMessages;
use App\Filament\Pages\StaffIdCards;
use App\Filament\Pages\StudentIdCards;
use App\Filament\Pages\SubjectChoices;
use App\Filament\Pages\TakeAttendance;
use App\Filament\Pages\TermBudget;

/**
 * The school menu (sidebar), in one place: every page's group, position
 * and label. Daily work comes first, in the order a school uses it; the
 * one-time set-up (years, classes, subjects...) and the settings are
 * folded away at the bottom. Pages read their place from here through
 * App\Filament\Concerns\PlacedInMenu; who sees what is still decided by
 * each page's own access rules (Modules).
 */
class Menu
{
    /** Group => folded until opened. In this order in the sidebar. */
    public const GROUPS = [
        'Students' => false,
        'Fees' => false,
        'Exams & Results' => false,
        'Finance' => false,
        'Staff & Payroll' => false,
        'Library' => false,
        'Sick Bay' => false,
        'Stores' => false,
        'Transport' => false,
        'Reports' => false,
        'School Setup' => true,
        'Settings' => true,
    ];

    /** @var array<class-string, array{0: string, 1: int, 2: string}> page => [group, position, label] */
    public const ITEMS = [
        StudentResource::class => ['Students', 1, 'Students'],
        GuardianResource::class => ['Students', 2, 'Parents & Guardians'],
        TakeAttendance::class => ['Students', 3, 'Attendance Register'],
        PromoteStudents::class => ['Students', 4, 'Promote Students'],
        StudentIdCards::class => ['Students', 5, 'Student ID Cards'],
        SendMessages::class => ['Students', 6, 'Messages (SMS)'],

        ReceivePayment::class => ['Fees', 1, 'Receive Payment'],
        PaymentResource::class => ['Fees', 2, 'Payments'],
        FeeBalanceResource::class => ['Fees', 3, 'Student Accounts'],
        BillingResource::class => ['Fees', 4, 'Billing'],
        StudentDiscountResource::class => ['Fees', 5, 'Discounts & Bursaries'],
        FeeReminderResource::class => ['Fees', 6, 'Fee Reminders'],
        SchoolPayTransactionResource::class => ['Fees', 7, 'SchoolPay Payments'],
        FeeStructureResource::class => ['Fees', 8, 'Fee Setup'],
        FeeStructureSheet::class => ['Fees', 9, 'Fees Sheet'],

        AssessmentResource::class => ['Exams & Results', 1, 'Exams'],
        EnterMarks::class => ['Exams & Results', 2, 'Enter Marks'],
        AssessTopics::class => ['Exams & Results', 3, 'Assess Topics'],
        MarksProgress::class => ['Exams & Results', 4, 'Marks Progress'],
        ClassResults::class => ['Exams & Results', 5, 'Results'],
        ReportCards::class => ['Exams & Results', 6, 'Report Cards'],
        SubjectChoices::class => ['Exams & Results', 7, 'Subject Choices'],
        SyllabusTopicResource::class => ['Exams & Results', 8, 'Syllabus Topics'],

        IncomeExpenditure::class => ['Finance', 1, 'Income vs Expenditure'],
        ExpenseResource::class => ['Finance', 2, 'Expenses'],
        IncomeResource::class => ['Finance', 3, 'Other Income'],
        TermBudget::class => ['Finance', 4, 'Budget'],
        BankAccountResource::class => ['Finance', 5, 'Bank Accounts'],
        BankStatementLineResource::class => ['Finance', 6, 'Bank Reconciliation'],

        StaffResource::class => ['Staff & Payroll', 1, 'Staff'],
        PayrollPeriodResource::class => ['Staff & Payroll', 2, 'Payroll'],
        SalaryArrearResource::class => ['Staff & Payroll', 3, 'Salary Arrears'],
        StaffIdCards::class => ['Staff & Payroll', 4, 'Staff ID Cards'],

        LibraryLoanResource::class => ['Library', 1, 'Issue & Return'],
        LibraryBookResource::class => ['Library', 2, 'Books'],

        SickBayVisitResource::class => ['Sick Bay', 1, 'Sick Bay Register'],
        SickBayMedicineResource::class => ['Sick Bay', 2, 'Medicines'],

        StoreItemResource::class => ['Stores', 1, 'Store Items'],
        StoreMovementResource::class => ['Stores', 2, 'Stock Movements'],
        AssetResource::class => ['Stores', 3, 'Asset Register'],

        TransportRouteResource::class => ['Transport', 1, 'Routes'],
        TransportLearnerResource::class => ['Transport', 2, 'Learners on the Van'],
        TransportCollections::class => ['Transport', 3, 'Transport Collections'],

        AllReports::class => ['Reports', 1, 'All Reports'],
        EnrolmentReport::class => ['Reports', 2, 'Enrolment'],
        FeeCollectionsReport::class => ['Reports', 3, 'Fee Collections'],
        FeesByClassReport::class => ['Reports', 4, 'Fees by Class'],
        AttendanceReport::class => ['Reports', 5, 'Attendance Report'],

        AcademicYearResource::class => ['School Setup', 1, 'Academic Years'],
        TermResource::class => ['School Setup', 2, 'Terms'],
        ClassLevelResource::class => ['School Setup', 3, 'Class Levels'],
        SchoolClassResource::class => ['School Setup', 4, 'Classes'],
        SectionResource::class => ['School Setup', 5, 'Streams'],
        SubjectResource::class => ['School Setup', 6, 'Subjects'],
        CombinationResource::class => ['School Setup', 7, 'A-Level Combinations'],
        GradingScaleResource::class => ['School Setup', 8, 'Grading Scales'],
        HouseResource::class => ['School Setup', 9, 'Houses'],
        ResidencyTypeResource::class => ['School Setup', 10, 'Residency Types'],
        FinanceCategoryResource::class => ['School Setup', 11, 'Income & Expense Categories'],
        AllowanceTypeResource::class => ['School Setup', 12, 'Allowance Types'],
        DeductionTypeResource::class => ['School Setup', 13, 'Deduction Types'],

        SchoolProfile::class => ['Settings', 1, 'School Profile'],
        SchoolPaySettings::class => ['Settings', 3, 'SchoolPay'],
        AuditTrailResource::class => ['Settings', 4, 'Audit Trail'],
        Backups::class => ['Settings', 5, 'Backups'],
        SchoolSubscription::class => ['Settings', 6, 'Subscription'],
        Licence::class => ['Settings', 7, 'Licence'],
    ];

    /** @return array{0: string, 1: int, 2: string}|null */
    public static function place(string $class): ?array
    {
        return self::ITEMS[$class] ?? null;
    }
}
