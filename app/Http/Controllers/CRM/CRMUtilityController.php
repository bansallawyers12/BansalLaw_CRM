<?php
namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Concerns\EnsuresCrmRecordAccess;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

use App\Models\Lead;
use App\Models\Admin;
use App\Models\Staff;
// use App\Models\WebsiteSetting; // removed website settings dependency
// use App\Models\State; // REMOVED: State model has been deleted
use PDF;
use Auth;
use App\Models\Note;
use App\Models\ClientMatter;
use Carbon\Carbon;
use App\Services\ComposeMatterDocumentService;
use App\Services\EmailService;
use App\Services\CrmSentEmailS3Service;
use App\Support\ClientActivity;
use App\Support\EmailTimelineActivity;
use App\Support\WorkflowStageFreeze;

class CRMUtilityController extends Controller
{
    private function viewerCanMutateAnyRecord(): bool
    {
        $u = Auth::user();
        if ($u instanceof Staff && $u->hasEffectiveSuperAdminPrivileges()) {
            return true;
        }
        // Client-portal and lead users are allowed via their user type (unchanged legacy behaviour)
        return in_array($u->type ?? '', ['client', 'lead'], true);
    }

    use EnsuresCrmRecordAccess;

    protected $emailService;
    protected $crmSentEmailS3Service;
    protected $composeMatterDocumentService;

    public function __construct(
        EmailService $emailService,
        CrmSentEmailS3Service $crmSentEmailS3Service,
        ComposeMatterDocumentService $composeMatterDocumentService
    ) {
        $this->middleware('auth:admin');
        $this->emailService = $emailService;
        $this->crmSentEmailS3Service = $crmSentEmailS3Service;
        $this->composeMatterDocumentService = $composeMatterDocumentService;
    }
    // Dashboard functionality moved to DashboardController

    public function fetchnotification(Request $request){
        // $notificalists = \App\Models\Notification::where('receiver_id', Auth::user()->id)->where('receiver_status', 0)->orderby('created_at','DESC')->paginate(5);
         $notificalistscount = \App\Models\Notification::where('receiver_id', Auth::user()->id)->where('receiver_status', 0)->count();
        /* $output = '';
	    foreach($notificalists as $listnoti){
	        $output .= '<a href="'.$listnoti->url.'?t='.$listnoti->id.'" class="dropdown-item dropdown-item-unread">
						<span class="dropdown-item-icon bg-primary text-white">
							<i class="fa-solid fa-code"></i>
						</span>
						<span class="dropdown-item-desc">'.$listnoti->message.' <span class="time">'.date('d/m/Y h:i A',strtotime($listnoti->created_at)).'</span></span>
					</a>';
	    }*/

	    $data = array(
           //'notification' => $output,
           'unseen_notification'  => $notificalistscount
        );
        echo json_encode($data);
    }

    // Moved to DashboardController

    // Moved to DashboardController

    // Dashboard notification methods moved to DashboardController

    /**
     * My Profile.
     *
     * @return \Illuminate\Http\Response
     */
	public function myProfile(Request $request)
	{
		/* Get all Select Data */
			$countries = array();
		/* Get all Select Data */

		if ($request->isMethod('post'))
		{
			$requestData 		= 	$request->all();

			$this->validate($request, [
										'first_name' => 'required',
										'last_name' => 'nullable',
										'country' => 'required',
										'phone' => 'required',
										'state' => 'required',
										'city' => 'required',
										'address' => 'required',
										'zip' => 'required'
									  ]);

			$obj							= 	\App\Models\Staff::find(Auth::user()->id);

			$obj->first_name				=	@$requestData['first_name'];
			$obj->last_name					=	@$requestData['last_name'];
			$obj->phone						=	@$requestData['phone'];
			$obj->address					=	@$requestData['address'];
			$obj->company_name				=	@$requestData['company_name'];
			$obj->company_website			=	@$requestData['company_website'];

			$saved							=	$obj->save();

			if(!$saved)
			{
				return redirect()->back()->with('error', config('constants.server_error'));
			}
			else
			{
				return Redirect::to('/my_profile')->with('success', 'Your Profile has been edited successfully.');
			}
		}
		else
		{
			$id = Auth::user()->id;
			$fetchedData = \App\Models\Staff::find($id);

			return view('crm.my_profile', compact(['fetchedData', 'countries']));
		}
	}
	/**
     * Change password and Logout automatiaclly.
     *
     * @return \Illuminate\Http\Response
     */
	public function change_password(Request $request)
	{
		$user = Auth::guard('admin')->user();
		$staff = $user instanceof Staff ? $user : null;

		if (!$staff || (int) ($staff->status ?? 0) !== 1) {
			Auth::guard('admin')->logout();
			$request->session()->invalidate();
			$request->session()->regenerateToken();
			return redirect()->route('crm.login')->with('error', 'Unauthorized: Active staff authentication required.');
		}

		if ($request->isMethod('post'))
		{
			$throttleKey = 'change-password:' . $staff->id . '|' . $request->ip();
			if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
				$seconds = RateLimiter::availableIn($throttleKey);
				return redirect()->back()->with('error', "Too many password change attempts. Please try again in {$seconds} seconds.");
			}

			$this->validate($request, [
				'old_password' => 'required',
				'password' => ['required', 'string', 'min:8', 'confirmed', 'different:old_password'],
				'password_confirmation' => 'required',
			], [
				'password.min' => 'The new password must be at least 8 characters long.',
				'password.different' => 'The new password must be different from your current password.',
				'password.confirmed' => 'The password confirmation does not match.',
			]);

			if ($request->filled('admin_id') && (int) $request->input('admin_id') !== (int) $staff->id) {
				return redirect()->back()->with('error', 'You can change the password only for your own account.');
			}

			if (!Hash::check($request->input('old_password'), $staff->password)) {
				RateLimiter::hit($throttleKey, 60);
				return redirect()->back()->with('error', 'Your current password does not match the password you provided. Please try again.');
			}

			// Clear rate limiter upon successful credential verification
			RateLimiter::clear($throttleKey);

			// Update password and rotate remember token
			$staff->password = Hash::make($request->input('password'));
			$staff->setRememberToken(Str::random(60));

			// Revoke all existing Sanctum API tokens for this staff member
			if (method_exists($staff, 'tokens')) {
				$staff->tokens()->delete();
			}

			if ($staff->save()) {
				// Record audit log
				$loginLog = new \App\Models\StaffLoginLog();
				$loginLog->level = 'info';
				$loginLog->user_id = $staff->id;
				$loginLog->ip_address = $request->getClientIp();
				$loginLog->user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
				$loginLog->message = 'Password changed; session invalidated and logged out';
				$loginLog->save();

				// Invalidate session, regenerate CSRF token, and clear credentials
				Auth::guard('admin')->logout();
				$request->session()->invalidate();
				$request->session()->regenerateToken();
				\Illuminate\Support\Facades\Cookie::queue(\Illuminate\Support\Facades\Cookie::forget('password'));

				Log::info('Staff password successfully changed and session invalidated', [
					'staff_id' => $staff->id,
					'ip' => $request->ip(),
				]);

				return redirect()->route('crm.login')->with('success', 'Your password has been changed successfully. Please log in with your new password.');
			} else {
				return redirect()->back()->with('error', config('constants.server_error'));
			}
		}

		return view('crm.change_password', [
			'staff' => $staff,
		]);
	}

	public function updateAction(Request $request)
	{
		$status = 0;
		if ($request->isMethod('post'))
		{
			$id = (int) trim((string) $request->input('id', 0));
			$table = trim((string) $request->input('table', ''));
			$col = trim((string) ($request->input('colname') ?? $request->input('colum') ?? $request->input('col') ?? ''));
			$cstatus = $request->input('current_status') ?? $request->input('cstatus') ?? $request->input('status');

			$allowedCols = ['status', 'is_active', 'is_archive', 'is_trash'];

			$systemTables = [
				'staff', 'branches', 'workflows', 'workflow_stages', 'matters',
				'crm_email_templates', 'matter_email_templates', 'matter_other_email_templates',
				'templates', 'products', 'document_checklists', 'personal_document_types',
				'matter_document_types', 'teams'
			];

			$clientTables = [
				'admins', 'client_matters', 'client_matter_tasks', 'quotations', 'email_labels'
			];

			$allowedTables = array_merge($systemTables, $clientTables);

			if ($id <= 0 || empty($table) || empty($col) || $cstatus === null) {
				return response()->json(['status' => 0, 'message' => 'Missing required parameters.']);
			}

			if (!in_array($table, $allowedTables, true) || !Schema::hasTable($table)) {
				return response()->json(['status' => 0, 'message' => 'Status update is not authorized for this table.']);
			}

			if (!in_array($col, $allowedCols, true) || !Schema::hasColumn($table, $col)) {
				return response()->json(['status' => 0, 'message' => 'Target column is not authorized or does not exist.']);
			}

			$user = Auth::guard('admin')->user();
			$staff = $user instanceof \App\Models\Staff ? $user : null;
			if (!$staff || (int) ($staff->status ?? 0) !== 1) {
				return response()->json(['status' => 0, 'message' => 'Unauthorized: Active staff authentication required.']);
			}

			// 1. Authorization check for staff management table
			if ($table === 'staff') {
				if ($id === (int) $staff->id) {
					return response()->json(['status' => 0, 'message' => 'Unauthorized: You cannot modify your own staff status.']);
				}
				if (!$staff->hasEffectiveSuperAdminPrivileges()) {
					return response()->json(['status' => 0, 'message' => 'Unauthorized: Modifying staff status requires Super Admin privileges.']);
				}
			}
			// 2. Authorization check for client records (admins table)
			elseif ($table === 'admins') {
				$client = DB::table('admins')->where('id', $id)->first();
				if (!$client) {
					return response()->json(['status' => 0, 'message' => 'ID does not exist, please check it once again.']);
				}
				$this->ensureCrmRecordAccess((int) $id);
			}
			// 3. Authorization check for system-wide configuration tables
			elseif (in_array($table, $systemTables, true)) {
				$canManageSystem = $staff->canAccessAdminConsole() || $staff->hasEffectiveSuperAdminPrivileges();

				if (!$canManageSystem) {
					return response()->json(['status' => 0, 'message' => 'Unauthorized: Modifying system status requires Admin Console privileges.']);
				}
			}
			// 4. Authorization check for client-specific records
			elseif (in_array($table, $clientTables, true)) {
				$row = DB::table($table)->where('id', $id)->first();
				if (!$row) {
					return response()->json(['status' => 0, 'message' => 'ID does not exist, please check it once again.']);
				}

				if ($table === 'client_matter_tasks') {
					$clientId = $row->client_id ?? null;
					if (!$clientId && !empty($row->client_matter_id)) {
						$clientId = DB::table('client_matters')->where('id', $row->client_matter_id)->value('client_id');
					}
					if ($clientId) {
						$this->ensureCrmRecordAccess((int) $clientId);
					} elseif (!$staff->hasEffectiveSuperAdminPrivileges()) {
						return response()->json(['status' => 0, 'message' => 'Unauthorized: No client associated with this task.']);
					}
				} elseif ($table === 'client_matters' || $table === 'quotations') {
					$clientId = $row->client_id ?? $row->admin_id ?? null;
					if ($clientId) {
						$this->ensureCrmRecordAccess((int) $clientId);
					} elseif (!$staff->hasEffectiveSuperAdminPrivileges()) {
						return response()->json(['status' => 0, 'message' => 'Unauthorized: No client associated with this record.']);
					}
				} elseif ($table === 'email_labels') {
					$ownerId = (int) ($row->user_id ?? 0);
					$isOwner = $ownerId > 0 && $ownerId === (int) $staff->id;
					$canManageLabels = $isOwner || $staff->canAccessAdminConsole() || $staff->hasEffectiveSuperAdminPrivileges();
					if (!$canManageLabels) {
						return response()->json(['status' => 0, 'message' => 'Unauthorized: You can only modify your own custom email labels.']);
					}
				}
			}

			$recordExist = DB::table($table)->where('id', $id)->exists();
			if ($recordExist) {
				$updated_status = ((int) $cstatus === 1) ? 0 : 1;
				$response = DB::table($table)->where('id', $id)->update([
					$col => $updated_status,
					'updated_at' => date('Y-m-d H:i:s')
				]);

				if ($response) {
					$status = 1;
					$message = ($updated_status === 1) ? 'Record has been enabled successfully.' : 'Record has been disabled successfully.';
				} else {
					$message = config('constants.server_error');
				}
			} else {
				$message = 'ID does not exist, please check it once again.';
			}
		} else {
			$message = config('constants.post_method');
		}

		return response()->json(['status' => $status, 'message' => $message]);
	}

	public function moveAction(Request $request)
	{
		$status = 0;
		if ($request->isMethod('post'))
		{
			$id = (int) trim($request->input('id', 0));
			$table = trim((string) $request->input('table', ''));
			$col = trim((string) $request->input('col', ''));

			$allowedCols = ['status', 'is_active', 'is_archive', 'is_trash'];
			$systemTables = [
				'matters', 'workflows', 'workflow_stages', 'branches',
				'crm_email_templates', 'matter_email_templates', 'matter_other_email_templates',
				'templates', 'products', 'document_checklists', 'personal_document_types',
				'matter_document_types', 'teams'
			];
			$clientTables = [
				'admins', 'client_matters', 'client_matter_tasks', 'quotations', 'email_labels'
			];

			$allowedTables = array_merge($systemTables, $clientTables);

			if ($id <= 0 || empty($table) || empty($col)) {
				return response()->json(['status' => 0, 'message' => 'Missing required parameters.']);
			}

			if (!in_array($table, $allowedTables, true) || !Schema::hasTable($table)) {
				return response()->json(['status' => 0, 'message' => 'Column zeroing is not authorized for this table.']);
			}

			if (!in_array($col, $allowedCols, true) || !Schema::hasColumn($table, $col)) {
				return response()->json(['status' => 0, 'message' => 'Target column is not authorized or does not exist.']);
			}

			$user = Auth::guard('admin')->user();
			$staff = $user instanceof \App\Models\Staff ? $user : null;
			if (!$staff || (int) ($staff->status ?? 0) !== 1) {
				return response()->json(['status' => 0, 'message' => 'Unauthorized: Active staff authentication required.']);
			}

			// 1. Authorization check for client records (admins table)
			if ($table === 'admins') {
				$client = DB::table('admins')->where('id', $id)->first();
				if (!$client) {
					return response()->json(['status' => 0, 'message' => 'ID does not exist, please check it once again.']);
				}
				$this->ensureCrmRecordAccess((int) $id);
			}
			// 3. Authorization check for system configuration tables
			elseif (in_array($table, $systemTables, true)) {
				$canManageSystem = $staff->canAccessAdminConsole() || $staff->hasEffectiveSuperAdminPrivileges();
				if (!$canManageSystem) {
					return response()->json(['status' => 0, 'message' => 'Unauthorized: Modifying system configuration requires Admin Console privileges.']);
				}
			}
			// 4. Access check for client-specific records
			elseif (in_array($table, $clientTables, true)) {
				$row = DB::table($table)->where('id', $id)->first();
				if (!$row) {
					return response()->json(['status' => 0, 'message' => 'ID does not exist, please check it once again.']);
				}

				if ($table === 'client_matter_tasks') {
					$clientId = $row->client_id ?? null;
					if (!$clientId && !empty($row->client_matter_id)) {
						$clientId = DB::table('client_matters')->where('id', $row->client_matter_id)->value('client_id');
					}
					if ($clientId) {
						$this->ensureCrmRecordAccess((int) $clientId);
					} elseif (!$staff->hasEffectiveSuperAdminPrivileges()) {
						return response()->json(['status' => 0, 'message' => 'Unauthorized: No client associated with this task.']);
					}
				} elseif ($table === 'client_matters' || $table === 'quotations') {
					$clientId = $row->client_id ?? $row->admin_id ?? null;
					if ($clientId) {
						$this->ensureCrmRecordAccess((int) $clientId);
					} elseif (!$staff->hasEffectiveSuperAdminPrivileges()) {
						return response()->json(['status' => 0, 'message' => 'Unauthorized: No client associated with this record.']);
					}
				} elseif ($table === 'email_labels') {
					$ownerId = (int) ($row->user_id ?? 0);
					$isOwner = $ownerId > 0 && $ownerId === (int) $staff->id;
					$canManageLabels = $isOwner || $staff->canAccessAdminConsole() || $staff->hasEffectiveSuperAdminPrivileges();
					if (!$canManageLabels) {
						return response()->json(['status' => 0, 'message' => 'Unauthorized: You can only modify your own custom email labels.']);
					}
				}
			}

			$recordExist = DB::table($table)->where('id', $id)->exists();
			if ($recordExist) {
				$response = DB::table($table)->where('id', $id)->update([$col => 0, 'updated_at' => date('Y-m-d H:i:s')]);
				if ($response) {
					$status = 1;
					$message = 'Record status successfully changed.';
				} else {
					$message = config('constants.server_error');
				}
			} else {
				$message = 'ID does not exist, please check it once again.';
			}
		} else {
			$message = config('constants.post_method');
		}

		return response()->json(['status' => $status, 'message' => $message]);
	}

	private function validateAndAuthorizeStatusMutation(Request $request, string $targetColumn = 'status'): array
	{
		$systemTables = [
			'branches', 'workflows', 'workflow_stages', 'matters',
			'crm_email_templates', 'matter_email_templates', 'matter_other_email_templates',
			'templates', 'products', 'document_checklists', 'personal_document_types',
			'matter_document_types', 'teams'
		];

		$clientTables = [
			'admins', 'client_matters', 'client_matter_tasks', 'quotations', 'email_labels'
		];

		$allowedTables = array_merge($systemTables, $clientTables);

		$id = (int) trim((string) $request->input('id', 0));
		$table = trim((string) $request->input('table', ''));

		if ($id <= 0 || empty($table)) {
			return [
				'authorized' => false,
				'response' => response()->json(['status' => 0, 'message' => 'Id OR Table does not exist, please check it once again.'])
			];
		}

		if (!in_array($table, $allowedTables, true) || !Schema::hasTable($table)) {
			return [
				'authorized' => false,
				'response' => response()->json(['status' => 0, 'message' => 'Status mutation is not authorized for this table.'])
			];
		}

		if (!Schema::hasColumn($table, $targetColumn)) {
			return [
				'authorized' => false,
				'response' => response()->json(['status' => 0, 'message' => 'Target column does not exist on this table.'])
			];
		}

		$user = Auth::guard('admin')->user();
		$staff = $user instanceof \App\Models\Staff ? $user : null;
		if (!$staff || (int) ($staff->status ?? 0) !== 1) {
			return [
				'authorized' => false,
				'response' => response()->json(['status' => 0, 'message' => 'Unauthorized: Active staff authentication required.'])
			];
		}

		// 1. Authorization check for client records (admins table)
		if ($table === 'admins') {
			$client = DB::table('admins')->where('id', $id)->first();
			if (!$client) {
				return [
					'authorized' => false,
					'response' => response()->json(['status' => 0, 'message' => 'ID does not exist, please check it once again.'])
				];
			}
			$this->ensureCrmRecordAccess((int) $id);
		}
		// 2. Authorization check for system-wide configuration tables
		elseif (in_array($table, $systemTables, true)) {
			$canManageSystem = $staff->canAccessAdminConsole() || $staff->hasEffectiveSuperAdminPrivileges();
			if (!$canManageSystem) {
				return [
					'authorized' => false,
					'response' => response()->json(['status' => 0, 'message' => 'Unauthorized: Modifying system configuration requires Admin Console privileges.'])
				];
			}
		}
		// 3. Authorization check for client-specific records
		elseif (in_array($table, $clientTables, true)) {
			$row = DB::table($table)->where('id', $id)->first();
			if (!$row) {
				return [
					'authorized' => false,
					'response' => response()->json(['status' => 0, 'message' => 'ID does not exist, please check it once again.'])
				];
			}

			if ($table === 'client_matter_tasks') {
				$clientId = $row->client_id ?? null;
				if (!$clientId && !empty($row->client_matter_id)) {
					$clientId = DB::table('client_matters')->where('id', $row->client_matter_id)->value('client_id');
				}
				if ($clientId) {
					$this->ensureCrmRecordAccess((int) $clientId);
				} elseif (!$staff->hasEffectiveSuperAdminPrivileges()) {
					return [
						'authorized' => false,
						'response' => response()->json(['status' => 0, 'message' => 'Unauthorized: No client associated with this task.'])
					];
				}
			} elseif ($table === 'client_matters' || $table === 'quotations') {
				$clientId = $row->client_id ?? $row->admin_id ?? null;
				if ($clientId) {
					$this->ensureCrmRecordAccess((int) $clientId);
				} elseif (!$staff->hasEffectiveSuperAdminPrivileges()) {
					return [
						'authorized' => false,
						'response' => response()->json(['status' => 0, 'message' => 'Unauthorized: No client associated with this record.'])
					];
				}
			} elseif ($table === 'email_labels') {
				$ownerId = (int) ($row->user_id ?? 0);
				$isOwner = $ownerId > 0 && $ownerId === (int) $staff->id;
				$canManageLabels = $isOwner || $staff->canAccessAdminConsole() || $staff->hasEffectiveSuperAdminPrivileges();
				if (!$canManageLabels) {
					return [
						'authorized' => false,
						'response' => response()->json(['status' => 0, 'message' => 'Unauthorized: You can only modify your own custom email labels.'])
					];
				}
			}
		}

		return [
			'authorized' => true,
			'id' => $id,
			'table' => $table,
			'user' => $user,
			'staff' => $staff,
		];
	}

	public function declinedAction(Request $request)
	{
		if (!$request->isMethod('post')) {
			return response()->json(['status' => 0, 'message' => config('constants.post_method')]);
		}

		$authCheck = $this->validateAndAuthorizeStatusMutation($request, 'status');
		if (!$authCheck['authorized']) {
			return $authCheck['response'];
		}

		$id = $authCheck['id'];
		$table = $authCheck['table'];

		$recordExist = DB::table($table)->where('id', $id)->exists();
		if (!$recordExist) {
			return response()->json(['status' => 0, 'message' => 'ID does not exist, please check it once again.']);
		}

		$response = DB::table($table)->where('id', $id)->update([
			'status' => 2,
			'updated_at' => date('Y-m-d H:i:s')
		]);

		if ($response) {
			return response()->json(['status' => 1, 'message' => 'Record has been disabled successfully.']);
		}

		return response()->json(['status' => 0, 'message' => config('constants.server_error')]);
	}

	public function approveAction(Request $request)
	{
		if (!$request->isMethod('post')) {
			return response()->json(['status' => 0, 'message' => config('constants.post_method')]);
		}

		$authCheck = $this->validateAndAuthorizeStatusMutation($request, 'status');
		if (!$authCheck['authorized']) {
			return $authCheck['response'];
		}

		$id = $authCheck['id'];
		$table = $authCheck['table'];

		$recordExist = DB::table($table)->where('id', $id)->exists();
		if (!$recordExist) {
			return response()->json(['status' => 0, 'message' => 'ID does not exist, please check it once again.']);
		}

		$response = DB::table($table)->where('id', $id)->update([
			'status' => 1,
			'updated_at' => date('Y-m-d H:i:s')
		]);

		if ($response) {
			return response()->json(['status' => 1, 'message' => 'Record has been approved successfully.']);
		}

		return response()->json(['status' => 0, 'message' => config('constants.server_error')]);
	}

	public function processAction(Request $request)
	{
		if (!$request->isMethod('post')) {
			return response()->json(['status' => 0, 'message' => config('constants.post_method')]);
		}

		$authCheck = $this->validateAndAuthorizeStatusMutation($request, 'status');
		if (!$authCheck['authorized']) {
			return $authCheck['response'];
		}

		$id = $authCheck['id'];
		$table = $authCheck['table'];

		$recordExist = DB::table($table)->where('id', $id)->exists();
		if (!$recordExist) {
			return response()->json(['status' => 0, 'message' => 'ID does not exist, please check it once again.']);
		}

		$response = DB::table($table)->where('id', $id)->update([
			'status' => 4,
			'updated_at' => date('Y-m-d H:i:s')
		]);

		if ($response) {
			return response()->json(['status' => 1, 'message' => 'Record has been processed successfully.']);
		}

		return response()->json(['status' => 0, 'message' => config('constants.server_error')]);
	}

	public function archiveAction(Request $request)
	{
		if (!$request->isMethod('post')) {
			return response()->json(['status' => 0, 'message' => config('constants.post_method'), 'astatus' => '']);
		}

		$authCheck = $this->validateAndAuthorizeStatusMutation($request, 'is_archive');
		if (!$authCheck['authorized']) {
			return $authCheck['response'];
		}

		$id = $authCheck['id'];
		$table = $authCheck['table'];

		$recordExist = DB::table($table)->where('id', $id)->exists();
		if (!$recordExist) {
			return response()->json(['status' => 0, 'message' => 'ID does not exist, please check it once again.', 'astatus' => '']);
		}

		$response = DB::table($table)->where('id', $id)->update([
			'is_archive' => 1,
			'updated_at' => date('Y-m-d H:i:s')
		]);

		$astatus = '';
		$getarchive = DB::table($table)->where('id', $id)->first();
		if ($getarchive && isset($getarchive->status)) {
			if ((int)$getarchive->status === 0) {
				$astatus = '<span title="draft" class="ui label uppercase">Draft</span><span> (Archived)</span>';
			} else if ((int)$getarchive->status === 1) {
				$astatus = '<span title="draft" class="ui label uppercase yellow">Sent</span><span> (Archived)</span>';
			} else if ((int)$getarchive->status === 2) {
				$astatus = '<span title="draft" class="ui label uppercase text-danger">Declined</span><span> (Archived)</span>';
			}
		}

		if ($response) {
			return response()->json([
				'status' => 1,
				'message' => 'Record has been archived successfully.',
				'astatus' => $astatus
			]);
		}

		return response()->json(['status' => 0, 'message' => config('constants.server_error'), 'astatus' => $astatus]);
	}

	public function deleteAction(Request $request)
	{
		$status = 0;
		if ($request->isMethod('post'))
		{
			$requestData = $request->all();
			$id = (int) trim($requestData['id'] ?? 0);
			$table = trim((string)($requestData['table'] ?? ''));

			$coreStructuralTables = [
				'branches', 'workflows', 'matters', 'teams'
			];

			$systemTables = [
				'crm_email_templates', 'matter_email_templates', 'matter_other_email_templates',
				'templates', 'products', 'document_checklists', 'personal_document_types',
				'matter_document_types', 'workflow_stages'
			];

			$clientTables = [
				'admins', 'client_matters', 'client_matter_tasks', 'quotations', 'email_labels'
			];

			$allowedTables = array_merge($coreStructuralTables, $systemTables, $clientTables);

			if ($id <= 0 || empty($table)) {
				return response()->json(['status' => 0, 'message' => 'Id OR Table does not exist, please check it once again.']);
			}

			if (!in_array($table, $allowedTables, true) || !Schema::hasTable($table)) {
				return response()->json(['status' => 0, 'message' => 'Deletion is not authorized for this table.']);
			}

			$user = Auth::guard('admin')->user();
			$staff = $user instanceof \App\Models\Staff ? $user : null;
			if (!$staff || (int) ($staff->status ?? 0) !== 1) {
				return response()->json(['status' => 0, 'message' => 'Unauthorized: Active staff authentication required.']);
			}

			// 1. Authorization check for core structural tables
			if (in_array($table, $coreStructuralTables, true)) {
				if (!$staff->hasEffectiveSuperAdminPrivileges()) {
					return response()->json(['status' => 0, 'message' => 'Unauthorized: Deleting core structural configuration requires Super Admin privileges.']);
				}

				if ($table === 'branches') {
					$hasStaff = (Schema::hasColumn('staff', 'office_id') && DB::table('staff')->where('office_id', $id)->where('status', 1)->exists())
						|| (Schema::hasColumn('staff', 'branch_id') && DB::table('staff')->where('branch_id', $id)->where('status', 1)->exists());
					$hasClients = (Schema::hasColumn('admins', 'office_id') && DB::table('admins')->where('office_id', $id)->where('status', 1)->exists())
						|| (Schema::hasColumn('admins', 'branch_id') && DB::table('admins')->where('branch_id', $id)->where('status', 1)->exists());
					if ($hasStaff || $hasClients) {
						return response()->json(['status' => 0, 'message' => 'Cannot delete office branch with active staff or clients assigned.']);
					}
				} elseif ($table === 'matters') {
					$hasClientMatters = Schema::hasColumn('client_matters', 'matter_id') && DB::table('client_matters')->where('matter_id', $id)->where('matter_status', 1)->exists();
					if ($hasClientMatters) {
						return response()->json(['status' => 0, 'message' => 'Cannot delete matter type with active client matters.']);
					}
				} elseif ($table === 'workflows') {
					$hasActiveMatters = Schema::hasColumn('client_matters', 'workflow_id') && DB::table('client_matters')->where('workflow_id', $id)->where('matter_status', 1)->exists();
					if ($hasActiveMatters) {
						return response()->json(['status' => 0, 'message' => 'Cannot delete workflow with active client matters.']);
					}
				}
			}

			// 2. Authorization check for system-wide configuration tables
			if (in_array($table, $systemTables, true)) {
				$canManageSystem = $staff->canAccessAdminConsole() || $staff->hasEffectiveSuperAdminPrivileges();
				if (!$canManageSystem) {
					return response()->json(['status' => 0, 'message' => 'Unauthorized: Modifying system configuration requires Admin Console privileges.']);
				}
			}

			$recordExist = DB::table($table)->where('id', $id)->exists();
			if (!$recordExist) {
				return response()->json(['status' => 0, 'message' => 'ID does not exist, please check it once again.']);
			}

			if ($table === 'admins') {
				$o = \App\Models\Admin::where('id', $id)->first();
				if (!$o) {
					return response()->json(['status' => 0, 'message' => 'ID does not exist, please check it once again.']);
				}
				$this->ensureCrmRecordAccess((int) $id);
				$is_status = ($o->status == 1) ? 0 : 1;
				$response = DB::table($table)->where('id', $id)->update(['status' => $is_status, 'updated_at' => date('Y-m-d H:i:s')]);
				if ($response) {
					$status = 1;
					$message = ($is_status === 0) ? 'Record has been inactive successfully.' : 'Record has been active successfully.';
				} else {
					$message = config('constants.server_error');
				}
			}
			else if ($table === 'client_matters') {
				$matter = \App\Models\ClientMatter::find($id);
				if (!$matter) {
					return response()->json(['status' => 0, 'message' => 'ID does not exist, please check it once again.']);
				}
				if ($matter->client_id) {
					$this->ensureCrmRecordAccess((int) $matter->client_id);
				} elseif (!$staff->hasEffectiveSuperAdminPrivileges()) {
					return response()->json(['status' => 0, 'message' => 'Unauthorized: Cannot access this record.']);
				}
				$response = DB::table($table)->where('id', $id)->update(['matter_status' => 0]);
				if ($response) {
					$status = 1;
					$message = 'Record has been removed successfully.';
					if ($matter && $matter->client_id) {
						$emailLogIds = \App\Models\EmailLog::where('client_id', $matter->client_id)
							->where('client_matter_id', $matter->id)
							->pluck('id');
						if ($emailLogIds->isNotEmpty()) {
							DB::table('email_label_email_log')->whereIn('email_log_id', $emailLogIds)->delete();
							DB::table('email_log_attachments')->whereIn('email_log_id', $emailLogIds)->delete();
							\App\Models\EmailLog::whereIn('id', $emailLogIds)->delete();
						}
					}
				} else {
					$message = config('constants.server_error');
				}
			}
			else if ($table === 'quotations') {
				$quotation = DB::table('quotations')->where('id', $id)->first();
				if (!$quotation) {
					return response()->json(['status' => 0, 'message' => 'ID does not exist, please check it once again.']);
				}
				if (!empty($quotation->client_id)) {
					$this->ensureCrmRecordAccess((int) $quotation->client_id);
				} elseif (!$staff->hasEffectiveSuperAdminPrivileges()) {
					return response()->json(['status' => 0, 'message' => 'Unauthorized: Cannot access this record.']);
				}
				$response = DB::table($table)->where('id', $id)->update(['is_archive' => 1]);
				if ($response) {
					$status = 1;
					$message = 'Record has been removed successfully.';
				} else {
					$message = config('constants.server_error');
				}
			}
			else if ($table === 'client_matter_tasks') {
				$task = DB::table('client_matter_tasks')->where('id', $id)->first();
				if (!$task) {
					return response()->json(['status' => 0, 'message' => 'ID does not exist, please check it once again.']);
				}
				$clientId = $task->client_id ?? null;
				if (!$clientId && !empty($task->client_matter_id)) {
					$clientId = DB::table('client_matters')->where('id', $task->client_matter_id)->value('client_id');
				}
				if ($clientId) {
					$this->ensureCrmRecordAccess((int) $clientId);
				} elseif (!$staff->hasEffectiveSuperAdminPrivileges()) {
					return response()->json(['status' => 0, 'message' => 'Unauthorized: Cannot access this task.']);
				}
				$response = DB::table($table)->where('id', $id)->delete();
				if ($response) {
					$status = 1;
					$message = 'Task has been deleted successfully.';
				} else {
					$message = config('constants.server_error');
				}
			}
			else if ($table === 'templates') {
				$response = DB::table($table)->where('id', $id)->delete();
				DB::table('template_infos')->where('quotation_id', $id)->delete();
				if ($response) {
					$status = 1;
					$message = 'Record has been deleted successfully.';
				} else {
					$message = config('constants.server_error');
				}
			}
			else if ($table === 'products') {
				$response = DB::table($table)->where('id', $id)->delete();
				DB::table('template_infos')->where('quotation_id', $id)->delete();
				if ($response) {
					$status = 1;
					$message = 'Record has been deleted successfully.';
				} else {
					$message = config('constants.server_error');
				}
			}
			else if ($table === 'email_labels') {
				$label = DB::table($table)->where('id', $id)->first();
				if (!$label) {
					return response()->json(['status' => 0, 'message' => 'ID does not exist, please check it once again.']);
				}
				if ($label->type === 'system') {
					$message = 'System labels cannot be deleted.';
				} else {
					$ownerId = (int) ($label->user_id ?? 0);
					$isOwner = $ownerId > 0 && $ownerId === (int) $staff->id;
					$canManage = $isOwner || $staff->canAccessAdminConsole() || $staff->hasEffectiveSuperAdminPrivileges();
					if (!$canManage) {
						return response()->json(['status' => 0, 'message' => 'Unauthorized: You can only delete your own custom email labels.']);
					}
					$response = DB::table($table)->where('id', $id)->delete();
					if ($response) {
						$status = 1;
						$message = 'Record has been deleted successfully.';
					} else {
						$message = config('constants.server_error');
					}
				}
			}
			else if ($table === 'workflow_stages') {
				$row = DB::table('workflow_stages')->where('id', $id)->first();
				if ($row && WorkflowStageFreeze::isFrozen($row->name)) {
					$message = 'This workflow stage is frozen and cannot be deleted.';
				} else {
					$response = DB::table($table)->where('id', $id)->delete();
					if ($response) {
						$status = 1;
						$message = 'Record has been deleted successfully.';
					} else {
						$message = config('constants.server_error');
					}
				}
			}
			else {
				$response = DB::table($table)->where('id', $id)->delete();
				if ($response) {
					$status = 1;
					$message = 'Record has been deleted successfully.';
				} else {
					$message = config('constants.server_error');
				}
			}
		} else {
			$message = config('constants.post_method');
		}

		return response()->json(['status' => $status, 'message' => $message]);
	}

	public function gettemplates(Request $request){
		$id = $request->id;
		$template = \App\Models\EmailTemplate::find($id);
		if ($template) {
			echo json_encode(array('subject' => $template->subject, 'description' => $template->description));
		} else {
			echo json_encode(array('subject' => '', 'description' => ''));
		}
	}

	/**
	 * Get compose defaults for a client matter: first email template, dedicated checklist IDs, and macro values.
	 * Used to auto-select matter's first email and checklists when opening compose modal.
	 * macro_values enables replacement of {ClientID}, {ApplicantGivenNames}, {visa_apply}, etc. in First email template.
	 */
	public function getComposeDefaults(Request $request){
		$clientMatterId = $request->client_matter_id;
		$ownerId = (int) ($request->client_id ?? 0);

		if (!$clientMatterId) {
			if ($ownerId <= 0) {
				return response()->json([
					'template' => null,
					'checklist_ids' => [],
					'matter_documents' => [],
					'macro_values' => null,
				]);
			}

			$this->ensureCrmRecordAccess($ownerId);

			return response()->json([
				'template' => null,
				'checklist_ids' => [],
				'matter_documents' => $this->composeMatterDocumentService->listForMatter($ownerId, 0),
				'macro_values' => null,
			]);
		}
		$clientMatter = ClientMatter::find($clientMatterId);
		if (! $clientMatter) {
			return response()->json([
				'template' => null,
				'checklist_ids' => [],
				'matter_documents' => [],
				'macro_values' => null,
			]);
		}

		$this->ensureCrmRecordAccess((int) $clientMatter->client_id);

		$matterDocuments = $this->composeMatterDocumentService->listForMatter(
			(int) $clientMatter->client_id,
			(int) $clientMatterId
		);

		if (! $clientMatter->sel_matter_id) {
			return response()->json([
				'template' => null,
				'checklist_ids' => [],
				'matter_documents' => $matterDocuments,
				'macro_values' => $this->getComposeMacroValues((int) $clientMatter->client_id, (int) $clientMatterId),
			]);
		}
		$matterId = $clientMatter->sel_matter_id;
		$clientId = $clientMatter->client_id;

		// First Email template - one per matter
		$firstTemplate = \App\Models\EmailTemplate::forMatter($matterId)->ofType(\App\Models\EmailTemplate::TYPE_MATTER_FIRST)->orderBy('id', 'asc')->first();

		// Additional matter templates - multiple per matter
		$otherTemplates = \App\Models\EmailTemplate::forMatter($matterId)->ofType(\App\Models\EmailTemplate::TYPE_MATTER_OTHER)->orderBy('id', 'asc')->get();

		// Build full list: first email first, then other templates
		$allTemplates = [];
		if ($firstTemplate) {
			$allTemplates[] = ['id' => $firstTemplate->id, 'name' => $firstTemplate->name, 'subject' => $firstTemplate->subject, 'description' => $firstTemplate->description];
		}
		foreach ($otherTemplates as $t) {
			$allTemplates[] = ['id' => $t->id, 'name' => $t->name, 'subject' => $t->subject, 'description' => $t->description];
		}

		$checklistIds = \App\Models\UploadChecklist::where('matter_id', $matterId)->pluck('id')->toArray();

		// Build macro values for First email template replacement
		$macroValues = $this->getComposeMacroValues($clientId, $clientMatterId);

		return response()->json([
			'template' => $firstTemplate ? ['id' => $firstTemplate->id, 'name' => $firstTemplate->name, 'subject' => $firstTemplate->subject, 'description' => $firstTemplate->description] : null,
			'matter_templates' => $allTemplates,
			'checklist_ids' => $checklistIds,
			'matter_documents' => $matterDocuments,
			'macro_values' => $macroValues,
		]);
	}

	/**
	 * Get macro replacement values for a client matter (ClientID, ApplicantGivenNames, visa_apply, fees, etc.)
	 */
	protected function getComposeMacroValues($clientId, $clientMatterId)
	{
		$client = Admin::find($clientId);
		if (!$client) {
			return null;
		}

		$clientMatter = ClientMatter::find($clientMatterId);
		if (!$clientMatter) {
			return null;
		}

		$values = [
			'ClientID' => $client->client_id ?? '',
			'ApplicantGivenNames' => $client->first_name ?? '',
			'ApplicantSurname' => $client->last_name ?? '',
			'client_firstname' => ($client->first_name ?? '') ? ucfirst($client->first_name) : '',
			'client_reference' => $client->client_id ?? '',
			'visa_apply' => '',
			'Blocktotalfeesincltax' => '',
			'Blocktotalfeesinclgst' => '',
			'Block1feesincltax' => '',
			'Block1feesinclgst' => '',
			'Block2feesincltax' => '',
			'Block2feesinclgst' => '',
			'Block3feesincltax' => '',
			'Block3feesinclgst' => '',
			'TotalDisbursements' => '',
			'TotalEstimatedOthCosts' => '',
			'GrandTotalFeesAndCosts' => '',
			'PDF_url_for_sign' => '',
		];

		$matterInfo = null;
		if ($clientMatter->sel_matter_id) {
			$matterInfo = DB::table('matters')->where('id', $clientMatter->sel_matter_id)->first();
		}

		if ($matterInfo) {
			$values['visa_apply'] = $matterInfo->title ?? '';

			$matterCol = static function ($row, string $column, $default = 0) {
				return property_exists($row, $column) ? ($row->{$column} ?? $default) : $default;
			};

			$block1 = floatval($matterCol($matterInfo, 'Block_1_Ex_Tax', 0));
			$block2 = floatval($matterCol($matterInfo, 'Block_2_Ex_Tax', 0));
			$block3 = floatval($matterCol($matterInfo, 'Block_3_Ex_Tax', 0));
			$blockTotal = $block1 + $block2 + $block3;
			$totalOther = floatval($matterCol($matterInfo, 'additional_fee_1', 0));
			$totalDisbursements = floatval($matterCol($matterInfo, 'TotalDisbursements', 0));
			$grandTotal = $blockTotal + $totalDisbursements + $totalOther;

			$formattedBlockTotal = number_format($blockTotal, 2, '.', '');
			$b1 = number_format($block1, 2, '.', '');
			$b2 = number_format($block2, 2, '.', '');
			$b3 = number_format($block3, 2, '.', '');
			$values['Blocktotalfeesincltax'] = $formattedBlockTotal;
			$values['Blocktotalfeesinclgst'] = $formattedBlockTotal;
			$values['Block1feesincltax'] = $b1;
			$values['Block1feesinclgst'] = $b1;
			$values['Block2feesincltax'] = $b2;
			$values['Block2feesinclgst'] = $b2;
			$values['Block3feesincltax'] = $b3;
			$values['Block3feesinclgst'] = $b3;
			$values['TotalDisbursements'] = number_format($totalDisbursements, 2, '.', '');
			$values['TotalEstimatedOthCosts'] = number_format($totalOther, 2, '.', '');
			$values['GrandTotalFeesAndCosts'] = number_format($grandTotal, 2, '.', '');
		}

		return $values;
	}

	/**
	 * Convert plain URLs in HTML content to clickable links (open in new tab, copyable).
	 * URL as link text makes it copyable. Skips URLs inside href="..." (preceded by space/> not ").
	 */
	protected function linkifyUrlsInHtml(string $html): string
	{
		if (empty(trim($html))) {
			return $html;
		}
		// Match URLs preceded by start, whitespace, or > (excludes href="url" where " is before url)
		$pattern = '#(^|[\s>])(https?://[^\s<>"\']+)#i';
		return preg_replace_callback($pattern, function ($m) {
			$prefix = $m[1];
			$url = $m[2];
			$link = '<a href="' . htmlspecialchars($url) . '" target="_blank" rel="noopener noreferrer" style="color:#2563eb;text-decoration:underline;word-break:break-all;">' . htmlspecialchars($url) . '</a>';
			return $prefix . $link;
		}, $html);
	}

	/**
	 * Normalize compose recipient input (array, comma-separated string, or single value).
	 *
	 * @return list<string|int>
	 */
	protected function normalizeComposeRecipientInput(mixed $value): array
	{
		if ($value === null || $value === '') {
			return [];
		}

		if (is_array($value)) {
			return array_values(array_filter(array_map('trim', $value), static fn ($v) => $v !== ''));
		}

		return array_values(array_filter(
			array_map('trim', preg_split('/[,;]/', (string) $value)),
			static fn ($v) => $v !== ''
		));
	}

	/**
	 * Resolve compose To/Cc/Bcc values (Admin/Agent IDs or raw email addresses) to email strings.
	 *
	 * @param  array<int|string>  $values
	 * @return list<string>
	 */
	protected function resolveComposeRecipientEmails(array $values, string $type = 'client'): array
	{
		$emails = [];

		foreach ($values as $value) {
			if ($value === '' || $value === null) {
				continue;
			}

			if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
				$emails[] = $value;
				continue;
			}

			if ($type === 'agent') {
				$row = \App\Models\Staff::where('id', $value)->first();
				$address = $row ? ($row->email ?: $row->business_email) : null;
			} else {
				$row = \App\Models\Admin::where('id', $value)->first();
				$address = $row->email ?? null;
			}

			if (! empty($address)) {
				$emails[] = $address;
			}
		}

		return array_values(array_unique($emails));
	}

	/**
	 * Normalize a compose From header to a single email address.
	 */
	protected function normalizeComposeFromAddress(mixed $value): ?string
	{
		if (! is_string($value) || trim($value) === '') {
			return null;
		}

		$from = trim($value);
		if (preg_match('/<([^>]+)>/', $from, $matches)) {
			$from = trim($matches[1]);
		}

		return filter_var($from, FILTER_VALIDATE_EMAIL) ? $from : null;
	}

	/**
	 * Whether the given address may be used as compose From (active CRM mailbox or system sender).
	 */
	protected function isAllowedComposeFromAddress(string $fromAddress): bool
	{
		$fromAddress = strtolower(trim($fromAddress));
		$routing = app(\App\Services\MailRoutingService::class);

		if ($routing->isSystemFromAddress($fromAddress)) {
			return true;
		}

		$userEmail = strtolower(trim((string) (Auth::user()->email ?? '')));
		if ($userEmail !== '' && $fromAddress === $userEmail) {
			return true;
		}

		return \App\Models\Email::whereRaw('LOWER(email) = ?', [$fromAddress])
			->where('status', true)
			->exists();
	}

	/**
	 * Require CLIENT_ID / MATTER_REF in compose subjects (e.g. CPRE2600130 / CIV_1).
	 * Returns null when valid; otherwise a user-facing error message.
	 */
	protected function validateComposeSubjectHasClientMatterReference(string $subject, $clientId, $clientMatterId): ?string
	{
		$subject = trim($subject);
		if ($clientId === null || $clientId === '' || ! is_numeric($clientId)) {
			return null;
		}

		$client = Admin::find((int) $clientId);
		if (! $client) {
			return null;
		}
		if (($client->type ?? '') === 'lead' || strcasecmp((string) request()->input('type', ''), 'lead') === 0) {
			return null;
		}
		$clientRef = trim((string) ($client->client_id ?? ''));
		if ($clientRef === '') {
			return null;
		}

		$matterRef = '';
		if ($clientMatterId !== null && $clientMatterId !== '' && is_numeric($clientMatterId)) {
			$matter = ClientMatter::find((int) $clientMatterId);
			$matterRef = trim((string) ($matter->client_unique_matter_no ?? ''));
		}

		$ref = $matterRef !== '' ? ($clientRef . ' / ' . $matterRef) : $clientRef;
		$normalizedSubject = preg_replace('/\s+/', ' ', strtoupper($subject)) ?? '';

		if ($matterRef !== '') {
			$pattern = '/' . preg_quote(strtoupper($clientRef), '/') . '\s*\/\s*' . preg_quote(strtoupper($matterRef), '/') . '/';
			if ($normalizedSubject !== '' && preg_match($pattern, $normalizedSubject)) {
				return null;
			}
		} elseif ($normalizedSubject !== '' && stripos($normalizedSubject, strtoupper($clientRef)) !== false) {
			return null;
		}

		return 'Subject must include the matter reference: ' . $ref . ' (at the start or end is fine).';
	}

	/**
	 * Prevent Zoho 554 5.2.3 "Mail Size exceeds limit" bounces by rejecting oversized compose attachments early.
	 *
	 * @param  array<int, mixed>  $uploadedFiles
	 * @param  array<int, mixed>  $existingPaths
	 * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse|null
	 */
	protected function assertComposeAttachmentsWithinSizeLimit(Request $request, array $uploadedFiles = [], array $existingPaths = [])
	{
		$maxBytes = (int) config('crm.compose_max_total_attachment_bytes', 18 * 1024 * 1024);
		if ($maxBytes <= 0) {
			return null;
		}

		$total = 0;
		foreach ($uploadedFiles as $file) {
			if ($file instanceof \Illuminate\Http\UploadedFile) {
				$total += (int) $file->getSize();
			}
		}
		foreach ($existingPaths as $path) {
			if (is_string($path) && $path !== '' && is_file($path)) {
				$total += (int) filesize($path);
			}
		}

		if ($total <= $maxBytes) {
			return null;
		}

		$maxMb = max(1, (int) round($maxBytes / (1024 * 1024)));
		$totalMb = round($total / (1024 * 1024), 1);
		$message = 'Attachments total ' . $totalMb . ' MB, which exceeds the '
			. $maxMb . ' MB send limit. Zoho Mail will bounce oversized messages '
			. '(554 Mail Size exceeds limit). Remove large files or share them as a link instead.';

		if ($request->ajax() || $request->wantsJson()) {
			return response()->json([
				'status' => false,
				'success' => false,
				'message' => $message,
				'error_code' => 'attachments_too_large',
			], 422);
		}

		return redirect()->back()->with('error', $message)->withInput();
	}

    public function sendmail(Request $request){
		$requestData = $request->all();
		// Restore & in subject (front-end sends __AMP__ to avoid WAF 403 on special characters)
		$requestData['subject'] = str_replace('__AMP__', '&', $requestData['subject'] ?? '');
		$subjectRefError = $this->validateComposeSubjectHasClientMatterReference(
			(string) ($requestData['subject'] ?? ''),
			$requestData['client_id'] ?? $requestData['lead_id'] ?? null,
			$requestData['compose_client_matter_id'] ?? null
		);
		if ($subjectRefError !== null) {
			if ($request->ajax() || $request->wantsJson()) {
				return response()->json([
					'status' => false,
					'success' => false,
					'message' => $subjectRefError,
				], 422);
			}

			return redirect()->back()->with('error', $subjectRefError)->withInput();
		}
		if (($requestData['message_encoding'] ?? '') === 'b64') {
			$encodedMessage = $requestData['message'] ?? '';
			if (is_string($encodedMessage) && $encodedMessage !== '') {
				$decodedMessage = base64_decode($encodedMessage, true);
				if ($decodedMessage !== false) {
					$requestData['message'] = $decodedMessage;
				}
			}
		}
		//echo '<pre>'; print_r($requestData); die;

		// Gate on the associated client or lead record
		$associatedAdminId = $requestData['client_id'] ?? $requestData['lead_id'] ?? null;
		if ($associatedAdminId) {
			$this->ensureCrmRecordAccess((int) $associatedAdminId);
		}

		$fromAddress = $this->normalizeComposeFromAddress($requestData['email_from'] ?? null);
		if (! $fromAddress || ! $this->isAllowedComposeFromAddress($fromAddress)) {
            $message = 'Invalid sender address. Use your login email or a configured CRM mailbox.';
			if ($request->ajax() || $request->wantsJson()) {
				return response()->json([
					'status' => false,
					'success' => false,
					'message' => $message,
				], 422);
			}

			return redirect()->back()->with('error', $message);
		}
		$requestData['email_from'] = $fromAddress;

		$user_id = @Auth::user()->id;
		$array = array();

		$resendLogId = (int) ($requestData['resend_email_log_id'] ?? 0);
		$isResend = $resendLogId > 0;

		if ($isResend) {
			$obj = \App\Models\EmailLog::find($resendLogId);
			if (! $obj || (string) ($obj->send_status ?? '') !== \App\Models\EmailLog::SEND_STATUS_FAILED) {
				$message = 'Only failed emails can be resent.';
				if ($request->ajax() || $request->wantsJson()) {
					return response()->json([
						'status' => false,
						'success' => false,
						'message' => $message,
					], 422);
				}

				return redirect()->back()->with('error', $message);
			}
			if ($obj->client_id) {
				$this->ensureCrmRecordAccess((int) $obj->client_id);
			}
			$obj->retry_count = (int) ($obj->retry_count ?? 0) + 1;
		} else {
			$obj = new \App\Models\EmailLog;
			$obj->user_id = $user_id;
		}

		$obj->from_mail 	=  $requestData['email_from'];
        $fromMailbox = strtolower(trim((string) ($requestData['email_from'] ?? '')));
        if ($fromMailbox !== '' && \Illuminate\Support\Facades\Schema::hasColumn('email_logs', 'mailbox_email')) {
            $obj->mailbox_email = $fromMailbox;
        }
        if (\Illuminate\Support\Facades\Schema::hasColumn('email_logs', 'sync_source')) {
            $obj->sync_source = \App\Models\EmailLog::SYNC_SOURCE_COMPOSE;
        }
		// email_to / email_cc are Admin or Agent row IDs from the compose UI — store actual addresses
		$emailToList = $this->normalizeComposeRecipientInput($requestData['email_to'] ?? null);
		$resolvedTo = [];
		foreach ($emailToList as $recipientId) {
			if ($recipientId === '' || $recipientId === null) {
				continue;
			}
			if (filter_var($recipientId, FILTER_VALIDATE_EMAIL)) {
				$resolvedTo[] = $recipientId;
				continue;
			}
			if (($requestData['type'] ?? '') === 'agent') {
				$r = \App\Models\Staff::where('id', $recipientId)->first();
				if ($r) {
					$em = $r->email ?: $r->business_email;
					if ($em) {
						$resolvedTo[] = $em;
					}
				}
			} else {
				$r = \App\Models\Admin::where('id', $recipientId)->first();
				if ($r && ! empty($r->email)) {
					$resolvedTo[] = $r->email;
				}
			}
		}
		$obj->to_mail = $resolvedTo !== []
			? implode(',', array_unique($resolvedTo))
			: implode(',', $emailToList);
		if (! empty($requestData['email_cc'])) {
			$ccEmails = $this->resolveComposeRecipientEmails(
				$this->normalizeComposeRecipientInput($requestData['email_cc']),
				(string) ($requestData['type'] ?? 'client')
			);
			if ($ccEmails !== []) {
				$obj->cc = implode(',', $ccEmails);
			}
		}
		if (! empty($requestData['email_bcc'])) {
			$bccEmails = $this->resolveComposeRecipientEmails(
				$this->normalizeComposeRecipientInput($requestData['email_bcc']),
				(string) ($requestData['type'] ?? 'client')
			);
			if ($bccEmails !== []) {
				$obj->bcc = implode(',', $bccEmails);
			}
		} else {
			$obj->bcc = null;
		}
        $obj->template_id 	=  $requestData['template'] ?? null;
		$obj->subject		=  $requestData['subject'];
		if(isset($requestData['type'])){
		    $obj->type 			=  @$requestData['type'];
		}
		$obj->message		 =  $requestData['message'];
        $obj->mail_type      =  2;
        $obj->mail_body_type =  'sent';
        $obj->fetch_mail_sent_time = now();
        $plainPreview = trim(strip_tags($requestData['message'] ?? ''));
        if ($plainPreview !== '') {
            $obj->text_preview = mb_substr($plainPreview, 0, 200);
        }
        if (! empty($requestData['reply_to_email_id'])) {
            $parentId = (int) $requestData['reply_to_email_id'];
            $parent = \App\Models\EmailLog::find($parentId);
            if ($parent) {
                $obj->thread_info = array_filter([
                    'parent_email_log_id' => $parentId,
                    'in_reply_to' => $parent->message_id ?? null,
                    'is_reply' => true,
                ]);
            }
        }
        $obj->client_id      =  $requestData['client_id'] ?? $requestData['lead_id'] ?? null;
        $obj->client_matter_id =  $requestData['compose_client_matter_id'] ?? null;
        $obj->send_status = \App\Models\EmailLog::SEND_STATUS_PENDING;
        $obj->send_error = null;
        $obj->sent_at = null;
        $obj->failed_at = null;
        if (! $isResend) {
            $obj->user_id = $user_id;
        }

        if ($emailToList === []) {
            $message = 'At least one recipient is required.';
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'status' => false,
                    'success' => false,
                    'message' => $message,
                ], 422);
            }

            return redirect()->back()->with('error', $message)->withInput();
        }

		$saved	=	$obj->save();
        $activityClientId = $requestData['client_id'] ?? $requestData['lead_id'] ?? null;
        if ($activityClientId === null && ! empty($emailToList) && ($requestData['type'] ?? '') !== 'agent') {
            $activityClientId = (int) reset($emailToList);
        }
        if (isset($requestData['checklistfile'])) {
            if (! empty($requestData['checklistfile']) && $activityClientId) {
                ClientActivity::log(
                    (int) $activityClientId,
                    'Checklist sent to client',
                    ClientActivity::TYPE_DOCUMENT
                );
            }
        }

        if (isset($requestData['checklistfile_document'])) {
            if (! empty($requestData['checklistfile_document']) && $activityClientId) {
                ClientActivity::log(
                    (int) $activityClientId,
                    'Document Checklist sent to client',
                    ClientActivity::TYPE_DOCUMENT
                );
            }
        }

		$subject = $requestData['subject'];
		$message = $requestData['message'];

		// Replace First email macros when matter context is present
		$clientMatterIdForMacros = $requestData['compose_client_matter_id'] ?? null;
		$clientIdForMacros = $requestData['client_id'] ?? null;
		if ($clientMatterIdForMacros && $clientIdForMacros) {
			$macroValues = $this->getComposeMacroValues($clientIdForMacros, $clientMatterIdForMacros);
		if ($macroValues) {
			foreach ($macroValues as $key => $val) {
				if ((string)$val === '' || $key === 'PDF_url_for_sign') continue;
				$subject = str_replace('{' . $key . '}', $val, $subject);
				$subject = str_replace('${' . $key . '}', $val, $subject);
				$message = str_replace('{' . $key . '}', $val, $message);
				$message = str_replace('${' . $key . '}', $val, $message);
			}
		}
		}
		// Convert plain URLs to clickable links (open in new tab, copyable)
		$message = $this->linkifyUrlsInHtml($message);

		$matterDocumentPaths = $this->resolveComposeMatterDocumentAttachments(
			$requestData,
			(int) ($requestData['client_id'] ?? $activityClientId ?? 0)
		);

		foreach($emailToList as $l){
			if (filter_var($l, FILTER_VALIDATE_EMAIL)) {
				$client = new \stdClass();
				$client->first_name = '';
				$client->full_name = '';
				$client->email = $l;
			} else {
				if(@$requestData['type'] == 'agent'){
					$client = \App\Models\Staff::Where('id', $l)->first();
				}else{
					$client = \App\Models\Admin::Where('id', $l)->first();
				}
			}

			if ($client) {
				$firstName = $client->first_name ?? $client->full_name ?? '';
				$subject = str_replace('{Client First Name}', $firstName, $subject);
				$message = str_replace('{Client First Name}', $firstName, $message);
				$message = str_replace('{Client Assignee Name}', $firstName, $message);
			}

			$message = str_replace('{Company Name}', optional(Auth::user())->company_name ?? '', $message);

			$array['files'] = $matterDocumentPaths;

			if(isset($requestData['checklistfile'])){
    		    if(!empty($requestData['checklistfile'])){
    		       $checklistfiles = $requestData['checklistfile'];
    		        foreach($checklistfiles as $checklistfile){
    		           $filechecklist =  \App\Models\UploadChecklist::where('id', $checklistfile)->first();
    		           if($filechecklist){
    		               $safePath = realpath(public_path('checklists/' . basename($filechecklist->file)));
    		               $allowedChecklistDir = realpath(public_path('checklists'));
    		               if ($safePath && $allowedChecklistDir && str_starts_with($safePath, $allowedChecklistDir)) {
    		                   $array['files'][] = $safePath;
    		               }
    		           }
    		        }
    		    }
		    }
            //echo "<pre>array=";print_r($array);die;

		    /*if($request->hasfile('attach'))
            {
                 $array['filesatta'][] =  $request->attach;
            }*/

            // Process Uploaded Files
            if ($request->hasFile('attach')) {
                foreach ($request->file('attach') as $file1) {
                    $array['filesatta'][] =  $file1;
                }
            }

            $composeAttachmentBudget = $this->assertComposeAttachmentsWithinSizeLimit(
                $request,
                $array['filesatta'] ?? [],
                $array['files'] ?? []
            );
            if ($composeAttachmentBudget !== null) {
                return $composeAttachmentBudget;
            }

            //dd($client->email,  $requestData['email_from']);
            //$this->send_compose_template($client->email, $subject, $requestData['email_from'], $message, '', $array,@$ccarray);

            try {
                $attachments = [];
                //dd($array['filesatta']);
                if(isset($array['files'])){
                    $attachments = array_merge($attachments, $array['files']);
                }

                if(isset($array['filesatta'])){
                    foreach($array['filesatta'] as $file) {
                        $filename = time().'_'.$file->getClientOriginalName(); // Unique filename
                        $filePath = storage_path('app/uploads/'.$filename); // Save in storage/uploads folder

                        // Move the file to storage folder
                        $file->move(storage_path('app/uploads'), $filename);

                        // Add saved file path to attachments
                        $attachments[] = $filePath;
                    }
                }

                $ccarray = [];
                if (! empty($requestData['email_cc'])) {
                    $ccarray = $this->resolveComposeRecipientEmails(
                        $this->normalizeComposeRecipientInput($requestData['email_cc']),
                        (string) ($requestData['type'] ?? 'client')
                    );
                }

                $bccarray = [];
                if (! empty($requestData['email_bcc'])) {
                    $bccarray = $this->resolveComposeRecipientEmails(
                        $this->normalizeComposeRecipientInput($requestData['email_bcc']),
                        (string) ($requestData['type'] ?? 'client')
                    );
                }

                $this->emailService->sendEmail(
                    'emails.common',
                    ['content' => $message],
                    $client->email,
                    $subject,
                    $requestData['email_from'],
                    $attachments,
                    $ccarray,
                    $bccarray
                );

                // Store full email to S3 for archival (HTML snapshot + attachments)
                try {
                    $attachmentTuples = [];
                    foreach ($attachments as $p) {
                        if (is_string($p) && file_exists($p)) {
                            $attachmentTuples[] = ['path' => $p, 'name' => basename($p)];
                        }
                    }
                    $this->crmSentEmailS3Service->storeToS3($obj, $subject, $message, $attachmentTuples);
                } catch (\Exception $s3Ex) {
                    \Log::warning('CRM sent email S3 storage failed (email still sent)', ['error' => $s3Ex->getMessage()]);
                }

                $obj->send_status = \App\Models\EmailLog::SEND_STATUS_SENT;
                $obj->send_error = null;
                $obj->sent_at = now();
                $obj->failed_at = null;
                $obj->fetch_mail_sent_time = now();
                $obj->save();

                // Timeline: every CRM-sent email for a client/lead
                $timelineClientId = (int) ($obj->client_id ?: $activityClientId ?: 0);
                if ($timelineClientId > 0) {
                    $matterRef = null;
                    if (! empty($obj->client_matter_id)) {
                        $matterRef = ClientMatter::where('id', (int) $obj->client_matter_id)
                            ->value('client_unique_matter_no');
                    }
                    ClientActivity::log(
                        $timelineClientId,
                        EmailTimelineActivity::subjectSent((string) ($obj->subject ?? $subject), $matterRef ?: null),
                        ClientActivity::TYPE_EMAIL,
                        EmailTimelineActivity::descriptionTo((string) ($obj->to_mail ?? $client->email ?? '')),
                        [
                            'source' => 'crm_compose',
                            'use_for' => ! empty($obj->client_matter_id) ? 'matter' : null,
                        ]
                    );
                    if (! empty($obj->client_matter_id)) {
                        ClientMatter::touchRecentActivity((int) $obj->client_matter_id, 'email');
                    }
                }

                // Return JSON response for AJAX requests, redirect for regular form submissions
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json([
                        'status' => true,
                        'success' => true,
                        'message' => 'Email sent successfully!',
                        'email_log_id' => $obj->id,
                    ]);
                }
                return redirect()->back()->with('success', 'Email sent successfully!');
            } catch (\Exception $e) {
                if (isset($obj) && $obj->exists) {
                    $obj->send_status = \App\Models\EmailLog::SEND_STATUS_FAILED;
                    $obj->send_error = mb_substr($e->getMessage(), 0, 2000);
                    $obj->failed_at = now();
                    try {
                        $obj->save();
                    } catch (\Exception $saveEx) {
                        \Log::warning('Failed to persist failed email log', [
                            'email_log_id' => $obj->id ?? null,
                            'error' => $saveEx->getMessage(),
                        ]);
                    }
                }
                // Return JSON response for AJAX requests, redirect for regular form submissions
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json([
                        'status' => false,
                        'success' => false,
                        'message' => 'Failed to send email: ' . $e->getMessage(),
                        'email_log_id' => isset($obj) && $obj->exists ? $obj->id : null,
                        'send_status' => \App\Models\EmailLog::SEND_STATUS_FAILED,
                    ], 422);
                }
                return redirect()->back()->with('error', 'Failed to send email: ' . $e->getMessage())->withInput();
            }
        }
        if(!empty($array['file'])){
            unset($array['file']);
        }
        if(!$saved) {
            // Return JSON response for AJAX requests, redirect for regular form submissions
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'status' => false,
                    'success' => false,
                    'message' => config('constants.server_error')
                ], 500);
            }
            return redirect()->back()->with('error', config('constants.server_error'));
        } else {
            // Return JSON response for AJAX requests, redirect for regular form submissions
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'status' => true,
                    'success' => true,
                    'message' => 'Email Sent Successfully',
                    'email_log_id' => $obj->id,
                ]);
            }
            return redirect()->back()->with('success', 'Email Sent Successfully');
        }
	}

    public function checkclientexist(Request $request){
        $actor = Auth::guard('admin')->user() ?: Auth::user();
        if (!$actor) {
            echo 0;
            return;
        }

        $val = trim((string) $request->vl);
        if ($val === '') {
            echo 0;
            return;
        }

        if ($request->type == 'email') {
            $query = \App\Models\Admin::where('email', $val)->whereIn('type', ['client', 'lead']);
        } else if ($request->type == 'clientid') {
            $query = \App\Models\Admin::where('client_id', $val)->whereIn('type', ['client', 'lead']);
        } else {
            $query = \App\Models\Admin::where('phone', $val)->whereIn('type', ['client', 'lead']);
        }

        \App\Support\StaffClientVisibility::restrictAdminEloquentQuery($query);

        if ($query->exists()) {
            return response('1');
        }

        return response('0');
    }

	public function allnotification(Request $request){
		$reopenNotifService = app(\App\Services\MatterReopenNotificationService::class);
		$reopenNotifService->reassertUnreadForReceiver((int) Auth::user()->id);

		$perPage = 20;
		$lists = $reopenNotifService
			->orderWithStickyFirst(
				\App\Models\Notification::where('receiver_id', Auth::user()->id)
			)
			->paginate($perPage)
			->appends($request->except('page', 'infinite'));
		// Fix URLs for notifications that point to non-existent or wrong routes
		$lists->getCollection()->transform(function ($notification) {
			// Message notifications: /messages (404) -> client detail + Workflow tab
			if ($notification->notification_type === 'message' && ($notification->url === '/messages' || str_starts_with($notification->url ?? '', '/messages'))) {
				$clientMatter = \DB::table('client_matters')->where('id', $notification->module_id)->first();
				if ($clientMatter) {
					$path = '/clients/detail/' . base64_encode(convert_uuencode($clientMatter->client_id));
					if (!empty($clientMatter->client_unique_matter_no)) {
						$path .= '/' . $clientMatter->client_unique_matter_no;
					}
					$notification->url = url($path . '/workflow');
				}
			}
			// Broadcast notifications: /broadcasts/{uuid} -> all notifications (manage console removed)
			if ($notification->notification_type === 'broadcast' && str_starts_with($notification->url ?? '', '/broadcasts/')) {
				$batchUuid = \Illuminate\Support\Str::afterLast($notification->url ?? '', '/');
				if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $batchUuid)) {
					$notification->url = route('crm.all-notifications');
				}
			}
			// Retired Matter tab used URL segment "client_portal"; remap stored links to Workflow.
			$rawUrl = $notification->url ?? '';
			if (is_string($rawUrl) && str_ends_with($rawUrl, '/client_portal')) {
				$notification->url = \Illuminate\Support\Str::replaceEnd('/client_portal', '/workflow', $rawUrl);
			}
			return $notification;
		});

		// Infinite scroll append — rows only (this page only)
		if ($request->boolean('infinite')) {
			$html = view('crm.notifications.partials.notification_rows', [
				'lists' => $lists,
			])->render();

			return response()->json([
				'html' => $html,
				'current_page' => $lists->currentPage(),
				'last_page' => $lists->lastPage(),
				'total' => $lists->total(),
				'from' => $lists->firstItem(),
				'to' => $lists->lastItem(),
				'has_more' => $lists->hasMorePages(),
				'loaded' => $lists->count(),
			]);
		}

		return view('crm.notifications', compact(['lists']));
	}

    // Dashboard methods moved to DashboardController

	//Get matter templates
	public function getmattertemplates(Request $request){
		$id = $request->id;
		$template = \App\Models\EmailTemplate::find($id);
		if ($template) {
			echo json_encode(array('subject' => $template->subject, 'description' => $template->description));
		} else {
			echo json_encode(array('subject' => '', 'description' => ''));
		}
	}

    // Column preferences method moved to DashboardController

    /**
     * Get office visit notifications (optimized - no N+1 queries)
     */
    public function fetchOfficeVisitNotifications(Request $request)
    {
        // Fetch notifications with sender relationship eager loaded
        $notifications = \App\Models\Notification::with(['sender:id,first_name,last_name'])
            ->where('receiver_id', Auth::id())
            ->where('notification_type', 'officevisit')
            ->where('receiver_status', 0)
            ->orderBy('created_at', 'DESC')
            ->get();

        if ($notifications->isEmpty()) {
            return response()->json(['notifications' => [], 'count' => 0]);
        }

        // Batch-load all checkin logs (eliminates N+1)
        $checkinLogIds = $notifications->pluck('module_id')->filter()->unique();
        $checkinLogs = \App\Models\CheckinLog::whereIn('id', $checkinLogIds)
            ->where('status', 0)
            ->get()
            ->keyBy('id');

        // If no active checkin logs, return empty
        if ($checkinLogs->isEmpty()) {
            return response()->json(['notifications' => [], 'count' => 0]);
        }

        $clientIds = $checkinLogs->pluck('client_id')->filter()->unique()->values();
        $contacts = $clientIds->isNotEmpty()
            ? \App\Models\Admin::whereIn('type', ['client', 'lead'])->whereIn('id', $clientIds)->get()->keyBy('id')
            : collect();

        // Build response data
        $data = [];
        foreach ($notifications as $notification) {
            $checkinLog = $checkinLogs->get($notification->module_id);
            
            if (!$checkinLog) {
                continue;
            }

            $preloaded = $checkinLog->client_id ? $contacts->get($checkinLog->client_id) : null;

            $data[] = [
                'id' => $notification->id,
                'checkin_id' => $checkinLog->id,
                'message' => $notification->message,
                'sender_name' => $notification->sender 
                    ? $notification->sender->first_name . ' ' . $notification->sender->last_name 
                    : 'System',
                'client_name' => $checkinLog->contactDisplayLabel($preloaded),
                'visit_purpose' => $checkinLog->visit_purpose,
                'created_at' => $notification->created_at->format('d/m/Y h:i A'),
                'url' => $notification->url
            ];
        }

        return response()->json(['notifications' => $data, 'count' => count($data)]);
    }

    /**
     * Get in-person waiting count
     */
    public function fetchInPersonWaitingCount(Request $request)
    {
        $InPersonwaitingCount = \App\Models\CheckinLog::inPersonWaitingCountForViewer();

        return response()->json(['InPersonwaitingCount' => $InPersonwaitingCount]);
    }

    /**
     * Get total activity count
     */
    public function fetchTotalActivityCount(Request $request)
    {
        $viewer = Auth::user();
        $seeAll = $viewer instanceof Staff && $viewer->hasEffectiveSuperAdminPrivileges();
        if ($seeAll) {
            $assigneesCount = \App\Models\Note::where('type', 'client')
                ->whereNotNull('client_id')
                ->where('is_action', 1)
                ->where('status', 0)
                ->count();
        } else {
            $assigneesCount = \App\Models\Note::where('assigned_to', Auth::user()->id)
                ->where('type', 'client')
                ->where('is_action', 1)
                ->where('status', 0)
                ->count();
        }
        
        return response()->json(['assigneesCount' => $assigneesCount]);
    }

    /**
     * Mark notification as seen
     */
    public function markNotificationSeen(Request $request)
    {
        $notification = \App\Models\Notification::find($request->notification_id);
        
        if (!$notification || $notification->receiver_id != Auth::id()) {
            return response()->json(['status' => 'error']);
        }

        $marked = app(\App\Services\MatterReopenNotificationService::class)
            ->tryMarkAsRead($notification, (int) Auth::id());

        return response()->json([
            'status' => $marked ? 'success' : 'sticky',
            'sticky' => ! $marked && ($notification->notification_type ?? '') === \App\Services\MatterReopenNotificationService::TYPE_REQUEST,
        ]);
    }

    /**
     * Resolve ticked Matter documents tab files for compose send.
     *
     * @param  array<string, mixed>  $requestData
     * @return list<string>
     */
    protected function resolveComposeMatterDocumentAttachments(array $requestData, int $composeClientId): array
    {
        $ids = $requestData['checklistfile_document'] ?? [];
        if (! is_array($ids) || $ids === [] || $composeClientId <= 0) {
            return [];
        }

        $composeClientMatterId = ! empty($requestData['compose_client_matter_id'])
            ? (int) $requestData['compose_client_matter_id']
            : 0;
        $isLeadCompose = strcasecmp((string) ($requestData['type'] ?? ''), 'lead') === 0;

        $paths = [];
        foreach ($ids as $documentId) {
            $document = \App\Models\Document::find($documentId);
            if (! $document) {
                continue;
            }
            $docOwnerId = (int) ($document->client_id ?: $document->lead_id);
            if ($docOwnerId !== $composeClientId) {
                continue;
            }
            if ($composeClientMatterId > 0 && (int) $document->client_matter_id !== $composeClientMatterId) {
                continue;
            }
            if (! $isLeadCompose && $composeClientMatterId <= 0) {
                continue;
            }
            if (! in_array((string) $document->doc_type, ['matter', 'visa'], true)) {
                continue;
            }

            $attachment = $this->composeMatterDocumentService->attachmentForEmail($document);
            if ($attachment && ! empty($attachment['path']) && is_readable($attachment['path'])) {
                $paths[] = $attachment['path'];
            }
        }

        return $paths;
    }
}