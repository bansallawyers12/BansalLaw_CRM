/**
 * Accounts module - Client Funds Ledger balance, render, edit
 * Extracted from detail-main.js - Phase 3e refactoring.
 * Requires: jQuery, ClientDetailConfig, Flatpickr (optional)
 */
(function($) {
    'use strict';
    if (!$) return;

    if (window.__ClientAccountsModuleBooted) {
        if (typeof window.bindAccountEntryButtons === 'function') {
            $(function() {
                window.bindAccountEntryButtons();
            });
        }
        return;
    }
    window.__ClientAccountsModuleBooted = true;

    function clientLedgerBalanceAmount(selectedMatter) {
        var client_id = window.ClientDetailConfig.clientId;
        $.ajax({
            type: 'post',
            url: window.ClientDetailConfig.urls.clientLedgerBalance,
            sync: true,
            dataType: 'json',
            data: { client_id: client_id, selectedMatter: selectedMatter },
            success: function(response) {
                var obj = (typeof response === 'object' && response !== null) ? response : (typeof response === 'string' && response.trim() ? (function(){ try { return JSON.parse(response); } catch(e) { return null; } })() : null);
                if (!obj) return;
                $('#client_ledger_balance_amount').val(obj.record_get);
            }
        });
    }

    function renderClientFundsLedger(entries) {
        var trRows = "";
        $.each(entries, function(index, entry) {
            var typeIconMap = {
                'Deposit': 'fa-arrow-down',
                'Fee Transfer': 'fa-arrow-right-from-bracket',
                'Disbursement': 'fa-arrow-right-from-bracket',
                'Refund': 'fa-arrow-right-from-bracket'
            };
            var typeIcon = typeIconMap[entry.client_fund_ledger_type] || 'fa-money-bill';
            var typeClass = entry.client_fund_ledger_type === 'Deposit' ? 'text-success' : 'text-primary';
            var depositAmount = entry.deposit_amount ? '$' + parseFloat(entry.deposit_amount).toFixed(2) : '$0.00';
            var withdrawAmount = entry.withdraw_amount ? '$' + parseFloat(entry.withdraw_amount).toFixed(2) : '$0.00';
            var balanceAmount = entry.balance_amount ? '$' + parseFloat(entry.balance_amount).toFixed(2) : '$0.00';
            var payM = entry.payment_method != null && String(entry.payment_method).trim() !== '' ? String(entry.payment_method) : '—';
            var surVal = entry.eftpos_surcharge_amount != null && parseFloat(entry.eftpos_surcharge_amount) > 0 ? parseFloat(entry.eftpos_surcharge_amount) : '';
            var methodCell = payM;
            if (surVal !== '') {
                methodCell += '<br/><span style="font-size:11px;color:#6c757d;">+$' + surVal.toFixed(2) + ' surcharge</span>';
            }
            var editIcon = entry.client_fund_ledger_type !== 'Fee Transfer' ?
                '<a href="#" class="edit-ledger-entry" data-id="' + entry.id + '" data-trans-date="' + entry.trans_date + '" data-entry-date="' + entry.entry_date + '" data-type="' + entry.client_fund_ledger_type + '" data-description="' + (entry.description || '') + '" data-deposit="' + (entry.deposit_amount || '') + '" data-withdraw="' + (entry.withdraw_amount || '') + '" data-payment-method="' + (entry.payment_method || '').replace(/"/g, '&quot;') + '" data-eftpos-surcharge="' + surVal + '"><i class="fa-solid fa-pen"></i></a>' : '';
            trRows += '<tr data-id="' + entry.id + '">' +
                '<td>' + entry.trans_date + ' ' + editIcon + '</td>' +
                '<td class="type-cell"><i class="fa-solid ' + typeIcon + ' type-icon ' + typeClass + '"></i>' +
                '<span>' + entry.client_fund_ledger_type + (entry.invoice_no ? '<br/>(' + entry.invoice_no + ')' : '') + '</span></td>' +
                '<td style="font-size:0.9em;color:#495057;">' + methodCell + '</td>' +
                '<td class="description">' + (entry.description || '') + '</td>' +
                '<td><a href="#" title="View Receipt ' + (entry.trans_no || '') + '">' + (entry.trans_no || '') + '</a></td>' +
                '<td class="currency text-success">' + depositAmount + '</td>' +
                '<td class="currency text-danger">' + withdrawAmount + '</td>' +
                '<td class="currency">' + balanceAmount + '</td></tr>';
        });
        $('.client-funds-ledger-list').html(trRows);
    }

    function toggleEditLedgerEftposSurcharge() {
        var type = $('#editLedgerModal input[name="client_fund_ledger_type"]').val();
        var pm = $('#edit_ledger_payment_method').val();
        if (type === 'Deposit' && pm === 'EFTPOS') {
            $('#edit_ledger_eftpos_surcharge_group').show();
        } else {
            $('#edit_ledger_eftpos_surcharge_group').hide();
        }
    }

    function handleEditLedgerEntry(element) {
        var id = $(element).data('id');
        var transDate = $(element).data('trans-date');
        var entryDate = $(element).data('entry-date');
        var type = $(element).data('type');
        var description = $(element).data('description');
        var deposit = $(element).data('deposit');
        var withdraw = $(element).data('withdraw');
        var paymentMethod = $(element).data('payment-method');
        if (paymentMethod === undefined || paymentMethod === null) {
            paymentMethod = '';
        }
        var totalDep = parseFloat(deposit);
        if (isNaN(totalDep)) {
            totalDep = 0;
        }
        var surRaw = $(element).data('eftpos-surcharge');
        var sur = surRaw !== undefined && surRaw !== null && surRaw !== '' ? parseFloat(surRaw) : 0;
        if (isNaN(sur)) {
            sur = 0;
        }
        var principal = (type === 'Deposit' && sur > 0) ? Math.max(0, totalDep - sur) : totalDep;
        $('#editLedgerModal input[name="id"]').val(id);
        $('#editLedgerModal input[name="trans_date"]').val(transDate);
        $('#editLedgerModal input[name="entry_date"]').val(entryDate);
        $('#editLedgerModal input[name="client_fund_ledger_type"]').val(type).prop('readonly', true);
        $('#editLedgerModal select[name="payment_method"]').val(String(paymentMethod));
        $('#editLedgerModal input[name="description"]').val(description);
        $('#edit_ledger_eftpos_surcharge').val(sur > 0 ? sur.toFixed(2) : '');
        if (parseFloat(deposit) === 0) {
            $('#editLedgerModal input[name="deposit_amount"]').val(deposit).prop('readonly', true);
        } else {
            $('#editLedgerModal input[name="deposit_amount"]').val(principal.toFixed(2)).prop('readonly', true);
        }
        if (parseFloat(withdraw) === 0) {
            $('#editLedgerModal input[name="withdraw_amount"]').val(withdraw).prop('readonly', true);
        } else {
            $('#editLedgerModal input[name="withdraw_amount"]').val(withdraw).prop('readonly', true);
        }
        if (typeof flatpickr !== 'undefined') {
            var transDateEl = $('#editLedgerModal input[name="trans_date"]')[0];
            var entryDateEl = $('#editLedgerModal input[name="entry_date"]')[0];
            if (transDateEl && !$(transDateEl).prop('readonly') && !$(transDateEl).data('flatpickr')) {
                flatpickr(transDateEl, {
                    dateFormat: 'd/m/Y',
                    allowInput: true,
                    clickOpens: true,
                    defaultDate: $(transDateEl).val() || null,
                    locale: { firstDayOfWeek: 1 },
                    onChange: function(selectedDates, dateStr) {
                        $(transDateEl).val(dateStr);
                    }
                });
            }
            if (entryDateEl && !$(entryDateEl).prop('readonly') && !$(entryDateEl).data('flatpickr')) {
                flatpickr(entryDateEl, {
                    dateFormat: 'd/m/Y',
                    allowInput: true,
                    clickOpens: true,
                    defaultDate: $(entryDateEl).val() || null,
                    locale: { firstDayOfWeek: 1 },
                    onChange: function(selectedDates, dateStr) {
                        $(entryDateEl).val(dateStr);
                    }
                });
            }
        }
        toggleEditLedgerEftposSurcharge();
        $('#editLedgerModal').modal('show');
    }

    function attachEditLedgerHandlers() {
        $('.dropdown-menu .edit-ledger-entry').off('click').on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            handleEditLedgerEntry(this);
        });
    }

    window.clientLedgerBalanceAmount = clientLedgerBalanceAmount;
    window.renderClientFundsLedger = renderClientFundsLedger;

    function resolveAccountMatterIdForEntry() {
        if (typeof window.resolveAccountMatterId === 'function') {
            return window.resolveAccountMatterId();
        }
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
        var cfg = window.ClientDetailConfig || {};
        if (cfg.clientMatterId != null && cfg.clientMatterId !== '') {
            return String(cfg.clientMatterId);
        }
        return '';
    }

    function loadInvoicesForOfficeReceiptEntry(matterId) {
        var cfg = window.ClientDetailConfig || {};
        var url = (cfg.urls && cfg.urls.getInvoicesByMatter) ? cfg.urls.getInvoicesByMatter : '';
        if (!url) {
            return;
        }
        $.ajax({
            url: url,
            type: 'POST',
            data: {
                _token: cfg.csrfToken || '',
                client_matter_id: matterId,
                client_id: cfg.clientId || ''
            },
            success: function(response) {
                var $select = $('#office_receipt_form').find('select[name="invoice_no[]"]');
                $select.empty();
                $select.append('<option value="">Select Invoice (Optional)</option>');
                if (response.status && response.invoices && response.invoices.length > 0) {
                    response.invoices.forEach(function(invoice) {
                        $select.append('<option value="' + invoice.trans_no + '">' +
                            invoice.trans_no + ' - $' + parseFloat(invoice.balance_amount).toFixed(2) +
                            ' (' + invoice.status + ')</option>');
                    });
                }
            },
            error: function(xhr) {
                console.error('Failed to load invoices for office receipt:', xhr);
                $('#office_receipt_form').find('select[name="invoice_no[]"]')
                    .html('<option value="">Error loading invoices</option>');
            }
        });
    }

    function bindAccountEntryButtons() {
        $(document).off('click.accountTab', '.createreceipt[data-account-entry="true"]')
            .on('click.accountTab', '.createreceipt[data-account-entry="true"]', function(e) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();

                var receiptType = String($(this).data('receipt-type') || '');
                var $modal = $('#createreceiptmodal');
                var selectedMatter = resolveAccountMatterIdForEntry();
                var modalTitles = {
                    '1': '<i class="fa-solid fa-building-columns" style="color: #28a745;"></i> Trust Account Entry',
                    '2': '<i class="fa-solid fa-hand-holding-dollar" style="color: #007bff;"></i> Office Receipt &mdash; <small style="font-size:0.75em;color:#6c757d;">Money received directly (not trust)</small>',
                    '3': '<i class="fa-solid fa-file-invoice-dollar" style="color: #17a2b8;"></i> Tax Invoice'
                };
                var formIdMap = {
                    '1': 'client_receipt_form',
                    '2': 'office_receipt_form',
                    '3': 'invoice_receipt_form'
                };

                if (!$modal.length) {
                    console.error('Modal element #createreceiptmodal not found in DOM');
                    alert('Error: Receipt modal not found. Please refresh the page.');
                    return;
                }

                if (typeof $modal.modal !== 'function') {
                    console.error('Bootstrap modal plugin not loaded');
                    alert('Error: Modal plugin not available. Please refresh the page.');
                    return;
                }

                $modal.find('.receipt-type-selector').hide();
                $modal.find('.modal-title').html(modalTitles[receiptType] || 'Create Receipt');
                $('#client_receipt_form, #invoice_receipt_form, #office_receipt_form').hide();

                if (receiptType === '1') {
                    $('#client_matter_id_ledger').val(selectedMatter);
                    $('input[name="receipt_type"][value="client_receipt"]').prop('checked', true).trigger('change');
                } else if (receiptType === '2') {
                    $('#client_matter_id_office').val(selectedMatter);
                    $('input[name="receipt_type"][value="office_receipt"]').prop('checked', true).trigger('change');
                    if (typeof window.loadInvoicesForOfficeReceipt === 'function') {
                        window.loadInvoicesForOfficeReceipt(selectedMatter);
                    } else {
                        loadInvoicesForOfficeReceiptEntry(selectedMatter);
                    }
                } else if (receiptType === '3') {
                    if (typeof window.prepareInvoiceFormForCreate === 'function') {
                        window.prepareInvoiceFormForCreate(selectedMatter);
                    }
                    $('input[name="receipt_type"][value="invoice_receipt"]').prop('checked', true).trigger('change');
                }

                setTimeout(function() {
                    $('#client_receipt_form, #invoice_receipt_form, #office_receipt_form').hide();
                    var formId = formIdMap[receiptType];
                    if (formId) {
                        $('#' + formId).show();
                    }
                    if (receiptType === '3') {
                        if (typeof window.prepareInvoiceFormForCreate === 'function') {
                            window.prepareInvoiceFormForCreate(selectedMatter);
                        }
                        if (window._accountPendingInvoicePrefill && typeof window.getAccountCostsDisclosure === 'function' && typeof window.prefillInvoiceLinesFromDisclosure === 'function') {
                            window.prefillInvoiceLinesFromDisclosure(window.getAccountCostsDisclosure());
                            window._accountPendingInvoicePrefill = false;
                        }
                    } else if (receiptType === '1') {
                        $('#client_matter_id_ledger').val(selectedMatter);
                        if (window._accountPendingRetainerPrefill && typeof window.getAccountCostsDisclosure === 'function' && typeof window.prefillTrustRetainerFromDisclosure === 'function') {
                            window.prefillTrustRetainerFromDisclosure(window.getAccountCostsDisclosure());
                            window._accountPendingRetainerPrefill = false;
                        }
                    } else if (receiptType === '2') {
                        $('#client_matter_id_office').val(selectedMatter);
                    }
                }, 100);

                $modal.modal('show');
            });
    }

    window.bindAccountEntryButtons = bindAccountEntryButtons;

    function ensureAccountEntryButtonsBound() {
        bindAccountEntryButtons();
    }

    $(document).ready(function() {
        setTimeout(function() {
            attachEditLedgerHandlers();
        }, 500);

        $(document).on('shown.bs.dropdown', function() {
            attachEditLedgerHandlers();
        });

        $(document).on('click', '.edit-ledger-entry', function(e) {
            if ($(this).closest('.dropdown-menu').length > 0) {
                return;
            }
            e.preventDefault();
            handleEditLedgerEntry(this);
        });

        $(document).on('change', '#edit_ledger_payment_method', toggleEditLedgerEftposSurcharge);

        $('#updateLedgerEntryBtn').on('click', function() {
            var form = $('#editLedgerForm')[0];
            var formData = new FormData(form);
            $.ajax({
                type: 'POST',
                url: window.ClientDetailConfig.urls.updateClientFundsLedger,
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.status) {
                        $('#editLedgerModal').modal('hide');
                        localStorage.setItem('activeTab', 'accounts');
                        location.reload();
                        $('.custom-error-msg').html('<span class="alert alert-success">' + response.message + '</span>');
                        if (response.updatedEntries) {
                            renderClientFundsLedger(response.updatedEntries);
                        }
                        if (response.currentFundsHeld !== undefined) {
                            var formatted = '$ ' + parseFloat(response.currentFundsHeld).toFixed(2);
                            $('.current-funds-held, .funds-held').text(formatted);
                        }
                    } else {
                        $('.custom-error-msg').html('<span class="alert alert-danger">' + response.message + '</span>');
                    }
                },
                error: function(xhr, status, error) {
                    $('.custom-error-msg').html('<span class="alert alert-danger">An error occurred. Please try again.</span>');
                    console.error('AJAX error:', status, error);
                }
            });
        });
    });

    window.ClientAccountsTab = window.ClientAccountsTab || {};
    window.ClientAccountsTab.loadIfNeeded = function() {
        var $body = $('#account-tab-body');
        if (!$body.length || $body.attr('data-loaded') === '1' || $body.data('loading')) {
            return;
        }

        var cfg = window.ClientDetailConfig || {};
        var url = (cfg.urls && cfg.urls.accountTabHtml) ? cfg.urls.accountTabHtml : '';
        if (!url) {
            $body.html('<div class="text-center py-5 text-danger">Failed to load account ledger. Please refresh the page.</div>');
            return;
        }

        $body.data('loading', true);
        $.ajax({
            url: url,
            type: 'GET',
            data: {
                client_matter_id: cfg.clientMatterId || '',
                matter_ref: cfg.matterId || ''
            },
            success: function(html) {
                $body.html(html);
                $body.attr('data-loaded', '1');
                $(document).trigger('accountTabContentLoaded');
            },
            error: function() {
                $body.html('<div class="text-center py-5 text-danger">Failed to load account ledger. Please try again.</div>');
            },
            complete: function() {
                $body.data('loading', false);
            }
        });
    };

    $(document).ready(function() {
        ensureAccountEntryButtonsBound();
        if ($('#account-tab').hasClass('active')) {
            window.ClientAccountsTab.loadIfNeeded();
        }
    });

    $(document).on('accountTabContentLoaded clientTabContentLoaded', function(e, tabId) {
        if (e.type === 'clientTabContentLoaded' && String(tabId || '').toLowerCase() !== 'account') {
            return;
        }
        ensureAccountEntryButtonsBound();
    });

})(typeof jQuery !== 'undefined' ? jQuery : null);
