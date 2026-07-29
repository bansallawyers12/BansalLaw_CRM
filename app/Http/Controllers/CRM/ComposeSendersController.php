<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use App\Models\Email;
use App\Models\Staff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * Returns compose "From" addresses for the CRM email UI.
 * Staff/personal senders come from the emails table (Zoho SMTP).
 * System senders (AWS SES) come from emails marked mail_provider=ses.
 */
class ComposeSendersController extends Controller
{
    /** @var list<string> */
    private const SYSTEM_MAIL_PROVIDERS = Email::SYSTEM_MAIL_PROVIDERS;

    /**
     * GET /crm/compose-senders
     */
    public function senders(Request $request)
    {
        $zohoSenders = $this->getZohoComposeSenders();
        $systemSenders = $this->getSystemComposeSenders();
        $signaturesByEmail = $this->getStaffSignaturesByEmail();

        $list = collect($zohoSenders)
            ->merge($systemSenders)
            ->unique('email')
            ->values()
            ->map(function (array $sender) use ($signaturesByEmail) {
                $emailKey = strtolower(trim($sender['email'] ?? ''));
                $sender['signature'] = $signaturesByEmail[$emailKey] ?? '';

                return $sender;
            })
            ->all();

        $defaultFrom = $this->resolveDefaultFrom($list);
        $currentUserSignature = $this->resolveCurrentUserSignature($signaturesByEmail);

        return response()->json([
            'senders' => $list,
            'default_from' => $defaultFrom,
            'signatures_by_email' => $signaturesByEmail,
            'current_user_signature' => $currentUserSignature,
        ]);
    }

    /**
     * GET /crm/staff-email-signature?from_email=
     * Returns the latest staff signature from the database (for compose/reply/forward).
     */
    public function staffSignature(Request $request)
    {
        if (! Schema::hasColumn('staff', 'email_signature')) {
            return response()->json(['signature' => '']);
        }

        $authUser = auth('admin')->user();
        if (! $authUser instanceof Staff) {
            return response()->json(['signature' => '']);
        }

        // Always use the logged-in staff member's signature first (reply/forward/compose).
        $signature = Staff::query()
            ->where('id', $authUser->id)
            ->where('status', 1)
            ->value('email_signature');

        $signature = trim((string) ($signature ?? ''));
        return response()->json(['signature' => $signature]);
    }

    private function getZohoComposeSenders(): array
    {
        return Email::query()
            ->where('status', true)
            ->where(function ($q) {
                $q->where('mail_provider', 'zoho')
                    ->orWhereNull('mail_provider')
                    ->orWhere('mail_provider', '');
            })
            ->orderBy('email')
            ->get()
            ->map(function (Email $account) {
                return [
                    'email' => $account->email,
                    'name' => $account->display_name ?: $account->email,
                    'nickname' => '',
                    'provider' => 'zoho',
                ];
            })
            ->all();
    }

    private function getSystemComposeSenders(): array
    {
        $senders = Email::query()
            ->where('status', true)
            ->whereIn('mail_provider', self::SYSTEM_MAIL_PROVIDERS)
            ->orderBy('email')
            ->get()
            ->map(function (Email $account) {
                return [
                    'email' => $account->email,
                    'name' => $account->display_name ?: $account->email,
                    'nickname' => '',
                    'provider' => 'ses',
                ];
            })
            ->all();

        $defaultFrom = strtolower(trim((string) config('mail.from.address', '')));
        if ($defaultFrom !== '' && ! collect($senders)->contains(fn ($s) => strcasecmp($s['email'], $defaultFrom) === 0)) {
            $senders[] = [
                'email' => config('mail.from.address'),
                'name' => config('mail.from.name', config('app.name')),
                'nickname' => '',
                'provider' => 'ses',
            ];
        }

        return $senders;
    }

    private function resolveDefaultFrom(array $list): string
    {
        $zohoEmails = collect($list)
            ->filter(fn ($s) => ($s['provider'] ?? '') === 'zoho')
            ->pluck('email')
            ->filter()
            ->values()
            ->all();

        $authEmail = optional(auth('admin')->user())->email;
        if ($authEmail && in_array($authEmail, $zohoEmails, true)) {
            return $authEmail;
        }

        if (! empty($zohoEmails)) {
            return $zohoEmails[0];
        }

        $preferred = (string) config('mail.from.address', '');
        if (blank($preferred)) {
            $preferred = $authEmail ?? '';
        }

        $emails = array_column($list, 'email');
        if (! empty($emails) && ! in_array($preferred, $emails, true)) {
            return $emails[0];
        }

        return $preferred;
    }

    /**
     * @return array<string, string> Lowercase email => HTML signature
     */
    private function getStaffSignaturesByEmail(): array
    {
        if (! Schema::hasColumn('staff', 'email_signature')) {
            return [];
        }

        return Staff::query()
            ->where('status', 1)
            ->whereNotNull('email_signature')
            ->where('email_signature', '!=', '')
            ->get(['email', 'email_signature'])
            ->mapWithKeys(function (Staff $staff) {
                $email = strtolower(trim((string) $staff->email));

                return $email !== '' ? [$email => (string) $staff->email_signature] : [];
            })
            ->all();
    }

    private function resolveCurrentUserSignature(array $signaturesByEmail): string
    {
        $authEmail = strtolower(trim((string) optional(auth('admin')->user())->email));

        return $authEmail !== '' ? ($signaturesByEmail[$authEmail] ?? '') : '';
    }
}
