@extends('layouts.crm_client_detail')
@section('title', 'Trust reports')

@section('content')
<div class="main-content">
    <section class="section">
        <div class="section-body">
            <div class="row mb-3">
                <div class="col-md-8">
                    <h4 class="mb-1"><i class="fas fa-file-export text-secondary me-2"></i>Trust reports</h4>
                    <p class="text-muted mb-0" style="font-size: 14px;">
                        Practice-wide listings from the live trust ledger (non-voided rows only). Use for reconciliation and external examination supporting schedules. Export CSV for spreadsheets.
                    </p>
                </div>
                <div class="col-md-4 text-md-end mt-2 mt-md-0">
                    <a href="{{ route('trust-accounting.withdrawal-authority-types.index') }}" class="btn btn-outline-primary btn-sm me-1">Rule 42 types</a>
                    <a href="{{ route('trust-accounting.bank-accounts.index') }}" class="btn btn-outline-primary btn-sm me-1">Bank accounts</a>
                    <a href="{{ route('trust-accounting.reconciliation.index') }}" class="btn btn-outline-primary btn-sm me-1">Reconciliation</a>
                    <a href="{{ route('trust-accounting.periods.index') }}" class="btn btn-outline-secondary btn-sm">Period locks</a>
                    <a href="{{ route('trust-accounting.audit-log.index') }}" class="btn btn-outline-secondary btn-sm">Audit log</a>
                </div>
            </div>

            <div class="row">
                <div class="col-md-4 mb-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title"><i class="fas fa-balance-scale me-2 text-primary"></i>Trial balance</h5>
                            <p class="card-text text-muted small">Trust funds held per client and matter, as at a date (sum of all posted movements to that date).</p>
                            <a href="{{ route('trust-accounting.reports.trial-balance') }}" class="btn btn-primary btn-sm">Open</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title"><i class="fas fa-arrow-down me-2 text-success"></i>Receipts journal</h5>
                            <p class="card-text text-muted small">All trust movements with money in (deposits) in a date range, including reversals that post a receipt.</p>
                            <a href="{{ route('trust-accounting.reports.receipts-journal') }}" class="btn btn-primary btn-sm">Open</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title"><i class="fas fa-arrow-up me-2 text-danger"></i>Payments journal</h5>
                            <p class="card-text text-muted small">All trust movements with money out (payments, transfers, disbursements) in a date range.</p>
                            <a href="{{ route('trust-accounting.reports.payments-journal') }}" class="btn btn-primary btn-sm">Open</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title"><i class="fas fa-university me-2 text-info"></i>Bank reconciliation</h5>
                            <p class="card-text text-muted small">Model trust bank accounts, enter statement lines, and match them to ledger receipts and payments (Rule 48 workflow).</p>
                            <a href="{{ route('trust-accounting.reconciliation.index') }}" class="btn btn-primary btn-sm">Open</a>
                            <a href="{{ route('trust-accounting.bank-accounts.index') }}" class="btn btn-outline-secondary btn-sm">Accounts</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
@endsection
