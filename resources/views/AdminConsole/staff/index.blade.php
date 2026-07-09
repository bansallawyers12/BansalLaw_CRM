@extends('layouts.crm_client_detail')
@include('components.require-tinymce')
@section('title', 'Staff')

@section('content')
<div class="main-content adminconsole-staff-list adminconsole-staff-spa adminconsole-staff-form"
    id="staff-admin-app"
    data-index-url="{{ route('adminconsole.staff.index') }}"
    data-create-url="{{ route('adminconsole.staff.create') }}"
    data-store-url="{{ route('adminconsole.staff.store') }}"
    data-edit-url-template="{{ route('adminconsole.staff.edit', ['id' => '__ID__']) }}"
    data-update-url-template="{{ route('adminconsole.staff.update', ['id' => '__ID__']) }}"
    data-view-url-template="{{ route('adminconsole.staff.view', ['id' => '__ID__']) }}"
    data-initial-tab="{{ $tab ?? 'active' }}"
    data-initial-search="{{ $searchBy ?? '' }}">
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
                    <div class="card staff-list-card">
                        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                            <div>
                                <h4 class="mb-1">Staff</h4>
                                <p class="text-muted small mb-0">Manage team members, roles, and access without leaving this page.</p>
                            </div>
                            <div class="card-header-action">
                                <button type="button" class="btn btn-primary" id="staff-add-btn">
                                    <i class="fa-solid fa-plus"></i> Add Staff
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="staff-list-tabs-toolbar d-flex flex-wrap align-items-center justify-content-between mb-3 gap-2">
                                <ul class="nav nav-pills mb-0 flex-wrap" id="staff_tabs" role="tablist">
                                    <li class="nav-item">
                                        <button type="button" class="nav-link staff-tab-btn {{ ($tab ?? 'active') === 'active' ? 'active' : '' }}" data-tab="active">Active</button>
                                    </li>
                                    <li class="nav-item">
                                        <button type="button" class="nav-link staff-tab-btn {{ ($tab ?? '') === 'inactive' ? 'active' : '' }}" data-tab="inactive">Inactive</button>
                                    </li>
                                    <li class="nav-item">
                                        <button type="button" class="nav-link staff-tab-btn {{ ($tab ?? '') === 'invited' ? 'active' : '' }}" data-tab="invited">All staff</button>
                                    </li>
                                </ul>
                                <div class="staff-list-search-form">
                                    <div class="staff-list-toolbar d-flex align-items-stretch">
                                        <input id="staff-search-input" type="search" class="form-control" value="{{ $searchBy ?? '' }}" placeholder="Search name or email" aria-label="Search staff">
                                        <button type="button" id="staff-search-btn" class="btn btn-primary" aria-label="Search">
                                            <i class="fa-solid fa-search"></i>
                                        </button>
                                        <button type="button" id="staff-search-clear" class="btn btn-light border ms-1" aria-label="Clear search" title="Clear search">
                                            <i class="fa-solid fa-times"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div id="staff-list-loading" class="staff-list-loading d-none" aria-hidden="true">
                                <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>
                                <span>Loading staff...</span>
                            </div>

                            <div id="staff-list-content">
                                @include('AdminConsole.staff.partials.list-table', [
                                    'lists' => $lists,
                                    'totalData' => $totalData,
                                    'tab' => $tab ?? 'active',
                                    'searchBy' => $searchBy ?? '',
                                ])
                            </div>
                        </div>
                        <div class="card-footer" id="staff-list-footer">
                            @include('AdminConsole.staff.partials.pagination', ['lists' => $lists])
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

{{-- Create staff modal --}}
<div class="modal fade staff-form-modal" id="staffCreateModal" tabindex="-1" aria-labelledby="staffCreateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable staff-form-modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-1" id="staffCreateModalLabel">Add staff member</h5>
                    <p class="text-muted small mb-0">Complete each section below. Required fields are in Personal details and Office & role.</p>
                </div>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <form id="staff-create-form" class="staff-modal-form" autocomplete="off" novalidate>
                @csrf
                <div class="modal-body">
                    <div id="staff-create-alert" class="alert alert-danger d-none" role="alert"></div>
                    <div id="staff-create-form-body"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="staff-create-submit">
                        <span class="submit-label">Save staff</span>
                        <span class="submit-spinner spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Edit staff modal --}}
<div class="modal fade staff-form-modal" id="staffEditModal" tabindex="-1" aria-labelledby="staffEditModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable staff-form-modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-1" id="staffEditModalLabel">Edit staff member</h5>
                    <p class="text-muted small mb-0">Expand sections to update details, permissions, or email signature.</p>
                </div>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <form id="staff-edit-form" class="staff-modal-form" autocomplete="off" novalidate>
                @csrf
                @method('PUT')
                <input type="hidden" id="staff_edit_id" name="staff_id" value="">
                <div class="modal-body">
                    <div id="staff-edit-alert" class="alert alert-danger d-none" role="alert"></div>
                    <div id="staff-edit-form-body"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-primary me-auto staff-view-from-edit" id="staff-view-from-edit">
                        <i class="fa-regular fa-eye"></i> View profile
                    </button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="staff-edit-submit">
                        <span class="submit-label">Update staff</span>
                        <span class="submit-spinner spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- View staff modal --}}
<div class="modal fade staff-form-modal" id="staffViewModal" tabindex="-1" aria-labelledby="staffViewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="staffViewModalLabel">Staff profile</h5>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <div id="staff-view-body"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="staff-edit-from-view">
                    <i class="fa-solid fa-edit"></i> Edit staff
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/adminconsole/staff.js') }}?v={{ time() }}"></script>
@endpush
