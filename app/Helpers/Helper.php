<?php

namespace App\Helpers;

use App\Company;
use Auth;
use Exception;
use Illuminate\Support\Facades\Log;

class Helper
{
    /**
     * Send SMS via the unified Cellcast-backed SMS manager.
     */
    public static function sendSms($receiverNumber, $message)
    {
        $receiverNumber = $receiverNumber ?? '+610422905860';

        try {
            $result = app(\App\Services\Sms\UnifiedSmsManager::class)
                ->sendSms($receiverNumber, $message, 'manual');

            if (! empty($result['success'])) {
                return json_encode('SMS sent successfully.');
            }

            return json_encode($result['message'] ?? 'SMS failed');
        } catch (Exception $e) {
            Log::error('Helper::sendSms failed', ['error' => $e->getMessage()]);

            return json_encode($e->getMessage());
        }
    }

    public static function changeDateFormate($date, $date_format)
    {
        return \Carbon\Carbon::createFromFormat('Y-m-d', $date)->format($date_format);
    }

    public static function getUserCompany(): ?object
    {
        $companyId = Auth::user()->comp_id;

        return Company::find($companyId);
    }
}
