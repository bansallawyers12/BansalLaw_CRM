<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ImprovedPdfConverterService
{
    private $pythonConverter;
    private $apiUrl;
    private $timeout;

    public function __construct()
    {
        $this->pythonConverter = app(\App\Services\PythonConverterService::class);
        $this->apiUrl = env('PYTHON_CONVERTER_URL', 'http://localhost:5000');
        $this->timeout = env('PYTHON_CONVERTER_TIMEOUT', 120);
    }

    /**
     * Convert Word document to PDF with high quality
     */
    public function convertToHighQualityPdf(UploadedFile $file, string $outputPath = null): array
    {
        try {
            // Validate file
            $this->validateFile($file);

            // Method 1: Try Python converter first (best quality)
            if ($this->pythonConverter->isApiAvailable()) {
                $result = $this->pythonConverter->convertToPdf($file);
                if ($result['success']) {
                    Log::info('PDF converted successfully using Python converter');
                    return $this->processSuccessfulConversion($result, $outputPath);
                }
            }

            // Method 2: Try LibreOffice with enhanced settings
            $result = $this->convertWithLibreOffice($file, $outputPath);
            if ($result['success']) {
                Log::info('PDF converted successfully using LibreOffice');
                return $result;
            }

            // Method 3: Try online conversion service as last resort
            $result = $this->convertWithOnlineService($file, $outputPath);
            if ($result['success']) {
                Log::info('PDF converted successfully using online service');
                return $result;
            }

            throw new \Exception('All conversion methods failed');

        } catch (\Exception $e) {
            Log::error('PDF conversion failed', [
                'error' => $e->getMessage(),
                'file' => $file->getClientOriginalName()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Convert using LibreOffice with enhanced settings
     */
    private function convertWithLibreOffice(UploadedFile $file, string $outputPath = null): array
    {
        try {
            $wordPath = $file->store('temp');
            $wordFullPath = storage_path('app/' . $wordPath);
            $pdfFullPath = preg_replace('/\.(docx|doc)$/i', '.pdf', $wordFullPath);

            // Create LibreOffice profile directory for better control
            $libreOfficeProfileDir = storage_path('app/libreoffice_profile');
            if (!is_dir($libreOfficeProfileDir)) {
                mkdir($libreOfficeProfileDir, 0755, true);
            }

            // Detect OS and set LibreOffice path
            $libreOfficePath = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN')
                ? '"C:\\Program Files\\LibreOffice\\program\\soffice.exe"'
                : 'libreoffice';

            // Enhanced conversion command with quality and layout preservation settings
            $command = $libreOfficePath . ' --headless --convert-to pdf:writer_pdf_Export --outdir ' . escapeshellarg(dirname($wordFullPath)) . ' ' . escapeshellarg($wordFullPath);
            
            // Set environment variables for better quality
            putenv('LIBREOFFICE_PROFILE_DIR=' . storage_path('app/libreoffice_profile'));
            
            exec($command . ' 2>&1', $output, $resultCode);

            if (!file_exists($pdfFullPath) || $resultCode !== 0) {
                throw new \Exception('LibreOffice conversion failed: ' . implode("\n", $output));
            }

            $pdfData = file_get_contents($pdfFullPath);

            // Clean up temp files
            @unlink($wordFullPath);
            @unlink($pdfFullPath);

            // Save to output path if provided
            if ($outputPath) {
                file_put_contents($outputPath, $pdfData);
            }

            return [
                'success' => true,
                'pdf_data' => $pdfData,
                'filename' => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME) . '.pdf',
                'method' => 'libreoffice',
                'message' => 'Converted using LibreOffice with enhanced settings'
            ];

        } catch (\Exception $e) {
            // Clean up temp files on error
            if (isset($wordFullPath) && file_exists($wordFullPath)) {
                @unlink($wordFullPath);
            }
            if (isset($pdfFullPath) && file_exists($pdfFullPath)) {
                @unlink($pdfFullPath);
            }

            return [
                'success' => false,
                'error' => 'LibreOffice conversion failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Convert using online service as fallback
     */
    private function convertWithOnlineService(UploadedFile $file, string $outputPath = null): array
    {
        try {
            // This is a placeholder for online conversion service
            // You can integrate with services like CloudConvert, Zamzar, etc.
            throw new \Exception('Online conversion service not configured');

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Online conversion failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Process successful conversion result
     */
    private function processSuccessfulConversion(array $result, string $outputPath = null): array
    {
        if ($outputPath) {
            file_put_contents($outputPath, $result['pdf_data']);
        }

        return $result;
    }

    /**
     * Validate uploaded file
     */
    private function validateFile(UploadedFile $file): void
    {
        $allowedExtensions = ['doc', 'docx'];
        $extension = strtolower($file->getClientOriginalExtension());

        if (!in_array($extension, $allowedExtensions)) {
            throw new \Exception("Unsupported file format. Allowed formats: " . implode(', ', $allowedExtensions));
        }

        $maxSize = 50 * 1024 * 1024; // 50MB
        if ($file->getSize() > $maxSize) {
            throw new \Exception("File too large. Maximum size is 50MB.");
        }
    }

    /**
     * Get conversion service status
     */
    public function getServiceStatus(): array
    {
        $status = [
            'python_converter' => $this->pythonConverter->isApiAvailable(),
            'libreoffice' => $this->isLibreOfficeAvailable(),
            'online_service' => false // Configure as needed
        ];

        return [
            'available' => in_array(true, $status),
            'services' => $status
        ];
    }

    /**
     * Check if LibreOffice is available
     */
    private function isLibreOfficeAvailable(): bool
    {
        $libreOfficePath = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN')
            ? '"C:\\Program Files\\LibreOffice\\program\\soffice.exe"'
            : 'libreoffice';

        exec($libreOfficePath . ' --version 2>/dev/null', $output, $resultCode);
        return $resultCode === 0;
    }
}
