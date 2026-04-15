@extends('layouts.crm_client_detail')
@section('title', 'Website Bookings')

@section('content')

<style>
/* Website Bookings — Powder Blue & Soft Gold (docs/theme.md) */
.booking-appointments-page {
    overflow-x: hidden !important;
    max-width: 100% !important;
}

/* Main panel header — white card, navy title (overrides Stisla / legacy blue bars) */
.booking-appointments-page > .row > .col-12 > .card > .card-header {
    background: var(--card-bg) !important;
    background-color: var(--card-bg) !important;
    border-bottom: 1px solid var(--border) !important;
    color: var(--navy) !important;
}

.booking-appointments-page > .row > .col-12 > .card > .card-header h4 {
    color: var(--navy) !important;
    font-weight: 700;
}

.booking-appointments-page > .row > .col-12 > .card > .card-header h4 .text-muted {
    color: var(--text-muted) !important;
    font-weight: 600;
}

.booking-appointments-page > .row > .col-12 > .card > .card-body {
    background: var(--page-bg) !important;
}

/* KPI stat cards — Stisla uses blue .card-header + white icons; reset to theme.md */
.booking-appointments-page .card.card-statistic-1 {
    border: 1px solid var(--border);
    border-radius: 10px;
    box-shadow: 0 1px 4px rgba(30, 61, 96, 0.06);
    overflow: hidden;
    background: var(--card-bg) !important;
}

.booking-appointments-page .card.card-statistic-1 .card-icon {
    border-radius: 0 !important;
}

.booking-appointments-page .card.card-statistic-1 .card-icon i {
    color: inherit !important;
    opacity: 1 !important;
}

.booking-appointments-page .card.card-statistic-1 .card-icon.bg-warning {
    background: rgba(200, 153, 42, 0.2) !important;
    background-color: rgba(200, 153, 42, 0.2) !important;
    color: var(--accent-gold) !important;
}

.booking-appointments-page .card.card-statistic-1 .card-icon.bg-warning i {
    color: var(--accent-gold) !important;
}

.booking-appointments-page .card.card-statistic-1 .card-icon.bg-primary {
    background: rgba(30, 61, 96, 0.1) !important;
    background-color: rgba(30, 61, 96, 0.1) !important;
    color: var(--navy) !important;
}

.booking-appointments-page .card.card-statistic-1 .card-icon.bg-primary i {
    color: var(--navy) !important;
}

.booking-appointments-page .card.card-statistic-1 .card-icon.bg-success {
    background: rgba(30, 122, 82, 0.14) !important;
    background-color: rgba(30, 122, 82, 0.14) !important;
    color: var(--success) !important;
}

.booking-appointments-page .card.card-statistic-1 .card-icon.bg-success i {
    color: var(--success) !important;
}

.booking-appointments-page .card.card-statistic-1 .card-icon.bg-info {
    background: rgba(58, 111, 168, 0.14) !important;
    background-color: rgba(58, 111, 168, 0.14) !important;
    color: var(--sidebar-active) !important;
}

.booking-appointments-page .card.card-statistic-1 .card-icon.bg-info i {
    color: var(--sidebar-active) !important;
}

/* Inner label row — must not use theme’s blue header strip */
.booking-appointments-page .card.card-statistic-1 .card-wrap .card-header {
    background: var(--card-bg) !important;
    background-color: var(--card-bg) !important;
    border-bottom: 1px solid var(--border) !important;
    color: var(--text-muted) !important;
    padding: 0.65rem 1rem !important;
}

.booking-appointments-page .card.card-statistic-1 .card-wrap .card-header h4 {
    font-size: 11.5px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--text-muted) !important;
    margin: 0 !important;
}

.booking-appointments-page .card.card-statistic-1 .card-wrap .card-body {
    font-size: 28px;
    font-weight: 700;
    color: var(--navy) !important;
    background: var(--card-bg) !important;
    background-color: var(--card-bg) !important;
    padding: 0.75rem 1rem !important;
    line-height: 1.2;
}

/* Filter strip */
.booking-appointments-page .filter-section {
    background: var(--page-bg);
    border: 1px solid var(--border);
    padding: 16px;
    border-radius: 10px;
    margin-bottom: 20px;
}

.booking-appointments-page .filter-section label {
    color: var(--text-muted);
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.booking-appointments-page .filter-section .form-control {
    border-color: var(--border);
    border-radius: 8px;
    color: var(--text-dark);
}

.booking-appointments-page .filter-section .form-control:focus {
    border-color: var(--sidebar-active);
    box-shadow: 0 0 0 3px rgba(58, 111, 168, 0.15);
}

/* Table — theme.md tables */
.booking-appointments-page .table-responsive table.table {
    --bs-table-bg: transparent;
    --bs-table-color: var(--text-dark);
    --bs-table-striped-bg: rgba(221, 234, 248, 0.35);
    --bs-table-hover-bg: #ebf3ff;
    border-color: var(--border);
}

.booking-appointments-page .table-responsive table.table thead th {
    background: var(--page-bg) !important;
    color: var(--text-muted) !important;
    font-weight: 600;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    border-color: var(--border) !important;
}

.booking-appointments-page .table-responsive table.table tbody td {
    color: var(--text-dark) !important;
    border-color: var(--border) !important;
    vertical-align: middle;
}

.booking-appointments-page .table-responsive table.table tbody td .text-muted {
    color: var(--text-muted) !important;
}

.booking-appointments-page .table-responsive table.table tbody td small.text-muted {
    color: var(--text-muted) !important;
}

.booking-appointments-page .table-responsive table.table tbody td small[style*="color"] {
    color: var(--text-muted) !important;
    display: block !important;
    visibility: visible !important;
}

/* Status / payment badges — theme status colours */
.booking-appointments-page .table .badge {
    font-weight: 600;
    border-radius: 999px;
    padding: 0.35em 0.65em;
}

.booking-appointments-page .table .badge.badge-warning {
    background: rgba(200, 153, 42, 0.15) !important;
    color: #7a5800 !important;
}

.booking-appointments-page .table .badge.badge-primary {
    background: rgba(30, 61, 96, 0.12) !important;
    color: var(--navy) !important;
}

.booking-appointments-page .table .badge.badge-success {
    background: rgba(30, 122, 82, 0.12) !important;
    color: var(--success) !important;
}

.booking-appointments-page .table .badge.badge-info {
    background: rgba(58, 111, 168, 0.12) !important;
    color: var(--sidebar-active) !important;
}

.booking-appointments-page .table .badge.badge-danger {
    background: rgba(168, 48, 32, 0.12) !important;
    color: var(--danger) !important;
}

.booking-appointments-page .table .badge.badge-secondary {
    background: rgba(94, 122, 144, 0.12) !important;
    color: var(--text-muted) !important;
}

.booking-appointments-page .table .badge.badge-dark {
    background: rgba(94, 122, 144, 0.18) !important;
    color: var(--text-dark) !important;
}

.booking-appointments-page .table tbody td .badge {
    color: inherit !important;
}

/* Action buttons — keep icon contrast */
.booking-appointments-page .btn-sm.btn-primary {
    background: var(--navy);
    border-color: var(--navy);
}

.booking-appointments-page .btn-sm.btn-primary:hover {
    background: var(--sidebar-active);
    border-color: var(--sidebar-active);
}

.booking-appointments-page .btn-sm.btn-warning {
    background: var(--accent-gold);
    border-color: var(--accent-gold);
    color: #fff;
}

.booking-appointments-page .btn-sm.btn-info {
    background: var(--sidebar-active);
    border-color: var(--sidebar-active);
    color: #fff;
}

/*
 * custom.css targets .card-header-action .btn-primary for legacy purple gradient headers
 * (rgba white fill + white text). On our light theme header that reads as invisible on hover.
 */
.booking-appointments-page .card-header-action .btn.btn-primary {
    background: var(--navy) !important;
    border: 1px solid var(--navy) !important;
    color: #fff !important;
    opacity: 1 !important;
    visibility: visible !important;
}

.booking-appointments-page .card-header-action .btn.btn-primary:hover,
.booking-appointments-page .card-header-action .btn.btn-primary:focus {
    background: var(--sidebar-active) !important;
    border-color: var(--sidebar-active) !important;
    color: #fff !important;
    filter: none !important;
}

.booking-appointments-page .card-header-action .btn.btn-info {
    background: var(--sidebar-active) !important;
    border: 1px solid var(--sidebar-active) !important;
    color: #fff !important;
}

.booking-appointments-page .card-header-action .btn.btn-info:hover,
.booking-appointments-page .card-header-action .btn.btn-info:focus {
    background: var(--navy) !important;
    border-color: var(--navy) !important;
    color: #fff !important;
    filter: none !important;
}

.booking-appointments-page .table-responsive table.table tbody td a {
    color: var(--sidebar-active);
    font-weight: 600;
}

.booking-appointments-page .table-responsive table.table tbody td a:hover {
    color: var(--navy);
}

/* Pagination — Powder Blue & Soft Gold (docs/theme.md); override Bootstrap primary blue */
.booking-appointments-page #appointments-pagination p.text-muted,
.booking-appointments-page #appointments-pagination .text-muted {
    color: var(--text-muted) !important;
}

.booking-appointments-page #appointments-pagination .pagination {
    gap: 0.25rem;
}

.booking-appointments-page #appointments-pagination .pagination .page-link {
    color: var(--navy);
    background-color: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: 8px;
    font-weight: 600;
}

.booking-appointments-page #appointments-pagination .pagination .page-link:hover {
    color: var(--navy);
    background-color: var(--sidebar-bg);
    border-color: var(--sidebar-hover);
}

.booking-appointments-page #appointments-pagination .pagination .page-link:focus {
    box-shadow: 0 0 0 3px rgba(58, 111, 168, 0.2);
    color: var(--navy);
}

.booking-appointments-page #appointments-pagination .pagination .page-item.active .page-link {
    background-color: var(--navy);
    border-color: var(--navy);
    color: #fff;
}

.booking-appointments-page #appointments-pagination .pagination .page-item.active .page-link:hover,
.booking-appointments-page #appointments-pagination .pagination .page-item.active .page-link:focus {
    background-color: var(--sidebar-active);
    border-color: var(--sidebar-active);
    color: #fff;
}

.booking-appointments-page #appointments-pagination .pagination .page-item.disabled .page-link {
    color: var(--text-muted);
    background-color: var(--page-bg);
    border-color: var(--border);
    opacity: 0.7;
    pointer-events: none;
}
</style>

<div class="section-body booking-appointments-page">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4>
                        <i class="fas fa-globe mr-2"></i> 
                        Website Bookings 
                        <small class="text-muted">(Live list from {{ rtrim(config('services.appointment_api.url'), '/') }}/appointments)</small>
                    </h4>
                    <div class="card-header-action">
                        @if(Auth::user() && in_array(Auth::user()->role, [1, 12]))
                        <a href="{{ route('booking.sync.dashboard') }}" class="btn btn-sm btn-info">
                            <i class="fas fa-sync"></i> Sync Status
                        </a>
                        <button onclick="manualSync()" class="btn btn-sm btn-primary">
                            <i class="fas fa-sync-alt"></i> Manual Sync
                        </button>
                        @endif
                    </div>
                </div>
                <div class="card-body">
                    <!-- Statistics Cards -->
                    <div class="row mb-4">
                        <div class="col-lg col-md-6 col-sm-6 col-12">
                            <div class="card card-statistic-1">
                                <div class="card-icon bg-warning">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <div class="card-wrap">
                                    <div class="card-header">
                                        <h4>Payment Pending</h4>
                                    </div>
                                    <div class="card-body">
                                        {{ $stats['pending'] ?? 0 }}
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg col-md-6 col-sm-6 col-12">
                            <div class="card card-statistic-1">
                                <div class="card-icon bg-primary">
                                    <i class="fas fa-dollar-sign"></i>
                                </div>
                                <div class="card-wrap">
                                    <div class="card-header">
                                        <h4>Paid</h4>
                                    </div>
                                    <div class="card-body">
                                        {{ $stats['paid'] ?? 0 }}
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg col-md-6 col-sm-6 col-12">
                            <div class="card card-statistic-1">
                                <div class="card-icon bg-success">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <div class="card-wrap">
                                    <div class="card-header">
                                        <h4>Confirmed</h4>
                                    </div>
                                    <div class="card-body">
                                        {{ $stats['confirmed'] ?? 0 }}
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg col-md-6 col-sm-6 col-12">
                            <div class="card card-statistic-1">
                                <div class="card-icon bg-info">
                                    <i class="fas fa-calendar-check"></i>
                                </div>
                                <div class="card-wrap">
                                    <div class="card-header">
                                        <h4>Today</h4>
                                    </div>
                                    <div class="card-body">
                                        {{ $stats['today'] ?? 0 }}
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg col-md-6 col-sm-6 col-12">
                            <div class="card card-statistic-1">
                                <div class="card-icon bg-primary">
                                    <i class="fas fa-list"></i>
                                </div>
                                <div class="card-wrap">
                                    <div class="card-header">
                                        <h4>Total</h4>
                                    </div>
                                    <div class="card-body">
                                        {{ $stats['total'] ?? 0 }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Filters -->
                    <div class="filter-section">
                        <form method="GET" action="{{ route('booking.appointments.index') }}" id="filter-form" autocomplete="off">
                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <label>Search with Client Reference, Description</label>
                                    <input type="text" class="form-control" name="search" id="filter-search" 
                                           value="{{ request('search') }}" 
                                           placeholder="Search with Client reference, description">
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-3">
                                    <label>Status</label>
                                    <select class="form-control" name="status" id="filter-status">
                                        <option value="">All Status</option>
                                        <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                                        <option value="confirmed" {{ request('status') == 'confirmed' ? 'selected' : '' }}>Confirmed</option>
                                        <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>Completed</option>
                                        <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                                        <option value="no_show" {{ request('status') == 'no_show' ? 'selected' : '' }}>No Show</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label>Consultant</label>
                                    <select class="form-control" name="consultant_id" id="filter-consultant">
                                        <option value="">All Consultants</option>
                                        @foreach($consultants as $consultant)
                                            <option value="{{ $consultant->id }}" {{ request('consultant_id') == $consultant->id ? 'selected' : '' }}>
                                                {{ $consultant->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label>From Date</label>
                                    <input type="date" class="form-control" name="date_from" id="filter-date-from" value="{{ request('date_from') }}">
                                </div>
                                <div class="col-md-2">
                                    <label>To Date</label>
                                    <input type="date" class="form-control" name="date_to" id="filter-date-to" value="{{ request('date_to') }}">
                                </div>
                                <div class="col-md-2">
                                    <label>&nbsp;</label>
                                    <div>
                                        <button type="submit" class="btn btn-primary" style="width: calc(50% - 5px); margin-right: 5px;">
                                            <i class="fas fa-filter"></i> Filter
                                        </button>
                                        <a href="{{ route('booking.appointments.index') }}" class="btn btn-secondary" style="width: calc(50% - 5px);">
                                            <i class="fas fa-redo"></i> Reset
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-striped table-hover" id="appointments-table">
                            <thead>
                                <tr>
                                    <th width="120">CRM ID</th>
                                    <th>Client</th>
                                    <th>Appointment</th>
                                    <th>Service</th>
                                    <th>Consultant</th>
                                    <th>Description</th>
                                    <th>Status</th>
                                    <th>Payment</th>
                                    <th width="150">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="appointments-table-body">
                                <tr>
                                    <td colspan="9" class="text-center text-muted py-4">
                                        <i class="fas fa-spinner fa-spin"></i> Loading appointments…
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div id="appointments-pagination" class="mt-3"></div>

                    <!-- Edit Date & Time Modal -->
                    <div class="modal fade" id="editDatetimeModal" tabindex="-1" role="dialog" aria-labelledby="editDatetimeModalLabel" aria-hidden="true">
                        <div class="modal-dialog" role="document">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="editDatetimeModalLabel">Update Appointment Date & Time</h5>
                                    <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                                <form id="edit-datetime-form">
                                    @csrf
                                    <div class="modal-body">
                                        <input type="hidden" id="edit-datetime-appointment-id" name="appointment_id">
                                        <div id="edit-datetime-error" class="alert alert-danger d-none"></div>
                                        <div class="form-group">
                                            <label for="edit-appointment-date">Appointment Date</label>
                                            <input type="date" class="form-control" id="edit-appointment-date" name="appointment_date" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="edit-appointment-time">Appointment Time</label>
                                            <input type="time" class="form-control" id="edit-appointment-time" name="appointment_time" required>
                                        </div>
                                        <p class="text-muted mb-0">
                                            Changes are saved immediately after submission. Only date and time can be modified here.
                                        </p>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-primary" id="edit-datetime-submit">Save Changes</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const appointmentsListApiUrl = @json(route('booking.api.appointments'));
const appointmentsListPerPage = 20;
let appointmentsListCurrentPage = {{ (int) request('page', 1) }};

function escapeHtml(text) {
    if (text === null || text === undefined) {
        return '';
    }
    const d = document.createElement('div');
    d.textContent = text;
    return d.innerHTML;
}

function getAppointmentsListParams(page) {
    return {
        format: 'list',
        page: page,
        per_page: appointmentsListPerPage,
        search: ($('#filter-search').val() || '').trim(),
        status: $('#filter-status').val() || '',
        consultant_id: $('#filter-consultant').val() || '',
        date_from: $('#filter-date-from').val() || '',
        date_to: $('#filter-date-to').val() || ''
    };
}

function syncAppointmentsUrlToAddressBar(page) {
    const p = getAppointmentsListParams(page);
    const params = new URLSearchParams();
    if (p.search) {
        params.set('search', p.search);
    }
    if (p.status) {
        params.set('status', p.status);
    }
    if (p.consultant_id) {
        params.set('consultant_id', p.consultant_id);
    }
    if (p.date_from) {
        params.set('date_from', p.date_from);
    }
    if (p.date_to) {
        params.set('date_to', p.date_to);
    }
    if (page > 1) {
        params.set('page', String(page));
    }
    const qs = params.toString();
    window.history.replaceState({}, '', window.location.pathname + (qs ? ('?' + qs) : ''));
}

function buildAppointmentRowHtml(row) {
    let clientCell = '';
    const name = escapeHtml(row.client_name || '');
    const email = escapeHtml(row.client_email || '');
    const phone = escapeHtml(row.client_phone || '');
    if (row.client_id && row.client_detail_url) {
        const href = row.client_detail_url;
        clientCell += '<strong><a href="' + escapeHtml(href) + '" target="_blank">' + name + '</a></strong><br>';
        clientCell += '<small><a href="' + escapeHtml(href) + '" target="_blank">' + email + '</a></small><br>';
        clientCell += '<small>' + phone + '</small>';
        if (row.client_reference) {
            clientCell += '<div class="mt-1"><small class="text-muted">' + escapeHtml(row.client_reference) + '</small></div>';
        }
    } else {
        clientCell += '<strong>' + name + '</strong><br><small>' + email + '</small><br><small>' + phone + '</small>';
    }

    const timeLine = row.timeslot_full
        ? escapeHtml(row.timeslot_full)
        : escapeHtml(row.appointment_time_label || '');
    const locRaw = row.location ? String(row.location) : '';
    const locLabel = locRaw
        ? escapeHtml(locRaw.charAt(0).toUpperCase() + locRaw.slice(1))
        : '';
    const serviceMain = row.service_type ? escapeHtml(row.service_type) : 'N/A';
    const serviceSub = row.enquiry_type ? escapeHtml(row.enquiry_type) : '';
    const consultantCell = row.consultant_name
        ? escapeHtml(row.consultant_name)
        : '<span class="text-muted">Not Assigned</span>';
    let descCell = '<span class="text-muted">N/A</span>';
    if (row.enquiry_details_short) {
        descCell = '<small>' + escapeHtml(row.enquiry_details_short) + '</small>';
    }

    const badgeClass = escapeHtml(row.status_badge_class || 'secondary');
    const statusLabel = escapeHtml(row.status_label || '');
    const amt = Number(row.final_amount);
    const paymentCell = row.is_paid
        ? ('<span class="badge badge-primary">Paid</span><br><small>$' + escapeHtml(amt.toFixed(2)) + '</small>')
        : '<span class="badge badge-secondary">Free</span>';

    const idDisp = escapeHtml(String(row.id != null ? row.id : ''));
    const showUrl = row.show_url || '';
    const editUrl = row.edit_url || '';
    const viewBtn = showUrl
        ? ('<a href="' + escapeHtml(showUrl) + '" class="btn btn-sm btn-primary" title="View in CRM"><i class="fas fa-eye"></i></a>')
        : ('<button type="button" class="btn btn-sm btn-secondary" disabled title="Not synced to CRM yet"><i class="fas fa-eye"></i></button>');
    const editBtn = editUrl
        ? ('<a href="' + escapeHtml(editUrl) + '" class="btn btn-sm btn-warning" title="Edit in CRM"><i class="fas fa-edit"></i></a>')
        : ('<button type="button" class="btn btn-sm btn-secondary" disabled title="Not synced to CRM yet"><i class="fas fa-edit"></i></button>');
    const crmId = row.crm_appointment_id;
    const quickBtn = crmId
        ? ('<button type="button" class="btn btn-sm btn-info quick-action-btn" data-id="' + escapeHtml(String(crmId)) + '" title="Quick Actions"><i class="fas fa-bolt"></i></button>')
        : ('<button type="button" class="btn btn-sm btn-secondary" disabled title="Requires synced CRM record"><i class="fas fa-bolt"></i></button>');

    return (
        '<tr>' +
        '<td>' + idDisp + '</td>' +
        '<td>' + clientCell + '</td>' +
        '<td><strong>' + escapeHtml(row.appointment_date_label || '') + '</strong><br>' +
        '<small>' + timeLine + '</small><br>' +
        '<small><i class="fas fa-map-marker-alt"></i> ' + locLabel + '</small></td>' +
        '<td>' + serviceMain + '<br><small>' + serviceSub + '</small></td>' +
        '<td>' + consultantCell + '</td>' +
        '<td>' + descCell + '</td>' +
        '<td><span class="badge badge-' + badgeClass + '">' + statusLabel + '</span></td>' +
        '<td>' + paymentCell + '</td>' +
        '<td>' + viewBtn + ' ' + editBtn + ' ' + quickBtn + '</td>' +
        '</tr>'
    );
}

function renderAppointmentsPagination(meta) {
    const $wrap = $('#appointments-pagination');
    $wrap.empty();
    if (!meta || meta.last_page <= 1) {
        return;
    }
    const cur = meta.current_page;
    const last = meta.last_page;
    const from = meta.from != null ? meta.from : 0;
    const to = meta.to != null ? meta.to : 0;
    const total = meta.total != null ? meta.total : 0;

    let html = '<div class="d-flex flex-column align-items-center">';
    html += '<p class="text-muted small mb-2">Showing ' + from + '–' + to + ' of ' + total + '</p>';
    html += '<nav><ul class="pagination pagination-sm mb-0">';

    html += '<li class="page-item' + (cur <= 1 ? ' disabled' : '') + '">';
    html += '<a class="page-link appointments-page-link" href="#" data-page="' + (cur - 1) + '">Previous</a></li>';

    const windowSize = 5;
    let start = Math.max(1, cur - Math.floor(windowSize / 2));
    let end = Math.min(last, start + windowSize - 1);
    if (end - start < windowSize - 1) {
        start = Math.max(1, end - windowSize + 1);
    }
    for (let i = start; i <= end; i++) {
        html += '<li class="page-item' + (i === cur ? ' active' : '') + '">';
        html += '<a class="page-link appointments-page-link" href="#" data-page="' + i + '">' + i + '</a></li>';
    }

    html += '<li class="page-item' + (cur >= last ? ' disabled' : '') + '">';
    html += '<a class="page-link appointments-page-link" href="#" data-page="' + (cur + 1) + '">Next</a></li>';

    html += '</ul></nav></div>';
    $wrap.html(html);
}

function loadAppointmentsList(page) {
    appointmentsListCurrentPage = page;
    const $tbody = $('#appointments-table-body');
    $tbody.html(
        '<tr><td colspan="9" class="text-center text-muted py-4">' +
        '<i class="fas fa-spinner fa-spin"></i> Loading appointments…</td></tr>'
    );

    $.ajax({
        url: appointmentsListApiUrl,
        method: 'GET',
        data: getAppointmentsListParams(page),
        dataType: 'json'
    }).done(function (res) {
        if (res.message && !res.data) {
            $tbody.html(
                '<tr><td colspan="9" class="text-center text-danger py-4">' + escapeHtml(res.message) + '</td></tr>'
            );
            $('#appointments-pagination').empty();
            return;
        }
        const rows = res.data || [];
        if (!rows.length) {
            $tbody.html(
                '<tr><td colspan="9" class="text-center text-muted py-4">' +
                '<i class="fas fa-info-circle"></i> No appointments found.</td></tr>'
            );
        } else {
            $tbody.html(rows.map(buildAppointmentRowHtml).join(''));
        }
        renderAppointmentsPagination(res.meta);
        syncAppointmentsUrlToAddressBar(page);
    }).fail(function (xhr) {
        const msg = (xhr.responseJSON && xhr.responseJSON.message)
            ? xhr.responseJSON.message
            : 'Could not load appointments.';
        $tbody.html(
            '<tr><td colspan="9" class="text-center text-danger py-4">' + escapeHtml(msg) + '</td></tr>'
        );
        $('#appointments-pagination').empty();
    });
}

$('#filter-form').on('submit', function (e) {
    e.preventDefault();
    loadAppointmentsList(1);
});

$(document).on('click', '.appointments-page-link', function (e) {
    e.preventDefault();
    const $li = $(this).closest('li');
    if ($li.hasClass('disabled')) {
        return;
    }
    const p = parseInt($(this).data('page'), 10);
    if (!isNaN(p) && p >= 1) {
        loadAppointmentsList(p);
    }
});

$(document).on('click', '.quick-action-btn', function () {
    const id = $(this).data('id');
    if (id) {
        quickAction(String(id));
    }
});

$(function () {
    loadAppointmentsList(appointmentsListCurrentPage);
});

function manualSync() {
    if (!confirm('Start manual sync now? This will fetch latest appointments from the public booking website.')) {
        return;
    }
    
    const hasSweetAlert = typeof Swal !== 'undefined';
    
    if (hasSweetAlert) {
        Swal.fire({
            title: 'Syncing...',
            text: 'Fetching appointments from public booking website',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
    }

    $.ajax({
        url: '{{ route("booking.sync.manual") }}',
        method: 'POST',
        data: {
            _token: '{{ csrf_token() }}'
        },
        success: function(response) {
            if (hasSweetAlert) {
                Swal.fire({
                    icon: 'success',
                    title: 'Sync Completed!',
                    html: `
                        <p>Fetched: ${response.stats.fetched}</p>
                        <p>New: ${response.stats.new}</p>
                        <p>Updated: ${response.stats.updated}</p>
                        <p>Failed: ${response.stats.failed}</p>
                    `,
                    confirmButtonText: 'OK'
                }).then(() => {
                    window.location.reload();
                });
            } else {
                alert(
                    'Sync completed!\n' +
                    'Fetched: ' + response.stats.fetched + '\n' +
                    'New: ' + response.stats.new + '\n' +
                    'Updated: ' + response.stats.updated + '\n' +
                    'Failed: ' + response.stats.failed
                );
                window.location.reload();
            }
        },
        error: function(xhr) {
            const message = xhr.responseJSON?.message || 'An error occurred during sync';
            
            if (hasSweetAlert) {
                Swal.fire({
                    icon: 'error',
                    title: 'Sync Failed',
                    text: message,
                    confirmButtonText: 'OK'
                });
            } else {
                alert('Sync failed: ' + message);
            }
        }
    });
}

function quickAction(appointmentId) {
    // This can open a modal with quick actions
    window.location.href = '{{ url("/booking/appointments") }}/' + appointmentId;
}

const baseBookingAppointmentUrl = '{{ url("/booking/appointments") }}';

$(document).on('click', '.edit-datetime-btn', function() {
    const button = $(this);
    const appointmentId = button.data('id');
    const appointmentDate = button.data('date');
    const appointmentTime = button.data('time');

    $('#edit-datetime-appointment-id').val(appointmentId);
    $('#edit-appointment-date').val(appointmentDate);
    $('#edit-appointment-time').val(appointmentTime);
    $('#editDatetimeModalLabel').text('Update Appointment #' + appointmentId + ' Date & Time');
    $('#edit-datetime-error').addClass('d-none').text('');

    $('#editDatetimeModal').modal('show');
});

$('#editDatetimeModal').on('hidden.bs.modal', function() {
    $('#edit-datetime-form')[0].reset();
    $('#edit-datetime-appointment-id').val('');
    $('#edit-datetime-error').addClass('d-none').text('');
    $('#edit-datetime-submit').prop('disabled', false).text('Save Changes');
});

$('#edit-datetime-form').on('submit', function(event) {
    event.preventDefault();

    const appointmentId = $('#edit-datetime-appointment-id').val();
    const appointmentDate = $('#edit-appointment-date').val();
    const appointmentTime = $('#edit-appointment-time').val();
    const errorBox = $('#edit-datetime-error');
    const submitButton = $('#edit-datetime-submit');

    errorBox.addClass('d-none').text('');
    submitButton.prop('disabled', true).text('Saving...');

    $.ajax({
        url: baseBookingAppointmentUrl + '/' + appointmentId + '/update-datetime',
        method: 'POST',
        data: {
            _token: '{{ csrf_token() }}',
            appointment_date: appointmentDate,
            appointment_time: appointmentTime
        },
        success: function(response) {
            $('#editDatetimeModal').modal('hide');

            const message = response.message || 'Appointment date and time updated successfully.';
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'success',
                    title: 'Updated',
                    text: message
                }).then(() => {
                    window.location.reload();
                });
            } else {
                alert(message);
                window.location.reload();
            }
        },
        error: function(xhr) {
            submitButton.prop('disabled', false).text('Save Changes');

            let message = 'Failed to update appointment date and time.';
            if (xhr.responseJSON) {
                if (xhr.responseJSON.errors) {
                    message = Object.values(xhr.responseJSON.errors).flat().join(' ');
                } else if (xhr.responseJSON.message) {
                    message = xhr.responseJSON.message;
                }
            }

            errorBox.removeClass('d-none').text(message);
        },
        complete: function() {
            submitButton.prop('disabled', false).text('Save Changes');
        }
    });
});

setInterval(function () {
    loadAppointmentsList(appointmentsListCurrentPage);
}, 5 * 60 * 1000);
</script>

@endsection

