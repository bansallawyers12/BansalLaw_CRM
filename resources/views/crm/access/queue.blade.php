@extends('layouts.crm_client_detail')
@section('title', 'Access requests')
@php
    $crmAccessReasonLabels = config('crm_access.quick_reason_options', []);
@endphp
@section('content')
<div class="main-content">
    <section class="section">
        <div class="section-body">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">Pending supervisor access requests</h4>
                    <div class="d-flex gap-2">
                        <a href="{{ route('crm.access.dashboard') }}" class="btn btn-sm btn-outline-primary">Grants dashboard</a>
                        <a href="{{ route('dashboard') }}" class="btn btn-sm btn-secondary">Main dashboard</a>
                    </div>
                </div>
                <div class="card-body">
                    <div id="crm-access-queue-msg" class="alert d-none" role="alert"></div>
                    <div class="table-responsive">
                        <table class="table table-striped table-sm" id="crm-access-queue-table">
                            <thead>
                                <tr>
                                    <th>Requested</th>
                                    <th>Requester</th>
                                    <th>Record</th>
                                    <th>Reason / note</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
@endsection
@push('scripts')
<script>
(function () {
    var dataUrl = @json($dataUrl);
    var reasonLabels = @json($crmAccessReasonLabels);
    var approveTpl = @json(str_replace('999999999', '__ID__', route('crm.access.approve', ['grant' => 999999999])));
    var rejectTpl = @json(str_replace('999999999', '__ID__', route('crm.access.reject', ['grant' => 999999999])));
    var token = document.querySelector('meta[name="csrf-token"]');
    token = token ? token.getAttribute('content') : '';
    var fmtWhen = typeof window.formatGrantWhen === 'function'
        ? window.formatGrantWhen
        : function (v) { return v == null || v === '' ? '' : String(v); };

    function showMsg(text, isErr) {
        var el = document.getElementById('crm-access-queue-msg');
        el.textContent = text;
        el.className = 'alert ' + (isErr ? 'alert-danger' : 'alert-success');
        el.classList.remove('d-none');
    }

    function rowHtml(g) {
        var req = g.staff ? (g.staff.first_name + ' ' + g.staff.last_name).trim() : ('#' + g.staff_id);
        var rec = g.admin ? (g.admin.first_name + ' ' + g.admin.last_name).trim() : ('#' + g.admin_id);
        var reasonCode = g.quick_reason_code || '';
        var reasonText = reasonCode && reasonLabels[reasonCode] ? String(reasonLabels[reasonCode]) : reasonCode;
        reasonText = reasonText ? String(reasonText).replace(/</g, '&lt;') : '';
        var note = g.requester_note ? String(g.requester_note).replace(/</g, '&lt;') : '';
        var reasonNote = '';
        if (reasonText && note) {
            reasonNote = '<span class="text-muted">Reason:</span> ' + reasonText + '<br><span class="text-muted">Note:</span> ' + note;
        } else if (reasonText) {
            reasonNote = reasonText;
        } else {
            reasonNote = note;
        }
        var rid = g.id;
        return '<tr data-grant-id="' + rid + '">' +
            '<td>' + fmtWhen(g.requested_at) + '</td>' +
            '<td>' + req + '</td>' +
            '<td>' + rec + ' <span class="text-muted">(' + g.record_type + ' #' + g.admin_id + ')</span></td>' +
            '<td>' + reasonNote + '</td>' +
            '<td class="text-nowrap">' +
            '<button type="button" class="btn btn-sm btn-success js-cag-approve" data-id="' + rid + '">Approve</button> ' +
            '<button type="button" class="btn btn-sm btn-outline-danger js-cag-reject" data-id="' + rid + '">Reject</button>' +
            '</td></tr>';
    }

    function load() {
        fetch(dataUrl, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                var tb = document.querySelector('#crm-access-queue-table tbody');
                tb.innerHTML = '';
                (data.items || []).forEach(function (g) {
                    tb.insertAdjacentHTML('beforeend', rowHtml(g));
                });
            })
            .catch(function () { showMsg('Failed to load queue.', true); });
    }

    document.addEventListener('click', function (e) {
        if (e.target.matches('.js-cag-approve')) {
            var id = e.target.getAttribute('data-id');
            fetch(approveTpl.replace('__ID__', id), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': token,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: '{}'
            }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
              .then(function (x) {
                  if (!x.ok) { showMsg(x.j.message || 'Approve failed', true); return; }
                  showMsg(x.j.message || 'Approved', false);
                  load();
              }).catch(function () { showMsg('Approve failed', true); });
        }
        if (e.target.matches('.js-cag-reject')) {
            var id2 = e.target.getAttribute('data-id');
            var reason = window.prompt('Reject reason (optional):') || '';
            fetch(rejectTpl.replace('__ID__', id2), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': token,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ reason: reason })
            }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
              .then(function (x) {
                  if (!x.ok) { showMsg(x.j.message || 'Reject failed', true); return; }
                  showMsg(x.j.message || 'Rejected', false);
                  load();
              }).catch(function () { showMsg('Reject failed', true); });
        }
    });

    document.addEventListener('DOMContentLoaded', load);
})();
</script>
@endpush
