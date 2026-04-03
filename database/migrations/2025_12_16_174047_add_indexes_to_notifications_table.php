<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds composite indexes to optimize notification queries.
     * These indexes target the most common query patterns used for fetching notifications.
     */
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            // Composite index for office visit notification queries
            // Optimizes: WHERE receiver_id=X AND notification_type='officevisit' AND receiver_status=0 ORDER BY created_at DESC
            $table->index(
                ['receiver_id', 'notification_type', 'receiver_status', 'created_at'], 
                'idx_notifications_receiver_type_status'
            );
            
            // Index for broadcast notification queries
            // Optimizes: WHERE notification_type='broadcast' AND receiver_id=X AND receiver_status=0 ORDER BY created_at DESC
            $table->index(
                ['notification_type', 'receiver_id', 'receiver_status', 'created_at'],
                'idx_notifications_type_receiver_status'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('idx_notifications_receiver_type_status');
            $table->dropIndex('idx_notifications_type_receiver_status');
        });
    }
};
