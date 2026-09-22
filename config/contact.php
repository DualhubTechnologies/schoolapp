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
];
