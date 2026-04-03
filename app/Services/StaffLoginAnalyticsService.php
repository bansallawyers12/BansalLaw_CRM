<?php

namespace App\Services;

use App\Models\StaffLoginLog;
use App\Models\Admin;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class StaffLoginAnalyticsService
{
    /**
     * Get daily login counts for a date range
     */
    public function getDailyLogins(?int $staffId = null, ?Carbon $startDate = null, ?Carbon $endDate = null): Collection
    {
        $startDate = $startDate ?? Carbon::now()->subDays(30);
        $endDate = $endDate ?? Carbon::now();

        $query = StaffLoginLog::query()
            ->where('message', 'like', '%Logged in%')
            ->whereBetween('created_at', [$startDate, $endDate]);

        if ($staffId) {
            $query->where('user_id', $staffId);
        }

        return $query->select(
            DB::raw("DATE(created_at) as date"),
            DB::raw('COUNT(*) as count'),
            DB::raw('COUNT(DISTINCT user_id) as unique_staff')
        )
        ->groupBy(DB::raw("DATE(created_at)"))
        ->orderBy('date', 'asc')
        ->get()
        ->map(function ($item) {
            return [
                'date' => $item->date,
                'count' => (int) $item->count,
                'unique_staff' => (int) $item->unique_staff,
            ];
        });
    }

    /**
     * Get weekly login aggregates
     */
    public function getWeeklyLogins(?int $staffId = null, ?Carbon $startDate = null, ?Carbon $endDate = null): Collection
    {
        $startDate = $startDate ?? Carbon::now()->subWeeks(12);
        $endDate = $endDate ?? Carbon::now();

        $query = StaffLoginLog::query()
            ->where('message', 'like', '%Logged in%')
            ->whereBetween('created_at', [$startDate, $endDate]);

        if ($staffId) {
            $query->where('user_id', $staffId);
        }

        return $query->select(
            DB::raw('EXTRACT(YEAR FROM created_at) as year'),
            DB::raw('EXTRACT(WEEK FROM created_at) as week'),
            DB::raw('MIN(DATE(created_at)) as week_start'),
            DB::raw('COUNT(*) as count'),
            DB::raw('COUNT(DISTINCT user_id) as unique_staff')
        )
        ->groupBy(DB::raw('EXTRACT(YEAR FROM created_at)'), DB::raw('EXTRACT(WEEK FROM created_at)'))
        ->orderBy('year', 'asc')
        ->orderBy('week', 'asc')
        ->get()
        ->map(function ($item) {
            return [
                'year' => (int) $item->year,
                'week' => (int) $item->week,
                'week_start' => $item->week_start,
                'count' => (int) $item->count,
                'unique_staff' => (int) $item->unique_staff,
                'label' => "Week {$item->week}, {$item->year}",
            ];
        });
    }

    /**
     * Get monthly login aggregates
     */
    public function getMonthlyLogins(?int $staffId = null, ?Carbon $startDate = null, ?Carbon $endDate = null): Collection
    {
        $startDate = $startDate ?? Carbon::now()->subMonths(12);
        $endDate = $endDate ?? Carbon::now();

        $query = StaffLoginLog::query()
            ->where('message', 'like', '%Logged in%')
            ->whereBetween('created_at', [$startDate, $endDate]);

        if ($staffId) {
            $query->where('user_id', $staffId);
        }

        return $query->select(
            DB::raw('EXTRACT(YEAR FROM created_at) as year'),
            DB::raw('EXTRACT(MONTH FROM created_at) as month'),
            DB::raw("TO_CHAR(created_at, 'YYYY-MM') as month_key"),
            DB::raw('COUNT(*) as count'),
            DB::raw('COUNT(DISTINCT user_id) as unique_staff')
        )
        ->groupBy(DB::raw('EXTRACT(YEAR FROM created_at)'), DB::raw('EXTRACT(MONTH FROM created_at)'))
        ->orderBy('year', 'asc')
        ->orderBy('month', 'asc')
        ->get()
        ->map(function ($item) {
            return [
                'year' => (int) $item->year,
                'month' => (int) $item->month,
                'month_key' => $item->month_key,
                'count' => (int) $item->count,
                'unique_staff' => (int) $item->unique_staff,
                'label' => Carbon::createFromDate($item->year, $item->month, 1)->format('M Y'),
            ];
        });
    }

    /**
     * Get hourly login distribution
     */
    public function getHourlyDistribution(?int $staffId = null, ?Carbon $startDate = null, ?Carbon $endDate = null): Collection
    {
        $startDate = $startDate ?? Carbon::now()->subDays(30);
        $endDate = $endDate ?? Carbon::now();

        $query = StaffLoginLog::query()
            ->where('message', 'like', '%Logged in%')
            ->whereBetween('created_at', [$startDate, $endDate]);

        if ($staffId) {
            $query->where('user_id', $staffId);
        }

        return $query->select(
            DB::raw('EXTRACT(HOUR FROM created_at) as hour'),
            DB::raw('COUNT(*) as count')
        )
        ->groupBy(DB::raw('EXTRACT(HOUR FROM created_at)'))
        ->orderBy('hour', 'asc')
        ->get()
        ->map(function ($item) {
            return [
                'hour' => (int) $item->hour,
                'count' => (int) $item->count,
                'label' => sprintf('%02d:00', $item->hour),
            ];
        });
    }

    /**
     * Get login summary statistics
     */
    public function getSummary(?int $staffId = null, ?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $startDate = $startDate ?? Carbon::now()->subDays(30);
        $endDate = $endDate ?? Carbon::now();

        $query = StaffLoginLog::query()
            ->where('message', 'like', '%Logged in%')
            ->whereBetween('created_at', [$startDate, $endDate]);

        if ($staffId) {
            $query->where('user_id', $staffId);
        }

        $totalLogins = $query->count();
        $uniqueStaff = $query->distinct('user_id')->count('user_id');
        
        $failedLogins = StaffLoginLog::query()
            ->where('level', 'critical')
            ->where('message', 'like', '%Invalid%')
            ->whereBetween('created_at', [$startDate, $endDate]);
        
        if ($staffId) {
            $failedLogins->where('user_id', $staffId);
        }
        $failedCount = $failedLogins->count();

        $avgPerDay = $totalLogins > 0 ? round($totalLogins / max(1, $startDate->diffInDays($endDate)), 2) : 0;

        // Get peak hour
        $peakHour = $this->getHourlyDistribution($staffId, $startDate, $endDate)
            ->sortByDesc('count')
            ->first();

        // Get most active day of week
        $dayOfWeek = StaffLoginLog::query()
            ->where('message', 'like', '%Logged in%')
            ->whereBetween('created_at', [$startDate, $endDate]);
        
        if ($staffId) {
            $dayOfWeek->where('user_id', $staffId);
        }
        
        $dayStats = $dayOfWeek->select(
            DB::raw('EXTRACT(DOW FROM created_at) as day'),
            DB::raw("TO_CHAR(created_at, 'Day') as day_name"),
            DB::raw('COUNT(*) as count')
        )
        ->groupBy(DB::raw('EXTRACT(DOW FROM created_at)'), DB::raw("TO_CHAR(created_at, 'Day')"))
        ->orderByDesc('count')
        ->first();

        return [
            'total_logins' => $totalLogins,
            'unique_staff' => $uniqueStaff,
            'failed_logins' => $failedCount,
            'success_rate' => $totalLogins > 0 ? round((($totalLogins - $failedCount) / $totalLogins) * 100, 2) : 100,
            'average_per_day' => $avgPerDay,
            'peak_hour' => $peakHour ? [
                'hour' => $peakHour['hour'],
                'label' => $peakHour['label'],
                'count' => $peakHour['count'],
            ] : null,
            'most_active_day' => $dayStats ? [
                'day' => $dayStats->day,
                'day_name' => $dayStats->day_name,
                'count' => (int) $dayStats->count,
            ] : null,
            'date_range' => [
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString(),
                'days' => $startDate->diffInDays($endDate),
            ],
        ];
    }

    /**
     * Get top staff by login count
     */
    public function getTopStaff(int $limit = 10, ?Carbon $startDate = null, ?Carbon $endDate = null): Collection
    {
        $startDate = $startDate ?? Carbon::now()->subDays(30);
        $endDate = $endDate ?? Carbon::now();

        return StaffLoginLog::query()
            ->where('message', 'like', '%Logged in%')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->select(
                'user_id',
                DB::raw('COUNT(*) as login_count'),
                DB::raw('MAX(created_at) as last_login')
            )
            ->groupBy('user_id')
            ->orderByDesc('login_count')
            ->limit($limit)
            ->get()
            ->map(function ($item) {
                $staff = \App\Models\Staff::find($item->user_id);
                return [
                    'staff_id' => $item->user_id,
                    'staff_name' => $staff ? trim("{$staff->first_name} {$staff->last_name}") : "Staff #{$item->user_id}",
                    'staff_email' => $staff ? $staff->email : null,
                    'login_count' => (int) $item->login_count,
                    'last_login' => $item->last_login,
                ];
            });
    }

    /**
     * Get login trends (comparing periods)
     */
    public function getTrends(?int $staffId = null, string $period = 'month'): array
    {
        $currentStart = match($period) {
            'day' => Carbon::now()->subDays(7),
            'week' => Carbon::now()->subWeeks(4),
            'month' => Carbon::now()->subMonths(3),
            default => Carbon::now()->subMonths(3),
        };

        $previousStart = $currentStart->copy()->sub($currentStart->diffInDays(Carbon::now()), 'days');

        $current = match($period) {
            'day' => $this->getDailyLogins($staffId, $currentStart, Carbon::now()),
            'week' => $this->getWeeklyLogins($staffId, $currentStart, Carbon::now()),
            'month' => $this->getMonthlyLogins($staffId, $currentStart, Carbon::now()),
            default => $this->getMonthlyLogins($staffId, $currentStart, Carbon::now()),
        };

        $previous = match($period) {
            'day' => $this->getDailyLogins($staffId, $previousStart, $currentStart),
            'week' => $this->getWeeklyLogins($staffId, $previousStart, $currentStart),
            'month' => $this->getMonthlyLogins($staffId, $previousStart, $currentStart),
            default => $this->getMonthlyLogins($staffId, $previousStart, $currentStart),
        };

        $currentTotal = $current->sum('count');
        $previousTotal = $previous->sum('count');
        $change = $previousTotal > 0 
            ? round((($currentTotal - $previousTotal) / $previousTotal) * 100, 2)
            : ($currentTotal > 0 ? 100 : 0);

        return [
            'current_period' => [
                'start' => $currentStart->toDateString(),
                'end' => Carbon::now()->toDateString(),
                'total' => $currentTotal,
                'data' => $current,
            ],
            'previous_period' => [
                'start' => $previousStart->toDateString(),
                'end' => $currentStart->toDateString(),
                'total' => $previousTotal,
                'data' => $previous,
            ],
            'change_percentage' => $change,
            'change_absolute' => $currentTotal - $previousTotal,
        ];
    }
}
