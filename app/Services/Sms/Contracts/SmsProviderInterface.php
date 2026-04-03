<?php

namespace App\Services\Sms\Contracts;

/**
 * SmsProviderInterface
 * 
 * Contract that all SMS providers must implement
 * Ensures consistent API across different providers (Twilio, Cellcast, etc.)
 */
interface SmsProviderInterface
{
    /**
     * Send SMS message
     * 
     * @param string $to Phone number to send to
     * @param string $message Message content
     * @return array Response with success status and data
     * 
     * Expected response format:
     * [
     *     'success' => bool,
     *     'message' => string,
     *     'data' => array|null,
     *     'results' => array|null (for compatibility)
     * ]
     */
    public function sendSms(string $to, string $message): array;

    /**
     * Get provider name/identifier
     * 
     * @return string Provider name (e.g., 'twilio', 'cellcast')
     */
    public function getProviderName(): string;

    /**
     * Check if provider supports delivery status tracking
     * 
     * @return bool True if webhooks/status checking is supported
     */
    public function supportsDeliveryStatus(): bool;

    /**
     * Check if provider supports bulk sending
     * 
     * @return bool True if bulk operations are supported
     */
    public function supportsBulkSending(): bool;

    /**
     * Get provider-specific configuration status
     * 
     * @return array Configuration health check
     * [
     *     'configured' => bool,
     *     'issues' => array,
     *     'details' => array
     * ]
     */
    public function getHealthStatus(): array;
}
