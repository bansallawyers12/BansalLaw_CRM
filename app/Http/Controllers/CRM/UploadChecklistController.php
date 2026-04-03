<?php
namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;

use App\Models\Admin;
use App\Models\UploadChecklist; 
  
use Auth;

class UploadChecklistController extends Controller
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
		$query 		= UploadChecklist::query(); 
		$totalData 	= $query->count();	//for all data
		$lists		= $query->sortable(['id' => 'desc'])->paginate(config('constants.limit'));
		// Dropdown: Active Matter list
		$matterIds = DB::table('matters')->select('id','title','nick_name')->where('status','1')->orderBy('id', 'asc')->get();
		return view('crm.uploadchecklist.index',compact(['lists', 'totalData','matterIds'])); 	
	}
	
	 
	public function store(Request $request)
	{		
		//check authorization end
		if ($request->isMethod('post')) 
		{
			$this->validate($request, ['name' => 'required|max:255']);
			$requestData 	= 	$request->all();
			$obj			= 	new UploadChecklist; 
			$obj->matter_id	=	@$requestData['matter_id'];
			$obj->name		=	@$requestData['name'];
			/* Profile Image Upload Function Start */						  
			if($request->hasfile('checklists')) 
			{	
				$checklists = $this->uploadFile($request->file('checklists'), config('constants.checklists'));
			}
			else
			{
				$checklists = NULL;
			}		
			/* Profile Image Upload Function End */	
			$obj->file		=	@$checklists;
			$saved			=	$obj->save();  
			
			if(!$saved)
			{
				return redirect()->back()->with('error', config('constants.server_error'));
			}
			else
			{
				// Redirect back to matter-specific page if matter_id is provided
				if (!empty($requestData['matter_id'])) {
					return Redirect::to('/upload-checklists/matter/' . $requestData['matter_id'])->with('success', 'Record Added Successfully');
				} else {
					return Redirect::to('/upload-checklists')->with('success', 'Record Added Successfully');
				}
			}				
		}	
	}

	/**
     * Show checklists for a specific matter.
     *
     * @param int $matterId
     * @return \Illuminate\Http\Response 
     */
	public function showByMatter($matterId)
	{
		$query 		= UploadChecklist::where('matter_id', $matterId); 
		$totalData 	= $query->count();
		$lists		= $query->sortable(['id' => 'desc'])->paginate(config('constants.limit'));
		
		// Get matter information
		$matter = DB::table('matters')->select('id','title','nick_name')->where('id', $matterId)->where('status','1')->first();
		
		if (!$matter) {
			return redirect()->back()->with('error', 'Matter not found');
		}
		
		// Dropdown: Active Matter list for form
		$matterIds = DB::table('matters')->select('id','title','nick_name')->where('status','1')->orderBy('id', 'asc')->get();
		
		return view('crm.uploadchecklist.index', compact(['lists', 'totalData', 'matterIds', 'matter'])); 	
	}
}
