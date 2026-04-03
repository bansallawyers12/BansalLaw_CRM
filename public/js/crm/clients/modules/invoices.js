/**
 * Invoices module - List invoices, Quick Receipt helpers, create invoice modal
 * Extracted from detail-main.js - Phase 3f refactoring.
 * Requires: jQuery, ClientDetailConfig
 */
(function($) {
    'use strict';
    if (!$) return;

    function listOfInvoice() {
        var client_id = window.ClientDetailConfig.clientId;
        var selectedMatter = $('.general_matter_checkbox_client_detail').is(':checked') ?
            $('.general_matter_checkbox_client_detail').val() : $('#sel_matter_id_client_detail').val();
        $.ajax({
            type: 'post',
            url: window.ClientDetailConfig.urls.listOfInvoice,
            sync: true,
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
                } catch(e) {
                    console.error('Failed to parse JSON response from listOfInvoice:', e);
                    console.error('Response received:', response);
                    $('#office_receipt_form .invoice_no_cls').html('<option value="">Error loading invoices</option>');
                    $('#client_receipt_form .invoice_no_cls').html('<option value="">Error loading invoices</option>');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error in listOfInvoice:', status, error);
                $('#office_receipt_form .invoice_no_cls').html('<option value="">Failed to load invoices</option>');
                $('#client_receipt_form .invoice_no_cls').html('<option value="">Failed to load invoices</option>');
            }
        });
    }

    function loadInvoicesForQuickReceipt(matterId, preSelectInvoice) {
        var token = (window.ClientDetailConfig && window.ClientDetailConfig.csrfToken) || $('meta[name="csrf-token"]').attr('content');
        return $.ajax({
            type: 'POST',
            url: window.ClientDetailConfig.urls.getInvoicesByMatter,
            data: {
                client_matter_id: matterId,
                client_id: window.ClientDetailConfig.clientId,
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
        }).fail(function(xhr) {
            console.error('Failed to load invoices for Quick Receipt:', xhr);
            $('#office_receipt_form .productitem_office tr.clonedrow_office').first().find('select.invoice_no_cls')
                .html('<option value="">Error loading invoices</option>');
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
