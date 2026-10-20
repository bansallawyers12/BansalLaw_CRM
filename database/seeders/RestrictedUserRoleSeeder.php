<?php

namespace Database\Seeders;

use App\Services\CrmAccess\StaffRestrictedAccessService;
use Illuminate\Database\Seeder;

/**
 * Step 2 — seed Restricted User role (empty module_access).
 * Local-only workflow: php artisan db:seed --class=RestrictedUserRoleSeeder
 */
class RestrictedUserRoleSeeder extends Seeder
{
    public function run(): void
    {
        $service = app(StaffRestrictedAccessService::class);
        $role = $service->ensureRestrictedRole();

        $this->command?->info("Restricted User role ready: id={$role->id}, name=\"{$role->name}\"");
        $this->command?->line('module_access=' . $role->module_access);
        $this->command?->warn('Set CRM_RESTRICTED_ROLE_ID=' . $role->id . ' in local .env (optional; name lookup also works).');
    }
}
