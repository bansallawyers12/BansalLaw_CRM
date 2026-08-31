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
}
