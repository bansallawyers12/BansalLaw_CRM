<?php
namespace App\Http\Controllers\AdminConsole;

use App\Http\Controllers\Controller;
use App\Models\EmailLabel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;

class EmailLabelController extends Controller
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
     * Display a listing of email labels.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = EmailLabel::with(['user']);
        
        // Filter by type if provided
        if ($request->has('type') && $request->type != '') {
            $query->where('type', $request->type);
        }
        
        // Filter active/inactive
        if ($request->has('is_active')) {
            $query->where('is_active', $request->is_active);
        }
        
        $totalData = $query->count();
        $lists = $query->orderBy('type', 'desc') // System labels first
                      ->orderBy('name', 'asc')
                      ->paginate(config('constants.limit', 15));
        
        return view('AdminConsole.features.emaillabels.index', compact(['lists', 'totalData']));
    }

    /**
     * Show the form for creating a new email label.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
        return redirect()->route('adminconsole.features.emaillabels.index');
    }

    /**
     * Store a newly created email label in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        if (! $request->isMethod('post')) {
            return redirect()->route('adminconsole.features.emaillabels.index');
        }

        $userId = Auth::id();
        $wantsJson = $request->expectsJson() || $request->ajax();

        $validator = Validator::make($request->all(), [
            'name' => [
                'required',
                'string',
                'max:255',
                function ($attribute, $value, $fail) use ($userId) {
                    $exists = EmailLabel::where('user_id', $userId)
                        ->where('name', $value)
                        ->where('is_active', true)
                        ->exists();

                    if ($exists) {
                        $fail('A label with this name already exists.');
                    }
                }
            ],
            'color' => [
                'required',
                'string',
                'regex:/^#[0-9A-Fa-f]{6}$/'
            ],
            'icon' => 'nullable|string|max:50',
            'description' => 'nullable|string|max:500',
            'type' => 'nullable|in:system,custom',
        ]);

        if ($validator->fails()) {
            if ($wantsJson) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first(),
                    'errors' => $validator->errors(),
                ], 422);
            }

            return redirect()->route('adminconsole.features.emaillabels.index')
                ->withErrors($validator)
                ->withInput();
        }

        $requestData = $request->all();

        $obj = new EmailLabel;
        $obj->user_id = $userId;
        $obj->name = $requestData['name'];
        $obj->color = $requestData['color'];
        $obj->icon = $requestData['icon'] ?? 'fas fa-tag';
        $obj->type = $requestData['type'] ?? 'custom';
        $obj->description = $requestData['description'] ?? null;
        $obj->is_active = true;

        $saved = $obj->save();

        if (! $saved) {
            $message = config('constants.server_error');

            if ($wantsJson) {
                return response()->json(['success' => false, 'message' => $message], 500);
            }

            return redirect()->route('adminconsole.features.emaillabels.index')->with('error', $message);
        }

        $message = 'Email Label Created Successfully';

        if ($wantsJson) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'label' => $this->labelToArray($obj->fresh(['user'])),
            ]);
        }

        return redirect()->route('adminconsole.features.emaillabels.index')->with('success', $message);
    }

    /**
     * Show the form for editing the specified email label.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        if (empty($id)) {
            return redirect()->route('adminconsole.features.emaillabels.index')->with('error', config('constants.unauthorized'));
        }

        $decodedId = is_numeric($id) ? (int) $id : $this->decodeString($id);
        $label = EmailLabel::with('user')->find($decodedId);

        if (! $label) {
            if (request()->expectsJson() || request()->ajax()) {
                return response()->json(['success' => false, 'message' => 'Record Not Exist'], 404);
            }

            return redirect()->route('adminconsole.features.emaillabels.index')->with('error', 'Record Not Exist');
        }

        if (request()->expectsJson() || request()->ajax()) {
            return response()->json([
                'success' => true,
                'label' => $this->labelToArray($label),
            ]);
        }

        return redirect()->route('adminconsole.features.emaillabels.index');
    }

    /**
     * Update the specified email label in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $requestData = $request->all();
        $userId = Auth::id();
        $wantsJson = $request->expectsJson() || $request->ajax();
        $labelId = (int) $id;

        $validator = Validator::make($request->all(), [
            'name' => [
                'required',
                'string',
                'max:255',
                function ($attribute, $value, $fail) use ($userId, $labelId) {
                    $exists = EmailLabel::where('user_id', $userId)
                        ->where('name', $value)
                        ->where('id', '!=', $labelId)
                        ->where('is_active', true)
                        ->exists();

                    if ($exists) {
                        $fail('A label with this name already exists.');
                    }
                }
            ],
            'color' => [
                'required',
                'string',
                'regex:/^#[0-9A-Fa-f]{6}$/'
            ],
            'icon' => 'nullable|string|max:50',
            'description' => 'nullable|string|max:500',
            'is_active' => 'nullable|in:0,1',
        ]);

        if ($validator->fails()) {
            if ($wantsJson) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first(),
                    'errors' => $validator->errors(),
                ], 422);
            }

            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $obj = EmailLabel::find($labelId);
        if (! $obj) {
            if ($wantsJson) {
                return response()->json(['success' => false, 'message' => 'Record Not Found'], 404);
            }

            return redirect()->route('adminconsole.features.emaillabels.index')->with('error', 'Record Not Found');
        }

        if ($obj->type === 'system') {
            if ($wantsJson) {
                return response()->json(['success' => false, 'message' => 'System labels cannot be edited'], 403);
            }

            return redirect()->back()->with('error', 'System labels cannot be edited');
        }

        $obj->name = $requestData['name'];
        $obj->color = $requestData['color'];
        $obj->icon = $requestData['icon'] ?? 'fas fa-tag';
        $obj->description = $requestData['description'] ?? null;
        if (array_key_exists('is_active', $requestData)) {
            $obj->is_active = (int) $requestData['is_active'] === 1;
        }

        $saved = $obj->save();

        if (! $saved) {
            $message = config('constants.server_error');

            if ($wantsJson) {
                return response()->json(['success' => false, 'message' => $message], 500);
            }

            return redirect()->back()->with('error', $message);
        }

        $message = 'Email Label Updated Successfully';

        if ($wantsJson) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'label' => $this->labelToArray($obj->fresh(['user'])),
            ]);
        }

        return redirect()->route('adminconsole.features.emaillabels.index')->with('success', $message);
    }

    /**
     * Normalize an email label for JSON responses and client-side row rendering.
     */
    private function labelToArray(EmailLabel $label): array
    {
        $label->loadMissing('user');

        return [
            'id' => (int) $label->id,
            'name' => (string) $label->name,
            'color' => (string) ($label->color ?: '#3A6FA8'),
            'icon' => (string) ($label->icon ?: 'fas fa-tag'),
            'type' => (string) $label->type,
            'description' => $label->description,
            'is_active' => (bool) $label->is_active,
            'created_by' => $label->user
                ? trim($label->user->first_name . ' ' . $label->user->last_name)
                : 'System',
            'updated_at' => $label->updated_at
                ? $label->updated_at->format('Y-m-d H:i')
                : '-',
        ];
    }

    /**
     * Decode string ID (following pattern from other admin feature controllers)
     */
    public function decodeString($string = null)
    {
        return convert_uudecode(base64_decode($string));
    }
}

