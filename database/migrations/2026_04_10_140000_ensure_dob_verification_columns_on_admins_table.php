<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DOB verification fields used by ClientsController and client detail views.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('admins')) {
            return;
        }

        if (! Schema::hasColumn('admins', 'dob_verified_date')) {
            Schema::table('admins', function (Blueprint $table) {
                $table->timestamp('dob_verified_date')->nullable();
            });
        }

        if (! Schema::hasColumn('admins', 'dob_verified_by')) {
            Schema::table('admins', function (Blueprint $table) {
                $table->unsignedBigInteger('dob_verified_by')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('admins')) {
            return;
        }

        if (Schema::hasColumn('admins', 'dob_verified_by')) {
            Schema::table('admins', function (Blueprint $table) {
                $table->dropColumn('dob_verified_by');
            });
        }
        if (Schema::hasColumn('admins', 'dob_verified_date')) {
            Schema::table('admins', function (Blueprint $table) {
                $table->dropColumn('dob_verified_date');
            });
        }
    }
};
