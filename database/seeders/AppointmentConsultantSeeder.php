<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\AppointmentConsultant;
use App\Models\BookingAppointment;

class AppointmentConsultantSeeder extends Seeder
{
    /**
     * Seed Lawyers CRM calendars only (ajay / kunal).
     */
    public function run(): void
    {
        $desired = [
            [
                'id' => 1,
                'name' => 'Michael',
                'email' => 'kunal@bansallawyers.com.au',
                'calendar_type' => 'kunal',
                'location' => 'melbourne',
                'specializations' => [],
                'is_active' => true,
            ],
            [
                'id' => 2,
                'name' => 'Ajay',
                'email' => 'ajay@bansallawyers.com.au',
                'calendar_type' => 'ajay',
                'location' => 'melbourne',
                'specializations' => [],
                'is_active' => true,
            ],
        ];

        $defaultId = (int) config('booking_calendar.local_consultant_id_by_calendar_type.ajay', 2);

        // Remap bookings off any leftover immigration consultants before delete.
        $legacyIds = AppointmentConsultant::query()
            ->whereNotIn('calendar_type', ['ajay', 'kunal'])
            ->pluck('id');

        if ($legacyIds->isNotEmpty()) {
            BookingAppointment::whereIn('consultant_id', $legacyIds)
                ->update(['consultant_id' => $defaultId]);
            AppointmentConsultant::whereIn('id', $legacyIds)->delete();
        }

        foreach ($desired as $row) {
            AppointmentConsultant::updateOrCreate(
                ['calendar_type' => $row['calendar_type']],
                [
                    'name' => $row['name'],
                    'email' => $row['email'],
                    'location' => $row['location'],
                    'specializations' => $row['specializations'],
                    'is_active' => $row['is_active'],
                ]
            );
        }

        // Align IDs with booking_calendar config defaults when safe.
        foreach ($desired as $row) {
            $existing = AppointmentConsultant::where('calendar_type', $row['calendar_type'])->first();
            if (! $existing || (int) $existing->id === (int) $row['id']) {
                continue;
            }

            $targetTaken = AppointmentConsultant::where('id', $row['id'])->exists();
            if ($targetTaken) {
                continue;
            }

            BookingAppointment::where('consultant_id', $existing->id)
                ->update(['consultant_id' => $row['id']]);

            DB::table('appointment_consultants')
                ->where('id', $existing->id)
                ->update(['id' => $row['id']]);
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("SELECT setval(pg_get_serial_sequence('appointment_consultants', 'id'), (SELECT COALESCE(MAX(id), 1) FROM appointment_consultants))");
        }

        $this->command?->info('✓ Appointment consultants limited to ajay / kunal (Michael + Ajay)');
    }
}
