/**
 * Edit matter details modal: opposing parties, party role by stream, fetch + open.
 */
(function ($) {
    'use strict';

    function getChangeMatterStream() {
        var opt = $('#change_sel_matter_id').find('option:selected');
        var s = opt.attr('data-stream');
        return (s && String(s).trim() !== '') ? String(s).trim() : 'general';
    }

    function showChangeMatterAssigneeModal() {
        var el = document.getElementById('changeMatterAssigneeModal');
        if (!el) {
            return;
        }
        if (el.parentElement !== document.body) {
            document.body.appendChild(el);
        }
        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            bootstrap.Modal.getOrCreateInstance(el).show();
        } else {
            $(el).modal('show');
        }
    }

    window.changeMatterAppendOpposingRow = function (data) {
        if (!window.OtherPartyPicker) {
            return;
        }
        var prefill = {};
        if (typeof data === 'string') {
            prefill = { name: data, party_role: arguments[1] || '' };
        } else if (data && typeof data === 'object') {
            prefill = {
                opposing_lead_id: data.opposing_lead_id || null,
                name: data.name || '',
                party_role: data.party_role || '',
                rep_firm: data.rep_firm || '',
                rep_name: data.rep_name || '',
                rep_email: data.rep_email || '',
                rep_phone: data.rep_phone || '',
                rep_notes: data.rep_notes || ''
            };
        }
        window.OtherPartyPicker.appendRow('#change_matter_opposing_parties_container', {
            rowClass: 'cm-opp-row opp-party-row',
            searchUrl: window.OTHER_PARTY_SEARCH_URL,
            solicitorSearchUrl: window.CONTACT_PERSON_SEARCH_URL,
            excludeId: window.currentClientId || null,
            stream: getChangeMatterStream(),
            data: prefill
        });
    };

    window.changeMatterRebuildPartyRoleSelect = function () {
        var stream = getChangeMatterStream();
        var map = window.MATTER_PARTY_ROLES_BY_STREAM || {};
        var roles = map[stream] || map.general || {};
        var $pr = $('#change_matter_our_party_role');
        if (!$pr.length) { return; }
        var cur = $pr.val();
        $pr.empty().append($('<option/>').val('').text('— Select role —'));
        Object.keys(roles).forEach(function (k) {
            $pr.append($('<option/>').val(k).text(roles[k]));
        });
        if (cur) {
            $pr.val(cur);
        }
        if (window.OtherPartyPicker) {
            window.OtherPartyPicker.rebuildRoleSelects('#change_matter_opposing_parties_container', stream);
        }
    };

    window.prepareChangeMatterAssigneeSubmit = function () {
        var ourRole = $.trim($('#change_matter_our_party_role').val() || '');
        if ($('#change_matter_our_party_role').length && ourRole === '') {
            if (typeof iziToast !== 'undefined' && iziToast.warning) {
                iziToast.warning({ title: 'Required', message: 'Our client\'s role is required.', position: 'topRight' });
            } else {
                alert('Our client\'s role is required.');
            }
            return;
        }

        var rows = window.OtherPartyPicker
            ? window.OtherPartyPicker.collectRows('#change_matter_opposing_parties_container')
            : [];
        $('#change_matter_opposing_parties_json').val(JSON.stringify(rows));

        var init = $('#change_matter_initial_sel_matter_id').val();
        var now = $('#change_sel_matter_id').val();
        if (init && now && String(init) !== String(now)) {
            if (!window.confirm('You are changing the law matter type. The existing matter reference will not change automatically. Continue?')) {
                return;
            }
        }
        customValidate('change_matter_assignee');
    };

    $(document).on('change', '#change_sel_matter_id', function () {
        if (typeof window.changeMatterRebuildPartyRoleSelect === 'function') {
            window.changeMatterRebuildPartyRoleSelect();
        }
    });

    $(document).on('click', '#change_matter_add_opposing_btn', function (e) {
        e.preventDefault();
        if (typeof window.changeMatterAppendOpposingRow === 'function') {
            window.changeMatterAppendOpposingRow({});
        }
    });

    function ensureSelectOptionForValue($sel, rawVal, labelText) {
        if (!$sel.length) {
            return;
        }
        var v = rawVal != null && rawVal !== '' ? String(rawVal) : '';
        if (v === '' || v === '0') {
            return;
        }
        var exists = false;
        $sel.find('option').each(function () {
            if (String($(this).val()) === v) {
                exists = true;
                return false;
            }
        });
        if (!exists && labelText) {
            $sel.append($('<option/>').val(v).text(labelText));
        }
    }

    function labelFromAssigneeStaffMap(staffMap, rawId) {
        if (rawId == null || rawId === '' || rawId === 0 || rawId === '0') {
            return '';
        }
        var key = String(rawId);
        var s = staffMap[key] || staffMap[rawId];
        if (!s) {
            return 'Staff #' + key;
        }
        var parts = [];
        if (s.first_name) { parts.push(String(s.first_name)); }
        if (s.last_name) { parts.push(String(s.last_name)); }
        var t = parts.join(' ').trim();
        if (s.email) {
            t = t ? t + ' \u2014 ' + String(s.email) : String(s.email);
        }
        return t || ('Staff #' + key);
    }

    $(document).delegate('.changeMatterAssignee', 'click', function (e) {
        e.preventDefault();

        var matterId = $(this).attr('data-client-matter-id');
        if (!matterId) {
            matterId = $('.general_matter_checkbox_client_detail').is(':checked')
                ? $('.general_matter_checkbox_client_detail').val()
                : $('#sel_matter_id_client_detail').val();
        }

        if (!matterId && window.ClientDetailConfig && window.ClientDetailConfig.clientMatterId) {
            matterId = String(window.ClientDetailConfig.clientMatterId);
        }

        if (!matterId) {
            if (typeof iziToast !== 'undefined' && iziToast.warning) {
                iziToast.warning({ title: 'Select Matter', message: 'Please select a matter first.', position: 'topRight' });
            } else { alert('Please select a matter first.'); }
            return;
        }

        $('#selectedMatterLM').val(matterId);

        var fetchUrl = (window.ClientDetailConfig && window.ClientDetailConfig.urls && window.ClientDetailConfig.urls.fetchClientMatterAssignee) || '/clients/fetchClientMatterAssignee';

        $.ajax({
            type: 'post',
            url: fetchUrl,
            data: { _token: $('meta[name="csrf-token"]').attr('content'), client_matter_id: matterId },
            success: function (res) {
                var info = (typeof res === 'string' ? (function () { try { return JSON.parse(res); } catch (e) { return {}; } })() : res) || {};
                var m = info.matter_info || {};
                var staffMap = info.assignee_staff_for_modal || {};

                var $lp = $('#change_sel_legal_practitioner_id');
                var $pr = $('#change_sel_person_responsible_id');
                var $pa = $('#change_sel_person_assisting_id');

                ensureSelectOptionForValue($lp, m.sel_legal_practitioner, labelFromAssigneeStaffMap(staffMap, m.sel_legal_practitioner));
                ensureSelectOptionForValue($pr, m.sel_person_responsible, labelFromAssigneeStaffMap(staffMap, m.sel_person_responsible));
                ensureSelectOptionForValue($pa, m.sel_person_assisting, labelFromAssigneeStaffMap(staffMap, m.sel_person_assisting));

                if (m.sel_legal_practitioner) { $lp.val(String(m.sel_legal_practitioner)).trigger('change'); }
                else { $lp.val('').trigger('change'); }

                if (m.sel_person_responsible) { $pr.val(String(m.sel_person_responsible)).trigger('change'); }
                else { $pr.val('').trigger('change'); }

                if (m.sel_person_assisting) { $pa.val(String(m.sel_person_assisting)).trigger('change'); }
                else { $pa.val('').trigger('change'); }

                if (m.office_id) { $('#change_office_id').val(String(m.office_id)).trigger('change'); }
                else { $('#change_office_id').val('').trigger('change'); }

                if ($('#change_matter_incidence_type').length) {
                    $('#change_matter_incidence_type').val(m.incidence_type ? String(m.incidence_type) : '');
                }

                if ($('#change_matter_date_of_incidence').length) {
                    var doi = m.date_of_incidence;
                    if (doi) {
                        doi = String(doi);
                        if (doi.indexOf('T') > 0) { doi = doi.split('T')[0]; }
                        else if (doi.indexOf(' ') > 0) { doi = doi.split(' ')[0]; }
                        $('#change_matter_date_of_incidence').val(doi);
                    } else {
                        $('#change_matter_date_of_incidence').val('');
                    }
                }

                if ($('#change_matter_case_detail').length) {
                    $('#change_matter_case_detail').val(m.case_detail ? String(m.case_detail) : '');
                }

                var matterOpts = info.matter_options || [];
                var $mtSel = $('#change_sel_matter_id');
                if ($mtSel.length) {
                    $mtSel.empty();
                    $mtSel.append($('<option/>').val('').text('\u2014 Select law matter type \u2014'));
                    matterOpts.forEach(function (o) {
                        var opt = $('<option/>').val(String(o.id)).text(o.title || '');
                        if (o.stream) { opt.attr('data-stream', o.stream); }
                        $mtSel.append(opt);
                    });
                    if (m.sel_matter_id) {
                        $mtSel.val(String(m.sel_matter_id));
                    }
                    $('#change_matter_initial_sel_matter_id').val(m.sel_matter_id ? String(m.sel_matter_id) : '');
                }

                if (typeof window.changeMatterRebuildPartyRoleSelect === 'function') {
                    window.changeMatterRebuildPartyRoleSelect();
                }

                if ($('#change_matter_our_party_role').length) {
                    $('#change_matter_our_party_role').val(m.our_party_role ? String(m.our_party_role) : '');
                }

                var opp = info.opposing_parties || [];
                var $oppC = $('#change_matter_opposing_parties_container');
                if ($oppC.length) {
                    $oppC.empty();
                    if (opp.length === 0) {
                        // no default row
                    } else {
                        opp.forEach(function (p) {
                            if (typeof window.changeMatterAppendOpposingRow === 'function') {
                                window.changeMatterAppendOpposingRow(p);
                            }
                        });
                    }
                }

                showChangeMatterAssigneeModal();
            },
            error: function () {
                if (typeof iziToast !== 'undefined' && iziToast.error) {
                    iziToast.error({ title: 'Error', message: 'Could not load matter details. Try again.', position: 'topRight' });
                } else {
                    alert('Could not load matter details. Try again.');
                }
            }
        });
    });

    $(document).on('shown.bs.modal', '#changeMatterAssigneeModal', function () {
        var modalEl = this;
        $('#change_sel_legal_practitioner_id, #change_sel_person_responsible_id, #change_sel_person_assisting_id, #change_office_id').each(function () {
            var el = this;
            if (typeof destroyTS === 'function') destroyTS(el);
            if (typeof initTS === 'function') {
                initTS(el, { create: false, allowEmptyOption: true, dropdownParent: modalEl });
                var ts = el.tomselect;
                if (ts && ts.wrapper) {
                    ts.wrapper.style.width = '100%';
                }
            }
        });
    });

    $(document).on('hidden.bs.modal', '#changeMatterAssigneeModal', function () {
        $('#change_sel_legal_practitioner_id, #change_sel_person_responsible_id, #change_sel_person_assisting_id, #change_office_id').each(function () {
            if (typeof destroyTS === 'function') destroyTS(this);
        });
        $('#change_matter_opposing_parties_container .opp-party-lead-select').each(function () {
            if (typeof destroyTS === 'function') destroyTS(this);
        });
    });
})(jQuery);
