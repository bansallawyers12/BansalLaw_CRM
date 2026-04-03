<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Document;
use App\Services\SignatureService;
use Illuminate\Support\Facades\Log;

class SendSignatureReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'signatures:send-auto-reminders {--days=7 : Documents pending longer than this get reminders}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically send reminders for pending signatures older than specified days (max 3 reminders per signer)';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(SignatureService $signatureService)
    {
        $days = (int) $this->option('days');
        
        $this->info("📧 Sending automatic reminders for documents pending >{$days} days...");
        
        try {
            // Find pending documents older than specified days
            $documents = Document::where('status', 'sent')
                ->where('created_at', '<', now()->subDays($days))
                ->notArchived()
                ->with('signers')
                ->get();
            
            $totalDocuments = $documents->count();
            $this->info("Found {$totalDocuments} pending document(s) older than {$days} days");
            
            if ($totalDocuments === 0) {
                $this->comment("ℹ️  No reminders to send at this time");
                return Command::SUCCESS;
            }
            
            $sentCount = 0;
            $skippedCount = 0;
            $failedCount = 0;
            
            foreach ($documents as $document) {
                $this->line("Processing: {$document->display_title}");
                
                foreach ($document->signers as $signer) {
                    // Only send to pending signers
                    if ($signer->status !== 'pending') {
                        continue;
                    }
                    
                    // Check if max reminders already sent
                    if ($signer->reminder_count >= 3) {
                        $this->comment("  ⏭️  Skipped {$signer->email} (max reminders reached)");
                        $skippedCount++;
                        continue;
                    }
                    
                    // Send reminder
                    if ($signatureService->remind($signer)) {
                        $this->info("  ✅ Sent reminder to {$signer->email} (#{$signer->reminder_count})");
                        $sentCount++;
                    } else {
                        $this->error("  ❌ Failed to send to {$signer->email}");
                        $failedCount++;
                    }
                }
            }
            
            // Summary
            $this->newLine();
            $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->info("📊 Summary:");
            $this->info("   ✅ Sent: {$sentCount} reminder(s)");
            
            if ($skippedCount > 0) {
                $this->comment("   ⏭️  Skipped: {$skippedCount} (limits/cooldown)");
            }
            
            if ($failedCount > 0) {
                $this->error("   ❌ Failed: {$failedCount}");
            }
            
            $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            
            // Log results
            Log::info("Auto-reminders job completed", [
                'sent' => $sentCount,
                'skipped' => $skippedCount,
                'failed' => $failedCount,
                'days_threshold' => $days
            ]);
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error("❌ Error sending auto-reminders: {$e->getMessage()}");
            Log::error("Failed to send auto-reminders", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return Command::FAILURE;
        }
    }
}

