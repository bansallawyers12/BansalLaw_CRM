<?php

namespace App\Http\Controllers\AdminConsole;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Models\UserRole;
use App\Services\CrmAccess\CrmAccessService;
use App\Services\StaffMailboxService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class StaffController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:admin');
    }

    /**
     * Unified staff listing (SPA shell).
     */
    public function index(Request $request)
    {
        $tab = $this->normalizeStaffTab($request->query('tab', 'active'));
        $data = $this->buildStaffListPayload($request, $tab);

        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'success' => true,
                'tab' => $tab,
                'total' => $data['totalData'],
                'html' => view('AdminConsole.staff.partials.list-table', $data)->render(),
                'pagination' => view('AdminConsole.staff.partials.pagination', $data)->render(),
            ]);
        }

        return view('AdminConsole.staff.index', array_merge($data, [
            'tab' => $tab,
            'searchBy' => $request->query('search_by', ''),
        ]));
    }

    public function active(Request $request)
    {
        return $this->redirectToStaffIndex($request, 'active');
    }

    public function inactive(Request $request)
    {
        return $this->redirectToStaffIndex($request, 'inactive');
    }

    public function invited(Request $request)
    {
        return $this->redirectToStaffIndex($request, 'invited');
    }

    public function create(Request $request)
    {
        $check = $this->checkAuthorizationAction('user_management', $request->route()->getActionMethod(), Auth::user()->role);
        if ($check) {
            return $this->respondUnauthorized($request);
        }

        $usertype = UserRole::orderedForSelect();

        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'success' => true,
                'html' => view('AdminConsole.staff.partials.form-fields', [
                    'mode' => 'create',
                    'fetchedData' => null,
                    'usertype' => $usertype,
                    'fieldPrefix' => 'create_staff',
                ])->render(),
            ]);
        }

        return redirect()->route('adminconsole.staff.index', ['action' => 'create']);
    }

    public function store(Request $request)
    {
        $check = $this->checkAuthorizationAction('user_management', $request->route()->getActionMethod(), Auth::user()->role);
        if ($check) {
            return $this->respondUnauthorized($request);
        }

        if (!$request->isMethod('post')) {
            return redirect()->route('adminconsole.staff.index');
        }

        try {
            $requestData = $request->all();

            $this->validate($request, [
                'first_name' => 'required|max:255',
                'last_name' => 'required|max:255',
                'email' => 'required|email|max:255|unique:staff',
                'password' => 'required|string|min:8|max:255|confirmed',
                'phone' => 'required',
                'role' => 'required',
                'office' => 'required',
            ]);

            $storeActor = Auth::user();
            $canGrantEmailDelete = Staff::canGrantEmailDeleteWithAttachmentsPermission(
                $storeActor instanceof Staff ? $storeActor : null
            );
            if (! $canGrantEmailDelete && $request->has('can_delete_email_with_attachments')) {
                return $this->respondStaffMessage($request, 'Only Super Admin or Admin can grant email delete permission.', 422);
            }

            $canGrantInboxSync = Staff::canGrantInboxSyncPermission(
                $storeActor instanceof Staff ? $storeActor : null
            );
            if (! $canGrantInboxSync && $request->has('can_sync_inbox_emails')) {
                return $this->respondStaffMessage($request, 'Only Super Admin or Admin can grant inbox sync permission.', 422);
            }

            $canGrantCloseDiscontinue = Staff::canGrantCloseDiscontinueMatterPermission(
                $storeActor instanceof Staff ? $storeActor : null
            );
            if (! $canGrantCloseDiscontinue && $request->has('can_close_discontinue_matter')) {
                return $this->respondStaffMessage($request, 'Only Super Admin or Admin can grant close/discontinue matter permission.', 422);
            }

            $obj = new Staff();
            $this->fillStaffFromRequest($obj, $requestData, $request, isCreate: true);
            $saved = $obj->save();

            if (!$saved) {
                throw new \RuntimeException(config('constants.server_error'));
            }

            app(StaffMailboxService::class)->syncFromStaff(
                $obj->fresh(),
                $requestData['zoho_app_password'] ?? null
            );

            $loginUrl = url('/login');
            $message = "Staff added successfully. They can sign in at {$loginUrl} using this email address and the password you set.";

            return $this->respondStaffSaved($request, $message, $obj->fresh(['usertype', 'office']), 'active');
        } catch (ValidationException $e) {
            return $this->respondStaffValidationError($request, $e);
        } catch (\Exception $e) {
            return $this->respondStaffError($request, $e, 'An error occurred while creating the staff.');
        }
    }

    public function edit(Request $request, $id)
    {
        $check = $this->checkAuthorizationAction('user_management', 'edit', Auth::user()->role);
        if ($check) {
            return $this->respondUnauthorized($request);
        }

        $staff = $this->findStaffOrFail($id);
        if ($staff instanceof \Symfony\Component\HttpFoundation\Response) {
            return $staff;
        }

        $currentRoleId = isset($staff->role) ? (int) $staff->role : 0;
        $usertype = UserRole::orderedForSelect($currentRoleId > 0 ? $currentRoleId : null);

        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'success' => true,
                'staff' => $this->serializeStaffSummary($staff),
                'html' => view('AdminConsole.staff.partials.form-fields', [
                    'mode' => 'edit',
                    'fetchedData' => $staff,
                    'usertype' => $usertype,
                    'fieldPrefix' => 'edit_staff',
                ])->render(),
            ]);
        }

        return redirect()->route('adminconsole.staff.index', ['action' => 'edit', 'id' => $staff->id]);
    }

    public function update(Request $request, $id)
    {
        try {
            $check = $this->checkAuthorizationAction('user_management', 'update', Auth::user()->role);
            if ($check) {
                return $this->respondUnauthorized($request);
            }

            $staff = $this->findStaffOrFail($id);
            if ($staff instanceof \Symfony\Component\HttpFoundation\Response) {
                return $staff;
            }

            $requestData = $request->all();

            $this->validate($request, [
                'first_name' => 'required|max:255',
                'last_name' => 'required|max:255',
                'email' => 'required|email|max:255|unique:staff,email,' . $staff->id,
                'phone' => 'required|max:255',
                'password' => 'nullable|string|min:8|max:255|confirmed',
            ]);

            $crmAccess = app(CrmAccessService::class);
            $actor = Auth::user();
            $isSuperAdminActor = $actor instanceof Staff && (int) ($actor->role ?? 0) === 1;

            if (! Staff::canGrantEmailDeleteWithAttachmentsPermission($actor instanceof Staff ? $actor : null) && $request->has('can_delete_email_with_attachments')) {
                return $this->respondStaffMessage($request, 'Only Super Admin or Admin can grant email delete permission.', 422);
            }

            if (! Staff::canGrantInboxSyncPermission($actor instanceof Staff ? $actor : null) && $request->has('can_sync_inbox_emails')) {
                return $this->respondStaffMessage($request, 'Only Super Admin or Admin can grant inbox sync permission.', 422);
            }

            if (! Staff::canGrantCloseDiscontinueMatterPermission($actor instanceof Staff ? $actor : null) && $request->has('can_close_discontinue_matter')) {
                return $this->respondStaffMessage($request, 'Only Super Admin or Admin can grant close/discontinue matter permission.', 422);
            }

            if (! $isSuperAdminActor && $request->has('grant_super_admin_access')) {
                return $this->respondStaffMessage($request, 'Only Superadmin role user can provide this access.', 422);
            }

            if (! $isSuperAdminActor && $request->has('trust_rule42_supervisor')) {
                return $this->respondStaffMessage($request, 'Only Superadmin role user can set Rule 42 supervisor authority.', 422);
            }

            $prevQuickEnabled = (bool) ($staff->quick_access_enabled ?? false);
            $prevStatus = (int) ($staff->status ?? 1);
            $previousEmail = $staff->email;

            $this->fillStaffFromRequest($staff, $requestData, $request, isCreate: false);

            $saved = $staff->save();

            if ($saved) {
                app(StaffMailboxService::class)->syncFromStaff(
                    $staff->fresh(),
                    $requestData['zoho_app_password'] ?? null,
                    $previousEmail
                );
            }

            if ($saved && $prevStatus === 1 && (int) $staff->status === 0) {
                $crmAccess->revokeGrantsForStaff((int) $staff->id, 'Staff account deactivated');
            } elseif ($saved && $actor instanceof Staff && $crmAccess->canManageStaffQuickAccess($actor) && $prevQuickEnabled && ! $staff->quick_access_enabled) {
                $crmAccess->revokeGrantsForStaff((int) $staff->id, 'Quick access disabled');
            }

            if (!$saved) {
                throw new \RuntimeException(config('constants.server_error'));
            }

            $tab = (int) $staff->status === 1 ? 'active' : 'inactive';

            return $this->respondStaffSaved($request, 'Staff updated successfully.', $staff->fresh(['usertype', 'office']), $tab);
        } catch (ValidationException $e) {
            return $this->respondStaffValidationError($request, $e);
        } catch (\Exception $e) {
            return $this->respondStaffError($request, $e, 'An error occurred while updating the staff.', (int) $id);
        }
    }

    public function view(Request $request, $id)
    {
        $staff = $this->findStaffOrFail($id);
        if ($staff instanceof \Symfony\Component\HttpFoundation\Response) {
            return $staff;
        }

        $staff->load(['usertype', 'office']);

        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'success' => true,
                'html' => view('AdminConsole.staff.partials.view-body', [
                    'fetchedData' => $staff,
                ])->render(),
                'staff' => $this->serializeStaffSummary($staff),
            ]);
        }

        return redirect()->route('adminconsole.staff.index', ['action' => 'view', 'id' => $staff->id]);
    }

    public function savezone(Request $request)
    {
        if ($request->isMethod('post')) {
            $requestData = $request->all();
            $obj = Staff::find(@$requestData['user_id']);

            if (!$obj) {
                return redirect()->back()->with('error', 'Staff not found.');
            }

            $obj->time_zone = @$requestData['timezone'];
            $saved = $obj->save();

            if (!$saved) {
                return redirect()->back()->with('error', config('constants.server_error'));
            }

            return redirect()->route('adminconsole.staff.index', ['action' => 'view', 'id' => $requestData['user_id']])->with('success', 'Staff edited successfully.');
        }
    }

    protected function redirectToStaffIndex(Request $request, string $tab)
    {
        return redirect()->route('adminconsole.staff.index', array_filter([
            'tab' => $tab,
            'search_by' => $request->query('search_by'),
            'page' => $request->query('page'),
        ]));
    }

    protected function normalizeStaffTab(?string $tab): string
    {
        $tab = strtolower(trim((string) $tab));

        return in_array($tab, ['active', 'inactive', 'invited'], true) ? $tab : 'active';
    }

    protected function buildStaffListPayload(Request $request, string $tab): array
    {
        $searchBy = trim((string) $request->query('search_by', ''));

        $query = match ($tab) {
            'inactive' => Staff::where('status', 0),
            'invited' => Staff::query(),
            default => Staff::active(),
        };

        if ($searchBy !== '') {
            $searchLower = strtolower($searchBy);
            $query->where(function ($q) use ($searchLower) {
                $q->whereRaw('LOWER(first_name) LIKE ?', ['%' . $searchLower . '%'])
                    ->orWhereRaw('LOWER(last_name) LIKE ?', ['%' . $searchLower . '%'])
                    ->orWhereRaw('LOWER(email) LIKE ?', ['%' . $searchLower . '%']);
            });
        }

        $query->with(['usertype', 'office']);
        $totalData = (clone $query)->count();
        $lists = $query->orderBy('id', 'DESC')->paginate(config('constants.limit'))->appends([
            'tab' => $tab,
            'search_by' => $searchBy,
        ]);

        return [
            'lists' => $lists,
            'totalData' => $totalData,
            'tab' => $tab,
            'searchBy' => $searchBy,
        ];
    }

    protected function findStaffOrFail($id)
    {
        if (!isset($id) || $id === '' || !is_numeric($id) || (int) $id <= 0) {
            if (request()->ajax() || request()->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Invalid staff ID.'], 404);
            }

            return redirect()->route('adminconsole.staff.index')->with('error', 'Invalid staff ID.');
        }

        $staff = Staff::find((int) $id);
        if (!$staff) {
            if (request()->ajax() || request()->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Staff not found.'], 404);
            }

            return redirect()->route('adminconsole.staff.index')->with('error', 'Staff not found.');
        }

        return $staff;
    }

    protected function fillStaffFromRequest(Staff $obj, array $requestData, Request $request, bool $isCreate): void
    {
        $obj->first_name = @$requestData['first_name'];
        $obj->last_name = @$requestData['last_name'];
        $obj->email = @$requestData['email'];
        if (Schema::hasColumn('staff', 'email_signature')) {
            $obj->email_signature = $requestData['email_signature'] ?? null;
        }
        $obj->country_code = @$requestData['country_code'];
        $obj->position = @$requestData['position'];
        $obj->phone = @$requestData['phone'];
        $obj->role = @$requestData['role'];
        $obj->office_id = @$requestData['office'];
        $obj->team = @$requestData['team'];
        $obj->show_dashboard_per = isset($requestData['show_dashboard_per']) ? 1 : 0;
        $obj->permission = (isset($requestData['permission']) && is_array($requestData['permission']))
            ? implode(',', $requestData['permission'])
            : '';

        $isSolicitor = $this->requestIndicatesSolicitor($requestData);
        $obj->is_solicitor = $isSolicitor ? 1 : 0;

        if ($isSolicitor) {
            $obj->marn_number = @$requestData['marn_number'];
            $obj->company_name = @$requestData['company_name'];
            $obj->business_address = @$requestData['business_address'];
            $obj->business_phone = @$requestData['business_phone'];
            $obj->business_mobile = @$requestData['business_mobile'];
            $obj->business_email = @$requestData['business_email'];
            $obj->tax_number = @$requestData['tax_number'];
        } elseif (!$isCreate) {
            $obj->marn_number = null;
            $obj->company_name = null;
            $obj->business_address = null;
            $obj->business_phone = null;
            $obj->business_mobile = null;
            $obj->business_email = null;
            $obj->tax_number = null;
        }

        if ($isCreate) {
            $obj->password = Hash::make(@$requestData['password']);
            $obj->status = isset($requestData['status']) ? (int) $requestData['status'] : 1;
        } else {
            if (!empty($requestData['password'] ?? '')) {
                $obj->password = Hash::make($requestData['password']);
            }
            $obj->status = isset($requestData['status']) ? (int) $requestData['status'] : 1;
        }

        $actor = Auth::user();
        if ((int) ($obj->role ?? 0) === 14) {
            $obj->quick_access_enabled = true;
        } elseif ($actor instanceof Staff && app(CrmAccessService::class)->canManageStaffQuickAccess($actor)) {
            $obj->quick_access_enabled = $request->boolean('quick_access_enabled');
        }

        $isSuperAdminActor = $actor instanceof Staff && (int) ($actor->role ?? 0) === 1;
        if ($isSuperAdminActor && !$isCreate) {
            $obj->grant_super_admin_access = $request->boolean('grant_super_admin_access') ? 1 : null;
        }

        if ($isSuperAdminActor && Schema::hasColumn('staff', 'trust_rule42_supervisor')) {
            $obj->trust_rule42_supervisor = $request->boolean('trust_rule42_supervisor');
        }

        $canGrantEmailDelete = Staff::canGrantEmailDeleteWithAttachmentsPermission(
            $actor instanceof Staff ? $actor : null
        );
        if ($canGrantEmailDelete && Schema::hasColumn('staff', 'can_delete_email_with_attachments')) {
            $obj->can_delete_email_with_attachments = $request->boolean('can_delete_email_with_attachments');
        }

        $canGrantInboxSync = Staff::canGrantInboxSyncPermission(
            $actor instanceof Staff ? $actor : null
        );
        if ($canGrantInboxSync && Schema::hasColumn('staff', 'can_sync_inbox_emails')) {
            $obj->can_sync_inbox_emails = $request->boolean('can_sync_inbox_emails');
        }

        $canGrantCloseDiscontinue = Staff::canGrantCloseDiscontinueMatterPermission(
            $actor instanceof Staff ? $actor : null
        );
        if ($canGrantCloseDiscontinue && Schema::hasColumn('staff', 'can_close_discontinue_matter')) {
            $obj->can_close_discontinue_matter = $request->boolean('can_close_discontinue_matter');
        }
    }

    protected function serializeStaffSummary(Staff $staff): array
    {
        return [
            'id' => $staff->id,
            'name' => trim($staff->first_name . ' ' . $staff->last_name),
            'email' => $staff->email,
            'status' => (int) $staff->status,
        ];
    }

    protected function respondUnauthorized(Request $request)
    {
        if ($request->ajax() || $request->expectsJson()) {
            return response()->json(['success' => false, 'message' => config('constants.unauthorized')], 403);
        }

        return Redirect::to('/dashboard')->with('error', config('constants.unauthorized'));
    }

    protected function respondStaffMessage(Request $request, string $message, int $status = 422)
    {
        if ($request->ajax() || $request->expectsJson()) {
            return response()->json(['success' => false, 'message' => $message], $status);
        }

        return redirect()->back()->withInput()->with('error', $message);
    }

    protected function respondStaffValidationError(Request $request, ValidationException $e)
    {
        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first() ?: 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        }

        return redirect()->back()->withErrors($e->validator)->withInput();
    }

    protected function respondStaffError(Request $request, \Exception $e, string $message, ?int $staffId = null)
    {
        Log::error('Staff save error: ' . $e->getMessage(), [
            'staff_id' => $staffId,
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);

        $errorMsg = $message;
        if (config('app.debug')) {
            $errorMsg .= ' (' . $e->getMessage() . ')';
        }

        if ($request->ajax() || $request->expectsJson()) {
            return response()->json(['success' => false, 'message' => $errorMsg], 500);
        }

        return redirect()->back()->withInput()->with('error', $errorMsg);
    }

    protected function respondStaffSaved(Request $request, string $message, Staff $staff, string $tab)
    {
        $staff->load(['usertype', 'office']);

        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'tab' => $tab,
                'staff' => $this->serializeStaffSummary($staff),
                'row_html' => view('AdminConsole.staff.partials.row', [
                    'list' => $staff,
                    'tab' => $tab,
                ])->render(),
            ]);
        }

        return redirect()->route('adminconsole.staff.index', ['tab' => $tab])->with('success', $message);
    }

    private function requestIndicatesSolicitor(array $requestData): bool
    {
        return isset($requestData['is_solicitor']) || isset($requestData['is_migration_agent']);
    }
}
