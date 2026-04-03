<?php

namespace App\Services;

use App\Models\EmailAccount;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class S3EmailStorageService
{
    /**
     * Base S3 path for email accounts
     */
    private const BASE_S3_PATH = 'email-accounts';

    /**
     * Default folders to create for each email account
     */
    private const DEFAULT_FOLDERS = [
        'Inbox',
        'Sent',
        'Drafts',
        'Trash',
        'Spam',
        'Archive'
    ];

    /**
     * Create folder structure for an email account on S3
     */
    public function createAccountFolders(EmailAccount $account): bool
    {
        try {
            $accountPath = $this->getAccountPath($account);
            
            // Create main account folder on S3
            if (!$this->folderExists($accountPath)) {
                $this->createFolder($accountPath);
                Log::info("Created main S3 folder for account: {$account->email}", [
                    'account_id' => $account->id,
                    'path' => $accountPath
                ]);
            }

            // Create default subfolders on S3
            foreach (self::DEFAULT_FOLDERS as $folder) {
                $folderPath = $accountPath . '/' . $folder;
                if (!$this->folderExists($folderPath)) {
                    $this->createFolder($folderPath);
                    Log::info("Created S3 folder: {$folder} for account: {$account->email}", [
                        'account_id' => $account->id,
                        'folder' => $folder,
                        'path' => $folderPath
                    ]);
                }
            }

            // Create additional folders based on provider
            $this->createProviderSpecificFolders($account);

            return true;
        } catch (\Exception $e) {
            Log::error("Failed to create S3 folders for account: {$account->email}", [
                'account_id' => $account->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Create provider-specific folders on S3
     */
    private function createProviderSpecificFolders(EmailAccount $account): void
    {
        $accountPath = $this->getAccountPath($account);
        $providerFolders = [];

        switch (strtolower($account->provider)) {
            case 'gmail':
                $providerFolders = ['Important', 'Starred', 'All Mail'];
                break;
            case 'outlook':
                $providerFolders = ['Junk Email', 'Deleted Items', 'Outbox'];
                break;
            case 'zoho':
                $providerFolders = ['Important', 'All Mail'];
                break;
        }

        foreach ($providerFolders as $folder) {
            $folderPath = $accountPath . '/' . $folder;
            if (!$this->folderExists($folderPath)) {
                $this->createFolder($folderPath);
                Log::info("Created provider-specific S3 folder: {$folder} for account: {$account->email}", [
                    'account_id' => $account->id,
                    'provider' => $account->provider,
                    'folder' => $folder,
                    'path' => $folderPath
                ]);
            }
        }
    }

    /**
     * Get the S3 path for an email account
     */
    public function getAccountPath(EmailAccount $account): string
    {
        $safeEmail = $this->sanitizeEmailForPath($account->email);
        return self::BASE_S3_PATH . '/' . $safeEmail;
    }

    /**
     * Get the S3 path for a specific folder within an account
     */
    public function getFolderPath(EmailAccount $account, string $folder): string
    {
        $accountPath = $this->getAccountPath($account);
        $safeFolder = $this->sanitizeFolderName($folder);
        return $accountPath . '/' . $safeFolder;
    }

    /**
     * Get the S3 path for an email file
     */
    public function getEmailFilePath(EmailAccount $account, string $folder, string $messageId): string
    {
        $folderPath = $this->getFolderPath($account, $folder);
        $safeMessageId = $this->sanitizeMessageId($messageId);
        return $folderPath . '/' . $safeMessageId . '.eml';
    }

    /**
     * Save email content to S3
     */
    public function saveEmailToS3(EmailAccount $account, string $folder, string $messageId, string $emailContent): bool
    {
        try {
            $filePath = $this->getEmailFilePath($account, $folder, $messageId);
            
            // Ensure the folder exists on S3
            $folderPath = $this->getFolderPath($account, $folder);
            if (!$this->folderExists($folderPath)) {
                $this->createFolder($folderPath);
            }

            // Save the email content to S3
            Storage::disk('s3')->put($filePath, $emailContent);
            
            Log::info("Saved email to S3", [
                'account_id' => $account->id,
                'account_email' => $account->email,
                'folder' => $folder,
                'message_id' => $messageId,
                's3_path' => $filePath
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error("Failed to save email to S3", [
                'account_id' => $account->id,
                'account_email' => $account->email,
                'folder' => $folder,
                'message_id' => $messageId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get email content from S3
     */
    public function getEmailFromS3(EmailAccount $account, string $folder, string $messageId): ?string
    {
        try {
            $filePath = $this->getEmailFilePath($account, $folder, $messageId);
            
            if (Storage::disk('s3')->exists($filePath)) {
                return Storage::disk('s3')->get($filePath);
            }
            
            return null;
        } catch (\Exception $e) {
            Log::error("Failed to get email from S3", [
                'account_id' => $account->id,
                'account_email' => $account->email,
                'folder' => $folder,
                'message_id' => $messageId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get S3 URL for email file
     */
    public function getEmailS3Url(EmailAccount $account, string $folder, string $messageId): ?string
    {
        try {
            $filePath = $this->getEmailFilePath($account, $folder, $messageId);
            
            if (Storage::disk('s3')->exists($filePath)) {
                $region = config('filesystems.disks.s3.region');
                $bucket = config('filesystems.disks.s3.bucket');
                return "https://{$bucket}.s3.{$region}.amazonaws.com/{$filePath}";
            }
            
            return null;
        } catch (\Exception $e) {
            Log::error("Failed to get S3 URL for email", [
                'account_id' => $account->id,
                'account_email' => $account->email,
                'folder' => $folder,
                'message_id' => $messageId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Delete email from S3
     */
    public function deleteEmailFromS3(EmailAccount $account, string $folder, string $messageId): bool
    {
        try {
            $filePath = $this->getEmailFilePath($account, $folder, $messageId);
            
            if (Storage::disk('s3')->exists($filePath)) {
                Storage::disk('s3')->delete($filePath);
                Log::info("Deleted email from S3", [
                    'account_id' => $account->id,
                    'account_email' => $account->email,
                    'folder' => $folder,
                    'message_id' => $messageId,
                    's3_path' => $filePath
                ]);
                return true;
            }
            
            return false;
        } catch (\Exception $e) {
            Log::error("Failed to delete email from S3", [
                'account_id' => $account->id,
                'account_email' => $account->email,
                'folder' => $folder,
                'message_id' => $messageId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Check if folder exists on S3
     */
    private function folderExists(string $folderPath): bool
    {
        try {
            // S3 doesn't have true folders, so we check if any files exist with this prefix
            $files = Storage::disk('s3')->files($folderPath);
            return !empty($files);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Create folder on S3 (by creating a placeholder file)
     */
    private function createFolder(string $folderPath): void
    {
        try {
            // Create a placeholder file to simulate folder creation
            $placeholderPath = $folderPath . '/.folder_placeholder';
            Storage::disk('s3')->put($placeholderPath, '');
        } catch (\Exception $e) {
            Log::error("Failed to create S3 folder", [
                'folder_path' => $folderPath,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Sanitize email address for use in file paths
     */
    private function sanitizeEmailForPath(string $email): string
    {
        return str_replace(['@', '.', '+'], ['_at_', '_dot_', '_plus_'], $email);
    }

    /**
     * Sanitize folder name for use in file paths
     */
    private function sanitizeFolderName(string $folder): string
    {
        return preg_replace('/[^a-zA-Z0-9\-_]/', '_', $folder);
    }

    /**
     * Sanitize message ID for use in file paths
     */
    private function sanitizeMessageId(string $messageId): string
    {
        return preg_replace('/[^a-zA-Z0-9\-_\.]/', '_', $messageId);
    }

    /**
     * Get storage statistics for this email account on S3
     */
    public function getAccountStorageStats(EmailAccount $account): array
    {
        try {
            $accountPath = $this->getAccountPath($account);
            $files = Storage::disk('s3')->allFiles($accountPath);
            
            $totalSize = 0;
            $fileCount = 0;
            
            foreach ($files as $file) {
                if (!str_ends_with($file, '.folder_placeholder')) {
                    $size = Storage::disk('s3')->size($file);
                    $totalSize += $size;
                    $fileCount++;
                }
            }
            
            return [
                'total_files' => $fileCount,
                'total_size' => $totalSize,
                'total_size_mb' => round($totalSize / 1024 / 1024, 2),
                'account_path' => $accountPath
            ];
        } catch (\Exception $e) {
            Log::error("Failed to get S3 storage stats", [
                'account_id' => $account->id,
                'error' => $e->getMessage()
            ]);
            
            return [
                'total_files' => 0,
                'total_size' => 0,
                'total_size_mb' => 0,
                'account_path' => null
            ];
        }
    }
}
