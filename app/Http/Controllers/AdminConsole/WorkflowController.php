<?php
namespace App\Http\Controllers\AdminConsole;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

use App\Models\Workflow;
use App\Models\WorkflowStage;
use App\Models\ClientMatter;
use App\Models\Matter;

class WorkflowController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:admin');
    }

    /**
     * List workflows (Workflow model).
     */
    public function index(Request $request)
    {
        $query = Workflow::with(['matter', 'stages']);
        $lists = $query->orderBy('name')->paginate(config('constants.limit', 20));
        $matters = Matter::orderBy('title')->get(['id', 'title', 'nick_name']);

        return view('AdminConsole.features.workflow.workflows-index', compact('lists', 'matters'));
    }

    /**
     * Create new workflow form.
     */
    public function create(Request $request)
    {
        return redirect()->route('adminconsole.features.workflow.index');
    }

    /**
     * Store new workflow.
     */
    public function storeWorkflow(Request $request)
    {
        $wantsJson = $request->expectsJson() || $request->ajax();

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'matter_id' => 'nullable|exists:matters,id',
        ]);

        if ($validator->fails()) {
            if ($wantsJson) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first(),
                    'errors' => $validator->errors(),
                ], 422);
            }

            return redirect()->route('adminconsole.features.workflow.index')
                ->withErrors($validator)
                ->withInput();
        }

        $wf = null;
        DB::transaction(function () use ($request, &$wf) {
            $wf = new Workflow();
            $wf->name = $request->name;
            $wf->matter_id = $request->matter_id ?: null;
            $wf->status = 1;
            $wf->save();

            $generalWorkflow = Workflow::whereRaw('LOWER(name) = ?', ['general'])->first();
            $defaultStages = [];
            if ($generalWorkflow) {
                $defaultStages = WorkflowStage::where('workflow_id', $generalWorkflow->id)
                    ->orderByRaw('COALESCE(sort_order, id) ASC')
                    ->pluck('name')
                    ->toArray();
            }

            if (empty($defaultStages)) {
                $defaultStages = ['Application Received', 'Checklist', 'Ready to Close', 'File Closed'];
            }

            foreach ($defaultStages as $i => $stageName) {
                $stage = new WorkflowStage();
                $stage->name = $stageName;
                $stage->workflow_id = $wf->id;
                $stage->sort_order = $i + 1;
                $stage->save();
            }
        });

        if (!$wf || !$wf->id) {
            $message = 'Workflow could not be created. Please try again.';

            if ($wantsJson) {
                return response()->json(['success' => false, 'message' => $message], 500);
            }

            return redirect()->route('adminconsole.features.workflow.index')->with('error', $message);
        }

        $message = 'Workflow created. Default stages copied from General — use Manage Stages to amend.';

        if ($wantsJson) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'workflow' => $this->workflowToArray($wf->fresh(['matter', 'stages'])),
            ]);
        }

        return redirect()
            ->route('adminconsole.features.workflow.stages', base64_encode(convert_uuencode($wf->id)))
            ->with('success', $message);
    }

    /**
     * Edit workflow form.
     */
    public function editWorkflow($id)
    {
        $id = $this->decodeString($id);
        $workflow = Workflow::with(['matter', 'stages'])->find($id);

        if (!$workflow) {
            if (request()->expectsJson() || request()->ajax()) {
                return response()->json(['success' => false, 'message' => 'Workflow not found'], 404);
            }

            return redirect()->route('adminconsole.features.workflow.index')->with('error', 'Workflow not found');
        }

        if (request()->expectsJson() || request()->ajax()) {
            return response()->json([
                'success' => true,
                'workflow' => $this->workflowToArray($workflow),
            ]);
        }

        return redirect()->route('adminconsole.features.workflow.index');
    }

    /**
     * Update workflow.
     */
    public function updateWorkflow(Request $request, $id)
    {
        $wantsJson = $request->expectsJson() || $request->ajax();
        $id = $this->decodeString($id);
        $workflow = Workflow::find($id);

        if (!$workflow) {
            if ($wantsJson) {
                return response()->json(['success' => false, 'message' => 'Workflow not found'], 404);
            }

            return redirect()->route('adminconsole.features.workflow.index')->with('error', 'Workflow not found');
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'matter_id' => 'nullable|exists:matters,id',
        ]);

        if ($validator->fails()) {
            if ($wantsJson) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first(),
                    'errors' => $validator->errors(),
                ], 422);
            }

            return redirect()->route('adminconsole.features.workflow.index')
                ->withErrors($validator)
                ->withInput();
        }

        $workflow->name = $request->name;
        $workflow->matter_id = $request->matter_id ?: null;
        $workflow->save();

        $message = 'Workflow Updated Successfully';

        if ($wantsJson) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'workflow' => $this->workflowToArray($workflow->fresh(['matter', 'stages'])),
            ]);
        }

        return redirect()->route('adminconsole.features.workflow.index')->with('success', $message);
    }

    /**
     * List stages for a workflow.
     */
    public function stages($id)
    {
        $id = $this->decodeString($id);
        $workflow = Workflow::find($id);

        if (!$workflow) {
            return redirect()->route('adminconsole.features.workflow.index')->with('error', 'Workflow not found');
        }

        $lists = WorkflowStage::where('workflow_id', $workflow->id)
            ->orderByRaw('COALESCE(sort_order, id) ASC')
            ->paginate(config('constants.limit', 50));

        $stageIds = $lists->pluck('id')->toArray();
        $matterCounts = ClientMatter::where('workflow_id', $workflow->id)
            ->whereIn('workflow_stage_id', $stageIds)
            ->selectRaw('workflow_stage_id, COUNT(*) as cnt')
            ->groupBy('workflow_stage_id')
            ->pluck('cnt', 'workflow_stage_id');

        $workflowEncodedId = base64_encode(convert_uuencode($workflow->id));

        return view('AdminConsole.features.workflow.stages-index', compact(
            'workflow',
            'lists',
            'matterCounts',
            'workflowEncodedId'
        ));
    }

    /**
     * Create stage form (for a specific workflow).
     */
    public function createStage(Request $request, $workflowId)
    {
        $workflowId = $this->decodeString($workflowId);
        $workflow = Workflow::find($workflowId);

        if (!$workflow) {
            return redirect()->route('adminconsole.features.workflow.index')->with('error', 'Workflow not found');
        }

        return redirect()->route('adminconsole.features.workflow.stages', base64_encode(convert_uuencode($workflow->id)));
    }

    /**
     * Store new stage(s).
     */
    public function store(Request $request)
    {
        $wantsJson = $request->expectsJson() || $request->ajax();

        $validator = Validator::make($request->all(), [
            'stage_name' => 'required|array|min:1',
            'stage_name.*' => 'required|string|max:255',
            'after_stage_id' => 'nullable|integer|exists:workflow_stages,id',
            'workflow_id' => 'nullable|integer|exists:workflows,id',
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

        $workflowId = $request->workflow_id;
        if (!$workflowId) {
            $general = Workflow::firstOrCreate(
                ['name' => 'General'],
                ['description' => 'Default General Workflow', 'status' => 1]
            );
            $workflowId = $general->id;
        }

        $stages = array_values(array_filter($request->stage_name, function ($name) {
            return trim((string) $name) !== '';
        }));

        if (empty($stages)) {
            $message = 'Please enter at least one stage name.';

            if ($wantsJson) {
                return response()->json(['success' => false, 'message' => $message], 422);
            }

            return redirect()->back()->withInput()->with('error', $message);
        }

        $afterStageId = $request->input('after_stage_id');

        if ($afterStageId && !$workflowId) {
            $message = 'Cannot insert after a stage without a workflow context.';

            if ($wantsJson) {
                return response()->json(['success' => false, 'message' => $message], 422);
            }

            return redirect()->back()->withInput()->with('error', $message);
        }

        if ($afterStageId) {
            $afterStage = WorkflowStage::where('id', $afterStageId)->first();
            if (!$afterStage || (int) $afterStage->workflow_id !== (int) $workflowId) {
                $message = 'Invalid “insert after” stage for this workflow.';

                if ($wantsJson) {
                    return response()->json(['success' => false, 'message' => $message], 422);
                }

                return redirect()->back()->withInput()->with('error', $message);
            }
        }

        $createdStages = [];

        DB::transaction(function () use ($stages, $workflowId, $afterStageId, &$createdStages) {
            if ($afterStageId) {
                $afterStage = WorkflowStage::where('id', $afterStageId)->lockForUpdate()->first();
                $effectiveAfter = (int) ($afterStage->sort_order ?? $afterStage->id);
                $n = count($stages);
                $toShift = WorkflowStage::where('workflow_id', $workflowId)
                    ->where('id', '!=', $afterStage->id)
                    ->whereRaw('COALESCE(sort_order, id) > ?', [$effectiveAfter])
                    ->orderByRaw('COALESCE(sort_order, id) DESC')
                    ->lockForUpdate()
                    ->get();

                foreach ($toShift as $row) {
                    $curr = (int) ($row->sort_order ?? $row->id);
                    $row->sort_order = $curr + $n;
                    $row->save();
                }

                $pos = 0;
                foreach ($stages as $stageName) {
                    $o = new WorkflowStage();
                    $o->name = $stageName;
                    $o->workflow_id = $workflowId;
                    $o->sort_order = $effectiveAfter + 1 + $pos;
                    $o->save();
                    $createdStages[] = $o;
                    $pos++;
                }

                return;
            }

            $sortQuery = WorkflowStage::query();
            if ($workflowId) {
                $sortQuery->where('workflow_id', $workflowId);
            } else {
                $sortQuery->whereNull('workflow_id');
            }

            $maxSortOrder = (int) ($sortQuery->max('sort_order') ?? $sortQuery->max('id') ?? 0);

            foreach ($stages as $stageName) {
                $o = new WorkflowStage();
                $o->name = $stageName;
                $o->workflow_id = $workflowId;
                $o->sort_order = ++$maxSortOrder;
                $o->save();
                $createdStages[] = $o;
            }
        });

        $msg = $afterStageId
            ? 'Stage(s) inserted after the selected stage.'
            : 'Workflow Stages Added Successfully';

        if ($wantsJson) {
            return response()->json([
                'success' => true,
                'message' => $msg,
                'stages' => array_map(function (WorkflowStage $stage) {
                    return $this->stageToArray($stage, 0);
                }, $createdStages),
                'after_stage_id' => $afterStageId ? (int) $afterStageId : null,
            ]);
        }

        if ($workflowId) {
            return redirect()->route('adminconsole.features.workflow.stages', base64_encode(convert_uuencode($workflowId)))
                ->with('success', $msg);
        }

        return redirect()->route('adminconsole.features.workflow.index')->with('success', $msg);
    }

    /**
     * Edit stage form.
     */
    public function edit($id)
    {
        $id = $this->decodeString($id);
        $fetchedData = WorkflowStage::find($id);

        if (!$fetchedData) {
            if (request()->expectsJson() || request()->ajax()) {
                return response()->json(['success' => false, 'message' => 'Workflow Stage Not Found'], 404);
            }

            return redirect()->route('adminconsole.features.workflow.index')->with('error', 'Workflow Stage Not Found');
        }

        if (request()->expectsJson() || request()->ajax()) {
            $matterCount = ClientMatter::where('workflow_stage_id', $fetchedData->id)->count();

            return response()->json([
                'success' => true,
                'stage' => $this->stageToArray($fetchedData, $matterCount),
            ]);
        }

        return redirect()->route('adminconsole.features.workflow.index');
    }

    /**
     * Update stage.
     */
    public function update(Request $request, $id)
    {
        $wantsJson = $request->expectsJson() || $request->ajax();
        $id = $this->decodeString($id);
        $stage = WorkflowStage::find($id);

        if (!$stage) {
            if ($wantsJson) {
                return response()->json(['success' => false, 'message' => 'Workflow Stage Not Found'], 404);
            }

            return redirect()->route('adminconsole.features.workflow.index')->with('error', 'Workflow Stage Not Found');
        }

        $validator = Validator::make($request->all(), [
            'stage_name' => 'required|array|min:1',
            'stage_name.*' => 'required|string|max:255',
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

        if ($stage->isFrozen()) {
            $message = 'This workflow stage is protected and cannot be renamed.';

            if ($wantsJson) {
                return response()->json(['success' => false, 'message' => $message], 422);
            }

            $workflow = $stage->workflow;
            if ($workflow) {
                return redirect()->route('adminconsole.features.workflow.stages', base64_encode(convert_uuencode($workflow->id)))
                    ->with('error', $message);
            }

            return redirect()->route('adminconsole.features.workflow.index')->with('error', $message);
        }

        $stage->name = $request->stage_name[0];
        $stage->save();

        $message = 'Workflow Stage Updated Successfully';
        $matterCount = ClientMatter::where('workflow_stage_id', $stage->id)->count();

        if ($wantsJson) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'stage' => $this->stageToArray($stage->fresh(), $matterCount),
            ]);
        }

        $workflow = $stage->workflow;
        if ($workflow) {
            return redirect()->route('adminconsole.features.workflow.stages', base64_encode(convert_uuencode($workflow->id)))
                ->with('success', $message);
        }

        return redirect()->route('adminconsole.features.workflow.index')->with('success', $message);
    }

    private function workflowToArray(Workflow $workflow): array
    {
        $workflow->loadMissing(['matter', 'stages']);
        $encodedId = base64_encode(convert_uuencode($workflow->id));

        return [
            'id' => (int) $workflow->id,
            'encoded_id' => $encodedId,
            'name' => $workflow->name,
            'matter_id' => $workflow->matter_id ? (int) $workflow->matter_id : null,
            'matter_title' => $workflow->matter ? $workflow->matter->title : null,
            'stages_count' => $workflow->stages->count(),
            'stages_url' => route('adminconsole.features.workflow.stages', $encodedId),
        ];
    }

    private function stageToArray(WorkflowStage $stage, int $matterCount = 0): array
    {
        return [
            'id' => (int) $stage->id,
            'encoded_id' => base64_encode(convert_uuencode($stage->id)),
            'name' => $stage->name,
            'is_frozen' => $stage->isFrozen(),
            'matter_count' => $matterCount,
        ];
    }
}
