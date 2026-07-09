@extends('layouts.crm_client_detail')
@section('title', 'Trust bank accounts')

@section('content')
<div class="main-content">
    <section class="section">
        <div class="section-body">
            @include('../Elements/flash-message')

            <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
                <div>
                    <h4 class="mb-1"><i class="fa-solid fa-building-columns text-secondary me-2"></i>Trust bank accounts</h4>
                    <p class="text-muted small mb-0">Practice trust accounts at your ADI (Rule 57 disclosure support). Use these records when reconciling bank statements to the trust cash book.</p>
                </div>
                <div class="d-flex flex-wrap gap-1">
                    <a href="{{ route('trust-accounting.reconciliation.index') }}" class="btn btn-primary btn-sm">Reconciliation</a>
                    <a href="{{ route('trust-accounting.reports.index') }}" class="btn btn-outline-secondary btn-sm">Reports</a>
                </div>
            </div>

            <div class="row">
                <div class="col-lg-5 mb-4">
                    <div class="card">
                        <div class="card-header"><h5 class="mb-0">Add account</h5></div>
                        <div class="card-body">
                            <form method="post" action="{{ route('trust-accounting.bank-accounts.store') }}">
                                @csrf
                                <div class="mb-3">
                                    <label class="form-label">Display name</label>
                                    <input type="text" name="name" class="form-control form-control-sm @error('name') is-invalid @enderror" value="{{ old('name') }}" required maxlength="255">
                                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">BSB <span class="text-muted">(optional)</span></label>
                                    <input type="text" name="bsb" class="form-control form-control-sm" value="{{ old('bsb') }}" maxlength="16">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Account number hint <span class="text-muted">(e.g. last 4 digits)</span></label>
                                    <input type="text" name="account_number_hint" class="form-control form-control-sm" value="{{ old('account_number_hint') }}" maxlength="32">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Notes</label>
                                    <textarea name="notes" class="form-control form-control-sm" rows="2" maxlength="5000">{{ old('notes') }}</textarea>
                                </div>
                                <button type="submit" class="btn btn-primary btn-sm">Save</button>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-lg-7 mb-4">
                    <div class="card">
                        <div class="card-header"><h5 class="mb-0">Accounts</h5></div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-striped table-sm mb-0">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>BSB</th>
                                            <th>Hint</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($accounts as $acc)
                                            <tr>
                                                <td>{{ $acc->name }}</td>
                                                <td class="font-monospace">{{ $acc->bsb ?? '—' }}</td>
                                                <td class="font-monospace">{{ $acc->account_number_hint ?? '—' }}</td>
                                                <td>
                                                    @if($acc->is_active)
                                                        <span class="badge bg-success">Active</span>
                                                    @else
                                                        <span class="badge bg-secondary">Inactive</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="4" class="text-center text-muted py-4">No trust bank accounts yet.</td></tr>
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
