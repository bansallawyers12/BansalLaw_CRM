@extends('layouts.crm_client_detail')
@section('title', 'Matter')

@section('styles')
<style>
/* Scoped Matter Action Buttons & Dropdown UI */
.adminconsole-matter-spa .mat-list-table .mat-actions-cell {
    padding-top: 8px !important;
    padding-bottom: 8px !important;
    vertical-align: middle !important;
}

.adminconsole-matter-spa .mat-action-btns {
    display: inline-flex !important;
    align-items: center !important;
    gap: 6px !important;
    vertical-align: middle !important;
}

.adminconsole-matter-spa .mat-btn-action {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 5px !important;
    padding: 5px 11px !important;
    font-size: 12px !important;
    font-weight: 600 !important;
    line-height: 1.4 !important;
    border-radius: 6px !important;
    transition: all 0.15s ease-in-out !important;
    box-shadow: 0 1px 2px rgba(30, 61, 96, 0.05) !important;
    height: 31px !important;
    text-decoration: none !important;
}

.adminconsole-matter-spa .mat-btn-action i {
    font-size: 12px !important;
}

.adminconsole-matter-spa .mat-btn-view {
    background: var(--card-bg, #ffffff) !important;
    border: 1px solid var(--border, #c8dcef) !important;
    color: var(--navy, #1e3d60) !important;
}

.adminconsole-matter-spa .mat-btn-view:hover,
.adminconsole-matter-spa .mat-btn-view:focus {
    background: var(--sidebar-bg, #ddeaf8) !important;
    border-color: var(--sidebar-active, #3a6fa8) !important;
    color: var(--sidebar-active, #3a6fa8) !important;
}

.adminconsole-matter-spa .mat-btn-edit {
    background: var(--navy, #1e3d60) !important;
    border: 1px solid var(--navy, #1e3d60) !important;
    color: #ffffff !important;
}

.adminconsole-matter-spa .mat-btn-edit:hover,
.adminconsole-matter-spa .mat-btn-edit:focus {
    background: var(--sidebar-active, #3a6fa8) !important;
    border-color: var(--sidebar-active, #3a6fa8) !important;
    color: #ffffff !important;
    box-shadow: 0 2px 6px rgba(30, 61, 96, 0.18) !important;
}

.adminconsole-matter-spa .mat-btn-more {
    background: var(--card-bg, #ffffff) !important;
    border: 1px solid var(--border, #c8dcef) !important;
    color: var(--navy, #1e3d60) !important;
}

.adminconsole-matter-spa .mat-btn-more:hover,
.adminconsole-matter-spa .mat-btn-more:focus,
.adminconsole-matter-spa .mat-btn-more[aria-expanded="true"] {
    background: var(--sidebar-bg, #ddeaf8) !important;
    border-color: var(--sidebar-active, #3a6fa8) !important;
    color: var(--sidebar-active, #3a6fa8) !important;
}

.adminconsole-matter-spa .mat-list-table-wrap {
    overflow: visible !important;
}

.adminconsole-matter-spa .mat-list-table .mat-dropdown-menu {
    z-index: 1060 !important;
    overflow: visible !important;
    max-height: none !important;
    scrollbar-width: none !important;
    -ms-overflow-style: none !important;
    background: var(--card-bg, #ffffff) !important;
    border: 1px solid var(--border, #c8dcef) !important;
    border-top: 3px solid var(--accent-gold, #c8992a) !important;
    border-radius: 12px !important;
    box-shadow: 0 12px 32px rgba(30, 61, 96, 0.16), 0 2px 8px rgba(30, 61, 96, 0.06) !important;
    padding: 6px !important;
    min-width: 220px !important;
    margin-top: 6px !important;
    animation: matDropdownFadeIn 0.15s ease-out !important;
}

.adminconsole-matter-spa .mat-list-table .mat-dropdown-menu::-webkit-scrollbar {
    display: none !important;
    width: 0 !important;
    height: 0 !important;
}

@keyframes matDropdownFadeIn {
    from {
        opacity: 0;
        transform: translateY(-4px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.adminconsole-matter-spa .mat-dropdown-header {
    font-size: 10px !important;
    font-weight: 700 !important;
    text-transform: uppercase !important;
    letter-spacing: 0.08em !important;
    color: var(--text-muted, #5e7a90) !important;
    padding: 6px 12px 4px !important;
    user-select: none !important;
}

.adminconsole-matter-spa .mat-dropdown-menu .mat-dropdown-item {
    display: flex !important;
    align-items: center !important;
    gap: 10px !important;
    padding: 8px 12px !important;
    border-radius: 8px !important;
    color: var(--text-dark, #1a2c40) !important;
    font-size: 13px !important;
    font-weight: 500 !important;
    line-height: 1.4 !important;
    width: 100% !important;
    box-sizing: border-box !important;
    text-align: left !important;
    background: transparent !important;
    border: none !important;
    cursor: pointer !important;
    transition: all 0.15s ease !important;
    text-decoration: none !important;
}

.adminconsole-matter-spa .mat-dropdown-menu .mat-dropdown-item:hover,
.adminconsole-matter-spa .mat-dropdown-menu .mat-dropdown-item:focus {
    background-color: var(--page-bg, #f0f6ff) !important;
    color: var(--navy, #1e3d60) !important;
    padding-left: 15px !important;
}

.adminconsole-matter-spa .mat-dropdown-icon {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    width: 28px !important;
    height: 28px !important;
    border-radius: 6px !important;
    font-size: 13px !important;
    flex-shrink: 0 !important;
    transition: transform 0.15s ease !important;
}

.adminconsole-matter-spa .mat-dropdown-item:hover .mat-dropdown-icon {
    transform: scale(1.08) !important;
}

.adminconsole-matter-spa .mat-icon-email {
    background: rgba(58, 111, 168, 0.1) !important;
    color: var(--sidebar-active, #3a6fa8) !important;
}

.adminconsole-matter-spa .mat-icon-checklist {
    background: rgba(200, 153, 42, 0.12) !important;
    color: #936b0f !important;
}

.adminconsole-matter-spa .mat-icon-templates {
    background: rgba(30, 61, 96, 0.08) !important;
    color: var(--navy, #1e3d60) !important;
}

.adminconsole-matter-spa .mat-icon-delete {
    background: rgba(168, 48, 32, 0.1) !important;
    color: var(--danger, #a83020) !important;
}

.adminconsole-matter-spa .mat-dropdown-label {
    flex: 1 !important;
    white-space: nowrap !important;
}

.adminconsole-matter-spa .mat-dropdown-menu .mat-dropdown-divider {
    margin: 5px 0 !important;
    border: none !important;
    border-top: 1px solid var(--border, #c8dcef) !important;
    opacity: 0.7 !important;
    width: 100% !important;
    box-sizing: border-box !important;
}

.adminconsole-matter-spa .mat-dropdown-menu .mat-dropdown-item--danger {
    color: var(--danger, #a83020) !important;
}

.adminconsole-matter-spa .mat-dropdown-menu .mat-dropdown-item--danger:hover,
.adminconsole-matter-spa .mat-dropdown-menu .mat-dropdown-item--danger:focus {
    background-color: #fff1f0 !important;
    color: #8c1e10 !important;
}
</style>
@endsection

@section('content')
<div class="main-content adminconsole-features adminconsole-matter-list adminconsole-matter-spa adminconsole-matter-form matter-index-layout"
    id="mat-admin-app"
    data-index-url="{{ route('adminconsole.features.matter.index') }}"
    data-create-url="{{ route('adminconsole.features.matter.create') }}"
    data-store-url="{{ route('adminconsole.features.matter.store') }}"
    data-edit-url-template="{{ route('adminconsole.features.matter.edit', ['id' => '__ID__']) }}"
    data-update-url-template="{{ route('adminconsole.features.matter.update', ['id' => '__ID__']) }}"
    data-view-url-template="{{ route('adminconsole.features.matter.view', ['id' => '__ID__']) }}"
    data-initial-search="{{ $searchBy ?? '' }}"
    data-infinite-scroll="1">
    <section class="section">
        <div class="section-body">
            <div class="server-error">
                @include('../Elements/flash-message')
            </div>
            <div class="custom-error-msg"></div>
            <div class="row">
                <div class="col-3 col-md-3 col-lg-3">
                    @include('../Elements/CRM/setting')
                </div>
                <div class="col-9 col-md-9 col-lg-9">
                    <div class="card mat-list-card">
                        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                            <div>
                                <h4 class="mb-1">All matters</h4>
                                <p class="text-muted small mb-0">Manage matter types, workflows, and fees without leaving this page.</p>
                            </div>
                            <div class="card-header-action">
                                <button type="button" class="btn btn-primary" id="mat-add-btn">
                                    <i class="fa-solid fa-plus"></i> Create matter
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="mat-list-toolbar d-flex flex-wrap align-items-center justify-content-end mb-3 gap-2">
                                <div class="mat-list-search-form">
                                    <div class="mat-list-search d-flex align-items-stretch">
                                        <input id="mat-search-input" type="search" class="form-control" value="{{ $searchBy ?? '' }}" placeholder="Search by title or nick name" aria-label="Search matters">
                                        <button type="button" id="mat-search-btn" class="btn btn-primary" aria-label="Search">
                                            <i class="fa-solid fa-search"></i>
                                        </button>
                                        <button type="button" id="mat-search-clear" class="btn btn-light border ms-1" aria-label="Clear search" title="Clear search">
                                            <i class="fa-solid fa-xmark"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div id="mat-list-loading" class="mat-list-loading d-none" aria-hidden="true">
                                <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>
                                <span>Loading matters...</span>
                            </div>

                            <div id="mat-list-content">
                                @include('AdminConsole.features.matter.partials.list-table', [
                                    'lists' => $lists,
                                    'totalData' => $totalData,
                                    'searchBy' => $searchBy ?? '',
                                    'hasStreamColumn' => $hasStreamColumn ?? \Illuminate\Support\Facades\Schema::hasColumn('matters', 'stream'),
                                ])
                            </div>
                        </div>
                        <div class="card-footer" id="mat-list-footer">
                            @include('AdminConsole.features.matter.partials.scroll-status', [
                                'lists' => $lists,
                                'totalData' => $totalData,
                            ])
                        </div>
                        <div id="mat-infinite-loader" class="mat-infinite-loader text-center text-muted small py-2 d-none" aria-hidden="true" hidden>
                            <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                            Loading more matters...
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<div class="modal fade mat-form-modal" id="matCreateModal" tabindex="-1" aria-labelledby="matCreateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable mat-form-modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-1" id="matCreateModalLabel">Create matter</h5>
                    <p class="text-muted small mb-0">Enter matter details, block fees, and additional fees.</p>
                </div>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <form id="mat-create-form" class="mat-modal-form" autocomplete="off" novalidate>
                @csrf
                <div class="modal-body">
                    <div id="mat-create-alert" class="alert alert-danger d-none" role="alert"></div>
                    <div id="mat-create-form-body"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="mat-create-submit">
                        <span class="submit-label">Save matter</span>
                        <span class="submit-spinner spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade mat-form-modal" id="matEditModal" tabindex="-1" aria-labelledby="matEditModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable mat-form-modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-1" id="matEditModalLabel">Edit matter</h5>
                    <p class="text-muted small mb-0">Update matter details, block fees, and additional fees.</p>
                </div>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <form id="mat-edit-form" class="mat-modal-form" autocomplete="off" novalidate>
                @csrf
                @method('PUT')
                <input type="hidden" id="mat_edit_id" name="mat_id" value="">
                <div class="modal-body">
                    <div id="mat-edit-alert" class="alert alert-danger d-none" role="alert"></div>
                    <div id="mat-edit-form-body"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-primary me-auto mat-view-from-edit" id="mat-view-from-edit">
                        <i class="fa-regular fa-eye"></i> View matter
                    </button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="mat-edit-submit">
                        <span class="submit-label">Update matter</span>
                        <span class="submit-spinner spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade mat-form-modal" id="matViewModal" tabindex="-1" aria-labelledby="matViewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="matViewModalLabel">Matter details</h5>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <div id="mat-view-body"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="mat-edit-from-view">
                    <i class="fa-solid fa-pen-to-square"></i> Edit matter
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/adminconsole/matter.js') }}?v={{ time() }}"></script>
@endpush
