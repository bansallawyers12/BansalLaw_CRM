<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\SignatureService;
use Illuminate\Support\Facades\Log;

class ArchiveOldDrafts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'signatures:archive-drafts {--days=30 : Number of days old drafts should be}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Archive draft documents older than specified days (default: 30)';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(SignatureService $signatureService)
    {
        $days = (int) $this->option('days');
        
        $this->info("🗄️  Archiving draft documents older than {$days} days...");
        
        try {
            $count = $signatureService->archiveOldDrafts($days);
            
            if ($count > 0) {
                $this->info("✅ Successfully archived {$count} old draft document(s)");
                Log::info("Auto-archived {$count} old drafts", ['days' => $days]);
            } else {
                $this->comment("ℹ️  No drafts found to archive");
            }
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("❌ Error archiving drafts: {$e->getMessage()}");
            Log::error("Failed to archive old drafts", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return Command::FAILURE;
        }
    }
}

