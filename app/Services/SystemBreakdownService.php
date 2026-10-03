<?php

namespace App\Services;

use App\Models\SystemBreakdownLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Throwable;

class SystemBreakdownService
{
    /**
     * Capture and record any system exception.
     */
    public static function recordException(Throwable $e, ?Request $request = null): ?SystemBreakdownLog
    {
        try {
            $request = $request ?: (app()->bound('request') ? request() : null);

            $message = trim((string) $e->getMessage());
            if ($message === '') {
                $message = get_class($e) . ' occurred with no message.';
            }

            $file = (string) $e->getFile();
            $line = (int) $e->getLine();
            $exceptionClass = get_class($e);

            // Don't record standard validation or 404 not found exceptions
            if ($e instanceof \Illuminate\Validation\ValidationException ||
                $e instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException ||
                $e instanceof \Illuminate\Auth\AuthenticationException) {
                return null;
            }

            $url = $request ? $request->fullUrl() : (isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : 'CLI / Background Worker');
            $method = $request ? $request->method() : (isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'CLI');
            $ip = $request ? $request->ip() : (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1');
            $userAgent = $request ? substr((string) $request->userAgent(), 0, 500) : 'N/A';

            // User context
            $userId = null;
            $userName = 'Guest / Unauthenticated';
            $userEmail = null;
            $userRole = null;

            try {
                $user = null;
                if (Auth::guard('admin')->check()) {
                    $user = Auth::guard('admin')->user();
                } elseif (Auth::check()) {
                    $user = Auth::user();
                }

                if ($user) {
                    $userId = $user->id;
                    $userName = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: ($user->name ?? 'User #' . $user->id);
                    $userEmail = $user->email ?? null;
                    $userRole = $user->role->role_name ?? ($user->role_id ?? ($user->role ?? 'Staff'));
                }
            } catch (Throwable $authEx) {
                // Ignore auth resolution errors during exception handling
            }

            // Payload sanitization (redact sensitive keys)
            $payload = [];
            if ($request) {
                try {
                    $rawInput = $request->except(['password', 'password_confirmation', 'secret', 'token', '_token', 'credit_card', 'api_key', 'authorization']);
                    $payload = self::sanitizeArray($rawInput);
                } catch (Throwable $payloadEx) {
                    $payload = ['_error' => 'Could not serialize request payload: ' . $payloadEx->getMessage()];
                }
            }

            // Stack trace formatting (keep first 35 frames)
            $traceString = self::formatStackTrace($e);

            // Determine status code
            $statusCode = 500;
            if (method_exists($e, 'getStatusCode')) {
                $statusCode = $e->getStatusCode();
            } elseif ($e->getCode() >= 400 && $e->getCode() < 600) {
                $statusCode = $e->getCode();
            }

            // Grouping hash: based on exception class, file, line, and core message
            $errorHash = md5($exceptionClass . '|' . $file . '|' . $line . '|' . substr($message, 0, 150));

            // Check if active open record exists for grouping
            $existing = SystemBreakdownLog::where('error_hash', $errorHash)
                ->where('status', '!=', 'resolved')
                ->first();

            if ($existing) {
                $existing->occurrence_count += 1;
                $existing->last_seen_at = Carbon::now('Australia/Melbourne');
                $existing->status = 'open'; // Reopen if was ignored
                if ($userEmail) {
                    $existing->user_id = $userId;
                    $existing->user_name = $userName;
                    $existing->user_email = $userEmail;
                    $existing->user_role = $userRole;
                }
                $existing->url = $url;
                $existing->http_method = $method;
                $existing->request_payload = $payload;
                $existing->save();
                return $existing;
            }

            return SystemBreakdownLog::create([
                'error_hash' => $errorHash,
                'exception_class' => $exceptionClass,
                'message' => $message,
                'file' => $file,
                'line' => $line,
                'url' => $url,
                'http_method' => $method,
                'status_code' => (string) $statusCode,
                'user_id' => $userId,
                'user_name' => $userName,
                'user_email' => $userEmail,
                'user_role' => $userRole,
                'ip_address' => $ip,
                'user_agent' => $userAgent,
                'request_payload' => $payload,
                'stack_trace' => $traceString,
                'occurrence_count' => 1,
                'status' => 'open',
                'first_seen_at' => Carbon::now('Australia/Melbourne'),
                'last_seen_at' => Carbon::now('Australia/Melbourne'),
            ]);
        } catch (Throwable $dbEx) {
            // Fallback: If DB write fails, append to emergency log file
            try {
                $fallbackPath = storage_path('logs/system_breakdowns_fallback.log');
                $entry = sprintf(
                    "[%s] SYSTEM BREAKDOWN: %s in %s:%d\nURL: %s\nException: %s\nDB Error: %s\n---\n",
                    date('Y-m-d H:i:s'),
                    $e->getMessage(),
                    $e->getFile(),
                    $e->getLine(),
                    isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : 'CLI',
                    get_class($e),
                    $dbEx->getMessage()
                );
                @file_put_contents($fallbackPath, $entry, FILE_APPEND);
            } catch (Throwable $fileEx) {
                // Absorb error to avoid infinite loops
            }
            return null;
        }
    }

    /**
     * Get aggregate KPI statistics for the error monitor.
     */
    public function getStats(): array
    {
        $now = Carbon::now('Australia/Melbourne');
        $todayStart = $now->copy()->startOfDay();

        $totalOpen = SystemBreakdownLog::where('status', 'open')->count();
        $totalInvestigating = SystemBreakdownLog::where('status', 'investigating')->count();
        $totalResolved = SystemBreakdownLog::where('status', 'resolved')->count();
        $totalErrors = SystemBreakdownLog::count();

        $todayErrors = SystemBreakdownLog::where('last_seen_at', '>=', $todayStart)->sum('occurrence_count');
        $uniqueUsers = SystemBreakdownLog::whereNotNull('user_email')->distinct('user_email')->count('user_email');

        return [
            'total_open' => $totalOpen,
            'total_investigating' => $totalInvestigating,
            'total_resolved' => $totalResolved,
            'total_errors' => $totalErrors,
            'today_occurrences' => $todayErrors ?: 0,
            'unique_users' => $uniqueUsers,
        ];
    }

    /**
     * Get filtered, paginated error records.
     */
    public function getFilteredErrors(Request $request)
    {
        $query = SystemBreakdownLog::query();

        // Status filter
        $status = $request->input('status', 'open');
        if ($status === 'open') {
            $query->whereIn('status', ['open', 'investigating']);
        } elseif ($status === 'resolved') {
            $query->where('status', 'resolved');
        } elseif ($status !== 'all') {
            $query->where('status', $status);
        }

        // Search query
        if ($request->filled('search')) {
            $query->search($request->input('search'));
        }

        // Exception type filter
        if ($request->filled('type')) {
            $query->where('exception_class', 'like', '%' . $request->input('type') . '%');
        }

        // User filter
        if ($request->filled('user')) {
            $query->where('user_email', 'like', '%' . $request->input('user') . '%');
        }

        // Date range
        $timeframe = $request->input('timeframe', 'all');
        if ($timeframe === 'today') {
            $query->where('last_seen_at', '>=', Carbon::now('Australia/Melbourne')->startOfDay());
        } elseif ($timeframe === '7days') {
            $query->where('last_seen_at', '>=', Carbon::now('Australia/Melbourne')->subDays(7));
        }

        return $query->orderBy('last_seen_at', 'desc')->paginate(20)->withQueryString();
    }

    /**
     * Read and parse storage/logs/laravel-*.log files into structured records.
     */
    public function getStorageLogEntries(int $limit = 50): array
    {
        $logDir = storage_path('logs');
        if (!File::isDirectory($logDir)) {
            return [];
        }

        $files = File::glob($logDir . DIRECTORY_SEPARATOR . 'laravel-*.log');
        if (empty($files)) {
            $single = $logDir . DIRECTORY_SEPARATOR . 'laravel.log';
            if (File::exists($single)) {
                $files = [$single];
            }
        }

        rsort($files); // Most recent dates first

        $entries = [];
        $pattern = '/^\[(\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:[\+-]\d{2}:\d{2})?)\]\s+([a-zA-Z0-9_\-]+)\.([A-Z]+):\s+(.*?)(?=\n\[\d{4}-\d{2}-\d{2}|\Z)/sm';

        foreach (array_slice($files, 0, 3) as $file) {
            $filename = basename($file);
            $content = File::get($file);
            if (strlen($content) > 2 * 1024 * 1024) {
                // If file is > 2MB, read last 2MB to keep memory low
                $content = substr($content, -2 * 1024 * 1024);
            }

            if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
                $matches = array_reverse($matches); // Latest first
                foreach ($matches as $match) {
                    $timestamp = $match[1];
                    $env = $match[2];
                    $level = strtoupper($match[3]);
                    $body = trim($match[4]);

                    // Extract first line of error as title
                    $lines = explode("\n", $body);
                    $firstLine = $lines[0] ?? '';
                    $stackSnippet = implode("\n", array_slice($lines, 1, 15));

                    $entries[] = [
                        'file' => $filename,
                        'timestamp' => $timestamp,
                        'env' => $env,
                        'level' => $level,
                        'title' => $firstLine,
                        'stack' => $stackSnippet,
                        'full_body' => substr($body, 0, 3000),
                    ];

                    if (count($entries) >= $limit) {
                        break 2;
                    }
                }
            }
        }

        return $entries;
    }

    /**
     * Mark error as resolved with optional notes.
     */
    public function resolveError(int $id, string $notes = ''): bool
    {
        $log = SystemBreakdownLog::find($id);
        if (!$log) {
            return false;
        }

        $log->status = 'resolved';
        $log->resolution_notes = $notes;
        $log->resolved_at = Carbon::now('Australia/Melbourne');
        return $log->save();
    }

    /**
     * Update error status (open, investigating, resolved, ignored).
     */
    public function updateStatus(int $id, string $status, string $notes = ''): bool
    {
        $log = SystemBreakdownLog::find($id);
        if (!$log) {
            return false;
        }

        $log->status = in_array($status, ['open', 'investigating', 'resolved', 'ignored']) ? $status : 'open';
        if ($notes !== '') {
            $log->resolution_notes = $notes;
        }
        if ($log->status === 'resolved') {
            $log->resolved_at = Carbon::now('Australia/Melbourne');
        } else {
            $log->resolved_at = null;
        }
        return $log->save();
    }

    /**
     * Clear error records.
     */
    public function clearErrors(string $scope = 'resolved'): int
    {
        if ($scope === 'all') {
            return SystemBreakdownLog::truncate() ? 1 : 0;
        }

        return SystemBreakdownLog::where('status', 'resolved')->delete();
    }

    /**
     * Format a clean, human-readable stack trace.
     */
    private static function formatStackTrace(Throwable $e): string
    {
        $trace = $e->getTrace();
        $formatted = [];

        $formatted[] = sprintf("#0 %s:%d", $e->getFile(), $e->getLine());

        $i = 1;
        foreach ($trace as $frame) {
            $file = $frame['file'] ?? '[internal function]';
            $line = $frame['line'] ?? '';
            $class = $frame['class'] ?? '';
            $type = $frame['type'] ?? '';
            $function = $frame['function'] ?? '';

            $call = $class ? ($class . $type . $function . '()') : ($function . '()');
            $lineStr = $line ? (':' . $line) : '';

            $isApp = str_contains($file, 'app' . DIRECTORY_SEPARATOR) || str_contains($file, 'resources' . DIRECTORY_SEPARATOR);
            $marker = $isApp ? ' [APP CODE]' : '';

            $formatted[] = sprintf("#%d %s%s %s%s", $i, $file, $lineStr, $call, $marker);
            $i++;
            if ($i > 35) {
                $formatted[] = "... [additional frames omitted for performance] ...";
                break;
            }
        }

        return implode("\n", $formatted);
    }

    /**
     * Sanitize array values to avoid binary or nested recursion.
     */
    private static function sanitizeArray($data, int $depth = 0)
    {
        if ($depth > 4) {
            return '[Nested Depth Exceeded]';
        }

        if (is_array($data)) {
            $result = [];
            foreach ($data as $key => $val) {
                // Redact sensitive patterns
                $lower = strtolower((string) $key);
                if (str_contains($lower, 'password') || str_contains($lower, 'secret') || str_contains($lower, 'token') || str_contains($lower, 'card')) {
                    $result[$key] = '******** (redacted)';
                } else {
                    $result[$key] = self::sanitizeArray($val, $depth + 1);
                }
            }
            return $result;
        }

        if (is_object($data)) {
            return get_class($data);
        }

        if (is_string($data)) {
            return mb_substr($data, 0, 500, 'UTF-8');
        }

        return $data;
    }
}
