@extends('layouts.crm_client_detail')
@section('title', 'Trust reports')

@section('content')
<div class="main-content">
    <section class="section">
        <div class="section-body">
            <div class="row mb-3">
                <div class="col-md-8">
                    <h4 class="mb-1"><i class="fa-solid fa-file-export text-secondary me-2"></i>Trust reports</h4>
                    <p class="text-muted mb-0" style="font-size: 14px;">
                        Practice-wide listings from the live trust ledger (non-voided rows only). Use for reconciliation and external examination supporting schedules. Export CSV for spreadsheets.
                    </p>
                </div>
                <div class="col-md-4 text-md-end mt-2 mt-md-0">
                    <a href="{{ route('trust-accounting.guide') }}" class="btn btn-outline-info btn-sm me-1">
                        <i class="fa-solid fa-book me-1"></i> Guide
                    </a>
                    <a href="{{ route('trust-accounting.withdrawal-authority-types.index') }}" class="btn btn-outline-primary btn-sm me-1">Rule 42 types</a>
                    <a href="{{ route('trust-accounting.bank-accounts.index') }}" class="btn btn-outline-primary btn-sm me-1">Bank accounts</a>
                    <a href="{{ route('trust-accounting.reconciliation.index') }}" class="btn btn-outline-primary btn-sm me-1">Reconciliation</a>
                    <a href="{{ route('trust-accounting.periods.index') }}" class="btn btn-outline-secondary btn-sm">Period locks</a>
                    <a href="{{ route('trust-accounting.practice-sequences.index') }}" class="btn btn-outline-secondary btn-sm">Sequences</a>
                    <a href="{{ route('trust-accounting.audit-log.index') }}" class="btn btn-outline-secondary btn-sm">Audit log</a>
                </div>
            </div>

            <div class="row">
                <div class="col-md-4 mb-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title"><i class="fa-solid fa-balance-scale me-2 text-primary"></i>Trial balance</h5>
                            <p class="card-text text-muted small">Trust funds held per client and matter, as at a date (sum of all posted movements to that date).</p>
                            <a href="{{ route('trust-accounting.reports.trial-balance') }}" class="btn btn-primary btn-sm">Open</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title"><i class="fa-solid fa-arrow-down me-2 text-success"></i>Receipts journal</h5>
                            <p class="card-text text-muted small">All trust movements with money in (deposits) in a date range. CSV includes invoice reference and payer/banking metadata where captured.</p>
                            <a href="{{ route('trust-accounting.reports.receipts-journal') }}" class="btn btn-primary btn-sm">Open</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title"><i class="fa-solid fa-arrow-up me-2 text-danger"></i>Payments journal</h5>
                            <p class="card-text text-muted small">Trust withdrawals in a date range. Includes Rule 42 authority columns on fee transfers (and in CSV) when Phase 5 tables are present.</p>
                            <a href="{{ route('trust-accounting.reports.payments-journal') }}" class="btn btn-primary btn-sm">Open</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title"><i class="fa-solid fa-triangle-exclamation me-2 text-warning"></i>Overdrawn ledger</h5>
                            <p class="card-text text-muted small">Rule 40 — practice-wide report of negative ledger balances and overdraw audit events.</p>
                            <a href="{{ route('trust-accounting.reports.overdrawn-ledger') }}" class="btn btn-primary btn-sm">Open</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title"><i class="fa-solid fa-file-zipper me-2 text-secondary"></i>Auditor's pack</h5>
                            <p class="card-text text-muted small">Download a ZIP with receipts journal, payments journal, trial balance and overdrawn ledger for a date range.</p>
                            <form method="get" action="{{ route('trust-accounting.reports.auditors-pack') }}" class="row g-1 mt-2">
                                <div class="col-6"><input type="date" name="from_date" class="form-control form-control-sm" required></div>
                                <div class="col-6"><input type="date" name="to_date" class="form-control form-control-sm" required></div>
                                <div class="col-12"><button type="submit" class="btn btn-primary btn-sm w-100">Download ZIP</button></div>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title"><i class="fa-solid fa-file-lines me-2 text-primary"></i>Trust statements</h5>
                            <p class="card-text text-muted small">Rule 52 — on-demand and 30 June annual statement runs.</p>
                            <a href="{{ route('trust-accounting.statements.index') }}" class="btn btn-primary btn-sm">Statements</a>
                            <a href="{{ route('trust-accounting.statements.annual') }}" class="btn btn-outline-secondary btn-sm">30 June</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title"><i class="fa-solid fa-box-archive me-2 text-secondary"></i>Monthly archives</h5>
                            <p class="card-text text-muted small">Rule 38 — immutable month-end CSV copies of journals and trial balance.</p>
                            <a href="{{ route('trust-accounting.archives.index') }}" class="btn btn-primary btn-sm">Archives</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title"><i class="fa-solid fa-building-columns me-2 text-info"></i>Bank reconciliation</h5>
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
