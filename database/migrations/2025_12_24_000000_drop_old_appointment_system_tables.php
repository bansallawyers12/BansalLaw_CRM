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
     * Drops all tables related to the old appointment booking system.
     * These tables are no longer used after migrating to the new booking system.
     * 
     * Tables to drop:
     * - appointments (old appointment system)
     * - appointment_logs (appointment activity logs)
     * - book_services (service types: Paid/Free)
     * - book_service_disable_slots (disabled slot management)
     * - book_service_slot_per_persons (slot configuration per person)
     * - tbl_paid_appointment_payment (payment records for old appointments)
     */
    public function up(): void
    {
        // Drop tables in reverse dependency order to avoid foreign key constraint issues
        // Start with tables that might have foreign keys pointing to them
        
        $tablesToDrop = [
            'appointment_logs',           // Logs table (references appointments)
            'tbl_paid_appointment_payment', // Payment table (might reference appointments)
            'appointments',               // Main appointments table
            'book_service_disable_slots', // Disabled slots (might reference book_services)
            'book_service_slot_per_persons', // Slot config (might reference book_services)
            'book_services',              // Service types table
        ];
        
        foreach ($tablesToDrop as $table) {
            if (Schema::hasTable($table)) {
                Schema::dropIfExists($table);
                echo "Dropped table: {$table}\n";
            }
        }
    }

    /**
     * Reverse the migrations.
     * 
     * WARNING: This will NOT recreate the tables as the old appointment system
     * has been completely removed. The table structures are not preserved.
     */
    public function down(): void
    {
        // Cannot reverse - old appointment system has been removed
        // Tables would need to be recreated manually if needed (not recommended)
        throw new \Exception('Cannot reverse this migration. Old appointment system tables have been permanently removed.');
    }
};

