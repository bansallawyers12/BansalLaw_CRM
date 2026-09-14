<?php

namespace Tests\Unit;

use App\Services\EmailSync\IncomingEmailSyncService;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SentSyncAvailabilityFloorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'app.timezone' => 'Australia/Melbourne',
            'imap_sync.sent_available_from' => '2026-08-12',
            'imap_sync.unassigned_available_from' => '2026-08-10',
        ]);
    }

    #[Test]
    public function it_resolves_sent_floor_separately_from_unassigned_floor(): void
    {
        $sent = IncomingEmailSyncService::resolveSentAvailableFrom();
        $inbox = IncomingEmailSyncService::resolveUnassignedAvailableFrom();

        $this->assertNotNull($sent);
        $this->assertNotNull($inbox);
        $this->assertSame('2026-08-12', $sent->toDateString());
        $this->assertSame('2026-08-10', $inbox->toDateString());
    }

    #[Test]
    public function it_clamps_sent_sync_since_to_august_12(): void
    {
        $service = app(IncomingEmailSyncService::class);
        $method = new \ReflectionMethod(IncomingEmailSyncService::class, 'clampSentSyncSince');
        $method->setAccessible(true);

        $fromNull = $method->invoke($service, null);
        $this->assertSame('2026-08-12', $fromNull->toDateString());

        $beforeFloor = $method->invoke($service, Carbon::parse('2026-08-01', 'Australia/Melbourne'));
        $this->assertSame('2026-08-12', $beforeFloor->toDateString());

        $afterFloor = $method->invoke($service, Carbon::parse('2026-08-20', 'Australia/Melbourne'));
        $this->assertSame('2026-08-20', $afterFloor->toDateString());
    }
}
