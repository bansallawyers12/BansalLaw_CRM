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
    protected $description = 'Sync recent appointments from the public booking website API';

    /**
     * Execute the console command.
     */
    public function handle(AppointmentSyncService $syncService): int
    {
        $minutes = max(1, (int) $this->option('minutes'));

        $this->info("Starting appointment sync (last {$minutes} minutes)...");

        if (! $syncService->isApiConfigured()) {
            $this->warn('Bansal appointment API is not configured; skipping sync.');

            return self::SUCCESS;
        }

        try {
            $stats = $syncService->syncRecentAppointments($minutes);

            $this->info('Sync finished.');
            $this->newLine();

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

            // Partial per-appointment failures are logged in sync stats — do not fail the
            // scheduled run (exit 1 makes schedule:run throw and floods system-errors).
            if (($stats['failed'] ?? 0) > 0) {
                $this->warn("⚠ {$stats['failed']} appointment(s) failed to sync (see logs / sync_details).");
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('✗ Sync failed: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}

