{{-- ========================================
    MATTER-RELATED MODALS (Client Portal / Workflow)
    This file contains matter modals for the client detail page
    ======================================== --}}

{{-- 1. Add Application Modal - REMOVED (orphaned, no UI trigger; matters created via client_matters sync) --}}

{{-- 2. Discontinue Matter Modal --}}
<!-- Discontinue Matter Modal -->
<div class="modal fade custom_modal" id="discon_application" tabindex="-1" role="dialog" aria-labelledby="matterModalLabel" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="appliationModalLabel">Discontinue Matter</h5>
				<button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body">
				<form method="post" action="{{URL::to('/client-portal/discontinue')}}" name="discontinue_matter" id="discontinue_matter" autocomplete="off" enctype="multipart/form-data">
				@csrf
				<input type="hidden" name="diapp_id" value="">
					<div class="row">
						<div class="col-12 col-md-12 col-lg-12">
							<div class="form-group">
								<label for="workflow">Discontinue Reason <span class="span_req">*</span></label>
								<select data-valid="required" class="form-control workflow" id="workflow" name="workflow">
									<option value="">Please Select</option>
									<option value="Change of Matter">Change of Matter</option>
									<option value="Error by Team Member">Error by Team Member</option>
									<option value="Financial Difficulties">Financial Difficulties</option>
									<option value="Loss of competitor">Loss of competitor</option>
									<option value="Other Reasons">Other Reasons</option>

								</select>
								<span class="custom-error workflow_error" role="alert">
									<strong></strong>
								</span>
							</div>
						</div>
						<div class="col-12 col-md-12 col-lg-12">
							<div class="form-group">
								<label>Notes <span class="span_req">*</span></label>
								<textarea data-valid="required"  class="form-control" name="note"></textarea>

							</div>
						</div>
						<div class="col-12 col-md-12 col-lg-12">
							<button onclick="customValidate('discontinue_matter')" type="button" class="btn btn-primary">Save</button>
							<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
						</div>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>

{{-- 2a-1. Verification: Payment, Service Agreement, Forms - Legal Practitioner must tick before proceeding --}}
<div class="modal fade custom_modal" id="verification-payment-forms-modal" tabindex="-1" role="dialog" aria-labelledby="verificationPaymentFormsModalLabel" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="verificationPaymentFormsModalLabel">Verification: Payment, Service Agreement, Forms</h5>
				<button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body">
				<p class="mb-3" style="color: #374151;">As a Legal Practitioner, please confirm that you have verified the Payment, Service Agreement, and Forms before proceeding.</p>
				<form id="verification-payment-forms-form" name="verification-payment-forms-form" autocomplete="off">
					@csrf
					<input type="hidden" name="matter_id" id="verification-payment-forms-matter-id" value="">
					<div class="row">
						<div class="col-12 col-md-12 col-lg-12">
							<div class="form-group">
								<div class="form-check">
									<input type="checkbox" class="form-check-input" id="verification-confirm-checkbox" name="verification_confirm" required>
									<label class="form-check-label" for="verification-confirm-checkbox">I have verified Payment, Service Agreement, and Forms for this matter <span class="span_req">*</span></label>
								</div>
								<span class="custom-error verification-confirm-error" role="alert"><strong></strong></span>
							</div>
						</div>
						<div class="col-12 col-md-12 col-lg-12">
							<div class="form-group">
								<label for="verification-note">Optional note</label>
								<textarea class="form-control" id="verification-note" name="verification_note" rows="2" placeholder="Add any optional notes..."></textarea>
							</div>
						</div>
						<div class="col-12 col-md-12 col-lg-12">
							<button type="button" class="btn btn-primary" id="verification-payment-forms-submit">
								<i class="fa-solid fa-check"></i> Verify and Proceed to Next Stage
							</button>
							<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
						</div>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>

{{-- 2a. Decision Received Modal (Granted/Refused/Withdrawn + note) --}}
<div class="modal fade custom_modal" id="decision-received-modal" tabindex="-1" role="dialog" aria-labelledby="decisionReceivedModalLabel" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="decisionReceivedModalLabel">Decision Received</h5>
				<button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body">
				<p class="mb-3" style="color: #374151;">Please select the outcome and add a note.</p>
				<form id="decision-received-form" name="decision-received-form" autocomplete="off">
					@csrf
					<input type="hidden" name="matter_id" id="decision-received-matter-id" value="">
					<div class="row">
						<div class="col-12 col-md-12 col-lg-12">
							<div class="form-group">
								<label for="decision-outcome">Outcome <span class="span_req">*</span></label>
								<select class="form-control" id="decision-outcome" name="decision_outcome" data-valid="required" required>
									<option value="">Please Select</option>
									<option value="Granted">Granted</option>
									<option value="Refused">Refused</option>
									<option value="Withdrawn">Withdrawn</option>
								</select>
								<span class="custom-error decision-outcome-error" role="alert"><strong></strong></span>
							</div>
						</div>
						<div class="col-12 col-md-12 col-lg-12">
							<div class="form-group">
								<label for="decision-note">Note <span class="span_req">*</span></label>
								<textarea class="form-control" id="decision-note" name="decision_note" rows="3" placeholder="Enter note..." required></textarea>
								<span class="custom-error decision-note-error" role="alert"><strong></strong></span>
							</div>
						</div>
						<div class="col-12 col-md-12 col-lg-12">
							<button type="button" class="btn btn-primary" id="decision-received-submit">
								<i class="fa-solid fa-check"></i> Proceed to Decision Received
							</button>
							<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
						</div>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>

{{-- 2b. Discontinue Matter Modal (for Workflow tab - client_matters) --}}
@php
    $discontinueMatterList = collect();
    if (isset($fetchedData) && $fetchedData && ! empty($fetchedData->id)) {
        $discontinueMatterList = \Illuminate\Support\Facades\DB::table('client_matters')
            ->leftJoin('matters', 'client_matters.sel_matter_id', '=', 'matters.id')
            ->select('client_matters.id', 'client_matters.client_unique_matter_no', 'matters.title')
            ->where('client_matters.client_id', $fetchedData->id)
            ->where('client_matters.matter_status', 1)
            ->whereNotNull('client_matters.sel_matter_id')
            ->orderByDesc('client_matters.created_at')
            ->get();
    }
@endphp
<div class="modal fade custom_modal matter-close-modal" id="discontinue-matter-modal" tabindex="-1" role="dialog" aria-labelledby="discontinueMatterModalLabel" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content matter-close-modal__content">
			<div class="modal-header matter-close-modal__header">
				<h5 class="modal-title matter-close-modal__title" id="discontinueMatterModalLabel">Close Matter</h5>
				<x-crm.modal-close />
			</div>
			<div class="modal-body">
				<form id="discontinue-matter-form" name="discontinue-matter-form" autocomplete="off">
					@csrf
					<div class="row">
						<div class="col-12 col-md-12 col-lg-12">
							<div class="form-group">
								<label for="discontinue-matter-select">Matter <span class="span_req">*</span></label>
								<select class="form-control" id="discontinue-matter-select" name="matter_id" data-valid="required" required>
									<option value="">Please Select</option>
									@foreach($discontinueMatterList as $matterOption)
										@php
											$matterLabel = \App\Models\Matter::displayTitleFromJoinedRow($matterOption->title ?? null);
										@endphp
										<option value="{{ $matterOption->id }}">{{ $matterLabel }} ({{ $matterOption->client_unique_matter_no }})</option>
									@endforeach
								</select>
								<span class="custom-error discontinue-matter-error" role="alert"><strong></strong></span>
							</div>
						</div>
						<div class="col-12 col-md-12 col-lg-12">
							<div class="form-group">
								<label for="discontinue-reason">Reason for Discontinue <span class="span_req">*</span></label>
								<select class="form-control" id="discontinue-reason" name="discontinue_reason" data-valid="required" required>
									<option value="">Please Select</option>
									<option value="Complete">Complete</option>
									<option value="Change of Matter">Change of Matter</option>
									<option value="Error by Team Member">Error by Team Member</option>
									<option value="Financial Difficulties">Financial Difficulties</option>
									<option value="Grant of Another visa">Grant of Another visa</option>
									<option value="Loss of Competitor">Loss of Competitor</option>
									<option value="Client Withdrew">Client Withdrew</option>
									<option value="Other Reasons">Other Reasons</option>
								</select>
								<span class="custom-error discontinue-reason-error" role="alert"><strong></strong></span>
							</div>
						</div>
						<div class="col-12 col-md-12 col-lg-12">
							<div class="form-group">
								<label for="discontinue-notes">Notes</label>
								<textarea class="form-control" id="discontinue-notes" name="discontinue_notes" rows="3" placeholder="Optional additional notes"></textarea>
							</div>
						</div>
						<div class="col-12 col-md-12 col-lg-12">
							<button type="button" class="btn btn-danger" id="discontinue-matter-submit">
								<i class="fa-solid fa-ban" id="discontinue-matter-submit-icon"></i> <span id="discontinue-matter-submit-label">Discontinue</span>
							</button>
							<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
						</div>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>

@php
    $matterCompletionChecklist = config('matter_completion.checklist', []);
@endphp
<div class="modal fade custom_modal matter-close-modal" id="complete-matter-modal" tabindex="-1" role="dialog" aria-labelledby="completeMatterModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-lg">
		<div class="modal-content matter-close-modal__content">
			<div class="modal-header matter-close-modal__header">
				<h5 class="modal-title matter-close-modal__title" id="completeMatterModalLabel">Complete Matter — Checklist</h5>
				<x-crm.modal-close />
			</div>
			<div class="modal-body">
				<p class="text-muted small mb-3">All items below must be checked before the matter can be marked as complete.</p>
				<div id="complete-matter-checklist" class="complete-matter-checklist">
					@foreach($matterCompletionChecklist as $key => $label)
						<label class="complete-matter-checklist__item d-flex align-items-start gap-2 mb-2">
							<input type="checkbox" class="complete-matter-check-item mt-1" name="completion_checklist[{{ $key }}]" value="1" data-check-key="{{ $key }}">
							<span>{{ $label }}</span>
						</label>
					@endforeach
				</div>
				<span class="custom-error complete-matter-checklist-error text-danger small d-block mt-2" role="alert"><strong></strong></span>
				<div class="form-group mt-3 mb-0">
					<label for="complete-matter-description">Description <small class="text-muted">(optional)</small></label>
					<textarea class="form-control" id="complete-matter-description" rows="3" maxlength="5000" placeholder="Optional notes about completing this matter"></textarea>
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-outline-secondary" id="complete-matter-back-btn">
					<i class="fa-solid fa-arrow-left"></i> Back
				</button>
				<button type="button" class="btn btn-success" id="complete-matter-submit" disabled>
					<i class="fa-solid fa-circle-check"></i> Complete matter
				</button>
			</div>
		</div>
	</div>
</div>

{{-- 2c. Change Workflow Modal (for existing matters) --}}
<div class="modal fade custom_modal" id="change-workflow-modal" tabindex="-1" role="dialog" aria-labelledby="changeWorkflowModalLabel" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="changeWorkflowModalLabel">Change Workflow</h5>
				<button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body">
				<input type="hidden" id="change-workflow-matter-id" value="">
				<div class="form-group">
					<label for="change-workflow-select">Select Workflow</label>
					<select class="form-control" id="change-workflow-select">
						@foreach(\App\Models\Workflow::orderBy('name')->get() as $wf)
						<option value="{{ $wf->id }}">{{ $wf->name }}{{ $wf->matter ? ' (' . $wf->matter->title . ')' : '' }}</option>
						@endforeach
					</select>
					<small class="form-text text-muted">Stage will be mapped by name; if no match, first stage is used.</small>
				</div>
				<button type="button" class="btn btn-primary" id="change-workflow-submit">
					<i class="fa-solid fa-right-left"></i> Change Workflow
				</button>
				<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
			</div>
		</div>
	</div>
</div>

{{-- 3. Revert Discontinued Matter Modal --}}
<div class="modal fade custom_modal" id="revert_matter" tabindex="-1" role="dialog" aria-labelledby="matterModalLabel" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="appliationModalLabel">Revert Discontinued Matter</h5>
				<button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body">
				<form method="post" action="{{URL::to('/client-portal/revert')}}" name="revertapplication" id="revertapplication" autocomplete="off" enctype="multipart/form-data">
				@csrf
				<input type="hidden" name="revapp_id" value="">
					<div class="row">
						<div class="col-12 col-md-12 col-lg-12">
							<div class="form-group">
								<label>Notes <span class="span_req">*</span></label>
								<textarea data-valid="required"  class="form-control" name="note"></textarea>

							</div>
						</div>
						<div class="col-12 col-md-12 col-lg-12">
							<button onclick="customValidate('revertapplication')" type="button" class="btn btn-primary">Save</button>
							<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
						</div>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>

{{-- 4. Add Interested Service Modal - REMOVED --}}
{{-- Feature deprecated - no UI triggers exist --}}
{{-- Backend routes still exist (/interested-service, /get-services) but modal never opens --}}
{{-- Partner/Product/Branch dropdown population routes (getProduct, getBranch) were never implemented --}}

