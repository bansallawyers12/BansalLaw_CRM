<?php

namespace Tests\Unit;

use App\Services\BansalAppointmentSync\AppointmentSyncService;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SyncBansalAppointmentsCommandTest extends TestCase
{
    #[Test]
    public function skips_successfully_when_api_not_configured(): void
    {
        $sync = Mockery::mock(AppointmentSyncService::class);
        $sync->shouldReceive('isApiConfigured')->once()->andReturn(false);
        $sync->shouldNotReceive('syncRecentAppointments');
        $this->app->instance(AppointmentSyncService::class, $sync);

        $this->artisan('booking:sync-appointments', ['--minutes' => 1440])
            ->assertExitCode(0);
    }

    #[Test]
    public function returns_success_when_some_appointments_fail(): void
    {
        $sync = Mockery::mock(AppointmentSyncService::class);
        $sync->shouldReceive('isApiConfigured')->once()->andReturn(true);
        $sync->shouldReceive('syncRecentAppointments')->once()->with(1440)->andReturn([
            'fetched' => 3,
            'new' => 1,
            'updated' => 1,
            'skipped' => 0,
            'failed' => 1,
            'errors' => [['appointment_id' => 9, 'error' => 'boom']],
        ]);
        $this->app->instance(AppointmentSyncService::class, $sync);

        $this->artisan('booking:sync-appointments', ['--minutes' => 1440])
            ->assertExitCode(0);
    }

    #[Test]
    public function returns_failure_when_sync_throws(): void
    {
        $sync = Mockery::mock(AppointmentSyncService::class);
        $sync->shouldReceive('isApiConfigured')->once()->andReturn(true);
        $sync->shouldReceive('syncRecentAppointments')->once()->andThrow(new \RuntimeException('API down'));
        $this->app->instance(AppointmentSyncService::class, $sync);

        $this->artisan('booking:sync-appointments', ['--minutes' => 10])
            ->assertExitCode(1);
    }
}
