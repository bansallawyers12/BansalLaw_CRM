<?php

namespace App\Console\Commands;

use App\Models\Admin;
use App\Models\Staff;
use App\Models\StaffAccessAudit;
use App\Models\StaffAccessProfile;
use App\Models\StaffRecordGrant;
use App\Services\CrmAccess\StaffAccessGate;
use App\Services\CrmAccess\StaffRestrictedAccessService;
use App\Support\StaffClientVisibility;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * Local QA harness for restricted staff access (Steps 1–8).
 * Creates an ephemeral staff member, exercises the gate, then cleans up.
 */
class QaRestrictedAccess extends Command
{
    protected $signature = 'crm:qa-restricted-access';

    protected $description = 'Run local end-to-end checks for restricted staff access (creates/deletes a temporary staff row)';

    public function handle(StaffRestrictedAccessService $svc, StaffAccessGate $gate): int
    {
        $fail = 0;
        $pass = 0;
        $assert = function (bool $ok, string $msg) use (&$fail, &$pass) {
            if ($ok) {
                $this->info("[PASS] {$msg}");
                $pass++;
            } else {
                $this->error("[FAIL] {$msg}");
                $fail++;
            }
        };

        $this->line('DB=' . config('database.connections.' . config('database.default') . '.database'));

        $assert($svc->isFeatureEnabled(), 'Feature flag CRM_RESTRICTED_ACCESS_ENABLED is on');
        $role = $svc->ensureRestrictedRole();
        $assert((int) $role->id > 0, "Restricted role exists (#{$role->id} {$role->name})");
        $assert(
            ! in_array((int) $role->id, array_map('intval', (array) config('crm_access.restricted_access.full_access_role_ids', [1])), true),
            'Restricted role id is not a full-access role id'
        );

        $super = Staff::query()->where('role', 1)->orderBy('id')->first();
        $assert($super !== null, 'At least one Super Admin (role 1) staff exists');
        if ($super) {
            $assert($gate->hasFullCrmAccess($super), 'Super Admin hasFullCrmAccess');
            $assert(! $gate->isRestricted($super), 'Super Admin is not restricted');
        }

        $client = Admin::query()->where('type', 'client')->whereNull('is_deleted')->orderBy('id')->first(['id']);
        $assert($client !== null, 'At least one client exists for grant tests');

        $email = 'qa-restricted-' . time() . '@example.test';
        Staff::query()->where('email', $email)->delete();

        $staff = Staff::query()->create([
            'first_name' => 'QA',
            'last_name' => 'Restricted',
            'email' => $email,
            'password' => bcrypt('QaRestricted!' . time()),
            'status' => 1,
            'role' => (int) $role->id,
        ]);
        $svc->ensureRestrictedProfile($staff);
        $staff = $staff->fresh();

        try {
            $assert($gate->isRestricted($staff), 'Ephemeral staff isRestricted');
            $assert($gate->canViewDashboard($staff), 'Restricted can view dashboard shell');
            $assert(! empty($gate->dashboardWidgetFlags($staff)['shell_only']), 'Dashboard is shell_only by default');
            $assert($gate->canAccessRoute($staff, 'dashboard'), 'Dashboard route allowed');
            $assert(! $gate->canAccessRoute($staff, 'booking.appointments.index'), 'Booking route blocked');

            $adminId = (int) ($client->id ?? 0);
            if ($adminId > 0) {
                $assert(! $gate->canClient($staff, $adminId), 'No client access without grant');
                $grant = $svc->grantRecord(
                    $staff,
                    StaffRecordGrant::TYPE_CLIENT,
                    $adminId,
                    [StaffRecordGrant::SCOPE_VIEW],
                    $super,
                    'QA view-only'
                );
                $assert($gate->canClient($staff, $adminId), 'View grant allows canClient');
                $assert($gate->canEmails($staff, $adminId), 'View grant allows related emails');
                $assert(StaffClientVisibility::canAccessEmails($adminId, $staff), 'Visibility allows emails with grant');
                $overview = $svc->accessOverview($staff, $gate);
                $assert(($overview['clients'] ?? 0) === 1, 'Overview shows 1 client grant');
                $assert(! empty($overview['can_see_emails']), 'Overview shows email access');
                $assert(($overview['email_people'] ?? 0) >= 1, 'Overview email people count');
                $inventory = app(\App\Services\CrmAccess\StaffAccessInventoryService::class)->inventory($staff, 20);
                $assert(($inventory['counts']['restricted_grants'] ?? 0) >= 1, 'Inventory lists restricted grant');
                $assert(($inventory['counts']['clients'] ?? 0) >= 1, 'Inventory lists client from grant');
                $assert($gate->hasActiveGrants($staff), 'hasActiveGrants after grant');
                $assert($gate->canDashboardWidget($staff, 'tasks'), 'Tasks unlock after grant');
                $assert($gate->canDashboardWidget($staff, 'calendar'), 'Calendar unlocks after grant');
                $assert(! $gate->canDashboardWidget($staff, 'closed_matters'), 'Closed matters stay off without closed grant');
                $assert(! empty($gate->navFlags($staff)['tasks']), 'Nav tasks after grant');
                $assert($gate->canAccessRoute($staff, 'assignee.tasks'), 'Tasks route allowed after grant');
                $assert(! $gate->canAccessRoute($staff, 'clients.closedmatterslist'), 'Closed matters list blocked');
                $assert(! $gate->canAdminConsole($staff), 'No Admin Console before nav grant');
                $assert(! $gate->canAccessRoute($staff, 'adminconsole.features.matter.index'), 'Matter List blocked before grant');

                $svc->updateAccessProfile(
                    $staff,
                    StaffAccessProfile::MODE_RESTRICTED,
                    [],
                    true,
                    true,
                    $super,
                    [
                        'booking' => ['view'],
                        'admin_console' => ['view'],
                        'clients' => ['view', 'edit'],
                        'leads' => ['view'],
                    ],
                    ['matter_list', 'workflows']
                );
                $assert($gate->canAdminConsole($staff), 'Admin Console after profile grant');
                $assert(! empty($gate->navFlags($staff)['booking']), 'Booking nav after profile grant');
                $assert($gate->canAdminConsoleModule($staff, 'matter_list'), 'Matter List module granted');
                $assert($gate->canAccessRoute($staff, 'adminconsole.features.matter.index'), 'Matter List route allowed');
                $assert($gate->canAccessRoute($staff, 'booking.appointments.calendar'), 'Booking route allowed');
                $assert($gate->canNavAction($staff, 'clients', 'edit'), 'Clients edit action granted');
                $assert($gate->canAccessRoute($staff, 'clients.edit'), 'Clients edit route allowed');
                $assert(! $gate->canNavAction($staff, 'clients', 'delete'), 'Clients delete still denied');
                $assert(! $gate->canAccessRoute($staff, 'clients.store'), 'Clients add still denied');
                $assert(! $gate->canNavAction($staff, 'leads', 'edit'), 'Leads edit still denied');
                $assert(! $gate->canAdminConsoleModule($staff, 'roles'), 'Roles module still denied');
                $assert(! $gate->canAccessRoute($staff, 'adminconsole.system.staff-access.index'), 'Staff Access stays blocked');

                $svc->revokeGrant($grant->fresh(), $super, 'QA revoke');
                $assert(! $gate->canClient($staff, $adminId), 'After revoke, client access denied');
                $assert(! $gate->canEmails($staff, $adminId), 'After revoke, emails denied');

                if (Schema::hasTable('staff_access_audit')) {
                    $auditCount = StaffAccessAudit::query()->where('target_staff_id', $staff->id)->count();
                    $assert($auditCount >= 2, "Audit rows written (count={$auditCount})");
                }
            }
        } finally {
            if (Schema::hasTable('staff_access_audit')) {
                StaffAccessAudit::query()
                    ->where(function ($q) use ($staff) {
                        $q->where('target_staff_id', $staff->id)->orWhere('actor_staff_id', $staff->id);
                    })
                    ->delete();
            }
            StaffRecordGrant::query()->where('staff_id', $staff->id)->delete();
            StaffAccessProfile::query()->where('staff_id', $staff->id)->delete();
            Staff::query()->where('id', $staff->id)->delete();
        }

        $this->newLine();
        $this->line("RESULT: {$pass} passed, {$fail} failed");

        return $fail > 0 ? self::FAILURE : self::SUCCESS;
    }
}
