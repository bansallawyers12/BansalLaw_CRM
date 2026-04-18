/**
 * Matter-scoped task list on client detail Tasks tab (new design demo).
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

    function matterId() {
        var $el = $('#sel_matter_id_client_detail');
        if (!$el.length) {
            return null;
        }
        var v = $el.val();
        return v !== undefined && v !== null && String(v).trim() !== '' ? String(v) : null;
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
     * Enable add-task field only when client + matter + store URL exist.
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
        var mid = matterId();
        var storeUrl = urlMap().matterTaskStore;
        var unlocked = !!(cid && mid && storeUrl);
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
        var mid = matterId();
        var $list = $wrap.find('.cdn-matter-task__list');

        if (!cid || !mid) {
            $list.html(statusPara('muted', 'Select a matter above to view and edit its tasks.'));
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
            data: { client_id: cid, matter_id: mid },
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
                    html += '<li class="cdn-matter-task__row d-flex align-items-start py-2 border-bottom" data-id="' + rowId + '">';
                    html += '<input type="checkbox" class="form-check-input cdn-matter-task__cb" id="cdn-mtask-' + rowId + '"' + (done ? ' checked' : '') + ' />';
                    html += '<label class="form-check-label flex-grow-1 mb-0 cdn-matter-task__label' + (done ? ' is-done' : '') + '" for="cdn-mtask-' + rowId + '">' + title + '</label>';
                    html += '<button type="button" class="btn btn-link btn-sm text-danger p-0 flex-shrink-0 cdn-matter-task__del" title="Remove task" aria-label="Remove task">&times;</button>';
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

        $(document).on('change', '#sel_matter_id_client_detail', function () {
            scheduleReload();
        });

        $(document).on('select2:select', '#sel_matter_id_client_detail', function () {
            scheduleReload();
        });

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
            var mid = matterId();
            var storeUrl = urlMap().matterTaskStore;
            if (!cid || !mid || !storeUrl) {
                notifyError('Select a matter first.');
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
                    matter_id: mid,
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
            var mid = matterId();
            var base = taskBase();
            if (!id || !cid || !mid || !base) {
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
                    matter_id: mid,
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
            var mid = matterId();
            var base = taskBase();
            if (!id || !cid || !mid || !base) {
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
                    matter_id: mid,
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
