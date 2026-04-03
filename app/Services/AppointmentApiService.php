<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Exception;

class AppointmentApiService
{
    protected $baseUrl;
    protected $serviceToken;
    protected $token;
    protected $tokenExpiry;

    public function __construct($baseUrl = null, $serviceToken = null)
    {
        $this->baseUrl = $baseUrl ?? config('services.appointment_api.url', 'https://bansalimmigration.com.au/api');
        $this->serviceToken = $serviceToken ?? config('services.appointment_api.service_token');
    }

    /**
     * Authenticate using service token (no login required)
     */
    public function authenticate()
    { 
        try {
            // Check if we have a cached token
            $cachedToken = Cache::get('appointment_api_token');   
            if ($cachedToken) {
                $this->token = $cachedToken;
                return true;
            }

            // Authenticate with service token
            $response = Http::timeout(300)->post($this->baseUrl . '/service-account/authenticate', [
                'service_token' => $this->serviceToken
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                if ($data['success']) {
                    $this->token = $data['data']['token'];
                    
                    // Cache the token for 24 hours
                    Cache::put('appointment_api_token', $this->token, now()->addHours(24));
                    
                    return true;
                } else {
                    throw new Exception($data['message'] ?? 'Service authentication failed');
                }
            }

            throw new Exception('Service authentication failed: ' . $response->status());
        } catch (Exception $e) {
            throw new Exception('Authentication error: ' . $e->getMessage());
        }
    }

    /**
     * Get all appointments with optional filters
     */
    public function getAppointments($params = [])
    {
        $this->ensureAuthenticated();
        
        try {
            $response = Http::withToken($this->token)
                ->timeout(30)
                ->get($this->baseUrl . '/appointments', $params);

            if ($response->successful()) {
                return $response->json();
            }

            throw new Exception('Failed to fetch appointments: ' . $response->status());
        } catch (Exception $e) {
            throw new Exception('Get appointments error: ' . $e->getMessage());
        }
    }

    /**
     * Get appointment statistics
     */
    public function getStatistics()
    {
        $this->ensureAuthenticated();
        
        try {
            $response = Http::withToken($this->token)
                ->timeout(30)
                ->get($this->baseUrl . '/appointments/statistics/overview');

            if ($response->successful()) {
                return $response->json();
            }

            throw new Exception('Failed to fetch statistics: ' . $response->status());
        } catch (Exception $e) {
            throw new Exception('Get statistics error: ' . $e->getMessage());
        }
    }

    /**
     * Create new appointment
     */
    public function createAppointment($data)
    {
        $this->ensureAuthenticated();
        
        try {
            $response = Http::withToken($this->token)
                ->timeout(30)
                ->post($this->baseUrl . '/appointments', $data);

            if ($response->successful()) {
                return $response->json();
            }

            throw new Exception('Failed to create appointment: ' . $response->status());
        } catch (Exception $e) {
            throw new Exception('Create appointment error: ' . $e->getMessage());
        }
    }

    /**
     * Update appointment
     */
    public function updateAppointment($id, $data)
    {
        $this->ensureAuthenticated();
        
        try {
            $response = Http::withToken($this->token)
                ->timeout(30)
                ->put($this->baseUrl . '/appointments/' . $id, $data);

            if ($response->successful()) {
                return $response->json();
            }

            throw new Exception('Failed to update appointment: ' . $response->status());
        } catch (Exception $e) {
            throw new Exception('Update appointment error: ' . $e->getMessage());
        }
    }

    /**
     * Reschedule appointment using dedicated endpoint.
     */
    public function rescheduleAppointment(int $appointmentId, string $date, string $time): array
    {
        $this->ensureAuthenticated();

        try {
            $response = Http::withToken($this->token)
                ->timeout(30)
                ->post($this->baseUrl . '/appointments/update-appointment', [
                    'appointment_id' => $appointmentId,
                    'appointment_date' => $date,
                    'appointment_time' => $time,
                ]);

            if ($response->successful()) {
                return $response->json();
            }

            throw new Exception('Failed to reschedule appointment: ' . $response->status());
        } catch (Exception $e) {
            throw new Exception('Reschedule appointment error: ' . $e->getMessage());
        }
    }

    /**
     * Delete appointment
     */
    public function deleteAppointment($id)
    {
        $this->ensureAuthenticated();
        
        try {
            $response = Http::withToken($this->token)
                ->timeout(30)
                ->delete($this->baseUrl . '/appointments/' . $id);

            if ($response->successful()) {
                return $response->json();
            }

            throw new Exception('Failed to delete appointment: ' . $response->status());
        } catch (Exception $e) {
            throw new Exception('Delete appointment error: ' . $e->getMessage());
        }
    }

    /**
     * Ensure we are authenticated
     */
    protected function ensureAuthenticated()
    {
        if (!$this->token) {
            $this->authenticate();
        }
    }

    /**
     * Clear cached token (useful for testing)
     */
    public function clearCache()
    {
        Cache::forget('appointment_api_token');
        $this->token = null;
    }
}