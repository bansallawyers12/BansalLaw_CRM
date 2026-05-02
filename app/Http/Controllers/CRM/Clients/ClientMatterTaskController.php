<?php

namespace App\Http\Controllers\CRM\Clients;

use App\Http\Controllers\Controller;
use App\Models\ClientMatter;
use App\Models\ClientMatterTask;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ClientMatterTaskController extends Controller
{
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
     * Tasks are listed per client; this picks an active matter row only to satisfy client_matter_id FK on create.
     */
    private function resolveDefaultMatterForClient(int $clientId): ?ClientMatter
    {
        if ($clientId < 1) {
            return null;
        }

        return ClientMatter::query()
            ->where('client_id', $clientId)
            ->where(function ($q) {
                $q->where('matter_status', 1)->orWhere('matter_status', '1');
            })
            ->orderByDesc('id')
            ->first();
    }

    public function index(Request $request)
    {
        $clientId = (int) $request->query('client_id');
        if ($clientId < 1) {
            return response()->json(['status' => false, 'message' => 'Invalid client'], 422);
        }

        $tasks = ClientMatterTask::query()
            ->where('client_id', $clientId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return response()->json(['status' => true, 'data' => $tasks]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'client_id' => 'required|integer|min:1',
            'matter_id' => 'nullable|integer|min:1',
            'title'     => 'required|string|max:500',
        ]);

        $clientId = (int) $validated['client_id'];
        $matter = null;
        if (! empty($validated['matter_id'])) {
            $matter = $this->resolveMatter($clientId, (int) $validated['matter_id']);
        }
        if (! $matter) {
            $matter = $this->resolveDefaultMatterForClient($clientId);
        }
        if (! $matter) {
            return response()->json(['status' => false, 'message' => 'No active matter for this client. Add a matter before creating tasks.'], 422);
        }

        $title = trim($validated['title']);
        if ($title === '') {
            return response()->json(['status' => false, 'message' => 'Title is required'], 422);
        }

        $maxSort = (int) ClientMatterTask::where('client_id', $clientId)->max('sort_order');

        $task = new ClientMatterTask;
        $task->client_matter_id = $matter->id;
        $task->client_id        = $matter->client_id;
        $task->title            = $title;
        $task->is_done          = false;
        $task->sort_order       = $maxSort + 1;
        $task->created_by       = Auth::user()->id;
        $task->save();

        return response()->json(['status' => true, 'data' => $task]);
    }

    public function update(Request $request, ClientMatterTask $task)
    {
        $clientId = (int) $request->input('client_id');
        if ($clientId < 1 || (int) $task->client_id !== $clientId) {
            return response()->json(['status' => false, 'message' => 'Forbidden'], 403);
        }

        $changed = false;

        if ($request->exists('is_done')) {
            $task->is_done = $this->parseBoolean($request->input('is_done'));
            $changed = true;
        }

        if ($request->has('title')) {
            $request->validate(['title' => 'required|string|max:500']);
            $t = trim((string) $request->input('title'));
            if ($t === '') {
                return response()->json(['status' => false, 'message' => 'Title is required'], 422);
            }
            $task->title = $t;
            $changed = true;
        }

        if (! $changed) {
            return response()->json(['status' => false, 'message' => 'No changes submitted'], 422);
        }

        $task->save();

        return response()->json(['status' => true, 'data' => $task]);
    }

    public function destroy(Request $request, ClientMatterTask $task)
    {
        $clientId = (int) $request->input('client_id');
        if ($clientId < 1 || (int) $task->client_id !== $clientId) {
            return response()->json(['status' => false, 'message' => 'Forbidden'], 403);
        }

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
}
