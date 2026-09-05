/**
 * All Notifications — infinite scroll (this page only).
 */
(function ($, window) {
    'use strict';

    var isLoadingMore = false;
    var scrollObserver = null;
    var bound = false;

    function $root() {
        return $('#crm-all-notifications');
    }

    function $list() {
        return $('#notificationsList');
    }

    function baseUrl() {
        return $root().attr('data-base-url') || window.location.pathname;
    }

    function setInfiniteLoader(visible) {
        $('#notificationsInfiniteLoader').prop('hidden', !visible);
    }

    function readListState() {
        var $el = $list();
        return {
            page: parseInt($el.attr('data-page'), 10) || 1,
            lastPage: parseInt($el.attr('data-last-page'), 10) || 1,
            total: parseInt($el.attr('data-total'), 10) || 0,
            loaded: parseInt($el.attr('data-loaded'), 10) || 0,
            hasMore: $el.attr('data-has-more') === '1'
        };
    }

    function writeListState(state) {
        $list().attr({
            'data-page': state.page,
            'data-last-page': state.lastPage,
            'data-total': state.total,
            'data-loaded': state.loaded,
            'data-has-more': state.hasMore ? '1' : '0'
        });
    }

    function updateScrollInfo(from, to, total) {
        var text = total > 0
            ? ('Showing ' + from + '–' + to + ' of ' + total)
            : 'Showing 0 of 0';
        $('#notificationsScrollInfo').text(text);
    }

    function buildUrl(page) {
        var url = new URL(baseUrl(), window.location.origin);
        var current = new URL(window.location.href);
        current.searchParams.forEach(function (value, key) {
            if (key === 'infinite' || key === 'page') {
                return;
            }
            url.searchParams.set(key, value);
        });
        url.searchParams.set('page', String(page));
        url.searchParams.set('infinite', '1');
        return url;
    }

    function parseItems(html) {
        return $('<div>').append($.parseHTML(html || '')).find('.crm-notif-item');
    }

    function hasMore() {
        return readListState().hasMore;
    }

    function loadMore() {
        if (isLoadingMore || !hasMore()) {
            return;
        }

        var state = readListState();
        var nextPage = state.page + 1;
        if (nextPage > state.lastPage) {
            writeListState($.extend({}, state, { hasMore: false }));
            return;
        }

        var url = buildUrl(nextPage);
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
                    writeListState($.extend({}, readListState(), { hasMore: false }));
                    return;
                }

                var $el = $list();
                var $items = parseItems(resp.html);
                if (!$items.length) {
                    writeListState($.extend({}, readListState(), { hasMore: false }));
                    updateScrollInfo(0, 0, resp.total || 0);
                    return;
                }

                var existing = {};
                $el.find('.crm-notif-item[data-notification-id]').each(function () {
                    existing[String($(this).attr('data-notification-id'))] = true;
                });

                var appended = 0;
                $items.each(function () {
                    var id = String($(this).attr('data-notification-id') || '');
                    if (id && existing[id]) {
                        return;
                    }
                    if (id) {
                        existing[id] = true;
                    }
                    $el.append(this);
                    appended += 1;
                });

                var nextState = {
                    page: resp.current_page || nextPage,
                    lastPage: resp.last_page || state.lastPage,
                    total: typeof resp.total === 'number' ? resp.total : state.total,
                    loaded: state.loaded + appended,
                    hasMore: !!resp.has_more && appended > 0
                };
                writeListState(nextState);
                updateScrollInfo(
                    nextState.loaded > 0 ? 1 : 0,
                    nextState.loaded,
                    nextState.total
                );

                if (typeof resp.total === 'number') {
                    $('#notificationsTotalBadge').text(resp.total + ' Total');
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
        if (isLoadingMore || !hasMore()) {
            return;
        }
        var sentinel = document.getElementById('notificationsScrollSentinel');
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
        $(window).off('scroll.notificationsInfinite resize.notificationsInfinite');

        var sentinel = document.getElementById('notificationsScrollSentinel');
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
            $(window).on('scroll.notificationsInfinite resize.notificationsInfinite', maybeLoadMore);
        }
    }

    function bind() {
        if (bound || !$root().length || !$list().length) {
            return;
        }
        bound = true;
        bindInfiniteScroll();
        window.requestAnimationFrame(maybeLoadMore);
    }

    $(function () {
        bind();
    });
})(jQuery, window);
