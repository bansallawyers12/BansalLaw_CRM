/**
 * Shared other-party row UI for matter forms (create, edit modal, quick add).
 */
(function (global) {
    'use strict';

    function partyRoleOptionsHtml(stream, customRoles) {
        if (customRoles && typeof customRoles === 'object' && Object.keys(customRoles).length) {
            var html = '<option value="">— Select role —</option>';
            Object.keys(customRoles).forEach(function (k) {
                html += '<option value="' + k + '">' + customRoles[k] + '</option>';
            });
            return html;
        }
        var map = global.MATTER_PARTY_ROLES_BY_STREAM || {};
        var roles = map[stream] || map.general || {};
        var html = '<option value="">— Select role —</option>';
        Object.keys(roles).forEach(function (k) {
            html += '<option value="' + k + '">' + roles[k] + '</option>';
        });
        return html;
    }

    function initSearchTomSelect(selectEl, searchUrl, excludeId, placeholder) {
        if (selectEl && typeof global.destroyTS === 'function') {
            global.destroyTS(selectEl);
        }
        if (!selectEl || typeof global.initTS !== 'function' || typeof global.buildContactPersonSearchTomSelectConfig !== 'function') {
            console.warn('Party search: Tom Select helpers not loaded.');
            return false;
        }
        var cfg = global.buildContactPersonSearchTomSelectConfig({
            url: searchUrl,
            placeholder: placeholder || 'Type name, phone, email, or ID…',
            dropdownParent: 'body',
            excludeId: excludeId
        });
        global.initTS(selectEl, cfg);
        return true;
    }

    function fillRepFieldsFromPerson(row, person) {
        if (!row || !person) return;
        var nameEl = row.querySelector('.opp-party-rep-name');
        var emailEl = row.querySelector('.opp-party-rep-email');
        var phoneEl = row.querySelector('.opp-party-rep-phone');
        var fullName = ((person.first_name || '') + ' ' + (person.last_name || '')).trim();
        if (!fullName && person.text) {
            fullName = String(person.text).split(' (')[0].trim();
        }
        if (nameEl) nameEl.value = fullName;
        if (emailEl) emailEl.value = person.email || '';
        if (phoneEl) phoneEl.value = person.phone || '';
    }

    function setRepFieldsReadonly(row, readonly) {
        if (!row) return;
        ['opp-party-rep-name', 'opp-party-rep-email', 'opp-party-rep-phone'].forEach(function (cls) {
            var el = row.querySelector('.' + cls);
            if (!el) return;
            if (readonly) {
                el.setAttribute('readonly', 'readonly');
            } else {
                el.removeAttribute('readonly');
            }
        });
    }

    function toggleSolicitorMode(row, isManual) {
        if (!row) return;
        var searchWrap = row.querySelector('.opp-party-solicitor-search-wrap');
        var solSelect = row.querySelector('.opp-party-solicitor-select');
        if (searchWrap) {
            searchWrap.style.display = isManual ? 'none' : 'block';
        }
        if (solSelect && solSelect.tomselect) {
            if (isManual) {
                solSelect.tomselect.disable();
            } else {
                solSelect.tomselect.enable();
            }
        }
        if (isManual) {
            setRepFieldsReadonly(row, false);
        } else {
            var hasSelection = solSelect && solSelect.tomselect && solSelect.tomselect.getValue();
            setRepFieldsReadonly(row, !!hasSelection);
        }
    }

    function wireSolicitorMode(row, solicitorSearchUrl, excludeId) {
        var uid = 'sm' + Date.now() + Math.floor(Math.random() * 10000);
        var radios = row.querySelectorAll('.opp-party-solicitor-mode input[type="radio"]');
        radios.forEach(function (radio) {
            radio.name = 'solicitor_mode_' + uid;
            radio.addEventListener('change', function () {
                toggleSolicitorMode(row, this.value === 'manual');
            });
        });

        var solSelect = row.querySelector('.opp-party-solicitor-select');
        if (solSelect && solicitorSearchUrl) {
            initSearchTomSelect(solSelect, solicitorSearchUrl, excludeId, 'Search solicitor (name, phone, email)…');
            solSelect.addEventListener('change', function () {
                var ts = solSelect.tomselect;
                if (!ts) return;
                var val = ts.getValue();
                if (!val) {
                    setRepFieldsReadonly(row, false);
                    return;
                }
                var opt = ts.options[val] || ts.options[String(val)];
                fillRepFieldsFromPerson(row, opt || {});
                setRepFieldsReadonly(row, true);
            });
        }
    }

    function selectPartyOnRow(row, person) {
        if (!row || !person) return;
        var leadSelect = row.querySelector('.opp-party-lead-select');
        if (!leadSelect) return;
        if (!leadSelect.tomselect && person.id) {
            initSearchTomSelect(leadSelect, global.OTHER_PARTY_SEARCH_URL || '', null, 'Search other party…');
        }
        var ts = leadSelect.tomselect;
        if (ts && person.id) {
            ts.enable();
            ts.addOption({
                id: person.id,
                text: person.text || ((person.first_name || '') + ' ' + (person.last_name || '')).trim(),
                first_name: person.first_name || '',
                last_name: person.last_name || '',
                email: person.email || '',
                phone: person.phone || '',
                client_id: person.client_id != null && person.client_id !== '' ? person.client_id : ''
            });
            ts.setValue(String(person.id), true);
        }
        var leadIdEl = row.querySelector('.opp-party-lead-id');
        if (leadIdEl) leadIdEl.value = person.id ? String(person.id) : '';
        var nameEl = row.querySelector('.opp-party-name');
        if (nameEl) {
            nameEl.value = ((person.first_name || '') + ' ' + (person.last_name || '')).trim() || (person.text || '').split(' (')[0];
        }
    }

    /**
     * Append an other-party row to a container.
     */
    function appendOtherPartyRow(container, opts) {
        opts = opts || {};
        var wrap = typeof container === 'string' ? document.querySelector(container) : container;
        if (!wrap) return null;

        var rowClass = opts.rowClass || 'opp-party-row';
        var data = opts.data || {};
        var stream = opts.stream || 'general';
        var customRoles = opts.roles || null;
        var solicitorSearchUrl = opts.solicitorSearchUrl || global.CONTACT_PERSON_SEARCH_URL || '';

        var row = document.createElement('div');
        row.className = rowClass + ' opp-party-row';

        row.innerHTML =
            '<div class="opp-party-row__top">' +
                '<div class="opp-party-field opp-party-field--party">' +
                    '<label>Other party <span class="text-danger">*</span></label>' +
                    '<select class="form-control opp-party-lead-select" data-placeholder="Search other party…"></select>' +
                    '<input type="hidden" class="opp-party-lead-id" value="">' +
                    '<input type="hidden" class="opp-party-name" value="">' +
                '</div>' +
                '<div class="opp-party-field opp-party-field--role">' +
                    '<label>Their role <span class="text-danger">*</span></label>' +
                    '<select class="form-control opp-party-role-select">' + partyRoleOptionsHtml(stream, customRoles) + '</select>' +
                '</div>' +
                '<div class="opp-party-field opp-party-field--remove">' +
                    '<button type="button" class="btn btn-sm btn-outline-danger opp-party-remove">Remove</button>' +
                '</div>' +
            '</div>' +
            '<div class="opp-party-solicitor-block">' +
                '<div class="opp-party-solicitor-block__head">Opposing solicitor <span class="text-muted fw-normal">(optional)</span></div>' +
                '<div class="opp-party-solicitor-mode">' +
                    '<label><input type="radio" value="search" checked> Search existing</label>' +
                    '<label><input type="radio" value="manual"> Enter manually</label>' +
                '</div>' +
                '<div class="opp-party-solicitor-search-wrap">' +
                    '<select class="form-control opp-party-solicitor-select" data-placeholder="Search solicitor…"></select>' +
                '</div>' +
                '<div class="opp-party-rep-fields">' +
                    '<div class="opp-party-field"><label class="small mb-1">Solicitor firm</label>' +
                        '<input type="text" class="form-control form-control-sm opp-party-rep-firm" maxlength="255" placeholder="Firm name"></div>' +
                    '<div class="opp-party-field"><label class="small mb-1">Solicitor name</label>' +
                        '<input type="text" class="form-control form-control-sm opp-party-rep-name" maxlength="255"></div>' +
                    '<div class="opp-party-field"><label class="small mb-1">Email</label>' +
                        '<input type="email" class="form-control form-control-sm opp-party-rep-email" maxlength="255"></div>' +
                    '<div class="opp-party-field"><label class="small mb-1">Phone</label>' +
                        '<input type="text" class="form-control form-control-sm opp-party-rep-phone" maxlength="64"></div>' +
                    '<div class="opp-party-field opp-party-field--full"><label class="small mb-1">Notes</label>' +
                        '<input type="text" class="form-control form-control-sm opp-party-rep-notes" maxlength="500" placeholder="Optional notes"></div>' +
                '</div>' +
            '</div>';

        row.querySelector('.opp-party-remove').addEventListener('click', function () {
            row.querySelectorAll('.opp-party-lead-select, .opp-party-solicitor-select').forEach(function (sel) {
                if (typeof global.destroyTS === 'function') global.destroyTS(sel);
            });
            row.remove();
        });

        var leadSelect = row.querySelector('.opp-party-lead-select');
        var isLegacy = !data.opposing_lead_id && data.name;

        if (isLegacy) {
            row.classList.add('opp-party-legacy');
            var partyField = row.querySelector('.opp-party-field--party');
            if (partyField) {
                var legacyName = String(data.name || '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
                partyField.innerHTML =
                    '<label>Other party <span class="text-muted">(legacy — re-link)</span></label>' +
                    '<input type="text" class="form-control form-control-sm opp-party-legacy-name" readonly value="' + legacyName + '">' +
                    '<input type="hidden" class="opp-party-lead-id" value="">' +
                    '<input type="hidden" class="opp-party-name" value="' + legacyName + '">';
            }
        } else if (opts.searchUrl && leadSelect) {
            initSearchTomSelect(leadSelect, opts.searchUrl, opts.excludeId, 'Search other party…');
            if (data.opposing_lead_id) {
                var ts = leadSelect.tomselect;
                var label = data.name || ('Other party #' + data.opposing_lead_id);
                if (ts) {
                    ts.addOption({ id: data.opposing_lead_id, text: label });
                    ts.setValue(String(data.opposing_lead_id), true);
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

        wireSolicitorMode(row, solicitorSearchUrl, opts.excludeId);

        var roleSel = row.querySelector('.opp-party-role-select');
        if (roleSel && data.party_role) roleSel.value = data.party_role;

        if (data.rep_firm) row.querySelector('.opp-party-rep-firm').value = data.rep_firm;
        if (data.rep_name) row.querySelector('.opp-party-rep-name').value = data.rep_name;
        if (data.rep_email) row.querySelector('.opp-party-rep-email').value = data.rep_email;
        if (data.rep_phone) row.querySelector('.opp-party-rep-phone').value = data.rep_phone;
        if (data.rep_notes) row.querySelector('.opp-party-rep-notes').value = data.rep_notes;

        if (data.rep_name && !data.rep_from_search) {
            var manualRadio = row.querySelector('.opp-party-solicitor-mode input[value="manual"]');
            if (manualRadio) {
                manualRadio.checked = true;
                toggleSolicitorMode(row, true);
            }
        }

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
        initTomSelect: function (el, url, excludeId) {
            return initSearchTomSelect(el, url, excludeId);
        },
        selectParty: selectPartyOnRow,
        partyRoleOptionsHtml: partyRoleOptionsHtml,
        filterDynFields: filterDynFields,
        isIndianOnlyDynField: isIndianOnlyDynField
    };
})(window);
