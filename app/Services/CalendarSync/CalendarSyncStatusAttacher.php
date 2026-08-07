<?php

namespace App\Services\CalendarSync;

use App\Models\Staff;
use App\Models\ZohoCalendarEventLink;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

/**
 * Attach lightweight Zoho sync status fields onto calendar JSON rows.
 * Status badges are only exposed to Super Admin (staff must not see Zoho UI).
 */
class CalendarSyncStatusAttacher
{
    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    public function attach(array $rows): array
    {
        if ($rows === [] || ! Schema::hasTable('zoho_calendar_event_links')) {
            return $rows;
        }

        $actor = Auth::guard('admin')->user();
        if (! ($actor instanceof Staff) || ! CalendarSyncMasterControl::canControl($actor)) {
            return $rows;
        }

        $staffIds = [];
        $hearingIds = [];
        $bookingIds = [];

        foreach ($rows as $row) {
            $kind = (string) ($row['event_kind'] ?? '');
            if ($kind === 'staff_event' && ! empty($row['staff_calendar_event_id'])) {
                $staffIds[] = (int) $row['staff_calendar_event_id'];
            } elseif ($kind === 'court_hearing' && ! empty($row['court_hearing_id'])) {
                $hearingIds[] = (int) $row['court_hearing_id'];
            } elseif (($kind === '' || $kind === 'booking') && ! empty($row['crm_appointment_id'])) {
                $bookingIds[] = (int) $row['crm_appointment_id'];
            } elseif (($kind === '' || $kind === 'booking') && isset($row['id']) && is_numeric($row['id'])) {
                $bookingIds[] = (int) $row['id'];
            }
        }

        $links = $this->loadLinks($staffIds, $hearingIds, $bookingIds);

        return array_map(function (array $row) use ($links) {
            $type = null;
            $id = null;
            $kind = (string) ($row['event_kind'] ?? '');

            if ($kind === 'staff_event' && ! empty($row['staff_calendar_event_id'])) {
                $type = ZohoCalendarEventLink::TYPE_STAFF_EVENT;
                $id = (int) $row['staff_calendar_event_id'];
            } elseif ($kind === 'court_hearing' && ! empty($row['court_hearing_id'])) {
                $type = ZohoCalendarEventLink::TYPE_HEARING;
                $id = (int) $row['court_hearing_id'];
            } elseif (! empty($row['crm_appointment_id'])) {
                $type = ZohoCalendarEventLink::TYPE_BOOKING;
                $id = (int) $row['crm_appointment_id'];
            } elseif (($kind === '' || $kind === 'booking') && isset($row['id']) && is_numeric($row['id'])) {
                $type = ZohoCalendarEventLink::TYPE_BOOKING;
                $id = (int) $row['id'];
            }

            if ($type === null || $id === null) {
                $row['zoho_sync_status'] = null;
                $row['zoho_sync_label'] = null;

                return $row;
            }

            $key = $type . ':' . $id;
            /** @var ZohoCalendarEventLink|null $link */
            $link = $links->get($key);
            if (! $link) {
                $row['zoho_sync_status'] = null;
                $row['zoho_sync_label'] = null;

                return $row;
            }

            $row['zoho_sync_status'] = $link->sync_status;
            $row['zoho_sync_label'] = $this->labelFor($link->sync_status);
            $row['zoho_sync_error'] = $link->last_error;
            $row['zoho_event_uid'] = $link->zoho_event_uid;

            return $row;
        }, $rows);
    }

    /**
     * @param  list<int>  $staffIds
     * @param  list<int>  $hearingIds
     * @param  list<int>  $bookingIds
     * @return Collection<string, ZohoCalendarEventLink>
     */
    protected function loadLinks(array $staffIds, array $hearingIds, array $bookingIds): Collection
    {
        $staffIds = array_values(array_unique(array_filter($staffIds)));
        $hearingIds = array_values(array_unique(array_filter($hearingIds)));
        $bookingIds = array_values(array_unique(array_filter($bookingIds)));

        if ($staffIds === [] && $hearingIds === [] && $bookingIds === []) {
            return collect();
        }

        $query = ZohoCalendarEventLink::query()->where(function ($q) use ($staffIds, $hearingIds, $bookingIds) {
            if ($staffIds !== []) {
                $q->orWhere(function ($inner) use ($staffIds) {
                    $inner->where('local_type', ZohoCalendarEventLink::TYPE_STAFF_EVENT)
                        ->whereIn('local_id', $staffIds);
                });
            }
            if ($hearingIds !== []) {
                $q->orWhere(function ($inner) use ($hearingIds) {
                    $inner->where('local_type', ZohoCalendarEventLink::TYPE_HEARING)
                        ->whereIn('local_id', $hearingIds);
                });
            }
            if ($bookingIds !== []) {
                $q->orWhere(function ($inner) use ($bookingIds) {
                    $inner->where('local_type', ZohoCalendarEventLink::TYPE_BOOKING)
                        ->whereIn('local_id', $bookingIds);
                });
            }
        });

        return $query->get()->keyBy(fn (ZohoCalendarEventLink $link) => $link->local_type . ':' . $link->local_id);
    }

    protected function labelFor(?string $status): ?string
    {
        return match ($status) {
            ZohoCalendarEventLink::STATUS_LINKED => 'Synced to Outlook',
            ZohoCalendarEventLink::STATUS_PENDING => 'Zoho pending',
            ZohoCalendarEventLink::STATUS_FAILED => 'Zoho sync failed',
            ZohoCalendarEventLink::STATUS_UNLINKED => 'Unlinked',
            default => $status,
        };
    }
}
