@extends('layouts.crm_client_detail')
@section('title', 'Matter Document Folders')

@section('content')
<div class="main-content adminconsole-features adminconsole-matter-document-type-list adminconsole-matter-document-type-spa adminconsole-matter-document-type-form"
    id="mdt-admin-app"
    data-index-url="{{ route('adminconsole.features.matterdocumenttype.index') }}"
    data-create-url="{{ route('adminconsole.features.matterdocumenttype.create') }}"
    data-store-url="{{ route('adminconsole.features.matterdocumenttype.store') }}"
    data-edit-url-template="{{ route('adminconsole.features.matterdocumenttype.edit', ['id' => '__ID__']) }}"
    data-update-url-template="{{ route('adminconsole.features.matterdocumenttype.update', ['id' => '__ID__']) }}"
    data-view-url-template="{{ route('adminconsole.features.matterdocumenttype.view', ['id' => '__ID__']) }}"
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
                    <div class="card mdt-list-card">
                        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                            <div>
                                <h4 class="mb-1">Matter document folders</h4>
                                <p class="text-muted small mb-0">Manage folder types for matter documents without leaving this page.</p>
                            </div>
                            <div class="card-header-action">
                                <button type="button" class="btn btn-primary" id="mdt-add-btn">
                                    <i class="fa fa-plus"></i> Add folder
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="mdt-list-toolbar d-flex flex-wrap align-items-center justify-content-end mb-3 gap-2">
                                <div class="mdt-list-search-form">
                                    <div class="mdt-list-search d-flex align-items-stretch">
                                        <input id="mdt-search-input" type="search" class="form-control" value="{{ $searchBy ?? '' }}" placeholder="Search by title" aria-label="Search folders">
                                        <button type="button" id="mdt-search-btn" class="btn btn-primary" aria-label="Search">
                                            <i class="fas fa-search"></i>
                                        </button>
                                        <button type="button" id="mdt-search-clear" class="btn btn-light border ms-1" aria-label="Clear search" title="Clear search">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div id="mdt-list-loading" class="mdt-list-loading d-none" aria-hidden="true">
                                <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>
                                <span>Loading folders...</span>
                            </div>

                            <div id="mdt-list-content">
                                @include('AdminConsole.features.matterdocumenttype.partials.list-table', [
                                    'lists' => $lists,
                                    'totalData' => $totalData,
                                    'searchBy' => $searchBy ?? '',
                                ])
                            </div>
                        </div>
                        <div class="card-footer" id="mdt-list-footer">
                            @include('AdminConsole.features.matterdocumenttype.partials.pagination', [
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

<div class="modal fade mdt-form-modal" id="mdtCreateModal" tabindex="-1" aria-labelledby="mdtCreateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable mdt-form-modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-1" id="mdtCreateModalLabel">Add matter document folder</h5>
                    <p class="text-muted small mb-0">Enter a title for the new folder.</p>
                </div>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <form id="mdt-create-form" class="mdt-modal-form" autocomplete="off" novalidate>
                @csrf
                <div class="modal-body">
                    <div id="mdt-create-alert" class="alert alert-danger d-none" role="alert"></div>
                    <div id="mdt-create-form-body"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="mdt-create-submit">
                        <span class="submit-label">Save folder</span>
                        <span class="submit-spinner spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade mdt-form-modal" id="mdtEditModal" tabindex="-1" aria-labelledby="mdtEditModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable mdt-form-modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-1" id="mdtEditModalLabel">Edit matter document folder</h5>
                    <p class="text-muted small mb-0">Update the folder title.</p>
                </div>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <form id="mdt-edit-form" class="mdt-modal-form" autocomplete="off" novalidate>
                @csrf
                @method('PUT')
                <input type="hidden" id="mdt_edit_id" name="mdt_id" value="">
                <div class="modal-body">
                    <div id="mdt-edit-alert" class="alert alert-danger d-none" role="alert"></div>
                    <div id="mdt-edit-form-body"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-primary me-auto mdt-view-from-edit" id="mdt-view-from-edit">
                        <i class="far fa-eye"></i> View folder
                    </button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="mdt-edit-submit">
                        <span class="submit-label">Update folder</span>
                        <span class="submit-spinner spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade mdt-form-modal" id="mdtViewModal" tabindex="-1" aria-labelledby="mdtViewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="mdtViewModalLabel">Folder details</h5>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <div id="mdt-view-body"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="mdt-edit-from-view">
                    <i class="fa fa-edit"></i> Edit folder
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/adminconsole/matter-document-type.js') }}?v={{ time() }}"></script>
@endpush
