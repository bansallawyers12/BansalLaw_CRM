<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lead model and many queries filter with whereNull('is_deleted').
     * Column is a nullable timestamp (soft-delete marker); use timestamps in code, not integer flags.
     * Fresh installs also get this from 0001_01_01_000000_create_admins_table when that migration creates admins.
     */
    public function up(): void
    {
        if (! Schema::hasTable('admins') || Schema::hasColumn('admins', 'is_deleted')) {
            return;
        }

        Schema::table('admins', function (Blueprint $table) {
            $table->timestamp('is_deleted')->nullable();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('admins') || ! Schema::hasColumn('admins', 'is_deleted')) {
            return;
        }

        Schema::table('admins', function (Blueprint $table) {
            $table->dropColumn('is_deleted');
        });
    }
};
