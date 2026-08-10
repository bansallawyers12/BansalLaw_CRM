<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Protects Migration CRM → Legal CRM lead handoff routes.
 * Does not affect public POST /api/leads.
 */
class VerifyMigrationCrmToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $configured = (string) config('services.migration_crm.token', '');

        if ($configured === '') {
            Log::channel('migration_legal_crm')->error('Migration CRM token missing on Legal CRM', [
                'path' => $request->path(),
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Migration CRM API token is not configured on Legal CRM.',
            ], 503);
        }

        $bearer = (string) ($request->bearerToken() ?? '');

        if ($bearer === '' || ! hash_equals($configured, $bearer)) {
            Log::channel('migration_legal_crm')->warning('Migration CRM token rejected', [
                'path' => $request->path(),
                'ip' => $request->ip(),
                'has_bearer' => $bearer !== '',
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 401);
        }

        Log::channel('migration_legal_crm')->info('Migration CRM token accepted', [
            'path' => $request->path(),
            'ip' => $request->ip(),
        ]);

        return $next($request);
    }
}
