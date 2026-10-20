<?php

namespace App\Console\Commands;

use App\Models\Staff;
use App\Services\CrmAccess\StaffRestrictedAccessService;
use Illuminate\Console\Command;

class EnsureRestrictedUserRole extends Command
{
    protected $signature = 'crm:ensure-restricted-role
                            {--assign-staff= : Optional staff id to set role + restricted profile}
                            {--dry-run : Show actions without saving staff assignment}';

    protected $description = 'Ensure Restricted User role exists (Step 2) and optionally assign it to a staff member locally';

    public function handle(StaffRestrictedAccessService $service): int
    {
        $role = $service->ensureRestrictedRole();
        $this->info("Role OK: #{$role->id} {$role->name}");
        $this->line('module_access: ' . $role->module_access);
        $this->line('Feature flag: ' . ($service->isFeatureEnabled() ? 'ENABLED' : 'disabled'));
        $this->line('Full access roles (Super Admin only): ' . json_encode(config('crm_access.restricted_access.full_access_role_ids')));

        $staffId = (int) $this->option('assign-staff');
        if ($staffId <= 0) {
            $this->comment('Tip: php artisan crm:ensure-restricted-role --assign-staff=STAFF_ID');

            return self::SUCCESS;
        }

        $staff = Staff::query()->find($staffId);
        if (! $staff) {
            $this->error("Staff #{$staffId} not found.");

            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            $this->warn("Dry-run: would set staff #{$staffId} role={$role->id} and ensure restricted profile.");

            return self::SUCCESS;
        }

        $staff->role = $role->id;
        $staff->save();
        $profile = $service->ensureRestrictedProfile($staff);

        $this->info("Assigned staff #{$staffId} ({$staff->email}) → role #{$role->id}");
        $this->info("Profile access_mode={$profile->access_mode}, widgets=" . json_encode($profile->dashboard_widgets));

        return self::SUCCESS;
    }
}
