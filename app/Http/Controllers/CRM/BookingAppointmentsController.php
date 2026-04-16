<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use App\Models\BookingAppointment;
use App\Models\AppointmentConsultant;
use App\Models\Admin;
use App\Models\ClientMatter;
use App\Models\AppointmentSyncLog;
use App\Models\ActivitiesLog;
use App\Services\BansalAppointmentSync\AppointmentSyncService;
use App\Services\BansalAppointmentSync\BansalApiClient;
use App\Services\Booking\BookingCalendarExternalFeed;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Exception;
use Carbon\Carbon;
use App\Support\StaffClientVisibility;
use Illuminate\Support\Str;
use Yajra\DataTables\Facades\DataTables;

class BookingAppointmentsController extends Controller
{
       protected AppointmentSyncService $syncService;
    protected BansalApiClient $bansalApiClient;
    protected BookingCalendarExternalFeed $calendarExternalFeed;

    public function __construct(
        AppointmentSyncService $syncService,
        BansalApiClient $bansalApiClient,
        BookingCalendarExternalFeed $calendarExternalFeed
    ) {
        $this->middleware('auth:admin');
        $this->syncService = $syncService;
        $this->bansalApiClient = $bansalApiClient;
        $this->calendarExternalFeed = $calendarExternalFeed;
    }

    protected function assertBookingAppointmentAccess(BookingAppointment $appointment): void
    {
        StaffClientVisibility::abortUnlessMayAccessBookingAppointment($appointment);
    }

    /**
     * ?status= value for the public /appointments API (null means omit the parameter).
     */
    protected function calendarApiStatusQueryValue(): int|string|null
    {
        $raw = config('booking_calendar.external.api_status_filter', 1);
        if ($raw === '' || $raw === null) {
            return null;
        }

        return $raw;
    }

    protected function calendarIncludePastInVisibleRange(): bool
    {
        return (bool) config('booking_calendar.include_past_in_visible_range', false);
    }

    /**
     * When set, this calendar type uses only CRM rows with the given consultant_id (no public API).
     */
    protected function calendarLocalConsultantIdForType(?string $type): ?int
    {
        if ($type === null || $type === '') {
            return null;
        }
        $map = config('booking_calendar.local_consultant_id_by_calendar_type', []);
        if (! array_key_exists($type, $map)) {
            return null;
        }
        $id = (int) $map[$type];

        return $id > 0 ? $id : null;
    }

    protected function calendarTypeUsesLocalDbOnly(?string $type): bool
    {
        return $this->calendarLocalConsultantIdForType($type) !== null;
    }

    protected function applyBookingCalendarTypeScope(Builder $query, string $type): void
    {
        $localId = $this->calendarLocalConsultantIdForType($type);
        if ($localId !== null) {
            $query->where('consultant_id', $localId);

            return;
        }
        $query->whereHas('consultant', function ($q) use ($type) {
            $q->where('calendar_type', $type);
        });
    }

    /**
     * When BOOKING_CALENDAR_INCLUDE_PAST_IN_RANGE is true: restrict to FullCalendar’s [start, end) window.
     */
    protected function applyCalendarVisibleDatetimeWindow(Builder $query, Request $request, Carbon $startOfToday): void
    {
        if ($request->filled('start') && $request->filled('end')) {
            try {
                $rangeStart = Carbon::parse($request->get('start'));
                $rangeEnd = Carbon::parse($request->get('end'));
                $query->where('appointment_datetime', '>=', $rangeStart)
                    ->where('appointment_datetime', '<', $rangeEnd);

                return;
            } catch (Exception) {
                // fall through
            }
        }
        $query->where('appointment_datetime', '>=', $startOfToday);
    }

    /**
     * @param  list<array<string, mixed>>  $normalized
     * @return list<array<string, mixed>>
     */
    protected function filterNormalizedRowsForCalendarVisibleRange(Request $request, array $normalized, Carbon $startOfToday): array
    {
        return array_values(array_filter($normalized, function (array $row) use ($request, $startOfToday) {
            if (empty($row['appointment_datetime'])) {
                return false;
            }
            try {
                $dt = Carbon::parse($row['appointment_datetime'], config('app.timezone'));
            } catch (Exception) {
                return false;
            }
            if ($dt->gte($startOfToday)) {
                return true;
            }
            if ($request->filled('start') && $request->filled('end')) {
                try {
                    $rangeStart = Carbon::parse($request->get('start'));
                    $rangeEnd = Carbon::parse($request->get('end'));

                    return $dt->gte($rangeStart) && $dt->lt($rangeEnd);
                } catch (Exception) {
                    return false;
                }
            }

            return false;
        }));
    }

    /**
     * FullCalendar JSON: local DB, external Bansal public API, or both (see config/booking_calendar.php).
     *
     * @param  Builder<BookingAppointment>  $query  Pre-filtered (e.g. by calendar type)
     * @return array{success: bool, data: array<int, array<string, mixed>>, message?: string}
     */
    protected function buildCalendarFeedResponse(Request $request, Builder $query): array
    {
        $source = config('booking_calendar.data_source', 'local');
        $reqType = (string) $request->get('type', '');
        if ($reqType !== '' && $this->calendarTypeUsesLocalDbOnly($reqType)) {
            $source = 'local';
        }
        $startOfToday = Carbon::today(config('app.timezone'));
        $currentDateTime = Carbon::now(config('app.timezone'));
        $includePast = $this->calendarIncludePastInVisibleRange();

        if ($source === 'local') {
            if ($includePast) {
                $this->applyCalendarVisibleDatetimeWindow($query, $request, $startOfToday);
            } else {
                $query->where('appointment_datetime', '>=', $startOfToday);
            }
            $appointments = $query->get();

            Log::info('Calendar API Request - Showing Today and Future Appointments (local)', [
                'type' => $request->get('type'),
                'start' => $request->get('start'),
                'end' => $request->get('end'),
                'start_of_today_filter' => $startOfToday->toDateTimeString(),
                'current_datetime' => $currentDateTime->toDateTimeString(),
                'timezone' => $startOfToday->timezone->getName(),
                'appointments_count_after_filter' => $appointments->count(),
            ]);

            return [
                'success' => true,
                'data' => $appointments
                    ->map(fn (BookingAppointment $a) => $this->calendarExternalFeed->calendarPayloadFromModel($a))
                    ->values()
                    ->all(),
            ];
        }

        if ($source === 'merge') {
            try {
                return [
                    'success' => true,
                    'data' => $this->mergeCalendarLocalAndExternal($request, $query, $startOfToday),
                ];
            } catch (Exception $e) {
                Log::error('Calendar merge (external) failed; using local only', ['error' => $e->getMessage()]);
                if ($this->calendarIncludePastInVisibleRange()) {
                    $this->applyCalendarVisibleDatetimeWindow($query, $request, $startOfToday);
                } else {
                    $query->where('appointment_datetime', '>=', $startOfToday);
                }
                $appointments = $query->get();

                return [
                    'success' => true,
                    'data' => $appointments
                        ->map(fn (BookingAppointment $a) => $this->calendarExternalFeed->calendarPayloadFromModel($a))
                        ->values()
                        ->all(),
                ];
            }
        }

        // external
        $type = (string) $request->get('type', 'ajay');
        $apiStatus = $this->calendarApiStatusQueryValue();
        $serviceId = $this->calendarExternalFeed->resolveServiceIdForCalendarType($type);

        try {
            // Synthetic bansal_appointment_id must match stats: API rows often omit id — without this, KPIs show data but events are empty.
            $normalized = $this->calendarExternalFeed->fetchAppointmentsNormalized(
                $serviceId,
                $apiStatus,
                $request->get('start'),
                $request->get('end'),
                true
            );
            if ($normalized === [] && ($request->filled('start') || $request->filled('end'))) {
                $normalized = $this->calendarExternalFeed->fetchAppointmentsNormalized(
                    $serviceId,
                    $apiStatus,
                    null,
                    null,
                    true
                );
            }
        } catch (Exception $e) {
            Log::error('Calendar external API failed', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => [],
            ];
        }

        if ($this->calendarIncludePastInVisibleRange()) {
            $normalized = $this->filterNormalizedRowsForCalendarVisibleRange($request, $normalized, $startOfToday);
        } else {
            $normalized = array_values(array_filter($normalized, function (array $row) use ($startOfToday) {
                if (empty($row['appointment_datetime'])) {
                    return false;
                }
                try {
                    return Carbon::parse($row['appointment_datetime'], config('app.timezone'))->gte($startOfToday);
                } catch (Exception) {
                    return false;
                }
            }));
        }

        $data = $this->calendarExternalFeed->resolveWithLocalCrmRows($normalized);

        Log::info('Calendar API Request - external Bansal appointment API', [
            'type' => $type,
            'service_id' => $serviceId,
            'rows' => count($data),
        ]);

        return ['success' => true, 'data' => $data];
    }

    /**
     * @param  Builder<BookingAppointment>  $query
     * @return list<array<string, mixed>>
     */
    protected function mergeCalendarLocalAndExternal(Request $request, Builder $query, Carbon $startOfToday): array
    {
        $localQuery = clone $query;
        if ($this->calendarIncludePastInVisibleRange()) {
            $this->applyCalendarVisibleDatetimeWindow($localQuery, $request, $startOfToday);
        } else {
            $localQuery->where('appointment_datetime', '>=', $startOfToday);
        }
        $localModels = $localQuery->get();

        $localPayloads = $localModels
            ->map(fn (BookingAppointment $a) => $this->calendarExternalFeed->calendarPayloadFromModel($a))
            ->values()
            ->all();

        $localBansalKeys = $localModels->pluck('bansal_appointment_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->flip()
            ->all();

        $type = (string) $request->get('type', 'ajay');
        $apiStatus = $this->calendarApiStatusQueryValue();
        $serviceId = $this->calendarExternalFeed->resolveServiceIdForCalendarType($type);

        $normalized = $this->calendarExternalFeed->fetchAppointmentsNormalized(
            $serviceId,
            $apiStatus,
            $request->get('start'),
            $request->get('end'),
            true
        );
        if ($normalized === [] && ($request->filled('start') || $request->filled('end'))) {
            $normalized = $this->calendarExternalFeed->fetchAppointmentsNormalized(
                $serviceId,
                $apiStatus,
                null,
                null,
                true
            );
        }

        if ($this->calendarIncludePastInVisibleRange()) {
            $normalized = $this->filterNormalizedRowsForCalendarVisibleRange($request, $normalized, $startOfToday);
        } else {
            $normalized = array_values(array_filter($normalized, function (array $row) use ($startOfToday) {
                if (empty($row['appointment_datetime'])) {
                    return false;
                }
                try {
                    return Carbon::parse($row['appointment_datetime'], config('app.timezone'))->gte($startOfToday);
                } catch (Exception) {
                    return false;
                }
            }));
        }

        $extras = [];
        foreach ($normalized as $row) {
            $bid = $row['bansal_appointment_id'] ?? null;
            if ($bid && isset($localBansalKeys[(int) $bid])) {
                continue;
            }
            $extras[] = $this->calendarExternalFeed->toReadOnlyCalendarPayload($row);
        }

        return array_merge($localPayloads, $extras);
    }

    /**
     * Display appointment list (table rows load from CRM DB via POST /booking/api/appointments format=list).
     */
    public function index()
    {
        $consultants = AppointmentConsultant::active()->get();

        $statsBase = BookingAppointment::query();
        StaffClientVisibility::restrictBookingAppointmentEloquentQuery($statsBase);

        $stats = [
            'pending' => (clone $statsBase)->where('status', 'pending')->where('is_paid', 1)->count(),
            'paid' => (clone $statsBase)->where('status', 'paid')->where('is_paid', 1)->count(),
            'confirmed' => (clone $statsBase)->where('status', 'confirmed')->count(),
            'today' => (clone $statsBase)->whereDate('appointment_datetime', today())->count(),
            'total' => (clone $statsBase)->count(),
        ];

        $bookingListStatusForSelect = $this->calendarExternalFeed->websiteBookingsListResolvedStatusForUi(request('status'));

        return view('crm.booking.appointments.index', compact('consultants', 'stats', 'bookingListStatusForSelect'));
    }

    /**
     * Website bookings table JSON: proxies https://www.bansallawyers.com.au/api/appointments (see APPOINTMENT_API_URL).
     */
    protected function websiteBookingsListFromPublicApi(Request $request): \Illuminate\Http\JsonResponse
    {
        $bearer = config('services.appointment_api.bearer_token');
        $service = config('services.appointment_api.service_token');
        if (empty($bearer) && empty($service)) {
            return response()->json([
                'message' => 'Appointment API is not configured. Set APPOINTMENT_API_BEARER_TOKEN or APPOINTMENT_API_SERVICE_TOKEN in .env.',
            ], 503);
        }

        try {
            $bundle = $this->calendarExternalFeed->fetchWebsiteBookingsListFromPublicApi($request);
        } catch (\Throwable $e) {
            Log::error('Website bookings list: public API failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Could not load appointments from the website API: ' . $e->getMessage(),
            ], 502);
        }

        $rows = $bundle['rows'];
        $meta = $bundle['meta'];

        $bansalIds = collect($rows)->pluck('bansal_appointment_id')->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();
        $locals = collect();
        if ($bansalIds !== []) {
            $locals = BookingAppointment::with(['client', 'consultant'])
                ->whereIn('bansal_appointment_id', $bansalIds)
                ->get()
                ->keyBy(fn (BookingAppointment $m) => (int) $m->bansal_appointment_id);
        }

        $clientIds = $locals->pluck('client_id')->filter()->unique();
        $clientMatterRefs = [];
        if ($clientIds->isNotEmpty()) {
            $clientMatterRefs = ClientMatter::whereIn('client_id', $clientIds)
                ->select('client_id', 'client_unique_matter_no')
                ->orderByDesc('id')
                ->get()
                ->unique('client_id')
                ->pluck('client_unique_matter_no', 'client_id')
                ->toArray();
        }

        $data = [];
        foreach ($rows as $norm) {
            $bid = $norm['bansal_appointment_id'] ?? null;
            $local = $bid !== null ? ($locals[(int) $bid] ?? null) : null;

            if ($local) {
                $visibilityQuery = BookingAppointment::query()->whereKey($local->getKey());
                StaffClientVisibility::restrictBookingAppointmentEloquentQuery($visibilityQuery);
                if (! $visibilityQuery->exists()) {
                    continue;
                }
            }

            $data[] = $this->serializeWebsiteApiRowForBookingTable($norm, $local, $clientMatterRefs);
        }

        return response()->json([
            'data' => $data,
            'meta' => $meta,
        ]);
    }

    /**
     * Paginated bookings list for /booking/appointments — reads booking_appointments with client (admins) and consultants.
     */
    protected function crmBookingsListFromDatabase(Request $request): \Illuminate\Http\JsonResponse
    {
        $perPage = max(1, min(100, (int) $request->input('per_page', 20)));
        $page = max(1, (int) $request->input('page', 1));

        $query = BookingAppointment::query()->with(['client', 'consultant']);
        StaffClientVisibility::restrictBookingAppointmentEloquentQuery($query);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('consultant_id')) {
            $query->where('consultant_id', $request->consultant_id);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('appointment_datetime', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('appointment_datetime', '<=', $request->date_to);
        }
        if ($request->filled('search') && trim((string) $request->search) !== '') {
            $raw = trim((string) $request->search);
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $raw) . '%';
            $query->where(function (Builder $q) use ($like, $raw) {
                $q->where('client_name', 'like', $like)
                    ->orWhere('client_email', 'like', $like)
                    ->orWhere('client_phone', 'like', $like)
                    ->orWhere('enquiry_details', 'like', $like)
                    ->orWhere('service_type', 'like', $like);
                if (ctype_digit($raw)) {
                    $id = (int) $raw;
                    $q->orWhere('id', $id)
                        ->orWhere('bansal_appointment_id', $id);
                }
            });
        }

        $query->orderByDesc('appointment_datetime');

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        $clientIds = $paginator->getCollection()->pluck('client_id')->filter()->unique();
        $clientMatterRefs = [];
        if ($clientIds->isNotEmpty()) {
            $clientMatterRefs = ClientMatter::whereIn('client_id', $clientIds)
                ->select('client_id', 'client_unique_matter_no')
                ->orderByDesc('id')
                ->get()
                ->unique('client_id')
                ->pluck('client_unique_matter_no', 'client_id')
                ->toArray();
        }

        $data = [];
        foreach ($paginator->items() as $appointment) {
            $norm = $this->bookingAppointmentToNormArrayForList($appointment);
            $data[] = $this->serializeWebsiteApiRowForBookingTable($norm, $appointment, $clientMatterRefs);
        }

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function bookingAppointmentToNormArrayForList(BookingAppointment $a): array
    {
        $dt = $a->appointment_datetime;

        $norm = [
            'appointment_datetime' => $dt ? $dt->toIso8601String() : now()->toIso8601String(),
            'status' => $a->status,
            'status_label' => ucwords(str_replace('_', ' ', (string) $a->status)),
            'is_paid' => (bool) $a->is_paid,
            'final_amount' => $a->final_amount,
            'client_name' => $a->client_name,
            'client_email' => $a->client_email,
            'client_phone' => $a->client_phone,
            'enquiry_details' => $a->enquiry_details,
            'service_type' => $a->service_type,
            'enquiry_type' => $a->enquiry_type,
            'timeslot_full' => $a->timeslot_full,
            'location' => $a->location,
            'bansal_appointment_id' => $a->bansal_appointment_id,
            'consultant' => $a->consultant ? ['name' => $a->consultant->name] : null,
        ];

        $websiteCode = $a->website_status_code;
        if ($websiteCode === null
            && $a->status === 'paid'
            && $a->is_paid
            && ($a->payment_status ?? null) === 'completed') {
            $websiteCode = 10;
        }

        if ($websiteCode !== null) {
            $meta = BookingCalendarExternalFeed::websiteStatusCodeDisplayMeta((int) $websiteCode);
            if ($meta !== null) {
                $norm['website_status_code'] = (int) $websiteCode;
                $norm['status_label'] = $meta['label'];
                $norm['status_badge_class'] = $meta['badge'];
            }
        }

        return $norm;
    }

    /**
     * @param  array<string, mixed>  $norm
     * @param  array<int, string>  $clientMatterRefs
     * @return array<string, mixed>
     */
    protected function serializeWebsiteApiRowForBookingTable(array $norm, ?BookingAppointment $local, array $clientMatterRefs): array
    {
        $dt = Carbon::parse($norm['appointment_datetime']);

        $clientDetailUrl = null;
        $clientReference = null;
        if ($local && $local->client_id) {
            $encodedClientId = base64_encode(convert_uuencode($local->client_id));
            $latestMatterRef = $clientMatterRefs[$local->client_id] ?? null;
            $clientDetailUrl = $latestMatterRef
                ? route('clients.detail', [$encodedClientId, $latestMatterRef])
                : route('clients.detail', [$encodedClientId]);
            $clientReference = $local->client?->client_id;
        }

        $status = (string) ($norm['status'] ?? 'pending');
        $statusBadge = isset($norm['status_badge_class']) && is_string($norm['status_badge_class'])
            ? $norm['status_badge_class']
            : match ($status) {
                'pending' => 'warning',
                'paid' => 'primary',
                'confirmed' => 'success',
                'completed' => 'info',
                'cancelled' => 'danger',
                'no_show' => 'dark',
                default => 'secondary',
            };

        $statusLabel = $norm['status_label'] ?? ucwords(str_replace('_', ' ', $status));

        $consultantName = null;
        if ($local && $local->consultant) {
            $consultantName = $local->consultant->name;
        } elseif (! empty($norm['consultant']) && is_array($norm['consultant'])) {
            $consultantName = $norm['consultant']['name'] ?? null;
        }

        $isPaid = (bool) ($norm['is_paid'] ?? false);
        $finalAmount = $norm['final_amount'] ?? 0;

        $enquiryDetails = $norm['enquiry_details'] ?? null;
        $enquiryDetailsShort = $enquiryDetails ? Str::limit((string) $enquiryDetails, 100) : null;

        $serviceType = $norm['service_type'] ?? null;
        $enquiryType = $norm['enquiry_type'] ?? null;

        $timeslotFull = $norm['timeslot_full'] ?? null;
        if (! is_string($timeslotFull) || trim($timeslotFull) === '') {
            $timeslotFull = null;
        }

        $location = $norm['location'] ?? null;

        $crmId = $local?->id;
        $websiteId = $norm['bansal_appointment_id'] ?? null;
        $displayId = $crmId ?? $websiteId;

        $showUrl = $local ? route('booking.appointments.show', $local->id) : null;
        $editUrl = $local ? route('booking.appointments.edit', $local->id) : null;

        return [
            'id' => $displayId,
            'crm_appointment_id' => $crmId,
            'website_appointment_id' => $websiteId,
            'website_status_code' => $norm['website_status_code'] ?? $local?->website_status_code,
            'client_name' => $local ? $local->client_name : ($norm['client_name'] ?? ''),
            'client_email' => $local ? $local->client_email : ($norm['client_email'] ?? ''),
            'client_phone' => $local ? $local->client_phone : ($norm['client_phone'] ?? ''),
            'client_id' => $local?->client_id,
            'client_reference' => $clientReference,
            'client_detail_url' => $clientDetailUrl,
            'appointment_date_label' => $dt->format('d M Y'),
            'appointment_time_label' => $dt->format('h:i A'),
            'timeslot_full' => $timeslotFull,
            'location' => $location,
            'service_type' => $serviceType ?: null,
            'enquiry_type' => $enquiryType,
            'enquiry_details' => $enquiryDetails,
            'enquiry_details_short' => $enquiryDetailsShort,
            'consultant_name' => $consultantName,
            'status' => $status,
            'status_label' => $statusLabel,
            'status_badge_class' => $statusBadge,
            'is_paid' => $isPaid,
            'final_amount' => $finalAmount,
            'show_url' => $showUrl,
            'edit_url' => $editUrl,
        ];
    }

    /**
     * Get appointments for DataTables
     */
    public function getAppointments(Request $request)
    {
        if ($request->get('format') === 'list') {
            return $this->crmBookingsListFromDatabase($request);
        }

        $query = BookingAppointment::with(['client', 'consultant']);
        StaffClientVisibility::restrictBookingAppointmentEloquentQuery($query);

        // Filter by calendar type (consultant type), or explicit consultant_id for local-only calendars (e.g. ajay → id 2)
        if ($request->filled('type')) {
            $this->applyBookingCalendarTypeScope($query, (string) $request->type);
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by consultant
        if ($request->filled('consultant_id')) {
            $query->where('consultant_id', $request->consultant_id);
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->whereDate('appointment_datetime', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('appointment_datetime', '<=', $request->date_to);
        }

        // Check if calendar format is requested
        if ($request->get('format') === 'calendar') {
            return response()->json($this->buildCalendarFeedResponse($request, $query));
        }

        // Default: Return DataTables format ordered by ID (descending - newest first)
        $query->orderByDesc('id');

        return DataTables::of($query)
            ->addColumn('client_info', function ($appointment) {
                if ($appointment->client_id) {
                    $clientLink = route('clients.detail', base64_encode(convert_uuencode($appointment->client_id)));
                    
                    return '<a href="' . $clientLink . '" target="_blank">' . 
                           '<strong>' . e($appointment->client_name) . '</strong><br>' .
                           '<small>' . e($appointment->client_email) . '</small>' .
                           '</a>';
                }
                
                return '<strong>' . e($appointment->client_name) . '</strong><br>' .
                       '<small>' . e($appointment->client_email) . '</small>';
            })
            ->addColumn('appointment_info', function ($appointment) {
                return '<strong>' . $appointment->appointment_datetime->format('d/m/Y') . '</strong><br>' .
                       '<small>' . ($appointment->timeslot_full ?? $appointment->appointment_datetime->format('h:i A')) . '</small>';
            })
            ->addColumn('consultant_info', function ($appointment) {
                return $appointment->consultant 
                    ? '<span class="badge badge-info">' . e($appointment->consultant->name) . '</span>'
                    : '<span class="badge badge-secondary">Unassigned</span>';
            })
            ->addColumn('status_badge', function ($appointment) {
                $color = $appointment->status_badge;
                $label = ucfirst(str_replace('_', ' ', $appointment->status));
                return '<span class="badge badge-' . $color . '">' . $label . '</span>';
            })
            ->addColumn('payment_info', function ($appointment) {
                if ($appointment->is_paid) {
                    return '<span class="badge badge-success">Paid</span><br>' .
                           '<small>$' . number_format($appointment->final_amount, 2) . '</small>';
                }
                return '<span class="badge badge-secondary">Free</span>';
            })
            ->addColumn('actions', function ($appointment) {
                return '<a href="' . route('booking.appointments.show', $appointment->id) . '" class="btn btn-sm btn-primary">' .
                       '<i class="fas fa-eye"></i> View' .
                       '</a>';
            })
            ->rawColumns(['client_info', 'appointment_info', 'consultant_info', 'status_badge', 'payment_info', 'actions'])
            ->make(true);
    }

    /**
     * Show appointment detail
     */
    public function show($id)
    {
        $appointment = BookingAppointment::with(['client', 'consultant', 'assignedBy'])->findOrFail($id);
        $this->assertBookingAppointmentAccess($appointment);
        $consultants = AppointmentConsultant::active()->get();
        $latestClientMatter = null;

        if ($appointment->client_id) {
            $latestClientMatter = ClientMatter::where('client_id', $appointment->client_id)
                ->orderByDesc('id')
                ->first();
        }
        
        return view('crm.booking.appointments.show', compact('appointment', 'consultants', 'latestClientMatter'));
    }

    /**
     * Show edit appointment form (date & time only).
     */
    public function edit($id)
    {
        $appointment = BookingAppointment::with(['client', 'consultant'])->findOrFail($id);
        $this->assertBookingAppointmentAccess($appointment);
        return view('crm.booking.appointments.edit', compact('appointment'));
    }

    /**
     * KPI header cards for the booking calendar (shared with JSON refresh for first-load reliability).
     *
     * @return array{this_month: int, today: int, upcoming: int, pending: int, paid: int, no_show: int}
     */
    protected function calendarHeaderStatsForType(string $type): array
    {
        $calendarStatsBase = function () use ($type) {
            $q = BookingAppointment::query();
            $this->applyBookingCalendarTypeScope($q, $type);
            StaffClientVisibility::restrictBookingAppointmentEloquentQuery($q);

            return $q;
        };

        $calendarSource = config('booking_calendar.data_source', 'local');
        if ($this->calendarTypeUsesLocalDbOnly($type)) {
            $calendarSource = 'local';
        }
        $apiStatus = $this->calendarApiStatusQueryValue();

        if ($calendarSource === 'external') {
            return $this->calendarExternalFeed->computeStats($type, $apiStatus);
        }

        if ($calendarSource === 'merge') {
            $localStats = [
                'this_month' => (clone $calendarStatsBase())->whereMonth('appointment_datetime', now()->month)->count(),
                'today' => (clone $calendarStatsBase())->whereDate('appointment_datetime', today())->count(),
                'upcoming' => (clone $calendarStatsBase())->where('appointment_datetime', '>', now())->count(),
                'pending' => (clone $calendarStatsBase())->where('status', 'pending')->where('is_paid', 1)->count(),
                'paid' => (clone $calendarStatsBase())->where('status', 'paid')->where('is_paid', 1)->count(),
                'no_show' => (clone $calendarStatsBase())->where('status', 'no_show')->count(),
            ];

            return $this->calendarExternalFeed->computeMergeStatsWithLocal($type, $apiStatus, $localStats);
        }

        return [
            'this_month' => (clone $calendarStatsBase())->whereMonth('appointment_datetime', now()->month)->count(),
            'today' => (clone $calendarStatsBase())->whereDate('appointment_datetime', today())->count(),
            'upcoming' => (clone $calendarStatsBase())->where('appointment_datetime', '>', now())->count(),
            'pending' => (clone $calendarStatsBase())->where('status', 'pending')->where('is_paid', 1)->count(),
            'paid' => (clone $calendarStatsBase())->where('status', 'paid')->where('is_paid', 1)->count(),
            'no_show' => (clone $calendarStatsBase())->where('status', 'no_show')->count(),
        ];
    }

    /**
     * JSON stats for calendar header cards (retried client-side when SSR/API is cold).
     */
    public function calendarStatsJson(string $type)
    {
        $validTypes = ['ajay', 'kunal'];
        if (! in_array($type, $validTypes, true)) {
            return response()->json(['success' => false, 'message' => 'Invalid calendar type'], 404);
        }

        try {
            $stats = $this->calendarHeaderStatsForType($type);
        } catch (Exception $e) {
            Log::error('calendarStatsJson failed', ['type' => $type, 'error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }

        return response()->json(['success' => true, 'data' => $stats]);
    }

    /**
     * Calendar view by type
     */
    public function calendar($type)
    {
        $validTypes = ['ajay', 'kunal'];
        
        if (!in_array($type, $validTypes)) {
            abort(404);
        }

        $localConsultantId = $this->calendarLocalConsultantIdForType($type);
        $appointmentsQuery = BookingAppointment::with(['client', 'consultant']);
        if ($localConsultantId !== null) {
            $appointmentsQuery->where('consultant_id', $localConsultantId);
        } else {
            $appointmentsQuery->where(function ($query) use ($type) {
                $query->whereHas('consultant', function ($q) use ($type) {
                    $q->where('calendar_type', $type);
                })->orWhereNull('consultant_id');
            });
        }
        $appointmentsQuery
            ->where('appointment_datetime', '>', Carbon::now(config('app.timezone')))
            ->orderBy('appointment_datetime');
        StaffClientVisibility::restrictBookingAppointmentEloquentQuery($appointmentsQuery);
        $appointments = $appointmentsQuery->get();

        $calendarTitle = match($type) {
            'ajay' => 'Ajay Calendar',
            'kunal' => 'Michael',
            default => ucfirst($type)
        };

        $stats = $this->calendarHeaderStatsForType($type);

        $consultants = AppointmentConsultant::active()->get();

        // Use FullCalendar v6 version
        return view('crm.booking.appointments.calendar-v6', compact('type', 'appointments', 'calendarTitle', 'stats', 'consultants'));
    }

    /**
     * Update appointment status
     */
    public function updateStatus(Request $request, $id)
    {
        $appointment = BookingAppointment::findOrFail($id);
        $this->assertBookingAppointmentAccess($appointment);

        $request->validate([
            'status' => 'required|in:pending,paid,confirmed,completed,cancelled,no_show,rescheduled',
            'cancellation_reason' => 'required_if:status,cancelled|nullable|string'
        ]);

        $oldStatus = $appointment->status;
        $appointment->status = $request->status;

        // Set timestamp based on status
        switch ($request->status) {
            case 'confirmed':
                $appointment->confirmed_at = now();
                break;
            case 'completed':
                $appointment->completed_at = now();
                break;
            case 'cancelled':
                $appointment->cancelled_at = now();
                // Cancellation reason is now required when status is cancelled
                $appointment->cancellation_reason = $request->cancellation_reason ?? null;
                break;
        }

        $appointment->save();

        $syncError = null;
        $shouldSyncStatus = in_array($request->status, ['cancelled', 'completed', 'confirmed']);

        if ($shouldSyncStatus) {
            if ($appointment->bansal_appointment_id) {
                try {
                    $this->syncService->pushStatusUpdate(
                        $appointment,
                        $request->status,
                        $request->status === 'cancelled' ? $request->cancellation_reason : null
                    );

                    $appointment->forceFill([
                        'last_synced_at' => now(),
                        'sync_status' => 'synced',
                        'sync_error' => null,
                    ])->save();
                } catch (Exception $e) {
                    $syncError = $e->getMessage();

                    Log::error('Failed to sync appointment status with Bansal API', [
                        'appointment_id' => $appointment->id,
                        'bansal_appointment_id' => $appointment->bansal_appointment_id,
                        'status' => $request->status,
                        'error' => $syncError,
                    ]);

                    $appointment->forceFill([
                        'sync_status' => 'error',
                        'sync_error' => $syncError,
                    ])->save();
                }
            } else {
                Log::warning('Skipping Bansal sync because appointment is missing bansal_appointment_id', [
                    'appointment_id' => $appointment->id,
                    'status' => $request->status,
                ]);
                $syncError = 'Missing website booking identifier.';
            }
        }

        // Log activity using existing codebase pattern (only if client exists)
        if ($appointment->client_id) {
            $activityLog = new ActivitiesLog;
            $activityLog->client_id = $appointment->client_id;
            $activityLog->created_by = Auth::id();
            $activityLog->subject = 'Booking appointment status updated';
            $activityLog->description = '<p><strong>Status changed:</strong> ' . ucfirst($oldStatus) . ' → ' . ucfirst($request->status) . '</p>' .
                                       ($request->cancellation_reason ? '<p><strong>Reason:</strong> ' . e($request->cancellation_reason) . '</p>' : '');
            $activityLog->task_status = 0;
            $activityLog->pin = 0;
            $activityLog->save();
        }

        // Send cancellation confirmation email to client if requested
        if ($request->status === 'cancelled' && $request->boolean('send_cancellation_confirmation')) {
            try {
                $notificationService = app(\App\Services\BansalAppointmentSync\NotificationService::class);
                $notificationService->sendCancellationConfirmationEmail(
                    $appointment->fresh(),
                    $request->cancellation_reason
                );
            } catch (Exception $e) {
                Log::error('Failed to send appointment cancellation confirmation email', [
                    'appointment_id' => $appointment->id,
                    'client_email' => $appointment->client_email,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        $message = $syncError
            ? 'Status updated locally. Sync with website failed: ' . $syncError
            : 'Status updated successfully';

        return response()->json([
            'success' => true,
            'message' => $message,
            'sync_error' => $syncError
        ]);
    }

    /**
     * Update consultant assignment
     */
    public function updateConsultant(Request $request, $id)
    {
        try {
            $appointment = BookingAppointment::findOrFail($id);
            $this->assertBookingAppointmentAccess($appointment);

            $request->validate([
                'consultant_id' => 'required|exists:appointment_consultants,id'
            ]);

            $oldConsultantId = $appointment->consultant_id;
            $appointment->consultant_id = $request->consultant_id;
            
            // Only set assigned_by_admin_id if user is authenticated and exists
            $adminId = Auth::id();
            if ($adminId) {
                // Verify admin exists before assigning (prevents FK constraint violation)
                $adminExists = Admin::where('id', $adminId)->exists();
                if ($adminExists) {
                    $appointment->assigned_by_admin_id = $adminId;
                }
                // If admin doesn't exist, leave it as null (column is nullable)
            }
            // If Auth::id() is null, leave assigned_by_admin_id as null (column is nullable)
            
            $appointment->save();

            // Log activity using existing codebase pattern (only if client exists)
            if ($appointment->client_id) {
                $consultant = AppointmentConsultant::find($request->consultant_id);
                $activityLog = new ActivitiesLog;
                $activityLog->client_id = $appointment->client_id;
                $activityLog->created_by = Auth::id();
                $activityLog->subject = 'Booking appointment consultant reassigned';
                $activityLog->description = '<p><strong>Consultant assigned:</strong> ' . ($consultant ? e($consultant->name) : 'N/A') . '</p>';
                $activityLog->task_status = 0;
                $activityLog->pin = 0;
                $activityLog->save();
            }

            return response()->json([
                'success' => true,
                'message' => 'Consultant assigned successfully'
            ]);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Return JSON for validation errors
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            // Return JSON for not found errors
            return response()->json([
                'success' => false,
                'message' => 'Appointment not found'
            ], 404);
            
        } catch (\Illuminate\Database\QueryException $e) {
            // Log database-specific errors with more details
            $errorCode = $e->getCode();
            $errorMessage = $e->getMessage();
            
            Log::error('Database error updating consultant', [
                'appointment_id' => $id,
                'error_code' => $errorCode,
                'error_message' => $errorMessage,
                'sql_state' => $e->errorInfo[0] ?? null,
                'sql_code' => $e->errorInfo[1] ?? null,
                'sql_message' => $e->errorInfo[2] ?? null,
                'trace' => $e->getTraceAsString()
            ]);
            
            // Check for specific database errors
            if (strpos($errorMessage, 'assigned_by_admin_id') !== false) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error: assigned_by_admin_id column issue. Please check database schema.'
                ], 500);
            }
            
            if (strpos($errorMessage, 'foreign key constraint') !== false || strpos($errorMessage, 'a foreign key constraint fails') !== false) {
                return response()->json([
                    'success' => false,
                    'message' => 'Foreign key constraint violation. The admin user may not exist in the system.'
                ], 500);
            }
            
            if (strpos($errorMessage, "doesn't exist") !== false || strpos($errorMessage, 'Unknown column') !== false) {
                return response()->json([
                    'success' => false,
                    'message' => 'Database column missing. Please run migrations on production server.'
                ], 500);
            }
            
            // Return JSON for any other database errors
            return response()->json([
                'success' => false,
                'message' => 'Database error occurred while updating consultant. Please check server logs.'
            ], 500);
            
        } catch (\Exception $e) {
            // Log the error
            Log::error('Error updating consultant', [
                'appointment_id' => $id,
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Return JSON for any other errors
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while updating consultant. Please try again.'
            ], 500);
        }
    }

    /**
     * Update meeting type
     */
    public function updateMeetingType(Request $request, $id)
    {
        try {
            $appointment = BookingAppointment::findOrFail($id);
            $this->assertBookingAppointmentAccess($appointment);

            $request->validate([
                'meeting_type' => 'required|in:in_person,phone,video'
            ]);

            // Validate: Video meeting type is only allowed for paid appointments
            if ($request->meeting_type === 'video' && !$appointment->is_paid) {
                return response()->json([
                    'success' => false,
                    'message' => 'Video meeting type is only available for paid appointments.'
                ], 422);
            }

            $oldMeetingType = $appointment->meeting_type;
            $appointment->meeting_type = $request->meeting_type;
            $appointment->save();

            // Log activity using existing codebase pattern (only if client exists)
            if ($appointment->client_id) {
                $oldDisplay = ucfirst(str_replace('_', ' ', $oldMeetingType));
                $newDisplay = ucfirst(str_replace('_', ' ', $request->meeting_type));
                
                $activityLog = new ActivitiesLog;
                $activityLog->client_id = $appointment->client_id;
                $activityLog->created_by = Auth::id();
                $activityLog->subject = 'Booking appointment meeting type updated';
                $activityLog->description = '<p><strong>Meeting type changed:</strong> ' . $oldDisplay . ' → ' . $newDisplay . '</p>';
                $activityLog->task_status = 0;
                $activityLog->pin = 0;
                $activityLog->save();
            }

            return response()->json([
                'success' => true,
                'message' => 'Meeting type updated successfully',
                'meeting_type' => $appointment->meeting_type
            ]);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Appointment not found'
            ], 404);
            
        } catch (\Exception $e) {
            Log::error('Error updating meeting type', [
                'appointment_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while updating meeting type. Please try again.'
            ], 500);
        }
    }

    /**
     * Update appointment date and time.
     */
    public function update(Request $request, $id)
    {  
        $appointment = BookingAppointment::findOrFail($id);
        $this->assertBookingAppointmentAccess($appointment);

        $request->validate([
            'appointment_date' => 'required|date',
            'appointment_time' => 'required|date_format:H:i',
            'meeting_type' => 'required|in:in_person,phone,video',
            'preferred_language' => 'required|string|in:English,Hindi,Punjabi',
        ]);

        // Validate: Video meeting type is only allowed for paid appointments
        if ($request->meeting_type === 'video' && !$appointment->is_paid) {
            return $this->handleUpdateError($request, 'Video meeting type is only available for paid appointments.', 422);
        }

        $oldDatetime = $appointment->appointment_datetime;
        $oldMeetingType = $appointment->meeting_type;
        $oldPreferredLanguage = $appointment->preferred_language ?? 'English';
        
        try {
            $newDatetime = Carbon::createFromFormat(
                'Y-m-d H:i',
                $request->appointment_date . ' ' . $request->appointment_time,
                config('app.timezone')
            );
        } catch (Exception $e) {
            return $this->handleUpdateError($request, 'Invalid date or time provided.', 422, $e->getMessage());
        }

        // Check if anything has changed
        $datetimeChanged = !$oldDatetime || !$oldDatetime->equalTo($newDatetime);
        $meetingTypeChanged = $oldMeetingType !== $request->meeting_type;
        $preferredLanguageChanged = $oldPreferredLanguage !== $request->preferred_language;
        
        if (!$datetimeChanged && !$meetingTypeChanged && !$preferredLanguageChanged) {
            $message = 'No changes detected. Appointment details remain unchanged.';

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $message,
                ]);
            }

            return redirect()
                ->back()
                ->with('success', $message);
        }

        // Update appointment fields in local database FIRST (always update locally)
        if ($datetimeChanged) {
            $appointment->appointment_datetime = $newDatetime;
            $appointment->timeslot_full = $newDatetime->format('h:i A');
        }
        
        if ($meetingTypeChanged) {
            $appointment->meeting_type = $request->meeting_type;
        }
        
        if ($preferredLanguageChanged) {
            $appointment->preferred_language = $request->preferred_language;
        }

        // Try to sync with Bansal API if any field changed AND bansal_appointment_id exists
        $syncError = null;
        $apiSynced = false;
        $newBansalAppointmentId = null;

        if (($datetimeChanged || $meetingTypeChanged || $preferredLanguageChanged) && !empty($appointment->bansal_appointment_id)) {
            try {
                // Determine which fields to send to API
                // Always send date and time (use new values if changed, otherwise current values)
                $apiDate = $datetimeChanged ? $request->appointment_date : $appointment->appointment_datetime->format('Y-m-d');
                $apiTime = $datetimeChanged ? $request->appointment_time : $appointment->appointment_datetime->format('H:i');
                
                // Always send meeting_type - use new value if changed, otherwise current appointment value
                // API requires meeting_type when preferred_language is sent, so always send it
                $apiMeetingType = $meetingTypeChanged ? $request->meeting_type : ($appointment->meeting_type ?? 'in_person');
                
                // Always send preferred_language - use new value if changed, otherwise current appointment value
                // API requires preferred_language when meeting_type is sent, so always send it
                $apiPreferredLanguage = $preferredLanguageChanged ? $request->preferred_language : ($appointment->preferred_language ?? 'English');

                $apiResponse = $this->bansalApiClient->rescheduleAppointment(
                    (int) $appointment->bansal_appointment_id,
                    $apiDate,
                    $apiTime,
                    $apiMeetingType,
                    $apiPreferredLanguage
                );

                if ($apiResponse['success'] ?? false) {
                    $apiSynced = true;
                    $appointment->last_synced_at = now();
                    $appointment->sync_status = 'synced';
                    $appointment->sync_error = null;
                } else {
                    $errorMessage = $apiResponse['message'] ?? 'Failed to update appointment on website.';
                    $errors = $apiResponse['errors'] ?? [];
                    
                    // Check if error is "invalid appointment id" - if so, try to create new appointment
                    if (strpos(strtolower($errorMessage), 'appointment id is invalid') !== false || 
                        (isset($errors['appointment_id']) && strpos(strtolower(implode(' ', $errors['appointment_id'])), 'invalid') !== false)) {
                        
                        // Try to create new appointment via API
                        try {
                            $newBansalAppointmentId = $this->createAppointmentViaApi($appointment, $request, $datetimeChanged, $meetingTypeChanged, $preferredLanguageChanged);
                            
                            if ($newBansalAppointmentId) {
                                // Update appointment with new bansal_appointment_id
                                $appointment->bansal_appointment_id = $newBansalAppointmentId;
                                $appointment->last_synced_at = now();
                                $appointment->sync_status = 'synced';
                                $appointment->sync_error = null;
                                $apiSynced = true;
                            } else {
                                $syncError = 'Failed to create appointment on website. Original error: ' . $errorMessage;
                                $appointment->sync_status = 'error';
                                $appointment->sync_error = $syncError;
                            }
                        } catch (Exception $createException) {
                            $syncError = 'Failed to create appointment on website: ' . $createException->getMessage();
                            $appointment->sync_status = 'error';
                            $appointment->sync_error = $syncError;
                            
                            Log::warning('Failed to create appointment via API after invalid ID error', [
                                'appointment_id' => $appointment->id,
                                'old_bansal_appointment_id' => $appointment->bansal_appointment_id,
                                'error' => $createException->getMessage(),
                            ]);
                        }
                    } else {
                        // Other API error (not invalid ID)
                        $syncError = $errorMessage;
                        $appointment->sync_status = 'error';
                        $appointment->sync_error = $syncError;
                    }
                }
            } catch (Exception $e) {
                $syncError = $e->getMessage();
                
                // Check if error is "invalid appointment id" - if so, try to create new appointment
                if (strpos(strtolower($syncError), 'appointment id is invalid') !== false) {
                    // Try to create new appointment via API
                    try {
                        $newBansalAppointmentId = $this->createAppointmentViaApi($appointment, $request, $datetimeChanged, $meetingTypeChanged, $preferredLanguageChanged);
                        
                        if ($newBansalAppointmentId) {
                            // Update appointment with new bansal_appointment_id
                            $appointment->bansal_appointment_id = $newBansalAppointmentId;
                            $appointment->last_synced_at = now();
                            $appointment->sync_status = 'synced';
                            $appointment->sync_error = null;
                            $apiSynced = true;
                            $syncError = null; // Clear error since we successfully created new appointment
                        } else {
                            $syncError = 'Failed to create appointment on website. Original error: ' . $syncError;
                            $appointment->sync_status = 'error';
                            $appointment->sync_error = $syncError;
                        }
                    } catch (Exception $createException) {
                        $createErrorMessage = $createException->getMessage();
                        
                        // Provide user-friendly error messages based on error type
                        if (stripos($createErrorMessage, 'time is outside of available booking hours') !== false || 
                            stripos($createErrorMessage, 'outside of available booking hours') !== false) {
                            $syncError = 'The selected appointment time is not available for booking. Please choose a different time slot.';
                        } elseif (stripos($createErrorMessage, 'time slot') !== false || 
                                  stripos($createErrorMessage, 'slot') !== false) {
                            $syncError = 'The selected time slot is not available. Please choose a different time.';
                        } else {
                            $syncError = 'Failed to create appointment on website: ' . $createErrorMessage;
                        }
                        
                        $appointment->sync_status = 'error';
                        $appointment->sync_error = $syncError;
                        
                        Log::warning('Failed to create appointment via API after invalid ID error', [
                            'appointment_id' => $appointment->id,
                            'old_bansal_appointment_id' => $appointment->bansal_appointment_id,
                            'error' => $createErrorMessage,
                            'appointment_date' => $appointment->appointment_datetime->format('Y-m-d'),
                            'appointment_time' => $appointment->appointment_datetime->format('H:i'),
                        ]);
                    }
                } else {
                    // Other exception (not invalid ID)
                    $appointment->sync_status = 'error';
                    $appointment->sync_error = $syncError;
                    
                    // Log other exceptions (not invalid ID errors)
                    Log::warning('Failed to sync appointment update with Bansal API', [
                        'appointment_id' => $appointment->id,
                        'bansal_appointment_id' => $appointment->bansal_appointment_id,
                        'error' => $syncError,
                    ]);
                }
                
                // Note: We don't log here if it was an "invalid ID" error because:
                // - If creation succeeded: $syncError is null, so no log needed
                // - If creation failed: Already logged in the inner catch block above
            }
        } else {
            // No bansal_appointment_id, so just update locally
            if ($datetimeChanged || $meetingTypeChanged || $preferredLanguageChanged) {
                $appointment->sync_status = 'new';
                $appointment->sync_error = null;
            }
        }
        
        $appointment->save();

        // Log activity if client exists
        if ($appointment->client_id) {
            $activityLog = new ActivitiesLog;
            $activityLog->client_id = $appointment->client_id;
            $activityLog->created_by = Auth::id();
            $activityLog->task_status = 0;
            $activityLog->pin = 0;

            $descriptionParts = [];
            
            if ($datetimeChanged) {
                $from = $oldDatetime ? $oldDatetime->format('d M Y, h:i A') : 'N/A';
                $to = $newDatetime->format('d M Y, h:i A');
                $descriptionParts[] = sprintf(
                    '<p><strong>Appointment rescheduled:</strong> %s → %s</p>',
                    e($from),
                    e($to)
                );
            }
            
            if ($meetingTypeChanged) {
                $oldDisplay = ucfirst(str_replace('_', ' ', $oldMeetingType));
                $newDisplay = ucfirst(str_replace('_', ' ', $request->meeting_type));
                $descriptionParts[] = sprintf(
                    '<p><strong>Meeting type changed:</strong> %s → %s</p>',
                    e($oldDisplay),
                    e($newDisplay)
                );
            }
            
            if ($preferredLanguageChanged) {
                $descriptionParts[] = sprintf(
                    '<p><strong>Preferred language changed:</strong> %s → %s</p>',
                    e($oldPreferredLanguage),
                    e($request->preferred_language)
                );
            }

            if (!empty($descriptionParts)) {
                $activityLog->subject = 'Booking appointment updated';
                $activityLog->description = implode('', $descriptionParts);
                $activityLog->save();
            }
        }

        // Build success message
        $messageParts = [];
        if ($datetimeChanged) {
            $messageParts[] = 'date and time';
        }
        if ($meetingTypeChanged) {
            $messageParts[] = 'meeting type';
        }
        if ($preferredLanguageChanged) {
            $messageParts[] = 'preferred language';
        }
        
        $message = 'Appointment ' . implode(', ', $messageParts) . ' updated successfully.';
        
        // Add warning if API sync failed
        if ($syncError) {
            $message .= ' However, sync with website failed: ' . $syncError;
        } elseif ($newBansalAppointmentId) {
            $message .= ' Note: A new appointment was created on the website (previous appointment ID was invalid).';
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'appointment_datetime' => $appointment->appointment_datetime->toIso8601String(),
            ]);
        }

        return redirect()
            ->route('booking.appointments.show', $appointment->id)
            ->with('success', $message);
    }

    /**
     * Create appointment via Bansal API when update fails due to invalid appointment ID
     */
    protected function createAppointmentViaApi(BookingAppointment $appointment, Request $request, bool $datetimeChanged, bool $meetingTypeChanged, bool $preferredLanguageChanged): ?int
    {
        try {
            // Use updated values from appointment object (already updated locally but not saved yet)
            // Map meeting_type from database format to API format
            $currentMeetingType = $meetingTypeChanged ? $request->meeting_type : ($appointment->meeting_type ?? 'in_person');
            $meetingTypeForApi = match($currentMeetingType) {
                'video' => 'video-call',
                'in_person' => 'in-person',
                'phone' => 'phone',
                default => 'in-person'
            };
            
            // Get updated datetime
            $appointmentDatetime = $datetimeChanged ? $appointment->appointment_datetime : $appointment->appointment_datetime;
            
            // Get updated preferred language
            $currentPreferredLanguage = $preferredLanguageChanged ? $request->preferred_language : ($appointment->preferred_language ?? 'English');
            
            // Determine specific_service from enquiry_type or service_type
            $specificService = $this->determineSpecificService($appointment);
            
            // Build payload for createAppointment API
            $payload = [
                'full_name' => $appointment->client_name,
                'email' => $appointment->client_email,
                'phone' => $appointment->client_phone ?? '',
                'appointment_date' => $appointmentDatetime->format('Y-m-d'),
                'appointment_time' => $appointmentDatetime->format('H:i'),
                'appointment_datetime' => $appointmentDatetime->format('Y-m-d H:i:s'),
                'duration_minutes' => $appointment->duration_minutes ?? 15,
                'location' => $appointment->location ?? 'melbourne',
                'meeting_type' => $meetingTypeForApi,
                'preferred_language' => $currentPreferredLanguage,
                'specific_service' => $specificService,
                'enquiry_type' => $appointment->enquiry_type ?? 'pr_complex',
                'service_type' => $appointment->service_type ?? 'Permanent Residency',
                'enquiry_details' => $appointment->enquiry_details ?? '',
                'is_paid' => $appointment->is_paid ?? false,
                'amount' => $appointment->amount ?? 0,
                'final_amount' => $appointment->final_amount ?? 0,
                'payment_status' => $appointment->payment_status ?? ($appointment->is_paid ? 'pending' : null),
                'slot_overwrite' => 0,
            ];
            
            $apiResponse = $this->bansalApiClient->createAppointment($payload);
            
            if ($apiResponse['success'] ?? false) {
                // Extract new bansal_appointment_id from response
                if (isset($apiResponse['data']['id'])) {
                    return (int) $apiResponse['data']['id'];
                } elseif (isset($apiResponse['data']['appointment_id'])) {
                    return (int) $apiResponse['data']['appointment_id'];
                } elseif (isset($apiResponse['appointment_id'])) {
                    return (int) $apiResponse['appointment_id'];
                }
            }
            
            Log::warning('Bansal API createAppointment returned success but no appointment ID', [
                'appointment_id' => $appointment->id,
                'response' => $apiResponse,
            ]);
            
            return null;
        } catch (Exception $e) {
            $errorMessage = $e->getMessage();
            
            // Log as WARNING instead of ERROR since this is expected in some cases
            // (e.g., when time slot is not available)
            Log::warning('Failed to create appointment via API', [
                'appointment_id' => $appointment->id,
                'error' => $errorMessage,
                'appointment_date' => $appointment->appointment_datetime->format('Y-m-d'),
                'appointment_time' => $appointment->appointment_datetime->format('H:i'),
            ]);
            
            // Re-throw the exception so it can be handled by the caller
            throw $e;
        }
    }
    
    /**
     * Determine specific_service for API based on appointment data
     */
    protected function determineSpecificService(BookingAppointment $appointment): string
    {
        // If enquiry_type exists, try to map it
        if ($appointment->enquiry_type) {
            $enquiryType = strtolower($appointment->enquiry_type);
            
            // Map common enquiry types to specific_service
            if (strpos($enquiryType, 'overseas') !== false || $enquiryType === 'international') {
                return 'overseas-enquiry';
            } elseif ($appointment->is_paid) {
                return 'paid-consultation';
            } else {
                return 'consultation';
            }
        }
        
        // Fallback based on is_paid
        if ($appointment->is_paid) {
            return 'paid-consultation';
        }
        
        return 'consultation';
    }

    protected function handleUpdateError(Request $request, string $message, int $status = 422, $context = null)
    {
        if ($context) {
            Log::warning('Booking appointment update failed', [
                'message' => $message,
                'context' => $context,
            ]);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => $message,
            ], $status);
        }

        return redirect()
            ->back()
            ->withInput()
            ->with('error', $message);
    }

    /**
     * Add admin note
     */
    public function addNote(Request $request, $id)
    {
        $appointment = BookingAppointment::findOrFail($id);
        $this->assertBookingAppointmentAccess($appointment);

        $request->validate([
            'note' => 'required|string|max:2000'
        ]);

        $timestamp = now()->format('Y-m-d H:i');
        $adminName = Auth::user()->first_name . ' ' . Auth::user()->last_name;
        $newNote = "[{$timestamp} - {$adminName}]\n" . $request->note;

        $appointment->admin_notes = $appointment->admin_notes 
            ? $appointment->admin_notes . "\n\n" . $newNote
            : $newNote;
        
        $appointment->save();

        // Log activity using existing codebase pattern
        if ($appointment->client_id) {
            $activityLog = new ActivitiesLog;
            $activityLog->client_id = $appointment->client_id;
            $activityLog->created_by = Auth::id();
            $activityLog->subject = 'Note added to booking appointment';
            $activityLog->description = '<p>' . e($request->note) . '</p>';
            $activityLog->task_status = 0;
            $activityLog->pin = 0;
            $activityLog->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'Note added successfully',
            'notes' => $appointment->admin_notes
        ]);
    }

    /**
     * Sync dashboard
     */
    public function syncDashboard()
    {
        // Get sync logs with pagination
        $syncLogs = AppointmentSyncLog::orderBy('created_at', 'desc')->paginate(20);
        
        // Get last successful sync
        $lastSync = AppointmentSyncLog::where('status', 'success')
            ->latest('created_at')
            ->first();
        
        // Determine system status
        $lastLog = AppointmentSyncLog::latest('created_at')->first();
        $systemStatus = [
            'status' => 'success',
            'message' => 'All systems operational'
        ];
        
        if ($lastLog) {
            if ($lastLog->status === 'failed') {
                $systemStatus = [
                    'status' => 'error',
                    'message' => 'Last sync failed: ' . ($lastLog->error_message ?? 'Unknown error')
                ];
            } elseif ($lastLog->status === 'running') {
                $systemStatus = [
                    'status' => 'running',
                    'message' => 'Sync currently in progress'
                ];
            }
        }
        
        // Calculate next sync time (every 10 minutes)
        $nextSync = $lastSync ? $lastSync->created_at->addMinutes(10)->diffForHumans() : 'Within 10 minutes';
        
        // Calculate statistics
        $totalSyncs = AppointmentSyncLog::where('status', 'success')->count();
        $failedSyncs = AppointmentSyncLog::where('status', 'failed')->count();
        $totalAttempts = $totalSyncs + $failedSyncs;
        $successRate = $totalAttempts > 0 ? round(($totalSyncs / $totalAttempts) * 100) : 100;
        
        $syncStatsApptBase = BookingAppointment::query();
        StaffClientVisibility::restrictBookingAppointmentEloquentQuery($syncStatsApptBase);

        $stats = [
            'total_synced' => (clone $syncStatsApptBase)->count(),
            'today' => AppointmentSyncLog::whereDate('created_at', today())->count(),
            'failed' => $failedSyncs,
            'success_rate' => $successRate,
        ];

        return view('crm.booking.sync.dashboard', compact('syncLogs', 'systemStatus', 'lastSync', 'nextSync', 'stats'));
    }

    /**
     * Manual sync trigger (admin only)
     */
    public function manualSync(Request $request)
    {
        // Check authorization using Gate
        if (!Gate::allows('trigger-manual-sync')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        try {
            $minutes = $request->input('minutes', 60);
            $stats = $this->syncService->syncRecentAppointments($minutes);

            // Log activity - no need to log this as it's already logged in AppointmentSyncLog

            return response()->json([
                'success' => true,
                'message' => 'Sync completed successfully',
                'stats' => $stats
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Sync failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get appointment details as JSON (for modal/AJAX)
     */
    public function getAppointmentJson($id)
    {
        $appointment = BookingAppointment::with(['client', 'consultant', 'assignedBy'])->findOrFail($id);
        $this->assertBookingAppointmentAccess($appointment);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $appointment->id,
                'bansal_appointment_id' => $appointment->bansal_appointment_id,
                'client_id' => $appointment->client_id,
                'client_name' => $appointment->client_name,
                'client_email' => $appointment->client_email,
                'client_phone' => $appointment->client_phone,
                'appointment_datetime' => $appointment->appointment_datetime,
                'formatted_date' => $appointment->formatted_date,
                'formatted_time' => $appointment->formatted_time,
                'timeslot_full' => $appointment->timeslot_full,
                'location' => $appointment->location,
                'location_display' => $appointment->location_display,
                'full_address' => $appointment->full_address,
                'service_type' => $appointment->service_type,
                'enquiry_type' => $appointment->enquiry_type,
                'enquiry_details' => $appointment->enquiry_details,
                'meeting_type' => $appointment->meeting_type,
                'status' => $appointment->status,
                'status_badge' => $appointment->status_badge,
                'is_paid' => $appointment->is_paid,
                'final_amount' => $appointment->final_amount,
                'payment_status' => $appointment->payment_status,
                'payment_method' => $appointment->payment_method,
                'paid_at' => $appointment->paid_at?->format('d/m/Y h:i A'),
                'promo_code' => $appointment->promo_code,
                'admin_notes' => $appointment->admin_notes,
                'consultant' => $appointment->consultant ? [
                    'id' => $appointment->consultant->id,
                    'name' => $appointment->consultant->name,
                    'calendar_type' => $appointment->consultant->calendar_type,
                ] : null,
                'synced_from_bansal_at' => $appointment->synced_from_bansal_at?->format('d/m/Y h:i A'),
                'updated_at' => $appointment->updated_at?->format('d/m/Y h:i A'),
            ]
        ]);
    }

    /**
     * Send reminder manually
     */
    public function sendReminder(Request $request, $id)
    {
        $appointment = BookingAppointment::findOrFail($id);
        $this->assertBookingAppointmentAccess($appointment);

        $notificationService = app(\App\Services\BansalAppointmentSync\NotificationService::class);
        
        $request->validate([
            'type' => 'required|in:email,sms,both'
        ]);

        $results = [
            'email' => null,
            'sms' => null
        ];

        if (in_array($request->type, ['email', 'both'])) {
            $results['email'] = $notificationService->sendDetailedConfirmationEmail($appointment);
        }

        if (in_array($request->type, ['sms', 'both'])) {
            $results['sms'] = $notificationService->sendReminderSms($appointment);
        }

        $success = ($results['email'] !== false && $results['sms'] !== false);

        return response()->json([
            'success' => $success,
            'message' => $success ? 'Reminder sent successfully' : 'Failed to send reminder',
            'results' => $results
        ]);
    }

    /**
     * Get sync statistics
     */
    public function syncStats()
    {
        $appt24 = BookingAppointment::query()->where('created_at', '>=', now()->subDay());
        StaffClientVisibility::restrictBookingAppointmentEloquentQuery($appt24);

        $stats = [
            'today' => [
                'syncs' => AppointmentSyncLog::today()->count(),
                'successful' => AppointmentSyncLog::today()->success()->count(),
                'failed' => AppointmentSyncLog::today()->failed()->count(),
                'appointments_synced' => AppointmentSyncLog::today()->sum('appointments_new'),
            ],
            'last_24h' => [
                'appointments' => (clone $appt24)->count(),
                'pending' => (clone $appt24)->where('status', 'pending')->count(),
            ],
            'last_sync' => AppointmentSyncLog::latest('started_at')->first(),
        ];

        return response()->json([
            'success' => true,
            'stats' => $stats
        ]);
    }

    /**
     * Export appointments to CSV
     */
    public function export(Request $request)
    {
        $query = BookingAppointment::with(['client', 'consultant']);
        StaffClientVisibility::restrictBookingAppointmentEloquentQuery($query);

        // Apply same filters as index
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('consultant_id')) {
            $query->where('consultant_id', $request->consultant_id);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('appointment_datetime', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('appointment_datetime', '<=', $request->date_to);
        }

        $appointments = $query->orderBy('appointment_datetime', 'desc')->get();

        $filename = 'booking_appointments_' . now()->format('Y-m-d_His') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function() use ($appointments) {
            $file = fopen('php://output', 'w');
            
            // Headers
            fputcsv($file, [
                'ID',
                'Website booking ID',
                'Client Name',
                'Email',
                'Phone',
                'Appointment Date',
                'Time',
                'Location',
                'Service Type',
                'Consultant',
                'Status',
                'Payment Status',
                'Amount',
                'Synced At'
            ]);

            // Data
            foreach ($appointments as $apt) {
                fputcsv($file, [
                    $apt->id,
                    $apt->bansal_appointment_id,
                    $apt->client_name,
                    $apt->client_email,
                    $apt->client_phone,
                    $apt->appointment_datetime->format('d/m/Y'),
                    $apt->timeslot_full ?? $apt->appointment_datetime->format('h:i A'),
                    $apt->location,
                    $apt->service_type,
                    $apt->consultant?->name ?? 'Unassigned',
                    $apt->status,
                    $apt->is_paid ? 'Paid' : 'Free',
                    $apt->is_paid ? $apt->final_amount : '0.00',
                    $apt->synced_from_bansal_at?->format('d/m/Y h:i A')
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Bulk update status
     */
    public function bulkUpdateStatus(Request $request)
    {
        $request->validate([
            'appointment_ids' => 'required|array',
            'appointment_ids.*' => 'exists:booking_appointments,id',
            'status' => 'required|in:pending,confirmed,completed,cancelled,no_show'
        ]);

        $updated = 0;
        
        foreach ($request->appointment_ids as $id) {
            $appointment = BookingAppointment::find($id);
            if ($appointment) {
                $this->assertBookingAppointmentAccess($appointment);
                $appointment->status = $request->status;
                
                if ($request->status === 'confirmed') {
                    $appointment->confirmed_at = now();
                } elseif ($request->status === 'completed') {
                    $appointment->completed_at = now();
                } elseif ($request->status === 'cancelled') {
                    $appointment->cancelled_at = now();
                }
                
                $appointment->save();
                $updated++;
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Updated {$updated} appointments"
        ]);
    }
}

