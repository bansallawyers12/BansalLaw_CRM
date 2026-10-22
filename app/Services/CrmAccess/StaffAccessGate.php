<?php

namespace App\Services\CrmAccess;

use App\Models\Staff;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * CRM access helpers shared by nav, dashboard, and allocation bypass checks.
 * (Legacy per-staff grant / Staff Access UI has been removed.)
 */
class StaffAccessGate
{
    public function isFeatureEnabled(): bool
    {
        return false;
    }

    public function isRestricted(Staff|Authenticatable|null $staff): bool
    {
        return false;
    }

    public function hasFullCrmAccess(Staff|Authenticatable|null $staff): bool
    {
        if (! $staff instanceof Staff) {
            return false;
        }

        $roleIds = config('crm_access.exempt_role_ids', [1, 17]);
        $roleIds = is_array($roleIds) ? array_map('intval', $roleIds) : [1, 17];

        if (in_array((int) ($staff->role ?? 0), $roleIds, true)) {
            return true;
        }

        if (method_exists($staff, 'hasEffectiveSuperAdminPrivileges')
            && $staff->hasEffectiveSuperAdminPrivileges()
        ) {
            return true;
        }

        if (method_exists($staff, 'hasFullCrmAllocationAccess') && $staff->hasFullCrmAllocationAccess()) {
            return true;
        }

        return false;
    }

    /**
     * @return array{
     *   restricted: bool,
     *   dashboard: bool,
     *   profile: bool,
     *   clients: bool,
     *   leads: bool,
     *   accounts: bool,
     *   booking: bool,
     *   signatures: bool,
     *   office_visits: bool,
     *   front_desk: bool,
     *   tasks: bool,
     *   unassigned_mail: bool,
     *   calendar: bool,
     *   search: bool,
     *   office_checkin: bool,
     *   admin_console: bool,
     *   notifications: bool,
     *   closed_matters: bool
     * }
     */
    public function navFlags(Staff|Authenticatable|null $staff): array
    {
        return [
            'restricted' => false,
            'dashboard' => true,
            'profile' => true,
            'clients' => true,
            'leads' => true,
            'accounts' => true,
            'booking' => true,
            'signatures' => true,
            'office_visits' => true,
            'front_desk' => true,
            'tasks' => true,
            'unassigned_mail' => true,
            'calendar' => true,
            'search' => true,
            'office_checkin' => true,
            'admin_console' => true,
            'notifications' => true,
            'closed_matters' => true,
        ];
    }

    /**
     * @return array{
     *   restricted: bool,
     *   shell_only: bool,
     *   active_matters: bool,
     *   closed_matters: bool,
     *   tasks: bool,
     *   recent_activity: bool,
     *   calendar: bool,
     *   access_approvals: bool,
     *   new_client: bool
     * }
     */
    public function dashboardWidgetFlags(Staff|Authenticatable|null $staff): array
    {
        return [
            'restricted' => false,
            'shell_only' => false,
            'active_matters' => true,
            'closed_matters' => true,
            'tasks' => true,
            'recent_activity' => true,
            'calendar' => true,
            'access_approvals' => true,
            'new_client' => true,
        ];
    }

    public function canDashboardWidget(Staff|Authenticatable|null $staff, string $widgetKey): bool
    {
        return true;
    }

    public function canCreateLeads(Staff|Authenticatable|null $staff): bool
    {
        return $staff instanceof Staff;
    }

    public function canWriteNotes(Staff|Authenticatable|null $staff, int $adminId, ?int $matterId = null): bool
    {
        return $staff instanceof Staff && $adminId > 0;
    }

    public function canEmails(Staff|Authenticatable|null $staff, ?int $adminId = null, ?int $matterId = null): bool
    {
        return $staff instanceof Staff;
    }

    public function canAccessRoute(Staff $staff, ?string $routeName): bool
    {
        return true;
    }

    public function applyGrantedEmailLogFilter($query, Staff $staff): void
    {
        // No-op: grant-based email filtering removed with Staff Access.
    }
}
