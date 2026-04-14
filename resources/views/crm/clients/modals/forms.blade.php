<!-- Visa agreement Form -->
<div class="modal fade custom_modal" id="visaAgreementCreateFormModel" tabindex="-1" role="dialog" aria-labelledby="visaAgreementModalLabel11" aria-hidden="true">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="visaAgreementModalLabel">Create Visa Agreement</h5>
				<button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">×</span>
				</button>
			</div>
			<div class="modal-body">
				<form method="POST" action="{{route('clients.generateagreement')}}" name="visaagreementform11" id="visaagreementform11" autocomplete="off">
					@csrf
					<!-- Hidden Fields for Client and Client Matter ID -->
					<input type="hidden" name="client_id" id="visa_agreement_client_id">
					<input type="hidden" name="client_matter_id" id="visa_agreement_client_matter_id">

					<!-- Error Message Container -->
					<div class="custom-error-msg"></div>

					<!-- Agent Details (Read-only, assuming agent is pre-fetched) -->
					<div class="row">
						<div class="col-12">
							<h6 class="font-medium text-gray-900">Agent Details</h6>
							<div class="row mt-2">
								<div class="col-6">
									<div class="form-group">
										<label class="text-sm font-medium text-gray-700">Practitioner name - <span id="visaagree_agent_name_label"></span></label>
										<input type="hidden" name="agent_id" id="visaagree_agent_id">
										<input type="hidden" name="agent_name" id="visaagree_agent_name">
									</div>
								</div>
								<div class="col-6">
									<div class="form-group">
										<label class="text-sm font-medium text-gray-700">Business Name - <span id="visaagree_business_name_label"></span></label>
										<input type="hidden" name="business_name" id="visaagree_business_name" class="form-control bg-gray-100">
									</div>
								</div>
							</div>
						</div>
					</div>

					<!-- Submit Button -->
					<div class="row mt-4">
						<div class="col-12">
							<button type="submit" class="btn btn-primary">Generate Agreement</button>
						</div>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>

<!-- Agreement Model Open -->
<div class="modal fade custom_modal" id="agreementModal" tabindex="-1" role="dialog" aria-labelledby="agreementModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-lg" role="document">
		<form id="agreementUploadForm" enctype="multipart/form-data">
			<input type="hidden" name="clientmatterid" id="agreemnt_clientmatterid" value="">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="agreementModalLabel">Upload Agreement (PDF)</h5>
					<button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
						<span aria-hidden="true">&times;</span>
					</button>
				</div>
				<div class="modal-body">
					<div id="agreementDropZone" class="agreement-drop-zone" role="button" tabindex="0" aria-label="Drop PDF or click to browse">
						<input type="file" name="agreement_doc" id="agreementFileInput" class="agreement-file-input" accept=".pdf" required>
						<i class="fas fa-cloud-upload-alt agreement-drop-icon"></i>
						<p class="agreement-drop-text mb-0">Drag file here or <strong>click to browse</strong></p>
						<span id="agreementFileName" class="agreement-file-name text-muted small d-block mt-1"></span>
					</div>
					<div id="agreementUploadError" class="text-danger small mt-2" style="display:none;"></div>
					<style>
						.agreement-drop-zone {
							border: 2px dashed #007bff;
							border-radius: 8px;
							padding: 32px 24px;
							text-align: center;
							background: #e9ecef;
							cursor: pointer;
							transition: border-color .2s, background-color .2s;
							position: relative;
						}
						.agreement-drop-zone:hover { border-color: #0056b3; background: #dee2e6; }
						.agreement-drop-zone.agreement-drop-zone--over { border-color: #0056b3; background: #cfe2ff; }
						.agreement-drop-icon { font-size: 2.5rem; color: #007bff; display: block; margin-bottom: 12px; }
						.agreement-drop-text { color: #495057; }
						.agreement-file-input { position: absolute; left: 0; top: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; }
					</style>
				</div>
			</div>
		</form>
	</div>
</div>
