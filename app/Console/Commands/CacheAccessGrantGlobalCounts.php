<?php

namespace App\Console\Commands;

use App\Models\ClientAccessGrant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class CacheAccessGrantGlobalCounts extends Command
{
    public const CACHE_KEY = 'crm.access_grants.global_counts';

    /** @var int */
    public const TTL_SECONDS = 3600;

    /**
     * @var string
     */
    protected $signature = 'access:cache-grant-stats';

    /**
     * @var string
     */
    protected $description = 'Refresh cached global pending/active counts for the CRM access grants dashboard.';

    public function handle(): int
    {
        $payload = [
            'pending_count' => ClientAccessGrant::query()->where('status', 'pending')->count(),
            'active_count' => ClientAccessGrant::query()->where('status', 'active')->count(),
        ];

        Cache::put(self::CACHE_KEY, $payload, self::TTL_SECONDS);

        $this->info('Cached pending_count='.$payload['pending_count'].', active_count='.$payload['active_count'].'.');

        return self::SUCCESS;
    }
}
