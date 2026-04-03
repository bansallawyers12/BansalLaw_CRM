<?php

namespace App\Http\Controllers\AdminConsole;

use App\Http\Controllers\Controller;
use App\Models\AnzscoOccupation;
use App\Services\AnzscoImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Yajra\DataTables\Facades\DataTables;

class AnzscoOccupationController extends Controller
{
    protected $importService;

    public function __construct(AnzscoImportService $importService)
    {
        $this->middleware('auth:admin');
        $this->importService = $importService;
    }

    /**
     * Display listing page
     */
    public function index()
    {
        return view('AdminConsole.database.anzsco.index');
    }

    /**
     * Get data for DataTables
     */
    public function getData(Request $request)
    {
        $query = AnzscoOccupation::query();

        // Apply filters
        if ($request->has('search') && $request->search['value']) {
            $searchTerm = $request->search['value'];
            $query->search($searchTerm);
        }

        if ($request->has('status') && $request->status !== '') {
            $query->where('is_active', $request->status);
        }

        if ($request->has('list') && $request->list !== '') {
            $validLists = ['mltssl', 'stsol', 'rol', 'csol'];
            $listName = strtolower($request->list);
            if (in_array($listName, $validLists)) {
                $listColumn = 'is_on_' . $listName;
                $query->where($listColumn, true);
            }
        }

        return DataTables::of($query)
            ->addColumn('lists', function ($occupation) {
                if (!$occupation) return '<span class="text-muted">None</span>';
                
                $lists = $occupation->occupation_lists;
                $badges = '';
                foreach ($lists as $list) {
                    $color = match($list) {
                        'MLTSSL' => 'success',
                        'STSOL' => 'info',
                        'ROL' => 'warning',
                        'CSOL' => 'secondary',
                        default => 'secondary'
                    };
                    $badges .= "<span class='badge badge-{$color} mr-1'>{$list}</span>";
                }
                return $badges ?: '<span class="text-muted">None</span>';
            })
            ->addColumn('status', function ($occupation) {
                if (!$occupation) return '';
                $checked = $occupation->is_active ? 'checked' : '';
                return "<label class='switch'><input type='checkbox' class='status-toggle' data-id='{$occupation->id}' {$checked}><span class='slider round'></span></label>";
            })
            ->addColumn('actions', function ($occupation) {
                if (!$occupation) return '';
                return view('AdminConsole.database.anzsco.partials.actions', compact('occupation'))->render();
            })
            ->editColumn('anzsco_code', function ($occupation) {
                return $occupation ? $occupation->anzsco_code : '';
            })
            ->editColumn('occupation_title', function ($occupation) {
                return $occupation ? $occupation->occupation_title : '';
            })
            ->editColumn('skill_level', function ($occupation) {
                return $occupation ? $occupation->skill_level : '';
            })
            ->editColumn('assessing_authority', function ($occupation) {
                return $occupation ? $occupation->assessing_authority : '';
            })
            ->editColumn('assessment_validity_years', function ($occupation) {
                return $occupation ? $occupation->assessment_validity_years : '';
            })
            ->rawColumns(['lists', 'status', 'actions'])
            ->make(true);
    }

    /**
     * Show create form
     */
    public function create()
    {
        return view('AdminConsole.database.anzsco.form');
    }

    /**
     * Store new occupation
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'anzsco_code' => 'required|string|max:10|unique:anzsco_occupations,anzsco_code',
            'occupation_title' => 'required|string|max:255',
            'skill_level' => 'nullable|integer|between:1,5',
            'assessing_authority' => 'nullable|string|max:255',
            'assessment_validity_years' => 'nullable|integer|min:1|max:10',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $data = $request->only([
                'anzsco_code',
                'occupation_title',
                'skill_level',
                'assessing_authority',
                'assessment_validity_years',
                'additional_info',
                'alternate_titles'
            ]);

            // Handle boolean checkboxes
            $data['is_on_mltssl'] = $request->has('is_on_mltssl');
            $data['is_on_stsol'] = $request->has('is_on_stsol');
            $data['is_on_rol'] = $request->has('is_on_rol');
            $data['is_on_csol'] = $request->has('is_on_csol');
            $data['is_active'] = $request->has('is_active') ? true : false;

            // Set default validity if not provided
            if (empty($data['assessment_validity_years'])) {
                $data['assessment_validity_years'] = 3;
            }

            $occupation = AnzscoOccupation::create($data);

            return response()->json([
                'success' => true,
                'message' => 'Occupation created successfully',
                'data' => $occupation
            ]);

        } catch (\Exception $e) {
            Log::error('Error creating ANZSCO occupation: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error creating occupation: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show edit form
     */
    public function edit($id)
    {
        $occupation = AnzscoOccupation::findOrFail($id);
        return view('AdminConsole.database.anzsco.form', compact('occupation'));
    }

    /**
     * Update occupation
     */
    public function update(Request $request, $id)
    {
        $occupation = AnzscoOccupation::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'anzsco_code' => 'required|string|max:10|unique:anzsco_occupations,anzsco_code,' . $id,
            'occupation_title' => 'required|string|max:255',
            'skill_level' => 'nullable|integer|between:1,5',
            'assessing_authority' => 'nullable|string|max:255',
            'assessment_validity_years' => 'nullable|integer|min:1|max:10',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $data = $request->only([
                'anzsco_code',
                'occupation_title',
                'skill_level',
                'assessing_authority',
                'assessment_validity_years',
                'additional_info',
                'alternate_titles'
            ]);

            // Handle boolean checkboxes
            $data['is_on_mltssl'] = $request->has('is_on_mltssl');
            $data['is_on_stsol'] = $request->has('is_on_stsol');
            $data['is_on_rol'] = $request->has('is_on_rol');
            $data['is_on_csol'] = $request->has('is_on_csol');
            $data['is_active'] = $request->has('is_active') ? true : false;

            $occupation->update($data);

            return response()->json([
                'success' => true,
                'message' => 'Occupation updated successfully',
                'data' => $occupation
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating ANZSCO occupation: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error updating occupation: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete occupation
     */
    public function destroy($id)
    {
        try {
            $occupation = AnzscoOccupation::findOrFail($id);
            $occupation->delete();

            return response()->json([
                'success' => true,
                'message' => 'Occupation deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error deleting ANZSCO occupation: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error deleting occupation: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Toggle active status
     */
    public function toggleStatus(Request $request, $id)
    {
        try {
            $occupation = AnzscoOccupation::findOrFail($id);
            $occupation->is_active = !$occupation->is_active;
            $occupation->save();

            return response()->json([
                'success' => true,
                'message' => 'Status updated successfully',
                'is_active' => $occupation->is_active
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating status'
            ], 500);
        }
    }

    /**
     * Show import page
     */
    public function importPage()
    {
        return view('AdminConsole.database.anzsco.import');
    }

    /**
     * Handle file import
     */
    public function import(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:csv,xlsx,xls|max:10240', // 10MB max
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $file = $request->file('file');
            $filePath = $file->getRealPath();
            
            // Get column mapping from request
            $columnMapping = json_decode($request->input('column_mapping', '{}'), true);
            
            // If no mapping provided, use default mapping
            if (empty($columnMapping)) {
                $columnMapping = $this->getDefaultColumnMapping();
            }

            $updateExisting = $request->input('update_existing', true);

            $results = $this->importService->import($filePath, $columnMapping, $updateExisting);

            return response()->json($results);

        } catch (\Exception $e) {
            Log::error('Import error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Download import template
     */
    public function downloadTemplate()
    {
        $template = $this->importService->generateTemplate();
        
        $filename = 'anzsco_import_template_' . date('Y-m-d') . '.csv';
        
        return response($template, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Get default column mapping
     */
    protected function getDefaultColumnMapping()
    {
        return [
            'anzsco_code' => 'anzsco_code',
            'occupation_title' => 'occupation_title',
            'skill_level' => 'skill_level',
            'is_on_mltssl' => 'mltssl',
            'is_on_stsol' => 'stsol',
            'is_on_rol' => 'rol',
            'is_on_csol' => 'csol',
            'assessing_authority' => 'assessing_authority',
            'assessment_validity_years' => 'validity_years',
            'additional_info' => 'additional_info',
            'alternate_titles' => 'alternate_titles'
        ];
    }

    /**
     * API: Search occupations for autocomplete
     */
    public function search(Request $request)
    {
        $query = $request->input('q', '');
        $limit = $request->input('limit', 20);

        if (empty($query)) {
            return response()->json([]);
        }

        $occupations = AnzscoOccupation::active()
            ->search($query)
            ->limit($limit)
            ->get(['id', 'anzsco_code', 'occupation_title', 'assessing_authority', 
                   'assessment_validity_years', 'is_on_mltssl', 'is_on_stsol', 
                   'is_on_rol', 'is_on_csol']);

        return response()->json($occupations->map(function($occ) {
            return [
                'id' => $occ->id,
                'anzsco_code' => $occ->anzsco_code,
                'occupation_title' => $occ->occupation_title,
                'assessing_authority' => $occ->assessing_authority,
                'assessment_validity_years' => $occ->assessment_validity_years,
                'lists' => $occ->occupation_lists,
                'lists_string' => $occ->occupation_lists_string,
                'label' => $occ->occupation_title . ' (' . $occ->anzsco_code . ')',
                'value' => $occ->occupation_title
            ];
        }));
    }

    /**
     * API: Get occupation by code
     */
    public function getByCode($code)
    {
        $occupation = AnzscoOccupation::active()
            ->where('anzsco_code', $code)
            ->first();

        if (!$occupation) {
            return response()->json([
                'success' => false,
                'message' => 'Occupation not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $occupation->id,
                'anzsco_code' => $occupation->anzsco_code,
                'occupation_title' => $occupation->occupation_title,
                'assessing_authority' => $occupation->assessing_authority,
                'assessment_validity_years' => $occupation->assessment_validity_years,
                'lists' => $occupation->occupation_lists,
                'lists_string' => $occupation->occupation_lists_string
            ]
        ]);
    }
}



