<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Models\ActivitiesLog;
use App\Models\ClientMatter;
use App\Models\WorkflowStage;
use App\Services\MatterTaskNoteService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use App\Http\Controllers\Concerns\EnsuresCrmRecordAccess;
use App\Models\Staff;

/**
 * Staff CRM controller for matters, workflow-adjacent utilities, and documents.
 */
class ClientMatterHubController extends Controller
{
    use EnsuresCrmRecordAccess;
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth:admin');
    }


	//Load Application Insert Update Data
		public function loadMatterUpsert(Request $request){
		$clientId = $request->client_id;
		$clientMatterId = $request->client_matter_id;

		$matter = DB::table('client_matters')
			->where('client_id', $clientId)
			->where('id', $clientMatterId)
			->first();

		if (!$matter) {
			return response()->json(['status' => false, 'message' => 'Matter not found'], 404);
		}
		
		return response()->json([
			'status' => true,
			'client_matter_id' => $clientMatterId,
			'message' => 'Ready'
		]);
	}

	public function completestage(Request $request){
		$matterId = $request->id ?? $request->client_matter_id;
		$clientMatter = ClientMatter::with('workflowStage')->find($matterId);
		if (!$clientMatter) {
			echo json_encode(['status' => false, 'message' => 'Matter not found']);
			return;
		}
		$this->ensureCrmRecordAccess((int) $clientMatter->client_id);
		$stageName = $clientMatter->workflowStage?->name ?? '';
		$clientMatter->matter_status = 0; // Discontinued/completed
		$clientMatter->closed_by = Auth::guard('admin')->id() ?? Auth::id();
		$clientMatter->discontinue_reason = 'Completed';
		$saved = $clientMatter->save();
		if ($saved) {
			$response = ['status' => true, 'stage' => $stageName, 'width' => 100, 'message' => 'Matter has been successfully completed.'];
		} else {
			$response = ['status' => false, 'message' => 'Please try again'];
		}
		echo json_encode($response);
	}

	public function updatestage(Request $request){
		$matterId = $request->id ?? $request->client_matter_id;
		$clientMatter = ClientMatter::with('workflowStage')->find($matterId);
		if (!$clientMatter || !$clientMatter->workflowStage) {
			echo json_encode(['status' => false, 'message' => 'Matter or stage not found']);
			return;
		}
		$this->ensureCrmRecordAccess((int) $clientMatter->client_id);
		$currentStage = $clientMatter->workflowStage;
		$workflowId = $currentStage->workflow_id ?? $clientMatter->workflow_id;
		$nextStage = WorkflowStage::where('id', '>', $currentStage->id)
			->when($workflowId, fn($q) => $q->where('workflow_id', $workflowId))
			->orderByRaw('COALESCE(sort_order, id) ASC')->first();
		if (!$nextStage) {
			echo json_encode(['status' => false, 'message' => 'No next stage']);
			return;
		}
		$stages = WorkflowStage::when($workflowId, fn($q) => $q->where('workflow_id', $workflowId))->orderByRaw('COALESCE(sort_order, id) ASC')->get();
		$nextIndex = $stages->search(fn($s) => $s->id == $nextStage->id) + 1;
		$width = $stages->count() > 0 ? round(($nextIndex / $stages->count()) * 100) : 0;
		$clientMatter->workflow_stage_id = $nextStage->id;
		$saved = $clientMatter->save();
		if ($saved) {
			$comments = 'moved the stage from <b>' . e($currentStage->name) . '</b> to <b>' . e($nextStage->name) . '</b>';
			$obj = new ActivitiesLog;
			$obj->client_id = $clientMatter->client_id;
			$obj->created_by = Auth::user()->id;
			$obj->subject = 'Stage: ' . $currentStage->name;
			$obj->description = $comments;
			$obj->activity_type = 'stage';
			$obj->use_for = 'matter';
			$obj->save();
			$lastStage = $stages->last();
			$displayback = $lastStage && $lastStage->name == $nextStage->name;
			$response = ['status' => true, 'stage' => $nextStage->name, 'width' => $width, 'displaycomplete' => $displayback, 'message' => 'Matter has been successfully moved to next stage.'];
		} else {
			$response = ['status' => false, 'message' => 'Please try again'];
		}
		echo json_encode($response);
	}

	public function updatebackstage(Request $request){
		$matterId = $request->id ?? $request->client_matter_id;
		$clientMatter = ClientMatter::with('workflowStage')->find($matterId);
		if (!$clientMatter || !$clientMatter->workflowStage) {
			echo json_encode(['status' => false, 'message' => 'Matter or stage not found']);
			return;
		}
		$this->ensureCrmRecordAccess((int) $clientMatter->client_id);
		$currentStage = $clientMatter->workflowStage;
		$workflowId = $currentStage->workflow_id ?? $clientMatter->workflow_id;
		$prevStage = WorkflowStage::where('id', '<', $currentStage->id)
			->when($workflowId, fn($q) => $q->where('workflow_id', $workflowId))
			->orderByRaw('COALESCE(sort_order, id) DESC')->first();
		if (!$prevStage) {
			echo json_encode(['status' => false, 'message' => '']);
			return;
		}
		$stages = WorkflowStage::when($workflowId, fn($q) => $q->where('workflow_id', $workflowId))->orderByRaw('COALESCE(sort_order, id) ASC')->get();
		$prevIndex = $stages->search(fn($s) => $s->id == $prevStage->id) + 1;
		$width = $stages->count() > 0 ? round(($prevIndex / $stages->count()) * 100) : 0;
		$clientMatter->workflow_stage_id = $prevStage->id;
		$saved = $clientMatter->save();
		if ($saved) {
			$comments = 'moved the stage from <b>' . $currentStage->name . '</b> to <b>' . $prevStage->name . '</b>';
			$obj = new ActivitiesLog;
			$obj->client_id = $clientMatter->client_id;
			$obj->created_by = Auth::user()->id;
			$obj->subject = 'Stage: ' . $currentStage->name;
			$obj->description = $comments;
			$obj->activity_type = 'stage';
			$obj->use_for = 'matter';
			$obj->save();
			$lastStage = $stages->last();
			$displayback = $lastStage && $lastStage->name == $prevStage->name;
			$response = ['status' => true, 'stage' => $prevStage->name, 'width' => $width, 'displaycomplete' => $displayback, 'message' => 'Matter has been successfully moved to previous stage.'];
		} else {
			$response = ['status' => false, 'message' => 'Please try again'];
		}
		echo json_encode($response);
	}

	/**
	 * Move Client Matter to Next Stage
	 * 
	 * Updates the workflow_stage_id for a client_matter to the next stage in sequence
	 * Also updates the applications table if it exists (for backward compatibility)
	 * 
	 * @param Request $request
	 * @return \Illuminate\Http\JsonResponse
	 */
	public function updateClientMatterNextStage(Request $request){
		try {
			$matterId = $request->input('matter_id');
			
			if (!$matterId) {
				return response()->json([
					'status' => false,
					'message' => 'Matter ID is required'
				], 422);
			}

			return DB::transaction(function () use ($request, $matterId) {
				// Get the client matter with pessimistic locking
				$clientMatter = ClientMatter::where('id', $matterId)->lockForUpdate()->first();

				if (!$clientMatter) {
					return response()->json([
						'status' => false,
						'message' => 'Client matter not found'
					], 404);
				}

				$this->ensureCrmRecordAccess((int) $clientMatter->client_id);

				// Get current stage
				$currentStageId = $clientMatter->workflow_stage_id;

				if (!$currentStageId) {
					return response()->json([
						'status' => false,
						'message' => 'Current stage not found'
					], 404);
				}

				// Get current stage details
				$currentStage = WorkflowStage::find($currentStageId);
			
			if (!$currentStage) {
				return response()->json([
					'status' => false,
					'message' => 'Current workflow stage not found'
				], 404);
			}

			// Get next stage (ordered by sort_order, then id) - scope to same workflow as client matter
			$currentOrder = $currentStage->sort_order ?? $currentStage->id;
			$stageQuery = WorkflowStage::whereRaw('COALESCE(sort_order, id) > ?', [$currentOrder]);
			if ($clientMatter->workflow_id) {
				$stageQuery->where('workflow_id', $clientMatter->workflow_id);
			} elseif ($currentStage->workflow_id) {
				$stageQuery->where('workflow_id', $currentStage->workflow_id);
			}
			$nextStage = $stageQuery->orderByRaw('COALESCE(sort_order, id) ASC')->first();

			if (!$nextStage) {
				return response()->json([
					'status' => false,
					'message' => 'Already at the last stage',
					'is_last_stage' => true
				], 400);
			}

			// When advancing to "Decision Received", require decision_outcome and decision_note
			$nextStageName = $nextStage->name ?? '';
			$isAdvancingToDecisionReceived = (strtolower(trim($nextStageName)) === 'decision received');
			if ($isAdvancingToDecisionReceived) {
				$decisionOutcome = $request->input('decision_outcome');
				$decisionNote = $request->input('decision_note', '');
				if (!$decisionOutcome || trim($decisionOutcome) === '') {
					return response()->json([
						'status' => false,
						'message' => 'Please select an outcome (Granted/Refused/Withdrawn) for Decision Received.'
					], 422);
				}
				if (!in_array(trim($decisionOutcome), ['Granted', 'Refused', 'Withdrawn'])) {
					return response()->json([
						'status' => false,
						'message' => 'Invalid outcome. Must be Granted, Refused, or Withdrawn.'
					], 422);
				}
				if (!$decisionNote || trim($decisionNote) === '') {
					return response()->json([
						'status' => false,
						'message' => 'Please enter a note for Decision Received.'
					], 422);
				}
			}

			// Update client_matters table
			$clientMatter->workflow_stage_id = $nextStage->id;
			if ($isAdvancingToDecisionReceived) {
				$clientMatter->decision_outcome = trim($request->input('decision_outcome'));
				$clientMatter->decision_note = trim($request->input('decision_note', ''));
			}
			$saved = $clientMatter->save();

			if ($saved) {
				// applications table removed - workflow tracked via client_matters.workflow_stage_id

				// Calculate progress percentage (by sort_order) - scope to same workflow
				$progressQuery = WorkflowStage::query();
				if ($clientMatter->workflow_id) {
					$progressQuery->where('workflow_id', $clientMatter->workflow_id);
				}
				$totalStages = (clone $progressQuery)->count();
				$nextOrder = $nextStage->sort_order ?? $nextStage->id;
				$currentStageIndex = (clone $progressQuery)->whereRaw('COALESCE(sort_order, id) <= ?', [$nextOrder])->count();
				$progressPercentage = $totalStages > 0 ? round(($currentStageIndex / $totalStages) * 100) : 0;

				// Check if this is the last stage
				$isLastStageQuery = WorkflowStage::whereRaw('COALESCE(sort_order, id) > ?', [$nextOrder]);
				if ($clientMatter->workflow_id) {
					$isLastStageQuery->where('workflow_id', $clientMatter->workflow_id);
				}
				$isLastStage = !$isLastStageQuery->exists();

				$matterNo = $clientMatter->client_unique_matter_no ?? 'ID: ' . $matterId;

				// Activity feed: logged for all CRM workflow stage changes.
					$comments = 'moved the stage from <b>' . $currentStage->name . '</b> to <b>' . $nextStage->name . '</b>';
					if ($isAdvancingToDecisionReceived) {
						$decisionOutcome = $request->input('decision_outcome');
						$decisionNote = $request->input('decision_note', '');
						$comments .= '<br>Outcome: <b>' . e($decisionOutcome) . '</b>';
						if (!empty(trim($decisionNote))) {
							$comments .= '<br>Note: ' . e($decisionNote);
						}
					}

					$activityLog = new ActivitiesLog;
					$activityLog->client_id = $clientMatter->client_id;
					$activityLog->created_by = Auth::user()->id;
					$activityLog->subject = $matterNo . ' Stage: ' . $currentStage->name;
					$activityLog->description = $comments;
					$activityLog->activity_type = 'stage';
					$activityLog->use_for = 'matter';
					$activityLog->task_status = 0;
					$activityLog->pin = 0;
					$activityLog->source = 'crm';
					$activityLog->save();

				// Notify client of stage change (for List Notifications API)
				$notificationMessage = 'Stage moved from ' . $currentStage->name . ' to ' . $nextStage->name . ' for matter ' . $matterNo;
				DB::table('notifications')->insert([
					'sender_id' => Auth::user()->id,
					'receiver_id' => $clientMatter->client_id,
					'module_id' => $matterId,
					'url' => '/documents',
					'notification_type' => 'stage_change',
					'message' => $notificationMessage,
					'created_at' => now(),
					'updated_at' => now(),
					'sender_status' => 1,
					'receiver_status' => 0,
					'seen' => 0
				]);

				return response()->json([
					'status' => true,
					'message' => 'Matter has been successfully moved to the next stage.',
					'stage_name' => $nextStage->name,
					'stage_id' => $nextStage->id,
					'progress_percentage' => $progressPercentage,
					'is_last_stage' => $isLastStage
				]);
			} else {
				return response()->json([
					'status' => false,
					'message' => 'Failed to update matter stage. Please try again.'
				], 500);
			}
			});

		} catch (\Exception $e) {
			Log::error('Error updating client matter next stage: ' . $e->getMessage(), [
				'matter_id' => $request->input('matter_id'),
				'trace' => $e->getTraceAsString()
			]);

			return response()->json([
				'status' => false,
				'message' => 'An error occurred while updating the stage. Please try again.'
			], 500);
		}
	}

	/**
	 * Move Client Matter to Previous Stage
	 *
	 * Updates the workflow_stage_id for a client_matter to the previous stage in sequence.
	 * Also updates the applications table if it exists (for backward compatibility).
	 *
	 * @param Request $request
	 * @return \Illuminate\Http\JsonResponse
	 */
	public function updateClientMatterPreviousStage(Request $request)
	{
		try {
			$matterId = $request->input('matter_id');

			if (!$matterId) {
				return response()->json([
					'status' => false,
					'message' => 'Matter ID is required'
				], 422);
			}

			return DB::transaction(function () use ($request, $matterId) {
				$clientMatter = ClientMatter::where('id', $matterId)->lockForUpdate()->first();

				if (!$clientMatter) {
					return response()->json([
						'status' => false,
						'message' => 'Client matter not found'
					], 404);
				}

				$this->ensureCrmRecordAccess((int) $clientMatter->client_id);

				$currentStageId = $clientMatter->workflow_stage_id;

				if (!$currentStageId) {
					return response()->json([
						'status' => false,
						'message' => 'Current stage not found'
					], 404);
				}

				$currentStage = WorkflowStage::find($currentStageId);

			if (!$currentStage) {
				return response()->json([
					'status' => false,
					'message' => 'Current workflow stage not found'
				], 404);
			}

			$currentOrder = $currentStage->sort_order ?? $currentStage->id;
			$prevQuery = WorkflowStage::whereRaw('COALESCE(sort_order, id) < ?', [$currentOrder]);
			if ($clientMatter->workflow_id) {
				$prevQuery->where('workflow_id', $clientMatter->workflow_id);
			} elseif ($currentStage->workflow_id) {
				$prevQuery->where('workflow_id', $currentStage->workflow_id);
			}
			$prevStage = $prevQuery->orderByRaw('COALESCE(sort_order, id) DESC')->first();

			if (!$prevStage) {
				return response()->json([
					'status' => false,
					'message' => 'Already at the first stage',
					'is_first_stage' => true
				], 400);
			}

			$clientMatter->workflow_stage_id = $prevStage->id;
			$saved = $clientMatter->save();

			if ($saved) {
				// applications table removed - workflow tracked via client_matters

				$progressQuery = WorkflowStage::query();
				if ($clientMatter->workflow_id) {
					$progressQuery->where('workflow_id', $clientMatter->workflow_id);
				} elseif ($prevStage->workflow_id) {
					$progressQuery->where('workflow_id', $prevStage->workflow_id);
				}
				$totalStages = (clone $progressQuery)->count();
				$prevOrder = $prevStage->sort_order ?? $prevStage->id;
				$currentStageIndex = (clone $progressQuery)->whereRaw('COALESCE(sort_order, id) <= ?', [$prevOrder])->count();
				$progressPercentage = $totalStages > 0 ? round(($currentStageIndex / $totalStages) * 100) : 0;

				$isFirstStageQuery = (clone $progressQuery)->whereRaw('COALESCE(sort_order, id) < ?', [$prevOrder]);
				$isFirstStage = !$isFirstStageQuery->exists();

				$matterNo = $clientMatter->client_unique_matter_no ?? 'ID: ' . $matterId;

				// Activity feed: logged for all CRM workflow stage changes.
					$comments = 'moved the stage from <b>' . $currentStage->name . '</b> to <b>' . $prevStage->name . '</b>';

					$activityLog = new ActivitiesLog;
					$activityLog->client_id = $clientMatter->client_id;
					$activityLog->created_by = Auth::user()->id;
					$activityLog->subject = $matterNo . ' Stage: ' . $currentStage->name;
					$activityLog->description = $comments;
					$activityLog->activity_type = 'stage';
					$activityLog->use_for = 'matter';
					$activityLog->task_status = 0;
					$activityLog->pin = 0;
					$activityLog->source = 'crm';
					$activityLog->save();

				$notificationMessage = 'Stage moved from ' . $currentStage->name . ' to ' . $prevStage->name . ' for matter ' . $matterNo;
				DB::table('notifications')->insert([
					'sender_id' => Auth::user()->id,
					'receiver_id' => $clientMatter->client_id,
					'module_id' => $matterId,
					'url' => '/documents',
					'notification_type' => 'stage_change',
					'message' => $notificationMessage,
					'created_at' => now(),
					'updated_at' => now(),
					'sender_status' => 1,
					'receiver_status' => 0,
					'seen' => 0
				]);

				return response()->json([
					'status' => true,
					'message' => 'Matter has been successfully moved to the previous stage.',
					'stage_name' => $prevStage->name,
					'stage_id' => $prevStage->id,
					'progress_percentage' => $progressPercentage,
					'is_first_stage' => $isFirstStage
				]);
			}

			return response()->json([
				'status' => false,
				'message' => 'Failed to update matter stage. Please try again.'
			], 500);
			});

		} catch (\Exception $e) {
			Log::error('Error updating client matter previous stage: ' . $e->getMessage(), [
				'matter_id' => $request->input('matter_id'),
				'trace' => $e->getTraceAsString()
			]);

			return response()->json([
				'status' => false,
				'message' => 'An error occurred while updating the stage. Please try again.'
			], 500);
		}
	}

	/**
	 * Client detail tabs where matter discontinue/reopen should notify the client (in-app notifications).
	 * Current Workflow tab and legacy application tab.
	 */
	private function shouldNotifyClientForMatterLifecycle(?string $currentTab): bool
	{
		$t = strtolower(trim((string) $currentTab));

		return in_array($t, ['application', 'workflow'], true);
	}


	private function createMatterActionNotes(ClientMatter $clientMatter, string $description): void
	{
		MatterTaskNoteService::createGroupedForMatter(
			(int) $clientMatter->client_id,
			(int) $clientMatter->id,
			$description,
			(int) Auth::user()->id,
			$clientMatter,
			(int) Auth::user()->id,
			(int) Auth::user()->id
		);
	}

	/**
	 * Change workflow for an existing client matter.
	 * Maps current stage by name to new workflow; falls back to first stage if no match.
	 *
	 * @param Request $request
	 * @return \Illuminate\Http\JsonResponse
	 */
	public function changeClientMatterWorkflow(Request $request)
	{
		try {
			$matterId = $request->input('matter_id');
			$workflowId = $request->input('workflow_id');

			if (!$matterId || !$workflowId) {
				return response()->json(['status' => false, 'message' => 'Matter ID and Workflow ID are required'], 422);
			}

			$clientMatter = ClientMatter::find($matterId);
			if (!$clientMatter) {
				return response()->json(['status' => false, 'message' => 'Client matter not found'], 404);
			}

			$workflow = \App\Models\Workflow::find($workflowId);
			if (!$workflow) {
				return response()->json(['status' => false, 'message' => 'Workflow not found'], 404);
			}

			$currentStageName = null;
			if ($clientMatter->workflow_stage_id) {
				$currentStage = WorkflowStage::find($clientMatter->workflow_stage_id);
				$currentStageName = $currentStage ? trim($currentStage->name) : null;
			}

			$newStageId = null;
			if ($currentStageName) {
				$matched = WorkflowStage::where('workflow_id', $workflowId)
					->whereRaw('LOWER(TRIM(name)) = ?', [strtolower($currentStageName)])
					->first();
				$newStageId = $matched ? $matched->id : null;
			}
			if (!$newStageId) {
				$firstStage = WorkflowStage::where('workflow_id', $workflowId)
					->orderByRaw('COALESCE(sort_order, id) ASC')
					->first();
				$newStageId = $firstStage ? $firstStage->id : null;
			}

			if (!$newStageId) {
				return response()->json(['status' => false, 'message' => 'Selected workflow has no stages. Add stages first.'], 400);
			}

			$clientMatter->workflow_id = $workflowId;
			$clientMatter->workflow_stage_id = $newStageId;
			$clientMatter->save();

			$matterNo = $clientMatter->client_unique_matter_no ?? 'ID:' . $matterId;

			// Activity feed: workflow change from CRM.
				$activityLog = new ActivitiesLog;
				$activityLog->client_id = $clientMatter->client_id;
				$activityLog->created_by = Auth::user()->id;
				$activityLog->subject = $matterNo . ' Workflow changed to ' . $workflow->name;
				$activityLog->description = 'Workflow changed to <b>' . e($workflow->name) . '</b>. Stage mapped accordingly.';
				$activityLog->activity_type = 'stage';
				$activityLog->use_for = 'matter';
				$activityLog->task_status = 0;
				$activityLog->pin = 0;
				$activityLog->source = 'crm';
				$activityLog->save();

			return response()->json([
				'status' => true,
				'message' => 'Workflow changed successfully.',
				'workflow_id' => $workflowId,
				'stage_id' => $newStageId,
			]);
		} catch (\Exception $e) {
			Log::error('Error changing client matter workflow: ' . $e->getMessage(), [
				'matter_id' => $request->input('matter_id'),
				'trace' => $e->getTraceAsString()
			]);
			return response()->json([
				'status' => false,
				'message' => 'An error occurred while changing the workflow. Please try again.'
			], 500);
		}
	}

	/**
	 * Discontinue a client matter (set matter_status = 0)
	 * Requires discontinue_reason from dropdown. Logs activity with reason.
	 *
	 * @param Request $request
	 * @return \Illuminate\Http\JsonResponse
	 */
	public function discontinueClientMatter(Request $request)
	{
		try {
			$user = Auth::guard('admin')->user();
			$canDiscontinue = $user instanceof \App\Models\Staff && $user->canCloseDiscontinueMatter();
			if (!$canDiscontinue) {
				return response()->json(['status' => false, 'message' => 'You do not have permission to close this matter.'], 403);
			}

			$matterId = $request->input('matter_id');
			$reason = $request->input('discontinue_reason');
			$notes = $request->input('discontinue_notes', '');
			$isComplete = trim((string) $reason) === \App\Support\MatterCompletionChecklist::REASON_COMPLETE;
			$completionChecklist = null;

			if (!$matterId) {
				return response()->json(['status' => false, 'message' => 'Matter ID is required'], 422);
			}

			if (!$reason || trim($reason) === '') {
				return response()->json(['status' => false, 'message' => 'Please select a reason for discontinuing.'], 422);
			}

			if ($isComplete) {
				$rawChecklist = $request->input('completion_checklist');
				if (is_string($rawChecklist) && $rawChecklist !== '') {
					$decoded = json_decode($rawChecklist, true);
					$rawChecklist = json_last_error() === JSON_ERROR_NONE ? $decoded : [];
				}
				$completionChecklist = \App\Support\MatterCompletionChecklist::normalizeInput($rawChecklist);
				if (! \App\Support\MatterCompletionChecklist::allChecked($completionChecklist)) {
					return response()->json([
						'status' => false,
						'message' => 'All completion checklist items must be checked before completing the matter.',
					], 422);
				}
			}

			$clientMatter = ClientMatter::find($matterId);

			if (!$clientMatter) {
				return response()->json(['status' => false, 'message' => 'Client matter not found.'], 404);
			}

			$this->ensureCrmRecordAccess((int) $clientMatter->client_id);

			$clientMatter->matter_status = 0;
			$clientMatter->closed_by = Auth::guard('admin')->id() ?? Auth::id();
			$clientMatter->discontinue_reason = $reason;
			$clientMatter->discontinue_notes = $notes;
			if ($isComplete && Schema::hasColumn('client_matters', 'matter_completion_checklist')) {
				$clientMatter->matter_completion_checklist = $completionChecklist;
			}
			$saved = $clientMatter->save();

			if ($saved) {
				// Email conversations remain associated with the matter history for audit / record keeping.

				// applications table removed

				$description = $isComplete
					? 'Completed matter.'
					: 'Discontinued matter. Reason: <b>' . e($reason) . '</b>';
				if ($isComplete) {
					$checklistHtml = \App\Support\MatterCompletionChecklist::toHtmlSummary($completionChecklist ?? []);
					if ($checklistHtml !== '') {
						$description .= '<br>Checklist:<br>' . $checklistHtml;
					}
				}
				if (!empty(trim($notes))) {
					$description .= '<br>Notes: ' . e($notes);
				}

				// Activity feed: matter discontinued from CRM.
					$activityLog = new ActivitiesLog;
					$activityLog->client_id = $clientMatter->client_id;
					$activityLog->created_by = Auth::user()->id;
					$activityLog->subject = $isComplete ? 'Matter Completed' : 'Matter Discontinued';
					$activityLog->description = $description;
					$activityLog->activity_type = 'stage';
					$activityLog->use_for = 'matter';
					$activityLog->task_status = 0;
					$activityLog->pin = 0;
					$activityLog->source = 'crm';
					$activityLog->save();

				// Notify client when discontinue is from matter-related tabs (workflow, application)
				$currentTab = $request->input('current_tab', 'personaldetails');
				if ($this->shouldNotifyClientForMatterLifecycle($currentTab)) {
					$matterNo = $clientMatter->client_unique_matter_no ?? 'ID: ' . $matterId;
					$notificationMessage = $isComplete
						? 'Your matter ' . $matterNo . ' has been completed.'
						: 'Your matter ' . $matterNo . ' has been discontinued. Reason: ' . e($reason);
					DB::table('notifications')->insert([
						'sender_id' => Auth::user()->id,
						'receiver_id' => $clientMatter->client_id,
						'module_id' => $matterId,
						'url' => '/documents',
						'notification_type' => 'matter_discontinued',
						'message' => $notificationMessage,
						'created_at' => now(),
						'updated_at' => now(),
						'sender_status' => 1,
						'receiver_status' => 0,
						'seen' => 0
					]);
				}

				// Build redirect URL: go to another active matter, or revert to lead view (no matter)
				$encodeId = base64_encode(convert_uuencode($clientMatter->client_id));
				$otherMatter = ClientMatter::where('client_id', $clientMatter->client_id)
					->where('id', '!=', $matterId)
					->where('matter_status', 1)
					->orderBy('id', 'desc')
					->first();
				$redirectUrl = '/clients/detail/' . $encodeId;
				if ($otherMatter) {
					$redirectUrl .= '/' . $otherMatter->client_unique_matter_no . '/' . $currentTab;
				} else {
					$redirectUrl .= '/' . $currentTab;
				}

				return response()->json([
					'status' => true,
					'message' => $isComplete
						? 'Matter has been successfully completed.'
						: 'Matter has been successfully discontinued.',
					'redirect_url' => $redirectUrl
				]);
			}

			return response()->json(['status' => false, 'message' => 'Failed to discontinue matter.'], 500);

		} catch (\Exception $e) {
			Log::error('Error discontinuing client matter: ' . $e->getMessage(), [
				'matter_id' => $request->input('matter_id'),
				'trace' => $e->getTraceAsString()
			]);
			return response()->json([
				'status' => false,
				'message' => 'An error occurred while discontinuing the matter.'
			], 500);
		}
	}

	/**
	 * Request to reopen a discontinued client matter (non-admins).
	 *
	 * @param Request $request
	 * @return \Illuminate\Http\JsonResponse
	 */
	public function requestReopenMatter(Request $request)
	{
		try {
			$matterId = $request->input('matter_id');

			if (!$matterId) {
				return response()->json(['status' => false, 'message' => 'Matter ID is required'], 422);
			}

			$clientMatter = ClientMatter::find($matterId);

			if (!$clientMatter) {
				return response()->json(['status' => false, 'message' => 'Client matter not found.'], 404);
			}

			$this->ensureCrmRecordAccess((int) $clientMatter->client_id);

			if ($clientMatter->matter_status == 1) {
				return response()->json(['status' => false, 'message' => 'Matter is already open.'], 400);
			}

			$clientMatter->reopen_requested_by = Auth::guard('admin')->id() ?? Auth::id();
			$saved = $clientMatter->save();

			if ($saved) {
				// Send notification to admins
				$admins = \App\Models\Admin::where('role', 1)->whereNull('is_deleted')->get();
				$requesterName = Auth::user() ? Auth::user()->first_name . ' ' . Auth::user()->last_name : 'A team member';
				$matterObj = $clientMatter->sel_matter_id ? \App\Models\Matter::find($clientMatter->sel_matter_id) : null;
				$matterTitle = $matterObj ? $matterObj->title : 'Matter';
				$matterNoSuffix = !empty($clientMatter->client_unique_matter_no) ? '/' . $clientMatter->client_unique_matter_no : '';
				$matterNoLabel = !empty($clientMatter->client_unique_matter_no) ? ' (' . $clientMatter->client_unique_matter_no . ')' : '';
				$url = '/clients/detail/' . base64_encode(convert_uuencode($clientMatter->client_id)) . $matterNoSuffix;

				foreach ($admins as $admin) {
					\App\Models\Notification::create([
						'sender_id' => Auth::guard('admin')->id() ?? Auth::id(),
						'receiver_id' => $admin->id,
						'module_id' => $clientMatter->id,
						'url' => $url,
						'notification_type' => 'Matter Reopen Request',
						'message' => $requesterName . ' has requested to reopen ' . $matterTitle . $matterNoLabel . '.',
						'receiver_status' => 0,
						'sender_status' => 0,
						'seen' => 0
					]);
				}

				return response()->json([
					'status' => true,
					'message' => 'Reopen request has been sent to admins.'
				]);
			}

			return response()->json(['status' => false, 'message' => 'Failed to send reopen request.'], 500);

		} catch (\Exception $e) {
			if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface || $e instanceof \Illuminate\Auth\Access\AuthorizationException) {
				throw $e;
			}
			Log::error('Error requesting to reopen client matter: ' . $e->getMessage(), [
				'matter_id' => $request->input('matter_id'),
				'trace' => $e->getTraceAsString()
			]);
			return response()->json([
				'status' => false,
				'message' => 'An error occurred while requesting to reopen the matter.'
			], 500);
		}
	}

	/**
	 * Reopen a discontinued client matter (set matter_status = 1).
	 *
	 * @param Request $request
	 * @return \Illuminate\Http\JsonResponse
	 */
	public function reopenClientMatter(Request $request)
	{
		try {
			$user = Auth::guard('admin')->user();
			$canReopen = $user instanceof \App\Models\Staff && ($user->hasEffectiveSuperAdminPrivileges() || $user->hasCrmModule('45'));
			if (!$canReopen) {
				return response()->json(['status' => false, 'message' => 'You do not have permission to reopen this matter.'], 403);
			}

			$matterId = $request->input('matter_id');

			if (!$matterId) {
				return response()->json(['status' => false, 'message' => 'Matter ID is required'], 422);
			}

			$clientMatter = ClientMatter::find($matterId);

			if (!$clientMatter) {
				return response()->json(['status' => false, 'message' => 'Client matter not found.'], 404);
			}

			$this->ensureCrmRecordAccess((int) $clientMatter->client_id);

			$requesterId = $clientMatter->reopen_requested_by;

			$clientMatter->matter_status = 1;
			$clientMatter->closed_by = null;
			$clientMatter->discontinue_reason = null;
			$clientMatter->discontinue_notes = null;
			if (Schema::hasColumn('client_matters', 'matter_completion_checklist')) {
				$clientMatter->matter_completion_checklist = null;
			}
			$clientMatter->reopen_requested_by = null;
			$saved = $clientMatter->save();

			if ($saved) {
				// Send notification back to requester if applicable
				if ($requesterId && $requesterId != (Auth::guard('admin')->id() ?? Auth::id())) {
					$matterObj = $clientMatter->sel_matter_id ? \App\Models\Matter::find($clientMatter->sel_matter_id) : null;
					$matterTitle = $matterObj ? $matterObj->title : 'Matter';
					$matterNoSuffix = !empty($clientMatter->client_unique_matter_no) ? '/' . $clientMatter->client_unique_matter_no : '';
					$matterNoLabel = !empty($clientMatter->client_unique_matter_no) ? ' (' . $clientMatter->client_unique_matter_no . ')' : '';
					$url = '/clients/detail/' . base64_encode(convert_uuencode($clientMatter->client_id)) . $matterNoSuffix;
					\App\Models\Notification::create([
						'sender_id' => Auth::guard('admin')->id() ?? Auth::id(),
						'receiver_id' => $requesterId,
						'module_id' => $clientMatter->id,
						'url' => $url,
						'notification_type' => 'Matter Reopened',
						'message' => 'Your request to reopen ' . $matterTitle . $matterNoLabel . ' has been approved.',
						'receiver_status' => 0,
						'sender_status' => 0,
						'seen' => 0
					]);
				}
				// applications table removed

				// Activity feed: matter reopened from CRM.
				$activityLog = new ActivitiesLog;
				$activityLog->client_id = $clientMatter->client_id;
				$activityLog->created_by = Auth::user()->id;
				$activityLog->subject = 'Matter Reopened';
				$activityLog->description = 'Matter was reopened and set back to active.';
				$activityLog->activity_type = 'stage';
				$activityLog->use_for = 'matter';
				$activityLog->task_status = 0;
				$activityLog->pin = 0;
				$activityLog->source = 'crm';
				$activityLog->save();

				// Notify client when reopen is from matter-related tabs or matter list
				$currentTab = $request->input('current_tab', '');
				$source = $request->input('source', '');
				$shouldNotify = false;

				if ($this->shouldNotifyClientForMatterLifecycle($currentTab)) {
					$shouldNotify = true;
				} elseif ($source === 'matter_list') {
					// Reopen from Matter List - always notify
					$shouldNotify = true;
				}

				if ($shouldNotify) {
					$matterNo = $clientMatter->client_unique_matter_no ?? 'ID: ' . $matterId;
					$notificationMessage = 'Your matter ' . $matterNo . ' has been reopened and is now active again.';
					DB::table('notifications')->insert([
						'sender_id' => Auth::user()->id,
						'receiver_id' => $clientMatter->client_id,
						'module_id' => $matterId,
						'url' => '/documents',
						'notification_type' => 'matter_reopened',
						'message' => $notificationMessage,
						'created_at' => now(),
						'updated_at' => now(),
						'sender_status' => 1,
						'receiver_status' => 0,
						'seen' => 0
					]);
				}

				return response()->json([
					'status' => true,
					'message' => 'Matter has been successfully reopened.',
					'redirect_url' => route('clients.clientsmatterslist')
				]);
			}

			return response()->json(['status' => false, 'message' => 'Failed to reopen matter.'], 500);

		} catch (\Exception $e) {
			Log::error('Error reopening client matter: ' . $e->getMessage(), [
				'matter_id' => $request->input('matter_id'),
				'trace' => $e->getTraceAsString()
			]);
			return response()->json([
				'status' => false,
				'message' => 'An error occurred while reopening the matter.'
			], 500);
		}
	}

	/**
	 * Permanently delete a closed client matter. Only allowed if matter was created more than 1 year ago.
	 *
	 * @param Request $request
	 * @return \Illuminate\Http\JsonResponse
	 */
	public function deleteClientMatter(Request $request)
	{
		try {
			$matterId = $request->input('matter_id');

			if (!$matterId) {
				return response()->json(['status' => false, 'message' => 'Matter ID is required'], 422);
			}

			$clientMatter = ClientMatter::find($matterId);

			if (!$clientMatter) {
				return response()->json(['status' => false, 'message' => 'Client matter not found.'], 404);
			}

			$this->ensureCrmRecordAccess((int) $clientMatter->client_id);

			$actor = Auth::user();
			if (! ($actor instanceof Staff && ($actor->hasEffectiveSuperAdminPrivileges() || $actor->canCloseDiscontinueMatter()))) {
				return response()->json(['status' => false, 'message' => 'Unauthorized to delete matter.'], 403);
			}

			if ((int) $clientMatter->matter_status !== 0) {
				return response()->json(['status' => false, 'message' => 'Only discontinued or closed matters can be permanently deleted.'], 422);
			}

			$oneYearAgo = now()->subYear();
			$createdAt = $clientMatter->created_at ? \Carbon\Carbon::parse($clientMatter->created_at) : null;

			if (!$createdAt || $createdAt->gt($oneYearAgo)) {
				return response()->json([
					'status' => false,
					'message' => 'Matter can only be deleted one year after creation. Matter created on ' . ($createdAt ? $createdAt->format('d/m/Y') : 'N/A') . '.'
				], 422);
			}

			$clientId = $clientMatter->client_id;
			$clientMatter->delete();

			// Activity feed: permanent matter delete from CRM.
			$activityLog = new ActivitiesLog;
			$activityLog->client_id = $clientId;
			$activityLog->created_by = Auth::user()->id;
			$activityLog->subject = 'Matter Deleted';
			$activityLog->description = 'Matter #' . $matterId . ' was permanently deleted from closed matters.';
			$activityLog->activity_type = 'stage';
			$activityLog->task_status = 0;
			$activityLog->pin = 0;
			$activityLog->source = 'crm';
			$activityLog->save();

			return response()->json([
				'status' => true,
				'message' => 'Matter has been permanently deleted.',
				'matter_id' => (int) $matterId
			]);

		} catch (\Exception $e) {
			Log::error('Error deleting client matter: ' . $e->getMessage(), [
				'matter_id' => $request->input('matter_id'),
				'trace' => $e->getTraceAsString()
			]);
			return response()->json([
				'status' => false,
				'message' => 'An error occurred while deleting the matter.'
			], 500);
		}
	}

	/**
	 * Update matter deadline. Accepts matter_id, set_deadline (bool), and deadline (date when set).
	 *
	 * @param Request $request
	 * @return \Illuminate\Http\JsonResponse
	 */
	public function updateClientMatterDeadline(Request $request)
	{
		try {
			$matterId = $request->input('matter_id');
			$setDeadline = filter_var($request->input('set_deadline'), FILTER_VALIDATE_BOOLEAN);
			$deadline = $request->input('deadline');

			if (!$matterId) {
				return response()->json(['status' => false, 'message' => 'Matter ID is required'], 422);
			}

			$clientMatter = ClientMatter::find($matterId);

			if (!$clientMatter) {
				return response()->json(['status' => false, 'message' => 'Client matter not found.'], 404);
			}

			if ($setDeadline) {
				$request->validate(['deadline' => 'required|date']);
				$clientMatter->deadline = $deadline;
			} else {
				$clientMatter->deadline = null;
			}

			$clientMatter->save();

			return response()->json([
				'status' => true,
				'message' => $setDeadline ? 'Deadline has been set.' : 'Deadline has been cleared.',
				'deadline' => $clientMatter->deadline ? $clientMatter->deadline->format('Y-m-d') : null,
			]);

		} catch (\Illuminate\Validation\ValidationException $e) {
			return response()->json([
				'status' => false,
				'message' => 'Please select a valid date.',
				'errors' => $e->errors(),
			], 422);
		} catch (\Exception $e) {
			Log::error('Error updating matter deadline: ' . $e->getMessage(), [
				'matter_id' => $request->input('matter_id'),
				'trace' => $e->getTraceAsString()
			]);
			return response()->json([
				'status' => false,
				'message' => 'An error occurred while updating the deadline.'
			], 500);
		}
	}

	// LEGACY METHOD - Still used by some JavaScript but outputs HTML directly (old pattern)
	// TODO: Refactor to return JSON and handle rendering in frontend
	public function getMatterLogs(Request $request){
		$id = $request->id ?? $request->client_matter_id;
		$clientMatter = ClientMatter::with('workflowStage')->find($id);

		if (!$clientMatter || !$clientMatter->workflowStage) {
			return response()->json(['error' => 'Matter not found'], 404);
		}

		$this->ensureCrmRecordAccess((int) $clientMatter->client_id);

		$workflowId = $clientMatter->workflowStage->workflow_id ?? $clientMatter->workflow_id;
		$currentStage = $clientMatter->workflowStage;
		$stagesquery = \App\Models\WorkflowStage::when($workflowId, fn($q) => $q->where('workflow_id', $workflowId))->orderByRaw('COALESCE(sort_order, id) ASC')->get();
		foreach($stagesquery as $stages){
			$stage1 = '';

			$workflowstagess = \App\Models\WorkflowStage::where('name', $currentStage->name)->when($workflowId, fn($q) => $q->where('workflow_id', $workflowId))->first();

			$prevdata = $workflowstagess ? \App\Models\WorkflowStage::where('id', '<', $workflowstagess->id)->when($workflowId, fn($q) => $q->where('workflow_id', $workflowId))->orderByRaw('COALESCE(sort_order, id) DESC')->get() : collect();
			$stagearray = array();
			foreach($prevdata as $pre){
				$stagearray[] = $pre->id;
			}

			if(in_array($stages->id, $stagearray)){
				$stage1 = 'app_green';
			}
			if($clientMatter->matter_status == 0){
				$stage1 = 'app_green';
			}
			$stagname = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $stages->name)));
			?>

			<div class="accordion cus_accrodian">
				<div class="accordion-header collapsed <?php echo $stage1; ?> <?php if($currentStage->name == $stages->name && $clientMatter->matter_status == 1){ echo  'app_blue'; }  ?>" role="button" data-toggle="collapse" data-target="#<?php echo $stagname; ?>_accor" aria-expanded="false">
					<h4><?php echo $stages->name; ?></h4>
					<div class="accord_hover">
						<a title="Add Note" class="openappnote" data-app-type="<?php echo $stages->name; ?>" data-id="<?php echo $clientMatter->id; ?>" href="javascript:;"><i class="fa-solid fa-file-lines"></i></a>
						<!-- opendocnote REMOVED - workflow checklist upload flow dead (no modal, no handler) -->
						<a data-app-type="<?php echo $stages->name; ?>" title="Email" data-id="<?php echo $clientMatter->id; ?>" data-email="" data-name="" class="openclientemail" title="Compose Mail" href="javascript:;"><i class="fa-solid fa-envelope"></i></a>
					</div>
				</div>
				<?php
				$applicationlists = \App\Models\ActivitiesLog::where('client_id', $clientMatter->client_id)
					->where('use_for', 'matter')
					->where('subject', 'like', '%Stage: ' . $stages->name . '%')
					->orderby('created_at', 'DESC')->get();
				?>
				<div class="accordion-body collapse" id="<?php echo $stagname; ?>_accor" data-parent="#accordion" style="">
					<div class="activity_list">
					<?php foreach($applicationlists as $applicationlist){
						$staff = \App\Models\Staff::where('id',$applicationlist->created_by)->first();
					?>
						<div class="activity_col">
							<div class="activity_txt_time">
								<span class="span_txt"><b><?php echo e($staff ? $staff->first_name : 'System'); ?></b> <?php echo e($applicationlist->description); ?></span>
								<span class="span_time"><?php echo date('d D, M Y h:i A', strtotime($applicationlist->created_at)); ?></span>
							</div>
							<?php if($applicationlist->subject != ''){ ?>
							<div class="app_description">
								<div class="app_card">
									<div class="app_title"><?php echo e($applicationlist->subject); ?></div>
								</div>
								<?php if($applicationlist->description != ''){ ?>
								<div class="log_desc">
									<?php echo e($applicationlist->description); ?>
								</div>
								<?php } ?>
							</div>
							<?php } ?>
						</div>
					<?php } ?>
					</div>
				</div>
			</div>
		<?php } ?>
		<?php
	}

	public function addNote(Request $request){
		$noteid = $request->noteid;
		$type = $request->type;
		$clientMatter = ClientMatter::find($noteid);
		if (!$clientMatter) {
			echo json_encode(['status' => false, 'message' => 'Matter not found']);
			return;
		}
		$this->ensureCrmRecordAccess((int) $clientMatter->client_id);

		$obj = new ActivitiesLog;
		$obj->client_id = $clientMatter->client_id;
		$obj->created_by = Auth::user()->id;
		$obj->subject = e($request->title ?? '');
		$obj->description = e($request->description ?? '');
		$obj->activity_type = 'note';
		$obj->use_for = 'matter';
		$saved = $obj->save();
		if($saved){
			$response['status'] 	= 	true;
			$response['message']	=	'Note successfully added';
		}else{
			$response['status'] 	= 	false;
			$response['message']	=	'Please try again';
		}
		echo json_encode($response);
	}

	public function getMatterNotes(Request $request){
		$noteid = $request->id;
		$clientMatter = ClientMatter::find($noteid);
		if (!$clientMatter) {
			echo '';
			return;
		}
		$this->ensureCrmRecordAccess((int) $clientMatter->client_id);

		$lists = ActivitiesLog::where('activity_type','note')
			->where('use_for','matter')
			->where('client_id', $clientMatter->client_id)
			->orderby('created_at', 'DESC')->get();

		ob_start();
			?>
			<div class="note_term_list">
				<?php
				foreach($lists as $list){
					$staff = \App\Models\Staff::where('id', $list->created_by)->first();
				?>
					<div class="note_col" id="note_id_<?php echo $list->id; ?>">
						<div class="note_content">
						<h4><a class="viewmatternote" data-id="<?php echo $list->id; ?>" href="javascript:;"><?php echo e(@$list->subject == "" ? config('constants.empty') : Str::limit(@$list->subject, 19, '...')); ?></a></h4>
						<p><?php echo e(@$list->description == "" ? config('constants.empty') : Str::limit(@$list->description, 15, '...')); ?></p>
						</div>
						<div class="extra_content">
							<div class="left">
								<div class="author">
									<a href="#"><?php echo e($staff ? substr($staff->first_name, 0, 1) : '?'); ?></a>
								</div>
								<div class="note_modify">
									<small>Last Modified <span><?php echo date('Y-m-d', strtotime($list->updated_at)); ?></span></small>
								</div>
							</div>
							<div class="right">

							</div>
						</div>
					</div>
				<?php } ?>
				</div>
				<div class="clearfix"></div>
			<?php
			echo ob_get_clean();

	}

	public function sendMatterMail(Request $request){
		$requestData = $request->all();
		$user_id = @Auth::user()->id;
		$subject = $requestData['subject'];
		$message = $requestData['message'];
		$to = $requestData['to'];

	$client = \App\Models\Admin::where('email', $requestData['to'])->first();
		if (!$client) {
			return response()->json(['status' => false, 'message' => 'Client not found'], 404);
		}
		$subject = str_replace('{Client First Name}', $client->first_name, $subject);
		$message = str_replace('{Client First Name}', $client->first_name, $message);
		$message = str_replace('{Client Assignee Name}', $client->first_name, $message);
		$message = str_replace('{Company Name}', optional(Auth::user())->company_name ?? '', $message);
			$array = array();
			$ccarray = array();
			if(isset($requestData['email_cc']) && !empty($requestData['email_cc'])){
				foreach($requestData['email_cc'] as $cc){
					$clientcc = \App\Models\Admin::Where('id', $cc)->first();
					$ccarray[] = $clientcc;
				}
			}
				$sent = $this->send_compose_template($to, $subject, 'support@digitrex.live', $message, 'digitrex', $array, $ccarray ?? []);
			if($sent){
				$clientMatter = ClientMatter::find($request->noteid);
				$objs = new ActivitiesLog;
				$objs->client_id = $clientMatter ? $clientMatter->client_id : null;
				$objs->created_by = Auth::user()->id;
				$objs->subject = '<b>Subject : '.$subject.'</b>';
				$objs->description = '<b>To: '.$to.'</b></br>'.$message;
				$objs->activity_type = 'email';
				$objs->use_for = 'matter';
				$saved = $objs->save();
				$response['status'] 	= 	true;
				$response['message']	=	'Email Sent Successfully';
			}else{
				$response['status'] 	= 	false;
				$response['message']	=	'Failed to send email. Please try again.';
			}

		echo json_encode($response);
	}

	public function updateintake(Request $request){
		// intakedate was on applications table which has been removed
		echo json_encode(['status' => true, 'message' => 'Date field removed with applications table.']);
	}

	public function updateexpectwin(Request $request){
		// expect_win_date was on applications table - use client_matters.deadline instead
		$obj = ClientMatter::find($request->appid ?? $request->client_matter_id);
		if ($obj && Schema::hasColumn('client_matters', 'deadline')) {
			$obj->deadline = $request->from;
			$saved = $obj->save();
			echo json_encode(['status' => $saved, 'message' => $saved ? 'Date successfully updated.' : 'Please try again']);
		} else {
			echo json_encode(['status' => true, 'message' => 'Date field migrated to matter deadline.']);
		}
	}

	public function updatedates(Request $request){
		// start_date/end_date were on applications - use client_matters.deadline
		$obj = ClientMatter::find($request->appid ?? $request->client_matter_id);
		if ($obj && Schema::hasColumn('client_matters', 'deadline')) {
			$obj->deadline = $request->from;
			$saved = $obj->save();
			if ($saved) {
				$d = $obj->deadline ? date_parse($obj->deadline) : null;
				echo json_encode(['status' => true, 'message' => 'Date successfully updated.', 'dates' => $d ? ['date' => sprintf('%02d', $d['day']), 'month' => date('M', strtotime($obj->deadline)), 'year' => $d['year']] : []]);
			} else {
				echo json_encode(['status' => false, 'message' => 'Please try again']);
			}
		} else {
			echo json_encode(['status' => true, 'message' => 'Date fields migrated to matter.']);
		}
	}

	public function discontinueMatter(Request $request){
		$obj = ClientMatter::find($request->diapp_id ?? $request->client_matter_id);
		if (!$obj) {
			echo json_encode(['status' => false, 'message' => 'Matter not found']);
			return;
		}
		$this->ensureCrmRecordAccess((int) $obj->client_id);
		$user = Auth::user();
		if (! ($user instanceof Staff && ($user->hasEffectiveSuperAdminPrivileges() || $user->canCloseDiscontinueMatter()))) {
			echo json_encode(['status' => false, 'message' => 'Unauthorized']);
			return;
		}
		$obj->matter_status = 0;
		$obj->closed_by = Auth::guard('admin')->id() ?? Auth::id();
		$obj->discontinue_reason = $request->workflow ?? 'Discontinued';
		$obj->discontinue_notes = $request->note ?? '';
		$saved = $obj->save();
		echo json_encode(['status' => $saved, 'message' => $saved ? 'Matter successfully discontinued.' : 'Please try again']);
	}

	public function revertMatter(Request $request){
		$obj = ClientMatter::with('workflowStage')->find($request->revapp_id ?? $request->client_matter_id);
		if (!$obj) {
			echo json_encode(['status' => false, 'message' => 'Matter not found']);
			return;
		}
		$this->ensureCrmRecordAccess((int) $obj->client_id);
		$user = Auth::user();
		if (! ($user instanceof Staff && ($user->hasEffectiveSuperAdminPrivileges() || $user->canCloseDiscontinueMatter()))) {
			echo json_encode(['status' => false, 'message' => 'Unauthorized']);
			return;
		}
		$obj->matter_status = 1;
		$obj->closed_by = null;
		$obj->discontinue_reason = null;
		$obj->discontinue_notes = null;
		$obj->reopen_requested_by = null;
		$saved = $obj->save();
		$stage = $obj->workflowStage;
		$workflowId = $stage->workflow_id ?? $obj->workflow_id;
		$stages = \App\Models\WorkflowStage::when($workflowId, fn($q) => $q->where('workflow_id', $workflowId))->orderByRaw('COALESCE(sort_order, id) ASC')->get();
		$idx = $stages->search(fn($s) => $s->id == ($stage->id ?? 0)) + 1;
		$width = $stages->count() > 0 ? round(($idx / $stages->count()) * 100) : 0;
		$lastStage = $stages->last();
		$displayback = $lastStage && $stage && $lastStage->name == $stage->name;
		echo json_encode(['status' => $saved, 'width' => $width, 'displaycomplete' => $displayback, 'message' => $saved ? 'Matter successfully reverted.' : 'Please try again']);
	}

	public function application_ownership(Request $request){
		// ratio was on applications - client_matters does not have ratio
		echo json_encode(['status' => true, 'message' => 'Ownership ratio field removed with applications table.', 'ratio' => $request->ratio ?? 0]);
	}

	// Removed legacy method: saleforcast

	// REMOVED - Unused method (no references found in views or JavaScript)
	// This method returned application dropdown options for a client but was never used
	// public function getapplicationbycid(Request $request){ ... }


	public function applicationsavefee(Request $request){
		// Fee options functionality has been removed
		$response = [
			'status' => false,
			'message' => 'Application fee options feature has been removed.'
		];
		return response()->json($response);
	}

	// REMOVED - Application PDF export functionality (view file deleted, was broken and unused)
	// public function exportapplicationpdf(Request $request, $id){
	// 	$applications = \App\Models\Application::where('id', $id)->first();
	// 	$cleintname = \App\Models\Admin::whereIn('type', ['client', 'lead'])->where('id',@$applications->client_id)->first();
	// 	$pdf = PDF::setOptions([
	// 		'isHtml5ParserEnabled' => true, 'isRemoteEnabled' => true,
	// 		'logOutputFile' => storage_path('logs/log.htm'),
	// 		'tempDir' => storage_path('logs/')
	// 		])->loadView('emails.application',compact(['cleintname','applications','productdetail','PartnerBranch','partnerdetail']));
	// 	return $pdf->stream('application.pdf');
	// }

	public function getapplications(Request $request){
		$client_id = (int) $request->client_id;
		if ($client_id > 0) {
			$this->ensureCrmRecordAccess($client_id);
		}
		$matters = ClientMatter::where('client_id', '=', $client_id)->orderBy('id','desc')->get();
		ob_start();
		?>
		<option value="">Choose Matter</option>
		<?php
		foreach($matters as $matter){
			$label = $matter->client_unique_matter_no ?? 'Matter #' . $matter->id;
			?>
		<option value="<?php echo $matter->id; ?>"><?php echo e($label); ?></option>
			<?php
		}
		return ob_get_clean();
	}



	/**
	 * Return personal or visa document categories for the Move Document modal.
	 * Called from the CRM web session (auth:admin), so Sanctum token is not needed.
	 */
	public function getDocumentCategoriesForMove(Request $request)
	{
		$type     = $request->get('type');         // 'personal' or 'matter' (legacy: 'visa')
		if ($type === 'visa') {
			$type = 'matter';
		}
		$clientId = (int) $request->get('client_id');
		$matterId = (int) $request->get('matter_id');

		try {
			if ($type === 'personal') {
				$categories = DB::table('personal_document_types')
					->where('status', 1)
					->where(function ($q) use ($clientId) {
						$q->whereNull('client_id')
						  ->orWhere('client_id', $clientId);
					})
					->orderBy('id', 'asc')
					->select('id', 'title')
					->get();

				return response()->json(['success' => true, 'categories' => $categories]);
			}

			if ($type === 'matter') {
				$categories = DB::table('visa_document_types')
					->where('status', 1)
					->where(function ($q) use ($clientId, $matterId) {
						$q->where(function ($q2) {
								$q2->whereNull('client_id')->whereNull('client_matter_id');
							})
						  ->orWhere(function ($q2) use ($clientId) {
								$q2->where('client_id', $clientId)->whereNull('client_matter_id');
							})
						  ->orWhere(function ($q2) use ($clientId, $matterId) {
								$q2->where('client_id', $clientId)->where('client_matter_id', $matterId);
							});
					})
					->orderBy('id', 'asc')
					->select('id', 'title')
					->get();

				return response()->json(['success' => true, 'categories' => $categories]);
			}

			return response()->json(['success' => false, 'message' => 'Invalid type.'], 422);

		} catch (\Exception $e) {
			Log::error('getDocumentCategoriesForMove error: ' . $e->getMessage());
			return response()->json(['success' => false, 'message' => 'Failed to load categories.'], 500);
		}
	}

	/**
	 * Delete email conversations from the database for a closed matter.
	 * Only removes database records (email_logs, email_log_attachments, email_label_email_log).
	 * Does NOT delete any files from S3 cloud storage.
	 *
	 * @param int $clientId
	 * @param int $matterId
	 * @return void
	 */
	private function deleteEmailConversationsForMatter(int $clientId, int $matterId): void
	{
		// Deprecated/Disabled: Matter email history is preserved upon discontinue/completion for legal/audit purposes.
		return;
	}
}
