<?php

namespace App\Services\FrontDesk;

use App\Models\BookingAppointment;
use Illuminate\Support\Collection;

class CheckInAppointmentService
{
    /**
     * Return today's BookingAppointments for the given admin (client or lead) ID.
     *
     * Uses the office timezone from config (app.timezone) so "today" is always
     * relative to the office location, not UTC.
     */
    public function getTodaysAppointments(int $adminId): Collection
    {
        $tz = config('app.timezone', 'UTC');
        $today = now($tz)->toDateString();

        return BookingAppointment::with(['consultant'])
            ->where('client_id', $adminId)
            ->whereDate('appointment_datetime', $today)
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->orderBy('appointment_datetime')
            ->get();
    }

    /**
     * Format an appointment for the wizard dropdown.
     */
    public function formatForWizard(BookingAppointment $appt): array
    {
        return [
            'id'           => $appt->id,
            'datetime'     => $appt->appointment_datetime?->format('d/m/Y h:i A'),
            'status'       => $appt->status,
            'consultant'   => $appt->consultant?->name ?? 'Unassigned',
            'location'     => $appt->location_display ?? ucfirst((string) $appt->location),
            'meeting_type' => $appt->meeting_type,
        ];
    }
}
