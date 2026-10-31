<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('staff')) {
            return;
        }

        if (! Schema::hasColumn('staff', 'can_access_admin_console')) {
            Schema::table('staff', function (Blueprint $table) {
                $table->boolean('can_access_admin_console')->default(false);
            });
        }

        // Keep current Admin Console users (Super Admin, Person Responsible, Admin).
        DB::table('staff')
            ->whereIn('role', [1, 12, 17])
            ->update(['can_access_admin_console' => true]);
    }

    public function down(): void
    {
        if (Schema::hasTable('staff') && Schema::hasColumn('staff', 'can_access_admin_console')) {
            Schema::table('staff', function (Blueprint $table) {
                $table->dropColumn('can_access_admin_console');
            });
        }
    }
};
