(function ($) {
    'use strict';

    var $app = $('#mat-admin-app');
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

    var $listContent = $('#mat-list-content');
    var $listFooter = $('#mat-list-footer');
    var $listLoading = $('#mat-list-loading');
    var $searchInput = $('#mat-search-input');

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
        window.history.replaceState({ matSearch: state.search }, '', urls.index + (query ? ('?' + query) : ''));
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
                showFlashMessage((response && response.message) || 'Failed to load matters.', 'error');
                return;
            }
            $listContent.html(response.html || '');
            $listFooter.html(response.pagination || '');
            updateBrowserUrl();
        }).fail(function (xhr) {
            showFlashMessage((xhr.responseJSON && xhr.responseJSON.message) || 'Failed to load matters.', 'error');
        }).always(function () {
            setLoading(false);
        });
    }

    function clearFormErrors($form, $alert) {
        $alert.addClass('d-none').text('');
        $form.find('.field-error').text('');
        $form.find('.is-invalid').removeClass('is-invalid');
    }

    function expandAccordionSection($form, fieldName) {
        var $input = $form.find('[name="' + fieldName + '"]');
        if (!$input.length) {
            return;
        }
        var $collapse = $input.closest('.accordion-body.collapse');
        if ($collapse.length && !$collapse.hasClass('show')) {
            $collapse.addClass('show');
            var targetId = $collapse.attr('id');
            if (targetId) {
                $form.find('[data-bs-target="#' + targetId + '"]').attr('aria-expanded', 'true').removeClass('collapsed');
            }
        }
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
                expandAccordionSection($form, field);
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
        var $modal = $('#matCreateModal');
        var $body = $('#mat-create-form-body');
        var $alert = $('#mat-create-alert');

        $body.html('<div class="text-center py-5 text-muted"><div class="spinner-border spinner-border-sm me-2" role="status"></div>Loading form...</div>');
        clearFormErrors($('#mat-create-form'), $alert);
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
        var $modal = $('#matEditModal');
        var $body = $('#mat-edit-form-body');
        var $alert = $('#mat-edit-alert');

        state.editItemId = itemId;
        $('#mat_edit_id').val(itemId);
        $body.html('<div class="text-center py-5 text-muted"><div class="spinner-border spinner-border-sm me-2" role="status"></div>Loading form...</div>');
        clearFormErrors($('#mat-edit-form'), $alert);
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
            $('#matEditModalLabel').text('Edit ' + (response.matter && response.matter.title ? response.matter.title : 'matter'));
            $body.html(response.html || '');
        }).fail(function (xhr) {
            $body.html('<div class="alert alert-danger mb-0">' + escapeHtml((xhr.responseJSON && xhr.responseJSON.message) || 'Could not load edit form.') + '</div>');
        });
    }

    function openViewModal(itemId) {
        var $modal = $('#matViewModal');
        var $body = $('#mat-view-body');

        state.currentItemId = itemId;
        $body.html('<div class="text-center py-5 text-muted"><div class="spinner-border spinner-border-sm me-2" role="status"></div>Loading...</div>');
        showModal($modal);

        $.ajax({
            url: buildUrl(urls.viewTemplate, itemId),
            method: 'GET',
            headers: ajaxHeaders()
        }).done(function (response) {
            if (!response || !response.success) {
                $body.html('<div class="alert alert-danger mb-0">' + escapeHtml((response && response.message) || 'Could not load matter.') + '</div>');
                return;
            }
            $('#matViewModalLabel').text((response.matter && response.matter.title) || 'Matter details');
            $body.html(response.html || '');
        }).fail(function (xhr) {
            $body.html('<div class="alert alert-danger mb-0">' + escapeHtml((xhr.responseJSON && xhr.responseJSON.message) || 'Could not load matter.') + '</div>');
        });
    }

    function deleteItem(itemId) {
        if (!itemId) {
            return;
        }

        if (!window.confirm('Are you sure you want to delete this matter? Related data may also be removed.')) {
            return;
        }

        $.ajax({
            url: (typeof site_url !== 'undefined' ? site_url : '') + '/delete_action',
            method: 'POST',
            headers: ajaxHeaders(),
            data: {
                id: itemId,
                table: 'matters'
            }
        }).done(function (resp) {
            var obj = typeof resp === 'string' ? JSON.parse(resp) : resp;
            if (obj.status == 1) {
                showFlashMessage(obj.message || 'Matter deleted successfully.', 'success');
                loadList(state.page);
            } else {
                showFlashMessage(obj.message || 'Could not delete matter.', 'error');
            }
        }).fail(function () {
            showFlashMessage('Could not delete matter.', 'error');
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

    $('#mat-search-btn').on('click', function () {
        state.search = $.trim($searchInput.val());
        state.page = 1;
        loadList(1);
    });

    $('#mat-search-clear').on('click', function () {
        $searchInput.val('');
        state.search = '';
        state.page = 1;
        loadList(1);
    });

    $searchInput.on('keydown', function (event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            $('#mat-search-btn').trigger('click');
        }
    });

    $(document).on('click', '#mat-list-pagination a', function (event) {
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

    $('#mat-add-btn').on('click', openCreateModal);

    $(document).on('click', '.mat-edit-btn', function () {
        openEditModal($(this).data('mat-id'));
    });

    $(document).on('click', '.mat-view-btn', function () {
        openViewModal($(this).data('mat-id'));
    });

    $(document).on('click', '.mat-delete-btn', function () {
        deleteItem($(this).data('mat-id'));
    });

    $('#mat-edit-from-view').on('click', function () {
        var itemId = state.currentItemId;
        if (!itemId) {
            return;
        }
        hideModal($('#matViewModal'));
        setTimeout(function () {
            openEditModal(itemId);
        }, 250);
    });

    $('#mat-view-from-edit').on('click', function () {
        var itemId = state.editItemId;
        if (!itemId) {
            return;
        }
        hideModal($('#matEditModal'));
        setTimeout(function () {
            openViewModal(itemId);
        }, 250);
    });

    $('#mat-create-form').on('submit', function (event) {
        event.preventDefault();
        submitForm($(this), urls.store, $('#mat-create-alert'), $('#mat-create-submit'), function (response) {
            hideModal($('#matCreateModal'));
            showFlashMessage(response.message, 'success');
            loadList(1);
        });
    });

    $('#mat-edit-form').on('submit', function (event) {
        event.preventDefault();
        var itemId = state.editItemId || $('#mat_edit_id').val();
        if (!itemId) {
            return;
        }
        submitForm(
            $(this),
            buildUrl(urls.updateTemplate, itemId),
            $('#mat-edit-alert'),
            $('#mat-edit-submit'),
            function (response) {
                hideModal($('#matEditModal'));
                showFlashMessage(response.message, 'success');
                loadList(state.page);
            }
        );
    });

    handleDeepLinkAction();
})(jQuery);
