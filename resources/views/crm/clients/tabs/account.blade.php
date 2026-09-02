           <!-- Account Tab -->
           <div class="tab-pane{{ strtolower((string) ($activeTab ?? '')) === 'account' ? ' active' : '' }}" id="account-tab">
@php
    $accountTabIsLoaded = ! empty(($accountTabData ?? [])['loaded']);
@endphp
            <div id="account-tab-body" data-loaded="{{ $accountTabIsLoaded ? '1' : '0' }}">
                @if($accountTabIsLoaded)
                    @include('crm.clients.tabs.account_content')
                @else
                    <div class="account-tab-lazy-placeholder text-center py-5" role="status">
                        <i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i>
                        <p class="text-muted mb-0 mt-2">Loading account ledger…</p>
                    </div>
                @endif
            </div>

<!-- Account Tab JavaScript -->
<script>
    function resolveAccountMatterId() {
        if ($('.general_matter_checkbox_client_detail').is(':checked')) {
            var generalMatter = $('.general_matter_checkbox_client_detail').val();
            if (generalMatter) {
                return generalMatter;
            }
        }

        var selectedMatter = $('#sel_matter_id_client_detail').val();
        if (selectedMatter) {
            return selectedMatter;
        }

        if (window.ClientDetailConfig && window.ClientDetailConfig.clientMatterId) {
            return String(window.ClientDetailConfig.clientMatterId);
        }

        return '';
    }

    function formatInvoiceDateToday() {
        var d = new Date();
        var dd = String(d.getDate()).padStart(2, '0');
        var mm = String(d.getMonth() + 1).padStart(2, '0');
        var yyyy = d.getFullYear();
        return dd + '/' + mm + '/' + yyyy;
    }

    var invoiceLineRowTemplate = null;

    function captureInvoiceLineRowTemplate() {
        if (invoiceLineRowTemplate === null) {
            var $row = $('#invoice_receipt_form .productitem_invoice tr.clonedrow_invoice').first();
            if ($row.length) {
                invoiceLineRowTemplate = $row.prop('outerHTML');
            }
        }
        return invoiceLineRowTemplate;
    }

    function prepareInvoiceFormForCreate(selectedMatter) {
        $('#invoice_receipt_form input[name="function_type"]').val('add');
        $('#invoice_receipt_id').val('');
        $('#client_matter_id_invoice').val(selectedMatter);

        // Editing an invoice replaces the blank line rows with the saved ones,
        // so rebuild a single empty row before creating a new invoice.
        var rowTemplate = captureInvoiceLineRowTemplate();
        if (rowTemplate) {
            $('#invoice_receipt_form .productitem_invoice').html(rowTemplate);
        }
        $('.total_withdraw_amount_all_rows_invoice').text('');
        $('#invoice_receipt_form .invoice-draft-btn').show();
        $('#invoice_receipt_form .invoice-final-btn').text('Create Invoice');

        var today = formatInvoiceDateToday();
        var $firstRow = $('#invoice_receipt_form .productitem_invoice tr.clonedrow_invoice').first();
        $firstRow.find('input[name="trans_date[]"]').each(function() {
            $(this).val(today);
            var fp = this._flatpickr;
            if (fp) {
                fp.setDate(today, false, 'd/m/Y');
            }
        });
        $firstRow.find('input[name="entry_date[]"]').each(function() {
            $(this).val(today);
            var fp = this._flatpickr;
            if (fp) {
                fp.setDate(today, false, 'd/m/Y');
            }
        });

        if (typeof initFlatpickrForClass === 'function') {
            initFlatpickrForClass('#invoice_receipt_form .report_date_fields_invoice');
            initFlatpickrForClass('#invoice_receipt_form .report_entry_date_fields_invoice', {
                defaultDate: new Date()
            });
        }
    }

    function getAccountCostsDisclosure() {
        var el = document.getElementById('account-costs-disclosure-data');
        if (!el || !el.textContent) {
            return null;
        }
        try {
            var data = JSON.parse(el.textContent);
            return data && typeof data === 'object' ? data : null;
        } catch (e) {
            return null;
        }
    }

    function prefillInvoiceLinesFromDisclosure(disclosure) {
        if (!disclosure) {
            return;
        }

        var rowTemplate = captureInvoiceLineRowTemplate();
        if (!rowTemplate) {
            return;
        }

        var scopeSnippet = (disclosure.scopeOfWork || '').trim();
        if (scopeSnippet.length > 120) {
            scopeSnippet = scopeSnippet.substring(0, 117) + '...';
        }

        var lines = [];
        var feesExGst = parseFloat(disclosure.estimatedLegalFees) || 0;
        var feesInclGst = parseFloat(disclosure.professionalFeesInclGst);
        if (isNaN(feesInclGst) || feesInclGst <= 0) {
            var gst = parseFloat(disclosure.gstAmount);
            if (isNaN(gst) || gst < 0) {
                gst = feesExGst * 0.10;
            }
            feesInclGst = feesExGst + gst;
        }
        if (feesInclGst > 0) {
            lines.push({
                paymentType: 'Professional Fees',
                gst: 'Yes',
                amount: feesInclGst,
                description: scopeSnippet || 'Professional fees per costs disclosure'
            });
        }
        if (parseFloat(disclosure.estimatedDisbursements) > 0) {
            lines.push({
                paymentType: 'Disbursements',
                gst: 'No',
                amount: disclosure.estimatedDisbursements,
                description: 'Disbursements per costs disclosure'
            });
        }
        if (parseFloat(disclosure.estimatedBarristerFees) > 0) {
            lines.push({
                paymentType: 'Barrister Fees',
                gst: 'No',
                amount: disclosure.estimatedBarristerFees,
                description: 'Barrister fees per costs disclosure'
            });
        }

        if (lines.length === 0) {
            return;
        }

        var html = '';
        lines.forEach(function() {
            html += rowTemplate;
        });
        $('#invoice_receipt_form .productitem_invoice').html(html);

        var today = formatInvoiceDateToday();
        $('#invoice_receipt_form .productitem_invoice tr.clonedrow_invoice').each(function(index) {
            var line = lines[index];
            if (!line) {
                return;
            }
            var $row = $(this);
            $row.find('input[name="trans_date[]"]').val(today);
            $row.find('input[name="entry_date[]"]').val(today);
            $row.find('select[name="gst_included[]"]').val(line.gst);
            $row.find('select[name="payment_type[]"]').val(line.paymentType);
            $row.find('textarea[name="description[]"]').val(line.description);
            $row.find('input[name="withdraw_amount[]"]').val(parseFloat(line.amount).toFixed(2)).trigger('blur');
        });

        if (typeof initFlatpickrForClass === 'function') {
            initFlatpickrForClass('#invoice_receipt_form .report_date_fields_invoice');
            initFlatpickrForClass('#invoice_receipt_form .report_entry_date_fields_invoice', {
                defaultDate: new Date()
            });
        }
    }

    function prefillTrustRetainerFromDisclosure(disclosure) {
        if (!disclosure || parseFloat(disclosure.retainerAmount) <= 0) {
            return;
        }

        var $row = $('#client_receipt_form .clonedrow').first();
        if (!$row.length) {
            return;
        }

        var today = formatInvoiceDateToday();
        $row.find('.client_fund_ledger_type').val('Deposit').trigger('change');
        $row.find('.deposit_amount_per_row').val(parseFloat(disclosure.retainerAmount).toFixed(2)).trigger('keyup');
        $row.find('input[name="description[]"]').val('Retainer per costs disclosure');
        $row.find('input[name="trans_date[]"]').val(today);
        $row.find('input[name="entry_date[]"]').val(today);

        if (typeof initFlatpickrForClass === 'function') {
            initFlatpickrForClass('#client_receipt_form .report_date_fields');
            initFlatpickrForClass('#client_receipt_form .report_entry_date_fields', {
                defaultDate: new Date()
            });
        }
    }

    function openLegalFormsTabFromAccount() {
        var tabBtn = document.getElementById('cdn-tab-legalforms');
        if (tabBtn) {
            tabBtn.click();
            return;
        }
        if (window.ClientDetailConfig && window.ClientDetailConfig.detailBaseUrl && window.ClientDetailConfig.encodeId) {
            var matter = window.ClientDetailConfig.matterId || window.ClientDetailConfig.matterRefNo || '';
            var url = window.ClientDetailConfig.detailBaseUrl + '/' + window.ClientDetailConfig.encodeId;
            if (matter) {
                url += '/' + matter;
            }
            url += '/legalforms';
            window.location.href = url;
        }
    }

    window._accountPendingInvoicePrefill = false;
    window._accountPendingRetainerPrefill = false;

window.captureInvoiceLineRowTemplate = captureInvoiceLineRowTemplate;
window.resolveAccountMatterId = resolveAccountMatterId;
window.prepareInvoiceFormForCreate = prepareInvoiceFormForCreate;
window.getAccountCostsDisclosure = getAccountCostsDisclosure;
window.prefillInvoiceLinesFromDisclosure = prefillInvoiceLinesFromDisclosure;
window.prefillTrustRetainerFromDisclosure = prefillTrustRetainerFromDisclosure;

function initAccountTabScripts() {
    if (window.__accountTabScriptsInitialized) {
        if (typeof window.bindAccountEntryButtons === 'function') {
            window.bindAccountEntryButtons();
        }
        return;
    }
    window.__accountTabScriptsInitialized = true;

    // Snapshot the pristine invoice line row before any edit flow replaces it.
    captureInvoiceLineRowTemplate();

    if (typeof window.bindAccountEntryButtons === 'function') {
        window.bindAccountEntryButtons();
    }

    $(document).on('click.accountTab', '#account-view-disclosure-link', function(e) {
        e.preventDefault();
        openLegalFormsTabFromAccount();
    });

    $(document).on('click.accountTab', '#account-invoice-from-disclosure', function(e) {
        e.preventDefault();
        if (!getAccountCostsDisclosure()) {
            return;
        }
        window._accountPendingInvoicePrefill = true;
        $('.createreceipt[data-receipt-type="3"]').first().trigger('click');
    });

    $(document).on('click.accountTab', '#account-retainer-from-disclosure', function(e) {
        e.preventDefault();
        if (!getAccountCostsDisclosure()) {
            return;
        }
        window._accountPendingRetainerPrefill = true;
        $('.createreceipt[data-receipt-type="1"]').first().trigger('click');
    });
    
    // Reset modal when closed (cleanup for next use)
    $('#createreceiptmodal').on('hidden.bs.modal', function() {
        window._accountPendingInvoicePrefill = false;
        window._accountPendingRetainerPrefill = false;
        // Show radio buttons again (in case user opens from a different page)
        $(this).find('.receipt-type-selector').show();
        
        // Reset modal title to default
        $(this).find('.modal-title').html('Create Receipt');
        
    });
    
    // Copy Reference Functionality
    $(document).on('click', '.copy-reference', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const reference = $(this).data('reference');
        const $item = $(this);
        const originalHtml = $item.html();
        
        // Modern clipboard API
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(reference).then(() => {
                // Show success feedback
                $item.html('<i class="fa-solid fa-check"></i> Copied!');
                $item.css({'background-color': '#d4edda', 'color': '#155724'});
                
                // Reset after 1.5 seconds
                setTimeout(() => {
                    $item.html(originalHtml);
                    $item.css({'background-color': '', 'color': ''});
                }, 1500);
                
            }).catch(err => {
                console.error('Failed to copy:', err);
                $item.html('<i class="fa-solid fa-xmark"></i> Failed');
                $item.css({'background-color': '#f8d7da', 'color': '#721c24'});
                setTimeout(() => {
                    $item.html(originalHtml);
                    $item.css({'background-color': '', 'color': ''});
                }, 1500);
            });
        } else {
            // Fallback for older browsers
            const $temp = $('<textarea>');
            $('body').append($temp);
            $temp.val(reference).select();
            
            try {
                document.execCommand('copy');
                $temp.remove();
                
                // Show success feedback
                $item.html('<i class="fa-solid fa-check"></i> Copied!');
                $item.css({'background-color': '#d4edda', 'color': '#155724'});
                
                setTimeout(() => {
                    $item.html(originalHtml);
                    $item.css({'background-color': '', 'color': ''});
                }, 1500);
                
            } catch(err) {
                $temp.remove();
                $item.html('<i class="fa-solid fa-xmark"></i> Failed');
                $item.css({'background-color': '#f8d7da', 'color': '#721c24'});
                setTimeout(() => {
                    $item.html(originalHtml);
                    $item.css({'background-color': '', 'color': ''});
                }, 1500);
            }
        }
    });
    
    // Function to load invoices for the CREATE office receipt form
    function loadInvoicesForOfficeReceipt(matterId) {
        
        $.ajax({
            url: '{{ route("clients.getInvoicesByMatter") }}',
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                client_matter_id: matterId,
                client_id: '{{ $fetchedData->id }}'
            },
            success: function(response) {
                
                var $select = $('#office_receipt_form').find('select[name="invoice_no[]"]');
                $select.empty();
                $select.append('<option value="">Select Invoice (Optional)</option>');
                
                if(response.status && response.invoices && response.invoices.length > 0) {
                    response.invoices.forEach(function(invoice) {
                        $select.append('<option value="' + invoice.trans_no + '">' + 
                            invoice.trans_no + ' - $' + parseFloat(invoice.balance_amount).toFixed(2) + 
                            ' (' + invoice.status + ')</option>');
                    });
                } else {
                }
            },
            error: function(xhr) {
                console.error('❌ Failed to load invoices:', xhr);
                console.error('Response:', xhr.responseText);
                var $select = $('#office_receipt_form').find('select[name="invoice_no[]"]');
                $select.html('<option value="">Error loading invoices</option>');
            }
        });
    }
    window.loadInvoicesForOfficeReceipt = loadInvoicesForOfficeReceipt;
    
    // Update Office Receipt - Save as Draft
    $('#updateOfficeReceiptDraftBtn').on('click', function() {
        updateOfficeReceipt('draft');
    });
    
    // Update Office Receipt - Save and Finalize
    $('#updateOfficeReceiptFinalBtn').on('click', function() {
        updateOfficeReceipt('final');
    });
    
    function updateOfficeReceipt(saveType) {
        var form = $('#editOfficeReceiptForm')[0];
        var formData = new FormData(form);
        formData.append('save_type', saveType);
        formData.append('_token', '{{ csrf_token() }}');
        
        
        $.ajax({
            type: 'POST',
            url: '{{ route("clients.updateOfficeReceipt") }}',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.status) {
                    $('#editOfficeReceiptModal').modal('hide');
                    
                    // Show success message
                    crmAlert(response.message || 'Office receipt updated successfully!');
                    
                    // Reload page to show updated data
                    localStorage.setItem('activeTab', 'account');
                    location.reload();
                } else {
                    crmAlert('Error: ' + (response.message || 'Failed to update office receipt'));
                }
            },
            error: function(xhr, status, error) {
                var msg = 'An error occurred while updating. Please try again.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                } else if (xhr.responseText && xhr.responseText.indexOf('{') === 0) {
                    try {
                        var j = JSON.parse(xhr.responseText);
                        if (j.message) msg = j.message;
                    } catch (e) { /* keep default */ }
                }
                crmAlert(msg);
                console.error('AJAX error:', status, error, xhr.responseText);
            }
        });
    }
    
    // Document upload handlers for edit modal
    $('.add-document-btn-edit').on('click', function(e) {
        e.preventDefault();
        $('.docofficereceiptupload_edit').click();
    });
    
    $('.docofficereceiptupload_edit').on('change', function() {
        var fileName = $(this).val().split('\\').pop();
        if(fileName) {
            $('.file-selection-hint-edit').text('Selected: ' + fileName);
        }
    });
    
    // ================================================================
    // QUICK RECEIPT BUTTON - Pre-populate office receipt from invoice
    // ================================================================
    // FIX: Add tab visibility check to prevent duplicate handlers
    $(document).on('click', '.quick-receipt-btn:not(.createreceipt)', function(e) {
        // Only handle if the account tab is active/visible
        const isAccountTabActive = $('#account-tab').hasClass('active') || $('#account-tab').is(':visible');

        if (!isAccountTabActive) {
            return;
        }

        e.preventDefault();
        e.stopPropagation();
        
        const invoiceData = {
            invoiceNo: $(this).data('invoice-no'),
            balance: parseFloat($(this).data('invoice-balance')) || 0,
            description: $(this).data('invoice-description') || '',
            matterId: $(this).data('matter-id')
        };
        
        
        // Open the create receipt modal
        const $modal = $('#createreceiptmodal');
        
        // Enable Quick Receipt mode to prevent form clearing
        $modal.data('quick-receipt-mode', true);
        $modal.data('quick-receipt-invoice-data', invoiceData);
        
        // FIX: Hide invoice option - Quick Receipt is only for payments, not creating invoices
        $modal.find('input[name="receipt_type"][value="invoice_receipt"]').closest('label').hide();
        
        // Select "Direct Office Receipt" radio button
        $('input[name="receipt_type"][value="office_receipt"]').prop('checked', true).trigger('change');
        
        // Update modal title
        $modal.find('.modal-title').html('<i class="fa-solid fa-money-bill-wave" style="color: #28a745;"></i> Quick Receipt for ' + invoiceData.invoiceNo);
        
        // Wait briefly for the form to render, then populate fields
        if (typeof window.populateQuickReceiptOfficeForm === 'function') {
            setTimeout(function() {
                window.populateQuickReceiptOfficeForm(invoiceData);
            }, 100);
        } else {
            console.error('populateQuickReceiptOfficeForm is not available');
        }
        
        // Add a badge to indicate this is from Quick Receipt
        // FIX: Remove ALL existing badges first to prevent duplication
        $modal.find('.modal-header .badge').remove();
        $modal.find('.modal-header').prepend('<span class="badge bg-success" style="margin-right: 10px;"><i class="fa-solid fa-bolt"></i> QUICK RECEIPT</span>');
        
        // SOLUTION 5: Validate modal is available before opening
        if (typeof $modal.modal !== 'function') {
            console.error('❌ Bootstrap modal not available');
            crmAlert('Error: Modal plugin not loaded. Please refresh the page.');
            return;
        }
        
        // Open the modal
        $modal.modal('show');
    });
    
    // Remove Quick Receipt badge when modal closes
    $('#createreceiptmodal').on('hidden.bs.modal', function() {
        $(this).find('.badge-success').remove();
        $(this).find('.modal-title').html('Create Receipt');
        
        // FIX: Restore invoice option when modal closes (in case it was hidden by Quick Receipt)
        $(this).find('input[name="receipt_type"][value="invoice_receipt"]').closest('label').show();

        // Clear Quick Receipt state
        $(this).removeData('quick-receipt-mode');
        $(this).removeData('quick-receipt-invoice-data');
    });
    
    // ================================================================
    // CLIPBOARD PASTE FUNCTIONALITY
    // ================================================================
    let detectedClipboardAmount = null;
    
    // Function to extract amount from clipboard text
    function extractAmount(text) {
        if (!text) return null;
        
        // Remove currency symbols and commas
        text = text.replace(/[$,]/g, '').trim();
        
        // Try to find a number (with optional decimal)
        const match = text.match(/\d+\.?\d*/);
        if (match) {
            const amount = parseFloat(match[0]);
            return isNaN(amount) ? null : amount;
        }
        return null;
    }
    
    // Try to read clipboard when modal opens
    $('#createreceiptmodal').on('shown.bs.modal', function() {
        // Modern Clipboard API
        if (navigator.clipboard && navigator.clipboard.readText) {
            navigator.clipboard.readText()
                .then(text => {
                    const amount = extractAmount(text);
                    if (amount && amount > 0) {
                        detectedClipboardAmount = amount;
                        $('.clipboard-preview').text('($' + amount.toFixed(2) + ' detected)');
                        $('.paste-clipboard-btn').addClass('btn-outline-success').removeClass('btn-outline-primary');
                    } else {
                        detectedClipboardAmount = null;
                        $('.clipboard-preview').text('');
                    }
                })
                .catch(err => {
                    // Clipboard access denied or not available
                    detectedClipboardAmount = null;
                });
        }
    });
    
    // Handle clipboard paste button click
    $(document).on('click', '.paste-clipboard-btn', function(e) {
        e.preventDefault();
        
        if (detectedClipboardAmount && detectedClipboardAmount > 0) {
            // Find the active amount input in the visible receipt form
            const $activeForm = $('#office_receipt_form:visible');
            if ($activeForm.length > 0) {
                const $amountInput = $activeForm.find('input[name="deposit_amount[]"]').first();
                $amountInput.val(detectedClipboardAmount.toFixed(2));
                $amountInput.focus();
                
                // Visual feedback
                $amountInput.css('background-color', '#d4edda');
                setTimeout(() => {
                    $amountInput.css('background-color', '');
                }, 1000);
                
            }
        } else {
            // Try to read clipboard again
            if (navigator.clipboard && navigator.clipboard.readText) {
                navigator.clipboard.readText()
                    .then(text => {
                        const amount = extractAmount(text);
                        if (amount && amount > 0) {
                            const $activeForm = $('#office_receipt_form:visible');
                            if ($activeForm.length > 0) {
                                const $amountInput = $activeForm.find('input[name="deposit_amount[]"]').first();
                                $amountInput.val(amount.toFixed(2));
                                $amountInput.focus();
                            }
                        } else {
                            crmAlert('No valid amount found in clipboard. Please copy a number first.');
                        }
                    })
                    .catch(err => {
                        crmAlert('Could not access clipboard. Please paste manually using Ctrl+V.');
                    });
            } else {
                crmAlert('Clipboard API not available in your browser. Please paste manually.');
            }
        }
    });
    
    // ================================================================
    // REPEAT LAST ENTRY FUNCTIONALITY
    // ================================================================
    let lastOfficeReceiptEntry = null;
    
    // Store last entry when office receipt is successfully saved
    $(document).on('submit', '#office_receipt_form', function() {
        // Capture form data before submission
        const $firstRow = $(this).find('.productitem_office tr.clonedrow_office').first();
        
        lastOfficeReceiptEntry = {
            payment_method: $firstRow.find('select[name="payment_method[]"]').val(),
            description: $firstRow.find('input[name="description[]"]').val(),
            deposit_amount: $firstRow.find('input[name="deposit_amount[]"]').val(),
            eftpos_surcharge_amount: $firstRow.find('.office-eftpos-surcharge-input').val() || ''
        };
        
        // Store in localStorage for persistence
        localStorage.setItem('lastOfficeReceiptEntry', JSON.stringify(lastOfficeReceiptEntry));
    });
    
    // Load last entry from localStorage on page load
    if (localStorage.getItem('lastOfficeReceiptEntry')) {
        try {
            lastOfficeReceiptEntry = JSON.parse(localStorage.getItem('lastOfficeReceiptEntry'));
        } catch(e) {
            lastOfficeReceiptEntry = null;
        }
    }
    
    // Handle repeat last entry button click
    $(document).on('click', '.repeat-last-entry-btn', function(e) {
        e.preventDefault();
        
        if (!lastOfficeReceiptEntry) {
            crmAlert('No previous office receipt entry found. Create one first, then use this feature.');
            return;
        }
        
        // Find the active office receipt form
        const $activeForm = $('#office_receipt_form:visible');
        if ($activeForm.length === 0) {
            crmAlert('Please select "Direct Office Receipt" first.');
            return;
        }
        
        const $firstRow = $activeForm.find('.productitem_office tr.clonedrow_office').first();
        
        // Set today's date
        const today = new Date();
        const dateStr = ('0' + today.getDate()).slice(-2) + '/' + 
                       ('0' + (today.getMonth() + 1)).slice(-2) + '/' + 
                       today.getFullYear();
        
        $firstRow.find('input[name="trans_date[]"]').val(dateStr);
        $firstRow.find('input[name="entry_date[]"]').val(dateStr);
        
        // Populate from last entry
        $firstRow.find('select[name="payment_method[]"]').val(lastOfficeReceiptEntry.payment_method);
        $firstRow.find('input[name="description[]"]').val(lastOfficeReceiptEntry.description);
        $firstRow.find('input[name="deposit_amount[]"]').val(lastOfficeReceiptEntry.deposit_amount);
        if (lastOfficeReceiptEntry.eftpos_surcharge_amount) {
            $firstRow.find('.office-eftpos-surcharge-input').val(lastOfficeReceiptEntry.eftpos_surcharge_amount);
        }
        if (typeof window.toggleOfficeEftposSurchargeRow === 'function') {
            window.toggleOfficeEftposSurchargeRow($firstRow);
        }
        if (typeof window.grandtotalAccountTab_office === 'function') {
            window.grandtotalAccountTab_office();
        }
        
        // Visual feedback
        $firstRow.find('input, select').each(function() {
            $(this).css('background-color', '#d4edda');
        });
        setTimeout(() => {
            $firstRow.find('input, select').css('background-color', '');
        }, 1000);
        
        
        // Focus on amount field for easy adjustment
        $firstRow.find('input[name="deposit_amount[]"]').focus().select();
    });
    
    // ================================================================
    // QUICK ALLOCATE - Smart invoice allocation for unallocated receipts
    // ================================================================
    function handleQuickAllocateClick(button) {
        const $btn = $(button);
        const receiptId = $btn.data('receipt-id');
        const receiptAmount = parseFloat($btn.data('receipt-amount'));
        const matterId = $btn.data('matter-id');
        const clientId = $btn.data('client-id');


        const originalHtml = $btn.html();
        $btn.html('<i class="fa-solid fa-spinner fa-spin"></i> Finding matches...');

        $.ajax({
            url: '{{ route("clients.getInvoicesByMatter") }}',
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                client_matter_id: matterId,
                client_id: clientId
            },
            success: function(response) {
                $btn.html(originalHtml);

                if (!response.status) {
                    crmAlert('Error: ' + (response.message || 'Failed to fetch invoices'));
                    return;
                }

                if (!response.invoices || response.invoices.length === 0) {
                    crmAlert('No unpaid invoices found for this client/matter.');
                    return;
                }


                let exactMatch = null;
                let closeMatches = [];
                let otherInvoices = [];

                response.invoices.forEach(function(invoice) {
                    const invBalance = parseFloat(invoice.balance_amount);
                    const difference = Math.abs(invBalance - receiptAmount);
                    const percentDiff = (difference / (receiptAmount || 1)) * 100;


                    if (difference < 0.01) {
                        exactMatch = invoice;
                    } else if (percentDiff <= 10) {
                        closeMatches.push(invoice);
                    } else {
                        otherInvoices.push(invoice);
                    }
                });

                showAllocationModal(receiptId, receiptAmount, exactMatch, closeMatches, otherInvoices);
            },
            error: function(xhr) {
                $btn.html(originalHtml);
                console.error('❌ AJAX Error:', xhr);
                console.error('Status:', xhr.status);
                console.error('Response:', xhr.responseText);

                let errorMsg = 'Error loading invoices. ';
                if (xhr.status === 500) {
                    errorMsg += 'Server error (500). Check browser console for details.';
                } else if (xhr.status === 404) {
                    errorMsg += 'Route not found (404).';
                } else if (xhr.status === 419) {
                    errorMsg += 'CSRF token expired. Please refresh the page.';
                } else {
                    errorMsg += 'Status: ' + xhr.status;
                }

                crmAlert(errorMsg);
            }
        });
    }

    // Use capture phase to intercept click before Bootstrap dropdown stops propagation
    document.addEventListener('click', function(event) {
        const target = event.target.closest('.quick-allocate-receipt');
        if (!target) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();
        if (event.stopImmediatePropagation) {
            event.stopImmediatePropagation();
        }

        handleQuickAllocateClick(target);
    }, true);
    
    // ================================================================
    // QUICK ALLOCATE FOR CLIENT FUND LEDGER - Same system for deposits
    // ================================================================
    function handleQuickAllocateLedgerClick(button) {
        const $btn = $(button);
        const receiptId = $btn.data('receipt-id');
        const receiptAmount = parseFloat($btn.data('receipt-amount'));
        const matterId = $btn.data('matter-id');
        const clientId = $btn.data('client-id');


        const originalHtml = $btn.html();
        $btn.html('<i class="fa-solid fa-spinner fa-spin"></i> Finding matches...');

        $.ajax({
            url: '{{ route("clients.getInvoicesByMatter") }}',
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                client_matter_id: matterId,
                client_id: clientId
            },
            success: function(response) {
                $btn.html(originalHtml);

                if (!response.status) {
                    crmAlert('Error: ' + (response.message || 'Failed to fetch invoices'));
                    return;
                }

                if (!response.invoices || response.invoices.length === 0) {
                    crmAlert('No unpaid invoices found for this client/matter.');
                    return;
                }


                let exactMatch = null;
                let closeMatches = [];
                let otherInvoices = [];

                response.invoices.forEach(function(invoice) {
                    const invBalance = parseFloat(invoice.balance_amount);
                    const difference = Math.abs(invBalance - receiptAmount);
                    const percentDiff = (difference / (receiptAmount || 1)) * 100;


                    if (difference < 0.01) {
                        exactMatch = invoice;
                    } else if (percentDiff <= 10) {
                        closeMatches.push(invoice);
                    } else {
                        otherInvoices.push(invoice);
                    }
                });

                showLedgerAllocationModal(receiptId, receiptAmount, exactMatch, closeMatches, otherInvoices);
            },
            error: function(xhr) {
                $btn.html(originalHtml);
                console.error('❌ AJAX Error:', xhr);
                console.error('Status:', xhr.status);
                console.error('Response:', xhr.responseText);

                let errorMsg = 'Error loading invoices. ';
                if (xhr.status === 500) {
                    errorMsg += 'Server error (500). Check browser console for details.';
                } else if (xhr.status === 404) {
                    errorMsg += 'Route not found (404).';
                } else if (xhr.status === 419) {
                    errorMsg += 'CSRF token expired. Please refresh the page.';
                } else {
                    errorMsg += 'Status: ' + xhr.status;
                }

                crmAlert(errorMsg);
            }
        });
    }

    // Capture phase listener for client fund ledger quick allocate
    document.addEventListener('click', function(event) {
        const target = event.target.closest('.quick-allocate-ledger');
        if (!target) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();
        if (event.stopImmediatePropagation) {
            event.stopImmediatePropagation();
        }

        // Check if this is a re-allocation (button text contains "Re-allocate")
        const isReallocation = target.textContent.includes('Re-allocate');
        
        if (isReallocation) {
            // Show confirmation dialog
            if (!confirm('⚠️ This deposit is already allocated to an invoice.\n\nRe-allocating will:\n• Remove the existing Fee Transfer\n• Create a new Fee Transfer for the new invoice\n• Update both invoice statuses\n\nAre you sure you want to re-allocate?')) {
                return;
            }
        }

        handleQuickAllocateLedgerClick(target);
    }, true);

    function showLedgerAllocationModal(receiptId, receiptAmount, exactMatch, closeMatches, otherInvoices) {
        let modalHtml = '<div class="modal fade" id="quickAllocateLedgerModal" tabindex="-1" role="dialog">' +
            '<div class="modal-dialog modal-lg" role="document">' +
            '<div class="modal-content">' +
            '<div class="modal-header" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white;">' +
            '<h5 class="modal-title"><i class="fa-solid fa-magic"></i> Allocate Deposit to Invoice</h5>' +
            '<button type="button" class="close" data-bs-dismiss="modal" style="color: white;">' +
            '<span>&times;</span>' +
            '</button>' +
            '</div>' +
            '<div class="modal-body">' +
            '<div class="alert alert-info">' +
            '<i class="fa-solid fa-circle-info"></i> Deposit Amount: <strong>$' + receiptAmount.toFixed(2) + '</strong>' +
            '</div>';

        if (exactMatch) {
            modalHtml += '<div class="alert alert-success" style="border-left: 4px solid #28a745;">' +
                '<h6><i class="fa-solid fa-bullseye"></i> <strong>Exact Match Found!</strong></h6>' +
                '<p style="margin-bottom: 10px;">' +
                exactMatch.trans_no + ' - $' + parseFloat(exactMatch.balance_amount).toFixed(2) + 
                ' (' + exactMatch.status + ')' +
                '</p>' +
                '<button class="btn btn-success allocate-ledger-to-invoice-btn" ' +
                'data-receipt-id="' + receiptId + '" ' +
                'data-invoice-no="' + exactMatch.trans_no + '">' +
                '<i class="fa-solid fa-check"></i> Allocate to ' + exactMatch.trans_no +
                '</button>' +
                '</div>';
        }
        
        if (closeMatches.length > 0) {
            modalHtml += '<div style="margin-top: 20px;">' +
                '<h6><i class="fa-solid fa-star"></i> Close Matches:</h6>' +
                '<div class="list-group">';
            
            closeMatches.forEach(function(invoice) {
                const invBalance = parseFloat(invoice.balance_amount);
                const difference = Math.abs(invBalance - receiptAmount);
                const diffText = difference > 0 ? ' (diff: $' + difference.toFixed(2) + ')' : '';
                
                modalHtml += '<div class="list-group-item">' +
                    '<div class="d-flex justify-content-between align-items-center">' +
                    '<div>' +
                    '<strong>' + invoice.trans_no + '</strong> - ' +
                    '$' + invBalance.toFixed(2) + 
                    ' (' + invoice.status + ')' + diffText +
                    '<br/><small class="text-muted">' + invoice.description + '</small>' +
                    '</div>' +
                    '<button class="btn btn-sm btn-primary allocate-ledger-to-invoice-btn" ' +
                    'data-receipt-id="' + receiptId + '" ' +
                    'data-invoice-no="' + invoice.trans_no + '">' +
                    '<i class="fa-solid fa-link"></i> Allocate' +
                    '</button>' +
                    '</div>' +
                    '</div>';
            });
            
            modalHtml += '</div></div>';
        }
        
        if (otherInvoices.length > 0) {
            modalHtml += '<div style="margin-top: 20px;">' +
                '<h6><i class="fa-solid fa-list"></i> Other Unpaid Invoices:</h6>' +
                '<div class="list-group" style="max-height: 300px; overflow-y: auto;">';
            
            otherInvoices.forEach(function(invoice) {
                const invBalance = parseFloat(invoice.balance_amount);
                const difference = Math.abs(invBalance - receiptAmount);
                const diffText = difference > 0 ? ' (diff: $' + difference.toFixed(2) + ')' : '';
                
                modalHtml += '<div class="list-group-item">' +
                    '<div class="d-flex justify-content-between align-items-center">' +
                    '<div>' +
                    '<strong>' + invoice.trans_no + '</strong> - ' +
                    '$' + invBalance.toFixed(2) + 
                    ' (' + invoice.status + ')' + diffText +
                    '</div>' +
                    '<button class="btn btn-sm btn-primary allocate-ledger-to-invoice-btn" ' +
                    'data-receipt-id="' + receiptId + '" ' +
                    'data-invoice-no="' + invoice.trans_no + '">' +
                    '<i class="fa-solid fa-link"></i> Allocate' +
                    '</button>' +
                    '</div>' +
                    '</div>';
            });
            
            modalHtml += '</div></div>';
        }
        
        // If no invoices in any category, show a message
        if (!exactMatch && closeMatches.length === 0 && otherInvoices.length === 0) {
            modalHtml += '<div class="alert alert-warning">' +
                '<i class="fa-solid fa-triangle-exclamation"></i> No unpaid invoices found for this client/matter.' +
                '</div>';
        }
        
        modalHtml += '</div>' +
            '<div class="modal-footer">' +
            '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>' +
            '</div>' +
            '</div>' +
            '</div>' +
            '</div>';
        
        // Remove existing modal if any
        $('#quickAllocateLedgerModal').remove();
        
        // Add to body and show
        $('body').append(modalHtml);
        $('#quickAllocateLedgerModal').modal('show');
    }
    
    // Handle allocation button click in ledger modal
    $(document).on('click', '.allocate-ledger-to-invoice-btn', function(e) {
        e.preventDefault();
        
        const receiptId = $(this).data('receipt-id');
        const invoiceNo = $(this).data('invoice-no');
        
        const $btn = $(this);
        const originalHtml = $btn.html();
        $btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> Allocating...');
        
        
        // Update the ledger entry with the invoice number
        $.ajax({
            url: '{{ route("clients.updateClientFundLedger") }}',
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                id: receiptId,
                client_id: '{{ $fetchedData->id }}',
                invoice_no: invoiceNo
            },
            success: function(response) {
                if (response.status) {
                    $('#quickAllocateLedgerModal').modal('hide');
                    
                    // Show success message
                    crmAlert('✅ Deposit successfully allocated to ' + invoiceNo + '!');
                    
                    // Reload page to show updated allocation
                    localStorage.setItem('activeTab', 'account');
                    location.reload();
                } else {
                    $btn.prop('disabled', false).html(originalHtml);
                    crmAlert('Error: ' + (response.message || 'Failed to allocate deposit'));
                }
            },
            error: function(xhr) {
                $btn.prop('disabled', false).html(originalHtml);
                console.error('❌ Allocation error:', xhr);
                console.error('Status:', xhr.status);
                console.error('Response:', xhr.responseText);
                
                let errorMsg = 'An error occurred while allocating. ';
                if (xhr.status === 500) {
                    errorMsg += 'Server error (500). Check browser console for details.';
                } else if (xhr.status === 404) {
                    errorMsg += 'Route not found (404).';
                } else if (xhr.status === 419) {
                    errorMsg += 'CSRF token expired. Please refresh the page.';
                } else {
                    errorMsg += 'Status: ' + xhr.status;
                }
                
                crmAlert(errorMsg);
            }
        });
    });
    
    function showAllocationModal(receiptId, receiptAmount, exactMatch, closeMatches, otherInvoices) {
        let modalHtml = '<div class="modal fade" id="quickAllocateModal" tabindex="-1" role="dialog">' +
            '<div class="modal-dialog modal-lg" role="document">' +
            '<div class="modal-content">' +
            '<div class="modal-header" style="background: linear-gradient(135deg, var(--navy) 0%, var(--sidebar-active) 100%); color: white;">' +
            '<h5 class="modal-title"><i class="fa-solid fa-magic"></i> Smart Invoice Allocation</h5>' +
            '<button type="button" class="close" data-bs-dismiss="modal" style="color: white;">' +
            '<span>&times;</span>' +
            '</button>' +
            '</div>' +
            '<div class="modal-body">' +
            '<div class="alert alert-info">' +
            '<i class="fa-solid fa-circle-info"></i> Receipt Amount: <strong>$' + receiptAmount.toFixed(2) + '</strong>' +
            '</div>';
        
        if (exactMatch) {
            const invBalance = parseFloat(exactMatch.balance_amount);
            const excessAmount = receiptAmount - invBalance;
            const isOverpayment = excessAmount > 0.01;
            const warningHtml = isOverpayment ? 
                '<div class="alert alert-warning mt-2" style="border-left: 4px solid #ffc107;">' +
                '<i class="fa-solid fa-triangle-exclamation"></i> <strong>Note:</strong> Receipt amount ($' + receiptAmount.toFixed(2) + ') exceeds invoice balance ($' + invBalance.toFixed(2) + '). ' +
                'A residual receipt of $' + excessAmount.toFixed(2) + ' will be created.' +
                '</div>' : '';
            
            modalHtml += '<div class="alert alert-success" style="border-left: 4px solid #28a745;">' +
                '<h6><i class="fa-solid fa-bullseye"></i> <strong>Exact Match Found!</strong></h6>' +
                '<p style="margin-bottom: 10px;">' +
                exactMatch.trans_no + ' - $' + invBalance.toFixed(2) + 
                ' (' + exactMatch.status + ')' +
                '</p>' +
                warningHtml +
                '<button class="btn btn-success allocate-to-invoice-btn" ' +
                'data-receipt-id="' + receiptId + '" ' +
                'data-invoice-no="' + exactMatch.trans_no + '" ' +
                'data-invoice-balance="' + invBalance + '">' +
                '<i class="fa-solid fa-check"></i> Allocate to ' + exactMatch.trans_no +
                '</button>' +
                '</div>';
        }
        
        if (closeMatches.length > 0) {
            modalHtml += '<div style="margin-top: 20px;">' +
                '<h6><i class="fa-solid fa-star"></i> Close Matches:</h6>' +
                '<div class="list-group">';
            
            closeMatches.forEach(function(invoice) {
                const invBalance = parseFloat(invoice.balance_amount);
                const excessAmount = receiptAmount - invBalance;
                const isOverpayment = excessAmount > 0.01;
                const warningIcon = isOverpayment ? ' <i class="fa-solid fa-triangle-exclamation text-warning" title="Receipt exceeds invoice amount - will create residual receipt"></i>' : '';
                
                modalHtml += '<div class="list-group-item">' +
                    '<div class="d-flex justify-content-between align-items-center">' +
                    '<div>' +
                    '<strong>' + invoice.trans_no + '</strong> - ' +
                    '$' + invBalance.toFixed(2) + 
                    ' (' + invoice.status + ')' + warningIcon +
                    '<br/><small class="text-muted">' + invoice.description + '</small>' +
                    (isOverpayment ? '<br/><small class="text-warning"><i class="fa-solid fa-circle-info"></i> Excess: $' + excessAmount.toFixed(2) + ' will create residual receipt</small>' : '') +
                    '</div>' +
                    '<button class="btn btn-sm btn-primary allocate-to-invoice-btn" ' +
                    'data-receipt-id="' + receiptId + '" ' +
                    'data-invoice-no="' + invoice.trans_no + '" ' +
                    'data-invoice-balance="' + invBalance + '">' +
                    '<i class="fa-solid fa-link"></i> Allocate' +
                    '</button>' +
                    '</div>' +
                    '</div>';
            });
            
            modalHtml += '</div></div>';
        }
        
        if (otherInvoices.length > 0) {
            modalHtml += '<div style="margin-top: 20px;">' +
                '<h6><i class="fa-solid fa-list"></i> Other Unpaid Invoices:</h6>' +
                '<div class="list-group" style="max-height: 300px; overflow-y: auto;">';
            
            otherInvoices.forEach(function(invoice) {
                const invBalance = parseFloat(invoice.balance_amount);
                const difference = Math.abs(invBalance - receiptAmount);
                const excessAmount = receiptAmount - invBalance;
                const isOverpayment = excessAmount > 0.01;
                const diffText = difference > 0 ? ' (diff: $' + difference.toFixed(2) + ')' : '';
                const warningIcon = isOverpayment ? ' <i class="fa-solid fa-triangle-exclamation text-warning" title="Receipt exceeds invoice amount - will create residual receipt"></i>' : '';
                
                modalHtml += '<div class="list-group-item">' +
                    '<div class="d-flex justify-content-between align-items-center">' +
                    '<div>' +
                    '<strong>' + invoice.trans_no + '</strong> - ' +
                    '$' + invBalance.toFixed(2) + 
                    ' (' + invoice.status + ')' + diffText + warningIcon +
                    (isOverpayment ? '<br/><small class="text-warning"><i class="fa-solid fa-circle-info"></i> Excess: $' + excessAmount.toFixed(2) + ' will create residual receipt</small>' : '') +
                    '</div>' +
                    '<button class="btn btn-sm btn-primary allocate-to-invoice-btn" ' +
                    'data-receipt-id="' + receiptId + '" ' +
                    'data-invoice-no="' + invoice.trans_no + '" ' +
                    'data-invoice-balance="' + invBalance + '">' +
                    '<i class="fa-solid fa-link"></i> Allocate' +
                    '</button>' +
                    '</div>' +
                    '</div>';
            });
            
            modalHtml += '</div></div>';
        }
        
        // If no invoices in any category, show a message
        if (!exactMatch && closeMatches.length === 0 && otherInvoices.length === 0) {
            modalHtml += '<div class="alert alert-warning">' +
                '<i class="fa-solid fa-triangle-exclamation"></i> No unpaid invoices found for this client/matter.' +
                '</div>';
        }
        
        modalHtml += '</div>' +
            '<div class="modal-footer">' +
            '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>' +
            '</div>' +
            '</div>' +
            '</div>' +
            '</div>';
        
        // Remove existing modal if any
        $('#quickAllocateModal').remove();
        
        // Add to body and show
        $('body').append(modalHtml);
        $('#quickAllocateModal').modal('show');
    }
    
    // Handle allocation button click in modal
    $(document).on('click', '.allocate-to-invoice-btn', function(e) {
        e.preventDefault();
        
        const receiptId = $(this).data('receipt-id');
        const invoiceNo = $(this).data('invoice-no');
        const receiptAmount = parseFloat($(this).closest('.modal').find('.alert-info strong').text().replace('$', '').replace(',', ''));
        
        // Get invoice balance from the modal content
        const $invoiceRow = $(this).closest('.list-group-item, .alert');
        let invoiceBalance = 0;
        const invoiceText = $invoiceRow.text();
        const balanceMatch = invoiceText.match(/\$([\d,]+\.?\d*)/);
        if (balanceMatch) {
            invoiceBalance = parseFloat(balanceMatch[1].replace(',', ''));
        }
        
        // Check if receipt amount exceeds invoice balance
        const excessAmount = receiptAmount - invoiceBalance;
        const isOverpayment = excessAmount > 0.01; // Allow for small rounding differences
        
        // Show warning if overpayment
        if (isOverpayment) {
            const confirmMsg = `⚠️ WARNING: Receipt amount exceeds invoice balance!\n\n` +
                            `Receipt Amount: $${receiptAmount.toFixed(2)}\n` +
                            `Invoice Balance: $${invoiceBalance.toFixed(2)}\n` +
                            `Excess: $${excessAmount.toFixed(2)}\n\n` +
                            `Applying this allocation will:\n` +
                            `• Allocate $${invoiceBalance.toFixed(2)} to ${invoiceNo}\n` +
                            `• Create a new residual receipt of $${excessAmount.toFixed(2)}\n` +
                            `• The residual receipt will be available for allocation to other invoices\n\n` +
                            `Do you want to proceed?`;
            
            if (!confirm(confirmMsg)) {
                return false;
            }
        }
        
        const $btn = $(this);
        const originalHtml = $btn.html();
        $btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> Allocating...');
        
        
        // Update the receipt with the invoice number
        $.ajax({
            url: '{{ route("clients.updateOfficeReceipt") }}',
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                id: receiptId,
                client_id: '{{ $fetchedData->id }}',  // Include client_id for activity log
                invoice_no: invoiceNo,
                save_type: 'final'
            },
            success: function(response) {
                if (response.status) {
                    $('#quickAllocateModal').modal('hide');
                    
                    // Show success message
                    crmAlert('✅ Receipt successfully allocated to ' + invoiceNo + '!');
                    
                    // Reload page to show updated allocation
                    localStorage.setItem('activeTab', 'account');
                    location.reload();
                } else {
                    $btn.prop('disabled', false).html(originalHtml);
                    crmAlert('Error: ' + (response.message || 'Failed to allocate receipt'));
                }
            },
            error: function(xhr) {
                $btn.prop('disabled', false).html(originalHtml);
                console.error('❌ Allocation error:', xhr);
                console.error('Status:', xhr.status);
                console.error('Response:', xhr.responseText);
                
                let errorMsg = 'An error occurred while allocating. ';
                if (xhr.status === 500) {
                    errorMsg += 'Server error (500). Check browser console for details.';
                } else if (xhr.status === 404) {
                    errorMsg += 'Route not found (404).';
                } else if (xhr.status === 419) {
                    errorMsg += 'CSRF token expired. Please refresh the page.';
                } else {
                    errorMsg += 'Status: ' + xhr.status;
                }
                
                crmAlert(errorMsg);
            }
        });
    });
    
    // ================================================================
    // DRAG & DROP ALLOCATION - Visual drag-and-drop receipt allocation
    // ================================================================
    let draggedReceipt = null;
    
    // Handle drag start on unallocated receipts
    $(document).on('dragstart', 'tr.unallocated-receipt[draggable="true"]', function(e) {
        draggedReceipt = {
            id: $(this).data('receipt-id'),
            amount: $(this).data('receipt-amount'),
            receiptNo: $(this).data('receipt-no')
        };
        
        $(this).css('opacity', '0.5');
        
        // Set drag data
        e.originalEvent.dataTransfer.effectAllowed = 'move';
        e.originalEvent.dataTransfer.setData('text/html', $(this).html());
        
        // Add visual feedback to drop zones
        $('.invoice-drop-zone').addClass('drag-active');
        
    });
    
    // Handle drag end
    $(document).on('dragend', 'tr.unallocated-receipt[draggable="true"]', function(e) {
        $(this).css('opacity', '1');
        $('.invoice-drop-zone').removeClass('drag-active drag-over');
    });
    
    // Handle drag over invoice rows
    $(document).on('dragover', 'tr.invoice-drop-zone', function(e) {
        e.preventDefault();
        e.originalEvent.dataTransfer.dropEffect = 'move';
        
        $(this).addClass('drag-over');
        
        return false;
    });
    
    // Handle drag leave
    $(document).on('dragleave', 'tr.invoice-drop-zone', function(e) {
        $(this).removeClass('drag-over');
    });
    
    // Handle drop on invoice
    $(document).on('drop', 'tr.invoice-drop-zone', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        $(this).removeClass('drag-over');
        $('.invoice-drop-zone').removeClass('drag-active');
        
        if (!draggedReceipt) return false;
        
        const invoiceNo = $(this).data('invoice-no');
        const invoiceBalance = parseFloat($(this).data('invoice-balance'));
        const receiptAmount = parseFloat(draggedReceipt.amount);
        
        
        // Check if receipt amount exceeds invoice balance
        const excessAmount = receiptAmount - invoiceBalance;
        const isOverpayment = excessAmount > 0.01; // Allow for small rounding differences
        
        // Show confirmation with amount info
        let confirmMsg = '';
        if (isOverpayment) {
            // Warning for overpayment - will create residual receipt
            confirmMsg = `⚠️ WARNING: Receipt amount exceeds invoice balance!\n\n` +
                        `Receipt: ${draggedReceipt.receiptNo} - $${receiptAmount.toFixed(2)}\n` +
                        `Invoice: ${invoiceNo} - $${invoiceBalance.toFixed(2)}\n` +
                        `Excess: $${excessAmount.toFixed(2)}\n\n` +
                        `Applying this allocation will:\n` +
                        `• Allocate $${invoiceBalance.toFixed(2)} to ${invoiceNo}\n` +
                        `• Create a new residual receipt of $${excessAmount.toFixed(2)}\n` +
                        `• The residual receipt will be available for allocation to other invoices\n\n` +
                        `Do you want to proceed?`;
        } else {
            const amountMatch = Math.abs(invoiceBalance - receiptAmount) < 0.01;
            const matchText = amountMatch ? '✓ EXACT MATCH' : '(Partial payment)';
            confirmMsg = `Allocate ${draggedReceipt.receiptNo} ($${receiptAmount.toFixed(2)}) to ${invoiceNo} ($${invoiceBalance.toFixed(2)})?\n\n${matchText}`;
        }
        
        if (confirm(confirmMsg)) {
            // Perform allocation
            allocateReceiptToInvoice(draggedReceipt.id, invoiceNo);
        }
        
        draggedReceipt = null;
        return false;
    });
    
    function allocateReceiptToInvoice(receiptId, invoiceNo) {
        
        // Show loading indicator
        const $loadingDiv = $('<div class="allocation-loading">' +
            '<div class="spinner-border text-primary" role="status"></div>' +
            '<p>Allocating receipt to ' + invoiceNo + '...</p>' +
            '</div>');
        $('body').append($loadingDiv);
        
        $.ajax({
            url: '{{ route("clients.updateOfficeReceipt") }}',
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                id: receiptId,
                invoice_no: invoiceNo,
                save_type: 'final'
            },
            success: function(response) {
                $loadingDiv.remove();
                
                if (response.status) {
                    // Show success animation
                    const $successDiv = $('<div class="allocation-success">' +
                        '<div class="success-checkmark">' +
                        '<i class="fa-solid fa-circle-check"></i>' +
                        '</div>' +
                        '<p>✅ Receipt successfully allocated to ' + invoiceNo + '!</p>' +
                        '</div>');
                    $('body').append($successDiv);
                    
                    setTimeout(function() {
                        $successDiv.fadeOut(function() {
                            $(this).remove();
                            // Reload page
                            localStorage.setItem('activeTab', 'account');
                            location.reload();
                        });
                    }, 1500);
                } else {
                    crmAlert('Error: ' + (response.message || 'Failed to allocate receipt'));
                }
            },
            error: function(xhr) {
                $loadingDiv.remove();
                console.error('Allocation error:', xhr);
                crmAlert('An error occurred while allocating. Please try again.');
            }
        });
    }
    
});

</script>

<style>
/* Account page specific styles */
#account-tab .transaction-table tbody tr {
    transition: background-color 0.3s;
}

#account-tab .transaction-table tbody tr:hover {
    background-color: #f0f8ff !important;
}

/* Reference dropdown + invoice menu colours: public/css/crm-theme.css (#account-tab .transaction-table) */

/* FIX: Remove Bootstrap's default dropdown-toggle arrow (we have custom caret-down icon) */
.transaction-table .dropdown-toggle::after {
    display: none !important;
}

/* FIX: Allow dropdowns to escape overflow constraints */
.transaction-table .dropdown {
    position: relative;
}

.transaction-table .dropdown-menu {
    position: absolute !important;
    z-index: 9999 !important;
    transform: none !important;
    will-change: auto !important;
}

/* Override restrictive parent rules for dropdowns */
.account-section .dropdown-menu {
    max-width: none !important;
    overflow: visible !important;
}

/* Unallocated Office Receipt - Red Background */
.unallocated-receipt {
    background-color: #ffe6e6 !important;
    border-left: 4px solid #dc3545 !important;
}

.unallocated-receipt:hover {
    background-color: #ffcccc !important;
}

/* Add a visual indicator to unallocated receipts */
.unallocated-receipt td {
    color: #721c24;
}

.unallocated-receipt td .reference-dropdown-trigger {
    color: #dc3545;
    font-weight: 600;
}

/* ================================================================
   DRAG & DROP STYLES
   ================================================================ */

/* Draggable unallocated receipts */
tr.unallocated-receipt[draggable="true"] {
    cursor: move;
    user-select: none;
}

tr.unallocated-receipt[draggable="true"]:hover {
    box-shadow: 0 2px 8px rgba(220, 53, 69, 0.3);
    transform: translateY(-1px);
    transition: all 0.2s;
}

/* Invoice drop zones */
tr.invoice-drop-zone {
    position: relative;
    transition: all 0.3s ease;
}

/* Active drop zones (when dragging) */
tr.invoice-drop-zone.drag-active {
    background-color: #e8f4f8 !important;
    border-left: 4px solid var(--navy) !important;
}

tr.invoice-drop-zone.drag-active::before {
    content: "💧 Drop here to allocate";
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    background: var(--navy);
    color: white;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    z-index: 10;
    pointer-events: none;
}

/* Hover effect when dragging over */
tr.invoice-drop-zone.drag-over {
    background-color: #d1e7dd !important;
    border-left: 6px solid #28a745 !important;
    transform: scale(1.02);
    box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
}

tr.invoice-drop-zone.drag-over::before {
    content: "✓ Release to allocate";
    background: #28a745;
}

/* Loading overlay */
.allocation-loading {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.7);
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    z-index: 9999;
}

.allocation-loading .spinner-border {
    width: 4rem;
    height: 4rem;
    border-width: 0.4rem;
}

.allocation-loading p {
    color: white;
    font-size: 18px;
    font-weight: 600;
    margin-top: 20px;
}

/* Success overlay */
.allocation-success {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(40, 167, 69, 0.95);
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    z-index: 9999;
}

.allocation-success .success-checkmark {
    font-size: 100px;
    color: white;
    animation: scaleIn 0.5s ease-out;
}

.allocation-success p {
    color: white;
    font-size: 24px;
    font-weight: 600;
    margin-top: 20px;
    animation: fadeInUp 0.5s ease-out 0.3s both;
}

@keyframes scaleIn {
    from {
        transform: scale(0);
        opacity: 0;
    }
    to {
        transform: scale(1);
        opacity: 1;
    }
}

@keyframes fadeInUp {
    from {
        transform: translateY(20px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

/* Drag cursor indicator */
tr.unallocated-receipt[draggable="true"]::after {
    content: "🖱️ Drag to invoice";
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    background: #ff6b6b;
    color: white;
    padding: 3px 10px;
    border-radius: 15px;
    font-size: 10px;
    font-weight: 600;
    opacity: 0;
    transition: opacity 0.3s;
    pointer-events: none;
}

tr.unallocated-receipt[draggable="true"]:hover::after {
    opacity: 1;
}

/* ============================================================================
   RECEIPT UPLOAD MODAL - DRAG AND DROP ZONE STYLES
   ============================================================================ */

.receipt-drag-drop-zone {
    border: 2px dashed #ccc;
    border-radius: 8px;
    padding: 40px 20px;
    text-align: center;
    background-color: #f9f9f9;
    cursor: pointer !important;
    transition: all 0.3s ease;
    min-height: 140px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 15px;
    position: relative;
    z-index: 1;
}

.receipt-drag-drop-zone:hover {
    border-color: #007bff;
    background-color: #f0f8ff;
    transform: translateY(-2px);
}

.receipt-drag-drop-zone.drag_over {
    border-color: #28a745;
    background-color: #e8f5e9;
    border-width: 3px;
    box-shadow: 0 0 10px rgba(40, 167, 69, 0.3);
}

.receipt-drag-drop-zone .drag-zone-inner {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 15px;
    width: 100%;
}

.receipt-drag-drop-zone .drag-zone-inner i {
    font-size: 48px;
    color: #2563eb;
    transition: all 0.3s ease;
}

.receipt-drag-drop-zone:hover .drag-zone-inner i {
    transform: scale(1.1);
    color: #0056b3;
}

.receipt-drag-drop-zone .drag-zone-content {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.receipt-drag-drop-zone .drag-zone-text {
    font-size: 16px;
    font-weight: 500;
    color: #333;
    margin: 0;
}

.receipt-drag-drop-zone .drag-zone-formats {
    font-size: 13px;
    color: #4b5563;
}

.receipt-drag-drop-zone.uploading {
    pointer-events: none;
    opacity: 0.6;
    border-color: #007bff;
}

.receipt-drag-drop-zone.uploading .drag-zone-text {
    color: #007bff;
}

.receipt-drag-drop-zone.uploading .drag-zone-text::after {
    content: ' - Uploading...';
    font-weight: bold;
}

.receipt-drag-drop-zone.file-selected {
    border-color: #28a745;
    background-color: #f0fff4;
}

/* Selected File Display */
.selected-file-display {
    padding: 12px 15px;
    background-color: #e8f5e9;
    border-radius: 6px;
    border: 1px solid #c3e6cb;
    margin-bottom: 15px;
}

.selected-file-display .file-info {
    display: flex;
    align-items: center;
    gap: 10px;
}

.selected-file-display .file-info i {
    font-size: 20px;
}

.selected-file-display .file-name {
    flex: 1;
    font-weight: 500;
    color: #155724;
    word-break: break-word;
}

.selected-file-display .remove-file {
    padding: 0;
    margin: 0;
    line-height: 1;
}

.selected-file-display .remove-file:hover {
    text-decoration: none;
    opacity: 0.8;
}

/* Responsive adjustments */
@media (max-width: 576px) {
    .receipt-drag-drop-zone {
        padding: 30px 15px;
        min-height: 120px;
    }
    
    .receipt-drag-drop-zone .drag-zone-inner i {
        font-size: 36px;
    }
    
    .receipt-drag-drop-zone .drag-zone-text {
        font-size: 14px;
    }
}
</style>

<!-- Upload Receipt Document Modal -->
<div class="modal fade" id="uploadReceiptDocModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fa-solid fa-upload"></i> Upload Receipt Document
                </h5>
                <button type="button" class="close" data-bs-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form id="uploadReceiptDocForm" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" id="upload_receipt_id" name="receipt_id">
                    <input type="hidden" id="upload_client_id" name="clientid">
                    <input type="hidden" id="upload_matter_id" name="client_matter_id">
                    <input type="hidden" id="upload_receipt_type" name="receipt_type">
                    <input type="hidden" name="doctype" value="receipt_uploads">
                    <input type="hidden" name="type" value="client">
                    
                    <div class="form-group">
                        <label>Select Receipt Document <span class="text-danger">*</span></label>
                        
                        <!-- NEW: Drag and Drop Zone -->
                        <div class="receipt-drag-drop-zone" id="receiptDragDropZone">
                            <div class="drag-zone-inner">
                                <i class="fa-solid fa-cloud-arrow-up"></i>
                                <div class="drag-zone-content">
                                    <p class="drag-zone-text">Drag file here or <strong>click to browse</strong></p>
                                    <small class="drag-zone-formats">Accepted: PDF, JPG, PNG, DOC, DOCX (Max 10MB)</small>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Keep existing file input (hidden, used as fallback) -->
                        <input type="file" class="d-none" name="document_upload" id="receipt_document_upload" 
                            accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" style="display: none;">
                        
                        <!-- File name display (shown after selection) -->
                        <div id="selected-file-display" class="selected-file-display" style="display: none;">
                            <div class="file-info">
                                <i class="fa-solid fa-file-lines text-success"></i>
                                <span id="selected-file-name" class="file-name"></span>
                                <button type="button" class="btn btn-sm btn-link text-danger remove-file" title="Remove file">
                                    <i class="fa-solid fa-xmark"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fa-solid fa-circle-info"></i> This document will be attached to the selected receipt entry for verification purposes.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-upload"></i> Upload Document
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // FIX: Bootstrap dropdown stops event propagation completely
    // Solution: Directly attach handlers to each button after page load
    
    // Function to attach upload receipt handlers
    function attachUploadHandlers() {
        // Upload Client Receipt Document - direct attachment
        $('.upload-clientreceipt-doc').off('click').on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            let receiptId = $(this).data('receipt-id');
            let clientId = $(this).data('client-id');
            let matterId = $(this).data('matter-id');
            
            $('#upload_receipt_id').val(receiptId);
            $('#upload_client_id').val(clientId);
            $('#upload_matter_id').val(matterId);
            $('#upload_receipt_type').val('client');
            $('#uploadReceiptDocModal').modal('show');
        });
        
        // Upload Office Receipt Document - direct attachment
        $('.upload-officereceipt-doc').off('click').on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            let receiptId = $(this).data('receipt-id');
            let clientId = $(this).data('client-id');
            let matterId = $(this).data('matter-id');
            
            $('#upload_receipt_id').val(receiptId);
            $('#upload_client_id').val(clientId);
            $('#upload_matter_id').val(matterId);
            $('#upload_receipt_type').val('office');
            $('#uploadReceiptDocModal').modal('show');
        });
        
        // Upload Journal Receipt Document - direct attachment
        $('.upload-journalreceipt-doc').off('click').on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            let receiptId = $(this).data('receipt-id');
            let clientId = $(this).data('client-id');
            let matterId = $(this).data('matter-id');
            
            $('#upload_receipt_id').val(receiptId);
            $('#upload_client_id').val(clientId);
            $('#upload_matter_id').val(matterId);
            $('#upload_receipt_type').val('journal');
            $('#uploadReceiptDocModal').modal('show');
        });
    }
    
    // FIX: Move modals to body to prevent z-index/positioning issues
    // Bootstrap modals must be direct children of body to work properly
    if ($('#uploadReceiptDocModal').parent().attr('id') !== 'body' && !$('#uploadReceiptDocModal').parent().is('body')) {
        $('#uploadReceiptDocModal').appendTo('body');
    }
    
    // Also move editOfficeReceiptModal if it's not already at body level
    if ($('#editOfficeReceiptModal').length > 0 && !$('#editOfficeReceiptModal').parent().is('body')) {
        $('#editOfficeReceiptModal').appendTo('body');
    }
    
    // Also move editLedgerModal if it's not already at body level
    if ($('#editLedgerModal').length > 0 && !$('#editLedgerModal').parent().is('body')) {
        $('#editLedgerModal').appendTo('body');
    }
    
    // Function to load invoices for the edit modal
    const loadInvoicesForEdit = function(matterId, selectedInvoice) {
        $.ajax({
            url: '{{ route("clients.getInvoicesByMatter") }}',
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                client_matter_id: matterId,
                client_id: '{{ $fetchedData->id }}'
            },
            success: function(response) {
                var $select = $('#edit_office_invoice_no');
                $select.empty();
                $select.append('<option value="">Select Invoice (Optional)</option>');
                
                if(response.invoices && response.invoices.length > 0) {
                    response.invoices.forEach(function(invoice) {
                        var selected = (invoice.trans_no == selectedInvoice) ? 'selected' : '';
                        $select.append('<option value="' + invoice.trans_no + '" ' + selected + '>' + 
                            invoice.trans_no + ' - $' + parseFloat(invoice.balance_amount).toFixed(2) + 
                            ' (' + invoice.status + ')</option>');
                    });
                }
                
            },
            error: function(xhr) {
                console.error('Failed to load invoices:', xhr);
                $('#edit_office_invoice_no').html('<option value="">No invoices available</option>');
            }
        });
    };
    
    window.loadInvoicesForEdit = loadInvoicesForEdit;
    
    // Function to attach edit office receipt handlers
    function attachEditOfficeReceiptHandlers() {
        $('.edit-office-receipt-entry').off('click').on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            var id = $(this).data('id');
            var receiptId = $(this).data('receiptid');
            var transDate = $(this).data('trans-date');
            var entryDate = $(this).data('entry-date');
            var paymentMethod = $(this).data('payment-method');
            var description = $(this).data('description');
            var deposit = $(this).data('deposit');
            var eftposSurcharge = $(this).data('eftpos-surcharge');
            var invoiceNo = $(this).data('invoice-no');
            var matterId = $(this).data('matter-id');
            var uploadedDocId = $(this).data('uploaded-doc-id');
            
            
            var totalDep = parseFloat(deposit) || 0;
            var sur = (eftposSurcharge !== undefined && eftposSurcharge !== null && eftposSurcharge !== '') ? parseFloat(eftposSurcharge) : 0;
            if (isNaN(sur)) sur = 0;
            var principalOffice = (paymentMethod === 'EFTPOS' && sur > 0) ? Math.max(0, totalDep - sur) : totalDep;
            
            // Populate modal fields
            $('#editOfficeReceiptForm input[name="id"]').val(id);
            $('#edit_office_receipt_id').val(receiptId);
            $('#edit_office_client_matter_id').val(matterId);
            $('#edit_office_trans_date').val(transDate);
            $('#edit_office_entry_date').val(entryDate);
            // Match <option value="..."> (e.g. DB "Bank Transfer" vs value "Bank transfer")
            var pmMap = { 'Bank Transfer': 'Bank transfer', 'bank transfer': 'Bank transfer' };
            if (pmMap[paymentMethod]) {
                paymentMethod = pmMap[paymentMethod];
            }
            $('#edit_office_payment_method').val(paymentMethod);
            $('#edit_office_deposit_amount').val(principalOffice.toFixed(2));
            $('#edit_office_eftpos_surcharge').val(sur > 0 ? sur.toFixed(2) : '');
            if (paymentMethod === 'EFTPOS') {
                $('#edit_office_eftpos_surcharge_row').show();
            } else {
                $('#edit_office_eftpos_surcharge_row').hide();
            }
            $('#edit_office_description').val(description);
            
            // Initialize Flatpickr for office receipt dates
            if (typeof flatpickr !== 'undefined') {
                $('#edit_office_trans_date, #edit_office_entry_date').each(function() {
                    var $this = $(this);
                    if (!$this.data('flatpickr')) {
                        flatpickr(this, {
                            dateFormat: 'd/m/Y', // dd/mm/yyyy format
                            allowInput: true,
                            clickOpens: true,
                            defaultDate: $this.val() || null,
                            locale: { firstDayOfWeek: 1 },
                            onChange: function(selectedDates, dateStr, instance) {
                                $this.val(dateStr);
                                $this.trigger('change');
                            }
                        });
                    }
                });
            }
            
            // Load invoices for the matter and select the current one
            loadInvoicesForEdit(matterId, invoiceNo);
            
            // Show current document if exists
            if(uploadedDocId && uploadedDocId != '') {
                $('#current_document_display').html('<p class="text-info"><i class="fa-solid fa-file-pdf"></i> Document attached (ID: ' + uploadedDocId + ')</p>');
            } else {
                $('#current_document_display').html('');
            }
            
            // Show modal
            $('#editOfficeReceiptModal').modal('show');
        });
    }
    
    // Attach handlers on page load and after lazy-loaded ledger HTML
    attachUploadHandlers();
    attachEditOfficeReceiptHandlers();
    $(document).on('accountTabContentLoaded', function() {
        attachUploadHandlers();
        attachEditOfficeReceiptHandlers();
    });
    
    $(document).on('change', '#edit_office_payment_method', function() {
        var pm = $(this).val();
        if (pm === 'EFTPOS') {
            $('#edit_office_eftpos_surcharge_row').show();
        } else {
            var p = parseFloat($('#edit_office_deposit_amount').val()) || 0;
            var s = parseFloat($('#edit_office_eftpos_surcharge').val()) || 0;
            $('#edit_office_deposit_amount').val((p + s).toFixed(2));
            $('#edit_office_eftpos_surcharge').val('');
            $('#edit_office_eftpos_surcharge_row').hide();
        }
    });
    
    // Re-attach after any dynamic content updates
    $(document).on('DOMNodeInserted', function(e) {
        if ($(e.target).find('.upload-clientreceipt-doc, .upload-officereceipt-doc, .upload-journalreceipt-doc').length) {
            attachUploadHandlers();
        }
        if ($(e.target).find('.edit-office-receipt-entry').length) {
            attachEditOfficeReceiptHandlers();
        }
    });
    
    // ============================================================================
    // DRAG AND DROP FUNCTIONALITY FOR RECEIPT DOCUMENT UPLOAD MODAL
    // ============================================================================
    
    
    function initReceiptDragDrop() {
        
        var $zone = $('#receiptDragDropZone');
        if ($zone.length === 0) {
            console.warn('⚠️ Receipt drag zone not found');
            return;
        }
        
        
        // Remove all existing handlers
        $zone.off('click dragenter dragover dragleave drop');
        $(document).off('dragover.receipt dragenter.receipt');
        
        // Prevent default drag behaviors on the modal to avoid interference
        $(document).on('dragover.receipt dragenter.receipt', '#uploadReceiptDocModal', function(e) {
            e.preventDefault();
            e.stopPropagation();
        });
        
        // DIRECT BINDING to receipt drag zone for priority
        $zone.on('dragenter', function(e) {
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
            $(this).addClass('drag_over');
            return false;
        });
        
        $zone.on('dragover', function(e) {
            var event = e.originalEvent || e;
            event.preventDefault();
            event.stopPropagation();
            
            if (event.dataTransfer) {
                event.dataTransfer.dropEffect = 'copy';
            }
            
            $(this).addClass('drag_over');
            return false;
        });

        $zone.on('dragleave', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            // Only remove highlight if actually leaving the zone
            var rect = this.getBoundingClientRect();
            var x = e.originalEvent.clientX;
            var y = e.originalEvent.clientY;
            
            if (x <= rect.left || x >= rect.right || y <= rect.top || y >= rect.bottom) {
                $(this).removeClass('drag_over');
            }
            return false;
        });

        $zone.on('drop', function(e) {
            var event = e.originalEvent || e;
            event.preventDefault();
            event.stopPropagation();
            event.stopImmediatePropagation();
            
            $(this).removeClass('drag_over');
            
            var files = event.dataTransfer ? event.dataTransfer.files : null;
            if (files && files.length > 0) {
                handleReceiptFileDrop(files[0]);
            } else {
                console.error('❌ No files in drop event');
            }
            return false;
        });

        // Click to browse
        $zone.on('click', function(e) {
            e.preventDefault();
            // Don't trigger if user is clicking the remove button
            if (!$(e.target).closest('.remove-file').length) {
                $('#receipt_document_upload').click();
            }
        });
        
    }
    
    // Initialize when modal is shown
    $('#uploadReceiptDocModal').on('shown.bs.modal', function() {
        setTimeout(initReceiptDragDrop, 100);
    });
    
    // Also initialize on page load (in case modal is already open)
    $(document).ready(function() {
        initReceiptDragDrop();
    });

    // File input change handler (for when user clicks to browse)
    $(document).on('change', '#receipt_document_upload', function() {
        var file = this.files[0];
        if (file) {
            if (validateReceiptFile(file)) {
                displaySelectedReceiptFile(file);
            } else {
                // Clear the input
                $(this).val('');
            }
        }
    });

    // Remove file button handler
    $(document).on('click', '.remove-file', function(e) {
        e.preventDefault();
        e.stopPropagation();
        clearSelectedReceiptFile();
    });

    // Function to handle dropped file
    function handleReceiptFileDrop(file) {
        if (validateReceiptFile(file)) {
            // Set file to input using DataTransfer API
            var dataTransfer = new DataTransfer();
            dataTransfer.items.add(file);
            $('#receipt_document_upload')[0].files = dataTransfer.files;
            
            // Display selected file
            displaySelectedReceiptFile(file);
        }
    }

    // Function to validate file
    function validateReceiptFile(file) {
        // Validate file type
        var allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
        var fileExtension = file.name.split('.').pop().toLowerCase();
        
        if (!allowedExtensions.includes(fileExtension)) {
            if (typeof iziToast !== 'undefined' && typeof iziToast.error === 'function') {
                iziToast.error({ message: 'Invalid file type. Please upload PDF, JPG, PNG, DOC, or DOCX files only.', position: 'topRight' });
            } else {
                crmAlert('Invalid file type. Please upload PDF, JPG, PNG, DOC, or DOCX files only.');
            }
            return false;
        }
        
        // Validate file size (10MB max)
        var maxSize = 10 * 1024 * 1024; // 10MB in bytes
        if (file.size > maxSize) {
            if (typeof iziToast !== 'undefined' && typeof iziToast.error === 'function') {
                iziToast.error({ message: 'File size exceeds 10MB limit. Please choose a smaller file.', position: 'topRight' });
            } else {
                crmAlert('File size exceeds 10MB limit. Please choose a smaller file.');
            }
            return false;
        }
        
        return true;
    }

    // Function to display selected file name
    function displaySelectedReceiptFile(file) {
        var fileName = file.name;
        var fileSize = formatFileSize(file.size);
        
        $('#selected-file-name').text(fileName + ' (' + fileSize + ')');
        $('#selected-file-display').show();
        $('#receiptDragDropZone').addClass('file-selected').hide();
    }

    // Function to clear selected file
    function clearSelectedReceiptFile() {
        $('#receipt_document_upload').val('');
        $('#selected-file-display').hide();
        $('#selected-file-name').text('');
        $('#receiptDragDropZone').removeClass('file-selected').show();
    }

    // Function to format file size
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        var k = 1024;
        var sizes = ['Bytes', 'KB', 'MB', 'GB'];
        var i = Math.floor(Math.log(bytes) / Math.log(k));
        return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
    }

    // Reset drag zone when modal is opened
    $('#uploadReceiptDocModal').on('show.bs.modal', function() {
        clearSelectedReceiptFile();
    });

    // Reset drag zone when modal is closed
    $('#uploadReceiptDocModal').on('hidden.bs.modal', function() {
        $('#receiptDragDropZone').removeClass('drag_over uploading file-selected');
        clearSelectedReceiptFile();
    });
    
    // Handle form submission
    $('#uploadReceiptDocForm').on('submit', function(e) {
        e.preventDefault();
        
        // Validate file is selected
        var fileInput = $('#receipt_document_upload')[0];
        if (!fileInput.files || fileInput.files.length === 0) {
            if (typeof iziToast !== 'undefined' && typeof iziToast.error === 'function') {
                iziToast.error({ message: 'Please select a file to upload.', position: 'topRight' });
            } else {
                crmAlert('Please select a file to upload.');
            }
            return false;
        }
        
        let receiptType = $('#upload_receipt_type').val();
        let formData = new FormData(this);
        let uploadUrl = '';
        
        // Determine the correct endpoint
        if (receiptType === 'client') {
            uploadUrl = '{{ route("clients.uploadclientreceiptdocument") }}';
        } else if (receiptType === 'office') {
            uploadUrl = '{{ route("clients.uploadofficereceiptdocument") }}';
        } else if (receiptType === 'journal') {
            uploadUrl = '{{ route("clients.uploadjournalreceiptdocument") }}';
        } else {
            if (typeof iziToast !== 'undefined' && typeof iziToast.error === 'function') {
                iziToast.error({ message: 'Invalid receipt type.', position: 'topRight' });
            } else {
                crmAlert('Invalid receipt type.');
            }
            return false;
        }
        
        // Show loading state on file display (if visible) or drag zone
        if ($('#selected-file-display').is(':visible')) {
            $('#selected-file-display').css('opacity', '0.6');
        } else {
            $('#receiptDragDropZone').addClass('uploading');
        }
        
        // Show loading state on submit button
        let submitBtn = $(this).find('button[type="submit"]');
        let originalText = submitBtn.html();
        submitBtn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> Uploading...');
        
        $.ajax({
            url: uploadUrl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                if (response.status) {
                    if (typeof iziToast !== 'undefined' && typeof iziToast.success === 'function') {
                        iziToast.success({ message: response.message || 'Document uploaded successfully', position: 'topRight' });
                    } else {
                        crmAlert(response.message || 'Document uploaded successfully');
                    }
                    $('#uploadReceiptDocModal').modal('hide');
                    $('#uploadReceiptDocForm')[0].reset();
                    clearSelectedReceiptFile();
                    
                    // Reload the page to show the uploaded document
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    if (typeof iziToast !== 'undefined' && typeof iziToast.error === 'function') {
                        iziToast.error({ message: response.message || 'Failed to upload document', position: 'topRight' });
                    } else {
                        crmAlert('Error: ' + (response.message || 'Failed to upload document'));
                    }
                }
            },
            error: function(xhr) {
                console.error('Upload error:', xhr);
                let errorMessage = 'An error occurred while uploading the document';
                
                // Try to extract error message from response
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = xhr.responseJSON.message;
                }
                
                if (typeof iziToast !== 'undefined' && typeof iziToast.error === 'function') {
                    iziToast.error({ message: errorMessage, position: 'topRight' });
                } else {
                    crmAlert('Error: ' + errorMessage);
                }
            },
            complete: function() {
                // Remove loading states
                $('#selected-file-display').css('opacity', '1');
                $('#receiptDragDropZone').removeClass('uploading');
                submitBtn.prop('disabled', false).html(originalText);
            }
        });
    });
    
    // Reset form when modal is closed (additional handler for form reset)
    $('#uploadReceiptDocModal').on('hidden.bs.modal', function() {
        $('#uploadReceiptDocForm')[0].reset();
        // clearSelectedReceiptFile() is already called in the handler above
    });
}

function scheduleAccountTabScriptsInit() {
    function runInit() {
        if (typeof jQuery === 'undefined') {
            setTimeout(runInit, 25);
            return;
        }
        jQuery(function() {
            initAccountTabScripts();
            if (typeof window.bindAccountEntryButtons === 'function') {
                window.bindAccountEntryButtons();
            }
        });
    }
    runInit();
}

scheduleAccountTabScriptsInit();

if (typeof jQuery !== 'undefined') {
    jQuery(document).on('clientTabContentLoaded accountTabContentLoaded', function (_event, tabId) {
        if (_event.type === 'clientTabContentLoaded') {
            initAccountTabScripts();
            if (typeof window.bindAccountEntryButtons === 'function') {
                window.bindAccountEntryButtons();
            }
            return;
        }
        if (String(tabId || '').toLowerCase() === 'account') {
            initAccountTabScripts();
            if (typeof window.bindAccountEntryButtons === 'function') {
                window.bindAccountEntryButtons();
            }
        }
    });
}
</script>

</div>
<!-- End Account Tab -->