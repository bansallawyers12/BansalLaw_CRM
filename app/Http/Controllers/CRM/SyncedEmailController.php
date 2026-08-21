<?php



namespace App\Http\Controllers\CRM;



use App\Http\Controllers\Controller;

use App\Logging\InboxSyncLogger;

use App\Models\EmailLog;

use App\Models\Staff;

use App\Services\EmailSync\IncomingEmailSyncService;

use App\Services\EmailSync\ManualInboxSyncRunner;

use App\Services\EmailSync\UnassignedEmailAssignmentService;

use App\Support\StaffClientVisibility;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\Auth;

use Illuminate\Support\Facades\Schema;



class SyncedEmailController extends Controller

{

    public function __construct()

    {

        $this->middleware('auth:admin');

    }



    public function assignToClient(Request $request, UnassignedEmailAssignmentService $assignmentService)

    {

        if (! $this->staffCanSyncInbox()) {

            return response()->json([

                'success' => false,

                'message' => 'You do not have permission to assign synced inbox emails.',

            ], 403);

        }



        $validated = $request->validate([

            'email_log_id' => 'required|integer|min:1',

            'client_id' => 'required|integer|min:1',

            'client_matter_id' => 'required|integer|min:1',

        ]);

        if (! $this->canAccessSyncedEmail((int) $validated['email_log_id'])) {
            return response()->json([
                'success' => false,
                'message' => 'Email not found or you do not have permission to update it.',
            ], 404);
        }



        $result = $assignmentService->assignToClient(

            (int) $validated['email_log_id'],

            (int) $validated['client_id'],

            (int) $validated['client_matter_id'],

            Auth::id()

        );



        $status = $result['success'] ? 200 : 422;



        return response()->json($result, $status);

    }

    public function assignBySubject(\App\Services\EmailSync\SubjectReferenceAutoAssignService $autoAssignService)
    {
        if (! $this->staffCanSyncInbox()) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to assign synced inbox emails.',
            ], 403);
        }

        $staff = Auth::guard('admin')->user();
        if (! $staff instanceof Staff || ! $staff->canAssignEmailsBySubject()) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to use Assign by subject.',
            ], 403);
        }

        @set_time_limit(120);

        $result = $autoAssignService->scanAndAssignForStaff($staff);

        return response()->json([
            'success' => true,
            'message' => $this->assignBySubjectSummary($result),
            'assigned_count' => $result['assigned_count'],
            'assigned' => $result['assigned'],
            'ready_pairs' => $result['ready_pairs'] ?? [],
            'needs_matter' => $result['needs_matter'],
            'skipped_count' => $result['skipped_count'],
        ]);
    }

    public function confirmSubjectAssignments(
        Request $request,
        \App\Services\EmailSync\SubjectReferenceAutoAssignService $autoAssignService
    ) {
        if (! $this->staffCanSyncInbox()) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to assign synced inbox emails.',
            ], 403);
        }

        $staff = Auth::guard('admin')->user();
        if (! $staff instanceof Staff || ! $staff->canAssignEmailsBySubject()) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to use Assign by subject.',
            ], 403);
        }

        $validated = $request->validate([
            'assignments' => 'required|array|min:1|max:200',
            'assignments.*.email_log_id' => 'required|integer|min:1',
            'assignments.*.client_id' => 'required|integer|min:1',
            'assignments.*.client_matter_id' => 'required|integer|min:1',
        ]);

        foreach ($validated['assignments'] as $item) {
            if (! $this->canAccessSyncedEmail((int) $item['email_log_id'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email not found or you do not have permission to update it.',
                ], 404);
            }
        }

        $result = $autoAssignService->confirmMatterChoices($validated['assignments'], true);
        $result['message'] = $result['assigned_count'] === 1
            ? '1 selected email assigned.'
            : $result['assigned_count'] . ' selected emails assigned.';

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    /**
     * @param array{ready_pairs?: list<mixed>, needs_matter: list<mixed>, skipped_count: int} $result
     */
    protected function assignBySubjectSummary(array $result): string
    {
        $ready = count($result['ready_pairs'] ?? []);
        $needs = count($result['needs_matter'] ?? []);
        $parts = [];
        if ($ready > 0) {
            $parts[] = $ready === 1
                ? '1 email matched client ID + matter — select to assign.'
                : $ready . ' emails matched client ID + matter — select to assign.';
        }
        if ($needs > 0) {
            $parts[] = $needs === 1
                ? '1 client needs a matter chosen.'
                : $needs . ' clients need a matter chosen.';
        }
        if ($parts === []) {
            return 'No unassigned emails had a matching client ID and matter, or a unique client name.';
        }

        return implode(' ', $parts);
    }



    public function unlinkFromClient(Request $request, UnassignedEmailAssignmentService $assignmentService)

    {
        $request->merge([

            'action' => $request->input('action', 'unassigned'),

        ]);

        $validated = $request->validate([

            'email_log_id' => 'required|integer|min:1',

            'action' => 'required|in:unassigned,client',

            'client_id' => 'required_if:action,client|nullable|integer|min:1',

            'client_matter_id' => 'required_if:action,client|nullable|integer|min:1',

        ]);



        if (! $this->canReassignOrUnlinkEmail((int) $validated['email_log_id'], (string) $validated['action'])) {
            return response()->json([
                'success' => false,
                'message' => 'Email not found or you do not have permission to reassign it.',
            ], 403);
        }

        if ($validated['action'] === 'client') {
            $result = $assignmentService->assignToClient(
                (int) $validated['email_log_id'],
                (int) $validated['client_id'],
                (int) $validated['client_matter_id'],
                Auth::id(),
                true
            );
        } else {
            $result = $assignmentService->unlinkFromClient(
                (int) $validated['email_log_id'],
                Auth::id()
            );
        }

        return response()->json($result, $result['success'] ? 200 : 422);
    }



    public function unassignedIndex()
    {
        if (\App\Services\EmailSync\InboxSyncMasterControl::isDisabled()) {
            return redirect()->route('dashboard')->with(
                'error',
                \App\Services\EmailSync\InboxSyncMasterControl::disabledMessage()
            );
        }

        $staff = Auth::guard('admin')->user();

        if (! $staff instanceof Staff || ! $staff->canViewSyncedInboxMail()) {
            abort(403, 'You do not have permission to view unassigned synced emails.');
        }

        if (! $staff->canViewAllSyncedInboxMail()) {
            $unassignedCount = IncomingEmailSyncService::countUnassignedSyncedInboxMail($staff);

            if ($unassignedCount === 0) {
                return redirect()->route('dashboard')->with('error', 'You currently have no unassigned emails.');
            }
        }

        return view('crm.unassigned_emails.index');
    }



    public function autoAssignmentReviewIndex()

    {

        $staff = Auth::guard('admin')->user();

        if (! $staff instanceof Staff || ! $staff->canViewSyncedInboxMail()) {

            abort(403, 'You do not have permission to review auto-assigned emails.');

        }



        return view('crm.auto_assignment_review.index');

    }



    public function unassignedCount()
    {
        if (\App\Services\EmailSync\InboxSyncMasterControl::isDisabled()) {
            return response()->json([
                'success' => true,
                'count' => 0,
                'disabled' => true,
            ]);
        }

        $staff = Auth::guard('admin')->user();

        if (! $staff instanceof Staff || ! $staff->canViewSyncedInboxMail()) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to view unassigned synced emails.',
                'count' => 0,
            ], 403);
        }

        return response()->json([
            'success' => true,
            'count' => IncomingEmailSyncService::countUnassignedSyncedInboxMail($staff),
        ]);
    }



    /**

     * Mark all unread emails as read in a synced inbox folder (unassigned or assigned).

     */

    public function markFolderRead(Request $request)

    {

        $staff = Auth::guard('admin')->user();

        if (! $staff instanceof Staff || ! $staff->canViewSyncedInboxMail()) {

            return response()->json([

                'success' => false,

                'message' => 'You do not have permission to update synced inbox emails.',

                'updated_count' => 0,

            ], 403);

        }



        $folder = $request->input('folder', 'unassigned');

        if (! in_array($folder, ['unassigned', 'assigned'], true)) {

            return response()->json([

                'success' => false,

                'message' => 'Invalid folder.',

                'updated_count' => 0,

            ], 422);

        }



        $query = EmailLog::query();

        IncomingEmailSyncService::applySyncedInboxVisibilityFilter($query, $staff);



        $query->where('mail_type', 1)

            ->where(function ($q) {

                $q->where('mail_body_type', 'inbox')

                    ->orWhereNull('mail_body_type');

            })

            ->where(function ($q) {

                $q->whereNull('mail_is_read')

                    ->orWhere('mail_is_read', false);

            });



        if ($folder === 'unassigned') {

            IncomingEmailSyncService::applyUnassignedSyncedInboxScope($query);

        } else {

            if (! Schema::hasColumn('email_logs', 'sync_assignment_status')) {

                return response()->json([

                    'success' => true,

                    'message' => 'No unread emails to update.',

                    'updated_count' => 0,

                ]);

            }



            $query->whereIn('sync_assignment_status', ['auto_assigned', 'manual_assigned'])

                ->whereNotNull('client_id');



            if (Schema::hasColumn('email_logs', 'synced_email_id')) {

                $query->whereNotNull('synced_email_id');

            }

        }



        $updatedCount = (clone $query)->count();



        if ($updatedCount > 0) {

            $query->update(['mail_is_read' => true]);

        }



        $message = $updatedCount > 0

            ? ($updatedCount === 1 ? '1 email marked as read.' : $updatedCount . ' emails marked as read.')

            : 'No unread emails to update.';



        return response()->json([

            'success' => true,

            'message' => $message,

            'updated_count' => $updatedCount,

        ]);

    }



    public function syncNow(

        Request $request,

        ManualInboxSyncRunner $runner,

    ) {

        if (! $this->staffCanSyncInbox()) {

            InboxSyncLogger::warning('Manual inbox sync denied', [

                'staff_id' => Auth::id(),

                'reason' => 'permission',

            ]);



            return response()->json([

                'success' => false,

                'message' => 'You do not have permission to sync inbox emails.',

            ], 403);

        }



        $staff = Auth::guard('admin')->user();

        if (! $staff instanceof Staff) {

            return response()->json([

                'success' => false,

                'message' => 'Staff account required to sync inbox emails.',

            ], 403);

        }



        @set_time_limit(600);

        @ignore_user_abort(true);



        $email = trim((string) $request->input('email', ''));

        $syncRange = strtolower(trim((string) $request->input('sync_range', '')));



        if ($syncRange === '' && $request->boolean('today', false)) {

            $syncRange = 'today';

        }

        if ($syncRange === '') {

            $syncRange = 'today';

        }



        $prepared = $runner->prepare($staff, $syncRange, $email);

        if (($prepared['success'] ?? true) === false) {

            InboxSyncLogger::warning('Manual inbox sync prepare failed', [

                'staff_id' => (int) $staff->id,

                'sync_range' => $syncRange,

                'email' => $email,

                'message' => $prepared['message'] ?? null,

            ]);



            return response()->json($prepared, 422);

        }



        InboxSyncLogger::info('Manual inbox sync started (inline)', [

            'staff_id' => (int) $staff->id,

            'staff_email' => $staff->email,

            'sync_range' => $syncRange,

            'email' => $email !== '' ? $email : ($prepared['email'] ?? ''),

            'addresses' => $prepared['addresses'] ?? [],

            'source' => 'manual',

        ]);



        $summary = $runner->execute($prepared);



        if (($summary['success'] ?? true) === false) {

            InboxSyncLogger::error('Manual inbox sync failed', [

                'staff_id' => (int) $staff->id,

                'sync_range' => $syncRange,

                'email' => $email,

                'message' => $summary['message'] ?? 'Inbox sync failed.',

                'summary' => $summary,

            ]);



            return response()->json([

                'success' => false,

                'message' => $summary['message'] ?? 'Inbox sync failed.',

                'sync_range' => $syncRange,

                'total_imported' => (int) ($summary['total_imported'] ?? 0),

                'total_skipped' => (int) ($summary['total_skipped'] ?? 0),

                'total_failed' => (int) ($summary['total_failed'] ?? 0),

                'mailboxes' => $summary['mailboxes'] ?? [],

            ], 422);

        }



        $response = [

            'success' => true,

            'background' => false,

            'sync_range' => $syncRange,

            'message' => \App\Services\EmailSync\InboxSyncStatusStore::buildResultMessage($summary),

            'summary' => $summary,

            'total_imported' => (int) ($summary['total_imported'] ?? 0),

            'total_skipped' => (int) ($summary['total_skipped'] ?? 0),

            'total_failed' => (int) ($summary['total_failed'] ?? 0),

            'mailboxes' => $summary['mailboxes'] ?? [],

        ];



        $statusCode = ((int) ($summary['total_failed'] ?? 0)) > 0 ? 422 : 200;



        return response()->json($response, $statusCode);

    }



    public function syncStatus(string $syncId)

    {

        return response()->json([

            'success' => false,

            'message' => 'Background inbox sync is no longer used. Sync runs inline when you click Sync.',

        ], 410);

    }



    protected function staffCanSyncInbox(): bool

    {

        $staff = Auth::guard('admin')->user();



        return $staff instanceof Staff && $staff->canSyncInboxEmails();

    }



    protected function canAccessSyncedEmail(int $emailLogId): bool

    {

        $staff = Auth::guard('admin')->user();

        if (! $staff instanceof Staff) {

            return false;

        }



        $query = EmailLog::query()

            ->whereKey($emailLogId)

            ->whereNotNull('synced_email_id');

        IncomingEmailSyncService::applySyncedInboxVisibilityFilter($query, $staff);



        return $query->exists();

    }



    protected function canUnlinkSyncedEmail(int $emailLogId): bool
    {
        return $this->canReassignOrUnlinkEmail($emailLogId, 'unassigned');
    }

    /**
     * Reassign-to-client works for any client-linked email staff can access.
     * Move-to-Unassigned requires a Zoho-synced origin (synced_email_id).
     */
    protected function canReassignOrUnlinkEmail(int $emailLogId, string $action): bool
    {
        $staff = Auth::guard('admin')->user();

        if (! $staff instanceof Staff) {
            return false;
        }

        $emailLog = EmailLog::query()
            ->whereKey($emailLogId)
            ->whereNotNull('client_id')
            ->first(['id', 'client_id', 'synced_email_id', 'mail_type']);

        if (! $emailLog) {
            return false;
        }

        // CRM compose / outbound is not reassigned via this endpoint.
        if ((int) ($emailLog->mail_type ?? 0) === 2) {
            return false;
        }

        if ($action === 'unassigned' && empty($emailLog->synced_email_id)) {
            return false;
        }

        if (StaffClientVisibility::canAccessClientOrLead((int) $emailLog->client_id, $staff)) {
            return true;
        }

        if (! $staff->canSyncInboxEmails()) {
            return false;
        }

        // Sync-inbox staff may reassign/unlink rows they can see in the synced queue.
        if (! empty($emailLog->synced_email_id)) {
            return $this->canAccessSyncedEmail($emailLogId);
        }

        return false;
    }
}


