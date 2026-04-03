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
     * Adds DEFAULT 0 to the integer statistic columns in appointment_sync_logs table.
     * This matches the original migration file definition and fixes the NOT NULL constraint violation
     * when creating sync log records without explicitly providing these values.
     */
    public function up(): void
    {
        // Check if table exists
        if (Schema::hasTable('appointment_sync_logs')) {
            // Add default value of 0 to all integer statistic columns
            // This matches the original migration file definition
            DB::statement("
                ALTER TABLE appointment_sync_logs 
                ALTER COLUMN appointments_fetched SET DEFAULT 0,
                ALTER COLUMN appointments_new SET DEFAULT 0,
                ALTER COLUMN appointments_updated SET DEFAULT 0,
                ALTER COLUMN appointments_skipped SET DEFAULT 0,
                ALTER COLUMN appointments_failed SET DEFAULT 0
            ");
        }
    }

    /**
     * Reverse the migrations.
     * 
     * Removes the default values from the columns.
     */
    public function down(): void
    {
        if (Schema::hasTable('appointment_sync_logs')) {
            // Remove default values
            DB::statement("
                ALTER TABLE appointment_sync_logs 
                ALTER COLUMN appointments_fetched DROP DEFAULT,
                ALTER COLUMN appointments_new DROP DEFAULT,
                ALTER COLUMN appointments_updated DROP DEFAULT,
                ALTER COLUMN appointments_skipped DROP DEFAULT,
                ALTER COLUMN appointments_failed DROP DEFAULT
            ");
        }
    }
};
