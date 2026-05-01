/**
 * Edit matter details modal: opposing parties, party role by stream, fetch + open.
 * Loaded before detail-main.js on client/company detail; also on clients/edit Matter tab.
 */
(function ($) {
    'use strict';

    /** Open Bootstrap 5 matter modal; move node to body so no ancestor transform/overflow hides it. */
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

    window.changeMatterAppendOpposingRow = function (name, role) {
        var $c = $('#change_matter_opposing_parties_container');
        if (!$c.length) { return; }
        var $row = $('<div class="row mb-2 cm-opp-row align-items-end"></div>');
        var $n = $('<input type="text" class="form-control cm-opp-name" maxlength="500">').val(name || '');
        var $r = $('<input type="text" class="form-control cm-opp-role" maxlength="255" placeholder="Role (e.g. co-defendant)">').val(role || '');
        $row.append($('<div class="col-md-5"/>').append('<label class="small mb-0 d-block">Name</label>').append($n));
        $row.append($('<div class="col-md-5"/>').append('<label class="small mb-0 d-block">Their role</label>').append($r));
        var $rm = $('<button type="button" class="btn btn-sm btn-outline-danger w-100">Remove</button>');
        $rm.on('click', function () { $row.remove(); });
        $row.append($('<div class="col-md-2"/>').append('<label class="small mb-0 d-block">&nbsp;</label>').append($rm));
        $c.append($row);
    };

    window.changeMatterRebuildPartyRoleSelect = function () {
        var $mt = $('#change_sel_matter_id');
        var opt = $mt.find('option:selected');
        var stream = (opt.attr('data-stream') || 'general').toString();
        var map = window.MATTER_PARTY_ROLES_BY_STREAM || {};
        var roles = map[stream] || map.general || {};
        var $pr = $('#change_matter_our_party_role');
        if (!$pr.length) { return; }
        var cur = $pr.val();
        $pr.empty().append($('<option/>').val('').text('\u2014'));
        Object.keys(roles).forEach(function (k) {
            $pr.append($('<option/>').val(k).text(roles[k]));
        });
        if (cur) {
            $pr.val(cur);
        }
    };

    window.prepareChangeMatterAssigneeSubmit = function () {
        var rows = [];
        $('#change_matter_opposing_parties_container .cm-opp-row').each(function () {
            var name = $.trim($(this).find('.cm-opp-name').val() || '');
            var party_role = $.trim($(this).find('.cm-opp-role').val() || '');
            if (name !== '') { rows.push({ name: name, party_role: party_role }); }
        });
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
            window.changeMatterAppendOpposingRow('', '');
        }
    });

    $(document).delegate('.changeMatterAssignee', 'click', function (e) {
        e.preventDefault();

        var matterId = $(this).attr('data-client-matter-id');
        if (!matterId) {
            matterId = $('.general_matter_checkbox_client_detail').is(':checked')
                ? $('.general_matter_checkbox_client_detail').val()
                : $('#sel_matter_id_client_detail').val();
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

                if (m.sel_legal_practitioner) $('#change_sel_legal_practitioner_id').val(m.sel_legal_practitioner).trigger('change');
                else $('#change_sel_legal_practitioner_id').val('').trigger('change');

                if (m.sel_person_responsible) $('#change_sel_person_responsible_id').val(m.sel_person_responsible).trigger('change');
                else $('#change_sel_person_responsible_id').val('').trigger('change');

                if (m.sel_person_assisting) $('#change_sel_person_assisting_id').val(m.sel_person_assisting).trigger('change');
                else $('#change_sel_person_assisting_id').val('').trigger('change');

                if (m.office_id) $('#change_office_id').val(m.office_id).trigger('change');
                else $('#change_office_id').val('').trigger('change');

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
                        if (typeof window.changeMatterAppendOpposingRow === 'function') {
                            window.changeMatterAppendOpposingRow('', '');
                        }
                    } else {
                        opp.forEach(function (p) {
                            if (typeof window.changeMatterAppendOpposingRow === 'function') {
                                window.changeMatterAppendOpposingRow(p.name || '', p.party_role || '');
                            }
                        });
                    }
                }
            }
        });

        showChangeMatterAssigneeModal();
    });

    $(document).on('shown.bs.modal', '#changeMatterAssigneeModal', function () {
        var $modal = $(this);
        $('#change_sel_legal_practitioner_id, #change_sel_person_responsible_id, #change_sel_person_assisting_id, #change_office_id').each(function () {
            var $el = $(this);
            if ($el.data('select2')) $el.select2('destroy');
            $el.select2({ dropdownParent: $modal, minimumResultsForSearch: 0, width: '100%' });
        });
    });

    $(document).on('hidden.bs.modal', '#changeMatterAssigneeModal', function () {
        $('#change_sel_legal_practitioner_id, #change_sel_person_responsible_id, #change_sel_person_assisting_id, #change_office_id').each(function () {
            var $el = $(this);
            if ($el.data('select2')) {
                try { $el.select2('close'); } catch (e) { /* no-op */ }
            }
        });
    });
})(jQuery);
