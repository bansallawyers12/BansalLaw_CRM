<?php

namespace App\Jobs;

use App\Models\Staff;
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
        $statusStore->markRunning($this->syncId);

        try {
            $summary = $runner->execute($this->prepared);

            if (($summary['success'] ?? true) === false) {
                $statusStore->markFailed(
                    $this->syncId,
                    (string) ($summary['message'] ?? 'Inbox sync failed.'),
                    $summary
                );

                return;
            }

            if ((int) ($summary['total_failed'] ?? 0) > 0) {
                $statusStore->markFailed(
                    $this->syncId,
                    InboxSyncStatusStore::buildResultMessage($summary),
                    $summary
                );

                return;
            }

            $statusStore->markCompleted($this->syncId, $summary);
        } catch (Throwable $e) {
            $statusStore->markFailed($this->syncId, $e->getMessage());
        }
    }

    public function failed(Throwable $exception): void
    {
        app(InboxSyncStatusStore::class)->markFailed($this->syncId, $exception->getMessage());
    }
}
