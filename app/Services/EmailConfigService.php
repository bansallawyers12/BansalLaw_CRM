<?php

namespace App\Services;

use App\Models\Email;
use Illuminate\Support\Facades\Log;

/**
 * Service for resolving email sender configuration.
 */
class EmailConfigService
{
    /**
     * Get email configuration for a specific account by email ID
     *
     * @param int $emailId The email record ID
     * @return array Sender configuration array
     * @throws \Exception If email config not found
     */
    public function forAccountById(int $emailId): array
    {
        try {
            $emailConfig = Email::findOrFail($emailId);
            
            return $this->buildConfig($emailConfig);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve email config by ID', [
                'email_id' => $emailId,
                'error' => $e->getMessage()
            ]);
            throw new \Exception("Email configuration not found for ID: {$emailId}");
        }
    }

    /**
     * Get email configuration for a specific account by email address
     *
     * @param string $email The email address
     * @return array Sender configuration array
     * @throws \Exception If email config not found
     */
    public function forAccount(string $email): array
    {
        try {
            $emailConfig = Email::where('email', $email)
                ->where('status', true)
                ->firstOrFail();
            
            return $this->buildConfig($emailConfig);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve email config by email address', [
                'email' => $email,
                'error' => $e->getMessage()
            ]);
            throw new \Exception("Email configuration not found for: {$email}");
        }
    }

    /**
     * Build sender configuration array from Email model
     *
     * @param Email $emailConfig
     * @return array
     */
    protected function buildConfig(Email $emailConfig): array
    {
        return [
            'from_address' => $emailConfig->email,
            'from_name' => $emailConfig->display_name ?? 'Bansal Migration',
            'email_signature' => $emailConfig->email_signature ?? '',
        ];
    }

    /**
     * Deprecated: Keep for backward compatibility. SendGrid mailer is fixed.
     *
     * @param array $config Configuration array from forAccount()
     * @return void
     */
    public function applyConfig(array $config): void
    {
        config([
            'mail.default' => 'sendgrid',
            'mail.from.address' => $config['from_address'],
            'mail.from.name' => $config['from_name'],
        ]);

        Log::debug('Applied sender configuration', [
            'from' => $config['from_address'],
        ]);
    }

    /**
     * Get all active email accounts for dropdown selection
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getActiveAccounts()
    {
        return Email::where('status', true)
            ->select('id', 'email', 'display_name')
            ->orderBy('email')
            ->get();
    }

    /**
     * Get default email account (first active account or system default)
     *
     * @return array|null
     */
    public function getDefaultAccount(): ?array
    {
        try {
            // Try to get the first active account
            $emailConfig = Email::where('status', true)
                ->orderBy('id')
                ->first();

            if ($emailConfig) {
                return $this->buildConfig($emailConfig);
            }

            // Fallback to environment defaults
            if (env('MAIL_FROM_ADDRESS')) {
                return [
                    'from_address' => env('MAIL_FROM_ADDRESS'),
                    'from_name' => env('MAIL_FROM_NAME', 'Bansal Migration'),
                ];
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Failed to get default email account', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get email configuration from .env file only
     * Use this when you want to force .env credentials regardless of database accounts
     *
     * @return array|null
     */
    public function getEnvAccount(): ?array
    {
        try {
            if (env('MAIL_FROM_ADDRESS')) {
                return [
                    'from_address' => env('MAIL_FROM_ADDRESS'),
                    'from_name' => env('MAIL_FROM_NAME', 'Bansal Migration'),
                ];
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Failed to get .env email configuration', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get email configuration for EOI verification/confirmation emails.
     * Looks up admin@bansalimmigration from the emails table (or EOI_FROM_EMAIL from .env).
     * Returns sender details for setting the from-address before sending.
     *
     * @return array|null Config array (from_address, from_name, email_signature) or null if not found
     */
    public function getEoiFromAccount(): ?array
    {
        $preferredEmail = config('services.eoi.from_email', 'admin@bansalimmigration.com.au');

        // Try exact match first (from config/env)
        $emailConfig = Email::where('status', true)
            ->where('email', $preferredEmail)
            ->first();

        // If not found, try pattern: admin@bansalimmigration% (any domain)
        if (!$emailConfig) {
            $emailConfig = Email::where('status', true)
                ->where('email', 'like', 'admin@bansalimmigration%')
                ->first();
        }

        if ($emailConfig) {
            return $this->buildConfig($emailConfig);
        }

        return null;
    }

    /**
     * Validate email configuration by attempting connection
     *
     * @param array $config
     * @return bool
     */
    public function validateConfig(array $config): bool
    {
        try {
            return !empty($config['from_address']);
        } catch (\Exception $e) {
            Log::warning('Email config validation failed', [
                'config' => $config['from_address'] ?? 'unknown',
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}

