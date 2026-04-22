/**
 * Activity Feed Functionality
 * Handles filtering, width toggle, and activity feed interactions
 */

(function($) {
    'use strict';

    /**
     * Initialize Activity Feed functionality
     */
    function init() {
        setupFilterButtons();
        setupWidthToggle();
        setupExtendedFilters();
        setupRefreshButton();
    }

    /**
     * Setup refresh button to reload activities
     */
    function setupRefreshButton() {
        $('#activity-feed-refresh').on('click', function() {
            var $btn = $(this).find('i');
            $btn.addClass('fa-spin');
            if (typeof window.loadActivities === 'function') {
                window.loadActivities();
            }
            if (typeof getallactivities === 'function') {
                getallactivities();
            }
            setTimeout(function() { $btn.removeClass('fa-spin'); }, 800);
        });
    }

    /**
     * Setup activity filter buttons
     * Type filter works with extended filters (search, date) when they are active
     */
    function setupFilterButtons() {
        $('.activity-filter-btn').on('click', function() {
            $('.activity-filter-btn').removeClass('active');
            $(this).addClass('active');
            // Use applyExtendedFilters so type + search + date are combined when filter bar is visible
            if ($('#activity-feed-filter-bar').is(':visible')) {
                applyExtendedFilters();
            } else {
                filterActivities($(this).data('filter'));
            }
        });
    }

    /**
     * Filter activities based on type
     * @param {string} filterType - The type of filter to apply (all, activity, note, document, accounting)
     */
    function filterActivities(filterType) {
        if (filterType === 'all') {
            $('.feed-item.activity').show();
        } else if (filterType === 'activity') {
            // Show activity-type-activity, activity-type-sms, and activity-type-stage (workflow)
            $('.feed-item.activity').hide();
            $('.feed-item.activity-type-activity, .feed-item.activity-type-sms, .feed-item.activity-type-stage').show();
        } else if (filterType === 'note') {
            // Show only actual notes (exclude activity edits, SMS, documents, and accounting)
            $('.feed-item.activity').each(function() {
                var $item = $(this);
                // Hide Activity edits, SMS, stage, document, and accounting activities, show everything else (notes)
                if (!$item.hasClass('activity-type-sms') && 
                    !$item.hasClass('activity-type-activity') &&
                    !$item.hasClass('activity-type-stage') &&
                    !$item.hasClass('activity-type-document') && 
                    !$item.hasClass('activity-type-signature') &&
                    !$item.hasClass('activity-type-financial')) {
                    $item.show();
                } else {
                    $item.hide();
                }
            });
        } else if (filterType === 'document') {
            $('.feed-item.activity').hide();
            // Show document activities - check both class and subject text
            $('.feed-item.activity').each(function() {
                var $item = $(this);
                var hasDocumentClass = $item.hasClass('activity-type-document');
                
                // If it has the document class, show it
                if (hasDocumentClass) {
                    $item.show();
                    return;
                }
                
                // Fallback: Check subject text for document-related keywords
                // This handles legacy activities that don't have activity_type set
                var subject = $item.find('.feed-content strong').text().toLowerCase();
                var subjectText = subject || '';
                
                // Document-related patterns to match (but exclude accounting-related receipt documents)
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
                    'document.*attached',
                    'document.*detached'
                ];
                
                // Check if subject matches any document pattern (but not accounting receipt documents)
                var isAccountingReceiptDoc = /(receipt document|journal receipt document|client receipt document|office receipt document)/i.test(subjectText);
                var isDocument = !isAccountingReceiptDoc && documentPatterns.some(function(pattern) {
                    var regex = new RegExp(pattern, 'i');
                    return regex.test(subjectText);
                });
                
                if (isDocument) {
                    $item.show();
                }
            });
        } else if (filterType === 'signature') {
            $('.feed-item.activity').hide();
            $('.feed-item.activity-type-signature').show();
        } else if (filterType === 'accounting') {
            $('.feed-item.activity').hide();
            // Show accounting activities - check both class and subject text
            $('.feed-item.activity').each(function() {
                var $item = $(this);
                var hasFinancialClass = $item.hasClass('activity-type-financial');
                
                // If it has the financial class, show it
                if (hasFinancialClass) {
                    $item.show();
                    return;
                }
                
                // Fallback: Check subject text for accounting-related keywords
                // This handles legacy activities that don't have activity_type set
                var subject = $item.find('.feed-content strong').text().toLowerCase();
                var subjectText = subject || '';
                
                // Accounting-related patterns to match
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
                
                // Check if subject matches any accounting pattern
                var isAccounting = accountingPatterns.some(function(pattern) {
                    var regex = new RegExp(pattern, 'i');
                    return regex.test(subjectText);
                });
                
                if (isAccounting) {
                    $item.show();
                }
            });
        } else {
            // Show only activities with specific type (for other filters like document, accounting)
            $('.feed-item.activity').hide();
            $('.feed-item.activity-type-' + filterType).show();
        }
    }

    /**
     * Setup width toggle checkbox
     * When checked, shows extended filter bar (search, date range, apply/reset)
     */
    function setupWidthToggle() {
        $('#increase-activity-feed-width').on('change', function() {
            var onActivityTab = $('.crm-container').hasClass('crm-container--activity-tab');
            if ($(this).is(':checked')) {
                // On the full-width Activity tab the feed already fills the viewport —
                // only open the filter bar; don't add wide-mode / compact-mode.
                if (!onActivityTab) {
                    $('.activity-feed').addClass('wide-mode');
                    if ($('.main-content').is(':visible')) {
                        $('.main-content').addClass('compact-mode');
                    }
                }
                $('#activity-feed-filter-bar').slideDown(200);
                initActivityFeedDatepickers();
            } else {
                $('#activity-feed-filter-bar').slideUp(200);
                if (!onActivityTab) {
                    $('.activity-feed').removeClass('wide-mode');
                    $('.main-content').removeClass('compact-mode');
                }
            }
            
            // Adjust Activity Feed height after layout change
            if (typeof adjustActivityFeedHeight === 'function') {
                adjustActivityFeedHeight();
                setTimeout(function() {
                    adjustActivityFeedHeight();
                }, 150);
            }
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
        var searchVal = ($('#activity-feed-search').val() || '').trim().toLowerCase();
        var dateFrom = ($('#activity-feed-date-from').val() || '').trim();
        var dateTo = ($('#activity-feed-date-to').val() || '').trim();
        var activeType = $('.activity-filter-btn.active').data('filter') || 'all';

        $('.feed-item.activity').each(function() {
            var $item = $(this);
            var typeMatch = matchesTypeFilter($item, activeType);
            var searchMatch = !searchVal || $item.find('.feed-content').text().toLowerCase().indexOf(searchVal) >= 0;
            var itemDate = $item.attr('data-created-at') || '';
            var dateMatch = true;
            if (itemDate) {
                if (dateFrom && itemDate < dateFrom) dateMatch = false;
                if (dateTo && itemDate > dateTo) dateMatch = false;
            }
            $item.toggle(typeMatch && searchMatch && dateMatch);
        });

        updateEmptyState();
    }

    /**
     * Check if item matches the current type filter
     */
    function matchesTypeFilter($item, filterType) {
        if (filterType === 'all') return true;
        if (filterType === 'activity') {
            return $item.hasClass('activity-type-activity') || $item.hasClass('activity-type-sms') || $item.hasClass('activity-type-stage');
        }
        if (filterType === 'note') {
            return !$item.hasClass('activity-type-sms') && !$item.hasClass('activity-type-activity') &&
                !$item.hasClass('activity-type-stage') &&
                !$item.hasClass('activity-type-document') && !$item.hasClass('activity-type-financial');
        }
        if (filterType === 'document') {
            if ($item.hasClass('activity-type-document')) return true;
            var subject = ($item.find('.feed-content strong').text() || '').toLowerCase();
            if (/(receipt document|journal receipt document|client receipt document|office receipt document)/i.test(subject)) return false;
            var docPatterns = ['document', 'added.*document', 'updated.*document', 'visa document', 'matter document', 'personal document', 'checklist', 'uploaded', 'signed document', 'placed signature fields on matter document', 'placed signature fields on visa document'];
            return docPatterns.some(function(p) { return new RegExp(p, 'i').test(subject); });
        }
        if (filterType === 'accounting') {
            if ($item.hasClass('activity-type-financial')) return true;
            var subj = ($item.find('.feed-content strong').text() || '').toLowerCase();
            return /invoice|receipt|payment|ledger|account/.test(subj);
        }
        return true;
    }

    /**
     * Show/hide empty state when no activities match
     */
    function updateEmptyState() {
        var visible = $('.feed-item.activity:visible').length;
        $('.feed-item--empty').toggle(visible === 0);
        $('.feed-item-no-results').toggle(visible === 0 && $('.feed-item.activity').length > 0);
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
            return { html: '<i class="fas fa-sms"></i>', cls: 'feed-icon-sms' };
        }
        if (activityType === 'document') {
            return { html: '<i class="fas fa-file-alt"></i>', cls: '' };
        }
        if (activityType === 'signature') {
            return { html: '<i class="fas fa-file-signature"></i>', cls: 'feed-icon-signature' };
        }
        if (activityType === 'financial') {
            return { html: '<i class="fas fa-dollar-sign"></i>', cls: 'feed-icon-accounting' };
        }
        if (activityType === 'note') {
            var nt = getNoteTypeClass(subject);
            var ic = (subject || '').toLowerCase().indexOf('call') !== -1 ? 'fa-phone' : (sl.indexOf('email') !== -1 ? 'fa-envelope' : (sl.indexOf('in-person') !== -1 ? 'fa-user-friends' : (sl.indexOf('attention') !== -1 ? 'fa-exclamation-triangle' : (sl.indexOf('others') !== -1 ? 'fa-ellipsis-h' : 'fa-sticky-note'))));
            return { html: '<i class="fas ' + ic + '"></i>', cls: 'feed-icon-note' + nt.feedIcon };
        }
        if (activityType === 'activity') {
            return { html: '<i class="fas fa-bolt"></i>', cls: 'feed-icon-activity' };
        }
        if (activityType === 'stage') {
            return { html: '<i class="fas fa-tasks" aria-hidden="true"></i>', cls: 'feed-icon-stage' };
        }
        if (sl.indexOf('invoice') !== -1 || sl.indexOf('receipt') !== -1 || sl.indexOf('ledger') !== -1 || sl.indexOf('payment') !== -1 || sl.indexOf('account') !== -1) {
            return { html: '<i class="fas fa-dollar-sign"></i>', cls: '' };
        }
        if (sl.indexOf('document') !== -1) {
            return { html: '<i class="fas fa-file-alt"></i>', cls: '' };
        }
        return { html: '<i class="fas fa-sticky-note"></i>', cls: '' };
    }

    /**
     * Returns full HTML for all activity rows (replaces .feed-list contents).
     */
    window.buildActivityFeedListHtml = function (data) {
        if (!data || !data.length) return '';
        var html = '';
        for (var k = 0; k < data.length; k++) {
            var v = data[k];
            if (v.activity_id == null) {
                continue;
            }
            var activityType = v.activity_type || 'note';
            var subject = v.subject || '';
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
            var summaryLine;
            if (isStage) {
                summaryLine = 'Stage' + ' · ' + fullName + ' · ' + date;
            } else {
                if (subjectOnly) {
                    summaryLine = (subject + ' · ' + fullName + ' · ' + date).trim();
                } else {
                    summaryLine = (fullName + ' ' + subject + ' · ' + date).trim();
                }
            }
            var detailId = 'feed-detail-js-' + id;
            var headline = subjectOnly ? escapeHtml(subject) : (escapeHtml(fullName) + '  ' + escapeHtml(subject));

            var liOpen = '<li class="feed-item ' + feedItemClass + ' activity' + activityTypeClass + noExpandClass + '" id="activity_' + id + '" data-created-at="' + escapeAttr(createdAtYmd) + '">' +
                '<span class="feed-icon ' + (icon.cls || '') + '">' + icon.html + '</span>' +
                '<div class="feed-content">';

            if (isExpandable) {
                if (isStage) {
                    liOpen += '<button type="button" class="feed-item-summary" data-feed-toggle aria-expanded="false" aria-controls="' + detailId + '" aria-label="Show or hide full activity content">' +
                        '<span class="feed-item-summary-text">' + escapeHtml(summaryLine) + '</span>' +
                        '<span class="feed-item-summary-chevron" aria-hidden="true"><i class="fas fa-chevron-down"></i></span></button>' +
                        '<div class="feed-item-detail" id="' + detailId + '" hidden>' +
                        '<div class="feed-item-body-outer" data-clampable="1">' +
                        '<div class="feed-item-body-chunk">' + (messageHtml || '') + '</div>' +
                        '<button type="button" class="feed-item-body-more btn btn-link btn-sm p-0" hidden>Show more</button></div></div>';
                } else {
                    liOpen += '<button type="button" class="feed-item-summary" data-feed-toggle aria-expanded="false" aria-controls="' + detailId + '" aria-label="Show or hide full activity content">' +
                        '<span class="feed-item-summary-text">' + escapeHtml(summaryLine) + '</span>' +
                        '<span class="feed-item-summary-chevron" aria-hidden="true"><i class="fas fa-chevron-down"></i></span></button>' +
                        '<div class="feed-item-detail" id="' + detailId + '" hidden>' +
                        '<p class="feed-item-full-headline mb-0"><strong>' + headline + '</strong>' +
                        (canConvert
                            ? '<i class="fas fa-ellipsis-v convert-activity-to-note" style="margin-left: 5px; cursor: pointer;" title="Convert to Note" data-activity-id="' + id + '" data-activity-subject="' + escapeAttr(subject) + '" data-activity-description="' + escapeAttr(v.raw_description != null ? v.raw_description : '') + '" data-activity-created-by="' + escapeAttr(v.created_by) + '" data-activity-created-at="' + escapeAttr(v.raw_created_at != null ? v.raw_created_at : '') + '" data-client-id="' + escapeAttr((window.ClientDetailConfig && window.ClientDetailConfig.clientId) || '') + '"></i>'
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
                    '<span class="feed-item-summary-text">' + escapeHtml(summaryLine) + '</span></div>';
            }

            liOpen += '</div></li>';
            html += liOpen;
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
        $('.feed-item--expanded .feed-item-detail:visible .feed-item-body-chunk').each(function() {
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
        filterActivities: filterActivities
    };

})(jQuery);

