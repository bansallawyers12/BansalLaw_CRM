<?php
namespace App\Services;

use App\Models\ClientMatter;
use App\Models\Note;
use App\Models\Staff;
use App\Models\Notification;
use App\Models\CheckinLog;
use App\Models\ActivitiesLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Support\StaffClientVisibility;

class DashboardService
{
    /**
     * Native Super Admin (role 1) or session-elevated grant — same matter/action visibility as role 1.
     */
    public function viewerSeesAllMattersAndTasks($user): bool
    {
        return $user instanceof Staff && $user->hasEffectiveSuperAdminPrivileges();
    }

    /**
     * Open (incomplete) client action tasks for top-nav badge and dashboard KPI.
     * Staff see tasks assigned to them; elevated Super Admin sees all open tasks.
     */
    public function getPendingOpenTaskCount($user): int
    {
        return $this->getNoteDeadlineCount($user);
    }

    public function forgetPendingOpenTaskCountCache($user): void
    {
        if (! $user) {
            return;
        }
        $userId = (int) $user->id;
        $seeAll = $this->viewerSeesAllMattersAndTasks($user);
        Cache::forget('dashboard_note_deadline_count_'.$userId.'_'.($seeAll ? 'all' : 'mine'));
    }

    /**
     * Get all dashboard data
     */
    public function getDashboardData($request): array
    {
        $user = Auth::guard('admin')->user() ?: Auth::user();
        
        $notesPage = $this->getNotesPage($user, 1, 10);
        $casesPage = $this->getCasesRequiringAttentionPage($user, 1, 10);

        return [
            'notesData' => $notesPage['items'],
            'notes_current_page' => $notesPage['current_page'],
            'notes_last_page' => $notesPage['last_page'],
            'notes_per_page' => $notesPage['per_page'],
            'cases_requiring_attention_data' => $casesPage['items'],
            'cases_current_page' => $casesPage['current_page'],
            'cases_last_page' => $casesPage['last_page'],
            'cases_per_page' => $casesPage['per_page'],
            'count_active_matter' => $this->getActiveMatterCount($user),
            'count_closed_matter' => $this->getClosedMatterCount($user),
            'count_note_deadline' => $notesPage['total'],
            'count_cases_requiring_attention_data' => $casesPage['total'],
            'dashboardAssignableStaff' => $this->getAssignableStaffForPopover(),
        ];
    }

    /**
     * Active staff for the dashboard "Add task" popover.
     * Cached as plain arrays — never cache Eloquent models (unserialize breaks).
     *
     * @return \Illuminate\Support\Collection<int, array{id: int, first_name: string, last_name: string, office_name: string}>
     */
    public function getAssignableStaffForPopover()
    {
        $rows = Cache::remember('dashboard_assignable_staff_v3', 300, function () {
            return Staff::query()
                ->leftJoin('branches', 'staff.office_id', '=', 'branches.id')
                ->where('staff.status', 1)
                ->orderBy('staff.first_name')
                ->orderBy('staff.last_name')
                ->get([
                    'staff.id',
                    'staff.first_name',
                    'staff.last_name',
                    'branches.office_name',
                ])
                ->map(fn (Staff $row) => [
                    'id' => (int) $row->id,
                    'first_name' => (string) ($row->first_name ?? ''),
                    'last_name' => (string) ($row->last_name ?? ''),
                    'office_name' => (string) ($row->office_name ?? ''),
                ])
                ->values()
                ->all();
        });

        return collect(is_array($rows) ? $rows : []);
    }

    /**
     * Paginated notes / tasks for the dashboard My Tasks list (infinite scroll).
     * Matches Tasks page: includes Personal Tasks (null client_id) and all task groups.
     *
     * @return array{items: \Illuminate\Support\Collection, current_page: int, last_page: int, per_page: int, total: int}
     */
    public function getNotesPage($user, int $page = 1, int $perPage = 10): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(50, $perPage));
        $total = $this->getNoteDeadlineCount($user);
        $lastPage = max(1, (int) ceil($total / $perPage));

        $items = $this->notesQuery($user)
            ->orderByRaw('CASE WHEN note_deadline IS NOT NULL THEN 0 ELSE 1 END')
            ->orderBy('note_deadline', 'ASC')
            ->orderBy('created_at', 'DESC')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        return [
            'items' => $items,
            'current_page' => $page,
            'last_page' => $lastPage,
            'per_page' => $perPage,
            'total' => $total,
        ];
    }

    /**
     * Base query for dashboard My Tasks (same scope as getNoteDeadlineCount).
     */
    private function notesQuery($user)
    {
        $query = Note::with([
            'client:id,first_name,last_name,client_id,is_company',
            'client.company:id,admin_id,company_name',
            'assignedUser:id,first_name,last_name',
        ])
            ->where('type', 'client')
            ->where('is_action', 1)
            ->where('status', '!=', 1);

        // Super Admin (or elevated) sees ALL actions — matching Action page behavior
        if (! $this->viewerSeesAllMattersAndTasks($user)) {
            $query->where('assigned_to', $user->id);
        }

        return $query;
    }

    /**
     * Paginated cases requiring attention for dashboard infinite scroll.
     *
     * @return array{items: \Illuminate\Support\Collection, current_page: int, last_page: int, per_page: int, total: int}
     */
    public function getCasesRequiringAttentionPage($user, int $page = 1, int $perPage = 10): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(50, $perPage));
        $total = $this->getCasesRequiringAttentionCount($user);
        $lastPage = max(1, (int) ceil($total / $perPage));

        $cases = $this->casesRequiringAttentionQuery($user)
            ->orderBy('updated_at', 'asc')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        $this->attachLatestActivities($cases);

        return [
            'items' => $cases,
            'current_page' => $page,
            'last_page' => $lastPage,
            'per_page' => $perPage,
            'total' => $total,
        ];
    }

    /**
     * Base query for cases requiring attention (same scope as getCasesRequiringAttentionCount).
     */
    private function casesRequiringAttentionQuery($user)
    {
        $query = ClientMatter::with([
                'client:id,first_name,last_name,client_id',
                'matter:id,title',
                'personResponsible:id,first_name,last_name'
            ])
            ->where('matter_status', 1)
            ->where('updated_at', '>=', Carbon::now()->subDays(100));

        if (! $this->viewerSeesAllMattersAndTasks($user)) {
            $query->whereHas('client', function ($q) use ($user) {
                StaffClientVisibility::excludeSuperAdminOnlyLockedClientsFromAdminQuery($q, $user);
            });
        }

        $this->applyRoleBasedFiltering($query, $user);

        return $query;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, ClientMatter>  $cases
     */
    protected function attachLatestActivities($cases): void
    {
        if ($cases->isEmpty()) {
            return;
        }

        $clientIds = $cases->pluck('client_id')->filter()->unique()->values();
        if ($clientIds->isEmpty()) {
            foreach ($cases as $case) {
                $case->latest_activity = [
                    'type' => 'default',
                    'date' => $case->updated_at,
                ];
            }

            return;
        }

        $latestLogIds = ActivitiesLog::query()
            ->selectRaw('MAX(id) as id')
            ->whereIn('client_id', $clientIds)
            ->groupBy('client_id')
            ->pluck('id');

        $logsByClient = $latestLogIds->isEmpty()
            ? collect()
            : ActivitiesLog::query()
                ->whereIn('id', $latestLogIds)
                ->get(['id', 'client_id', 'subject', 'created_at'])
                ->keyBy('client_id');

        foreach ($cases as $case) {
            $log = $logsByClient->get($case->client_id);
            $case->latest_activity = $log
                ? $this->activityFromLog($log)
                : [
                    'type' => 'default',
                    'date' => $case->updated_at,
                ];
        }
    }

    /**
     * @return array{type: string, date: mixed}
     */
    protected function activityFromLog(ActivitiesLog $log): array
    {
        $subject = strtolower($log->subject ?? '');
        $type = 'default';

        if (str_contains($subject, 'stage') || str_contains($subject, 'workflow')) {
            $type = 'stage_updated';
        } elseif (str_contains($subject, 'status')) {
            $type = 'status_changed';
        } elseif (str_contains($subject, 'appointment') || str_contains($subject, 'meeting')) {
            $type = 'appointment_scheduled';
        } elseif (str_contains($subject, 'payment') || str_contains($subject, 'invoice')) {
            $type = 'payment_received';
        } elseif (str_contains($subject, 'note')) {
            $type = 'note_added';
        } elseif (str_contains($subject, 'email')) {
            $type = 'email_sent';
        } elseif (str_contains($subject, 'document') || str_contains($subject, 'upload')) {
            $type = 'document_uploaded';
        } elseif (str_contains($subject, 'sign')) {
            $type = 'signed';
        }

        return [
            'type' => $type,
            'date' => $log->created_at,
        ];
    }

    /**
     * Apply role-based filtering to queries
     */
    private function applyRoleBasedFiltering($query, $user)
    {
        if ($this->viewerSeesAllMattersAndTasks($user)) {
            return;
        }

        if (!$user) {
            $query->whereRaw('1 = 0');
            return;
        }

        // Exclude super-admin-only locked clients for non-super-admins
        $query->whereHas('client', function ($q) use ($user) {
            StaffClientVisibility::excludeSuperAdminOnlyLockedClientsFromAdminQuery($q, $user);
        });

        // Exempt roles / staff see all non-locked matters
        if (StaffClientVisibility::isExemptFromAllocation($user)) {
            return;
        }

        // Non-exempt staff: must be assigned on matter, client owner, or have active cross-access grant
        $uid = (int) $user->id;
        $query->where(function ($q) use ($uid) {
            $q->where('client_matters.sel_legal_practitioner', $uid)
                ->orWhere('client_matters.sel_person_responsible', $uid)
                ->orWhere('client_matters.sel_person_assisting', $uid)
                ->orWhereHas('client', function ($clientQ) use ($uid) {
                    $clientQ->where('user_id', $uid);
                })
                ->orWhereExists(function ($sub) use ($uid) {
                    $sub->select(DB::raw('1'))
                        ->from('client_access_grants')
                        ->whereColumn('client_access_grants.admin_id', 'client_matters.client_id')
                        ->where('client_access_grants.staff_id', $uid)
                        ->where('client_access_grants.status', 'active')
                        ->whereNotNull('client_access_grants.ends_at')
                        ->whereRaw('client_access_grants.ends_at > NOW()');
                });
        });
    }

    /**
     * Get active matter count with caching (viewer-scoped)
     */
    private function getActiveMatterCount($user = null): int
    {
        $user = $user ?? (Auth::guard('admin')->user() ?: Auth::user());
        $userId = $user ? $user->id : 0;
        
        return Cache::remember('active_matter_count_staff_' . $userId, 300, function () use ($user) {
            $query = ClientMatter::from('client_matters as client_matters')
                ->join('admins as ad', 'client_matters.client_id', '=', 'ad.id')
                ->where('ad.is_archived', '=', '0')
                ->whereIn('ad.type', ['client', 'lead'])
                ->whereNull('ad.is_deleted')
                ->where('client_matters.matter_status', 1);

            $this->applyRoleBasedFiltering($query, $user);

            return (int) $query->count();
        });
    }

    /**
     * Get closed matter count (viewer-scoped, mirrors closedmatterslist filters).
     */
    private function getClosedMatterCount($user = null): int
    {
        $user = $user ?? (Auth::guard('admin')->user() ?: Auth::user());
        $userId = $user ? $user->id : 0;

        return Cache::remember('closed_matter_count_staff_' . $userId, 300, function () use ($user) {
            $closedStages = ClientMatter::closedWorkflowStageNames();

            $query = ClientMatter::from('client_matters as client_matters')
                ->join('admins as ad', 'client_matters.client_id', '=', 'ad.id')
                ->leftJoin('workflow_stages as ws', 'client_matters.workflow_stage_id', '=', 'ws.id')
                ->where('ad.is_archived', '=', '0')
                ->whereIn('ad.type', ['client', 'lead'])
                ->whereNull('ad.is_deleted')
                ->where(function ($q) use ($closedStages) {
                    $q->where('client_matters.matter_status', '=', '0')
                        ->orWhereRaw(
                            'LOWER(TRIM(ws.name)) IN (' . implode(',', array_fill(0, count($closedStages), '?')) . ')',
                            $closedStages
                        );
                });

            $this->applyRoleBasedFiltering($query, $user);

            return (int) $query->count();
        });
    }

    /**
     * Get note deadline count (all tasks count)
     * Matches Tasks page getTaskCounts: includes Personal Tasks
     */
    private function getNoteDeadlineCount($user): int
    {
        $userId = $user ? (int) $user->id : 0;
        $seeAll = $this->viewerSeesAllMattersAndTasks($user);

        return Cache::remember('dashboard_note_deadline_count_' . $userId . '_' . ($seeAll ? 'all' : 'mine'), 60, function () use ($user, $seeAll) {
            $query = Note::where('type', 'client')
                ->where('is_action', 1)
                ->where('status', '!=', 1);

            if (! $seeAll) {
                $query->where('assigned_to', $user->id);
            }

            return $query->count();
        });
    }

    /**
     * Get cases requiring attention count
     */
    private function getCasesRequiringAttentionCount($user): int
    {
        $userId = $user ? (int) $user->id : 0;
        $seeAll = $this->viewerSeesAllMattersAndTasks($user);

        return Cache::remember('dashboard_cases_attention_count_' . $userId . '_' . ($seeAll ? 'all' : 'mine'), 60, function () use ($user, $seeAll) {
            $query = ClientMatter::join('admins as clients', 'client_matters.client_id', '=', 'clients.id')
                ->where('client_matters.matter_status', 1)
                ->where('client_matters.updated_at', '>=', Carbon::now()->subDays(100));

            if (! $seeAll) {
                StaffClientVisibility::applyExcludeSuperAdminOnlyLockedClientsOnAdminJoin($query, 'clients', $user);
            }

            $this->applyRoleBasedFiltering($query, $user);

            return $query->count();
        });
    }

    /**
     * Get notifications
     */
    public function getNotifications(): array
    {
        $count = Notification::where('receiver_id', Auth::id())
            ->where('receiver_status', 0)
            ->count();

        return ['count' => $count];
    }

    /**
     * Get office visit notifications
     */
    public function getOfficeVisitNotifications(): array
    {
        $notifications = Notification::with(['sender:id,first_name,last_name'])
            ->where('receiver_id', Auth::id())
            ->where('notification_type', 'officevisit')
            ->where('receiver_status', 0)
            ->orderBy('created_at', 'DESC')
            ->get();

        $data = [];
        foreach ($notifications as $notification) {
            $checkinLog = CheckinLog::find($notification->module_id);
            
            if (!$checkinLog || $checkinLog->status != 0) {
                continue;
            }

            $data[] = [
                'id' => $notification->id,
                'checkin_id' => $checkinLog->id,
                'message' => $notification->message,
                'sender_name' => $notification->sender 
                    ? $notification->sender->first_name . ' ' . $notification->sender->last_name 
                    : 'System',
                'client_name' => $checkinLog->contactDisplayLabel(),
                'visit_purpose' => $checkinLog->visit_purpose,
                'created_at' => $notification->created_at->format('d/m/Y h:i A'),
                'url' => $notification->url
            ];
        }

        return $data;
    }

    /**
     * Mark notification as seen
     */
    public function markNotificationAsSeen($notificationId): array
    {
        $notification = Notification::find($notificationId);
        
        if (!$notification || $notification->receiver_id != Auth::id()) {
            return ['status' => 'error'];
        }

        $notification->receiver_status = 1;
        $notification->save();

        return ['status' => 'success'];
    }

    /**
     * Extend note deadline
     */
    public function extendNoteDeadline($data, $user = null): array
    {
        try {
            $user = $user ?? Auth::user();
            $uniqueGroupId = trim((string) ($data['unique_group_id'] ?? ''));

            if ($uniqueGroupId !== '') {
                $notes = Note::where('unique_group_id', $uniqueGroupId)
                    ->where('unique_group_id', '!=', '')
                    ->whereNotNull('unique_group_id')
                    ->get();
            } else {
                $noteId = $data['note_id'] ?? $data['id'] ?? 0;
                $notes = Note::where('id', $noteId)->get();
            }

            if ($notes->isEmpty()) {
                return ['success' => false, 'message' => 'No notes found with the provided criteria'];
            }

            if ($user && !$this->viewerSeesAllMattersAndTasks($user)) {
                $uid = (int) $user->id;
                foreach ($notes as $note) {
                    $isOwnerOrAssignee = ((int)$note->assigned_to === $uid || (int)$note->user_id === $uid);
                    $hasClientAccess = $note->client_id ? StaffClientVisibility::canAccessClientOrLead((int) $note->client_id, $user) : false;
                    if (!$isOwnerOrAssignee && !$hasClientAccess) {
                        return ['success' => false, 'message' => 'Unauthorized deadline extension.'];
                    }
                }
            }

            if ($uniqueGroupId !== '') {
                $updated = Note::where('unique_group_id', $uniqueGroupId)
                    ->where('unique_group_id', '!=', '')
                    ->whereNotNull('unique_group_id')
                    ->update([
                        'description' => $data['description'],
                        'note_deadline' => $data['note_deadline'],
                        'user_id' => $user ? $user->id : Auth::id()
                    ]);
            } else {
                $noteId = $data['note_id'] ?? $data['id'] ?? 0;
                $updated = Note::where('id', $noteId)->update([
                    'description' => $data['description'],
                    'note_deadline' => $data['note_deadline'],
                    'user_id' => $user ? $user->id : Auth::id()
                ]);
            }

            if ($updated > 0) {
                // Create notification and activity log for the first note
                $firstNote = $notes->first();
                $this->createNotificationAndActivityLog($firstNote);

                return [
                    'success' => true, 
                    'message' => 'Successfully updated', 
                    'clientID' => $firstNote->client_id
                ];
            } else {
                return ['success' => false, 'message' => 'Failed to update notes'];
            }
        } catch (\Exception $e) {
            Log::error('Error extending note deadline: ' . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred while extending the deadline'];
        }
    }

    /**
     * Update action completion status and create completed action activity
     * Matches Action tab behavior: updates note(s), creates ActivitiesLog with optional completion notes
     */
    public function completeTask($noteId, $uniqueGroupId, ?string $completionNotes = null, $user = null): array
    {
        $user = $user ?? Auth::user();
        $noteData = Note::where('id', $noteId)->first();

        if (!$noteData) {
            return ['success' => false, 'message' => 'Task not found'];
        }

        if ($user && !$this->viewerSeesAllMattersAndTasks($user)) {
            $uid = (int) $user->id;
            $notesToCheck = collect([$noteData]);
            $groupId = trim((string) ($uniqueGroupId ?? ''));
            if ($groupId !== '') {
                $groupNotes = Note::where('unique_group_id', $groupId)->whereNotNull('unique_group_id')->get();
                if ($groupNotes->isNotEmpty()) {
                    $notesToCheck = $groupNotes;
                }
            }

            foreach ($notesToCheck as $checkNote) {
                $isOwnerOrAssignee = ((int)$checkNote->assigned_to === $uid || (int)$checkNote->user_id === $uid);
                if (!$isOwnerOrAssignee) {
                    return ['success' => false, 'message' => 'Unauthorized task completion.'];
                }
                if ($checkNote->client_id && !StaffClientVisibility::canAccessClientOrLead((int) $checkNote->client_id, $user)) {
                    return ['success' => false, 'message' => 'Unauthorized task completion.'];
                }
            }
        }

        // Update all notes in the group (matches Action tab behavior), or single note if no group
        $updated = 0;
        $groupId = trim((string) ($uniqueGroupId ?? ''));
        if ($groupId !== '') {
            $updated = Note::where('unique_group_id', $groupId)
                ->where('unique_group_id', '!=', '')
                ->whereNotNull('unique_group_id')
                ->update(['status' => 1]);
        }
        if (!$updated) {
            $updated = Note::where('id', $noteId)->update(['status' => 1]);
        }
        if (!$updated) {
            return ['success' => false, 'message' => 'Failed to complete task'];
        }

        app(\App\Services\ClientMatterTaskSyncService::class)->syncCompletionFromNote($noteData, true);

        if ($noteData->client_id) {
            $assigneeName = 'N/A';
            if ($noteData->assigned_to) {
                $assignee = \App\Models\Staff::find($noteData->assigned_to);
                $assigneeName = $assignee ? $assignee->first_name . ' ' . $assignee->last_name : 'N/A';
            }

            $description = '';
            if (!empty($completionNotes)) {
                $description .= '<p>';
                $description .= '<i class="fa-solid fa-ellipsis-vertical convert-activity-to-note" ';
                $description .= 'style="cursor: pointer; color: #6c757d;" ';
                $description .= 'title="Convert to Note" ';
                $description .= 'data-activity-id="" ';
                $description .= 'data-activity-subject="Completion Notes" ';
                $description .= 'data-activity-description="' . htmlspecialchars($completionNotes, ENT_QUOTES) . '" ';
                $description .= 'data-activity-created-by="' . Auth::id() . '" ';
                $description .= 'data-activity-created-at="' . now() . '" ';
                $description .= 'data-client-id="' . $noteData->client_id . '"></i></p>';
                $description .= '<p>' . nl2br(htmlspecialchars($completionNotes)) . '</p>';
                $description .= '<hr>';
            }
            $description .= '<p>' . ($noteData->description ?? '') . '</p>';

            ActivitiesLog::create([
                'client_id' => $noteData->client_id,
                'created_by' => Auth::id(),
                'subject' => 'completed task for ' . $assigneeName,
                'description' => $description,
                'activity_type' => 'activity',
                'use_for' => (Auth::id() != $noteData->assigned_to) ? $noteData->assigned_to : null,
                'followup_date' => $noteData->updated_at,
                'task_group' => $noteData->task_group ?? null,
                'task_status' => 1,
                'pin' => 0,
            ]);
        }

        return ['success' => true, 'message' => 'Task completed successfully'];
    }

    /**
     * Create notification and activity log
     */
    private function createNotificationAndActivityLog($note): void
    {
        try {
            // Create notification only if assigned_to exists
            if ($note->assigned_to) {
                $notificationUrl = $note->client_id
                    ? url('/clients/detail/' . base64_encode(convert_uuencode($note->client_id)))
                    : route('assignee.tasks');
                Notification::create([
                    'sender_id' => Auth::id(),
                    'receiver_id' => $note->assigned_to,
                    'module_id' => $note->client_id ?? 0,
                    'url' => $notificationUrl,
                    'notification_type' => 'client',
                    'message' => 'Task Extended by ' . Auth::user()->first_name . ' ' . Auth::user()->last_name . ' on ' . date('d/M/Y h:i A')
                ]);
            }

            // Create activity log (client_id may be null for Personal Tasks)
            ActivitiesLog::create([
                'client_id' => $note->client_id,
                'created_by' => Auth::id(),
                'subject' => 'Extended Note Deadline',
                'description' => '<span class="text-semi-bold">' . ($note->title ?? 'Note') . '</span><p>' . ($note->description ?? '') . '</p>',
                'activity_type' => 'activity',
                'use_for' => Auth::id() != $note->user_id ? $note->user_id : '',
                'followup_date' => $note->action_date ?? null,
                'task_group' => $note->task_group ?? null,
                'task_status' => 0,
                'pin' => 0,
            ]);
        } catch (\Exception $e) {
            // Log the error but don't break the main functionality
            Log::error('Error creating notification/activity log: ' . $e->getMessage());
        }
    }
}
