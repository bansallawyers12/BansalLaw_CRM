@extends('layouts.crm_client_detail')
@section('title', 'Roles and Permissions')

@section('content')
<div class="main-content adminconsole-features adminconsole-roles-list adminconsole-roles-spa adminconsole-roles-form"
    id="roles-admin-app"
    data-index-url="{{ route('adminconsole.system.roles.index') }}"
    data-create-url="{{ route('adminconsole.system.roles.create') }}"
    data-store-url="{{ route('adminconsole.system.roles.store') }}"
    data-edit-url-template="{{ route('adminconsole.system.roles.edit', ['id' => '__ID__']) }}"
    data-update-url-template="{{ route('adminconsole.system.roles.update', ['id' => '__ID__']) }}"
    data-view-url-template="{{ route('adminconsole.system.roles.view', ['id' => '__ID__']) }}"
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
                    <div class="card roles-list-card">
                        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                            <div>
                                <h4 class="mb-1">Roles and permissions</h4>
                                <p class="text-muted small mb-0">Manage user roles and module access without leaving this page.</p>
                            </div>
                            <div class="card-header-action">
                                <button type="button" class="btn btn-primary" id="roles-add-btn">
                                    <i class="fa-solid fa-plus"></i> Add role
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="roles-list-toolbar d-flex flex-wrap align-items-center justify-content-end mb-3 gap-2">
                                <div class="roles-list-search-form">
                                    <div class="roles-list-search d-flex align-items-stretch">
                                        <input id="roles-search-input" type="search" class="form-control" value="{{ $searchBy ?? '' }}" placeholder="Search name or description" aria-label="Search roles">
                                        <button type="button" id="roles-search-btn" class="btn btn-primary" aria-label="Search">
                                            <i class="fa-solid fa-search"></i>
                                        </button>
                                        <button type="button" id="roles-search-clear" class="btn btn-light border ms-1" aria-label="Clear search" title="Clear search">
                                            <i class="fa-solid fa-times"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div id="roles-list-loading" class="roles-list-loading d-none" aria-hidden="true">
                                <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>
                                <span>Loading roles...</span>
                            </div>

                            <div id="roles-list-content">
                                @include('AdminConsole.system.roles.partials.list-table', [
                                    'lists' => $lists,
                                    'totalData' => $totalData,
                                    'searchBy' => $searchBy ?? '',
                                ])
                            </div>
                        </div>
                        <div class="card-footer" id="roles-list-footer">
                            @include('AdminConsole.system.roles.partials.pagination', ['lists' => $lists, 'totalData' => $totalData])
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

{{-- Create role modal --}}
<div class="modal fade roles-form-modal" id="rolesCreateModal" tabindex="-1" aria-labelledby="rolesCreateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable roles-form-modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-1" id="rolesCreateModalLabel">Add role</h5>
                    <p class="text-muted small mb-0">Set the role name, then expand Module permissions to configure access.</p>
                </div>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <form id="roles-create-form" class="roles-modal-form" autocomplete="off" novalidate>
                @csrf
                <div class="modal-body">
                    <div id="roles-create-alert" class="alert alert-danger d-none" role="alert"></div>
                    <div id="roles-create-form-body"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="roles-create-submit">
                        <span class="submit-label">Save role</span>
                        <span class="submit-spinner spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Edit role modal --}}
<div class="modal fade roles-form-modal" id="rolesEditModal" tabindex="-1" aria-labelledby="rolesEditModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable roles-form-modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-1" id="rolesEditModalLabel">Edit role</h5>
                    <p class="text-muted small mb-0">Update role details and module permissions.</p>
                </div>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <form id="roles-edit-form" class="roles-modal-form" autocomplete="off" novalidate>
                @csrf
                @method('PUT')
                <input type="hidden" id="roles_edit_id" name="role_id" value="">
                <div class="modal-body">
                    <div id="roles-edit-alert" class="alert alert-danger d-none" role="alert"></div>
                    <div id="roles-edit-form-body"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-primary me-auto roles-view-from-edit" id="roles-view-from-edit">
                        <i class="fa-regular fa-eye"></i> View role
                    </button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="roles-edit-submit">
                        <span class="submit-label">Update role</span>
                        <span class="submit-spinner spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- View role modal --}}
<div class="modal fade roles-form-modal" id="rolesViewModal" tabindex="-1" aria-labelledby="rolesViewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="rolesViewModalLabel">Role details</h5>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <div id="roles-view-body"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="roles-edit-from-view">
                    <i class="fa-solid fa-edit"></i> Edit role
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/adminconsole/roles.js') }}?v={{ time() }}"></script>
@endpush
