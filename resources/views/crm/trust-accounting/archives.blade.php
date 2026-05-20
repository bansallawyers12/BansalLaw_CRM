@extends('layouts.crm_client_detail')
@section('title', 'Trust monthly archives')

@section('content')
<div class="main-content">
    <section class="section">
        <div class="section-body">
            @include('../Elements/flash-message')
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
                <div>
                    <h4 class="mb-1">Monthly report archives</h4>
                    <p class="text-muted small mb-0">Rule 38 — immutable month-end copies of receipts journal, payments journal and trial balance.</p>
                </div>
                <a href="{{ route('trust-accounting.reports.index') }}" class="btn btn-outline-secondary btn-sm">Reports hub</a>
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <form method="post" action="{{ route('trust-accounting.archives.store') }}" class="row g-2 align-items-end">
                        @csrf
                        <div class="col-md-2">
                            <label class="form-label small mb-0">Year</label>
                            <input type="number" name="period_year" class="form-control form-control-sm" value="{{ old('period_year', now()->year) }}" required min="2000" max="2100">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small mb-0">Month</label>
                            <input type="number" name="period_month" class="form-control form-control-sm" value="{{ old('period_month', now()->month) }}" required min="1" max="12">
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-primary btn-sm">Archive month reports</button>
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
                                    <th>Period</th>
                                    <th>Type</th>
                                    <th>Prepared</th>
                                    <th>By</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                            @forelse($archives as $archive)
                                <tr>
                                    <td>{{ $archive->period_year }}-{{ str_pad((string) $archive->period_month, 2, '0', STR_PAD_LEFT) }}</td>
                                    <td>{{ str_replace('_', ' ', $archive->archive_type) }}</td>
                                    <td>{{ $archive->prepared_at ? \Carbon\Carbon::parse($archive->prepared_at)->format('d/m/Y H:i') : '—' }}</td>
                                    <td>{{ $archive->staff_name ?? '—' }}</td>
                                    <td class="text-end">
                                        @if($archive->file_document_id)
                                        <a href="{{ route('trust-accounting.archives.download', $archive->id) }}" class="btn btn-outline-secondary btn-sm">Download</a>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted py-4">No archives yet. Lock a period and archive the month-end reports.</td></tr>
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
