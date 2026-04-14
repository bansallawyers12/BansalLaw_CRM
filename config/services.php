<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'sendgrid' => [
        'api_key' => env('SENDGRID_API_KEY'),
        'base_url' => env('SENDGRID_BASE_URL', 'https://api.sendgrid.com'),
        'from_email' => env('SENDGRID_FROM_EMAIL', ''),
        'senders' => env('SENDGRID_SENDERS', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Python Services Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the unified Python services that handle PDF processing,
    | email parsing, analysis, and rendering.
    |
    */

    'python' => [
        'url' => env('PYTHON_SERVICE_URL', 'http://localhost:5001'),
        'timeout' => env('PYTHON_SERVICE_TIMEOUT', 120),
        'max_retries' => env('PYTHON_SERVICE_MAX_RETRIES', 3),
        'health_check_interval' => env('PYTHON_SERVICE_HEALTH_CHECK_INTERVAL', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | PDF Converter Services
    |--------------------------------------------------------------------------
    |
    | Configuration for PDF conversion services (legacy support)
    |
    */

    'python_pdf' => [
        'url' => env('PYTHON_PDF_SERVICE_URL', 'http://127.0.0.1:5001'),
        'timeout' => env('PYTHON_PDF_SERVICE_TIMEOUT', 60),
    ],

    'python_converter' => [
        // Falls back to PYTHON_SERVICE_URL so one .env line suffices when both use the same process
        'url' => env('PYTHON_CONVERTER_URL') ?: env('PYTHON_SERVICE_URL', 'http://localhost:5001'),
        'timeout' => env('PYTHON_CONVERTER_TIMEOUT', 120),
    ],

    /*
    |--------------------------------------------------------------------------
    | OpenAI Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for OpenAI API integration
    |
    */

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'organization' => env('OPENAI_ORGANIZATION'),
        'timeout' => env('OPENAI_TIMEOUT', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Google reCAPTCHA Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Google reCAPTCHA integration on login forms
    |
    */

    'recaptcha' => [
        'key' => env('RECAPTCHA_SITE_KEY'),
        'secret' => env('RECAPTCHA_SITE_SECRET'),
    ],

    /*
    |--------------------------------------------------------------------------
    | SMS Service Providers Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for SMS service providers:
    | - Cellcast: Used for Australian numbers (+61)
    | - Twilio: Used for international numbers (including India +91)
    |
    */

    'cellcast' => [
        'api_key' => env('CELLCAST_API_KEY'),
        'base_url' => env('CELLCAST_BASE_URL', 'https://api.cellcast.com.au/v1'),
        'sender_id' => env('CELLCAST_SENDER_ID', ''),
        'timeout' => env('CELLCAST_TIMEOUT', 30),
    ],

    'twilio' => [
        'account_sid' => env('TWILIO_SID'),
        'auth_token' => env('TWILIO_TOKEN'),
        'from' => env('TWILIO_FROM'),
        'timeout' => env('TWILIO_TIMEOUT', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Bansal website CRM API (appointments / public booking sync)
    |--------------------------------------------------------------------------
    |
    | Set these in the environment (e.g. server env or secrets manager), not
    | necessarily in .env, if you prefer to keep secrets out of files.
    |
    | - BANSAL_API_BASE_URL or BANSAL_API_URL: API root (no trailing slash).
    |   Default below targets production; override for local (e.g. http://localhost/api/crm).
    | - BANSAL_API_TOKEN: Bearer token (required for any feature calling this API).
    | - BANSAL_API_TIMEOUT: HTTP timeout in seconds (default 30).
    |
    | Reconnecting: set the variables above on this CRM (e.g. production .env or server env).
    | Issue the token on the website side for the /api/crm routes this app calls. Then run:
    |   php artisan booking:test-api --show-config
    |
    */

    'bansal_api' => [
        'url' => rtrim(env('BANSAL_API_BASE_URL', env('BANSAL_API_URL', 'https://www.bansallawyers.com.au/api/crm')), '/'),
        'token' => env('BANSAL_API_TOKEN'),
        'timeout' => (int) env('BANSAL_API_TIMEOUT', 30),
    ],

    'appointment_api' => [
        'url' => rtrim(env('APPOINTMENT_API_URL', 'https://www.bansallawyers.com.au/api'), '/'),
        'service_token' => env('APPOINTMENT_API_SERVICE_TOKEN'),
        /** When set, used as Bearer token instead of /service-account/authenticate. */
        'bearer_token' => env('APPOINTMENT_API_BEARER_TOKEN'),
        'timeout' => (int) env('APPOINTMENT_API_TIMEOUT', 120),
    ],

    /*
    |--------------------------------------------------------------------------
    | Stripe Payment Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Stripe payment processing
    | Get your keys from: https://dashboard.stripe.com/apikeys
    |
    */

    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Firebase Cloud Messaging (FCM) Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Firebase Cloud Messaging push notifications (HTTP v1 API)
    | 
    | To set up:
    | 1. Go to Firebase Console > Project Settings > Cloud Messaging
    | 2. Click "Manage Service Accounts"
    | 3. Create or select a service account
    | 4. Generate a new private key (JSON file)
    | 5. Save the JSON file to storage/app/firebase-service-account.json
    | 6. Set FCM_SERVICE_ACCOUNT_PATH in .env (relative to storage/app/)
    |
    */

    'fcm' => [
        'service_account_path' => env('FCM_SERVICE_ACCOUNT_PATH', 'firebase-service-account.json'),
    ],

];