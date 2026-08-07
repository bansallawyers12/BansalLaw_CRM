<?php

namespace App\Services\CalendarSync;

use App\Models\Admin;
use App\Models\ClientMatter;
use App\Models\Staff;
use App\Models\StaffCalendarEvent;
use App\Models\ZohoCalendarEventLink;
use App\Models\ZohoCalendarUnlinkedEvent;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Resolve unlinked Zoho events into CRM staff calendar events.
 */
class UnlinkedCalendarAssignmentService
{
    public function assignToClient(
        ZohoCalendarUnlinkedEvent $unlinked,
        int $clientId,
        ?int $clientMatterId,
        Staff $actor,
        ?string $eventType = 'meeting'
    ): StaffCalendarEvent {
        if ($unlinked->status !== ZohoCalendarUnlinkedEvent::STATUS_OPEN) {
            throw new RuntimeException('This unlinked event is already resolved.');
        }

        $client = Admin::query()->find($clientId);
        if (! $client) {
            throw new RuntimeException('Client not found.');
        }

        if ($clientMatterId) {
            $matter = ClientMatter::query()
                ->where('id', $clientMatterId)
                ->where('client_id', $clientId)
                ->first();
            if (! $matter) {
                throw new RuntimeException('Matter does not belong to this client.');
            }
        }

        $starts = $unlinked->starts_at instanceof Carbon
            ? $unlinked->starts_at->copy()
            : Carbon::now(config('app.timezone'));
        $ends = $unlinked->ends_at instanceof Carbon
            ? $unlinked->ends_at->copy()
            : $starts->copy()->addHour();

        $parsedTitle = CalendarEventTitleParser::parse($unlinked->title);
        $cleanTitle = $parsedTitle['title'] !== '' ? $parsedTitle['title'] : ($unlinked->title ?: 'Zoho event');

        return DB::transaction(function () use (
            $unlinked,
            $clientId,
            $clientMatterId,
            $actor,
            $eventType,
            $starts,
            $ends,
            $cleanTitle,
            $parsedTitle
        ) {
            $event = StaffCalendarEvent::create([
                'title' => $cleanTitle,
                'event_type' => in_array($eventType, StaffCalendarEvent::TYPES, true) ? $eventType : 'meeting',
                'starts_at' => $starts,
                'ends_at' => $ends,
                'is_all_day' => (bool) $unlinked->is_all_day,
                'client_id' => $clientId,
                'client_matter_id' => $clientMatterId,
                'location' => $unlinked->location,
                'notes' => $unlinked->description,
                'created_by_staff_id' => $actor->id,
            ]);

            ZohoCalendarEventLink::query()->updateOrCreate(
                [
                    'local_type' => ZohoCalendarEventLink::TYPE_STAFF_EVENT,
                    'local_id' => $event->id,
                ],
                [
                    'staff_id' => $actor->id,
                    'client_id' => $clientId,
                    'client_matter_id' => $clientMatterId,
                    'file_ref' => $parsedTitle['file_ref'] ?: (string) $clientId,
                    'matter_ref' => $parsedTitle['matter_ref'],
                    'zoho_event_uid' => $unlinked->zoho_event_uid,
                    'zoho_calendar_uid' => $unlinked->zoho_calendar_uid,
                    'etag' => $unlinked->etag,
                    'sync_status' => ZohoCalendarEventLink::STATUS_LINKED,
                    'direction' => ZohoCalendarEventLink::DIRECTION_ZOHO_TO_CRM,
                    'last_error' => null,
                    'last_synced_at' => now(),
                ]
            );

            $unlinked->status = ZohoCalendarUnlinkedEvent::STATUS_LINKED;
            $unlinked->linked_local_type = ZohoCalendarEventLink::TYPE_STAFF_EVENT;
            $unlinked->linked_local_id = $event->id;
            $unlinked->resolved_by_staff_id = $actor->id;
            $unlinked->resolved_at = now();
            $unlinked->save();

            return $event;
        });
    }

    public function dismiss(ZohoCalendarUnlinkedEvent $unlinked, Staff $actor): void
    {
        if ($unlinked->status !== ZohoCalendarUnlinkedEvent::STATUS_OPEN) {
            return;
        }

        $unlinked->status = ZohoCalendarUnlinkedEvent::STATUS_DISMISSED;
        $unlinked->resolved_by_staff_id = $actor->id;
        $unlinked->resolved_at = now();
        $unlinked->save();
    }
}
