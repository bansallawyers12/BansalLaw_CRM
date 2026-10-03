<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\SystemBreakdownLog;
use App\Services\SystemBreakdownService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class SystemBreakdownController extends Controller
{
    protected SystemBreakdownService $service;

    public function __construct(SystemBreakdownService $service)
    {
        $this->middleware('auth:admin');
        $this->service = $service;
    }

    /**
     * Display the System Error & Breakdown Monitor dashboard.
     */
    public function index(Request $request)
    {
        $stats = $this->service->getStats();
        $errors = $this->service->getFilteredErrors($request);
        $activeTab = $request->input('tab', 'db'); // 'db' or 'logs'

        // Log files explorer
        $allLogFiles = $this->service->getAllLogFiles();
        $selectedLogFile = $request->input('log_file');

        if (!$selectedLogFile || !isset($allLogFiles[$selectedLogFile])) {
            $selectedLogFile = array_key_first($allLogFiles) ?: '';
        }

        $logLevel = $request->input('log_level', 'all');
        $logSearch = $request->input('log_search');
        $viewRaw = (bool) $request->input('view_raw', false);

        $selectedFileInfo = $selectedLogFile && isset($allLogFiles[$selectedLogFile]) ? $allLogFiles[$selectedLogFile] : null;

        $logEntries = [];
        $rawLogContent = '';

        if ($selectedLogFile) {
            if ($viewRaw) {
                $rawLogContent = $this->service->getRawLogContent($selectedLogFile);
            } else {
                $logEntries = $this->service->getLogEntriesForFile($selectedLogFile, 150, $logLevel, $logSearch);
            }
        }

        return view('system_breakdown.index', compact(
            'stats',
            'errors',
            'activeTab',
            'allLogFiles',
            'selectedLogFile',
            'selectedFileInfo',
            'logEntries',
            'rawLogContent',
            'viewRaw',
            'logLevel',
            'logSearch'
        ));
    }

    /**
     * Download any log file safely.
     */
    public function downloadLog(Request $request): BinaryFileResponse
    {
        $relativePath = (string) $request->input('file');
        $cleanPath = str_replace(['..', "\0"], '', $relativePath);
        $fullPath = storage_path('logs/' . str_replace('/', DIRECTORY_SEPARATOR, $cleanPath));

        if (!File::exists($fullPath) || !File::isFile($fullPath)) {
            abort(404, 'Requested log file not found.');
        }

        return response()->download($fullPath, basename($fullPath), [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }

    /**
     * Clear or truncate a selected log file.
     */
    public function clearLogFile(Request $request)
    {
        $relativePath = (string) $request->input('file');
        $cleanPath = str_replace(['..', "\0"], '', $relativePath);
        $fullPath = storage_path('logs/' . str_replace('/', DIRECTORY_SEPARATOR, $cleanPath));

        if (File::exists($fullPath) && File::isFile($fullPath)) {
            File::put($fullPath, '');
        }

        return redirect()->route('system_errors.index', [
            'tab' => 'logs',
            'log_file' => $relativePath,
        ])->with('success', 'Log file "' . basename($fullPath) . '" was truncated successfully.');
    }

    /**
     * Get detailed breakdown for a single error (with source code snippet).
     */
    public function show($id)
    {
        $error = SystemBreakdownLog::findOrFail($id);

        $codeSnippet = [];
        if (!empty($error->file) && File::exists($error->file) && $error->line) {
            try {
                $lines = file($error->file);
                $targetLine = (int) $error->line;
                $startLine = max(1, $targetLine - 8);
                $endLine = min(count($lines), $targetLine + 8);

                for ($i = $startLine; $i <= $endLine; $i++) {
                    $codeSnippet[] = [
                        'line_number' => $i,
                        'is_error_line' => ($i === $targetLine),
                        'content' => rtrim($lines[$i - 1] ?? ''),
                    ];
                }
            } catch (Throwable $fileEx) {
                $codeSnippet = [];
            }
        }

        return response()->json([
            'success' => true,
            'data' => $error,
            'code_snippet' => $codeSnippet,
            'short_file' => $error->short_file,
            'relative_time' => $error->relative_last_seen,
        ]);
    }

    /**
     * Update status (open, investigating, resolved, ignored).
     */
    public function updateStatus(Request $request, $id)
    {
        $status = $request->input('status', 'resolved');
        $notes = (string) $request->input('notes', '');

        $updated = $this->service->updateStatus((int) $id, $status, $notes);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => $updated,
                'message' => $updated ? 'Status successfully updated to ' . ucfirst($status) : 'Failed to update error status.',
            ]);
        }

        return redirect()->back()->with('success', 'Error status updated to ' . ucfirst($status));
    }

    /**
     * Clear error records (resolved or all).
     */
    public function clear(Request $request)
    {
        $scope = $request->input('scope', 'resolved');
        $count = $this->service->clearErrors($scope);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Cleared {$scope} errors.",
            ]);
        }

        return redirect()->route('system_errors.index')->with('success', "Cleared {$scope} errors.");
    }

    /**
     * API endpoint for live polling stats.
     */
    public function apiStats()
    {
        return response()->json([
            'success' => true,
            'stats' => $this->service->getStats(),
            'timestamp' => date('H:i:s'),
        ]);
    }

    /**
     * Simulate a test error to verify capture mechanics.
     */
    public function simulateError(Request $request)
    {
        try {
            $userTrigger = $request->input('trigger', 'manual');
            throw new \RuntimeException("SIMULATED_TEST_BREAKDOWN: Test verification error triggered by {$userTrigger} at " . date('Y-m-d H:i:s'));
        } catch (Throwable $e) {
            SystemBreakdownService::recordException($e, $request);
        }

        return redirect()->route('system_errors.index', ['status' => 'open'])
            ->with('success', 'Simulated test error was triggered and captured successfully! Check the top of the error list.');
    }
}
