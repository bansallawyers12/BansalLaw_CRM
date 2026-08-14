<?php

use Monolog\Handler\StreamHandler;
use Monolog\Handler\SyslogUdpHandler;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Log Channel
    |--------------------------------------------------------------------------
    |
    | This option defines the default log channel that gets used when writing
    | messages to the logs. The name specified in this option should match
    | one of the channels defined in the "channels" configuration array.
    |
    */

    'default' => env('LOG_CHANNEL', 'stack'),

    /*
    | Keep only the last N days of inbox-sync, inbox-sync-run, and
    | email-upload-errors log files. Older dated files are deleted.
    */
    'email_ops_retention_days' => (int) env('EMAIL_OPS_LOG_RETENTION_DAYS', 7),

    /*
    |--------------------------------------------------------------------------
    | Log Channels
    |--------------------------------------------------------------------------
    |
    | Here you may configure the log channels for your application. Out of
    | the box, Laravel uses the Monolog PHP logging library. This gives
    | you a variety of powerful log handlers / formatters to utilize.
    |
    | Available Drivers: "single", "daily", "slack", "syslog",
    |                    "errorlog", "monolog",
    |                    "custom", "stack"
    |
    */

    'channels' => [
        'stack' => [
            'driver' => 'stack',
            'channels' => ['daily'],
        ],

        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/laravel.log'),
            'level' => 'debug',
            'permission' => 0644,
            'tap' => [App\Logging\Utf8LogFormatter::class],
        ],

        'daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/laravel.log'),
            'level' => 'debug',
            'days' => 7,
            'permission' => 0644,
            'tap' => [App\Logging\Utf8LogFormatter::class],
        ],

        'slack' => [
            'driver' => 'slack',
            'url' => env('LOG_SLACK_WEBHOOK_URL'),
            'username' => 'Laravel Log',
            'emoji' => ':boom:',
            'level' => 'critical',
        ],

        'papertrail' => [
            'driver'  => 'monolog',
            'level' => 'debug',
            'handler' => SyslogUdpHandler::class,
            'handler_with' => [
                'host' => env('PAPERTRAIL_URL'),
                'port' => env('PAPERTRAIL_PORT'),
            ],
        ],

        'stderr' => [
            'driver' => 'monolog',
            'handler' => StreamHandler::class,
            'with' => [
                'stream' => 'php://stderr',
            ],
        ],

        'syslog' => [
            'driver' => 'syslog',
            'level' => 'debug',
        ],

        'errorlog' => [
            'driver' => 'errorlog',
            'level' => 'debug',
        ],

        'email_upload' => [
            'driver' => 'daily',
            'path' => storage_path('logs/email-upload-errors.log'),
            'level' => 'error',
            'days' => (int) env('EMAIL_OPS_LOG_RETENTION_DAYS', 7),
            'permission' => 0644,
            'tap' => [App\Logging\Utf8LogFormatter::class],
        ],

        'inbox_sync' => [
            'driver' => 'daily',
            'path' => storage_path('logs/inbox-sync/inbox-sync.log'),
            'level' => 'debug',
            'days' => (int) env('EMAIL_OPS_LOG_RETENTION_DAYS', 7),
            'permission' => 0644,
            'tap' => [App\Logging\Utf8LogFormatter::class],
        ],

        'inbox_sync_runs' => [
            'driver' => 'daily',
            'path' => storage_path('logs/inbox-sync-runs/inbox-sync-run.log'),
            'level' => 'info',
            'days' => (int) env('EMAIL_OPS_LOG_RETENTION_DAYS', 7),
            'permission' => 0644,
            'tap' => [App\Logging\Utf8LogFormatter::class],
        ],

        'migration_legal_crm' => [
            'driver' => 'daily',
            'path' => storage_path('logs/migration-legal-crm.log'),
            'level' => 'debug',
            'days' => 30,
            'permission' => 0644,
            'tap' => [App\Logging\Utf8LogFormatter::class],
        ],

    ],

];
