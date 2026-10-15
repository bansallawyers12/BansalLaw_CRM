<?php

namespace Tests\Unit;

use App\Logging\CronAppendLogFormatter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CronAppendLogFormatterTest extends TestCase
{
    #[Test]
    public function it_formats_lines_for_system_breakdown_parsing(): void
    {
        config(['app.timezone' => 'Australia/Melbourne']);
        $this->travelTo('2026-10-15 19:54:19');

        $line = CronAppendLogFormatter::format('info', 'Starting inbox sync for michael@bansallawyers.com.au...');

        $this->assertSame(
            '[2026-10-15 19:54:19] cron.INFO: Starting inbox sync for michael@bansallawyers.com.au...',
            $line
        );
    }
}
