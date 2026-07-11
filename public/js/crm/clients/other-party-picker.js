/**
 * Shared other-party row UI for matter forms (create, edit modal, quick add).
 */
(function (global) {
    'use strict';

    function partyRoleOptionsHtml(stream) {
        var map = global.MATTER_PARTY_ROLES_BY_STREAM || {};
        var roles = map[stream] || map.general || {};
        var html = '<option value="">— Select role —</option>';
        Object.keys(roles).forEach(function (k) {
            html += '<option value="' + k + '">' + roles[k] + '</option>';
        });
        return html;
    }

    function initOtherPartyTomSelect(selectEl, searchUrl) {
        if (selectEl && typeof global.destroyTS === 'function') {
            global.destroyTS(selectEl);
        }
        if (!selectEl || typeof global.initTS !== 'function' || typeof global.buildContactPersonSearchTomSelectConfig !== 'function') {
            return;
        }
        var cfg = global.buildContactPersonSearchTomSelectConfig({
            url: searchUrl,
            placeholder: 'Search other party (name, phone, email, ID)...',
            dropdownParent: 'body'
        });
        global.initTS(selectEl, cfg);
    }

    /**
     * Append an other-party row to a container.
     *
     * @param {HTMLElement|string} container
     * @param {object} opts
     * @param {string} opts.rowClass - CSS class on row wrapper
     * @param {string} opts.searchUrl
     * @param {string} opts.stream - matter stream for role dropdown
     * @param {object} [opts.data] - prefill { opposing_lead_id, name, party_role, rep_* }
     */
    function appendOtherPartyRow(container, opts) {
        opts = opts || {};
        var wrap = typeof container === 'string' ? document.querySelector(container) : container;
        if (!wrap) return null;

        var rowClass = opts.rowClass || 'opp-party-row';
        var data = opts.data || {};
        var stream = opts.stream || 'general';

        var row = document.createElement('div');
        row.className = rowClass + ' opp-party-row border rounded p-2 mb-2';
        row.style.background = '#fff';

        row.innerHTML =
            '<div class="row g-2 align-items-end">' +
            '<div class="col-md-5">' +
            '<label class="small mb-0 d-block">Other party <span class="text-danger">*</span></label>' +
            '<select class="form-control opp-party-lead-select" data-placeholder="Search other party..."></select>' +
            '<input type="hidden" class="opp-party-lead-id" value="">' +
            '<input type="hidden" class="opp-party-name" value="">' +
            '</div>' +
            '<div class="col-md-3">' +
            '<label class="small mb-0 d-block">Their role <span class="text-danger">*</span></label>' +
            '<select class="form-control opp-party-role-select">' + partyRoleOptionsHtml(stream) + '</select>' +
            '</div>' +
            '<div class="col-md-2">' +
            '<label class="small mb-0 d-block">&nbsp;</label>' +
            '<button type="button" class="btn btn-sm btn-outline-danger w-100 opp-party-remove">Remove</button>' +
            '</div>' +
            '</div>' +
            '<div class="row g-2 mt-1 opp-party-rep-row">' +
            '<div class="col-md-3"><label class="small mb-0">Solicitor firm</label><input type="text" class="form-control form-control-sm opp-party-rep-firm" maxlength="255" placeholder="Firm name"></div>' +
            '<div class="col-md-3"><label class="small mb-0">Solicitor name</label><input type="text" class="form-control form-control-sm opp-party-rep-name" maxlength="255"></div>' +
            '<div class="col-md-2"><label class="small mb-0">Email</label><input type="email" class="form-control form-control-sm opp-party-rep-email" maxlength="255"></div>' +
            '<div class="col-md-2"><label class="small mb-0">Phone</label><input type="text" class="form-control form-control-sm opp-party-rep-phone" maxlength="64"></div>' +
            '<div class="col-md-2"><label class="small mb-0">Notes</label><input type="text" class="form-control form-control-sm opp-party-rep-notes" maxlength="500"></div>' +
            '</div>';

        row.querySelector('.opp-party-remove').addEventListener('click', function () {
            var sel = row.querySelector('.opp-party-lead-select');
            if (sel && typeof global.destroyTS === 'function') global.destroyTS(sel);
            row.remove();
        });

        var leadSelect = row.querySelector('.opp-party-lead-select');
        var isLegacy = !data.opposing_lead_id && data.name;
        if (isLegacy) {
            row.classList.add('opp-party-legacy');
            var legacyName = String(data.name || '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
            var leadCol = row.querySelector('.col-md-5');
            if (leadCol) {
                leadCol.innerHTML =
                    '<label class="small mb-0 d-block">Other party <span class="text-muted">(legacy — re-link)</span></label>' +
                    '<input type="text" class="form-control form-control-sm opp-party-legacy-name" readonly value="' + legacyName + '">' +
                    '<input type="hidden" class="opp-party-lead-id" value="">' +
                    '<input type="hidden" class="opp-party-name" value="' + legacyName + '">';
            }
        } else if (opts.searchUrl && leadSelect) {
            initOtherPartyTomSelect(leadSelect, opts.searchUrl);
            if (data.opposing_lead_id) {
                var ts = leadSelect.tomselect;
                var label = data.name || ('Other party #' + data.opposing_lead_id);
                if (ts) {
                    ts.addOption({ id: data.opposing_lead_id, text: label });
                    ts.setValue(String(data.opposing_lead_id));
                }
                row.querySelector('.opp-party-lead-id').value = String(data.opposing_lead_id);
                row.querySelector('.opp-party-name').value = data.name || '';
            }
            leadSelect.addEventListener('change', function () {
                var ts2 = leadSelect.tomselect;
                var val = ts2 ? ts2.getValue() : leadSelect.value;
                row.querySelector('.opp-party-lead-id').value = val || '';
                if (ts2 && val) {
                    var opt = ts2.options[val];
                    row.querySelector('.opp-party-name').value = opt && opt.text ? opt.text.split(' (')[0] : '';
                }
            });
        }

        var roleSel = row.querySelector('.opp-party-role-select');
        if (roleSel && data.party_role) roleSel.value = data.party_role;

        if (data.rep_firm) row.querySelector('.opp-party-rep-firm').value = data.rep_firm;
        if (data.rep_name) row.querySelector('.opp-party-rep-name').value = data.rep_name;
        if (data.rep_email) row.querySelector('.opp-party-rep-email').value = data.rep_email;
        if (data.rep_phone) row.querySelector('.opp-party-rep-phone').value = data.rep_phone;
        if (data.rep_notes) row.querySelector('.opp-party-rep-notes').value = data.rep_notes;

        wrap.appendChild(row);
        return row;
    }

    function rebuildRoleSelectsInContainer(container, stream) {
        var wrap = typeof container === 'string' ? document.querySelector(container) : container;
        if (!wrap) return;
        wrap.querySelectorAll('.opp-party-role-select').forEach(function (sel) {
            var cur = sel.value;
            sel.innerHTML = partyRoleOptionsHtml(stream);
            if (cur) sel.value = cur;
        });
    }

    function collectOtherPartyRows(container) {
        var wrap = typeof container === 'string' ? document.querySelector(container) : container;
        if (!wrap) return [];
        var rows = [];
        wrap.querySelectorAll('.opp-party-row, .dyn-opp-row, .cm-opp-row').forEach(function (row) {
            var leadIdEl = row.querySelector('.opp-party-lead-id');
            var leadId = leadIdEl ? parseInt(leadIdEl.value, 10) : 0;
            if (!leadId) {
                var tsEl = row.querySelector('.opp-party-lead-select');
                if (tsEl && tsEl.tomselect) {
                    leadId = parseInt(tsEl.tomselect.getValue(), 10) || 0;
                }
            }
            var roleEl = row.querySelector('.opp-party-role-select') || row.querySelector('.dyn-opp-role') || row.querySelector('.cm-opp-role');
            var partyRole = roleEl ? String(roleEl.value || '').trim() : '';
            var nameEl = row.querySelector('.opp-party-name');
            var legacyNameEl = row.querySelector('.opp-party-legacy-name');
            var name = nameEl ? String(nameEl.value || '').trim() : (legacyNameEl ? String(legacyNameEl.value || '').trim() : '');

            if (leadId <= 0 && name !== '' && partyRole !== '') {
                rows.push({
                    opposing_lead_id: 0,
                    name: name,
                    party_role: partyRole,
                    rep_firm: (row.querySelector('.opp-party-rep-firm') || {}).value || '',
                    rep_name: (row.querySelector('.opp-party-rep-name') || {}).value || '',
                    rep_email: (row.querySelector('.opp-party-rep-email') || {}).value || '',
                    rep_phone: (row.querySelector('.opp-party-rep-phone') || {}).value || '',
                    rep_notes: (row.querySelector('.opp-party-rep-notes') || {}).value || ''
                });
                return;
            }

            if (leadId <= 0 || partyRole === '') return;

            rows.push({
                opposing_lead_id: leadId,
                name: name,
                party_role: partyRole,
                rep_firm: (row.querySelector('.opp-party-rep-firm') || {}).value || '',
                rep_name: (row.querySelector('.opp-party-rep-name') || {}).value || '',
                rep_email: (row.querySelector('.opp-party-rep-email') || {}).value || '',
                rep_phone: (row.querySelector('.opp-party-rep-phone') || {}).value || '',
                rep_notes: (row.querySelector('.opp-party-rep-notes') || {}).value || ''
            });
        });
        return rows;
    }

    function isIndianOnlyDynField(f) {
        if (!f || !f.id) return false;
        if (f.id === 'dyn_opposing_party') return true;
        var indianIds = ['dyn_fir_no', 'dyn_fir', 'dyn_police_station', 'dyn_jail', 'dyn_nclt_bench', 'dyn_drt_location'];
        if (indianIds.indexOf(f.id) >= 0) return true;
        var text = ((f.label || '') + ' ' + (f.placeholder || '')).toLowerCase();
        if (/₹|\bfir\b|\bipc\b|saket|rohini|\bdelhi\b|nclt|drt-|police station|\bjail\b|consumer forum, delhi|high court delhi|new delhi/.test(text)) {
            return true;
        }
        return false;
    }

    function filterDynFields(fields) {
        return (fields || []).filter(function (f) { return !isIndianOnlyDynField(f); });
    }

    global.OtherPartyPicker = {
        appendRow: appendOtherPartyRow,
        rebuildRoleSelects: rebuildRoleSelectsInContainer,
        collectRows: collectOtherPartyRows,
        initTomSelect: initOtherPartyTomSelect,
        partyRoleOptionsHtml: partyRoleOptionsHtml,
        filterDynFields: filterDynFields,
        isIndianOnlyDynField: isIndianOnlyDynField
    };
})(window);
