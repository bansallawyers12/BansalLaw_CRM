<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Leads/clients store primary phone on admins; some schemas (e.g. early PostgreSQL)
 * were missing these columns while code and Admin::$fillable expect them.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('admins')) {
            return;
        }

        if (! Schema::hasColumn('admins', 'country_code')) {
            Schema::table('admins', function (Blueprint $table) {
                $table->string('country_code', 32)->nullable();
            });
        }

        if (! Schema::hasColumn('admins', 'phone')) {
            Schema::table('admins', function (Blueprint $table) {
                $table->string('phone', 100)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('admins')) {
            return;
        }

        if (Schema::hasColumn('admins', 'phone')) {
            Schema::table('admins', function (Blueprint $table) {
                $table->dropColumn('phone');
            });
        }
        if (Schema::hasColumn('admins', 'country_code')) {
            Schema::table('admins', function (Blueprint $table) {
                $table->dropColumn('country_code');
            });
        }
    }
};
