<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\AppointmentConsultant;
use App\Models\BookingAppointment;
use App\Models\ClientCourtHearing;
use App\Models\ClientMatter;
use App\Models\Note;
use App\Models\Staff;
use App\Models\StaffCalendarEvent;
use App\Services\Booking\BookingCalendarExternalFeed;
use App\Services\Booking\StaffCalendarFeedService;
use App\Support\StaffClientVisibility;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class StaffPersonalCalendarFeedService
{
    public function __construct(
        protected StaffCalendarFeedService $staffCalendarFeed
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function eventsForStaffRequest(Staff $staff, Request $request): array
    {
        $calendarType = $this->bookingCalendarTypeForStaff($staff);

        $events = array_merge(
            $calendarType
                ? $this->websiteBookingsForCalendarType($calendarType, $request)
                : $this->websiteBookings($staff, $request),
            $this->bookingCalendarImportantEvents($calendarType ?? '', $request)
        );

        $events = $this->deduplicateEvents($events);

        usort($events, fn (array $a, array $b) => strcmp(
            (string) ($a['starts_at'] ?? ''),
            (string) ($b['starts_at'] ?? '')
        ));

        return $events;
    }

    /**
     * Remove duplicate rows from merged booking + staff/court feeds.
     *
     * @param  list<array<string, mixed>>  $events
     * @return list<array<string, mixed>>
     */
    protected function deduplicateEvents(array $events): array
    {
        $byCanonical = [];

        foreach ($events as $event) {
            $key = $this->canonicalEventKey($event);
            if (! isset($byCanonical[$key])) {
                $byCanonical[$key] = $event;

                continue;
            }

            $byCanonical[$key] = $this->pickPreferredEvent($byCanonical[$key], $event);
        }

        $bySlot = [];
        foreach ($byCanonical as $event) {
            $slot = $this->eventSlotKey($event);
            if ($slot === null) {
                $bySlot[$this->canonicalEventKey($event)] = $event;

                continue;
            }

            if (! isset($bySlot[$slot])) {
                $bySlot[$slot] = $event;

                continue;
            }

            $bySlot[$slot] = $this->pickPreferredEvent($bySlot[$slot], $event);
        }

        return array_values($bySlot);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function canonicalEventKey(array $row): string
    {
        $kind = (string) ($row['event_kind'] ?? '');

        if ($kind === 'website_booking' && ! empty($row['booking_appointment_id'])) {
            return 'booking:' . $row['booking_appointment_id'];
        }

        if ($kind === 'staff_event' && ! empty($row['staff_calendar_event_id'])) {
            return 'staff:' . $row['staff_calendar_event_id'];
        }

        if ($kind === 'court_hearing' && ! empty($row['court_hearing_id'])) {
            return 'court:' . $row['court_hearing_id'];
        }

        $id = (string) ($row['id'] ?? '');
        if ($id !== '') {
            return 'id:' . $id;
        }

        $start = substr((string) ($row['starts_at'] ?? ''), 0, 16);
        $clientId = (string) ($row['client_id'] ?? '');

        return $kind . '|' . (string) ($row['event_type'] ?? '') . '|' . $clientId . '|' . $start;
    }

    /**
     * Cross-source key for the same client at the same minute (booking vs staff event).
     *
     * @param  array<string, mixed>  $row
     */
    protected function eventSlotKey(array $row): ?string
    {
        $start = (string) ($row['starts_at'] ?? '');
        if ($start === '') {
            return null;
        }

        $kind = (string) ($row['event_kind'] ?? '');
        $type = (string) ($row['event_type'] ?? '');

        if (! in_array($kind, ['website_booking', 'staff_event', 'court_hearing'], true)
            && ! in_array($type, ['meeting', 'court'], true)) {
            return null;
        }

        $clientId = (string) ($row['client_id'] ?? '0');
        $minute = substr($start, 0, 16);

        return $clientId . '|' . $minute . '|' . ($type !== '' ? $type : $kind);
    }

    /**
     * @param  array<string, mixed>  $a
     * @param  array<string, mixed>  $b
     * @return array<string, mixed>
     */
    protected function pickPreferredEvent(array $a, array $b): array
    {
        $priority = [
            'website_booking' => 3,
            'court_hearing' => 2,
            'staff_event' => 1,
        ];

        $priorityA = $priority[(string) ($a['event_kind'] ?? '')] ?? 0;
        $priorityB = $priority[(string) ($b['event_kind'] ?? '')] ?? 0;

        return $priorityA >= $priorityB ? $a : $b;
    }

    /**
     * Booking calendar tab this staff member owns (ajay / kunal), if any.
     */
    public function bookingCalendarTypeForStaff(Staff $staff): ?string
    {
        if (Schema::hasTable('appointment_consultants')) {
            $consultantIds = $this->consultantIdsForStaff($staff);
            if ($consultantIds !== []) {
                $type = AppointmentConsultant::query()
                    ->whereIn('id', $consultantIds)
                    ->whereIn('calendar_type', ['ajay', 'kunal'])
                    ->value('calendar_type');
                if (is_string($type) && $type !== '') {
                    return $type;
                }
            }
        }

        $firstName = strtolower(trim((string) ($staff->first_name ?? '')));
        if ($firstName === 'ajay') {
            return 'ajay';
        }
        if (in_array($firstName, ['michael', 'kunal'], true)) {
            return 'kunal';
        }

        return null;
    }

    /**
     * @return array{today: int, this_week: int, overdue_actions: int}
     */
    public function statsForStaff(Staff $staff): array
    {
        $tz = config('app.timezone');
        $today = Carbon::today($tz);
        $weekEnd = $today->copy()->endOfWeek();
        $staffId = (int) $staff->id;

        $todayRequest = new Request([
            'start' => $today->toIso8601String(),
            'end' => $today->copy()->addDay()->toIso8601String(),
        ]);
        $weekRequest = new Request([
            'start' => $today->toIso8601String(),
            'end' => $weekEnd->copy()->addDay()->toIso8601String(),
        ]);

        return [
            'today' => $this->countEventsForStaffRequest($staff, $todayRequest),
            'this_week' => $this->countEventsForStaffRequest($staff, $weekRequest),
            'overdue_actions' => Note::query()
                ->where('assigned_to', $staffId)
                ->where('is_action', 1)
                ->where('status', 0)
                ->whereNotNull('note_deadline')
                ->whereDate('note_deadline', '<', $today->toDateString())
                ->count(),
        ];
    }

    public function countEventsForStaffRequest(Staff $staff, Request $request): int
    {
        $calendarType = $this->bookingCalendarTypeForStaff($staff);

        $bookingCount = $calendarType
            ? $this->countWebsiteBookingsForCalendarType($calendarType, $request)
            : $this->countWebsiteBookings($staff, $request);

        $importantCount = $this->countBookingCalendarImportantEvents($calendarType ?? '', $request);

        return $bookingCount + $importantCount;
    }

    protected function countWebsiteBookingsForCalendarType(string $calendarType, Request $request): int
    {
        if (! Schema::hasTable('booking_appointments')) {
            return 0;
        }

        $consultantId = (int) config('booking_calendar.local_consultant_id_by_calendar_type.' . $calendarType, 0);
        $query = BookingAppointment::query();
        if ($consultantId > 0) {
            $query->where('consultant_id', $consultantId);
        } else {
            $query->whereHas('consultant', function (Builder $q) use ($calendarType) {
                $q->where('calendar_type', $calendarType);
            });
        }

        $query->whereNotIn('status', ['cancelled', 'no_show']);

        StaffClientVisibility::restrictBookingAppointmentEloquentQuery($query);
        $this->applyDatetimeWindow($query, 'appointment_datetime', $request);

        return (int) $query->count();
    }

    protected function countWebsiteBookings(Staff $staff, Request $request): int
    {
        if (! Schema::hasTable('booking_appointments') || ! Schema::hasTable('appointment_consultants')) {
            return 0;
        }

        $consultantIds = $this->consultantIdsForStaff($staff);
        if ($consultantIds === []) {
            return 0;
        }

        $query = BookingAppointment::query()
            ->whereIn('consultant_id', $consultantIds)
            ->whereNotIn('status', ['cancelled', 'no_show']);

        StaffClientVisibility::restrictBookingAppointmentEloquentQuery($query);
        $this->applyDatetimeWindow($query, 'appointment_datetime', $request);

        return (int) $query->count();
    }

    protected function countBookingCalendarImportantEvents(string $calendarType, Request $request): int
    {
        $feedRequest = Request::create('/', 'GET', array_merge(
            $request->query(),
            $request->request->all(),
            ['type' => $calendarType]
        ));

        return $this->staffCalendarFeed->countEventsForCalendarRequest($feedRequest);
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function staffCalendarEvents(int $staffId, Request $request): array
    {
        if (! Schema::hasTable('staff_calendar_events')) {
            return [];
        }

        $query = StaffCalendarEvent::query()->with(['client']);

        $query->where(function (Builder $q) use ($staffId) {
            $q->where('created_by_staff_id', $staffId)
                ->orWhere(function (Builder $inner) use ($staffId) {
                    $inner->whereNotNull('client_matter_id')
                        ->whereIn('client_matter_id', $this->assignedMatterIdsQuery($staffId));
                })
                ->orWhere(function (Builder $inner) use ($staffId) {
                    $inner->whereNotNull('client_id')
                        ->whereNull('client_matter_id')
                        ->whereIn('client_id', $this->assignedClientIdsQuery($staffId));
                });
        });

        StaffClientVisibility::restrictEloquentQueryByClientIdColumn($query, 'client_id');
        $this->applyDatetimeWindow($query, 'starts_at', $request);

        return $query->orderBy('starts_at')->get()
            ->map(fn (StaffCalendarEvent $e) => $this->wrapStaffEvent(
                $this->staffCalendarFeed->payloadFromStaffEvent($e),
                $e->client
            ))
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function courtHearings(int $staffId, Request $request): array
    {
        if (! Schema::hasTable('client_court_hearings')) {
            return [];
        }

        $query = ClientCourtHearing::query()->with(['client']);

        $query->where(function (Builder $q) use ($staffId) {
            $q->whereIn('client_matter_id', $this->assignedMatterIdsQuery($staffId))
                ->orWhereIn('client_id', $this->assignedClientIdsQuery($staffId));
        });

        StaffClientVisibility::restrictEloquentQueryByClientIdColumn($query, 'client_id');
        $this->applyHearingDateWindow($query, $request);

        return $query->orderBy('hearing_date')->get()
            ->map(fn (ClientCourtHearing $h) => $this->wrapStaffEvent(
                $this->staffCalendarFeed->payloadFromCourtHearing($h),
                $h->client
            ))
            ->unique(function (array $row) {
                $start = (string) ($row['starts_at'] ?? '');
                $clientId = (string) ($row['client_id'] ?? '');

                return $clientId . '|' . substr($start, 0, 16);
            })
            ->values()
            ->all();
    }

    /**
     * Website bookings + important events using the same consultant scope as /booking/calendar/{type}.
     *
     * @return list<array<string, mixed>>
     */
    protected function websiteBookingsForCalendarType(string $calendarType, Request $request): array
    {
        if (! Schema::hasTable('booking_appointments')) {
            return [];
        }

        $consultantId = (int) config('booking_calendar.local_consultant_id_by_calendar_type.' . $calendarType, 0);
        $query = BookingAppointment::query()->with(['client', 'consultant']);
        if ($consultantId > 0) {
            $query->where('consultant_id', $consultantId);
        } else {
            $query->whereHas('consultant', function (Builder $q) use ($calendarType) {
                $q->where('calendar_type', $calendarType);
            });
        }

        // Calendars never show cancelled / no-show; lists still do.
        $query->whereNotIn('status', ['cancelled', 'no_show']);

        StaffClientVisibility::restrictBookingAppointmentEloquentQuery($query);
        $this->applyDatetimeWindow($query, 'appointment_datetime', $request);

        return $query->orderBy('appointment_datetime')->get()
            ->map(fn (BookingAppointment $appointment) => $this->payloadFromBookingAppointment($appointment))
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function bookingCalendarImportantEvents(string $calendarType, Request $request): array
    {
        // Do not use $request->url() here: statsForStaff builds a synthetic Request without
        // server vars, so url() becomes "http://:" and Request::create() throws BadRequestException
        // (surfaced as BadRequestHttpException "Bad request." on /dashboard).
        $feedRequest = Request::create('/', 'GET', array_merge(
            $request->query(),
            $request->request->all(),
            ['type' => $calendarType]
        ));

        return array_values(array_map(
            function (array $row) {
                $row['client_email'] = $row['client_email'] ?? null;

                return $row;
            },
            $this->staffCalendarFeed->eventsForCalendarRequest($feedRequest)
        ));
    }

    /**
     * Website bookings for consultants that match this staff member (email / first name).
     *
     * @return list<array<string, mixed>>
     */
    protected function websiteBookings(Staff $staff, Request $request): array
    {
        if (! Schema::hasTable('booking_appointments') || ! Schema::hasTable('appointment_consultants')) {
            return [];
        }

        $consultantIds = $this->consultantIdsForStaff($staff);
        if ($consultantIds === []) {
            return [];
        }

        $query = BookingAppointment::query()
            ->with(['client', 'consultant'])
            ->whereIn('consultant_id', $consultantIds)
            ->whereNotIn('status', ['cancelled', 'no_show']);

        StaffClientVisibility::restrictBookingAppointmentEloquentQuery($query);
        $this->applyDatetimeWindow($query, 'appointment_datetime', $request);

        return $query->orderBy('appointment_datetime')->get()
            ->map(fn (BookingAppointment $appointment) => $this->payloadFromBookingAppointment($appointment))
            ->values()
            ->all();
    }

    /**
     * @return list<int>
     */
    protected function consultantIdsForStaff(Staff $staff): array
    {
        $email = strtolower(trim((string) ($staff->email ?? '')));
        $firstName = strtolower(trim((string) ($staff->first_name ?? '')));

        if ($email === '' && mb_strlen($firstName) < 3) {
            return [];
        }

        if ($email !== '') {
            $byEmail = AppointmentConsultant::query()
                ->whereRaw('LOWER(email) = ?', [$email])
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();
            if ($byEmail !== []) {
                return $byEmail;
            }
        }

        if (mb_strlen($firstName) < 3) {
            return [];
        }

        $safeName = str_replace(['%', '_'], '', $firstName);

        return AppointmentConsultant::query()
            ->where(function (Builder $q) use ($firstName, $safeName) {
                $q->whereRaw('LOWER(name) = ?', [$firstName])
                    ->orWhereRaw('LOWER(name) LIKE ?', [$safeName . ' %']);
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function payloadFromBookingAppointment(BookingAppointment $appointment): array
    {
        $tz = config('app.timezone');
        $start = $appointment->appointment_datetime
            ? $appointment->appointment_datetime->copy()->timezone($tz)
            : Carbon::now($tz);
        $duration = (int) ($appointment->duration_minutes ?: 15);
        if ($duration < 15) {
            $duration = 15;
        }
        $end = $start->copy()->addMinutes($duration);

        $clientName = $this->clientDisplayName($appointment->client)
            ?: trim((string) ($appointment->client_name ?? ''))
            ?: 'Client';
        $status = (string) ($appointment->status ?? 'pending');
        $statusLabel = BookingCalendarExternalFeed::crmCalendarLegendStatusLabel($status);
        $meetingType = trim((string) ($appointment->meeting_type ?? ''));
        $meetingTypeDisplay = $meetingType !== ''
            ? ucwords(str_replace('_', ' ', $meetingType))
            : 'Appointment';
        $title = $clientName . ' (' . $meetingTypeDisplay . ')';

        return [
            'id' => 'booking-' . $appointment->id,
            'event_kind' => 'website_booking',
            'booking_appointment_id' => $appointment->id,
            'read_only' => true,
            'title' => $title,
            'event_type' => 'meeting',
            'appointment_datetime' => $start->toIso8601String(),
            'duration_minutes' => $duration,
            'starts_at' => $start->toIso8601String(),
            'ends_at' => $end->toIso8601String(),
            'is_all_day' => false,
            'client_id' => $appointment->client_id,
            'client_id_encoded' => $appointment->client_id
                ? base64_encode(convert_uuencode((string) $appointment->client_id))
                : null,
            'client_name' => $clientName,
            'client_email' => $appointment->client_email ?: $this->clientEmail($appointment->client),
            'location' => $appointment->location,
            'meeting_type' => $appointment->meeting_type,
            'notes' => $appointment->enquiry_details,
            'status' => $status,
            'status_label' => $statusLabel,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function actionDeadlines(int $staffId, Request $request, string $tz): array
    {
        $query = Note::query()
            ->with(['client'])
            ->where('assigned_to', $staffId)
            ->where('is_action', 1)
            ->where('status', 0)
            ->whereNotNull('note_deadline');

        $this->applyDateColumnWindow($query, 'note_deadline', $request);

        return $query->orderBy('note_deadline')->get()
            ->map(function (Note $note) use ($tz) {
                $deadline = Carbon::parse($note->note_deadline, $tz)->startOfDay()->setTime(9, 0);
                $clientName = $this->clientDisplayName($note->client);
                $title = trim(($clientName ? $clientName . ' — ' : '') . ($note->title ?: 'Task'));

                return [
                    'id' => 'action-' . $note->id,
                    'event_kind' => 'action',
                    'read_only' => true,
                    'title' => $title,
                    'event_type' => 'deadline',
                    'appointment_datetime' => $deadline->toIso8601String(),
                    'duration_minutes' => 30,
                    'starts_at' => $deadline->toIso8601String(),
                    'ends_at' => $deadline->copy()->addMinutes(30)->toIso8601String(),
                    'is_all_day' => true,
                    'client_id' => $note->client_id,
                    'client_id_encoded' => $note->client_id
                        ? base64_encode(convert_uuencode((string) $note->client_id))
                        : null,
                    'client_name' => $clientName,
                    'client_email' => $this->clientEmail($note->client),
                    'notes' => $note->description,
                    'status' => 'action',
                    'status_label' => 'My Action',
                    'action_url' => url('/action'),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function matterDeadlines(int $staffId, Request $request, string $tz): array
    {
        $query = ClientMatter::query()
            ->with(['client', 'matter'])
            ->where('matter_status', 1)
            ->whereNotNull('deadline')
            ->where(function (Builder $q) use ($staffId) {
                $q->where('sel_legal_practitioner', $staffId)
                    ->orWhere('sel_person_responsible', $staffId)
                    ->orWhere('sel_person_assisting', $staffId);
            });

        $this->applyDateColumnWindow($query, 'deadline', $request);

        return $query->orderBy('deadline')->get()
            ->map(function (ClientMatter $matter) use ($tz) {
                $deadline = Carbon::parse($matter->deadline, $tz)->startOfDay()->setTime(17, 0);
                $clientName = $this->clientDisplayName($matter->client);
                $matterLabel = \App\Models\Matter::displayTitleFromJoinedRow(optional($matter->matter)->title);
                $title = trim(($clientName ?: 'Client') . ' — Matter deadline' . ($matterLabel ? ' (' . $matterLabel . ')' : ''));

                return [
                    'id' => 'matter-deadline-' . $matter->id,
                    'event_kind' => 'matter_deadline',
                    'read_only' => true,
                    'title' => $title,
                    'event_type' => 'deadline',
                    'appointment_datetime' => $deadline->toIso8601String(),
                    'duration_minutes' => 60,
                    'starts_at' => $deadline->toIso8601String(),
                    'ends_at' => $deadline->copy()->addHour()->toIso8601String(),
                    'is_all_day' => true,
                    'client_id' => $matter->client_id,
                    'client_id_encoded' => $matter->client_id
                        ? base64_encode(convert_uuencode((string) $matter->client_id))
                        : null,
                    'client_name' => $clientName,
                    'client_email' => $this->clientEmail($matter->client),
                    'client_matter_id' => $matter->id,
                    'matter_no' => $matter->client_unique_matter_no,
                    'status' => 'deadline',
                    'status_label' => 'Matter Deadline',
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function wrapStaffEvent(array $payload, ?Admin $client = null): array
    {
        if ($client) {
            $payload['client_email'] = $this->clientEmail($client);
        }

        return $payload;
    }

    /**
     * @return Builder<ClientMatter>
     */
    protected function assignedMatterIdsQuery(int $staffId): Builder
    {
        return ClientMatter::query()
            ->select('id')
            ->where('matter_status', 1)
            ->where(function (Builder $q) use ($staffId) {
                $q->where('sel_legal_practitioner', $staffId)
                    ->orWhere('sel_person_responsible', $staffId)
                    ->orWhere('sel_person_assisting', $staffId);
            });
    }

    /**
     * @return Builder<ClientMatter>
     */
    protected function assignedClientIdsQuery(int $staffId): Builder
    {
        return ClientMatter::query()
            ->select('client_id')
            ->where('matter_status', 1)
            ->where(function (Builder $q) use ($staffId) {
                $q->where('sel_legal_practitioner', $staffId)
                    ->orWhere('sel_person_responsible', $staffId)
                    ->orWhere('sel_person_assisting', $staffId);
            });
    }

    /**
     * Dashboard calendar only shows today and future items.
     */
    public function upcomingStart(?string $timezone = null): Carbon
    {
        return Carbon::today($timezone ?: config('app.timezone'))->startOfDay();
    }

    /**
     * Clamp a FullCalendar range so past dates are never included.
     *
     * @return array{0: Carbon, 1: Carbon|null}
     */
    public function clampRangeToUpcoming(?string $start, ?string $end, ?string $timezone = null): array
    {
        $tz = $timezone ?: config('app.timezone');
        $floor = $this->upcomingStart($tz);
        $rangeEnd = null;

        try {
            $rangeStart = $start ? Carbon::parse($start, $tz) : $floor->copy();
        } catch (Exception) {
            $rangeStart = $floor->copy();
        }

        if ($rangeStart->lt($floor)) {
            $rangeStart = $floor->copy();
        }

        if ($end) {
            try {
                $rangeEnd = Carbon::parse($end, $tz);
            } catch (Exception) {
                $rangeEnd = null;
            }
        }

        return [$rangeStart, $rangeEnd];
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     */
    protected function applyDatetimeWindow(Builder $query, string $column, Request $request): void
    {
        [$rangeStart, $rangeEnd] = $this->clampRangeToUpcoming(
            $request->get('start'),
            $request->get('end')
        );

        $query->where($column, '>=', $rangeStart);
        if ($rangeEnd) {
            $query->where($column, '<', $rangeEnd);
        }
    }

    /**
     * @param  Builder<ClientCourtHearing>  $query
     */
    protected function applyHearingDateWindow(Builder $query, Request $request): void
    {
        [$rangeStart, $rangeEnd] = $this->clampRangeToUpcoming(
            $request->get('start'),
            $request->get('end')
        );

        $query->whereDate('hearing_date', '>=', $rangeStart->toDateString());
        if ($rangeEnd) {
            $query->whereDate('hearing_date', '<', $rangeEnd->toDateString());
        }
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     */
    protected function applyDateColumnWindow(Builder $query, string $column, Request $request): void
    {
        [$rangeStart, $rangeEnd] = $this->clampRangeToUpcoming(
            $request->get('start'),
            $request->get('end')
        );

        $query->whereDate($column, '>=', $rangeStart->toDateString());
        if ($rangeEnd) {
            $query->whereDate($column, '<', $rangeEnd->toDateString());
        }
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

    protected function colorForBookingStatus(string $status): string
    {
        return match ($status) {
            'pending' => '#D4A84A',
            'paid' => '#1E3D60',
            'confirmed' => '#1E7A52',
            'completed' => '#3A6FA8',
            'cancelled' => '#A83020',
            'no_show' => '#5E7A90',
            'rescheduled' => '#1E3D60',
            default => '#5E7A90',
        };
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public function toFullCalendarEvent(array $row): array
    {
        $type = (string) ($row['event_type'] ?? 'other');
        $kind = (string) ($row['event_kind'] ?? 'other');
        $start = $row['starts_at'] ?? $row['appointment_datetime'] ?? null;
        $end = $row['ends_at'] ?? null;

        if ($start && ! $end) {
            $end = Carbon::parse($start)->addMinutes((int) ($row['duration_minutes'] ?? 60))->toIso8601String();
        }

        $color = match ($kind) {
            'action', 'matter_deadline' => StaffCalendarFeedService::colorForEventType('deadline'),
            'court_hearing' => StaffCalendarFeedService::colorForEventType('court'),
            'website_booking' => $this->colorForBookingStatus((string) ($row['status'] ?? '')),
            default => StaffCalendarFeedService::colorForEventType($type),
        };

        $textColor = $kind === 'website_booking'
            ? (((string) ($row['status'] ?? '')) === 'pending' ? '#1A2C40' : '#fff')
            : StaffCalendarFeedService::textColorForEventType(
                in_array($kind, ['action', 'matter_deadline'], true) ? 'deadline' : $type
            );

        return [
            'id' => (string) ($row['id'] ?? uniqid('evt-', true)),
            'title' => (string) ($row['title'] ?? 'Event'),
            'start' => $start,
            'end' => $end,
            'allDay' => (bool) ($row['is_all_day'] ?? false),
            'backgroundColor' => $color,
            'borderColor' => $color,
            'textColor' => $textColor,
            'classNames' => ['event-' . $type, 'event-kind-' . $kind],
            'extendedProps' => $row,
        ];
    }
}
