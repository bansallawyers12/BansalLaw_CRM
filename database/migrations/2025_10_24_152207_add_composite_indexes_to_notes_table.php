<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds composite indexes to optimize client task queries in action pages.
     * These indexes target the most common query patterns in AssigneeController.
     */
    public function up(): void
    {
        Schema::table('notes', function (Blueprint $table) {
            // Index for main action page queries (assigned tasks)
            // Optimizes: WHERE type='client' AND status<>'1' AND assigned_to=X AND folloup=1
            $table->index(['type', 'status', 'assigned_to', 'folloup'], 'idx_notes_action_assigned');
            
            // Index for task filtering by group and date
            // Optimizes: WHERE type='client' AND task_group=X AND followup_date
            $table->index(['type', 'task_group', 'followup_date'], 'idx_notes_task_group_date');
            
            // Index for client-specific task queries
            // Optimizes: WHERE type='client' AND client_id=X AND folloup=1
            $table->index(['type', 'client_id', 'folloup'], 'idx_notes_client_tasks');
            
            // Index for completed tasks queries
            // Optimizes: WHERE type='client' AND status='1' AND assigned_to=X
            $table->index(['type', 'status', 'followup_date'], 'idx_notes_completed_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notes', function (Blueprint $table) {
            $table->dropIndex('idx_notes_action_assigned');
            $table->dropIndex('idx_notes_task_group_date');
            $table->dropIndex('idx_notes_client_tasks');
            $table->dropIndex('idx_notes_completed_date');
        });
    }
};
