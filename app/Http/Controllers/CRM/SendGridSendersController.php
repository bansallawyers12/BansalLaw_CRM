<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use App\Models\Email;
use App\Models\Staff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Returns compose "From" addresses for the CRM email UI.
 * Staff/personal senders come from the emails table (Zoho SMTP).
 * SendGrid verified senders are included only when marked mail_provider=sendgrid.
 */
class SendGridSendersController extends Controller
{
    /**
     * GET /crm/sendgrid-senders
     */
    public function senders(Request $request)
    {
        $zohoSenders = $this->getZohoComposeSenders();
        $sendgridSenders = $this->getSendGridVerifiedSenders();
        $signaturesByEmail = $this->getStaffSignaturesByEmail();

        $list = collect($zohoSenders)
            ->merge($sendgridSenders)
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

        $fromEmail = strtolower(trim((string) $request->query('from_email', '')));

        if ($fromEmail !== '') {
            $fromSignature = Staff::query()
                ->where('status', 1)
                ->whereRaw('LOWER(TRIM(email)) = ?', [$fromEmail])
                ->whereNotNull('email_signature')
                ->where('email_signature', '!=', '')
                ->value('email_signature');

            if ($fromSignature !== null && trim((string) $fromSignature) !== '') {
                return response()->json(['signature' => (string) $fromSignature]);
            }
        }

        $signature = Staff::query()
            ->where('id', $authUser->id)
            ->where('status', 1)
            ->value('email_signature');

        return response()->json([
            'signature' => trim((string) ($signature ?? '')),
        ]);
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

    private function getSendGridVerifiedSenders(): array
    {
        $apiKey = config('services.sendgrid.api_key');
        $baseUrl = rtrim(config('services.sendgrid.base_url', 'https://api.sendgrid.com'), '/');
        $senders = [];

        $explicitSendgrid = Email::query()
            ->where('status', true)
            ->where('mail_provider', 'sendgrid')
            ->orderBy('email')
            ->get();

        foreach ($explicitSendgrid as $account) {
            $senders[] = [
                'email' => $account->email,
                'name' => $account->display_name ?: $account->email,
                'nickname' => '',
                'provider' => 'sendgrid',
            ];
        }

        if (empty($apiKey)) {
            return $senders;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
            ])->timeout(10)->get($baseUrl . '/v3/verified_senders');

            if ($response->successful()) {
                foreach (($response->json()['results'] ?? []) as $sender) {
                    if (! empty($sender['from_email']) && (isset($sender['verified']) ? $sender['verified'] : true)) {
                        $email = $sender['from_email'];
                        if (! collect($senders)->contains(fn ($s) => strcasecmp($s['email'], $email) === 0)) {
                            $senders[] = [
                                'email' => $email,
                                'name' => $sender['from_name'] ?? $sender['nickname'] ?? $email,
                                'nickname' => $sender['nickname'] ?? '',
                                'provider' => 'sendgrid',
                            ];
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('SendGrid verified senders fetch failed: ' . $e->getMessage());
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

        $preferred = config('services.sendgrid.from_email', '');
        if (blank($preferred)) {
            $preferred = $authEmail ?? config('mail.from.address', '');
        }

        $emails = array_column($list, 'email');
        if (! empty($emails) && ! in_array($preferred, $emails, true)) {
            return $emails[0];
        }

        return (string) $preferred;
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
