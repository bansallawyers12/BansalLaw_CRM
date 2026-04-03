@extends('layouts.crm_client_detail')
@section('title', 'SMS Dashboard')

@section('content')
<!-- Main Content -->
<div class="main-content">
    <section class="section">
        <div class="section-body">
            <div class="server-error">
                @include('../Elements/flash-message')
            </div>
            <div class="custom-error-msg">
            </div>
            <div class="row">
                <div class="col-3 col-md-3 col-lg-3">
                    @include('../Elements/CRM/setting')
                </div>
                <div class="col-9 col-md-9 col-lg-9">
                    <div class="card">
                        <div class="card-header">
                            <h4><i class="fas fa-sms"></i> SMS Management Dashboard</h4>
                            <p class="text-muted">Monitor SMS activity, manage templates, and send messages</p>
                        </div>
                        <div class="card-body">
                            {{-- Statistics Cards --}}
                            <div class="row">
                                <div class="col-lg-3 col-md-6 col-sm-6 col-12">
                                    <div class="card card-statistic-1">
                                        <div class="card-icon bg-primary">
                                            <i class="fas fa-paper-plane"></i>
                                        </div>
                                        <div class="card-wrap">
                                            <div class="card-header">
                                                <h4>Total Sent Today</h4>
                                            </div>
                                            <div class="card-body">
                                                {{ $stats['total_today'] ?? 0 }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-3 col-md-6 col-sm-6 col-12">
                                    <div class="card card-statistic-1">
                                        <div class="card-icon bg-danger">
                                            <i class="fas fa-flag"></i>
                                        </div>
                                        <div class="card-wrap">
                                            <div class="card-header">
                                                <h4>Via Cellcast (AU)</h4>
                                            </div>
                                            <div class="card-body">
                                                {{ $stats['cellcast_today'] ?? 0 }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-3 col-md-6 col-sm-6 col-12">
                                    <div class="card card-statistic-1">
                                        <div class="card-icon bg-info">
                                            <i class="fas fa-globe"></i>
                                        </div>
                                        <div class="card-wrap">
                                            <div class="card-header">
                                                <h4>Via Twilio (Intl)</h4>
                                            </div>
                                            <div class="card-body">
                                                {{ $stats['twilio_today'] ?? 0 }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-3 col-md-6 col-sm-6 col-12">
                                    <div class="card card-statistic-1">
                                        <div class="card-icon bg-warning">
                                            <i class="fas fa-exclamation-triangle"></i>
                                        </div>
                                        <div class="card-wrap">
                                            <div class="card-header">
                                                <h4>Failed Messages</h4>
                                            </div>
                                            <div class="card-body">
                                                {{ $stats['failed_today'] ?? 0 }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Quick Actions --}}
                            <div class="row">
                                <div class="col-12">
                                    <div class="card">
                                        <div class="card-header">
                                            <h4>Quick Actions</h4>
                                        </div>
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-lg-3 col-md-6 col-sm-6 col-12">
                                                    <a href="{{ route('adminconsole.features.sms.send.create') }}" class="btn btn-primary btn-block">
                                                        <i class="fas fa-plus"></i> Send SMS
                                                    </a>
                                                </div>
                                                <div class="col-lg-3 col-md-6 col-sm-6 col-12">
                                                    <a href="{{ route('adminconsole.features.sms.history') }}" class="btn btn-info btn-block">
                                                        <i class="fas fa-history"></i> View History
                                                    </a>
                                                </div>
                                                <div class="col-lg-3 col-md-6 col-sm-6 col-12">
                                                    <a href="{{ route('adminconsole.features.sms.templates.index') }}" class="btn btn-success btn-block">
                                                        <i class="fas fa-file-alt"></i> Manage Templates
                                                    </a>
                                                </div>
                                                <div class="col-lg-3 col-md-6 col-sm-6 col-12">
                                                    <a href="{{ route('adminconsole.features.sms.statistics') }}" class="btn btn-warning btn-block">
                                                        <i class="fas fa-chart-bar"></i> View Statistics
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Recent Activity --}}
                            <div class="row">
                                <div class="col-12">
                                    <div class="card">
                                        <div class="card-header">
                                            <h4>
                                                <i class="fas fa-clock"></i> Recent SMS Activity
                                                <small class="float-right">
                                                    <span class="badge badge-success">
                                                        <i class="fas fa-circle"></i> Services Online
                                                    </span>
                                                </small>
                                            </h4>
                                        </div>
                                        <div class="card-body">
                                            @if($recentSms->count() > 0)
                                            <div class="table-responsive">
                                                <table class="table table-striped">
                                                    <thead>
                                                        <tr>
                                                            <th>Time</th>
                                                            <th>To</th>
                                                            <th>Message</th>
                                                            <th>Status</th>
                                                            <th>Provider</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach($recentSms as $sms)
                                                        <tr>
                                                            <td>
                                                                <small>{{ $sms->created_at->diffForHumans() }}</small>
                                                            </td>
                                                            <td>
                                                                <span style="font-family: monospace;">{{ $sms->formatted_phone ?? $sms->recipient_phone }}</span>
                                                            </td>
                                                            <td>
                                                                <div style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                                                    {{ $sms->message_content }}
                                                                </div>
                                                            </td>
                                                            <td>
                                                                <span class="badge badge-{{ $sms->status === 'sent' ? 'success' : ($sms->status === 'failed' ? 'danger' : 'warning') }}">
                                                                    {{ ucfirst($sms->status) }}
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <span class="badge badge-{{ $sms->provider === 'cellcast' ? 'danger' : 'info' }}">
                                                                    {{ strtoupper($sms->provider) }}
                                                                </span>
                                                            </td>
                                                        </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                            @else
                                            <div class="text-center py-4">
                                                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                                <h5>No SMS Activity Yet</h5>
                                                <p class="text-muted">Send your first SMS message to see activity here!</p>
                                                <a href="{{ route('adminconsole.features.sms.send.create') }}" class="btn btn-primary">
                                                    <i class="fas fa-paper-plane"></i> Send First SMS
                                                </a>
                                            </div>
                                            @endif
                                        </div>
                                    </div>
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
