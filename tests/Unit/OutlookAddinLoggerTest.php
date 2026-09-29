<?php

namespace Tests\Unit;

use App\Logging\OutlookAddinLogger;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class OutlookAddinLoggerTest extends TestCase
{
    private string $tempLogsDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempLogsDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'outlook-addin-test-' . uniqid('', true);
        @mkdir($this->tempLogsDir, 0755, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempLogsDir)) {
            $files = glob($this->tempLogsDir . DIRECTORY_SEPARATOR . '*') ?: [];
            foreach ($files as $file) {
                @unlink($file);
            }
            @rmdir($this->tempLogsDir);
        }
        parent::tearDown();
    }

    #[Test]
    public function writes_date_wise_success_and_error_logs(): void
    {
        $today = date('Y-m-d');

        OutlookAddinLogger::write('SUCCESS', 'Save Success Operation', [
            'client_id' => 45,
            'client_matter_id' => 11,
            'document_id' => 999,
        ], $this->tempLogsDir);

        OutlookAddinLogger::write('ERROR', 'Save Failure Operation', [
            'client_id' => 45,
            'error' => 'Storage disk full',
        ], $this->tempLogsDir);

        $mainLog = $this->tempLogsDir . '/outlook-addin-' . $today . '.log';
        $errorLog = $this->tempLogsDir . '/outlook-addin-errors-' . $today . '.log';
        $successLog = $this->tempLogsDir . '/outlook-addin-success-' . $today . '.log';

        $this->assertFileExists($mainLog);
        $this->assertFileExists($errorLog);
        $this->assertFileExists($successLog);

        $mainContent = file_get_contents($mainLog);
        $this->assertStringContainsString('[SUCCESS]', $mainContent);
        $this->assertStringContainsString('Save Success Operation', $mainContent);
        $this->assertStringContainsString('"client_id":45', $mainContent);
        $this->assertStringContainsString('[ERROR]', $mainContent);
        $this->assertStringContainsString('Storage disk full', $mainContent);

        $errorContent = file_get_contents($errorLog);
        $this->assertStringContainsString('[ERROR]', $errorContent);
        $this->assertStringNotContainsString('Save Success Operation', $errorContent);

        $successContent = file_get_contents($successLog);
        $this->assertStringContainsString('[SUCCESS]', $successContent);
        $this->assertStringNotContainsString('Save Failure Operation', $successContent);
    }

    #[Test]
    public function prunes_files_older_than_ten_days_and_keeps_recent_files(): void
    {
        $today = Carbon::now()->format('Y-m-d');
        $fiveDaysAgo = Carbon::now()->subDays(5)->format('Y-m-d');
        $tenDaysAgo = Carbon::now()->subDays(10)->format('Y-m-d');
        $elevenDaysAgo = Carbon::now()->subDays(11)->format('Y-m-d');
        $twentyDaysAgo = Carbon::now()->subDays(20)->format('Y-m-d');

        // Files that should be KEPT (today and last 10 days)
        $keepToday = $this->tempLogsDir . '/outlook-addin-' . $today . '.log';
        $keepFive = $this->tempLogsDir . '/outlook-addin-' . $fiveDaysAgo . '.log';
        $keepTen = $this->tempLogsDir . '/outlook-addin-' . $tenDaysAgo . '.log';
        $keepSuccess = $this->tempLogsDir . '/outlook-addin-success-' . $fiveDaysAgo . '.log';

        // Files that should be DELETED (older than 10 days)
        $dropEleven = $this->tempLogsDir . '/outlook-addin-' . $elevenDaysAgo . '.log';
        $dropTwenty = $this->tempLogsDir . '/outlook-addin-' . $twentyDaysAgo . '.log';
        $dropOldErrors = $this->tempLogsDir . '/outlook-addin-errors-' . $elevenDaysAgo . '.log';

        foreach ([$keepToday, $keepFive, $keepTen, $keepSuccess, $dropEleven, $dropTwenty, $dropOldErrors] as $path) {
            file_put_contents($path, "log entry\n");
        }

        $result = OutlookAddinLogger::prune(10, $this->tempLogsDir);

        $this->assertSame(3, $result['deleted'], 'Should have deleted exactly 3 files older than 10 days');
        $this->assertSame(4, $result['kept'], 'Should have kept 4 files within 10 days');

        $this->assertFileExists($keepToday);
        $this->assertFileExists($keepFive);
        $this->assertFileExists($keepTen);
        $this->assertFileExists($keepSuccess);

        $this->assertFileDoesNotExist($dropEleven);
        $this->assertFileDoesNotExist($dropTwenty);
        $this->assertFileDoesNotExist($dropOldErrors);
    }
}
