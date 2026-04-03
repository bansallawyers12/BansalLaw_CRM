<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BansalAppointmentSync\AppointmentSyncService;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'booking:sync-appointments')]
class SyncBansalAppointments extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'booking:sync-appointments 
                            {--minutes=10 : Number of minutes to look back}';

    /**
     * The console command description.
     */
    protected $description = 'Sync recent appointments from Bansal Immigration API';

    /**
     * Execute the console command.
     */
    public function handle(AppointmentSyncService $syncService): int
    {
        $minutes = (int)$this->option('minutes');

        $this->info("Starting appointment sync (last {$minutes} minutes)...");

        try {
            $stats = $syncService->syncRecentAppointments($minutes);

            $this->info('✓ Sync completed successfully!');
            $this->newLine();
            
            // Display results table
            $this->table(
                ['Metric', 'Count'],
                [
                    ['Fetched', $stats['fetched']],
                    ['New', $stats['new']],
                    ['Updated', $stats['updated']],
                    ['Skipped', $stats['skipped']],
                    ['Failed', $stats['failed']],
                ]
            );

            if ($stats['failed'] > 0) {
                $this->warn("⚠ {$stats['failed']} appointments failed to sync");
                return self::FAILURE;
            }

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('✗ Sync failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}

