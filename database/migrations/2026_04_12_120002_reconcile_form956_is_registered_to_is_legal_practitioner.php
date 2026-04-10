<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('form956')) {
            return;
        }

        $hasOld = Schema::hasColumn('form956', 'is_registered_migration_agent');
        $hasNew = Schema::hasColumn('form956', 'is_legal_practitioner');

        if ($hasOld && $hasNew) {
            DB::statement(
                'UPDATE form956 SET is_legal_practitioner = CASE WHEN COALESCE(is_registered_migration_agent, 0) = 1 OR COALESCE(is_legal_practitioner, 0) = 1 THEN 1 ELSE 0 END'
            );
            Schema::table('form956', function (Blueprint $table) {
                $table->dropColumn('is_registered_migration_agent');
            });

            return;
        }

        if ($hasOld && ! $hasNew) {
            Schema::table('form956', function (Blueprint $table) {
                $table->boolean('is_legal_practitioner')->default(false);
            });
            DB::table('form956')->update([
                'is_legal_practitioner' => DB::raw('COALESCE(is_registered_migration_agent, 0)'),
            ]);
            Schema::table('form956', function (Blueprint $table) {
                $table->dropColumn('is_registered_migration_agent');
            });

            return;
        }

        // Only new column or neither: nothing to migrate from old name
    }

    public function down(): void
    {
        if (! Schema::hasTable('form956')) {
            return;
        }

        if (Schema::hasColumn('form956', 'is_registered_migration_agent')) {
            return;
        }

        if (! Schema::hasColumn('form956', 'is_legal_practitioner')) {
            return;
        }

        Schema::table('form956', function (Blueprint $table) {
            $table->boolean('is_registered_migration_agent')->default(false);
        });

        DB::table('form956')->update([
            'is_registered_migration_agent' => DB::raw('COALESCE(is_legal_practitioner, 0)'),
        ]);

        Schema::table('form956', function (Blueprint $table) {
            $table->dropColumn('is_legal_practitioner');
        });
    }
};
