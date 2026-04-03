<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\UploadedFile;
use Exception;

/**
 * Python Service Integration
 * 
 * This service provides integration with the unified Python services
 * for PDF processing, email parsing, analysis, and rendering.
 */
class PythonService
{
    private $baseUrl;
    private $timeout;
    private $maxRetries;

    public function __construct()
    {
        $this->baseUrl = config('services.python.url', 'http://localhost:5000');
        $this->timeout = config('services.python.timeout', 120);
        $this->maxRetries = config('services.python.max_retries', 3);
    }

    /**
     * Check if Python service is available
     */
    public function isHealthy(): bool
    {
        try {
            $response = Http::timeout(10)->get($this->baseUrl . '/health');
            return $response->successful() && $response->json('status') === 'healthy';
        } catch (Exception $e) {
            Log::warning('Python service health check failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Get service status and information
     */
    public function getStatus(): array
    {
        try {
            $response = Http::timeout(10)->get($this->baseUrl);
            return $response->json();
        } catch (Exception $e) {
            return [
                'service' => 'Migration Manager Python Services',
                'status' => 'unavailable',
                'error' => $e->getMessage()
            ];
        }
    }

    // ============================================================================
    // PDF Service Methods
    // ============================================================================

    /**
     * Convert PDF pages to images
     */
    public function convertPdfToImages(UploadedFile $file, int $dpi = 150): array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->attach('file', file_get_contents($file->getPathname()), $file->getClientOriginalName())
                ->post($this->baseUrl . '/pdf/convert-to-images', [
                    'dpi' => $dpi
                ]);

            if (!$response->successful()) {
                throw new Exception('PDF conversion failed: ' . $response->body());
            }

            return $response->json();
        } catch (Exception $e) {
            Log::error('PDF to images conversion failed', [
                'file' => $file->getClientOriginalName(),
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Merge multiple PDF files
     */
    public function mergePdfs(array $files): array
    {
        try {
            $multipart = [];
            foreach ($files as $index => $file) {
                $multipart[] = [
                    'name' => 'files',
                    'contents' => file_get_contents($file->getPathname()),
                    'filename' => $file->getClientOriginalName()
                ];
            }

            $response = Http::timeout($this->timeout)
                ->attach($multipart)
                ->post($this->baseUrl . '/pdf/merge');

            if (!$response->successful()) {
                throw new Exception('PDF merge failed: ' . $response->body());
            }

            return $response->json();
        } catch (Exception $e) {
            Log::error('PDF merge failed', [
                'file_count' => count($files),
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Convert a specific PDF page to an image
     * 
     * @param string $filePath Absolute path to the PDF file
     * @param int $pageNumber Page number (1-based)
     * @param int $resolution DPI resolution (default: 150)
     * @return array|null Returns ['success' => bool, 'image_data' => string] or null on failure
     */
    public function convertPageToImage(string $filePath, int $pageNumber, int $resolution = 150): ?array
    {
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
        } catch (Exception $e) {
            Log::error('Error converting PDF page', [
                'file_path' => $filePath,
                'page_number' => $pageNumber,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Add signatures to a PDF file
     * 
     * @param string $inputPath Input PDF path
     * @param string $outputPath Output PDF path
     * @param array $signatures Array of signature data
     * @return bool
     */
    public function addSignaturesToPdf(string $inputPath, string $outputPath, array $signatures): bool
    {
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
        } catch (Exception $e) {
            Log::error('Error adding signatures to PDF', [
                'input_path' => $inputPath,
                'output_path' => $outputPath,
                'error' => $e->getMessage()
            ]);
            return false;
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
        } catch (Exception $e) {
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
        } catch (Exception $e) {
            Log::error('Error validating PDF', [
                'file_path' => $filePath,
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
        } catch (Exception $e) {
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
        } catch (Exception $e) {
            Log::error('Error normalizing PDF', [
                'input_path' => $inputPath,
                'output_path' => $outputPath,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    // ============================================================================
    // Email Service Methods
    // ============================================================================

    /**
     * Parse .msg file and extract email data
     */
    public function parseEmail(UploadedFile $file): array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->attach('file', file_get_contents($file->getPathname()), $file->getClientOriginalName())
                ->post($this->baseUrl . '/email/parse');

            if (!$response->successful()) {
                throw new Exception('Email parsing failed: ' . $response->body());
            }

            return $response->json();
        } catch (Exception $e) {
            Log::error('Email parsing failed', [
                'file' => $file->getClientOriginalName(),
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Analyze email content for categorization, priority, sentiment, etc.
     */
    public function analyzeEmail(array $emailData): array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->post($this->baseUrl . '/email/analyze', $emailData);

            if (!$response->successful()) {
                throw new Exception('Email analysis failed: ' . $response->body());
            }

            return $response->json();
        } catch (Exception $e) {
            Log::error('Email analysis failed', [
                'subject' => $emailData['subject'] ?? 'Unknown',
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Render email with enhanced HTML and styling
     */
    public function renderEmail(array $emailData): array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->post($this->baseUrl . '/email/render', $emailData);

            if (!$response->successful()) {
                throw new Exception('Email rendering failed: ' . $response->body());
            }

            return $response->json();
        } catch (Exception $e) {
            Log::error('Email rendering failed', [
                'subject' => $emailData['subject'] ?? 'Unknown',
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Complete email processing pipeline: parse + analyze + render
     */
    public function processEmail(UploadedFile $file): array
    {
        try {
            $response = Http::timeout($this->timeout * 2) // Longer timeout for full pipeline
                ->attach('file', file_get_contents($file->getPathname()), $file->getClientOriginalName())
                ->post($this->baseUrl . '/email/parse-analyze-render');

            if (!$response->successful()) {
                throw new Exception('Email processing failed: ' . $response->body());
            }

            return $response->json();
        } catch (Exception $e) {
            Log::error('Email processing pipeline failed', [
                'file' => $file->getClientOriginalName(),
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    // ============================================================================
    // Utility Methods
    // ============================================================================

    /**
     * Test the Python service with a simple request
     */
    public function testConnection(): array
    {
        try {
            $response = Http::timeout(10)->get($this->baseUrl . '/health');
            
            if ($response->successful()) {
                return [
                    'success' => true,
                    'status' => $response->json('status'),
                    'services' => $response->json('services', []),
                    'response_time' => $response->transferStats->getHandlerStat('total_time')
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Service returned status: ' . $response->status(),
                    'body' => $response->body()
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get service configuration
     */
    public function getConfig(): array
    {
        return [
            'base_url' => $this->baseUrl,
            'timeout' => $this->timeout,
            'max_retries' => $this->maxRetries,
            'endpoints' => [
                'health' => $this->baseUrl . '/health',
                'pdf_convert' => $this->baseUrl . '/pdf/convert-to-images',
                'pdf_merge' => $this->baseUrl . '/pdf/merge',
                'email_parse' => $this->baseUrl . '/email/parse',
                'email_analyze' => $this->baseUrl . '/email/analyze',
                'email_render' => $this->baseUrl . '/email/render',
                'email_process' => $this->baseUrl . '/email/parse-analyze-render'
            ]
        ];
    }

    /**
     * Retry a request with exponential backoff
     */
    private function retryRequest(callable $request, int $maxRetries = null): mixed
    {
        $maxRetries = $maxRetries ?? $this->maxRetries;
        $attempt = 0;
        $lastException = null;

        while ($attempt < $maxRetries) {
            try {
                return $request();
            } catch (Exception $e) {
                $lastException = $e;
                $attempt++;
                
                if ($attempt < $maxRetries) {
                    $delay = pow(2, $attempt) * 1000; // Exponential backoff in milliseconds
                    usleep($delay * 1000); // Convert to microseconds
                    
                    Log::warning("Python service request failed, retrying", [
                        'attempt' => $attempt,
                        'max_retries' => $maxRetries,
                        'delay_ms' => $delay,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }

        throw $lastException;
    }
}
