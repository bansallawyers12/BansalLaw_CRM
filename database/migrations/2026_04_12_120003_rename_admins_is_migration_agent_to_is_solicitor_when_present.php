<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('admins')) {
            return;
        }

        if (Schema::hasColumn('admins', 'is_solicitor') && Schema::hasColumn('admins', 'is_migration_agent')) {
            DB::statement(
                'UPDATE admins SET is_solicitor = CASE WHEN COALESCE(is_migration_agent, 0) = 1 OR COALESCE(is_solicitor, 0) = 1 THEN 1 ELSE 0 END'
            );
            $this->dropAdminsLegacyAgentFlagIndex();
            Schema::table('admins', function (Blueprint $table) {
                $table->dropColumn('is_migration_agent');
            });

            return;
        }

        if (Schema::hasColumn('admins', 'is_solicitor')) {
            return;
        }

        if (! Schema::hasColumn('admins', 'is_migration_agent')) {
            return;
        }

        Schema::table('admins', function (Blueprint $table) {
            $table->tinyInteger('is_solicitor')->default(0)->nullable();
        });

        DB::table('admins')->update([
            'is_solicitor' => DB::raw('COALESCE(is_migration_agent, 0)'),
        ]);

        $this->dropAdminsLegacyAgentFlagIndex();

        Schema::table('admins', function (Blueprint $table) {
            $table->dropColumn('is_migration_agent');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('admins')) {
            return;
        }

        if (Schema::hasColumn('admins', 'is_migration_agent')) {
            return;
        }

        if (! Schema::hasColumn('admins', 'is_solicitor')) {
            return;
        }

        Schema::table('admins', function (Blueprint $table) {
            $table->tinyInteger('is_migration_agent')->default(0)->nullable();
        });

        DB::table('admins')->update([
            'is_migration_agent' => DB::raw('COALESCE(is_solicitor, 0)'),
        ]);

        Schema::table('admins', function (Blueprint $table) {
            $table->index('is_migration_agent', 'admins_is_migration_agent_index');
        });

        Schema::table('admins', function (Blueprint $table) {
            $table->dropColumn('is_solicitor');
        });
    }

    private function dropAdminsLegacyAgentFlagIndex(): void
    {
        try {
            Schema::table('admins', function (Blueprint $table) {
                $table->dropIndex(['is_migration_agent']);
            });
        } catch (\Throwable) {
            try {
                Schema::table('admins', function (Blueprint $table) {
                    $table->dropIndex('admins_is_migration_agent_index');
                });
            } catch (\Throwable) {
                // Index may not exist on this driver / schema
            }
        }
    }
};
