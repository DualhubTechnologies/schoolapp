<?php

/*
 * SMS for fee reminders.
 *
 *   SMS_DRIVER=log            (default) messages are written to the log and
 *                             recorded as sent -- safe for testing
 *   SMS_DRIVER=africastalking send through Africa's Talking, which reaches
 *                             MTN and Airtel numbers in Uganda
 *
 * For Africa's Talking, create an app at africastalking.com and set:
 *   AFRICASTALKING_USERNAME   your app username ("sandbox" while testing)
 *   AFRICASTALKING_API_KEY    the app's API key
 *   AFRICASTALKING_SENDER_ID  an approved sender ID / short code (optional)
 */

return [

    'driver' => env('SMS_DRIVER', 'log'),

    'country_code' => env('SMS_COUNTRY_CODE', '256'),

    'africastalking' => [
        'username' => env('AFRICASTALKING_USERNAME', 'sandbox'),
        'api_key' => env('AFRICASTALKING_API_KEY'),
        'sender_id' => env('AFRICASTALKING_SENDER_ID'),
        'endpoint' => env('AFRICASTALKING_USERNAME', 'sandbox') === 'sandbox'
            ? 'https://api.sandbox.africastalking.com/version1/messaging'
            : 'https://api.africastalking.com/version1/messaging',
    ],

];
