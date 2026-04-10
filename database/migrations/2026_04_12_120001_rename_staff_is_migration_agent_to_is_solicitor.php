<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('staff')) {
            return;
        }

        if (Schema::hasColumn('staff', 'is_solicitor') && Schema::hasColumn('staff', 'is_migration_agent')) {
            DB::statement(
                'UPDATE staff SET is_solicitor = CASE WHEN COALESCE(is_migration_agent, 0) = 1 OR COALESCE(is_solicitor, 0) = 1 THEN 1 ELSE 0 END'
            );
            Schema::table('staff', function (Blueprint $table) {
                $table->dropColumn('is_migration_agent');
            });

            return;
        }

        if (Schema::hasColumn('staff', 'is_solicitor')) {
            return;
        }

        if (! Schema::hasColumn('staff', 'is_migration_agent')) {
            return;
        }

        Schema::table('staff', function (Blueprint $table) {
            $table->tinyInteger('is_solicitor')->default(0);
        });

        DB::table('staff')->update([
            'is_solicitor' => DB::raw('COALESCE(is_migration_agent, 0)'),
        ]);

        Schema::table('staff', function (Blueprint $table) {
            $table->dropColumn('is_migration_agent');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('staff')) {
            return;
        }

        if (Schema::hasColumn('staff', 'is_migration_agent')) {
            return;
        }

        if (! Schema::hasColumn('staff', 'is_solicitor')) {
            return;
        }

        Schema::table('staff', function (Blueprint $table) {
            $table->tinyInteger('is_migration_agent')->default(0);
        });

        DB::table('staff')->update([
            'is_migration_agent' => DB::raw('COALESCE(is_solicitor, 0)'),
        ]);

        Schema::table('staff', function (Blueprint $table) {
            $table->dropColumn('is_solicitor');
        });
    }
};
