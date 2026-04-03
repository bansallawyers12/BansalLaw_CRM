<?php

namespace App\Http\Controllers\AdminConsole\Sms;

use App\Http\Controllers\Controller;
use App\Services\Sms\UnifiedSmsManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * SmsSendController
 * 
 * Handles manual SMS sending and bulk operations for AdminConsole
 */
class SmsSendController extends Controller
{
    protected $smsManager;

    public function __construct(UnifiedSmsManager $smsManager)
    {
        $this->middleware('auth:admin');
        $this->smsManager = $smsManager;
    }

    /**
     * Show manual SMS send form
     */
    public function create(Request $request)
    {
        return view('AdminConsole.features.sms.send.create');
    }

    /**
     * Send manual SMS (API endpoint - already used in client detail)
     */
    public function send(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string',
            'message' => 'required|string|max:1600',
            'client_id' => 'nullable|exists:admins,id',
            'contact_id' => 'nullable|exists:client_contacts,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $result = $this->smsManager->sendSms(
            $request->phone,
            $request->message,
            'manual',
            [
                'client_id' => $request->client_id,
                'contact_id' => $request->contact_id,
            ]
        );

        return response()->json($result);
    }

    /**
     * Send SMS from template (API endpoint)
     */
    public function sendFromTemplate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string',
            'template_id' => 'required|exists:sms_templates,id',
            'variables' => 'nullable|array',
            'client_id' => 'nullable|exists:admins,id',
            'contact_id' => 'nullable|exists:client_contacts,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $result = $this->smsManager->sendFromTemplate(
            $request->phone,
            $request->template_id,
            $request->variables ?? [],
            [
                'client_id' => $request->client_id,
                'contact_id' => $request->contact_id,
            ]
        );

        return response()->json($result);
    }

    /**
     * Send bulk SMS
     */
    public function sendBulk(Request $request)
    {
        // TODO: Implement bulk SMS feature
        // Will support:
        // - Multiple phone numbers
        // - CSV upload
        // - Template usage
        // - Scheduling
        
        return response()->json([
            'success' => false,
            'message' => 'Bulk SMS feature coming soon'
        ], 501);
    }
}
