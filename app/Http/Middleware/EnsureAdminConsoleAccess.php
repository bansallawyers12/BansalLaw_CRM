<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminConsoleAccess
{
    /**
     * Admin Console is restricted to Admin and Super Admin roles only.
     * Role 1 = Super Admin, Role 12 = Admin (per AppointmentSyncServiceProvider)
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('admin')->user();

        if (!$user) {
            return redirect()->route('crm.login');
        }

        $allowedRoles = [1, 12]; // Super Admin, Admin
        if (!in_array((int) $user->role, $allowedRoles)) {
            return redirect()->route('dashboard')->with('error', 'Unauthorized: Only Admin and Super Admin can access Admin Console.');
        }

        return $next($request);
    }
}
