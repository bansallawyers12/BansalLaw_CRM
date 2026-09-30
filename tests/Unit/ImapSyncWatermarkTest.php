<?php

namespace Tests\Unit;

use App\Services\EmailSync\IncomingEmailSyncService;
use Tests\TestCase;

class ImapSyncWatermarkTest extends TestCase
{
    public function test_clamp_watermark_when_lower_uid_failed(): void
    {
        $this->assertSame(
            100,
            IncomingEmailSyncService::clampImapWatermarkAfterFailures(100, 102, 101)
        );
    }

    public function test_clamp_watermark_unchanged_when_no_failures(): void
    {
        $this->assertSame(
            1588,
            IncomingEmailSyncService::clampImapWatermarkAfterFailures(100, 1588, null)
        );
    }
}
