<?php

/*
 * How schools reach the SchoolHub team: shown on the landing page (demo
 * booking, WhatsApp, call, email). Demo requests are emailed to "email".
 */
return [

    // Local format, as people will dial it.
    'phone' => env('CONTACT_PHONE', '0782 863209'),

    // International format without "+" or spaces, for wa.me links.
    'whatsapp' => env('CONTACT_WHATSAPP', '256782863209'),

    'email' => env('CONTACT_EMAIL', 'dualhubtechnologies@gmail.com'),

    // Who builds and supports SchoolHub, and who founded it: on the About
    // page's team section, the footer and the terms.
    'company' => env('CONTACT_COMPANY', 'FERO TECH SMC LIMITED'),
    'founder' => env('CONTACT_FOUNDER', 'Adrian Mugizi'),

    // The Team page, in order. 'photo' is a file in public/images/team, e.g. a background-free WebP
    // (square, at least 400px), or null for a silhouette. Social links and
    // email are optional; each one shows as an icon.
    'team' => [
        [
            'name' => env('CONTACT_FOUNDER', 'Adrian Mugizi'),
            'role' => 'Founder, FERO TECH SMC LIMITED & SchoolHub.',
            'photo' => 'adrian-mugizi.webp',
            'linkedin' => 'https://www.linkedin.com/in/adrian-mugizi-1a0854152',
            'facebook' => null,
            'x' => null,
            'email' => env('CONTACT_EMAIL', 'dualhubtechnologies@gmail.com'),
        ],
    ],

    // Where the team is and when it answers: on the Contact page and in the
    // footer, and in the details Google reads. Keep these the same as the
    // Google Business Profile so Google matches the two.
    'location' => env('CONTACT_LOCATION', 'Wakiso, Uganda'),
    'locality' => env('CONTACT_LOCALITY', 'Wakiso'),
    'hours' => env('CONTACT_HOURS', 'Monday to Friday, 8:00 am – 6:00 pm'),
    'opens' => env('CONTACT_OPENS', '08:00'),
    'closes' => env('CONTACT_CLOSES', '18:00'),
    'days' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'],

    // The Windows app installer: the newest GitHub release, so the website
    // never needs changing for a new version (desktop/build-windows.ps1).
    'windows_download' => env('WINDOWS_DOWNLOAD_URL', 'https://github.com/DualhubTechnologies/schoolapp/releases/latest/download/SchoolHub-Setup.exe'),

    // Whether the website offers the Windows app at all. Off while the next
    // version (short licence keys) is prepared; WINDOWS_DOWNLOAD_ENABLED=true
    // in .env, or true here, shows the section and links again.
    'windows_download_enabled' => (bool) env('WINDOWS_DOWNLOAD_ENABLED', false),
];
