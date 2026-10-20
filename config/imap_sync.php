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

    // Conservative defaults to avoid CLI memory exhaustion on large mailboxes (override via .env).
    'max_messages_per_mailbox' => (int) env('MAIL_SYNC_MAX_MESSAGES', 10),

    'range_sync_multiplier' => (int) env('MAIL_SYNC_RANGE_MULTIPLIER', 2),

    'max_range_sync_batches' => (int) env('MAIL_SYNC_MAX_RANGE_BATCHES', 8),

    'max_incremental_batches' => (int) env('MAIL_SYNC_MAX_INCREMENTAL_BATCHES', 3),

    /*
    |--------------------------------------------------------------------------
    | Empty IMAP response (Webklex "empty response")
    |--------------------------------------------------------------------------
    | Retry only this transient FETCH/SEARCH failure, then treat the page as
    | empty. The UID cursor is not advanced. Other IMAP errors still fail the
    | folder for this run.
    */
    'empty_response_retries' => (int) env('MAIL_SYNC_EMPTY_RESPONSE_RETRIES', 2),

    'empty_response_retry_sleep_ms' => (int) env('MAIL_SYNC_EMPTY_RESPONSE_RETRY_SLEEP_MS', 500),

    /*
    |--------------------------------------------------------------------------
    | IMAP connection failed (Webklex ConnectionFailedException)
    |--------------------------------------------------------------------------
    | Retry transient connect/handshake failures during paging. If still failing,
    | the folder sync stops without advancing the UID cursor.
    */
    'connection_failed_retries' => (int) env('MAIL_SYNC_CONNECTION_FAILED_RETRIES', 2),

    'connection_failed_retry_sleep_ms' => (int) env('MAIL_SYNC_CONNECTION_FAILED_RETRY_SLEEP_MS', 1000),

    'folders' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('MAIL_SYNC_FOLDERS', 'INBOX'))
    ))),

    'sent_folders' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env(
            'MAIL_SYNC_SENT_FOLDERS',
            'Sent,Sent Items,Sent Messages,Sent Mail,INBOX.Sent,INBOX/Sent'
        ))
    ))),

    /*
    |--------------------------------------------------------------------------
    | Always sync Zoho Sent folder
    |--------------------------------------------------------------------------
    | When true, every mailbox sync also fetches Sent (even if the mailbox
    | checkbox sync_sent_enabled is off). Sent Mail UI depends on this.
    */
    'always_sync_sent' => filter_var(env('MAIL_SYNC_ALWAYS_SYNC_SENT', true), FILTER_VALIDATE_BOOLEAN),

    'initial_backfill_multiplier' => (int) env('MAIL_SYNC_INITIAL_BACKFILL_MULTIPLIER', 2),

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

    /*
    |--------------------------------------------------------------------------
    | Skip body fetch for messages already in email_logs (UID / Message-ID)
    |--------------------------------------------------------------------------
    | Uses IMAP headers-only reads before downloading .eml bodies. Disable only
    | when diagnosing suspected missed mail.
    */
    'prefetch_known_duplicates' => filter_var(env('MAIL_SYNC_PREFETCH_KNOWN_DUPLICATES', true), FILTER_VALIDATE_BOOLEAN),

    /*
    |--------------------------------------------------------------------------
    | "Today" manual/CLI sync start time (app timezone)
    |--------------------------------------------------------------------------
    | HH:MM wall-clock time. Emails before this time today are not fetched
    | when the "today" range or --today flag is used.
    */
    'today_sync_start' => env('MAIL_SYNC_TODAY_START', '09:00'),

    /*
    |--------------------------------------------------------------------------
    | Unassigned synced mail availability floor (app timezone)
    |--------------------------------------------------------------------------
    | Unassigned Zoho-synced mail before this calendar date is hidden from
    | Unassigned Mail and removed when the "From 10 Aug" sync option runs.
    | Use Y-m-d (e.g. 2026-08-10). Empty disables the floor.
    | Applies to incoming/unassigned lists — not Sent sync (see sent_available_from).
    */
    'unassigned_available_from' => env('MAIL_SYNC_UNASSIGNED_AVAILABLE_FROM', '2026-08-10'),

    /*
    |--------------------------------------------------------------------------
    | Sent-folder sync availability floor (app timezone)
    |--------------------------------------------------------------------------
    | Zoho Sent sync never fetches mail older than this date. Incoming/INBOX
    | sync is unaffected. Use Y-m-d (e.g. 2026-08-12). Empty disables the floor.
    */
    'sent_available_from' => env('MAIL_SYNC_SENT_AVAILABLE_FROM', '2026-08-12'),

    'unassigned_storage_prefix' => 'sync-inbox',

    'queue' => env('MAIL_INBOX_SYNC_QUEUE', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Preserve unread state in Zoho / Outlook during sync
    |--------------------------------------------------------------------------
    | When true, CRM fetches message bodies with IMAP BODY.PEEK[] so importing
    | mail does not set the \Seen flag. Outlook then keeps new Zoho mail unread.
    | Read/unread inside the CRM is tracked separately via email_logs.mail_is_read.
    */
    'use_peek_fetch' => env('MAIL_SYNC_USE_PEEK_FETCH', true),

    /*
    |--------------------------------------------------------------------------
    | Python parser optimisation for IMAP import
    |--------------------------------------------------------------------------
    | metadata_first: cheap /email/parse for matching + duplicate detection;
    |                 full parse-render-pdf only when the message is actually imported.
    | full: always parse-render-pdf once (legacy behaviour, still single call).
    */
    'python_parse_mode' => env('MAIL_SYNC_PYTHON_PARSE_MODE', 'metadata_first'),

    // Skip /email/analyze HTTP call during IMAP sync (analysis can be backfilled later).
    'skip_python_analysis' => env('MAIL_SYNC_SKIP_PYTHON_ANALYSIS', true),

    /*
    |--------------------------------------------------------------------------
    | Excluded mailboxes (never sync Inbox or Sent from Zoho)
    |--------------------------------------------------------------------------
    | Comma-separated addresses. Matching CRM mailboxes are skipped by cron,
    | manual sync, and read-state sync for both INBOX and Sent folders.
    */
    'excluded_mailboxes' => array_values(array_unique(array_filter(array_map(
        static fn ($address) => strtolower(trim((string) $address)),
        explode(',', (string) env(
            'MAIL_SYNC_EXCLUDED_MAILBOXES',
            'michaelsaleh.bi@outlook.com'
        ))
    )))),

];
