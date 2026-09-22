<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Resolve the reachable Python microservice base URL.
 *
 * Production historically mixed ports 5000 (legacy docs) and 5002 (current default).
 * Laravel often points at 5002 while an older systemd unit still binds 5000 — that
 * surfaces as cURL error 7 on email upload. This resolver probes the configured URL
 * then common alternates once per process and caches the winner.
 */
class PythonServiceUrlResolver
{
    private static ?string $cachedUrl = null;

    private static ?bool $cachedAvailable = null;

    public function baseUrl(bool $forceRefresh = false): string
    {
        $status = $this->resolve($forceRefresh);

        return $status['url'];
    }

    public function isAvailable(bool $forceRefresh = false): bool
    {
        return $this->resolve($forceRefresh)['available'];
    }

    /**
     * @return array{url: string, available: bool, probed: list<string>}
     */
    public function resolve(bool $forceRefresh = false): array
    {
        if (! $forceRefresh && self::$cachedUrl !== null && self::$cachedAvailable !== null) {
            return [
                'url' => self::$cachedUrl,
                'available' => self::$cachedAvailable,
                'probed' => [self::$cachedUrl],
            ];
        }

        $primary = rtrim((string) config('services.python.url', 'http://127.0.0.1:5002'), '/');
        $fallback = rtrim((string) config('services.python.fallback_url', ''), '/');

        $candidates = [];
        foreach ([$primary, $fallback, $this->swapPort($primary, 5002, 5000), $this->swapPort($primary, 5000, 5002)] as $candidate) {
            if ($candidate !== '' && ! in_array($candidate, $candidates, true)) {
                $candidates[] = $candidate;
            }
        }

        foreach ($candidates as $url) {
            if ($this->probe($url)) {
                if ($url !== $primary) {
                    Log::warning('Python service reachable on alternate URL; update PYTHON_SERVICE_URL', [
                        'configured' => $primary,
                        'reachable' => $url,
                    ]);
                }

                self::$cachedUrl = $url;
                self::$cachedAvailable = true;

                return [
                    'url' => $url,
                    'available' => true,
                    'probed' => $candidates,
                ];
            }
        }

        self::$cachedUrl = $primary !== '' ? $primary : 'http://127.0.0.1:5002';
        self::$cachedAvailable = false;

        return [
            'url' => self::$cachedUrl,
            'available' => false,
            'probed' => $candidates,
        ];
    }

    private function probe(string $url): bool
    {
        try {
            $response = Http::timeout(3)->connectTimeout(2)->get($url . '/health');

            if (! $response->successful()) {
                return false;
            }

            $status = $response->json('status');

            // Accept either explicit healthy status or a plain 200 from older builds.
            return $status === null || $status === 'healthy' || $status === 'ok';
        } catch (\Throwable) {
            return false;
        }
    }

    private function swapPort(string $url, int $from, int $to): string
    {
        if ($url === '') {
            return '';
        }

        $parts = parse_url($url);
        if (! is_array($parts) || ! isset($parts['host'])) {
            return '';
        }

        $port = $parts['port'] ?? null;
        if ($port === null) {
            // Default HTTP port implied — only rewrite when the string literally ends with :from
            if (! str_ends_with($url, ':' . $from)) {
                return '';
            }
        } elseif ((int) $port !== $from) {
            return '';
        }

        $scheme = $parts['scheme'] ?? 'http';
        $host = $parts['host'];

        return $scheme . '://' . $host . ':' . $to;
    }
}
