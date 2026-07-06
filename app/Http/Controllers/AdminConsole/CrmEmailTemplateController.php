<?php
namespace App\Http\Controllers\AdminConsole;

use App\Http\Controllers\Controller;
use App\Models\EmailTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CrmEmailTemplateController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:admin');
    }

    public function index(Request $request)
    {
        $query = EmailTemplate::crm();
        $totalData = $query->count();
        $lists = $query->sortable(['id' => 'desc'])->paginate(config('constants.limit'));

        return view('AdminConsole.features.crmemailtemplate.index', compact(['lists', 'totalData']));
    }

    public function create(Request $request)
    {
        return redirect()->route('adminconsole.features.crmemailtemplate.index');
    }

    public function store(Request $request)
    {
        $wantsJson = $request->expectsJson() || $request->ajax();

        if (!$request->isMethod('post')) {
            return redirect()->route('adminconsole.features.crmemailtemplate.index');
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'subject' => 'nullable|string|max:255',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            if ($wantsJson) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first(),
                    'errors' => $validator->errors(),
                ], 422);
            }

            return redirect()->route('adminconsole.features.crmemailtemplate.index')
                ->withErrors($validator)
                ->withInput();
        }

        $requestData = $request->all();

        $obj = new EmailTemplate;
        $obj->type = EmailTemplate::TYPE_CRM;
        $obj->name = $requestData['name'];
        $obj->subject = $requestData['subject'] ?? null;
        $obj->description = $requestData['description'] ?? null;

        if (!$obj->save()) {
            $message = config('constants.server_error');

            if ($wantsJson) {
                return response()->json(['success' => false, 'message' => $message], 500);
            }

            return redirect()->back()->with('error', $message);
        }

        $message = 'Crm Email Template Added Successfully';

        if ($wantsJson) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'template' => $this->templateToArray($obj->fresh()),
            ]);
        }

        return redirect()->route('adminconsole.features.crmemailtemplate.index')->with('success', $message);
    }

    public function edit($id)
    {
        if (empty($id)) {
            if (request()->expectsJson() || request()->ajax()) {
                return response()->json(['success' => false, 'message' => config('constants.unauthorized')], 403);
            }

            return redirect()->route('adminconsole.features.crmemailtemplate.index')->with('error', config('constants.unauthorized'));
        }

        $decodedId = is_numeric($id) ? (int) $id : $this->decodeString($id);

        if (!EmailTemplate::crm()->where('id', $decodedId)->exists()) {
            if (request()->expectsJson() || request()->ajax()) {
                return response()->json(['success' => false, 'message' => 'Crm Email Template Not Exist'], 404);
            }

            return redirect()->route('adminconsole.features.crmemailtemplate.index')->with('error', 'Crm Email Template Not Exist');
        }

        $fetchedData = EmailTemplate::crm()->find($decodedId);

        if (request()->expectsJson() || request()->ajax()) {
            return response()->json([
                'success' => true,
                'template' => $this->templateToArray($fetchedData),
            ]);
        }

        return redirect()->route('adminconsole.features.crmemailtemplate.index');
    }

    public function update(Request $request, $id)
    {
        $wantsJson = $request->expectsJson() || $request->ajax();
        $decodedId = is_numeric($id) ? (int) $id : $this->decodeString($id);

        $obj = EmailTemplate::crm()->find($decodedId);
        if (!$obj) {
            if ($wantsJson) {
                return response()->json(['success' => false, 'message' => 'Crm Email Template Not Found'], 404);
            }

            return redirect()->route('adminconsole.features.crmemailtemplate.index')->with('error', 'Crm Email Template Not Found');
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'subject' => 'nullable|string|max:255',
            'description' => 'nullable|string',
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
        $obj->name = $requestData['name'];
        $obj->subject = $requestData['subject'] ?? null;
        $obj->description = $requestData['description'] ?? null;

        if (!$obj->save()) {
            $message = config('constants.server_error');

            if ($wantsJson) {
                return response()->json(['success' => false, 'message' => $message], 500);
            }

            return redirect()->back()->with('error', $message);
        }

        $message = 'Crm Email Template Updated Successfully';

        if ($wantsJson) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'template' => $this->templateToArray($obj->fresh()),
            ]);
        }

        return redirect()->route('adminconsole.features.crmemailtemplate.index')->with('success', $message);
    }

    private function templateToArray(EmailTemplate $template): array
    {
        $empty = config('constants.empty', '—');
        $name = $template->name ?? '';
        $subject = $template->subject ?? '';

        return [
            'id' => (int) $template->id,
            'encoded_id' => base64_encode(convert_uuencode($template->id)),
            'name' => $name,
            'subject' => $subject,
            'description' => $template->description,
            'display_name' => $name === '' ? $empty : Str::limit($name, 50, '...'),
            'display_subject' => $subject === '' ? $empty : Str::limit($subject, 50, '...'),
        ];
    }
}
