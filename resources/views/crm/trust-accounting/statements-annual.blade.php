@extends('layouts.crm_client_detail')
@section('title', 'Annual trust statements (30 June)')

@section('content')
<div class="main-content">
    <section class="section">
        <div class="section-body">
            @include('../Elements/flash-message')
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
                <div>
                    <h4 class="mb-1">Annual trust statements — 30 June</h4>
                    <p class="text-muted small mb-0">Rule 52(4)(c) — matters with trust balances as at the selected date. Exempt if zero balance and no activity in 12 months.</p>
                </div>
                <a href="{{ route('trust-accounting.statements.index') }}" class="btn btn-outline-secondary btn-sm">On-demand statements</a>
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <form method="get" class="row g-2 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label small mb-0">As at (default 30 June)</label>
                            <input type="date" name="as_at" class="form-control form-control-sm" value="{{ $asAt }}">
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary btn-sm w-100">Run</button>
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
                                    <th>Last statement sent</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                            @forelse($matters as $row)
                                <tr>
                                    <td>{{ $row->client_ref }}</td>
                                    <td>{{ $row->client_unique_matter_no ?? '—' }}</td>
                                    <td>{{ trim(($row->first_name ?? '') . ' ' . ($row->last_name ?? '')) }}</td>
                                    <td class="text-end font-monospace">${{ number_format((float) $row->balance, 2) }}</td>
                                    <td class="small">{{ $row->trust_last_statement_sent_at ? \Carbon\Carbon::parse($row->trust_last_statement_sent_at)->format('d/m/Y') : '—' }}</td>
                                    <td>
                                        @if($row->exempt)
                                            <span class="badge bg-secondary">Exempt (Rule 52(5))</span>
                                        @else
                                            <span class="badge bg-warning text-dark">Statement due</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        @if(!$row->exempt)
                                        <a class="btn btn-outline-primary btn-sm" target="_blank" href="{{ route('trust-accounting.statements.generate', ['client_id' => $row->client_id, 'matter_id' => $row->client_matter_id, 'to_date' => $asAt]) }}">PDF</a>
                                        <form method="post" action="{{ route('trust-accounting.statements.mark-sent') }}" class="d-inline">
                                            @csrf
                                            <input type="hidden" name="matter_id" value="{{ $row->client_matter_id }}">
                                            <button type="submit" class="btn btn-outline-secondary btn-sm">Mark sent</button>
                                        </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="text-center text-muted py-4">No matters with trust balances as at this date.</td></tr>
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
