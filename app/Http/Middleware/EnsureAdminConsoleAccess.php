<?php

namespace App\Http\Middleware;

use App\Models\Staff;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminConsoleAccess
{
    /**
     * Admin Console: roles in config('crm.admin_console_role_ids') (default 1, 12, 17)
     * plus staff with effective super-admin elevation.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('admin')->user();

        if (!$user) {
            return redirect()->route('crm.login');
        }

        $staff = $user instanceof Staff ? $user : null;
        if (! $staff || ! $staff->canAccessAdminConsole()) {
            return redirect()->route('dashboard')->with('error', 'Unauthorized: You do not have permission to access Admin Console.');
        }

        return $next($request);
    }
}
