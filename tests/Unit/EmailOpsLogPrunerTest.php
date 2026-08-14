<?php

namespace Tests\Unit;

use App\Logging\EmailOpsLogPruner;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmailOpsLogPrunerTest extends TestCase
{
    private string $logsDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logsDir = storage_path('logs/email-ops-pruner-test-' . uniqid('', true));
        File::makeDirectory($this->logsDir . '/inbox-sync', 0755, true);
        File::makeDirectory($this->logsDir . '/inbox-sync-runs', 0755, true);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->logsDir);
        parent::tearDown();
    }

    #[Test]
    public function deletes_dated_files_older_than_seven_days_and_keeps_recent(): void
    {
        $today = now()->format('Y-m-d');
        $sixDaysAgo = now()->subDays(6)->format('Y-m-d');
        $eightDaysAgo = now()->subDays(8)->format('Y-m-d');

        $keepInbox = $this->logsDir . '/inbox-sync/inbox-sync-' . $today . '.log';
        $keepRun = $this->logsDir . '/inbox-sync-runs/inbox-sync-run-' . $sixDaysAgo . '.log';
        $keepUpload = $this->logsDir . '/email-upload-errors-' . $today . '.log';
        $dropInbox = $this->logsDir . '/inbox-sync/inbox-sync-' . $eightDaysAgo . '.log';
        $dropRun = $this->logsDir . '/inbox-sync-runs/inbox-sync-run-' . $eightDaysAgo . '.log';
        $dropUpload = $this->logsDir . '/email-upload-errors-' . $eightDaysAgo . '.log';

        foreach ([$keepInbox, $keepRun, $keepUpload, $dropInbox, $dropRun, $dropUpload] as $path) {
            file_put_contents($path, "log\n");
        }

        $result = EmailOpsLogPruner::prune(7, $this->logsDir);

        $this->assertSame(3, $result['deleted']);
        $this->assertSame(3, $result['kept']);
        $this->assertFileExists($keepInbox);
        $this->assertFileExists($keepRun);
        $this->assertFileExists($keepUpload);
        $this->assertFileDoesNotExist($dropInbox);
        $this->assertFileDoesNotExist($dropRun);
        $this->assertFileDoesNotExist($dropUpload);
    }
}
