{{-- Shared Quick Add Matter modal JS (detail + edit pages) --}}
<script>
(function () {
    if (window.__quickAddMatterScriptsInit) return;
    window.__quickAddMatterScriptsInit = true;

    window.MATTER_PARTY_ROLES_BY_STREAM = window.MATTER_PARTY_ROLES_BY_STREAM || @json(config('matter_streams.party_roles_by_stream', []));
    window.OTHER_PARTY_SEARCH_URL = window.OTHER_PARTY_SEARCH_URL || @json(route('api.search.other.party'));
    window.CONTACT_PERSON_SEARCH_URL = window.CONTACT_PERSON_SEARCH_URL || @json(route('api.search.contact.person'));

    function getAddMatterModalEl() {
        var shown = document.querySelector('#addMatterModal.show');
        if (shown) return shown;
        return document.getElementById('addMatterModal');
    }

    function prepareAddMatterModalShell() {
        var el = getAddMatterModalEl();
        if (!el) return null;
        document.querySelectorAll('#addMatterModal').forEach(function (node) {
            if (node !== el) node.remove();
        });
        if (el.parentElement !== document.body) {
            document.body.appendChild(el);
        }
        return el;
    }

    function clearQuickAddOpposingParties() {
        var oppWrap = document.getElementById('quick_add_opposing_parties_wrap');
        if (!oppWrap) return;
        oppWrap.querySelectorAll('.opp-party-lead-select, .opp-party-solicitor-select').forEach(function (sel) {
            if (typeof window.destroyTS === 'function') window.destroyTS(sel);
        });
        oppWrap.innerHTML = '';
    }

    function resetAddMatterFormFields() {
        var msg = document.getElementById('editAddMatterMsg');
        if (msg) msg.innerHTML = '';
        var caseDetail = document.getElementById('edit_add_matter_case_detail');
        if (caseDetail) caseDetail.value = '';
        var doi = document.getElementById('edit_add_matter_date_of_incidence');
        if (doi) doi.value = '';
        var it = document.getElementById('edit_add_matter_incidence_type');
        if (it) it.value = '';

        [
            'edit_add_matter_our_party_role',
            'edit_add_matter_matter_id',
            'edit_add_matter_legal_practitioner',
            'edit_add_matter_person_responsible',
            'edit_add_matter_person_assisting'
        ].forEach(function (id) {
            var el = document.getElementById(id);
            if (!el) return;
            if (el.tomselect) {
                el.tomselect.clear(true);
            } else {
                el.value = '';
            }
        });
        clearQuickAddOpposingParties();
    }

    function closeAddMatterModal() {
        var el = getAddMatterModalEl();
        if (!el) return;
        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            var inst = bootstrap.Modal.getInstance(el);
            if (inst) {
                inst.hide();
            } else {
                el.classList.remove('show');
                el.style.display = 'none';
                el.setAttribute('aria-hidden', 'true');
                document.body.classList.remove('modal-open');
                document.querySelectorAll('.modal-backdrop').forEach(function (b) { b.remove(); });
            }
        } else if (window.jQuery) {
            window.jQuery(el).modal('hide');
        } else {
            el.style.display = 'none';
            document.body.style.overflow = '';
        }
        resetAddMatterFormFields();
    }

    function getQuickAddMatterStream() {
        var sel = document.getElementById('edit_add_matter_matter_id');
        if (!sel) return 'general';
        var val = sel.tomselect ? String(sel.tomselect.getValue() || '') : String(sel.value || '');
        var opt = null;
        if (val) {
            for (var i = 0; i < sel.options.length; i++) {
                if (String(sel.options[i].value) === val) {
                    opt = sel.options[i];
                    break;
                }
            }
        } else {
            opt = sel.options[sel.selectedIndex];
        }
        return opt && opt.getAttribute('data-stream') ? opt.getAttribute('data-stream') : 'general';
    }

    window.rebuildQuickAddPartyRole = function () {
        var map = window.MATTER_PARTY_ROLES_BY_STREAM || {};
        var stream = getQuickAddMatterStream();
        var roles = map[stream] || map.general || {};
        var pr = document.getElementById('edit_add_matter_our_party_role');
        if (!pr) return;
        var cur = pr.tomselect ? String(pr.tomselect.getValue() || '') : pr.value;
        var html = '<option value="">— Select role —</option>';
        Object.keys(roles).forEach(function (k) {
            html += '<option value="' + k + '">' + roles[k] + '</option>';
        });
        if (pr.tomselect && typeof window.destroyTS === 'function') {
            window.destroyTS(pr);
        }
        pr.innerHTML = html;
        if (cur && roles[cur]) {
            pr.value = cur;
        }
        ensureSearchableModalSelect(pr, 'Search role…');
        if (cur && roles[cur] && pr.tomselect) {
            pr.tomselect.setValue(cur, true);
        }
        if (window.OtherPartyPicker) {
            window.OtherPartyPicker.rebuildRoleSelects('#quick_add_opposing_parties_wrap', stream);
        }
    };

    function quickAddOpposingPartyRow(e) {
        if (e && e.preventDefault) e.preventDefault();
        if (!window.OtherPartyPicker) {
            if (typeof iziToast !== 'undefined' && iziToast.show) {
                iziToast.show({ message: 'Other party picker is not loaded. Refresh the page and try again.', color: 'red', position: 'topRight', timeout: 5000 });
            } else {
                crmAlert('Other party picker is not loaded. Refresh the page and try again.');
            }
            return;
        }
        window.OtherPartyPicker.appendRow('#quick_add_opposing_parties_wrap', {
            rowClass: 'quick-opp-row opp-party-row',
            searchUrl: window.OTHER_PARTY_SEARCH_URL,
            solicitorSearchUrl: window.CONTACT_PERSON_SEARCH_URL,
            excludeId: window.currentClientId || null,
            stream: getQuickAddMatterStream(),
            data: {}
        });
    }

    function bindQuickAddOpposingPartyButton() {
        var addBtn = document.getElementById('quick_add_opposing_party_btn');
        if (!addBtn || addBtn.dataset.oppPartyBound === '1') return;
        addBtn.dataset.oppPartyBound = '1';
        addBtn.addEventListener('click', quickAddOpposingPartyRow);
    }

    bindQuickAddOpposingPartyButton();

    function ensureSearchableModalSelect(selectEl, placeholder) {
        if (!selectEl || typeof window.initTS !== 'function') {
            return null;
        }
        if (selectEl.tomselect) {
            return selectEl.tomselect;
        }
        selectEl.removeAttribute('placeholder');
        var ph = placeholder || selectEl.getAttribute('data-placeholder') || 'Type to search…';
        selectEl.removeAttribute('data-placeholder');
        var ts = window.initTS(selectEl, {
            plugins: ['clear_button'],
            create: false,
            allowEmptyOption: true,
            maxItems: 1,
            placeholder: ph,
            dropdownParent: 'body',
            sortField: { field: 'text', direction: 'asc' }
        });
        if (ts && window.OtherPartyPicker && typeof window.OtherPartyPicker.bindModalScrollDropdown === 'function') {
            window.OtherPartyPicker.bindModalScrollDropdown(ts, selectEl);
        }
        return ts;
    }

    function ensureAddMatterSearchableSelects() {
        var matterSel = document.getElementById('edit_add_matter_matter_id');
        var firstInit = matterSel && !matterSel.tomselect;
        ensureSearchableModalSelect(matterSel, 'Search matter type…');
        if (firstInit && matterSel) {
            matterSel.addEventListener('change', function () {
                if (typeof window.rebuildQuickAddPartyRole === 'function') {
                    window.rebuildQuickAddPartyRole();
                }
            });
        }
        ensureSearchableModalSelect(
            document.getElementById('edit_add_matter_legal_practitioner'),
            'Search legal practitioner…'
        );
        ensureSearchableModalSelect(
            document.getElementById('edit_add_matter_person_responsible'),
            'Search person responsible…'
        );
        ensureSearchableModalSelect(
            document.getElementById('edit_add_matter_person_assisting'),
            'Search person assisting…'
        );
        ensureSearchableModalSelect(
            document.getElementById('edit_add_matter_office_id'),
            'Search office…'
        );
        ensureSearchableModalSelect(
            document.getElementById('edit_add_matter_our_party_role'),
            'Search role…'
        );
    }

    function openAddMatterModal() {
        var el = prepareAddMatterModalShell();
        if (!el) return;
        bindQuickAddOpposingPartyButton();
        if (typeof window.rebuildQuickAddPartyRole === 'function') window.rebuildQuickAddPartyRole();

        function afterShown() {
            ensureAddMatterSearchableSelects();
            if (typeof window.rebuildQuickAddPartyRole === 'function') {
                window.rebuildQuickAddPartyRole();
            }
        }

        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            // focus:false so Tom Select dropdowns on <body> remain clickable
            var inst = bootstrap.Modal.getOrCreateInstance(el, { focus: false });
            el.addEventListener('shown.bs.modal', afterShown, { once: true });
            inst.show();
        } else if (window.jQuery) {
            window.jQuery(el).one('shown.bs.modal', afterShown).modal('show');
        } else {
            el.style.display = 'block';
            el.classList.add('show');
            document.body.classList.add('modal-open');
            afterShown();
        }
    }

    // Reset form when Bootstrap finishes hiding (Cancel / X / backdrop).
    document.addEventListener('hidden.bs.modal', function (e) {
        if (e.target && e.target.id === 'addMatterModal') {
            resetAddMatterFormFields();
        }
    });

    async function submitLeadMatterFromEdit() {
        var msgEl = document.getElementById('editAddMatterMsg');
        var btn = document.getElementById('editAddMatterSubmitBtn');
        if (!msgEl || !window.storeLeadMatterFromEditUrl || !window.editClientConfig) return;
        msgEl.innerHTML = '';
        var matterId = document.getElementById('edit_add_matter_matter_id');
        var agentId = document.getElementById('edit_add_matter_legal_practitioner');
        if (!matterId || !agentId || !matterId.value || !agentId.value) {
            msgEl.innerHTML = '<div class="alert alert-warning">Select a matter type and legal practitioner.</div>';
            return;
        }
        var ourRoleEl = document.getElementById('edit_add_matter_our_party_role');
        if (!ourRoleEl || !ourRoleEl.value) {
            msgEl.innerHTML = '<div class="alert alert-warning">Our client&rsquo;s role is required.</div>';
            return;
        }
        var fd = new FormData();
        fd.append('_token', window.editClientConfig.csrfToken);
        var matterClientPk = (window.currentClientId != null && String(window.currentClientId).trim() !== '')
            ? String(window.currentClientId).trim()
            : String(@json((int) ($fetchedData->id ?? 0)));
        fd.append('client_id', matterClientPk);
        fd.append('matter_id', matterId.value);
        fd.append('legal_practitioner', agentId.value);
        fd.append('our_party_role', ourRoleEl.value);
        var oppRows = window.OtherPartyPicker
            ? window.OtherPartyPicker.collectRows('#quick_add_opposing_parties_wrap')
            : [];
        fd.append('opposing_parties_json', JSON.stringify(oppRows));
        var office = document.getElementById('edit_add_matter_office_id');
        if (office && office.value) fd.append('office_id', office.value);
        var pr = document.getElementById('edit_add_matter_person_responsible');
        if (pr && pr.value) fd.append('person_responsible', pr.value);
        var pa = document.getElementById('edit_add_matter_person_assisting');
        if (pa && pa.value) fd.append('person_assisting', pa.value);
        var caseDetailEl = document.getElementById('edit_add_matter_case_detail');
        if (caseDetailEl && caseDetailEl.value.trim() !== '') {
            fd.append('case_detail', caseDetailEl.value.trim());
        }
        var doiEl = document.getElementById('edit_add_matter_date_of_incidence');
        if (doiEl && doiEl.value) fd.append('date_of_incidence', doiEl.value);
        var itEl = document.getElementById('edit_add_matter_incidence_type');
        if (itEl && itEl.value.trim() !== '') fd.append('incidence_type', itEl.value.trim());
        btn.disabled = true;
        try {
            var res = await fetch(window.storeLeadMatterFromEditUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': window.editClientConfig.csrfToken
                },
                body: fd
            });
            var data = await res.json().catch(function () { return {}; });
            if (res.ok && data.success) {
                msgEl.innerHTML = '<div class="alert alert-success">' + (data.message || 'Matter created.') + '</div>';
                if (data.conflict_warning && typeof iziToast !== 'undefined' && iziToast.show) {
                    iziToast.show({
                        message: data.conflict_warning,
                        color: 'orange',
                        position: 'topRight',
                        timeout: 8000
                    });
                }
                window.setTimeout(function () {
                    closeAddMatterModal();
                    window.location.reload();
                }, 600);
                return;
            }
            var errText = data.message || 'Could not create matter.';
            if (data.errors) {
                errText += ' ' + Object.values(data.errors).flat().join(' ');
            }
            msgEl.innerHTML = '<div class="alert alert-danger">' + errText + '</div>';
        } catch (e) {
            msgEl.innerHTML = '<div class="alert alert-danger">Network error. Try again.</div>';
        } finally {
            btn.disabled = false;
        }
    }

    window.openAddMatterModal = openAddMatterModal;
    window.closeAddMatterModal = closeAddMatterModal;
    window.submitLeadMatterFromEdit = submitLeadMatterFromEdit;
    window.quickAddOpposingPartyRow = quickAddOpposingPartyRow;
})();
</script>
