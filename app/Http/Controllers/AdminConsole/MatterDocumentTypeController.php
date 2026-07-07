<?php

namespace App\Http\Controllers\AdminConsole;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\ClientMatter;
use App\Models\Matter;
use App\Models\VisaDocumentType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;

class MatterDocumentTypeController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:admin');
    }

    public function index(Request $request)
    {
        $data = $this->buildListPayload($request);

        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'success' => true,
                'total' => $data['totalData'],
                'html' => view('AdminConsole.features.matterdocumenttype.partials.list-table', $data)->render(),
                'pagination' => view('AdminConsole.features.matterdocumenttype.partials.pagination', $data)->render(),
            ]);
        }

        return view('AdminConsole.features.matterdocumenttype.index', $data);
    }

    public function create(Request $request)
    {
        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'success' => true,
                'html' => view('AdminConsole.features.matterdocumenttype.partials.form-fields', [
                    'mode' => 'create',
                    'fetchedData' => null,
                    'fieldPrefix' => 'create_mdt',
                ])->render(),
            ]);
        }

        return redirect()->route('adminconsole.features.matterdocumenttype.index', ['action' => 'create']);
    }

    public function store(Request $request)
    {
        if (!$request->isMethod('post')) {
            return redirect()->route('adminconsole.features.matterdocumenttype.index');
        }

        try {
            $this->validate($request, [
                'title' => 'required|unique:visa_document_types,title',
            ]);

            $obj = new VisaDocumentType();
            $obj->title = $request->input('title');
            $obj->status = 1;

            if (!$obj->save()) {
                throw new \RuntimeException(config('constants.server_error'));
            }

            return $this->respondSaved($request, 'Matter document folder created successfully.', $obj->fresh());
        } catch (ValidationException $e) {
            return $this->respondValidationError($request, $e);
        } catch (\Exception $e) {
            return $this->respondError($request, $e, 'An error occurred while creating the folder.');
        }
    }

    public function edit(Request $request, $id)
    {
        $item = $this->findItemOrFail($id);
        if ($item instanceof \Symfony\Component\HttpFoundation\Response) {
            return $item;
        }

        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'success' => true,
                'item' => $this->serializeSummary($item),
                'html' => view('AdminConsole.features.matterdocumenttype.partials.form-fields', [
                    'mode' => 'edit',
                    'fetchedData' => $item,
                    'fieldPrefix' => 'edit_mdt',
                ])->render(),
            ]);
        }

        return redirect()->route('adminconsole.features.matterdocumenttype.index', [
            'action' => 'edit',
            'id' => $item->id,
        ]);
    }

    public function view(Request $request, $id)
    {
        $item = $this->findItemOrFail($id);
        if ($item instanceof \Symfony\Component\HttpFoundation\Response) {
            return $item;
        }

        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'success' => true,
                'item' => $this->serializeSummary($item),
                'html' => view('AdminConsole.features.matterdocumenttype.partials.view-body', [
                    'fetchedData' => $item,
                ])->render(),
            ]);
        }

        return redirect()->route('adminconsole.features.matterdocumenttype.index', [
            'action' => 'view',
            'id' => $item->id,
        ]);
    }

    public function update(Request $request, $id)
    {
        try {
            $item = $this->findItemOrFail($id);
            if ($item instanceof \Symfony\Component\HttpFoundation\Response) {
                return $item;
            }

            $this->validate($request, [
                'title' => 'required|unique:visa_document_types,title,' . $item->id,
            ]);

            $item->title = $request->input('title');

            if (!$item->save()) {
                throw new \RuntimeException(config('constants.server_error'));
            }

            return $this->respondSaved($request, 'Matter document folder updated successfully.', $item->fresh());
        } catch (ValidationException $e) {
            return $this->respondValidationError($request, $e);
        } catch (\Exception $e) {
            return $this->respondError($request, $e, 'An error occurred while updating the folder.', is_numeric($id) ? (int) $id : null);
        }
    }

    protected function buildListPayload(Request $request): array
    {
        $searchBy = trim((string) $request->query('search_by', ''));
        $query = VisaDocumentType::where('status', 1);

        if ($searchBy !== '') {
            $query->where('title', 'like', '%' . $searchBy . '%');
        }

        $totalData = $query->count();
        $lists = $query->sortable(['id' => 'desc'])->paginate(config('constants.limit'));

        if ($searchBy !== '') {
            $lists->appends(['search_by' => $searchBy]);
        }

        return [
            'lists' => $lists,
            'totalData' => $totalData,
            'searchBy' => $searchBy,
        ];
    }

    protected function findItemOrFail($id)
    {
        if (!isset($id) || $id === '') {
            if (request()->ajax() || request()->expectsJson()) {
                return response()->json(['success' => false, 'message' => config('constants.unauthorized')], 404);
            }

            return redirect()->route('adminconsole.features.matterdocumenttype.index')->with('error', config('constants.unauthorized'));
        }

        $decodedId = is_numeric($id) ? (int) $id : $this->decodeString($id);

        if (!VisaDocumentType::where('id', $decodedId)->exists()) {
            if (request()->ajax() || request()->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Matter document folder not found.'], 404);
            }

            return redirect()->route('adminconsole.features.matterdocumenttype.index')->with('error', 'Matter document folder not found.');
        }

        return VisaDocumentType::find($decodedId);
    }

    protected function resolveClientLabel(?int $clientId): string
    {
        if (!$clientId) {
            return 'Common for all clients';
        }

        $admin = Admin::select('first_name', 'last_name')->find($clientId);

        return $admin ? trim($admin->first_name . ' ' . $admin->last_name) : 'NA';
    }

    protected function resolveClientMatterLabel(?int $clientMatterId): string
    {
        if (!$clientMatterId) {
            return 'Common for all client matters';
        }

        $clientMatter = ClientMatter::select('sel_matter_id')->find($clientMatterId);
        if (!$clientMatter) {
            return 'Common for all client matters';
        }

        $matter = Matter::select('title', 'nick_name')->find($clientMatter->sel_matter_id);
        if (!$matter) {
            return 'NA';
        }

        return $matter->title . ' (' . $matter->nick_name . ')';
    }

    protected function serializeSummary(VisaDocumentType $item): array
    {
        return [
            'id' => $item->id,
            'title' => $item->title,
            'client_label' => $this->resolveClientLabel($item->client_id ? (int) $item->client_id : null),
            'client_matter_label' => $this->resolveClientMatterLabel($item->client_matter_id ? (int) $item->client_matter_id : null),
            'status' => (int) $item->status,
        ];
    }

    protected function respondValidationError(Request $request, ValidationException $e)
    {
        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first() ?: 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        }

        return redirect()->back()->withErrors($e->validator)->withInput();
    }

    protected function respondError(Request $request, \Exception $e, string $message, ?int $itemId = null)
    {
        Log::error('Matter document type save error: ' . $e->getMessage(), [
            'item_id' => $itemId,
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);

        $errorMsg = $message;
        if (config('app.debug')) {
            $errorMsg .= ' (' . $e->getMessage() . ')';
        }

        if ($request->ajax() || $request->expectsJson()) {
            return response()->json(['success' => false, 'message' => $errorMsg], 500);
        }

        return redirect()->back()->withInput()->with('error', $errorMsg);
    }

    protected function respondSaved(Request $request, string $message, VisaDocumentType $item)
    {
        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'item' => $this->serializeSummary($item),
                'row_html' => view('AdminConsole.features.matterdocumenttype.partials.row', [
                    'list' => $item,
                ])->render(),
            ]);
        }

        return redirect()->route('adminconsole.features.matterdocumenttype.index')->with('success', $message);
    }
}
