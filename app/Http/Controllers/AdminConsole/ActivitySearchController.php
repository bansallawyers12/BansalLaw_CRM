<?php

namespace App\Http\Controllers\AdminConsole;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use App\Models\ActivitiesLog;
use App\Models\Admin;
use Carbon\Carbon;

class ActivitySearchController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth:admin');
    }

    /**
     * Display the activity search page
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        // Check if user is Super Admin (role = 1)
        if (Auth::user()->role != 1) {
            return Redirect::to('/dashboard')->with('error', 'Unauthorized: Only Super Admins can access Activity Search.');
        }

        // Get all active staff
        $staffList = \App\Models\Staff::where('status', 1)
            ->orderBy('first_name', 'ASC')
            ->get()
            ->map(function($staff) {
                return [
                    'id' => $staff->id,
                    'name' => $staff->first_name . ' ' . $staff->last_name,
                    'email' => $staff->email
                ];
            });

        // Get activity types for filter
        $activityTypes = [
            'activity' => 'General Activity',
            'sms' => 'SMS',
            'email' => 'Email',
            'document' => 'Document',
            'note' => 'Note',
            'financial' => 'Financial',
            'lead_converted' => 'Lead Converted',
            'followup_scheduled' => 'Action Scheduled',
            'followup_completed' => 'Action Completed',
            'followup_rescheduled' => 'Action Rescheduled',
            'followup_cancelled' => 'Action Cancelled',
        ];

        // Get task groups (action categories)
        $taskGroups = [
            'Call' => 'Call',
            'Checklist' => 'Checklist',
            'Review' => 'Review',
            'Query' => 'Query',
            'Urgent' => 'Urgent',
            'Personal Action' => 'Personal Action',
        ];

        $activities = collect();
        $totalActivities = 0;

        // Process search if form is submitted
        if ($request->has('search')) {
            $query = ActivitiesLog::query()
                ->select(
                    'activities_logs.*',
                    'creator.first_name as creator_first_name',
                    'creator.last_name as creator_last_name',
                    'creator.email as creator_email',
                    'assignee.first_name as assignee_first_name',
                    'assignee.last_name as assignee_last_name',
                    'assignee.email as assignee_email',
                    'client.first_name as client_first_name',
                    'client.last_name as client_last_name',
                    'client.email as client_email'
                )
                ->leftJoin('staff as creator', 'activities_logs.created_by', '=', 'creator.id')
                ->leftJoin('staff as assignee', 'activities_logs.use_for', '=', 'assignee.id')
                ->leftJoin('admins as client', 'activities_logs.client_id', '=', 'client.id');

            // Filter by Assigner (created_by)
            if ($request->filled('assigner_id')) {
                $query->where('activities_logs.created_by', $request->assigner_id);
            }

            // Filter by Assignee (use_for)
            if ($request->filled('assignee_id')) {
                $query->where('activities_logs.use_for', $request->assignee_id);
            }

            // Filter by Client
            if ($request->filled('client_id')) {
                $query->where('activities_logs.client_id', $request->client_id);
            }

            // Filter by Activity Type
            if ($request->filled('activity_type')) {
                $query->where('activities_logs.activity_type', $request->activity_type);
            }

            // Filter by Task Status (Action Status)
            if ($request->filled('task_status')) {
                $query->where('activities_logs.task_status', $request->task_status);
            }

            // Filter by Task Group (Action Category)
            if ($request->filled('task_group')) {
                $query->where('activities_logs.task_group', $request->task_group);
            }

            // Filter by Date Range
            if ($request->filled('date_from')) {
                $dateFrom = Carbon::parse($request->date_from)->startOfDay();
                $query->where('activities_logs.created_at', '>=', $dateFrom);
            }

            if ($request->filled('date_to')) {
                $dateTo = Carbon::parse($request->date_to)->endOfDay();
                $query->where('activities_logs.created_at', '<=', $dateTo);
            }

            // Filter by Keyword (search in subject and description)
            if ($request->filled('keyword')) {
                $keyword = $request->keyword;
                $query->where(function($q) use ($keyword) {
                    $q->where('activities_logs.subject', 'ILIKE', '%' . $keyword . '%')
                      ->orWhere('activities_logs.description', 'ILIKE', '%' . $keyword . '%');
                });
            }

            // Get total count
            $totalActivities = $query->count();

            // Order by most recent first
            $query->orderBy('activities_logs.created_at', 'DESC');

            // Paginate results
            $activities = $query->paginate(50)->appends($request->except('page'));
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
     * Export activities to CSV
     *
     * @return \Illuminate\Http\Response
     */
    public function export(Request $request)
    {
        // Check if user is Super Admin (role = 1)
        if (Auth::user()->role != 1) {
            return Redirect::to('/dashboard')->with('error', 'Unauthorized: Only Super Admins can export activities.');
        }

        $query = ActivitiesLog::query()
            ->select(
                'activities_logs.*',
                'creator.first_name as creator_first_name',
                'creator.last_name as creator_last_name',
                'creator.email as creator_email',
                'assignee.first_name as assignee_first_name',
                'assignee.last_name as assignee_last_name',
                'assignee.email as assignee_email',
                'client.first_name as client_first_name',
                'client.last_name as client_last_name',
                'client.email as client_email'
            )
            ->leftJoin('staff as creator', 'activities_logs.created_by', '=', 'creator.id')
            ->leftJoin('staff as assignee', 'activities_logs.use_for', '=', 'assignee.id')
            ->leftJoin('admins as client', 'activities_logs.client_id', '=', 'client.id');

        // Apply same filters as index
        if ($request->filled('assigner_id')) {
            $query->where('activities_logs.created_by', $request->assigner_id);
        }

        if ($request->filled('assignee_id')) {
            $query->where('activities_logs.use_for', $request->assignee_id);
        }

        if ($request->filled('client_id')) {
            $query->where('activities_logs.client_id', $request->client_id);
        }

        if ($request->filled('activity_type')) {
            $query->where('activities_logs.activity_type', $request->activity_type);
        }

        if ($request->filled('task_status')) {
            $query->where('activities_logs.task_status', $request->task_status);
        }

        if ($request->filled('task_group')) {
            $query->where('activities_logs.task_group', $request->task_group);
        }

        if ($request->filled('date_from')) {
            $dateFrom = Carbon::parse($request->date_from)->startOfDay();
            $query->where('activities_logs.created_at', '>=', $dateFrom);
        }

        if ($request->filled('date_to')) {
            $dateTo = Carbon::parse($request->date_to)->endOfDay();
            $query->where('activities_logs.created_at', '<=', $dateTo);
        }

        if ($request->filled('keyword')) {
            $keyword = $request->keyword;
            $query->where(function($q) use ($keyword) {
                $q->where('activities_logs.subject', 'ILIKE', '%' . $keyword . '%')
                  ->orWhere('activities_logs.description', 'ILIKE', '%' . $keyword . '%');
            });
        }

        // Limit export to 5000 records
        $activities = $query->orderBy('activities_logs.created_at', 'DESC')->limit(5000)->get();

        // Generate CSV
        $filename = 'activity_search_' . date('Y-m-d_His') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function() use ($activities) {
            $file = fopen('php://output', 'w');
            
            // Add CSV headers
            fputcsv($file, [
                'Activity ID',
                'Date & Time',
                'Assigner Name',
                'Assigner Email',
                'Assignee Name',
                'Assignee Email',
                'Client Name',
                'Client Email',
                'Activity Type',
                'Action Category',
                'Status',
                'Subject',
                'Description',
                'Follow-up Date'
            ]);

            // Add data rows
            foreach ($activities as $activity) {
                $assignerName = $activity->creator_first_name . ' ' . $activity->creator_last_name;
                $assigneeName = $activity->assignee_first_name ? ($activity->assignee_first_name . ' ' . $activity->assignee_last_name) : 'N/A';
                $clientName = $activity->client_first_name . ' ' . $activity->client_last_name;
                
                $status = 'N/A';
                if ($activity->task_group) {
                    $status = $activity->task_status == 1 ? 'Completed' : 'Incomplete';
                }

                fputcsv($file, [
                    $activity->id,
                    $activity->created_at ? $activity->created_at->format('Y-m-d H:i:s') : '',
                    $assignerName,
                    $activity->creator_email ?? '',
                    $assigneeName,
                    $activity->assignee_email ?? '',
                    $clientName,
                    $activity->client_email ?? '',
                    $activity->activity_type ?? 'N/A',
                    $activity->task_group ?? 'N/A',
                    $status,
                    $activity->subject ?? '',
                    strip_tags($activity->description ?? ''),
                    $activity->followup_date ? $activity->followup_date->format('Y-m-d H:i:s') : ''
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Search clients for autocomplete
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function searchClients(Request $request)
    {
        $query = $request->get('q', '');
        
        if (strlen($query) < 2) {
            return response()->json([]);
        }

        $clients = Admin::whereIn('type', ['client', 'lead'])
            ->where(function($q) use ($query) {
                $searchLower = strtolower($query);
                $q->whereRaw('LOWER(first_name) LIKE ?', ['%' . $searchLower . '%'])
                  ->orWhereRaw('LOWER(last_name) LIKE ?', ['%' . $searchLower . '%'])
                  ->orWhereRaw('LOWER(email) LIKE ?', ['%' . $searchLower . '%']);
            })
            ->limit(20)
            ->get()
            ->map(function($client) {
                return [
                    'id' => $client->id,
                    'text' => $client->first_name . ' ' . $client->last_name . ' (' . $client->email . ')'
                ];
            });

        return response()->json($clients);
    }
}
