@extends('layouts.crm_client_detail')
@section('title', 'Matter')

@section('content')
<div class="main-content adminconsole-features adminconsole-matter-list adminconsole-matter-spa adminconsole-matter-form matter-index-layout"
    id="mat-admin-app"
    data-index-url="{{ route('adminconsole.features.matter.index') }}"
    data-create-url="{{ route('adminconsole.features.matter.create') }}"
    data-store-url="{{ route('adminconsole.features.matter.store') }}"
    data-edit-url-template="{{ route('adminconsole.features.matter.edit', ['id' => '__ID__']) }}"
    data-update-url-template="{{ route('adminconsole.features.matter.update', ['id' => '__ID__']) }}"
    data-view-url-template="{{ route('adminconsole.features.matter.view', ['id' => '__ID__']) }}"
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
                    <div class="card mat-list-card">
                        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                            <div>
                                <h4 class="mb-1">All matters</h4>
                                <p class="text-muted small mb-0">Manage matter types, workflows, and fees without leaving this page.</p>
                            </div>
                            <div class="card-header-action">
                                <button type="button" class="btn btn-primary" id="mat-add-btn">
                                    <i class="fa fa-plus"></i> Create matter
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="mat-list-toolbar d-flex flex-wrap align-items-center justify-content-end mb-3 gap-2">
                                <div class="mat-list-search-form">
                                    <div class="mat-list-search d-flex align-items-stretch">
                                        <input id="mat-search-input" type="search" class="form-control" value="{{ $searchBy ?? '' }}" placeholder="Search by title or nick name" aria-label="Search matters">
                                        <button type="button" id="mat-search-btn" class="btn btn-primary" aria-label="Search">
                                            <i class="fas fa-search"></i>
                                        </button>
                                        <button type="button" id="mat-search-clear" class="btn btn-light border ms-1" aria-label="Clear search" title="Clear search">
                                            <i class="fas fa-times"></i>
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
                            @include('AdminConsole.features.matter.partials.pagination', [
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
                        <i class="far fa-eye"></i> View matter
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
                    <i class="fa fa-edit"></i> Edit matter
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/adminconsole/matter.js') }}?v={{ time() }}"></script>
@endpush
