@extends('layouts.crm_client_detail')
@include('components.require-tinymce')
@section('title', 'Leads')

@section('styles')
<link rel="stylesheet" href="{{ asset('css/listing-pagination.css') }}">
<link rel="stylesheet" href="{{ asset('css/listing-container.css') }}">
<link rel="stylesheet" href="{{ asset('css/listing-flatpickr.css') }}">
<style>
    /* Leads index â€” docs/theme.md (CSS variables from crm-theme.css :root) */

    .btn-edit-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 36px;
        height: 36px;
        background: var(--navy, #1e3d60);
        border: none;
        border-radius: 8px;
        color: #fff !important;
        text-decoration: none;
        box-shadow: 0 2px 4px rgba(30, 61, 96, 0.2);
        margin-right: 8px;
    }

    .btn-edit-icon:hover {
        filter: brightness(1.08);
        box-shadow: 0 4px 8px rgba(30, 61, 96, 0.25);
        color: #fff !important;
        text-decoration: none;
    }

    .btn-edit-icon:focus {
        outline: none;
        box-shadow: 0 0 0 3px rgba(58, 111, 168, 0.35);
        color: #fff !important;
    }

    .btn-edit-icon i {
        font-size: 14px;
        color: #fff;
    }

    .btn-archive-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 36px;
        height: 36px;
        background: var(--accent-gold, #c8992a);
        border: none;
        border-radius: 8px;
        color: #fff !important;
        text-decoration: none;
        box-shadow: 0 2px 4px rgba(200, 153, 42, 0.25);
        cursor: pointer;
    }

    .btn-archive-icon:hover {
        filter: brightness(1.06);
        box-shadow: 0 4px 8px rgba(200, 153, 42, 0.3);
        color: #fff !important;
        text-decoration: none;
    }

    .btn-archive-icon:focus {
        outline: none;
        box-shadow: 0 0 0 3px rgba(200, 153, 42, 0.35);
        color: #fff !important;
    }

    .btn-archive-icon i {
        font-size: 14px;
        color: #fff;
    }

    .action-buttons {
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }

    .listing-container .card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 16px;
    }

    .listing-container .card-header-actions {
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
    }

    .listing-container .per-page-select {
        border: 1px solid var(--border, #c8dcef) !important;
        border-radius: 8px !important;
        background: var(--card-bg, #ffffff) !important;
        color: var(--navy, #1e3d60) !important;
        font-weight: 600 !important;
        padding: 8px 16px !important;
        min-width: 110px;
        width: auto !important;
        flex: 0 0 auto;
        box-shadow: 0 1px 4px rgba(30, 61, 96, 0.06);
    }

    .listing-container .per-page-select:focus {
        outline: none;
        border-color: var(--sidebar-active, #3a6fa8) !important;
        box-shadow: 0 0 0 3px rgba(58, 111, 168, 0.2);
    }

    .listing-container .per-page-select option {
        background: var(--card-bg, #ffffff);
        color: var(--navy, #1e3d60);
    }

    .listing-container .filter_panel {
        background: var(--page-bg, #f0f6ff);
        border-radius: 10px;
        padding: 24px;
        margin-bottom: 24px;
        display: none;
        border: 1px solid var(--border, #c8dcef);
        box-shadow: 0 1px 4px rgba(30, 61, 96, 0.06);
    }

    .listing-container .filter_panel h4 {
        color: var(--navy, #1e3d60) !important;
        font-size: 18px;
        font-weight: 700;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 14px;
        border-radius: 20px;
        font-weight: 600;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .status-badge.open {
        background: rgba(58, 111, 168, 0.15);
        color: var(--sidebar-active, #3a6fa8);
        border: 1px solid rgba(58, 111, 168, 0.35);
    }

    .status-badge.closed {
        background: rgba(168, 48, 32, 0.12);
        color: var(--danger, #a83020);
        border: 1px solid rgba(168, 48, 32, 0.3);
    }

    .status-badge.converted {
        background: rgba(30, 122, 82, 0.12);
        color: var(--success, #1e7a52);
        border: 1px solid rgba(30, 122, 82, 0.35);
    }

    .sortable-header a {
        color: inherit;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }

    .sortable-header i {
        color: var(--text-muted, #5e7a90);
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
<div class="listing-container">
    <section class="listing-section" style="padding-top: 40px;">
        <div class="listing-section-body">
            @include('../Elements/flash-message')

            <div class="card">
                <div class="custom-error-msg">
                </div>
                <div class="card-header">
                    <h4>All Leads</h4>

                    <div class="card-header-actions">
                        @if(Auth::user() && in_array(Auth::user()->role, [1, 12]))
                        <a href="{{ route('clients.insights', ['section' => 'leads']) }}" class="btn btn-theme btn-theme-sm" title="Lead Insights">
                            <i class="fa-solid fa-chart-line"></i> Insights
                        </a>
                        @endif
                        <a href="javascript:;" class="btn btn-theme btn-theme-sm" data-bs-toggle="modal" data-bs-target="#importLeadModal" title="Import Lead">
                            <i class="fa-solid fa-upload"></i> Import Lead
                        </a>
                        <a href="{{route('leads.create')}}" class="btn btn-primary">Create Lead</a>
                        <select name="per_page" id="per_page" class="form-control per-page-select">
                            @foreach([10, 20, 50, 100, 200] as $option)
                                <option value="{{ $option }}" {{ ($perPage ?? 20) == $option ? 'selected' : '' }}>
                                    {{ $option }} / page
                                </option>
                            @endforeach
                        </select>
                        <a href="javascript:;" class="btn btn-theme btn-theme-sm filter_btn">
                            <i class="fa-solid fa-filter"></i> Filter
                        </a>
                    </div>
                </div>

                <div class="card-body">
                    <ul class="nav nav-pills" id="client_tabs" role="tablist">
                        <li class="nav-item is_checked_client" style="display:none;">
                            <a class="btn btn-primary emailmodal" href="javascript:;">Send Mail</a>
                        </li>
                        <li class="nav-item is_checked_client" style="display:none;">
                            <a class="btn btn-primary" href="javascript:;">Change Assignee</a>
                        </li>

                        <li class="nav-item is_checked_client_merge" style="display:none;">
                            <a class="btn btn-primary" href="javascript:;">Merge</a>
                        </li>

                        <li class="nav-item is_checked_clientn">
                            <a class="nav-link " id="clients-tab"  href="{{URL::to('/clients')}}" >Clients</a>
                        </li>
                        <li class="nav-item is_checked_clientn">
                            <a class="nav-link" id="archived-tab"  href="{{URL::to('/archived')}}" >Archived</a>
                        </li>
                        <li class="nav-item is_checked_clientn">
                            <a class="nav-link active" id="lead-tab"  href="{{URL::to('/leads')}}" >Leads</a>
                        </li>
                        <li class="nav-item is_checked_clientn">
                            <a class="nav-link" id="other-parties-tab" href="{{ route('leads.other_parties.index') }}">Other Parties</a>
                        </li>
                    </ul>

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
                        <form action="{{URL::to('/leads')}}" method="get" id="leadFilterForm">
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

                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="date_filter_field">Date Field</label>
                                        <select name="date_filter_field" id="date_filter_field" class="form-control">
                                            <option value="created_at" {{ request('date_filter_field', 'created_at') === 'created_at' ? 'selected' : '' }}>Created Date</option>
                                            <option value="updated_at" {{ request('date_filter_field') === 'updated_at' ? 'selected' : '' }}>Last Updated</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="date-filter-section mt-2">
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
                                    <span class="date-range-arrow">â†’</span>
                                    <div class="form-group">
                                        <label for="lead_to_date">To Date</label>
                                        <input type="date" name="to_date" id="lead_to_date" value="{{ request('to_date') }}" class="form-control">
                                    </div>
                                </div>
                            </div>

                            <div class="row mt-3">
                                <div class="col-md-12 text-center">
                                    <div class="filter-buttons-container">
                                        <button type="submit" class="btn btn-primary btn-theme-lg me-3">Apply Filters</button>
                                        <a class="btn btn-info" href="{{URL::to('/leads')}}">Reset</a>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>

                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th class="text-center">
                                        <div class="form-check custom-checkbox-table">
                                            <input type="checkbox" data-checkboxes="mygroup" data-checkbox-role="dad" class="form-check-input" id="checkbox-all">
                                            <label for="checkbox-all" class="form-check-label">&nbsp;</label>
                                        </div>
                                    </th>
                                    <th class="sortable-header">@sortablelink('first_name', 'Name')</th>
                                    <th>Info</th>
                                    <th class="sortable-header">@sortablelink('created_at', 'Contact Date')</th>
                                    <th class="sortable-header">@sortablelink('lead_status', 'Stage')</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody class="tdata">
                                @if(@$totalData !== 0)
                                <?php $i = 0; ?>
                                @foreach (@$lists as $list)
                                    <?php
                                    // Followup functionality removed
                                    ?>
                                    <tr id="id_{{@$list->id}}">
                                        <td style="white-space: initial;" class="text-center">
                                            <div class="form-check">
                                                <input data-id="{{@$list->id}}" data-email="{{@$list->email}}" data-name="{{@$list->first_name}} {{@$list->last_name}}" data-clientid="{{@$list->client_id}}" type="checkbox" data-checkboxes="mygroup" class="cb-element form-check-input  your-checkbox" id="checkbox-{{$i}}">
                                                <label for="checkbox-{{$i}}" class="form-check-label">&nbsp;</label>
                                            </div>
                                        </td>
                                        <td style="white-space: initial;">
                                            <a href="{{ route('clients.detail', base64_encode(convert_uuencode(@$list->id))) }}">
                                                {{ @$list->first_name == "" ? config('constants.empty') : Str::limit(@$list->first_name, '50', '...') }}
                                                {{ @$list->last_name == "" ? config('constants.empty') : Str::limit(@$list->last_name, '50', '...') }}
                                            </a>

                                        </td>
                                        <td><i class="fa-solid fa-mobile"></i> {{@$list->phone}} <br/> <i class="fa-solid fa-envelope"></i> {{@$list->email}}</td>
                                        <td>{{date('d/m/Y h:i:s a', strtotime($list->created_at))}}</td>
                                        <td>
                                            @php
                                                $stageKey = $list->lead_status ?: 'new';
                                                $stageLabel = ($leadStageLabels[$stageKey] ?? ucfirst(str_replace('_', ' ', $stageKey)));
                                                $stageSlug = \Illuminate\Support\Str::slug($stageKey, '_');
                                            @endphp
                                            <span class="status-badge {{ $stageSlug }}">
                                                <i class="fa-solid fa-circle"></i> {{ $stageLabel }}
                                            </span>
                                            @if($list->lead_status === 'follow_up' && $list->followup_date)
                                                <br><small class="text-muted">Follow-up: {{ $list->followup_date->format('d/m/Y') }}</small>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="{{route('clients.edit', base64_encode(convert_uuencode(@$list->id)))}}" class="btn-edit-icon" title="Edit Lead">
                                                    <i class="fa-solid fa-pen-to-square"></i>
                                                </a>
                                                <form action="{{ route('leads.archive', base64_encode(convert_uuencode(@$list->id))) }}" method="POST" class="archive-lead-form" style="display: inline-block;">
                                                    @csrf
                                                    <button type="button" class="btn-archive-icon" title="Archive Lead" onclick="confirmArchive(event, '{{ @$list->first_name }} {{ @$list->last_name }}');">
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
                                        <td colspan="6" style="text-align: center; padding: 20px;">
                                            No Record Found
                                        </td>
                                    </tr>
                                @endif
                                </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
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
<script>
    jQuery(document).ready(function($){
        // Import Lead Modal - drag & drop file picker
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

            $input.on('change', function() {
                syncSelectedFile(this.files && this.files[0] ? this.files[0] : null);
            });

            $removeBtn.on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $input.val('');
                syncSelectedFile(null);
            });

            $dropzone.on('dragover dragenter', function(e) {
                e.preventDefault();
                e.stopPropagation();
                if (!$dropzone.hasClass('has-file')) {
                    $dropzone.addClass('is-dragover');
                }
            });

            $dropzone.on('dragleave dragend drop', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $dropzone.removeClass('is-dragover');
            });

            $dropzone.on('drop', function(e) {
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

            $modal.on('hidden.bs.modal', function() {
                $input.val('');
                syncSelectedFile(null);
                $dropzone.removeClass('is-dragover');
                $submitBtn.prop('disabled', true).html('<i class="fa-solid fa-upload"></i> Import Leads');
            });

            $('#importLeadForm').on('submit', function() {
                $submitBtn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> Importing...');
            });

            if ($input[0] && $input[0].files && $input[0].files[0]) {
                syncSelectedFile($input[0].files[0]);
            }
        })();

        // Auto-open the import modal when there are import validation errors (after redirect back)
        @if ($errors->has('import_file'))
            $('#importLeadModal').modal('show');
        @endif

        $('#per_page').on('change', function(){
            var currentUrl = new URL(window.location.href);
            currentUrl.searchParams.set('per_page', $(this).val());
            currentUrl.searchParams.delete('page');
            window.location.href = currentUrl.toString();
        });

        $('.lead-quick-filter').on('click', function(){
            var filter = $(this).data('filter');
            $('#lead_quick_date_range').val(filter);
            $('#lead_from_date, #lead_to_date').val('');
            $('#leadFilterForm').submit();
        });

        $('#lead_from_date, #lead_to_date').on('change', function(){
            $('#lead_quick_date_range').val('');
        });

        $('#clearLeadFilters').on('click', function(){
            window.location.href = "{{ URL::to('/leads') }}";
        });

        $('.listing-container .filter_btn').on('click', function(){
            $('.listing-container .filter_panel').toggle();
        });
        
        $('.listing-container .assignlead_modal').on('click', function(){
              var val = $(this).attr('mleadid');
              $('#assignlead_modal #mlead_id').val(val);
              $('#assignlead_modal').modal('show');
          });

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

        $('.listing-container [data-checkboxes]').each(function () {
            var me = $(this),
            group = me.data('checkboxes'),
            role = me.data('checkbox-role');

            me.change(function () {
                var all = $('.listing-container [data-checkboxes="' + group + '"]:not([data-checkbox-role="dad"])'),
                checked = $('.listing-container [data-checkboxes="' + group + '"]:not([data-checkbox-role="dad"]):checked'),
                dad = $('.listing-container [data-checkboxes="' + group + '"][data-checkbox-role="dad"]'),
                total = all.length,
                checked_length = checked.length;
                if (role == 'dad') {
                    if (me.is(':checked')) {
                        all.prop('checked', true);
                        $('.listing-container .is_checked_client').show();
                        $('.listing-container .is_checked_clientn').hide();
                    } else {
                        all.prop('checked', false);
                        $('.listing-container .is_checked_client').hide();
                        $('.listing-container .is_checked_clientn').show();
                    }
                } else {
                    if (checked_length >= total) {
                        dad.prop('checked', true);
                        $('.listing-container .is_checked_client').show();
                        $('.listing-container .is_checked_clientn').hide();
                    } else {
                        dad.prop('checked', false);
                        $('.listing-container .is_checked_client').hide();
                        $('.listing-container .is_checked_clientn').show();
                    }
                }
                if(checked_length == 2){
                    $('.listing-container .is_checked_client_merge').show();
                } else {
                    $('.listing-container .is_checked_client_merge').hide();
                }
            });
        });

        var clickedOrder = [];
        var clickedIds = [];
        $(document).delegate('.listing-container .your-checkbox', 'click', function(){
            var clicked_id = $(this).data('id');
            var nameStr = $(this).attr('data-name');
            var clientidStr = $(this).attr('data-clientid');
            var finalStr = nameStr+'('+clientidStr+')';
            if ($(this).is(':checked')) {
                clickedOrder.push(finalStr);
                clickedIds.push(clicked_id);
            } else {
                var index = clickedOrder.indexOf(finalStr);
                if (index !== -1) {
                    clickedOrder.splice(index, 1);
                }
                var index1 = clickedIds.indexOf(clicked_id);
                if (index1 !== -1) {
                    clickedIds.splice(index1, 1);
                }
            }
        });

        //merge task
        $(document).delegate('.listing-container .is_checked_client_merge', 'click', function(){
            if ( clickedOrder.length > 0 && clickedOrder.length == 2 )
            {
                var mergeStr = "Are you sure want to merge "+clickedOrder[0]+" record into this "+clickedOrder[1]+" record?";
                if (confirm(mergeStr)) {
                    $.ajax({
                        type:'post',
                        url:"{{URL::to('/')}}/merge_records",
                        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
                        data: {merge_from:clickedIds[0],merge_into:clickedIds[1]},
                        success: function(response){
                            var obj = $.parseJSON(response);
                            location.reload(true);
                        }
                    });
                }
            }
        });

        $('.listing-container .cb-element').change(function () {
            if ($('.listing-container .cb-element:checked').length == $('.listing-container .cb-element').length){
                $('.listing-container #checkbox-all').prop('checked',true);
            }
            else {
                $('.listing-container #checkbox-all').prop('checked',false);
            }

            if ($('.listing-container .cb-element:checked').length > 0){
                $('.listing-container .is_checked_client').show();
                $('.listing-container .is_checked_clientn').hide();
            }else{
                $('.listing-container .is_checked_client').hide();
                $('.listing-container .is_checked_clientn').show();
            }
        });

        var crmRecipientsUrl = '{{ URL::to('/clients/get-recipients') }}';

        $(document).delegate('.listing-container .emailmodal', 'click', function(){
            $('#emailmodal').modal('show');
            var array = [];
            var data = [];
            $('.listing-container .cb-element:checked').each(function(){
                var id = $(this).attr('data-id');
                array.push(id);
                var email = $(this).attr('data-email');
                var name = $(this).attr('data-name');
                var status = 'Client';

                data.push({
                    id: id,
                    name: name,
                    email: email,
                    status: status
                });
            });

            var $to = $('#emailmodal .js-data-example-ajax');
            if ($to.length && typeof initRecipientsMultiTomSelectPreload === 'function') {
                initRecipientsMultiTomSelectPreload($to[0], { url: crmRecipientsUrl, dropdownParent: '#emailmodal', options: data, items: array });
            }

        });

        $(document).delegate('.listing-container .clientemail', 'click', function(){
            $('#emailmodal').modal('show');
            var array = [];
            var data = [];

            var id = $(this).attr('data-id');
            array.push(id);
            var email = $(this).attr('data-email');
            var name = $(this).attr('data-name');
            var status = 'Client';

            data.push({
                id: id,
                name: name,
                email: email,
                status: status
            });

            var $to = $('#emailmodal .js-data-example-ajax');
            if ($to.length && typeof initRecipientsMultiTomSelectPreload === 'function') {
                initRecipientsMultiTomSelectPreload($to[0], { url: crmRecipientsUrl, dropdownParent: '#emailmodal', options: data, items: array });
            }

        });

        $(document).delegate('.listing-container .selecttemplate', 'change', function(){
            var v = $(this).val();
            $.ajax({
                url: '{{URL::to('/get-templates')}}',
                type:'GET',
                datatype:'json',
                data:{id:v},
                success: function(response){
                    var res = JSON.parse(response);
                    $('.selectedsubject').val(res.subject);
                    // Clear and set TinyMCE editor content
                    $(".tinymce-editor").each(function() {
                        var editorId = $(this).attr('id');
                        if (editorId && typeof tinymce !== 'undefined' && tinymce.get(editorId)) {
                            tinymce.get(editorId).setContent(res.description || '');
                        } else {
                            $(this).val(res.description || '');
                        }
                    });
                }
            });
        });

        if (typeof initTS === 'function' && typeof buildCrmGetRecipientsMultiTomSelectConfig === 'function') {
            $('#emailmodal .js-data-example-ajax').each(function () {
                initTS(this, buildCrmGetRecipientsMultiTomSelectConfig({
                    url: crmRecipientsUrl,
                    dropdownParent: '#emailmodal',
                    enableRemoteLoad: true
                }));
            });
            $('#emailmodal .js-data-example-ajaxcc').each(function () {
                initTS(this, buildCrmGetRecipientsMultiTomSelectConfig({
                    url: crmRecipientsUrl,
                    dropdownParent: '#emailmodal',
                    enableRemoteLoad: true
                }));
            });
        }

        // Template picker: inside #emailmodal so skipped by global scripts.js; wire on shown.
        $(document).on('shown.bs.modal', '#emailmodal', function () {
            if (typeof initTS !== 'function') return;
            var modalEl = this;
            $(modalEl).find('.selecttemplate').each(function () {
                if (!this.tomselect) {
                    initTS(this, { create: false, allowEmptyOption: true, dropdownParent: modalEl });
                }
            });
        });
        $(document).on('hidden.bs.modal', '#emailmodal', function () {
            var modalEl = this;
            $(modalEl).find('.selecttemplate').each(function () {
                if (typeof destroyTS === 'function') destroyTS(this);
            });
        });
    });
</script>
<script>
    // Archive lead confirmation function - Global scope
    function confirmArchive(event, leadName) {
        // Prevent default button behavior
        if (event) {
            event.preventDefault();
            event.stopPropagation();
        }
        
        // Find the form - try multiple methods for compatibility
        var form = null;
        if (event && event.target) {
            form = event.target.closest('form');
            if (!form && typeof jQuery !== 'undefined') {
                form = jQuery(event.target).closest('.archive-lead-form')[0];
            }
        }
        
        // If still no form found, try to find by button
        if (!form && event && event.target) {
            var button = event.target.closest('button') || event.target;
            if (button) {
                form = button.closest('form');
            }
        }
        
        var confirmMessage = 'Are you sure you want to archive the lead "' + (leadName || 'this lead') + '"?\n\nThis will move the lead to the archived list.';
        
        if (confirm(confirmMessage)) {
            if (form) {
                form.submit();
            } else {
                alert('Error: Could not find the form to submit. Please try again.');
                console.error('Archive form not found');
            }
        }
        
        return false;
    }
</script>
@endpush


