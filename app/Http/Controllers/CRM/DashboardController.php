<?php
namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use App\Http\Requests\DashboardRequest;
use App\Models\AppointmentConsultant;
use App\Models\Staff;
use App\Services\CrmAccess\StaffAccessGate;
use App\Services\DashboardService;
use App\Services\StaffPersonalCalendarFeedService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    protected $dashboardService;

    protected StaffPersonalCalendarFeedService $personalCalendarFeed;

    protected StaffAccessGate $accessGate;

    public function __construct(
        DashboardService $dashboardService,
        StaffPersonalCalendarFeedService $personalCalendarFeed,
        StaffAccessGate $accessGate
    ) {
        $this->middleware('auth:admin');
        $this->dashboardService = $dashboardService;
        $this->personalCalendarFeed = $personalCalendarFeed;
        $this->accessGate = $accessGate;
    }

    /**
     * Show the dashboard
     */
    public function index(DashboardRequest $request)
    {
        $staff = Auth::guard('admin')->user();
        $widgetFlags = $this->accessGate->dashboardWidgetFlags($staff instanceof Staff ? $staff : null);

        $needsWidgetData = $widgetFlags['active_matters']
            || $widgetFlags['closed_matters']
            || $widgetFlags['tasks']
            || $widgetFlags['recent_activity'];

        $dashboardData = $needsWidgetData
            ? $this->dashboardService->getDashboardData($request)
            : $this->emptyDashboardPayload();

        $dashboardData['dashboardWidgets'] = $widgetFlags;

        if ($staff instanceof Staff) {
            $canAccessPersonalCalendar = $staff->canAccessPersonalCalendar()
                && $this->accessGate->canDashboardWidget($staff, 'calendar');
            $dashboardData['canAccessPersonalCalendar'] = $canAccessPersonalCalendar;
            $dashboardData['calendarStats'] = null;
            $dashboardData['bookingCalendarType'] = $canAccessPersonalCalendar
                ? $this->personalCalendarFeed->ownedWebsiteCalendarType($staff)
                : null;
            $dashboardData['calendarPersonalLabel'] = trim((string) ($staff->first_name ?? ''))
                ?: (trim((string) (($staff->first_name ?? '') . ' ' . ($staff->last_name ?? ''))) ?: 'My calendar');
            $dashboardData['calendarTypeOptions'] = $canAccessPersonalCalendar
                ? StaffPersonalCalendarFeedService::CALENDAR_TYPES
                : [];
            $canFilterCalendarStaff = $canAccessPersonalCalendar
                && $this->personalCalendarFeed->canFilterStaffCalendar($staff);
            $dashboardData['canFilterCalendarStaff'] = $canFilterCalendarStaff;
            $dashboardData['calendarStaffOptions'] = $canFilterCalendarStaff
                ? $this->personalCalendarFeed->staffFilterOptions()
                : [];
            $dashboardData['bookingConsultants'] = $canAccessPersonalCalendar
                ? $this->bookingConsultantsForModal()
                : [];
        } else {
            $dashboardData['canAccessPersonalCalendar'] = false;
            $dashboardData['calendarStats'] = ['today' => 0, 'this_week' => 0, 'overdue_actions' => 0];
            $dashboardData['bookingCalendarType'] = null;
            $dashboardData['calendarTypeOptions'] = [];
            $dashboardData['canFilterCalendarStaff'] = false;
            $dashboardData['calendarStaffOptions'] = [];
            $dashboardData['bookingConsultants'] = [];
        }

        return view('crm.dashboard', $dashboardData);
    }

    /**
     * Minimal payload when restricted users have no dashboard widgets.
     *
     * @return array<string, mixed>
     */
    protected function emptyDashboardPayload(): array
    {
        return [
            'notesData' => [],
            'notes_current_page' => 1,
            'notes_last_page' => 1,
            'notes_per_page' => 10,
            'cases_requiring_attention_data' => [],
            'cases_current_page' => 1,
            'cases_last_page' => 1,
            'cases_per_page' => 10,
            'count_active_matter' => 0,
            'count_closed_matter' => 0,
            'count_note_deadline' => 0,
            'count_cases_requiring_attention_data' => 0,
            'dashboardAssignableStaff' => collect(),
        ];
    }

    /**
     * JSON summary for AJAX dashboard refresh (KPIs, lists, calendar stats).
     */
    public function summary(Request $request)
    {
        $user = Auth::guard('admin')->user() ?: Auth::user();
        $staff = $user instanceof Staff ? $user : null;
        $widgetFlags = $this->accessGate->dashboardWidgetFlags($staff);

        if ($widgetFlags['shell_only']) {
            return response()->json([
                'success' => true,
                'kpis' => [
                    'active_matters' => 0,
                    'closed_matters' => 0,
                    'note_deadline' => 0,
                    'recent_activity' => 0,
                ],
                'calendar_stats' => null,
                'widgets' => $widgetFlags,
            ]);
        }

        $perPage = max(1, min(50, (int) $request->input('per_page', config('crm.dashboard.list_per_page', 10))));
        $fresh = $request->boolean('fresh', true);

        $payload = $this->dashboardService->getRefreshSummary($user, $perPage, $fresh);

        if (! $widgetFlags['active_matters']) {
            $payload['kpis']['active_matters'] = 0;
        }
        if (! $widgetFlags['closed_matters']) {
            $payload['kpis']['closed_matters'] = 0;
        }
        if (! $widgetFlags['tasks']) {
            $payload['kpis']['note_deadline'] = 0;
            $payload['tasks'] = [
                'html' => '',
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => $perPage,
                'total' => 0,
            ];
        }
        if (! $widgetFlags['recent_activity']) {
            $payload['kpis']['recent_activity'] = 0;
            $payload['cases'] = [
                'html' => '',
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => $perPage,
                'total' => 0,
            ];
        }

        $payload['calendar_stats'] = ($staff
            && $widgetFlags['calendar']
            && $staff->canAccessPersonalCalendar())
            ? $this->personalCalendarFeed->statsForViewer($staff, $request)
            : null;
        $payload['widgets'] = $widgetFlags;

        return response()->json(array_merge(['success' => true], $payload));
    }

    /**
     * JSON feed for the staff personal calendar on the dashboard.
     */
    public function calendarEvents(Request $request)
    {
        $staff = Auth::guard('admin')->user();
        if (! $staff instanceof Staff) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        if (! $this->accessGate->canDashboardWidget($staff, 'calendar')) {
            return response()->json([
                'success' => false,
                'message' => 'Calendar widget is not enabled for your account.',
            ], 403);
        }

        if (! $staff->canAccessPersonalCalendar()) {
            return response()->json([
                'success' => false,
                'message' => 'Personal calendar access has not been granted. Ask a Super Admin to enable it on your staff profile.',
            ], 403);
        }

        $rows = $this->personalCalendarFeed->eventsForViewerRequest($staff, $request);
        $events = array_map(
            fn (array $row) => $this->personalCalendarFeed->toFullCalendarEvent($row),
            $rows
        );

        $includeStats = $request->boolean('include_stats', true);

        return response()->json([
            'success' => true,
            'data' => $events,
            'stats' => $includeStats ? $this->personalCalendarFeed->statsForViewer($staff, $request) : null,
            'staff_view' => $this->personalCalendarFeed->resolveCalendarView($staff, $request)['mode'],
            'booking_calendar_type' => $this->personalCalendarFeed->resolveRequestedCalendarType($request)
                ?? $this->personalCalendarFeed->ownedWebsiteCalendarType($staff),
        ]);
    }

    /**
     * Active appointment consultants for the shared booking appointment modal.
     *
     * @return list<array{id: int, name: string, calendar_type: ?string}>
     */
    public function bookingConsultantsForModal(): array
    {
        return AppointmentConsultant::query()
            ->active()
            ->orderBy('name')
            ->get(['id', 'name', 'calendar_type'])
            ->unique('id')
            ->values()
            ->map(fn (AppointmentConsultant $c) => [
                'id' => (int) $c->id,
                'name' => (string) $c->name,
                'calendar_type' => $c->calendar_type,
            ])
            ->all();
    }

    /**
     * Paginated My Tasks HTML for dashboard infinite scroll.
     */
    public function tasks(Request $request)
    {
        $user = Auth::guard('admin')->user() ?: Auth::user();
        if ($user instanceof Staff && ! $this->accessGate->canDashboardWidget($user, 'tasks')) {
            return response()->json([
                'success' => false,
                'message' => 'Tasks widget is not enabled for your account.',
            ], 403);
        }

        $page = max(1, (int) $request->input('page', 1));
        $perPage = max(1, min(50, (int) $request->input('per_page', 10)));

        $notesPage = $this->dashboardService->getNotesPage($user, $page, $perPage);

        $html = '';
        foreach ($notesPage['items'] as $note) {
            $html .= view('components.dashboard.task-item', ['note' => $note])->render();
        }

        return response()->json([
            'success' => true,
            'html' => $html,
            'current_page' => $notesPage['current_page'],
            'last_page' => $notesPage['last_page'],
            'per_page' => $notesPage['per_page'],
            'total' => $notesPage['total'],
            'has_more' => $notesPage['current_page'] < $notesPage['last_page'],
        ]);
    }

    /**
     * Paginated Recent Matter Activity HTML for dashboard infinite scroll.
     */
    public function casesRequiringAttention(Request $request)
    {
        $user = Auth::guard('admin')->user() ?: Auth::user();
        if ($user instanceof Staff && ! $this->accessGate->canDashboardWidget($user, 'recent_activity')) {
            return response()->json([
                'success' => false,
                'message' => 'Recent activity widget is not enabled for your account.',
            ], 403);
        }

        $page = max(1, (int) $request->input('page', 1));
        $perPage = max(1, min(50, (int) $request->input('per_page', 10)));

        $casesPage = $this->dashboardService->getCasesRequiringAttentionPage($user, $page, $perPage);

        $html = '';
        foreach ($casesPage['items'] as $case) {
            $html .= view('components.dashboard.case-item', ['case' => $case])->render();
        }

        return response()->json([
            'success' => true,
            'html' => $html,
            'current_page' => $casesPage['current_page'],
            'last_page' => $casesPage['last_page'],
            'per_page' => $casesPage['per_page'],
            'total' => $casesPage['total'],
            'has_more' => $casesPage['current_page'] < $casesPage['last_page'],
        ]);
    }

    /**
     * Get dashboard notifications
     */
    public function fetchNotifications(Request $request)
    {
        $notifications = $this->dashboardService->getNotifications();
        
        return response()->json([
            'unseen_notification' => $notifications['count']
        ]);
    }

    /**
     * Get office visit notifications
     */
    public function fetchOfficeVisitNotifications(Request $request)
    {
        $notifications = $this->dashboardService->getOfficeVisitNotifications();
        
        return response()->json([
            'notifications' => $notifications,
            'count' => count($notifications)
        ]);
    }

    /**
     * Mark notification as seen
     */
    public function markNotificationSeen(Request $request)
    {
        $result = $this->dashboardService->markNotificationAsSeen($request->notification_id);
        
        return response()->json($result);
    }

    /**
     * Extend note deadline
     */
    public function extendDeadlineDate(Request $request)
    {
        try {
            $user = Auth::guard('admin')->user() ?: Auth::user();
            if ($user instanceof Staff && ! $this->accessGate->canDashboardWidget($user, 'tasks')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tasks widget is not enabled for your account.',
                ], 403);
            }

            $this->validate($request, [
                'note_id' => 'required|integer',
                'unique_group_id' => 'nullable|string',
                'description' => 'required|string',
                'note_deadline' => 'required|date'
            ]);

            Log::info('Extend deadline request data:', $request->all());

            $result = $this->dashboardService->extendNoteDeadline($request->all(), $user);
            
            Log::info('Extend deadline result:', $result);

            if (isset($result['success']) && $result['success'] === false && str_contains(strtolower($result['message'] ?? ''), 'unauthorized')) {
                return response()->json($result, 403);
            }

            return response()->json($result);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validation error in extendDeadlineDate:', $e->errors());
            return response()->json([
                'success' => false,
                'message' => 'Validation failed: ' . implode(', ', Arr::flatten($e->errors()))
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error in extendDeadlineDate: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while extending the deadline'
            ], 500);
        }
    }

    /**
     * Update action completion status
     */
    public function completeTask(Request $request)
    {
        try {
            $user = Auth::guard('admin')->user() ?: Auth::user();
            if ($user instanceof Staff && ! $this->accessGate->canDashboardWidget($user, 'tasks')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tasks widget is not enabled for your account.',
                ], 403);
            }

            Log::info('Update action completed request data:', $request->all());
            
            $this->validate($request, [
                'id' => 'required|integer',
                'unique_group_id' => 'nullable|string',
                'completion_notes' => 'nullable|string|max:5000'
            ]);

            $result = $this->dashboardService->completeTask(
                $request->id,
                $request->unique_group_id ?? '',
                $request->completion_notes,
                $user
            );
            
            Log::info('Update action completed result:', $result);

            if (isset($result['success']) && $result['success'] === false && str_contains(strtolower($result['message'] ?? ''), 'unauthorized')) {
                return response()->json($result, 403);
            }

            return response()->json($result);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validation error in completeTask:', $e->errors());
            return response()->json([
                'success' => false,
                'message' => 'Validation failed: ' . implode(', ', Arr::flatten($e->errors()))
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error in completeTask: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while updating task completion'
            ], 500);
        }
    }

    /**
     * Check checkin status
     */
    public function checkCheckinStatus(Request $request)
    {
        try {
            $checkinLog = \App\Models\CheckinLog::where('id', $request->checkin_id)->first();
            
            if ($checkinLog) {
                return response()->json([
                    'success' => true,
                    'status' => $checkinLog->status
                ]);
            }

            return response()->json(['success' => false, 'message' => 'Checkin not found']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error checking checkin status']);
        }
    }

    /**
     * Update checkin status
     */
    public function updateCheckinStatus(Request $request)
    { 
        try {
            if (!$request->has('checkin_id') || !is_numeric($request->checkin_id)) {
                return response()->json(['success' => false, 'message' => 'Valid checkin ID is required'], 422);
            }

            $checkinLog = \App\Models\CheckinLog::where('id', (int) $request->checkin_id)->first();
            
            if (!$checkinLog) {
                return response()->json(['success' => false, 'message' => 'Checkin not found'], 404);
            }

            $actor = Auth::guard('admin')->user();
            if (!$actor) {
                return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
            }

            $isAssignee = ((int) ($checkinLog->user_id ?? 0)) === (int) $actor->id;
            $hasFrontDeskAccess = method_exists($actor, 'canAccessFrontDeskCheckIn') && $actor->canAccessFrontDeskCheckIn();
            $isSuperAdmin = method_exists($actor, 'hasEffectiveSuperAdminPrivileges') && $actor->hasEffectiveSuperAdminPrivileges();

            if (!$isAssignee && !$hasFrontDeskAccess && !$isSuperAdmin) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access to check-in record'], 403);
            }

            if ($checkinLog->client_id) {
                $this->ensureCrmRecordAccess((int) $checkinLog->client_id);
            }

            $allowedStatuses = [0, 1, 2, '0', '1', '2'];
            if (!$request->has('status') || !in_array($request->status, $allowedStatuses, false)) {
                return response()->json(['success' => false, 'message' => 'Invalid status value'], 422);
            }

            $checkinLog->status = (int) $request->status;
            
            if ($request->has('wait_type')) {
                $checkinLog->wait_type = (int) $request->wait_type;
            }
            
            $saved = $checkinLog->save();
            
            if ($saved) {
                return response()->json([
                    'success' => true,
                    'message' => 'Status updated successfully to ' . $checkinLog->status
                ]);
            } else {
                return response()->json(['success' => false, 'message' => 'Failed to save status update']);
            }
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error updating checkin status: ' . $e->getMessage()]);
        }
    }

    /**
     * Get in-person waiting count
     */
    public function fetchInPersonWaitingCount(Request $request)
    {
        $InPersonwaitingCount = \App\Models\CheckinLog::inPersonWaitingCountForViewer();

        return response()->json(['InPersonwaitingCount' => $InPersonwaitingCount]);
    }

    /**
     * Get total activity count
     */
    public function fetchTotalActivityCount(Request $request)
    {
        $viewer = Auth::user();
        $seeAll = $viewer instanceof Staff && $viewer->hasEffectiveSuperAdminPrivileges();
        if ($seeAll) {
            $assigneesCount = \App\Models\Note::where('type', 'client')
                ->whereNotNull('client_id')
                ->where('is_action', 1)
                ->where('status', 0)
                ->count();
        } else {
            $assigneesCount = \App\Models\Note::where('assigned_to', Auth::user()->id)
                ->where('type', 'client')
                ->where('is_action', 1)
                ->where('status', 0)
                ->count();
        }
        
        return response()->json(['assigneesCount' => $assigneesCount]);
    }
}
