/**
 * Matter-scoped task list on client detail Tasks tab.
 * Requires: jQuery, ClientDetailConfig.urls.matterTask*, clientId, clientMatterId, csrfToken
 */
(function ($) {
    'use strict';
    if (!$) {
        return;
    }
    // Script can be included via SSR active-tab AND ClientTabLazy.ensureTabScripts —
    // without this guard, delegated click handlers stack and each Add creates N tasks.
    if (window.__cdnMatterTasksModuleBound) {
        return;
    }
    window.__cdnMatterTasksModuleBound = true;

    var reloadTimer = null;
    var pendingDelete = null;
    var DONE_PANEL_KEY = 'cdn-matter-tasks-done-open';
    var EVT = '.cdnMatterTasks';
    var tasksPage = 1;
    var tasksHasMore = false;
    var tasksLoading = false;
    var taskAddInFlight = false;
    var tasksRows = [];
    var reminderRows = [];
    var tasksOpenCount = 0;
    var tasksDoneCount = 0;
    var composerKind = 'task';

    function cfg() {
        return window.ClientDetailConfig || {};
    }

    function urlMap() {
        return cfg().urls || {};
    }

    function canUsePersonalCalendar() {
        return cfg().urls && cfg().urls.personalCalendarEnabled === true;
    }

    function getComposerKind() {
        var $composer = $('#cdn-matter-tasks .cdn-matter-task-composer');
        var kind = ($composer.attr('data-composer-kind') || composerKind || 'task').toLowerCase();
        return kind === 'reminder' ? 'reminder' : 'task';
    }

    function setComposerKind(kind) {
        kind = kind === 'reminder' ? 'reminder' : 'task';
        if (kind === 'reminder' && !canUsePersonalCalendar()) {
            notifyError('Personal calendar access has not been granted. Ask a Super Admin to enable it on your staff profile.');
            kind = 'task';
        }
        composerKind = kind;
        var $composer = $('#cdn-matter-tasks .cdn-matter-task-composer');
        $composer.attr('data-composer-kind', kind);
        $composer.find('.cdn-matter-task-composer__kind').each(function () {
            var isActive = String($(this).data('kind')) === kind;
            $(this).toggleClass('is-active', isActive).attr('aria-pressed', isActive ? 'true' : 'false');
        });
        var $inp = $('#cdn-matter-task-title');
        var $due = $('#cdn-matter-task-due');
        var $dueLabel = $('#cdn-matter-task-due-label');
        var $addLabel = $('#cdn-matter-task-add-label');
        if (kind === 'reminder') {
            $inp.attr('placeholder', 'Add a reminder…');
            $due.attr('placeholder', 'Remind on').attr('aria-label', 'Remind on');
            if ($dueLabel.length) {
                $dueLabel.text('Remind on');
            }
            if ($addLabel.length) {
                $addLabel.text('Add reminder');
            }
        } else {
            $inp.attr('placeholder', 'Add a task…');
            $due.attr('placeholder', 'Due date').attr('aria-label', 'Due date');
            if ($dueLabel.length) {
                $dueLabel.text('Due date');
            }
            if ($addLabel.length) {
                $addLabel.text('Add task');
            }
        }
        var $hint = $('#cdn-matter-task-composer-hint');
        if ($hint.length) {
            if (kind === 'reminder') {
                $hint.html(
                    '<i class="fa-solid fa-bell" aria-hidden="true"></i> <strong>Reminder</strong> — saved to your personal calendar (My Calendar). A date is required.'
                );
            } else if (!(matterId() || matterRef())) {
                $hint.html(
                    '<strong>Task</strong> needs a matter. Switch to <strong>Reminder</strong> to add something on your calendar for this lead/client.'
                );
            } else {
                $hint.html(
                    '<strong>Task</strong> — stays on this matter. Tick the checkbox when done.'
                );
            }
            $hint.toggleClass('is-reminder', kind === 'reminder');
        }
        $composer.toggleClass('is-reminder-mode', kind === 'reminder');
        var fp = getDueFlatpickr($due);
        if (fp && fp.altInput) {
            $(fp.altInput).attr('placeholder', kind === 'reminder' ? 'Remind on' : 'Due date');
        }
        syncComposerLock();
    }

    function clientId() {
        var id = cfg().clientId;
        return id !== undefined && id !== null && String(id) !== '' ? String(id) : null;
    }

    function matterRef() {
        var shared = window.ClientDetailShared;
        if (shared && typeof shared.parseClientDetailMatterRefFromUrl === 'function') {
            var fromUrl = shared.parseClientDetailMatterRefFromUrl();
            if (fromUrl) {
                return String(fromUrl).trim();
            }
        }
        var ref = cfg().matterRefNo || cfg().matterId || '';
        ref = ref == null ? '' : String(ref).trim();
        if (!ref) {
            return null;
        }
        if (shared && typeof shared.isClientDetailTabSlug === 'function' && shared.isClientDetailTabSlug(ref)) {
            return null;
        }
        return ref;
    }

    function matterIdFromSelectByRef(ref) {
        if (!ref) {
            return null;
        }
        var found = null;
        $('#sel_matter_id_client_detail option').each(function () {
            var optRef = $(this).attr('data-clientuniquematterno') || $(this).data('clientuniquematterno');
            if (optRef != null && String(optRef) === String(ref)) {
                found = safeId($(this).val());
                return false;
            }
        });
        return found;
    }

    function matterId() {
        // Prefer URL/server context so Tasks stay on the matter in the path (e.g. FAM_1).
        var fromConfig = safeId(cfg().clientMatterId);
        if (fromConfig) {
            return fromConfig;
        }

        var shared = window.ClientDetailShared;
        if (shared && typeof shared.getSelectedClientDetailMatterId === 'function') {
            var selectedId = safeId(shared.getSelectedClientDetailMatterId());
            if (selectedId) {
                return selectedId;
            }
        }

        var selectVal = safeId($('#sel_matter_id_client_detail').val());
        if (selectVal) {
            return selectVal;
        }

        return matterIdFromSelectByRef(matterRef());
    }

    function csrf() {
        return cfg().csrfToken || $('meta[name="csrf-token"]').attr('content') || '';
    }

    function taskBase() {
        return urlMap().matterTaskBase || '';
    }

    function actionPageUrl(noteId) {
        var base = urlMap().assigneeAction || '';
        if (!base) {
            return '';
        }
        var nid = safeId(noteId);
        if (!nid) {
            return base;
        }
        return base + (base.indexOf('?') >= 0 ? '&' : '?') + 'note_id=' + nid;
    }

    function notifyError(message) {
        var msg = message || 'Something went wrong. Please try again.';
        if (typeof window.crmNotify !== 'undefined' && typeof window.crmNotify.error === 'function') {
            window.crmNotify.error({ message: msg });
        } else if (typeof iziToast !== 'undefined' && typeof iziToast.error === 'function') {
            iziToast.error({ message: msg, position: 'topRight' });
        } else {
            crmAlert(msg);
        }
    }

    function isTaskDone(it) {
        return it.is_done === true || it.is_done === 1 || it.is_done === '1';
    }

    function safeId(raw) {
        var n = parseInt(raw, 10);
        return n > 0 ? n : null;
    }

    function esc(text) {
        return $('<div>').text(text == null ? '' : String(text)).html();
    }

    function getDueFlatpickr($due) {
        if (!$due || !$due.length) {
            return null;
        }
        var el = $due[0];
        var fp = $due.data('flatpickr') || (el && el._flatpickr) || null;
        if (fp && !$due.data('flatpickr')) {
            $due.data('flatpickr', fp);
        }
        return fp;
    }

    function syncComposerLock() {
        var $wrap = $('#cdn-matter-tasks');
        if (!$wrap.length) {
            return;
        }
        var $inp = $('#cdn-matter-task-title');
        var $due = $('#cdn-matter-task-due');
        var $btn = $('#cdn-matter-task-add');
        var $reminderKind = $('#cdn-matter-task-kind-reminder');
        if (!$inp.length || !$btn.length) {
            return;
        }
        var hasMatter = !!(matterId() || matterRef());
        var isReminderKind = getComposerKind() === 'reminder';
        var calendarOk = canUsePersonalCalendar();
        // Reminders can be added on leads/clients without a matter; tasks still need a matter.
        var unlocked = !!(clientId() && (hasMatter || (isReminderKind && calendarOk)));
        var busy = !!taskAddInFlight;
        $inp.prop('disabled', !unlocked || busy);
        $due.prop('disabled', !unlocked || busy);
        var fp = getDueFlatpickr($due);
        if (fp) {
            if (!unlocked || busy) {
                if (typeof fp.close === 'function') {
                    fp.close();
                }
                if (fp.altInput) {
                    fp.altInput.disabled = true;
                }
            } else if (fp.altInput) {
                fp.altInput.disabled = false;
            }
        }
        $btn.prop('disabled', !unlocked || busy);
        if ($reminderKind.length) {
            $reminderKind.prop('disabled', !calendarOk);
            $reminderKind.toggleClass('is-locked', !calendarOk);
            $reminderKind.attr(
                'title',
                calendarOk
                    ? 'Add a reminder to your personal calendar (My Calendar)'
                    : 'Ask a Super Admin to grant personal calendar access'
            );
            if (!calendarOk && getComposerKind() === 'reminder') {
                setComposerKind('task');
            }
        }
        var cid = clientId();
        var mid = matterId();
        var ref = matterRef();
        var storeUrl = urlMap().matterTaskStore;
        // Keep the composer interactive when reminders are allowed without a matter,
        // so the Reminder card remains clickable even while Task fields stay disabled.
        var unlockedClass = !!(cid && storeUrl && (mid || ref || calendarOk));
        $wrap.toggleClass('cdn-matter-tasks--locked', !unlockedClass);
        $wrap.toggleClass('cdn-matter-tasks--busy', busy);
        $wrap.toggleClass('cdn-matter-tasks--needs-matter', !!(cid && !mid && !ref && !isReminderKind));
    }

    function clearDueDateInput() {
        var $due = $('#cdn-matter-task-due');
        var fp = getDueFlatpickr($due);
        clearFieldInvalid($due);
        if (fp && fp.altInput) {
            clearFieldInvalid($(fp.altInput));
        }
        if (fp && typeof fp.clear === 'function') {
            fp.clear();
            return;
        }
        $due.val('');
    }

    function markFieldInvalid($el) {
        if ($el && $el.length) {
            $el.addClass('is-invalid');
        }
    }

    function clearFieldInvalid($el) {
        if ($el && $el.length) {
            $el.removeClass('is-invalid');
        }
    }

    function isValidCalendarDate(year, month, day) {
        if (month < 1 || month > 12 || day < 1 || day > 31 || year < 1900 || year > 2200) {
            return false;
        }
        var dt = new Date(year, month - 1, day);
        return dt.getFullYear() === year && dt.getMonth() === month - 1 && dt.getDate() === day;
    }

    function pad2(n) {
        return (n < 10 ? '0' : '') + n;
    }

    /**
     * Parse typed due date (optional). Empty is allowed.
     * Accepts d/m/Y, d-m-Y, or Y-m-d.
     * @returns {{ok:boolean, empty?:boolean, value?:string, message?:string}}
     */
    function parseDueDateInput(raw) {
        var text = (raw == null ? '' : String(raw)).trim();
        if (!text) {
            return { ok: true, empty: true };
        }

        var iso = text.match(/^(\d{4})-(\d{1,2})-(\d{1,2})$/);
        if (iso) {
            var yIso = parseInt(iso[1], 10);
            var mIso = parseInt(iso[2], 10);
            var dIso = parseInt(iso[3], 10);
            if (!isValidCalendarDate(yIso, mIso, dIso)) {
                return { ok: false, message: 'Please enter a valid due date (DD/MM/YYYY).' };
            }
            return { ok: true, value: yIso + '-' + pad2(mIso) + '-' + pad2(dIso) };
        }

        var dmy = text.match(/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/);
        if (dmy) {
            var d = parseInt(dmy[1], 10);
            var m = parseInt(dmy[2], 10);
            var y = parseInt(dmy[3], 10);
            if (!isValidCalendarDate(y, m, d)) {
                return { ok: false, message: 'Please enter a valid due date (DD/MM/YYYY).' };
            }
            return { ok: true, value: y + '-' + pad2(m) + '-' + pad2(d) };
        }

        return { ok: false, message: 'Please enter a valid due date (DD/MM/YYYY).' };
    }

    function getDueDateTypedValue($due) {
        var fp = getDueFlatpickr($due);
        if (fp && fp.altInput) {
            return (fp.altInput.value || '').trim();
        }
        return ($due.val() || '').trim();
    }

    function validateTaskTitle($inp) {
        var title = ($inp.val() || '').trim();
        clearFieldInvalid($inp);
        if (!title) {
            markFieldInvalid($inp);
            notifyError('Please enter a task before adding.');
            $inp.trigger('focus');
            return null;
        }
        return title;
    }

    /**
     * Validate optional due date. Returns Y-m-d string, '' if empty, or null if invalid.
     */
    function validateTaskDueDate($due) {
        var fp = getDueFlatpickr($due);
        var $visible = fp && fp.altInput ? $(fp.altInput) : $due;
        clearFieldInvalid($due);
        clearFieldInvalid($visible);

        var typed = getDueDateTypedValue($due);
        var parsed = parseDueDateInput(typed);
        if (!parsed.ok) {
            markFieldInvalid($visible);
            notifyError(parsed.message || 'Please enter a valid due date.');
            $visible.trigger('focus');
            return null;
        }
        if (parsed.empty) {
            if (fp && typeof fp.clear === 'function') {
                fp.clear();
            } else {
                $due.val('');
            }
            return '';
        }

        if (fp && typeof fp.setDate === 'function') {
            try {
                // Input shows d/m/Y; selectedDates still resolve from parsed Y-m-d
                fp.setDate(parsed.value, true, 'Y-m-d');
            } catch (e) {
                markFieldInvalid($visible);
                notifyError('Please enter a valid due date (DD/MM/YYYY).');
                $visible.trigger('focus');
                return null;
            }
        } else {
            $due.val(parsed.value);
        }

        return parsed.value;
    }

    function destroyDueDatePicker($due) {
        var fp = getDueFlatpickr($due);
        if (fp && typeof fp.destroy === 'function') {
            try {
                fp.destroy();
            } catch (e) {
                /* ignore */
            }
        }
        if ($due && $due.length) {
            $due.removeData('flatpickr');
            if ($due[0]) {
                delete $due[0]._flatpickr;
            }
        }
    }

    function initDueDatePicker(force) {
        var el = document.getElementById('cdn-matter-task-due');
        if (!el) {
            return false;
        }
        if (typeof flatpickr === 'undefined') {
            return false;
        }
        var $due = $(el);
        var existing = getDueFlatpickr($due);
        if (existing && !force) {
            // Instance exists but may be detached after lazy-tab HTML replace
            if (existing.input && document.body.contains(existing.input)) {
                return true;
            }
            destroyDueDatePicker($due);
        } else if (existing && force) {
            destroyDueDatePicker($due);
        }

        var fp = flatpickr(el, {
            // Single visible input (d/m/Y) — avoids altInput + hidden field CSS races
            dateFormat: 'd/m/Y',
            allowInput: true,
            clickOpens: true,
            closeOnSelect: true,
            disableMobile: true,
            monthSelectorType: 'static',
            appendTo: document.body,
            locale: {
                firstDayOfWeek: 1
            },
            onReady: function (selectedDates, dateStr, instance) {
                if (instance.calendarContainer) {
                    instance.calendarContainer.classList.add('cdn-matter-task-due-calendar');
                }
                $(instance.input)
                    .off('blur.matterTaskDue input.matterTaskDue')
                    .on('blur.matterTaskDue', function () {
                        var typed = (instance.input.value || '').trim();
                        if (!typed) {
                            clearFieldInvalid($(instance.input));
                            instance.clear();
                            return;
                        }
                        var parsed = parseDueDateInput(typed);
                        if (!parsed.ok) {
                            markFieldInvalid($(instance.input));
                            return;
                        }
                        clearFieldInvalid($(instance.input));
                        instance.setDate(parsed.value, true, 'Y-m-d');
                        instance.close();
                    })
                    .on('input.matterTaskDue', function () {
                        clearFieldInvalid($(instance.input));
                    });
            },
            onChange: function (selectedDates, dateStr, instance) {
                clearFieldInvalid($due);
                // Ensure popup hides after picking a day (do not leave sticky inline styles)
                if (selectedDates && selectedDates.length && instance && typeof instance.close === 'function') {
                    instance.close();
                }
            },
            onOpen: function (selectedDates, dateStr, instance) {
                if (!instance.calendarContainer) {
                    return;
                }
                instance.calendarContainer.classList.add('cdn-matter-task-due-calendar');
                // Only z-index — never set display/visibility/opacity inline or closeOnSelect cannot hide the calendar
                instance.calendarContainer.style.zIndex = '100000';
            },
            onClose: function (selectedDates, dateStr, instance) {
                if (!instance.calendarContainer) {
                    return;
                }
                // Clear any leftover visibility overrides so Flatpickr CSS can hide the popup
                instance.calendarContainer.style.removeProperty('display');
                instance.calendarContainer.style.removeProperty('visibility');
                instance.calendarContainer.style.removeProperty('opacity');
            }
        });

        $due.data('flatpickr', fp);
        syncComposerLock();
        return true;
    }

    function ensureDueDatePicker() {
        if (initDueDatePicker(false)) {
            return;
        }
        // Flatpickr or tab HTML may not be ready yet (lazy tab / script order)
        var attempts = 0;
        var timer = window.setInterval(function () {
            attempts += 1;
            if (initDueDatePicker(false) || attempts >= 20) {
                window.clearInterval(timer);
            }
        }, 100);
    }

    function setBusy(busy) {
        var $wrap = $('#cdn-matter-tasks');
        $wrap.toggleClass('cdn-matter-tasks--busy', !!busy);
        $wrap.find('.cdn-matter-task__cb, .cdn-matter-task__del').prop('disabled', !!busy);
        $wrap
            .find('.cdn-matter-task__action-link')
            .toggleClass('cdn-matter-task__action-link--disabled', !!busy)
            .attr('aria-disabled', busy ? 'true' : 'false');
        syncComposerLock();
    }

    function statusBlock(kind, html) {
        var cls = 'cdn-matter-task__status';
        if (kind === 'error') {
            cls += ' cdn-matter-task__status--error';
        } else if (kind === 'empty') {
            cls += ' cdn-matter-task__status--empty';
        } else {
            cls += ' cdn-matter-task__status--muted';
        }
        return '<div class="' + cls + '">' + html + '</div>';
    }

    function skeletonHtml() {
        var rows = '';
        for (var i = 0; i < 3; i++) {
            rows +=
                '<div class="cdn-matter-task__skeleton-row">' +
                '<span class="cdn-matter-task__skeleton-box"></span>' +
                '<span class="cdn-matter-task__skeleton-line"></span>' +
                '</div>';
        }
        return (
            '<div class="cdn-matter-task__skeleton" aria-busy="true" aria-label="Loading tasks">' +
            rows +
            '</div>'
        );
    }

    function formatCreatedAt(raw) {
        if (!raw) {
            return '';
        }
        var d = new Date(raw);
        if (isNaN(d.getTime())) {
            return '';
        }
        try {
            return d.toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' });
        } catch (e) {
            return raw.split('T')[0] || raw;
        }
    }

    function parseDateOnly(raw) {
        if (!raw) {
            return null;
        }
        var s = String(raw).trim();
        // Prefer calendar Y-m-d (API now returns date:Y-m-d). Avoid UTC shift from ISO midnights.
        var m = s.match(/^(\d{4})-(\d{2})-(\d{2})/);
        if (m) {
            return new Date(parseInt(m[1], 10), parseInt(m[2], 10) - 1, parseInt(m[3], 10));
        }
        var d = new Date(s);
        return isNaN(d.getTime()) ? null : d;
    }

    function formatDueDate(raw) {
        var d = parseDateOnly(raw);
        if (!d) {
            return '';
        }
        try {
            return d.toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' });
        } catch (e) {
            return String(raw).split('T')[0] || String(raw);
        }
    }

    function isDueDateOverdue(raw, done) {
        if (done || !raw) {
            return false;
        }
        var due = parseDateOnly(raw);
        if (!due) {
            return false;
        }
        var today = new Date();
        today.setHours(0, 0, 0, 0);
        due.setHours(0, 0, 0, 0);
        return due.getTime() < today.getTime();
    }

    function creatorName(it) {
        var c = it && it.creator;
        if (!c) {
            return '';
        }
        var name = ((c.first_name || '') + ' ' + (c.last_name || '')).trim();
        return name;
    }

    function assigneeName(it) {
        var a = it && it.assignee;
        if (!a) {
            return '';
        }
        return ((a.first_name || '') + ' ' + (a.last_name || '')).trim();
    }

    function rowMetaHtml(it) {
        var parts = [];
        var creator = creatorName(it);
        if (creator) {
            parts.push('Added by ' + esc(creator));
        }
        var assignee = assigneeName(it);
        if (assignee && assignee !== creator) {
            parts.push('Assigned to ' + esc(assignee));
        }
        var when = formatCreatedAt(it.created_at);
        if (when) {
            parts.push(esc(when));
        }
        var isReminder = it && it.item_kind === 'reminder';
        var dueLabel = formatDueDate(it.due_date);
        var dueHtml = '';
        if (dueLabel) {
            var overdue = isDueDateOverdue(it.due_date, isTaskDone(it));
            if (isReminder) {
                dueHtml =
                    '<span class="cdn-matter-task__remind"><i class="fa-regular fa-bell cdn-matter-task__due-icon" aria-hidden="true"></i>Remind ' +
                    esc(dueLabel) +
                    '</span>';
            } else if (overdue) {
                dueHtml =
                    '<span class="cdn-matter-task__due is-overdue" title="Past due"><i class="fa-solid fa-triangle-exclamation cdn-matter-task__due-icon" aria-hidden="true"></i>Overdue · ' +
                    esc(dueLabel) +
                    '</span>';
            } else {
                dueHtml =
                    '<span class="cdn-matter-task__due"><i class="fa-regular fa-calendar cdn-matter-task__due-icon" aria-hidden="true"></i>Due ' +
                    esc(dueLabel) +
                    '</span>';
            }
        }
        var metaBits = parts.length ? '<div class="cdn-matter-task__meta-line">' + parts.join(' · ') + '</div>' : '';
        if (!metaBits && !dueHtml) {
            return '';
        }
        return (
            '<div class="cdn-matter-task__meta">' +
            metaBits +
            (dueHtml ? '<div class="cdn-matter-task__meta-due">' + dueHtml + '</div>' : '') +
            '</div>'
        );
    }

    function isReminderDone(it) {
        if (!it) {
            return false;
        }
        if (it.is_done === true || it.is_done === 1 || it.is_done === '1') {
            return true;
        }
        var status = String(it.status || '')
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '_');
        return status === 'completed';
    }

    function activeReminderCount() {
        var count = 0;
        for (var i = 0; i < reminderRows.length; i++) {
            if (!isReminderDone(reminderRows[i])) {
                count += 1;
            }
        }
        return count;
    }

    function buildReminderRowHtml(it) {
        var rowId = safeId(it.id);
        if (!rowId) {
            return '';
        }
        var done = isReminderDone(it);
        var title = esc(it.title || '');
        var calendarHref = urlMap().personalCalendar || urlMap().dashboardCalendar || '';
        if (calendarHref && it && it.due_date) {
            calendarHref +=
                (calendarHref.indexOf('?') >= 0 ? '&' : '?') +
                'date=' +
                encodeURIComponent(String(it.due_date));
        }
        var html =
            '<li class="cdn-matter-task__row is-reminder' +
            (done ? ' is-done-row' : '') +
            '" data-id="' +
            rowId +
            '" data-item-kind="reminder" data-reminder-status="' +
            esc(String(it.status || (done ? 'completed' : 'scheduled'))) +
            '">';
        html += '<div class="cdn-matter-task__row-main">';
        html += '<div class="cdn-matter-task__text">';
        html +=
            '<div class="cdn-matter-task__title-row">' +
            '<div class="cdn-matter-task__label' +
            (done ? ' is-done' : '') +
            '">' +
            title +
            '<span class="cdn-matter-task__badge"><i class="fa-regular fa-bell" aria-hidden="true"></i> Reminder</span></div>' +
            '<span class="cdn-matter-task__status-chip' +
            (done ? ' is-done' : ' is-open') +
            '">' +
            (done ? 'Done' : 'Open') +
            '</span></div>';
        html += rowMetaHtml(it);
        html += '</div>';
        html += '</div>';
        html += '<div class="cdn-matter-task__actions">';
        if (calendarHref && !done) {
            html +=
                '<a class="cdn-matter-task__action-link" href="' +
                esc(calendarHref) +
                '" title="Open this reminder on My Calendar" aria-label="Open this reminder on My Calendar"><i class="fa-solid fa-calendar-days" aria-hidden="true"></i><span class="cdn-matter-task__action-link-text">My Calendar</span></a>';
        }
        html +=
            '<button type="button" class="cdn-matter-task__del" title="Delete reminder" aria-label="Delete reminder"><i class="fa-solid fa-trash-can" aria-hidden="true"></i></button>';
        html += '</div>';
        html += '</li>';
        return html;
    }

    function buildRowHtml(it) {
        if (it && it.item_kind === 'reminder') {
            return buildReminderRowHtml(it);
        }
        var rowId = safeId(it.id);
        if (!rowId) {
            return '';
        }
        var done = isTaskDone(it);
        var title = esc(it.title || '');
        var cbId = 'cdn-mtask-' + rowId;
        var noteId = safeId(it.note_id);
        var actionHref = urlMap().assigneeAction ? esc(actionPageUrl(noteId)) : '';

        var html = '<li class="cdn-matter-task__row' + (done ? ' is-done-row' : '') + '" data-id="' + rowId + '" data-item-kind="task"';
        if (noteId) {
            html += ' data-note-id="' + noteId + '"';
        }
        html += '>';
        html += '<div class="cdn-matter-task__row-main">';
        html += '<input type="checkbox" class="cdn-matter-task__cb" id="' + cbId + '"' + (done ? ' checked' : '') + ' />';
        html += '<div class="cdn-matter-task__text">';
        html +=
            '<div class="cdn-matter-task__title-row">' +
            '<label class="cdn-matter-task__label' +
            (done ? ' is-done' : '') +
            '" for="' +
            cbId +
            '">' +
            title +
            '</label>' +
            '<span class="cdn-matter-task__status-chip' +
            (done ? ' is-done' : ' is-open') +
            '">' +
            (done ? 'Done' : 'Open') +
            '</span></div>';
        html += rowMetaHtml(it);
        html += '</div>';
        html += '</div>';
        html += '<div class="cdn-matter-task__actions">';
        if (actionHref) {
            html +=
                '<a class="cdn-matter-task__action-link" href="' +
                actionHref +
                '" title="Open this task on your My Tasks page" aria-label="Open this task on your My Tasks page" target="_blank" rel="noopener"><i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i><span class="cdn-matter-task__action-link-text">My Tasks</span></a>';
        }
        html +=
            '<button type="button" class="cdn-matter-task__del" title="Delete task" aria-label="Delete task"><i class="fa-solid fa-trash-can" aria-hidden="true"></i></button>';
        html += '</div>';
        html += '</li>';
        return html;
    }

    function updateStats(openCount, doneCount, reminderCount) {
        var $stats = $('#cdn-matter-task-stats');
        if (!$stats.length) {
            return;
        }
        reminderCount = typeof reminderCount === 'number' ? reminderCount : activeReminderCount();
        var total = openCount + doneCount + reminderCount;
        if (total === 0) {
            $stats.text('');
            return;
        }
        var parts = [];
        if (openCount > 0) {
            parts.push(openCount + ' open');
        }
        if (doneCount > 0) {
            parts.push(doneCount + ' done');
        }
        if (reminderCount > 0) {
            parts.push(reminderCount + ' reminder' + (reminderCount === 1 ? '' : 's'));
        }
        $stats.text(parts.join(' · '));
    }

    function renderList(rows, stats) {
        var open = [];
        var done = [];
        for (var i = 0; i < rows.length; i++) {
            if (isTaskDone(rows[i])) {
                done.push(rows[i]);
            } else {
                open.push(rows[i]);
            }
        }

        var activeReminders = [];
        var doneReminders = [];
        var allReminders = reminderRows || [];
        for (var ri = 0; ri < allReminders.length; ri++) {
            if (isReminderDone(allReminders[ri])) {
                doneReminders.push(allReminders[ri]);
            } else {
                activeReminders.push(allReminders[ri]);
            }
        }

        var openCount = stats && typeof stats.openCount === 'number' ? stats.openCount : open.length;
        var doneCount =
            stats && typeof stats.doneCount === 'number'
                ? stats.doneCount + doneReminders.length
                : done.length + doneReminders.length;
        var reminderCount =
            stats && typeof stats.reminderCount === 'number'
                ? stats.reminderCount
                : activeReminders.length;
        updateStats(openCount, doneCount, reminderCount);

        if (rows.length === 0 && allReminders.length === 0) {
            return statusBlock(
                'empty',
                '<div class="cdn-matter-task__empty">' +
                    '<span class="cdn-matter-task__empty-icon" aria-hidden="true"><i class="fa-solid fa-clipboard-list"></i></span>' +
                    '<p class="cdn-matter-task__empty-title">No tasks yet</p>' +
                    '<p class="cdn-matter-task__empty-hint">Add a task for this matter, or switch to <strong>Reminder</strong> to put something on your personal calendar.</p>' +
                    '</div>'
            );
        }

        var html = '';
        if (activeReminders.length) {
            html += '<div class="cdn-matter-task__section cdn-matter-task__section--reminders">';
            html += '<h3 class="cdn-matter-task__section-title">Reminders</h3>';
            html += '<ul class="list-unstyled cdn-matter-task__ul mb-0">';
            for (var r = 0; r < activeReminders.length; r++) {
                html += buildReminderRowHtml(activeReminders[r]);
            }
            html += '</ul></div>';
        }

        if (open.length) {
            html += '<div class="cdn-matter-task__section">';
            if (activeReminders.length) {
                html += '<h3 class="cdn-matter-task__section-title">Tasks</h3>';
            }
            html += '<ul class="list-unstyled cdn-matter-task__ul mb-0">';
            for (var o = 0; o < open.length; o++) {
                html += buildRowHtml(open[o]);
            }
            html += '</ul></div>';
        }

        if (done.length || doneReminders.length) {
            var doneOpen = false;
            try {
                doneOpen = window.sessionStorage.getItem(DONE_PANEL_KEY) === '1';
            } catch (e) {
                doneOpen = false;
            }
            html += '<details class="cdn-matter-task__done-panel"' + (doneOpen ? ' open' : '') + '>';
            html +=
                '<summary class="cdn-matter-task__done-summary">Completed <span class="cdn-matter-task__done-count">(' +
                doneCount +
                ')</span></summary>';
            html += '<ul class="list-unstyled cdn-matter-task__ul cdn-matter-task__ul--done mb-0">';
            for (var dr = 0; dr < doneReminders.length; dr++) {
                html += buildReminderRowHtml(doneReminders[dr]);
            }
            for (var d = 0; d < done.length; d++) {
                html += buildRowHtml(done[d]);
            }
            html += '</ul></details>';
        }

        if (!open.length && !activeReminders.length && (done.length || doneReminders.length)) {
            html =
                '<p class="cdn-matter-task__all-done small text-muted mb-2">All tasks and reminders are complete.</p>' +
                html;
        }

        if (tasksHasMore) {
            html +=
                '<div class="cdn-matter-task__load-more text-center py-2">' +
                '<button type="button" class="btn btn-sm btn-outline-secondary cdn-matter-task-load-more"' +
                (tasksLoading ? ' disabled' : '') +
                '>Load more</button></div>';
        }

        return html;
    }

    function flushPendingDelete(onComplete) {
        if (!pendingDelete) {
            if (typeof onComplete === 'function') {
                onComplete();
            }
            return;
        }
        var id = pendingDelete.id;
        var kind = pendingDelete.kind || 'task';
        var cid = clientId();
        var base =
            kind === 'reminder'
                ? urlMap().matterReminderDestroyBase || ''
                : taskBase();
        if (pendingDelete.timer) {
            clearTimeout(pendingDelete.timer);
        }
        pendingDelete = null;
        if (!id || !cid || !base) {
            if (typeof onComplete === 'function') {
                onComplete();
            }
            return;
        }
        $.ajax({
            url: base + '/' + id + '/delete',
            type: 'POST',
            dataType: 'json',
            headers: {
                'X-CSRF-TOKEN': csrf(),
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json'
            },
            data: { client_id: cid, _token: csrf() },
            complete: function () {
                if (typeof onComplete === 'function') {
                    onComplete();
                }
            },
            error: function () {
                notifyError(kind === 'reminder' ? 'Could not delete reminder.' : 'Could not delete task.');
            }
        });
    }

    function paintTasksList($list) {
        $list.html(
            renderList(tasksRows, {
                openCount: tasksOpenCount,
                doneCount: tasksDoneCount,
                reminderCount: activeReminderCount()
            })
        );
    }

    function fetchTasksPage(page, append) {
        var $wrap = $('#cdn-matter-tasks');
        if (!$wrap.length) {
            return;
        }
        var cid = clientId();
        var mid = matterId();
        var $list = $wrap.find('.cdn-matter-task__list');
        var ref = matterRef();
        var indexUrl = urlMap().matterTaskIndex;

        if (!cid || !indexUrl) {
            return;
        }
        if (tasksLoading) {
            return;
        }

        tasksLoading = true;
        if (!append) {
            $list.html(skeletonHtml());
        } else {
            $list.find('.cdn-matter-task-load-more').prop('disabled', true);
        }

        var listData = { client_id: cid, page: page };
        if (mid) {
            listData.matter_id = mid;
            listData.client_matter_id = mid;
        }
        if (ref) {
            listData.matter_ref = ref;
        }

        $.ajax({
            url: indexUrl,
            type: 'GET',
            dataType: 'json',
            data: listData,
            complete: function () {
                tasksLoading = false;
                syncComposerLock();
            },
            success: function (res) {
                if (!res || !res.status) {
                    if (!append) {
                        $list.html(statusBlock('error', '<p class="small mb-0">Could not load tasks.</p>'));
                        updateStats(0, 0);
                    }
                    return;
                }
                var pageRows = res.data || [];
                tasksPage = res.page || page;
                tasksHasMore = !!res.has_more;
                if (typeof res.open_count === 'number') {
                    tasksOpenCount = res.open_count;
                }
                if (typeof res.done_count === 'number') {
                    tasksDoneCount = res.done_count;
                }
                if (!append) {
                    reminderRows = Array.isArray(res.reminders) ? res.reminders : [];
                }
                if (append) {
                    tasksRows = tasksRows.concat(pageRows);
                } else {
                    tasksRows = pageRows;
                    if (typeof res.open_count !== 'number' || typeof res.done_count !== 'number') {
                        var open = 0;
                        var done = 0;
                        for (var i = 0; i < tasksRows.length; i++) {
                            if (isTaskDone(tasksRows[i])) {
                                done++;
                            } else {
                                open++;
                            }
                        }
                        tasksOpenCount = open;
                        tasksDoneCount = done;
                    }
                }
                paintTasksList($list);
            },
            error: function (xhr) {
                var msg = 'Could not load tasks.';
                if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                }
                if (!append) {
                    $list.html(statusBlock('error', '<p class="small mb-0">' + esc(msg) + '</p>'));
                    updateStats(0, 0);
                }
            }
        });
    }

    function reload() {
        if (pendingDelete) {
            flushPendingDelete(function () {
                reload();
            });
            return;
        }
        var $wrap = $('#cdn-matter-tasks');
        if (!$wrap.length) {
            return;
        }

        var cid = clientId();
        var mid = matterId();
        var $list = $wrap.find('.cdn-matter-task__list');

        if (!cid) {
            $list.html(statusBlock('muted', '<p class="small mb-0">Unable to load tasks for this record.</p>'));
            updateStats(0, 0);
            syncComposerLock();
            return;
        }

        var ref = matterRef();
        if (!mid && !ref) {
            // Lead / client with no matter: still load client-level reminders.
            var indexUrlNoMatter = urlMap().matterTaskIndex;
            if (!indexUrlNoMatter || !canUsePersonalCalendar()) {
                $list.html(
                    statusBlock(
                        'muted',
                        '<p class="small mb-0">Select a matter to view its tasks, or switch to Reminder to add calendar reminders for this lead/client.</p>'
                    )
                );
                updateStats(0, 0);
                syncComposerLock();
                return;
            }
            syncComposerLock();
            tasksPage = 1;
            tasksHasMore = false;
            tasksRows = [];
            reminderRows = [];
            tasksOpenCount = 0;
            tasksDoneCount = 0;
            fetchTasksPage(1, false);
            return;
        }

        var indexUrl = urlMap().matterTaskIndex;
        if (!indexUrl) {
            $list.html(statusBlock('error', '<p class="small mb-0">Task list is not configured.</p>'));
            updateStats(0, 0);
            syncComposerLock();
            return;
        }

        syncComposerLock();
        tasksPage = 1;
        tasksHasMore = false;
        tasksRows = [];
        reminderRows = [];
        tasksOpenCount = 0;
        tasksDoneCount = 0;
        fetchTasksPage(1, false);
    }

    function scheduleReload() {
        if (reloadTimer) {
            clearTimeout(reloadTimer);
        }
        reloadTimer = setTimeout(function () {
            reloadTimer = null;
            reload();
        }, 120);
    }

    $(document).ready(function () {
        ensureDueDatePicker();
        reload();

        // Rebind safely if this ready block ever re-runs (namespaced + off-first)
        $(document).off(EVT);

        $(document).on('click' + EVT, '.cdn-matter-task-load-more', function (e) {
            e.preventDefault();
            if (tasksLoading || !tasksHasMore) {
                return;
            }
            fetchTasksPage(tasksPage + 1, true);
        });

        $(document).on('click' + EVT, '[data-tab="clientaction"]', function () {
            ensureDueDatePicker();
            scheduleReload();
        });

        $(document).on('clientTabContentLoaded' + EVT, function (e, tabId) {
            if (String(tabId || '').toLowerCase() !== 'clientaction') {
                return;
            }
            // Lazy-loaded Tasks HTML replaces the due input — re-bind Flatpickr
            ensureDueDatePicker();
            scheduleReload();
        });

        // Calendar icon should open the picker (icon itself is pointer-events: none)
        $(document).on('mousedown' + EVT, '.cdn-matter-task-composer__due-wrap', function (e) {
            var $due = $('#cdn-matter-task-due');
            if (!$due.length || $due.prop('disabled')) {
                return;
            }
            // Only force-open when clicking the wrap chrome / icon area, not while typing
            if (e.target === $due[0]) {
                return;
            }
            var fp = getDueFlatpickr($due);
            if (fp && typeof fp.open === 'function') {
                e.preventDefault();
                fp.open();
            }
        });

        $(document).on('toggle' + EVT, '.cdn-matter-task__done-panel', function () {
            try {
                window.sessionStorage.setItem(DONE_PANEL_KEY, this.open ? '1' : '0');
            } catch (e) {
                /* ignore */
            }
        });

        $(document).on('click' + EVT, '.cdn-matter-task-composer__kind', function (e) {
            e.preventDefault();
            var kind = String($(this).data('kind') || 'task');
            setComposerKind(kind);
            syncComposerLock();
            $('#cdn-matter-task-title').trigger('focus');
        });

        $(document).on('click' + EVT, '#cdn-matter-task-add', function (e) {
            e.preventDefault();
            e.stopImmediatePropagation();
            if (taskAddInFlight) {
                return;
            }
            var kind = getComposerKind();
            var $inp = $('#cdn-matter-task-title');
            var $due = $('#cdn-matter-task-due');
            ensureDueDatePicker();
            var title = validateTaskTitle($inp);
            if (title === null) {
                return;
            }
            var dueDate = validateTaskDueDate($due);
            if (dueDate === null) {
                return;
            }
            if (kind === 'reminder') {
                if (!canUsePersonalCalendar()) {
                    notifyError('Personal calendar access has not been granted. Ask a Super Admin to enable it on your staff profile.');
                    return;
                }
                if (!dueDate) {
                    markFieldInvalid($due);
                    var fpDue = getDueFlatpickr($due);
                    if (fpDue && fpDue.altInput) {
                        markFieldInvalid($(fpDue.altInput));
                    }
                    notifyError('Choose a reminder date.');
                    return;
                }
            }
            var cid = clientId();
            var mid = matterId();
            var ref = matterRef();
            var storeUrl = urlMap().matterTaskStore;
            if (!cid || !storeUrl) {
                notifyError(kind === 'reminder' ? 'Unable to add a reminder for this record.' : 'Unable to add a task for this record.');
                return;
            }
            if (kind !== 'reminder' && !mid && !ref) {
                notifyError('Select a matter before adding a task.');
                return;
            }
            if (kind === 'reminder' && !canUsePersonalCalendar()) {
                notifyError('Personal calendar access has not been granted. Ask a Super Admin to enable it on your staff profile.');
                return;
            }

            var $btn = $(this);
            taskAddInFlight = true;
            setBusy(true);
            $btn.prop('disabled', true);
            var storeData = {
                client_id: cid,
                title: title,
                kind: kind,
                _token: csrf()
            };
            if (dueDate) {
                storeData.due_date = dueDate;
            }
            if (mid) {
                storeData.matter_id = mid;
                storeData.client_matter_id = mid;
            }
            if (ref) {
                storeData.matter_ref = ref;
            }
            $.ajax({
                url: storeUrl,
                type: 'POST',
                dataType: 'json',
                headers: {
                    'X-CSRF-TOKEN': csrf(),
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json'
                },
                data: storeData,
                success: function (res) {
                    if (res && res.status) {
                        $inp.val('');
                        clearFieldInvalid($inp);
                        clearDueDateInput();
                        reload();
                        setTimeout(function () {
                            $inp.trigger('focus');
                        }, 0);
                    } else {
                        notifyError(res && res.message ? res.message : null);
                    }
                },
                error: function (xhr) {
                    var msg = null;
                    if (xhr && xhr.responseJSON) {
                        if (xhr.responseJSON.message) {
                            msg = xhr.responseJSON.message;
                        } else if (xhr.responseJSON.errors) {
                            var first = xhr.responseJSON.errors;
                            var k = Object.keys(first)[0];
                            if (k && first[k] && first[k][0]) {
                                msg = first[k][0];
                            }
                        }
                    }
                    notifyError(msg);
                },
                complete: function () {
                    taskAddInFlight = false;
                    setBusy(false);
                    $btn.prop('disabled', false);
                }
            });
        });

        $(document).on('input' + EVT, '#cdn-matter-task-title', function () {
            clearFieldInvalid($(this));
        });

        $(document).on('keydown' + EVT, '#cdn-matter-task-title', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                $('#cdn-matter-task-add').trigger('click');
            }
        });

        $(document).on('keydown' + EVT, '#cdn-matter-task-due, .cdn-matter-task-composer__due-alt', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                $('#cdn-matter-task-add').trigger('click');
            }
        });

        $(document).on('change' + EVT, '.cdn-matter-task__cb', function () {
            var $cb = $(this);
            if ($cb.prop('disabled')) {
                return;
            }
            var $row = $cb.closest('.cdn-matter-task__row');
            var id = safeId($row.data('id'));
            var cid = clientId();
            var base = taskBase();
            if (!id || !cid || !base) {
                return;
            }
            var checked = $cb.is(':checked');
            var $label = $row.find('.cdn-matter-task__label');
            $label.toggleClass('is-done', checked);
            $row.toggleClass('is-done-row', checked);

            setBusy(true);
            $.ajax({
                url: base + '/' + id + '/update',
                type: 'POST',
                dataType: 'json',
                headers: {
                    'X-CSRF-TOKEN': csrf(),
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json'
                },
                data: {
                    client_id: cid,
                    is_done: checked ? 1 : 0,
                    _token: csrf()
                },
                success: function (res) {
                    if (!res || !res.status) {
                        reload();
                        notifyError(res && res.message ? res.message : null);
                    } else {
                        scheduleReload();
                    }
                },
                error: function () {
                    reload();
                    notifyError();
                },
                complete: function () {
                    setBusy(false);
                }
            });
        });

        $(document).on('click' + EVT, '.cdn-matter-task__del', function () {
            var $btn = $(this);
            if ($btn.prop('disabled')) {
                return;
            }
            var $row = $btn.closest('.cdn-matter-task__row');
            var id = safeId($row.data('id'));
            var kind = String($row.data('item-kind') || 'task') === 'reminder' ? 'reminder' : 'task';
            var cid = clientId();
            if (!id || !cid) {
                return;
            }

            var title =
                $row.find('.cdn-matter-task__label').clone().children().remove().end().text() ||
                $row.find('.cdn-matter-task__label').text() ||
                (kind === 'reminder' ? 'Reminder' : 'Task');
            title = String(title || '').replace(/\s+/g, ' ').trim();

            var ask =
                typeof window.crmConfirm === 'function'
                    ? window.crmConfirm({
                          title: kind === 'reminder' ? 'Delete reminder?' : 'Delete task?',
                          text:
                              'Delete "' +
                              title +
                              '"? This cannot be undone.',
                          confirmText: kind === 'reminder' ? 'Yes, delete reminder' : 'Yes, delete task',
                          cancelText: 'Cancel',
                          icon: 'warning',
                          confirmColor: '#b91c1c'
                      })
                    : Promise.resolve(window.confirm('Delete "' + title + '"?'));

            ask.then(function (ok) {
                if (!ok) {
                    return;
                }

                if (pendingDelete) {
                    flushPendingDelete();
                }

                $btn.prop('disabled', true);
                $row.addClass('cdn-matter-task__row--removing');

                pendingDelete = { id: id, kind: kind, timer: null };
                flushPendingDelete(function () {
                    if (kind === 'reminder') {
                        reminderRows = reminderRows.filter(function (row) {
                            return safeId(row.id) !== id;
                        });
                    } else {
                        tasksRows = tasksRows.filter(function (row) {
                            return safeId(row.id) !== id;
                        });
                    }
                    $row.remove();
                    var open = $('.cdn-matter-task__row:not(.is-done-row):not(.is-reminder)').length;
                    var done = $('.cdn-matter-task__row.is-done-row').length;
                    var reminders = activeReminderCount();
                    updateStats(open, done, reminders);
                    var $list = $('#cdn-matter-tasks .cdn-matter-task__list');
                    if (open + done + reminders === 0 || $list.find('.cdn-matter-task__row').length === 0) {
                        $list.html(renderList([]));
                    }
                    if (typeof window.crmNotify !== 'undefined' && typeof window.crmNotify.success === 'function') {
                        window.crmNotify.success({
                            message: (kind === 'reminder' ? 'Reminder' : 'Task') + ' deleted.'
                        });
                    } else if (typeof iziToast !== 'undefined' && typeof iziToast.success === 'function') {
                        iziToast.success({
                            title: 'Deleted',
                            message: (kind === 'reminder' ? 'Reminder' : 'Task') + ' deleted.',
                            position: 'topRight'
                        });
                    }
                });
            });
        });
    });

    // Keep Reminder toggle in sync when tab HTML is injected.
    $(function () {
        setComposerKind(getComposerKind());
        syncComposerLock();
    });

    window.MatterTaskList = { reload: reload, scheduleReload: scheduleReload };
})(window.jQuery);
