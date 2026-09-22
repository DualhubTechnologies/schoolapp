<?php

/*
 * SchoolHub subscriptions: every feature is available on every plan;
 * plans differ only by how many students and staff logins a school has.
 */
return [

    // A new school starts on the trial plan for this many days.
    'trial_days' => (int) env('SUBSCRIPTION_TRIAL_DAYS', 30),

    // Length of each billing cycle. Ugandan schools budget by term.
    'cycle_months' => [
        'term' => 4,
        'year' => 12,
    ],

    // Warn the school this many days before the period ends.
    'warn_days' => 14,

    // After the end date the school keeps working (with a red banner) for
    // this many days, then is locked until payment is recorded. Nothing
    // is ever deleted.
    'grace_days' => 14,

    // Warn when usage reaches this share of the plan's limit.
    'usage_warning' => 0.9,

    // Reminders before a trial or subscription ends (subscriptions:remind,
    // run daily). Days before the end date; 0 = the last day. Each is sent
    // once per end date, so renewing starts a fresh set.
    'reminder_days' => [14, 7, 3, 1, 0],

    // After the end date: when grace starts, this many days before the
    // school locks, and on the day it locks.
    'lock_warning_days' => 3,

    // Reminders this close to (or past) the end also go by SMS to the
    // school's phone. Earlier ones are email only, to keep SMS costs down.
    'sms_within_days' => 3,

    // Local time the daily reminder run happens.
    'reminder_time' => env('SUBSCRIPTION_REMINDER_TIME', '08:00'),
    'reminder_timezone' => 'Africa/Kampala',

    // Logins with only these roles do not count towards the user limit
    // (parents and students checking results should never cost a school).
    'free_roles' => ['Parent', 'Student'],

    // Shown to school administrators on the Subscription page.
    'payment' => [
        'mobile_money' => env('SUBSCRIPTION_MOMO', ''),        // e.g. "MTN 0772 000000 (SchoolHub Ltd)"
        'airtel_money' => env('SUBSCRIPTION_AIRTEL', ''),
        'bank' => env('SUBSCRIPTION_BANK', ''),                // e.g. "Stanbic, A/C 9030000000000, SchoolHub Ltd"
        'contact_phone' => env('SUBSCRIPTION_CONTACT_PHONE', ''),
        'contact_email' => env('SUBSCRIPTION_CONTACT_EMAIL', ''),
    ],
];
