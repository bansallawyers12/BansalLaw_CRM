<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\ClientCourtHearing;
use App\Models\ClientMatter;
use App\Models\Note;
use App\Models\Staff;
use App\Models\StaffCalendarEvent;
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
        $tz = $staff->time_zone ?: config('app.timezone');
        $staffId = (int) $staff->id;

        $events = array_merge(
            $this->staffCalendarEvents($staffId, $request),
            $this->courtHearings($staffId, $request),
            $this->actionDeadlines($staffId, $request, $tz),
            $this->matterDeadlines($staffId, $request, $tz)
        );

        usort($events, fn (array $a, array $b) => strcmp(
            (string) ($a['starts_at'] ?? ''),
            (string) ($b['starts_at'] ?? '')
        ));

        try {
            $events = app(\App\Services\CalendarSync\CalendarSyncStatusAttacher::class)->attach($events);
        } catch (Exception) {
            // table may not exist yet
        }

        return $events;
    }

    /**
     * @return array{today: int, this_week: int, overdue_actions: int}
     */
    public function statsForStaff(Staff $staff): array
    {
        $tz = $staff->time_zone ?: config('app.timezone');
        $today = Carbon::today($tz);
        $weekEnd = $today->copy()->endOfWeek();
        $staffId = (int) $staff->id;

        $events = $this->eventsForStaffRequest($staff, new Request([
            'start' => $today->copy()->subDays(7)->toIso8601String(),
            'end' => $weekEnd->copy()->addDay()->toIso8601String(),
        ]));

        $todayCount = 0;
        $weekCount = 0;

        foreach ($events as $event) {
            if (empty($event['starts_at'])) {
                continue;
            }
            try {
                $start = Carbon::parse($event['starts_at'], $tz);
            } catch (Exception) {
                continue;
            }
            if ($start->isSameDay($today)) {
                $todayCount++;
            }
            if ($start->betweenIncluded($today, $weekEnd)) {
                $weekCount++;
            }
        }

        $overdueActions = Note::query()
            ->where('assigned_to', $staffId)
            ->where('is_action', 1)
            ->where('status', 0)
            ->whereNotNull('note_deadline')
            ->whereDate('note_deadline', '<', $today->toDateString())
            ->count();

        return [
            'today' => $todayCount,
            'this_week' => $weekCount,
            'overdue_actions' => $overdueActions,
        ];
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
                $this->staffCalendarFeed->payloadFromStaffEvent($e)
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
                $this->staffCalendarFeed->payloadFromCourtHearing($h)
            ))
            ->values()
            ->all();
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
                $title = trim(($clientName ? $clientName . ' — ' : '') . ($note->title ?: 'Action'));

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
    protected function wrapStaffEvent(array $payload): array
    {
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
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     */
    protected function applyDatetimeWindow(Builder $query, string $column, Request $request): void
    {
        if ($request->filled('start') && $request->filled('end')) {
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
    }

    /**
     * @param  Builder<ClientCourtHearing>  $query
     */
    protected function applyHearingDateWindow(Builder $query, Request $request): void
    {
        if ($request->filled('start') && $request->filled('end')) {
            try {
                $rangeStart = Carbon::parse($request->get('start'), config('app.timezone'))->startOfDay();
                $rangeEnd = Carbon::parse($request->get('end'), config('app.timezone'))->startOfDay();
                $query->whereDate('hearing_date', '>=', $rangeStart->toDateString())
                    ->whereDate('hearing_date', '<', $rangeEnd->toDateString());
            } catch (Exception) {
                // no filter
            }
        }
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     */
    protected function applyDateColumnWindow(Builder $query, string $column, Request $request): void
    {
        if ($request->filled('start') && $request->filled('end')) {
            try {
                $rangeStart = Carbon::parse($request->get('start'), config('app.timezone'))->startOfDay();
                $rangeEnd = Carbon::parse($request->get('end'), config('app.timezone'))->startOfDay();
                $query->whereDate($column, '>=', $rangeStart->toDateString())
                    ->whereDate($column, '<', $rangeEnd->toDateString());
            } catch (Exception) {
                // no filter
            }
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
            'action' => '#c0392b',
            'matter_deadline' => '#9b1c2e',
            'court_hearing' => '#5c3d8f',
            default => StaffCalendarFeedService::colorForEventType($type),
        };

        $textColor = ($type === 'reminder' && $kind === 'staff_event') ? '#1A2C40' : '#fff';

        $classNames = ['event-' . $type, 'event-kind-' . $kind];
        $syncStatus = $row['zoho_sync_status'] ?? null;
        if ($syncStatus === 'linked') {
            $classNames[] = 'fc-zoho-synced';
        } elseif ($syncStatus === 'failed') {
            $classNames[] = 'fc-zoho-failed';
        } elseif ($syncStatus === 'pending') {
            $classNames[] = 'fc-zoho-pending';
        }

        return [
            'id' => (string) ($row['id'] ?? uniqid('evt-', true)),
            'title' => (string) ($row['title'] ?? 'Event'),
            'start' => $start,
            'end' => $end,
            'allDay' => (bool) ($row['is_all_day'] ?? false),
            'backgroundColor' => $color,
            'borderColor' => $color,
            'textColor' => $textColor,
            'classNames' => $classNames,
            'extendedProps' => $row,
        ];
    }
}
