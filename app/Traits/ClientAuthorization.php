<?php

namespace App\Traits;

use Auth;
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

