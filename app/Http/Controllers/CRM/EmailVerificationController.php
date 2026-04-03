<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use App\Services\EmailVerificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class EmailVerificationController extends Controller
{
    protected $verificationService;

    public function __construct(EmailVerificationService $verificationService)
    {
        // Exclude verifyEmail from auth middleware (public access for clients)
        $this->middleware('auth:admin')->except(['verifyEmail']);
        $this->verificationService = $verificationService;
    }

    /**
     * Send verification email (Admin action)
     */
    public function sendVerificationEmail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email_id' => 'required|exists:client_emails,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 422);
        }

        $result = $this->verificationService->sendVerificationEmail($request->email_id);

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    /**
     * Verify email (Public action - client clicks link)
     */
    public function verifyEmail($token, Request $request)
    {
        $result = $this->verificationService->verifyToken(
            $token,
            $request->ip(),
            $request->userAgent()
        );

        if ($result['success']) {
            return view('emails.verification_success', [
                'clientEmail' => $result['client_email']
            ]);
        } else {
            return view('emails.verification_failed', [
                'message' => $result['message']
            ]);
        }
    }

    /**
     * Resend verification email
     */
    public function resendVerificationEmail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email_id' => 'required|exists:client_emails,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 422);
        }

        if (!$this->verificationService->canResendVerification($request->email_id)) {
            return response()->json([
                'success' => false,
                'message' => 'Please wait 2 minutes before resending verification email'
            ], 429);
        }

        $result = $this->verificationService->sendVerificationEmail($request->email_id);

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    /**
     * Get verification status
     */
    public function getStatus($emailId)
    {
        $clientEmail = \App\Models\ClientEmail::find($emailId);

        if (!$clientEmail) {
            return response()->json([
                'success' => false,
                'message' => 'Email not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'is_verified' => $clientEmail->is_verified,
            'verified_at' => $clientEmail->verified_at,
            'needs_verification' => $clientEmail->needsVerification()
        ]);
    }
}
