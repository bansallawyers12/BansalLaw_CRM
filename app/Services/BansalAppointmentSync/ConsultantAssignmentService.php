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
        $noeId = $appointment['noe_id'] ?? null;
        $serviceId = $appointment['service_id'] ?? null;
        
        // Map location string to inperson_address if needed
        if ($location === 'adelaide' || $inpersonAddress == 1) {
            return 'adelaide';
        }

        // Melbourne calendars (based on noe_id and service_id)
        if ($location === 'melbourne' || $inpersonAddress == 2 || empty($inpersonAddress)) {
            
            // Education: noe_id=5, service_id=2 (Free)  OR 1 (Paid) OR 3 (Paid Overseas)
            if ($noeId == 5 && ($serviceId == 2 || $serviceId == 1 || $serviceId == 3)) {
                return 'education';
            }

            // JRP: noe_id in [2,3], service_id=2 (Free)
            if (in_array($noeId, [2, 3]) && $serviceId == 2) {
                return 'jrp';
            }

            // Tourist: noe_id=4, service_id=2 (Free) OR 1 (Paid) OR 3 (Paid Overseas)
            if ($noeId == 4 && ($serviceId == 2 || $serviceId == 1 || $serviceId == 3)) {
                return 'tourist';
            }

            // Others/Paid:
            // - service_id=1 (Paid) or 3 (Paid Overseas) with any noe_id in [1,2,3,6,7,8]
            // - OR service_id=2 (Free) with noe_id in [1,6,7]
            if ( ( $serviceId == 1 || $serviceId == 3 )&& in_array($noeId, [1, 2, 3, 6, 7, 8])) {
                return 'paid';
            }
            if ($serviceId == 2 && in_array($noeId, [1, 6, 7])) {
                return 'paid';
            }
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

