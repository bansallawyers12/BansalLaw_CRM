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

    public function unassignedIndex()
    {
        if (! $this->staffCanSyncInbox()) {
            abort(403, 'You do not have permission to view unassigned synced emails.');
        }

        return view('crm.unassigned_emails.index');
    }

    public function syncNow(Request $request, IncomingEmailSyncService $syncService)
    {
        if (! $this->staffCanSyncInbox()) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to sync inbox emails.',
            ], 403);
        }

        @set_time_limit(300);

        $staff = Auth::guard('admin')->user();
        $email = trim((string) $request->input('email', ''));
        $syncRange = strtolower(trim((string) $request->input('sync_range', '')));

        if ($syncRange === '' && $request->boolean('today', false)) {
            $syncRange = 'today';
        }
        if ($syncRange === '') {
            $syncRange = 'today';
        }

        if (! IncomingEmailSyncService::isValidSyncRange($syncRange)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid sync range selected.',
                'mailboxes' => [],
                'total_imported' => 0,
                'total_skipped' => 0,
                'total_failed' => 0,
            ], 422);
        }

        $since = $syncRange === 'full'
            ? null
            : IncomingEmailSyncService::resolveSyncSince($syncRange);

        if ($staff instanceof Staff && $staff->canViewAllSyncedInboxMail()) {
            if ($syncRange === 'full') {
                $syncService->resetUidTracking(null, null);
            }

            $summary = $syncService->syncAll(null, $since);
            $summary['sync_range'] = $syncRange;

            return response()->json($summary);
        }

        $addresses = [];
        if ($email !== '') {
            $addresses = [strtolower($email)];
        } elseif ($staff instanceof Staff) {
            $addresses = IncomingEmailSyncService::mailboxAddressesForStaff((int) $staff->id, $staff->email);
            if ($addresses === [] && trim((string) $staff->email) !== '') {
                $addresses = [strtolower(trim((string) $staff->email))];
            }
        }

        if ($addresses === []) {
            return response()->json([
                'success' => false,
                'message' => 'No synced mailbox is linked to your staff account. Configure it in Admin Console → Staff → Email & mailbox.',
                'mailboxes' => [],
                'total_imported' => 0,
                'total_skipped' => 0,
                'total_failed' => 0,
            ], 422);
        }

        if ($syncRange === 'full') {
            $syncService->resetUidTracking(
                $email !== '' ? $email : null,
                $email === '' ? $addresses : null
            );
        }

        $summary = [
            'success' => true,
            'sync_range' => $syncRange,
            'mailboxes' => [],
            'total_imported' => 0,
            'total_skipped' => 0,
            'total_failed' => 0,
        ];

        foreach ($addresses as $address) {
            $partial = $syncService->syncAll($address, $since);
            if (($partial['success'] ?? true) === false) {
                $summary['success'] = false;
                $summary['message'] = $partial['message'] ?? 'Inbox sync is disabled.';
            }
            foreach ($partial['mailboxes'] ?? [] as $mailboxEmail => $result) {
                $summary['mailboxes'][$mailboxEmail] = $result;
                $summary['total_imported'] += (int) ($result['imported'] ?? 0);
                $summary['total_skipped'] += (int) ($result['skipped'] ?? 0);
                $summary['total_failed'] += (int) ($result['failed'] ?? 0);
            }
        }

        return response()->json($summary);
    }

    protected function staffCanSyncInbox(): bool
    {
        $staff = Auth::guard('admin')->user();

        return $staff instanceof Staff && $staff->canSyncInboxEmails();
    }
}
