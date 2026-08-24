<?php

namespace App\Http\Controllers\AdminConsole\Sms;

use App\Http\Controllers\Controller;
use App\Models\SmsLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * SmsWebhookController
 *
 * Handles webhooks from Cellcast for delivery status and incoming messages.
 */
class SmsWebhookController extends Controller
{
    /**
     * Handle Cellcast webhook for delivery status
     */
    public function cellcastStatus(Request $request)
    {
        if (! $this->verifyCellcastSignature($request)) {
            return response('Unauthorized', 401);
        }

        Log::info('Cellcast Status Webhook', $request->all());

        $messageId = $request->input('message_id');
        $status = $request->input('status');

        if (! $messageId || ! $status) {
            return response('Invalid webhook data', 400);
        }

        $smsLog = SmsLog::where('provider_message_id', $messageId)->first();

        if ($smsLog) {
            $internalStatus = $this->mapCellcastStatus($status);

            $updateData = ['status' => $internalStatus];
            if ($internalStatus === 'delivered') {
                $updateData['delivered_at'] = $smsLog->delivered_at ?? now();
            }
            $smsLog->update($updateData);

            Log::info('SMS status updated', [
                'sms_log_id' => $smsLog->id,
                'status' => $internalStatus,
            ]);
        }

        return response('OK', 200);
    }

    /**
     * Handle Cellcast webhook for incoming messages
     */
    public function cellcastIncoming(Request $request)
    {
        if (! $this->verifyCellcastSignature($request)) {
            return response('Unauthorized', 401);
        }

        Log::info('Cellcast Incoming Message', $request->all());

        $from = $request->input('from') ?? $request->input('sender');
        $body = $request->input('message') ?? $request->input('text') ?? $request->input('content');
        $messageId = $request->input('message_id') ?? $request->input('id');

        if ($from && $body) {
            $contact = null;
            $cleanPhone = preg_replace('/[^\d]/', '', (string) $from);
            $lastDigits = strlen($cleanPhone) >= 9 ? substr($cleanPhone, -9) : $cleanPhone;
            if ($lastDigits !== '') {
                $contact = \App\Models\ClientContact::where('phone', 'LIKE', '%' . $lastDigits)->first();
            }

            SmsLog::create([
                'client_id' => $contact?->client_id ?? $contact?->admin_id,
                'client_contact_id' => $contact?->id,
                'sender_id' => null,
                'recipient_phone' => (string) $from,
                'formatted_phone' => (string) $from,
                'message_content' => (string) $body,
                'message_type' => 'notification',
                'provider' => 'cellcast',
                'provider_message_id' => $messageId,
                'status' => 'delivered',
                'sent_at' => now(),
                'delivered_at' => now(),
            ]);

            Log::info('Incoming Cellcast SMS saved', ['from' => $from, 'id' => $messageId]);
        }

        return response('OK', 200);
    }

    /**
     * Verify Cellcast webhook signature or secret token
     */
    protected function verifyCellcastSignature(Request $request): bool
    {
        $secret = config('services.cellcast.webhook_secret') ?: config('services.cellcast.api_key');
        if (empty($secret)) {
            Log::warning('Cellcast webhook signature verification skipped: CELLCAST_WEBHOOK_SECRET / CELLCAST_API_KEY is not set');

            return true;
        }

        $signature = $request->header('X-Cellcast-Signature')
            ?? $request->header('X-Signature')
            ?? $request->input('secret')
            ?? $request->input('token');

        if (empty($signature)) {
            if (app()->environment('testing')) {
                return true;
            }
            Log::warning('Cellcast webhook signature verification failed: missing signature/secret');

            return false;
        }

        if (hash_equals((string) $secret, (string) $signature)) {
            return true;
        }

        $computedSignature = hash_hmac('sha256', $request->getContent(), $secret);
        if (hash_equals($computedSignature, (string) $signature)) {
            return true;
        }

        Log::warning('Cellcast webhook signature verification failed: signature mismatch');

        return false;
    }

    /**
     * Map Cellcast status to internal status
     */
    protected function mapCellcastStatus($cellcastStatus)
    {
        $statusMap = [
            'SENT' => 'sent',
            'DELIVERED' => 'delivered',
            'FAILED' => 'failed',
            'REJECTED' => 'failed',
            'EXPIRED' => 'failed',
        ];

        return $statusMap[strtoupper($cellcastStatus)] ?? 'unknown';
    }
}
