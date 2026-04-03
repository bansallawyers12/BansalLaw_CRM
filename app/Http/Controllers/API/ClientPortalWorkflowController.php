<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Events\NotificationCountUpdated;
use App\Models\ClientMatter;
use App\Models\Document;
use App\Services\ClientPortalActionNoteService;
use App\Models\Notification;
use App\Models\Staff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ClientPortalWorkflowController extends Controller
{
    /**
     * List of Workflow Stages with Active Stage
     * GET /api/workflow/stages
     * 
     * Returns all workflow stages with the current active stage for the client
     */
    public function getWorkflowStages(Request $request)
    {
        try {
            $admin = $request->user();
            $clientId = $admin->id;
            
            // Get client_matter_id parameter (optional)
            $clientMatterId = $request->get('client_matter_id');
            $workflowId = null;

            // Resolve workflow_id from client_matters using client_id + client_matter_id
            if (!is_null($clientMatterId)) {
                $workflowId = DB::table('client_matters')
                    ->where('client_id', $clientId)
                    ->where('id', $clientMatterId)
                    ->value('workflow_id');
            }
            
            // Use client_matter_id for document checklists (migrated from applications table)
            // Get all workflow stages (ordered by sort_order, then id)
            $workflowStagesQuery = DB::table('workflow_stages');

            if (!is_null($workflowId)) {
                $workflowStagesQuery->where('workflow_id', $workflowId);
            }

            $workflowStages = $workflowStagesQuery
                ->orderByRaw('COALESCE(sort_order, id) ASC')
                ->select(
                    'id',
                    'name',
                    'created_at',
                    'updated_at'
                )
                ->get()
                ->map(function ($stage) use ($clientId, $clientMatterId) {
                    // Calculate allowed_checklist_count and get allowed_checklist items for this stage
                    $allowedChecklistCount = 0;
                    $allowedChecklist = [];
                    
                    if (!is_null($clientMatterId)) {
                        $checklistItems = DB::table('cp_doc_checklists')
                            ->where('client_matter_id', $clientMatterId)
                            ->where('client_id', $clientId)
                            ->where('wf_stage', $stage->name)
                            ->where('allow_client', 1)
                            ->select('id', 'cp_checklist_name')
                            ->orderBy('id', 'asc')
                            ->get();
                        
                        $allowedChecklistCount = $checklistItems->count();
                        
                        // Format checklist items as array with id, name, and no_of_document_uploaded (for this API only)
                        $allowedChecklist = $checklistItems->map(function ($item) use ($clientMatterId) {
                            $noOfDocumentUploaded = Document::workflowChecklist()
                                ->where('client_matter_id', $clientMatterId)
                                ->where('cp_list_id', $item->id)
                                ->count();
                            return [
                                'id' => $item->id,
                                'name' => $item->cp_checklist_name,
                                'no_of_document_uploaded' => $noOfDocumentUploaded,
                            ];
                        })->toArray();
                    }
                    
                    return [
                        'id' => $stage->id,
                        'name' => $stage->name,
                        'stage_name' => $stage->name, // Alias for consistency
                        'allowed_checklist_count' => $allowedChecklistCount,
                        'allowed_checklist' => $allowedChecklist,
                        'created_at' => $stage->created_at,
                        'updated_at' => $stage->updated_at
                    ];
                });

            // Get active stage for the client
            $activeStage = null;
            $activeStageInfo = null;
            
            if (!is_null($clientMatterId)) {
                // Get active stage for specific matter
                $activeStageInfo = DB::table('client_matters')
                    ->join('workflow_stages', 'client_matters.workflow_stage_id', '=', 'workflow_stages.id')
                    ->select(
                        'client_matters.workflow_stage_id',
                        'workflow_stages.name as stage_name',
                        'client_matters.client_unique_matter_no',
                        'client_matters.matter_status',
                        'client_matters.updated_at as stage_updated_at'
                    )
                    ->where('client_matters.client_id', $clientId)
                    ->where('client_matters.id', $clientMatterId)
                    ->where('client_matters.matter_status', 1) // Active matter
                    ->first();
            } else {
                // Get active stage for the most recent active matter
                $activeStageInfo = DB::table('client_matters')
                    ->join('workflow_stages', 'client_matters.workflow_stage_id', '=', 'workflow_stages.id')
                    ->select(
                        'client_matters.workflow_stage_id',
                        'workflow_stages.name as stage_name',
                        'client_matters.client_unique_matter_no',
                        'client_matters.matter_status',
                        'client_matters.updated_at as stage_updated_at'
                    )
                    ->where('client_matters.client_id', $clientId)
                    ->where('client_matters.matter_status', 1) // Active matter
                    ->orderBy('client_matters.updated_at', 'desc')
                    ->first();
            }

            if ($activeStageInfo) {
                $activeStage = [
                    'id' => $activeStageInfo->workflow_stage_id,
                    'name' => $activeStageInfo->stage_name,
                    'stage_name' => $activeStageInfo->stage_name,
                    'client_matter_no' => $activeStageInfo->client_unique_matter_no,
                    'matter_status' => $activeStageInfo->matter_status,
                    'stage_updated_at' => $activeStageInfo->stage_updated_at,
                    'is_active' => true
                ];
            }

            // Mark active stage in the workflow stages list
            $workflowStagesWithActive = $workflowStages->map(function ($stage) use ($activeStage) {
                $stage['is_active'] = ($activeStage && $stage['id'] == $activeStage['id']);
                $stage['is_current_stage'] = ($activeStage && $stage['id'] == $activeStage['id']);
                return $stage;
            });

            // Get case summary when client_matter_id is provided
            $caseSummary = null;
            if (!is_null($clientMatterId)) {
                $caseSummary = $this->getCaseSummaryForClientMatter($clientId, $clientMatterId);
            }

            $responseData = [
                'workflow_stages' => $workflowStagesWithActive,
                'total_stages' => $workflowStages->count(),
                'active_stage' => $activeStage,
                'has_active_stage' => !is_null($activeStage),
                'client_id' => $clientId,
                'client_matter_id' => $clientMatterId
            ];

            if (!is_null($caseSummary)) {
                $responseData['case_summary'] = $caseSummary;
            }

            return response()->json([
                'success' => true,
                'data' => $responseData
            ], 200);

        } catch (\Exception $e) {
            Log::error('Workflow Stages API Error: ' . $e->getMessage(), [
                'user_id' => $admin->id ?? null,
                'client_matter_id' => $clientMatterId ?? null,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch workflow stages',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get case summary for a client matter
     * Returns: case_name, case_status
     */
    private function getCaseSummaryForClientMatter($clientId, $clientMatterId)
    {
        $matter = DB::table('client_matters')
            ->leftJoin('matters', 'client_matters.sel_matter_id', '=', 'matters.id')
            ->where('client_matters.client_id', $clientId)
            ->where('client_matters.id', $clientMatterId)
            ->select(
                'client_matters.client_unique_matter_no',
                'client_matters.matter_status',
                'client_matters.sel_matter_id',
                'matters.title as matter_title',
                'matters.nick_name as matter_nick_name'
            )
            ->first();

        if (!$matter) {
            return null;
        }

        // Build case_name: "nick_name - title (client_unique_matter_no)" e.g. "020 - Bridging (Class B) (BA_2)"
        $caseName = 'General Matter';
        if ($matter->sel_matter_id != 1 && !empty($matter->matter_title)) {
            if (!empty($matter->matter_nick_name)) {
                $caseName = $matter->matter_nick_name . ' - ' . $matter->matter_title;
            } else {
                $caseName = $matter->matter_title;
            }
        }
        if (!empty($matter->client_unique_matter_no)) {
            $caseName .= ' (' . $matter->client_unique_matter_no . ')';
        }

        return [
            'case_name' => $caseName,
            'case_status' => $matter->matter_status == 1 ? 'Active' : 'Inactive',
        ];
    }

    /**
     * Get Workflow Stage Details
     * GET /api/workflow/stages/{stage_id}
     * 
     * Returns details of a specific workflow stage
     */
    public function getWorkflowStageDetails(Request $request, $stageId)
    {
        try {
            $admin = $request->user();
            $clientId = $admin->id;

            // Validate stage_id
            if (!is_numeric($stageId) || $stageId <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid stage ID'
                ], 422);
            }

            // Get workflow stage details
            $stage = DB::table('workflow_stages')
                ->where('id', $stageId)
                ->select(
                    'id',
                    'name',
                    'created_at',
                    'updated_at'
                )
                ->first();

            if (!$stage) {
                return response()->json([
                    'success' => false,
                    'message' => 'Workflow stage not found'
                ], 404);
            }

            // Check if this stage is currently active for the client
            $isActive = DB::table('client_matters')
                ->where('client_id', $clientId)
                ->where('workflow_stage_id', $stageId)
                ->where('matter_status', 1) // Active matter
                ->exists();

            // Get client matters in this stage
            $clientMattersInStage = DB::table('client_matters')
                ->join('matters', 'client_matters.sel_matter_id', '=', 'matters.id')
                ->select(
                    'client_matters.id',
                    'client_matters.client_unique_matter_no',
                    'client_matters.matter_status',
                    'matters.title as matter_title',
                    'client_matters.updated_at'
                )
                ->where('client_matters.client_id', $clientId)
                ->where('client_matters.workflow_stage_id', $stageId)
                ->orderBy('client_matters.updated_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'stage' => [
                        'id' => $stage->id,
                        'name' => $stage->name,
                        'stage_name' => $stage->name,
                        'is_active' => $isActive,
                        'created_at' => $stage->created_at,
                        'updated_at' => $stage->updated_at
                    ],
                    'client_matters_in_stage' => $clientMattersInStage,
                    'total_matters_in_stage' => $clientMattersInStage->count(),
                    'active_matters_in_stage' => $clientMattersInStage->where('matter_status', 1)->count()
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Workflow Stage Details API Error: ' . $e->getMessage(), [
                'user_id' => $admin->id ?? null,
                'stage_id' => $stageId,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch workflow stage details',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get Allowed Checklist for a Client Matter
     * GET /api/workflow/allowed-checklist
     *
     * Returns all checklist items where allow_client = 1 for a given client matter,
     * optionally filtered by workflow stage ID or a single checklist item ID.
     *
     * Query params:
     *   - client_matter_id     (required): The client matter ID
     *   - stage_id             (optional): Filter results to a specific workflow stage
     *   - allowed_checklist_id (optional): Filter to a single checklist item by ID
     */
    public function getAllowedChecklist(Request $request)
    {
        try {
            $admin    = $request->user();
            $clientId = $admin->id;

            $clientMatterId     = $request->get('client_matter_id');
            $stageId            = $request->get('stage_id');
            $allowedChecklistId = $request->get('allowed_checklist_id');

            if (!$clientMatterId) {
                return response()->json([
                    'success' => false,
                    'message' => 'client_matter_id is required'
                ], 422);
            }

            // Verify the matter belongs to this client and get its current stage
            $matter = DB::table('client_matters')
                ->leftJoin('workflow_stages', 'client_matters.workflow_stage_id', '=', 'workflow_stages.id')
                ->where('client_matters.id', $clientMatterId)
                ->where('client_matters.client_id', $clientId)
                ->select(
                    'client_matters.id',
                    'client_matters.client_id',
                    'client_matters.matter_status',
                    'workflow_stages.name as current_stage'
                )
                ->first();

            if (!$matter) {
                return response()->json([
                    'success' => false,
                    'message' => 'Client matter not found'
                ], 404);
            }

            // Fetch allowed checklist items, optionally filtered by stage
            $query = DB::table('cp_doc_checklists')
                ->where('client_matter_id', $clientMatterId)
                ->where('client_id', $clientId)
                ->where('allow_client', 1);

            if ($stageId) {
                $query->where('wf_stage_id', $stageId);
            }

            if ($allowedChecklistId !== null && $allowedChecklistId !== '') {
                $query->where('id', (int) $allowedChecklistId);
            }

            $checklistItems = $query
                ->select('id', 'cp_checklist_name', 'description', 'wf_stage', 'wf_stage_id', 'allow_client')
                ->orderBy('wf_stage_id', 'asc')
                ->orderBy('id', 'asc')
                ->get();

            // When filtering by allowed_checklist_id: return one entry per uploaded document (so multiple docs for same checklist all appear)
            if ($allowedChecklistId !== null && $allowedChecklistId !== '') {
                $allowedChecklists = collect();
                foreach ($checklistItems as $item) {
                    $allDocs = DB::table('documents')
                        ->where('cp_list_id', $item->id)
                        ->where('type', 'workflow_checklist')
                        ->where('client_matter_id', $clientMatterId)
                        ->orderBy('id', 'desc')
                        ->select('id', 'file_name', 'myfile', 'cp_doc_status', 'created_at')
                        ->get();
                    if ($allDocs->isEmpty()) {
                        continue;
                    }
                    $stageSlug = $item->wf_stage
                        ? strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', trim($item->wf_stage)))
                        : null;
                    foreach ($allDocs as $doc) {
                        $docStatus = $doc->cp_doc_status ?? null;
                        $docStatusText = match (true) {
                            $docStatus === 0 => 'In Progress',
                            $docStatus === 1 => 'Approved',
                            $docStatus === 2 => 'Rejected',
                            default => null,
                        };
                        $allowedChecklists->push([
                            'id'                => $item->id,
                            'checklist_name'    => $item->cp_checklist_name,
                            'document_type'     => $item->cp_checklist_name,
                            'description'       => $item->description,
                            'type'              => $stageSlug,
                            'type_id'           => $item->wf_stage_id,
                            'type_name'         => $item->wf_stage,
                            'is_mandatory'      => (bool) $item->allow_client,
                            'due_date'          => null,
                            'due_time'          => null,
                            'is_upload'         => true,
                            'file_name'         => $doc->file_name,
                            'file_url'          => $doc->myfile,
                            'doc_status'        => $docStatus,
                            'doc_status_text'   => $docStatusText,
                            'uploaded_doc_id'  => $doc->id,
                            'upload_doc_date'  => $doc->created_at ? date('Y-m-d', strtotime($doc->created_at)) : null,
                        ]);
                    }
                }
            } else {
                // Enrich each item with upload status from the documents table (latest document only per checklist)
                $allowedChecklists = $checklistItems->map(function ($item) use ($clientMatterId) {
                    $latestDoc = DB::table('documents')
                        ->where('cp_list_id', $item->id)
                        ->where('type', 'workflow_checklist')
                        ->where('client_matter_id', $clientMatterId)
                        ->orderBy('id', 'desc')
                        ->select('id', 'file_name', 'myfile', 'cp_doc_status', 'created_at')
                        ->first();

                    $stageSlug = $item->wf_stage
                        ? strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', trim($item->wf_stage)))
                        : null;

                    $docStatus = $latestDoc?->cp_doc_status ?? null;
                    $docStatusText = match (true) {
                        $docStatus === 0 => 'In Progress',
                        $docStatus === 1 => 'Approved',
                        $docStatus === 2 => 'Rejected',
                        default => null,
                    };

                    return [
                        'id'                => $item->id,
                        'checklist_name'    => $item->cp_checklist_name,
                        'document_type'     => $item->cp_checklist_name,
                        'description'       => $item->description,
                        'type'              => $stageSlug,
                        'type_id'           => $item->wf_stage_id,
                        'type_name'         => $item->wf_stage,
                        'is_mandatory'      => (bool) $item->allow_client,
                        'due_date'          => null,
                        'due_time'          => null,
                        'is_upload'         => $latestDoc !== null,
                        'file_name'         => $latestDoc?->file_name ?? null,
                        'file_url'          => $latestDoc?->myfile ?? null,
                        'doc_status'        => $docStatus,
                        'doc_status_text'   => $docStatusText,
                        'uploaded_doc_id'  => $latestDoc?->id ?? null,
                        'upload_doc_date'  => $latestDoc?->created_at ? date('Y-m-d', strtotime($latestDoc?->created_at)) : null,
                    ];
                });
            }

            return response()->json([
                'success' => true,
                'data'    => [
                    'matter_info' => [
                        'client_matter_id' => (int) $clientMatterId,
                        'client_id'        => $clientId,
                        'current_stage'    => $matter->current_stage,
                        'status'           => $matter->matter_status,
                    ],
                    'allowed_checklists'       => $allowedChecklists,
                    'total_allowed_checklists' => $allowedChecklists->count(),
                    'stage_filter'             => $stageId ? (int) $stageId : null,
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Allowed Checklist API Error: ' . $e->getMessage(), [
                'user_id'               => $admin->id ?? null,
                'client_matter_id'      => $clientMatterId ?? null,
                'stage_id'              => $stageId ?? null,
                'allowed_checklist_id'  => $allowedChecklistId ?? null,
                'trace'                 => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch allowed checklist',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload Allowed Checklist Document - Single Upload
     * POST /api/workflow/upload-allowed-checklist
     *
     * Form-data params:
     *   - client_matter_id    (required): Client matter ID
     *   - allowed_checklist_id (required): ID from cp_doc_checklists
     *   - file                (required): Single file, max 10 MB
     */
    public function uploadAllowedChecklistDocument(Request $request)
    {
        try {
            $admin    = $request->user();
            $clientId = $admin->id;

            $validator = Validator::make($request->all(), [
                'client_matter_id'     => 'required|integer|min:1',
                'allowed_checklist_id' => 'required|integer|min:1',
                'file'                 => 'required|file|max:10240',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors'  => $validator->errors()
                ], 422);
            }

            $clientMatterId    = (int) $request->input('client_matter_id');
            $allowedChecklistId = (int) $request->input('allowed_checklist_id');
            $file              = $request->file('file');

            // Verify matter belongs to this client
            $matter = DB::table('client_matters')
                ->where('id', $clientMatterId)
                ->where('client_id', $clientId)
                ->first();

            if (!$matter) {
                return response()->json([
                    'success' => false,
                    'message' => 'Client matter not found'
                ], 404);
            }

            // Verify checklist item belongs to this matter and is allowed
            $checklistItem = DB::table('cp_doc_checklists')
                ->where('id', $allowedChecklistId)
                ->where('client_matter_id', $clientMatterId)
                ->where('client_id', $clientId)
                ->where('allow_client', 1)
                ->first();

            if (!$checklistItem) { 
                return response()->json([
                    'success' => false,
                    'message' => 'Allowed checklist item not found'
                ], 404);
            }

            // Validate file name characters
            $originalName = $file->getClientOriginalName();
            if (!preg_match('/^[a-zA-Z0-9_\-\.\s\$]+$/', $originalName)) {
                return response()->json([
                    'success' => false,
                    'message' => 'File name can only contain letters, numbers, dashes (-), underscores (_), spaces, dots (.), and dollar signs ($). Please rename the file and try again.'
                ], 422);
            }

            // Get client display info for file naming
            $adminInfo = DB::table('admins')
                ->select('client_id', 'first_name')
                ->where('id', $clientId)
                ->first();

            $clientUniqueId  = $adminInfo->client_id ?? $clientId;
            $clientFirstName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $adminInfo->first_name ?? 'client');
            $checklistName   = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $checklistItem->cp_checklist_name ?? 'doc');

            $extension   = $file->getClientOriginalExtension();
            $fileSize    = $file->getSize();
            $newFileName = $clientFirstName . '_' . $checklistName . '_' . time() . '.' . $extension;
            $filePath    = $clientUniqueId . '/workflow_checklist/' . $newFileName;

            // Upload to S3
            Storage::disk('s3')->put($filePath, file_get_contents($file));
            $fileUrl = $this->s3ObjectPublicUrl($filePath);

            // Insert new document record
            $documentId = DB::table('documents')->insertGetId([
                'cp_list_id'      => $allowedChecklistId,
                'user_id'         => $clientId,
                'client_id'       => $clientId,
                'client_matter_id'=> $clientMatterId,
                'file_name'       => $clientFirstName . '_' . $checklistName . '_' . time(),
                'filetype'        => $extension,
                'myfile'          => $fileUrl,
                'myfile_key'      => $newFileName,
                'file_size'       => $fileSize,
                'type'            => 'workflow_checklist',
                'doc_type'        => 'workflow_checklist',
                'checklist'       => $checklistItem->cp_checklist_name,
                'cp_doc_status'   => 0,
                'status'          => 'draft',
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);

            $this->notifyStaffAndCreateActionForChecklistUpload($clientId, $clientMatterId, [$checklistItem->cp_checklist_name ?? 'checklist']);

            return response()->json([
                'success' => true,
                'message' => 'Allowed checklist document uploaded successfully',
                'data'    => [
                    'document_id'          => $documentId,
                    'client_matter_id'     => $clientMatterId,
                    'allowed_checklist_id' => $allowedChecklistId,
                    'checklist_name'       => $checklistItem->cp_checklist_name,
                    'file_name'            => pathinfo($newFileName, PATHINFO_FILENAME),
                    'file_type'            => $extension,
                    'file_size'            => $fileSize,
                    'file_size_formatted'  => $this->formatFileSize($fileSize),
                    'file_url'             => $fileUrl,
                    'file_key'             => $newFileName,
                    's3_path'              => $filePath,
                    'uploaded_at'          => now()->toISOString(),
                ]
            ], 201);

        } catch (\Exception $e) {
            Log::error('Upload Allowed Checklist Document API Error: ' . $e->getMessage(), [
                'user_id'              => $admin->id ?? null,
                'client_matter_id'     => $clientMatterId ?? null,
                'allowed_checklist_id' => $allowedChecklistId ?? null,
                'trace'                => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to upload document',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload Allowed Checklist Documents - Bulk Upload
     * POST /api/workflow/upload-allowed-checklist-bulk-upload
     *
     * Form-data params:
     *   - client_matter_id      (required): Client matter ID
     *   - files[]               (required): Multiple files, max 20, each max 10 MB
     *   - allowed_checklist_ids (required): Comma-separated checklist IDs, one per file in same order
     *                                       e.g. "14,14,15" for 3 files
     */
    public function uploadAllowedChecklistDocumentBulk(Request $request)
    {
        try {
            $admin    = $request->user();
            $clientId = $admin->id;

            $validator = Validator::make($request->all(), [
                'client_matter_id'      => 'required|integer|min:1',
                'files'                 => 'required|array|min:1|max:20',
                'files.*'               => 'required|file|max:10240',
                'allowed_checklist_ids' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors'  => $validator->errors()
                ], 422);
            }

            $clientMatterId  = (int) $request->input('client_matter_id');
            $files           = $request->file('files');
            $checklistIdsRaw = $request->input('allowed_checklist_ids');

            // Parse comma-separated checklist IDs
            $checklistIds = array_map('intval', array_filter(array_map('trim', explode(',', $checklistIdsRaw))));

            if (count($files) !== count($checklistIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Number of files (' . count($files) . ') must match number of checklist IDs (' . count($checklistIds) . ')'
                ], 422);
            }

            // Verify matter belongs to this client
            $matter = DB::table('client_matters')
                ->where('id', $clientMatterId)
                ->where('client_id', $clientId)
                ->first();

            if (!$matter) {
                return response()->json([
                    'success' => false,
                    'message' => 'Client matter not found'
                ], 404);
            }

            // Get client display info for file naming
            $adminInfo = DB::table('admins')
                ->select('client_id', 'first_name')
                ->where('id', $clientId)
                ->first();

            $clientUniqueId  = $adminInfo->client_id ?? $clientId;
            $clientFirstName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $adminInfo->first_name ?? 'client');

            $uploadedDocuments = [];
            $errors            = [];

            foreach ($files as $index => $file) {
                $allowedChecklistId = $checklistIds[$index];

                // Validate file name
                $originalName = $file->getClientOriginalName();
                if (!preg_match('/^[a-zA-Z0-9_\-\.\s\$]+$/', $originalName)) {
                    $errors[] = [
                        'index'   => $index,
                        'file'    => $originalName,
                        'message' => 'Invalid file name characters. Only letters, numbers, dashes, underscores, spaces, dots, and dollar signs are allowed.'
                    ];
                    continue;
                }

                // Verify checklist item belongs to this matter and is allowed
                $checklistItem = DB::table('cp_doc_checklists')
                    ->where('id', $allowedChecklistId)
                    ->where('client_matter_id', $clientMatterId)
                    ->where('client_id', $clientId)
                    ->where('allow_client', 1)
                    ->first();

                if (!$checklistItem) {
                    $errors[] = [
                        'index'                => $index,
                        'allowed_checklist_id' => $allowedChecklistId,
                        'message'              => 'Allowed checklist item ID ' . $allowedChecklistId . ' not found or not accessible'
                    ];
                    continue;
                }

                $checklistName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $checklistItem->cp_checklist_name ?? 'doc');
                $extension     = $file->getClientOriginalExtension();
                $fileSize      = $file->getSize();
                $newFileName   = $clientFirstName . '_' . $checklistName . '_' . time() . '_' . $index . '.' . $extension;
                $filePath      = $clientUniqueId . '/workflow_checklist/' . $newFileName;

                // Upload to S3
                Storage::disk('s3')->put($filePath, file_get_contents($file));
                $fileUrl = $this->s3ObjectPublicUrl($filePath);

                // Insert document record
                $documentId = DB::table('documents')->insertGetId([
                    'cp_list_id'       => $allowedChecklistId,
                    'user_id'          => $clientId,
                    'client_id'        => $clientId,
                    'client_matter_id' => $clientMatterId,
                    'file_name'        => $clientFirstName . '_' . $checklistName . '_' . time() . '_' . $index,
                    'filetype'         => $extension,
                    'myfile'           => $fileUrl,
                    'myfile_key'       => $newFileName,
                    'file_size'        => $fileSize,
                    'type'             => 'workflow_checklist',
                    'doc_type'         => 'workflow_checklist',
                    'checklist'        => $checklistItem->cp_checklist_name,
                    'cp_doc_status'    => 0,
                    'status'           => 'draft',
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ]);

                $uploadedDocuments[] = [
                    'document_id'          => $documentId,
                    'client_matter_id'     => $clientMatterId,
                    'allowed_checklist_id' => $allowedChecklistId,
                    'checklist_name'       => $checklistItem->cp_checklist_name,
                    'file_name'            => pathinfo($newFileName, PATHINFO_FILENAME),
                    'file_type'            => $extension,
                    'file_size'            => $fileSize,
                    'file_size_formatted'  => $this->formatFileSize($fileSize),
                    'file_url'             => $fileUrl,
                    'file_key'             => $newFileName,
                    's3_path'              => $filePath,
                    'uploaded_at'          => now()->toISOString(),
                ];
            }

            $uploadedCount = count($uploadedDocuments);
            $errorCount    = count($errors);

            if ($uploadedCount > 0) {
                $checklistNamesForMessage = [];
                foreach ($uploadedDocuments as $doc) {
                    $checklistNamesForMessage[] = $doc['checklist_name'] ?? 'checklist';
                }
                $this->notifyStaffAndCreateActionForChecklistUpload($clientId, $clientMatterId, $checklistNamesForMessage);
            }

            if ($uploadedCount === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'No documents were uploaded',
                    'errors'  => $errors
                ], 422);
            }

            return response()->json([
                'success' => true,
                'message' => $uploadedCount . ' document(s) uploaded successfully' . ($errorCount > 0 ? ', ' . $errorCount . ' failed' : ''),
                'data'    => [
                    'documents'       => $uploadedDocuments,
                    'uploaded_count'  => $uploadedCount,
                    'failed_count'    => $errorCount,
                    'errors'          => $errors,
                ]
            ], 201);

        } catch (\Exception $e) {
            Log::error('Bulk Upload Allowed Checklist API Error: ' . $e->getMessage(), [
                'user_id'          => $admin->id ?? null,
                'client_matter_id' => $clientMatterId ?? null,
                'trace'            => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to upload documents',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Notify PERSON ASSISTING and super admin(s) for the client matter, update their notification badges,
     * and create a Client Portal action on the Action page with the given message.
     *
     * @param int $clientId Admin ID of the client who uploaded
     * @param int $clientMatterId Client matter ID
     * @param array $checklistNames List of checklist names (e.g. ['Academic Records'])
     */
    private function notifyStaffAndCreateActionForChecklistUpload(int $clientId, int $clientMatterId, array $checklistNames): void
    {
        $matter = DB::table('client_matters')->where('id', $clientMatterId)->first();
        if (!$matter || !isset($matter->client_id)) {
            return;
        }

        $clientRow = DB::table('admins')->where('id', $clientId)->select('first_name', 'last_name')->first();
        $clientName = $clientRow ? trim(($clientRow->first_name ?? '') . ' ' . ($clientRow->last_name ?? '')) : 'Client';
        $checklistList = implode(', ', array_unique(array_filter($checklistNames)));
        $checklistWord = count(array_unique(array_filter($checklistNames))) > 1 ? 'checklists' : 'checklist';
        $message = $clientName . ' have uploaded document in ' . $checklistList . ' ' . $checklistWord . ' from mobile app.';

        $notificationUrl = url('/clients/detail/' . base64_encode(convert_uuencode($matter->client_id)));

        $recipientIds = collect();
        $superAdminIds = Staff::where('role', 1)->where('status', 1)->pluck('id');
        $recipientIds = $recipientIds->merge($superAdminIds);
        if (!empty($matter->sel_person_assisting)) {
            $recipientIds->push($matter->sel_person_assisting);
        }
        $recipientIds = $recipientIds->unique()->filter()->values();

        foreach ($recipientIds as $receiverStaffId) {
            if (!$receiverStaffId || !Staff::where('id', $receiverStaffId)->exists()) {
                continue;
            }
            try {
                Notification::create([
                    'sender_id'       => $clientId,
                    'receiver_id'     => $receiverStaffId,
                    'module_id'       => $clientMatterId,
                    'url'             => $notificationUrl,
                    'notification_type' => 'checklist',
                    'message'         => $message,
                    'receiver_status' => 0,
                    'seen'            => 0,
                ]);
                $unreadCount = (int) DB::table('notifications')
                    ->where('receiver_id', $receiverStaffId)
                    ->where('receiver_status', 0)
                    ->count();
                broadcast(new NotificationCountUpdated($receiverStaffId, $unreadCount, $message, $notificationUrl));
            } catch (\Exception $e) {
                Log::warning('Checklist upload: failed to notify staff', [
                    'receiver_id' => $receiverStaffId,
                    'error'       => $e->getMessage(),
                ]);
            }
        }

        $clientMatterModel = ClientMatter::find($clientMatterId);
        if (!$clientMatterModel) {
            return;
        }
        $fallbackStaffId = $recipientIds->first();
        $fallbackStaffId = $fallbackStaffId ? (int) $fallbackStaffId : null;
        try {
            ClientPortalActionNoteService::createGroupedForMatter(
                (int) $clientMatterModel->client_id,
                $clientMatterId,
                $message,
                $clientId,
                $clientMatterModel,
                $fallbackStaffId
            );
        } catch (\Exception $e) {
            Log::warning('Checklist upload: failed to create Action page entry', [
                'client_matter_id' => $clientMatterId,
                'error'            => $e->getMessage(),
            ]);
        }
    }

    /**
     * Public URL for an object on the S3 disk (adapter implements url(); contract type does not).
     */
    private function s3ObjectPublicUrl(string $path): string
    {
        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk('s3');

        return $disk->url($path);
    }

    /**
     * Format bytes into a human-readable size string
     */
    private function formatFileSize(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' bytes';
    }
}
