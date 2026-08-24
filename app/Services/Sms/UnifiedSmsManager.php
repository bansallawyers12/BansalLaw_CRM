<?php

namespace App\Services\Sms;

use App\Models\SmsLog;
use App\Models\ActivitiesLog;
use App\Helpers\PhoneValidationHelper;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * UnifiedSmsManager
 *
 * Centralized SMS service that handles all SMS operations with:
 * - Cellcast as the sole SMS provider
 * - Comprehensive activity logging
 * - Error handling and retry logic
 * - Template support
 * - Delivery status tracking
 */
class UnifiedSmsManager
{
    public function __construct(protected CellcastProvider $cellcastService)
    {
    }

    /**
     * Send SMS with activity logging via Cellcast.
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
            $validation = PhoneValidationHelper::validatePhoneNumber($to);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'message' => $validation['message'],
                ];
            }

            if ($validation['is_placeholder'] ?? false) {
                return [
                    'success' => false,
                    'message' => 'Cannot send SMS to placeholder numbers',
                ];
            }

            $formatted = PhoneValidationHelper::formatForSMS($to);

            if (!$formatted) {
                return [
                    'success' => false,
                    'message' => 'Invalid phone number format',
                ];
            }

            $provider = 'cellcast';

            Log::info('UnifiedSmsManager: Sending SMS', [
                'to' => $formatted,
                'provider' => $provider,
                'type' => $type,
                'client_id' => $context['client_id'] ?? null,
            ]);

            $result = $this->cellcastService->sendSms($formatted, $message);

            $providerMessageId = null;
            if ($result['success'] && isset($result['data']['messages'][0]['message_id'])) {
                $providerMessageId = $result['data']['messages'][0]['message_id'];
            }

            $countryCode = '+61';
            if (! empty($context['contact_id'])) {
                $contact = \App\Models\ClientContact::find($context['contact_id']);
                if ($contact && $contact->country_code) {
                    $countryCode = $contact->country_code;
                }
            }
            if ($countryCode === '+61' && preg_match('/^(\+\d{1,3})/', $to, $matches)) {
                $countryCode = $matches[1];
            } elseif ($countryCode === '+61' && preg_match('/^(\+\d{1,3})/', $formatted, $matches)) {
                $countryCode = $matches[1];
            }

            $smsLog = $this->logSmsActivity([
                'client_id' => $context['client_id'] ?? null,
                'client_contact_id' => $context['contact_id'] ?? null,
                'sender_id' => $context['sender_id'] ?? Auth::id(),
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

            if (isset($smsLog->id)) {
                $result['sms_log_id'] = $smsLog->id;
            }

            return $result;
        } catch (\Exception $e) {
            Log::error('UnifiedSmsManager: Exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'SMS service error: ' . $e->getMessage(),
            ];
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

        if (! $result['success'] && $this->isTemplateMissingError($result)) {
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
        if (! $template) {
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
        if (! $template) {
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

            if (! $template || ! $template->is_active) {
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
     */
    protected function sendFromTemplateModel($to, \App\Models\SmsTemplate $template, array $variables = [], array $context = []): array
    {
        $message = $this->replaceTemplateVariables($template->message, $variables);
        $context['template_id'] = $template->id;
        $type = $this->resolveMessageTypeFromCategory($template->category);

        $result = $this->sendSms($to, $message, $type, $context);
        if (! empty($result['success'])) {
            $template->increment('usage_count');
        }

        return $result;
    }

    protected function resolveMessageTypeFromCategory(?string $category): string
    {
        return in_array($category, ['verification', 'notification', 'manual', 'reminder'], true)
            ? $category
            : 'manual';
    }

    protected function isTemplateMissingError(array $result): bool
    {
        return str_contains($result['message'] ?? '', 'Template not found');
    }

    protected function replaceTemplateVariables($message, $variables)
    {
        foreach ($variables as $key => $value) {
            $message = str_replace('{' . $key . '}', (string) $value, $message);
        }

        return $message;
    }

    protected function logSmsActivity($data)
    {
        try {
            $smsLog = SmsLog::create($data);

            if (! empty($data['client_id'])) {
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
            Log::error('UnifiedSmsManager: Failed to log SMS activity', [
                'error' => $e->getMessage(),
                'data' => $data,
                'trace' => $e->getTraceAsString(),
            ]);

            return (object) ['id' => null];
        }
    }

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

    protected function formatActivityDescription($data)
    {
        $messageContent = trim($data['message_content']);
        $statusBadge = $data['status'] === 'sent'
            ? '<span class="badge bg-success">Sent</span>'
            : '<span class="badge bg-danger">Failed</span>';

        $providerBadge = '<span class="badge bg-info text-dark">' . strtoupper($data['provider']) . '</span>';

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
     * Get SMS delivery status from Cellcast
     */
    public function getDeliveryStatus($smsLogId)
    {
        try {
            $smsLog = SmsLog::find($smsLogId);

            if (! $smsLog) {
                return [
                    'success' => false,
                    'message' => 'SMS log not found',
                ];
            }

            if (! $smsLog->provider_message_id) {
                return [
                    'success' => false,
                    'message' => 'No provider message ID available',
                ];
            }

            $result = $this->cellcastService->getSmsStatus($smsLog->provider_message_id);

            if ($result['success'] && isset($result['status'])) {
                $updateData = ['status' => $result['status']];
                if ($result['status'] === 'delivered') {
                    $updateData['delivered_at'] = $smsLog->delivered_at ?? now();
                }
                $smsLog->update($updateData);
            }

            return $result;
        } catch (\Exception $e) {
            Log::error('UnifiedSmsManager: Status check error', [
                'sms_log_id' => $smsLogId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Status check failed',
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
