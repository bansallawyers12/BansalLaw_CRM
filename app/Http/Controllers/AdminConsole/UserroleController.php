<?php
namespace App\Http\Controllers\AdminConsole;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;

use App\Models\Admin;
use App\Models\UserRole;

use Auth;

class UserroleController extends Controller
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
		//check authorization start	
			$check = $this->checkAuthorizationAction('user_role', $request->route()->getActionMethod(), Auth::user()->role);
			if($check)
			{
				return Redirect::to('/dashboard')->with('error',config('constants.unauthorized'));
			}	
		//check authorization end
		$query 		= UserRole::query();
		 
		$totalData 	= $query->count();	//for all data

		$lists		= $query->sortable(['id' => 'desc'])->paginate(config('constants.limit'));
		
		return view('AdminConsole.system.roles.index',compact(['lists', 'totalData']));	

		//return view('crm.usertype.index');	
	}
	
	public function create(Request $request) 
	{
			//check authorization start	
			$check = $this->checkAuthorizationAction('user_role', $request->route()->getActionMethod(), Auth::user()->role);
			if($check)
			{
				return Redirect::to('/dashboard')->with('error',config('constants.unauthorized'));
			}	
		//check authorization end
		return view('AdminConsole.system.roles.create');	
	} 
	
	public function store(Request $request)
	{
		//check authorization start	
			$check = $this->checkAuthorizationAction('user_role', $request->route()->getActionMethod(), Auth::user()->role);
			if($check)
			{
				return Redirect::to('/dashboard')->with('error',config('constants.unauthorized'));
			}	
		//check authorization end
		if ($request->isMethod('post')) 
		{
			$this->validate($request, [
										//'usertype' => 'required|max:255|unique:user_roles',
										
									  ]);
			
			$requestData 		= 	$request->all();
			
			$obj				= 	new UserRole;
			$obj->name	=	@$requestData['name'];
			$obj->description	=	@$requestData['description'];
			$obj->module_access	=	json_encode(@$requestData['module_access']);
			
			$saved				=	$obj->save(); 
			
			if(!$saved)
			{
				return redirect()->back()->with('error', config('constants.server_error'));
			}
			else
			{
				return redirect()->route('adminconsole.system.roles.index')->with('success', 'User Role added Successfully');
			}				
		}	

		return view('AdminConsole.system.roles.create');	
	}
	
	/**
	 * Show the form for editing the specified user role.
	 */
	public function edit($id)
	{			
		//check authorization start	
			$check = $this->checkAuthorizationAction('user_role', 'edit', Auth::user()->role);
			if($check)
			{
				return Redirect::to('/dashboard')->with('error',config('constants.unauthorized'));
			}	
		//check authorization end
		
		if(isset($id) && !empty($id))
		{
			$id = $this->decodeString($id);	
			if(UserRole::where('id', '=', $id)->exists()) 
			{
				$fetchedData = UserRole::find($id);
				return view('AdminConsole.system.roles.edit', compact(['fetchedData']));
			}
			else
			{
				return redirect()->route('adminconsole.system.roles.index')->with('error', 'User Role Not Exist');
			}	
		}
		else
		{
			return redirect()->route('adminconsole.system.roles.index')->with('error', config('constants.unauthorized'));
		}
	}

	/**
	 * Update the specified user role in storage.
	 */
	public function update(Request $request, $id)
	{			
		//check authorization start	
			$check = $this->checkAuthorizationAction('user_role', 'update', Auth::user()->role);
			if($check)
			{
				return Redirect::to('/dashboard')->with('error',config('constants.unauthorized'));
			}	
		//check authorization end
		
		$requestData = $request->all();
		
		/* $this->validate($request, [
									'usertype' => 'required|max:255|unique:user_roles,usertype,'.$id
								  ]); */									  
								  
		$obj = UserRole::find($id);
		if (!$obj) {
			return redirect()->route('adminconsole.system.roles.index')->with('error', 'User Role Not Found');
		}
		
		$obj->name = @$requestData['name'];
		$obj->description = @$requestData['description'];
		$obj->module_access = json_encode(@$requestData['module_access']);
		
		$saved = $obj->save();
		
		if(!$saved)
		{
			return redirect()->back()->with('error', config('constants.server_error'));
		}
		else
		{
			return redirect()->route('adminconsole.system.roles.index')->with('success', 'User Role Updated Successfully');
		}				
	}
}


