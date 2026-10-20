@extends('layouts.crm_client_detail')
@section('title', 'Manage Staff Access')

@php
    $staffName = trim(($staff->first_name ?? '') . ' ' . ($staff->last_name ?? '')) ?: 'Staff';
    $staffInitials = strtoupper(substr((string) ($staff->first_name ?? 'S'), 0, 1) . substr((string) ($staff->last_name ?? 'A'), 0, 1));
    $currentMode = $profile->access_mode ?? \App\Models\StaffAccessProfile::MODE_FULL;
    $currentWidgets = $profile ? $profile->allowedDashboardWidgets() : [];
    $currentNav = $profile ? $profile->allowedNavModules() : [];
    $currentNavActions = $profile ? $profile->navModuleActionsMap() : [];
    $currentAdminConsole = $profile ? $profile->allowedAdminConsoleModules() : [];
    $actionLabels = [
        'view' => 'View',
        'add' => 'Add',
        'edit' => 'Edit',
        'delete' => 'Delete',
    ];
    $widgetLabels = [
        'active_matters' => ['label' => 'Active matters', 'hint' => 'KPI card', 'icon' => 'fa-briefcase'],
        'closed_matters' => ['label' => 'Closed matters', 'hint' => 'KPI card', 'icon' => 'fa-box-archive'],
        'tasks' => ['label' => 'My Tasks', 'hint' => 'Deadlines list', 'icon' => 'fa-list-check'],
        'recent_activity' => ['label' => 'Recent activity', 'hint' => 'Matter updates', 'icon' => 'fa-clock-rotate-left'],
        'calendar' => ['label' => 'Personal calendar', 'hint' => 'Appointments', 'icon' => 'fa-calendar-days'],
        'access_approvals' => ['label' => 'Access approvals', 'hint' => 'Supervisor queue', 'icon' => 'fa-user-shield'],
    ];
    $navLabels = [
        'clients' => ['label' => 'Clients', 'hint' => 'Client list / matters'],
        'leads' => ['label' => 'Leads', 'hint' => 'Lead list'],
        'accounts' => ['label' => 'Accounts', 'hint' => 'Invoices & receipts'],
        'booking' => ['label' => 'Booking', 'hint' => 'Appointment calendars'],
        'signatures' => ['label' => 'E-Signatures', 'hint' => 'Signature dashboard'],
        'office_visits' => ['label' => 'Office visits', 'hint' => 'Visit tracking'],
        'front_desk' => ['label' => 'Front desk', 'hint' => 'Check-in desk'],
        'tasks' => ['label' => 'Tasks', 'hint' => 'Top-bar tasks icon'],
        'unassigned_mail' => ['label' => 'Unassigned mail', 'hint' => 'Synced inbox'],
        'calendar' => ['label' => 'My calendar', 'hint' => 'Personal calendar icon'],
        'search' => ['label' => 'Global search', 'hint' => 'Top-bar search'],
        'office_checkin' => ['label' => 'Office check-in', 'hint' => 'Check-in shortcut'],
        'admin_console' => ['label' => 'Admin Console', 'hint' => 'Top-bar Admin Console link'],
    ];
    $adminConsoleLabels = [
        'email_labels' => 'Email Labels',
        'workflows' => 'Workflows',
        'emails' => 'Email',
        'crm_email_template' => 'CRM Email Template',
        'offices' => 'Offices',
        'staff' => 'Staff',
        'teams' => 'Teams',
        'roles' => 'Roles',
        'document_checklist' => 'Document Checklist',
        'matter_list' => 'Matter List',
        'sms' => 'SMS Management',
        'sms_templates' => 'SMS Templates',
        'esignature' => 'E-Signature',
        'activity_search' => 'Activity Search',
    ];
    $scopeLabels = [
        'view' => ['label' => 'View', 'hint' => 'Open the file'],
        'emails' => ['label' => 'Emails', 'hint' => 'Mail tab & uploads'],
        'documents' => ['label' => 'Documents', 'hint' => 'Reserved'],
        'notes' => ['label' => 'Notes', 'hint' => 'Reserved'],
    ];
    $typeLabels = [
        'client' => 'Client',
        'lead' => 'Lead',
        'matter' => 'Matter',
        'closed_matter' => 'Closed Matter',
    ];
    $grantCount = $grants->count();
    $overview = $accessOverview ?? [
        'clients' => 0,
        'leads' => 0,
        'matters' => 0,
        'closed_matters' => 0,
        'total_grants' => $grantCount,
        'email_people' => 0,
        'can_see_emails' => false,
        'nav_modules' => $currentNavActions,
        'admin_console_modules' => $currentAdminConsole,
        'access_mode' => $currentMode,
    ];
@endphp

@push('styles')
<style>
.adminconsole-staff-access-edit .sa-hero {
    display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 1rem;
    padding: 1.1rem 1.25rem; margin-bottom: 1rem;
    background: #fff; border: 1px solid #d7e3f2; border-radius: 12px;
}
.adminconsole-staff-access-edit .sa-hero__identity { display: flex; align-items: center; gap: 0.85rem; min-width: 0; }
.adminconsole-staff-access-edit .sa-avatar {
    width: 48px; height: 48px; border-radius: 12px; flex-shrink: 0;
    display: grid; place-items: center; font-weight: 700; font-size: 0.95rem; color: #fff;
    background: linear-gradient(135deg, var(--navy, #1e3a5f) 0%, var(--sidebar-active, #3a6fa8) 100%);
}
.adminconsole-staff-access-edit .sa-hero h4 { margin: 0 0 0.2rem; font-size: 1.15rem; }
.adminconsole-staff-access-edit .sa-hero__meta { margin: 0; color: #64748b; font-size: 0.85rem; }
.adminconsole-staff-access-edit .sa-card {
    background: #fff; border: 1px solid #d7e3f2; border-radius: 12px; margin-bottom: 1rem; overflow: hidden;
}
.adminconsole-staff-access-edit .sa-card__head {
    display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 0.5rem;
    padding: 0.9rem 1.15rem; border-bottom: 1px solid #e8eef6; background: #f8fafc;
}
.adminconsole-staff-access-edit .sa-card__head h5 { margin: 0; font-size: 0.98rem; font-weight: 650; }
.adminconsole-staff-access-edit .sa-card__sub { margin: 0.15rem 0 0; color: #64748b; font-size: 0.8rem; }
.adminconsole-staff-access-edit .sa-card__body { padding: 1.15rem; }
.adminconsole-staff-access-edit .sa-mode-grid {
    display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0.75rem; margin-bottom: 1rem;
}
@media (max-width: 700px) {
    .adminconsole-staff-access-edit .sa-mode-grid { grid-template-columns: 1fr; }
}
.adminconsole-staff-access-edit .sa-mode {
    position: relative; display: block; margin: 0; cursor: pointer;
    border: 1.5px solid #d7e3f2; border-radius: 10px; padding: 0.9rem 1rem 0.9rem 2.6rem;
    background: #fff; transition: border-color .15s, box-shadow .15s, background .15s;
}
.adminconsole-staff-access-edit .sa-mode:hover { border-color: #9bb8d9; }
.adminconsole-staff-access-edit .sa-mode.is-active {
    border-color: var(--sidebar-active, #3a6fa8);
    background: rgba(58, 111, 168, 0.06);
    box-shadow: 0 0 0 1px rgba(58, 111, 168, 0.2);
}
.adminconsole-staff-access-edit .sa-mode input {
    position: absolute; left: 0.9rem; top: 1.05rem; margin: 0;
}
.adminconsole-staff-access-edit .sa-mode__title { display: block; font-weight: 650; color: #1e293b; }
.adminconsole-staff-access-edit .sa-mode__hint { display: block; margin-top: 0.2rem; font-size: 0.8rem; color: #64748b; line-height: 1.35; }
.adminconsole-staff-access-edit .sa-shell-flags {
    display: flex; flex-wrap: wrap; gap: 0.65rem; margin-bottom: 1.1rem;
}
.adminconsole-staff-access-edit .sa-flag {
    display: inline-flex; align-items: center; gap: 0.45rem;
    margin: 0; padding: 0.45rem 0.75rem; border: 1px solid #d7e3f2; border-radius: 999px;
    background: #fff; cursor: pointer; font-size: 0.86rem; color: #334155;
}
.adminconsole-staff-access-edit .sa-flag input { margin: 0; }
.adminconsole-staff-access-edit .sa-widget-grid {
    display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0.65rem;
}
@media (max-width: 900px) {
    .adminconsole-staff-access-edit .sa-widget-grid { grid-template-columns: 1fr; }
}
.adminconsole-staff-access-edit .sa-widget {
    display: flex; align-items: flex-start; gap: 0.7rem; margin: 0;
    padding: 0.75rem 0.85rem; border: 1px solid #d7e3f2; border-radius: 10px;
    background: #fff; cursor: pointer; transition: border-color .15s, background .15s;
}
.adminconsole-staff-access-edit .sa-widget:hover { border-color: #9bb8d9; }
.adminconsole-staff-access-edit .sa-widget.is-on {
    border-color: var(--sidebar-active, #3a6fa8);
    background: rgba(58, 111, 168, 0.06);
}
.adminconsole-staff-access-edit .sa-widget input { margin: 0.15rem 0 0; flex-shrink: 0; }
.adminconsole-staff-access-edit .sa-widget__icon {
    width: 32px; height: 32px; border-radius: 8px; flex-shrink: 0;
    display: grid; place-items: center; color: var(--sidebar-active, #3a6fa8);
    background: rgba(58, 111, 168, 0.12); font-size: 0.85rem;
}
.adminconsole-staff-access-edit .sa-widget__label { display: block; font-weight: 600; color: #1e293b; font-size: 0.88rem; }
.adminconsole-staff-access-edit .sa-widget__hint { display: block; color: #64748b; font-size: 0.75rem; margin-top: 0.1rem; }
.adminconsole-staff-access-edit .sa-restricted-only[hidden] { display: none !important; }
.adminconsole-staff-access-edit .sa-empty {
    text-align: center; padding: 1.75rem 1rem; color: #64748b;
    border: 1px dashed #c9d7ea; border-radius: 10px; background: #f8fafc;
}
.adminconsole-staff-access-edit .sa-empty i { font-size: 1.5rem; color: #94a3b8; margin-bottom: 0.45rem; }
.adminconsole-staff-access-edit .sa-grant-table { margin-bottom: 1.25rem; }
.adminconsole-staff-access-edit .sa-grant-table table { margin: 0; }
.adminconsole-staff-access-edit .sa-scope-badge {
    display: inline-block; margin: 0 0.2rem 0.2rem 0; padding: 0.15rem 0.5rem;
    border-radius: 999px; border: 1px solid #d7e3f2; background: #f1f5f9;
    font-size: 0.72rem; font-weight: 600; color: #334155;
}
.adminconsole-staff-access-edit .sa-grant-form {
    border: 1px solid #d7e3f2; border-radius: 10px; padding: 1rem; background: #f8fafc;
}
.adminconsole-staff-access-edit .sa-grant-form h6 { margin: 0 0 0.85rem; font-weight: 650; }
.adminconsole-staff-access-edit .sa-picker {
    border: 1px solid #d7e3f2; border-radius: 10px; background: #fff; overflow: hidden;
}
.adminconsole-staff-access-edit .sa-picker__toolbar {
    display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: center;
    padding: 0.65rem 0.75rem; border-bottom: 1px solid #e8eef6; background: #f8fafc;
}
.adminconsole-staff-access-edit .sa-picker__toolbar .form-control { flex: 1 1 180px; min-width: 0; }
.adminconsole-staff-access-edit .sa-picker__actions { display: flex; flex-wrap: wrap; gap: 0.35rem; }
.adminconsole-staff-access-edit .sa-picker__list {
    max-height: 280px; overflow: auto; padding: 0.35rem 0.5rem;
}
.adminconsole-staff-access-edit .sa-picker__row {
    display: flex; align-items: flex-start; gap: 0.55rem; margin: 0;
    padding: 0.45rem 0.4rem; border-radius: 6px; cursor: pointer; font-size: 0.86rem; color: #1e293b;
}
.adminconsole-staff-access-edit .sa-picker__row:hover { background: rgba(58, 111, 168, 0.08); }
.adminconsole-staff-access-edit .sa-picker__row.is-granted {
    opacity: 1; background: rgba(34, 197, 94, 0.08); border-bottom-color: #bbf7d0;
}
.adminconsole-staff-access-edit .sa-picker__row.is-granted input { accent-color: #16a34a; }
.adminconsole-staff-access-edit .sa-picker__row.is-revoke {
    background: rgba(239, 68, 68, 0.08); border-bottom-color: #fecaca;
}
.adminconsole-staff-access-edit .sa-picker__row.is-revoke input { accent-color: #dc2626; }
.adminconsole-staff-access-edit .sa-picker__row.is-in-basket { background: rgba(58, 111, 168, 0.1); }
.adminconsole-staff-access-edit .sa-picker__row input { margin: 0.2rem 0 0; flex-shrink: 0; }
.adminconsole-staff-access-edit .sa-picker__empty,
.adminconsole-staff-access-edit .sa-picker__status {
    padding: 0.75rem; text-align: center; color: #64748b; font-size: 0.82rem;
}
.adminconsole-staff-access-edit .sa-picker__footer {
    display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: center; justify-content: space-between;
    padding: 0.55rem 0.75rem; border-top: 1px solid #e8eef6; background: #f8fafc; font-size: 0.78rem; color: #64748b;
}
.adminconsole-staff-access-edit .sa-basket {
    display: none; flex-direction: column; gap: 0.45rem; margin-top: 0.65rem;
    padding: 0.65rem; border: 1px dashed #c9d7ea; border-radius: 10px; background: #fff;
}
.adminconsole-staff-access-edit .sa-basket.is-visible { display: flex; }
.adminconsole-staff-access-edit .sa-basket__meta {
    display: flex; align-items: center; justify-content: space-between;
    gap: 0.5rem; font-size: 0.78rem; color: #64748b;
}
.adminconsole-staff-access-edit #grant-basket-chips {
    display: flex; flex-wrap: wrap; gap: 0.4rem;
}
.adminconsole-staff-access-edit .sa-chip {
    display: inline-flex; align-items: center; gap: 0.35rem; max-width: 100%;
    padding: 0.35rem 0.45rem 0.35rem 0.55rem; border-radius: 999px;
    border: 1px solid rgba(58, 111, 168, 0.3); background: rgba(58, 111, 168, 0.08);
    font-size: 0.78rem; color: #1e3a5f; line-height: 1.2;
}
.adminconsole-staff-access-edit .sa-chip__type {
    flex-shrink: 0; font-size: 0.65rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: 0.02em; color: #3a6fa8;
}
.adminconsole-staff-access-edit .sa-chip__label {
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 220px;
}
.adminconsole-staff-access-edit .sa-chip__remove {
    border: 0; background: transparent; color: #64748b; cursor: pointer;
    padding: 0 0.15rem; line-height: 1; font-size: 1rem;
}
.adminconsole-staff-access-edit .sa-chip__remove:hover { color: #b91c1c; }
.adminconsole-staff-access-edit .sa-search-hint {
    margin: 0.35rem 0 0; font-size: 0.75rem; color: #64748b;
}
.adminconsole-staff-access-edit .sa-scope-grid {
    display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0.5rem;
}
.adminconsole-staff-access-edit .sa-scope {
    display: flex; align-items: flex-start; gap: 0.55rem; margin: 0;
    padding: 0.6rem 0.7rem; border: 1px solid #d7e3f2; border-radius: 8px;
    background: #fff; cursor: pointer;
}
.adminconsole-staff-access-edit .sa-scope.is-on {
    border-color: var(--sidebar-active, #3a6fa8);
    background: rgba(58, 111, 168, 0.06);
}
.adminconsole-staff-access-edit .sa-scope input { margin: 0.12rem 0 0; flex-shrink: 0; }
.adminconsole-staff-access-edit .sa-scope__label { display: block; font-weight: 650; font-size: 0.86rem; color: #1e293b; }
.adminconsole-staff-access-edit .sa-scope__hint { display: block; font-size: 0.72rem; color: #64748b; }
.adminconsole-staff-access-edit .sa-actions { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-top: 1rem; }
.adminconsole-staff-access-edit .sa-section-label {
    display: block; font-weight: 650; font-size: 0.86rem; color: #334155; margin-bottom: 0.55rem;
}
.adminconsole-staff-access-edit .sa-nav-card {
    display: flex; flex-direction: column; gap: 0.55rem; margin: 0;
    padding: 0.75rem 0.85rem; border: 1px solid #d7e3f2; border-radius: 10px;
    background: #fff; transition: border-color .15s, background .15s;
}
.adminconsole-staff-access-edit .sa-nav-card.is-on {
    border-color: var(--sidebar-active, #3a6fa8);
    background: rgba(58, 111, 168, 0.06);
}
.adminconsole-staff-access-edit .sa-nav-card__head {
    display: flex; align-items: flex-start; gap: 0.7rem; cursor: pointer;
}
.adminconsole-staff-access-edit .sa-nav-card__head input { margin: 0.15rem 0 0; flex-shrink: 0; }
.adminconsole-staff-access-edit .sa-nav-actions {
    display: none; flex-wrap: wrap; gap: 0.4rem; padding-left: 1.55rem;
}
.adminconsole-staff-access-edit .sa-nav-card.is-on .sa-nav-actions { display: flex; }
.adminconsole-staff-access-edit .sa-nav-action {
    display: inline-flex; align-items: center; gap: 0.3rem; margin: 0;
    padding: 0.28rem 0.55rem; border: 1px solid #d7e3f2; border-radius: 999px;
    background: #fff; font-size: 0.75rem; color: #334155; cursor: pointer;
}
.adminconsole-staff-access-edit .sa-nav-action.is-on {
    border-color: var(--sidebar-active, #3a6fa8);
    background: rgba(58, 111, 168, 0.12); color: #1e3a5f; font-weight: 600;
}
.adminconsole-staff-access-edit .sa-nav-action input { margin: 0; }
.adminconsole-staff-access-edit .sa-overview {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(110px, 1fr)); gap: 0.55rem;
    margin-bottom: 1rem;
}
.adminconsole-staff-access-edit .sa-overview__tile {
    border: 1px solid #e2e8f0; border-radius: 10px; background: #fff; padding: 0.7rem 0.8rem;
}
.adminconsole-staff-access-edit .sa-overview__tile.is-on {
    border-color: #9bb8d9; background: rgba(58, 111, 168, 0.06);
}
.adminconsole-staff-access-edit .sa-overview__n {
    display: block; font-size: 1.35rem; font-weight: 700; color: #0f172a; line-height: 1.1;
}
.adminconsole-staff-access-edit .sa-overview__l {
    display: block; margin-top: 0.15rem; font-size: 0.75rem; color: #64748b; font-weight: 600;
}
.adminconsole-staff-access-edit .sa-overview__hint {
    margin: 0 0 0.85rem; font-size: 0.8rem; color: #64748b;
}
.adminconsole-staff-access-edit .sa-overview-nav {
    display: flex; flex-wrap: wrap; gap: 0.35rem; margin-top: 0.35rem;
}
.adminconsole-staff-access-edit .sa-overview-nav span {
    display: inline-flex; align-items: center; gap: 0.3rem;
    padding: 0.2rem 0.5rem; border-radius: 999px; font-size: 0.72rem;
    background: #f8fafc; border: 1px solid #e2e8f0; color: #334155;
}
.adminconsole-staff-access-edit .sa-overview-nav em {
    font-style: normal; color: #64748b; font-weight: 500;
}
.adminconsole-staff-access-edit .sa-inv-tabs {
    display: flex; flex-wrap: wrap; gap: 0.35rem; margin-bottom: 0.75rem;
}
.adminconsole-staff-access-edit .sa-inv-tab {
    border: 1px solid #e2e8f0; background: #fff; border-radius: 999px;
    padding: 0.28rem 0.7rem; font-size: 0.78rem; font-weight: 600; color: #475569; cursor: pointer;
}
.adminconsole-staff-access-edit .sa-inv-tab.is-active {
    border-color: #3a6fa8; background: rgba(58, 111, 168, 0.1); color: #1e3a5f;
}
.adminconsole-staff-access-edit .sa-inv-panel[hidden] { display: none !important; }
.adminconsole-staff-access-edit .sa-inv-table-wrap {
    max-height: 280px; overflow: auto; border: 1px solid #e2e8f0; border-radius: 8px;
}
.adminconsole-staff-access-edit .sa-inv-table-wrap table { margin: 0; }
.adminconsole-staff-access-edit .sa-inv-source {
    display: block; font-size: 0.72rem; color: #64748b;
}
.adminconsole-staff-access-edit .sa-inv-note {
    margin: 0 0 0.75rem; padding: 0.55rem 0.75rem; border-radius: 8px;
    background: #f8fafc; border: 1px solid #e2e8f0; font-size: 0.8rem; color: #475569;
}
.adminconsole-staff-access-edit .sa-nav-actions__hint {
    flex: 1 1 100%; font-size: 0.72rem; color: #64748b; margin: 0.1rem 0 0;
}
</style>
@endpush

@section('content')
<div class="main-content adminconsole-features adminconsole-staff-access-edit">
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
                    <div class="sa-hero">
                        <div class="sa-hero__identity">
                            <div class="sa-avatar" aria-hidden="true">{{ $staffInitials }}</div>
                            <div>
                                <h4>{{ $staffName }}</h4>
                                <p class="sa-hero__meta">
                                    {{ $staff->email }}
                                    · {{ $staff->usertype->name ?? ('Role #' . $staff->role) }}
                                    @if(!empty($isRestrictedNow))
                                        · <span class="badge bg-warning text-dark">Restricted now</span>
                                    @else
                                        · <span class="badge bg-success">Not restricted</span>
                                    @endif
                                </p>
                            </div>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            <a href="{{ route('adminconsole.system.staff-access.audit', ['target_staff_id' => $staff->id]) }}" class="btn btn-outline-secondary btn-sm">
                                <i class="fa-solid fa-clock-rotate-left"></i> Audit
                            </a>
                            <a href="{{ route('adminconsole.system.staff-access.index') }}" class="btn btn-outline-primary btn-sm">
                                <i class="fa-solid fa-arrow-left"></i> All staff
                            </a>
                        </div>
                    </div>

                    @if(empty($featureEnabled))
                        <div class="alert alert-warning">Restricted access feature flag is off. Enable <code>CRM_RESTRICTED_ACCESS_ENABLED=true</code> locally to save changes.</div>
                    @endif

                    @php
                        $inv = $accessInventory ?? [
                            'is_exempt' => false,
                            'is_restricted' => !empty($isRestrictedNow),
                            'counts' => [
                                'clients' => (int) ($overview['clients'] ?? 0),
                                'leads' => (int) ($overview['leads'] ?? 0),
                                'matters' => (int) ($overview['matters'] ?? 0),
                                'cross_grants' => 0,
                                'restricted_grants' => (int) ($overview['total_grants'] ?? 0),
                                'email_people' => (int) ($overview['email_people'] ?? 0),
                            ],
                            'clients' => [],
                            'leads' => [],
                            'matters' => [],
                            'cross_grants' => [],
                            'restricted_grants' => [],
                            'email_people' => [],
                            'truncated' => [],
                        ];
                        $invCounts = $inv['counts'] ?? [];
                    @endphp
                    <div class="sa-card" id="sa-existing-access">
                        <div class="sa-card__head">
                            <div>
                                <h5>Existing CRM access (kept)</h5>
                                <p class="sa-card__sub">Full read-only list of clients, leads, matters, emails, and grants this person already has. Nothing here is removed when you manage restricted grants below.</p>
                            </div>
                        </div>
                        <div class="sa-card__body">
                            <div class="sa-inv-note">
                                Sources included: matter allocation (Legal Practitioner / Person Responsible / Person Assisting), record owner, active cross-access grants, and restricted Staff Access grants.
                                @if(!empty($inv['is_exempt']))
                                    <strong> This staff is exempt / full access — they can see beyond this inventory.</strong>
                                @elseif(!empty($inv['is_restricted']))
                                    <strong> Restricted mode:</strong> day-to-day CRM visibility follows restricted grants; allocation rows below are still shown so you can see historical / matter assignments.
                                @endif
                            </div>

                            <div class="sa-overview">
                                <div class="sa-overview__tile {{ ($invCounts['clients'] ?? 0) > 0 ? 'is-on' : '' }}">
                                    <span class="sa-overview__n">{{ (int) ($invCounts['clients'] ?? 0) }}</span>
                                    <span class="sa-overview__l">Clients</span>
                                </div>
                                <div class="sa-overview__tile {{ ($invCounts['leads'] ?? 0) > 0 ? 'is-on' : '' }}">
                                    <span class="sa-overview__n">{{ (int) ($invCounts['leads'] ?? 0) }}</span>
                                    <span class="sa-overview__l">Leads</span>
                                </div>
                                <div class="sa-overview__tile {{ ($invCounts['matters'] ?? 0) > 0 ? 'is-on' : '' }}">
                                    <span class="sa-overview__n">{{ (int) ($invCounts['matters'] ?? 0) }}</span>
                                    <span class="sa-overview__l">Matters</span>
                                </div>
                                <div class="sa-overview__tile {{ ($invCounts['email_people'] ?? 0) > 0 ? 'is-on' : '' }}">
                                    <span class="sa-overview__n">{{ (int) ($invCounts['email_people'] ?? 0) }}</span>
                                    <span class="sa-overview__l">Email people</span>
                                </div>
                                <div class="sa-overview__tile {{ ($invCounts['cross_grants'] ?? 0) > 0 ? 'is-on' : '' }}">
                                    <span class="sa-overview__n">{{ (int) ($invCounts['cross_grants'] ?? 0) }}</span>
                                    <span class="sa-overview__l">Cross grants</span>
                                </div>
                                <div class="sa-overview__tile {{ ($invCounts['restricted_grants'] ?? 0) > 0 ? 'is-on' : '' }}">
                                    <span class="sa-overview__n">{{ (int) ($invCounts['restricted_grants'] ?? 0) }}</span>
                                    <span class="sa-overview__l">Restricted grants</span>
                                </div>
                            </div>

                            @php
                                $overviewNav = $overview['nav_modules'] ?? [];
                                $overviewAdmin = $overview['admin_console_modules'] ?? [];
                            @endphp
                            @if($overviewNav !== [] || $overviewAdmin !== [])
                                <div class="mt-2 mb-3">
                                    <span class="sa-section-label mb-1">Top menu / Admin Console already granted</span>
                                    <div class="sa-overview-nav">
                                        @foreach($overviewNav as $modKey => $modActions)
                                            <span>
                                                {{ $navLabels[$modKey]['label'] ?? $modKey }}
                                                <em>{{ implode(', ', array_map(fn ($a) => $actionLabels[$a] ?? $a, $modActions)) }}</em>
                                            </span>
                                        @endforeach
                                        @foreach($overviewAdmin as $acKey)
                                            <span>{{ $adminConsoleLabels[$acKey] ?? $acKey }} <em>Admin</em></span>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            <div class="sa-inv-tabs" role="tablist" aria-label="Access lists">
                                @foreach([
                                    'clients' => 'Clients',
                                    'leads' => 'Leads',
                                    'matters' => 'Matters',
                                    'emails' => 'Emails',
                                    'cross' => 'Cross grants',
                                    'restricted' => 'Restricted grants',
                                ] as $tabKey => $tabLabel)
                                    <button type="button" class="sa-inv-tab {{ $tabKey === 'clients' ? 'is-active' : '' }}" data-sa-inv-tab="{{ $tabKey }}">{{ $tabLabel }}</button>
                                @endforeach
                            </div>

                            @php
                                $invPanels = [
                                    'clients' => ['rows' => $inv['clients'] ?? [], 'empty' => 'No clients from allocation, ownership, or grants.', 'cols' => 'person'],
                                    'leads' => ['rows' => $inv['leads'] ?? [], 'empty' => 'No leads from allocation, ownership, or grants.', 'cols' => 'person'],
                                    'matters' => ['rows' => $inv['matters'] ?? [], 'empty' => 'Not assigned on any matter as LP / PR / PA.', 'cols' => 'matter'],
                                    'emails' => ['rows' => $inv['email_people'] ?? [], 'empty' => 'No people whose emails they can open yet.', 'cols' => 'email'],
                                    'cross' => ['rows' => $inv['cross_grants'] ?? [], 'empty' => 'No active cross-access grants.', 'cols' => 'cross'],
                                    'restricted' => ['rows' => $inv['restricted_grants'] ?? [], 'empty' => 'No restricted Staff Access grants yet.', 'cols' => 'restricted'],
                                ];
                            @endphp
                            @foreach($invPanels as $panelKey => $panel)
                                <div class="sa-inv-panel" data-sa-inv-panel="{{ $panelKey }}" @if($panelKey !== 'clients') hidden @endif>
                                    @if(empty($panel['rows']))
                                        <div class="sa-empty py-3">
                                            <div class="small text-muted">{{ $panel['empty'] }}</div>
                                        </div>
                                    @else
                                        <div class="sa-inv-table-wrap">
                                            <table class="table table-sm table-hover align-middle mb-0">
                                                <thead>
                                                    <tr>
                                                        @if($panel['cols'] === 'person')
                                                            <th>Person</th>
                                                            <th>Why they have access</th>
                                                        @elseif($panel['cols'] === 'matter')
                                                            <th>Matter</th>
                                                            <th>Role</th>
                                                        @elseif($panel['cols'] === 'email')
                                                            <th>Person</th>
                                                            <th>Type</th>
                                                            <th>Email</th>
                                                        @elseif($panel['cols'] === 'cross')
                                                            <th>Record</th>
                                                            <th>Grant</th>
                                                            <th>Ends</th>
                                                        @else
                                                            <th>Type</th>
                                                            <th>Record</th>
                                                            <th>Scopes</th>
                                                        @endif
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($panel['rows'] as $row)
                                                        <tr>
                                                            @if($panel['cols'] === 'person')
                                                                <td>
                                                                    <strong>{{ $row['label'] ?? ('#' . ($row['id'] ?? '')) }}</strong>
                                                                    @if(!empty($row['email']))
                                                                        <span class="sa-inv-source">{{ $row['email'] }}</span>
                                                                    @endif
                                                                </td>
                                                                <td>
                                                                    @foreach(($row['sources'] ?? []) as $src)
                                                                        <span class="sa-inv-source">{{ $src }}</span>
                                                                    @endforeach
                                                                </td>
                                                            @elseif($panel['cols'] === 'matter')
                                                                <td>{{ $row['label'] ?? ('Matter #' . ($row['id'] ?? '')) }}</td>
                                                                <td>{{ implode(', ', $row['roles'] ?? []) }}</td>
                                                            @elseif($panel['cols'] === 'email')
                                                                <td>{{ $row['label'] ?? ('#' . ($row['id'] ?? '')) }}</td>
                                                                <td>{{ ucfirst((string) ($row['type'] ?? '')) }}</td>
                                                                <td>{{ $row['email'] ?? '—' }}</td>
                                                            @elseif($panel['cols'] === 'cross')
                                                                <td>{{ $row['label'] ?? ('#' . ($row['admin_id'] ?? '')) }}</td>
                                                                <td>{{ $row['grant_type'] ?? '' }} {{ !empty($row['access_type']) ? '· '.$row['access_type'] : '' }}</td>
                                                                <td>{{ $row['ends_at'] ?? '—' }}</td>
                                                            @else
                                                                <td>{{ ucfirst(str_replace('_', ' ', (string) ($row['resource_type'] ?? ''))) }}</td>
                                                                <td>{{ $row['label'] ?? ('#' . ($row['resource_id'] ?? '')) }}</td>
                                                                <td>{{ implode(', ', $row['scopes'] ?? []) }}</td>
                                                            @endif
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                        @php
                                            $truncKey = $panelKey === 'emails' ? 'email_people' : ($panelKey === 'cross' ? 'cross_grants' : ($panelKey === 'restricted' ? 'restricted_grants' : $panelKey));
                                        @endphp
                                        @if(!empty($inv['truncated'][$truncKey]))
                                            <p class="small text-muted mt-2 mb-0">Showing first {{ count($panel['rows']) }} of {{ (int) ($invCounts[$truncKey] ?? count($panel['rows'])) }}. Full JSON: <code>staff-access/{{ $staff->id }}/inventory</code></p>
                                        @endif
                                    @endif
                                </div>
                            @endforeach

                            <div class="mt-3 d-flex flex-wrap gap-2">
                                <a href="#sa-active-grants" class="btn btn-sm btn-outline-primary">Manage restricted grants</a>
                            </div>
                        </div>
                    </div>

                    <div class="sa-card">
                        <div class="sa-card__head">
                            <div>
                                <h5>Access profile</h5>
                                <p class="sa-card__sub">Choose Full CRM rules or Restricted grants-only access.</p>
                            </div>
                        </div>
                        <div class="sa-card__body">
                            <form method="post" action="{{ route('adminconsole.system.staff-access.profile', $staff->id) }}" id="sa-profile-form">
                                @csrf
                                @method('PUT')

                                <span class="sa-section-label">Mode</span>
                                <div class="sa-mode-grid">
                                    <label class="sa-mode {{ $currentMode === 'full' ? 'is-active' : '' }}" data-sa-mode-card>
                                        <input type="radio" name="access_mode" value="full" @checked($currentMode === 'full') data-sa-mode>
                                        <span class="sa-mode__title">Full access</span>
                                        <span class="sa-mode__hint">Normal allocation and module rules for this staff member.</span>
                                    </label>
                                    <label class="sa-mode {{ $currentMode === 'restricted' ? 'is-active' : '' }}" data-sa-mode-card>
                                        <input type="radio" name="access_mode" value="restricted" @checked($currentMode === 'restricted') data-sa-mode>
                                        <span class="sa-mode__title">Restricted</span>
                                        <span class="sa-mode__hint">Profile + bare dashboard by default. Only granted clients, leads, and matters.</span>
                                    </label>
                                </div>

                                <span class="sa-section-label">Shell access</span>
                                <div class="sa-shell-flags">
                                    <label class="sa-flag">
                                        <input type="checkbox" name="can_view_own_profile" value="1" @checked(($profile->can_view_own_profile ?? true))>
                                        <span>Own profile</span>
                                    </label>
                                    <label class="sa-flag">
                                        <input type="checkbox" name="can_view_dashboard" value="1" @checked(($profile->can_view_dashboard ?? true))>
                                        <span>Dashboard shell</span>
                                    </label>
                                </div>

                                <div class="sa-restricted-only" data-sa-restricted-only @if($currentMode !== 'restricted') hidden @endif>
                                    <span class="sa-section-label">Dashboard widgets</span>
                                    <p class="text-muted small mb-2">Tasks, calendar, recent activity, and active matters turn on automatically after any record grant. Closed matters stay off unless you grant a Closed Matter below.</p>
                                    <div class="sa-widget-grid">
                                        @foreach($knownWidgets as $widgetKey)
                                            @php $meta = $widgetLabels[$widgetKey] ?? ['label' => $widgetKey, 'hint' => '', 'icon' => 'fa-square']; @endphp
                                            <label class="sa-widget {{ in_array($widgetKey, $currentWidgets, true) ? 'is-on' : '' }}" data-sa-toggle-card>
                                                <input type="checkbox" name="dashboard_widgets[]" value="{{ $widgetKey }}" @checked(in_array($widgetKey, $currentWidgets, true))>
                                                <span class="sa-widget__icon"><i class="fa-solid {{ $meta['icon'] }}"></i></span>
                                                <span>
                                                    <span class="sa-widget__label">{{ $meta['label'] }}</span>
                                                    <span class="sa-widget__hint">{{ $meta['hint'] }}</span>
                                                </span>
                                            </label>
                                        @endforeach
                                    </div>

                                    <span class="sa-section-label mt-3">Top menu bar</span>
                                    <p class="text-muted small mb-2">Enable a module, then tick View / Add / Edit / Delete for that area. Clients/Leads also appear after record grants (view). Add/Edit/Delete still need the ticks below.</p>
                                    <div class="sa-widget-grid">
                                        @foreach($knownNavModules as $navKey)
                                            @php
                                                $meta = $navLabels[$navKey] ?? ['label' => $navKey, 'hint' => ''];
                                                $moduleOn = in_array($navKey, $currentNav, true);
                                                $actionsForModule = $knownNavModuleActions[$navKey] ?? ['view'];
                                                $selectedActions = $currentNavActions[$navKey] ?? ($moduleOn ? ['view'] : []);
                                            @endphp
                                            <div class="sa-nav-card {{ $moduleOn ? 'is-on' : '' }}" data-sa-nav-card>
                                                <label class="sa-nav-card__head">
                                                    <input type="checkbox"
                                                           value="{{ $navKey }}"
                                                           data-sa-nav-enable
                                                           @checked($moduleOn)>
                                                    <span>
                                                        <span class="sa-widget__label">{{ $meta['label'] }}</span>
                                                        <span class="sa-widget__hint">{{ $meta['hint'] }}</span>
                                                    </span>
                                                </label>
                                                <div class="sa-nav-actions" data-sa-nav-actions>
                                                    <p class="sa-nav-actions__hint">Permissions for {{ $meta['label'] }}</p>
                                                    @foreach($actionsForModule as $action)
                                                        <label class="sa-nav-action {{ in_array($action, $selectedActions, true) ? 'is-on' : '' }}" data-sa-nav-action>
                                                            <input type="checkbox"
                                                                   name="nav_modules[{{ $navKey }}][]"
                                                                   value="{{ $action }}"
                                                                   data-sa-nav-action-input
                                                                   data-action="{{ $action }}"
                                                                   @checked(in_array($action, $selectedActions, true))
                                                                   @disabled(! $moduleOn)>
                                                            <span>{{ $actionLabels[$action] ?? ucfirst($action) }}</span>
                                                        </label>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>

                                    <span class="sa-section-label mt-3">Admin Console modules</span>
                                    <p class="text-muted small mb-2">Tick sidebar options inside Admin Console. Selecting any module also enables the Admin Console top-bar link. Staff Access stays Super Admin only.</p>
                                    <div class="sa-widget-grid">
                                        @foreach($knownAdminConsoleModules as $acKey)
                                            <label class="sa-widget {{ in_array($acKey, $currentAdminConsole, true) ? 'is-on' : '' }}" data-sa-toggle-card>
                                                <input type="checkbox" name="admin_console_modules[]" value="{{ $acKey }}" @checked(in_array($acKey, $currentAdminConsole, true))>
                                                <span>
                                                    <span class="sa-widget__label">{{ $adminConsoleLabels[$acKey] ?? $acKey }}</span>
                                                    <span class="sa-widget__hint">Admin Console</span>
                                                </span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>

                                <div class="sa-actions">
                                    <button type="submit" class="btn btn-primary" @disabled(empty($featureEnabled))>
                                        <i class="fa-solid fa-floppy-disk me-1"></i> Save profile
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="sa-card" id="sa-active-grants">
                        <div class="sa-card__head">
                            <div>
                                <h5>Record grants</h5>
                                <p class="sa-card__sub">
                                    @if($grantCount > 0)
                                        {{ $grantCount }} active {{ $grantCount === 1 ? 'grant' : 'grants' }}
                                        ({{ (int) ($overview['clients'] ?? 0) }} clients ·
                                        {{ (int) ($overview['leads'] ?? 0) }} leads ·
                                        {{ (int) ($overview['matters'] ?? 0) }} matters ·
                                        {{ (int) ($overview['closed_matters'] ?? 0) }} closed)
                                        — unlocks own tasks, calendar, reminders, and related mail.
                                    @else
                                        No grants yet — bare dashboard only. Grants unlock tasks, calendar, reminders, and emails for those records (closed matters stay blocked unless granted).
                                    @endif
                                </p>
                            </div>
                        </div>
                        <div class="sa-card__body">
                            <div class="sa-grant-table">
                                @if($grantCount === 0)
                                    <div class="sa-empty">
                                        <div><i class="fa-regular fa-folder-open"></i></div>
                                        <div class="fw-semibold text-dark mb-1">No active grants</div>
                                        <div class="small">Search a client, lead, matter, or closed matter below and choose scopes.</div>
                                    </div>
                                @else
                                    <div class="table-responsive">
                                        <table class="table table-sm table-hover align-middle mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Type</th>
                                                    <th>Record</th>
                                                    <th>Scopes</th>
                                                    <th class="text-end">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($grants as $grant)
                                                    <tr>
                                                        <td><span class="badge bg-light text-dark border">{{ $typeLabels[$grant->resource_type] ?? ucfirst(str_replace('_', ' ', (string) $grant->resource_type)) }}</span></td>
                                                        <td>{{ $grantLabels[$grant->id] ?? ('#' . $grant->resource_id) }}</td>
                                                        <td>
                                                            @foreach(($grant->scopes ?? []) as $scope)
                                                                <span class="sa-scope-badge">{{ $scopeLabels[$scope]['label'] ?? $scope }}</span>
                                                            @endforeach
                                                        </td>
                                                        <td class="text-end">
                                                            <form method="post" action="{{ route('adminconsole.system.staff-access.grants.revoke', [$staff->id, $grant->id]) }}" onsubmit="return confirm('Revoke this grant?');" class="d-inline">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit" class="btn btn-sm btn-outline-danger" @disabled(empty($featureEnabled))>Revoke</button>
                                                            </form>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @endif
                            </div>

                            <form method="post" action="{{ route('adminconsole.system.staff-access.grants.store', $staff->id) }}" id="staff-access-grant-form" class="sa-grant-form">
                                @csrf
                                <h6>Add grants <span class="text-muted fw-normal">(browse &amp; tick)</span></h6>
                                <p class="text-muted small mb-3 mb-md-2">Browse the list, tick to grant, then save. Records this person <strong>already can access</strong> start checked — <strong>untick</strong> any Client / Lead / Matter / Closed Matter to remove that access (allocation, owner, cross-grant, restricted grant; related emails follow). Then click Save.</p>
                                <div class="row g-3">
                                    <div class="col-md-3">
                                        <label class="form-label" for="grant-resource-type">Type</label>
                                        <select id="grant-resource-type" class="form-select">
                                            <option value="client">Client</option>
                                            <option value="lead">Lead</option>
                                            <option value="matter">Matter</option>
                                            <option value="closed_matter">Closed Matter</option>
                                        </select>
                                    </div>
                                    <div class="col-md-9">
                                        <label class="form-label" for="grant-resource-search">Browse records</label>
                                        <div class="sa-picker" id="grant-picker">
                                            <div class="sa-picker__toolbar">
                                                <input type="search" id="grant-resource-search" class="form-control form-control-sm" placeholder="Filter by name, email, ID, or matter code (optional)…" autocomplete="off">
                                                <div class="sa-picker__actions">
                                                    <button type="button" class="btn btn-sm btn-outline-primary" id="grant-select-page">Tick page</button>
                                                    <button type="button" class="btn btn-sm btn-outline-secondary" id="grant-clear-page">Untick page</button>
                                                </div>
                                            </div>
                                            <div id="grant-picker-list" class="sa-picker__list" role="group" aria-label="Records to grant"></div>
                                            <div class="sa-picker__footer">
                                                <span id="grant-picker-status">Loading…</span>
                                                <button type="button" class="btn btn-sm btn-light border" id="grant-load-more" hidden>Load more</button>
                                            </div>
                                        </div>
                                        <div id="grant-basket" class="sa-basket" aria-live="polite">
                                            <div class="sa-basket__meta">
                                                <span id="grant-basket-count">0 selected</span>
                                                <button type="button" id="grant-basket-clear" class="btn btn-link btn-sm p-0">Clear selection</button>
                                            </div>
                                            <div id="grant-basket-chips"></div>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <span class="sa-section-label">Scopes <span class="text-muted fw-normal">(for all selected)</span></span>
                                        <div class="sa-scope-grid">
                                            @foreach($knownScopes as $scope)
                                                @php $meta = $scopeLabels[$scope] ?? ['label' => $scope, 'hint' => '']; @endphp
                                                <label class="sa-scope {{ $scope === 'view' ? 'is-on' : '' }}" data-sa-toggle-card>
                                                    <input type="checkbox"
                                                           name="scopes[]"
                                                           value="{{ $scope }}"
                                                           @checked($scope === 'view')
                                                           @if($scope === 'view') required @endif>
                                                    <span>
                                                        <span class="sa-scope__label">{{ $meta['label'] }}</span>
                                                        <span class="sa-scope__hint">{{ $meta['hint'] }}</span>
                                                    </span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label" for="grant-notes">Notes <span class="text-muted">(optional)</span></label>
                                        <input type="text" name="notes" id="grant-notes" class="form-control" maxlength="1000" placeholder="Reason for access">
                                    </div>
                                    <div class="col-12">
                                        <button type="submit" class="btn btn-primary" id="grant-save-btn" @disabled(empty($featureEnabled))>
                                            <i class="fa-solid fa-plus me-1"></i> Save grants
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
@endsection

@push('scripts')
<script>
(function () {
    function syncToggleCards(root) {
        (root || document).querySelectorAll('[data-sa-toggle-card]').forEach(function (card) {
            var input = card.querySelector('input[type="checkbox"]');
            if (!input) return;
            card.classList.toggle('is-on', !!input.checked);
        });
    }

    function syncModeCards() {
        document.querySelectorAll('[data-sa-mode-card]').forEach(function (card) {
            var input = card.querySelector('input[type="radio"]');
            card.classList.toggle('is-active', !!(input && input.checked));
        });
        var restricted = document.querySelector('input[name="access_mode"][value="restricted"]');
        var panel = document.querySelector('[data-sa-restricted-only]');
        if (panel) panel.hidden = !(restricted && restricted.checked);
    }

    document.querySelectorAll('[data-sa-toggle-card] input').forEach(function (input) {
        input.addEventListener('change', function () {
            syncToggleCards(input.closest('[data-sa-toggle-card]') && input.closest('.sa-widget-grid, .sa-scope-grid, .sa-card') || document);
            syncToggleCards();
        });
    });
    document.querySelectorAll('[data-sa-mode]').forEach(function (input) {
        input.addEventListener('change', syncModeCards);
    });

    function syncNavCard(card) {
        var enable = card.querySelector('[data-sa-nav-enable]');
        var actionInputs = card.querySelectorAll('[data-sa-nav-action-input]');
        var on = !!(enable && enable.checked);
        card.classList.toggle('is-on', on);
        actionInputs.forEach(function (input) {
            input.disabled = !on;
            if (!on) {
                input.checked = false;
            }
            var wrap = input.closest('[data-sa-nav-action]');
            if (wrap) wrap.classList.toggle('is-on', !!input.checked);
        });
        if (on) {
            var viewInput = card.querySelector('[data-sa-nav-action-input][data-action="view"]');
            var anyChecked = Array.prototype.some.call(actionInputs, function (i) { return i.checked; });
            if (viewInput && !anyChecked) {
                viewInput.checked = true;
                var viewWrap = viewInput.closest('[data-sa-nav-action]');
                if (viewWrap) viewWrap.classList.add('is-on');
            }
        }
    }

    document.querySelectorAll('[data-sa-nav-card]').forEach(function (card) {
        var enable = card.querySelector('[data-sa-nav-enable]');
        if (enable) {
            enable.addEventListener('change', function () {
                syncNavCard(card);
            });
        }
        card.querySelectorAll('[data-sa-nav-action-input]').forEach(function (input) {
            input.addEventListener('change', function () {
                if (input.dataset.action === 'view' && !input.checked) {
                    // Turning off View disables the whole module.
                    var en = card.querySelector('[data-sa-nav-enable]');
                    if (en) {
                        en.checked = false;
                        syncNavCard(card);
                        return;
                    }
                }
                if (input.checked) {
                    var viewInput = card.querySelector('[data-sa-nav-action-input][data-action="view"]');
                    if (viewInput && !viewInput.checked) {
                        viewInput.checked = true;
                    }
                }
                syncNavCard(card);
            });
        });
        syncNavCard(card);
    });

    document.querySelectorAll('[data-sa-inv-tab]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var key = btn.getAttribute('data-sa-inv-tab');
            document.querySelectorAll('[data-sa-inv-tab]').forEach(function (b) {
                b.classList.toggle('is-active', b === btn);
            });
            document.querySelectorAll('[data-sa-inv-panel]').forEach(function (panel) {
                panel.hidden = panel.getAttribute('data-sa-inv-panel') !== key;
            });
        });
    });

    syncToggleCards();
    syncModeCards();

    var typeEl = document.getElementById('grant-resource-type');
    var searchEl = document.getElementById('grant-resource-search');
    var listEl = document.getElementById('grant-picker-list');
    var statusEl = document.getElementById('grant-picker-status');
    var loadMoreBtn = document.getElementById('grant-load-more');
    var selectPageBtn = document.getElementById('grant-select-page');
    var clearPageBtn = document.getElementById('grant-clear-page');
    var basketEl = document.getElementById('grant-basket');
    var chipsEl = document.getElementById('grant-basket-chips');
    var countEl = document.getElementById('grant-basket-count');
    var clearBasketBtn = document.getElementById('grant-basket-clear');
    var saveBtn = document.getElementById('grant-save-btn');
    var form = document.getElementById('staff-access-grant-form');
    var searchUrl = @json(route('adminconsole.system.staff-access.search'));
    var staffId = @json((int) $staff->id);
    var typeLabels = { client: 'Client', lead: 'Lead', matter: 'Matter', closed_matter: 'Closed' };
    var basket = {};
    var revokeBasket = {};
    var alreadyAccess = {};
    var pageItems = [];
    var page = 1;
    var lastPage = 1;
    var total = 0;
    var loading = false;
    var timer = null;

    function basketKey(type, id) {
        return String(type) + ':' + String(id);
    }

    function updateSaveButton() {
        var n = Object.keys(basket).length;
        var r = Object.keys(revokeBasket).length;
        countEl.textContent = (n ? (n + ' to grant') : '') + (n && r ? ' · ' : '') + (r ? (r + ' to remove') : (n ? '' : 'Nothing selected'));
        basketEl.classList.toggle('is-visible', n > 0 || r > 0);
        if (!saveBtn || saveBtn.hasAttribute('disabled')) return;
        if (n && r) {
            saveBtn.innerHTML = '<i class="fa-solid fa-floppy-disk me-1"></i> Save ' + n + ' grant' + (n > 1 ? 's' : '') + ', remove ' + r;
        } else if (r) {
            saveBtn.innerHTML = '<i class="fa-solid fa-user-slash me-1"></i> Remove access (' + r + ')';
        } else if (n > 1) {
            saveBtn.innerHTML = '<i class="fa-solid fa-plus me-1"></i> Save ' + n + ' grants';
        } else {
            saveBtn.innerHTML = '<i class="fa-solid fa-plus me-1"></i> Save grants';
        }
    }

    function renderBasket() {
        var keys = Object.keys(basket);
        var revokeKeys = Object.keys(revokeBasket);
        chipsEl.innerHTML = '';

        keys.forEach(function (key, idx) {
            var item = basket[key];
            var chip = document.createElement('span');
            chip.className = 'sa-chip';

            var typeSpan = document.createElement('span');
            typeSpan.className = 'sa-chip__type';
            typeSpan.textContent = typeLabels[item.type] || item.type;

            var labelSpan = document.createElement('span');
            labelSpan.className = 'sa-chip__label';
            labelSpan.textContent = item.label;
            labelSpan.title = item.label;

            var removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'sa-chip__remove';
            removeBtn.title = 'Remove';
            removeBtn.setAttribute('aria-label', 'Remove');
            removeBtn.innerHTML = '&times;';
            removeBtn.addEventListener('click', function () {
                delete basket[key];
                renderBasket();
                syncListChecks();
            });

            var typeInput = document.createElement('input');
            typeInput.type = 'hidden';
            typeInput.name = 'items[' + idx + '][type]';
            typeInput.value = item.type;

            var idInput = document.createElement('input');
            idInput.type = 'hidden';
            idInput.name = 'items[' + idx + '][id]';
            idInput.value = String(item.id);

            chip.appendChild(typeSpan);
            chip.appendChild(labelSpan);
            chip.appendChild(removeBtn);
            chip.appendChild(typeInput);
            chip.appendChild(idInput);
            chipsEl.appendChild(chip);
        });

        revokeKeys.forEach(function (key, idx) {
            var item = revokeBasket[key];
            var chip = document.createElement('span');
            chip.className = 'sa-chip';
            chip.style.borderColor = '#fca5a5';
            chip.style.background = 'rgba(239, 68, 68, 0.08)';

            var typeSpan = document.createElement('span');
            typeSpan.className = 'sa-chip__type';
            typeSpan.textContent = 'Remove';

            var labelSpan = document.createElement('span');
            labelSpan.className = 'sa-chip__label';
            labelSpan.textContent = (typeLabels[item.type] || item.type) + ': ' + item.label;
            labelSpan.title = item.label;

            var undoBtn = document.createElement('button');
            undoBtn.type = 'button';
            undoBtn.className = 'sa-chip__remove';
            undoBtn.title = 'Keep access';
            undoBtn.setAttribute('aria-label', 'Keep access');
            undoBtn.innerHTML = '&times;';
            undoBtn.addEventListener('click', function () {
                delete revokeBasket[key];
                renderBasket();
                syncListChecks();
            });

            var typeInput = document.createElement('input');
            typeInput.type = 'hidden';
            typeInput.name = 'revoke_items[' + idx + '][type]';
            typeInput.value = item.type;

            var idInput = document.createElement('input');
            idInput.type = 'hidden';
            idInput.name = 'revoke_items[' + idx + '][id]';
            idInput.value = String(item.id);

            chip.appendChild(typeSpan);
            chip.appendChild(labelSpan);
            chip.appendChild(undoBtn);
            chip.appendChild(typeInput);
            chip.appendChild(idInput);
            chipsEl.appendChild(chip);
        });

        updateSaveButton();
    }

    function setBasketItem(type, id, label, on) {
        var key = basketKey(type, id);
        if (on) {
            basket[key] = { type: type, id: id, label: label };
            delete revokeBasket[key];
        } else {
            delete basket[key];
        }
        renderBasket();
    }

    function applyCheckboxState(type, id, label, checked, already) {
        var key = basketKey(type, id);
        if (already) {
            delete basket[key];
            if (checked) {
                delete revokeBasket[key];
            } else {
                revokeBasket[key] = { type: type, id: id, label: label };
            }
        } else {
            delete revokeBasket[key];
            if (checked) {
                basket[key] = { type: type, id: id, label: label };
            } else {
                delete basket[key];
            }
        }
        renderBasket();
    }

    function syncListChecks() {
        listEl.querySelectorAll('[data-sa-pick]').forEach(function (row) {
            var cb = row.querySelector('input[type="checkbox"]');
            if (!cb) return;
            var type = cb.dataset.type;
            var id = cb.dataset.id;
            var key = basketKey(type, id);
            var already = !!alreadyAccess[key];
            var checked;
            if (already) {
                checked = !revokeBasket[key];
            } else {
                checked = !!basket[key];
            }
            cb.checked = checked;
            row.classList.toggle('is-in-basket', !already && checked);
            row.classList.toggle('is-granted', already && checked);
            row.classList.toggle('is-revoke', already && !checked);
        });
    }

    function renderList(items, append) {
        if (!append) {
            listEl.innerHTML = '';
            pageItems = [];
        }
        if (!items.length && !append) {
            listEl.innerHTML = '<div class="sa-picker__empty">No records found.</div>';
            return;
        }
        items.forEach(function (item) {
            pageItems.push(item);
            var type = item.type || typeEl.value;
            var key = basketKey(type, item.id);
            var already = !!item.granted;
            if (already) {
                alreadyAccess[key] = true;
            }
            var inRevoke = !!revokeBasket[key];
            var inBasket = !!basket[key];
            var checked = already ? !inRevoke : inBasket;

            var row = document.createElement('label');
            row.className = 'sa-picker__row'
                + (already && checked ? ' is-granted' : '')
                + (already && !checked ? ' is-revoke' : '')
                + (!already && inBasket ? ' is-in-basket' : '');
            row.setAttribute('data-sa-pick', '1');

            var cb = document.createElement('input');
            cb.type = 'checkbox';
            cb.dataset.type = type;
            cb.dataset.id = String(item.id);
            cb.dataset.label = item.label;
            cb.dataset.already = already ? '1' : '0';
            cb.checked = checked;
            cb.addEventListener('change', function () {
                applyCheckboxState(type, item.id, item.label, cb.checked, already);
                row.classList.toggle('is-in-basket', !already && cb.checked);
                row.classList.toggle('is-granted', already && cb.checked);
                row.classList.toggle('is-revoke', already && !cb.checked);
                var textEl = row.querySelector('[data-sa-pick-label]');
                if (textEl) {
                    textEl.textContent = item.label + (already
                        ? (cb.checked ? ' (already has access)' : ' (will remove access)')
                        : '');
                }
            });

            var text = document.createElement('span');
            text.setAttribute('data-sa-pick-label', '1');
            text.textContent = item.label + (already
                ? (checked ? ' (already has access)' : ' (will remove access)')
                : '');

            row.appendChild(cb);
            row.appendChild(text);
            listEl.appendChild(row);
        });
    }

    var alreadyCount = 0;
    function updateStatus() {
        var shown = pageItems.length;
        var base = total
            ? ('Showing ' + shown + ' of ' + total)
            : (loading ? 'Loading…' : 'No records');
        if (alreadyCount > 0 && total) {
            base += ' · ' + alreadyCount + ' already have access (checked)';
        }
        statusEl.textContent = base;
        loadMoreBtn.hidden = !(page < lastPage);
        loadMoreBtn.disabled = loading;
    }

    function fetchPage(nextPage, append) {
        if (loading) return;
        loading = true;
        updateStatus();
        var q = (searchEl.value || '').trim();
        var url = searchUrl
            + '?type=' + encodeURIComponent(typeEl.value)
            + '&q=' + encodeURIComponent(q)
            + '&page=' + encodeURIComponent(nextPage)
            + '&per_page=40'
            + '&staff_id=' + encodeURIComponent(staffId);

        fetch(url, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        }).then(function (r) { return r.json(); }).then(function (data) {
            page = (data && data.page) || nextPage;
            lastPage = (data && data.last_page) || 1;
            total = (data && data.total) || 0;
            alreadyCount = (data && data.already_count) || 0;
            renderList((data && data.results) || [], !!append);
            loading = false;
            updateStatus();
        }).catch(function () {
            loading = false;
            if (!append) {
                listEl.innerHTML = '<div class="sa-picker__empty">Failed to load records.</div>';
            }
            statusEl.textContent = 'Load failed';
            loadMoreBtn.hidden = true;
        });
    }

    function reloadList() {
        page = 1;
        lastPage = 1;
        total = 0;
        fetchPage(1, false);
    }

    if (typeEl) typeEl.addEventListener('change', function () {
        searchEl.value = '';
        reloadList();
    });
    if (searchEl) {
        searchEl.addEventListener('input', function () {
            clearTimeout(timer);
            timer = setTimeout(reloadList, 300);
        });
    }
    if (loadMoreBtn) loadMoreBtn.addEventListener('click', function () {
        if (page < lastPage) fetchPage(page + 1, true);
    });
    if (selectPageBtn) selectPageBtn.addEventListener('click', function () {
        listEl.querySelectorAll('[data-sa-pick] input[type="checkbox"]').forEach(function (cb) {
            cb.checked = true;
            applyCheckboxState(
                cb.dataset.type,
                parseInt(cb.dataset.id, 10),
                cb.dataset.label,
                true,
                cb.dataset.already === '1'
            );
            var row = cb.closest('[data-sa-pick]');
            var already = cb.dataset.already === '1';
            row.classList.toggle('is-in-basket', !already);
            row.classList.toggle('is-granted', already);
            row.classList.toggle('is-revoke', false);
            var textEl = row.querySelector('[data-sa-pick-label]');
            if (textEl && already) textEl.textContent = cb.dataset.label + ' (already has access)';
        });
    });
    if (clearPageBtn) clearPageBtn.addEventListener('click', function () {
        listEl.querySelectorAll('[data-sa-pick] input[type="checkbox"]').forEach(function (cb) {
            cb.checked = false;
            applyCheckboxState(
                cb.dataset.type,
                parseInt(cb.dataset.id, 10),
                cb.dataset.label,
                false,
                cb.dataset.already === '1'
            );
            var row = cb.closest('[data-sa-pick]');
            var already = cb.dataset.already === '1';
            row.classList.toggle('is-in-basket', false);
            row.classList.toggle('is-granted', false);
            row.classList.toggle('is-revoke', already);
            var textEl = row.querySelector('[data-sa-pick-label]');
            if (textEl && already) textEl.textContent = cb.dataset.label + ' (will remove access)';
        });
    });
    if (clearBasketBtn) clearBasketBtn.addEventListener('click', function () {
        basket = {};
        revokeBasket = {};
        renderBasket();
        syncListChecks();
    });

    if (form) form.addEventListener('submit', function (e) {
        var n = Object.keys(basket).length;
        var r = Object.keys(revokeBasket).length;
        if (n === 0 && r === 0) {
            e.preventDefault();
            alert('Tick records to grant, or untick existing access to remove it, then save.');
            return;
        }
        if (r > 0) {
            var ok = window.confirm(
                'Remove existing access for ' + r + ' record' + (r > 1 ? 's' : '') + '?\n\n'
                + 'This clears allocation / owner / cross-grants / restricted grants for those items. Related emails will no longer be visible for them.'
            );
            if (!ok) {
                e.preventDefault();
            }
        }
    });

    renderBasket();
    reloadList();
})();
</script>
@endpush
