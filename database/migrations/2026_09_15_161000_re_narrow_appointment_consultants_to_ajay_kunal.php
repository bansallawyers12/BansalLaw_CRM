<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Re-narrow appointment_consultants.calendar_type to ajay/kunal
     * after temporarily restoring multi-calendar types.
     */
    public function up(): void
    {
        if (! Schema::hasTable('appointment_consultants')) {
            return;
        }

        $defaultType = 'ajay';
        $defaultConsultantId = (int) (
            DB::table('appointment_consultants')
                ->where('calendar_type', $defaultType)
                ->where('is_active', true)
                ->value('id')
            ?? DB::table('appointment_consultants')->where('calendar_type', $defaultType)->value('id')
            ?? config('booking_calendar.local_consultant_id_by_calendar_type.ajay')
            ?? 0
        );

        $legacyTypes = ['paid', 'jrp', 'education', 'tourist', 'adelaide', 'adelaide_education', 'arun'];

        if ($defaultConsultantId > 0 && Schema::hasTable('booking_appointments')) {
            $legacyIds = DB::table('appointment_consultants')
                ->whereIn('calendar_type', $legacyTypes)
                ->pluck('id');
            if ($legacyIds->isNotEmpty()) {
                DB::table('booking_appointments')
                    ->whereIn('consultant_id', $legacyIds)
                    ->update(['consultant_id' => $defaultConsultantId]);
            }
        }

        DB::table('appointment_consultants')
            ->whereIn('calendar_type', $legacyTypes)
            ->update([
                'calendar_type' => $defaultType,
                'location' => 'melbourne',
                'is_active' => false,
                'updated_at' => now(),
            ]);

        if (DB::getDriverName() === 'pgsql') {
            $constraints = DB::select("
                SELECT conname AS constraint_name
                FROM pg_constraint
                WHERE conrelid = 'appointment_consultants'::regclass
                  AND contype = 'c'
                  AND (
                    pg_get_constraintdef(oid) LIKE '%calendar_type%'
                    OR pg_get_constraintdef(oid) LIKE '%location%'
                  )
            ");
            foreach ($constraints as $constraint) {
                DB::statement('ALTER TABLE appointment_consultants DROP CONSTRAINT IF EXISTS ' . $constraint->constraint_name);
            }
            DB::statement("ALTER TABLE appointment_consultants ADD CONSTRAINT appointment_consultants_calendar_type_check CHECK (calendar_type IN ('ajay', 'kunal'))");
            DB::statement("ALTER TABLE appointment_consultants ADD CONSTRAINT appointment_consultants_location_check CHECK (location IN ('melbourne'))");
        } elseif (Schema::hasColumn('appointment_consultants', 'calendar_type')) {
            DB::statement("ALTER TABLE appointment_consultants MODIFY COLUMN calendar_type ENUM('ajay', 'kunal') NOT NULL");
            if (Schema::hasColumn('appointment_consultants', 'location')) {
                DB::statement("ALTER TABLE appointment_consultants MODIFY COLUMN location ENUM('melbourne') NOT NULL");
            }
        }
    }

    public function down(): void
    {
        // Intentionally empty: multi-calendar restore was reverted by product choice.
    }
};
