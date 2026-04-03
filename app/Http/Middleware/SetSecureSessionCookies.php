<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;

/**
 * Middleware to dynamically set secure session cookies based on HTTPS detection
 * 
 * This ensures that session cookies are marked as secure when the application
 * is accessed via HTTPS, even if SESSION_SECURE_COOKIE is not explicitly set
 * in the .env file. This is especially important for production environments.
 */
class SetSecureSessionCookies
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Only modify if SESSION_SECURE_COOKIE is not explicitly set in .env
        // If it's explicitly set, respect that value
        $explicitSecureCookie = env('SESSION_SECURE_COOKIE');
        
        if ($explicitSecureCookie === null || $explicitSecureCookie === '') {
            // Auto-detect HTTPS from request
            $isSecure = $request->isSecure() || 
                       $request->server('HTTP_X_FORWARDED_PROTO') === 'https' ||
                       $request->server('HTTP_X_FORWARDED_SSL') === 'on';
            
            // Dynamically set secure cookie config
            Config::set('session.secure', $isSecure);
        }
        
        return $next($request);
    }
}
