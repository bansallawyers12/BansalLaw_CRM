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

            // For local development, we'll generate a mock token
            // In production, this would call the actual external API
            $mockToken = 'local_token_' . Str::random(32) . '_' . time();
            
            $response = [
                'success' => true,
                'token' => $mockToken,
                'message' => 'Token generated successfully for local development',
                'service_name' => $request->service_name,
                'admin_email' => $request->admin_email,
                'generated_at' => now()->toISOString()
            ];

            // Log the token generation
            Log::info('Local service account token generated', [
                'service_name' => $request->service_name,
                'admin_email' => $request->admin_email,
                'token' => $mockToken
            ]);

            return response()->json($response, 200);

        } catch (\Exception $e) {
            Log::error('Failed to generate local service account token', [
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

            // For local development, we'll accept any service token
            // In production, this would validate against the actual service token
            $authToken = 'local_auth_token_' . time() . '_' . substr(md5($request->service_token), 0, 16);
            
            $response = [
                'success' => true,
                'data' => [
                    'token' => $authToken,
                    'expires_at' => now()->addHours(24)->toISOString()
                ],
                'message' => 'Authentication successful for local development'
            ];

            // Log the authentication
            Log::info('Local service account authentication', [
                'service_token_length' => strlen($request->service_token),
                'auth_token' => $authToken
            ]);

            return response()->json($response, 200);

        } catch (\Exception $e) {
            Log::error('Failed to authenticate local service account', [
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