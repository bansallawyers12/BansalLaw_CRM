<?php

namespace App\Jobs;

use App\Logging\InboxSyncLogger;
use App\Services\EmailSync\InboxSyncStatusStore;
use App\Services\EmailSync\ManualInboxSyncRunner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SyncInboxEmailsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public int $tries = 1;

    /**
     * @param  array<string, mixed>  $prepared
     */
    public function __construct(
        public string $syncId,
        public int $staffId,
        public array $prepared,
    ) {
        $this->onQueue((string) config('imap_sync.queue', 'default'));
    }

    public function handle(ManualInboxSyncRunner $runner, InboxSyncStatusStore $statusStore): void
    {
        InboxSyncLogger::info('Background inbox sync started', [
            'sync_id' => $this->syncId,
            'staff_id' => $this->staffId,
            'sync_range' => $this->prepared['sync_range'] ?? null,
            'email' => $this->prepared['email'] ?? null,
            'addresses' => $this->prepared['addresses'] ?? [],
        ]);

        $statusStore->markRunning($this->syncId);

        try {
            $summary = $runner->execute($this->prepared);

            if (($summary['success'] ?? true) === false) {
                $message = (string) ($summary['message'] ?? 'Inbox sync failed.');
                InboxSyncLogger::error('Background inbox sync failed', [
                    'sync_id' => $this->syncId,
                    'staff_id' => $this->staffId,
                    'message' => $message,
                    'summary' => $summary,
                ]);
                $statusStore->markFailed($this->syncId, $message, $summary);

                return;
            }

            if ((int) ($summary['total_failed'] ?? 0) > 0) {
                $message = InboxSyncStatusStore::buildResultMessage($summary);
                InboxSyncLogger::error('Background inbox sync completed with failures', [
                    'sync_id' => $this->syncId,
                    'staff_id' => $this->staffId,
                    'message' => $message,
                    'summary' => $summary,
                ]);
                $statusStore->markFailed($this->syncId, $message, $summary);

                return;
            }

            InboxSyncLogger::info('Background inbox sync completed', [
                'sync_id' => $this->syncId,
                'staff_id' => $this->staffId,
                'total_imported' => (int) ($summary['total_imported'] ?? 0),
                'total_skipped' => (int) ($summary['total_skipped'] ?? 0),
                'total_failed' => (int) ($summary['total_failed'] ?? 0),
                'mailboxes' => array_keys($summary['mailboxes'] ?? []),
            ]);
            $statusStore->markCompleted($this->syncId, $summary);
        } catch (Throwable $e) {
            InboxSyncLogger::error('Background inbox sync exception', [
                'sync_id' => $this->syncId,
                'staff_id' => $this->staffId,
            ], $e);
            $statusStore->markFailed($this->syncId, $e->getMessage());
        }
    }

    public function failed(Throwable $exception): void
    {
        InboxSyncLogger::error('Background inbox sync job failed permanently', [
            'sync_id' => $this->syncId,
            'staff_id' => $this->staffId,
        ], $exception);

        app(InboxSyncStatusStore::class)->markFailed($this->syncId, $exception->getMessage());
    }
}
