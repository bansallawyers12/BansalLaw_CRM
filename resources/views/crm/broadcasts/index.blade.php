@extends('layouts.crm_client_detail')
@section('title', 'Broadcast Notifications')

@section('styles')
<link rel="stylesheet" href="{{ asset('css/broadcast-manage.css') }}">
@endsection

@section('content')
<div class="main-content broadcast-manage-page">
    <section class="section">
        <div class="section-header">
            <div>
                <h1 class="mb-0">Broadcast Notifications</h1>
                <p class="mb-0 broadcast-subtitle">Send announcements and monitor read receipts in real time.</p>
            </div>
        </div>

        <div class="section-body">
            <ul class="nav nav-tabs" id="broadcastTabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="broadcasts-tab" data-bs-toggle="tab" href="#broadcasts" role="tab" aria-controls="broadcasts" aria-selected="true">
                        <i class="fas fa-bullhorn mr-1"></i> Broadcasts
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="active-staff-tab" data-bs-toggle="tab" href="#active-staff" role="tab" aria-controls="active-staff" aria-selected="false">
                        <i class="fas fa-users mr-1"></i> Active Staff
                    </a>
                </li>
            </ul>

            <div class="tab-content" id="broadcastTabsContent">
                <!-- Broadcasts Tab -->
                <div class="tab-pane fade show active" id="broadcasts" role="tabpanel" aria-labelledby="broadcasts-tab">
                    <div class="row mt-3">
                        <div class="col-lg-5">
                            <div class="card">
                                <div class="card-header">
                                    <h4 class="mb-0">Compose Broadcast</h4>
                                </div>
                                <div class="card-body">
                                    <div id="broadcast-compose-feedback" class="alert d-none" role="alert"></div>
                                    <form id="broadcast-compose-form" novalidate>
                                        @csrf
                                        <div class="form-group">
                                            <label for="broadcast-title">Title <span class="text-muted">(optional)</span></label>
                                            <input type="text" id="broadcast-title" name="title" class="form-control" maxlength="255" placeholder="System Maintenance">
                                        </div>

                                        <div class="form-group">
                                            <label for="broadcast-message">Message <span class="text-muted small">(Max 1000 characters)</span></label>
                                            <textarea id="broadcast-message" name="message" class="form-control" placeholder="Enter the announcement you want everyone to see..." required></textarea>
                                            <div class="d-flex justify-content-between align-items-center mt-1">
                                                <small class="text-muted">Rich text formatting is supported</small>
                                                <small class="text-muted" id="broadcast-char-count">0 / 1000 characters</small>
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <label for="broadcast-scope">Audience</label>
                                            <select id="broadcast-scope" name="scope" class="form-control">
                                                <option value="all" selected>All staff</option>
                                                <option value="specific">Specific team members</option>
                                                <option value="team" disabled>Teams (coming soon)</option>
                                            </select>
                                        </div>

                                        <div class="form-group d-none" id="broadcast-recipient-group">
                                            <label for="broadcast-recipient-select">Select recipients</label>
                                            <select id="broadcast-recipient-select" class="form-control" name="recipient_ids[]" multiple="multiple" data-placeholder="Search team members"></select>
                                            <small class="form-text text-muted">Start typing to search for staff members. Portal client targeting will be added soon.</small>
                                        </div>

                                        <div class="d-flex justify-content-between align-items-center mt-4">
                                            <span class="text-muted small">
                                                Broadcasts send instantly and appear in the sticky banner for recipients.
                                            </span>
                                            <button type="submit" class="btn btn-primary" id="broadcast-submit-btn">
                                                <span class="submit-text">Send Broadcast</span>
                                                <span class="submit-spinner spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-7">
                            <div class="card h-100">
                                <div class="card-header">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h4 class="mb-0">Broadcast History</h4>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="badge broadcast-count-badge" id="broadcast-history-count">0 broadcasts</span>
                                            <button type="button" class="btn btn-sm broadcast-refresh-btn" id="broadcast-refresh-history">
                                                <i class="fas fa-sync-alt mr-1"></i> Refresh
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <!-- Tabs for different views -->
                                    <ul class="nav nav-pills nav-fill" id="history-tabs" role="tablist">
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link active" id="all-broadcasts-tab" data-bs-toggle="pill" data-bs-target="#all-broadcasts" type="button" role="tab">
                                                <i class="fas fa-globe mr-1"></i> All Broadcasts
                                            </button>
                                        </li>
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link" id="my-sent-tab" data-bs-toggle="pill" data-bs-target="#my-sent" type="button" role="tab">
                                                <i class="fas fa-paper-plane mr-1"></i> My Sent
                                            </button>
                                        </li>
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link" id="my-read-tab" data-bs-toggle="pill" data-bs-target="#my-read" type="button" role="tab">
                                                <i class="fas fa-check-circle mr-1"></i> My Read
                                            </button>
                                        </li>
                                    </ul>
                                </div>
                                <div class="card-body">
                                    <div class="tab-content" id="history-tabs-content">
                                        <!-- All Broadcasts Tab -->
                                        <div class="tab-pane fade show active" id="all-broadcasts" role="tabpanel">
                                            <div class="table-responsive">
                                                <table class="table table-striped" id="broadcast-history-table">
                                                    <thead>
                                                        <tr>
                                                            <th>Sent By</th>
                                                            <th>Date</th>
                                                            <th>Message</th>
                                                            <th class="text-center">Read</th>
                                                            <th class="text-center">Unread</th>
                                                            <th class="text-right">Actions</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="broadcast-history-body">
                                                        <tr>
                                                            <td colspan="6" class="text-center text-muted py-4">
                                                                <i class="fas fa-bullhorn mb-2" style="font-size: 28px;"></i>
                                                                <div>No broadcasts yet. Send your first announcement!</div>
                                                            </td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                        
                                        <!-- My Sent Broadcasts Tab -->
                                        <div class="tab-pane fade" id="my-sent" role="tabpanel">
                                            <div class="table-responsive">
                                                <table class="table table-striped">
                                                    <thead>
                                                        <tr>
                                                            <th>Date</th>
                                                            <th>Message</th>
                                                            <th class="text-center">Read</th>
                                                            <th class="text-center">Unread</th>
                                                            <th class="text-right">Actions</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="my-sent-body">
                                                        <tr>
                                                            <td colspan="5" class="text-center text-muted py-4">
                                                                <i class="fas fa-paper-plane mb-2" style="font-size: 28px;"></i>
                                                                <div>You haven't sent any broadcasts yet.</div>
                                                            </td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                        
                                        <!-- My Read Broadcasts Tab -->
                                        <div class="tab-pane fade" id="my-read" role="tabpanel">
                                            <div class="table-responsive">
                                                <table class="table table-striped">
                                                    <thead>
                                                        <tr>
                                                            <th>Sent By</th>
                                                            <th>Date</th>
                                                            <th>Message</th>
                                                            <th>Read At</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="my-read-body">
                                                        <tr>
                                                            <td colspan="4" class="text-center text-muted py-4">
                                                                <i class="fas fa-check-circle mb-2" style="font-size: 28px;"></i>
                                                                <div>No read broadcasts yet.</div>
                                                            </td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-footer text-muted small">
                                    <i class="fas fa-info-circle"></i> All staff can view broadcast history. Only super admins can delete broadcasts.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Active Staff Tab -->
                <div class="tab-pane fade" id="active-staff" role="tabpanel" aria-labelledby="active-staff-tab">
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="card shadow-sm border-0 active-staff-modern-card" id="active-staff-card">
                                <!-- Modern Header Section -->
                                <div class="active-staff-header">
                                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
                                        <div class="flex-grow-1">
                                            <div class="d-flex align-items-center gap-3 mb-2">
                                                <div class="active-staff-icon-wrapper">
                                                    <i class="fas fa-users"></i>
                                                </div>
                                                <div>
                                                    <h4 class="mb-1 active-staff-title">Active Staff</h4>
                                                    <p class="mb-0 active-staff-subtitle">Monitor real-time staff presence and activity</p>
                                                </div>
                                            </div>
                                            <div class="active-staff-stats">
                                                <span class="badge badge-pill active-staff-count-badge" id="active-staff-count">
                                                    <i class="fas fa-circle status-dot-online"></i>
                                                    <span class="count-text">1</span> online
                                                </span>
                                                <small class="text-muted ml-3">
                                                    <i class="fas fa-info-circle"></i> Presence calculated from active sessions within the last 5 minutes
                                                </small>
                                            </div>
                                        </div>
                                        <div class="d-flex align-items-center gap-2 flex-shrink-0">
                                            @if(Auth::user() && in_array(Auth::user()->role, [1, 12]))
                                            <a href="{{ route('staff-login-analytics.index') }}" class="btn btn-light btn-sm active-staff-action-btn">
                                                <i class="fas fa-chart-line"></i>
                                                <span class="d-none d-md-inline">Analytics</span>
                                            </a>
                                            @endif
                                            <button type="button" class="btn btn-sm active-staff-refresh-btn" id="active-staff-refresh">
                                                <i class="fas fa-sync-alt"></i>
                                                <span class="d-none d-md-inline">Refresh</span>
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <!-- Enhanced Search and Filters -->
                                    <div class="active-staff-filters">
                                        <div class="row g-3">
                                            <div class="col-md-5 col-lg-4">
                                                <div class="input-group input-group-sm">
                                                    <div class="input-group-prepend">
                                                        <span class="input-group-text bg-white border-right-0">
                                                            <i class="fas fa-search text-muted"></i>
                                                        </span>
                                                    </div>
                                                    <input type="text" 
                                                           class="form-control border-left-0 active-staff-search-input" 
                                                           id="active-staff-search" 
                                                           placeholder="Search by name or email...">
                                                </div>
                                            </div>
                                            <div class="col-md-3 col-lg-2">
                                                <select class="form-control form-control-sm active-staff-filter-select" id="active-staff-role-filter">
                                                    <option value="">All Roles</option>
                                                    @foreach(\App\Models\UserRole::orderedForSelect() as $role)
                                                        <option value="{{ $role->id }}">{{ $role->name ?? 'Role #' . $role->id }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-md-3 col-lg-2">
                                                <select class="form-control form-control-sm active-staff-filter-select" id="active-staff-team-filter">
                                                    <option value="">All Teams</option>
                                                    @foreach(\App\Models\Team::all() as $team)
                                                        <option value="{{ $team->id }}">{{ $team->name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-md-1 col-lg-1">
                                                <button type="button" 
                                                        class="btn btn-outline-secondary btn-sm btn-block active-staff-clear-btn" 
                                                        id="active-staff-clear-filters"
                                                        title="Clear filters">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Modern Table Section -->
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-hover mb-0 active-staff-table-modern" id="active-staff-table">
                                            <thead>
                                                <tr>
                                                    <th class="sortable" data-sort="name">
                                                        <span class="th-content">
                                                            <i class="fas fa-user"></i>
                                                            <span>Staff</span>
                                                            <i class="fas fa-sort sort-icon"></i>
                                                        </span>
                                                    </th>
                                                    <th class="text-center sortable" data-sort="status" style="width: 100px;">
                                                        <span class="th-content">
                                                            <i class="fas fa-circle"></i>
                                                            <span>Status</span>
                                                        </span>
                                                    </th>
                                                    <th class="sortable" data-sort="role">
                                                        <span class="th-content">
                                                            <i class="fas fa-user-tag"></i>
                                                            <span>Role</span>
                                                            <i class="fas fa-sort sort-icon"></i>
                                                        </span>
                                                    </th>
                                                    <th class="sortable" data-sort="team">
                                                        <span class="th-content">
                                                            <i class="fas fa-users-cog"></i>
                                                            <span>Team</span>
                                                            <i class="fas fa-sort sort-icon"></i>
                                                        </span>
                                                    </th>
                                                    <th class="sortable" data-sort="last_activity">
                                                        <span class="th-content">
                                                            <i class="fas fa-clock"></i>
                                                            <span>Last Activity</span>
                                                            <i class="fas fa-sort sort-icon"></i>
                                                        </span>
                                                    </th>
                                                    <th>
                                                        <span class="th-content">
                                                            <i class="fas fa-sign-in-alt"></i>
                                                            <span>Last Login</span>
                                                        </span>
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody id="active-staff-body">
                                                <tr>
                                                    <td colspan="6" class="text-center py-5">
                                                        <div class="active-staff-empty-state">
                                                            <div class="spinner-border spinner-border-sm text-primary mb-3" role="status" id="active-staff-loading" style="display: none;">
                                                                <span class="sr-only">Loading...</span>
                                                            </div>
                                                            <i class="fas fa-users mb-3 empty-state-icon"></i>
                                                            <div id="active-staff-empty-message" class="empty-state-message">Click the tab to load active staff.</div>
                                                        </div>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                
                                <!-- Modern Footer -->
                                <div class="card-footer bg-white border-top active-staff-footer">
                                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                                        <div class="text-muted small">
                                            <i class="fas fa-info-circle text-primary"></i>
                                            <span id="active-staff-info">Refreshing manually will recalculate active sessions in real time.</span>
                                            <span id="active-staff-last-refresh" class="ml-2"></span>
                                        </div>
                                        <nav aria-label="Active staff pagination" id="active-staff-pagination">
                                            <!-- Pagination will be inserted here by JavaScript -->
                                        </nav>
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

<!-- Detail Modal -->
<div class="modal fade" id="broadcastDetailModal" tabindex="-1" role="dialog" aria-labelledby="broadcastDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="broadcastDetailModalLabel">Broadcast Details</h5>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <strong id="broadcast-detail-title" class="d-block"></strong>
                    <span id="broadcast-detail-message" class="d-block"></span>
                    <small class="text-muted" id="broadcast-detail-meta"></small>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-striped">
                        <thead>
                            <tr>
                                <th>Recipient</th>
                                <th>Status</th>
                                <th>Read at</th>
                            </tr>
                        </thead>
                        <tbody id="broadcast-detail-body">
                            <tr>
                                <td colspan="3" class="text-center text-muted py-3">Loading recipients…</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // Initialize TinyMCE for broadcast message
    let broadcastEditor = null;
    
    if (typeof tinymce !== 'undefined') {
        tinymce.init({
            selector: '#broadcast-message',
            license_key: 'gpl',
            height: 250,
            menubar: false,
            plugins: [
                'lists', 'link', 'autolink', 'code', 'wordcount'
            ],
            toolbar: 'undo redo | bold italic underline strikethrough | forecolor backcolor | alignleft aligncenter alignright | bullist numlist | link | code | removeformat',
            content_style: 'body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; font-size: 14px; line-height: 1.6; }',
            placeholder: 'Enter the announcement you want everyone to see...',
            branding: false,
            promotion: false,
            statusbar: true,
            resize: true,
            max_chars: 1000,
            setup: function(editor) {
                broadcastEditor = editor;
                
                // Character counter
                editor.on('init', function() {
                    updateCharCount(editor);
                });
                
                editor.on('keyup change', function() {
                    updateCharCount(editor);
                });
                
                // Enforce character limit
                editor.on('keydown', function(e) {
                    const content = editor.getContent({format: 'text'});
                    if (content.length >= 1000 && e.keyCode !== 8 && e.keyCode !== 46) {
                        e.preventDefault();
                        return false;
                    }
                });
            }
        });
    }
    
    function updateCharCount(editor) {
        const content = editor.getContent({format: 'text'});
        const charCount = content.length;
        const charCountEl = document.getElementById('broadcast-char-count');
        
        if (charCountEl) {
            charCountEl.textContent = `${charCount} / 1000 characters`;
            charCountEl.className = 'text-muted';
            
            if (charCount > 950) {
                charCountEl.className = 'text-warning font-weight-bold';
            }
            if (charCount >= 1000) {
                charCountEl.className = 'text-danger font-weight-bold';
            }
        }
    }
    
    (function () {
        /* Full URLs so fetch works when the app lives in a subdirectory (e.g. /BansalLaw_CRM/public/) */
        const BROADCAST_API_BASE = @json(url('/notifications/broadcasts'));
        const ACTIVE_STAFF_JSON_URL = @json(url('/dashboard/active-staff'));
        const GET_ASSIGNEE_AJAX_URL = @json(url('/getassigneeajax'));

        const composeForm = document.getElementById('broadcast-compose-form');
        const messageInput = document.getElementById('broadcast-message');
        const titleInput = document.getElementById('broadcast-title');
        const scopeSelect = document.getElementById('broadcast-scope');
        const recipientGroup = document.getElementById('broadcast-recipient-group');
        const recipientSelect = $('#broadcast-recipient-select');

        const feedbackEl = document.getElementById('broadcast-compose-feedback');
        const submitBtn = document.getElementById('broadcast-submit-btn');
        const submitText = submitBtn.querySelector('.submit-text');
        const submitSpinner = submitBtn.querySelector('.submit-spinner');

        const historyBody = document.getElementById('broadcast-history-body');
        const historyCount = document.getElementById('broadcast-history-count');
        const refreshBtn = document.getElementById('broadcast-refresh-history');

        const detailModal = $('#broadcastDetailModal');
        const detailTitle = document.getElementById('broadcast-detail-title');
        const detailMessage = document.getElementById('broadcast-detail-message');
        const detailMeta = document.getElementById('broadcast-detail-meta');
        const detailBody = document.getElementById('broadcast-detail-body');

        const activeStaffBody = document.getElementById('active-staff-body');
        const activeStaffCount = document.getElementById('active-staff-count');
        const activeStaffRefresh = document.getElementById('active-staff-refresh');
        const activeStaffSearch = document.getElementById('active-staff-search');
        const activeStaffRoleFilter = document.getElementById('active-staff-role-filter');
        const activeStaffTeamFilter = document.getElementById('active-staff-team-filter');
        const activeStaffClearFilters = document.getElementById('active-staff-clear-filters');
        const activeStaffLoading = document.getElementById('active-staff-loading');
        const activeStaffEmptyMessage = document.getElementById('active-staff-empty-message');
        const activeStaffLastRefresh = document.getElementById('active-staff-last-refresh');
        const activeStaffPagination = document.getElementById('active-staff-pagination');
        const activeStaffTab = document.getElementById('active-staff-tab');

        // Active Staff State
        let activeStaffState = {
            loaded: false,
            loading: false,
            currentPage: 1,
            perPage: 15,
            sortBy: 'name',
            sortDir: 'asc',
            search: '',
            roleId: null,
            teamId: null,
            refreshTimeout: null,
            debounceTimeout: null,
        };

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        // Setup CSRF token for all AJAX requests (including Select2)
        if (typeof $ !== 'undefined' && $.ajaxSetup) {
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': csrfToken
                }
            });
        }

        function toggleRecipientsVisibility() {
            if (scopeSelect.value === 'specific') {
                recipientGroup.classList.remove('d-none');
            } else {
                recipientGroup.classList.add('d-none');
                recipientSelect.val(null).trigger('change');
            }
        }

        function showFeedback(type, message) {
            feedbackEl.classList.remove('d-none', 'alert-success', 'alert-danger', 'alert-warning');
            feedbackEl.classList.add(`alert-${type}`);
            feedbackEl.textContent = message;
        }

        function hideFeedback() {
            feedbackEl.classList.add('d-none');
            feedbackEl.textContent = '';
        }

        function setSubmitting(isSubmitting) {
            submitBtn.disabled = isSubmitting;
            submitText.classList.toggle('d-none', isSubmitting);
            submitSpinner.classList.toggle('d-none', !isSubmitting);
        }

        function formatDate(dateString) {
            const date = new Date(dateString);
            if (Number.isNaN(date.getTime())) {
                return '';
            }
            return new Intl.DateTimeFormat(undefined, {
                year: 'numeric',
                month: 'short',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit',
            }).format(date);
        }

        // State to track current staff and permissions
        let currentState = {
            isSuperAdmin: false,
            currentStaffId: null
        };

        // Helper function to truncate HTML message for display
        function truncateMessage(html, maxLength = 150) {
            const temp = document.createElement('div');
            temp.innerHTML = html;
            const text = temp.textContent || temp.innerText || '';
            
            if (text.length <= maxLength) {
                return html;
            }
            
            // Truncate text and add ellipsis
            const truncated = text.substring(0, maxLength) + '...';
            return `<span title="${text.replace(/"/g, '&quot;')}">${truncated}</span>`;
        }

        function renderHistoryTable(items, isSuperAdmin = false) {
            historyBody.innerHTML = '';

            if (!items.length) {
                historyBody.innerHTML = `<tr>
                    <td colspan="6" class="text-center text-muted py-4">
                        <i class="fas fa-bullhorn mb-2" style="font-size: 28px;"></i>
                        <div>No broadcasts yet. Send your first announcement!</div>
                    </td>
                </tr>`;
                historyCount.textContent = '0 broadcasts';
                return;
            }

            historyCount.textContent = `${items.length} broadcast${items.length !== 1 ? 's' : ''}`;

            items.forEach((item) => {
                const row = document.createElement('tr');
                const deleteBtn = isSuperAdmin 
                    ? `<button type="button" class="btn btn-outline-danger btn-sm ml-1" data-action="delete-broadcast" data-batch="${item.batch_uuid}" title="Delete broadcast">
                           <i class="fas fa-trash"></i>
                       </button>`
                    : '';
                
                row.innerHTML = `
                    <td>
                        <strong>${item.sender_name || 'Unknown'}</strong>
                        <br><small class="text-muted">#${item.sender_id}</small>
                    </td>
                    <td>${formatDate(item.sent_at)}</td>
                    <td>
                        ${item.title ? `<strong>${item.title}</strong><br>` : ''}
                        <span class="broadcast-message-text">${item.message}</span>
                    </td>
                    <td class="text-center">
                        <span class="badge badge-success">${item.read_count}</span>
                    </td>
                    <td class="text-center">
                        <span class="badge badge-warning">${item.unread_count}</span>
                    </td>
                    <td class="text-right">
                        <button type="button" class="btn btn-outline-primary btn-sm" data-action="view-broadcast" data-batch="${item.batch_uuid}">
                            <i class="fas fa-eye"></i> Details
                        </button>
                        ${deleteBtn}
                    </td>
                `;
                historyBody.appendChild(row);
            });
        }

        function renderMySentTable(items, isSuperAdmin = false) {
            const mySentBody = document.getElementById('my-sent-body');
            mySentBody.innerHTML = '';

            if (!items.length) {
                mySentBody.innerHTML = `<tr>
                    <td colspan="5" class="text-center text-muted py-4">
                        <i class="fas fa-paper-plane mb-2" style="font-size: 28px;"></i>
                        <div>You haven't sent any broadcasts yet.</div>
                    </td>
                </tr>`;
                return;
            }

            items.forEach((item) => {
                const row = document.createElement('tr');
                const deleteBtn = isSuperAdmin 
                    ? `<button type="button" class="btn btn-outline-danger btn-sm ml-1" data-action="delete-broadcast" data-batch="${item.batch_uuid}" title="Delete broadcast">
                           <i class="fas fa-trash"></i>
                       </button>`
                    : '';
                
                row.innerHTML = `
                    <td>${formatDate(item.sent_at)}</td>
                    <td>
                        ${item.title ? `<strong>${item.title}</strong><br>` : ''}
                        <span class="broadcast-message-text">${item.message}</span>
                    </td>
                    <td class="text-center">
                        <span class="badge badge-success">${item.read_count}</span>
                    </td>
                    <td class="text-center">
                        <span class="badge badge-warning">${item.unread_count}</span>
                    </td>
                    <td class="text-right">
                        <button type="button" class="btn btn-outline-primary btn-sm" data-action="view-broadcast" data-batch="${item.batch_uuid}">
                            <i class="fas fa-eye"></i> Details
                        </button>
                        ${deleteBtn}
                    </td>
                `;
                mySentBody.appendChild(row);
            });
        }

        function renderMyReadTable(items) {
            const myReadBody = document.getElementById('my-read-body');
            myReadBody.innerHTML = '';

            if (!items.length) {
                myReadBody.innerHTML = `<tr>
                    <td colspan="4" class="text-center text-muted py-4">
                        <i class="fas fa-check-circle mb-2" style="font-size: 28px;"></i>
                        <div>No read broadcasts yet.</div>
                    </td>
                </tr>`;
                return;
            }

            items.forEach((item) => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>
                        <strong>${item.sender_name || 'Unknown'}</strong>
                    </td>
                    <td>${formatDate(item.sent_at)}</td>
                    <td>
                        ${item.title ? `<strong>${item.title}</strong><br>` : ''}
                        <span class="broadcast-message-text">${item.message}</span>
                    </td>
                    <td>
                        <span class="text-success">
                            <i class="fas fa-check mr-1"></i>${formatDate(item.read_at)}
                        </span>
                    </td>
                `;
                myReadBody.appendChild(row);
            });
        }

        function formatTimeAgo(dateString) {
            if (!dateString) return '—';
            const date = new Date(dateString);
            if (Number.isNaN(date.getTime())) return '—';
            
            const now = new Date();
            const diffMs = now - date;
            const diffMins = Math.floor(diffMs / 60000);
            const diffHours = Math.floor(diffMs / 3600000);
            const diffDays = Math.floor(diffMs / 86400000);

            if (diffMins < 1) return 'Just now';
            if (diffMins < 60) return `${diffMins} minute${diffMins > 1 ? 's' : ''} ago`;
            if (diffHours < 24) return `${diffHours} hour${diffHours > 1 ? 's' : ''} ago`;
            if (diffDays < 7) return `${diffDays} day${diffDays > 1 ? 's' : ''} ago`;
            
            return formatDate(dateString);
        }

        function getActivityDuration(lastActivity) {
            if (!lastActivity) return '—';
            const date = new Date(lastActivity);
            if (Number.isNaN(date.getTime())) return '—';
            
            const now = new Date();
            const diffMs = now - date;
            const diffMins = Math.floor(diffMs / 60000);
            
            if (diffMins < 1) return 'Just now';
            if (diffMins < 60) return `${diffMins} min${diffMins > 1 ? 's' : ''}`;
            const hours = Math.floor(diffMins / 60);
            return `${hours} hour${hours > 1 ? 's' : ''}`;
        }

        function getInitials(name) {
            if (!name) return '?';
            const parts = name.trim().split(/\s+/);
            if (parts.length >= 2) {
                return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
            }
            return name.substring(0, 2).toUpperCase();
        }

        function renderActiveStaff(data, meta) {
            activeStaffBody.innerHTML = '';
            activeStaffLoading.style.display = 'none';

            if (!data || !data.length) {
                activeStaffBody.innerHTML = `<tr>
                    <td colspan="6" class="text-center py-5">
                        <div class="active-staff-empty-state">
                            <i class="fas fa-users mb-3 empty-state-icon"></i>
                            <div class="empty-state-message">No active staff detected in the last few minutes.</div>
                        </div>
                    </td>
                </tr>`;
                const countBadge = activeStaffCount.querySelector('.count-text') || activeStaffCount;
                if (countBadge.tagName === 'SPAN') {
                    countBadge.textContent = '0';
                } else {
                    activeStaffCount.innerHTML = `<i class="fas fa-circle status-dot-online"></i><span class="count-text">0</span> online`;
                }
                activeStaffCount.className = 'badge badge-pill active-staff-count-badge';
                activeStaffEmptyMessage.textContent = 'No active staff detected in the last few minutes.';
                renderPagination(null);
                return;
            }

            const total = meta?.total || data.length;
            const countBadge = activeStaffCount.querySelector('.count-text');
            if (countBadge) {
                countBadge.textContent = total;
            } else {
                activeStaffCount.innerHTML = `<i class="fas fa-circle status-dot-online"></i><span class="count-text">${total}</span> online`;
            }
            activeStaffCount.className = 'badge badge-pill active-staff-count-badge';
            activeStaffEmptyMessage.textContent = '';

            data.forEach((staff) => {
                const row = document.createElement('tr');
                row.className = 'active-staff-row';
                
                const avatar = staff.profile_img 
                    ? `<img src="${staff.profile_img}" alt="${staff.name}" class="user-avatar" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">`
                    : '';
                const avatarFallback = `<div class="user-avatar-fallback" style="${staff.profile_img ? 'display:none;' : ''}">${getInitials(staff.name)}</div>`;
                
                const teamBadge = staff.team_name 
                    ? `<span class="badge badge-light team-badge" ${staff.team_color ? `style="background-color: ${staff.team_color}20; color: ${staff.team_color}; border: 1px solid ${staff.team_color}40;"` : ''}>${staff.team_name}</span>`
                    : '<span class="text-muted">—</span>';
                
                const roleName = staff.role_name || `Role #${staff.role_id || '—'}`;
                const officeInfo = staff.office_name ? `<br><small class="text-muted"><i class="fas fa-building mr-1"></i>${staff.office_name}</small>` : '';

                row.innerHTML = `
                    <td>
                        <div class="d-flex align-items-center">
                            <div class="user-avatar-wrapper mr-2">
                                ${avatar}
                                ${avatarFallback}
                            </div>
                            <div>
                                <strong>${staff.name}</strong>
                                <br>
                                <span class="text-muted small">#${staff.id}</span>
                                ${officeInfo}
                            </div>
                        </div>
                    </td>
                    <td class="text-center">
                        <span class="status-indicator online" title="Online"></span>
                    </td>
                    <td>${roleName}</td>
                    <td>${teamBadge}</td>
                    <td>
                        <div>${formatTimeAgo(staff.last_activity)}</div>
                        <small class="text-muted">Active for ${getActivityDuration(staff.last_activity)}</small>
                    </td>
                    <td>${staff.last_login ? formatTimeAgo(staff.last_login) : '—'}</td>
                `;
                activeStaffBody.appendChild(row);
            });

            renderPagination(meta);
            updateSortIcons();
        }

        function renderPagination(meta) {
            if (!meta || meta.last_page <= 1) {
                activeStaffPagination.innerHTML = '';
                return;
            }

            let html = '<ul class="pagination pagination-sm mb-0">';
            
            // Previous button
            html += `<li class="page-item ${meta.current_page === 1 ? 'disabled' : ''}">
                <a class="page-link" href="#" data-page="${meta.current_page - 1}" ${meta.current_page === 1 ? 'tabindex="-1"' : ''}>
                    <i class="fas fa-chevron-left"></i>
                </a>
            </li>`;

            // Page numbers
            const startPage = Math.max(1, meta.current_page - 2);
            const endPage = Math.min(meta.last_page, meta.current_page + 2);

            if (startPage > 1) {
                html += `<li class="page-item"><a class="page-link" href="#" data-page="1">1</a></li>`;
                if (startPage > 2) {
                    html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
                }
            }

            for (let i = startPage; i <= endPage; i++) {
                html += `<li class="page-item ${i === meta.current_page ? 'active' : ''}">
                    <a class="page-link" href="#" data-page="${i}">${i}</a>
                </li>`;
            }

            if (endPage < meta.last_page) {
                if (endPage < meta.last_page - 1) {
                    html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
                }
                html += `<li class="page-item"><a class="page-link" href="#" data-page="${meta.last_page}">${meta.last_page}</a></li>`;
            }

            // Next button
            html += `<li class="page-item ${meta.current_page === meta.last_page ? 'disabled' : ''}">
                <a class="page-link" href="#" data-page="${meta.current_page + 1}" ${meta.current_page === meta.last_page ? 'tabindex="-1"' : ''}>
                    <i class="fas fa-chevron-right"></i>
                </a>
            </li>`;

            html += '</ul>';
            activeStaffPagination.innerHTML = html;

            // Add click handlers
            activeStaffPagination.querySelectorAll('a[data-page]').forEach(link => {
                link.addEventListener('click', (e) => {
                    e.preventDefault();
                    const page = parseInt(link.getAttribute('data-page'));
                    if (page && page !== activeStaffState.currentPage) {
                        activeStaffState.currentPage = page;
                        loadActiveStaff();
                    }
                });
            });
        }

        function updateSortIcons() {
            document.querySelectorAll('.sortable').forEach(th => {
                const sortIcon = th.querySelector('.sort-icon');
                if (!sortIcon) return;

                const sortValue = th.getAttribute('data-sort');
                if (sortValue === activeStaffState.sortBy) {
                    th.classList.add('active');
                    sortIcon.className = `fas fa-sort-${activeStaffState.sortDir === 'asc' ? 'up' : 'down'} sort-icon`;
                    sortIcon.style.color = '';
                } else {
                    th.classList.remove('active');
                    sortIcon.className = 'fas fa-sort sort-icon';
                    sortIcon.style.color = '';
                }
            });
        }

        function loadHistory() {
            historyBody.classList.add('loading');
            fetch(BROADCAST_API_BASE + '/history', {
                method: 'GET',
                headers: { Accept: 'application/json' },
                credentials: 'include',
            })
                .then((response) => {
                    if (!response.ok) {
                        throw new Error('Unable to load broadcast history.');
                    }
                    return response.json();
                })
                .then((payload) => {
                    // Store current staff info
                    currentState.isSuperAdmin = payload.is_super_admin || false;
                    currentState.currentStaffId = payload.current_staff_id || payload.current_user_id || null;
                    
                    renderHistoryTable(payload.data || [], currentState.isSuperAdmin);
                })
                .catch((error) => {
                    console.error(error);
                    showFeedback('danger', 'Failed to load broadcast history. Please try again.');
                })
                .finally(() => {
                    historyBody.classList.remove('loading');
                });
        }

        function loadMySent() {
            const mySentBody = document.getElementById('my-sent-body');
            mySentBody.classList.add('loading');
            
            fetch(BROADCAST_API_BASE + '/my-history', {
                method: 'GET',
                headers: { Accept: 'application/json' },
                credentials: 'include',
            })
                .then((response) => {
                    if (!response.ok) {
                        throw new Error('Unable to load your sent broadcasts.');
                    }
                    return response.json();
                })
                .then((payload) => {
                    renderMySentTable(payload.data || [], currentState.isSuperAdmin);
                })
                .catch((error) => {
                    console.error(error);
                    showFeedback('danger', 'Failed to load your sent broadcasts.');
                })
                .finally(() => {
                    mySentBody.classList.remove('loading');
                });
        }

        function loadMyRead() {
            const myReadBody = document.getElementById('my-read-body');
            myReadBody.classList.add('loading');
            
            fetch(BROADCAST_API_BASE + '/read-history', {
                method: 'GET',
                headers: { Accept: 'application/json' },
                credentials: 'include',
            })
                .then((response) => {
                    if (!response.ok) {
                        throw new Error('Unable to load your read broadcasts.');
                    }
                    return response.json();
                })
                .then((payload) => {
                    renderMyReadTable(payload.data || []);
                })
                .catch((error) => {
                    console.error(error);
                    showFeedback('danger', 'Failed to load your read broadcasts.');
                })
                .finally(() => {
                    myReadBody.classList.remove('loading');
                });
        }

        function deleteBroadcast(batchUuid) {
            if (!confirm('Are you sure you want to delete this broadcast? This action cannot be undone.')) {
                return;
            }

            fetch(`${BROADCAST_API_BASE}/${batchUuid}`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                credentials: 'include',
            })
                .then(async (response) => {
                    const payload = await response.json();
                    if (!response.ok) {
                        throw new Error(payload.message || 'Failed to delete broadcast.');
                    }
                    return payload;
                })
                .then(() => {
                    showFeedback('success', 'Broadcast deleted successfully.');
                    // Reload all tabs
                    loadHistory();
                    loadMySent();
                    loadMyRead();
                })
                .catch((error) => {
                    console.error(error);
                    showFeedback('danger', error.message || 'Failed to delete broadcast.');
                });
        }

        function loadBroadcastDetails(batchUuid, onSuccess) {
            detailBody.innerHTML = `<tr>
                <td colspan="3" class="text-center text-muted py-3">Loading recipients…</td>
            </tr>`;

            return fetch(`${BROADCAST_API_BASE}/${batchUuid}/details`, {
                method: 'GET',
                headers: { Accept: 'application/json' },
                credentials: 'include',
            })
                .then((response) => {
                    if (!response.ok) {
                        throw new Error('Unable to load broadcast details.');
                    }
                    return response.json();
                })
                .then((payload) => {
                    const data = payload.data;
                    detailTitle.textContent = data.title || '';
                    detailTitle.classList.toggle('d-none', !data.title);
                    detailMessage.innerHTML = data.message || ''; // Use innerHTML to render HTML content
                    detailMeta.textContent = `${data.sender_name || 'You'} • ${formatDate(data.sent_at)}`;

                    if (!Array.isArray(data.recipients) || !data.recipients.length) {
                        detailBody.innerHTML = `<tr>
                            <td colspan="3" class="text-center text-muted py-3">No recipients found.</td>
                        </tr>`;
                        if (typeof onSuccess === 'function') onSuccess();
                        return;
                    }

                    detailBody.innerHTML = '';
                    data.recipients.forEach((recipient) => {
                        const row = document.createElement('tr');
                        const statusBadge = recipient.read
                            ? '<span class="badge badge-success">Read</span>'
                            : '<span class="badge badge-secondary">Unread</span>';
                        row.innerHTML = `
                            <td>${recipient.receiver_name || `Staff #${recipient.receiver_id}`}</td>
                            <td>${statusBadge}</td>
                            <td>${recipient.read_at ? formatDate(recipient.read_at) : '-'}</td>
                        `;
                        detailBody.appendChild(row);
                    });
                    if (typeof onSuccess === 'function') onSuccess();
                })
                .catch((error) => {
                    console.error(error);
                    detailBody.innerHTML = `<tr>
                        <td colspan="3" class="text-center text-danger py-3">Failed to load recipients.</td>
                    </tr>`;
                });
        }

        function loadActiveStaff(showLoading = true) {
            if (activeStaffState.loading) return;
            
            activeStaffState.loading = true;
            if (showLoading) {
                activeStaffLoading.style.display = 'inline-block';
                activeStaffEmptyMessage.textContent = 'Loading active staff...';
            }

            const params = new URLSearchParams({
                threshold: 5,
                page: activeStaffState.currentPage,
                per_page: activeStaffState.perPage,
                sort_by: activeStaffState.sortBy,
                sort_dir: activeStaffState.sortDir,
            });

            if (activeStaffState.search) {
                params.append('search', activeStaffState.search);
            }
            if (activeStaffState.roleId) {
                params.append('role_id', activeStaffState.roleId);
            }
            if (activeStaffState.teamId) {
                params.append('team_id', activeStaffState.teamId);
            }

            fetch(`${ACTIVE_STAFF_JSON_URL}?${params.toString()}`, {
                method: 'GET',
                headers: { Accept: 'application/json' },
                credentials: 'include',
            })
                .then(async (response) => {
                    if (!response.ok) {
                        const error = await response.json().catch(() => ({}));
                        throw new Error(error.message || `HTTP ${response.status}: Unable to load active staff.`);
                    }
                    return response.json();
                })
                .then((payload) => {
                    activeStaffState.loaded = true;
                    activeStaffState.loading = false;
                    renderActiveStaff(payload.data || [], payload.meta || {});
                    activeStaffLastRefresh.textContent = `Last updated: ${new Date().toLocaleTimeString()}`;
                })
                .catch((error) => {
                    console.error('Active staff load error:', error);
                    activeStaffState.loading = false;
                    activeStaffLoading.style.display = 'none';
                    activeStaffBody.innerHTML = `<tr>
                        <td colspan="6" class="text-center py-5">
                            <div class="active-staff-empty-state">
                                <i class="fas fa-exclamation-triangle mb-3 empty-state-icon text-warning"></i>
                                <div class="empty-state-message">
                                    <strong class="text-danger d-block mb-2">Failed to load active staff</strong>
                                    <span class="text-muted">${error.message || 'Please try again or refresh the page.'}</span>
                                </div>
                                <button class="btn btn-sm mt-3" id="active-staff-retry-btn">
                                    <i class="fas fa-redo mr-1"></i> Retry
                                </button>
                            </div>
                        </td>
                    </tr>`;
                    activeStaffCount.innerHTML = `<i class="fas fa-circle status-dot-online"></i><span class="count-text">—</span> unavailable`;
                    activeStaffCount.className = 'badge badge-pill active-staff-count-badge';
                    
                    // Add retry button handler
                    const retryBtn = activeStaffBody.querySelector('#active-staff-retry-btn');
                    if (retryBtn) {
                        retryBtn.addEventListener('click', () => loadActiveStaff(true));
                    }
                });
        }

        function debounceLoadActiveStaff(delay = 300) {
            clearTimeout(activeStaffState.debounceTimeout);
            activeStaffState.debounceTimeout = setTimeout(() => {
                activeStaffState.currentPage = 1; // Reset to first page on filter change
                loadActiveStaff();
            }, delay);
        }

        // Tab-based loading
        if (activeStaffTab) {
            // Use jQuery for Bootstrap tab events
            $('#active-staff-tab').on('shown.bs.tab', function() {
                if (!activeStaffState.loaded && !activeStaffState.loading) {
                    loadActiveStaff();
                }
            });
        }

        composeForm.addEventListener('submit', (event) => {
            event.preventDefault();
            hideFeedback();

            // Get message from TinyMCE editor
            let messageContent = '';
            if (broadcastEditor) {
                messageContent = broadcastEditor.getContent({format: 'html'}).trim();
                const textContent = broadcastEditor.getContent({format: 'text'}).trim();
                
                // Validate content
                if (!textContent) {
                    showFeedback('warning', 'Please enter a message before sending your broadcast.');
                    broadcastEditor.focus();
                    return;
                }
                
                // Validate character limit
                if (textContent.length > 1000) {
                    showFeedback('warning', 'Message exceeds 1000 character limit. Please shorten your message.');
                    broadcastEditor.focus();
                    return;
                }
            } else {
                // Fallback to textarea if TinyMCE isn't loaded
                messageContent = messageInput.value.trim();
                if (!messageContent) {
                    showFeedback('warning', 'Please enter a message before sending your broadcast.');
                    messageInput.focus();
                    return;
                }
            }

            if (scopeSelect.value === 'specific' && recipientSelect.val().length === 0) {
                showFeedback('warning', 'Select at least one recipient or switch back to All staff.');
                return;
            }

            setSubmitting(true);

            fetch(BROADCAST_API_BASE + '/send', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                credentials: 'include',
                body: JSON.stringify({
                    title: titleInput.value || null,
                    message: messageContent,
                    scope: scopeSelect.value,
                    recipient_ids: scopeSelect.value === 'specific' ? recipientSelect.val() : [],
                }),
            })
                .then(async (response) => {
                    const payload = await response.json();
                    if (!response.ok) {
                        throw new Error(payload.message || 'Unable to send broadcast.');
                    }
                    return payload;
                })
                .then((payload) => {
                    showFeedback('success', 'Broadcast sent successfully.');
                    
                    // Reset form
                    composeForm.reset();
                    
                    // Reset TinyMCE editor
                    if (broadcastEditor) {
                        broadcastEditor.setContent('');
                        updateCharCount(broadcastEditor);
                    }
                    
                    // Reset recipient select
                    recipientSelect.val(null).trigger('change');
                    toggleRecipientsVisibility();
                    
                    // Reload history
                    loadHistory();
                })
                .catch((error) => {
                    console.error(error);
                    showFeedback('danger', error.message || 'Failed to send broadcast.');
                })
                .finally(() => {
                    setSubmitting(false);
                });
        });

        // Event delegation for history table actions (view and delete)
        historyBody.addEventListener('click', (event) => {
            const viewButton = event.target.closest('[data-action="view-broadcast"]');
            const deleteButton = event.target.closest('[data-action="delete-broadcast"]');
            
            if (viewButton) {
                const batchUuid = viewButton.getAttribute('data-batch');
                if (batchUuid) {
                    loadBroadcastDetails(batchUuid);
                    detailModal.modal('show');
                }
            }
            
            if (deleteButton) {
                const batchUuid = deleteButton.getAttribute('data-batch');
                if (batchUuid) {
                    deleteBroadcast(batchUuid);
                }
            }
        });

        // Event delegation for "My Sent" table
        const mySentBody = document.getElementById('my-sent-body');
        if (mySentBody) {
            mySentBody.addEventListener('click', (event) => {
                const viewButton = event.target.closest('[data-action="view-broadcast"]');
                const deleteButton = event.target.closest('[data-action="delete-broadcast"]');
                
                if (viewButton) {
                    const batchUuid = viewButton.getAttribute('data-batch');
                    if (batchUuid) {
                        loadBroadcastDetails(batchUuid);
                        detailModal.modal('show');
                    }
                }
                
                if (deleteButton) {
                    const batchUuid = deleteButton.getAttribute('data-batch');
                    if (batchUuid) {
                        deleteBroadcast(batchUuid);
                    }
                }
            });
        }

        // Tab switching event listeners
        $('#all-broadcasts-tab').on('shown.bs.tab', function() {
            loadHistory();
        });

        $('#my-sent-tab').on('shown.bs.tab', function() {
            loadMySent();
        });

        $('#my-read-tab').on('shown.bs.tab', function() {
            loadMyRead();
        });

        refreshBtn.addEventListener('click', (event) => {
            event.preventDefault();
            // Reload all tabs
            loadHistory();
            loadMySent();
            loadMyRead();
        });

        // Active Staff Event Listeners
        if (activeStaffRefresh) {
            activeStaffRefresh.addEventListener('click', (event) => {
                event.preventDefault();
                if (activeStaffState.loading) return;
                loadActiveStaff(true);
            });
        }

        if (activeStaffSearch) {
            activeStaffSearch.addEventListener('input', (e) => {
                activeStaffState.search = e.target.value.trim();
                debounceLoadActiveStaff(500);
            });
        }

        if (activeStaffRoleFilter) {
            activeStaffRoleFilter.addEventListener('change', (e) => {
                activeStaffState.roleId = e.target.value ? parseInt(e.target.value) : null;
                debounceLoadActiveStaff();
            });
        }

        if (activeStaffTeamFilter) {
            activeStaffTeamFilter.addEventListener('change', (e) => {
                activeStaffState.teamId = e.target.value ? parseInt(e.target.value) : null;
                debounceLoadActiveStaff();
            });
        }

        if (activeStaffClearFilters) {
            activeStaffClearFilters.addEventListener('click', (e) => {
                e.preventDefault();
                activeStaffState.search = '';
                activeStaffState.roleId = null;
                activeStaffState.teamId = null;
                activeStaffState.currentPage = 1;
                if (activeStaffSearch) activeStaffSearch.value = '';
                if (activeStaffRoleFilter) activeStaffRoleFilter.value = '';
                if (activeStaffTeamFilter) activeStaffTeamFilter.value = '';
                loadActiveStaff();
            });
        }

        // Sortable columns
        document.querySelectorAll('.sortable').forEach(th => {
            th.style.cursor = 'pointer';
            th.addEventListener('click', () => {
                const sortValue = th.getAttribute('data-sort');
                if (sortValue === activeStaffState.sortBy) {
                    activeStaffState.sortDir = activeStaffState.sortDir === 'asc' ? 'desc' : 'asc';
                } else {
                    activeStaffState.sortBy = sortValue;
                    activeStaffState.sortDir = 'asc';
                }
                loadActiveStaff();
            });
        });

        scopeSelect.addEventListener('change', function() {
            toggleRecipientsVisibility();
            
            // Re-initialize Select2 when dropdown becomes visible to fix width/position issues
            if (scopeSelect.value === 'specific' && !recipientSelect.data('select2-initialized')) {
                initializeRecipientSelect();
            }
        });

        function initializeRecipientSelect() {
            console.log('🔧 Initializing recipient Select2 dropdown...');
            
            recipientSelect.select2({
                width: '100%',
                placeholder: recipientSelect.data('placeholder') || 'Select recipients',
                minimumInputLength: 0,  // Allow showing all users when clicking dropdown
                ajax: {
                    url: GET_ASSIGNEE_AJAX_URL,
                    dataType: 'json',
                    delay: 250,
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    data(params) {
                        return {
                            likevalue: params.term || '',
                        };
                    },
                    processResults(data, params) {
                        // Handle both array and object responses with error handling
                        let items = [];
                        
                        if (Array.isArray(data)) {
                            items = data;
                        } else if (data && Array.isArray(data.data)) {
                            items = data.data;
                        } else if (data && data.error) {
                            console.error('Error loading recipients:', data.error);
                            return { results: [] };
                        } else {
                            console.warn('Unexpected response format:', data);
                            return { results: [] };
                        }
                        
                        return {
                            results: items.map((item) => ({
                                id: item.id,
                                text: item.assignee || item.agent_id || `Staff #${item.id}`,
                            })),
                        };
                    },
                    transport: function(params, success, failure) {
                        // Custom transport to handle authentication and errors properly
                        const requestParams = params.data;
                        const url = params.url + '?' + new URLSearchParams(requestParams).toString();
                        
                        fetch(url, {
                            method: 'GET',
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            credentials: 'include',
                        })
                        .then(response => {
                            if (!response.ok) {
                                if (response.status === 419) {
                                    throw new Error('CSRF token mismatch. Please refresh the page.');
                                } else if (response.status === 401) {
                                    throw new Error('Authentication required. Please log in again.');
                                } else {
                                    throw new Error(`HTTP ${response.status}: Unable to load staff list.`);
                                }
                            }
                            return response.json();
                        })
                        .then(data => {
                            console.log('✅ Recipients loaded:', data);
                            success(data);
                        })
                        .catch(error => {
                            console.error('❌ Failed to load recipients:', error);
                            failure();
                            
                            // Show user-friendly error
                            if (error.message.includes('CSRF')) {
                                showFeedback('danger', 'Session expired. Please refresh the page.');
                            } else if (error.message.includes('Authentication')) {
                                showFeedback('danger', 'Please log in again to continue.');
                            }
                        });
                        
                        return { abort: () => {} };
                    },
                    cache: true,
                },
            });
            
            recipientSelect.data('select2-initialized', true);
        }

        toggleRecipientsVisibility();
        loadHistory();

        // If arrived via broadcast notification link (?batch=xxx), open that broadcast's details modal
        const batchParam = new URLSearchParams(window.location.search).get('batch');
        if (batchParam) {
            loadBroadcastDetails(batchParam, () => {
                detailModal.modal('show');
                // Clear ?batch= from URL to avoid re-opening on refresh
                const url = new URL(window.location);
                url.searchParams.delete('batch');
                window.history.replaceState({}, '', url);
            });
        }
        // Active users will load when tab is clicked (tab-based loading)
    })();
</script>
@endpush


