<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Concerns\EnsuresCrmRecordAccess;
use App\Http\Controllers\Controller;
use App\Models\ClientContact;
use App\Models\Staff;
use App\Services\Sms\PhoneVerificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;

class PhoneVerificationController extends Controller
{
    use EnsuresCrmRecordAccess;

    protected $verificationService;

    public function __construct(PhoneVerificationService $verificationService)
    {
        $this->middleware('auth:admin');
        $this->verificationService = $verificationService;
    }

    /**
     * Ensure the authenticated staff user is active (status === 1).
     */
    protected function ensureActiveStaff(): ?\Illuminate\Http\JsonResponse
    {
        $staff = Auth::guard('admin')->user();
        if (! $staff instanceof Staff || (int) $staff->status !== 1) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Inactive staff account',
            ], 403);
        }

        return null;
    }

    /**
     * Send OTP to phone number
     */
    public function sendOTP(Request $request)
    {
        if ($denied = $this->ensureActiveStaff()) {
            return $denied;
        }

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

            $staff = Auth::guard('admin')->user();
            $sendKey = 'phone_otp_send:' . ($staff ? $staff->id : $request->ip());
            if (RateLimiter::tooManyAttempts($sendKey, 6)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Too many OTP requests. Please wait a minute before requesting another code.',
                ], 429);
            }
            RateLimiter::hit($sendKey, 60);

            $contact = ClientContact::findOrFail($request->contact_id);
            $this->ensureCrmRecordAccess((int) ($contact->client_id ?? $contact->admin_id));

            $result = $this->verificationService->sendOTP($request->contact_id);

            return response()->json($result, $result['success'] ? 200 : 400);
        } catch (\Exception $e) {
            if ($e instanceof \Illuminate\Http\Exceptions\HttpResponseException) {
                throw $e;
            }
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
        if ($denied = $this->ensureActiveStaff()) {
            return $denied;
        }

        try {
            $validator = Validator::make($request->all(), [
                'contact_id' => 'required|exists:client_contacts,id',
                'otp_code' => ['required', 'string', 'size:6', 'regex:/^[0-9]{6}$/'],
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first()
                ], 422);
            }

            $verifyKey = 'phone_otp_verify:' . $request->ip() . ':' . $request->contact_id;
            if (RateLimiter::tooManyAttempts($verifyKey, 10)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Too many verification attempts. Please wait a moment before trying again.',
                ], 429);
            }
            RateLimiter::hit($verifyKey, 60);

            $contact = ClientContact::findOrFail($request->contact_id);
            $this->ensureCrmRecordAccess((int) ($contact->client_id ?? $contact->admin_id));

            $cleanOtp = trim((string) $request->otp_code);

            $result = $this->verificationService->verifyOTP(
                $request->contact_id,
                $cleanOtp
            );

            if (! empty($result['success'])) {
                RateLimiter::clear($verifyKey);
            }

            return response()->json($result, $result['success'] ? 200 : 400);
        } catch (\Exception $e) {
            if ($e instanceof \Illuminate\Http\Exceptions\HttpResponseException) {
                throw $e;
            }
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
        if ($denied = $this->ensureActiveStaff()) {
            return $denied;
        }

        $validator = Validator::make($request->all(), [
            'contact_id' => 'required|exists:client_contacts,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 422);
        }

        $staff = Auth::guard('admin')->user();
        $sendKey = 'phone_otp_send:' . ($staff ? $staff->id : $request->ip());
        if (RateLimiter::tooManyAttempts($sendKey, 6)) {
            return response()->json([
                'success' => false,
                'message' => 'Too many OTP requests. Please wait a minute before requesting another code.',
            ], 429);
        }
        RateLimiter::hit($sendKey, 60);

        $contact = ClientContact::findOrFail($request->contact_id);
        $this->ensureCrmRecordAccess((int) ($contact->client_id ?? $contact->admin_id));

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
        if ($denied = $this->ensureActiveStaff()) {
            return $denied;
        }

        $contact = ClientContact::find($contactId);

        if (!$contact) {
            return response()->json([
                'success' => false,
                'message' => 'Contact not found'
            ], 404);
        }

        $this->ensureCrmRecordAccess((int) ($contact->client_id ?? $contact->admin_id));

        return response()->json([
            'success' => true,
            'is_verified' => $contact->is_verified,
            'verified_at' => $contact->verified_at,
            'needs_verification' => $contact->needsVerification()
        ]);
    }
}