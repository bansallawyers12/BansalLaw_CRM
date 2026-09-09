<?php

namespace App\Console\Commands;

use App\Models\Staff;
use App\Models\StaffCalendarEvent;
use App\Support\PersonalCalendarFeedReset;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class ClearPersonalCalendarLegacyItems extends Command
{
    protected $signature = 'booking:clear-personal-calendar-legacy
                            {--staff= : Staff id to clear (defaults to configured personal_calendar_cleared entries)}
                            {--dry-run : Show counts without updating}';

    protected $description = 'Cancel existing personal calendar events for staff with a personal_calendar_cleared config entry (Khushi by default)';

    public function handle(): int
    {
        if (! Schema::hasTable('staff_calendar_events')) {
            $this->warn('staff_calendar_events table missing.');

            return self::FAILURE;
        }

        $onlyId = (int) $this->option('staff');
        $rows = config('booking_calendar.personal_calendar_cleared', []);
        if (! is_array($rows) || $rows === []) {
            $this->warn('No booking_calendar.personal_calendar_cleared entries configured.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $totalCancelled = 0;

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $staffId = (int) ($row['staff_id'] ?? 0);
            if ($staffId < 1 || ($onlyId > 0 && $onlyId !== $staffId)) {
                continue;
            }

            $staff = Staff::query()->where('id', $staffId)->first();
            $clearedAt = PersonalCalendarFeedReset::clearedAtForStaff($staff);
            if (! $staff || ! $clearedAt) {
                $this->warn("Skipping staff #{$staffId}: name/id does not match personal_calendar_cleared config.");

                continue;
            }

            $query = StaffCalendarEvent::query()
                ->where('created_by_staff_id', $staff->id)
                ->where('created_at', '<', $clearedAt);

            if (Schema::hasColumn('staff_calendar_events', 'status')) {
                $query->whereNotIn('status', ['cancelled', 'completed']);
            }

            $count = (int) $query->count();
            $label = trim(($staff->first_name ?? '') . ' ' . ($staff->last_name ?? '')) ?: ('#' . $staff->id);
            $this->info("{$label} (id {$staff->id}): {$count} active event(s) created before {$clearedAt->toDateTimeString()}");

            if ($dryRun || $count < 1) {
                continue;
            }

            if (Schema::hasColumn('staff_calendar_events', 'status')) {
                $updated = (int) $query->update(['status' => 'cancelled']);
            } else {
                $updated = (int) $query->delete();
            }

            $totalCancelled += $updated;
            $this->info("  → cancelled/removed {$updated}");
        }

        if ($dryRun) {
            $this->comment('Dry run only — no changes written.');
        } else {
            $this->info("Done. Total cancelled/removed: {$totalCancelled}");
        }

        return self::SUCCESS;
    }
}
