<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Legacy MySQL client_matters includes user_id (staff who created / owns the row).
 * Stub / PostgreSQL installs created a minimal table without it; inserts then fail.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('client_matters')) {
            return;
        }

        if (Schema::hasColumn('client_matters', 'user_id')) {
            return;
        }

        Schema::table('client_matters', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->index();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('client_matters') || ! Schema::hasColumn('client_matters', 'user_id')) {
            return;
        }

        Schema::table('client_matters', function (Blueprint $table) {
            $table->dropColumn('user_id');
        });
    }
};
