<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BansalAppointmentSync\AppointmentSyncService;
use Carbon\Carbon;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'booking:backfill')]
class BackfillBansalAppointments extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'booking:backfill 
                            {start_date : Start date (Y-m-d)}
                            {end_date : End date (Y-m-d)}
                            {--force : Skip confirmation prompt}';

    /**
     * The console command description.
     */
    protected $description = 'Backfill historical appointments from Bansal API';

    /**
     * Execute the console command.
     */
    public function handle(AppointmentSyncService $syncService): int
    {
        try {
            $startDate = Carbon::parse($this->argument('start_date'));
            $endDate = Carbon::parse($this->argument('end_date'));
        } catch (\Exception $e) {
            $this->error('Invalid date format. Please use Y-m-d format (e.g., 2024-01-01)');
            return self::FAILURE;
        }

        if ($startDate->isAfter($endDate)) {
            $this->error('Start date must be before end date');
            return self::FAILURE;
        }

        $days = $startDate->diffInDays($endDate);
        
        $this->info("Backfill Configuration:");
        $this->table(
            ['Setting', 'Value'],
            [
                ['Start Date', $startDate->toDateString()],
                ['End Date', $endDate->toDateString()],
                ['Days to Process', $days],
                ['Estimated Records', '~' . ($days * 5) . ' (varies)'],
            ]
        );

        $this->newLine();
        $this->warn("⚠ This operation may take several minutes depending on the date range.");
        $this->warn("⚠ It will fetch ALL appointments in this range from the API.");
        
        // Confirmation prompt (unless --force is used)
        if (!$this->option('force')) {
            $this->newLine();
            if (!$this->confirm('Do you want to continue?', false)) {
                $this->info('Backfill cancelled.');
                return self::SUCCESS;
            }
        }

        $this->newLine();
        $this->info('Starting backfill...');
        $this->newLine();

        try {
            // Create progress bar
            $this->output->write('Processing');
            
            $stats = $syncService->backfillHistoricalData($startDate, $endDate);

            $this->newLine();
            $this->newLine();
            $this->info('✓ Backfill completed!');
            $this->newLine();
            
            $this->table(
                ['Metric', 'Count'],
                [
                    ['Fetched', $stats['fetched']],
                    ['New', $stats['new']],
                    ['Skipped', $stats['skipped']],
                    ['Failed', $stats['failed']],
                ]
            );

            if ($stats['failed'] > 0) {
                $this->newLine();
                $this->warn("⚠ {$stats['failed']} appointments failed to import");
                $this->info("Check the logs for details: storage/logs/laravel.log");
            }

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->newLine();
            $this->newLine();
            $this->error('✗ Backfill failed: ' . $e->getMessage());
            $this->error('Check the logs for more details: storage/logs/laravel.log');
            return self::FAILURE;
        }
    }
}

