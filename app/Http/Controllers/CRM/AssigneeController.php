<?php
namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

// WARNING: Appointment and AppointmentLog models have been removed - old appointment system deleted
// use App\Models\Appointment;
use App\Models\Note;
// use App\Models\AppointmentLog;
use App\Models\Notification;
use Carbon\Carbon;
use App\Models\Admin;
use App\Models\Staff;
use App\Models\ActivitiesLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\TaskTimelineService;
use App\Services\ClientMatterTaskSyncService;
use App\Services\DashboardService;
use App\Helpers\SortableHelper;
use Illuminate\Support\Facades\URL;

class AssigneeController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */

     public function __construct()
     {
         $this->middleware('auth:admin');
     }

    private function viewerSeesAllTasks(): bool
    {
        return app(DashboardService::class)->viewerSeesAllMattersAndTasks(Auth::user());
    }

    /**
     * SQL expression concatenating two nullable columns with a space (driver-aware).
     * MySQL/MariaDB use CONCAT(); pgsql/sqlite accept || for string concatenation.
     */
    private function sqlConcatWithSpace(string $leftColumn, string $rightColumn): string
    {
        return match (DB::connection()->getDriverName()) {
            'mysql', 'mariadb' => "CONCAT(COALESCE({$leftColumn}, ''), ' ', COALESCE({$rightColumn}, ''))",
            default => "COALESCE({$leftColumn}, '') || ' ' || COALESCE({$rightColumn}, '')",
        };
    }

    /**
     * Sort open tasks by due date: missing dates first when ascending,
     * then earliest → latest (later dates first when descending, missing last).
     */
    private function orderTasksByDueDate($query, string $direction = 'asc'): void
    {
        $direction = $direction === 'desc' ? 'desc' : 'asc';

        // MySQL historically allows zero dates; Postgres rejects '0000-00-00' literals.
        $missingDate = match (DB::connection()->getDriverName()) {
            'mysql', 'mariadb' => "(notes.action_date IS NULL OR notes.action_date = '0000-00-00' OR notes.action_date = '0000-00-00 00:00:00')",
            default => '(notes.action_date IS NULL)',
        };

        if ($direction === 'asc') {
            // No due date on top, then early → late
            $query->orderByRaw("{$missingDate} DESC")
                ->orderBy('notes.action_date', 'asc')
                ->orderBy('notes.created_at', 'desc');
        } else {
            // Late → early, no due date at bottom
            $query->orderByRaw("{$missingDate} ASC")
                ->orderBy('notes.action_date', 'desc')
                ->orderBy('notes.created_at', 'desc');
        }
    }

    /**
     * @return list<AllowedSort>
     */
    private function noteListAllowedSorts(): array
    {
        return [
            AllowedSort::callback('first_name', function ($query, bool $descending): void {
                $joinAlias = 'sort_assignee';
                $alreadyJoined = collect($query->getQuery()->joins ?? [])
                    ->contains(function ($join) use ($joinAlias) {
                        return $join->table === 'staff as '.$joinAlias || $join->table === $joinAlias;
                    });
                if (! $alreadyJoined) {
                    $query->leftJoin('staff as '.$joinAlias, $joinAlias.'.id', '=', 'notes.assigned_to')
                        ->select('notes.*');
                }
                $query->orderBy($joinAlias.'.first_name', $descending ? 'desc' : 'asc');
            }),
            AllowedSort::field('action_date', 'notes.action_date'),
            AllowedSort::field('task_group', 'notes.task_group'),
            AllowedSort::field('created_at', 'notes.created_at'),
        ];
    }

    /**
     * Apply Spatie sorts used by assignee Blade @sortablelink headers.
     */
    private function sortNotesList($query, string $defaultSort = '-created_at')
    {
        $allowedNames = ['first_name', 'action_date', 'task_group', 'created_at'];

        return QueryBuilder::for($query, SortableHelper::requestWithAllowedSortsOnly($allowedNames))
            ->allowedSorts(...$this->noteListAllowedSorts())
            ->defaultSort($defaultSort);
    }

    //All action lists except completed = Closed
    public function index(Request $request)
    {
        $query = Note::query()
            ->with(['noteStaff','noteClient.company','lead.service','assigned_staff'])
            ->where('type','client')
            ->where('is_action', 1)
            ->where('status','<>','1');

        if ($this->viewerSeesAllTasks()) {
            $query->whereNotNull('client_id');
        } else {
            $query->where('assigned_to', Auth::user()->id);
        }

        $assignees = $this->sortNotesList($query)->paginate(20);

        return view('crm.assignee.index',compact('assignees'))
         ->with('i', (request()->input('page', 1) - 1) * 20);
    }

    //All completed action lists
    public function completed(Request $request)
    {
        $query = Note::with(['noteStaff','noteClient.company','lead.service','assigned_staff'])
            ->where('type','client')
            ->where('is_action', 1)
            ->where('status','1');

        if ($this->viewerSeesAllTasks()) {
            $query->whereNotNull('client_id');
        } else {
            $query->where('assigned_to', Auth::user()->id);
        }

        $assignees = $this->sortNotesList($query)->paginate(20);

        return view('crm.assignee.completed',compact('assignees'))
         ->with('i', (request()->input('page', 1) - 1) * 20);
    }

    //Update action to be complete (shared logic with dashboard.tasks.complete)
    public function completeTask(Request $request)
    {
        $user = Auth::guard('admin')->user() ?: Auth::user();
        $data = $request->all();
        $noteId = (int) ($data['id'] ?? 0);
        $uniqueGroupId = trim((string) ($data['unique_group_id'] ?? ''));
        $completionNotes = isset($data['completion_notes']) ? (string) $data['completion_notes'] : null;

        if ($noteId <= 0) {
            return response()->json([
                'success' => false,
                'status' => false,
                'message' => 'Task not found',
            ]);
        }

        $result = app(DashboardService::class)->completeTask(
            $noteId,
            $uniqueGroupId,
            $completionNotes,
            $user
        );

        $ok = (bool) ($result['success'] ?? false);
        $message = (string) ($result['message'] ?? ($ok ? 'Task completed successfully' : 'Please try again'));
        $statusCode = 200;
        if (!$ok && str_contains(strtolower($message), 'unauthorized')) {
            $statusCode = 403;
        }

        // Include both `success` (dashboard) and `status` (legacy assignee UIs).
        return response()->json([
            'success' => $ok,
            'status' => $ok,
            'message' => $message,
        ], $statusCode);
    }

    //Update action to be not complete
    public function reopenTask(Request $request)
    {
        $user = Auth::guard('admin')->user() ?: Auth::user();
        $data = $request->all();
        $noteId = $data['id'] ?? 0;
        $uniqueGroupId = trim((string)($data['unique_group_id'] ?? ''));

        $noteData = Note::find($noteId);
        if (!$noteData) {
            return response()->json(['status' => false, 'message' => 'Task not found']);
        }

        if ($user && !app(\App\Services\DashboardService::class)->viewerSeesAllMattersAndTasks($user)) {
            $uid = (int) $user->id;
            $notesToCheck = collect([$noteData]);
            if ($uniqueGroupId !== '') {
                $groupNotes = Note::where('unique_group_id', $uniqueGroupId)
                    ->where('unique_group_id', '!=', '')
                    ->whereNotNull('unique_group_id')
                    ->get();
                if ($groupNotes->isNotEmpty()) {
                    $notesToCheck = $groupNotes;
                }
            }

            foreach ($notesToCheck as $checkNote) {
                $isAssigneeOrOwner = ((int)$checkNote->assigned_to === $uid || (int)$checkNote->user_id === $uid);
                if (!$isAssigneeOrOwner) {
                    return response()->json(['status' => false, 'message' => 'Unauthorized task modification.'], 403);
                }
                if ($checkNote->client_id && !\App\Support\StaffClientVisibility::canAccessClientOrLead((int)$checkNote->client_id, $user)) {
                    return response()->json(['status' => false, 'message' => 'Unauthorized task modification.'], 403);
                }
            }
        }

        $updated = 0;
        if ($uniqueGroupId !== '') {
            $updated = Note::where('unique_group_id', $uniqueGroupId)
                ->where('unique_group_id', '!=', '')
                ->whereNotNull('assigned_to')
                ->whereNotNull('unique_group_id')
                ->update(['status' => '0']);
        }
        if ($updated === 0) {
            $updated = Note::where('id', $noteId)->update(['status' => '0']);
        }

        if($updated){
            $noteRow = Note::where('id', $data['id'] ?? 0)->first();
            if ($noteRow) {
                app(ClientMatterTaskSyncService::class)->syncCompletionFromNote($noteRow, false);
            }
            $dashboardService = app(\App\Services\DashboardService::class);
            if ($user) {
                $dashboardService->forgetPendingOpenTaskCountCache($user);
            }
            if ($noteRow && ! empty($noteRow->assigned_to)) {
                $assigneeStaff = Staff::find($noteRow->assigned_to);
                if ($assigneeStaff) {
                    $dashboardService->forgetPendingOpenTaskCountCache($assigneeStaff);
                }
            }
            $response['status'] 	= 	true;
            $response['message']	=	'Task updated successfully';
        } else {
            $response['status'] 	= 	false;
            $response['message']	=	'Please try again';
        }
        return response()->json($response);
    }

     //All assigned by me action list which r incomplete
     public function assigned_by_me(Request $request)
     {
        $listStatus = $request->input('status', 'incomplete') === 'completed'
            ? 'completed'
            : 'incomplete';

        $query = Note::with(['noteStaff', 'noteClient.company', 'assigned_staff', 'clientMatter'])
            ->where('type', 'client')
            ->where('is_action', 1);

        if ($listStatus === 'completed') {
            $query->where('status', 1);
        } else {
            $query->where('status', '<>', 1);
        }

        if ($this->viewerSeesAllTasks()) {
            $query->whereNotNull('client_id');
        } else {
            $query->where('user_id', Auth::user()->id);
        }

        $perPage = 20;
        $assignees = $this->sortNotesList($query)->paginate($perPage)->appends(
            $request->except('page', 'infinite', 'spa')
        );
        $i = ((int) $request->input('page', 1) - 1) * $perPage;

        $rowViewData = [
            'assignees' => $assignees,
            'assignees_notCompleted' => $assignees, // legacy alias for rows partial
            'i' => $i,
            'listStatus' => $listStatus,
        ];

        // Infinite scroll append — rows only
        if ($request->boolean('infinite') || ($request->ajax() && ! $request->boolean('spa'))) {
            $html = view('crm.assignee.partials.assigned_by_me_rows', array_merge($rowViewData, [
                'appendOnly' => true,
            ]))->render();

            return response()->json([
                'html' => $html,
                'status' => $listStatus,
                'current_page' => $assignees->currentPage(),
                'last_page' => $assignees->lastPage(),
                'per_page' => $assignees->perPage(),
                'from' => $assignees->firstItem() ?: 0,
                'to' => $assignees->lastItem() ?: 0,
                'total' => $assignees->total(),
                'has_more' => $assignees->hasMorePages(),
                'next_page' => $assignees->hasMorePages()
                    ? ($assignees->currentPage() + 1)
                    : null,
            ]);
        }

        // SPA tab / sort — replace table body region
        if ($request->boolean('spa')) {
            $html = view('crm.assignee.partials.assigned_by_me_table', array_merge($rowViewData, [
                'appendOnly' => false,
            ]))->render();

            $pushQuery = $request->except('spa', 'infinite', 'page');
            if ($listStatus === 'incomplete') {
                unset($pushQuery['status']);
            } else {
                $pushQuery['status'] = 'completed';
            }
            $pushUrl = route('assignee.assigned_by_me', $pushQuery);

            return response()->json([
                'html' => $html,
                'status' => $listStatus,
                'current_page' => $assignees->currentPage(),
                'last_page' => $assignees->lastPage(),
                'per_page' => $assignees->perPage(),
                'from' => $assignees->firstItem() ?: 0,
                'to' => $assignees->lastItem() ?: 0,
                'total' => $assignees->total(),
                'has_more' => $assignees->hasMorePages(),
                'loaded' => $assignees->count(),
                'url' => $pushUrl,
            ]);
        }

        return view('crm.assignee.assign_by_me', [
            'assignees' => $assignees,
            'assignees_notCompleted' => $assignees,
            'listStatus' => $listStatus,
            'i' => $i,
        ]);
     }

    //All assigned to me action list
    public function assigned_to_me(Request $request)
    {
        $base = Note::with(['noteStaff','noteClient.company','lead.service','assigned_staff','clientMatter'])
            ->where('type','client')
            ->where('is_action', 1)
            ->where('assigned_to', Auth::user()->id);

        if ($this->viewerSeesAllTasks()) {
            $base->whereNotNull('client_id');
        }

        $assignees_notCompleted = $this->sortNotesList((clone $base)->where('status','<>','1'))->paginate(20);
        $assignees_completed = $this->sortNotesList((clone $base)->where('status','1'))->paginate(20);

        return view('crm.assignee.assign_to_me',compact('assignees_notCompleted','assignees_completed'))
         ->with('i', (request()->input('page', 1) - 1) * 20);
    }

    public function tasksCompleted(Request $request)
    {
        $req_data = $request->all();
        if (isset($req_data['group_type']) && $req_data['group_type'] != "") {
            $task_group = $req_data['group_type'];
        } else {
            $task_group = 'All';
        }
        $staff = Auth::user();

        $assignees_completed = \App\Models\Note::with([
                'noteStaff',
                'noteClient.company',
                'assigned_staff',
                'clientMatter'
            ])
            ->where('status', 1)
            ->where('type', 'client')
            ->whereNotNull('client_id')
            ->where('is_action', 1)
            ->when(! ($staff instanceof Staff && $staff->hasEffectiveSuperAdminPrivileges()), function ($query) use ($staff) {
                return $query->where('assigned_to', $staff->id);
            })
            ->when($task_group !== 'All', function ($query) use ($task_group) {
                if ($task_group === 'Personal Task' || $task_group === 'Personal Action') {
                    return $query->whereIn('task_group', ['Personal Task', 'Personal Action']);
                }
                if ($task_group === 'Follow Up' || $task_group === 'Follow up') {
                    return $query->whereIn('task_group', ['Follow Up', 'Follow up']);
                }
                return $query->where('task_group', 'like', $task_group);
            });

        $perPage = 20;
        $assignees_completed = $this->sortNotesList($assignees_completed, '-action_date')
            ->paginate($perPage)
            ->appends($request->except('page', 'infinite', 'spa'));
        $i = ((int) $request->input('page', 1) - 1) * $perPage;

        $taskGroupCounts = $this->getCompletedTaskGroupCounts($staff);

        $viewData = [
            'assignees_completed' => $assignees_completed,
            'task_group' => $task_group,
            'taskGroupCounts' => $taskGroupCounts,
            'i' => $i,
        ];

        if ($request->boolean('infinite') || ($request->ajax() && ! $request->boolean('spa'))) {
            $html = view('crm.assignee.tasks.partials.completed_rows', array_merge($viewData, [
                'appendOnly' => true,
            ]))->render();

            return response()->json([
                'html' => $html,
                'group_type' => $task_group,
                'current_page' => $assignees_completed->currentPage(),
                'last_page' => $assignees_completed->lastPage(),
                'per_page' => $assignees_completed->perPage(),
                'from' => $assignees_completed->firstItem() ?: 0,
                'to' => $assignees_completed->lastItem() ?: 0,
                'total' => $assignees_completed->total(),
                'has_more' => $assignees_completed->hasMorePages(),
                'next_page' => $assignees_completed->hasMorePages()
                    ? ($assignees_completed->currentPage() + 1)
                    : null,
                'counts' => $taskGroupCounts,
            ]);
        }

        if ($request->boolean('spa')) {
            $html = view('crm.assignee.tasks.partials.completed_spa_body', array_merge($viewData, [
                'appendOnly' => false,
            ]))->render();

            $pushQuery = $request->except('spa', 'infinite', 'page');
            if (($pushQuery['group_type'] ?? 'All') === 'All') {
                unset($pushQuery['group_type']);
            }
            $pushUrl = route('assignee.tasks.completed', $pushQuery);

            return response()->json([
                'html' => $html,
                'group_type' => $task_group,
                'current_page' => $assignees_completed->currentPage(),
                'last_page' => $assignees_completed->lastPage(),
                'per_page' => $assignees_completed->perPage(),
                'from' => $assignees_completed->firstItem() ?: 0,
                'to' => $assignees_completed->lastItem() ?: 0,
                'total' => $assignees_completed->total(),
                'has_more' => $assignees_completed->hasMorePages(),
                'loaded' => $assignees_completed->count(),
                'url' => $pushUrl,
                'counts' => $taskGroupCounts,
            ]);
        }

        return view('crm.assignee.tasks.completed', $viewData);
    }

    /**
     * Get completed action counts grouped by task_group (field name kept for database compatibility)
     * Uses a single query with GROUP BY for better performance
     * 
     * @param \App\Models\Admin $staff
     * @return array
     */
    private function getCompletedTaskGroupCounts($staff)
    {
        $query = \App\Models\Note::where('status', 1)
            ->where('type', 'client')
            ->where('is_action', 1)
            ->when(! ($staff instanceof Staff && $staff->hasEffectiveSuperAdminPrivileges()), function ($query) use ($staff) {
                return $query->where('assigned_to', $staff->id);
            });

        // For admin role, add client_id filter
        if ($staff instanceof Staff && $staff->hasEffectiveSuperAdminPrivileges()) {
            $query->whereNotNull('client_id');
        }

        // Get counts grouped by task_group
        $groupedCounts = $query->selectRaw('task_group, COUNT(*) as count')
            ->groupBy('task_group')
            ->pluck('count', 'task_group')
            ->toArray();

        // Initialize all action groups with default count of 0
        $counts = [
            'All' => array_sum($groupedCounts),
            'Call' => $groupedCounts['Call'] ?? 0,
            'Checklist' => $groupedCounts['Checklist'] ?? 0,
            'Review' => $groupedCounts['Review'] ?? 0,
            'Query' => $groupedCounts['Query'] ?? 0,
            'Urgent' => $groupedCounts['Urgent'] ?? 0,
            'Personal Task' => ($groupedCounts['Personal Task'] ?? 0) + ($groupedCounts['Personal Action'] ?? 0),
            'Follow Up' => ($groupedCounts['Follow Up'] ?? 0) + ($groupedCounts['Follow up'] ?? 0),
        ];

        return $counts;
    }

    public function tasks(Request $request)
    {
        $filter = strtolower(trim((string) $request->input('filter', 'all')));
        if ($filter === '') {
            $filter = 'all';
        }

        $search = trim((string) $request->input('q', ''));
        if ($search === '' && $request->filled('note_id')) {
            $search = trim((string) $request->input('note_id'));
        }
        // Legacy DataTables search payload (compat for /tasks/list)
        if ($search === '' && is_array($request->input('search'))) {
            $search = trim((string) ($request->input('search.value') ?? ''));
        } elseif ($search === '' && is_string($request->input('search'))) {
            $search = trim((string) $request->input('search'));
        }

        $query = Note::query()
            ->select([
                'notes.id',
                'notes.user_id',
                'notes.client_id',
                'notes.matter_id',
                'notes.assigned_to',
                'notes.status',
                'notes.type',
                'notes.is_action',
                'notes.action_date',
                'notes.task_group',
                'notes.description',
                'notes.unique_group_id',
                'notes.created_at',
            ])
            ->with(['noteStaff', 'noteClient.company', 'assigned_staff', 'clientMatter'])
            ->where('notes.status', '<>', '1')
            ->where('notes.type', 'client')
            ->where('notes.is_action', 1);

        if (Auth::check() && ! $this->viewerSeesAllTasks()) {
            $query->where('notes.assigned_to', Auth::user()->id);
        }

        $this->applyOpenTasksFilter($query, $filter);
        $this->applyOpenTasksSearch($query, $search);
        $this->applyOpenTasksSort($query, $request);

        $perPage = 20;
        $assignees = $query->paginate($perPage)->appends(
            $request->except('page', 'infinite', 'spa')
        );
        $i = ((int) $request->input('page', 1) - 1) * $perPage;
        $taskGroupCounts = $this->getOpenTaskGroupCounts();

        $viewData = [
            'assignees' => $assignees,
            'filter' => $filter,
            'search' => $search,
            'taskGroupCounts' => $taskGroupCounts,
            'i' => $i,
        ];

        if ($request->boolean('infinite') || ($request->ajax() && ! $request->boolean('spa'))) {
            $html = view('crm.assignee.tasks.partials.open_rows', array_merge($viewData, [
                'appendOnly' => true,
            ]))->render();

            return response()->json([
                'html' => $html,
                'filter' => $filter,
                'q' => $search,
                'current_page' => $assignees->currentPage(),
                'last_page' => $assignees->lastPage(),
                'per_page' => $assignees->perPage(),
                'from' => $assignees->firstItem() ?: 0,
                'to' => $assignees->lastItem() ?: 0,
                'total' => $assignees->total(),
                'has_more' => $assignees->hasMorePages(),
                'next_page' => $assignees->hasMorePages()
                    ? ($assignees->currentPage() + 1)
                    : null,
                'counts' => $taskGroupCounts,
            ]);
        }

        if ($request->boolean('spa')) {
            $html = view('crm.assignee.tasks.partials.open_spa_body', array_merge($viewData, [
                'appendOnly' => false,
            ]))->render();

            $pushQuery = $request->except('spa', 'infinite', 'page');
            if (($pushQuery['filter'] ?? 'all') === 'all') {
                unset($pushQuery['filter']);
            }
            if (($pushQuery['q'] ?? '') === '') {
                unset($pushQuery['q']);
            }
            $pushUrl = route('assignee.tasks', $pushQuery);

            return response()->json([
                'html' => $html,
                'filter' => $filter,
                'q' => $search,
                'current_page' => $assignees->currentPage(),
                'last_page' => $assignees->lastPage(),
                'per_page' => $assignees->perPage(),
                'from' => $assignees->firstItem() ?: 0,
                'to' => $assignees->lastItem() ?: 0,
                'total' => $assignees->total(),
                'has_more' => $assignees->hasMorePages(),
                'loaded' => $assignees->count(),
                'url' => $pushUrl,
                'counts' => $taskGroupCounts,
            ]);
        }

        return view('crm.assignee.tasks', $viewData);
    }

    /**
     * Compat endpoint for legacy /tasks/list bookmarks and redirects.
     * Returns the same Spatie paginator HTML JSON as GET /tasks?infinite=1 (not Yajra).
     */
    public function getTasks(Request $request)
    {
        $request->merge(['infinite' => 1]);

        return $this->tasks($request);
    }

    public function getTaskCounts(Request $request)
    {
        return response()->json($this->getOpenTaskGroupCounts());
    }

    /**
     * Open-task badge counts by filter tab.
     *
     * @return array<string, int>
     */
    private function getOpenTaskGroupCounts(): array
    {
        $counts = [
            'all' => 0,
            'call' => 0,
            'checklist' => 0,
            'review' => 0,
            'query' => 0,
            'urgent' => 0,
            'personal_action' => 0,
            'follow_up' => 0,
        ];

        $query = Note::where('status', '<>', '1')
            ->where('type', 'client')
            ->where('is_action', 1);

        if (! $this->viewerSeesAllTasks()) {
            $query->where('assigned_to', Auth::user()->id);
        }

        $counts['all'] = (clone $query)->count();
        $counts['call'] = (clone $query)->where('task_group', 'Call')->count();
        $counts['checklist'] = (clone $query)->where('task_group', 'Checklist')->count();
        $counts['review'] = (clone $query)->where('task_group', 'Review')->count();
        $counts['query'] = (clone $query)->where('task_group', 'Query')->count();
        $counts['urgent'] = (clone $query)->where('task_group', 'Urgent')->count();
        $counts['personal_action'] = (clone $query)->whereIn('task_group', ['Personal Task', 'Personal Action'])->count();
        $counts['follow_up'] = (clone $query)->whereIn('task_group', ['Follow Up', 'Follow up'])->count();

        return $counts;
    }

    private function applyOpenTasksFilter($query, string $filter): void
    {
        if ($filter === '' || $filter === 'all') {
            return;
        }

        if ($filter === 'assigned_by_me') {
            $query->where('notes.user_id', Auth::user()->id);

            return;
        }

        if ($filter === 'completed') {
            $query->where('notes.status', '1');

            return;
        }

        if ($filter === 'personal_action') {
            $query->whereIn('notes.task_group', ['Personal Task', 'Personal Action']);

            return;
        }

        if ($filter === 'follow_up') {
            $query->whereIn('notes.task_group', ['Follow Up', 'Follow up']);

            return;
        }

        $query->where('notes.task_group', ucfirst($filter));
    }

    private function applyOpenTasksSearch($query, string $keyword): void
    {
        $keyword = trim($keyword);
        if ($keyword === '') {
            return;
        }

        $keywordLower = mb_strtolower($keyword, 'UTF-8');
        $nameConcat = $this->sqlConcatWithSpace('first_name', 'last_name');

        $query->where(function ($outer) use ($keyword, $keywordLower, $nameConcat) {
            if (ctype_digit($keyword)) {
                $outer->orWhere('notes.id', (int) $keyword);
            }

            $outer->orWhereRaw('LOWER(notes.description) LIKE ?', ['%'.$keywordLower.'%'])
                ->orWhereRaw('LOWER(COALESCE(notes.task_group, \'\')) LIKE ?', ['%'.$keywordLower.'%'])
                ->orWhereHas('noteStaff', function ($q) use ($keywordLower, $nameConcat) {
                    $q->where(function ($subQ) use ($keywordLower, $nameConcat) {
                        $subQ->whereRaw("LOWER({$nameConcat}) LIKE ?", ["%{$keywordLower}%"])
                            ->orWhereRaw('LOWER(first_name) LIKE ?', ["%{$keywordLower}%"])
                            ->orWhereRaw('LOWER(last_name) LIKE ?', ["%{$keywordLower}%"]);
                    });
                })
                ->orWhereHas('assigned_staff', function ($q) use ($keywordLower, $nameConcat) {
                    $q->where(function ($subQ) use ($keywordLower, $nameConcat) {
                        $subQ->whereRaw("LOWER({$nameConcat}) LIKE ?", ["%{$keywordLower}%"])
                            ->orWhereRaw('LOWER(first_name) LIKE ?', ["%{$keywordLower}%"])
                            ->orWhereRaw('LOWER(last_name) LIKE ?', ["%{$keywordLower}%"]);
                    });
                })
                ->orWhereHas('noteClient', function ($q) use ($keywordLower) {
                    $q->whereRaw('LOWER(client_id) LIKE ?', ['%'.$keywordLower.'%'])
                        ->orWhereRaw('LOWER(first_name) LIKE ?', ['%'.$keywordLower.'%'])
                        ->orWhereRaw('LOWER(last_name) LIKE ?', ['%'.$keywordLower.'%'])
                        ->orWhereHas('company', function ($cq) use ($keywordLower) {
                            $cq->whereRaw('LOWER(company_name) LIKE ?', ['%'.$keywordLower.'%']);
                        });
                });
        });
    }

    private function applyOpenTasksSort($query, Request $request): void
    {
        $sort = (string) $request->input('sort', '');

        if ($sort === 'task_group' || $sort === '-task_group') {
            $query->orderBy('notes.task_group', str_starts_with($sort, '-') ? 'desc' : 'asc');

            return;
        }

        if ($sort === 'action_date' || $sort === '-action_date') {
            $this->orderTasksByDueDate($query, str_starts_with($sort, '-') ? 'desc' : 'asc');

            return;
        }

        // Default (and assign_date alias): missing due dates first, then earliest → latest
        $this->orderTasksByDueDate($query, 'asc');
    }

    /**
     * Helper to verify staff authorization to delete or manage a task note.
     */
    protected function authorizeNoteManagement(Note $appointment): void
    {
        $actor = Auth::guard('admin')->user();
        if (!$actor) {
            abort(401, 'Unauthenticated');
        }

        $userId = (int) $actor->id;
        $isCreator = ((int) ($appointment->user_id ?? 0)) === $userId;
        $isAssignee = ((int) ($appointment->assigned_to ?? 0)) === $userId;
        $isSuperAdmin = method_exists($actor, 'hasEffectiveSuperAdminPrivileges') && $actor->hasEffectiveSuperAdminPrivileges();

        if (!$isCreator && !$isAssignee && !$isSuperAdmin) {
            abort(403, 'Unauthorized access to task.');
        }

        if ($appointment->client_id) {
            $this->ensureCrmRecordAccess((int) $appointment->client_id);
        }
    }


    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @param  Note  $Note
     * @return \Illuminate\Http\Response
     */
    public function destroy( $id,Note $Note)
    {   // dd($id);
        $appointment = Note::findOrFail($id);
        $this->authorizeNoteManagement($appointment);

        $appointment->is_action = 0;
        $appointment->save();

        return redirect()->route('assignee.index')
        ->with('success','Assingee deleted successfully');
    }

    public function destroy_by_me( $id,Note $Note)
    {
        $appointment = Note::findOrFail($id);
        $this->authorizeNoteManagement($appointment);

        $appointment->is_action = 0;
        if( $appointment->save() ){
            $objs = new ActivitiesLog;
            $objs->client_id = $appointment->client_id;
            $objs->created_by = Auth::user()->id;

            $assign_user = \App\Models\Staff::find($appointment->assigned_to);
            if($assign_user){
                $assign_full_name = $assign_user->first_name." ".$assign_user->last_name;
                $objs->subject = 'deleted task for '.@$assign_full_name;
            } else {
                $objs->subject = 'deleted task ';
            }

            $objs->description = '<p>'.$appointment->description.'</p>';
            if(Auth::user()->id != @$appointment->assigned_to){
                $objs->use_for = @$appointment->assigned_to;
            } else {
                $objs->use_for = null;
            }
            $objs->followup_date = @$appointment->action_date;
            $objs->task_group = @$appointment->task_group;
            $objs->task_status = 0;
            $objs->pin = 0;
            $objs->activity_type = 'activity';
            $objs->save();
            return redirect()->route('assignee.assigned_by_me')->with('success','Activity deleted successfully');
        }
    }

    public function destroy_to_me( $id,Note $Note)
    {
        $appointment = Note::findOrFail($id);
        $this->authorizeNoteManagement($appointment);

        $appointment->is_action = 0;
        $appointment->save();
        return redirect()->route('assignee.assigned_to_me')->with('success','Assingee deleted successfully');
    }

    //incomplete activity remove
    public function destroy_activity($id,Note $Note)
    {
        $appointment = Note::findOrFail($id);
        $this->authorizeNoteManagement($appointment);

        $appointment->is_action = 0;
        if( $appointment->save() ){
            $objs = new ActivitiesLog;
            $objs->client_id = $appointment->client_id;
            $objs->created_by = Auth::user()->id;

            $assign_user = \App\Models\Staff::find($appointment->assigned_to);
            if($assign_user){
                $assign_full_name = $assign_user->first_name." ".$assign_user->last_name;
                $objs->subject = 'deleted task for '.@$assign_full_name;
            } else {
                $objs->subject = 'deleted task ';
            }

            $objs->description = '<p>'.$appointment->description.'</p>';
            if(Auth::user()->id != @$appointment->assigned_to){
                $objs->use_for = @$appointment->assigned_to;
            } else {
                $objs->use_for = null;
            }
            $objs->followup_date = @$appointment->action_date;
            $objs->task_group = @$appointment->task_group;
            $objs->task_status = 0;
            $objs->pin = 0;
            $objs->activity_type = 'activity';
            $objs->save();
            echo json_encode(array('success' => true, 'message' => 'Activity deleted successfully'));
            exit;
        }
    }

    //complete activity remove
    public function destroy_complete_activity( $id,Note $Note)
    {
        $appointment = Note::findOrFail($id);
        $this->authorizeNoteManagement($appointment);

        $appointment->is_action = 0;
        if( $appointment->save() ){
            $objs = new ActivitiesLog;
            $objs->client_id = $appointment->client_id;
            $objs->created_by = Auth::user()->id;

            $assign_user = \App\Models\Staff::find($appointment->assigned_to);
            if($assign_user){
                $assign_full_name = $assign_user->first_name." ".$assign_user->last_name;
                $objs->subject = 'deleted completed task for '.@$assign_full_name;
            } else {
                $objs->subject = 'deleted completed task ';
            }

            $objs->description = '<p>'.$appointment->description.'</p>';
            if(Auth::user()->id != @$appointment->assigned_to){
                $objs->use_for = @$appointment->assigned_to;
            } else {
                $objs->use_for = null;
            }
            $objs->followup_date = @$appointment->action_date;
            $objs->task_group = @$appointment->task_group;
            $objs->task_status = 0;
            $objs->pin = 0;
            $objs->activity_type = 'activity';
            $objs->save();

            if (request()->ajax() || request()->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Task deleted successfully',
                ]);
            }

            return redirect()->route('assignee.tasks.completed')->with('success','Task deleted successfully');
        }
    }


    //Get All assignee list dropdown
    public function get_assignee_list(Request $request){
        $assignedto = $request->assignedto;

        $content1 = array();
        foreach(\App\Models\Staff::where('status',1)->orderby('first_name','ASC')->get() as $admin)
        {
            $branchname = \App\Models\Branch::where('id',$admin->office_id)->first();
            $option_value =  $admin->first_name.' '.$admin->last_name.' ('.@$branchname->office_name.')';

            if($admin->id == $assignedto){
                $content1[] = '<option value="'.$admin->id.'" selected>'.$option_value.'</option>';
            } else {
                $content1[] = '<option value="'.$admin->id.'">'.$option_value.'</option>';
            }
        }
        $response['status'] 	= 	true;
        $response['message']	=	$content1;
       echo json_encode($response);
    }

    // Helper function to get assignee name
    protected function getAssigneeName($assigneeId)
    {
        $staff = \App\Models\Staff::find($assigneeId);
        return $staff ? $staff->first_name . ' ' . $staff->last_name : 'Unknown Assignee';
    }

    /**
     * Update an action (Note) based on the provided data.
     * This function marks the current action as complete and creates a new action with the provided information.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateTask(Request $request)
    {
        // Validate the incoming request data
        $validated = $request->validate([
            'id' => 'required|exists:notes,id', // Ensure the action ID exists in the notes table
            'client_id' => 'nullable|string', // Client ID is optional for Personal Tasks
            'assigned_to' => 'required|exists:staff,id',
            'description' => 'required|string',
            'task_group' => 'required|string|in:Call,Checklist,Review,Query,Urgent,Personal Task,Follow Up,Follow up',
        ]);

        try {
            // Normalize Follow up casing to the canonical stored value.
            if (strcasecmp($validated['task_group'], 'Follow up') === 0) {
                $validated['task_group'] = 'Follow Up';
            }

            // Log the incoming assigned_to value for debugging
            Log::info('Updating action with assigned_to: ' . $validated['assigned_to']);

            // Decode client_id if it was encoded and not empty
            $clientId = null;
            if (!empty($validated['client_id'])) {
                $clientId = convert_uudecode(base64_decode($validated['client_id']));
            }

            // Find the current action (Note) by ID
            $currentAction = Note::findOrFail($validated['id']);

            // Get assignee information for activity logs
            $admin_data_old = Staff::where('id', $currentAction->assigned_to)->first();
            $assignee_name_old = $admin_data_old ? $admin_data_old->first_name . " " . $admin_data_old->last_name : 'N/A';

            // Step 1: Mark the current action as complete
            $currentAction->update(['status' => '1']);
            app(ClientMatterTaskSyncService::class)->syncCompletionFromNote($currentAction, true);

            // Step 2: Activity Feed log for completed action
            if ($currentAction->client_id) {
                $completionLog = new ActivitiesLog;
                $completionLog->client_id = $currentAction->client_id;
                $completionLog->created_by = Auth::user()->id;
                $completionLog->subject = 'Task completed for ' . $assignee_name_old;
                $completionLog->description = '<p>' . $currentAction->description . '</p>';
                if (Auth::user()->id != $currentAction->assigned_to) {
                    $completionLog->use_for = $currentAction->assigned_to;
                } else {
                    $completionLog->use_for = null;
                }
                $completionLog->followup_date = $currentAction->updated_at;
                $completionLog->task_group = $currentAction->task_group;
                $completionLog->task_status = 1; // Marked as completed
                $completionLog->pin = 0;
                $completionLog->activity_type = 'activity';
                $completionLog->save();
            }

            $admin_data = Staff::where('id', $validated['assigned_to'])->first();
            $assignee_name = $admin_data ? $admin_data->first_name . " " . $admin_data->last_name : 'N/A';

            // Use the original action's action_date or today's date if not available
            $followupDate = $currentAction->action_date ?: date('Y-m-d');

            // Step 3: Create a new action with the provided information
            $newAction = new Note;
            $newAction->user_id = Auth::user()->id;
            $newAction->client_id = $clientId;
            $newAction->matter_id = $currentAction->matter_id;
            $newAction->assigned_to = $validated['assigned_to'];
            $newAction->description = $validated['description'];
            $newAction->action_date = $followupDate;
            $newAction->task_group = $validated['task_group'];
            $newAction->type = 'client';
            $newAction->is_action = 1;
            $newAction->status = '0'; // New action is incomplete
            $newAction->pin = 0; // Set pin to 0 (required field)
            $actionUniqueId = 'group_' . uniqid('', true);
            $newAction->unique_group_id = $actionUniqueId; // Generate unique group ID for the new action
            $newAction->save();

            if ($clientId) {
                app(ClientMatterTaskSyncService::class)->mirrorTaskNoteToClientTask($newAction);
            }

            // Step 4: Activity Feed log for the new action
            if ($clientId) {
                app(TaskTimelineService::class)->logTaskNoteCreated($newAction, '', $assignee_name);
            }

            return response()->json([
                'success' => true,
                'message' => 'Task completed and new task created successfully.'
            ], 200);

        } catch (\Exception $e) {
            // Log the exception for debugging
            Log::error('Error updating task: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while updating the task: ' . $e->getMessage()
            ], 500);
        }
    }

}