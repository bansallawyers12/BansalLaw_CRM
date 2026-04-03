<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    |
    | This option controls the default authentication "guard" and password
    | reset options for your application. You may change these defaults
    | as required, but they're a perfect start for most applications.
    |
    */

    'defaults' => [
        'guard' => 'admin',
        'passwords' => 'staff',  // Staff CRM password resets
    ],
	'admins' => [
        'driver' => 'eloquent',
        'model' => App\Models\Admin::class,
    ],
	'staff' => [
        'driver' => 'eloquent',
        'model' => App\Models\Staff::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    |
    | Next, you may define every authentication guard for your application.
    | Of course, a great default configuration has been defined for you
    | here which uses session storage and the Eloquent user provider.
    |
    | All authentication drivers have a user provider. This defines how the
    | users are actually retrieved out of your database or other storage
    | mechanisms used by this application to persist your user's data.
    |
    | Supported: "session", "token"
    |
    */

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'admins', // Changed from 'users' to 'admins'
        ],

        'api' => [
            'driver' => 'sanctum',
            'provider' => 'admins',
        ],
		'admin' => [
            'driver' => 'session',
            'provider' => 'staff',  // CRM login uses staff table
        ],
		'provider' => [
            'driver' => 'session',
            'provider' => 'providers',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Providers
    |--------------------------------------------------------------------------
    |
    | All authentication drivers have a user provider. This defines how the
    | users are actually retrieved out of your database or other storage
    | mechanisms used by this application to persist your user's data.
    |
    | If you have multiple user tables or models you may configure multiple
    | sources which represent each model / table. These sources may then
    | be assigned to any extra authentication guards you have defined.
    |
    | Supported: "database", "eloquent"
    |
    */

    'providers' => [
        // Removed 'users' provider - no longer used (legacy sub-user system was never implemented)
        // 'users' => [
        //     'driver' => 'eloquent',
        //     'model' => App\Models\User::class,
        // ],
		'admins' => [
            'driver' => 'eloquent',
            'model' => App\Models\Admin::class,
        ],
		'staff' => [
            'driver' => 'eloquent',
            'model' => App\Models\Staff::class,
        ],
		'providers' => [
            'driver' => 'eloquent',
            'model' => App\Provider::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Resetting Passwords
    |--------------------------------------------------------------------------
    |
    | You may specify multiple password reset configurations if you have more
    | than one user table or model in the application and you want to have
    | separate password reset settings based on the specific user types.
    |
    | The expire time is the number of minutes that the reset token should be
    | considered valid. This security feature keeps tokens short-lived so
    | they have less time to be guessed. You may change this as needed.
    |
    */

    'passwords' => [
        // Removed 'users' password reset config - no longer used
        // 'users' => [
        //     'provider' => 'users',
        //     'table' => 'password_reset_tokens',
        //     'expire' => 60,
        // ],
		'admins' => [
            'provider' => 'admins',
            'table' => 'password_reset_tokens',
            'expire' => 15,
        ],
		'staff' => [
            'provider' => 'staff',
            'table' => 'password_reset_tokens',
            'expire' => 15,
        ],
		'providers' => [
            'provider' => 'providers',
            'table' => 'password_reset_tokens',
            'expire' => 60,
        ],
    ],

];
