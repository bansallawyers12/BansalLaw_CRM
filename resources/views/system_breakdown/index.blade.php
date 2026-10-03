<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Breakdown & All Error Logs Monitor – Bansal Law CRM</title>
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

        header {
            background: linear-gradient(180deg, #131c2e 0%, var(--bg-page) 100%);
            border-bottom: 1px solid var(--border-subtle);
            padding: 16px 28px;
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
            top: -4px; left: -4px; right: -4px; bottom: -4px;
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

        .tag-pill.env { background: var(--accent-blue-bg); color: var(--accent-blue); border: 1px solid rgba(59, 130, 246, 0.3); }
        .tag-pill.live { background: var(--accent-green-bg); color: var(--accent-green); border: 1px solid rgba(16, 185, 129, 0.3); }
        .tag-pill.auth { background: var(--accent-purple-bg); color: #d8b4fe; border: 1px solid rgba(139, 92, 246, 0.3); }

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

        .btn-primary { background-color: var(--accent-blue); color: #ffffff; }
        .btn-primary:hover { background-color: #2563eb; }
        .btn-outline { background-color: var(--bg-card); border-color: var(--border-subtle); color: var(--text-secondary); }
        .btn-outline:hover { background-color: var(--bg-card-hover); color: var(--text-primary); border-color: #3b82f6; }
        .btn-warning { background-color: var(--accent-amber-bg); border-color: rgba(245, 158, 11, 0.3); color: #fde68a; }
        .btn-warning:hover { background-color: rgba(245, 158, 11, 0.25); color: #fff; }

        .container {
            max-width: 1440px;
            margin: 0 auto;
            padding: 24px 28px;
        }

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
            grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .metric-card {
            background-color: var(--bg-card);
            border: 1px solid var(--border-subtle);
            border-radius: 12px;
            padding: 16px 20px;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .metric-label {
            font-size: 11px;
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.05em;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .metric-value {
            font-size: 26px;
            font-weight: 700;
            letter-spacing: -0.03em;
            line-height: 1.1;
        }

        .metric-card.red .metric-value { color: var(--accent-red); }
        .metric-card.amber .metric-value { color: var(--accent-amber); }
        .metric-card.green .metric-value { color: var(--accent-green); }
        .metric-card.blue .metric-value { color: var(--accent-blue); }
        .metric-card.purple .metric-value { color: var(--accent-purple); }

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
            transition: all 0.15s ease;
        }

        .tab-link:hover { color: var(--text-primary); }
        .tab-link.active { color: var(--accent-blue); border-bottom-color: var(--accent-blue); }

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

        /* Toolbar / Filter */
        .toolbar {
            background-color: var(--bg-card);
            border: 1px solid var(--border-subtle);
            border-radius: 12px;
            padding: 14px 18px;
            margin-bottom: 20px;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .search-box {
            flex: 1;
            min-width: 260px;
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
        }
        .search-input:focus { border-color: var(--accent-blue); }

        .search-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
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
        }

        /* Error Cards */
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
            cursor: pointer;
            transition: all 0.15s ease;
        }

        .error-card:hover {
            background-color: var(--bg-card-hover);
            border-color: #3b82f6;
            transform: translateX(2px);
        }

        .error-card.investigating { border-left-color: var(--accent-amber); }
        .error-card.resolved { border-left-color: var(--accent-green); opacity: 0.75; }

        .error-title {
            font-family: var(--font-mono);
            font-size: 14px;
            font-weight: 700;
            color: #f87171;
            word-break: break-word;
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
            font-size: 11px;
        }

        /* TAB 2: Multi-File Log Explorer Layout */
        .logs-layout {
            display: grid;
            grid-template-columns: 360px 1fr;
            gap: 20px;
            align-items: start;
        }

        @media (max-width: 1024px) {
            .logs-layout { grid-template-columns: 1fr; }
        }

        .log-files-sidebar {
            background-color: var(--bg-card);
            border: 1px solid var(--border-subtle);
            border-radius: 12px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .sidebar-header {
            padding: 14px 18px;
            border-bottom: 1px solid var(--border-subtle);
            font-weight: 700;
            font-size: 13px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background-color: #0e1523;
        }

        .log-category-title {
            padding: 10px 18px 6px 18px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-muted);
            background-color: rgba(15, 23, 42, 0.6);
            border-bottom: 1px solid rgba(255, 255, 255, 0.03);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .log-file-item {
            padding: 10px 18px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.04);
            display: flex;
            align-items: center;
            justify-content: space-between;
            text-decoration: none;
            color: var(--text-secondary);
            font-family: var(--font-mono);
            font-size: 12px;
            transition: all 0.15s ease;
        }

        .log-file-item:hover {
            background-color: var(--bg-card-hover);
            color: var(--text-primary);
        }

        .log-file-item.active {
            background-color: var(--accent-blue-bg);
            border-left: 3px solid var(--accent-blue);
            color: #fff;
            font-weight: 600;
        }

        .file-meta-sub {
            font-size: 10px;
            color: var(--text-muted);
            margin-top: 2px;
        }

        /* Log Content Area */
        .log-content-panel {
            background-color: var(--bg-card);
            border: 1px solid var(--border-subtle);
            border-radius: 12px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .log-file-header-bar {
            padding: 16px 20px;
            background-color: #0e1523;
            border-bottom: 1px solid var(--border-subtle);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
        }

        .log-file-name {
            font-family: var(--font-mono);
            font-size: 15px;
            font-weight: 700;
            color: #93c5fd;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .log-filter-bar {
            padding: 12px 20px;
            background-color: #121927;
            border-bottom: 1px solid var(--border-subtle);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
        }

        .parsed-log-card {
            padding: 14px 20px;
            border-bottom: 1px solid var(--border-subtle);
            display: flex;
            flex-direction: column;
            gap: 8px;
            font-family: var(--font-mono);
            font-size: 12px;
            transition: background 0.15s ease;
        }

        .parsed-log-card:hover {
            background-color: var(--bg-card-hover);
        }

        .json-pills-wrap {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 4px;
        }

        .json-pill {
            background-color: #1e293b;
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 4px;
            padding: 2px 8px;
            font-size: 11px;
            color: #cbd5e1;
        }

        .json-pill span { color: #94a3b8; }
        .json-pill strong { color: #38bdf8; }

        .raw-text-view {
            background-color: #050811;
            padding: 18px;
            font-family: var(--font-mono);
            font-size: 11.5px;
            line-height: 1.6;
            color: #cbd5e1;
            max-height: 650px;
            overflow-y: auto;
            white-space: pre-wrap;
            word-break: break-all;
        }

        /* Inspector Modal */
        .modal-overlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(3, 7, 18, 0.85);
            backdrop-filter: blur(8px);
            z-index: 100;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .modal-overlay.active { display: flex; }

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
            background-color: rgba(11, 15, 25, 0.5);
        }

        .close-btn {
            background: none; border: none; color: var(--text-muted); font-size: 20px; cursor: pointer;
        }
        .close-btn:hover { color: #fff; }

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

        .code-line { display: flex; padding: 3px 12px; line-height: 1.6; }
        .code-line.error-target { background-color: rgba(239, 68, 68, 0.2); color: #fca5a5; font-weight: 700; }
        .line-num { width: 48px; color: #475569; user-select: none; text-align: right; padding-right: 14px; }
        .code-line.error-target .line-num { color: #ef4444; font-weight: 700; }
        .line-text { white-space: pre-wrap; word-break: break-all; }

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

        .param-table { width: 100%; border-collapse: collapse; font-size: 12px; }
        .param-table th, .param-table td { padding: 8px 12px; border: 1px solid var(--border-subtle); text-align: left; }
        .param-table th { background-color: var(--bg-elevated); color: var(--text-secondary); font-weight: 600; }
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
                        Bansal Law CRM – System Breakdown & All Logs Monitor
                        <span class="tag-pill env">{{ config('app.env') }}</span>
                        <span class="tag-pill auth">Protected (Login Required)</span>
                        <span class="tag-pill live">Listening Active</span>
                    </div>
                    <div class="brand-subtitle">
                        Multi-file error log inspector (Upload errors, Inbox sync errors, Outlook addin, DB logs, and Real-time exceptions)
                    </div>
                </div>
            </div>

            <div class="header-actions">
                <a href="{{ route('system_errors.simulate') }}" class="btn btn-warning" title="Simulate a test exception to verify capture">
                    ⚡ Simulate Test Error
                </a>
                <a href="{{ route('system_errors.index', ['tab' => $activeTab, 'log_file' => $selectedLogFile]) }}" class="btn btn-outline" title="Refresh dashboard">
                    🔄 Refresh
                </a>
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
                    <span>Total Log Files Tracked</span>
                    <span>📁</span>
                </div>
                <div class="metric-value">{{ count($allLogFiles) }}</div>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <div class="tabs-nav">
            <a href="{{ route('system_errors.index', ['tab' => 'db']) }}" class="tab-link {{ $activeTab === 'db' ? 'active' : '' }}">
                <span>💥 Real-Time Captured Breakdowns</span>
                <span class="tab-badge">{{ $errors->total() }}</span>
            </a>
            <a href="{{ route('system_errors.index', ['tab' => 'logs', 'log_file' => $selectedLogFile]) }}" class="tab-link {{ $activeTab === 'logs' ? 'active' : '' }}">
                <span>📂 All Log Files Explorer (Uploads, Sync, Outlook, Core)</span>
                <span class="tab-badge">{{ count($allLogFiles) }} files</span>
            </a>
        </div>

        @if($activeTab === 'db')
            <!-- TAB 1: Database Captured Exceptions -->
            <form method="GET" action="{{ route('system_errors.index') }}" class="toolbar">
                <input type="hidden" name="tab" value="db">
                <div class="search-box">
                    <span class="search-icon">🔍</span>
                    <input type="text" name="search" class="search-input" placeholder="Search by error message, exception class, file path, URL, or user email..." value="{{ request('search') }}">
                </div>

                <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
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
                        <a href="{{ route('system_errors.index', ['tab' => 'db']) }}" class="btn btn-outline">Reset</a>
                    @endif
                </div>
            </form>

            @if($errors->count() > 0)
                <div class="error-list">
                    @foreach($errors as $item)
                        <div class="error-card {{ $item->status }}" onclick="openErrorDetail({{ $item->id }})">
                            <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:16px;">
                                <div style="display:flex; flex-direction:column; gap:4px; flex:1;">
                                    <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap; font-size:12px;">
                                        <span class="badge {{ $item->status_code >= 500 ? 'badge-red' : 'badge-amber' }}">
                                            {{ $item->http_method ?? 'ANY' }} {{ $item->status_code ?? '500' }}
                                        </span>
                                        <span class="badge badge-purple">
                                            {{ class_basename($item->exception_class) }}
                                        </span>
                                        @if($item->occurrence_count > 1)
                                            <span class="badge-count">
                                                {{ $item->occurrence_count }}× Occurrences
                                            </span>
                                        @endif
                                        <span class="code-location" title="{{ $item->file }}">
                                            {{ $item->short_file }}
                                        </span>
                                        <span class="badge badge-gray">{{ $item->status }}</span>
                                    </div>
                                    <div class="error-title">
                                        {{ $item->message }}
                                    </div>
                                    <div style="font-size:12px; color:var(--text-muted); font-family:var(--font-mono);">
                                        URL: <span style="color:#93c5fd;">{{ $item->url ?? 'CLI/Background' }}</span>
                                    </div>
                                </div>

                                <div style="display:flex; align-items:center; gap:8px;" onclick="event.stopPropagation()">
                                    <button type="button" class="btn btn-outline" style="padding:4px 10px; font-size:12px;" onclick="openErrorDetail({{ $item->id }})">
                                        Inspect
                                    </button>
                                    @if($item->status !== 'resolved')
                                        <form action="{{ route('system_errors.update_status', $item->id) }}" method="POST" style="display:inline;">
                                            @csrf
                                            <input type="hidden" name="status" value="resolved">
                                            <button type="submit" class="btn btn-outline" style="padding:4px 10px; font-size:12px; color:#10b981;">
                                                Resolve
                                            </button>
                                        </form>
                                    @else
                                        <form action="{{ route('system_errors.update_status', $item->id) }}" method="POST" style="display:inline;">
                                            @csrf
                                            <input type="hidden" name="status" value="open">
                                            <button type="submit" class="btn btn-outline" style="padding:4px 10px; font-size:12px;">
                                                Reopen
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </div>

                            <div style="display:flex; align-items:center; justify-content:space-between; font-size:12px; color:var(--text-secondary); border-top:1px solid rgba(255,255,255,0.04); padding-top:8px; margin-top:2px;">
                                <div style="display:flex; align-items:center; gap:6px; color:var(--text-primary);">
                                    <span style="width:20px; height:20px; border-radius:50%; background:var(--accent-blue); color:#fff; display:inline-flex; align-items:center; justify-content:center; font-size:10px; font-weight:700;">
                                        {{ strtoupper(substr($item->user_name ?: 'G', 0, 1)) }}
                                    </span>
                                    <span>{{ $item->user_name }}</span>
                                    @if($item->user_email)
                                        <span style="color:var(--text-muted);">({{ $item->user_email }})</span>
                                    @endif
                                    @if($item->ip_address)
                                        <span style="color:var(--text-muted); font-family:var(--font-mono);">• {{ $item->ip_address }}</span>
                                    @endif
                                </div>
                                <div style="font-family:var(--font-mono); color:var(--text-muted);">
                                    Last seen: <span style="color:var(--text-secondary);">{{ $item->relative_last_seen }}</span> ({{ $item->last_seen_at ? $item->last_seen_at->format('d M Y, H:i:s') : 'N/A' }})
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div style="margin-top:24px;">
                    {{ $errors->links() }}
                </div>
            @else
                <div style="background-color:var(--bg-card); border:1px dashed var(--border-subtle); border-radius:14px; padding:60px 20px; text-align:center;">
                    <div style="font-size:42px; margin-bottom:12px;">🎉</div>
                    <div style="font-size:18px; font-weight:700; margin-bottom:6px;">Zero System Breakdowns Found</div>
                    <div style="color:var(--text-muted); max-width:420px; margin:0 auto 20px auto;">
                        No errors match your filter. Any failure encountered by any staff user across the CRM will appear here automatically.
                    </div>
                    <a href="{{ route('system_errors.simulate') }}" class="btn btn-primary">
                        ⚡ Simulate Test Error to Verify
                    </a>
                </div>
            @endif

        @else
            <!-- TAB 2: Multi-File Log Explorer -->
            <div class="logs-layout">

                <!-- Left Sidebar: Categorized Log Files List -->
                <div class="log-files-sidebar">
                    <div class="sidebar-header">
                        <span>All System Log Files</span>
                        <span class="badge badge-gray">{{ count($allLogFiles) }} Files</span>
                    </div>

                    @php
                        $groupedFiles = [];
                        foreach ($allLogFiles as $path => $f) {
                            $groupedFiles[$f['category_label']][] = $f;
                        }
                    @endphp

                    <div style="max-height: 720px; overflow-y: auto;">
                        @foreach($groupedFiles as $catLabel => $files)
                            <div class="log-category-title">
                                <span>{{ $files[0]['icon'] }}</span>
                                <span>{{ $catLabel }}</span>
                                <span style="margin-left:auto; font-weight:normal; opacity:0.7;">({{ count($files) }})</span>
                            </div>

                            @foreach($files as $f)
                                <a href="{{ route('system_errors.index', ['tab' => 'logs', 'log_file' => $f['relative_path']]) }}"
                                   class="log-file-item {{ $selectedLogFile === $f['relative_path'] ? 'active' : '' }}">
                                    <div style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap; padding-right:8px;">
                                        <div style="font-weight:600; color: {{ $selectedLogFile === $f['relative_path'] ? '#fff' : '#e2e8f0' }};">
                                            {{ $f['filename'] }}
                                        </div>
                                        <div class="file-meta-sub">
                                            {{ $f['size_formatted'] }} • {{ $f['last_modified'] }}
                                        </div>
                                    </div>

                                    @if($f['error_count'] > 0)
                                        <span class="badge badge-red" style="font-size:10px; padding:2px 6px;">
                                            {{ $f['error_count'] }} err
                                        </span>
                                    @endif
                                </a>
                            @endforeach
                        @endforeach
                    </div>
                </div>

                <!-- Right Panel: Selected Log File Content & Controls -->
                <div class="log-content-panel">
                    @if($selectedFileInfo)
                        <!-- File Header Bar -->
                        <div class="log-file-header-bar">
                            <div>
                                <div class="log-file-name">
                                    <span>{{ $selectedFileInfo['icon'] }}</span>
                                    <span>{{ $selectedFileInfo['relative_path'] }}</span>
                                    <span class="badge badge-purple">{{ $selectedFileInfo['category_label'] }}</span>
                                </div>
                                <div style="font-size:11px; color:var(--text-muted); font-family:var(--font-mono); margin-top:3px;">
                                    Size: <strong>{{ $selectedFileInfo['size_formatted'] }}</strong> • Last Written: <strong>{{ $selectedFileInfo['last_modified'] }}</strong>
                                </div>
                            </div>

                            <div style="display:flex; align-items:center; gap:8px;">
                                <a href="{{ route('system_errors.download_log', ['file' => $selectedLogFile]) }}" class="btn btn-outline" style="padding:5px 10px; font-size:12px;">
                                    📥 Download
                                </a>
                                <form action="{{ route('system_errors.clear_log_file') }}" method="POST" onsubmit="return confirm('Truncate and empty {{ $selectedFileInfo['filename'] }}?');" style="display:inline;">
                                    @csrf
                                    <input type="hidden" name="file" value="{{ $selectedLogFile }}">
                                    <button type="submit" class="btn btn-outline" style="padding:5px 10px; font-size:12px; color:#f87171;">
                                        🧹 Truncate File
                                    </button>
                                </form>
                            </div>
                        </div>

                        <!-- Filter and Search within this file -->
                        <form method="GET" action="{{ route('system_errors.index') }}" class="log-filter-bar">
                            <input type="hidden" name="tab" value="logs">
                            <input type="hidden" name="log_file" value="{{ $selectedLogFile }}">

                            <div style="display:flex; align-items:center; gap:8px; flex:1;">
                                <input type="text" name="log_search" class="search-input" style="padding:6px 12px; font-size:12px;" placeholder="Search in {{ $selectedFileInfo['filename'] }}..." value="{{ $logSearch }}">
                                <select name="log_level" class="filter-select" style="padding:6px 10px; font-size:12px;" onchange="this.form.submit()">
                                    <option value="all" {{ $logLevel === 'all' ? 'selected' : '' }}>Level: All Levels</option>
                                    <option value="errors" {{ $logLevel === 'errors' ? 'selected' : '' }}>Level: Errors Only</option>
                                    <option value="warning" {{ $logLevel === 'warning' ? 'selected' : '' }}>Level: Warnings</option>
                                    <option value="info" {{ $logLevel === 'info' ? 'selected' : '' }}>Level: Info</option>
                                </select>
                                <button type="submit" class="btn btn-primary" style="padding:6px 12px; font-size:12px;">Filter</button>
                                @if($logSearch || $logLevel !== 'all')
                                    <a href="{{ route('system_errors.index', ['tab' => 'logs', 'log_file' => $selectedLogFile]) }}" class="btn btn-outline" style="padding:6px 10px; font-size:12px;">Reset</a>
                                @endif
                            </div>

                            <div style="display:flex; align-items:center; gap:8px;">
                                @if($viewRaw)
                                    <a href="{{ route('system_errors.index', ['tab' => 'logs', 'log_file' => $selectedLogFile, 'log_level' => $logLevel, 'log_search' => $logSearch]) }}" class="btn btn-outline" style="padding:5px 10px; font-size:12px;">
                                        🗂️ Parsed Cards View
                                    </a>
                                @else
                                    <a href="{{ route('system_errors.index', ['tab' => 'logs', 'log_file' => $selectedLogFile, 'view_raw' => 1]) }}" class="btn btn-outline" style="padding:5px 10px; font-size:12px;">
                                        📄 Raw Text View
                                    </a>
                                @endif
                            </div>
                        </form>

                        <!-- Log Entries List or Raw Text -->
                        @if($viewRaw)
                            <pre class="raw-text-view">{{ $rawLogContent }}</pre>
                        @else
                            @if(count($logEntries) > 0)
                                <div style="display:flex; flex-direction:column; max-height:650px; overflow-y:auto;">
                                    @foreach($logEntries as $idx => $entry)
                                        <div class="parsed-log-card">
                                            <div style="display:flex; align-items:center; justify-content:space-between;">
                                                <div style="display:flex; align-items:center; gap:8px;">
                                                    <span class="badge {{ in_array($entry['level'], ['ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY']) ? 'badge-red' : ($entry['level'] === 'WARNING' ? 'badge-amber' : 'badge-blue') }}">
                                                        {{ $entry['level'] }}
                                                    </span>
                                                    <span style="color:var(--text-muted); font-size:11px;">{{ $entry['env'] }}</span>
                                                </div>
                                                <span style="color:var(--text-muted); font-size:11px;">{{ $entry['timestamp'] }}</span>
                                            </div>

                                            <div style="color: {{ in_array($entry['level'], ['ERROR', 'CRITICAL']) ? '#fca5a5' : '#e2e8f0' }}; font-weight:600; word-break:break-word;">
                                                {{ $entry['title'] }}
                                            </div>

                                            <!-- JSON Structured Metadata Pills (e.g. upload errors, sync error context) -->
                                            @if(!empty($entry['json_context']))
                                                <div class="json-pills-wrap">
                                                    @foreach($entry['json_context'] as $jk => $jv)
                                                        @if(!is_array($jv) && !is_object($jv))
                                                            <div class="json-pill">
                                                                <span>{{ $jk }}:</span> <strong>{{ $jv }}</strong>
                                                            </div>
                                                        @endif
                                                    @endforeach
                                                </div>
                                            @endif

                                            <!-- Stack Trace or Technical Detail -->
                                            @if(!empty($entry['stack']))
                                                <details style="margin-top:4px;">
                                                    <summary style="cursor:pointer; color:var(--accent-blue); font-size:11px; user-select:none;">
                                                        View Stack Trace & Technical Details
                                                    </summary>
                                                    <pre style="background:rgba(0,0,0,0.5); padding:10px; border-radius:6px; font-size:11px; margin-top:6px; overflow-x:auto; color:#cbd5e1; border:1px solid #1e293b;">{{ $entry['stack'] }}</pre>
                                                </details>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div style="padding:40px 20px; text-align:center; color:var(--text-muted);">
                                    <div style="font-size:28px; margin-bottom:8px;">🔍</div>
                                    <div>No log entries match your filter in this file.</div>
                                </div>
                            @endif
                        @endif

                    @else
                        <div style="padding:60px 20px; text-align:center; color:var(--text-muted);">
                            <div style="font-size:36px; margin-bottom:10px;">📂</div>
                            <div style="font-size:16px; font-weight:600; color:var(--text-primary); margin-bottom:4px;">Select a Log File from the Sidebar</div>
                            <div>Choose any upload error, inbox sync, or core log file from the left to inspect its contents.</div>
                        </div>
                    @endif
                </div>

            </div>
        @endif

    </div>

    <!-- Inspector Modal for Database Breakdowns -->
    <div id="errorModal" class="modal-overlay" onclick="closeErrorModal(event)">
        <div class="modal-content" onclick="event.stopPropagation()">
            <div class="modal-header">
                <div>
                    <div id="modalExceptionClass" class="badge badge-purple" style="margin-bottom:6px;">Exception</div>
                    <div id="modalMessage" style="font-family:var(--font-mono); font-size:15px; font-weight:700; color:#f87171;"></div>
                    <div id="modalLocation" style="font-family:var(--font-mono); font-size:12px; color:var(--text-muted); margin-top:4px;"></div>
                </div>
                <button type="button" class="close-btn" onclick="closeModalDirect()">✕</button>
            </div>

            <div class="modal-body">
                <div id="codeSnippetWrapper">
                    <div class="code-box">
                        <div class="code-box-header">
                            <span id="codeFilePath">Source Code Snippet</span>
                            <span>Exact line highlighted</span>
                        </div>
                        <div id="codeLinesContainer"></div>
                    </div>
                </div>

                <div>
                    <h4 style="font-size:13px; font-weight:700; color:var(--text-secondary); margin-bottom:8px; text-transform:uppercase; letter-spacing:0.05em;">
                        Request & User Context
                    </h4>
                    <table class="param-table">
                        <tr><th style="width:160px;">URL</th><td id="modalUrl" style="font-family:var(--font-mono); color:#93c5fd;"></td></tr>
                        <tr><th>HTTP Method</th><td id="modalMethod" style="font-family:var(--font-mono);"></td></tr>
                        <tr><th>User Name / Email</th><td id="modalUser"></td></tr>
                        <tr><th>User Role</th><td id="modalRole"></td></tr>
                        <tr><th>IP & User Agent</th><td id="modalClientInfo" style="font-family:var(--font-mono); font-size:11px;"></td></tr>
                        <tr><th>First / Last Seen</th><td id="modalTimes" style="font-family:var(--font-mono);"></td></tr>
                    </table>
                </div>

                <div id="payloadSection">
                    <h4 style="font-size:13px; font-weight:700; color:var(--text-secondary); margin-bottom:8px; text-transform:uppercase; letter-spacing:0.05em;">
                        Submitted Form Data / Payload (Sanitized)
                    </h4>
                    <pre id="modalPayload" style="background:#050811; border:1px solid #1e293b; border-radius:8px; padding:12px; font-family:var(--font-mono); font-size:11px; overflow-x:auto; color:#a5f3fc;"></pre>
                </div>

                <div>
                    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:8px;">
                        <h4 style="font-size:13px; font-weight:700; color:var(--text-secondary); text-transform:uppercase; letter-spacing:0.05em;">
                            Cleaned Stack Trace
                        </h4>
                        <button type="button" class="btn btn-outline" style="padding:2px 8px; font-size:11px;" onclick="copyFullError()">
                            📋 Copy Error Report
                        </button>
                    </div>
                    <pre id="modalStackTrace" class="trace-pre"></pre>
                </div>
            </div>

            <div class="modal-footer">
                <div style="display:flex; align-items:center; gap:8px;">
                    <span id="modalStatusBadge" class="badge badge-gray">open</span>
                    <span id="modalOccurrences" style="font-family:var(--font-mono); font-size:12px; color:var(--text-muted);"></span>
                </div>

                <div style="display:flex; align-items:center; gap:8px;">
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

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeModalDirect();
        });
    </script>
</body>
</html>
