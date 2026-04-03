<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use App\Services\ActiveStaffService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActiveStaffController extends Controller
{
    public function __construct(
        protected ActiveStaffService $activeStaff
    ) {
        $this->middleware('auth:admin');
    }

    /**
     * Return the list of currently active staff.
     */
    public function index(Request $request): JsonResponse
    {
        $threshold = (int) $request->query('threshold', 5);
        $search = $request->query('search');
        $roleId = $request->query('role_id') ? (int) $request->query('role_id') : null;
        $teamId = $request->query('team_id') ? (int) $request->query('team_id') : null;
        $sortBy = $request->query('sort_by', 'name');
        $sortDir = $request->query('sort_dir', 'asc');
        $perPage = (int) $request->query('per_page', 15);
        $page = (int) $request->query('page', 1);

        $result = $this->activeStaff->getActiveStaff(
            max(1, $threshold),
            $search,
            $roleId,
            $teamId,
            $sortBy,
            $sortDir,
            max(1, min(100, $perPage)), // Limit between 1 and 100
            max(1, $page)
        );

        return response()->json($result);
    }
}
