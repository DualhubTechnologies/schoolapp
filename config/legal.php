<?php

/*
 * SchoolHub's terms and conditions (resources/views/legal/terms.blade.php).
 *
 * When the terms change, update the text AND bump "terms_version" to the
 * new date: each school records the version it accepted when it
 * registered (schools.terms_version), so you can tell who agreed to what.
 */
return [

    'terms_version' => '2026-10-06',

    // How long a locked (unpaid) school's data is kept before it may be deleted.
    'locked_retention_months' => 12,

    // How long after a written request a school's data is deleted.
    'deletion_days' => 30,

    // Notice given before prices or these terms change.
    'notice_days' => 30,
];
