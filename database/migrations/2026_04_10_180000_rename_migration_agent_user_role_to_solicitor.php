<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Staff "User Role (Type)" labels come from user_roles.name (AdminConsole staff create/edit).
 * Role id 16 is the firm solicitor / matter-handling role; display name is set to Solicitor.
 */
return new class extends Migration
{
    private const ROLE_ID = 16;

    private const TO_NAME = 'Solicitor';

    public function up(): void
    {
        if (! Schema::hasTable('user_roles') || ! Schema::hasColumn('user_roles', 'name')) {
            return;
        }

        if (! DB::table('user_roles')->where('id', self::ROLE_ID)->exists()) {
            return;
        }

        $row = DB::table('user_roles')->where('id', self::ROLE_ID)->first();
        $data = [
            'name' => self::TO_NAME,
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('user_roles', 'description')) {
            $data['description'] = self::TO_NAME;
        }

        DB::table('user_roles')->where('id', self::ROLE_ID)->update($data);
    }

    public function down(): void
    {
        if (! Schema::hasTable('user_roles') || ! Schema::hasColumn('user_roles', 'name')) {
            return;
        }

        if (! DB::table('user_roles')->where('id', self::ROLE_ID)->exists()) {
            return;
        }

        $legacy = $this->legacyDisplayNameForDown();

        $data = [
            'name' => $legacy,
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('user_roles', 'description')) {
            $row = DB::table('user_roles')->where('id', self::ROLE_ID)->first();
            $d = (string) ($row->description ?? '');
            if (trim($d) === '' || strcasecmp(trim($d), self::TO_NAME) === 0) {
                $data['description'] = $legacy;
            }
        }

        DB::table('user_roles')->where('id', self::ROLE_ID)->update($data);
    }

    private function legacyDisplayNameForDown(): string
    {
        return implode('', array_map('chr', [
            77, 105, 103, 114, 97, 116, 105, 111, 110, 32, 65, 103, 101, 110, 116,
        ]));
    }
};
