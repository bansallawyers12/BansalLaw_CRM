<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    /**
     * Dummy Legal Practitioner for dropdowns (add matter, cost assignment, etc.).
     * Login: shubam.bansal@dummy.bansallaw.crm / DummyLP2026!
     */
    private const EMAIL = 'shubam.bansal@dummy.bansallaw.crm';

    public function up(): void
    {
        if (! Schema::hasTable('staff') || ! Schema::hasTable('user_roles')) {
            return;
        }

        $now = now();

        if (! DB::table('user_roles')->where('id', 16)->exists()) {
            $roleRow = ['id' => 16, 'created_at' => $now, 'updated_at' => $now];
            foreach (['name' => 'Legal Practitioner', 'description' => 'Legal Practitioner', 'module_access' => null] as $col => $val) {
                if (Schema::hasColumn('user_roles', $col)) {
                    $roleRow[$col] = $val;
                }
            }
            DB::table('user_roles')->insert($roleRow);
        }

        $password = Hash::make('DummyLP2026!');

        $staffRow = [
            'first_name' => 'Shubam',
            'last_name' => 'Bansal',
            'password' => $password,
            'role' => 16,
            'status' => 1,
            'is_migration_agent' => 1,
            'office_id' => null,
            'updated_at' => $now,
            'created_at' => $now,
        ];
        if (Schema::hasColumn('staff', 'verified')) {
            $staffRow['verified'] = 0;
        }

        $staffColumns = array_flip(Schema::getColumnListing('staff'));
        $staffRow = array_intersect_key($staffRow, $staffColumns);

        $existing = DB::table('staff')->where('email', self::EMAIL)->first();
        if ($existing) {
            $update = $staffRow;
            unset($update['created_at']);
            DB::table('staff')->where('id', $existing->id)->update(array_intersect_key($update, $staffColumns));
        } else {
            $insert = array_merge($staffRow, ['email' => self::EMAIL]);
            DB::table('staff')->insert(array_intersect_key($insert, $staffColumns));
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('staff')) {
            return;
        }

        DB::table('staff')->where('email', self::EMAIL)->delete();
    }
};
