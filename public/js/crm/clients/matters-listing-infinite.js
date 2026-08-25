/**
 * Client matters listing infinite scroll (active + closed).
 * Fetches the next page via AJAX and appends table rows — same pattern as clients listing.
 */
(function ($, window) {
    'use strict';

    var isLoadingMore = false;
    var bound = false;

    function $root() {
        return $('#matters-listing-root');
    }

    function usesInfiniteScroll() {
        return $root().attr('data-infinite-scroll') === '1';
    }

    function updateLoadedCountLabel() {
        var $r = $root();
        var $count = $r.find('[data-loaded-count]');
        if (!$count.length) {
            return;
        }
        $count.text(String($r.find('tbody.tdata tr.matter-data-row').length));
    }

    function setInfiniteLoader(visible) {
        var $loader = $root().find('#mattersInfiniteLoader');
        if ($loader.length) {
            $loader.prop('hidden', !visible);
        }
    }

    function hasMoreMatters() {
        if (!usesInfiniteScroll()) {
            return false;
        }
        var current = parseInt($root().attr('data-current-page'), 10) || 1;
        var last = parseInt($root().attr('data-last-page'), 10) || 1;
        return current < last;
    }

    function extractRootFromHtml(html) {
        var $parsed = $('<div>').append($.parseHTML(html, document, true));
        var $found = $parsed.find('#matters-listing-root');
        if ($found.length) {
            return $found.first();
        }
        return $();
    }

    function loadMoreMatters() {
        if (!usesInfiniteScroll() || isLoadingMore || !hasMoreMatters()) {
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
                    if (rowId && $tbody.find('#' + rowId).length) {
                        return;
                    }
                    $tbody.append(this);
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
        if (!usesInfiniteScroll() || isLoadingMore || !hasMoreMatters()) {
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

    $(function () {
        bindInfiniteScroll();
    });
})(jQuery, window);
