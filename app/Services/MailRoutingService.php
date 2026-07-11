<?php

namespace App\Services;

use App\Models\Email;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Routes CRM outbound mail: Zoho SMTP for staff/personal senders, AWS SES for system mail.
 */
class MailRoutingService
{
    /** @var list<string> Legacy mail_provider values that use the system (SES) mailer. */
    private const SYSTEM_MAIL_PROVIDERS = Email::SYSTEM_MAIL_PROVIDERS;

    public function systemMailerName(): string
    {
        return (string) config('mail_routing.system_mailer', 'ses');
    }

    public function personalMailerName(): string
    {
        return (string) config('mail_routing.personal_mailer', 'zoho');
    }

    /**
     * Default From address for automated / system emails (AWS SES).
     */
    public function systemFromAddress(): string
    {
        return (string) config('mail.from.address');
    }

    public function systemFromName(): string
    {
        return (string) config('mail.from.name', config('app.name'));
    }

    /**
     * Resolve Laravel mailer name for a given From address.
     */
    public function resolveMailerName(?string $fromAddress, bool $forceSystem = false): string
    {
        if ($forceSystem || blank($fromAddress)) {
            return $this->systemMailerName();
        }

        $fromAddress = strtolower(trim($fromAddress));

        if ($this->isSystemFromAddress($fromAddress)) {
            return $this->systemMailerName();
        }

        $account = Email::whereRaw('LOWER(email) = ?', [$fromAddress])->first();

        if ($account) {
            if ($this->isSystemMailProvider($account->mail_provider)) {
                return $this->systemMailerName();
            }

            if (! $account->status) {
                Log::warning('Inactive email account used as sender; falling back to system mailer', [
                    'from' => $fromAddress,
                ]);

                return $this->systemMailerName();
            }

            return $this->personalMailerName();
        }

        return $this->systemMailerName();
    }

    public function isSystemFromAddress(string $fromAddress): bool
    {
        $fromAddress = strtolower(trim($fromAddress));

        foreach ((array) config('mail_routing.system_from_addresses', []) as $explicit) {
            if (strtolower(trim((string) $explicit)) === $fromAddress) {
                return true;
            }
        }

        foreach ((array) config('mail_routing.system_from_patterns', []) as $pattern) {
            if ($pattern !== '' && str_starts_with($fromAddress, strtolower($pattern))) {
                return true;
            }
        }

        $systemDefault = strtolower(trim($this->systemFromAddress()));
        if ($systemDefault !== '' && $fromAddress === $systemDefault) {
            $account = Email::whereRaw('LOWER(email) = ?', [$fromAddress])->first();
            if (! $account || $this->isSystemMailProvider($account->mail_provider)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Apply per-account Zoho SMTP credentials before sending.
     */
    public function configureZohoMailer(string $fromAddress): void
    {
        $fromAddress = strtolower(trim($fromAddress));
        $account = Email::whereRaw('LOWER(email) = ?', [$fromAddress])->first();

        $host = $account?->smtp_host ?: config('mail.mailers.zoho.host', 'smtp.zoho.com');
        $port = (int) ($account?->smtp_port ?: config('mail.mailers.zoho.port', 587));
        $encryption = $account?->smtp_encryption ?: config('mail.mailers.zoho.encryption', 'tls');
        $username = $account?->email ?: $fromAddress;
        $password = $account?->password ?: config('mail.mailers.zoho.password');

        if (blank($password)) {
            Log::warning('Zoho SMTP password missing for sender', ['from' => $fromAddress]);
        }

        config([
            'mail.mailers.zoho.host' => $host,
            'mail.mailers.zoho.port' => $port,
            'mail.mailers.zoho.encryption' => $encryption,
            'mail.mailers.zoho.username' => $username,
            'mail.mailers.zoho.password' => $password,
        ]);

        $this->purgeMailer($this->personalMailerName());
    }

    public function purgeMailer(string $mailerName): void
    {
        app('mail.manager')->purge($mailerName);
    }

    /**
     * Return a configured mailer instance for the given From address.
     */
    public function mailer(?string $fromAddress = null, bool $forceSystem = false): Mailer
    {
        $mailerName = $this->resolveMailerName($fromAddress, $forceSystem);

        if ($mailerName === $this->personalMailerName() && ! blank($fromAddress)) {
            $this->configureZohoMailer($fromAddress);
        }

        return Mail::mailer($mailerName);
    }

    /**
     * Send immediately using the correct mailer for the From address.
     */
    public function sendTo(
        string|array $to,
        \Closure|\Illuminate\Contracts\Mail\Mailable|string $view,
        ?string $fromAddress = null,
        bool $forceSystem = false,
        array $viewData = [],
        ?\Closure $callback = null
    ): void {
        $mailer = $this->mailer($fromAddress, $forceSystem);

        if ($view instanceof \Illuminate\Contracts\Mail\Mailable) {
            $mailer->to($to)->sendNow($view);

            return;
        }

        if ($callback) {
            $mailer->send($view, $viewData, $callback);

            return;
        }

        $mailer->to($to)->send($view, $viewData);
    }

    /**
     * Queue a mailable via SendCrmEmailJob so Zoho credentials are applied at send time.
     */
    public function queueTo(string|array $to, \Illuminate\Contracts\Mail\Mailable $mailable, ?string $fromAddress = null, bool $forceSystem = false): void
    {
        \App\Jobs\SendCrmEmailJob::dispatch($to, $mailable, $fromAddress, $forceSystem);
    }

    /**
     * Send a closure-based message (compose / signatures).
     */
    public function sendClosure(string $view, array $data, \Closure $callback, ?string $fromAddress = null, bool $forceSystem = false): void
    {
        $this->mailer($fromAddress, $forceSystem)->send($view, $data, $callback);
    }

    private function isSystemMailProvider(?string $provider): bool
    {
        return in_array((string) $provider, self::SYSTEM_MAIL_PROVIDERS, true);
    }
}
