@extends('layouts.crm_client_detail')
@include('components.require-tinymce')
@section('title', 'Leads')

@section('styles')
<link rel="stylesheet" href="{{ asset('css/listing-pagination.css') }}">
<link rel="stylesheet" href="{{ asset('css/listing-container.css') }}">
<link rel="stylesheet" href="{{ asset('css/listing-flatpickr.css') }}">
<link rel="stylesheet" href="{{ asset('css/clients-index.css') }}">
<style>
    .clients-listing .filter_panel {
        background: var(--page-bg, #f0f6ff);
        border-radius: 12px;
        padding: 24px;
        margin-bottom: 24px;
        display: none;
        border: 1px solid var(--border, #c8dcef);
    }

    .clients-listing .filter_panel h4 {
        color: var(--navy, #1e3d60);
        font-size: 18px;
        font-weight: 700;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .clients-listing .active-filters-badge {
        background: rgba(30, 122, 82, 0.15);
        color: var(--success, #1e7a52);
        border: 1px solid rgba(30, 122, 82, 0.3);
        border-radius: 999px;
        padding: 4px 12px;
        font-size: 12px;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .clients-listing .form-group label {
        color: var(--text-muted, #5e7a90) !important;
        font-weight: 600;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        margin-bottom: 8px;
    }

    .clients-listing .per-page-select {
        border: 1px solid var(--border, #c8dcef) !important;
        border-radius: 8px !important;
        background: var(--card-bg, #ffffff) !important;
        color: var(--navy, #1e3d60) !important;
        font-weight: 600 !important;
        padding: 8px 12px !important;
        min-width: 90px;
        width: auto !important;
        flex: 0 0 auto;
    }

    .clients-listing .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 5px 12px;
        border-radius: 999px;
        font-weight: 600;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }

    .clients-listing .status-badge i {
        font-size: 6px;
    }

    .clients-listing .status-badge.new {
        background: rgba(58, 111, 168, 0.15);
        color: var(--sidebar-active, #3a6fa8);
        border: 1px solid rgba(58, 111, 168, 0.35);
    }

    .clients-listing .status-badge.initial_consultation,
    .clients-listing .status-badge.conflict_check {
        background: rgba(30, 61, 96, 0.1);
        color: var(--navy, #1e3d60);
        border: 1px solid rgba(30, 61, 96, 0.2);
    }

    .clients-listing .status-badge.engaged,
    .clients-listing .status-badge.retained,
    .clients-listing .status-badge.converted {
        background: rgba(30, 122, 82, 0.12);
        color: var(--success, #1e7a52);
        border: 1px solid rgba(30, 122, 82, 0.3);
    }

    .clients-listing .status-badge.follow_up {
        background: rgba(200, 153, 42, 0.15);
        color: var(--accent-gold, #c8992a);
        border: 1px solid rgba(200, 153, 42, 0.35);
    }

    .clients-listing .status-badge.not_proceeding,
    .clients-listing .status-badge.declined,
    .clients-listing .status-badge.not_qualified,
    .clients-listing .status-badge.hostile {
        background: rgba(168, 48, 32, 0.1);
        color: var(--danger, #a83020);
        border: 1px solid rgba(168, 48, 32, 0.25);
    }

    .clients-listing .sortable-header {
        cursor: pointer;
        user-select: none;
        white-space: nowrap;
    }

    .clients-listing .sortable-header a {
        color: inherit;
        text-decoration: none;
    }

    .clients-swal-popup {
        border-radius: 14px !important;
        border: 1px solid var(--border, #c8dcef) !important;
        box-shadow: 0 16px 48px rgba(30, 61, 96, 0.18) !important;
        padding: 0.5rem 0 1.5rem !important;
    }

    .clients-swal-popup .swal2-title {
        color: var(--navy, #1e3d60) !important;
        font-size: 1.2rem !important;
        font-weight: 700 !important;
    }

    .clients-swal-popup .swal2-html-container {
        color: var(--text-muted, #5e7a90) !important;
        font-size: 0.9375rem !important;
        line-height: 1.5 !important;
    }

    .clients-swal-popup .swal2-actions {
        gap: 10px !important;
    }

    .clients-swal-popup .swal2-styled.swal2-confirm,
    .clients-swal-popup .swal2-styled.swal2-cancel {
        border-radius: 8px !important;
        font-weight: 600 !important;
        padding: 0.55rem 1.25rem !important;
        box-shadow: none !important;
    }

    .clients-swal-popup .swal2-styled.swal2-confirm:focus,
    .clients-swal-popup .swal2-styled.swal2-cancel:focus {
        box-shadow: 0 0 0 3px rgba(58, 111, 168, 0.25) !important;
    }

    /* Import Lead modal */
    #importLeadModal .modal-content {
        border: 1px solid var(--border, #c8dcef);
        border-radius: 14px;
        overflow: hidden;
        box-shadow: 0 16px 48px rgba(30, 61, 96, 0.18);
    }

    #importLeadModal .modal-header {
        align-items: flex-start;
        padding: 1.25rem 1.5rem;
        border-bottom: 1px solid var(--border, #c8dcef);
        background: linear-gradient(180deg, #fff 0%, var(--page-bg, #f0f6ff) 100%);
    }

    #importLeadModal .import-lead-modal__title-wrap {
        display: flex;
        align-items: center;
        gap: 14px;
    }

    #importLeadModal .import-lead-modal__icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 44px;
        height: 44px;
        border-radius: 12px;
        background: rgba(58, 111, 168, 0.12);
        color: var(--sidebar-active, #3a6fa8);
        font-size: 18px;
        flex-shrink: 0;
    }

    #importLeadModal .modal-title {
        margin: 0;
        color: var(--navy, #1e3d60);
        font-weight: 700;
        font-size: 1.15rem;
        line-height: 1.3;
    }

    #importLeadModal .import-lead-modal__subtitle {
        margin: 2px 0 0;
        color: var(--text-muted, #5e7a90);
        font-size: 0.875rem;
        font-weight: 500;
    }

    #importLeadModal .btn-close {
        margin-top: 4px;
        opacity: 0.65;
    }

    #importLeadModal .btn-close:hover {
        opacity: 1;
    }

    #importLeadModal .modal-body {
        padding: 1.5rem;
        background: #fff;
    }

    #importLeadModal .import-lead-modal__layout {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    #importLeadModal .import-lead-modal__steps-bar {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 0.75rem;
    }

    @media (max-width: 767px) {
        #importLeadModal .import-lead-modal__steps-bar {
            grid-template-columns: 1fr;
        }
    }

    #importLeadModal .import-lead-modal__step-card {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        padding: 0.75rem 0.85rem;
        border: 1px solid var(--border, #c8dcef);
        border-radius: 10px;
        background: var(--page-bg, #f0f6ff);
        min-height: 100%;
    }

    #importLeadModal .import-lead-modal__step-card p {
        margin: 0;
        color: var(--text-muted, #5e7a90);
        font-size: 0.8125rem;
        line-height: 1.45;
    }

    #importLeadModal .import-lead-modal__toolbar {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
    }

    #importLeadModal .import-lead-modal__required-fields {
        padding: 0.85rem 1rem;
        border: 1px solid rgba(58, 111, 168, 0.25);
        border-radius: 10px;
        background: rgba(58, 111, 168, 0.06);
    }

    #importLeadModal .import-lead-modal__required-fields h6 {
        margin: 0 0 0.5rem;
        color: var(--navy, #1e3d60);
        font-size: 0.8125rem;
        font-weight: 700;
    }

    #importLeadModal .import-lead-modal__required-list {
        display: flex;
        flex-wrap: wrap;
        gap: 0.45rem 0.75rem;
        margin: 0;
        padding: 0;
        list-style: none;
    }

    #importLeadModal .import-lead-modal__required-list li {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        color: var(--navy, #1e3d60);
        font-size: 0.8125rem;
        font-weight: 600;
    }

    #importLeadModal .import-lead-modal__required-list li.is-optional {
        color: var(--text-muted, #5e7a90);
        font-weight: 500;
    }

    #importLeadModal .import-lead-modal__required-list .badge-req {
        display: inline-block;
        padding: 0.1rem 0.4rem;
        border-radius: 999px;
        background: rgba(168, 48, 32, 0.12);
        color: var(--danger, #a83020);
        font-size: 0.6875rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }

    #importLeadModal .import-lead-modal__required-list .badge-opt {
        display: inline-block;
        padding: 0.1rem 0.4rem;
        border-radius: 999px;
        background: rgba(30, 61, 96, 0.08);
        color: var(--text-muted, #5e7a90);
        font-size: 0.6875rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }

    #importLeadModal .import-lead-modal__step-num {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 22px;
        height: 22px;
        border-radius: 999px;
        background: var(--navy, #1e3d60);
        color: #fff;
        font-size: 11px;
        font-weight: 700;
        flex-shrink: 0;
    }

    #importLeadModal .import-lead-modal__template-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        width: 100%;
        justify-content: center;
        padding: 0.55rem 0.85rem;
        border-radius: 10px;
        border: 1px solid rgba(58, 111, 168, 0.35);
        background: #fff;
        color: var(--sidebar-active, #3a6fa8);
        font-size: 0.875rem;
        font-weight: 600;
        text-decoration: none;
        transition: background 0.15s ease, border-color 0.15s ease;
    }

    #importLeadModal .import-lead-modal__template-btn:hover {
        background: rgba(58, 111, 168, 0.08);
        border-color: var(--sidebar-active, #3a6fa8);
        color: var(--navy, #1e3d60);
        text-decoration: none;
    }

    #importLeadModal .import-lead-modal__toolbar .import-lead-modal__template-btn {
        width: auto;
        white-space: nowrap;
    }

    #importLeadModal .import-lead-modal__dropzone {
        position: relative;
        border: 2px dashed var(--border, #c8dcef);
        border-radius: 14px;
        background: var(--page-bg, #f0f6ff);
        padding: 2rem 1.25rem;
        text-align: center;
        cursor: pointer;
        transition: border-color 0.15s ease, background 0.15s ease, box-shadow 0.15s ease;
    }

    #importLeadModal .import-lead-modal__dropzone:hover,
    #importLeadModal .import-lead-modal__dropzone.is-dragover {
        border-color: var(--sidebar-active, #3a6fa8);
        background: rgba(58, 111, 168, 0.06);
        box-shadow: 0 0 0 4px rgba(58, 111, 168, 0.1);
    }

    #importLeadModal .import-lead-modal__dropzone.has-file {
        border-style: solid;
        background: #fff;
        padding: 1rem 1.1rem;
        text-align: left;
        cursor: default;
    }

    #importLeadModal .import-lead-modal__dropzone-icon {
        width: 52px;
        height: 52px;
        margin: 0 auto 0.85rem;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(30, 61, 96, 0.08);
        color: var(--navy, #1e3d60);
        font-size: 22px;
    }

    #importLeadModal .import-lead-modal__dropzone-title {
        margin: 0 0 0.35rem;
        color: var(--navy, #1e3d60);
        font-weight: 700;
        font-size: 1rem;
    }

    #importLeadModal .import-lead-modal__dropzone-hint {
        margin: 0;
        color: var(--text-muted, #5e7a90);
        font-size: 0.875rem;
    }

    #importLeadModal .import-lead-modal__dropzone-action {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        margin-top: 0.85rem;
        padding: 0.45rem 0.9rem;
        border-radius: 8px;
        background: var(--navy, #1e3d60);
        color: #fff;
        font-size: 0.8125rem;
        font-weight: 600;
    }

    #importLeadModal .import-lead-modal__file-input {
        position: absolute;
        width: 0.1px;
        height: 0.1px;
        opacity: 0;
        overflow: hidden;
        z-index: -1;
    }

    #importLeadModal .import-lead-modal__file-preview {
        display: none;
        align-items: center;
        gap: 12px;
    }

    #importLeadModal .import-lead-modal__dropzone.has-file .import-lead-modal__file-preview {
        display: flex;
    }

    #importLeadModal .import-lead-modal__dropzone.has-file .import-lead-modal__dropzone-empty {
        display: none;
    }

    #importLeadModal .import-lead-modal__file-icon {
        width: 42px;
        height: 42px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(30, 122, 82, 0.12);
        color: var(--success, #1e7a52);
        font-size: 18px;
        flex-shrink: 0;
    }

    #importLeadModal .import-lead-modal__file-meta {
        flex: 1;
        min-width: 0;
    }

    #importLeadModal .import-lead-modal__file-name {
        display: block;
        color: var(--navy, #1e3d60);
        font-weight: 700;
        font-size: 0.9375rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    #importLeadModal .import-lead-modal__file-size {
        display: block;
        color: var(--text-muted, #5e7a90);
        font-size: 0.8125rem;
        margin-top: 2px;
    }

    #importLeadModal .import-lead-modal__file-remove {
        border: none;
        background: rgba(168, 48, 32, 0.1);
        color: var(--danger, #a83020);
        width: 34px;
        height: 34px;
        border-radius: 8px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        cursor: pointer;
    }

    #importLeadModal .import-lead-modal__file-remove:hover {
        background: rgba(168, 48, 32, 0.16);
    }

    #importLeadModal .import-lead-modal__formats {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 0.85rem;
    }

    #importLeadModal .import-lead-modal__format-chip {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 0.35rem 0.65rem;
        border-radius: 999px;
        background: rgba(30, 61, 96, 0.06);
        color: var(--navy, #1e3d60);
        font-size: 0.75rem;
        font-weight: 600;
    }

    #importLeadModal .import-lead-modal__option {
        margin-top: 0;
        padding: 0;
        border: none;
        background: transparent;
    }

    #importLeadModal .import-lead-modal__toggle {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        width: 100%;
        margin: 0;
        padding: 0.9rem 1rem;
        border: 1px solid var(--border, #c8dcef);
        border-radius: 12px;
        background: #fff;
        cursor: pointer;
        user-select: none;
        transition: border-color 0.15s ease, box-shadow 0.15s ease, background 0.15s ease;
    }

    #importLeadModal .import-lead-modal__toggle:hover {
        border-color: rgba(58, 111, 168, 0.45);
        background: rgba(58, 111, 168, 0.03);
    }

    #importLeadModal .import-lead-modal__toggle-input {
        position: absolute;
        opacity: 0;
        width: 1px;
        height: 1px;
        pointer-events: none;
    }

    #importLeadModal .import-lead-modal__toggle-switch {
        position: relative;
        flex-shrink: 0;
        width: 42px;
        height: 24px;
        margin-top: 2px;
        border-radius: 999px;
        background: #c8d4e3;
        transition: background 0.15s ease;
    }

    #importLeadModal .import-lead-modal__toggle-switch::after {
        content: '';
        position: absolute;
        top: 3px;
        left: 3px;
        width: 18px;
        height: 18px;
        border-radius: 50%;
        background: #fff;
        box-shadow: 0 1px 3px rgba(30, 61, 96, 0.25);
        transition: transform 0.15s ease;
    }

    #importLeadModal .import-lead-modal__toggle-input:checked + .import-lead-modal__toggle-switch {
        background: var(--sidebar-active, #3a6fa8);
    }

    #importLeadModal .import-lead-modal__toggle-input:checked + .import-lead-modal__toggle-switch::after {
        transform: translateX(18px);
    }

    #importLeadModal .import-lead-modal__toggle-input:focus-visible + .import-lead-modal__toggle-switch {
        outline: 2px solid rgba(58, 111, 168, 0.45);
        outline-offset: 2px;
    }

    #importLeadModal .import-lead-modal__toggle-text {
        display: flex;
        flex-direction: column;
        gap: 0.2rem;
        min-width: 0;
    }

    #importLeadModal .import-lead-modal__toggle-title {
        color: var(--navy, #1e3d60);
        font-size: 0.9375rem;
        font-weight: 700;
        line-height: 1.35;
    }

    #importLeadModal .import-lead-modal__option-help {
        display: block;
        color: var(--text-muted, #5e7a90);
        font-size: 0.8125rem;
        font-weight: 500;
        line-height: 1.45;
    }

    #importLeadModal .import-lead-modal__submit[disabled] {
        opacity: 0.55;
        cursor: not-allowed;
    }

    #importLeadModal .modal-footer {
        padding: 1rem 1.5rem;
        border-top: 1px solid var(--border, #c8dcef);
        background: var(--page-bg, #f0f6ff);
        gap: 0.65rem;
    }

    #importLeadModal .modal-footer .btn-primary {
        background: var(--navy, #1e3d60);
        border-color: var(--navy, #1e3d60);
        font-weight: 600;
        padding: 0.55rem 1.1rem;
        border-radius: 10px;
    }

    #importLeadModal .modal-footer .btn-primary:hover {
        filter: brightness(1.06);
    }

    #importLeadModal .modal-footer .btn-secondary {
        background: #fff;
        border: 1px solid var(--border, #c8dcef);
        color: var(--navy, #1e3d60);
        font-weight: 600;
        padding: 0.55rem 1.1rem;
        border-radius: 10px;
    }

    #importLeadModal .alert-danger {
        border-radius: 10px;
        border: 1px solid rgba(168, 48, 32, 0.25);
        background: rgba(168, 48, 32, 0.08);
        color: var(--danger, #a83020);
        font-size: 0.875rem;
    }
</style>
@include('crm.clients.partials.enhanced-date-filter-styles')
@endsection

@section('content')
@php
    $leadFilters = collect([
        'client_id' => request('client_id'),
        'name' => request('name'),
        'email' => request('email'),
        'phone' => request('phone'),
        'lead_stage_filter' => request('lead_stage_filter'),
        'include_inactive' => request('include_inactive'),
        'quick_date_range' => request('quick_date_range'),
        'from_date' => request('from_date'),
        'to_date' => request('to_date'),
        'date_filter_field' => request('date_filter_field') !== 'created_at' ? request('date_filter_field') : null,
    ]);
    $activeLeadFilters = $leadFilters->filter(function ($value) {
        return $value !== null && $value !== '';
    })->count();
@endphp
<div id="clients-listing-spa-root" class="listing-container clients-listing clients-listing--leads" data-spa-root="1">
    <section class="listing-section">
        <div class="listing-section-body" id="clients-listing-spa-inner">
            @include('../Elements/flash-message')

            <div class="card">
                <div class="custom-error-msg">
                </div>
                <div class="card-header">
                    <div class="clients-page-header">
                        <div class="clients-page-header__title">
                            <span class="clients-page-header__icon" aria-hidden="true">
                                <i class="fa-solid fa-user-plus"></i>
                            </span>
                            <div>
                                <h4>All Leads</h4>
                                <p class="clients-page-header__subtitle">
                                    {{ number_format($lists->total()) }} {{ Str::plural('lead', $lists->total()) }} &middot; Manage enquiries and follow-ups
                                </p>
                            </div>
                        </div>

                        <div class="card-header-actions">
                            @if(Auth::user() && in_array(Auth::user()->role, [1, 12]))
                            <a href="{{ route('clients.insights', ['section' => 'leads']) }}" class="btn btn-theme btn-theme-sm" title="Lead Insights">
                                <i class="fa-solid fa-chart-line"></i> Insights
                            </a>
                            @endif
                            <a href="javascript:;" class="btn btn-theme btn-theme-sm" data-bs-toggle="modal" data-bs-target="#importLeadModal" title="Import Lead">
                                <i class="fa-solid fa-upload"></i> Import Lead
                            </a>
                            <a href="{{ route('leads.create') }}" class="btn btn-primary">Create Lead</a>
                            <div class="per-page-wrap">
                                <label for="per_page">Show</label>
                                <select name="per_page" id="per_page" class="form-control per-page-select" aria-label="Results per page">
                                    @foreach([10, 20, 50, 100, 200] as $option)
                                        <option value="{{ $option }}" {{ ($perPage ?? 20) == $option ? 'selected' : '' }}>
                                            {{ $option }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <a href="javascript:;" class="btn btn-theme btn-theme-sm filter_btn{{ $activeLeadFilters > 0 ? ' filter_btn--active' : '' }}" id="filterToggleBtn">
                                <i class="fa-solid fa-filter"></i> Filter
                                @if($activeLeadFilters > 0)
                                    <span class="filter-count-badge">{{ $activeLeadFilters }}</span>
                                @endif
                            </a>
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    <div class="clients-toolbar">
                        <ul class="clients-tabs nav" id="client_tabs" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link" id="clients-tab" href="{{ URL::to('/clients') }}">Clients</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="archived-tab" href="{{ URL::to('/archived') }}">Archived</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link active" id="lead-tab" href="{{ URL::to('/leads') }}">Leads</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="other-parties-tab" href="{{ route('leads.other_parties.index') }}">Other Parties</a>
                            </li>
                        </ul>
                        <span class="clients-select-help"><i class="fa-regular fa-square-check"></i> Select leads with the checkboxes on the left</span>
                    </div>

                    <div class="clients-bulk-bar" id="clientsBulkBar" aria-live="polite">
                        <span class="clients-bulk-bar__count">
                            <i class="fa-solid fa-check-double"></i>
                            <span id="selectedCount">0</span> selected
                        </span>
                        <div class="clients-bulk-bar__actions">
                            <a class="btn btn-primary btn-sm is_checked_client emailmodal" href="javascript:;" style="display:none;">
                                <i class="fa-regular fa-envelope"></i> Send Mail
                            </a>
                            <button type="button" class="btn btn-primary btn-sm is_checked_client mark-mails-read-btn" style="display:none;" title="Mark all unread mail as read for selected leads">
                                <i class="fa-solid fa-envelope-open"></i> Mark Mail Read
                            </button>
                            <a class="btn btn-primary btn-sm is_checked_client" href="javascript:;" style="display:none;">
                                <i class="fa-solid fa-user-pen"></i> Change Assignee
                            </a>
                            <a class="btn btn-primary btn-sm is_checked_client_merge" href="javascript:;" style="display:none;">
                                <i class="fa-solid fa-code-merge"></i> Merge
                            </a>
                        </div>
                    </div>

                    <div class="filter_panel">
                        <div class="d-flex justify-content-between align-items-center flex-wrap">
                            <h4>
                                Search By Details
                                @if($activeLeadFilters > 0)
                                    <span class="active-filters-badge">
                                        <i class="fa-solid fa-filter"></i> {{ $activeLeadFilters }} Active
                                    </span>
                                @endif
                            </h4>
                            @if($activeLeadFilters > 0)
                                <button type="button" class="clear-filter-btn" id="clearLeadFilters">
                                    <i class="fa-solid fa-undo"></i> Clear Filters
                                </button>
                            @endif
                        </div>
                        <form action="{{ URL::to('/leads') }}" method="get" id="leadFilterForm">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="client_id">Client ID</label>
                                        <input type="text" name="client_id" id="client_id" value="{{ request('client_id') }}" class="form-control" placeholder="Client ID">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="name">Name</label>
                                        <input type="text" name="name" id="name" value="{{ request('name') }}" class="form-control" placeholder="Lead name">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="email">Email</label>
                                        <input type="text" name="email" id="email" value="{{ request('email') }}" class="form-control" placeholder="Lead email">
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="phone">Phone</label>
                                        <input type="text" name="phone" id="phone" value="{{ request('phone') }}" class="form-control" placeholder="Lead phone">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="lead_stage_filter">Stage</label>
                                        <select name="lead_stage_filter" id="lead_stage_filter" class="form-control">
                                            <option value="">All stages</option>
                                            @foreach(($leadStageLabels ?? []) as $stageVal => $stageLabel)
                                                <option value="{{ $stageVal }}" {{ request('lead_stage_filter') === $stageVal ? 'selected' : '' }}>
                                                    {{ $stageLabel }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label class="d-block">&nbsp;</label>
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" name="include_inactive" id="include_inactive" value="1" {{ request('include_inactive') ? 'checked' : '' }}>
                                            <label class="form-check-label" for="include_inactive">Include inactive (Not qualified / Hostile)</label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="date-filter-section mt-3">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="date_filter_field"><i class="fa-solid fa-calendar-days"></i> Date Field</label>
                                            <select name="date_filter_field" id="date_filter_field" class="form-control">
                                                <option value="created_at" {{ request('date_filter_field', 'created_at') === 'created_at' ? 'selected' : '' }}>Created Date</option>
                                                <option value="updated_at" {{ request('date_filter_field') === 'updated_at' ? 'selected' : '' }}>Last Updated</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <input type="hidden" name="quick_date_range" id="lead_quick_date_range" value="{{ request('quick_date_range') }}">
                                @php
                                    $quickFilters = [
                                        'today' => 'Today',
                                        'this_week' => 'This Week',
                                        'this_month' => 'This Month',
                                        'last_month' => 'Last Month',
                                        'last_30_days' => 'Last 30 Days',
                                        'last_90_days' => 'Last 90 Days',
                                        'this_year' => 'This Year',
                                        'last_year' => 'Last Year',
                                    ];
                                @endphp
                                <div class="quick-filters">
                                    @foreach($quickFilters as $key => $label)
                                        <span class="quick-filter-chip lead-quick-filter {{ request('quick_date_range') === $key ? 'active' : '' }}" data-filter="{{ $key }}">
                                            <i class="fa-solid fa-calendar"></i> {{ $label }}
                                        </span>
                                    @endforeach
                                </div>
                                <div class="divider-text">Or Custom Range</div>
                                <div class="date-range-wrapper">
                                    <div class="form-group">
                                        <label for="lead_from_date">From Date</label>
                                        <input type="date" name="from_date" id="lead_from_date" value="{{ request('from_date') }}" class="form-control">
                                    </div>
                                    <span class="date-range-arrow">&rarr;</span>
                                    <div class="form-group">
                                        <label for="lead_to_date">To Date</label>
                                        <input type="date" name="to_date" id="lead_to_date" value="{{ request('to_date') }}" class="form-control">
                                    </div>
                                </div>
                            </div>

                            <div class="row mt-4">
                                <div class="col-md-12 text-center">
                                    <div class="filter-buttons-container">
                                        <button type="submit" class="btn btn-primary btn-theme-lg me-3">Apply Filters</button>
                                        <a class="btn btn-info" href="{{ URL::to('/leads') }}">Reset</a>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>

                    @if($lists->total() > 0)
                    <div class="clients-results-bar">
                        Showing {{ number_format($lists->firstItem()) }}&ndash;{{ number_format($lists->lastItem()) }} of {{ number_format($lists->total()) }} leads
                        @if($activeLeadFilters > 0)
                            <span class="clients-results-bar__filtered"><i class="fa-solid fa-filter"></i> Filtered</span>
                        @endif
                    </div>
                    @endif

                    <div class="table-responsive">
                        <table class="table table-leads">
                            <thead>
                                <tr>
                                    <th class="client-select-cell">
                                        <label class="client-row-checkbox client-row-checkbox--header" for="checkbox-all" title="Select all on this page">
                                            <input type="checkbox" data-checkboxes="mygroup" data-checkbox-role="dad" class="cb-select-all" id="checkbox-all">
                                            <span class="client-row-checkbox__box" aria-hidden="true"><i class="fa-solid fa-check"></i></span>
                                        </label>
                                    </th>
                                    <th class="sortable-header">@sortablelink('first_name', 'Name')</th>
                                    <th>Info</th>
                                    <th class="sortable-header">@sortablelink('created_at', 'Contact Date')</th>
                                    <th class="sortable-header">@sortablelink('lead_status', 'Stage')</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="tdata">
                                @if($lists->count() > 0)
                                <?php $i = 0; ?>
                                @foreach ($lists as $list)
                                    <?php
                                    $encodedId = base64_encode(convert_uuencode(@$list->id));
                                    $clientDetailUrl = URL::to('/clients/detail/' . $encodedId);
                                    $firstName = trim((string) @$list->first_name);
                                    $lastName = trim((string) @$list->last_name);
                                    $displayName = trim($firstName . ' ' . $lastName);
                                    if ($displayName === '') {
                                        $displayName = config('constants.empty');
                                    }
                                    $initials = strtoupper(substr($firstName !== '' ? $firstName : $lastName, 0, 1) . substr($lastName !== '' ? $lastName : $firstName, 0, 1));
                                    if ($initials === '') {
                                        $initials = '?';
                                    }
                                    $unreadMailCount = (int) ($unreadEmailCounts[$list->id] ?? 0);
                                    $clientEmailsUrl = rtrim($clientDetailUrl, '/') . '/emails';
                                    $stageKey = $list->lead_status ?: 'new';
                                    $stageLabel = ($leadStageLabels[$stageKey] ?? ucfirst(str_replace('_', ' ', $stageKey)));
                                    $stageSlug = \Illuminate\Support\Str::slug($stageKey, '_');
                                    $contactAt = \Carbon\Carbon::parse($list->created_at);
                                    ?>
                                    <tr id="id_{{ @$list->id }}" class="client-data-row">
                                        <td class="client-select-cell">
                                            <label class="client-row-checkbox" for="checkbox-{{ $i }}" title="Select lead">
                                                <input data-id="{{ @$list->id }}" data-email="{{ @$list->email }}" data-name="{{ @$list->first_name }} {{ @$list->last_name }}" data-clientid="{{ @$list->client_id }}" data-unread="{{ $unreadMailCount }}" type="checkbox" data-checkboxes="mygroup" class="cb-element your-checkbox" id="checkbox-{{ $i }}">
                                                <span class="client-row-checkbox__box" aria-hidden="true"><i class="fa-solid fa-check"></i></span>
                                            </label>
                                        </td>
                                        <td>
                                            <div class="client-name-cell">
                                                <span class="client-avatar" aria-hidden="true">{{ $initials }}</span>
                                                <div class="client-name-meta">
                                                    <a href="{{ $clientDetailUrl }}" class="client-name-link" title="View lead profile">{{ Str::limit($displayName, 50, '...') }}</a>
                                                    @if($unreadMailCount > 0)
                                                        <a href="{{ $clientEmailsUrl }}" class="client-unread-mail-badge" title="{{ $unreadMailCount }} unread {{ Str::plural('email', $unreadMailCount) }} — open Emails tab">
                                                            <i class="fa-solid fa-envelope" aria-hidden="true"></i>{{ $unreadMailCount }}
                                                        </a>
                                                    @endif
                                                </div>
                                            </div>
                                        </td>
                                        <td class="lead-info-col">
                                            <div class="lead-info-cell">
                                                @if(!empty($list->phone))
                                                    <span class="lead-info-cell__item" title="{{ $list->phone }}">
                                                        <i class="fa-solid fa-mobile" aria-hidden="true"></i>
                                                        <span class="lead-info-cell__text">{{ $list->phone }}</span>
                                                    </span>
                                                @endif
                                                @if(!empty($list->email))
                                                    <span class="lead-info-cell__item" title="{{ $list->email }}">
                                                        <i class="fa-solid fa-envelope" aria-hidden="true"></i>
                                                        <span class="lead-info-cell__text">{{ $list->email }}</span>
                                                    </span>
                                                @endif
                                                @if(empty($list->phone) && empty($list->email))
                                                    {{ config('constants.empty') }}
                                                @endif
                                            </div>
                                        </td>
                                        <td class="lead-contact-date-cell">
                                            <span class="lead-contact-date__date">{{ $contactAt->format('d/m/Y') }}</span>
                                            <span class="lead-contact-date__time">{{ $contactAt->format('g:i a') }}</span>
                                        </td>
                                        <td>
                                            <span class="status-badge {{ $stageSlug }}">
                                                <i class="fa-solid fa-circle"></i> {{ $stageLabel }}
                                            </span>
                                            @if($list->lead_status === 'follow_up' && $list->followup_date)
                                                <br><small class="text-muted">Follow-up: {{ $list->followup_date->format('d/m/Y') }}</small>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            <div class="action-buttons">
                                                <a class="btn-action-icon btn-action-email clientemail"
                                                   data-id="{{ @$list->id }}"
                                                   data-email="{{ @$list->email }}"
                                                   data-name="{{ @$list->first_name }} {{ @$list->last_name }}"
                                                   href="javascript:;"
                                                   title="Send email">
                                                    <i class="fa-regular fa-envelope"></i>
                                                </a>
                                                <a class="btn-action-icon btn-action-edit"
                                                   href="{{ route('clients.edit', $encodedId) }}"
                                                   title="Edit lead">
                                                    <i class="fa-regular fa-pen-to-square"></i>
                                                </a>
                                                <form action="{{ route('leads.archive', $encodedId) }}" method="POST" class="archive-lead-form" style="display: inline-block;">
                                                    @csrf
                                                    <button type="button"
                                                            class="btn-action-icon btn-action-archive"
                                                            title="Archive lead"
                                                            onclick="confirmArchiveLead(event, '{{ @$list->first_name }} {{ @$list->last_name }}')">
                                                        <i class="fa-solid fa-box-archive"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php $i++; ?>
                                @endforeach
                                @else
                                    <tr>
                                        <td colspan="6">
                                            <div class="clients-empty-state">
                                                <div class="clients-empty-state__icon" aria-hidden="true">
                                                    <i class="fa-solid fa-user-slash"></i>
                                                </div>
                                                <h5>No leads found</h5>
                                                <p>
                                                    @if($activeLeadFilters > 0)
                                                        No records match your current filters. Try adjusting or clearing them.
                                                    @else
                                                        There are no lead records to display yet.
                                                    @endif
                                                </p>
                                                @if($activeLeadFilters > 0)
                                                    <a href="{{ URL::to('/leads') }}" class="btn btn-theme btn-theme-sm">
                                                        <i class="fa-solid fa-undo"></i> Clear Filters
                                                    </a>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>

                    <div class="card-footer">
                        {!! $lists->appends(\Request::except('page'))->render() !!}
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<div class="modal fade" id="assignlead_modal">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                  <h4 class="modal-title">Assign Lead</h4>
                  <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                  </button>
            </div>
            <form action="{{ url('leads/assign') }}" method="POST" name="add-assign" autocomplete="off" enctype="multipart/form-data" id="addnoteform">
    @csrf
    <div class="modal-body">
        <div class="form-group row">
            <div class="col-sm-12">
                <input id="mlead_id" name="mlead_id" type="hidden" value="">
                <select name="assignto" class="form-control crm-ts-plain " style="width: 100%;">
                    <option value="">Select</option>
                    @foreach(\App\Models\Staff::where('status', 1)->orderBy('first_name')->get() as $ulist)
                    <option value="{{@$ulist->id}}">{{@$ulist->first_name}} {{@$ulist->last_name}}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>
    <div class="modal-footer">
        <button type="submit" class="btn btn-primary" onClick='customValidate("add-assign")'>
            <i class="fa-solid fa-floppy-disk"></i> Assign Lead
        </button>
    </div>
</form>
        </div>
    </div>
</div>

<!-- Import Lead Modal -->
<div id="importLeadModal" data-backdrop="static" data-keyboard="false" class="modal fade custom_modal" tabindex="-1" role="dialog" aria-labelledby="importLeadModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div class="import-lead-modal__title-wrap">
                    <span class="import-lead-modal__icon" aria-hidden="true">
                        <i class="fa-solid fa-file-import"></i>
                    </span>
                    <div>
                        <h5 class="modal-title" id="importLeadModalLabel">Import Leads</h5>
                        <p class="import-lead-modal__subtitle">Upload a file to create multiple leads at once</p>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form method="post" name="importLeadForm" action="{{ URL::to('/clients/import') }}" autocomplete="off" enctype="multipart/form-data" id="importLeadForm">
                @csrf
                <div class="modal-body">
                    @if ($errors->has('import_file'))
                        <div class="alert alert-danger mb-3">
                            <i class="fa-solid fa-circle-exclamation"></i>
                            {{ $errors->first('import_file') }}
                        </div>
                    @endif

                    <div class="import-lead-modal__layout">
                        <div class="import-lead-modal__steps-bar" aria-label="Import steps">
                            <div class="import-lead-modal__step-card">
                                <span class="import-lead-modal__step-num">1</span>
                                <p>Download the template or prepare your spreadsheet with one lead per row.</p>
                            </div>
                            <div class="import-lead-modal__step-card">
                                <span class="import-lead-modal__step-num">2</span>
                                <p>Upload a JSON, CSV, or Excel file using the area below.</p>
                            </div>
                            <div class="import-lead-modal__step-card">
                                <span class="import-lead-modal__step-num">3</span>
                                <p>Review the import summary and check the leads list when finished.</p>
                            </div>
                        </div>

                        <label class="import-lead-modal__dropzone" id="import_lead_dropzone" for="import_lead_file">
                            <input type="file"
                                class="import-lead-modal__file-input"
                                id="import_lead_file"
                                name="import_file"
                                accept=".json,.csv,.xlsx,.xls"
                                required>

                            <div class="import-lead-modal__dropzone-empty">
                                <div class="import-lead-modal__dropzone-icon">
                                    <i class="fa-solid fa-cloud-arrow-up"></i>
                                </div>
                                <p class="import-lead-modal__dropzone-title">Drag & drop your file here</p>
                                <p class="import-lead-modal__dropzone-hint">or click to browse from your computer</p>
                                <span class="import-lead-modal__dropzone-action">
                                    <i class="fa-solid fa-folder-open"></i> Choose file
                                </span>
                            </div>

                            <div class="import-lead-modal__file-preview">
                                <span class="import-lead-modal__file-icon">
                                    <i class="fa-solid fa-file-lines"></i>
                                </span>
                                <span class="import-lead-modal__file-meta">
                                    <span class="import-lead-modal__file-name" id="import_lead_file_name">No file selected</span>
                                    <span class="import-lead-modal__file-size" id="import_lead_file_size"></span>
                                </span>
                                <button type="button" class="import-lead-modal__file-remove" id="import_lead_file_remove" aria-label="Remove selected file">
                                    <i class="fa-solid fa-xmark"></i>
                                </button>
                            </div>
                        </label>

                        <div class="import-lead-modal__toolbar">
                            <div class="import-lead-modal__formats" aria-label="Supported file formats">
                                <span class="import-lead-modal__format-chip"><i class="fa-solid fa-file-code"></i> JSON</span>
                                <span class="import-lead-modal__format-chip"><i class="fa-solid fa-file-csv"></i> CSV</span>
                                <span class="import-lead-modal__format-chip"><i class="fa-solid fa-file-excel"></i> XLSX</span>
                                <span class="import-lead-modal__format-chip"><i class="fa-solid fa-file-excel"></i> XLS</span>
                            </div>
                            <a href="{{ route('leads.import_template') }}" class="import-lead-modal__template-btn">
                                <i class="fa-solid fa-download"></i>
                                Download CSV Template
                            </a>
                        </div>

                        <div class="import-lead-modal__required-fields">
                            <h6><i class="fa-solid fa-table-columns"></i> Expected columns</h6>
                            <ul class="import-lead-modal__required-list">
                                <li><span class="badge-req">Required</span> First Name</li>
                                <li><span class="badge-req">One required</span> Email or Phone</li>
                                <li class="is-optional"><span class="badge-opt">Optional</span> Last Name</li>
                                <li class="is-optional"><span class="badge-opt">Optional</span> Stage</li>
                                <li class="is-optional"><span class="badge-opt">Optional</span> Notes</li>
                            </ul>
                        </div>

                        <div class="import-lead-modal__option">
                            <label class="import-lead-modal__toggle" for="skip_duplicates_lead">
                                <input class="import-lead-modal__toggle-input"
                                    type="checkbox"
                                    id="skip_duplicates_lead"
                                    name="skip_duplicates"
                                    value="1"
                                    checked>
                                <span class="import-lead-modal__toggle-switch" aria-hidden="true"></span>
                                <span class="import-lead-modal__toggle-text">
                                    <span class="import-lead-modal__toggle-title">Skip duplicate leads</span>
                                    <span class="import-lead-modal__option-help">Ignore rows that match an existing lead by email or phone, or that repeat within the same file.</span>
                                </span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary import-lead-modal__submit" id="import_lead_submit" disabled>
                        <i class="fa-solid fa-upload"></i> Import Leads
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="emailmodal"  data-backdrop="static" data-keyboard="false" class="modal fade custom_modal" tabindex="-1" role="dialog" aria-labelledby="clientModalLabel" aria-hidden="true" data-staff-signature="{{ auth()->user()->email_signature ?? '' }}" data-signature-prefill="allow">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="clientModalLabel">Compose Email</h5>
				<button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body">
				<form method="post" name="sendmail" action="{{URL::to('/sendmail')}}" autocomplete="off" enctype="multipart/form-data">
				@csrf
					<div class="row">
						<div class="col-12 col-md-6 col-lg-6">
							<div class="form-group">
								<label for="email_from">From <span class="span_req">*</span></label>
								@include('partials.email-from-compose')
								@if ($errors->has('email_from'))
									<span class="custom-error" role="alert">
										<strong>{{ @$errors->first('email_from') }}</strong>
									</span>
								@endif
							</div>
						</div>
						<div class="col-12 col-md-6 col-lg-6">
							<div class="form-group">
								<label for="email_to">To <span class="span_req">*</span></label>
								<select multiple data-valid="required" class="js-data-example-ajax" name="email_to[]"></select>

								@if ($errors->has('email_to'))
									<span class="custom-error" role="alert">
										<strong>{{ @$errors->first('email_to') }}</strong>
									</span>
								@endif
							</div>
						</div>
						<div class="col-12 col-md-6 col-lg-6">
							<div class="form-group">
								<label for="email_cc">CC </label>
								<select multiple data-valid="" class="js-data-example-ajaxcc" name="email_cc[]"></select>

								@if ($errors->has('email_cc'))
									<span class="custom-error" role="alert">
										<strong>{{ @$errors->first('email_cc') }}</strong>
									</span>
								@endif
							</div>
						</div>

						<div class="col-12 col-md-6 col-lg-6">
							<div class="form-group">
								<label for="template">Templates </label>
								<select data-valid="" class="form-control crm-ts-plain selecttemplate" name="template">
									<option value="">Select</option>
									@foreach(\App\Models\EmailTemplate::crm()->orderBy('id', 'desc')->get() as $list)
										<option value="{{$list->id}}">{{$list->name}}</option>
									@endforeach
								</select>

							</div>
						</div>
						<div class="col-12 col-md-12 col-lg-12">
							<div class="form-group">
								<label for="subject">Subject <span class="span_req">*</span></label>
								<input type="text" name="subject" value="{{ old('subject', '') }}" class="form-control selectedsubject" data-valid="required" autocomplete="off" placeholder="Enter Subject">
								@if ($errors->has('subject'))
									<span class="custom-error" role="alert">
										<strong>{{ @$errors->first('subject') }}</strong>
									</span>
								@endif
							</div>
						</div>
						<div class="col-12 col-md-12 col-lg-12">
							<div class="form-group">
								<label for="message">Message <span class="span_req">*</span></label>
								<textarea class="tinymce-editor selectedmessage" name="message"></textarea>
								@if ($errors->has('message'))
									<span class="custom-error" role="alert">
										<strong>{{ @$errors->first('message') }}</strong>
									</span>
								@endif
							</div>
						</div>
						<div class="col-12 col-md-12 col-lg-12">
							<button onclick="customValidate('sendmail')" type="button" class="btn btn-primary">Send</button>
							<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
						</div>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>

@endsection
@push('scripts')
<script src="{{ asset('js/crm/clients/clients-listing-spa.js') }}"></script>
<script>
window.ClientsListingSpaConfig = {
    csrfToken: document.querySelector('meta[name="csrf-token"]') ? document.querySelector('meta[name="csrf-token"]').getAttribute('content') : '',
    routes: {
        clientsIndex: @json(url('/clients')),
        leadsIndex: @json(url('/leads')),
        markBulkEmailsRead: @json(route('clients.markBulkClientsEmailsRead')),
        mergeRecords: @json(url('/merge_records')),
        getRecipients: @json(url('/clients/get-recipients')),
        getTemplates: @json(url('/get-templates'))
    }
};

jQuery(function ($) {
    if (window.ClientsListingSpa) {
        ClientsListingSpa.init();
    }

    (function initImportLeadModal() {
        var $modal = $('#importLeadModal');
        var $input = $('#import_lead_file');
        var $dropzone = $('#import_lead_dropzone');
        var $fileName = $('#import_lead_file_name');
        var $fileSize = $('#import_lead_file_size');
        var $removeBtn = $('#import_lead_file_remove');
        var $submitBtn = $('#import_lead_submit');

        function formatFileSize(bytes) {
            if (!bytes && bytes !== 0) return '';
            if (bytes < 1024) return bytes + ' B';
            if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
            return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
        }

        function syncSelectedFile(file) {
            if (!file) {
                $dropzone.removeClass('has-file');
                $fileName.text('No file selected');
                $fileSize.text('');
                $submitBtn.prop('disabled', true);
                return;
            }

            $dropzone.addClass('has-file');
            $fileName.text(file.name);
            $fileSize.text(formatFileSize(file.size));
            $submitBtn.prop('disabled', false);
        }

        $input.on('change', function () {
            syncSelectedFile(this.files && this.files[0] ? this.files[0] : null);
        });

        $removeBtn.on('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            $input.val('');
            syncSelectedFile(null);
        });

        $dropzone.on('dragover dragenter', function (e) {
            e.preventDefault();
            e.stopPropagation();
            if (!$dropzone.hasClass('has-file')) {
                $dropzone.addClass('is-dragover');
            }
        });

        $dropzone.on('dragleave dragend drop', function (e) {
            e.preventDefault();
            e.stopPropagation();
            $dropzone.removeClass('is-dragover');
        });

        $dropzone.on('drop', function (e) {
            if ($dropzone.hasClass('has-file')) return;
            var files = e.originalEvent.dataTransfer && e.originalEvent.dataTransfer.files;
            if (!files || !files.length) return;

            if (typeof DataTransfer !== 'undefined') {
                var dt = new DataTransfer();
                dt.items.add(files[0]);
                $input[0].files = dt.files;
            } else {
                return;
            }

            $input.trigger('change');
        });

        $modal.on('hidden.bs.modal', function () {
            $input.val('');
            syncSelectedFile(null);
            $dropzone.removeClass('is-dragover');
            $submitBtn.prop('disabled', true).html('<i class="fa-solid fa-upload"></i> Import Leads');
        });

        $('#importLeadForm').on('submit', function () {
            $submitBtn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> Importing...');
        });

        if ($input[0] && $input[0].files && $input[0].files[0]) {
            syncSelectedFile($input[0].files[0]);
        }
    })();

    @if ($errors->has('import_file'))
        $('#importLeadModal').modal('show');
    @endif

    $(document).on('shown.bs.modal', '#assignlead_modal', function () {
        var modalEl = this;
        var sel = modalEl.querySelector('select[name="assignto"]');
        if (!sel) return;
        if (typeof destroyTS === 'function') destroyTS(sel);
        if (typeof initTS === 'function') {
            initTS(sel, { create: false, allowEmptyOption: true, dropdownParent: modalEl });
        }
    });

    $(document).on('hidden.bs.modal', '#assignlead_modal', function () {
        var sel = this.querySelector('select[name="assignto"]');
        if (sel && typeof destroyTS === 'function') destroyTS(sel);
    });
});
</script>
@endpush


