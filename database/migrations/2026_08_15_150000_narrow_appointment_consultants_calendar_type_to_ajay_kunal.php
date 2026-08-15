<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lawyers CRM calendars are ajay/kunal only.
     * Remap any leftover immigration calendar types, then narrow the enum/check.
     */
    public function up(): void
    {
        if (! Schema::hasTable('appointment_consultants')) {
            return;
        }

        $defaultType = (string) config('booking_calendar.default_website_calendar_type', 'ajay');
        if (! in_array($defaultType, ['ajay', 'kunal'], true)) {
            $defaultType = 'ajay';
        }

        $defaultConsultantId = (int) (
            DB::table('appointment_consultants')
                ->where('calendar_type', $defaultType)
                ->where('is_active', true)
                ->value('id')
            ?? DB::table('appointment_consultants')->where('calendar_type', $defaultType)->value('id')
            ?? config('booking_calendar.local_consultant_id_by_calendar_type.' . $defaultType)
            ?? 0
        );

        $legacyTypes = ['paid', 'jrp', 'education', 'tourist', 'adelaide'];

        // Point historical bookings at the default Lawyers consultant before deactivating leftovers.
        if ($defaultConsultantId > 0) {
            $legacyIds = DB::table('appointment_consultants')
                ->whereIn('calendar_type', $legacyTypes)
                ->pluck('id');

            if ($legacyIds->isNotEmpty() && Schema::hasTable('booking_appointments')) {
                DB::table('booking_appointments')
                    ->whereIn('consultant_id', $legacyIds)
                    ->update(['consultant_id' => $defaultConsultantId]);
            }
        }

        DB::table('appointment_consultants')
            ->whereIn('calendar_type', $legacyTypes)
            ->update([
                'is_active' => false,
                'updated_at' => now(),
            ]);

        // Prefer deleting unused leftover consultants when nothing still references them.
        $leftoverIds = DB::table('appointment_consultants')
            ->whereIn('calendar_type', $legacyTypes)
            ->pluck('id');

        foreach ($leftoverIds as $id) {
            $stillUsed = Schema::hasTable('booking_appointments')
                && DB::table('booking_appointments')->where('consultant_id', $id)->exists();

            if (! $stillUsed) {
                DB::table('appointment_consultants')->where('id', $id)->delete();
            } else {
                // Keep the row but force a legal calendar_type so the narrowed check can apply.
                DB::table('appointment_consultants')
                    ->where('id', $id)
                    ->update([
                        'calendar_type' => $defaultType,
                        'location' => 'melbourne',
                        'is_active' => false,
                        'updated_at' => now(),
                    ]);
            }
        }

        DB::table('appointment_consultants')
            ->where('location', 'adelaide')
            ->update([
                'location' => 'melbourne',
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
        if (! Schema::hasTable('appointment_consultants')) {
            return;
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE appointment_consultants DROP CONSTRAINT IF EXISTS appointment_consultants_calendar_type_check');
            DB::statement('ALTER TABLE appointment_consultants DROP CONSTRAINT IF EXISTS appointment_consultants_location_check');
            DB::statement("ALTER TABLE appointment_consultants ADD CONSTRAINT appointment_consultants_calendar_type_check CHECK (calendar_type IN ('paid', 'jrp', 'education', 'tourist', 'adelaide', 'ajay', 'kunal'))");
            DB::statement("ALTER TABLE appointment_consultants ADD CONSTRAINT appointment_consultants_location_check CHECK (location IN ('melbourne', 'adelaide'))");
        } elseif (Schema::hasColumn('appointment_consultants', 'calendar_type')) {
            DB::statement("ALTER TABLE appointment_consultants MODIFY COLUMN calendar_type ENUM('paid', 'jrp', 'education', 'tourist', 'adelaide', 'ajay', 'kunal') NOT NULL");
            if (Schema::hasColumn('appointment_consultants', 'location')) {
                DB::statement("ALTER TABLE appointment_consultants MODIFY COLUMN location ENUM('melbourne', 'adelaide') NOT NULL");
            }
        }
    }
};
