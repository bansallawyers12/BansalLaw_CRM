<?php

namespace App\Http\Controllers\AdminConsole;

use App\Http\Controllers\Controller;
use App\Models\UserRole;
use Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;

class UserroleController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:admin');
    }

    public function index(Request $request)
    {
        $check = $this->checkAuthorizationAction('user_role', $request->route()->getActionMethod(), Auth::user()->role);
        if ($check) {
            return $this->respondUnauthorized($request);
        }

        $data = $this->buildRolesListPayload($request);

        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'success' => true,
                'total' => $data['totalData'],
                'html' => view('AdminConsole.system.roles.partials.list-table', $data)->render(),
                'pagination' => view('AdminConsole.system.roles.partials.pagination', $data)->render(),
            ]);
        }

        return view('AdminConsole.system.roles.index', $data);
    }

    public function create(Request $request)
    {
        $check = $this->checkAuthorizationAction('user_role', $request->route()->getActionMethod(), Auth::user()->role);
        if ($check) {
            return $this->respondUnauthorized($request);
        }

        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'success' => true,
                'html' => view('AdminConsole.system.roles.partials.form-fields', [
                    'mode' => 'create',
                    'fetchedData' => null,
                    'fieldPrefix' => 'create_role',
                ])->render(),
            ]);
        }

        return redirect()->route('adminconsole.system.roles.index', ['action' => 'create']);
    }

    public function store(Request $request)
    {
        $check = $this->checkAuthorizationAction('user_role', $request->route()->getActionMethod(), Auth::user()->role);
        if ($check) {
            return $this->respondUnauthorized($request);
        }

        if (!$request->isMethod('post')) {
            return redirect()->route('adminconsole.system.roles.index');
        }

        try {
            $this->validate($request, [
                'name' => 'required|max:255',
            ]);

            $requestData = $request->all();

            $obj = new UserRole();
            $obj->name = @$requestData['name'];
            $obj->description = @$requestData['description'];
            $obj->module_access = json_encode(@$requestData['module_access']);

            $saved = $obj->save();

            if (!$saved) {
                throw new \RuntimeException(config('constants.server_error'));
            }

            return $this->respondRoleSaved($request, 'User role added successfully.', $obj->fresh());
        } catch (ValidationException $e) {
            return $this->respondRoleValidationError($request, $e);
        } catch (\Exception $e) {
            return $this->respondRoleError($request, $e, 'An error occurred while creating the role.');
        }
    }

    public function edit(Request $request, $id)
    {
        $check = $this->checkAuthorizationAction('user_role', 'edit', Auth::user()->role);
        if ($check) {
            return $this->respondUnauthorized($request);
        }

        $role = $this->findRoleOrFail($id);
        if ($role instanceof \Symfony\Component\HttpFoundation\Response) {
            return $role;
        }

        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'success' => true,
                'role' => $this->serializeRoleSummary($role),
                'html' => view('AdminConsole.system.roles.partials.form-fields', [
                    'mode' => 'edit',
                    'fetchedData' => $role,
                    'fieldPrefix' => 'edit_role',
                ])->render(),
            ]);
        }

        return redirect()->route('adminconsole.system.roles.index', ['action' => 'edit', 'id' => $role->id]);
    }

    public function view(Request $request, $id)
    {
        $check = $this->checkAuthorizationAction('user_role', 'edit', Auth::user()->role);
        if ($check) {
            return $this->respondUnauthorized($request);
        }

        $role = $this->findRoleOrFail($id);
        if ($role instanceof \Symfony\Component\HttpFoundation\Response) {
            return $role;
        }

        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'success' => true,
                'role' => $this->serializeRoleSummary($role),
                'html' => view('AdminConsole.system.roles.partials.view-body', [
                    'fetchedData' => $role,
                ])->render(),
            ]);
        }

        return redirect()->route('adminconsole.system.roles.index', ['action' => 'view', 'id' => $role->id]);
    }

    public function update(Request $request, $id)
    {
        $check = $this->checkAuthorizationAction('user_role', 'update', Auth::user()->role);
        if ($check) {
            return $this->respondUnauthorized($request);
        }

        try {
            $role = $this->findRoleOrFail($id);
            if ($role instanceof \Symfony\Component\HttpFoundation\Response) {
                return $role;
            }

            $this->validate($request, [
                'name' => 'required|max:255',
            ]);

            $requestData = $request->all();

            $role->name = @$requestData['name'];
            $role->description = @$requestData['description'];
            $role->module_access = json_encode(@$requestData['module_access']);

            $saved = $role->save();

            if (!$saved) {
                throw new \RuntimeException(config('constants.server_error'));
            }

            return $this->respondRoleSaved($request, 'User role updated successfully.', $role->fresh());
        } catch (ValidationException $e) {
            return $this->respondRoleValidationError($request, $e);
        } catch (\Exception $e) {
            return $this->respondRoleError($request, $e, 'An error occurred while updating the role.', is_numeric($id) ? (int) $id : null);
        }
    }

    protected function buildRolesListPayload(Request $request): array
    {
        $searchBy = trim((string) $request->query('search_by', ''));
        $query = UserRole::query();

        if ($searchBy !== '') {
            $query->where(function ($q) use ($searchBy) {
                $q->where('name', 'like', '%' . $searchBy . '%')
                    ->orWhere('description', 'like', '%' . $searchBy . '%');
            });
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

    protected function findRoleOrFail($id)
    {
        if (!isset($id) || $id === '') {
            if (request()->ajax() || request()->expectsJson()) {
                return response()->json(['success' => false, 'message' => config('constants.unauthorized')], 404);
            }

            return redirect()->route('adminconsole.system.roles.index')->with('error', config('constants.unauthorized'));
        }

        $decodedId = is_numeric($id) ? (int) $id : $this->decodeString($id);

        if (!UserRole::where('id', $decodedId)->exists()) {
            if (request()->ajax() || request()->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'User role not found.'], 404);
            }

            return redirect()->route('adminconsole.system.roles.index')->with('error', 'User Role Not Exist');
        }

        return UserRole::find($decodedId);
    }

    protected function serializeRoleSummary(UserRole $role): array
    {
        $moduleAccess = json_decode($role->module_access ?? '{}', true);
        if (!is_array($moduleAccess)) {
            $moduleAccess = (array) $moduleAccess;
        }

        return [
            'id' => $role->id,
            'name' => $role->name,
            'description' => $role->description,
            'permission_count' => count($moduleAccess),
        ];
    }

    protected function respondUnauthorized(Request $request)
    {
        if ($request->ajax() || $request->expectsJson()) {
            return response()->json(['success' => false, 'message' => config('constants.unauthorized')], 403);
        }

        return Redirect::to('/dashboard')->with('error', config('constants.unauthorized'));
    }

    protected function respondRoleValidationError(Request $request, ValidationException $e)
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

    protected function respondRoleError(Request $request, \Exception $e, string $message, ?int $roleId = null)
    {
        Log::error('Role save error: ' . $e->getMessage(), [
            'role_id' => $roleId,
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

    protected function respondRoleSaved(Request $request, string $message, UserRole $role)
    {
        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'role' => $this->serializeRoleSummary($role),
                'row_html' => view('AdminConsole.system.roles.partials.row', [
                    'list' => $role,
                ])->render(),
            ]);
        }

        return redirect()->route('adminconsole.system.roles.index')->with('success', $message);
    }
}
