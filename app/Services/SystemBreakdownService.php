<?php

namespace App\Services;

use App\Models\SystemBreakdownLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Throwable;

class SystemBreakdownService
{
    /**
     * Ensure the system_breakdown_logs table exists on PostgreSQL/MySQL.
     * Auto-creates the table on the fly if migrations have not executed yet.
     */
    public static function ensureTableExists(): bool
    {
        try {
            if (!Schema::hasTable('system_breakdown_logs')) {
                Schema::create('system_breakdown_logs', function (Blueprint $table) {
                    $table->id();
                    $table->string('error_hash', 64)->index();
                    $table->string('exception_class', 255)->nullable()->index();
                    $table->text('message');
                    $table->text('file')->nullable();
                    $table->integer('line')->nullable();
                    $table->text('url')->nullable();
                    $table->string('http_method', 10)->nullable();
                    $table->string('status_code', 10)->nullable()->default('500');
                    $table->unsignedBigInteger('user_id')->nullable()->index();
                    $table->string('user_name', 191)->nullable();
                    $table->string('user_email', 191)->nullable()->index();
                    $table->string('user_role', 100)->nullable();
                    $table->string('ip_address', 45)->nullable();
                    $table->text('user_agent')->nullable();
                    $table->json('request_payload')->nullable();
                    $table->json('request_headers')->nullable();
                    $table->mediumText('stack_trace')->nullable();
                    $table->integer('occurrence_count')->default(1);
                    $table->string('status', 20)->default('open')->index();
                    $table->text('resolution_notes')->nullable();
                    $table->timestamp('resolved_at')->nullable();
                    $table->timestamp('first_seen_at')->nullable()->useCurrent();
                    $table->timestamp('last_seen_at')->nullable()->useCurrent();
                    $table->timestamps();
                });
            }
            return true;
        } catch (Throwable $schemaEx) {
            return false;
        }
    }
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
        self::ensureTableExists();

        try {
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
        } catch (Throwable $e) {
            return [
                'total_open' => 0,
                'total_investigating' => 0,
                'total_resolved' => 0,
                'total_errors' => 0,
                'today_occurrences' => 0,
                'unique_users' => 0,
            ];
        }
    }

    /**
     * Get filtered, paginated error records.
     */
    public function getFilteredErrors(Request $request)
    {
        self::ensureTableExists();

        try {
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
        } catch (Throwable $e) {
            return new \Illuminate\Pagination\LengthAwarePaginator([], 0, 20);
        }
    }

    /**
    /**
     * Discover and categorize all log files in storage/logs recursively.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getAllLogFiles(): array
    {
        $logDir = storage_path('logs');
        if (!File::isDirectory($logDir)) {
            return [];
        }

        $allFiles = [];
        try {
            $allFiles = File::allFiles($logDir);
        } catch (Throwable $fileEx) {
            try {
                $allFiles = File::files($logDir);
            } catch (Throwable $fallbackEx) {
                $allFiles = [];
            }
        }
        $result = [];

        foreach ($allFiles as $file) {
            $ext = strtolower($file->getExtension());
            if (!in_array($ext, ['log', 'htm', 'html', 'txt'])) {
                continue;
            }

            $relativePath = str_replace(['\\', '/'], '/', substr($file->getPathname(), strlen($logDir) + 1));
            $filename = $file->getFilename();

            // Categorize by file purpose
            $category = 'other';
            $categoryLabel = 'Other System Logs';
            $icon = '📋';

            if (str_starts_with($filename, 'email-upload-errors') || str_contains($relativePath, 'upload-error')) {
                $category = 'upload_errors';
                $categoryLabel = 'Upload Error Logs';
                $icon = '📤';
            } elseif (str_starts_with($filename, 'inbox-sync') || str_contains($relativePath, 'inbox-sync')) {
                $category = 'inbox_sync';
                $categoryLabel = 'Inbox Sync Logs';
                $icon = '🔄';
            } elseif (str_contains($relativePath, 'outlook-addin')) {
                $category = 'outlook_addin';
                $categoryLabel = 'Outlook Add-in Logs';
                $icon = '📧';
            } elseif (str_starts_with($filename, 'db_') || str_contains($filename, 'database')) {
                $category = 'database';
                $categoryLabel = 'Database Operation Logs';
                $icon = '🗄️';
            } elseif (str_starts_with($filename, 'laravel-') || $filename === 'laravel.log') {
                $category = 'laravel';
                $categoryLabel = 'Laravel Core Logs';
                $icon = '⚙️';
            }

            $sizeBytes = $file->getSize();
            $sizeFormatted = $sizeBytes >= 1048576
                ? round($sizeBytes / 1048576, 2) . ' MB'
                : round($sizeBytes / 1024, 1) . ' KB';

            // Quick error estimate by scanning line markers
            $errorCount = 0;
            try {
                if ($sizeBytes > 0 && $sizeBytes < 5 * 1024 * 1024) {
                    $contentSample = File::get($file->getPathname());
                    $errorCount = substr_count($contentSample, '.ERROR:')
                        + substr_count($contentSample, '.CRITICAL:')
                        + substr_count($contentSample, '.ALERT:')
                        + substr_count($contentSample, '.EMERGENCY:')
                        + substr_count($contentSample, '] ERROR:')
                        + substr_count($contentSample, 'IMAP sync failed')
                        + substr_count($contentSample, 'Email upload failed');
                }
            } catch (Throwable $scanEx) {
                $errorCount = 0;
            }

            $lastModified = Carbon::createFromTimestamp($file->getMTime(), 'Australia/Melbourne');

            $result[$relativePath] = [
                'filename' => $filename,
                'relative_path' => $relativePath,
                'category' => $category,
                'category_label' => $categoryLabel,
                'icon' => $icon,
                'size_bytes' => $sizeBytes,
                'size_formatted' => $sizeFormatted,
                'error_count' => $errorCount,
                'last_modified' => $lastModified->format('d M Y, H:i:s'),
                'last_modified_timestamp' => $file->getMTime(),
            ];
        }

        // Sort by last modified descending
        uasort($result, fn($a, $b) => $b['last_modified_timestamp'] <=> $a['last_modified_timestamp']);

        return $result;
    }

    /**
     * Read and parse entries from any selected log file.
     */
    public function getLogEntriesForFile(string $relativePath, int $limit = 100, ?string $levelFilter = null, ?string $search = null): array
    {
        $logDir = storage_path('logs');
        $cleanPath = str_replace(['..', "\0"], '', $relativePath);
        $fullPath = $logDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $cleanPath);

        if (!File::exists($fullPath) || !File::isFile($fullPath)) {
            return [];
        }

        $content = File::get($fullPath);
        if (strlen($content) > 4 * 1024 * 1024) {
            // Keep last 4MB for high performance
            $content = substr($content, -4 * 1024 * 1024);
        }

        $entries = [];

        // Dual pattern: handles both [DATE] env.LEVEL: Message AND [DATE] LEVEL: Message
        $pattern = '/^\[(\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:[\+-]\d{2}:\d{2})?)\]\s+(?:([a-zA-Z0-9_\-]+)\.)?([A-Z]+):\s+(.*?)(?=\n\[\d{4}-\d{2}-\d{2}|\Z)/sm';

        if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
            $matches = array_reverse($matches); // Latest entries first

            foreach ($matches as $match) {
                $timestamp = $match[1];
                $env = $match[2] ?: 'system';
                $level = strtoupper($match[3]);
                $body = trim($match[4]);

                // Level filtering
                if ($levelFilter && $levelFilter !== 'all') {
                    if ($levelFilter === 'errors' && !in_array($level, ['ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY'])) {
                        continue;
                    }
                    if ($levelFilter !== 'errors' && $level !== strtoupper($levelFilter)) {
                        continue;
                    }
                }

                // Search filtering
                if ($search && !str_contains(strtolower($body), strtolower($search)) && !str_contains($timestamp, $search)) {
                    continue;
                }

                // Separate title, JSON metadata, and stack trace
                $lines = explode("\n", $body);
                $firstLine = $lines[0] ?? '';

                $jsonContext = null;
                $stack = '';

                // Try to find JSON payload in the message
                if (preg_match('/(\{.*\})/s', $body, $jsonMatch)) {
                    $decoded = json_decode($jsonMatch[1], true);
                    if ($decoded && is_array($decoded)) {
                        $jsonContext = $decoded;
                        // Remove trace from JSON if present to keep view clean
                        if (isset($jsonContext['trace'])) {
                            $stack = $jsonContext['trace'];
                            unset($jsonContext['trace']);
                        }
                    }
                }

                if (!$stack && count($lines) > 1) {
                    $stack = implode("\n", array_slice($lines, 1, 30));
                }

                $entries[] = [
                    'timestamp' => $timestamp,
                    'env' => $env,
                    'level' => $level,
                    'title' => $firstLine,
                    'json_context' => $jsonContext,
                    'stack' => $stack,
                    'full_body' => $body,
                ];

                if (count($entries) >= $limit) {
                    break;
                }
            }
        } else {
            // Fallback for non-standard line-based logs (e.g. db_restore.log)
            $lines = array_reverse(array_filter(explode("\n", $content)));
            $count = 0;
            foreach ($lines as $line) {
                $trimmed = trim($line);
                if (empty($trimmed)) continue;

                if ($search && !str_contains(strtolower($trimmed), strtolower($search))) {
                    continue;
                }

                $level = 'INFO';
                if (stripos($trimmed, 'error') !== false || stripos($trimmed, 'fatal') !== false || stripos($trimmed, 'failed') !== false) {
                    $level = 'ERROR';
                } elseif (stripos($trimmed, 'warn') !== false) {
                    $level = 'WARNING';
                }

                if ($levelFilter && $levelFilter === 'errors' && $level !== 'ERROR') {
                    continue;
                }

                $entries[] = [
                    'timestamp' => 'N/A',
                    'env' => 'log',
                    'level' => $level,
                    'title' => $trimmed,
                    'json_context' => null,
                    'stack' => '',
                    'full_body' => $trimmed,
                ];

                $count++;
                if ($count >= $limit) break;
            }
        }

        return $entries;
    }

    /**
     * Read raw log content safely.
     */
    public function getRawLogContent(string $relativePath, int $maxBytes = 500000): string
    {
        $logDir = storage_path('logs');
        $cleanPath = str_replace(['..', "\0"], '', $relativePath);
        $fullPath = $logDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $cleanPath);

        if (!File::exists($fullPath) || !File::isFile($fullPath)) {
            return 'File not found.';
        }

        $size = File::size($fullPath);
        if ($size > $maxBytes) {
            $handle = fopen($fullPath, 'r');
            fseek($handle, -$maxBytes, SEEK_END);
            $content = fread($handle, $maxBytes);
            fclose($handle);
            return "[Showing last " . round($maxBytes / 1024) . " KB of " . round($size / 1024) . " KB]\n...\n" . $content;
        }

        return File::get($fullPath);
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
