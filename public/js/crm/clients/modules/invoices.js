/**
 * Invoices module - List invoices, Quick Receipt helpers, create invoice modal
 * Extracted from detail-main.js - Phase 3f refactoring.
 * Requires: jQuery, ClientDetailConfig
 */
(function($) {
    'use strict';
    if (!$) return;

    function notifyInvoiceError(message) {
        var msg = message || 'Something went wrong. Please try again.';
        if (typeof window.crmNotify !== 'undefined' && typeof window.crmNotify.error === 'function') {
            window.crmNotify.error({ message: msg });
            return;
        }
        if (typeof window.crmAlert === 'function') {
            window.crmAlert(msg);
            return;
        }
        if (typeof window.showCrmFlash === 'function') {
            window.showCrmFlash(msg, 'error');
        }
    }

    function setInvoiceSelectError(message) {
        var optionHtml = '<option value="">' + (message || 'Error loading invoices') + '</option>';
        $('#office_receipt_form .invoice_no_cls').html(optionHtml);
        $('#client_receipt_form .invoice_no_cls').html(optionHtml);
    }

    function listOfInvoice() {
        var cfg = window.ClientDetailConfig || {};
        var client_id = cfg.clientId;
        var selectedMatter = $('.general_matter_checkbox_client_detail').is(':checked') ?
            $('.general_matter_checkbox_client_detail').val() : $('#sel_matter_id_client_detail').val();
        if (!cfg.urls || !cfg.urls.listOfInvoice) {
            setInvoiceSelectError('Invoice list unavailable');
            notifyInvoiceError('Invoice list is not configured on this page. Please refresh.');
            return;
        }
        $.ajax({
            type: 'post',
            url: cfg.urls.listOfInvoice,
            dataType: 'json',
            data: { client_id: client_id, selectedMatter: selectedMatter },
            success: function(response) {
                try {
                    var obj = response;
                    if (typeof response === 'string') {
                        obj = $.parseJSON(response);
                    }
                    if (!obj || typeof obj !== 'object') {
                        throw new Error('Invalid response structure');
                    }
                    $('#office_receipt_form .invoice_no_cls').html(obj.record_get || '<option value="">No invoices found</option>');
                    $('#client_receipt_form .invoice_no_cls').html(obj.record_get || '<option value="">No invoices found</option>');
                } catch (e) {
                    setInvoiceSelectError('Error loading invoices');
                    notifyInvoiceError('Could not load the invoice list. Please try again or refresh the page.');
                }
            },
            error: function() {
                setInvoiceSelectError('Failed to load invoices');
                notifyInvoiceError('Could not load the invoice list. Please try again or refresh the page.');
            }
        });
    }

    function loadInvoicesForQuickReceipt(matterId, preSelectInvoice) {
        var cfg = window.ClientDetailConfig || {};
        var token = cfg.csrfToken || $('meta[name="csrf-token"]').attr('content');
        if (!cfg.urls || !cfg.urls.getInvoicesByMatter) {
            $('#office_receipt_form .productitem_office tr.clonedrow_office').first().find('select.invoice_no_cls')
                .html('<option value="">Invoice list unavailable</option>');
            notifyInvoiceError('Invoice list is not configured on this page. Please refresh.');
            return $.Deferred().reject().promise();
        }
        return $.ajax({
            type: 'POST',
            url: cfg.urls.getInvoicesByMatter,
            data: {
                client_matter_id: matterId,
                client_id: cfg.clientId,
                _token: token
            }
        }).done(function(response) {
            var $dropdown = $('#office_receipt_form .productitem_office tr.clonedrow_office').first().find('select.invoice_no_cls');
            if (!$dropdown.length) return;
            $dropdown.empty();
            $dropdown.append('<option value="">Select Invoice (Optional)</option>');
            if (response && Array.isArray(response.invoices) && response.invoices.length > 0) {
                response.invoices.forEach(function(invoice) {
                    var selected = invoice.trans_no === preSelectInvoice ? 'selected' : '';
                    $dropdown.append(
                        '<option value="' + invoice.trans_no + '" ' + selected + '>' +
                        invoice.trans_no + ' - $' + parseFloat(invoice.balance_amount || 0).toFixed(2) +
                        ' (' + (invoice.status || '') + ')</option>'
                    );
                });
            }
        }).fail(function() {
            $('#office_receipt_form .productitem_office tr.clonedrow_office').first().find('select.invoice_no_cls')
                .html('<option value="">Error loading invoices</option>');
            notifyInvoiceError('Could not load invoices for Quick Receipt. You can still enter the amount manually.');
        });
    }

    function populateQuickReceiptOfficeForm(invoiceData) {
        var $modal = $('#createreceiptmodal');
        if (!$modal.length || !$modal.data('quick-receipt-mode')) return;
        $('#client_matter_id_office').val(invoiceData.matterId);
        var today = new Date();
        var dateStr = ('0' + today.getDate()).slice(-2) + '/' + ('0' + (today.getMonth() + 1)).slice(-2) + '/' + today.getFullYear();
        var $firstRow = $('#office_receipt_form .productitem_office tr.clonedrow_office').first();
        if (!$firstRow.length) return;
        $firstRow.find('input[name="trans_date[]"]').val(dateStr);
        $firstRow.find('input[name="entry_date[]"]').val(dateStr);
        $firstRow.find('input[name="deposit_amount[]"]').val(parseFloat(invoiceData.balance || 0).toFixed(2));
        $firstRow.find('input[name="description[]"]').val('Payment for ' + invoiceData.invoiceNo + ' - ' + (invoiceData.description || ''));
        loadInvoicesForQuickReceipt(invoiceData.matterId, invoiceData.invoiceNo)
            .always(function() {
                var $modalRef = $('#createreceiptmodal');
                if ($modalRef.data('quick-receipt-mode')) {
                    $firstRow.find('select[name="payment_method[]"]').focus();
                    $modalRef.removeData('quick-receipt-mode');
                    $modalRef.removeData('quick-receipt-invoice-data');
                }
            });
    }

    function normalizeInvoicePaymentType(paymentType) {
        var map = {
            'Professional Fee': 'Professional Fees',
            'Department Charges': 'Government Fees',
            'Other Cost': 'Other Costs',
            'Disbursement': 'Disbursements'
        };
        var value = (paymentType || '').trim();
        return map[value] || value;
    }

    function invoiceMoney(value) {
        if (value === null || value === undefined || value === '') {
            return 0;
        }
        var n = parseFloat(String(value).replace(/[^0-9.-]+/g, ''));
        return isNaN(n) ? 0 : Math.round(n * 100) / 100;
    }

    function invoiceMoneyField(value) {
        if (value === null || value === undefined || value === '') {
            return '';
        }
        var n = invoiceMoney(value);
        return n.toFixed(2);
    }

    function invoiceRowPaymentSign($row) {
        return $row.find('select[name="payment_type[]"]').val() === 'Discount' ? -1 : 1;
    }

    function invoiceLineHasAmountInputs($row) {
        var hoursRaw = $.trim($row.find('.invoice-hours').val() || '');
        var rateRaw = $.trim($row.find('.invoice-rate-ex-gst').val() || '');
        var amountRaw = $.trim($row.find('.invoice-amount-ex-gst').val() || '');
        return hoursRaw !== '' || rateRaw !== '' || amountRaw !== '';
    }

    function resetInvoiceLineRow($row) {
        $row.find('input[name="id[]"]').val('');
        $row.find('input[name="gst_included[]"]').val('Yes');
        $row.find('.withdraw_amount_invoice_per_row').val('');
        $row.find('.invoice-hours, .invoice-rate-ex-gst, .invoice-amount-ex-gst, .invoice-line-gst').val('').prop('readonly', false);
        $row.find('textarea[name="description[]"]').val('');
        $row.find('select[name="payment_type[]"]').prop('selectedIndex', 0);
        $row.find('select[name="fee_earner_id[]"]').prop('selectedIndex', 0);
        $row.find('select[name="fee_earner_role[]"]').prop('selectedIndex', 0);
        $row.find('select[name="billing_basis[]"]').val('hourly');
        $row.find('input[name="trans_no[]"]').val('');
        $row.find('.unique_trans_no_invoice').val('');
    }

    function stripClonedFlatpickr($row) {
        $row.find('input.flatpickr-alt-input').remove();
        $row.find('.report_entry_date_fields_invoice').each(function() {
            if (this._flatpickr && typeof this._flatpickr.destroy === 'function') {
                this._flatpickr.destroy();
            }
            $(this).removeData('flatpickr').removeClass('flatpickr-input').show();
        });
    }

    function recalcInvoiceTimesheetRow($row, options) {
        options = options || {};
        if (!$row.find('.invoice-amount-ex-gst').length) {
            return;
        }
        var basis = ($row.find('.invoice-billing-basis').val() || 'hourly').toLowerCase();
        var hoursRaw = $.trim($row.find('.invoice-hours').val() || '');
        var rateRaw = $.trim($row.find('.invoice-rate-ex-gst').val() || '');
        var $amount = $row.find('.invoice-amount-ex-gst');
        var $gst = $row.find('.invoice-line-gst');
        var amountRaw = $.trim($amount.val() || '');
        var hours = invoiceMoney(hoursRaw);
        var rate = invoiceMoney(rateRaw);
        var amountEx = invoiceMoney(amountRaw);

        $row.find('.invoice-hours, .invoice-rate-ex-gst').prop('readonly', basis === 'fixed');

        if (!invoiceLineHasAmountInputs($row)) {
            if (!options.keepGst) {
                $gst.val('');
            }
            $row.find('.withdraw_amount_invoice_per_row').val('');
            $row.find('.invoice-gst-included').val('Yes');
            return;
        }

        if (basis === 'hourly' && hoursRaw !== '' && rateRaw !== '') {
            amountEx = invoiceMoney(hours * rate);
            $amount.val(amountEx.toFixed(2));
        }

        if (!options.keepGst) {
            $gst.val(invoiceMoney(amountEx * 0.10).toFixed(2));
        }

        var gst = invoiceMoney($gst.val());
        var incl = invoiceMoney(amountEx + gst);
        $row.find('.withdraw_amount_invoice_per_row').val(incl.toFixed(2));
        $row.find('.invoice-gst-included').val(gst > 0.00001 ? 'Yes' : 'No');
    }

    function grandtotalAccountTab_invoice() {
        var totalEx = 0;
        var totalGst = 0;
        var totalIncl = 0;
        var $visibleTables = $('.productitem_invoice').filter(function() {
            return $(this).closest('form').is(':visible');
        });
        if (!$visibleTables.length) {
            $visibleTables = $('.productitem_invoice');
        }

        $visibleTables.find('tr:visible').each(function() {
            var $row = $(this);
            var sign = invoiceRowPaymentSign($row);
            if ($row.find('.invoice-amount-ex-gst').length) {
                recalcInvoiceTimesheetRow($row, { keepGst: true });
                totalEx += sign * invoiceMoney($row.find('.invoice-amount-ex-gst').val());
                totalGst += sign * invoiceMoney($row.find('.invoice-line-gst').val());
                totalIncl += sign * invoiceMoney($row.find('.withdraw_amount_invoice_per_row').val());
                return;
            }
            var withdrawVal = $row.find('.withdraw_amount_invoice_per_row').val();
            if (withdrawVal) {
                totalIncl += sign * invoiceMoney(String(withdrawVal).replace(/[^0-9.-]+/g, ''));
            }
        });

        var $form = $visibleTables.closest('form').first();
        var $scope = $form.length ? $form : $(document);
        $scope.find('.total_invoice_ex_gst').text('$' + totalEx.toFixed(2));
        $scope.find('.total_invoice_gst').text('$' + totalGst.toFixed(2));
        $scope.find('.total_withdraw_amount_all_rows_invoice').html('$' + totalIncl.toFixed(2));
    }

    function populateInvoiceLineRow($row, line) {
        line = line || {};
        $row.find('input[name="id[]"]').val(line.id || '');
        $row.find('input[name="trans_date[]"]').val(line.trans_date || '');
        $row.find('input[name="entry_date[]"]').val(line.entry_date || '');
        $row.find('select[name="payment_type[]"]').val(normalizeInvoicePaymentType(line.payment_type));
        $row.find('[name="description[]"]').val(line.description || '');
        $row.find('select[name="fee_earner_id[]"]').val(line.fee_earner_id || '');
        $row.find('select[name="fee_earner_role[]"]').val(line.fee_earner_role || '');

        var basis = line.billing_basis
            || $row.find('select[name="billing_basis[]"]').val()
            || 'hourly';
        $row.find('select[name="billing_basis[]"]').val(basis);
        $row.find('input[name="hours[]"]').val(line.hours != null && line.hours !== '' ? line.hours : '');
        $row.find('input[name="rate_ex_gst[]"]').val(invoiceMoneyField(line.rate_ex_gst));

        var amountEx = line.amount_ex_gst;
        var lineGst = line.line_gst;
        var withdraw = invoiceMoney(line.withdraw_amount);
        var hasSavedAmounts = (amountEx !== null && amountEx !== undefined && amountEx !== '')
            || (lineGst !== null && lineGst !== undefined && lineGst !== '')
            || (line.withdraw_amount !== null && line.withdraw_amount !== undefined && line.withdraw_amount !== '');
        if (!hasSavedAmounts) {
            $row.find('.invoice-amount-ex-gst, .invoice-line-gst, .withdraw_amount_invoice_per_row').val('');
            recalcInvoiceTimesheetRow($row, { keepGst: true });
            return;
        }
        if (amountEx === null || amountEx === undefined || amountEx === '') {
            if (line.gst_included === 'Yes' && withdraw) {
                lineGst = invoiceMoney(withdraw / 11);
                amountEx = invoiceMoney(withdraw - lineGst);
            } else {
                amountEx = withdraw || 0;
                lineGst = lineGst || 0;
            }
        }

        $row.find('.invoice-amount-ex-gst').val(invoiceMoneyField(amountEx));
        $row.find('.invoice-line-gst').val(invoiceMoneyField(lineGst));
        recalcInvoiceTimesheetRow($row, { keepGst: true });
    }

    function cloneInvoiceLineRow($tbody, line) {
        var html = '';
        if ($tbody && $tbody.length) {
            var $source = $tbody.find('tr.clonedrow_invoice, tr.product_field_clone_invoice').first();
            if ($source.length) {
                html = $source.prop('outerHTML');
            }
        }
        if (!html && typeof window.captureInvoiceLineRowTemplate === 'function') {
            html = window.captureInvoiceLineRowTemplate();
        }
        if (!html) {
            return $();
        }
        var $row = $(html);
        stripClonedFlatpickr($row);
        if ($tbody && $tbody.find('tr').length) {
            $row.removeClass('clonedrow_invoice').addClass('product_field_clone_invoice');
        }
        resetInvoiceLineRow($row);
        populateInvoiceLineRow($row, line || {});
        return $row;
    }

    function renderInvoiceEditLines($tbody, records) {
        if (!$tbody.length) {
            return;
        }
        $tbody.find('tr.clonedrow_invoice, tr.product_field_clone_invoice').remove();
        $.each(records || [], function(index, line) {
            var $row = cloneInvoiceLineRow($tbody, line);
            if (index < 1) {
                $row.removeClass('product_field_clone_invoice').addClass('clonedrow_invoice');
            }
            $tbody.append($row);
            if (typeof initFlatpickrForClass === 'function') {
                initFlatpickrForClass($row.find('.report_entry_date_fields_invoice'));
            }
        });
        grandtotalAccountTab_invoice();
    }

    window.listOfInvoice = listOfInvoice;
    window.loadInvoicesForQuickReceipt = loadInvoicesForQuickReceipt;
    window.populateQuickReceiptOfficeForm = populateQuickReceiptOfficeForm;
    window.recalcInvoiceTimesheetRow = recalcInvoiceTimesheetRow;
    window.grandtotalAccountTab_invoice = grandtotalAccountTab_invoice;
    window.populateInvoiceLineRow = populateInvoiceLineRow;
    window.cloneInvoiceLineRow = cloneInvoiceLineRow;
    window.renderInvoiceEditLines = renderInvoiceEditLines;

    // createapplicationnewinvoice handler REMOVED - Create Invoice from Schedule flow unused
    // (payment schedule list removed; no /create-invoice route)

})(typeof jQuery !== 'undefined' ? jQuery : null);
