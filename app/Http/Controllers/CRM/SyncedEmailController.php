<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use App\Jobs\SyncInboxEmailsJob;
use App\Logging\InboxSyncLogger;
use App\Models\EmailLog;
use App\Models\Staff;
use App\Services\EmailSync\IncomingEmailSyncService;
use App\Services\EmailSync\InboxSyncStatusStore;
use App\Services\EmailSync\ManualInboxSyncRunner;
use App\Services\EmailSync\UnassignedEmailAssignmentService;
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

        $result = $assignmentService->assignToClient(
            (int) $validated['email_log_id'],
            (int) $validated['client_id'],
            (int) $validated['client_matter_id'],
            Auth::id()
        );

        $status = $result['success'] ? 200 : 422;

        return response()->json($result, $status);
    }

    public function unassignedIndex()
    {
        $staff = Auth::guard('admin')->user();
        if (! $staff instanceof Staff || ! $staff->canViewSyncedInboxMail()) {
            abort(403, 'You do not have permission to view unassigned synced emails.');
        }

        return view('crm.unassigned_emails.index');
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
        InboxSyncStatusStore $statusStore
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

        $activeSyncId = $statusStore->getActiveSyncId((int) $staff->id);
        if ($activeSyncId !== null) {
            $existing = $statusStore->get($activeSyncId, (int) $staff->id);
            if ($existing && in_array($existing['status'] ?? '', ['pending', 'running'], true)) {
                InboxSyncLogger::info('Manual inbox sync already running', [
                    'staff_id' => (int) $staff->id,
                    'sync_id' => $activeSyncId,
                    'status' => $existing['status'] ?? null,
                ]);

                return response()->json([
                    'success' => true,
                    'background' => true,
                    'sync_id' => $activeSyncId,
                    'status' => $existing['status'],
                    'message' => 'Inbox sync is already running in the background.',
                ]);
            }
        }

        $syncId = $statusStore->create((int) $staff->id, [
            'sync_range' => $syncRange,
            'email' => $email,
        ]);

        SyncInboxEmailsJob::dispatchAfterResponse($syncId, (int) $staff->id, $prepared);

        InboxSyncLogger::info('Manual inbox sync queued', [
            'sync_id' => $syncId,
            'staff_id' => (int) $staff->id,
            'staff_email' => $staff->email,
            'sync_range' => $syncRange,
            'email' => $email !== '' ? $email : ($prepared['email'] ?? ''),
            'addresses' => $prepared['addresses'] ?? [],
        ]);

        return response()->json([
            'success' => true,
            'background' => true,
            'sync_id' => $syncId,
            'status' => 'pending',
            'message' => 'Inbox sync started in the background.',
        ]);
    }

    public function syncStatus(string $syncId, InboxSyncStatusStore $statusStore)
    {
        if (! $this->staffCanSyncInbox()) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to sync inbox emails.',
            ], 403);
        }

        $staff = Auth::guard('admin')->user();
        if (! $staff instanceof Staff) {
            return response()->json([
                'success' => false,
                'message' => 'Staff account required.',
            ], 403);
        }

        $status = $statusStore->get($syncId, (int) $staff->id);
        if ($status === null) {
            return response()->json([
                'success' => false,
                'message' => 'Sync status not found.',
            ], 404);
        }

        $response = [
            'success' => true,
            'sync_id' => $syncId,
            'status' => $status['status'] ?? 'pending',
            'message' => $status['message'] ?? null,
            'sync_range' => $status['sync_range'] ?? null,
            'summary' => $status['summary'] ?? null,
        ];

        if (($status['summary'] ?? null) !== null && is_array($status['summary'])) {
            $summary = $status['summary'];
            $response['total_imported'] = (int) ($summary['total_imported'] ?? 0);
            $response['total_skipped'] = (int) ($summary['total_skipped'] ?? 0);
            $response['total_failed'] = (int) ($summary['total_failed'] ?? 0);
            $response['mailboxes'] = $summary['mailboxes'] ?? [];
        }

        return response()->json($response);
    }

    protected function staffCanSyncInbox(): bool
    {
        $staff = Auth::guard('admin')->user();

        return $staff instanceof Staff && $staff->canSyncInboxEmails();
    }
}
