/**
 * Activity Feed Functionality
 * Handles filtering, width toggle, and activity feed interactions
 */

(function($) {
    'use strict';

    /** Match .crm-container .activity-feed .feed-item { display: grid !important } — jQuery .hide() cannot win; use this class instead. */
    var FILTER_HIDDEN_CLASS = 'feed-item--filter-hidden';

    function feedRoot() {
        return $('#activity-feed');
    }

    /** Visible summary line (progressive rows) or legacy .feed-content strong headline */
    function getActivityRowHeadlineText($item) {
        var el = $item.find('.feed-item-summary-text').first();
        var raw = el.length ? el.text() : $item.find('.feed-content strong').text();
        return (raw || '').toLowerCase();
    }

    function isLeadConvertedItem($item) {
        return $item.hasClass('activity-type-lead_converted')
            || /lead converted/i.test(getActivityRowHeadlineText($item));
    }

    /** Tasks/actions and other non-note timeline events (incl. legacy rows stored as activity_type note). */
    var TASK_ACTION_SUBJECT_RE = /\b(?:set (?:action|task) for|updated (?:action|task) for|completed (?:action|task) for|(?:action|task) completed for|new (?:action|task) assigned for|deleted(?:\s+completed)?\s+(?:action|task)|appointment created|booking appointment|converted activity to note|extended note deadline|note added to booking appointment)\b/i;

    function isTaskOrActionTimelineItem($item) {
        if ($item.hasClass('activity-type-activity')) {
            return true;
        }
        return TASK_ACTION_SUBJECT_RE.test(getActivityRowHeadlineText($item));
    }

    /** Strict: only client notes (activity_type note), not tasks mis-tagged as note. */
    function isClientNoteTimelineItem($item) {
        if (isLeadConvertedItem($item) || isTaskOrActionTimelineItem($item)) {
            return false;
        }
        var cls = $item.attr('class') || '';
        return /\bactivity-type-note(?:-|$|\s)/.test(cls);
    }

    function isTimelineActivityItem($item) {
        return $item.hasClass('activity-type-sms')
            || $item.hasClass('activity-type-stage')
            || isLeadConvertedItem($item)
            || isTaskOrActionTimelineItem($item);
    }

    var FEED_NO_RESULTS_HTML = '<li class="feed-item feed-item-no-results" style="text-align: center; padding: 28px 20px; color: #5e7a90;">' +
        '<i class="fa-solid fa-filter" style="font-size: 1.5em; margin-bottom: 8px; opacity: 0.5;" aria-hidden="true"></i>' +
        '<p class="mb-0 small">No activities match your filters</p></li>';

    var FEED_EMPTY_HTML = '<li class="feed-item feed-item--empty" style="text-align: center; padding: 36px 20px; color: #5e7a90;">' +
        '<i class="fa-solid fa-inbox" style="font-size: 2em; margin-bottom: 10px; opacity: 0.5;" aria-hidden="true"></i>' +
        '<p class="mb-1" style="color: #1e3d60; font-weight: 600;">No activities yet</p>' +
        '<p class="mb-0 small">Notes, tasks, documents, and stage changes will appear here.</p></li>';

    var FEED_LOAD_SENTINEL_HTML = '<li class="feed-item feed-load-sentinel" aria-hidden="true"></li>';

    var FEED_ERROR_HTML = '<li class="feed-item feed-item--empty" style="text-align: center; padding: 36px 20px; color: #5e7a90;">' +
        '<p class="mb-0 small">Could not load timeline. Use Refresh to try again.</p></li>';

    var feedState = {
        page: 0,
        hasMore: false,
        loading: false,
        fillPasses: 0,
        requestSeq: 0
    };
    var sentinelObserver = null;
    var activeXhr = null;

    /**
     * Initialize Activity Feed functionality
     */
    function init() {
        setupFilterButtons();
        setupWidthToggle();
        setupExtendedFilters();
        setupRefreshButton();
        setupFilterBarToggle();
        setupExpandAllToggle();
        ensureTimelineFiltersVisible();
        bindFeedScroll();
        loadActivities({ reset: true });
    }

    function isExpandAllActive() {
        return $('#activity-feed').data('expandAll') === true;
    }

    function setExpandAllActive(active) {
        $('#activity-feed').data('expandAll', !!active);
        updateExpandAllUi(!!active);
    }

    function updateExpandAllUi(expanded) {
        var $btn = $('#activity-feed-expand-all');
        if (!$btn.length) {
            return;
        }
        $btn.attr('aria-pressed', expanded ? 'true' : 'false');
        $btn.attr('title', expanded ? 'Collapse all details' : 'Expand all details');
        $btn.attr('aria-label', expanded ? 'Collapse all activity details' : 'Expand all activity details');
        $btn.toggleClass('is-active', expanded);
        $btn.find('i')
            .toggleClass('fa-angles-down', !expanded)
            .toggleClass('fa-angles-up', expanded);
        $btn.find('.activity-feed-expand-all__label').text(expanded ? 'Collapse all' : 'Expand all');
    }

    function setFeedItemExpanded($li, expand) {
        if (!$li || !$li.length || $li.hasClass('feed-item--no-expand')) {
            return;
        }
        var $btn = $li.find('.feed-item-summary[aria-controls]').first();
        var $detail = $li.find('.feed-item-detail').first();
        if (!$btn.length || !$detail.length) {
            return;
        }
        if (expand) {
            $btn.attr('aria-expanded', 'true');
            $detail.removeAttr('hidden');
            $li.addClass('feed-item--expanded');
        } else {
            $btn.attr('aria-expanded', 'false');
            $detail.attr('hidden', 'hidden');
            $li.removeClass('feed-item--expanded');
        }
    }

    function setAllFeedItemsExpanded(expand) {
        var $items = $('#activity-feed .feed-list > .feed-item.activity').not('.feed-item--filter-hidden');
        $items.each(function() {
            setFeedItemExpanded($(this), expand);
        });
        if (expand) {
            requestAnimationFrame(function() {
                if (typeof window.initActivityFeedClamps === 'function') {
                    window.initActivityFeedClamps();
                }
            });
        }
        if (typeof adjustActivityFeedHeight === 'function') {
            adjustActivityFeedHeight();
        }
    }

    function setupExpandAllToggle() {
        $('#activity-feed-expand-all').on('click', function() {
            var next = !isExpandAllActive();
            setExpandAllActive(next);
            setAllFeedItemsExpanded(next);
        });
        updateExpandAllUi(false);
    }

    function isOnActivityTab() {
        return $('#activityfeed-tab').hasClass('active')
            || $('.crm-container').hasClass('crm-container--activity-tab');
    }

    function isFilterBarCollapsed() {
        return $('#activity-feed').data('filtersCollapsed') === true;
    }

    /** Whether search/date filters should affect the feed (not the same as DOM :visible during slide animations). */
    function isExtendedFiltersActive() {
        if (isOnActivityTab()) {
            return !isFilterBarCollapsed();
        }
        return $('#activity-feed-filter-bar').is(':visible');
    }

    function setFilterBarCollapsed(collapsed) {
        $('#activity-feed').data('filtersCollapsed', collapsed);
    }

    function updateFilterToggleUi(collapsed) {
        var $toggle = $('#activity-feed-filter-toggle');
        if (!$toggle.length) {
            return;
        }
        $toggle.attr('aria-expanded', collapsed ? 'false' : 'true');
        $toggle.attr('title', collapsed ? 'Show search' : 'Hide search');
        $toggle.toggleClass('is-active', !collapsed);
    }

    function setFilterBarVisible(visible, animate) {
        var $bar = $('#activity-feed-filter-bar');
        if (!$bar.length) {
            return;
        }
        if (visible) {
            $bar.removeClass('activity-feed-filter-bar--collapsed');
            setFilterBarCollapsed(false);
            updateFilterToggleUi(false);
            if (animate) {
                $bar.stop(true, true).slideDown(200, function() {
                    afterFilterBarLayoutChange();
                });
            } else {
                $bar.show();
                afterFilterBarLayoutChange();
            }
            initActivityFeedDatepickers();
        } else {
            $bar.addClass('activity-feed-filter-bar--collapsed');
            setFilterBarCollapsed(true);
            updateFilterToggleUi(true);
            if (animate) {
                $bar.stop(true, true).slideUp(200, afterFilterBarLayoutChange);
            } else {
                $bar.hide();
                afterFilterBarLayoutChange();
            }
        }
    }

    function afterFilterBarLayoutChange() {
        if (typeof adjustActivityFeedHeight === 'function') {
            adjustActivityFeedHeight();
            setTimeout(adjustActivityFeedHeight, 150);
        }
    }

    /**
     * Timeline tab: show filter toggle (bar closed by default); other tabs: hide bar unless expand-width is checked.
     */
    function ensureTimelineFiltersVisible() {
        var $toggle = $('#activity-feed-filter-toggle');
        if (!isOnActivityTab()) {
            $toggle.attr('hidden', 'hidden');
            if (!$('#increase-activity-feed-width').is(':checked')) {
                $('#activity-feed-filter-bar').hide().removeClass('activity-feed-filter-bar--collapsed');
            }
            return;
        }
        $toggle.removeAttr('hidden');
        // First visit: keep search/date bar closed (type filters stay visible).
        if ($('#activity-feed').data('filtersCollapsed') === undefined) {
            setFilterBarCollapsed(true);
        }
        if (isFilterBarCollapsed()) {
            $('#activity-feed-filter-bar').hide().addClass('activity-feed-filter-bar--collapsed');
            updateFilterToggleUi(true);
        } else {
            setFilterBarVisible(true, false);
        }
    }

    function setupFilterBarToggle() {
        $('#activity-feed-filter-toggle').on('click', function() {
            if (!isOnActivityTab()) {
                return;
            }
            var expanding = isFilterBarCollapsed();
            setFilterBarVisible(expanding, true);
            if (!expanding) {
                loadActivities({ reset: true });
            }
        });
    }

    /**
     * Setup refresh button to reload activities
     */
    function setupRefreshButton() {
        $('#activity-feed-refresh').on('click', function() {
            var $btn = $(this).find('i');
            $btn.addClass('fa-spin');
            loadActivities({ reset: true });
            setTimeout(function() { $btn.removeClass('fa-spin'); }, 800);
        });
    }

    /**
     * Setup activity filter buttons
     * Type filter works with extended filters (search, date) when they are active
     */
    function setupFilterButtons() {
        var $root = feedRoot();
        if (!$root.length) return;
        $root.find('.activity-filter-btn').on('click', function() {
            $root.find('.activity-filter-btn').removeClass('active');
            $(this).addClass('active');
            loadActivities({ reset: true });
        });
    }

    /**
     * Filter activities based on type
     * @param {string} filterType - The type of filter to apply (all, activity, note, document, accounting)
     */
    function filterActivities(filterType) {
        var $root = feedRoot();
        if (!$root.length) return;
        var $rows = $root.find('.feed-item.activity');

        if (filterType === 'all') {
            $rows.removeClass(FILTER_HIDDEN_CLASS);
        } else if (filterType === 'activity') {
            $rows.each(function() {
                var $item = $(this);
                $item.toggleClass(FILTER_HIDDEN_CLASS, !isTimelineActivityItem($item));
            });
        } else if (filterType === 'note') {
            $rows.each(function() {
                var $item = $(this);
                $item.toggleClass(FILTER_HIDDEN_CLASS, !isClientNoteTimelineItem($item));
            });
        } else if (filterType === 'document') {
            var documentPatterns = [
                'document',
                'added.*document',
                'updated.*document',
                'deleted.*document',
                'renamed.*document',
                'added.*migration document',
                'updated.*migration document',
                'added.*personal document',
                'updated.*personal document',
                'added.*visa document',
                'updated.*visa document',
                'added.*matter document',
                'updated.*matter document',
                'added.*personal checklist',
                'added.*visa checklist',
                'added.*matter document checklist',
                'placed signature fields on matter document',
                'placed signature fields on visa document',
                'updated.*checklist',
                'signed document',
                'signed cost agreement',
                'signed costs disclosure',
                'document.*attached',
                'document.*detached'
            ];
            $rows.each(function() {
                var $item = $(this);
                if ($item.hasClass('activity-type-document')) {
                    $item.removeClass(FILTER_HIDDEN_CLASS);
                    return;
                }
                var subjectText = getActivityRowHeadlineText($item);
                var isAccountingReceiptDoc = /(receipt document|journal receipt document|client receipt document|office receipt document)/i.test(subjectText);
                var isDocument = !isAccountingReceiptDoc && documentPatterns.some(function(pattern) {
                    return new RegExp(pattern, 'i').test(subjectText);
                });
                $item.toggleClass(FILTER_HIDDEN_CLASS, !isDocument);
            });
        } else if (filterType === 'signature') {
            $rows.each(function() {
                var $item = $(this);
                $item.toggleClass(FILTER_HIDDEN_CLASS, !$item.hasClass('activity-type-signature'));
            });
        } else if (filterType === 'accounting') {
            var accountingPatterns = [
                'invoice',
                'added invoice',
                'updated invoice',
                'deleted invoice',
                'receipt',
                'office receipt',
                'client receipt',
                'journal receipt',
                'receipt document',
                'journal receipt document',
                'client receipt document',
                'office receipt document',
                'added.*receipt',
                'updated.*receipt',
                'ledger',
                'client funds ledger',
                'fee transfer',
                'allocation',
                'allocated',
                'payment',
                'deposit',
                'withdrawal',
                'balance',
                'cost agreement',
                'account'
            ];
            $rows.each(function() {
                var $item = $(this);
                if ($item.hasClass('activity-type-financial')) {
                    $item.removeClass(FILTER_HIDDEN_CLASS);
                    return;
                }
                var subjectText = getActivityRowHeadlineText($item);
                var isAccounting = accountingPatterns.some(function(pattern) {
                    return new RegExp(pattern, 'i').test(subjectText);
                });
                $item.toggleClass(FILTER_HIDDEN_CLASS, !isAccounting);
            });
        } else {
            $rows.each(function() {
                var $item = $(this);
                $item.toggleClass(FILTER_HIDDEN_CLASS, !$item.hasClass('activity-type-' + filterType));
            });
        }

        updateEmptyState();
    }

    function reapplyFilters() {
        loadActivities({ reset: true });
    }

    /**
     * Setup width toggle checkbox
     * When checked, shows extended filter bar (search, date range, apply/reset)
     */
    function setupWidthToggle() {
        $('#increase-activity-feed-width').on('change', function() {
            if (isOnActivityTab()) {
                return;
            }
            if ($(this).is(':checked')) {
                $('.activity-feed').addClass('wide-mode');
                if ($('.main-content').is(':visible')) {
                    $('.main-content').addClass('compact-mode');
                }
                setFilterBarVisible(true, true);
            } else {
                setFilterBarVisible(false, true);
                $('.activity-feed').removeClass('wide-mode');
                $('.main-content').removeClass('compact-mode');
            }
            reapplyFilters();
        });
    }

    /**
     * Initialize Flatpickr on activity feed date inputs (when filter bar is visible)
     */
    function initActivityFeedDatepickers() {
        if (typeof flatpickr === 'undefined') return;
        var $from = $('#activity-feed-date-from');
        var $to = $('#activity-feed-date-to');
        if (!$from.length || !$to.length) return;
        if ($from.data('flatpickr')) return; // Already initialized
        flatpickr('#activity-feed-date-from', { dateFormat: 'Y-m-d', allowInput: true });
        flatpickr('#activity-feed-date-to', { dateFormat: 'Y-m-d', allowInput: true });
    }

    /**
     * Setup extended filters (search, date range, apply, reset)
     * Only active when checkbox is ticked
     */
    function setupExtendedFilters() {
        $('#activity-feed-apply').on('click', function() {
            applyExtendedFilters();
        });
        $('#activity-feed-reset').on('click', function() {
            $('#activity-feed-search').val('');
            $('#activity-feed-date-from').val('');
            $('#activity-feed-date-to').val('');
            applyExtendedFilters();
        });
        $('#activity-feed-search').on('keypress', function(e) {
            if (e.which === 13) { applyExtendedFilters(); }
        });
    }

    /**
     * Apply search and date filters, combined with current type filter
     */
    function applyExtendedFilters() {
        loadActivities({ reset: true });
    }

    /**
     * Check if item matches the current type filter
     */
    function matchesTypeFilter($item, filterType) {
        if (filterType === 'all') return true;
        if (filterType === 'activity') {
            return isTimelineActivityItem($item);
        }
        if (filterType === 'note') {
            return isClientNoteTimelineItem($item);
        }
        if (filterType === 'document') {
            if ($item.hasClass('activity-type-document')) return true;
            var subject = getActivityRowHeadlineText($item);
            if (/(receipt document|journal receipt document|client receipt document|office receipt document)/i.test(subject)) return false;
            var docPatterns = ['document', 'added.*document', 'updated.*document', 'visa document', 'matter document', 'personal document', 'checklist', 'uploaded', 'signed document', 'placed signature fields on matter document', 'placed signature fields on visa document'];
            return docPatterns.some(function(p) { return new RegExp(p, 'i').test(subject); });
        }
        if (filterType === 'accounting') {
            if ($item.hasClass('activity-type-financial')) return true;
            var subj = getActivityRowHeadlineText($item);
            return /invoice|receipt|payment|ledger|account/.test(subj);
        }
        if (filterType === 'signature') {
            return $item.hasClass('activity-type-signature');
        }
        return true;
    }

    /**
     * Show/hide empty state when no activities match
     */
    function updateEmptyState() {
        var $root = feedRoot();
        if (!$root.length) return;
        var $acts = $root.find('.feed-item.activity');
        var total = $acts.length;
        var visible = $acts.not('.' + FILTER_HIDDEN_CLASS).length;
        $root.find('.feed-item--empty').toggleClass(FILTER_HIDDEN_CLASS, total > 0);
        $root.find('.feed-item-no-results').toggleClass('feed-item-no-results--show', visible === 0 && total > 0);
    }

    // --- Progressive disclosure: build feed HTML (shared with client + company detail AJAX) ---

    function escapeHtml(s) {
        if (s == null) return '';
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function escapeAttr(s) {
        if (s == null) return '';
        return String(s).replace(/&/g, '&amp;').replace(/"/g, '&quot;');
    }

    function stripHtmlToText(html) {
        if (html == null) return '';
        var d = document.createElement('div');
        d.innerHTML = String(html);
        return (d.textContent || d.innerText || '').trim();
    }

    function getNoteTypeClass(subject) {
        var s = (subject || '').toLowerCase();
        if (s.indexOf('call') !== -1) {
            return { li: ' activity-type-note-call', feedIcon: ' feed-icon-note-call' };
        }
        if (s.indexOf('email') !== -1) {
            return { li: ' activity-type-note-email', feedIcon: ' feed-icon-note-email' };
        }
        if (s.indexOf('in-person') !== -1) {
            return { li: ' activity-type-note-in-person', feedIcon: ' feed-icon-note-in-person' };
        }
        if (s.indexOf('attention') !== -1) {
            return { li: ' activity-type-note-attention', feedIcon: ' feed-icon-note-attention' };
        }
        if (s.indexOf('others') !== -1) {
            return { li: ' activity-type-note-others', feedIcon: ' feed-icon-note-others' };
        }
        return { li: ' activity-type-note', feedIcon: '' };
    }

    function getActivityIconElement(activityType, subject) {
        var sl = (subject || '').toLowerCase();
        if (activityType === 'sms') {
            return { html: '<i class="fa-solid fa-sms"></i>', cls: 'feed-icon-sms' };
        }
        if (activityType === 'document') {
            return { html: '<i class="fa-solid fa-file-lines"></i>', cls: '' };
        }
        if (activityType === 'signature') {
            return { html: '<i class="fa-solid fa-file-signature"></i>', cls: 'feed-icon-signature' };
        }
        if (activityType === 'financial') {
            return { html: '<i class="fa-solid fa-dollar-sign"></i>', cls: 'feed-icon-accounting' };
        }
        if (activityType === 'lead_converted' || sl.indexOf('lead converted') !== -1) {
            return { html: '<i class="fa-solid fa-user-check"></i>', cls: 'feed-icon-lead-converted' };
        }
        if (activityType === 'note') {
            var nt = getNoteTypeClass(subject);
            var ic = (subject || '').toLowerCase().indexOf('call') !== -1 ? 'fa-phone' : (sl.indexOf('email') !== -1 ? 'fa-envelope' : (sl.indexOf('in-person') !== -1 ? 'fa-user-group' : (sl.indexOf('attention') !== -1 ? 'fa-triangle-exclamation' : (sl.indexOf('others') !== -1 ? 'fa-ellipsis' : 'fa-note-sticky'))));
            return { html: '<i class="fa-solid ' + ic + '"></i>', cls: 'feed-icon-note' + nt.feedIcon };
        }
        if (activityType === 'activity') {
            return { html: '<i class="fa-solid fa-bolt"></i>', cls: 'feed-icon-activity' };
        }
        if (activityType === 'stage') {
            return { html: '<i class="fa-solid fa-list-check" aria-hidden="true"></i>', cls: 'feed-icon-stage' };
        }
        if (sl.indexOf('invoice') !== -1 || sl.indexOf('receipt') !== -1 || sl.indexOf('ledger') !== -1 || sl.indexOf('payment') !== -1 || sl.indexOf('account') !== -1) {
            return { html: '<i class="fa-solid fa-dollar-sign"></i>', cls: '' };
        }
        if (sl.indexOf('document') !== -1) {
            return { html: '<i class="fa-solid fa-file-lines"></i>', cls: '' };
        }
        return { html: '<i class="fa-solid fa-note-sticky"></i>', cls: '' };
    }

    /**
     * Returns full HTML for all activity rows (replaces .feed-list contents).
     */
    function currentTypeFilter() {
        var $root = feedRoot();
        return ($root.find('.activity-filter-btn.active').data('filter') || 'all');
    }

    function feedRequestParams(page, clientId) {
        var params = {
            id: clientId,
            page: page,
            per_page: 40,
            type: currentTypeFilter()
        };
        if (isExtendedFiltersActive()) {
            var keyword = ($('#activity-feed-search').val() || '').trim();
            var dateFrom = ($('#activity-feed-date-from').val() || '').trim();
            var dateTo = ($('#activity-feed-date-to').val() || '').trim();
            if (keyword) params.keyword = keyword;
            if (dateFrom) params.date_from = dateFrom;
            if (dateTo) params.date_to = dateTo;
        }
        return params;
    }

    function filtersAreActive() {
        if (currentTypeFilter() !== 'all') return true;
        if (!isExtendedFiltersActive()) return false;
        return !!(($('#activity-feed-search').val() || '').trim()
            || ($('#activity-feed-date-from').val() || '').trim()
            || ($('#activity-feed-date-to').val() || '').trim());
    }

    function feedScroller() {
        return document.querySelector('#activity-feed .feed-list')
            || document.querySelector('#activity-feed');
    }

    function bindFeedScroll() {
        var scroller = feedScroller();
        if (!scroller || scroller.getAttribute('data-feed-scroll-bound') === '1') {
            return;
        }
        scroller.setAttribute('data-feed-scroll-bound', '1');
        $(scroller).on('scroll.activityFeed', function() {
            if (scroller.scrollHeight - scroller.scrollTop - scroller.clientHeight < 120) {
                loadActivities({ append: true });
            }
        });
    }

    function disconnectSentinel() {
        if (sentinelObserver) {
            sentinelObserver.disconnect();
            sentinelObserver = null;
        }
    }

    function observeSentinel() {
        disconnectSentinel();
        var el = document.querySelector('#activity-feed .feed-load-sentinel');
        var root = feedScroller();
        if (!el || !root || typeof IntersectionObserver === 'undefined') {
            return;
        }
        sentinelObserver = new IntersectionObserver(function(entries) {
            if (entries.some(function(e) { return e.isIntersecting; })) {
                loadActivities({ append: true });
            }
        }, { root: root, rootMargin: '120px', threshold: 0 });
        sentinelObserver.observe(el);
    }

    function fillViewportIfNeeded() {
        if (!feedState.hasMore || feedState.loading || feedState.fillPasses >= 8) {
            return;
        }
        var scroller = feedScroller();
        var list = document.querySelector('#activity-feed .feed-list');
        if (!scroller || !list) return;
        if (scroller.scrollHeight <= scroller.clientHeight + 40) {
            feedState.fillPasses += 1;
            loadActivities({ append: true });
        }
    }

    function afterFeedRender() {
        if (isExpandAllActive()) {
            setAllFeedItemsExpanded(true);
        }
        if (typeof window.initActivityFeedClamps === 'function') {
            window.initActivityFeedClamps();
        }
        if (typeof adjustActivityFeedHeight === 'function') {
            adjustActivityFeedHeight();
        }
        observeSentinel();
        fillViewportIfNeeded();
    }

    function loadActivities(opts) {
        opts = opts || {};
        var append = !!opts.append;
        var $list = $('#activity-feed .feed-list');
        if (!$list.length) {
            return;
        }
        var clientId = opts.clientId || (window.ClientDetailConfig && window.ClientDetailConfig.clientId);
        var url = (window.ClientDetailConfig && window.ClientDetailConfig.urls && window.ClientDetailConfig.urls.getActivities)
            || (typeof site_url !== 'undefined' ? site_url + '/get-activities' : '/get-activities');
        if (!clientId) {
            return;
        }
        if (append) {
            if (feedState.loading || !feedState.hasMore) {
                return;
            }
        } else {
            feedState.requestSeq += 1;
            feedState.hasMore = false;
            feedState.fillPasses = 0;
            disconnectSentinel();
            if (activeXhr && typeof activeXhr.abort === 'function') {
                activeXhr.abort();
            }
        }

        var requestSeq = feedState.requestSeq;
        var page = append ? (feedState.page + 1) : 1;
        feedState.loading = true;

        activeXhr = $.ajax({
            url: url,
            type: 'GET',
            dataType: 'json',
            data: feedRequestParams(page, clientId),
            success: function(response) {
                if (requestSeq !== feedState.requestSeq) {
                    return;
                }
                feedState.loading = false;
                activeXhr = null;
                if (!response || !response.status) {
                    if (!append) {
                        $list.html(FEED_ERROR_HTML);
                    }
                    return;
                }
                if (response.data === undefined || response.data === null) {
                    if (!append) {
                        $list.html(FEED_EMPTY_HTML);
                    }
                    return;
                }
                var data = response.data || [];
                feedState.page = response.page || page;
                feedState.hasMore = !!response.has_more;
                if (append) {
                    $list.find('.feed-load-sentinel').remove();
                    if (data.length) {
                        $list.append(window.buildActivityFeedListHtml(data, {
                            has_more: feedState.hasMore,
                            filtered: false
                        }));
                    } else if (feedState.hasMore) {
                        $list.append(FEED_LOAD_SENTINEL_HTML);
                    }
                } else {
                    $list.html(window.buildActivityFeedListHtml(data, {
                        has_more: feedState.hasMore,
                        filtered: filtersAreActive()
                    }));
                }
                afterFeedRender();
            },
            error: function(xhr, status) {
                if (requestSeq !== feedState.requestSeq) {
                    return;
                }
                feedState.loading = false;
                activeXhr = null;
                if (status === 'abort') {
                    return;
                }
                if (!append) {
                    $list.html(FEED_ERROR_HTML);
                }
            }
        });
    }

    window.loadActivities = loadActivities;
    window.getallactivities = function(clientId) {
        loadActivities({ reset: true, clientId: clientId });
    };

    window.buildActivityFeedListHtml = function (data, options) {
        options = options || {};
        if (!data || !data.length) {
            return options.filtered ? FEED_NO_RESULTS_HTML : FEED_EMPTY_HTML;
        }
        var html = '';
        for (var k = 0; k < data.length; k++) {
            var v = data[k];
            if (v.activity_id == null) {
                continue;
            }
            var activityType = v.activity_type || 'activity';
            var subject = v.subject || '';
            var sl = subject.toLowerCase();
            if (activityType !== 'lead_converted' && sl.indexOf('lead converted') !== -1) {
                activityType = 'lead_converted';
            }
            var icon = getActivityIconElement(activityType, subject);
            var noteAdd = (activityType === 'note' ? getNoteTypeClass(subject) : { li: '', feedIcon: '' });
            var messageHtml = v.message != null && v.message !== undefined ? String(v.message) : '';
            var taskGroup = v.task_group || '';
            var followupDate = (v.followup_date_display && String(v.followup_date_display)) || v.followup_date || '';
            var date = v.date || '';
            var fullName = (v.name || 'Staff').trim() || 'Staff';
            var createdAtYmd = v.created_at_ymd || '';
            var id = v.activity_id;
            var subjectOnly = v.subject_without_staff_prefix === true;
            var activityTypeClass = ' activity-type-' + activityType;
            if (activityType === 'note') {
                activityTypeClass += noteAdd.li;
            }
            var feedItemClass = activityType === 'stage' ? 'feed-item--stage' : 'feed-item--email';

            var bodyPlain = stripHtmlToText(messageHtml);
            var canConvert = /added a note|updated a note/i.test(String(subject));
            var isStage = activityType === 'stage';
            var isExpandable;
            if (isStage) {
                isExpandable = stripHtmlToText(messageHtml) !== '';
            } else {
                isExpandable = bodyPlain !== '' || !!(taskGroup && String(taskGroup).length) || !!(followupDate && String(followupDate).length) || canConvert;
            }

            var noExpandClass = isExpandable ? '' : ' feed-item--no-expand';
            var summaryTitle;
            var summaryMeta;
            if (isStage) {
                summaryTitle = 'Stage updated';
                summaryMeta = fullName + ' · ' + date;
            } else if (subjectOnly) {
                summaryTitle = subject;
                summaryMeta = fullName + ' · ' + date;
            } else {
                summaryTitle = subject;
                summaryMeta = fullName + ' · ' + date;
            }
            var detailId = 'feed-detail-js-' + id;
            var headline = subjectOnly ? escapeHtml(subject) : (escapeHtml(fullName) + '  ' + escapeHtml(subject));

            var summaryInner = '<span class="feed-item-summary-main">' +
                '<span class="feed-item-summary-text">' + escapeHtml(summaryTitle) + '</span>' +
                '<span class="feed-item-summary-meta">' + escapeHtml(summaryMeta) + '</span>' +
                '</span>';

            var liOpen = '<li class="feed-item ' + feedItemClass + ' activity' + activityTypeClass + noExpandClass + '" id="activity_' + id + '" data-created-at="' + escapeAttr(createdAtYmd) + '">' +
                '<span class="feed-icon ' + (icon.cls || '') + '">' + icon.html + '</span>' +
                '<div class="feed-content">';

            if (isExpandable) {
                if (isStage) {
                    liOpen += '<button type="button" class="feed-item-summary" data-feed-toggle aria-expanded="false" aria-controls="' + detailId + '" aria-label="Show or hide full activity content">' +
                        summaryInner +
                        '<span class="feed-item-summary-chevron" aria-hidden="true"><i class="fa-solid fa-chevron-down"></i></span></button>' +
                        '<div class="feed-item-detail" id="' + detailId + '" hidden>' +
                        '<div class="feed-item-body-outer" data-clampable="1">' +
                        '<div class="feed-item-body-chunk">' + (messageHtml || '') + '</div>' +
                        '<button type="button" class="feed-item-body-more btn btn-link btn-sm p-0" hidden>Show more</button></div></div>';
                } else {
                    liOpen += '<button type="button" class="feed-item-summary" data-feed-toggle aria-expanded="false" aria-controls="' + detailId + '" aria-label="Show or hide full activity content">' +
                        summaryInner +
                        '<span class="feed-item-summary-chevron" aria-hidden="true"><i class="fa-solid fa-chevron-down"></i></span></button>' +
                        '<div class="feed-item-detail" id="' + detailId + '" hidden>' +
                        '<p class="feed-item-full-headline mb-0"><strong>' + headline + '</strong>' +
                        (canConvert
                            ? '<i class="fa-solid fa-ellipsis-vertical convert-activity-to-note" style="margin-left: 5px; cursor: pointer;" title="Convert to Note" data-activity-id="' + id + '" data-activity-subject="' + escapeAttr(subject) + '" data-activity-description="' + escapeAttr(v.raw_description != null ? v.raw_description : '') + '" data-activity-created-by="' + escapeAttr(v.created_by) + '" data-activity-created-at="' + escapeAttr(v.raw_created_at != null ? v.raw_created_at : '') + '" data-client-id="' + escapeAttr((window.ClientDetailConfig && window.ClientDetailConfig.clientId) || '') + '"></i>'
                            : '') + '</p>';
                    if (messageHtml) {
                        liOpen += '<div class="feed-item-body-outer" data-clampable="1">' +
                            '<div class="feed-item-body-chunk">' + messageHtml + '</div>' +
                            '<button type="button" class="feed-item-body-more btn btn-link btn-sm p-0" hidden>Show more</button></div>';
                    }
                    if (taskGroup) {
                        liOpen += '<p class="mb-0 small">' + escapeHtml(String(taskGroup)) + '</p>';
                    }
                    if (followupDate) {
                        liOpen += '<p class="mb-0 small text-muted">' + escapeHtml(String(followupDate)) + '</p>';
                    }
                    liOpen += '</div>';
                }
            } else {
                liOpen += '<div class="feed-item-summary feed-item-summary--static" role="none">' +
                    summaryInner + '</div>';
            }

            liOpen += '</div></li>';
            html += liOpen;
        }
        if (!html) {
            return options.filtered ? FEED_NO_RESULTS_HTML : FEED_EMPTY_HTML;
        }
        if (options.has_more) {
            html += FEED_LOAD_SENTINEL_HTML;
        }
        return html;
    };

    function updateClampButtonForChunk($chunk) {
        var el = $chunk[0];
        if (!el) return;
        if (el.classList.contains('feed-item-body-chunk--expanded')) {
            return;
        }
        var $more = $chunk.closest('.feed-item-body-outer').find('.feed-item-body-more');
        if (el.offsetParent === null) {
            $more.attr('hidden', 'hidden');
            return;
        }
        if (el.scrollHeight > el.clientHeight + 1) {
            $more.removeAttr('hidden');
        } else {
            $more.attr('hidden', 'hidden');
        }
    }

    function initClampsInDetail($detail) {
        if (!$detail || !$detail.length) return;
        $detail.find('.feed-item-body-chunk').each(function() {
            var $c = $(this);
            if (!$c.data('ro')) {
                $c.data('ro', true);
                if (window.ResizeObserver) {
                    new window.ResizeObserver(function() { updateClampButtonForChunk($c); }).observe(this);
                }
            }
            updateClampButtonForChunk($c);
        });
    }

    window.initActivityFeedClamps = function() {
        $('#activity-feed .feed-item--expanded .feed-item-detail:visible .feed-item-body-chunk').each(function() {
            updateClampButtonForChunk($(this));
        });
    };

    function setupProgressiveDisclosure() {
        $(document).on('click', '.activity-feed .feed-item-summary[aria-controls]', function(e) {
            var $btn = $(this);
            var $li = $btn.closest('.feed-item');
            var $detail = $li.find('.feed-item-detail');
            var expanded = $btn.attr('aria-expanded') === 'true';
            if (expanded) {
                $btn.attr('aria-expanded', 'false');
                if ($detail.length) {
                    $detail.attr('hidden', 'hidden');
                }
                $li.removeClass('feed-item--expanded');
                if (isExpandAllActive()) {
                    setExpandAllActive(false);
                }
            } else {
                $btn.attr('aria-expanded', 'true');
                if ($detail.length) {
                    $detail.removeAttr('hidden');
                }
                $li.addClass('feed-item--expanded');
                requestAnimationFrame(function() {
                    initClampsInDetail($li.find('.feed-item-detail').first());
                });
            }
        });

        $(document).on('click', '.activity-feed .feed-item-body-more', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var $btn = $(this);
            var $outer = $btn.closest('.feed-item-body-outer');
            var $chunk = $outer.find('.feed-item-body-chunk');
            if ($chunk.length === 0) {
                return;
            }
            if ($chunk.hasClass('feed-item-body-chunk--expanded')) {
                $chunk.removeClass('feed-item-body-chunk--expanded');
                $btn.text('Show more');
            } else {
                $chunk.addClass('feed-item-body-chunk--expanded');
                $btn.text('Show less');
            }
            if (!$chunk.hasClass('feed-item-body-chunk--expanded')) {
                requestAnimationFrame(function() { updateClampButtonForChunk($chunk); });
            } else {
                $btn.removeAttr('hidden');
            }
        });
    }

    // Initialize when DOM is ready
    $(document).ready(function() {
        init();
        setupProgressiveDisclosure();
    });

    // Expose public API
    window.ActivityFeed = {
        init: init,
        filterActivities: filterActivities,
        reapplyFilters: reapplyFilters,
        ensureTimelineFiltersVisible: ensureTimelineFiltersVisible,
        loadActivities: loadActivities,
        expandAll: function() {
            setExpandAllActive(true);
            setAllFeedItemsExpanded(true);
        },
        collapseAll: function() {
            setExpandAllActive(false);
            setAllFeedItemsExpanded(false);
        }
    };

})(jQuery);

