<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Reads a date typed into an import spreadsheet. Schools write dates day
 * first (DD-MM-YYYY), and Excel saves them in whatever shape the
 * computer's settings choose, so this accepts:
 *
 *   14-03-2012  14/03/2012  14.03.2012  4-3-2012  14-03-12   day first
 *   2012-03-14  2012/03/14                                   year first
 *   14-Mar-2012  14 March 2012  Mar 14, 2012                 month by name
 *   40982                                                     Excel day number
 *
 * Day-first is always tried before month-first; 03/14/2012 is read
 * month-first only because 14 cannot be a month. A date that does not
 * exist (31-02-2012) is refused rather than rolled over.
 */
class ImportDate
{
    /** What to tell the school when a date cannot be read. */
    public const HINT = 'Use DD-MM-YYYY, e.g. 14-03-2012.';

    /** The date as Y-m-d, or null when it is blank or cannot be read. */
    public static function parse(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        // Excel's day number, when a date cell is saved as a plain number.
        if (preg_match('/^\d{4,5}$/', $value) && (int) $value > 3000) {
            return CarbonImmutable::create(1899, 12, 30)->addDays((int) $value)->format('Y-m-d');
        }

        // Numbers only: d-m-y (any of - / . or space between).
        if (preg_match('/^(\d{1,4})[\-\/\.\s](\d{1,2})[\-\/\.\s](\d{1,4})$/', $value, $m)) {
            [$a, $b, $c] = [(int) $m[1], (int) $m[2], (int) $m[3]];

            if (strlen($m[1]) === 4) {
                return self::make($a, $b, $c); // 2012-03-14
            }

            $year = strlen($m[3]) === 2 ? self::fullYear($c) : $c;

            if (strlen($m[3]) !== 2 && strlen($m[3]) !== 4) {
                return null;
            }

            return self::make($year, $b, $a)     // 14-03-2012, day first
                ?? ($b > 12 ? self::make($year, $a, $b) : null); // 03/14/2012
        }

        // Month by name: 14-Mar-2012, 14 March 2012, Mar 14, 2012.
        if (preg_match('/[a-z]/i', $value)) {
            foreach (['j-M-Y', 'j-M-y', 'j M Y', 'j F Y', 'M j, Y', 'F j, Y', 'j-F-Y', 'j M y'] as $format) {
                $date = \DateTime::createFromFormat('!'.$format, $value);
                $errors = \DateTime::getLastErrors();

                if ($date && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                    return $date->format('Y-m-d');
                }
            }
        }

        return null;
    }

    /** A real calendar date, or null (no 31 February). */
    protected static function make(int $year, int $month, int $day): ?string
    {
        return $year >= 1900 && $year <= 2100 && checkdate($month, $day, $year)
            ? sprintf('%04d-%02d-%02d', $year, $month, $day)
            : null;
    }

    /** 12 -> 2012, 85 -> 1985: anything past this year is last century. */
    protected static function fullYear(int $twoDigits): int
    {
        $year = 2000 + $twoDigits;

        return $year > (int) now()->format('Y') ? $year - 100 : $year;
    }
}
