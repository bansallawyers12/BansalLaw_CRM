<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Legacy user_roles rows (stub table: id + timestamps) kept NULL name after
 * 2026_04_10_175000 added columns. Staff create/edit shows {{ $ut->name }} → blank options.
 * Canonical seed (2026_04_11_180000) inserts only missing ids; it does not update existing stubs.
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

        foreach (config('crm_roles.defaults', []) as $id => $meta) {
            $id = (int) $id;
            $name = trim((string) ($meta['name'] ?? ''));
            if ($id < 1 || $name === '') {
                continue;
            }
            $description = trim((string) ($meta['description'] ?? $name));
            if (! DB::table('user_roles')->where('id', $id)->exists()) {
                continue;
            }
            $current = DB::table('user_roles')->where('id', $id)->value('name');
            if (trim((string) ($current ?? '')) !== '') {
                continue;
            }
            $upd = ['name' => $name, 'updated_at' => $now];
            if (isset($columns['description'])) {
                $upd['description'] = $description;
            }
            DB::table('user_roles')->where('id', $id)->update($upd);
        }

        $orphanIds = DB::table('user_roles')
            ->whereRaw("TRIM(COALESCE(name, '')) = ''")
            ->orderBy('id')
            ->pluck('id');

        foreach ($orphanIds as $rid) {
            $label = 'Role #'.$rid;
            $upd = ['name' => $label, 'updated_at' => $now];
            if (isset($columns['description'])) {
                $desc = DB::table('user_roles')->where('id', $rid)->value('description');
                if (trim((string) ($desc ?? '')) === '') {
                    $upd['description'] = $label;
                }
            }
            DB::table('user_roles')->where('id', $rid)->update($upd);
        }
    }

    public function down(): void
    {
        // Not reversible without storing previous labels.
    }
};
