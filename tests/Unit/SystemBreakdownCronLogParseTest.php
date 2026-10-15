<?php

namespace Tests\Unit;

use App\Services\SystemBreakdownService;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

class SystemBreakdownCronLogParseTest extends TestCase
{
    #[Test]
    public function it_parses_timestamped_inbox_cron_capture_lines(): void
    {
        $content = <<<'LOG'
[2026-10-15 19:54:19] cron.INFO: Starting inbox sync for michael@bansallawyers.com.au...
/opt/alt/php-fpm83/usr/bin/php: no version information available
[2026-10-15 19:54:25] cron.INFO: Done. Imported: 0, Already in CRM: 40, Failed: 0
LOG;

        $entries = $this->parsePlainLogLines($content, 'inbox-sync/email-inbox-sync-michael-2026-10-15.log');

        $this->assertCount(3, $entries);
        $this->assertSame('2026-10-15 19:54:25', $entries[0]['timestamp']);
        $this->assertStringContainsString('Done. Imported: 0', $entries[0]['title']);
        $this->assertSame('2026-10-15 19:54:19', $entries[1]['timestamp']);
        $this->assertStringContainsString('no version information', $entries[1]['title']);
        $this->assertSame('2026-10-15 19:54:19', $entries[2]['timestamp']);
        $this->assertStringContainsString('Starting inbox sync', $entries[2]['title']);
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function parsePlainLogLines(string $content, string $relativePath): array
    {
        $service = new SystemBreakdownService;
        $method = new ReflectionMethod(SystemBreakdownService::class, 'parsePlainLogLines');
        $method->setAccessible(true);

        return $method->invoke($service, $content, 50, null, null, $relativePath);
    }
}
