@extends('layouts.crm_client_detail')
@section('title', 'Audit Entry')

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
<div class="main-content adminconsole-features adminconsole-staff-access-audit-show">
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
                        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                            <div>
                                <h4 class="mb-1">Audit entry #{{ $entry->id }}</h4>
                                <p class="text-muted small mb-0">{{ $actionLabels[$entry->action] ?? $entry->action }}</p>
                            </div>
                            <a href="{{ route('adminconsole.system.staff-access.audit', request()->only(['target_staff_id','actor_staff_id','action','from','to'])) }}" class="btn btn-outline-primary btn-sm">
                                <i class="fa-solid fa-arrow-left"></i> Back to audit
                            </a>
                        </div>
                        <div class="card-body">
                            <dl class="row mb-4">
                                <dt class="col-sm-3">When</dt>
                                <dd class="col-sm-9">{{ optional($entry->created_at)->timezone(config('app.timezone'))->format('Y-m-d H:i:s T') }}</dd>

                                <dt class="col-sm-3">Actor</dt>
                                <dd class="col-sm-9">{{ $staffName($entry->actor) }}</dd>

                                <dt class="col-sm-3">Target</dt>
                                <dd class="col-sm-9">
                                    @if($entry->target_staff_id)
                                        <a href="{{ route('adminconsole.system.staff-access.edit', $entry->target_staff_id) }}">{{ $staffName($entry->target) }}</a>
                                    @else
                                        —
                                    @endif
                                </dd>

                                <dt class="col-sm-3">Action</dt>
                                <dd class="col-sm-9"><span class="badge bg-light text-dark border">{{ $actionLabels[$entry->action] ?? $entry->action }}</span></dd>

                                <dt class="col-sm-3">Resource</dt>
                                <dd class="col-sm-9">
                                    @if($entry->resource_type)
                                        {{ $entry->resource_type }} #{{ $entry->resource_id }}
                                    @else
                                        —
                                    @endif
                                </dd>

                                <dt class="col-sm-3">IP</dt>
                                <dd class="col-sm-9">{{ $entry->ip_address ?: '—' }}</dd>
                            </dl>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <h6>Before</h6>
                                    <pre class="bg-light border rounded p-3 small mb-0" style="max-height: 420px; overflow: auto;">{{ $entry->before ? json_encode($entry->before, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : '—' }}</pre>
                                </div>
                                <div class="col-md-6">
                                    <h6>After</h6>
                                    <pre class="bg-light border rounded p-3 small mb-0" style="max-height: 420px; overflow: auto;">{{ $entry->after ? json_encode($entry->after, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : '—' }}</pre>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
@endsection
