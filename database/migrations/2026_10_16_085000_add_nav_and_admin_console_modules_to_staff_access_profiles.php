<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Restricted profiles: Super Admin can grant top-menu and Admin Console modules.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('staff_access_profiles')) {
            return;
        }

        Schema::table('staff_access_profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('staff_access_profiles', 'nav_modules')) {
                $table->json('nav_modules')->nullable()->after('dashboard_widgets');
            }
            if (! Schema::hasColumn('staff_access_profiles', 'admin_console_modules')) {
                $table->json('admin_console_modules')->nullable()->after('nav_modules');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('staff_access_profiles')) {
            return;
        }

        Schema::table('staff_access_profiles', function (Blueprint $table) {
            if (Schema::hasColumn('staff_access_profiles', 'admin_console_modules')) {
                $table->dropColumn('admin_console_modules');
            }
            if (Schema::hasColumn('staff_access_profiles', 'nav_modules')) {
                $table->dropColumn('nav_modules');
            }
        });
    }
};
