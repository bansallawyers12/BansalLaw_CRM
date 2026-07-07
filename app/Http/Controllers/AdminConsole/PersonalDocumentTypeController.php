<?php

namespace App\Http\Controllers\AdminConsole;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\PersonalDocumentType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;

class PersonalDocumentTypeController extends Controller
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
                'html' => view('AdminConsole.features.personaldocumenttype.partials.list-table', $data)->render(),
                'pagination' => view('AdminConsole.features.personaldocumenttype.partials.pagination', $data)->render(),
            ]);
        }

        return view('AdminConsole.features.personaldocumenttype.index', $data);
    }

    public function create(Request $request)
    {
        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'success' => true,
                'html' => view('AdminConsole.features.personaldocumenttype.partials.form-fields', [
                    'mode' => 'create',
                    'fetchedData' => null,
                    'fieldPrefix' => 'create_pdt',
                ])->render(),
            ]);
        }

        return redirect()->route('adminconsole.features.personaldocumenttype.index', ['action' => 'create']);
    }

    public function store(Request $request)
    {
        if (!$request->isMethod('post')) {
            return redirect()->route('adminconsole.features.personaldocumenttype.index');
        }

        try {
            $this->validate($request, [
                'title' => 'required|unique:personal_document_types,title',
            ]);

            $obj = new PersonalDocumentType();
            $obj->title = $request->input('title');
            $obj->status = 1;

            if (!$obj->save()) {
                throw new \RuntimeException(config('constants.server_error'));
            }

            return $this->respondSaved($request, 'Personal document folder created successfully.', $obj->fresh());
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
                'html' => view('AdminConsole.features.personaldocumenttype.partials.form-fields', [
                    'mode' => 'edit',
                    'fetchedData' => $item,
                    'fieldPrefix' => 'edit_pdt',
                ])->render(),
            ]);
        }

        return redirect()->route('adminconsole.features.personaldocumenttype.index', [
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
                'html' => view('AdminConsole.features.personaldocumenttype.partials.view-body', [
                    'fetchedData' => $item,
                ])->render(),
            ]);
        }

        return redirect()->route('adminconsole.features.personaldocumenttype.index', [
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
                'title' => 'required|unique:personal_document_types,title,' . $item->id,
            ]);

            $item->title = $request->input('title');

            if (!$item->save()) {
                throw new \RuntimeException(config('constants.server_error'));
            }

            return $this->respondSaved($request, 'Personal document folder updated successfully.', $item->fresh());
        } catch (ValidationException $e) {
            return $this->respondValidationError($request, $e);
        } catch (\Exception $e) {
            return $this->respondError($request, $e, 'An error occurred while updating the folder.', is_numeric($id) ? (int) $id : null);
        }
    }

    protected function buildListPayload(Request $request): array
    {
        $searchBy = trim((string) $request->query('search_by', ''));
        $query = PersonalDocumentType::where('status', 1);

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

            return redirect()->route('adminconsole.features.personaldocumenttype.index')->with('error', config('constants.unauthorized'));
        }

        $decodedId = is_numeric($id) ? (int) $id : $this->decodeString($id);

        if (!PersonalDocumentType::where('id', $decodedId)->exists()) {
            if (request()->ajax() || request()->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Personal document folder not found.'], 404);
            }

            return redirect()->route('adminconsole.features.personaldocumenttype.index')->with('error', 'Personal Document Type Not Exist');
        }

        return PersonalDocumentType::find($decodedId);
    }

    protected function resolveClientLabel(?int $clientId): string
    {
        if (!$clientId) {
            return 'Common for all clients';
        }

        $admin = Admin::select('first_name', 'last_name')->find($clientId);

        return $admin ? trim($admin->first_name . ' ' . $admin->last_name) : 'NA';
    }

    protected function serializeSummary(PersonalDocumentType $item): array
    {
        return [
            'id' => $item->id,
            'title' => $item->title,
            'client_label' => $this->resolveClientLabel($item->client_id ? (int) $item->client_id : null),
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
        Log::error('Personal document type save error: ' . $e->getMessage(), [
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

    protected function respondSaved(Request $request, string $message, PersonalDocumentType $item)
    {
        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'item' => $this->serializeSummary($item),
                'row_html' => view('AdminConsole.features.personaldocumenttype.partials.row', [
                    'list' => $item,
                ])->render(),
            ]);
        }

        return redirect()->route('adminconsole.features.personaldocumenttype.index')->with('success', $message);
    }
}
