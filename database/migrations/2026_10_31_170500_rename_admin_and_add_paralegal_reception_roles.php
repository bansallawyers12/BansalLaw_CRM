<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds Paralegal (Australia), Paralegal (India), and Reception.
 * Keeps Admin (17) as Admin. Does not delete Person Responsible (12)
 * or Person Assisting (13); those stay for staff already on them.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('user_roles') || ! Schema::hasColumn('user_roles', 'name')) {
            return;
        }

        $now = now();
        $columns = array_flip(Schema::getColumnListing('user_roles'));

        $currentAdmin = DB::table('user_roles')->where('id', 17)->value('name');
        if ($currentAdmin !== null && trim((string) $currentAdmin) === 'Administrator') {
            $upd = ['name' => 'Admin', 'updated_at' => $now];
            if (isset($columns['description'])) {
                $upd['description'] = 'Admin';
            }
            DB::table('user_roles')->where('id', 17)->update($upd);
        }

        foreach (config('crm_roles.defaults', []) as $id => $meta) {
            $id = (int) $id;
            $name = trim((string) ($meta['name'] ?? ''));
            if ($id < 1 || $name === '' || DB::table('user_roles')->where('id', $id)->exists()) {
                continue;
            }

            $row = [
                'id' => $id,
                'name' => $name,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            if (isset($columns['description'])) {
                $row['description'] = trim((string) ($meta['description'] ?? $name));
            }
            if (isset($columns['module_access'])) {
                $row['module_access'] = null;
            }
            DB::table('user_roles')->insert(array_intersect_key($row, $columns));
        }

        $driver = Schema::getConnection()->getDriverName();
        $max = (int) (DB::table('user_roles')->max('id') ?? 0);
        if ($max > 0 && $driver === 'mysql') {
            DB::statement('ALTER TABLE user_roles AUTO_INCREMENT = '.($max + 1));
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('user_roles')) {
            return;
        }

        DB::table('user_roles')->whereIn('id', [18, 19, 20])->whereNotExists(function ($query) {
            $query->select(DB::raw(1))
                ->from('staff')
                ->whereColumn('staff.role', 'user_roles.id');
        })->delete();
    }
};
