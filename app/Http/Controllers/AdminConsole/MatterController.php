<?php

namespace App\Http\Controllers\AdminConsole;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Schema;
use App\Models\Matter;
use Illuminate\Validation\Rule;

class MatterController extends Controller
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
     * Display a listing of the matters.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = Matter::query();
        $totalData = $query->count(); // for all data
        //dd($totalData);
        if ($request->has('title')) {
            $title 		= 	$request->input('title');
            if(trim($title) != '') {
                $query->where('title', 'LIKE', '%' . $title . '%');
            }
        }
        $lists	= $query->sortable(['id' => 'desc'])->paginate(20);
        return view('AdminConsole.features.matter.index', compact(['lists', 'totalData']));
    }

    public function create(Request $request)
    {
        return view('AdminConsole.features.matter.create');
    }

    public function store(Request $request)
    {
        if ($request->isMethod('post'))
        {
            // Validation rules with unique check for nick_name and optional fields
            $streamKeys = array_keys(config('matter_streams.streams', []));
            $this->validate($request, [
                'title' => 'required|max:255',
                'nick_name' => 'required|max:255|unique:matters,nick_name',
                'stream' => ['nullable', 'string', 'max:64', Rule::in($streamKeys)],
            ]);

            $requestData = $request->all();
            $obj = new Matter;
            $obj->title = $requestData['title'];
            $obj->nick_name = $requestData['nick_name'];
            if (Schema::hasColumn('matters', 'stream')) {
                $obj->stream = isset($requestData['stream']) && $requestData['stream'] !== ''
                    ? $requestData['stream']
                    : null;
            }
            $obj->workflow_id = $requestData['workflow_id'] ?? null;
            $obj->status = $requestData['status'] ?? 1;
            $obj->is_for_company = $requestData['is_for_company'] ?? 0;

            // Block fee defaults (stored on matter type, copied to cost assignment when editing)
            if (Schema::hasColumn('matters', 'Block_1_Description')) {
                $obj->Block_1_Description = $requestData['Block_1_Description'] ?? null;
                $obj->Block_2_Description = $requestData['Block_2_Description'] ?? null;
                $obj->Block_3_Description = $requestData['Block_3_Description'] ?? null;
            }
            if (Schema::hasColumn('matters', 'Block_1_Ex_Tax')) {
                $obj->Block_1_Ex_Tax = $requestData['Block_1_Ex_Tax'] ?? null;
                $obj->Block_2_Ex_Tax = $requestData['Block_2_Ex_Tax'] ?? null;
                $obj->Block_3_Ex_Tax = $requestData['Block_3_Ex_Tax'] ?? null;
            }
            if (Schema::hasColumn('matters', 'additional_fee_1')) {
                $obj->additional_fee_1 = $requestData['additional_fee_1'] ?? null;
            }

            $saved = $obj->save();
            if (!$saved)
            {
                return redirect()->back()->with('error', config('constants.server_error'));
            }
            else
            {
                return redirect()->route('adminconsole.features.matter.index')->with('success', 'Matter Added Successfully');
            }
        }
        return view('AdminConsole.features.matter.create');
    }

    /**
     * Show the form for editing the specified matter.
     */
    public function edit($id)
    {
        if (isset($id) && !empty($id))
        {
            $id = $this->decodeString($id);
            if (Matter::where('id', '=', $id)->exists())
            {
                $fetchedData = Matter::find($id);
                return view('AdminConsole.features.matter.edit', compact(['fetchedData']));
            }
            else
            {
                return redirect()->route('adminconsole.features.matter.index')->with('error', 'Matter Not Exist');
            }
        }
        else
        {
            return redirect()->route('adminconsole.features.matter.index')->with('error', config('constants.unauthorized'));
        }
    }

    /**
     * Update the specified matter in storage.
     */
    public function update(Request $request, $id)
    {
        $requestData = $request->all();
        $streamKeys = array_keys(config('matter_streams.streams', []));
        $this->validate($request, [
            'title' => 'required|max:255',
            'nick_name' => 'required|max:255|unique:matters,nick_name,'.$id,
            'stream' => ['nullable', 'string', 'max:64', Rule::in($streamKeys)],
        ]);

        $obj = Matter::find($id);
        if (!$obj) {
            return redirect()->route('adminconsole.features.matter.index')->with('error', 'Matter Not Found');
        }

        $obj->title = $requestData['title'];
        $obj->nick_name = $requestData['nick_name'];
        if (Schema::hasColumn('matters', 'stream')) {
            $obj->stream = isset($requestData['stream']) && $requestData['stream'] !== ''
                ? $requestData['stream']
                : null;
        }
        $obj->workflow_id = $requestData['workflow_id'] ?: null;
        $obj->is_for_company = $requestData['is_for_company'] ?? $obj->is_for_company ?? 0;

        if (Schema::hasColumn('matters', 'Block_1_Description')) {
            $obj->Block_1_Description = $requestData['Block_1_Description'] ?? null;
            $obj->Block_2_Description = $requestData['Block_2_Description'] ?? null;
            $obj->Block_3_Description = $requestData['Block_3_Description'] ?? null;
        }
        if (Schema::hasColumn('matters', 'Block_1_Ex_Tax')) {
            $obj->Block_1_Ex_Tax = $requestData['Block_1_Ex_Tax'] ?? null;
            $obj->Block_2_Ex_Tax = $requestData['Block_2_Ex_Tax'] ?? null;
            $obj->Block_3_Ex_Tax = $requestData['Block_3_Ex_Tax'] ?? null;
        }
        if (Schema::hasColumn('matters', 'additional_fee_1')) {
            $obj->additional_fee_1 = $requestData['additional_fee_1'] ?? null;
        }

        $saved = $obj->save();
        if (!$saved)
        {
            return redirect()->back()->with('error', config('constants.server_error'));
        }
        else
        {
            return redirect()->route('adminconsole.features.matter.index')->with('success', 'Matter Updated Successfully');
        }
    }
}


