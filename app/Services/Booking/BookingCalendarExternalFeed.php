<?php

namespace App\Services\Booking;

use App\Models\BookingAppointment;
use App\Services\AppointmentApiService;
use App\Support\StaffClientVisibility;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class BookingCalendarExternalFeed
{
    public function __construct(
        protected AppointmentApiService $appointmentApi
    ) {}

    public function resolveServiceIdForCalendarType(string $type): int
    {
        $map = config('booking_calendar.external.service_ids_by_type', []);
        $specific = $map[$type] ?? null;

        if ($specific !== null && $specific !== '') {
            return (int) $specific;
        }

        return (int) config('booking_calendar.external.default_service_id', 1);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function fetchAppointmentsNormalized(
        int $serviceId,
        int|string|null $apiStatusFilter,
        ?string $calendarRangeStart,
        ?string $calendarRangeEnd,
        bool $allowSyntheticBansalIdForStats = false
    ): array {
        $params = [];
        if (config('booking_calendar.external.pass_calendar_range', true)
            && $calendarRangeStart
            && $calendarRangeEnd
        ) {
            $startKey = config('booking_calendar.external.range_param_start', 'start_date');
            $endKey = config('booking_calendar.external.range_param_end', 'end_date');
            $params[$startKey] = substr($calendarRangeStart, 0, 10);
            $params[$endKey] = substr($calendarRangeEnd, 0, 10);
        }

        $json = $this->appointmentApi->getAppointmentsByServiceAndStatus($serviceId, $apiStatusFilter, $params);

        if (! is_array($json)) {
            throw new \RuntimeException('Appointment API returned invalid response');
        }

        $rawList = $this->extractListFromPayload($json);

        // Many booking APIs return success: false while still sending a usable data[] list — use rows when present.
        $successFlag = $json['success'] ?? null;
        $explicitFailure = $successFlag === false || $successFlag === 0 || $successFlag === '0' || $successFlag === 'false';
        if ($rawList === [] && $explicitFailure) {
            throw new \RuntimeException(
                'Appointment API returned an error: ' . ($json['message'] ?? json_encode($json))
            );
        }

        $out = [];
        foreach ($rawList as $row) {
            if (! is_array($row)) {
                continue;
            }
            $norm = $this->normalizeApiRow($row);
            if (empty($norm['appointment_datetime'])) {
                continue;
            }
            if (empty($norm['bansal_appointment_id'])) {
                if (! $allowSyntheticBansalIdForStats) {
                    continue;
                }
                $norm['bansal_appointment_id'] = (int) (crc32(json_encode($row)) & 0x7FFFFFFF) ?: 1;
            }
            $out[] = $norm;
        }

        return $out;
    }

    /**
     * Load normalized rows for dashboard stats: retry strategies + optional loose status filter.
     *
     * @return list<array<string, mixed>>
     */
    public function fetchRowsForStatsWithFallbacks(int $serviceId, int|string|null $apiStatusFilter): array
    {
        $tz = config('app.timezone');
        $now = Carbon::now($tz);

        $statusForStats = config('booking_calendar.external.stats_omit_status_param', false)
            ? null
            : $apiStatusFilter;

        $attempts = [
            [$statusForStats, null, null],
            [$statusForStats, $now->copy()->startOfMonth()->toDateString(), $now->copy()->endOfMonth()->toDateString()],
            [
                $statusForStats,
                $now->copy()->subMonths(3)->startOfMonth()->toDateString(),
                $now->copy()->addMonths(12)->endOfMonth()->toDateString(),
            ],
            // Some deployments only return data with the list filter used on the calendar:
            [$apiStatusFilter, null, null],
            [$apiStatusFilter, $now->copy()->startOfMonth()->toDateString(), $now->copy()->endOfMonth()->toDateString()],
        ];

        foreach ($attempts as [$status, $start, $end]) {
            try {
                $rows = $this->fetchAppointmentsNormalized($serviceId, $status, $start, $end, true);
                if ($rows !== []) {
                    Log::info('Calendar stats: API rows loaded', [
                        'count' => count($rows),
                        'had_status' => $status !== null,
                        'range' => $start && $end ? "{$start}–{$end}" : 'none',
                    ]);

                    return $rows;
                }
            } catch (Throwable $e) {
                Log::info('Calendar stats fetch attempt failed', [
                    'message' => $e->getMessage(),
                    'had_status' => $status !== null,
                    'range' => $start && $end ? "{$start}–{$end}" : 'none',
                ]);
            }
        }

        Log::warning('Calendar stats: all API fetch attempts returned no rows');

        return [];
    }

    /**
     * Stats for the calendar header cards (external API rows, same filters as list).
     *
     * @return array{this_month: int, today: int, upcoming: int, pending: int, paid: int, no_show: int}
     */
    public function computeStats(string $calendarType, int|string|null $apiStatusFilter): array
    {
        $serviceId = $this->resolveServiceIdForCalendarType($calendarType);
        $rows = $this->fetchRowsForStatsWithFallbacks($serviceId, $apiStatusFilter);

        return $this->accumulateStatsFromNormalizedRows($rows);
    }

    /**
     * Local DB stats (same keys) plus API-only rows not yet synced for this calendar type.
     *
     * @param  array<string, int>  $localStats
     * @return array<string, int>
     */
    public function computeMergeStatsWithLocal(string $calendarType, int|string|null $apiStatusFilter, array $localStats): array
    {
        $delta = $this->computeUnsyncedApiStatsDelta($calendarType, $apiStatusFilter);
        foreach ($localStats as $k => $v) {
            $localStats[$k] = $v + ($delta[$k] ?? 0);
        }

        return $localStats;
    }

    /**
     * @return array<string, int>
     */
    protected function computeUnsyncedApiStatsDelta(string $calendarType, int|string|null $apiStatusFilter): array
    {
        $serviceId = $this->resolveServiceIdForCalendarType($calendarType);
        $synced = $this->syncedBansalAppointmentIdsForCalendarType($calendarType);
        $rows = $this->fetchRowsForStatsWithFallbacks($serviceId, $apiStatusFilter);
        $filtered = array_values(array_filter($rows, function (array $r) use ($synced) {
            $id = $r['bansal_appointment_id'] ?? null;

            return $id === null || ! isset($synced[(int) $id]);
        }));

        return $this->accumulateStatsFromNormalizedRows($filtered);
    }

    /**
     * @return array<int, bool>
     */
    protected function syncedBansalAppointmentIdsForCalendarType(string $type): array
    {
        $q = BookingAppointment::query()->whereHas('consultant', function ($q2) use ($type) {
            $q2->where('calendar_type', $type);
        });
        StaffClientVisibility::restrictBookingAppointmentEloquentQuery($q);

        $ids = $q->whereNotNull('bansal_appointment_id')
            ->pluck('bansal_appointment_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->all();

        return array_fill_keys($ids, true);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{this_month: int, today: int, upcoming: int, pending: int, paid: int, no_show: int}
     */
    protected function accumulateStatsFromNormalizedRows(array $rows): array
    {
        $tz = config('app.timezone');
        $now = Carbon::now($tz);
        $today = Carbon::today($tz);

        $stats = [
            'this_month' => 0,
            'today' => 0,
            'upcoming' => 0,
            'pending' => 0,
            'paid' => 0,
            'no_show' => 0,
        ];

        foreach ($rows as $row) {
            $dt = $this->parseAppointmentDateTime($row['appointment_datetime'] ?? null);
            if (! $dt) {
                continue;
            }

            if ((int) $dt->format('n') === (int) $now->format('n') && (int) $dt->format('Y') === (int) $now->format('Y')) {
                $stats['this_month']++;
            }

            if ($dt->isSameDay($today)) {
                $stats['today']++;
            }
            if ($dt->gt($now)) {
                $stats['upcoming']++;
            }

            if ($this->rowCountsAsPaymentPendingForStats($row)) {
                $stats['pending']++;
            }
            if ($this->rowCountsAsPaidForStats($row)) {
                $stats['paid']++;
            }
            if (($row['status'] ?? '') === 'no_show' || $this->statusLabelImpliesNoShow($row)) {
                $stats['no_show']++;
            }
        }

        return $stats;
    }

    /**
     * Dashboard “Payment Pending” / “Paid” cards: prefer `payment_type` / `payment_status` from the API so a row
     * is not classified from status+is_paid alone when payment_type already says Paid.
     */
    protected function rowCountsAsPaymentPendingForStats(array $row): bool
    {
        if ($this->rowCountsAsPaidForStats($row)) {
            return false;
        }

        $ptype = strtolower($this->rawPaymentTypeForStats($row));
        if ($ptype !== '' && $this->paymentTypeStringImpliesPendingBucket($ptype)) {
            return true;
        }

        return ($row['status'] ?? '') === 'pending' && ! empty($row['is_paid']);
    }

    protected function rowCountsAsPaidForStats(array $row): bool
    {
        $ptype = strtolower($this->rawPaymentTypeForStats($row));
        if ($ptype !== '' && $this->paymentTypeStringImpliesPaidBucket($ptype)) {
            return true;
        }

        return ($row['status'] ?? '') === 'paid' && ! empty($row['is_paid']);
    }

    protected function rawPaymentTypeForStats(array $row): string
    {
        $p = $row['payment_type'] ?? '';

        return is_string($p) ? trim($p) : '';
    }

    protected function paymentTypeStringImpliesPaidBucket(string $ptype): bool
    {
        if ($ptype === '') {
            return false;
        }
        if (str_contains($ptype, 'payment pending') || str_contains($ptype, 'pay pending') || str_contains($ptype, 'pending payment')) {
            return false;
        }
        if (str_contains($ptype, 'unpaid') || str_contains($ptype, 'not paid') || str_contains($ptype, 'not_paid')) {
            return false;
        }
        if (in_array($ptype, ['paid', 'complete', 'completed', 'success', 'successful', 'succeeded'], true)) {
            return true;
        }
        if (preg_match('/\bpaid\b/', $ptype)) {
            return true;
        }
        foreach (['card', 'stripe', 'paypal', 'square', 'eftpos', 'completed', 'captured', 'successful', 'succeeded'] as $needle) {
            if (str_contains($ptype, $needle)) {
                return true;
            }
        }

        return false;
    }

    protected function paymentTypeStringImpliesPendingBucket(string $ptype): bool
    {
        if (str_contains($ptype, 'payment pending') || str_contains($ptype, 'pay pending') || str_contains($ptype, 'pending payment')) {
            return true;
        }
        if (str_contains($ptype, 'awaiting payment') || str_contains($ptype, 'awaiting_payment')) {
            return true;
        }
        if (str_contains($ptype, 'unpaid') || str_contains($ptype, 'not paid') || str_contains($ptype, 'not_paid')) {
            return true;
        }

        return false;
    }

    protected function statusLabelImpliesNoShow(array $row): bool
    {
        $label = strtolower((string) ($row['status_label'] ?? ''));

        return str_contains($label, 'no show') || str_contains($label, 'no-show');
    }

    /**
     * Merge API shape with CRM row when present (uses CRM id and full payload).
     *
     * @param  list<array<string, mixed>>  $normalizedFromApi
     * @return list<array<string, mixed>>
     */
    public function resolveWithLocalCrmRows(array $normalizedFromApi): array
    {
        $bansalIds = [];
        foreach ($normalizedFromApi as $r) {
            if (! empty($r['bansal_appointment_id'])) {
                $bansalIds[] = (int) $r['bansal_appointment_id'];
            }
        }
        $bansalIds = array_values(array_unique(array_filter($bansalIds)));

        // Only `consultant` is needed for calendarPayloadFromModel(). Avoid eager-loading `client`
        // (Admin): some DBs omit the legacy `staff` table and loading Admin can trigger Staff lookups → HTTP 500.
        try {
            $locals = $bansalIds === []
                ? collect()
                : BookingAppointment::with(['consultant'])
                    ->whereIn('bansal_appointment_id', $bansalIds)
                    ->get()
                    ->keyBy('bansal_appointment_id');
        } catch (Throwable $e) {
            Log::warning('Calendar: local CRM merge skipped for Bansal ids (using API-only rows)', [
                'error' => $e->getMessage(),
                'bansal_ids' => $bansalIds,
            ]);
            $locals = collect();
        }

        $out = [];
        foreach ($normalizedFromApi as $row) {
            $bid = $row['bansal_appointment_id'] ?? null;
            if ($bid && $locals->has((int) $bid)) {
                $out[] = $this->calendarPayloadFromModel($locals->get((int) $bid));

                continue;
            }
            $out[] = $this->toReadOnlyCalendarPayload($row);
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    public function calendarPayloadFromModel(BookingAppointment $appointment): array
    {
        $encodedClientId = $appointment->client_id
            ? base64_encode(convert_uuencode($appointment->client_id))
            : null;

        return [
            'id' => $appointment->id,
            'crm_appointment_id' => $appointment->id,
            'bansal_appointment_id' => $appointment->bansal_appointment_id,
            'read_only' => false,
            'client_id' => $appointment->client_id,
            'client_id_encoded' => $encodedClientId,
            'client_name' => $appointment->client_name,
            'client_email' => $appointment->client_email,
            'client_phone' => $this->scalarForCalendar($appointment->client_phone),
            'service_type' => $this->scalarForCalendar(
                $appointment->service_type ?? $appointment->enquiry_type
            ),
            'appointment_datetime' => $appointment->appointment_datetime->toIso8601String(),
            'duration_minutes' => $appointment->duration_minutes,
            'status' => $appointment->status,
            'status_label' => $this->statusLabelForCalendar(null, (string) $appointment->status),
            'payment_type' => $this->scalarForCalendar($appointment->payment_method ?? null),
            'payment_status' => $this->paymentDisplayLabel($appointment->payment_method ?? null, (bool) $appointment->is_paid),
            'location' => $appointment->location,
            'meeting_type' => $appointment->meeting_type,
            'preferred_language' => $appointment->preferred_language ?? 'English',
            'is_paid' => $appointment->is_paid,
            'final_amount' => $appointment->final_amount ?? 0,
            'consultant' => $appointment->consultant ? [
                'id' => $appointment->consultant->id,
                'name' => $appointment->consultant->name,
            ] : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $row  Output of normalizeApiRow()
     * @return array<string, mixed>
     */
    public function toReadOnlyCalendarPayload(array $row): array
    {
        $bid = $row['bansal_appointment_id'] ?? null;

        return array_merge($row, [
            'id' => $bid,
            'crm_appointment_id' => null,
            'read_only' => true,
        ]);
    }

    /**
     * List appointments for the CRM "Website Bookings" page (CRM route accepts GET or POST; filters in request input).
     *
     * @return array{rows: list<array<string, mixed>>, meta: array<string, int|null>}
     */
    public function fetchWebsiteBookingsListFromPublicApi(Request $request): array
    {
        $perPage = min(max((int) $request->input('per_page', 20), 1), 100);
        $page = max((int) $request->input('page', 1), 1);

        $hasDateRange = $request->filled('date_from') || $request->filled('date_to');
        $aggregateDates = $hasDateRange && filter_var(config('booking_calendar.website_bookings_list.aggregate_when_date_filtered', true), FILTER_VALIDATE_BOOL);
        $searchQInit = $request->filled('search') ? trim((string) $request->search) : '';
        $aggregateSearch = $searchQInit !== ''
            && filter_var(config('booking_calendar.website_bookings_list.aggregate_when_search_filtered', true), FILTER_VALIDATE_BOOL);

        if ($aggregateDates || $aggregateSearch) {
            return $this->fetchWebsiteBookingsListAggregatedWithDateFilter($request, $page, $perPage);
        }

        $params = $this->buildWebsiteBookingsApiBaseParams($request, $page, $perPage);

        $json = $this->appointmentApi->getAppointments($params);
        if (! is_array($json)) {
            throw new \RuntimeException('Appointment API returned invalid response');
        }

        $rawList = $this->extractListFromPayload($json);

        $normalized = [];
        foreach ($rawList as $row) {
            if (! is_array($row)) {
                continue;
            }
            $norm = $this->normalizeApiRowForBookingsList($row);
            if (empty($norm['appointment_datetime'])) {
                continue;
            }
            $normalized[] = $norm;
        }

        $statusFilter = $this->resolveWebsiteBookingsListApiStatus($request->status);
        $normalized = $this->filterNormalizedRowsByWebsiteStatusCode($normalized, $statusFilter);
        $normalized = $this->filterNormalizedRowsByWebsiteSearchText($normalized, $searchQInit !== '' ? $searchQInit : null);

        $meta = $this->extractPaginationMetaFromWebsiteApi($json, count($normalized), $page, $perPage);

        return [
            'rows' => $normalized,
            'meta' => $meta,
        ];
    }

    /**
     * Summary counts for /booking/appointments KPI cards (same public API as the data table).
     *
     * @return array{pending: int, paid: int, confirmed: int, today: int, total: int}
     */
    public function fetchWebsiteBookingsKpiStats(): array
    {
        $rows = $this->fetchAllNormalizedWebsiteBookingsUnfiltered();

        return $this->aggregateWebsiteBookingsKpiStatsFromRows($rows);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{pending: int, paid: int, confirmed: int, today: int, total: int}
     */
    protected function aggregateWebsiteBookingsKpiStatsFromRows(array $rows): array
    {
        $tz = config('app.timezone') ?: 'UTC';
        $todayStart = Carbon::today($tz);

        $stats = [
            'pending' => 0,
            'paid' => 0,
            'confirmed' => 0,
            'today' => 0,
            'total' => count($rows),
        ];

        foreach ($rows as $norm) {
            $code = isset($norm['website_status_code']) ? (int) $norm['website_status_code'] : null;
            $isPaid = (bool) ($norm['is_paid'] ?? false);

            // "Payment Pending" card: generic pending (0) + payment-pending (9) on the public API scale.
            if ($code === 0 || $code === 9) {
                $stats['pending']++;
            }

            if ($isPaid || $code === 10 || $code === 2) {
                $stats['paid']++;
            }

            if ($code === 1) {
                $stats['confirmed']++;
            }

            try {
                $dt = Carbon::parse((string) $norm['appointment_datetime'], $tz);
                if ($dt->isSameDay($todayStart)) {
                    $stats['today']++;
                }
            } catch (Throwable) {
                // ignore invalid dates for "today"
            }
        }

        return $stats;
    }

    /**
     * Pull list rows from the website API (no status/search/date filters) up to configured page limit.
     *
     * @return list<array<string, mixed>>
     */
    protected function fetchAllNormalizedWebsiteBookingsUnfiltered(): array
    {
        $maxPages = max(1, (int) config('booking_calendar.website_bookings_list.aggregated_fetch_max_api_pages', 40));
        $chunk = max(10, min(100, (int) config('booking_calendar.website_bookings_list.api_per_chunk', 100)));
        $request = Request::create('/', 'GET', []);

        $allRaw = [];

        for ($apiPage = 1; $apiPage <= $maxPages; $apiPage++) {
            $params = $this->buildWebsiteBookingsApiBaseParams($request, $apiPage, $chunk);
            $json = $this->appointmentApi->getAppointments($params);
            if (! is_array($json)) {
                throw new \RuntimeException('Appointment API returned invalid response');
            }

            $batch = $this->extractListFromPayload($json);
            foreach ($batch as $row) {
                if (is_array($row)) {
                    $allRaw[] = $row;
                }
            }

            $metaChunk = $this->extractPaginationMetaFromWebsiteApi($json, count($batch), $apiPage, $chunk);
            $lastPageHint = max(1, (int) ($metaChunk['last_page'] ?? 1));

            if ($apiPage >= $lastPageHint || count($batch) === 0) {
                break;
            }
        }

        $seen = [];
        $deduped = [];
        foreach ($allRaw as $row) {
            $id = $row['id'] ?? $row['appointment_id'] ?? null;
            $key = $id !== null ? 'id:' . $id : 'h:' . md5((json_encode($row) ?: ''));
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $deduped[] = $row;
        }

        $normalized = [];
        foreach ($deduped as $row) {
            $norm = $this->normalizeApiRowForBookingsList($row);
            if (empty($norm['appointment_datetime'])) {
                continue;
            }
            $normalized[] = $norm;
        }

        return $normalized;
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildWebsiteBookingsApiBaseParams(Request $request, int $page, int $perPage): array
    {
        $params = [
            'page' => $page,
            'per_page' => $perPage,
        ];

        $serviceId = config('booking_calendar.website_bookings_list.api_service_id');
        if ($serviceId !== null && $serviceId !== '' && (int) $serviceId > 0) {
            $params['service_id'] = (int) $serviceId;
        }

        $this->applyWebsiteBookingsDateParamsToApiQuery($request, $params);

        if ($request->filled('consultant_id')) {
            $params['consultant_id'] = $request->consultant_id;
        }
        $this->applyWebsiteBookingsSearchParamsToApiQuery($request, $params);
        if ($request->filled('status')) {
            $apiStatus = $this->resolveWebsiteBookingsListApiStatus($request->status);
            if ($apiStatus !== null) {
                $params['status'] = $apiStatus;
            }
        }

        return $params;
    }

    /**
     * @param  array<string, mixed>  $params
     */
    protected function applyWebsiteBookingsSearchParamsToApiQuery(Request $request, array &$params): void
    {
        if (! $request->filled('search')) {
            return;
        }
        $q = trim((string) $request->search);
        if ($q === '') {
            return;
        }

        $primary = (string) config('booking_calendar.website_bookings_list.api_search_param', 'search');
        $params[$primary] = $q;
        $params['search'] = $q;
        $params['q'] = $q;
        $params['query'] = $q;
        $params['keyword'] = $q;
        $params['term'] = $q;
    }

    /**
     * Website bookings filter sends integer codes 0–11 (public /appointments API).
     * Legacy query values (e.g. ?status=pending from older CRM links) are mapped here.
     */
    protected function resolveWebsiteBookingsListApiStatus(mixed $raw): ?int
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        if (is_numeric($raw)) {
            $i = (int) $raw;

            return ($i >= 0 && $i <= 11) ? $i : null;
        }
        if (is_string($raw)) {
            $s = strtolower(str_replace(['-', ' '], '_', trim($raw)));
            $legacy = [
                'pending' => 0,
                'approved' => 1,
                'completed' => 2,
                'rejected' => 3,
                'n/p' => 4,
                'np' => 4,
                'in_progress' => 5,
                'inprogress' => 5,
                'did_not_come' => 6,
                'dnc' => 6,
                'cancelled' => 7,
                'canceled' => 7,
                'missed' => 8,
                'payment_pending' => 9,
                'pending_payment' => 9,
                'pending_with_payment_pending' => 9,
                'pending_with_payment_success' => 10,
                'pending_with_payment_failed' => 11,
            ];
            if (isset($legacy[$s])) {
                return $legacy[$s];
            }
        }

        return null;
    }

    /**
     * Normalized status for the bookings list status filter (empty string = all).
     */
    public function websiteBookingsListResolvedStatusForUi(mixed $raw): string
    {
        $i = $this->resolveWebsiteBookingsListApiStatus($raw);

        return $i === null ? '' : (string) $i;
    }

    /**
     * Human labels for website appointment status codes (filter + display).
     *
     * @return array<int, string>
     */
    public static function websiteBookingsStatusLabels(): array
    {
        return [
            0 => 'Pending',
            1 => 'Approved',
            2 => 'Completed',
            3 => 'Rejected',
            4 => 'N/P',
            5 => 'In Progress',
            6 => 'Did Not Come',
            7 => 'Cancelled',
            8 => 'Missed',
            9 => 'Pending With Payment Pending',
            10 => 'Pending With Payment Success',
            11 => 'Pending With Payment Failed',
        ];
    }

    /**
     * @return array{slug: string, label: string, badge: string}|null
     */
    protected function websiteListStatusDisplayMeta(mixed $raw): ?array
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        if (! is_numeric($raw)) {
            return null;
        }
        $i = (int) $raw;
        $labels = self::websiteBookingsStatusLabels();
        if ($i < 0 || $i > 11 || ! isset($labels[$i])) {
            return null;
        }

        $badge = match ($i) {
            0, 9 => 'warning',
            1 => 'success',
            2, 5 => 'info',
            3, 7, 11 => 'danger',
            4 => 'secondary',
            6, 8 => 'dark',
            10 => 'primary',
            default => 'secondary',
        };

        return [
            'slug' => 'website_status_' . $i,
            'label' => $labels[$i],
            'badge' => $badge,
        ];
    }

    /**
     * When a status filter is applied, drop rows whose API code is known and differs (API may ignore query).
     *
     * @param  list<array<string, mixed>>  $normalized
     * @return list<array<string, mixed>>
     */
    protected function filterNormalizedRowsByWebsiteStatusCode(array $normalized, ?int $statusFilter): array
    {
        if ($statusFilter === null) {
            return $normalized;
        }

        return array_values(array_filter($normalized, function (array $norm) use ($statusFilter) {
            $code = $norm['website_status_code'] ?? null;
            if ($code === null) {
                return true;
            }

            return (int) $code === $statusFilter;
        }));
    }

    /**
     * Search / client reference box: match name, email, phone, enquiry text, service labels (substring, case-insensitive).
     *
     * @param  list<array<string, mixed>>  $normalized
     * @return list<array<string, mixed>>
     */
    protected function filterNormalizedRowsByWebsiteSearchText(array $normalized, ?string $search): array
    {
        $q = $search !== null ? trim($search) : '';
        if ($q === '') {
            return $normalized;
        }
        $needle = Str::lower($q);

        return array_values(array_filter($normalized, function (array $norm) use ($needle) {
            $hay = Str::lower(trim(
                ($norm['client_name'] ?? '') . ' ' .
                ($norm['client_email'] ?? '') . ' ' .
                ($norm['client_phone'] ?? '') . ' ' .
                ($norm['enquiry_details'] ?? '') . ' ' .
                ($norm['service_type'] ?? '') . ' ' .
                ($norm['enquiry_type'] ?? '') . ' ' .
                ($norm['website_search_extra'] ?? '')
            ));

            return $hay !== '' && str_contains($hay, $needle);
        }));
    }

    /**
     * @param  array<string, mixed>  $params
     */
    protected function applyWebsiteBookingsDateParamsToApiQuery(Request $request, array &$params): void
    {
        if (! $request->filled('date_from') && ! $request->filled('date_to')) {
            return;
        }

        $primaryFrom = (string) config('booking_calendar.website_bookings_list.api_date_param_from', 'date_from');
        $primaryTo = (string) config('booking_calendar.website_bookings_list.api_date_param_to', 'date_to');

        if ($request->filled('date_from')) {
            $d = (string) $request->date_from;
            $params[$primaryFrom] = $d;
            $params['date_from'] = $d;
            $params['start_date'] = $d;
            $params['from_date'] = $d;
            $params['appointment_date_from'] = $d;
        }
        if ($request->filled('date_to')) {
            $d = (string) $request->date_to;
            $params[$primaryTo] = $d;
            $params['date_to'] = $d;
            $params['end_date'] = $d;
            $params['to_date'] = $d;
            $params['appointment_date_to'] = $d;
        }
    }

    /**
     * @return array{rows: list<array<string, mixed>>, meta: array<string, int|null>}
     */
    protected function fetchWebsiteBookingsListAggregatedWithDateFilter(Request $request, int $pageRequested, int $perPageRequested): array
    {
        $maxPages = max(1, (int) config('booking_calendar.website_bookings_list.aggregated_fetch_max_api_pages', 40));
        $chunk = max(10, min(100, (int) config('booking_calendar.website_bookings_list.api_per_chunk', 100)));

        $dateFrom = $request->filled('date_from') ? (string) $request->date_from : null;
        $dateTo = $request->filled('date_to') ? (string) $request->date_to : null;

        $allRaw = [];

        for ($apiPage = 1; $apiPage <= $maxPages; $apiPage++) {
            $params = $this->buildWebsiteBookingsApiBaseParams($request, $apiPage, $chunk);
            $json = $this->appointmentApi->getAppointments($params);
            if (! is_array($json)) {
                throw new \RuntimeException('Appointment API returned invalid response');
            }

            $batch = $this->extractListFromPayload($json);
            foreach ($batch as $row) {
                if (is_array($row)) {
                    $allRaw[] = $row;
                }
            }

            $metaChunk = $this->extractPaginationMetaFromWebsiteApi($json, count($batch), $apiPage, $chunk);
            $lastPageHint = max(1, (int) ($metaChunk['last_page'] ?? 1));

            if ($apiPage >= $lastPageHint || count($batch) === 0) {
                break;
            }
        }

        $seen = [];
        $deduped = [];
        foreach ($allRaw as $row) {
            $id = $row['id'] ?? $row['appointment_id'] ?? null;
            $key = $id !== null ? 'id:' . $id : 'h:' . md5(json_encode($row) ?: '');
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $deduped[] = $row;
        }

        $normalized = [];
        foreach ($deduped as $row) {
            $norm = $this->normalizeApiRowForBookingsList($row);
            if (empty($norm['appointment_datetime'])) {
                continue;
            }
            if (! $this->normalizedRowMatchesWebsiteBookingsDateRange($norm, $dateFrom, $dateTo)) {
                continue;
            }
            $normalized[] = $norm;
        }

        $statusFilter = $this->resolveWebsiteBookingsListApiStatus($request->status);
        $normalized = $this->filterNormalizedRowsByWebsiteStatusCode($normalized, $statusFilter);

        $searchQAgg = $request->filled('search') ? trim((string) $request->search) : '';
        $normalized = $this->filterNormalizedRowsByWebsiteSearchText($normalized, $searchQAgg !== '' ? $searchQAgg : null);

        usort($normalized, function (array $a, array $b): int {
            $da = (string) ($a['appointment_datetime'] ?? '');
            $db = (string) ($b['appointment_datetime'] ?? '');

            return strcmp($db, $da);
        });

        $total = count($normalized);
        $lastPage = max(1, (int) ceil($total / max(1, $perPageRequested)));
        $pageRequested = min(max(1, $pageRequested), $lastPage);
        $offset = ($pageRequested - 1) * $perPageRequested;
        $pageRows = array_slice($normalized, $offset, $perPageRequested);

        $meta = [
            'current_page' => $pageRequested,
            'last_page' => $lastPage,
            'per_page' => $perPageRequested,
            'total' => $total,
            'from' => $total > 0 ? $offset + 1 : 0,
            'to' => $total > 0 ? min($total, $offset + count($pageRows)) : 0,
        ];

        if (count($allRaw) > 0 && $total === 0) {
            Log::warning('Website bookings date filter: API rows were fetched but none matched the selected date range (timezone or API data).', [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'api_rows' => count($allRaw),
            ]);
        }

        return [
            'rows' => $pageRows,
            'meta' => $meta,
        ];
    }

    /**
     * @param  array<string, mixed>  $norm
     */
    protected function normalizedRowMatchesWebsiteBookingsDateRange(array $norm, ?string $dateFrom, ?string $dateTo): bool
    {
        if (($dateFrom === null || $dateFrom === '') && ($dateTo === null || $dateTo === '')) {
            return true;
        }

        try {
            $dt = Carbon::parse($norm['appointment_datetime'], config('app.timezone'));
        } catch (Throwable) {
            return false;
        }

        if ($dateFrom !== null && $dateFrom !== '') {
            try {
                $from = Carbon::parse($dateFrom, config('app.timezone'))->startOfDay();
                if ($dt->lt($from)) {
                    return false;
                }
            } catch (Throwable) {
                return false;
            }
        }

        if ($dateTo !== null && $dateTo !== '') {
            try {
                $to = Carbon::parse($dateTo, config('app.timezone'))->endOfDay();
                if ($dt->gt($to)) {
                    return false;
                }
            } catch (Throwable) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    public function normalizeApiRowForBookingsList(array $item): array
    {
        $base = $this->normalizeApiRow($item);

        $details = Arr::get($item, 'enquiry_details')
            ?? Arr::get($item, 'description')
            ?? Arr::get($item, 'notes')
            ?? Arr::get($item, 'message');

        if (is_string($details)) {
            $base['enquiry_details'] = $details;
        } elseif (is_scalar($details)) {
            $base['enquiry_details'] = (string) $details;
        } else {
            $base['enquiry_details'] = null;
        }

        $et = Arr::get($item, 'enquiry_type');
        $base['enquiry_type'] = $this->isMeaningfulApiScalar($et) ? trim((string) $et) : null;

        $tf = Arr::get($item, 'timeslot_full') ?? Arr::get($item, 'slot_display') ?? Arr::get($item, 'time_slot');
        if ($this->isMeaningfulApiScalar($tf)) {
            $base['timeslot_full'] = trim((string) $tf);
        }

        $rawStatus = $item['status'] ?? null;
        if (is_numeric($rawStatus)) {
            $code = (int) $rawStatus;
            if ($code >= 0 && $code <= 11) {
                $base['website_status_code'] = $code;
            }
        }

        $meta = $this->websiteListStatusDisplayMeta($rawStatus);
        if ($meta !== null) {
            $base['status'] = $meta['slug'];
            $base['status_label'] = $meta['label'];
            $base['status_badge_class'] = $meta['badge'];
        }

        $refBits = [];
        foreach ([
            'order_hash',
            'booking_reference',
            'client_reference',
            'reference',
            'matter_no',
            'client_unique_matter_no',
            'matter_number',
            'ref',
        ] as $key) {
            $v = Arr::get($item, $key);
            if ($this->isMeaningfulApiScalar($v)) {
                $refBits[] = trim((string) $v);
            }
        }
        $base['website_search_extra'] = $refBits !== [] ? implode(' ', $refBits) : '';

        return $base;
    }

    /**
     * @return array{current_page: int, last_page: int, per_page: int, total: int, from: int|null, to: int|null}
     */
    protected function extractPaginationMetaFromWebsiteApi(array $json, int $rowsOnPage, int $requestedPage, int $requestedPerPage): array
    {
        $candidates = [
            $json['pagination'] ?? null,
            $json['meta'] ?? null,
        ];
        $data = $json['data'] ?? null;
        if (is_array($data) && ! array_is_list($data)) {
            $candidates[] = $data['pagination'] ?? null;
            $candidates[] = $data['meta'] ?? null;
        }

        foreach ($candidates as $p) {
            if (! is_array($p)) {
                continue;
            }
            if (! isset($p['total']) && ! isset($p['last_page'])) {
                continue;
            }

            $total = (int) ($p['total'] ?? 0);
            $perPage = (int) ($p['per_page'] ?? $requestedPerPage);
            $perPage = max(1, $perPage);
            $lastPage = (int) ($p['last_page'] ?? ($total > 0 ? (int) ceil($total / $perPage) : 1));
            $currentPage = (int) ($p['current_page'] ?? $requestedPage);
            $from = $p['from'] ?? ($total > 0 ? (($currentPage - 1) * $perPage + 1) : null);
            $to = $p['to'] ?? ($total > 0 ? min($total, ($currentPage - 1) * $perPage + $rowsOnPage) : null);

            return [
                'current_page' => max(1, $currentPage),
                'last_page' => max(1, $lastPage),
                'per_page' => $perPage,
                'total' => $total,
                'from' => $from !== null ? (int) $from : null,
                'to' => $to !== null ? (int) $to : null,
            ];
        }

        return [
            'current_page' => 1,
            'last_page' => 1,
            'per_page' => $requestedPerPage,
            'total' => $rowsOnPage,
            'from' => $rowsOnPage > 0 ? 1 : 0,
            'to' => $rowsOnPage,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    public function extractListFromPayload(array $payload): array
    {
        foreach (['data', 'appointments', 'items', 'results'] as $key) {
            if (! isset($payload[$key])) {
                continue;
            }
            $data = $payload[$key];

            if (is_string($data)) {
                $decoded = json_decode($data, true);
                $data = is_array($decoded) ? $decoded : [];
            }

            if (isset($data['data']) && is_array($data['data'])) {
                return array_values($data['data']);
            }

            if (is_array($data) && $data !== [] && array_is_list($data)) {
                return array_values($data);
            }

            if (is_array($data)) {
                // Single associative wrapper e.g. data: { records: [...] }
                foreach (['data', 'records', 'items', 'appointments'] as $inner) {
                    if (isset($data[$inner]) && is_array($data[$inner])) {
                        return array_values($data[$inner]);
                    }
                }
            }
        }

        return [];
    }

    /**
     * Normalize one API appointment row to CRM calendar JSON field names.
     *
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    protected function normalizeApiRow(array $item): array
    {
        $bansalId = $item['id'] ?? $item['appointment_id'] ?? null;
        $name = $item['full_name'] ?? $item['client_name'] ?? $item['name'] ?? '';
        $email = $item['email'] ?? $item['client_email'] ?? '';
        $phone = $this->extractPhoneFromApiRow($item);
        $serviceLabel = $this->extractServiceLabelFromApiRow($item);

        $when = $item['appointment_datetime']
            ?? $item['scheduled_at']
            ?? $item['slot_datetime']
            ?? null;

        if ($when === null && ! empty($item['date'])) {
            $datePart = trim((string) $item['date']);
            $timePart = $item['time'] ?? $item['appointment_time'] ?? $item['slot_time'] ?? null;
            $when = $timePart !== null && $timePart !== ''
                ? $datePart . ' ' . trim((string) $timePart)
                : $datePart;
        }

        if ($when === null && ! empty($item['appointment_date'])) {
            $d = trim((string) $item['appointment_date']);
            $t = $item['appointment_time'] ?? null;
            $when = $t !== null && $t !== '' ? $d . ' ' . trim((string) $t) : $d;
        }

        $statusLabelFromApi = $this->extractStatusLabelFromApiRow($item);
        $status = $this->normalizeStatus(
            $item['status'] ?? $this->guessStatusSlugFromLabel($statusLabelFromApi)
        );

        $paymentTypeRaw = $this->extractPaymentTypeFromApiRow($item);
        $isPaid = $this->inferIsPaidFromPaymentType(
            $paymentTypeRaw,
            Arr::get($item, 'is_paid', false)
        );

        $meetingRaw = $item['meeting_type'] ?? null;
        $meeting = $this->normalizeMeetingType(is_string($meetingRaw) ? $meetingRaw : null);

        return [
            'bansal_appointment_id' => $bansalId !== null ? (int) $bansalId : null,
            'client_id' => $item['client_id'] ?? null,
            'client_id_encoded' => null,
            'client_name' => $name,
            'client_email' => $email,
            'client_phone' => $this->scalarForCalendar($phone),
            'service_type' => $this->scalarForCalendar($serviceLabel),
            'appointment_datetime' => $this->formatDateTimeForJson($when),
            'duration_minutes' => (int) ($item['duration_minutes'] ?? 15),
            'status' => $status,
            'status_label' => $this->statusLabelForCalendar($statusLabelFromApi, $status),
            'payment_type' => $this->scalarForCalendar($paymentTypeRaw),
            'payment_status' => $this->paymentDisplayLabel($paymentTypeRaw, $isPaid),
            'location' => $item['location'] ?? null,
            'meeting_type' => $meeting,
            'preferred_language' => $item['preferred_language'] ?? 'English',
            'is_paid' => (bool) $isPaid,
            'final_amount' => $item['final_amount'] ?? Arr::get($item, 'amount', 0),
            'consultant' => $this->normalizeConsultant($item),
        ];
    }

    /**
     * Avoid JSON null / literal "null" in calendar payloads (JS would print "null").
     */
    protected function scalarForCalendar(?string $value): string
    {
        if ($value === null) {
            return '';
        }
        $s = trim($value);
        if ($s === '' || strcasecmp($s, 'null') === 0 || strcasecmp($s, 'undefined') === 0) {
            return '';
        }

        return $s;
    }

    /**
     * Human-readable service line for the calendar modal (APIs vary widely).
     */
    protected function extractServiceLabelFromApiRow(array $item): ?string
    {
        $paths = [
            'service_type_display',
            'enquiry_type_display',
            'service_label',
            'service_name',
            'service_title',
            'booking_type_label',
            'appointment_type_label',
            'service.name',
            'service.title',
            'service.label',
            'service_type',
            'enquiry_type',
            'specific_service',
            'service_slug',
            'booking_type',
            'type',
            'category',
        ];

        foreach ($paths as $path) {
            $v = Arr::get($item, $path);
            if (! $this->isMeaningfulApiScalar($v)) {
                continue;
            }
            $s = trim((string) $v);

            return $this->prettifyServiceLabel($s);
        }

        return null;
    }

    protected function isMeaningfulApiScalar(mixed $v): bool
    {
        if ($v === null) {
            return false;
        }
        if (is_string($v)) {
            $s = trim($v);

            return $s !== '' && strcasecmp($s, 'null') !== 0 && strcasecmp($s, 'undefined') !== 0;
        }
        if (is_numeric($v)) {
            return true;
        }

        return false;
    }

    protected function prettifyServiceLabel(string $s): string
    {
        $t = str_replace(['-', '_'], ' ', $s);

        return (string) preg_replace_callback('/\b[a-z]/i', static fn (array $m) => strtoupper($m[0]), $t);
    }

    protected function extractStatusLabelFromApiRow(array $item): ?string
    {
        foreach ([
            'status_label',
            'status_display',
            'statusLabel',
            'status_text',
            'appointment_status_label',
            'booking_status_label',
        ] as $path) {
            $v = Arr::get($item, $path);
            if ($this->isMeaningfulApiScalar($v)) {
                return trim((string) $v);
            }
        }

        return null;
    }

    protected function extractPaymentTypeFromApiRow(array $item): ?string
    {
        foreach ([
            'payment_type',
            'paymentType',
            'paymemnt_type',
            'payment_method',
            'paymentMethod',
            'payment_method_label',
            'payment_status',
            'paymentStatus',
            'payment_status_label',
            'pay_type',
            'billing_type',
            'payment.name',
            'payment.label',
        ] as $path) {
            $v = Arr::get($item, $path);
            if ($this->isMeaningfulApiScalar($v)) {
                return trim((string) $v);
            }
        }

        return null;
    }

    /**
     * Map a human status line to internal slug when the API omits numeric/string status.
     */
    protected function guessStatusSlugFromLabel(?string $label): ?string
    {
        if ($label === null || trim($label) === '') {
            return null;
        }
        $s = strtolower($label);

        return match (true) {
            str_contains($s, 'cancel') => 'cancelled',
            str_contains($s, 'no show') || str_contains($s, 'no-show') || str_contains($s, 'no_show') => 'no_show',
            str_contains($s, 'complet') => 'completed',
            str_contains($s, 'confirm') => 'confirmed',
            (str_contains($s, 'paid') && ! str_contains($s, 'unpaid') && ! str_contains($s, 'not paid')) => 'paid',
            str_contains($s, 'pend') || str_contains($s, 'await') => 'pending',
            default => null,
        };
    }

    protected function inferIsPaidFromPaymentType(?string $paymentType, mixed $existingIsPaid): bool
    {
        if (filter_var($existingIsPaid, FILTER_VALIDATE_BOOLEAN)) {
            return true;
        }
        if ($paymentType === null || trim((string) $paymentType) === '') {
            return false;
        }
        $t = strtolower(trim((string) $paymentType));
        if (in_array($t, ['free', 'unpaid', 'no_payment', 'none', 'nil', 'pending', 'not_paid'], true)) {
            return false;
        }
        if (str_contains($t, 'payment pending') || str_contains($t, 'pay pending')) {
            return false;
        }
        if (str_contains($t, 'paid') && ! str_contains($t, 'unpaid')) {
            return true;
        }
        if (str_contains($t, 'stripe') || str_contains($t, 'card') || str_contains($t, 'paypal')) {
            return true;
        }

        return false;
    }

    protected function statusLabelForCalendar(?string $apiLabel, string $normalizedStatus): string
    {
        if ($apiLabel !== null && $this->scalarForCalendar($apiLabel) !== '') {
            return $this->scalarForCalendar($apiLabel);
        }

        return ucwords(str_replace('_', ' ', $normalizedStatus));
    }

    protected function paymentDisplayLabel(?string $paymentType, bool $isPaid): string
    {
        if ($paymentType !== null && $this->scalarForCalendar($paymentType) !== '') {
            return $this->scalarForCalendar($paymentType);
        }

        return $isPaid ? 'Paid' : 'Free';
    }

    /**
     * Public booking APIs use varying keys (and sometimes nest under client / contact).
     */
    protected function extractPhoneFromApiRow(array $item): ?string
    {
        $paths = [
            'phone',
            'client_phone',
            'phone_no',
            'phone_number',
            'phoneNumber',
            'phoneno',
            'mobile',
            'mobile_no',
            'mobile_number',
            'mobile_phone',
            'contact_phone',
            'telephone',
            'tel',
            'cell',
            'cell_phone',
            'contact_number',
            'primary_phone',
            'whatsapp',
            'whatsapp_number',
            'client.mobile',
            'client.phone',
            'client.phone_number',
            'client.phone_no',
            'client.mobile_phone',
            'contact.phone',
            'contact.mobile',
            'customer.phone',
            'user.phone',
            'lead.phone',
            'applicant.phone',
        ];

        foreach ($paths as $path) {
            $v = Arr::get($item, $path);
            if (! $this->isMeaningfulApiScalar($v)) {
                continue;
            }
            $s = preg_replace('/\s+/', ' ', trim((string) $v));

            return $s;
        }

        $cc = Arr::get($item, 'country_code') ?? Arr::get($item, 'phone_country_code');
        $national = Arr::get($item, 'national_number')
            ?? Arr::get($item, 'local_number')
            ?? Arr::get($item, 'phone_without_code');
        if ($this->isMeaningfulApiScalar($cc) && $this->isMeaningfulApiScalar($national)) {
            return trim((string) $cc) . ' ' . trim((string) $national);
        }

        return null;
    }

    protected function normalizeConsultant(array $item): ?array
    {
        if (! empty($item['consultant']) && is_array($item['consultant'])) {
            $c = $item['consultant'];

            return [
                'id' => $c['id'] ?? null,
                'name' => $c['name'] ?? '',
            ];
        }

        return null;
    }

    protected function normalizeMeetingType(?string $meetingType): string
    {
        if (empty($meetingType)) {
            return 'in_person';
        }

        $normalized = strtolower(trim(str_replace([' ', '-'], '_', $meetingType)));

        return match ($normalized) {
            'in_person', 'inperson', 'office', 'onsite' => 'in_person',
            'phone', 'telephone', 'call' => 'phone',
            'video', 'videocall', 'video_call', 'zoom', 'online' => 'video',
            default => 'in_person',
        };
    }

    protected function normalizeStatus(mixed $status): string
    {
        if ($status === null || $status === '') {
            return 'pending';
        }

        if (is_int($status) || (is_string($status) && ctype_digit($status))) {
            return match ((int) $status) {
                1 => 'pending',
                2 => 'paid',
                3 => 'confirmed',
                4 => 'completed',
                5 => 'cancelled',
                6 => 'no_show',
                default => 'pending',
            };
        }

        $s = strtolower((string) $status);

        return match ($s) {
            'pending', 'paid', 'confirmed', 'completed', 'cancelled', 'no_show', 'rescheduled' => $s,
            default => 'pending',
        };
    }

    protected function formatDateTimeForJson(mixed $when): ?string
    {
        if ($when === null || $when === '') {
            return null;
        }

        try {
            return Carbon::parse($when, config('app.timezone'))->toIso8601String();
        } catch (Throwable) {
            return null;
        }
    }

    protected function parseAppointmentDateTime(mixed $when): ?Carbon
    {
        if ($when === null || $when === '') {
            return null;
        }

        try {
            return Carbon::parse($when, config('app.timezone'));
        } catch (Throwable) {
            return null;
        }
    }
}
