@extends('layouts.crm_client_detail')
@section('title', 'Trust accounting periods')

@section('content')
<div class="main-content">
    <section class="section">
        <div class="section-body">
            <div class="server-error">
                @include('../Elements/flash-message')
            </div>

            <div class="row mb-3">
                <div class="col-md-8">
                    <h4 class="mb-1"><i class="fas fa-lock text-secondary me-2"></i>Trust accounting periods</h4>
                    <p class="text-muted mb-0" style="font-size: 14px;">
                        Lock a date range after reconciliation or end-of-month close. While locked, staff cannot create trust entries, void lines, or edit trust metadata for those transaction dates.
                    </p>
                </div>
                <div class="col-md-4 text-md-end mt-2 mt-md-0">
                    <a href="{{ route('trust-accounting.reports.index') }}" class="btn btn-outline-primary btn-sm me-1">
                        <i class="fas fa-file-export me-1"></i> Reports
                    </a>
                    <a href="{{ route('trust-accounting.withdrawal-authority-types.index') }}" class="btn btn-outline-primary btn-sm me-1">
                        <i class="fas fa-gavel me-1"></i> Rule 42 types
                    </a>
                    <a href="{{ route('trust-accounting.reconciliation.index') }}" class="btn btn-outline-primary btn-sm me-1">
                        <i class="fas fa-link me-1"></i> Reconciliation
                    </a>
                    <a href="{{ route('trust-accounting.practice-sequences.index') }}" class="btn btn-outline-secondary btn-sm me-1">
                        <i class="fas fa-sort-numeric-down me-1"></i> Sequences
                    </a>
                    <a href="{{ route('trust-accounting.audit-log.index') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-list-alt me-1"></i> Trust audit log
                    </a>
                </div>
            </div>

            <div class="row">
                <div class="col-lg-5 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Lock new period</h5>
                        </div>
                        <div class="card-body">
                            <form method="post" action="{{ route('trust-accounting.periods.store') }}">
                                @csrf
                                <div class="form-group mb-3">
                                    <label for="period_start">Period start</label>
                                    <input type="date" name="period_start" id="period_start" class="form-control @error('period_start') is-invalid @enderror"
                                           value="{{ old('period_start') }}" required>
                                    @error('period_start')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="form-group mb-3">
                                    <label for="period_end">Period end</label>
                                    <input type="date" name="period_end" id="period_end" class="form-control @error('period_end') is-invalid @enderror"
                                           value="{{ old('period_end') }}" required>
                                    @error('period_end')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="form-group mb-3">
                                    <label for="notes">Notes (optional)</label>
                                    <textarea name="notes" id="notes" class="form-control @error('notes') is-invalid @enderror" rows="3" maxlength="5000" placeholder="e.g. March 2026 bank reconciliation completed">{{ old('notes') }}</textarea>
                                    @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-lock me-1"></i> Lock period
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-lg-7 mb-4">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Existing periods</h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-striped mb-0">
                                    <thead>
                                        <tr>
                                            <th>Start</th>
                                            <th>End</th>
                                            <th>Status</th>
                                            <th>Locked</th>
                                            <th style="min-width: 200px;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    @forelse($periods as $p)
                                        <tr>
                                            <td>{{ $p->period_start->format('Y-m-d') }}</td>
                                            <td>{{ $p->period_end->format('Y-m-d') }}</td>
                                            <td>
                                                @if($p->status === 'locked')
                                                    <span class="badge bg-danger">Locked</span>
                                                @else
                                                    <span class="badge bg-secondary">Unlocked</span>
                                                @endif
                                            </td>
                                            <td style="font-size: 13px;">
                                                @if($p->locked_at)
                                                    {{ $p->locked_at->format('Y-m-d H:i') }}
                                                    @if($p->lockedBy)
                                                        <br><span class="text-muted">by {{ $p->lockedBy->first_name }} {{ $p->lockedBy->last_name }}</span>
                                                    @endif
                                                @else
                                                    —
                                                @endif
                                            </td>
                                            <td>
                                                @if($p->isLocked())
                                                    <form method="post" action="{{ route('trust-accounting.periods.unlock', $p) }}" class="unlock-period-form" onsubmit="return trustPeriodConfirmUnlock(this);">
                                                        @csrf
                                                        <input type="hidden" name="unlock_reason" value="">
                                                        <button type="submit" class="btn btn-sm btn-outline-warning">
                                                            <i class="fas fa-unlock me-1"></i> Unlock
                                                        </button>
                                                    </form>
                                                @elseif($p->unlocked_at)
                                                    <small class="text-muted d-block">{{ $p->unlocked_at->format('Y-m-d H:i') }}</small>
                                                    @if($p->unlock_reason)
                                                        <small class="text-muted">{{ Str::limit($p->unlock_reason, 80) }}</small>
                                                    @endif
                                                @else
                                                    —
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="text-center text-muted py-4">No periods defined yet.</td>
                                        </tr>
                                    @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        @if($periods->hasPages())
                            <div class="card-footer">{{ $periods->links() }}</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
<script>
function trustPeriodConfirmUnlock(form) {
    var reason = window.prompt('Unlock reason (required, min 10 characters). This is stored for auditors:', '');
    if (!reason || reason.trim().length < 10) {
        alert('A detailed unlock reason is required.');
        return false;
    }
    form.querySelector('input[name="unlock_reason"]').value = reason.trim();
    return window.confirm('Unlock this period? Trust transactions in this date range will become editable again.');
}
</script>
@endsection
