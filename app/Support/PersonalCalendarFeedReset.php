<?php

namespace App\Support;

use App\Models\Staff;
use Carbon\Carbon;

class PersonalCalendarFeedReset
{
    /**
     * When set, that staff member's personal booking calendar hides items created before this instant.
     */
    public static function clearedAtForStaff(?Staff $staff): ?Carbon
    {
        if (! $staff) {
            return null;
        }

        $rows = config('booking_calendar.personal_calendar_cleared', []);
        if (! is_array($rows)) {
            return null;
        }

        $first = strtolower(trim((string) ($staff->first_name ?? '')));
        $last = strtolower(trim((string) ($staff->last_name ?? '')));

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $configuredId = (int) ($row['staff_id'] ?? 0);
            if ($configuredId < 1 || $configuredId !== (int) $staff->id) {
                continue;
            }

            $cfgFirst = strtolower(trim((string) ($row['first_name'] ?? '')));
            $cfgLast = strtolower(trim((string) ($row['last_name'] ?? '')));
            if ($cfgFirst === '' || $cfgLast === '' || $cfgFirst !== $first || $cfgLast !== $last) {
                continue;
            }

            $raw = trim((string) ($row['cleared_at'] ?? ''));
            if ($raw === '') {
                continue;
            }

            try {
                return Carbon::parse($raw, config('app.timezone'));
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }

    public static function clearedAtForStaffId(int $staffId): ?Carbon
    {
        if ($staffId < 1) {
            return null;
        }

        $staff = Staff::query()->where('id', $staffId)->first();

        return self::clearedAtForStaff($staff);
    }
}
