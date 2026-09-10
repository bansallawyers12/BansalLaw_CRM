/**
 * Timeline billing (Super Admin only) — category amounts from billing structure + statement totals.
 */
(function ($) {
    'use strict';

    var cachedRows = [];
    var scheduleByKey = {};

    function cfg() {
        return (window.ClientDetailConfig && window.ClientDetailConfig.timelineBilling) || {};
    }

    function billingEnabled() {
        return !!cfg().enabled && $('#activity-feed-billing-calc').length > 0;
    }

    function gstRate() {
        var rate = parseFloat(cfg().gstRate);
        return isFinite(rate) && rate >= 0 ? rate : 0.1;
    }

    function csrfToken() {
        return (window.ClientDetailConfig && window.ClientDetailConfig.csrfToken)
            || $('meta[name="csrf-token"]').attr('content')
            || '';
    }

    function money(n) {
        var v = Math.round((Number(n) || 0) * 100) / 100;
        return '$' + v.toLocaleString('en-AU', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function round2(n) {
        return Math.round((Number(n) || 0) * 100) / 100;
    }

    function gstFromInclusive(incl) {
        return round2(incl / 11);
    }

    function netFromInclusive(incl) {
        return round2(incl - gstFromInclusive(incl));
    }

    function loadScheduleFromConfig() {
        scheduleByKey = {};
        var structure = cfg().structure || [];
        structure.forEach(function (row) {
            if (!row || !row.key) {
                return;
            }
            scheduleByKey[row.key] = {
                key: row.key,
                label: row.label || row.key,
                applies_to: row.applies_to || '',
                amount_incl_gst: round2(row.amount_incl_gst || 0)
            };
        });
    }

    function amountForCategory(key) {
        if (!key || !scheduleByKey[key]) {
            return 0;
        }
        return round2(scheduleByKey[key].amount_incl_gst || 0);
    }

    function labelForCategory(key) {
        if (!key || !scheduleByKey[key]) {
            return '—';
        }
        return scheduleByKey[key].label || key;
    }

    function resolveCategory(activityType, subject) {
        var type = String(activityType || '').toLowerCase();
        var text = String(subject || '');
        if (type === 'stage' || type === 'lead_converted' || type === 'financial') {
            return null;
        }
        if (/^(?:stage updated|lead converted|pin(?:ned)?|unpinned?)\b/i.test(text)) {
            return null;
        }
        if (/\bemail\b/i.test(text) || type.indexOf('note-email') !== -1) {
            return 'email';
        }
        if (/\b(?:search|attending to|infotrack)\b/i.test(text)) {
            return 'search';
        }
        if (/\breview(?:ing|ed)?\b/i.test(text)) {
            return 'review';
        }
        if (type === 'sms' || type.indexOf('sms') === 0) {
            return 'sms';
        }
        if (type === 'document' || type.indexOf('document') === 0) {
            return 'document';
        }
        if (type === 'signature' || type.indexOf('signature') === 0) {
            return 'signature';
        }
        if (type === 'note' || type.indexOf('note') === 0) {
            return 'note';
        }
        if (type === 'activity' || type === '' || /\b(?:action|task)\b/i.test(text)) {
            return 'activity';
        }
        return null;
    }

    function shortDate(raw, ymd) {
        var text = String(raw || '').trim();
        var m = text.match(/^(\d{1,2}\s+[A-Za-z]{3}\s+\d{4})/);
        if (m) {
            return m[1];
        }
        if (ymd && /^\d{4}-\d{2}-\d{2}$/.test(ymd)) {
            var parts = ymd.split('-');
            var months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            var mi = parseInt(parts[1], 10) - 1;
            return parseInt(parts[2], 10) + ' ' + (months[mi] || parts[1]) + ' ' + parts[0];
        }
        return text || ymd || '';
    }

    function billingPanel() {
        var $all = $('#activity-feed-billing-panel');
        if ($all.length <= 1) {
            return $all;
        }
        var $open = $all.filter('.show').first();
        if ($open.length) {
            return $open;
        }
        var $onBody = $all.filter(function () {
            return this.parentNode === document.body;
        }).first();
        if ($onBody.length) {
            return $onBody;
        }
        // Prefer the panel that already has fee lines loaded
        var $withFees = $all.filter(function () {
            return $(this).find('#activity-feed-billing-fees-body tr[data-fee-row]').length > 0;
        }).first();
        if ($withFees.length) {
            return $withFees;
        }
        return $all.last();
    }

    function ensureBillingModalOnBody() {
        var $modal = billingPanel();
        if ($modal.length) {
            $('#activity-feed-billing-panel').not($modal).remove();
        }
        $modal = billingPanel();
        if ($modal.length && !$modal.parent().is('body')) {
            $modal.appendTo('body');
        }
        ensureStatementModalOnBody();
    }

    function isPanelOpen() {
        var $panel = billingPanel();
        if (!$panel.length) {
            return false;
        }
        return $panel.hasClass('show') || $panel.is(':visible');
    }

    function showAllNonBillable() {
        return $('#activity-feed-billing-show-all').is(':checked');
    }

    function updateCalcButton(open) {
        var $btn = $('#activity-feed-billing-calc');
        var $label = $btn.find('[data-billing-calc-label]');
        if (open) {
            $btn.addClass('is-active').attr('aria-expanded', 'true').attr('title', 'Close billing statement');
            $label.text('Close');
        } else {
            $btn.removeClass('is-active').attr('aria-expanded', 'false').attr('title', 'Calculate billing from timeline');
            $label.text('Billing');
        }
        $('#activity-feed').toggleClass('activity-feed--billing-open', !!open);
    }

    function showBillingModalDom($modal) {
        $modal.addClass('show').css('display', 'block').attr('aria-hidden', 'false');
        $('body').addClass('modal-open');
        if (!$('.modal-backdrop.activity-feed-billing-backdrop').length) {
            $('<div class="modal-backdrop fade show activity-feed-billing-backdrop"></div>').appendTo('body');
        }
    }

    function hideBillingModalDom($modal) {
        $modal.removeClass('show').css('display', 'none').attr('aria-hidden', 'true');
        $('.modal-backdrop.activity-feed-billing-backdrop').remove();
        if (!$('.modal.show').length) {
            $('body').removeClass('modal-open');
            $('.modal-backdrop').remove();
        }
    }

    function setPanelOpen(open) {
        var $panel = billingPanel();
        if (!$panel.length) {
            return;
        }
        ensureBillingModalOnBody();
        $panel = billingPanel();

        if (open) {
            updateCalcButton(true);
            renderStructureTable();
            if (!$('#activity-feed-billing-fees-body tr[data-fee-row]').length) {
                loadFromTimeline();
            } else {
                recalculate();
            }
            if (typeof $panel.modal === 'function') {
                $panel.modal('show');
            } else {
                showBillingModalDom($panel);
            }
        } else {
            closeStatementModal();
            if (typeof $panel.modal === 'function') {
                $panel.modal('hide');
            } else {
                hideBillingModalDom($panel);
            }
            updateCalcButton(false);
        }
    }

    function escapeHtml(str) {
        return String(str == null ? '' : str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function renderStructureTable() {
        var $body = $('#activity-feed-billing-structure-body');
        if (!$body.length) {
            return;
        }
        var keys = Object.keys(scheduleByKey);
        if (!keys.length) {
            $body.html('<tr class="activity-feed-billing-empty"><td colspan="4">No billing categories configured.</td></tr>');
            return;
        }
        var html = keys.map(function (key, index) {
            var row = scheduleByKey[key];
            return '<tr data-rate-key="' + escapeHtml(key) + '">' +
                '<td class="activity-feed-billing-sno-cell">' + (index + 1) + '</td>' +
                '<td><strong>' + escapeHtml(row.label) + '</strong></td>' +
                '<td class="activity-feed-billing-applies">' + escapeHtml(row.applies_to) + '</td>' +
                '<td class="text-right">' +
                '<div class="activity-feed-billing-amount-input">' +
                '<span class="activity-feed-billing-amount-prefix">$</span>' +
                '<input type="number" min="0" step="0.01" class="form-control form-control-sm billing-rate-amount" value="' + round2(row.amount_incl_gst).toFixed(2) + '" aria-label="Amount for ' + escapeHtml(row.label) + '">' +
                '</div></td></tr>';
        }).join('');
        $body.html(html);
    }

    function clientLabelForReport() {
        var c = window.ClientDetailConfig || {};
        var parts = [];
        if (c.clientFirstName) {
            parts.push(String(c.clientFirstName));
        }
        if (c.clientRef) {
            parts.push('(' + String(c.clientRef) + ')');
        }
        if (c.matterUniqueNo || c.matterRefNo) {
            parts.push('Matter ' + String(c.matterUniqueNo || c.matterRefNo));
        }
        return parts.length ? parts.join(' ') : 'Client file';
    }

    function buildStructureReportRows() {
        return Object.keys(scheduleByKey).map(function (key, index) {
            var row = scheduleByKey[key];
            var amount = round2(row.amount_incl_gst || 0);
            return {
                sno: index + 1,
                key: key,
                label: row.label,
                applies_to: row.applies_to,
                amount: amount
            };
        });
    }

    function buildStructureReportText() {
        var rows = buildStructureReportRows();
        var lines = [
            'BANSAL Lawyers — Billing structure',
            clientLabelForReport(),
            'Generated: ' + new Date().toLocaleString(),
            '',
            '#\tCategory\tWhere it applies\tAmount (incl. GST)'
        ];
        rows.forEach(function (row) {
            lines.push([
                String(row.sno),
                row.label,
                row.applies_to,
                money(row.amount)
            ].join('\t'));
        });
        return lines.join('\n');
    }

    function buildStructureReportHtml() {
        var rows = buildStructureReportRows();
        var body = rows.map(function (row) {
            return '<tr>' +
                '<td class="num">' + row.sno + '</td>' +
                '<td>' + escapeHtml(row.label) + '</td>' +
                '<td>' + escapeHtml(row.applies_to) + '</td>' +
                '<td class="num">' + money(row.amount) + '</td>' +
                '</tr>';
        }).join('');
        return '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Billing structure</title>' +
            '<style>' +
            'body{font-family:Segoe UI,Arial,sans-serif;color:#111;margin:24px;}' +
            'h1{font-size:18px;margin:0 0 4px;}' +
            '.meta{color:#555;font-size:12px;margin-bottom:18px;}' +
            'table{width:100%;border-collapse:collapse;font-size:13px;}' +
            'th,td{border-bottom:1px solid #ccc;padding:8px 6px;text-align:left;vertical-align:top;}' +
            'th{background:#1e3d60;color:#fff;font-size:11px;text-transform:uppercase;letter-spacing:.03em;}' +
            'td.num,th.num{text-align:right;font-variant-numeric:tabular-nums;white-space:nowrap;}' +
            'th.num:first-child,td.num:first-child{text-align:center;width:3rem;}' +
            '@media print{body{margin:12mm;}}' +
            '</style></head><body>' +
            '<h1>BANSAL Lawyers — Billing structure</h1>' +
            '<div class="meta">' + escapeHtml(clientLabelForReport()) + '<br>Generated: ' + escapeHtml(new Date().toLocaleString()) + '</div>' +
            '<table><thead><tr>' +
            '<th class="num">#</th><th>Category</th><th>Where it applies</th><th class="num">Amount (incl. GST)</th>' +
            '</tr></thead><tbody>' + body + '</tbody></table></body></html>';
    }

    function buildBillingInvoiceEmailHtml() {
        syncCacheFromDom();
        var feeState = readFeeLines();
        var disbRows = readDisbursements();
        var totals = calculate(feeState.lines, disbRows);
        var th = 'background:#1e3d60;color:#fff;padding:8px 6px;font-size:11px;text-transform:uppercase;letter-spacing:.03em;';
        var td = 'border-bottom:1px solid #d7e3ef;padding:8px 6px;vertical-align:top;';
        var tdRight = td + 'text-align:right;white-space:nowrap;font-variant-numeric:tabular-nums;';
        var tdCenter = td + 'text-align:center;width:40px;white-space:nowrap;font-variant-numeric:tabular-nums;';

        var feesBody;
        if (!feeState.detailLines.length) {
            feesBody = '<tr><td colspan="4" style="' + td + 'color:#5e7a90;font-style:italic;text-align:center;">No professional fee lines selected.</td></tr>';
        } else {
            feesBody = feeState.detailLines.map(function (line, index) {
                return '<tr>' +
                    '<td style="' + tdCenter + '">' + (index + 1) + '</td>' +
                    '<td style="' + td + 'white-space:nowrap;">' + escapeHtml(line.date) + '</td>' +
                    '<td style="' + td + '">' + escapeHtml(line.description) + '</td>' +
                    '<td style="' + tdRight + '">' + money(line.amount_incl_gst) + '</td>' +
                    '</tr>';
            }).join('');
        }

        var disbBody;
        if (!disbRows.length) {
            disbBody = '<tr><td colspan="5" style="' + td + 'color:#5e7a90;font-style:italic;text-align:center;">No disbursements.</td></tr>';
        } else {
            disbBody = disbRows.map(function (row, index) {
                var n = normalizeDisbursement(row);
                return '<tr>' +
                    '<td style="' + tdCenter + '">' + (index + 1) + '</td>' +
                    '<td style="' + td + '">' + escapeHtml(row.description || 'Disbursement') + '</td>' +
                    '<td style="' + tdRight + '">' + money(n.net) + '</td>' +
                    '<td style="' + tdRight + '">' + money(n.gst) + '</td>' +
                    '<td style="' + tdRight + '">' + money(n.incl_gst) + '</td>' +
                    '</tr>';
            }).join('');
        }

        return '<div style="font-family:Segoe UI,Arial,sans-serif;color:#1a2c40;font-size:14px;line-height:1.45;">' +
            '<p style="margin:0 0 4px;font-size:18px;font-weight:700;color:#1e3d60;">BANSAL Lawyers — Billing invoice</p>' +
            '<p style="margin:0 0 16px;color:#5e7a90;font-size:12px;">' +
            escapeHtml(clientLabelForReport()) + '<br>Generated: ' + escapeHtml(new Date().toLocaleString()) +
            '</p>' +

            '<p style="margin:0 0 8px;font-size:15px;font-weight:700;color:#1e3d60;">Professional Fees</p>' +
            '<table style="width:100%;border-collapse:collapse;font-size:13px;margin-bottom:18px;">' +
            '<thead><tr>' +
            '<th style="' + th + 'text-align:center;">#</th>' +
            '<th style="' + th + 'text-align:left;">Date</th>' +
            '<th style="' + th + 'text-align:left;">Description</th>' +
            '<th style="' + th + 'text-align:right;">Amount (Including GST)</th>' +
            '</tr></thead>' +
            '<tbody>' + feesBody + '</tbody>' +
            '<tfoot><tr>' +
            '<td colspan="3" style="padding:10px 6px 8px;text-align:right;font-weight:600;border-top:1px solid #1e3d60;">Fees subtotal (incl. GST)</td>' +
            '<td style="padding:10px 6px 8px;text-align:right;font-weight:700;border-top:1px solid #1e3d60;white-space:nowrap;">' + money(totals.fees_incl) + '</td>' +
            '</tr></tfoot>' +
            '</table>' +

            '<p style="margin:0 0 8px;font-size:15px;font-weight:700;color:#1e3d60;">Disbursements</p>' +
            '<table style="width:100%;border-collapse:collapse;font-size:13px;margin-bottom:18px;">' +
            '<thead><tr>' +
            '<th style="' + th + 'text-align:center;">#</th>' +
            '<th style="' + th + 'text-align:left;">Description</th>' +
            '<th style="' + th + 'text-align:right;">Amount</th>' +
            '<th style="' + th + 'text-align:right;">GST</th>' +
            '<th style="' + th + 'text-align:right;">Amount (Including GST)</th>' +
            '</tr></thead>' +
            '<tbody>' + disbBody + '</tbody>' +
            '<tfoot><tr>' +
            '<td colspan="2" style="padding:10px 6px 8px;text-align:right;font-weight:600;border-top:1px solid #1e3d60;">Disb. subtotal</td>' +
            '<td style="padding:10px 6px 8px;text-align:right;font-weight:700;border-top:1px solid #1e3d60;white-space:nowrap;">' + money(totals.disb_net) + '</td>' +
            '<td style="padding:10px 6px 8px;text-align:right;font-weight:700;border-top:1px solid #1e3d60;white-space:nowrap;">' + money(totals.disb_gst) + '</td>' +
            '<td style="padding:10px 6px 8px;text-align:right;font-weight:700;border-top:1px solid #1e3d60;white-space:nowrap;">' + money(totals.disb_incl) + '</td>' +
            '</tr></tfoot>' +
            '</table>' +

            '<table style="width:100%;max-width:360px;margin-left:auto;border-collapse:collapse;font-size:13px;">' +
            '<tr>' +
            '<td style="padding:4px 0;color:#5e7a90;">Total Fees and Disbursements</td>' +
            '<td style="padding:4px 0;text-align:right;font-weight:600;white-space:nowrap;">' + money(totals.total_net) + '</td>' +
            '</tr>' +
            '<tr>' +
            '<td style="padding:4px 0;color:#5e7a90;">GST Included</td>' +
            '<td style="padding:4px 0;text-align:right;font-weight:600;white-space:nowrap;">' + money(totals.gst_included) + '</td>' +
            '</tr>' +
            '<tr>' +
            '<td style="padding:8px 0 0;border-top:2px solid #1e3d60;font-weight:700;color:#1e3d60;">Total Amount Due</td>' +
            '<td style="padding:8px 0 0;border-top:2px solid #1e3d60;text-align:right;font-weight:700;color:#1e3d60;white-space:nowrap;font-size:15px;">' + money(totals.total_due) + '</td>' +
            '</tr>' +
            '</table>' +
            '</div><p><br></p>';
    }

    function setComposeMessageHtml(html) {
        var $ta = $('#compose_email_message');
        if (typeof window.setTinyMCEContent === 'function') {
            window.setTinyMCEContent('compose_email_message', html);
            return;
        }
        if (typeof tinymce !== 'undefined' && tinymce.get('compose_email_message')) {
            try {
                tinymce.get('compose_email_message').setContent(html);
                return;
            } catch (e) { /* fall through */ }
        }
        if ($ta.length) {
            $ta.val(html);
        }
    }

    function emailBillingStructure() {
        var $emailModal = $('#emailmodal');
        if (!$emailModal.length) {
            window.crmAlert('Compose Email is not available on this page.');
            return;
        }

        // Keep the active billing panel (with loaded fee lines) and drop any empty duplicates
        ensureBillingModalOnBody();
        syncCacheFromDom();
        var html = buildBillingInvoiceEmailHtml();
        var feeState = readFeeLines();
        var disbRows = readDisbursements();
        if (!feeState.detailLines.length && !disbRows.length) {
            window.crmAlert('No selected fee lines found. In Billing, click Reload, keep lines selected, then click Email again.');
            return;
        }

        var subjectBase = 'Billing invoice — ' + clientLabelForReport();
        var cfgClient = window.ClientDetailConfig || {};

        function openComposeWithInvoice() {
            var matterId = cfgClient.clientMatterId
                || $('#sel_matter_id_client_detail').val()
                || $('.general_matter_checkbox_client_detail:checked').val()
                || '';
            if (matterId) {
                $('#emailmodal #compose_client_matter_id').val(matterId);
            }

            var emailModalEl = $emailModal.get(0);
            if (emailModalEl) {
                emailModalEl.dataset.signaturePrefill = 'skip';
            }

            var $subj = $('#compose_email_subject');
            if (typeof window.ensureSubjectHasComposeReference === 'function') {
                $subj.val(window.ensureSubjectHasComposeReference(subjectBase));
            } else if (typeof window.prefillComposeSubjectWithReference === 'function') {
                window.prefillComposeSubjectWithReference(true);
                var existing = $.trim($subj.val() || '');
                if (existing && existing.toLowerCase().indexOf('billing invoice') === -1) {
                    $subj.val(existing + ' — Billing invoice');
                } else if (!existing) {
                    $subj.val(subjectBase);
                }
            } else {
                $subj.val(subjectBase);
            }

            var applied = false;
            var applyBody = function () {
                if (applied) {
                    return;
                }
                applied = true;
                setComposeMessageHtml(html);
            };

            $emailModal.off('shown.bs.modal.timelineBillingEmail')
                .one('shown.bs.modal.timelineBillingEmail', function () {
                    setTimeout(applyBody, 250);
                });

            if (typeof $emailModal.modal === 'function') {
                $emailModal.modal('show');
            } else {
                $emailModal.addClass('show').css('display', 'block').attr('aria-hidden', 'false');
                $('body').addClass('modal-open');
            }

            if ($emailModal.hasClass('show') || $emailModal.is(':visible')) {
                setTimeout(applyBody, 300);
            }
        }

        if (isPanelOpen()) {
            var $billing = billingPanel();
            $billing.off('hidden.bs.modal.timelineBillingEmail')
                .one('hidden.bs.modal.timelineBillingEmail', function () {
                    setTimeout(openComposeWithInvoice, 50);
                });
            setPanelOpen(false);
            setTimeout(function () {
                if (!$emailModal.hasClass('show') && !$emailModal.is(':visible')) {
                    openComposeWithInvoice();
                }
            }, 600);
            return;
        }

        openComposeWithInvoice();
    }

    function printBillingStructure() {
        var html = buildStructureReportHtml();
        var win = window.open('', '_blank', 'noopener,noreferrer,width=960,height=720');
        if (!win) {
            window.crmAlert('Please allow pop-ups to print the billing structure.');
            return;
        }
        win.document.open();
        win.document.write(html);
        win.document.close();
        win.focus();
        setTimeout(function () {
            try {
                win.print();
            } catch (e) { /* ignore */ }
        }, 250);
    }

    function shareBillingStructure() {
        var text = buildStructureReportText();
        var title = 'Billing structure — ' + clientLabelForReport();
        var $status = $('#activity-feed-billing-save-status');

        function showShareStatus(msg, ok) {
            if (!$status.length) {
                return;
            }
            $status.prop('hidden', false).removeAttr('hidden')
                .removeClass('is-error is-ok')
                .addClass(ok ? 'is-ok' : 'is-error')
                .text(msg);
        }

        if (navigator.share) {
            navigator.share({ title: title, text: text }).then(function () {
                showShareStatus('Billing structure shared.', true);
            }).catch(function (err) {
                if (err && err.name === 'AbortError') {
                    return;
                }
                copyStructureText(text, showShareStatus);
            });
            return;
        }
        copyStructureText(text, showShareStatus);
    }

    function copyStructureText(text, showShareStatus) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function () {
                showShareStatus('Billing structure copied to clipboard.', true);
            }).catch(function () {
                fallbackCopyStructureText(text, showShareStatus);
            });
            return;
        }
        fallbackCopyStructureText(text, showShareStatus);
    }

    function fallbackCopyStructureText(text, showShareStatus) {
        var $ta = $('<textarea>').val(text).css({ position: 'fixed', left: '-9999px', top: '0' }).appendTo('body');
        $ta[0].select();
        try {
            var ok = document.execCommand('copy');
            showShareStatus(ok ? 'Billing structure copied to clipboard.' : 'Could not copy billing structure.', ok);
        } catch (e) {
            showShareStatus('Could not copy billing structure.', false);
        }
        $ta.remove();
    }

    function readStructureAmountsFromDom() {
        var amounts = {};
        $('#activity-feed-billing-structure-body tr[data-rate-key]').each(function () {
            var key = $(this).attr('data-rate-key');
            var val = parseFloat($(this).find('.billing-rate-amount').val());
            amounts[key] = isFinite(val) ? round2(Math.max(0, val)) : 0;
        });
        return amounts;
    }

    function applyAmountsToSchedule(amounts) {
        Object.keys(amounts).forEach(function (key) {
            if (!scheduleByKey[key]) {
                return;
            }
            scheduleByKey[key].amount_incl_gst = round2(amounts[key]);
        });
        if (window.ClientDetailConfig && window.ClientDetailConfig.timelineBilling) {
            window.ClientDetailConfig.timelineBilling.structure = Object.keys(scheduleByKey).map(function (key) {
                return scheduleByKey[key];
            });
        }
    }

    function saveRates() {
        var amounts = readStructureAmountsFromDom();
        var url = cfg().saveRatesUrl || '/clients/timeline-billing-rates';
        var $status = $('#activity-feed-billing-save-status');
        var $btn = $('#activity-feed-billing-save-rates');
        $btn.prop('disabled', true);
        $status.prop('hidden', false).removeAttr('hidden').removeClass('is-error is-ok').text('Saving…');

        $.ajax({
            url: url,
            method: 'POST',
            data: {
                _token: csrfToken(),
                rates: amounts
            },
            success: function (res) {
                if (!res || !res.status) {
                    $status.addClass('is-error').text((res && res.message) || 'Could not save amounts.');
                    return;
                }
                if (res.structure && res.structure.length) {
                    scheduleByKey = {};
                    res.structure.forEach(function (row) {
                        scheduleByKey[row.key] = {
                            key: row.key,
                            label: row.label,
                            applies_to: row.applies_to,
                            amount_incl_gst: round2(row.amount_incl_gst || 0)
                        };
                    });
                } else {
                    applyAmountsToSchedule(amounts);
                }
                renderStructureTable();
                // Refresh fee line rates from saved schedule
                syncCacheFromDom();
                cachedRows.forEach(function (row) {
                    if (row.category) {
                        row.rate = amountForCategory(row.category);
                    }
                });
                renderFeeRows(cachedRows);
                recalculate();
                $status.addClass('is-ok').text('Amounts saved. Fee lines updated.');
            },
            error: function (xhr) {
                var msg = (xhr.responseJSON && xhr.responseJSON.message) || 'Could not save amounts.';
                $status.addClass('is-error').text(msg);
            },
            complete: function () {
                $btn.prop('disabled', false);
            }
        });
    }

    function collectFeeRowsFromFeed() {
        var rows = [];
        $('#activity-feed .feed-list > .feed-item').each(function () {
            var $item = $(this);
            if ($item.hasClass('feed-item--loading')
                || $item.hasClass('feed-item--empty')
                || $item.hasClass('feed-item-no-results')
                || $item.hasClass('feed-load-sentinel')
                || $item.hasClass('feed-item--filter-hidden')) {
                return;
            }

            var cls = $item.attr('class') || '';
            var typeMatch = cls.match(/\bactivity-type-([a-z0-9_]+)/i);
            var activityType = typeMatch ? typeMatch[1] : 'activity';
            var subject = $.trim($item.find('.feed-item-summary-text').first().text()
                || $item.find('.feed-content strong').first().text()
                || '');
            if (!subject) {
                return;
            }

            var meta = $.trim($item.find('.feed-item-summary-meta').first().text() || '');
            var dateText = '';
            if (meta.indexOf('·') !== -1) {
                dateText = $.trim(meta.split('·').pop());
            }
            var ymd = $item.attr('data-created-at') || '';
            var idAttr = $item.attr('id') || '';
            var idMatch = idAttr.match(/^activity_(\d+)$/);
            var category = resolveCategory(activityType, subject);
            var rate = category ? amountForCategory(category) : 0;
            var qty = category && rate > 0 ? 1 : 0;

            rows.push({
                id: idMatch ? idMatch[1] : '',
                date: shortDate(dateText, ymd),
                description: subject,
                activityType: activityType,
                category: category,
                rate: rate,
                qty: qty,
                included: qty > 0,
                billable: !!category && rate > 0
            });
        });
        return rows;
    }

    function rowsToRender(rows) {
        if (showAllNonBillable()) {
            return rows;
        }
        return rows.filter(function (row) {
            return row.billable || row.included;
        });
    }

    function renderFeeRows(rows) {
        var $body = $('#activity-feed-billing-fees-body');
        if (!$body.length) {
            return;
        }
        var visible = rowsToRender(rows);
        if (!visible.length) {
            var msg = rows.length
                ? 'No billable timeline items for the current structure. Adjust amounts or turn on “Show non-billable”.'
                : 'No timeline items available. Refresh the feed, then click Reload.';
            $body.html('<tr class="activity-feed-billing-empty"><td colspan="7">' + msg + '</td></tr>');
            syncSelectAllState();
            return;
        }
        var html = visible.map(function (row, idx) {
            var rate = row.category ? amountForCategory(row.category) : round2(row.rate || 0);
            var qty = Number(row.qty) || 0;
            var amount = row.included ? round2(qty * rate) : 0;
            var muted = row.included ? '' : ' is-excluded';
            return '<tr data-fee-row="' + idx + '" data-activity-id="' + escapeHtml(row.id) + '" data-category="' + escapeHtml(row.category || '') + '" class="' + muted + '">' +
                '<td class="activity-feed-billing-col-include"><input type="checkbox" class="billing-fee-include" ' + (row.included ? 'checked' : '') + ' aria-label="Include fee line"></td>' +
                '<td class="billing-fee-date">' + escapeHtml(row.date) + '</td>' +
                '<td class="billing-fee-desc" title="' + escapeHtml(row.description) + '">' + escapeHtml(row.description) + '</td>' +
                '<td class="billing-fee-category">' + escapeHtml(labelForCategory(row.category)) + '</td>' +
                '<td class="text-right"><input type="number" min="0" step="1" class="form-control form-control-sm billing-fee-qty" value="' + qty + '" aria-label="Quantity" ' + (row.category ? '' : 'disabled') + '></td>' +
                '<td class="text-right billing-fee-rate" data-rate="' + rate + '">' + money(rate) + '</td>' +
                '<td class="text-right billing-fee-amount">' + money(amount) + '</td>' +
                '</tr>';
        }).join('');
        $body.html(html);
        syncSelectAllState();
    }

    function syncCacheFromDom() {
        if (!cachedRows.length) {
            return;
        }
        var byId = {};
        cachedRows.forEach(function (row, i) {
            if (row.id) {
                byId[String(row.id)] = i;
            }
        });
        $('#activity-feed-billing-fees-body tr[data-fee-row]').each(function () {
            var $tr = $(this);
            var id = String($tr.attr('data-activity-id') || '');
            var idx = byId[id];
            if (idx == null) {
                return;
            }
            var qty = parseFloat($tr.find('.billing-fee-qty').val());
            cachedRows[idx].qty = isFinite(qty) ? qty : 0;
            cachedRows[idx].included = $tr.find('.billing-fee-include').is(':checked');
            cachedRows[idx].rate = amountForCategory(cachedRows[idx].category);
            cachedRows[idx].billable = !!cachedRows[idx].category && cachedRows[idx].rate > 0;
        });
    }

    function loadFromTimeline() {
        cachedRows = collectFeeRowsFromFeed();
        renderFeeRows(cachedRows);
        recalculate();
    }

    function ensureDisbEmptyState() {
        var $body = $('#activity-feed-billing-disb-body');
        var hasRows = $body.find('tr[data-disb-row]').length > 0;
        if (hasRows) {
            $body.find('[data-disb-empty]').remove();
            return;
        }
        if (!$body.find('[data-disb-empty]').length) {
            $body.html('<tr class="activity-feed-billing-empty" data-disb-empty="1"><td colspan="5">No disbursements — use Disbursement to add one (e.g. InfoTrack).</td></tr>');
        }
    }

    function addDisbursementRow(preset) {
        preset = preset || {};
        var $body = $('#activity-feed-billing-disb-body');
        $body.find('[data-disb-empty]').remove();
        var net = preset.net != null ? Number(preset.net) : '';
        var gst = preset.gst != null ? Number(preset.gst) : '';
        var desc = preset.description || '';
        var incl = '';
        if (net !== '' && gst !== '') {
            incl = round2(Number(net) + Number(gst));
        } else if (net !== '' && gst === '') {
            gst = round2(Number(net) * gstRate());
            incl = round2(Number(net) + Number(gst));
        }
        var row = '<tr data-disb-row="1">' +
            '<td><input type="text" class="form-control form-control-sm billing-disb-desc" value="' + escapeHtml(desc) + '" placeholder="e.g. InfoTrack search fee" aria-label="Disbursement description"></td>' +
            '<td class="text-right"><input type="number" min="0" step="0.01" class="form-control form-control-sm billing-disb-net" value="' + (net === '' ? '' : net) + '" aria-label="Net amount"></td>' +
            '<td class="text-right"><input type="number" min="0" step="0.01" class="form-control form-control-sm billing-disb-gst" value="' + (gst === '' ? '' : gst) + '" aria-label="GST"></td>' +
            '<td class="text-right"><input type="number" min="0" step="0.01" class="form-control form-control-sm billing-disb-incl" value="' + (incl === '' ? '' : incl) + '" aria-label="Amount including GST"></td>' +
            '<td class="activity-feed-billing-col-remove"><button type="button" class="btn btn-sm btn-link text-danger billing-disb-remove" title="Remove" aria-label="Remove disbursement"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button></td>' +
            '</tr>';
        $body.append(row);
        ensureDisbEmptyState();
        recalculate();
        $body.find('tr[data-disb-row]').last().find('.billing-disb-desc').trigger('focus');
    }

    function readFeeLines() {
        var lines = [];
        var selectedLines = 0;
        var detailLines = [];
        var $panel = billingPanel();
        var $rows = $panel.find('#activity-feed-billing-fees-body tr[data-fee-row]');
        if (!$rows.length) {
            $rows = $('#activity-feed-billing-fees-body tr[data-fee-row]');
        }
        $rows.each(function () {
            var $tr = $(this);
            if (!$tr.find('.billing-fee-include').is(':checked')) {
                return;
            }
            var qty = parseFloat($tr.find('.billing-fee-qty').val());
            var rate = parseFloat($tr.find('.billing-fee-rate').attr('data-rate'));
            var amountText = $.trim($tr.find('.billing-fee-amount').text() || '').replace(/[^0-9.-]/g, '');
            var amountFromText = parseFloat(amountText);
            var amount = 0;
            if (isFinite(qty) && qty > 0 && isFinite(rate) && rate > 0) {
                amount = round2(qty * rate);
            } else if (isFinite(amountFromText) && amountFromText > 0) {
                amount = round2(amountFromText);
            } else {
                return;
            }
            lines.push({ amount_incl_gst: amount });
            detailLines.push({
                date: $.trim($tr.find('.billing-fee-date').text() || ''),
                description: $.trim($tr.find('.billing-fee-desc').attr('title') || $tr.find('.billing-fee-desc').text() || ''),
                amount_incl_gst: amount
            });
            selectedLines += 1;
        });

        // Fallback: use in-memory timeline rows if the DOM panel was replaced/empty
        if (!detailLines.length && cachedRows.length) {
            cachedRows.forEach(function (row) {
                if (!row.included) {
                    return;
                }
                var rate = row.category ? amountForCategory(row.category) : round2(row.rate || 0);
                var qty = Number(row.qty) || 0;
                var amount = round2(qty * rate);
                if (!(amount > 0)) {
                    return;
                }
                lines.push({ amount_incl_gst: amount });
                detailLines.push({
                    date: row.date || '',
                    description: row.description || '',
                    amount_incl_gst: amount
                });
                selectedLines += 1;
            });
        }

        return { lines: lines, selectedLines: selectedLines, detailLines: detailLines };
    }

    function readDisbursements() {
        var rows = [];
        var $panel = billingPanel();
        var $disbRows = $panel.find('#activity-feed-billing-disb-body tr[data-disb-row]');
        if (!$disbRows.length) {
            $disbRows = $('#activity-feed-billing-disb-body tr[data-disb-row]');
        }
        $disbRows.each(function () {
            var $tr = $(this);
            var netVal = $tr.find('.billing-disb-net').val();
            var gstVal = $tr.find('.billing-disb-gst').val();
            var inclVal = $tr.find('.billing-disb-incl').val();
            var desc = $.trim($tr.find('.billing-disb-desc').val() || '');
            var net = netVal === '' ? null : parseFloat(netVal);
            var gst = gstVal === '' ? null : parseFloat(gstVal);
            var incl = inclVal === '' ? null : parseFloat(inclVal);
            if ((net == null || !isFinite(net)) && (incl == null || !isFinite(incl))) {
                return;
            }
            rows.push({ description: desc || 'Disbursement', net: net, gst: gst, amount_incl_gst: incl });
        });
        return rows;
    }

    function normalizeDisbursement(row) {
        var net = row.net;
        var gst = row.gst;
        var incl = row.amount_incl_gst;
        if (net != null && isFinite(net) && gst != null && isFinite(gst) && (incl == null || !isFinite(incl))) {
            incl = round2(net + gst);
        } else if (net != null && isFinite(net) && incl != null && isFinite(incl) && (gst == null || !isFinite(gst))) {
            gst = round2(incl - net);
        } else if (gst != null && isFinite(gst) && incl != null && isFinite(incl) && (net == null || !isFinite(net))) {
            net = round2(incl - gst);
        } else if (net != null && isFinite(net) && (gst == null || !isFinite(gst)) && (incl == null || !isFinite(incl))) {
            gst = round2(net * gstRate());
            incl = round2(net + gst);
        } else if (incl != null && isFinite(incl) && (net == null || !isFinite(net)) && (gst == null || !isFinite(gst))) {
            gst = gstFromInclusive(incl);
            net = netFromInclusive(incl);
        } else {
            net = isFinite(net) ? net : 0;
            gst = isFinite(gst) ? gst : 0;
            incl = isFinite(incl) ? incl : round2(net + gst);
        }
        return { net: round2(net), gst: round2(gst), incl_gst: round2(incl) };
    }

    function calculate(feeLines, disbursements) {
        var feesIncl = 0;
        feeLines.forEach(function (line) {
            feesIncl += Number(line.amount_incl_gst) || 0;
        });
        feesIncl = round2(feesIncl);
        var feesGst = gstFromInclusive(feesIncl);
        var feesNet = netFromInclusive(feesIncl);

        var disbNet = 0;
        var disbGst = 0;
        var disbIncl = 0;
        disbursements.forEach(function (row) {
            var n = normalizeDisbursement(row);
            disbNet += n.net;
            disbGst += n.gst;
            disbIncl += n.incl_gst;
        });
        disbNet = round2(disbNet);
        disbGst = round2(disbGst);
        disbIncl = round2(disbIncl);

        return {
            fees_incl: feesIncl,
            fees_net: feesNet,
            fees_gst: feesGst,
            disb_net: disbNet,
            disb_gst: disbGst,
            disb_incl: disbIncl,
            total_net: round2(feesNet + disbNet),
            gst_included: round2(feesGst + disbGst),
            total_due: round2(feesIncl + disbIncl)
        };
    }

    function updateFeeLineAmount($tr) {
        var qty = parseFloat($tr.find('.billing-fee-qty').val()) || 0;
        var rate = parseFloat($tr.find('.billing-fee-rate').attr('data-rate')) || 0;
        var included = $tr.find('.billing-fee-include').is(':checked');
        var amount = included ? round2(qty * rate) : 0;
        $tr.find('.billing-fee-amount').text(money(amount));
        $tr.toggleClass('is-excluded', !included);
    }

    function syncSelectAllState() {
        var $boxes = $('#activity-feed-billing-fees-body tr[data-fee-row] .billing-fee-include');
        var $all = $('#activity-feed-billing-select-all');
        if (!$all.length) {
            return;
        }
        if (!$boxes.length) {
            $all.prop('checked', false).prop('indeterminate', false);
            return;
        }
        var checked = $boxes.filter(':checked').length;
        $all.prop('checked', checked === $boxes.length);
        $all.prop('indeterminate', checked > 0 && checked < $boxes.length);
    }

    function updateSelectionMeta(selectedLines) {
        var $meta = $('[data-billing-selection-meta]');
        $meta.text(selectedLines + (selectedLines === 1 ? ' line selected' : ' lines selected'));
        $meta.toggleClass('is-clickable', selectedLines > 0);
        $meta.attr('title', selectedLines > 0 ? 'Show selected lines as statement' : '');
        $meta.attr('role', selectedLines > 0 ? 'button' : null);
        $meta.attr('tabindex', selectedLines > 0 ? '0' : null);
    }

    function renderTotals(totals) {
        var map = {
            fees_incl: totals.fees_incl,
            disb_net: totals.disb_net,
            disb_gst: totals.disb_gst,
            disb_incl: totals.disb_incl,
            total_net: totals.total_net,
            gst_included: totals.gst_included,
            total_due: totals.total_due
        };
        Object.keys(map).forEach(function (key) {
            $('[data-billing-field="' + key + '"]').text(money(map[key]));
        });

        var $summary = $('#activity-feed-billing-summary');
        var hasAny = totals.total_due > 0 || totals.total_net > 0 || totals.gst_included > 0
            || $('#activity-feed-billing-fees-body tr[data-fee-row] .billing-fee-include:checked').length > 0
            || $('#activity-feed-billing-disb-body tr[data-disb-row]').length > 0;
        if (hasAny) {
            $summary.prop('hidden', false).removeAttr('hidden');
        }
    }

    function renderStatementPreview() {
        ensureStatementModalOnBody();
        var feeState = readFeeLines();
        var disbRows = readDisbursements();
        var totals = calculate(feeState.lines, disbRows);

        var $feesBody = $('#activity-feed-billing-statement-fees');
        if (!$feesBody.length) {
            return totals;
        }
        if (!feeState.detailLines.length) {
            $feesBody.html('<tr><td colspan="3" class="billing-statement-empty">No professional fee lines selected.</td></tr>');
        } else {
            $feesBody.html(feeState.detailLines.map(function (line) {
                return '<tr>' +
                    '<td class="billing-statement-col-date">' + escapeHtml(line.date) + '</td>' +
                    '<td>' + escapeHtml(line.description) + '</td>' +
                    '<td class="text-right">' + money(line.amount_incl_gst) + '</td>' +
                    '</tr>';
            }).join(''));
        }

        var $disbBody = $('#activity-feed-billing-statement-disb');
        if (!disbRows.length) {
            $disbBody.html('<tr><td colspan="4" class="billing-statement-empty">No disbursements.</td></tr>');
        } else {
            $disbBody.html(disbRows.map(function (row) {
                var n = normalizeDisbursement(row);
                return '<tr>' +
                    '<td>' + escapeHtml(row.description || 'Disbursement') + '</td>' +
                    '<td class="text-right">' + money(n.net) + '</td>' +
                    '<td class="text-right">' + money(n.gst) + '</td>' +
                    '<td class="text-right">' + money(n.incl_gst) + '</td>' +
                    '</tr>';
            }).join(''));
        }

        var map = {
            fees_incl: totals.fees_incl,
            disb_net: totals.disb_net,
            disb_gst: totals.disb_gst,
            disb_incl: totals.disb_incl,
            total_net: totals.total_net,
            gst_included: totals.gst_included,
            total_due: totals.total_due
        };
        Object.keys(map).forEach(function (key) {
            $('#activity-feed-billing-statement-modal [data-statement-field="' + key + '"]').text(money(map[key]));
        });

        return totals;
    }

    function ensureStatementModalOnBody() {
        var $modals = $('#activity-feed-billing-statement-modal');
        if ($modals.length > 1) {
            $modals.slice(1).remove();
        }
        var $modal = $('#activity-feed-billing-statement-modal');
        if ($modal.length && !$modal.parent().is('body')) {
            $modal.appendTo('body');
        }
    }

    function openStatementModal() {
        ensureStatementModalOnBody();
        renderStatementPreview();
        var $modal = $('#activity-feed-billing-statement-modal');
        if (!$modal.length) {
            return;
        }
        if (typeof $modal.modal === 'function') {
            $modal.off('shown.bs.modal.timelineBillingStatement')
                .on('shown.bs.modal.timelineBillingStatement', function () {
                    var $backs = $('.modal-backdrop');
                    if ($backs.length > 1) {
                        $backs.last().css('z-index', 12075);
                    }
                    $modal.css('z-index', 12100);
                });
            $modal.modal('show');
        } else {
            $modal.addClass('show').css({ display: 'flex', zIndex: 12100 }).attr('aria-hidden', 'false');
            $('body').addClass('modal-open');
            if (!$('.modal-backdrop.activity-feed-billing-statement-backdrop').length) {
                $('<div class="modal-backdrop fade show activity-feed-billing-statement-backdrop" style="z-index:12075"></div>').appendTo('body');
            }
        }
    }

    function closeStatementModal() {
        var $modal = $('#activity-feed-billing-statement-modal');
        if (!$modal.length) {
            return;
        }
        if (typeof $modal.modal === 'function') {
            $modal.modal('hide');
        } else {
            $modal.removeClass('show').css('display', 'none').attr('aria-hidden', 'true');
            $('.modal-backdrop.activity-feed-billing-statement-backdrop').remove();
            if (!$('.modal.show').length) {
                $('body').removeClass('modal-open');
            }
        }
    }

    function isStatementView() {
        var $modal = $('#activity-feed-billing-statement-modal');
        return $modal.length > 0 && ($modal.hasClass('show') || $modal.is(':visible'));
    }

    function setStatementView(show) {
        if (show) {
            openStatementModal();
        } else {
            closeStatementModal();
        }
    }

    function recalculate() {
        $('#activity-feed-billing-fees-body tr[data-fee-row]').each(function () {
            updateFeeLineAmount($(this));
        });
        var feeState = readFeeLines();
        var totals = calculate(feeState.lines, readDisbursements());
        updateSelectionMeta(feeState.selectedLines);
        renderTotals(totals);
        syncSelectAllState();
        if (isStatementView()) {
            renderStatementPreview();
        }
        return totals;
    }

    function clearBilling() {
        cachedRows = [];
        closeStatementModal();
        $('#activity-feed-billing-fees-body').html(
            '<tr class="activity-feed-billing-empty"><td colspan="7">Open Billing to load timeline items, or click Reload.</td></tr>'
        );
        $('#activity-feed-billing-disb-body').html(
            '<tr class="activity-feed-billing-empty" data-disb-empty="1"><td colspan="5">No disbursements — use Disbursement to add one (e.g. InfoTrack).</td></tr>'
        );
        $('#activity-feed-billing-show-all').prop('checked', false);
        $('#activity-feed-billing-select-all').prop('checked', false).prop('indeterminate', false);
        updateSelectionMeta(0);
        renderTotals({
            fees_incl: 0, disb_net: 0, disb_gst: 0, disb_incl: 0,
            total_net: 0, gst_included: 0, total_due: 0
        });
        $('#activity-feed-billing-summary').prop('hidden', true).attr('hidden', 'hidden');
    }

    function bindHandlers() {
        $(document).off('click.timelineBilling', '#activity-feed-billing-calc')
            .on('click.timelineBilling', '#activity-feed-billing-calc', function (e) {
                e.preventDefault();
                setPanelOpen(!isPanelOpen());
            });

        $(document).off('click.timelineBilling', '#activity-feed-billing-close')
            .on('click.timelineBilling', '#activity-feed-billing-close', function (e) {
                e.preventDefault();
                setPanelOpen(false);
            });

        $(document).off('click.timelineBilling', '#activity-feed-billing-reload')
            .on('click.timelineBilling', '#activity-feed-billing-reload', function (e) {
                e.preventDefault();
                loadFromTimeline();
            });

        $(document).off('click.timelineBilling', '#activity-feed-billing-add-disb')
            .on('click.timelineBilling', '#activity-feed-billing-add-disb', function (e) {
                e.preventDefault();
                addDisbursementRow();
            });

        $(document).off('click.timelineBilling', '#activity-feed-billing-clear')
            .on('click.timelineBilling', '#activity-feed-billing-clear', function (e) {
                e.preventDefault();
                clearBilling();
            });

        $(document).off('click.timelineBilling', '#activity-feed-billing-show-selected')
            .on('click.timelineBilling', '#activity-feed-billing-show-selected', function (e) {
                e.preventDefault();
                openStatementModal();
            });

        $(document).off('click.timelineBilling keydown.timelineBilling', '[data-billing-selection-meta]')
            .on('click.timelineBilling', '[data-billing-selection-meta].is-clickable', function (e) {
                e.preventDefault();
                openStatementModal();
            })
            .on('keydown.timelineBilling', '[data-billing-selection-meta].is-clickable', function (e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    openStatementModal();
                }
            });

        $(document).off('click.timelineBilling', '#activity-feed-billing-save-rates')
            .on('click.timelineBilling', '#activity-feed-billing-save-rates', function (e) {
                e.preventDefault();
                saveRates();
            });

        $(document).off('click.timelineBilling', '#activity-feed-billing-print-structure')
            .on('click.timelineBilling', '#activity-feed-billing-print-structure', function (e) {
                e.preventDefault();
                printBillingStructure();
            });

        $(document).off('click.timelineBilling', '#activity-feed-billing-share-structure')
            .on('click.timelineBilling', '#activity-feed-billing-share-structure', function (e) {
                e.preventDefault();
                shareBillingStructure();
            });

        $(document).off('click.timelineBilling', '#activity-feed-billing-email-structure')
            .on('click.timelineBilling', '#activity-feed-billing-email-structure', function (e) {
                e.preventDefault();
                emailBillingStructure();
            });

        $(document).off('change.timelineBillingShowAll', '#activity-feed-billing-show-all')
            .on('change.timelineBillingShowAll', '#activity-feed-billing-show-all', function () {
                syncCacheFromDom();
                if (!cachedRows.length) {
                    cachedRows = collectFeeRowsFromFeed();
                }
                renderFeeRows(cachedRows);
                recalculate();
            });

        $(document).off('change.timelineBillingSelectAll', '#activity-feed-billing-select-all')
            .on('change.timelineBillingSelectAll', '#activity-feed-billing-select-all', function () {
                var checked = $(this).is(':checked');
                $('#activity-feed-billing-fees-body tr[data-fee-row] .billing-fee-include').prop('checked', checked);
                recalculate();
            });

        $(document).off('click.timelineBilling', '.billing-disb-remove')
            .on('click.timelineBilling', '.billing-disb-remove', function (e) {
                e.preventDefault();
                $(this).closest('tr').remove();
                ensureDisbEmptyState();
                recalculate();
            });

        $(document).off('input.timelineBilling change.timelineBilling', '#activity-feed-billing-panel')
            .on('input.timelineBilling change.timelineBilling', '#activity-feed-billing-panel .billing-fee-qty, #activity-feed-billing-panel .billing-fee-include', function () {
                recalculate();
            })
            .on('input.timelineBilling change.timelineBilling', '#activity-feed-billing-panel .billing-disb-net', function () {
                var $tr = $(this).closest('tr');
                var net = parseFloat($(this).val());
                if (!isFinite(net)) {
                    recalculate();
                    return;
                }
                var $gst = $tr.find('.billing-disb-gst');
                var $incl = $tr.find('.billing-disb-incl');
                if ($gst.val() === '' || $gst.data('auto') === true) {
                    var gst = round2(net * gstRate());
                    $gst.val(gst.toFixed(2)).data('auto', true);
                    $incl.val(round2(net + gst).toFixed(2));
                } else {
                    var gstManual = parseFloat($gst.val()) || 0;
                    $incl.val(round2(net + gstManual).toFixed(2));
                }
                recalculate();
            })
            .on('input.timelineBilling change.timelineBilling', '#activity-feed-billing-panel .billing-disb-gst', function () {
                $(this).data('auto', false);
                var $tr = $(this).closest('tr');
                var net = parseFloat($tr.find('.billing-disb-net').val());
                var gst = parseFloat($(this).val());
                if (isFinite(net) && isFinite(gst)) {
                    $tr.find('.billing-disb-incl').val(round2(net + gst).toFixed(2));
                }
                recalculate();
            })
            .on('input.timelineBilling change.timelineBilling', '#activity-feed-billing-panel .billing-disb-incl', function () {
                var $tr = $(this).closest('tr');
                var incl = parseFloat($(this).val());
                var net = parseFloat($tr.find('.billing-disb-net').val());
                if (isFinite(incl) && isFinite(net)) {
                    $tr.find('.billing-disb-gst').val(round2(incl - net).toFixed(2)).data('auto', false);
                }
                recalculate();
            });
    }

    function init() {
        if (!billingEnabled()) {
            return;
        }
        loadScheduleFromConfig();
        ensureBillingModalOnBody();
        updateCalcButton(isPanelOpen());
        bindHandlers();

        $(document).off('hidden.bs.modal.timelineBilling', '#activity-feed-billing-panel')
            .on('hidden.bs.modal.timelineBilling', '#activity-feed-billing-panel', function () {
                closeStatementModal();
                updateCalcButton(false);
            })
            .off('shown.bs.modal.timelineBilling', '#activity-feed-billing-panel')
            .on('shown.bs.modal.timelineBilling', '#activity-feed-billing-panel', function () {
                updateCalcButton(true);
            });
    }

    $(document).ready(init);
    $(document).on('clientTabContentLoaded', function (e, tabId) {
        if (String(tabId || '').toLowerCase() === 'activityfeed') {
            init();
        }
    });

    window.TimelineBilling = {
        init: init,
        recalculate: recalculate,
        loadFromTimeline: loadFromTimeline,
        calculate: calculate,
        openStatementModal: openStatementModal
    };
})(jQuery);
