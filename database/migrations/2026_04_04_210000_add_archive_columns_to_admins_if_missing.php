<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fresh PostgreSQL installs created `admins` from 0001 without archive columns; leads/clients code expects them.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('admins')) {
            return;
        }

        if (! Schema::hasColumn('admins', 'is_archived')) {
            Schema::table('admins', function (Blueprint $table) {
                $table->unsignedTinyInteger('is_archived')->default(0);
            });
        }

        if (! Schema::hasColumn('admins', 'archived_by')) {
            Schema::table('admins', function (Blueprint $table) {
                $table->unsignedBigInteger('archived_by')->nullable();
            });
        }

        if (! Schema::hasColumn('admins', 'archived_on')) {
            Schema::table('admins', function (Blueprint $table) {
                $table->timestamp('archived_on')->nullable();
            });
        }

        try {
            Schema::table('admins', function (Blueprint $table) {
                $table->foreign('archived_by')
                    ->references('id')
                    ->on('admins')
                    ->onDelete('set null');
            });
        } catch (\Throwable) {
            // FK already present (e.g. from 2026_01_26_000000_add_archived_by_to_admins_table)
        }

        try {
            Schema::table('admins', function (Blueprint $table) {
                $table->index('archived_by');
            });
        } catch (\Throwable) {
            // Index already present
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('admins')) {
            return;
        }

        try {
            Schema::table('admins', function (Blueprint $table) {
                $table->dropForeign(['archived_by']);
            });
        } catch (\Throwable) {
            //
        }

        try {
            Schema::table('admins', function (Blueprint $table) {
                $table->dropIndex(['archived_by']);
            });
        } catch (\Throwable) {
            //
        }

        if (Schema::hasColumn('admins', 'archived_on')) {
            Schema::table('admins', function (Blueprint $table) {
                $table->dropColumn('archived_on');
            });
        }
        if (Schema::hasColumn('admins', 'archived_by')) {
            Schema::table('admins', function (Blueprint $table) {
                $table->dropColumn('archived_by');
            });
        }
        if (Schema::hasColumn('admins', 'is_archived')) {
            Schema::table('admins', function (Blueprint $table) {
                $table->dropColumn('is_archived');
            });
        }
    }
};
