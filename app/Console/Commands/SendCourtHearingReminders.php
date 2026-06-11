<?php

namespace App\Console\Commands;

use App\Services\CourtHearingReminderService;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'court-hearings:send-reminders')]
class SendCourtHearingReminders extends Command
{
    protected $signature = 'court-hearings:send-reminders';

    protected $description = 'Send SMS reminders to clients for upcoming court hearings';

    public function handle(CourtHearingReminderService $reminderService): int
    {
        $this->info('Sending court hearing SMS reminders...');

        try {
            $stats = $reminderService->sendDueReminders();

            $this->info("✓ Sent {$stats['sent']} reminder(s)");

            if ($stats['failed'] > 0) {
                $this->warn("⚠ {$stats['failed']} reminder(s) failed");
            }

            if ($stats['skipped'] > 0) {
                $this->line("Skipped {$stats['skipped']} past hearing(s)");
            }

            $this->newLine();
            $this->table(
                ['Metric', 'Count'],
                [
                    ['Due now', $stats['total']],
                    ['Sent', $stats['sent']],
                    ['Failed', $stats['failed']],
                    ['Skipped (past)', $stats['skipped']],
                ]
            );

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('✗ Failed: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
