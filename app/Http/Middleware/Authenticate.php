<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string|null
     */
    protected function redirectTo($request)
    {
        // Check if this is an API request
        if ($request->is('api/*')) {
            return null; // API requests should not redirect
        }
        
        // Default to CRM login (formerly admin.login)
        return route('crm.login');
    }
}
