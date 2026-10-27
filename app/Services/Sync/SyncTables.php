<?php

namespace App\Services\Sync;

/**
 * What the Windows app and the online account keep in step, in the order
 * records are saved (a record comes after the ones it points to; deletes
 * run in the reverse order). Release 1: the school's structure, learners
 * and parents, staff, fees and payments, income and expenses, marks and
 * report cards.
 *
 * For each table:
 *   via       for a table without school_id: [parent table, column] that
 *             ties its rows to the school (marks through assessments).
 *   same      columns that make two records the same thing, e.g. a class
 *             name: a record added on both sides is joined up quietly,
 *             and the latest edit wins. [] means one per school.
 *   review    how possible duplicates are spotted (DuplicateFinder) and
 *             listed for someone to decide on, instead of `same`.
 *   numbers   a running number that may clash when both sides add
 *             records: the incoming record takes the next free one; the
 *             printed number (receipt_no, voucher_no) never changes.
 *   finance   deleting one is listed for confirmation before the other
 *             side deletes it too.
 *   id_lists  JSON columns holding lists of ids, e.g. an exam's classes.
 *
 * @phpstan-type TableRules array{via?: array{0: string, 1: string}, same?: list<string>, review?: string, numbers?: array{column: string, group: list<string>}, finance?: bool, id_lists?: array<string, string>}
 */
class SyncTables
{
    /** @var array<string, TableRules> */
    public const TABLES = [
        'academic_years' => ['same' => ['name']],
        'terms' => ['same' => ['academic_year_id', 'sequence']],
        'class_levels' => ['same' => ['name']],
        'residency_types' => ['same' => ['name']],
        'houses' => ['same' => ['name']],
        'transport_routes' => ['same' => ['name']],
        'staff' => ['review' => 'staff'],
        'school_classes' => ['same' => ['name']],
        'sections' => ['same' => ['school_class_id', 'name']],
        'subjects' => ['same' => ['curriculum', 'name']],
        'class_subject' => ['via' => ['school_classes', 'school_class_id'], 'same' => ['school_class_id', 'subject_id']],
        'combinations' => ['same' => ['name']],
        'combination_subject' => ['via' => ['combinations', 'combination_id'], 'same' => ['combination_id', 'subject_id']],
        'guardians' => ['same' => ['phone', 'name']],
        'students' => ['review' => 'learner'],
        'student_subject' => ['via' => ['students', 'student_id'], 'same' => ['student_id', 'subject_id']],
        'fee_structures' => ['same' => ['name', 'school_class_id', 'term_id', 'residency_type_id', 'frequency', 'applies_to']],
        'bank_accounts' => ['same' => ['name', 'account_number']],
        'student_charges' => ['same' => ['student_id', 'term_id', 'fee_structure_id', 'description']],
        'student_discounts' => ['same' => ['student_id', 'fee_structure_id', 'term_id', 'academic_year_id', 'scope']],
        'student_payments' => ['review' => 'payment', 'numbers' => ['column' => 'receipt_seq', 'group' => []], 'finance' => true],
        'finance_categories' => ['same' => ['type', 'name']],
        'finance_entries' => ['review' => 'entry', 'numbers' => ['column' => 'voucher_seq', 'group' => ['type']], 'finance' => true],
        'grading_scales' => ['same' => ['curriculum', 'purpose']],
        'grading_bands' => ['via' => ['grading_scales', 'grading_scale_id'], 'same' => ['grading_scale_id', 'grade']],
        'assessments' => ['same' => ['term_id', 'name'], 'id_lists' => ['class_ids' => 'school_classes', 'subject_ids' => 'subjects']],
        'mark_sheets' => ['same' => ['assessment_id', 'school_class_id', 'subject_id']],
        'marks' => ['via' => ['assessments', 'assessment_id'], 'same' => ['assessment_id', 'student_id', 'subject_id', 'paper']],
        'term_reports' => ['via' => ['terms', 'term_id'], 'same' => ['student_id', 'term_id']],
        'syllabus_topics' => ['same' => ['subject_id', 'class_number', 'code', 'name']],
        'topic_scores' => ['same' => ['student_id', 'syllabus_topic_id']],
        'report_card_templates' => ['same' => []],
    ];

    /** Names people understand, for the review list and sync history. */
    public const LABELS = [
        'staff' => 'staff member',
        'students' => 'learner',
        'guardians' => 'parent',
        'student_payments' => 'fees payment',
        'finance_entries' => 'income or expense',
    ];

    /** @return list<string> */
    public static function names(): array
    {
        return array_keys(self::TABLES);
    }

    /** @return TableRules */
    public static function rules(string $table): array
    {
        return self::TABLES[$table] ?? [];
    }

    public static function position(string $table): int
    {
        return (int) array_search($table, self::names(), true);
    }

    public static function isFinance(string $table): bool
    {
        return (bool) (self::TABLES[$table]['finance'] ?? false);
    }

    public static function label(string $table): string
    {
        return self::LABELS[$table] ?? str_replace('_', ' ', str($table)->singular()->toString());
    }
}
