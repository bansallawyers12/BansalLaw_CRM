<?php

namespace App\Services;

use App\Models\EmailAccount;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class S3AttachmentStorageService
{
    /**
     * Base S3 path for email attachments
     */
    private const BASE_S3_PATH = 'email-attachments';

    /**
     * Save attachment to S3
     */
    public function saveAttachmentToS3(EmailAccount $account, string $messageId, string $filename, string $content, string $contentType = null): array
    {
        try {
            $safeMessageId = $this->sanitizeMessageId($messageId);
            $safeFilename = $this->sanitizeFilename($filename);
            
            // Create S3 path: email-attachments/{account_email}/{message_id}/{filename}
            $accountPath = $this->sanitizeEmailForPath($account->email);
            $s3Path = self::BASE_S3_PATH . '/' . $accountPath . '/' . $safeMessageId . '/' . $safeFilename;
            
            // Save attachment to S3
            Storage::disk('s3')->put($s3Path, $content);
            
            // Get file size
            $fileSize = Storage::disk('s3')->size($s3Path);
            
            // Get S3 URL
            $region = config('filesystems.disks.s3.region');
            $bucket = config('filesystems.disks.s3.bucket');
            $s3Url = "https://{$bucket}.s3.{$region}.amazonaws.com/{$s3Path}";
            
            Log::info("Saved attachment to S3", [
                'account_id' => $account->id,
                'account_email' => $account->email,
                'message_id' => $messageId,
                'filename' => $filename,
                's3_path' => $s3Path,
                'file_size' => $fileSize
            ]);

            return [
                'success' => true,
                's3_path' => $s3Path,
                's3_url' => $s3Url,
                'filename' => $safeFilename,
                'original_filename' => $filename,
                'file_size' => $fileSize,
                'content_type' => $contentType,
                'message_id' => $messageId
            ];
        } catch (\Exception $e) {
            Log::error("Failed to save attachment to S3", [
                'account_id' => $account->id,
                'account_email' => $account->email,
                'message_id' => $messageId,
                'filename' => $filename,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get attachment from S3
     */
    public function getAttachmentFromS3(string $s3Path): ?string
    {
        try {
            if (Storage::disk('s3')->exists($s3Path)) {
                return Storage::disk('s3')->get($s3Path);
            }
            
            return null;
        } catch (\Exception $e) {
            Log::error("Failed to get attachment from S3", [
                's3_path' => $s3Path,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get S3 URL for attachment
     */
    public function getAttachmentS3Url(string $s3Path): ?string
    {
        try {
            if (Storage::disk('s3')->exists($s3Path)) {
                $region = config('filesystems.disks.s3.region');
                $bucket = config('filesystems.disks.s3.bucket');
                return "https://{$bucket}.s3.{$region}.amazonaws.com/{$s3Path}";
            }
            
            return null;
        } catch (\Exception $e) {
            Log::error("Failed to get S3 URL for attachment", [
                's3_path' => $s3Path,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Delete attachment from S3
     */
    public function deleteAttachmentFromS3(string $s3Path): bool
    {
        try {
            if (Storage::disk('s3')->exists($s3Path)) {
                Storage::disk('s3')->delete($s3Path);
                Log::info("Deleted attachment from S3", [
                    's3_path' => $s3Path
                ]);
                return true;
            }
            
            return false;
        } catch (\Exception $e) {
            Log::error("Failed to delete attachment from S3", [
                's3_path' => $s3Path,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get attachment info from S3
     */
    public function getAttachmentInfo(string $s3Path): ?array
    {
        try {
            if (Storage::disk('s3')->exists($s3Path)) {
                $size = Storage::disk('s3')->size($s3Path);
                $lastModified = Storage::disk('s3')->lastModified($s3Path);
                $region = config('filesystems.disks.s3.region');
                $bucket = config('filesystems.disks.s3.bucket');
                $url = "https://{$bucket}.s3.{$region}.amazonaws.com/{$s3Path}";
                
                return [
                    'size' => $size,
                    'last_modified' => $lastModified,
                    'url' => $url,
                    'exists' => true
                ];
            }
            
            return null;
        } catch (\Exception $e) {
            Log::error("Failed to get attachment info from S3", [
                's3_path' => $s3Path,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * List attachments for a specific message
     */
    public function listAttachmentsForMessage(EmailAccount $account, string $messageId): array
    {
        try {
            $safeMessageId = $this->sanitizeMessageId($messageId);
            $accountPath = $this->sanitizeEmailForPath($account->email);
            $messagePath = self::BASE_S3_PATH . '/' . $accountPath . '/' . $safeMessageId;
            
            $files = Storage::disk('s3')->files($messagePath);
            
            $attachments = [];
            foreach ($files as $file) {
                $info = $this->getAttachmentInfo($file);
                if ($info) {
                    $attachments[] = [
                        's3_path' => $file,
                        'filename' => basename($file),
                        'size' => $info['size'],
                        'url' => $info['url'],
                        'last_modified' => $info['last_modified']
                    ];
                }
            }
            
            return $attachments;
        } catch (\Exception $e) {
            Log::error("Failed to list attachments for message", [
                'account_id' => $account->id,
                'message_id' => $messageId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Get storage statistics for attachments
     */
    public function getAttachmentStorageStats(EmailAccount $account = null): array
    {
        try {
            $basePath = self::BASE_S3_PATH;
            
            if ($account) {
                $accountPath = $this->sanitizeEmailForPath($account->email);
                $basePath = self::BASE_S3_PATH . '/' . $accountPath;
            }
            
            $files = Storage::disk('s3')->allFiles($basePath);
            
            $totalSize = 0;
            $fileCount = 0;
            $accounts = [];
            
            foreach ($files as $file) {
                $size = Storage::disk('s3')->size($file);
                $totalSize += $size;
                $fileCount++;
                
                // Extract account info from path
                $pathParts = explode('/', $file);
                if (count($pathParts) >= 3) {
                    $accountEmail = str_replace(['_at_', '_dot_', '_plus_'], ['@', '.', '+'], $pathParts[2]);
                    if (!isset($accounts[$accountEmail])) {
                        $accounts[$accountEmail] = ['files' => 0, 'size' => 0];
                    }
                    $accounts[$accountEmail]['files']++;
                    $accounts[$accountEmail]['size'] += $size;
                }
            }
            
            return [
                'total_files' => $fileCount,
                'total_size' => $totalSize,
                'total_size_mb' => round($totalSize / 1024 / 1024, 2),
                'accounts' => $accounts,
                'base_path' => $basePath
            ];
        } catch (\Exception $e) {
            Log::error("Failed to get attachment storage stats", [
                'account_id' => $account ? $account->id : null,
                'error' => $e->getMessage()
            ]);
            
            return [
                'total_files' => 0,
                'total_size' => 0,
                'total_size_mb' => 0,
                'accounts' => [],
                'base_path' => null
            ];
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
     * Sanitize message ID for use in file paths
     */
    private function sanitizeMessageId(string $messageId): string
    {
        return preg_replace('/[^a-zA-Z0-9\-_\.]/', '_', $messageId);
    }

    /**
     * Sanitize filename for use in file paths
     */
    private function sanitizeFilename(string $filename): string
    {
        // Remove or replace dangerous characters
        $filename = preg_replace('/[^a-zA-Z0-9\-_\.]/', '_', $filename);
        
        // Ensure filename is not empty
        if (empty($filename)) {
            $filename = 'attachment_' . time();
        }
        
        // Limit filename length
        if (strlen($filename) > 255) {
            $extension = pathinfo($filename, PATHINFO_EXTENSION);
            $name = pathinfo($filename, PATHINFO_FILENAME);
            $filename = substr($name, 0, 255 - strlen($extension) - 1) . '.' . $extension;
        }
        
        return $filename;
    }

    /**
     * Generate presigned URL for secure attachment access
     */
    public function generatePresignedUrl(string $s3Path, int $expirationMinutes = 60): ?string
    {
        try {
            if (Storage::disk('s3')->exists($s3Path)) {
                // For now, return the public URL. In production, you might want to use AWS SDK to generate presigned URLs
                $region = config('filesystems.disks.s3.region');
                $bucket = config('filesystems.disks.s3.bucket');
                return "https://{$bucket}.s3.{$region}.amazonaws.com/{$s3Path}";
            }
            
            return null;
        } catch (\Exception $e) {
            Log::error("Failed to generate presigned URL", [
                's3_path' => $s3Path,
                'expiration_minutes' => $expirationMinutes,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
}
