<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Removes the obsolete "Client" role (id=7) from user_roles.
     * Clients/leads are now identified by type ('client'|'lead') in admins, not by role.
     * Staff have been migrated to the staff table.
     */
    public function up(): void
    {
        DB::table('user_roles')->where('id', 7)->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('user_roles')->insert([
            'id' => 7,
            'name' => 'Client',
            'description' => 'Client',
            'module_access' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
