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
                    'added.*personal checklist',
                    'added.*visa checklist',
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
            var docPatterns = ['document', 'added.*document', 'updated.*document', 'visa document', 'personal document', 'checklist', 'uploaded', 'signed document'];
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

    // Initialize when DOM is ready
    $(document).ready(function() {
        init();
    });

    // Expose public API
    window.ActivityFeed = {
        init: init,
        filterActivities: filterActivities
    };

})(jQuery);

