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
        // Explicit consultant from website push / CRM config
        if (! empty($appointmentData['consultant_id']) && is_numeric($appointmentData['consultant_id'])) {
            $byId = AppointmentConsultant::where('id', (int) $appointmentData['consultant_id'])
                ->where('is_active', true)
                ->first();
            if ($byId) {
                return $byId;
            }
        }

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

        $location = $appointment['location'] ?? null;
        $inpersonAddress = $appointment['inperson_address'] ?? null;
        $noeId = (int) ($appointment['noe_id'] ?? 0);
        $serviceId = $appointment['service_id'] ?? null;
        $scheme = $appointment['noe_scheme'] ?? 'immigration';

        $resolved = null;

        if ($location === 'adelaide' || $inpersonAddress == 1) {
            $resolved = 'adelaide';
        } elseif ($location === 'melbourne' || $inpersonAddress == 2 || empty($inpersonAddress)) {
            $validService = in_array($serviceId, [1, 2, 3], true);

            if ($scheme === 'crm') {
                if ($noeId === 11 && $validService) {
                    $resolved = 'education';
                } elseif ($noeId === 12 && $validService) {
                    $resolved = 'tourist';
                } else {
                    $resolved = 'paid';
                }
            } elseif ($noeId == 5 && $validService) {
                $resolved = 'education';
            } elseif (in_array($noeId, [2, 3], true) && $serviceId == 2) {
                $resolved = 'jrp';
            } elseif ($noeId == 4 && $validService) {
                $resolved = 'tourist';
            } elseif (($serviceId == 1 || $serviceId == 3) && in_array($noeId, [1, 2, 3, 6, 7, 8], true)) {
                $resolved = 'paid';
            } elseif ($serviceId == 2 && in_array($noeId, [1, 6, 7], true)) {
                $resolved = 'paid';
            }
        }

        // Legal CRM only has ajay/kunal calendars — remap legacy immigration types.
        if ($resolved !== null && ! in_array($resolved, $allowed, true)) {
            return $default;
        }

        return $resolved ?? $default;
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

