/**
 * Add My Task popover on the dashboard (Tom Select client picker).
 */
(function ($) {
    'use strict';

    if (!$) {
        return;
    }

    function clientsUrl() {
        var root = document.getElementById('dashboardRoot');
        return (root && root.getAttribute('data-clients-url')) || '/clients/get-allclients';
    }

    function personalTaskStoreUrl() {
        var root = document.getElementById('dashboardRoot');
        return (root && root.getAttribute('data-personal-task-url')) || '';
    }

    function matterReminderStoreUrl() {
        var root = document.getElementById('dashboardRoot');
        return (root && root.getAttribute('data-matter-reminder-url')) || '';
    }

    function personalCalendarEnabled() {
        var root = document.getElementById('dashboardRoot');
        return !!(root && root.getAttribute('data-personal-calendar-enabled') === '1');
    }

    function syncAddTaskKindUI($root, kind) {
        kind = kind === 'reminder' ? 'reminder' : 'task';
        if (kind === 'reminder' && !personalCalendarEnabled()) {
            kind = 'task';
        }
        $root.toggleClass('is-reminder-mode', kind === 'reminder');
        $root.find('.add-task-kind-input').val(kind);
        $root.find('.add-task-kind-btn').each(function () {
            var isActive = String($(this).data('add-task-kind')) === kind;
            $(this).toggleClass('is-active', isActive).attr('aria-pressed', isActive ? 'true' : 'false');
        });
        var $remindGroup = $root.find('.add-task-remind-on-group');
        if ($remindGroup.length) {
            $remindGroup.prop('hidden', kind !== 'reminder');
        }
        var $hint = $root.find('.add-task-kind-hint');
        if ($hint.length) {
            $hint.text(
                kind === 'reminder'
                    ? ($hint.attr('data-hint-reminder') || '')
                    : ($hint.attr('data-hint-task') || '')
            );
        }
        $root.find('.add-task-client-label-text').text(
            kind === 'reminder' ? 'Client / Lead' : 'Client / Lead (optional)'
        );
        $root.find('.add-task-note-label-text').text(kind === 'reminder' ? 'Reminder' : 'Task Description');
        var $note = $root.find('#assignnote, #add_task_assignnote').first();
        if ($note.length) {
            $note.attr(
                'placeholder',
                kind === 'reminder'
                    ? 'What should we remind you about?'
                    : 'Enter task description... (type @ to tag staff)'
            );
            $note.attr('rows', kind === 'reminder' ? 3 : 4);
        }
        var $submitLabel = $root.find('.add-task-submit-label');
        if ($submitLabel.length) {
            $submitLabel.text(kind === 'reminder' ? 'Add Reminder' : 'Add My Task');
        }
        var $submitBtn = $root.find('.add-task-submit-btn, #add_my_task').first();
        if ($submitBtn.length) {
            var $icon = $submitBtn.find('i').first();
            if ($icon.length) {
                $icon.attr('class', kind === 'reminder' ? 'fa-solid fa-bell' : 'fa-solid fa-circle-plus');
            }
        }
        var $title = $('.popover.add-my-task-popover .add-task-modal-title').first();
        if ($title.length) {
            $title.html(
                kind === 'reminder'
                    ? '<i class="fa-solid fa-bell"></i> Add Reminder'
                    : '<i class="fa-solid fa-circle-plus"></i> Add New Task'
            );
        }
        $root.find('.custom-error').remove();
        $root.find('.error-message').text('');
    }

    function resolvePopoverClientId($popover) {
        var $sel = $popover.find('#assign_client_id, #add_task_client_select').first();
        if (!$sel.length) {
            return '';
        }
        var val = $sel.val();
        var el = $sel[0];
        if (el && el.tomselect && val) {
            var opt = el.tomselect.options[val];
            if (opt && opt.cid != null && String(opt.cid) !== '') {
                return String(opt.cid);
            }
        }
        return val ? String(val) : '';
    }

    function initPopovers(scope) {
        ($(scope || document).find('.add_my_task').addBack('.add_my_task')).each(function () {
            var $btn = $(this);
            if ($btn.data('popover-initialized')) {
                return;
            }
            $btn.data('popover-initialized', true);

            var contentId = $btn.data('content-id');
            var popoverOpts = {
                html: true,
                sanitize: false,
                trigger: 'click',
                placement: 'top',
                boundary: 'viewport',
                container: 'body',
                title: '<span class="add-task-modal-title"><i class="fa-solid fa-circle-plus"></i> Add New Task</span><button type="button" class="add-task-modal-close btn-close" aria-label="Close"></button>',
                template: '<div class="popover add-my-task-popover" role="tooltip"><div class="popover-header"></div><div class="popover-body"></div></div>'
            };

            if (contentId && $('#' + contentId).length) {
                popoverOpts.content = function () {
                    return $('#' + contentId).html();
                };
            }

            $btn.popover(popoverOpts);
        });
    }

    function initializeClientTomSelect($popoverTip) {
        var attempts = 0;
        var maxAttempts = 10;

        function tryInitialize() {
            attempts++;
            var $clientSelect = ($popoverTip && $popoverTip.length)
                ? $popoverTip.find('#assign_client_id')
                : $('.popover.show #assign_client_id, .popover.add-my-task-popover:visible #assign_client_id').first();

            if ($clientSelect.length && $clientSelect.is(':visible')) {
                if (typeof initTS !== 'function' || typeof buildGetAllClientsTomSelectConfig !== 'function' || typeof destroyTS !== 'function') {
                    if (attempts < maxAttempts) {
                        window.setTimeout(tryInitialize, 50);
                    }
                    return;
                }

                try {
                    var el = $clientSelect[0];
                    destroyTS(el);
                    initTS(el, buildGetAllClientsTomSelectConfig({
                        url: clientsUrl(),
                        dropdownParent: 'body',
                        placeholder: 'Search client or lead...'
                    }));
                    var w = el.tomselect && el.tomselect.wrapper;
                    if (w) {
                        w.style.width = '100%';
                    }
                } catch (error) {
                    console.error('Error initializing client Tom Select:', error);
                }
                return;
            }

            if (attempts < maxAttempts) {
                window.setTimeout(tryInitialize, 50);
            }
        }

        tryInitialize();
    }

    window.initDashboardAddTaskPopovers = initPopovers;

    $(function () {
        initPopovers(document);

        $(document).on('shown.bs.popover', '.add_my_task', function () {
            var $popover = $('.popover.add-my-task-popover');
            if ($popover.length === 0) {
                $popover = $('.popover:visible').last();
                $popover.addClass('add-my-task-popover');
            }

            $popover.css({
                position: 'fixed',
                left: '50%',
                top: '50%',
                transform: 'translate(-50%, -50%)',
                margin: '0',
                'z-index': '9999'
            });

            if (!$('.popover-backdrop').length) {
                $('body').append('<div class="popover-backdrop"></div>');
            }
            $('.popover-backdrop').addClass('show');

            $('.popover-backdrop').off('click.dashboardTask').on('click.dashboardTask', function () {
                $('.add_my_task').popover('hide');
            });

            window.setTimeout(function () {
                initializeClientTomSelect($popover);
                var $root = $popover.find('.add-task-layout').first();
                if ($root.length) {
                    syncAddTaskKindUI($root, 'task');
                }
            }, 100);
        });

        $(document).on('click', '.add-task-kind-btn', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var $root = $(this).closest('.add-task-layout');
            if ($root.length) {
                syncAddTaskKindUI($root, String($(this).data('add-task-kind') || 'task'));
            }
        });

        $(document).on('hide.bs.popover', '.add_my_task', function () {
            if (typeof destroyTS === 'function') {
                document.querySelectorAll('#assign_client_id').forEach(function (el) {
                    destroyTS(el);
                });
            }
        });

        $(document).on('hidden.bs.popover', '.add_my_task', function () {
            $('.popover-backdrop').removeClass('show');
        });

        $(document).on('click', '#add_my_task', function (e) {
            e.preventDefault();
            e.stopPropagation();

            if ($('.popuploader').length) {
                $('.popuploader').show();
            }

            var flag = true;
            $('.custom-error').remove();

            var $popover = $(this).closest('.popover');
            if ($popover.length === 0) {
                $popover = $('.popover:visible');
            }
            var $root = $popover.find('.add-task-layout').first();
            if (!$root.length) {
                $root = $popover;
            }

            var kind = String($root.find('.add-task-kind-input').val() || 'task').toLowerCase();
            if (kind === 'reminder') {
                var clientId = resolvePopoverClientId($popover);
                var title = String($popover.find('#assignnote').val() || '').trim();
                var dueDate = String($popover.find('#add_task_remind_on').val() || '').trim();
                var reminderUrl = matterReminderStoreUrl();

                if (!clientId) {
                    flag = false;
                    $popover.find('#client-error').text('Select a client or lead.');
                }
                if (!title) {
                    flag = false;
                    $popover.find('#assignnote').after(
                        "<span class='custom-error' role='alert' style='color: red; font-size: 12px; display: block; margin-top: 5px;'>Reminder text is required.</span>"
                    );
                }
                if (!dueDate) {
                    flag = false;
                    $popover.find('#add_task_remind_on_error').text('Choose a reminder date.');
                }
                if (!reminderUrl) {
                    flag = false;
                }
                if (!flag) {
                    if ($('.popuploader').length) {
                        $('.popuploader').hide();
                    }
                    return false;
                }

                $.ajax({
                    type: 'post',
                    url: reminderUrl,
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    dataType: 'json',
                    data: {
                        client_id: clientId,
                        title: title,
                        due_date: dueDate,
                        kind: 'reminder',
                        _token: $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function (response) {
                        if ($('.popuploader').length) {
                            $('.popuploader').hide();
                        }
                        if (response && response.status) {
                            $('.add_my_task').popover('hide');
                            $('.popover-backdrop').removeClass('show');
                            if (typeof window.refreshDashboard === 'function') {
                                window.refreshDashboard();
                            } else {
                                window.location.reload();
                            }
                        } else {
                            window.crmAlert(response && response.message ? response.message : 'Could not add reminder.');
                        }
                    },
                    error: function (xhr) {
                        if ($('.popuploader').length) {
                            $('.popuploader').hide();
                        }
                        var errorMsg = 'Failed to add reminder. Please try again.';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            errorMsg = xhr.responseJSON.message;
                        }
                        window.crmAlert(errorMsg);
                    }
                });

                return false;
            }

            var selectedRemCat = [];
            $popover.find('.checkbox-item:checked').each(function () {
                selectedRemCat.push($(this).val());
            });

            if (selectedRemCat.length === 0) {
                if ($('.popuploader').length) {
                    $('.popuploader').hide();
                }
                $popover.find('#dropdownMenuButton').after(
                    "<span class='custom-error' role='alert' style='color: red; font-size: 12px; display: block; margin-top: 5px;'>Assignee field is required.</span>"
                );
                flag = false;
            }

            var assignnoteValue = $popover.find('#assignnote').val();
            if (!assignnoteValue || String(assignnoteValue).trim() === '') {
                if ($('.popuploader').length) {
                    $('.popuploader').hide();
                }
                $popover.find('#assignnote').after(
                    "<span class='custom-error' role='alert' style='color: red; font-size: 12px; display: block; margin-top: 5px;'>Note field is required.</span>"
                );
                flag = false;
            }

            var storeUrl = personalTaskStoreUrl();
            if (!storeUrl) {
                flag = false;
            }

            if (!flag) {
                if ($('.popuploader').length) {
                    $('.popuploader').hide();
                }
                return false;
            }

            $.ajax({
                type: 'post',
                url: storeUrl,
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                dataType: 'json',
                data: {
                    note_type: 'follow_up',
                    description: assignnoteValue,
                    client_id: $popover.find('#assign_client_id').val(),
                    rem_cat: selectedRemCat,
                    task_group: $popover.find('#task_group').val()
                },
                success: function (response) {
                    if ($('.popuploader').length) {
                        $('.popuploader').hide();
                    }
                    if (response && response.success) {
                        $('.add_my_task').popover('hide');
                        $('.popover-backdrop').removeClass('show');
                        if (typeof window.refreshDashboard === 'function') {
                            window.refreshDashboard();
                        } else {
                            window.location.reload();
                        }
                    } else {
                        window.crmAlert(response && response.message ? response.message : 'An error occurred');
                    }
                },
                error: function (xhr) {
                    if ($('.popuploader').length) {
                        $('.popuploader').hide();
                    }
                    var errorMsg = 'Failed to add task. Please try again.';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMsg = xhr.responseJSON.message;
                    }
                    window.crmAlert(errorMsg);
                }
            });

            return false;
        });
    });

    window.DashboardAddTaskPopover = {
        init: initPopovers
    };
})(typeof jQuery !== 'undefined' ? jQuery : null);
