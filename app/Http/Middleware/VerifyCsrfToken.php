<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * Indicates whether the XSRF-TOKEN cookie should be set on the response.
     *
     * @var bool
     */
    protected $addHttpCookie = true;

    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array
     */ 
    protected $except = [
        //
		'api/*',
		'broadcasting/auth', // Pusher broadcasting auth endpoint
		'admin/update_visit_purpose',
		'admin/update_visit_comment',
		'admin/attend_session',
		'admin/complete_session',
		'admin/update_task_comment',
		'admin/update_task_description',
		'admin/update_task_status',
		'admin/update_task_priority',
		'admin/updateduedate',
		'get-activities', // Client activities endpoint (GET request, no CSRF needed)
    ];
}
