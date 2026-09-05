/**
 * Assigned by Me SPA — Incomplete/Completed tabs, sort, infinite scroll (this page only).
 */
(function ($, window) {
    'use strict';

    var isSpaLoading = false;
    var isLoadingMore = false;
    var scrollObserver = null;
    var bound = false;

    function $root() {
        return $('#assigned-by-me-spa-root');
    }

    function baseUrl() {
        return $root().attr('data-base-url') || window.location.pathname;
    }

    function currentStatus() {
        return $root().attr('data-status') === 'completed' ? 'completed' : 'incomplete';
    }

    function setSpaLoading(visible) {
        var $loader = $('#assignedByMeSpaLoading');
        if ($loader.length) {
            $loader.toggleClass('d-none', !visible);
            $loader.attr('aria-busy', visible ? 'true' : 'false');
        }
        $root().toggleClass('is-spa-loading', !!visible);
    }

    function setInfiniteLoader(visible) {
        $('#assignedByMeInfiniteLoader').prop('hidden', !visible);
    }

    function syncTabUi(status) {
        $root().find('.assigned-by-me-spa-tab').removeClass('active');
        $root().find('.assigned-by-me-spa-tab[data-status="' + status + '"]').addClass('active');
        $root().attr('data-status', status);
    }

    function readTbodyState() {
        var $tbody = $('#assignedByMeTbody');
        return {
            page: parseInt($tbody.attr('data-page'), 10) || 1,
            lastPage: parseInt($tbody.attr('data-last-page'), 10) || 1,
            total: parseInt($tbody.attr('data-total'), 10) || 0,
            loaded: parseInt($tbody.attr('data-loaded'), 10) || 0,
            hasMore: $tbody.attr('data-has-more') === '1'
        };
    }

    function writeTbodyState(state) {
        $('#assignedByMeTbody').attr({
            'data-page': state.page,
            'data-last-page': state.lastPage,
            'data-total': state.total,
            'data-loaded': state.loaded,
            'data-has-more': state.hasMore ? '1' : '0',
            'data-status': currentStatus()
        });
    }

    function updateScrollInfo(from, to, total) {
        var text = total > 0
            ? ('Showing ' + from + '–' + to + ' of ' + total + ' entries')
            : 'Showing 0 of 0 entries';
        $('#assignedByMeScrollInfo').text(text);
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
        if (params.status === 'incomplete') {
            url.searchParams.delete('status');
        }
        return url;
    }

    function currentQueryParams() {
        var params = {};
        var current = new URL(window.location.href);
        current.searchParams.forEach(function (value, key) {
            if (key === 'spa' || key === 'infinite' || key === 'page') {
                return;
            }
            params[key] = value;
        });
        params.status = currentStatus();
        return params;
    }

    function applySpaResponse(resp, pushUrl) {
        if (!resp || !resp.html) {
            return;
        }

        $('#assigned-by-me-spa-content').html(resp.html);

        var status = resp.status === 'completed' ? 'completed' : 'incomplete';
        syncTabUi(status);

        var loaded = typeof resp.loaded === 'number'
            ? resp.loaded
            : $('#assignedByMeTbody tr[data-note-id]').length;
        writeTbodyState({
            page: resp.current_page || 1,
            lastPage: resp.last_page || 1,
            total: typeof resp.total === 'number' ? resp.total : 0,
            loaded: loaded,
            hasMore: !!resp.has_more
        });
        updateScrollInfo(resp.from || (loaded > 0 ? 1 : 0), resp.to || loaded, resp.total || 0);

        if (pushUrl && window.history && window.history.pushState) {
            window.history.pushState({ assignedByMeSpa: true, status: status }, '', pushUrl);
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
        if (!requestParams.status) {
            requestParams.status = currentStatus();
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
                console.error('Assigned-by-me SPA error:', st);
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

                var $tbody = $('#assignedByMeTbody');
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
                    $tbody.find('.assigned-by-me-empty-row').remove();
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
        var sentinel = document.getElementById('assignedByMeScrollSentinel');
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
        $(window).off('scroll.assignedByMeSpa resize.assignedByMeSpa');

        var sentinel = document.getElementById('assignedByMeScrollSentinel');
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
            $(window).on('scroll.assignedByMeSpa resize.assignedByMeSpa', maybeLoadMore);
        }
    }

    function bindSpaHandlers() {
        if (bound || !$root().length) {
            return;
        }
        bound = true;

        $(document).on('click', '#assigned-by-me-spa-root .assigned-by-me-spa-tab', function (e) {
            e.preventDefault();
            var status = $(this).attr('data-status') === 'completed' ? 'completed' : 'incomplete';
            if (status === currentStatus() && !isSpaLoading) {
                return;
            }
            loadSpa({ status: status }, { pushState: true });
        });

        $(document).on('click', '#assigned-by-me-spa-root .sort_col a', function (e) {
            e.preventDefault();
            var href = $(this).attr('href');
            if (!href) {
                return;
            }
            var sortUrl = new URL(href, window.location.origin);
            var params = currentQueryParams();
            sortUrl.searchParams.forEach(function (value, key) {
                if (key === 'spa' || key === 'infinite' || key === 'page') {
                    return;
                }
                params[key] = value;
            });
            params.status = currentStatus();
            loadSpa(params, { pushState: true });
        });

        window.addEventListener('popstate', function (event) {
            if (!$root().length) {
                return;
            }
            var status = 'incomplete';
            if (event.state && event.state.status) {
                status = event.state.status === 'completed' ? 'completed' : 'incomplete';
            } else {
                var url = new URL(window.location.href);
                status = url.searchParams.get('status') === 'completed' ? 'completed' : 'incomplete';
            }
            loadSpa({ status: status }, { pushState: false });
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
        bindSpaHandlers();
    });

    window.AssignedByMeSpa = {
        reload: reloadCurrent,
        load: loadSpa,
        status: currentStatus
    };
})(jQuery, window);
