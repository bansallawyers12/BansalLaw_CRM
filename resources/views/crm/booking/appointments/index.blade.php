@extends('layouts.crm_client_detail')
@section('title', 'Bookings')

@section('content')

<style>
/* Bookings list — Powder Blue & Soft Gold (docs/theme.md), page-scoped only */
.booking-appointments-page {
    overflow-x: hidden !important;
    max-width: 100% !important;
}

.booking-appointments-page > .row > .col-12 > .card {
    border: 1px solid var(--border);
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(30, 61, 96, 0.06);
    overflow: hidden;
}

.booking-appointments-page > .row > .col-12 > .card > .card-header {
    background: var(--card-bg) !important;
    background-color: var(--card-bg) !important;
    border-bottom: 1px solid var(--border) !important;
    color: var(--navy) !important;
    padding: 1rem 1.25rem !important;
    align-items: center;
}

.booking-appointments-page > .row > .col-12 > .card > .card-header h4 {
    color: var(--navy) !important;
    font-weight: 700;
    font-size: 1.15rem;
    margin: 0;
    display: flex;
    flex-wrap: wrap;
    align-items: baseline;
    gap: 0.35rem 0.65rem;
}

.booking-appointments-page > .row > .col-12 > .card > .card-header h4 .text-muted,
.booking-appointments-page > .row > .col-12 > .card > .card-header h4 small {
    color: var(--text-muted) !important;
    font-weight: 500;
    font-size: 0.82rem;
}

.booking-appointments-page > .row > .col-12 > .card > .card-body {
    background: var(--card-bg) !important;
    padding: 1.25rem !important;
}

.booking-appointments-page .card-header-action {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.booking-appointments-page .card-header-action .btn {
    border-radius: 8px !important;
    font-weight: 600;
    padding: 0.4rem 0.85rem;
}

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

/* KPI strip */
.booking-appointments-page .ba-kpi-row {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 12px;
    margin-bottom: 1.25rem;
}

@media (max-width: 991px) {
    .booking-appointments-page .ba-kpi-row {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}

@media (max-width: 575px) {
    .booking-appointments-page .ba-kpi-row {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

.booking-appointments-page .ba-kpi {
    display: flex;
    align-items: center;
    gap: 12px;
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: 10px;
    box-shadow: 0 1px 4px rgba(30, 61, 96, 0.06);
    padding: 14px 16px;
    min-height: 76px;
}

.booking-appointments-page .ba-kpi__icon {
    width: 42px;
    height: 42px;
    border-radius: 10px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    font-size: 1rem;
}

.booking-appointments-page .ba-kpi__icon--gold {
    background: rgba(200, 153, 42, 0.15);
    color: var(--accent-gold);
}

.booking-appointments-page .ba-kpi__icon--navy {
    background: rgba(30, 61, 96, 0.1);
    color: var(--navy);
}

.booking-appointments-page .ba-kpi__icon--success {
    background: rgba(30, 122, 82, 0.12);
    color: var(--success);
}

.booking-appointments-page .ba-kpi__icon--blue {
    background: rgba(58, 111, 168, 0.14);
    color: var(--sidebar-active);
}

.booking-appointments-page .ba-kpi__meta {
    min-width: 0;
}

.booking-appointments-page .ba-kpi__label {
    margin: 0;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--text-muted);
    line-height: 1.2;
}

.booking-appointments-page .ba-kpi__value {
    margin: 2px 0 0;
    font-size: 1.55rem;
    font-weight: 700;
    color: var(--navy);
    line-height: 1.15;
}

/* Filter toolbar */
.booking-appointments-page .filter-section {
    background: linear-gradient(180deg, #f5f9ff 0%, var(--page-bg) 100%);
    border: 1px solid var(--border);
    padding: 18px 18px 14px;
    border-radius: 12px;
    margin-bottom: 1.25rem;
}

.booking-appointments-page .filter-section .ba-filter-grid {
    display: grid;
    grid-template-columns: 1.6fr 1fr 1fr 0.9fr 0.9fr auto;
    gap: 12px 14px;
    align-items: end;
}

@media (max-width: 1199px) {
    .booking-appointments-page .filter-section .ba-filter-grid {
        grid-template-columns: 1fr 1fr 1fr;
    }
}

@media (max-width: 767px) {
    .booking-appointments-page .filter-section .ba-filter-grid {
        grid-template-columns: 1fr 1fr;
    }
}

@media (max-width: 575px) {
    .booking-appointments-page .filter-section .ba-filter-grid {
        grid-template-columns: 1fr;
    }
}

.booking-appointments-page .filter-section .ba-field label {
    display: block;
    margin-bottom: 6px;
    color: var(--text-muted);
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.booking-appointments-page .filter-section .ba-search-wrap {
    position: relative;
}

.booking-appointments-page .filter-section .ba-search-wrap > i {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-muted);
    pointer-events: none;
    font-size: 0.9rem;
}

.booking-appointments-page .filter-section .ba-search-wrap .form-control {
    padding-left: 36px;
}

.booking-appointments-page .filter-section .form-control {
    border: 1px solid var(--border);
    border-radius: 8px;
    color: var(--text-dark);
    background: var(--card-bg);
    min-height: 40px;
    font-size: 0.9rem;
}

.booking-appointments-page .filter-section .form-control:focus {
    border-color: var(--sidebar-active);
    box-shadow: 0 0 0 3px rgba(58, 111, 168, 0.15);
}

.booking-appointments-page .ba-filter-actions {
    display: flex;
    gap: 8px;
    flex-wrap: nowrap;
}

.booking-appointments-page .ba-filter-actions .btn {
    border-radius: 8px;
    font-weight: 600;
    min-height: 40px;
    padding: 0.45rem 1rem;
    white-space: nowrap;
}

.booking-appointments-page .ba-filter-actions .btn-primary {
    background: var(--navy);
    border-color: var(--navy);
    color: #fff;
}

.booking-appointments-page .ba-filter-actions .btn-primary:hover {
    background: var(--sidebar-active);
    border-color: var(--sidebar-active);
}

.booking-appointments-page .ba-filter-actions .btn-secondary {
    background: var(--card-bg);
    border: 1px solid var(--border);
    color: var(--navy);
}

.booking-appointments-page .ba-filter-actions .btn-secondary:hover {
    background: var(--sidebar-bg);
    border-color: var(--border);
    color: var(--navy);
}

/* Table panel */
.booking-appointments-page .ba-table-panel {
    border: 1px solid var(--border);
    border-radius: 12px;
    overflow: hidden;
    background: var(--card-bg);
}

.booking-appointments-page .ba-table-panel .table-responsive {
    margin: 0;
}

.booking-appointments-page .table-responsive table.table {
    --bs-table-bg: var(--card-bg);
    --bs-table-color: var(--text-dark);
    --bs-table-striped-bg: rgba(221, 234, 248, 0.28);
    --bs-table-hover-bg: #ebf3ff;
    border-color: var(--border);
    margin-bottom: 0;
}

.booking-appointments-page .table-responsive table.table thead th {
    background: var(--page-bg) !important;
    color: var(--text-muted) !important;
    font-weight: 600;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    border-color: var(--border) !important;
    border-bottom-width: 1px;
    padding: 12px 14px;
    white-space: nowrap;
}

.booking-appointments-page .table-responsive table.table tbody td {
    color: var(--text-dark) !important;
    border-color: var(--border) !important;
    vertical-align: middle;
    padding: 14px;
}

.booking-appointments-page .table-responsive table.table tbody tr.appointment-data-row {
    transition: background-color 0.15s ease;
}

.booking-appointments-page .table-responsive table.table tbody td .text-muted,
.booking-appointments-page .table-responsive table.table tbody td small.text-muted {
    color: var(--text-muted) !important;
}

.booking-appointments-page .ba-row-num {
    color: var(--text-muted);
    font-weight: 600;
    font-variant-numeric: tabular-nums;
}

.booking-appointments-page .ba-client__name {
    display: block;
    font-weight: 700;
    color: var(--navy);
    margin-bottom: 2px;
}

.booking-appointments-page .ba-client__name a {
    color: var(--navy);
    text-decoration: none;
}

.booking-appointments-page .ba-client__name a:hover {
    color: var(--sidebar-active);
}

.booking-appointments-page .ba-client__line {
    display: block;
    font-size: 12.5px;
    color: var(--text-muted);
    line-height: 1.35;
}

.booking-appointments-page .ba-client__line a {
    color: var(--sidebar-active);
    font-weight: 500;
    text-decoration: none;
}

.booking-appointments-page .ba-client__line a:hover {
    color: var(--navy);
}

.booking-appointments-page .ba-client__ref {
    display: inline-block;
    margin-top: 6px;
    padding: 2px 8px;
    border-radius: 6px;
    background: rgba(30, 61, 96, 0.06);
    color: var(--navy);
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 0.02em;
}

.booking-appointments-page .ba-when__date {
    display: block;
    font-weight: 700;
    color: var(--navy);
    margin-bottom: 2px;
}

.booking-appointments-page .ba-when__meta {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 12.5px;
    color: var(--text-muted);
    line-height: 1.4;
}

.booking-appointments-page .ba-when__meta i {
    width: 12px;
    text-align: center;
    opacity: 0.85;
}

.booking-appointments-page .ba-service__main {
    display: block;
    font-weight: 600;
    color: var(--text-dark);
}

.booking-appointments-page .ba-service__sub {
    display: block;
    margin-top: 2px;
    font-size: 12.5px;
    color: var(--text-muted);
}

.booking-appointments-page .ba-consultant {
    font-weight: 600;
    color: var(--text-dark);
}

.booking-appointments-page .ba-desc {
    font-size: 12.5px;
    color: var(--text-muted);
    max-width: 220px;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.booking-appointments-page .table .badge,
.booking-appointments-page .table .ba-badge {
    display: inline-flex;
    align-items: center;
    font-weight: 600;
    font-size: 11px;
    border-radius: 999px;
    padding: 0.35em 0.7em;
    letter-spacing: 0.02em;
    border: 0;
}

.booking-appointments-page .table .badge.bg-warning,
.booking-appointments-page .table .badge.badge-warning {
    background: rgba(200, 153, 42, 0.15) !important;
    color: #7a5800 !important;
}

.booking-appointments-page .table .badge.bg-primary,
.booking-appointments-page .table .badge.badge-primary {
    background: rgba(30, 61, 96, 0.12) !important;
    color: var(--navy) !important;
}

.booking-appointments-page .table .badge.bg-success,
.booking-appointments-page .table .badge.badge-success {
    background: rgba(30, 122, 82, 0.12) !important;
    color: var(--success) !important;
}

.booking-appointments-page .table .badge.bg-info,
.booking-appointments-page .table .badge.badge-info {
    background: rgba(58, 111, 168, 0.12) !important;
    color: var(--sidebar-active) !important;
}

.booking-appointments-page .table .badge.bg-danger,
.booking-appointments-page .table .badge.badge-danger {
    background: rgba(168, 48, 32, 0.12) !important;
    color: var(--danger) !important;
}

.booking-appointments-page .table .badge.bg-secondary,
.booking-appointments-page .table .badge.badge-secondary,
.booking-appointments-page .table .badge.bg-dark,
.booking-appointments-page .table .badge.badge-dark {
    background: rgba(94, 122, 144, 0.12) !important;
    color: var(--text-muted) !important;
}

.booking-appointments-page .ba-pay {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 4px;
}

.booking-appointments-page .ba-pay__amt {
    font-size: 12px;
    font-weight: 600;
    color: var(--text-dark);
}

.booking-appointments-page .ba-actions {
    display: inline-flex;
    flex-wrap: nowrap;
    align-items: center;
    gap: 6px;
}

.booking-appointments-page .ba-actions .ba-action {
    width: 34px;
    height: 34px;
    padding: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    border: 1px solid var(--border);
    background: var(--card-bg);
    color: var(--navy);
    line-height: 1;
    flex-shrink: 0;
    transition: background-color 0.15s ease, border-color 0.15s ease, color 0.15s ease;
}

.booking-appointments-page .ba-actions .ba-action:hover:not(:disabled) {
    background: var(--page-bg);
    border-color: var(--sidebar-active);
    color: var(--sidebar-active);
}

.booking-appointments-page .ba-actions .ba-action--view:hover:not(:disabled) {
    background: rgba(30, 61, 96, 0.08);
    border-color: var(--navy);
    color: var(--navy);
}

.booking-appointments-page .ba-actions .ba-action--edit:hover:not(:disabled) {
    background: rgba(200, 153, 42, 0.12);
    border-color: var(--accent-gold);
    color: #7a5800;
}

.booking-appointments-page .ba-actions .ba-action--quick:hover:not(:disabled) {
    background: rgba(58, 111, 168, 0.12);
    border-color: var(--sidebar-active);
    color: var(--sidebar-active);
}

.booking-appointments-page .ba-actions .ba-action:disabled {
    opacity: 0.45;
    cursor: not-allowed;
}

.booking-appointments-page .table-responsive table.table tbody td a {
    color: var(--sidebar-active);
    font-weight: 600;
}

.booking-appointments-page .table-responsive table.table tbody td a:hover {
    color: var(--navy);
}

.booking-appointments-page .appointments-infinite-footer {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    padding: 16px 0 4px;
}

.booking-appointments-page .appointments-loaded-count {
    font-size: 13px;
    font-weight: 600;
    color: var(--text-muted);
}

.booking-appointments-page .appointments-infinite-loader {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 10px 16px;
    color: var(--text-muted);
    font-size: 13px;
    font-weight: 600;
}

.booking-appointments-page .appointments-infinite-loader[hidden] {
    display: none !important;
}

.booking-appointments-page .appointments-infinite-loader__spinner {
    width: 16px;
    height: 16px;
    border: 2px solid rgba(58, 111, 168, 0.25);
    border-top-color: var(--sidebar-active, #3a6fa8);
    border-radius: 50%;
    animation: appointments-infinite-spin 0.7s linear infinite;
}

@keyframes appointments-infinite-spin {
    to { transform: rotate(360deg); }
}
</style>

<div class="section-body booking-appointments-page">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4>
                        <i class="fa-solid fa-calendar-check me-2"></i>
                        {{ $bookingsPageTitle ?? 'Bookings' }}
                        <small class="text-muted">{{ $bookingsPageSubtitle ?? '(Leads & clients, appointments, and consultants from CRM)' }}</small>
                    </h4>
                    <div class="card-header-action">
                        @if(Auth::user() && in_array(Auth::user()->role, [1, 12]))
                        <a href="{{ route('booking.sync.dashboard') }}" class="btn btn-sm btn-info">
                            <i class="fa-solid fa-rotate"></i> Sync Status
                        </a>
                        <button onclick="manualSync()" class="btn btn-sm btn-primary">
                            <i class="fa-solid fa-rotate"></i> Manual Sync
                        </button>
                        @endif
                    </div>
                </div>
                <div class="card-body">
                    <div class="ba-kpi-row">
                        <div class="ba-kpi">
                            <span class="ba-kpi__icon ba-kpi__icon--gold"><i class="fa-solid fa-clock"></i></span>
                            <div class="ba-kpi__meta">
                                <p class="ba-kpi__label">Payment Pending</p>
                                <p class="ba-kpi__value">{{ $stats['pending'] ?? 0 }}</p>
                            </div>
                        </div>
                        <div class="ba-kpi">
                            <span class="ba-kpi__icon ba-kpi__icon--navy"><i class="fa-solid fa-dollar-sign"></i></span>
                            <div class="ba-kpi__meta">
                                <p class="ba-kpi__label">Paid</p>
                                <p class="ba-kpi__value">{{ $stats['paid'] ?? 0 }}</p>
                            </div>
                        </div>
                        <div class="ba-kpi">
                            <span class="ba-kpi__icon ba-kpi__icon--success"><i class="fa-solid fa-circle-check"></i></span>
                            <div class="ba-kpi__meta">
                                <p class="ba-kpi__label">Confirmed</p>
                                <p class="ba-kpi__value">{{ $stats['confirmed'] ?? 0 }}</p>
                            </div>
                        </div>
                        <div class="ba-kpi">
                            <span class="ba-kpi__icon ba-kpi__icon--blue"><i class="fa-solid fa-calendar-check"></i></span>
                            <div class="ba-kpi__meta">
                                <p class="ba-kpi__label">Today</p>
                                <p class="ba-kpi__value">{{ $stats['today'] ?? 0 }}</p>
                            </div>
                        </div>
                        <div class="ba-kpi">
                            <span class="ba-kpi__icon ba-kpi__icon--navy"><i class="fa-solid fa-list"></i></span>
                            <div class="ba-kpi__meta">
                                <p class="ba-kpi__label">Total</p>
                                <p class="ba-kpi__value">{{ $stats['total'] ?? 0 }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="filter-section">
                        <form method="GET" action="{{ route('booking.appointments.index') }}" id="filter-form" autocomplete="off">
                            <div class="ba-filter-grid">
                                <div class="ba-field">
                                    <label for="filter-search">Search</label>
                                    <div class="ba-search-wrap">
                                        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                                        <input type="text" class="form-control" name="search" id="filter-search"
                                               value=""
                                               placeholder="Name, email, phone, matter ref, or notes">
                                    </div>
                                </div>
                                <div class="ba-field">
                                    <label for="filter-status">Status</label>
                                    <select class="form-control" name="status" id="filter-status">
                                        <option value="" {{ ($bookingListStatusForSelect ?? '') === '' ? 'selected' : '' }}>All Status</option>
                                        <option value="pending" {{ ($bookingListStatusForSelect ?? '') === 'pending' ? 'selected' : '' }}>Payment Pending</option>
                                        <option value="paid" {{ ($bookingListStatusForSelect ?? '') === 'paid' ? 'selected' : '' }}>Paid</option>
                                        <option value="confirmed" {{ ($bookingListStatusForSelect ?? '') === 'confirmed' ? 'selected' : '' }}>Confirmed</option>
                                        <option value="completed" {{ ($bookingListStatusForSelect ?? '') === 'completed' ? 'selected' : '' }}>Completed</option>
                                        <option value="cancelled" {{ ($bookingListStatusForSelect ?? '') === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                                        <option value="no_show" {{ ($bookingListStatusForSelect ?? '') === 'no_show' ? 'selected' : '' }}>No Show</option>
                                    </select>
                                </div>
                                <div class="ba-field">
                                    <label for="filter-consultant">Consultant</label>
                                    <select class="form-control" name="consultant_id" id="filter-consultant">
                                        <option value="">All Consultants</option>
                                        @foreach($consultants as $consultant)
                                            <option value="{{ $consultant->id }}"
                                                @if(!empty($isAjayAppointmentsView) && (int) $consultant->id === (int) ($ajayConsultantId ?? 0)) selected @endif>
                                                {{ $consultant->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="ba-field">
                                    <label for="filter-date-from">From</label>
                                    <input type="date" class="form-control" name="date_from" id="filter-date-from" value="">
                                </div>
                                <div class="ba-field">
                                    <label for="filter-date-to">To</label>
                                    <input type="date" class="form-control" name="date_to" id="filter-date-to" value="">
                                </div>
                                <div class="ba-field ba-filter-actions">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fa-solid fa-filter"></i> Filter
                                    </button>
                                    <a href="{{ !empty($isAjayAppointmentsView) ? route('booking.appointments.index', ['view' => 'ajay']) : route('booking.appointments.index') }}" class="btn btn-secondary">
                                        <i class="fa-solid fa-arrow-rotate-right"></i> Reset
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>

                    <div class="ba-table-panel">
                        <div class="table-responsive">
                            <table class="table table-hover" id="appointments-table">
                                <thead>
                                    <tr>
                                        <th width="52">#</th>
                                        <th>Client</th>
                                        <th>Appointment</th>
                                        <th>Service</th>
                                        <th>Consultant</th>
                                        <th>Description</th>
                                        <th>Status</th>
                                        <th>Payment</th>
                                        <th width="124">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="appointments-table-body">
                                    <tr>
                                        <td colspan="9" class="text-center text-muted py-4">
                                            <i class="fa-solid fa-spinner fa-spin"></i> Loading appointments…
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="appointments-infinite-footer" id="appointments-infinite-footer" hidden>
                        <div class="appointments-loaded-count" id="appointments-loaded-count" aria-live="polite"></div>
                        <div class="appointments-infinite-loader" id="appointments-infinite-loader" hidden aria-live="polite">
                            <span class="appointments-infinite-loader__spinner" aria-hidden="true"></span>
                            <span>Loading more appointments…</span>
                        </div>
                    </div>

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
const appointmentsListCsrfToken = @json(csrf_token());
const appointmentsListPerPage = 20;
const appointmentsListSource = @json($bookingsListSource ?? 'crm');
let appointmentsListCurrentPage = 1;
let appointmentsListLastPage = 1;
let appointmentsListTotal = 0;
let appointmentsListLoading = false;
let appointmentsListLoadingMore = false;

function escapeHtml(text) {
    if (text === null || text === undefined) {
        return '';
    }
    const d = document.createElement('div');
    d.textContent = text;
    return d.innerHTML;
}

/** Start time only (e.g. "11:00 AM"), not a start–end range. */
function appointmentStartTimeLabel(timeslotFull) {
    const raw = String(timeslotFull || '').trim();
    if (!raw) {
        return '';
    }
    return raw.split(/\s*[-–—]\s*/)[0].trim();
}

/** List API: all filters (including status) in POST body. */
function appointmentsListRequestUrl() {
    return appointmentsListApiUrl;
}

function getAppointmentsListPostData(page) {
    return {
        _token: appointmentsListCsrfToken,
        format: 'list',
        list_source: appointmentsListSource,
        page: page,
        per_page: appointmentsListPerPage,
        status: $('#filter-status').val() || '',
        search: ($('#filter-search').val() || '').trim(),
        consultant_id: $('#filter-consultant').val() || '',
        date_from: $('#filter-date-from').val() || '',
        date_to: $('#filter-date-to').val() || ''
    };
}

/** Keep /booking/appointments URL clean — filters are POST-only. Preserve view=ajay for Ajay Appointments. */
function syncAppointmentsCleanUrl() {
    const params = new URLSearchParams(window.location.search);
    const keepView = params.get('view');
    if (keepView === 'ajay') {
        const next = window.location.pathname + '?view=ajay';
        if (window.location.search !== '?view=ajay') {
            window.history.replaceState({}, '', next);
        }
        return;
    }
    if (window.location.search === '') {
        return;
    }
    window.history.replaceState({}, '', window.location.pathname);
}

function buildAppointmentRowHtml(row, rowNumber) {
    const name = escapeHtml(row.client_name || '');
    const email = escapeHtml(row.client_email || '');
    const phone = escapeHtml(row.client_phone || '');
    let clientCell = '<div class="ba-client">';
    if (row.client_id && row.client_detail_url) {
        const href = escapeHtml(row.client_detail_url);
        clientCell += '<span class="ba-client__name"><a href="' + href + '" target="_blank">' + name + '</a></span>';
        clientCell += '<span class="ba-client__line"><a href="' + href + '" target="_blank">' + email + '</a></span>';
        clientCell += '<span class="ba-client__line">' + phone + '</span>';
        if (row.client_reference) {
            clientCell += '<span class="ba-client__ref">' + escapeHtml(row.client_reference) + '</span>';
        }
    } else {
        clientCell += '<span class="ba-client__name">' + name + '</span>';
        clientCell += '<span class="ba-client__line">' + email + '</span>';
        clientCell += '<span class="ba-client__line">' + phone + '</span>';
    }
    clientCell += '</div>';

    const timeLine = escapeHtml(row.appointment_time_label || appointmentStartTimeLabel(row.timeslot_full) || '');
    const locRaw = row.location ? String(row.location) : '';
    const locLabel = locRaw
        ? escapeHtml(locRaw.charAt(0).toUpperCase() + locRaw.slice(1))
        : '';
    let whenCell = '<div class="ba-when">';
    whenCell += '<span class="ba-when__date">' + escapeHtml(row.appointment_date_label || '') + '</span>';
    if (timeLine) {
        whenCell += '<span class="ba-when__meta"><i class="fa-regular fa-clock"></i> ' + timeLine + '</span>';
    }
    if (locLabel) {
        whenCell += '<span class="ba-when__meta"><i class="fa-solid fa-location-dot"></i> ' + locLabel + '</span>';
    }
    whenCell += '</div>';

    const serviceMain = row.service_type ? escapeHtml(row.service_type) : 'N/A';
    const serviceSub = row.enquiry_type ? escapeHtml(row.enquiry_type) : '';
    let serviceCell = '<div class="ba-service"><span class="ba-service__main">' + serviceMain + '</span>';
    if (serviceSub) {
        serviceCell += '<span class="ba-service__sub">' + serviceSub + '</span>';
    }
    serviceCell += '</div>';

    const consultantCell = row.consultant_name
        ? ('<span class="ba-consultant">' + escapeHtml(row.consultant_name) + '</span>')
        : '<span class="text-muted">Not Assigned</span>';
    const descCell = row.enquiry_details_short
        ? ('<div class="ba-desc" title="' + escapeHtml(row.enquiry_details_short) + '">' + escapeHtml(row.enquiry_details_short) + '</div>')
        : '<span class="text-muted">—</span>';

    const badgeClass = escapeHtml(row.status_badge_class || 'secondary');
    const statusLabel = escapeHtml(row.status_label || '');
    const amt = Number(row.final_amount);
    const payBadge = escapeHtml(row.payment_badge_class || (row.is_paid ? 'success' : 'secondary'));
    const payText = escapeHtml(row.payment_status != null && String(row.payment_status).trim() !== ''
        ? String(row.payment_status)
        : (row.is_paid ? 'Paid' : 'Free'));
    let paymentCell = '<div class="ba-pay"><span class="badge bg-' + payBadge + '">' + payText + '</span>';
    if (row.is_paid && amt > 0) {
        paymentCell += '<span class="ba-pay__amt">$' + escapeHtml(amt.toFixed(2)) + '</span>';
    }
    paymentCell += '</div>';

    const showUrl = row.show_url || '';
    const editUrl = row.edit_url || '';
    const viewBtn = showUrl
        ? ('<a href="' + escapeHtml(showUrl) + '" class="ba-action ba-action--view" title="View in CRM"><i class="fa-solid fa-eye"></i></a>')
        : ('<button type="button" class="ba-action" disabled title="Not synced to CRM yet"><i class="fa-solid fa-eye"></i></button>');
    const editBtn = editUrl
        ? ('<a href="' + escapeHtml(editUrl) + '" class="ba-action ba-action--edit" title="Edit in CRM"><i class="fa-solid fa-pen-to-square"></i></a>')
        : ('<button type="button" class="ba-action" disabled title="Not synced to CRM yet"><i class="fa-solid fa-pen-to-square"></i></button>');
    const crmId = row.crm_appointment_id;
    const quickBtn = crmId
        ? ('<button type="button" class="ba-action ba-action--quick quick-action-btn" data-id="' + escapeHtml(String(crmId)) + '" title="Quick Actions"><i class="fa-solid fa-bolt"></i></button>')
        : ('<button type="button" class="ba-action" disabled title="Requires synced CRM record"><i class="fa-solid fa-bolt"></i></button>');

    return (
        '<tr class="appointment-data-row">' +
        '<td><span class="ba-row-num">' + escapeHtml(String(rowNumber)) + '</span></td>' +
        '<td>' + clientCell + '</td>' +
        '<td>' + whenCell + '</td>' +
        '<td>' + serviceCell + '</td>' +
        '<td>' + consultantCell + '</td>' +
        '<td>' + descCell + '</td>' +
        '<td><span class="badge bg-' + badgeClass + '">' + statusLabel + '</span></td>' +
        '<td>' + paymentCell + '</td>' +
        '<td><div class="ba-actions">' + viewBtn + editBtn + quickBtn + '</div></td>' +
        '</tr>'
    );
}

function setAppointmentsInfiniteLoader(visible) {
    const $loader = $('#appointments-infinite-loader');
    if ($loader.length) {
        $loader.prop('hidden', !visible);
    }
}

function updateAppointmentsLoadedCount() {
    const loaded = $('#appointments-table-body tr.appointment-data-row').length;
    const $footer = $('#appointments-infinite-footer');
    const $count = $('#appointments-loaded-count');
    if (!loaded || !appointmentsListTotal) {
        $footer.prop('hidden', true);
        $count.text('');
        return;
    }
    $footer.prop('hidden', false);
    $count.text('Showing ' + loaded + ' of ' + appointmentsListTotal + ' appointments');
}

function hasMoreAppointments() {
    return appointmentsListCurrentPage < appointmentsListLastPage;
}

function applyAppointmentsMeta(meta) {
    if (!meta) {
        appointmentsListCurrentPage = 1;
        appointmentsListLastPage = 1;
        appointmentsListTotal = 0;
        return;
    }
    appointmentsListCurrentPage = parseInt(meta.current_page, 10) || 1;
    appointmentsListLastPage = parseInt(meta.last_page, 10) || 1;
    appointmentsListTotal = parseInt(meta.total, 10) || 0;
}

function loadAppointmentsList(page, append) {
    const replace = !append;
    if (replace) {
        if (appointmentsListLoading) {
            return;
        }
        appointmentsListLoading = true;
        appointmentsListLoadingMore = false;
    } else {
        if (appointmentsListLoading || appointmentsListLoadingMore || !hasMoreAppointments()) {
            return;
        }
        appointmentsListLoadingMore = true;
        setAppointmentsInfiniteLoader(true);
    }

    const $tbody = $('#appointments-table-body');
    if (replace) {
        $tbody.html(
            '<tr><td colspan="9" class="text-center text-muted py-4">' +
            '<i class="fa-solid fa-spinner fa-spin"></i> Loading appointments…</td></tr>'
        );
        $('#appointments-infinite-footer').prop('hidden', true);
        setAppointmentsInfiniteLoader(false);
    }

    $.ajax({
        url: appointmentsListRequestUrl(),
        method: 'POST',
        data: getAppointmentsListPostData(page),
        dataType: 'json'
    }).done(function (res) {
        if (res.message && !res.data) {
            if (replace) {
                $tbody.html(
                    '<tr><td colspan="9" class="text-center text-danger py-4">' + escapeHtml(res.message) + '</td></tr>'
                );
                applyAppointmentsMeta(null);
                updateAppointmentsLoadedCount();
            }
            return;
        }
        const rows = res.data || [];
        applyAppointmentsMeta(res.meta);
        const startNumber = ((page - 1) * appointmentsListPerPage) + 1;

        if (replace) {
            if (!rows.length) {
                $tbody.html(
                    '<tr><td colspan="9" class="text-center text-muted py-4">' +
                    '<i class="fa-solid fa-circle-info"></i> No appointments found.</td></tr>'
                );
            } else {
                $tbody.html(rows.map(function (row, i) {
                    return buildAppointmentRowHtml(row, startNumber + i);
                }).join(''));
            }
        } else if (rows.length) {
            $tbody.append(rows.map(function (row, i) {
                return buildAppointmentRowHtml(row, startNumber + i);
            }).join(''));
        }

        updateAppointmentsLoadedCount();
        syncAppointmentsCleanUrl();
    }).fail(function (xhr) {
        if (!replace) {
            return;
        }
        const msg = (xhr.responseJSON && xhr.responseJSON.message)
            ? xhr.responseJSON.message
            : 'Could not load appointments.';
        $tbody.html(
            '<tr><td colspan="9" class="text-center text-danger py-4">' + escapeHtml(msg) + '</td></tr>'
        );
        applyAppointmentsMeta(null);
        updateAppointmentsLoadedCount();
    }).always(function () {
        if (replace) {
            appointmentsListLoading = false;
        } else {
            appointmentsListLoadingMore = false;
            setAppointmentsInfiniteLoader(false);
        }
        window.requestAnimationFrame(maybeLoadMoreAppointments);
    });
}

function loadMoreAppointments() {
    if (appointmentsListLoading || appointmentsListLoadingMore || !hasMoreAppointments()) {
        return;
    }
    loadAppointmentsList(appointmentsListCurrentPage + 1, true);
}

function maybeLoadMoreAppointments() {
    if (appointmentsListLoading || appointmentsListLoadingMore || !hasMoreAppointments()) {
        return;
    }
    const scrollBottom = window.innerHeight + window.scrollY;
    const triggerLine = document.documentElement.scrollHeight - 280;
    if (scrollBottom >= triggerLine) {
        loadMoreAppointments();
    }
}

$('#filter-form').on('submit', function (e) {
    e.preventDefault();
    loadAppointmentsList(1, false);
});

$(document).on('click', '.quick-action-btn', function () {
    const id = $(this).data('id');
    if (id) {
        quickAction(String(id));
    }
});

$(window).on('scroll.appointmentsInfinite resize.appointmentsInfinite', function () {
    maybeLoadMoreAppointments();
});

$(function () {
    syncAppointmentsCleanUrl();
    loadAppointmentsList(1, false);
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
                crmAlert(
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
                crmAlert('Sync failed: ' + message);
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
                crmAlert(message);
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

