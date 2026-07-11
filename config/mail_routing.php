<?php

return [

    /*
    |--------------------------------------------------------------------------
    | System mailer (AWS SES)
    |--------------------------------------------------------------------------
    |
    | Used for automated / no-reply emails: verification, appointments,
    | hubdoc, signatures, invoices, etc.
    |
    */
    'system_mailer' => env('CRM_SYSTEM_MAILER', 'ses'),

    /*
    |--------------------------------------------------------------------------
    | Personal mailer (Zoho SMTP)
    |--------------------------------------------------------------------------
    |
    | Used when staff send from their own address (Michael, admin, ajay, etc.)
    |
    */
    'personal_mailer' => env('CRM_PERSONAL_MAILER', 'zoho'),

    /*
    |--------------------------------------------------------------------------
    | From-address patterns that always use the system mailer
    |--------------------------------------------------------------------------
    */
    'system_from_patterns' => [
        'noreply@',
        'no-reply@',
        'donotreply@',
        'do-not-reply@',
        'notifications@',
        'mailer-daemon@',
    ],

    /*
    |--------------------------------------------------------------------------
    | Explicit system from addresses (exact match, case-insensitive)
    |--------------------------------------------------------------------------
    */
    'system_from_addresses' => array_values(array_filter(array_map(
        'trim',
        explode(',', env('CRM_SYSTEM_FROM_ADDRESSES', ''))
    ))),

];
