<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

/**
 * One-shot Python CLI fallback when the HTTP microservice is down.
 *
 * Production often has PYTHON_SERVICE_URL=http://127.0.0.1:5002 but no daemon
 * listening. This invokes python_services/cli_email.py directly so .msg/.eml
 * uploads still work when Python + deps are installed.
 */
class PythonEmailCliFallback
{
    /**
     * @return array<string, mixed>|null  Parsed payload, or null if CLI cannot run
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

            return [
                'success' => false,
                'error_code' => 'cli_python_missing',
                'error' => 'Email CLI needs Python 3.9+ (prefer python3.13). '
                    . 'On the server run: cd python_services && python3.13 -m venv venv && ./venv/bin/pip install -r requirements.txt',
                'technical_error' => 'No suitable Python binary found for CLI fallback',
            ];
        }

        $timeout = max(30, $timeout ?? (int) config('services.python.timeout', 180));
        $timezone = (string) config('app.timezone', 'Australia/Melbourne');
        $command = $mode === 'parse' ? 'parse' : 'parse-render-pdf';

        $args = [$python, $script, $command, $file->getPathname(), '--timezone', $timezone];
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

        try {
            /** @var array<string, mixed> $decoded */
            $decoded = json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            Log::error('Python email CLI returned invalid JSON', [
                'stdout_preview' => substr($stdout, 0, 500),
                'stderr' => $stderr,
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

        Log::info('Python email CLI fallback used', [
            'mode' => $command,
            'exit' => $process->getExitCode(),
            'file' => $file->getClientOriginalName(),
        ]);

        return $decoded;
    }

    public function isAvailable(): bool
    {
        return $this->resolvePythonBinary() !== null
            && $this->resolveCliScript() !== null;
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

        // Prefer versioned interpreters first — cPanel often ships an ancient `python3`
        // that cannot run extract-msg / this CLI (seen as SyntaxError on annotations).
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
        ]));

        foreach ($candidates as $bin) {
            $resolved = $this->resolveBinaryPath($bin);
            if ($resolved === null) {
                continue;
            }
            if ($this->pythonMeetsMinimum($resolved, 3, 9)) {
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

    private function pythonMeetsMinimum(string $binary, int $major, int $minor): bool
    {
        $probe = new Process([$binary, '-c', 'import sys; print("%d.%d" % sys.version_info[:2])']);
        $probe->setTimeout(5);
        $probe->run();
        if (! $probe->isSuccessful()) {
            return false;
        }

        $version = trim($probe->getOutput());
        if (! preg_match('/^(\d+)\.(\d+)/', $version, $m)) {
            return false;
        }

        $maj = (int) $m[1];
        $min = (int) $m[2];

        return $maj > $major || ($maj === $major && $min >= $minor);
    }
}
