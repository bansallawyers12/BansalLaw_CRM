<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\SystemBreakdownLog;
use App\Services\SystemBreakdownService;
use App\Support\UiManualTestCatalog;
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
        try {
            SystemBreakdownService::ensureTableExists();

            $userThreshold = (int) $request->input('user_threshold', 30);
            if ($userThreshold < 1 || $userThreshold > 1440) {
                $userThreshold = 30;
            }

            $stats = $this->service->getStats($userThreshold);
            $errors = $this->service->getFilteredErrors($request);
            $activeTab = $request->input('tab', 'db'); // 'db', 'logs', 'users', or 'ui_tests' (local only)
            if ($activeTab === 'ui_tests' && ! app()->environment('local')) {
                abort(404);
            }

            $showUiTestsTab = app()->environment('local');
            $uiManualTestSections = ($activeTab === 'ui_tests' && $showUiTestsTab)
                ? UiManualTestCatalog::sections()
                : [];
            $uiManualTestStorageKey = UiManualTestCatalog::storageKey();
            $uiManualTestMeta = config('ui_manual_tests.meta', []);

            $activeUsersData = $this->service->getActiveUsersData($userThreshold);

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
                'logSearch',
                'activeUsersData',
                'userThreshold',
                'showUiTestsTab',
                'uiManualTestSections',
                'uiManualTestStorageKey',
                'uiManualTestMeta'
            ));
        } catch (\Throwable $e) {
            // Diagnostic fallback: show the exact error message and trace rather than 500 generic page
            return response()->make(
                '<div style="background:#0b0f19; color:#f8fafc; font-family:sans-serif; padding:32px; min-height:100vh; line-height:1.6;">' .
                '<h2 style="color:#ef4444; font-size:22px; margin-bottom:12px;">⚠️ System Breakdown Monitor Diagnostic Error</h2>' .
                '<p style="color:#94a3b8; margin-bottom:16px;">The dashboard encountered an error while booting. Full diagnostic details are shown below:</p>' .
                '<div style="background:#1e293b; border-left:4px solid #ef4444; padding:16px; border-radius:6px; margin-bottom:20px;">' .
                '<p style="font-size:16px; color:#fca5a5; font-weight:bold;">' . htmlspecialchars($e->getMessage()) . '</p>' .
                '<p style="font-family:monospace; color:#94a3b8; font-size:12px; margin-top:6px;">' . htmlspecialchars($e->getFile()) . ':' . $e->getLine() . '</p>' .
                '</div>' .
                '<h3 style="color:#93c5fd; font-size:15px; margin-bottom:8px;">Stack Trace:</h3>' .
                '<pre style="background:#050811; padding:16px; border-radius:8px; overflow:auto; font-size:11.5px; line-height:1.6; color:#cbd5e1; border:1px solid #1e293b; max-height:400px;">' .
                htmlspecialchars($e->getTraceAsString()) .
                '</pre>' .
                '<div style="margin-top:20px; display:flex; gap:12px;">' .
                '<a href="' . url('/system-errors?tab=logs') . '" style="background:#3b82f6; color:#fff; padding:10px 18px; border-radius:6px; text-decoration:none; font-weight:bold;">📂 View Log Files Tab</a>' .
                '<a href="' . url('/system-errors') . '" style="background:#334155; color:#fff; padding:10px 18px; border-radius:6px; text-decoration:none;">🔄 Retry Dashboard</a>' .
                '</div>' .
                '</div>',
                200
            );
        }
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
