@extends('layouts.crm_client_detail')
@section('title', 'Trust statements')

@section('content')
<div class="main-content">
    <section class="section">
        <div class="section-body">
            @include('../Elements/flash-message')
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
                <div>
                    <h4 class="mb-1">Trust account statements</h4>
                    <p class="text-muted small mb-0">Rule 52 — generate on-demand statements by client and matter.</p>
                </div>
                <a href="{{ route('trust-accounting.statements.annual') }}" class="btn btn-outline-primary btn-sm">30 June batch</a>
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <form method="get" action="{{ route('trust-accounting.statements.generate') }}" target="_blank" class="row g-2 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label small mb-0">Client ID (admins.id)</label>
                            <input type="number" name="client_id" class="form-control form-control-sm" value="{{ old('client_id', request('client_id')) }}" required min="1">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small mb-0">Matter ID (client_matters.id)</label>
                            <input type="number" name="matter_id" class="form-control form-control-sm" value="{{ old('matter_id', request('matter_id')) }}" required min="1">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small mb-0">From (optional)</label>
                            <input type="date" name="from_date" class="form-control form-control-sm" value="{{ request('from_date') }}">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small mb-0">To (optional)</label>
                            <input type="date" name="to_date" class="form-control form-control-sm" value="{{ request('to_date') }}">
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary btn-sm w-100">Generate PDF</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>
</div>
@endsection
