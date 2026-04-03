<?php
namespace App\Http\Controllers\AdminConsole;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;

use App\Models\Admin;
use App\Models\Team; 
  
use Auth;

class TeamController extends Controller
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
	
		$query 		= Team::query(); 
		 
		$totalData 	= $query->count();	//for all data
		
		$lists		= $query->sortable(['id' => 'desc'])->paginate(config('constants.limit'));
		
		return view('AdminConsole.system.teams.index',compact(['lists', 'totalData'])); 	
		
		//return view('AdminConsole\.features\.producttype.index');	 
	}
	
	/**
	 * Show the form for editing the specified team.
	 */
	public function edit($id)
	{
		if(isset($id) && !empty($id))
		{
			if(Team::where('id', '=', $id)->exists()) 
			{
				$fetchedData = Team::find($id);
				$query = Team::query(); 
				$totalData = $query->count();	//for all data
				$lists = $query->sortable(['id' => 'desc'])->paginate(config('constants.limit'));
				return view('AdminConsole.system.teams.index', compact(['fetchedData','lists','totalData']));
			}
			else 
			{
				return redirect()->route('adminconsole.system.teams.index')->with('error', 'Team Not Exist');
			}	
		}
		else
		{
			return redirect()->route('adminconsole.system.teams.index')->with('error', config('constants.unauthorized'));
		}
	}

	/**
	 * Update the specified team in storage.
	 */
	public function update(Request $request, $id)
	{
		$this->validate($request, [
			'name' => 'required|max:255'
		]);
		
		$requestData = $request->all();
	
		$obj = Team::find($id); 
		if (!$obj) {
			return redirect()->route('adminconsole.system.teams.index')->with('error', 'Team Not Found');
		}
		
		$obj->name = @$requestData['name'];
		$obj->color = @$requestData['color'];
		$saved = $obj->save();  
		
		if(!$saved)
		{
			return redirect()->back()->with('error', config('constants.server_error'));
		}
		else
		{
			return redirect()->route('adminconsole.system.teams.index')->with('success', 'Record Updated Successfully');
		}	
	}
	 
	public function store(Request $request)
	{		
		//check authorization end
		if ($request->isMethod('post')) 
		{
			$this->validate($request, [
										'name' => 'required|max:255'
									  ]);
			
			$requestData 		= 	$request->all();
		
			$obj				= 	new Team; 
			$obj->name	        =	@$requestData['name'];
			$obj->color			=	@$requestData['color'];
			$saved				=	$obj->save();  
			
			if(!$saved)
			{
				return redirect()->back()->with('error', config('constants.server_error'));
			}
			else
			{
				return redirect()->route('adminconsole.system.teams.index')->with('success', 'Record Added Successfully');
			}				
		}	

		
	}
	

}


