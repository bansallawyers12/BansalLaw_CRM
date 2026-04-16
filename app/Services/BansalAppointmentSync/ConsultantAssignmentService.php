<?php

namespace App\Services\BansalAppointmentSync;

use App\Models\AppointmentConsultant;
use Illuminate\Support\Facades\Log;

class ConsultantAssignmentService
{
    /**
     * Assign consultant based on appointment details
     * Mimics the 5-calendar logic from the old appointment system (removed)
     * WARNING: AppointmentsController has been deleted - old appointment system removed
     */
    public function assignConsultant(array $appointmentData): ?AppointmentConsultant
    {
        $calendarType = $this->determineCalendarType($appointmentData);
        
        if (!$calendarType) {
            Log::warning('Could not determine calendar type for appointment', [
                'appointment_id' => $appointmentData['id'] ?? null,
                'noe_id' => $appointmentData['noe_id'] ?? null,
                'service_id' => $appointmentData['service_id'] ?? null,
                'location' => $appointmentData['location'] ?? null
            ]);
            return null;
        }

        $consultant = AppointmentConsultant::where('calendar_type', $calendarType)
            ->where('is_active', true)
            ->first();

        if (!$consultant) {
            Log::error('No active consultant found for calendar type', [
                'calendar_type' => $calendarType
            ]);
        }

        return $consultant;
    }

    /**
     * Determine calendar type based on appointment data
     * Logic copied from resources/views/Admin/appointments/calender.blade.php
     */
    protected function determineCalendarType(array $appointment): ?string
    {
        $location = $appointment['location'] ?? null;
        $inpersonAddress = $appointment['inperson_address'] ?? null;
        $noeId = (int) ($appointment['noe_id'] ?? 0);
        $serviceId = $appointment['service_id'] ?? null;
        $scheme = $appointment['noe_scheme'] ?? 'immigration';

        if ($location === 'adelaide' || $inpersonAddress == 1) {
            return 'adelaide';
        }

        if (! ($location === 'melbourne' || $inpersonAddress == 2 || empty($inpersonAddress))) {
            return null;
        }

        $validService = in_array($serviceId, [1, 2, 3], true);

        if ($scheme === 'crm') {
            if ($noeId === 11 && $validService) {
                return 'education';
            }
            if ($noeId === 12 && $validService) {
                return 'tourist';
            }

            return 'paid';
        }

        // Immigration / website booking (noe_id 1–8)
        if ($noeId == 5 && $validService) {
            return 'education';
        }
        if (in_array($noeId, [2, 3], true) && $serviceId == 2) {
            return 'jrp';
        }
        if ($noeId == 4 && $validService) {
            return 'tourist';
        }
        if (($serviceId == 1 || $serviceId == 3) && in_array($noeId, [1, 2, 3, 6, 7, 8], true)) {
            return 'paid';
        }
        if ($serviceId == 2 && in_array($noeId, [1, 6, 7], true)) {
            return 'paid';
        }

        return null;
    }

    /**
     * Get consultant by calendar type
     */
    public function getConsultantByCalendarType(string $calendarType): ?AppointmentConsultant
    {
        return AppointmentConsultant::where('calendar_type', $calendarType)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Get all active consultants
     */
    public function getAllConsultants(): \Illuminate\Database\Eloquent\Collection
    {
        return AppointmentConsultant::where('is_active', true)->get();
    }
}

