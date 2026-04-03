<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Hash;

/**
 * One-time / recovery: ensures user_roles id=1 exists and creates a super-admin staff row.
 * Set SUPERADMIN_BOOTSTRAP_EMAIL and SUPERADMIN_BOOTSTRAP_PASSWORD (e.g. in .env) then run:
 * php artisan db:seed --class=Database\\Seeders\\SuperAdminBootstrapSeeder
 */
class SuperAdminBootstrapSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('SUPERADMIN_BOOTSTRAP_EMAIL', 'admin1@gmail.com');
        $password = env('SUPERADMIN_BOOTSTRAP_PASSWORD');
        if ($password === null || $password === '') {
            $this->command?->error('Set SUPERADMIN_BOOTSTRAP_PASSWORD in the environment or .env before running this seeder.');

            return;
        }

        if (! Schema::hasTable('user_roles') || ! Schema::hasTable('staff')) {
            $this->command?->error('Required tables user_roles or staff are missing. Run migrations first.');

            return;
        }

        $now = now();
        $row = ['id' => 1, 'created_at' => $now, 'updated_at' => $now];
        foreach (['name' => 'Super Admin', 'description' => 'Super Admin', 'module_access' => null] as $col => $val) {
            if (Schema::hasColumn('user_roles', $col)) {
                $row[$col] = $val;
            }
        }
        DB::table('user_roles')->updateOrInsert(['id' => 1], $row);

        $staffRow = [
            'first_name' => 'Admin',
            'last_name' => 'One',
            'password' => Hash::make($password),
            'phone' => '0000000000',
            'role' => 1,
            'status' => 1,
            'office_id' => null,
            'updated_at' => $now,
            'created_at' => $now,
        ];
        if (Schema::hasColumn('staff', 'verified')) {
            $staffRow['verified'] = 1;
        }

        $staffColumns = array_flip(Schema::getColumnListing('staff'));
        $staffRow = array_intersect_key($staffRow, $staffColumns);

        $existing = DB::table('staff')->where('email', $email)->first();
        if ($existing) {
            $update = array_diff_key($staffRow, array_flip(['created_at']));
            DB::table('staff')->where('id', $existing->id)->update($update);
        } else {
            $insert = array_merge($staffRow, ['email' => $email]);
            $insert = array_intersect_key($insert, $staffColumns);
            DB::table('staff')->insert($insert);
        }

        $this->command?->info("Super-admin staff ready: {$email} (role=1). Remove SUPERADMIN_BOOTSTRAP_PASSWORD from .env after use.");
    }
}
