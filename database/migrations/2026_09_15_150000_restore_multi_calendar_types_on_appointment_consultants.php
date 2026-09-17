<?php

use App\Services\StaffPersonalCalendarFeedService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Restore multi-calendar types (plus kunal/Michael) on appointment_consultants.
     */
    public function up(): void
    {
        if (! Schema::hasTable('appointment_consultants')) {
            return;
        }

        $types = StaffPersonalCalendarFeedService::calendarTypeKeys();
        $inList = collect($types)->map(fn ($t) => "'" . str_replace("'", "''", $t) . "'")->implode(', ');

        if (DB::getDriverName() === 'pgsql') {
            $constraints = DB::select("
                SELECT conname AS constraint_name
                FROM pg_constraint
                WHERE conrelid = 'appointment_consultants'::regclass
                  AND contype = 'c'
                  AND pg_get_constraintdef(oid) LIKE '%calendar_type%'
            ");

            foreach ($constraints as $constraint) {
                DB::statement('ALTER TABLE appointment_consultants DROP CONSTRAINT IF EXISTS ' . $constraint->constraint_name);
            }

            DB::statement("ALTER TABLE appointment_consultants ADD CONSTRAINT appointment_consultants_calendar_type_check CHECK (calendar_type IN ({$inList}))");

            DB::statement('ALTER TABLE appointment_consultants DROP CONSTRAINT IF EXISTS appointment_consultants_location_check');
            DB::statement("ALTER TABLE appointment_consultants ADD CONSTRAINT appointment_consultants_location_check CHECK (location IN ('melbourne', 'adelaide'))");
        } elseif (Schema::hasColumn('appointment_consultants', 'calendar_type')) {
            DB::statement("ALTER TABLE appointment_consultants MODIFY COLUMN calendar_type ENUM({$inList}) NOT NULL");
            if (Schema::hasColumn('appointment_consultants', 'location')) {
                DB::statement("ALTER TABLE appointment_consultants MODIFY COLUMN location ENUM('melbourne', 'adelaide') NOT NULL");
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('appointment_consultants')) {
            return;
        }

        // Re-narrow to ajay/kunal (previous production constraint).
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE appointment_consultants DROP CONSTRAINT IF EXISTS appointment_consultants_calendar_type_check');
            DB::statement("ALTER TABLE appointment_consultants ADD CONSTRAINT appointment_consultants_calendar_type_check CHECK (calendar_type IN ('ajay', 'kunal'))");
            DB::statement('ALTER TABLE appointment_consultants DROP CONSTRAINT IF EXISTS appointment_consultants_location_check');
            DB::statement("ALTER TABLE appointment_consultants ADD CONSTRAINT appointment_consultants_location_check CHECK (location IN ('melbourne'))");
        } elseif (Schema::hasColumn('appointment_consultants', 'calendar_type')) {
            DB::statement("ALTER TABLE appointment_consultants MODIFY COLUMN calendar_type ENUM('ajay', 'kunal') NOT NULL");
            if (Schema::hasColumn('appointment_consultants', 'location')) {
                DB::statement("ALTER TABLE appointment_consultants MODIFY COLUMN location ENUM('melbourne') NOT NULL");
            }
        }
    }
};
