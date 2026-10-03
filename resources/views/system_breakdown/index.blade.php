<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Breakdown & Error Monitor – Bansal Law CRM</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700&family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-page: #0b0f19;
            --bg-card: #111827;
            --bg-card-hover: #162032;
            --bg-elevated: #1f293d;
            --border-subtle: #26334d;
            --border-focus: #3b82f6;
            --text-primary: #f8fafc;
            --text-secondary: #94a3b8;
            --text-muted: #64748b;
            --accent-red: #ef4444;
            --accent-red-bg: rgba(239, 68, 68, 0.12);
            --accent-amber: #f59e0b;
            --accent-amber-bg: rgba(245, 158, 11, 0.12);
            --accent-green: #10b981;
            --accent-green-bg: rgba(16, 185, 129, 0.12);
            --accent-blue: #3b82f6;
            --accent-blue-bg: rgba(59, 130, 246, 0.12);
            --accent-purple: #8b5cf6;
            --accent-purple-bg: rgba(139, 92, 246, 0.12);
            --font-sans: 'Outfit', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            --font-mono: 'JetBrains Mono', monospace;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            background-color: var(--bg-page);
            color: var(--text-primary);
            font-family: var(--font-sans);
            font-size: 14px;
            line-height: 1.5;
            min-height: 100vh;
        }

        /* Top Header */
        header {
            background: linear-gradient(180deg, #131c2e 0%, var(--bg-page) 100%);
            border-bottom: 1px solid var(--border-subtle);
            padding: 18px 28px;
            position: sticky;
            top: 0;
            z-index: 50;
            backdrop-filter: blur(12px);
        }

        .header-inner {
            max-width: 1440px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
        }

        .brand-section {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .pulse-indicator {
            width: 12px;
            height: 12px;
            background-color: var(--accent-red);
            border-radius: 50%;
            position: relative;
            box-shadow: 0 0 12px var(--accent-red);
        }

        .pulse-indicator.healthy {
            background-color: var(--accent-green);
            box-shadow: 0 0 12px var(--accent-green);
        }

        .pulse-indicator::after {
            content: '';
            position: absolute;
            top: -4px;
            left: -4px;
            right: -4px;
            bottom: -4px;
            border-radius: 50%;
            border: 2px solid currentColor;
            opacity: 0.6;
            animation: pulse-ring 2s cubic-bezier(0.215, 0.61, 0.355, 1) infinite;
        }

        @keyframes pulse-ring {
            0% { transform: scale(0.6); opacity: 0.8; }
            100% { transform: scale(2.2); opacity: 0; }
        }

        .brand-title {
            font-size: 18px;
            font-weight: 700;
            letter-spacing: -0.02em;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .brand-subtitle {
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 2px;
        }

        .tag-pill {
            font-family: var(--font-mono);
            font-size: 11px;
            padding: 2px 8px;
            border-radius: 6px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .tag-pill.env {
            background: var(--accent-blue-bg);
            color: var(--accent-blue);
            border: 1px solid rgba(59, 130, 246, 0.3);
        }

        .tag-pill.live {
            background: var(--accent-green-bg);
            color: var(--accent-green);
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .btn {
            font-family: var(--font-sans);
            font-size: 13px;
            font-weight: 600;
            padding: 8px 14px;
            border-radius: 8px;
            border: 1px solid transparent;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.15s ease-in-out;
            text-decoration: none;
        }

        .btn-primary {
            background-color: var(--accent-blue);
            color: #ffffff;
        }
        .btn-primary:hover {
            background-color: #2563eb;
        }

        .btn-outline {
            background-color: var(--bg-card);
            border-color: var(--border-subtle);
            color: var(--text-secondary);
        }
        .btn-outline:hover {
            background-color: var(--bg-card-hover);
            color: var(--text-primary);
            border-color: #3b82f6;
        }

        .btn-danger-outline {
            background-color: var(--accent-red-bg);
            border-color: rgba(239, 68, 68, 0.3);
            color: #fca5a5;
        }
        .btn-danger-outline:hover {
            background-color: rgba(239, 68, 68, 0.25);
            border-color: var(--accent-red);
            color: #fff;
        }

        .btn-warning {
            background-color: var(--accent-amber-bg);
            border-color: rgba(245, 158, 11, 0.3);
            color: #fde68a;
        }
        .btn-warning:hover {
            background-color: rgba(245, 158, 11, 0.25);
            color: #fff;
        }

        /* Container */
        .container {
            max-width: 1440px;
            margin: 0 auto;
            padding: 24px 28px;
        }

        /* Alert notifications */
        .flash-alert {
            background: rgba(16, 185, 129, 0.15);
            border: 1px solid var(--accent-green);
            color: #a7f3d0;
            padding: 12px 18px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        /* Metrics Strip */
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .metric-card {
            background-color: var(--bg-card);
            border: 1px solid var(--border-subtle);
            border-radius: 12px;
            padding: 18px 20px;
            display: flex;
            flex-direction: column;
            gap: 6px;
            transition: transform 0.15s ease, border-color 0.15s ease;
        }

        .metric-card:hover {
            border-color: #334155;
            transform: translateY(-2px);
        }

        .metric-label {
            font-size: 12px;
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.05em;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .metric-value {
            font-size: 28px;
            font-weight: 700;
            letter-spacing: -0.03em;
            line-height: 1.1;
        }

        .metric-card.red .metric-value { color: var(--accent-red); }
        .metric-card.amber .metric-value { color: var(--accent-amber); }
        .metric-card.green .metric-value { color: var(--accent-green); }
        .metric-card.blue .metric-value { color: var(--accent-blue); }
        .metric-card.purple .metric-value { color: var(--accent-purple); }

        /* Toolbar & Filters */
        .toolbar {
            background-color: var(--bg-card);
            border: 1px solid var(--border-subtle);
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 20px;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
        }

        .search-box {
            flex: 1;
            min-width: 280px;
            position: relative;
        }

        .search-input {
            width: 100%;
            background-color: var(--bg-page);
            border: 1px solid var(--border-subtle);
            color: var(--text-primary);
            padding: 9px 14px 9px 36px;
            border-radius: 8px;
            font-family: var(--font-sans);
            font-size: 13px;
            outline: none;
            transition: border-color 0.15s ease;
        }

        .search-input:focus {
            border-color: var(--accent-blue);
        }

        .search-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 14px;
        }

        .filter-group {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .filter-select {
            background-color: var(--bg-page);
            border: 1px solid var(--border-subtle);
            color: var(--text-secondary);
            padding: 9px 12px;
            border-radius: 8px;
            font-family: var(--font-sans);
            font-size: 13px;
            outline: none;
            cursor: pointer;
        }

        .filter-select:focus {
            border-color: var(--accent-blue);
            color: var(--text-primary);
        }

        /* Tabs Navigation */
        .tabs-nav {
            display: flex;
            gap: 8px;
            border-bottom: 1px solid var(--border-subtle);
            margin-bottom: 20px;
        }

        .tab-link {
            padding: 10px 18px;
            font-weight: 600;
            font-size: 14px;
            color: var(--text-muted);
            text-decoration: none;
            border-bottom: 2px solid transparent;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: color 0.15s ease, border-color 0.15s ease;
        }

        .tab-link:hover {
            color: var(--text-primary);
        }

        .tab-link.active {
            color: var(--accent-blue);
            border-bottom-color: var(--accent-blue);
        }

        .tab-badge {
            background-color: var(--bg-elevated);
            color: var(--text-secondary);
            font-family: var(--font-mono);
            font-size: 11px;
            padding: 2px 7px;
            border-radius: 10px;
        }

        .tab-link.active .tab-badge {
            background-color: var(--accent-blue-bg);
            color: var(--accent-blue);
        }

        /* Error Cards List */
        .error-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .error-card {
            background-color: var(--bg-card);
            border: 1px solid var(--border-subtle);
            border-left: 4px solid var(--accent-red);
            border-radius: 10px;
            padding: 16px 20px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            transition: all 0.15s ease;
            cursor: pointer;
        }

        .error-card:hover {
            background-color: var(--bg-card-hover);
            border-color: #3b82f6;
            transform: translateX(2px);
        }

        .error-card.investigating {
            border-left-color: var(--accent-amber);
        }

        .error-card.resolved {
            border-left-color: var(--accent-green);
            opacity: 0.75;
        }

        .error-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
        }

        .error-title-wrap {
            display: flex;
            flex-direction: column;
            gap: 4px;
            flex: 1;
        }

        .error-title {
            font-family: var(--font-mono);
            font-size: 14px;
            font-weight: 700;
            color: #f87171;
            word-break: break-word;
        }

        .error-card.resolved .error-title {
            color: #34d399;
            text-decoration: line-through;
        }

        .error-meta-strip {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            font-size: 12px;
            color: var(--text-muted);
        }

        .badge {
            font-family: var(--font-mono);
            font-size: 11px;
            padding: 3px 8px;
            border-radius: 6px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .badge-red { background: var(--accent-red-bg); color: #fca5a5; }
        .badge-amber { background: var(--accent-amber-bg); color: #fde68a; }
        .badge-green { background: var(--accent-green-bg); color: #a7f3d0; }
        .badge-blue { background: var(--accent-blue-bg); color: #93c5fd; }
        .badge-purple { background: var(--accent-purple-bg); color: #d8b4fe; }
        .badge-gray { background: var(--bg-elevated); color: var(--text-secondary); }

        .badge-count {
            background: #ef4444;
            color: #fff;
            font-weight: 700;
            padding: 2px 7px;
            border-radius: 12px;
            font-size: 11px;
        }

        .code-location {
            font-family: var(--font-mono);
            color: #e2e8f0;
            background: rgba(15, 23, 42, 0.7);
            padding: 2px 6px;
            border-radius: 4px;
            border: 1px solid rgba(255, 255, 255, 0.08);
        }

        .error-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 12px;
            color: var(--text-secondary);
            border-top: 1px solid rgba(255, 255, 255, 0.04);
            padding-top: 8px;
            margin-top: 2px;
        }

        .user-tag {
            display: flex;
            align-items: center;
            gap: 6px;
            color: var(--text-primary);
        }

        .user-avatar-circle {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: var(--accent-blue);
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            font-weight: 700;
        }

        .card-actions {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Empty state */
        .empty-state {
            background-color: var(--bg-card);
            border: 1px dashed var(--border-subtle);
            border-radius: 14px;
            padding: 60px 20px;
            text-align: center;
        }

        .empty-icon {
            font-size: 42px;
            margin-bottom: 12px;
        }

        .empty-title {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 6px;
        }

        .empty-desc {
            color: var(--text-muted);
            max-width: 420px;
            margin: 0 auto 20px auto;
        }

        /* Detail Modal / Drawer */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(3, 7, 18, 0.85);
            backdrop-filter: blur(8px);
            z-index: 100;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal-content {
            background-color: var(--bg-card);
            border: 1px solid var(--border-subtle);
            border-radius: 14px;
            width: 100%;
            max-width: 960px;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            animation: modal-pop 0.2s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes modal-pop {
            0% { transform: scale(0.96); opacity: 0; }
            100% { transform: scale(1); opacity: 1; }
        }

        .modal-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--border-subtle);
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
        }

        .modal-body {
            padding: 24px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .modal-footer {
            padding: 16px 24px;
            border-top: 1px solid var(--border-subtle);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            background-color: rgba(11, 15, 25, 0.5);
        }

        .close-btn {
            background: none;
            border: none;
            color: var(--text-muted);
            font-size: 20px;
            cursor: pointer;
            line-height: 1;
        }
        .close-btn:hover { color: #fff; }

        /* Code Snippet Box */
        .code-box {
            background-color: #050811;
            border: 1px solid #1e293b;
            border-radius: 8px;
            overflow: hidden;
            font-family: var(--font-mono);
            font-size: 12px;
        }

        .code-box-header {
            background-color: #0f172a;
            padding: 8px 14px;
            border-bottom: 1px solid #1e293b;
            color: var(--text-secondary);
            font-size: 11px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .code-line {
            display: flex;
            padding: 3px 12px;
            line-height: 1.6;
        }

        .code-line.error-target {
            background-color: rgba(239, 68, 68, 0.2);
            color: #fca5a5;
            font-weight: 700;
        }

        .line-num {
            width: 48px;
            color: #475569;
            user-select: none;
            text-align: right;
            padding-right: 14px;
        }

        .code-line.error-target .line-num {
            color: #ef4444;
            font-weight: 700;
        }

        .line-text {
            white-space: pre-wrap;
            word-break: break-all;
        }

        /* Stack trace pre */
        .trace-pre {
            background-color: #050811;
            border: 1px solid #1e293b;
            border-radius: 8px;
            padding: 14px;
            font-family: var(--font-mono);
            font-size: 11px;
            line-height: 1.6;
            color: #cbd5e1;
            max-height: 280px;
            overflow-y: auto;
            white-space: pre-wrap;
        }

        .param-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }

        .param-table th, .param-table td {
            padding: 8px 12px;
            border: 1px solid var(--border-subtle);
            text-align: left;
        }

        .param-table th {
            background-color: var(--bg-elevated);
            color: var(--text-secondary);
            font-weight: 600;
        }

        /* Storage log card */
        .log-entry-card {
            background-color: var(--bg-card);
            border: 1px solid var(--border-subtle);
            border-radius: 8px;
            padding: 14px 18px;
            display: flex;
            flex-direction: column;
            gap: 8px;
            font-family: var(--font-mono);
            font-size: 12px;
        }

        .log-entry-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
    </style>
</head>
<body>

    <!-- Header -->
    <header>
        <div class="header-inner">
            <div class="brand-section">
                <div class="pulse-indicator {{ $stats['total_open'] > 0 ? '' : 'healthy' }}"></div>
                <div>
                    <div class="brand-title">
                        Bansal Law CRM – System Breakdown Monitor
                        <span class="tag-pill env">{{ config('app.env') }}</span>
                        <span class="tag-pill live">Listening Active</span>
                    </div>
                    <div class="brand-subtitle">
                        Live real-time exception capturer, diagnostics, and stack-trace inspector for all CRM users
                    </div>
                </div>
            </div>

            <div class="header-actions">
                <a href="{{ route('system_errors.simulate') }}" class="btn btn-warning" title="Simulate a test exception to verify capture">
                    ⚡ Simulate Test Error
                </a>
                <a href="{{ route('system_errors.index') }}" class="btn btn-outline" title="Refresh dashboard">
                    🔄 Refresh
                </a>
                <form action="{{ route('system_errors.clear') }}" method="POST" onsubmit="return confirm('Clear all resolved errors?');" style="display:inline;">
                    @csrf
                    <input type="hidden" name="scope" value="resolved">
                    <button type="submit" class="btn btn-outline">
                        🧹 Clear Resolved
                    </button>
                </form>
            </div>
        </div>
    </header>

    <div class="container">

        <!-- Flash messages -->
        @if(session('success'))
            <div class="flash-alert">
                <span>✅ {{ session('success') }}</span>
                <button type="button" onclick="this.parentElement.remove()" style="background:none;border:none;color:#fff;cursor:pointer;">✕</button>
            </div>
        @endif

        <!-- Metrics Cards -->
        <div class="metrics-grid">
            <div class="metric-card red">
                <div class="metric-label">
                    <span>Open Breakdowns</span>
                    <span>⚠️</span>
                </div>
                <div class="metric-value">{{ $stats['total_open'] }}</div>
            </div>

            <div class="metric-card amber">
                <div class="metric-label">
                    <span>Under Investigation</span>
                    <span>🔍</span>
                </div>
                <div class="metric-value">{{ $stats['total_investigating'] }}</div>
            </div>

            <div class="metric-card green">
                <div class="metric-label">
                    <span>Resolved Issues</span>
                    <span>✅</span>
                </div>
                <div class="metric-value">{{ $stats['total_resolved'] }}</div>
            </div>

            <div class="metric-card purple">
                <div class="metric-label">
                    <span>Failures Today</span>
                    <span>📅</span>
                </div>
                <div class="metric-value">{{ $stats['today_occurrences'] }}</div>
            </div>

            <div class="metric-card blue">
                <div class="metric-label">
                    <span>Impacted Users</span>
                    <span>👥</span>
                </div>
                <div class="metric-value">{{ $stats['unique_users'] }}</div>
            </div>
        </div>

        <!-- Toolbar / Search / Filters -->
        <form method="GET" action="{{ route('system_errors.index') }}" class="toolbar">
            <input type="hidden" name="tab" value="{{ $activeTab }}">
            <div class="search-box">
                <span class="search-icon">🔍</span>
                <input type="text" name="search" class="search-input" placeholder="Search by error message, exception class, file path, URL, or user email..." value="{{ request('search') }}">
            </div>

            <div class="filter-group">
                <select name="status" class="filter-select" onchange="this.form.submit()">
                    <option value="open" {{ request('status', 'open') === 'open' ? 'selected' : '' }}>Status: Open & Active</option>
                    <option value="investigating" {{ request('status') === 'investigating' ? 'selected' : '' }}>Status: Investigating</option>
                    <option value="resolved" {{ request('status') === 'resolved' ? 'selected' : '' }}>Status: Resolved Only</option>
                    <option value="all" {{ request('status') === 'all' ? 'selected' : '' }}>Status: All Records</option>
                </select>

                <select name="timeframe" class="filter-select" onchange="this.form.submit()">
                    <option value="all" {{ request('timeframe') === 'all' ? 'selected' : '' }}>Time: All Time</option>
                    <option value="today" {{ request('timeframe') === 'today' ? 'selected' : '' }}>Time: Today Only</option>
                    <option value="7days" {{ request('timeframe') === '7days' ? 'selected' : '' }}>Time: Past 7 Days</option>
                </select>

                <button type="submit" class="btn btn-primary">Filter</button>
                @if(request()->anyFilled(['search', 'status', 'timeframe']))
                    <a href="{{ route('system_errors.index', ['tab' => $activeTab]) }}" class="btn btn-outline">Reset</a>
                @endif
            </div>
        </form>

        <!-- Navigation Tabs -->
        <div class="tabs-nav">
            <a href="{{ route('system_errors.index', array_merge(request()->query(), ['tab' => 'db'])) }}" class="tab-link {{ $activeTab === 'db' ? 'active' : '' }}">
                <span>Captured System Breakdowns</span>
                <span class="tab-badge">{{ $errors->total() }}</span>
            </a>
            <a href="{{ route('system_errors.index', array_merge(request()->query(), ['tab' => 'logs'])) }}" class="tab-link {{ $activeTab === 'logs' ? 'active' : '' }}">
                <span>Storage Log Stream (laravel.log)</span>
                <span class="tab-badge">{{ count($storageLogs) }}</span>
            </a>
        </div>

        @if($activeTab === 'db')
            <!-- TAB 1: Database Captured Exceptions -->
            @if($errors->count() > 0)
                <div class="error-list">
                    @foreach($errors as $item)
                        <div class="error-card {{ $item->status }}" onclick="openErrorDetail({{ $item->id }})">
                            <div class="error-header">
                                <div class="error-title-wrap">
                                    <div class="error-meta-strip">
                                        <span class="badge {{ $item->status_code >= 500 ? 'badge-red' : 'badge-amber' }}">
                                            {{ $item->http_method ?? 'ANY' }} {{ $item->status_code ?? '500' }}
                                        </span>
                                        <span class="badge badge-purple">
                                            {{ class_basename($item->exception_class) }}
                                        </span>
                                        @if($item->occurrence_count > 1)
                                            <span class="badge-count" title="This exact error occurred {{ $item->occurrence_count }} times">
                                                {{ $item->occurrence_count }}× Occurrences
                                            </span>
                                        @endif
                                        <span class="code-location" title="{{ $item->file }}">
                                            {{ $item->short_file }}
                                        </span>
                                        <span class="badge badge-gray">
                                            {{ $item->status }}
                                        </span>
                                    </div>
                                    <div class="error-title">
                                        {{ $item->message }}
                                    </div>
                                    <div style="font-size: 12px; color: var(--text-muted); font-family: var(--font-mono);">
                                        URL: <span style="color: #93c5fd;">{{ $item->url ?? 'CLI/Background' }}</span>
                                    </div>
                                </div>

                                <div class="card-actions" onclick="event.stopPropagation()">
                                    <button type="button" class="btn btn-outline" style="padding: 4px 10px; font-size: 12px;" onclick="openErrorDetail({{ $item->id }})">
                                        Inspect
                                    </button>
                                    @if($item->status !== 'resolved')
                                        <form action="{{ route('system_errors.update_status', $item->id) }}" method="POST" style="display:inline;">
                                            @csrf
                                            <input type="hidden" name="status" value="resolved">
                                            <button type="submit" class="btn btn-outline" style="padding: 4px 10px; font-size: 12px; color: #10b981;">
                                                Resolve
                                            </button>
                                        </form>
                                    @else
                                        <form action="{{ route('system_errors.update_status', $item->id) }}" method="POST" style="display:inline;">
                                            @csrf
                                            <input type="hidden" name="status" value="open">
                                            <button type="submit" class="btn btn-outline" style="padding: 4px 10px; font-size: 12px;">
                                                Reopen
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </div>

                            <div class="error-footer">
                                <div class="user-tag">
                                    <span class="user-avatar-circle">
                                        {{ strtoupper(substr($item->user_name ?: 'G', 0, 1)) }}
                                    </span>
                                    <span>{{ $item->user_name }}</span>
                                    @if($item->user_email)
                                        <span style="color: var(--text-muted);">({{ $item->user_email }})</span>
                                    @endif
                                    @if($item->ip_address)
                                        <span style="color: var(--text-muted); font-family: var(--font-mono);">• {{ $item->ip_address }}</span>
                                    @endif
                                </div>
                                <div style="font-family: var(--font-mono); color: var(--text-muted);">
                                    Last seen: <span style="color: var(--text-secondary);">{{ $item->relative_last_seen }}</span> ({{ $item->last_seen_at ? $item->last_seen_at->format('d M Y, H:i:s') : 'N/A' }})
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div style="margin-top: 24px;">
                    {{ $errors->links() }}
                </div>
            @else
                <div class="empty-state">
                    <div class="empty-icon">🎉</div>
                    <div class="empty-title">Zero System Breakdowns Found</div>
                    <div class="empty-desc">
                        No errors match your current filter criteria. Any error encountered by any user in the CRM will automatically be captured and displayed here in real time.
                    </div>
                    <a href="{{ route('system_errors.simulate') }}" class="btn btn-primary">
                        ⚡ Simulate Test Error to Verify
                    </a>
                </div>
            @endif

        @else
            <!-- TAB 2: Storage Logs Stream (laravel.log) -->
            @if(count($storageLogs) > 0)
                <div class="error-list">
                    @foreach($storageLogs as $log)
                        <div class="log-entry-card">
                            <div class="log-entry-header">
                                <div style="display:flex; align-items:center; gap:8px;">
                                    <span class="badge {{ $log['level'] === 'ERROR' || $log['level'] === 'CRITICAL' ? 'badge-red' : 'badge-amber' }}">
                                        {{ $log['level'] }}
                                    </span>
                                    <span style="color: var(--text-muted);">{{ $log['file'] }}</span>
                                </div>
                                <span style="color: var(--text-muted);">{{ $log['timestamp'] }}</span>
                            </div>
                            <div style="color: #fca5a5; font-weight: 600;">
                                {{ $log['title'] }}
                            </div>
                            @if(!empty($log['stack']))
                                <pre style="background: rgba(0,0,0,0.5); padding: 10px; border-radius: 6px; font-size: 11px; overflow-x: auto; color: #cbd5e1;">{{ $log['stack'] }}</pre>
                            @endif
                        </div>
                    @endforeach
                </div>
            @else
                <div class="empty-state">
                    <div class="empty-icon">📜</div>
                    <div class="empty-title">Log Files Empty</div>
                    <div class="empty-desc">No entries found in storage/logs/laravel-*.log.</div>
                </div>
            @endif
        @endif

    </div>

    <!-- Inspector Modal -->
    <div id="errorModal" class="modal-overlay" onclick="closeErrorModal(event)">
        <div class="modal-content" onclick="event.stopPropagation()">
            <div class="modal-header">
                <div>
                    <div id="modalExceptionClass" class="badge badge-purple" style="margin-bottom: 6px;">Exception</div>
                    <div id="modalMessage" style="font-family: var(--font-mono); font-size: 15px; font-weight: 700; color: #f87171;"></div>
                    <div id="modalLocation" style="font-family: var(--font-mono); font-size: 12px; color: var(--text-muted); margin-top: 4px;"></div>
                </div>
                <button type="button" class="close-btn" onclick="closeModalDirect()">✕</button>
            </div>

            <div class="modal-body">
                <!-- Code Snippet -->
                <div id="codeSnippetWrapper">
                    <div class="code-box">
                        <div class="code-box-header">
                            <span id="codeFilePath">Source Code Snippet</span>
                            <span>Exact line highlighted</span>
                        </div>
                        <div id="codeLinesContainer"></div>
                    </div>
                </div>

                <!-- User & Request Context -->
                <div>
                    <h4 style="font-size: 13px; font-weight: 700; color: var(--text-secondary); margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.05em;">
                        Request & User Context
                    </h4>
                    <table class="param-table">
                        <tr>
                            <th style="width: 160px;">URL</th>
                            <td id="modalUrl" style="font-family: var(--font-mono); color: #93c5fd;"></td>
                        </tr>
                        <tr>
                            <th>HTTP Method</th>
                            <td id="modalMethod" style="font-family: var(--font-mono);"></td>
                        </tr>
                        <tr>
                            <th>User Name / Email</th>
                            <td id="modalUser"></td>
                        </tr>
                        <tr>
                            <th>User Role</th>
                            <td id="modalRole"></td>
                        </tr>
                        <tr>
                            <th>IP & User Agent</th>
                            <td id="modalClientInfo" style="font-family: var(--font-mono); font-size: 11px;"></td>
                        </tr>
                        <tr>
                            <th>First / Last Seen</th>
                            <td id="modalTimes" style="font-family: var(--font-mono);"></td>
                        </tr>
                    </table>
                </div>

                <!-- Request Payload -->
                <div id="payloadSection">
                    <h4 style="font-size: 13px; font-weight: 700; color: var(--text-secondary); margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.05em;">
                        Submitted Form Data / Payload (Sanitized)
                    </h4>
                    <pre id="modalPayload" style="background: #050811; border: 1px solid #1e293b; border-radius: 8px; padding: 12px; font-family: var(--font-mono); font-size: 11px; overflow-x: auto; color: #a5f3fc;"></pre>
                </div>

                <!-- Stack Trace -->
                <div>
                    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom: 8px;">
                        <h4 style="font-size: 13px; font-weight: 700; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.05em;">
                            Cleaned Stack Trace
                        </h4>
                        <button type="button" class="btn btn-outline" style="padding: 2px 8px; font-size: 11px;" onclick="copyFullError()">
                            📋 Copy Error Report
                        </button>
                    </div>
                    <pre id="modalStackTrace" class="trace-pre"></pre>
                </div>
            </div>

            <div class="modal-footer">
                <div style="display:flex; align-items:center; gap: 8px;">
                    <span id="modalStatusBadge" class="badge badge-gray">open</span>
                    <span id="modalOccurrences" style="font-family: var(--font-mono); font-size: 12px; color: var(--text-muted);"></span>
                </div>

                <div style="display:flex; align-items:center; gap: 8px;">
                    <button type="button" id="btnMarkInvestigating" class="btn btn-warning" onclick="changeCurrentStatus('investigating')">
                        Investigate
                    </button>
                    <button type="button" id="btnMarkResolved" class="btn btn-primary" onclick="changeCurrentStatus('resolved')">
                        Mark as Resolved
                    </button>
                    <button type="button" class="btn btn-outline" onclick="closeModalDirect()">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentErrorData = null;

        function openErrorDetail(errorId) {
            fetch('{{ url("system-errors/details") }}/' + errorId, {
                headers: { 'Accept': 'application/json' }
            })
            .then(res => res.json())
            .then(res => {
                if (!res.success) {
                    alert('Could not load error details');
                    return;
                }

                currentErrorData = res.data;
                const err = res.data;

                document.getElementById('modalExceptionClass').textContent = err.exception_class || 'Exception';
                document.getElementById('modalMessage').textContent = err.message || '';
                document.getElementById('modalLocation').textContent = (err.file || 'Unknown') + (err.line ? (':' + err.line) : '');

                document.getElementById('modalUrl').textContent = (err.http_method ? err.http_method + ' ' : '') + (err.url || 'CLI / Background');
                document.getElementById('modalMethod').textContent = err.http_method || 'N/A';
                document.getElementById('modalUser').textContent = (err.user_name || 'Guest') + (err.user_email ? ' (' + err.user_email + ')' : '');
                document.getElementById('modalRole').textContent = err.user_role || 'N/A';
                document.getElementById('modalClientInfo').textContent = (err.ip_address || '127.0.0.1') + ' • ' + (err.user_agent || 'N/A');
                document.getElementById('modalTimes').textContent = (err.first_seen_at || '') + ' → ' + (err.last_seen_at || '');

                document.getElementById('modalStatusBadge').textContent = err.status.toUpperCase();
                document.getElementById('modalOccurrences').textContent = (err.occurrence_count || 1) + ' occurrence(s)';

                // Code snippet
                const linesContainer = document.getElementById('codeLinesContainer');
                linesContainer.innerHTML = '';
                if (res.code_snippet && res.code_snippet.length > 0) {
                    document.getElementById('codeSnippetWrapper').style.display = 'block';
                    document.getElementById('codeFilePath').textContent = err.file + ':' + err.line;

                    res.code_snippet.forEach(line => {
                        const row = document.createElement('div');
                        row.className = 'code-line' + (line.is_error_line ? ' error-target' : '');
                        row.innerHTML = `<span class="line-num">${line.line_number}</span><span class="line-text">${escapeHtml(line.content)}</span>`;
                        linesContainer.appendChild(row);
                    });
                } else {
                    document.getElementById('codeSnippetWrapper').style.display = 'none';
                }

                // Payload
                const payloadStr = JSON.stringify(err.request_payload, null, 2);
                document.getElementById('modalPayload').textContent = payloadStr || 'No input payload sent with this request.';

                // Stack trace
                document.getElementById('modalStackTrace').textContent = err.stack_trace || 'No trace recorded.';

                // Buttons state
                const resolveBtn = document.getElementById('btnMarkResolved');
                if (err.status === 'resolved') {
                    resolveBtn.textContent = 'Reopen Issue';
                    resolveBtn.onclick = () => changeCurrentStatus('open');
                } else {
                    resolveBtn.textContent = 'Mark as Resolved';
                    resolveBtn.onclick = () => changeCurrentStatus('resolved');
                }

                document.getElementById('errorModal').classList.add('active');
            })
            .catch(e => {
                console.error(e);
                alert('Error loading details.');
            });
        }

        function closeErrorModal(e) {
            if (e.target.id === 'errorModal') {
                closeModalDirect();
            }
        }

        function closeModalDirect() {
            document.getElementById('errorModal').classList.remove('active');
        }

        function changeCurrentStatus(newStatus) {
            if (!currentErrorData) return;

            const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            fetch('{{ url("system-errors/status") }}/' + currentErrorData.id, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                body: JSON.stringify({ status: newStatus })
            })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    window.location.reload();
                } else {
                    alert('Failed to update status.');
                }
            });
        }

        function copyFullError() {
            if (!currentErrorData) return;
            const text = `### SYSTEM BREAKDOWN ERROR REPORT
**Error:** ${currentErrorData.message}
**Exception:** ${currentErrorData.exception_class}
**Location:** ${currentErrorData.file}:${currentErrorData.line}
**URL:** ${currentErrorData.http_method} ${currentErrorData.url}
**User:** ${currentErrorData.user_name} (${currentErrorData.user_email || 'No email'})
**Occurrences:** ${currentErrorData.occurrence_count}
**Last Seen:** ${currentErrorData.last_seen_at}

\`\`\`
${currentErrorData.stack_trace}
\`\`\``;

            navigator.clipboard.writeText(text).then(() => {
                alert('Full error report copied to clipboard!');
            });
        }

        function escapeHtml(text) {
            const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
            return text.replace(/[&<>"']/g, m => map[m]);
        }

        // Close on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeModalDirect();
        });
    </script>
</body>
</html>
