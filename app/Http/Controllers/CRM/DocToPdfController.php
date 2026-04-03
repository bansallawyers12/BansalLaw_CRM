<?php
namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\PythonConverterService;

class DocToPdfController extends Controller
{
    protected $pythonConverter;

    public function __construct(PythonConverterService $pythonConverter)
    {
        $this->pythonConverter = $pythonConverter;
    }

    public function showForm()
    {
        // Check if running over HTTPS and show warning if not
        $isSecure = request()->isSecure();

        $response = response()->view('crm.upload', compact('isSecure'));

        // Add security headers
        return $this->addSecurityHeaders($response);
    }

    /**
     * Add security headers to response
     */
    private function addSecurityHeaders($response)
    {
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Only set HSTS header if using HTTPS
        if (request()->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }

    /**
     * Debug method to check current configuration
     */
    public function debugConfig()
    {
        $config = [
            'python_converter_url' => env('PYTHON_CONVERTER_URL', 'http://localhost:5000'),
            'python_converter_timeout' => env('PYTHON_CONVERTER_TIMEOUT', 120),
            'app_url' => url('/'),
        ];

        return response()->json($config);
    }

    /**
     * Convert DOCX to PDF using Python processing
     */
    public function convertLocal(Request $request)
    {
        $request->validate([
            'document' => 'required|mimes:doc,docx|max:51200', // 50MB max
        ]);

        $file = $request->file('document');
        $originalFileName = $file->getClientOriginalName();

        try {
            // Check if Python service is available
            if (!$this->pythonConverter->isApiAvailable()) {
                return back()->with('error', 'Conversion service is currently unavailable. Please try again later.');
            }

            // Convert using Python service
            $response = $this->pythonConverter->convertAndDownload($file);

            return $this->addSecurityHeaders($response);

        } catch (\Exception $e) {
            Log::error('Python conversion failed', [
                'error' => $e->getMessage(),
                'file' => $originalFileName
            ]);

            return back()->with('error', 'Conversion failed: ' . $e->getMessage());
        }
    }

    /**
     * Test local conversion functionality
     */
    public function testLocalConversion()
    {
        try {
            // Check if Python service is available
            $apiStatus = $this->pythonConverter->getApiStatus();

            $result = [
                'success' => $apiStatus['available'],
                'message' => $apiStatus['available'] ? 'Python conversion service is ready' : 'Python conversion service is not available',
                'api_status' => $apiStatus
            ];

            return response()->json($result);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Local conversion test failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test Python conversion service
     */
    public function testPythonConversion()
    {
        try {
            $result = $this->pythonConverter->testConversion();
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Python conversion test failed: ' . $e->getMessage()
            ], 500);
        }
    }
}
