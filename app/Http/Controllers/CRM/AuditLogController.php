<?php
namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;

use App\Models\Admin;
use App\Models\StaffLoginLog;
 
use Auth;

class AuditLogController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth:admin');
    }
	/**
     * All Vendors.
     *
     * @return \Illuminate\Http\Response
     */
	public function index(Request $request)  
	{		
		$user = Auth::guard('admin')->user();
		if (!$user) {
			abort(401, 'Unauthenticated');
		}

		$isSuperAdmin = method_exists($user, 'hasEffectiveSuperAdminPrivileges') && $user->hasEffectiveSuperAdminPrivileges();
		$isAdminRole = in_array((int) ($user->role ?? 0), [1, 12, 17], true);

		if (!$isSuperAdmin && !$isAdminRole) {
			abort(403, 'Unauthorized access to audit logs.');
		}

		$query 		= StaffLoginLog::query(); 
		$totalData 	= $query->count();	//for all data
		$lists		= $query->sortable(['id' => 'desc'])->paginate(20);
		return view('crm.auditlogs.index', compact(['lists', 'totalData']));
	}
	
	
}
