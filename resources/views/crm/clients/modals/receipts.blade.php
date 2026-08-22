{{-- ========================================
    ALL RECEIPT-RELATED MODALS
    This file contains all receipt and financial reporting modals
    Total: 6 large modals for comprehensive financial management
    ======================================== --}}
@php
    $__receiptModalSolicitors = \App\Services\ClientEditService::staffSelectableForSolicitorRole();
@endphp

<style>
/* ============================================================================
   CLIENT FUNDS LEDGER - DRAG AND DROP ZONE STYLES
   ============================================================================ */

.ledger-drag-drop-zone {
    border: 2px dashed #ccc;
    border-radius: 6px;
    padding: 15px 12px;
    text-align: center;
    background-color: #f9f9f9;
    cursor: pointer !important;
    transition: all 0.3s ease;
    min-height: 70px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 0;
    width: auto;
    min-width: 250px;
    max-width: 300px;
    position: relative;
    z-index: 1;
}

.ledger-drag-drop-zone:hover {
    border-color: #007bff;
    background-color: #f0f8ff;
    transform: translateY(-2px);
}

.ledger-drag-drop-zone.drag_over {
    border-color: #28a745;
    background-color: #e8f5e9;
    border-width: 3px;
    box-shadow: 0 0 10px rgba(40, 167, 69, 0.3);
}

.ledger-drag-drop-zone .drag-zone-inner {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    width: 100%;
}

.ledger-drag-drop-zone .drag-zone-inner i {
    font-size: 28px;
    color: #2563eb;
    transition: all 0.3s ease;
}

.ledger-drag-drop-zone:hover .drag-zone-inner i {
    transform: scale(1.1);
    color: #0056b3;
}

.ledger-drag-drop-zone .drag-zone-content {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.ledger-drag-drop-zone .drag-zone-text {
    font-size: 13px;
    font-weight: 500;
    color: #333;
    margin: 0;
    line-height: 1.3;
}

.ledger-drag-drop-zone .drag-zone-formats {
    font-size: 11px;
    color: #4b5563;
    line-height: 1.2;
}

.ledger-drag-drop-zone.file-selected {
    border-color: #28a745;
    background-color: #f0fff4;
}

/* Selected Files Display */
.ledger-selected-files-display {
    padding: 8px;
    background-color: #e8f5e9;
    border-radius: 6px;
    border: 1px solid #c3e6cb;
    margin-bottom: 10px;
    max-width: 350px;
}

.ledger-selected-files-display .files-list {
    display: flex;
    flex-direction: column;
    gap: 5px;
    margin-bottom: 8px;
}

.ledger-selected-files-display .file-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 5px 8px;
    background-color: white;
    border-radius: 4px;
    font-size: 13px;
}

.ledger-selected-files-display .file-item i {
    color: #28a745;
}

.ledger-selected-files-display .file-item .file-name {
    flex: 1;
    color: #155724;
    word-break: break-word;
}

.ledger-selected-files-display .file-item .remove-file {
    padding: 0;
    margin: 0;
    line-height: 1;
    color: #dc3545;
    cursor: pointer;
}

.ledger-selected-files-display .file-item .remove-file:hover {
    opacity: 0.8;
}

.ledger-selected-files-display .remove-all-files {
    padding: 5px 10px;
    font-size: 12px;
}

/* Tax invoice line description — taller than a single-line input */
#invoice_receipt_form textarea.invoice-line-description,
#create_invoice_receipt textarea.invoice-line-description,
#adjust_invoice_receipt_form textarea.invoice-line-description {
    min-height: 4.5rem;
    resize: vertical;
    line-height: 1.35;
}
</style>

{{-- 1. Create Receipt Modal (Multi-Type: Client Funds Ledger, Invoice, Office Receipt) --}}
<div class="modal fade custom_modal" id="createreceiptmodal" tabindex="-1" role="dialog" aria-labelledby="receiptModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
		<div class="modal-content">
		  	<div class="modal-header">
				<h5 class="modal-title">Create Receipt</h5>
				<button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
		    </div>

		  	<div class="modal-body receipt-modal-body">
				<!-- Radio Button Selection -->
				<div class="form-group receipt-type-selector">
			  		<label class="receipt-type-selector__label">Select entry type</label>
			  		<div class="receipt-type-selector__options">
			  		<label class="receipt-type-option">
						<input type="radio" name="receipt_type" value="client_receipt" checked>
						<span class="receipt-type-option__pill"><i class="fa-solid fa-building-columns"></i> Trust Account Entry</span>
			  		</label>

			  		<label class="receipt-type-option">
						<input type="radio" name="receipt_type" value="invoice_receipt">
						<span class="receipt-type-option__pill"><i class="fa-solid fa-file-invoice-dollar"></i> Tax Invoice</span>
			  		</label>

			  		<label class="receipt-type-option">
						<input type="radio" name="receipt_type" value="office_receipt">
						<span class="receipt-type-option__pill"><i class="fa-solid fa-hand-holding-dollar"></i> Office Receipt</span>
			  		</label>
			  		</div>
				</div>

				<!-- Trust Account Entry Form (LSBC Uniform Law Compliant) -->
				<form class="form-type trust-entry-form" method="post" action="{{URL::to('/clients/saveaccountreport')}}" name="client_receipt_form" autocomplete="off" id="client_receipt_form" enctype="multipart/form-data">
					@csrf
					<input type="hidden" name="client_id" value="{{$fetchedData->id}}">
					<input type="hidden" name="loggedin_staffid" value="{{@Auth::user()->id}}">
					<input type="hidden" name="receipt_type" value="1">
                    <input type="hidden" name="client_ledger_balance_amount" id="client_ledger_balance_amount" value="">
                    <input type="hidden" name="client_matter_id" id="client_matter_id_ledger" value="">
					<div class="row g-3">
						<div class="col-12 col-md-6 col-lg-4">
							<div class="form-group trust-entry-client-field">
								<label for="trust-entry-client-name">Client <span class="span_req">*</span></label>
								<input type="text" id="trust-entry-client-name" name="client" class="form-control trust-entry-control" data-valid="required" autocomplete="off" placeholder="" value="{{ $fetchedData->first_name.' '.$fetchedData->last_name }}">
								<span class="custom-error title_error" role="alert">
									<strong></strong>
								</span>
							</div>
						</div>

                       	<div class="col-12">
							<div class="form-group trust-entry-lines-panel">
                                <div class="trust-entry-lines-panel__header">
                                    <h6 class="trust-entry-lines-panel__title"><i class="fa-solid fa-list-ul"></i> Transaction lines</h6>
                                    <p class="trust-entry-lines-panel__hint">Add one or more trust receipts, payments, transfers, or refunds. Scroll horizontally on smaller screens to see all columns.</p>
                                </div>
                                <div class="trust-entry-table-scroll">
                                <table class="table trust-entry-lines-table text_wrap vertical_align">
                                    <thead>
                                        <tr>
                                            <th class="trust-entry-col-date">Trans. Date</th>
                                            <th class="trust-entry-col-date">Entry Date</th>
                                            <th class="trust-entry-col-type">Transaction Type</th>
                                            <th class="trust-entry-col-invoice" title="Required when type is Transfer to Office Account">Invoice Ref.</th>
                                            <th class="trust-entry-col-method">Payment Method</th>
                                            <th class="trust-entry-col-desc">Particulars / Description</th>
                                            <th class="trust-entry-amount-col">Trust Receipts (+)</th>
											<th class="trust-entry-amount-col">Trust Payments (−)</th>
                                            <th class="trust-entry-action-col"></th>
                                        </tr>
                                    </thead>
                                    <tbody class="productitem">
                                        <tr class="clonedrow">
                                            <td data-label="Trans. Date">
                                                <input data-valid="required" class="form-control trust-entry-control report_date_fields" name="trans_date[]" type="text" value="" />
                                            </td>
                                            <td data-label="Entry Date">
                                                <input data-valid="required" class="form-control trust-entry-control report_entry_date_fields" name="entry_date[]" type="text" value="" />
                                            </td>
                                            <td data-label="Transaction Type">
                                                <select class="form-control trust-entry-control client_fund_ledger_type" name="client_fund_ledger_type[]" data-valid="required">
                                                    <option value="">Select</option>
                                                    <option value="Deposit" title="Money received into trust account on behalf of client">Trust Receipt</option>
                                                    <option value="Fee Transfer" title="Transfer from trust to office account for professional fees (requires invoice)">Transfer to Office Account</option>
                                                    <option value="Disbursement" title="Payment made from trust account on behalf of client (e.g. court fees, outlays)">Disbursement (Trust Payment)</option>
                                                    <option value="Refund" title="Money returned to client from trust account">Refund to Client</option>
                                                </select>
                                            </td>
                                            <td class="trust-entry-invoice-cell" data-label="Invoice Ref.">
                                                <span class="ledger-invoice-placeholder trust-entry-placeholder">—</span>
                                                <select class="form-control trust-entry-control invoice_no_cls" name="invoice_no[]" style="display:none;">
                                                </select>
                                            </td>
                                            <td data-label="Payment Method">
                                                <div class="trust-entry-cell-stack">
                                                <select class="form-control trust-entry-control ledger-payment-method" name="payment_method[]">
                                                    <option value="">—</option>
                                                    <option value="Cash">Cash</option>
                                                    <option value="Bank transfer">Bank Transfer / EFT</option>
                                                    <option value="EFTPOS">EFTPOS / Card</option>
                                                    <option value="Cheque">Cheque</option>
                                                    <option value="Refund">Refund</option>
                                                </select>
                                                <div class="ledger-eftpos-surcharge-block" style="display:none;">
                                                    <label class="text-muted trust-entry-sub-label">Card surcharge ($)</label>
                                                    <input type="text" class="form-control trust-entry-control ledger-eftpos-surcharge-input" name="eftpos_surcharge_amount[]" inputmode="decimal" autocomplete="off" placeholder="0.00" oninput="this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1').replace(/(\.\d{2}).*/g, '$1')" value="">
                                                </div>
                                                </div>
                                            </td>
                                            <td data-label="Particulars">
                                                <input data-valid="required" class="form-control trust-entry-control" name="description[]" type="text" value="" />
                                            </td>

                                            <td class="trust-entry-amount-col" data-label="Trust Receipts (+)">
                                                <div class="trust-currency-field">
                                                <span class="currencyinput">$</span>
                                                <input data-valid="required" class="form-control trust-entry-control deposit_amount_per_row" name="deposit_amount[]" type="text" oninput="this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1').replace(/(\.\d{2}).*/g, '$1')" value="" readonly/>
                                                </div>
                                            </td>

											<td class="trust-entry-amount-col" data-label="Trust Payments (−)">
                                                <div class="trust-currency-field">
                                                <span class="currencyinput">$</span>
                                                <input data-valid="required" class="form-control trust-entry-control withdraw_amount_per_row" name="withdraw_amount[]" type="text" oninput="this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1').replace(/(\.\d{2}).*/g, '$1')" value="" readonly/>
                                                </div>
                                            </td>

                                            <td class="trust-entry-action-col">
                                                <button type="button" class="btn btn-outline-danger trust-entry-remove-btn removeitems" title="Remove line"><i class="fa-solid fa-xmark"></i></button>
                                            </td>
                                        </tr>
                                    </tbody>
                                    <tfoot class="trust-entry-tfoot">
                                        <tr>
                                            <td colspan="5" class="trust-entry-totals-label">Totals</td>
                                            <td class="trust-entry-amount-col trust-entry-totals-deposit">
                                                <span class="total_deposit_amount_all_rows">$0.00</span>
                                            </td>
                                            <td class="trust-entry-amount-col trust-entry-totals-withdraw">
                                                <span class="total_withdraw_amount_all_rows">$0.00</span>
                                            </td>
                                            <td class="trust-entry-action-col"></td>
                                        </tr>
                                    </tfoot>
                                </table>
                                </div>
                                <div class="trust-entry-totals-mobile" aria-hidden="true">
                                    <span class="trust-entry-totals-label">Totals</span>
                                    <span class="trust-entry-totals-deposit"><span class="total_deposit_amount_all_rows">$0.00</span></span>
                                    <span class="trust-entry-totals-withdraw"><span class="total_withdraw_amount_all_rows">$0.00</span></span>
                                </div>

                            </div>
						</div>

                        <div class="col-12 trust-entry-form-footer">
                            <a href="javascript:;" class="btn btn-sm btn-outline-primary trust-entry-add-line openproductrinfo"><i class="fa-solid fa-plus"></i> Add New Line</a>

                            <div class="trust-entry-form-footer__actions">
                            <div class="upload_client_receipt_document trust-entry-upload">
                                <input type="hidden" name="type" value="client">
                                <input type="hidden" name="doctype" value="client_receipt">
                                
                                <div class="ledger-drag-drop-zone" id="ledgerDragDropZone">
                                    <div class="drag-zone-inner">
                                        <i class="fa-solid fa-cloud-arrow-up"></i>
                                        <div class="drag-zone-content">
                                            <p class="drag-zone-text">Drag files here or <strong>click to browse</strong></p>
                                            <small class="drag-zone-formats">PDF, JPG, PNG, DOC, DOCX (multiple files)</small>
                                        </div>
                                    </div>
                                </div>
                                
                                <input class="docclientreceiptupload d-none" type="file" name="document_upload[]" multiple style="display: none;">
                                
                                <div id="ledger-selected-files-display" class="ledger-selected-files-display" style="display: none;">
                                    <div id="ledger-files-list" class="files-list"></div>
                                    <button type="button" class="btn btn-sm btn-link text-danger remove-all-files" title="Remove all files">
                                        <i class="fa-solid fa-xmark"></i> Clear All
                                    </button>
                                </div>
                                
                                <span class="file-selection-hint"></span>
                            </div>
							<button onclick="customValidate('client_receipt_form')" type="button" class="btn btn-primary trust-entry-save-btn">Save Entry</button>
							<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            </div>
                        </div>
                    </div>
				</form>

				<!-- Tax Invoice Form (LSBC Compliant) -->
				<form class="form-type" method="post" action="{{URL::to('/clients/saveinvoicereport')}}" name="invoice_receipt_form" autocomplete="off" id="invoice_receipt_form" style="display:none;">
					@csrf
					<input type="hidden" name="client_id" value="{{$fetchedData->id}}">
					<input type="hidden" name="loggedin_staffid" value="{{@Auth::user()->id}}">
					<input type="hidden" name="receipt_type" value="3">
					<input type="hidden" name="receipt_id" id="invoice_receipt_id" value="">
					<input type="hidden" name="function_type" id="invoice_function_type" value="">
                    <input type="hidden" name="client_matter_id" id="client_matter_id_invoice" value="">

					<div class="row">
						<div class="col-3 col-md-3 col-lg-3">
							<div class="form-group">
								<label for="client">Client <span class="span_req">*</span></label>
								<input type="text" name="client" class="form-control" data-valid="required" autocomplete="off" placeholder="" value="{{ $fetchedData->first_name.' '.$fetchedData->last_name }}">
								<span class="custom-error title_error" role="alert">
									<strong></strong>
								</span>
							</div>
						</div>

                        <div class="col-12 col-md-12 col-lg-12">
                            <!--<div class="Invoic_no_cls" style="text-align: center;">
                                <b>Invoice No -
                                    <span class="unique_invoice_no"></span>
                                </b>
                                <input type="hidden" name="invoice_no" class="invoice_no" value="">
                            </div>-->
							<div class="form-group">
                                <table border="1" style="margin-bottom:0rem !important;" class="table text_wrap table-striped table-hover table-md vertical_align">
                                    <thead>
                                        <tr>
                                            <th style="width:15%;color: #34395e;" title="Date shown on the tax invoice">Invoice Date</th>
                                            <th style="width:15%;color: #34395e;" title="Date this entry was posted in the system">Date Recorded</th>
                                            <th style="width:13%;color: #34395e;" title="Is GST included in the amount?">GST Included</th>
                                            <th style="width:14%;color: #34395e;" title="Type of charge being invoiced">Charge Type</th>
                                            <th style="width:25%;color: #34395e;">Description</th>
                                            <th style="width:14%;color: #34395e;">Amount</th>
                                            <th style="width:1%;color: #34395e;"></th>
                                        </tr>
                                    </thead>
                                    <tbody class="productitem_invoice">
                                        <tr class="clonedrow_invoice">
                                            <td>
                                                <input name="id[]" type="hidden" value="" />
                                                <input data-valid="required" class="form-control report_date_fields_invoice" name="trans_date[]" type="text" value="" title="Date shown on the tax invoice" />
                                            </td>
                                            <td>
                                                <input data-valid="required" class="form-control report_entry_date_fields_invoice" name="entry_date[]" type="text" value="" title="Date this entry was posted in the system" />
                                            </td>
                                            <td>
                                                <select class="form-control" name="gst_included[]" data-valid="required">
                                                    <option value="">Select</option>
                                                    <option value="Yes">Yes</option>
                                                    <option value="No">No</option>
                                                </select>
                                            </td>

                                            <td>
                                                <select class="form-control payment_type_invoice_per_row" name="payment_type[]" data-valid="required">
                                                    <option value="">Select</option>
                                                    @foreach(\App\Support\InvoiceChargeTypes::options() as $chargeType)
                                                        <option value="{{ $chargeType }}">{{ $chargeType }}</option>
                                                    @endforeach
                                                </select>
                                            </td>
                                            <td>
                                                <textarea data-valid="required" class="form-control invoice-line-description" name="description[]" rows="3"></textarea>
                                            </td>

                                            <td>
                                                <span class="currencyinput" style="display: inline-block;color: #34395e;">$</span>
                                                <input data-valid="required" style="display: inline-block;" class="form-control withdraw_amount_invoice_per_row" name="withdraw_amount[]" type="text" oninput="this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1').replace(/(\.\d{2}).*/g, '$1')" value="" />
                                            </td>

                                            <td>
                                                <a class="removeitems_invoice" href="javascript:;"><i class="fa-solid fa-xmark"></i></a>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>

                                <table border="1" class="table text_wrap table-striped table-hover table-md vertical_align">
                                    <tbody>
                                        <tr>
                                            <td colspan="5" style="width:83.6%;text-align:right;color: #34395e;">Totals</td>
                                            <td colspan="2">
                                                <span class="total_withdraw_amount_all_rows_invoice" style="color: #34395e;"></span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
						</div>

                        <div class="col-3 col-md-3 col-lg-3">
                            <a href="javascript:;" class="openproductrinfo_invoice"><i class="fa-solid fa-plus"></i> Add New Line</a>
                        </div>

						<div class="col-9 col-md-9 col-lg-9 text-right">
                            <input type="hidden" name="save_type" class="save_type" value="">
                            <button onclick="customValidate('invoice_receipt_form','draft')" type="button" class="btn btn-primary invoice-draft-btn" style="margin:0px !important;">Draft Invoice</button>
							<button onclick="customValidate('invoice_receipt_form','final')" type="button" class="btn btn-primary invoice-final-btn" style="margin:0px !important;">Create Invoice</button>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
						</div>
                    </div>
				</form>

				<!-- Office Receipt Form -->
				<form class="form-type"  method="post" action="{{URL::to('/clients/saveofficereport')}}" name="office_receipt_form" autocomplete="off" id="office_receipt_form" style="display:none;">
					@csrf
					<input type="hidden" name="client_id" value="{{$fetchedData->id}}">
					<input type="hidden" name="loggedin_staffid" value="{{@Auth::user()->id}}">
					<input type="hidden" name="receipt_type" value="2">
                    <input type="hidden" name="client_matter_id" id="client_matter_id_office" value="">
                    <input type="hidden" name="save_type" class="save_type_office" value="">
					<div class="row">
						<div class="col-3 col-md-3 col-lg-3">
							<div class="form-group">
								<label for="client">Client <span class="span_req">*</span></label>
								<input type="text" name="client" class="form-control" data-valid="required" autocomplete="off" placeholder="" value="{{ $fetchedData->first_name.' '.$fetchedData->last_name }}">
								<span class="custom-error title_error" role="alert">
									<strong></strong>
								</span>
							</div>
						</div>

                        <div class="col-12 col-md-12 col-lg-12">
							<div class="form-group">
                                <!-- Quick Actions Toolbar -->
                                <div class="quick-actions-toolbar" style="margin-bottom: 15px; padding: 10px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid var(--navy);">
                                    <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                                        <span style="font-weight: 600; color: var(--navy);">
                                            <i class="fa-solid fa-bolt"></i> Quick Actions:
                                        </span>
                                        <button type="button" class="btn btn-sm btn-outline-primary paste-clipboard-btn" 
                                                title="Paste amount from clipboard">
                                            <i class="fa-solid fa-clipboard"></i> Paste from Clipboard
                                            <span class="clipboard-preview" style="margin-left: 5px; font-weight: bold; color: #28a745;"></span>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-info repeat-last-entry-btn"
                                                title="Repeat last office receipt entry">
                                            <i class="fa-solid fa-arrow-rotate-right"></i> Repeat Last Entry
                                        </button>
                                        <small style="margin-left: auto; color: #4b5563;">
                                            <i class="fa-solid fa-circle-info"></i> Use these shortcuts to speed up data entry
                                        </small>
                                    </div>
                                </div>

                                <table border="1" style="margin-bottom:0rem !important;" class="table text_wrap table-striped table-hover table-md vertical_align">
                                    <thead>
                                        <tr>
                                            <th style="width:15%;color: #34395e;">Trans. Date</th>
                                            <th style="width:15%;color: #34395e;">Entry Date</th>
                                            <th style="width:15%;color: #34395e;" title="Invoice number this receipt is linked to (if any)">Invoice Ref. No.</th>
                                            <th style="width:5%;color: #34395e;">Payment method</th>
                                            <th style="width:25%;color: #34395e;">Description</th>
                                            <th style="width:14%;color: #34395e;" title="Amount received into office account">Amount Received</th>
                                            <th style="width:1%;color: #34395e;"></th>
                                        </tr>
                                    </thead>
                                    <tbody class="productitem_office">
                                        <tr class="clonedrow_office">
                                            <td>
                                                <input data-valid="required"  class="form-control report_date_fields_office" name="trans_date[]" type="text" value="" />
                                            </td>
                                            <td>
                                                <input data-valid="required" class="form-control report_entry_date_fields_office" name="entry_date[]" type="text" value="" />
                                            </td>
                                            <td>
                                                <select class="form-control invoice_no_cls"  name="invoice_no[]">
                                                </select>
                                            </td>
                                            <td>
                                                <select class="form-control office-receipt-payment-method" name="payment_method[]" data-valid="required" >
                                                    <option value="">Select</option>
													<option value="Cash">Cash</option>
                                                    <option value="Bank transfer">Bank transfer</option>
                                                    <option value="EFTPOS">EFTPOS</option>
                                                    <option value="Refund">Refund</option>
                                                </select>
                                                <div class="office-eftpos-surcharge-block" style="display:none;margin-top:6px;">
                                                    <label class="text-muted" style="font-size:11px;margin:0;display:block;">Card surcharge ($)</label>
                                                    <input type="text" class="form-control office-eftpos-surcharge-input" name="eftpos_surcharge_amount[]" inputmode="decimal" autocomplete="off" placeholder="0.00" style="font-size:12px;padding:4px 8px;" oninput="this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1').replace(/(\.\d{2}).*/g, '$1')" value="">
                                                </div>
                                            </td>
                                            <td>
                                                <input data-valid="required" class="form-control" name="description[]" type="text" value="" />
                                            </td>

                                            <td>
                                                <span class="currencyinput" style="display: inline-block;color: #34395e;">$</span>
                                                <input data-valid="required" style="display: inline-block;" class="form-control total_deposit_amount_office" name="deposit_amount[]" type="text" oninput="this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1').replace(/(\.\d{2}).*/g, '$1')" value="" />
                                            </td>

                                            <td>
                                                <a class="removeitems_office" href="javascript:;"><i class="fa-solid fa-xmark"></i></a>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>

                                <table border="1" class="table text_wrap table-striped table-hover table-md vertical_align">
                                    <tbody>
                                        <tr>
                                            <td colspan="5" style="width:83.6%;text-align:right;color: #34395e;">Totals</td>
                                            <td colspan="2">
                                                <span class="total_deposit_amount_all_rows_office" style="color: #34395e;"></span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
						</div>

                        <div class="col-3 col-md-3 col-lg-3">
                            <a href="javascript:;" class="openproductrinfo_office"><i class="fa-solid fa-plus"></i> Add New Line</a>
                        </div>

						<div class="col-9 col-md-9 col-lg-9 text-right" style="display: flex; align-items: center; justify-content: flex-end; gap: 10px; flex-wrap: wrap;">
                            <div class="upload_office_receipt_document" style="display:inline-block;">
                                <input type="hidden" name="type" value="client">
                                <input type="hidden" name="doctype" value="office_receipt">
                                
                                <!-- NEW: Drag and Drop Zone -->
                                <div class="ledger-drag-drop-zone office-drag-drop-zone" id="officeDragDropZone">
                                    <div class="drag-zone-inner">
                                        <i class="fa-solid fa-cloud-arrow-up"></i>
                                        <div class="drag-zone-content">
                                            <p class="drag-zone-text">Drag files here or <strong>click to browse</strong></p>
                                            <small class="drag-zone-formats">Accepted: PDF, JPG, PNG, DOC, DOCX (Multiple files allowed)</small>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Keep existing file input (hidden, used as fallback) -->
                                <input class="docofficereceiptupload d-none" type="file" name="document_upload[]" multiple style="display: none;">
                                
                                <!-- File selection display (shown after files are selected) -->
                                <div id="office-selected-files-display" class="ledger-selected-files-display" style="display: none;">
                                    <div id="office-files-list" class="files-list"></div>
                                    <button type="button" class="btn btn-sm btn-link text-danger remove-all-files-office" title="Remove all files">
                                        <i class="fa-solid fa-xmark"></i> Clear All
                                    </button>
                                </div>
                                
                                <!-- Keep existing file-selection-hint for compatibility -->
                                <span class="file-selection-hint1" style="margin-right: 10px; color: #34395e;"></span>
                            </div>

                            <button onclick="customValidate('office_receipt_form','draft')" type="button" class="btn btn-secondary" style="margin: 0px !important;"><i class="fa-solid fa-floppy-disk"></i> Save Draft</button>
                            <button onclick="customValidate('office_receipt_form','final')" type="button" class="btn btn-primary" style="margin: 0px !important;"><i class="fa-solid fa-check"></i> Save and Finalize</button>
							<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
						</div>
                    </div>
				</form>
		  	</div>
		</div>
	</div>
</div>

{{-- 2. Adjust Invoice Receipt Modal --}}
<!-- Create Adjust Invoice Receipt  -->
<div class="modal fade custom_modal" id="createadjustinvoicereceiptmodal" tabindex="-1" role="dialog" aria-labelledby="receiptModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-lg" role="document">
		<div class="modal-content">
		  	<div class="modal-header">
				<h5 class="modal-title">Adjust Invoice</h5>
				<button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
		    </div>

		  	<div class="modal-body">
				<!-- Invoice Receipt Form -->
				<form class="form-type" method="post" action="{{URL::to('/clients/saveadjustinvoicereport')}}" name="adjust_invoice_receipt_form" autocomplete="off" id="adjust_invoice_receipt_form">
					@csrf
					<input type="hidden" name="client_id" value="{{$fetchedData->id}}">
					<input type="hidden" name="loggedin_staffid" value="{{@Auth::user()->id}}">
					<input type="hidden" name="receipt_type" value="3">
					<input type="hidden" name="receipt_id" id="adjust_invoice_receipt_id" value="">
					<input type="hidden" name="function_type" id="adjust_invoice_function_type" value="add">

					<div class="row">
						<div class="col-3 col-md-3 col-lg-3">
							<div class="form-group">
								<label for="client">Client <span class="span_req">*</span></label>
								<input type="text" name="client" class="form-control" data-valid="required" autocomplete="off" placeholder="">
								<span class="custom-error title_error" role="alert">
									<strong></strong>
								</span>
							</div>
						</div>

                        <div class="col-12 col-md-12 col-lg-12">
                            <div class="Invoic_no_cls" style="text-align: center;">
                                <b>Invoice No -
                                    <span class="unique_invoice_no"></span>
                                </b>
                                <input type="hidden" name="invoice_no" class="invoice_no" value="">
                            </div>
							<div class="form-group">
                                <table border="1" style="margin-bottom:0rem !important;" class="table text_wrap table-striped table-hover table-md vertical_align">
                                    <thead>
                                        <tr>
                                            <th style="width:15%;color: #34395e;" title="Date shown on the tax invoice">Invoice Date</th>
                                            <th style="width:15%;color: #34395e;" title="Date this entry was posted in the system">Date Recorded</th>
                                            <th style="width:13%;color: #34395e;" title="Is GST included in the amount?">GST Included</th>
                                            <th style="width:5%;color: #34395e;" title="Type of charge being invoiced">Charge Type</th>
                                            <th style="width:25%;color: #34395e;">Description</th>
                                            <th style="width:14%;color: #34395e;">Amount</th>
                                            <th style="width:1%;color: #34395e;"></th>
                                        </tr>
                                    </thead>
                                    <tbody class="productitem_invoice">
                                        <tr class="clonedrow_invoice">
                                            <td>
                                                <input name="id[]" type="hidden" value="" />
                                                <input data-valid="required" class="form-control report_date_fields_invoice" name="trans_date[]" type="text" value="" title="Date shown on the tax invoice" />
                                            </td>
                                            <td>
                                                <input data-valid="required" class="form-control report_entry_date_fields_invoice" name="entry_date[]" type="text" value="" title="Date this entry was posted in the system" />
                                            </td>
                                            <td>
                                                <select class="form-control" name="gst_included[]">
                                                    <option value="">Select</option>
                                                    <option value="Yes">Yes</option>
                                                    <option value="No">No</option>
                                                </select>
                                            </td>

                                            <td>
                                                <select class="form-control" name="payment_type[]">
                                                    <option value="">Select</option>
                                                    <option value="Adjust">Adjust/Discount</option>
                                                </select>
                                            </td>
                                            <td>
                                                <textarea data-valid="required" class="form-control invoice-line-description" name="description[]" rows="3"></textarea>
                                            </td>

                                            <td>
                                                <span class="currencyinput" style="display: inline-block;color: #34395e;">$</span>
                                                <input data-valid="required" style="display: inline-block;" class="form-control withdraw_amount_invoice_per_row" name="withdraw_amount[]" type="text" value="" />
                                            </td>

                                            <td>
                                                <a class="removeitems_invoice" href="javascript:;"><i class="fa-solid fa-xmark"></i></a>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>

                                <table border="1" class="table text_wrap table-striped table-hover table-md vertical_align">
                                    <tbody>
                                        <tr>
                                            <td colspan="5" style="width:83.6%;text-align:right;color: #34395e;">Totals</td>
                                            <td colspan="2">
                                                <span class="total_withdraw_amount_all_rows_invoice" style="color: #34395e;"></span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
						</div>

                        <div class="col-12 col-md-12 col-lg-12 text-right">
                            <input type="hidden" name="save_type" class="save_type" value="">
                            <button onclick="customValidate('adjust_invoice_receipt_form','final')" type="button" class="btn btn-primary" style="margin:0px !important;">Create Invoice</button>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
						</div>
                    </div>
				</form>
			</div>
		</div>
	</div>
</div>

{{-- 3. Create Client Receipt Modal --}}
<!-- Create Client Receipt Modal -->
<div class="modal fade custom_modal" id="createclientreceiptmodal" tabindex="-1" role="dialog" aria-labelledby="create_noteModalLabel" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="appliationModalLabel">Create Client Receipt</h5>
				<button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body">
                <input type="hidden"  id="top_value_db" value="">
				<form method="post" action="{{URL::to('/clients/saveaccountreport')}}" name="create_client_receipt" autocomplete="off" id="create_client_receipt" enctype="multipart/form-data">
				@csrf
				<input type="hidden" name="client_id" value="{{$fetchedData->id}}">
                <input type="hidden" name="loggedin_staffid" value="{{@Auth::user()->id}}">
                <input type="hidden" name="receipt_type" value="1">
					<div class="row">
						<div class="col-6 col-md-6 col-lg-6">
							<div class="form-group">
								<label for="client">Client <span class="span_req">*</span></label>
								<input type="text" name="client" class="form-control" data-valid="required" autocomplete="off" placeholder="">
								<span class="custom-error title_error" role="alert">
									<strong></strong>
								</span>
							</div>
						</div>

                        <div class="col-6 col-md-6 col-lg-6">
                            <div class="form-group">
                                <label for="sel_client_agent_id">Solicitor <span class="span_req">*</span></label>
                                <select data-valid="required" class="form-control crm-ts-plain" name="agent_id" id="sel_client_agent_id">
                                    <option value="">Select solicitor</option>
                                    @foreach($__receiptModalSolicitors as $aplist)
                                        <option value="{{$aplist->id}}">{{@$aplist->first_name}} {{@$aplist->last_name}}@if(!empty($aplist->email)) ({{@$aplist->email}})@endif</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

						<div class="col-12 col-md-12 col-lg-12">
							<div class="form-group">
                                <table border="1" style="margin-bottom:0rem !important;" class="table text_wrap table-striped table-hover table-md vertical_align">
                                    <thead>
                                        <tr>
                                            <th style="width:15%;color: #34395e;">Trans. Date</th>
                                            <th style="width:15%;color: #34395e;">Entry Date</th>
                                            <th style="width:15%;color: #34395e;">Trans. No</th>
                                            <th style="width:5%;color: #34395e;">Payment Method</th>
                                            <th style="width:35%;color: #34395e;">Description</th>
                                            <th style="width:14%;color: #34395e;">Deposit</th>
                                            <th style="width:1%;color: #34395e;"></th>
                                        </tr>
                                    </thead>
                                    <tbody class="productitem">
                                        <tr class="clonedrow">
                                            <td>
                                                <input data-valid="required"  class="form-control report_date_fields" name="trans_date[]" type="text" value="" />
                                            </td>
                                            <td>
                                                <input data-valid="required" class="form-control report_entry_date_fields" name="entry_date[]" type="text" value="" />
                                            </td>
                                            <td>
                                                <input class="form-control unique_trans_no" type="text" value="" readonly/>
                                                <input class="unique_trans_no_hidden" name="trans_no[]" type="hidden" value="" />
                                            </td>
                                            <td>
                                                <select class="form-control" name="payment_method[]">
                                                    <option value="">Select</option>
                                                    <option value="Cash">Cash</option>
                                                    <option value="Bank tansfer">Bank tansfer</option>
                                                    <option value="EFTPOS">EFTPOS</option>
                                                </select>
                                            </td>
                                            <td>
                                                <input data-valid="required" class="form-control" name="description[]" type="text" value="" />
                                            </td>

                                            <td>
                                                <span class="currencyinput" style="display: inline-block;color: #34395e;">$</span>
                                                <input data-valid="required" style="display: inline-block;" class="form-control deposit_amount_per_row" name="deposit_amount[]" type="text" value="" />
                                            </td>

                                            <td>
                                                <a class="removeitems" href="javascript:;"><i class="fa-solid fa-xmark"></i></a>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>

                                <table border="1" class="table text_wrap table-striped table-hover table-md vertical_align">
                                    <tbody>
                                        <tr>
                                            <td colspan="5" style="width:83.6%;text-align:right;color: #34395e;">Totals</td>
                                            <td colspan="2">
                                                <span class="total_deposit_amount_all_rows" style="color: #34395e;"></span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
						</div>

                        <div class="col-3 col-md-3 col-lg-3">
                            <a href="javascript:;" class="openproductrinfo"><i class="fa-solid fa-plus"></i> Add New Line</a>
                        </div>

						<div class="col-9 col-md-9 col-lg-9 text-right">

                            <div class="upload_client_receipt_document" style="display:inline-block;">
                                <input type="hidden" name="type" value="client">
                                <input type="hidden" name="doctype" value="client_receipt">
                                <a href="javascript:;" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Add Document</a>
                                <input class="docclientreceiptupload" type="file" name="document_upload[]"/>
                            </div>

                            <button onclick="customValidate('create_client_receipt')" type="button" class="btn btn-primary" style="margin:0px !important;">Save Entry</button>
							<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
						</div>
                    </div>
				</form>

			</div>
		</div>
	</div>
</div>

{{-- 4. Create Invoice Receipt Modal --}}
<!-- Create Invoice Receipt Modal -->
<div class="modal fade custom_modal" id="createinvoicereceiptmodal" tabindex="-1" role="dialog" aria-labelledby="create_noteModalLabel" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="appliationModalLabel">Create Invoice</h5>
				<button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body">
                <input type="hidden"  id="invoice_top_value_db" value="">
				<form method="post" action="{{URL::to('/clients/saveinvoicereport')}}" name="create_invoice_receipt" autocomplete="off" id="create_invoice_receipt" >
				@csrf
				<input type="hidden" name="client_id" value="{{$fetchedData->id}}">
                <input type="hidden" name="loggedin_staffid" value="{{@Auth::user()->id}}">
                <input type="hidden" name="receipt_type" value="3">
                <input type="hidden" name="receipt_id" id="update_draft_invoice_receipt_id" value="">
                <input type="hidden" name="function_type" id="update_draft_invoice_function_type" value="">

					<div class="row">
						<div class="col-6 col-md-6 col-lg-6">
							<div class="form-group">
								<label for="client">Client <span class="span_req">*</span></label>
								<input type="text" name="client" class="form-control" data-valid="required" autocomplete="off" placeholder="">
								<span class="custom-error title_error" role="alert">
									<strong></strong>
								</span>
							</div>
						</div>

                        <div class="col-6 col-md-6 col-lg-6">
                            <div class="form-group">
                                <label for="sel_invoice_agent_id">Solicitor <span class="span_req">*</span></label>
                                <select data-valid="required" class="form-control crm-ts-plain" name="agent_id" id="sel_invoice_agent_id">
                                    <option value="">Select solicitor</option>
                                    @foreach($__receiptModalSolicitors as $aplist)
                                        <option value="{{$aplist->id}}">{{@$aplist->first_name}} {{@$aplist->last_name}}@if(!empty($aplist->email)) ({{@$aplist->email}})@endif</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

						<div class="col-12 col-md-12 col-lg-12">
                            <div class="Invoic_no_cls" style="text-align: center;">
                                <b>Invoice No -
                                    <span class="unique_invoice_no"></span>
                                </b>
                                <input type="hidden" name="invoice_no" class="invoice_no" value="">
                            </div>
							<div class="form-group">
                                <table border="1" style="margin-bottom:0rem !important;" class="table text_wrap table-striped table-hover table-md vertical_align">
                                    <thead>
                                        <tr>
                                            <th style="width:15%;color: #34395e;" title="Date shown on the tax invoice">Invoice Date</th>
                                            <th style="width:15%;color: #34395e;" title="Date this entry was posted in the system">Date Recorded</th>
                                            <th style="width:15%;color: #34395e;">Trans. No</th>
                                            <th style="width:13%;color: #34395e;" title="Is GST included in the amount?">GST Included</th>
                                            <th style="width:14%;color: #34395e;" title="Type of charge being invoiced">Charge Type</th>
                                            <th style="width:25%;color: #34395e;">Description</th>
                                            <th style="width:14%;color: #34395e;">Amount</th>
                                            <th style="width:1%;color: #34395e;"></th>
                                        </tr>
                                    </thead>
                                    <tbody class="productitem_invoice">
                                        <tr class="clonedrow_invoice">
                                            <td>
                                                <input name="id[]" type="hidden" value="" />
                                                <input data-valid="required" class="form-control report_date_fields_invoice" name="trans_date[]" type="text" value="" title="Date shown on the tax invoice" />
                                            </td>
                                            <td>
                                                <input data-valid="required" class="form-control report_entry_date_fields_invoice" name="entry_date[]" type="text" value="" title="Date this entry was posted in the system" />
                                            </td>
                                            <td>
                                                <input class="form-control unique_trans_no_invoice" type="text" value="" readonly/>
                                                <input class="unique_trans_no_hidden_invoice" name="trans_no[]" type="hidden" value="" />
                                            </td>
                                            <td>
                                                <select class="form-control" name="gst_included[]">
                                                    <option value="">Select</option>
                                                    <option value="Yes">Yes</option>
                                                    <option value="No">No</option>
                                                </select>
                                            </td>

                                            <td>
                                                <select class="form-control payment_type_invoice_per_row" name="payment_type[]">
                                                    <option value="">Select</option>
                                                    @foreach(\App\Support\InvoiceChargeTypes::options() as $chargeType)
                                                        <option value="{{ $chargeType }}">{{ $chargeType }}</option>
                                                    @endforeach
                                                </select>
                                            </td>
                                            <td>
                                                <textarea data-valid="required" class="form-control invoice-line-description" name="description[]" rows="3"></textarea>
                                            </td>

                                            <td>
                                                <span class="currencyinput" style="display: inline-block;color: #34395e;">$</span>
                                                <input data-valid="required" style="display: inline-block;" class="form-control withdraw_amount_invoice_per_row" name="withdraw_amount[]" type="text" oninput="this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1').replace(/(\.\d{2}).*/g, '$1')" value="" />
                                            </td>

                                            <td>
                                                <a class="removeitems_invoice" href="javascript:;"><i class="fa-solid fa-xmark"></i></a>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>

                                <table border="1" class="table text_wrap table-striped table-hover table-md vertical_align">
                                    <tbody>
                                        <tr>
                                            <td colspan="5" style="width:83.6%;text-align:right;color: #34395e;">Totals</td>
                                            <td colspan="2">
                                                <span class="total_withdraw_amount_all_rows_invoice" style="color: #34395e;"></span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
						</div>

                        <div class="col-3 col-md-3 col-lg-3">
                            <a href="javascript:;" class="openproductrinfo_invoice"><i class="fa-solid fa-plus"></i> Add New Line</a>
                        </div>

						<div class="col-9 col-md-9 col-lg-9 text-right">
                            <input type="hidden" name="save_type" class="save_type" value="">
                            <button onclick="customValidate('create_invoice_receipt','draft')" type="button" class="btn btn-primary" style="margin:0px !important;">Draft Invoice</button>
							<button onclick="customValidate('create_invoice_receipt','final')" type="button" class="btn btn-primary" style="margin:0px !important;">Create Invoice</button>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
						</div>
                    </div>
				</form>
			</div>
		</div>
	</div>
</div>

{{-- 5. Create Office Receipt Modal --}}
<!-- Create Office Receipt Modal -->
<div class="modal fade custom_modal" id="createofficereceiptmodal" tabindex="-1" role="dialog" aria-labelledby="create_noteModalLabel" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="appliationModalLabel">Create Office Receipt</h5>
				<button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body">
                <input type="hidden"  id="office_top_value_db" value="">
				<form method="post" action="{{URL::to('/clients/saveofficereport')}}" name="create_office_receipt" autocomplete="off" id="create_office_receipt" >
				@csrf
				<input type="hidden" name="client_id" value="{{$fetchedData->id}}">
                <input type="hidden" name="loggedin_staffid" value="{{@Auth::user()->id}}">
                <input type="hidden" name="receipt_type" value="2">
					<div class="row">
						<div class="col-6 col-md-6 col-lg-6">
							<div class="form-group">
								<label for="client">Client <span class="span_req">*</span></label>
								<input type="text" name="client" class="form-control" data-valid="required" autocomplete="off" placeholder="">
								<span class="custom-error title_error" role="alert">
									<strong></strong>
								</span>
							</div>
						</div>

                        <div class="col-6 col-md-6 col-lg-6">
                            <div class="form-group">
                                <label for="sel_office_agent_id">Solicitor <span class="span_req">*</span></label>
                                <select data-valid="required" class="form-control crm-ts-plain" name="agent_id" id="sel_office_agent_id">
                                    <option value="">Select solicitor</option>
                                    @foreach($__receiptModalSolicitors as $aplist)
                                        <option value="{{$aplist->id}}">{{@$aplist->first_name}} {{@$aplist->last_name}}@if(!empty($aplist->email)) ({{@$aplist->email}})@endif</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

						<div class="col-12 col-md-12 col-lg-12">
							<div class="form-group">
                                <table border="1" style="margin-bottom:0rem !important;" class="table text_wrap table-striped table-hover table-md vertical_align">
                                    <thead>
                                        <tr>
                                            <th style="width:15%;color: #34395e;">Trans. Date</th>
                                            <th style="width:15%;color: #34395e;">Entry Date</th>
                                            <th style="width:15%;color: #34395e;">Receipt No</th>
                                            <th style="width:15%;color: #34395e;" title="Invoice number this receipt is linked to (if any)">Invoice Ref. No.</th>
                                            <th style="width:5%;color: #34395e;">Payment method</th>
                                            <th style="width:25%;color: #34395e;">Description</th>
                                            <th style="width:14%;color: #34395e;" title="Amount received into office account">Amount Received</th>
                                            <th style="width:1%;color: #34395e;"></th>
                                        </tr>
                                    </thead>
                                    <tbody class="productitem_office">
                                        <tr class="clonedrow_office">
                                            <td>
                                                <input data-valid="required"  class="form-control report_date_fields_office" name="trans_date[]" type="text" value="" />
                                            </td>
                                            <td>
                                                <input data-valid="required" class="form-control report_entry_date_fields_office" name="entry_date[]" type="text" value="" />
                                            </td>
                                            <td>
                                                <input class="form-control unique_trans_no_office" type="text" value="" readonly/>
                                                <input class="unique_trans_no_hidden_office" name="trans_no[]" type="hidden" value="" />
                                            </td>
                                            <td>
                                                <select class="form-control invoice_no_cls"  name="invoice_no[]">
                                                </select>
                                            </td>
                                            <td>
                                                <select class="form-control" name="payment_method[]" data-valid="required" >
                                                    <option value="">Select</option>
                                                    <option value="Cash">Cash</option>
                                                    <option value="Bank tansfer">Bank tansfer</option>
                                                    <option value="EFTPOS">EFTPOS</option>
                                                </select>
                                            </td>
                                            <td>
                                                <input data-valid="required" class="form-control" name="description[]" type="text" value="" />
                                            </td>

                                            <td>
                                                <span class="currencyinput" style="display: inline-block;color: #34395e;">$</span>
                                                <input data-valid="required" style="display: inline-block;" class="form-control total_withdrawal_amount_office" name="withdraw_amount[]" type="text" value="" />
                                            </td>

                                            <td>
                                                <a class="removeitems_office" href="javascript:;"><i class="fa-solid fa-xmark"></i></a>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>

                                <table border="1" class="table text_wrap table-striped table-hover table-md vertical_align">
                                    <tbody>
                                        <tr>
                                            <td colspan="5" style="width:83.6%;text-align:right;color: #34395e;">Totals</td>
                                            <td colspan="2">
                                                <span class="total_withdraw_amount_all_rows_office" style="color: #34395e;"></span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
						</div>

                        <div class="col-3 col-md-3 col-lg-3">
                            <a href="javascript:;" class="openproductrinfo_office"><i class="fa-solid fa-plus"></i> Add New Line</a>
                        </div>

						<div class="col-9 col-md-9 col-lg-9 text-right" style="display: flex; align-items: center; justify-content: flex-end; gap: 10px; flex-wrap: wrap;">
                            <div class="upload_office_receipt_document" style="display:inline-block;">
                                <input type="hidden" name="type" value="client">
                                <input type="hidden" name="doctype" value="office_receipt">
                                
                                <!-- NEW: Drag and Drop Zone -->
                                <div class="ledger-drag-drop-zone office-drag-drop-zone" id="officeDragDropZone2">
                                    <div class="drag-zone-inner">
                                        <i class="fa-solid fa-cloud-arrow-up"></i>
                                        <div class="drag-zone-content">
                                            <p class="drag-zone-text">Drag files here or <strong>click to browse</strong></p>
                                            <small class="drag-zone-formats">Accepted: PDF, JPG, PNG, DOC, DOCX (Multiple files allowed)</small>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Keep existing file input (hidden, used as fallback) -->
                                <input class="docofficereceiptupload d-none" type="file" name="document_upload[]" multiple style="display: none;">
                                
                                <!-- File selection display (shown after files are selected) -->
                                <div id="office-selected-files-display2" class="ledger-selected-files-display" style="display: none;">
                                    <div id="office-files-list2" class="files-list"></div>
                                    <button type="button" class="btn btn-sm btn-link text-danger remove-all-files-office" title="Remove all files">
                                        <i class="fa-solid fa-xmark"></i> Clear All
                                    </button>
                                </div>
                            </div>

                            <button onclick="customValidate('create_office_receipt')" type="button" class="btn btn-primary" style="margin: 0px !important;">Save Entry</button>
							<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
						</div>
                    </div>
				</form>

			</div>
		</div>
	</div>
</div>

{{-- 6. Create Journal Modal --}}
<!-- Create Journal Modal -->
<div class="modal fade custom_modal" id="createjournalreceiptmodal" tabindex="-1" role="dialog" aria-labelledby="create_noteModalLabel" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="appliationModalLabel">Create Journal</h5>
				<button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body">
                <input type="hidden"  id="journal_top_value_db" value="">
				<form method="post" action="{{URL::to('/clients/savejournalreport')}}" name="create_journal_receipt" autocomplete="off" id="create_journal_receipt" >
				@csrf
				<input type="hidden" name="client_id" value="{{$fetchedData->id}}">
                <input type="hidden" name="loggedin_staffid" value="{{@Auth::user()->id}}">
                <input type="hidden" name="receipt_type" value="4">
					<div class="row">
						<div class="col-6 col-md-6 col-lg-6">
							<div class="form-group">
								<label for="client">Client <span class="span_req">*</span></label>
								<input type="text" name="client" class="form-control" data-valid="required" autocomplete="off" placeholder="">
								<span class="custom-error title_error" role="alert">
									<strong></strong>
								</span>
							</div>
						</div>

                        <div class="col-6 col-md-6 col-lg-6">
                            <div class="form-group">
                                <label for="sel_journal_agent_id">Solicitor <span class="span_req">*</span></label>
                                <select data-valid="required" class="form-control crm-ts-plain" name="agent_id" id="sel_journal_agent_id">
                                    <option value="">Select solicitor</option>
                                    @foreach($__receiptModalSolicitors as $aplist)
                                        <option value="{{$aplist->id}}">{{@$aplist->first_name}} {{@$aplist->last_name}}@if(!empty($aplist->email)) ({{@$aplist->email}})@endif</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

						<div class="col-12 col-md-12 col-lg-12">
							<div class="form-group">
                                <table border="1" style="margin-bottom:0rem !important;" class="table text_wrap table-striped table-hover table-md vertical_align">
                                    <thead>
                                        <tr>
                                            <th style="width:15%;color: #34395e;">Trans. Date</th>
                                            <th style="width:15%;color: #34395e;">Entry Date</th>
                                            <th style="width:12%;color: #34395e;">Trans. No</th>
                                            <th style="width:13%;color: #34395e;">Invoice No</th>
                                            <th style="width:25%;color: #34395e;">Description</th>
                                            <th style="width:15%;color: #34395e;">Transfer</th>
											<th style="width:1%;color: #34395e;"></th>
                                        </tr>
                                    </thead>
                                    <tbody class="productitem_journal">
                                        <tr class="clonedrow_journal">
                                            <td>
                                                <input data-valid="required"  class="form-control report_date_fields_journal" name="trans_date[]" type="text" value="" />
                                            </td>
                                            <td>
                                                <input data-valid="required" class="form-control report_entry_date_fields_journal" name="entry_date[]" type="text" value="" />
                                            </td>
                                            <td>
                                                <input class="form-control unique_trans_no_journal" type="text" value="" readonly/>
                                                <input class="unique_trans_no_hidden_journal" name="trans_no[]" type="hidden" value="" />
                                            </td>

                                            <td>
                                                <select data-valid="required" class="form-control invoice_no_cls"  name="invoice_no[]">
                                                </select>
                                            </td>

                                            <td>
                                                <input data-valid="required" class="form-control" name="description[]" type="text" value="" />
                                            </td>

                                            <td>
                                                <span class="currencyinput" style="display: inline-block;color: #34395e;">$</span>
                                                <input data-valid="required" style="display: inline-block;" class="form-control total_withdrawal_amount_journal" name="withdraw_amount[]" type="text" value="" />
                                            </td>

					                        <td>
                                                <a class="removeitems_journal" href="javascript:;"><i class="fa-solid fa-xmark"></i></a>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>

                                <table border="1" class="table text_wrap table-striped table-hover table-md vertical_align">
                                    <tbody>
                                        <tr>
                                            <td colspan="5" style="width:48.99%;text-align:right;color: #34395e;">Totals</td>
                                            <td colspan="2" style="width:10.99%;">
                                                <span class="total_withdraw_amount_all_rows_journal" style="color: #34395e;"></span>
                                            </td>
										</tr>
                                    </tbody>
                                </table>
                            </div>
						</div>

                        <div class="col-3 col-md-3 col-lg-3">
                            <a href="javascript:;" class="openproductrinfo_journal"><i class="fa-solid fa-plus"></i> Add New Line</a>
                        </div>

						<div class="col-9 col-md-9 col-lg-9 text-right">

                            <div class="upload_journal_receipt_document" style="display:inline-block;">
                                <input type="hidden" name="type" value="client">
                                <input type="hidden" name="doctype" value="journal_receipt">
                                <a href="javascript:;" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Add Document</a>

                                <input class="docjournalreceiptupload" type="file" name="document_upload[]"/>
                            </div>

                            <button onclick="customValidate('create_journal_receipt')" type="button" class="btn btn-primary" style="margin:0px !important;">Save Entry</button>
						<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
					</div>
                    </div>
			</form>
            </div>
		</div>
	</div>
</div>

<script>
(function () {
    'use strict';

    /**
     * Agent dropdowns inside receipt / invoice / office / journal modals.
     * Static <select> options rendered server-side; use plain single Tom Select
     * with dropdownParent set to the modal element so the dropdown renders correctly.
     */
    var agentModalMap = [
        { modal: '#createclientreceiptmodal',  select: '#sel_client_agent_id'  },
        { modal: '#createinvoicereceiptmodal', select: '#sel_invoice_agent_id' },
        { modal: '#createofficereceiptmodal',  select: '#sel_office_agent_id'  },
        { modal: '#createjournalreceiptmodal', select: '#sel_journal_agent_id' }
    ];

    agentModalMap.forEach(function (entry) {
        jQuery(document).on('shown.bs.modal', entry.modal, function () {
            var modalEl = this;
            var sel = modalEl.querySelector(entry.select);
            if (!sel) return;
            if (typeof destroyTS === 'function') destroyTS(sel);
            if (typeof initTS === 'function' && typeof buildPlainSingleTomSelectConfig === 'function') {
                initTS(sel, buildPlainSingleTomSelectConfig({ dropdownParent: modalEl }));
            }
        });

        jQuery(document).on('hidden.bs.modal', entry.modal, function () {
            var sel = this.querySelector(entry.select);
            if (sel && typeof destroyTS === 'function') destroyTS(sel);
        });
    });
}());
</script>

