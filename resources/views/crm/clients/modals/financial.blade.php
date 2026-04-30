{{-- Commission Invoice & General Invoice modals REMOVED - /create-invoice route and createInvoice controller do not exist --}}
{{-- Payment Details modal (addpaymentmodal) REMOVED - no UI opened it; invoice/payment-store route and /get-invoices do not exist --}}

<!-- Edit Client Funds Ledger Entry Modal -->
<div class="modal fade" id="editLedgerModal" tabindex="-1" role="dialog" aria-labelledby="editLedgerModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editLedgerModalLabel">Edit Client Funds Ledger Entry</h5>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">×</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="editLedgerForm">
                    <input type="hidden" name="id">
                    <input type="hidden" name="client_id" value="{{$fetchedData->id}}">
                    <div class="form-group">
                        <label for="trans_date">Transaction Date</label>
                        <input type="text" class="form-control" name="trans_date" required>
                    </div>
                    <div class="form-group">
                        <label for="entry_date">Entry Date</label>
                        <input type="text" class="form-control" name="entry_date" required>
                    </div>
                    <div class="form-group">
                        <label for="client_fund_ledger_type">Type</label>
                        <input type="text" class="form-control" name="client_fund_ledger_type" readonly>
                    </div>
                    <div class="form-group">
                        <label for="edit_ledger_payment_method">Payment method</label>
                        <select class="form-control" name="payment_method" id="edit_ledger_payment_method">
                            <option value="">—</option>
                            <option value="Cash">Cash</option>
                            <option value="Bank transfer">Bank transfer</option>
                            <option value="EFTPOS">EFTPOS</option>
                            <option value="Refund">Refund</option>
                        </select>
                    </div>
                    <div class="form-group" id="edit_ledger_eftpos_surcharge_group" style="display:none;">
                        <label for="edit_ledger_eftpos_surcharge">Card surcharge ($)</label>
                        <input type="number" class="form-control" name="eftpos_surcharge_amount" id="edit_ledger_eftpos_surcharge" step="0.01" min="0" value="">
                    </div>
                    <div class="form-group">
                        <label for="description">Description</label>
                        <input type="text" class="form-control" name="description">
                    </div>
                    <div class="form-group">
                        <label for="deposit_amount">Funds In (+) <span class="text-muted" style="font-weight:normal;font-size:12px;">(excl. surcharge)</span></label>
                        <input type="number" class="form-control" name="deposit_amount" step="0.01" value="0.00">
                    </div>
                    <div class="form-group">
                        <label for="withdraw_amount">Funds Out (-)</label>
                        <input type="number" class="form-control" name="withdraw_amount" step="0.01" value="0.00">
                    </div>

            </div>
            <div class="modal-footer">
                <div class="upload_client_receipt_document" style="display:inline-block;">
                    <input type="hidden" name="type" value="client">
                    <input type="hidden" name="doctype" value="client_receipt">
                    <span class="file-selection-hint" style="margin-left: 10px; color: #34395e;"></span>
                    <a href="javascript:;" class="btn btn-primary add-document-btn"><i class="fa fa-plus"></i> Add Document</a>
                    <input class="docclientreceiptupload" type="file" name="document_upload[]"/>
                </div>
                </form>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="updateLedgerEntryBtn">Update Entry</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Office Receipt Entry Modal -->
<div class="modal fade" id="editOfficeReceiptModal" tabindex="-1" role="dialog" aria-labelledby="editOfficeReceiptModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editOfficeReceiptModalLabel"><i class="fas fa-hand-holding-usd"></i> Edit Direct Office Receipt</h5>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">×</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="editOfficeReceiptForm">
                    <input type="hidden" name="id">
                    <input type="hidden" name="receipt_id" id="edit_office_receipt_id">
                    <input type="hidden" name="client_id" value="{{$fetchedData->id}}">
                    <input type="hidden" name="client_matter_id" id="edit_office_client_matter_id">
                    <input type="hidden" name="receipt_type" value="2">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_office_trans_date">Transaction Date <span class="text-danger">*</span></label>
                                <input type="text" class="form-control datepicker" name="trans_date" id="edit_office_trans_date" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_office_entry_date">Entry Date <span class="text-danger">*</span></label>
                                <input type="text" class="form-control datepicker" name="entry_date" id="edit_office_entry_date" required>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_office_payment_method">Payment Method <span class="text-danger">*</span></label>
                                <select class="form-control" name="payment_method" id="edit_office_payment_method" required>
                                    <option value="">Select Method</option>
                                    <option value="Cash">Cash</option>
                                    <option value="Bank transfer">Bank Transfer</option>
                                    <option value="EFTPOS">EFTPOS</option>
                                    <option value="Refund">Refund</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_office_deposit_amount">Amount received <span class="text-danger">*</span> <span class="text-muted" style="font-weight:normal;font-size:12px;">(excl. surcharge)</span></label>
                                <input type="number" class="form-control" name="deposit_amount" id="edit_office_deposit_amount" step="0.01" value="0.00" required>
                            </div>
                        </div>
                    </div>

                    <div class="row" id="edit_office_eftpos_surcharge_row" style="display:none;">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_office_eftpos_surcharge">Card surcharge ($)</label>
                                <input type="number" class="form-control" name="eftpos_surcharge_amount" id="edit_office_eftpos_surcharge" step="0.01" min="0" value="">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label for="edit_office_invoice_no">Invoice Number (Optional)</label>
                                <select class="form-control" name="invoice_no" id="edit_office_invoice_no">
                                    <option value="">Select Invoice (Optional)</option>
                                </select>
                                <small class="form-text text-muted">Attach this payment to an invoice</small>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label for="edit_office_description">Description</label>
                                <textarea class="form-control" name="description" id="edit_office_description" rows="3"></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12">
                            <div class="upload_office_receipt_document_edit" style="display:inline-block;">
                                <input type="hidden" name="type" value="client">
                                <input type="hidden" name="doctype" value="office_receipt">
                                <span class="file-selection-hint-edit" style="margin-left: 10px; color: #34395e;"></span>
                                <a href="javascript:;" class="btn btn-info add-document-btn-edit"><i class="fa fa-plus"></i> Add/Update Document</a>
                                <input class="docofficereceiptupload_edit" type="file" name="document_upload[]"/>
                            </div>
                            <div id="current_document_display" class="mt-2"></div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-secondary" id="updateOfficeReceiptDraftBtn"><i class="fas fa-save"></i> Save as Draft</button>
                <button type="button" class="btn btn-primary" id="updateOfficeReceiptFinalBtn"><i class="fas fa-check"></i> Save and Finalize</button>
            </div>
        </div>
    </div>
</div>

<!-- Cost Assignment Form -->
<div class="modal fade custom_modal" id="costAssignmentCreateFormModel" tabindex="-1" role="dialog" aria-labelledby="costAssignmentModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="costAssignmentModalLabel">Create Cost Assignment</h5>
				<button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">×</span>
				</button>
			</div>
			<div class="modal-body">
				<form method="POST" action="{{route('clients.savecostassignment')}}" name="costAssignmentform" id="costAssignmentform" autocomplete="off">
					@csrf
					<!-- Hidden Fields for Client and Client Matter ID -->
					<input type="hidden" name="client_id" id="cost_assignment_client_id">
					<input type="hidden" name="client_matter_id" id="cost_assignment_client_matter_id">
                    <input type="hidden" name="agent_id" id="costassign_agent_id">
					<!-- Error Message Container -->
					<div class="custom-error-msg"></div>

					<!-- Agent Details (Read-only, assuming agent is pre-fetched) -->
					<div class="row">
						<div class="col-12">
							<h6 class="font-medium text-gray-900">Agent Details</h6>
							<div class="row mt-2">
								<div class="col-6">
									<div class="form-group">
										<label class="text-sm font-medium text-gray-700">Practitioner name - <span id="costassign_agent_name_label"></span></label>
                                    </div>
								</div>
								<div class="col-6">
									<div class="form-group">
										<label class="text-sm font-medium text-gray-700">Business Name - <span id="costassign_business_name_label"></span></label>
									</div>
								</div>

                                <div class="col-6">
									<div class="form-group">
										<label class="text-sm font-medium text-gray-700">Client Matter Name - <span id="costassign_client_matter_name_label"></span></label>
									</div>
								</div>
                            </div>
						</div>
					</div>

                    <div class="accordion-body collapse show" id="primary_info" data-parent="#accordion">

						<div style="margin-bottom: 15px;" class="accordion-header" role="button" data-bs-toggle="collapse" data-bs-target="#primary_info" aria-expanded="true">
							<h4>Block Fee</h4>
						</div>

						<div class="row">
							<div class="col-12 col-md-6 col-lg-6">
								<div class="form-group">
									<label for="Block_1_Ex_Tax">Block 1 Incl. GST</label>
									{!! html()->text('Block_1_Ex_Tax')->class('form-control')->id('Block_1_Ex_Tax')->attribute('autocomplete', 'off')->attribute('placeholder', 'Enter Block 1 Incl. GST' ) !!}
									@if ($errors->has('Block_1_Ex_Tax'))
										<span class="custom-error" role="alert">
											<strong>{{ @$errors->first('Block_1_Ex_Tax') }}</strong>
										</span>
									@endif
								</div>
							</div>

							<div class="col-12 col-md-6 col-lg-6">
								<div class="form-group">
									<label for="Block_2_Ex_Tax">Block 2 Incl. GST</label>
									{!! html()->text('Block_2_Ex_Tax')->class('form-control')->id('Block_2_Ex_Tax')->attribute('autocomplete', 'off')->attribute('placeholder', 'Enter Block 2 Incl. GST' ) !!}
									@if ($errors->has('Block_2_Ex_Tax'))
										<span class="custom-error" role="alert">
											<strong>{{ @$errors->first('Block_2_Ex_Tax') }}</strong>
										</span>
									@endif
								</div>
							</div>
						</div>

						<div class="row">
							<div class="col-12 col-md-6 col-lg-6">
								<div class="form-group">
									<label for="Block_3_Ex_Tax">Block 3 Incl. GST</label>
									{!! html()->text('Block_3_Ex_Tax')->class('form-control')->id('Block_3_Ex_Tax')->attribute('autocomplete', 'off')->attribute('placeholder', 'Enter Block 3 Incl. GST' ) !!}
									@if ($errors->has('Block_3_Ex_Tax'))
										<span class="custom-error" role="alert">
											<strong>{{ @$errors->first('Block_3_Ex_Tax') }}</strong>
										</span>
									@endif
								</div>
							</div>

							<div class="col-12 col-md-6 col-lg-6">
								<div class="form-group">
									<label for="TotalBLOCKFEE">Total Block Fee</label>
									{!! html()->text('TotalBLOCKFEE')->class('form-control')->id('TotalBLOCKFEE')->attribute('autocomplete', 'off')->attribute('placeholder', 'Enter Total Block Fee')->attribute('readonly', 'readonly' ) !!}
								</div>
							</div>
						</div>

                        <div style="margin-bottom: 15px;" class="accordion-header">
                            <h4>Disbursements</h4>
                        </div>

                        <div id="disbursement-lines-container">
                            <div class="disbursement-lines-header row mb-1 d-none d-md-flex">
                                <div class="col-md-4"><small class="text-muted font-weight-bold">Nature</small></div>
                                <div class="col-md-4"><small class="text-muted font-weight-bold">Description</small></div>
                                <div class="col-md-3"><small class="text-muted font-weight-bold">Amount ($)</small></div>
                            </div>
                            <div id="disbursement-rows">
                                {{-- rows injected by JS --}}
                            </div>
                        </div>

                        <div class="row mt-2 mb-3">
                            <div class="col-12">
                                <button type="button" class="btn btn-outline-secondary btn-sm btn-add-disbursement-row">
                                    <i class="fas fa-plus mr-1"></i> Add Disbursement
                                </button>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12 col-md-6 col-lg-6">
                                <div class="form-group">
                                    <label for="TotalDisbursements">Total Disbursements</label>
                                    <input type="text" name="TotalDisbursements" id="TotalDisbursements" class="form-control" readonly placeholder="0.00">
                                </div>
                            </div>
                        </div>

					<div style="margin-bottom: 15px;" class="accordion-header" role="button" data-bs-toggle="collapse" data-bs-target="#primary_info" aria-expanded="true">
                            <h4>Additional Fee</h4>
                        </div>

                        <div class="row">
                            <div class="col-12 col-md-6 col-lg-6">
                                <div class="form-group">
                                    <label for="additional_fee_1">Additional Fee1</label>
                                    {!! html()->text('additional_fee_1')->class('form-control')->id('additional_fee_1')->attribute('autocomplete', 'off')->attribute('placeholder', 'Enter Additional Fee' ) !!}
                                    @if ($errors->has('additional_fee_1'))
                                        <span class="custom-error" role="alert">
                                            <strong>{{ @$errors->first('additional_fee_1') }}</strong>
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>

                    </div>

					<!-- Submit Button -->
					<div class="row mt-4">
						<div class="col-12">
							<button type="submit" class="btn btn-primary">Save Cost Assignment</button>
						</div>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>

<!-- Lead Cost Assignment Form -->
<div class="modal fade custom_modal" id="costAssignmentCreateFormModelLead" tabindex="-1" role="dialog" aria-labelledby="costAssignmentModalLabelLead" aria-hidden="true">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="costAssignmentModalLabelLead">Create Cost Assignment</h5>
				<button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">×</span>
				</button>
			</div>
			<div class="modal-body">
				<form method="POST" action="{{route('clients.savecostassignmentlead')}}" name="costAssignmentformlead" id="costAssignmentformlead" autocomplete="off">
					@csrf
					<!-- Hidden Fields for Client and Client Matter ID -->
					<input type="hidden" name="client_id" id="cost_assignment_lead_id">
					<!-- Error Message Container -->
					<div class="custom-error-msg"></div>
					<div class="row">
                        <div class="col-12 col-md-6 col-lg-6">
                            <div class="form-group">
                                <label for="legal_practitioner_lead">Legal Practitioner <span class="span_req">*</span></label>
                                <select data-valid="required" class="form-control select2" name="legal_practitioner" id="sel_legal_practitioner_id_lead">
                                    <option value="">Select responsible solicitor</option>
                                    @foreach(\App\Models\Staff::where('role',16)->select('id','first_name','last_name','email')->where('status',1)->get() as $migAgntlist)
                                        <option value="{{$migAgntlist->id}}">{{@$migAgntlist->first_name}} {{@$migAgntlist->last_name}} ({{@$migAgntlist->email}})</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-12 col-md-6 col-lg-6">
                            <div class="form-group">
                                <label for="person_responsible">Select Person Responsible <span class="span_req">*</span></label>
                                <select data-valid="required" class="form-control select2" name="person_responsible" id="sel_person_responsible_id_lead">
                                    <option value="">Select Person Responsible</option>
                                    @foreach(\App\Models\Staff::where('role',12)->select('id','first_name','last_name','email')->where('status',1)->get() as $perreslist)
                                        <option value="{{$perreslist->id}}">{{@$perreslist->first_name}} {{@$perreslist->last_name}} ({{@$perreslist->email}})</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-12 col-md-6 col-lg-6">
                            <div class="form-group">
                                <label for="person_assisting">Select Person Assisting <span class="span_req">*</span></label>
                                <select data-valid="required" class="form-control select2" name="person_assisting" id="sel_person_assisting_id_lead">
                                    <option value="">Select Person Assisting</option>
                                    @foreach(\App\Models\Staff::where('role',13)->select('id','first_name','last_name','email')->where('status',1)->get() as $perassislist)
                                        <option value="{{$perassislist->id}}">{{@$perassislist->first_name}} {{@$perassislist->last_name}} ({{@$perassislist->email}})</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-12 col-md-6 col-lg-6">
                            <div class="form-group">
                                <label for="office_id">Handling Office <span class="span_req">*</span></label>
                                <select data-valid="required" class="form-control select2" name="office_id" id="sel_office_id_lead">
                                    <option value="">Select Office</option>
                                    @foreach(\App\Models\Branch::orderBy('office_name')->get() as $office)
                                        <option value="{{$office->id}}" 
                                            {{ Auth::user()->office_id == $office->id ? 'selected' : '' }}>
                                            {{$office->office_name}}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-12 col-md-6 col-lg-6">
                            <div class="form-group">
                                <label for="matter_id">Select Matter <span class="span_req">*</span></label>
                                <select data-valid="required" class="form-control select2" name="matter_id" id="sel_matter_id_lead">
                                    <option value="">Select Matter</option>
                                    @php
                                        $leadCostMatterQuery = \App\Models\Matter::select('id', 'title')->where('status', 1)
                                            ->forClientType((bool) (isset($fetchedData) && $fetchedData->is_company));
                                        $leadCostMatterList = $leadCostMatterQuery->get();
                                    @endphp
                                    {{-- Matter type id 1: label from DB (e.g. Civil Law) --}}
                                    <option value="1">{{ \App\Models\Matter::displayTitleFromJoinedRow(\App\Models\Matter::query()->where('id', 1)->value('title')) }}</option>
                                    @foreach($leadCostMatterList->reject(function ($m) { return (int) $m->id === 1; }) as $matterlist)
                                        <option value="{{$matterlist->id}}">{{@$matterlist->title}}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
					</div>

					<div class="accordion-body collapse show" id="primary_info" data-parent="#accordion">
                        <div style="margin-bottom: 15px;" class="accordion-header" role="button" data-bs-toggle="collapse" data-bs-target="#primary_info" aria-expanded="true">
							<h4>Block Fee</h4>
						</div>

						<div class="row">
							<div class="col-12 col-md-6 col-lg-6">
								<div class="form-group">
									<label for="Block_1_Ex_Tax">Block 1 Incl. GST</label>
									{!! html()->text('Block_1_Ex_Tax')->class('form-control')->id('Block_1_Ex_Tax_lead')->attribute('autocomplete', 'off')->attribute('placeholder', 'Enter Block 1 Incl. GST' ) !!}
									@if ($errors->has('Block_1_Ex_Tax'))
										<span class="custom-error" role="alert">
											<strong>{{ @$errors->first('Block_1_Ex_Tax') }}</strong>
										</span>
									@endif
								</div>
							</div>

							<div class="col-12 col-md-6 col-lg-6">
								<div class="form-group">
									<label for="Block_2_Ex_Tax">Block 2 Incl. GST</label>
									{!! html()->text('Block_2_Ex_Tax')->class('form-control')->id('Block_2_Ex_Tax_lead')->attribute('autocomplete', 'off')->attribute('placeholder', 'Enter Block 2 Incl. GST' ) !!}
									@if ($errors->has('Block_2_Ex_Tax'))
										<span class="custom-error" role="alert">
											<strong>{{ @$errors->first('Block_2_Ex_Tax') }}</strong>
										</span>
									@endif
								</div>
							</div>
						</div>

						<div class="row">
							<div class="col-12 col-md-6 col-lg-6">
								<div class="form-group">
									<label for="Block_3_Ex_Tax">Block 3 Incl. GST</label>
									{!! html()->text('Block_3_Ex_Tax')->class('form-control')->id('Block_3_Ex_Tax_lead')->attribute('autocomplete', 'off')->attribute('placeholder', 'Enter Block 3 Incl. GST' ) !!}
									@if ($errors->has('Block_3_Ex_Tax'))
										<span class="custom-error" role="alert">
											<strong>{{ @$errors->first('Block_3_Ex_Tax') }}</strong>
										</span>
									@endif
								</div>
							</div>

							<div class="col-12 col-md-6 col-lg-6">
								<div class="form-group">
									<label for="TotalBLOCKFEE">Total Block Fee</label>
									{!! html()->text('TotalBLOCKFEE')->class('form-control')->id('TotalBLOCKFEE_lead')->attribute('autocomplete', 'off')->attribute('placeholder', 'Enter Total Block Fee')->attribute('readonly', 'readonly' ) !!}
								</div>
							</div>
						</div>

                        <div style="margin-bottom: 15px;" class="accordion-header">
                            <h4>Disbursements</h4>
                        </div>

                        <div id="disbursement-lines-container-lead">
                            <div class="disbursement-lines-header row mb-1 d-none d-md-flex">
                                <div class="col-md-4"><small class="text-muted font-weight-bold">Nature</small></div>
                                <div class="col-md-4"><small class="text-muted font-weight-bold">Description</small></div>
                                <div class="col-md-3"><small class="text-muted font-weight-bold">Amount ($)</small></div>
                            </div>
                            <div id="disbursement-rows-lead">
                                {{-- rows injected by JS --}}
                            </div>
                        </div>

                        <div class="row mt-2 mb-3">
                            <div class="col-12">
                                <button type="button" class="btn btn-outline-secondary btn-sm btn-add-disbursement-row-lead">
                                    <i class="fas fa-plus mr-1"></i> Add Disbursement
                                </button>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12 col-md-6 col-lg-6">
                                <div class="form-group">
                                    <label for="TotalDisbursements_lead">Total Disbursements</label>
                                    <input type="text" name="TotalDisbursements" id="TotalDisbursements_lead" class="form-control" readonly placeholder="0.00">
                                </div>
                            </div>
                        </div>

					<div style="margin-bottom: 15px;" class="accordion-header" role="button" data-bs-toggle="collapse" data-bs-target="#primary_info" aria-expanded="true">
                            <h4>Additional Fee</h4>
                        </div>

                        <div class="row">
                            <div class="col-12 col-md-6 col-lg-6">
                                <div class="form-group">
                                    <label for="additional_fee_1">Additional Fee1</label>
                                    {!! html()->text('additional_fee_1')->class('form-control')->id('additional_fee_1_lead')->attribute('autocomplete', 'off')->attribute('placeholder', 'Enter Additional Fee' ) !!}
                                    @if ($errors->has('additional_fee_1'))
                                        <span class="custom-error" role="alert">
                                            <strong>{{ @$errors->first('additional_fee_1') }}</strong>
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>

                    </div>

					<!-- Submit Button -->
					<div class="row mt-4">
						<div class="col-12">
							<button onclick="customValidate('costAssignmentformlead')" type="button" class="btn btn-primary">Save Cost Assignment</button>
						</div>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>
