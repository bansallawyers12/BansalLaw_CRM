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
    $kindInputId = $kindInputId ?? 'add_task_kind';
    $remindOnId = $remindOnId ?? 'add_task_remind_on';
    $remindOnErrorId = $remindOnErrorId ?? 'add_task_remind_on_error';
    $staffMembers = $staffMembers ?? collect();
    $viewer = auth('admin')->user();
    $canAddReminder = $viewer instanceof \App\Models\Staff && $viewer->canAccessPersonalCalendar();
@endphp
<div class="modern-popover-content add-task-layout" data-can-reminder="{{ $canAddReminder ? '1' : '0' }}">
    @if($canAddReminder)
    <div class="form-group form-group-full-width add-task-kind-group">
        <div class="add-task-kind-toggle" role="group" aria-label="Task or reminder">
            <button type="button" class="add-task-kind-btn is-active" data-add-task-kind="task" aria-pressed="true">
                <span class="add-task-kind-btn__icon" aria-hidden="true"><i class="fa-solid fa-list-check"></i></span>
                <span class="add-task-kind-btn__copy">
                    <span class="add-task-kind-btn__title">Task</span>
                    <span class="add-task-kind-btn__desc">Assign to staff</span>
                </span>
            </button>
            <button type="button" class="add-task-kind-btn add-task-kind-btn--reminder" data-add-task-kind="reminder" aria-pressed="false">
                <span class="add-task-kind-btn__icon" aria-hidden="true"><i class="fa-solid fa-bell"></i></span>
                <span class="add-task-kind-btn__copy">
                    <span class="add-task-kind-btn__title">Reminder</span>
                    <span class="add-task-kind-btn__desc">On your calendar</span>
                </span>
            </button>
        </div>
        <input type="hidden" id="{{ $kindInputId }}" class="add-task-kind-input" value="task">
        <p class="add-task-kind-hint" data-hint-task="Assign work to one or more people. Client is optional." data-hint-reminder="Pick a client or lead and a date — we’ll put it on your personal calendar.">
            Assign work to one or more people. Client is optional.
        </p>
    </div>
    @else
    <input type="hidden" id="{{ $kindInputId }}" class="add-task-kind-input" value="task">
    @endif

    <div class="add-task-fields">
        <div class="form-group add-task-client-group">
            <label class="control-label" for="{{ $clientSelectId }}"><i class="fa-solid fa-user-circle"></i> <span class="add-task-client-label-text">Client / Lead</span></label>
            <select id="{{ $clientSelectId }}" class="form-control js-data-example-ajaxccsearch__addmytask" data-placeholder="Search client or lead..."></select>
            <div id="{{ $clientErrorId }}" class="error-message"></div>
        </div>

        <div class="add-task-assignee-block">
            @include('components.add-task-assignee-picker', [
                'selectAllId' => $selectAllId,
                'hiddenSelectId' => $hiddenSelectId,
                'errorId' => $assigneesErrorId,
                'staffMembers' => $staffMembers,
            ])
        </div>

        <div class="form-group add-task-remind-on-group" hidden>
            <label class="control-label" for="{{ $remindOnId }}"><i class="fa-regular fa-calendar"></i> Remind on</label>
            <input type="date" id="{{ $remindOnId }}" class="form-control add-task-remind-on" autocomplete="off">
            <div id="{{ $remindOnErrorId }}" class="error-message"></div>
        </div>

        <div class="form-group form-group-full-width add-task-description-group">
            <label class="control-label" for="{{ $noteTextareaId }}"><i class="fa-solid fa-pen"></i> <span class="add-task-note-label-text">Task Description</span></label>
            <textarea id="{{ $noteTextareaId }}" class="form-control js-staff-mentions" rows="4" placeholder="Enter task description... (type @ to tag staff)"></textarea>
            <div id="{{ $noteErrorId }}" class="error-message"></div>
        </div>
    </div>

    <input id="{{ $taskGroupId }}" name="task_group" type="hidden" value="Personal Task">

    <div class="add-task-modal-footer">
        <button type="button" class="btn btn-primary add-task-submit-btn" id="{{ $submitBtnId }}">
            <i class="fa-solid fa-circle-plus"></i> <span class="add-task-submit-label">{{ $submitLabel }}</span>
        </button>
    </div>
</div>
