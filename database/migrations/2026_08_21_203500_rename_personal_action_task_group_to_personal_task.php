<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rename stored task_group label "Personal Action" → "Personal Task"
     * so UI and database use the same product term.
     */
    public function up(): void
    {
        foreach (['notes', 'activities_logs'] as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'task_group')) {
                continue;
            }

            DB::table($table)
                ->where('task_group', 'Personal Action')
                ->update(['task_group' => 'Personal Task']);
        }
    }

    public function down(): void
    {
        // Irreversible for rows created as "Personal Task" after up().
        // Only reverse the original rename when an exact leftover match is needed
        // via a targeted data fix — do not mass-rewrite all Personal Task rows.
    }
};
