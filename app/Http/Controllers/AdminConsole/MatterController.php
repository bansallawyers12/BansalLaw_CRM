<?php

namespace App\Http\Controllers\AdminConsole;

use App\Http\Controllers\Controller;
use App\Models\Matter;
use App\Models\Workflow;
use App\Services\AdminConsoleFormDataService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

use Illuminate\Support\Facades\Auth;

class MatterController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:admin');
    }

    public function index(Request $request)
    {
        $check = $this->checkAuthorizationAction('matter_management', $request->route()->getActionMethod(), Auth::user()?->role);
        if ($check) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => config('constants.unauthorized')], 403);
            }
            return Redirect::to('/dashboard')->with('error', config('constants.unauthorized'));
        }

        $data = $this->buildListPayload($request);

        if ($request->ajax() || $request->expectsJson()) {
            $lists = $data['lists'];
            $payload = [
                'success' => true,
                'total' => $data['totalData'],
                'currentPage' => $lists->currentPage(),
                'lastPage' => $lists->lastPage(),
                'hasMore' => $lists->hasMorePages(),
                'loaded' => (int) ($lists->lastItem() ?? 0),
                'status' => view('AdminConsole.features.matter.partials.scroll-status', $data)->render(),
            ];

            if ($request->boolean('append')) {
                $payload['append'] = true;
                $payload['rows'] = view('AdminConsole.features.matter.partials.list-rows', $data)->render();
            } else {
                $payload['html'] = view('AdminConsole.features.matter.partials.list-table', $data)->render();
            }

            return response()->json($payload);
        }

        return view('AdminConsole.features.matter.index', $data);
    }

    public function create(Request $request)
    {
        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'success' => true,
                'html' => view('AdminConsole.features.matter.partials.form-fields', [
                    'mode' => 'create',
                    'fetchedData' => null,
                    'fieldPrefix' => 'create_mat',
                    'workflows' => app(AdminConsoleFormDataService::class)->workflowOptions(),
                ])->render(),
            ]);
        }

        return redirect()->route('adminconsole.features.matter.index', ['action' => 'create']);
    }

    public function store(Request $request)
    {
        if (!$request->isMethod('post')) {
            return redirect()->route('adminconsole.features.matter.index');
        }

        try {
            $this->validateMatterRequest($request);

            $obj = new Matter();
            $this->fillMatterFromRequest($obj, $request->all(), $request);

            if (!$obj->save()) {
                throw new \RuntimeException(config('constants.server_error'));
            }

            return $this->respondSaved($request, 'Matter added successfully.', $obj->fresh(['workflow']));
        } catch (ValidationException $e) {
            return $this->respondValidationError($request, $e);
        } catch (\Exception $e) {
            return $this->respondError($request, $e, 'An error occurred while creating the matter.');
        }
    }

    public function edit(Request $request, $id)
    {
        $matter = $this->findMatterOrFail($id);
        if ($matter instanceof \Symfony\Component\HttpFoundation\Response) {
            return $matter;
        }

        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'success' => true,
                'matter' => $this->serializeMatterSummary($matter),
                'html' => view('AdminConsole.features.matter.partials.form-fields', [
                    'mode' => 'edit',
                    'fetchedData' => $matter,
                    'fieldPrefix' => 'edit_mat',
                    'workflows' => app(AdminConsoleFormDataService::class)->workflowOptions(),
                ])->render(),
            ]);
        }

        return redirect()->route('adminconsole.features.matter.index', [
            'action' => 'edit',
            'id' => $matter->id,
        ]);
    }

    public function view(Request $request, $id)
    {
        $matter = $this->findMatterOrFail($id);
        if ($matter instanceof \Symfony\Component\HttpFoundation\Response) {
            return $matter;
        }

        $matter->load('workflow');

        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'success' => true,
                'matter' => $this->serializeMatterSummary($matter),
                'html' => view('AdminConsole.features.matter.partials.view-body', [
                    'fetchedData' => $matter,
                ])->render(),
            ]);
        }

        return redirect()->route('adminconsole.features.matter.index', [
            'action' => 'view',
            'id' => $matter->id,
        ]);
    }

    public function update(Request $request, $id)
    {
        try {
            $matter = $this->findMatterOrFail($id);
            if ($matter instanceof \Symfony\Component\HttpFoundation\Response) {
                return $matter;
            }

            $this->validateMatterRequest($request, $matter->id);

            $this->fillMatterFromRequest($matter, $request->all(), $request);

            if (!$matter->save()) {
                throw new \RuntimeException(config('constants.server_error'));
            }

            return $this->respondSaved($request, 'Matter updated successfully.', $matter->fresh(['workflow']));
        } catch (ValidationException $e) {
            return $this->respondValidationError($request, $e);
        } catch (\Exception $e) {
            return $this->respondError($request, $e, 'An error occurred while updating the matter.', is_numeric($id) ? (int) $id : null);
        }
    }

    protected function buildListPayload(Request $request): array
    {
        $searchBy = trim((string) $request->query('search_by', $request->query('title', '')));
        $perPage = $this->resolveListPerPage($request);
        $query = Matter::query();

        if ($searchBy !== '') {
            $query->where(function ($q) use ($searchBy) {
                $q->where('title', 'like', '%' . $searchBy . '%')
                    ->orWhere('nick_name', 'like', '%' . $searchBy . '%');
            });
        }

        $totalData = $query->count();
        $lists = $query->sortable(['id' => 'desc'])->paginate($perPage)->withQueryString();

        return [
            'lists' => $lists,
            'totalData' => $totalData,
            'searchBy' => $searchBy,
            'perPage' => $perPage,
            'hasStreamColumn' => Schema::hasColumn('matters', 'stream'),
        ];
    }

    protected function resolveListPerPage(Request $request): int
    {
        $allowed = [10, 20, 50, 100];
        $perPage = (int) $request->query('per_page', config('constants.limit', 20));

        return in_array($perPage, $allowed, true) ? $perPage : (int) config('constants.limit', 20);
    }

    protected function findMatterOrFail($id)
    {
        if (!isset($id) || $id === '') {
            if (request()->ajax() || request()->expectsJson()) {
                return response()->json(['success' => false, 'message' => config('constants.unauthorized')], 404);
            }

            return redirect()->route('adminconsole.features.matter.index')->with('error', config('constants.unauthorized'));
        }

        $decodedId = is_numeric($id) ? (int) $id : $this->decodeString($id);

        if (!Matter::where('id', $decodedId)->exists()) {
            if (request()->ajax() || request()->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Matter not found.'], 404);
            }

            return redirect()->route('adminconsole.features.matter.index')->with('error', 'Matter Not Exist');
        }

        return Matter::find($decodedId);
    }

    protected function validateMatterRequest(Request $request, ?int $ignoreId = null): void
    {
        $streamKeys = array_keys(config('matter_streams.streams', []));
        $nickRule = Rule::unique('matters', 'nick_name');
        if ($ignoreId) {
            $nickRule = $nickRule->ignore($ignoreId);
        }

        $this->validate($request, [
            'title' => 'required|max:255',
            'nick_name' => ['required', 'max:255', $nickRule],
            'stream' => ['nullable', 'string', 'max:64', Rule::in($streamKeys)],
        ]);
    }

    protected function fillMatterFromRequest(Matter $obj, array $requestData, Request $request): void
    {
        $obj->title = $requestData['title'] ?? null;
        $obj->nick_name = $requestData['nick_name'] ?? null;

        if (Schema::hasColumn('matters', 'stream')) {
            $obj->stream = isset($requestData['stream']) && $requestData['stream'] !== ''
                ? $requestData['stream']
                : null;
        }

        $obj->workflow_id = !empty($requestData['workflow_id']) ? $requestData['workflow_id'] : null;
        $obj->is_for_company = $requestData['is_for_company'] ?? ($obj->exists ? ($obj->is_for_company ?? 0) : 0);

        if (!$obj->exists) {
            $obj->status = $requestData['status'] ?? 1;
        }

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
    }

    protected function resolveStreamLabel(?string $stream): string
    {
        if (!$stream) {
            return '—';
        }

        return \Illuminate\Support\Arr::get(config('matter_streams.streams', []), $stream, $stream);
    }

    protected function serializeMatterSummary(Matter $matter): array
    {
        if (!$matter->relationLoaded('workflow')) {
            $matter->load('workflow');
        }

        return [
            'id' => $matter->id,
            'title' => $matter->title,
            'nick_name' => $matter->nick_name,
            'stream' => $matter->stream,
            'stream_label' => $this->resolveStreamLabel($matter->stream),
            'workflow_id' => $matter->workflow_id,
            'workflow_name' => optional($matter->workflow)->name,
            'is_for_company' => (int) ($matter->is_for_company ?? 0),
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

    protected function respondError(Request $request, \Exception $e, string $message, ?int $matterId = null)
    {
        Log::error('Matter save error: ' . $e->getMessage(), [
            'matter_id' => $matterId,
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

    protected function respondSaved(Request $request, string $message, Matter $matter)
    {
        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'matter' => $this->serializeMatterSummary($matter),
                'row_html' => view('AdminConsole.features.matter.partials.row', [
                    'list' => $matter,
                    'hasStreamColumn' => Schema::hasColumn('matters', 'stream'),
                ])->render(),
            ]);
        }

        return redirect()->route('adminconsole.features.matter.index')->with('success', $message);
    }
}
