/**
 * WAF-safe .msg upload filename helpers (mirrors EmailUploadController::sanitizeFilename).
 * Sanitize multipart Content-Disposition names before POST so production WAF/mod_security
 * does not block apostrophes and other special characters.
 */
(function (global) {
    'use strict';

    function sanitizeEmailUploadFilename(filename) {
        if (!filename || typeof filename !== 'string') {
            return 'email_' + Date.now() + '.msg';
        }

        var lastDot = filename.lastIndexOf('.');
        var extension = lastDot >= 0 ? filename.slice(lastDot + 1) : '';
        var nameWithoutExt = lastDot >= 0 ? filename.slice(0, lastDot) : filename;

        var sanitizedName = nameWithoutExt.replace(/[^a-zA-Z0-9\-_.]/g, '_');
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

    global.crmSanitizeEmailUploadFilename = sanitizeEmailUploadFilename;
    global.crmBuildEmailUploadFormData = buildEmailUploadFormData;
    global.crmEmailUpload403Message = emailUpload403Message;
})(typeof window !== 'undefined' ? window : this);
