<?php
namespace App\Http\Controllers\AdminConsole;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;

use App\Models\Admin;
use App\Models\VisaDocumentType;

use Auth;

class VisaDocumentTypeController extends Controller
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
		//check authorization end
        $query 		= VisaDocumentType::where('status', 1);
        $totalData 	= $query->count();	//for all data
        $lists		= $query->sortable(['id' => 'desc'])->paginate(config('constants.limit'));
        return view('AdminConsole.features.visadocumenttype.index',compact(['lists', 'totalData']));
    }

	public function create(Request $request)
	{
		return view('AdminConsole.features.visadocumenttype.create');
	}

	public function store(Request $request)
	{
		//check authorization end
		if ($request->isMethod('post'))
		{
			$this->validate($request,
                [ 'title' => 'required|unique:visa_document_types,title']
            );

            $requestData  = 	$request->all(); //dd($requestData);
            $obj		 = 	new VisaDocumentType;
			$obj->title	=	@$requestData['title'];
			$obj->status	= 1;
			$saved	=	$obj->save();
            if(!$saved)
			{
				return redirect()->back()->with('error', config('constants.server_error'));
			}
			else
			{
				return redirect()->route('adminconsole.features.visadocumenttype.index')->with('success', 'Visa Document Type Created Successfully');
			}
		}
        return view('AdminConsole.features.visadocumenttype.create');
	}

	/**
	 * Show the form for editing the specified visa document type.
	 */
	public function edit($id)
	{
        //check authorization end
        
		if(isset($id) && !empty($id)) {
			$id = $this->decodeString($id);
			if(VisaDocumentType::where('id', '=', $id)->exists()) {
				$fetchedData = VisaDocumentType::find($id);
				return view('AdminConsole.features.visadocumenttype.edit', compact(['fetchedData']));
			} else {
				return redirect()->route('adminconsole.features.visadocumenttype.index')->with('error', 'Visa Document Type Not Exist');
			}
		} else {
			return redirect()->route('adminconsole.features.visadocumenttype.index')->with('error', config('constants.unauthorized'));
		}
    }

	/**
	 * Update the specified visa document type in storage.
	 */
	public function update(Request $request, $id)
	{
        //check authorization end
        
        $requestData = $request->all();
        $this->validate($request,
            ['title' => 'required|unique:visa_document_types,title,'.$id]
        );

        $obj = VisaDocumentType::find($id);
        if (!$obj) {
			return redirect()->route('adminconsole.features.visadocumenttype.index')->with('error', 'Visa Document Type Not Found');
		}
        
        $obj->title = @$requestData['title'];
        $saved = $obj->save();
        
        if(!$saved) {
			return redirect()->back()->with('error', config('constants.server_error'));
		} else {
			return redirect()->route('adminconsole.features.visadocumenttype.index')->with('success', 'Visa Document Type Updated Successfully');
		}
    }



}


