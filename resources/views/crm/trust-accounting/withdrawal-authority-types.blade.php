@extends('layouts.crm_client_detail')
@section('title', 'Rule 42 — withdrawal authority types')

@section('content')
<div class="main-content">
    <section class="section">
        <div class="section-body">
            @include('../Elements/flash-message')

            <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
                <div>
                    <h4 class="mb-1"><i class="fa-solid fa-gavel text-secondary me-2"></i>Rule 42 withdrawal authority types</h4>
                    <p class="text-muted small mb-0">
                        Labels shown when staff post a <strong>Fee transfer</strong> from trust. Inactive types cannot be selected on new entries but remain on historical records.
                    </p>
                </div>
                <div class="d-flex flex-wrap gap-1">
                    <a href="{{ route('trust-accounting.periods.index') }}" class="btn btn-outline-secondary btn-sm">Period locks</a>
                    <a href="{{ route('trust-accounting.reports.index') }}" class="btn btn-outline-primary btn-sm">Reports</a>
                </div>
            </div>

            <div class="row">
                <div class="col-lg-5 mb-4">
                    <div class="card">
                        <div class="card-header"><h5 class="mb-0">Add type</h5></div>
                        <div class="card-body">
                            <form method="post" action="{{ route('trust-accounting.withdrawal-authority-types.store') }}">
                                @csrf
                                <div class="mb-3">
                                    <label class="form-label">Label</label>
                                    <input type="text" name="label" class="form-control form-control-sm @error('label') is-invalid @enderror"
                                           value="{{ old('label') }}" required maxlength="255">
                                    @error('label')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Sort order</label>
                                    <input type="number" name="sort_order" class="form-control form-control-sm @error('sort_order') is-invalid @enderror"
                                           value="{{ old('sort_order', 0) }}" min="0" max="65535">
                                    @error('sort_order')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="mb-3 form-check">
                                    <input type="hidden" name="is_active" value="0">
                                    <input type="checkbox" name="is_active" value="1" class="form-check-input" id="new_is_active"
                                           {{ old('is_active', '1') === '1' || old('is_active') === true ? 'checked' : '' }}>
                                    <label class="form-check-label" for="new_is_active">Active</label>
                                </div>
                                <button type="submit" class="btn btn-primary btn-sm">Save</button>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-lg-7 mb-4">
                    <div class="card">
                        <div class="card-header"><h5 class="mb-0">Configured types</h5></div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-striped table-sm mb-0 align-middle">
                                    <thead>
                                        <tr>
                                            <th>Label</th>
                                            <th style="width: 90px;">Order</th>
                                            <th style="width: 90px;">Status</th>
                                            <th style="width: 100px;"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($types as $t)
                                            <tr>
                                                <td class="py-2">
                                                    <form method="post" action="{{ route('trust-accounting.withdrawal-authority-types.update', $t) }}" id="authority-type-{{ $t->id }}" class="d-none" aria-hidden="true"></form>
                                                    <input type="hidden" name="_token" form="authority-type-{{ $t->id }}" value="{{ csrf_token() }}">
                                                    <input type="hidden" name="_method" form="authority-type-{{ $t->id }}" value="PUT">
                                                    <input type="text" name="label" form="authority-type-{{ $t->id }}"
                                                           class="form-control form-control-sm" value="{{ $t->label }}" required maxlength="255">
                                                </td>
                                                <td class="py-2">
                                                    <input type="number" name="sort_order" form="authority-type-{{ $t->id }}"
                                                           class="form-control form-control-sm" value="{{ $t->sort_order }}" min="0" max="65535">
                                                </td>
                                                <td class="py-2">
                                                    <input type="hidden" name="is_active" value="0" form="authority-type-{{ $t->id }}">
                                                    <div class="form-check mb-0">
                                                        <input type="checkbox" name="is_active" value="1" form="authority-type-{{ $t->id }}"
                                                               class="form-check-input" id="active_{{ $t->id }}" {{ $t->is_active ? 'checked' : '' }}>
                                                        <label class="form-check-label small" for="active_{{ $t->id }}">Active</label>
                                                    </div>
                                                </td>
                                                <td class="py-2">
                                                    <button type="submit" form="authority-type-{{ $t->id }}" class="btn btn-outline-primary btn-sm">Update</button>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="4" class="text-center text-muted py-4">No authority types.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
@endsection
