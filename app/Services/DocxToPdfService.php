<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Exception;

class DocxToPdfService
{
    private $pythonApiUrl;
    private $timeout;

    public function __construct()
    {
        $this->pythonApiUrl = config('services.python_converter.url', 'http://localhost:5000');
        $this->timeout = config('services.python_converter.timeout', 300);
    }

    /**
     * Convert DOCX file to PDF
     *
     * @param string $docxPath Path to the DOCX file
     * @param string|null $outputPath Optional output path for PDF
     * @return array
     */
    public function convertDocxToPdf($docxPath, $outputPath = null)
    {
        try {
            // Validate input file
            if (!file_exists($docxPath)) {
                throw new Exception("DOCX file not found: {$docxPath}");
            }

            if (!in_array(strtolower(pathinfo($docxPath, PATHINFO_EXTENSION)), ['docx', 'doc'])) {
                throw new Exception("Invalid file format. Only DOCX and DOC files are supported.");
            }

            // Check file size (max 50MB)
            $fileSize = filesize($docxPath);
            if ($fileSize > 50 * 1024 * 1024) {
                throw new Exception("File size exceeds 50MB limit.");
            }

            // Prepare the file for upload
            $filename = basename($docxPath);
            
            // Make HTTP request to Python service
            $response = Http::timeout($this->timeout)
                ->attach('file', file_get_contents($docxPath), $filename)
                ->post($this->pythonApiUrl . '/convert');

            if (!$response->successful()) {
                throw new Exception("Python service error: " . $response->body());
            }

            $result = $response->json();

            if (!$result['success']) {
                throw new Exception("Conversion failed: " . ($result['message'] ?? 'Unknown error'));
            }

            // Decode base64 PDF data
            $pdfData = base64_decode($result['pdf_data']);
            
            // Save PDF file
            if ($outputPath) {
                file_put_contents($outputPath, $pdfData);
            } else {
                // Generate default output path
                $outputPath = storage_path('app/public/converted/' . pathinfo($filename, PATHINFO_FILENAME) . '.pdf');
                Storage::disk('public')->makeDirectory('converted');
                file_put_contents($outputPath, $pdfData);
            }

            return [
                'success' => true,
                'original_file' => $docxPath,
                'pdf_file' => $outputPath,
                'pdf_size' => strlen($pdfData),
                'message' => $result['message'] ?? 'Conversion completed successfully'
            ];

        } catch (Exception $e) {
            Log::error('DOCX to PDF conversion failed', [
                'file' => $docxPath,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Check if Python service is healthy
     *
     * @return bool
     */
    public function isServiceHealthy()
    {
        try {
            $response = Http::timeout(10)->get($this->pythonApiUrl . '/health');
            return $response->successful() && $response->json('libreoffice_available', false);
        } catch (Exception $e) {
            Log::error('Python service health check failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Test the conversion service with a sample file
     *
     * @return array
     */
    public function testService()
    {
        try {
            $response = Http::timeout(30)->get($this->pythonApiUrl . '/test');
            
            if ($response->successful()) {
                $result = $response->json();
                return [
                    'success' => true,
                    'test_result' => $result
                ];
            }

            return [
                'success' => false,
                'error' => 'Test failed: ' . $response->body()
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Test failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Convert DOCX file and return as download response
     *
     * @param string $docxPath
     * @param string|null $outputFilename
     * @return \Illuminate\Http\Response|null
     */
    public function convertAndDownload($docxPath, $outputFilename = null)
    {
        $result = $this->convertDocxToPdf($docxPath);
        
        if (!$result['success']) {
            return null;
        }

        $outputFilename = $outputFilename ?: pathinfo(basename($docxPath), PATHINFO_FILENAME) . '.pdf';
        
        return response()->download($result['pdf_file'], $outputFilename, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $outputFilename . '"'
        ])->deleteFileAfterSend();
    }
}
