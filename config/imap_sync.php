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

    'range_sync_multiplier' => (int) env('MAIL_SYNC_RANGE_MULTIPLIER', 4),

    'max_range_sync_batches' => (int) env('MAIL_SYNC_MAX_RANGE_BATCHES', 8),

    'max_incremental_batches' => (int) env('MAIL_SYNC_MAX_INCREMENTAL_BATCHES', 6),

    'folders' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('MAIL_SYNC_FOLDERS', 'INBOX'))
    ))),

    'sent_folders' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('MAIL_SYNC_SENT_FOLDERS', 'Sent'))
    ))),

    'initial_backfill_multiplier' => (int) env('MAIL_SYNC_INITIAL_BACKFILL_MULTIPLIER', 4),

    /*
    |--------------------------------------------------------------------------
    | Automatic catch-up (no manual Full sync needed after parser downtime)
    |--------------------------------------------------------------------------
    */
    'auto_catchup_enabled' => env('MAIL_SYNC_AUTO_CATCHUP_ENABLED', true),

    'auto_catchup_days' => (int) env('MAIL_SYNC_AUTO_CATCHUP_DAYS', 7),

    'auto_catchup_interval_hours' => (int) env('MAIL_SYNC_AUTO_CATCHUP_INTERVAL_HOURS', 6),

    'high_confidence_threshold' => (int) env('MAIL_SYNC_HIGH_CONFIDENCE', 80),

    'schedule_minutes' => (int) env('MAIL_SYNC_SCHEDULE_MINUTES', 5),

    'unassigned_storage_prefix' => 'sync-inbox',

    'queue' => env('MAIL_INBOX_SYNC_QUEUE', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Preserve unread state in Zoho / Outlook during sync
    |--------------------------------------------------------------------------
    | When true, CRM uses IMAP BODY.PEEK so fetching mail for import does not
    | set the \Seen flag on the mailbox. Read/unread in the CRM is tracked
    | separately via email_logs.mail_is_read.
    */
    'use_peek_fetch' => env('MAIL_SYNC_USE_PEEK_FETCH', true),

];
