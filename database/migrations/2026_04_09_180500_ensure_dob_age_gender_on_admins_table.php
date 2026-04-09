<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Client/lead basic info saves expect dob, age, and gender on admins; some PostgreSQL
 * schemas were created without these columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('admins')) {
            return;
        }

        if (! Schema::hasColumn('admins', 'dob')) {
            Schema::table('admins', function (Blueprint $table) {
                $table->date('dob')->nullable();
            });
        }

        if (! Schema::hasColumn('admins', 'age')) {
            Schema::table('admins', function (Blueprint $table) {
                $table->string('age', 64)->nullable();
            });
        }

        if (! Schema::hasColumn('admins', 'gender')) {
            Schema::table('admins', function (Blueprint $table) {
                $table->string('gender', 32)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('admins')) {
            return;
        }

        if (Schema::hasColumn('admins', 'gender')) {
            Schema::table('admins', function (Blueprint $table) {
                $table->dropColumn('gender');
            });
        }
        if (Schema::hasColumn('admins', 'age')) {
            Schema::table('admins', function (Blueprint $table) {
                $table->dropColumn('age');
            });
        }
        if (Schema::hasColumn('admins', 'dob')) {
            Schema::table('admins', function (Blueprint $table) {
                $table->dropColumn('dob');
            });
        }
    }
};
