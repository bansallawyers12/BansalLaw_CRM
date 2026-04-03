<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Admin;
use App\Support\StaffClientVisibility;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

trait EnsuresCrmRecordAccess
{
    /**
     * Abort with 403 unless the current staff may access this client/lead row (admins.id).
     *
     * Rows where no matching admins.id exists with type = client|lead are silently allowed
     * (e.g. ad-hoc documents associated to a staff user_id, not a client). Callers that
     * explicitly want a 404 on missing records should check existence themselves first.
     */
    protected function ensureCrmRecordAccess(int $adminId): void
    {
        if ($adminId <= 0) {
            return;
        }

        $row = Admin::query()
            ->where('id', $adminId)
            ->whereIn('type', ['client', 'lead'])
            ->first(['id', 'type']);

        // ID does not correspond to a client/lead — skip gate (not our concern)
        if (! $row) {
            return;
        }

        $user = Auth::guard('admin')->user();
        if (StaffClientVisibility::canAccessClientOrLead($adminId, $user)) {
            return;
        }

        if (request()->expectsJson() || request()->ajax()) {
            throw new HttpResponseException(
                response()->json(StaffClientVisibility::unauthorizedPayload(), 403)
            );
        }

        abort(403, 'Unauthorized');
    }

    /**
     * Abort 403 if $adminId is a client/lead row and the current user cannot access it.
     * Unlike ensureCrmRecordAccess, throws even if the row doesn't exist.
     */
    protected function ensureCrmRecordAccessStrict(int $adminId): void
    {
        if ($adminId <= 0) {
            abort(404);
        }

        $row = Admin::query()
            ->where('id', $adminId)
            ->whereIn('type', ['client', 'lead'])
            ->first(['id', 'type']);

        if (! $row) {
            abort(404);
        }

        $user = Auth::guard('admin')->user();
        if (StaffClientVisibility::canAccessClientOrLead($adminId, $user)) {
            return;
        }

        if (request()->expectsJson() || request()->ajax()) {
            throw new HttpResponseException(
                response()->json(StaffClientVisibility::unauthorizedPayload(), 403)
            );
        }

        abort(403, 'Unauthorized');
    }

    /**
     * Run access check for the first present request key (client_id, admin_id, lead_id).
     */
    protected function ensureCrmRecordAccessFromRequest(Request $request, array $keys = ['client_id', 'admin_id', 'lead_id']): void
    {
        foreach ($keys as $key) {
            if (! $request->filled($key)) {
                continue;
            }
            $v = (int) $request->input($key);
            if ($v > 0) {
                $this->ensureCrmRecordAccess($v);

                return;
            }
        }
    }

    /**
     * Gate email logs and similar rows: only check when a CRM client is linked (admins.id).
     */
    protected function ensureCrmRecordAccessForOptionalClientId(?int $clientAdminId): void
    {
        if ($clientAdminId !== null && $clientAdminId > 0) {
            $this->ensureCrmRecordAccess($clientAdminId);
        }
    }
}
