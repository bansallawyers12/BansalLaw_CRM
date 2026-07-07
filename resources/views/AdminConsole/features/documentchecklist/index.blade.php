@extends('layouts.crm_client_detail')
@section('title', 'Document Checklist')

@section('content')
<div class="main-content adminconsole-features adminconsole-document-checklist-list adminconsole-document-checklist-spa adminconsole-document-checklist-form"
    id="dcl-admin-app"
    data-index-url="{{ route('adminconsole.features.documentchecklist.index') }}"
    data-create-url="{{ route('adminconsole.features.documentchecklist.create') }}"
    data-store-url="{{ route('adminconsole.features.documentchecklist.store') }}"
    data-edit-url-template="{{ route('adminconsole.features.documentchecklist.edit', ['id' => '__ID__']) }}"
    data-update-url-template="{{ route('adminconsole.features.documentchecklist.update', ['id' => '__ID__']) }}"
    data-view-url-template="{{ route('adminconsole.features.documentchecklist.view', ['id' => '__ID__']) }}"
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
                    <div class="card dcl-list-card">
                        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                            <div>
                                <h4 class="mb-1">Document checklist</h4>
                                <p class="text-muted small mb-0">Manage checklist items by document type without leaving this page.</p>
                            </div>
                            <div class="card-header-action">
                                <button type="button" class="btn btn-primary" id="dcl-add-btn">
                                    <i class="fa fa-plus"></i> Add checklist
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="dcl-list-toolbar d-flex flex-wrap align-items-center justify-content-end mb-3 gap-2">
                                <div class="dcl-list-search-form">
                                    <div class="dcl-list-search d-flex align-items-stretch">
                                        <input id="dcl-search-input" type="search" class="form-control" value="{{ $searchBy ?? '' }}" placeholder="Search by name" aria-label="Search checklists">
                                        <button type="button" id="dcl-search-btn" class="btn btn-primary" aria-label="Search">
                                            <i class="fas fa-search"></i>
                                        </button>
                                        <button type="button" id="dcl-search-clear" class="btn btn-light border ms-1" aria-label="Clear search" title="Clear search">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div id="dcl-list-loading" class="dcl-list-loading d-none" aria-hidden="true">
                                <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>
                                <span>Loading checklists...</span>
                            </div>

                            <div id="dcl-list-content">
                                @include('AdminConsole.features.documentchecklist.partials.list-table', [
                                    'lists' => $lists,
                                    'totalData' => $totalData,
                                    'searchBy' => $searchBy ?? '',
                                ])
                            </div>
                        </div>
                        <div class="card-footer" id="dcl-list-footer">
                            @include('AdminConsole.features.documentchecklist.partials.pagination', [
                                'lists' => $lists,
                                'totalData' => $totalData,
                            ])
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<div class="modal fade dcl-form-modal" id="dclCreateModal" tabindex="-1" aria-labelledby="dclCreateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable dcl-form-modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-1" id="dclCreateModalLabel">Add checklist</h5>
                    <p class="text-muted small mb-0">Enter the checklist name and document type.</p>
                </div>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <form id="dcl-create-form" class="dcl-modal-form" autocomplete="off" novalidate>
                @csrf
                <div class="modal-body">
                    <div id="dcl-create-alert" class="alert alert-danger d-none" role="alert"></div>
                    <div id="dcl-create-form-body"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="dcl-create-submit">
                        <span class="submit-label">Save checklist</span>
                        <span class="submit-spinner spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade dcl-form-modal" id="dclEditModal" tabindex="-1" aria-labelledby="dclEditModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable dcl-form-modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-1" id="dclEditModalLabel">Edit checklist</h5>
                    <p class="text-muted small mb-0">Update the checklist name or document type.</p>
                </div>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <form id="dcl-edit-form" class="dcl-modal-form" autocomplete="off" novalidate>
                @csrf
                @method('PUT')
                <input type="hidden" id="dcl_edit_id" name="dcl_id" value="">
                <div class="modal-body">
                    <div id="dcl-edit-alert" class="alert alert-danger d-none" role="alert"></div>
                    <div id="dcl-edit-form-body"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-primary me-auto dcl-view-from-edit" id="dcl-view-from-edit">
                        <i class="far fa-eye"></i> View checklist
                    </button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="dcl-edit-submit">
                        <span class="submit-label">Update checklist</span>
                        <span class="submit-spinner spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade dcl-form-modal" id="dclViewModal" tabindex="-1" aria-labelledby="dclViewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="dclViewModalLabel">Checklist details</h5>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <div id="dcl-view-body"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="dcl-edit-from-view">
                    <i class="fa fa-edit"></i> Edit checklist
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/adminconsole/document-checklist.js') }}?v={{ time() }}"></script>
@endpush
