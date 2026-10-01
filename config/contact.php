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

    // Who builds and supports SchoolHub.
    'company' => env('CONTACT_COMPANY', 'DualHub Technologies'),

    // Where the team is and when it answers: on the Contact page and in the
    // footer, and in the details Google reads. Keep these the same as the
    // Google Business Profile so Google matches the two.
    'location' => env('CONTACT_LOCATION', 'Wakiso, Uganda'),
    'locality' => env('CONTACT_LOCALITY', 'Wakiso'),
    'hours' => env('CONTACT_HOURS', 'Monday to Friday, 8:00 am – 6:00 pm'),
    'opens' => env('CONTACT_OPENS', '08:00'),
    'closes' => env('CONTACT_CLOSES', '18:00'),
    'days' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'],
];
