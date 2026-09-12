/**
 * Open Tasks SPA — filter tabs, search, sort, infinite scroll (/tasks only).
 */
(function ($, window) {
    'use strict';

    var isSpaLoading = false;
    var isLoadingMore = false;
    var scrollObserver = null;
    var bound = false;
    var searchTimer = null;

    function $root() {
        return $('#open-tasks-spa-root');
    }

    function baseUrl() {
        return $root().attr('data-base-url') || '/tasks';
    }

    function currentFilter() {
        return $root().attr('data-filter') || 'all';
    }

    function currentSearch() {
        var $input = $('#searchInput');
        if ($input.length) {
            return String($input.val() || '').trim();
        }
        return String($root().attr('data-q') || '').trim();
    }

    function setSpaLoading(visible) {
        var $loader = $('#openTasksSpaLoading');
        if ($loader.length) {
            $loader.toggleClass('d-none', !visible);
            $loader.attr('aria-busy', visible ? 'true' : 'false');
        }
        $root().toggleClass('is-spa-loading', !!visible);
    }

    function setInfiniteLoader(visible) {
        $('#actionInfiniteLoader').prop('hidden', !visible);
    }

    function readTbodyState() {
        var $tbody = $('#openTasksTbody');
        return {
            page: parseInt($tbody.attr('data-page'), 10) || 1,
            lastPage: parseInt($tbody.attr('data-last-page'), 10) || 1,
            total: parseInt($tbody.attr('data-total'), 10) || 0,
            loaded: parseInt($tbody.attr('data-loaded'), 10) || 0,
            hasMore: $tbody.attr('data-has-more') === '1'
        };
    }

    function writeTbodyState(state) {
        $('#openTasksTbody').attr({
            'data-page': state.page,
            'data-last-page': state.lastPage,
            'data-total': state.total,
            'data-loaded': state.loaded,
            'data-has-more': state.hasMore ? '1' : '0',
            'data-filter': currentFilter()
        });
    }

    function updateScrollInfo(from, to, total) {
        var text = total > 0
            ? ('Showing ' + from + '–' + to + ' of ' + total + ' entries')
            : 'Showing 0 of 0 entries';
        $('#actionScrollInfo').text(text);
    }

    function applyCounts(counts) {
        if (!counts || typeof counts !== 'object') {
            return;
        }
        Object.keys(counts).forEach(function (key) {
            $root().find('[data-count-key="' + key + '"]').text(counts[key] || 0);
        });
        if (typeof window.refreshCrmNavPendingTaskCount === 'function') {
            window.refreshCrmNavPendingTaskCount();
        }
    }

    function buildUrl(params, options) {
        options = options || {};
        var url = new URL(baseUrl(), window.location.origin);
        Object.keys(params || {}).forEach(function (key) {
            if (params[key] === '' || params[key] == null) {
                return;
            }
            url.searchParams.set(key, String(params[key]));
        });
        if (options.spa) {
            url.searchParams.set('spa', '1');
            url.searchParams.delete('page');
            url.searchParams.delete('infinite');
        }
        if (options.infinite) {
            url.searchParams.set('infinite', '1');
        }
        if (!params.filter || params.filter === 'all') {
            url.searchParams.delete('filter');
        }
        if (!params.q) {
            url.searchParams.delete('q');
        }
        url.searchParams.delete('note_id');
        return url;
    }

    function currentQueryParams() {
        var params = {};
        var current = new URL(window.location.href);
        current.searchParams.forEach(function (value, key) {
            if (key === 'spa' || key === 'infinite' || key === 'page' || key === 'note_id') {
                return;
            }
            params[key] = value;
        });
        params.filter = currentFilter();
        params.q = currentSearch();
        return params;
    }

    function applySpaResponse(resp, pushUrl) {
        if (!resp || !resp.html) {
            return;
        }

        $('#open-tasks-spa-content').html(resp.html);

        var filter = resp.filter || 'all';
        var q = typeof resp.q === 'string' ? resp.q : '';
        $root().attr('data-filter', filter);
        $root().attr('data-q', q);

        if ($('#searchInput').length && typeof resp.q === 'string') {
            $('#searchInput').val(resp.q);
        }

        var loaded = typeof resp.loaded === 'number'
            ? resp.loaded
            : $('#openTasksTbody tr[data-note-id]').length;

        writeTbodyState({
            page: resp.current_page || 1,
            lastPage: resp.last_page || 1,
            total: typeof resp.total === 'number' ? resp.total : 0,
            loaded: loaded,
            hasMore: !!resp.has_more
        });
        updateScrollInfo(resp.from || (loaded > 0 ? 1 : 0), resp.to || loaded, resp.total || 0);
        applyCounts(resp.counts);

        if (pushUrl && window.history && window.history.pushState) {
            window.history.pushState({ openTasksSpa: true, filter: filter, q: q }, '', pushUrl);
        }

        if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
            $('#open-tasks-spa-content [data-bs-toggle="tooltip"]').each(function () {
                try { new bootstrap.Tooltip(this); } catch (err) {}
            });
        }

        bindInfiniteScroll();
        window.requestAnimationFrame(maybeLoadMore);
    }

    function loadSpa(params, options) {
        options = options || {};
        if (isSpaLoading) {
            return;
        }

        var requestParams = $.extend({}, currentQueryParams(), params || {});
        if (!requestParams.filter) {
            requestParams.filter = currentFilter();
        }

        var url = buildUrl(requestParams, { spa: true });
        isSpaLoading = true;
        setSpaLoading(true);

        $.ajax({
            url: url.toString(),
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            success: function (resp) {
                var pushUrl = resp && resp.url
                    ? resp.url
                    : buildUrl(requestParams, {}).toString();
                applySpaResponse(resp, options.pushState !== false ? pushUrl : null);
            },
            error: function (xhr) {
                var st = xhr && xhr.status;
                if (st === 401 || st === 419 || st === 403) {
                    window.location.reload();
                    return;
                }
                console.error('Open-tasks SPA error:', st);
            },
            complete: function () {
                isSpaLoading = false;
                setSpaLoading(false);
            }
        });
    }

    function hasMore() {
        return readTbodyState().hasMore;
    }

    function loadMore() {
        if (isSpaLoading || isLoadingMore || !hasMore()) {
            return;
        }

        var state = readTbodyState();
        var nextPage = state.page + 1;
        if (nextPage > state.lastPage) {
            writeTbodyState($.extend({}, state, { hasMore: false }));
            return;
        }

        var params = currentQueryParams();
        params.page = nextPage;
        var url = buildUrl(params, { infinite: true });

        isLoadingMore = true;
        setInfiniteLoader(true);

        $.ajax({
            url: url.toString(),
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            success: function (resp) {
                if (!resp || !resp.html) {
                    writeTbodyState($.extend({}, readTbodyState(), { hasMore: false }));
                    return;
                }

                var $tbody = $('#openTasksTbody');
                var $rows = $(resp.html).filter('tr');
                if (!$rows.length) {
                    writeTbodyState($.extend({}, readTbodyState(), { hasMore: false }));
                    updateScrollInfo(0, 0, resp.total || 0);
                    return;
                }

                var existing = {};
                $tbody.find('tr[data-note-id]').each(function () {
                    existing[String($(this).attr('data-note-id'))] = true;
                });

                var appended = 0;
                $rows.each(function () {
                    var id = String($(this).attr('data-note-id') || '');
                    if (id && existing[id]) {
                        return;
                    }
                    if (id) {
                        existing[id] = true;
                    }
                    $tbody.find('.open-tasks-empty-row').remove();
                    $tbody.append(this);
                    appended += 1;
                });

                var nextState = {
                    page: resp.current_page || nextPage,
                    lastPage: resp.last_page || state.lastPage,
                    total: typeof resp.total === 'number' ? resp.total : state.total,
                    loaded: state.loaded + appended,
                    hasMore: !!resp.has_more && appended > 0
                };
                writeTbodyState(nextState);
                updateScrollInfo(
                    nextState.loaded > 0 ? 1 : 0,
                    nextState.loaded,
                    nextState.total
                );
                applyCounts(resp.counts);

                if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
                    $tbody.find('tr[data-note-id]').slice(-appended).find('[data-bs-toggle="tooltip"]').each(function () {
                        try { new bootstrap.Tooltip(this); } catch (err) {}
                    });
                }
            },
            error: function (xhr) {
                var st = xhr && xhr.status;
                if (st === 401 || st === 419 || st === 403) {
                    window.location.reload();
                }
            },
            complete: function () {
                isLoadingMore = false;
                setInfiniteLoader(false);
                window.requestAnimationFrame(maybeLoadMore);
            }
        });
    }

    function maybeLoadMore() {
        if (isSpaLoading || isLoadingMore || !hasMore()) {
            return;
        }
        var sentinel = document.getElementById('actionScrollSentinel');
        if (!sentinel) {
            return;
        }
        var rect = sentinel.getBoundingClientRect();
        if (rect.top <= window.innerHeight + 140) {
            loadMore();
        }
    }

    function bindInfiniteScroll() {
        if (scrollObserver) {
            scrollObserver.disconnect();
            scrollObserver = null;
        }
        $(window).off('scroll.openTasksSpa resize.openTasksSpa');

        var sentinel = document.getElementById('actionScrollSentinel');
        if (!sentinel) {
            return;
        }

        if ('IntersectionObserver' in window) {
            scrollObserver = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        loadMore();
                    }
                });
            }, { root: null, rootMargin: '200px 0px', threshold: 0 });
            scrollObserver.observe(sentinel);
        } else {
            $(window).on('scroll.openTasksSpa resize.openTasksSpa', maybeLoadMore);
        }
    }

    function bindSpaHandlers() {
        if (bound || !$root().length) {
            return;
        }
        bound = true;

        $(document).on('click', '#open-tasks-spa-root .open-tasks-spa-filter', function (e) {
            e.preventDefault();
            var filter = $(this).attr('data-filter') || 'all';
            if (filter === currentFilter() && !isSpaLoading) {
                return;
            }
            loadSpa({ filter: filter }, { pushState: true });
        });

        $(document).on('keyup', '#open-tasks-spa-root #searchInput', function () {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(function () {
                loadSpa({ q: currentSearch() }, { pushState: true });
            }, 300);
        });

        $(document).on('click', '#open-tasks-spa-root .sort_col a', function (e) {
            e.preventDefault();
            var href = $(this).attr('href');
            if (!href) {
                return;
            }
            var sortUrl = new URL(href, window.location.origin);
            var params = currentQueryParams();
            sortUrl.searchParams.forEach(function (value, key) {
                if (key === 'spa' || key === 'infinite' || key === 'page' || key === 'note_id') {
                    return;
                }
                params[key] = value;
            });
            params.filter = currentFilter();
            params.q = currentSearch();
            loadSpa(params, { pushState: true });
        });

        window.addEventListener('popstate', function (event) {
            if (!$root().length) {
                return;
            }
            var filter = 'all';
            var q = '';
            if (event.state && event.state.filter) {
                filter = event.state.filter;
                q = event.state.q || '';
            } else {
                var url = new URL(window.location.href);
                filter = url.searchParams.get('filter') || 'all';
                q = url.searchParams.get('q') || '';
            }
            if ($('#searchInput').length) {
                $('#searchInput').val(q);
            }
            loadSpa({ filter: filter, q: q }, { pushState: false });
        });

        bindInfiniteScroll();
        window.requestAnimationFrame(maybeLoadMore);
    }

    function reloadCurrent() {
        loadSpa({}, { pushState: false });
    }

    $(function () {
        if (!$root().length) {
            return;
        }

        // Deep link from client Tasks tab (?note_id=) — server already applied search on first paint
        var params = new URLSearchParams(window.location.search);
        var noteId = params.get('note_id');
        if (noteId && /^\d+$/.test(noteId)) {
            $('#searchInput').val(noteId);
            $root().attr('data-q', noteId);
            var normalized = buildUrl({ filter: currentFilter(), q: noteId }, {});
            if (window.history && window.history.replaceState) {
                window.history.replaceState(
                    { openTasksSpa: true, filter: currentFilter(), q: noteId },
                    '',
                    normalized.toString()
                );
            }
        }

        bindSpaHandlers();
    });

    window.OpenTasksSpa = {
        reload: reloadCurrent,
        load: loadSpa,
        filter: currentFilter,
        search: currentSearch
    };
})(jQuery, window);
