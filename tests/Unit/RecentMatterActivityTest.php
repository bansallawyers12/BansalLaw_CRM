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
}
