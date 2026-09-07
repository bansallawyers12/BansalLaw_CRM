<?php

namespace Tests\Unit;

use App\Support\TimelineBillingSchedule;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class TimelineBillingScheduleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $path = TimelineBillingSchedule::storagePath();
        if (File::exists($path)) {
            File::delete($path);
        }
    }

    protected function tearDown(): void
    {
        $path = TimelineBillingSchedule::storagePath();
        if (File::exists($path)) {
            File::delete($path);
        }
        parent::tearDown();
    }

    public function test_structure_includes_email_category(): void
    {
        $structure = TimelineBillingSchedule::structure();
        $keys = array_column($structure, 'key');

        $this->assertContains('email', $keys);
        $this->assertContains('note', $keys);
        $this->assertContains('search', $keys);
    }

    public function test_resolve_category_for_email_and_stage(): void
    {
        $this->assertSame('email', TimelineBillingSchedule::resolveCategory('note', 'Email to mediator'));
        $this->assertSame('activity', TimelineBillingSchedule::resolveCategory('activity', 'completed action for Client'));
        $this->assertNull(TimelineBillingSchedule::resolveCategory('stage', 'Stage updated'));
    }

    public function test_save_amounts_persists_email_rate(): void
    {
        TimelineBillingSchedule::saveAmounts(['email' => 75.5]);

        $this->assertSame(75.5, TimelineBillingSchedule::amountFor('email'));
        $this->assertFileExists(TimelineBillingSchedule::storagePath());
    }
}
