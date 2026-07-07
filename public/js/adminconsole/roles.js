(function ($) {
    'use strict';

    var $app = $('#roles-admin-app');
    if (!$app.length) {
        return;
    }

    var state = {
        search: String($app.data('initial-search') || ''),
        page: 1,
        loading: false,
        currentRoleId: null,
        editRoleId: null
    };

    var urls = {
        index: $app.data('index-url'),
        create: $app.data('create-url'),
        store: $app.data('store-url'),
        editTemplate: $app.data('edit-url-template'),
        updateTemplate: $app.data('update-url-template'),
        viewTemplate: $app.data('view-url-template')
    };

    var $listContent = $('#roles-list-content');
    var $listFooter = $('#roles-list-footer');
    var $listLoading = $('#roles-list-loading');
    var $searchInput = $('#roles-search-input');

    function escapeHtml(value) {
        return $('<div/>').text(value == null ? '' : String(value)).html();
    }

    function buildUrl(idTemplate, id) {
        return String(idTemplate || '').replace('__ID__', encodeURIComponent(id));
    }

    function ajaxHeaders() {
        return {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        };
    }

    function showFlashMessage(message, type) {
        var alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
        $('.server-error').html(
            '<div class="alert ' + alertClass + ' alert-dismissible fade show" role="alert">' +
            escapeHtml(message) +
            '<button type="button" class="close" data-bs-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>'
        );
    }

    function hideModal($modal) {
        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            var instance = bootstrap.Modal.getInstance($modal[0]);
            if (instance) {
                instance.hide();
            }
        }
    }

    function showModal($modal) {
        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            bootstrap.Modal.getOrCreateInstance($modal[0]).show();
        }
    }

    function setLoading(isLoading) {
        state.loading = isLoading;
        $listLoading.toggleClass('d-none', !isLoading);
        $listContent.toggleClass('is-loading', isLoading);
    }

    function updateBrowserUrl() {
        if (!window.history || !window.history.replaceState) {
            return;
        }
        var params = new URLSearchParams();
        if (state.search) {
            params.set('search_by', state.search);
        }
        if (state.page > 1) {
            params.set('page', String(state.page));
        }
        var query = params.toString();
        var nextUrl = urls.index + (query ? ('?' + query) : '');
        window.history.replaceState({ rolesSearch: state.search }, '', nextUrl);
    }

    function loadRolesList(page) {
        if (state.loading) {
            return;
        }
        if (typeof page === 'number') {
            state.page = page;
        }

        setLoading(true);

        $.ajax({
            url: urls.index,
            method: 'GET',
            headers: ajaxHeaders(),
            data: {
                search_by: state.search,
                page: state.page
            }
        }).done(function (response) {
            if (!response || !response.success) {
                showFlashMessage((response && response.message) || 'Failed to load roles list.', 'error');
                return;
            }
            $listContent.html(response.html || '');
            $listFooter.html(response.pagination || '');
            updateBrowserUrl();
        }).fail(function (xhr) {
            var message = (xhr.responseJSON && xhr.responseJSON.message) || 'Failed to load roles list.';
            showFlashMessage(message, 'error');
        }).always(function () {
            setLoading(false);
        });
    }

    function clearFormErrors($form, $alert) {
        $alert.addClass('d-none').text('');
        $form.find('.field-error').text('');
        $form.find('.is-invalid').removeClass('is-invalid');
    }

    function expandRoleSectionForField($form, field) {
        var prefix = $form.attr('id') === 'roles-create-form' ? 'create_role' : 'edit_role';
        var sectionId = prefix + '_details';
        if (field !== 'name' && field !== 'description') {
            sectionId = prefix + '_permissions_wrap';
        }
        var $section = $('#' + sectionId);
        if ($section.length && !$section.hasClass('show')) {
            if (typeof bootstrap !== 'undefined' && bootstrap.Collapse) {
                bootstrap.Collapse.getOrCreateInstance($section[0], { toggle: false }).show();
            } else {
                $section.addClass('show');
            }
            var $header = $('[data-bs-target="#' + sectionId + '"]');
            if ($header.length) {
                $header.removeClass('collapsed').attr('aria-expanded', 'true');
            }
        }
    }

    function showFieldErrors($form, $alert, errors) {
        clearFormErrors($form, $alert);
        var messages = [];
        var firstSelector = null;
        var firstField = null;
        $.each(errors || {}, function (field, msgs) {
            var msg = Array.isArray(msgs) ? msgs[0] : msgs;
            messages.push(msg);
            $form.find('.field-error[data-field="' + field + '"]').text(msg);
            var $input = $form.find('[name="' + field + '"]');
            if ($input.length) {
                $input.addClass('is-invalid');
                if (!firstSelector) {
                    firstSelector = $input.first();
                    firstField = field;
                }
            }
        });
        if (messages.length) {
            $alert.removeClass('d-none').text(messages[0]);
        }
        if (firstField) {
            expandRoleSectionForField($form, firstField);
        }
        if (firstSelector && firstSelector.length) {
            setTimeout(function () {
                firstSelector.trigger('focus');
            }, 200);
        }
    }

    function setSubmitting($btn, isSubmitting) {
        $btn.prop('disabled', isSubmitting);
        $btn.find('.submit-label').toggleClass('d-none', isSubmitting);
        $btn.find('.submit-spinner').toggleClass('d-none', !isSubmitting);
    }

    function initRolesFormBehaviors($container) {
        $container.find('.roles-select-all').off('click.rolesForm').on('click.rolesForm', function () {
            var groupClass = $(this).attr('data-class');
            if (groupClass) {
                $container.find('.' + groupClass).prop('checked', true);
            }
        });

        $container.find('.roles-deselect-all').off('click.rolesForm').on('click.rolesForm', function () {
            var groupClass = $(this).attr('data-class');
            if (groupClass) {
                $container.find('.' + groupClass).prop('checked', false);
            }
        });
    }

    function openCreateModal() {
        var $modal = $('#rolesCreateModal');
        var $body = $('#roles-create-form-body');
        var $alert = $('#roles-create-alert');

        $body.html('<div class="text-center py-5 text-muted"><div class="spinner-border spinner-border-sm me-2" role="status"></div>Loading form...</div>');
        clearFormErrors($('#roles-create-form'), $alert);
        showModal($modal);

        $.ajax({
            url: urls.create,
            method: 'GET',
            headers: ajaxHeaders()
        }).done(function (response) {
            if (!response || !response.success) {
                $body.html('<div class="alert alert-danger mb-0">' + escapeHtml((response && response.message) || 'Could not load form.') + '</div>');
                return;
            }
            $body.html(response.html || '');
            initRolesFormBehaviors($body);
        }).fail(function (xhr) {
            var message = (xhr.responseJSON && xhr.responseJSON.message) || 'Could not load create form.';
            $body.html('<div class="alert alert-danger mb-0">' + escapeHtml(message) + '</div>');
        });
    }

    function openEditModal(roleId) {
        var $modal = $('#rolesEditModal');
        var $body = $('#roles-edit-form-body');
        var $alert = $('#roles-edit-alert');

        state.editRoleId = roleId;
        $('#roles_edit_id').val(roleId);
        $body.html('<div class="text-center py-5 text-muted"><div class="spinner-border spinner-border-sm me-2" role="status"></div>Loading form...</div>');
        clearFormErrors($('#roles-edit-form'), $alert);
        showModal($modal);

        $.ajax({
            url: buildUrl(urls.editTemplate, roleId),
            method: 'GET',
            headers: ajaxHeaders()
        }).done(function (response) {
            if (!response || !response.success) {
                $body.html('<div class="alert alert-danger mb-0">' + escapeHtml((response && response.message) || 'Could not load form.') + '</div>');
                return;
            }
            $('#rolesEditModalLabel').text('Edit ' + (response.role && response.role.name ? response.role.name : 'role'));
            $body.html(response.html || '');
            initRolesFormBehaviors($body);
        }).fail(function (xhr) {
            var message = (xhr.responseJSON && xhr.responseJSON.message) || 'Could not load edit form.';
            $body.html('<div class="alert alert-danger mb-0">' + escapeHtml(message) + '</div>');
        });
    }

    function openViewModal(roleId) {
        var $modal = $('#rolesViewModal');
        var $body = $('#roles-view-body');

        state.currentRoleId = roleId;
        $body.html('<div class="text-center py-5 text-muted"><div class="spinner-border spinner-border-sm me-2" role="status"></div>Loading role...</div>');
        showModal($modal);

        $.ajax({
            url: buildUrl(urls.viewTemplate, roleId),
            method: 'GET',
            headers: ajaxHeaders()
        }).done(function (response) {
            if (!response || !response.success) {
                $body.html('<div class="alert alert-danger mb-0">' + escapeHtml((response && response.message) || 'Could not load role.') + '</div>');
                return;
            }
            $('#rolesViewModalLabel').text((response.role && response.role.name) || 'Role details');
            $body.html(response.html || '');
        }).fail(function (xhr) {
            var message = (xhr.responseJSON && xhr.responseJSON.message) || 'Could not load role.';
            $body.html('<div class="alert alert-danger mb-0">' + escapeHtml(message) + '</div>');
        });
    }

    function submitRoleForm($form, url, $alert, $submit, onSuccess) {
        clearFormErrors($form, $alert);
        setSubmitting($submit, true);

        $.ajax({
            url: url,
            method: 'POST',
            headers: ajaxHeaders(),
            data: $form.serialize()
        }).done(function (response) {
            if (!response || !response.success) {
                showFieldErrors($form, $alert, (response && response.errors) || { form: [(response && response.message) || 'Save failed.'] });
                return;
            }
            if (typeof onSuccess === 'function') {
                onSuccess(response);
            }
        }).fail(function (xhr) {
            if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                showFieldErrors($form, $alert, xhr.responseJSON.errors);
                return;
            }
            var message = (xhr.responseJSON && xhr.responseJSON.message) || 'Save failed. Please try again.';
            $alert.removeClass('d-none').text(message);
        }).always(function () {
            setSubmitting($submit, false);
        });
    }

    function handleDeepLinkAction() {
        var params = new URLSearchParams(window.location.search);
        var action = params.get('action');
        var id = params.get('id');
        if (action === 'create') {
            openCreateModal();
        } else if (action === 'edit' && id) {
            openEditModal(id);
        } else if (action === 'view' && id) {
            openViewModal(id);
        }
    }

    $('#roles-search-btn').on('click', function () {
        state.search = $.trim($searchInput.val());
        state.page = 1;
        loadRolesList(1);
    });

    $('#roles-search-clear').on('click', function () {
        $searchInput.val('');
        state.search = '';
        state.page = 1;
        loadRolesList(1);
    });

    $searchInput.on('keydown', function (event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            $('#roles-search-btn').trigger('click');
        }
    });

    $(document).on('click', '#roles-list-pagination a', function (event) {
        var href = $(this).attr('href');
        if (!href || state.loading) {
            return;
        }
        event.preventDefault();
        var url = new URL(href, window.location.origin);
        state.page = parseInt(url.searchParams.get('page') || '1', 10) || 1;
        if (url.searchParams.get('search_by')) {
            state.search = url.searchParams.get('search_by');
            $searchInput.val(state.search);
        }
        loadRolesList(state.page);
    });

    $('#roles-add-btn').on('click', openCreateModal);

    $(document).on('click', '.roles-edit-btn', function () {
        openEditModal($(this).data('role-id'));
    });

    $(document).on('click', '.roles-view-btn', function () {
        openViewModal($(this).data('role-id'));
    });

    $('#roles-edit-from-view').on('click', function () {
        var roleId = state.currentRoleId;
        if (!roleId) {
            return;
        }
        hideModal($('#rolesViewModal'));
        setTimeout(function () {
            openEditModal(roleId);
        }, 250);
    });

    $('#roles-view-from-edit').on('click', function () {
        var roleId = state.editRoleId;
        if (!roleId) {
            return;
        }
        hideModal($('#rolesEditModal'));
        setTimeout(function () {
            openViewModal(roleId);
        }, 250);
    });

    $('#roles-create-form').on('submit', function (event) {
        event.preventDefault();
        var $form = $(this);
        submitRoleForm($form, urls.store, $('#roles-create-alert'), $('#roles-create-submit'), function (response) {
            hideModal($('#rolesCreateModal'));
            showFlashMessage(response.message, 'success');
            loadRolesList(1);
        });
    });

    $('#roles-edit-form').on('submit', function (event) {
        event.preventDefault();
        var roleId = state.editRoleId || $('#roles_edit_id').val();
        if (!roleId) {
            return;
        }
        var $form = $(this);
        submitRoleForm(
            $form,
            buildUrl(urls.updateTemplate, roleId),
            $('#roles-edit-alert'),
            $('#roles-edit-submit'),
            function (response) {
                hideModal($('#rolesEditModal'));
                showFlashMessage(response.message, 'success');
                loadRolesList(state.page);
            }
        );
    });

    handleDeepLinkAction();
})(jQuery);
