@extends('layouts.crm_client_detail')
@section('title', 'Staff Access Audit')

@push('styles')
<style>
.adminconsole-staff-access-audit .sa-audit-head p { max-width: 40rem; }
.adminconsole-staff-access-audit .sa-filters {
    padding: 0.85rem 1rem; margin-bottom: 1rem;
    border: 1px solid #d7e3f2; border-radius: 10px; background: #f8fafc;
}
</style>
@endpush

@section('content')
@php
    $actionLabels = [
        'profile_ensured_restricted' => 'Profile set restricted',
        'profile_updated' => 'Profile updated',
        'grant_created' => 'Grant created',
        'grant_updated' => 'Grant updated',
        'grant_revoked' => 'Grant revoked',
    ];
    $staffName = static function ($staff) {
        if (! $staff) {
            return '—';
        }
        $name = trim(($staff->first_name ?? '') . ' ' . ($staff->last_name ?? ''));

        return $name !== '' ? $name : ($staff->email ?? ('#' . $staff->id));
    };
@endphp
<div class="main-content adminconsole-features adminconsole-staff-access-audit">
    <section class="section">
        <div class="section-body">
            <div class="server-error">
                @include('../Elements/flash-message')
            </div>
            <div class="row">
                <div class="col-3 col-md-3 col-lg-3">
                    @include('../Elements/CRM/setting')
                </div>
                <div class="col-9 col-md-9 col-lg-9">
                    <div class="card">
                        <div class="card-header sa-audit-head d-flex flex-wrap align-items-center justify-content-between gap-2">
                            <div>
                                <h4 class="mb-1">Staff Access Audit</h4>
                                <p class="text-muted small mb-0">Who changed restricted profiles and grants, and when.</p>
                            </div>
                            <div class="d-flex gap-2">
                                <a href="{{ route('adminconsole.system.staff-access.index') }}" class="btn btn-outline-primary btn-sm">
                                    <i class="fa-solid fa-arrow-left"></i> Staff Access
                                </a>
                            </div>
                        </div>
                        <div class="card-body">
                            @if(!empty($tableMissing))
                                <div class="alert alert-warning mb-0">Audit table is not available. Run the restricted-access migration locally.</div>
                            @else
                                <form method="get" action="{{ route('adminconsole.system.staff-access.audit') }}" class="row g-2 mb-3 align-items-end sa-filters">
                                    <div class="col-md-3">
                                        <label class="form-label small mb-1">Target staff</label>
                                        <select name="target_staff_id" class="form-select form-select-sm">
                                            <option value="0">All</option>
                                            @foreach($staffOptions as $opt)
                                                <option value="{{ $opt->id }}" @selected((int)($filters['target_staff_id'] ?? 0) === (int)$opt->id)>
                                                    {{ trim(($opt->first_name ?? '') . ' ' . ($opt->last_name ?? '')) }} ({{ $opt->email }})
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small mb-1">Actor</label>
                                        <select name="actor_staff_id" class="form-select form-select-sm">
                                            <option value="0">All</option>
                                            @foreach($staffOptions as $opt)
                                                <option value="{{ $opt->id }}" @selected((int)($filters['actor_staff_id'] ?? 0) === (int)$opt->id)>
                                                    {{ trim(($opt->first_name ?? '') . ' ' . ($opt->last_name ?? '')) }} ({{ $opt->email }})
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small mb-1">Action</label>
                                        <select name="action" class="form-select form-select-sm">
                                            <option value="">All</option>
                                            @foreach($actionOptions as $action)
                                                <option value="{{ $action }}" @selected(($filters['action'] ?? '') === $action)>
                                                    {{ $actionLabels[$action] ?? $action }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small mb-1">From</label>
                                        <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="form-control form-control-sm">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small mb-1">To</label>
                                        <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="form-control form-control-sm">
                                    </div>
                                    <div class="col-12 d-flex gap-2">
                                        <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                                        <a href="{{ route('adminconsole.system.staff-access.audit') }}" class="btn btn-light border btn-sm">Clear</a>
                                    </div>
                                </form>

                                <div class="table-responsive">
                                    <table class="table table-sm table-striped table-hover align-middle mb-0">
                                        <thead>
                                            <tr>
                                                <th>When</th>
                                                <th>Actor</th>
                                                <th>Target</th>
                                                <th>Action</th>
                                                <th>Resource</th>
                                                <th>IP</th>
                                                <th></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($logs as $row)
                                                <tr>
                                                    <td class="text-nowrap">{{ optional($row->created_at)->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</td>
                                                    <td>{{ $staffName($row->actor) }}</td>
                                                    <td>
                                                        @if($row->target_staff_id)
                                                            <a href="{{ route('adminconsole.system.staff-access.edit', $row->target_staff_id) }}">{{ $staffName($row->target) }}</a>
                                                        @else
                                                            —
                                                        @endif
                                                    </td>
                                                    <td><span class="badge bg-light text-dark border">{{ $actionLabels[$row->action] ?? $row->action }}</span></td>
                                                    <td class="small">
                                                        @if($row->resource_type)
                                                            {{ $row->resource_type }} #{{ $row->resource_id }}
                                                        @else
                                                            —
                                                        @endif
                                                    </td>
                                                    <td class="small text-muted">{{ $row->ip_address ?: '—' }}</td>
                                                    <td class="text-end">
                                                        <a href="{{ route('adminconsole.system.staff-access.audit.show', $row->id) }}" class="btn btn-sm btn-outline-primary">Detail</a>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="7" class="text-center text-muted py-4">No audit entries match these filters.</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>

                                @if(method_exists($logs, 'links'))
                                    <div class="mt-3">{{ $logs->links() }}</div>
                                @endif
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
@endsection
