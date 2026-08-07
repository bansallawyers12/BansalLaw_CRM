<?php

namespace App\Services\CalendarSync;

use App\Models\ZohoCalendarConnection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Zoho OAuth for Calendar API (accounts.zoho.com.au by default).
 */
class ZohoCalendarOAuthService
{
    public function isConfigured(): bool
    {
        return filled(config('zoho_calendar.client_id'))
            && filled(config('zoho_calendar.client_secret'))
            && filled(config('zoho_calendar.redirect_uri'));
    }

    public function authorizationUrl(string $state): string
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Zoho Calendar OAuth is not configured. Set ZOHO_CALENDAR_CLIENT_ID and ZOHO_CALENDAR_CLIENT_SECRET.');
        }

        $query = http_build_query([
            'scope' => config('zoho_calendar.scopes'),
            'client_id' => config('zoho_calendar.client_id'),
            'response_type' => 'code',
            'access_type' => 'offline',
            'redirect_uri' => config('zoho_calendar.redirect_uri'),
            'prompt' => 'consent',
            'state' => $state,
        ], '', '&', PHP_QUERY_RFC3986);

        return rtrim((string) config('zoho_calendar.accounts_url'), '/') . '/oauth/v2/auth?' . $query;
    }

    public function makeState(): string
    {
        return Str::random(40);
    }

    /**
     * @return array{access_token: string, refresh_token?: string, expires_in?: int, api_domain?: string, accounts_server?: string}
     */
    public function exchangeCode(string $code): array
    {
        $response = Http::asForm()
            ->timeout(30)
            ->post(rtrim((string) config('zoho_calendar.accounts_url'), '/') . '/oauth/v2/token', [
                'grant_type' => 'authorization_code',
                'client_id' => config('zoho_calendar.client_id'),
                'client_secret' => config('zoho_calendar.client_secret'),
                'redirect_uri' => config('zoho_calendar.redirect_uri'),
                'code' => $code,
            ]);

        if (! $response->successful()) {
            Log::warning('Zoho Calendar OAuth code exchange failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new RuntimeException('Failed to exchange Zoho authorization code (' . $response->status() . ').');
        }

        $data = $response->json();
        if (! is_array($data) || empty($data['access_token'])) {
            throw new RuntimeException('Zoho token response did not include an access_token.');
        }

        return $data;
    }

    /**
     * @return array{access_token: string, expires_in?: int, api_domain?: string}
     */
    public function refreshAccessToken(ZohoCalendarConnection $connection): array
    {
        $refresh = $connection->refresh_token;
        if (! $refresh) {
            throw new RuntimeException('No Zoho refresh token on file. Reconnect Zoho Calendar OAuth.');
        }

        $accounts = rtrim((string) ($connection->accounts_server ?: config('zoho_calendar.accounts_url')), '/');

        $response = Http::asForm()
            ->timeout(30)
            ->post($accounts . '/oauth/v2/token', [
                'grant_type' => 'refresh_token',
                'client_id' => config('zoho_calendar.client_id'),
                'client_secret' => config('zoho_calendar.client_secret'),
                'refresh_token' => $refresh,
            ]);

        if (! $response->successful()) {
            Log::warning('Zoho Calendar OAuth refresh failed', [
                'staff_id' => $connection->staff_id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new RuntimeException('Failed to refresh Zoho access token (' . $response->status() . ').');
        }

        $data = $response->json();
        if (! is_array($data) || empty($data['access_token'])) {
            throw new RuntimeException('Zoho refresh response did not include an access_token.');
        }

        return $data;
    }

    public function storeTokens(ZohoCalendarConnection $connection, array $tokenPayload): ZohoCalendarConnection
    {
        $expiresIn = (int) ($tokenPayload['expires_in'] ?? 3600);
        $connection->access_token = (string) $tokenPayload['access_token'];
        if (! empty($tokenPayload['refresh_token'])) {
            $connection->refresh_token = (string) $tokenPayload['refresh_token'];
        }
        $connection->expires_at = now()->addSeconds(max(60, $expiresIn - 30));
        if (! empty($tokenPayload['api_domain'])) {
            $connection->api_domain = (string) $tokenPayload['api_domain'];
        }
        if (! empty($tokenPayload['accounts_server'])) {
            $connection->accounts_server = (string) $tokenPayload['accounts_server'];
        }
        $connection->scopes = (string) ($tokenPayload['scope'] ?? config('zoho_calendar.scopes'));
        $connection->last_error = null;
        $connection->save();

        return $connection;
    }

    public function validAccessToken(ZohoCalendarConnection $connection): string
    {
        if (! $connection->isExpired()) {
            $token = $connection->access_token;
            if ($token) {
                return $token;
            }
        }

        $payload = $this->refreshAccessToken($connection);
        $this->storeTokens($connection, $payload);
        $token = $connection->fresh()->access_token;
        if (! $token) {
            throw new RuntimeException('Zoho access token missing after refresh.');
        }

        return $token;
    }
}
