<?php

namespace App\Console\Commands;

use App\Services\Email\EmailCalendarMergeService;
use Illuminate\Console\Command;

/**
 * Idempotent: ensures pending email→calendar links become visible calendar events.
 * Safe to run on every deploy. Does not delete emails or calendar records.
 */
class PromotePendingEmailCalendarLinks extends Command
{
    protected $signature = 'emails:promote-pending-calendar';

    protected $description = 'Promote pending email calendar links onto the staff/court calendar (does not remove emails from calendar)';

    public function handle(EmailCalendarMergeService $mergeService): int
    {
        $result = $mergeService->promotePendingLinksOntoCalendar();

        $this->info(sprintf(
            'Calendar promote complete: %d promoted, %d failed.',
            (int) ($result['promoted'] ?? 0),
            (int) ($result['failed'] ?? 0)
        ));

        return self::SUCCESS;
    }
}
