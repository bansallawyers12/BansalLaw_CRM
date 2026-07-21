<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Zoho IMAP defaults (incoming mail sync)
    |--------------------------------------------------------------------------
    */
    'default_host' => env('ZOHO_IMAP_HOST', 'imappro.zoho.com'),
    'default_port' => (int) env('ZOHO_IMAP_PORT', 993),
    'default_encryption' => env('ZOHO_IMAP_ENCRYPTION', 'ssl'),
    'validate_cert' => env('ZOHO_IMAP_VALIDATE_CERT', true),

    /*
    |--------------------------------------------------------------------------
    | Sync behaviour
    |--------------------------------------------------------------------------
    */
    'enabled' => env('MAIL_INBOX_SYNC_ENABLED', true),

    'max_messages_per_mailbox' => (int) env('MAIL_SYNC_MAX_MESSAGES', 25),

    'folders' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('MAIL_SYNC_FOLDERS', 'INBOX'))
    ))),

    'sent_folders' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('MAIL_SYNC_SENT_FOLDERS', 'Sent'))
    ))),

    'initial_backfill_multiplier' => (int) env('MAIL_SYNC_INITIAL_BACKFILL_MULTIPLIER', 4),

    'high_confidence_threshold' => (int) env('MAIL_SYNC_HIGH_CONFIDENCE', 80),

    'schedule_minutes' => (int) env('MAIL_SYNC_SCHEDULE_MINUTES', 5),

    'unassigned_storage_prefix' => 'sync-inbox',

    'queue' => env('MAIL_INBOX_SYNC_QUEUE', 'default'),

];
