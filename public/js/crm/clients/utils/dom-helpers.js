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

    /**
     * Adjust file preview container heights based on viewport.
     */
    function adjustPreviewContainers() {
        $('.preview-pane.file-preview-container').each(function() {
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
    window.adjustPreviewContainers = adjustPreviewContainers;
    window.downloadFile = downloadFile;

})(typeof jQuery !== 'undefined' ? jQuery : null);
