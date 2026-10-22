<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // Where website visitors are (Website visitors page): MaxMind's free
    // GeoLite2 City database, kept on this server so visitor addresses
    // never leave it. A free MaxMind account gives the account ID and
    // licence key; `php artisan geoip:update` downloads the database, and
    // the scheduler refreshes it weekly.
    'maxmind' => [
        'account_id' => env('MAXMIND_ACCOUNT_ID'),
        'license_key' => env('MAXMIND_LICENSE_KEY'),
        'database' => env('MAXMIND_DATABASE', storage_path('app/geoip/GeoLite2-City.mmdb')),
    ],

    // MTN MoMo Collections: schools pay for SchoolHub from their phones
    // (Subscription page → Pay now). Keys come from momodeveloper.mtn.com;
    // in the sandbox, `php artisan momo:sandbox-user` makes the API user
    // and key. The sandbox only takes EUR; MTN Uganda live is
    // MOMO_ENVIRONMENT=mtnuganda, MOMO_CURRENCY=UGX and MTN's live address.
    'momo' => [
        'environment' => env('MOMO_ENVIRONMENT', 'sandbox'),
        'base_url' => env('MOMO_BASE_URL', 'https://sandbox.momodeveloper.mtn.com'),
        'subscription_key' => env('MOMO_COLLECTION_KEY'),
        'api_user' => env('MOMO_API_USER'),
        'api_key' => env('MOMO_API_KEY'),
        'currency' => env('MOMO_CURRENCY', 'EUR'),
    ],

];
