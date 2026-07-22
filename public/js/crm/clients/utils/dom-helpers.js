/**
 * DOM/layout helper utilities for client detail pages.
 * Extracted from detail-main.js - Phase 2 refactoring.
 * Requires: jQuery
 */
(function($) {
    'use strict';
    if (!$) return;

    /**
     * Adjust activity feed height based on viewport and content.
     */
    function adjustActivityFeedHeight() {
        if (!$('.activity-feed').length || !$('.crm-container').length) {
            return;
        }

        var $container = $('.crm-container');
        var isUnified = $container.hasClass('crm-container--unified');

        if (!isUnified && !$('.main-content').length) {
            return;
        }

        /* Unified layout: Timeline tab fills the grid row via CSS; other tabs hide the feed. */
        if (isUnified) {
            if ($('.main-content').length && $('.main-content').is(':visible')) {
                $('.main-content').css('max-height', 'none');
                $('.main-content').css('overflow-y', 'visible');
                $('.main-content').css('height', 'auto');
            }

            if (!$('.activity-feed').is(':visible') || $container.hasClass('crm-container--no-feed')) {
                $container.css('align-items', '');
                $('.activity-feed').css('max-height', '');
                $('.activity-feed').css('height', '');
                $('.activity-feed').css('min-height', '');
                $('.activity-feed').css('overflow-y', '');
                return;
            }

            /* Dedicated Activity/Timeline tab: stretch feed to viewport (no fixed px height). */
            if ($container.hasClass('crm-container--activity-tab')) {
                $container.css('align-items', 'stretch');
                $('.activity-feed').css('max-height', '');
                $('.activity-feed').css('height', '');
                $('.activity-feed').css('min-height', '');
                $('.activity-feed').css('overflow-y', '');
                return;
            }

            $container.css('align-items', 'start');

            var $feed = $('.activity-feed');
            var feedTop = $feed.offset() ? $feed.offset().top : 0;
            var bottomGutter = 24;
            var targetHeight = Math.max(320, $(window).height() - feedTop - bottomGutter);

            $feed.css('max-height', targetHeight + 'px');
            $feed.css('height', targetHeight + 'px');
            $feed.css('overflow-y', 'auto');
            return;
        }

        var windowHeight = $(window).height();
        var maxAvailableHeight = windowHeight - 120;

        $('.crm-container').css('align-items', 'flex-start');

        var mainVisible = $('.main-content').is(':visible');
        if (mainVisible) {
            $('.main-content').css('max-height', 'none');
            $('.main-content').css('overflow-y', 'visible');
            $('.main-content').css('height', 'auto');
        }

        var mainContentHeight = mainVisible ? $('.main-content').outerHeight() : 0;
        var activityFeedContentHeight = $('.activity-feed').prop('scrollHeight');
        var hasSubstantialContent = activityFeedContentHeight > 100;

        var targetHeight;
        if (!mainVisible) {
            targetHeight = maxAvailableHeight;
        } else if (hasSubstantialContent) {
            targetHeight = Math.max(mainContentHeight, maxAvailableHeight);
        } else {
            targetHeight = Math.min(mainContentHeight, maxAvailableHeight);
        }

        $('.activity-feed').css('max-height', targetHeight + 'px');
        $('.activity-feed').css('height', targetHeight + 'px');
        $('.activity-feed').css('overflow-y', 'auto');
    }

    var clientDocumentsTabConfigs = [
        { selector: '#matterdocuments-tab', paneSelector: '.subtab6-pane.active' },
        { selector: '#personaldocuments-tab', paneSelector: '.subtab2-pane.active' }
    ];

    /**
     * Personal documents: preview + list row at full viewport height.
     */
    function adjustPersonalDocPreviewHeight() {
        var $tab = $('#personaldocuments-tab');
        if (!$tab.length || !$tab.hasClass('active')) {
            return;
        }

        var $pane = $tab.find('.subtab2-pane.active');
        var $preview = $pane.find('.client-doc-preview-pane').first();
        if (!$preview.length) {
            return;
        }

        var fullHeight = '100vh';

        $preview.css({
            height: fullHeight,
            minHeight: fullHeight,
            maxHeight: fullHeight,
            flex: '1 1 ' + fullHeight
        });

        $pane.css({
            minHeight: fullHeight,
            height: fullHeight,
            alignItems: 'stretch'
        });

        var $listPanel = $pane.find('.checklist-table-container');
        if ($listPanel.length) {
            $listPanel.css({
                minHeight: fullHeight,
                maxHeight: fullHeight,
                height: fullHeight
            });
        }

        var $content = $tab.find('.subtab2-content').first();
        if ($content.length) {
            $content.css({
                minHeight: fullHeight,
                height: fullHeight,
                maxHeight: 'none'
            });
        }
    }

    /**
     * Size Personal/Matter document tabs so the checklist list + preview row fills the viewport.
     */
    function adjustClientDocumentsPanelHeight() {
        var bottomGutter = 12;
        var minRowHeight = 400;
        var isMobile = $(window).width() <= 768;
        var viewportHeight = window.innerHeight || $(window).height();

        if ($('#personaldocuments-tab').hasClass('active') && !isMobile) {
            adjustPersonalDocPreviewHeight();
            return;
        }

        clientDocumentsTabConfigs.forEach(function(cfg) {
            var $tab = $(cfg.selector);
            if (!$tab.length) {
                return;
            }

            var $content = $tab.find('.subtab2-content, .subtab6-content').first();
            var $pane = $tab.find(cfg.paneSelector);
            var $listPanel = $pane.find('.checklist-table-container');
            var $preview = $pane.find('.client-doc-preview-pane').first();

            if (!$tab.hasClass('active')) {
                $tab.css({ height: '', maxHeight: '', minHeight: '' });
                $content.css({ height: '', minHeight: '', maxHeight: '' });
                $pane.css({ height: '', minHeight: '', maxHeight: '' });
                $listPanel.css({ height: '', minHeight: '' });
                $preview.css({ height: '', minHeight: '' });
                return;
            }

            if (!$content.length || !$pane.length || !$listPanel.length || !$preview.length) {
                return;
            }

            var tabTop = $tab.offset().top;
            var tabHeight = Math.max(isMobile ? 480 : 560, viewportHeight - tabTop - bottomGutter);
            $tab.css({
                height: tabHeight + 'px',
                maxHeight: tabHeight + 'px',
                minHeight: tabHeight + 'px'
            });

            // Size only the list + preview row from its top edge to the viewport bottom.
            var contentTop = $content.offset().top;
            var rowHeight = Math.max(minRowHeight, viewportHeight - contentTop - bottomGutter);
            $content.css({
                height: rowHeight + 'px',
                minHeight: rowHeight + 'px',
                maxHeight: rowHeight + 'px'
            });
            $pane.css({
                height: '100%',
                minHeight: '100%'
            });

            if (isMobile) {
                var listHeight = $listPanel.outerHeight(true);
                var previewHeight = Math.max(240, rowHeight - listHeight - 12);
                $preview.css({
                    height: previewHeight + 'px',
                    minHeight: previewHeight + 'px'
                });
                $listPanel.css({ height: '', minHeight: '' });
                return;
            }

            $listPanel.css({ height: '100%', minHeight: '100%' });
            $preview.css({ height: '100%', minHeight: '100%' });
        });
    }

    function scheduleClientDocumentsPanelHeightAdjust() {
        adjustClientDocumentsPanelHeight();
        adjustPersonalDocPreviewHeight();
        window.requestAnimationFrame(function() {
            adjustClientDocumentsPanelHeight();
            adjustPersonalDocPreviewHeight();
        });
        setTimeout(function() {
            adjustClientDocumentsPanelHeight();
            adjustPersonalDocPreviewHeight();
        }, 150);
        setTimeout(function() {
            adjustClientDocumentsPanelHeight();
            adjustPersonalDocPreviewHeight();
        }, 400);
    }

    /** @deprecated Use adjustClientDocumentsPanelHeight */
    function adjustMatterDocumentsPanelHeight() {
        adjustClientDocumentsPanelHeight();
    }

    /**
     * Adjust file preview container heights based on viewport.
     */
    function adjustPreviewContainers() {
        if ($('#matterdocuments-tab').hasClass('active') || $('#personaldocuments-tab').hasClass('active')) {
            adjustClientDocumentsPanelHeight();
            return;
        }

        $('.preview-pane.file-preview-container').not('.client-doc-preview-pane').each(function() {
            var windowHeight = $(window).height();
            var containerTop = $(this).offset().top;
            var desiredHeight = windowHeight - containerTop - 50;

            if (desiredHeight >= 600) {
                $(this).css('height', desiredHeight + 'px');
            } else {
                $(this).css('height', '600px');
            }
        });
    }

    /**
     * Trigger file download via temporary anchor element.
     * @param {string} url - Download URL
     * @param {string} fileName - Suggested filename
     */
    function downloadFile(url, fileName) {
        var link = document.createElement('a');
        link.href = url;
        link.download = fileName;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    window.adjustActivityFeedHeight = adjustActivityFeedHeight;
    window.adjustClientDocumentsPanelHeight = adjustClientDocumentsPanelHeight;
    window.adjustPersonalDocPreviewHeight = adjustPersonalDocPreviewHeight;
    window.scheduleClientDocumentsPanelHeightAdjust = scheduleClientDocumentsPanelHeightAdjust;
    window.adjustMatterDocumentsPanelHeight = adjustMatterDocumentsPanelHeight;
    window.adjustPreviewContainers = adjustPreviewContainers;
    window.downloadFile = downloadFile;

    if (typeof jQuery !== 'undefined') {
        jQuery(window).on('load', scheduleClientDocumentsPanelHeightAdjust);
    }

})(typeof jQuery !== 'undefined' ? jQuery : null);
