<?php

namespace App\Http\Controllers\AdminConsole;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use App\Models\Matter;

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
            $this->validate($request, [
                'title' => 'required|max:255',
                'nick_name' => 'required|max:255|unique:matters,nick_name',
            ]);

            $requestData = $request->all();
            $obj = new Matter;
            $obj->title = $requestData['title'];
            $obj->nick_name = $requestData['nick_name'];
            $obj->workflow_id = $requestData['workflow_id'] ?? null;
            $obj->surcharge = $requestData['surcharge'];
            $obj->status = $requestData['status'] ?? 1; // Set default status to 1 (active)
            $obj->is_for_company = $requestData['is_for_company'] ?? 0; // Default to 0 (for personal clients)

            $obj->Dept_Base_Application_Charge = $requestData['Dept_Base_Application_Charge'] ?? '0.00';
            $obj->Dept_Non_Internet_Application_Charge = $requestData['Dept_Non_Internet_Application_Charge'] ?? '0.00';
            $obj->Dept_Additional_Applicant_Charge_18_Plus = $requestData['Dept_Additional_Applicant_Charge_18_Plus'] ?? '0.00';
            $obj->Dept_Additional_Applicant_Charge_Under_18 = $requestData['Dept_Additional_Applicant_Charge_Under_18'] ?? '0.00';
            $obj->Dept_Subsequent_Temp_Application_Charge = $requestData['Dept_Subsequent_Temp_Application_Charge'] ?? '0.00';
            $obj->Dept_Second_VAC_Instalment_Charge_18_Plus = $requestData['Dept_Second_VAC_Instalment_Charge_18_Plus'] ?? '0.00';
            $obj->Dept_Second_VAC_Instalment_Under_18 = $requestData['Dept_Second_VAC_Instalment_Under_18'] ?? '0.00';
            $obj->Dept_Nomination_Application_Charge = $requestData['Dept_Nomination_Application_Charge'] ?? '0.00';
            $obj->Dept_Sponsorship_Application_Charge = $requestData['Dept_Sponsorship_Application_Charge'] ?? '0.00';

            $obj->Block_1_Description = $requestData['Block_1_Description'] ?? null;
            $obj->Block_1_Ex_Tax = $requestData['Block_1_Ex_Tax'] ?? '0.00';
            $obj->Block_2_Description = $requestData['Block_2_Description'] ?? null;
            $obj->Block_2_Ex_Tax = $requestData['Block_2_Ex_Tax'] ?? '0.00';
            $obj->Block_3_Description = $requestData['Block_3_Description'] ?? null;
            $obj->Block_3_Ex_Tax = $requestData['Block_3_Ex_Tax'] ?? '0.00';

            $obj->additional_fee_1 = $requestData['additional_fee_1'] ?? '0.00';
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
        $this->validate($request, [
            'title' => 'required|max:255',
            'nick_name' => 'required|max:255|unique:matters,nick_name,'.$id,
        ]);

        $obj = Matter::find($id);
        if (!$obj) {
            return redirect()->route('adminconsole.features.matter.index')->with('error', 'Matter Not Found');
        }

        $obj->title = $requestData['title'];
        $obj->nick_name = $requestData['nick_name'];
        $obj->workflow_id = $requestData['workflow_id'] ?: null;
        $obj->is_for_company = $requestData['is_for_company'] ?? $obj->is_for_company ?? 0;

        $obj->surcharge = $requestData['surcharge']?? $obj->surcharge;

        $obj->Dept_Base_Application_Charge = $requestData['Dept_Base_Application_Charge'] ?? $obj->Dept_Base_Application_Charge;
        $obj->Dept_Non_Internet_Application_Charge = $requestData['Dept_Non_Internet_Application_Charge'] ?? $obj->Dept_Non_Internet_Application_Charge;
        $obj->Dept_Additional_Applicant_Charge_18_Plus = $requestData['Dept_Additional_Applicant_Charge_18_Plus'] ?? $obj->Dept_Additional_Applicant_Charge_18_Plus;
        $obj->Dept_Additional_Applicant_Charge_Under_18 = $requestData['Dept_Additional_Applicant_Charge_Under_18'] ?? $obj->Dept_Additional_Applicant_Charge_Under_18;
        $obj->Dept_Subsequent_Temp_Application_Charge = $requestData['Dept_Subsequent_Temp_Application_Charge'] ?? $obj->Dept_Subsequent_Temp_Application_Charge;
        $obj->Dept_Second_VAC_Instalment_Charge_18_Plus = $requestData['Dept_Second_VAC_Instalment_Charge_18_Plus'] ?? $obj->Dept_Second_VAC_Instalment_Charge_18_Plus;
        $obj->Dept_Second_VAC_Instalment_Under_18 = $requestData['Dept_Second_VAC_Instalment_Under_18'] ?? $obj->Dept_Second_VAC_Instalment_Under_18;
        $obj->Dept_Nomination_Application_Charge = $requestData['Dept_Nomination_Application_Charge'] ?? $obj->Dept_Nomination_Application_Charge;
        $obj->Dept_Sponsorship_Application_Charge = $requestData['Dept_Sponsorship_Application_Charge'] ?? $obj->Dept_Sponsorship_Application_Charge;

        $obj->Block_1_Description = $requestData['Block_1_Description'] ?? $obj->Block_1_Description;
        $obj->Block_1_Ex_Tax = $requestData['Block_1_Ex_Tax'] ?? $obj->Block_1_Ex_Tax;
        $obj->Block_2_Description = $requestData['Block_2_Description'] ?? $obj->Block_2_Description;
        $obj->Block_2_Ex_Tax = $requestData['Block_2_Ex_Tax'] ?? $obj->Block_2_Ex_Tax;
        $obj->Block_3_Description = $requestData['Block_3_Description'] ?? $obj->Block_3_Description;
        $obj->Block_3_Ex_Tax = $requestData['Block_3_Ex_Tax'] ?? $obj->Block_3_Ex_Tax;

        $obj->additional_fee_1 = $requestData['additional_fee_1'] ?? $obj->additional_fee_1;
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


