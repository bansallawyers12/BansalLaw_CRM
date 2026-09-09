<?php

namespace App\Http\Controllers\CRM\Clients;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\EnsuresCrmRecordAccess;
use App\Models\ClientMatter;
use App\Models\ClientMatterTask;
use App\Models\Staff;
use App\Models\StaffCalendarEvent;
use App\Services\ClientMatterTaskSyncService;
use App\Services\TaskTimelineService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class ClientMatterTaskController extends Controller
{
    use EnsuresCrmRecordAccess;

    public function __construct()
    {
        $this->middleware('auth:admin');
    }

    private function resolveMatter(int $clientId, int $matterId): ?ClientMatter
    {
        if ($clientId < 1 || $matterId < 1) {
            return null;
        }

        return ClientMatter::where('id', $matterId)->where('client_id', $clientId)->first();
    }

    /**
     * Resolve the client_matters row from request (numeric id and/or matter ref like FAM_1).
     */
    private function resolveMatterFromRequest(Request $request, int $clientId): ?ClientMatter
    {
        if ($clientId < 1) {
            return null;
        }

        $matterId = (int) ($request->input('matter_id')
            ?: $request->input('client_matter_id')
            ?: $request->query('matter_id')
            ?: $request->query('client_matter_id')
            ?: 0);

        if ($matterId > 0) {
            $matter = $this->resolveMatter($clientId, $matterId);
            if ($matter) {
                return $matter;
            }
        }

        $matterRef = trim((string) ($request->input('matter_ref')
            ?: $request->query('matter_ref')
            ?: $request->input('matter_ref_no')
            ?: $request->query('matter_ref_no')
            ?: ''));

        if ($matterRef === '') {
            return null;
        }

        return ClientMatter::query()
            ->where('client_id', $clientId)
            ->where('client_unique_matter_no', $matterRef)
            ->first();
    }

    public function index(Request $request)
    {
        $clientId = (int) $request->query('client_id');
        if ($clientId < 1) {
            return response()->json(['status' => false, 'message' => 'Invalid client'], 422);
        }

        $this->ensureCrmRecordAccess($clientId);

        $matter = $this->resolveMatterFromRequest($request, $clientId);
        if (! $matter) {
            // Lead / client with no matter selected: reminders only (no matter tasks).
            return response()->json([
                'status' => true,
                'data' => [],
                'reminders' => $this->clientRemindersPayload($clientId, null),
                'page' => 1,
                'per_page' => 50,
                'total' => 0,
                'open_count' => 0,
                'done_count' => 0,
                'has_more' => false,
            ]);
        }

        $perPage = (int) ($request->query('per_page') ?: config('crm.notes.matter_task_per_page', 50));
        $perPage = max(5, min(200, $perPage));
        $page = max(1, (int) $request->query('page', 1));

        $base = ClientMatterTask::query()
            ->where('client_id', $clientId)
            ->where('client_matter_id', $matter->id);

        $total = (clone $base)->count();
        $openCount = (clone $base)->where('is_done', false)->count();
        $doneCount = max(0, $total - $openCount);

        $tasks = (clone $base)
            ->with([
                'creator:id,first_name,last_name',
                'note:id,user_id,assigned_to,unique_group_id',
                'note.user:id,first_name,last_name',
                'note.assignedUser:id,first_name,last_name',
            ])
            ->orderBy('is_done')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->forPage($page, $perPage)
            ->get();

        $taskSync = app(ClientMatterTaskSyncService::class);
        $taskSync->repairCreatedByFromLinkedNotes($tasks);

        // Prefer linked Action creator for "Added by"; include assignee when different.
        $data = $tasks->map(static function (ClientMatterTask $task) {
            $row = $task->toArray();
            $note = $task->note;
            $noteCreator = $note?->user;
            if ($noteCreator) {
                $row['created_by'] = (int) $noteCreator->id;
                $row['creator'] = [
                    'id' => (int) $noteCreator->id,
                    'first_name' => $noteCreator->first_name,
                    'last_name' => $noteCreator->last_name,
                ];
            }

            $assignee = $note?->assignedUser;
            if ($assignee) {
                $row['assignee'] = [
                    'id' => (int) $assignee->id,
                    'first_name' => $assignee->first_name,
                    'last_name' => $assignee->last_name,
                ];
            } else {
                $row['assignee'] = null;
            }

            $row['item_kind'] = 'task';

            return $row;
        })->values();

        return response()->json([
            'status' => true,
            'data' => $data,
            'reminders' => $this->clientRemindersPayload((int) $matter->client_id, (int) $matter->id),
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'open_count' => $openCount,
            'done_count' => $doneCount,
            'has_more' => ($page * $perPage) < $total,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'client_id'         => 'required|integer|min:1',
            'matter_id'         => 'nullable|integer|min:1',
            'client_matter_id'  => 'nullable|integer|min:1',
            'matter_ref'        => 'nullable|string|max:50',
            'matter_ref_no'     => 'nullable|string|max:50',
            'title'             => 'required|string|max:500',
            'due_date'          => 'nullable|date',
            'kind'              => 'nullable|in:task,reminder',
        ]);

        $kind = (string) ($validated['kind'] ?? 'task');
        if ($kind === 'reminder') {
            return $this->storeReminder($request, $validated);
        }

        $clientId = (int) $validated['client_id'];
        $this->ensureCrmRecordAccess($clientId);
        $matter = $this->resolveMatterFromRequest($request, $clientId);
        if (! $matter) {
            return response()->json(['status' => false, 'message' => 'Matter not found for this client. Select a matter before creating tasks.'], 422);
        }

        $title = trim($validated['title']);
        if ($title === '') {
            return response()->json(['status' => false, 'message' => 'Title is required'], 422);
        }

        $maxSort = (int) ClientMatterTask::where('client_matter_id', $matter->id)->max('sort_order');

        $task = new ClientMatterTask;
        $task->client_matter_id = $matter->id;
        $task->client_id        = $matter->client_id;
        $task->title            = $title;
        $task->due_date         = ! empty($validated['due_date']) ? $validated['due_date'] : null;
        $task->is_done          = false;
        $task->sort_order       = $maxSort + 1;
        $task->created_by       = Auth::user()->id;
        $task->save();

        app(ClientMatterTaskSyncService::class)->mirrorClientTaskToTaskNote($task);
        $task->refresh();

        app(TaskTimelineService::class)->logTaskCreated($task, $matter);

        $payload = $task->toArray();
        $payload['item_kind'] = 'task';

        return response()->json(['status' => true, 'data' => $payload]);
    }

    /**
     * Create a personal-calendar reminder linked to this client/matter.
     *
     * @param  array{client_id: int|string, title: string, due_date?: mixed}  $validated
     */
    protected function storeReminder(Request $request, array $validated)
    {
        $staff = Auth::guard('admin')->user();
        if (! $staff instanceof Staff || ! $staff->canAccessPersonalCalendar()) {
            return response()->json([
                'status' => false,
                'message' => 'Personal calendar access has not been granted. Ask a Super Admin to enable it on your staff profile.',
            ], 403);
        }

        $clientId = (int) $validated['client_id'];
        $this->ensureCrmRecordAccess($clientId);
        $matter = $this->resolveMatterFromRequest($request, $clientId);

        $title = trim((string) $validated['title']);
        if ($title === '') {
            return response()->json(['status' => false, 'message' => 'Title is required'], 422);
        }

        $dueYmd = $this->normalizeDueDateYmd($validated['due_date'] ?? null);
        if ($dueYmd === '') {
            return response()->json(['status' => false, 'message' => 'Choose a reminder date.'], 422);
        }

        $tz = (string) config('app.timezone');
        $startsAt = Carbon::parse($dueYmd . ' 09:00:00', $tz);
        $endsAt = $startsAt->copy()->addMinutes(30);

        $event = StaffCalendarEvent::create([
            'title' => $title,
            'event_type' => 'reminder',
            'status' => 'scheduled',
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'is_all_day' => true,
            'calendar_type' => null,
            'client_id' => $clientId,
            'client_matter_id' => $matter?->id,
            'notes' => null,
            'created_by_staff_id' => (int) $staff->id,
        ]);

        $event->load(['createdBy:id,first_name,last_name']);

        return response()->json([
            'status' => true,
            'data' => $this->reminderRowPayload($event),
        ]);
    }

    public function update(Request $request, ClientMatterTask $task)
    {
        $this->ensureCrmRecordAccess((int) $task->client_id);

        $clientIdInput = (int) $request->input('client_id');
        if ($clientIdInput > 0 && $clientIdInput !== (int) $task->client_id) {
            return response()->json(['status' => false, 'message' => 'Forbidden'], 403);
        }

        $changed = false;
        $timelineChanges = [];

        $sync = app(ClientMatterTaskSyncService::class);

        if ($request->exists('is_done')) {
            $oldDone = (bool) $task->is_done;
            $task->is_done = $this->parseBoolean($request->input('is_done'));
            if ($oldDone !== (bool) $task->is_done) {
                $timelineChanges['Status'] = [
                    'old' => $oldDone ? 'Completed' : 'Open',
                    'new' => $task->is_done ? 'Completed' : 'Open',
                ];
            }
            $changed = true;
        }

        if ($request->has('title')) {
            $request->validate(['title' => 'required|string|max:500']);
            $t = trim((string) $request->input('title'));
            if ($t === '') {
                return response()->json(['status' => false, 'message' => 'Title is required'], 422);
            }
            if ($t !== (string) $task->title) {
                $timelineChanges['Title'] = [
                    'old' => (string) $task->title,
                    'new' => $t,
                ];
            }
            $task->title = $t;
            $changed = true;
        }

        if ($request->exists('due_date')) {
            $request->validate(['due_date' => 'nullable|date']);
            $rawDue = $request->input('due_date');
            $newDue = ($rawDue !== null && trim((string) $rawDue) !== '') ? $rawDue : null;
            $oldDue = $task->due_date ? $task->due_date->format('Y-m-d') : '';
            $newDueStr = $this->normalizeDueDateYmd($newDue);
            if ($oldDue !== $newDueStr) {
                $timelineChanges['Due date'] = [
                    'old' => $oldDue !== '' ? date('d/m/Y', strtotime($oldDue)) : '',
                    'new' => $newDueStr !== '' ? date('d/m/Y', strtotime($newDueStr)) : '',
                ];
            }
            $task->due_date = $newDueStr !== '' ? $newDueStr : null;
            $changed = true;
        }

        if (! $changed) {
            return response()->json(['status' => false, 'message' => 'No changes submitted'], 422);
        }

        $task->save();

        if ($request->exists('is_done')) {
            $sync->syncCompletionFromClientTask($task);
        }
        if ($request->has('title')) {
            $sync->syncTitleFromClientTask($task);
        }
        if ($request->exists('due_date')) {
            $sync->syncDueDateFromClientTask($task);
        }

        if ($timelineChanges !== []) {
            app(TaskTimelineService::class)->logTaskUpdated($task, $timelineChanges);
        }

        return response()->json(['status' => true, 'data' => $task]);
    }

    public function destroy(Request $request, ClientMatterTask $task)
    {
        $this->ensureCrmRecordAccess((int) $task->client_id);

        $clientIdInput = (int) $request->input('client_id');
        if ($clientIdInput > 0 && $clientIdInput !== (int) $task->client_id) {
            return response()->json(['status' => false, 'message' => 'Forbidden'], 403);
        }

        app(ClientMatterTaskSyncService::class)->onClientTaskDeleted($task);
        $task->delete();

        return response()->json(['status' => true]);
    }

    public function destroyReminder(Request $request, int $event)
    {
        $staff = Auth::guard('admin')->user();
        if (! $staff instanceof Staff || ! $staff->canAccessPersonalCalendar()) {
            return response()->json([
                'status' => false,
                'message' => 'Personal calendar access has not been granted. Ask a Super Admin to enable it on your staff profile.',
            ], 403);
        }

        $calendarEvent = StaffCalendarEvent::query()
            ->whereKey($event)
            ->where('event_type', 'reminder')
            ->firstOrFail();

        $this->ensureCrmRecordAccess((int) $calendarEvent->client_id);

        $clientIdInput = (int) $request->input('client_id');
        if ($clientIdInput > 0 && $clientIdInput !== (int) $calendarEvent->client_id) {
            return response()->json(['status' => false, 'message' => 'Forbidden'], 403);
        }

        $calendarEvent->delete();

        return response()->json(['status' => true]);
    }

    /**
     * Reminders for a client/lead; optionally scoped to one matter.
     *
     * @return list<array<string, mixed>>
     */
    protected function clientRemindersPayload(int $clientId, ?int $matterId = null): array
    {
        if ($clientId < 1 || ! Schema::hasTable('staff_calendar_events')) {
            return [];
        }

        $tz = (string) config('app.timezone');
        $todayStart = Carbon::today($tz)->startOfDay();
        $hasStatus = Schema::hasColumn('staff_calendar_events', 'status');

        $query = StaffCalendarEvent::query()
            ->with(['createdBy:id,first_name,last_name'])
            ->where('client_id', $clientId)
            ->where('event_type', 'reminder');

        if ($matterId !== null && $matterId > 0) {
            $query->where('client_matter_id', $matterId);
        }

        if ($hasStatus) {
            // Active upcoming reminders + completed ones (so Tasks tab mirrors calendar status).
            $query->where(function ($q) use ($todayStart) {
                $q->where(function ($active) use ($todayStart) {
                    $active->where(function ($statusQ) {
                        $statusQ->whereNull('status')
                            ->orWhereNotIn('status', ['completed', 'cancelled']);
                    })->where('starts_at', '>=', $todayStart);
                })->orWhere('status', 'completed');
            });
        } else {
            $query->where('starts_at', '>=', $todayStart);
        }

        return $query
            ->orderByRaw($hasStatus
                ? "CASE WHEN status = 'completed' THEN 1 ELSE 0 END"
                : '0')
            ->orderBy('starts_at')
            ->orderBy('id')
            ->get()
            ->map(fn (StaffCalendarEvent $event) => $this->reminderRowPayload($event))
            ->values()
            ->all();
    }

    /**
     * @deprecated Use {@see clientRemindersPayload()}
     *
     * @return list<array<string, mixed>>
     */
    protected function matterRemindersPayload(ClientMatter $matter): array
    {
        return $this->clientRemindersPayload((int) $matter->client_id, (int) $matter->id);
    }

    /**
     * @return array<string, mixed>
     */
    protected function reminderRowPayload(StaffCalendarEvent $event): array
    {
        $tz = (string) config('app.timezone');
        $start = $event->starts_at?->copy()->timezone($tz);
        $creator = $event->createdBy;
        $status = 'scheduled';
        if (Schema::hasColumn('staff_calendar_events', 'status')) {
            $raw = strtolower(trim((string) ($event->status ?? 'scheduled')));
            if (in_array($raw, StaffCalendarEvent::STATUSES, true)) {
                $status = $raw;
            }
        }
        $isDone = $status === 'completed';

        return [
            'id' => (int) $event->id,
            'item_kind' => 'reminder',
            'staff_calendar_event_id' => (int) $event->id,
            'title' => (string) $event->title,
            'due_date' => $start ? $start->toDateString() : null,
            'starts_at' => $start?->toIso8601String(),
            'status' => $status,
            'status_label' => StaffCalendarEvent::STATUS_LABELS[$status] ?? ucfirst($status),
            'is_done' => $isDone,
            'client_id' => $event->client_id,
            'client_matter_id' => $event->client_matter_id,
            'created_by' => $event->created_by_staff_id,
            'created_at' => $event->created_at?->toIso8601String(),
            'creator' => $creator ? [
                'id' => (int) $creator->id,
                'first_name' => $creator->first_name,
                'last_name' => $creator->last_name,
            ] : null,
            'assignee' => null,
            'note_id' => null,
        ];
    }

    /**
     * Normalise checkbox / JSON / string values to bool (explicit 0 / false / off => false).
     */
    private function parseBoolean(mixed $raw): bool
    {
        if ($raw === true || $raw === 1) {
            return true;
        }
        if ($raw === false || $raw === 0) {
            return false;
        }
        if (is_string($raw)) {
            $n = strtolower(trim($raw));
            if (in_array($n, ['1', 'true', 'yes', 'on'], true)) {
                return true;
            }
            if (in_array($n, ['0', 'false', 'no', 'off', ''], true)) {
                return false;
            }
        }

        return filter_var($raw, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Normalise due dates to Y-m-d so ISO and d/m/Y compare as the same day.
     */
    private function normalizeDueDateYmd(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return '';
        }
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $raw, $m)) {
            return $m[1] . '-' . $m[2] . '-' . $m[3];
        }
        if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $raw, $m)) {
            return sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
        }

        $ts = strtotime($raw);

        return $ts ? date('Y-m-d', $ts) : '';
    }
}
