<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Convert Office documents to PDF via python_services (LibreOffice).
 * Used for high-fidelity CRM embed preview when soffice is installed.
 */
class OfficeToPdfConverterService
{
    private string $baseUrl;

    private int $timeout;

    private ?bool $available = null;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.python_pdf.url', 'http://127.0.0.1:5002'), '/');
        $this->timeout = max(30, (int) config('services.python_pdf.office_timeout', config('services.python.timeout', 180)));
    }

    public function isAvailable(): bool
    {
        if ($this->available !== null) {
            return $this->available;
        }

        try {
            $response = Http::timeout(8)->get($this->baseUrl.'/documents/converter-status');
            if ($response->successful()) {
                $this->available = (bool) ($response->json('available') ?? false);

                return $this->available;
            }
        } catch (\Throwable $e) {
            Log::debug('Office→PDF converter status check failed', [
                'error' => $e->getMessage(),
                'url' => $this->baseUrl,
            ]);
        }

        $this->available = false;

        return false;
    }

    /**
     * Convert Office file bytes to PDF. Returns null when converter unavailable or conversion fails.
     */
    public function convertToPdf(string $fileContent, string $filename): ?string
    {
        if ($fileContent === '' || ! $this->isAvailable()) {
            return null;
        }

        $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($filename)) ?: 'document.bin';

        try {
            $response = Http::timeout($this->timeout)
                ->attach('file', $fileContent, $safeName)
                ->post($this->baseUrl.'/documents/convert-to-pdf');

            if (! $response->successful()) {
                Log::warning('Office→PDF conversion rejected', [
                    'file' => $safeName,
                    'status' => $response->status(),
                    'body' => substr($response->body(), 0, 300),
                ]);

                // Don't keep probing as "available" if LibreOffice vanished mid-flight.
                if ($response->status() === 503) {
                    $this->available = false;
                }

                return null;
            }

            $pdf = $response->body();
            if ($pdf === '' || ! str_starts_with($pdf, '%PDF')) {
                Log::warning('Office→PDF conversion returned non-PDF payload', [
                    'file' => $safeName,
                    'bytes' => strlen($pdf),
                ]);

                return null;
            }

            return $pdf;
        } catch (\Throwable $e) {
            Log::warning('Office→PDF conversion error', [
                'file' => $safeName,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
