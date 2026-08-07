<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Zoho Calendar sync (CRM ↔ Zoho Mail calendar / Outlook-via-Zoho)
    |--------------------------------------------------------------------------
    | Default OFF until Super Admin enables the master switch. Local-only setup
    | until you deliberately deploy.
    */
    'enabled' => env('ZOHO_CALENDAR_SYNC_ENABLED', false),

    'accounts_url' => rtrim((string) env('ZOHO_CALENDAR_ACCOUNTS_URL', 'https://accounts.zoho.com.au'), '/'),

    'calendar_api_url' => rtrim((string) env('ZOHO_CALENDAR_API_URL', 'https://calendar.zoho.com.au/api/v1'), '/'),

    'client_id' => env('ZOHO_CALENDAR_CLIENT_ID', ''),

    'client_secret' => env('ZOHO_CALENDAR_CLIENT_SECRET', ''),

    'redirect_uri' => env('ZOHO_CALENDAR_REDIRECT_URI', env('APP_URL', 'http://127.0.0.1:8001') . '/adminconsole/features/calendar-sync/callback'),

    /*
    | Scopes for Zoho Calendar. Adjust if your Zoho data centre uses different names.
    */
    'scopes' => env('ZOHO_CALENDAR_SCOPES', 'ZohoCalendar.calendar.ALL,ZohoCalendar.event.ALL'),

    'timezone' => env('ZOHO_CALENDAR_TIMEZONE', env('APP_TIMEZONE', 'Australia/Melbourne')),

    /*
    | Zoho → CRM pull window (days around today). Used by calendar:sync-from-zoho.
    */
    'pull_days_back' => (int) env('ZOHO_CALENDAR_PULL_DAYS_BACK', 7),

    'pull_days_forward' => (int) env('ZOHO_CALENDAR_PULL_DAYS_FORWARD', 60),

    /*
    | Schedule interval for automatic Zoho pull (minutes). 0 = disabled in cron.
    */
    'schedule_minutes' => (int) env('ZOHO_CALENDAR_SCHEDULE_MINUTES', 15),
];
