@extends('layouts.crm_client_detail')
@section('title', 'Trust receipts journal')

@section('content')
@php
    $payerColumnsEnabled = $payerColumnsEnabled ?? false;
@endphp
<div class="main-content">
    <section class="section">
        <div class="section-body">
            @include('../Elements/flash-message')

            <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
                <div>
                    <h4 class="mb-1">Trust receipts journal</h4>
                    <p class="text-muted small mb-0">Rows with funds in for the period (allocated deposits show invoice reference where present). Export CSV for the full filtered set.</p>
                </div>
                <a href="{{ route('trust-accounting.reports.index') }}" class="btn btn-outline-secondary btn-sm">All reports</a>
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <form method="get" action="{{ route('trust-accounting.reports.receipts-journal') }}" class="row g-2 align-items-end">
                        <div class="col-md-2">
                            <label class="form-label small mb-0">From</label>
                            <input type="date" name="from_date" class="form-control form-control-sm" value="{{ $from }}">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small mb-0">To</label>
                            <input type="date" name="to_date" class="form-control form-control-sm" value="{{ $to }}">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small mb-0">Client id (admins.id)</label>
                            <input type="number" name="client_id" class="form-control form-control-sm" value="{{ request('client_id') }}" min="1" placeholder="Optional">
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

            <p class="small text-muted">Page subtotal (this page only): <strong>${{ number_format($sumPage, 2) }}</strong></p>

            @php $rjCols = 9 + ($payerColumnsEnabled ? 3 : 0); @endphp
            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-sm mb-0">
                            <thead>
                                <tr>
                                    <th>Trans date</th>
                                    <th>No.</th>
                                    <th>Type</th>
                                    <th>Client</th>
                                    <th>Matter</th>
                                    <th>Description</th>
                                    <th class="text-end">Amount</th>
                                    <th>Method</th>
                                    <th>Invoice</th>
                                    @if($payerColumnsEnabled)
                                        <th>Payer</th>
                                        <th>Bank ref</th>
                                        <th>Banking date</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                            @forelse($entries as $row)
                                <tr>
                                    <td>{{ $row->trans_date }}</td>
                                    <td class="font-monospace small">{{ $row->trans_no }}</td>
                                    <td>{{ $row->client_fund_ledger_type }}</td>
                                    <td>{{ $row->client_ref }}</td>
                                    <td>{{ $row->client_unique_matter_no ?? '—' }}</td>
                                    <td style="max-width:220px;"><span class="small">{{ Str::limit((string) ($row->description ?? ''), 80) }}</span></td>
                                    <td class="text-end font-monospace">${{ number_format((float) $row->deposit_amount, 2) }}</td>
                                    <td class="small">{{ $row->payment_method ?? '—' }}</td>
                                    <td class="small">{{ $row->invoice_no ?? '—' }}</td>
                                    @if($payerColumnsEnabled)
                                        <td class="small">{{ $row->payer_name ?? '—' }}</td>
                                        <td class="small font-monospace">{{ $row->bank_deposit_reference ?? '—' }}</td>
                                        <td class="small text-nowrap">{{ $row->banking_date ?? '—' }}</td>
                                    @endif
                                </tr>
                            @empty
                                <tr><td colspan="{{ $rjCols }}" class="text-center text-muted py-4">No rows.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                @if($entries->hasPages())
                    <div class="card-footer">{{ $entries->links() }}</div>
                @endif
            </div>
        </div>
    </section>
</div>
@endsection
