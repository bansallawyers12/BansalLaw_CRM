<?php

namespace App\Console\Commands;

use App\Models\EmailAccount;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Throwable;

class SyncEmails extends Command
{
    /**
     * The name and signature of the console command.
     *
     * Usage: php artisan emails:sync {id}
     */
    protected $signature = 'emails:sync {id : ID of the email_accounts row}';

    /**
     * The console command description.
     */
    protected $description = 'Sync emails for the given EmailAccount using the Python IMAP script';

    public function handle(): int
    {
        $id = (int) $this->argument('id');
        $account = EmailAccount::findOrFail($id);

        // Log sync attempt
        Log::info("Starting email sync for account ID: {$id}, Email: {$account->email}, Provider: {$account->provider}");

        $python = 'py'; // Use py command for Windows Python launcher
        if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
            $python = 'python3'; // Use python3 on Unix-like systems
        }
        $script = base_path('sync_emails.py');

        // Get the correct authentication token/password
        $authToken = $account->access_token;
        if (!$authToken && $account->password) {
            // Decrypt the password if it's encrypted
            try {
                $authToken = decrypt($account->password);
            } catch (\Exception $e) {
                // If decryption fails, use the password as-is (might be plain text)
                $authToken = $account->password;
            }
        }

        $args = [
            $python,
            $script,
            (string) $account->provider,
            (string) $account->email,
            (string) $authToken,
        ];

        // Log the command being executed (without sensitive data)
        Log::info("Executing sync command", [
            'python' => $python,
            'script' => $script,
            'provider' => $account->provider,
            'email' => $account->email,
            'has_token' => !empty($authToken)
        ]);

        $process = new Process($args, base_path());
        $process->setTimeout(config('mail.process_timeout', 600));
        $process->run();

        // Capture both stdout and stderr
        $output = trim($process->getOutput());
        $errorOutput = trim($process->getErrorOutput());

        // Log debug information from stderr
        if (!empty($errorOutput)) {
            Log::info("Python script debug output", ['debug_output' => $errorOutput]);
            $this->line("Debug output: " . $errorOutput);
        }

        if (! $process->isSuccessful()) {
            $errorMessage = 'Failed to run sync: ' . ($errorOutput ?: $output);
            Log::error("Email sync failed", [
                'account_id' => $id,
                'error_output' => $errorOutput,
                'stdout' => $output,
                'exit_code' => $process->getExitCode()
            ]);
            $this->error($errorMessage);
            return self::FAILURE;
        }

        try {
            $emails = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            Log::error("Invalid JSON from Python script", [
                'account_id' => $id,
                'error' => $e->getMessage(),
                'output' => $output,
                'debug_output' => $errorOutput
            ]);
            $this->error('Invalid JSON from Python script: ' . $e->getMessage());
            $this->line("Raw output: " . $output);
            return self::FAILURE;
        }

        if (! is_array($emails)) {
            Log::error("Unexpected result from Python script", [
                'account_id' => $id,
                'result_type' => gettype($emails),
                'result' => $emails
            ]);
            $this->error('Unexpected result from Python script.');
            return self::FAILURE;
        }

        // Check if the result contains an error with debug info
        if (isset($emails['error']) && isset($emails['debug_info'])) {
            Log::error("Email sync error with debug info", [
                'account_id' => $id,
                'error' => $emails['error'],
                'debug_info' => $emails['debug_info']
            ]);
            
            $this->error("Sync error: " . $emails['error']);
            
            // Display debug information
            if (isset($emails['debug_info']['network_test'])) {
                $networkTest = $emails['debug_info']['network_test'];
                $this->line("Network diagnostics:");
                $this->line("  Hostname: " . ($networkTest['hostname'] ?? 'N/A'));
                $this->line("  DNS Resolution: " . (is_array($networkTest['dns_resolution']) ? implode(', ', $networkTest['dns_resolution']) : ($networkTest['dns_resolution'] ?? 'FAILED')));
                $this->line("  Socket Connection: " . ($networkTest['socket_connection'] ?? 'N/A'));
                $this->line("  SSL Connection: " . ($networkTest['ssl_connection'] ?? 'N/A'));
                
                if (!empty($networkTest['error_details'])) {
                    $this->line("  Error Details:");
                    foreach ($networkTest['error_details'] as $detail) {
                        $this->line("    - " . $detail);
                    }
                }
            }
            
            return self::FAILURE;
        }

        $syncedCount = 0;
        foreach ($emails as $mail) {
            $from = $mail['from'] ?? null;
            $subject = $mail['subject'] ?? null;
            $date = $mail['date'] ?? null;
            $to = $mail['to'] ?? null;
            $cc = $mail['cc'] ?? null;
            $headers = $mail['headers'] ?? null;

            // Convert arrays to strings for database storage
            $toEmail = is_array($to) ? implode(', ', $to) : $to;
            $ccEmail = is_array($cc) ? implode(', ', $cc) : $cc;
            $headersJson = is_array($headers) ? json_encode($headers) : $headers;

            DB::table('emails')->updateOrInsert(
                [
                    'account_id' => $account->id,
                    'subject' => $subject,
                ],
                [
                    'from_email' => $from,
                    'to_email' => $toEmail,
                    'cc' => $ccEmail,
                    'headers' => $headersJson,
                    'date' => $date,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
            $syncedCount++;
        }

        Log::info("Email sync completed successfully", [
            'account_id' => $id,
            'synced_count' => $syncedCount
        ]);
        
        $this->info("Synced {$syncedCount} emails for {$account->email}");
        return self::SUCCESS;
    }
}


