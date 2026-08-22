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
                    <p class="text-muted small">Amounts and dates are locked after posting. Super-admin can delete a receipt from the receipt list if a correction is needed. Edit description, payment method, and attachments below.</p>
                    <div class="form-group">
                        <label for="trans_date">Transaction Date</label>
                        <input type="text" class="form-control bg-light" name="trans_date" id="edit_ledger_trans_date" readonly>
                    </div>
                    <div class="form-group">
                        <label for="entry_date">Entry Date</label>
                        <input type="text" class="form-control bg-light" name="entry_date" id="edit_ledger_entry_date" readonly>
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
                            <option value="Bank transfer">Bank Transfer / EFT</option>
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
                        <label for="deposit_amount">Funds In (+) <span class="text-muted" style="font-weight:normal;font-size:12px;">(locked)</span></label>
                        <input type="number" class="form-control bg-light" name="deposit_amount" step="0.01" value="0.00" readonly>
                    </div>
                    <div class="form-group">
                        <label for="withdraw_amount">Funds Out (-) <span class="text-muted" style="font-weight:normal;font-size:12px;">(locked)</span></label>
                        <input type="number" class="form-control bg-light" name="withdraw_amount" step="0.01" value="0.00" readonly>
                    </div>

            </div>
            <div class="modal-footer">
                <div class="upload_client_receipt_document" style="display:inline-block;">
                    <input type="hidden" name="type" value="client">
                    <input type="hidden" name="doctype" value="client_receipt">
                    <span class="file-selection-hint" style="margin-left: 10px; color: #34395e;"></span>
                    <a href="javascript:;" class="btn btn-primary add-document-btn"><i class="fa-solid fa-plus"></i> Add Document</a>
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
                <h5 class="modal-title" id="editOfficeReceiptModalLabel"><i class="fa-solid fa-hand-holding-dollar"></i> Edit Direct Office Receipt</h5>
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
                                <a href="javascript:;" class="btn btn-info add-document-btn-edit"><i class="fa-solid fa-plus"></i> Add/Update Document</a>
                                <input class="docofficereceiptupload_edit" type="file" name="document_upload[]"/>
                            </div>
                            <div id="current_document_display" class="mt-2"></div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-secondary" id="updateOfficeReceiptDraftBtn"><i class="fa-solid fa-floppy-disk"></i> Save as Draft</button>
                <button type="button" class="btn btn-primary" id="updateOfficeReceiptFinalBtn"><i class="fa-solid fa-check"></i> Save and Finalize</button>
            </div>
        </div>
    </div>
</div>
