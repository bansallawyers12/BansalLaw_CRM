@extends('layouts.crm_client_detail')
@section('title', 'Trust audit log')

@section('content')
<div class="main-content">
    <section class="section">
        <div class="section-body">
            <div class="server-error">
                @include('../Elements/flash-message')
            </div>

            <div class="row mb-3">
                <div class="col-md-8">
                    <h4 class="mb-1"><i class="fa-solid fa-clipboard-list text-secondary me-2"></i>Trust audit log</h4>
                    <p class="text-muted mb-0" style="font-size: 14px;">Append-only record of trust ledger voids, Rule 42 authorities, bank reconciliation actions, and period lock/unlock. Filter and export CSV (up to 10,000 rows per download) for examinations.</p>
                </div>
                <div class="col-md-4 text-md-end mt-2 mt-md-0">
                    <a href="{{ route('trust-accounting.guide') }}" class="btn btn-outline-info btn-sm me-1">
                        <i class="fa-solid fa-book me-1"></i> Guide
                    </a>
                    <a href="{{ route('trust-accounting.reports.index') }}" class="btn btn-outline-primary btn-sm me-1">Reports</a>
                    <a href="{{ route('trust-accounting.practice-sequences.index') }}" class="btn btn-outline-secondary btn-sm me-1">
                        <i class="fa-solid fa-sort-numeric-down me-1"></i> Sequences
                    </a>
                    <a href="{{ route('trust-accounting.periods.index') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="fa-solid fa-lock me-1"></i> Period locks
                    </a>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <form method="get" action="{{ route('trust-accounting.audit-log.index') }}" class="row g-2 align-items-end">
                        <div class="col-md-2">
                            <label class="form-label small mb-0">Table</label>
                            <select name="table_name" class="form-control form-control-sm">
                                <option value="">Any</option>
                                <option value="account_client_receipts" @selected(request('table_name') === 'account_client_receipts')>Ledger rows</option>
                                <option value="trust_accounting_periods" @selected(request('table_name') === 'trust_accounting_periods')>Period locks</option>
                                <option value="trust_withdrawal_authorities" @selected(request('table_name') === 'trust_withdrawal_authorities')>Rule 42 authorities</option>
                                <option value="trust_bank_accounts" @selected(request('table_name') === 'trust_bank_accounts')>Trust bank accounts</option>
                                <option value="trust_bank_statement_lines" @selected(request('table_name') === 'trust_bank_statement_lines')>Bank statement lines</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small mb-0">Row ID</label>
                            <input type="number" name="row_id" class="form-control form-control-sm" value="{{ request('row_id') }}" min="1" placeholder="e.g. receipt id">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small mb-0">Event contains</label>
                            <input type="text" name="event" class="form-control form-control-sm" value="{{ request('event') }}" placeholder="voided, lock…">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small mb-0">From</label>
                            <input type="date" name="from_date" class="form-control form-control-sm" value="{{ request('from_date') }}">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small mb-0">To</label>
                            <input type="date" name="to_date" class="form-control form-control-sm" value="{{ request('to_date') }}">
                        </div>
                        <div class="col-md-2 d-flex gap-1 flex-wrap">
                            <button type="submit" class="btn btn-primary btn-sm flex-grow-1">Filter</button>
                            <button type="submit" name="export" value="csv" class="btn btn-outline-secondary btn-sm flex-grow-1" title="Exports up to 10,000 matching rows">CSV</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-sm mb-0 text-nowrap">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>When</th>
                                    <th>Table</th>
                                    <th>Row</th>
                                    <th>Event</th>
                                    <th>Field</th>
                                    <th>Performer</th>
                                    <th>IP</th>
                                    <th>Details</th>
                                </tr>
                            </thead>
                            <tbody>
                            @forelse($logs as $log)
                                @php
                                    $auditOld = (string) ($log->old_value ?? '');
                                    $auditNew = (string) ($log->new_value ?? '');
                                    $auditCtx = (string) ($log->context ?? '');
                                @endphp
                                <tr>
                                    <td>{{ $log->id }}</td>
                                    <td>
                                        @if($log->created_at)
                                            {{ \Carbon\Carbon::parse($log->created_at)->format('Y-m-d H:i:s') }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td>{{ $log->table_name }}</td>
                                    <td>{{ $log->row_id }}</td>
                                    <td>{{ $log->event }}</td>
                                    <td>{{ $log->field_name ?? '—' }}</td>
                                    <td>
                                        @if(filled($log->performer_name))
                                            {{ trim((string) $log->performer_name) }}
                                        @elseif($log->performed_by)
                                            #{{ $log->performed_by }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td>{{ $log->ip_address ?? '—' }}</td>
                                    <td style="max-width: 320px; white-space: normal; font-size: 12px;">
                                        @if($auditOld !== '' || $auditNew !== '')
                                            @if($auditOld !== '')
                                                <span class="text-danger">{{ Str::limit($auditOld, 120) }}</span>
                                            @endif
                                            @if($auditOld !== '' && $auditNew !== '') → @endif
                                            @if($auditNew !== '')
                                                <span class="text-success">{{ Str::limit($auditNew, 120) }}</span>
                                            @endif
                                        @endif
                                        @if($auditCtx !== '')
                                            <div class="text-muted mt-1">{{ Str::limit($auditCtx, 200) }}</div>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="9" class="text-center text-muted py-4">No entries match your filters.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                @if($logs->hasPages())
                    <div class="card-footer">{{ $logs->links() }}</div>
                @endif
            </div>
        </div>
    </section>
</div>
@endsection
