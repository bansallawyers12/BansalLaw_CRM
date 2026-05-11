<?php

namespace App\Traits;

use Auth;
use App\Models\Staff;
use App\Support\StaffClientVisibility;
use Illuminate\Support\Facades\Redirect;

trait ClientAuthorization
{
    /**
     * Check if user has access to client module (module 20)
     *
     * @return array
     */
    protected function checkClientModuleAccess()
    {
        $user = Auth::guard('admin')->user();
        if (! $user instanceof \App\Models\Staff) {
            return [];
        }
        $roles = \App\Models\UserRole::find($user->role);
        if (! $roles || $roles->module_access === null || $roles->module_access === '') {
            return [];
        }
        $newarray = json_decode($roles->module_access);
        $module_access = is_array($newarray) ? $newarray : (array) $newarray;

        return $module_access;
    }

    /**
     * Whether the user may open client/matter list routes (/clients, matters list, client email list).
     * Aligns with lead list: any of module keys 20–23, or the same role-only bypasses as leads
     * (full-access roles, extra role ids, assigned-only role ids). Row-level visibility is still
     * enforced separately by StaffClientVisibility.
     */
    protected function hasClientListModuleAccess(): bool
    {
        $user = Auth::guard('admin')->user();
        if (! $user instanceof Staff) {
            return false;
        }

        $roleId = (int) ($user->role ?? 0);

        if (in_array($roleId, StaffClientVisibility::leadFullAccessRoleIds(), true)) {
            return true;
        }

        $extraRoleIds = config('crm.lead_list_extra_role_ids', []);
        if ($extraRoleIds !== [] && in_array($roleId, $extraRoleIds, true)) {
            return true;
        }

        $assignedOnlyRoleIds = config('crm.lead_list_assigned_only_role_ids', [13, 14, 15, 16]);
        if ($assignedOnlyRoleIds !== [] && in_array($roleId, $assignedOnlyRoleIds, true)) {
            return true;
        }

        $keys = config('crm.client_list_module_access_keys', ['20', '21', '22', '23']);
        if ($keys === []) {
            $keys = ['20', '21', '22', '23'];
        }
        foreach ($keys as $key) {
            $k = trim((string) $key);
            if ($k !== '' && $user->hasCrmModule($k)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if user has access to a specific module
     *
     * @param string $moduleId
     * @return bool
     */
    protected function hasModuleAccess($moduleId = '20')
    {
        $user = Auth::guard('admin')->user();
        if ($user instanceof \App\Models\Staff) {
            return $user->hasCrmModule($moduleId);
        }

        return false;
    }

    /**
     * Get module access or return empty result
     *
     * @param string $moduleId
     * @return bool
     */
    protected function requireModuleAccess($moduleId = '20')
    {
        if (!$this->hasModuleAccess($moduleId)) {
            return false;
        }
        return true;
    }
}

