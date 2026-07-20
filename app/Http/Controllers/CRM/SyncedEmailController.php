<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Services\EmailSync\IncomingEmailSyncService;
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

    public function syncNow(Request $request, IncomingEmailSyncService $syncService)
    {
        if (! $this->staffCanSyncInbox()) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to sync inbox emails.',
            ], 403);
        }

        $email = trim((string) $request->input('email', ''));

        $summary = $syncService->syncAll($email !== '' ? $email : null);

        return response()->json($summary);
    }

    protected function staffCanSyncInbox(): bool
    {
        $staff = Auth::guard('admin')->user();

        return $staff instanceof Staff && $staff->canSyncInboxEmails();
    }
}
