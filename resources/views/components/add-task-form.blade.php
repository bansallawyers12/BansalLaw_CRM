@php
    $clientSelectId = $clientSelectId ?? 'add_task_client_select';
    $clientErrorId = $clientErrorId ?? 'add_task_client_error';
    $noteTextareaId = $noteTextareaId ?? 'add_task_assignnote';
    $noteErrorId = $noteErrorId ?? 'add_task_note_error';
    $taskGroupId = $taskGroupId ?? 'add_task_task_group';
    $submitBtnId = $submitBtnId ?? 'add_my_task_submit';
    $submitLabel = $submitLabel ?? 'Add My Task';
    $selectAllId = $selectAllId ?? 'add_task_select_all';
    $hiddenSelectId = $hiddenSelectId ?? 'add_task_rem_cat';
    $assigneesErrorId = $assigneesErrorId ?? 'add_task_assignees_error';
    $staffMembers = $staffMembers ?? collect();
@endphp
<div class="modern-popover-content add-task-layout">
    <div class="form-group add-task-client-group">
        <label class="control-label" for="{{ $clientSelectId }}"><i class="fa-solid fa-user-circle"></i> Client</label>
        <select id="{{ $clientSelectId }}" class="form-control js-data-example-ajaxccsearch__addmytask" data-placeholder="Search client..."></select>
        <div id="{{ $clientErrorId }}" class="error-message"></div>
    </div>

    @include('components.add-task-assignee-picker', [
        'selectAllId' => $selectAllId,
        'hiddenSelectId' => $hiddenSelectId,
        'errorId' => $assigneesErrorId,
        'staffMembers' => $staffMembers,
    ])

    <div class="form-group form-group-full-width add-task-description-group">
        <label class="control-label" for="{{ $noteTextareaId }}"><i class="fa-solid fa-comment"></i> Task Description</label>
        <textarea id="{{ $noteTextareaId }}" class="form-control js-staff-mentions" rows="3" placeholder="Enter task description... (type @ to tag staff)"></textarea>
        <div id="{{ $noteErrorId }}" class="error-message"></div>
    </div>

    <input id="{{ $taskGroupId }}" name="task_group" type="hidden" value="Personal Task">

    <div class="add-task-modal-footer">
        <button type="button" class="btn btn-primary" id="{{ $submitBtnId }}">
            <i class="fa-solid fa-circle-plus"></i> {{ $submitLabel }}
        </button>
    </div>
</div>
