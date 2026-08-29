<?php

namespace App\Http\Middleware;

use App\Models\Staff;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks Communication Check unless the feature flag is on and the signed-in
 * staff is Super Admin or has been granted access.
 */
class EnsureCommunicationCheckEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! (bool) config('crm.communication_check.enabled', false)) {
            abort(404);
        }

        $user = Auth::guard('admin')->user() ?? Auth::user();
        if (! $user instanceof Staff || ! $user->canUseCommunicationCheck()) {
            abort(403, 'You do not have permission to use Communication Check.');
        }

        return $next($request);
    }
}
