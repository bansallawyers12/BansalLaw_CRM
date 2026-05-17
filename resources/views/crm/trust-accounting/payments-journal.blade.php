@extends('layouts.crm_client_detail')
@section('title', 'Trust payments journal')

@section('content')
<div class="main-content">
    <section class="section">
        <div class="section-body">
            @include('../Elements/flash-message')

            <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
                <div>
                    <h4 class="mb-1">Trust payments journal</h4>
                    <p class="text-muted small mb-0">Rows with funds out for the period (payments, fee transfers, disbursements, refunds, etc.).@if(!empty($rule42ColumnsEnabled)) Rule 42 withdrawal authority is shown for fee transfers where captured.@endif</p>
                </div>
                <a href="{{ route('trust-accounting.reports.index') }}" class="btn btn-outline-secondary btn-sm">All reports</a>
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <form method="get" action="{{ route('trust-accounting.reports.payments-journal') }}" class="row g-2 align-items-end">
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

            @php
                $rjCols = !empty($rule42ColumnsEnabled) ? 14 : 9;
            @endphp
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
                                    @if(!empty($rule42ColumnsEnabled))
                                        <th>Rule 42 type</th>
                                        <th>Notice</th>
                                        <th>Rule 42 notes</th>
                                        <th>Override</th>
                                        <th>Override reason</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                            @forelse($entries as $row)
                                @php
                                    $ovTxt = !empty($rule42ColumnsEnabled)
                                        ? \App\Services\TrustAccounting\TrustWithdrawalAuthorityService::supervisorOverrideYesNoEmpty($row->rule42_supervisor_override ?? null)
                                        : '';
                                @endphp
                                <tr>
                                    <td>{{ $row->trans_date }}</td>
                                    <td class="font-monospace small">{{ $row->trans_no }}</td>
                                    <td>{{ $row->client_fund_ledger_type }}</td>
                                    <td>{{ $row->client_ref }}</td>
                                    <td>{{ $row->client_unique_matter_no ?? '—' }}</td>
                                    <td style="max-width:220px;"><span class="small">{{ Str::limit($row->description, 80) }}</span></td>
                                    <td class="text-end font-monospace">${{ number_format((float) $row->withdraw_amount, 2) }}</td>
                                    <td class="small">{{ $row->payment_method ?? '—' }}</td>
                                    <td class="small">{{ $row->invoice_no ?? '—' }}</td>
                                    @if(!empty($rule42ColumnsEnabled))
                                        <td class="small">{{ $row->rule42_authority_type_label ?? '—' }}</td>
                                        <td class="small text-nowrap">{{ $row->rule42_notice_given_date ?? '—' }}</td>
                                        <td class="small" style="max-width:160px;">{{ $row->rule42_authority_notes ? Str::limit($row->rule42_authority_notes, 60) : '—' }}</td>
                                        <td class="small text-nowrap">{{ $ovTxt === '' ? '—' : $ovTxt }}</td>
                                        <td class="small" style="max-width:140px;">{{ !empty($row->rule42_override_reason) ? Str::limit($row->rule42_override_reason, 50) : '—' }}</td>
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
