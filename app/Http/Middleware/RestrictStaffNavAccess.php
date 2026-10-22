<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Legacy Staff Access nav gate — removed. Kept as a no-op so cached routes
 * that still reference restricted.access do not break after deploy.
 */
class RestrictStaffNavAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }
}
