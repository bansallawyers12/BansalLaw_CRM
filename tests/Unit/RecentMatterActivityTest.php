<?php

namespace Tests\Unit;

use App\Models\ClientMatter;
use App\Services\DashboardService;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use Tests\TestCase;

class RecentMatterActivityTest extends TestCase
{
    #[DataProvider('activityTypeProvider')]
    public function test_activity_type_from_matter_maps_updated_at_type(string $updatedAtType, string $expected): void
    {
        $matter = new ClientMatter(['updated_at_type' => $updatedAtType]);

        $method = new ReflectionMethod(DashboardService::class, 'activityTypeFromMatter');
        $method->setAccessible(true);

        $this->assertSame(
            $expected,
            $method->invoke(new DashboardService(), $matter)
        );
    }

    public static function activityTypeProvider(): array
    {
        return [
            'empty defaults to recently updated' => ['', 'default'],
            'signed maps to signed badge' => ['signed', 'signed'],
            'email maps to email badge' => ['email', 'email_sent'],
            'note maps to note badge' => ['note', 'note_added'],
            'document maps to upload badge' => ['document', 'document_uploaded'],
            'stage maps to stage badge' => ['stage_updated', 'stage_updated'],
        ];
    }

    #[DataProvider('upcomingDeadlineProvider')]
    public function test_matter_has_upcoming_deadline(?string $deadline, bool $expected): void
    {
        $service = new DashboardService();
        $method = new ReflectionMethod(DashboardService::class, 'matterHasUpcomingDeadline');
        $method->setAccessible(true);

        $matter = new ClientMatter(['deadline' => $deadline]);
        [$deadlineStart, $deadlineEnd] = (new ReflectionMethod(DashboardService::class, 'upcomingDeadlineDateRange'))
            ->invoke($service);

        $this->assertSame(
            $expected,
            $method->invoke($service, $matter, $deadlineStart, $deadlineEnd)
        );
    }

    public static function upcomingDeadlineProvider(): array
    {
        $today = \Carbon\Carbon::today()->toDateString();
        $inThreeDays = \Carbon\Carbon::today()->addDays(3)->toDateString();
        $inEightDays = \Carbon\Carbon::today()->addDays(8)->toDateString();
        $yesterday = \Carbon\Carbon::today()->subDay()->toDateString();

        return [
            'null deadline is excluded' => [null, false],
            'deadline today is included' => [$today, true],
            'deadline in three days is included' => [$inThreeDays, true],
            'deadline beyond seven days is excluded' => [$inEightDays, false],
            'past deadline is excluded' => [$yesterday, false],
        ];
    }

    #[DataProvider('activityFromLogProvider')]
    public function test_activity_from_log_maps_type_and_subject(string $activityType, string $subject, string $expected): void
    {
        $log = new \App\Models\ActivitiesLog([
            'activity_type' => $activityType,
            'subject' => $subject,
        ]);

        $method = new ReflectionMethod(DashboardService::class, 'activityFromLog');
        $method->setAccessible(true);

        $result = $method->invoke(new DashboardService(), $log);

        $this->assertSame($expected, $result['type']);
    }

    public static function activityFromLogProvider(): array
    {
        return [
            'email column maps to email sent' => ['email', 'hello', 'email_sent'],
            'note column maps to note added' => ['note', 'hello', 'note_added'],
            'document column maps to upload' => ['document', 'hello', 'document_uploaded'],
            'generic type uses email subject' => ['activity', 'Inbox Email Re-assign', 'email_sent'],
            'generic type uses note subject' => ['activity', 'added a note', 'note_added'],
        ];
    }

    public function test_pick_activity_log_prefers_matter_reference_in_subject(): void
    {
        $method = new ReflectionMethod(DashboardService::class, 'pickActivityLogForMatter');
        $method->setAccessible(true);

        $clientLogs = collect([
            new \App\Models\ActivitiesLog(['id' => 20, 'subject' => 'Email for OTHER2600001']),
            new \App\Models\ActivitiesLog(['id' => 19, 'subject' => 'Note for MAND2600086']),
        ]);
        $matter = new ClientMatter(['client_unique_matter_no' => 'MAND2600086']);

        $picked = $method->invoke(new DashboardService(), $clientLogs, $matter);

        $this->assertSame('Note for MAND2600086', $picked->subject);
    }
}
