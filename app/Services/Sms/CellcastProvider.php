<?php

namespace App\Services\Sms;

use App\Services\Sms\Contracts\SmsProviderInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Exception;

class CellcastProvider implements SmsProviderInterface
{
    protected $apiKey;
    protected $baseUrl;
    protected $maxRetries = 3;
    protected $retryDelay = 2; // seconds

    public function __construct()
    {
        $this->apiKey = config('services.cellcast.api_key');
        // Remove trailing slash from base URL to prevent double slashes
        $this->baseUrl = rtrim(config('services.cellcast.base_url'), '/');
    }

    /**
     * Send SMS via Cellcast API
     */
    public function sendSms(string $to, string $message): array
    {
        try {
            // Validate phone number
            if (!$this->isValidPhoneNumber($to)) {
                return [
                    'success' => false,
                    'message' => 'Invalid phone number format'
                ];
            }

            // Check if it's a placeholder number
            if ($this->isPlaceholderNumber($to)) {
                return [
                    'success' => false,
                    'message' => 'Cannot send SMS to placeholder number'
                ];
            }

            // Format phone number for Australian numbers
            $formattedNumber = $this->formatPhoneNumber($to);

            Log::info('Sending SMS via Cellcast', [
                'to' => $formattedNumber,
                'message' => $message,
                'original_number' => $to
            ]);

            $response = $this->makeApiCall($formattedNumber, $message);

            if ($response['success']) {
                Log::info('Cellcast SMS sent successfully', [
                    'to' => $formattedNumber,
                    'response' => $response
                ]);

                return [
                    'success' => true,
                    'message' => 'SMS sent successfully',
                    'data' => $response['data'] ?? null
                ];
            } else {
                Log::error('Cellcast SMS failed', [
                    'to' => $formattedNumber,
                    'error' => $response['message']
                ]);

                return [
                    'success' => false,
                    'message' => 'Failed to send SMS: ' . $response['message']
                ];
            }

        } catch (Exception $e) {
            Log::error('Cellcast SMS Service Error', [
                'to' => $to,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'SMS service error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Send verification code SMS
     */
    public function sendVerificationCodeSMS($to, $message)
    {
        return $this->sendSms($to, $message);
    }

    /**
     * Make API call to Cellcast with retry mechanism
     */
    protected function makeApiCall($to, $message)
    {
        $attempt = 0;
        $lastError = null;

        while ($attempt < $this->maxRetries) {
            try {
                $attempt++;
                
                Log::info("Cellcast API attempt {$attempt}", [
                    'to' => $to,
                    'attempt' => $attempt
                ]);

                $response = Http::timeout(config('services.cellcast.timeout'))
                    ->withHeaders([
                        'APPKEY' => $this->apiKey,
                        'Content-Type' => 'application/json'
                    ])
                    ->post($this->baseUrl . '/send-sms', [
                        'numbers' => [$to],
                        'sms_text' => $message,
                        'from' => config('services.cellcast.sender_id') ?? ''
                    ]);

                if ($response->successful()) {
                    $data = $response->json();
                    
                    // Check Cellcast API response format: meta.status === "SUCCESS"
                    if (isset($data['meta']['status']) && $data['meta']['status'] === 'SUCCESS') {
                        return [
                            'success' => true,
                            'data' => $data['data'] ?? $data,
                            'message' => $data['msg'] ?? 'SMS sent successfully'
                        ];
                    } else {
                        $errorMessage = $data['msg'] ?? $data['message'] ?? 'Unknown API error';
                        $lastError = $errorMessage;
                        
                        Log::warning("Cellcast API returned error on attempt {$attempt}", [
                            'response' => $data,
                            'error' => $errorMessage
                        ]);
                    }
                } else {
                    $errorMessage = "HTTP {$response->status()}: " . $response->body();
                    $lastError = $errorMessage;
                    
                    Log::warning("Cellcast API HTTP error on attempt {$attempt}", [
                        'status' => $response->status(),
                        'body' => $response->body(),
                        'error' => $errorMessage
                    ]);
                }

                // Wait before retry
                if ($attempt < $this->maxRetries) {
                    sleep($this->retryDelay);
                }

            } catch (Exception $e) {
                $lastError = $e->getMessage();
                
                Log::warning("Cellcast API exception on attempt {$attempt}", [
                    'error' => $e->getMessage(),
                    'attempt' => $attempt
                ]);

                // Wait before retry
                if ($attempt < $this->maxRetries) {
                    sleep($this->retryDelay);
                }
            }
        }

        return [
            'success' => false,
            'message' => "Failed after {$this->maxRetries} attempts. Last error: " . $lastError
        ];
    }

    /**
     * Validate phone number format
     */
    protected function isValidPhoneNumber($phone)
    {
        // Remove any non-digit characters except +
        $cleaned = preg_replace('/[^\d+]/', '', $phone);
        
        // Check if it's a valid phone number format
        return preg_match('/^\+?[1-9]\d{9,14}$/', $cleaned);
    }

    /**
     * Check if phone number is a placeholder
     */
    protected function isPlaceholderNumber($phone)
    {
        // Remove any non-digit characters
        $cleaned = preg_replace('/[^\d]/', '', $phone);
        
        // Check if it starts with 4444444444 (placeholder pattern)
        return strpos($cleaned, '4444444444') === 0;
    }

    /**
     * Format phone number for Australian numbers
     */
    protected function formatPhoneNumber($phone)
    {
        // Remove any non-digit characters except +
        $cleaned = preg_replace('/[^\d+]/', '', $phone);
        
        // If it's an Australian number without country code, add it
        if (preg_match('/^0[2-9]\d{8}$/', $cleaned)) {
            // Remove leading 0 and add +61
            return '+61' . substr($cleaned, 1);
        }
        
        // If it's already formatted with +61, return as is
        if (strpos($cleaned, '+61') === 0) {
            return $cleaned;
        }
        
        // If it starts with 61, add +
        if (strpos($cleaned, '61') === 0) {
            return '+' . $cleaned;
        }
        
        // Return as is if already properly formatted
        return $cleaned;
    }

    /**
     * Check if phone number is Australian
     */
    public function isAustralianNumber($phone)
    {
        $formatted = $this->formatPhoneNumber($phone);
        return strpos($formatted, '+61') === 0;
    }

    /**
     * Get SMS status (if supported by Cellcast API)
     */
    public function getSmsStatus($messageId)
    {
        try {
            $response = Http::timeout(config('services.cellcast.timeout'))
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json'
                ])
                ->get($this->baseUrl . '/sms/status/' . $messageId);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json()
                ];
            } else {
                return [
                    'success' => false,
                    'message' => "HTTP {$response->status()}: " . $response->body()
                ];
            }

        } catch (Exception $e) {
            Log::error('Cellcast SMS Status Check Error', [
                'message_id' => $messageId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Status check error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get provider name
     */
    public function getProviderName(): string
    {
        return 'cellcast';
    }

    /**
     * Supports delivery status via webhooks
     */
    public function supportsDeliveryStatus(): bool
    {
        return true;
    }

    /**
     * Supports bulk sending
     */
    public function supportsBulkSending(): bool
    {
        return true;
    }

    /**
     * Get Cellcast configuration health status
     */
    public function getHealthStatus(): array
    {
        $issues = [];
        $configured = true;

        if (!config('services.cellcast.api_key')) {
            $issues[] = 'Missing Cellcast API Key';
            $configured = false;
        }

        if (!config('services.cellcast.base_url')) {
            $issues[] = 'Missing Cellcast Base URL';
            $configured = false;
        }

        return [
            'configured' => $configured,
            'issues' => $issues,
            'details' => [
                'api_key' => config('services.cellcast.api_key') ? 'Configured' : 'Missing',
                'base_url' => config('services.cellcast.base_url') ?: 'Missing',
                'sender_id' => config('services.cellcast.sender_id') ?: 'Not configured',
                'timeout' => config('services.cellcast.timeout') ?: 'Default (30s)',
            ]
        ];
    }
}

