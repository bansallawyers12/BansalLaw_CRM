<?php
namespace App\Http\Controllers\AdminConsole;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;

use App\Models\Admin;
use App\Models\Tag; 
  
use Auth;

class TagController extends Controller
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
	
		$query 		= Tag::with(['createddetail', 'updateddetail']); 
		
		// Filter by tag type if provided
		if ($request->has('tag_type') && $request->tag_type != '') {
			$query->where('tag_type', $request->tag_type);
		}
		 
		$totalData 	= $query->count();	//for all data
		
		$lists		= $query->sortable(['id' => 'desc'])->paginate(config('constants.limit'));
		
		return view('AdminConsole.features.tags.index',compact(['lists', 'totalData'])); 	
		
		//return view('AdminConsole\.features\.producttype.index');	 
	}
	
	public function create(Request $request)
	{
		//check authorization end
		//return view('AdminConsole\.system\.users\.create',compact(['usertype']));	
		
		return view('AdminConsole.features.tags.create');	
	}
	 
	public function store(Request $request)
	{		
		//check authorization end
		if ($request->isMethod('post')) 
		{
			$this->validate($request, [
										'name' => 'required|max:255',
										'tag_type' => 'required|in:normal,red'
									  ]);
			
			$requestData 		= 	$request->all();
			
			$obj				= 	new Tag; 
			$obj->name	=	@$requestData['name'];
			$obj->tag_type	=	@$requestData['tag_type'] ?? Tag::TYPE_NORMAL;
			$obj->is_hidden	=	(@$requestData['tag_type'] === Tag::TYPE_RED) ? true : false;
			$obj->created_by	=	Auth::user()->id;
			$saved				=	$obj->save();  
			
			if(!$saved)
			{
				return redirect()->back()->with('error', config('constants.server_error'));
			}
			else
			{
				return redirect()->route('adminconsole.features.tags.index')->with('success', 'Record Added Successfully');
			}				
		}	

		return view('AdminConsole.features.tags.create');	
	}
	
	/**
	 * Show the form for editing the specified tag.
	 */
	public function edit($id)
	{
		//check authorization end
		
		if(isset($id) && !empty($id))
		{
			$id = $this->decodeString($id);	
			if(Tag::where('id', '=', $id)->exists()) 
			{
				$fetchedData = Tag::find($id);
				return view('AdminConsole.features.tags.edit', compact(['fetchedData']));
			}
			else 
			{
				return redirect()->route('adminconsole.features.tags.index')->with('error', 'Record Not Exist');
			}	
		} 
		else
		{
			return redirect()->route('adminconsole.features.tags.index')->with('error', config('constants.unauthorized'));
		}		 
	}

	/**
	 * Update the specified tag in storage.
	 */
	public function update(Request $request, $id)
	{
		//check authorization end
		
		$requestData = $request->all();
		
		$this->validate($request, [										
									'name' => 'required|max:255',
									'tag_type' => 'required|in:normal,red'
								  ]);
							  					  
		$obj = Tag::find($id);
		if (!$obj) {
			return redirect()->route('adminconsole.features.tags.index')->with('error', 'Record Not Found');
		}
		
		$obj->updated_by = Auth::user()->id;			
		$obj->name = @$requestData['name'];
		$obj->tag_type = @$requestData['tag_type'] ?? Tag::TYPE_NORMAL;
		$obj->is_hidden = (@$requestData['tag_type'] === Tag::TYPE_RED) ? true : false;
		$saved = $obj->save();
		
		if(!$saved)
		{
			return redirect()->back()->with('error', config('constants.server_error'));
		}
		else
		{
			return redirect()->route('adminconsole.features.tags.index')->with('success', 'Record Updated Successfully');
		}				
	}
}


