<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Reverb messaging lab — optional env-based auto login
    |--------------------------------------------------------------------------
    |
    | When set, GET/POST /reverb-messaging-test* can sign in as this staff user
    | without using the login form. Leave empty in production unless you
    | explicitly need this (credentials stay in .env only, not in code).
    |
    */

    'access_login' => env('REVERB_ACCESS_LOGIN'),
    'access_password' => env('REVERB_ACCESS_PASSWORD'),

];
