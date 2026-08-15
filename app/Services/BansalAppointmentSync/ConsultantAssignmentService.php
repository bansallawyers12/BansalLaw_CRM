<?php

namespace App\Services\BansalAppointmentSync;

use App\Models\AppointmentConsultant;
use Illuminate\Support\Facades\Log;

class ConsultantAssignmentService
{
    /**
     * Assign consultant based on appointment details.
     * Lawyers CRM calendars are ajay / kunal only.
     */
    public function assignConsultant(array $appointmentData): ?AppointmentConsultant
    {
        // Explicit consultant from website push / CRM config
        if (! empty($appointmentData['consultant_id']) && is_numeric($appointmentData['consultant_id'])) {
            $byId = AppointmentConsultant::where('id', (int) $appointmentData['consultant_id'])
                ->where('is_active', true)
                ->whereIn('calendar_type', ['ajay', 'kunal'])
                ->first();
            if ($byId) {
                return $byId;
            }
        }

        $calendarType = $this->determineCalendarType($appointmentData);

        $consultant = AppointmentConsultant::where('calendar_type', $calendarType)
            ->where('is_active', true)
            ->first();

        if (! $consultant) {
            Log::error('No active consultant found for calendar type', [
                'calendar_type' => $calendarType,
                'appointment_id' => $appointmentData['id'] ?? null,
                'noe_id' => $appointmentData['noe_id'] ?? null,
                'service_id' => $appointmentData['service_id'] ?? null,
            ]);
        }

        return $consultant;
    }

    /**
     * Resolve to ajay or kunal only.
     */
    protected function determineCalendarType(array $appointment): string
    {
        $allowed = ['ajay', 'kunal'];
        $default = (string) config('booking_calendar.default_website_calendar_type', 'ajay');
        if (! in_array($default, $allowed, true)) {
            $default = 'ajay';
        }

        if (! empty($appointment['calendar_type']) && is_string($appointment['calendar_type'])) {
            $explicit = strtolower(trim($appointment['calendar_type']));
            if (in_array($explicit, $allowed, true)) {
                return $explicit;
            }
        }

        return $default;
    }

    /**
     * Get consultant by calendar type
     */
    public function getConsultantByCalendarType(string $calendarType): ?AppointmentConsultant
    {
        $type = strtolower(trim($calendarType));
        if (! in_array($type, ['ajay', 'kunal'], true)) {
            $type = (string) config('booking_calendar.default_website_calendar_type', 'ajay');
            if (! in_array($type, ['ajay', 'kunal'], true)) {
                $type = 'ajay';
            }
        }

        return AppointmentConsultant::where('calendar_type', $type)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Get all active consultants
     */
    public function getAllConsultants(): \Illuminate\Database\Eloquent\Collection
    {
        return AppointmentConsultant::where('is_active', true)
            ->whereIn('calendar_type', ['ajay', 'kunal'])
            ->get();
    }
}
