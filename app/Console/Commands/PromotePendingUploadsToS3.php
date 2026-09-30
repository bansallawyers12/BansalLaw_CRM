<?php

namespace App\Console\Commands;

use App\Services\CrmDurableStorage;
use Illuminate\Console\Command;

class PromotePendingUploadsToS3 extends Command
{
    protected $signature = 'storage:promote-pending-to-s3';

    protected $description = 'Upload local-only CRM files to S3 when the bucket is available';

    public function handle(CrmDurableStorage $storage): int
    {
        if (! $storage->usesCloud()) {
            $this->warn('S3 is not configured; nothing to promote.');

            return self::SUCCESS;
        }

        $stats = $storage->promoteAllPendingToCloud();

        $this->info(sprintf(
            'Promote complete: scanned=%d promoted=%d skipped=%d failed=%d',
            $stats['scanned'],
            $stats['promoted'],
            $stats['skipped'],
            $stats['failed']
        ));

        return self::SUCCESS;
    }
}
