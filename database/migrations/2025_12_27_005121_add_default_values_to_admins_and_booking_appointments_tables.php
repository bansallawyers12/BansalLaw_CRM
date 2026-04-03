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
     * Adds DEFAULT values to columns that have NOT NULL constraints but no defaults.
     * This fixes the appointment sync errors:
     * 1. admins.verified - adds DEFAULT 0
     * 2. admins.is_archived - adds DEFAULT 0
     * 3. admins.show_dashboard_per - adds DEFAULT 0
     * 4. booking_appointments.follow_up_required - adds DEFAULT 0 (false)
     * 5. booking_appointments.confirmation_email_sent - adds DEFAULT 0 (false)
     * 6. booking_appointments.reminder_sms_sent - adds DEFAULT 0 (false)
     * 
     * SAFETY: This migration only adds default values. No data is modified or deleted.
     */
    public function up(): void
    {
        // Fix admins.verified column
        if (Schema::hasTable('admins') && Schema::hasColumn('admins', 'verified')) {
            DB::statement("
                ALTER TABLE admins 
                ALTER COLUMN verified SET DEFAULT 0
            ");
        }
        
        // Fix admins.is_archived column
        if (Schema::hasTable('admins') && Schema::hasColumn('admins', 'is_archived')) {
            DB::statement("
                ALTER TABLE admins 
                ALTER COLUMN is_archived SET DEFAULT 0
            ");
        }
        
        // Fix admins.show_dashboard_per column
        if (Schema::hasTable('admins') && Schema::hasColumn('admins', 'show_dashboard_per')) {
            DB::statement("
                ALTER TABLE admins 
                ALTER COLUMN show_dashboard_per SET DEFAULT 0
            ");
        }
        
        // Fix booking_appointments.follow_up_required column
        if (Schema::hasTable('booking_appointments') && Schema::hasColumn('booking_appointments', 'follow_up_required')) {
            DB::statement("
                ALTER TABLE booking_appointments 
                ALTER COLUMN follow_up_required SET DEFAULT 0
            ");
        }
        
        // Fix booking_appointments.confirmation_email_sent column
        if (Schema::hasTable('booking_appointments') && Schema::hasColumn('booking_appointments', 'confirmation_email_sent')) {
            DB::statement("
                ALTER TABLE booking_appointments 
                ALTER COLUMN confirmation_email_sent SET DEFAULT 0
            ");
        }
        
        // Fix booking_appointments.reminder_sms_sent column
        if (Schema::hasTable('booking_appointments') && Schema::hasColumn('booking_appointments', 'reminder_sms_sent')) {
            DB::statement("
                ALTER TABLE booking_appointments 
                ALTER COLUMN reminder_sms_sent SET DEFAULT 0
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
        if (Schema::hasTable('admins') && Schema::hasColumn('admins', 'verified')) {
            DB::statement("
                ALTER TABLE admins 
                ALTER COLUMN verified DROP DEFAULT
            ");
        }
        
        if (Schema::hasTable('admins') && Schema::hasColumn('admins', 'is_archived')) {
            DB::statement("
                ALTER TABLE admins 
                ALTER COLUMN is_archived DROP DEFAULT
            ");
        }
        
        if (Schema::hasTable('admins') && Schema::hasColumn('admins', 'show_dashboard_per')) {
            DB::statement("
                ALTER TABLE admins 
                ALTER COLUMN is_archived DROP DEFAULT
            ");
        }
        
        if (Schema::hasTable('admins') && Schema::hasColumn('admins', 'show_dashboard_per')) {
            DB::statement("
                ALTER TABLE admins 
                ALTER COLUMN show_dashboard_per DROP DEFAULT
            ");
        }
        
        if (Schema::hasTable('booking_appointments') && Schema::hasColumn('booking_appointments', 'follow_up_required')) {
            DB::statement("
                ALTER TABLE booking_appointments 
                ALTER COLUMN follow_up_required DROP DEFAULT
            ");
        }
        
        if (Schema::hasTable('booking_appointments') && Schema::hasColumn('booking_appointments', 'confirmation_email_sent')) {
            DB::statement("
                ALTER TABLE booking_appointments 
                ALTER COLUMN confirmation_email_sent DROP DEFAULT
            ");
        }
        
        if (Schema::hasTable('booking_appointments') && Schema::hasColumn('booking_appointments', 'reminder_sms_sent')) {
            DB::statement("
                ALTER TABLE booking_appointments 
                ALTER COLUMN reminder_sms_sent DROP DEFAULT
            ");
        }
    }
};
