<?php
namespace App\Http\Controllers\AdminConsole;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Country;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class BranchesController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:admin');
    }

    public function index(Request $request)
    {
        $check = $this->checkAuthorizationAction('branch_management', $request->route()->getActionMethod(), Auth::user()?->role);
        if ($check) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => config('constants.unauthorized')], 403);
            }
            return Redirect::to('/dashboard')->with('error', config('constants.unauthorized'));
        }

        $query = Branch::query();
        $totalData = $query->count();
        $lists = $query->sortable(['id' => 'desc'])->paginate(config('constants.limit'));
        $countries = Country::orderBy('name')->get(['sortname', 'name']);

        return view('AdminConsole.system.offices.index', compact('lists', 'totalData', 'countries'));
    }

    public function create(Request $request)
    {
        return redirect()->route('adminconsole.system.offices.index');
    }

    public function store(Request $request)
    {
        $wantsJson = $request->expectsJson() || $request->ajax();

        if (!$request->isMethod('post')) {
            return redirect()->route('adminconsole.system.offices.index');
        }

        $validator = Validator::make($request->all(), [
            'office_name' => 'required|string|max:255',
            'country' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'zip' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:255',
            'mobile' => 'nullable|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'choose_admin' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            if ($wantsJson) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first(),
                    'errors' => $validator->errors(),
                ], 422);
            }

            return redirect()->route('adminconsole.system.offices.index')
                ->withErrors($validator)
                ->withInput();
        }

        $requestData = $request->all();

        $obj = new Branch;
        $obj->office_name = $requestData['office_name'];
        $obj->address = $requestData['address'] ?? null;
        $obj->city = $requestData['city'] ?? null;
        $obj->state = $requestData['state'] ?? null;
        $obj->zip = $requestData['zip'] ?? null;
        $obj->country = $requestData['country'];
        $obj->email = $requestData['email'];
        $obj->phone = $requestData['phone'] ?? null;
        $obj->mobile = $requestData['mobile'] ?? null;
        $obj->contact_person = $requestData['contact_person'] ?? null;
        $obj->choose_admin = $requestData['choose_admin'] ?? null;

        if (!$obj->save()) {
            $message = config('constants.server_error');

            if ($wantsJson) {
                return response()->json(['success' => false, 'message' => $message], 500);
            }

            return redirect()->back()->with('error', $message);
        }

        $message = 'Branch Added Successfully';

        if ($wantsJson) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'office' => $this->officeToArray($obj->fresh()),
            ]);
        }

        return redirect()->route('adminconsole.system.offices.index')->with('success', $message);
    }

    public function edit($id)
    {
        if (empty($id)) {
            if (request()->expectsJson() || request()->ajax()) {
                return response()->json(['success' => false, 'message' => config('constants.unauthorized')], 403);
            }

            return redirect()->route('adminconsole.system.offices.index')->with('error', config('constants.unauthorized'));
        }

        $decodedId = is_numeric($id) ? (int) $id : $this->decodeString($id);

        if (!Branch::where('id', $decodedId)->exists()) {
            if (request()->expectsJson() || request()->ajax()) {
                return response()->json(['success' => false, 'message' => 'Branch Not Exist'], 404);
            }

            return redirect()->route('adminconsole.system.offices.index')->with('error', 'Branch Not Exist');
        }

        $fetchedData = Branch::find($decodedId);

        if (request()->expectsJson() || request()->ajax()) {
            return response()->json([
                'success' => true,
                'office' => $this->officeToArray($fetchedData),
            ]);
        }

        return redirect()->route('adminconsole.system.offices.index');
    }

    public function update(Request $request, $id)
    {
        $wantsJson = $request->expectsJson() || $request->ajax();
        $decodedId = is_numeric($id) ? (int) $id : $this->decodeString($id);

        $obj = Branch::find($decodedId);
        if (!$obj) {
            if ($wantsJson) {
                return response()->json(['success' => false, 'message' => 'Branch Not Found'], 404);
            }

            return redirect()->route('adminconsole.system.offices.index')->with('error', 'Branch Not Found');
        }

        $validator = Validator::make($request->all(), [
            'office_name' => 'required|string|max:255',
            'country' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'zip' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:255',
            'mobile' => 'nullable|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'choose_admin' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            if ($wantsJson) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first(),
                    'errors' => $validator->errors(),
                ], 422);
            }

            return redirect()->back()->withErrors($validator)->withInput();
        }

        $requestData = $request->all();
        $obj->office_name = $requestData['office_name'];
        $obj->address = $requestData['address'] ?? null;
        $obj->city = $requestData['city'] ?? null;
        $obj->state = $requestData['state'] ?? null;
        $obj->zip = $requestData['zip'] ?? null;
        $obj->country = $requestData['country'];
        $obj->email = $requestData['email'];
        $obj->phone = $requestData['phone'] ?? null;
        $obj->mobile = $requestData['mobile'] ?? null;
        $obj->contact_person = $requestData['contact_person'] ?? null;
        $obj->choose_admin = $requestData['choose_admin'] ?? null;

        if (!$obj->save()) {
            $message = config('constants.server_error');

            if ($wantsJson) {
                return response()->json(['success' => false, 'message' => $message], 500);
            }

            return redirect()->back()->with('error', $message);
        }

        $message = 'Branch Updated Successfully';

        if ($wantsJson) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'office' => $this->officeToArray($obj->fresh()),
            ]);
        }

        return redirect()->route('adminconsole.system.offices.index')->with('success', $message);
    }

    public function view(Request $request, $id = null)
    {
        if (isset($id) && !empty($id)) {
            if (Branch::where('id', '=', $id)->exists()) {
                $fetchedData = Branch::find($id);
                return view('AdminConsole.system.offices.view', compact(['fetchedData']));
            }

            return redirect()->route('adminconsole.system.offices.index')->with('error', 'Branch Not Exist');
        }

        return redirect()->route('adminconsole.system.offices.index')->with('error', config('constants.unauthorized'));
    }

    public function viewclient(Request $request, $id = null)
    {
        if (isset($id) && !empty($id)) {
            if (Branch::where('id', '=', $id)->exists()) {
                $fetchedData = Branch::find($id);
                return view('AdminConsole.system.offices.viewclient', compact(['fetchedData']));
            }

            return redirect()->route('adminconsole.system.offices.index')->with('error', 'Branch Not Exist');
        }

        return redirect()->route('adminconsole.system.offices.index')->with('error', config('constants.unauthorized'));
    }

    private function officeToArray(Branch $branch): array
    {
        $empty = config('constants.empty', '—');
        $countryDisplay = $branch->country ?: $empty;
        if ($branch->country) {
            $countryRow = Country::where('sortname', $branch->country)->first();
            if ($countryRow) {
                $countryDisplay = $countryRow->name;
            }
        }

        return [
            'id' => (int) $branch->id,
            'encoded_id' => base64_encode(convert_uuencode($branch->id)),
            'office_name' => $branch->office_name,
            'address' => $branch->address,
            'city' => $branch->city,
            'state' => $branch->state,
            'zip' => $branch->zip,
            'country' => $branch->country,
            'email' => $branch->email,
            'phone' => $branch->phone,
            'mobile' => $branch->mobile,
            'contact_person' => $branch->contact_person,
            'choose_admin' => $branch->choose_admin,
            'view_url' => route('adminconsole.system.offices.view', $branch->id),
            'display_name' => $branch->office_name ? Str::limit($branch->office_name, 50, '...') : $empty,
            'display_city' => $branch->city ? Str::limit($branch->city, 50, '...') : $empty,
            'display_country' => Str::limit($countryDisplay, 50, '...'),
            'display_mobile' => $branch->mobile ? Str::limit($branch->mobile, 50, '...') : $empty,
            'display_phone' => $branch->phone ? Str::limit($branch->phone, 50, '...') : $empty,
            'display_contact_person' => $branch->contact_person ? Str::limit($branch->contact_person, 50, '...') : $empty,
        ];
    }
}
