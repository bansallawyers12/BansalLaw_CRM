<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Returns SendGrid verified senders for the Compose Email "From" dropdown.
 * Used by partials/email-from-sendgrid-script.blade.php (AJAX on page load).
 */
class SendGridSendersController extends Controller
{
    /**
     * Return verified senders as JSON for AJAX (e.g. frontend populating From dropdown).
     * GET /crm/sendgrid-senders
     */
    public function senders(Request $request)
    {
        $list = $this->getVerifiedSenders();
        $fromEmail = config('services.sendgrid.from_email', '');
        if (empty($fromEmail)) {
            $fromEmail = optional(auth('admin')->user())->email ?? config('mail.from.address', '');
        }
        $emails = array_column($list, 'email');
        if (!empty($emails) && !in_array($fromEmail, $emails)) {
            $fromEmail = $emails[0];
        }
        return response()->json([
            'senders' => $list,
            'default_from' => $fromEmail,
        ]);
    }

    /**
     * Fetch verified senders from SendGrid API.
     */
    private function getVerifiedSenders(): array
    {
        $apiKey = config('services.sendgrid.api_key');
        $baseUrl = rtrim(config('services.sendgrid.base_url', 'https://api.sendgrid.com'), '/');
        $senders = [];

        if (empty($apiKey)) {
            Log::warning('SendGrid senders: SENDGRID_API_KEY not set in .env');
            return $this->getFallbackSendersFromEnv();
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
            ])->timeout(10)->get($baseUrl . '/v3/verified_senders');

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['results'])) {
                    foreach ($data['results'] as $sender) {
                        if (!empty($sender['from_email']) && (isset($sender['verified']) ? $sender['verified'] : true)) {
                            $senders[] = [
                                'email' => $sender['from_email'],
                                'name' => $sender['from_name'] ?? $sender['nickname'] ?? $sender['from_email'],
                                'nickname' => $sender['nickname'] ?? '',
                            ];
                        }
                    }
                }
            }

            if (empty($senders)) {
                $response2 = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $apiKey,
                ])->timeout(10)->get($baseUrl . '/v3/senders');

                if ($response2->successful()) {
                    $data2 = $response2->json();
                    $result = $data2['result'] ?? (is_array($data2) ? $data2 : []);
                    foreach (is_array($result) ? $result : [] as $sender) {
                        $email = $sender['from']['email'] ?? $sender['email'] ?? null;
                        if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                            $senders[] = [
                                'email' => $email,
                                'name' => $sender['from']['name'] ?? $sender['nickname'] ?? $email,
                                'nickname' => $sender['nickname'] ?? '',
                            ];
                        }
                    }
                }
            }

            if (empty($senders) && strpos($baseUrl, 'api.sendgrid.com') !== false) {
                $responseEu = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $apiKey,
                ])->timeout(10)->get('https://api.eu.sendgrid.com/v3/verified_senders');

                if ($responseEu->successful()) {
                    $data = $responseEu->json();
                    if (isset($data['results'])) {
                        foreach ($data['results'] as $sender) {
                            if (!empty($sender['from_email']) && (isset($sender['verified']) ? $sender['verified'] : true)) {
                                $senders[] = [
                                    'email' => $sender['from_email'],
                                    'name' => $sender['from_name'] ?? $sender['nickname'] ?? $sender['from_email'],
                                    'nickname' => $sender['nickname'] ?? '',
                                ];
                            }
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('SendGrid API Error: ' . $e->getMessage());
        }

        $senders = collect($senders)->unique('email')->values()->toArray();

        if (empty($senders)) {
            return $this->getFallbackSendersFromEnv();
        }

        return $senders;
    }

    /**
     * Fallback: use SENDGRID_SENDERS from .env (comma-separated emails).
     */
    private function getFallbackSendersFromEnv(): array
    {
        $fallbackSenders = config('services.sendgrid.senders');
        if (empty($fallbackSenders) || !is_string($fallbackSenders)) {
            return [];
        }
        $emails = array_filter(array_map('trim', explode(',', $fallbackSenders)));
        $list = [];
        foreach ($emails as $email) {
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $list[] = [
                    'email' => $email,
                    'name' => $email,
                    'nickname' => '',
                ];
            }
        }
        return $list;
    }
}
