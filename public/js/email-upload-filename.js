/**
 * WAF-safe Outlook email upload helpers (mirrors EmailUploadController::sanitizeFilename).
 * Sanitize multipart Content-Disposition names before POST so production WAF/mod_security
 * does not block apostrophes and other special characters.
 */
(function (global) {
    'use strict';

    var DEFAULT_EMAIL_EXTENSIONS = ['msg', 'eml'];

    function getAllowedEmailUploadExtensions() {
        var fromWindow = global.__CRM_EMAIL_ALLOWED_EXTENSIONS__;
        if (Array.isArray(fromWindow) && fromWindow.length) {
            return fromWindow.map(function (ext) {
                return String(ext).toLowerCase().replace(/^\./, '');
            });
        }
        return DEFAULT_EMAIL_EXTENSIONS.slice();
    }

    function emailUploadAcceptAttribute() {
        return getAllowedEmailUploadExtensions().map(function (ext) {
            return '.' + ext;
        }).join(',');
    }

    function emailUploadExtensionsLabel() {
        return getAllowedEmailUploadExtensions().map(function (ext) {
            return '.' + ext;
        }).join(', ');
    }

    function hasAllowedEmailUploadExtension(filename) {
        if (!filename || typeof filename !== 'string') {
            return false;
        }
        var lower = filename.toLowerCase();
        var allowed = getAllowedEmailUploadExtensions();
        for (var i = 0; i < allowed.length; i++) {
            if (lower.endsWith('.' + allowed[i])) {
                return true;
            }
        }
        return false;
    }

    function hasAllowedEmailUploadMime(file) {
        if (!file) {
            return false;
        }
        var type = String(file.type || '').toLowerCase();
        return type === 'message/rfc822'
            || type === 'application/vnd.ms-outlook'
            || type === 'application/octet-stream';
    }

    function isAllowedEmailUploadFilename(filename) {
        return hasAllowedEmailUploadExtension(filename);
    }

    function isPotentialEmailUploadFile(file) {
        if (!file) {
            return false;
        }
        return hasAllowedEmailUploadExtension(file.name) || hasAllowedEmailUploadMime(file);
    }

    function detectEmailUploadExtensionFromBytes(buffer) {
        if (!buffer || !buffer.byteLength) {
            return '';
        }

        var bytes = new Uint8Array(buffer);
        if (bytes.length >= 4
            && bytes[0] === 0xD0 && bytes[1] === 0xCF && bytes[2] === 0x11 && bytes[3] === 0xE0) {
            return 'msg';
        }

        var headLength = Math.min(bytes.length, 4096);
        var head = '';
        for (var i = 0; i < headLength; i++) {
            head += String.fromCharCode(bytes[i]);
        }

        if (/^(From:|Return-Path:|Received:|MIME-Version:|Date:|X-)/im.test(head)) {
            return 'eml';
        }

        return '';
    }

    function getEmailUploadExtension(filename) {
        if (!filename || typeof filename !== 'string') {
            return '';
        }
        var lower = filename.toLowerCase();
        var allowed = getAllowedEmailUploadExtensions();
        for (var i = 0; i < allowed.length; i++) {
            if (lower.endsWith('.' + allowed[i])) {
                return allowed[i];
            }
        }
        return '';
    }

    function buildEmailUploadFilename(baseName, extension) {
        var stem = String(baseName || '').trim();
        if (!stem) {
            stem = 'email_' + Date.now();
        }
        if (extension) {
            return stem + '.' + extension;
        }
        return stem;
    }

    function ensureEmailUploadFileExtension(file) {
        return new Promise(function (resolve) {
            if (!file) {
                resolve(null);
                return;
            }

            var currentExtension = getEmailUploadExtension(file.name);
            if (currentExtension) {
                resolve(file);
                return;
            }

            if (!(file.slice && typeof file.arrayBuffer === 'function')) {
                resolve(file);
                return;
            }

            file.slice(0, 4096).arrayBuffer().then(function (buffer) {
                var detected = detectEmailUploadExtensionFromBytes(buffer);
                if (!detected) {
                    resolve(file);
                    return;
                }

                var lastDot = file.name.lastIndexOf('.');
                var stem = lastDot > 0 ? file.name.slice(0, lastDot) : (file.name || ('email_' + Date.now()));
                stem = stem.replace(/[\\/:*?"<>|]+/g, '_').trim() || ('email_' + Date.now());
                var finalName = buildEmailUploadFilename(stem, detected);

                resolve(new File([file], finalName, {
                    type: file.type || (detected === 'eml' ? 'message/rfc822' : 'application/vnd.ms-outlook'),
                    lastModified: file.lastModified || Date.now()
                }));
            }).catch(function () {
                resolve(file);
            });
        });
    }

    function filterAllowedEmailUploadFiles(files) {
        return Array.from(files || []).filter(function (file) {
            return isPotentialEmailUploadFile(file);
        });
    }

    /**
     * Resolve dropped files via DataTransferItem / FileSystem API when available.
     * Outlook and some browsers expose 0-byte File objects on dataTransfer.files alone.
     */
    function getFilesFromDataTransfer(dataTransfer) {
        return new Promise(function (resolve) {
            if (!dataTransfer) {
                resolve([]);
                return;
            }

            var collected = [];
            var pending = [];
            var items = dataTransfer.items;

            if (items && items.length) {
                for (var i = 0; i < items.length; i++) {
                    (function (item) {
                        if (item.kind !== 'file') {
                            return;
                        }

                        var entry = item.webkitGetAsEntry ? item.webkitGetAsEntry() : null;
                        if (entry && entry.isFile) {
                            pending.push(new Promise(function (res) {
                                entry.file(function (file) {
                                    res(file || null);
                                }, function () {
                                    res(item.getAsFile());
                                });
                            }));
                            return;
                        }

                        var directFile = item.getAsFile();
                        if (directFile) {
                            collected.push(directFile);
                        }
                    })(items[i]);
                }
            }

            if (!pending.length && !collected.length && dataTransfer.files && dataTransfer.files.length) {
                resolve(Array.from(dataTransfer.files));
                return;
            }

            if (!pending.length) {
                resolve(collected);
                return;
            }

            Promise.all(pending).then(function (entryFiles) {
                entryFiles.forEach(function (file) {
                    if (file) {
                        collected.push(file);
                    }
                });

                if (!collected.length && dataTransfer.files && dataTransfer.files.length) {
                    collected = Array.from(dataTransfer.files);
                }

                resolve(collected);
            }).catch(function () {
                if (dataTransfer.files && dataTransfer.files.length) {
                    resolve(Array.from(dataTransfer.files));
                } else {
                    resolve(collected);
                }
            });
        });
    }

    function ensureEmailUploadFileContent(file) {
        return new Promise(function (resolve) {
            if (!file) {
                resolve(null);
                return;
            }

            if (file.size > 0) {
                resolve(file);
                return;
            }

            if (typeof file.arrayBuffer !== 'function') {
                resolve(file);
                return;
            }

            file.arrayBuffer().then(function (buffer) {
                if (!buffer || !buffer.byteLength) {
                    resolve(file);
                    return;
                }

                resolve(new File([buffer], file.name, {
                    type: file.type || 'application/octet-stream',
                    lastModified: file.lastModified || Date.now()
                }));
            }).catch(function () {
                resolve(file);
            });
        });
    }

    function normalizeEmailUploadFiles(files) {
        return Promise.all(Array.from(files || []).map(function (file) {
            return ensureEmailUploadFileContent(file).then(function (resolved) {
                return ensureEmailUploadFileExtension(resolved);
            });
        })).then(function (normalized) {
            return normalized.filter(function (file) {
                return file && hasAllowedEmailUploadExtension(file.name);
            });
        });
    }

    function isEmptyEmailUploadFile(file) {
        return !!(file && file.size === 0);
    }

    function classifyEmailUploadDropFile(file) {
        if (!file) {
            return 'invalid';
        }

        if (file.size > 0) {
            if (hasAllowedEmailUploadExtension(file.name)) {
                return 'valid';
            }
            if (isPotentialEmailUploadFile(file)) {
                return 'needs_extension';
            }
            return 'invalid_type';
        }

        if (hasAllowedEmailUploadExtension(file.name) || hasAllowedEmailUploadMime(file)) {
            return 'empty_email';
        }

        return 'empty_unknown';
    }

    function emailUploadOutlookDragInstructions() {
        return 'Browsers cannot read emails dragged directly from the Outlook desktop app.\n\n'
            + 'To upload an email:\n'
            + '1. In Outlook, open the email\n'
            + '2. Go to File → Save As (or drag the email to your Desktop)\n'
            + '3. Save it as a .msg or .eml file\n'
            + '4. Drag the saved file here, or click Browse to select it';
    }

    function emailUploadEmptyFileMessage() {
        return 'The email file is empty (0 bytes) and cannot be uploaded.\n\n'
            + emailUploadOutlookDragInstructions();
    }

    function getEmailUploadDropFailureMessage(rawFiles, normalizedFiles) {
        var raw = Array.from(rawFiles || []);
        var normalized = Array.from(normalizedFiles || []);

        if (!raw.length) {
            return {
                title: 'No files detected',
                message: 'Nothing was received from the drop. '
                    + 'Save the email from Outlook as a .msg or .eml file, then try again.'
            };
        }

        var classifications = raw.map(classifyEmailUploadDropFile);
        var hasEmptyEmail = classifications.some(function (type) {
            return type === 'empty_email' || type === 'empty_unknown';
        });
        var hasInvalidType = classifications.some(function (type) {
            return type === 'invalid_type';
        });
        var hasValidRaw = classifications.some(function (type) {
            return type === 'valid' || type === 'needs_extension';
        });

        if (hasEmptyEmail || (!normalized.length && raw.some(isEmptyEmailUploadFile))) {
            return {
                title: 'Cannot upload directly from Outlook',
                message: emailUploadOutlookDragInstructions()
            };
        }

        if (!normalized.length && hasInvalidType) {
            return {
                title: 'Invalid file type',
                message: 'Please upload Outlook email files only (' + emailUploadExtensionsLabel() + ').'
            };
        }

        if (!normalized.length && hasValidRaw) {
            return {
                title: 'Could not read dropped file',
                message: 'The dropped file could not be prepared for upload. '
                    + 'Save the email from Outlook as a .msg or .eml file, then try again.'
            };
        }

        if (!normalized.length) {
            return {
                title: 'Upload not supported',
                message: emailUploadOutlookDragInstructions()
            };
        }

        return null;
    }

    function resolveEmailUploadDrop(dataTransfer) {
        return getFilesFromDataTransfer(dataTransfer).then(function (rawFiles) {
            return normalizeEmailUploadFiles(rawFiles).then(function (normalizedFiles) {
                return {
                    rawFiles: rawFiles,
                    files: normalizedFiles
                };
            });
        });
    }

    function resolveEmailUploadDropFiles(dataTransfer) {
        return resolveEmailUploadDrop(dataTransfer).then(function (result) {
            return result.files;
        });
    }

    function sanitizeEmailUploadFilename(filename, preferredExtension) {
        if (!filename || typeof filename !== 'string') {
            var fallbackExt = preferredExtension || 'eml';
            return 'email_' + Date.now() + '.' + fallbackExt;
        }

        var lastDot = filename.lastIndexOf('.');
        var extension = lastDot >= 0 ? filename.slice(lastDot + 1).toLowerCase().replace(/[^a-z0-9]/g, '') : '';
        if (!extension && preferredExtension) {
            extension = String(preferredExtension).toLowerCase().replace(/[^a-z0-9]/g, '');
        }
        var nameWithoutExt = lastDot >= 0 ? filename.slice(0, lastDot) : filename;

        var sanitizedName = nameWithoutExt.replace(/[^a-zA-Z0-9_-]/g, '_');
        sanitizedName = sanitizedName.replace(/_+/g, '_').replace(/^_+|_+$/g, '');

        if (!sanitizedName) {
            sanitizedName = 'email_' + Date.now();
        }

        var sanitizedFilename = extension ? sanitizedName + '.' + extension : sanitizedName;

        if (sanitizedFilename.length > 255) {
            var maxNameLength = 255 - extension.length - (extension ? 1 : 0);
            if (maxNameLength > 0) {
                sanitizedName = sanitizedName.slice(0, maxNameLength);
                sanitizedFilename = extension ? sanitizedName + '.' + extension : sanitizedName;
            } else {
                sanitizedFilename = 'email_' + Date.now() + (extension ? '.' + extension : '');
            }
        }

        return sanitizedFilename;
    }

    /**
     * Rebuild FormData for email upload forms with WAF-safe filenames.
     * Preserves all non-file fields from the form.
     */
    function buildEmailUploadFormData(form) {
        var formData = new FormData(form);
        var fileInput = form.querySelector('input[name="email_files[]"]');

        if (!fileInput || !fileInput.files || !fileInput.files.length) {
            return formData;
        }

        var rebuilt = new FormData();
        formData.forEach(function (value, key) {
            if (key !== 'email_files[]') {
                rebuilt.append(key, value);
            }
        });

        Array.from(fileInput.files).forEach(function (file) {
            rebuilt.append('email_files[]', file, sanitizeEmailUploadFilename(file.name));
        });

        return rebuilt;
    }

    /**
     * User-facing message for email upload HTTP 403 (Laravel JSON vs WAF HTML).
     */
    function emailUpload403Message(xhrOrText, status) {
        var responseText = '';
        var parsed = null;

        if (xhrOrText && typeof xhrOrText === 'object' && xhrOrText.responseText !== undefined) {
            status = xhrOrText.status;
            responseText = xhrOrText.responseText || '';
            parsed = xhrOrText.responseJSON || null;
        } else {
            responseText = xhrOrText || '';
        }

        if (status !== 403) {
            return null;
        }

        var isHtml = /<html[\s>]/i.test(responseText) || /<!DOCTYPE/i.test(responseText);

        if (isHtml || (responseText.indexOf('Forbidden') !== -1 && !(parsed && parsed.message))) {
            return 'The server blocked this upload (security filter). Rename files to remove special characters such as apostrophes and try again.';
        }

        if (parsed && parsed.message) {
            return parsed.message;
        }

        return 'Access denied. You may not have permission to upload emails for this client.';
    }

    function resolveEmailUploadLogBaseUrl() {
        var container = document.getElementById('outlookContainer')
            || document.getElementById('outlook-email-container')
            || document.querySelector('.email-interface-container')
            || document.querySelector('[data-base-url]');

        if (container) {
            var configured = container.getAttribute('data-base-url');
            if (configured) {
                return configured.replace(/\/$/, '');
            }
        }

        return window.location.origin;
    }

    function logEmailUploadFailure(context) {
        var payload = context || {};
        var csrfMeta = document.querySelector('meta[name="csrf-token"]');
        var body = JSON.stringify(payload);

        try {
            fetch(resolveEmailUploadLogBaseUrl() + '/log-email-upload-error', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfMeta ? csrfMeta.content : ''
                },
                body: body,
                credentials: 'same-origin',
                keepalive: true
            }).catch(function () {});
        } catch (ignored) {}
    }

    var emailUploadResultModalReady = false;
    var emailUploadResultModalResolve = null;

    function defaultEmailUploadResultTitle(type) {
        if (type === 'success') return 'Upload Successful';
        if (type === 'error') return 'Upload Failed';
        if (type === 'warning') return 'Upload Warning';
        return 'Notice';
    }

    function ensureEmailUploadResultModal() {
        if (emailUploadResultModalReady) return;
        emailUploadResultModalReady = true;

        if (!document.getElementById('emailUploadResultModalStyles')) {
            var style = document.createElement('style');
            style.id = 'emailUploadResultModalStyles';
            style.textContent = [
                '.email-upload-result-modal-overlay{display:none;position:fixed;inset:0;background:rgba(50,49,48,.5);z-index:10060;align-items:center;justify-content:center;padding:20px;}',
                '.email-upload-result-modal-overlay.active{display:flex;}',
                '.email-upload-result-modal{width:100%;max-width:520px;max-height:min(85vh,720px);background:#fff;border-radius:12px;box-shadow:0 16px 40px rgba(0,0,0,.2);padding:28px 24px 22px;text-align:center;display:flex;flex-direction:column;animation:emailUploadResultModalIn .2s ease-out;}',
                '@keyframes emailUploadResultModalIn{from{opacity:0;transform:translateY(-12px) scale(.98)}to{opacity:1;transform:translateY(0) scale(1)}}',
                '.email-upload-result-modal__icon{width:56px;height:56px;margin:0 auto 16px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:26px;}',
                '.email-upload-result-modal__icon--success{background:#e7f6ec;color:#107c10;}',
                '.email-upload-result-modal__icon--error{background:#fde7e9;color:#d13438;}',
                '.email-upload-result-modal__icon--warning{background:#fff4e5;color:#d97706;}',
                '.email-upload-result-modal__icon--info{background:#eff6fc;color:#0078d4;}',
                '.email-upload-result-modal__title{margin:0 0 12px;font-size:20px;font-weight:600;color:#323130;}',
                '.email-upload-result-modal__message{margin:0 0 22px;font-size:14px;line-height:1.55;color:#605e5c;text-align:left;white-space:pre-wrap;word-break:break-word;overflow-y:auto;max-height:min(50vh,360px);padding:14px 16px;background:#faf9f8;border:1px solid #edebe9;border-radius:8px;}',
                '.email-upload-result-modal__details{margin:-12px 0 18px;text-align:left;}',
                '.email-upload-result-modal__details-toggle{background:none;border:none;padding:0;font-size:13px;font-weight:600;color:#0078d4;cursor:pointer;text-decoration:underline;}',
                '.email-upload-result-modal__details-toggle:hover{color:#106ebe;}',
                '.email-upload-result-modal__details-body{margin:8px 0 0;font-size:12px;line-height:1.5;color:#605e5c;white-space:pre-wrap;word-break:break-word;overflow-y:auto;max-height:180px;padding:10px 12px;background:#f3f2f1;border:1px solid #edebe9;border-radius:6px;text-align:left;}',
                '.email-upload-result-modal__actions{display:flex;justify-content:center;}',
                '.email-upload-result-modal__btn{min-width:120px;padding:10px 22px;border-radius:6px;font-size:14px;font-weight:600;cursor:pointer;border:1px solid transparent;background:#0078d4;color:#fff;}',
                '.email-upload-result-modal__btn:hover{background:#106ebe;}'
            ].join('');
            document.head.appendChild(style);
        }

        if (document.getElementById('emailUploadResultModal')) return;

        var overlay = document.createElement('div');
        overlay.id = 'emailUploadResultModal';
        overlay.className = 'email-upload-result-modal-overlay';
        overlay.setAttribute('aria-hidden', 'true');
        overlay.innerHTML = ''
            + '<div class="email-upload-result-modal" role="dialog" aria-modal="true" aria-labelledby="emailUploadResultModalTitle">'
            + '  <div class="email-upload-result-modal__icon" id="emailUploadResultModalIcon" aria-hidden="true"></div>'
            + '  <h3 class="email-upload-result-modal__title" id="emailUploadResultModalTitle"></h3>'
            + '  <div class="email-upload-result-modal__message" id="emailUploadResultModalMessage"></div>'
            + '  <div class="email-upload-result-modal__details" id="emailUploadResultModalDetails" hidden>'
            + '    <button type="button" class="email-upload-result-modal__details-toggle" id="emailUploadResultModalDetailsToggle" aria-expanded="false">Show technical details</button>'
            + '    <pre class="email-upload-result-modal__details-body" id="emailUploadResultModalDetailsBody" hidden></pre>'
            + '  </div>'
            + '  <div class="email-upload-result-modal__actions">'
            + '    <button type="button" class="email-upload-result-modal__btn" id="emailUploadResultModalOk">OK</button>'
            + '  </div>'
            + '</div>';
        document.body.appendChild(overlay);

        var detailsToggle = document.getElementById('emailUploadResultModalDetailsToggle');
        if (detailsToggle) {
            detailsToggle.addEventListener('click', function () {
                var body = document.getElementById('emailUploadResultModalDetailsBody');
                if (!body) return;
                var isHidden = body.hidden;
                body.hidden = !isHidden;
                detailsToggle.textContent = isHidden ? 'Hide technical details' : 'Show technical details';
                detailsToggle.setAttribute('aria-expanded', isHidden ? 'true' : 'false');
            });
        }

        var okBtn = document.getElementById('emailUploadResultModalOk');
        var dialog = overlay.querySelector('.email-upload-result-modal');

        function closeEmailUploadResultModal() {
            overlay.classList.remove('active');
            overlay.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('email-upload-result-modal-open');
            if (typeof emailUploadResultModalResolve === 'function') {
                var resolve = emailUploadResultModalResolve;
                emailUploadResultModalResolve = null;
                resolve();
            }
        }

        if (okBtn) okBtn.addEventListener('click', closeEmailUploadResultModal);
        overlay.addEventListener('click', function (event) {
            if (event.target === overlay) closeEmailUploadResultModal();
        });
        if (dialog) {
            dialog.addEventListener('click', function (event) { event.stopPropagation(); });
        }
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && overlay.classList.contains('active')) {
                closeEmailUploadResultModal();
            }
        });
    }

    function showEmailUploadResultModal(options) {
        options = options || {};
        ensureEmailUploadResultModal();

        var overlay = document.getElementById('emailUploadResultModal');
        var iconEl = document.getElementById('emailUploadResultModalIcon');
        var titleEl = document.getElementById('emailUploadResultModalTitle');
        var messageEl = document.getElementById('emailUploadResultModalMessage');
        var type = options.type || 'info';
        var title = options.title || defaultEmailUploadResultTitle(type);
        var message = options.message || '';
        var details = options.details || '';

        if (!overlay || !iconEl || !titleEl || !messageEl) {
            window.alert(title + '\n\n' + message + (details ? '\n\n' + details : ''));
            return Promise.resolve();
        }

        var iconMap = {
            success: { className: 'email-upload-result-modal__icon--success', icon: 'fa-circle-check' },
            error: { className: 'email-upload-result-modal__icon--error', icon: 'fa-circle-exclamation' },
            warning: { className: 'email-upload-result-modal__icon--warning', icon: 'fa-triangle-exclamation' },
            info: { className: 'email-upload-result-modal__icon--info', icon: 'fa-circle-info' }
        };
        var iconConfig = iconMap[type] || iconMap.info;

        iconEl.className = 'email-upload-result-modal__icon ' + iconConfig.className;
        iconEl.innerHTML = '<i class="fa-solid ' + iconConfig.icon + '"></i>';
        titleEl.textContent = title;
        messageEl.textContent = message;

        var detailsContainer = document.getElementById('emailUploadResultModalDetails');
        var detailsBody = document.getElementById('emailUploadResultModalDetailsBody');
        var detailsToggle = document.getElementById('emailUploadResultModalDetailsToggle');
        if (detailsContainer && detailsBody && detailsToggle) {
            if (details) {
                detailsBody.textContent = details;
                detailsBody.hidden = true;
                detailsToggle.textContent = 'Show technical details';
                detailsToggle.setAttribute('aria-expanded', 'false');
                detailsContainer.hidden = false;
            } else {
                detailsContainer.hidden = true;
                detailsBody.textContent = '';
            }
        }

        overlay.classList.add('active');
        overlay.setAttribute('aria-hidden', 'false');
        document.body.classList.add('email-upload-result-modal-open');

        var okBtn = document.getElementById('emailUploadResultModalOk');
        if (okBtn) okBtn.focus();

        return new Promise(function (resolve) {
            emailUploadResultModalResolve = resolve;
        });
    }

    /**
     * Build the collapsible "technical details" text from an upload response:
     * per-file technical errors, error codes, and log reference IDs.
     */
    function buildEmailUploadTechnicalDetails(data) {
        if (!data || typeof data !== 'object') {
            return '';
        }

        var lines = [];
        var errors = Array.isArray(data.errors) ? data.errors : [];

        errors.forEach(function (err) {
            if (!err || typeof err !== 'object') {
                return;
            }
            var parts = [];
            if (err.error_code) {
                parts.push('[' + err.error_code + ']');
            }
            if (err.technical_error && err.technical_error !== err.error) {
                parts.push(err.technical_error);
            }
            if (err.reference) {
                parts.push('Ref: ' + err.reference);
            }
            if (parts.length) {
                lines.push((err.filename || 'Unknown file') + ' — ' + parts.join(' '));
            }
        });

        if (data.technical_error) {
            lines.push(data.technical_error);
        }
        if (data.reference) {
            lines.push('Ref: ' + data.reference);
        }

        return lines.join('\n');
    }

    /**
     * True when any per-file error indicates the Python processing service
     * itself is down/failing (used to gate "check the Python service" tips).
     */
    function emailUploadHasServiceError(errors) {
        var serviceCodes = ['service_unreachable', 'service_error', 'service_invalid_response', 'timeout'];
        return (Array.isArray(errors) ? errors : []).some(function (err) {
            return err && serviceCodes.indexOf(err.error_code) !== -1;
        });
    }

    /**
     * Format the warnings[] array from an upload response (attachment/PDF
     * soft-failures on otherwise successful uploads) for display.
     */
    function formatEmailUploadWarnings(warnings) {
        if (!Array.isArray(warnings) || !warnings.length) {
            return '';
        }
        return warnings.map(function (entry) {
            if (!entry || typeof entry !== 'object') {
                return '';
            }
            var fileWarnings = Array.isArray(entry.warnings) ? entry.warnings : [];
            var name = entry.original_filename || entry.filename || 'Unknown file';
            return name + ':\n' + fileWarnings.map(function (w) { return '  - ' + w; }).join('\n');
        }).filter(Boolean).join('\n\n');
    }

    global.crmGetAllowedEmailUploadExtensions = getAllowedEmailUploadExtensions;
    global.crmEmailUploadAcceptAttribute = emailUploadAcceptAttribute;
    global.crmEmailUploadExtensionsLabel = emailUploadExtensionsLabel;
    global.crmIsAllowedEmailUploadFilename = isAllowedEmailUploadFilename;
    global.crmIsPotentialEmailUploadFile = isPotentialEmailUploadFile;
    global.crmDetectEmailUploadExtensionFromBytes = detectEmailUploadExtensionFromBytes;
    global.crmEnsureEmailUploadFileExtension = ensureEmailUploadFileExtension;
    global.crmFilterAllowedEmailUploadFiles = filterAllowedEmailUploadFiles;
    global.crmGetFilesFromDataTransfer = getFilesFromDataTransfer;
    global.crmEnsureEmailUploadFileContent = ensureEmailUploadFileContent;
    global.crmNormalizeEmailUploadFiles = normalizeEmailUploadFiles;
    global.crmResolveEmailUploadDrop = resolveEmailUploadDrop;
    global.crmResolveEmailUploadDropFiles = resolveEmailUploadDropFiles;
    global.crmGetEmailUploadDropFailureMessage = getEmailUploadDropFailureMessage;
    global.crmEmailUploadOutlookDragInstructions = emailUploadOutlookDragInstructions;
    global.crmEmailUploadEmptyFileMessage = emailUploadEmptyFileMessage;
    global.crmSanitizeEmailUploadFilename = sanitizeEmailUploadFilename;
    global.crmBuildEmailUploadFormData = buildEmailUploadFormData;
    global.crmEmailUpload403Message = emailUpload403Message;
    global.crmLogEmailUploadFailure = logEmailUploadFailure;
    global.crmShowEmailUploadResultModal = showEmailUploadResultModal;
    global.crmBuildEmailUploadTechnicalDetails = buildEmailUploadTechnicalDetails;
    global.crmEmailUploadHasServiceError = emailUploadHasServiceError;
    global.crmFormatEmailUploadWarnings = formatEmailUploadWarnings;
})(typeof window !== 'undefined' ? window : this);
