/**
 * Client-scoped task list on client detail Tasks tab (not filtered by selected matter).
 * Requires: jQuery, ClientDetailConfig.urls.matterTask*, clientId, csrfToken
 */
(function ($) {
    'use strict';
    if (!$) {
        return;
    }

    var reloadTimer = null;
    var pendingDelete = null;
    var UNDO_MS = 6000;
    var DONE_PANEL_KEY = 'cdn-matter-tasks-done-open';

    function cfg() {
        return window.ClientDetailConfig || {};
    }

    function urlMap() {
        return cfg().urls || {};
    }

    function clientId() {
        var id = cfg().clientId;
        return id !== undefined && id !== null && String(id) !== '' ? String(id) : null;
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
            alert(msg);
        }
    }

    function notifyUndo(title, onUndo) {
        var label = esc(title || 'Task');
        if (typeof iziToast !== 'undefined' && typeof iziToast.show === 'function') {
            iziToast.destroy();
            iziToast.show({
                title: 'Task removed',
                message: label,
                position: 'topRight',
                timeout: UNDO_MS,
                close: true,
                progressBar: true,
                displayMode: 2,
                buttons: [
                    [
                        '<button type="button"><b>Undo</b></button>',
                        function (instance, toast) {
                            instance.hide({ transitionOut: 'fadeOut' }, toast, 'button');
                            if (typeof onUndo === 'function') {
                                onUndo();
                            }
                        },
                        true
                    ]
                ]
            });
            return;
        }
        if (typeof window.crmNotify !== 'undefined' && typeof window.crmNotify.info === 'function') {
            window.crmNotify.info({
                message: (title || 'Task') + ' removed. Refresh the tab if you need to restore it.'
            });
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

    function syncComposerLock() {
        var $wrap = $('#cdn-matter-tasks');
        if (!$wrap.length) {
            return;
        }
        var $inp = $('#cdn-matter-task-title');
        var $btn = $('#cdn-matter-task-add');
        if (!$inp.length || !$btn.length) {
            return;
        }
        var cid = clientId();
        var storeUrl = urlMap().matterTaskStore;
        var unlocked = !!(cid && storeUrl);
        var busy = $wrap.hasClass('cdn-matter-tasks--busy');
        $inp.prop('disabled', !unlocked || busy);
        $btn.prop('disabled', !unlocked || busy);
        $wrap.toggleClass('cdn-matter-tasks--locked', !unlocked);
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

    function creatorName(it) {
        var c = it && it.creator;
        if (!c) {
            return '';
        }
        var name = ((c.first_name || '') + ' ' + (c.last_name || '')).trim();
        return name;
    }

    function rowMetaHtml(it) {
        var parts = [];
        var creator = creatorName(it);
        if (creator) {
            parts.push('Added by ' + esc(creator));
        }
        var when = formatCreatedAt(it.created_at);
        if (when) {
            parts.push(esc(when));
        }
        if (!parts.length) {
            return '';
        }
        return '<div class="cdn-matter-task__meta">' + parts.join(' · ') + '</div>';
    }

    function buildRowHtml(it) {
        var rowId = safeId(it.id);
        if (!rowId) {
            return '';
        }
        var done = isTaskDone(it);
        var title = esc(it.title || '');
        var cbId = 'cdn-mtask-' + rowId;
        var noteId = safeId(it.note_id);
        var actionHref = urlMap().assigneeAction ? esc(actionPageUrl(noteId)) : '';

        var html = '<li class="cdn-matter-task__row' + (done ? ' is-done-row' : '') + '" data-id="' + rowId + '"';
        if (noteId) {
            html += ' data-note-id="' + noteId + '"';
        }
        html += '>';
        html += '<div class="cdn-matter-task__row-main">';
        html += '<input type="checkbox" class="cdn-matter-task__cb" id="' + cbId + '"' + (done ? ' checked' : '') + ' />';
        html += '<div class="cdn-matter-task__text">';
        html += '<label class="cdn-matter-task__label' + (done ? ' is-done' : '') + '" for="' + cbId + '">' + title + '</label>';
        html += rowMetaHtml(it);
        html += '</div>';
        html += '</div>';
        html += '<div class="cdn-matter-task__actions">';
        if (actionHref) {
            html +=
                '<a class="cdn-matter-task__action-link" href="' +
                actionHref +
                '" title="Open on Action page" aria-label="Open on Action page"><i class="fa-solid fa-external-link-alt" aria-hidden="true"></i><span class="cdn-matter-task__action-link-text">Action</span></a>';
        }
        html +=
            '<button type="button" class="cdn-matter-task__del" title="Delete task" aria-label="Delete task"><i class="fa-solid fa-trash-can" aria-hidden="true"></i></button>';
        html += '</div>';
        html += '</li>';
        return html;
    }

    function updateStats(openCount, doneCount) {
        var $stats = $('#cdn-matter-task-stats');
        if (!$stats.length) {
            return;
        }
        var total = openCount + doneCount;
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
        $stats.text(parts.join(' · '));
    }

    function renderList(rows) {
        var open = [];
        var done = [];
        for (var i = 0; i < rows.length; i++) {
            if (isTaskDone(rows[i])) {
                done.push(rows[i]);
            } else {
                open.push(rows[i]);
            }
        }

        updateStats(open.length, done.length);

        if (rows.length === 0) {
            return statusBlock(
                'empty',
                '<div class="cdn-matter-task__empty">' +
                    '<span class="cdn-matter-task__empty-icon" aria-hidden="true"><i class="fa-solid fa-clipboard-list"></i></span>' +
                    '<p class="cdn-matter-task__empty-title">No tasks yet</p>' +
                    '<p class="cdn-matter-task__empty-hint">Add a task above. Tasks you create here also appear on the <strong>Action</strong> page for follow-up.</p>' +
                    '</div>'
            );
        }

        var html = '';
        if (open.length) {
            html += '<div class="cdn-matter-task__section">';
            html += '<ul class="list-unstyled cdn-matter-task__ul mb-0">';
            for (var o = 0; o < open.length; o++) {
                html += buildRowHtml(open[o]);
            }
            html += '</ul></div>';
        }

        if (done.length) {
            var doneOpen = false;
            try {
                doneOpen = window.sessionStorage.getItem(DONE_PANEL_KEY) === '1';
            } catch (e) {
                doneOpen = false;
            }
            html += '<details class="cdn-matter-task__done-panel"' + (doneOpen ? ' open' : '') + '>';
            html += '<summary class="cdn-matter-task__done-summary">Completed <span class="cdn-matter-task__done-count">(' + done.length + ')</span></summary>';
            html += '<ul class="list-unstyled cdn-matter-task__ul cdn-matter-task__ul--done mb-0">';
            for (var d = 0; d < done.length; d++) {
                html += buildRowHtml(done[d]);
            }
            html += '</ul></details>';
        }

        if (!open.length && done.length) {
            html =
                '<p class="cdn-matter-task__all-done small text-muted mb-2">All tasks are complete.</p>' + html;
        }

        return html;
    }

    function cancelPendingDelete() {
        if (!pendingDelete) {
            return;
        }
        if (pendingDelete.timer) {
            clearTimeout(pendingDelete.timer);
        }
        pendingDelete = null;
    }

    function flushPendingDelete(onComplete) {
        if (!pendingDelete) {
            if (typeof onComplete === 'function') {
                onComplete();
            }
            return;
        }
        var id = pendingDelete.id;
        var cid = clientId();
        var base = taskBase();
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
                notifyError('Could not delete task.');
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
        var $list = $wrap.find('.cdn-matter-task__list');

        if (!cid) {
            $list.html(statusBlock('muted', '<p class="small mb-0">Unable to load tasks for this record.</p>'));
            updateStats(0, 0);
            syncComposerLock();
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
        $list.html(skeletonHtml());

        $.ajax({
            url: indexUrl,
            type: 'GET',
            dataType: 'json',
            data: { client_id: cid },
            complete: function () {
                syncComposerLock();
            },
            success: function (res) {
                if (!res || !res.status) {
                    $list.html(statusBlock('error', '<p class="small mb-0">Could not load tasks.</p>'));
                    updateStats(0, 0);
                    return;
                }
                $list.html(renderList(res.data || []));
            },
            error: function (xhr) {
                var msg = 'Could not load tasks.';
                if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                }
                $list.html(statusBlock('error', '<p class="small mb-0">' + esc(msg) + '</p>'));
                updateStats(0, 0);
            }
        });
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
        reload();

        $(document).on('click', '[data-tab="clientaction"]', function () {
            scheduleReload();
        });

        $(document).on('toggle', '.cdn-matter-task__done-panel', function () {
            try {
                window.sessionStorage.setItem(DONE_PANEL_KEY, this.open ? '1' : '0');
            } catch (e) {
                /* ignore */
            }
        });

        $(document).on('click', '#cdn-matter-task-add', function () {
            var $inp = $('#cdn-matter-task-title');
            var title = ($inp.val() || '').trim();
            if (!title) {
                return;
            }
            var cid = clientId();
            var storeUrl = urlMap().matterTaskStore;
            if (!cid || !storeUrl) {
                notifyError('Unable to add a task for this record.');
                return;
            }

            var $btn = $(this);
            setBusy(true);
            $btn.prop('disabled', true);
            $.ajax({
                url: storeUrl,
                type: 'POST',
                dataType: 'json',
                headers: {
                    'X-CSRF-TOKEN': csrf(),
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json'
                },
                data: {
                    client_id: cid,
                    title: title,
                    _token: csrf()
                },
                success: function (res) {
                    if (res && res.status) {
                        $inp.val('');
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
                    setBusy(false);
                    $btn.prop('disabled', false);
                }
            });
        });

        $(document).on('keydown', '#cdn-matter-task-title', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                $('#cdn-matter-task-add').trigger('click');
            }
        });

        $(document).on('change', '.cdn-matter-task__cb', function () {
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

        $(document).on('click', '.cdn-matter-task__del', function () {
            var $btn = $(this);
            if ($btn.prop('disabled')) {
                return;
            }
            var $row = $btn.closest('.cdn-matter-task__row');
            var id = safeId($row.data('id'));
            var cid = clientId();
            if (!id || !cid) {
                return;
            }

            if (pendingDelete) {
                flushPendingDelete();
            }
            var title = $row.find('.cdn-matter-task__label').text() || 'Task';
            $row.addClass('cdn-matter-task__row--removing');

            pendingDelete = { id: id, timer: null };
            pendingDelete.timer = setTimeout(function () {
                flushPendingDelete(function () {
                    var $list = $('#cdn-matter-tasks .cdn-matter-task__list');
                    if ($list.find('.cdn-matter-task__row').length === 0) {
                        $list.html(renderList([]));
                    }
                });
            }, UNDO_MS);

            notifyUndo(title, function () {
                cancelPendingDelete();
                reload();
            });

            setTimeout(function () {
                $row.remove();
                var open = $('.cdn-matter-task__row:not(.is-done-row)').length;
                var done = $('.cdn-matter-task__row.is-done-row').length;
                updateStats(open, done);
                if (open + done === 0) {
                    $('#cdn-matter-tasks .cdn-matter-task__list').html(renderList([]));
                }
            }, 280);
        });
    });

    window.MatterTaskList = { reload: reload, scheduleReload: scheduleReload };
})(window.jQuery);
