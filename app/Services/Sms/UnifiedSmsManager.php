<?php

namespace App\Services\Sms;

use App\Models\SmsLog;
use App\Models\ActivitiesLog;
use App\Helpers\PhoneValidationHelper;
use App\Services\Sms\Contracts\SmsProviderInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * UnifiedSmsManager
 * 
 * Centralized SMS service that handles all SMS operations with:
 * - Automatic provider selection (Cellcast for AU, Twilio for others)
 * - Comprehensive activity logging
 * - Error handling and retry logic
 * - Template support
 * - Delivery status tracking
 */
class UnifiedSmsManager
{
    protected SmsProviderInterface $cellcastService;
    protected SmsProviderInterface $smsService;
    
    public function __construct(CellcastProvider $cellcastService, TwilioProvider $smsService)
    {
        $this->cellcastService = $cellcastService;
        $this->smsService = $smsService;
    }

    /**
     * Send SMS with automatic provider selection and activity logging
     * 
     * @param string $to Phone number (9-10 digits for AU numbers)
     * @param string $message SMS message content
     * @param string $type Message type: verification|notification|manual|reminder
     * @param array $context Additional context (client_id, contact_id, template_id)
     * @return array Result with success status and data
     */
    public function sendSms($to, $message, $type = 'manual', $context = [])
    {
        try {
            // Validate phone number
            $validation = PhoneValidationHelper::validatePhoneNumber($to);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'message' => $validation['message']
                ];
            }

            // Check if placeholder number
            if ($validation['is_placeholder'] ?? false) {
                return [
                    'success' => false,
                    'message' => 'Cannot send SMS to placeholder numbers'
                ];
            }

            // Format phone number for SMS
            $formatted = PhoneValidationHelper::formatForSMS($to);
            
            if (!$formatted) {
                return [
                    'success' => false,
                    'message' => 'Invalid phone number format'
                ];
            }

            // Determine provider
            $provider = PhoneValidationHelper::getProviderForNumber($to);
            
            Log::info('UnifiedSmsManager: Sending SMS', [
                'to' => $formatted,
                'provider' => $provider,
                'type' => $type,
                'client_id' => $context['client_id'] ?? null
            ]);

            // Send via appropriate provider
            $result = $this->sendViaProvider($provider, $formatted, $message);

            // Extract provider message ID
            $providerMessageId = null;
            if ($result['success']) {
                if ($provider === 'twilio' && isset($result['results'][0]['sid'])) {
                    $providerMessageId = $result['results'][0]['sid'];
                } elseif ($provider === 'cellcast' && isset($result['data']['messages'][0]['message_id'])) {
                    $providerMessageId = $result['data']['messages'][0]['message_id'];
                }
            }

            // Extract country code from phone number or contact
            $countryCode = '+61'; // Default to Australia
            // Try to get from contact if available
            if (!empty($context['contact_id'])) {
                $contact = \App\Models\ClientContact::find($context['contact_id']);
                if ($contact && $contact->country_code) {
                    $countryCode = $contact->country_code;
                }
            }
            // Fallback: extract from phone number string
            if ($countryCode === '+61' && preg_match('/^(\+\d{1,3})/', $to, $matches)) {
                $countryCode = $matches[1];
            } elseif ($countryCode === '+61' && preg_match('/^(\+\d{1,3})/', $formatted, $matches)) {
                $countryCode = $matches[1];
            }

            // Log SMS activity to database
            $smsLog = $this->logSmsActivity([
                'client_id' => $context['client_id'] ?? null,
                'client_contact_id' => $context['contact_id'] ?? null,
                'sender_id' => $context['sender_id'] ?? Auth::id(), // Allow override via context
                'recipient_phone' => $to,
                'country_code' => $countryCode,
                'formatted_phone' => $formatted,
                'message_content' => $message,
                'message_type' => $type,
                'template_id' => $context['template_id'] ?? null,
                'provider' => $provider,
                'provider_message_id' => $providerMessageId,
                'status' => $result['success'] ? 'sent' : 'failed',
                'error_message' => $result['success'] ? null : ($result['message'] ?? $result['error'] ?? 'Unknown error'),
                'cost' => 0,
                'sent_at' => $result['success'] ? now() : null,
            ]);

            // Add SMS log ID to result (if logging succeeded)
            if (isset($smsLog->id)) {
                $result['sms_log_id'] = $smsLog->id;
            }

            return $result;

        } catch (\Exception $e) {
            Log::error('UnifiedSmsManager: Exception', [
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
     * Send SMS via specific provider
     */
    protected function sendViaProvider($provider, $phone, $message)
    {
        if ($provider === 'cellcast') {
            return $this->cellcastService->sendSms($phone, $message);
        } else {
            return $this->smsService->sendSms($phone, $message);
        }
    }

    /**
     * Send verification code SMS
     */
    public function sendVerificationCode($to, $code, $context = [])
    {
        $result = $this->sendFromTemplateByAlias($to, 'phone_verification', [
            'verification_code' => (string) $code,
            'expiry_minutes' => '5',
        ], $context);

        // Only fall back to raw message if the template itself is missing; do NOT
        // retry if the provider already attempted delivery and failed.
        if (!$result['success'] && $this->isTemplateMissingError($result)) {
            $message = "BANSAL IMMIGRATION: Your verification code is {$code}. This code expires in 5 minutes.";
            return $this->sendSms($to, $message, 'verification', $context);
        }

        return $result;
    }

    /**
     * Render an active template by alias (no send, no usage increment).
     */
    public function renderTemplateByAlias(string $alias, array $variables = []): ?string
    {
        $template = \App\Models\SmsTemplate::active()->byAlias($alias)->first();
        if (!$template) {
            return null;
        }

        return $this->replaceTemplateVariables($template->message, $variables);
    }

    /**
     * Send SMS using template alias (Admin Console editable).
     */
    public function sendFromTemplateByAlias($to, string $alias, array $variables = [], array $context = [])
    {
        $template = \App\Models\SmsTemplate::active()->byAlias($alias)->first();
        if (!$template) {
            return [
                'success' => false,
                'message' => 'Template not found or inactive',
            ];
        }

        return $this->sendFromTemplateModel($to, $template, $variables, $context);
    }

    /**
     * Send SMS from template by ID (public convenience method).
     */
    public function sendFromTemplate($to, $templateId, $variables = [], $context = [])
    {
        try {
            $template = \App\Models\SmsTemplate::find($templateId);

            if (!$template || !$template->is_active) {
                return [
                    'success' => false,
                    'message' => 'Template not found or inactive',
                ];
            }

            return $this->sendFromTemplateModel($to, $template, $variables, $context);

        } catch (\Exception $e) {
            Log::error('UnifiedSmsManager: Template error', [
                'template_id' => $templateId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Template processing error',
            ];
        }
    }

    /**
     * Core: render template body, increment usage, and send.
     * Shared by sendFromTemplate and sendFromTemplateByAlias to avoid double DB fetch.
     */
    protected function sendFromTemplateModel($to, \App\Models\SmsTemplate $template, array $variables = [], array $context = []): array
    {
        $message = $this->replaceTemplateVariables($template->message, $variables);
        $context['template_id'] = $template->id;
        $template->increment('usage_count');
        $type = $this->resolveMessageTypeFromCategory($template->category);

        return $this->sendSms($to, $message, $type, $context);
    }

    protected function resolveMessageTypeFromCategory(?string $category): string
    {
        return in_array($category, ['verification', 'notification', 'manual', 'reminder'], true)
            ? $category
            : 'manual';
    }

    /**
     * Returns true when a result failure means the template row was missing/inactive
     * (so callers know whether a raw-message fallback is appropriate).
     */
    protected function isTemplateMissingError(array $result): bool
    {
        return str_contains($result['message'] ?? '', 'Template not found');
    }

    /**
     * Replace template variables
     */
    protected function replaceTemplateVariables($message, $variables)
    {
        foreach ($variables as $key => $value) {
            $message = str_replace('{' . $key . '}', (string) $value, $message);
        }
        
        return $message;
    }

    /**
     * Log SMS activity to database and create activity log entry
     */
    protected function logSmsActivity($data)
    {
        try {
            // Create SMS log entry
            $smsLog = SmsLog::create($data);

            // Auto-create activity log entry for client timeline
            if (!empty($data['client_id'])) {
                ActivitiesLog::create([
                    'client_id' => $data['client_id'],
                    'created_by' => $data['sender_id'],
                    'subject' => $this->getActivitySubject($data['message_type'], $data['status']),
                    'description' => $this->formatActivityDescription($data),
                    'sms_log_id' => $smsLog->id,
                    'activity_type' => 'sms',
                    'task_status' => 0,
                    'pin' => 0,
                ]);
            }

            return $smsLog;
        } catch (\Exception $e) {
            // Log the error but don't fail the SMS sending
            Log::error('UnifiedSmsManager: Failed to log SMS activity', [
                'error' => $e->getMessage(),
                'data' => $data,
                'trace' => $e->getTraceAsString()
            ]);

            // Return a dummy log entry to prevent breaking the flow
            // The SMS was sent successfully, just logging failed
            return (object)['id' => null];
        }
    }

    /**
     * Get activity subject based on message type
     */
    protected function getActivitySubject($type, $status)
    {
        $statusText = $status === 'sent' ? 'sent' : 'failed to send';
        
        switch ($type) {
            case 'verification':
                return "{$statusText} verification SMS";
            case 'notification':
                return "{$statusText} notification SMS";
            case 'reminder':
                return "{$statusText} reminder SMS";
            case 'manual':
            default:
                return "{$statusText} SMS";
        }
    }

    /**
     * Format activity description with SMS details (complete message)
     */
    protected function formatActivityDescription($data)
    {
        $messageContent = trim($data['message_content']); // Complete message, not truncated
        $statusBadge = $data['status'] === 'sent' 
            ? '<span class="badge badge-success">Sent</span>' 
            : '<span class="badge badge-danger">Failed</span>';
        
        $providerBadge = '<span class="badge badge-info">' . strtoupper($data['provider']) . '</span>';
        
        $errorSection = '';
        if ($data['error_message']) {
            $errorSection = '<p class="text-danger mt-2"><small><strong>Error:</strong> ' 
                . htmlspecialchars($data['error_message']) 
                . '</small></p>';
        }
        
        return "
            <div class='sms-activity'>
                <p><strong>To:</strong> {$data['formatted_phone']} {$statusBadge} {$providerBadge}</p>
                <p style='margin-bottom: 5px;'><strong>Message:</strong></p>
                <p style='background: #f8f9fa; padding: 8px; border-radius: 4px; margin: 0; white-space: pre-wrap; word-wrap: break-word;'>{$messageContent}</p>
                {$errorSection}
            </div>
        ";
    }

    /**
     * Get SMS delivery status from provider
     */
    public function getDeliveryStatus($smsLogId)
    {
        try {
            $smsLog = SmsLog::find($smsLogId);
            
            if (!$smsLog) {
                return [
                    'success' => false,
                    'message' => 'SMS log not found'
                ];
            }

            if (!$smsLog->provider_message_id) {
                return [
                    'success' => false,
                    'message' => 'No provider message ID available'
                ];
            }

            // Query provider for status
            if ($smsLog->provider === 'cellcast') {
                $result = $this->cellcastService->getSmsStatus($smsLog->provider_message_id);
            } else {
                $result = $this->smsService->getSmsStatus($smsLog->provider_message_id);
            }

            // Update SMS log status if changed
            if ($result['success'] && isset($result['status'])) {
                $smsLog->update([
                    'status' => $result['status'],
                    'delivered_at' => $result['status'] === 'delivered' ? now() : null
                ]);
            }

            return $result;

        } catch (\Exception $e) {
            Log::error('UnifiedSmsManager: Status check error', [
                'sms_log_id' => $smsLogId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Status check failed'
            ];
        }
    }

    /**
     * Get SMS statistics
     */
    public function getStatistics($startDate = null, $endDate = null)
    {
        $query = SmsLog::query();

        if ($startDate) {
            $query->where('sent_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('sent_at', '<=', $endDate);
        }

        return [
            'total' => $query->count(),
            'sent' => $query->where('status', 'sent')->count(),
            'delivered' => $query->where('status', 'delivered')->count(),
            'failed' => $query->where('status', 'failed')->count(),
            'by_provider' => [
                'cellcast' => $query->where('provider', 'cellcast')->count(),
                'twilio' => $query->where('provider', 'twilio')->count(),
            ],
            'by_type' => [
                'verification' => $query->where('message_type', 'verification')->count(),
                'notification' => $query->where('message_type', 'notification')->count(),
                'manual' => $query->where('message_type', 'manual')->count(),
                'reminder' => $query->where('message_type', 'reminder')->count(),
            ],
            'total_cost' => $query->sum('cost'),
        ];
    }
}

