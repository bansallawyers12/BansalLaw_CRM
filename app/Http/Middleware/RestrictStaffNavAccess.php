<?php

namespace App\Http\Middleware;

use App\Models\Staff;
use App\Services\CrmAccess\StaffAccessGate;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Step 4 — Block deep links to modules a restricted staff member cannot use.
 * Allows dashboard shell + own profile always; clients/leads only when grants exist.
 */
class RestrictStaffNavAccess
{
    public function __construct(
        protected StaffAccessGate $gate
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->gate->isFeatureEnabled()) {
            return $next($request);
        }

        $user = Auth::guard('admin')->user();
        if (! $user instanceof Staff) {
            return $next($request);
        }

        if ($this->gate->hasFullCrmAccess($user) || ! $this->gate->isRestricted($user)) {
            return $next($request);
        }

        $routeName = optional($request->route())->getName();
        if ($this->gate->canAccessRoute($user, $routeName)) {
            return $next($request);
        }

        if ($request->expectsJson() || $request->ajax() || str_contains(strtolower((string) $request->header('Accept', '')), 'application/json')) {
            return response()->json([
                'success' => false,
                'status' => false,
                'message' => 'You do not have access to this area. Contact Super Admin for access.',
                'error_type' => 'forbidden',
            ], 403);
        }

        return redirect()
            ->route('dashboard')
            ->with('error', 'You do not have access to that area. Contact Super Admin if you need access.');
    }
}
