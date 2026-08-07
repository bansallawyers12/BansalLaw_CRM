<?php

namespace App\Http\Controllers\AdminConsole;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Models\ZohoCalendarConnection;
use App\Models\ZohoCalendarEventLink;
use App\Models\ZohoCalendarStaffMap;
use App\Models\ZohoCalendarUnlinkedEvent;
use App\Services\CalendarSync\CalendarSyncMasterControl;
use App\Services\CalendarSync\UnlinkedCalendarAssignmentService;
use App\Services\CalendarSync\ZohoCalendarApiClient;
use App\Services\CalendarSync\ZohoCalendarOAuthService;
use App\Services\CalendarSync\ZohoToCrmCalendarSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;

class CalendarSyncController extends Controller
{
    public function __construct(
        protected ZohoCalendarOAuthService $oauth,
        protected ZohoCalendarApiClient $api
    ) {
        $this->middleware('auth:admin');
    }

    public function index(Request $request)
    {
        $staff = $this->requireSuperAdmin();
        if ($staff instanceof \Illuminate\Http\RedirectResponse) {
            return $staff;
        }

        $canControl = true;
        $master = CalendarSyncMasterControl::statusPayload();
        $oauthConfigured = $this->oauth->isConfigured();

        $staffAccounts = collect();
        $staffWithoutAccount = collect();
        $unlinkedEvents = collect();
        $unlinkedOpenCount = 0;
        $recentLinks = collect();

        try {
            $staffAccounts = ZohoCalendarStaffMap::query()
                ->with(['staff', 'connection'])
                ->orderByDesc('sync_enabled')
                ->orderByDesc('is_org_default')
                ->orderBy('id')
                ->get();

            $mappedIds = $staffAccounts->pluck('staff_id')->all();
            $staffWithoutAccount = Staff::query()
                ->where('status', 1)
                ->when($mappedIds !== [], fn ($q) => $q->whereNotIn('id', $mappedIds))
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->get(['id', 'first_name', 'last_name', 'email']);

            $unlinkedOpenCount = ZohoToCrmCalendarSyncService::openUnlinkedCount();
            $unlinkedEvents = ZohoCalendarUnlinkedEvent::query()
                ->open()
                ->orderBy('starts_at')
                ->limit(50)
                ->get();
            $recentLinks = ZohoCalendarEventLink::query()
                ->orderByDesc('id')
                ->limit(25)
                ->get();
        } catch (\Throwable $e) {
            Log::debug('Calendar sync index data load issue', ['error' => $e->getMessage()]);
        }

        // Load calendars for connected accounts (for dropdowns)
        $calendarsByStaff = [];
        foreach ($staffAccounts as $account) {
            if (! $account->connection || ! $account->isConnected()) {
                continue;
            }
            try {
                $calendarsByStaff[$account->staff_id] = $this->api->listCalendars($account->connection);
            } catch (\Throwable $e) {
                $calendarsByStaff[$account->staff_id] = [];
                $account->setAttribute('calendar_list_error', $e->getMessage());
            }
        }

        return view('AdminConsole.features.calendar_sync.index', compact([
            'canControl',
            'master',
            'oauthConfigured',
            'staffAccounts',
            'staffWithoutAccount',
            'calendarsByStaff',
            'unlinkedEvents',
            'unlinkedOpenCount',
            'recentLinks',
        ]));
    }

    public function updateMaster(Request $request)
    {
        $staff = $this->requireSuperAdmin();
        if ($staff instanceof \Illuminate\Http\RedirectResponse) {
            return $staff;
        }

        $enabled = $request->boolean('enabled');
        CalendarSyncMasterControl::setEnabled($enabled, $staff);

        return Redirect::back()->with(
            'success',
            $enabled
                ? 'Zoho calendar sync is ON for staff accounts that have credentials + Connect completed.'
                : 'Zoho calendar sync is OFF. No push or pull will run.'
        );
    }

    /**
     * Add / update a staff calendar credential row (like adding an email account).
     */
    public function storeStaffCredential(Request $request)
    {
        $actor = $this->requireSuperAdmin();
        if ($actor instanceof \Illuminate\Http\RedirectResponse) {
            return $actor;
        }

        $validated = $request->validate([
            'staff_id' => 'required|integer|exists:staff,id',
            'zoho_email' => 'nullable|email|max:255',
            'display_name' => 'nullable|string|max:150',
            'zoho_calendar_uid' => 'nullable|string|max:128',
            'sync_enabled' => 'sometimes|boolean',
            'is_org_default' => 'sometimes|boolean',
            'notes' => 'nullable|string|max:500',
        ]);

        $map = ZohoCalendarStaffMap::query()->firstOrNew(['staff_id' => (int) $validated['staff_id']]);
        $map->fill([
            'staff_id' => (int) $validated['staff_id'],
            'zoho_email' => $validated['zoho_email'] ?? $map->zoho_email,
            'display_name' => $validated['display_name'] ?? $map->display_name,
            'zoho_calendar_uid' => $validated['zoho_calendar_uid'] ?? $map->zoho_calendar_uid,
            'sync_enabled' => $request->boolean('sync_enabled'),
            'is_org_default' => $request->boolean('is_org_default'),
            'notes' => $validated['notes'] ?? $map->notes,
        ]);

        // Auto-fill zoho_email from staff if empty
        if (! filled($map->zoho_email)) {
            $target = Staff::query()->find((int) $validated['staff_id']);
            $map->zoho_email = $target?->email;
        }

        if ($map->is_org_default) {
            ZohoCalendarStaffMap::query()
                ->where('staff_id', '!=', $map->staff_id)
                ->update(['is_org_default' => false]);
        }

        $map->save();

        return Redirect::back()->with(
            'success',
            'Staff calendar credential saved for staff #' . $map->staff_id . '. Click Connect Zoho and sign in as that person’s Zoho Mail account.'
        );
    }

    public function updateStaffCredential(Request $request, int $staffId)
    {
        $actor = $this->requireSuperAdmin();
        if ($actor instanceof \Illuminate\Http\RedirectResponse) {
            return $actor;
        }

        $map = ZohoCalendarStaffMap::query()->where('staff_id', $staffId)->firstOrFail();

        $validated = $request->validate([
            'zoho_email' => 'nullable|email|max:255',
            'display_name' => 'nullable|string|max:150',
            'zoho_calendar_uid' => 'nullable|string|max:128',
            'sync_enabled' => 'sometimes|boolean',
            'is_org_default' => 'sometimes|boolean',
            'notes' => 'nullable|string|max:500',
        ]);

        $map->fill([
            'zoho_email' => $validated['zoho_email'] ?? $map->zoho_email,
            'display_name' => $validated['display_name'] ?? $map->display_name,
            'notes' => $validated['notes'] ?? $map->notes,
        ]);

        if (array_key_exists('zoho_calendar_uid', $validated)) {
            $map->zoho_calendar_uid = $validated['zoho_calendar_uid'] ?: null;
        }

        $map->sync_enabled = $request->has('sync_enabled')
            ? $request->boolean('sync_enabled')
            : $map->sync_enabled;
        $map->is_org_default = $request->has('is_org_default')
            ? $request->boolean('is_org_default')
            : $map->is_org_default;

        if ($map->is_org_default) {
            ZohoCalendarStaffMap::query()
                ->where('staff_id', '!=', $map->staff_id)
                ->update(['is_org_default' => false]);
        }

        // Keep connection default calendar in sync when set on this form
        if (filled($map->zoho_calendar_uid)) {
            ZohoCalendarConnection::query()
                ->where('staff_id', $map->staff_id)
                ->update(['default_calendar_uid' => $map->zoho_calendar_uid]);
        }

        $map->save();

        return Redirect::back()->with('success', 'Calendar credentials updated for this staff member.');
    }

    public function deleteStaffCredential(Request $request, int $staffId)
    {
        $actor = $this->requireSuperAdmin();
        if ($actor instanceof \Illuminate\Http\RedirectResponse) {
            return $actor;
        }

        ZohoCalendarStaffMap::query()->where('staff_id', $staffId)->delete();
        ZohoCalendarConnection::query()->where('staff_id', $staffId)->delete();

        return Redirect::back()->with('success', 'Staff calendar credentials and Zoho tokens removed for staff #' . $staffId . '.');
    }

    /**
     * Start OAuth for a staff member (Super Admin only — signs into that user’s Zoho).
     */
    public function connect(Request $request)
    {
        $actor = $this->requireSuperAdmin();
        if ($actor instanceof \Illuminate\Http\RedirectResponse) {
            return $actor;
        }

        $targetStaffId = (int) $request->query('staff_id', $actor->id);

        if (! $this->oauth->isConfigured()) {
            return Redirect::route('adminconsole.features.calendarsync.index')
                ->with('error', 'Set ZOHO_CALENDAR_CLIENT_ID and ZOHO_CALENDAR_CLIENT_SECRET in .env first (org-level API app).');
        }

        $target = Staff::query()->find($targetStaffId);
        if (! $target) {
            return Redirect::back()->with('error', 'Staff not found.');
        }

        // Ensure a credential row exists so sync can be toggled after connect
        ZohoCalendarStaffMap::query()->firstOrCreate(
            ['staff_id' => $targetStaffId],
            [
                'zoho_email' => $target->email,
                'sync_enabled' => true,
            ]
        );

        try {
            $state = $this->oauth->makeState();
            $request->session()->put('zoho_calendar_oauth_state', $state);
            $request->session()->put('zoho_calendar_oauth_staff_id', $targetStaffId);

            return Redirect::away($this->oauth->authorizationUrl($state));
        } catch (\Throwable $e) {
            return Redirect::route('adminconsole.features.calendarsync.index')
                ->with('error', $e->getMessage());
        }
    }

    public function callback(Request $request)
    {
        $actor = $this->requireSuperAdmin();
        if ($actor instanceof \Illuminate\Http\RedirectResponse) {
            return $actor;
        }

        $expectedState = $request->session()->pull('zoho_calendar_oauth_state');
        $targetStaffId = (int) $request->session()->pull('zoho_calendar_oauth_staff_id', $actor->id);
        $state = (string) $request->query('state', '');

        if (! $expectedState || ! hash_equals((string) $expectedState, $state)) {
            return Redirect::route('adminconsole.features.calendarsync.index')
                ->with('error', 'Invalid OAuth state. Try Connect Zoho again.');
        }

        if ($request->query('error')) {
            return Redirect::route('adminconsole.features.calendarsync.index')
                ->with('error', 'Zoho denied access: ' . $request->query('error'));
        }

        $code = (string) $request->query('code', '');
        if ($code === '') {
            return Redirect::route('adminconsole.features.calendarsync.index')
                ->with('error', 'Zoho did not return an authorization code.');
        }

        $target = Staff::query()->find($targetStaffId);
        if (! $target) {
            return Redirect::route('adminconsole.features.calendarsync.index')
                ->with('error', 'Target staff not found.');
        }

        try {
            $tokens = $this->oauth->exchangeCode($code);

            $connection = ZohoCalendarConnection::query()->firstOrNew(['staff_id' => $targetStaffId]);
            $connection->staff_id = $targetStaffId;
            $connection->zoho_email = $target->email ?? $connection->zoho_email;
            $connection->connected_at = now();
            $this->oauth->storeTokens($connection, $tokens);

            $map = ZohoCalendarStaffMap::query()->firstOrCreate(
                ['staff_id' => $targetStaffId],
                ['zoho_email' => $target->email, 'sync_enabled' => true]
            );
            if (! filled($map->zoho_email)) {
                $map->zoho_email = $target->email;
            }

            try {
                $calendars = $this->api->listCalendars($connection->fresh());
                if ($calendars !== []) {
                    $default = collect($calendars)->firstWhere('is_default', true) ?? $calendars[0];
                    $uid = $default['uid'] ?? null;
                    if ($uid) {
                        $connection->default_calendar_uid = $uid;
                        $connection->save();
                        if (! filled($map->zoho_calendar_uid)) {
                            $map->zoho_calendar_uid = $uid;
                        }
                    }
                }
            } catch (\Throwable $e) {
                Log::info('Zoho calendars list after OAuth deferred', [
                    'staff_id' => $targetStaffId,
                    'error' => $e->getMessage(),
                ]);
            }

            $map->last_error = null;
            $map->save();

            $name = trim(($target->first_name ?? '') . ' ' . ($target->last_name ?? '')) ?: ('#' . $targetStaffId);

            return Redirect::route('adminconsole.features.calendarsync.index')
                ->with('success', 'Zoho connected for ' . $name . '. Choose their calendar below if needed, keep Sync ON.');
        } catch (\Throwable $e) {
            Log::warning('Zoho calendar OAuth callback failed', ['error' => $e->getMessage()]);

            return Redirect::route('adminconsole.features.calendarsync.index')
                ->with('error', 'OAuth failed: ' . $e->getMessage());
        }
    }

    public function disconnectStaff(Request $request, int $staffId)
    {
        $actor = $this->requireSuperAdmin();
        if ($actor instanceof \Illuminate\Http\RedirectResponse) {
            return $actor;
        }

        ZohoCalendarConnection::query()->where('staff_id', $staffId)->delete();
        ZohoCalendarStaffMap::query()->where('staff_id', $staffId)->update([
            'last_error' => 'Disconnected — reconnect to resume sync.',
        ]);

        return Redirect::back()->with('success', 'Zoho OAuth tokens removed for staff #' . $staffId . '. Credential row kept — re-connect when ready.');
    }

    public function syncNow(Request $request, ZohoToCrmCalendarSyncService $syncService)
    {
        $staff = $this->requireSuperAdmin();
        if ($staff instanceof \Illuminate\Http\RedirectResponse) {
            return $staff;
        }

        if (CalendarSyncMasterControl::isDisabled()) {
            return Redirect::back()->with('error', CalendarSyncMasterControl::disabledMessage());
        }

        try {
            $summary = $syncService->syncAll();
            $msg = sprintf(
                'Zoho pull done. Scanned %d · linked %d · auto %d · queued %d · retry out %d.',
                $summary['scanned'],
                $summary['linked_seen'],
                $summary['auto_linked'],
                $summary['unlinked_queued'],
                $summary['outbound_retried']
            );
            if ($summary['errors'] !== []) {
                return Redirect::back()->with('error', $msg . ' Issues: ' . implode('; ', array_slice($summary['errors'], 0, 3)));
            }

            return Redirect::back()->with('success', $msg);
        } catch (\Throwable $e) {
            return Redirect::back()->with('error', 'Sync failed: ' . $e->getMessage());
        }
    }

    public function assignUnlinked(Request $request, UnlinkedCalendarAssignmentService $assignmentService)
    {
        $staff = $this->requireSuperAdmin();
        if ($staff instanceof \Illuminate\Http\RedirectResponse) {
            return $staff;
        }

        $validated = $request->validate([
            'unlinked_id' => 'required|integer|exists:zoho_calendar_unlinked_events,id',
            'client_id' => 'required|integer|exists:admins,id',
            'client_matter_id' => 'nullable|integer|exists:client_matters,id',
            'event_type' => 'nullable|in:court,meeting,deadline,reminder,other',
        ]);

        $unlinked = ZohoCalendarUnlinkedEvent::query()->findOrFail($validated['unlinked_id']);

        try {
            $event = $assignmentService->assignToClient(
                $unlinked,
                (int) $validated['client_id'],
                isset($validated['client_matter_id']) ? (int) $validated['client_matter_id'] : null,
                $staff,
                $validated['event_type'] ?? 'meeting'
            );

            return Redirect::back()->with(
                'success',
                'Assigned to CRM staff event #' . $event->id . ' (client #' . $validated['client_id'] . ').'
            );
        } catch (\Throwable $e) {
            return Redirect::back()->with('error', $e->getMessage());
        }
    }

    public function dismissUnlinked(Request $request, UnlinkedCalendarAssignmentService $assignmentService)
    {
        $staff = $this->requireSuperAdmin();
        if ($staff instanceof \Illuminate\Http\RedirectResponse) {
            return $staff;
        }

        $validated = $request->validate([
            'unlinked_id' => 'required|integer|exists:zoho_calendar_unlinked_events,id',
        ]);

        $unlinked = ZohoCalendarUnlinkedEvent::query()->findOrFail($validated['unlinked_id']);
        $assignmentService->dismiss($unlinked, $staff);

        return Redirect::back()->with('success', 'Unlinked event dismissed.');
    }

    /**
     * @return Staff|\Illuminate\Http\RedirectResponse
     */
    protected function requireSuperAdmin()
    {
        $staff = Auth::guard('admin')->user();
        if (! ($staff instanceof Staff) || ! CalendarSyncMasterControl::canControl($staff)) {
            return Redirect::route('adminconsole.features.emails.index')
                ->with('error', 'Only Super Admin can manage or view calendar sync.');
        }

        return $staff;
    }
}
