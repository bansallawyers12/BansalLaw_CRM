<?php

namespace App\Console\Commands;

use App\Models\EmailAccount;
use App\Services\EmailFolderService;
use Illuminate\Console\Command;

class TestEmailFolders extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'emails:test-folders {account_id? : ID of the email account to test}';

    /**
     * The console command description.
     */
    protected $description = 'Test email folder creation and management functionality';

    public function handle(): int
    {
        $accountId = $this->argument('account_id');
        
        if ($accountId) {
            $account = EmailAccount::find($accountId);
            if (!$account) {
                $this->error("Email account with ID {$accountId} not found.");
                return self::FAILURE;
            }
            $accounts = [$account];
        } else {
            $accounts = EmailAccount::all();
            if ($accounts->isEmpty()) {
                $this->info('No email accounts found. Please create an email account first.');
                return self::SUCCESS;
            }
        }

        $folderService = new EmailFolderService();

        foreach ($accounts as $account) {
            $this->info("Testing folder functionality for account: {$account->email}");
            $this->line("Provider: {$account->provider}");
            $this->line("Account ID: {$account->id}");
            
            // Test folder creation
            $this->line("\n1. Testing folder creation...");
            $created = $folderService->createAccountFolders($account);
            if ($created) {
                $this->info("✓ Folders created successfully");
            } else {
                $this->error("✗ Failed to create folders");
                continue;
            }

            // Test getting account path
            $this->line("\n2. Testing account path...");
            $accountPath = $folderService->getAccountPath($account);
            $this->line("Account path: {$accountPath}");

            // Test getting folders
            $this->line("\n3. Testing folder listing...");
            $folders = $folderService->getAccountFolders($account);
            $this->line("Found folders: " . implode(', ', $folders));

            // Test storage stats
            $this->line("\n4. Testing storage statistics...");
            $stats = $folderService->getAccountStorageStats($account);
            $this->line("Total emails: {$stats['total_emails']}");
            $this->line("Total size: " . $this->formatBytes($stats['total_size']));
            
            if (!empty($stats['folders'])) {
                $this->line("Folder breakdown:");
                foreach ($stats['folders'] as $folderName => $folderStats) {
                    $this->line("  {$folderName}: {$folderStats['email_count']} emails, " . $this->formatBytes($folderStats['size']));
                }
            }

            // Test email file path generation
            $this->line("\n5. Testing email file path generation...");
            $testMessageId = 'test-message-123@example.com';
            $testFolder = 'Inbox';
            $emailPath = $folderService->getEmailFilePath($account, $testFolder, $testMessageId);
            $this->line("Sample email path: {$emailPath}");

            // Test saving a sample email
            $this->line("\n6. Testing email saving...");
            $sampleEmailContent = $this->generateSampleEmail($testMessageId);
            $saved = $folderService->saveEmailToFile($account, $testFolder, $testMessageId, $sampleEmailContent);
            if ($saved) {
                $this->info("✓ Sample email saved successfully");
                
                // Test reading the email back
                $this->line("\n7. Testing email reading...");
                $readContent = $folderService->getEmailFromFile($account, $testFolder, $testMessageId);
                if ($readContent) {
                    $this->info("✓ Email read successfully");
                    $this->line("Email size: " . strlen($readContent) . " bytes");
                } else {
                    $this->error("✗ Failed to read email");
                }
            } else {
                $this->error("✗ Failed to save sample email");
            }

            $this->line("\n" . str_repeat('-', 50));
        }

        $this->info("\nFolder testing completed!");
        return self::SUCCESS;
    }

    /**
     * Format bytes into human readable format
     */
    private function formatBytes(int $bytes): string
    {
        if ($bytes === 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = floor(log($bytes, 1024));
        $power = min($power, count($units) - 1);
        
        return round($bytes / pow(1024, $power), 2) . ' ' . $units[$power];
    }

    /**
     * Generate a sample email for testing
     */
    private function generateSampleEmail(string $messageId): string
    {
        return "Message-ID: {$messageId}\r\n" .
               "From: test@example.com\r\n" .
               "To: recipient@example.com\r\n" .
               "Subject: Test Email\r\n" .
               "Date: " . date('r') . "\r\n" .
               "Content-Type: text/plain; charset=UTF-8\r\n\r\n" .
               "This is a test email for folder functionality testing.\r\n" .
               "It contains sample content to verify that emails can be saved and retrieved from local storage.";
    }
}
