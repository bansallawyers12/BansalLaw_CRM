@extends('layouts.crm_client_detail')
@section('title', 'SMS Dashboard & Operations Hub')

@section('content')
<style>
/* Modern SPA Theme for SMS Console */
.sms-spa-container {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    color: #334155;
}

/* Glassmorphism Header */
.sms-header-card {
    background: linear-gradient(135deg, #1e1b4b 0%, #312e81 50%, #4338ca 100%);
    border-radius: 16px;
    padding: 24px 30px;
    color: #ffffff;
    box-shadow: 0 10px 25px -5px rgba(67, 56, 202, 0.3);
    margin-bottom: 24px;
    position: relative;
    overflow: hidden;
}
.sms-header-card::after {
    content: '';
    position: absolute;
    top: -50%;
    right: -10%;
    width: 300px;
    height: 300px;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0) 70%);
    pointer-events: none;
}

/* Tab Pills */
.sms-nav-pills {
    display: flex;
    gap: 8px;
    background: #f1f5f9;
    padding: 6px;
    border-radius: 12px;
    margin-bottom: 24px;
    overflow-x: auto;
}
.sms-nav-link {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 18px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.9rem;
    color: #64748b;
    text-decoration: none !important;
    transition: all 0.2s ease;
    border: none;
    background: transparent;
    cursor: pointer;
    white-space: nowrap;
}
.sms-nav-link:hover {
    color: #1e293b;
    background: rgba(255,255,255,0.6);
}
.sms-nav-link.active {
    background: #ffffff;
    color: #4338ca;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}

/* Stat Cards */
.sms-stat-card {
    background: #ffffff;
    border-radius: 14px;
    padding: 20px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 2px 4px rgba(0,0,0,0.02);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.sms-stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 16px rgba(0,0,0,0.06);
}
.sms-stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
}

/* Phone Mockup Screen */
.phone-mockup {
    width: 100%;
    max-width: 320px;
    height: 420px;
    background: #0f172a;
    border-radius: 36px;
    padding: 16px 12px;
    box-shadow: 0 20px 30px -10px rgba(15, 23, 42, 0.4);
    border: 4px solid #334155;
    margin: 0 auto;
    display: flex;
    flex-direction: column;
}
.phone-notch {
    width: 110px;
    height: 18px;
    background: #334155;
    border-radius: 0 0 10px 10px;
    margin: 0 auto 12px auto;
}
.phone-screen {
    background: #f8fafc;
    border-radius: 20px;
    flex: 1;
    padding: 12px;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    justify-content: flex-end;
}
.sms-bubble-incoming {
    background: #e2e8f0;
    color: #1e293b;
    border-radius: 16px 16px 16px 4px;
    padding: 10px 14px;
    font-size: 0.85rem;
    max-width: 85%;
    margin-bottom: 8px;
    line-height: 1.4;
    word-break: break-word;
}
.sms-bubble-outgoing {
    background: #4338ca;
    color: #ffffff;
    border-radius: 16px 16px 4px 16px;
    padding: 10px 14px;
    font-size: 0.85rem;
    max-width: 85%;
    align-self: flex-end;
    margin-bottom: 8px;
    line-height: 1.4;
    word-break: break-word;
    box-shadow: 0 2px 6px rgba(67, 56, 202, 0.25);
}

/* Floating Toast */
#smsToast {
    position: fixed;
    top: 24px;
    right: 24px;
    z-index: 9999;
    min-width: 300px;
    display: none;
    box-shadow: 0 10px 25px rgba(0,0,0,0.15);
    border-radius: 10px;
}

/* Interactive Variable Tag Buttons */
.var-btn {
    transition: all 0.15s ease;
    border-radius: 8px;
    font-size: 0.82rem;
    background: #ffffff;
    border: 1px solid #cbd5e1;
    color: #334155;
}
.var-btn:hover {
    background: #4338ca;
    color: #ffffff;
    border-color: #4338ca;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(67, 56, 202, 0.2);
}
</style>

<!-- Floating Alert Toast -->
<div id="smsToast" class="alert alert-dismissible fade show" role="alert">
    <div class="d-flex align-items-center gap-2">
        <i id="smsToastIcon" class="fa-solid fa-circle-check fs-5"></i>
        <strong id="smsToastTitle" class="me-auto">Notification</strong>
    </div>
    <div id="smsToastBody" class="mt-1 small"></div>
    <button type="button" class="btn-close" onclick="$('#smsToast').fadeOut()"></button>
</div>

<!-- Main SPA Content -->
<div class="main-content adminconsole-features adminconsole-sms-dashboard sms-spa-container">
    <section class="section">
        <div class="section-body">
            <div class="server-error">
                @include('../Elements/flash-message')
            </div>
            
            <div class="row">
                <!-- Left Sidebar Settings -->
                <div class="col-3 col-md-3 col-lg-3">
                    @include('../Elements/CRM/setting')
                </div>
                
                <!-- Main SPA Body -->
                <div class="col-9 col-md-9 col-lg-9">
                    
                    <!-- Header Card -->
                    <div class="sms-header-card d-flex justify-content-between align-items-center flex-wrap gap-3">
                        <div>
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <h3 class="m-0 text-white font-weight-bold"><i class="fa-solid fa-comment-sms me-2"></i>SMS Hub</h3>
                                <span class="badge bg-success text-white px-2 py-1 align-middle rounded-pill">
                                    <i class="fa-solid fa-circle me-1" style="font-size: 8px;"></i>Online
                                </span>
                            </div>
                            <p class="text-white-50 m-0 small">Unified SMS Engine (Cellcast)</p>
                        </div>
                        <div class="d-flex gap-2">
                            <button onclick="refreshCurrentTab()" class="btn btn-light btn-sm font-weight-bold text-indigo shadow-sm">
                                <i class="fa-solid fa-arrows-rotate me-1" id="refreshBtnIcon"></i> Refresh
                            </button>
                            <button onclick="switchTab('send')" class="btn btn-warning btn-sm font-weight-bold shadow-sm">
                                <i class="fa-solid fa-paper-plane me-1"></i> Quick Send
                            </button>
                        </div>
                    </div>

                    <!-- Navigation Pills -->
                    <div class="sms-nav-pills">
                        <button class="sms-nav-link active" data-tab="overview" onclick="switchTab('overview')">
                            <i class="fa-solid fa-chart-pie"></i> Overview
                        </button>
                        <button class="sms-nav-link" data-tab="send" onclick="switchTab('send')">
                            <i class="fa-solid fa-paper-plane"></i> Send SMS
                        </button>
                        <button class="sms-nav-link" data-tab="history" onclick="switchTab('history')">
                            <i class="fa-solid fa-clock-rotate-left"></i> History & Logs
                        </button>
                        <button class="sms-nav-link" data-tab="templates" onclick="switchTab('templates')">
                            <i class="fa-solid fa-file-lines"></i> Templates
                        </button>
                        <button class="sms-nav-link" data-tab="analytics" onclick="switchTab('analytics')">
                            <i class="fa-solid fa-chart-column"></i> Analytics
                        </button>
                    </div>

                    <!-- TAB 1: OVERVIEW -->
                    <div id="tab-overview" class="spa-tab-content">
                        <!-- Stat Counters -->
                        <div class="row g-3 mb-4">
                            <div class="col-md-4 col-sm-6">
                                <div class="sms-stat-card d-flex align-items-center gap-3">
                                    <div class="sms-stat-icon bg-primary text-white">
                                        <i class="fa-solid fa-paper-plane"></i>
                                    </div>
                                    <div>
                                        <div class="text-muted small font-weight-bold">Sent Today</div>
                                        <h3 class="m-0 font-weight-bold text-dark" id="stat-total">{{ $stats['total_today'] ?? 0 }}</h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 col-sm-6">
                                <div class="sms-stat-card d-flex align-items-center gap-3">
                                    <div class="sms-stat-icon bg-danger text-white">
                                        <i class="fa-solid fa-flag"></i>
                                    </div>
                                    <div>
                                        <div class="text-muted small font-weight-bold">Cellcast</div>
                                        <h3 class="m-0 font-weight-bold text-dark" id="stat-cellcast">{{ $stats['cellcast_today'] ?? 0 }}</h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 col-sm-6">
                                <div class="sms-stat-card d-flex align-items-center gap-3">
                                    <div class="sms-stat-icon bg-warning text-white">
                                        <i class="fa-solid fa-triangle-exclamation"></i>
                                    </div>
                                    <div>
                                        <div class="text-muted small font-weight-bold">Failed Today</div>
                                        <h3 class="m-0 font-weight-bold text-dark" id="stat-failed">{{ $stats['failed_today'] ?? 0 }}</h3>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Recent Activity & Quick Composer Row -->
                        <div class="row g-3">
                            <div class="col-lg-12">
                                <div class="card border-0 shadow-sm rounded-4">
                                    <div class="card-header bg-white border-bottom-0 pt-4 px-4 d-flex justify-content-between align-items-center">
                                        <h5 class="m-0 font-weight-bold text-dark"><i class="fa-solid fa-clock text-indigo me-2"></i>Recent Activity</h5>
                                        <button onclick="switchTab('history')" class="btn btn-link btn-sm text-decoration-none text-indigo font-weight-bold p-0">
                                            View All Logs <i class="fa-solid fa-arrow-right ms-1"></i>
                                        </button>
                                    </div>
                                    <div class="card-body px-4 pb-4">
                                        <div class="table-responsive">
                                            <table class="table table-hover align-middle mb-0" id="overviewActivityTable">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>Time</th>
                                                        <th>Recipient</th>
                                                        <th>Message</th>
                                                        <th>Provider</th>
                                                        <th>Status</th>
                                                        <th class="text-end">Action</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="overviewRecentSmsBody">
                                                    @forelse($recentSms as $sms)
                                                    <tr>
                                                        <td class="small text-muted">{{ $sms->created_at->diffForHumans() }}</td>
                                                        <td>
                                                            <span class="font-weight-bold font-monospace text-dark">{{ $sms->formatted_phone ?? $sms->recipient_phone }}</span>
                                                        </td>
                                                        <td>
                                                            <div class="text-truncate" style="max-width: 280px;" title="{{ $sms->message_content }}">
                                                                {{ $sms->message_content }}
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-{{ $sms->provider === 'cellcast' ? 'danger' : 'info' }} text-white text-uppercase">
                                                                {{ $sms->provider }}
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-{{ $sms->status === 'sent' ? 'success' : ($sms->status === 'failed' ? 'danger' : 'warning') }} text-white">
                                                                {{ ucfirst($sms->status) }}
                                                            </span>
                                                        </td>
                                                        <td class="text-end">
                                                            <button onclick="viewSmsDetails({{ $sms->id }})" class="btn btn-outline-secondary btn-sm rounded-pill py-1 px-3">
                                                                Details
                                                            </button>
                                                        </td>
                                                    </tr>
                                                    @empty
                                                    <tr>
                                                        <td colspan="6" class="text-center py-4 text-muted">
                                                            <i class="fa-solid fa-inbox fa-2x mb-2 text-muted"></i>
                                                            <p class="m-0">No recent SMS activity logged.</p>
                                                        </td>
                                                    </tr>
                                                    @endforelse
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 2: SEND SMS -->
                    <div id="tab-send" class="spa-tab-content" style="display: none;">
                        <div class="row g-4">
                            <!-- Composer Form -->
                            <div class="col-lg-7">
                                <div class="card border-0 shadow-sm rounded-4 h-100">
                                    <div class="card-header bg-white border-bottom-0 pt-4 px-4">
                                        <h5 class="m-0 font-weight-bold text-dark"><i class="fa-solid fa-pen-to-square text-indigo me-2"></i>Compose SMS</h5>
                                    </div>
                                    <div class="card-body px-4 pb-4">
                                        <form id="spaSendSmsForm">
                                            @csrf
                                            <div class="mb-3">
                                                <label class="form-label font-weight-bold">Recipient Mobile Phone <span class="text-danger">*</span></label>
                                                <div class="input-group">
                                                    <span class="input-group-text bg-light"><i class="fa-solid fa-phone text-muted"></i></span>
                                                    <input type="text" class="form-control" id="composerPhone" name="phone" placeholder="+61412345678" required>
                                                </div>
                                                <div class="form-text text-muted small">Auto-formats to international E.164 (e.g. +61 for Australia).</div>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label font-weight-bold">Use SMS Template (Optional)</label>
                                                <select class="form-select form-control" id="composerTemplateSelect" onchange="applyTemplateToComposer(this.value)">
                                                    <option value="">-- Choose a pre-defined template --</option>
                                                    @foreach($activeTemplates ?? [] as $tmpl)
                                                        <option value="{{ $tmpl->id }}">{{ $tmpl->title }} ({{ ucfirst($tmpl->category ?? 'general') }})</option>
                                                    @endforeach
                                                </select>
                                            </div>

                                            <div class="mb-3">
                                                <div class="d-flex justify-content-between align-items-center mb-1">
                                                    <label class="form-label font-weight-bold m-0">Message Content <span class="text-danger">*</span></label>
                                                    <span class="badge bg-light text-dark border small" id="encodingBadge">GSM 7-bit</span>
                                                </div>
                                                <textarea class="form-control" id="composerMessage" name="message" rows="5" placeholder="Type your SMS message here..." required oninput="updateSmsCounters()"></textarea>
                                                
                                                <!-- Counter details -->
                                                <div class="d-flex justify-content-between align-items-center mt-2 small text-muted">
                                                    <div>
                                                        <span id="charCountText">0</span> characters
                                                    </div>
                                                    <div>
                                                        Segments: <strong id="segmentCountText" class="text-indigo">0</strong> SMS
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="d-flex gap-2 justify-content-end pt-2">
                                                <button type="button" onclick="clearComposer()" class="btn btn-light px-4">
                                                    Reset
                                                </button>
                                                <button type="submit" id="sendSmsSubmitBtn" class="btn btn-indigo text-white font-weight-bold px-4 shadow-sm" style="background-color: #4338ca;">
                                                    <i class="fa-solid fa-paper-plane me-1"></i> Send Message Now
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <!-- Mobile Phone Live Preview -->
                            <div class="col-lg-5">
                                <div class="card border-0 shadow-sm rounded-4 text-center p-3 h-100 bg-white">
                                    <h6 class="font-weight-bold text-muted mb-3"><i class="fa-solid fa-mobile-screen me-2"></i>Live Device Preview</h6>
                                    
                                    <div class="phone-mockup">
                                        <div class="phone-notch"></div>
                                        <div class="phone-screen">
                                            <div class="sms-bubble-incoming">
                                                Hello! How can we assist your legal query today?
                                            </div>
                                            <div class="sms-bubble-outgoing" id="phonePreviewBubble">
                                                Your message will appear here in real-time...
                                            </div>
                                        </div>
                                    </div>
                                    <small class="text-muted mt-3">Preview updates instantly as you type.</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 3: HISTORY & LOGS -->
                    <div id="tab-history" class="spa-tab-content" style="display: none;">
                        <div class="card border-0 shadow-sm rounded-4">
                            <div class="card-header bg-white border-bottom-0 pt-4 px-4">
                                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                    <h5 class="m-0 font-weight-bold text-dark"><i class="fa-solid fa-clock-rotate-left text-indigo me-2"></i>SMS History & Delivery Logs</h5>
                                    
                                    <!-- Search & Filter Bar -->
                                    <div class="d-flex gap-2 flex-wrap">
                                        <input type="text" class="form-control form-control-sm" id="historySearchInput" placeholder="Search phone or text..." style="width: 200px;" onkeyup="debounceHistorySearch()">
                                        <select class="form-select form-control form-control-sm" id="historyStatusFilter" onchange="loadHistoryData(1)" style="width: 130px;">
                                            <option value="">All Statuses</option>
                                            <option value="sent">Sent</option>
                                            <option value="failed">Failed</option>
                                            <option value="pending">Pending</option>
                                        </select>
                                        <select class="form-select form-control form-control-sm" id="historyProviderFilter" onchange="loadHistoryData(1)" style="width: 130px;">
                                            <option value="">All Providers</option>
                                            <option value="cellcast">Cellcast</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body px-4 pb-4">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Date & Time</th>
                                                <th>To Phone</th>
                                                <th>Message Snippet</th>
                                                <th>Provider</th>
                                                <th>Status</th>
                                                <th class="text-end">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody id="historyTableBody">
                                            <tr>
                                                <td colspan="6" class="text-center py-4">
                                                    <i class="fa-solid fa-spinner fa-spin me-2 text-indigo"></i> Loading SMS Logs...
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <!-- Pagination Container -->
                                <div class="d-flex justify-content-between align-items-center mt-3" id="historyPagination">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 4: TEMPLATES -->
                    <div id="tab-templates" class="spa-tab-content" style="display: none;">
                        <div class="card border-0 shadow-sm rounded-4">
                            <div class="card-header bg-white border-bottom-0 pt-4 px-4 d-flex justify-content-between align-items-center">
                                <h5 class="m-0 font-weight-bold text-dark"><i class="fa-solid fa-file-lines text-indigo me-2"></i>SMS Template Library</h5>
                                <button onclick="openTemplateModal()" class="btn btn-indigo text-white btn-sm font-weight-bold px-3 shadow-sm" style="background-color: #4338ca;">
                                    <i class="fa-solid fa-plus me-1"></i> New Template
                                </button>
                            </div>
                            <div class="card-body px-4 pb-4">
                                <div class="row g-3 mb-3">
                                    <div class="col-md-6">
                                        <input type="text" class="form-control" id="templateSearchInput" placeholder="Search templates..." onkeyup="debounceTemplateSearch()">
                                    </div>
                                </div>
                                <div class="row g-3" id="templatesGrid">
                                    <div class="col-12 text-center py-4">
                                        <i class="fa-solid fa-spinner fa-spin me-2 text-indigo"></i> Loading Templates...
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 5: ANALYTICS -->
                    <div id="tab-analytics" class="spa-tab-content" style="display: none;">
                        <div class="row g-4">
                            <div class="col-md-6">
                                <div class="card border-0 shadow-sm rounded-4 p-4">
                                    <h6 class="font-weight-bold text-dark mb-3"><i class="fa-solid fa-chart-pie me-2 text-indigo"></i>Gateway Distribution</h6>
                                    <div class="p-3 bg-light rounded-3">
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="font-weight-bold text-danger"><i class="fa-solid fa-flag me-1"></i>Cellcast</span>
                                            <strong id="analyticsCellcastPct">0%</strong>
                                        </div>
                                        <div class="progress mb-3" style="height: 10px;">
                                            <div id="analyticsCellcastBar" class="progress-bar bg-danger" style="width: 0%;"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card border-0 shadow-sm rounded-4 p-4">
                                    <h6 class="font-weight-bold text-dark mb-3"><i class="fa-solid fa-circle-check me-2 text-success"></i>Delivery Health & Success Rate</h6>
                                    <div class="text-center py-3">
                                        <h2 class="display-5 font-weight-bold text-success m-0" id="analyticsSuccessRate">100%</h2>
                                        <span class="text-muted small">Total Successful Deliveries Today</span>
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

<!-- MODAL: SMS Log Details -->
<div class="modal fade" id="smsDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 rounded-4 shadow-lg overflow-hidden">
            <div class="modal-header border-0 px-4 py-3 bg-white border-bottom d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-3 p-2 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; background: #e0e7ff; color: #4338ca;">
                        <i class="fa-solid fa-circle-info fs-5"></i>
                    </div>
                    <div>
                        <h5 class="modal-title font-weight-bold text-dark m-0">SMS Delivery Details</h5>
                        <small class="text-muted">Detailed payload, recipient routing, and gateway metrics</small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" onclick="$('#smsDetailModal').modal('hide')"></button>
            </div>
            <div class="modal-body p-4 bg-white" id="smsDetailModalBody">
                <div class="text-center py-4">
                    <i class="fa-solid fa-spinner fa-spin fa-2x text-indigo"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: Simple, Ultra-Interactive Template Editor Modal -->
<div class="modal fade" id="templateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 rounded-4 shadow-lg overflow-hidden">
            
            <!-- Clean High-Contrast Header -->
            <div class="modal-header border-0 px-4 py-3 bg-white border-bottom d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-3 p-2 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; background: #e0e7ff; color: #4338ca;">
                        <i class="fa-solid fa-file-pen fs-5"></i>
                    </div>
                    <div>
                        <h5 class="modal-title font-weight-bold text-dark m-0" id="templateModalTitle">SMS Template Editor</h5>
                        <small class="text-muted">Compose reusable message templates with instant live preview</small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" onclick="$('#templateModal').modal('hide')"></button>
            </div>
            
            <!-- Modal Body -->
            <div class="modal-body p-4 bg-white">
                <form id="templateForm">
                    @csrf
                    <input type="hidden" id="templateId" name="template_id" value="">
                    <input type="hidden" id="templateVariablesInput" name="variables" value="">

                    <!-- Row 1: Title & Category -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-7">
                            <label class="form-label font-weight-bold text-dark mb-1">Template Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="templateTitleInput" name="title" required placeholder="e.g. Appointment Reminder">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label font-weight-bold text-dark mb-1">Category</label>
                            <select class="form-select form-control" id="templateCategoryInput" name="category">
                                <option value="reminder">Reminder</option>
                                <option value="notification">Notification</option>
                                <option value="verification">Verification</option>
                                <option value="manual">Manual</option>
                            </select>
                        </div>
                    </div>

                    <!-- Row 2: Variable Insert Buttons -->
                    <div class="mb-3">
                        <label class="form-label font-weight-bold text-dark mb-1 d-flex justify-content-between">
                            <span><i class="fa-solid fa-hand-pointer text-indigo me-1"></i> Click to insert merge variable:</span>
                            <span class="badge bg-light text-dark border font-monospace small" id="modalEncodingBadge">GSM 7-bit</span>
                        </label>

                        <div class="p-2 bg-light rounded-3 border d-flex flex-wrap gap-1 align-items-center">
                            <button type="button" class="btn btn-sm var-btn" onclick="insertModalVariable('{first_name}')">
                                👤 {first_name}
                            </button>
                            <button type="button" class="btn btn-sm var-btn" onclick="insertModalVariable('{appointment_date}')">
                                📅 {appointment_date}
                            </button>
                            <button type="button" class="btn btn-sm var-btn" onclick="insertModalVariable('{appointment_time}')">
                                ⏰ {appointment_time}
                            </button>
                            <button type="button" class="btn btn-sm var-btn" onclick="insertModalVariable('{office_phone}')">
                                📞 {office_phone}
                            </button>
                            <button type="button" class="btn btn-sm var-btn" onclick="insertModalVariable('{location}')">
                                📍 {location}
                            </button>
                            <button type="button" class="btn btn-sm var-btn" onclick="insertModalVariable('{company_name}')">
                                🏢 {company_name}
                            </button>
                        </div>
                    </div>

                    <!-- Row 3: Message Textarea -->
                    <div class="mb-3">
                        <label class="form-label font-weight-bold text-dark mb-1">Message Body <span class="text-danger">*</span></label>
                        <textarea class="form-control font-monospace fs-6" id="templateMessageInput" name="message" rows="4" required placeholder="Type message body..." oninput="updateModalCounters()"></textarea>

                        <div class="d-flex justify-content-between align-items-center mt-1 small text-muted">
                            <div><span id="modalCharCount">0</span> characters</div>
                            <div>Segments: <strong id="modalSegmentCount" class="text-indigo">0</strong> SMS</div>
                        </div>
                    </div>

                    <!-- Row 4: Live Interactive Customer SMS Preview -->
                    <div class="mb-3 p-3 rounded-3" style="background: #f0f4ff; border: 1px solid #c7d2fe;">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="small font-weight-bold text-indigo"><i class="fa-solid fa-eye me-1"></i> Live Customer Output Preview:</span>
                            <span class="badge bg-indigo text-white" style="font-size: 10px; background-color: #4338ca;">Real-time Render</span>
                        </div>
                        <div class="p-2 bg-white rounded border font-monospace text-dark small" id="modalLiveSamplePreview" style="white-space: pre-wrap; min-height: 42px;">
                            Sample output will appear here as you type...
                        </div>
                    </div>

                    <!-- Footer Row: Switch & Action Buttons -->
                    <div class="d-flex align-items-center justify-content-between pt-2 border-top">
                        <div class="form-check form-switch m-0 d-flex align-items-center gap-2">
                            <input class="form-check-input m-0" type="checkbox" id="templateIsActiveInput" name="is_active" value="1" checked style="width: 2.2em; height: 1.2em; cursor: pointer;">
                            <label class="form-check-label font-weight-bold text-dark small cursor-pointer m-0" for="templateIsActiveInput">Active Template</label>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-light rounded-3 px-4 font-weight-bold text-muted" onclick="$('#templateModal').modal('hide')">Cancel</button>
                            <button type="submit" id="saveTemplateModalBtn" class="btn btn-indigo text-white rounded-3 px-4 font-weight-bold shadow-sm" style="background-color: #4338ca;">
                                <i class="fa-solid fa-floppy-disk me-1"></i> Save Template
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
let currentTab = 'overview';
let activeTemplatesCache = [];
let historySearchTimer = null;
let templateSearchTimer = null;

$(document).ready(function() {
    // Check URL hash on load
    const hash = window.location.hash.replace('#', '');
    if (['overview', 'send', 'history', 'templates', 'analytics'].includes(hash)) {
        switchTab(hash, false);
    } else {
        switchTab('overview', false);
    }

    // Live preview binding
    $('#composerMessage').on('input', function() {
        const val = $(this).val();
        $('#phonePreviewBubble').text(val.trim() ? val : 'Your message will appear here in real-time...');
    });
});

/* SPA Tab Switcher */
function switchTab(tabName, updateHash = true) {
    currentTab = tabName;
    if (updateHash) {
        window.location.hash = tabName;
    }

    // Toggle nav active state
    $('.sms-nav-link').removeClass('active');
    $(`.sms-nav-link[data-tab="${tabName}"]`).addClass('active');

    // Toggle tab panels
    $('.spa-tab-content').hide();
    $(`#tab-${tabName}`).fadeIn(200);

    // Trigger tab-specific data load
    if (tabName === 'history') {
        loadHistoryData(1);
    } else if (tabName === 'templates') {
        loadTemplatesData();
    } else if (tabName === 'analytics' || tabName === 'overview') {
        loadDashboardStats();
    }
}

function refreshCurrentTab() {
    $('#refreshBtnIcon').addClass('fa-spin');
    setTimeout(() => $('#refreshBtnIcon').removeClass('fa-spin'), 1000);
    switchTab(currentTab, false);
}

/* Toast Notifications */
function showToast(title, message, type = 'success') {
    const toast = $('#smsToast');
    const icon = $('#smsToastIcon');
    toast.removeClass('alert-success alert-danger alert-warning alert-info');
    icon.removeClass('fa-circle-check fa-circle-xmark fa-triangle-exclamation');

    if (type === 'success') {
        toast.addClass('alert-success');
        icon.addClass('fa-circle-check');
    } else if (type === 'danger') {
        toast.addClass('alert-danger');
        icon.addClass('fa-circle-xmark');
    } else {
        toast.addClass('alert-warning');
        icon.addClass('fa-triangle-exclamation');
    }

    $('#smsToastTitle').text(title);
    $('#smsToastBody').text(message);
    toast.stop(true, true).fadeIn(200).delay(4000).fadeOut(300);
}

/* Character & SMS Segment Calculator */
function updateSmsCounters() {
    const text = $('#composerMessage').val() || '';
    const charCount = text.length;
    
    // Check if non-GSM (Unicode)
    const gsm7BitRegex = /^[\A-Za-z0-9 \r\n@£$¥èéùìòÇ\Øø\ÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ!"#¤%&'()*+,\-./:;<=>?¡ÄÖÑÜ§àäöñüà]*$/;
    const isUnicode = !gsm7BitRegex.test(text);

    $('#encodingBadge').text(isUnicode ? 'Unicode (UTF-16)' : 'GSM 7-bit');
    
    let segments = 0;
    if (charCount > 0) {
        if (isUnicode) {
            segments = charCount <= 70 ? 1 : Math.ceil(charCount / 67);
        } else {
            segments = charCount <= 160 ? 1 : Math.ceil(charCount / 153);
        }
    }

    $('#charCountText').text(charCount);
    $('#segmentCountText').text(segments);
}

/* Modal Counter, Variable Sync & Live Interactive Preview */
function updateModalCounters() {
    const text = $('#templateMessageInput').val() || '';
    const charCount = text.length;
    const gsm7BitRegex = /^[\A-Za-z0-9 \r\n@£$¥èéùìòÇ\Øø\ÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ!"#¤%&'()*+,\-./:;<=>?¡ÄÖÑÜ§àäöñüà]*$/;
    const isUnicode = !gsm7BitRegex.test(text);

    $('#modalEncodingBadge').text(isUnicode ? 'Unicode (UTF-16)' : 'GSM 7-bit');

    let segments = 0;
    if (charCount > 0) {
        if (isUnicode) {
            segments = charCount <= 70 ? 1 : Math.ceil(charCount / 67);
        } else {
            segments = charCount <= 160 ? 1 : Math.ceil(charCount / 153);
        }
    }

    $('#modalCharCount').text(charCount);
    $('#modalSegmentCount').text(segments);

    // Render Live Customer Preview with sample data replacements
    if (charCount > 0) {
        let sample = text;
        sample = sample.replace(/\{first_name\}/g, 'John');
        sample = sample.replace(/\{client_name\}/g, 'John Doe');
        sample = sample.replace(/\{appointment_date\}/g, '29 July 2026');
        sample = sample.replace(/\{appointment_time\}/g, '10:30 AM');
        sample = sample.replace(/\{office_phone\}/g, '03 9876 5432');
        sample = sample.replace(/\{location\}/g, 'Melbourne CBD');
        sample = sample.replace(/\{company_name\}/g, 'Bansal Lawyers');
        $('#modalLiveSamplePreview').text(sample);
    } else {
        $('#modalLiveSamplePreview').text('Sample output will appear here as you type...');
    }

    // Auto sync variables input
    const matches = text.match(/\{([a-zA-Z0-9_]+)\}/g);
    if (matches) {
        const cleanVars = [...new Set(matches.map(m => m.replace(/[\{\}]/g, '')))];
        $('#templateVariablesInput').val(cleanVars.join(', '));
    } else {
        $('#templateVariablesInput').val('');
    }
}

function insertModalVariable(tag) {
    const textarea = document.getElementById('templateMessageInput');
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const text = textarea.value;

    textarea.value = text.substring(0, start) + tag + text.substring(end);
    textarea.selectionStart = textarea.selectionEnd = start + tag.length;
    textarea.focus();
    $(textarea).trigger('input');
}

function clearComposer() {
    $('#spaSendSmsForm')[0].reset();
    $('#phonePreviewBubble').text('Your message will appear here in real-time...');
    updateSmsCounters();
}

/* Load Dashboard Stats via AJAX */
function loadDashboardStats() {
    $.ajax({
        url: '{{ route("adminconsole.features.sms.dashboard") }}?json=1',
        method: 'GET',
        success: function(res) {
            if (res.success) {
                $('#stat-total').text(res.stats.total_today || 0);
                $('#stat-cellcast').text(res.stats.cellcast_today || 0);
                $('#stat-failed').text(res.stats.failed_today || 0);

                // Update Overview Table
                if (res.recentSms && res.recentSms.length > 0) {
                    let html = '';
                    res.recentSms.forEach(sms => {
                        html += `
                        <tr>
                            <td class="small text-muted">${new Date(sms.created_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</td>
                            <td><span class="font-weight-bold font-monospace text-dark">${sms.formatted_phone || sms.recipient_phone}</span></td>
                            <td><div class="text-truncate" style="max-width: 280px;">${sms.message_content}</div></td>
                            <td><span class="badge bg-danger text-white text-uppercase">${sms.provider || 'cellcast'}</span></td>
                            <td><span class="badge bg-${sms.status === 'sent' ? 'success' : (sms.status === 'failed' ? 'danger' : 'warning')} text-white">${sms.status}</span></td>
                            <td class="text-end">
                                <button onclick="viewSmsDetails(${sms.id})" class="btn btn-outline-secondary btn-sm rounded-pill py-1 px-3">Details</button>
                            </td>
                        </tr>`;
                    });
                    $('#overviewRecentSmsBody').html(html);
                }

                // Update Analytics tab
                const total = res.stats.total_today || 0;
                const cellcast = res.stats.cellcast_today || 0;
                const failed = res.stats.failed_today || 0;

                const cellPct = total > 0 ? Math.round((cellcast / total) * 100) : 0;
                const successRate = total > 0 ? Math.round(((total - failed) / total) * 100) : 100;

                $('#analyticsCellcastPct').text(cellPct + '%');
                $('#analyticsCellcastBar').css('width', cellPct + '%');
                $('#analyticsSuccessRate').text(successRate + '%');
            }
        }
    });
}

/* AJAX SMS Submission */
$('#spaSendSmsForm').on('submit', function(e) {
    e.preventDefault();
    const btn = $('#sendSmsSubmitBtn');
    btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin me-1"></i> Sending...');

    $.ajax({
        url: '{{ route("adminconsole.features.sms.send") }}',
        method: 'POST',
        data: $(this).serialize(),
        success: function(res) {
            if (res.success) {
                showToast('SMS Dispatched', 'Your SMS message has been successfully routed and sent.', 'success');
                clearComposer();
                loadDashboardStats();
            } else {
                showToast('Send Failed', res.message || 'Failed to dispatch message', 'danger');
            }
        },
        error: function(xhr) {
            const res = xhr.responseJSON;
            showToast('Error', (res && res.message) ? res.message : 'An error occurred while sending SMS', 'danger');
        },
        complete: function() {
            btn.prop('disabled', false).html('<i class="fa-solid fa-paper-plane me-1"></i> Send Message Now');
        }
    });
});

/* History Data Loader */
function debounceHistorySearch() {
    clearTimeout(historySearchTimer);
    historySearchTimer = setTimeout(() => loadHistoryData(1), 300);
}

function loadHistoryData(page = 1) {
    const search = $('#historySearchInput').val();
    const status = $('#historyStatusFilter').val();
    const provider = $('#historyProviderFilter').val();

    $('#historyTableBody').html(`
        <tr>
            <td colspan="6" class="text-center py-4">
                <i class="fa-solid fa-spinner fa-spin me-2 text-indigo"></i> Loading SMS Logs...
            </td>
        </tr>
    `);

    $.ajax({
        url: `{{ route("adminconsole.features.sms.history") }}?json=1&page=${page}&search=${encodeURIComponent(search)}&status=${status}&provider=${provider}`,
        method: 'GET',
        success: function(res) {
            if (res.success && res.data.data.length > 0) {
                let html = '';
                res.data.data.forEach(sms => {
                    const dateStr = new Date(sms.created_at).toLocaleString();
                    html += `
                    <tr>
                        <td class="small text-muted">${dateStr}</td>
                        <td><span class="font-weight-bold font-monospace text-dark">${sms.formatted_phone || sms.recipient_phone}</span></td>
                        <td><div class="text-truncate" style="max-width: 250px;">${sms.message_content}</div></td>
                        <td><span class="badge bg-${sms.provider === 'cellcast' ? 'danger' : 'info'} text-white text-uppercase">${sms.provider}</span></td>
                        <td><span class="badge bg-${sms.status === 'sent' ? 'success' : (sms.status === 'failed' ? 'danger' : 'warning')} text-white">${sms.status}</span></td>
                        <td class="text-end">
                            <button onclick="viewSmsDetails(${sms.id})" class="btn btn-outline-secondary btn-sm rounded-pill py-1 px-3">
                                View Details
                            </button>
                        </td>
                    </tr>`;
                });
                $('#historyTableBody').html(html);

                // Render pagination
                let pagHtml = `<span class="small text-muted">Showing page ${res.data.current_page} of ${res.data.last_page} (${res.data.total} total)</span><div class="btn-group">`;
                if (res.data.prev_page_url) {
                    pagHtml += `<button onclick="loadHistoryData(${res.data.current_page - 1})" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-chevron-left"></i> Prev</button>`;
                }
                if (res.data.next_page_url) {
                    pagHtml += `<button onclick="loadHistoryData(${res.data.current_page + 1})" class="btn btn-outline-secondary btn-sm">Next <i class="fa-solid fa-chevron-right"></i></button>`;
                }
                pagHtml += `</div>`;
                $('#historyPagination').html(pagHtml);
            } else {
                $('#historyTableBody').html(`
                    <tr>
                        <td colspan="6" class="text-center py-4 text-muted">
                            <i class="fa-solid fa-inbox fa-2x mb-2 text-muted"></i>
                            <p class="m-0">No SMS log entries found matching criteria.</p>
                        </td>
                    </tr>
                `);
                $('#historyPagination').html('');
            }
        }
    });
}

/* Log Details View Modal */
function viewSmsDetails(id) {
    $('#smsDetailModal').modal('show');
    $('#smsDetailModalBody').html(`
        <div class="text-center py-4">
            <i class="fa-solid fa-spinner fa-spin fa-2x text-indigo"></i>
        </div>
    `);

    $.ajax({
        url: `/adminconsole/features/sms/history/${id}?json=1`,
        method: 'GET',
        success: function(res) {
            if (res.success) {
                const d = res.data;
                const modalHtml = `
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded-3">
                                <small class="text-muted d-block">Recipient Phone</small>
                                <strong class="fs-6 font-monospace">${d.formatted_phone || d.recipient_phone}</strong>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded-3">
                                <small class="text-muted d-block">Delivery Status</small>
                                <span class="badge bg-${d.status === 'sent' ? 'success' : (d.status === 'failed' ? 'danger' : 'warning')} fs-6">${d.status.toUpperCase()}</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded-3">
                                <small class="text-muted d-block">Provider Gateway</small>
                                <strong class="text-uppercase">${d.provider || 'N/A'}</strong>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded-3">
                                <small class="text-muted d-block">Date Sent</small>
                                <strong>${new Date(d.created_at).toLocaleString()}</strong>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label font-weight-bold">Message Content</label>
                            <div class="p-3 border rounded-3 bg-white font-monospace text-dark" style="white-space: pre-wrap;">${d.message_content}</div>
                        </div>
                        ${d.provider_message_id ? `
                        <div class="col-12">
                            <label class="form-label font-weight-bold">Provider Reference ID</label>
                            <div class="p-2 bg-light rounded font-monospace small">${d.provider_message_id}</div>
                        </div>` : ''}
                    </div>
                `;
                $('#smsDetailModalBody').html(modalHtml);
            }
        }
    });
}

/* Template Loader & Management */
function debounceTemplateSearch() {
    clearTimeout(templateSearchTimer);
    templateSearchTimer = setTimeout(() => loadTemplatesData(), 300);
}

function loadTemplatesData() {
    const search = $('#templateSearchInput').val();
    $('#templatesGrid').html(`
        <div class="col-12 text-center py-4">
            <i class="fa-solid fa-spinner fa-spin me-2 text-indigo"></i> Loading Templates...
        </div>
    `);

    $.ajax({
        url: `/adminconsole/features/sms/templates?json=1&search=${encodeURIComponent(search)}`,
        method: 'GET',
        success: function(res) {
            if (res.success && res.data.data.length > 0) {
                activeTemplatesCache = res.data.data;
                let html = '';
                res.data.data.forEach(tmpl => {
                    html += `
                    <div class="col-md-6">
                        <div class="card border p-3 rounded-3 h-100 shadow-sm position-relative">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h6 class="font-weight-bold m-0 text-dark">${tmpl.title}</h6>
                                <span class="badge bg-${tmpl.is_active ? 'success' : 'secondary'} rounded-pill">${tmpl.is_active ? 'Active' : 'Inactive'}</span>
                            </div>
                            <p class="small text-muted mb-2 font-monospace bg-light p-2 rounded" style="white-space: pre-wrap; max-height: 90px; overflow-y: auto;">${tmpl.message}</p>
                            <div class="d-flex justify-content-between align-items-center mt-auto pt-2 border-top">
                                <span class="badge bg-indigo text-white text-uppercase" style="background-color: #4338ca;">${tmpl.category || 'general'}</span>
                                <div class="btn-group">
                                    <button onclick="applyTemplateToComposer(${tmpl.id})" class="btn btn-outline-indigo btn-sm py-1">Use</button>
                                    <button onclick="editTemplate(${tmpl.id})" class="btn btn-outline-secondary btn-sm py-1"><i class="fa-solid fa-pen"></i> Edit</button>
                                    <button onclick="deleteTemplate(${tmpl.id})" class="btn btn-outline-danger btn-sm py-1"><i class="fa-solid fa-trash"></i></button>
                                </div>
                            </div>
                        </div>
                    </div>`;
                });
                $('#templatesGrid').html(html);
            } else {
                $('#templatesGrid').html(`
                    <div class="col-12 text-center py-4 text-muted">
                        <i class="fa-solid fa-folder-open fa-2x mb-2 text-muted"></i>
                        <p class="m-0">No SMS templates found.</p>
                    </div>
                `);
            }
        }
    });
}

function applyTemplateToComposer(templateId) {
    if (!templateId) return;
    $.ajax({
        url: `/adminconsole/features/sms/templates/${templateId}`,
        method: 'GET',
        success: function(res) {
            if (res.success) {
                switchTab('send');
                $('#composerTemplateSelect').val(templateId);
                $('#composerMessage').val(res.data.message).trigger('input');
                showToast('Template Applied', `Loaded template: ${res.data.title}`, 'info');
            }
        }
    });
}

function openTemplateModal() {
    $('#templateForm')[0].reset();
    $('#templateId').val('');
    $('#templateModalTitle').text('New SMS Template');
    updateModalCounters();
    $('#templateModal').modal('show');
}

function editTemplate(id) {
    $.ajax({
        url: `/adminconsole/features/sms/templates/${id}`,
        method: 'GET',
        success: function(res) {
            if (res.success) {
                const t = res.data;
                $('#templateId').val(t.id);
                $('#templateTitleInput').val(t.title);
                $('#templateCategoryInput').val(t.category || 'reminder');
                $('#templateMessageInput').val(t.message);
                $('#templateVariablesInput').val(t.variables || '');
                $('#templateIsActiveInput').prop('checked', t.is_active);
                $('#templateModalTitle').text('Edit SMS Template');
                updateModalCounters();
                $('#templateModal').modal('show');
            }
        }
    });
}

$('#templateForm').on('submit', function(e) {
    e.preventDefault();
    const id = $('#templateId').val();
    const url = id ? `/adminconsole/features/sms/templates/${id}` : '/adminconsole/features/sms/templates';
    const method = id ? 'PUT' : 'POST';

    const btn = $('#saveTemplateModalBtn');
    btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin me-1"></i> Saving...');

    $.ajax({
        url: url,
        method: method,
        data: $(this).serialize(),
        success: function(res) {
            if (res.success) {
                showToast('Success', res.message || 'Template saved', 'success');
                $('#templateModal').modal('hide');
                loadTemplatesData();
            } else {
                showToast('Error', res.message || 'Validation error', 'danger');
            }
        },
        error: function(xhr) {
            const res = xhr.responseJSON;
            showToast('Error', (res && res.message) ? res.message : 'Error saving template', 'danger');
        },
        complete: function() {
            btn.prop('disabled', false).html('<i class="fa-solid fa-floppy-disk me-1"></i> Save Template');
        }
    });
});

function deleteTemplate(id) {
    if (!confirm('Are you sure you want to delete this template?')) return;
    $.ajax({
        url: `/adminconsole/features/sms/templates/${id}`,
        method: 'DELETE',
        data: { _token: '{{ csrf_token() }}' },
        success: function(res) {
            if (res.success) {
                showToast('Deleted', 'Template removed', 'success');
                loadTemplatesData();
            } else {
                showToast('Error', res.message, 'danger');
            }
        }
    });
}
</script>
@endsection
