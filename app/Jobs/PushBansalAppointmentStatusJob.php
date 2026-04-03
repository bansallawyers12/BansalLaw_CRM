<?php

namespace App\Jobs;

use App\Models\BookingAppointment;
use App\Services\BansalAppointmentSync\AppointmentSyncService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PushBansalAppointmentStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        protected int $appointmentId,
        protected string $status,
        protected ?string $reason = null
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(AppointmentSyncService $syncService): void
    {
        $appointment = BookingAppointment::find($this->appointmentId);

        if (!$appointment) {
            Log::warning('PushBansalAppointmentStatusJob: appointment not found', [
                'appointment_id' => $this->appointmentId,
                'status' => $this->status,
            ]);
            return;
        }

        try {
            $syncService->pushStatusUpdate($appointment, $this->status, $this->reason);

            $appointment->forceFill([
                'last_synced_at' => now(),
                'sync_status' => 'synced',
                'sync_error' => null,
            ])->save();
        } catch (Exception $e) {
            $appointment->forceFill([
                'sync_status' => 'error',
                'sync_error' => $e->getMessage(),
            ])->save();

            // Re-throw so the job is marked as failed and can be retried if configured.
            throw $e;
        }
    }
}


