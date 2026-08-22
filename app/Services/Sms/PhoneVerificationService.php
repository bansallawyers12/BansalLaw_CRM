<?php

namespace App\Services\Sms;

use App\Models\PhoneVerification;
use App\Models\ClientContact;
use App\Services\ContactVerificationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class PhoneVerificationService
{
    protected $smsManager;
    protected $otpValidMinutes = 5;
    protected $resendCooldownSeconds = 30;
    protected $maxAttemptsPerHour = 3;

    public function __construct(
        UnifiedSmsManager $smsManager,
        protected ContactVerificationService $contactVerification
    ) {
        $this->smsManager = $smsManager;
    }

    /**
     * Send OTP to phone number
     */
    public function sendOTP($contactId)
    {
        $contact = ClientContact::findOrFail($contactId);

        if ($this->isPlaceholderNumber($contact->phone)) {
            return [
                'success' => false,
                'message' => 'Cannot verify placeholder phone numbers',
            ];
        }

        if (! $contact->isAustralianNumber()) {
            return [
                'success' => false,
                'message' => 'Phone verification is only available for Australian numbers',
            ];
        }

        if (! $this->canSendOTP($contact)) {
            return [
                'success' => false,
                'message' => 'Too many OTP requests. Please try again later.',
            ];
        }

        $otpCode = PhoneVerification::generateOTP();
        $expiresAt = Carbon::now()->addMinutes($this->otpValidMinutes);

        $this->contactVerification->supersedePendingPhoneVerifications($contactId);

        $verification = PhoneVerification::create([
            'client_contact_id' => $contactId,
            'client_id' => $contact->client_id,
            'phone' => $contact->phone,
            'country_code' => $contact->country_code,
            'otp_code' => $otpCode,
            'status' => PhoneVerification::STATUS_PENDING,
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

        if (! $smsResult['success']) {
            $verification->update([
                'status' => PhoneVerification::STATUS_SUPERSEDED,
                'otp_sent_at' => null,
            ]);

            return [
                'success' => false,
                'message' => 'Failed to send SMS. Please try again.',
            ];
        }

        Log::info('OTP sent', [
            'contact_id' => $contactId,
            'phone' => $fullNumber,
            'expires_at' => $expiresAt,
        ]);

        return [
            'success' => true,
            'message' => 'Verification code sent successfully',
            'expires_at' => $expiresAt->toIso8601String(),
            'expires_in_seconds' => $this->otpValidMinutes * 60,
        ];
    }

    /**
     * Verify OTP
     */
    public function verifyOTP($contactId, $otpCode)
    {
        $verification = PhoneVerification::where('client_contact_id', $contactId)
            ->pending()
            ->latest()
            ->first();

        if (! $verification) {
            return [
                'success' => false,
                'message' => 'No verification request found',
            ];
        }

        if ($verification->isExpired()) {
            $verification->update(['status' => PhoneVerification::STATUS_EXPIRED]);

            return [
                'success' => false,
                'message' => 'Verification code has expired',
            ];
        }

        if (! $verification->canAttempt()) {
            if ($verification->status === PhoneVerification::STATUS_PENDING) {
                $verification->update(['status' => PhoneVerification::STATUS_MAX_ATTEMPTS]);
            }

            return [
                'success' => false,
                'message' => 'Maximum verification attempts exceeded',
            ];
        }

        if ((string) $verification->otp_code !== (string) $otpCode) {
            $verification->incrementAttempts();

            return [
                'success' => false,
                'message' => 'Invalid verification code',
                'attempts_remaining' => max(0, $verification->max_attempts - $verification->attempts),
            ];
        }

        $contact = ClientContact::find($contactId);
        if (! $contact) {
            return [
                'success' => false,
                'message' => 'Contact not found',
            ];
        }

        $verifiedBy = Auth::guard('admin')->id() ?? Auth::id();

        $verification->update([
            'status' => PhoneVerification::STATUS_VERIFIED,
            'is_verified' => true,
            'verified_at' => now(),
            'verified_by' => $verifiedBy,
        ]);

        $contact->update([
            'is_verified' => true,
            'verified_at' => now(),
            'verified_by' => $verifiedBy,
        ]);

        Log::info('Phone verified', [
            'contact_id' => $contactId,
            'verified_by' => $verifiedBy,
        ]);

        return [
            'success' => true,
            'message' => 'Phone number verified successfully',
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
            ->whereNotNull('otp_sent_at')
            ->latest('otp_sent_at')
            ->first();

        if (! $lastVerification) {
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
        $cleaned = preg_replace('/[^\d]/', '', $phone);

        return strpos($cleaned, '4444444444') === 0;
    }
}
