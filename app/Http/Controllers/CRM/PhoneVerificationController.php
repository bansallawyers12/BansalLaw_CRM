<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use App\Services\Sms\PhoneVerificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PhoneVerificationController extends Controller
{
    protected $verificationService;

    public function __construct(PhoneVerificationService $verificationService)
    {
        $this->middleware('auth:admin');
        $this->verificationService = $verificationService;
    }

    /**
     * Send OTP to phone number
     */
    public function sendOTP(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'contact_id' => 'required|exists:client_contacts,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first()
                ], 422);
            }

            $result = $this->verificationService->sendOTP($request->contact_id);

            return response()->json($result, $result['success'] ? 200 : 400);
        } catch (\Exception $e) {
            \Log::error('OTP Send Error', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while sending the verification code. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Verify OTP
     */
    public function verifyOTP(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'contact_id' => 'required|exists:client_contacts,id',
                'otp_code' => 'required|string|size:6'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first()
                ], 422);
            }

            $result = $this->verificationService->verifyOTP(
                $request->contact_id,
                $request->otp_code
            );

            return response()->json($result, $result['success'] ? 200 : 400);
        } catch (\Exception $e) {
            \Log::error('OTP Verification Error', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred during verification. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Resend OTP
     */
    public function resendOTP(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'contact_id' => 'required|exists:client_contacts,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 422);
        }

        if (!$this->verificationService->canResendOTP($request->contact_id)) {
            return response()->json([
                'success' => false,
                'message' => 'Please wait 30 seconds before requesting another code'
            ], 429);
        }

        $result = $this->verificationService->sendOTP($request->contact_id);

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    /**
     * Get verification status
     */
    public function getStatus(Request $request, $contactId)
    {
        $contact = \App\Models\ClientContact::find($contactId);

        if (!$contact) {
            return response()->json([
                'success' => false,
                'message' => 'Contact not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'is_verified' => $contact->is_verified,
            'verified_at' => $contact->verified_at,
            'needs_verification' => $contact->needsVerification()
        ]);
    }
}