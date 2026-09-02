/**
 * Client matters listing — infinite scroll + SPA Active/Closed tabs.
 */
(function ($, window) {
    'use strict';

    var isLoadingMore = false;
    var isSpaLoading = false;
    var bound = false;
    var spaBound = false;

    function $root() {
        return $('#matters-listing-root');
    }

    function isSpaPage() {
        return $root().hasClass('matters-listing-spa');
    }

    function usesInfiniteScroll() {
        return $root().attr('data-infinite-scroll') === '1';
    }

    function currentTab() {
        if (window.MattersListSpa && typeof window.MattersListSpa.getTab === 'function') {
            return window.MattersListSpa.getTab() === 'closed' ? 'closed' : 'active';
        }
        return $root().attr('data-list-tab') === 'closed' ? 'closed' : 'active';
    }

    function tabUrl(tab) {
        var cfg = window.MattersListSpaConfig || {};
        var $r = $root();
        if (tab === 'closed') {
            return $r.attr('data-closed-url') || cfg.closedUrl || '';
        }
        return $r.attr('data-active-url') || cfg.activeUrl || '';
    }

    function updateScrollMoreHint() {
        var $hint = $root().find('[data-scroll-more-hint]');
        if (!$hint.length) {
            return;
        }
        $hint.toggle(hasMoreMatters());
    }

    function updateLoadedCountLabel() {
        var $r = $root();
        var $count = $r.find('[data-loaded-count]');
        if ($count.length) {
            $count.text(String($r.find('tbody.tdata tr.matter-data-row').length));
        }
        updateScrollMoreHint();
    }

    function setInfiniteLoader(visible) {
        var $loader = $root().find('#mattersInfiniteLoader');
        if ($loader.length) {
            $loader.prop('hidden', !visible);
        }
    }

    function setSpaLoading(visible) {
        var $loader = $('#mattersSpaLoading');
        if (!$loader.length) {
            return;
        }
        $loader.toggleClass('d-none', !visible);
        $loader.attr('aria-busy', visible ? 'true' : 'false');
    }

    function hasMoreMatters() {
        if (!usesInfiniteScroll()) {
            return false;
        }
        var current = parseInt($root().attr('data-current-page'), 10) || 1;
        var last = parseInt($root().attr('data-last-page'), 10) || 1;
        return current < last;
    }

    /** Parse HTML fragment without executing scripts (avoids re-binding handlers). */
    function extractRootFromHtml(html) {
        var $parsed = $('<div>').append($.parseHTML(html, document, false));
        var $found = $parsed.find('#matters-listing-root');
        if ($found.length) {
            return $found.first();
        }
        return $();
    }

    function buildQueryFromForm($form) {
        var params = {};
        if (!$form || !$form.length) {
            return params;
        }
        $.each($form.serializeArray(), function (_, field) {
            if (!field.name) {
                return;
            }
            if (field.value === '' || field.value == null) {
                return;
            }
            params[field.name] = field.value;
        });
        return params;
    }

    function applySpaResponse(resp, pushUrl) {
        if (!resp || typeof resp !== 'object' || !resp.html) {
            return;
        }

        $('#matters-spa-content').html(resp.html);

        var $r = $root();
        $r.attr('data-list-tab', resp.listTab || currentTab());
        $r.attr('data-current-page', String(resp.currentPage || 1));
        $r.attr('data-last-page', String(resp.lastPage || 1));

        if (resp.title) {
            $('#mattersSpaTitle').text(resp.title);
        }
        if (resp.subtitle) {
            $('#mattersSpaSubtitle').html(String(resp.subtitle).replace(/ · /g, ' &middot; '));
        }

        var tab = resp.listTab || currentTab();
        $('.matters-spa-tab').removeClass('is-active active');
        $('.matters-spa-tab[data-tab="' + tab + '"]').addClass('is-active active');

        var $icon = $('#mattersSpaIcon i');
        if ($icon.length) {
            $icon.attr('class', 'fa-solid ' + (tab === 'closed' ? 'fa-box-archive' : 'fa-folder-open'));
        }

        updateFilterToggleBadge();
        updateLoadedCountLabel();

        if (pushUrl) {
            if (window.history && window.history.pushState) {
                window.history.pushState({ mattersSpa: true, tab: tab }, '', pushUrl);
            }
        }

        window.requestAnimationFrame(maybeLoadMoreMatters);
    }

    function updateFilterToggleBadge() {
        var $form = $('#matterFilterForm');
        var count = 0;
        if ($form.length) {
            $.each($form.serializeArray(), function (_, field) {
                if (!field.name || field.name === 'sort' || field.name === 'direction' || field.name === 'page') {
                    return;
                }
                if (field.value !== '' && field.value != null) {
                    count += 1;
                }
            });
        }
        var $btn = $('#filterToggleBtn');
        $btn.toggleClass('filter_btn--active', count > 0);
        $btn.find('.filter-count-badge').remove();
        if (count > 0) {
            $btn.append('<span class="filter-count-badge">' + count + '</span>');
        }
    }

    function loadSpaContent(tab, params, options) {
        options = options || {};
        if (window.MattersListSpa && typeof window.MattersListSpa.loadTab === 'function') {
            window.MattersListSpa.loadTab(tab, params || {}, options.pushState !== false);
            return;
        }

        // Fallback if inline SPA helper is missing
        if (isSpaLoading) {
            return;
        }

        var baseUrl = tabUrl(tab);
        if (!baseUrl) {
            return;
        }

        var url = new URL(baseUrl, window.location.origin);
        Object.keys(params || {}).forEach(function (key) {
            if (params[key] !== '' && params[key] != null) {
                url.searchParams.set(key, params[key]);
            }
        });
        url.searchParams.set('spa', '1');
        url.searchParams.delete('page');

        isSpaLoading = true;
        setSpaLoading(true);

        fetch(url.href, {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
            .then(function (res) {
                if (!res.ok) {
                    throw new Error('Failed to load matters');
                }
                return res.json();
            })
            .then(function (resp) {
                var clean = new URL(url.href);
                clean.searchParams.delete('spa');
                var pushUrl = clean.pathname + (clean.searchParams.toString() ? '?' + clean.searchParams.toString() : '');
                applySpaResponse(resp, options.pushState === false ? null : pushUrl);
            })
            .catch(function () {
                if (typeof iziToast !== 'undefined') {
                    iziToast.error({ title: 'Error', message: 'Could not load matters list.', position: 'topRight' });
                } else {
                    window.crmAlert('Could not load matters list.');
                }
            })
            .finally(function () {
                isSpaLoading = false;
                setSpaLoading(false);
            });
    }

    function loadMoreMatters() {
        if (!usesInfiniteScroll() || isLoadingMore || isSpaLoading || !hasMoreMatters()) {
            return;
        }

        var $r = $root();
        var current = parseInt($r.attr('data-current-page'), 10) || 1;
        var nextPage = current + 1;
        var url = new URL(window.location.href);
        url.searchParams.set('page', String(nextPage));
        url.searchParams.set('per_page', '20');
        url.searchParams.delete('spa');

        isLoadingMore = true;
        setInfiniteLoader(true);

        $.ajax({
            url: url.href,
            method: 'GET',
            dataType: 'html',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' },
            success: function (html) {
                var $newRoot = extractRootFromHtml(html);
                if (!$newRoot.length) {
                    return;
                }

                var lastPage = parseInt($newRoot.attr('data-last-page'), 10) || nextPage;
                var $rows = $newRoot.find('tbody.tdata tr.matter-data-row');

                var $tbody = $r.find('tbody.tdata');
                var appended = 0;

                $rows.each(function () {
                    var rowId = this.id;
                    if (rowId) {
                        // Avoid #id selector issues with special characters
                        if ($tbody[0] && $tbody[0].querySelector('#' + CSS.escape(rowId))) {
                            return;
                        }
                    }
                    $tbody.append($(this));
                    appended += 1;
                });

                $r.attr('data-current-page', String(nextPage));
                $r.attr('data-last-page', String(lastPage));
                updateLoadedCountLabel();

                if (!appended && nextPage >= lastPage) {
                    setInfiniteLoader(false);
                }
            },
            error: function () {
                // Keep current page so scroll can retry.
            },
            complete: function () {
                isLoadingMore = false;
                setInfiniteLoader(false);
                window.requestAnimationFrame(maybeLoadMoreMatters);
            }
        });
    }

    function maybeLoadMoreMatters() {
        if (!usesInfiniteScroll() || isLoadingMore || isSpaLoading || !hasMoreMatters()) {
            return;
        }
        var scrollBottom = window.innerHeight + window.scrollY;
        var triggerLine = document.documentElement.scrollHeight - 320;
        if (scrollBottom >= triggerLine) {
            loadMoreMatters();
        }
    }

    function bindInfiniteScroll() {
        if (bound || !usesInfiniteScroll()) {
            return;
        }
        bound = true;
        $(window).on('scroll.mattersInfinite resize.mattersInfinite', function () {
            maybeLoadMoreMatters();
        });
        updateLoadedCountLabel();
        window.requestAnimationFrame(maybeLoadMoreMatters);
    }

    function bindSpaHandlers() {
        if (spaBound || !isSpaPage()) {
            return;
        }
        spaBound = true;

        // Tab clicks are handled by inline vanilla MattersListSpa (more reliable).
        // Only bind a jQuery fallback if that helper is missing.
        if (!window.MattersListSpa) {
            $(document).on('click.mattersSpa', '.matters-spa-tab', function (e) {
                e.preventDefault();
                e.stopPropagation();
                var tab = $(this).attr('data-tab') === 'closed' ? 'closed' : 'active';
                if (tab === currentTab() && !isSpaLoading) {
                    return;
                }
                loadSpaContent(tab, {}, { pushState: true });
            });
        }

        window.MattersListAfterSpaSwap = function () {
            updateLoadedCountLabel();
            window.requestAnimationFrame(maybeLoadMoreMatters);
        };

        $(document).on('submit.mattersSpa', '#matterFilterForm', function (e) {
            if (!isSpaPage()) {
                return;
            }
            e.preventDefault();
            var params = buildQueryFromForm($(this));
            loadSpaContent(currentTab(), params, { pushState: true });
        });

        $(document).on('click.mattersSpa', '#clearMatterFilters, .matters-spa-reset', function (e) {
            if (!isSpaPage()) {
                return;
            }
            e.preventDefault();
            loadSpaContent(currentTab(), {}, { pushState: true });
        });

        $(document).on('click.mattersSpa', '.matter-quick-filter', function () {
            if (!isSpaPage()) {
                return;
            }
            var filter = $(this).data('filter');
            $('#matter_quick_date_range').val(filter);
            $('#from_date, #to_date').val('');
            $('#matterFilterForm').trigger('submit');
        });

        $(document).on('change.mattersSpa', '#from_date, #to_date', function () {
            $('#matter_quick_date_range').val('');
        });

        $(document).on('click.mattersSpa', '#filterToggleBtn, .matters-listing .filter_btn', function (e) {
            e.preventDefault();
            $('#matterFilterPanel').toggleClass('is-open');
        });

        $(document).on('click.mattersSpa', '.matters-table .sortable-header a', function (e) {
            if (!isSpaPage()) {
                return;
            }
            e.preventDefault();
            var href = $(this).attr('href');
            if (!href) {
                return;
            }
            var url = new URL(href, window.location.origin);
            var params = {};
            url.searchParams.forEach(function (value, key) {
                params[key] = value;
            });
            loadSpaContent(currentTab(), params, { pushState: true });
        });

        // popstate handled by inline MattersListSpa when present
        if (!window.MattersListSpa) {
            window.addEventListener('popstate', function () {
                if (!isSpaPage()) {
                    return;
                }
                var path = window.location.pathname;
                var closedPath = (function () {
                    try {
                        return new URL(tabUrl('closed'), window.location.origin).pathname;
                    } catch (err) {
                        return '';
                    }
                })();
                var tab = (closedPath && path.indexOf(closedPath) !== -1) ? 'closed' : 'active';
                var params = {};
                var search = new URLSearchParams(window.location.search);
                search.forEach(function (value, key) {
                    if (key !== 'spa') {
                        params[key] = value;
                    }
                });
                loadSpaContent(tab, params, { pushState: false });
            });
        }

        $(document).on('click.mattersSpa', '.assign-office-btn, .edit-office-btn', function (e) {
            e.preventDefault();
            var matterId = $(this).data('matter-id');
            var matterNo = $(this).data('matter-no');
            var matterTitle = $(this).data('matter-title');
            var officeId = $(this).data('office-id') || '';

            $('#edit_matter_id').val(matterId);
            $('#modal_matter_number').text(matterNo);
            $('#modal_matter_title').text(matterTitle || 'N/A');
            $('#edit_office_id').val(officeId).trigger('change');
            $('#editMatterOfficeModal').modal('show');
        });

        $('#editMatterOfficeForm').on('submit.mattersSpa', function (e) {
            e.preventDefault();
            var formData = $(this).serialize();
            var submitBtn = $(this).find('button[type="submit"]');
            var originalText = submitBtn.html();
            submitBtn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> Saving...');

            $.ajax({
                url: $(this).attr('action') || window.mattersUpdateOfficeUrl || '',
                method: 'POST',
                data: formData,
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        if (typeof iziToast !== 'undefined') {
                            iziToast.success({ title: 'Success', message: response.message, position: 'topRight' });
                        }
                        $('#editMatterOfficeModal').modal('hide');
                        var params = buildQueryFromForm($('#matterFilterForm'));
                        loadSpaContent(currentTab(), params, { pushState: false });
                    } else {
                        if (typeof iziToast !== 'undefined') {
                            iziToast.error({ title: 'Error', message: response.message || 'Failed to update office', position: 'topRight' });
                        }
                        submitBtn.prop('disabled', false).html(originalText);
                    }
                },
                error: function (xhr) {
                    var errorMsg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'An error occurred. Please try again.';
                    if (typeof iziToast !== 'undefined') {
                        iziToast.error({ title: 'Error', message: errorMsg, position: 'topRight' });
                    }
                    submitBtn.prop('disabled', false).html(originalText);
                }
            });
        });

        $(document).on('click.mattersSpa', '.closed-matter-reopen', function (e) {
            e.preventDefault();
            var matterId = $(this).data('matter-id');
            if (!matterId) {
                return;
            }
            if (!window.confirm('Reopen this matter? It will be moved back to active matters.')) {
                return;
            }
            var $btn = $(this);
            $btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> Reopening...');
            $.ajax({
                url: window.mattersReopenUrl || '',
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                    'Accept': 'application/json'
                },
                data: JSON.stringify({ matter_id: matterId, source: 'matter_list' }),
                success: function (resp) {
                    if (resp.status && resp.redirect_url) {
                        window.location.href = resp.redirect_url;
                    } else if (resp.status) {
                        loadSpaContent('active', {}, { pushState: true });
                    } else {
                        window.crmAlert(resp.message || 'Failed to reopen matter.');
                        $btn.prop('disabled', false).html('<i class="fa-solid fa-arrow-rotate-right"></i> Reopen');
                    }
                },
                error: function () {
                    window.crmAlert('An error occurred. Please try again.');
                    $btn.prop('disabled', false).html('<i class="fa-solid fa-arrow-rotate-right"></i> Reopen');
                }
            });
        });

        $(document).on('click.mattersSpa', '.closed-matter-request-reopen', function (e) {
            e.preventDefault();
            var matterId = $(this).data('matter-id');
            if (!matterId) {
                return;
            }
            if (!window.confirm('Send a request to the admin to reopen this matter?')) {
                return;
            }
            var $btn = $(this);
            $btn.prop('disabled', true);
            $.ajax({
                url: window.mattersRequestReopenUrl || '',
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                    'Accept': 'application/json'
                },
                data: JSON.stringify({ matter_id: matterId }),
                success: function (resp) {
                    if (resp.status) {
                        if (typeof iziToast !== 'undefined') {
                            iziToast.success({ title: 'Sent', message: resp.message || 'Request sent.', position: 'topRight' });
                        }
                        var params = buildQueryFromForm($('#matterFilterForm'));
                        loadSpaContent('closed', params, { pushState: false });
                    } else {
                        window.crmAlert(resp.message || 'Failed to send request.');
                        $btn.prop('disabled', false);
                    }
                },
                error: function () {
                    window.crmAlert('An error occurred. Please try again.');
                    $btn.prop('disabled', false);
                }
            });
        });
    }

    if (!$) {
        return;
    }

    $(function () {
        bindInfiniteScroll();
        bindSpaHandlers();
    });
})(window.jQuery || window.$, window);
