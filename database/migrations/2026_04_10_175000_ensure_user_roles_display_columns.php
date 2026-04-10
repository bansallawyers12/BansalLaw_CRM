<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Legacy stub user_roles (2025_09_01) had only id + timestamps. Staff role dropdowns need name/description.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('user_roles')) {
            return;
        }

        Schema::table('user_roles', function (Blueprint $table) {
            if (! Schema::hasColumn('user_roles', 'name')) {
                $table->string('name', 255)->nullable();
            }
            if (! Schema::hasColumn('user_roles', 'description')) {
                $table->text('description')->nullable();
            }
            if (! Schema::hasColumn('user_roles', 'module_access')) {
                $table->text('module_access')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('user_roles')) {
            return;
        }

        Schema::table('user_roles', function (Blueprint $table) {
            if (Schema::hasColumn('user_roles', 'module_access')) {
                $table->dropColumn('module_access');
            }
            if (Schema::hasColumn('user_roles', 'description')) {
                $table->dropColumn('description');
            }
            if (Schema::hasColumn('user_roles', 'name')) {
                $table->dropColumn('name');
            }
        });
    }
};
