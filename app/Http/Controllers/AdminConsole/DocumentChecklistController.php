<?php

namespace App\Http\Controllers\AdminConsole;

use App\Http\Controllers\Controller;
use App\Models\DocumentChecklist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DocumentChecklistController extends Controller
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
                'html' => view('AdminConsole.features.documentchecklist.partials.list-table', $data)->render(),
                'pagination' => view('AdminConsole.features.documentchecklist.partials.pagination', $data)->render(),
            ]);
        }

        return view('AdminConsole.features.documentchecklist.index', $data);
    }

    public function create(Request $request)
    {
        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'success' => true,
                'html' => view('AdminConsole.features.documentchecklist.partials.form-fields', [
                    'mode' => 'create',
                    'fetchedData' => null,
                    'fieldPrefix' => 'create_dcl',
                ])->render(),
            ]);
        }

        return redirect()->route('adminconsole.features.documentchecklist.index', ['action' => 'create']);
    }

    public function store(Request $request)
    {
        if (!$request->isMethod('post')) {
            return redirect()->route('adminconsole.features.documentchecklist.index');
        }

        try {
            $this->validate($request, [
                'name' => [
                    'required',
                    'max:255',
                    Rule::unique('document_checklists')->where(function ($query) {
                        return $query->where('doc_type', request('doc_type'));
                    }),
                ],
                'doc_type' => 'required',
            ]);

            $obj = new DocumentChecklist();
            $obj->name = $request->input('name');
            $obj->doc_type = $request->input('doc_type');
            $obj->status = 1;

            if (!$obj->save()) {
                throw new \RuntimeException(config('constants.server_error'));
            }

            return $this->respondSaved($request, 'Checklist added successfully.', $obj->fresh());
        } catch (ValidationException $e) {
            return $this->respondValidationError($request, $e);
        } catch (\Exception $e) {
            return $this->respondError($request, $e, 'An error occurred while creating the checklist.');
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
                'html' => view('AdminConsole.features.documentchecklist.partials.form-fields', [
                    'mode' => 'edit',
                    'fetchedData' => $item,
                    'fieldPrefix' => 'edit_dcl',
                ])->render(),
            ]);
        }

        return redirect()->route('adminconsole.features.documentchecklist.index', [
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
                'html' => view('AdminConsole.features.documentchecklist.partials.view-body', [
                    'fetchedData' => $item,
                ])->render(),
            ]);
        }

        return redirect()->route('adminconsole.features.documentchecklist.index', [
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
                'doc_type' => 'required',
                'name' => [
                    'required',
                    'max:255',
                    Rule::unique('document_checklists')->where(function ($query) use ($request) {
                        return $query->where('doc_type', $request->doc_type);
                    })->ignore($item->id),
                ],
            ]);

            $item->name = $request->input('name');
            $item->doc_type = $request->input('doc_type');

            if (!$item->save()) {
                throw new \RuntimeException(config('constants.server_error'));
            }

            return $this->respondSaved($request, 'Checklist updated successfully.', $item->fresh());
        } catch (ValidationException $e) {
            return $this->respondValidationError($request, $e);
        } catch (\Exception $e) {
            return $this->respondError($request, $e, 'An error occurred while updating the checklist.', is_numeric($id) ? (int) $id : null);
        }
    }

    protected function buildListPayload(Request $request): array
    {
        $searchBy = trim((string) $request->query('search_by', ''));
        $query = DocumentChecklist::where('status', 1);

        if ($searchBy !== '') {
            $query->where('name', 'like', '%' . $searchBy . '%');
        }

        $totalData = $query->count();
        $lists = $query->sortable(['id' => 'desc'])->paginate(100);

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

            return redirect()->route('adminconsole.features.documentchecklist.index')->with('error', config('constants.unauthorized'));
        }

        $decodedId = is_numeric($id) ? (int) $id : $this->decodeString($id);

        if (!DocumentChecklist::where('id', $decodedId)->exists()) {
            if (request()->ajax() || request()->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Checklist not found.'], 404);
            }

            return redirect()->route('adminconsole.features.documentchecklist.index')->with('error', 'Checklist Not Exist');
        }

        return DocumentChecklist::find($decodedId);
    }

    protected function resolveDocTypeLabel($docType): string
    {
        switch ((int) $docType) {
            case 1:
                return 'Personal';
            case 2:
                return 'Visa';
            case 3:
                return 'Nomination';
            default:
                return '—';
        }
    }

    protected function serializeSummary(DocumentChecklist $item): array
    {
        return [
            'id' => $item->id,
            'name' => $item->name,
            'doc_type' => (int) $item->doc_type,
            'doc_type_label' => $this->resolveDocTypeLabel($item->doc_type),
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
        Log::error('Document checklist save error: ' . $e->getMessage(), [
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

    protected function respondSaved(Request $request, string $message, DocumentChecklist $item)
    {
        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'item' => $this->serializeSummary($item),
                'row_html' => view('AdminConsole.features.documentchecklist.partials.row', [
                    'list' => $item,
                ])->render(),
            ]);
        }

        return redirect()->route('adminconsole.features.documentchecklist.index')->with('success', $message);
    }
}
