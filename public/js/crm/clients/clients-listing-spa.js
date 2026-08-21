/**
 * Clients listing SPA — no full page reloads for tabs, filters, pagination, bulk actions.
 * Loaded only on /clients index; handles Clients / Archived / Leads tab swaps via AJAX.
 */
(function ($, window) {
    'use strict';

    var SPA_PATHS = ['/clients', '/archived', '/leads'];
    var clickedOrder = [];
    var clickedIds = [];
    var isLoading = false;
    var isLoadingMore = false;
    var spaEventsBound = false;
    var infiniteScrollBound = false;

    function cfg() {
        return window.ClientsListingSpaConfig || {};
    }

    function $root() {
        return $('#clients-listing-spa-root');
    }

    function usesInfiniteScroll() {
        return $root().attr('data-infinite-scroll') === '1';
    }

    function setInfiniteLoader(visible) {
        var $loader = $root().find('#clientsInfiniteLoader');
        if ($loader.length) {
            $loader.prop('hidden', !visible);
        }
    }

    function hasMoreClients() {
        if (!usesInfiniteScroll()) {
            return false;
        }
        var current = parseInt($root().attr('data-current-page'), 10) || 1;
        var last = parseInt($root().attr('data-last-page'), 10) || 1;
        return current < last;
    }

    function loadMoreClients() {
        if (!usesInfiniteScroll() || isLoading || isLoadingMore || !hasMoreClients()) {
            return;
        }

        var $r = $root();
        var current = parseInt($r.attr('data-current-page'), 10) || 1;
        var nextPage = current + 1;
        var url = new URL(window.location.href);
        url.searchParams.set('page', String(nextPage));
        url.searchParams.set('per_page', '20');

        isLoadingMore = true;
        setInfiniteLoader(true);

        $.ajax({
            url: url.href,
            method: 'GET',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' },
            success: function (html) {
                var $newRoot = extractSpaRootFromHtml(html);
                if (!$newRoot.length) {
                    return;
                }

                var lastPage = parseInt($newRoot.attr('data-last-page'), 10) || nextPage;
                var $rows = $newRoot.find('tbody.tdata tr.client-data-row');
                var $tbody = $r.find('tbody.tdata');
                var appended = 0;

                $rows.each(function () {
                    var rowId = this.id;
                    if (rowId && $tbody.find('#' + rowId).length) {
                        return;
                    }
                    $tbody.append(this);
                    appended += 1;
                });

                $r.attr('data-current-page', String(nextPage));
                $r.attr('data-last-page', String(lastPage));
                updateBulkSelectionUI();

                if (!appended && nextPage >= lastPage) {
                    setInfiniteLoader(false);
                }
            },
            error: function () {
                // Keep current page so the user can retry by scrolling again.
            },
            complete: function () {
                isLoadingMore = false;
                setInfiniteLoader(false);
                window.requestAnimationFrame(maybeLoadMoreClients);
            }
        });
    }

    function maybeLoadMoreClients() {
        if (!usesInfiniteScroll() || isLoading || isLoadingMore || !hasMoreClients()) {
            return;
        }
        var scrollBottom = window.innerHeight + window.scrollY;
        var triggerLine = document.documentElement.scrollHeight - 280;
        if (scrollBottom >= triggerLine) {
            loadMoreClients();
        }
    }

    function bindInfiniteScroll() {
        if (infiniteScrollBound) {
            return;
        }
        infiniteScrollBound = true;
        $(window).on('scroll.clientsInfinite resize.clientsInfinite', function () {
            maybeLoadMoreClients();
        });
    }

    function clientsSwalBase() {
        return { customClass: { popup: 'clients-swal-popup' }, buttonsStyling: true, reverseButtons: true, focusCancel: true };
    }

    function clientsSwalConfirm(options) {
        if (typeof Swal === 'undefined') {
            var fallback = options.text || options.title || 'Are you sure?';
            return Promise.resolve({ isConfirmed: window.confirm(fallback) });
        }
        return Swal.fire($.extend({}, clientsSwalBase(), {
            title: options.title || 'Confirm',
            text: options.text || undefined,
            html: options.html || undefined,
            icon: options.icon || 'question',
            showCancelButton: true,
            confirmButtonText: options.confirmText || 'Yes',
            cancelButtonText: options.cancelText || 'Cancel',
            confirmButtonColor: options.confirmColor || '#1e3d60',
            cancelButtonColor: options.cancelColor || '#5e7a90'
        }));
    }

    function clientsSwalAlert(options) {
        if (typeof Swal === 'undefined') {
            window.alert(options.text || options.title || '');
            return Promise.resolve();
        }
        return Swal.fire($.extend({}, clientsSwalBase(), {
            title: options.title || '',
            text: options.text || undefined,
            html: options.html || undefined,
            icon: options.icon || 'info',
            confirmButtonText: options.confirmText || 'OK',
            confirmButtonColor: options.confirmColor || '#1e3d60',
            timer: options.timer,
            showConfirmButton: !options.timer
        }));
    }

    window.clientsSwalConfirm = clientsSwalConfirm;
    window.clientsSwalAlert = clientsSwalAlert;

    function isSpaPath(pathname) {
        return SPA_PATHS.indexOf(pathname) !== -1;
    }

    function resetSelectionState() {
        clickedOrder = [];
        clickedIds = [];
    }

    function updateBulkSelectionUI() {
        var $r = $root();
        if (!$r.length) {
            return;
        }

        var $rowChecks = $r.find('.cb-element');
        var checkedCount = $rowChecks.filter(':checked').length;
        var totalRows = $rowChecks.length;
        var $bulkBar = $r.find('#clientsBulkBar');
        var $bulkActions = $r.find('.is_checked_client');
        var $mergeAction = $r.find('.is_checked_client_merge');
        var $selectAll = $r.find('#checkbox-all')[0];

        $r.find('#selectedCount').text(checkedCount);

        $r.find('tbody tr.client-data-row').each(function () {
            var isChecked = $(this).find('.cb-element').is(':checked');
            $(this).toggleClass('is-selected', isChecked);
        });

        if ($selectAll) {
            if (checkedCount === 0) {
                $selectAll.checked = false;
                $selectAll.indeterminate = false;
            } else if (checkedCount >= totalRows && totalRows > 0) {
                $selectAll.checked = true;
                $selectAll.indeterminate = false;
            } else {
                $selectAll.checked = false;
                $selectAll.indeterminate = true;
            }
        }

        if ($bulkBar.length) {
            if (checkedCount > 0) {
                $bulkBar.addClass('is-visible');
                $bulkActions.show();
            } else {
                $bulkBar.removeClass('is-visible');
                $bulkActions.hide();
            }
        }

        if ($mergeAction.length) {
            $mergeAction.toggle(checkedCount === 2);
        }
    }

    function removeClientRow(clientId) {
        $root().find('#id_' + clientId).fadeOut(200, function () {
            $(this).remove();
            updateBulkSelectionUI();
        });
    }

    function setLoading(loading) {
        isLoading = loading;
        $root().toggleClass('is-spa-loading', loading);
    }

    function extractSpaRootFromHtml(html) {
        var $parsed = $('<div>').append($.parseHTML(html, document, true));
        var $newRoot = $parsed.find('#clients-listing-spa-root').first();
        if (!$newRoot.length) {
            $newRoot = $parsed.find('[data-spa-root="1"]').first();
        }
        return $newRoot;
    }

    function loadListing(url, pushState) {
        if (isLoading) {
            return;
        }

        var resolved = new URL(url, window.location.origin);
        if (!isSpaPath(resolved.pathname)) {
            window.location.href = resolved.href;
            return;
        }

        setLoading(true);

        $.ajax({
            url: resolved.href,
            method: 'GET',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' },
            success: function (html) {
                var $newRoot = extractSpaRootFromHtml(html);
                if (!$newRoot.length) {
                    window.location.href = resolved.href;
                    return;
                }

                $newRoot.attr('id', 'clients-listing-spa-root').attr('data-spa-root', '1');
                if (resolved.pathname === '/clients' || resolved.pathname === '/leads' || resolved.pathname === '/archived') {
                    $newRoot.addClass('clients-listing');
                }
                $newRoot.removeClass('clients-listing--leads clients-listing--archived');
                if (resolved.pathname === '/leads') {
                    $newRoot.addClass('clients-listing--leads');
                }
                if (resolved.pathname === '/archived') {
                    $newRoot.addClass('clients-listing--archived');
                }

                $root().replaceWith($newRoot);

                if (pushState !== false) {
                    history.pushState({ clientsListingSpa: true, url: resolved.href }, '', resolved.href);
                }

                resetSelectionState();
                initPanel();
                window.requestAnimationFrame(maybeLoadMoreClients);
            },
            error: function () {
                clientsSwalAlert({
                    icon: 'error',
                    title: 'Could not load',
                    text: 'Unable to load the listing. Please try again.'
                });
            },
            complete: function () {
                setLoading(false);
            }
        });
    }

    function initPanel() {
        var $r = $root();
        if (!$r.length) {
            return;
        }

        if ($r.find('.filter-count-badge').length || $r.find('.active-filters-badge').length) {
            $r.find('.filter_panel').show();
        }

        updateBulkSelectionUI();
        if (usesInfiniteScroll()) {
            bindInfiniteScroll();
            window.requestAnimationFrame(maybeLoadMoreClients);
        }
    }

    function bindSpaEvents() {
        if (spaEventsBound) {
            return;
        }
        spaEventsBound = true;

        $(document).on('click.clientsListingSpa', '#clients-listing-spa-root .clients-tabs .nav-link, #clients-listing-spa-root #client_tabs .nav-link', function (e) {
            var href = $(this).attr('href');
            if (!href || href.indexOf('javascript') === 0 || href === '#') {
                return;
            }
            var path = new URL(href, window.location.origin).pathname;
            if (!isSpaPath(path)) {
                return;
            }
            e.preventDefault();
            loadListing(href);
        });

        $(document).on('click.clientsListingSpa', '#clients-listing-spa-root .pagination a', function (e) {
            var href = $(this).attr('href');
            if (!href || href === '#') {
                return;
            }
            e.preventDefault();
            loadListing(href);
        });

        $(document).on('click.clientsListingSpa', '#clients-listing-spa-root .sortable-header a', function (e) {
            var href = $(this).attr('href');
            if (!href) {
                return;
            }
            e.preventDefault();
            loadListing(href);
        });

        $(document).on('change.clientsListingSpa', '#clients-listing-spa-root #per_page', function () {
            if (usesInfiniteScroll()) {
                return;
            }
            var url = new URL(window.location.href);
            url.searchParams.set('per_page', $(this).val());
            url.searchParams.delete('page');
            loadListing(url.toString());
        });

        $(document).on('submit.clientsListingSpa', '#clients-listing-spa-root #filterForm, #clients-listing-spa-root #leadFilterForm', function (e) {
            e.preventDefault();
            var qs = $(this).serialize();
            var base = $(this).attr('action') || window.location.pathname;
            var url = new URL(base + (qs ? '?' + qs : ''), window.location.origin);
            if (usesInfiniteScroll() || url.pathname === '/clients') {
                url.searchParams.set('per_page', '20');
                url.searchParams.delete('page');
            }
            loadListing(url.toString());
        });

        $(document).on('click.clientsListingSpa', '#clients-listing-spa-root #clearFilters', function (e) {
            e.preventDefault();
            loadListing(cfg().routes.clientsIndex || '/clients');
        });

        $(document).on('click.clientsListingSpa', '#clients-listing-spa-root #clearLeadFilters', function (e) {
            e.preventDefault();
            loadListing(cfg().routes.leadsIndex || '/leads');
        });

        $(document).on('click.clientsListingSpa', '#clients-listing-spa-root .quick-filter-chip', function () {
            var filter = $(this).data('filter');
            var $form = $(this).closest('form');
            if ($form.length) {
                $form.find('input[name="quick_date_range"]').val(filter);
                $form.find('input[name="from_date"], input[name="to_date"]').val('');
                $form.trigger('submit');
            }
        });

        $(document).on('change.clientsListingSpa', '#clients-listing-spa-root input[name="from_date"], #clients-listing-spa-root input[name="to_date"]', function () {
            $(this).closest('form').find('input[name="quick_date_range"]').val('');
        });

        $(document).on('click.clientsListingSpa', '#clients-listing-spa-root .filter_btn', function () {
            $root().find('.filter_panel').slideToggle(200);
        });

        $(document).on('change.clientsListingSpa', '#clients-listing-spa-root [data-checkboxes]', function () {
            var me = $(this);
            var group = me.data('checkboxes');
            var role = me.data('checkbox-role');
            var all = $root().find('[data-checkboxes="' + group + '"]:not([data-checkbox-role="dad"])');
            var checked = all.filter(':checked');
            var dad = $root().find('[data-checkboxes="' + group + '"][data-checkbox-role="dad"]');

            if (role === 'dad') {
                all.prop('checked', me.is(':checked'));
            } else if (checked.length >= all.length && all.length > 0) {
                dad.prop('checked', true);
            } else {
                dad.prop('checked', false);
            }
            updateBulkSelectionUI();
        });

        $(document).on('change.clientsListingSpa', '#clients-listing-spa-root .cb-element', function () {
            updateBulkSelectionUI();
        });

        $(document).on('click.clientsListingSpa', '#clients-listing-spa-root .your-checkbox', function () {
            var clicked_id = $(this).data('id');
            var nameStr = $(this).attr('data-name');
            var clientidStr = $(this).attr('data-clientid');
            var finalStr = nameStr + '(' + clientidStr + ')';
            if ($(this).is(':checked')) {
                if (clickedOrder.indexOf(finalStr) === -1) {
                    clickedOrder.push(finalStr);
                }
                if (clickedIds.indexOf(clicked_id) === -1) {
                    clickedIds.push(clicked_id);
                }
            } else {
                clickedOrder = clickedOrder.filter(function (s) { return s !== finalStr; });
                clickedIds = clickedIds.filter(function (id) { return id !== clicked_id; });
            }
        });

        $(document).on('click.clientsListingSpa', '#clients-listing-spa-root .is_checked_client_merge', function () {
            if (clickedOrder.length !== 2) {
                return;
            }
            var mergeStr = 'Merge <strong>' + clickedOrder[0] + '</strong> into <strong>' + clickedOrder[1] + '</strong>?';
            clientsSwalConfirm({
                title: 'Merge ' + recordTypeLabelPlural() + '?',
                html: mergeStr + '<br><br>This action cannot be undone.',
                icon: 'warning',
                confirmText: 'Yes, merge',
                confirmColor: '#a83020'
            }).then(function (result) {
                if (!result.isConfirmed) {
                    return;
                }
                $.ajax({
                    type: 'post',
                    url: cfg().routes.mergeRecords,
                    headers: { 'X-CSRF-TOKEN': cfg().csrfToken },
                    data: { merge_from: clickedIds[0], merge_into: clickedIds[1] },
                    success: function () {
                        removeClientRow(clickedIds[0]);
                        resetSelectionState();
                        $root().find('.cb-element').prop('checked', false);
                        updateBulkSelectionUI();
                        clientsSwalAlert({
                            icon: 'success',
                            title: 'Merged',
                            text: recordTypeLabel() + ' records merged successfully.',
                            timer: 2000
                        });
                    },
                    error: function () {
                        clientsSwalAlert({
                            icon: 'error',
                            title: 'Merge failed',
                            text: 'Could not merge ' + recordTypeLabelPlural() + '.'
                        });
                    }
                });
            });
        });

        $(document).on('click.clientsListingSpa', '#clients-listing-spa-root .emailmodal', function () {
            openEmailModalForChecked();
        });

        $(document).on('click.clientsListingSpa', '#clients-listing-spa-root .clientemail', function () {
            openEmailModalForSingle($(this));
        });

        $(document).on('change.clientsListingSpa', '#clients-listing-spa-root .selecttemplate', function () {
            var v = $(this).val();
            $.ajax({
                url: cfg().routes.getTemplates,
                type: 'GET',
                dataType: 'json',
                data: { id: v },
                success: function (response) {
                    var res = typeof response === 'string' ? JSON.parse(response) : response;
                    $('.selectedsubject').val(res.subject);
                    $('.tinymce-editor').each(function () {
                        var editorId = $(this).attr('id');
                        if (editorId && typeof tinymce !== 'undefined' && tinymce.get(editorId)) {
                            tinymce.get(editorId).setContent(res.description || '');
                        } else {
                            $(this).val(res.description || '');
                        }
                    });
                }
            });
        });

        window.addEventListener('popstate', function () {
            if (isSpaPath(window.location.pathname)) {
                loadListing(window.location.href, false);
            }
        });
    }

    function recordTypeLabel() {
        return window.location.pathname === '/leads' ? 'Lead' : 'Client';
    }

    function recordTypeLabelPlural() {
        return window.location.pathname === '/leads' ? 'leads' : 'clients';
    }

    function openEmailModalForChecked() {
        var array = [];
        var data = [];
        $root().find('.cb-element:checked').each(function () {
            var id = $(this).attr('data-id');
            array.push(id);
            data.push({
                id: id,
                name: $(this).attr('data-name'),
                email: $(this).attr('data-email'),
                status: recordTypeLabel()
            });
        });
        showEmailModal(array, data);
    }

    function openEmailModalForSingle($el) {
        var id = $el.attr('data-id');
        showEmailModal([id], [{
            id: id,
            name: $el.attr('data-name'),
            email: $el.attr('data-email'),
            status: recordTypeLabel()
        }]);
    }

    function showEmailModal(array, data) {
        $('#emailmodal').modal('show');
        var $to = $('#emailmodal .js-data-example-ajax');
        if ($to.length && typeof initRecipientsMultiTomSelectPreload === 'function') {
            initRecipientsMultiTomSelectPreload($to[0], {
                url: cfg().routes.getRecipients,
                dropdownParent: '#emailmodal',
                options: data,
                items: array
            });
        }
    }

    window.archiveClientAction = function (event, clientName) {
        event.preventDefault();
        var form = jQuery(event.target).closest('.archive-client-form')[0];
        if (!form) {
            return false;
        }

        var $row = jQuery(form).closest('tr');
        var safeName = clientName || 'this client';

        clientsSwalConfirm({
            title: 'Archive client?',
            html: 'Are you sure you want to archive <strong>' + safeName + '</strong>?<br><br>This will move the client to the archived list.',
            icon: 'warning',
            confirmText: 'Yes, archive',
            confirmColor: '#a83020'
        }).then(function (result) {
            if (!result.isConfirmed) {
                return;
            }

            $.ajax({
                url: form.action,
                method: 'POST',
                data: $(form).serialize(),
                headers: { 'X-CSRF-TOKEN': cfg().csrfToken, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                success: function (data) {
                    $row.fadeOut(200, function () {
                        $(this).remove();
                        updateBulkSelectionUI();
                    });
                    clientsSwalAlert({
                        icon: 'success',
                        title: 'Archived',
                        text: (data && data.message) ? data.message : 'Client archived successfully.',
                        timer: 2000
                    });
                },
                error: function (xhr) {
                    var message = 'Could not archive client.';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        message = xhr.responseJSON.message;
                    }
                    clientsSwalAlert({ icon: 'error', title: 'Archive failed', text: message });
                }
            });
        });

        return false;
    };

    window.confirmArchiveLead = function (event, leadName) {
        event.preventDefault();
        var form = jQuery(event.target).closest('.archive-lead-form')[0];
        if (!form) {
            return false;
        }

        var $row = jQuery(form).closest('tr');
        var safeName = leadName || 'this lead';

        clientsSwalConfirm({
            title: 'Archive lead?',
            html: 'Are you sure you want to archive <strong>' + safeName + '</strong>?<br><br>This will move the lead to the archived list.',
            icon: 'warning',
            confirmText: 'Yes, archive',
            confirmColor: '#a83020'
        }).then(function (result) {
            if (!result.isConfirmed) {
                return;
            }

            $.ajax({
                url: form.action,
                method: 'POST',
                data: $(form).serialize(),
                headers: { 'X-CSRF-TOKEN': cfg().csrfToken, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                success: function (data) {
                    $row.fadeOut(200, function () {
                        $(this).remove();
                        updateBulkSelectionUI();
                    });
                    clientsSwalAlert({
                        icon: 'success',
                        title: 'Archived',
                        text: (data && data.message) ? data.message : 'Lead archived successfully.',
                        timer: 2000
                    });
                },
                error: function (xhr) {
                    var message = 'Could not archive lead.';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        message = xhr.responseJSON.message;
                    }
                    clientsSwalAlert({ icon: 'error', title: 'Archive failed', text: message });
                }
            });
        });

        return false;
    };

    window.unarchiveArchivedClient = function (id, clientName) {
        if (!id) {
            clientsSwalAlert({ icon: 'warning', title: 'Invalid record', text: 'Please select a valid record.' });
            return;
        }

        var safeName = clientName || 'this record';
        var $row = $root().find('#id_' + id);

        clientsSwalConfirm({
            title: 'Unarchive record?',
            html: 'Are you sure you want to unarchive <strong>' + safeName + '</strong>?<br><br>This will move the record back to the active list.',
            icon: 'question',
            confirmText: 'Yes, unarchive',
            confirmColor: '#1e7a52'
        }).then(function (result) {
            if (!result.isConfirmed) {
                return;
            }

            $.ajax({
                type: 'POST',
                url: (cfg().routes.unarchive || '/unarchive') + '/' + id,
                headers: { 'X-CSRF-TOKEN': cfg().csrfToken, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                dataType: 'json',
                success: function (resp) {
                    var obj = typeof resp === 'string' ? JSON.parse(resp) : resp;
                    if (obj && obj.status === 1) {
                        $row.fadeOut(200, function () {
                            $(this).remove();
                            updateBulkSelectionUI();
                            if ($root().find('.client-data-row').length === 0) {
                                loadListing(window.location.href, false);
                            }
                        });
                        clientsSwalAlert({
                            icon: 'success',
                            title: 'Unarchived',
                            text: obj.message || 'Record unarchived successfully.',
                            timer: 2200
                        });
                    } else {
                        clientsSwalAlert({
                            icon: 'error',
                            title: 'Could not unarchive',
                            text: (obj && obj.message) ? obj.message : 'Failed to unarchive record.'
                        });
                    }
                },
                error: function (xhr) {
                    var message = 'An error occurred while unarchiving the record.';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        message = xhr.responseJSON.message;
                    }
                    clientsSwalAlert({ icon: 'error', title: 'Unarchive failed', text: message });
                }
            });
        });
    };

    function initEmailModalPickers() {
        if (typeof initTS !== 'function' || typeof buildCrmGetRecipientsMultiTomSelectConfig !== 'function') {
            return;
        }
        $('#emailmodal .js-data-example-ajax').each(function () {
            if (!this.tomselect) {
                initTS(this, buildCrmGetRecipientsMultiTomSelectConfig({
                    url: cfg().routes.getRecipients,
                    dropdownParent: '#emailmodal',
                    enableRemoteLoad: true
                }));
            }
        });
        $('#emailmodal .js-data-example-ajaxcc').each(function () {
            if (!this.tomselect) {
                initTS(this, buildCrmGetRecipientsMultiTomSelectConfig({
                    url: cfg().routes.getRecipients,
                    dropdownParent: '#emailmodal',
                    enableRemoteLoad: true
                }));
            }
        });
    }

    window.ClientsListingSpa = {
        init: function () {
            if (!$root().length) {
                return;
            }
            bindSpaEvents();
            initPanel();
            initEmailModalPickers();

            $(document).on('shown.bs.modal', '#emailmodal', function () {
                if (typeof initTS !== 'function') {
                    return;
                }
                var modalEl = this;
                $(modalEl).find('.selecttemplate').each(function () {
                    if (!this.tomselect) {
                        initTS(this, { create: false, allowEmptyOption: true, dropdownParent: modalEl });
                    }
                });
            });
            $(document).on('hidden.bs.modal', '#emailmodal', function () {
                var modalEl = this;
                $(modalEl).find('.selecttemplate').each(function () {
                    if (typeof destroyTS === 'function') {
                        destroyTS(this);
                    }
                });
            });

            history.replaceState({ clientsListingSpa: true, url: window.location.href }, '', window.location.href);
        },
        loadListing: loadListing,
        updateBulkSelectionUI: updateBulkSelectionUI
    };
})(jQuery, window);
