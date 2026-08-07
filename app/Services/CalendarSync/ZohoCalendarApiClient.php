<?php

namespace App\Services\CalendarSync;

use App\Models\StaffCalendarEvent;
use App\Models\ZohoCalendarConnection;
use App\Models\BookingAppointment;
use App\Models\ClientCourtHearing;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Thin Zoho Calendar HTTP client.
 * Authorization uses Zoho-oauthtoken (standard for Zoho resource APIs).
 */
class ZohoCalendarApiClient
{
    public function __construct(
        protected ZohoCalendarOAuthService $oauth
    ) {}

    /**
     * @return list<array{uid: string, name: string, is_default: bool, privilege?: string}>
     */
    public function listCalendars(ZohoCalendarConnection $connection): array
    {
        $token = $this->oauth->validAccessToken($connection);
        $url = $this->calendarBase($connection) . '/calendars';

        $response = Http::withHeaders($this->authHeaders($token))
            ->timeout(30)
            ->get($url);

        if (! $response->successful()) {
            throw new RuntimeException('Zoho list calendars failed (' . $response->status() . '): ' . $this->shortBody($response->body()));
        }

        $json = $response->json();
        $rows = $json['calendars'] ?? $json['Calendar']['calendars'] ?? $json ?? [];
        if (! is_array($rows)) {
            return [];
        }

        // Some payloads wrap as calendars.calendar
        if (isset($rows['calendar']) && is_array($rows['calendar'])) {
            $rows = $rows['calendar'];
        }
        if ($rows !== [] && array_is_list($rows) === false && isset($rows['uid'])) {
            $rows = [$rows];
        }

        $out = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $uid = (string) ($row['uid'] ?? $row['calendar_uid'] ?? $row['id'] ?? '');
            if ($uid === '') {
                continue;
            }
            $out[] = [
                'uid' => $uid,
                'name' => (string) ($row['name'] ?? $row['calendar_name'] ?? $uid),
                'is_default' => (bool) ($row['isdefault'] ?? $row['is_default'] ?? false),
                'privilege' => isset($row['privilege']) ? (string) $row['privilege'] : null,
            ];
        }

        return $out;
    }

    /**
     * List events in a calendar between two instants (inclusive range for Zoho API).
     *
     * @return list<array{
     *   uid: string,
     *   etag: string|null,
     *   title: string|null,
     *   description: string|null,
     *   location: string|null,
     *   starts_at: \Carbon\Carbon|null,
     *   ends_at: \Carbon\Carbon|null,
     *   is_all_day: bool,
     *   raw: array
     * }>
     */
    public function listEvents(
        ZohoCalendarConnection $connection,
        string $calendarUid,
        Carbon $rangeStart,
        Carbon $rangeEnd
    ): array {
        $token = $this->oauth->validAccessToken($connection);
        $url = $this->calendarBase($connection) . '/calendars/' . rawurlencode($calendarUid) . '/events';

        $range = [
            'start' => $rangeStart->copy()->utc()->format('Ymd\THis\Z'),
            'end' => $rangeEnd->copy()->utc()->format('Ymd\THis\Z'),
        ];

        $response = Http::withHeaders($this->authHeaders($token))
            ->timeout(60)
            ->get($url, [
                'range' => json_encode($range, JSON_UNESCAPED_SLASHES),
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Zoho list events failed (' . $response->status() . '): ' . $this->shortBody($response->body()));
        }

        $json = $response->json() ?? [];
        $rows = $json['events'] ?? $json['event'] ?? [];
        if (! is_array($rows)) {
            return [];
        }
        if (isset($rows[0]) === false && (isset($rows['uid']) || isset($rows['title']))) {
            $rows = [$rows];
        }

        $tz = (string) config('zoho_calendar.timezone', config('app.timezone', 'Australia/Melbourne'));
        $out = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $uid = (string) ($row['uid'] ?? $row['event_uid'] ?? $row['id'] ?? '');
            if ($uid === '') {
                continue;
            }

            $dateandtime = $row['dateandtime'] ?? $row['dateAndTime'] ?? [];
            if (! is_array($dateandtime)) {
                $dateandtime = [];
            }
            $eventTz = (string) ($dateandtime['timezone'] ?? $tz);
            $startRaw = (string) ($dateandtime['start'] ?? $row['start'] ?? '');
            $endRaw = (string) ($dateandtime['end'] ?? $row['end'] ?? '');
            $isAllDay = (bool) ($row['isallday'] ?? $row['is_all_day'] ?? false);

            $starts = $this->parseZohoDateTime($startRaw, $eventTz, $isAllDay);
            $ends = $this->parseZohoDateTime($endRaw, $eventTz, $isAllDay);

            $out[] = [
                'uid' => $uid,
                'etag' => isset($row['etag']) ? (string) $row['etag'] : null,
                'title' => isset($row['title']) ? (string) $row['title'] : null,
                'description' => isset($row['description'])
                    ? (string) $row['description']
                    : (isset($row['richtext_description']) ? strip_tags((string) $row['richtext_description']) : null),
                'location' => isset($row['location']) ? (string) $row['location'] : null,
                'starts_at' => $starts,
                'ends_at' => $ends,
                'is_all_day' => $isAllDay,
                'raw' => $row,
            ];
        }

        return $out;
    }

    protected function parseZohoDateTime(string $raw, string $tz, bool $isAllDay): ?Carbon
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        try {
            // All-day: Ymd
            if (preg_match('/^\d{8}$/', $raw)) {
                return Carbon::createFromFormat('Ymd', $raw, $tz)->startOfDay();
            }
            // UTC Z form: YmdThisZ
            if (preg_match('/^\d{8}T\d{6}Z$/', $raw)) {
                return Carbon::createFromFormat('Ymd\THis\Z', $raw, 'UTC')->timezone($tz);
            }
            // Offset form or ISO
            return Carbon::parse($raw, $tz);
        } catch (\Throwable) {
            Log::debug('Could not parse Zoho datetime', ['raw' => $raw]);

            return null;
        }
    }

    /**
     * @return array{uid: string|null, etag: string|null, raw: array}
     */
    public function createEvent(
        ZohoCalendarConnection $connection,
        string $calendarUid,
        array $eventData
    ): array {
        $token = $this->oauth->validAccessToken($connection);
        $url = $this->calendarBase($connection) . '/calendars/' . rawurlencode($calendarUid) . '/events';

        $response = Http::withHeaders($this->authHeaders($token))
            ->timeout(45)
            ->asForm()
            ->post($url, [
                'eventdata' => json_encode($eventData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);

        if (! $response->successful()) {
            // Retry as query string (official samples often use query param)
            $response = Http::withHeaders($this->authHeaders($token))
                ->timeout(45)
                ->post($url . '?eventdata=' . rawurlencode(json_encode($eventData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)));
        }

        if (! $response->successful()) {
            throw new RuntimeException('Zoho create event failed (' . $response->status() . '): ' . $this->shortBody($response->body()));
        }

        return $this->extractEventIdentity($response->json() ?? []);
    }

    /**
     * @return array{uid: string|null, etag: string|null, raw: array}
     */
    public function updateEvent(
        ZohoCalendarConnection $connection,
        string $calendarUid,
        string $eventUid,
        array $eventData,
        ?string $etag = null
    ): array {
        $token = $this->oauth->validAccessToken($connection);
        $url = $this->calendarBase($connection) . '/calendars/' . rawurlencode($calendarUid)
            . '/events/' . rawurlencode($eventUid);

        $payload = ['eventdata' => json_encode($eventData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)];
        if ($etag) {
            $payload['etag'] = $etag;
        }

        $response = Http::withHeaders($this->authHeaders($token))
            ->timeout(45)
            ->asForm()
            ->put($url, $payload);

        if (! $response->successful()) {
            throw new RuntimeException('Zoho update event failed (' . $response->status() . '): ' . $this->shortBody($response->body()));
        }

        return $this->extractEventIdentity($response->json() ?? []);
    }

    public function deleteEvent(
        ZohoCalendarConnection $connection,
        string $calendarUid,
        string $eventUid,
        ?string $etag = null
    ): void {
        $token = $this->oauth->validAccessToken($connection);
        $url = $this->calendarBase($connection) . '/calendars/' . rawurlencode($calendarUid)
            . '/events/' . rawurlencode($eventUid);

        $query = $etag ? ['etag' => $etag] : [];

        $response = Http::withHeaders($this->authHeaders($token))
            ->timeout(45)
            ->delete($url, $query);

        if (! $response->successful() && $response->status() !== 404) {
            throw new RuntimeException('Zoho delete event failed (' . $response->status() . '): ' . $this->shortBody($response->body()));
        }
    }

    /**
     * @return array{uid: string|null, etag: string|null, raw: array}
     */
    protected function extractEventIdentity(array $json): array
    {
        $events = $json['events'] ?? $json['event'] ?? $json;
        if (isset($events[0]) && is_array($events[0])) {
            $row = $events[0];
        } elseif (is_array($events) && (isset($events['uid']) || isset($events['event_uid']))) {
            $row = $events;
        } else {
            $row = is_array($events) ? $events : [];
        }

        return [
            'uid' => isset($row['uid']) ? (string) $row['uid'] : (isset($row['event_uid']) ? (string) $row['event_uid'] : null),
            'etag' => isset($row['etag']) ? (string) $row['etag'] : null,
            'raw' => $json,
        ];
    }

    public function buildEventDataFromStaffEvent(StaffCalendarEvent $event): array
    {
        $title = CalendarEventTitleBuilder::forStaffEvent($event);
        $tz = (string) config('zoho_calendar.timezone', config('app.timezone', 'Australia/Melbourne'));

        $starts = $event->starts_at instanceof Carbon
            ? $event->starts_at->copy()->timezone($tz)
            : Carbon::parse($event->starts_at, $tz);
        $ends = $event->ends_at
            ? ($event->ends_at instanceof Carbon ? $event->ends_at->copy()->timezone($tz) : Carbon::parse($event->ends_at, $tz))
            : $starts->copy()->addHour();

        $isAllDay = (bool) $event->is_all_day;
        if ($isAllDay) {
            $dateandtime = [
                'timezone' => $tz,
                'start' => $starts->format('Ymd'),
                'end' => $ends->format('Ymd'),
            ];
        } else {
            $dateandtime = [
                'timezone' => $tz,
                'start' => $starts->utc()->format('Ymd\THis\Z'),
                'end' => $ends->utc()->format('Ymd\THis\Z'),
            ];
        }

        $descriptionParts = array_filter([
            'CRM staff calendar event',
            $event->event_type ? ('Type: ' . $event->event_type) : null,
            $event->notes ? trim((string) $event->notes) : null,
            $event->client_id ? ('File #: ' . $event->client_id) : null,
            $event->client_matter_id ? ('Matter id: ' . $event->client_matter_id) : null,
        ]);

        $data = [
            'title' => $title,
            'dateandtime' => $dateandtime,
            'isallday' => $isAllDay,
            'description' => implode("\n", $descriptionParts),
        ];

        if (! empty($event->location)) {
            $data['location'] = Str::limit((string) $event->location, 255, '...');
        }

        if (! empty($event->reminder_minutes) && (int) $event->reminder_minutes > 0) {
            $data['reminders'] = [[
                'action' => 'popup',
                'minutes' => -1 * (int) $event->reminder_minutes,
            ]];
        }

        return $data;
    }

    public function buildEventDataFromHearing(ClientCourtHearing $hearing): array
    {
        $title = CalendarEventTitleBuilder::forHearing($hearing);
        $tz = (string) config('zoho_calendar.timezone', config('app.timezone', 'Australia/Melbourne'));
        $starts = $hearing->hearingStartsAt()->timezone($tz);
        $ends = $starts->copy()->addHour();
        $isAllDay = $hearing->hearing_time === null || $hearing->hearing_time === '';

        if ($isAllDay) {
            $dateandtime = [
                'timezone' => $tz,
                'start' => $starts->format('Ymd'),
                'end' => $starts->format('Ymd'),
            ];
        } else {
            $dateandtime = [
                'timezone' => $tz,
                'start' => $starts->utc()->format('Ymd\THis\Z'),
                'end' => $ends->utc()->format('Ymd\THis\Z'),
            ];
        }

        $descriptionParts = array_filter([
            'CRM court hearing',
            $hearing->case_number ? ('Case: ' . $hearing->case_number) : null,
            $hearing->judge_name ? ('Judge: ' . $hearing->judge_name) : null,
            $hearing->status ? ('Status: ' . $hearing->status) : null,
            $hearing->notes ? trim((string) $hearing->notes) : null,
            $hearing->client_id ? ('File #: ' . $hearing->client_id) : null,
        ]);

        $data = [
            'title' => $title,
            'dateandtime' => $dateandtime,
            'isallday' => $isAllDay,
            'description' => implode("\n", $descriptionParts),
        ];

        if (! empty($hearing->court_name)) {
            $data['location'] = Str::limit((string) $hearing->court_name, 255, '...');
        }

        if (! empty($hearing->reminder_minutes) && (int) $hearing->reminder_minutes > 0) {
            $data['reminders'] = [[
                'action' => 'popup',
                'minutes' => -1 * (int) $hearing->reminder_minutes,
            ]];
        }

        return $data;
    }

    public function buildEventDataFromBooking(BookingAppointment $appointment): array
    {
        $title = CalendarEventTitleBuilder::forBooking($appointment);
        $tz = (string) config('zoho_calendar.timezone', config('app.timezone', 'Australia/Melbourne'));

        $starts = $appointment->appointment_datetime instanceof Carbon
            ? $appointment->appointment_datetime->copy()->timezone($tz)
            : Carbon::parse($appointment->appointment_datetime, $tz);

        $mins = max(15, (int) ($appointment->duration_minutes ?: 30));
        $ends = $starts->copy()->addMinutes($mins);

        $dateandtime = [
            'timezone' => $tz,
            'start' => $starts->utc()->format('Ymd\THis\Z'),
            'end' => $ends->utc()->format('Ymd\THis\Z'),
        ];

        $descriptionParts = array_filter([
            'CRM booking appointment',
            $appointment->meeting_type ? ('Meeting: ' . $appointment->meeting_type) : null,
            $appointment->status ? ('Status: ' . $appointment->status) : null,
            $appointment->enquiry_details ? trim((string) $appointment->enquiry_details) : null,
            $appointment->client_id ? ('File #: ' . $appointment->client_id) : null,
        ]);

        $data = [
            'title' => $title,
            'dateandtime' => $dateandtime,
            'isallday' => false,
            'description' => implode("\n", $descriptionParts),
        ];

        $location = $appointment->inperson_address ?: $appointment->location;
        if (! empty($location)) {
            $data['location'] = Str::limit((string) $location, 255, '...');
        }

        return $data;
    }

    protected function calendarBase(ZohoCalendarConnection $connection): string
    {
        // Prefer configured regional calendar API; api_domain from OAuth is usually www.zohoapis.com(.au)
        $configured = rtrim((string) config('zoho_calendar.calendar_api_url'), '/');
        if ($configured !== '') {
            return $configured;
        }

        $domain = rtrim((string) ($connection->api_domain ?: 'https://www.zohoapis.com.au'), '/');

        return $domain . '/calendar/v1';
    }

    /**
     * @return array{Authorization: string, Accept: string}
     */
    protected function authHeaders(string $token): array
    {
        return [
            'Authorization' => 'Zoho-oauthtoken ' . $token,
            'Accept' => 'application/json',
        ];
    }

    protected function shortBody(string $body): string
    {
        $body = trim(preg_replace('/\s+/', ' ', $body) ?? $body);

        return strlen($body) > 400 ? substr($body, 0, 397) . '...' : $body;
    }
}
