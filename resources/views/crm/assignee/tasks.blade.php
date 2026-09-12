@extends('layouts.crm_client_detail')
@section('title', 'Tasks')

@section('styles')
<link rel="stylesheet" href="{{ asset('css/listing-pagination.css') }}">
<link rel="stylesheet" href="{{ asset('css/listing-container.css') }}">
<link rel="stylesheet" href="{{ asset('css/task-list.css') }}?v={{ @filemtime(public_path('css/task-list.css')) ?: time() }}">
<style>
    #open-tasks-spa-root.is-spa-loading #open-tasks-spa-content {
        opacity: 0.55;
        pointer-events: none;
        transition: opacity 0.15s ease;
    }
    .open-tasks-spa-loading {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        padding: 10px 12px;
        margin-bottom: 8px;
        color: var(--navy, #1e3d60);
        font-size: 0.875rem;
        font-weight: 600;
    }
    .open-tasks-spa-loading.d-none { display: none !important; }
</style>
@endsection

@section('content')
@php
    $filter = $filter ?? 'all';
    $search = $search ?? '';
    $assignees = $assignees ?? collect();
    $taskGroupCounts = $taskGroupCounts ?? [];
    $i = $i ?? 0;
@endphp
<div class="listing-container assignee-action-page" id="open-tasks-spa-root"
     data-base-url="{{ route('assignee.tasks') }}"
     data-filter="{{ $filter }}"
     data-q="{{ $search }}"
     data-infinite-scroll="1">
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
                        @include('components.add-task-form', [
                            'staffMembers' => \App\Models\Staff::where('status', 1)->orderby('first_name', 'ASC')->get(),
                        ])
                    </template>
                    {{-- Do not use data-role="popover": legacy public/js/popover.js conflicts with BS5. --}}
                            <button type="button" class="btn btn-primary add_my_task add-my-task-header-btn" data-bs-toggle="popover" data-container="body" data-placement="bottom-start" data-html="true">
                                <i class="fa-solid fa-plus"></i> Add My Task
                            </button>
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    <div id="openTasksSpaLoading" class="open-tasks-spa-loading d-none" aria-live="polite" aria-busy="false">
                        <span class="action-infinite-loader__spinner" aria-hidden="true"></span>
                        <span>Updating list...</span>
                    </div>
                    <div id="open-tasks-spa-content">
                        @include('crm.assignee.tasks.partials.open_spa_body', [
                            'assignees' => $assignees,
                            'filter' => $filter,
                            'search' => $search,
                            'taskGroupCounts' => $taskGroupCounts,
                            'i' => $i,
                            'appendOnly' => false,
                        ])
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<!-- Task Completion Notes Modal -->
<div class="modal fade" id="completionNotesModal" tabindex="-1" role="dialog" aria-labelledby="completionNotesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content completion-notes-modal-content">
            <div class="modal-header completion-notes-modal-header">
                <h5 class="modal-title" id="completionNotesModalLabel">
                    <i class="fa-solid fa-check completion-task-modal-header-icon" aria-hidden="true"></i> Complete Task
                </h5>
                <x-crm.modal-close />
            </div>
            <div class="modal-body completion-notes-modal-body">
                <div class="form-group mb-0">
                    <label for="completionNotes" class="completion-notes-label">
                        <i class="fa-solid fa-comment"></i> Completion Notes/Feedback
                    </label>
                    <textarea 
                        class="form-control completion-notes-textarea" 
                        id="completionNotes" 
                        rows="4" 
                        placeholder="Enter any notes or feedback about completing this task..."
                    ></textarea>
                    <p class="completion-notes-hint mb-0">
                        <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                        These notes will be saved in the activity log.
                    </p>
                </div>
            </div>
            <div class="modal-footer completion-notes-modal-footer">
                <button type="button" class="btn btn-cancel-complete" data-bs-dismiss="modal">
                    Cancel
                </button>
                <button type="button" class="btn btn-complete-task-primary" id="confirmTaskCompletion">
                    <i class="fa-solid fa-check" aria-hidden="true"></i> Complete Task
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

    /* Add New Task — same navy gradient header as Add matter */
    .popover.add-my-task-popover .popover-header {
        display: flex !important;
        align-items: center !important;
        justify-content: space-between !important;
        gap: 12px !important;
        background: linear-gradient(135deg, var(--navy, #1e3d60) 0%, var(--sidebar-active, #3a6fa8) 100%) !important;
        background-image: linear-gradient(135deg, var(--navy, #1e3d60) 0%, var(--sidebar-active, #3a6fa8) 100%) !important;
        color: #fff !important;
        border-bottom: 3px solid var(--accent-gold, #c8992a) !important;
        padding: 16px 20px !important;
        border-radius: 14px 14px 0 0 !important;
        font-weight: 700 !important;
        font-size: 1.125rem !important;
        letter-spacing: -0.01em !important;
    }

    .popover.add-my-task-popover .add-task-modal-title,
    .popover.add-my-task-popover .add-task-modal-title i {
        color: #fff !important;
        -webkit-text-fill-color: #fff !important;
    }

    .popover.add-my-task-popover .add-task-modal-close {
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        flex-shrink: 0 !important;
        width: 36px !important;
        height: 36px !important;
        min-width: 36px !important;
        margin: 0 0 0 auto !important;
        padding: 0 !important;
        border-radius: 8px !important;
        border: 1px solid rgba(255, 255, 255, 0.4) !important;
        background: rgba(255, 255, 255, 0.14) !important;
        background-image: none !important;
        opacity: 1 !important;
        box-shadow: none !important;
        filter: none !important;
        font-size: 0 !important;
        line-height: 0 !important;
        overflow: hidden !important;
        cursor: pointer !important;
    }

    .popover.add-my-task-popover .add-task-modal-close::before {
        content: "" !important;
        display: block !important;
        width: 14px !important;
        height: 14px !important;
        background-color: #fff !important;
        -webkit-mask: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Cpath fill='black' d='M.293.293a1 1 0 0 1 1.414 0L8 6.586 14.293.293a1 1 0 1 1 1.414 1.414L9.414 8l6.293 6.293a1 1 0 0 1-1.414 1.414L8 9.414l-6.293 6.293a1 1 0 0 1-1.414-1.414L6.586 8 .293 1.707a1 1 0 0 1 0-1.414'/%3E%3C/svg%3E") center / contain no-repeat !important;
        mask: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Cpath fill='black' d='M.293.293a1 1 0 0 1 1.414 0L8 6.586 14.293.293a1 1 0 1 1 1.414 1.414L9.414 8l6.293 6.293a1 1 0 0 1-1.414 1.414L8 9.414l-6.293 6.293a1 1 0 0 1-1.414-1.414L6.586 8 .293 1.707a1 1 0 0 1 0-1.414'/%3E%3C/svg%3E") center / contain no-repeat !important;
    }

    .popover.add-my-task-popover .add-task-modal-close:hover,
    .popover.add-my-task-popover .add-task-modal-close:focus {
        opacity: 1 !important;
        background: rgba(255, 255, 255, 0.22) !important;
        background-image: none !important;
        border-color: rgba(255, 255, 255, 0.55) !important;
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
    display: flex !important;
    align-items: center !important;
    justify-content: space-between !important;
    gap: 12px !important;
    background: linear-gradient(135deg, var(--navy, #1e3d60) 0%, var(--sidebar-active, #3a6fa8) 100%) !important;
    background-image: linear-gradient(135deg, var(--navy, #1e3d60) 0%, var(--sidebar-active, #3a6fa8) 100%) !important;
    color: #fff !important;
    border-bottom: 3px solid var(--accent-gold, #c8992a) !important;
    font-size: 1.125rem !important;
    font-weight: 700 !important;
    padding: 16px 20px !important;
    border-radius: 12px 12px 0 0 !important;
    letter-spacing: -0.01em !important;
}

.popover.update-task-popover .update-task-modal-title {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: #fff !important;
    -webkit-text-fill-color: #fff !important;
    font-weight: 700 !important;
    flex: 1;
    min-width: 0;
}

.popover.update-task-popover .update-task-modal-close {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    flex-shrink: 0 !important;
    width: 36px !important;
    height: 36px !important;
    min-width: 36px !important;
    margin: 0 0 0 auto !important;
    padding: 0 !important;
    border-radius: 8px !important;
    border: 1px solid rgba(255, 255, 255, 0.4) !important;
    background: rgba(255, 255, 255, 0.14) !important;
    background-image: none !important;
    opacity: 1 !important;
    box-shadow: none !important;
    filter: none !important;
    font-size: 0 !important;
    line-height: 0 !important;
    overflow: hidden !important;
    cursor: pointer !important;
}

.popover.update-task-popover .update-task-modal-close::before {
    content: "" !important;
    display: block !important;
    width: 14px !important;
    height: 14px !important;
    background-color: #fff !important;
    -webkit-mask: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Cpath fill='black' d='M.293.293a1 1 0 0 1 1.414 0L8 6.586 14.293.293a1 1 0 1 1 1.414 1.414L9.414 8l6.293 6.293a1 1 0 0 1-1.414 1.414L8 9.414l-6.293 6.293a1 1 0 0 1-1.414-1.414L6.586 8 .293 1.707a1 1 0 0 1 0-1.414'/%3E%3C/svg%3E") center / contain no-repeat !important;
    mask: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Cpath fill='black' d='M.293.293a1 1 0 0 1 1.414 0L8 6.586 14.293.293a1 1 0 1 1 1.414 1.414L9.414 8l6.293 6.293a1 1 0 0 1-1.414 1.414L8 9.414l-6.293 6.293a1 1 0 0 1-1.414-1.414L6.586 8 .293 1.707a1 1 0 0 1 0-1.414'/%3E%3C/svg%3E") center / contain no-repeat !important;
}

.popover.update-task-popover .update-task-modal-close:hover,
.popover.update-task-popover .update-task-modal-close:focus {
    opacity: 1 !important;
    background: rgba(255, 255, 255, 0.22) !important;
    background-image: none !important;
    border-color: rgba(255, 255, 255, 0.55) !important;
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

.popover .modern-popover-content > .text-center,
.popover .modern-popover-content > .add-task-modal-footer {
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
<script src="{{ asset('js/crm/assignee/tasks-spa.js') }}?v={{ @filemtime(public_path('js/crm/assignee/tasks-spa.js')) ?: time() }}"></script>
<script type="text/javascript">
$(function () {
    function spaReload() {
        if (window.OpenTasksSpa && typeof window.OpenTasksSpa.reload === 'function') {
            window.OpenTasksSpa.reload();
        } else {
            location.reload();
        }
    }

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
            title: '<span class="add-task-modal-title"><i class="fa-solid fa-circle-plus"></i> Add New Task</span><button type="button" class="add-task-modal-close btn-close" aria-label="Close"></button>',
            template: '<div class="popover" role="tooltip"><div class="popover-arrow"></div><h3 class="popover-header"></h3><div class="popover-body"></div></div>'
        };
        if (actionAddTaskHtml) {
            popoverOpts.content = actionAddTaskHtml;
        }
        $(this).popover(popoverOpts);
    });

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
                        placeholder: 'Search client or lead...'
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

    $(document).on('click', '.update-task-modal-close', function(e) {
        e.preventDefault();
        e.stopPropagation();
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

    // Update badge counts (fallback; SPA responses also refresh counts)
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
                    Object.keys(data).forEach(function(key) {
                        if (key === 'unauthenticated') {
                            return;
                        }
                        $('#open-tasks-spa-root [data-count-key="' + key + '"]').text(data[key] || 0);
                    });
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

    // Handle Update Task button click
    $(document).on('click', '#open-tasks-spa-root .update_task', function() {
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
            title: '<span class="update-task-modal-title">Update Task</span><button type="button" class="update-task-modal-close btn-close" aria-label="Close"></button>',
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
                spaReload();
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
                    crmAlert(msg);
                }
            }
        });
    });

    // Delete record
    $(document).on('click', '#open-tasks-spa-root .deleteNote', function(e) {
        e.preventDefault();
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });

        var url = $(this).data('remote');
        
        if (!url) {
            console.error('No delete URL found');
            crmAlert('Unable to delete: missing URL');
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
                spaReload();
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
                    crmAlert(msg);
                }
            });
        }
    });

    // Complete task - open modal
    var currentTaskId = null;
    var currentTaskGroupId = null;
    
    $('.assignee-action-page').on('click', '.complete_task', function() {
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
                
                // Reload list
                spaReload();
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
                    crmAlert(msg);
                }
                
                // Reset button
                $button.prop('disabled', false).html('<i class="fa-solid fa-check"></i> Complete Task');
            }
        });
    });

    // Add My Task / Reminder — kind toggle inside popover
    function syncAddTaskKindUI($root, kind) {
        kind = kind === 'reminder' ? 'reminder' : 'task';
        $root.toggleClass('is-reminder-mode', kind === 'reminder');
        $root.find('.add-task-kind-input').val(kind);
        $root.find('.add-task-kind-btn').each(function () {
            var isActive = String($(this).data('add-task-kind')) === kind;
            $(this).toggleClass('is-active', isActive).attr('aria-pressed', isActive ? 'true' : 'false');
        });
        var $remindGroup = $root.find('.add-task-remind-on-group');
        if ($remindGroup.length) {
            $remindGroup.prop('hidden', kind !== 'reminder');
        }
        var $hint = $root.find('.add-task-kind-hint');
        if ($hint.length) {
            $hint.text(
                kind === 'reminder'
                    ? ($hint.attr('data-hint-reminder') || '')
                    : ($hint.attr('data-hint-task') || '')
            );
        }
        $root.find('.add-task-client-label-text').text(
            kind === 'reminder' ? 'Client / Lead' : 'Client / Lead (optional)'
        );
        $root.find('.add-task-note-label-text').text(kind === 'reminder' ? 'Reminder' : 'Task Description');
        var $note = $root.find('#add_task_assignnote, #assignnote').first();
        if ($note.length) {
            $note.attr(
                'placeholder',
                kind === 'reminder'
                    ? 'What should we remind you about?'
                    : 'Enter task description... (type @ to tag staff)'
            );
            $note.attr('rows', kind === 'reminder' ? 3 : 4);
        }
        var $submitLabel = $root.find('.add-task-submit-label');
        if ($submitLabel.length) {
            $submitLabel.text(kind === 'reminder' ? 'Add Reminder' : 'Add My Task');
        }
        var $submitBtn = $root.find('.add-task-submit-btn, #add_my_task_submit, #add_my_task').first();
        if ($submitBtn.length) {
            var $icon = $submitBtn.find('i').first();
            if ($icon.length) {
                $icon.attr('class', kind === 'reminder' ? 'fa-solid fa-bell' : 'fa-solid fa-circle-plus');
            }
        }
        var $title = $('.popover.add-my-task-popover .add-task-modal-title').first();
        if ($title.length) {
            $title.html(
                kind === 'reminder'
                    ? '<i class="fa-solid fa-bell"></i> Add Reminder'
                    : '<i class="fa-solid fa-circle-plus"></i> Add New Task'
            );
        }
        $root.find('.custom-error').remove();
        $root.find('.error-message').text('');
    }

    function resolveAddTaskClientId($root) {
        var $sel = $root.find('#add_task_client_select, #assign_client_id').first();
        if (!$sel.length) {
            return '';
        }
        var val = $sel.val();
        var el = $sel[0];
        if (el && el.tomselect && val) {
            var opt = el.tomselect.options[val];
            if (opt && opt.cid != null && String(opt.cid) !== '') {
                return String(opt.cid);
            }
        }
        return val ? String(val) : '';
    }

    $(document).on('click', '.add-task-kind-btn', function (e) {
        e.preventDefault();
        e.stopPropagation();
        var $root = $(this).closest('.add-task-layout');
        if (!$root.length) {
            return;
        }
        syncAddTaskKindUI($root, String($(this).data('add-task-kind') || 'task'));
    });

    $(document).on('shown.bs.popover', '.add_my_task', function () {
        var $root = $('.popover.add-my-task-popover .add-task-layout').first();
        if ($root.length) {
            syncAddTaskKindUI($root, 'task');
        }
    });

    // Add My Task submission
    $(document).on('click', '#add_my_task_submit', function() {
        $(".popuploader").show();
        var flag = true;
        var error = "";
        $(".custom-error").remove();
        $('.error-message').text('');

        var $addRoot = $(this).closest('.popover').find('.add-task-layout').first();
        if (!$addRoot.length) {
            $addRoot = $('.popover.add-my-task-popover .add-task-layout').first();
        }

        var kind = String($addRoot.find('.add-task-kind-input').val() || 'task').toLowerCase();
        if (kind === 'reminder') {
            var clientId = resolveAddTaskClientId($addRoot);
            var title = String($addRoot.find('#add_task_assignnote').val() || '').trim();
            var dueDate = String($addRoot.find('#add_task_remind_on').val() || '').trim();

            if (!clientId) {
                $('.popuploader').hide();
                $addRoot.find('#add_task_client_error').text('Select a client or lead.');
                flag = false;
            }
            if (!title) {
                $('.popuploader').hide();
                $addRoot.find('#add_task_note_error').text('Reminder text is required.');
                flag = false;
            }
            if (!dueDate) {
                $('.popuploader').hide();
                $addRoot.find('#add_task_remind_on_error').text('Choose a reminder date.');
                flag = false;
            }

            if (!flag) {
                $(".popuploader").hide();
                return;
            }

            $.ajax({
                type: 'post',
                url: "{{ route('clients.matterTask.store') }}",
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
                dataType: 'json',
                data: {
                    client_id: clientId,
                    title: title,
                    due_date: dueDate,
                    kind: 'reminder',
                    _token: $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    $('.popuploader').hide();
                    if (response && response.status) {
                        $('.add_my_task').each(function() {
                            try {
                                $(this).popover('hide');
                            } catch (e) { /* ignore */ }
                        });
                        $('.popover-backdrop').removeClass('show');
                        if (typeof iziToast !== 'undefined') {
                            iziToast.success({
                                title: 'Reminder added',
                                message: 'Saved to your personal calendar.',
                                position: 'topRight'
                            });
                        } else {
                            crmAlert('Reminder added to your personal calendar.');
                        }
                        spaReload();
                    } else {
                        crmAlert(response && response.message ? response.message : 'Could not add reminder.');
                    }
                },
                error: function(xhr) {
                    $('.popuploader').hide();
                    var msg = 'Failed to add reminder. Please try again.';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        msg = xhr.responseJSON.message;
                    }
                    crmAlert(msg);
                }
            });
            return;
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
                        spaReload();
                    } else {
                        crmAlert(response && response.message ? response.message : 'An error occurred');
                        spaReload();
                    }
                },
                error: function(xhr, status, error) {
                    $('.popuploader').hide();
                    console.error('Error adding task:', error);
                    crmAlert('Failed to add task. Please try again.');
                }
            });
        } else {
            $(".popuploader").hide();
        }
    });
});
</script>
@endpush
