<?php

namespace App\Support;

/**
 * Release the PHP session write lock early on long or parallel AJAX requests.
 * Without this, a slow GET (e.g. activity feed) can block POST /create-note on the same browser session.
 */
final class ReleaseSessionLock
{
    public static function forParallelRequests(): void
    {
        if (app()->runningInConsole()) {
            return;
        }

        try {
            if (session()->isStarted()) {
                session()->save();
            }
        } catch (\Throwable $e) {
            // Non-fatal: continue the request even if session could not be flushed.
        }
    }
}
