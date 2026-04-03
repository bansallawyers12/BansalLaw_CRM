<?php

namespace App\Console\Commands;

use App\Models\Email;
use App\Models\EmailDraft;
use App\Models\Attachment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;

class RefreshEmailData extends Command
{
    protected $signature = 'emails:refresh {--force : Force refresh without confirmation}';
    protected $description = 'Clear all email data and reset sync state for fresh start';

    public function handle()
    {
        if (!$this->option('force')) {
            if (!$this->confirm('This will delete ALL email data, attachments, and drafts. Are you sure?')) {
                $this->info('Operation cancelled.');
                return;
            }
        }

        $this->info('Starting email data refresh...');

        // Clear email data
        $this->clearEmailData();
        
        // Clear attachment files
        $this->clearAttachmentFiles();
        
        // Clear drafts
        $this->clearDrafts();
        
        // Clear storage directories
        $this->clearStorageDirectories();

        $this->info('✅ Email data refresh completed successfully!');
        $this->info('You can now sync emails from the start.');
    }

    private function clearEmailData()
    {
        $this->info('Clearing email data...');
        
        // Delete all emails (this will cascade delete attachments due to foreign key)
        $emailCount = Email::count();
        Email::truncate();
        
        $this->line("   - Deleted {$emailCount} emails");
    }

    private function clearAttachmentFiles()
    {
        $this->info('Clearing attachment files...');
        
        // Clear attachment storage directories
        $attachmentDirs = [
            'storage/app/attachments',
            'storage/app/temp/attachments',
            'storage/app/email-attachments',
        ];

        $totalFiles = 0;
        foreach ($attachmentDirs as $dir) {
            if (File::exists($dir)) {
                $files = File::allFiles($dir);
                $totalFiles += count($files);
                File::deleteDirectory($dir);
                File::makeDirectory($dir, 0755, true);
            }
        }

        $this->line("   - Cleared {$totalFiles} attachment files");
    }

    private function clearDrafts()
    {
        $this->info('Clearing email drafts...');
        
        $draftCount = EmailDraft::count();
        EmailDraft::truncate();
        
        $this->line("   - Deleted {$draftCount} drafts");
    }

    private function clearStorageDirectories()
    {
        $this->info('Recreating storage directories...');
        
        $directories = [
            'storage/app/attachments',
            'storage/app/temp/attachments',
            'storage/app/email-attachments',
        ];

        foreach ($directories as $dir) {
            if (!File::exists($dir)) {
                File::makeDirectory($dir, 0755, true);
            }
        }

        $this->line("   - Storage directories recreated");
    }
}