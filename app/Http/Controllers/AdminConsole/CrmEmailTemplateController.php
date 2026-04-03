<?php
namespace App\Http\Controllers\AdminConsole;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;

use App\Models\Admin;
use App\Models\EmailTemplate;

use Auth;

class CrmEmailTemplateController extends Controller
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
	
		$query 		= EmailTemplate::crm(); 
		 
		$totalData 	= $query->count();	//for all data
		
		$lists		= $query->sortable(['id' => 'desc'])->paginate(config('constants.limit'));
		
		return view('AdminConsole.features.crmemailtemplate.index',compact(['lists', 'totalData'])); 	
		
	}
	
	public function create(Request $request)
	{
		//check authorization end
		//return view('AdminConsole\.system\.users\.create',compact(['usertype']));	
		
		return view('AdminConsole.features.crmemailtemplate.create');	
	}
	
	public function store(Request $request)
	{		
		//check authorization end
		if ($request->isMethod('post')) 
		{
			
			$requestData 		= 	$request->all();
			
			$obj				= 	new EmailTemplate; 
			$obj->type	=	EmailTemplate::TYPE_CRM;
			$obj->name	=	@$requestData['name'];
			$obj->subject	=	@$requestData['subject'];
			$obj->description	=	@$requestData['description'];
			
			$saved				=	$obj->save();  
			
			if(!$saved)
			{
				return redirect()->back()->with('error', config('constants.server_error'));
			}
			else
			{
				return redirect()->route('adminconsole.features.crmemailtemplate.index')->with('success', 'Crm Email Template Added Successfully');
			}				
		}	

		return view('AdminConsole.features.crmemailtemplate.create');	
	}
	
	/**
	 * Show the form for editing the specified CRM email template.
	 */
	public function edit($id)
	{
		//check authorization end
		
		if(isset($id) && !empty($id))
		{
			$id = $this->decodeString($id);	
			if(EmailTemplate::crm()->where('id', '=', $id)->exists()) 
			{
				$fetchedData = EmailTemplate::crm()->find($id);
				return view('AdminConsole.features.crmemailtemplate.edit', compact(['fetchedData']));
			}
			else 
			{
				return redirect()->route('adminconsole.features.crmemailtemplate.index')->with('error', 'Crm Email Template Not Exist');
			}	
		} 
		else
		{
			return redirect()->route('adminconsole.features.crmemailtemplate.index')->with('error', config('constants.unauthorized'));
		}		
	}

	/**
	 * Update the specified CRM email template in storage.
	 */
	public function update(Request $request, $id)
	{
		//check authorization end
		
		$requestData = $request->all();
						  					  
		$obj = EmailTemplate::crm()->find($id);
		if (!$obj) {
			return redirect()->route('adminconsole.features.crmemailtemplate.index')->with('error', 'Crm Email Template Not Found');
		}
		
		$obj->name = @$requestData['name'];
		$obj->subject = @$requestData['subject'];
		$obj->description = @$requestData['description'];
		
		$saved = $obj->save();
		
		if(!$saved)
		{
			return redirect()->back()->with('error', config('constants.server_error'));
		}
		else
		{
			return redirect()->route('adminconsole.features.crmemailtemplate.index')->with('success', 'Crm Email Template Updated Successfully');
		}				
	}
}


