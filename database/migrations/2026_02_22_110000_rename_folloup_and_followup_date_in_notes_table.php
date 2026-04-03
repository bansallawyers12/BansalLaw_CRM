<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Renames Action tab columns for clarity:
     * - folloup → is_action (1 = Action item, 0 = regular note)
     * - followup_date → action_date (scheduled date for the action)
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        // Drop indexes that reference the columns we're renaming (if they exist)
        $indexes = ['idx_notes_action_assigned', 'idx_notes_task_group_date', 'idx_notes_client_tasks', 'idx_notes_completed_date'];
        foreach ($indexes as $indexName) {
            if ($this->indexExists('notes', $indexName, $driver)) {
                Schema::table('notes', function (Blueprint $table) use ($indexName) {
                    $table->dropIndex($indexName);
                });
            }
        }

        Schema::table('notes', function (Blueprint $table) {
            $table->renameColumn('folloup', 'is_action');
            $table->renameColumn('followup_date', 'action_date');
        });

        Schema::table('notes', function (Blueprint $table) {
            $table->index(['type', 'status', 'assigned_to', 'is_action'], 'idx_notes_action_assigned');
            $table->index(['type', 'task_group', 'action_date'], 'idx_notes_task_group_date');
            $table->index(['type', 'client_id', 'is_action'], 'idx_notes_client_tasks');
            $table->index(['type', 'status', 'action_date'], 'idx_notes_completed_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        $indexes = ['idx_notes_action_assigned', 'idx_notes_task_group_date', 'idx_notes_client_tasks', 'idx_notes_completed_date'];
        foreach ($indexes as $indexName) {
            if ($this->indexExists('notes', $indexName, $driver)) {
                Schema::table('notes', function (Blueprint $table) use ($indexName) {
                    $table->dropIndex($indexName);
                });
            }
        }

        Schema::table('notes', function (Blueprint $table) {
            $table->renameColumn('is_action', 'folloup');
            $table->renameColumn('action_date', 'followup_date');
        });

        Schema::table('notes', function (Blueprint $table) {
            $table->index(['type', 'status', 'assigned_to', 'folloup'], 'idx_notes_action_assigned');
            $table->index(['type', 'task_group', 'followup_date'], 'idx_notes_task_group_date');
            $table->index(['type', 'client_id', 'folloup'], 'idx_notes_client_tasks');
            $table->index(['type', 'status', 'followup_date'], 'idx_notes_completed_date');
        });
    }

    private function indexExists(string $table, string $index, string $driver): bool
    {
        if ($driver === 'mysql') {
            $result = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$index]);
            return !empty($result);
        }
        if ($driver === 'pgsql') {
            $result = DB::select("SELECT 1 FROM pg_indexes WHERE tablename = ? AND indexname = ?", [$table, $index]);
            return !empty($result);
        }
        return false;
    }
};
