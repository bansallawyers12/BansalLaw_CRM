(function ($) {
    'use strict';

    var $app = $('#staff-admin-app');
    if (!$app.length) {
        return;
    }

    var state = {
        tab: $app.data('initial-tab') || 'active',
        search: String($app.data('initial-search') || ''),
        page: 1,
        loading: false,
        currentStaffId: null,
        editStaffId: null
    };

    var urls = {
        index: $app.data('index-url'),
        create: $app.data('create-url'),
        store: $app.data('store-url'),
        editTemplate: $app.data('edit-url-template'),
        updateTemplate: $app.data('update-url-template'),
        viewTemplate: $app.data('view-url-template')
    };

    var $listContent = $('#staff-list-content');
    var $listFooter = $('#staff-list-footer');
    var $listLoading = $('#staff-list-loading');
    var $searchInput = $('#staff-search-input');

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

    function syncTabButtons() {
        $('.staff-tab-btn').removeClass('active');
        $('.staff-tab-btn[data-tab="' + state.tab + '"]').addClass('active');
    }

    function updateBrowserUrl() {
        if (!window.history || !window.history.replaceState) {
            return;
        }
        var params = new URLSearchParams();
        if (state.tab && state.tab !== 'active') {
            params.set('tab', state.tab);
        }
        if (state.search) {
            params.set('search_by', state.search);
        }
        if (state.page > 1) {
            params.set('page', String(state.page));
        }
        var query = params.toString();
        var nextUrl = urls.index + (query ? ('?' + query) : '');
        window.history.replaceState({ staffTab: state.tab }, '', nextUrl);
    }

    function loadStaffList(page) {
        if (state.loading) {
            return;
        }
        if (typeof page === 'number') {
            state.page = page;
        }

        setLoading(true);
        syncTabButtons();

        $.ajax({
            url: urls.index,
            method: 'GET',
            headers: ajaxHeaders(),
            data: {
                tab: state.tab,
                search_by: state.search,
                page: state.page
            }
        }).done(function (response) {
            if (!response || !response.success) {
                showFlashMessage((response && response.message) || 'Failed to load staff list.', 'error');
                return;
            }
            $listContent.html(response.html || '');
            $listFooter.html(response.pagination || '');
            updateBrowserUrl();
        }).fail(function (xhr) {
            var message = (xhr.responseJSON && xhr.responseJSON.message) || 'Failed to load staff list.';
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

    function expandStaffSectionForField($form, field) {
        var sectionMap = {
            first_name: '_personal',
            last_name: '_personal',
            email: '_personal',
            phone: '_personal',
            country_code: '_personal',
            password: '_personal',
            password_confirmation: '_personal',
            status: '_personal',
            position: '_office',
            role: '_office',
            office: '_office',
            team: '_office',
            permission: '_access',
            show_dashboard_per: '_access',
            quick_access_enabled: '_access',
            can_delete_email_with_attachments: '_access',
            can_sync_inbox_emails: '_access',
            can_close_discontinue_matter: '_access',
            can_edit_final_invoice: '_access',
            grant_super_admin_access: '_access',
            trust_rule42_supervisor: '_access',
            is_solicitor: '_solicitor',
            marn_number: '_solicitor',
            company_name: '_solicitor',
            email_signature: '_signature'
        };

        var suffix = sectionMap[field] || '_personal';
        var prefix = $form.attr('id') === 'staff-create-form' ? 'create_staff' : 'edit_staff';
        var sectionId = prefix + suffix;
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
            expandStaffSectionForField($form, firstField);
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

    function initStaffFormBehaviors($container, fieldPrefix) {
        var prefix = fieldPrefix || 'create_staff';
        var $toggle = $container.find('#' + prefix + '_is_solicitor, .staff-is-solicitor-toggle').first();
        var $section = $container.find('#' + prefix + '_agent_details_section, .staff-agent-details').first();

        $toggle.off('change.staffForm').on('change.staffForm', function () {
            if ($(this).is(':checked')) {
                $section.slideDown(150);
            } else {
                $section.slideUp(150);
            }
        });

        $container.find('.staff-grant-super-admin').off('change.staffForm').on('change.staffForm', function () {
            var $checkbox = $(this);
            if ($checkbox.is(':disabled')) {
                return;
            }
            var isChecked = $checkbox.is(':checked');
            var message = isChecked
                ? 'Are you sure you want to grant Super admin access level to this user?'
                : 'Are you sure you want to remove Super admin access level from this user?';
            if (!window.confirm(message)) {
                $checkbox.prop('checked', !isChecked);
            }
        });

        if (typeof tinymce !== 'undefined') {
            $container.find('.tinymce-editor-full').each(function () {
                var editorId = this.id;
                if (!editorId) {
                    return;
                }
                if (tinymce.get(editorId)) {
                    tinymce.get(editorId).remove();
                }
                tinymce.init({
                    license_key: 'gpl',
                    selector: '#' + editorId,
                    height: 260,
                    menubar: false,
                    statusbar: true,
                    plugins: ['lists', 'link', 'autolink', 'wordcount', 'code'],
                    toolbar: 'undo redo | bold italic underline | bullist numlist | link | removeformat | code',
                    branding: false,
                    promotion: false,
                    setup: function (editor) {
                        editor.on('change', function () {
                            editor.save();
                        });
                    }
                });
            });
        }

        $container.find('.telephone').each(function () {
            if (typeof window.initIntlTelInput === 'function') {
                window.initIntlTelInput(this);
            }
        });
    }

    function destroyTinyMceIn($container) {
        if (typeof tinymce === 'undefined') {
            return;
        }
        $container.find('.staff-email-signature, .tinymce-editor-full').each(function () {
            var editor = tinymce.get(this.id);
            if (editor) {
                editor.remove();
            }
        });
    }

    function openCreateModal() {
        var $modal = $('#staffCreateModal');
        var $body = $('#staff-create-form-body');
        var $alert = $('#staff-create-alert');

        $body.html('<div class="text-center py-5 text-muted"><div class="spinner-border spinner-border-sm me-2" role="status"></div>Loading form...</div>');
        clearFormErrors($('#staff-create-form'), $alert);
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
            initStaffFormBehaviors($body, 'create_staff');
        }).fail(function (xhr) {
            var message = (xhr.responseJSON && xhr.responseJSON.message) || 'Could not load create form.';
            $body.html('<div class="alert alert-danger mb-0">' + escapeHtml(message) + '</div>');
        });
    }

    function openEditModal(staffId) {
        var $modal = $('#staffEditModal');
        var $body = $('#staff-edit-form-body');
        var $alert = $('#staff-edit-alert');

        state.editStaffId = staffId;
        $('#staff_edit_id').val(staffId);
        $body.html('<div class="text-center py-5 text-muted"><div class="spinner-border spinner-border-sm me-2" role="status"></div>Loading form...</div>');
        clearFormErrors($('#staff-edit-form'), $alert);
        showModal($modal);

        $.ajax({
            url: buildUrl(urls.editTemplate, staffId),
            method: 'GET',
            headers: ajaxHeaders()
        }).done(function (response) {
            if (!response || !response.success) {
                $body.html('<div class="alert alert-danger mb-0">' + escapeHtml((response && response.message) || 'Could not load form.') + '</div>');
                return;
            }
            $('#staffEditModalLabel').text('Edit ' + (response.staff && response.staff.name ? response.staff.name : 'staff member'));
            $body.html(response.html || '');
            initStaffFormBehaviors($body, 'edit_staff');
        }).fail(function (xhr) {
            var message = (xhr.responseJSON && xhr.responseJSON.message) || 'Could not load edit form.';
            $body.html('<div class="alert alert-danger mb-0">' + escapeHtml(message) + '</div>');
        });
    }

    function openViewModal(staffId) {
        var $modal = $('#staffViewModal');
        var $body = $('#staff-view-body');

        state.currentStaffId = staffId;
        $body.html('<div class="text-center py-5 text-muted"><div class="spinner-border spinner-border-sm me-2" role="status"></div>Loading profile...</div>');
        showModal($modal);

        $.ajax({
            url: buildUrl(urls.viewTemplate, staffId),
            method: 'GET',
            headers: ajaxHeaders()
        }).done(function (response) {
            if (!response || !response.success) {
                $body.html('<div class="alert alert-danger mb-0">' + escapeHtml((response && response.message) || 'Could not load staff profile.') + '</div>');
                return;
            }
            $('#staffViewModalLabel').text((response.staff && response.staff.name) || 'Staff profile');
            $body.html(response.html || '');
        }).fail(function (xhr) {
            var message = (xhr.responseJSON && xhr.responseJSON.message) || 'Could not load staff profile.';
            $body.html('<div class="alert alert-danger mb-0">' + escapeHtml(message) + '</div>');
        });
    }

    function serializeForm($form) {
        if (typeof tinymce !== 'undefined') {
            tinymce.triggerSave();
        }
        return $form.serialize();
    }

    function submitStaffForm($form, url, method, $alert, $submit, onSuccess) {
        clearFormErrors($form, $alert);
        setSubmitting($submit, true);

        $.ajax({
            url: url,
            method: method,
            headers: ajaxHeaders(),
            data: serializeForm($form)
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

    $('.staff-tab-btn').on('click', function () {
        var tab = $(this).data('tab');
        if (!tab || tab === state.tab) {
            return;
        }
        state.tab = tab;
        state.page = 1;
        loadStaffList(1);
    });

    $('#staff-search-btn').on('click', function () {
        state.search = $.trim($searchInput.val());
        state.page = 1;
        loadStaffList(1);
    });

    $('#staff-search-clear').on('click', function () {
        $searchInput.val('');
        state.search = '';
        state.page = 1;
        loadStaffList(1);
    });

    $searchInput.on('keydown', function (event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            $('#staff-search-btn').trigger('click');
        }
    });

    $(document).on('click', '#staff-list-pagination a', function (event) {
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
        if (url.searchParams.get('tab')) {
            state.tab = url.searchParams.get('tab');
        }
        loadStaffList(state.page);
    });

    $('#staff-add-btn').on('click', openCreateModal);

    $(document).on('click', '.staff-edit-btn', function () {
        openEditModal($(this).data('staff-id'));
    });

    $(document).on('click', '.staff-view-btn', function () {
        openViewModal($(this).data('staff-id'));
    });

    $('#staff-edit-from-view').on('click', function () {
        var staffId = state.currentStaffId;
        if (!staffId) {
            return;
        }
        hideModal($('#staffViewModal'));
        setTimeout(function () {
            openEditModal(staffId);
        }, 250);
    });

    $('#staff-view-from-edit').on('click', function () {
        var staffId = state.editStaffId;
        if (!staffId) {
            return;
        }
        hideModal($('#staffEditModal'));
        setTimeout(function () {
            openViewModal(staffId);
        }, 250);
    });

    $('#staff-create-form').on('submit', function (event) {
        event.preventDefault();
        var $form = $(this);
        submitStaffForm($form, urls.store, 'POST', $('#staff-create-alert'), $('#staff-create-submit'), function (response) {
            hideModal($('#staffCreateModal'));
            destroyTinyMceIn($('#staff-create-form-body'));
            showFlashMessage(response.message, 'success');
            state.tab = response.tab || 'active';
            loadStaffList(1);
        });
    });

    $('#staff-edit-form').on('submit', function (event) {
        event.preventDefault();
        var staffId = state.editStaffId || $('#staff_edit_id').val();
        if (!staffId) {
            return;
        }
        var $form = $(this);
        submitStaffForm(
            $form,
            buildUrl(urls.updateTemplate, staffId),
            'POST',
            $('#staff-edit-alert'),
            $('#staff-edit-submit'),
            function (response) {
                hideModal($('#staffEditModal'));
                destroyTinyMceIn($('#staff-edit-form-body'));
                showFlashMessage(response.message, 'success');
                state.tab = response.tab || state.tab;
                loadStaffList(state.page);
            }
        );
    });

    $('#staff-admin-app .change-status').off('change');

    $(document).on('change', '#staff-admin-app .change-status', function () {
        var id = $.trim($(this).attr('data-id'));
        var currentStatus = $.trim($(this).attr('data-status'));
        var table = $.trim($(this).attr('data-table'));
        var col = $.trim($(this).attr('data-col'));
        var $toggle = $(this);

        if (!id || table !== 'staff') {
            return;
        }

        $.ajax({
            type: 'POST',
            headers: ajaxHeaders(),
            url: (typeof site_url !== 'undefined' ? site_url : '') + '/update_action',
            data: {
                id: id,
                current_status: currentStatus,
                table: table,
                colname: col
            }
        }).done(function (resp) {
            var obj = typeof resp === 'string' ? JSON.parse(resp) : resp;
            if (obj.status == 1) {
                showFlashMessage(obj.message || 'Status updated.', 'success');
                loadStaffList(state.page);
            } else {
                showFlashMessage(obj.message || 'Could not update status.', 'error');
                $toggle.prop('checked', currentStatus == 1);
            }
        }).fail(function () {
            showFlashMessage('Could not update status.', 'error');
            $toggle.prop('checked', currentStatus == 1);
        });
    });

    $('#staffCreateModal, #staffEditModal').on('hidden.bs.modal', function () {
        var $body = $(this).find('.modal-body');
        destroyTinyMceIn($body);
    });

    handleDeepLinkAction();
})(jQuery);
