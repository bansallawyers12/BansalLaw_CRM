@extends('layouts.crm_client_detail')
@section('title', 'Performance Insights')

@section('styles')
<link rel="stylesheet" href="{{ asset('css/listing-container.css') }}">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0"></script>
<style>
    .insights-container {
        padding: 32px;
        background: #f8fafc;
        min-height: 100vh;
    }

    .insights-card {
        background: white;
        border-radius: 16px;
        padding: 24px;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08);
        transition: transform 0.2s, box-shadow 0.2s;
    }

    .insights-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(15, 23, 42, 0.12);
    }

    .insights-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 16px;
        margin-bottom: 24px;
    }

    .insights-header h1 {
        font-size: 28px;
        font-weight: 700;
        color: #1e293b;
        margin: 0;
    }

    .insight-tabs .nav-link {
        border-radius: 999px;
        margin-right: 8px;
        font-weight: 600;
        color: #475569;
        transition: all 0.3s;
    }

    .insight-tabs .nav-link.active {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
    }

    .insight-tabs .nav-link:hover:not(.active) {
        background: #f1f5f9;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 16px;
        margin-bottom: 24px;
    }

    .stat-card {
        background: white;
        border-radius: 16px;
        padding: 20px;
        border: 1px solid #e2e8f0;
        position: relative;
        overflow: hidden;
        transition: all 0.3s;
    }

    .stat-card:hover {
        border-color: #667eea;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.15);
    }

    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }

    .stat-card h3 {
        font-size: 14px;
        text-transform: uppercase;
        color: #94a3b8;
        margin: 0 0 8px;
        letter-spacing: 0.5px;
    }

    .stat-card .value {
        font-size: 32px;
        font-weight: 700;
        color: #1e293b;
        margin-bottom: 6px;
    }

    .stat-card .subtext {
        color: #64748b;
        font-size: 13px;
    }

    .stat-trend {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-size: 12px;
        margin-top: 8px;
        padding: 4px 8px;
        border-radius: 12px;
        font-weight: 600;
    }

    .stat-trend.up {
        background: #dcfce7;
        color: #16a34a;
    }

    .stat-trend.down {
        background: #fee2e2;
        color: #dc2626;
    }

    .stat-trend.neutral {
        background: #f1f5f9;
        color: #64748b;
    }

    .panel-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 16px;
    }

    .panel-grid .insights-card h4 {
        font-size: 16px;
        font-weight: 600;
        color: #1e293b;
        margin-bottom: 16px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .panel-grid .insights-card h4 .badge {
        font-size: 11px;
        padding: 4px 8px;
        border-radius: 12px;
        background: #f1f5f9;
        color: #64748b;
        font-weight: 600;
    }

    .list-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 0;
        border-bottom: 1px solid #f1f5f9;
        transition: background 0.2s;
    }

    .list-item:hover {
        background: #f8fafc;
        margin: 0 -12px;
        padding: 12px;
        border-radius: 8px;
    }

    .list-item:last-child {
        border-bottom: none;
    }

    .progress-bar {
        background: #e2e8f0;
        border-radius: 999px;
        height: 8px;
        width: 100%;
        overflow: hidden;
        margin-top: 8px;
    }

    .progress-bar span {
        display: block;
        height: 100%;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        transition: width 0.6s ease;
    }

    .recent-table {
        width: 100%;
        border-collapse: collapse;
    }

    .recent-table th,
    .recent-table td {
        padding: 12px 8px;
        border-bottom: 1px solid #f1f5f9;
        font-size: 14px;
        color: #475569;
        text-align: left;
    }

    .recent-table th {
        text-transform: uppercase;
        font-size: 12px;
        letter-spacing: 0.5px;
        color: #94a3b8;
        font-weight: 600;
    }

    .recent-table tbody tr {
        transition: background 0.2s;
    }

    .recent-table tbody tr:hover {
        background: #f8fafc;
    }

    .recent-table a {
        color: #667eea;
        text-decoration: none;
        font-weight: 500;
        transition: color 0.2s;
    }

    .recent-table a:hover {
        color: #764ba2;
        text-decoration: underline;
    }

    .empty-state {
        text-align: center;
        padding: 32px 24px;
        color: #94a3b8;
    }

    .empty-state svg {
        width: 48px;
        height: 48px;
        margin-bottom: 12px;
        opacity: 0.5;
    }

    .chart-container {
        position: relative;
        height: 300px;
        margin-top: 16px;
    }

    .status-badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 600;
        text-transform: capitalize;
    }

    .status-badge.active {
        background: #dcfce7;
        color: #16a34a;
    }

    .status-badge.inactive {
        background: #fee2e2;
        color: #dc2626;
    }

    .status-badge.new {
        background: #dbeafe;
        color: #2563eb;
    }

    .filter-section {
        background: white;
        border-radius: 12px;
        padding: 16px;
        margin-bottom: 24px;
        border: 1px solid #e2e8f0;
    }

    .filter-section .form-control, .filter-section .btn {
        border-radius: 8px;
    }

    .icon-wrapper {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 12px;
    }

    .icon-wrapper.clients {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }

    .icon-wrapper.matters {
        background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        color: white;
    }

    .icon-wrapper.leads {
        background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        color: white;
    }
</style>
@endsection

@section('content')
<div class="insights-container">
    <div class="insights-header">
        <div>
            <h1>Performance Insights</h1>
            <p class="text-muted mb-0">High-level snapshot of clients, matters, and leads</p>
        </div>
        <ul class="nav nav-pills insight-tabs">
            <li class="nav-item">
                <a class="nav-link {{ $section === 'clients' ? 'active' : '' }}" href="{{ route('clients.insights', ['section' => 'clients']) }}">Clients</a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ $section === 'matters' ? 'active' : '' }}" href="{{ route('clients.insights', ['section' => 'matters']) }}">Matters</a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ $section === 'leads' ? 'active' : '' }}" href="{{ route('clients.insights', ['section' => 'leads']) }}">Leads</a>
            </li>
        </ul>
    </div>

    @if($section === 'clients')
        <div class="stats-grid">
            <div class="stat-card">
                <div class="icon-wrapper clients">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
                        <path d="M7 14s-1 0-1-1 1-4 5-4 5 3 5 4-1 1-1 1H7zm4-6a3 3 0 1 0 0-6 3 3 0 0 0 0 6z"/>
                        <path fill-rule="evenodd" d="M5.216 14A2.238 2.238 0 0 1 5 13c0-1.355.68-2.75 1.936-3.72A6.325 6.325 0 0 0 5 9c-4 0-5 3-5 4s1 1 1 1h4.216z"/>
                        <path d="M4.5 8a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5z"/>
                    </svg>
                </div>
                <h3>Total Clients</h3>
                <div class="value">{{ number_format($clientStats['total']) }}</div>
                <div class="subtext">All active client records</div>
                @if($clientStats['new30'] > 0)
                    <div class="stat-trend up">
                        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor" viewBox="0 0 16 16">
                            <path fill-rule="evenodd" d="M8 15a.5.5 0 0 0 .5-.5V2.707l3.146 3.147a.5.5 0 0 0 .708-.708l-4-4a.5.5 0 0 0-.708 0l-4 4a.5.5 0 1 0 .708.708L7.5 2.707V14.5a.5.5 0 0 0 .5.5z"/>
                        </svg>
                        +{{ $clientStats['new30'] }} this month
                    </div>
                @endif
            </div>
            <div class="stat-card">
                <div class="icon-wrapper clients">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
                        <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zM8 4a.905.905 0 0 0-.9.995l.35 3.507a.552.552 0 0 0 1.1 0l.35-3.507A.905.905 0 0 0 8 4zm.002 6a1 1 0 1 0 0 2 1 1 0 0 0 0-2z"/>
                    </svg>
                </div>
                <h3>New (30 Days)</h3>
                <div class="value">{{ number_format($clientStats['new30']) }}</div>
                <div class="subtext">Onboarded within last month</div>
            </div>
            <div class="stat-card">
                <div class="icon-wrapper clients">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
                        <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z"/>
                        <path d="M7.002 11a1 1 0 1 1 2 0 1 1 0 0 1-2 0zM7.1 4.995a.905.905 0 1 1 1.8 0l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 4.995z"/>
                    </svg>
                </div>
                <h3>Inactive</h3>
                <div class="value">{{ number_format($clientStats['inactive']) }}</div>
                <div class="subtext">Have been paused or inactive</div>
            </div>
            <div class="stat-card">
                <div class="icon-wrapper clients">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
                        <path d="M2.5 3.5a.5.5 0 0 1 0-1h11a.5.5 0 0 1 0 1h-11zm2-2a.5.5 0 0 1 0-1h7a.5.5 0 0 1 0 1h-7zM0 13a1.5 1.5 0 0 0 1.5 1.5h13A1.5 1.5 0 0 0 16 13V6a1.5 1.5 0 0 0-1.5-1.5h-13A1.5 1.5 0 0 0 0 6v7z"/>
                    </svg>
                </div>
                <h3>Archived</h3>
                <div class="value">{{ number_format($clientStats['archived']) }}</div>
                <div class="subtext">Moved out of the active pipeline</div>
            </div>
        </div>

        <div class="panel-grid">
            <div class="insights-card">
                <h4>
                    Status Breakdown
                    <span class="badge">{{ $clientStats['total'] }} Total</span>
                </h4>
                @forelse($clientStatusBreakdown as $row)
                    @php
                        $percentage = $clientStats['total'] ? round(($row->total / $clientStats['total']) * 100, 1) : 0;
                    @endphp
                    <div class="list-item">
                        <div style="flex: 1;">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <strong>{{ $row->label }}</strong>
                                <span class="text-muted">{{ $percentage }}%</span>
                            </div>
                            <div class="progress-bar">
                                <span style="width: {{ $percentage }}%"></span>
                            </div>
                        </div>
                        <span style="font-size: 18px; font-weight: 600; margin-left: 16px;">{{ number_format($row->total) }}</span>
                    </div>
                @empty
                    <div class="empty-state">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z"/>
                            <path d="M8 4a.905.905 0 0 0-.9.995l.35 3.507a.552.552 0 0 0 1.1 0l.35-3.507A.905.905 0 0 0 8 4zm.002 6a1 1 0 1 0 0 2 1 1 0 0 0 0-2z"/>
                        </svg>
                        <div>No status data available</div>
                    </div>
                @endforelse
            </div>
            <div class="insights-card">
                <h4>Monthly Growth</h4>
                <div class="chart-container">
                    <canvas id="clientMonthlyChart"></canvas>
                </div>
            </div>
            <div class="insights-card">
                <h4>
                    Recently Added Clients
                    <span class="badge">Last 5</span>
                </h4>
                @if($recentClients->isEmpty())
                    <div class="empty-state">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z"/>
                            <path d="M8 4a.905.905 0 0 0-.9.995l.35 3.507a.552.552 0 0 0 1.1 0l.35-3.507A.905.905 0 0 0 8 4zm.002 6a1 1 0 1 0 0 2 1 1 0 0 0 0-2z"/>
                        </svg>
                        <div>No recent clients</div>
                    </div>
                @else
                    <table class="recent-table">
                        <thead>
                            <tr>
                                <th>Client</th>
                                <th>Client ID</th>
                                <th>Status</th>
                                <th>Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($recentClients as $client)
                                <tr>
                                    <td>
                                        <a href="{{ route('clients.edit', ['id' => $client->id]) }}">
                                            {{ $client->first_name }} {{ $client->last_name }}
                                        </a>
                                    </td>
                                    <td>
                                        <a href="{{ route('clients.edit', ['id' => $client->id]) }}">
                                            {{ $client->client_id }}
                                        </a>
                                    </td>
                                    <td>
                                        <span class="status-badge {{ $client->status == 1 ? 'active' : 'inactive' }}">
                                            {{ $client->status == 1 ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                    <td>{{ Carbon\Carbon::parse($client->created_at)->format('d M Y') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    @elseif($section === 'matters')
        <div class="stats-grid">
            <div class="stat-card">
                <div class="icon-wrapper matters">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
                        <path d="M2.5 1A1.5 1.5 0 0 0 1 2.5v11A1.5 1.5 0 0 0 2.5 15h11a1.5 1.5 0 0 0 1.5-1.5v-11A1.5 1.5 0 0 0 13.5 1h-11zm4 2h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5v-1a.5.5 0 0 1 .5-.5zm3 0h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5v-1a.5.5 0 0 1 .5-.5zM3.5 6h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5v-1a.5.5 0 0 1 .5-.5zm3 0h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5v-1a.5.5 0 0 1 .5-.5zm3 0h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5v-1a.5.5 0 0 1 .5-.5zM3.5 9h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5v-1a.5.5 0 0 1 .5-.5zm3 0h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5v-1a.5.5 0 0 1 .5-.5zm3 0h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5v-1a.5.5 0 0 1 .5-.5zM3.5 12h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5v-1a.5.5 0 0 1 .5-.5z"/>
                    </svg>
                </div>
                <h3>Total Matters</h3>
                <div class="value">{{ number_format($matterStats['total']) }}</div>
                <div class="subtext">Active client matters</div>
                @if($matterStats['new30'] > 0)
                    <div class="stat-trend up">
                        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor" viewBox="0 0 16 16">
                            <path fill-rule="evenodd" d="M8 15a.5.5 0 0 0 .5-.5V2.707l3.146 3.147a.5.5 0 0 0 .708-.708l-4-4a.5.5 0 0 0-.708 0l-4 4a.5.5 0 1 0 .708.708L7.5 2.707V14.5a.5.5 0 0 0 .5.5z"/>
                        </svg>
                        +{{ $matterStats['new30'] }} this month
                    </div>
                @endif
            </div>
            <div class="stat-card">
                <div class="icon-wrapper matters">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
                        <path d="M2 2a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v13.5a.5.5 0 0 1-.777.416L8 13.101l-5.223 2.815A.5.5 0 0 1 2 15.5V2zm2-1a1 1 0 0 0-1 1v12.566l4.723-2.482a.5.5 0 0 1 .554 0L13 14.566V2a1 1 0 0 0-1-1H4z"/>
                    </svg>
                </div>
                <h3>New (30 Days)</h3>
                <div class="value">{{ number_format($matterStats['new30']) }}</div>
                <div class="subtext">Opened over the last month</div>
            </div>
            <div class="stat-card">
                <div class="icon-wrapper matters">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
                        <path d="M7 14s-1 0-1-1 1-4 5-4 5 3 5 4-1 1-1 1H7zm4-6a3 3 0 1 0 0-6 3 3 0 0 0 0 6z"/>
                        <path fill-rule="evenodd" d="M5.216 14A2.238 2.238 0 0 1 5 13c0-1.355.68-2.75 1.936-3.72A6.325 6.325 0 0 0 5 9c-4 0-5 3-5 4s1 1 1 1h4.216z"/>
                        <path d="M4.5 8a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5z"/>
                    </svg>
                </div>
                <h3>Assigned to Agents</h3>
                <div class="value">{{ number_format($matterStats['assigned']) }}</div>
                <div class="subtext">Matters with clear ownership</div>
            </div>
        </div>

        <div class="panel-grid">
            <div class="insights-card">
                <h4>
                    Top Migration Agents
                    <span class="badge">Top 5</span>
                </h4>
                @forelse($mattersByAgent as $agent)
                    <div class="list-item">
                        <span>{{ $agent->agent_name }}</span>
                        <strong style="font-size: 18px;">{{ number_format($agent->total) }}</strong>
                    </div>
                @empty
                    <div class="empty-state">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z"/>
                            <path d="M8 4a.905.905 0 0 0-.9.995l.35 3.507a.552.552 0 0 0 1.1 0l.35-3.507A.905.905 0 0 0 8 4zm.002 6a1 1 0 1 0 0 2 1 1 0 0 0 0-2z"/>
                        </svg>
                        <div>No agent assignments recorded</div>
                    </div>
                @endforelse
            </div>
            <div class="insights-card">
                <h4>
                    Recent Matters
                    <span class="badge">Last 5</span>
                </h4>
                @if($recentMatters->isEmpty())
                    <div class="empty-state">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z"/>
                            <path d="M8 4a.905.905 0 0 0-.9.995l.35 3.507a.552.552 0 0 0 1.1 0l.35-3.507A.905.905 0 0 0 8 4zm.002 6a1 1 0 1 0 0 2 1 1 0 0 0 0-2z"/>
                        </svg>
                        <div>No recent matters</div>
                    </div>
                @else
                    <table class="recent-table">
                        <thead>
                            <tr>
                                <th>Matter #</th>
                                <th>Client</th>
                                <th>Agent</th>
                                <th>Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($recentMatters as $matter)
                                <tr>
                                    <td><strong>{{ $matter->client_unique_matter_no }}</strong></td>
                                    <td>{{ $matter->client_first_name }} {{ $matter->client_last_name }}</td>
                                    <td>
                                        @if($matter->agent_first_name)
                                            {{ $matter->agent_first_name }} {{ $matter->agent_last_name }}
                                        @else
                                            <span class="text-muted">Unassigned</span>
                                        @endif
                                    </td>
                                    <td>{{ Carbon\Carbon::parse($matter->created_at)->format('d M Y') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    @else
        <div class="stats-grid">
            <div class="stat-card">
                <div class="icon-wrapper leads">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
                        <path d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5zM1 4v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V4H1z"/>
                    </svg>
                </div>
                <h3>Total Leads</h3>
                <div class="value">{{ number_format($leadStats['total']) }}</div>
                <div class="subtext">Active leads in the funnel</div>
                @if($leadStats['new30'] > 0)
                    <div class="stat-trend up">
                        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor" viewBox="0 0 16 16">
                            <path fill-rule="evenodd" d="M8 15a.5.5 0 0 0 .5-.5V2.707l3.146 3.147a.5.5 0 0 0 .708-.708l-4-4a.5.5 0 0 0-.708 0l-4 4a.5.5 0 1 0 .708.708L7.5 2.707V14.5a.5.5 0 0 0 .5.5z"/>
                        </svg>
                        +{{ $leadStats['new30'] }} this month
                    </div>
                @endif
            </div>
            <div class="stat-card">
                <div class="icon-wrapper leads">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
                        <path d="M1 2.5A1.5 1.5 0 0 1 2.5 1h3A1.5 1.5 0 0 1 7 2.5v3A1.5 1.5 0 0 1 5.5 7h-3A1.5 1.5 0 0 1 1 5.5v-3zM2.5 2a.5.5 0 0 0-.5.5v3a.5.5 0 0 0 .5.5h3a.5.5 0 0 0 .5-.5v-3a.5.5 0 0 0-.5-.5h-3zm6.5.5A1.5 1.5 0 0 1 10.5 1h3A1.5 1.5 0 0 1 15 2.5v3A1.5 1.5 0 0 1 13.5 7h-3A1.5 1.5 0 0 1 9 5.5v-3zm1.5-.5a.5.5 0 0 0-.5.5v3a.5.5 0 0 0 .5.5h3a.5.5 0 0 0 .5-.5v-3a.5.5 0 0 0-.5-.5h-3zM1 10.5A1.5 1.5 0 0 1 2.5 9h3A1.5 1.5 0 0 1 7 10.5v3A1.5 1.5 0 0 1 5.5 15h-3A1.5 1.5 0 0 1 1 13.5v-3zm1.5-.5a.5.5 0 0 0-.5.5v3a.5.5 0 0 0 .5.5h3a.5.5 0 0 0 .5-.5v-3a.5.5 0 0 0-.5-.5h-3zm6.5.5A1.5 1.5 0 0 1 10.5 9h3a1.5 1.5 0 0 1 1.5 1.5v3a1.5 1.5 0 0 1-1.5 1.5h-3A1.5 1.5 0 0 1 9 13.5v-3zm1.5-.5a.5.5 0 0 0-.5.5v3a.5.5 0 0 0 .5.5h3a.5.5 0 0 0 .5-.5v-3a.5.5 0 0 0-.5-.5h-3z"/>
                    </svg>
                </div>
                <h3>New (30 Days)</h3>
                <div class="value">{{ number_format($leadStats['new30']) }}</div>
                <div class="subtext">Recently captured leads</div>
            </div>
            <div class="stat-card">
                <div class="icon-wrapper leads">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
                        <path d="M12.166 8.94c-.524 1.062-1.234 2.12-1.96 3.07A31.493 31.493 0 0 1 8 14.58a31.481 31.481 0 0 1-2.206-2.57c-.726-.95-1.436-2.008-1.96-3.07C3.304 7.867 3 6.862 3 6a5 5 0 0 1 10 0c0 .862-.305 1.867-.834 2.94zM8 16s6-5.686 6-10A6 6 0 0 0 2 6c0 4.314 6 10 6 10z"/>
                        <path d="M8 8a2 2 0 1 1 0-4 2 2 0 0 1 0 4zm0 1a3 3 0 1 0 0-6 3 3 0 0 0 0 6z"/>
                    </svg>
                </div>
                <h3>Assigned</h3>
                <div class="value">{{ number_format($leadStats['assigned']) }}</div>
                <div class="subtext">Actively owned by a team member</div>
            </div>
        </div>

        <div class="panel-grid">
            <div class="insights-card">
                <h4>
                    Status Breakdown
                    <span class="badge">{{ $leadStats['total'] }} Total</span>
                </h4>
                @forelse($leadsByStatus as $row)
                    <div class="list-item">
                        <span>{{ ucfirst($row->status ?? 'Unknown') }}</span>
                        <strong style="font-size: 18px;">{{ number_format($row->total) }}</strong>
                    </div>
                @empty
                    <div class="empty-state">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z"/>
                            <path d="M8 4a.905.905 0 0 0-.9.995l.35 3.507a.552.552 0 0 0 1.1 0l.35-3.507A.905.905 0 0 0 8 4zm.002 6a1 1 0 1 0 0 2 1 1 0 0 0 0-2z"/>
                        </svg>
                        <div>No lead statuses available</div>
                    </div>
                @endforelse
            </div>
            <div class="insights-card">
                <h4>
                    Quality Mix
                    <span class="badge">Quality Distribution</span>
                </h4>
                @forelse($leadsByQuality as $row)
                    <div class="list-item">
                        <span>{{ $row->lead_quality ?? 'Unrated' }}</span>
                        <strong style="font-size: 18px;">{{ number_format($row->total) }}</strong>
                    </div>
                @empty
                    <div class="empty-state">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z"/>
                            <path d="M8 4a.905.905 0 0 0-.9.995l.35 3.507a.552.552 0 0 0 1.1 0l.35-3.507A.905.905 0 0 0 8 4zm.002 6a1 1 0 1 0 0 2 1 1 0 0 0 0-2z"/>
                        </svg>
                        <div>No quality ratings available</div>
                    </div>
                @endforelse
            </div>
            <div class="insights-card">
                <h4>Monthly Intake</h4>
                <div class="chart-container">
                    <canvas id="leadMonthlyChart"></canvas>
                </div>
            </div>
            <div class="insights-card">
                <h4>
                    Recent Leads
                    <span class="badge">Last 5</span>
                </h4>
                @if($recentLeads->isEmpty())
                    <div class="empty-state">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z"/>
                            <path d="M8 4a.905.905 0 0 0-.9.995l.35 3.507a.552.552 0 0 0 1.1 0l.35-3.507A.905.905 0 0 0 8 4zm.002 6a1 1 0 1 0 0 2 1 1 0 0 0 0-2z"/>
                        </svg>
                        <div>No recent leads</div>
                    </div>
                @else
                    <table class="recent-table">
                        <thead>
                            <tr>
                                <th>Lead</th>
                                <th>Service</th>
                                <th>Status</th>
                                <th>Quality</th>
                                <th>Captured</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($recentLeads as $lead)
                                <tr>
                                    <td><strong>{{ $lead->first_name }} {{ $lead->last_name }}</strong></td>
                                    <td>{{ $lead->service ?? '—' }}</td>
                                    <td>
                                        <span class="status-badge new">
                                            {{ ucfirst($lead->status ?? 'unknown') }}
                                        </span>
                                    </td>
                                    <td>{{ $lead->lead_quality ?? 'Unrated' }}</td>
                                    <td>{{ Carbon\Carbon::parse($lead->created_at)->format('d M Y') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    @endif
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const gradientPurple = {
        start: 'rgba(102, 126, 234, 0.8)',
        end: 'rgba(118, 75, 162, 0.8)',
        border: 'rgba(102, 126, 234, 1)'
    };

    const gradientPink = {
        start: 'rgba(240, 147, 251, 0.8)',
        end: 'rgba(245, 87, 108, 0.8)',
        border: 'rgba(240, 147, 251, 1)'
    };

    const gradientBlue = {
        start: 'rgba(79, 172, 254, 0.8)',
        end: 'rgba(0, 242, 254, 0.8)',
        border: 'rgba(79, 172, 254, 1)'
    };

    Chart.defaults.font.family = "'Inter', sans-serif";
    Chart.defaults.color = '#64748b';

    @if($section === 'clients')
        // Client Monthly Growth Chart
        const clientCtx = document.getElementById('clientMonthlyChart');
        if (clientCtx) {
            const clientData = {
                labels: {!! json_encode($clientMonthlyGrowth->pluck('label')) !!},
                datasets: [{
                    label: 'New Clients',
                    data: {!! json_encode($clientMonthlyGrowth->pluck('total')) !!},
                    backgroundColor: function(context) {
                        const chart = context.chart;
                        const {ctx, chartArea} = chart;
                        if (!chartArea) return gradientPurple.start;
                        const gradient = ctx.createLinearGradient(0, chartArea.bottom, 0, chartArea.top);
                        gradient.addColorStop(0, gradientPurple.end);
                        gradient.addColorStop(1, gradientPurple.start);
                        return gradient;
                    },
                    borderColor: gradientPurple.border,
                    borderWidth: 2,
                    borderRadius: 8,
                    tension: 0.4
                }]
            };

            new Chart(clientCtx, {
                type: 'bar',
                data: clientData,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: 'rgba(30, 41, 59, 0.9)',
                            padding: 12,
                            titleColor: '#fff',
                            bodyColor: '#fff',
                            borderColor: 'rgba(102, 126, 234, 0.5)',
                            borderWidth: 1,
                            displayColors: false,
                            callbacks: {
                                label: function(context) {
                                    return context.parsed.y + ' new clients';
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            },
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
        }
    @elseif($section === 'leads')
        // Lead Monthly Intake Chart
        const leadCtx = document.getElementById('leadMonthlyChart');
        if (leadCtx) {
            const leadData = {
                labels: {!! json_encode($leadMonthlyGrowth->pluck('label')) !!},
                datasets: [{
                    label: 'New Leads',
                    data: {!! json_encode($leadMonthlyGrowth->pluck('total')) !!},
                    backgroundColor: function(context) {
                        const chart = context.chart;
                        const {ctx, chartArea} = chart;
                        if (!chartArea) return gradientBlue.start;
                        const gradient = ctx.createLinearGradient(0, chartArea.bottom, 0, chartArea.top);
                        gradient.addColorStop(0, gradientBlue.end);
                        gradient.addColorStop(1, gradientBlue.start);
                        return gradient;
                    },
                    borderColor: gradientBlue.border,
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4
                }]
            };

            new Chart(leadCtx, {
                type: 'line',
                data: leadData,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: 'rgba(30, 41, 59, 0.9)',
                            padding: 12,
                            titleColor: '#fff',
                            bodyColor: '#fff',
                            borderColor: 'rgba(79, 172, 254, 0.5)',
                            borderWidth: 1,
                            displayColors: false,
                            callbacks: {
                                label: function(context) {
                                    return context.parsed.y + ' new leads';
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            },
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
        }
    @endif
});
</script>
@endsection
