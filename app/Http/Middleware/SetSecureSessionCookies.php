<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;

/**
 * Middleware to dynamically set secure session cookies based on HTTPS detection.
 *
 * When session.secure is already explicitly true (set via config/session.php or
 * SESSION_SECURE_COOKIE=true in .env), this middleware leaves it untouched.
 * When it is false/null it auto-detects HTTPS from the request and upgrades the
 * flag for the duration of the request, which handles reverse-proxy setups where
 * the app itself sees plain HTTP but the client is on HTTPS.
 *
 * NOTE: env() must NOT be called here — it returns null when the config cache is
 * active (php artisan config:cache). Always read from config() instead.
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
        // If the compiled config already marks the cookie as secure, do nothing.
        if (config('session.secure')) {
            return $next($request);
        }

        // Auto-detect HTTPS, including common reverse-proxy headers.
        $isSecure = $request->isSecure()
            || $request->server('HTTP_X_FORWARDED_PROTO') === 'https'
            || $request->server('HTTP_X_FORWARDED_SSL') === 'on';

        if ($isSecure) {
            Config::set('session.secure', true);
        }

        return $next($request);
    }
}
