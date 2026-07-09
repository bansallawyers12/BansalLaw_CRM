@extends('layouts.crm_client_detail')
@section('title', 'Personal Document Folder')

@section('content')
<div class="main-content adminconsole-features adminconsole-personal-doc-type-list adminconsole-personal-doc-type-spa adminconsole-personal-doc-type-form"
    id="pdt-admin-app"
    data-index-url="{{ route('adminconsole.features.personaldocumenttype.index') }}"
    data-create-url="{{ route('adminconsole.features.personaldocumenttype.create') }}"
    data-store-url="{{ route('adminconsole.features.personaldocumenttype.store') }}"
    data-edit-url-template="{{ route('adminconsole.features.personaldocumenttype.edit', ['id' => '__ID__']) }}"
    data-update-url-template="{{ route('adminconsole.features.personaldocumenttype.update', ['id' => '__ID__']) }}"
    data-view-url-template="{{ route('adminconsole.features.personaldocumenttype.view', ['id' => '__ID__']) }}"
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
                    <div class="card pdt-list-card">
                        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                            <div>
                                <h4 class="mb-1">Personal document folders</h4>
                                <p class="text-muted small mb-0">Manage folder types for client personal documents without leaving this page.</p>
                            </div>
                            <div class="card-header-action">
                                <button type="button" class="btn btn-primary" id="pdt-add-btn">
                                    <i class="fa-solid fa-plus"></i> Add folder
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="pdt-list-toolbar d-flex flex-wrap align-items-center justify-content-end mb-3 gap-2">
                                <div class="pdt-list-search-form">
                                    <div class="pdt-list-search d-flex align-items-stretch">
                                        <input id="pdt-search-input" type="search" class="form-control" value="{{ $searchBy ?? '' }}" placeholder="Search by title" aria-label="Search folders">
                                        <button type="button" id="pdt-search-btn" class="btn btn-primary" aria-label="Search">
                                            <i class="fa-solid fa-search"></i>
                                        </button>
                                        <button type="button" id="pdt-search-clear" class="btn btn-light border ms-1" aria-label="Clear search" title="Clear search">
                                            <i class="fa-solid fa-xmark"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div id="pdt-list-loading" class="pdt-list-loading d-none" aria-hidden="true">
                                <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>
                                <span>Loading folders...</span>
                            </div>

                            <div id="pdt-list-content">
                                @include('AdminConsole.features.personaldocumenttype.partials.list-table', [
                                    'lists' => $lists,
                                    'totalData' => $totalData,
                                    'searchBy' => $searchBy ?? '',
                                ])
                            </div>
                        </div>
                        <div class="card-footer" id="pdt-list-footer">
                            @include('AdminConsole.features.personaldocumenttype.partials.pagination', [
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

{{-- Create modal --}}
<div class="modal fade pdt-form-modal" id="pdtCreateModal" tabindex="-1" aria-labelledby="pdtCreateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable pdt-form-modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-1" id="pdtCreateModalLabel">Add personal document folder</h5>
                    <p class="text-muted small mb-0">Enter a title for the new folder.</p>
                </div>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <form id="pdt-create-form" class="pdt-modal-form" autocomplete="off" novalidate>
                @csrf
                <div class="modal-body">
                    <div id="pdt-create-alert" class="alert alert-danger d-none" role="alert"></div>
                    <div id="pdt-create-form-body"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="pdt-create-submit">
                        <span class="submit-label">Save folder</span>
                        <span class="submit-spinner spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Edit modal --}}
<div class="modal fade pdt-form-modal" id="pdtEditModal" tabindex="-1" aria-labelledby="pdtEditModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable pdt-form-modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-1" id="pdtEditModalLabel">Edit personal document folder</h5>
                    <p class="text-muted small mb-0">Update the folder title.</p>
                </div>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <form id="pdt-edit-form" class="pdt-modal-form" autocomplete="off" novalidate>
                @csrf
                @method('PUT')
                <input type="hidden" id="pdt_edit_id" name="pdt_id" value="">
                <div class="modal-body">
                    <div id="pdt-edit-alert" class="alert alert-danger d-none" role="alert"></div>
                    <div id="pdt-edit-form-body"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-primary me-auto pdt-view-from-edit" id="pdt-view-from-edit">
                        <i class="fa-regular fa-eye"></i> View folder
                    </button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="pdt-edit-submit">
                        <span class="submit-label">Update folder</span>
                        <span class="submit-spinner spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- View modal --}}
<div class="modal fade pdt-form-modal" id="pdtViewModal" tabindex="-1" aria-labelledby="pdtViewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="pdtViewModalLabel">Folder details</h5>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <div id="pdt-view-body"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="pdt-edit-from-view">
                    <i class="fa-solid fa-pen-to-square"></i> Edit folder
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/adminconsole/personal-document-type.js') }}?v={{ time() }}"></script>
@endpush
