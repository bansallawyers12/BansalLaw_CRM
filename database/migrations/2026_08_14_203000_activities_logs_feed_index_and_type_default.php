<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('activities_logs')) {
            return;
        }

        if (Schema::hasColumn('activities_logs', 'activity_type') && DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE activities_logs ALTER COLUMN activity_type SET DEFAULT 'activity'");
        }

        $indexName = 'activities_logs_client_id_created_at_index';
        $hasIndex = false;
        if (DB::getDriverName() === 'pgsql') {
            $row = DB::selectOne(
                'SELECT 1 AS ok FROM pg_indexes WHERE schemaname = current_schema() AND indexname = ?',
                [$indexName]
            );
            $hasIndex = (bool) $row;
        }

        if (! $hasIndex) {
            try {
                Schema::table('activities_logs', function (Blueprint $table) use ($indexName) {
                    $table->index(['client_id', 'created_at'], $indexName);
                });
            } catch (\Throwable) {
                // Index may already exist under a different name.
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('activities_logs')) {
            return;
        }

        if (Schema::hasColumn('activities_logs', 'activity_type') && DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE activities_logs ALTER COLUMN activity_type SET DEFAULT 'note'");
        }

        try {
            Schema::table('activities_logs', function (Blueprint $table) {
                $table->dropIndex('activities_logs_client_id_created_at_index');
            });
        } catch (\Throwable) {
            //
        }
    }
};
