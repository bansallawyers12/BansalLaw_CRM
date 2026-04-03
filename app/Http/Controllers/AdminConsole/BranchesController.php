<?php
namespace App\Http\Controllers\AdminConsole;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;

use App\Models\Admin;
use App\Models\Branch;
 
use Auth;

class BranchesController extends Controller
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
			
			/* if($check)
			{
				return Redirect::to('/dashboard')->with('error',config('constants.unauthorized'));
			} */	
		//check authorization end
	
		 $query 		= Branch::query(); 
		 
		$totalData 	= $query->count();	//for all data
		
		$lists		= $query->sortable(['id' => 'desc'])->paginate(config('constants.limit'));
		
		return view('AdminConsole.system.offices.index',compact(['lists', 'totalData'])); 	
		
		//return view('AdminConsole.system.offices.index');	 
	}
	
	public function create(Request $request)
	{
		//check authorization end
		//return view('AdminConsole\.system\.users\.create',compact(['usertype']));	
		
		return view('AdminConsole.system.offices.create');	
	}
	
	public function store(Request $request)
	{		
		//check authorization end
		if ($request->isMethod('post')) 
		{
			$this->validate($request, [
										'office_name' => 'required|max:255',
										'country' => 'required|max:255',
										'email' => 'required|max:255'
									  ]);
			
			$requestData 		= 	$request->all();
			
			$obj				= 	new Branch; 
			$obj->office_name	=	@$requestData['office_name'];
			$obj->address	=	@$requestData['address'];
			$obj->city	=	@$requestData['city'];
			$obj->state	=	@$requestData['state'];
			$obj->zip	=	@$requestData['zip'];
			$obj->country	=	@$requestData['country'];
			$obj->email	=	@$requestData['email']; 
			$obj->phone	=	@$requestData['phone'];
			$obj->mobile	=	@$requestData['mobile'];
			$obj->contact_person	=	@$requestData['contact_person'];
			$obj->choose_admin	=	@$requestData['choose_admin'];
			
			$saved				=	$obj->save();  
			
			if(!$saved)
			{
				return redirect()->back()->with('error', config('constants.server_error'));
			}
			else
			{
				return redirect()->route('adminconsole.system.offices.index')->with('success', 'Branch Added Successfully');
			}				
		}	

		return view('AdminConsole.system.offices.create');	
	}
	
	/**
	 * Show the form for editing the specified branch.
	 */
	public function edit($id)
	{
		//check authorization end
		
		if(isset($id) && !empty($id))
		{
			$id = $this->decodeString($id);	
			if(Branch::where('id', '=', $id)->exists()) 
			{
				$fetchedData = Branch::find($id);
				return view('AdminConsole.system.offices.edit', compact(['fetchedData']));
			}
			else 
			{
				return redirect()->route('adminconsole.system.offices.index')->with('error', 'Branch Not Exist');
			}	
		} 
		else
		{
			return redirect()->route('adminconsole.system.offices.index')->with('error', config('constants.unauthorized'));
		}
	}

	/**
	 * Update the specified branch in storage.
	 */
	public function update(Request $request, $id)
	{
		//check authorization end
		
		$requestData = $request->all();
		
		$this->validate($request, [										
									'office_name' => 'required|max:255',
									'country' => 'required|max:255',
									'email' => 'required|max:255'
								  ]);
							  					  
		$obj = Branch::find($id);
		if (!$obj) {
			return redirect()->route('adminconsole.system.offices.index')->with('error', 'Branch Not Found');
		}
					
		$obj->office_name = @$requestData['office_name'];
		$obj->address = @$requestData['address'];
		$obj->city = @$requestData['city'];
		$obj->state = @$requestData['state'];
		$obj->zip = @$requestData['zip'];
		$obj->country = @$requestData['country'];
		$obj->email = @$requestData['email'];
		$obj->phone = @$requestData['phone'];
		$obj->mobile = @$requestData['mobile'];
		$obj->contact_person = @$requestData['contact_person'];
		$obj->choose_admin = @$requestData['choose_admin'];
		
		$saved = $obj->save();
		
		if(!$saved)
		{
			return redirect()->back()->with('error', config('constants.server_error'));
		}
		else
		{
			return redirect()->route('adminconsole.system.offices.index')->with('success', 'Branch Updated Successfully');
		}				
	}
	
	public function view(Request $request, $id = NULL)
	{
			
			if(isset($id) && !empty($id))
			{
				
				
				if(Branch::where('id', '=', $id)->exists()) 
				{
					$fetchedData = Branch::find($id);
					return view('AdminConsole.system.offices.view', compact(['fetchedData']));
				}
				else 
				{
					return redirect()->route('adminconsole.system.offices.index')->with('error', 'Branch Not Exist');
				}	
			} 
			else
			{
				return redirect()->route('adminconsole.system.offices.index')->with('error', config('constants.unauthorized'));
			}		
		 	
		
	}
	
	public function viewclient(Request $request, $id = NULL)
	{
			
			if(isset($id) && !empty($id))
			{
				
				
				if(Branch::where('id', '=', $id)->exists()) 
				{
					$fetchedData = Branch::find($id);
					return view('AdminConsole.system.offices.viewclient', compact(['fetchedData']));
				}
				else 
				{
					return redirect()->route('adminconsole.system.offices.index')->with('error', 'Branch Not Exist');
				}	
			} 
			else
			{
				return redirect()->route('adminconsole.system.offices.index')->with('error', config('constants.unauthorized'));
			}		
		 	
		
	}
}


