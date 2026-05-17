@extends('layouts.crm_client_detail')
@section('title', 'Trust practice sequences')

@section('content')
@php
    use App\Services\TrustAccounting\TrustReceiptSequenceService;
@endphp
<div class="main-content">
    <section class="section">
        <div class="section-body">
            <div class="row mb-3">
                <div class="col-md-8">
                    <h4 class="mb-1"><i class="fas fa-sort-numeric-down text-secondary me-2"></i>Trust practice sequences</h4>
                    <p class="text-muted mb-0" style="font-size: 14px;">
                        Read-only counters used for practice-wide trust receipt numbers (<code>TR-{{ '{year}' }}-NNNNNN</code>)
                        and trust journal numbers (<code>TJ-{{ '{year}' }}-NNNNNN</code>). Victorian trust year begins 1&nbsp;April;
                        <strong>Trust year start</strong> is the calendar year of that 1&nbsp;April (e.g.&nbsp;2025 covers Apr&nbsp;2025–Mar&nbsp;2026).
                        Rows appear when the system first allocates a number for that year and sequence type.
                    </p>
                </div>
                <div class="col-md-4 text-md-end mt-2 mt-md-0">
                    <a href="{{ route('trust-accounting.reports.index') }}" class="btn btn-outline-primary btn-sm me-1">
                        <i class="fas fa-file-export me-1"></i> Reports
                    </a>
                    <a href="{{ route('trust-accounting.periods.index') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-lock me-1"></i> Period locks
                    </a>
                </div>
            </div>

            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped mb-0">
                            <thead>
                                <tr>
                                    <th>Trust year start</th>
                                    @if($hasSequenceType)
                                        <th>Type</th>
                                        <th>Description</th>
                                    @endif
                                    <th class="text-end">Last sequence no.</th>
                                    <th>Updated</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($sequences as $row)
                                    @php
                                        $type = $hasSequenceType ? (string) ($row->sequence_type ?? '') : TrustReceiptSequenceService::TYPE_RECEIPT;
                                        $typeLabel = match ($type) {
                                            TrustReceiptSequenceService::TYPE_JOURNAL => 'TJ — Trust journals',
                                            TrustReceiptSequenceService::TYPE_RECEIPT => 'TR — Trust receipts',
                                            default => $type,
                                        };
                                    @endphp
                                    <tr>
                                        <td>{{ $row->trust_year_start_year }}</td>
                                        @if($hasSequenceType)
                                            <td><code>{{ e($type) }}</code></td>
                                            <td class="small text-muted">{{ $typeLabel }}</td>
                                        @endif
                                        <td class="text-end font-monospace">{{ number_format((int) $row->last_sequence) }}</td>
                                        <td class="small">{{ $row->updated_at ?? '—' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ $hasSequenceType ? 5 : 3 }}" class="text-muted text-center py-4">
                                            No sequence rows yet. Counters are created when the first trust receipt or journal number is issued for a trust year.
                                        </td>
                                    </tr>
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
