<?php

namespace App\Services\Booking;

use App\Models\Admin;
use App\Models\ClientCourtHearing;
use App\Models\StaffCalendarEvent;
use App\Support\CalendarEventText;
use App\Support\StaffClientVisibility;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class StaffCalendarFeedService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function eventsForCalendarRequest(Request $request): array
    {
        if (! config('booking_calendar.include_important_events', true)) {
            return [];
        }

        $type = (string) $request->get('type', '');
        $startOfToday = Carbon::today(config('app.timezone'));
        $includePast = (bool) config('booking_calendar.include_past_in_visible_range', false);

        $staffEvents = $this->staffEventsPayload($request, $type, $startOfToday, $includePast);
        $courtEvents = $this->courtHearingsPayload($request, $startOfToday, $includePast);

        return array_merge($staffEvents, $courtEvents);
    }

    /**
     * @param  Builder<StaffCalendarEvent>  $query
     */
    public function restrictStaffCalendarEventQuery(Builder $query): void
    {
        StaffClientVisibility::restrictEloquentQueryByClientIdColumn($query, 'client_id');
    }

    public function abortUnlessMayAccessStaffCalendarEvent(StaffCalendarEvent $event): void
    {
        $query = StaffCalendarEvent::query()->whereKey($event->getKey());
        $this->restrictStaffCalendarEventQuery($query);
        if ($query->exists()) {
            return;
        }

        abort(403, 'You do not have access to this calendar event.');
    }

    public function abortUnlessMayAccessCourtHearing(ClientCourtHearing $hearing): void
    {
        if (! StaffClientVisibility::canAccessClientOrLead((int) $hearing->client_id)) {
            abort(403, 'You do not have access to this court hearing.');
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function staffEventsPayload(
        Request $request,
        string $calendarType,
        Carbon $startOfToday,
        bool $includePast
    ): array {
        if (! Schema::hasTable('staff_calendar_events')) {
            return [];
        }

        $query = StaffCalendarEvent::query()->with(['client']);

        if ($calendarType !== '') {
            $query->where(function (Builder $q) use ($calendarType) {
                $q->whereNull('calendar_type')
                    ->orWhere('calendar_type', $calendarType);
            });
        }

        $this->restrictStaffCalendarEventQuery($query);
        $this->applyDatetimeWindow($query, 'starts_at', $request, $startOfToday, $includePast);

        return $query->orderBy('starts_at')->get()
            ->map(fn (StaffCalendarEvent $e) => $this->payloadFromStaffEvent($e))
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function courtHearingsPayload(
        Request $request,
        Carbon $startOfToday,
        bool $includePast
    ): array {
        if (! Schema::hasTable('client_court_hearings')) {
            return [];
        }

        $query = ClientCourtHearing::query()->with(['client']);

        StaffClientVisibility::restrictEloquentQueryByClientIdColumn($query, 'client_id');

        $this->applyHearingDateWindow($query, $request, $startOfToday, $includePast);

        return $query->orderBy('hearing_date')->orderBy('hearing_time')->get()
            ->map(fn (ClientCourtHearing $h) => $this->payloadFromCourtHearing($h))
            ->unique(function (array $row) {
                $start = (string) ($row['starts_at'] ?? '');
                $clientId = (string) ($row['client_id'] ?? '');

                return $clientId . '|' . substr($start, 0, 16);
            })
            ->values()
            ->all();
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     */
    protected function applyDatetimeWindow(
        Builder $query,
        string $column,
        Request $request,
        Carbon $startOfToday,
        bool $includePast
    ): void {
        if ($includePast && $request->filled('start') && $request->filled('end')) {
            try {
                $rangeStart = Carbon::parse($request->get('start'), config('app.timezone'));
                $rangeEnd = Carbon::parse($request->get('end'), config('app.timezone'));
                $query->where($column, '>=', $rangeStart)
                    ->where($column, '<', $rangeEnd);

                return;
            } catch (Exception) {
                // fall through
            }
        }

        $query->where($column, '>=', $startOfToday);
    }

    /**
     * @param  Builder<ClientCourtHearing>  $query
     */
    protected function applyHearingDateWindow(
        Builder $query,
        Request $request,
        Carbon $startOfToday,
        bool $includePast
    ): void {
        if ($includePast && $request->filled('start') && $request->filled('end')) {
            try {
                $rangeStart = Carbon::parse($request->get('start'), config('app.timezone'))->startOfDay();
                $rangeEnd = Carbon::parse($request->get('end'), config('app.timezone'))->startOfDay();
                $query->whereDate('hearing_date', '>=', $rangeStart->toDateString())
                    ->whereDate('hearing_date', '<', $rangeEnd->toDateString());

                return;
            } catch (Exception) {
                // fall through
            }
        }

        $query->whereDate('hearing_date', '>=', $startOfToday->toDateString());
    }

    /**
     * @return array<string, mixed>
     */
    public function payloadFromStaffEvent(StaffCalendarEvent $event): array
    {
        $tz = config('app.timezone');
        $start = $event->starts_at->copy()->timezone($tz);
        $end = $event->ends_at
            ? $event->ends_at->copy()->timezone($tz)
            : $start->copy()->addHour();

        $clientName = $this->clientDisplayName($event->client);
        $encodedClientId = $event->client_id
            ? base64_encode(convert_uuencode((string) $event->client_id))
            : null;

        $title = CalendarEventText::displayStaffTitle((string) $event->title, (string) $event->event_type);
        $location = CalendarEventText::sanitizeLocation($event->location);
        $isAllDay = (bool) $event->is_all_day;

        return [
            'id' => 'staff-cal-' . $event->id,
            'event_kind' => 'staff_event',
            'staff_calendar_event_id' => $event->id,
            'read_only' => false,
            'title' => $title,
            'event_type' => $event->event_type,
            'appointment_datetime' => $start->toIso8601String(),
            'duration_minutes' => max(15, (int) $start->diffInMinutes($end)),
            'starts_at' => $start->toIso8601String(),
            'ends_at' => $end->toIso8601String(),
            'is_all_day' => $isAllDay,
            'calendar_type' => $event->calendar_type,
            'client_id' => $event->client_id,
            'client_id_encoded' => $encodedClientId,
            'client_name' => $clientName,
            'client_email' => $this->clientEmail($event->client),
            'client_matter_id' => $event->client_matter_id,
            'location' => $location,
            'notes' => $event->notes,
            'status'           => $event->event_type,
            'status_label'     => ucfirst(str_replace('_', ' ', $event->event_type)),
            'reminder_minutes' => $event->reminder_minutes,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payloadFromCourtHearing(ClientCourtHearing $hearing): array
    {
        $tz = config('app.timezone');
        $date = $hearing->hearing_date instanceof Carbon
            ? $hearing->hearing_date->copy()
            : Carbon::parse($hearing->hearing_date, $tz);

        if ($hearing->hearing_time) {
            $timeStr = $hearing->hearing_time instanceof \DateTimeInterface
                ? $hearing->hearing_time->format('H:i:s')
                : (string) $hearing->hearing_time;
            $start = Carbon::parse($date->format('Y-m-d') . ' ' . $timeStr, $tz);
        } else {
            $start = $date->copy()->startOfDay()->setTime(9, 0);
        }

        $end = $start->copy()->addMinutes(60);
        $clientName = $this->clientDisplayName($hearing->client);
        $encodedClientId = base64_encode(convert_uuencode((string) $hearing->client_id));

        $typeLabel = CalendarEventText::sanitizeHearingType($hearing->hearing_type);
        $courtName = CalendarEventText::sanitizeLocation($hearing->court_name);
        $courtLabel = $courtName ? ' @ ' . $courtName : '';
        $title = trim(($clientName ?: 'Client') . ' — ' . $typeLabel . $courtLabel);

        return [
            'id' => 'court-' . $hearing->id,
            'event_kind' => 'court_hearing',
            'court_hearing_id' => $hearing->id,
            'read_only' => true,
            'title' => $title,
            'event_type' => 'court',
            'appointment_datetime' => $start->toIso8601String(),
            'duration_minutes' => 60,
            'starts_at' => $start->toIso8601String(),
            'ends_at' => $end->toIso8601String(),
            'is_all_day' => $hearing->hearing_time === null,
            'client_id' => $hearing->client_id,
            'client_id_encoded' => $encodedClientId,
            'client_name' => $clientName,
            'client_email' => $this->clientEmail($hearing->client),
            'client_matter_id' => $hearing->client_matter_id,
            'court_name' => $courtName,
            'case_number' => $hearing->case_number,
            'judge_name' => $hearing->judge_name,
            'hearing_type' => $typeLabel,
            'hearing_status' => $hearing->status,
            'location' => $courtName,
            'notes' => $hearing->notes,
            'status' => 'court',
            'status_label' => $hearing->status ?? 'Scheduled',
            'reminder_minutes' => $hearing->reminder_minutes,
            'reminder_sms_sent_at' => $hearing->reminder_sms_sent_at
                ? Carbon::parse($hearing->reminder_sms_sent_at)->timezone($tz)->toIso8601String()
                : null,
        ];
    }

    protected function clientDisplayName(?Admin $client): ?string
    {
        if (! $client) {
            return null;
        }

        $name = trim(($client->first_name ?? '') . ' ' . ($client->last_name ?? ''));

        return $name !== '' ? $name : ($client->client_id ?? 'Client #' . $client->id);
    }

    protected function clientEmail(?Admin $client): ?string
    {
        if (! $client) {
            return null;
        }

        $email = trim((string) ($client->email ?? ''));

        return $email !== '' ? $email : null;
    }

    public static function colorForEventType(string $type): string
    {
        return match ($type) {
            'court' => '#5c3d8f',
            'meeting' => '#0d6efd',
            'deadline' => '#c0392b',
            'reminder' => '#d97706',
            default => '#5E7A90',
        };
    }

    public static function textColorForEventType(string $type): string
    {
        return $type === 'reminder' ? '#1A2C40' : '#fff';
    }
}
