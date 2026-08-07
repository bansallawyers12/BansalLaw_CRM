<?php

namespace App\Console\Commands;

use App\Services\CalendarSync\CalendarSyncMasterControl;
use App\Services\CalendarSync\ZohoToCrmCalendarSyncService;
use Illuminate\Console\Command;

class SyncZohoCalendarEvents extends Command
{
    protected $signature = 'calendar:sync-from-zoho
                            {--days-back= : Days before today to scan (default from config)}
                            {--days-forward= : Days after today to scan (default from config)}';

    protected $description = 'Pull Zoho calendar events into CRM (link matches + unlinked queue)';

    public function handle(ZohoToCrmCalendarSyncService $syncService): int
    {
        if (CalendarSyncMasterControl::isDisabled()) {
            $this->warn(CalendarSyncMasterControl::disabledMessage());

            return self::SUCCESS;
        }

        $this->info('Starting Zoho → CRM calendar sync...');

        $daysBack = $this->option('days-back') !== null ? (int) $this->option('days-back') : null;
        $daysForward = $this->option('days-forward') !== null ? (int) $this->option('days-forward') : null;

        $summary = $syncService->syncAll($daysBack, $daysForward);

        $this->line(sprintf(
            'Scanned: %d | Already linked: %d | Auto-linked: %d | Queued unlinked: %d | Updated queue: %d | Outbound retried: %d',
            $summary['scanned'],
            $summary['linked_seen'],
            $summary['auto_linked'],
            $summary['unlinked_queued'],
            $summary['unlinked_updated'],
            $summary['outbound_retried']
        ));

        foreach ($summary['errors'] as $error) {
            $this->error('  - ' . $error);
        }

        return $summary['errors'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
