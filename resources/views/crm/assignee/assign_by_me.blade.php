@extends('layouts.crm_client_detail')
@section('title', 'Assigned by Me')

@section('styles')
<link rel="stylesheet" href="{{ asset('css/listing-pagination.css') }}">
<link rel="stylesheet" href="{{ asset('css/listing-container.css') }}">
<link rel="stylesheet" href="{{ asset('css/listing-datepicker.css') }}">
<style>
    /* Assigned by me — docs/theme.md (tokens from crm-theme.css :root; shared listing-*.css for table/cards) */
    .listing-container .client-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
        padding-bottom: 20px;
        border-bottom: 1px solid var(--border, #c8dcef);
        flex-wrap: wrap;
        gap: 15px;
    }

    .listing-container .client-header h1,
    .listing-container .client-header h4 {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--navy, #1e3d60) !important;
        margin: 0;
        word-wrap: break-word;
    }

    .listing-container .client-status {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }

    .listing-container .nav-pills .nav-item .nav-link {
        margin-left: 8px;
    }

    .listing-container .nav-pills .status-badge.nav-link {
        color: var(--text-dark, #1a2c40);
        border: 1px solid var(--border, #c8dcef);
        border-radius: 8px;
        font-weight: 600;
    }

    .listing-container .nav-pills .status-badge.nav-link:hover {
        background: var(--sidebar-hover, #c8dcef);
        color: var(--navy, #1e3d60);
    }

    .listing-container .nav-pills .status-badge.nav-link.active {
        background: var(--navy, #1e3d60) !important;
        color: #fff !important;
        border-color: var(--navy, #1e3d60);
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

    .listing-container .countAction {
        background: var(--navy, #1e3d60);
        padding: 2px 8px;
        border-radius: 999px;
        color: #fff;
        font-size: 0.8em;
        margin-left: 5px;
    }

    .listing-container .complete_task {
        cursor: pointer;
    }

    .listing-container .btn-sm {
        padding: 5px 10px;
        font-size: 0.85em;
    }

    /* Update-task popover: theme tokens (popover mounts under body; outside .listing-container) */
    .popover .popover-body h4 {
        color: var(--navy, #1e3d60);
        font-weight: 700;
        font-size: 1.1rem;
        margin-bottom: 1rem;
    }

    .popover .popover-body .btn-info {
        background: var(--navy, #1e3d60) !important;
        border-color: var(--navy, #1e3d60) !important;
        color: #fff !important;
    }

    .popover .popover-body .btn-info:hover {
        background: var(--sidebar-active, #3a6fa8) !important;
        border-color: var(--sidebar-active, #3a6fa8) !important;
        color: #fff !important;
    }

    body > .select2-container--open {
        z-index: 10700 !important;
    }

    .listing-container .btn-link {
        color: var(--sidebar-active, #3a6fa8) !important;
    }

    .listing-container .btn-link:hover {
        color: var(--navy, #1e3d60) !important;
    }

    .listing-container .btn-info {
        background: var(--sidebar-active, #3a6fa8) !important;
        border-color: var(--sidebar-active, #3a6fa8) !important;
        color: #fff !important;
    }

    .listing-container .btn-info:hover {
        filter: brightness(1.06);
        color: #fff !important;
    }

    /* Assign / task modals render outside .listing-container */
    #openassigneview .modal-content,
    .custom_modal .modal-content {
        border-radius: 10px;
        border: 1px solid var(--border, #c8dcef);
        box-shadow: 0 1px 4px rgba(30, 61, 96, 0.08);
    }

    #openassigneview .modal-header,
    .custom_modal .modal-header {
        background: var(--page-bg, #f0f6ff) !important;
        border-bottom: 1px solid var(--border, #c8dcef) !important;
        color: var(--navy, #1e3d60) !important;
    }

    #openassigneview .modal-body,
    .custom_modal .modal-body {
        padding: 20px;
    }

    .listing-container .select2-container {
        z-index: 100000;
        width: 100% !important;
    }

    @media (max-width: 768px) {
        .listing-container .table th,
        .listing-container .table td {
            font-size: 0.85em;
            padding: 8px;
        }

        .listing-container .btn-sm {
            padding: 4px 8px;
        }

        .listing-container .table .btn.btn-sm.btn-primary,
        .listing-container .table .btn.btn-sm.btn-danger {
            padding: 0 !important;
        }
    }
</style>
@endsection

@section('content')
<div class="listing-container">
    <section class="listing-section" style="padding-top: 80px;">
        <div class="listing-section-body">
            @include('../Elements/flash-message')
            
            <div class="client-header">
                <h4>Assigned by Me</h4>
                <div class="client-status">
                    <ul class="nav nav-pills" id="client_tabs" role="tablist">
                        <li class="nav-item">
                            <a class="status-badge nav-link active" href="{{ URL::to('/action') }}">Incomplete</a>
                        </li>
                        <li class="nav-item">
                            <a class="status-badge nav-link" href="{{ URL::to('/action_completed') }}">Completed</a>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <form action="{{ route('assignee.assigned_by_me') }}" method="get" class="mb-4">
                        <div class="row">
                            <div class="col-md-12 group_type_section">
                                <!-- Add filters if needed -->
                            </div>
                        </div>
                    </form>

                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="active_quotation" role="tabpanel">
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th width="5%" style="text-align: center;">Sno</th>
                                            <th width="5%" style="text-align: center;">Done</th>
                                            <th width="15%">Assignee Name</th>
                                            <th width="15%">Client Reference</th>
                                            <th width="15%" class="sort_col">@sortablelink('action_date', 'Assign Date')</th>
                                            <th width="10%" class="sort_col">@sortablelink('task_group', 'Type')</th>
                                            <th>Note</th>
                                            <th width="15%">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @if (count($assignees_notCompleted) > 0)
                                            @foreach ($assignees_notCompleted as $list)
                                                @php
                                                    $admin = \App\Models\Staff::where('id', $list->assigned_to)->first();
                                                    $full_name = $admin ? ($admin->first_name ?? 'N/A') . ' ' . ($admin->last_name ?? 'N/A') : 'N/P';
                                                    $client_name = $list->noteClient ? trim($list->noteClient->company_name_or_personal_name) : 'N/P';
                                                    if ($list->noteClient && $client_name === '') {
                                                        $client_name = trim($list->noteClient->first_name . ' ' . $list->noteClient->last_name) ?: 'N/P';
                                                    }
                                                @endphp
                                                <tr>
                                                    <td style="text-align: center;">{{ ++$i }}</td>
                                                    <td style="text-align: center;">
                                                        <input type="radio" class="complete_task" data-bs-toggle="tooltip" title="Mark Complete!" data-id="{{ $list->id }}" data-unique_group_id="{{ $list->unique_group_id }}">
                                                    </td>
                                                    <td>{{ $full_name }}</td>
                                                    <td>
                                                        {{ $client_name }}
                                                        <br>
                                                        @if ($list->noteClient)
                                                            <a href="{{ URL::to('/clients/detail/' . base64_encode(convert_uuencode($list->client_id))) }}" target="_blank">{{ $list->noteClient->client_id }}</a>
                                                        @endif
                                                    </td>
                                                    <td>{{ $list->action_date ? date('d/m/Y', strtotime($list->action_date)) : 'N/P' }}</td>
                                                    <td>{{ $list->task_group ?? 'N/P' }}</td>
                                                    <td>
                                                        @if (isset($list->description) && $list->description != "")
                                                            @if (strlen($list->description) > 190)
                                                                {!! substr($list->description, 0, 190) !!}
                                                                <button type="button" class="btn btn-link" data-bs-toggle="popover" title="" data-content="{{ $list->description }}">Read more</button>
                                                            @else
                                                                {!! $list->description !!}
                                                            @endif
                                                        @else
                                                            N/P
                                                        @endif
                                                    </td>
                                                    <td>
                                                        @if ($list->task_group != 'Personal Action')
                                                            <button type="button" data-noteid="{{ $list->description }}" data-taskid="{{ $list->id }}" data-taskgroupid="{{ $list->task_group }}" data-actiondate="{{ $list->action_date }}" class="btn btn-primary btn-sm update_task" data-bs-toggle="tooltip" title="Update Task" data-bs-container="body" data-role="popover" data-bs-placement="bottom" data-bs-html="true" data-bs-content='
                                                                <div id="popover-content">
                                                                    <h4 class="text-center">Update Task</h4>
                                                                    <div class="form-group row" style="margin-bottom:12px">
                                                                        <label for="rem_cat" class="col-sm-3 control-label c6 f13" style="margin-top:8px">Select Assignee</label>
                                                                        <div class="col-sm-9">
                                                                            <select class="assigneeselect2 form-control selec_reg" id="rem_cat" name="rem_cat">
                                                                                <option value="">Select</option>
                                                                                @foreach (\App\Models\Staff::where('status', 1)->orderBy('first_name', 'ASC')->get() as $admin)
                                                                                    @php
                                                                                        $branchname = \App\Models\Branch::where('id', $admin->office_id)->first();
                                                                                    @endphp
                                                                                    <option value="{{ $admin->id }}" {{ $admin->id == $list->assigned_to ? 'selected' : '' }}>{{ $admin->first_name . ' ' . $admin->last_name . ' (' . ($branchname->office_name ?? 'N/A') . ')' }}</option>
                                                                                @endforeach
                                                                            </select>
                                                                        </div>
                                                                    </div>
                                                                    <div class="form-group row" style="margin-bottom:12px">
                                                                        <label for="assignnote" class="col-sm-3 control-label c6 f13" style="margin-top:8px">Note</label>
                                                                        <div class="col-sm-9">
                                                                            <textarea id="assignnote" class="form-control summernote-simple f13" placeholder="Enter a note..."></textarea>
                                                                        </div>
                                                                    </div>
                                                                    <div class="form-group row" style="margin-bottom:12px">
                                                                        <label for="popoverdatetime" class="col-sm-3 control-label c6 f13" style="margin-top:8px">Date</label>
                                                                        <div class="col-sm-9">
                                                                            <input type="date" class="form-control f13" placeholder="yyyy-mm-dd" id="popoverdatetime" value="{{ $list->action_date ? date('Y-m-d', strtotime($list->action_date)) : date('Y-m-d') }}" name="popoverdate">
                                                                        </div>
                                                                    </div>
                                                                    <div class="form-group row" style="margin-bottom:12px">
                                                                        <label for="task_group" class="col-sm-3 control-label c6 f13" style="margin-top:8px">Group</label>
                                                                        <div class="col-sm-9">
                                                                            <select class="assigneeselect2 form-control selec_reg" id="task_group" name="task_group">
                                                                                <option value="">Select</option>
                                                                                <option value="Call">Call</option>
                                                                                <option value="Checklist">Checklist</option>
                                                                                <option value="Review">Review</option>
                                                                                <option value="Query">Query</option>
                                                                                <option value="Urgent">Urgent</option>
                                                                            </select>
                                                                        </div>
                                                                    </div>
                                                                    <input id="assign_note_id" type="hidden" value="">
                                                                    <input id="assign_client_id" type="hidden" value="{{ base64_encode(convert_uuencode($list->client_id)) }}">
                                                                    <div class="text-center">
                                                                        <button class="btn btn-info" id="updateTask">Update Task</button>
                                                                    </div>
                                                                </div>'>
                                                                <i class="fa fa-edit" aria-hidden="true"></i>
                                                            </button>
                                                            <button class="btn btn-danger btn-sm deleteNote" data-remote="/destroy_activity/{{ $list->id }}" data-bs-toggle="tooltip" title="Delete Task">
                                                                <i class="fa fa-trash" aria-hidden="true"></i>
                                                            </button>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        @else
                                            <tr>
                                                <td colspan="8" style="text-align: center; padding: 20px;">
                                                    No actions assigned by me.
                                                </td>
                                            </tr>
                                        @endif
                                    </tbody>
                                </table>
                                
                                <!-- Pagination -->
                                <div class="card-footer">
                                    {!! $assignees_notCompleted->appends($_GET)->links() !!}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<!-- Assign Modal -->
<div class="modal fade custom_modal" id="openassigneview" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content taskview">
            <!-- Modal content will be loaded dynamically -->
        </div>
    </div>
</div>

<!-- Task Completion Notes Modal — markup + tokens match action page (public/css/crm-theme.css) -->
<div class="modal fade" id="completionNotesModal" tabindex="-1" role="dialog" aria-labelledby="completionNotesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content completion-notes-modal-content">
            <div class="modal-header completion-notes-modal-header">
                <h5 class="modal-title" id="completionNotesModalLabel">
                    <i class="fa fa-check completion-task-modal-header-icon" aria-hidden="true"></i> Complete Task
                </h5>
                <button type="button" class="close completion-notes-modal-close" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body completion-notes-modal-body">
                <div class="form-group mb-0">
                    <label for="completionNotes" class="completion-notes-label">
                        <i class="fa fa-comment"></i> Completion Notes/Feedback
                    </label>
                    <textarea
                        class="form-control completion-notes-textarea"
                        id="completionNotes"
                        rows="5"
                        placeholder="Enter any notes or feedback about completing this task..."
                    ></textarea>
                    <small class="form-text completion-notes-hint">
                        <i class="fa fa-info-circle"></i> These notes will be saved in the activity log.
                    </small>
                </div>
            </div>
            <div class="modal-footer completion-notes-modal-footer">
                <button type="button" class="btn btn-cancel-complete" data-bs-dismiss="modal">
                    <i class="fa fa-times"></i> Cancel
                </button>
                <button type="button" class="btn btn-complete-task-primary" id="confirmTaskCompletion">
                    <i class="fa fa-check"></i> Complete Task
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<link rel="stylesheet" href="{{URL::to('/')}}/css/task-popover-modern.css">
<script src="{{ URL::to('/') }}/js/popover.js"></script>
<script>
    jQuery(document).ready(function($) {
        // Open assignee modal
        $(document).on('click', '.listing-container .openassignee', function() {
            $('.assignee').show();
        });

        $(document).on('click', '.listing-container .closeassignee', function() {
            $('.assignee').hide();
        });

        // Reassign task
        $(document).on('click', '.listing-container .reassign_task', function() {
            var note_id = $(this).attr('data-noteid');
            $('#assignnote').val(note_id);
            var task_id = $(this).attr('data-taskid');
            $('#assign_note_id').val(task_id);
        });

        // Update task — populate fields + Select2 (content in DOM; dropdown parent must be popover, not hidden modal)
        $(document).on('shown.bs.popover', '.listing-container .update_task', function() {
            var $trigger = $(this);
            var $shell = $('.popover.show').last();
            var $popover = $shell.find('.popover-body');
            if (!$popover.length) {
                $popover = $shell;
            }

            $popover.find('#assignnote').val($trigger.attr('data-noteid') || '');
            $popover.find('#assign_note_id').val($trigger.attr('data-taskid') || '');
            var taskgroup_id = $trigger.attr('data-taskgroupid');
            $popover.find('#task_group').val(taskgroup_id || '').trigger('change');
            var followupdate_id = $trigger.attr('data-actiondate');
            if (followupdate_id) {
                $popover.find('#popoverdatetime').val(followupdate_id.split(' ')[0]);
            }

            $popover.find('.assigneeselect2').each(function() {
                var $sel = $(this);
                if ($sel.hasClass('select2-hidden-accessible')) {
                    try {
                        $sel.select2('destroy');
                    } catch (e) { /* ignore */ }
                }
            });
            if (typeof $.fn.select2 === 'function') {
                $popover.find('.assigneeselect2').select2({
                    width: '100%',
                    dropdownParent: $shell.length ? $shell : $(document.body)
                });
            }
        });

        $(document).on('hide.bs.popover', '.listing-container .update_task', function() {
            $('.popover .assigneeselect2').each(function() {
                var $sel = $(this);
                if ($sel.hasClass('select2-hidden-accessible')) {
                    try {
                        $sel.select2('destroy');
                    } catch (e) { /* ignore */ }
                }
            });
        });

        // Mark task as not complete
        $(document).on('click', '.listing-container .not_complete_task', function() {
            var row_id = $(this).attr('data-id');
            var row_unique_group_id = $(this).attr('data-unique_group_id');
            if (row_id != "") {
                $.ajax({
                    type: 'post',
                    url: "{{ URL::to('/') }}/update-action-not-completed",
                    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                    data: { id: row_id, unique_group_id: row_unique_group_id },
                    success: function(response) {
                        location.reload();
                    }
                });
            }
        });

        // Mark task as complete - open modal
        var currentTaskId = null;
        var currentTaskGroupId = null;
        
        $(document).on('click', '.listing-container .complete_task', function() {
            var row_id = $(this).attr('data-id');
            var row_unique_group_id = $(this).attr('data-unique_group_id');
            
            if (row_id != "") {
                // Store task IDs for later use
                currentTaskId = row_id;
                currentTaskGroupId = row_unique_group_id;
                
                // Clear previous notes
                $('#completionNotes').val('');
                
                // Show the completion notes modal
                $('#completionNotesModal').modal('show');
            }
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
            $button.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Completing...');
            
            $.ajax({
                type: 'post',
                url: "{{ URL::to('/') }}/update-action-completed",
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                data: {
                    id: currentTaskId, 
                    unique_group_id: currentTaskGroupId,
                    completion_notes: completionNotes
                },
                success: function(response) {
                    // Close modal
                    $('#completionNotesModal').modal('hide');
                    
                    // Reset button
                    $button.prop('disabled', false).html('<i class="fa fa-check"></i> Complete Task');
                    
                    // Clear stored IDs
                    currentTaskId = null;
                    currentTaskGroupId = null;
                    
                    // Reload page
                    location.reload();
                },
                error: function(xhr) {
                    console.error('Error completing task:', xhr.responseText);
                    alert('An error occurred while completing the task.');
                    
                    // Reset button
                    $button.prop('disabled', false).html('<i class="fa fa-check"></i> Complete Task');
                }
            });
        });

        // Update task (scope to visible popover — IDs repeat per row in markup)
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
                    url: "{{ URL::to('/') }}/clients/action/update",
                    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                    data: {
                        note_id: $root.find('#assign_note_id').val(),
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
                        // Parse response if it's a string (fallback for older jQuery versions)
                        var obj = (typeof response === 'string') ? $.parseJSON(response) : response;
                        if (obj.success) {
                            $("[data-role=popover]").each(function() {
                                (($(this).popover('hide').data('bs.popover') || {}).inState || {}).click = false;
                            });
                            location.reload();
                        } else {
                            alert(obj.message);
                            location.reload();
                        }
                    },
                    error: function(xhr, status, error) {
                        $('.popuploader').hide();
                        console.error('Error updating task:', xhr.responseText);
                        alert('An error occurred while updating the task. Please try again.');
                    }
                });
            } else {
                $("#loader").hide();
            }
        });

        // REMOVED: Deprecated appointment system functionality
        // Open assignee view modal - endpoint /get-assigne-detail was removed
        // $(document).on('click', '.listing-container .openassigneview', function() { ... });

        // Delete task record
        $(document).on('click', '.listing-container .deleteNote', function(e) {
            e.preventDefault();
            var url = $(this).data('remote');
            
            if (confirm('Are you sure you want to delete this task?')) {
                $.ajaxSetup({
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    }
                });

                $.ajax({
                    type: 'DELETE',
                    url: url,
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert('Error deleting task: ' + response.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        alert('Error deleting task. Please try again.');
                        console.error('Delete error:', error);
                    }
                });
            }
        });
    });
</script>
@endpush
