(function ($) {
    'use strict';

    var $app = $('#dcl-admin-app');
    if (!$app.length) {
        return;
    }

    var state = {
        search: String($app.data('initial-search') || ''),
        page: 1,
        loading: false,
        currentItemId: null,
        editItemId: null
    };

    var urls = {
        index: $app.data('index-url'),
        create: $app.data('create-url'),
        store: $app.data('store-url'),
        editTemplate: $app.data('edit-url-template'),
        updateTemplate: $app.data('update-url-template'),
        viewTemplate: $app.data('view-url-template')
    };

    var $listContent = $('#dcl-list-content');
    var $listFooter = $('#dcl-list-footer');
    var $listLoading = $('#dcl-list-loading');
    var $searchInput = $('#dcl-search-input');

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
        window.history.replaceState({ dclSearch: state.search }, '', urls.index + (query ? ('?' + query) : ''));
    }

    function loadList(page) {
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
                showFlashMessage((response && response.message) || 'Failed to load checklists.', 'error');
                return;
            }
            $listContent.html(response.html || '');
            $listFooter.html(response.pagination || '');
            updateBrowserUrl();
        }).fail(function (xhr) {
            showFlashMessage((xhr.responseJSON && xhr.responseJSON.message) || 'Failed to load checklists.', 'error');
        }).always(function () {
            setLoading(false);
        });
    }

    function clearFormErrors($form, $alert) {
        $alert.addClass('d-none').text('');
        $form.find('.field-error').text('');
        $form.find('.is-invalid').removeClass('is-invalid');
    }

    function showFieldErrors($form, $alert, errors) {
        clearFormErrors($form, $alert);
        var messages = [];
        var firstSelector = null;
        $.each(errors || {}, function (field, msgs) {
            var msg = Array.isArray(msgs) ? msgs[0] : msgs;
            messages.push(msg);
            $form.find('.field-error[data-field="' + field + '"]').text(msg);
            var $input = $form.find('[name="' + field + '"]');
            if ($input.length) {
                $input.addClass('is-invalid');
                if (!firstSelector) {
                    firstSelector = $input.first();
                }
            }
        });
        if (messages.length) {
            $alert.removeClass('d-none').text(messages[0]);
        }
        if (firstSelector && firstSelector.length) {
            firstSelector.trigger('focus');
        }
    }

    function setSubmitting($btn, isSubmitting) {
        $btn.prop('disabled', isSubmitting);
        $btn.find('.submit-label').toggleClass('d-none', isSubmitting);
        $btn.find('.submit-spinner').toggleClass('d-none', !isSubmitting);
    }

    function openCreateModal() {
        var $modal = $('#dclCreateModal');
        var $body = $('#dcl-create-form-body');
        var $alert = $('#dcl-create-alert');

        $body.html('<div class="text-center py-5 text-muted"><div class="spinner-border spinner-border-sm me-2" role="status"></div>Loading form...</div>');
        clearFormErrors($('#dcl-create-form'), $alert);
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
        }).fail(function (xhr) {
            $body.html('<div class="alert alert-danger mb-0">' + escapeHtml((xhr.responseJSON && xhr.responseJSON.message) || 'Could not load create form.') + '</div>');
        });
    }

    function openEditModal(itemId) {
        var $modal = $('#dclEditModal');
        var $body = $('#dcl-edit-form-body');
        var $alert = $('#dcl-edit-alert');

        state.editItemId = itemId;
        $('#dcl_edit_id').val(itemId);
        $body.html('<div class="text-center py-5 text-muted"><div class="spinner-border spinner-border-sm me-2" role="status"></div>Loading form...</div>');
        clearFormErrors($('#dcl-edit-form'), $alert);
        showModal($modal);

        $.ajax({
            url: buildUrl(urls.editTemplate, itemId),
            method: 'GET',
            headers: ajaxHeaders()
        }).done(function (response) {
            if (!response || !response.success) {
                $body.html('<div class="alert alert-danger mb-0">' + escapeHtml((response && response.message) || 'Could not load form.') + '</div>');
                return;
            }
            $('#dclEditModalLabel').text('Edit ' + (response.item && response.item.name ? response.item.name : 'checklist'));
            $body.html(response.html || '');
        }).fail(function (xhr) {
            $body.html('<div class="alert alert-danger mb-0">' + escapeHtml((xhr.responseJSON && xhr.responseJSON.message) || 'Could not load edit form.') + '</div>');
        });
    }

    function openViewModal(itemId) {
        var $modal = $('#dclViewModal');
        var $body = $('#dcl-view-body');

        state.currentItemId = itemId;
        $body.html('<div class="text-center py-5 text-muted"><div class="spinner-border spinner-border-sm me-2" role="status"></div>Loading...</div>');
        showModal($modal);

        $.ajax({
            url: buildUrl(urls.viewTemplate, itemId),
            method: 'GET',
            headers: ajaxHeaders()
        }).done(function (response) {
            if (!response || !response.success) {
                $body.html('<div class="alert alert-danger mb-0">' + escapeHtml((response && response.message) || 'Could not load checklist.') + '</div>');
                return;
            }
            $('#dclViewModalLabel').text((response.item && response.item.name) || 'Checklist details');
            $body.html(response.html || '');
        }).fail(function (xhr) {
            $body.html('<div class="alert alert-danger mb-0">' + escapeHtml((xhr.responseJSON && xhr.responseJSON.message) || 'Could not load checklist.') + '</div>');
        });
    }

    function deleteItem(itemId) {
        if (!itemId) {
            return;
        }

        if (!window.confirm('Are you sure you want to delete this checklist? Related data may also be removed.')) {
            return;
        }

        $.ajax({
            url: (typeof site_url !== 'undefined' ? site_url : '') + '/delete_action',
            method: 'POST',
            headers: ajaxHeaders(),
            data: {
                id: itemId,
                table: 'document_checklists'
            }
        }).done(function (resp) {
            var obj = typeof resp === 'string' ? JSON.parse(resp) : resp;
            if (obj.status == 1) {
                showFlashMessage(obj.message || 'Checklist deleted successfully.', 'success');
                loadList(state.page);
            } else {
                showFlashMessage(obj.message || 'Could not delete checklist.', 'error');
            }
        }).fail(function () {
            showFlashMessage('Could not delete checklist.', 'error');
        });
    }

    function submitForm($form, url, $alert, $submit, onSuccess) {
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
            $alert.removeClass('d-none').text((xhr.responseJSON && xhr.responseJSON.message) || 'Save failed. Please try again.');
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

    $('#dcl-search-btn').on('click', function () {
        state.search = $.trim($searchInput.val());
        state.page = 1;
        loadList(1);
    });

    $('#dcl-search-clear').on('click', function () {
        $searchInput.val('');
        state.search = '';
        state.page = 1;
        loadList(1);
    });

    $searchInput.on('keydown', function (event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            $('#dcl-search-btn').trigger('click');
        }
    });

    $(document).on('click', '#dcl-list-pagination a', function (event) {
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
        loadList(state.page);
    });

    $('#dcl-add-btn').on('click', openCreateModal);

    $(document).on('click', '.dcl-edit-btn', function () {
        openEditModal($(this).data('dcl-id'));
    });

    $(document).on('click', '.dcl-view-btn', function () {
        openViewModal($(this).data('dcl-id'));
    });

    $(document).on('click', '.dcl-delete-btn', function () {
        deleteItem($(this).data('dcl-id'));
    });

    $('#dcl-edit-from-view').on('click', function () {
        var itemId = state.currentItemId;
        if (!itemId) {
            return;
        }
        hideModal($('#dclViewModal'));
        setTimeout(function () {
            openEditModal(itemId);
        }, 250);
    });

    $('#dcl-view-from-edit').on('click', function () {
        var itemId = state.editItemId;
        if (!itemId) {
            return;
        }
        hideModal($('#dclEditModal'));
        setTimeout(function () {
            openViewModal(itemId);
        }, 250);
    });

    $('#dcl-create-form').on('submit', function (event) {
        event.preventDefault();
        submitForm($(this), urls.store, $('#dcl-create-alert'), $('#dcl-create-submit'), function (response) {
            hideModal($('#dclCreateModal'));
            showFlashMessage(response.message, 'success');
            loadList(1);
        });
    });

    $('#dcl-edit-form').on('submit', function (event) {
        event.preventDefault();
        var itemId = state.editItemId || $('#dcl_edit_id').val();
        if (!itemId) {
            return;
        }
        submitForm(
            $(this),
            buildUrl(urls.updateTemplate, itemId),
            $('#dcl-edit-alert'),
            $('#dcl-edit-submit'),
            function (response) {
                hideModal($('#dclEditModal'));
                showFlashMessage(response.message, 'success');
                loadList(state.page);
            }
        );
    });

    handleDeepLinkAction();
})(jQuery);
