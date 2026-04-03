<?php

namespace App\Console\Commands;

use App\Services\CrmAccess\CrmAccessService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ExpireCrmAccessGrants extends Command
{
    protected $signature = 'access:expire-grants';

    protected $description = 'Mark expired CRM cross-access grants as expired (safety net)';

    public function handle(CrmAccessService $crmAccess): int
    {
        $n = $crmAccess->expireStaleGrants();
        $this->info("Expired {$n} grant(s).");

        if ($n > 0) {
            Cache::forget(CacheAccessGrantGlobalCounts::CACHE_KEY);
        }

        return self::SUCCESS;
    }
}
