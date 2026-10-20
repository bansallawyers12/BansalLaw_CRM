@extends('layouts.crm_client_detail')
@section('title', 'Staff Access')

@push('styles')
<style>
.adminconsole-staff-access .sa-toolbar {
    display: flex; flex-wrap: wrap; gap: 0.6rem; align-items: stretch; margin-bottom: 1rem;
}
.adminconsole-staff-access .sa-toolbar .form-control,
.adminconsole-staff-access .sa-toolbar .form-select { min-width: 0; }
.adminconsole-staff-access .sa-toolbar__search { flex: 1 1 220px; }
.adminconsole-staff-access .sa-toolbar__mode { flex: 0 1 180px; }
.adminconsole-staff-access .sa-name {
    display: flex; align-items: center; gap: 0.65rem; min-width: 0;
}
.adminconsole-staff-access .sa-avatar {
    width: 34px; height: 34px; border-radius: 9px; flex-shrink: 0;
    display: grid; place-items: center; font-size: 0.75rem; font-weight: 700; color: #fff;
    background: linear-gradient(135deg, var(--navy, #1e3a5f) 0%, var(--sidebar-active, #3a6fa8) 100%);
}
.adminconsole-staff-access .sa-name__text { min-width: 0; }
.adminconsole-staff-access .sa-name__text strong { display: block; line-height: 1.2; }
.adminconsole-staff-access .sa-name__text small { color: #64748b; }
.adminconsole-staff-access .sa-card-head p { max-width: 42rem; }
.adminconsole-staff-access .sa-grant-breakdown {
    display: flex; flex-wrap: wrap; gap: 0.3rem; max-width: 22rem;
}
.adminconsole-staff-access .sa-grant-pill {
    display: inline-flex; align-items: center; gap: 0.25rem;
    padding: 0.15rem 0.45rem; border-radius: 999px; font-size: 0.72rem;
    background: #f1f5f9; color: #334155; border: 1px solid #e2e8f0; white-space: nowrap;
}
.adminconsole-staff-access .sa-grant-pill strong { font-weight: 700; color: #0f172a; }
.adminconsole-staff-access .sa-grant-pill.is-zero { opacity: 0.45; }
</style>
@endpush

@section('content')
<div class="main-content adminconsole-features adminconsole-staff-access">
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
                        <div class="card-header sa-card-head d-flex flex-wrap align-items-start justify-content-between gap-2">
                            <div>
                                <h4 class="mb-1">Staff Access</h4>
                                <p class="text-muted small mb-0">Super Admin only — see who can access which clients, leads, matters, and related emails; set restricted mode and grants.</p>
                            </div>
                            <a href="{{ route('adminconsole.system.staff-access.audit') }}" class="btn btn-outline-secondary btn-sm">
                                <i class="fa-solid fa-clock-rotate-left"></i> Audit log
                            </a>
                        </div>
                        <div class="card-body">
                            @if(empty($featureEnabled))
                                <div class="alert alert-warning">
                                    Restricted access is disabled. Set <code>CRM_RESTRICTED_ACCESS_ENABLED=true</code> in local <code>.env</code> to manage profiles and grants.
                                </div>
                            @endif

                            <form method="get" action="{{ route('adminconsole.system.staff-access.index') }}" class="sa-toolbar">
                                <input type="search" name="q" value="{{ $search ?? '' }}" class="form-control sa-toolbar__search" placeholder="Search name or email" aria-label="Search staff">
                                <select name="mode" class="form-select sa-toolbar__mode" aria-label="Filter by access mode">
                                    <option value="" @selected(($modeFilter ?? '') === '')>All modes</option>
                                    <option value="restricted" @selected(($modeFilter ?? '') === 'restricted')>Restricted</option>
                                    <option value="full" @selected(($modeFilter ?? '') === 'full')>Full</option>
                                    <option value="default" @selected(($modeFilter ?? '') === 'default')>No profile yet</option>
                                </select>
                                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-search"></i></button>
                                @if(!empty($search) || !empty($modeFilter))
                                    <a href="{{ route('adminconsole.system.staff-access.index') }}" class="btn btn-light border">Clear</a>
                                @endif
                            </form>

                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>Staff</th>
                                            <th>Role</th>
                                            <th>Access mode</th>
                                            <th>Can see (grants)</th>
                                            <th class="text-end">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($lists as $row)
                                            @php
                                                $mode = $row->accessProfile->access_mode ?? null;
                                                $modeLabel = $mode === 'restricted' ? 'Restricted' : ($mode === 'full' ? 'Full' : 'Default');
                                                $name = trim(($row->first_name ?? '') . ' ' . ($row->last_name ?? '')) ?: 'Staff';
                                                $initials = strtoupper(substr((string) ($row->first_name ?? 'S'), 0, 1) . substr((string) ($row->last_name ?? 'A'), 0, 1));
                                                $grants = (int) ($row->active_grants_count ?? 0);
                                                $cClients = (int) ($row->clients_grants_count ?? 0);
                                                $cLeads = (int) ($row->leads_grants_count ?? 0);
                                                $cMatters = (int) ($row->matters_grants_count ?? 0);
                                                $cClosed = (int) ($row->closed_matters_grants_count ?? 0);
                                            @endphp
                                            <tr>
                                                <td>
                                                    <div class="sa-name">
                                                        <div class="sa-avatar" aria-hidden="true">{{ $initials }}</div>
                                                        <div class="sa-name__text">
                                                            <strong>{{ $name }}</strong>
                                                            <small>{{ $row->email }}</small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>{{ $row->usertype->name ?? ('Role #' . $row->role) }}</td>
                                                <td>
                                                    @if($mode === 'restricted')
                                                        <span class="badge bg-warning text-dark">{{ $modeLabel }}</span>
                                                    @elseif($mode === 'full')
                                                        <span class="badge bg-success">{{ $modeLabel }}</span>
                                                    @else
                                                        <span class="badge bg-secondary">{{ $modeLabel }}</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if($grants > 0)
                                                        <div class="sa-grant-breakdown" title="Related emails follow these grants">
                                                            <span class="sa-grant-pill {{ $cClients ? '' : 'is-zero' }}"><strong>{{ $cClients }}</strong> clients</span>
                                                            <span class="sa-grant-pill {{ $cLeads ? '' : 'is-zero' }}"><strong>{{ $cLeads }}</strong> leads</span>
                                                            <span class="sa-grant-pill {{ $cMatters ? '' : 'is-zero' }}"><strong>{{ $cMatters }}</strong> matters</span>
                                                            <span class="sa-grant-pill {{ $cClosed ? '' : 'is-zero' }}"><strong>{{ $cClosed }}</strong> closed</span>
                                                            <span class="sa-grant-pill"><strong>{{ $grants }}</strong> total · emails yes</span>
                                                        </div>
                                                    @else
                                                        <span class="text-muted">None yet</span>
                                                    @endif
                                                </td>
                                                <td class="text-end">
                                                    <a href="{{ route('adminconsole.system.staff-access.edit', $row->id) }}" class="btn btn-sm btn-primary">Manage access</a>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="5" class="text-center text-muted py-4">No staff found.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>

                            <div class="mt-3">
                                {{ $lists->links() }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
@endsection
