<?php

namespace App\Services\BansalAppointmentSync;

use App\Models\BookingAppointment;
use App\Models\AppointmentSyncLog;
use App\Models\ActivitiesLog;
use App\Models\Admin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

class AppointmentSyncService
{
    protected BansalApiClient $apiClient;
    protected ClientMatchingService $clientMatcher;
    protected ConsultantAssignmentService $consultantAssigner;
    
    protected AppointmentSyncLog $syncLog;

    public function __construct(
        BansalApiClient $apiClient,
        ClientMatchingService $clientMatcher,
        ConsultantAssignmentService $consultantAssigner
    ) {
        $this->apiClient = $apiClient;
        $this->clientMatcher = $clientMatcher;
        $this->consultantAssigner = $consultantAssigner;
    }

    /**
     * Sync recent appointments (main polling method)
     */
    public function syncRecentAppointments(int $minutes = 10): array
    {
        // Create sync log
        $this->syncLog = AppointmentSyncLog::create([
            'sync_type' => 'polling',
            'started_at' => now(),
            'status' => 'running'
        ]);

        $stats = [
            'fetched' => 0,
            'new' => 0,
            'updated' => 0,
            'skipped' => 0,
            'failed' => 0,
            'errors' => []
        ];

        try {
            Log::info('Starting appointment sync', ['minutes' => $minutes]);

            // Fetch appointments from Bansal API
            $appointments = $this->apiClient->getRecentAppointments($minutes);
            $stats['fetched'] = count($appointments);

            Log::info("Fetched {$stats['fetched']} appointments from Bansal API");

            // Process each appointment
            foreach ($appointments as $appointmentData) {
                try {
                    $result = $this->processAppointment($appointmentData);
                    
                    if ($result === 'new') {
                        $stats['new']++;
                    } elseif ($result === 'updated') {
                        $stats['updated']++;
                    } elseif ($result === 'skipped') {
                        $stats['skipped']++;
                    }
                } catch (Exception $e) {
                    $stats['failed']++;
                    $stats['errors'][] = [
                        'appointment_id' => $appointmentData['id'] ?? 'unknown',
                        'error' => $e->getMessage()
                    ];
                    
                    Log::error('Failed to process appointment', [
                        'appointment_id' => $appointmentData['id'] ?? null,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }

            // Update sync log
            $this->syncLog->update([
                'completed_at' => now(),
                'status' => $stats['failed'] > 0 ? 'failed' : 'success',
                'appointments_fetched' => $stats['fetched'],
                'appointments_new' => $stats['new'],
                'appointments_updated' => $stats['updated'],
                'appointments_skipped' => $stats['skipped'],
                'appointments_failed' => $stats['failed'],
                'sync_details' => json_encode($stats)
            ]);

            Log::info('Appointment sync completed', $stats);

            return $stats;
        } catch (Exception $e) {
            $this->syncLog->update([
                'completed_at' => now(),
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'appointments_fetched' => $stats['fetched'],
                'appointments_failed' => $stats['failed']
            ]);

            Log::error('Appointment sync failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    /**
     * Process single appointment
     */
    protected function processAppointment(array $appointmentData): string
    {
        // Debug: Check $appointmentData array
        Log::info('Processing appointment data', [
            'full_data' => $appointmentData,
            'status' => $appointmentData['status'] ?? 'not_set',
            'is_paid' => $appointmentData['is_paid'] ?? 'not_set',
            'payment_data' => $appointmentData['payment'] ?? 'not_set'
        ]);
        
        $bansalId = $appointmentData['id'];

        // Check if already exists
        $existingAppointment = BookingAppointment::where('bansal_appointment_id', $bansalId)->first();

        if ($existingAppointment) {
            $updated = false;
            $mappedPaymentStatus = $this->mapPaymentStatus($appointmentData) ?? ($appointmentData['payment_status'] ?? null);
            $newIsPaid = isset($appointmentData['is_paid']) ? (bool) $appointmentData['is_paid'] : null;
            $newStatus = $appointmentData['status'] ?? null;

            if ($mappedPaymentStatus && $existingAppointment->payment_status !== $mappedPaymentStatus) {
                $existingAppointment->payment_status = $mappedPaymentStatus;
                $updated = true;
            }
            if ($newIsPaid !== null && (bool) $existingAppointment->is_paid !== $newIsPaid) {
                $existingAppointment->is_paid = $newIsPaid;
                $updated = true;
            }
            if (!empty($appointmentData['payment']['paid_at'])) {
                $parsedPaidAt = Carbon::parse($appointmentData['payment']['paid_at']);
                if ($existingAppointment->paid_at != $parsedPaidAt) {
                    $existingAppointment->paid_at = $parsedPaidAt;
                    $updated = true;
                }
            } elseif ($newIsPaid === true && empty($existingAppointment->paid_at)) {
                $existingAppointment->paid_at = now();
                $updated = true;
            }
            if (!empty($appointmentData['payment']['payment_method']) && $existingAppointment->payment_method !== $appointmentData['payment']['payment_method']) {
                $existingAppointment->payment_method = $appointmentData['payment']['payment_method'];
                $updated = true;
            }
            if (isset($appointmentData['final_amount']) && (float) $existingAppointment->final_amount !== (float) $appointmentData['final_amount']) {
                $existingAppointment->final_amount = $appointmentData['final_amount'];
                $updated = true;
            }
            if (isset($appointmentData['amount']) && (float) $existingAppointment->amount !== (float) $appointmentData['amount']) {
                $existingAppointment->amount = $appointmentData['amount'];
                $updated = true;
            }
            if (isset($appointmentData['discount_amount']) && (float) $existingAppointment->discount_amount !== (float) $appointmentData['discount_amount']) {
                $existingAppointment->discount_amount = $appointmentData['discount_amount'];
                $updated = true;
            }
            if (!empty($appointmentData['promo_code']) && $existingAppointment->promo_code !== $appointmentData['promo_code']) {
                $existingAppointment->promo_code = $appointmentData['promo_code'];
                $updated = true;
            }

            if ($newStatus !== null && $newStatus !== '') {
                $mappedStatus = $this->mapStatus((string) $newStatus);
                $terminal = ['cancelled', 'completed', 'no_show'];
                $wouldReopen = in_array($existingAppointment->status, $terminal, true)
                    && ! in_array($mappedStatus, $terminal, true);

                if (! $wouldReopen && $existingAppointment->status !== $mappedStatus) {
                    $existingAppointment->status = $mappedStatus;
                    $updated = true;
                    if ($mappedStatus === 'cancelled' && empty($existingAppointment->cancelled_at)) {
                        $existingAppointment->cancelled_at = now();
                    }
                    if ($mappedStatus === 'confirmed' && empty($existingAppointment->confirmed_at)) {
                        $existingAppointment->confirmed_at = now();
                    }
                    if ($mappedStatus === 'completed' && empty($existingAppointment->completed_at)) {
                        $existingAppointment->completed_at = now();
                    }
                }
            }

            // Always update last sync timestamps
            $existingAppointment->last_synced_at = now();
            $existingAppointment->sync_status = 'synced';
            $existingAppointment->sync_error = null;

            if ($updated) {
                $existingAppointment->save();
                Log::info('Updated existing appointment from website sync', ['bansal_id' => $bansalId]);
                return 'updated';
            }

            $existingAppointment->save();
            return 'skipped';
        }

        // Match or create client
        $client = $this->clientMatcher->findOrCreateClient($appointmentData);

        // Calculate service_id and noe_id BEFORE assigning consultant
        // These are needed by assignConsultant() to determine calendar type
        $serviceId = $this->mapServiceId($appointmentData);
        $noeId = $this->mapNoeId($appointmentData);
        $location = $appointmentData['location'] ?? null;
        $inpersonAddress = $location ? $this->mapInpersonAddress($location) : null;
        
        // Prepare appointment data with calculated values for consultant assignment
        $scheme = \App\Support\BookingCatalogue::inferNoeScheme($appointmentData);
        $appointmentDataForConsultant = array_merge($appointmentData, [
            'service_id' => $serviceId,
            'noe_id' => $noeId,
            'inperson_address' => $inpersonAddress === 1 ? 2 : $inpersonAddress,
            'location' => ($appointmentData['location'] ?? null) === 'adelaide' ? 'melbourne' : ($appointmentData['location'] ?? 'melbourne'),
            'noe_scheme' => $scheme,
        ]);

        // Assign consultant (now has access to service_id and noe_id)
        $consultant = $this->consultantAssigner->assignConsultant($appointmentDataForConsultant);

        // Map status
        $status = $this->mapStatus((string) ($appointmentData['status'] ?? 'pending'));

        // Create appointment record
        $appointment = BookingAppointment::create([
            'bansal_appointment_id' => $bansalId,
            'order_hash' => $appointmentData['order_hash'] ?? null,
            
            'client_id' => $client?->id,
            'consultant_id' => $consultant?->id,
            
            'client_name' => $appointmentData['full_name'],
            'client_email' => $appointmentData['email'],
            'client_phone' => $appointmentData['phone'] ?? null,
            'client_timezone' => 'Australia/Melbourne',
            
            'appointment_datetime' => Carbon::parse($appointmentData['appointment_datetime']),
            'timeslot_full' => $appointmentData['appointment_time'] ?? null,
            'duration_minutes' => $appointmentData['duration_minutes']
                ?? \App\Support\BookingCatalogue::durationMinutesForDbServiceId($serviceId),
            'location' => ($appointmentData['location'] ?? null) === 'adelaide'
                ? 'melbourne'
                : ($appointmentData['location'] ?? 'melbourne'),
            'inperson_address' => $inpersonAddress === 1 ? 2 : $inpersonAddress,
            'meeting_type' => $this->mapMeetingType($appointmentData['meeting_type'] ?? null),
            'preferred_language' => $appointmentData['preferred_language'] ?? 'English',
            
            'service_id' => $serviceId,
            'noe_id' => $noeId,
            'noe_scheme' => $scheme,
            'enquiry_type' => $appointmentData['enquiry_type'] ?? null,
            'service_type' => $appointmentData['service_type'] ?? null,
            'enquiry_details' => $appointmentData['enquiry_details'] ?? null,
            
            'status' => $status,
            'confirmed_at' => $status === 'confirmed' ? now() : null,
            
            'is_paid' => $appointmentData['is_paid'] ?? false,
            'amount' => $appointmentData['amount'] ?? 0,
            'discount_amount' => $appointmentData['discount_amount'] ?? 0,
            'final_amount' => $appointmentData['final_amount'] ?? 0,
            'promo_code' => $appointmentData['promo_code'] ?? null,
            'payment_status' => $this->mapPaymentStatus($appointmentData),
            'payment_method' => $appointmentData['payment']['payment_method'] ?? null,
            'paid_at' => !empty($appointmentData['payment']['paid_at']) 
                ? Carbon::parse($appointmentData['payment']['paid_at']) 
                : null,
            
            'synced_from_bansal_at' => now(),
            'last_synced_at' => now(),
            'sync_status' => 'synced',
        ]);

        Log::info('Created new appointment', [
            'bansal_id' => $bansalId,
            'crm_id' => $appointment->id,
            'client_id' => $client?->id,
            'consultant_id' => $consultant?->id
        ]);

        // Create activity log for synced appointment (only if client exists)
        if ($appointment->client_id) {
            try {
                $this->createActivityLogForSyncedAppointment($appointment, $serviceId, $noeId);
            } catch (Exception $e) {
                // Log error but don't fail the sync process
                Log::warning('Failed to create activity log for synced appointment', [
                    'appointment_id' => $appointment->id,
                    'client_id' => $appointment->client_id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return 'new';
    }

    /**
     * Map location to inperson_address (legacy compatibility)
     */
    protected function mapInpersonAddress(string $location): ?int
    {
        return match($location) {
            'adelaide' => 1,
            'melbourne' => 2,
            default => null,
        };
    }

    /**
     * Map service_id from Bansal data
     */
    protected function mapServiceId(array $appointmentData): ?int
    {
        $specific = (string) ($appointmentData['specific_service'] ?? '');
        $duration = (int) ($appointmentData['duration_minutes'] ?? 0);
        $amount = (float) ($appointmentData['final_amount'] ?? $appointmentData['amount'] ?? 0);
        $isPaid = ! empty($appointmentData['is_paid']) || $amount > 0;

        if ($specific === 'extended-consultation' || $duration === 60 || abs($amount - 220.0) < 0.01) {
            return 3;
        }
        if ($specific === 'overseas-enquiry') {
            // Retired Lawyers product; treat historical overseas as standard paid 30 min.
            return 1;
        }
        if ($specific === 'paid-consultation' || ($isPaid && $specific !== 'consultation')) {
            return 1;
        }
        if ($isPaid) {
            return 1;
        }

        return 2; // Free
    }

    /**
     * Map noe_id from enquiry_type
     */
    protected function mapNoeId(array $appointmentData): ?int
    {
        if (isset($appointmentData['noe_id']) && is_numeric($appointmentData['noe_id'])) {
            $direct = (int) $appointmentData['noe_id'];
            if ($direct > 0) {
                return $direct;
            }
        }

        $serviceType = $appointmentData['service_type'] ?? null;
        $enquiryType = $appointmentData['enquiry_type'] ?? null;

        foreach (\App\Support\BookingCatalogue::crmNatureOfEnquiry() as $row) {
            if ($serviceType === ($row['service_type'] ?? null) || $enquiryType === ($row['enquiry_type'] ?? null)) {
                return (int) $row['id'];
            }
        }

        return match($serviceType) {
            'permanent-residency' => 1,
            'temporary-residency' => 2,
            'jrp-skill-assessment' => 3,
            'tourist-visa' => 4,
            'education-visa' => 5,
            'complex-matters' => 6,
            'visa-cancellation' => 7,
            'international-migration' => 8,
            default => null,
        };
    }

    /**
     * Map meeting type from Bansal API to CRM enum values
     * Handles various formats and normalizes to: 'in_person', 'phone', 'video'
     */
    protected function mapMeetingType(?string $meetingType): string
    {
        // Handle NULL or empty string
        if (empty($meetingType)) {
            return 'in_person'; // Default value
        }

        // Normalize: convert to lowercase and replace spaces/hyphens with underscores
        $normalized = strtolower(trim($meetingType));
        $normalized = str_replace([' ', '-'], '_', $normalized);

        return match($normalized) {
            'in_person', 'inperson', 'in-person', 'in person', 'office', 'onsite' => 'in_person',
            'phone', 'telephone', 'call' => 'phone',
            'video', 'videocall', 'video_call', 'zoom', 'online' => 'video',
            default => 'in_person' // Default fallback
        };
    }

    /**
     * Map status from Bansal to CRM
     */
    protected function mapStatus(string $bansalStatus): string
    {
        $normalized = strtolower(trim($bansalStatus));

        // Website numeric statuses (lawyers) → CRM slugs
        if (ctype_digit($normalized)) {
            return match ((int) $normalized) {
                1, 5 => 'confirmed',
                2 => 'completed',
                3, 7 => 'cancelled',
                6, 8 => 'no_show',
                10 => 'paid',
                default => 'pending',
            };
        }

        return match($normalized) {
            'pending' => 'pending',
            'paid' => 'paid',
            'confirmed', 'approved' => 'confirmed',
            'completed' => 'completed',
            'cancelled', 'canceled', 'rejected' => 'cancelled',
            'no_show', 'noshow', 'missed' => 'no_show',
            default => 'pending',
        };
    }

    /**
     * Map payment status
     */
    protected function mapPaymentStatus(array $appointmentData): ?string
    {
        if (empty($appointmentData['payment'])) {
            return null;
        }

        $paymentStatus = $appointmentData['payment']['status'] ?? null;

        return match($paymentStatus) {
            'completed', 'succeeded' => 'completed',
            'pending', 'processing' => 'pending',
            'failed' => 'failed',
            'refunded' => 'refunded',
            default => null
        };
    }

    /**
     * Backfill historical appointments
     */
    public function backfillHistoricalData(Carbon $startDate, Carbon $endDate): array
    {
        $this->syncLog = AppointmentSyncLog::create([
            'sync_type' => 'backfill',
            'started_at' => now(),
            'status' => 'running'
        ]);

        $stats = [
            'fetched' => 0,
            'new' => 0,
            'skipped' => 0,
            'failed' => 0
        ];

        try {
            Log::info('Starting backfill', [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString()
            ]);

            $page = 1;
            $hasMore = true;

            while ($hasMore) {
                $response = $this->apiClient->getAppointmentsByDateRange(
                    $startDate->toDateString(),
                    $endDate->toDateString(),
                    $page
                );

                $appointments = $response['data'] ?? [];
                $pagination = $response['pagination'] ?? [];

                $stats['fetched'] += count($appointments);

                foreach ($appointments as $appointmentData) {
                    try {
                        $result = $this->processAppointment($appointmentData);
                        if ($result === 'new') {
                            $stats['new']++;
                        } else {
                            $stats['skipped']++;
                        }
                    } catch (Exception $e) {
                        $stats['failed']++;
                        Log::error('Backfill: Failed to process appointment', [
                            'appointment_id' => $appointmentData['id'] ?? null,
                            'error' => $e->getMessage()
                        ]);
                    }
                }

                // Check if more pages
                $hasMore = !empty($pagination['current_page']) && 
                          !empty($pagination['last_page']) && 
                          $pagination['current_page'] < $pagination['last_page'];
                $page++;

                // Add delay between pages to avoid rate limiting
                if ($hasMore) {
                    sleep(2);
                }
            }

            $this->syncLog->update([
                'completed_at' => now(),
                'status' => 'success',
                'appointments_fetched' => $stats['fetched'],
                'appointments_new' => $stats['new'],
                'appointments_skipped' => $stats['skipped'],
                'appointments_failed' => $stats['failed']
            ]);

            Log::info('Backfill completed', $stats);

            return $stats;
        } catch (Exception $e) {
            $this->syncLog->update([
                'completed_at' => now(),
                'status' => 'failed',
                'error_message' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Push status update to Bansal API.
     */
    public function pushStatusUpdate(BookingAppointment $appointment, string $status, ?string $reason = null): ?array
    {
        if (empty($appointment->bansal_appointment_id)) {
            Log::warning('Skipping Bansal status update: missing bansal_appointment_id', [
                'appointment_id' => $appointment->id,
                'status' => $status,
            ]);
            return null;
        }

        $type = match ($status) {
            'cancelled' => 'cancel',
            'completed' => 'complete',
            'confirmed' => 'confirm',
            default => null,
        };

        if ($type === null) {
            Log::warning('Skipping Bansal status update: unsupported status type', [
                'appointment_id' => $appointment->id,
                'status' => $status,
            ]);
            return null;
        }

        try {
            $context = [];
            if ($appointment->appointment_datetime) {
                $context['appointment_date'] = $appointment->appointment_datetime->format('Y-m-d');
                $context['appointment_time'] = $appointment->appointment_datetime->format('H:i');
                $context['meeting_type'] = $appointment->meeting_type ?? 'in_person';
                $context['preferred_language'] = $appointment->preferred_language ?? 'English';
            }

            $response = $this->apiClient->updateAppointmentStatus(
                (int) $appointment->bansal_appointment_id,
                $type,
                $reason,
                $context
            );

            Log::info('Bansal appointment status updated', [
                'appointment_id' => $appointment->id,
                'bansal_appointment_id' => $appointment->bansal_appointment_id,
                'status' => $status,
                'response' => $response,
            ]);

            return $response;
        } catch (Exception $e) {
            Log::error('Failed to push status update to Bansal API', [
                'appointment_id' => $appointment->id,
                'bansal_appointment_id' => $appointment->bansal_appointment_id,
                'status' => $status,
                'reason' => $reason,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Create activity log entry for synced appointment
     * 
     * @param BookingAppointment $appointment
     * @param int|null $serviceId
     * @param int|null $noeId
     * @return void
     */
    protected function createActivityLogForSyncedAppointment(BookingAppointment $appointment, ?int $serviceId, ?int $noeId): void
    {
        // Determine subject based on service type
        $subject = 'scheduled an appointment';
        $serviceTitle = 'Appointment';
        
        if ($serviceId == 2) {
            $subject = 'scheduled a free appointment';
            $serviceTitle = 'Free Consultation';
        } elseif ($serviceId == 1) {
            $subject = 'scheduled a paid appointment';
            $serviceTitle = 'Standard Consultation';
        } elseif ($serviceId == 3) {
            $subject = 'scheduled a paid appointment';
            $serviceTitle = 'Extended Consultation';
        }

        $enquiryTitle = \App\Support\BookingCatalogue::enquiryTypeDisplay(
            $appointment->enquiry_type,
            $noeId,
            $appointment->noe_scheme ?? 'crm'
        );

        // Format meeting type
        $appointmentDetails = '';
        if ($appointment->meeting_type) {
            $meetingType = strtolower($appointment->meeting_type);
            if ($meetingType === 'in_person') {
                $appointmentDetails = 'In Person';
            } elseif ($meetingType === 'phone') {
                $appointmentDetails = 'Phone';
            } elseif ($meetingType === 'video') {
                $appointmentDetails = 'Video Call';
            }
        }

        // Format appointment date
        $appointmentDate = $appointment->appointment_datetime;
        $activityLogDate = $appointmentDate ? $appointmentDate->format('Y-m-d') : date('Y-m-d');
        
        // Format appointment time
        $appointmentTime = $appointment->timeslot_full ?? ($appointmentDate ? $appointmentDate->format('h:i A') : '');

        // Build description HTML (similar to manual appointment creation)
        $description = '<div style="display: -webkit-inline-box;">
                <span style="height: 60px; width: 60px; border: 1px solid rgb(3, 169, 244); border-radius: 50%; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 2px;overflow: hidden;">
                    <span  style="flex: 1 1 0%; width: 100%; text-align: center; background: rgb(237, 237, 237); border-top-left-radius: 120px; border-top-right-radius: 120px; font-size: 12px;line-height: 24px;">
                        ' . date('d M', strtotime($activityLogDate)) . '
                    </span>
                    <span style="background: rgb(84, 178, 75); color: rgb(255, 255, 255); flex: 1 1 0%; width: 100%; border-bottom-left-radius: 120px; border-bottom-right-radius: 120px; text-align: center;font-size: 12px; line-height: 21px;">
                        ' . date('Y', strtotime($activityLogDate)) . '
                    </span>
                </span>
            </div>
            <div style="display:inline-grid;">
                <span class="text-semi-bold">' . e($enquiryTitle) . '</span> 
                <span class="text-semi-bold">' . e($serviceTitle) . '</span>';
        
        if ($appointmentDetails) {
            $description .= '  <span class="text-semi-bold">' . e($appointmentDetails) . '</span>';
        }
        
        if ($appointment->enquiry_details) {
            $description .= '  <span class="text-semi-bold">' . e($appointment->enquiry_details) . '</span>';
        }
        
        if ($appointmentTime) {
            $description .= '  <p class="text-semi-light-grey col-v-1">@ ' . e($appointmentTime) . '</p>';
        }
        
        $description .= '</div>';

        // Get client name for subject
        $clientName = '';
        if ($appointment->client_id) {
            // Try to get client name from Admin model (first_name + last_name)
            $client = Admin::where('id', $appointment->client_id)
                ->whereIn('type', ['client', 'lead'])
                ->select('first_name', 'last_name')
                ->first();
            
            if ($client) {
                $clientName = trim(($client->first_name ?? '') . ' ' . ($client->last_name ?? ''));
            }
        }
        
        // Fallback to client_name field if Admin lookup didn't work
        if (empty($clientName) && $appointment->client_name) {
            $clientName = trim($appointment->client_name);
        }
        
        // Prepend client name to subject (format: "Client Name scheduled an appointment")
        $finalSubject = $subject;
        /*if (!empty($clientName)) {
            $finalSubject = $clientName . ' ' . $subject;
        }*/

        // Create activity log entry
        ActivitiesLog::create([
            'client_id' => $appointment->client_id,
            'created_by' => $appointment->client_id, // System sync, not a user action
            'subject' => $finalSubject,
            'description' => $description,
            'activity_type' => 'activity',
            'task_status' => 0,
            'pin' => 0,
        ]);
    }
}

