<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

/**
 * One-shot Python CLI fallback when the HTTP microservice is down.
 *
 * Production may experience port 5002 connection refused if the daemon is
 * stopped. This invokes python_services/cli_email.py directly so .msg/.eml
 * email uploads and previews continue working seamlessly.
 */
class PythonEmailCliFallback
{
    /**
     * @return array<string, mixed>|null Parsed payload, or null if CLI cannot run
     */
    public function parse(
        UploadedFile $file,
        string $mode = 'parse-render-pdf',
        bool $metadataOnly = false,
        ?int $timeout = null
    ): ?array {
        if (! filter_var(config('services.python.cli_fallback', true), FILTER_VALIDATE_BOOLEAN)) {
            return null;
        }

        $python = $this->resolvePythonBinary();
        $script = $this->resolveCliScript();
        if ($python === null || $script === null) {
            Log::warning('Python email CLI fallback unavailable', [
                'python' => $python,
                'script' => $script,
            ]);

            return null;
        }

        $timeout = max(30, $timeout ?? (int) config('services.python.timeout', 180));
        $timezone = (string) config('app.timezone', 'Australia/Melbourne');
        $command = $mode === 'parse' ? 'parse' : 'parse-render-pdf';
        $originalName = $file->getClientOriginalName() ?: basename($file->getPathname());

        $args = [
            $python,
            $script,
            $command,
            $file->getPathname(),
            '--original-name',
            $originalName,
            '--timezone',
            $timezone,
        ];
        if ($metadataOnly && $command === 'parse') {
            $args[] = '--metadata-only';
        }

        $process = new Process($args, base_path('python_services'), null, null, $timeout);
        $process->run();

        $stdout = trim($process->getOutput());
        $stderr = trim($process->getErrorOutput());

        if ($stdout === '') {
            Log::error('Python email CLI produced no stdout', [
                'exit' => $process->getExitCode(),
                'stderr' => $stderr,
                'cmd' => $process->getCommandLine(),
            ]);

            return [
                'success' => false,
                'error_code' => 'cli_failed',
                'error' => 'Email CLI parser returned no output.'
                    . ($stderr !== '' ? ' ' . $stderr : ''),
                'technical_error' => $stderr !== '' ? $stderr : 'empty stdout',
            ];
        }

        // Extract JSON from stdout even if preceded by other lines
        $jsonString = $this->extractJsonPayload($stdout);
        if ($jsonString === null) {
            Log::error('Python email CLI returned non-JSON stdout', [
                'stdout_preview' => substr($stdout, 0, 500),
                'stderr' => $stderr,
            ]);

            return [
                'success' => false,
                'error_code' => 'cli_invalid_response',
                'error' => 'Email CLI parser returned invalid response format.',
                'technical_error' => substr($stdout, 0, 500),
            ];
        }

        try {
            /** @var array<string, mixed> $decoded */
            $decoded = json_decode($jsonString, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            Log::error('Python email CLI returned invalid JSON', [
                'json_snippet' => substr($jsonString, 0, 500),
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error_code' => 'cli_invalid_response',
                'error' => 'Email CLI parser returned invalid JSON.',
                'technical_error' => $e->getMessage(),
            ];
        }

        if (! $process->isSuccessful() && empty($decoded['error'])) {
            $decoded['success'] = false;
            $decoded['error'] = $decoded['error']
                ?? ('Email CLI exited with code ' . $process->getExitCode());
        }

        Log::info('Python email CLI fallback succeeded', [
            'mode' => $command,
            'file' => $originalName,
        ]);

        return $decoded;
    }

    public function isAvailable(): bool
    {
        return $this->resolvePythonBinary() !== null
            && $this->resolveCliScript() !== null;
    }

    protected function extractJsonPayload(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        if (str_starts_with($raw, '{')) {
            try {
                json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

                return $raw;
            } catch (\Throwable) {
                // Fall through — may be warning text then JSON without a clean split.
            }
        }

        // Prefer the last complete JSON object line (CLI emits one JSON object per run).
        $lines = preg_split("/\r\n|\n|\r/", $raw) ?: [];
        for ($i = count($lines) - 1; $i >= 0; $i--) {
            $line = trim($lines[$i]);
            if ($line === '' || ! str_starts_with($line, '{')) {
                continue;
            }
            try {
                json_decode($line, true, 512, JSON_THROW_ON_ERROR);

                return $line;
            } catch (\Throwable) {
                continue;
            }
        }

        // Strip leading non-JSON noise (PyMuPDF "warning: ..." prints) and decode from first '{'.
        $start = strpos($raw, '{');
        if ($start === false) {
            return null;
        }

        $candidate = substr($raw, $start);
        try {
            json_decode($candidate, true, 512, JSON_THROW_ON_ERROR);

            return $candidate;
        } catch (\Throwable) {
            // Try brace-balanced extraction for multiline JSON after warnings.
            $extracted = $this->extractBalancedJsonObject($candidate);
            if ($extracted !== null) {
                try {
                    json_decode($extracted, true, 512, JSON_THROW_ON_ERROR);

                    return $extracted;
                } catch (\Throwable) {
                    return null;
                }
            }
        }

        return null;
    }

    protected function extractBalancedJsonObject(string $raw): ?string
    {
        $start = strpos($raw, '{');
        if ($start === false) {
            return null;
        }

        $depth = 0;
        $inString = false;
        $escape = false;
        $length = strlen($raw);

        for ($i = $start; $i < $length; $i++) {
            $ch = $raw[$i];

            if ($inString) {
                if ($escape) {
                    $escape = false;
                    continue;
                }
                if ($ch === '\\') {
                    $escape = true;
                    continue;
                }
                if ($ch === '"') {
                    $inString = false;
                }
                continue;
            }

            if ($ch === '"') {
                $inString = true;
                continue;
            }
            if ($ch === '{') {
                $depth++;
                continue;
            }
            if ($ch === '}') {
                $depth--;
                if ($depth === 0) {
                    return substr($raw, $start, $i - $start + 1);
                }
            }
        }

        return null;
    }

    private function resolveCliScript(): ?string
    {
        $configured = trim((string) config('services.python.cli_script', ''));
        $candidates = array_filter([
            $configured !== '' ? $configured : null,
            base_path('python_services/cli_email.py'),
        ]);

        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    private function resolvePythonBinary(): ?string
    {
        $configured = trim((string) config('services.python.cli_python', ''));
        $candidates = array_values(array_filter([
            $configured !== '' ? $configured : null,
            base_path('python_services/venv/bin/python'),
            base_path('python_services/venv/bin/python3'),
            base_path('python_services/venv/Scripts/python.exe'),
            'python3.13',
            'python3.12',
            'python3.11',
            'python3.10',
            'python3.9',
            'python3',
            'python',
            '/usr/local/bin/python3.13',
            '/usr/local/bin/python3',
            '/opt/cpanel/ea-python311/root/usr/bin/python3',
            '/opt/cpanel/ea-python310/root/usr/bin/python3',
            '/opt/cpanel/ea-python39/root/usr/bin/python3',
            '/opt/alt/python311/bin/python3',
            '/opt/alt/python310/bin/python3',
            '/opt/alt/python39/bin/python3',
        ]));

        foreach ($candidates as $bin) {
            $resolved = $this->resolveBinaryPath($bin);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        return null;
    }

    private function resolveBinaryPath(string $bin): ?string
    {
        if (str_contains($bin, DIRECTORY_SEPARATOR) || str_contains($bin, '/') || str_contains($bin, '\\')) {
            return (is_file($bin) || is_executable($bin)) ? $bin : null;
        }

        $which = new Process(
            PHP_OS_FAMILY === 'Windows' ? ['where', $bin] : ['command', '-v', $bin]
        );
        $which->setTimeout(5);
        $which->run();
        if (! $which->isSuccessful()) {
            return null;
        }

        $path = trim(strtok($which->getOutput(), "\n") ?: '');

        return $path !== '' ? $path : null;
    }
}
