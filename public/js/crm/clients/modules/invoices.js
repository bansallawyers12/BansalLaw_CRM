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

    function invoiceDatePad(n) {
        return (n < 10 ? '0' : '') + n;
    }

    function formatInvoicePickerDate(date) {
        if (!(date instanceof Date) || isNaN(date.getTime())) {
            return '';
        }
        return invoiceDatePad(date.getDate()) + '/' + invoiceDatePad(date.getMonth() + 1) + '/' + date.getFullYear();
    }

    function parseOneInvoiceDate(part, fallbackYear) {
        part = $.trim(part || '');
        if (!part) {
            return null;
        }
        var slash = part.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/);
        if (slash) {
            return new Date(parseInt(slash[3], 10), parseInt(slash[2], 10) - 1, parseInt(slash[1], 10));
        }
        var dash = part.match(/^(\d{1,2})-(\d{1,2})-(\d{4})$/);
        if (dash) {
            return new Date(parseInt(dash[3], 10), parseInt(dash[2], 10) - 1, parseInt(dash[1], 10));
        }
        var named = part.match(/^(\d{1,2})\s+([A-Za-z]+)(?:\s+(\d{4}))?$/);
        if (!named) {
            return null;
        }
        var months = {
            jan: 0, january: 0, feb: 1, february: 1, mar: 2, march: 2, apr: 3, april: 3,
            may: 4, jun: 5, june: 5, jul: 6, july: 6, aug: 7, august: 7,
            sep: 8, sept: 8, september: 8, oct: 9, october: 9, nov: 10, november: 10, dec: 11, december: 11
        };
        var month = months[named[2].toLowerCase()];
        if (month == null) {
            return null;
        }
        var year = named[3] ? parseInt(named[3], 10) : fallbackYear;
        if (!year) {
            return null;
        }
        return new Date(year, month, parseInt(named[1], 10));
    }

    function parseInvoiceWorkDates(value) {
        var raw = $.trim(value || '');
        if (!raw) {
            return { mode: 'single', dates: [] };
        }
        var parts = raw.split(/\s+[–—]\s+|\s+-\s+|\s+to\s+/i);
        var fallbackYear = null;
        parts.forEach(function(part) {
            var yearMatch = String(part).match(/(19|20)\d{2}/);
            if (yearMatch) {
                fallbackYear = parseInt(yearMatch[0], 10);
            }
        });
        var dates = [];
        parts.forEach(function(part) {
            var parsed = parseOneInvoiceDate(part, fallbackYear);
            if (parsed && !isNaN(parsed.getTime())) {
                dates.push(parsed);
            }
        });
        if (!dates.length) {
            var single = parseOneInvoiceDate(raw, fallbackYear);
            if (single && !isNaN(single.getTime())) {
                dates.push(single);
            }
        }
        if (dates.length >= 2) {
            return { mode: 'range', dates: dates.slice(0, 2) };
        }
        return { mode: 'single', dates: dates };
    }

    function formatInvoiceWorkDate(dates, mode) {
        if (!dates || !dates.length) {
            return '';
        }
        if (mode === 'range' && dates.length >= 2) {
            return formatInvoicePickerDate(dates[0]) + ' – ' + formatInvoicePickerDate(dates[1]);
        }
        return formatInvoicePickerDate(dates[0]);
    }

    function destroyInvoiceDatePicker(el) {
        var $el = $(el);
        var inst = el._flatpickr || $el.data('flatpickr');
        if (inst && typeof inst.destroy === 'function') {
            inst.destroy();
        }
        $el.removeData('flatpickr').removeClass('flatpickr-input');
    }

    function syncInvoiceDateModeButtons($row, mode) {
        $row.find('.invoice-date-mode-btn').each(function() {
            var isActive = $(this).data('date-mode') === mode;
            $(this).toggleClass('btn-primary', isActive)
                .toggleClass('btn-outline-secondary', !isActive);
        });
        $row.find('.invoice-date-mode').val(mode);
        $row.find('.invoice-work-date').attr('placeholder', mode === 'range' ? 'Select range' : 'Select date');
    }

    function initInvoiceWorkDatePicker($input, mode, dates) {
        if (typeof flatpickr === 'undefined' || !$input || !$input.length) {
            return;
        }
        mode = mode === 'range' ? 'range' : 'single';
        var existing = $.trim($input.val() || '');
        var el = $input.get(0);
        destroyInvoiceDatePicker(el);
        var locale = $.extend({}, (flatpickr.l10ns && flatpickr.l10ns.default) || {}, {
            firstDayOfWeek: 1,
            rangeSeparator: ' – '
        });
        var config = {
            dateFormat: 'd/m/Y',
            allowInput: false,
            clickOpens: true,
            disableMobile: true,
            locale: locale,
            mode: mode === 'range' ? 'range' : 'single',
            onChange: function(selectedDates) {
                $input.val(formatInvoiceWorkDate(selectedDates, mode));
            }
        };
        if (dates && dates.length) {
            config.defaultDate = mode === 'range' ? dates.slice(0, 2) : dates[0];
        }
        var fp = flatpickr(el, config);
        $input.data('flatpickr', fp);
        $input.prop('readonly', true);
        if (dates && dates.length) {
            $input.val(formatInvoiceWorkDate(dates, mode));
        } else if (existing) {
            $input.val(existing);
        }
    }

    function applyInvoiceRowDateMode($row, mode, keepDates) {
        var $input = $row.find('.invoice-work-date');
        var parsed = parseInvoiceWorkDates($input.val());
        var dates = keepDates === false ? [] : parsed.dates;
        if (mode === 'single' && dates.length > 1) {
            dates = [dates[0]];
        }
        syncInvoiceDateModeButtons($row, mode);
        initInvoiceWorkDatePicker($input, mode, dates);
    }

    function initInvoiceWorkDates($scope) {
        ($scope && $scope.length ? $scope.find('.invoice-work-date') : $('.invoice-work-date')).each(function() {
            var $input = $(this);
            var $row = $input.closest('tr');
            var parsed = parseInvoiceWorkDates($input.val());
            var mode = parsed.mode;
            if (parsed.dates.length < 2 && $row.find('.invoice-date-mode').val() === 'range') {
                mode = 'range';
            }
            syncInvoiceDateModeButtons($row, mode);
            initInvoiceWorkDatePicker($input, mode, parsed.dates);
        });
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
        $row.find('.invoice-hours, .invoice-rate-ex-gst, .invoice-amount-ex-gst, .invoice-line-gst').val('');
        $row.find('textarea[name="description[]"]').val('');
        $row.find('select[name="payment_type[]"]').prop('selectedIndex', 0);
        $row.find('select[name="fee_earner_id[]"]').prop('selectedIndex', 0);
        $row.find('select[name="fee_earner_role[]"]').prop('selectedIndex', 0);
        var $form = $row.closest('form');
        var mode = ($form.find('.invoice-billing-mode').val() || 'hourly');
        $row.find('.invoice-billing-basis').val(mode);
        $row.find('input[name="trans_no[]"]').val('');
        $row.find('.unique_trans_no_invoice').val('');
        $row.find('.invoice-date-mode').val('single');
        $row.find('.invoice-work-date').val('');
        syncInvoiceDateModeButtons($row, 'single');
    }

    function stripClonedFlatpickr($row) {
        $row.find('.flatpickr-calendar').remove();
        $row.find('input.flatpickr-alt-input').remove();
        $row.find('.report_entry_date_fields_invoice, .invoice-work-date').each(function() {
            destroyInvoiceDatePicker(this);
            $(this).removeClass('flatpickr-input').prop('readonly', true).show();
        });
    }

    function invoiceLineRowIsClientBlank($row) {
        if (!$row || !$row.length || !$row.closest('.productitem_invoice').length) {
            return false;
        }
        if ($.trim($row.find('input[name="id[]"]').val())) {
            return false;
        }
        if ($.trim($row.find('textarea[name="description[]"], input[name="description[]"]').val())) {
            return false;
        }
        if ($.trim($row.find('select[name="payment_type[]"]').val())) {
            return false;
        }
        return !invoiceLineHasAmountInputs($row);
    }

    function recalcInvoiceTimesheetRow($row, options) {
        options = options || {};
        if (!$row.find('.invoice-amount-ex-gst').length) {
            return;
        }
        var $form = $row.closest('form');
        var basis = ($form.find('.invoice-billing-mode').val()
            || $row.find('.invoice-billing-basis').val()
            || 'hourly').toLowerCase();
        $row.find('.invoice-billing-basis').val(basis);
        var hoursRaw = $.trim($row.find('.invoice-hours').val() || '');
        var rateRaw = $.trim($row.find('.invoice-rate-ex-gst').val() || '');
        var $amount = $row.find('.invoice-amount-ex-gst');
        var $gst = $row.find('.invoice-line-gst');
        var amountRaw = $.trim($amount.val() || '');
        var hours = invoiceMoney(hoursRaw);
        var rate = invoiceMoney(rateRaw);
        var amountEx = invoiceMoney(amountRaw);

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
        var parsedWorkDate = parseInvoiceWorkDates(line.trans_date);
        syncInvoiceDateModeButtons($row, parsedWorkDate.mode);
        initInvoiceWorkDatePicker($row.find('.invoice-work-date'), parsedWorkDate.mode, parsedWorkDate.dates);
        $row.find('select[name="payment_type[]"]').val(normalizeInvoicePaymentType(line.payment_type));
        $row.find('[name="description[]"]').val(line.description || '');
        $row.find('select[name="fee_earner_id[]"]').val(line.fee_earner_id || '');
        $row.find('select[name="fee_earner_role[]"]').val(line.fee_earner_role || '');

        var basis = line.billing_basis
            || $row.closest('form').find('.invoice-billing-mode').val()
            || 'hourly';
        $row.find('.invoice-billing-basis').val(basis);
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
                initFlatpickrForClass($row.find('.report_entry_date_fields_invoice'), { allowInput: false });
            }
            initInvoiceWorkDates($row);
        });
        applyInvoiceBillingMode($tbody.closest('form'), invoiceModeFromLines(records));
    }

    function applyInvoiceBillingMode($form, mode) {
        if (!$form || !$form.length) {
            return;
        }
        mode = mode === 'fixed' ? 'fixed' : 'hourly';
        $form.find('.invoice-billing-mode').val(mode);
        $form.toggleClass('invoice-billing-mode-fixed', mode === 'fixed');
        $form.toggleClass('invoice-billing-mode-hourly', mode === 'hourly');
        $form.find('.invoice-mode-btn').each(function() {
            var isActive = $(this).data('invoice-mode') === mode;
            $(this).toggleClass('btn-primary', isActive)
                .toggleClass('btn-outline-secondary', !isActive);
        });
        $form.find('.invoice-billing-basis').val(mode);
        if (mode === 'fixed') {
            $form.find('.invoice-hours').val('');
        }
        $form.find('.productitem_invoice tr').each(function() {
            recalcInvoiceTimesheetRow($(this), { keepGst: true });
        });
        grandtotalAccountTab_invoice();
    }

    function invoiceModeFromLines(records) {
        var hourly = false;
        var fixed = false;
        $.each(records || [], function(_, line) {
            var basis = String(line.billing_basis || '').toLowerCase();
            if (basis === 'hourly' || (parseFloat(line.hours) > 0)) {
                hourly = true;
            }
            if (basis === 'fixed') {
                fixed = true;
            }
        });
        if (hourly) {
            return 'hourly';
        }
        return fixed ? 'fixed' : 'hourly';
    }

    window.listOfInvoice = listOfInvoice;
    window.loadInvoicesForQuickReceipt = loadInvoicesForQuickReceipt;
    window.populateQuickReceiptOfficeForm = populateQuickReceiptOfficeForm;
    window.recalcInvoiceTimesheetRow = recalcInvoiceTimesheetRow;
    window.grandtotalAccountTab_invoice = grandtotalAccountTab_invoice;
    window.populateInvoiceLineRow = populateInvoiceLineRow;
    window.cloneInvoiceLineRow = cloneInvoiceLineRow;
    window.renderInvoiceEditLines = renderInvoiceEditLines;
    window.applyInvoiceBillingMode = applyInvoiceBillingMode;
    window.initInvoiceWorkDates = initInvoiceWorkDates;
    window.stripInvoiceLinePickers = stripClonedFlatpickr;
    window.invoiceLineRowIsClientBlank = invoiceLineRowIsClientBlank;

    $(document).on('click', '.invoice-mode-btn', function(e) {
        e.preventDefault();
        applyInvoiceBillingMode($(this).closest('form'), $(this).data('invoice-mode'));
    });

    $(document).on('click', '.invoice-date-mode-btn', function(e) {
        e.preventDefault();
        applyInvoiceRowDateMode($(this).closest('tr'), $(this).data('date-mode'));
    });

    $(document).on('keydown paste cut drop', '.invoice-work-date', function(e) {
        if (e.type === 'keydown') {
            var key = e.key || '';
            var allowed = key === 'Tab' || key === 'Enter' || key === 'Escape'
                || key.indexOf('Arrow') === 0
                || e.which === 9 || e.which === 13 || e.which === 27
                || e.which === 37 || e.which === 38 || e.which === 39 || e.which === 40;
            if (allowed) {
                return;
            }
        }
        e.preventDefault();
    });

    $(function() {
        initInvoiceWorkDates($('#invoice_receipt_form, #create_invoice_receipt'));
    });

    // createapplicationnewinvoice handler REMOVED - Create Invoice from Schedule flow unused
    // (payment schedule list removed; no /create-invoice route)

})(typeof jQuery !== 'undefined' ? jQuery : null);
