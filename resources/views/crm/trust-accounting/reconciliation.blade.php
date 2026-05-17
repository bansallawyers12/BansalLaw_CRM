@extends('layouts.crm_client_detail')
@section('title', 'Trust bank reconciliation')

@section('content')
<div class="main-content">
    <section class="section">
        <div class="section-body">
            @include('../Elements/flash-message')

            <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
                <div>
                    <h4 class="mb-1"><i class="fas fa-link text-secondary me-2"></i>Bank reconciliation</h4>
                    <p class="text-muted small mb-0">
                        Enter bank statement lines (positive amount = credit to trust account; negative = debit / payment). Match each line to the corresponding trust ledger row. Ledger lists are capped at 500 rows — narrow the date range if needed.
                    </p>
                </div>
                <div class="d-flex flex-wrap gap-1">
                    <a href="{{ route('trust-accounting.bank-accounts.index') }}" class="btn btn-outline-secondary btn-sm">Bank accounts</a>
                    <a href="{{ route('trust-accounting.reports.index') }}" class="btn btn-outline-secondary btn-sm">Reports</a>
                </div>
            </div>

            @if($accounts->isEmpty())
                <div class="alert alert-warning">
                    Add a <a href="{{ route('trust-accounting.bank-accounts.index') }}">trust bank account</a> before reconciling.
                </div>
            @else
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="get" action="{{ route('trust-accounting.reconciliation.index') }}" class="row g-2 align-items-end">
                            <div class="col-md-4">
                                <label class="form-label small mb-0">Bank account</label>
                                <select name="trust_bank_account_id" class="form-select form-select-sm" required>
                                    @foreach($accounts as $acc)
                                        <option value="{{ $acc->id }}" @selected($account && (int)$account->id === (int)$acc->id)>
                                            {{ $acc->name }}@if(!$acc->is_active) (inactive)@endif
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small mb-0">From</label>
                                <input type="date" name="from_date" class="form-control form-control-sm" value="{{ $from }}">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small mb-0">To</label>
                                <input type="date" name="to_date" class="form-control form-control-sm" value="{{ $to }}">
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary btn-sm w-100">Run</button>
                            </div>
                        </form>
                    </div>
                </div>
            @endif

            @if($account)
                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="card h-100"><div class="card-body py-3">
                            <div class="text-muted small">Ledger deposits (period)</div>
                            <div class="fs-5 font-monospace">${{ number_format($ledgerMovement['deposits'], 2) }}</div>
                        </div></div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card h-100"><div class="card-body py-3">
                            <div class="text-muted small">Ledger payments (period)</div>
                            <div class="fs-5 font-monospace">${{ number_format($ledgerMovement['payments'], 2) }}</div>
                        </div></div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card h-100"><div class="card-body py-3">
                            <div class="text-muted small">Bank credits − debits (lines in range)</div>
                            <div class="fs-5 font-monospace">${{ number_format($bankNet, 2) }}</div>
                        </div></div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card h-100 border-{{ abs($movementVariance) < 0.01 ? 'success' : 'warning' }}"><div class="card-body py-3">
                            <div class="text-muted small">Movement variance (ledger net − bank net)</div>
                            <div class="fs-5 font-monospace">${{ number_format($movementVariance, 2) }}</div>
                        </div></div>
                    </div>
                </div>
                <div class="alert alert-light border small mb-4 py-2">
                    <strong>Trial balance total</strong> (all matters, non-voided trust ledger, as at {{ $to }}):
                    <span class="font-monospace">${{ number_format($trialBalanceTotal, 2) }}</span>
                    <span class="text-muted">— compare to bank balance per ADI statement when completing Rule 48 reconciliation.</span>
                </div>

                <div class="card mb-4">
                    <div class="card-header"><h5 class="mb-0">Add bank line</h5></div>
                    <div class="card-body">
                        <form method="post" action="{{ route('trust-accounting.reconciliation.lines.store') }}" class="row g-2 align-items-end">
                            @csrf
                            <input type="hidden" name="trust_bank_account_id" value="{{ $account->id }}">
                            <input type="hidden" name="from_date" value="{{ $from }}">
                            <input type="hidden" name="to_date" value="{{ $to }}">
                            <div class="col-md-2">
                                <label class="form-label small mb-0">Value date</label>
                                <input type="date" name="value_date" class="form-control form-control-sm" value="{{ old('value_date', $to) }}" required>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small mb-0">Amount <span class="text-muted">(+ in / − out)</span></label>
                                <input type="text" name="amount" class="form-control form-control-sm" inputmode="decimal" placeholder="-1200.00" value="{{ old('amount') }}" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small mb-0">Narrative</label>
                                <input type="text" name="narrative" class="form-control form-control-sm" value="{{ old('narrative') }}" maxlength="5000">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small mb-0">Bank reference</label>
                                <input type="text" name="bank_reference" class="form-control form-control-sm" value="{{ old('bank_reference') }}" maxlength="500">
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary btn-sm w-100">Add line</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Statement lines</h5>
                        <span class="small text-muted">Credits ${{ number_format($bankCredits, 2) }} · Debits ${{ number_format($bankDebits, 2) }}</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm table-striped mb-0 align-middle">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th class="text-end">Amount</th>
                                        <th>Narrative</th>
                                        <th>Ref</th>
                                        <th>Match</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($lines as $line)
                                        <tr>
                                            <td class="text-nowrap">{{ $line->value_date->format('Y-m-d') }}</td>
                                            <td class="text-end font-monospace">${{ number_format((float) $line->amount, 2) }}</td>
                                            <td>{{ \Illuminate\Support\Str::limit($line->narrative ?? '—', 80) }}</td>
                                            <td class="small font-monospace">{{ $line->bank_reference ?? '—' }}</td>
                                            <td>
                                                @if($line->matched_account_client_receipt_id)
                                                    <span class="badge bg-success">Ledger #{{ $line->matched_account_client_receipt_id }}</span>
                                                    @if($line->matchedReceipt)
                                                        <span class="small text-muted">{{ $line->matchedReceipt->trans_no }}</span>
                                                    @endif
                                                @else
                                                    <span class="badge bg-secondary">Unmatched</span>
                                                @endif
                                            </td>
                                            <td class="text-end text-nowrap">
                                                @if(!$line->matched_account_client_receipt_id)
                                                    <form method="post" action="{{ route('trust-accounting.reconciliation.lines.destroy', $line) }}" class="d-inline" onsubmit="return confirm('Delete this bank line?');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <input type="hidden" name="trust_bank_account_id" value="{{ $account->id }}">
                                                        <button type="submit" class="btn btn-outline-danger btn-sm py-0">Delete</button>
                                                    </form>
                                                @else
                                                    <form method="post" action="{{ route('trust-accounting.reconciliation.unmatch') }}" class="d-inline">
                                                        @csrf
                                                        <input type="hidden" name="statement_line_id" value="{{ $line->id }}">
                                                        <input type="hidden" name="trust_bank_account_id" value="{{ $account->id }}">
                                                        <input type="hidden" name="from_date" value="{{ $from }}">
                                                        <input type="hidden" name="to_date" value="{{ $to }}">
                                                        <button type="submit" class="btn btn-outline-warning btn-sm py-0">Unmatch</button>
                                                    </form>
                                                @endif
                                            </td>
                                        </tr>
                                        @if(!$line->matched_account_client_receipt_id)
                                            <tr class="bg-light">
                                                <td colspan="6" class="small py-3">
                                                    <form method="post" action="{{ route('trust-accounting.reconciliation.match') }}" class="row g-2 align-items-center">
                                                        @csrf
                                                        <input type="hidden" name="statement_line_id" value="{{ $line->id }}">
                                                        <input type="hidden" name="trust_bank_account_id" value="{{ $account->id }}">
                                                        <input type="hidden" name="from_date" value="{{ $from }}">
                                                        <input type="hidden" name="to_date" value="{{ $to }}">
                                                        <div class="col-md-5">
                                                            <label class="small mb-0">Match to ledger row</label>
                                                            <select name="receipt_id" class="form-select form-select-sm" required>
                                                                <option value="">— Select —</option>
                                                                @if((float) $line->amount > 0)
                                                                    @php $opts = $unmatchedDeposits->filter(fn ($r) => round((float) $r->deposit_amount - (float) $line->amount, 2) === 0.0); @endphp
                                                                    @forelse($opts as $r)
                                                                        <option value="{{ $r->id }}">{{ $r->trans_no }} · {{ $r->trans_date }} · ref {{ $r->client_ref }} · ${{ number_format((float) $r->deposit_amount, 2) }}</option>
                                                                    @empty
                                                                        <option value="" disabled>No unmatched deposits with this amount</option>
                                                                    @endforelse
                                                                @elseif((float) $line->amount < 0)
                                                                    @php $opts = $unmatchedPayments->filter(fn ($r) => round((float) $r->withdraw_amount + (float) $line->amount, 2) === 0.0); @endphp
                                                                    @forelse($opts as $r)
                                                                        <option value="{{ $r->id }}">{{ $r->trans_no }} · {{ $r->trans_date }} · ref {{ $r->client_ref }} · ${{ number_format((float) $r->withdraw_amount, 2) }}</option>
                                                                    @empty
                                                                        <option value="" disabled>No unmatched payments with this amount</option>
                                                                    @endforelse
                                                                @else
                                                                    <option value="" disabled>Invalid line amount (must be non-zero)</option>
                                                                @endif
                                                            </select>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <label class="small mb-0">Notes <span class="text-muted">(optional)</span></label>
                                                            <input type="text" name="match_notes" class="form-control form-control-sm" maxlength="2000">
                                                        </div>
                                                        <div class="col-md-3">
                                                            <button type="submit" class="btn btn-success btn-sm mt-3 mt-md-4">Match</button>
                                                        </div>
                                                    </form>
                                                </td>
                                            </tr>
                                        @endif
                                    @empty
                                        <tr><td colspan="6" class="text-center text-muted py-4">No bank lines in this date range.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-6 mb-4">
                        <div class="card">
                            <div class="card-header"><h6 class="mb-0">Unmatched ledger deposits (period)</h6></div>
                            <div class="card-body p-0" style="max-height: 280px; overflow-y: auto;">
                                <table class="table table-sm mb-0">
                                    <thead class="sticky-top bg-white"><tr><th>Ref</th><th>No.</th><th class="text-end">Amount</th></tr></thead>
                                    <tbody>
                                        @forelse($unmatchedDeposits as $r)
                                            <tr>
                                                <td class="small">{{ $r->client_ref }}</td>
                                                <td class="small font-monospace">{{ $r->trans_no }}</td>
                                                <td class="text-end font-monospace small">${{ number_format((float) $r->deposit_amount, 2) }}</td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="3" class="text-muted small p-3">None.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6 mb-4">
                        <div class="card">
                            <div class="card-header"><h6 class="mb-0">Unmatched ledger payments (period)</h6></div>
                            <div class="card-body p-0" style="max-height: 280px; overflow-y: auto;">
                                <table class="table table-sm mb-0">
                                    <thead class="sticky-top bg-white"><tr><th>Ref</th><th>No.</th><th class="text-end">Amount</th></tr></thead>
                                    <tbody>
                                        @forelse($unmatchedPayments as $r)
                                            <tr>
                                                <td class="small">{{ $r->client_ref }}</td>
                                                <td class="small font-monospace">{{ $r->trans_no }}</td>
                                                <td class="text-end font-monospace small">${{ number_format((float) $r->withdraw_amount, 2) }}</td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="3" class="text-muted small p-3">None.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </section>
</div>
@endsection
