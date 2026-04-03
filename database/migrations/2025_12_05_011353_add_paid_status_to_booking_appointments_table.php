<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // For PostgreSQL, we need to drop and recreate the column with the new enum values
        // Laravel's enum() method creates a CHECK constraint in PostgreSQL
        if (DB::getDriverName() === 'pgsql') {
            // Drop the existing check constraint
            DB::statement("ALTER TABLE booking_appointments DROP CONSTRAINT IF EXISTS booking_appointments_status_check");
            // Add new constraint with 'paid' option
            DB::statement("ALTER TABLE booking_appointments ADD CONSTRAINT booking_appointments_status_check CHECK (status IN ('pending', 'paid', 'confirmed', 'completed', 'cancelled', 'no_show', 'rescheduled'))");
        } else {
            // MySQL syntax
            DB::statement("ALTER TABLE `booking_appointments` MODIFY COLUMN `status` ENUM('pending', 'paid', 'confirmed', 'completed', 'cancelled', 'no_show', 'rescheduled') DEFAULT 'pending'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            // Drop the constraint and recreate without 'paid'
            DB::statement("ALTER TABLE booking_appointments DROP CONSTRAINT IF EXISTS booking_appointments_status_check");
            DB::statement("ALTER TABLE booking_appointments ADD CONSTRAINT booking_appointments_status_check CHECK (status IN ('pending', 'confirmed', 'completed', 'cancelled', 'no_show', 'rescheduled'))");
        } else {
            // MySQL syntax
            DB::statement("ALTER TABLE `booking_appointments` MODIFY COLUMN `status` ENUM('pending', 'confirmed', 'completed', 'cancelled', 'no_show', 'rescheduled') DEFAULT 'pending'");
        }
    }
};
