<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Services\CrmAccess\CrmAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SuperAdminElevationController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'elevated' => ['required', 'boolean'],
        ]);

        $user = Auth::guard('admin')->user();
        if (! $user instanceof Staff) {
            abort(403);
        }

        $user->refresh();

        $svc = app(CrmAccessService::class);
        if (! $svc->canToggleSuperAdminElevation($user)) {
            abort(403);
        }

        if ($request->boolean('elevated')) {
            $request->session()->put(CrmAccessService::SESSION_SUPER_ADMIN_ELEVATED, true);
        } else {
            $request->session()->forget(CrmAccessService::SESSION_SUPER_ADMIN_ELEVATED);
        }

        return redirect()->back();
    }
}
