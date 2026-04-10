<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Inserts missing user_roles rows from config/crm_roles.php (canonical ids/names).
 * Does not delete or renumber existing rows. Does not overwrite custom names except
 * legacy id=16 labels (Legal Practitioner / Migration Agent) → Solicitor to match product.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('user_roles')) {
            return;
        }

        if (! Schema::hasColumn('user_roles', 'name')) {
            return;
        }

        $defaults = config('crm_roles.defaults', []);
        if (! is_array($defaults) || $defaults === []) {
            return;
        }

        $now = now();
        $columns = array_flip(Schema::getColumnListing('user_roles'));

        foreach ($defaults as $id => $meta) {
            $id = (int) $id;
            if ($id < 1 || ! is_array($meta)) {
                continue;
            }

            $name = (string) ($meta['name'] ?? '');
            $description = (string) ($meta['description'] ?? $name);
            if ($name === '') {
                continue;
            }

            $exists = DB::table('user_roles')->where('id', $id)->exists();

            if (! $exists) {
                $row = [
                    'id' => $id,
                    'name' => $name,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                if (isset($columns['description'])) {
                    $row['description'] = $description;
                }
                if (isset($columns['module_access'])) {
                    $row['module_access'] = null;
                }
                $row = array_intersect_key($row, $columns);
                DB::table('user_roles')->insert($row);

                continue;
            }

            // Legacy dummy / old labels for role 16 (see 2026_04_08_230000, rename migration)
            if ($id === 16 && isset($columns['name'])) {
                $current = DB::table('user_roles')->where('id', 16)->value('name');
                $legacy = ['Legal Practitioner', 'Migration Agent'];
                if ($current !== null && in_array(trim((string) $current), $legacy, true)) {
                    $upd = ['name' => $name, 'updated_at' => $now];
                    if (isset($columns['description'])) {
                        $upd['description'] = $description;
                    }
                    DB::table('user_roles')->where('id', 16)->update($upd);
                }
            }
        }

        $this->syncIdentityAfterExplicitIds();
    }

    public function down(): void
    {
        // Do not remove roles — staff.role references user_roles.id.
    }

    protected function syncIdentityAfterExplicitIds(): void
    {
        if (! Schema::hasTable('user_roles')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();
        $max = (int) (DB::table('user_roles')->max('id') ?? 0);
        if ($max < 1) {
            return;
        }

        if ($driver === 'pgsql') {
            $schema = Schema::getConnection()->getConfig('schema') ?? 'public';
            $row = DB::selectOne(
                'SELECT pg_get_serial_sequence(?, ?) AS seq',
                [$schema.'.user_roles', 'id']
            );
            if ($row && ! empty($row->seq)) {
                DB::statement('SELECT setval(?, ?::bigint, true)', [$row->seq, $max]);
            }

            return;
        }

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE user_roles AUTO_INCREMENT = '.($max + 1));
        }
    }
};
