@extends('layouts.crm_client_detail')
@include('components.require-tinymce')
@section('title', 'Completed Tasks')

@section('styles')
<link rel="stylesheet" href="{{ asset('css/listing-pagination.css') }}">
<link rel="stylesheet" href="{{ asset('css/listing-container.css') }}">
<link rel="stylesheet" href="{{ asset('css/listing-flatpickr.css') }}">
<style>
    /* Completed actions â€” docs/theme.md (tokens from crm-theme.css :root) */
    .listing-container .action-completed-filter-form {
        margin-bottom: 0;
    }

    .listing-container .filter-buttons {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-bottom: 0;
        max-width: 100%;
    }

    /* Wrapper <button><a>â€¦</a></button> â€” strip chrome so themed <a> shows */
    .listing-container .filter-buttons > button {
        background: transparent !important;
        border: none !important;
        padding: 0 !important;
        margin: 0;
    }

    .listing-container .filter-buttons a.group_type {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: var(--page-bg, #f0f6ff) !important;
        color: var(--navy, #1e3d60) !important;
        border: 1px solid var(--border, #c8dcef) !important;
        border-radius: 8px;
        padding: 8px 14px;
        font-size: 0.9em;
        font-weight: 600;
        text-decoration: none !important;
        transition: background-color 0.15s ease, color 0.15s ease, border-color 0.15s ease;
        white-space: nowrap;
    }

    .listing-container .filter-buttons a.group_type:hover {
        background: var(--sidebar-hover, #c8dcef) !important;
        color: var(--navy, #1e3d60) !important;
    }

    .listing-container .filter-buttons a.group_type.active {
        background: var(--navy, #1e3d60) !important;
        color: #fff !important;
        border-color: var(--navy, #1e3d60) !important;
    }

    .listing-container .filter-buttons a.group_type:not(.active) .countAction {
        background: rgba(30, 61, 96, 0.1);
        color: var(--navy, #1e3d60);
        padding: 2px 8px;
        border-radius: 999px;
        font-size: 0.8em;
        margin-left: 4px;
        font-weight: 700;
    }

    .listing-container .filter-buttons a.group_type.active .countAction {
        background: rgba(255, 255, 255, 0.22);
        color: #fff;
        padding: 2px 8px;
        border-radius: 999px;
        font-size: 0.8em;
        margin-left: 4px;
        font-weight: 700;
    }

    .listing-container .card-header .nav-pills .nav-link {
        color: var(--text-dark, #1a2c40);
        border: 1px solid var(--border, #c8dcef);
        border-radius: 8px;
        font-weight: 600;
    }

    .listing-container .card-header .nav-pills .nav-link:hover {
        background: var(--sidebar-hover, #c8dcef);
        color: var(--navy, #1e3d60);
    }

    .listing-container .card-header .nav-pills .nav-link.active {
        background: var(--navy, #1e3d60) !important;
        color: #fff !important;
        border-color: var(--navy, #1e3d60);
    }

    .listing-container .action-buttons {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
        justify-content: center;
    }

    .listing-container .action-buttons form {
        display: inline-flex;
        margin: 0;
    }

    .listing-container .action-buttons .btn:not(.btn-sm) {
        padding: 5px 10px;
        font-size: 0.9em;
        border-radius: 8px;
        white-space: nowrap;
    }

    .listing-container .btn-info {
        background: var(--navy, #1e3d60) !important;
        border-color: var(--navy, #1e3d60) !important;
        color: #fff !important;
    }

    .listing-container .btn-info:hover {
        filter: brightness(1.06);
        color: #fff !important;
    }

    body > .ts-dropdown {
        z-index: 10700 !important;
    }

    .popover .ts-wrapper {
        width: 100% !important;
        max-width: 100% !important;
    }

    .listing-container .btn-link {
        color: var(--sidebar-active, #3a6fa8) !important;
    }

    .listing-container .btn-link:hover {
        color: var(--navy, #1e3d60) !important;
    }

    .listing-container .sort_col a {
        color: var(--sidebar-active, #3a6fa8) !important;
        text-decoration: none;
        font-weight: 600;
    }

    .listing-container .sort_col a:hover {
        color: var(--navy, #1e3d60) !important;
        text-decoration: underline;
    }
    
    /* Column width specifications */
    .listing-container .table th:nth-child(1), 
    .listing-container .table td:nth-child(1) { /* Sno */
        width: 5%;
        min-width: 50px;
        max-width: 60px;
    }
    
    .listing-container .table th:nth-child(2), 
    .listing-container .table td:nth-child(2) { /* Done */
        width: 8%;
        min-width: 60px;
        max-width: 80px;
        text-align: center;
    }
    
    .listing-container .table th:nth-child(3), 
    .listing-container .table td:nth-child(3) { /* Assigner Name */
        width: 15%;
        min-width: 120px;
        max-width: 150px;
    }
    
    .listing-container .table th:nth-child(4), 
    .listing-container .table td:nth-child(4) { /* Client Reference */
        width: 15%;
        min-width: 120px;
        max-width: 150px;
    }
    
    .listing-container .table th:nth-child(5), 
    .listing-container .table td:nth-child(5) { /* Assign Date */
        width: 12%;
        min-width: 100px;
        max-width: 120px;
    }
    
    .listing-container .table th:nth-child(6), 
    .listing-container .table td:nth-child(6) { /* Type */
        width: 10%;
        min-width: 80px;
        max-width: 100px;
    }
    
    .listing-container .table th:nth-child(7), 
    .listing-container .table td:nth-child(7) { /* Note column */
        width: 25%;
        min-width: 200px;
        max-width: 300px;
        word-wrap: break-word;
        overflow-wrap: break-word;
        white-space: normal;
        line-height: 1.4;
    }
    
    .listing-container .table th:nth-child(8), 
    .listing-container .table td:nth-child(8) { /* Action column */
        width: 10%;
        min-width: 100px;
        max-width: 120px;
        white-space: nowrap;
        text-align: center;
    }
    
    /* Ensure popover content doesn't cause overflow */
    .listing-container .popover {
        max-width: 400px;
        word-wrap: break-word;
    }

    .listing-container .table tbody td[colspan] {
        color: var(--text-muted, #5e7a90) !important;
        font-style: italic;
    }

    .completed-tasks-infinite-loader {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        padding: 14px 8px;
        color: var(--navy, #1e3d60);
        font-size: 0.875rem;
        font-weight: 600;
    }

    .completed-tasks-infinite-loader[hidden] {
        display: none !important;
    }

    .completed-tasks-infinite-loader__spinner {
        width: 18px;
        height: 18px;
        border: 2px solid var(--border, #c8dcef);
        border-top-color: var(--navy, #1e3d60);
        border-radius: 50%;
        animation: completedTasksSpin 0.7s linear infinite;
    }

    @keyframes completedTasksSpin {
        to { transform: rotate(360deg); }
    }

    .completed-tasks-scroll-info {
        text-align: center;
        padding: 8px 12px 16px;
        color: var(--text-muted, #5e7a90);
        font-size: 0.8125rem;
    }

    .completed-tasks-scroll-sentinel {
        height: 1px;
        width: 100%;
    }

    #completed-tasks-spa-root.is-spa-loading #completed-tasks-spa-content {
        opacity: 0.55;
        pointer-events: none;
        transition: opacity 0.15s ease;
    }

    .completed-tasks-spa-loading {
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

    .completed-tasks-spa-loading.d-none {
        display: none !important;
    }

    .completed-tasks-header-title {
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
        min-width: 0;
    }

    .completed-tasks-header-title h4 {
        margin: 0;
    }

    .completed-tasks-back {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 7px 14px;
        border-radius: 8px;
        border: 1px solid var(--border, #c8dcef);
        background: #fff;
        color: var(--navy, #1e3d60);
        font-size: 0.875rem;
        font-weight: 600;
        text-decoration: none !important;
        white-space: nowrap;
        transition: background-color 0.15s ease, border-color 0.15s ease, color 0.15s ease;
    }

    .completed-tasks-back:hover,
    .completed-tasks-back:focus {
        background: var(--page-bg, #f0f6ff);
        border-color: var(--sidebar-active, #3a6fa8);
        color: var(--navy, #1e3d60);
    }

    /* DONE — same rounded checkbox as Tasks / Assigned by Me */
    #completed-tasks-spa-root .action-done-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 26px;
        height: 26px;
        margin: 0 auto;
        padding: 0;
        border: 1.5px solid #b7c9dc;
        border-radius: 7px;
        background: #fff;
        color: transparent;
        cursor: pointer;
        vertical-align: middle;
        box-shadow: none;
        transition: border-color 0.15s ease, background-color 0.15s ease, color 0.15s ease, box-shadow 0.15s ease;
    }

    #completed-tasks-spa-root .action-done-btn i {
        font-size: 12px;
        line-height: 1;
    }

    #completed-tasks-spa-root .action-done-btn--done {
        border-color: var(--navy, #1e3d60);
        background: var(--navy, #1e3d60);
        color: #fff;
    }

    #completed-tasks-spa-root .action-done-btn--done:hover,
    #completed-tasks-spa-root .action-done-btn--done:focus-visible {
        border-color: var(--sidebar-active, #3a6fa8);
        background: var(--sidebar-active, #3a6fa8);
        color: #fff;
        box-shadow: 0 0 0 3px rgba(58, 111, 168, 0.18);
        outline: none;
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
        .listing-container .filter-buttons {
            flex-direction: column;
        }
        
        .listing-container .filter-buttons > button {
            width: 100%;
        }

        .listing-container .filter-buttons a.group_type {
            width: 100%;
            justify-content: center;
        }
        
        .listing-container .card-header .d-flex {
            flex-direction: column;
            gap: 10px;
        }
        
        .listing-container .nav-pills {
            margin-top: 10px;
        }
        
        .listing-container .table th:nth-child(7), 
        .listing-container .table td:nth-child(7) { /* Note column on mobile */
            width: 20%;
            min-width: 150px;
            max-width: 200px;
        }
        
        .listing-container .action-buttons {
            flex-direction: column;
            gap: 3px;
        }
        
        .listing-container .action-buttons .btn {
            padding: 3px 6px;
            font-size: 0.8em;
        }
    }
    
    @media (max-width: 576px) {
        .listing-container .table th:nth-child(7), 
        .listing-container .table td:nth-child(7) { /* Note column */
            width: 15%;
            min-width: 120px;
            max-width: 150px;
        }
    }
</style>
@endsection

@section('content')
@php
    $task_group = $task_group ?? 'All';
@endphp
<div class="listing-container" id="completed-tasks-spa-root"
     data-base-url="{{ route('assignee.tasks.completed') }}"
     data-group-type="{{ $task_group }}"
     data-infinite-scroll="1">
    <section class="listing-section" style="padding-top: 80px;">
        <div class="listing-section-body">
            @include('../Elements/flash-message')
            
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div class="completed-tasks-header-title">
                            <a href="{{ route('assignee.tasks') }}" class="completed-tasks-back" title="Back to Tasks">
                                <i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back
                            </a>
                            <h4>Completed Tasks</h4>
                        </div>
                        <ul class="nav nav-pills" id="client_tabs" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link" id="incomplete-tab" href="{{ route('assignee.tasks') }}">Incomplete</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link active" id="completed-tab" href="{{ route('assignee.tasks.completed') }}">Completed</a>
                            </li>
                        </ul>
                    </div>
                </div>
                
                <div class="card-body">
                    <div id="completedTasksSpaLoading" class="completed-tasks-spa-loading d-none" aria-live="polite" aria-busy="false">
                        <span class="completed-tasks-infinite-loader__spinner" aria-hidden="true"></span>
                        <span>Updating list...</span>
                    </div>
                    <div id="completed-tasks-spa-content">
                        @include('crm.assignee.tasks.partials.completed_spa_body', [
                            'assignees_completed' => $assignees_completed,
                            'task_group' => $task_group,
                            'taskGroupCounts' => $taskGroupCounts,
                            'i' => $i ?? 0,
                            'appendOnly' => false,
                        ])
                    </div>
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
@endsection

@push('scripts')
<link rel="stylesheet" href="{{URL::to('/')}}/css/task-popover-modern.css">
<script src="{{ asset('js/crm/assignee/tasks-completed-spa.js') }}?v={{ @filemtime(public_path('js/crm/assignee/tasks-completed-spa.js')) ?: time() }}"></script>
<script>
jQuery(document).ready(function($){
    function spaReload() {
        if (window.CompletedTasksSpa && typeof window.CompletedTasksSpa.reload === 'function') {
            window.CompletedTasksSpa.reload();
        } else {
            location.reload();
        }
    }

    function escapeHtml(text) {
        if (!text) return '';
        return String(text).replace(/[&<>"']/g, function(m) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[m];
        });
    }

    function getCompletedUpdateTaskContent(assignedTo, noteText, taskId, taskGroup, followupDate, clientId) {
        assignedTo = String(assignedTo || '');
        noteText = escapeHtml(noteText || '');
        taskId = escapeHtml(taskId || '');
        taskGroup = String(taskGroup || '');
        followupDate = escapeHtml((followupDate || '').toString().split(' ')[0] || '');
        clientId = escapeHtml(clientId || '');

        var staffOptions = `
            @foreach (\App\Models\Staff::where('status', 1)->orderBy('first_name', 'ASC')->get() as $admin)
                @php $branchname = \App\Models\Branch::where('id', $admin->office_id)->first(); @endphp
                <option value="{{ $admin->id }}" ${assignedTo == '{{ $admin->id }}' ? 'selected' : ''}>
                    {{ $admin->first_name }} {{ $admin->last_name }} ({{ $branchname->office_name ?? 'N/A' }})
                </option>
            @endforeach
        `;

        return `
            <div id="popover-content" class="modern-popover-content update-task-layout">
                <div class="form-group">
                    <label class="control-label" for="rem_cat">Select Assignee</label>
                    <select class="form-control crm-ts-assignee selec_reg" id="rem_cat" name="rem_cat">
                        <option value="">Select</option>
                        ${staffOptions}
                    </select>
                </div>
                <div class="form-group">
                    <label class="control-label" for="assignnote">Note</label>
                    <textarea id="assignnote" class="form-control f13" rows="4" placeholder="Enter a note....">${noteText}</textarea>
                </div>
                <div class="form-group">
                    <label class="control-label" for="popoverdatetime">DateTime</label>
                    <input type="date" class="form-control f13" id="popoverdatetime" value="${followupDate}" name="popoverdate">
                </div>
                <div class="form-group">
                    <label class="control-label" for="task_group">Group</label>
                    <select class="form-control crm-ts-assignee selec_reg" id="task_group" name="task_group">
                        <option value="">Select</option>
                        <option value="Call" ${taskGroup == 'Call' ? 'selected' : ''}>Call</option>
                        <option value="Checklist" ${taskGroup == 'Checklist' ? 'selected' : ''}>Checklist</option>
                        <option value="Review" ${taskGroup == 'Review' ? 'selected' : ''}>Review</option>
                        <option value="Query" ${taskGroup == 'Query' ? 'selected' : ''}>Query</option>
                        <option value="Urgent" ${taskGroup == 'Urgent' ? 'selected' : ''}>Urgent</option>
                    </select>
                </div>
                <input id="assign_note_id" type="hidden" value="${taskId}">
                <input id="assign_client_id" type="hidden" value="${clientId}">
                <div class="update-task-actions">
                    <button type="button" class="btn btn-info" id="updateTask">Update Task</button>
                </div>
            </div>`;
    }

    $(document).on('click', '#completed-tasks-spa-root .completed-update-task', function(e) {
        e.preventDefault();
        e.stopPropagation();
        var $button = $(this);
        $('.update_task').popover('hide');
        $button.popover('dispose');
        $button.popover({
            html: true,
            sanitize: false,
            title: '<span class="update-task-modal-title">Update Task</span><button type="button" class="update-task-modal-close btn-close" aria-label="Close"></button>',
            content: getCompletedUpdateTaskContent(
                $button.attr('data-assignedto') || '',
                $button.attr('data-noteid') || '',
                $button.attr('data-taskid') || '',
                $button.attr('data-taskgroupid') || '',
                $button.attr('data-actiondate') || '',
                $button.attr('data-clientid') || ''
            ),
            trigger: 'manual',
            placement: 'auto',
            boundary: 'viewport',
            customClass: 'update-task-popover',
            template: '<div class="popover" role="tooltip"><div class="popover-header"></div><div class="popover-body"></div></div>',
            container: 'body'
        }).popover('show');
    });

    $(document).on('shown.bs.popover', '#completed-tasks-spa-root .completed-update-task', function() {
        var $shell = $('.popover.show').last();
        var $popover = $shell.find('.popover-body');
        if (!$popover.length) {
            $popover = $shell;
        }

        $popover.find('.crm-ts-assignee').each(function() {
            if (typeof destroyTS === 'function') destroyTS(this);
        });
        var ddParent = $shell.length ? $shell[0] : document.body;
        if (typeof initTS === 'function') {
            $popover.find('.crm-ts-assignee').each(function() {
                initTS(this, { create: false, allowEmptyOption: true, dropdownParent: ddParent });
                var ts = this.tomselect;
                if (ts && ts.wrapper) {
                    ts.wrapper.style.width = '100%';
                    ts.wrapper.style.maxWidth = '100%';
                }
            });
        }
    });

    $(document).on('hide.bs.popover', '#completed-tasks-spa-root .completed-update-task', function() {
        $('.popover .crm-ts-assignee').each(function() {
            if (typeof destroyTS === 'function') destroyTS(this);
        });
    });

    $(document).on('click', '.update-task-modal-close', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $('.update_task, .completed-update-task').popover('hide');
    });

    // Mark task as incomplete
    $(document).on('click', '#completed-tasks-spa-root .not_complete_task', function(){
        var row_id = $(this).attr('data-id');
        var row_unique_group_id = $(this).attr('data-unique_group_id');
        if(row_id != ""){
            $.ajax({
                type: 'post',
                url: "{{ route('tasks.reopen') }}",
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                data: { id: row_id, unique_group_id: row_unique_group_id },
                success: function(){
                    spaReload();
                }
            });
        }
    });

    $(document).on('click', '#completed-tasks-spa-root .deleteCompletedNote', function(e) {
        e.preventDefault();
        var url = $(this).data('remote');
        if (!url || !confirm('Are you sure want to delete?')) {
            return;
        }
        $.ajax({
            type: 'DELETE',
            url: url,
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            dataType: 'json',
            success: function(response) {
                if (response && response.success) {
                    spaReload();
                } else {
                    crmAlert('Error deleting task: ' + ((response && response.message) || 'Unknown error'));
                }
            },
            error: function() {
                crmAlert('Error deleting task. Please try again.');
            }
        });
    });

    // Reassign from completed (creates new action)
    $(document).on('click', '#updateTask', function() {
        var $root = $(this).closest('.popover-body');
        if (!$root.length) {
            $root = $('.popover.show .popover-body');
        }

        $(".popuploader").show();
        var flag = true;
        var error = "";
        $root.find(".custom-error").remove();

        if ($root.find('#rem_cat').val() == '') {
            $('.popuploader').hide();
            error = "Assignee field is required.";
            $root.find('#rem_cat').after("<span class='custom-error' role='alert'>" + error + "</span>");
            flag = false;
        }
        if ($root.find('#assignnote').val() == '') {
            $('.popuploader').hide();
            error = "Note field is required.";
            $root.find('#assignnote').after("<span class='custom-error' role='alert'>" + error + "</span>");
            flag = false;
        }
        if ($root.find('#task_group').val() == '') {
            $('.popuploader').hide();
            error = "Group field is required.";
            $root.find('#task_group').after("<span class='custom-error' role='alert'>" + error + "</span>");
            flag = false;
        }
        if (flag) {
            $.ajax({
                type: 'post',
                url: "{{ route('clients.tasks.reassign') }}",
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                data: {
                    note_type: 'follow_up',
                    description: $root.find('#assignnote').val(),
                    client_id: $root.find('#assign_client_id').val(),
                    followup_datetime: $root.find('#popoverdatetime').val(),
                    assignee_name: $root.find('#rem_cat :selected').text(),
                    rem_cat: $root.find('#rem_cat option:selected').val(),
                    task_group: $root.find('#task_group option:selected').val()
                },
                success: function(response) {
                    $('.popuploader').hide();
                    var obj = (typeof response === 'string') ? $.parseJSON(response) : response;
                    $('.update_task').popover('hide');
                    if (obj && obj.success) {
                        spaReload();
                    } else {
                        crmAlert((obj && obj.message) ? obj.message : 'Could not update task.');
                        spaReload();
                    }
                },
                error: function() {
                    $('.popuploader').hide();
                    crmAlert('An error occurred. Please try again.');
                }
            });
        } else {
            $("#loader").hide();
        }
    });
});
</script>
@endpush

