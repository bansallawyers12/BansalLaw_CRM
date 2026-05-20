@extends('layouts.crm_client_detail')
@section('title', 'Overdrawn ledger report')

@section('content')
<div class="main-content">
    <section class="section">
        <div class="section-body">
            @include('../Elements/flash-message')
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
                <div>
                    <h4 class="mb-1">Overdrawn ledger report</h4>
                    <p class="text-muted small mb-0">Rule 40 — ledger rows with negative running balance and audit log entries for overdrawn transactions.</p>
                </div>
                <a href="{{ route('trust-accounting.reports.index') }}" class="btn btn-outline-secondary btn-sm">All reports</a>
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <form method="get" class="row g-2 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label small mb-0">From</label>
                            <input type="date" name="from_date" class="form-control form-control-sm" value="{{ $from }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small mb-0">To</label>
                            <input type="date" name="to_date" class="form-control form-control-sm" value="{{ $to }}">
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary btn-sm w-100">Run</button>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" name="export" value="csv" class="btn btn-outline-secondary btn-sm w-100">CSV</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-sm mb-0">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Ref</th>
                                    <th>Type</th>
                                    <th>Client</th>
                                    <th>Matter</th>
                                    <th class="text-end">Withdrawal</th>
                                    <th class="text-end">Balance</th>
                                </tr>
                            </thead>
                            <tbody>
                            @forelse($rows as $row)
                                <tr>
                                    <td>{{ $row->trans_date }}</td>
                                    <td>{{ $row->trans_no }}</td>
                                    <td>{{ $row->client_fund_ledger_type }}</td>
                                    <td>{{ $row->client_ref }}</td>
                                    <td>{{ $row->client_unique_matter_no ?? '—' }}</td>
                                    <td class="text-end font-monospace">${{ number_format((float) $row->withdraw_amount, 2) }}</td>
                                    <td class="text-end font-monospace text-danger">${{ number_format((float) $row->balance_amount, 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="text-center text-muted py-4">No overdrawn ledger rows in this period.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
@endsection
