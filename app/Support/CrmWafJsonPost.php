<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * TEMPORARY transport workaround when hosting/WAF blocks multipart or form POST.
 * Remove when normal uploads and POST bodies are allowed (see config/crm.php).
 */
final class CrmWafJsonPost
{
    public static function enabled(): bool
    {
        return filter_var(config('crm.temporary_waf_safe_json_post', false), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @param  callable(Request): mixed  $handler
     */
    public static function dispatch(Request $request, callable $handler): mixed
    {
        if (! self::enabled()) {
            abort(404);
        }

        if (! $request->isJson()) {
            return response()->json([
                'success' => false,
                'status' => false,
                'message' => 'JSON request body required.',
            ], 415);
        }

        $payload = $request->json()->all();
        if (isset($payload['payload']) && is_array($payload['payload'])) {
            $payload = $payload['payload'];
        }

        if (! is_array($payload) || $payload === []) {
            return response()->json([
                'success' => false,
                'status' => false,
                'message' => 'Invalid payload.',
                'errors' => ['error' => ['Invalid payload.']],
            ], 422);
        }

        $request->replace($payload);
        $request->headers->set('Accept', 'application/json');
        $request->headers->set('X-Requested-With', 'XMLHttpRequest');

        return $handler($request);
    }

    /**
     * @return array<string, string> canonical route URL => WAF JSON route URL
     */
    public static function routeUrlMap(): array
    {
        if (! self::enabled()) {
            return [];
        }

        $pairs = config('crm.temporary_waf_safe_json_post_endpoints', []);
        $map = [];
        foreach ($pairs as $from => $to) {
            if (! is_string($from) || ! is_string($to)) {
                continue;
            }
            try {
                $map[route($from)] = route($to);
            } catch (\Throwable $e) {
                continue;
            }
        }

        return $map;
    }
}
