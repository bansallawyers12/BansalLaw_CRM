<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Drops columns that have been replaced or removed:
     * - profile_img: replaced with static avatar.png
     * - telephone: use phone + country_code
     * - nati_language, py_field, regional_points: EOI fields without form UI (removed)
     */
    public function up(): void
    {
        // admins table
        $adminsColumnsToDrop = ['profile_img', 'telephone', 'nati_language', 'py_field', 'regional_points'];
        $adminsExisting = array_filter($adminsColumnsToDrop, fn (string $c) => Schema::hasColumn('admins', $c));
        if (!empty($adminsExisting)) {
            Schema::table('admins', function (Blueprint $table) use ($adminsExisting) {
                $table->dropColumn(array_values($adminsExisting));
            });
        }

        // staff table
        if (Schema::hasTable('staff')) {
            $staffColumnsToDrop = ['profile_img', 'telephone'];
            $staffExisting = array_filter($staffColumnsToDrop, fn (string $c) => Schema::hasColumn('staff', $c));
            if (!empty($staffExisting)) {
                Schema::table('staff', function (Blueprint $table) use ($staffExisting) {
                    $table->dropColumn(array_values($staffExisting));
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasColumn('admins', 'profile_img')) {
            Schema::table('admins', function (Blueprint $table) {
                $table->string('profile_img')->nullable()->after('status');
            });
        }
        if (!Schema::hasColumn('admins', 'telephone')) {
            Schema::table('admins', function (Blueprint $table) {
                $table->string('telephone')->nullable()->after('country_code');
            });
        }
        if (!Schema::hasColumn('admins', 'nati_language')) {
            Schema::table('admins', function (Blueprint $table) {
                $table->string('nati_language')->nullable();
            });
        }
        if (!Schema::hasColumn('admins', 'py_field')) {
            Schema::table('admins', function (Blueprint $table) {
                $table->string('py_field')->nullable();
            });
        }
        if (!Schema::hasColumn('admins', 'regional_points')) {
            Schema::table('admins', function (Blueprint $table) {
                $table->string('regional_points')->nullable();
            });
        }

        if (Schema::hasTable('staff')) {
            if (!Schema::hasColumn('staff', 'telephone')) {
                Schema::table('staff', function (Blueprint $table) {
                    $table->string('telephone', 100)->nullable()->after('phone');
                });
            }
            if (!Schema::hasColumn('staff', 'profile_img')) {
                Schema::table('staff', function (Blueprint $table) {
                    $table->string('profile_img', 500)->nullable()->after('telephone');
                });
            }
        }
    }
};
