<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Protects public booking and payment endpoints.
 * When BOOKING_SHARED_SECRET is configured, requests must supply a matching secret.
 * When unset, requests pass through under route-level rate limiting.
 */
class VerifyBookingApiAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $configured = (string) config('services.booking.shared_secret', '');

        if ($configured !== '') {
            $secret = $request->header('X-Booking-Secret')
                ?? $request->header('X-API-Key')
                ?? $request->bearerToken()
                ?? $request->input('secret')
                ?? $request->input('api_key');

            if (empty($secret) || ! is_string($secret) || ! hash_equals($configured, $secret)) {
                Log::warning('Booking API request rejected: missing or invalid shared secret', [
                    'path' => $request->path(),
                    'ip' => $request->ip(),
                ]);

                return response()->json([
                    'success' => false,
                    'status' => false,
                    'message' => 'Unauthorized booking API access.',
                ], 401);
            }
        }

        return $next($request);
    }
}
