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

    window.listOfInvoice = listOfInvoice;
    window.loadInvoicesForQuickReceipt = loadInvoicesForQuickReceipt;
    window.populateQuickReceiptOfficeForm = populateQuickReceiptOfficeForm;

    // createapplicationnewinvoice handler REMOVED - Create Invoice from Schedule flow unused
    // (payment schedule list removed; no /create-invoice route)

})(typeof jQuery !== 'undefined' ? jQuery : null);
