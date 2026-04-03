<?php
namespace App\Http\Controllers\AdminConsole;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redirect;

use App\Models\Admin;
use App\Models\Company;
use App\Models\UserRole;

use Auth;

/**
 * ClientController - Manages clients (type=client/lead in admins table).
 *
 * DEPRECATED SPLIT: Former UserController was split into:
 * - StaffController + staff table: Staff management (active, inactive, invited, create, edit, view).
 * - This ClientController: Client management (clientlist, createclient, editclient, etc.).
 * Staff CRUD is now at adminconsole.staff.* routes.
 */
class ClientController extends Controller
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

	public function clientlist(Request $request)
	{
		//check authorization start
			$check = $this->checkAuthorizationAction('user_management', $request->route()->getActionMethod(), Auth::user()->role);
			if($check)
			{
				return Redirect::to('/dashboard')->with('error',config('constants.unauthorized'));
			}
		//check authorization end
		$query 		= Admin::with('company')->whereIn('type', ['client', 'lead']);

		$totalData 	= $query->count();	//for all data

		$lists		= $query->sortable(['id' => 'desc'])->paginate(config('constants.limit'));

		return view('AdminConsole.system.clients.clientlist',compact(['lists', 'totalData']));
	}

	public function createclient(Request $request)
	{
		//check authorization start
			$check = $this->checkAuthorizationAction('user_management', $request->route()->getActionMethod(), Auth::user()->role);
			if($check)
			{
				return Redirect::to('/dashboard')->with('error',config('constants.unauthorized'));
			}
		//check authorization end
		return view('AdminConsole.system.clients.createclient');
	}

	public function storeclient(Request $request)
	{
		//check authorization start
			$check = $this->checkAuthorizationAction('user_management', $request->route()->getActionMethod(), Auth::user()->role);
			if($check)
			{
				return Redirect::to('/dashboard')->with('error',config('constants.unauthorized'));
			}
		//check authorization end
		if ($request->isMethod('post'))
		{
			$this->validate($request, [
										'company_name' => 'required|max:255',
										'first_name' => 'required|max:255',
										'last_name' => 'required|max:255',
										'company_website' => 'required|max:255',
										'email' => 'required|max:255|unique:admins',
										'password' => 'required|max:255',
										'phone' => 'required|max:255'
									  ]);

			$requestData 		= 	$request->all();

			$obj				= 	new Admin;
			$obj->is_company	=	1;
			$obj->first_name	=	@$requestData['first_name'];
			$obj->last_name		=	@$requestData['last_name'];
			$obj->email			=	@$requestData['email'];
			$obj->password	=	Hash::make(@$requestData['password']);
			$obj->phone	=	@$requestData['phone'];
			$obj->country	=	@$requestData['country'];
			$obj->city	=	@$requestData['city'];
			$obj->verified	=	1;
			$obj->type	=	'client';
			
			// Set required NOT NULL fields with default values (PostgreSQL doesn't apply DB defaults on explicit INSERT)
			$obj->australian_study = isset($requestData['australian_study']) ? (int)$requestData['australian_study'] : 0;
			$obj->specialist_education = isset($requestData['specialist_education']) ? (int)$requestData['specialist_education'] : 0;
			$obj->regional_study = isset($requestData['regional_study']) ? (int)$requestData['regional_study'] : 0;
			$obj->cp_status = isset($requestData['cp_status']) ? (int)$requestData['cp_status'] : 0;
			$obj->cp_code_verify = isset($requestData['cp_code_verify']) ? (int)$requestData['cp_code_verify'] : 0;

			$saved				=	$obj->save();

			if(!$saved)
			{
				return redirect()->back()->with('error', config('constants.server_error'));
			}

			Company::create([
				'admin_id' => $obj->id,
				'company_name' => @$requestData['company_name'],
				'company_website' => @$requestData['company_website'],
				'created_at' => now(),
				'updated_at' => now(),
			]);

			return redirect()->route('adminconsole.system.clients.clientlist')->with('success', 'Client Added Successfully');
		}

		return view('AdminConsole.system.clients.createclient');
	}

	/**
	 * Show the form for editing the specified client.
	 */
	public function editclient($id)
	{
		//check authorization start
			$check = $this->checkAuthorizationAction('user_management', 'editclient', Auth::user()->role);
			if($check)
			{
				return Redirect::to('/dashboard')->with('error',config('constants.unauthorized'));
			}
		//check authorization end
		$usertype = UserRole::all();
		
		if(isset($id) && !empty($id))
		{
			$id = $this->decodeString($id);
			if(Admin::where('id', '=', $id)->exists())
			{
				$fetchedData = Admin::with('company')->find($id);
				return view('AdminConsole.system.clients.editclient', compact(['fetchedData', 'usertype']));
			}
			else
			{
				return redirect()->route('adminconsole.system.clients.clientlist')->with('error', 'Client Not Exist');
			}
		}
		else
		{
			return redirect()->route('adminconsole.system.clients.clientlist')->with('error', config('constants.unauthorized'));
		}
	}

	/**
	 * Update the specified client in storage.
	 */
	public function updateclient(Request $request, $id)
	{
		//check authorization start
			$check = $this->checkAuthorizationAction('user_management', 'updateclient', Auth::user()->role);
			if($check)
			{
				return Redirect::to('/dashboard')->with('error',config('constants.unauthorized'));
			}
		//check authorization end
		
		$requestData = $request->all();

		$this->validate($request, [
			'company_name' => 'required|max:255',
			'first_name' => 'required|max:255',
			'last_name' => 'required|max:255',
			'company_website' => 'required|max:255',
			'email' => 'required|max:255|unique:admins,email,'.$id,
			'password' => 'required|max:255',
			'phone' => 'required|max:255'
		]);

		$obj = Admin::find($id);
		if (!$obj) {
			return redirect()->route('adminconsole.system.clients.clientlist')->with('error', 'Client Not Found');
		}

		$obj->first_name = @$requestData['first_name'];
		$obj->last_name = @$requestData['last_name'];
		$obj->email = @$requestData['email'];

		$company = Company::firstOrNew(['admin_id' => $obj->id]);
		$company->company_name = @$requestData['company_name'];
		$company->company_website = @$requestData['company_website'];
		$company->save();

		if(!empty(@$requestData['password']))
		{
			$obj->password = Hash::make(@$requestData['password']);
		}
		
		$obj->phone = @$requestData['phone'];
		$obj->country = @$requestData['country'];
		$obj->city = @$requestData['city'];
		$obj->type = 'client';

		$saved = $obj->save();

		if(!$saved)
		{
			return redirect()->back()->with('error', config('constants.server_error'));
		}
		else
		{
			return redirect()->route('adminconsole.system.clients.clientlist')->with('success', 'Client Updated Successfully');
		}
	}
}
