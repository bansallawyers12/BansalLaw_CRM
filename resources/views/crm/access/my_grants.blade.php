@extends('layouts.crm_client_detail')
@section('title', 'My access requests')
@section('content')
<div class="main-content">
    <section class="section">
        <div class="section-body">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">My access requests</h4>
                    <a href="{{ route('dashboard') }}" class="btn btn-sm btn-secondary">Dashboard</a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-sm" id="crm-my-grants-table">
                            <thead>
                                <tr>
                                    <th>Requested</th>
                                    <th>Record #</th>
                                    <th>Type</th>
                                    <th>Grant type</th>
                                    <th>Status</th>
                                    <th>Expires</th>
                                </tr>
                            </thead>
                            <tbody id="crm-my-grants-body">
                                <tr><td colspan="6" class="text-center text-muted py-3">Loading…</td></tr>
                            </tbody>
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
    var dataUrl = @json(route('crm.access.my-grants.data'));
    var fmtWhen = typeof window.formatGrantWhen === 'function'
        ? window.formatGrantWhen
        : function (v) { return v == null || v === '' ? '' : String(v); };

    function statusBadge(status) {
        var map = {
            active: 'success',
            pending: 'warning',
            expired: 'secondary',
            revoked: 'danger',
            rejected: 'danger',
        };
        var cls = map[status] || 'light';
        return '<span class="badge badge-' + cls + '">' + status + '</span>';
    }

    function row(g) {
        var expires = g.ends_at ? (fmtWhen(g.ends_at) || '—') : '—';
        return '<tr>' +
            '<td>' + (g.requested_at ? (fmtWhen(g.requested_at) || '—') : '—') + '</td>' +
            '<td>#' + g.admin_id + '</td>' +
            '<td>' + (g.record_type || '—') + '</td>' +
            '<td>' + (g.grant_type || '—').replace('_', ' ') + '</td>' +
            '<td>' + statusBadge(g.status) + '</td>' +
            '<td>' + expires + '</td>' +
            '</tr>';
    }

    fetch(dataUrl, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            var tbody = document.getElementById('crm-my-grants-body');
            var items = data.items || [];
            if (items.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">No access requests yet.</td></tr>';
                return;
            }
            tbody.innerHTML = items.map(row).join('');
        })
        .catch(function () {
            document.getElementById('crm-my-grants-body').innerHTML =
                '<tr><td colspan="6" class="text-center text-danger py-3">Failed to load.</td></tr>';
        });
})();
</script>
@endpush
