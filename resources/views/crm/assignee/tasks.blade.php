@extends('layouts.crm_client_detail')
@include('components.require-datatables')
@section('title', 'Tasks')

@section('styles')
<link rel="stylesheet" href="{{ asset('css/listing-pagination.css') }}">
<link rel="stylesheet" href="{{ asset('css/listing-container.css') }}">
<link rel="stylesheet" href="{{ asset('css/task-list.css') }}?v={{ @filemtime(public_path('css/task-list.css')) ?: time() }}">
@endsection

@section('content')
<div class="listing-container assignee-action-page">
    <section class="listing-section">
        <div class="listing-section-body">
            <div class="server-error">
                @include('../Elements/flash-message')
            </div>
            <div class="custom-error-msg"></div>

            <div class="card">
                <div class="card-header">
                    <div class="action-page-header">
                        <div class="action-page-header__title">
                            <span class="action-page-header__icon" aria-hidden="true">
                                <i class="fa-solid fa-list-check"></i>
                            </span>
                            <div>
                                <h4>Tasks</h4>
                                <p class="action-page-header__subtitle">Open tasks — complete, update, or open the matter</p>
                            </div>
                        </div>
                        <div class="card-header-actions">
                            <a class="btn btn-outline-navy" id="assigned_by_me" href="{{ URL::to('/assigned_by_me') }}">Assigned by me</a>
                            <a class="btn btn-outline-navy" id="archived-tab" href="{{ route('assignee.tasks.completed') }}">Completed</a>
                    {{-- Popover body from <template> (data-content attribute breaks on staff names with quotes / long HTML) --}}
                    <template id="action-add-task-popover-template">
                        <div class="modern-popover-content add-task-layout">
                            <div class="form-group">
                                <label class="control-label"><i class="fa-solid fa-user-circle"></i> Client</label>
                                <select id="add_task_client_select" class="form-control js-data-example-ajaxccsearch__addmytask" data-placeholder="Search and select client..."></select>
                                <div id="add_task_client_error" class="error-message"></div>
                            </div>
                            <div class="form-group">
                                <label class="control-label"><i class="fa-solid fa-users"></i> Assignees</label>
                                <div class="dropdown-multi-select" style="width: 100%;">
                                    <button type="button" class="btn btn-default dropdown-toggle" id="add_task_dropdown_btn" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false" style="width: 100%;">
                                        Select assignees <span class="selected-count"></span>
                                    </button>
                                    <div class="dropdown-menu" aria-labelledby="add_task_dropdown_btn" style="width: 100%;">
                                        <div class="dropdown-search-wrapper" style="padding: 8px; border-bottom: 1px solid #c8dcef;">
                                            <input type="text" class="form-control assignee-search-input" placeholder="Search assignees..." style="font-size: 13px; padding: 6px 10px;">
                                        </div>
                                        <label class="dropdown-item"><input type="checkbox" id="add_task_select_all" /> <strong>Select All</strong></label>
                                        <div style="border-top: 1px solid #c8dcef; margin: 5px 0;"></div>
                                        <div class="assignee-list">
                                            @foreach(\App\Models\Staff::where('status',1)->orderby('first_name','ASC')->get() as $admin)
                                                @php
                                                    $branchname = \App\Models\Branch::where('id',$admin->office_id)->first();
                                                    $searchText = strtolower($admin->first_name . $admin->last_name . @$branchname->office_name);
                                                    $searchText = str_replace(' ', '', $searchText);
                                                @endphp
                                                <label class="dropdown-item assignee-item" data-searchtext="{{ e($searchText) }}" data-staff-id="{{ $admin->id }}" data-staff-name="{{ e(trim($admin->first_name . ' ' . $admin->last_name)) }}">
                                                    <input type="checkbox" class="checkbox-item" value="{{ $admin->id }}">
                                                    {{ $admin->first_name }} {{ $admin->last_name }} ({{ @$branchname->office_name }})
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                                <select class="d-none" id="add_task_rem_cat" name="rem_cat[]" multiple="multiple">
                                    @foreach(\App\Models\Staff::where('status',1)->orderby('first_name','ASC')->get() as $admin)
                                        <option value="{{ $admin->id }}">{{ $admin->first_name }} {{ $admin->last_name }}</option>
                                    @endforeach
                                </select>
                                <div id="add_task_assignees_error" class="error-message"></div>
                            </div>
                            <div class="form-group form-group-full-width">
                                <label class="control-label"><i class="fa-solid fa-comment"></i> Task Description</label>
                                <textarea id="add_task_assignnote" class="form-control js-staff-mentions" rows="3" placeholder="Enter task description... (type @ to tag staff)"></textarea>
                                <div id="add_task_note_error" class="error-message"></div>
                            </div>
                            <input id="add_task_task_group" name="task_group" type="hidden" value="Personal Task">
                            <div class="text-center">
                                <button type="button" class="btn btn-primary" id="add_my_task_submit">
                                    <i class="fa-solid fa-circle-plus"></i> Add My Task
                                </button>
                            </div>
                        </div>
                    </template>
                    {{-- Do not use class "tab-button" here: global tab handler calls table.ajax.reload() on every .tab-button click and breaks this popover/Tom Select. --}}
                    {{-- Do not use data-role="popover": legacy public/js/popover.js conflicts with BS5 (re-inits empty popover + Tom Select without dropdownParent). --}}
                            <button type="button" class="btn btn-primary add_my_task add-my-task-header-btn" data-bs-toggle="popover" data-container="body" data-placement="bottom-start" data-html="true">
                                <i class="fa-solid fa-plus"></i> Add My Task
                            </button>
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    <div class="action-toolbar">
                        <div class="tabs">
                            <button type="button" class="tab-button active" data-filter="all">All <span class="badge" id="all-count">0</span></button>
                            <button type="button" class="tab-button" data-filter="call">Call <span class="badge" id="call-count">0</span></button>
                            <button type="button" class="tab-button" data-filter="checklist">Checklist <span class="badge" id="checklist-count">0</span></button>
                            <button type="button" class="tab-button" data-filter="review">Review <span class="badge" id="review-count">0</span></button>
                            <button type="button" class="tab-button" data-filter="query">Query <span class="badge" id="query-count">0</span></button>
                            <button type="button" class="tab-button" data-filter="urgent">Urgent <span class="badge" id="urgent-count">0</span></button>
                            <button type="button" class="tab-button" data-filter="personal_action">Personal Task <span class="badge" id="personal-task-count">0</span></button>
                            <button type="button" class="tab-button" data-filter="follow_up">Follow up <span class="badge" id="follow-up-count">0</span></button>
                        </div>
                        <div class="action-search">
                            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                            <input type="text" id="searchInput" placeholder="Search tasks..." aria-label="Search tasks">
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table yajra-datatable">
                            <thead>
                                <tr>
                                    <th data-column="DT_RowIndex">#</th>
                                    <th data-column="done">Done</th>
                                    <th data-column="assigner_name">Assigner</th>
                                    <th data-column="client_reference">Client / Matter</th>
                                    <th data-column="assign_date">Date</th>
                                    <th data-column="task_group">Type</th>
                                    <th data-column="note_description">Note</th>
                                    <th data-column="action">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                            </tbody>
                        </table>
                    </div>
                    <div id="actionInfiniteLoader" class="action-infinite-loader" hidden aria-live="polite">
                        <span class="action-infinite-loader__spinner" aria-hidden="true"></span>
                        <span>Loading more tasks...</span>
                    </div>
                    <div id="actionScrollSentinel" class="action-scroll-sentinel" aria-hidden="true"></div>
                    <div id="actionScrollInfo" class="action-scroll-info">Showing 0 of 0 entries</div>
                </div>
            </div>
        </div>
    </section>
</div>

<!-- Assign Modal -->
<div class="modal fade custom_modal" id="openassigneview" tabindex="-1" role="dialog" aria-labelledby="" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content taskview"></div>
    </div>
</div>

<!-- Task Completion Notes Modal -->
<div class="modal fade" id="completionNotesModal" tabindex="-1" role="dialog" aria-labelledby="completionNotesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content completion-notes-modal-content">
            <div class="modal-header completion-notes-modal-header">
                <h5 class="modal-title" id="completionNotesModalLabel">
                    <i class="fa-solid fa-check completion-task-modal-header-icon" aria-hidden="true"></i> Complete Task
                </h5>
                <button type="button" class="close completion-notes-modal-close" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body completion-notes-modal-body">
                <div class="form-group mb-0">
                    <label for="completionNotes" class="completion-notes-label">
                        <i class="fa-solid fa-comment"></i> Completion Notes/Feedback
                    </label>
                    <textarea 
                        class="form-control completion-notes-textarea" 
                        id="completionNotes" 
                        rows="5" 
                        placeholder="Enter any notes or feedback about completing this task..."
                    ></textarea>
                    <small class="form-text completion-notes-hint">
                        <i class="fa-solid fa-circle-info"></i> These notes will be saved in the activity log.
                    </small>
                </div>
            </div>
            <div class="modal-footer completion-notes-modal-footer">
                <button type="button" class="btn btn-cancel-complete" data-bs-dismiss="modal">
                    <i class="fa-solid fa-xmark"></i> Cancel
                </button>
                <button type="button" class="btn btn-complete-task-primary" id="confirmTaskCompletion">
                    <i class="fa-solid fa-check"></i> Complete Task
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<link rel="stylesheet" href="{{URL::to('/')}}/css/task-popover-modern.css?v={{ @filemtime(public_path('css/task-popover-modern.css')) ?: time() }}">
<script src="{{URL::to('/')}}/js/components/dropdown-multi-select.js"></script>
<script src="{{URL::to('/')}}/js/components/task-description-mentions.js?v={{ @filemtime(public_path('js/components/task-description-mentions.js')) ?: time() }}"></script>
<style>
/* Ensure popovers display correctly */

.btn_readmore {
    color: var(--sidebar-active, #3a6fa8) !important;
    text-decoration: none !important;
    background: none !important;
    border: none !important;
    padding: 0 !important;
    font-size: inherit !important;
    cursor: pointer !important;
}

.btn_readmore:hover {
    color: var(--navy, #1e3d60) !important;
    text-decoration: underline !important;
}

    /* Popover styling for better design */
    .popover {
        max-width: 600px !important;
        width: 600px !important;
        border-radius: 10px !important;
        box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12) !important;
        border: none !important;
        z-index: 9999 !important;
        overflow: hidden !important;
    }
    
    /* Center Add My Task popover in the middle of the page */
    .popover.add-my-task-popover {
        position: fixed !important;
        left: 50% !important;
        top: 50% !important;
        transform: translate(-50%, -50%) !important;
        margin: 0 !important;
        overflow: visible !important; /* Tom Select dropdown is clipped by overflow:hidden on .popover */
    }
    
    /* Hide arrow for centered Add My Task popover */
    .popover.add-my-task-popover .arrow,
    .popover.add-my-task-popover .popover-arrow {
        display: none !important;
    }
    
    /* Add backdrop for Add My Task popup */
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
    
    /* Fix popover display issues */
    .popover {
        display: block !important;
        opacity: 1 !important;
        visibility: visible !important;
    }
    
    /* Ensure popover shows on click */
    .popover.show {
        display: block !important;
        opacity: 1 !important;
        visibility: visible !important;
    }

.popover .popover-header {
    background: var(--navy, #1e3d60) !important;
    color: #fff !important;
    border-bottom: 1px solid var(--border, #c8dcef) !important;
    border-radius: 8px 8px 0 0 !important;
    padding: 16px 20px !important;
    font-weight: 600 !important;
    font-size: 15px !important;
    letter-spacing: 0.5px !important;
}

.popover .popover-body {
    padding: 20px !important;
    word-wrap: break-word !important;
    white-space: normal !important;
}

.popover .popover-body * {
    box-sizing: border-box !important;
}

/* Form styling within popover */
.popover .form-group {
    margin-bottom: 0 !important;
    box-sizing: border-box !important;
}

.popover .modern-popover-content {
    display: grid !important;
    grid-template-columns: 1fr 1fr !important;
    gap: 20px !important;
    padding: 5px !important;
}

/* Update Task: simple single-column modal form */
.popover .modern-popover-content.update-task-layout {
    display: flex !important;
    flex-direction: column !important;
    gap: 14px !important;
    grid-template-columns: none !important;
    max-width: 100%;
    padding: 0 !important;
}

.popover .modern-popover-content.update-task-layout > .form-group,
.popover .modern-popover-content.update-task-layout > .form-group-full-width,
.popover .modern-popover-content.update-task-layout > .text-center,
.popover .modern-popover-content.update-task-layout > .update-task-actions {
    width: 100% !important;
    max-width: 100% !important;
    min-width: 0 !important;
    grid-column: auto !important;
    margin-bottom: 0 !important;
}

.popover.update-task-popover {
    max-width: 440px !important;
    width: min(440px, 94vw) !important;
    overflow: visible !important;
    border-radius: 12px !important;
    border: 1px solid var(--border, #c8dcef) !important;
    box-shadow: 0 16px 40px rgba(30, 61, 96, 0.16) !important;
}

.popover.update-task-popover .popover-header {
    background: #fff !important;
    color: var(--navy, #1e3d60) !important;
    border-bottom: 1px solid var(--border, #c8dcef) !important;
    font-size: 1.05rem !important;
    font-weight: 700 !important;
    padding: 14px 18px !important;
}

.popover.update-task-popover .popover-body {
    background: #fff !important;
    padding: 16px 18px 18px !important;
}

.popover.update-task-popover .popover-arrow {
    display: none !important;
}

.popover.update-task-popover .control-label {
    margin-bottom: 6px !important;
    font-size: 12px !important;
    font-weight: 600 !important;
    color: var(--text-muted, #5e7a90) !important;
}

.popover.update-task-popover .control-label i {
    margin-right: 4px;
    color: var(--navy, #1e3d60);
}

.popover.update-task-popover .form-control,
.popover.update-task-popover select.form-control,
.popover.update-task-popover textarea.form-control {
    border: 1px solid var(--border, #c8dcef) !important;
    border-radius: 8px !important;
    padding: 9px 12px !important;
    font-size: 14px !important;
    line-height: 1.4 !important;
    color: var(--text-dark, #1a2c40) !important;
    background: #fff !important;
    min-height: 40px !important;
    box-shadow: none !important;
}

.popover.update-task-popover select.form-control {
    appearance: auto !important;
    -webkit-appearance: menulist !important;
    -moz-appearance: menulist !important;
    cursor: pointer;
}

.popover.update-task-popover textarea.form-control {
    min-height: 96px !important;
    resize: vertical !important;
}

.popover.update-task-popover .update-task-matter-box {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    flex-wrap: nowrap;
    padding: 10px 12px;
    border: 1px solid var(--border, #c8dcef);
    border-radius: 8px;
    background: var(--page-bg, #f0f6ff);
}

.popover.update-task-popover .update-task-matter-box .matter-meta {
    min-width: 0;
    flex: 1 1 auto;
    color: var(--navy, #1e3d60);
    font-weight: 700;
    font-size: 14px;
    line-height: 1.3;
}

.popover.update-task-popover .update-task-matter-box .matter-meta small {
    display: block;
    margin-top: 2px;
    font-weight: 500;
    font-size: 12px;
    color: var(--text-muted, #5e7a90);
}

.popover.update-task-popover .update-task-matter-box .btn-open-matter {
    flex: 0 0 auto;
    padding: 7px 12px !important;
    font-size: 12px !important;
    font-weight: 600 !important;
    letter-spacing: 0 !important;
    border-radius: 7px !important;
    white-space: nowrap;
    text-transform: none !important;
}

.popover.update-task-popover .update-task-actions {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    margin-top: 4px !important;
    padding-top: 12px;
    border-top: 1px solid var(--border, #c8dcef);
}

.popover.update-task-popover .update-task-actions .btn {
    padding: 9px 16px !important;
    font-size: 13px !important;
    font-weight: 600 !important;
    letter-spacing: 0 !important;
    text-transform: none !important;
    min-width: auto !important;
}

.popover.update-task-popover .update-task-actions .btn-secondary {
    background: #fff !important;
    border: 1px solid var(--border, #c8dcef) !important;
    color: var(--text-dark, #1a2c40) !important;
}

.popover.update-task-popover .error-message:empty {
    display: none !important;
    min-height: 0 !important;
    margin: 0 !important;
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
    margin-top: 10px !important;
}

.popover .form-group label {
    font-weight: 600 !important;
    color: var(--text-muted, #5e7a90) !important;
    margin-bottom: 8px !important;
    display: block !important;
    font-size: 13px !important;
}

.popover .form-control {
    border: 1px solid var(--border, #c8dcef) !important;
    border-radius: 6px !important;
    padding: 10px 12px !important;
    font-size: 14px !important;
    transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out !important;
    width: 100% !important;
    max-width: 100% !important;
    box-sizing: border-box !important;
    display: block !important;
    background: var(--card-bg, #fff) !important;
    color: var(--text-dark, #1a2c40) !important;
}

.popover .form-control:focus {
    border-color: var(--sidebar-active, #3a6fa8) !important;
    box-shadow: 0 0 0 0.2rem rgba(58, 111, 168, 0.2) !important;
    outline: 0 !important;
}

.popover textarea.form-control {
    min-height: 80px !important;
    resize: vertical !important;
    line-height: 1.5 !important;
}

.popover select.form-control {
    appearance: auto !important;
    -webkit-appearance: auto !important;
    -moz-appearance: auto !important;
    width: 100% !important;
    max-width: 100% !important;
    min-width: 100% !important;
}

.popover .ts-wrapper {
    width: 100% !important;
    max-width: 100% !important;
}

.popover .crm-ts-assignee {
    width: 100% !important;
    max-width: 100% !important;
}

/* Button styling */
.popover .btn {
    padding: 12px 30px !important;
    font-size: 14px !important;
    font-weight: 600 !important;
    border-radius: 8px !important;
    transition: all 0.2s ease !important;
    letter-spacing: 0.5px !important;
}

.popover .btn-primary {
    background: var(--navy, #1e3d60) !important;
    border: 1px solid var(--navy, #1e3d60) !important;
    color: #fff !important;
}

.popover .btn-primary:hover {
    background: var(--sidebar-active, #3a6fa8) !important;
    border-color: var(--sidebar-active, #3a6fa8) !important;
    transform: translateY(-2px) !important;
    box-shadow: 0 4px 12px rgba(30, 61, 96, 0.2) !important;
}

.popover .btn-info {
    background: var(--navy, #1e3d60) !important;
    border: 1px solid var(--navy, #1e3d60) !important;
    color: #fff !important;
}

.popover .btn-info:hover {
    background: var(--sidebar-active, #3a6fa8) !important;
    border-color: var(--sidebar-active, #3a6fa8) !important;
    transform: translateY(-2px) !important;
    box-shadow: 0 4px 12px rgba(30, 61, 96, 0.2) !important;
}

/* Error message styling */
.popover .error-message {
    color: var(--danger, #a83020) !important;
    font-size: 11px !important;
    margin-top: 4px !important;
    font-weight: 500 !important;
    display: block !important;
    min-height: 16px !important;
}

/* Box header styling */
.popover .box-header {
    border-bottom: 1px solid var(--border, #c8dcef) !important;
    padding-bottom: 15px !important;
    margin-bottom: 15px !important;
}

.popover .box-header:last-child {
    border-bottom: none !important;
    padding-bottom: 0 !important;
    margin-bottom: 0 !important;
}

/* Box footer styling */
.popover .box-footer {
    border-top: 1px solid var(--border, #c8dcef) !important;
    padding-top: 15px !important;
    margin-top: 15px !important;
    text-align: center !important;
}

/* Responsive adjustments */
@media (max-width: 576px) {
    .popover {
        max-width: 90vw !important;
        width: 90vw !important;
        left: 5vw !important;
    }
    
    .popover .popover-body {
        padding: 15px !important;
    }
    
    .popover .form-group {
        margin-bottom: 12px !important;
    }
    
    .popover .modern-popover-content {
        grid-template-columns: 1fr !important;
    }
}

/* Add My Task specific styling */
.popover .dropdown-multi-select {
    position: relative;
    display: block;
    width: 100%;
}

.popover .dropdown-multi-select .btn {
    width: 100%;
    text-align: left;
    background-color: var(--card-bg, #fff);
    border: 1px solid var(--border, #c8dcef);
    border-radius: 6px;
    padding: 8px 12px;
    font-size: 14px;
    color: var(--text-dark, #1a2c40);
    transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
}

.popover .dropdown-multi-select .btn:hover,
.popover .dropdown-multi-select .btn:focus {
    border-color: var(--sidebar-active, #3a6fa8);
    box-shadow: 0 0 0 0.2rem rgba(58, 111, 168, 0.2);
    outline: 0;
}

.popover .dropdown-multi-select .dropdown-menu {
    width: 100%;
    max-height: 300px;
    overflow: hidden;
    border: 1px solid var(--border, #c8dcef);
    border-radius: 6px;
    box-shadow: 0 4px 12px rgba(30, 61, 96, 0.1);
    padding: 0;
    margin-top: 2px;
}

/* Bootstrap manages the show class on dropdown-menu directly */
.popover .dropdown-multi-select .dropdown-menu:not(.show) {
    display: none;
}

.popover .dropdown-multi-select .dropdown-search-wrapper {
    padding: 8px;
    border-bottom: 1px solid var(--border, #c8dcef);
    background: var(--page-bg, #f0f6ff);
    position: sticky;
    top: 0;
    z-index: 10;
}

.popover .dropdown-multi-select .assignee-search-input {
    font-size: 13px;
    padding: 6px 10px;
    border-radius: 6px;
    border: 1px solid var(--border, #c8dcef);
    width: 100%;
}

.popover .dropdown-multi-select .assignee-search-input:focus {
    border-color: var(--sidebar-active, #3a6fa8);
    box-shadow: 0 0 0 2px rgba(58, 111, 168, 0.15);
    outline: none;
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

/* Override display for hidden items - CRITICAL for search to work */
.popover .dropdown-multi-select .assignee-item[style*="display: none"],
.popover .dropdown-multi-select .assignee-item.hidden {
    display: none !important;
}

.popover .dropdown-multi-select .dropdown-item:hover {
    background-color: var(--sidebar-bg, #ddeaf8);
}

.popover .dropdown-multi-select .dropdown-item input[type="checkbox"] {
    margin-right: 8px;
    margin-bottom: 0;
}

.popover .form-label {
    font-weight: 500;
    color: var(--text-muted, #5e7a90);
    margin-bottom: 8px;
    display: block;
    font-size: 14px;
}

.popover .form-group {
    margin-bottom: 20px;
}

.popover .form-group:last-child {
    margin-bottom: 0;
}

/* Client search: style lives on .ts-control (see task-popover-modern.css) — do not pad the wrapper */
.popover .js-data-example-ajaxccsearch__addmytask.ts-wrapper,
.popover select.js-data-example-ajaxccsearch__addmytask {
    width: 100%;
    border: 0;
    padding: 0;
    background: transparent;
    box-shadow: none;
}
    
    /* Final overflow prevention rules removed (redundant — handled by top-level styles) */

    /* Flatpickr z-index fix to appear above popovers */
    .flatpickr-calendar {
        z-index: 99999 !important;
    }

    /* Tom Select dropdown above Add My Task popover when parent is body */
    body > .ts-dropdown {
        z-index: 10050 !important;
    }

    body > .ts-dropdown {
        z-index: 10050 !important;
    }

    .popover .ts-wrapper {
        width: 100% !important;
        max-width: 100% !important;
    }
</style>
<script type="text/javascript">
$(function () {
    var actionAddTaskTpl = document.getElementById('action-add-task-popover-template');
    var actionAddTaskHtml = (actionAddTaskTpl && actionAddTaskTpl.innerHTML) ? String(actionAddTaskTpl.innerHTML).trim() : '';
    if (!actionAddTaskHtml && document.querySelector('.add_my_task')) {
        console.error('Action Add My Task: #action-add-task-popover-template is missing or empty.');
    }

    /**
     * Resolve the live Add My Task popover tip (Bootstrap 5 + jQuery compat).
     * Prefer Popover.getInstance(el).getTipElement(); aria-describedby alone can lag behind shown.bs.
     */
    function getAddTaskPopoverTip(triggerEl) {
        if (!triggerEl || !triggerEl.getAttribute) {
            return $();
        }
        if (typeof bootstrap !== 'undefined' && bootstrap.Popover) {
            try {
                var inst = bootstrap.Popover.getInstance(triggerEl);
                if (inst) {
                    var tipEl = null;
                    if (typeof inst.getTipElement === 'function') {
                        tipEl = inst.getTipElement();
                    } else if (inst.tip) {
                        tipEl = inst.tip;
                    }
                    if (tipEl) {
                        return $(tipEl);
                    }
                }
            } catch (err) { /* fall through */ }
        }
        var raw = triggerEl.getAttribute('aria-describedby') || '';
        var ids = raw.trim().split(/\s+/).filter(Boolean);
        for (var i = 0; i < ids.length; i++) {
            var byId = document.getElementById(ids[i]);
            if (byId) {
                return $(byId);
            }
        }
        var $marked = $('.popover.add-my-task-popover').filter(':visible').last();
        if ($marked.length && $marked.find('#add_task_client_select').length) {
            return $marked;
        }
        return $('.popover').filter(function() {
            return $(this).find('#add_task_client_select').length > 0;
        }).last();
    }

    $('.add_my_task').each(function() {
        var popoverOpts = {
            html: true,
            sanitize: false,
            trigger: 'click',
            placement: 'top',
            boundary: 'viewport',
            container: 'body',
            customClass: 'add-my-task-popover',
            title: '<i class="fa-solid fa-circle-plus"></i> Add New Task',
            template: '<div class="popover" role="tooltip"><div class="popover-arrow"></div><h3 class="popover-header"></h3><div class="popover-body"></div></div>'
        };
        if (actionAddTaskHtml) {
            popoverOpts.content = actionAddTaskHtml;
        }
        $(this).popover(popoverOpts);
    });

    var ACTION_PAGE_SIZE = 20;
    var actionScrollState = {
        ready: false,
        loadingMore: false,
        hasMore: true,
        total: 0,
        loaded: 0,
        drawCounter: 0,
        seenIds: {}
    };

    function getActionListFilter() {
        var $activeTab = $('.tabs .tab-button.active');
        return $activeTab.length ? ($activeTab.data('filter') || 'all') : 'all';
    }

    function applyActionListRequestData(d, start) {
        d.filter = getActionListFilter();
        d.search = d.search || {};
        d.search.value = $('#searchInput').val() || '';
        d.start = typeof start === 'number' ? start : 0;
        d.length = ACTION_PAGE_SIZE;
        return d;
    }

    function getActionRowId(row) {
        if (!row) {
            return '';
        }
        if (row.id != null && row.id !== '') {
            return String(row.id);
        }
        var html = String(row.done_action || '');
        var match = html.match(/data-id="(\d+)"/);
        return match ? match[1] : '';
    }

    function rememberActionRowIds(rows) {
        (rows || []).forEach(function(row) {
            var id = getActionRowId(row);
            if (id) {
                actionScrollState.seenIds[id] = true;
            }
        });
    }

    function getActionAjaxParams(start) {
        var params = {
            draw: actionScrollState.drawCounter + 1,
            start: parseInt(start, 10) || 0,
            length: ACTION_PAGE_SIZE,
            filter: getActionListFilter(),
            search: {
                value: $('#searchInput').val() || '',
                regex: false
            },
            order: [{ column: 4, dir: 'desc' }],
            columns: []
        };

        if (table) {
            var order = table.order();
            if (order && order.length) {
                params.order = order.map(function(item) {
                    return { column: item[0], dir: item[1] };
                });
            }
            var settings = table.settings()[0];
            params.columns = (settings.aoColumns || []).map(function(col, idx) {
                return {
                    data: col.data != null ? col.data : (col.mData != null ? col.mData : idx),
                    name: col.name != null ? col.name : (col.sName || ''),
                    searchable: col.searchable != null ? col.searchable : !!col.bSearchable,
                    orderable: col.orderable != null ? col.orderable : !!col.bSortable,
                    search: { value: '', regex: false }
                };
            });
        }

        actionScrollState.drawCounter = params.draw;
        return params;
    }

    function buildActionRowHtml(row, rowNumber) {
        return '<tr data-action-id="' + getActionRowId(row) + '">'
            + '<td>' + rowNumber + '</td>'
            + '<td>' + (row.done_action || '') + '</td>'
            + '<td>' + (row.assigner_name || '') + '</td>'
            + '<td>' + (row.client_reference || '') + '</td>'
            + '<td>' + (row.assign_date || '') + '</td>'
            + '<td>' + (row.task_group || '') + '</td>'
            + '<td>' + (row.note_description || '') + '</td>'
            + '<td>' + (row.action || '') + '</td>'
            + '</tr>';
    }

    function appendActionRows(rows) {
        if (!rows || !rows.length) {
            return 0;
        }
        var startNumber = actionScrollState.loaded;
        var appended = 0;
        var html = '';
        rows.forEach(function(row) {
            var id = getActionRowId(row);
            if (id && actionScrollState.seenIds[id]) {
                return;
            }
            if (id) {
                actionScrollState.seenIds[id] = true;
            }
            html += buildActionRowHtml(row, startNumber + appended + 1);
            appended += 1;
        });
        if (!appended) {
            return 0;
        }
        var $tbody = $('.assignee-action-page .yajra-datatable tbody');
        $tbody.append(html);

        $tbody.find('tr').slice(-appended).find('[data-bs-toggle="popover"]')
            .not('.update_task')
            .not('.add_my_task')
            .popover({
                html: true,
                sanitize: false,
                trigger: 'click',
                placement: 'bottom',
                boundary: 'viewport',
                container: 'body'
            });
        return appended;
    }

    function updateActionScrollInfo() {
        var loaded = actionScrollState.loaded;
        var total = actionScrollState.total;
        var text = loaded > 0
            ? ('Showing 1–' + loaded + ' of ' + total + (total === 1 ? ' entry' : ' entries'))
            : (total > 0 ? ('Showing 0 of ' + total + ' entries') : 'Showing 0 of 0 entries');
        $('#actionScrollInfo').text(text);
    }

    function setActionInfiniteLoader(visible) {
        $('#actionInfiniteLoader').prop('hidden', !visible);
    }

    function resetActionScrollState() {
        actionScrollState.ready = false;
        actionScrollState.loadingMore = false;
        actionScrollState.hasMore = true;
        actionScrollState.total = 0;
        actionScrollState.loaded = 0;
        actionScrollState.seenIds = {};
        setActionInfiniteLoader(false);
        updateActionScrollInfo();
    }

    function syncActionScrollStateFromJson(json, appendCount) {
        var batchCount = (json && json.data) ? json.data.length : 0;
        actionScrollState.total = json ? (json.recordsFiltered || 0) : 0;
        if (typeof appendCount === 'number') {
            actionScrollState.loaded += appendCount;
        } else {
            actionScrollState.loaded = batchCount;
            rememberActionRowIds(json && json.data);
        }
        actionScrollState.hasMore = actionScrollState.loaded < actionScrollState.total;
        actionScrollState.ready = actionScrollState.loaded > 0 || actionScrollState.total === 0;
        if (json && json.draw) {
            actionScrollState.drawCounter = parseInt(json.draw, 10) || actionScrollState.drawCounter;
        }
        updateActionScrollInfo();
    }

    function maybeFillActionViewport() {
        if (!actionScrollState.ready || !table || actionScrollState.loadingMore || !actionScrollState.hasMore) {
            return;
        }
        var sentinel = document.getElementById('actionScrollSentinel');
        if (!sentinel) {
            return;
        }
        var rect = sentinel.getBoundingClientRect();
        if (rect.top <= window.innerHeight + 120) {
            loadMoreActions();
        }
    }

    function loadMoreActions() {
        if (!actionScrollState.ready || !table || actionScrollState.loadingMore || !actionScrollState.hasMore) {
            return;
        }
        var start = actionScrollState.loaded;
        if (start < ACTION_PAGE_SIZE) {
            return;
        }

        actionScrollState.loadingMore = true;
        setActionInfiniteLoader(true);

        var params = getActionAjaxParams(start);

        $.ajax({
            url: "{{ route('tasks.list') }}",
            type: 'GET',
            data: params,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json'
            },
            success: function(json) {
                if (json && json.data && json.data.length) {
                    var appended = appendActionRows(json.data);
                    syncActionScrollStateFromJson(json, appended);
                    if (!appended) {
                        actionScrollState.hasMore = false;
                    }
                } else {
                    actionScrollState.hasMore = false;
                    updateActionScrollInfo();
                }
            },
            error: function(xhr) {
                var st = xhr && xhr.status;
                if (st === 401 || st === 419 || st === 403) {
                    window.location.reload();
                    return;
                }
                console.error('Action infinite scroll error:', st, xhr && xhr.responseText);
            },
            complete: function() {
                actionScrollState.loadingMore = false;
                setActionInfiniteLoader(false);
                window.requestAnimationFrame(maybeFillActionViewport);
            }
        });
    }

    var actionInfiniteScrollBound = false;

    function bindActionInfiniteScroll() {
        if (actionInfiniteScrollBound) {
            return;
        }
        actionInfiniteScrollBound = true;

        var sentinel = document.getElementById('actionScrollSentinel');
        if (!sentinel) {
            return;
        }

        if ('IntersectionObserver' in window) {
            var observer = new IntersectionObserver(function(entries) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) {
                        loadMoreActions();
                    }
                });
            }, {
                root: null,
                rootMargin: '240px 0px',
                threshold: 0
            });
            observer.observe(sentinel);
        }

        $(window).on('scroll.actionInfinite resize.actionInfinite', function() {
            maybeFillActionViewport();
        });
    }

    var table = ($.fn.DataTable && $('.yajra-datatable').length)
        ? $('.yajra-datatable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "{{ route('tasks.list') }}",
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json'
            },
            data: function(d) {
                applyActionListRequestData(d, 0);
            },
            error: function(xhr, error, thrown) {
                var st = xhr && xhr.status;
                // Expired CSRF/session or auth: Laravel often returns HTML; reload sends user to login
                if (st === 401 || st === 419 || st === 403) {
                    window.location.reload();
                    return;
                }
                console.error('DataTables Ajax Error:', error, thrown, st, xhr && xhr.responseURL);
                if (xhr && xhr.responseText && xhr.responseText.includes('Malformed UTF-8')) {
                    console.warn('UTF-8 encoding issue detected. Please refresh the page.');
                }
            }
        },
        columns: [
            {data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false},
            {data: 'done_action', name: 'done', orderable: false, searchable: false},
            {data: 'assigner_name', name: 'assigner_name', orderable: true, searchable: true},
            {data: 'client_reference', name: 'client_reference', orderable: true, searchable: true},
            {data: 'assign_date', name: 'assign_date', orderable: true, searchable: true},
            {data: 'task_group', name: 'task_group', orderable: true, searchable: true},
            {data: 'note_description', name: 'note_description', orderable: true, searchable: true},
            {data: 'action', name: 'action', orderable: false, searchable: false}
        ],
        drawCallback: function() {
            // Initialize popovers for dynamically added elements (exclude update_task buttons which are initialized manually)
            $('[data-bs-toggle="popover"]').not('.update_task').not('.add_my_task').popover({
                html: true,
                sanitize: false,
                trigger: 'click',
                placement: 'bottom',
                boundary: 'viewport',
                container: 'body'
            });

            // Update badge counts
            updateBadgeCounts();
        },
        layout: {
            topStart: null,
            topEnd: null,
            bottomStart: null,
            bottomEnd: null
        },
        paging: true,
        lengthChange: false,
        pageLength: ACTION_PAGE_SIZE,
        order: [[4, 'desc']],
        responsive: false,
        autoWidth: false,
        language: {
            emptyTable: 'No open tasks found',
            zeroRecords: 'No matching tasks found'
        }
    }) : null;

    if (table) {
        table.on('preXhr.dt', function() {
            if (!actionScrollState.loadingMore) {
                resetActionScrollState();
            }
        });

        table.on('xhr.dt', function(e, settings, json) {
            if (!actionScrollState.loadingMore) {
                syncActionScrollStateFromJson(json, false);
                bindActionInfiniteScroll();
                window.requestAnimationFrame(maybeFillActionViewport);
            }
        });
    }

    // Search functionality
    var actionSearchTimer = null;
    $('#searchInput').on('keyup', function() {
        clearTimeout(actionSearchTimer);
        actionSearchTimer = setTimeout(function() {
            if (table) { table.ajax.reload(); }
        }, 300);
    });

    // Deep link from client Tasks tab (note_id query param)
    (function () {
        var params = new URLSearchParams(window.location.search);
        var noteId = params.get('note_id');
        if (noteId && /^\d+$/.test(noteId)) {
            $('#searchInput').val(noteId);
            if (table) { table.ajax.reload(); }
        }
    })();

    // Helper function to escape HTML to prevent XSS
    function escapeHtml(text) {
        if (!text) return '';
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    // Function to generate Update Task popover content
    function getUpdateTaskContent(assignedTo, noteId, taskId, taskGroup, followupDate, clientId, matterRef, matterUrl, clientLabel) {
        // Sanitize all inputs to prevent XSS
        assignedTo = String(assignedTo || '');
        noteId = escapeHtml(noteId || '');
        taskId = escapeHtml(taskId || '');
        taskGroup = String(taskGroup || '');
        clientId = escapeHtml(clientId || '');
        matterRef = escapeHtml(matterRef || '');
        matterUrl = escapeHtml(matterUrl || '');
        clientLabel = escapeHtml(clientLabel || '');

        var matterBlock = '';
        if (matterUrl) {
            var matterTitle = matterRef || clientLabel || 'Open client';
            var matterSub = (matterRef && clientLabel) ? '<small>' + clientLabel + '</small>' : '';
            matterBlock = `
                <div class="form-group form-group-full-width">
                    <label class="control-label">Matter</label>
                    <div class="update-task-matter-box">
                        <div class="matter-meta">
                            ${matterTitle}
                            ${matterSub}
                        </div>
                        <a href="${matterUrl}" target="_blank" class="btn btn-primary btn-open-matter">Open</a>
                    </div>
                </div>`;
        }

        return `
            <div id="popover-content" class="modern-popover-content update-task-layout">
                ${matterBlock}
                <div class="form-group">
                    <label class="control-label" for="update_task_rem_cat">Assignee</label>
                    <select class="form-control update-task-native-select" id="update_task_rem_cat" name="rem_cat">
                        @foreach(\App\Models\Staff::where('status',1)->orderby('first_name','ASC')->get() as $admin)
                            <?php $branchname = \App\Models\Branch::where('id',$admin->office_id)->first(); ?>
                            <option value="{{ $admin->id }}" ${assignedTo == '{{ $admin->id }}' ? 'selected' : ''}>
                                {{ $admin->first_name }} {{ $admin->last_name }} ({{ @$branchname->office_name }})
                            </option>
                        @endforeach
                    </select>
                    <div id="assignee-error" class="error-message"></div>
                </div>

                <div class="form-group">
                    <label class="control-label" for="update_task_task_group">Group</label>
                    <select class="form-control update-task-native-select" id="update_task_task_group" name="task_group">
                        <option value="Call" ${taskGroup == 'Call' ? 'selected' : ''}>Call</option>
                        <option value="Checklist" ${taskGroup == 'Checklist' ? 'selected' : ''}>Checklist</option>
                        <option value="Review" ${taskGroup == 'Review' ? 'selected' : ''}>Review</option>
                        <option value="Query" ${taskGroup == 'Query' ? 'selected' : ''}>Query</option>
                        <option value="Urgent" ${taskGroup == 'Urgent' ? 'selected' : ''}>Urgent</option>
                        <option value="Personal Task" ${taskGroup == 'Personal Task' || taskGroup == 'Personal Action' ? 'selected' : ''}>Personal Task</option>
                        <option value="Follow Up" ${taskGroup == 'Follow Up' || taskGroup == 'Follow up' || taskGroup == 'follow_up' ? 'selected' : ''}>Follow up</option>
                    </select>
                    <div id="task-group-error" class="error-message"></div>
                </div>

                <div class="form-group form-group-full-width">
                    <label class="control-label" for="update_task_assignnote">Description</label>
                    <textarea id="update_task_assignnote" class="form-control js-staff-mentions" rows="4" placeholder="Type @ to tag staff">${noteId}</textarea>
                    <div id="note-error" class="error-message"></div>
                </div>

                <input id="assign_note_id" type="hidden" value="${taskId}">
                <input id="update_task_client_id" type="hidden" value="${clientId}">

                <div class="update-task-actions">
                    <button type="button" class="btn btn-secondary" id="updateTaskCancel">Cancel</button>
                    <button type="button" class="btn btn-primary" id="updateTask">Save</button>
                </div>
            </div>`;
    }

    $(document).on('shown.bs.popover', '.add_my_task', function() {
        var triggerEl = this;

        function finishShown($popover) {
            if (!$popover || !$popover.length) {
                return;
            }
            $popover.addClass('add-my-task-popover');

            $popover.css({
                'position': 'fixed',
                'left': '50%',
                'top': '50%',
                'transform': 'translate(-50%, -50%)',
                'margin': '0',
                'z-index': '9999'
            });

            if (!$('.popover-backdrop').length) {
                $('body').append('<div class="popover-backdrop"></div>');
            }
            $('.popover-backdrop').addClass('show');

            $('.popover-backdrop').off('click').on('click', function() {
                $('.add_my_task').popover('hide');
            });

            setTimeout(function() {
                initializeClientTomSelect($popover, triggerEl, getAddTaskPopoverTip);
            }, 120);
        }

        var $tip = getAddTaskPopoverTip(triggerEl);
        if ($tip.length) {
            finishShown($tip);
            return;
        }
        var retries = 0;
        (function waitForTip() {
            $tip = getAddTaskPopoverTip(triggerEl);
            if ($tip.length) {
                finishShown($tip);
                return;
            }
            if (retries++ < 25) {
                setTimeout(waitForTip, 40);
            }
        })();
    });

    $(document).on('hide.bs.popover', '.add_my_task', function() {
        var $tip = getAddTaskPopoverTip(this);
        var $sel = $tip.find('#add_task_client_select');
        if ($sel.length && typeof destroyTS === 'function') {
            destroyTS($sel[0]);
        }
    });

    $(document).on('hidden.bs.popover', '.add_my_task', function() {
        $('.popover-backdrop').removeClass('show');
    });

    /**
     * @param {JQuery} $rootPopover - initial tip guess
     * @param {HTMLElement} triggerEl - popover trigger (for aria-describedby retries)
     * @param {function(HTMLElement): JQuery} resolveTip
     */
    function initializeClientTomSelect($rootPopover, triggerEl, resolveTip) {
        var attempts = 0;
        var maxAttempts = 40;
        resolveTip = resolveTip || getAddTaskPopoverTip;

        function tryInitialize() {
            attempts++;
            var $popover = resolveTip(triggerEl);
            if (!$popover.length && $rootPopover && $rootPopover.length) {
                $popover = $rootPopover;
            }
            var $clientSelect = $popover.find('#add_task_client_select').addBack('#add_task_client_select').first();

            if ($clientSelect.length && $popover.length) {
                if (typeof initTS !== 'function' || typeof buildGetAllClientsTomSelectConfig !== 'function' || typeof destroyTS !== 'function') {
                    if (attempts < maxAttempts) {
                        setTimeout(tryInitialize, 50);
                    }
                    return;
                }
                try {
                    var el = $clientSelect[0];
                    destroyTS(el);
                    initTS(el, buildGetAllClientsTomSelectConfig({
                        url: '{{URL::to('/clients/get-allclients')}}',
                        dropdownParent: 'body',
                        placeholder: 'Search client...'
                    }));
                    var _tsW = el.tomselect && el.tomselect.wrapper;
                    if (_tsW) {
                        _tsW.style.width = '100%';
                    }
                    return true;
                } catch (error) {
                    console.error('Error initializing client Tom Select:', error);
                    return false;
                }
            } else if (attempts < maxAttempts) {
                setTimeout(tryInitialize, 50);
            } else {
                console.warn('Add My Task: client Tom Select could not be initialized (popover or select missing).');
            }
        }

        tryInitialize();
    }

    // Initialize Update Task popover (native selects — no Tom Select placeholders)
    $(document).on('shown.bs.popover', '.update_task', function() {
        var $shell = $('.popover.show').filter(function() {
            return $(this).find('.update-task-layout').length > 0;
        }).last();
        if (!$shell.length) {
            $shell = $('.popover.show').last();
        }
        $shell.addClass('update-task-popover');
        $shell.css({
            'position': 'fixed',
            'left': '50%',
            'top': '50%',
            'transform': 'translate(-50%, -50%)',
            'margin': '0',
            'z-index': '1060'
        });

        if (!$('.popover-backdrop').length) {
            $('body').append('<div class="popover-backdrop"></div>');
        }
        $('.popover-backdrop').addClass('show').off('click.updateTask').on('click.updateTask', function() {
            $('.update_task').popover('hide');
        });
    });

    $(document).on('click', '#updateTaskCancel', function() {
        $('.update_task').popover('hide');
    });

    $(document).on('hide.bs.popover', '.update_task', function() {
        $('.popover-backdrop').removeClass('show').off('click.updateTask');
    });

    $(document).on('hidden.bs.popover', '.update_task', function() {
        if (!$('.popover.add-my-task-popover.show').length) {
            $('.popover-backdrop').removeClass('show');
        }
    });

    // Update badge counts
    function updateBadgeCounts() {
        $.ajax({
            url: "{{ route('tasks.counts') }}",
            method: "GET",
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json'
            },
            success: function(data) {
                if (data && typeof data === 'object') {
                    $('#all-count').text(data.all || 0);
                    $('#call-count').text(data.call || 0);
                    $('#checklist-count').text(data.checklist || 0);
                    $('#review-count').text(data.review || 0);
                    $('#query-count').text(data.query || 0);
                    $('#urgent-count').text(data.urgent || 0);
                    $('#personal-task-count').text(data.personal_action || 0);
                    $('#follow-up-count').text(data.follow_up || 0);
                    if (typeof window.refreshCrmNavPendingTaskCount === 'function') {
                        window.refreshCrmNavPendingTaskCount();
                    }
                } else {
                    console.warn('Invalid badge count data received');
                }
            },
            error: function(xhr) {
                if (xhr.status === 401 || xhr.status === 419 || xhr.status === 403) {
                    window.location.reload();
                    return;
                }
                console.error('Error fetching badge counts:', xhr.responseText);
            }
        });
    }

    // Filter by tabs (scoped to .tabs only — Add My Task must not have .tab-button or each click reloads the grid and tears down the popover)
    $('.tabs .tab-button').on('click', function() {
        $('.tabs .tab-button').removeClass('active');
        $(this).addClass('active');
        if (table) { table.ajax.reload(); }
    });

    // Handle Update Task button click
    $('.yajra-datatable').on('click', '.update_task', function() {
        var $button = $(this);
        var assignedTo = $button.data('assignedto') || '';
        var noteId = $button.data('noteid') || '';
        var taskId = $button.data('taskid') || '';
        var taskGroup = $button.data('taskgroupid') || '';
        var followupDate = $button.data('actiondate') || '';
        var clientId = $button.data('clientid') || '';
        var matterRef = $button.attr('data-matterref') || '';
        var matterUrl = $button.attr('data-matterurl') || '';
        var clientLabel = $button.attr('data-clientlabel') || '';

        // Set popover content
        $button.popover('dispose'); // Dispose of any existing popover
        $button.popover({
            html: true,
            sanitize: false,
            title: 'Update Task',
            content: getUpdateTaskContent(assignedTo, noteId, taskId, taskGroup, followupDate, clientId, matterRef, matterUrl, clientLabel),
            trigger: 'manual',
            placement: 'auto',
            boundary: 'viewport',
            customClass: 'update-task-popover',
            template: '<div class="popover" role="tooltip"><div class="popover-header"></div><div class="popover-body"></div></div>',
            container: 'body'
        }).popover('show');
    });

    // Close popover when clicking outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.popover').length && !$(e.target).closest('.update_task').length && !$(e.target).closest('.btn_readmore').length) {
            $('.update_task').popover('hide');
            $('.btn_readmore').popover('hide');
        }
    });

    // Handle Read More button clicks specifically
    $(document).on('click', '.btn_readmore', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        var $button = $(this);
        var fullContent = $button.data('full-content');
        
        // Only show popover if content exists
        if (!fullContent) {
            console.warn('No content found for read more button');
            return;
        }
        
        // Hide any other open popovers
        $('.update_task').popover('hide');
        $('.btn_readmore').popover('hide');
        
        // Set popover content and show safely with HTML escaping
        $button.popover('dispose');
        $button.popover({
            html: true,
            sanitize: true,
            content: escapeHtml(fullContent),
            trigger: 'manual',
            placement: 'top'
        }).popover('show');
    });

    // Re-initialize popovers after DataTable redraw
    $(document).on('draw.dt', '.yajra-datatable', function() {
        // Destroy existing popovers
        $('.btn_readmore').popover('dispose');
    });

    // Handle Update Task submission
    $(document).on('click', '#updateTask', function() {
        var $popover = $(this).closest('.popover');
        
        if (!$popover.length) {
            console.error('Popover not found');
            return;
        }
        
        var taskId = $popover.find('#assign_note_id').val() || '';
        var clientId = $popover.find('#update_task_client_id').val() || '';
        var assignee = $popover.find('#update_task_rem_cat').val() || '';
        var note = $popover.find('#update_task_assignnote').val() || '';
        var taskGroup = $popover.find('#update_task_task_group').val() || '';

        // Clear previous error messages
        $popover.find('.error-message').text('');

        // Client-side validation
        var isValid = true;
        if (!assignee) {
            $popover.find('#assignee-error').text('Please select an assignee.');
            isValid = false;
        }
        if (!note || note.trim() === '') {
            $popover.find('#note-error').text('Please enter a note.');
            isValid = false;
        }
        if (!taskGroup) {
            $popover.find('#task-group-error').text('Please select a task group.');
            isValid = false;
        }

        if (!isValid) {
            return; // Stop submission if validation fails
        }

        $.ajax({
            type: 'post',
            url: "{{ route('tasks.update') }}",
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
            data: {
                id: taskId,
                client_id: clientId,
                assigned_to: assignee,
                description: note,
                task_group: taskGroup
            },
            success: function(response) {
                $('.update_task').popover('hide');
                if (table) { table.draw(false); }
                if (typeof iziToast !== 'undefined') {
                    iziToast.success({ title: 'Updated', message: 'Task updated successfully.', position: 'topRight', timeout: 3000 });
                }
            },
            error: function(xhr) {
                console.error('Error updating task:', xhr.responseText);
                var msg = 'An error occurred while updating the task.';
                try { var r = JSON.parse(xhr.responseText); if (r.message) msg = r.message; } catch(e) {}
                if (typeof iziToast !== 'undefined') {
                    iziToast.error({ title: 'Error', message: msg, position: 'topRight', timeout: 5000 });
                } else {
                    alert(msg);
                }
            }
        });
    });

    // Delete record
    $('.yajra-datatable').on('click', '.deleteNote', function(e) {
        e.preventDefault();
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });

        var url = $(this).data('remote');
        
        if (!url) {
            console.error('No delete URL found');
            alert('Unable to delete: missing URL');
            return;
        }
        
        var deleteConfirm = confirm("Are you sure?");
        if (deleteConfirm) {
            $.ajax({
                url: url,
                type: 'DELETE',
                dataType: 'json',
                data: {method: '_DELETE', submit: true}
            }).done(function(data) {
                if (table) { table.draw(false); }
                if (typeof iziToast !== 'undefined') {
                    iziToast.success({ title: 'Deleted', message: 'Task deleted.', position: 'topRight', timeout: 2500 });
                }
            }).fail(function(xhr) {
                console.error('Error deleting task:', xhr.responseText);
                var msg = 'Could not delete task. Please try again.';
                try { var r = JSON.parse(xhr.responseText); if (r.message) msg = r.message; } catch(e) {}
                if (typeof iziToast !== 'undefined') {
                    iziToast.error({ title: 'Error', message: msg, position: 'topRight', timeout: 5000 });
                } else {
                    alert(msg);
                }
            });
        }
    });

    // Complete task - open modal
    var currentTaskId = null;
    var currentTaskGroupId = null;
    
    $('.yajra-datatable').on('click', '.complete_task', function() {
        var row_id = $(this).attr('data-id');
        var row_unique_group_id = $(this).attr('data-unique_group_id') || '';
        
        if (!row_id) {
            console.error('No task ID found');
            return;
        }
        
        // Store task IDs for later use
        currentTaskId = row_id;
        currentTaskGroupId = row_unique_group_id;
        
        // Clear previous notes
        $('#completionNotes').val('');
        
        // Show the completion notes modal
        $('#completionNotesModal').modal('show');
    });
    
    // Handle task completion with notes
    $(document).on('click', '#confirmTaskCompletion', function() {
        var completionNotes = $('#completionNotes').val().trim();
        
        if (!currentTaskId) {
            console.error('No task ID found');
            return;
        }
        
        // Disable button to prevent double submission
        var $button = $(this);
        $button.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> Completing...');
        
        $.ajax({
            type: 'post',
            url: "{{ route('tasks.complete') }}",
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
            data: {
                id: currentTaskId, 
                unique_group_id: currentTaskGroupId,
                completion_notes: completionNotes
            },
            success: function(response) {
                // Close modal
                $('#completionNotesModal').modal('hide');
                
                // Reset button
                $button.prop('disabled', false).html('<i class="fa-solid fa-check"></i> Complete Task');
                
                // Clear stored IDs
                currentTaskId = null;
                currentTaskGroupId = null;
                
                // Reload table
                if (table) { table.draw(false); }
                if (typeof updateBadgeCounts === 'function') {
                    updateBadgeCounts();
                } else if (typeof window.refreshCrmNavPendingTaskCount === 'function') {
                    window.refreshCrmNavPendingTaskCount();
                }
                
                // Show success notification
                if (typeof iziToast !== 'undefined') {
                    iziToast.success({ title: 'Done', message: response.message || 'Task completed successfully.', position: 'topRight', timeout: 3000 });
                }
            },
            error: function(xhr) {
                console.error('Error completing task:', xhr.responseText);
                var msg = 'An error occurred while completing the task.';
                try { var r = JSON.parse(xhr.responseText); if (r.message) msg = r.message; } catch(e) {}
                if (typeof iziToast !== 'undefined') {
                    iziToast.error({ title: 'Error', message: msg, position: 'topRight', timeout: 5000 });
                } else {
                    alert(msg);
                }
                
                // Reset button
                $button.prop('disabled', false).html('<i class="fa-solid fa-check"></i> Complete Task');
            }
        });
    });

    // Add My Task submission
    $(document).on('click', '#add_my_task_submit', function() {
        $(".popuploader").show();
        var flag = true;
        var error = "";
        $(".custom-error").remove();

        var $addRoot = $(this).closest('.popover').find('.add-task-layout').first();
        if (!$addRoot.length) {
            $addRoot = $('.popover.add-my-task-popover .add-task-layout').first();
        }

        var selectedRemCat = [];
        $addRoot.find('.checkbox-item:checked').each(function() {
            selectedRemCat.push($(this).val());
        });

        if (selectedRemCat.length === 0) {
            $('.popuploader').hide();
            error = "Assignee field is required.";
            $addRoot.find('#add_task_dropdown_btn').after("<span class='custom-error' role='alert'>" + error + "</span>");
            flag = false;
        }

        if (!$addRoot.find('#add_task_assignnote').val()) {
            $('.popuploader').hide();
            error = "Note field is required.";
            $addRoot.find('#add_task_assignnote').after("<span class='custom-error' role='alert'>" + error + "</span>");
            flag = false;
        }

        if (flag) {
            $.ajax({
                type: 'post',
                url: "{{ route('clients.tasks.personal.store') }}",
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
                dataType: 'json',
                data: {
                    note_type: 'follow_up',
                    description: $addRoot.find('#add_task_assignnote').val(),
                    client_id: $addRoot.find('#add_task_client_select').val(),
                    rem_cat: selectedRemCat,
                    task_group: $addRoot.find('#add_task_task_group').val()
                },
                success: function(response) {
                    $('.popuploader').hide();
                    // Response is already parsed as JSON due to dataType: 'json'
                    if (response && response.success) {
                        $('.add_my_task').each(function() {
                            try {
                                $(this).popover('hide');
                            } catch (e) { /* ignore */ }
                        });
                        $('.popover-backdrop').removeClass('show');
                        if (table) { table.draw(false); }
                    } else {
                        alert(response && response.message ? response.message : 'An error occurred');
                        if (table) { table.draw(false); }
                    }
                },
                error: function(xhr, status, error) {
                    $('.popuploader').hide();
                    console.error('Error adding task:', error);
                    alert('Failed to add task. Please try again.');
                }
            });
        } else {
            $(".popuploader").hide();
        }
    });
});
</script>
@endpush
