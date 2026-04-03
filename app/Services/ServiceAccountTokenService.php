<?php

namespace App\Services;

use App\Jobs\GenerateServiceAccountToken;
use App\Models\Admin;
use App\Models\Staff;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\API\ServiceAccountController;
use Illuminate\Http\Request;

class ServiceAccountTokenService
{
    /**
     * Dispatch a background job to generate service account token
     *
     * @param Admin|Staff $admin
     * @param string|null $serviceName
     * @param string|null $description
     * @return void
     */
    public function generateTokenInBackground(Admin|Staff $admin, $serviceName = null, $description = null, $password = null)
    {
        try {
            // Dispatch the job to run in background
            GenerateServiceAccountToken::dispatch($admin, $serviceName, $description, $password);
            
            Log::info('Service account token generation job dispatched', [
                'admin_id' => $admin->id,
                'admin_email' => $admin->email
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to dispatch service account token generation job', [
                'admin_id' => $admin->id,
                'admin_email' => $admin->email,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Generate token synchronously (for testing or immediate use)
     *
     * @param Admin|Staff $admin
     * @param string|null $serviceName
     * @param string|null $description
     * @param string|null $password
     * @return array|null
     */
    public function generateTokenSync(Admin|Staff $admin, $serviceName = null, $description = null, $password = null)
    {
        try {
            // Use provided password or default (decrypt_password column removed Phase 4)
            $adminPassword = $password ?: 'admin123';
            
            // Create a mock request object
            $request = new Request([
                'service_name' => $serviceName ?? 'Bansal Immigration CRM',
                'description' => $description ?? 'Service account token for admin authentication',
                'admin_email' => $admin->email,
                'admin_password' => $adminPassword,
            ]);

            // Call the controller method directly instead of making HTTP request
            $controller = new ServiceAccountController();
            $response = $controller->generateToken($request);
            
            // Get the response data
            $result = json_decode($response->getContent(), true);

            if ($result && isset($result['success']) && $result['success']) {
                // Update the admin record directly
                $admin->update([
                    'service_token' => $result['token'] ?? null,
                    'token_generated_at' => now()
                ]);
                
                Log::info('Service account token generated successfully synchronously', [
                    'admin_id' => $admin->id,
                    'admin_email' => $admin->email,
                    'token' => $result['token'] ?? 'N/A'
                ]);
                
                return $result;
            } else {
                Log::error('Failed to generate service account token synchronously', [
                    'admin_id' => $admin->id,
                    'admin_email' => $admin->email,
                    'response' => $result
                ]);
                return null;
            }
        } catch (\Exception $e) {
            Log::error('Exception occurred while generating service account token synchronously', [
                'admin_id' => $admin->id,
                'admin_email' => $admin->email,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
} 