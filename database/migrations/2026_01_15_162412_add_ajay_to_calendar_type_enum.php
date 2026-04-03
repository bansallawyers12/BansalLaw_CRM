<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Add 'ajay' to the calendar_type enum in appointment_consultants table
     */
    public function up(): void
    {
        // Laravel's enum() in PostgreSQL uses CHECK constraints, not PostgreSQL enum types
        // We need to drop the old constraint and add a new one with 'ajay' included
        if (DB::getDriverName() === 'pgsql') {
            // Find all CHECK constraints on calendar_type column
            $constraints = DB::select("
                SELECT conname as constraint_name
                FROM pg_constraint 
                WHERE conrelid = 'appointment_consultants'::regclass 
                AND contype = 'c'
                AND pg_get_constraintdef(oid) LIKE '%calendar_type%'
            ");
            
            // Drop all found constraints
            foreach ($constraints as $constraint) {
                DB::statement("ALTER TABLE appointment_consultants DROP CONSTRAINT IF EXISTS {$constraint->constraint_name}");
            }
            
            // Add new constraint with 'ajay' included
            DB::statement("ALTER TABLE appointment_consultants ADD CONSTRAINT appointment_consultants_calendar_type_check CHECK (calendar_type IN ('paid', 'jrp', 'education', 'tourist', 'adelaide', 'ajay'))");
        } else {
            // For MySQL, we need to modify the enum
            if (Schema::hasColumn('appointment_consultants', 'calendar_type')) {
                DB::statement("ALTER TABLE appointment_consultants MODIFY COLUMN calendar_type ENUM('paid', 'jrp', 'education', 'tourist', 'adelaide', 'ajay')");
            }
        }
    }

    /**
     * Reverse the migrations.
     * Note: Removing enum values is complex and may not be fully reversible
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            // First, update any 'ajay' records to 'paid' (or another valid value)
            DB::table('appointment_consultants')
                ->where('calendar_type', 'ajay')
                ->update(['calendar_type' => 'paid']);
            
            // Drop the constraint with 'ajay'
            DB::statement("ALTER TABLE appointment_consultants DROP CONSTRAINT IF EXISTS appointment_consultants_calendar_type_check");
            
            // Recreate the original constraint without 'ajay'
            DB::statement("ALTER TABLE appointment_consultants ADD CONSTRAINT appointment_consultants_calendar_type_check CHECK (calendar_type IN ('paid', 'jrp', 'education', 'tourist', 'adelaide'))");
        } else {
            // For MySQL, we can revert to original enum
            if (Schema::hasColumn('appointment_consultants', 'calendar_type')) {
                // First, update any 'ajay' records to 'paid' (or another valid value)
                DB::table('appointment_consultants')
                    ->where('calendar_type', 'ajay')
                    ->update(['calendar_type' => 'paid']);
                
                // Then modify the enum
                DB::statement("ALTER TABLE appointment_consultants MODIFY COLUMN calendar_type ENUM('paid', 'jrp', 'education', 'tourist', 'adelaide')");
            }
        }
    }
};
