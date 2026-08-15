<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Staff-only API authentication (Sanctum).
 */
class StaffApiAuthController extends Controller
{
    /**
     * POST /api/admin-login
     *
     * Login for staff users with roles 1, 12, 13, 16 (Staff model).
     */
    public function adminLogin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|max:255',
            'password' => 'required|string',
            'device_name' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $staff = Staff::where('email', $request->email)
            ->whereIn('role', [1, 12, 13, 16])
            ->where('status', 1)
            ->first();

        if (! $staff || ! Hash::check($request->password, $staff->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials',
            ], 401);
        }

        $deviceName = $request->device_name ?? 'admin-portal-app';
        $tokenObj = null;

        try {
            $refreshTokenValue = Str::random(64);
            $expiresAt = Carbon::now()->addDays(30);

            $insertData = [
                'user_id' => $staff->id,
                'token' => $refreshTokenValue,
                'device_name' => $deviceName,
                'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
                'is_revoked' => 0,
                'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
                'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
            ];

            return DB::transaction(function () use ($staff, $deviceName, $refreshTokenValue, $insertData, &$tokenObj) {
                $tokenObj = $staff->createToken($deviceName);
                $token = $tokenObj->plainTextToken;

                DB::table('refresh_tokens')->insertGetId($insertData);

                $staff->touch();

                return response()->json([
                    'success' => true,
                    'message' => 'Login successful',
                    'token' => $token,
                    'refresh_token' => $refreshTokenValue,
                    'user' => [
                        'id' => $staff->id,
                        'name' => $staff->first_name.' '.$staff->last_name,
                        'email' => $staff->email,
                        'role' => $staff->role,
                    ],
                ]);
            });
        } catch (\Illuminate\Database\QueryException $e) {
            if (isset($tokenObj) && isset($tokenObj->accessToken)) {
                $tokenObj->accessToken->delete();
            }

            $errorDetails = $this->handleRefreshTokenError($e, $staff->id, $insertData ?? [], $refreshTokenValue ?? '');

            return response()->json([
                'success' => false,
                'message' => 'Failed to complete login. Please try again.',
                'error' => 'Token generation failed',
                'problematic_field' => $errorDetails['field'],
                'error_details' => config('app.debug') ? $errorDetails : null,
            ], 500);
        } catch (\Exception $e) {
            if (isset($tokenObj) && isset($tokenObj->accessToken)) {
                $tokenObj->accessToken->delete();
            }

            Log::error('Failed to generate refresh token during admin login (non-database error)', [
                'user_id' => $staff->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to complete login. Please try again.',
                'error' => 'Token generation failed',
                'error_details' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * POST /api/logout
     */
    public function logout(Request $request)
    {
        try {
            $user = $request->user();
            $user->currentAccessToken()->delete();

            DB::table('refresh_tokens')
                ->where('user_id', $user->id)
                ->update(['is_revoked' => 1, 'updated_at' => Carbon::now()->format('Y-m-d H:i:s')]);

            $log = new \App\Models\StaffLoginLog();
            $log->level = 'info';
            $log->user_id = $user->id;
            $log->ip_address = $request->getClientIp();
            $log->user_agent = $request->userAgent() ?? '';
            $log->message = 'Logged out successfully (API)';
            $log->save();

            return response()->json([
                'success' => true,
                'message' => 'Logged out successfully',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Logout failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/logout-all
     */
    public function logoutAll(Request $request)
    {
        try {
            $user = $request->user();
            $user->tokens()->delete();

            DB::table('refresh_tokens')
                ->where('user_id', $user->id)
                ->update(['is_revoked' => 1, 'updated_at' => Carbon::now()->format('Y-m-d H:i:s')]);

            $log = new \App\Models\StaffLoginLog();
            $log->level = 'info';
            $log->user_id = $user->id;
            $log->ip_address = $request->getClientIp();
            $log->user_agent = $request->userAgent() ?? '';
            $log->message = 'Logged out from all devices successfully (API)';
            $log->save();

            return response()->json([
                'success' => true,
                'message' => 'Logged out from all devices successfully',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Logout from all devices failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function handleRefreshTokenError($e, $userId, $insertData, $tokenValue)
    {
        $errorMessage = $e->getMessage();
        $problematicField = null;

        if (str_contains($errorMessage, 'user_id')) {
            $problematicField = 'user_id';
        } elseif (str_contains($errorMessage, 'token')) {
            $problematicField = 'token';
        } elseif (str_contains($errorMessage, 'device_name')) {
            $problematicField = 'device_name';
        } elseif (str_contains($errorMessage, 'expires_at')) {
            $problematicField = 'expires_at';
        } elseif (str_contains($errorMessage, 'is_revoked')) {
            $problematicField = 'is_revoked';
        } elseif (str_contains($errorMessage, 'created_at')) {
            $problematicField = 'created_at';
        } elseif (str_contains($errorMessage, 'updated_at')) {
            $problematicField = 'updated_at';
        } elseif (str_contains($errorMessage, 'Duplicate entry')) {
            $problematicField = 'token (duplicate)';
        } elseif (str_contains($errorMessage, 'foreign key constraint')) {
            $problematicField = 'user_id (foreign key constraint - user may not exist)';
        } elseif (str_contains($errorMessage, 'cannot be null')) {
            preg_match("/Column '([^']+)' cannot be null/", $errorMessage, $matches);
            $problematicField = $matches[1] ?? 'unknown field';
        }

        Log::error('Failed to generate refresh token', [
            'user_id' => $userId,
            'error' => $errorMessage,
            'problematic_field' => $problematicField,
        ]);

        return [
            'field' => $problematicField,
            'message' => $errorMessage,
            'sql_state' => $e->errorInfo[0] ?? null,
            'driver_code' => $e->errorInfo[1] ?? null,
        ];
    }
}
