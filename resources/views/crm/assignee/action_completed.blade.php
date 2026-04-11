@extends('layouts.crm_client_detail')
@section('title', 'Completed Action')

@section('styles')
<link rel="stylesheet" href="{{ asset('css/listing-pagination.css') }}">
<link rel="stylesheet" href="{{ asset('css/listing-container.css') }}">
<link rel="stylesheet" href="{{ asset('css/listing-datepicker.css') }}">
<style>
    /* Completed actions — docs/theme.md (tokens from crm-theme.css :root) */
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

    /* Wrapper <button><a>…</a></button> — strip chrome so themed <a> shows */
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

    .listing-container .select2-container {
        z-index: 100000;
        width: 100% !important;
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
<div class="listing-container">
    <section class="listing-section" style="padding-top: 80px;">
        <div class="listing-section-body">
            @include('../Elements/flash-message')
            
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center flex-wrap">
                        <h4>Completed Action</h4>
                        <ul class="nav nav-pills" id="client_tabs" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link" id="incomplete-tab" href="{{ URL::to('/action') }}">Incomplete</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link active" id="completed-tab" href="{{ URL::to('/action_completed') }}">Completed</a>
                            </li>
                        </ul>
                    </div>
                </div>
                
                <div class="card-body">
                    <div class="tab-content" id="quotationContent">
                        <form action="{{ route('assignee.action_completed') }}" method="get" class="action-completed-filter-form">
                            <div class="row mb-3">
                                <div class="col-md-12 filter-buttons">
                                    <a href="{{URL::to('/action_completed?group_type=All')}}" id="All" class="group_type {{ $task_group == 'All' ? 'active' : '' }}">All <span class="countAction">{{ $taskGroupCounts['All'] }}</span></a>
                                    <button type="button">
                                        <a href="{{URL::to('/action_completed?group_type=Call')}}" id="Call" class="group_type {{ $task_group == 'Call' ? 'active' : '' }}"><i class="fa fa-phone" aria-hidden="true"></i> Call <span class="countAction">{{ $taskGroupCounts['Call'] }}</span></a>
                                    </button>
                                    <button type="button">
                                        <a href="{{URL::to('/action_completed?group_type=Checklist')}}" id="Checklist" class="group_type {{ $task_group == 'Checklist' ? 'active' : '' }}"><i class="fa fa-bars" aria-hidden="true"></i> Checklist <span class="countAction">{{ $taskGroupCounts['Checklist'] }}</span></a>
                                    </button>
                                    <button type="button">
                                        <a href="{{URL::to('/action_completed?group_type=Review')}}" id="Review" class="group_type {{ $task_group == 'Review' ? 'active' : '' }}"><i class="fa fa-check" aria-hidden="true"></i> Review <span class="countAction">{{ $taskGroupCounts['Review'] }}</span></a>
                                    </button>
                                    <button type="button">
                                        <a href="{{URL::to('/action_completed?group_type=Query')}}" id="Query" class="group_type {{ $task_group == 'Query' ? 'active' : '' }}"><i class="fa fa-question" aria-hidden="true"></i> Query <span class="countAction">{{ $taskGroupCounts['Query'] }}</span></a>
                                    </button>
                                    <button type="button">
                                        <a href="{{URL::to('/action_completed?group_type=Urgent')}}" id="Urgent" class="group_type {{ $task_group == 'Urgent' ? 'active' : '' }}"><i class="fa fa-flag" aria-hidden="true"></i> Urgent <span class="countAction">{{ $taskGroupCounts['Urgent'] }}</span></a>
                                    </button>
                                    <button type="button">
                                        <a href="{{URL::to('/action_completed?group_type=Personal Action')}}" id="Personal Action" class="group_type {{ $task_group == 'Personal Action' ? 'active' : '' }}"><i class="fa fa-tasks" aria-hidden="true"></i> Personal Action <span class="countAction">{{ $taskGroupCounts['Personal Action'] }}</span></a>
                                    </button>
                                    <button type="button">
                                        <a href="{{URL::to('/action_completed?group_type=Client Portal')}}" id="Client Portal" class="group_type {{ $task_group == 'Client Portal' ? 'active' : '' }}"><i class="fa fa-globe" aria-hidden="true"></i> Client app <span class="countAction">{{ $taskGroupCounts['Client Portal'] }}</span></a>
                                    </button>
                                    <button type="button">
                                        <a href="{{URL::to('/action_completed?group_type=Follow Up')}}" id="Follow Up" class="group_type {{ $task_group == 'Follow Up' ? 'active' : '' }}"><i class="fa fa-calendar-check-o" aria-hidden="true"></i> Follow up <span class="countAction">{{ $taskGroupCounts['Follow Up'] ?? 0 }}</span></a>
                                    </button>
                                </div>
                            </div>
                        </form>

                        <div class="tab-pane fade show active" id="active_quotation" role="tabpanel" aria-labelledby="active_quotation-tab">
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th style="text-align: center;">Sno</th>
                                            <th style="text-align: center;">Done</th>
                                            <th>Assigner Name</th>
                                            <th>Client Reference</th>
                                            <th class="sort_col">@sortablelink('action_date','Assign Date')</th>
                                            <th class="sort_col">@sortablelink('task_group','Type')</th>
                                            <th>Note</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @if(count($assignees_completed) > 0)
                                            @foreach($assignees_completed as $list)
                                                <?php
                                                    $staff = $list->noteStaff;
                                                    $full_name = $staff ? ($staff->first_name ?? 'N/A') . ' ' . ($staff->last_name ?? 'N/A') : 'N/A';
                                                    $client_name = $list->noteClient ? trim($list->noteClient->company_name_or_personal_name) : 'N/P';
                                                    if ($list->noteClient && $client_name === '') {
                                                        $client_name = trim($list->noteClient->first_name . ' ' . $list->noteClient->last_name) ?: 'N/P';
                                                    }
                                                ?>
                                                <tr>
                                                    <td style="text-align: center;">{{ ++$i }}</td>
                                                    <td style="text-align: center;">
                                                        <input type="radio" class="not_complete_task" data-bs-toggle="tooltip" title="Mark Incomplete!" data-id="{{ $list->id }}" data-unique_group_id="{{ $list->unique_group_id }}">
                                                    </td>
                                                    <td>{{ $full_name }}</td>
                                                    <td>
                                                        {{ $client_name }}<br>
                                                        @if($list->noteClient)
                                                            <a href="{{URL::to('/clients/detail/'.base64_encode(convert_uuencode(@$list->client_id)))}}" target="_blank">{{ $list->noteClient->client_id }}</a>
                                                        @endif
                                                    </td>
                                                    <td>{{ date('d/m/Y', strtotime($list->action_date)) ?? 'N/P' }}</td>
                                                    <td>{{ $list->task_group ?? 'N/P' }}</td>
                                                    <td>
                                                        @if(isset($list->description) && $list->description != "")
                                                            @if(strlen($list->description) > 190)
                                                                {{ substr($list->description, 0, 190) }}
                                                                <button type="button" class="btn btn-link" data-bs-toggle="popover" title="" data-content="{{ htmlspecialchars($list->description, ENT_QUOTES, 'UTF-8') }}">Read more</button>
                                                            @else
                                                                {{ $list->description }}
                                                            @endif
                                                        @else
                                                            N/P
                                                        @endif
                                                    </td>
                                                    <td>
                                                        <div class="action-buttons">
                                                            @if($list->task_group != 'Personal Action')
                                                                <button type="button" data-noteid="{{ $list->description }}" data-taskid="{{ $list->id }}" data-taskgroupid="{{ $list->task_group }}" data-actiondate="{{ $list->action_date }}" data-bs-toggle="tooltip" title="Update Task" class="btn btn-primary btn-sm update_task" data-bs-container="body" data-role="popover" data-bs-placement="bottom" data-bs-html="true" data-bs-content="<div id='popover-content'>
                                                                    <h4 class='text-center'>Update Task</h4>
                                                                    <div class='clearfix'></div>
                                                                    <div class='box-header with-border'>
                                                                        <div class='form-group row' style='margin-bottom:12px'>
                                                                            <label for='inputSub3' class='col-sm-3 control-label c6 f13' style='margin-top:8px'>Select Assignee</label>
                                                                            <div class='col-sm-9'>
                                                                                <select class='assigneeselect2 form-control selec_reg' id='rem_cat' name='rem_cat'>
                                                                                    <option value=''>Select</option>
                                                                                    @foreach(\App\Models\Staff::where('status',1)->orderby('first_name','ASC')->get() as $admin)
                                                                                        <?php $branchname = \App\Models\Branch::where('id', $admin->office_id)->first(); ?>
                                                                                        <option value='{{ $admin->id }}' {{ $admin->id == $list->assigned_to ? 'selected' : '' }}>{{ $admin->first_name . ' ' . $admin->last_name . ' (' . @$branchname->office_name . ')' }}</option>
                                                                                    @endforeach
                                                                                </select>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    <div class='box-header with-border'>
                                                                        <div class='form-group row' style='margin-bottom:12px'>
                                                                            <label for='inputEmail3' class='col-sm-3 control-label c6 f13' style='margin-top:8px'>Note</label>
                                                                            <div class='col-sm-9'>
                                                                                <textarea id='assignnote' class='form-control summernote-simple f13' placeholder='Enter a note....' type='text'></textarea>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    <div class='box-header with-border'>
                                                                        <div class='form-group row' style='margin-bottom:12px'>
                                                                            <label for='inputEmail3' class='col-sm-3 control-label c6 f13' style='margin-top:8px'>DateTime</label>
                                                                            <div class='col-sm-9'>
                                                                                <input type='date' class='form-control f13' placeholder='yyyy-mm-dd' id='popoverdatetime' value='{{ date('Y-m-d') }}' name='popoverdate'>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    <div class='form-group row' style='margin-bottom:12px'>
                                                                        <label for='inputSub3' class='col-sm-3 control-label c6 f13' style='margin-top:8px'>Group</label>
                                                                        <div class='col-sm-9'>
                                                                            <select class='assigneeselect2 form-control selec_reg' id='task_group' name='task_group'>
                                                                                <option value=''>Select</option>
                                                                                <option value='Call'>Call</option>
                                                                                <option value='Checklist'>Checklist</option>
                                                                                <option value='Review'>Review</option>
                                                                                <option value='Query'>Query</option>
                                                                                <option value='Urgent'>Urgent</option>
                                                                            </select>
                                                                        </div>
                                                                    </div>
                                                                    <input id='assign_note_id' type='hidden' value=''>
                                                                    <input id='assign_client_id' type='hidden' value='{{ base64_encode(convert_uuencode(@$list->client_id)) }}'>
                                                                    <div class='box-footer' style='padding:10px 0'>
                                                                        <div class='row text-center'>
                                                                            <div class='col-md-12 text-center'>
                                                                                <button class='btn btn-info' id='updateTask'>Update Task</button>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>">
                                                                    <i class="fa fa-edit" aria-hidden="true"></i>
                                                                </button>
                                                            @endif

                                                            <form action="{{ route('assignee.destroy_complete_activity', $list->id) }}" method="POST">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit" class="btn btn-danger btn-sm" data-bs-toggle="tooltip" title="Delete" onclick="return confirm('Are you sure want to delete?');">
                                                                    <i class="fa fa-trash" aria-hidden="true"></i>
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        @else
                                            <tr>
                                                <td colspan="8" style="text-align: center; padding: 20px;">
                                                    There are no completed actions.
                                                </td>
                                            </tr>
                                        @endif
                                    </tbody>
                                </table>
                                
                                <!-- Pagination -->
                                <div class="card-footer">
                                    {!! $assignees_completed->appends($_GET)->links() !!}
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
<div class="modal fade custom_modal" id="openassigneview" tabindex="-1" role="dialog" aria-labelledby="" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content taskview"></div>
    </div>
</div>
@endsection

@push('scripts')
<link rel="stylesheet" href="{{URL::to('/')}}/css/task-popover-modern.css">
<script src="{{URL::to('/')}}/js/popover.js"></script>
<script>
jQuery(document).ready(function($){
    $('.listing-container [data-bs-toggle="tooltip"]').tooltip();

    $(document).delegate('.listing-container .openassignee', 'click', function(){
        $('.assignee').show();
    });

    $(document).delegate('.listing-container .closeassignee', 'click', function(){
        $('.assignee').hide();
    });

    // Reassign task
    $(document).delegate('.listing-container .reassign_task', 'click', function(){
        var note_id = $(this).attr('data-noteid');
        $('#assignnote').val(note_id);
        var task_id = $(this).attr('data-taskid');
        $('#assign_note_id').val(task_id);
    });

    // Update task popover — populate fields + Select2 (dropdown parent = popover shell)
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

    // Mark task as incomplete
    $(document).delegate('.listing-container .not_complete_task', 'click', function(){
        var row_id = $(this).attr('data-id');
        var row_unique_group_id = $(this).attr('data-unique_group_id');
        if(row_id != ""){
            $.ajax({
                type: 'post',
                url: "{{URL::to('/')}}/update-action-not-completed",
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                data: { id: row_id, unique_group_id: row_unique_group_id },
                success: function(response){
                    var obj = $.parseJSON(response);
                    location.reload();
                }
            });
        }
    });

    // Reassign from completed (creates new action) — button id in popover is #updateTask
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
                url: "{{ URL::to('/') }}/clients/action/reassign",
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
                error: function() {
                    $('.popuploader').hide();
                    alert('An error occurred. Please try again.');
                }
            });
        } else {
            $("#loader").hide();
        }
    });

    // REMOVED: Deprecated appointment system functionality
    // Open assignee view modal - endpoint /get-assigne-detail was removed
    // $(document).delegate('.listing-container .openassigneview', 'click', function(){ ... });
});
</script>
@endpush
