<?php

namespace App\Services\Academics;

/**
 * UNEB's UACE award rules: a principal subject's grade (A–E principal
 * pass, O subsidiary pass, F fail) from the grades of its papers, each
 * 1 (D1) to 9 (F9). A subject sat as one paper is graded as if both of
 * two papers had that grade.
 *
 *   two papers     A both distinctions; B worst C3; C worst C4; D worst C5;
 *                  E worst C6, or a P7/P8 with a sum of 12 or less;
 *                  O a P7/P8 with a sum of 16 or less, or F9 with P7 or
 *                  better; F otherwise
 *   three / four   A worst C3, the rest distinctions; B worst C4, the rest
 *                  C3 or better; C worst C5, the rest C4 or better; D worst
 *                  C6, the rest C5 or better; E a P7 with the rest credits,
 *                  or a P8 with the rest credits and at least one better
 *                  than C6; O all P8 or better, one F9 with the rest P8 or
 *                  better, or two F9 with the rest P7 or better; F otherwise
 */
class UacePrincipalGrade
{
    /** Points each principal grade earns towards the UACE total. */
    public const POINTS = ['A' => 6, 'B' => 5, 'C' => 4, 'D' => 3, 'E' => 2, 'O' => 1, 'F' => 0];

    public const DESCRIPTORS = [
        'A' => 'Principal pass', 'B' => 'Principal pass', 'C' => 'Principal pass', 'D' => 'Principal pass',
        'E' => 'Principal pass', 'O' => 'Subsidiary pass', 'F' => 'Fail',
    ];

    /** A subsidiary subject passes (1 point) with a paper grade of C6 or better. */
    public const SUBSIDIARY_PASS = 6;

    /**
     * @param  list<int>  $papers  paper grades, 1 (D1) to 9 (F9)
     */
    public static function fromPapers(array $papers): ?string
    {
        if ($papers === []) {
            return null;
        }

        $papers = array_map(fn (int $g): int => max(1, min(9, $g)), $papers);

        if (count($papers) === 1) {
            $papers[] = $papers[0];
        }

        sort($papers);

        return count($papers) === 2 ? self::twoPapers($papers[0], $papers[1]) : self::morePapers($papers);
    }

    /** $best <= $worst. */
    protected static function twoPapers(int $best, int $worst): string
    {
        return match (true) {
            $worst <= 2 => 'A',
            $worst <= 6 => ['A', 'A', 'A', 'B', 'C', 'D', 'E'][$worst],
            $worst <= 8 && $best + $worst <= 12 => 'E',
            $worst <= 8 => 'O',
            $best <= 7 => 'O',
            default => 'F',
        };
    }

    /**
     * @param  list<int>  $papers  sorted, best first; three or four papers
     */
    protected static function morePapers(array $papers): string
    {
        $worst = $papers[count($papers) - 1];
        // The other papers (sorted, best first): their best and worst grades.
        $restBest = $papers[0];
        $restWorst = $papers[count($papers) - 2];

        // A: worst C3 with distinctions; B: worst C4 with C3 or better; ... D: worst C6 with C5 or better.
        foreach (['A' => 3, 'B' => 4, 'C' => 5, 'D' => 6] as $grade => $limit) {
            if ($worst <= $limit && $restWorst <= $limit - 1) {
                return $grade;
            }
        }

        if (($worst === 7 && $restWorst <= 6) || ($worst === 8 && $restWorst <= 6 && $restBest <= 5)) {
            return 'E';
        }

        $failures = count(array_filter($papers, fn (int $g): bool => $g === 9));
        $others = array_values(array_filter($papers, fn (int $g): bool => $g !== 9));

        return match (true) {
            $failures === 0 => 'O',
            $failures === 1 => 'O',
            $failures === 2 && $others !== [] && max($others) <= 7 => 'O',
            default => 'F',
        };
    }
}
