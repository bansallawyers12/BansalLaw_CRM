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
     * Capture drag payload synchronously during the drop event.
     * dataTransfer is cleared once the drop handler finishes, so async reads fail.
     */
    function captureEmailUploadDropSnapshot(dataTransfer) {
        var snapshot = {
            files: [],
            entries: [],
            types: []
        };

        if (!dataTransfer) {
            return snapshot;
        }

        if (dataTransfer.types && dataTransfer.types.length) {
            snapshot.types = Array.prototype.slice.call(dataTransfer.types);
        }

        if (dataTransfer.files && dataTransfer.files.length) {
            for (var i = 0; i < dataTransfer.files.length; i++) {
                snapshot.files.push(dataTransfer.files[i]);
            }
        }

        if (dataTransfer.items && dataTransfer.items.length) {
            for (var j = 0; j < dataTransfer.items.length; j++) {
                var item = dataTransfer.items[j];
                if (!item || item.kind !== 'file') {
                    continue;
                }

                var directFile = item.getAsFile ? item.getAsFile() : null;
                var entry = item.webkitGetAsEntry ? item.webkitGetAsEntry() : null;

                snapshot.entries.push({
                    file: directFile,
                    entry: entry,
                    type: item.type || ''
                });

                if (directFile) {
                    var alreadyCaptured = snapshot.files.some(function (existing) {
                        return existing === directFile;
                    });
                    if (!alreadyCaptured) {
                        snapshot.files.push(directFile);
                    }
                }
            }
        }

        return snapshot;
    }

    function filesAreEquivalent(left, right) {
        if (!left || !right) {
            return false;
        }
        return left === right
            || (left.name === right.name
                && left.size === right.size
                && left.lastModified === right.lastModified
                && left.type === right.type);
    }

    function appendUniqueEmailUploadFile(target, file) {
        if (!file) {
            return;
        }
        var exists = target.some(function (existing) {
            return filesAreEquivalent(existing, file);
        });
        if (!exists) {
            target.push(file);
        }
    }

    function waitForEmailUploadFileContent(file, maxAttempts, intervalMs) {
        maxAttempts = maxAttempts || 12;
        intervalMs = intervalMs || 100;

        return new Promise(function (resolve) {
            function attemptRead(attempt) {
                if (!file) {
                    resolve(null);
                    return;
                }

                if (file.size > 0) {
                    resolve(file);
                    return;
                }

                if (attempt >= maxAttempts) {
                    resolve(file);
                    return;
                }

                if (typeof file.arrayBuffer !== 'function') {
                    setTimeout(function () {
                        attemptRead(attempt + 1);
                    }, intervalMs);
                    return;
                }

                file.arrayBuffer().then(function (buffer) {
                    if (buffer && buffer.byteLength > 0) {
                        resolve(new File([buffer], file.name, {
                            type: file.type || 'application/octet-stream',
                            lastModified: file.lastModified || Date.now()
                        }));
                        return;
                    }

                    setTimeout(function () {
                        attemptRead(attempt + 1);
                    }, intervalMs);
                }).catch(function () {
                    setTimeout(function () {
                        attemptRead(attempt + 1);
                    }, intervalMs);
                });
            }

            attemptRead(0);
        });
    }

    function hydrateEmailUploadDropSnapshot(snapshot) {
        snapshot = snapshot || { files: [], entries: [], types: [] };
        var collected = Array.isArray(snapshot.files) ? snapshot.files.slice() : [];
        var pending = [];

        (snapshot.entries || []).forEach(function (entry) {
            if (entry && entry.entry && entry.entry.isFile && typeof entry.entry.file === 'function') {
                pending.push(new Promise(function (resolve) {
                    entry.entry.file(function (file) {
                        resolve(file || entry.file || null);
                    }, function () {
                        resolve(entry.file || null);
                    });
                }));
                return;
            }

            appendUniqueEmailUploadFile(collected, entry ? entry.file : null);
        });

        var hydratePromise = pending.length
            ? Promise.all(pending).then(function (entryFiles) {
                entryFiles.forEach(function (file) {
                    appendUniqueEmailUploadFile(collected, file);
                });
                return collected;
            })
            : Promise.resolve(collected);

        return hydratePromise.then(function (files) {
            return Promise.all(files.map(function (file) {
                return waitForEmailUploadFileContent(file);
            })).then(function (hydrated) {
                return hydrated.filter(function (file) {
                    return !!file;
                });
            });
        });
    }

    function getOutlookDragBridgeUrl() {
        // Outlook drag data lives on the user's Windows workstation clipboard.
        // Always use the local Python service, even when the CRM is hosted remotely.
        var configured = global.__CRM_OUTLOOK_DRAG_BRIDGE_URL__;
        if (typeof configured === 'string' && configured.trim()) {
            var value = configured.trim().replace(/\/+$/, '');
            if (/^https?:\/\/(127\.0\.0\.1|localhost)(:\d+)?$/i.test(value)) {
                return value;
            }
        }
        return 'http://127.0.0.1:5002';
    }

    function base64ToEmailUploadFile(filename, contentBase64, mimeType) {
        var binary = atob(contentBase64 || '');
        var bytes = new Uint8Array(binary.length);
        for (var i = 0; i < binary.length; i++) {
            bytes[i] = binary.charCodeAt(i);
        }

        var safeName = filename || ('email_' + Date.now() + '.msg');
        var blobType = mimeType || 'application/octet-stream';
        return new File([bytes], safeName, {
            type: blobType,
            lastModified: Date.now()
        });
    }

    function snapshotHasCapturedFiles(snapshot) {
        return !!(snapshot && snapshot.files && snapshot.files.length);
    }

    function resolveOutlookDragViaBridge() {
        var bridgeUrl = getOutlookDragBridgeUrl();
        return fetch(bridgeUrl + '/outlook/drag/resolve', {
            method: 'POST',
            headers: {
                'Accept': 'application/json'
            }
        }).then(function (response) {
            return response.json().catch(function () {
                return {};
            }).then(function (payload) {
                if (!response.ok || !payload || !payload.success || !payload.files || !payload.files.length) {
                    var message = (payload && payload.message)
                        ? payload.message
                        : (response.status === 404
                            ? 'No Outlook email found. Open Outlook, select the email, then drag it again.'
                            : 'Could not read the email from Outlook. Make sure Outlook is open and the Python service is running.');
                    throw new Error(message);
                }

                return payload.files.map(function (item) {
                    return base64ToEmailUploadFile(
                        item.filename,
                        item.content_base64,
                        item.filename && /\.eml$/i.test(item.filename)
                            ? 'message/rfc822'
                            : 'application/vnd.ms-outlook'
                    );
                });
            });
        });
    }

    function needsOutlookDragBridge(rawFiles, normalizedFiles) {
        var raw = Array.from(rawFiles || []);
        var normalized = Array.from(normalizedFiles || []);

        if (!raw.length) {
            return true;
        }

        if (!normalized.length && raw.every(isEmptyEmailUploadFile)) {
            return true;
        }

        return false;
    }

    /**
     * Resolve dropped files via DataTransferItem / FileSystem API when available.
     * Outlook and some browsers expose 0-byte File objects on dataTransfer.files alone.
     */
    function getFilesFromDataTransfer(dataTransfer) {
        return hydrateEmailUploadDropSnapshot(captureEmailUploadDropSnapshot(dataTransfer));
    }

    function getFilesFromDropSnapshot(snapshot) {
        return hydrateEmailUploadDropSnapshot(snapshot);
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
        return 'Could not read the email from Outlook.\n\n'
            + '1. Keep Outlook open on this computer\n'
            + '2. Click the email in Outlook so it is selected\n'
            + '3. Drag that selected email into this upload area again\n'
            + '4. Make sure the Python service is running locally (port 5002)';
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
                title: 'Could not read Outlook email',
                message: emailUploadOutlookDragInstructions()
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

    function resolveEmailUploadDrop(dataTransferOrSnapshot) {
        var snapshot = (dataTransferOrSnapshot && dataTransferOrSnapshot.files && dataTransferOrSnapshot.entries)
            ? dataTransferOrSnapshot
            : captureEmailUploadDropSnapshot(dataTransferOrSnapshot);

        function finalizeUploadFiles(combinedRaw, bridgeUsed) {
            return normalizeEmailUploadFiles(combinedRaw).then(function (normalizedFiles) {
                return {
                    rawFiles: combinedRaw,
                    files: normalizedFiles,
                    bridgeUsed: !!bridgeUsed
                };
            });
        }

        if (!snapshotHasCapturedFiles(snapshot)) {
            return resolveOutlookDragViaBridge().then(function (bridgeFiles) {
                return finalizeUploadFiles(bridgeFiles, true);
            });
        }

        return getFilesFromDropSnapshot(snapshot).then(function (rawFromDrop) {
            return resolveOutlookDragViaBridge().then(function (bridgeFiles) {
                var combinedRaw = rawFromDrop.slice();
                bridgeFiles.forEach(function (file) {
                    appendUniqueEmailUploadFile(combinedRaw, file);
                });
                return finalizeUploadFiles(combinedRaw, bridgeFiles.length > 0);
            }).catch(function () {
                return finalizeUploadFiles(rawFromDrop, false);
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

    global.crmGetAllowedEmailUploadExtensions = getAllowedEmailUploadExtensions;
    global.crmEmailUploadAcceptAttribute = emailUploadAcceptAttribute;
    global.crmEmailUploadExtensionsLabel = emailUploadExtensionsLabel;
    global.crmIsAllowedEmailUploadFilename = isAllowedEmailUploadFilename;
    global.crmIsPotentialEmailUploadFile = isPotentialEmailUploadFile;
    global.crmDetectEmailUploadExtensionFromBytes = detectEmailUploadExtensionFromBytes;
    global.crmEnsureEmailUploadFileExtension = ensureEmailUploadFileExtension;
    global.crmFilterAllowedEmailUploadFiles = filterAllowedEmailUploadFiles;
    global.crmCaptureEmailUploadDropSnapshot = captureEmailUploadDropSnapshot;
    global.crmHydrateEmailUploadDropSnapshot = hydrateEmailUploadDropSnapshot;
    global.crmGetFilesFromDropSnapshot = getFilesFromDropSnapshot;
    global.crmResolveOutlookDragViaBridge = resolveOutlookDragViaBridge;
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
})(typeof window !== 'undefined' ? window : this);
