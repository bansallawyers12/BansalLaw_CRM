<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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

            // Validate admin credentials
            $user = \App\Models\Staff::where('email', $request->admin_email)->first();
            if (!$user || !\Illuminate\Support\Facades\Hash::check($request->admin_password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid admin credentials',
                ], 401);
            }

            // Generate genuine Sanctum API token for staff member
            $token = $user->createToken($request->service_name)->plainTextToken;
            
            $response = [
                'success' => true,
                'token' => $token,
                'message' => 'Service account token generated successfully',
                'service_name' => $request->service_name,
                'admin_email' => $request->admin_email,
                'generated_at' => now()->toISOString()
            ];

            Log::info('Service account token generated', [
                'service_name' => $request->service_name,
                'admin_email' => $request->admin_email,
            ]);

            return response()->json($response, 200);

        } catch (\Exception $e) {
            Log::error('Failed to generate service account token', [
                'error' => $e->getMessage(),
                'request_data' => $request->all()
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

            $tokenModel = \Laravel\Sanctum\PersonalAccessToken::findToken($request->service_token);
            if (!$tokenModel || !$tokenModel->tokenable) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or expired service token',
                ], 401);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'token' => $request->service_token,
                    'user' => [
                        'id' => $tokenModel->tokenable->id,
                        'email' => $tokenModel->tokenable->email,
                    ],
                    'expires_at' => $tokenModel->expires_at?->toISOString()
                ],
                'message' => 'Authentication successful'
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to authenticate service account', [
                'error' => $e->getMessage(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Authentication failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
} 