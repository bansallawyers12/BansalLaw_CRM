<?php

namespace App\Http\Middleware;

use App\Models\Staff;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sanctum bearer tokens authenticate Staff, but CRM visibility checks prefer
 * Auth::guard('admin'). Mirror the Sanctum user onto that guard for the request.
 */
class SetAdminGuardFromSanctumUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user()
            ?? Auth::user()
            ?? Auth::guard('admin')->user();

        if ($user instanceof Staff) {
            Auth::guard('admin')->setUser($user);
            Auth::shouldUse('admin');
            $request->setUserResolver(static fn (?string $guard = null) => $user);
        }

        return $next($request);
    }
}
