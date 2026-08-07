<?php

namespace App\Services\CalendarSync;

use App\Models\BookingAppointment;
use App\Models\ClientCourtHearing;
use App\Models\StaffCalendarEvent;
use App\Models\ZohoCalendarConnection;
use App\Models\ZohoCalendarEventLink;
use App\Models\ZohoCalendarStaffMap;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Push / update / remove CRM calendar items on Zoho Calendar.
 */
class CrmToZohoCalendarSyncService
{
    public function __construct(
        protected ZohoCalendarApiClient $api,
        protected ZohoCalendarOAuthService $oauth
    ) {}

    public function pushStaffEvent(StaffCalendarEvent $event): ZohoCalendarEventLink
    {
        $event->loadMissing(['matter', 'client']);
        $refs = CalendarEventTitleBuilder::refsForStaffEvent($event);
        $title = CalendarEventTitleBuilder::forStaffEvent($event);

        return $this->push(
            localType: ZohoCalendarEventLink::TYPE_STAFF_EVENT,
            localId: (int) $event->id,
            staffId: $event->created_by_staff_id ? (int) $event->created_by_staff_id : null,
            clientId: $event->client_id ? (int) $event->client_id : null,
            clientMatterId: $event->client_matter_id ? (int) $event->client_matter_id : null,
            fileRef: $refs['file_ref'],
            matterRef: $refs['matter_ref'],
            title: $title,
            eventData: $this->api->buildEventDataFromStaffEvent($event)
        );
    }

    public function pushHearing(ClientCourtHearing $hearing): ZohoCalendarEventLink
    {
        $hearing->loadMissing(['matter', 'client']);
        $refs = CalendarEventTitleBuilder::refsForHearing($hearing);
        $title = CalendarEventTitleBuilder::forHearing($hearing);

        return $this->push(
            localType: ZohoCalendarEventLink::TYPE_HEARING,
            localId: (int) $hearing->id,
            staffId: null,
            clientId: $hearing->client_id ? (int) $hearing->client_id : null,
            clientMatterId: $hearing->client_matter_id ? (int) $hearing->client_matter_id : null,
            fileRef: $refs['file_ref'],
            matterRef: $refs['matter_ref'],
            title: $title,
            eventData: $this->api->buildEventDataFromHearing($hearing)
        );
    }

    public function pushBooking(BookingAppointment $appointment): ZohoCalendarEventLink
    {
        $refs = CalendarEventTitleBuilder::refsForBooking($appointment);
        $title = CalendarEventTitleBuilder::forBooking($appointment);
        $staffId = $appointment->assigned_by_admin_id ? (int) $appointment->assigned_by_admin_id : null;

        return $this->push(
            localType: ZohoCalendarEventLink::TYPE_BOOKING,
            localId: (int) $appointment->id,
            staffId: $staffId,
            clientId: $appointment->client_id ? (int) $appointment->client_id : null,
            clientMatterId: null,
            fileRef: $refs['file_ref'],
            matterRef: $refs['matter_ref'],
            title: $title,
            eventData: $this->api->buildEventDataFromBooking($appointment)
        );
    }

    public function deleteStaffEvent(StaffCalendarEvent $event): void
    {
        $this->deleteLocal(ZohoCalendarEventLink::TYPE_STAFF_EVENT, (int) $event->id, $event->created_by_staff_id ? (int) $event->created_by_staff_id : null);
    }

    public function deleteHearing(ClientCourtHearing $hearing): void
    {
        $this->deleteLocal(ZohoCalendarEventLink::TYPE_HEARING, (int) $hearing->id, null);
    }

    public function deleteBooking(BookingAppointment $appointment): void
    {
        $staffId = $appointment->assigned_by_admin_id ? (int) $appointment->assigned_by_admin_id : null;
        $this->deleteLocal(ZohoCalendarEventLink::TYPE_BOOKING, (int) $appointment->id, $staffId);
    }

    /**
     * @param  array<string, mixed>  $eventData
     */
    protected function push(
        string $localType,
        int $localId,
        ?int $staffId,
        ?int $clientId,
        ?int $clientMatterId,
        ?string $fileRef,
        ?string $matterRef,
        string $title,
        array $eventData
    ): ZohoCalendarEventLink {
        $link = ZohoCalendarEventLink::query()->firstOrNew([
            'local_type' => $localType,
            'local_id' => $localId,
        ]);

        $link->fill([
            'staff_id' => $staffId,
            'client_id' => $clientId,
            'client_matter_id' => $clientMatterId,
            'file_ref' => $fileRef,
            'matter_ref' => $matterRef,
            'direction' => ZohoCalendarEventLink::DIRECTION_CRM_TO_ZOHO,
        ]);

        if (CalendarSyncMasterControl::isDisabled()) {
            $link->sync_status = ZohoCalendarEventLink::STATUS_PENDING;
            $link->last_error = CalendarSyncMasterControl::disabledMessage();
            $link->save();

            return $link;
        }

        if (! $this->oauth->isConfigured()) {
            $link->sync_status = ZohoCalendarEventLink::STATUS_PENDING;
            $link->last_error = 'Zoho OAuth app credentials are not set (ZOHO_CALENDAR_CLIENT_ID / SECRET). Title would be: ' . $title;
            $link->save();

            return $link;
        }

        $resolved = $this->resolveConnectionAndCalendar($staffId);
        if ($resolved === null) {
            $link->sync_status = ZohoCalendarEventLink::STATUS_PENDING;
            $link->last_error = 'No Zoho connection / calendar map. Super Admin must Connect Zoho and set a default calendar (or enable a staff map). Title: ' . $title;
            $link->save();

            return $link;
        }

        [$connection, $calendarUid] = $resolved;

        try {
            if ($link->zoho_event_uid && $link->zoho_calendar_uid) {
                $result = $this->api->updateEvent(
                    $connection,
                    $link->zoho_calendar_uid,
                    $link->zoho_event_uid,
                    $eventData,
                    $link->etag
                );
            } else {
                $result = $this->api->createEvent($connection, $calendarUid, $eventData);
                $link->zoho_calendar_uid = $calendarUid;
            }

            if (! empty($result['uid'])) {
                $link->zoho_event_uid = $result['uid'];
            }
            if (! empty($result['etag'])) {
                $link->etag = $result['etag'];
            }
            if (! $link->zoho_calendar_uid) {
                $link->zoho_calendar_uid = $calendarUid;
            }

            $link->sync_status = ZohoCalendarEventLink::STATUS_LINKED;
            $link->last_error = null;
            $link->last_synced_at = now();
            $link->save();

            $this->touchStaffMapSuccess($staffId);

            Log::info('CrmToZohoCalendarSyncService: synced', [
                'local_type' => $localType,
                'local_id' => $localId,
                'zoho_event_uid' => $link->zoho_event_uid,
                'title' => $title,
            ]);
        } catch (\Throwable $e) {
            $link->sync_status = ZohoCalendarEventLink::STATUS_FAILED;
            $link->last_error = $e->getMessage();
            $link->save();

            $this->touchStaffMapError($staffId, $e->getMessage());

            Log::warning('CrmToZohoCalendarSyncService: push failed', [
                'local_type' => $localType,
                'local_id' => $localId,
                'error' => $e->getMessage(),
            ]);
        }

        return $link;
    }

    protected function deleteLocal(string $localType, int $localId, ?int $staffId): void
    {
        if (CalendarSyncMasterControl::isDisabled()) {
            return;
        }

        $link = ZohoCalendarEventLink::query()
            ->where('local_type', $localType)
            ->where('local_id', $localId)
            ->first();

        if (! $link || ! $link->zoho_event_uid || ! $link->zoho_calendar_uid) {
            if ($link) {
                $link->delete();
            }

            return;
        }

        $connection = $this->connectionForStaff($staffId)
            ?? ZohoCalendarConnection::query()->orderBy('id')->first();

        if (! $connection) {
            $link->sync_status = ZohoCalendarEventLink::STATUS_UNLINKED;
            $link->last_error = 'Deleted in CRM; no Zoho connection to remove remote event.';
            $link->save();

            return;
        }

        try {
            $this->api->deleteEvent(
                $connection,
                $link->zoho_calendar_uid,
                $link->zoho_event_uid,
                $link->etag
            );
            $link->delete();
        } catch (\Throwable $e) {
            $link->sync_status = ZohoCalendarEventLink::STATUS_FAILED;
            $link->last_error = 'CRM deleted event; Zoho delete failed: ' . $e->getMessage();
            $link->save();
            Log::warning('CrmToZohoCalendarSyncService: delete failed', [
                'local_type' => $localType,
                'local_id' => $localId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Prefer the staff member's own Zoho connection when they have sync enabled.
     * Fall back to an org-default staff calendar for hearings / unassigned staff id.
     *
     * @return array{0: ZohoCalendarConnection, 1: string}|null
     */
    protected function resolveConnectionAndCalendar(?int $staffId): ?array
    {
        if ($staffId) {
            $map = ZohoCalendarStaffMap::query()
                ->with('connection')
                ->where('staff_id', $staffId)
                ->where('sync_enabled', true)
                ->first();

            if ($map) {
                $connection = $map->connection;
                $calendarUid = $map->effectiveCalendarUid();
                if ($connection && filled($connection->access_token) && filled($calendarUid)) {
                    return [$connection, (string) $calendarUid];
                }

                // Own credentials incomplete — do not steal another staff token.
                return null;
            }

            // No map for this staff: fall through to org default only.
        }

        $org = ZohoCalendarStaffMap::query()
            ->with('connection')
            ->where('sync_enabled', true)
            ->where('is_org_default', true)
            ->orderBy('id')
            ->first();

        if (! $org) {
            // Legacy: first connected staff with a calendar.
            $org = ZohoCalendarStaffMap::query()
                ->with('connection')
                ->where('sync_enabled', true)
                ->orderBy('id')
                ->get()
                ->first(fn (ZohoCalendarStaffMap $m) => $m->isConnected() && filled($m->effectiveCalendarUid()));
        }

        if ($org) {
            $connection = $org->connection;
            $calendarUid = $org->effectiveCalendarUid();
            if ($connection && filled($connection->access_token) && filled($calendarUid)) {
                return [$connection, (string) $calendarUid];
            }
        }

        // Pure legacy connection without map row
        $connection = $staffId
            ? ($this->connectionForStaff($staffId))
            : null;
        $connection = $connection ?? ZohoCalendarConnection::query()->orderBy('id')->first();
        if ($connection && filled($connection->default_calendar_uid) && filled($connection->access_token)) {
            return [$connection, (string) $connection->default_calendar_uid];
        }

        return null;
    }

    protected function connectionForStaff(?int $staffId): ?ZohoCalendarConnection
    {
        if (! $staffId) {
            return null;
        }

        return ZohoCalendarConnection::query()->where('staff_id', $staffId)->first();
    }

    protected function touchStaffMapSuccess(?int $staffId): void
    {
        if (! $staffId) {
            return;
        }

        ZohoCalendarStaffMap::query()
            ->where('staff_id', $staffId)
            ->update([
                'last_synced_at' => now(),
                'last_error' => null,
            ]);
    }

    protected function touchStaffMapError(?int $staffId, string $error): void
    {
        if (! $staffId) {
            return;
        }

        ZohoCalendarStaffMap::query()
            ->where('staff_id', $staffId)
            ->update([
                'last_error' => Str::limit($error, 1000, '...'),
            ]);
    }
}
