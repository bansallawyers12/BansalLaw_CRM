<?php

namespace App\Services;

use App\Models\EmailAccount;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class EmailFolderService
{
    /**
     * Base storage path for email accounts
     */
    private const BASE_STORAGE_PATH = 'email-accounts';
    
    /**
     * Storage disk to use for email storage
     */
    private const STORAGE_DISK = 's3-emails';

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
     * Create folder structure for an email account
     */
    public function createAccountFolders(EmailAccount $account): bool
    {
        try {
            $accountPath = $this->getAccountPath($account);
            
            // Create main account folder
            if (!Storage::disk(self::STORAGE_DISK)->exists($accountPath)) {
                Storage::disk(self::STORAGE_DISK)->makeDirectory($accountPath);
                Log::info("Created main folder for account: {$account->email}", [
                    'account_id' => $account->id,
                    'path' => $accountPath
                ]);
            }

            // Create default subfolders
            foreach (self::DEFAULT_FOLDERS as $folder) {
                $folderPath = $accountPath . '/' . $folder;
                if (!Storage::disk(self::STORAGE_DISK)->exists($folderPath)) {
                    Storage::disk(self::STORAGE_DISK)->makeDirectory($folderPath);
                    Log::info("Created folder: {$folder} for account: {$account->email}", [
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
            Log::error("Failed to create folders for account: {$account->email}", [
                'account_id' => $account->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Create provider-specific folders
     */
    private function createProviderSpecificFolders(EmailAccount $account): void
    {
        $accountPath = $this->getAccountPath($account);
        $providerFolders = [];

        // Only Zoho is supported; keep Zoho-specific extras
        $providerFolders = ['Important', 'All Mail'];

        foreach ($providerFolders as $folder) {
            $folderPath = $accountPath . '/' . $folder;
            if (!Storage::disk(self::STORAGE_DISK)->exists($folderPath)) {
                Storage::disk(self::STORAGE_DISK)->makeDirectory($folderPath);
                Log::info("Created provider-specific folder: {$folder} for account: {$account->email}", [
                    'account_id' => $account->id,
                    'provider' => $account->provider,
                    'folder' => $folder,
                    'path' => $folderPath
                ]);
            }
        }
    }

    /**
     * Get the storage path for an email account
     */
    public function getAccountPath(EmailAccount $account): string
    {
        $safeEmail = $this->sanitizeEmailForPath($account->email);
        return self::BASE_STORAGE_PATH . '/' . $safeEmail;
    }

    /**
     * Get the storage path for a specific folder within an account
     */
    public function getFolderPath(EmailAccount $account, string $folder): string
    {
        $accountPath = $this->getAccountPath($account);
        $safeFolder = $this->sanitizeFolderName($folder);
        return $accountPath . '/' . $safeFolder;
    }

    /**
     * Get the storage path for an email file
     */
    public function getEmailFilePath(EmailAccount $account, string $folder, string $messageId): string
    {
        $folderPath = $this->getFolderPath($account, $folder);
        $safeMessageId = $this->sanitizeMessageId($messageId);
        return $folderPath . '/' . $safeMessageId . '.eml';
    }

    /**
     * Save email content to local file
     */
    public function saveEmailToFile(EmailAccount $account, string $folder, string $messageId, string $emailContent): bool
    {
        try {
            $filePath = $this->getEmailFilePath($account, $folder, $messageId);
            
            // Ensure the folder exists
            $folderPath = $this->getFolderPath($account, $folder);
            if (!Storage::disk(self::STORAGE_DISK)->exists($folderPath)) {
                Storage::disk(self::STORAGE_DISK)->makeDirectory($folderPath);
            }

            // Save the email content
            Storage::disk(self::STORAGE_DISK)->put($filePath, $emailContent);
            
            Log::info("Saved email to S3 storage", [
                'account_id' => $account->id,
                'account_email' => $account->email,
                'folder' => $folder,
                'message_id' => $messageId,
                'file_path' => $filePath
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error("Failed to save email to S3 storage", [
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
     * Get email content from local file
     */
    public function getEmailFromFile(EmailAccount $account, string $folder, string $messageId): ?string
    {
        try {
            $filePath = $this->getEmailFilePath($account, $folder, $messageId);
            
            if (Storage::disk(self::STORAGE_DISK)->exists($filePath)) {
                return Storage::disk(self::STORAGE_DISK)->get($filePath);
            }
            
            return null;
        } catch (\Exception $e) {
            Log::error("Failed to read email from S3 storage", [
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
     * Check if email exists in local storage
     */
    public function emailExistsInLocalStorage(EmailAccount $account, string $folder, string $messageId): bool
    {
        $filePath = $this->getEmailFilePath($account, $folder, $messageId);
        return Storage::disk(self::STORAGE_DISK)->exists($filePath);
    }

    /**
     * Get all folders for an account
     */
    public function getAccountFolders(EmailAccount $account): array
    {
        try {
            $accountPath = $this->getAccountPath($account);
            
            if (!Storage::disk(self::STORAGE_DISK)->exists($accountPath)) {
                return [];
            }

            $folders = Storage::disk(self::STORAGE_DISK)->directories($accountPath);
            return array_map(function($folder) use ($accountPath) {
                return str_replace($accountPath . '/', '', $folder);
            }, $folders);
        } catch (\Exception $e) {
            Log::error("Failed to get folders for account", [
                'account_id' => $account->id,
                'account_email' => $account->email,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Get email count for a specific folder
     */
    public function getFolderEmailCount(EmailAccount $account, string $folder): int
    {
        try {
            $folderPath = $this->getFolderPath($account, $folder);
            
            if (!Storage::disk(self::STORAGE_DISK)->exists($folderPath)) {
                return 0;
            }

            $files = Storage::disk(self::STORAGE_DISK)->files($folderPath);
            return count(array_filter($files, function($file) {
                return pathinfo($file, PATHINFO_EXTENSION) === 'eml';
            }));
        } catch (\Exception $e) {
            Log::error("Failed to get email count for folder", [
                'account_id' => $account->id,
                'account_email' => $account->email,
                'folder' => $folder,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * Delete account folders and all contents
     */
    public function deleteAccountFolders(EmailAccount $account): bool
    {
        try {
            $accountPath = $this->getAccountPath($account);
            
            if (Storage::disk(self::STORAGE_DISK)->exists($accountPath)) {
                Storage::disk(self::STORAGE_DISK)->deleteDirectory($accountPath);
                Log::info("Deleted all folders for account: {$account->email}", [
                    'account_id' => $account->id,
                    'path' => $accountPath
                ]);
            }

            return true;
        } catch (\Exception $e) {
            Log::error("Failed to delete folders for account: {$account->email}", [
                'account_id' => $account->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Sanitize email address for use in file path
     */
    private function sanitizeEmailForPath(string $email): string
    {
        // Replace @ with _at_ and remove invalid characters
        $sanitized = str_replace('@', '_at_', $email);
        $sanitized = preg_replace('/[^a-zA-Z0-9._-]/', '_', $sanitized);
        return $sanitized;
    }

    /**
     * Sanitize folder name for use in file path
     */
    private function sanitizeFolderName(string $folder): string
    {
        // Remove invalid characters and replace spaces with underscores
        $sanitized = preg_replace('/[^a-zA-Z0-9._-]/', '_', $folder);
        $sanitized = str_replace(' ', '_', $sanitized);
        return $sanitized;
    }

    /**
     * Sanitize message ID for use in file path
     */
    private function sanitizeMessageId(string $messageId): string
    {
        // Remove invalid characters and limit length
        $sanitized = preg_replace('/[^a-zA-Z0-9._-]/', '_', $messageId);
        return substr($sanitized, 0, 100); // Limit to 100 characters
    }

    /**
     * Get storage statistics for an account
     */
    public function getAccountStorageStats(EmailAccount $account): array
    {
        try {
            $accountPath = $this->getAccountPath($account);
            $stats = [
                'total_emails' => 0,
                'total_size' => 0,
                'folders' => []
            ];

            if (!Storage::disk(self::STORAGE_DISK)->exists($accountPath)) {
                return $stats;
            }

            $folders = $this->getAccountFolders($account);
            
            foreach ($folders as $folder) {
                $folderPath = $this->getFolderPath($account, $folder);
                $files = Storage::disk(self::STORAGE_DISK)->files($folderPath);
                $emailFiles = array_filter($files, function($file) {
                    return pathinfo($file, PATHINFO_EXTENSION) === 'eml';
                });

                $folderSize = 0;
                foreach ($emailFiles as $file) {
                    $folderSize += Storage::disk(self::STORAGE_DISK)->size($file);
                }

                $stats['folders'][$folder] = [
                    'email_count' => count($emailFiles),
                    'size' => $folderSize
                ];
                $stats['total_emails'] += count($emailFiles);
                $stats['total_size'] += $folderSize;
            }

            return $stats;
        } catch (\Exception $e) {
            Log::error("Failed to get storage stats for account", [
                'account_id' => $account->id,
                'account_email' => $account->email,
                'error' => $e->getMessage()
            ]);
            return [
                'total_emails' => 0,
                'total_size' => 0,
                'folders' => []
            ];
        }
    }
}
