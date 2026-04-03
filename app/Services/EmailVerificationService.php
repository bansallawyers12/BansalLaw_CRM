<?php

namespace App\Services;

use App\Models\EmailVerification;
use App\Models\ClientEmail;
use App\Mail\EmailVerificationMail;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;

class EmailVerificationService
{
    protected $tokenValidHours = 24;
    protected $maxAttemptsPerDay = 5;

    /**
     * Send verification email
     */
    public function sendVerificationEmail($emailId)
    {
        $clientEmail = ClientEmail::findOrFail($emailId);

        // Check if already verified
        if ($clientEmail->is_verified) {
            return [
                'success' => false,
                'message' => 'Email is already verified'
            ];
        }

        // Check rate limiting
        if (!$this->canSendVerification($clientEmail)) {
            return [
                'success' => false,
                'message' => 'Too many verification requests. Please try again tomorrow.'
            ];
        }

        // Generate token
        $token = EmailVerification::generateToken();
        $expiresAt = Carbon::now()->addHours($this->tokenValidHours);

        // Invalidate previous tokens
        EmailVerification::where('client_email_id', $emailId)
                        ->where('is_verified', false)
                        ->delete();

        // Create verification record
        $verification = EmailVerification::create([
            'client_email_id' => $emailId,
            'client_id' => $clientEmail->client_id,
            'email' => $clientEmail->email,
            'verification_token' => $token,
            'is_verified' => false,
            'token_sent_at' => now(),
            'token_expires_at' => $expiresAt,
        ]);

        // Update client email
        $clientEmail->update([
            'verification_token' => $token,
            'token_expires_at' => $expiresAt,
            'verification_sent_at' => now(),
        ]);

        // Generate verification URL
        $verificationUrl = route('clients.email.verify', ['token' => $token]);

        // Send email
        try {
            Mail::mailer('sendgrid')->to($clientEmail->email)->send(new EmailVerificationMail(
                $clientEmail,
                $verificationUrl,
                $expiresAt
            ));
        } catch (\Exception $e) {
            Log::error('Failed to send verification email', [
                'email_id' => $emailId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to send verification email. Please try again.'
            ];
        }

        Log::info('Verification email sent', [
            'email_id' => $emailId,
            'email' => $clientEmail->email,
            'expires_at' => $expiresAt
        ]);

        return [
            'success' => true,
            'message' => 'Verification email sent successfully',
            'expires_at' => $expiresAt->toIso8601String()
        ];
    }

    /**
     * Verify email using token
     */
    public function verifyToken($token, $ipAddress = null, $userAgent = null)
    {
        $verification = EmailVerification::where('verification_token', $token)
                                        ->where('is_verified', false)
                                        ->latest()
                                        ->first();

        if (!$verification) {
            return [
                'success' => false,
                'message' => 'Invalid or already used verification link'
            ];
        }

        // Check if expired
        if ($verification->isExpired()) {
            return [
                'success' => false,
                'message' => 'Verification link has expired. Please request a new one.'
            ];
        }

        // Mark as verified
        $verification->update([
            'is_verified' => true,
            'verified_at' => now(),
            'verified_by' => Auth::id() ?? null,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);

        // Update client email
        $clientEmail = ClientEmail::find($verification->client_email_id);
        $clientEmail->update([
            'is_verified' => true,
            'verified_at' => now(),
            'verified_by' => Auth::id() ?? null,
        ]);

        Log::info('Email verified', [
            'email_id' => $verification->client_email_id,
            'email' => $verification->email,
            'ip' => $ipAddress
        ]);

        return [
            'success' => true,
            'message' => 'Email verified successfully',
            'client_email' => $clientEmail
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
                                     ->latest('token_sent_at')
                                     ->value('token_sent_at');

        if (!$lastSent) {
            return true;
        }

        return Carbon::parse($lastSent)->addMinutes(2)->isPast();
    }
}
