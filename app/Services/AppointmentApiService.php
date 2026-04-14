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
        $this->baseUrl = rtrim($baseUrl ?? config('services.appointment_api.url'), '/');
        $this->serviceToken = $serviceToken ?? config('services.appointment_api.service_token');
    }

    protected function httpTimeoutSeconds(): int
    {
        $t = (int) config('services.appointment_api.timeout', 120);

        return max(5, min(300, $t > 600 ? (int) ($t / 1000) : $t));
    }

    /**
     * GET /appointments with default filters used by the admin calendar (public booking API).
     *
     * @param  array<string, mixed>  $params
     */
    /**
     * @param  int|string|null  $apiStatus  Omit from query when null (stats / loose listing).
     */
    public function getAppointmentsByServiceAndStatus(int $serviceId, int|string|null $apiStatus = 1, array $params = []): array
    {
        $query = array_merge(['service_id' => $serviceId], $params);
        if ($apiStatus !== null && $apiStatus !== '') {
            $query['status'] = $apiStatus;
        }

        return $this->getAppointments($query);
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
            $response = Http::timeout($this->httpTimeoutSeconds())->post($this->baseUrl . '/service-account/authenticate', [
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

        $useCachedServiceToken = ! empty($this->serviceToken)
            && empty(config('services.appointment_api.bearer_token'));

        $maxAttempts = 3;
        $lastError = null;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $response = Http::withToken($this->token)
                    ->timeout($this->httpTimeoutSeconds())
                    ->get($this->baseUrl . '/appointments', $params);

                if ($response->successful()) {
                    return $response->json();
                }

                $status = $response->status();

                if ($status === 401 && $useCachedServiceToken && $attempt < $maxAttempts) {
                    Cache::forget('appointment_api_token');
                    $this->token = null;
                    $this->authenticate();

                    continue;
                }

                $retryableStatus = in_array($status, [408, 425, 429, 500, 502, 503, 504], true);
                if ($retryableStatus && $attempt < $maxAttempts) {
                    usleep(250000 * (2 ** ($attempt - 1)));

                    continue;
                }

                throw new Exception('Failed to fetch appointments: HTTP ' . $status);
            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                $lastError = $e;
                if ($attempt >= $maxAttempts) {
                    throw new Exception('Get appointments error: ' . $e->getMessage());
                }
                usleep(250000 * (2 ** ($attempt - 1)));
            }
        }

        throw new Exception('Get appointments error: ' . ($lastError ? $lastError->getMessage() : 'max retries exceeded'));
    }

    /**
     * Get appointment statistics
     */
    public function getStatistics()
    {
        $this->ensureAuthenticated();
        
        try {
            $response = Http::withToken($this->token)
                ->timeout($this->httpTimeoutSeconds())
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
                ->timeout($this->httpTimeoutSeconds())
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
                ->timeout($this->httpTimeoutSeconds())
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
                ->timeout($this->httpTimeoutSeconds())
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
                ->timeout($this->httpTimeoutSeconds())
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
        $bearer = config('services.appointment_api.bearer_token');
        if (! empty($bearer)) {
            $this->token = $bearer;

            return;
        }

        if (! $this->token) {
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