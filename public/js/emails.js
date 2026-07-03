/**
 * Emails Module for CRM Client Email Tab
 * Handles upload, search, and display of .msg email files
 * Adapted from email-viewer app to work with migration manager backend
 */

(function() {
    'use strict';

    // =========================================================================
    // Module State
    // =========================================================================
    let currentPage = 1;
    let lastPage = 1;
    let isLoading = false;
    let isUploading = false;
    let currentMailType = 'inbox'; // 'inbox' or 'sent' - determines endpoint
    let currentLabelId = ''; // EmailLabel.id for filtering
    let currentSearch = '';
    let currentSort = 'date';
    let availableLabels = []; // Loaded from API
    let currentSenderFilter = '';
    let availableSenders = []; // Loaded from API

    // Expose function to set mail type (for external use)
    window.setEmailMailType = function(type) {
        currentMailType = type;
        currentPage = 1;
        const mailTypeFilter = document.getElementById('mailTypeFilter');
        if (mailTypeFilter) {
            mailTypeFilter.value = type;
        }
    };

    // =========================================================================
    // Utility Functions
    // =========================================================================

    /**
     * Resolves a path to a same-origin URL when the app is not at the site root
     * (e.g. WAMP: /BansalLaw_CRM/public/...). Set window.__CRM_BASE__ from Blade:
     * rtrim(url('/'), '/')
     */
    function crmUrl(path) {
        if (!path) {
            return path;
        }
        if (/^https?:\/\//i.test(path)) {
            return path;
        }
        var p = path.charAt(0) === '/' ? path : '/' + path;
        var base = (typeof window.__CRM_BASE__ === 'string' && window.__CRM_BASE__.length)
            ? window.__CRM_BASE__.replace(/\/$/, '') : '';
        return base ? (base + p) : p;
    }

    /**
     * Get client ID from the DOM (kept for backward compatibility)
     */
    function getClientId() {
        const container = document.querySelector('.email-interface-container');
        if (!container) {
            // Page doesn't have email interface - this is normal for pages that don't support emails
            return null;
        }
        
        // Check if the container has the required attribute
        const clientId = container.dataset.clientId;
        if (!clientId || clientId === '') {
            // Container exists but client ID is not set - page may not be configured for emails
            // This is not an error, just return null silently
            return null;
        }
        
        return clientId;
    }

    /**
     * Get matter ID from the DOM
     */
    function getMatterId() {
        const container = document.querySelector('.email-interface-container');
        if (!container) {
            // Page doesn't have email interface - this is normal for pages that don't support emails
            return null;
        }
        
        // Check if the container has the required attribute
        const matterId = container.dataset.matterId;
        if (!matterId || matterId === '') {
            return null;
        }
        return matterId;
    }

    /**
     * Check if we're in lead context (lead detail page - no matter)
     */
    function isLeadContext() {
        const container = document.querySelector('.email-interface-container');
        return container && container.dataset.context === 'lead';
    }

    /**
     * Get CSRF token from meta tag
     */
    function getCsrfToken() {
        const token = document.querySelector('meta[name="csrf-token"]');
        return token ? token.getAttribute('content') : '';
    }

    const MAX_EMAIL_FILES = 10;
    const MAX_EMAIL_FILE_BYTES = (typeof window.__CRM_EMAIL_MAX_FILE_BYTES__ === 'number' && window.__CRM_EMAIL_MAX_FILE_BYTES__ > 0)
        ? window.__CRM_EMAIL_MAX_FILE_BYTES__
        : (30 * 1024 * 1024);

    function isSessionOrSecurityError(message) {
        if (!message) {
            return false;
        }
        const lower = String(message).toLowerCase();
        return lower.includes('session') ||
            lower.includes('security token') ||
            lower.includes('unauthenticated') ||
            lower.includes('log in again');
    }

    /**
     * Map failed upload HTTP responses to user-facing messages.
     */
    function resolveUploadHttpError(status, errorText) {
        const text = errorText || '';
        let parsed = null;

        try {
            parsed = JSON.parse(text);
        } catch (e) {
            parsed = null;
        }

        if (parsed && typeof parsed === 'object') {
            if (status === 403 && (parsed.error_type === 'forbidden' || parsed.message === 'Unauthorized')) {
                return 'You do not have permission to upload emails for this client.';
            }
            if (status === 401 || parsed.message === 'Unauthenticated.') {
                return 'Your session has expired. Please refresh the page and log in again.';
            }
            if (status === 422) {
                const allowedLabel = (typeof window.crmEmailUploadExtensionsLabel === 'function')
                    ? window.crmEmailUploadExtensionsLabel()
                    : '.msg, .eml';
                let detail = 'Only Outlook email files are allowed (' + allowedLabel + ', max ' + MAX_EMAIL_FILES + ' files, ' + formatFileSize(MAX_EMAIL_FILE_BYTES) + ' each). Check your selection and try again.';
                if (parsed.errors && typeof parsed.errors === 'object') {
                    const flat = Object.values(parsed.errors)
                        .flat()
                        .map(function(v) { return v == null ? '' : String(v); })
                        .filter(Boolean);
                    if (flat.length) {
                        detail = flat.join(' ');
                    }
                } else if (parsed.message && String(parsed.message).trim() !== '' && parsed.message !== 'Validation failed') {
                    detail = String(parsed.message);
                }
                return detail;
            }
            if (status === 400) {
                let errorMsg = parsed.message || 'Upload failed';
                if (parsed.errors && Array.isArray(parsed.errors) && parsed.errors.length > 0) {
                    const details = parsed.errors.map(function(e, i) {
                        return (i + 1) + '. ' + (e.filename || 'Unknown file') + ': ' + (e.error || 'Unknown error');
                    }).join('\n');
                    errorMsg += '\n\nDetails:\n' + details;
                }
                return errorMsg;
            }
        }

        if (status === 419 || text.includes('CSRF token mismatch')) {
            return 'Security token expired. Please refresh the page and try again.';
        }
        if (status === 413) {
            return 'File too large. Maximum file size is ' + formatFileSize(MAX_EMAIL_FILE_BYTES) + ' per file.';
        }
        if (status === 403) {
            if (typeof window.crmEmailUpload403Message === 'function') {
                const wafMsg = window.crmEmailUpload403Message(text, status);
                if (wafMsg) {
                    return wafMsg;
                }
            }
            const isHtml = /<html[\s>]/i.test(text) || /<!DOCTYPE/i.test(text);
            if (isHtml || (text.includes('Forbidden') && !parsed)) {
                return 'The server blocked this upload (security filter). Rename files to remove special characters such as apostrophes and try again.';
            }
            if (text.includes('CSRF')) {
                return 'Session expired or security token invalid. Please refresh the page and try again.';
            }
            return 'The server blocked this upload (403). Try smaller .msg files or refresh the page. If it persists, contact your administrator.';
        }

        if (text.includes('403 Forbidden') || (status === 403 && text.includes('Forbidden'))) {
            return 'The server blocked this upload. Try smaller files or refresh the page and try again.';
        }
        if (text.includes('419') || text.includes('CSRF')) {
            return 'Security token expired. Please refresh the page and try again.';
        }

        if (status > 0) {
            return 'Upload failed: ' + status + ' ' + (text.substring(0, 500) || 'Unknown error');
        }

        return 'Server returned an invalid response. Please refresh the page and try again.';
    }

    /**
     * Sanitize filename for multipart upload (WAF-safe).
     * Mirrors EmailUploadController::sanitizeFilename — apostrophes in Content-Disposition can trigger mod_security 403.
     */
    function sanitizeUploadFilename(filename) {
        if (typeof window.crmSanitizeEmailUploadFilename === 'function') {
            return window.crmSanitizeEmailUploadFilename(filename);
        }
        if (!filename || typeof filename !== 'string') {
            return 'email_' + Date.now() + '.msg';
        }
        const lastDot = filename.lastIndexOf('.');
        let extension = lastDot >= 0 ? filename.slice(lastDot + 1) : '';
        const nameWithoutExt = lastDot >= 0 ? filename.slice(0, lastDot) : filename;
        let sanitizedName = nameWithoutExt.replace(/[^a-zA-Z0-9_-]/g, '_');
        sanitizedName = sanitizedName.replace(/_+/g, '_').replace(/^_+|_+$/g, '');
        if (!sanitizedName) {
            sanitizedName = 'email_' + Date.now();
        }
        extension = extension.toLowerCase().replace(/[^a-z0-9]/g, '');
        let sanitizedFilename = extension ? sanitizedName + '.' + extension : sanitizedName;
        if (sanitizedFilename.length > 255) {
            const maxNameLength = 255 - extension.length - (extension ? 1 : 0);
            if (maxNameLength > 0) {
                sanitizedName = sanitizedName.slice(0, maxNameLength);
                sanitizedFilename = extension ? sanitizedName + '.' + extension : sanitizedName;
            } else {
                sanitizedFilename = 'email_' + Date.now() + (extension ? '.' + extension : '');
            }
        }
        return sanitizedFilename;
    }

    function buildEmailUploadFormData(file, clientId, matterId, csrfToken, forceUpload) {
        const formData = new FormData();
        const isLead = isLeadContext();
        const safeName = sanitizeUploadFilename(file.name);
        formData.append('email_files[]', file, safeName);
        formData.append('client_id', clientId);
        formData.append('type', isLead ? 'lead' : 'client');
        formData.append(
            currentMailType === 'sent' ? 'upload_sent_mail_client_matter_id' : 'upload_inbox_mail_client_matter_id',
            (matterId !== null && matterId !== undefined) ? matterId : ''
        );
        formData.append('_token', csrfToken);
        if (forceUpload) {
            formData.append('force_upload', '1');
        }
        return formData;
    }

    const DUPLICATE_EXISTS_MESSAGE = 'This email already exists.';

    function ensureDuplicateEmailPromptModal() {
        let modal = document.getElementById('duplicateEmailModal');
        if (modal) {
            return modal;
        }

        if (!document.getElementById('duplicateEmailPromptStyles')) {
            const style = document.createElement('style');
            style.id = 'duplicateEmailPromptStyles';
            style.textContent = `
                .duplicate-email-modal-overlay{display:none;position:fixed;inset:0;background:rgba(50,49,48,.45);z-index:10050;align-items:center;justify-content:center;padding:20px}
                .duplicate-email-modal-overlay.active{display:flex}
                .duplicate-email-modal{width:100%;max-width:420px;background:#fff;border-radius:12px;box-shadow:0 16px 40px rgba(0,0,0,.18);padding:28px 24px 22px;text-align:center}
                .duplicate-email-modal__icon{width:56px;height:56px;margin:0 auto 16px;border-radius:50%;background:#fff4e5;color:#d97706;display:flex;align-items:center;justify-content:center;font-size:24px}
                .duplicate-email-modal__title{margin:0 0 10px;font-size:20px;font-weight:600;color:#323130}
                .duplicate-email-modal__message{margin:0 0 8px;font-size:15px;color:#323130;font-weight:500}
                .duplicate-email-modal__filename{margin:0 0 12px;font-size:13px;color:#605e5c;word-break:break-all}
                .duplicate-email-modal__question{margin:0 0 22px;font-size:14px;color:#605e5c}
                .duplicate-email-modal__actions{display:flex;gap:12px;justify-content:center}
                .duplicate-email-modal__btn{min-width:110px;padding:10px 18px;border-radius:6px;font-size:14px;font-weight:600;cursor:pointer;border:1px solid transparent}
                .duplicate-email-modal__btn--reject{background:#fff;border-color:#c8c6c4;color:#323130}
                .duplicate-email-modal__btn--accept{background:#0078d4;color:#fff}
            `;
            document.head.appendChild(style);
        }

        modal = document.createElement('div');
        modal.id = 'duplicateEmailModal';
        modal.className = 'duplicate-email-modal-overlay';
        modal.setAttribute('aria-hidden', 'true');
        modal.innerHTML = `
            <div class="duplicate-email-modal" role="dialog" aria-labelledby="duplicateEmailModalTitle" aria-modal="true">
                <div class="duplicate-email-modal__icon" aria-hidden="true"><i class="fas fa-envelope-open-text"></i></div>
                <h3 class="duplicate-email-modal__title" id="duplicateEmailModalTitle">Duplicate Email</h3>
                <p class="duplicate-email-modal__message">${DUPLICATE_EXISTS_MESSAGE}</p>
                <p class="duplicate-email-modal__filename" id="duplicateEmailFileName"></p>
                <p class="duplicate-email-modal__question">Do you want to upload it anyway?</p>
                <div class="duplicate-email-modal__actions">
                    <button type="button" class="duplicate-email-modal__btn duplicate-email-modal__btn--reject" id="duplicateEmailReject">Reject</button>
                    <button type="button" class="duplicate-email-modal__btn duplicate-email-modal__btn--accept" id="duplicateEmailAccept">Accept</button>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
        return modal;
    }

    function showDuplicateEmailPrompt(fileName) {
        return new Promise(function(resolve) {
            const modal = ensureDuplicateEmailPromptModal();
            const fileNameEl = document.getElementById('duplicateEmailFileName');
            const acceptBtn = document.getElementById('duplicateEmailAccept');
            const rejectBtn = document.getElementById('duplicateEmailReject');

            if (fileNameEl) {
                fileNameEl.textContent = fileName ? ('File: ' + fileName) : '';
            }

            function cleanup() {
                modal.classList.remove('active');
                modal.setAttribute('aria-hidden', 'true');
                acceptBtn.removeEventListener('click', onAccept);
                rejectBtn.removeEventListener('click', onReject);
                modal.removeEventListener('click', onOverlayClick);
                document.removeEventListener('keydown', onKeyDown);
            }

            function onAccept() {
                cleanup();
                resolve(true);
            }

            function onReject() {
                cleanup();
                resolve(false);
            }

            function onOverlayClick(event) {
                if (event.target === modal) {
                    onReject();
                }
            }

            function onKeyDown(event) {
                if (event.key === 'Escape') {
                    onReject();
                }
            }

            acceptBtn.addEventListener('click', onAccept);
            rejectBtn.addEventListener('click', onReject);
            modal.addEventListener('click', onOverlayClick);
            document.addEventListener('keydown', onKeyDown);

            modal.classList.add('active');
            modal.setAttribute('aria-hidden', 'false');
            acceptBtn.focus();
        });
    }

    function getDuplicateUploadError(data) {
        if (!data || !Array.isArray(data.errors)) {
            return null;
        }
        return data.errors.find(function(err) { return err && err.duplicate; }) || null;
    }

    /**
     * POST one .msg file to the upload endpoint and return parsed JSON.
     */
    async function postEmailUpload(file, clientId, matterId, csrfToken, uploadPath, forceUpload) {
        const formData = buildEmailUploadFormData(file, clientId, matterId, csrfToken, forceUpload);
        const response = await fetch(
            crmUrl(uploadPath),
            {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                },
                body: formData,
                credentials: 'same-origin'
            }
        );

        if (!response.ok) {
            const errorText = await response.text();
            throw new Error(resolveUploadHttpError(response.status, errorText));
        }

        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            const errorText = await response.text();
            throw new Error(resolveUploadHttpError(0, errorText));
        }

        return response.json();
    }

    function renderUploadBatchResult(data, uploadProgress, fileStatus, fileCountBadge) {
        if (data.status || data.success) {
            const failedCount = data.failed || 0;
            const uploadedCount = data.uploaded || 0;

            if (failedCount > 0) {
                if (uploadProgress) {
                    uploadProgress.className = 'upload-progress error';
                }
                fileStatus.textContent = 'Upload completed with errors';

                let errorMessage = data.message || ('Upload failed: ' + failedCount + ' file(s) failed');

                if (data.errors && Array.isArray(data.errors) && data.errors.length > 0) {
                    const errorDetails = data.errors.map(function(err, index) {
                        const filename = err.filename || 'Unknown file';
                        const error = err.error || 'Unknown error';
                        const fileSize = err.file_size ? (' (' + formatFileSize(err.file_size) + ')') : '';
                        return (index + 1) + '. ' + filename + fileSize + '\n   ' + error;
                    }).join('\n\n');
                    errorMessage += '\n\nError Details:\n' + errorDetails;

                    if (uploadedCount === 0 && failedCount > 0) {
                        errorMessage += '\n\nTip: Ensure the Python service is running and the .msg files are valid Outlook email files.';
                    }
                }

                showNotification(errorMessage, 'error');

                if (data.errors) {
                    console.error('Upload errors:', data.errors);
                }

                setTimeout(function() {
                    fileStatus.textContent = 'Ready to upload';
                    if (uploadProgress) {
                        uploadProgress.className = 'upload-progress';
                    }
                    if (fileCountBadge && uploadedCount === 0) {
                        fileCountBadge.classList.remove('show');
                    }
                }, 5000);

                if (uploadedCount > 0) {
                    loadEmails();
                }
            } else {
                if (uploadProgress) {
                    uploadProgress.className = 'upload-progress success';
                }
                fileStatus.textContent = 'Upload successful!';
                showNotification(data.message || 'Files uploaded successfully!', 'success');

                setTimeout(function() {
                    const fileInputEl = document.getElementById('emailFileInput');
                    if (fileInputEl) {
                        fileInputEl.value = '';
                    }
                    fileStatus.textContent = 'Ready to upload';
                    if (uploadProgress) {
                        uploadProgress.className = 'upload-progress';
                    }
                    if (fileCountBadge) {
                        fileCountBadge.classList.remove('show');
                    }
                }, 2000);

                loadEmails();
            }
        } else {
            if (uploadProgress) {
                uploadProgress.className = 'upload-progress error';
            }
            fileStatus.textContent = 'Upload failed';

            let errorMessage = data.message || 'Upload failed';

            if (data.errors) {
                if (typeof data.errors === 'object' && !Array.isArray(data.errors)) {
                    const errorDetails = [];
                    for (const key of Object.keys(data.errors)) {
                        const value = data.errors[key];
                        const fieldName = key.replace(/_/g, ' ').replace(/\b\w/g, function(l) { return l.toUpperCase(); });
                        if (Array.isArray(value)) {
                            errorDetails.push('• ' + fieldName + ': ' + value.join(', '));
                        } else {
                            errorDetails.push('• ' + fieldName + ': ' + value);
                        }
                    }
                    if (errorDetails.length > 0) {
                        errorMessage += '\n\nValidation Errors:\n' + errorDetails.join('\n');
                    }
                } else if (Array.isArray(data.errors) && data.errors.length > 0) {
                    const errorDetails = data.errors.map(function(err, index) {
                        if (typeof err === 'string') {
                            return (index + 1) + '. ' + err;
                        }
                        if (err.filename && err.error) {
                            return (index + 1) + '. ' + err.filename + ': ' + err.error;
                        }
                        return (index + 1) + '. ' + JSON.stringify(err);
                    }).join('\n');
                    errorMessage += '\n\nErrors:\n' + errorDetails;
                }
                console.error('Upload errors:', data.errors);
            }

            if (data.technical_error && data.technical_error !== errorMessage) {
                console.error('Technical error:', data.technical_error);
            }

            showNotification(errorMessage, 'error');

            setTimeout(function() {
                fileStatus.textContent = 'Ready to upload';
                if (uploadProgress) {
                    uploadProgress.className = 'upload-progress';
                }
            }, 5000);
        }
    }

    /**
     * filterEmails may return a raw array of emails, or { status, emails, message }
     * when the schema is not migrated / integration not configured (see ClientsController::filterEmails).
     */
    function normalizeEmailListResponse(data) {
        if (Array.isArray(data)) {
            return data;
        }
        // Sometimes the backend can return JSON as a string.
        if (typeof data === 'string') {
            try {
                return normalizeEmailListResponse(JSON.parse(data));
            } catch (e) {
                console.warn('Could not parse email list JSON string response:', e);
                return [];
            }
        }
        // Support Laravel paginator payload shape: { data: [...] }
        if (data && Array.isArray(data.data)) {
            return data.data;
        }
        if (data && data.emails != null) {
            const e = data.emails;
            if (Array.isArray(e)) {
                return e;
            }
            // Sometimes emails may be sent as a JSON string
            if (typeof e === 'string') {
                try {
                    const parsed = JSON.parse(e);
                    if (Array.isArray(parsed)) {
                        return parsed;
                    }
                } catch (err) {
                    console.warn('Could not parse `emails` JSON string:', err);
                }
            }
            // PHP may JSON-encode a non-sequential list as an object; only treat numeric keys as a list.
            if (typeof e === 'object' && e !== null) {
                const keys = Object.keys(e);
                if (keys.length > 0 && keys.every(k => /^\d+$/.test(k))) {
                    return keys
                        .map(k => Number(k))
                        .sort((a, b) => a - b)
                        .map(k => e[String(k)]);
                }
            }
        }
        if (data && data.status === 'error') {
            console.warn('Email list request returned error status:', data.message || data);
            return [];
        }
        // Fallback: if backend returns a single email object, render it as one-item list.
        if (data && typeof data === 'object' && (data.id || data.subject || data.from_mail || data.to_mail)) {
            return [data];
        }
        console.warn('Unexpected email list response shape:', data);
        return [];
    }

    /**
     * Show notification message
     */
    function showNotification(message, type = 'info') {
        if (typeof crmNotify !== 'undefined') {
            // Replace newlines with <br> to preserve formatting in iziToast
            const formattedMessage = message.replace(/\n/g, '<br>');
            if (type === 'success') {
                crmNotify.success({ title: 'Success', message: formattedMessage, position: 'topRight', transitionIn: 'fadeInDown', transitionOut: 'fadeOutUp' });
            } else if (type === 'error') {
                crmNotify.error({ title: 'Error', message: formattedMessage, position: 'topRight', timeout: 8000, transitionIn: 'fadeInDown', transitionOut: 'fadeOutUp' });
            } else {
                crmNotify.info({ title: 'Alert', message: formattedMessage, position: 'topRight', transitionIn: 'fadeInDown', transitionOut: 'fadeOutUp' });
            }
            return;
        }

        const notification = document.createElement('div');
        notification.className = `email-notification email-notification-${type}`;
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            border-radius: 4px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            z-index: 10000;
            max-width: 500px;
            max-height: 400px;
            overflow-y: auto;
            animation: slideIn 0.3s ease-out;
            font-size: 14px;
            white-space: pre-wrap;
            word-wrap: break-word;
            ${type === 'success' ? 'background: #10b981; color: white;' : ''}
            ${type === 'error' ? 'background: #ef4444; color: white;' : ''}
            ${type === 'info' ? 'background: #3b82f6; color: white;' : ''}
        `;
        notification.textContent = message;

        document.body.appendChild(notification);

        // Longer display time for error messages
        const displayTime = type === 'error' ? 8000 : 4000;

        setTimeout(() => {
            notification.style.animation = 'slideOut 0.3s ease-out';
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 300);
        }, displayTime);
    }

    /**
     * Format a Date object as dd/mm/yyyy hh:mm am/pm
     */
    function formatDateFromObject(date) {
        const day = String(date.getDate()).padStart(2, '0');
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const year = date.getFullYear();
        let hours = date.getHours();
        const minutes = String(date.getMinutes()).padStart(2, '0');
        const ampm = hours >= 12 ? 'pm' : 'am';
        hours = hours % 12;
        if (hours === 0) {
            hours = 12;
        }
        return day + '/' + month + '/' + year + ' ' + String(hours).padStart(2, '0') + ':' + minutes + ' ' + ampm;
    }

    /**
     * Format date to dd/mm/yyyy (with time) for email UI
     * Handles ISO date strings and formatted strings like "dd/mm/yyyy hh:mm am/pm"
     */
    function formatDate(dateString) {
        if (!dateString) return 'Unknown';
        try {
            if (typeof dateString === 'string' && /^\d{2}\/\d{2}\/\d{4}/.test(dateString)) {
                const parts = dateString.match(/^(\d{2})\/(\d{2})\/(\d{4})(?:\s+(\d{1,2}):(\d{2})\s*(am|pm))?/i);
                if (parts) {
                    const [, day, month, year, hour, minute, ampm] = parts;
                    if (hour !== undefined) {
                        return day + '/' + month + '/' + year + ' ' + String(hour).padStart(2, '0') + ':' + minute + ' ' + (ampm || '').toLowerCase();
                    }
                    return day + '/' + month + '/' + year;
                }
            }
            const date = new Date(dateString);
            if (isNaN(date.getTime())) {
                return dateString;
            }
            return formatDateFromObject(date);
        } catch (e) {
            return dateString;
        }
    }

    /**
     * Get the email date to display (prefers sent date over upload date)
     */
    function getEmailDate(email) {
        // Prefer fetch_mail_sent_time (email's original sent date)
        if (email.fetch_mail_sent_time) {
            return email.fetch_mail_sent_time;
        }
        // Fallback to received_date if available
        if (email.received_date) {
            return email.received_date;
        }
        // Last resort: use created_at - CRM-sent emails have this (was bug: recursive call caused stack overflow)
        return email.created_at || null;
    }

    /**
     * Format file size to readable string
     */
    function formatFileSize(bytes) {
        if (!bytes || bytes === 0) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + ' ' + sizes[i];
    }

    /**
     * Get attachment icon class based on content type
     */
    function getAttachmentIcon(contentType) {
        if (!contentType) return 'fas fa-paperclip';
        
        const type = contentType.toLowerCase();
        
        // Images
        if (type.includes('image')) {
            return 'fas fa-image';
        }
        
        // PDFs
        if (type.includes('pdf')) {
            return 'fas fa-file-pdf';
        }
        
        // Word documents
        if (type.includes('word') || type.includes('document') || type.includes('.docx')) {
            return 'fas fa-file-word';
        }
        
        // Excel spreadsheets
        if (type.includes('excel') || type.includes('spreadsheet') || type.includes('.xlsx')) {
            return 'fas fa-file-excel';
        }
        
        // PowerPoint
        if (type.includes('powerpoint') || type.includes('presentation')) {
            return 'fas fa-file-powerpoint';
        }
        
        // Archives
        if (type.includes('zip') || type.includes('rar') || type.includes('archive')) {
            return 'fas fa-file-archive';
        }
        
        // Code files
        if (type.includes('text/plain') || type.includes('code') || type.includes('javascript') || type.includes('html')) {
            return 'fas fa-file-code';
        }
        
        // Default
        return 'fas fa-paperclip';
    }

    /**
     * Get attachment icon color class based on content type
     */
    function getAttachmentIconColor(contentType) {
        if (!contentType) return '';
        
        const type = contentType.toLowerCase();
        
        if (type.includes('image')) return 'attachment-icon-image';
        if (type.includes('pdf')) return 'attachment-icon-pdf';
        if (type.includes('word') || type.includes('document')) return 'attachment-icon-word';
        if (type.includes('excel') || type.includes('spreadsheet')) return 'attachment-icon-excel';
        
        return '';
    }

    /**
     * Check if attachment can be previewed
     */
    function canPreviewAttachment(contentType) {
        if (!contentType) return false;
        
        const type = contentType.toLowerCase();
        return type.includes('image/') || type.includes('pdf');
    }

    /**
     * Sanitize filename for safe download
     */
    function sanitizeFilename(filename) {
        if (!filename) return 'download';
        
        // Remove invalid filename characters
        return filename
            .replace(/[/\\?%*:|"<>]/g, '-')  // Replace invalid chars
            .replace(/\s+/g, '_')             // Replace spaces with underscore
            .substring(0, 200);               // Limit length
    }

    /**
     * Filter to get only regular (non-inline) attachments
     */
    function getRegularAttachments(attachments) {
        if (!attachments || !Array.isArray(attachments)) {
            return [];
        }
        
        return attachments.filter(att => !att.is_inline);
    }

    /**
     * Debounce function
     */
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    // =========================================================================
    // Upload Functionality
    // =========================================================================

    /**
     * Initialize upload functionality with drag & drop
     */
    window.initializeUpload = function() {
        const fileInput = document.getElementById('emailFileInput');
        const uploadArea = document.getElementById('upload-area');
        const fileStatus = document.getElementById('fileStatus');
        const fileCountBadge = document.getElementById('file-count');
        const uploadProgress = document.getElementById('upload-progress');

        if (!fileInput || !uploadArea || !fileStatus) {
            console.warn('Upload elements not found - skipping email upload initialization (page may not have emails UI)');
            return;
        }

        let dragCounter = 0;

        // Prevent default drag behaviors on document
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            document.body.addEventListener(eventName, preventDefaults, false);
        });

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        // Highlight drop area when item is dragged over it
        uploadArea.addEventListener('dragenter', function(e) {
            dragCounter++;
            uploadArea.classList.add('drag-over');
        });

        uploadArea.addEventListener('dragleave', function(e) {
            dragCounter--;
            if (dragCounter === 0) {
                uploadArea.classList.remove('drag-over');
            }
        });

        uploadArea.addEventListener('dragover', function(e) {
            e.preventDefault();
        });

        // Handle dropped files
        uploadArea.addEventListener('drop', function(e) {
            dragCounter = 0;
            uploadArea.classList.remove('drag-over');
            
            const dt = e.dataTransfer;
            const files = dt.files;
            
            if (files && files.length > 0) {
                handleFiles(files);
            }
        });

        // Click to open file dialog
        uploadArea.addEventListener('click', function() {
            if (!isUploading) {
                fileInput.click();
            }
        });

        // Handle file input change
        fileInput.addEventListener('change', function() {
            const files = this.files;
            if (files && files.length > 0) {
                handleFiles(files);
            }
        });

        function handleFiles(files) {
            if (isUploading) {
                return;
            }

            // Filter to allowed Outlook email files
            const msgFiles = (typeof window.crmFilterAllowedEmailUploadFiles === 'function')
                ? window.crmFilterAllowedEmailUploadFiles(files)
                : Array.from(files).filter(function (file) {
                    return file.name.toLowerCase().endsWith('.msg') || file.name.toLowerCase().endsWith('.eml');
                });

            if (msgFiles.length === 0) {
                const allowedLabel = (typeof window.crmEmailUploadExtensionsLabel === 'function')
                    ? window.crmEmailUploadExtensionsLabel()
                    : '.msg, .eml';
                showNotification('Please select Outlook email files only (' + allowedLabel + ')', 'error');
                fileStatus.textContent = 'Invalid file type';
                fileStatus.parentElement.className = 'upload-progress error';
                setTimeout(function () {
                    fileStatus.textContent = 'Ready to upload';
                    fileStatus.parentElement.className = 'upload-progress';
                }, 3000);
                return;
            }

            if (msgFiles.length !== files.length) {
                showNotification('Only ' + msgFiles.length + ' of ' + files.length + ' files are valid Outlook email files', 'info');
            }

            if (msgFiles.length > MAX_EMAIL_FILES) {
                showNotification('Maximum ' + MAX_EMAIL_FILES + ' email files allowed per upload.', 'error');
                fileStatus.textContent = 'Too many files selected';
                fileStatus.parentElement.className = 'upload-progress error';
                setTimeout(function() {
                    fileStatus.textContent = 'Ready to upload';
                    fileStatus.parentElement.className = 'upload-progress';
                }, 3000);
                return;
            }

            const oversizedFiles = msgFiles.filter(function(file) {
                return file.size > MAX_EMAIL_FILE_BYTES;
            });
            if (oversizedFiles.length > 0) {
                const names = oversizedFiles.map(function(f) { return f.name; }).join(', ');
                showNotification(
                    oversizedFiles.length + ' file(s) exceed the ' + formatFileSize(MAX_EMAIL_FILE_BYTES) + ' limit: ' + names,
                    'error'
                );
                fileStatus.textContent = 'File too large';
                fileStatus.parentElement.className = 'upload-progress error';
                setTimeout(function() {
                    fileStatus.textContent = 'Ready to upload';
                    fileStatus.parentElement.className = 'upload-progress';
                }, 4000);
                return;
            }

            // Update file count badge
            updateFileCount(msgFiles.length);

            // Update status
            fileStatus.textContent = `${msgFiles.length} file(s) ready to upload`;
            fileStatus.parentElement.className = 'upload-progress';

            // Auto-upload immediately
            uploadFiles(msgFiles);
        }

        function updateFileCount(count) {
            if (fileCountBadge) {
                fileCountBadge.textContent = count;
                if (count > 0) {
                    fileCountBadge.classList.add('show');
                } else {
                    fileCountBadge.classList.remove('show');
                }
            }
        }

    };

    /**
     * Upload files to server (one request per file to avoid post_max_size / WAF limits)
     */
    async function uploadFiles(files) {
        const clientId = getClientId();
        const matterId = getMatterId();
        
        if (!clientId) {
            showNotification('Client ID not found', 'error');
            return;
        }
        
        if (!matterId) {
            showNotification('Matter ID not found. Please select a matter.', 'error');
            return;
        }

        isUploading = true;
        
        const fileStatus = document.getElementById('fileStatus');
        const uploadProgress = document.getElementById('upload-progress');
        const fileCountBadge = document.getElementById('file-count');
        
        if (uploadProgress) {
            uploadProgress.className = 'upload-progress uploading';
        }
        fileStatus.textContent = 'Uploading ' + files.length + ' file(s)...';

        try {
            const csrfToken = getCsrfToken();
            if (!csrfToken) {
                throw new Error('Security token not found. Please refresh the page and try again.');
            }

            const uploadPath = currentMailType === 'sent' ? '/upload-sent-fetch-mail' : '/upload-fetch-mail';

            let totalUploaded = 0;
            let totalFailed = 0;
            const allErrors = [];
            let stoppedEarly = false;

            for (let i = 0; i < files.length; i++) {
                const file = files[i];
                fileStatus.textContent = 'Uploading ' + (i + 1) + ' of ' + files.length + ': ' + file.name + '...';

                try {
                    let data = await postEmailUpload(file, clientId, matterId, csrfToken, uploadPath);
                    const duplicateError = getDuplicateUploadError(data);

                    if (duplicateError) {
                        const acceptUpload = await showDuplicateEmailPrompt(file.name);
                        if (acceptUpload) {
                            data = await postEmailUpload(file, clientId, matterId, csrfToken, uploadPath, true);
                        } else {
                            totalFailed += 1;
                            allErrors.push({
                                filename: file.name,
                                error: DUPLICATE_EXISTS_MESSAGE,
                                duplicate: true,
                                rejected: true
                            });
                            showNotification(DUPLICATE_EXISTS_MESSAGE, 'error');
                            continue;
                        }
                    }

                    const fileUploaded = data.uploaded || 0;
                    const fileFailed = data.failed || 0;
                    totalUploaded += fileUploaded;
                    totalFailed += fileFailed;

                    if (data.errors && Array.isArray(data.errors)) {
                        allErrors.push.apply(allErrors, data.errors);
                    } else if (fileUploaded === 0 && fileFailed === 0 && !data.status) {
                        totalFailed += 1;
                        allErrors.push({
                            filename: file.name,
                            error: data.message || 'Upload failed'
                        });
                    }
                } catch (fileError) {
                    console.error('Upload error for file:', file.name, fileError);
                    totalFailed += 1;
                    allErrors.push({
                        filename: file.name,
                        error: fileError.message || 'Upload failed'
                    });

                    if (isSessionOrSecurityError(fileError.message)) {
                        stoppedEarly = true;
                        break;
                    }
                }
            }

            const batchResult = {
                status: totalUploaded > 0 || totalFailed === 0,
                success: totalUploaded > 0 && totalFailed === 0,
                uploaded: totalUploaded,
                failed: totalFailed,
                errors: allErrors,
                message: stoppedEarly
                    ? (allErrors[0] && allErrors[0].error) || 'Upload stopped due to a session or security error.'
                    : (totalUploaded > 0 && totalFailed > 0
                        ? 'Partially successful: ' + totalUploaded + ' uploaded, ' + totalFailed + ' failed'
                        : (totalUploaded > 0
                            ? 'Successfully uploaded ' + totalUploaded + ' email' + (totalUploaded > 1 ? 's' : '')
                            : 'Upload failed: ' + totalFailed + ' email' + (totalFailed > 1 ? 's' : '') + ' could not be processed'))
            };

            renderUploadBatchResult(batchResult, uploadProgress, fileStatus, fileCountBadge);

        } catch (error) {
            console.error('Upload error:', error);
            if (uploadProgress) {
                uploadProgress.className = 'upload-progress error';
            }
            fileStatus.textContent = 'Upload failed';
            showNotification('Upload failed: ' + error.message, 'error');
            
            setTimeout(function() {
                fileStatus.textContent = 'Ready to upload';
                if (uploadProgress) {
                    uploadProgress.className = 'upload-progress';
                }
            }, 3000);
        } finally {
            isUploading = false;
        }
    }

    // =========================================================================
    // Search Functionality
    // =========================================================================

    /**
     * Initialize search functionality
     */
    window.initializeSearch = function() {
        const searchInput = document.getElementById('emailSearchInput');
        const labelFilter = document.getElementById('labelFilter');

        if (!searchInput) {
            console.warn('Search input not found - skipping search initialization');
            return;
        }
        
        if (!labelFilter) {
            console.warn('Label filter not found - search will work with limited functionality');
        }

        // Real-time search (debounced)
        const debouncedSearch = debounce(function() {
            currentSearch = searchInput.value;
            currentPage = 1;
            loadEmails();
        }, 500);

        searchInput.addEventListener('input', debouncedSearch);

        // Label filter change - auto-applies when changed
        if (labelFilter) {
            labelFilter.addEventListener('change', function() {
                currentLabelId = this.value;
                currentPage = 1;
                loadEmails();
            });
        }

        const senderFilter = document.getElementById('senderFilter');
        if (senderFilter) {
            senderFilter.addEventListener('change', function() {
                currentSenderFilter = this.value;
                currentPage = 1;
                loadEmails();
            });
        }

        // Fetch unique senders when module initializes
        fetchSenders();
    };

    // =========================================================================
    // Email List Functionality
    // =========================================================================

    /**
     * Initialize email list and load initial emails
     */
    window.loadEmails = function() {
        const container = document.querySelector('.email-interface-container');
        if (!container) {
            return;
        }
        const isLead = isLeadContext();
        if (!container.dataset.clientId) {
            return;
        }
        if (!isLead && !container.dataset.matterId) {
            return;
        }
        loadEmailsFromServer();
    };

    /**
     * Fetch and display emails from server
     */
    async function loadEmailsFromServer() {
        const clientId = getClientId();
        const matterId = getMatterId();
        const isLead = isLeadContext();
        
        if (!clientId) {
            return;
        }
        
        if (!isLead && !matterId) {
            const container = document.querySelector('.email-interface-container');
            if (container) {
                renderEmptyState('Please select a matter to view emails');
            }
            return;
        }

        if (isLoading) {
            return;
        }

        isLoading = true;
        updateLoadingState(true);

        try {
            const endpoint = crmUrl(
                isLead
                    ? '/clients/filter-lead-emails'
                    : (currentMailType === 'sent' ? '/clients/filter-sentemails' : '/clients/filter-emails')
            );

            const requestBody = isLead
                ? { client_id: clientId, search: currentSearch, status: '', label_id: currentLabelId, sender_filter: currentSenderFilter, page: currentPage }
                : { client_id: clientId, client_matter_id: matterId, search: currentSearch, status: '', label_id: currentLabelId, sender_filter: currentSenderFilter, page: currentPage };

            const response = await fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                    'Accept': 'application/json'
                },
                body: JSON.stringify(requestBody)
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const raw = await response.json();

            if (raw && raw.current_page !== undefined) {
                currentPage = raw.current_page;
                lastPage = raw.last_page || 1;
                
                const prevBtn = document.getElementById('prevBtn');
                const nextBtn = document.getElementById('nextBtn');
                if (prevBtn) prevBtn.disabled = currentPage <= 1;
                if (nextBtn) nextBtn.disabled = currentPage >= lastPage;
                
                const pageInfo = document.getElementById('pageInfo');
                if (pageInfo) {
                    const totalRecords = raw.total || 0;
                    const from = raw.from || 0;
                    const to = raw.to || 0;
                    if (totalRecords > 0) {
                        pageInfo.textContent = `Showing ${from}-${to} of ${totalRecords} (Page ${currentPage} of ${lastPage})`;
                    } else {
                        pageInfo.textContent = `0 records found (Page ${currentPage} of ${lastPage})`;
                    }
                }
            }

            const emails = normalizeEmailListResponse(raw);

            // Apply sorting
            const sortedEmails = sortEmails(emails);

            // API may return a human-readable message with an empty list (e.g. integration not configured)
            const emptyInfo =
                !Array.isArray(raw) && raw && raw.message && sortedEmails.length === 0
                    ? raw.message
                    : null;
            if (sortedEmails.length === 0 && emptyInfo) {
                renderEmptyState(emptyInfo, null);
                updateEmailCounts(0);
            } else {
                renderEmails(sortedEmails);
                updateEmailCounts(sortedEmails.length);
            }

        } catch (error) {
            console.error('Error loading emails:', error);
            showNotification('Failed to load emails: ' + error.message, 'error');
            renderEmptyState('Error loading emails');
        } finally {
            isLoading = false;
            updateLoadingState(false);
        }
    }

    /**
     * Sort emails based on current sort option
     */
    function sortEmails(emails) {
        if (!Array.isArray(emails)) {
            console.error('Emails is not an array:', emails);
            return [];
        }

        return emails.slice().sort((a, b) => {
            switch (currentSort) {
                case 'subject':
                    return (a.subject || '').localeCompare(b.subject || '');
                case 'sender':
                    return (a.from_mail || '').localeCompare(b.from_mail || '');
                case 'date':
                default:
                    // Use sent date for sorting, fallback to created_at
                    const getDateForSort = (email) => {
                        if (email.fetch_mail_sent_time) {
                            // Parse formatted date: "dd/mm/yyyy hh:mm am/pm"
                            const parts = email.fetch_mail_sent_time.match(/^(\d{2})\/(\d{2})\/(\d{4}) (\d{2}):(\d{2}) (am|pm)$/i);
                            if (parts) {
                                const [, day, month, year, hour, minute, ampm] = parts;
                                let hour24 = parseInt(hour);
                                if (ampm.toLowerCase() === 'pm' && hour24 !== 12) hour24 += 12;
                                if (ampm.toLowerCase() === 'am' && hour24 === 12) hour24 = 0;
                                return new Date(year, month - 1, day, hour24, minute);
                            }
                        }
                        if (email.received_date) {
                            return new Date(email.received_date);
                        }
                        return new Date(email.created_at || 0);
                    };
                    const dateA = getDateForSort(a);
                    const dateB = getDateForSort(b);
                    return dateB - dateA; // Newest first
            }
        });
    }

    /**
     * Render emails in the list
     */
    function renderEmails(emails) {
        const emailList = document.getElementById('emailList');
        if (!emailList) {
            console.error('Email list element not found');
            return;
        }

        // Clear existing content
        emailList.innerHTML = '';

        if (!emails || emails.length === 0) {
            let emptyMsg = null;
            let emptySub = null;
            if (isLeadContext()) {
                emptySub = 'Emails sent to this lead from the CRM will appear here.';
            } else if (currentMailType === 'sent') {
                emptySub = 'Emails sent from the CRM will appear here.';
            }
            renderEmptyState(emptyMsg, emptySub);
            return;
        }

        emails.forEach(email => {
            const emailItem = createEmailItem(email);
            emailList.appendChild(emailItem);
        });
    }

    /**
     * Create email list item element
     */
    function createEmailItem(email) {
        const div = document.createElement('div');
        div.className = 'email-item';
        div.dataset.emailId = email.id;

        const subject = email.subject || '(No subject)';
        const from = email.from_mail || 'Unknown sender';
        const to = cleanRecipients(email.to_mail) || 'Unknown recipient';
        const cc = cleanRecipients(email.cc);
        const date = formatDate(getEmailDate(email));
        const isRead = email.mail_is_read == 1;

        // NEW: Attachment indicator
        const hasAttachments = email.attachments && Array.isArray(email.attachments) && email.attachments.length > 0;
        const regularAttachments = hasAttachments ? getRegularAttachments(email.attachments) : [];
        const attachmentIcon = hasAttachments
            ? `<i class="fas fa-paperclip attachment-indicator" title="${email.attachments.length} attachment(s)"></i>`
            : '';
        const attachmentNamesHtml = regularAttachments.length
            ? `<div class="email-item-attachments">${regularAttachments.slice(0, 3).map(function(att) {
                const name = att.filename || att.display_name || att.file_name || 'Attachment';
                return `<span class="email-item-attachment-line"><i class="fas fa-file-alt"></i> ${escapeHtml(name)}</span>`;
            }).join('')}${regularAttachments.length > 3 ? `<span class="email-item-attachment-more">+${regularAttachments.length - 3} more</span>` : ''}</div>`
            : '';

        // NEW: Label badges
        const labelBadges = (email.labels && Array.isArray(email.labels)) 
            ? email.labels.map(label => 
                `<span class="label-badge" style="background-color: ${label.color}20; border-color: ${label.color}; color: ${label.color}">
                    <i class="${label.icon || 'fas fa-tag'}"></i> ${label.name}
                </span>`
            ).join('')
            : '';

        div.innerHTML = `
            <div class="email-item-header">
                <div class="email-subject" style="${!isRead ? 'font-weight: 700;' : ''}">
                    ${escapeHtml(subject)}
                    ${attachmentIcon}
                </div>
                <div class="email-date">${date}</div>
            </div>
            <div class="email-sender">From: ${escapeHtml(from)}</div>
            <div class="email-sender" style="font-size: 12px; color: #999;">To: ${escapeHtml(to)}</div>
            ${cc ? `<div class="email-sender" style="font-size: 12px; color: #999;">Cc: ${escapeHtml(cc)}</div>` : ''}
            <div class="email-badges">
                ${labelBadges}
            </div>
            ${attachmentNamesHtml}
        `;

        // Add click handler to view email
        div.addEventListener('click', function(e) {
            // Don't trigger if context menu is open (close it first on click)
            const contextMenu = document.getElementById('emailContextMenu');
            if (contextMenu && contextMenu.style.display === 'block') {
                hideContextMenu();
                return;
            }
            
            // Remove selection from other items
            document.querySelectorAll('.email-item').forEach(item => {
                item.classList.remove('selected');
            });
            
            // Add selection to this item
            this.classList.add('selected');
            
            // Load email details
            loadEmailDetail(email);
        });

        // Add right-click handler for context menu
        div.addEventListener('contextmenu', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            // Store current email for context menu actions
            this.dataset.emailData = JSON.stringify(email);
            
            // Show context menu at cursor position
            showContextMenu(e.clientX, e.clientY, email);
        });

        return div;
    }

    /**
     * Render empty state
     */
    function renderEmptyState(message = null, subtitle = null) {
        const emailList = document.getElementById('emailList');
        if (!emailList) return;

        const sub = subtitle || (message ? 'Please try again.' : (currentMailType === 'sent' ? 'Emails sent from the CRM will appear here.' : 'Upload .msg files to get started with email management.'));
        emailList.innerHTML = `
            <div class="empty-state">
                <div class="empty-state-icon">
                    <i class="fas fa-inbox"></i>
                </div>
                <div class="empty-state-text">
                    <h3>${message || 'No emails found'}</h3>
                    <p>${sub}</p>
                </div>
            </div>
        `;
    }

    /**
     * Update loading state visual indicator
     */
    function updateLoadingState(loading) {
        const emailList = document.getElementById('emailList');
        if (!emailList) return;

        if (loading) {
            emailList.innerHTML = `
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <i class="fas fa-spinner fa-spin"></i>
                    </div>
                    <div class="empty-state-text">
                        <h3>Loading emails...</h3>
                        <p>Please wait</p>
                    </div>
                </div>
            `;
        }
    }

    /**
     * Update email counts
     */
    function updateEmailCounts(total) {
        const resultsCount = document.getElementById('resultsCount');
        if (resultsCount) {
            resultsCount.textContent = `${total} result${total !== 1 ? 's' : ''}`;
        }
    }

    /**
     * Load and display email details with attachments (Gmail-like preview)
     */
    function loadEmailDetail(email) {
        const emailContentView = document.getElementById('emailContentView');
        const emailContentPlaceholder = document.getElementById('emailContentPlaceholder');

        if (!emailContentView || !emailContentPlaceholder) {
            console.error('Email detail elements not found');
            return;
        }

        // Hide placeholder, show content
        emailContentPlaceholder.style.display = 'none';
        emailContentView.style.display = 'block';

        const subject = email.subject || '(No subject)';
        const from = email.from_mail || 'Unknown';
        const to = cleanRecipients(email.to_mail) || 'Unknown';
        const cc = cleanRecipients(email.cc);
        const date = formatDate(getEmailDate(email));

        // Get all attachments (including inline) - show all so users can download important files like payment receipts
        const allAttachments = email.attachments && Array.isArray(email.attachments) ? email.attachments : [];
        const regularAttachments = allAttachments;
        const hasAttachments = regularAttachments.length > 0;

        // Build attachment list HTML
        let attachmentHtml = '';
        if (hasAttachments) {
            const attachmentItems = regularAttachments.map(att => `
                <div class="attachment-item" data-attachment-id="${att.id}">
                    <div class="attachment-info">
                        <i class="${getAttachmentIcon(att.content_type)} attachment-icon ${getAttachmentIconColor(att.content_type)}"></i>
                        <div class="attachment-details">
                            <div class="attachment-name">${escapeHtml(att.filename || att.display_name || 'Unknown')}</div>
                            <div class="attachment-size">${formatFileSize(att.file_size || 0)}</div>
                        </div>
                    </div>
                    <div class="attachment-actions">
                        <button class="download-btn download-attachment-btn" 
                                data-attachment-id="${att.id}" 
                                data-filename="${escapeHtml(att.filename || att.display_name || 'file')}"
                                title="Download ${escapeHtml(att.filename || 'file')}">
                            <i class="fas fa-download"></i> Download
                        </button>
                        ${canPreviewAttachment(att.content_type) ? `
                        <button class="preview-btn preview-attachment-btn" 
                                data-attachment-id="${att.id}" 
                                data-filename="${escapeHtml(att.filename || att.display_name || 'file')}"
                                title="Preview ${escapeHtml(att.filename || 'file')}">
                            <i class="fas fa-eye"></i> Preview
                        </button>
                        ` : ''}
                    </div>
                </div>
            `).join('');

            attachmentHtml = `
                <div class="attachment-list">
                    <div class="attachment-list-header">
                        <span class="attachment-list-title">
                            <i class="fas fa-paperclip"></i> 
                            ${regularAttachments.length} Attachment${regularAttachments.length !== 1 ? 's' : ''}
                        </span>
                        ${regularAttachments.length > 1 ? `
                        <button class="download-all-btn" 
                                data-mail-report-id="${email.id}"
                                data-email-subject="${escapeHtml(subject)}"
                                title="Download all attachments as ZIP">
                            <i class="fas fa-download"></i> Download All
                        </button>
                        ` : ''}
                    </div>
                    ${attachmentItems}
                </div>
            `;
        }

        // Original .msg file download section
        let previewSection = '';
        if (email.preview_url) {
            previewSection = `
                <div class="gmail-original-download">
                    <i class="fas fa-file-alt"></i>
                    <a href="${escapeHtml(email.preview_url)}" target="_blank">Download Original .msg</a>
                </div>
            `;
        }

        // --- Gmail-like email body (from database, rendered as HTML) ---
        let emailBodyHtml = '';
        const rawBody = email.enhanced_html || email.rendered_html || email.message || email.text_preview || '';

        if (rawBody && rawBody.trim() !== '') {
            emailBodyHtml = '<div class="gmail-body-frame-wrap"><iframe class="gmail-body-frame" id="gmailBodyFrame_' + email.id + '" sandbox="allow-same-origin allow-popups allow-popups-to-escape-sandbox" title="Email body"></iframe></div>';
        } else {
            emailBodyHtml = '<div class="gmail-body-empty"><i class="fas fa-envelope-open"></i><p>No email content available.</p></div>';
        }

        // Sender initial for avatar
        const senderInitial = from.charAt(0).toUpperCase();

        // Render complete Gmail-like email detail
        emailContentView.innerHTML = `
            <div class="gmail-preview">
                <div class="gmail-subject-row">
                    <h2 class="gmail-subject">${escapeHtml(subject)}</h2>
                </div>
                <div class="gmail-header">
                    <div class="gmail-avatar" style="background:${stringToColor(from)}">${senderInitial}</div>
                    <div class="gmail-header-info">
                        <div class="gmail-sender-row">
                            <span class="gmail-sender-name">${escapeHtml(from)}</span>
                            <span class="gmail-date">${date}</span>
                        </div>
                        <div class="gmail-recipient-row">
                            <span class="gmail-to-label">to</span>
                            <span class="gmail-to-value">${escapeHtml(to)}</span>
                            ${cc ? `<span class="gmail-cc-label">cc</span><span class="gmail-cc-value">${escapeHtml(cc)}</span>` : ''}
                            <button class="gmail-details-toggle" onclick="this.closest('.gmail-header').querySelector('.gmail-details-expanded').classList.toggle('show')">
                                <i class="fas fa-chevron-down"></i>
                            </button>
                        </div>
                        <div class="gmail-details-expanded">
                            <div class="gmail-detail-line"><strong>From:</strong> ${escapeHtml(from)}</div>
                            <div class="gmail-detail-line"><strong>To:</strong> ${escapeHtml(to)}</div>
                            ${cc ? `<div class="gmail-detail-line"><strong>Cc:</strong> ${escapeHtml(cc)}</div>` : ''}
                            <div class="gmail-detail-line"><strong>Date:</strong> ${date}</div>
                        </div>
                    </div>
                </div>
                <div class="gmail-body">
                    ${emailBodyHtml}
                </div>
                ${attachmentHtml}
                ${previewSection}
            </div>
        `;

        // Inject HTML content into sandboxed iframe after DOM is ready
        if (rawBody && rawBody.trim() !== '') {
            setTimeout(function() {
                const iframe = document.getElementById('gmailBodyFrame_' + email.id);
                if (iframe) {
                    const bodyContent = rawBody;
                    const resolvedBody = replaceCidReferences(bodyContent, allAttachments);

                    // Check if content is already a full HTML document
                    const isFullHtml = /<html[\s>]/i.test(resolvedBody);

                    let iframeDoc;
                    if (isFullHtml) {
                        // Full HTML document (e.g. Outlook/Word generated) — inject directly
                        // Patch in a small reset style to constrain images/tables inside the existing <head>
                        const patchStyle = '<style>body{margin:0;padding:16px 4px 24px;overflow-x:hidden;word-wrap:break-word;overflow-wrap:break-word;}img{max-width:100%!important;height:auto!important;}table{max-width:100%!important;}blockquote{border-left:3px solid #dadce0;margin:8px 0;padding:0 12px;}</style>';
                        if (/<head[\s>]/i.test(resolvedBody)) {
                            iframeDoc = resolvedBody.replace(/(<head[^>]*>)/i, '$1' + patchStyle);
                        } else {
                            iframeDoc = resolvedBody.replace(/(<html[^>]*>)/i, '$1<head>' + patchStyle + '</head>');
                        }
                    } else {
                        // HTML fragment — wrap in a clean document shell
                        iframeDoc = '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>' +
                            'body{margin:0;padding:16px 0 24px;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;font-size:14px;line-height:1.6;color:#202124;word-wrap:break-word;overflow-wrap:break-word;}' +
                            'img{max-width:100%;height:auto;}' +
                            'a{color:#1a73e8;text-decoration:none;}' +
                            'a:hover{text-decoration:underline;}' +
                            'table{max-width:100%;border-collapse:collapse;}' +
                            'pre,code{white-space:pre-wrap;word-wrap:break-word;max-width:100%;}' +
                            'blockquote{margin:8px 0;padding:0 12px;border-left:3px solid #dadce0;color:#5f6368;}' +
                            '</style></head><body>' + resolvedBody + '</body></html>';
                    }

                    iframe.srcdoc = iframeDoc;

                    // Auto-resize iframe to content height
                    iframe.onload = function() {
                        try {
                            const doc = iframe.contentDocument || iframe.contentWindow.document;
                            const contentHeight = doc.documentElement.scrollHeight || doc.body.scrollHeight;
                            iframe.style.height = Math.max(contentHeight + 20, 100) + 'px';
                        } catch (e) {
                            iframe.style.height = '500px';
                        }
                    };
                }
            }, 50);
        }
    }

    /**
     * Generate a consistent color from a string (for avatar)
     */
    function stringToColor(str) {
        if (!str) return '#1a73e8';
        let hash = 0;
        for (let i = 0; i < str.length; i++) {
            hash = str.charCodeAt(i) + ((hash << 5) - hash);
        }
        const colors = ['#1a73e8','#ea4335','#34a853','#fbbc04','#e37400','#a142f4','#24c1e0','#f538a0','#1e8e3e','#d93025','#5f6368','#185abc'];
        return colors[Math.abs(hash) % colors.length];
    }

    /**
     * Replace cid: references in email HTML with actual preview URLs for inline attachments
     */
    function replaceCidReferences(htmlContent, attachments) {
        if (!htmlContent || !attachments || attachments.length === 0) {
            return htmlContent;
        }
        
        // Create a map of content_id to attachment for quick lookup
        const cidMap = {};
        attachments.forEach(att => {
            if (!att.id) return; // Skip if no attachment ID
            
            // Always add filename to map (case-insensitive) as fallback
            if (att.filename) {
                const filenameKey = att.filename.toLowerCase();
                cidMap[filenameKey] = att;
                // Also try without extension
                const filenameWithoutExt = filenameKey.replace(/\.[^.]+$/, '');
                if (filenameWithoutExt !== filenameKey) {
                    cidMap[filenameWithoutExt] = att;
                }
            }
            
            // If content_id exists, add it to map (normalized)
            if (att.content_id) {
                // Normalize content_id (remove < > brackets if present)
                const normalizedCid = att.content_id.replace(/^<|>$/g, '').trim();
                if (normalizedCid) {
                    cidMap[normalizedCid.toLowerCase()] = att;
                }
            }
        });
        
        // Replace cid: references in img src attributes
        // Pattern: cid:filename or cid:<content-id>
        htmlContent = htmlContent.replace(/src=["']cid:([^"'>]+)["']/gi, (match, cidValue) => {
            // Remove any brackets and normalize
            const normalizedCid = cidValue.replace(/^<|>$/g, '').trim().toLowerCase();
            
            // Try to find matching attachment
            let attachment = cidMap[normalizedCid];
            
            // If not found, try with the original value
            if (!attachment) {
                attachment = cidMap[cidValue.toLowerCase()];
            }
            
            // Only rewrite when file exists in storage (avoids console 404 spam)
            if (attachment && attachment.id && attachment.s3_key) {
                const previewUrl = crmUrl('/mail-attachments/' + attachment.id + '/preview');
                return `src="${previewUrl}"`;
            }
            
            // If not found, return original (broken image will show)
            return match;
        });
        
        // Also handle background-image CSS with cid: references
        htmlContent = htmlContent.replace(/background-image:\s*url\(["']?cid:([^"')]+)["']?\)/gi, (match, cidValue) => {
            const normalizedCid = cidValue.replace(/^<|>$/g, '').trim().toLowerCase();
            let attachment = cidMap[normalizedCid] || cidMap[cidValue.toLowerCase()];
            
            if (attachment && attachment.id && attachment.s3_key) {
                const previewUrl = crmUrl('/mail-attachments/' + attachment.id + '/preview');
                return `background-image: url("${previewUrl}")`;
            }
            
            return match;
        });
        
        return htmlContent;
    }

    /**
     * Clean recipient strings by removing Python object representations
     */
    function cleanRecipients(recipientString) {
        if (!recipientString) return '';
        
        // Split by comma or semicolon (Outlook .msg uses semicolons)
        const recipients = recipientString.split(/[,;]/);
        
        // Filter out invalid recipients (Python object strings, malformed addresses)
        const validRecipients = recipients
            .map(r => r.trim())
            .filter(r => {
                // Remove entries that look like Python object representations
                if (r.includes('<extract_msg.') || r.includes('object at 0x')) {
                    return false;
                }
                // Remove entries that look like raw object references
                if (r.includes('Recipient') && r.includes('0x')) {
                    return false;
                }
                // Keep only entries that look like valid email addresses or names
                return r.length > 0 && !r.startsWith('<') && !r.includes('0x');
            });
        
        // Return cleaned recipient list or a placeholder if none are valid
        return validRecipients.length > 0 ? validRecipients.join(', ') : '';
    }

    /**
     * Escape HTML to prevent XSS
     */
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, m => map[m]);
    }

    // =========================================================================
    // Pagination
    // =========================================================================

    function initializePagination() {
        const prevBtn = document.getElementById('prevBtn');
        const nextBtn = document.getElementById('nextBtn');

        if (prevBtn) {
            prevBtn.addEventListener('click', function() {
                if (currentPage > 1) {
                    currentPage--;
                    loadEmailsFromServer();
                }
            });
        }

        if (nextBtn) {
            nextBtn.addEventListener('click', function() {
                if (currentPage < lastPage) {
                    currentPage++;
                    loadEmailsFromServer();
                }
            });
        }
    }

    // =========================================================================
    // Context Menu Management
    // =========================================================================

    let currentContextEmail = null; // Store email object for context menu actions

    /**
     * Resolve From email for compose modal signature lookup.
     */
    function getComposeFromEmail() {
        const fromSelect = document.querySelector('#emailmodal select.email-from-sendgrid, form[name="sendmail"] select.email-from-sendgrid');
        let fromEmail = (fromSelect && fromSelect.value) ? fromSelect.value.trim() : '';
        if (!fromEmail && window.__crmDefaultFromEmail) {
            fromEmail = window.__crmDefaultFromEmail.trim();
        }
        return fromEmail;
    }

    /**
     * Staff email signature for the selected From address.
     */
    function getStaffSignatureForComposeFrom() {
        const signatures = window.__crmStaffEmailSignatures || {};
        const fromEmail = getComposeFromEmail();
        if (fromEmail) {
            const matched = signatures[fromEmail.toLowerCase()];
            if (matched) {
                return matched;
            }
        }
        if (window.__crmCurrentUserSignature) {
            return window.__crmCurrentUserSignature;
        }
        const emailModal = document.getElementById('emailmodal');
        if (emailModal) {
            return emailModal.getAttribute('data-staff-signature') || '';
        }
        return '';
    }

    /**
     * Fetch the latest staff signature from the server (always fresh from DB).
     * @param {boolean} preferLoggedInStaff When true, use logged-in staff only (reply/forward).
     */
    async function fetchStaffSignatureForCompose(preferLoggedInStaff) {
        if (typeof window.crmFetchStaffSignature === 'function') {
            if (preferLoggedInStaff) {
                return (await window.crmFetchStaffSignature()).trim();
            }
            const fromEmail = getComposeFromEmail();
            return (await window.crmFetchStaffSignature(fromEmail)).trim();
        }
        if (preferLoggedInStaff && window.__crmCurrentUserSignature) {
            return String(window.__crmCurrentUserSignature).trim();
        }
        return (getStaffSignatureForComposeFrom() || '').trim();
    }

    /**
     * Append staff signature below quoted reply/forward content.
     */
    function prependStaffSignatureToMessage(message, signature) {
        const sig = (signature || getStaffSignatureForComposeFrom() || '').trim();
        if (!sig) {
            return message;
        }
        const quotedHtml = String(message || '').replace(/\n/g, '<br>');
        return quotedHtml + '<br><br>' + sig;
    }

    /**
     * Prepend staff signature using a fresh server lookup.
     */
    async function prependStaffSignatureToMessageAsync(message) {
        const signature = await fetchStaffSignatureForCompose(true);
        return prependStaffSignatureToMessage(message, signature);
    }

    /**
     * Insert staff signature when opening a new compose with an empty message body.
     */
    async function insertStaffSignatureForNewCompose() {
        const emailModal = document.getElementById('emailmodal');
        if (emailModal && emailModal.dataset.signaturePrefill === 'skip') {
            return;
        }

        const signature = await fetchStaffSignatureForCompose();
        if (!signature) {
            return;
        }

        const textarea = document.getElementById('compose_email_message');
        const editor = typeof tinymce !== 'undefined' ? tinymce.get('compose_email_message') : null;
        const current = editor ? editor.getContent().trim() : (textarea ? textarea.value.trim() : '');
        if (current) {
            return;
        }

        const content = signature + '<br><br>';
        if (editor) {
            editor.setContent(content);
        } else if (textarea) {
            textarea.value = content;
        }
    }

    /**
     * Format reply subject (add "Re:" prefix if not already present)
     */
    function formatReplySubject(originalSubject) {
        if (!originalSubject) return 'Re:';
        const subject = originalSubject.trim();
        if (subject.toLowerCase().startsWith('re:')) {
            return subject;
        }
        return 'Re: ' + subject;
    }

    /**
     * Format forward subject (add "Fwd:" prefix if not already present)
     */
    function formatForwardSubject(originalSubject) {
        if (!originalSubject) return 'Fwd:';
        const subject = originalSubject.trim();
        if (subject.toLowerCase().startsWith('fwd:') || subject.toLowerCase().startsWith('fw:')) {
            return subject;
        }
        return 'Fwd: ' + subject;
    }

    /**
     * Format quoted message for reply/forward
     */
    function formatQuotedMessage(email, isForward = false) {
        const from = email.from_mail || 'Unknown';
        const to = cleanRecipients(email.to_mail) || 'Unknown';
        const cc = cleanRecipients(email.cc);
        const date = formatDate(getEmailDate(email));
        const subject = email.subject || '(No subject)';
        const message = email.text_preview || '(No content)';
        
        let quotedText = '';
        
        if (isForward) {
            // Forward format with headers
            quotedText = '\n\n---------- Forwarded message ----------\n';
            quotedText += 'From: ' + from + '\n';
            quotedText += 'To: ' + to + '\n';
            if (cc) {
                quotedText += 'Cc: ' + cc + '\n';
            }
            quotedText += 'Date: ' + date + '\n';
            quotedText += 'Subject: ' + subject + '\n\n';
        } else {
            // Reply format (simpler)
            quotedText = '\n\n';
        }
        
        // Add original message with quote markers
        quotedText += 'On ' + date + ', ' + from + ' wrote:\n';
        quotedText += '> ' + message.replace(/\n/g, '\n> ');
        
        return quotedText;
    }

    /**
     * Extract email address from a string (handles "Name <email@domain.com>" format)
     */
    function extractEmailAddress(emailString) {
        if (!emailString) return '';
        
        // Try to extract email from angle brackets
        const match = emailString.match(/<([^>]+)>/);
        if (match) {
            return match[1].trim();
        }
        
        // If no brackets, check if it's a valid email
        if (emailString.includes('@')) {
            return emailString.trim();
        }
        
        return emailString.trim();
    }

    /**
     * Get current matter ID from the matter dropdown
     */
    function getCurrentMatterIdFromDropdown() {
        const matterDropdown = document.getElementById('sel_matter_id_client_detail');
        if (matterDropdown && matterDropdown.value) {
            return matterDropdown.value;
        }
        // Fallback: try to get from email interface container
        return getMatterId();
    }

    /**
     * Open compose modal and populate fields
     */
    function openComposeModal(data) {
        const modal = document.getElementById('emailmodal');
        if (!modal) {
            showNotification('Compose email modal not found. Please ensure you are on the client detail page.', 'error');
            return;
        }

        modal.dataset.signaturePrefill = (data.message && String(data.message).trim()) ? 'skip' : 'allow';

        // Always set matter ID - use provided one or get from dropdown
        const matterIdInput = document.getElementById('compose_client_matter_id');
        if (matterIdInput) {
            const matterId = data.matterId || getCurrentMatterIdFromDropdown();
            if (matterId) {
                matterIdInput.value = matterId;
            }
        }

        // Set subject
        const subjectInput = document.getElementById('compose_email_subject');
        if (subjectInput && data.subject) {
            subjectInput.value = data.subject;
        }

        // Set message (for TinyMCE editor)
        const messageTextarea = document.querySelector('#compose_email_message');
        if (messageTextarea && data.message) {
            // Wait for modal to be fully shown before setting TinyMCE content
            const setMessageContent = () => {
                // If TinyMCE is initialized, update it
                if (typeof tinymce !== 'undefined' && tinymce.get('compose_email_message')) {
                    try {
                        tinymce.get('compose_email_message').setContent(data.message);
                    } catch (e) {
                        // If TinyMCE not ready, set value directly
                        messageTextarea.value = data.message;
                    }
                } else {
                    // Set the value directly if TinyMCE not initialized
                    messageTextarea.value = data.message;
                }
            };
            
            // If modal is already shown, set immediately, otherwise wait
            if (modal.classList.contains('show') || modal.style.display === 'block') {
                setTimeout(setMessageContent, 200);
            } else {
                // Wait for modal to be shown
                modal.addEventListener('shown.bs.modal', setMessageContent, { once: true });
                if (typeof jQuery !== 'undefined') {
                    jQuery(modal).on('shown.bs.modal', setMessageContent);
                }
            }
        }

        // Set "To" field (Tom Select)
        if (data.to && data.to.length > 0) {
            const toSelect = document.querySelector('select[name="email_to[]"]');
            if (toSelect && typeof jQuery !== 'undefined') {
                const setToField = () => {
                    // Wait for Tom Select on the compose modal To field (initialized on client detail)
                    setTimeout(() => {
                        const emailAddresses = data.to.map(email => extractEmailAddress(email)).filter(addr => addr);
                        if (!emailAddresses.length) return;

                        const ts = toSelect.tomselect;
                        if (ts) {
                            ts.clear(true);
                            emailAddresses.forEach(function (addr) {
                                var key = String(addr);
                                if (!ts.options[key]) {
                                    ts.addOption({ id: key, name: key, email: key });
                                }
                                ts.addItem(key, true);
                            });
                            return;
                        }

                        // Native fallback if Tom Select is not ready
                        jQuery(toSelect).val(null).trigger('change');
                        emailAddresses.forEach(function (emailAddr) {
                            let option = Array.from(toSelect.options).find(opt => opt.value === emailAddr || opt.text === emailAddr);
                            if (!option) {
                                option = new Option(emailAddr, emailAddr, true, true);
                                toSelect.add(option);
                            } else {
                                option.selected = true;
                            }
                        });
                        jQuery(toSelect).val(emailAddresses).trigger('change');
                    }, 200);
                };
                
                // If modal is already shown, set immediately, otherwise wait
                if (modal.classList.contains('show') || modal.style.display === 'block') {
                    setToField();
                } else {
                    // Wait for modal to be shown
                    modal.addEventListener('shown.bs.modal', setToField, { once: true });
                    if (typeof jQuery !== 'undefined') {
                        jQuery(modal).on('shown.bs.modal', setToField);
                    }
                }
            }
        }

        // Open modal using Bootstrap
        if (typeof jQuery !== 'undefined') {
            jQuery(modal).modal('show');
        } else if (typeof bootstrap !== 'undefined') {
            const bsModal = new bootstrap.Modal(modal);
            bsModal.show();
        } else {
            // Fallback: just show the modal
            modal.style.display = 'block';
            modal.classList.add('show');
        }
    }

    /**
     * Handle Reply action
     */
    async function handleReply(email) {
        if (!email) {
            showNotification('No email selected for reply', 'error');
            return;
        }

        // Extract sender email for "To" field
        const senderEmail = extractEmailAddress(email.from_mail);
        if (!senderEmail) {
            showNotification('Could not extract sender email address', 'error');
            return;
        }

        // Get matter ID
        const matterId = getMatterId();

        // Format subject
        const replySubject = formatReplySubject(email.subject);

        // Format message with quoted original and fresh staff signature
        const replyMessage = await prependStaffSignatureToMessageAsync(formatQuotedMessage(email, false));

        // Open compose modal with reply data
        openComposeModal({
            to: [senderEmail],
            subject: replySubject,
            message: replyMessage,
            matterId: matterId
        });

        showNotification('Reply email opened', 'info');
    }

    /**
     * Handle Forward action
     */
    async function handleForward(email) {
        if (!email) {
            showNotification('No email selected for forward', 'error');
            return;
        }

        // Get matter ID
        const matterId = getMatterId();

        // Format subject
        const forwardSubject = formatForwardSubject(email.subject);

        // Format message with forwarded content and fresh staff signature
        const forwardMessage = await prependStaffSignatureToMessageAsync(formatQuotedMessage(email, true));

        // Open compose modal with forward data (no "To" pre-filled)
        openComposeModal({
            to: [],
            subject: forwardSubject,
            message: forwardMessage,
            matterId: matterId
        });

        showNotification('Forward email opened', 'info');
    }

    /**
     * Handle Delete email action (admin only - option shown based on server-side check)
     */
    async function handleDeleteEmail(email) {
        if (!email || !email.id) {
            showNotification('No email selected for delete', 'error');
            return;
        }

        const attachmentCount = Array.isArray(email.attachments) ? email.attachments.length : 0;
        const confirmDelete = typeof window.showEmailDeleteConfirm === 'function'
            ? await window.showEmailDeleteConfirm({
                subject: email.subject || '(No subject)',
                fromMail: email.from_mail || 'Unknown sender',
                attachmentCount: attachmentCount
            })
            : (window.confirm('Delete this email and its attachments?') && window.confirm('Final confirmation: permanently delete this email?'));

        if (!confirmDelete) {
            return;
        }

        try {
            const payload = {};
            const matterId = getMatterId();
            const clientId = getClientId();
            if (matterId) {
                payload.client_matter_id = matterId;
            }
            if (clientId) {
                payload.client_id = clientId;
            }

            // POST avoids 403 from some proxies/WAFs that block HTTP DELETE.
            const response = await fetch(crmUrl('/email-logs/' + email.id + '/delete'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken()
                },
                body: JSON.stringify(payload)
            });

            const data = await response.json().catch(() => ({}));

            if (response.ok && data.success) {
                showNotification('Email deleted successfully', 'success');
                // Reset content pane to placeholder when the viewed email was deleted
                const emailContentView = document.getElementById('emailContentView');
                const emailContentPlaceholder = document.getElementById('emailContentPlaceholder');
                if (emailContentView && emailContentPlaceholder) {
                    emailContentView.style.display = 'none';
                    emailContentPlaceholder.style.display = 'block';
                }
                loadEmailsFromServer();
            } else {
                const message = data.message || `Failed to delete email (${response.status})`;
                showNotification(message, 'error');
            }
        } catch (error) {
            console.error('Error deleting email:', error);
            showNotification('Failed to delete email: ' + error.message, 'error');
        }
    }

    /**
     * Show context menu at specified coordinates
     */
    function showContextMenu(x, y, email) {
        const contextMenu = document.getElementById('emailContextMenu');
        const overlay = document.getElementById('contextMenuOverlay');
        
        if (!contextMenu || !overlay) return;
        
        // Store current email
        currentContextEmail = email;
        
        // Position menu
        contextMenu.style.display = 'block';
        contextMenu.style.left = x + 'px';
        contextMenu.style.top = y + 'px';
        
        // Show overlay
        overlay.style.display = 'block';
        
        // Adjust menu position if it goes off-screen
        setTimeout(() => {
            const rect = contextMenu.getBoundingClientRect();
            const windowWidth = window.innerWidth;
            const windowHeight = window.innerHeight;
            
            if (rect.right > windowWidth) {
                contextMenu.style.left = (x - rect.width) + 'px';
            }
            if (rect.bottom > windowHeight) {
                contextMenu.style.top = (y - rect.height) + 'px';
            }
        }, 0);
    }

    /**
     * Hide context menu
     */
    function hideContextMenu() {
        const contextMenu = document.getElementById('emailContextMenu');
        const submenu = document.getElementById('labelSubmenu');
        const overlay = document.getElementById('contextMenuOverlay');
        
        if (contextMenu) contextMenu.style.display = 'none';
        if (submenu) submenu.style.display = 'none';
        if (overlay) overlay.style.display = 'none';
        
        currentContextEmail = null;
    }

    /**
     * Show label submenu
     */
    function showLabelSubmenu() {
        const contextMenu = document.getElementById('emailContextMenu');
        const submenu = document.getElementById('labelSubmenu');
        const labelContent = document.getElementById('labelSubmenuContent');
        
        if (!submenu || !labelContent || !currentContextEmail) return;
        
        // Get context menu position before hiding it
        const rect = contextMenu.getBoundingClientRect();
        
        // Hide main context menu
        contextMenu.style.display = 'none';
        
        // Position submenu next to context menu
        submenu.style.display = 'block';
        submenu.style.left = (rect.right + 2) + 'px';
        submenu.style.top = rect.top + 'px';
        
        // Get current email labels
        const currentLabels = currentContextEmail.labels || [];
        const currentLabelIds = currentLabels.map(l => l.id);
        
        // Filter out already applied labels
        const filteredLabels = availableLabels.filter(label => {
            return !currentLabelIds.includes(label.id);
        });
        
        // Build label options HTML
        if (filteredLabels.length === 0) {
            labelContent.innerHTML = `
                <div class="submenu-empty">
                    <p>All available labels are already applied</p>
                </div>
            `;
        } else {
            labelContent.innerHTML = filteredLabels.map(label => {
                const isApplied = currentLabelIds.includes(label.id);
                const icon = label.icon || 'fas fa-tag';
                const color = label.color || '#3B82F6';
                
                return `
                    <div class="submenu-item ${isApplied ? 'applied' : ''}" 
                         data-label-id="${label.id}" 
                         data-label-name="${escapeHtml(label.name)}">
                        <span class="submenu-item-badge" style="background-color: ${color}20; border-color: ${color}; color: ${color}">
                            <i class="${icon}"></i>
                        </span>
                        <span class="submenu-item-text">${escapeHtml(label.name)}</span>
                        ${isApplied ? '<i class="fas fa-check submenu-item-check"></i>' : ''}
                    </div>
                `;
            }).join('');
            
            // Add click handlers
            labelContent.querySelectorAll('.submenu-item').forEach(item => {
                item.addEventListener('click', async function() {
                    const labelId = this.dataset.labelId;
                    const labelName = this.dataset.labelName;
                    const isApplied = this.classList.contains('applied');
                    
                    if (isApplied) {
                        // Already applied (shouldn't happen due to filter, but handle it)
                        return;
                    }
                    
                    // Apply label
                    const success = await applyLabel(currentContextEmail.id, labelId);
                    if (success) {
                        // Reload email list to show updated labels
                        loadEmailsFromServer();
                        hideContextMenu();
                    }
                });
            });
        }
        
        // Back button handler
        const backBtn = submenu.querySelector('.submenu-back');
        if (backBtn) {
            backBtn.onclick = function() {
                submenu.style.display = 'none';
                contextMenu.style.display = 'block';
            };
        }
        
        // Adjust submenu position if it goes off-screen
        setTimeout(() => {
            const submenuRect = submenu.getBoundingClientRect();
            const windowWidth = window.innerWidth;
            
            if (submenuRect.right > windowWidth) {
                submenu.style.left = (rect.left - submenuRect.width) + 'px';
            }
        }, 0);
    }

    /**
     * Initialize context menu handlers
     */
    function initializeContextMenu() {
        const contextMenu = document.getElementById('emailContextMenu');
        const overlay = document.getElementById('contextMenuOverlay');
        
        if (!contextMenu || !overlay) return;
        
        // Handle menu item clicks
        contextMenu.addEventListener('click', function(e) {
            const item = e.target.closest('.context-menu-item');
            if (!item) return;
            
            const action = item.dataset.action;
            
            switch (action) {
                case 'apply-label':
                    showLabelSubmenu();
                    break;
                case 'reply':
                    if (currentContextEmail) {
                        handleReply(currentContextEmail);
                    }
                    hideContextMenu();
                    break;
                case 'forward':
                    if (currentContextEmail) {
                        handleForward(currentContextEmail);
                    }
                    hideContextMenu();
                    break;
                case 'delete':
                    if (currentContextEmail) {
                        handleDeleteEmail(currentContextEmail);
                    }
                    hideContextMenu();
                    break;
                default:
                    hideContextMenu();
            }
        });
        
        // Close menu when clicking overlay or outside
        overlay.addEventListener('click', hideContextMenu);
        
        // Close menu on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                hideContextMenu();
            }
        });
        
        // Close menu on scroll
        document.addEventListener('scroll', hideContextMenu, true);
    }

    // =========================================================================
    // Sender Management
    // =========================================================================

    /**
     * Fetch all unique senders for the current client/matter
     */
    window.fetchSenders = async function() {
        const clientId = getClientId();
        const matterId = getMatterId();
        
        if (!clientId) return;

        try {
            const response = await fetch(crmUrl('/clients/email-senders'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken()
                },
                body: JSON.stringify({
                    client_id: clientId,
                    client_matter_id: matterId || ''
                })
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();
            if (data.success && Array.isArray(data.senders)) {
                availableSenders = data.senders;
                populateSenderFilter();
            }
        } catch (error) {
            console.error('Error fetching senders:', error);
        }
    };

    /**
     * Populate sender filter dropdown
     */
    function populateSenderFilter() {
        const senderFilter = document.getElementById('senderFilter');
        if (!senderFilter) return;

        // Clear existing options (except "All Senders")
        while (senderFilter.options.length > 1) {
            senderFilter.remove(1);
        }

        // Add sender options
        availableSenders.forEach(sender => {
            if (sender && sender.trim() !== '') {
                const option = document.createElement('option');
                option.value = sender;
                option.textContent = sender;
                senderFilter.appendChild(option);
            }
        });
    }

    // =========================================================================
    // Label Management
    // =========================================================================

    /**
     * Fetch all labels from API
     */
    async function fetchLabels() {
        try {
            const response = await fetch(crmUrl('/email-labels'), {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken()
                }
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();
            if (data.success && Array.isArray(data.labels)) {
                availableLabels = data.labels;
                populateLabelFilter();
            }
        } catch (error) {
            console.error('Error fetching labels:', error);
        }
    }

    /**
     * Populate label filter dropdown
     */
    function populateLabelFilter() {
        const labelFilter = document.getElementById('labelFilter');
        if (!labelFilter) return;

        // Clear existing options (except "All Labels")
        while (labelFilter.options.length > 1) {
            labelFilter.remove(1);
        }

        // Add label options
        availableLabels.forEach(label => {
            const option = document.createElement('option');
            option.value = label.id;
            option.textContent = label.name;
            labelFilter.appendChild(option);
        });
    }

    /**
     * Label creation removed - labels are now managed in Admin Console
     * Use /adminconsole/features/email-labels to create/edit labels
     * Frontend only handles filtering and applying existing labels
     */

    /**
     * Apply label to email
     */
    async function applyLabel(mailReportId, labelId) {
        try {
            const response = await fetch(crmUrl('/email-labels/apply'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken()
                },
                body: JSON.stringify({ mail_report_id: mailReportId, label_id: labelId })
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();
            if (data.success) {
                showNotification('Label applied successfully', 'success');
                return true;
            } else {
                throw new Error(data.message || 'Failed to apply label');
            }
        } catch (error) {
            console.error('Error applying label:', error);
            showNotification('Error applying label: ' + error.message, 'error');
            return false;
        }
    }

    /**
     * Remove label from email
     */
    async function removeLabel(mailReportId, labelId) {
        try {
            const response = await fetch(crmUrl('/email-labels/remove'), {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken()
                },
                body: JSON.stringify({ mail_report_id: mailReportId, label_id: labelId })
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();
            if (data.success) {
                showNotification('Label removed successfully', 'success');
                return true;
            } else {
                throw new Error(data.message || 'Failed to remove label');
            }
        } catch (error) {
            console.error('Error removing label:', error);
            showNotification('Error removing label: ' + error.message, 'error');
            return false;
        }
    }

    // =========================================================================
    // Attachment Handling
    // =========================================================================

    /**
     * Download individual attachment
     */
    async function downloadAttachment(attachmentId, filename) {
        try {
            const response = await fetch(crmUrl('/mail-attachments/' + attachmentId + '/download'), {
                method: 'GET',
                headers: {
                    'Accept': 'application/octet-stream'
                }
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const blob = await response.blob();
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);

            showNotification(`Downloaded: ${filename}`, 'success');
        } catch (error) {
            console.error('Error downloading attachment:', error);
            showNotification('Error downloading attachment: ' + error.message, 'error');
        }
    }

    /**
     * Download all attachments as ZIP
     */
    async function downloadAllAttachments(mailReportId, emailSubject) {
        try {
            const response = await fetch(crmUrl('/mail-attachments/email/' + mailReportId + '/download-all'), {
                method: 'GET',
                headers: {
                    'Accept': 'application/octet-stream'
                }
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const blob = await response.blob();
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            const sanitizedSubject = sanitizeFilename(emailSubject || 'email');
            a.download = `${sanitizedSubject}_attachments.zip`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);

            showNotification('Attachments downloaded successfully', 'success');
        } catch (error) {
            console.error('Error downloading attachments:', error);
            showNotification('Error downloading attachments: ' + error.message, 'error');
        }
    }

    /**
     * Preview attachment
     */
    async function previewAttachment(attachmentId, filename) {
        try {
            const previewUrl = crmUrl('/mail-attachments/' + attachmentId + '/preview');
            const modal = document.getElementById('attachmentPreviewModal');
            const frame = document.getElementById('previewFrame');
            const filenameEl = document.getElementById('previewFileName');

            if (modal && frame && filenameEl) {
                filenameEl.textContent = filename;
                frame.src = previewUrl;
                modal.style.display = 'flex';
            }
        } catch (error) {
            console.error('Error previewing attachment:', error);
            showNotification('Error previewing attachment: ' + error.message, 'error');
        }
    }

    // =========================================================================
    // Initialization
    // =========================================================================

    // Initialize pagination on load
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializePagination);
    } else {
        initializePagination();
    }

    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeNewFeatures);
    } else {
        initializeNewFeatures();
    }

    /**
     * Initialize new filter and modal features
     */
    function initializeNewFeatures() {
        // Fetch labels on load
        fetchLabels();

        // Initialize context menu
        initializeContextMenu();

        // Mail type filter (Inbox/Sent)
        const mailTypeFilter = document.getElementById('mailTypeFilter');
        if (mailTypeFilter) {
            mailTypeFilter.addEventListener('change', function() {
                currentMailType = this.value;
                loadEmailsFromServer();
            });
        }

        // Label filter
        const labelFilter = document.getElementById('labelFilter');
        if (labelFilter) {
            labelFilter.addEventListener('change', function() {
                currentLabelId = this.value;
            });
        }

        // Apply button removed - all filters auto-apply:
        // - Search auto-applies as you type (debounced)
        // - Label filter auto-applies on change
        // - Mail type filter auto-applies on change

        // Label creation removed - now managed in Admin Console
        // Labels can only be created via /adminconsole/features/email-labels

        // Preview modal close
        const closePreviewBtn = document.getElementById('closePreviewBtn');
        const previewOverlay = document.getElementById('previewOverlay');
        if (closePreviewBtn) {
            closePreviewBtn.addEventListener('click', hidePreviewModal);
        }
        if (previewOverlay) {
            previewOverlay.addEventListener('click', hidePreviewModal);
        }

        // Initialize attachment handlers
        initializeAttachmentHandlers();

        // Auto-set matter ID when compose modal opens (for all email composes)
        const composeModal = document.getElementById('emailmodal');
        if (composeModal) {
            const applyComposeSignature = function() {
                setTimeout(function() {
                    insertStaffSignatureForNewCompose();
                }, 250);
            };

            // Listen for modal show event (Bootstrap 4)
            if (typeof jQuery !== 'undefined') {
                jQuery(composeModal).on('show.bs.modal', function() {
                    const matterIdInput = document.getElementById('compose_client_matter_id');
                    if (matterIdInput && !matterIdInput.value) {
                        // Only set if not already set (to preserve reply/forward matter ID)
                        const matterId = getCurrentMatterIdFromDropdown();
                        if (matterId) {
                            matterIdInput.value = matterId;
                        }
                    }
                });
                jQuery(composeModal).on('shown.bs.modal', applyComposeSignature);
            }
            // Also listen for native modal show event
            composeModal.addEventListener('show.bs.modal', function() {
                const matterIdInput = document.getElementById('compose_client_matter_id');
                if (matterIdInput && !matterIdInput.value) {
                    // Only set if not already set (to preserve reply/forward matter ID)
                    const matterId = getCurrentMatterIdFromDropdown();
                    if (matterId) {
                        matterIdInput.value = matterId;
                    }
                }
            });
            composeModal.addEventListener('shown.bs.modal', applyComposeSignature);
            const resetComposeSignaturePrefill = function() {
                composeModal.dataset.signaturePrefill = 'allow';
            };
            if (typeof jQuery !== 'undefined') {
                jQuery(composeModal).on('hidden.bs.modal', resetComposeSignaturePrefill);
            }
            composeModal.addEventListener('hidden.bs.modal', resetComposeSignaturePrefill);
        }
    }

    /**
     * Event delegation for attachment buttons
     * Handles all attachment-related clicks
     */
    function initializeAttachmentHandlers() {
        // Single delegated listener for all attachment actions
        document.addEventListener('click', function(e) {
            const target = e.target.closest('button');
            if (!target) return;

            // Download individual attachment
            if (target.classList.contains('download-attachment-btn')) {
                e.preventDefault();
                const attachmentId = target.dataset.attachmentId;
                const filename = target.dataset.filename;
                
                if (attachmentId && filename) {
                    // Disable button during download
                    const originalHtml = target.innerHTML;
                    target.disabled = true;
                    target.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Downloading...';
                    
                    downloadAttachment(attachmentId, filename).finally(() => {
                        target.disabled = false;
                        target.innerHTML = originalHtml;
                    });
                }
            }

            // Preview attachment
            if (target.classList.contains('preview-attachment-btn')) {
                e.preventDefault();
                const attachmentId = target.dataset.attachmentId;
                const filename = target.dataset.filename;
                
                if (attachmentId && filename) {
                    previewAttachment(attachmentId, filename);
                }
            }

            // Download all attachments as ZIP
            if (target.classList.contains('download-all-btn')) {
                e.preventDefault();
                const mailReportId = target.dataset.mailReportId;
                const emailSubject = target.dataset.emailSubject;
                
                if (mailReportId) {
                    // Disable button during download
                    const originalHtml = target.innerHTML;
                    target.disabled = true;
                    target.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating ZIP...';
                    
                    downloadAllAttachments(mailReportId, emailSubject).finally(() => {
                        target.disabled = false;
                        target.innerHTML = originalHtml;
                    });
                }
            }
        });
    }

    /**
     * Label creation functions removed - labels are now managed in Admin Console
     * Navigate to /adminconsole/features/email-labels to create/edit labels
     */

    /**
     * Hide preview modal
     */
    function hidePreviewModal() {
        const modal = document.getElementById('attachmentPreviewModal');
        const frame = document.getElementById('previewFrame');
        if (modal && frame) {
            modal.style.display = 'none';
            frame.src = ''; // Stop loading
        }
    }

    // Add CSS animations
    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes slideOut {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }
    `;
    document.head.appendChild(style);

})();

