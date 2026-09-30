// Dashboard JavaScript
// Owns: greeting clock, task complete/extend deadline, task detail side panel,
// and related modals. Page refresh / infinite scroll live in
// public/js/crm/dashboard/dashboard-page.js (loaded after this file).
$(document).ready(function() {
    // Check if required objects are defined
    if (typeof window.dashboardRoutes === 'undefined') {
        console.error('Dashboard routes not defined. Please ensure routes are loaded before this script.');
        return;
    }
    
    if (typeof window.dashboardData === 'undefined') {
        console.warn('Dashboard data not defined. Some features may not work correctly.');
    }
    
    initializeDashboard();
    initializeEventHandlers();
});

function initializeDashboard() {
    initDashboardClock();
}

/**
 * Update dashboard date/time once per minute (aligned to clock minute).
 * Uses native Intl APIs only — no polling, no network, minimal DOM updates.
 */
function initDashboardClock() {
    var dateTimeEl = document.getElementById('dashboardDateTime');
    if (!dateTimeEl) {
        return;
    }

    var greetingEl = document.getElementById('dashboardGreeting');
    var firstName = greetingEl ? (greetingEl.getAttribute('data-first-name') || '').trim() : '';
    var timeZone = (dateTimeEl.getAttribute('data-timezone') || '').trim() || undefined;

    var dateFormatter = new Intl.DateTimeFormat(undefined, {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
        timeZone: timeZone
    });
    var timeFormatter = new Intl.DateTimeFormat(undefined, {
        hour: 'numeric',
        minute: '2-digit',
        hour12: true,
        timeZone: timeZone
    });
    var hourFormatter = new Intl.DateTimeFormat(undefined, {
        hour: 'numeric',
        hour12: false,
        timeZone: timeZone
    });

    function greetingForHour(hour) {
        if (hour < 12) {
            return 'Good morning';
        }
        if (hour < 17) {
            return 'Good afternoon';
        }
        return 'Good evening';
    }

    function tick() {
        var now = new Date();
        var dateLabel = dateFormatter.format(now);
        var timeLabel = timeFormatter.format(now);
        dateTimeEl.textContent = dateLabel + ' \u00b7 ' + timeLabel;
        dateTimeEl.setAttribute('datetime', now.toISOString());

        if (greetingEl && firstName) {
            var hour = parseInt(hourFormatter.format(now), 10);
            greetingEl.textContent = greetingForHour(hour) + ', ' + firstName;
        }
    }

    tick();

    var now = new Date();
    var msUntilNextMinute = ((60 - now.getSeconds()) * 1000) - now.getMilliseconds();
    if (msUntilNextMinute < 0) {
        msUntilNextMinute = 0;
    }

    window.setTimeout(function() {
        tick();
        window.setInterval(tick, 60000);
    }, msUntilNextMinute);
}

function initializeEventHandlers() {
    // Extend deadline
    $(document).on('click', '#extend_deadline', extendDeadline);
    $(document).delegate('.btn-extend_note_deadline', 'click', openExtendDeadlineModal);

    // Completion notes modal - confirm task completion
    $(document).on('click', '#dashboardConfirmTaskCompletion', function() {
        if (dashboardPendingTaskId) {
            var notes = $('#dashboardCompletionNotes').val();
            var taskId = dashboardPendingTaskId;
            var uniqueGroupId = dashboardPendingUniqueGroupId;
            dashboardCompletionConfirmed = true;
            $('#dashboardCompletionNotesModal').modal('hide');
            completeTask(taskId, uniqueGroupId, notes);
            dashboardPendingTaskId = null;
            dashboardPendingUniqueGroupId = null;
        }
    });

    // Clear pending completion when modal is closed without completing
    $(document).on('hidden.bs.modal', '#dashboardCompletionNotesModal', function() {
        if (!dashboardCompletionConfirmed) {
            resetTaskCompleteCheckbox(dashboardPendingTaskId);
        }
        dashboardCompletionConfirmed = false;
        dashboardPendingTaskId = null;
        dashboardPendingUniqueGroupId = null;
    });

    // Dashboard Quick Tasks Modal (#create_task_modal / #tasktermform)
    initDashboardQuickTaskModal();
}

function extendDeadline() {
    if (!window.dashboardRoutes || !window.dashboardRoutes.extendDeadline) {
        console.error('Extend deadline route not defined');
        showNotification('Configuration error: Extend deadline route not available.', 'error');
        return;
    }
    
    $(".popuploader").show();
    let flag = true;
    $(".custom-error").remove();

    if ($('#assignnote').val() === '') {
        $('#assignnote').after("<span class='custom-error'>Note field is required.</span>");
        flag = false;
    }
    
    if ($('#note_deadline').val() === '') {
        $('#note_deadline').after("<span class='custom-error'>Note Deadline is required.</span>");
        flag = false;
    }

    if (flag) {
        $.ajax({
            type: 'POST',
            url: window.dashboardRoutes.extendDeadline,
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            data: {
                note_id: $('#note_id').val(),
                unique_group_id: $('#unique_group_id').val(),
                description: $('#assignnote').val(),
                note_deadline: noteDeadlineToYmd($('#note_deadline').val())
            },
            success: function(response) {
                $('.popuploader').hide();
                if (response && response.success === false) {
                    showNotification(response.message || 'Failed to extend deadline.', 'error');
                    return;
                }
                $('#extend_note_popup').modal('hide');
                showNotification((response && response.message) || 'Deadline extended successfully!', 'success');
                if (typeof window.refreshDashboard === 'function') {
                    setTimeout(function () { window.refreshDashboard(); }, 400);
                } else {
                    setTimeout(function () { location.reload(); }, 1000);
                }
            },
            error: function(xhr) {
                $('.popuploader').hide();
                var msg = 'Failed to extend deadline.';
                if (xhr && xhr.responseJSON) {
                    msg = xhr.responseJSON.message
                        || (xhr.responseJSON.errors
                            ? Object.values(xhr.responseJSON.errors).flat().join(' ')
                            : msg);
                }
                showNotification(msg, 'error');
            }
        });
    } else {
        $('.popuploader').hide();
    }
}

function openExtendDeadlineModal() {
    var $button = $(this);
    $('#note_id').val($button.attr("data-noteid"));
    $('#unique_group_id').val($button.attr("data-uniquegroupid"));
    $('#assignnote').val($button.attr("data-assignnote"));
    setNoteDeadlineInput($button.attr("data-deadlinedate"));
    $('#extend_note_popup').modal('show');
}

window.closeNotesDeadlineAction = function(noteid, noteuniqueid) {
    
    if (!window.dashboardRoutes || !window.dashboardRoutes.updateTaskCompleted) {
        console.error('Update task completed route not defined');
        showNotification('Configuration error: Update task route not available.', 'error');
        return;
    }
    
    if (confirm('Are you sure, you want to close this note deadline?')) {
        if (noteid == '' && noteuniqueid == '') {
            showNotification('Please select note to close the deadline.', 'error');
            return false;
        }
        
        
        $('.popuploader').show();
        $.ajax({
            type: 'post',
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
            url: window.dashboardRoutes.updateTaskCompleted,
            data: {'id': noteid, 'unique_group_id': noteuniqueid},
            success: function(response) {
                $('.popuploader').hide();
                showNotification('Task completed successfully!', 'success');
                if (typeof window.refreshCrmNavPendingTaskCount === 'function') {
                    window.refreshCrmNavPendingTaskCount();
                }
                if (typeof window.refreshDashboard === 'function') {
                    setTimeout(function () { window.refreshDashboard(); }, 400);
                } else {
                    setTimeout(function () { location.reload(); }, 1000);
                }
            },
            error: function(xhr, status, error) {
                console.error('Error response:', xhr.responseText);
                console.error('Status:', status, 'Error:', error);
                $('.popuploader').hide();
                showNotification('Failed to complete task.', 'error');
            }
        });
    } else {
        $('.popuploader').hide();
    }
}

function showNotification(message, type = 'info') {
    if (typeof crmToast === 'function') {
        crmToast(message, type);
        return;
    }
    if (typeof crmNotify !== 'undefined') {
        var opts = { message: message, position: 'topRight' };
        if (type === 'error') {
            crmNotify.error(opts);
        } else if (type === 'success') {
            crmNotify.success(opts);
        } else {
            crmNotify.info(opts);
        }
    }
}

// ====================
// Microsoft To Do Style Task Functions
// ====================

// Open Task Detail Panel
window.openTaskDetail = function(taskId) {
    const taskItem = $(`.todo-task-item[data-task-id="${taskId}"]`).first();
    if (!taskItem.length) return;
    
    const panel = $('#taskDetailPanel');
    const data = taskItem.data();
    // Use attr so empty data-client-id is reliable (jQuery .data() can coerce types)
    const rawClientId = (taskItem.attr('data-client-id') || '').trim();
    const isPersonalAction = rawClientId === '';
    const clientDetailUrl = taskItem.attr('data-client-detail-url') || data.clientDetailUrl || '';
    const personalActionUrl = (window.dashboardRoutes && window.dashboardRoutes.assigneeAction) ? window.dashboardRoutes.assigneeAction : '/tasks';
    
    // Populate panel with task data
    $('#taskDetailTitle').text(stripHtml(data.description));
    $('#taskDetailClientName').text(data.clientName || 'Personal Task');
    $('#taskDetailClientCode').text(data.clientCode ? `(${data.clientCode})` : '');
    $('#taskDetailClientLink').attr('href', isPersonalAction ? personalActionUrl : (clientDetailUrl || '#'));
    
    // Handle deadline display
    if (data.deadline) {
        $('#taskDetailDueDate').text(formatDate(data.deadline));
        $('#taskDetailDueDate').removeClass('overdue today tomorrow this-week upcoming no-deadline')
            .addClass(data.urgency);
    } else {
        $('#taskDetailDueDate').text('No deadline set');
        $('#taskDetailDueDate').removeClass('overdue today tomorrow this-week upcoming')
            .addClass('no-deadline');
    }
    
    $('#taskDetailAssigned').text(data.assignedTo);
    $('#taskDetailDescription').html(data.description);
    
    // Set checkbox state
    $('#taskDetailComplete').prop('checked', false);
    
    // Store task info in panel
    panel.data('taskId', taskId);
    panel.data('uniqueGroupId', data.uniqueGroupId);
    panel.data('noteId', taskId);
    panel.data('description', data.description);
    panel.data('deadline', data.deadlineFormatted || '');
    
    // Show panel
    panel.addClass('active');
};

// Close Task Detail Panel
window.closeTaskDetail = function() {
    $('#taskDetailPanel').removeClass('active');
};

// Pending task completion (stored when modal opens)
var dashboardPendingTaskId = null;
var dashboardPendingUniqueGroupId = null;
var dashboardCompletionConfirmed = false;

function taskCompleteCheckbox(taskId) {
    if (!taskId) {
        return $();
    }
    return $('#task-' + taskId);
}

function resetTaskCompleteCheckbox(taskId) {
    var $cb = taskCompleteCheckbox(taskId);
    if ($cb.length) {
        $cb.prop('checked', false);
    }
    var $detail = $('#taskDetailComplete');
    if ($detail.length && String($('#taskDetailPanel').data('taskId')) === String(taskId)) {
        $detail.prop('checked', false);
    }
}

// Handle Task Complete from Checkbox - open completion notes modal
window.handleTaskComplete = function(taskId, uniqueGroupId) {
    // Keep unchecked until the user confirms in the modal (avoids "done + overdue" stuck state).
    resetTaskCompleteCheckbox(taskId);
    dashboardCompletionConfirmed = false;
    dashboardPendingTaskId = taskId;
    dashboardPendingUniqueGroupId = uniqueGroupId;
    $('#dashboardCompletionNotes').val('');
    $('#dashboardCompletionNotesModal').modal('show');
};

// Complete Task from Detail Panel - open completion notes modal
window.completeTaskFromDetail = function() {
    const panel = $('#taskDetailPanel');
    dashboardCompletionConfirmed = false;
    dashboardPendingTaskId = panel.data('taskId');
    dashboardPendingUniqueGroupId = panel.data('uniqueGroupId');
    resetTaskCompleteCheckbox(dashboardPendingTaskId);
    $('#dashboardCompletionNotes').val('');
    $('#dashboardCompletionNotesModal').modal('show');
};

// Complete Task Function (called after modal confirm or directly)
function completeTask(taskId, uniqueGroupId, completionNotes) {
    if (!taskId) {
        showNotification('Invalid task data', 'error');
        return;
    }
    
    $('.popuploader').show();
    
    var postData = { id: taskId, unique_group_id: uniqueGroupId || '' };
    if (typeof completionNotes === 'string' && completionNotes.trim()) {
        postData.completion_notes = completionNotes.trim();
    }
    
    $.ajax({
        type: 'POST',
        url: window.dashboardRoutes.updateTaskCompleted,
        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
        data: postData,
        success: function(response) {
            $('.popuploader').hide();
            if (response.success) {
                // Close detail panel
                closeTaskDetail();
                
                // Animate task removal
                const taskItem = $(`.todo-task-item[data-task-id="${taskId}"]`);
                taskItem.css('opacity', '0.5');
                setTimeout(() => {
                    taskItem.fadeOut(300, function() {
                        $(this).remove();
                        updateTaskCount();
                    });
                }, 200);
                
                showNotification('Task completed successfully!', 'success');
                if (typeof window.refreshCrmNavPendingTaskCount === 'function') {
                    window.refreshCrmNavPendingTaskCount();
                }
            } else {
                resetTaskCompleteCheckbox(taskId);
                showNotification(response.message || 'Failed to complete task', 'error');
            }
        },
        error: function(xhr, status, error) {
            $('.popuploader').hide();
            resetTaskCompleteCheckbox(taskId);
            console.error('Error completing task:', error);
            showNotification('An error occurred while completing the task', 'error');
        }
    });
}

// Open Extend Modal from Task Item
window.openExtendModal = function(taskId) {
    const taskItem = $(`.todo-task-item[data-task-id="${taskId}"]`).first();
    if (!taskItem.length) {
        showNotification('Task not found', 'error');
        return;
    }

    const description = stripHtml(taskItem.attr('data-description') || '');
    const deadline = taskItem.attr('data-deadline-formatted')
        || taskItem.attr('data-deadline')
        || '';
    const uniqueGroupId = taskItem.attr('data-unique-group-id') || '';

    $('#note_id').val(taskId);
    $('#unique_group_id').val(uniqueGroupId);
    $('#assignnote').val(description);

    closeTaskDetail();

    try {
        setNoteDeadlineInput(deadline);
    } catch (err) {
        console.warn('setNoteDeadlineInput failed', err);
        $('#note_deadline').val(noteDeadlineToDdMmYyyy(deadline));
    }

    $('#extend_note_popup').modal('show');
};

// Open Add Deadline Modal for tasks without deadlines
window.openAddDeadlineModal = function(taskId) {
    openExtendModal(taskId); // Reuse the same modal
};

// Extend Task from Detail Panel
window.extendTaskFromDetail = function() {
    const panel = $('#taskDetailPanel');
    const taskId = panel.data('taskId');
    const uniqueGroupId = panel.data('uniqueGroupId') || '';
    const description = panel.data('description');
    const deadline = panel.data('deadlineFormatted') || panel.data('deadline');

    $('#note_id').val(taskId);
    $('#unique_group_id').val(uniqueGroupId || '');
    $('#assignnote').val(stripHtml(description));

    closeTaskDetail();

    try {
        setNoteDeadlineInput(deadline);
    } catch (err) {
        console.warn('setNoteDeadlineInput failed', err);
        $('#note_deadline').val(noteDeadlineToDdMmYyyy(deadline));
    }

    $('#extend_note_popup').modal('show');
};

// Update Task Count
function updateTaskCount() {
    const $root = $('#todo-task-list-root');
    let total = parseInt($root.attr('data-total'), 10);
    if (!isNaN(total)) {
        total = Math.max(0, total - 1);
        $root.attr('data-total', total);
        $('.todo-count-badge').text(total);
    } else {
        const count = $('.todo-task-item').length;
        $('.todo-count-badge').text(count);
        total = count;
    }

    const remainingInDom = $('.todo-task-item').length;
    if (remainingInDom === 0 && total === 0) {
        $('.todo-task-list').hide();
        $('#todoInfiniteLoader').remove();
        if ($('.todo-empty-state').length === 0) {
            $('.todo-task-list-container').html(`
                <div class="todo-empty-state">
                    <div class="todo-empty-icon">
                        <i class="fa-solid fa-circle-check"></i>
                    </div>
                    <h4>All caught up!</h4>
                    <p>You have no tasks at the moment.</p>
                    <button type="button"
                            class="todo-empty-add-btn add_my_task"
                            data-container="body"
                            data-placement="bottom-start"
                            data-html="true"
                            data-content-id="add-task-popover-template"
                            title="Add New Task">
                        <i class="fa-solid fa-plus"></i>
                        Add a task
                    </button>
                </div>
            `);
            if (window.DashboardAddTaskPopover && typeof window.DashboardAddTaskPopover.init === 'function') {
                window.DashboardAddTaskPopover.init('.todo-task-list-container');
            }
        }
    }
}

// Helper Functions
function stripHtml(html) {
    if (html == null || html === '') {
        return '';
    }
    const tmp = document.createElement('div');
    tmp.innerHTML = String(html);
    return tmp.textContent || tmp.innerText || '';
}

/** Convert Y-m-d / ISO / Date-ish strings to dd/mm/yyyy for display. */
function noteDeadlineToDdMmYyyy(value) {
    if (!value) {
        return '';
    }
    var str = String(value).trim();
    if (/^\d{1,2}\/\d{1,2}\/\d{4}$/.test(str)) {
        var parts = str.split('/');
        return ('0' + parts[0]).slice(-2) + '/' + ('0' + parts[1]).slice(-2) + '/' + parts[2];
    }
    var iso = str.match(/^(\d{4})-(\d{2})-(\d{2})/);
    if (iso) {
        return iso[3] + '/' + iso[2] + '/' + iso[1];
    }
    var parsed = new Date(str);
    if (!isNaN(parsed.getTime())) {
        var d = ('0' + parsed.getDate()).slice(-2);
        var m = ('0' + (parsed.getMonth() + 1)).slice(-2);
        return d + '/' + m + '/' + parsed.getFullYear();
    }
    return str;
}

/** Convert dd/mm/yyyy (or Y-m-d) to Y-m-d for the API. */
function noteDeadlineToYmd(value) {
    if (!value) {
        return '';
    }
    var str = String(value).trim();
    if (/^\d{4}-\d{2}-\d{2}$/.test(str)) {
        return str;
    }
    var m = str.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/);
    if (m) {
        return m[3] + '-' + ('0' + m[2]).slice(-2) + '-' + ('0' + m[1]).slice(-2);
    }
    return str;
}

function setNoteDeadlineInput(value) {
    var display = noteDeadlineToDdMmYyyy(value);
    var $el = $('#note_deadline');
    if (!$el.length) {
        return;
    }
    // Prefer native _flatpickr / crmFlatpickr — never trust .data('flatpickr')
    // when the input has data-flatpickr="standard" (jQuery returns that string).
    var fp = (typeof window.crmGetFlatpickrInstance === 'function')
        ? window.crmGetFlatpickrInstance($el)
        : (($el[0] && $el[0]._flatpickr) || null);

    if (!fp && typeof CRM_Flatpickr !== 'undefined') {
        CRM_Flatpickr.initStandard($el);
        fp = (typeof window.crmGetFlatpickrInstance === 'function')
            ? window.crmGetFlatpickrInstance($el)
            : (($el[0] && $el[0]._flatpickr) || null);
    }

    if (fp && typeof fp.setDate === 'function') {
        if (display) {
            fp.setDate(display, true);
        } else {
            fp.clear();
        }
        return;
    }
    $el.val(display);
}

function formatDate(dateString) {
    const date = new Date(dateString);
    const today = new Date();
    const tomorrow = new Date(today);
    tomorrow.setDate(tomorrow.getDate() + 1);
    
    // Reset time to compare dates only
    date.setHours(0, 0, 0, 0);
    today.setHours(0, 0, 0, 0);
    tomorrow.setHours(0, 0, 0, 0);
    
    if (date.getTime() === today.getTime()) {
        return 'Today';
    } else if (date.getTime() === tomorrow.getTime()) {
        return 'Tomorrow';
    } else {
        const options = { weekday: 'short', month: 'short', day: 'numeric' };
        return date.toLocaleDateString('en-US', options);
    }
}

// Close panel on ESC key
$(document).on('keydown', function(e) {
    if (e.key === 'Escape') {
        closeTaskDetail();
    }
});

// Keyboard: open task detail from focused row content (Enter / Space)
$(document).on('keydown', '.todo-task-content[role="button"]', function (e) {
    if (e.key !== 'Enter' && e.key !== ' ') {
        return;
    }
    e.preventDefault();
    var taskId = $(this).data('task-id') || $(this).closest('.todo-task-item').data('task-id');
    if (taskId && typeof window.openTaskDetail === 'function') {
        window.openTaskDetail(taskId);
    }
});

// Prevent checkbox label from opening detail
$(document).on('click', '.task-detail-checkbox', function(e) {
    e.stopPropagation();
});

/**
 * Dashboard Quick Tasks Modal (#create_task_modal / #tasktermform)
 */
function initDashboardQuickTaskModal() {
    // Filter staff members by search query in modal
    $(document).off('input.dashboardTask', '#dashboard-staff-search').on('input.dashboardTask', '#dashboard-staff-search', function(e) {
        e.stopPropagation();
        var query = $(this).val().toLowerCase().trim();
        $('#dashboard-staff-list .modern-staff-item').each(function() {
            var itemText = ($(this).data('name') || $(this).text()).toLowerCase();
            if (!query || itemText.indexOf(query) > -1) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    });

    // Select all staff
    $(document).off('click.dashboardTask', '#dashboard-select-all-staff').on('click.dashboardTask', '#dashboard-select-all-staff', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $('#dashboard-staff-list .modern-staff-item:visible .dashboard-checkbox-item').prop('checked', true);
        syncDashboardQuickTaskAssignees();
    });

    // Select none staff
    $(document).off('click.dashboardTask', '#dashboard-select-none-staff').on('click.dashboardTask', '#dashboard-select-none-staff', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $('#dashboard-staff-list .dashboard-checkbox-item').prop('checked', false);
        syncDashboardQuickTaskAssignees();
    });

    // Individual staff checkbox change
    $(document).off('change.dashboardTask', '.dashboard-checkbox-item').on('change.dashboardTask', '.dashboard-checkbox-item', function() {
        syncDashboardQuickTaskAssignees();
    });

    // Toggle deadline date field enabled / disabled
    $(document).off('change.dashboardTask', '#dashboard_note_deadline_checkbox').on('change.dashboardTask', '#dashboard_note_deadline_checkbox', function() {
        var isChecked = $(this).is(':checked');
        $('#dashboard_note_deadline').prop('disabled', !isChecked);
        $(this).val(isChecked ? '1' : '');
    });

    // Clear validation error message on input change
    $(document).off('change.dashboardTask input.dashboardTask', '#dashboard_client_select, #dashboard_assignnote, #dashboard_popoverdatetime, #dashboard_task_group').on('change.dashboardTask input.dashboardTask', '#dashboard_client_select, #dashboard_assignnote, #dashboard_popoverdatetime, #dashboard_task_group', function() {
        var $form = $(this).closest('#tasktermform');
        if ($(this).attr('id') === 'dashboard_client_select') {
            $form.find('.client_error').hide().text('');
        } else if ($(this).attr('id') === 'dashboard_assignnote') {
            $form.find('.note_error').hide().text('');
        } else if ($(this).attr('id') === 'dashboard_popoverdatetime') {
            $form.find('.date_error').hide().text('');
        } else if ($(this).attr('id') === 'dashboard_task_group') {
            $form.find('.group_error').hide().text('');
        }
    });

    // Reset validation errors when modal is opened
    $(document).off('show.bs.modal.dashboardTask', '#create_task_modal, #taskterm').on('show.bs.modal.dashboardTask', '#create_task_modal, #taskterm', function() {
        var $form = $('#tasktermform');
        if ($form.length) {
            $form.find('.custom-error').hide().text('');
        }
    });

    // Form submission prevention & delegation
    $(document).off('submit.dashboardTask', '#tasktermform').on('submit.dashboardTask', '#tasktermform', function(e) {
        e.preventDefault();
        $('#dashboard_assignStaff, #dashboard_assignUser').trigger('click');
    });

    // Submit button click handler
    $(document).off('click.dashboardTask', '#dashboard_assignStaff, #dashboard_assignUser').on('click.dashboardTask', '#dashboard_assignStaff, #dashboard_assignUser', function(e) {
        e.preventDefault();
        submitDashboardQuickTask($(this));
    });
}

function syncDashboardQuickTaskAssignees() {
    var checkedBoxes = $('#dashboard-staff-list .dashboard-checkbox-item:checked');
    var selectedVals = [];
    var names = [];
    checkedBoxes.each(function() {
        selectedVals.push($(this).val());
        var name = $(this).closest('.modern-staff-item').find('.staff-name').text().trim() || $(this).data('name') || '';
        if (name) {
            names.push(name);
        }
    });

    $('#dashboard_rem_cat').val(selectedVals);

    var $label = $('#dashboard-selected-users-text');
    var $btn = $('#dashboard_dropdownMenuButton');
    if (selectedVals.length === 0) {
        $label.text('SELECT ASSIGNEES');
        $btn.removeClass('has-selection');
    } else if (selectedVals.length === 1) {
        $label.text(names[0] || '1 Staff Selected');
        $btn.addClass('has-selection');
    } else {
        $label.text(selectedVals.length + ' Staff Selected');
        $btn.addClass('has-selection');
    }

    if (selectedVals.length > 0) {
        $('#tasktermform .assignee_error').hide().text('');
    }
}

function submitDashboardQuickTask($btn) {
    var $form = $('#tasktermform');
    if (!$form.length) {
        return;
    }

    // Reset error spans
    $form.find('.custom-error').hide().text('');

    var isValid = true;
    var $firstInvalid = null;

    // Validate client
    var clientId = $('#dashboard_client_select').val();
    if (!clientId) {
        $form.find('.client_error').text('Please select a client or lead.').show();
        isValid = false;
        if (!$firstInvalid) $firstInvalid = $('#dashboard_client_select');
    }

    // Validate assignees
    var assignees = $('#dashboard_rem_cat').val() || [];
    if (!Array.isArray(assignees)) {
        assignees = assignees ? [assignees] : [];
    }
    if (assignees.length === 0) {
        $('#dashboard-staff-list .dashboard-checkbox-item:checked').each(function() {
            assignees.push($(this).val());
        });
        $('#dashboard_rem_cat').val(assignees);
    }
    if (assignees.length === 0) {
        $form.find('.assignee_error').text('Please select at least one assignee.').show();
        isValid = false;
        if (!$firstInvalid) $firstInvalid = $('#dashboard_dropdownMenuButton');
    }

    // Validate description
    var description = $('#dashboard_assignnote').val();
    if (!description || !description.trim()) {
        $form.find('.note_error').text('Task description is required.').show();
        isValid = false;
        if (!$firstInvalid) $firstInvalid = $('#dashboard_assignnote');
    }

    // Validate date
    var followupDate = $('#dashboard_popoverdatetime').val();
    if (!followupDate) {
        $form.find('.date_error').text('Date is required.').show();
        isValid = false;
        if (!$firstInvalid) $firstInvalid = $('#dashboard_popoverdatetime');
    }

    // Validate group
    var taskGroup = $('#dashboard_task_group').val();
    if (!taskGroup) {
        $form.find('.group_error').text('Please select a group.').show();
        isValid = false;
        if (!$firstInvalid) $firstInvalid = $('#dashboard_task_group');
    }

    if (!isValid) {
        if ($firstInvalid) {
            $firstInvalid.focus();
        }
        return false;
    }

    var storeUrl = (window.dashboardRoutes && (window.dashboardRoutes.storeTask || window.dashboardRoutes.storePersonalTask))
        || '/clients/tasks/personal/store';

    var originalHtml = $btn.html();
    $btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> SAVING...');

    var formData = $form.serialize();

    $.ajax({
        url: storeUrl,
        type: 'POST',
        data: formData,
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        dataType: 'json',
        success: function(response) {
            $btn.prop('disabled', false).html(originalHtml);

            if (response && (response.success || response.status)) {
                $('#create_task_modal, #taskterm').modal('hide');

                $form[0].reset();
                $('#dashboard-staff-list .dashboard-checkbox-item').prop('checked', false);
                syncDashboardQuickTaskAssignees();
                $('#dashboard_note_deadline').prop('disabled', true);

                var successMsg = response.message || 'Task created successfully!';
                if (typeof iziToast !== 'undefined') {
                    iziToast.success({
                        title: 'Success',
                        message: successMsg,
                        position: 'topRight'
                    });
                } else if (typeof showNotification === 'function') {
                    showNotification(successMsg, 'success');
                } else if (typeof window.crmToast === 'function') {
                    window.crmToast(successMsg, 'success');
                }

                if (typeof window.refreshDashboard === 'function') {
                    setTimeout(function() {
                        window.refreshDashboard();
                    }, 400);
                } else {
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                }
            } else {
                var err = (response && response.message) || 'Failed to create task.';
                if (typeof iziToast !== 'undefined') {
                    iziToast.error({ title: 'Error', message: err, position: 'topRight' });
                } else if (typeof showNotification === 'function') {
                    showNotification(err, 'error');
                } else {
                    alert(err);
                }
            }
        },
        error: function(xhr) {
            $btn.prop('disabled', false).html(originalHtml);

            var errorMsg = 'Failed to create task.';
            if (xhr && xhr.responseJSON) {
                if (xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                } else if (xhr.responseJSON.errors) {
                    errorMsg = Object.values(xhr.responseJSON.errors).flat().join(' ');
                }
            }

            if (typeof iziToast !== 'undefined') {
                iziToast.error({ title: 'Error', message: errorMsg, position: 'topRight' });
            } else if (typeof showNotification === 'function') {
                showNotification(errorMsg, 'error');
            } else {
                alert(errorMsg);
            }
        }
    });
}