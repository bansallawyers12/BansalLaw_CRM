<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Str;
use App\Models\Admin;
use App\Models\ActivitiesLog;
use App\Models\CpDocChecklist;
use App\Models\Document;
use App\Models\ClientMatter;
use App\Models\WorkflowStage;
use App\Services\MatterActionNoteService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use App\Services\FCMService;

/**
 * Staff CRM controller for matters, workflow-adjacent utilities, documents, and checklists.
 */
class ClientMatterHubController extends Controller
{
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

	/**
	 * Legacy AJAX endpoint that previously returned the Matter / client-portal tab HTML.
	 * The tab was removed from the CRM UI; respond with a short notice and link to Workflow.
	 */
	public function getClientPortalDetail(Request $request){
		$matterId = $request->id ?? $request->client_matter_id;
		$clientMatter = ClientMatter::query()->find($matterId);
		if (!$clientMatter) {
			return response('<div class="p-3 text-danger">Matter not found.</div>', 404)
				->header('Content-Type', 'text/html; charset=UTF-8');
		}
		$encodeId = base64_encode(convert_uuencode((string) $clientMatter->client_id));
		$path = '/clients/detail/'.$encodeId;
		if (! empty($clientMatter->client_unique_matter_no)) {
			$path .= '/'.rawurlencode((string) $clientMatter->client_unique_matter_no);
		}
		$path .= '/workflow';
		$workflowUrl = url($path);
		$html = '<div class="p-3"><p class="mb-2 text-muted">This view is no longer used. Open the <strong>Workflow</strong> tab for this matter.</p>'
			.'<a class="btn btn-sm btn-primary" href="'.e($workflowUrl).'">Go to Workflow</a></div>';

		return response($html, 200)->header('Content-Type', 'text/html; charset=UTF-8');
	}

	public function completestage(Request $request){
		$matterId = $request->id ?? $request->client_matter_id;
		$clientMatter = ClientMatter::with('workflowStage')->find($matterId);
		if (!$clientMatter) {
			echo json_encode(['status' => false, 'message' => 'Matter not found']);
			return;
		}
		$stageName = $clientMatter->workflowStage?->name ?? '';
		$clientMatter->matter_status = 0; // Discontinued/completed
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
		$currentStage = $clientMatter->workflowStage;
		$workflowId = $currentStage->w_id ?? $clientMatter->workflow_id;
		$nextStage = WorkflowStage::where('id', '>', $currentStage->id)
			->when($workflowId, fn($q) => $q->where('w_id', $workflowId))
			->orderBy('id','asc')->first();
		if (!$nextStage) {
			echo json_encode(['status' => false, 'message' => 'No next stage']);
			return;
		}
		$stages = WorkflowStage::when($workflowId, fn($q) => $q->where('w_id', $workflowId))->orderBy('id')->get();
		$nextIndex = $stages->search(fn($s) => $s->id == $nextStage->id) + 1;
		$width = $stages->count() > 0 ? round(($nextIndex / $stages->count()) * 100) : 0;
		$clientMatter->workflow_stage_id = $nextStage->id;
		$saved = $clientMatter->save();
		if ($saved) {
			$comments = 'moved the stage from <b>' . $currentStage->name . '</b> to <b>' . $nextStage->name . '</b>';
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
		$currentStage = $clientMatter->workflowStage;
		$workflowId = $currentStage->w_id ?? $clientMatter->workflow_id;
		$prevStage = WorkflowStage::where('id', '<', $currentStage->id)
			->when($workflowId, fn($q) => $q->where('w_id', $workflowId))
			->orderBy('id','Desc')->first();
		if (!$prevStage) {
			echo json_encode(['status' => false, 'message' => '']);
			return;
		}
		$stages = WorkflowStage::when($workflowId, fn($q) => $q->where('w_id', $workflowId))->orderBy('id')->get();
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

			// Get the client matter
			$clientMatter = ClientMatter::find($matterId);
			
			if (!$clientMatter) {
				return response()->json([
					'status' => false,
					'message' => 'Client matter not found'
				], 404);
			}

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

			// When advancing FROM "Verification: Payment, Service Agreement, Forms", only a Legal Practitioner can proceed.
			// Any Legal Practitioner (role 16) can verify and proceed. They must tick and may add optional text.
			$currentStageName = $currentStage->name ?? '';
			$verificationStageNames = ['payment verified', 'verification: payment, service agreement, forms'];
			$isAtVerificationStage = in_array(strtolower(trim($currentStageName)), $verificationStageNames);
			if ($isAtVerificationStage) {
				$user = Auth::guard('admin')->user();
				$userRole = $user ? (int) $user->role : 0;
				// Role 16 = Legal Practitioner; Role 1 = Admin (typically can do anything - allow admin too)
				if ($userRole !== 16 && $userRole !== 1) {
					return response()->json([
						'status' => false,
						'message' => 'Only a Legal Practitioner (or Admin) can verify and proceed to the next stage.'
					], 403);
				}
				$userId = Auth::guard('admin')->id();
				$verificationConfirm = $request->input('verification_confirm');
				if (!filter_var($verificationConfirm, FILTER_VALIDATE_BOOLEAN)) {
					return response()->json([
						'status' => false,
						'message' => 'Please confirm that you have verified Payment, Service Agreement, and Forms before proceeding.'
					], 422);
				}
				// Record the verification
				DB::table('client_matter_payment_forms_verifications')->insert([
					'client_matter_id' => (int) $matterId,
					'verified_by' => $userId,
					'verified_at' => now(),
					'note' => $request->input('verification_note'),
					'created_at' => now(),
					'updated_at' => now(),
				]);
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

				// Activity feed: logged for all CRM workflow stage changes (legacy hook always allows logging).
				if (!$this->shouldOmitActivitiesLogForClientPortalWebContext($request)) {
					$comments = 'moved the stage from <b>' . $currentStage->name . '</b> to <b>' . $nextStage->name . '</b>';
					if ($isAdvancingToDecisionReceived) {
						$decisionOutcome = $request->input('decision_outcome');
						$decisionNote = $request->input('decision_note', '');
						$comments .= '<br>Outcome: <b>' . e($decisionOutcome) . '</b>';
						if (!empty(trim($decisionNote))) {
							$comments .= '<br>Note: ' . e($decisionNote);
						}
					}
					if ($isAtVerificationStage) {
						$verificationNote = $request->input('verification_note', '');
						if (!empty(trim($verificationNote))) {
							$comments .= '<br>Verification note: ' . e($verificationNote);
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
				}

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

			$clientMatter = ClientMatter::find($matterId);

			if (!$clientMatter) {
				return response()->json([
					'status' => false,
					'message' => 'Client matter not found'
				], 404);
			}

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

				$totalStages = WorkflowStage::count();
				$prevOrder = $prevStage->sort_order ?? $prevStage->id;
				$currentStageIndex = WorkflowStage::whereRaw('COALESCE(sort_order, id) <= ?', [$prevOrder])->count();
				$progressPercentage = $totalStages > 0 ? round(($currentStageIndex / $totalStages) * 100) : 0;
				$isFirstStage = !WorkflowStage::whereRaw('COALESCE(sort_order, id) < ?', [$prevOrder])->exists();

				$matterNo = $clientMatter->client_unique_matter_no ?? 'ID: ' . $matterId;

				// Activity feed: logged for all CRM workflow stage changes (legacy hook always allows logging).
				if (!$this->shouldOmitActivitiesLogForClientPortalWebContext($request)) {
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
				}

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
	 * Client detail tabs where matter discontinue/reopen should notify the client (DB notifications + FCM).
	 * Includes legacy URL slug `client_portal` and the current Workflow tab (`workflow`).
	 */
	private function shouldNotifyClientForMatterLifecycle(?string $currentTab): bool
	{
		$t = strtolower(trim((string) $currentTab));

		return in_array($t, ['application', 'client_portal', 'workflow'], true);
	}

	private function shouldOmitActivitiesLogForClientPortalWebContext(Request $request): bool
	{
		return false;
	}

	private function createMatterActionNotes(ClientMatter $clientMatter, string $description): void
	{
		MatterActionNoteService::createGroupedForMatter(
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

			// Activity feed: workflow change from CRM (legacy hook always allows logging).
			if (!$this->shouldOmitActivitiesLogForClientPortalWebContext($request)) {
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
			}

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
			$matterId = $request->input('matter_id');
			$reason = $request->input('discontinue_reason');
			$notes = $request->input('discontinue_notes', '');

			if (!$matterId) {
				return response()->json(['status' => false, 'message' => 'Matter ID is required'], 422);
			}

			if (!$reason || trim($reason) === '') {
				return response()->json(['status' => false, 'message' => 'Please select a reason for discontinuing.'], 422);
			}

			$clientMatter = ClientMatter::find($matterId);

			if (!$clientMatter) {
				return response()->json(['status' => false, 'message' => 'Client matter not found.'], 404);
			}

			$clientMatter->matter_status = 0;
			$saved = $clientMatter->save();

			if ($saved) {
				// applications table removed

				$description = 'Discontinued matter. Reason: <b>' . e($reason) . '</b>';
				if (!empty(trim($notes))) {
					$description .= '<br>Notes: ' . e($notes);
				}

				// Activity feed: matter discontinued from CRM (legacy hook always allows logging).
				if (!$this->shouldOmitActivitiesLogForClientPortalWebContext($request)) {
					$activityLog = new ActivitiesLog;
					$activityLog->client_id = $clientMatter->client_id;
					$activityLog->created_by = Auth::user()->id;
					$activityLog->subject = 'Matter Discontinued';
					$activityLog->description = $description;
					$activityLog->activity_type = 'stage';
					$activityLog->use_for = 'matter';
					$activityLog->task_status = 0;
					$activityLog->pin = 0;
					$activityLog->source = 'crm';
					$activityLog->save();
				}

				// Notify client and send push when discontinue is from matter-related tabs (workflow, application, legacy client_portal slug)
				$currentTab = $request->input('current_tab', 'personaldetails');
				if ($this->shouldNotifyClientForMatterLifecycle($currentTab)) {
					$matterNo = $clientMatter->client_unique_matter_no ?? 'ID: ' . $matterId;
					$notificationMessage = 'Your matter ' . $matterNo . ' has been discontinued. Reason: ' . e($reason);
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

					try {
						$fcmService = new FCMService();
						$notificationTitle = 'Matter Discontinued';
						$notificationBody = 'Your matter ' . $matterNo . ' has been discontinued. Reason: ' . $reason;
						$notificationData = [
							'type' => 'matter_discontinued',
							'client_matter_id' => (string) $matterId,
							'message' => $notificationMessage,
						];
						$fcmService->sendToUser($clientMatter->client_id, $notificationTitle, $notificationBody, $notificationData);
					} catch (\Exception $e) {
						Log::warning('Failed to send push notification for matter discontinued', [
							'client_id' => $clientMatter->client_id,
							'matter_id' => $matterId,
							'error' => $e->getMessage()
						]);
					}
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
					'message' => 'Matter has been successfully discontinued.',
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
	 * Reopen a discontinued client matter (set matter_status = 1).
	 *
	 * @param Request $request
	 * @return \Illuminate\Http\JsonResponse
	 */
	public function reopenClientMatter(Request $request)
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

			$clientMatter->matter_status = 1;
			$saved = $clientMatter->save();

			if ($saved) {
				// applications table removed

				// Activity feed: matter reopened from CRM (legacy hook always allows logging).
				if (!$this->shouldOmitActivitiesLogForClientPortalWebContext($request)) {
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
				}

				// Notify client and send push when reopen is from matter-related tabs or matter list
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

					try {
						$fcmService = new FCMService();
						$notificationTitle = 'Matter Reopened';
						$notificationBody = $notificationMessage;
						$notificationData = [
							'type' => 'matter_reopened',
							'client_matter_id' => (string) $matterId,
							'message' => $notificationMessage,
						];
						$fcmService->sendToUser($clientMatter->client_id, $notificationTitle, $notificationBody, $notificationData);
					} catch (\Exception $e) {
						Log::warning('Failed to send push notification for matter reopened', [
							'client_id' => $clientMatter->client_id,
							'matter_id' => $matterId,
							'error' => $e->getMessage()
						]);
					}
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

			// Activity feed: permanent matter delete from CRM (legacy hook always allows logging).
			if (!$this->shouldOmitActivitiesLogForClientPortalWebContext($request)) {
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
			}

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

		$workflowId = $clientMatter->workflowStage->w_id ?? $clientMatter->workflow_id;
		$currentStage = $clientMatter->workflowStage;
		$stagesquery = \App\Models\WorkflowStage::when($workflowId, fn($q) => $q->where('w_id', $workflowId))->orderBy('id')->get();
		foreach($stagesquery as $stages){
			$stage1 = '';

			$workflowstagess = \App\Models\WorkflowStage::where('name', $currentStage->name)->when($workflowId, fn($q) => $q->where('w_id', $workflowId))->first();

			$prevdata = $workflowstagess ? \App\Models\WorkflowStage::where('id', '<', $workflowstagess->id)->when($workflowId, fn($q) => $q->where('w_id', $workflowId))->orderBy('id','Desc')->get() : collect();
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
						<a title="Add Note" class="openappnote" data-app-type="<?php echo $stages->name; ?>" data-id="<?php echo $clientMatter->id; ?>" href="javascript:;"><i class="fa fa-file-alt"></i></a>
						<!-- opendocnote REMOVED - workflow checklist upload flow dead (no modal, no handler) -->
						<a data-app-type="<?php echo $stages->name; ?>" title="Email" data-id="<?php echo $clientMatter->id; ?>" data-email="" data-name="" class="openclientemail" title="Compose Mail" href="javascript:;"><i class="fa fa-envelope"></i></a>
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
								<span class="span_txt"><b><?php echo $staff ? $staff->first_name : 'System'; ?></b> <?php echo $applicationlist->description; ?></span>
								<span class="span_time"><?php echo date('d D, M Y h:i A', strtotime($applicationlist->created_at)); ?></span>
							</div>
							<?php if($applicationlist->subject != ''){ ?>
							<div class="app_description">
								<div class="app_card">
									<div class="app_title"><?php echo $applicationlist->subject; ?></div>
								</div>
								<?php if($applicationlist->description != ''){ ?>
								<div class="log_desc">
									<?php echo $applicationlist->description; ?>
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

		$obj = new ActivitiesLog;
		$obj->client_id = $clientMatter ? $clientMatter->client_id : null;
		$obj->created_by = Auth::user()->id;
		$obj->subject = $request->title;
		$obj->description = $request->description;
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

		$lists = ActivitiesLog::where('activity_type','note')
			->where('use_for','matter')
			->where('client_id', $clientMatter ? $clientMatter->client_id : null)
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
						<h4><a class="viewmatternote" data-id="<?php echo $list->id; ?>" href="javascript:;"><?php echo @$list->subject == "" ? config('constants.empty') : Str::limit(@$list->subject, 19, '...'); ?></a></h4>
						<p><?php echo @$list->description == "" ? config('constants.empty') : Str::limit(@$list->description, 15, '...'); ?></p>
						</div>
						<div class="extra_content">
							<div class="left">
								<div class="author">
									<a href="#"><?php echo $staff ? substr($staff->first_name, 0, 1) : '?'; ?></a>
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

	public function clientPortalSendmail(Request $request){
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
		$message .= '<br><br>Consumer guide: <a href="https://www.mara.gov.au/get-help-visa-subsite/FIles/consumer_guide_english.pdf">https://www.mara.gov.au/get-help-visa-subsite/FIles/consumer_guide_english.pdf</a>';
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
				$response['status'] 	= 	true;
				$response['message']	=	'Please try again';
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
		$obj->matter_status = 0;
		$saved = $obj->save();
		echo json_encode(['status' => $saved, 'message' => $saved ? 'Matter successfully discontinued.' : 'Please try again']);
	}

	public function revertMatter(Request $request){
		$obj = ClientMatter::with('workflowStage')->find($request->revapp_id ?? $request->client_matter_id);
		if (!$obj) {
			echo json_encode(['status' => false, 'message' => 'Matter not found']);
			return;
		}
		$obj->matter_status = 1;
		$saved = $obj->save();
		$stage = $obj->workflowStage;
		$workflowId = $stage->w_id ?? $obj->workflow_id;
		$stages = \App\Models\WorkflowStage::when($workflowId, fn($q) => $q->where('w_id', $workflowId))->orderBy('id')->get();
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

	/**
	 * Get checklist options from document_checklists (Personal + Visa)
	 * For use in Add New Checklist type-ahead input
	 */
	public function getDocumentChecklistsOptions(Request $request)
	{
		$search = $request->get('q', '');
		$table = Schema::hasTable('document_checklists') ? 'document_checklists' : 'portal_document_checklists';
		$checklists = DB::table($table)
			->where('status', 1)
			->whereIn('doc_type', [1, 2]) // 1=Personal, 2=Visa
			->when($search, function ($q) use ($search) {
				$q->where('name', 'like', '%' . $search . '%');
			})
			->orderBy('doc_type')
			->orderBy('name')
			->limit(50)
			->get(['id', 'name', 'doc_type']);

		$results = $checklists->map(function ($item) {
			return [
				'id'   => $item->name,
				'text' => $item->name,
				'name' => $item->name,
			];
		});

		return response()->json(['results' => $results]);
	}

	/**
	 * POST /add-checklists
	 * Adds one or more checklist items to cp_doc_checklists for a given client matter and workflow stage.
	 */
	public function addChecklist(Request $request)
	{
		$request->validate([
			'client_matter_id'    => 'required|integer',
			'wf_stage'            => 'required|string|max:255',
			'cp_checklist_names'  => 'required|array|min:1',
			'cp_checklist_names.*'=> 'required|string|max:255',
			'description'         => 'nullable|string|max:1000',
			'allow_client'        => 'nullable|integer|in:0,1',
		]);

		$clientMatterId = (int) $request->client_matter_id;
		$wfStage        = trim($request->wf_stage);
		$names          = array_filter(array_map('trim', $request->cp_checklist_names));
		$description    = $request->description ? trim($request->description) : null;
		$allowClient    = $request->has('allow_client') ? (int) $request->allow_client : 1;

		$matter = DB::table('client_matters')->where('id', $clientMatterId)->first();
		if (!$matter) {
			return response()->json(['success' => false, 'message' => 'Matter not found.'], 404);
		}

		$stage     = DB::table('workflow_stages')->where('name', $wfStage)->first();
		$wfStageId = $stage ? $stage->id : null;

		$inserted  = [];
		$now       = now();
		$adminUser = Auth::guard('admin')->user();
		$userId    = $adminUser ? $adminUser->id : null;

		foreach ($names as $name) {
			$newId = DB::table('cp_doc_checklists')->insertGetId([
				'user_id'           => $userId,
				'client_matter_id'  => $clientMatterId,
				'client_id'         => $matter->client_id,
				'wf_stage'          => $wfStage,
				'wf_stage_id'       => $wfStageId,
				'cp_checklist_name' => $name,
				'description'       => $description,
				'allow_client'      => $allowClient,
				'created_at'        => $now,
				'updated_at'        => $now,
			]);
			$inserted[] = DB::table('cp_doc_checklists')->where('id', $newId)->first();
		}

		$count = count($inserted);

		// When "Allow For Client" is set, notify client (in-app notification + push) so they see new checklist(s)
		if ($count > 0 && $allowClient === 1 && !empty($matter->client_id)) {
			$matterNo = $matter->client_unique_matter_no ?? 'ID: ' . $clientMatterId;
			$namesPreview = implode(', ', array_slice($names, 0, 3));
			if ($count > 3) {
				$namesPreview .= '...';
			}
			$notificationMessage = $count > 1
				? "{$count} new checklist items added for matter {$matterNo}: {$namesPreview}"
				: "New checklist \"{$namesPreview}\" added for matter {$matterNo}";

			DB::table('notifications')->insert([
				'sender_id'      => $userId,
				'receiver_id'    => $matter->client_id,
				'module_id'      => $clientMatterId,
				'url'            => '/documents',
				'notification_type' => 'checklist_added',
				'message'        => $notificationMessage,
				'created_at'     => $now,
				'updated_at'     => $now,
				'sender_status'  => 1,
				'receiver_status' => 0,
				'seen'           => 0,
			]);

			// Broadcast notification count for live bell badge (client portal / mobile)
			try {
				$clientCount = DB::table('notifications')->where('receiver_id', $matter->client_id)->where('receiver_status', 0)->count();
				broadcast(new \App\Events\NotificationCountUpdated($matter->client_id, $clientCount, $notificationMessage, '/documents'));
			} catch (\Exception $e) {
				Log::warning('Failed to broadcast notification count after checklist add', ['client_id' => $matter->client_id, 'error' => $e->getMessage()]);
			}

			// Push notification to client mobile app
			try {
				$fcmService = new FCMService();
				$pushTitle = $count > 1 ? 'New checklists added' : 'New checklist added';
				$pushBody = $count > 1
					? "{$count} checklist items added for matter {$matterNo}"
					: "Checklist \"{$namesPreview}\" added for matter {$matterNo}";
				$pushData = [
					'type'             => 'checklist_added',
					'clientMatterId'   => (string) $clientMatterId,
					'matterNo'         => $matterNo,
					'checklistCount'   => (string) $count,
				];
				$fcmService->sendToUser($matter->client_id, $pushTitle, $pushBody, $pushData);
			} catch (\Exception $e) {
				Log::warning('Failed to send push notification for checklist add', [
					'client_id' => $matter->client_id,
					'error'    => $e->getMessage(),
				]);
			}
		}

		// Create assignee action notes (Query task group)
		$clientMatter = ClientMatter::find($clientMatterId);
		if ($clientMatter) {
			$matterNo = $clientMatter->client_unique_matter_no ?? 'ID: ' . $clientMatterId;
			$namesPreview = implode(', ', array_slice($names, 0, 5));
			if (count($names) > 5) {
				$namesPreview .= '...';
			}
			$desc = $count > 1
				? 'New checklists added for matter ' . $matterNo . ': ' . $namesPreview
				: 'New checklist added for matter ' . $matterNo . ': ' . $namesPreview;
			$this->createMatterActionNotes($clientMatter, $desc);
		}

		return response()->json([
			'success' => true,
			'message' => $count . ' checklist' . ($count > 1 ? 's' : '') . ' added successfully.',
			'data'    => $inserted,
		]);
	}

	// checklistupload REMOVED - workflow checklist upload flow dead (no UI triggers it)

	public function deleteClientPortalDocs(Request $request){
		// Check if we're deleting by list_id (new method) or by id (old method for backward compatibility)
		if($request->has('list_id') && $request->list_id){
			// Delete all documents with the same cp_list_id
			$listId = $request->list_id;

			// Collect all matching documents before deletion so we can remove their S3 files
			$docsToDelete = Document::workflowChecklist()->where('cp_list_id', $listId)->get();

			// Get first document to get client_matter_id for response
			$appdoc = $docsToDelete->first();
			
			if($appdoc){
				// Remove each file from S3 (best-effort — failures are logged, never block DB delete)
				foreach ($docsToDelete as $docForS3) {
					$this->deleteS3File($docForS3->myfile);
				}

				// Delete all documents with this cp_list_id
				$res = Document::workflowChecklist()->where('cp_list_id', $listId)->delete();
				
				if($res){
				$response['status'] 	= 	true;
				$response['message'] 	= 	'Record removed successfully';

				// Notify client (for List Notifications API)
				$clientMatterId = $appdoc->client_matter_id ?? null;
				$clientMatter = $clientMatterId ? DB::table('client_matters')->where('id', $clientMatterId)->first() : null;
				if ($clientMatter && !empty($clientMatter->client_id)) {
					$matterNo = $clientMatter->client_unique_matter_no ?? 'ID: ' . $clientMatter->id;
					$docList = DB::table('cp_doc_checklists')->where('id', $appdoc->cp_list_id)->first();
$docType = $docList ? $docList->cp_checklist_name : ($appdoc->file_name ?? 'Document');
				$notificationMessage = 'Document "' . $docType . '" removed for matter ' . $matterNo;
				DB::table('notifications')->insert([
						'sender_id' => Auth::guard('admin')->id(),
						'receiver_id' => $clientMatter->client_id,
						'module_id' => $clientMatter->id,
						'url' => '/documents',
						'notification_type' => 'document_deleted',
						'message' => $notificationMessage,
						'created_at' => now(),
						'updated_at' => now(),
						'sender_status' => 1,
						'receiver_status' => 0,
						'seen' => 0
					]);

					// Create assignee action notes (Query task group)
					$clientMatterModel = ClientMatter::find($clientMatterId);
					if ($clientMatterModel) {
						$desc = 'Document "' . $docType . '" deleted for matter ' . $matterNo;
						$this->createMatterActionNotes($clientMatterModel, $desc);
					}
				}

				$clientMatterId = $appdoc->client_matter_id ?? null;
				$doclists = $clientMatterId ? Document::workflowChecklist()->where('client_matter_id', $clientMatterId)->orderBy('created_at','DESC')->get() : collect();
		$doclistdata = '';
		foreach($doclists as $doclist){
			$docdata = CpDocChecklist::where('id', $doclist->cp_list_id)->first();
			$fileUrl = ($doclist->myfile && str_starts_with($doclist->myfile, 'http')) ? $doclist->myfile : URL::to('/public/img/documents').'/'.$doclist->file_name;
			$docStatus = $doclist->cp_doc_status ?? 0;
			$doclistdata .= '<tr id="">';
				$doclistdata .= '<td><i class="fa fa-file"></i> '. $doclist->file_name.'<br>'.@$docdata->cp_checklist_name.'</td>';
				$doclistdata .= '<td>';
				$docType = $doclist->doc_type ?? '';
				if($docType == 'application'){ $doclistdata .= 'Application'; }else if($docType == 'acceptance'){ $doclistdata .=  'Acceptance'; }else if($docType == 'payment'){ $doclistdata .=  'Payment'; }else if($docType == 'formi20'){ $doclistdata .=  'Form I 20'; }else if($docType == 'visaapplication'){ $doclistdata .=  'Visa Application'; }else if($docType == 'interview'){ $doclistdata .=  'Interview'; }else if($docType == 'enrolment'){ $doclistdata .=  'Enrolment'; }else if($docType == 'courseongoing'){ $doclistdata .=  'Course Ongoing'; }else{ $doclistdata .= $docType; }
				$doclistdata .= '</td>';
				$staff = \App\Models\Staff::where('id', $doclist->user_id)->first();

			$doclistdata .= '<td><span style="    position: relative;background: rgb(3, 169, 244);font-size: .8rem;height: 24px;line-height: 24px;min-width: 24px;width: 24px;color: #fff;display: block;font-weight: 600;letter-spacing: 1px;text-align: center;border-radius: 50%;overflow: hidden;">'.($staff ? substr($staff->first_name, 0, 1) : '?').'</span>'.($staff ? $staff->first_name : 'System').'</td>';
			$doclistdata .= '<td>'.date('Y-m-d',strtotime($doclist->created_at)).'</td>';
			$doclistdata .= '<td>';
			if($docStatus == 1){
				$doclistdata .= '<span class="check"><i class="fa fa-eye"></i></span>';
			}
				$doclistdata .= '<div class="dropdown d-inline">
					<button class="btn btn-primary dropdown-toggle" type="button" id="" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">Action</button>
					<div class="dropdown-menu">
						<a target="_blank" class="dropdown-item" href="'.$fileUrl.'">Preview</a>
						<a data-id="'.$doclist->id.'" class="dropdown-item deletenote" data-href="deleteclientportaldocs" href="javascript:;">Delete</a>
						<a download class="dropdown-item" href="'.$fileUrl.'">Download</a>';
						if($docStatus == 0){
							$doclistdata .= '<a data-id="'.$doclist->id.'" class="dropdown-item publishdoc" href="javascript:;">Publish Document</a>';
						}else{
							$doclistdata .= '<a data-id="'.$doclist->id.'"  class="dropdown-item unpublishdoc" href="javascript:;">Unpublish Document</a>';
						}

					$doclistdata .= '</div>
				</div>
			</td>';
			$doclistdata .= '</tr>';
		}
		$clientMatterId = $appdoc->client_matter_id ?? null;
		$applicationuploadcount = $clientMatterId ? DB::select("SELECT COUNT(DISTINCT cp_list_id) AS cnt FROM documents WHERE cp_list_id IS NOT NULL AND client_matter_id = " . (int)$clientMatterId) : [((object)['cnt' => 0])];
		$response['status'] 	= 	true;

		$response['doclistdata']	=	$doclistdata;
		$response['client_portal_upload_count']	=	@$applicationuploadcount[0]->cnt; // legacy response key (distinct workflow checklist uploads)
		$response['workflow_checklist_upload_count'] = (int) (@$applicationuploadcount[0]->cnt ?? 0);

		$checklistItems = $clientMatterId ? CpDocChecklist::where('client_matter_id', $clientMatterId)->get() : collect();
			$checklistdata = '<table class="table"><tbody>';
			foreach($checklistItems as $checklistItem){
				$appcount = Document::workflowChecklist()->where('cp_list_id', $checklistItem->id)->count();
				$checklistdata .= '<tr>';
				if($appcount >0){
					$checklistdata .= '<td><span class="check"><i class="fa fa-check"></i></span></td>';
				}else{
					$checklistdata .= '<td><span class="round"></span></td>';
				}

				$checklistdata .= '<td>'.@$checklistItem->cp_checklist_name.'</td>';
				$checklistdata .= '<td><div class="circular-box cursor-pointer"><button class="transparent-button paddingNone">'.$appcount.'</button></div></td>';
			$checklistdata .= '</tr>';
		}
		$checklistdata .= '</tbody></table>';
		$response['checklistdata']	=	$checklistdata;
		$response['type']	=	$appdoc->doc_type ?? $appdoc->type ?? '';
			}else{
				$response['status'] 	= 	false;
				$response['message'] 	= 	'Please try again';
			}
			}else{
				$response['status'] 	= 	false;
				$response['message'] 	= 	'No Record found with this list_id';
			}
			echo json_encode($response);
			return;
		}
		
		// Backward compatibility: Delete by document id (old method)
		$docToDelete = Document::workflowChecklist()->where('id', $request->note_id)->first();
		if($docToDelete){
			$appdoc = $docToDelete;
			$res = $docToDelete->delete();
			if($res){
				$response['status'] 	= 	true;
				$response['message'] 	= 	'Record removed successfully';

				// Notify client (for List Notifications API)
				$clientMatterId = $appdoc->client_matter_id ?? null;
				$clientMatter = $clientMatterId ? DB::table('client_matters')->where('id', $clientMatterId)->first() : null;
				if ($clientMatter && !empty($clientMatter->client_id)) {
					$matterNo = $clientMatter->client_unique_matter_no ?? 'ID: ' . $clientMatter->id;
					$docList = DB::table('cp_doc_checklists')->where('id', $appdoc->cp_list_id)->first();
$docType = $docList ? $docList->cp_checklist_name : ($appdoc->file_name ?? 'Document');
				$notificationMessage = 'Document "' . $docType . '" removed for matter ' . $matterNo;
				DB::table('notifications')->insert([
						'sender_id' => Auth::guard('admin')->id(),
						'receiver_id' => $clientMatter->client_id,
						'module_id' => $clientMatter->id,
						'url' => '/documents',
						'notification_type' => 'document_deleted',
						'message' => $notificationMessage,
						'created_at' => now(),
						'updated_at' => now(),
						'sender_status' => 1,
						'receiver_status' => 0,
						'seen' => 0
					]);

					// Create assignee action notes (Query task group)
					$clientMatterModel = ClientMatter::find($clientMatterId);
					if ($clientMatterModel) {
						$desc = 'Document "' . $docType . '" deleted for matter ' . $matterNo;
						$this->createMatterActionNotes($clientMatterModel, $desc);
					}
				}

				$clientMatterId = $appdoc->client_matter_id ?? null;
				$doclists = $clientMatterId ? Document::workflowChecklist()->where('client_matter_id', $clientMatterId)->orderBy('created_at','DESC')->get() : collect();
		$doclistdata = '';
		foreach($doclists as $doclist){
			$docdata = CpDocChecklist::where('id', $doclist->cp_list_id)->first();
			$fileUrl = ($doclist->myfile && str_starts_with($doclist->myfile, 'http')) ? $doclist->myfile : URL::to('/public/img/documents').'/'.$doclist->file_name;
			$docStatus = $doclist->cp_doc_status ?? 0;
			$doclistdata .= '<tr id="">';
				$doclistdata .= '<td><i class="fa fa-file"></i> '. $doclist->file_name.'<br>'.@$docdata->cp_checklist_name.'</td>';
				$doclistdata .= '<td>';
				$docType = $doclist->doc_type ?? '';
				if($docType == 'application'){ $doclistdata .= 'Application'; }else if($docType == 'acceptance'){ $doclistdata .=  'Acceptance'; }else if($docType == 'payment'){ $doclistdata .=  'Payment'; }else if($docType == 'formi20'){ $doclistdata .=  'Form I 20'; }else if($docType == 'visaapplication'){ $doclistdata .=  'Visa Application'; }else if($docType == 'interview'){ $doclistdata .=  'Interview'; }else if($docType == 'enrolment'){ $doclistdata .=  'Enrolment'; }else if($docType == 'courseongoing'){ $doclistdata .=  'Course Ongoing'; }else{ $doclistdata .= $docType; }
				$doclistdata .= '</td>';
				$staff = \App\Models\Staff::where('id', $doclist->user_id)->first();

			$doclistdata .= '<td><span style="    position: relative;background: rgb(3, 169, 244);font-size: .8rem;height: 24px;line-height: 24px;min-width: 24px;width: 24px;color: #fff;display: block;font-weight: 600;letter-spacing: 1px;text-align: center;border-radius: 50%;overflow: hidden;">'.($staff ? substr($staff->first_name, 0, 1) : '?').'</span>'.($staff ? $staff->first_name : 'System').'</td>';
			$doclistdata .= '<td>'.date('Y-m-d',strtotime($doclist->created_at)).'</td>';
			$doclistdata .= '<td>';
			if($docStatus == 1){
				$doclistdata .= '<span class="check"><i class="fa fa-eye"></i></span>';
			}
				$doclistdata .= '<div class="dropdown d-inline">
					<button class="btn btn-primary dropdown-toggle" type="button" id="" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">Action</button>
					<div class="dropdown-menu">
						<a target="_blank" class="dropdown-item" href="'.$fileUrl.'">Preview</a>
						<a data-id="'.$doclist->id.'" class="dropdown-item deletenote" data-href="deleteclientportaldocs" href="javascript:;">Delete</a>
						<a download class="dropdown-item" href="'.$fileUrl.'">Download</a>';
						if($docStatus == 0){
							$doclistdata .= '<a data-id="'.$doclist->id.'" class="dropdown-item publishdoc" href="javascript:;">Publish Document</a>';
						}else{
							$doclistdata .= '<a data-id="'.$doclist->id.'"  class="dropdown-item unpublishdoc" href="javascript:;">Unpublish Document</a>';
						}

					$doclistdata .= '</div>
				</div>
			</td>';
			$doclistdata .= '</tr>';
		}

		$response['status'] 	= 	true;

		$response['doclistdata']	=	$doclistdata;

			}else{
				$response['status'] 	= 	false;
				$response['message'] 	= 	'Please try again';
			}
		}else{
			$response['status'] 	= 	false;
			$response['message'] 	= 	'No Record found';
		}
		echo json_encode($response);
	}

	public function publishdoc(Request $request){
		$doc = Document::workflowChecklist()->where('id', $request->appid)->first();
		if($doc){
			$doc->cp_doc_status = (int) $request->status;
			$saved = $doc->save();
			if($saved){
				$response['status'] 	= 	true;
				$response['message'] 	= 	'Record updated successfully';
				$clientMatterId = $doc->client_matter_id ?? null;
				$doclists = $clientMatterId ? Document::workflowChecklist()->where('client_matter_id', $clientMatterId)->orderBy('created_at','DESC')->get() : collect();
		$doclistdata = '';
		foreach($doclists as $doclist){
			$docdata = CpDocChecklist::where('id', $doclist->cp_list_id)->first();
			$fileUrl = ($doclist->myfile && str_starts_with($doclist->myfile, 'http')) ? $doclist->myfile : URL::to('/public/img/documents').'/'.$doclist->file_name;
			$docStatus = $doclist->cp_doc_status ?? 0;
			$doclistdata .= '<tr id="">';
				$doclistdata .= '<td><i class="fa fa-file"></i> '. $doclist->file_name.'<br>'.@$docdata->cp_checklist_name.'</td>';
				$doclistdata .= '<td>';
				$docType = $doclist->doc_type ?? '';
				if($docType == 'application'){ $doclistdata .= 'Application'; }else if($docType == 'acceptance'){ $doclistdata .=  'Acceptance'; }else if($docType == 'payment'){ $doclistdata .=  'Payment'; }else if($docType == 'formi20'){ $doclistdata .=  'Form I 20'; }else if($docType == 'visaapplication'){ $doclistdata .=  'Visa Application'; }else if($docType == 'interview'){ $doclistdata .=  'Interview'; }else if($docType == 'enrolment'){ $doclistdata .=  'Enrolment'; }else if($docType == 'courseongoing'){ $doclistdata .=  'Course Ongoing'; }else{ $doclistdata .= $docType; }
				$doclistdata .= '</td>';
				$staff = \App\Models\Staff::where('id', $doclist->user_id)->first();

			$doclistdata .= '<td><span style="    position: relative;background: rgb(3, 169, 244);font-size: .8rem;height: 24px;line-height: 24px;min-width: 24px;width: 24px;color: #fff;display: block;font-weight: 600;letter-spacing: 1px;text-align: center;border-radius: 50%;overflow: hidden;">'.($staff ? substr($staff->first_name, 0, 1) : '?').'</span>'.($staff ? $staff->first_name : 'System').'</td>';
			$doclistdata .= '<td>'.date('Y-m-d',strtotime($doclist->created_at)).'</td>';
			$doclistdata .= '<td>';
			if($docStatus == 1){
				$doclistdata .= '<span class="check"><i class="fa fa-eye"></i></span>';
			}
				$doclistdata .= '<div class="dropdown d-inline">
					<button class="btn btn-primary dropdown-toggle" type="button" id="" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">Action</button>
					<div class="dropdown-menu">
						<a target="_blank" class="dropdown-item" href="'.$fileUrl.'">Preview</a>
						<a data-id="'.$doclist->id.'" class="dropdown-item deletenote" data-href="deleteclientportaldocs" href="javascript:;">Delete</a>
						<a download class="dropdown-item" href="'.$fileUrl.'">Download</a>';
						if($docStatus == 0){
							$doclistdata .= '<a data-id="'.$doclist->id.'" class="dropdown-item publishdoc" href="javascript:;">Publish Document</a>';
						}else{
							$doclistdata .= '<a data-id="'.$doclist->id.'"  class="dropdown-item unpublishdoc" href="javascript:;">Unpublish Document</a>';
						}

					$doclistdata .= '</div>
				</div>
			</td>';
			$doclistdata .= '</tr>';
		}

		$response['status'] 	= 	true;

		$response['doclistdata']	=	$doclistdata;

			}else{
				$response['status'] 	= 	false;
				$response['message'] 	= 	'Please try again';
			}
		}else{
			$response['status'] 	= 	false;
			$response['message'] 	= 	'No Record found';
		}
		echo json_encode($response);
	}

	public function getapplications(Request $request){
		$client_id = $request->client_id;
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

	// REMOVED - Standalone migration index page (not linked from anywhere, orphaned page)
	// public function migrationindex(Request $request)
	// {
	// }

	// REMOVED - Applications import functionality (only used by removed applications index page)
	// public function import(Request $request){
	// }

	public function approveDocument(Request $request){
		$response = ['status' => false, 'message' => 'Error approving document.'];
		
		try {
			$documentId = $request->input('document_id');
			
			if (!$documentId) {
				$response['message'] = 'Document ID is required.';
				return response()->json($response);
			}
			
			// Update document cp_doc_status to 1 (Approved)
			$updated = DB::table('documents')
				->where('id', $documentId)
				->whereNotNull('cp_list_id')
				->update([
					'cp_doc_status' => 1,
					'updated_at' => now()
				]);
			
			if ($updated) {
				// Log activity (workflow checklist document, CRM)
				$doc = DB::table('documents')->where('id', $documentId)->first();
				if ($doc) {
					$clientMatterId = $doc->client_matter_id ?? null;
					$clientMatter = $clientMatterId ? DB::table('client_matters')->where('id', $clientMatterId)->first() : null;
					if ($clientMatter && !empty($clientMatter->client_id)) {
						if (!$this->shouldOmitActivitiesLogForClientPortalWebContext($request)) {
							DB::table('activities_logs')->insert([
								'client_id' => $clientMatter->client_id,
								'created_by' => Auth::guard('admin')->id(),
								'subject' => 'Approved workflow checklist document',
								'description' => 'Workflow checklist document approved in CRM for document ID: ' . $documentId,
								'task_status' => 0,
								'pin' => 0,
								'source' => 'crm',
								'created_at' => now(),
								'updated_at' => now()
							]);
						}

						// Notify client (for List Notifications API)
						$matterNo = $clientMatter->client_unique_matter_no ?? 'ID: ' . $clientMatter->id;
						$docList = DB::table('cp_doc_checklists')->where('id', $doc->cp_list_id)->first();
$docType = $docList ? $docList->cp_checklist_name : ($doc->file_name ?? 'Document');
					$notificationMessage = 'Document "' . $docType . '" approved for matter ' . $matterNo;
						DB::table('notifications')->insert([
							'sender_id' => Auth::guard('admin')->id(),
							'receiver_id' => $clientMatter->client_id,
							'module_id' => $clientMatter->id,
							'url' => '/documents',
							'notification_type' => 'document_approved',
							'message' => $notificationMessage,
							'created_at' => now(),
							'updated_at' => now(),
							'sender_status' => 1,
							'receiver_status' => 0,
							'seen' => 0
						]);

						// Create assignee action notes (Query task group)
						$clientMatterModel = ClientMatter::find($clientMatterId);
						if ($clientMatterModel) {
							$desc = 'Document "' . $docType . '" approved for matter ' . $matterNo;
							$this->createMatterActionNotes($clientMatterModel, $desc);
						}
					}
				}
				$response['status'] = true;
				$response['message'] = 'Document approved successfully!';
			} else {
				$response['message'] = 'Document not found or could not be updated.';
			}
		} catch (\Exception $e) {
			$response['message'] = 'An error occurred: ' . $e->getMessage();
		}

		return response()->json($response);
	}

	public function rejectDocument(Request $request){
		$response = ['status' => false, 'message' => 'Error rejecting document.'];
		
		try {
			$documentId = $request->input('document_id');
			$rejectReason = $request->input('reject_reason');
			
			if (!$documentId) {
				$response['message'] = 'Document ID is required.';
				return response()->json($response);
			}
			
			if (!$rejectReason || trim($rejectReason) === '') {
				$response['message'] = 'Rejection reason is required.';
				return response()->json($response);
			}
			
			// Update cp_doc_status to 2 (Rejected) and cp_rejection_reason
			$updateData = [
				'cp_doc_status' => 2,
				'cp_rejection_reason' => trim($rejectReason),
				'updated_at' => now()
			];
			
			// Update document status to 2 (Rejected)
			$updated = DB::table('documents')
				->where('id', $documentId)
				->whereNotNull('cp_list_id')
				->update($updateData);
			
			if ($updated) {
				// Log activity (workflow checklist document, CRM)
				$doc = DB::table('documents')->where('id', $documentId)->first();
				if ($doc) {
					$clientMatterId = $doc->client_matter_id ?? null;
					$clientMatter = $clientMatterId ? DB::table('client_matters')->where('id', $clientMatterId)->first() : null;
					if ($clientMatter && !empty($clientMatter->client_id)) {
						if (!$this->shouldOmitActivitiesLogForClientPortalWebContext($request)) {
							DB::table('activities_logs')->insert([
								'client_id' => $clientMatter->client_id,
								'created_by' => Auth::guard('admin')->id(),
								'subject' => 'Rejected workflow checklist document',
								'description' => 'Workflow checklist document rejected in CRM for document ID: ' . $documentId . (trim($rejectReason ?? '') !== '' ? '. Reason: ' . trim($rejectReason) : ''),
								'task_status' => 0,
								'pin' => 0,
								'source' => 'crm',
								'created_at' => now(),
								'updated_at' => now()
							]);
						}

						// Notify client (for List Notifications API)
						$matterNo = $clientMatter->client_unique_matter_no ?? 'ID: ' . $clientMatter->id;
						$docList = DB::table('cp_doc_checklists')->where('id', $doc->cp_list_id)->first();
$docType = $docList ? $docList->cp_checklist_name : ($doc->file_name ?? 'Document');
					$notificationMessage = 'Document "' . $docType . '" rejected for matter ' . $matterNo;
						DB::table('notifications')->insert([
							'sender_id' => Auth::guard('admin')->id(),
							'receiver_id' => $clientMatter->client_id,
							'module_id' => $clientMatter->id,
							'url' => '/documents',
							'notification_type' => 'document_rejected',
							'message' => $notificationMessage,
							'created_at' => now(),
							'updated_at' => now(),
							'sender_status' => 1,
							'receiver_status' => 0,
							'seen' => 0
						]);

						// Create assignee action notes (Query task group)
						$clientMatterModel = ClientMatter::find($clientMatterId);
						if ($clientMatterModel) {
							$desc = 'Document "' . $docType . '" rejected for matter ' . $matterNo;
							$this->createMatterActionNotes($clientMatterModel, $desc);
						}
					}
				}
				$response['status'] = true;
				$response['message'] = 'Document rejected successfully!';
			} else {
				$response['message'] = 'Document not found or could not be updated.';
			}
		} catch (\Exception $e) {
			$response['message'] = 'An error occurred: ' . $e->getMessage();
		}
		
		return response()->json($response);
	}

	public function downloadDocument(Request $request){
		$response = ['status' => false, 'message' => 'Error downloading document.'];
		
		try {
			$documentId = $request->input('document_id');
			
			if (!$documentId) {
				$response['message'] = 'Document ID is required.';
				return response()->json($response);
			}
			
			// Get document from database (workflow checklist docs only)
			$document = DB::table('documents')
				->where('id', $documentId)
				->whereNotNull('cp_list_id')
				->first();
			
			if (!$document) {
				$response['message'] = 'Document not found.';
				return response()->json($response);
			}
			
			$fileName = $document->file_name ?: 'document.pdf';
			$fileContent = false;
			
			// Prefer S3/URL (myfile) when available
			if ($document->myfile && str_starts_with((string) $document->myfile, 'http')) {
				$fileUrl = $document->myfile;
				$fileContent = @file_get_contents($fileUrl);
			}
			
			// Fallback: local file (workflow checklist via website upload)
			if ($fileContent === false && $fileName) {
				$localPath = config('constants.documents') . '/' . $fileName;
				if (file_exists($localPath)) {
					$fileContent = file_get_contents($localPath);
				}
			}
			
			// Retry S3 via cURL if file_get_contents failed
			if ($fileContent === false && $document->myfile && str_starts_with((string) $document->myfile, 'http')) {
				$ch = curl_init($document->myfile);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
				$fileContent = curl_exec($ch);
				$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
				if ($httpCode !== 200 || $fileContent === false) {
					$fileContent = false;
				}
			}
			
			if ($fileContent === false) {
				$response['message'] = 'Document not found or could not be retrieved.';
				return response()->json($response);
			}
			
			$fileUrl = $document->myfile ?? '';
			
			// Determine content type based on file extension or file URL
			$extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
			
			// If extension is empty, try to get it from URL (for S3 links)
			if (empty($extension) && $fileUrl) {
				$urlPath = parse_url($fileUrl, PHP_URL_PATH);
				if ($urlPath) {
					$urlExtension = strtolower(pathinfo($urlPath, PATHINFO_EXTENSION));
					if (!empty($urlExtension)) {
						$extension = $urlExtension;
					}
				}
			}
			
			// Default to PDF if extension still empty
			if (empty($extension)) {
				$extension = 'pdf';
			}
			
			$contentType = 'application/octet-stream';
			
			if ($extension === 'pdf') {
				$contentType = 'application/pdf';
			} elseif (in_array($extension, ['jpg', 'jpeg'])) {
				$contentType = 'image/jpeg';
			} elseif ($extension === 'png') {
				$contentType = 'image/png';
			} elseif ($extension === 'doc') {
				$contentType = 'application/msword';
			} elseif ($extension === 'docx') {
				$contentType = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
			}
			
			// Ensure filename has proper extension
			if (empty(pathinfo($fileName, PATHINFO_EXTENSION))) {
				$fileName .= '.' . $extension;
			}
			
			// Ensure filename is properly encoded
			$encodedFileName = rawurlencode($fileName);
			
			// Notify client (for List Notifications API)
			$clientMatterId = $document->client_matter_id ?? null;
			$clientMatter = $clientMatterId ? DB::table('client_matters')->where('id', $clientMatterId)->first() : null;
			if ($clientMatter && !empty($clientMatter->client_id) && Auth::guard('admin')->check()) {
				$matterNo = $clientMatter->client_unique_matter_no ?? 'ID: ' . $clientMatter->id;
				$docList = DB::table('cp_doc_checklists')->where('id', $document->cp_list_id)->first();
				$docType = $docList ? $docList->cp_checklist_name : ($document->file_name ?? 'Document');
				$notificationMessage = 'Document "' . $docType . '" downloaded for matter ' . $matterNo;
				DB::table('notifications')->insert([
					'sender_id' => Auth::guard('admin')->id(),
					'receiver_id' => $clientMatter->client_id,
					'module_id' => $clientMatter->id,
					'url' => '/documents',
					'notification_type' => 'document_downloaded',
					'message' => $notificationMessage,
					'created_at' => now(),
					'updated_at' => now(),
					'sender_status' => 1,
					'receiver_status' => 0,
					'seen' => 0
				]);
			}
			
			// Return file as download with proper headers to force download
			return response($fileContent, 200)
				->header('Content-Type', $contentType)
				->header('Content-Disposition', 'attachment; filename="' . addslashes($fileName) . '"; filename*=UTF-8\'\'' . $encodedFileName)
				->header('Content-Length', strlen($fileContent))
				->header('Cache-Control', 'no-cache, no-store, must-revalidate')
				->header('Pragma', 'no-cache')
				->header('Expires', '0')
				->header('X-Content-Type-Options', 'nosniff');
				
		} catch (\Exception $e) {
			$response['message'] = 'An error occurred: ' . $e->getMessage();
			return response()->json($response);
		}
	}

	/**
	 * GET /api/crm/matter-checklist-documents
	 * Returns documents for a given cp_doc_checklists entry.
	 */
	public function getChecklistDocuments(Request $request)
	{
		$checklistId   = $request->get('checklist_id');
		$clientMatterId = $request->get('client_matter_id');

		if (!$checklistId) {
			return response()->json(['success' => false, 'message' => 'checklist_id is required.'], 422);
		}

		$documents = DB::table('documents')
			->where('cp_list_id', $checklistId)
			->where('type', 'workflow_checklist')
			->when($clientMatterId, fn($q) => $q->where('client_matter_id', $clientMatterId))
			->select('id', 'file_name', 'myfile', 'cp_doc_status', 'cp_rejection_reason', 'created_at')
			->orderBy('id', 'asc')
			->get();

		return response()->json(['success' => true, 'documents' => $documents]);
	}

	/**
	 * POST /api/crm/matter-checklist-delete-document
	 * Deletes a single document by ID.
	 */
	public function deleteChecklistDocument(Request $request)
	{
		$documentId = $request->get('document_id');

		if (!$documentId) {
			return response()->json(['success' => false, 'message' => 'document_id is required.'], 422);
		}

		$document = DB::table('documents')->where('id', $documentId)->first();

		if (!$document) {
			return response()->json(['success' => false, 'message' => 'Document not found.'], 404);
		}

		$clientMatterId = $document->client_matter_id ?? null;
		$cpListId = $document->cp_list_id ?? null;
		$checklistName = 'checklist';
		if ($cpListId) {
			$cl = DB::table('cp_doc_checklists')->where('id', $cpListId)->first();
			$checklistName = $cl->cp_checklist_name ?? $checklistName;
		} elseif (!empty($document->checklist)) {
			$checklistName = $document->checklist;
		}

		// Remove the file from S3 before deleting the DB record (best-effort)
		$this->deleteS3File($document->myfile);

		$deleted = DB::table('documents')->where('id', $documentId)->delete();

		if ($deleted) {
			if ($clientMatterId) {
				$this->notifyClientAndCreateActionForDocumentStatusChangeByMatter((int) $clientMatterId, $checklistName, 'deleted');
			}
			return response()->json(['success' => true]);
		}

		return response()->json(['success' => false, 'message' => 'Failed to delete document.'], 500);
	}

	/**
	 * POST /api/crm/matter-checklist-update-document-status
	 * Updates cp_doc_status (1=Approved, 2=Rejected) on a document.
	 */
	public function updateChecklistDocumentStatus(Request $request)
	{
		$documentId      = $request->get('document_id');
		$status          = (int) $request->get('status');
		$rejectionReason = $request->get('rejection_reason', '');

		if (!$documentId || !in_array($status, [1, 2])) {
			return response()->json(['success' => false, 'message' => 'document_id and valid status (1 or 2) are required.'], 422);
		}

		$data = ['cp_doc_status' => $status];
		if ($status === 2) {
			$data['cp_rejection_reason'] = $rejectionReason;
		} else {
			$data['cp_rejection_reason'] = null;
		}

		$updated = DB::table('documents')->where('id', $documentId)->update($data);

		if ($updated !== false) {
			$this->notifyClientAndCreateActionForDocumentStatusChange($documentId, $status === 1 ? 'approved' : 'rejected');
			return response()->json(['success' => true]);
		}

		return response()->json(['success' => false, 'message' => 'Document not found.'], 404);
	}

	/**
	 * Notify client (list notifications + badge) and create assignee Query notes for document approve/reject/delete from CRM.
	 * Message format: "{Super admin or PERSON ASSISTING name} approved/rejected/deleted document of {ChecklistName} checklist in {MatterName}."
	 */
	private function notifyClientAndCreateActionForDocumentStatusChange(int $documentId, string $action): void
	{
		$doc = DB::table('documents')->where('id', $documentId)->first();
		if (!$doc || !$doc->client_matter_id) {
			return;
		}
		$clientMatter = DB::table('client_matters')->where('id', $doc->client_matter_id)->first();
		if (!$clientMatter || empty($clientMatter->client_id)) {
			return;
		}
		$checklistRow = $doc->cp_list_id ? DB::table('cp_doc_checklists')->where('id', $doc->cp_list_id)->first() : null;
		$checklistName = $checklistRow->cp_checklist_name ?? ($doc->checklist ?? 'checklist');
		$matterName = $clientMatter->client_unique_matter_no ?? ('ID: ' . $clientMatter->id);

		$actor = Auth::guard('admin')->user();
		$actorName = ($actor && (int) $actor->role === 1) ? 'Super admin' : ($actor ? trim(($actor->first_name ?? '') . ' ' . ($actor->last_name ?? '')) : 'Staff');
		if ($actorName === '') {
			$actorName = 'Staff';
		}

		$notificationType = $action === 'approved' ? 'document_approved' : ($action === 'rejected' ? 'document_rejected' : 'document_deleted');
		$message = $actorName . ' ' . $action . ' document of ' . $checklistName . ' checklist in ' . $matterName . '.';

		DB::table('notifications')->insert([
			'sender_id'         => Auth::guard('admin')->id(),
			'receiver_id'       => $clientMatter->client_id,
			'module_id'         => (int) $clientMatter->id,
			'url'               => '/documents',
			'notification_type' => $notificationType,
			'message'           => $message,
			'created_at'        => now(),
			'updated_at'        => now(),
			'sender_status'     => 1,
			'receiver_status'   => 0,
			'seen'              => 0,
		]);

		try {
			$clientCount = (int) DB::table('notifications')->where('receiver_id', $clientMatter->client_id)->where('receiver_status', 0)->count();
			broadcast(new \App\Events\NotificationCountUpdated($clientMatter->client_id, $clientCount, $message, '/documents'));
		} catch (\Exception $e) {
			Log::warning('Document status change: broadcast failed', ['client_id' => $clientMatter->client_id, 'error' => $e->getMessage()]);
		}

		$clientMatterModel = ClientMatter::find($clientMatter->id);
		if ($clientMatterModel) {
			$this->createMatterActionNotes($clientMatterModel, $message);
		}
	}

	/**
	 * Notify client and create assignee Query notes when a checklist document is deleted (record no longer exists).
	 */
	private function notifyClientAndCreateActionForDocumentStatusChangeByMatter(int $clientMatterId, string $checklistName, string $action): void
	{
		$clientMatter = DB::table('client_matters')->where('id', $clientMatterId)->first();
		if (!$clientMatter || empty($clientMatter->client_id)) {
			return;
		}
		$matterName = $clientMatter->client_unique_matter_no ?? ('ID: ' . $clientMatter->id);
		$actor = Auth::guard('admin')->user();
		$actorName = ($actor && (int) $actor->role === 1) ? 'Super admin' : ($actor ? trim(($actor->first_name ?? '') . ' ' . ($actor->last_name ?? '')) : 'Staff');
		if ($actorName === '') {
			$actorName = 'Staff';
		}
		$notificationType = 'document_deleted';
		$message = $actorName . ' ' . $action . ' document of ' . $checklistName . ' checklist in ' . $matterName . '.';

		DB::table('notifications')->insert([
			'sender_id'         => Auth::guard('admin')->id(),
			'receiver_id'       => $clientMatter->client_id,
			'module_id'         => (int) $clientMatter->id,
			'url'               => '/documents',
			'notification_type' => $notificationType,
			'message'           => $message,
			'created_at'        => now(),
			'updated_at'        => now(),
			'sender_status'     => 1,
			'receiver_status'   => 0,
			'seen'              => 0,
		]);

		try {
			$clientCount = (int) DB::table('notifications')->where('receiver_id', $clientMatter->client_id)->where('receiver_status', 0)->count();
			broadcast(new \App\Events\NotificationCountUpdated($clientMatter->client_id, $clientCount, $message, '/documents'));
		} catch (\Exception $e) {
			Log::warning('Document delete: broadcast failed', ['client_id' => $clientMatter->client_id, 'error' => $e->getMessage()]);
		}

		$clientMatterModel = ClientMatter::find($clientMatter->id);
		if ($clientMatterModel) {
			$this->createMatterActionNotes($clientMatterModel, $message);
		}
	}

	/**
	 * Delete a file from S3 using its stored full URL.
	 *
	 * Best-effort: if the URL is not an S3 URL, or if deletion fails for any
	 * reason, the error is logged but never propagated — DB deletion always proceeds.
	 */
	private function deleteS3File(?string $myfile): void
	{
		if (!$myfile || !str_starts_with($myfile, 'http')) {
			return;
		}

		try {
			$baseUrl = rtrim(Storage::disk('s3')->url(''), '/');
			if (!$baseUrl || !str_starts_with($myfile, $baseUrl . '/')) {
				return;
			}
			$key = substr($myfile, strlen($baseUrl) + 1);
			if ($key) {
				Storage::disk('s3')->delete($key);
			}
		} catch (\Exception $e) {
			Log::warning('S3 file deletion failed: ' . $e->getMessage(), ['myfile' => $myfile]);
		}
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
}
