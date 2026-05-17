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
                    <h4 class="mb-1"><i class="fas fa-clipboard-list text-secondary me-2"></i>Trust audit log</h4>
                    <p class="text-muted mb-0" style="font-size: 14px;">Append-only record of trust ledger voids, metadata edits, and period lock/unlock actions.</p>
                </div>
                <div class="col-md-4 text-md-end mt-2 mt-md-0">
                    <a href="{{ route('trust-accounting.reports.index') }}" class="btn btn-outline-primary btn-sm me-1">Reports</a>
                    <a href="{{ route('trust-accounting.periods.index') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-lock me-1"></i> Period locks
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
                                <option value="account_client_receipts" @selected(request('table_name') === 'account_client_receipts')>Ledger (receipts)</option>
                                <option value="trust_accounting_periods" @selected(request('table_name') === 'trust_accounting_periods')>Periods</option>
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
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary btn-sm w-100">Filter</button>
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
                                <tr>
                                    <td>{{ $log->id }}</td>
                                    <td>{{ \Carbon\Carbon::parse($log->created_at)->format('Y-m-d H:i:s') }}</td>
                                    <td>{{ $log->table_name }}</td>
                                    <td>{{ $log->row_id }}</td>
                                    <td>{{ $log->event }}</td>
                                    <td>{{ $log->field_name ?? '—' }}</td>
                                    <td>
                                        @if($log->performer_name)
                                            {{ trim($log->performer_name) }}
                                        @elseif($log->performed_by)
                                            #{{ $log->performed_by }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td>{{ $log->ip_address ?? '—' }}</td>
                                    <td style="max-width: 320px; white-space: normal; font-size: 12px;">
                                        @if($log->old_value || $log->new_value)
                                            <span class="text-danger">{{ Str::limit($log->old_value, 120) }}</span>
                                            @if($log->old_value && $log->new_value) → @endif
                                            <span class="text-success">{{ Str::limit($log->new_value, 120) }}</span>
                                        @endif
                                        @if($log->context)
                                            <div class="text-muted mt-1">{{ Str::limit($log->context, 200) }}</div>
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
