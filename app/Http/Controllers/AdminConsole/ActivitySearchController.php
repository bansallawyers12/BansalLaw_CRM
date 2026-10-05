<?php

namespace App\Http\Controllers\AdminConsole;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Staff;
use App\Services\ActivitySearchService;
use App\Services\AdminConsoleFormDataService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;

class ActivitySearchController extends Controller
{
    public function __construct(
        private ActivitySearchService $activitySearch,
        private AdminConsoleFormDataService $formData
    ) {
        $this->middleware('auth:admin');
    }

    /**
     * Display the activity search page
     */
    public function index(Request $request)
    {
        $actor = Auth::user();
        if (! ($actor instanceof Staff && $actor->hasEffectiveSuperAdminPrivileges())) {
            return Redirect::to('/dashboard')->with('error', 'Unauthorized: Only Super Admins can access Activity Search.');
        }

        $staffList = $this->formData->activitySearchStaffList();

        $activityTypes = [
            'activity' => 'General Activity',
            'sms' => 'SMS',
            'email' => 'Email',
            'document' => 'Document',
            'note' => 'Note',
            'financial' => 'Financial',
            'lead_converted' => 'Lead Converted',
            'followup_scheduled' => 'Task Scheduled',
            'followup_completed' => 'Task Completed',
            'followup_rescheduled' => 'Task Rescheduled',
            'followup_cancelled' => 'Task Cancelled',
        ];

        $taskGroups = [
            'Call' => 'Call',
            'Checklist' => 'Checklist',
            'Review' => 'Review',
            'Query' => 'Query',
            'Urgent' => 'Urgent',
            'Personal Task' => 'Personal Task',
        ];

        $activities = collect();
        $totalActivities = 0;

        if ($request->has('search')) {
            $query = $this->activitySearch->buildQuery($request);
            $totalActivities = (clone $query)->count();
            $activities = $query
                ->orderByDesc('activities_logs.created_at')
                ->orderByDesc('activities_logs.id')
                ->paginate(50)
                ->appends($request->except('page'));
        }

        return view('AdminConsole.system.activity-search.index', compact(
            'staffList',
            'activityTypes',
            'taskGroups',
            'activities',
            'totalActivities'
        ));
    }

    /**
     * Export activities to CSV (chunked stream; no full in-memory load).
     */
    public function export(Request $request)
    {
        $actor = Auth::user();
        if (! ($actor instanceof Staff && $actor->hasEffectiveSuperAdminPrivileges())) {
            return Redirect::to('/dashboard')->with('error', 'Unauthorized: Only Super Admins can export activities.');
        }

        return $this->activitySearch->streamCsvExport($request);
    }

    /**
     * Search clients for autocomplete
     */
    public function searchClients(Request $request)
    {
        $query = $request->get('q', '');

        if (strlen($query) < 2) {
            return response()->json([]);
        }

        $clients = Admin::whereIn('type', ['client', 'lead'])
            ->where(function ($q) use ($query) {
                $searchLower = strtolower($query);
                $q->whereRaw('LOWER(first_name) LIKE ?', ['%' . $searchLower . '%'])
                    ->orWhereRaw('LOWER(last_name) LIKE ?', ['%' . $searchLower . '%'])
                    ->orWhereRaw('LOWER(email) LIKE ?', ['%' . $searchLower . '%']);
            })
            ->select(['id', 'first_name', 'last_name', 'email'])
            ->limit(20)
            ->get()
            ->map(function ($client) {
                return [
                    'id' => $client->id,
                    'text' => $client->first_name . ' ' . $client->last_name . ' (' . $client->email . ')',
                ];
            });

        return response()->json($clients);
    }

    /**
     * Get single activity log detail by ID for modal viewer
     */
    public function show(Request $request, $id = null)
    {
        $actor = Auth::user();
        if (! ($actor instanceof Staff && $actor->hasEffectiveSuperAdminPrivileges())) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized: Only Super Admins can view activity details.',
            ], 403);
        }

        $activityId = (int) ($id ?: $request->input('id'));
        if (! $activityId) {
            return response()->json([
                'status' => false,
                'message' => 'Activity ID is required.',
            ], 400);
        }

        $activity = \App\Models\ActivitiesLog::query()
            ->select(
                'activities_logs.*',
                'creator.first_name as creator_first_name',
                'creator.last_name as creator_last_name',
                'creator.email as creator_email',
                'client.first_name as client_first_name',
                'client.last_name as client_last_name',
                'client.email as client_email'
            )
            ->leftJoin('staff as creator', 'activities_logs.created_by', '=', 'creator.id')
            ->leftJoin('admins as client', 'activities_logs.client_id', '=', 'client.id')
            ->where('activities_logs.id', $activityId)
            ->first();

        if (! $activity) {
            return response()->json([
                'status' => false,
                'message' => 'Activity not found.',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => [
                'id' => $activity->id,
                'subject' => $activity->subject ?? 'N/A',
                'description' => $activity->description ?? '',
                'activity_type' => $activity->activity_type ?? 'N/A',
                'task_group' => $activity->task_group ?? null,
                'task_status' => $activity->task_status,
                'followup_date' => $activity->followup_date ? (string) $activity->followup_date : null,
                'created_at' => $activity->created_at ? (string) $activity->created_at : null,
                'creator' => trim(($activity->creator_first_name ?? '') . ' ' . ($activity->creator_last_name ?? '')),
                'client' => trim(($activity->client_first_name ?? '') . ' ' . ($activity->client_last_name ?? '')),
                'client_id' => $activity->client_id,
            ],
        ]);
    }
}

