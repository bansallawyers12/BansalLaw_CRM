@extends('layouts.crm_client_detail')
@section('title', 'Trust trial balance')

@section('content')
<div class="main-content">
    <section class="section">
        <div class="section-body">
            @include('../Elements/flash-message')

            <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
                <div>
                    <h4 class="mb-1">Trust trial balance</h4>
                    <p class="text-muted small mb-0">Non-voided trust ledger only. Matters with no rows are omitted unless you include zero balances.</p>
                </div>
                <a href="{{ route('trust-accounting.reports.index') }}" class="btn btn-outline-secondary btn-sm">All reports</a>
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <form method="get" action="{{ route('trust-accounting.reports.trial-balance') }}" class="row g-2 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label small mb-0">As at</label>
                            <input type="date" name="as_at" class="form-control form-control-sm" value="{{ $asAt }}">
                        </div>
                        <div class="col-md-3 form-check mt-4 pt-1">
                            <input type="checkbox" class="form-check-input" name="include_zero" id="include_zero" value="1" @checked($includeZero)>
                            <label class="form-check-label small" for="include_zero">Include zero balances</label>
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
                                    <th>Client ref</th>
                                    <th>Matter</th>
                                    <th>Name</th>
                                    <th class="text-end">Balance</th>
                                </tr>
                            </thead>
                            <tbody>
                            @forelse($balances as $row)
                                <tr>
                                    <td>{{ $row->client_ref }}</td>
                                    <td>{{ $row->client_unique_matter_no ?? '—' }}</td>
                                    <td>{{ trim(($row->first_name ?? '') . ' ' . ($row->last_name ?? '')) }}</td>
                                    <td class="text-end font-monospace">${{ number_format((float) $row->balance, 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted py-4">No rows match.</td></tr>
                            @endforelse
                            </tbody>
                            @if($balances->count())
                                <tfoot>
                                    <tr class="fw-bold">
                                        <td colspan="3" class="text-end">Total</td>
                                        <td class="text-end font-monospace">${{ number_format($total, 2) }}</td>
                                    </tr>
                                </tfoot>
                            @endif
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
@endsection
