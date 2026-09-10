/**
 * Client document preview helpers.
 * Extracted from detail-main.js (CLI-4 progressive extraction).
 * Requires: jQuery. Exposes window.previewFile and related helpers.
 */
(function ($) {
    'use strict';
    if (!$) {
        return;
    }

    function renderPreviewLoadingOverlay(message) {
        return `
            <div class="preview-loading-overlay" style="position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; background: rgba(255,255,255,0.96); z-index: 5;">
                <div style="text-align: center; padding: 16px;">
                    <i class="fa-solid fa-spinner fa-spin fa-2x" style="color: #4a90e2;"></i>
                    <p class="preview-loading-message" style="margin-top: 12px; margin-bottom: 0; color: #666; font-size: 14px;">${message}</p>
                </div>
            </div>
        `;
    }

    function resolvePreviewContainer(containerId) {
        var $all = $('.' + containerId);
        if (!$all.length || $all.length === 1) {
            return $all;
        }

        var $visible = $all.filter(':visible');
        if ($visible.length) {
            return $visible.first();
        }

        var $activePane = $all.filter(function() {
            return $(this).closest('.subtab6-pane.active, .subtab2-pane.active').length > 0;
        });
        if ($activePane.length) {
            return $activePane.first();
        }

        return $all.first();
    }

    function isClientDocPreviewPane($container) {
        return $container && $container.length && $container.hasClass('client-doc-preview-pane');
    }

    function getPreviewFrameHeight($container, isOfficePreview) {
        if (isClientDocPreviewPane($container)) {
            return '100%';
        }

        return 'calc(100vh - ' + (isOfficePreview ? '140' : '100') + 'px)';
    }

    function buildPreviewHeaderHtml(fileType, fileUrl, fileLabel, options) {
        options = options || {};
        var normalizedType = (fileType || '').toLowerCase().replace(/^\./, '');
        var iconClass = documentFileIconClass(normalizedType);
        var label = fileLabel || normalizedType.toUpperCase() || 'Document';
        var safeLabel = $('<div/>').text(label).html();
        var downloadUrl = fileUrl + (fileUrl.indexOf('?') >= 0 ? '&' : '?') + 'download=1';
        var showToggleList = options.showToggleList !== false;
        var uploadedAt = String(options.uploadedAt || '').trim();
        var safeUploadedAt = uploadedAt ? $('<div/>').text(uploadedAt).html() : '';
        var isOfficePreview = normalizedType.match(/^(docx?|xlsx?|pptx?|rtf|odt|ods|odp|csv)$/);
        var typeBadge = isOfficePreview
            ? '<span class="client-doc-preview-type-badge">' + normalizedType.toUpperCase() + '</span>'
            : '';
        var toggleBtn = showToggleList
            ? '<button type="button" class="btn btn-sm client-doc-preview-action-btn client-doc-preview-toggle-list-btn" title="Toggle document list" aria-label="Toggle document list" onclick="var $pane=$(this).closest(\'.subtab6-pane, .subtab2-pane, .subtab-pane, .not-used-layout, .tab-pane\'); $pane.toggleClass(\'hide-list-view\'); $(this).toggleClass(\'is-active\');"><i class="fa-solid fa-bars" aria-hidden="true"></i></button>'
            : '';
        var uploadedMeta = safeUploadedAt
            ? '<div class="client-doc-preview-header-meta" title="Uploaded ' + safeUploadedAt + '">'
                + '<i class="fa-solid fa-clock" aria-hidden="true"></i>'
                + '<span class="client-doc-preview-uploaded-date">Uploaded ' + safeUploadedAt + '</span>'
                + '</div>'
            : '';

        return ''
            + '<div class="client-doc-preview-header">'
            + (showToggleList ? '<div class="client-doc-preview-header-start">' + toggleBtn + '</div>' : '')
            + '<div class="client-doc-preview-header-title">'
            + '<i class="fa-solid ' + iconClass + '" aria-hidden="true"></i>'
            + '<span class="client-doc-preview-filename" title="' + safeLabel + '">' + safeLabel + '</span>'
            + typeBadge
            + '</div>'
            + uploadedMeta
            + '<div class="client-doc-preview-header-actions">'
            + '<a href="' + fileUrl + '" target="_blank" rel="noopener" class="btn btn-sm client-doc-preview-action-btn client-doc-preview-open-btn" title="Open in new tab" aria-label="Open in new tab"><i class="fa-solid fa-up-right-from-square" aria-hidden="true"></i></a>'
            + '<a href="' + downloadUrl + '" class="btn btn-sm client-doc-preview-action-btn client-doc-preview-download-btn" title="Download file" aria-label="Download file"><i class="fa-solid fa-download" aria-hidden="true"></i></a>'
            + '</div>'
            + '</div>';
    }

    function resolveClientDocUploadedAt(fileUrl, docId) {
        var id = docId || extractDocumentIdFromPreviewUrl(fileUrl);
        if (!id) {
            return '';
        }

        var $row = $('#id_' + id + ' .doc-row').first();
        if (!$row.length) {
            $row = $('.doc-row[data-id="' + id + '"]').first();
        }
        if (!$row.length) {
            return '';
        }

        var fromData = String($row.attr('data-uploaded-at') || '').trim();
        if (fromData) {
            return fromData;
        }

        var title = String($row.attr('title') || '');
        var match = title.match(/\bon\s+(\d{1,2}\/\d{1,2}\/\d{4}(?:\s+\d{1,2}:\d{2})?)/i);
        return match ? match[1] : '';
    }

    function mountIframePreview(container, options) {
        const embeddedPreviewUrl = options.embeddedPreviewUrl;
        const toolbarHtml = options.toolbarHtml || '';
        const headerHtml = options.headerHtml || '';
        const loadingMessage = options.loadingMessage || 'Loading preview…';
        const slowMessage = options.slowMessage || 'Still loading preview… this may take up to a minute.';
        const frameHeight = options.frameHeight || getPreviewFrameHeight(container, false);
        const onError = typeof options.onError === 'function' ? options.onError : function() {};

        container.html(`
            <div class="preview-content preview-content-with-loader" style="flex: 1; display: flex; flex-direction: column; overflow: hidden; width: 100%; position: relative; min-height: 0;">
                ${headerHtml}
                ${toolbarHtml}
                <div class="preview-iframe-wrap">
                    ${renderPreviewLoadingOverlay(loadingMessage)}
                    <iframe class="preview-iframe" src="${embeddedPreviewUrl}" title="Document preview" style="width: 100%; height: ${frameHeight}; border: none; background: #fff;"></iframe>
                </div>
            </div>
        `);

        const $overlay = container.find('.preview-loading-overlay');
        const $iframe = container.find('.preview-iframe');
        const $message = container.find('.preview-loading-message');
        let finished = false;

        const finishLoading = function() {
            if (finished) {
                return;
            }
            finished = true;
            clearTimeout(slowTimer);
            clearTimeout(hardTimeout);
            $overlay.stop(true, true).fadeOut(200, function() {
                $(this).remove();
            });
        };

        const slowTimer = setTimeout(function() {
            if (!finished && $message.length) {
                $message.text(slowMessage);
            }
        }, 6000);

        const hardTimeout = setTimeout(function() {
            if (!finished) {
                finished = true;
                onError();
            }
        }, 120000);

        $iframe.on('load', function() {
            finishLoading();
            if (isClientDocPreviewPane(container)) {
                if (typeof window.scheduleClientDocumentsPanelHeightAdjust === 'function') {
                    window.scheduleClientDocumentsPanelHeightAdjust();
                } else if (typeof window.adjustMatterDocPreviewHeight === 'function') {
                    window.adjustMatterDocPreviewHeight();
                } else if (typeof window.adjustPersonalDocPreviewHeight === 'function') {
                    window.adjustPersonalDocPreviewHeight();
                }
            }
        });

        $iframe.on('error', function() {
            if (!finished) {
                finished = true;
                onError();
            }
        });
    }

    function extractDocumentIdFromPreviewUrl(fileUrl) {
        if (!fileUrl) {
            return null;
        }

        const urlPath = String(fileUrl).split('?')[0];
        let match = urlPath.match(/\/documents\/preview\/(\d+)/);
        if (match) {
            return match[1];
        }

        match = urlPath.match(/\/documents\/(\d+)\/preview-signed/);
        if (match) {
            return match[1];
        }

        return null;
    }

    function setMatterDocumentPreviewActive(fileUrl, containerId, containerEl) {
        if (!containerEl) {
            const $resolved = resolvePreviewContainer(containerId);
            containerEl = $resolved.length ? $resolved[0] : document.querySelector('.' + containerId);
        }
        if (!containerEl) {
            return;
        }

        const matterTab = containerEl.closest('#matterdocuments-tab');
        if (!matterTab) {
            return;
        }

        matterTab.querySelectorAll('tr.drow.is-preview-active').forEach(function(row) {
            row.classList.remove('is-preview-active');
        });
        matterTab.querySelectorAll('.doc-row.is-preview-active').forEach(function(docRow) {
            docRow.classList.remove('is-preview-active');
        });

        const docId = extractDocumentIdFromPreviewUrl(fileUrl);
        if (!docId) {
            return;
        }

        const row = matterTab.querySelector('#id_' + docId);
        if (row) {
            row.classList.add('is-preview-active');
            const docRow = row.querySelector('.doc-row');
            if (docRow) {
                docRow.classList.add('is-preview-active');
            }
        }
    }

    function documentFileIconClass(fileType) {
        const normalizedType = (fileType || '').toLowerCase().replace(/^\./, '');
        if (/^(mp4|webm|mov|m4v|avi|mkv|ogv|vob)$/.test(normalizedType)) {
            return 'fa-file-video';
        }
        if (/^(mp3|m4a|wav|ogg|aac)$/.test(normalizedType)) {
            return 'fa-file-audio';
        }
        if (/^(jpg|jpeg|png|gif|webp|bmp|tif|tiff)$/.test(normalizedType)) {
            return 'fa-file-image';
        }
        if (normalizedType === 'pdf') {
            return 'fa-file-pdf';
        }
        if (/^docx?$/.test(normalizedType) || /^(rtf|odt)$/.test(normalizedType)) {
            return 'fa-file-word';
        }
        if (/^xlsx?$/.test(normalizedType) || /^(csv|ods)$/.test(normalizedType)) {
            return 'fa-file-excel';
        }
        if (/^pptx?$/.test(normalizedType) || normalizedType === 'odp') {
            return 'fa-file-powerpoint';
        }
        return 'fa-file';
    }

    function previewVideoMimeType(fileType) {
        const normalizedType = (fileType || '').toLowerCase().replace(/^\./, '');
        const mimeMap = {
            mp4: 'video/mp4',
            webm: 'video/webm',
            mov: 'video/quicktime',
            m4v: 'video/x-m4v',
            avi: 'video/x-msvideo',
            mkv: 'video/x-matroska',
            ogv: 'video/ogg',
            vob: 'video/mpeg'
        };
        return mimeMap[normalizedType] || 'video/mp4';
    }

    function downloadDocumentFile(fileUrl, fileLabel) {
        if (!fileUrl) {
            return;
        }
        var downloadUrl = fileUrl + (fileUrl.indexOf('?') >= 0 ? '&' : '?') + 'download=1';
        var link = document.createElement('a');
        link.href = downloadUrl;
        link.rel = 'noopener';
        if (fileLabel) {
            link.setAttribute('download', String(fileLabel));
        }
        link.style.display = 'none';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    function isSpreadsheetFileType(fileType) {
        var normalizedType = (fileType || '').toLowerCase().replace(/^\./, '');
        return /^(xls|xlsx|csv|ods)$/.test(normalizedType);
    }

    /**
     * Expand the preview pane to full width (hide checklist list) for document viewing.
     */
    function enableFullDocumentPreviewLayout(container) {
        if (!container || !container.length) {
            return;
        }
        var $pane = container.closest('.subtab6-pane, .subtab2-pane, .subtab-pane, .not-used-layout, .tab-pane');
        if ($pane.length) {
            $pane.addClass('hide-list-view');
            $pane.find('.client-doc-preview-toggle-list-btn').addClass('is-active');
        }
    }

    function previewFile(fileType, fileUrl, containerId, fileLabel) {
        const container = resolvePreviewContainer(containerId);
        if (!container.length) {
            console.error('Preview container not found:', containerId);
            return;
        }

        setMatterDocumentPreviewActive(fileUrl, containerId, container[0]);

        if (!fileLabel) {
            const docId = extractDocumentIdFromPreviewUrl(fileUrl);
            if (docId) {
                const nameEl = document.querySelector('#id_' + docId + ' .doc-row span');
                if (nameEl) {
                    fileLabel = nameEl.textContent.trim();
                }
            }
        }

        const docId = extractDocumentIdFromPreviewUrl(fileUrl);
        if (docId) {
            const tabContainer = container.closest('.subtab6-pane, .subtab2-pane, .subtab-pane, .not-used-layout, .tab-pane');
            if (tabContainer.length) {
                tabContainer.find('.drow, .grid_list').removeClass('active-preview-doc');
                tabContainer.find('#id_' + docId + ', #gid_' + docId).addClass('active-preview-doc');
            } else {
                $('.drow, .grid_list').removeClass('active-preview-doc');
                $('#id_' + docId + ', #gid_' + docId).addClass('active-preview-doc');
            }
        }


        const embeddedPreviewUrl = fileUrl + (fileUrl.indexOf('?') >= 0 ? '&' : '?') + 'embed=1';
        const normalizedType = (fileType || '').toLowerCase().replace(/^\./, '');
        const isSpreadsheet = isSpreadsheetFileType(normalizedType);
        // Excel/Word/etc. use the shared office→PDF/HTML embed preview.
        const isOfficePreview = !!(normalizedType.match(/^(docx?|xlsx?|pptx?|rtf|odt|ods|odp|csv)$/) || isSpreadsheet);
        const inDocPane = isClientDocPreviewPane(container);
        const uploadedAt = resolveClientDocUploadedAt(fileUrl, docId);
        const previewHeaderHtml = buildPreviewHeaderHtml(fileType, fileUrl, fileLabel, {
            showToggleList: true,
            uploadedAt: uploadedAt
        });
        const mediaMaxHeight = inDocPane ? '100%' : 'calc(100vh - 300px)';

        // Spreadsheets open in full-width document view (list hidden; toolbar toggle restores it).
        if (isSpreadsheet) {
            enableFullDocumentPreviewLayout(container);
        }

        container.html(`
            <div class="preview-content preview-content-loading" style="flex: 1; display: flex; flex-direction: column; overflow: hidden; min-height: 0;">
                ${previewHeaderHtml}
                <div class="preview-loading-body" style="flex: 1; display: flex; align-items: center; justify-content: center; min-height: 200px;">
                    <div style="text-align: center;">
                        <i class="fa-solid fa-spinner fa-spin fa-2x" style="color: #4a90e2;"></i>
                        <p style="margin-top: 10px; color: #666;">${isOfficePreview ? 'Converting document for preview…' : 'Loading preview…'}</p>
                    </div>
                </div>
            </div>
        `);

        // Re-apply toggle active state after header is rendered.
        if (isSpreadsheet) {
            enableFullDocumentPreviewLayout(container);
        }

        if (typeof window.scheduleClientDocumentsPanelHeightAdjust === 'function') {
            window.scheduleClientDocumentsPanelHeightAdjust();
        } else if (typeof window.adjustMatterDocPreviewHeight === 'function') {
            window.adjustMatterDocPreviewHeight();
        } else if (typeof window.adjustPersonalDocPreviewHeight === 'function') {
            window.adjustPersonalDocPreviewHeight();
        } else if (typeof window.adjustClientDocumentsPanelHeight === 'function') {
            window.adjustClientDocumentsPanelHeight();
        }

        if (container[0] && typeof container[0].scrollIntoView === 'function' && !inDocPane) {
            container[0].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }

        const showPreviewError = function() {
            container.html(`
                <div class="preview-content" style="flex: 1; display: flex; flex-direction: column; overflow: hidden; min-height: 0;">
                    ${previewHeaderHtml}
                    <div class="preview-error-body" style="flex: 1; display: flex; align-items: center; justify-content: center; flex-direction: column;">
                        <i class="fa-solid fa-circle-exclamation fa-3x" style="color: #dc3545; margin-bottom: 15px;"></i>
                        <p style="margin-bottom: 15px;">Unable to load preview.</p>
                        <a href="${fileUrl}" target="_blank" rel="noopener" class="btn btn-primary">Open File</a>
                    </div>
                </div>
            `);
        };

        if (normalizedType.match(/(jpg|jpeg|png|gif|webp|bmp|tif|tiff)$/)) {
            const img = new Image();
            img.onload = function() {
                container.html(`
                    <div class="preview-content" style="flex: 1; display: flex; flex-direction: column; overflow: hidden; min-height: 0;">
                        ${previewHeaderHtml}
                        <div class="preview-media-body" style="flex: 1; overflow: auto; text-align: center; min-height: 0; padding: 8px;">
                            <img src="${embeddedPreviewUrl}" alt="Document Preview" style="max-width: 100%; max-height: ${mediaMaxHeight}; margin: auto; display: block;" />
                        </div>
                    </div>
                `);
                if (typeof window.scheduleClientDocumentsPanelHeightAdjust === 'function') {
                    window.scheduleClientDocumentsPanelHeightAdjust();
                } else if (typeof window.adjustMatterDocPreviewHeight === 'function') {
                    window.adjustMatterDocPreviewHeight();
                } else if (typeof window.adjustPersonalDocPreviewHeight === 'function') {
                    window.adjustPersonalDocPreviewHeight();
                }
            };
            img.onerror = showPreviewError;
            img.src = embeddedPreviewUrl;
        } else if (normalizedType === 'pdf' || isOfficePreview) {
            mountIframePreview(container, {
                embeddedPreviewUrl: embeddedPreviewUrl,
                headerHtml: previewHeaderHtml,
                toolbarHtml: '',
                loadingMessage: isOfficePreview ? 'Preparing document preview…' : 'Loading PDF preview…',
                slowMessage: isOfficePreview
                    ? 'Converting document for preview… please wait.'
                    : 'Still loading PDF… please wait.',
                frameHeight: getPreviewFrameHeight(container, isOfficePreview),
                onError: showPreviewError
            });
            if (isSpreadsheet) {
                enableFullDocumentPreviewLayout(container);
            }
        } else if (normalizedType === 'eml') {
            mountIframePreview(container, {
                embeddedPreviewUrl: embeddedPreviewUrl,
                headerHtml: previewHeaderHtml,
                loadingMessage: 'Loading email preview…',
                frameHeight: getPreviewFrameHeight(container, false),
                onError: showPreviewError
            });
        } else if (normalizedType === 'txt') {
            fetch(embeddedPreviewUrl, { credentials: 'same-origin', headers: { 'Accept': 'text/plain, */*' } })
                .then(function(response) {
                    if (!response.ok) {
                        throw new Error('HTTP ' + response.status);
                    }
                    return response.text();
                })
                .then(function(text) {
                    const escaped = $('<div/>').text(text).html();
                    container.html(`
                        <div class="preview-content" style="flex: 1; display: flex; flex-direction: column; overflow: hidden; min-height: 0;">
                            ${previewHeaderHtml}
                            <div class="preview-text-body" style="flex: 1; overflow: auto; width: 100%; padding: 12px; background: #fff; min-height: 0;">
                                <pre style="white-space: pre-wrap; word-wrap: break-word; font-size: 13px; margin: 0;">${escaped}</pre>
                            </div>
                        </div>
                    `);
                })
                .catch(showPreviewError);
        } else if (normalizedType.match(/^(mp4|webm|mov|m4v|avi|mkv|ogv|vob)$/)) {
            const videoMimeType = previewVideoMimeType(normalizedType);
            container.html(`
                <div class="preview-content" style="flex: 1; display: flex; flex-direction: column; overflow: hidden; min-height: 0;">
                    ${previewHeaderHtml}
                    <div class="preview-media-body" style="flex: 1; display: flex; align-items: center; justify-content: center; background: #000; min-height: 0;">
                        <video controls playsinline preload="metadata" style="max-width: 100%; max-height: ${mediaMaxHeight}; width: 100%;">
                            <source src="${embeddedPreviewUrl}" type="${videoMimeType}">
                            Your browser does not support video playback.
                        </video>
                    </div>
                </div>
            `);
            const videoEl = container.find('video')[0];
            if (videoEl) {
                videoEl.addEventListener('error', showPreviewError);
            }
        } else if (normalizedType === 'mp3' || normalizedType === 'm4a' || normalizedType === 'wav' || normalizedType === 'ogg' || normalizedType === 'aac') {
            const audioMimeMap = {
                mp3: 'audio/mpeg',
                m4a: 'audio/mp4',
                wav: 'audio/wav',
                ogg: 'audio/ogg',
                aac: 'audio/aac'
            };
            const audioMimeType = audioMimeMap[normalizedType] || 'audio/mpeg';
            container.html(`
                <div class="preview-content" style="flex: 1; display: flex; flex-direction: column; overflow: hidden; min-height: 0;">
                    ${previewHeaderHtml}
                    <div class="preview-media-body" style="flex: 1; display: flex; align-items: center; justify-content: center; background: #f8fafc; min-height: 0; padding: 24px;">
                        <audio controls preload="metadata" style="width: 100%; max-width: 560px;">
                            <source src="${embeddedPreviewUrl}" type="${audioMimeType}">
                            Your browser does not support audio playback.
                        </audio>
                    </div>
                </div>
            `);
            const audioEl = container.find('audio')[0];
            if (audioEl) {
                audioEl.addEventListener('error', showPreviewError);
            }
        } else {
            container.html(`
                <div class="preview-content" style="flex: 1; display: flex; flex-direction: column; overflow: hidden; min-height: 0;">
                    ${previewHeaderHtml}
                    <div class="preview-error-body" style="flex: 1; display: flex; align-items: center; justify-content: center; flex-direction: column;">
                        <i class="fa-solid fa-file fa-3x" style="color: #6c757d; margin-bottom: 15px;"></i>
                        <p style="margin-bottom: 15px;">Preview not available for this file type.</p>
                        <a href="${fileUrl}" target="_blank" rel="noopener" class="btn btn-primary">Open File</a>
                    </div>
                </div>
            `);
        }
    }

    window.previewFile = previewFile;
    window.resolvePreviewContainer = resolvePreviewContainer;
    window.documentFileIconClass = documentFileIconClass;
    window.downloadDocumentFile = downloadDocumentFile;
    window.isSpreadsheetFileType = isSpreadsheetFileType;
    window.enableFullDocumentPreviewLayout = enableFullDocumentPreviewLayout;

    $(function () {
        $('.preview-pane.file-preview-container').not('.client-doc-preview-pane').css({
            display: 'flex',
            'flex-direction': 'column',
            'margin-top': '15px',
            width: '499px',
            'min-height': '500px',
            height: 'calc(100vh - 200px)',
            border: '1px solid #dee2e6',
            'border-radius': '4px',
            padding: '15px',
            background: '#fff',
            position: 'sticky',
            top: '20px'
        });

        $(window).on('resize.documentPreview', function () {
            if (typeof window.adjustPreviewContainers === 'function') {
                window.adjustPreviewContainers();
            }
        }).trigger('resize.documentPreview');
    });

})(jQuery);
