<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PythonPDFService
{
    private $baseUrl;
    private $timeout;
    private $isAvailable = null;

    public function __construct()
    {
        $this->baseUrl = config('services.python_pdf.url', 'http://127.0.0.1:5000');
        $this->timeout = config('services.python_pdf.timeout', 30);
    }

    /**
     * Check if the Python PDF service is healthy and available
     *
     * @return bool
     */
    public function isHealthy(): bool
    {
        // Cache the availability check for 5 minutes
        if ($this->isAvailable !== null) {
            return $this->isAvailable;
        }

        try {
            $response = Http::timeout(15)->get($this->baseUrl . '/health');
            
            if ($response->successful()) {
                $data = $response->json();
                $this->isAvailable = ($data['status'] ?? '') === 'healthy';
                return $this->isAvailable;
            }
            
            $this->isAvailable = false;
            return false;
        } catch (\Exception $e) {
            Log::warning('Python PDF service is not available', [
                'error' => $e->getMessage(),
                'url' => $this->baseUrl
            ]);
            $this->isAvailable = false;
            return false;
        }
    }

    /**
     * Convert a PDF page to an image
     *
     * @param string $filePath Absolute path to the PDF file
     * @param int $pageNumber Page number (1-based)
     * @param int $resolution DPI resolution (default: 150)
     * @return array|null Returns ['success' => bool, 'image_data' => string] or null on failure
     */
    public function convertPageToImage(string $filePath, int $pageNumber, int $resolution = 150): ?array
    {
        if (!$this->isHealthy()) {
            Log::error('Python PDF service is not available for page conversion');
            return null;
        }

        try {
            // Normalize path for Windows
            $normalizedPath = str_replace('/', DIRECTORY_SEPARATOR, $filePath);
            
            $response = Http::timeout($this->timeout)
                ->post($this->baseUrl . '/convert_page', [
                    'file_path' => $normalizedPath,
                    'page_number' => $pageNumber,
                    'resolution' => $resolution
                ]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Failed to convert PDF page', [
                'file_path' => $filePath,
                'page_number' => $pageNumber,
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Error converting PDF page', [
                'file_path' => $filePath,
                'page_number' => $pageNumber,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get PDF information (page count, metadata, etc.)
     *
     * @param string $filePath Absolute path to the PDF file
     * @return array|null Returns PDF info array or null on failure
     */
    public function getPdfInfo(string $filePath): ?array
    {
        if (!$this->isHealthy()) {
            Log::error('Python PDF service is not available for PDF info');
            return null;
        }

        try {
            // Normalize path for Windows
            $normalizedPath = str_replace('/', DIRECTORY_SEPARATOR, $filePath);
            
            $response = Http::timeout($this->timeout)
                ->post($this->baseUrl . '/pdf_info', [
                    'file_path' => $normalizedPath
                ]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Failed to get PDF info', [
                'file_path' => $filePath,
                'status' => $response->status()
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Error getting PDF info', [
                'file_path' => $filePath,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Validate a PDF file
     *
     * @param string $filePath Absolute path to the PDF file
     * @return bool
     */
    public function validatePdf(string $filePath): bool
    {
        if (!$this->isHealthy()) {
            return false;
        }

        try {
            // Normalize path for Windows
            $normalizedPath = str_replace('/', DIRECTORY_SEPARATOR, $filePath);
            
            $response = Http::timeout($this->timeout)
                ->post($this->baseUrl . '/validate_pdf', [
                    'file_path' => $normalizedPath
                ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['valid'] ?? false;
            }

            return false;
        } catch (\Exception $e) {
            Log::error('Error validating PDF', [
                'file_path' => $filePath,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Add signatures to a PDF
     *
     * @param string $inputPath Input PDF path
     * @param string $outputPath Output PDF path
     * @param array $signatures Array of signature data
     * @return bool
     */
    public function addSignaturesToPdf(string $inputPath, string $outputPath, array $signatures): bool
    {
        if (!$this->isHealthy()) {
            Log::error('Python PDF service is not available for adding signatures');
            return false;
        }

        try {
            // Normalize paths for Windows
            $normalizedInputPath = str_replace('/', DIRECTORY_SEPARATOR, $inputPath);
            $normalizedOutputPath = str_replace('/', DIRECTORY_SEPARATOR, $outputPath);
            
            $response = Http::timeout($this->timeout)
                ->post($this->baseUrl . '/add_signatures', [
                    'input_path' => $normalizedInputPath,
                    'output_path' => $normalizedOutputPath,
                    'signatures' => $signatures
                ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['success'] ?? false;
            }

            Log::error('Failed to add signatures to PDF', [
                'input_path' => $inputPath,
                'output_path' => $outputPath,
                'status' => $response->status()
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error('Error adding signatures to PDF', [
                'input_path' => $inputPath,
                'output_path' => $outputPath,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Convert multiple pages to images in batch
     *
     * @param string $filePath Absolute path to the PDF file
     * @param array $pages Array of page numbers to convert
     * @param int $resolution DPI resolution (default: 150)
     * @return array|null Returns array of page_number => image_data or null on failure
     */
    public function batchConvertPages(string $filePath, array $pages, int $resolution = 150): ?array
    {
        if (!$this->isHealthy()) {
            Log::error('Python PDF service is not available for batch conversion');
            return null;
        }

        try {
            // Normalize path for Windows
            $normalizedPath = str_replace('/', DIRECTORY_SEPARATOR, $filePath);
            
            $response = Http::timeout($this->timeout * 2) // Double timeout for batch
                ->post($this->baseUrl . '/batch_convert', [
                    'file_path' => $normalizedPath,
                    'pages' => $pages,
                    'resolution' => $resolution
                ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['results'] ?? null;
            }

            Log::error('Failed to batch convert pages', [
                'file_path' => $filePath,
                'pages' => $pages,
                'status' => $response->status()
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Error batch converting pages', [
                'file_path' => $filePath,
                'pages' => $pages,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Normalize a PDF file for better compatibility
     *
     * @param string $inputPath Input PDF path
     * @param string $outputPath Output PDF path
     * @return bool
     */
    public function normalizePdf(string $inputPath, string $outputPath): bool
    {
        if (!$this->isHealthy()) {
            Log::error('Python PDF service is not available for PDF normalization');
            return false;
        }

        try {
            // Normalize paths for Windows
            $normalizedInputPath = str_replace('/', DIRECTORY_SEPARATOR, $inputPath);
            $normalizedOutputPath = str_replace('/', DIRECTORY_SEPARATOR, $outputPath);
            
            $response = Http::timeout($this->timeout)
                ->post($this->baseUrl . '/normalize_pdf', [
                    'input_path' => $normalizedInputPath,
                    'output_path' => $normalizedOutputPath
                ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['success'] ?? false;
            }

            Log::error('Failed to normalize PDF', [
                'input_path' => $inputPath,
                'output_path' => $outputPath,
                'status' => $response->status()
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error('Error normalizing PDF', [
                'input_path' => $inputPath,
                'output_path' => $outputPath,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get the service base URL
     *
     * @return string
     */
    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * Force reset the health check cache
     *
     * @return void
     */
    public function resetHealthCheck(): void
    {
        $this->isAvailable = null;
    }
}

