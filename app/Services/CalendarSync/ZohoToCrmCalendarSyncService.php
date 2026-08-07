<?php

namespace App\Services\CalendarSync;

use App\Models\ClientMatter;
use App\Models\StaffCalendarEvent;
use App\Models\ZohoCalendarConnection;
use App\Models\ZohoCalendarEventLink;
use App\Models\ZohoCalendarStaffMap;
use App\Models\ZohoCalendarUnlinkedEvent;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Pull events from Zoho into CRM: refresh existing links, auto-match by file number,
 * otherwise queue in unlinked calendar list (like Unassigned mail).
 */
class ZohoToCrmCalendarSyncService
{
    public function __construct(
        protected ZohoCalendarApiClient $api,
        protected ZohoCalendarOAuthService $oauth,
        protected CrmToZohoCalendarSyncService $outbound
    ) {}

    /**
     * @return array{
     *   scanned: int,
     *   linked_seen: int,
     *   auto_linked: int,
     *   unlinked_queued: int,
     *   unlinked_updated: int,
     *   outbound_retried: int,
     *   errors: list<string>
     * }
     */
    public function syncAll(?int $daysBack = null, ?int $daysForward = null): array
    {
        $summary = [
            'scanned' => 0,
            'linked_seen' => 0,
            'auto_linked' => 0,
            'unlinked_queued' => 0,
            'unlinked_updated' => 0,
            'outbound_retried' => 0,
            'errors' => [],
        ];

        if (CalendarSyncMasterControl::isDisabled()) {
            $summary['errors'][] = CalendarSyncMasterControl::disabledMessage();

            return $summary;
        }

        if (! $this->oauth->isConfigured()) {
            $summary['errors'][] = 'Zoho OAuth app credentials (client id/secret) are not configured in .env.';

            return $summary;
        }

        $accounts = $this->accountsToPull();
        if ($accounts === []) {
            $summary['errors'][] = 'No staff calendar credentials with Connect + sync ON. Add staff under Calendar Sync → Staff calendar credentials.';

            return $summary;
        }

        $daysBack = $daysBack ?? max(1, (int) config('zoho_calendar.pull_days_back', 7));
        $daysForward = $daysForward ?? max(1, (int) config('zoho_calendar.pull_days_forward', 60));
        $tz = (string) config('zoho_calendar.timezone', config('app.timezone', 'Australia/Melbourne'));
        $rangeStart = Carbon::now($tz)->subDays($daysBack)->startOfDay();
        $rangeEnd = Carbon::now($tz)->addDays($daysForward)->endOfDay();

        foreach ($accounts as $account) {
            /** @var ZohoCalendarConnection $connection */
            $connection = $account['connection'];
            $calendarUid = $account['calendar_uid'];
            $staffId = $account['staff_id'];

            try {
                $events = $this->api->listEvents($connection, $calendarUid, $rangeStart, $rangeEnd);
            } catch (\Throwable $e) {
                $summary['errors'][] = 'List events failed for staff #' . $staffId . ' calendar ' . $calendarUid . ': ' . $e->getMessage();
                ZohoCalendarStaffMap::query()->where('staff_id', $staffId)->update([
                    'last_error' => \Illuminate\Support\Str::limit($e->getMessage(), 1000, '...'),
                ]);
                Log::warning('ZohoToCrmCalendarSyncService list failed', [
                    'staff_id' => $staffId,
                    'calendar_uid' => $calendarUid,
                    'error' => $e->getMessage(),
                ]);
                continue;
            }

            ZohoCalendarStaffMap::query()->where('staff_id', $staffId)->update([
                'last_synced_at' => now(),
                'last_error' => null,
            ]);

            foreach ($events as $remote) {
                $summary['scanned']++;
                try {
                    $result = $this->processRemoteEvent($remote, $calendarUid, $staffId);
                    if ($result === 'linked_seen') {
                        $summary['linked_seen']++;
                    } elseif ($result === 'auto_linked') {
                        $summary['auto_linked']++;
                    } elseif ($result === 'queued') {
                        $summary['unlinked_queued']++;
                    } elseif ($result === 'updated_queue') {
                        $summary['unlinked_updated']++;
                    }
                } catch (\Throwable $e) {
                    $summary['errors'][] = 'Event ' . ($remote['uid'] ?? '?') . ': ' . $e->getMessage();
                }
            }
        }

        $summary['outbound_retried'] = $this->retryFailedOutbound();

        return $summary;
    }

    /**
     * @return list<array{staff_id: int, calendar_uid: string, connection: ZohoCalendarConnection}>
     */
    protected function accountsToPull(): array
    {
        $maps = ZohoCalendarStaffMap::query()
            ->with('connection')
            ->where('sync_enabled', true)
            ->orderByDesc('is_org_default')
            ->orderBy('id')
            ->get();

        $out = [];
        $seenCalendars = [];

        foreach ($maps as $map) {
            $connection = $map->connection;
            $calendarUid = $map->effectiveCalendarUid();
            if (! $connection || ! filled($connection->access_token) || ! filled($calendarUid)) {
                continue;
            }
            $key = (int) $map->staff_id . ':' . $calendarUid;
            if (isset($seenCalendars[$key])) {
                continue;
            }
            $seenCalendars[$key] = true;
            $out[] = [
                'staff_id' => (int) $map->staff_id,
                'calendar_uid' => (string) $calendarUid,
                'connection' => $connection,
            ];
        }

        // Legacy connections without a map
        if ($out === []) {
            foreach (ZohoCalendarConnection::query()->orderBy('id')->get() as $connection) {
                if (! filled($connection->access_token) || ! filled($connection->default_calendar_uid)) {
                    continue;
                }
                $out[] = [
                    'staff_id' => (int) $connection->staff_id,
                    'calendar_uid' => (string) $connection->default_calendar_uid,
                    'connection' => $connection,
                ];
            }
        }

        return $out;
    }

    /**
     * @deprecated use accountsToPull
     * @return list<string>
     */
    protected function calendarsToPull(ZohoCalendarConnection $connection): array
    {
        $uid = $connection->default_calendar_uid;

        return filled($uid) ? [(string) $uid] : [];
    }

    /**
     * @param  array{
     *   uid: string,
     *   etag: string|null,
     *   title: string|null,
     *   description: string|null,
     *   location: string|null,
     *   starts_at: Carbon|null,
     *   ends_at: Carbon|null,
     *   is_all_day: bool,
     *   raw: array
     * }  $remote
     * @return 'linked_seen'|'auto_linked'|'queued'|'updated_queue'|'skipped'
     */
    protected function processRemoteEvent(array $remote, string $calendarUid, ?int $ownerStaffId = null): string
    {
        $uid = $remote['uid'];
        $existingLink = ZohoCalendarEventLink::query()
            ->where('zoho_event_uid', $uid)
            ->first();

        if ($existingLink) {
            $existingLink->etag = $remote['etag'] ?: $existingLink->etag;
            $existingLink->zoho_calendar_uid = $calendarUid;
            if ($ownerStaffId && ! $existingLink->staff_id) {
                $existingLink->staff_id = $ownerStaffId;
            }
            $existingLink->last_synced_at = now();
            if ($existingLink->sync_status !== ZohoCalendarEventLink::STATUS_LINKED) {
                $existingLink->sync_status = ZohoCalendarEventLink::STATUS_LINKED;
                $existingLink->last_error = null;
            }
            $existingLink->save();

            ZohoCalendarUnlinkedEvent::query()
                ->where('zoho_event_uid', $uid)
                ->where('status', ZohoCalendarUnlinkedEvent::STATUS_OPEN)
                ->update([
                    'status' => ZohoCalendarUnlinkedEvent::STATUS_LINKED,
                    'linked_local_type' => $existingLink->local_type,
                    'linked_local_id' => $existingLink->local_id,
                    'resolved_at' => now(),
                    'last_seen_at' => now(),
                ]);

            return 'linked_seen';
        }

        $parsed = CalendarEventTitleParser::parse($remote['title'] ?? '');
        $auto = $this->tryAutoLinkFromFileRef($remote, $calendarUid, $parsed, $ownerStaffId);
        if ($auto) {
            return 'auto_linked';
        }

        return $this->upsertUnlinked($remote, $calendarUid, $parsed);
    }

    /**
     * @param  array{file_ref: string|null, matter_ref: string|null, title: string}  $parsed
     */
    protected function tryAutoLinkFromFileRef(array $remote, string $calendarUid, array $parsed, ?int $ownerStaffId = null): bool
    {
        $fileRef = $parsed['file_ref'];
        if (! $fileRef || ! ctype_digit($fileRef)) {
            return false;
        }

        $clientId = (int) $fileRef;
        $starts = $remote['starts_at'];
        if (! $starts instanceof Carbon) {
            return false;
        }

        // Find a matching staff event with same client near the same start (±2 hours)
        $windowStart = $starts->copy()->subHours(2);
        $windowEnd = $starts->copy()->addHours(2);

        $query = StaffCalendarEvent::query()
            ->where('client_id', $clientId)
            ->whereBetween('starts_at', [$windowStart, $windowEnd]);

        if ($ownerStaffId) {
            $query->where(function ($q) use ($ownerStaffId) {
                $q->where('created_by_staff_id', $ownerStaffId)
                    ->orWhereNull('created_by_staff_id');
            });
        }

        $match = $query->orderBy('starts_at')->first();

        if (! $match) {
            return false;
        }

        // Already linked to something else?
        $occupied = ZohoCalendarEventLink::query()
            ->where('local_type', ZohoCalendarEventLink::TYPE_STAFF_EVENT)
            ->where('local_id', $match->id)
            ->first();
        if ($occupied && $occupied->zoho_event_uid && $occupied->zoho_event_uid !== $remote['uid']) {
            return false;
        }

        $link = $occupied ?: new ZohoCalendarEventLink([
            'local_type' => ZohoCalendarEventLink::TYPE_STAFF_EVENT,
            'local_id' => $match->id,
        ]);
        $link->fill([
            'staff_id' => $match->created_by_staff_id,
            'client_id' => $match->client_id,
            'client_matter_id' => $match->client_matter_id,
            'file_ref' => $parsed['file_ref'],
            'matter_ref' => $parsed['matter_ref'],
            'zoho_event_uid' => $remote['uid'],
            'zoho_calendar_uid' => $calendarUid,
            'etag' => $remote['etag'],
            'sync_status' => ZohoCalendarEventLink::STATUS_LINKED,
            'direction' => ZohoCalendarEventLink::DIRECTION_ZOHO_TO_CRM,
            'last_error' => null,
            'last_synced_at' => now(),
        ]);
        $link->save();

        ZohoCalendarUnlinkedEvent::query()
            ->where('zoho_event_uid', $remote['uid'])
            ->where('status', ZohoCalendarUnlinkedEvent::STATUS_OPEN)
            ->update([
                'status' => ZohoCalendarUnlinkedEvent::STATUS_LINKED,
                'linked_local_type' => ZohoCalendarEventLink::TYPE_STAFF_EVENT,
                'linked_local_id' => $match->id,
                'resolved_at' => now(),
                'last_seen_at' => now(),
            ]);

        return true;
    }

    /**
     * @param  array{file_ref: string|null, matter_ref: string|null, title: string}  $parsed
     * @return 'queued'|'updated_queue'
     */
    protected function upsertUnlinked(array $remote, string $calendarUid, array $parsed): string
    {
        $row = ZohoCalendarUnlinkedEvent::query()->firstOrNew([
            'zoho_event_uid' => $remote['uid'],
            'zoho_calendar_uid' => $calendarUid,
        ]);

        $wasNew = ! $row->exists || $row->status === ZohoCalendarUnlinkedEvent::STATUS_DISMISSED;

        // Do not re-open dismissed automatically
        if ($row->exists && $row->status === ZohoCalendarUnlinkedEvent::STATUS_DISMISSED) {
            $row->last_seen_at = now();
            $row->save();

            return 'updated_queue';
        }

        if ($row->exists && $row->status === ZohoCalendarUnlinkedEvent::STATUS_LINKED) {
            $row->last_seen_at = now();
            $row->save();

            return 'updated_queue';
        }

        $row->fill([
            'title' => $remote['title'],
            'description' => $remote['description'],
            'location' => $remote['location'],
            'starts_at' => $remote['starts_at'],
            'ends_at' => $remote['ends_at'],
            'is_all_day' => (bool) $remote['is_all_day'],
            'etag' => $remote['etag'],
            'raw_payload' => $remote['raw'] ?? null,
            'parsed_file_ref' => $parsed['file_ref'],
            'parsed_matter_ref' => $parsed['matter_ref'],
            'status' => ZohoCalendarUnlinkedEvent::STATUS_OPEN,
            'last_seen_at' => now(),
            'last_error' => null,
        ]);
        $row->save();

        return $wasNew ? 'queued' : 'updated_queue';
    }

    /**
     * Retry failed/pending CRM→Zoho pushes (best effort).
     */
    protected function retryFailedOutbound(): int
    {
        if (! Schema::hasTable('zoho_calendar_event_links')) {
            return 0;
        }

        $links = ZohoCalendarEventLink::query()
            ->whereIn('sync_status', [
                ZohoCalendarEventLink::STATUS_PENDING,
                ZohoCalendarEventLink::STATUS_FAILED,
            ])
            ->where('direction', ZohoCalendarEventLink::DIRECTION_CRM_TO_ZOHO)
            ->orderBy('id')
            ->limit(50)
            ->get();

        $count = 0;
        foreach ($links as $link) {
            try {
                if ($link->local_type === ZohoCalendarEventLink::TYPE_STAFF_EVENT) {
                    $event = StaffCalendarEvent::query()->find($link->local_id);
                    if ($event) {
                        $this->outbound->pushStaffEvent($event);
                        $count++;
                    }
                } elseif ($link->local_type === ZohoCalendarEventLink::TYPE_HEARING) {
                    $hearing = \App\Models\ClientCourtHearing::query()->find($link->local_id);
                    if ($hearing) {
                        $this->outbound->pushHearing($hearing);
                        $count++;
                    }
                } elseif ($link->local_type === ZohoCalendarEventLink::TYPE_BOOKING) {
                    $booking = \App\Models\BookingAppointment::query()->find($link->local_id);
                    if ($booking) {
                        $this->outbound->pushBooking($booking);
                        $count++;
                    }
                }
            } catch (\Throwable $e) {
                Log::debug('Outbound retry failed', ['link_id' => $link->id, 'error' => $e->getMessage()]);
            }
        }

        return $count;
    }

    public static function openUnlinkedCount(): int
    {
        if (! Schema::hasTable('zoho_calendar_unlinked_events')) {
            return 0;
        }

        return (int) ZohoCalendarUnlinkedEvent::query()->open()->count();
    }
}
