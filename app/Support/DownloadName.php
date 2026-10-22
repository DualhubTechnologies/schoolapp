<?php

namespace App\Support;

/**
 * A file name for a download built from school data. Admission and staff
 * numbers are often written "DHS/2026/0014", and a "/" or "\" is not
 * allowed in a download's name, so those become "-".
 */
class DownloadName
{
    public static function make(string $name): string
    {
        $name = str_replace(['/', '\\'], '-', $name);

        return trim((string) preg_replace('/[\x00-\x1F"]+/', '', $name)) ?: 'download';
    }
}
