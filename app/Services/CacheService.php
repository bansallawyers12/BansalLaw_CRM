<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * TIER 1 OPTIMIZATION: Centralized cache service for easy Redis caching
 * 
 * Usage Examples:
 * 
 * // Cache a query result
 * $clients = CacheService::remember('active_clients', 600, function() {
 *     return Admin::where('status', 'active')->get();
 * });
 * 
 * // Cache with dynamic key
 * $client = CacheService::remember("client_{$id}", 3600, function() use ($id) {
 *     return Admin::with(['applications', 'invoices'])->find($id);
 * });
 * 
 * // Clear cache
 * CacheService::forget('active_clients');
 * CacheService::forget("client_{$id}");
 * 
 * // Clear multiple related caches
 * CacheService::forgetMany(['active_clients', 'dashboard_stats']);
 * 
 * // Clear cache by pattern (Redis only)
 * CacheService::forgetByPattern('client_*');
 */
class CacheService
{
    /**
     * Cache a value with a key for specified duration
     * 
     * @param string $key Cache key
     * @param int $ttl Time to live in seconds
     * @param callable $callback Function to execute if cache miss
     * @return mixed
     */
    public static function remember(string $key, int $ttl, callable $callback)
    {
        try {
            return Cache::remember($key, $ttl, $callback);
        } catch (\Exception $e) {
            Log::error("Cache error for key '{$key}': " . $e->getMessage());
            // If cache fails, execute callback directly
            return $callback();
        }
    }

    /**
     * Cache a value forever (until manually cleared)
     * 
     * @param string $key Cache key
     * @param callable $callback Function to execute if cache miss
     * @return mixed
     */
    public static function rememberForever(string $key, callable $callback)
    {
        try {
            return Cache::rememberForever($key, $callback);
        } catch (\Exception $e) {
            Log::error("Cache error for key '{$key}': " . $e->getMessage());
            return $callback();
        }
    }

    /**
     * Forget (delete) a cache entry
     * 
     * @param string $key Cache key to forget
     * @return bool
     */
    public static function forget(string $key): bool
    {
        try {
            return Cache::forget($key);
        } catch (\Exception $e) {
            Log::error("Cache forget error for key '{$key}': " . $e->getMessage());
            return false;
        }
    }

    /**
     * Forget multiple cache entries
     * 
     * @param array $keys Array of cache keys to forget
     * @return void
     */
    public static function forgetMany(array $keys): void
    {
        foreach ($keys as $key) {
            self::forget($key);
        }
    }

    /**
     * Forget cache entries by pattern (Redis only)
     * 
     * @param string $pattern Pattern to match (e.g., 'client_*')
     * @return void
     */
    public static function forgetByPattern(string $pattern): void
    {
        if (config('cache.default') !== 'redis') {
            Log::warning("forgetByPattern only works with Redis cache driver");
            return;
        }

        try {
            $redis = Cache::getRedis();
            $keys = $redis->keys(config('cache.prefix') . ':' . $pattern);
            
            if (!empty($keys)) {
                $redis->del($keys);
            }
        } catch (\Exception $e) {
            Log::error("Cache pattern forget error for pattern '{$pattern}': " . $e->getMessage());
        }
    }

    /**
     * Clear all cache
     * 
     * @return bool
     */
    public static function flush(): bool
    {
        try {
            return Cache::flush();
        } catch (\Exception $e) {
            Log::error("Cache flush error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get a value from cache without fallback
     * 
     * @param string $key Cache key
     * @param mixed $default Default value if not found
     * @return mixed
     */
    public static function get(string $key, $default = null)
    {
        try {
            return Cache::get($key, $default);
        } catch (\Exception $e) {
            Log::error("Cache get error for key '{$key}': " . $e->getMessage());
            return $default;
        }
    }

    /**
     * Put a value in cache
     * 
     * @param string $key Cache key
     * @param mixed $value Value to cache
     * @param int $ttl Time to live in seconds
     * @return bool
     */
    public static function put(string $key, $value, int $ttl): bool
    {
        try {
            return Cache::put($key, $value, $ttl);
        } catch (\Exception $e) {
            Log::error("Cache put error for key '{$key}': " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if a key exists in cache
     * 
     * @param string $key Cache key
     * @return bool
     */
    public static function has(string $key): bool
    {
        try {
            return Cache::has($key);
        } catch (\Exception $e) {
            Log::error("Cache has error for key '{$key}': " . $e->getMessage());
            return false;
        }
    }

    /**
     * Common cache keys for the application
     */
    public const DASHBOARD_STATS = 'dashboard_stats';
    public const ACTIVE_CLIENTS = 'active_clients';
    public const PENDING_APPLICATIONS = 'pending_applications';
    
    /**
     * Cache key builders for dynamic keys
     */
    public static function clientKey(int $clientId): string
    {
        return "client_{$clientId}";
    }

    public static function clientApplicationsKey(int $clientId): string
    {
        return "client_{$clientId}_applications";
    }

    public static function clientInvoicesKey(int $clientId): string
    {
        return "client_{$clientId}_invoices";
    }

    public static function clientDocumentsKey(int $clientId): string
    {
        return "client_{$clientId}_documents";
    }

    public static function dashboardUserKey(int $userId): string
    {
        return "dashboard_user_{$userId}";
    }

    /**
     * Clear all client-related caches
     * 
     * @param int $clientId
     * @return void
     */
    public static function clearClientCache(int $clientId): void
    {
        self::forgetMany([
            self::clientKey($clientId),
            self::clientApplicationsKey($clientId),
            self::clientInvoicesKey($clientId),
            self::clientDocumentsKey($clientId),
        ]);
        
        // Also clear general caches that might include this client
        self::forgetMany([
            self::DASHBOARD_STATS,
            self::ACTIVE_CLIENTS,
        ]);
    }

    /**
     * Clear dashboard caches
     * 
     * @return void
     */
    public static function clearDashboardCache(): void
    {
        self::forgetMany([
            self::DASHBOARD_STATS,
            self::ACTIVE_CLIENTS,
            self::PENDING_APPLICATIONS,
        ]);
    }
}
