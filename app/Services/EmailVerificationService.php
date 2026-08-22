<?php

namespace App\Services;

use App\Models\EmailVerification;
use App\Models\ClientEmail;
use App\Mail\EmailVerificationMail;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class EmailVerificationService
{
    protected $tokenValidHours = 24;
    protected $maxAttemptsPerDay = 5;

    public function __construct(
        protected MailRoutingService $mailRouting,
        protected ContactVerificationService $contactVerification
    ) {}

    /**
     * Send verification email
     */
    public function sendVerificationEmail($emailId)
    {
        $clientEmail = ClientEmail::findOrFail($emailId);

        if ($clientEmail->is_verified) {
            return [
                'success' => false,
                'message' => 'Email is already verified',
            ];
        }

        if (! $this->canSendVerification($clientEmail)) {
            return [
                'success' => false,
                'message' => 'Too many verification requests. Please try again tomorrow.',
            ];
        }

        $token = EmailVerification::generateToken();
        $expiresAt = Carbon::now()->addHours($this->tokenValidHours);

        $this->contactVerification->supersedePendingEmailVerifications($emailId);

        $verification = EmailVerification::create([
            'client_email_id' => $emailId,
            'client_id' => $clientEmail->client_id,
            'email' => $clientEmail->email,
            'verification_token' => $token,
            'status' => EmailVerification::STATUS_PENDING,
            'is_verified' => false,
            'token_sent_at' => now(),
            'token_expires_at' => $expiresAt,
        ]);

        $clientEmail->update([
            'verification_token' => $token,
            'token_expires_at' => $expiresAt,
            'verification_sent_at' => now(),
        ]);

        $verificationUrl = route('clients.email.verify', ['token' => $token]);

        try {
            $this->mailRouting->mailer(null, true)->to($clientEmail->email)->send(new EmailVerificationMail(
                $clientEmail,
                $verificationUrl,
                $expiresAt
            ));
        } catch (\Exception $e) {
            Log::error('Failed to send verification email', [
                'email_id' => $emailId,
                'error' => $e->getMessage(),
            ]);

            $verification->update([
                'status' => EmailVerification::STATUS_SUPERSEDED,
                'token_sent_at' => null,
            ]);

            $clientEmail->update([
                'verification_token' => null,
                'token_expires_at' => null,
                'verification_sent_at' => null,
            ]);

            return [
                'success' => false,
                'message' => 'Failed to send verification email. Please try again.',
            ];
        }

        Log::info('Verification email sent', [
            'email_id' => $emailId,
            'email' => $clientEmail->email,
            'expires_at' => $expiresAt,
        ]);

        return [
            'success' => true,
            'message' => 'Verification email sent successfully',
            'expires_at' => $expiresAt->toIso8601String(),
        ];
    }

    /**
     * Verify email using token
     */
    public function verifyToken($token, $ipAddress = null, $userAgent = null)
    {
        $verification = EmailVerification::where('verification_token', $token)
            ->pending()
            ->latest()
            ->first();

        if (! $verification) {
            return [
                'success' => false,
                'message' => 'Invalid or already used verification link',
            ];
        }

        if ($verification->isExpired()) {
            $verification->update(['status' => EmailVerification::STATUS_EXPIRED]);

            return [
                'success' => false,
                'message' => 'Verification link has expired. Please request a new one.',
            ];
        }

        $clientEmail = ClientEmail::find($verification->client_email_id);
        if (! $clientEmail) {
            $verification->update(['status' => EmailVerification::STATUS_SUPERSEDED]);

            return [
                'success' => false,
                'message' => 'Email record no longer exists',
            ];
        }

        $verifiedBy = Auth::guard('admin')->id() ?? Auth::id();

        $verification->update([
            'status' => EmailVerification::STATUS_VERIFIED,
            'is_verified' => true,
            'verified_at' => now(),
            'verified_by' => $verifiedBy,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);

        $clientEmail->update([
            'is_verified' => true,
            'verified_at' => now(),
            'verified_by' => $verifiedBy,
        ]);

        Log::info('Email verified', [
            'email_id' => $verification->client_email_id,
            'email' => $verification->email,
            'ip' => $ipAddress,
        ]);

        return [
            'success' => true,
            'message' => 'Email verified successfully',
            'client_email' => $clientEmail,
        ];
    }

    /**
     * Check rate limiting
     */
    protected function canSendVerification($clientEmail)
    {
        $recentAttempts = EmailVerification::where('email', $clientEmail->email)
            ->where('token_sent_at', '>', Carbon::now()->subDay())
            ->count();

        return $recentAttempts < $this->maxAttemptsPerDay;
    }

    /**
     * Check if can resend
     */
    public function canResendVerification($emailId)
    {
        $lastSent = EmailVerification::where('client_email_id', $emailId)
            ->whereNotNull('token_sent_at')
            ->latest('token_sent_at')
            ->value('token_sent_at');

        if (! $lastSent) {
            return true;
        }

        return Carbon::parse($lastSent)->addMinutes(2)->isPast();
    }
}
