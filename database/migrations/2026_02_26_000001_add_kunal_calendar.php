<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Add 'kunal' to the calendar_type and create Kunal Calendar consultant
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            $constraints = DB::select("
                SELECT conname as constraint_name
                FROM pg_constraint 
                WHERE conrelid = 'appointment_consultants'::regclass 
                AND contype = 'c'
                AND pg_get_constraintdef(oid) LIKE '%calendar_type%'
            ");
            
            foreach ($constraints as $constraint) {
                DB::statement("ALTER TABLE appointment_consultants DROP CONSTRAINT IF EXISTS {$constraint->constraint_name}");
            }
            
            DB::statement("ALTER TABLE appointment_consultants ADD CONSTRAINT appointment_consultants_calendar_type_check CHECK (calendar_type IN ('paid', 'jrp', 'education', 'tourist', 'adelaide', 'ajay', 'kunal'))");
        } else {
            if (Schema::hasColumn('appointment_consultants', 'calendar_type')) {
                DB::statement("ALTER TABLE appointment_consultants MODIFY COLUMN calendar_type ENUM('paid', 'jrp', 'education', 'tourist', 'adelaide', 'ajay', 'kunal')");
            }
        }

        // Insert Kunal Calendar consultant if not exists
        if (!DB::table('appointment_consultants')->where('calendar_type', 'kunal')->exists()) {
            $nextId = DB::table('appointment_consultants')->max('id') + 1;
            DB::table('appointment_consultants')->insert([
                'id' => $nextId,
                'name' => 'Kunal Calendar',
                'email' => 'kunal@bansalimmigration.com',
                'calendar_type' => 'kunal',
                'location' => 'melbourne',
                'specializations' => json_encode([]),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            // Update sequence for PostgreSQL
            if (DB::getDriverName() === 'pgsql') {
                DB::statement("SELECT setval(pg_get_serial_sequence('appointment_consultants', 'id'), (SELECT MAX(id) FROM appointment_consultants))");
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove Kunal Calendar consultant
        DB::table('appointment_consultants')->where('calendar_type', 'kunal')->delete();

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE appointment_consultants DROP CONSTRAINT IF EXISTS appointment_consultants_calendar_type_check");
            DB::statement("ALTER TABLE appointment_consultants ADD CONSTRAINT appointment_consultants_calendar_type_check CHECK (calendar_type IN ('paid', 'jrp', 'education', 'tourist', 'adelaide', 'ajay'))");
        } else {
            if (Schema::hasColumn('appointment_consultants', 'calendar_type')) {
                DB::statement("ALTER TABLE appointment_consultants MODIFY COLUMN calendar_type ENUM('paid', 'jrp', 'education', 'tourist', 'adelaide', 'ajay')");
            }
        }
    }
};
