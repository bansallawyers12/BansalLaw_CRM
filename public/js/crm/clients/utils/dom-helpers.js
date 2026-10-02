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

            /* Timeline tab: feed is in-pane; let CSS max-height + flex scroll handle layout. */
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

    function getClientDocViewportHeight() {
        if (window.visualViewport && typeof window.visualViewport.height === 'number') {
            return window.visualViewport.height;
        }
        return window.innerHeight || $(window).height();
    }

    function previewPaneHasOpenDocument($preview) {
        if (!$preview || !$preview.length) {
            return false;
        }
        if ($preview.find('.preview-iframe').length) {
            return true;
        }
        if ($preview.find('.preview-content-with-loader, .preview-content-loading').length) {
            return true;
        }
        return $preview.find('.preview-text-body').length > 0;
    }

    /**
     * Personal/Matter documents: preview + list row at full viewport height (100vh).
     */
    function adjustPersonalDocPreviewHeight() {
        adjustClientDocumentsPanelHeight();
    }

    function adjustMatterDocPreviewHeight() {
        adjustClientDocumentsPanelHeight();
    }

    /**
     * Set explicit heights on preview pane / iframe so preview fills 100vh on all screens.
     */
    function applyClientDocPreviewViewportHeight($preview, rowHeight) {
        if (!$preview || !$preview.length) {
            return;
        }
        var hasPreviewBody = $preview.find('.preview-iframe').length
            || $preview.find('.preview-text-body').length
            || $preview.find('.preview-media-body').length
            || $preview.find('.preview-content-loading, .preview-content-with-loader').length;
        if (!hasPreviewBody) {
            return;
        }

        $preview.css({
            height: '100vh',
            minHeight: '100vh',
            maxHeight: '100vh',
            flex: '1 1 auto'
        });

        var $content = $preview.find('.preview-content').first();
        if ($content.length) {
            $content.css({
                height: '100%',
                minHeight: '100%',
                maxHeight: '100%',
                flex: '1 1 auto'
            });
        }

        var headerH = $content.length ? ($content.find('.client-doc-preview-header').outerHeight(true) || 0) : 0;
        var toolbarH = $content.length ? ($content.find('.client-doc-preview-office-bar').outerHeight(true) || 0) : 0;
        var innerBody = 'calc(100vh - ' + (headerH + toolbarH) + 'px)';

        var $wrap = $preview.find('.preview-iframe-wrap').first();
        if ($wrap.length) {
            $wrap.css({
                height: innerBody,
                minHeight: innerBody,
                maxHeight: innerBody,
                flex: '1 1 auto',
                position: 'relative',
                overflow: 'hidden'
            });
            $wrap.find('.preview-iframe').css({
                position: 'absolute',
                top: '0',
                left: '0',
                right: '0',
                bottom: '0',
                width: '100%',
                height: '100%',
                minHeight: '0',
                border: 'none'
            });
        }

        var $textBody = $preview.find('.preview-text-body').first();
        if ($textBody.length) {
            $textBody.css({
                height: innerBody,
                minHeight: innerBody,
                maxHeight: innerBody
            });
        }

        var $mediaBody = $preview.find('.preview-media-body').first();
        if ($mediaBody.length) {
            $mediaBody.css({
                height: innerBody,
                minHeight: innerBody,
                maxHeight: innerBody,
                overflow: 'auto'
            });
        }
    }

    /**
     * Size Personal/Matter document tabs so preview is 100vh on all screen types.
     */
    function adjustClientDocumentsPanelHeight() {
        var isMobile = $(window).width() <= 768;

        clientDocumentsTabConfigs.forEach(function(cfg) {
            var $tab = $(cfg.selector);
            if (!$tab.length) {
                return;
            }

            var $docContainer = $tab.find('.documentalls-container').first();
            var $docStack = $tab.find('.visa-documents-content, .personal-documents-content').first();
            var $content = $tab.find('.subtab2-content, .subtab6-content').first();
            var $pane = $tab.find(cfg.paneSelector);
            var $listPanel = $pane.find('.checklist-table-container');
            var $preview = $pane.find('.client-doc-preview-pane').first();

            if (!$tab.hasClass('active')) {
                $tab.removeClass('client-doc-viewport-fill');
                $tab.css({ height: '', maxHeight: '', minHeight: '' });
                if ($docContainer.length) {
                    $docContainer.css({ height: '', minHeight: '', maxHeight: '' });
                }
                if ($docStack.length) {
                    $docStack.css({ height: '', minHeight: '', maxHeight: '' });
                }
                $content.css({ height: '', minHeight: '', maxHeight: '' });
                $pane.css({ height: '', minHeight: '', maxHeight: '' });
                $listPanel.css({ height: '', minHeight: '' });
                $preview.css({ height: '', minHeight: '' });
                return;
            }

            if (!$content.length || !$pane.length || !$preview.length) {
                return;
            }

            var isFullPreview = $pane.hasClass('hide-list-view');
            var hasOpenPreview = previewPaneHasOpenDocument($preview);
            var useViewportFill = hasOpenPreview || isFullPreview;

            $tab.toggleClass('client-doc-viewport-fill', useViewportFill);

            document.documentElement.style.setProperty('--client-doc-fill-height', '100vh');
            if ($docContainer.length) {
                $docContainer.css({
                    height: 'auto',
                    minHeight: '100vh',
                    maxHeight: 'none'
                });
            }
            if ($docStack.length) {
                $docStack.css({
                    height: 'auto',
                    minHeight: '100vh',
                    maxHeight: 'none'
                });
            }
            $tab.css({
                height: 'auto',
                maxHeight: 'none',
                minHeight: '100vh'
            });

            $content.css({
                height: isMobile ? 'auto' : '100vh',
                minHeight: '100vh',
                maxHeight: isMobile ? 'none' : '100vh'
            });
            $pane.css({
                height: isMobile ? 'auto' : '100vh',
                minHeight: '100vh',
                maxHeight: isMobile ? 'none' : '100vh'
            });

            if (isMobile) {
                $listPanel.css({ height: 'auto', minHeight: '', maxHeight: '45vh' });
                $preview.css({
                    width: '100%',
                    maxWidth: '100%',
                    height: '100vh',
                    minHeight: '100vh',
                    maxHeight: '100vh'
                });
                applyClientDocPreviewViewportHeight($preview);
                return;
            }

            if (isFullPreview) {
                $listPanel.css({ height: '', minHeight: '', maxHeight: '' });
                $preview.css({
                    width: '100%',
                    maxWidth: '100%',
                    flex: '1 1 100%',
                    height: '100vh',
                    minHeight: '100vh',
                    maxHeight: '100vh'
                });
                applyClientDocPreviewViewportHeight($preview);
                return;
            }

            if ($listPanel.length) {
                $listPanel.css({ height: '100vh', minHeight: '100vh', maxHeight: '100vh' });
            }
            $preview.css({
                height: '100vh',
                minHeight: '100vh',
                maxHeight: '100vh'
            });
            if ($preview.find('.preview-iframe').length || $preview.find('.preview-text-body').length
                || $preview.find('.preview-media-body').length
                || $preview.find('.preview-content-loading, .preview-content-with-loader').length) {
                applyClientDocPreviewViewportHeight($preview);
            }
        });
    }

    function scheduleClientDocumentsPanelHeightAdjust() {
        adjustClientDocumentsPanelHeight();
        window.requestAnimationFrame(adjustClientDocumentsPanelHeight);
        setTimeout(adjustClientDocumentsPanelHeight, 150);
        setTimeout(adjustClientDocumentsPanelHeight, 400);
        setTimeout(adjustClientDocumentsPanelHeight, 900);
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
            scheduleClientDocumentsPanelHeightAdjust();
            return;
        }

        $('.preview-pane.file-preview-container').not('.client-doc-preview-pane').each(function() {
            $(this).css({
                height: '100vh',
                minHeight: '100vh',
                maxHeight: '100vh'
            });
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
    window.applyClientDocPreviewViewportHeight = applyClientDocPreviewViewportHeight;
    window.adjustPersonalDocPreviewHeight = adjustPersonalDocPreviewHeight;
    window.adjustMatterDocPreviewHeight = adjustMatterDocPreviewHeight;
    window.scheduleClientDocumentsPanelHeightAdjust = scheduleClientDocumentsPanelHeightAdjust;
    window.adjustMatterDocumentsPanelHeight = adjustMatterDocumentsPanelHeight;
    window.adjustPreviewContainers = adjustPreviewContainers;
    window.downloadFile = downloadFile;

    if (typeof jQuery !== 'undefined') {
        jQuery(window).on('load', scheduleClientDocumentsPanelHeightAdjust);
        var clientDocPanelResizeTimer;
        jQuery(window).on('resize', function() {
            clearTimeout(clientDocPanelResizeTimer);
            clientDocPanelResizeTimer = setTimeout(scheduleClientDocumentsPanelHeightAdjust, 100);
        });
        if (window.visualViewport) {
            window.visualViewport.addEventListener('resize', function() {
                clearTimeout(clientDocPanelResizeTimer);
                clientDocPanelResizeTimer = setTimeout(scheduleClientDocumentsPanelHeightAdjust, 100);
            });
        }
    }

})(typeof jQuery !== 'undefined' ? jQuery : null);
