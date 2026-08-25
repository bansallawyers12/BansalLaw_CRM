<?php use Illuminate\Support\Facades\Storage; ?>
@php
    $accountTab = $accountTabData ?? [
        'clientMatterId' => $activeClientMatterId ?? null,
        'trustBalance' => 0.0,
        'outstandingBalance' => 0.0,
        'invoicedTotal' => 0.0,
        'costsDisclosure' => null,
        'exceedsDisclosure' => false,
        'trustRows' => collect(),
        'invoiceRows' => collect(),
        'officeRows' => collect(),
        'documentsById' => collect(),
    ];
    $client_selected_matter_id = $accountTab['clientMatterId'];
    $trustBalance = $accountTab['trustBalance'];
    $outstandingBalance = $accountTab['outstandingBalance'];
    $invoicedTotal = $accountTab['invoicedTotal'] ?? 0.0;
    $costsDisclosure = $accountTab['costsDisclosure'] ?? null;
    $exceedsDisclosure = ! empty($accountTab['exceedsDisclosure']);
    $receipts_lists = $accountTab['trustRows'];
    $receipts_lists_invoice = $accountTab['invoiceRows'];
    $receipts_lists_office = $accountTab['officeRows'];
    $accountDocumentsById = $accountTab['documentsById'];
    $outstandingIsZero = round((float) $outstandingBalance, 2) == 0.0;
@endphp

<textarea id="account-costs-disclosure-data" hidden readonly>@json($costsDisclosure)</textarea>

<div class="card full-width account-tab-card">
    <div class="account-actions-bar">
        <a class="btn account-action-btn account-action-btn--trust createreceipt" href="javascript:;" role="button" data-account-entry="true" data-receipt-type="1" title="Record trust money received, paid or transferred">
            <i class="fa-solid fa-building-columns"></i> Trust Account Entry
        </a>
        <a class="btn account-action-btn account-action-btn--office createreceipt" href="javascript:;" role="button" data-account-entry="true" data-receipt-type="2" title="Record money received directly into the office account">
            <i class="fa-solid fa-hand-holding-dollar"></i> Office Receipt
        </a>
        <a class="btn account-action-btn account-action-btn--invoice createreceipt" href="javascript:;" role="button" data-account-entry="true" data-receipt-type="3" title="Issue a tax invoice to the client">
            <i class="fa-solid fa-file-invoice-dollar"></i> Invoice
        </a>
    </div>

    <section class="account-costs-summary" aria-label="Matter costs summary">
        <div class="account-costs-summary__header">
            <h3 class="account-costs-summary__title">
                <i class="fa-solid fa-scale-balanced"></i> Matter Costs
            </h3>
            @if($costsDisclosure)
                <a href="javascript:;" class="account-costs-summary__link" id="account-view-disclosure-link" title="Open costs disclosure in Legal forms">
                    View {{ $costsDisclosure['formTypeLabel'] }}
                    @if(!empty($costsDisclosure['formDate']))
                        <span class="account-costs-summary__link-date">({{ $costsDisclosure['formDate'] }})</span>
                    @endif
                </a>
            @endif
        </div>
        <div class="account-costs-summary__grid">
            <div class="account-costs-metric account-costs-metric--estimate">
                <div class="account-costs-metric__label">Disclosed <span class="account-costs-metric__hint">(estimate)</span></div>
                <div class="account-costs-metric__value">
                    @if($costsDisclosure)
                        {{ '$ ' . number_format((float) $costsDisclosure['estimatedTotal'], 2) }}
                    @else
                        <span class="account-costs-metric__empty">No disclosure</span>
                    @endif
                </div>
            </div>
            <div class="account-costs-metric">
                <div class="account-costs-metric__label">Invoiced</div>
                <div class="account-costs-metric__value">{{ '$ ' . number_format($invoicedTotal, 2) }}</div>
            </div>
            <div class="account-costs-metric account-costs-metric--outstanding{{ $outstandingIsZero ? ' account-costs-metric--zero' : '' }}">
                <div class="account-costs-metric__label">Outstanding</div>
                <div class="account-costs-metric__value">{{ '$ ' . number_format($outstandingBalance, 2) }}</div>
            </div>
            <div class="account-costs-metric account-costs-metric--trust">
                <div class="account-costs-metric__label">Trust held</div>
                <div class="account-costs-metric__value">{{ '$ ' . number_format($trustBalance, 2) }}</div>
            </div>
        </div>
        @if($exceedsDisclosure)
            <div class="account-costs-summary__warning" role="status">
                <i class="fa-solid fa-triangle-exclamation"></i>
                Invoiced amount exceeds the costs disclosure estimate. Consider issuing an updated disclosure.
            </div>
        @endif
        @if($costsDisclosure)
            <div class="account-costs-summary__actions">
                <button type="button" class="btn btn-sm btn-outline-primary account-costs-action-btn" id="account-invoice-from-disclosure" title="Open invoice form with lines from the costs disclosure">
                    <i class="fa-solid fa-file-invoice-dollar"></i> Invoice from disclosure
                </button>
                @if(!empty($costsDisclosure['retainerAmount']) && (float) $costsDisclosure['retainerAmount'] > 0)
                    <button type="button" class="btn btn-sm btn-outline-success account-costs-action-btn" id="account-retainer-from-disclosure" title="Open trust receipt with retainer amount from disclosure">
                        <i class="fa-solid fa-building-columns"></i> Record retainer ({{ '$ ' . number_format((float) $costsDisclosure['retainerAmount'], 2) }})
                    </button>
                @endif
            </div>
        @elseif(empty($receipts_lists) || count($receipts_lists) === 0)
            <p class="account-costs-summary__empty-hint">No costs disclosure for this matter yet. Create one under <strong>Legal forms</strong>, then issue invoices or record trust receipts here.</p>
        @endif
    </section>

    <div class="account-layout">
        <!-- Trust Account Ledger Section -->
        <section class="account-section client-account">
            <div class="account-section-header">
                <h2><i class="fa-solid fa-building-columns account-section-icon account-section-icon--trust"></i> Trust Account Ledger</h2>
                <div class="balance-display">
                    <div class="balance-label">Trust Balance</div>
                    <div class="balance-amount funds-held current-funds-held">
                        {{ '$ ' . number_format($trustBalance, 2) }}
                    </div>
                </div>
            </div>
            <div class="transaction-table-wrapper">
                <table class="transaction-table" id="client-ledger-table">
                    <thead>
                        <tr>
                            <th style="text-align: left;">Trans. Date</th>
                            <th style="text-align: left;">Transaction Type</th>
                            <th style="text-align: left;">Method</th>
                            <th style="text-align: left;">Particulars / Description</th>
                            <th style="text-align: center;">Receipt No.</th>
                            <th style="text-align: right;">Trust Receipts (+)</th>
                            <th style="text-align: right;">Trust Payments (−)</th>
                            <th style="text-align: right; background: #e8f5e9;">Balance</th>
                        </tr>
                    </thead>
                    <tbody class="productitemList">
                        <?php
                        $type_label_map = [
                            'Deposit'      => 'Trust Receipt',
                            'Fee Transfer' => 'Transfer to Office Account',
                            'Disbursement' => 'Disbursement (Trust Payment)',
                            'Refund'       => 'Refund to Client',
                        ];
                        if(!empty($receipts_lists) && count($receipts_lists)>0 )
                        {
                            foreach($receipts_lists as $rec_list=>$rec_val)
                            {
                            $rowClass = !empty($rec_val->is_voided_for_balance) ? 'strike-through' : '';
                            $trust_running_balance = $rec_val->running_balance ?? 0;
                            ?>
                        <tr class="drow_account_ledger ledger-row {{$rowClass}}" data-type="{{$rec_val->client_fund_ledger_type}}" data-matterid="{{$rec_val->client_matter_id}}" data-trans-date="<?php echo htmlspecialchars($rec_val->trans_date, ENT_QUOTES, 'UTF-8'); ?>">
                            <td style="text-align: left; vertical-align: middle;">
                                <span style="display: inline-flex; align-items: center;">
                                    <?php
                                    if( isset($rec_val->validate_receipt) && $rec_val->validate_receipt == '1' )
                                    { ?>
                                        <i class="fa-solid fa-circle-check" title="Verified Receipt" style="margin-right: 5px; color: #28a745;"></i>
                                    <?php
                                    } ?>
                                    <?php echo $rec_val->trans_date;?>
                                </span>
                            </td>

                            <td class="type-cell" style="text-align: left; vertical-align: middle;">
                                <?php
                                $dbType = $rec_val->client_fund_ledger_type;
                                $type_label = $type_label_map[$dbType] ?? $dbType;
                                if($dbType == 'Deposit' ){
                                    $type_icon = 'fa-arrow-circle-down';
                                    $type_color = '#28a745';
                                } else if($dbType == 'Fee Transfer' ){
                                    $type_icon = 'fa-right-left';
                                    $type_color = '#fd7e14';
                                } else if($dbType == 'Disbursement' ){
                                    $type_icon = 'fa-arrow-circle-up';
                                    $type_color = '#dc3545';
                                } else if($dbType == 'Refund' ){
                                    $type_icon = 'fa-arrow-rotate-left';
                                    $type_color = '#6f42c1';
                                } else {
                                    $type_icon = 'fa-arrow-circle-up';
                                    $type_color = '#6c757d';
                                }?>
                                <i class="fa-solid {{$type_icon}} type-icon" title="{{$type_label}}" style="color: {{$type_color}};"></i>
                                <span>
                                    <strong style="font-size: 0.9em;">{{$type_label}}</strong>
                                    <?php if (!empty($rec_val->reversal_of_entry_id)) { ?>
                                        <br/><small class="text-muted">Reversal</small>
                                    <?php } elseif (!empty($rec_val->voided_at) || (int) ($rec_val->void_fee_transfer ?? 0) === 1) { ?>
                                        <br/><small class="text-muted">Reversed</small>
                                    <?php } ?>
                                    <br/>
                                    {!! !empty($rec_val->invoice_no) ? '<small style="color:#6c757d;">('.$rec_val->invoice_no.')</small>' : '' !!}
                                </span>
                                
                                <?php
                                if(isset($rec_val->uploaded_doc_id) && $rec_val->uploaded_doc_id != ""){
                                    $client_doc = $accountDocumentsById->get((int) $rec_val->uploaded_doc_id);
                                    if($client_doc){
                                        $docUrl = url('/documents/preview/' . $rec_val->uploaded_doc_id);
                                        ?>
                                        <a target="_blank" title="See Attached Document" class="link-primary" href="<?php echo $docUrl;?>"><i class="fa-solid fa-file-pdf"></i></a>
                                    <?php
                                    }
                                } ?>
                            </td>
                            <td style="text-align: left; vertical-align: middle; font-size: 0.9em; color: #495057;"><?php
                                $methodHtml = !empty($rec_val->payment_method) ? htmlspecialchars($rec_val->payment_method, ENT_QUOTES, 'UTF-8') : '—';
                                if (!empty($rec_val->eftpos_surcharge_amount) && floatval($rec_val->eftpos_surcharge_amount) > 0) {
                                    $methodHtml .= '<br/><span style="font-size:11px;color:#6c757d;">+$' . number_format((float) $rec_val->eftpos_surcharge_amount, 2) . ' surcharge</span>';
                                }
                                echo $methodHtml;
                            ?></td>

                            <td class="description" style="text-align: left; vertical-align: middle;"><?php echo $rec_val->description;?></td>

                            <td style="text-align: center; vertical-align: middle;">
                                <div class="dropdown d-inline-block">
                                    <span class="reference-dropdown-trigger dropdown-toggle" id="dropdownReceipt{{$rec_val->id}}" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false" style="cursor: pointer;">
                                        <?php echo $rec_val->trans_no;?>
                                        <i class="fa-solid fa-caret-down" style="font-size: 11px; opacity: 0.6; margin-left: 3px;"></i>
                                    </span>
                                    <div class="dropdown-menu" aria-labelledby="dropdownReceipt{{$rec_val->id}}">
                                        <a class="dropdown-item" href="{{URL::to('/clients/genClientFundReceipt')}}/{{$rec_val->id}}" target="_blank">
                                            <i class="fa-solid fa-eye"></i> View Receipt
                                        </a>
                                        <a class="dropdown-item" href="{{URL::to('/clients/genClientFundReceipt')}}/{{$rec_val->id}}?download=1">
                                            <i class="fa-solid fa-download"></i> Download PDF
                                        </a>
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item send-client-fund-receipt-to-client" href="javascript:;" data-receipt-id="<?php echo $rec_val->id; ?>" data-receipt-no="<?php echo $rec_val->trans_no; ?>">
                                            <i class="fa-solid fa-envelope"></i> Send to Client
                                        </a>
                                        <div class="dropdown-divider"></div>
                                        <?php if(!empty($rec_val->uploaded_doc_id)) {
                                            $uploadedDoc = $accountDocumentsById->get((int) $rec_val->uploaded_doc_id);
                                            if($uploadedDoc && !empty($uploadedDoc->myfile)) {
                                                $docUrl = url('/documents/preview/' . $uploadedDoc->id);
                                                ?>
                                        <a class="dropdown-item" href="<?php echo $docUrl; ?>" target="_blank">
                                            <i class="fa-solid fa-file-lines"></i> View Uploaded Receipt
                                        </a>
                                        <?php } } ?>
                                        <a class="dropdown-item upload-clientreceipt-doc" href="javascript:;" 
                                            data-receipt-id="<?php echo $rec_val->id; ?>" 
                                            data-client-id="<?php echo $fetchedData->id; ?>"
                                            data-matter-id="<?php echo $rec_val->client_matter_id; ?>">
                                            <i class="fa-solid fa-upload"></i> <?php echo !empty($rec_val->uploaded_doc_id) ? 'Replace' : 'Upload'; ?> Receipt Document
                                        </a>
                                        <?php if($rec_val->client_fund_ledger_type !== 'Fee Transfer'){ ?>
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item edit-ledger-entry" href="javascript:;"
                                            data-id="<?php echo $rec_val->id; ?>"
                                            data-receiptid="<?php echo $rec_val->receipt_id; ?>"
                                            data-trans-date="<?php echo htmlspecialchars($rec_val->trans_date, ENT_QUOTES, 'UTF-8'); ?>"
                                            data-entry-date="<?php echo htmlspecialchars($rec_val->entry_date, ENT_QUOTES, 'UTF-8'); ?>"
                                            data-type="<?php echo htmlspecialchars($rec_val->client_fund_ledger_type, ENT_QUOTES, 'UTF-8'); ?>"
                                            data-description="<?php echo htmlspecialchars($rec_val->description ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                            data-deposit="<?php echo htmlspecialchars($rec_val->deposit_amount ?? 0, ENT_QUOTES, 'UTF-8'); ?>"
                                            data-withdraw="<?php echo htmlspecialchars($rec_val->withdraw_amount ?? 0, ENT_QUOTES, 'UTF-8'); ?>"
                                            data-payment-method="<?php echo htmlspecialchars($rec_val->payment_method ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                            data-eftpos-surcharge="<?php echo htmlspecialchars($rec_val->eftpos_surcharge_amount ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                            <i class="fa-solid fa-pen-to-square"></i> Edit Entry
                                        </a>
                                        <?php } ?>
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item copy-reference" href="javascript:;" data-reference="<?php echo $rec_val->trans_no;?>">
                                            <i class="fa-solid fa-copy"></i> Copy Reference
                                        </a>
                                        
                                        <?php 
                                        // Quick Allocate button for Deposits (to allocate to invoices)
                                        if($rec_val->client_fund_ledger_type == 'Deposit') { 
                                            $isAllocated = !empty($rec_val->invoice_no);
                                        ?>
                                        <div class="dropdown-divider"></div>
                                        <?php if($isAllocated) { ?>
                                        <a class="dropdown-item" href="javascript:;" style="color: #28a745; cursor: default;" onclick="return false;">
                                            <i class="fa-solid fa-circle-check"></i> Already Allocated to <?php echo $rec_val->invoice_no; ?>
                                        </a>
                                        <a class="dropdown-item quick-allocate-ledger" href="javascript:;"
                                            data-receipt-id="<?php echo $rec_val->id; ?>"
                                            data-receipt-amount="<?php echo $rec_val->deposit_amount; ?>"
                                            data-matter-id="<?php echo $rec_val->client_matter_id; ?>"
                                            data-client-id="<?php echo $fetchedData->id; ?>"
                                            style="padding-left: 2rem;">
                                            <i class="fa-solid fa-rotate"></i> Re-allocate to Different Invoice
                                        </a>
                                        <?php } else { ?>
                                        <a class="dropdown-item quick-allocate-ledger" href="javascript:;"
                                            data-receipt-id="<?php echo $rec_val->id; ?>"
                                            data-receipt-amount="<?php echo $rec_val->deposit_amount; ?>"
                                            data-matter-id="<?php echo $rec_val->client_matter_id; ?>"
                                            data-client-id="<?php echo $fetchedData->id; ?>">
                                            <i class="fa-solid fa-magic"></i> Quick Allocate to Invoice
                                        </a>
                                        <?php } ?>
                                        <?php } ?>
                                    </div>
                                </div>
                            </td>

                            <td style="text-align: right; vertical-align: middle; color: #28a745; font-weight: 500;">
                                <?php echo !empty($rec_val->deposit_amount) ? '$ ' . number_format($rec_val->deposit_amount, 2) : ''; ?>
                            </td>
                            <td style="text-align: right; vertical-align: middle; color: #dc3545; font-weight: 500;">
                                <?php echo !empty($rec_val->withdraw_amount) ? '$ ' . number_format($rec_val->withdraw_amount, 2) : ''; ?>
                            </td>
                            <td style="text-align: right; vertical-align: middle; font-weight: 600; background: #f0fff4; border-left: 2px solid #c3e6cb;">
                                <?php
                                $balance_color = $trust_running_balance >= 0 ? '#155724' : '#721c24';
                                $balance_display = '$ ' . number_format(abs($trust_running_balance), 2);
                                if ($trust_running_balance < 0) $balance_display = '−' . $balance_display;
                                if (!empty($rec_val->is_voided_for_balance)) {
                                    echo '<span style="color:#6c757d;font-style:italic;">voided</span>';
                                } else {
                                    echo '<span style="color:' . $balance_color . ';">' . $balance_display . '</span>';
                                }
                                ?>
                            </td>
                        </tr>
                        <?php
                            } //end foreach
                        } else { ?>
                        <tr class="account-table-empty-row">
                            <td colspan="8" class="account-table-empty-cell">No trust entries yet. Use <strong>Trust Account Entry</strong> above to record money received, paid, or transferred.</td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Office Account Section -->
        <section class="account-section office-account">
            <div class="account-section-header">
                <h2><i class="fa-solid fa-building" style="color: #007bff;"></i> Office Account</h2>
                <div class="balance-display">
                    <div class="balance-label">Outstanding Balance</div>
                    <div class="balance-amount outstanding outstanding-balance{{ $outstandingIsZero ? ' outstanding-balance--zero' : '' }}">
                        {{ '$ ' . number_format($outstandingBalance, 2) }}

                        <?php if ($outstandingBalance < 0): ?>
                            <a class="link-primary adjustinvoice" href="javascript:;" title="Adjust Invoice">
                                <i class="fa-solid fa-pen"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="transaction-table-wrapper">
                <h4 style="margin-top:0; margin-bottom: 6px; font-weight: 600;"><i class="fa-solid fa-file-invoice-dollar" style="color: #007bff;"></i> Tax Invoices Issued</h4>
                <table class="transaction-table">
                    <thead>
                        <tr>
                            <th style="text-align: center;" title="Tax invoice number">Invoice No.</th>
                            <th style="text-align: left;" title="Date shown on the tax invoice">Invoice Date</th>
                            <th style="text-align: left;" title="Description of services rendered">Services / Description</th>
                            <th style="text-align: right;" title="Invoice amount (inc. GST where applicable)">Amount (incl. GST)</th>
                            <th style="text-align: left;" title="Payment status">Status</th>
                        </tr>
                    </thead>
                    <tbody class="productitemList_invoice">
                        <?php
                        $invoiceEditActor = auth()->guard('admin')->user();
                        $canEditFinalInvoices = $invoiceEditActor instanceof \App\Models\Staff
                            && $invoiceEditActor->canEditFinalInvoice();

                        if(!empty($receipts_lists_invoice) && count($receipts_lists_invoice)>0 )
                        {
                            foreach($receipts_lists_invoice as $inc_list=>$inc_val)
                            {
                                if($inc_val->void_invoice == 1 ) {
                                    $trcls = 'strike-through';
                                } else {
                                    $trcls = '';
                                }
                                
                                // Make unpaid/partial invoices drop zones for drag & drop allocation
                                $isDropZone = in_array($inc_val->invoice_status, [0, 2]) && $inc_val->void_invoice != 1;
                                $dropZoneClass = $isDropZone ? 'invoice-drop-zone' : '';
                                ?>
                                <tr class="drow_account_invoice invoiceTrRow <?php echo $trcls;?> <?php echo $dropZoneClass;?>" 
                                    id="invoiceTrRow_<?php echo $inc_val->id;?>" 
                                    data-matterid="{{$inc_val->client_matter_id}}"
                                    data-invoice-no="{{$inc_val->trans_no}}"
                                    data-invoice-balance="{{$inc_val->balance_amount}}"
                                    data-invoice-status="{{$inc_val->invoice_status}}">
                                        <td style="text-align: center; vertical-align: middle;">
                                            <div class="dropdown d-inline-block">
                                            <span class="reference-dropdown-trigger dropdown-toggle" id="dropdownInvoice{{$inc_val->id}}" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false" style="cursor: pointer;">
                                                <?php echo $inc_val->trans_no;?> <i class="fa-solid fa-caret-down" style="font-size: 11px; opacity: 0.6; margin-left: 3px;"></i>
                                            </span>
                                                <?php
                                                $invoiceSaveType = $inc_val->save_type ?? '';
                                                $canViewInvoicePdf = in_array($invoiceSaveType, ['final', 'draft'], true)
                                                    || $invoiceSaveType === ''; // legacy rows without save_type
                                                ?>
                                            <div class="dropdown-menu" aria-labelledby="dropdownInvoice{{$inc_val->id}}">
                                                <?php if($canViewInvoicePdf) { ?>
                                                <a class="dropdown-item" href="{{URL::to('/clients/genInvoice')}}/{{$inc_val->receipt_id}}/{{$fetchedData->id}}" target="_blank">
                                                    <i class="fa-solid fa-eye"></i> {{ $invoiceSaveType == 'draft' ? 'View Draft PDF' : 'View Invoice' }}
                                                </a>
                                                <a class="dropdown-item" href="{{URL::to('/clients/genInvoice')}}/{{$inc_val->receipt_id}}/{{$fetchedData->id}}?download=1">
                                                    <i class="fa-solid fa-download"></i> {{ $invoiceSaveType == 'draft' ? 'Download Draft PDF' : 'Download PDF' }}
                                                </a>
                                                <?php if($invoiceSaveType == 'final' || $invoiceSaveType === '') { ?>
                                                <div class="dropdown-divider"></div>
                                                <a class="dropdown-item send-invoice-to-client" href="javascript:;" data-invoice-id="<?php echo $inc_val->receipt_id; ?>" data-invoice-no="<?php echo $inc_val->trans_no; ?>">
                                                    <i class="fa-solid fa-envelope"></i> Send to Client
                                                </a>
                                                <?php } ?>
                                                <?php } ?>
                                                <?php if($invoiceSaveType == 'draft'){ ?>
                                                <div class="dropdown-divider"></div>
                                                <a class="dropdown-item updatedraftinvoice" href="javascript:;" data-receiptid="<?php echo $inc_val->receipt_id;?>" data-save-type="draft">
                                                    <i class="fa-solid fa-pen-to-square"></i> Edit Draft Invoice
                                                </a>
                                                <?php } ?>
                                                <?php
                                                // A finalised invoice can only be amended while nothing has been
                                                // allocated against it, otherwise the recalculated balance would
                                                // no longer match the receipts already applied.
                                                $isEditableFinal = ($invoiceSaveType == 'final' || $invoiceSaveType === '')
                                                    && $canEditFinalInvoices
                                                    && $inc_val->void_invoice != 1
                                                    && $inc_val->invoice_status == 0;
                                                if($isEditableFinal) { ?>
                                                <div class="dropdown-divider"></div>
                                                <a class="dropdown-item updatedraftinvoice" href="javascript:;" data-receiptid="<?php echo $inc_val->receipt_id;?>" data-save-type="final">
                                                    <i class="fa-solid fa-pen-to-square"></i> Edit Invoice
                                                </a>
                                                <?php } ?>
                                                <div class="dropdown-divider"></div>
                                                <a class="dropdown-item copy-reference" href="javascript:;" data-reference="<?php echo $inc_val->trans_no;?>">
                                                    <i class="fa-solid fa-copy"></i> Copy Invoice #
                                                </a>
                                                
                                                <?php if($invoiceSaveType == 'final' || $invoiceSaveType === '') { ?>
                                                <div class="dropdown-divider"></div>
                                                <?php
                                                $hubdoc_sent    = $inc_val->hubdoc_sent ?? false;
                                                $hubdoc_sent_at = $inc_val->hubdoc_sent_at ?? null;
                                                if($hubdoc_sent) {
                                                ?>
                                                    <a class="dropdown-item send-to-hubdoc-btn" href="javascript:;" data-invoice-id="<?php echo $inc_val->receipt_id; ?>" data-hubdoc-sent="1" style="color: #28a745;">
                                                        <i class="fa-solid fa-check"></i> Already Sent to Hubdoc
                                                    </a>
                                                    <div class="dropdown-item-text" style="font-size: 11px; color: #666; padding: 0.25rem 1rem;">
                                                        Sent: <?php echo date('d/m/Y H:i', strtotime($hubdoc_sent_at)); ?>
                                                    </div>
                                                    <a class="dropdown-item refresh-hubdoc-status" href="javascript:;" data-invoice-id="<?php echo $inc_val->receipt_id; ?>">
                                                        <i class="fa-solid fa-rotate"></i> Refresh Status
                                                    </a>
                                                <?php } else { ?>
                                                    <a class="dropdown-item send-to-hubdoc-btn" href="javascript:;" data-invoice-id="<?php echo $inc_val->receipt_id; ?>">
                                                        <i class="fa-solid fa-paper-plane"></i> Send to Hubdoc
                                                    </a>
                                                <?php } ?>
                                                <?php } ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td style="text-align: left; vertical-align: middle;"><?php echo $inc_val->trans_date;?></td>
                                    <td style="text-align: left; vertical-align: middle;"><?php echo $inc_val->description;?></td>
                                    <td style="text-align: right; vertical-align: middle; font-weight: 500;">
                                        {{ !empty($inc_val->withdraw_amount) ? '$ ' . number_format(abs($inc_val->withdraw_amount), 2) : '' }}
                                    </td>
                                        <?php
                                        $statusClassMap = [
                                            '0' => 'status-unpaid',
                                            '1' => 'status-paid',
                                            '2' => 'status-partial',
                                            '3' => 'status-void'
                                        ];

                                        $statusVal = [
                                            '0' => 'Unpaid',
                                            '1' => 'Paid',
                                            '2' => 'Partial',
                                            '3' => 'Void',
                                            '4' => 'Discount'

                                        ];

                                        $status = $inc_val->invoice_status;
                                        $statusClass = $statusClassMap[$status] ?? 'status-unpaid';
                                        if (isset($inc_val->save_type) && $inc_val->save_type == 'draft') {
                                            $statusDes = 'Draft';
                                            $statusClass = 'status-draft';
                                        } elseif (isset($inc_val->payment_type) && $inc_val->payment_type == 'Discount') {
                                            $statusDes = $statusVal[4];
                                        } else {
                                            $statusDes = $statusVal[$status] ?? 'Unpaid';
                                        }
                                        ?>

                                    <td style="text-align: left; vertical-align: middle;">
                                        <span class="status-badge <?php echo $statusClass; ?>">
                                            <?php echo $statusDes; ?>
                                        </span>
                                    </td>
                                </tr>
                        <?php
                            } //end foreach
                        } else { ?>
                        <tr class="account-table-empty-row">
                            <td colspan="5" class="account-table-empty-cell">No tax invoices issued yet.</td>
                        </tr>
                        <?php }
                        ?>

                    </tbody>
                </table>

                <h4 style="margin-top:14px; margin-bottom: 6px; font-weight: 600;"><i class="fa-solid fa-hand-holding-dollar" style="color: #28a745;"></i> Office Receipts</h4>
                <table class="transaction-table">
                    <thead>
                        <tr>
                            <th style="text-align: left;" title="Date money was received">Date</th>
                            <th colspan="2" style="text-align: left;" title="Payment method">Method</th>
                            <th style="text-align: left;" title="Particulars of payment received">Particulars / Description</th>
                            <th style="text-align: center;" title="Office receipt number">Receipt No.</th>
                            <th style="text-align: right;" title="Amount received into office account">Amount Received</th>
                        </tr>
                    </thead>
                    <tbody class="productitemList_office">
                        <?php
                        if(!empty($receipts_lists_office) && count($receipts_lists_office)>0 )
                        {
                            foreach($receipts_lists_office as $off_list=>$off_val)
                            {
                            // Determine if this receipt is unallocated (not linked to an invoice)
                            $isUnallocated = empty($off_val->invoice_no);
                            $unallocatedClass = $isUnallocated ? 'unallocated-receipt' : '';
                            $draggableAttr = $isUnallocated ? 'draggable="true"' : '';
                            ?>
                            <tr class="drow_account_office {{$unallocatedClass}}" 
                                data-matterid="{{$off_val->client_matter_id}}"
                                {{$draggableAttr}}
                                data-receipt-id="{{$off_val->id}}"
                                data-receipt-amount="{{$off_val->deposit_amount}}"
                                data-receipt-no="{{$off_val->trans_no}}">
                                <td style="text-align: left; vertical-align: middle;">
                                    <span style="display: inline-flex; align-items: center;">
                                        <?php
                                        if( isset($off_val->validate_receipt) && $off_val->validate_receipt == '1' )
                                        { ?>
                                            <i class="fa-solid fa-circle-check" title="Verified Receipt" style="margin-right: 5px; color: #28a745;"></i>
                                        <?php
                                        } ?>
                                        <?php echo $off_val->trans_date;?>
                                    </span>
                                    <?php
                                    if(isset($off_val->uploaded_doc_id) && $off_val->uploaded_doc_id >0){
                                        $office_doc = $accountDocumentsById->get((int) $off_val->uploaded_doc_id);
                                        if($office_doc){
                                            $docUrl = url('/documents/preview/' . $off_val->uploaded_doc_id);
                                            ?>
                                            <br/>
                                            <a title="See Attached Document" target="_blank" class="link-primary" href="<?php echo $docUrl;?>"><i class="fa-solid fa-file-pdf"></i> Document</a>
                                        <?php
                                        }
                                    } ?>
                                </td>
                                <?php
                                $payClassMap = [
                                    'Cash' => 'fa-arrow-down',
                                    'Bank transfer' => 'fa-arrow-right-from-bracket',
                                    'EFTPOS' => 'fa-arrow-right-from-bracket',
                                    'Refund' => 'fa-arrow-right-from-bracket'
                                ];
                                ?>
                                <td class="type-cell" style="text-align: left; vertical-align: middle;">
                                   <i class="fa-solid  <?php echo $payClassMap[$off_val->payment_method] ?? 'fa-money-bill'; ?> type-icon"></i>
                                   <span>
                                    {{$off_val->payment_method}}
                                    

                                    <?php if( !isset($off_val->extra_amount_receipt) || $off_val->extra_amount_receipt !== 'exceed' ) { ?>
                                        <br/>
                                        {{ !empty($off_val->invoice_no) ? '('.$off_val->invoice_no.')' : '' }}
                                    <?php } ?>

                                   </span>
                                </td>
                                <td style="text-align: left; vertical-align: middle; font-size: 0.9em; color: #495057;"><?php
                                    if (!empty($off_val->eftpos_surcharge_amount) && floatval($off_val->eftpos_surcharge_amount) > 0) {
                                        echo '<span style="font-size:11px;color:#6c757d;">+$' . number_format((float) $off_val->eftpos_surcharge_amount, 2) . ' surcharge</span>';
                                    } else {
                                        echo '—';
                                    }
                                ?></td>

                                <td class="description" style="text-align: left; vertical-align: middle;"><?php echo $off_val->description;?></td>
                                
                                    <td style="text-align: center; vertical-align: middle;">
                                        <div class="dropdown d-inline-block">
                                        <span class="reference-dropdown-trigger dropdown-toggle" id="dropdownOffice{{$off_val->id}}" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false" style="cursor: pointer;">
                                            <?php echo $off_val->trans_no;?> <i class="fa-solid fa-caret-down" style="font-size: 11px; opacity: 0.6; margin-left: 3px;"></i>
                                        </span>
                                        <div class="dropdown-menu" aria-labelledby="dropdownOffice{{$off_val->id}}">
                                            <?php if(isset($off_val->save_type) && $off_val->save_type == 'final') { ?>
                                            <a class="dropdown-item" href="{{URL::to('/clients/genOfficeReceipt')}}/{{$off_val->id}}" target="_blank">
                                                <i class="fa-solid fa-eye"></i> View Receipt
                                            </a>
                                            <a class="dropdown-item" href="{{URL::to('/clients/genOfficeReceipt')}}/{{$off_val->id}}?download=1">
                                                <i class="fa-solid fa-download"></i> Download PDF
                                            </a>
                                            <div class="dropdown-divider"></div>
                                            <a class="dropdown-item send-office-receipt-to-client" href="javascript:;" data-receipt-id="<?php echo $off_val->id; ?>" data-receipt-no="<?php echo $off_val->trans_no; ?>">
                                                <i class="fa-solid fa-envelope"></i> Send to Client
                                            </a>
                                            <?php } ?>
                                            <div class="dropdown-divider"></div>
                                            <?php if(!empty($off_val->uploaded_doc_id)) {
                                                $uploadedDoc = $accountDocumentsById->get((int) $off_val->uploaded_doc_id);
                                                if($uploadedDoc && !empty($uploadedDoc->myfile)) {
                                                    $docUrl = url('/documents/preview/' . $uploadedDoc->id);
                                                    ?>
                                            <a class="dropdown-item" href="<?php echo $docUrl; ?>" target="_blank">
                                                <i class="fa-solid fa-file-lines"></i> View Uploaded Receipt
                                            </a>
                                            <?php } } ?>
                                            <a class="dropdown-item upload-officereceipt-doc" href="javascript:;" 
                                                data-receipt-id="<?php echo $off_val->id; ?>" 
                                                data-client-id="<?php echo $fetchedData->id; ?>"
                                                data-matter-id="<?php echo $off_val->client_matter_id; ?>">
                                                <i class="fa-solid fa-upload"></i> <?php echo !empty($off_val->uploaded_doc_id) ? 'Replace' : 'Upload'; ?> Receipt Document
                                            </a>
                                            <?php
                                            $currentUserRole = Auth::check() ? Auth::user()->role : null;
                                            $canEditReceipt = ($currentUserRole == 1) || !isset($off_val->save_type) || $off_val->save_type == 'draft';
                                            if($canEditReceipt) { ?>
                                            <div class="dropdown-divider"></div>
                                            <a class="dropdown-item edit-office-receipt-entry" href="javascript:;"
                                                data-id="<?php echo $off_val->id; ?>"
                                                data-receiptid="<?php echo $off_val->receipt_id; ?>"
                                                data-trans-date="<?php echo htmlspecialchars($off_val->trans_date, ENT_QUOTES, 'UTF-8'); ?>"
                                                data-entry-date="<?php echo htmlspecialchars($off_val->entry_date, ENT_QUOTES, 'UTF-8'); ?>"
                                                data-payment-method="<?php echo htmlspecialchars($off_val->payment_method, ENT_QUOTES, 'UTF-8'); ?>"
                                                data-description="<?php echo htmlspecialchars($off_val->description ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                                data-deposit="<?php echo htmlspecialchars($off_val->deposit_amount ?? 0, ENT_QUOTES, 'UTF-8'); ?>"
                                                data-eftpos-surcharge="<?php echo htmlspecialchars($off_val->eftpos_surcharge_amount ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                                data-invoice-no="<?php echo htmlspecialchars($off_val->invoice_no ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                                data-matter-id="<?php echo $off_val->client_matter_id; ?>"
                                                data-uploaded-doc-id="<?php echo $off_val->uploaded_doc_id ?? ''; ?>"
                                                data-save-type="<?php echo htmlspecialchars($off_val->save_type ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                                <i class="fa-solid fa-pen-to-square"></i> <?php echo ($currentUserRole == 1 && isset($off_val->save_type) && $off_val->save_type == 'final') ? 'Edit Receipt' : 'Edit Draft Receipt'; ?>
                                            </a>
                                            <?php } ?>
                                            <div class="dropdown-divider"></div>
                                            <a class="dropdown-item copy-reference" href="javascript:;" data-reference="<?php echo $off_val->trans_no;?>">
                                                <i class="fa-solid fa-copy"></i> Copy Reference
                                            </a>
                                            
                                            <?php 
                                            // Quick Allocate button for unallocated receipts
                                            if(empty($off_val->invoice_no)) { 
                                            ?>
                                            <div class="dropdown-divider"></div>
                                            <a class="dropdown-item quick-allocate-receipt" href="javascript:;"
                                                data-receipt-id="<?php echo $off_val->id; ?>"
                                                data-receipt-amount="<?php echo $off_val->deposit_amount; ?>"
                                                data-matter-id="<?php echo $off_val->client_matter_id; ?>"
                                                data-client-id="<?php echo $fetchedData->id; ?>"
                                                style="color: #ff6b6b; font-weight: 600;">
                                                <i class="fa-solid fa-link"></i> Quick Allocate to Invoice
                                            </a>
                                            <?php } ?>
                                        </div>
                                    </div>
                                </td>

                                <td style="text-align: right; vertical-align: middle; color: #28a745; font-weight: 500;">
                                    {{ !empty($off_val->deposit_amount) ? '$ ' . number_format($off_val->deposit_amount, 2) : '' }}
                                    <?php 
                                    // Visual indicator for unallocated receipts
                                    if(empty($off_val->invoice_no)) { 
                                    ?>
                                    <br/>
                                    <small style="color: #dc3545; font-weight: 600;">
                                        <i class="fa-solid fa-circle-exclamation"></i> Unallocated
                                    </small>
                                    <?php } ?>
                                </td>
                            </tr>
                        <?php
                            } //end foreach
                        } else { ?>
                        <tr class="account-table-empty-row">
                            <td colspan="6" class="account-table-empty-cell">No office receipts recorded yet.</td>
                        </tr>
                        <?php }
                        ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>
