<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Admin;
use App\Models\ClientMatter;
use App\Support\MatterScopedAccess;
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
            if (request()->expectsJson() || request()->ajax()) {
                throw new HttpResponseException(
                    response()->json(StaffClientVisibility::unauthorizedPayload(), 403)
                );
            }
            abort(403, 'Unauthorized access: Invalid client record ID');
        }

        $adminRow = Admin::query()->where('id', $adminId)->first(['id', 'type']);
        if (!$adminRow) {
            if (request()->expectsJson() || request()->ajax()) {
                throw new HttpResponseException(
                    response()->json(StaffClientVisibility::unauthorizedPayload(), 403)
                );
            }
            abort(403, 'Unauthorized access: Client record not found');
        }

        if (!in_array($adminRow->type, ['client', 'lead'], true)) {
            if (request()->expectsJson() || request()->ajax()) {
                throw new HttpResponseException(
                    response()->json(StaffClientVisibility::unauthorizedPayload(), 403)
                );
            }
            abort(403, 'Unauthorized access to non-client record');
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
     * Client-file access is not enough. The viewer must be on this matter
     * (or be Super Admin, Calling Team, Kabir, Ajay, or Khusi).
     */
    protected function ensureClientMatterAccess(ClientMatter $matter): void
    {
        $this->ensureCrmRecordAccess((int) $matter->client_id);

        if (! MatterScopedAccess::isEnabled()) {
            return;
        }

        $user = Auth::guard('admin')->user();
        if (MatterScopedAccess::canAccessClientMatter((int) $matter->id, $user)) {
            return;
        }

        if (request()->expectsJson() || request()->ajax()) {
            throw new HttpResponseException(
                response()->json(StaffClientVisibility::unauthorizedPayload(), 403)
            );
        }

        abort(403, 'You do not have access to this matter.');
    }

    /**
     * Abort 403 if $adminId is a client/lead row and the current user cannot access it.
     * Unlike ensureCrmRecordAccess, throws even if the row doesn't exist.
     */
    protected function ensureCrmRecordAccessStrict(int $adminId): void
    {
        if ($adminId <= 0) {
            if (request()->expectsJson() || request()->ajax()) {
                throw new HttpResponseException(
                    response()->json(StaffClientVisibility::unauthorizedPayload(), 403)
                );
            }
            abort(404);
        }

        $row = Admin::query()
            ->where('id', $adminId)
            ->whereIn('type', ['client', 'lead'])
            ->first(['id', 'type']);

        if (! $row) {
            if (request()->expectsJson() || request()->ajax()) {
                throw new HttpResponseException(
                    response()->json(StaffClientVisibility::unauthorizedPayload(), 403)
                );
            }
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

    /**
     * Abort 403 unless the current staff may access emails for this client/lead
     * (and optional matter). Restricted users need an active record grant.
     */
    protected function ensureEmailAccess(int $adminId, ?int $matterId = null): void
    {
        $this->ensureCrmRecordAccess($adminId);

        $user = Auth::guard('admin')->user();
        if (StaffClientVisibility::canAccessEmails($adminId, $user, $matterId)) {
            return;
        }

        $payload = array_merge(StaffClientVisibility::unauthorizedPayload(), [
            'message' => 'You do not have email access for this record. Ask a Super Admin to grant client, lead, or matter access.',
        ]);

        if (request()->expectsJson() || request()->ajax()) {
            throw new HttpResponseException(response()->json($payload, 403));
        }

        abort(403, $payload['message']);
    }
}
