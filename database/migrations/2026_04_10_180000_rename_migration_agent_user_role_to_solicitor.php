<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Staff "User Role (Type)" labels come from user_roles.name (AdminConsole staff create/edit).
 * Rename the legacy "Migration Agent" role to "Solicitor" for display; role id is unchanged.
 */
return new class extends Migration
{
    private const FROM_NAME = 'Migration Agent';

    private const TO_NAME = 'Solicitor';

    public function up(): void
    {
        if (! Schema::hasTable('user_roles') || ! Schema::hasColumn('user_roles', 'name')) {
            return;
        }

        $rows = DB::table('user_roles')
            ->whereRaw('LOWER(TRIM(name)) = ?', [strtolower(self::FROM_NAME)])
            ->get();

        foreach ($rows as $row) {
            $data = [
                'name' => self::TO_NAME,
                'updated_at' => now(),
            ];

            if (Schema::hasColumn('user_roles', 'description')) {
                $d = (string) ($row->description ?? '');
                if (trim($d) === '' || strcasecmp(trim($d), self::FROM_NAME) === 0) {
                    $data['description'] = self::TO_NAME;
                }
            }

            DB::table('user_roles')->where('id', $row->id)->update($data);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('user_roles') || ! Schema::hasColumn('user_roles', 'name')) {
            return;
        }

        $rows = DB::table('user_roles')
            ->whereRaw('LOWER(TRIM(name)) = ?', [strtolower(self::TO_NAME)])
            ->get();

        foreach ($rows as $row) {
            $data = [
                'name' => self::FROM_NAME,
                'updated_at' => now(),
            ];

            if (Schema::hasColumn('user_roles', 'description')) {
                $d = (string) ($row->description ?? '');
                if (trim($d) === '' || strcasecmp(trim($d), self::TO_NAME) === 0) {
                    $data['description'] = self::FROM_NAME;
                }
            }

            DB::table('user_roles')->where('id', $row->id)->update($data);
        }
    }
};
