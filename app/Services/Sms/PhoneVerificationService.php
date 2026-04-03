<?php

namespace App\Services\Sms;

use App\Models\PhoneVerification;
use App\Models\ClientContact;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class PhoneVerificationService
{
    protected $smsManager;
    protected $otpValidMinutes = 5;
    protected $resendCooldownSeconds = 30;
    protected $maxAttemptsPerHour = 3;

    public function __construct(UnifiedSmsManager $smsManager)
    {
        $this->smsManager = $smsManager;
    }

    /**
     * Send OTP to phone number
     */
    public function sendOTP($contactId)
    {
        $contact = ClientContact::findOrFail($contactId);

        // Check if it's a placeholder number
        if ($this->isPlaceholderNumber($contact->phone)) {
            return [
                'success' => false,
                'message' => 'Cannot verify placeholder phone numbers'
            ];
        }

        // Validate it's an Australian number
        if (!$contact->isAustralianNumber()) {
            return [
                'success' => false,
                'message' => 'Phone verification is only available for Australian numbers'
            ];
        }

        // Check rate limiting
        if (!$this->canSendOTP($contact)) {
            return [
                'success' => false,
                'message' => 'Too many OTP requests. Please try again later.'
            ];
        }

        // Generate OTP
        $otpCode = PhoneVerification::generateOTP();
        $expiresAt = Carbon::now()->addMinutes($this->otpValidMinutes);

        // Invalidate previous OTPs
        PhoneVerification::where('client_contact_id', $contactId)
                        ->where('is_verified', false)
                        ->delete();

        // Create new verification record
        $verification = PhoneVerification::create([
            'client_contact_id' => $contactId,
            'client_id' => $contact->client_id,
            'phone' => $contact->phone,
            'country_code' => $contact->country_code,
            'otp_code' => $otpCode,
            'otp_sent_at' => now(),
            'otp_expires_at' => $expiresAt,
            'is_verified' => false,
            'attempts' => 0,
            'max_attempts' => 3,
        ]);

        $fullNumber = $contact->country_code . $contact->phone;

        $smsContext = [
            'client_id' => $contact->client_id,
            'contact_id' => $contactId,
        ];

        $smsResult = $this->smsManager->sendFromTemplateByAlias($fullNumber, 'phone_verification', [
            'verification_code' => $otpCode,
            'expiry_minutes' => (string) $this->otpValidMinutes,
        ], $smsContext);

        if (! $smsResult['success'] && str_contains($smsResult['message'] ?? '', 'Template not found')) {
            $message = "BANSAL IMMIGRATION: Your phone verification code is {$otpCode}. Please provide this code to our staff to verify your phone number. This code expires in {$this->otpValidMinutes} minutes.";
            $smsResult = $this->smsManager->sendSms($fullNumber, $message, 'verification', $smsContext);
        }

        if (!$smsResult['success']) {
            $verification->delete();
            return [
                'success' => false,
                'message' => 'Failed to send SMS. Please try again.'
            ];
        }

        Log::info('OTP sent', [
            'contact_id' => $contactId,
            'phone' => $fullNumber,
            'expires_at' => $expiresAt
        ]);

        return [
            'success' => true,
            'message' => 'Verification code sent successfully',
            'expires_at' => $expiresAt->toIso8601String(),
            'expires_in_seconds' => $this->otpValidMinutes * 60
        ];
    }

    /**
     * Verify OTP
     */
    public function verifyOTP($contactId, $otpCode)
    {
        $verification = PhoneVerification::where('client_contact_id', $contactId)
                                        ->where('is_verified', false)
                                        ->latest()
                                        ->first();

        if (!$verification) {
            return [
                'success' => false,
                'message' => 'No verification request found'
            ];
        }

        // Check if expired
        if ($verification->isExpired()) {
            return [
                'success' => false,
                'message' => 'Verification code has expired'
            ];
        }

        // Check attempts
        if (!$verification->canAttempt()) {
            return [
                'success' => false,
                'message' => 'Maximum verification attempts exceeded'
            ];
        }

        // Verify OTP
        if ($verification->otp_code !== $otpCode) {
            $verification->incrementAttempts();
            return [
                'success' => false,
                'message' => 'Invalid verification code',
                'attempts_remaining' => $verification->max_attempts - $verification->attempts
            ];
        }

        // Mark as verified
        $verification->update([
            'is_verified' => true,
            'verified_at' => now(),
            'verified_by' => Auth::id()
        ]);

        // Update contact
        $contact = ClientContact::find($contactId);
        $contact->update([
            'is_verified' => true,
            'verified_at' => now(),
            'verified_by' => Auth::id()
        ]);

        Log::info('Phone verified', [
            'contact_id' => $contactId,
            'verified_by' => Auth::id()
        ]);

        return [
            'success' => true,
            'message' => 'Phone number verified successfully'
        ];
    }

    /**
     * Check if OTP can be sent (rate limiting)
     */
    protected function canSendOTP($contact)
    {
        $recentAttempts = PhoneVerification::forPhone($contact->phone, $contact->country_code)
                                          ->where('otp_sent_at', '>', Carbon::now()->subHour())
                                          ->count();

        return $recentAttempts < $this->maxAttemptsPerHour;
    }

    /**
     * Check if can resend (cooldown period)
     */
    public function canResendOTP($contactId)
    {
        $lastVerification = PhoneVerification::where('client_contact_id', $contactId)
                                            ->latest('otp_sent_at')
                                            ->first();

        if (!$lastVerification) {
            return true;
        }

        $timeSinceLastSend = Carbon::now()->diffInSeconds($lastVerification->otp_sent_at);
        return $timeSinceLastSend >= $this->resendCooldownSeconds;
    }

    /**
     * Check if phone number is a placeholder
     */
    protected function isPlaceholderNumber($phone)
    {
        // Remove any non-digit characters
        $cleaned = preg_replace('/[^\d]/', '', $phone);
        
        // Check if it starts with 4444444444 (placeholder pattern)
        return strpos($cleaned, '4444444444') === 0;
    }
}

