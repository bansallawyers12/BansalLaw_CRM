@extends('layouts.crm_client_detail_dashboard')

@section('content')
    <main class="main-content">
        <header class="header">
            <div class="header-title-section">
                <h1>Dashboard</h1>
            </div>
            <div class="header-actions">
                <button type="button" class="action-btn action-btn-secondary" id="refreshDashboard" title="Refresh Dashboard (Alt+R)">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
            </div>
        </header>

        {{-- KPI Cards Section --}}
        <section class="kpi-cards">
            <x-dashboard.kpi-card 
                :title="'Active Matters'" 
                :count="$count_active_matter" 
                :route="route('clients.clientsmatterslist')"
                icon="fas fa-briefcase"
                icon-class="icon-active" 
            />
            
            <x-dashboard.kpi-card 
                :title="'Urgent Notes Deadlines'" 
                :count="$count_note_deadline"
                icon="fas fa-hourglass-half"
                icon-class="icon-pending" 
            />
            
            <x-dashboard.kpi-card 
                :title="'Cases Requiring Attention'" 
                :count="$count_cases_requiring_attention_data"
                icon="fas fa-check-circle"
                icon-class="icon-success" 
            />
        </section>

        @include('crm.partials.access-approvals-dashboard')

        {{-- Priority Focus Section --}}
        <section class="priority-focus">
            {{-- My Actions (Microsoft To Do Style) --}}
            <div class="focus-container todo-container">
                <div class="todo-header">
                    <div class="todo-header-left">
                        <h3>
                            <i class="fas fa-tasks" style="color: #2564cf;"></i> 
                            My Actions
                        </h3>
                        <span class="todo-count-badge">{{ $count_note_deadline }}</span>
                    </div>
                    {{-- Add Task popover template (outside attribute to avoid unescaped & in JS) --}}
                    <div id="add-task-popover-template" style="display:none;">
                        <div class="modern-popover-content add-task-layout">
                            <div class="form-group">
                                <label class="control-label"><i class="fa fa-user-circle"></i> Client</label>
                                <select id="assign_client_id" class="form-control js-data-example-ajaxccsearch__addmytask" placeholder="Search and select client..."></select>
                                <div id="client-error" class="error-message"></div>
                            </div>
                            <div class="form-group">
                                <label class="control-label"><i class="fa fa-users"></i> Assignees</label>
                                <div class="dropdown-multi-select" style="width: 100%;">
                                    <button type="button" class="btn btn-default dropdown-toggle" id="dropdownMenuButton" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false" style="width: 100%;">
                                        Select assignees <span class="selected-count"></span>
                                    </button>
                                    <div class="dropdown-menu" aria-labelledby="dropdownMenuButton" style="width: 100%;">
                                        <div class="dropdown-search-wrapper" style="padding: 8px; border-bottom: 1px solid #e2e8f0;">
                                            <input type="text" class="form-control assignee-search-input" placeholder="Search assignees..." style="font-size: 13px; padding: 6px 10px;">
                                        </div>
                                        <label class="dropdown-item"><input type="checkbox" id="select-all" /> <strong>Select All</strong></label>
                                        <div style="border-top: 1px solid #e2e8f0; margin: 5px 0;"></div>
                                        <div class="assignee-list">
                                            @foreach(\App\Models\Staff::where('status',1)->orderby('first_name','ASC')->get() as $admin)
                                                @php
                                                    $branchname = \App\Models\Branch::where('id',$admin->office_id)->first();
                                                    $searchText = strtolower($admin->first_name . $admin->last_name . (@$branchname->office_name ?? ''));
                                                    $searchText = str_replace(' ', '', $searchText);
                                                @endphp
                                                <label class="dropdown-item assignee-item" data-searchtext="{{ e($searchText) }}">
                                                    <input type="checkbox" class="checkbox-item" value="{{ $admin->id }}">
                                                    {{ e($admin->first_name) }} {{ e($admin->last_name) }} ({{ e(@$branchname->office_name ?? '') }})
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                                <select class="d-none" id="rem_cat" name="rem_cat[]" multiple="multiple">
                                    @foreach(\App\Models\Staff::where('status',1)->orderby('first_name','ASC')->get() as $admin)
                                        <option value="{{ $admin->id }}">{{ e($admin->first_name) }} {{ e($admin->last_name) }}</option>
                                    @endforeach
                                </select>
                                <div id="assignees-error" class="error-message"></div>
                            </div>
                            <div class="form-group form-group-full-width">
                                <label class="control-label"><i class="fa fa-comment"></i> Task Description</label>
                                <textarea id="assignnote" class="form-control" rows="3" placeholder="Enter task description..."></textarea>
                                <div id="note-error" class="error-message"></div>
                            </div>
                            <input id="task_group" name="task_group" type="hidden" value="Personal Action">
                            <div class="text-center">
                                <button type="button" class="btn btn-primary" id="add_my_task">
                                    <i class="fa fa-plus-circle"></i> Add My Task
                                </button>
                            </div>
                        </div>
                    </div>
                    <button class="todo-add-btn add_my_task" data-container="body" data-placement="bottom-start" data-html="true" data-content-id="add-task-popover-template" title="Add New Task">
                        <i class="fas fa-plus"></i>
                    </button>
                </div>
                
                <div class="todo-task-list-container">
                    @if(count($notesData) > 0)
                        <ul class="todo-task-list">
                            @foreach($notesData as $note)
                                <x-dashboard.task-item :note="$note" />
                            @endforeach
                        </ul>
                        @if($count_note_deadline > 6)
                            <div class="todo-load-more">
                                <p>Showing 6 of {{ $count_note_deadline }} actions</p>
                                <a href="{{ route('assignee.action') }}" class="todo-view-all-link">View all actions →</a>
                            </div>
                        @endif
                    @else
                        <div class="todo-empty-state">
                            <div class="todo-empty-icon">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <h4>All caught up!</h4>
                            <p>You have no actions at the moment.</p>
                            <button class="todo-empty-add-btn add_my_task" data-container="body" data-placement="bottom-start" data-html="true" data-content-id="add-task-popover-template" title="Add New Task">
                                <i class="fas fa-plus"></i>
                                Add an action
                            </button>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Cases Requiring Attention --}}
            <div class="focus-container">
                <div class="focus-header">
                    <h3>
                        <i class="fas fa-exclamation-circle" style="color: var(--warning-color);"></i> 
                        Cases Requiring Attention
                    </h3>
                    <span class="badge-count">{{ count($cases_requiring_attention_data) }}</span>
                </div>
                <div class="case-list-container">
                    @if(count($cases_requiring_attention_data) > 0)
                        <ul class="case-list">
                            @foreach($cases_requiring_attention_data as $case)
                                <x-dashboard.case-item :case="$case" />
                            @endforeach
                        </ul>
                    @else
                        <div class="empty-state-modern">
                            <i class="fas fa-thumbs-up fa-3x"></i>
                            <h4>Great Work!</h4>
                            <p>No cases requiring immediate attention.</p>
                        </div>
                    @endif
                </div>
            </div>
        </section>

        {{-- Client Matters Overview Section --}}
        <section class="cases-overview">
            <div class="cases-overview-header">
                <div class="header-left">
                    <h3>
                        <i class="fas fa-table"></i> 
                        Client Matters 
                        <span class="total-count">({{ $data->total() }} total)</span>
                    </h3>
                </div>
                <div class="header-right">
                    <x-dashboard.column-toggle :visibleColumns="$visibleColumns" />
                </div>
            </div>

            {{-- Filter Controls --}}
            <x-dashboard.filter-form :filters="$filters" :workflowStages="$workflowStages" />

            {{-- Data Table --}}
            <div class="table-responsive">
                <table class="data-table data-table-enhanced" role="grid">
                    <thead>
                        <tr role="row">
                            <th class="col-matter" role="columnheader">Matter</th>
                            <th class="col-client_id" role="columnheader">Client ID</th>
                            <th class="col-client_name" role="columnheader">Client Name</th>
                            <th class="col-dob" role="columnheader">DOB</th>
                            <th class="col-migration_agent" role="columnheader">Migration Agent</th>
                            <th class="col-person_responsible" role="columnheader">Person Responsible</th>
                            <th class="col-person_assisting" role="columnheader">Person Assisting</th>
                            <th class="col-stage" role="columnheader">Stage</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($data as $matter)
                            @if($matter && $matter->client_id)
                            <tr role="row" data-matter-id="{{ $matter->id ?? '' }}">
                                <td class="col-matter" style="white-space: initial;">
                                    <a href="{{ route('clients.detail', [base64_encode(convert_uuencode($matter->client_id)), $matter->client_unique_matter_no ?? '']) }}" class="matter-link">
                                        @if($matter->sel_matter_id == 1)
                                            General matter
                                        @else
                                            {{ $matter->matter->title ?? 'NA' }}
                                        @endif
                                        ({{ $matter->client_unique_matter_no ?? 'N/A' }})
                                    </a>
                                    @php
                                        $emailCount = 0;
                                        if($matter && $matter->client_id) {
                                            try {
                                                $emailCount = $matter->mailReports()
                                                    ->where('client_id', $matter->client_id)
                                                    ->where('conversion_type', 'conversion_email_fetch')
                                                    ->whereNull('mail_is_read')
                                                    ->where(function($query) {
                                                        $query->orWhere('mail_body_type', 'inbox')
                                                              ->orWhere('mail_body_type', 'sent');
                                                    })->count();
                                            } catch (\Exception $e) {
                                                $emailCount = 0;
                                            }
                                        }
                                    @endphp
                                    @if($emailCount > 0)
                                        <span class="badge badge-email" title="{{ $emailCount }} unread emails">
                                            <i class="fas fa-envelope"></i> {{ $emailCount }}
                                        </span>
                                    @endif
                                </td>
                                <td class="col-client_id">
                                    @php
                                        $clientDetailParams = [];
                                        if($matter && $matter->client_id) {
                                            $clientDetailParams = [base64_encode(convert_uuencode($matter->client_id))];
                                            if(!empty($matter->client_unique_matter_no)) {
                                                $clientDetailParams[] = $matter->client_unique_matter_no;
                                            }
                                        }
                                    @endphp
                                    @if(!empty($clientDetailParams))
                                        <a href="{{ route('clients.detail', $clientDetailParams) }}" class="client-id-link">
                                            {{ ($matter->client && $matter->client->client_id) ? $matter->client->client_id : config('constants.empty') }}
                                        </a>
                                    @else
                                        <span class="text-muted">{{ config('constants.empty') }}</span>
                                    @endif
                                </td>
                                <td class="col-client_name">
                                    @if($matter->client)
                                        {{ ($matter->client->first_name ?? '') ?: config('constants.empty') }} {{ ($matter->client->last_name ?? '') ?: config('constants.empty') }}
                                    @else
                                        {{ config('constants.empty') }}
                                    @endif
                                </td>
                                <td class="col-dob">
                                    @if($matter->client && $matter->client->dob)
                                        {{ \Carbon\Carbon::parse($matter->client->dob)->format('d/m/Y') }}
                                    @else
                                        {{ config('constants.empty') }}
                                    @endif
                                </td>
                                <td class="col-migration_agent">
                                    @if($matter->migrationAgent)
                                        <div class="user-avatar-cell">
                                            <div class="avatar-sm">
                                                {{ substr($matter->migrationAgent->first_name, 0, 1) }}{{ substr($matter->migrationAgent->last_name, 0, 1) }}
                                            </div>
                                            {{ $matter->migrationAgent->first_name }} {{ $matter->migrationAgent->last_name }}
                                        </div>
                                    @else
                                        {{ config('constants.empty') }}
                                    @endif
                                </td>
                                <td class="col-person_responsible">
                                    @if($matter->personResponsible)
                                        <div class="user-avatar-cell">
                                            <div class="avatar-sm">
                                                {{ substr($matter->personResponsible->first_name, 0, 1) }}{{ substr($matter->personResponsible->last_name, 0, 1) }}
                                            </div>
                                            {{ $matter->personResponsible->first_name }} {{ $matter->personResponsible->last_name }}
                                        </div>
                                    @else
                                        {{ config('constants.empty') }}
                                    @endif
                                </td>
                                <td class="col-person_assisting">
                                    @if($matter->personAssisting)
                                        <div class="user-avatar-cell">
                                            <div class="avatar-sm">
                                                {{ substr($matter->personAssisting->first_name, 0, 1) }}{{ substr($matter->personAssisting->last_name, 0, 1) }}
                                            </div>
                                            {{ $matter->personAssisting->first_name }} {{ $matter->personAssisting->last_name }}
                                        </div>
                                    @else
                                        {{ config('constants.empty') }}
                                    @endif
                                </td>
                                <td class="col-stage">
                                    <select class="form-select stageCls stage-select-enhanced" id="stage_{{ $matter->id }}" aria-label="Change stage for matter {{ $matter->client_unique_matter_no }}">
                                        @foreach($workflowStages as $stage)
                                            <option value="{{ $stage->id }}" {{ $matter->workflow_stage_id == $stage->id ? 'selected' : '' }}>
                                                {{ $stage->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </td>
                            </tr>
                            @endif
                        @empty
                            <tr>
                                <td colspan="8" class="empty-state">
                                    <div class="empty-state-modern">
                                        <i class="fas fa-inbox fa-3x"></i>
                                        <h4>No Records Found</h4>
                                        <p>Try adjusting your filters or search criteria.</p>
                                        @if(isset($filters['client_name']) || isset($filters['client_stage']))
                                            <a href="{{ route('dashboard') }}" class="btn btn-primary mt-3">
                                                <i class="fas fa-times"></i> Clear All Filters
                                            </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            
            {{-- Pagination --}}
            @if($data->hasPages())
                <div class="pagination-container">
                    <div class="pagination-info">
                        Showing <strong>{{ $data->firstItem() ?? 0 }}</strong> to <strong>{{ $data->lastItem() ?? 0 }}</strong> of <strong>{{ $data->total() }}</strong> results
                    </div>
                    <div class="pagination-links">
                        {{ $data->appends(request()->query())->links() }}
                    </div>
                </div>
            @endif
        </section>
    </main>

    {{-- Loading Overlay --}}
    <div class="loading-overlay" id="loadingOverlay" style="display: none;">
        <div class="spinner-container">
            <div class="spinner"></div>
            <p>Loading...</p>
        </div>
    </div>

    {{-- Toast Notification Container --}}
    <div class="toast-container" id="toastContainer"></div>

    {{-- Task Detail Panel --}}
    <x-dashboard.task-detail-panel />

    {{-- Modals --}}
    @include('components.dashboard.modals')
    
    {{-- Loading Overlay --}}
    <div class="popuploader" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999;">
        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 20px; border-radius: 5px; display: inline-block;">
            <i class="fa fa-spinner fa-spin" style="font-size: 24px; color: #007bff;"></i>
            <p style="margin-top: 10px; margin-bottom: 0;">Processing...</p>
        </div>
    </div>
@endsection

@push('styles')
@once
<link rel="stylesheet" href="{{ asset('css/dashboard.css') }}">
<style>
/* Microsoft To Do Style Task Widget */
.todo-container {
    background: #fafafa;
    border-radius: 12px;
    overflow: hidden;
    display: flex;
    flex-direction: column; /* Use flexbox for internal layout */
}

.todo-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    background: white;
    border-bottom: 1px solid #e0e0e0;
}

.todo-header-left {
    display: flex;
    align-items: center;
    gap: 12px;
}

.todo-header h3 {
    margin: 0;
    font-size: 20px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 10px;
}

.todo-count-badge {
    background: #2564cf;
    color: white;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 13px;
    font-weight: 600;
    min-width: 24px;
    text-align: center;
}

.todo-add-btn {
    width: 40px;
    height: 40px;
    border: none;
    background: #2564cf;
    color: white;
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
    box-shadow: 0 2px 8px rgba(37, 100, 207, 0.3);
}

.todo-add-btn:hover {
    background: #1e4fa0;
    transform: scale(1.05);
    box-shadow: 0 4px 12px rgba(37, 100, 207, 0.4);
}

.todo-task-list-container {
    background: white;
    flex: 1; /* Allow container to grow and fill available space */
    min-height: 0; /* Important for flex children with overflow */
    max-height: 500px; /* Prevent containers from becoming extremely long */
    overflow-y: auto; /* Enable scrolling when content exceeds container */
}

.todo-task-list {
    list-style: none;
    padding: 8px;
    margin: 0;
}

.todo-load-more {
    padding: 12px 16px;
    text-align: center;
    border-top: 1px solid #e0e0e0;
    font-size: 13px;
    color: #666;
    background: #fafafa;
}

.todo-load-more p {
    margin: 0 0 8px 0;
}

.todo-view-all-link {
    display: inline-block;
    color: #2564cf;
    text-decoration: none;
    font-weight: 500;
    font-size: 13px;
    transition: color 0.2s ease;
}

.todo-view-all-link:hover {
    color: #1a4fa0;
    text-decoration: underline;
}

.todo-empty-state {
    text-align: center;
    padding: 60px 20px;
}

.todo-empty-icon {
    font-size: 64px;
    color: #d0d0d0;
    margin-bottom: 16px;
}

.todo-empty-state h4 {
    color: #666;
    margin: 0 0 8px 0;
    font-size: 18px;
    font-weight: 600;
}

.todo-empty-state p {
    color: #999;
    font-size: 14px;
    margin: 0 0 24px 0;
}

.todo-empty-add-btn {
    padding: 10px 20px;
    border: none;
    background: #2564cf;
    color: white;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s ease;
}

.todo-empty-add-btn:hover {
    background: #1e4fa0;
    transform: translateY(-1px);
}

/* Fix Layout - Remove blank space on the side */
.main-content {
    margin-left: 0 !important;
    width: 100% !important;
    max-width: 100% !important;
    padding: 20px !important;
    box-sizing: border-box !important;
}

.sidebar-expanded + .main-content {
    margin-left: 0 !important;
    width: 100% !important;
}

/* Ensure all sections take full width */
.kpi-cards,
.priority-focus,
.cases-overview,
.quick-stats-banner {
    width: 100% !important;
    max-width: 100% !important;
    box-sizing: border-box;
}

.table-responsive {
    width: 100% !important;
    max-width: 100% !important;
    overflow-x: auto !important;
}

/* Test Page Specific Styles */
.header-title-section h1 {
    margin: 0;
    font-size: 1.8em;
    font-weight: 600;
    color: #333;
}

.header-actions {
    display: flex;
    gap: 10px;
    align-self: flex-start;
}

/* Enhanced Action Buttons */
.action-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 18px;
    border: none;
    border-radius: 8px;
    font-size: 0.9em;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.action-btn-primary {
    background: linear-gradient(135deg, #005792 0%, #003d66 100%);
    color: white;
}

.action-btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 87, 146, 0.4);
}

.action-btn-secondary {
    background: white;
    color: #005792;
    border: 2px solid #005792;
}

.action-btn-secondary:hover {
    background: #005792;
    color: white;
}

.action-btn-outline {
    background: transparent;
    color: #005792;
    border: 2px solid #005792;
}

.action-btn-outline:hover {
    background: #005792;
    color: white;
}

/* Focus Container Enhancements */
.focus-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.badge-count {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.85em;
    font-weight: 600;
}

.section-info {
    background: #e7f3ff;
    border-left: 3px solid #2196f3;
    padding: 10px 15px;
    border-radius: 4px;
    font-size: 0.85em;
    color: #0d47a1;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.section-info i {
    color: #2196f3;
}

.task-list-modern {
    list-style: none;
    padding: 0;
    margin: 0;
}

/* Modern Empty States */
.empty-state-modern {
    text-align: center;
    padding: 40px 20px;
    color: #999;
}

.empty-state-modern i {
    color: #cbd5e0;
    margin-bottom: 15px;
}

.empty-state-modern h4 {
    color: #666;
    margin: 10px 0 5px 0;
    font-size: 1.2em;
    font-weight: 600;
}

.empty-state-modern p {
    color: #999;
    font-size: 0.95em;
}

/* Enhanced Table Styles */
.data-table-enhanced {
    border-collapse: separate;
    border-spacing: 0;
}

.data-table-enhanced thead th {
    background: linear-gradient(to bottom, #f8f9fa 0%, #e9ecef 100%);
    font-weight: 600;
    color: #495057;
    border-bottom: 2px solid #dee2e6;
    padding: 12px 8px;
}

.data-table-enhanced tbody tr {
    transition: all 0.2s ease;
}

.data-table-enhanced tbody tr:hover {
    background-color: #f1f8ff;
    transform: scale(1.001);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.matter-link {
    color: #005792;
    font-weight: 500;
    text-decoration: none;
}

.matter-link:hover {
    color: #003d66;
    text-decoration: underline;
}

.client-id-link {
    color: #005792;
    font-weight: 600;
    text-decoration: none;
    font-family: 'Courier New', monospace;
}

/* Badge Styles */
.badge-email {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 3px 8px;
    border-radius: 12px;
    font-size: 0.75em;
    font-weight: 600;
    margin-left: 8px;
}

/* User Avatar in Table */
.user-avatar-cell {
    display: flex;
    align-items: center;
    gap: 8px;
}

.avatar-sm {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.7em;
    font-weight: 600;
    flex-shrink: 0;
}

/* Enhanced Stage Select */
.stage-select-enhanced {
    border: 1px solid #dee2e6;
    border-radius: 6px;
    padding: 6px 10px;
    font-size: 0.85em;
    transition: all 0.2s ease;
    width: 100%;
    min-width: 180px;
    white-space: normal;
    overflow: visible;
}

.stage-select-enhanced:focus {
    border-color: #005792;
    box-shadow: 0 0 0 3px rgba(0, 87, 146, 0.1);
    outline: none;
    z-index: 10;
    position: relative;
}

/* Fix stage column width */
.col-stage {
    min-width: 200px;
    max-width: 250px;
}

/* Loading Overlay */
.loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.6);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    backdrop-filter: blur(4px);
}

.spinner-container {
    text-align: center;
    color: white;
}

.spinner {
    width: 50px;
    height: 50px;
    border: 4px solid rgba(255, 255, 255, 0.3);
    border-top-color: white;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin: 0 auto 15px;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* Toast Notifications */
.toast-container {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 10000;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.toast {
    min-width: 300px;
    padding: 15px 20px;
    background: white;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    display: flex;
    align-items: center;
    gap: 12px;
    animation: slideInRight 0.3s ease;
}

@keyframes slideInRight {
    from {
        transform: translateX(400px);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

.toast-success {
    border-left: 4px solid #28a745;
}

.toast-error {
    border-left: 4px solid #dc3545;
}

.toast-info {
    border-left: 4px solid #17a2b8;
}

.toast-warning {
    border-left: 4px solid #ffc107;
}

.toast i {
    font-size: 1.5em;
}

.toast-success i { color: #28a745; }
.toast-error i { color: #dc3545; }
.toast-info i { color: #17a2b8; }
.toast-warning i { color: #ffc107; }

.toast-message {
    flex: 1;
    font-size: 0.95em;
    color: #333;
}

.toast-close {
    background: none;
    border: none;
    color: #999;
    font-size: 1.2em;
    cursor: pointer;
    padding: 0;
    width: 24px;
    height: 24px;
}

.toast-close:hover {
    color: #333;
}

/* Responsive Adjustments */
@media (max-width: 768px) {
    .dashboard-test-header {
        align-items: flex-start;
    }
    
    .header-actions {
        width: 100%;
    }
    
    .action-btn {
        flex: 1;
        justify-content: center;
    }
    
    .quick-stats-banner {
        grid-template-columns: 1fr;
    }
    
    .user-avatar-cell {
        flex-direction: column;
        align-items: flex-start;
        gap: 4px;
    }
    
    /* Todo Responsive */
    .todo-header {
        padding: 16px;
    }
    
    .todo-header h3 {
        font-size: 18px;
    }
    
    .todo-add-btn {
        width: 36px;
        height: 36px;
    }
    
    .todo-count-badge {
        font-size: 12px;
        padding: 3px 8px;
    }
    
    .priority-focus {
        flex-direction: column;
    }
    
    .focus-container {
        width: 100%;
    }

    /* Popover styling - matching action page */
    .popover {
        max-width: 600px !important;
        width: 600px !important;
        border-radius: 10px !important;
        box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12) !important;
        border: none !important;
        z-index: 9999 !important;
        overflow: hidden !important;
    }
    
    .popover.add-my-task-popover {
        position: fixed !important;
        left: 50% !important;
        top: 50% !important;
        transform: translate(-50%, -50%) !important;
        margin: 0 !important;
    }
    
    .popover.add-my-task-popover .arrow,
    .popover.add-my-task-popover .popover-arrow {
        display: none !important;
    }
    
    .popover-backdrop {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
        z-index: 9998;
        display: none;
    }
    
    .popover-backdrop.show {
        display: block;
    }
    
    .popover.show {
        display: block !important;
        opacity: 1 !important;
        visibility: visible !important;
    }

    .popover .popover-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
        color: white !important;
        border-bottom: none !important;
        border-radius: 8px 8px 0 0 !important;
        padding: 12px 18px !important;
        font-weight: 600 !important;
        font-size: 15px !important;
        letter-spacing: 0.5px !important;
    }

    .popover .popover-body {
        padding: 15px !important;
        word-wrap: break-word !important;
        white-space: normal !important;
    }

    .popover .popover-body * {
        box-sizing: border-box !important;
    }

    .popover .form-group {
        margin-bottom: 0 !important;
        box-sizing: border-box !important;
    }

    .popover .modern-popover-content {
        display: grid !important;
        grid-template-columns: 1fr 1fr !important;
        gap: 12px !important;
        padding: 5px !important;
    }

    .popover .modern-popover-content > .form-group {
        width: 100% !important;
        min-width: 0 !important;
        max-width: 100% !important;
    }

    .popover .modern-popover-content > .form-group-full-width {
        grid-column: 1 / -1 !important;
    }

    .popover .modern-popover-content > .text-center {
        grid-column: 1 / -1 !important;
        margin-top: 8px !important;
    }

    .popover .form-group label {
        font-weight: 600 !important;
        color: #2c3e50 !important;
        margin-bottom: 6px !important;
        display: block !important;
        font-size: 13px !important;
    }

    .popover .form-control {
        border: 1px solid #ced4da !important;
        border-radius: 6px !important;
        padding: 8px 12px !important;
        font-size: 14px !important;
        transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out !important;
        width: 100% !important;
        max-width: 100% !important;
        box-sizing: border-box !important;
        display: block !important;
    }

    .popover .form-control:focus {
        border-color: #0d6efd !important;
        box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25) !important;
        outline: 0 !important;
    }

    .popover textarea.form-control {
        min-height: 70px !important;
        resize: vertical !important;
        line-height: 1.5 !important;
    }

    .popover .btn {
        padding: 10px 24px !important;
        font-size: 14px !important;
        font-weight: 600 !important;
        border-radius: 8px !important;
        transition: all 0.2s ease !important;
        letter-spacing: 0.5px !important;
    }

    .popover .btn-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
        border: none !important;
        color: white !important;
    }

    .popover .btn-primary:hover {
        background: linear-gradient(135deg, #5568d3 0%, #6a3f8f 100%) !important;
        transform: translateY(-2px) !important;
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4) !important;
    }

    .popover .error-message {
        color: #dc3545 !important;
        font-size: 11px !important;
        margin-top: 3px !important;
        font-weight: 500 !important;
        display: block !important;
        min-height: 14px !important;
    }

    .popover .dropdown-multi-select {
        position: relative;
        display: block;
        width: 100%;
    }

    .popover .dropdown-multi-select .btn {
        width: 100%;
        text-align: left;
        background-color: #fff;
        border: 1px solid #ced4da;
        border-radius: 6px;
        padding: 8px 12px;
        font-size: 14px;
    }

    .popover .dropdown-multi-select .assignee-list {
        max-height: 200px;
        overflow-y: auto;
        padding: 8px;
    }

    .popover .dropdown-multi-select .dropdown-item {
        display: flex;
        align-items: center;
        padding: 6px 12px;
        border-radius: 4px;
        cursor: pointer;
        transition: background-color 0.15s ease;
    }

    .popover .dropdown-multi-select .dropdown-item:hover {
        background-color: #f8f9fa;
    }

    .popover .dropdown-multi-select .dropdown-item input[type="checkbox"] {
        margin-right: 8px;
        margin-bottom: 0;
    }

    .popover .js-data-example-ajaxccsearch__addmytask {
        width: 100%;
        border: 1px solid #ced4da;
        border-radius: 6px;
        padding: 8px 12px;
        font-size: 14px;
        background-color: #fff;
        transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
    }

    .popover .js-data-example-ajaxccsearch__addmytask:focus {
        border-color: #0d6efd;
        box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
        outline: 0;
    }

    .custom-error {
        color: #dc3545;
        font-size: 12px;
        margin-top: 5px;
        display: block;
    }
}
</style>
@endonce
@endpush

@push('scripts')
@once
<script src="{{URL::to('/')}}/js/popover.js"></script>
<script src="{{URL::to('/')}}/js/components/dropdown-multi-select.js"></script>
<script>
    // Define dashboard routes and data before loading the main script
    window.dashboardRoutes = {
        dashboard: "{{ route('dashboard') }}",
        updateStage: "{{ route('dashboard.update-stage') }}",
        columnPreferences: "{{ route('dashboard.column-preferences') }}",
        extendDeadline: "{{ route('dashboard.extend-deadline') }}",
        updateActionCompleted: "{{ route('dashboard.update-action-completed') }}",
        updateTaskCompleted: "{{ route('dashboard.update-action-completed') }}"
    };
    
    window.dashboardData = {
        visibleColumns: {!! json_encode($visibleColumns, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}
    };
    
    // Error handling for missing routes
    if (typeof window.dashboardRoutes === 'undefined') {
        console.error('Dashboard routes not defined');
    }
</script>
<script src="{{ asset('js/dashboard-optimized.js') }}"></script>
<script>
$(function () {
    // Initialize Add New Task popover - content from template (avoids unescaped & in data-content attribute)
    $('.add_my_task').each(function() {
        var $btn = $(this);
        var contentId = $btn.data('content-id');
        var popoverOpts = {
            html: true,
            sanitize: false,
            trigger: 'click',
            placement: 'top',
            boundary: 'viewport',
            container: 'body',
            title: '<i class="fa fa-plus-circle"></i> Add New Task',
            template: '<div class="popover add-my-task-popover" role="tooltip"><div class="popover-header"></div><div class="popover-body"></div></div>'
        };
        if (contentId && $('#' + contentId).length) {
            popoverOpts.content = function() { return $('#' + contentId).html(); };
        }
        $btn.popover(popoverOpts);
    });
    
    // Initialize client select for Add My Task popover
    $(document).on('shown.bs.popover', '.add_my_task', function() {
        var $popover = $('.popover.add-my-task-popover');
        if ($popover.length === 0) {
            $popover = $('.popover:visible').last();
            $popover.addClass('add-my-task-popover');
        }
        
        // Center the popover
        $popover.css({
            'position': 'fixed',
            'left': '50%',
            'top': '50%',
            'transform': 'translate(-50%, -50%)',
            'margin': '0',
            'z-index': '9999'
        });
        
        // Create and show backdrop
        if (!$('.popover-backdrop').length) {
            $('body').append('<div class="popover-backdrop"></div>');
        }
        $('.popover-backdrop').addClass('show');
        
        // Close popup when clicking backdrop
        $('.popover-backdrop').off('click').on('click', function() {
            $('.add_my_task').popover('hide');
        });
        
        // Initialize client Select2
        setTimeout(function() {
            initializeClientSelect2();
        }, 100);
    });
    
    // Hide backdrop when popover is hidden
    $(document).on('hidden.bs.popover', '.add_my_task', function() {
        $('.popover-backdrop').removeClass('show');
    });
    
    // Function to initialize client Select2
    function initializeClientSelect2() {
        var attempts = 0;
        var maxAttempts = 10;
        
        function tryInitialize() {
            attempts++;
            var $clientSelect = $('#assign_client_id');
            
            if ($clientSelect.length && $clientSelect.is(':visible')) {
                if ($clientSelect.hasClass('select2-hidden-accessible')) {
                    $clientSelect.select2('destroy');
                }
                
                try {
                    $clientSelect.select2({
                        closeOnSelect: true,
                        placeholder: 'Search client...',
                        allowClear: true,
                        width: '100%',
                        dropdownParent: $('.popover'),
                        ajax: {
                            url: '{{URL::to('/clients/get-allclients')}}',
                            dataType: 'json',
                            delay: 250,
                            processResults: function (data) {
                                if (!data || typeof data !== 'object') {
                                    return { results: [] };
                                }
                                return {
                                    results: data.items || []
                                };
                            },
                            cache: true
                        },
                        templateResult: formatRepomainMYTask,
                        templateSelection: formatRepoSelectionmainMYTask,
                        minimumInputLength: 1
                    });
                    return true;
                } catch (error) {
                    console.error('Error initializing Select2:', error);
                    return false;
                }
            } else if (attempts < maxAttempts) {
                setTimeout(tryInitialize, 50);
            }
        }
        
        tryInitialize();
    }
    
    // Helper functions for Select2 templates
    function formatRepomainMYTask (repo) {
        if (repo.loading) {
            return repo.text;
        }
        var cidSafe = String(repo.cid || '').replace(/'/g, '&#39;').replace(/&/g, '&amp;');
        var $container = $(
            "<div dataid='" + cidSafe + "' class='selectclient select2-result-repository ag-flex ag-space-between ag-align-center'>" +
            "<div class='ag-flex ag-align-start'>" +
                "<div class='ag-flex ag-flex-column col-hr-1'><div class='ag-flex'><span class='select2-result-repository__title text-semi-bold'></span>\u00A0</div>" +
                "<div class='ag-flex ag-align-center'><small class='select2-result-repository__description'></small></div>" +
            "</div>" +
            "</div>" +
            "<div class='ag-flex ag-flex-column ag-align-end'>" +
                "<span class='select2resultrepositorystatistics'></span>" +
            "</div>" +
            "</div>"
        );
        $container.find(".select2-result-repository__title").text(repo.name || '');
        $container.find(".select2-result-repository__description").text(repo.email || '');
        var statClass = (repo.status == 'Archived') ? 'ui label select2-result-repository__statistics' : 'ui label yellow select2-result-repository__statistics';
        if (repo.status) {
            $container.find(".select2resultrepositorystatistics").append($('<span class="' + statClass + '"></span>').text(repo.status));
        }
        return $container;
    }

    function formatRepoSelectionmainMYTask (repo) {
        return (repo && repo.name) || (repo && repo.text) || '';
    }
    
    // Add My Task submission - matching action page exactly
    // Use event delegation on document to catch clicks on dynamically loaded popover content
    $(document).on('click', '#add_my_task', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        // Show loading overlay
        if ($(".popuploader").length) {
            $(".popuploader").show();
        }
        
        var flag = true;
        
        // Remove any existing error messages
        $(".custom-error").remove();

        // Get selected assignees - search within the popover
        var selectedRemCat = [];
        var $popover = $(this).closest('.popover');
        if ($popover.length === 0) {
            $popover = $('.popover:visible');
        }
        
        $popover.find(".checkbox-item:checked").each(function() {
            selectedRemCat.push($(this).val());
        });

        if (selectedRemCat.length === 0) {
            if ($(".popuploader").length) {
                $(".popuploader").hide();
            }
            $popover.find('#dropdownMenuButton').after("<span class='custom-error' role='alert' style='color: red; font-size: 12px; display: block; margin-top: 5px;'>Assignee field is required.</span>");
            flag = false;
        }

        var assignnoteValue = $popover.find('#assignnote').val();
        if (!assignnoteValue || assignnoteValue.trim() == '') {
            if ($(".popuploader").length) {
                $(".popuploader").hide();
            }
            $popover.find('#assignnote').after("<span class='custom-error' role='alert' style='color: red; font-size: 12px; display: block; margin-top: 5px;'>Note field is required.</span>");
            flag = false;
        }

        if (flag) {
            var formData = {
                note_type: 'follow_up',
                description: assignnoteValue,
                client_id: $popover.find('#assign_client_id').val(),
                rem_cat: selectedRemCat,
                task_group: $popover.find('#task_group').val()
            };
            
            $.ajax({
                type: 'post',
                url: "{{URL::to('/')}}/clients/action/personal/store",
                headers: { 
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                dataType: 'json',
                data: formData,
                success: function(response) {
                    if ($(".popuploader").length) {
                        $(".popuploader").hide();
                    }
                    if (response && response.success) {
                        $(".add_my_task").popover('hide');
                        $('.popover-backdrop').removeClass('show');
                        // Reload page to show new action
                        setTimeout(() => {
                            location.reload();
                        }, 500);
                    } else {
                        alert(response && response.message ? response.message : 'An error occurred');
                    }
                },
                error: function(xhr, status, error) {
                    if ($(".popuploader").length) {
                        $(".popuploader").hide();
                    }
                    
                    var errorMsg = 'Failed to add task. Please try again.';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMsg = xhr.responseJSON.message;
                    } else if (xhr.responseText) {
                        try {
                            var errorData = JSON.parse(xhr.responseText);
                            if (errorData.message) {
                                errorMsg = errorData.message;
                            }
                        } catch(e) {}
                    }
                    alert(errorMsg);
                }
            });
        } else {
            if ($(".popuploader").length) {
                $(".popuploader").hide();
            }
        }
        
        return false;
    });
});
</script>
<script>
// Enhanced Dashboard Test Page JavaScript

// Toast Notification System
window.showToast = function(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    
    const icon = {
        success: 'fa-check-circle',
        error: 'fa-exclamation-circle',
        warning: 'fa-exclamation-triangle',
        info: 'fa-info-circle'
    }[type] || 'fa-info-circle';
    
    toast.innerHTML = `
        <i class="fas ${icon}"></i>
        <span class="toast-message">${message}</span>
        <button class="toast-close" onclick="this.parentElement.remove()">&times;</button>
    `;
    
    document.getElementById('toastContainer').appendChild(toast);
    
    setTimeout(() => {
        toast.style.animation = 'slideOutRight 0.3s ease';
        setTimeout(() => toast.remove(), 300);
    }, 5000);
};

// Override the default showNotification function
window.showNotification = function(message, type = 'info') {
    window.showToast(message, type);
};

// Loading Overlay Functions
window.showLoading = function() {
    document.getElementById('loadingOverlay').style.display = 'flex';
};

window.hideLoading = function() {
    document.getElementById('loadingOverlay').style.display = 'none';
};

// Refresh Dashboard
document.getElementById('refreshDashboard').addEventListener('click', function() {
    showLoading();
    setTimeout(() => {
        location.reload();
    }, 500);
});

// Keyboard Shortcuts
document.addEventListener('keydown', function(e) {
    // Alt + R to refresh
    if (e.altKey && e.key === 'r') {
        e.preventDefault();
        document.getElementById('refreshDashboard').click();
    }
    
    // Escape to close dropdowns
    if (e.key === 'Escape') {
        document.getElementById('columnDropdown')?.classList.remove('show');
    }
});

document.addEventListener('DOMContentLoaded', function() {
    // Dashboard ready
});

// Debounced search (override from original script)
let searchTimeout;
document.querySelector('input[name="client_name"]')?.addEventListener('input', function() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        console.log('Search query:', this.value);
    }, 500);
});

// Add animation to KPI cards on load
document.querySelectorAll('.card').forEach((card, index) => {
    card.style.animation = `fadeInUp 0.5s ease ${index * 0.1}s both`;
});

// Add CSS animation for cards
const style = document.createElement('style');
style.textContent = `
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    @keyframes slideOutRight {
        to {
            transform: translateX(400px);
            opacity: 0;
        }
    }
`;
document.head.appendChild(style);
</script>
@endonce
@endpush
