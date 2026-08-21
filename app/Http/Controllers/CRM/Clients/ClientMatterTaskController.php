<?php

namespace App\Http\Controllers\CRM\Clients;

use App\Http\Controllers\Controller;
use App\Models\ClientMatter;
use App\Models\ClientMatterTask;
use App\Services\ActionTaskTimelineService;
use App\Services\ClientMatterTaskSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

use App\Http\Controllers\Concerns\EnsuresCrmRecordAccess;

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
            return response()->json(['status' => false, 'message' => 'Invalid matter'], 422);
        }

        $tasks = ClientMatterTask::query()
            ->where('client_id', $clientId)
            ->where('client_matter_id', $matter->id)
            ->with(['creator:id,first_name,last_name'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return response()->json(['status' => true, 'data' => $tasks]);
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
        ]);

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

        app(ClientMatterTaskSyncService::class)->mirrorClientTaskToAction($task);
        $task->refresh();

        app(ActionTaskTimelineService::class)->logTaskCreated($task, $matter);

        return response()->json(['status' => true, 'data' => $task]);
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
            app(ActionTaskTimelineService::class)->logTaskUpdated($task, $timelineChanges);
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
