<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Staff assignee / lead owner: code selects admins.user_id (see StaffClientVisibility, LeadController).
 * Some PostgreSQL schemas never received this column.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('admins')) {
            return;
        }

        if (! Schema::hasColumn('admins', 'user_id')) {
            Schema::table('admins', function (Blueprint $table) {
                $table->unsignedBigInteger('user_id')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('admins') || ! Schema::hasColumn('admins', 'user_id')) {
            return;
        }

        Schema::table('admins', function (Blueprint $table) {
            $table->dropColumn('user_id');
        });
    }
};
