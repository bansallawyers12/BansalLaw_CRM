(function ($) {
    'use strict';

    var $app = $('#mat-admin-app');
    if (!$app.length) {
        return;
    }

    var PAGE_SIZE = 20;
    var infiniteBound = false;

    var state = {
        search: String($app.data('initial-search') || ''),
        page: 1,
        lastPage: 1,
        total: 0,
        loaded: 0,
        loading: false,
        loadingMore: false,
        currentItemId: null,
        editItemId: null
    };

    (function initScrollMetaFromDom() {
        var $status = $('#mat-list-scroll-status');
        if (!$status.length) {
            return;
        }
        state.page = parseInt($status.attr('data-current-page'), 10) || 1;
        state.lastPage = parseInt($status.attr('data-last-page'), 10) || 1;
        state.total = parseInt($status.attr('data-total'), 10) || 0;
        state.loaded = parseInt($status.attr('data-loaded'), 10) || 0;
    })();

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
    var $infiniteLoader = $('#mat-infinite-loader');
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
        if (typeof window.showCrmFlash === 'function') {
            window.showCrmFlash(escapeHtml(message), type === 'success' ? 'success' : 'danger', $('.server-error'));
            return;
        }
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

    function setInfiniteLoader(isLoading) {
        state.loadingMore = isLoading;
        if (!$infiniteLoader.length) {
            return;
        }
        $infiniteLoader.toggleClass('d-none', !isLoading);
        $infiniteLoader.prop('hidden', !isLoading);
        $infiniteLoader.attr('aria-hidden', isLoading ? 'false' : 'true');
    }

    function hasMoreMatters() {
        return state.page < state.lastPage;
    }

    function updateScrollStatus(response) {
        state.page = parseInt(response.currentPage, 10) || state.page;
        state.lastPage = parseInt(response.lastPage, 10) || state.lastPage;
        state.total = parseInt(response.total, 10) || 0;
        state.loaded = parseInt(response.loaded, 10) || $('#mat-list-tbody .mat-data-row').length;

        var $status = $('#mat-list-scroll-status');
        if (!$status.length) {
            if (response.status) {
                $listFooter.html(response.status);
            }
            return;
        }

        $status.attr('data-current-page', String(state.page));
        $status.attr('data-last-page', String(state.lastPage));
        $status.attr('data-total', String(state.total));
        $status.attr('data-loaded', String(state.loaded));
        $status.attr('data-has-more', response.hasMore ? '1' : '0');

        if (state.total > 0) {
            $status.find('[data-mat-loaded-count]').text(String(state.loaded));
            $status.find('[data-scroll-more-hint]').toggle(!!response.hasMore);
            $status.find('[data-scroll-end-hint]').toggle(!response.hasMore);
        }
    }

    function updateBrowserUrl() {
        if (!window.history || !window.history.replaceState) {
            return;
        }
        var params = new URLSearchParams();
        if (state.search) {
            params.set('search_by', state.search);
        }
        var query = params.toString();
        window.history.replaceState({ matSearch: state.search }, '', urls.index + (query ? ('?' + query) : ''));
    }

    function appendRows(rowsHtml) {
        var $tbody = $('#mat-list-tbody');
        if (!$tbody.length || !rowsHtml) {
            return;
        }

        $('#mat-empty-row').remove();
        $tbody.addClass('tdata');

        var $rows = $(rowsHtml);
        $rows.each(function () {
            var rowId = this.id;
            if (rowId && $tbody[0].querySelector('#' + CSS.escape(rowId))) {
                return;
            }
            $tbody.append(this);
        });
    }

    function loadList(options) {
        options = options || {};
        var append = !!options.append;

        if (state.loading || (append && (state.loadingMore || !hasMoreMatters()))) {
            return;
        }

        var requestPage = append ? state.page + 1 : 1;

        if (append) {
            setInfiniteLoader(true);
        } else {
            setLoading(true);
        }

        $.ajax({
            url: urls.index,
            method: 'GET',
            headers: ajaxHeaders(),
            data: {
                search_by: state.search,
                page: requestPage,
                per_page: PAGE_SIZE,
                append: append ? 1 : 0
            }
        }).done(function (response) {
            if (!response || !response.success) {
                showFlashMessage((response && response.message) || 'Failed to load matters.', 'error');
                return;
            }

            if (append) {
                appendRows(response.rows || '');
                updateScrollStatus(response);
            } else {
                $listContent.html(response.html || '');
                if (response.status) {
                    $listFooter.html(response.status);
                }
                state.page = parseInt(response.currentPage, 10) || 1;
                state.lastPage = parseInt(response.lastPage, 10) || 1;
                state.total = parseInt(response.total, 10) || 0;
                state.loaded = parseInt(response.loaded, 10) || 0;
            }
            updateBrowserUrl();
            window.requestAnimationFrame(maybeLoadMoreMatters);
        }).fail(function (xhr) {
            showFlashMessage((xhr.responseJSON && xhr.responseJSON.message) || 'Failed to load matters.', 'error');
        }).always(function () {
            if (append) {
                setInfiniteLoader(false);
            } else {
                setLoading(false);
            }
        });
    }

    function maybeLoadMoreMatters() {
        if (state.loading || state.loadingMore || !hasMoreMatters()) {
            return;
        }
        var scrollBottom = window.innerHeight + window.scrollY;
        var triggerLine = document.documentElement.scrollHeight - 320;
        if (scrollBottom >= triggerLine) {
            loadList({ append: true });
        }
    }

    function bindInfiniteScroll() {
        if (infiniteBound || $app.attr('data-infinite-scroll') !== '1') {
            return;
        }
        infiniteBound = true;
        $(window).on('scroll.matInfinite resize.matInfinite', function () {
            maybeLoadMoreMatters();
        });
        window.requestAnimationFrame(maybeLoadMoreMatters);
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
                loadList();
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
        loadList();
    });

    $('#mat-search-clear').on('click', function () {
        $searchInput.val('');
        state.search = '';
        loadList();
    });

    $searchInput.on('keydown', function (event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            $('#mat-search-btn').trigger('click');
        }
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
            loadList();
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
                loadList();
            }
        );
    });

    handleDeepLinkAction();
    bindInfiniteScroll();
})(jQuery);
