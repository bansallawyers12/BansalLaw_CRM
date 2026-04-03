<?php

namespace App\Http\Controllers\AdminConsole\Sms;

use App\Http\Controllers\Controller;
use App\Models\SmsLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * SmsWebhookController
 * 
 * Handles webhooks from SMS providers (Twilio, Cellcast) for AdminConsole
 * Used for delivery status updates and incoming messages
 */
class SmsWebhookController extends Controller
{
    /**
     * Handle Twilio webhook for delivery status
     */
    public function twilioStatus(Request $request)
    {
        Log::info('Twilio Status Webhook', $request->all());

        $messageSid = $request->input('MessageSid');
        $status = $request->input('MessageStatus');

        if (!$messageSid || !$status) {
            return response('Invalid webhook data', 400);
        }

        // Update SMS log
        $smsLog = SmsLog::where('provider_message_id', $messageSid)->first();

        if ($smsLog) {
            $smsLog->update([
                'status' => $status,
                'delivered_at' => in_array($status, ['delivered']) ? now() : null,
            ]);

            Log::info('SMS status updated', [
                'sms_log_id' => $smsLog->id,
                'status' => $status
            ]);
        }

        return response('OK', 200);
    }

    /**
     * Handle Twilio webhook for incoming messages
     */
    public function twilioIncoming(Request $request)
    {
        Log::info('Twilio Incoming Message', $request->all());

        // TODO: Implement incoming message handling in future sprints
        // Could be used for:
        // - Client responses
        // - Auto-reply system
        // - Keyword-based actions

        return response('OK', 200);
    }

    /**
     * Handle Cellcast webhook for delivery status
     */
    public function cellcastStatus(Request $request)
    {
        Log::info('Cellcast Status Webhook', $request->all());

        $messageId = $request->input('message_id');
        $status = $request->input('status');

        if (!$messageId || !$status) {
            return response('Invalid webhook data', 400);
        }

        // Update SMS log
        $smsLog = SmsLog::where('provider_message_id', $messageId)->first();

        if ($smsLog) {
            // Map Cellcast status to internal status
            $internalStatus = $this->mapCellcastStatus($status);
            
            $smsLog->update([
                'status' => $internalStatus,
                'delivered_at' => in_array($internalStatus, ['delivered']) ? now() : null,
            ]);

            Log::info('SMS status updated', [
                'sms_log_id' => $smsLog->id,
                'status' => $internalStatus
            ]);
        }

        return response('OK', 200);
    }

    /**
     * Handle Cellcast webhook for incoming messages
     */
    public function cellcastIncoming(Request $request)
    {
        Log::info('Cellcast Incoming Message', $request->all());

        // TODO: Implement incoming message handling in future sprints

        return response('OK', 200);
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
