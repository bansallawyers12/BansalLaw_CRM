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

    function notifyError(message) {
        var msg = message || 'Something went wrong. Please try again.';
        if (typeof iziToast !== 'undefined') {
            iziToast.error({ message: msg, position: 'topRight' });
        } else if (typeof toastr !== 'undefined') {
            toastr.error(msg);
        }
    }

    function safeId(raw) {
        var n = parseInt(raw, 10);
        return n > 0 ? n : null;
    }

    /**
     * Enable add-task field when client + store URL exist.
     */
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
        $inp.prop('disabled', !unlocked);
        $btn.prop('disabled', !unlocked);
        $wrap.toggleClass('cdn-matter-tasks--locked', !unlocked);
    }

    function statusPara(kind, text) {
        var cls = 'cdn-matter-task__status small mb-0';
        if (kind === 'error') {
            cls += ' text-danger';
        } else {
            cls += ' text-muted';
        }
        return '<p class="' + cls + '">' + $('<div>').text(text).html() + '</p>';
    }

    function reload() {
        var $wrap = $('#cdn-matter-tasks');
        if (!$wrap.length) {
            return;
        }

        var cid = clientId();
        var $list = $wrap.find('.cdn-matter-task__list');

        if (!cid) {
            $list.html(statusPara('muted', 'Unable to load tasks for this record.'));
            syncComposerLock();
            return;
        }

        var indexUrl = urlMap().matterTaskIndex;
        if (!indexUrl) {
            $list.html(statusPara('error', 'Task list is not configured.'));
            syncComposerLock();
            return;
        }

        syncComposerLock();

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
                    $list.html(statusPara('error', 'Could not load tasks.'));
                    return;
                }
                var rows = res.data || [];
                if (rows.length === 0) {
                    $list.html(statusPara('muted', 'No tasks yet. Add one below.'));
                    return;
                }
                var html = '<ul class="list-unstyled cdn-matter-task__ul mb-0">';
                for (var i = 0; i < rows.length; i++) {
                    var it = rows[i];
                    var rowId = safeId(it.id);
                    if (!rowId) {
                        continue;
                    }
                    var done = it.is_done === true;
                    var title = $('<div>').text(it.title || '').html();
                    var cbId = 'cdn-mtask-' + rowId;
                    html += '<li class="cdn-matter-task__row" data-id="' + rowId + '">';
                    html += '<div class="cdn-matter-task__row-main">';
                    html += '<input type="checkbox" class="cdn-matter-task__cb" id="' + cbId + '"' + (done ? ' checked' : '') + ' />';
                    html += '<label class="cdn-matter-task__label' + (done ? ' is-done' : '') + '" for="' + cbId + '">' + title + '</label>';
                    html += '</div>';
                    html += '<button type="button" class="cdn-matter-task__del" title="Delete task" aria-label="Delete task"><i class="fas fa-trash-alt" aria-hidden="true"></i></button>';
                    html += '</li>';
                }
                html += '</ul>';
                $list.html(html);
            },
            error: function (xhr) {
                var msg = 'Could not load tasks.';
                if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                }
                $list.html(statusPara('error', msg));
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
            var $row = $(this).closest('.cdn-matter-task__row');
            var id = safeId($row.data('id'));
            var cid = clientId();
            var base = taskBase();
            if (!id || !cid || !base) {
                return;
            }
            var checked = $(this).is(':checked');
            var $label = $row.find('.cdn-matter-task__label');
            $label.toggleClass('is-done', checked);

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
                    }
                },
                error: function () {
                    reload();
                    notifyError();
                }
            });
        });

        $(document).on('click', '.cdn-matter-task__del', function () {
            if (!window.confirm('Remove this task?')) {
                return;
            }
            var $row = $(this).closest('.cdn-matter-task__row');
            var id = safeId($row.data('id'));
            var cid = clientId();
            var base = taskBase();
            if (!id || !cid || !base) {
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
                data: {
                    client_id: cid,
                    _token: csrf()
                },
                success: function (res) {
                    if (res && res.status) {
                        reload();
                    } else {
                        notifyError(res && res.message ? res.message : null);
                    }
                },
                error: function () {
                    notifyError();
                }
            });
        });
    });

    window.MatterTaskList = { reload: reload, scheduleReload: scheduleReload };
})(window.jQuery);
