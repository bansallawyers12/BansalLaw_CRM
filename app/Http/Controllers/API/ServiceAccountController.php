<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class ServiceAccountController extends Controller
{
    /**
     * Generate service account token
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function generateToken(Request $request)
    {
        try {
            // Validate the request
            $request->validate([
                'service_name' => 'required|string',
                'description' => 'required|string',
                'admin_email' => 'required|email',
                'admin_password' => 'required|string',
            ]);

            // Rate limit check: 5 attempts per minute per email + IP
            $throttleKey = 'service-account-token|' . Str::lower($request->input('admin_email')) . '|' . $request->ip();
            if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Too many token generation attempts. Please try again in ' . RateLimiter::availableIn($throttleKey) . ' seconds.',
                ], 429);
            }

            // Validate staff credentials, active status, and administrative role privileges
            $user = \App\Models\Staff::where('email', $request->admin_email)->first();

            $adminRoles = config('crm.admin_console_role_ids', [1, 12, 17]);
            $isAuthorizedRole = $user && (
                $user->canAccessAdminConsole()
                || $user->hasEffectiveSuperAdminPrivileges()
                || in_array((int) $user->role, $adminRoles, true)
            );

            $isValid = $user
                && (int) $user->status === 1
                && $isAuthorizedRole
                && Hash::check($request->admin_password, $user->password);

            if (!$isValid) {
                // Timing equalization to mitigate staff email/status enumeration
                if (!$user || (int) $user->status !== 1 || !$isAuthorizedRole) {
                    Hash::check(
                        (string) $request->admin_password,
                        '$2y$10$e0MYzXyjpJS7Pd0RVvHwHe1FX5X1D2u.w8Y4vL.N.o8Y4vL.N.o8Y'
                    );
                }
                RateLimiter::hit($throttleKey, 60);

                return response()->json([
                    'success' => false,
                    'message' => 'Invalid admin credentials',
                ], 401);
            }

            // Clear rate limiter on valid authenticated request
            RateLimiter::clear($throttleKey);

            // Generate genuine Sanctum API token for staff member with explicit expiration
            $expirationMinutes = (int) config('sanctum.expiration', 10080);
            $expiresAt = $expirationMinutes > 0 ? now()->addMinutes($expirationMinutes) : null;
            $token = $user->createToken($request->service_name, ['*'], $expiresAt)->plainTextToken;
            
            $response = [
                'success' => true,
                'token' => $token,
                'message' => 'Service account token generated successfully',
                'service_name' => $request->service_name,
                'admin_email' => $request->admin_email,
                'generated_at' => now()->toISOString(),
                'expires_at' => $expiresAt?->toISOString(),
            ];

            Log::info('Service account token generated', [
                'service_name' => $request->service_name,
                'admin_email' => $request->admin_email,
                'staff_id' => $user->id,
            ]);

            return response()->json($response, 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            // NEVER log raw passwords or sensitive credentials
            Log::error('Failed to generate service account token', [
                'error' => $e->getMessage(),
                'request_data' => $request->only(['service_name', 'admin_email', 'description']),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate token',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Authenticate using service token
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function authenticate(Request $request)
    {
        try {
            // Validate the request
            $request->validate([
                'service_token' => 'required|string',
            ]);

            // Rate limit check: 15 attempts per minute per IP
            $throttleKey = 'service-account-auth|' . $request->ip();
            if (RateLimiter::tooManyAttempts($throttleKey, 15)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Too many authentication attempts. Please try again in ' . RateLimiter::availableIn($throttleKey) . ' seconds.',
                ], 429);
            }

            $tokenModel = \Laravel\Sanctum\PersonalAccessToken::findToken($request->service_token);
            $user = $tokenModel?->tokenable;

            // Check expiration against expires_at and sanctum.expiration config
            $expirationMinutes = (int) config('sanctum.expiration', 10080);
            $isExpired = false;
            if ($tokenModel) {
                if ($tokenModel->expires_at && $tokenModel->expires_at->isPast()) {
                    $isExpired = true;
                } elseif ($expirationMinutes > 0 && $tokenModel->created_at && $tokenModel->created_at->lte(now()->subMinutes($expirationMinutes))) {
                    $isExpired = true;
                }
            }

            $isActiveStaff = $user && (int) ($user->status ?? 0) === 1;

            if (! $tokenModel || ! $user || ! $isActiveStaff || $isExpired) {
                // Timing equalization
                Hash::check(
                    (string) $request->service_token,
                    '$2y$10$e0MYzXyjpJS7Pd0RVvHwHe1FX5X1D2u.w8Y4vL.N.o8Y4vL.N.o8Y'
                );

                RateLimiter::hit($throttleKey, 60);

                Log::warning('Service account token authentication failed', [
                    'ip' => $request->ip(),
                    'token_found' => (bool) $tokenModel,
                    'user_found' => (bool) $user,
                    'is_active' => $isActiveStaff,
                    'is_expired' => $isExpired,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or expired service token',
                ], 401);
            }

            RateLimiter::clear($throttleKey);

            $expiresAt = $tokenModel->expires_at
                ?? ($expirationMinutes > 0 ? $tokenModel->created_at?->addMinutes($expirationMinutes) : null);

            return response()->json([
                'success' => true,
                'data' => [
                    'token' => $request->service_token,
                    'user' => [
                        'id' => $user->id,
                        'email' => $user->email,
                    ],
                    'expires_at' => $expiresAt?->toISOString(),
                ],
                'message' => 'Authentication successful',
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to authenticate service account', [
                'error' => $e->getMessage(),
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Authentication failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
} 