<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BansalAppointmentSync\NotificationService;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'booking:send-reminders')]
class SendAppointmentReminders extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'booking:send-reminders';

    /**
     * The console command description.
     */
    protected $description = 'Send SMS reminders for appointments happening tomorrow';

    /**
     * Execute the console command.
     */
    public function handle(NotificationService $notificationService): int
    {
        $this->info('Sending appointment reminders...');

        try {
            $stats = $notificationService->sendUpcomingReminders();

            $this->info("✓ Sent {$stats['sent']} reminders");
            
            if ($stats['failed'] > 0) {
                $this->warn("⚠ {$stats['failed']} reminders failed");
            }

            $this->newLine();
            $this->table(
                ['Metric', 'Count'],
                [
                    ['Total Due', $stats['total']],
                    ['Sent', $stats['sent']],
                    ['Failed', $stats['failed']],
                ]
            );

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('✗ Failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}

