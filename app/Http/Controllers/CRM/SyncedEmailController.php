<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use App\Jobs\SyncInboxEmailsJob;
use App\Models\Staff;
use App\Services\EmailSync\InboxSyncStatusStore;
use App\Services\EmailSync\ManualInboxSyncRunner;
use App\Services\EmailSync\UnassignedEmailAssignmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
        if (! $this->staffCanSyncInbox()) {
            abort(403, 'You do not have permission to view unassigned synced emails.');
        }

        return view('crm.unassigned_emails.index');
    }

    public function syncNow(
        Request $request,
        ManualInboxSyncRunner $runner,
        InboxSyncStatusStore $statusStore
    ) {
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
            return response()->json($prepared, 422);
        }

        $activeSyncId = $statusStore->getActiveSyncId((int) $staff->id);
        if ($activeSyncId !== null) {
            $existing = $statusStore->get($activeSyncId, (int) $staff->id);
            if ($existing && in_array($existing['status'] ?? '', ['pending', 'running'], true)) {
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
