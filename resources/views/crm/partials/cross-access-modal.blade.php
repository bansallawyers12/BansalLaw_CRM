@php
    $crossAccessLeadBase = $crossAccessLeadBase ?? url('/history');
    $crossAccessAutoOpen = $crossAccessAutoOpen ?? session('crm_access_modal_payload');
@endphp

<style>
    /* Layout sets .btn { border: none }, which breaks outline-style CTAs; keep footer actions readable */
    #crmCrossAccessModal .modal-footer .btn-primary {
        background-color: #3498db;
        color: #fff;
        border: 1px solid #2980b9;
    }
    #crmCrossAccessModal .modal-footer .btn-primary:hover {
        background-color: #2980b9;
        color: #fff;
        border-color: #21618c;
    }
    #crmCrossAccessModal .modal-footer .btn-secondary {
        color: #fff;
        background-color: #6c757d;
        border: 1px solid #5a6268;
    }
</style>

<div class="modal fade" id="crmCrossAccessModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Request access</h5>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <p class="text-muted mb-2" id="crmCrossAccessRecordLabel"></p>
                <input type="hidden" id="crmCrossAccessAdminId" value="">
                <input type="hidden" id="crmCrossAccessRecordType" value="">
                <input type="hidden" id="crmCrossAccessNavId" value="">
                <input type="hidden" id="crmCrossAccessRedirectTo" value="">
                <div class="form-group">
                    <label for="crmCrossAccessOffice">Office</label>
                    <select id="crmCrossAccessOffice" class="form-control"></select>
                </div>
                <div class="form-group" id="crmCrossAccessReasonWrap">
                    <label for="crmCrossAccessReason">Reason for request</label>
                    <select id="crmCrossAccessReason" class="form-control"></select>
                </div>
                <div class="form-group d-none" id="crmCrossAccessNoteWrap">
                    <label for="crmCrossAccessNote">Note for supervisor</label>
                    <textarea id="crmCrossAccessNote" class="form-control" rows="3"></textarea>
                </div>
                <div class="alert d-none" id="crmCrossAccessMsg" role="alert"></div>
            </div>
            <div class="modal-footer d-flex flex-wrap gap-2 justify-content-end" id="crmCrossAccessModalFooter">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary d-none" id="crmCrossAccessBtnQuick">Quick access ({{ config('crm_access.quick_grant_minutes', 15) }} min)</button>
                <button type="button" class="btn btn-primary d-none" id="crmCrossAccessBtnSupervisor">Request supervisor access</button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function () {
    window.crmAccessLeadUrlPrefix = @json($crossAccessLeadBase);
    window.crmAccessMetaUrl = @json(route('crm.access.meta'));
    window.crmAccessQuickUrl = @json(route('crm.access.quick'));
    window.crmAccessSupervisorUrl = @json(route('crm.access.supervisor'));
    window.crmClientDetailBase = @json(url('/clients/detail'));
    window.crmAccessAutoOpen = @json($crossAccessAutoOpen);

    window.buildClientDetailUrlFromSearchId = function (selId) {
        var s = String(selId).split('/');
        if (s[1] === 'Matter' && s[2]) {
            return window.crmClientDetailBase + '/' + s[0] + '/' + s[2];
        }
        if (s[1] === 'Client') {
            return window.crmClientDetailBase + '/' + s[0];
        }
        return window.crmAccessLeadUrlPrefix + '/' + s[0];
    };

    function csrf() {
        var m = document.querySelector('meta[name="csrf-token"]');
        return m ? m.getAttribute('content') : '';
    }

    function showModalMsg(text, isErr) {
        var el = document.getElementById('crmCrossAccessMsg');
        el.textContent = text;
        el.className = 'alert ' + (isErr ? 'alert-danger' : 'alert-success');
        el.classList.remove('d-none');
    }

    function loadMeta(cb) {
        fetch(window.crmAccessMetaUrl, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (meta) {
                var off = document.getElementById('crmCrossAccessOffice');
                var reason = document.getElementById('crmCrossAccessReason');
                off.innerHTML = '';
                (meta.branches || []).forEach(function (b) {
                    var o = document.createElement('option');
                    o.value = b.id;
                    o.textContent = b.office_name;
                    off.appendChild(o);
                });
                if (meta.staff_office_id) {
                    off.value = String(meta.staff_office_id);
                }
                reason.innerHTML = '';
                (meta.quick_reasons || []).forEach(function (r) {
                    var o = document.createElement('option');
                    o.value = r.code;
                    o.textContent = r.label;
                    reason.appendChild(o);
                });
                var ui = meta.ui || {};
                var btnQ = document.getElementById('crmCrossAccessBtnQuick');
                var btnS = document.getElementById('crmCrossAccessBtnSupervisor');
                btnQ.classList.toggle('d-none', !ui.show_quick);
                btnS.classList.toggle('d-none', !ui.show_supervisor);
                var showReason = !!(ui.show_quick || ui.show_supervisor);
                document.getElementById('crmCrossAccessReasonWrap').classList.toggle('d-none', !showReason);
                document.getElementById('crmCrossAccessNoteWrap').classList.toggle('d-none', ui.quick_only_role || !ui.show_supervisor);
                if (cb) cb(meta);
            })
            .catch(function () { showModalMsg('Could not load form options.', true); });
    }

    window.openCrmAccessModal = function (repo) {
        document.getElementById('crmCrossAccessMsg').classList.add('d-none');
        document.getElementById('crmCrossAccessRecordLabel').textContent = (repo.name || '') + ' — ' + (repo.record_type || '') + ' #' + (repo.cid || '');
        document.getElementById('crmCrossAccessAdminId').value = repo.cid || '';
        document.getElementById('crmCrossAccessRecordType').value = repo.record_type || 'client';
        document.getElementById('crmCrossAccessNavId').value = repo.id || '';
        document.getElementById('crmCrossAccessRedirectTo').value = repo.redirect_to || '';
        document.getElementById('crmCrossAccessNote').value = '';
        loadMeta(function () {
            var el = document.getElementById('crmCrossAccessModal');
            if (el && window.bootstrap && bootstrap.Modal) {
                bootstrap.Modal.getOrCreateInstance(el).show();
            } else if (window.jQuery && jQuery.fn.modal) {
                jQuery('#crmCrossAccessModal').modal('show');
            }
        });
    };

    function postJson(url, body, done) {
        fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf(),
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(body)
        }).then(function (r) {
            return r.json().then(function (j) { return { ok: r.ok, j: j }; });
        }).then(done).catch(function () { done({ ok: false, j: { message: 'Network error' } }); });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var btnQ = document.getElementById('crmCrossAccessBtnQuick');
        var btnS = document.getElementById('crmCrossAccessBtnSupervisor');
        if (btnQ) {
            btnQ.addEventListener('click', function () {
                var adminId = parseInt(document.getElementById('crmCrossAccessAdminId').value, 10);
                var recordType = document.getElementById('crmCrossAccessRecordType').value;
                var officeId = parseInt(document.getElementById('crmCrossAccessOffice').value, 10);
                var reason = document.getElementById('crmCrossAccessReason').value;
                postJson(window.crmAccessQuickUrl, {
                    admin_id: adminId,
                    record_type: recordType,
                    office_id: officeId,
                    reason_code: reason
                }, function (x) {
                    if (!x.ok) { showModalMsg(x.j.message || 'Failed', true); return; }
                    var redirectTo = document.getElementById('crmCrossAccessRedirectTo').value;
                    if (redirectTo) {
                        window.location.href = redirectTo;
                        return;
                    }
                    var nav = document.getElementById('crmCrossAccessNavId').value;
                    window.location.href = window.buildClientDetailUrlFromSearchId(nav);
                });
            });
        }
        if (btnS) {
            btnS.addEventListener('click', function () {
                var adminId = parseInt(document.getElementById('crmCrossAccessAdminId').value, 10);
                var recordType = document.getElementById('crmCrossAccessRecordType').value;
                var officeId = parseInt(document.getElementById('crmCrossAccessOffice').value, 10);
                var reasonCode = document.getElementById('crmCrossAccessReason').value;
                var note = document.getElementById('crmCrossAccessNote').value;
                postJson(window.crmAccessSupervisorUrl, {
                    admin_id: adminId,
                    record_type: recordType,
                    office_id: officeId,
                    reason_code: reasonCode,
                    note: note
                }, function (x) {
                    if (!x.ok) { showModalMsg(x.j.message || 'Failed', true); return; }
                    showModalMsg('Request submitted. Approvers have been notified.', false);
                });
            });
        }

        if (window.crmAccessAutoOpen && typeof window.openCrmAccessModal === 'function') {
            window.openCrmAccessModal(window.crmAccessAutoOpen);
            window.crmAccessAutoOpen = null;
        }
    });
})();
</script>
@endpush
