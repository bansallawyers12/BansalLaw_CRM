<?php

namespace App\Http\Controllers\AdminConsole;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Models\UserRole;
use App\Support\CrmSheets;
use App\Services\CrmAccess\CrmAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;

class StaffController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:admin');
    }

    /**
     * Staff listing index (redirects to active).
     */
    public function index(Request $request)
    {
        return redirect()->route('adminconsole.staff.active');
    }

    /**
     * Active staff list.
     */
    public function active(Request $request)
    {
        $req_data = $request->all();
        $search_by = $req_data['search_by'] ?? '';

        if ($search_by) {
            $query = Staff::active()
                ->where(function ($q) use ($search_by) {
                    $searchLower = strtolower($search_by);
                    $q->whereRaw('LOWER(first_name) LIKE ?', ['%' . $searchLower . '%'])
                        ->orWhereRaw('LOWER(last_name) LIKE ?', ['%' . $searchLower . '%'])
                        ->orWhereRaw('LOWER(email) LIKE ?', ['%' . $searchLower . '%']);
                })
                ->with(['usertype', 'office']);
        } else {
            $query = Staff::active()->with(['usertype', 'office']);
        }

        $totalData = $query->count();
        $lists = $query->orderBy('id', 'DESC')->paginate(config('constants.limit'));

        return view('AdminConsole.staff.active', compact(['lists', 'totalData']));
    }

    /**
     * Inactive staff list.
     */
    public function inactive(Request $request)
    {
        $query = Staff::where('status', 0)->with(['usertype', 'office']);
        $totalData = $query->count();
        $lists = $query->orderBy('id', 'DESC')->paginate(config('constants.limit'));

        return view('AdminConsole.staff.inactive', compact(['lists', 'totalData']));
    }

    /**
     * All staff (invited).
     */
    public function invited(Request $request)
    {
        $query = Staff::with(['usertype', 'office']);
        $totalData = $query->count();
        $lists = $query->orderBy('id', 'DESC')->paginate(config('constants.limit'));

        return view('AdminConsole.staff.invited', compact(['lists', 'totalData']));
    }

    /**
     * Show create staff form.
     */
    public function create(Request $request)
    {
        $check = $this->checkAuthorizationAction('user_management', $request->route()->getActionMethod(), Auth::user()->role);
        if ($check) {
            return Redirect::to('/dashboard')->with('error', config('constants.unauthorized'));
        }

        $usertype = UserRole::all();
        $sheetDefinitions = CrmSheets::definitions();
        $selectedSheetKeys = array_keys($sheetDefinitions);

        return view('AdminConsole.staff.create', compact(['usertype', 'sheetDefinitions', 'selectedSheetKeys']));
    }

    /**
     * Store new staff.
     */
    public function store(Request $request)
    {
        $check = $this->checkAuthorizationAction('user_management', $request->route()->getActionMethod(), Auth::user()->role);
        if ($check) {
            return Redirect::to('/dashboard')->with('error', config('constants.unauthorized'));
        }

        if ($request->isMethod('post')) {
            $requestData = $request->all();

            $this->validate($request, [
                'first_name' => 'required|max:255',
                'last_name' => 'required|max:255',
                'email' => 'required|email|max:255|unique:staff',
                'password' => 'required|max:255|confirmed',
                'phone' => 'required',
                'role' => 'required',
                'office' => 'required',
            ]);

            $obj = new Staff();
            $obj->first_name = @$requestData['first_name'];
            $obj->last_name = @$requestData['last_name'];
            $obj->email = @$requestData['email'];
            $obj->country_code = @$requestData['country_code'];
            $obj->position = @$requestData['position'];
            $obj->password = Hash::make(@$requestData['password']);
            $obj->phone = @$requestData['phone'];
            $obj->role = @$requestData['role'];
            if ((int) $obj->role === 14) {
                // Calling Team always gets quick access auto-enabled
                $obj->quick_access_enabled = true;
            } else {
                $storeActor = Auth::user();
                if ($storeActor instanceof Staff && app(CrmAccessService::class)->canManageStaffQuickAccess($storeActor)) {
                    $obj->quick_access_enabled = $request->boolean('quick_access_enabled');
                }
            }
            $obj->office_id = @$requestData['office'];
            $obj->team = @$requestData['team'];
            $obj->show_dashboard_per = isset($requestData['show_dashboard_per']) ? 1 : 0;
            $obj->permission = (isset($requestData['permission']) && is_array($requestData['permission']))
                ? implode(',', $requestData['permission'])
                : '';
            $obj->sheet_access = $this->normalizeStaffSheetAccess($requestData['sheet_access'] ?? null);
            $obj->is_migration_agent = isset($requestData['is_migration_agent']) ? 1 : 0;

            if (isset($requestData['is_migration_agent'])) {
                $obj->marn_number = @$requestData['marn_number'];
                $obj->company_name = @$requestData['company_name'];
                $obj->business_address = @$requestData['business_address'];
                $obj->business_phone = @$requestData['business_phone'];
                $obj->business_mobile = @$requestData['business_mobile'];
                $obj->business_email = @$requestData['business_email'];
                $obj->tax_number = @$requestData['tax_number'];
            }

            $obj->status = isset($requestData['status']) ? (int) $requestData['status'] : 1;

            $saved = $obj->save();

            if (!$saved) {
                return redirect()->back()->with('error', config('constants.server_error'));
            }

            return redirect()->route('adminconsole.staff.active')->with('success', 'Staff added successfully.');
        }

        return redirect()->route('adminconsole.staff.create');
    }

    /**
     * Show edit staff form.
     */
    public function edit($id)
    {
        $check = $this->checkAuthorizationAction('user_management', 'edit', Auth::user()->role);
        if ($check) {
            return Redirect::to('/dashboard')->with('error', config('constants.unauthorized'));
        }

        $usertype = UserRole::all();

        if (!isset($id) || $id === '' || !is_numeric($id) || (int) $id <= 0) {
            return redirect()->route('adminconsole.staff.active')->with('error', 'Invalid staff ID.');
        }

        $id = (int) $id;
        $fetchedData = Staff::find($id);

        if (!$fetchedData) {
            return redirect()->route('adminconsole.staff.active')->with('error', 'Staff not found.');
        }

        $sheetDefinitions = CrmSheets::definitions();
        $allSheetKeys = array_keys($sheetDefinitions);
        $rawSheets = $fetchedData->sheet_access ?? null;
        if ($rawSheets === null || $rawSheets === '') {
            $selectedSheetKeys = $allSheetKeys;
        } else {
            $decoded = json_decode($rawSheets, true);
            if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
                $selectedSheetKeys = [];
            } else {
                $selectedSheetKeys = array_values(array_intersect($decoded, $allSheetKeys));
            }
        }

        return view('AdminConsole.staff.edit', compact(['fetchedData', 'usertype', 'sheetDefinitions', 'selectedSheetKeys']));
    }

    /**
     * Update staff.
     */
    public function update(Request $request, $id)
    {
        try {
            $check = $this->checkAuthorizationAction('user_management', 'update', Auth::user()->role);
            if ($check) {
                return Redirect::to('/dashboard')->with('error', config('constants.unauthorized'));
            }

            if (!isset($id) || $id === '' || !is_numeric($id) || (int) $id <= 0) {
                return redirect()->route('adminconsole.staff.active')->with('error', 'Invalid staff ID.');
            }

            $id = (int) $id;
            $requestData = $request->all();

            $this->validate($request, [
                'first_name' => 'required|max:255',
                'last_name' => 'required|max:255',
                'phone' => 'required|max:255',
            ]);

            $obj = Staff::find($id);
            if (!$obj) {
                return redirect()->route('adminconsole.staff.active')->with('error', 'Staff not found.');
            }

            $crmAccess = app(CrmAccessService::class);
            $actor = Auth::user();
            $isSuperAdminActor = $actor instanceof Staff && (int) ($actor->role ?? 0) === 1;

            if (! $isSuperAdminActor && $request->has('grant_super_admin_access')) {
                return redirect()->back()->withInput()->with('error', 'Only Superadmin role user can provide this access.');
            }

            $prevQuickEnabled = (bool) ($obj->quick_access_enabled ?? false);
            $prevStatus = (int) ($obj->status ?? 1);

            $obj->first_name = @$requestData['first_name'];
            $obj->last_name = @$requestData['last_name'];
            $obj->email = @$requestData['email'];
            $obj->country_code = @$requestData['country_code'];
            $obj->position = @$requestData['position'];
            $obj->phone = @$requestData['phone'];
            $prevRole = (int) ($obj->role ?? 0);
            $obj->role = @$requestData['role'];
            if ((int) $obj->role === 14 && $prevRole !== 14) {
                $obj->quick_access_enabled = true;
            }
            $obj->office_id = @$requestData['office'];
            $obj->team = @$requestData['team'];
            $obj->permission = (isset($requestData['permission']) && is_array($requestData['permission']))
                ? implode(',', $requestData['permission'])
                : '';
            $obj->sheet_access = $this->normalizeStaffSheetAccess($requestData['sheet_access'] ?? null);
            $obj->show_dashboard_per = isset($requestData['show_dashboard_per']) ? 1 : 0;
            $obj->is_migration_agent = isset($requestData['is_migration_agent']) ? 1 : 0;

            if (isset($requestData['is_migration_agent'])) {
                $obj->marn_number = @$requestData['marn_number'];
                $obj->company_name = @$requestData['company_name'];
                $obj->business_address = @$requestData['business_address'];
                $obj->business_phone = @$requestData['business_phone'];
                $obj->business_mobile = @$requestData['business_mobile'];
                $obj->business_email = @$requestData['business_email'];
                $obj->tax_number = @$requestData['tax_number'];
            } else {
                $obj->marn_number = null;
                $obj->company_name = null;
                $obj->business_address = null;
                $obj->business_phone = null;
                $obj->business_mobile = null;
                $obj->business_email = null;
                $obj->tax_number = null;
            }

            if (!empty(@$requestData['password'])) {
                $obj->password = Hash::make(@$requestData['password']);
            }

            if ($actor instanceof Staff && $crmAccess->canManageStaffQuickAccess($actor)) {
                $obj->quick_access_enabled = $request->boolean('quick_access_enabled');
            }

            if ($isSuperAdminActor) {
                $obj->grant_super_admin_access = $request->boolean('grant_super_admin_access') ? 1 : null;
            }

            $saved = $obj->save();

            if ($saved && $prevStatus === 1 && (int) $obj->status === 0) {
                $crmAccess->revokeGrantsForStaff((int) $obj->id, 'Staff account deactivated');
            } elseif ($saved && $actor instanceof Staff && $crmAccess->canManageStaffQuickAccess($actor) && $prevQuickEnabled && ! $obj->quick_access_enabled) {
                $crmAccess->revokeGrantsForStaff((int) $obj->id, 'Quick access disabled');
            }

            if (!$saved) {
                return redirect()->back()->with('error', config('constants.server_error'));
            }

            return redirect()->route('adminconsole.staff.view', $id)->with('success', 'Staff updated successfully.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()->withErrors($e->validator)->withInput();
        } catch (\Exception $e) {
            Log::error('Staff Update Error: ' . $e->getMessage(), [
                'staff_id' => $id,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return redirect()->back()->with('error', 'An error occurred while updating the staff.');
        }
    }

    /**
     * Save timezone (legacy).
     */
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

            return redirect()->route('adminconsole.staff.view', $requestData['user_id'])->with('success', 'Staff edited successfully.');
        }
    }

    /**
     * View staff details.
     */
    public function view(Request $request, $id)
    {
        if (!isset($id) || $id === '' || !is_numeric($id) || (int) $id <= 0) {
            return redirect()->route('adminconsole.staff.active')->with('error', 'Invalid staff ID.');
        }

        $id = (int) $id;
        $fetchedData = Staff::with(['usertype', 'office'])->find($id);

        if (!$fetchedData) {
            return redirect()->route('adminconsole.staff.active')->with('error', 'Staff not found.');
        }

        return view('AdminConsole.staff.view', compact(['fetchedData']));
    }

    /**
     * null = all sheets (legacy / full access). JSON array = whitelist.
     *
     * @param  mixed  $input
     */
    private function normalizeStaffSheetAccess($input): ?string
    {
        $allowed = CrmSheets::keys();
        $selected = is_array($input) ? $input : [];
        $selected = array_values(array_unique(array_intersect(array_map('strval', $selected), $allowed)));
        sort($selected);
        $allSorted = $allowed;
        sort($allSorted);
        if ($selected === $allSorted) {
            return null;
        }

        return json_encode($selected);
    }
}
