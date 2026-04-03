<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Update activities_logs.use_for from 'application' to 'matter'.
     * The value 'application' identified matter/client-portal related activity (stage changes, notes).
     *
     * On PostgreSQL, use_for can be integer (assignee IDs) or string. We alter to varchar(64)
     * so it can store both integer-as-string (e.g. '123') and strings ('matter', 'application').
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            $row = DB::selectOne("
                SELECT data_type FROM information_schema.columns
                WHERE table_name = 'activities_logs' AND column_name = 'use_for'
            ");
            if ($row && in_array($row->data_type, ['integer', 'bigint', 'smallint'], true)) {
                DB::statement('ALTER TABLE activities_logs ALTER COLUMN use_for TYPE VARCHAR(64) USING COALESCE(use_for::text, \'\')');
            }
        }

        DB::table('activities_logs')
            ->where('use_for', 'application')
            ->update(['use_for' => 'matter']);
    }

    public function down(): void
    {
        DB::table('activities_logs')
            ->where('use_for', 'matter')
            ->update(['use_for' => 'application']);
    }
};
