<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Email Sync Configuration (Cross-Platform)
    |--------------------------------------------------------------------------
    |
    | Configuration options for email synchronization performance and limits
    | that work on both Windows and Linux systems.
    |
    */

    // Maximum execution time for sync operations (in seconds)
    'sync_timeout' => env('MAIL_SYNC_TIMEOUT', 900), // 15 minutes

    // Process timeout for Python scripts (in seconds) - Optimized for robust connection handling
    'process_timeout' => env('MAIL_PROCESS_TIMEOUT', 600), // 10 minutes for robust processing

    // Maximum emails to sync per request - Optimized for fresh connections
    'max_sync_limit' => env('MAIL_MAX_SYNC_LIMIT', 10),

    // Maximum days to sync in one request - Allows 5 days as requested
    'max_sync_days' => env('MAIL_MAX_SYNC_DAYS', 5),

    // Batch size for database operations - 10 emails per batch with fresh connections
    'batch_size' => env('MAIL_BATCH_SIZE', 10),

    // Memory limit for sync operations
    'memory_limit' => env('MAIL_MEMORY_LIMIT', '1G'),

    // Enable/disable attachment processing
    'process_attachments' => env('MAIL_PROCESS_ATTACHMENTS', true),

    // Maximum attachments per email
    'max_attachments_per_email' => env('MAIL_MAX_ATTACHMENTS_PER_EMAIL', 5),

    // Maximum attachment size (in bytes)
    'max_attachment_size' => env('MAIL_MAX_ATTACHMENT_SIZE', 5242880), // 5MB

    // Enable/disable S3 uploads
    'enable_s3_upload' => env('MAIL_ENABLE_S3_UPLOAD', true),

    // Enable/disable local file storage
    'enable_local_storage' => env('MAIL_ENABLE_LOCAL_STORAGE', true),

    // Enable/disable email content file storage
    'enable_content_storage' => env('MAIL_ENABLE_CONTENT_STORAGE', true),

    // Enable/disable debug logging
    'enable_debug_logging' => env('MAIL_ENABLE_DEBUG_LOGGING', false),

    // Robust connection settings
    'connection_strategy' => env('MAIL_CONNECTION_STRATEGY', 'fresh_per_batch'),
    'connection_timeout' => env('MAIL_CONNECTION_TIMEOUT', 30),
    'connection_retries' => env('MAIL_CONNECTION_RETRIES', 3),
    'connection_retry_delay' => env('MAIL_CONNECTION_RETRY_DELAY', 2),

    /*
    |--------------------------------------------------------------------------
    | Cross-Platform Python Configuration
    |--------------------------------------------------------------------------
    |
    | These settings control where Python scripts are located and executed.
    | Change the folder name directly in the config file for different projects.
    |
    */

    // Python executable path (auto-detected based on OS)
    'python_executable' => env('MAIL_PYTHON_EXECUTABLE', '/home/migratio/public_html/bansal_immigration/python_outlook_web/venv/bin/python'),

    // Python script directory (change folder name here for different projects)
    'python_script_dir' => base_path('python_outlook_web'),

    // Python runner script (auto-detected based on OS)
    'python_runner_script' => env('MAIL_PYTHON_RUNNER_SCRIPT', null),

    // Auto-detect Python executable based on environment
    'auto_detect_python' => env('MAIL_AUTO_DETECT_PYTHON', true),

    // Web server user (for file permissions on Linux)
    'web_server_user' => env('MAIL_WEB_SERVER_USER', 'www-data'),

    // Web server group (for file permissions on Linux)
    'web_server_group' => env('MAIL_WEB_SERVER_GROUP', 'www-data'),

    // Storage directories
    'storage_directories' => [
        'emails' => storage_path('app/emails'),
        'attachments' => storage_path('app/attachments'),
        'temp' => storage_path('app/temp'),
        'scripts' => storage_path('app/scripts'),
    ],

    // Environment variables for Python processes
    'python_env_vars' => [
        'PATH' => env('PATH', '/usr/local/bin:/usr/bin:/bin'),
        'PYTHONPATH' => env('PYTHONPATH', ''),
        'PYTHONIOENCODING' => 'utf-8',
        'LANG' => env('LANG', 'en_US.UTF-8'),
        'LC_ALL' => env('LC_ALL', 'en_US.UTF-8'),
    ],

    // SSL/TLS configuration
    'ssl_config' => [
        'verify_peer' => env('MAIL_SSL_VERIFY_PEER', true),
        'verify_peer_name' => env('MAIL_SSL_VERIFY_PEER_NAME', true),
        'allow_self_signed' => env('MAIL_SSL_ALLOW_SELF_SIGNED', false),
        'cafile' => env('MAIL_SSL_CAFILE', null),
    ],

    /*
    |--------------------------------------------------------------------------
    | OS-Specific Defaults
    |--------------------------------------------------------------------------
    */

    // Windows-specific defaults
    'windows' => [
        'python_executable' => 'venv/Scripts/python.exe',
        'python_runner_script' => 'run_python.bat',
    ],

    // Linux/Unix-specific defaults
    'linux' => [
        'python_executable' => 'venv/bin/python',
        'python_runner_script' => 'run_python.sh',
    ],
];