// Outlook-style Email Interface Logic

function crmOutlookSanitizeUploadFilename(filename, preferredExtension) {
    if (typeof window.crmSanitizeEmailUploadFilename === 'function') {
        return window.crmSanitizeEmailUploadFilename(filename, preferredExtension);
    }
    if (!filename || typeof filename !== 'string') {
        return 'email_' + Date.now() + '.' + (preferredExtension || 'eml');
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

function crmOutlookEmailUpload403Message(responseText, status) {
    if (typeof window.crmEmailUpload403Message === 'function') {
        return window.crmEmailUpload403Message(responseText, status);
    }
    if (status !== 403) {
        return null;
    }
    var text = responseText || '';
    var isHtml = /<html[\s>]/i.test(text) || /<!DOCTYPE/i.test(text);
    if (isHtml || text.indexOf('Forbidden') !== -1) {
        return 'The server blocked this upload (security filter). Rename files to remove special characters such as apostrophes and try again.';
    }
    return 'Access denied. You may not have permission to upload emails for this client.';
}

document.addEventListener('DOMContentLoaded', function() {
    let currentPage = 1;
    let currentFolder = 'inbox'; // inbox, sent, outbox, unassigned
    let emails = [];
    let selectedEmailId = null;

    // Elements
    const outlookContainer = document.getElementById('outlookContainer');
    const appTimezone = (outlookContainer && outlookContainer.dataset.appTimezone) || 'Australia/Melbourne';
    const unassignedOnly = !!(outlookContainer && outlookContainer.getAttribute('data-unassigned-only') === '1');
    const defaultFolder = (outlookContainer && outlookContainer.getAttribute('data-default-folder')) || 'inbox';
    const folderItems = document.querySelectorAll('.folder-item');
    const emailListContainer = document.getElementById('emailList');
    const readingPane = document.getElementById('readingPane');
    const emptyState = document.getElementById('emptyState');
    const searchInput = document.getElementById('searchInput');
    const labelFilter = document.getElementById('labelFilter');
    const senderFilter = document.getElementById('senderFilter');
    const sortOrder = document.getElementById('sortOrder');
    const sendStatusFilter = document.getElementById('sendStatusFilter');
    const dateFromFilter = document.getElementById('dateFromFilter');
    const dateToFilter = document.getElementById('dateToFilter');
    const pageInfo = document.getElementById('pageInfo');
    const prevBtn = document.getElementById('prevBtn');
    const nextBtn = document.getElementById('nextBtn');
    
    // Compose Modal
    const composeModal = document.getElementById('composeModal');
    const composeTitle = document.getElementById('composeTitle');
    const toInput = document.getElementById('composeTo');
    const ccInput = document.getElementById('composeCc');
    const bccInput = document.getElementById('composeBcc');
    const subjectInput = document.getElementById('composeSubject');
    const composeReplyInput = document.getElementById('composeReplyInput');
    const composeQuoteWrap = document.getElementById('composeQuoteWrap');
    const composeQuotePanel = document.getElementById('composeQuotePanel');
    const composeQuoteFrame = document.getElementById('composeQuoteFrame');
    const composeQuoteToggle = document.getElementById('composeQuoteToggle');
    const composeQuoteToggleLabel = document.getElementById('composeQuoteToggleLabel');
    const composeSignatureWrap = document.getElementById('composeSignatureWrap');
    const composeSignatureFrame = document.getElementById('composeSignatureFrame');
    const composeFormatBar = document.getElementById('composeFormatBar');
    const btnSendEl = document.getElementById('btnSend');
    const btnResend = document.getElementById('btnResend');
    const btnAssignToClient = document.getElementById('btnAssignToClient');
    const btnSyncInbox = document.getElementById('btnSyncInbox');
    const syncRangeFilter = document.getElementById('syncRangeFilter');
    const syncMailboxFilter = document.getElementById('syncMailboxFilter');
    const assignEmailModal = document.getElementById('assignSyncedEmailModal');
    const assignClientSelect = document.getElementById('assignClientId');
    const assignMatterHiddenInput = document.getElementById('assignClientMatterId');
    const assignMatterList = document.getElementById('assignMatterList');
    const assignMatterPlaceholder = document.getElementById('assignMatterPlaceholder');
    const assignMatterLoading = document.getElementById('assignMatterLoading');
    const assignMatterPicker = document.getElementById('assignMatterPicker');
    const assignEmailLogIdInput = document.getElementById('assignEmailLogId');
    const assignEmailConfirmBtn = document.getElementById('assignEmailConfirmBtn');
    const assignEmailStatus = document.getElementById('assignEmailStatus');
    const assignEmailPreviewSubject = document.getElementById('assignEmailPreviewSubject');
    const assignEmailPreviewMeta = document.getElementById('assignEmailPreviewMeta');
    const assignMatterHint = document.getElementById('assignMatterHint');
    const assignMatterField = document.getElementById('assignMatterField');
    const assignClientField = document.getElementById('assignClientField');
    const assignStepClient = document.getElementById('assignStepClient');
    const assignStepMatter = document.getElementById('assignStepMatter');
    const assignStepConnector = document.getElementById('assignStepConnector');
    const assignEmailUrl = outlookContainer ? outlookContainer.getAttribute('data-assign-email-url') : '';
    const syncInboxUrl = outlookContainer ? outlookContainer.getAttribute('data-sync-inbox-url') : '';
    const syncStatusUrlBase = outlookContainer ? outlookContainer.getAttribute('data-sync-status-url') : '';
    const canSyncInbox = !!(outlookContainer && outlookContainer.getAttribute('data-can-sync-inbox') === '1');
    const canViewSyncedInbox = !!(outlookContainer && outlookContainer.getAttribute('data-can-view-synced-inbox') === '1');
    const canSelectSyncMailbox = !!(outlookContainer && outlookContainer.getAttribute('data-can-select-sync-mailbox') === '1');
    const mattersUrl = outlookContainer ? outlookContainer.getAttribute('data-matters-url') : '';
    const updateMailReadUrl = outlookContainer ? (outlookContainer.getAttribute('data-update-mail-read-url') || '') : '';
    const markAllReadUrl = outlookContainer ? (outlookContainer.getAttribute('data-mark-all-read-url') || '') : '';
    const markSyncedReadUrl = outlookContainer ? (outlookContainer.getAttribute('data-mark-synced-read-url') || '') : '';
    const btnMarkAllRead = document.getElementById('btnMarkAllRead');
    const btnMarkRead = document.getElementById('btnMarkRead');
    const syncedDateSummaryEl = document.getElementById('syncedDateSummary');
    const folderUnreadBadge = document.getElementById('folderUnreadBadge');
    const folderUnassignedUnreadBadge = document.getElementById('folderUnassignedUnreadBadge');
    const folderAssignedUnreadBadge = document.getElementById('folderAssignedUnreadBadge');
    let unreadCount = 0;
    let syncedDateSummary = null;
    let selectedEmail = null;

    let composeQuoteHtml = '';
    let composeSignatureHtml = '';

    // Initialize Data
    const baseUrl = outlookContainer ? outlookContainer.getAttribute('data-base-url') : '';
    const clientId = outlookContainer ? outlookContainer.getAttribute('data-client-id') : '';
    const matterId = outlookContainer ? outlookContainer.getAttribute('data-matter-id') : '';
    const authEmail = outlookContainer ? outlookContainer.getAttribute('data-auth-email') : '';
    let composeFromEmail = authEmail;
    let composeReplyToEmailId = null;
    let composeResendLogId = null;
    const staffSignatureUrl = outlookContainer ? (outlookContainer.getAttribute('data-staff-signature-url') || '') : '';
    const personalFolders = outlookContainer
        ? JSON.parse(outlookContainer.getAttribute('data-personal-folders') || '[]')
        : [];
    const matterFolders = outlookContainer
        ? JSON.parse(outlookContainer.getAttribute('data-matter-folders') || '[]')
        : [];

    function reportEmailUploadFailure(stage, details) {
        if (typeof window.crmLogEmailUploadFailure !== 'function') {
            return;
        }

        const payload = Object.assign({
            stage: stage,
            client_id: clientId,
            mail_type: currentFolder === 'sent' ? 'sent' : 'inbox'
        }, details || {});

        window.crmLogEmailUploadFailure(payload);
    }

    currentFolder = defaultFolder;
    loadEmails();
    updateOutboxFiltersVisibility();

    function updateOutboxFiltersVisibility() {
        const isOutbox = currentFolder === 'outbox';
        document.querySelectorAll('.list-filter-outbox').forEach(function (el) {
            el.hidden = !isOutbox;
        });
        if (labelFilter) {
            labelFilter.hidden = isOutbox;
        }
        if (senderFilter) {
            senderFilter.hidden = isOutbox;
        }
    }

    function resetComposeContext() {
        composeReplyToEmailId = null;
        composeResendLogId = null;
        if (btnSendEl) {
            btnSendEl.innerHTML = '<i class="fa-solid fa-paper-plane" aria-hidden="true"></i> Send';
        }
    }

    function renderSendStatusBadge(email) {
        const status = (email && email.send_status) ? String(email.send_status) : 'sent';
        if (currentFolder !== 'outbox' && status === 'sent') {
            return '';
        }
        const label = status.charAt(0).toUpperCase() + status.slice(1);
        return '<span class="email-status-badge email-status-badge--' + escapeHtml(status) + '">' + escapeHtml(label) + '</span>';
    }

    function emailHasCalendarIndicator(email) {
        if (!email) {
            return false;
        }
        if (email.has_calendar || email.has_calendar_invite) {
            return true;
        }
        if (email.calendar && email.calendar.has_calendar) {
            return true;
        }
        return hasCalendarAttachment(email);
    }

    function hasCalendarAttachment(email) {
        const attachments = getUserEmailAttachments(email);
        return attachments.some(function(att) {
            const name = String(att.filename || att.file_name || att.display_name || '').toLowerCase();
            const type = String(att.content_type || att.mime_type || '').toLowerCase();
            const ext = String(att.extension || '').toLowerCase();
            return ext === 'ics' || name.endsWith('.ics') || type.indexOf('text/calendar') !== -1;
        });
    }

    function formatCalendarEventType(eventType) {
        const labels = {
            hearing: 'Hearing',
            interview: 'Interview',
            walkin: 'Walk-in',
            appointment: 'Appointment',
            meeting: 'Meeting',
            court: 'Court',
            mention: 'Mention',
            tribunal: 'Tribunal'
        };
        return labels[eventType] || 'Event';
    }

    function renderCalendarListIndicator(email) {
        if (!emailHasCalendarIndicator(email)) {
            return '';
        }

        const calendar = email.calendar || {};
        const mergedCount = calendar.merged_count || 0;
        const pendingCount = calendar.pending_count || 0;
        const totalCount = calendar.count || (mergedCount + pendingCount) || 1;
        let title = 'Calendar event detected';
        if (mergedCount > 0) {
            title = mergedCount + ' event(s) added to calendar';
        } else if (pendingCount > 0) {
            title = 'Schedule detected — will merge when assigned to client';
        } else if (hasCalendarAttachment(email)) {
            title = 'Calendar invite attachment (.ics)';
        }

        const badge = mergedCount > 0
            ? '<span class="email-calendar-badge email-calendar-badge--merged">Calendar</span>'
            : '<span class="email-calendar-badge email-calendar-badge--detected">Schedule</span>';

        return '<span class="email-list-calendar" title="' + escapeHtml(title) + '" aria-label="' + escapeHtml(title) + '">'
            + '<i class="fa-solid fa-calendar-days"></i>'
            + badge
            + (totalCount > 1 ? '<span class="email-calendar-count">' + totalCount + '</span>' : '')
            + '</span>';
    }

    function renderReadingPaneCalendarBanner(email) {
        const bannerEl = document.getElementById('readCalendarBanner');
        if (!bannerEl) {
            return;
        }

        const calendar = email.calendar || {};
        const hasIndicator = emailHasCalendarIndicator(email);
        if (!hasIndicator) {
            bannerEl.hidden = true;
            bannerEl.innerHTML = '';
            return;
        }

        const events = Array.isArray(calendar.events) ? calendar.events : [];
        let html = '<div class="email-calendar-banner__header">'
            + '<i class="fa-solid fa-calendar-check" aria-hidden="true"></i>'
            + '<strong>Calendar</strong>'
            + '</div>';

        if (events.length > 0) {
            html += '<ul class="email-calendar-banner__list">';
            events.forEach(function(event) {
                const statusLabel = event.status === 'merged' ? 'Added to calendar' : 'Pending merge';
                const typeLabel = formatCalendarEventType(event.event_type || 'meeting');
                html += '<li>'
                    + '<span class="email-calendar-banner__type">' + escapeHtml(typeLabel) + '</span>'
                    + '<span class="email-calendar-banner__title">' + escapeHtml(event.event_title || 'Scheduled event') + '</span>'
                    + (event.starts_at ? '<span class="email-calendar-banner__when">' + escapeHtml(event.starts_at) + '</span>' : '')
                    + (event.location ? '<span class="email-calendar-banner__where">' + escapeHtml(event.location) + '</span>' : '')
                    + '<span class="email-calendar-banner__status email-calendar-banner__status--' + escapeHtml(event.status || 'merged') + '">' + escapeHtml(statusLabel) + '</span>'
                    + '</li>';
            });
            html += '</ul>';
        } else if (hasCalendarAttachment(email)) {
            html += '<p class="email-calendar-banner__message">This email includes a calendar invite attachment.</p>';
        } else {
            html += '<p class="email-calendar-banner__message">A schedule date was detected in this email.</p>';
        }

        bannerEl.hidden = false;
        bannerEl.innerHTML = html;
    }

    function renderSyncedClientBadge(email) {
        if (currentFolder !== 'assigned'
            && currentFolder !== 'unread'
            && currentFolder !== 'inbox'
            && email.sync_assignment_status !== 'auto_assigned'
            && email.sync_assignment_status !== 'manual_assigned') {
            return '';
        }

        const clientLabel = (email.client_name || '').trim()
            || (email.client_ref || '').trim()
            || (email.client_id ? ('Client #' + email.client_id) : '');
        if (! clientLabel) {
            return '';
        }

        const assignLabel = email.sync_assignment_status === 'manual_assigned' ? 'Manual' : 'Auto';
        return '<span class="email-client-badge" title="' + escapeHtml(assignLabel + ' assigned to client') + '">'
            + '<i class="fa-solid fa-user-check" aria-hidden="true"></i> '
            + escapeHtml(clientLabel)
            + '</span>';
    }

    function isEmailRead(email) {
        if (!email) {
            return false;
        }
        if (typeof email.is_read === 'boolean') {
            return email.is_read;
        }
        const value = email.mail_is_read;
        if (value === true || value === 1 || value === '1' || value === 't' || value === 'true') {
            return true;
        }
        if (value === false || value === 0 || value === '0' || value === 'f' || value === 'false') {
            return false;
        }
        return false;
    }

    function isEmailUnread(email) {
        return !isEmailRead(email);
    }

    function resetReadingPane() {
        selectedEmailId = null;
        readingPane.classList.remove('is-visible');
        emptyState.style.display = 'flex';
        const calendarBanner = document.getElementById('readCalendarBanner');
        if (calendarBanner) {
            calendarBanner.hidden = true;
            calendarBanner.innerHTML = '';
        }
    }

    function sortEmailsUnreadFirst(list) {
        const items = Array.isArray(list) ? list.slice() : [];
        const sortDir = sortOrder && sortOrder.value === 'asc' ? 1 : -1;

        items.sort(function(a, b) {
            const aUnread = isEmailUnread(a) ? 0 : 1;
            const bUnread = isEmailUnread(b) ? 0 : 1;
            if (aUnread !== bUnread) {
                return aUnread - bUnread;
            }

            const aDate = new Date(getEmailDate(a) || 0).getTime();
            const bDate = new Date(getEmailDate(b) || 0).getTime();
            if (isNaN(aDate) || isNaN(bDate)) {
                return 0;
            }

            return (aDate - bDate) * sortDir;
        });

        return items;
    }

    function updateMarkAllReadButtonVisibility() {
        if (!btnMarkAllRead) {
            return;
        }

        const isClientInbox = !!clientId
            && !!markAllReadUrl
            && unreadCount > 0
            && (currentFolder === 'inbox' || currentFolder === 'unread');

        const isSyncedFolder = !!markSyncedReadUrl
            && unreadCount > 0
            && (currentFolder === 'unassigned' || currentFolder === 'assigned');

        const canMarkAll = isClientInbox || isSyncedFolder;

        btnMarkAllRead.hidden = !canMarkAll;
        btnMarkAllRead.disabled = false;
    }

    function updateMarkReadButtonVisibility(email) {
        if (!btnMarkRead) {
            return;
        }
        btnMarkRead.hidden = !email || !isEmailUnread(email);
    }

    function isSyncedInboxFolder(folder) {
        return folder === 'unassigned' || folder === 'assigned';
    }

    function getAppTzDateKey(dateInput) {
        if (!dateInput) {
            return null;
        }
        const date = new Date(dateInput);
        if (isNaN(date.getTime())) {
            return null;
        }
        return new Intl.DateTimeFormat('en-CA', { timeZone: appTimezone }).format(date);
    }

    function parseEmailDateValue(email) {
        if (!email) {
            return null;
        }
        const raw = email.fetch_mail_sent_time || email.created_at || email.sent_at || email.received_date;
        if (!raw) {
            return null;
        }
        const parsed = new Date(raw);
        if (!isNaN(parsed.getTime())) {
            return parsed;
        }
        if (typeof raw === 'string' && /^\d{2}\/\d{2}\/\d{4}/.test(raw)) {
            const parts = raw.match(/^(\d{2})\/(\d{2})\/(\d{4})/);
            if (parts) {
                const iso = parts[3] + '-' + parts[2] + '-' + parts[1];
                const fallback = new Date(iso);
                if (!isNaN(fallback.getTime())) {
                    return fallback;
                }
            }
        }
        return null;
    }

    function getEmailDateBucket(email) {
        const emailDate = parseEmailDateValue(email);
        if (!emailDate) {
            return 'earlier';
        }

        const emailKey = getAppTzDateKey(emailDate);
        const todayKey = getAppTzDateKey(new Date());
        if (!emailKey || !todayKey) {
            return 'earlier';
        }
        if (emailKey === todayKey) {
            return 'today';
        }

        const yesterday = new Date();
        yesterday.setDate(yesterday.getDate() - 1);
        if (emailKey === getAppTzDateKey(yesterday)) {
            return 'yesterday';
        }

        const weekStart = new Date();
        const day = weekStart.getDay();
        const diffToMonday = day === 0 ? 6 : day - 1;
        weekStart.setDate(weekStart.getDate() - diffToMonday);
        weekStart.setHours(0, 0, 0, 0);
        const weekStartKey = getAppTzDateKey(weekStart);
        if (weekStartKey && emailKey >= weekStartKey) {
            return 'this_week';
        }

        return 'earlier';
    }

    const dateGroupLabels = {
        today: 'Today',
        yesterday: 'Yesterday',
        this_week: 'This week',
        earlier: 'Earlier'
    };

    const dateGroupIcons = {
        today: 'fa-sun',
        yesterday: 'fa-moon',
        this_week: 'fa-calendar-week',
        earlier: 'fa-clock-rotate-left'
    };

    function formatTimeOnly(dateInput) {
        if (!dateInput) {
            return '';
        }
        try {
            const date = new Date(dateInput);
            if (isNaN(date.getTime())) {
                return '';
            }
            return new Intl.DateTimeFormat('en-AU', {
                timeZone: appTimezone,
                hour: 'numeric',
                minute: '2-digit',
                hour12: true
            }).format(date).toLowerCase();
        } catch (e) {
            return '';
        }
    }

    function formatListEmailDate(email) {
        const bucket = getEmailDateBucket(email);
        const raw = getEmailDate(email);
        if (bucket === 'today') {
            const timeOnly = formatTimeOnly(raw);
            if (timeOnly) {
                return timeOnly;
            }
        }
        if (bucket === 'yesterday') {
            return 'Yesterday';
        }
        return formatEmailDate(raw);
    }

    function extractSenderName(fromMail) {
        const value = String(fromMail || '').trim();
        if (!value) {
            return 'Unknown';
        }
        const match = value.match(/^([^<]+)</);
        if (match && match[1]) {
            return match[1].trim().replace(/^["']|["']$/g, '');
        }
        if (value.includes('@')) {
            return value.split('@')[0].replace(/[._-]+/g, ' ');
        }
        return value;
    }

    function renderSyncedFolderUnreadBadge(count) {
        const badge = currentFolder === 'assigned'
            ? folderAssignedUnreadBadge
            : folderUnassignedUnreadBadge;
        const otherBadge = currentFolder === 'assigned'
            ? folderUnassignedUnreadBadge
            : folderAssignedUnreadBadge;

        if (otherBadge) {
            otherBadge.hidden = true;
            otherBadge.textContent = '';
        }

        if (!badge) {
            return;
        }

        const safeCount = Math.max(0, Number(count) || 0);
        if (safeCount > 0) {
            badge.textContent = safeCount > 99 ? '99+' : String(safeCount);
            badge.hidden = false;
            badge.setAttribute('aria-label', safeCount + ' unread');
        } else {
            badge.textContent = '';
            badge.hidden = true;
            badge.removeAttribute('aria-label');
        }
    }

    function renderSyncedDateSummaryBar(summary) {
        if (!syncedDateSummaryEl || !unassignedOnly) {
            return;
        }

        if (!summary || !summary.total) {
            syncedDateSummaryEl.hidden = true;
            syncedDateSummaryEl.innerHTML = '';
            return;
        }

        const todayCount = Number(summary.today) || 0;
        const yesterdayCount = Number(summary.yesterday) || 0;
        const weekCount = Number(summary.this_week) || 0;
        const earlierCount = Number(summary.earlier) || 0;
        const totalCount = Number(summary.total) || 0;
        const folderLabel = currentFolder === 'assigned' ? 'assigned' : 'unassigned';

        let chips = '';
        const stats = [
            { key: 'today', label: 'Today', value: todayCount, active: true },
            { key: 'yesterday', label: 'Yesterday', value: yesterdayCount },
            { key: 'this_week', label: 'This week', value: weekCount },
            { key: 'earlier', label: 'Earlier', value: earlierCount }
        ];

        stats.forEach(function (stat) {
            if (stat.value <= 0) {
                return;
            }
            chips += '<div class="synced-stat' + (stat.active ? ' synced-stat--active' : '') + '">'
                + '<span class="synced-stat__value">' + stat.value + '</span>'
                + '<span class="synced-stat__label">' + escapeHtml(stat.label) + '</span>'
                + '</div>';
        });

        syncedDateSummaryEl.innerHTML = ''
            + '<div class="synced-date-summary__head">'
            + '  <div class="synced-date-summary__title">'
            + '    <i class="fa-solid fa-inbox" aria-hidden="true"></i>'
            + '    <span>' + escapeHtml(folderLabel.charAt(0).toUpperCase() + folderLabel.slice(1)) + ' inbox</span>'
            + '  </div>'
            + '  <div class="synced-date-summary__today">'
            + '    <strong>' + todayCount + '</strong>'
            + '    <span>' + (todayCount === 1 ? 'email today' : 'emails today') + '</span>'
            + '  </div>'
            + '</div>'
            + (chips ? '<div class="synced-date-summary__stats">' + chips + '</div>' : '')
            + '<div class="synced-date-summary__meta">' + totalCount + ' total in this tab</div>';

        syncedDateSummaryEl.hidden = false;
    }

    function updateUnreadTabBadge(count) {
        unreadCount = Math.max(0, Number(count) || 0);
        if (!folderUnreadBadge) {
            if (unassignedOnly && isSyncedInboxFolder(currentFolder)) {
                renderSyncedFolderUnreadBadge(unreadCount);
            }
            updateMarkAllReadButtonVisibility();
            return;
        }
        if (unreadCount > 0) {
            folderUnreadBadge.textContent = unreadCount > 99 ? '99+' : String(unreadCount);
            folderUnreadBadge.hidden = false;
            folderUnreadBadge.setAttribute('aria-label', unreadCount + ' unread');
        } else {
            folderUnreadBadge.textContent = '';
            folderUnreadBadge.hidden = true;
            folderUnreadBadge.removeAttribute('aria-label');
        }
        if (unassignedOnly && isSyncedInboxFolder(currentFolder)) {
            renderSyncedFolderUnreadBadge(unreadCount);
        }
        updateMarkAllReadButtonVisibility();
    }

    async function markEmailAsRead(email, listElement) {
        if (!email || !isEmailUnread(email)) {
            return;
        }

        email.mail_is_read = true;
        email.is_read = true;
        if (listElement) {
            listElement.classList.remove('unread');
        }
        updateUnreadTabBadge(unreadCount - 1);

        if (currentFolder === 'unread') {
            emails = emails.filter(function (item) {
                return item.id !== email.id;
            });
            selectedEmailId = email.id;
            renderEmailList();
        } else if (currentFolder === 'inbox') {
            emails = sortEmailsUnreadFirst(emails);
            renderEmailList();
        } else if (isSyncedInboxFolder(currentFolder)) {
            renderEmailList();
        }

        updateMarkReadButtonVisibility(email);

        if (!updateMailReadUrl) {
            return;
        }

        try {
            const formData = new FormData();
            formData.append('mail_report_id', email.id);
            formData.append('_token', getCsrfToken());
            await fetch(updateMailReadUrl, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
        } catch (error) {
            console.error('Failed to mark email as read', error);
        }
    }

    async function markAllEmailsAsRead() {
        const isSyncedFolder = isSyncedInboxFolder(currentFolder);

        if (isSyncedFolder) {
            if (!markSyncedReadUrl) {
                crmToast('Cannot mark emails as read in this view.', 'warning');
                return;
            }
        } else if (!markAllReadUrl || !clientId) {
            crmToast('Cannot mark emails as read in this view.', 'warning');
            return;
        }

        if (unreadCount <= 0) {
            crmToast('No unread emails to update.', 'info');
            return;
        }

        const originalHtml = btnMarkAllRead ? btnMarkAllRead.innerHTML : '';
        if (btnMarkAllRead) {
            btnMarkAllRead.disabled = true;
            btnMarkAllRead.innerHTML = '<i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i><span>Marking...</span>';
        }

        try {
            let response;
            if (isSyncedFolder) {
                const formData = new FormData();
                formData.append('folder', currentFolder);
                formData.append('_token', getCsrfToken());
                response = await fetch(markSyncedReadUrl, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
            } else {
                const formData = new FormData();
                formData.append('client_id', clientId);
                if (matterId) {
                    formData.append('client_matter_id', matterId);
                }
                formData.append('_token', getCsrfToken());
                response = await fetch(markAllReadUrl, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
            }

            const data = await response.json().catch(function () {
                return {};
            });

            if (!response.ok || data.success === false) {
                crmToast(data.message || 'Could not mark emails as read.', 'error');
                return;
            }

            crmToast(data.message || 'All emails marked as read.', 'success');
            currentPage = 1;
            loadEmails();
        } catch (error) {
            crmToast('Could not mark emails as read: ' + (error.message || 'Unknown error'), 'error');
        } finally {
            if (btnMarkAllRead) {
                btnMarkAllRead.disabled = false;
                btnMarkAllRead.innerHTML = originalHtml;
            }
            updateMarkAllReadButtonVisibility();
        }
    }

    if (btnMarkAllRead) {
        btnMarkAllRead.addEventListener('click', function () {
            markAllEmailsAsRead();
        });
    }

    if (btnMarkRead) {
        btnMarkRead.addEventListener('click', function () {
            if (!selectedEmail) {
                return;
            }
            const activeEl = document.querySelector('.email-item.active');
            markEmailAsRead(selectedEmail, activeEl);
        });
    }

    // Event Listeners
    folderItems.forEach(item => {
        item.addEventListener('click', (e) => {
            const target = e.currentTarget;
            const folder = target.dataset.folder || 'inbox';
            if (folder === 'unassigned' && !canViewSyncedInbox) {
                return;
            }
            if (folder === 'assigned' && !canViewSyncedInbox) {
                return;
            }
            switchToFolder(folder);
            loadEmails();
        });
    });

    searchInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
            currentPage = 1;
            loadEmails();
        }
    });

    if (labelFilter) {
        labelFilter.addEventListener('change', () => {
            currentPage = 1;
            loadEmails();
        });
    }

    if (senderFilter) {
        senderFilter.addEventListener('change', () => {
            currentPage = 1;
            loadEmails();
        });
    }

    if (sortOrder) {
        sortOrder.addEventListener('change', () => {
            currentPage = 1;
            loadEmails();
        });
    }

    if (sendStatusFilter) {
        sendStatusFilter.addEventListener('change', () => {
            currentPage = 1;
            loadEmails();
        });
    }

    if (dateFromFilter) {
        dateFromFilter.addEventListener('change', () => {
            currentPage = 1;
            loadEmails();
        });
    }

    if (dateToFilter) {
        dateToFilter.addEventListener('change', () => {
            currentPage = 1;
            loadEmails();
        });
    }

    prevBtn.addEventListener('click', () => {
        if (currentPage > 1) {
            currentPage--;
            loadEmails();
        }
    });

    nextBtn.addEventListener('click', () => {
        currentPage++;
        loadEmails();
    });

    if (composeFormatBar && composeReplyInput) {
        composeFormatBar.addEventListener('click', (event) => {
            const btn = event.target.closest('.compose-format-btn');
            if (!btn) return;
            event.preventDefault();
            composeReplyInput.focus();
            const cmd = btn.getAttribute('data-cmd');
            if (cmd) {
                document.execCommand(cmd, false, null);
            }
        });
    }

    if (composeQuoteToggle && composeQuoteWrap) {
        composeQuoteToggle.addEventListener('click', () => {
            const collapsed = composeQuoteWrap.classList.toggle('is-collapsed');
            composeQuoteToggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
            if (composeQuoteToggleLabel) {
                composeQuoteToggleLabel.textContent = collapsed ? 'Show quoted message' : 'Hide quoted message';
            }
        });
    }

    // Close Modal
    document.getElementById('closeModal').addEventListener('click', () => {
        resetComposeContext();
        composeModal.classList.remove('active');
    });

    document.getElementById('btnDiscard').addEventListener('click', () => {
        resetComposeContext();
        composeModal.classList.remove('active');
    });

    function parseRecipientField(value) {
        if (!value || !String(value).trim()) {
            return [];
        }
        return String(value)
            .split(/[,;]/)
            .map(function (part) {
                const trimmed = part.trim();
                const match = trimmed.match(/<([^>]+)>/);
                return match ? match[1].trim() : trimmed;
            })
            .filter(function (addr) {
                return addr && addr.includes('@');
            });
    }

    function getDefaultComposeFromAddress() {
        return authEmail || '';
    }

    function getComposeFromInputValue() {
        const composeFromEl = document.getElementById('composeFrom');
        const raw = composeFromEl ? composeFromEl.value.trim() : '';
        if (!raw) {
            return getDefaultComposeFromAddress();
        }
        const parsed = parseRecipientField(raw);
        return parsed.length ? parsed[0] : raw;
    }

    function setComposeFromField(value) {
        composeFromEmail = value || getDefaultComposeFromAddress();
        const composeFromEl = document.getElementById('composeFrom');
        if (composeFromEl) {
            composeFromEl.value = composeFromEmail;
        }
    }

    function appendRecipientFields(formData, fieldName, value) {
        parseRecipientField(value).forEach(function (addr) {
            formData.append(fieldName + '[]', addr);
        });
    }

    function switchToFolder(folder) {
        folderItems.forEach(function (f) {
            const isActive = f.dataset.folder === folder;
            f.classList.toggle('active', isActive);
            f.setAttribute('aria-selected', isActive ? 'true' : 'false');
        });
        currentFolder = folder;
        currentPage = 1;
        resetReadingPane();
        updateOutboxFiltersVisibility();
        updateMarkAllReadButtonVisibility();
    }

    // Send Mail
    if (btnSendEl) btnSendEl.addEventListener('click', async () => {
        const to = toInput.value.trim();
        const cc = ccInput ? ccInput.value.trim() : '';
        const bcc = bccInput ? bccInput.value.trim() : '';
        const subject = subjectInput.value.trim();
        const message = getComposeMessageHtml();

        if (!to || !subject || !message) {
            crmToast('Please fill in To, Subject, and Message fields.', 'warning', 'Required');
            return;
        }

        composeFromEmail = getComposeFromInputValue();
        if (!composeFromEmail || !composeFromEmail.includes('@')) {
            crmToast('Please enter a valid From email address.', 'warning', 'Invalid sender');
            return;
        }

        const btnSend = btnSendEl;
        const originalHtml = btnSend.innerHTML;
        btnSend.innerHTML = composeResendLogId ? 'Resending...' : 'Sending...';
        btnSend.disabled = true;

        const formData = new FormData();
        if (clientId) formData.append('client_id', clientId);
        if (matterId) formData.append('compose_client_matter_id', matterId);
        formData.append('email_from', composeFromEmail);
        appendRecipientFields(formData, 'email_to', to);
        if (cc) {
            appendRecipientFields(formData, 'email_cc', cc);
        }
        if (bcc) {
            appendRecipientFields(formData, 'email_bcc', bcc);
        }
        formData.append('subject', typeof crmWafSafeEmailSubject === 'function' ? crmWafSafeEmailSubject(subject) : subject.replace(/&/g, '__AMP__'));
        if (typeof crmEncodeSendmailMessage === 'function') {
            formData.append('message', crmEncodeSendmailMessage(message));
            formData.append('message_encoding', 'b64');
        } else {
            formData.append('message', message);
        }
        formData.append('type', 'client');
        formData.append('mail_type', 2);
        if (composeResendLogId) {
            formData.append('resend_email_log_id', composeResendLogId);
        } else if (composeReplyToEmailId) {
            formData.append('reply_to_email_id', composeReplyToEmailId);
        }
        formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);

        const sendmailPath = typeof crmResolveSameOriginUrl === 'function'
            ? crmResolveSameOriginUrl((baseUrl || '') + '/sendmail')
            : ((baseUrl || '') + '/sendmail');

        try {
            const response = await fetch(sendmailPath, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            });
            const responseText = await response.text();
            let result = {};
            try {
                result = responseText ? JSON.parse(responseText) : {};
            } catch (parseErr) {
                result = {};
            }

            if (response.status === 403) {
                const wafMsg = typeof crmSendmail403Message === 'function'
                    ? crmSendmail403Message(responseText, response.status)
                    : (typeof crmEmailUpload403Message === 'function'
                        ? crmEmailUpload403Message(responseText, response.status)
                        : null);
                crmToast(wafMsg || 'The server blocked this email (HTTP 403). Try a shorter plain reply.', 'error');
                return;
            }
            
            if (result.success || response.ok) {
                const wasResend = !!composeResendLogId;
                crmToast(
                    result.message || (wasResend ? 'Email resent successfully!' : 'Email sent successfully!'),
                    'success'
                );
                composeModal.classList.remove('active');
                resetComposeContext();
                switchToFolder(wasResend ? 'outbox' : 'sent');
                updateOutboxFiltersVisibility();
                if (result.email_log_id) {
                    selectedEmailId = parseInt(result.email_log_id, 10) || null;
                }
                loadEmails();
            } else {
                const errMsg = result.message
                    || (result.errors ? Object.values(result.errors).flat().join('\n') : '')
                    || ('Failed to send email.' + (response.status ? ' (HTTP ' + response.status + ')' : ''));
                crmToast(errMsg, 'error');
                if (result.send_status === 'failed' || result.email_log_id) {
                    resetComposeContext();
                    composeModal.classList.remove('active');
                    switchToFolder('outbox');
                    updateOutboxFiltersVisibility();
                    if (result.email_log_id) {
                        selectedEmailId = parseInt(result.email_log_id, 10) || null;
                    }
                    loadEmails();
                }
            }
        } catch (error) {
            console.error('Error sending email:', error);
            crmToast('An error occurred while sending the email.', 'error');
        } finally {
            btnSend.innerHTML = originalHtml;
            btnSend.disabled = false;
        }
    });

    // Action Buttons (with null checks since they might be commented out)
    const btnReply = document.getElementById('btnReply');
    if (btnReply) btnReply.addEventListener('click', () => openCompose('reply'));
    
    const btnReplyAll = document.getElementById('btnReplyAll');
    if (btnReplyAll) btnReplyAll.addEventListener('click', () => openCompose('replyAll'));
    
    const btnForward = document.getElementById('btnForward');
    if (btnForward) btnForward.addEventListener('click', () => openCompose('forward'));

    if (btnResend) {
        btnResend.addEventListener('click', () => openComposeResend());
    }

    const canDeleteEmail = outlookContainer && outlookContainer.dataset.canDeleteEmail === '1';
    const btnDeleteEmail = document.getElementById('btnDeleteEmail');
    if (btnDeleteEmail && canDeleteEmail) {
        btnDeleteEmail.addEventListener('click', handleDeleteEmail);
    }

    // File Upload Handler
    const btnUploadEmail = document.getElementById('btnUploadEmail');
    const fileInput = document.getElementById('outlookEmailFileInput');
    const uploadStatus = document.getElementById('uploadStatus');
    const emailUploadLoadingOverlay = document.getElementById('emailUploadLoadingOverlay');
    const emailUploadLoadingTitle = document.getElementById('emailUploadLoadingTitle');
    const emailUploadLoadingMessage = document.getElementById('emailUploadLoadingMessage');
    const emailUploadLoadingFilename = document.getElementById('emailUploadLoadingFilename');
    const emailUploadLoadingProgressBar = document.getElementById('emailUploadLoadingProgressBar');

    let isEmailUploading = false;

    function updateEmailUploadLoading(title, message, filename, progressPercent) {
        if (emailUploadLoadingTitle && title) {
            emailUploadLoadingTitle.textContent = title;
        }
        if (emailUploadLoadingMessage && message) {
            emailUploadLoadingMessage.textContent = message;
        }
        if (emailUploadLoadingFilename) {
            emailUploadLoadingFilename.textContent = filename || '';
        }
        if (emailUploadLoadingProgressBar) {
            const pct = Math.max(0, Math.min(100, Number(progressPercent) || 0));
            emailUploadLoadingProgressBar.style.width = pct + '%';
        }
    }

    function showEmailUploadLoading(title, message, filename, progressPercent) {
        if (!emailUploadLoadingOverlay) return;
        updateEmailUploadLoading(title, message, filename, progressPercent);
        emailUploadLoadingOverlay.classList.add('active');
        emailUploadLoadingOverlay.setAttribute('aria-hidden', 'false');
        emailUploadLoadingOverlay.setAttribute('aria-busy', 'true');
        document.body.classList.add('email-upload-in-progress');
    }

    function hideEmailUploadLoading() {
        if (!emailUploadLoadingOverlay) return;
        emailUploadLoadingOverlay.classList.remove('active');
        emailUploadLoadingOverlay.setAttribute('aria-hidden', 'true');
        emailUploadLoadingOverlay.setAttribute('aria-busy', 'false');
        document.body.classList.remove('email-upload-in-progress');
        if (emailUploadLoadingProgressBar) {
            emailUploadLoadingProgressBar.style.width = '0%';
        }
    }

    if (fileInput) {
        if (btnUploadEmail) {
            btnUploadEmail.addEventListener('click', () => {
                if (isEmailUploading) return;
                fileInput.click();
            });
        }

        const inlineDropZone = document.getElementById('inlineDropZone');
        if (inlineDropZone) {
            inlineDropZone.addEventListener('click', () => {
                if (isEmailUploading) return;
                fileInput.click();
            });
        }

        async function processDroppedEmailFiles(dataTransferOrSnapshot) {
            let rawFiles = [];
            let files = [];

            if (typeof window.crmResolveEmailUploadDrop === 'function') {
                const dropResult = await window.crmResolveEmailUploadDrop(dataTransferOrSnapshot);
                rawFiles = dropResult.rawFiles || [];
                files = dropResult.files || [];
            } else if (typeof window.crmResolveEmailUploadDropFiles === 'function') {
                files = await window.crmResolveEmailUploadDropFiles(dataTransferOrSnapshot);
                rawFiles = (typeof window.crmGetFilesFromDataTransfer === 'function')
                    ? await window.crmGetFilesFromDataTransfer(dataTransferOrSnapshot)
                    : (dataTransferOrSnapshot && dataTransferOrSnapshot.files
                        ? Array.from(dataTransferOrSnapshot.files)
                        : []);
            } else {
                rawFiles = (dataTransferOrSnapshot && dataTransferOrSnapshot.files)
                    ? Array.from(dataTransferOrSnapshot.files)
                    : [];
                files = rawFiles;
            }

            if (files.length > 0) {
                handleUploadFiles(files);
                return;
            }

            const failure = (typeof window.crmGetEmailUploadDropFailureMessage === 'function')
                ? window.crmGetEmailUploadDropFailureMessage(rawFiles, files)
                : null;

            if (failure) {
                reportEmailUploadFailure('drop_failed', {
                    error: failure.message,
                    technical_error: failure.title
                });
                showUploadErrorAlert(failure.message, failure.title);
            }
        }

        function bindEmailDropZone(dropZone) {
            if (!dropZone) {
                return;
            }

            dropZone.addEventListener('dragenter', (e) => {
                e.preventDefault();
                e.stopPropagation();
                dropZone.classList.add('is-drag-over');
            });

            dropZone.addEventListener('dragover', (e) => {
                e.preventDefault();
                e.stopPropagation();
                if (e.dataTransfer) {
                    e.dataTransfer.dropEffect = 'copy';
                }
                dropZone.classList.add('is-drag-over');
            });

            dropZone.addEventListener('dragleave', (e) => {
                e.preventDefault();
                e.stopPropagation();
                dropZone.classList.remove('is-drag-over');
            });

            dropZone.addEventListener('drop', (e) => {
                e.preventDefault();
                e.stopPropagation();
                dropZone.classList.remove('is-drag-over');

                if (isEmailUploading) return;

                const snapshot = (typeof window.crmCaptureEmailUploadDropSnapshot === 'function')
                    ? window.crmCaptureEmailUploadDropSnapshot(e.dataTransfer)
                    : e.dataTransfer;
                processDroppedEmailFiles(snapshot);
            });
        }

        bindEmailDropZone(inlineDropZone);

        function formatUploadErrorDetails(errors) {
            if (!errors || !Array.isArray(errors) || errors.length === 0) {
                return '';
            }
            return errors.map(function(err, index) {
                const filename = err.filename || 'Unknown file';
                const error = err.error || 'Unknown error';
                return (index + 1) + '. ' + filename + '\n   ' + error;
            }).join('\n\n');
        }

        function buildUploadTechnicalDetails(errors) {
            if (typeof window.crmBuildEmailUploadTechnicalDetails === 'function') {
                return window.crmBuildEmailUploadTechnicalDetails({ errors: errors });
            }
            return '';
        }

        function showUploadResultModal(type, message, title, details) {
            if (typeof window.crmShowEmailUploadResultModal === 'function') {
                return window.crmShowEmailUploadResultModal({
                    type: type,
                    title: title,
                    message: message || '',
                    details: details || ''
                });
            }

            if (typeof window.crmToast === 'function') {
                window.crmToast((title ? title + ': ' : '') + (message || ''), type === 'error' ? 'error' : type === 'success' ? 'success' : 'info', title || 'Upload');
                return Promise.resolve();
            }

            return Promise.resolve();
        }

        function showUploadSuccessToast(message) {
            return showUploadResultModal('success', message, 'Upload Successful');
        }

        function showUploadFileSuccess(fileName) {
            updateUploadStatusForFile(fileName, 'success');
        }

        function showUploadFileError(fileName, errorMessage) {
            const text = errorMessage || 'Upload failed. Please try again.';
            updateUploadStatusForFile(fileName, 'error', text);
        }

        function updateUploadStatusForFile(fileName, type, detail) {
            if (!uploadStatus) return;
            uploadStatus.hidden = false;
            if (type === 'success') {
                uploadStatus.style.color = '#107c10';
                uploadStatus.textContent = 'Uploaded: ' + fileName;
            } else if (type === 'error') {
                uploadStatus.style.color = '#d13438';
                uploadStatus.textContent = 'Failed: ' + fileName + (detail ? ' — ' + detail : '');
            } else if (type === 'skipped') {
                uploadStatus.style.color = '#ca5010';
                uploadStatus.textContent = 'Skipped: ' + fileName + (detail ? ' — ' + detail : '');
            } else {
                uploadStatus.style.color = 'var(--outlook-blue)';
                uploadStatus.textContent = detail || fileName;
            }
        }

        function notifySingleFileUploadResult(file, fileResult) {
            if (fileResult.cancelled) {
                showUploadFileError(file.name, 'Upload cancelled.');
                updateUploadStatusForFile(file.name, 'skipped', 'Upload cancelled');
                return;
            }

            if (fileResult.uploaded > 0 && fileResult.failed === 0 && !(fileResult.rejected > 0)) {
                showUploadFileSuccess(file.name);
                updateUploadStatusForFile(file.name, 'success');
                return;
            }

            if (fileResult.rejected > 0) {
                const rejectedError = (fileResult.errors && fileResult.errors[0] && fileResult.errors[0].error)
                    ? fileResult.errors[0].error
                    : DUPLICATE_EXISTS_MESSAGE;
                showUploadFileError(file.name, rejectedError);
                updateUploadStatusForFile(file.name, 'skipped', rejectedError);
                return;
            }

            let errorMessage = fileResult.message || 'Upload failed';
            if (fileResult.errors && fileResult.errors.length) {
                errorMessage = fileResult.errors[0].error || errorMessage;
            }
            reportEmailUploadFailure('server_file_failed', {
                filename: file.name,
                error: errorMessage,
                technical_error: fileResult.errors && fileResult.errors[0] ? fileResult.errors[0].technical_error : null,
                errors: fileResult.errors || []
            });
            showUploadFileError(file.name, errorMessage);
            updateUploadStatusForFile(file.name, 'error', errorMessage);
        }

        function showUploadErrorAlert(message, title, details) {
            const text = message || 'Upload failed. Please try again.';
            const alertTitle = title || 'Upload Failed';
            return showUploadResultModal('error', text, alertTitle, details);
        }

        const DUPLICATE_EXISTS_MESSAGE = 'This email already exists.';

        function showDuplicateEmailPrompt(fileName) {
            return new Promise(function(resolve) {
                const modal = document.getElementById('duplicateEmailModal');
                const fileNameEl = document.getElementById('duplicateEmailFileName');
                const acceptBtn = document.getElementById('duplicateEmailAccept');
                const rejectBtn = document.getElementById('duplicateEmailReject');

                if (!modal || !acceptBtn || !rejectBtn) {
                    resolve(window.confirm(DUPLICATE_EXISTS_MESSAGE + '\n\nDo you want to upload it anyway?'));
                    return;
                }

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

        function getAttachmentStem(filename) {
            const lastDot = (filename || '').lastIndexOf('.');
            if (lastDot <= 0) {
                return filename || 'attachment';
            }
            return filename.substring(0, lastDot);
        }

        function getAttachmentExtension(filename) {
            const lastDot = (filename || '').lastIndexOf('.');
            if (lastDot <= 0) {
                return '';
            }
            return filename.substring(lastDot);
        }

        function inferAttachmentExtension(filename, contentType) {
            const ext = getAttachmentExtension(filename).replace('.', '').toLowerCase();
            if (ext) {
                return ext;
            }
            const mime = String(contentType || '').toLowerCase().split(';')[0].trim();
            const map = {
                'image/jpeg': 'jpg',
                'image/jpg': 'jpg',
                'image/png': 'png',
                'image/gif': 'gif',
                'image/bmp': 'bmp',
                'image/webp': 'webp',
                'application/pdf': 'pdf',
                'application/msword': 'doc',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document': 'docx'
            };
            return map[mime] || '';
        }

        function getAttachmentSaveStem(att, nameInput) {
            const stem = nameInput ? nameInput.value.trim() : getAttachmentStem(att.filename);
            return stem || getAttachmentStem(att.filename) || 'attachment';
        }

        function formatAttachmentFileSize(bytes) {
            const size = Number(bytes) || 0;
            if (size <= 0) {
                return 'Unknown size';
            }
            if (size < 1024) {
                return size + ' B';
            }
            if (size < 1024 * 1024) {
                return (size / 1024).toFixed(1) + ' KB';
            }
            return (size / (1024 * 1024)).toFixed(1) + ' MB';
        }

        function getAttachmentFileIcon(filename, contentType) {
            const ext = getAttachmentExtension(filename).toLowerCase().replace('.', '');
            if (ext === 'pdf' || (contentType && contentType.indexOf('pdf') !== -1)) {
                return 'fa-file-pdf';
            }
            if (['doc', 'docx'].indexOf(ext) !== -1) {
                return 'fa-file-word';
            }
            if (['xls', 'xlsx', 'csv'].indexOf(ext) !== -1) {
                return 'fa-file-excel';
            }
            if (['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'].indexOf(ext) !== -1) {
                return 'fa-file-image';
            }
            if (['zip', 'rar', '7z'].indexOf(ext) !== -1) {
                return 'fa-file-zipper';
            }
            return 'fa-file-lines';
        }

        function buildFolderOptionsHtml(storageType, selectedFolderId) {
            const folders = storageType === 'personal' ? personalFolders : matterFolders;
            if (!folders.length) {
                return '<option value="">No folders available</option>';
            }
            let html = '<option value="">Select folder</option>';
            folders.forEach(function(folder) {
                const selected = String(selectedFolderId) === String(folder.id) ? ' selected' : '';
                html += '<option value="' + escapeHtml(String(folder.id)) + '"' + selected + '>' + escapeHtml(folder.title) + '</option>';
            });
            return html;
        }

        function getFolderTitle(storageType, folderId) {
            if (!folderId || storageType === 'email') {
                return '';
            }
            const folders = storageType === 'personal' ? personalFolders : matterFolders;
            const match = folders.find(function(folder) {
                return String(folder.id) === String(folderId);
            });
            return match ? match.title : '';
        }

        function getStorageTypeLabel(storageType) {
            if (storageType === 'personal') {
                return 'Personal documents';
            }
            if (storageType === 'matter') {
                return 'Matter documents';
            }
            return 'Email attachments only';
        }

        function syncAttachmentFolderField(row, storageType, selectedFolderId) {
            const folderCell = row.querySelector('.attachment-storage-row__folder');
            const folderSelect = row.querySelector('.attachment-folder-select');
            if (!folderCell || !folderSelect) {
                return;
            }

            if (storageType === 'personal' || storageType === 'matter') {
                folderCell.hidden = false;
                folderSelect.innerHTML = buildFolderOptionsHtml(storageType, selectedFolderId || '');
                folderSelect.disabled = false;
            } else {
                folderCell.hidden = true;
                folderSelect.innerHTML = '<option value="">Select folder</option>';
                folderSelect.value = '';
                folderSelect.disabled = true;
            }
        }

        function setAttachmentRowStorageType(row, storageType, folderId) {
            row.dataset.storageType = storageType;
            row.classList.remove('has-error');
            const errorEl = row.querySelector('.attachment-field-error');
            if (errorEl) {
                errorEl.textContent = 'Please select a folder.';
            }

            row.querySelectorAll('.attachment-loc-btn').forEach(function(btn) {
                btn.classList.toggle('active', btn.dataset.type === storageType);
            });

            syncAttachmentFolderField(row, storageType, folderId || '');
        }

        function populateBulkFolderSelect(bulkFolderWrap, bulkFolderSelect, storageType, selectedFolderId) {
            if (!bulkFolderSelect || !bulkFolderWrap) {
                return;
            }
            if (storageType === 'personal' || storageType === 'matter') {
                bulkFolderWrap.hidden = false;
                bulkFolderSelect.innerHTML = buildFolderOptionsHtml(storageType, selectedFolderId || '');
                bulkFolderSelect.disabled = false;
            } else {
                bulkFolderWrap.hidden = true;
                bulkFolderSelect.innerHTML = '<option value="">Select folder</option>';
                bulkFolderSelect.value = '';
                bulkFolderSelect.disabled = true;
            }
        }

        function updateDestinationSummary(summaryEl, storageType, folderId) {
            if (!summaryEl) {
                return;
            }
            const folderTitle = getFolderTitle(storageType, folderId);
            if (storageType === 'email') {
                summaryEl.textContent = 'Stored with the email only';
                return;
            }
            if (folderTitle) {
                summaryEl.textContent = getStorageTypeLabel(storageType) + ' → ' + folderTitle;
                return;
            }
            summaryEl.textContent = getStorageTypeLabel(storageType) + ' — choose a folder';
        }

        function renderAttachmentFileCell(att) {
            const icon = getAttachmentFileIcon(att.filename, att.content_type);
            return `
                <div class="attachment-storage-row__file-inner">
                    <span class="attachment-storage-row__icon"><i class="fa-solid ${icon}"></i></span>
                    <span class="attachment-storage-row__filename" title="${escapeHtml(att.filename)}">${escapeHtml(att.filename)}</span>
                </div>
            `;
        }

        function renderAttachmentRenameCell(att, index) {
            const stem = getAttachmentStem(att.filename);
            const ext = getAttachmentExtension(att.filename) || (
                inferAttachmentExtension(att.filename, att.content_type)
                    ? '.' + inferAttachmentExtension(att.filename, att.content_type)
                    : ''
            );
            return `
                <div class="attachment-rename-input">
                    <input type="text" id="attachmentName_${index}" value="${escapeHtml(stem)}" data-original="${escapeHtml(att.filename)}" autocomplete="off" aria-label="Save ${escapeHtml(att.filename)} as">
                    ${ext ? `<span class="attachment-rename-ext">${escapeHtml(ext)}</span>` : ''}
                </div>
                <span class="attachment-field-error">Please enter a file name.</span>
            `;
        }

        function renderAttachmentStorageRow(att, index, mode) {
            const sizeLabel = formatAttachmentFileSize(att.file_size);
            const locationCell = mode === 'individual' ? `
                <td class="attachment-storage-row__location">
                    <div class="attachment-location-tabs attachment-location-tabs--compact" role="group" aria-label="Document location for ${escapeHtml(att.filename)}">
                        <button type="button" class="attachment-loc-btn active" data-type="email">Email</button>
                        <button type="button" class="attachment-loc-btn" data-type="personal">Personal</button>
                        <button type="button" class="attachment-loc-btn" data-type="matter">Matter</button>
                    </div>
                </td>
                <td class="attachment-storage-row__folder" hidden>
                    <select id="attachmentFolder_${index}" class="attachment-folder-select" disabled aria-label="Folder for ${escapeHtml(att.filename)}">
                        <option value="">Select folder</option>
                    </select>
                    <span class="attachment-field-error">Please select a folder.</span>
                </td>
            ` : '';

            return `
                <tr class="attachment-storage-row" data-index="${index}" data-storage-type="email">
                    <td class="attachment-storage-row__file">${renderAttachmentFileCell(att)}</td>
                    <td class="attachment-storage-row__size">${escapeHtml(sizeLabel)}</td>
                    <td class="attachment-storage-row__name">${renderAttachmentRenameCell(att, index)}</td>
                    ${locationCell}
                </tr>
            `;
        }

        function bindAttachmentStorageRowEvents(row) {
            row.querySelectorAll('.attachment-loc-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    setAttachmentRowStorageType(row, btn.dataset.type || 'email', '');
                });
            });
        }

        function applyBulkDestinationToRows(body, storageType, folderId) {
            body.querySelectorAll('.attachment-storage-row').forEach(function(row) {
                setAttachmentRowStorageType(row, storageType, folderId);
            });
        }

        function setAttachmentStorageMode(mode, attachments, body, tableHead, destinationPanel, modePanel) {
            const isBulk = mode === 'bulk';
            const isMulti = attachments.length > 1;

            if (tableHead) {
                tableHead.innerHTML = isBulk || !isMulti
                    ? `<tr>
                        <th scope="col" class="attachment-storage-table__col-file">File</th>
                        <th scope="col" class="attachment-storage-table__col-size">Size</th>
                        <th scope="col" class="attachment-storage-table__col-name">Save as</th>
                    </tr>`
                    : `<tr>
                        <th scope="col" class="attachment-storage-table__col-file">File</th>
                        <th scope="col" class="attachment-storage-table__col-size">Size</th>
                        <th scope="col" class="attachment-storage-table__col-name">Save as</th>
                        <th scope="col" class="attachment-storage-table__col-location">Location</th>
                        <th scope="col" class="attachment-storage-table__col-folder">Folder</th>
                    </tr>`;
            }

            body.innerHTML = attachments.map(function(att, index) {
                return renderAttachmentStorageRow(att, index, isBulk ? 'bulk' : 'individual');
            }).join('');
            body.querySelectorAll('.attachment-storage-row').forEach(bindAttachmentStorageRowEvents);

            if (destinationPanel) {
                destinationPanel.hidden = !isBulk && isMulti;
            }
            if (modePanel) {
                modePanel.hidden = !isMulti;
            }

            const destinationLabel = document.getElementById('attachmentDestinationLabel');
            if (destinationLabel) {
                destinationLabel.textContent = isMulti
                    ? (isBulk ? 'Save all files to' : 'Default location')
                    : 'Save file to';
            }
        }

        async function previewEmailAttachments(file) {
            const formData = new FormData();
            const extMatch = (file.name || '').toLowerCase().match(/\.(msg|eml)$/);
            const safeName = crmOutlookSanitizeUploadFilename(file.name, extMatch ? extMatch[1] : 'eml');
            formData.append('email_files[]', file, safeName);
            formData.append('client_id', clientId);
            formData.append('type', 'client');
            if (matterId) {
                if (currentFolder === 'sent') {
                    formData.append('upload_sent_mail_client_matter_id', matterId);
                } else {
                    formData.append('upload_inbox_mail_client_matter_id', matterId);
                }
            }
            formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);

            const response = await fetch(baseUrl + '/preview-email-attachments', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            });

            const responseText = await response.text();
            let result = {};
            try {
                result = responseText ? JSON.parse(responseText) : {};
            } catch (parseError) {
                throw new Error('Could not read attachment preview from the server. Please try again.');
            }

            if (!response.ok || !result.status) {
                throw new Error(result.message || 'Failed to preview attachments');
            }
            return result.attachments || [];
        }

        function showAttachmentStorageModal(attachments) {
            return new Promise(function(resolve) {
                const modal = document.getElementById('attachmentStorageModal');
                const body = document.getElementById('attachmentStorageModalBody');
                const tableHead = document.getElementById('attachmentStorageTableHead');
                const countEl = document.getElementById('attachmentStorageCount');
                const subtitleEl = document.getElementById('attachmentStorageSubtitle');
                const modePanel = document.getElementById('attachmentStorageMode');
                const destinationPanel = document.getElementById('attachmentStorageDestination');
                const bulkLocationTabs = document.getElementById('attachmentBulkLocationTabs');
                const bulkFolderWrap = document.getElementById('attachmentBulkFolderWrap');
                const bulkFolderSelect = document.getElementById('attachmentBulkFolder');
                const destinationSummary = document.getElementById('attachmentDestinationSummary');
                const modeBulkBtn = document.getElementById('attachmentModeBulk');
                const modeIndividualBtn = document.getElementById('attachmentModeIndividual');
                const confirmBtn = document.getElementById('attachmentStorageConfirm');
                const cancelBtn = document.getElementById('attachmentStorageCancel');
                const closeBtn = document.getElementById('attachmentStorageClose');

                if (!modal || !body || !confirmBtn || !cancelBtn) {
                    resolve(null);
                    return;
                }

                const isMulti = attachments.length > 1;
                let currentMode = isMulti ? 'bulk' : 'bulk';
                let bulkStorageType = 'email';
                let bulkFolderId = '';

                if (countEl) {
                    countEl.textContent = attachments.length + (attachments.length === 1 ? ' file' : ' files');
                }
                if (subtitleEl) {
                    subtitleEl.textContent = isMulti
                        ? 'Pick one folder for every file, or switch to per-file locations. You can rename files below.'
                        : 'Choose where this file is stored in Documents and rename it if needed.';
                }

                function setModeButtons(mode) {
                    [modeBulkBtn, modeIndividualBtn].forEach(function(btn) {
                        if (btn) {
                            btn.classList.toggle('active', btn.dataset.mode === mode);
                        }
                    });
                }

                function setBulkLocationTabs(storageType) {
                    if (!bulkLocationTabs) {
                        return;
                    }
                    bulkLocationTabs.querySelectorAll('.attachment-loc-btn').forEach(function(btn) {
                        btn.classList.toggle('active', btn.dataset.type === storageType);
                    });
                }

                function syncBulkDestination() {
                    populateBulkFolderSelect(bulkFolderWrap, bulkFolderSelect, bulkStorageType, bulkFolderId);
                    updateDestinationSummary(destinationSummary, bulkStorageType, bulkFolderId);
                    if (currentMode === 'bulk') {
                        applyBulkDestinationToRows(body, bulkStorageType, bulkFolderId);
                    }
                }

                setAttachmentStorageMode(currentMode, attachments, body, tableHead, destinationPanel, modePanel);
                setModeButtons(currentMode);
                setBulkLocationTabs(bulkStorageType);
                populateBulkFolderSelect(bulkFolderWrap, bulkFolderSelect, bulkStorageType, bulkFolderId);
                updateDestinationSummary(destinationSummary, bulkStorageType, bulkFolderId);
                applyBulkDestinationToRows(body, bulkStorageType, bulkFolderId);

                function cleanup() {
                    modal.classList.remove('active');
                    modal.setAttribute('aria-hidden', 'true');
                    confirmBtn.removeEventListener('click', onConfirm);
                    cancelBtn.removeEventListener('click', onCancel);
                    if (closeBtn) {
                        closeBtn.removeEventListener('click', onCancel);
                    }
                    if (modeBulkBtn) {
                        modeBulkBtn.removeEventListener('click', onModeBulk);
                    }
                    if (modeIndividualBtn) {
                        modeIndividualBtn.removeEventListener('click', onModeIndividual);
                    }
                    if (bulkLocationTabs) {
                        bulkLocationTabs.removeEventListener('click', onBulkLocationClick);
                    }
                    if (bulkFolderSelect) {
                        bulkFolderSelect.removeEventListener('change', onBulkFolderChange);
                    }
                    modal.removeEventListener('click', onOverlayClick);
                    document.removeEventListener('keydown', onKeyDown);
                }

                function collectConfig() {
                    return attachments.map(function(att, index) {
                        const row = body.querySelector('.attachment-storage-row[data-index="' + index + '"]');
                        const nameInput = document.getElementById('attachmentName_' + index);
                        const folderSelect = document.getElementById('attachmentFolder_' + index);
                        const storageType = row ? (row.dataset.storageType || 'email') : 'email';
                        
                        let folderId = folderSelect ? folderSelect.value : '';
                        if (currentMode === 'bulk' && (storageType === 'personal' || storageType === 'matter')) {
                            folderId = bulkFolderId;
                        }

                        return {
                            original_filename: att.filename,
                            file_name: getAttachmentSaveStem(att, nameInput),
                            storage_type: storageType,
                            folder_id: folderId
                        };
                    });
                }

                function validateConfig(config) {
                    let firstInvalidRow = null;
                    body.querySelectorAll('.attachment-storage-row').forEach(function(row) {
                        row.classList.remove('has-error');
                        row.querySelectorAll('.attachment-storage-row__name, .attachment-storage-row__folder').forEach(function(cell) {
                            cell.classList.remove('has-error');
                        });
                        row.querySelectorAll('.attachment-field-error').forEach(function(errorEl) {
                            errorEl.textContent = 'Please select a folder.';
                        });
                    });

                    if (currentMode === 'bulk' && (bulkStorageType === 'personal' || bulkStorageType === 'matter') && !bulkFolderId) {
                        showUploadErrorAlert('Please select a folder for all attachments.');
                        if (bulkFolderSelect) {
                            bulkFolderSelect.focus();
                        }
                        return false;
                    }

                    config.forEach(function(item, index) {
                        const row = body.querySelector('.attachment-storage-row[data-index="' + index + '"]');
                        const nameInput = document.getElementById('attachmentName_' + index);
                        const folderSelect = document.getElementById('attachmentFolder_' + index);

                        if (nameInput && !nameInput.value.trim()) {
                            if (row) {
                                row.classList.add('has-error');
                                const nameCell = row.querySelector('.attachment-storage-row__name');
                                if (nameCell) {
                                    nameCell.classList.add('has-error');
                                }
                                const errorEl = row.querySelector('.attachment-storage-row__name .attachment-field-error');
                                if (errorEl) {
                                    errorEl.textContent = 'Please enter a file name.';
                                }
                                if (!firstInvalidRow) {
                                    firstInvalidRow = row;
                                }
                            }
                        }

                        if (item.storage_type !== 'email' && !item.folder_id) {
                            if (row) {
                                row.classList.add('has-error');
                                const folderCell = row.querySelector('.attachment-storage-row__folder');
                                if (folderCell) {
                                    folderCell.classList.add('has-error');
                                }
                                const errorEl = row.querySelector('.attachment-storage-row__folder .attachment-field-error');
                                if (errorEl) {
                                    errorEl.textContent = 'Please select a folder.';
                                }
                                if (!firstInvalidRow) {
                                    firstInvalidRow = row;
                                }
                            }
                            if (folderSelect) {
                                folderSelect.focus();
                            }
                        }
                    });

                    if (firstInvalidRow) {
                        firstInvalidRow.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                        return false;
                    }

                    return true;
                }

                function onModeBulk() {
                    if (currentMode === 'bulk') {
                        return;
                    }
                    currentMode = 'bulk';
                    setModeButtons(currentMode);
                    setAttachmentStorageMode(currentMode, attachments, body, tableHead, destinationPanel, modePanel);
                    setBulkLocationTabs(bulkStorageType);
                    syncBulkDestination();
                }

                function onModeIndividual() {
                    if (currentMode === 'individual') {
                        return;
                    }
                    currentMode = 'individual';
                    setModeButtons(currentMode);
                    setAttachmentStorageMode(currentMode, attachments, body, tableHead, destinationPanel, modePanel);
                    if (destinationPanel) {
                        destinationPanel.hidden = true;
                    }
                }

                function onBulkLocationClick(event) {
                    const btn = event.target.closest('.attachment-loc-btn');
                    if (!btn || !bulkLocationTabs || !bulkLocationTabs.contains(btn)) {
                        return;
                    }
                    bulkStorageType = btn.dataset.type || 'email';
                    bulkFolderId = '';
                    setBulkLocationTabs(bulkStorageType);
                    syncBulkDestination();
                }

                function onBulkFolderChange() {
                    bulkFolderId = bulkFolderSelect ? bulkFolderSelect.value : '';
                    updateDestinationSummary(destinationSummary, bulkStorageType, bulkFolderId);
                    if (currentMode === 'bulk') {
                        applyBulkDestinationToRows(body, bulkStorageType, bulkFolderId);
                    }
                }

                function onConfirm() {
                    const config = collectConfig();
                    if (!validateConfig(config)) {
                        return;
                    }
                    cleanup();
                    resolve(config);
                }

                function onCancel() {
                    cleanup();
                    resolve(null);
                }

                function onOverlayClick(event) {
                    if (event.target === modal) {
                        onCancel();
                    }
                }

                function onKeyDown(event) {
                    if (event.key === 'Escape') {
                        onCancel();
                    }
                }

                confirmBtn.addEventListener('click', onConfirm);
                cancelBtn.addEventListener('click', onCancel);
                if (closeBtn) {
                    closeBtn.addEventListener('click', onCancel);
                }
                if (modeBulkBtn) {
                    modeBulkBtn.addEventListener('click', onModeBulk);
                }
                if (modeIndividualBtn) {
                    modeIndividualBtn.addEventListener('click', onModeIndividual);
                }
                if (bulkLocationTabs) {
                    bulkLocationTabs.addEventListener('click', onBulkLocationClick);
                }
                if (bulkFolderSelect) {
                    bulkFolderSelect.addEventListener('change', onBulkFolderChange);
                }
                modal.addEventListener('click', onOverlayClick);
                document.addEventListener('keydown', onKeyDown);

                modal.classList.add('active');
                modal.setAttribute('aria-hidden', 'false');
                if (isMulti && modeBulkBtn) {
                    modeBulkBtn.focus();
                } else if (bulkLocationTabs) {
                    const firstBulkBtn = bulkLocationTabs.querySelector('.attachment-loc-btn');
                    if (firstBulkBtn) {
                        firstBulkBtn.focus();
                    }
                } else {
                    confirmBtn.focus();
                }
            });
        }

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }

        function getAffectedDocumentFolders(attachmentStorageList) {
            if (!attachmentStorageList || !attachmentStorageList.length) {
                return [];
            }

            const seen = new Set();
            const folders = [];

            attachmentStorageList.forEach(function(item) {
                if (!item || (item.storage_type !== 'personal' && item.storage_type !== 'matter') || !item.folder_id) {
                    return;
                }

                const key = item.storage_type + ':' + item.folder_id;
                if (seen.has(key)) {
                    return;
                }

                seen.add(key);
                folders.push({
                    storage_type: item.storage_type,
                    folder_id: String(item.folder_id)
                });
            });

            return folders;
        }

        async function reloadDocumentFolder(folder) {
            if (!folder || !folder.folder_id || typeof jQuery === 'undefined') {
                return;
            }

            const formData = new FormData();
            formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);
            formData.append('clientid', clientId);
            formData.append('folder_name', folder.folder_id);
            formData.append('type', 'client');
            formData.append('doctype', folder.storage_type === 'matter' ? 'matter' : 'personal');
            if (folder.storage_type === 'matter' && matterId) {
                formData.append('client_matter_id', matterId);
            }

            const response = await fetch(baseUrl + '/documents/reload-folder-list', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            });

            const result = await response.json();
            if (!response.ok || !result.status) {
                throw new Error(result.message || 'Failed to refresh document folder');
            }

            if (folder.storage_type === 'personal') {
                jQuery('.documnetlist_' + folder.folder_id).html(result.data || '');
                if (result.griddata) {
                    jQuery('.griddata_' + folder.folder_id).html(result.griddata);
                }
                if (typeof initPersonalDocDragDrop === 'function') {
                    initPersonalDocDragDrop();
                }
            } else {
                jQuery('.migdocumnetlist_' + folder.folder_id).html(result.data || '');
                if (result.griddata) {
                    jQuery('#' + folder.folder_id + '-subtab6 .miggriddata').html(result.griddata);
                }
                if (typeof initVisaDocDragDrop === 'function') {
                    initVisaDocDragDrop();
                }
                var activeMatterId = jQuery('#sel_matter_id_client_detail').val();
                if (window.SidebarTabs) {
                    window.SidebarTabs.selectedMatter = activeMatterId;
                }
                if (window.SidebarTabs && typeof window.SidebarTabs.filtermatterdocumentsByMatter === 'function') {
                    window.SidebarTabs.filtermatterdocumentsByMatter(activeMatterId);
                }
            }
        }

        async function refreshDocumentFoldersFromStorage(attachmentStorageList) {
            const folders = getAffectedDocumentFolders(attachmentStorageList);
            if (!folders.length) {
                return;
            }

            for (let i = 0; i < folders.length; i++) {
                try {
                    await reloadDocumentFolder(folders[i]);
                } catch (refreshError) {
                    console.warn('Failed to refresh document folder after email upload:', folders[i], refreshError);
                }
            }

            if (typeof getallactivities === 'function') {
                getallactivities();
            }
        }

        function buildOutlookUploadFormData(file, forceUpload, attachmentStorage) {
            const formData = new FormData();
            const extMatch = (file.name || '').toLowerCase().match(/\.(msg|eml)$/);
            const safeName = crmOutlookSanitizeUploadFilename(file.name, extMatch ? extMatch[1] : 'eml');
            formData.append('email_files[]', file, safeName);
            formData.append('client_id', clientId);
            formData.append('type', 'client');
            if (currentFolder === 'sent') {
                formData.append('upload_sent_mail_client_matter_id', matterId);
            } else {
                formData.append('upload_inbox_mail_client_matter_id', matterId);
            }
            formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);
            if (forceUpload) {
                formData.append('force_upload', '1');
            }
            if (attachmentStorage && attachmentStorage.length) {
                formData.append('attachment_storage', JSON.stringify(attachmentStorage));
            }
            return formData;
        }

        async function postOutlookEmailUpload(file, uploadUrl, forceUpload, attachmentStorage) {
            const response = await fetch(uploadUrl, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: buildOutlookUploadFormData(file, forceUpload, attachmentStorage)
            });

            let result;
            const responseText = await response.text();
            try {
                result = responseText ? JSON.parse(responseText) : {};
            } catch (parseError) {
                if (response.status === 403) {
                    const wafMsg = crmOutlookEmailUpload403Message(responseText, response.status);
                    throw new Error(wafMsg || 'The server blocked this upload (security filter).');
                }
                throw new Error('The server returned an invalid response. Please refresh the page and try again.');
            }

            if (!response.ok) {
                if (response.status === 403) {
                    const wafMsg = crmOutlookEmailUpload403Message(responseText, response.status);
                    throw new Error(wafMsg || (result.message || 'Upload failed (HTTP 403).'));
                }
                let errorMsg = result.message || ('Upload failed (HTTP ' + response.status + ').');
                const details = formatUploadErrorDetails(result.errors);
                if (details) {
                    errorMsg += '\n\n' + details;
                }
                throw new Error(errorMsg);
            }

            return result;
        }

        async function uploadSingleOutlookFile(file, uploadUrl, fileIndex, totalFiles) {
            const baseProgress = totalFiles > 0 ? Math.round((fileIndex / totalFiles) * 100) : 0;

            updateEmailUploadLoading(
                'Uploading email',
                'Analyzing email attachments…',
                file.name,
                baseProgress
            );

            let attachmentStorage = null;
            try {
                const previewAttachments = await previewEmailAttachments(file);
                if (previewAttachments.length > 0) {
                    hideEmailUploadLoading();
                    attachmentStorage = await showAttachmentStorageModal(previewAttachments);
                    if (attachmentStorage === null) {
                        return { uploaded: 0, failed: 0, rejected: 1, cancelled: true, errors: [] };
                    }
                    showEmailUploadLoading(
                        'Uploading email',
                        'Saving attachments and uploading email…',
                        file.name,
                        baseProgress
                    );
                }
            } catch (previewError) {
                hideEmailUploadLoading();
                console.warn('Attachment preview failed:', previewError);
                const previewMessage = (previewError && previewError.message)
                    ? previewError.message
                    : 'Could not preview attachments for this email. Ensure the Python email service is running, then try again.';
                showUploadErrorAlert(previewMessage, 'Attachment preview failed');
                return {
                    uploaded: 0,
                    failed: 1,
                    rejected: 0,
                    errors: [{ filename: file.name, error: previewMessage }]
                };
            }

            updateEmailUploadLoading(
                'Uploading email',
                'Uploading and processing email…',
                file.name,
                baseProgress + (totalFiles > 0 ? Math.round(50 / totalFiles) : 0)
            );

            let result = await postOutlookEmailUpload(file, uploadUrl, false, attachmentStorage);
            const duplicateError = getDuplicateUploadError(result);

            if (duplicateError) {
                hideEmailUploadLoading();
                const acceptUpload = await showDuplicateEmailPrompt(file.name);
                if (acceptUpload) {
                    showEmailUploadLoading(
                        'Uploading email',
                        'Uploading duplicate email…',
                        file.name,
                        baseProgress
                    );
                    result = await postOutlookEmailUpload(file, uploadUrl, true, attachmentStorage);
                } else {
                    return {
                        uploaded: 0,
                        failed: 0,
                        rejected: 1,
                        errors: [{ filename: file.name, error: DUPLICATE_EXISTS_MESSAGE, duplicate: true }]
                    };
                }
            }

            return {
                uploaded: result.uploaded || 0,
                failed: result.failed || 0,
                rejected: 0,
                errors: result.errors || [],
                warnings: result.warnings || [],
                message: result.message || '',
                attachmentStorage: attachmentStorage
            };
        }

        const handleUploadFiles = async (files) => {
            if (isEmailUploading) return;
            if (!files || files.length === 0) return;

            let resolvedFiles = Array.from(files);
            if (typeof window.crmNormalizeEmailUploadFiles === 'function') {
                resolvedFiles = await window.crmNormalizeEmailUploadFiles(resolvedFiles);
            }

            const msgFiles = (typeof window.crmFilterAllowedEmailUploadFiles === 'function')
                ? window.crmFilterAllowedEmailUploadFiles(resolvedFiles)
                : resolvedFiles.filter(function (file) {
                    return file.name.toLowerCase().endsWith('.msg') || file.name.toLowerCase().endsWith('.eml');
                });

            if (msgFiles.length === 0) {
                const allowedLabel = (typeof window.crmEmailUploadExtensionsLabel === 'function')
                    ? window.crmEmailUploadExtensionsLabel()
                    : '.msg, .eml';
                showUploadErrorAlert('Please upload Outlook email files only (' + allowedLabel + ').');
                return;
            }

            const emptyFiles = msgFiles.filter(function (file) { return !file.size; });
            if (emptyFiles.length > 0) {
                const emptyMessage = (typeof window.crmEmailUploadEmptyFileMessage === 'function')
                    ? window.crmEmailUploadEmptyFileMessage()
                    : 'The email file is empty (0 bytes) and cannot be uploaded.';
                showUploadErrorAlert(emptyMessage, 'Cannot upload directly from Outlook');
                return;
            }

            const uploadUrl = currentFolder === 'sent'
                ? `${baseUrl}/upload-sent-fetch-mail`
                : `${baseUrl}/upload-fetch-mail`;

            isEmailUploading = true;
            showEmailUploadLoading(
                'Uploading email',
                `Preparing to upload ${msgFiles.length} email${msgFiles.length > 1 ? 's' : ''}…`,
                '',
                0
            );

            if (uploadStatus) {
                uploadStatus.hidden = false;
                uploadStatus.style.color = 'var(--outlook-blue)';
                uploadStatus.textContent = `Uploading ${msgFiles.length} file(s)...`;
            }

            let totalUploaded = 0;
            let totalFailed = 0;
            let totalRejected = 0;
            const allErrors = [];
            const allWarnings = [];
            const uploadedAttachmentStorage = [];

            try {
                for (let i = 0; i < msgFiles.length; i++) {
                    const file = msgFiles[i];
                    const progressPct = Math.round((i / msgFiles.length) * 100);

                    showEmailUploadLoading(
                        'Uploading email',
                        `Processing email ${i + 1} of ${msgFiles.length}`,
                        file.name,
                        progressPct
                    );

                    if (uploadStatus) {
                        uploadStatus.textContent = `Uploading ${i + 1} of ${msgFiles.length}: ${file.name}...`;
                    }

                    try {
                        const fileResult = await uploadSingleOutlookFile(file, uploadUrl, i, msgFiles.length);
                        notifySingleFileUploadResult(file, fileResult);
                        if (fileResult.cancelled) {
                            totalRejected += 1;
                            continue;
                        }
                        totalUploaded += fileResult.uploaded;
                        totalFailed += fileResult.failed;
                        totalRejected += fileResult.rejected || 0;
                        if (fileResult.attachmentStorage && fileResult.uploaded > 0) {
                            uploadedAttachmentStorage.push.apply(uploadedAttachmentStorage, fileResult.attachmentStorage);
                        }
                        if (fileResult.errors && fileResult.errors.length) {
                            allErrors.push.apply(allErrors, fileResult.errors);
                        }
                        if (fileResult.warnings && fileResult.warnings.length) {
                            allWarnings.push.apply(allWarnings, fileResult.warnings);
                        }
                    } catch (fileError) {
                        totalFailed += 1;
                        const errorText = fileError.message || 'Upload failed';
                        allErrors.push({
                            filename: file.name,
                            error: errorText
                        });
                        showUploadFileError(file.name, errorText);
                        updateUploadStatusForFile(file.name, 'error', errorText);
                    }

                    updateEmailUploadLoading(
                        'Uploading email',
                        `Completed ${i + 1} of ${msgFiles.length}`,
                        file.name,
                        Math.round(((i + 1) / msgFiles.length) * 100)
                    );
                }

                const failedErrors = allErrors.filter(function(err) { return !err.rejected; });
                const errorDetails = formatUploadErrorDetails(failedErrors);
                const technicalDetails = buildUploadTechnicalDetails(failedErrors);
                const warningsText = (typeof window.crmFormatEmailUploadWarnings === 'function')
                    ? window.crmFormatEmailUploadWarnings(allWarnings)
                    : '';
                const refreshUploadedDocumentFolders = function() {
                    if (uploadedAttachmentStorage.length) {
                        return refreshDocumentFoldersFromStorage(uploadedAttachmentStorage);
                    }
                    return Promise.resolve();
                };

                if (totalUploaded > 0 && totalFailed === 0 && totalRejected === 0) {
                    updateEmailUploadLoading('Upload complete', 'Your email was uploaded successfully.', '', 100);
                    if (uploadStatus) {
                        uploadStatus.style.color = 'green';
                        uploadStatus.textContent = 'Upload complete!';
                    }
                    if (warningsText) {
                        showUploadResultModal(
                            'warning',
                            'Successfully uploaded ' + totalUploaded + ' email' + (totalUploaded > 1 ? 's' : '')
                                + ', but some items could not be fully saved:\n\n' + warningsText,
                            'Uploaded With Warnings'
                        );
                    } else {
                        showUploadSuccessToast('Successfully uploaded ' + totalUploaded + ' email' + (totalUploaded > 1 ? 's' : '') + '.');
                    }
                    loadEmails();
                    refreshUploadedDocumentFolders();
                } else if (totalUploaded > 0) {
                    updateEmailUploadLoading('Upload finished', 'Some emails were uploaded with issues.', '', 100);
                    if (uploadStatus) {
                        uploadStatus.style.color = 'orange';
                        uploadStatus.textContent = 'Upload completed with issues';
                    }
                    showUploadSuccessToast('Successfully uploaded ' + totalUploaded + ' email' + (totalUploaded > 1 ? 's' : '') + '.');
                    if (totalFailed > 0) {
                        let errorMsg = totalFailed + ' file' + (totalFailed > 1 ? 's' : '') + ' could not be processed.';
                        if (errorDetails) {
                            errorMsg += '\n\nError details:\n' + errorDetails;
                        }
                        if (warningsText) {
                            errorMsg += '\n\nWarnings for uploaded emails:\n' + warningsText;
                        }
                        showUploadErrorAlert(errorMsg, undefined, technicalDetails);
                    }
                    loadEmails();
                    refreshUploadedDocumentFolders();
                } else if (totalRejected > 0 && totalFailed === 0) {
                    if (uploadStatus) {
                        uploadStatus.style.color = 'orange';
                        uploadStatus.textContent = 'Upload skipped';
                    }
                } else {
                    updateEmailUploadLoading('Upload failed', 'The email could not be uploaded.', '', 100);
                    if (uploadStatus) {
                        uploadStatus.style.color = 'red';
                        uploadStatus.textContent = 'Upload failed';
                    }
                    let errorMsg = 'Upload failed. Please try again.';
                    if (errorDetails) {
                        errorMsg = 'Upload failed.\n\nError details:\n' + errorDetails;
                    }
                    showUploadErrorAlert(errorMsg, undefined, technicalDetails);
                }
            } catch (error) {
                updateEmailUploadLoading('Upload failed', error.message || 'Upload failed. Please try again.', '', 100);
                if (uploadStatus) {
                    uploadStatus.style.color = 'red';
                    uploadStatus.textContent = 'Upload failed';
                }
                showUploadErrorAlert(error.message || 'Upload failed. Please try again.');
                console.error(error);
            } finally {
                isEmailUploading = false;
                setTimeout(function() {
                    hideEmailUploadLoading();
                }, totalUploaded > 0 && totalFailed === 0 && totalRejected === 0 ? 600 : 900);
            }

            if (uploadStatus) {
                setTimeout(function() { uploadStatus.hidden = true; }, 5000);
            }
        };

        fileInput.addEventListener('change', (e) => {
            handleUploadFiles(e.target.files);
            e.target.value = ''; // reset
        });

        // Drag & Drop
        const dragDropOverlay = document.getElementById('dragDropOverlay');
        let dragCounter = 0;

        if (outlookContainer && dragDropOverlay) {
            outlookContainer.addEventListener('dragenter', (e) => {
                e.preventDefault();
                e.stopPropagation();
                if (isEmailUploading) return;
                dragCounter++;
                dragDropOverlay.style.display = 'flex';
            });

            outlookContainer.addEventListener('dragleave', (e) => {
                e.preventDefault();
                e.stopPropagation();
                if (!outlookContainer.contains(e.relatedTarget)) {
                    dragCounter--;
                    if (dragCounter <= 0) {
                        dragCounter = 0;
                        dragDropOverlay.style.display = 'none';
                    }
                }
            });

            outlookContainer.addEventListener('dragover', (e) => {
                e.preventDefault();
                e.stopPropagation();
                if (e.dataTransfer) {
                    e.dataTransfer.dropEffect = 'copy';
                }
            });

            outlookContainer.addEventListener('drop', (e) => {
                e.preventDefault();
                e.stopPropagation();
                dragCounter = 0;
                dragDropOverlay.style.display = 'none';

                if (isEmailUploading) return;

                const snapshot = (typeof window.crmCaptureEmailUploadDropSnapshot === 'function')
                    ? window.crmCaptureEmailUploadDropSnapshot(e.dataTransfer)
                    : e.dataTransfer;
                processDroppedEmailFiles(snapshot);
            });
        }
    }

    // Fetch from backend
    async function loadEmails() {
        if ((currentFolder === 'unassigned' || currentFolder === 'assigned') && !canViewSyncedInbox) {
            currentFolder = unassignedOnly ? 'unassigned' : 'inbox';
            switchToFolder(currentFolder);
        }

        emailListContainer.innerHTML = '<div class="email-list-loading">Loading emails...</div>';

        try {
            const query = searchInput.value;
            const label = labelFilter ? labelFilter.value : '';
            const sender = senderFilter ? senderFilter.value : '';
            const folderToFetch = currentFolder;
            
            const url = new URL(`${baseUrl}/clients/outlook/fetch-all`);
            url.searchParams.append('folder', folderToFetch);
            url.searchParams.append('page', currentPage);
            url.searchParams.append('search', query);
            url.searchParams.append('label_id', label);
            url.searchParams.append('sender_filter', sender);
            url.searchParams.append('sort_order', sortOrder ? sortOrder.value : 'desc');
            if (folderToFetch === 'outbox') {
                if (sendStatusFilter && sendStatusFilter.value) {
                    url.searchParams.append('send_status', sendStatusFilter.value);
                }
                if (dateFromFilter && dateFromFilter.value) {
                    url.searchParams.append('date_from', dateFromFilter.value);
                }
                if (dateToFilter && dateToFilter.value) {
                    url.searchParams.append('date_to', dateToFilter.value);
                }
            }
            
            if (clientId) url.searchParams.append('client_id', clientId);
            if (matterId) url.searchParams.append('client_matter_id', matterId);

            const response = await fetch(url, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            const data = await response.json().catch(function () {
                return {};
            });

            if (!response.ok) {
                throw new Error(data.message || ('Failed to load emails (' + response.status + ')'));
            }

            if (folderToFetch !== currentFolder) {
                return;
            }
            
            emails = Array.isArray(data.emails) ? data.emails : [];
            if (typeof data.unread_count !== 'undefined') {
                updateUnreadTabBadge(data.unread_count);
            }
            if (data.date_summary) {
                syncedDateSummary = data.date_summary;
                renderSyncedDateSummaryBar(syncedDateSummary);
            } else if (unassignedOnly) {
                syncedDateSummary = null;
                renderSyncedDateSummaryBar(null);
            }
            if (currentFolder === 'inbox') {
                emails = sortEmailsUnreadFirst(emails);
            }
            
            // Pagination
            const total = data.total || 0;
            const lastPage = data.last_page || 1;
            const from = data.from || 0;
            const to = data.to || 0;
            
            if (total > 0) {
                pageInfo.textContent = `Showing ${from}-${to} of ${total}`;
            } else {
                pageInfo.textContent = '0 records found';
            }
            
            prevBtn.disabled = currentPage <= 1;
            nextBtn.disabled = currentPage >= lastPage;

            // Update sender filter dropdown
            if (senderFilter && data.senders) {
                const currentSelection = senderFilter.value;
                let optionsHtml = '<option value="">All Senders</option>';
                data.senders.forEach(s => {
                    optionsHtml += `<option value="${s}" ${s === currentSelection ? 'selected' : ''}>${s}</option>`;
                });
                senderFilter.innerHTML = optionsHtml;
            }

            renderEmailList();
        } catch (error) {
            console.error('Failed to fetch emails', error);
            emails = [];
            emailListContainer.innerHTML = '<div style="padding:16px;text-align:center;color:red;">'
                + escapeHtml(error.message || 'Error loading emails')
                + '</div>';
            pageInfo.textContent = '0 records found';
            prevBtn.disabled = true;
            nextBtn.disabled = true;
        }
    }

    function resolveAttachmentDisplayName(att) {
        if (!att) {
            return 'Attachment';
        }
        return att.filename || att.file_name || att.display_name || 'Attachment';
    }

    function formatEmailDate(dateString) {
        if (!dateString) {
            return '';
        }
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
                return String(dateString);
            }
            const formatted = new Intl.DateTimeFormat('en-AU', {
                timeZone: appTimezone,
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: 'numeric',
                minute: '2-digit',
                hour12: true
            }).format(date);
            return formatted.replace(',', '').toLowerCase();
        } catch (e) {
            return String(dateString);
        }
    }

    function getEmailDate(email) {
        if (!email) {
            return null;
        }
        return email.sent_at || email.failed_at || email.fetch_mail_sent_time_display || email.fetch_mail_sent_time || email.received_date || email.created_at || null;
    }

    function formatFileSize(bytes) {
        const size = Number(bytes) || 0;
        if (size <= 0) {
            return '';
        }
        if (size < 1024) {
            return size + ' B';
        }
        if (size < 1024 * 1024) {
            return (size / 1024).toFixed(1) + ' KB';
        }
        return (size / (1024 * 1024)).toFixed(1) + ' MB';
    }

    function getEmailAttachmentIconClass(att) {
        const name = resolveAttachmentDisplayName(att).toLowerCase();
        const ext = name.includes('.') ? name.split('.').pop() : '';
        const type = String(att.content_type || '').toLowerCase();

        if (ext === 'pdf' || type.includes('pdf')) {
            return 'fa-file-pdf email-attachment-icon--pdf';
        }
        if (['doc', 'docx'].includes(ext) || type.includes('word')) {
            return 'fa-file-word email-attachment-icon--word';
        }
        if (['xls', 'xlsx', 'csv'].includes(ext) || type.includes('excel') || type.includes('spreadsheet')) {
            return 'fa-file-excel email-attachment-icon--excel';
        }
        if (['ppt', 'pptx'].includes(ext) || type.includes('powerpoint')) {
            return 'fa-file-powerpoint email-attachment-icon--ppt';
        }
        if (['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'].includes(ext) || type.startsWith('image/')) {
            return 'fa-file-image email-attachment-icon--image';
        }
        if (['zip', 'rar', '7z'].includes(ext)) {
            return 'fa-file-zipper';
        }
        if (ext === 'msg') {
            return 'fa-envelope';
        }
        return 'fa-file-lines';
    }

    function canPreviewEmailAttachment(att) {
        const type = String(att.content_type || '').toLowerCase();
        const name = resolveAttachmentDisplayName(att).toLowerCase();
        return type.startsWith('image/')
            || type.includes('pdf')
            || /\.(pdf|png|jpe?g|gif|webp|bmp|txt|csv)$/.test(name);
    }

    function getAttachmentDownloadUrl(att) {
        if (att.download_url) {
            return att.download_url;
        }
        if (att.id) {
            return baseUrl + '/mail-attachments/' + att.id + '/download';
        }
        return '#';
    }

    function getAttachmentPreviewUrl(att) {
        if (att.preview_url) {
            return att.preview_url;
        }
        if (att.id) {
            return baseUrl + '/mail-attachments/' + att.id + '/preview';
        }
        return '#';
    }

    function registerCidLookupKey(cidMap, key, previewUrl) {
        if (!key || !previewUrl || previewUrl === '#') {
            return;
        }
        const normalized = String(key).replace(/^<|>$/g, '').trim().toLowerCase();
        if (normalized) {
            cidMap[normalized] = previewUrl;
        }
    }

    function resolveCidPreviewUrl(cidMap, cidValue) {
        const raw = String(cidValue || '').trim();
        const normalized = raw.replace(/^<|>$/g, '').trim().toLowerCase();
        if (!normalized) {
            return null;
        }
        if (cidMap[normalized]) {
            return cidMap[normalized];
        }
        const basename = normalized.split(/[/\\]/).pop();
        if (basename && cidMap[basename]) {
            return cidMap[basename];
        }
        const withoutExt = normalized.replace(/\.[^.]+$/, '');
        if (withoutExt !== normalized && cidMap[withoutExt]) {
            return cidMap[withoutExt];
        }
        return null;
    }

    function replaceCidReferencesInHtml(htmlContent, attachments) {
        if (!htmlContent || !attachments || attachments.length === 0) {
            return htmlContent;
        }

        const cidMap = {};
        attachments.forEach(function(att) {
            if (!att || !att.id) {
                return;
            }
            const previewUrl = getAttachmentPreviewUrl(att);
            if (!previewUrl || previewUrl === '#') {
                return;
            }

            registerCidLookupKey(cidMap, att.content_id, previewUrl);

            const filename = resolveAttachmentDisplayName(att);
            registerCidLookupKey(cidMap, filename, previewUrl);
            if (filename.includes('/')) {
                registerCidLookupKey(cidMap, filename.split('/').pop(), previewUrl);
            }
            if (filename.includes('\\')) {
                registerCidLookupKey(cidMap, filename.split('\\').pop(), previewUrl);
            }
            if (att.display_name && att.display_name !== filename) {
                registerCidLookupKey(cidMap, att.display_name, previewUrl);
            }
        });

        htmlContent = htmlContent.replace(/src=["']cid:([^"'>]+)["']/gi, function(match, cidValue) {
            const previewUrl = resolveCidPreviewUrl(cidMap, cidValue);
            return previewUrl ? 'src="' + previewUrl + '"' : match;
        });

        htmlContent = htmlContent.replace(/background-image:\s*url\(["']?cid:([^"')]+)["']?\)/gi, function(match, cidValue) {
            const previewUrl = resolveCidPreviewUrl(cidMap, cidValue);
            return previewUrl ? 'background-image: url("' + previewUrl + '")' : match;
        });

        return htmlContent;
    }

    function collectEmailAttachmentItems(email) {
        const items = [];

        if (email.msg_file_url) {
            items.push({
                name: 'Original email.msg',
                size: null,
                downloadUrl: email.msg_file_url,
                previewUrl: null,
                icon: 'fa-envelope'
            });
        }

        if (email.pdf_file_url) {
            items.push({
                name: 'Parsed email.pdf',
                size: null,
                downloadUrl: email.pdf_download_url || email.pdf_file_url,
                previewUrl: email.pdf_preview_url || email.pdf_file_url,
                icon: 'fa-file-pdf email-attachment-icon--pdf'
            });
        }

        (email.attachments || []).forEach(function(att) {
            if (att.is_inline) {
                return;
            }

            items.push({
                id: att.id,
                name: resolveAttachmentDisplayName(att),
                size: att.file_size,
                downloadUrl: getAttachmentDownloadUrl(att),
                previewUrl: canPreviewEmailAttachment(att) ? getAttachmentPreviewUrl(att) : null,
                icon: getEmailAttachmentIconClass(att)
            });
        });

        return items;
    }

    /**
     * User-uploaded / extracted attachments only (excludes stored .msg/.pdf copies).
     */
    function getUserEmailAttachments(email) {
        if (!email || !Array.isArray(email.attachments)) {
            return [];
        }

        return email.attachments.filter(function(att) {
            return att && !att.is_inline;
        });
    }

    function renderEmailAttachmentListSummary(email) {
        const items = getUserEmailAttachments(email).map(function(att) {
            return {
                name: resolveAttachmentDisplayName(att)
            };
        });

        if (!items.length) {
            return '';
        }

        const lines = items.slice(0, 3).map(function(item) {
            return '<span class="email-item-attachment-line"><i class="fa-solid fa-file-lines"></i> ' + escapeHtml(item.name) + '</span>';
        }).join('');

        const extra = items.length > 3
            ? '<span class="email-item-attachment-more">+' + (items.length - 3) + ' more</span>'
            : '';

        return '<div class="email-item-attachments">' + lines + extra + '</div>';
    }

    function cleanRecipients(recipientString) {
        if (!recipientString) return '';

        const recipients = String(recipientString).split(/[,;]/);

        const validRecipients = recipients
            .map(function(r) { return r.trim(); })
            .filter(function(r) {
                if (!r) return false;
                if (r.includes('<extract_msg.') || r.includes('object at 0x')) return false;
                if (r.includes('Recipient') && r.includes('0x')) return false;
                return !r.startsWith('<') && !r.includes('0x');
            });

        return validRecipients.length > 0 ? validRecipients.join(', ') : '';
    }

    function formatRecipientLine(label, value) {
        const cleaned = cleanRecipients(value);
        if (!cleaned) return '';
        return label + ': ' + cleaned;
    }

    function renderReadingPaneAttachments(email) {
        const items = collectEmailAttachmentItems(email);
        if (!items.length) {
            return '';
        }

        const rows = items.map(function(item) {
            const sizeLabel = formatFileSize(item.size);
            const previewBtn = item.previewUrl
                ? '<a href="' + item.previewUrl + '" target="_blank" rel="noopener" class="email-attachment-btn email-attachment-btn--preview" title="Preview ' + escapeHtml(item.name) + '"><i class="fa-solid fa-eye"></i> Preview</a>'
                : '';

            return ''
                + '<div class="email-attachment-row">'
                + '  <div class="email-attachment-row__icon"><i class="fa-solid ' + item.icon + '"></i></div>'
                + '  <div class="email-attachment-row__info">'
                + '    <div class="email-attachment-row__name" title="' + escapeHtml(item.name) + '">' + escapeHtml(item.name) + '</div>'
                + (sizeLabel ? '    <div class="email-attachment-row__meta">' + escapeHtml(sizeLabel) + '</div>' : '')
                + '  </div>'
                + '  <div class="email-attachment-row__actions">'
                + previewBtn
                + '    <a href="' + item.downloadUrl + '" target="_blank" rel="noopener" class="email-attachment-btn email-attachment-btn--download" title="Download ' + escapeHtml(item.name) + '"><i class="fa-solid fa-download"></i> Download</a>'
                + '  </div>'
                + '</div>';
        }).join('');

        return ''
            + '<div class="email-attachments-panel">'
            + '  <div class="email-attachments-panel__header">'
            + '    <i class="fa-solid fa-paperclip"></i>'
            + '    <span>Attachments (' + items.length + ')</span>'
            + '  </div>'
            + '  <div class="email-attachments-panel__list">' + rows + '</div>'
            + '</div>';
    }

    function renderListEmptyState(message, hint, iconClass) {
        const icon = iconClass || 'fa-inbox';
        let html = '<div class="email-list-empty">'
            + '<div class="email-list-empty__icon" aria-hidden="true"><i class="fa-solid ' + icon + '"></i></div>'
            + '<p class="email-list-empty__title">' + escapeHtml(message) + '</p>';
        if (hint) {
            html += '<p class="email-list-empty__hint">' + escapeHtml(hint) + '</p>';
        }
        html += '</div>';
        return html;
    }

    function renderEmailList() {
        emailListContainer.innerHTML = '';
        
        if (emails.length === 0) {
            let emptyMsg = 'No emails found.';
            let emptyHint = '';
            let emptyIcon = 'fa-inbox';

            if (currentFolder === 'unread') {
                emptyMsg = 'No unread emails';
                emptyHint = 'You\'re all caught up.';
                emptyIcon = 'fa-envelope-open';
            } else if (currentFolder === 'unassigned') {
                emptyMsg = 'No unassigned emails';
                emptyHint = 'All synced mail is linked to clients. Use Sync now to fetch new mail from Zoho.';
                emptyIcon = 'fa-user-clock';
            } else if (currentFolder === 'assigned') {
                emptyMsg = 'No assigned emails yet';
                emptyHint = 'Emails you assign from the Unassigned tab will appear here.';
                emptyIcon = 'fa-user-check';
            }

            emailListContainer.innerHTML = renderListEmptyState(emptyMsg, emptyHint, emptyIcon);
            return;
        }

        let insertedEarlierDivider = false;
        const showInboxSections = currentFolder === 'inbox'
            && emails.some(function(item) { return isEmailUnread(item); })
            && emails.some(function(item) { return !isEmailUnread(item); });
        const showDateGroups = isSyncedInboxFolder(currentFolder);
        let lastDateGroup = null;

        emails.forEach(function(email, index) {
            const unread = isEmailUnread(email);

            if (showDateGroups) {
                const bucket = getEmailDateBucket(email);
                if (bucket !== lastDateGroup) {
                    const groupHeader = document.createElement('div');
                    groupHeader.className = 'email-list-date-group email-list-date-group--' + bucket;
                    const groupCount = syncedDateSummary && syncedDateSummary[bucket]
                        ? Number(syncedDateSummary[bucket])
                        : null;
                    const groupIcon = dateGroupIcons[bucket] || dateGroupIcons.earlier;
                    const countLabel = groupCount !== null && groupCount > 0
                        ? '<span class="email-list-date-group__count">' + groupCount + '</span>'
                        : '';
                    groupHeader.innerHTML = ''
                        + '<span class="email-list-date-group__icon" aria-hidden="true"><i class="fa-solid ' + groupIcon + '"></i></span>'
                        + '<span class="email-list-date-group__label">' + escapeHtml(dateGroupLabels[bucket] || 'Earlier') + '</span>'
                        + countLabel;
                    emailListContainer.appendChild(groupHeader);
                    lastDateGroup = bucket;
                }
            }

            if (showInboxSections && !unread && index > 0 && isEmailUnread(emails[index - 1]) && !insertedEarlierDivider) {
                const divider = document.createElement('div');
                divider.className = 'email-list-section-divider';
                divider.innerHTML = '<span>Earlier messages</span>';
                emailListContainer.appendChild(divider);
                insertedEarlierDivider = true;
            }

            const el = document.createElement('div');
            el.className = 'email-item' + (unread ? ' unread' : '');
            if (selectedEmailId === email.id) {
                el.classList.add('active');
            }

            const sender = email.from_mail || 'Unknown';
            const subject = email.subject || '(No Subject)';
            const preview = normalizePreviewText(email.text_preview || '', 80);
            
            const hasAttachment = getUserEmailAttachments(email).length > 0;
            const attachmentIcon = hasAttachment ? '<i class="fa-solid fa-paperclip email-list-clip" title="Has attachments"></i>' : '';
            const attachmentSummary = renderEmailAttachmentListSummary(email);

            let dateStr = isSyncedInboxFolder(currentFolder)
                ? formatListEmailDate(email)
                : formatEmailDate(getEmailDate(email));
            const statusBadge = renderSendStatusBadge(email);
            const clientBadge = renderSyncedClientBadge(email);
            const calendarIndicator = renderCalendarListIndicator(email);
            const autoAssignedBadge = unread && email.sync_assignment_status === 'auto_assigned'
                ? '<span class="email-new-badge" title="Auto-assigned from synced inbox">New</span>'
                : '';
            const unreadBadge = unread && (currentFolder === 'inbox' || isSyncedInboxFolder(currentFolder))
                ? '<span class="email-unread-label" title="Unread">Unread</span>'
                : '';
            const markReadAction = unread && isSyncedInboxFolder(currentFolder)
                ? '<button type="button" class="email-item-mark-read" title="Mark as read" aria-label="Mark as read"><i class="fa-solid fa-check"></i><span>Read</span></button>'
                : '';

            if (isSyncedInboxFolder(currentFolder)) {
                const senderInitial = escapeHtml((sender.charAt(0) || '?').toUpperCase());
                const senderName = escapeHtml(extractSenderName(sender));
                const senderAddress = escapeHtml(sender);
                const badgeRow = attachmentIcon + calendarIndicator + unreadBadge + autoAssignedBadge + statusBadge + clientBadge;
                const unreadDot = unread ? '<span class="email-item-unread-dot" aria-hidden="true"></span>' : '';
                el.classList.add('email-item--synced');
                el.innerHTML = ''
                    + '<div class="email-item-synced">'
                    + '  <div class="email-item-avatar-wrap">'
                    + '    <div class="email-item-avatar" aria-hidden="true">' + senderInitial + '</div>'
                    +      unreadDot
                    + '  </div>'
                    + '  <div class="email-item-body">'
                    + '    <div class="email-item-top">'
                    + '      <div class="email-item-from">'
                    + '        <div class="email-sender-name">' + senderName + '</div>'
                    + '        <div class="email-sender-address">' + senderAddress + '</div>'
                    + '      </div>'
                    + '      <div class="email-item-actions-top">'
                    + '        <div class="email-date">' + dateStr + '</div>'
                    +          markReadAction
                    + '      </div>'
                    + '    </div>'
                    + '    <div class="email-subject">' + escapeHtml(subject) + '</div>'
                    + '    <div class="email-preview">' + escapeHtml(preview) + '</div>'
                    + (badgeRow ? '    <div class="email-item-badges">' + badgeRow + '</div>' : '')
                    + '  </div>'
                    + '</div>';
            } else {
                el.innerHTML = `
                <div class="email-item-header">
                    <div class="email-sender">${escapeHtml(sender)}${attachmentIcon}${calendarIndicator}${unreadBadge}${autoAssignedBadge}${statusBadge}${clientBadge}</div>
                    ${markReadAction}
                </div>
                <div class="email-subject">${escapeHtml(subject)}</div>
                <div class="email-preview">${escapeHtml(preview)}</div>
                <div class="email-item-footer">
                    ${attachmentSummary}
                    <div class="email-date">${dateStr}</div>
                </div>
            `;
            }

            el.addEventListener('click', (e) => {
                if (e.target.closest('.email-item-mark-read')) {
                    e.preventDefault();
                    e.stopPropagation();
                    markEmailAsRead(email, el);
                    return;
                }
                document.querySelectorAll('.email-item').forEach(i => i.classList.remove('active'));
                el.classList.add('active');
                selectedEmailId = email.id;
                showEmail(email, el);
            });

            emailListContainer.appendChild(el);
        });
    }

    function showEmail(email, listElement) {
        emptyState.style.display = 'none';
        readingPane.classList.add('is-visible');

        selectedEmail = email;
        updateMarkReadButtonVisibility(email);

        markEmailAsRead(email, listElement);

        renderReadingPaneCalendarBanner(email);

        document.getElementById('readSubject').textContent = email.subject || '(No Subject)';
        document.getElementById('readSender').textContent = email.from_mail || 'Unknown Sender';

        const readToEl = document.getElementById('readTo');
        const readCcEl = document.getElementById('readCc');
        const toLine = formatRecipientLine('To', email.to_mail);
        readToEl.textContent = toLine || 'To: Unknown';

        if (readCcEl) {
            const ccLine = formatRecipientLine('Cc', email.cc);
            if (ccLine) {
                readCcEl.textContent = ccLine;
                readCcEl.hidden = false;
            } else {
                readCcEl.textContent = '';
                readCcEl.hidden = true;
            }
        }

        const readBccEl = document.getElementById('readBcc');
        if (readBccEl) {
            const bccLine = formatRecipientLine('Bcc', email.bcc);
            if (bccLine) {
                readBccEl.textContent = bccLine;
                readBccEl.hidden = false;
            } else {
                readBccEl.textContent = '';
                readBccEl.hidden = true;
            }
        }

        const readSendErrorEl = document.getElementById('readSendError');
        if (readSendErrorEl) {
            if (email.send_status === 'failed' && email.send_error) {
                readSendErrorEl.hidden = false;
                readSendErrorEl.innerHTML = '<strong>Send failed:</strong> ' + escapeHtml(email.send_error);
            } else {
                readSendErrorEl.hidden = true;
                readSendErrorEl.textContent = '';
            }
        }

        if (btnResend) {
            btnResend.hidden = email.send_status !== 'failed' || parseInt(email.mail_type, 10) !== 2;
        }

        if (btnAssignToClient) {
            const needsAssign = currentFolder === 'unassigned'
                && (email.sync_assignment_status === 'unassigned' || (!email.client_id && email.mailbox_email));
            btnAssignToClient.hidden = !canSyncInbox || !needsAssign;
        }
        
        document.getElementById('readDate').textContent = formatEmailDate(getEmailDate(email));

        const initial = (email.from_mail || '?').charAt(0).toUpperCase();
        document.getElementById('readAvatar').textContent = initial;

        // Render Attachments if any exist
        const attachmentsContainer = document.getElementById('attachmentsContainer');
        const attachmentHtml = renderReadingPaneAttachments(email);

        if (attachmentHtml) {
            attachmentsContainer.hidden = false;
            attachmentsContainer.innerHTML = attachmentHtml;
        } else {
            attachmentsContainer.hidden = true;
            attachmentsContainer.innerHTML = '';
        }

        const iframe = document.getElementById('readBody');
        let contentStr = (email.message || email.html_content || email.text_content || '').trim();

        let pdfToPreview = null;
        if (!contentStr) {
            if (email.pdf_preview_url || email.pdf_file_url) {
                pdfToPreview = email.pdf_preview_url || email.pdf_file_url;
            } else if (email.attachments && email.attachments.length > 0) {
                const pdfAtt = email.attachments.find(function(a) {
                    const name = resolveAttachmentDisplayName(a).toLowerCase();
                    return name.endsWith('.pdf');
                });
                if (pdfAtt) {
                    pdfToPreview = getAttachmentPreviewUrl(pdfAtt);
                }
            }
        }

        if (pdfToPreview) {
            iframe.onload = null;
            iframe.removeAttribute('srcdoc');
            iframe.style.height = '100%';
            iframe.style.minHeight = '100%';
            iframe.src = pdfToPreview;
        } else {
            iframe.removeAttribute('src');
            iframe.removeAttribute('srcdoc');
            let bodyHtml = contentStr;
            if (bodyHtml && bodyHtml.includes('<')) {
                bodyHtml = replaceCidReferencesInHtml(bodyHtml, email.attachments || []);
            } else if (bodyHtml) {
                bodyHtml = escapeHtml(bodyHtml).replace(/\n/g, '<br>');
            }
            renderHtmlIframe(iframe, bodyHtml || '<p>No content available.</p>');
        }
    }

    function renderHtmlIframe(iframe, html) {
        if (!iframe) return;
        iframe.style.height = '100%';
        iframe.style.minHeight = '100%';
        const doc = iframe.contentDocument || iframe.contentWindow.document;
        const bodyHtml = html || '';
        doc.open();
        doc.write('<!DOCTYPE html><html><head><meta charset="utf-8"><base target="_blank"><style>' +
            'html,body{height:100%;margin:0;padding:0;box-sizing:border-box;}' +
            'body{font-family:"Segoe UI",-apple-system,BlinkMacSystemFont,sans-serif;font-size:14px;line-height:1.6;color:#242424;word-wrap:break-word;overflow-wrap:break-word;padding:16px 20px;overflow-y:auto;}' +
            'img{max-width:100%;height:auto;}' +
            'table{max-width:100%;}' +
            'a{color:#0078d4;}' +
            'blockquote{margin:0;padding-left:12px;border-left:3px solid #edebe9;color:#605e5c;}' +
            'p{margin:0 0 0.75em;}' +
            '</style></head><body>' + bodyHtml + '</body></html>');
        doc.close();
    }

    function resetComposeEditor() {
        composeQuoteHtml = '';
        composeSignatureHtml = '';
        if (composeReplyInput) {
            composeReplyInput.innerHTML = '';
        }
        if (toInput) {
            toInput.value = '';
        }
        if (ccInput) {
            ccInput.value = '';
        }
        if (bccInput) {
            bccInput.value = '';
        }
        if (subjectInput) {
            subjectInput.value = '';
        }
        if (composeQuoteWrap) {
            composeQuoteWrap.hidden = true;
            composeQuoteWrap.classList.remove('is-collapsed');
        }
        if (composeQuoteToggle) {
            composeQuoteToggle.setAttribute('aria-expanded', 'true');
        }
        if (composeQuoteToggleLabel) {
            composeQuoteToggleLabel.textContent = 'Hide quoted message';
        }
        if (composeQuoteFrame) {
            composeQuoteFrame.style.height = '48px';
            renderHtmlIframe(composeQuoteFrame, '');
        }
        if (composeSignatureWrap) {
            composeSignatureWrap.hidden = true;
        }
        if (composeSignatureFrame) {
            composeSignatureFrame.style.height = '48px';
            renderHtmlIframe(composeSignatureFrame, '');
        }
    }

    function setComposeQuote(quoteHtml) {
        composeQuoteHtml = (quoteHtml || '').trim();
        if (!composeQuoteWrap || !composeQuoteFrame) {
            return;
        }
        if (!composeQuoteHtml) {
            composeQuoteWrap.hidden = true;
            return;
        }
        composeQuoteWrap.hidden = false;
        composeQuoteWrap.classList.remove('is-collapsed');
        if (composeQuoteToggle) {
            composeQuoteToggle.setAttribute('aria-expanded', 'true');
        }
        if (composeQuoteToggleLabel) {
            composeQuoteToggleLabel.textContent = 'Hide quoted message';
        }
        renderHtmlIframe(composeQuoteFrame, composeQuoteHtml);
    }

    function setComposeSignature(signatureHtml) {
        composeSignatureHtml = (signatureHtml || '').trim();
        if (!composeSignatureWrap || !composeSignatureFrame) {
            return;
        }
        if (!composeSignatureHtml) {
            composeSignatureWrap.hidden = true;
            return;
        }
        composeSignatureWrap.hidden = false;
        renderHtmlIframe(composeSignatureFrame, composeSignatureHtml);
    }

    function getComposeMessageHtml() {
        const replyHtml = composeReplyInput ? composeReplyInput.innerHTML.trim() : '';
        const parts = [];
        if (replyHtml) {
            parts.push(replyHtml);
        }
        if (composeQuoteHtml) {
            parts.push(composeQuoteHtml);
        }
        if (composeSignatureHtml) {
            parts.push('<br><br>' + composeSignatureHtml);
        }
        return parts.join('').trim();
    }

    function focusComposeReply() {
        if (!composeReplyInput) return;
        composeReplyInput.focus();
        const selection = window.getSelection();
        if (!selection) return;
        const range = document.createRange();
        range.selectNodeContents(composeReplyInput);
        range.collapse(true);
        selection.removeAllRanges();
        selection.addRange(range);
    }

    function buildQuoteHtml(email, action, emailHtml) {
        const emailDate = formatEmailDate(getEmailDate(email));
        const ccLine = cleanRecipients(email.cc);
        if (action === 'forward') {
            let header = '---------- Forwarded message ---------<br>From: <strong>' +
                escapeHtml(email.from_mail) + '</strong><br>Date: ' + escapeHtml(emailDate) +
                '<br>Subject: ' + escapeHtml(email.subject);
            if (ccLine) {
                header += '<br>Cc: ' + escapeHtml(ccLine);
            }
            return '<br><br><div dir="ltr">' + header +
                '</div><br><blockquote style="margin:0px 0px 0px 0.8ex;border-left:1px solid rgb(204,204,204);padding-left:1ex">' +
                emailHtml + '</blockquote>';
        }
        let header = '<b>From:</b> ' + escapeHtml(email.from_mail) + '<br><b>Sent:</b> ' + escapeHtml(emailDate) +
            '<br><b>Subject:</b> ' + escapeHtml(email.subject);
        const toLine = cleanRecipients(email.to_mail);
        if (toLine) {
            header += '<br><b>To:</b> ' + escapeHtml(toLine);
        }
        if (ccLine) {
            header += '<br><b>Cc:</b> ' + escapeHtml(ccLine);
        }
        return '<br><br><blockquote style="margin:0px 0px 0px 0.8ex;border-left:1px solid rgb(204,204,204);padding-left:1ex">' +
            header + '<br><br>' + emailHtml + '</blockquote>';
    }

    function formatReplySubject(subject) {
        const value = (subject || '').trim();
        if (!value) return 'Re:';
        if (/^re:/i.test(value)) return value;
        return 'Re: ' + value;
    }

    function formatForwardSubject(subject) {
        const value = (subject || '').trim();
        if (!value) return 'Fwd:';
        if (/^(fwd|fw):/i.test(value)) return value;
        return 'Fwd: ' + value;
    }

    async function fetchLoggedInStaffSignature(fromEmail) {
        if (typeof window.crmFetchStaffSignature === 'function') {
            return (await window.crmFetchStaffSignature(fromEmail || '')).trim();
        }
        if (window.__crmCurrentUserSignature) {
            return String(window.__crmCurrentUserSignature).trim();
        }
        if (!staffSignatureUrl) {
            return '';
        }
        try {
            const response = await fetch(staffSignatureUrl, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
            });
            if (!response.ok) {
                return '';
            }
            const data = await response.json();
            return (data && data.signature) ? String(data.signature).trim() : '';
        } catch (e) {
            return '';
        }
    }

    async function openCompose(action) {
        if (!selectedEmailId) return;
        const email = emails.find(e => e.id === selectedEmailId);
        if (!email) return;

        composeModal.classList.add('active');
        resetComposeEditor();
        composeResendLogId = null;
        if (btnSendEl) {
            btnSendEl.innerHTML = '<i class="fa-solid fa-paper-plane" aria-hidden="true"></i> Send';
        }

        composeFromEmail = getDefaultComposeFromAddress();
        composeReplyToEmailId = email.id || null;
        setComposeFromField(composeFromEmail);

        let emailHtml = '';
        if (email.html_content) {
            emailHtml = email.html_content;
        } else if (email.message && email.message.includes('<')) {
            emailHtml = email.message;
        } else if (email.text_content) {
            emailHtml = escapeHtml(email.text_content).replace(/\n/g, '<br>');
        } else if (email.message) {
            emailHtml = escapeHtml(email.message).replace(/\n/g, '<br>');
        }

        if (action === 'reply') {
            composeTitle.textContent = 'Reply';
            const sender = parseRecipientField(email.from_mail);
            toInput.value = sender.length ? sender[0] : (email.from_mail || '');
            if (ccInput) {
                ccInput.value = cleanRecipients(email.cc) || '';
            }
            subjectInput.value = formatReplySubject(email.subject);
        } else if (action === 'replyAll') {
            composeTitle.textContent = 'Reply All';
            const toParts = [email.from_mail || '', cleanRecipients(email.to_mail)]
                .filter(function(part) { return part; });
            toInput.value = toParts.join(', ');
            if (ccInput) {
                ccInput.value = cleanRecipients(email.cc) || '';
            }
            subjectInput.value = formatReplySubject(email.subject);
        } else if (action === 'forward') {
            composeTitle.textContent = 'Forward';
            toInput.value = '';
            if (ccInput) {
                ccInput.value = '';
            }
            if (bccInput) {
                bccInput.value = '';
            }
            subjectInput.value = formatForwardSubject(email.subject);
        }

        setComposeQuote(buildQuoteHtml(email, action, emailHtml));

        try {
            const signatureHtml = await fetchLoggedInStaffSignature(composeFromEmail);
            setComposeSignature(signatureHtml);
        } catch (e) {
            setComposeSignature('');
        }

        focusComposeReply();
    }

    async function openComposeResend() {
        if (!selectedEmailId) return;
        const email = emails.find(function (e) { return e.id === selectedEmailId; });
        if (!email || email.send_status !== 'failed') return;

        composeModal.classList.add('active');
        resetComposeEditor();
        composeReplyToEmailId = null;
        composeResendLogId = email.id;
        setComposeFromField(email.from_mail || getDefaultComposeFromAddress());

        composeTitle.textContent = 'Resend Email';
        if (btnSendEl) {
            btnSendEl.innerHTML = '<i class="fa-solid fa-rotate-right" aria-hidden="true"></i> Resend';
        }

        toInput.value = cleanRecipients(email.to_mail) || email.to_mail || '';
        if (ccInput) {
            ccInput.value = cleanRecipients(email.cc) || email.cc || '';
        }
        if (bccInput) {
            bccInput.value = cleanRecipients(email.bcc) || email.bcc || '';
        }
        subjectInput.value = email.subject || '';

        setComposeQuote('');
        setComposeSignature('');

        const messageHtml = email.message || email.html_content || email.text_content || '';
        if (composeReplyInput) {
            composeReplyInput.innerHTML = messageHtml.includes('<') ? messageHtml : escapeHtml(messageHtml).replace(/\n/g, '<br>');
        }

        focusComposeReply();
    }

    async function handleDeleteEmail() {
        if (!selectedEmailId) {
            crmToast('No email selected for delete.', 'warning');
            return;
        }

        const email = emails.find(function(e) { return e.id === selectedEmailId; });
        if (!email) {
            crmToast('No email selected for delete.', 'warning');
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

        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
        const payload = {};
        if (clientId) {
            payload.client_id = clientId;
        }
        if (matterId) {
            payload.client_matter_id = matterId;
        }

        try {
            const response = await fetch(baseUrl + '/email-logs/' + email.id + '/delete', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfMeta ? csrfMeta.content : ''
                },
                body: JSON.stringify(payload)
            });

            const data = await response.json().catch(function() { return {}; });

            if (response.ok && data.success) {
                selectedEmailId = null;
                readingPane.classList.remove('is-visible');
                emptyState.style.display = 'flex';
                loadEmails();
                return;
            }

            crmToast(data.message || ('Failed to delete email (' + response.status + ')'), 'error');
        } catch (error) {
            crmToast('Failed to delete email: ' + (error && error.message ? error.message : 'Unknown error'), 'error');
        }
    }

    function normalizePreviewText(text, maxLen) {
        if (!text) return '';
        const textarea = document.createElement('textarea');
        textarea.innerHTML = String(text).replace(/<[^>]+>/g, ' ');
        const decoded = textarea.value.replace(/\s+/g, ' ').trim();
        return decoded.substring(0, maxLen || 80);
    }

    function escapeHtml(unsafe) {
        if (!unsafe) return '';
        return unsafe
             .replace(/&/g, "&amp;")
             .replace(/</g, "&lt;")
             .replace(/>/g, "&gt;")
             .replace(/"/g, "&quot;")
             .replace(/'/g, "&#039;");
    }

    function getCsrfToken() {
        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
        return csrfMeta ? csrfMeta.content : '';
    }

    function updateAssignConfirmButton() {
        if (!assignEmailConfirmBtn) {
            return;
        }
        const clientTs = assignClientSelect && assignClientSelect.tomselect ? assignClientSelect.tomselect : null;
        const clientId = assignClientSelect
            ? extractClientIdFromTomSelectValue(assignClientSelect.value, clientTs)
            : null;
        const hasClient = !!clientId;
        const hasMatter = assignMatterHiddenInput && assignMatterHiddenInput.value !== '';
        assignEmailConfirmBtn.disabled = !(hasClient && hasMatter);
    }

    function updateAssignStepProgress() {
        const clientTs = assignClientSelect && assignClientSelect.tomselect ? assignClientSelect.tomselect : null;
        const hasClient = !!extractClientIdFromTomSelectValue(
            assignClientSelect ? assignClientSelect.value : '',
            clientTs
        );
        const hasMatter = assignMatterHiddenInput && assignMatterHiddenInput.value !== '';

        if (assignStepClient) {
            assignStepClient.classList.toggle('assign-email-step--active', !hasClient);
            assignStepClient.classList.toggle('assign-email-step--done', hasClient);
        }
        if (assignStepMatter) {
            assignStepMatter.classList.toggle('assign-email-step--active', hasClient && !hasMatter);
            assignStepMatter.classList.toggle('assign-email-step--done', hasMatter);
        }
        if (assignStepConnector) {
            assignStepConnector.classList.toggle('assign-email-step__connector--done', hasClient);
        }
        if (assignClientField) {
            assignClientField.classList.toggle('assign-email-field--done', hasClient);
        }
        if (assignMatterField) {
            assignMatterField.classList.toggle('assign-email-field--disabled', !hasClient);
        }
    }

    function populateAssignEmailPreview() {
        if (!assignEmailPreviewSubject || !assignEmailPreviewMeta) {
            return;
        }

        const email = selectedEmailId
            ? emails.find(function (item) { return item.id === selectedEmailId; })
            : null;

        if (!email) {
            assignEmailPreviewSubject.textContent = '(No subject)';
            assignEmailPreviewMeta.textContent = '';
            return;
        }

        assignEmailPreviewSubject.textContent = email.subject || '(No subject)';

        const metaParts = [];
        if (email.from_mail) {
            metaParts.push('From ' + email.from_mail);
        }
        const dateStr = formatEmailDate(getEmailDate(email));
        if (dateStr) {
            metaParts.push(dateStr);
        }
        assignEmailPreviewMeta.textContent = metaParts.join(' · ');
    }

    function setAssignMatterHint(message, state) {
        if (!assignMatterHint) {
            return;
        }
        if (!message) {
            assignMatterHint.textContent = '';
            assignMatterHint.hidden = true;
            return;
        }
        assignMatterHint.hidden = false;
        assignMatterHint.textContent = message;
        assignMatterHint.classList.toggle('assign-email-field__hint--loading', state === 'loading');
        assignMatterHint.classList.toggle('assign-email-field__hint--success', state === 'success');
        assignMatterHint.classList.toggle('assign-email-field__hint--error', state === 'error');
    }

    function setAssignMatterFieldState(state) {
        if (assignMatterField) {
            assignMatterField.classList.toggle('assign-email-field--ready', state === 'ready');
            assignMatterField.classList.toggle('assign-email-field--loading', state === 'loading');
            assignMatterField.classList.toggle('assign-email-field--empty', state === 'empty');
        }
        if (assignMatterPicker) {
            assignMatterPicker.classList.toggle('assign-matter-picker--loading', state === 'loading');
            assignMatterPicker.classList.toggle('assign-matter-picker--ready', state === 'ready');
            assignMatterPicker.classList.toggle('assign-matter-picker--empty', state === 'empty');
        }
    }

    function setAssignMatterPickerView(view) {
        if (assignMatterPlaceholder) {
            assignMatterPlaceholder.hidden = view !== 'placeholder';
        }
        if (assignMatterLoading) {
            assignMatterLoading.hidden = view !== 'loading';
        }
        if (assignMatterList) {
            assignMatterList.hidden = view !== 'list';
        }
    }

    function clearAssignMatterSelection() {
        if (assignMatterHiddenInput) {
            assignMatterHiddenInput.value = '';
        }
        if (assignMatterList) {
            assignMatterList.innerHTML = '';
        }
        setAssignMatterPickerView('placeholder');
        setAssignMatterFieldState('idle');
        setAssignMatterHint('', null);
        updateAssignStepProgress();
    }

    function selectAssignMatter(matterId) {
        if (!assignMatterHiddenInput || !assignMatterList) {
            return;
        }
        assignMatterHiddenInput.value = String(matterId);
        assignMatterList.querySelectorAll('.assign-matter-card').forEach(function (card) {
            const isSelected = card.getAttribute('data-matter-id') === String(matterId);
            card.classList.toggle('assign-matter-card--selected', isSelected);
            card.setAttribute('aria-pressed', isSelected ? 'true' : 'false');
        });
        updateAssignConfirmButton();
        updateAssignStepProgress();
    }

    function renderAssignMatterPicker(matters, preferredMatterId) {
        if (!assignMatterList || !assignMatterHiddenInput) {
            return;
        }

        assignMatterList.innerHTML = '';
        assignMatterHiddenInput.value = '';

        if (!matters || matters.length === 0) {
            setAssignMatterPickerView('placeholder');
            if (assignMatterPlaceholder) {
                assignMatterPlaceholder.innerHTML = '<i class="fa-solid fa-folder-open" aria-hidden="true"></i><span>No matters found for this client.</span>';
            }
            return;
        }

        let autoSelectId = preferredMatterId || null;
        if (!autoSelectId && matters.length === 1) {
            autoSelectId = matters[0].id;
        }

        matters.forEach(function (matter) {
            const ref = (matter.client_unique_matter_no || '').trim();
            const title = (matter.title || '').trim();
            const card = document.createElement('button');
            card.type = 'button';
            card.className = 'assign-matter-card';
            card.setAttribute('data-matter-id', String(matter.id));
            card.setAttribute('aria-pressed', 'false');
            card.innerHTML =
                '<span class="assign-matter-card__main">'
                + '<span class="assign-matter-card__ref">' + escapeHtml(ref || 'Matter') + '</span>'
                + (title ? '<span class="assign-matter-card__title">' + escapeHtml(title) + '</span>' : '')
                + '</span>'
                + '<span class="assign-matter-card__check" aria-hidden="true"><i class="fa-solid fa-check"></i></span>';
            card.addEventListener('click', function () {
                selectAssignMatter(matter.id);
            });
            assignMatterList.appendChild(card);
        });

        setAssignMatterPickerView('list');
        if (autoSelectId) {
            selectAssignMatter(autoSelectId);
        } else {
            updateAssignStepProgress();
        }
    }

    function extractClientIdFromTomSelectValue(value, tsInstance) {
        if (!value) {
            return null;
        }
        const option = tsInstance && tsInstance.options ? tsInstance.options[value] : null;
        if (option && option.cid != null && option.cid !== '') {
            const fromCid = parseInt(option.cid, 10);
            if (!isNaN(fromCid)) {
                return fromCid;
            }
        }
        const numeric = parseInt(String(value), 10);
        if (!isNaN(numeric) && String(numeric) === String(value).trim()) {
            return numeric;
        }
        return null;
    }

    function extractMatterRefFromTomSelectValue(value) {
        if (!value) {
            return null;
        }
        const str = String(value);
        const marker = '/Matter/';
        const markerIndex = str.indexOf(marker);
        if (markerIndex >= 0) {
            const ref = str.substring(markerIndex + marker.length).trim();
            return ref !== '' ? ref : null;
        }
        return null;
    }

    function destroyAssignEmailModalSelects() {
        if (typeof destroyTS !== 'function') {
            return;
        }
        if (assignClientSelect) {
            destroyTS(assignClientSelect);
        }
    }

    function initAssignEmailModalSelects() {
        if (!assignEmailModal || typeof initTS !== 'function') {
            return;
        }

        destroyAssignEmailModalSelects();

        const dropdownParent = 'body';
        const clientsUrl = baseUrl
            ? baseUrl.replace(/\/$/, '') + '/clients/get-allclients'
            : '';

        if (assignClientSelect) {
            let clientTs = null;
            if (typeof buildGetAllClientsTomSelectConfig === 'function' && clientsUrl) {
                clientTs = initTS(assignClientSelect, buildGetAllClientsTomSelectConfig({
                    url: clientsUrl,
                    dropdownParent: dropdownParent,
                    placeholder: 'Type name, email, or client ref...',
                    onChange: function (value) {
                        clearAssignMatterSelection();
                        if (!value) {
                            updateAssignConfirmButton();
                            updateAssignStepProgress();
                            return;
                        }
                        const option = this.options[value] || {};
                        if (option.locked) {
                            this.clear(true);
                            if (typeof window.openCrmAccessModal === 'function') {
                                window.openCrmAccessModal(option);
                            }
                            updateAssignConfirmButton();
                            updateAssignStepProgress();
                            return;
                        }
                        const clientId = extractClientIdFromTomSelectValue(value, this);
                        const matterRef = extractMatterRefFromTomSelectValue(value);
                        loadAssignMattersForClient(clientId, matterRef);
                        updateAssignConfirmButton();
                        updateAssignStepProgress();
                    }
                }));
                if (clientTs && clientTs.wrapper) {
                    clientTs.wrapper.classList.add('assign-email-ts');
                    clientTs.wrapper.style.width = '100%';
                }
            } else {
                clientTs = initTS(assignClientSelect, {
                    create: false,
                    dropdownParent: dropdownParent
                });
            }
        }

        updateAssignConfirmButton();
        updateAssignStepProgress();
    }

    function loadAssignMattersForClient(selectedClientId, preferredMatterRef) {
        if (!mattersUrl || !selectedClientId) {
            clearAssignMatterSelection();
            if (assignMatterPlaceholder) {
                assignMatterPlaceholder.innerHTML = '<i class="fa-solid fa-arrow-up" aria-hidden="true"></i><span>Choose a client first to see their matters.</span>';
            }
            updateAssignConfirmButton();
            return;
        }

        if (assignMatterHiddenInput) {
            assignMatterHiddenInput.value = '';
        }
        setAssignMatterFieldState('loading');
        setAssignMatterPickerView('loading');
        setAssignMatterHint('Loading matters...', 'loading');
        updateAssignConfirmButton();
        updateAssignStepProgress();

        fetch(mattersUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: new URLSearchParams({ client_id: String(selectedClientId) }).toString()
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }
                return response.json();
            })
            .then(function (data) {
                const matters = (data && data.clientMatetrs) ? data.clientMatetrs : [];
                let preferredMatterId = null;

                matters.forEach(function (matter) {
                    const matterRef = String(matter.client_unique_matter_no || '').trim();
                    const selectedByContext = matterId && String(matter.id) === String(matterId);
                    const selectedByRef = preferredMatterRef
                        && matterRef.toLowerCase() === String(preferredMatterRef).toLowerCase();
                    if (selectedByContext || selectedByRef) {
                        preferredMatterId = matter.id;
                    }
                });

                const matterCount = matters.length;
                if (matterCount === 0) {
                    setAssignMatterFieldState('empty');
                    setAssignMatterHint('No matters found for this client. Create a matter first.', 'error');
                    renderAssignMatterPicker([], null);
                } else {
                    setAssignMatterFieldState('ready');
                    setAssignMatterHint(
                        matterCount === 1
                            ? 'Tap the matter below to confirm.'
                            : matterCount + ' matters — tap the one this email relates to.',
                        'success'
                    );
                    renderAssignMatterPicker(matters, preferredMatterId);
                }
                updateAssignConfirmButton();
                updateAssignStepProgress();
            })
            .catch(function () {
                setAssignMatterFieldState('empty');
                setAssignMatterPickerView('placeholder');
                if (assignMatterPlaceholder) {
                    assignMatterPlaceholder.innerHTML = '<i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i><span>Could not load matters. Try again.</span>';
                }
                setAssignMatterHint('Could not load matters. Try selecting the client again.', 'error');
                updateAssignConfirmButton();
                updateAssignStepProgress();
            });
    }

    if (assignClientSelect && typeof buildGetAllClientsTomSelectConfig !== 'function') {
        assignClientSelect.addEventListener('change', function () {
            loadAssignMattersForClient(assignClientSelect.value);
            updateAssignConfirmButton();
        });
    }

    if (btnAssignToClient && assignEmailModal) {
        btnAssignToClient.addEventListener('click', function () {
            if (!selectedEmailId) {
                crmToast('Select an email to assign.', 'warning');
                return;
            }
            if (assignEmailLogIdInput) {
                assignEmailLogIdInput.value = selectedEmailId;
            }
            if (assignEmailStatus) {
                assignEmailStatus.hidden = true;
                assignEmailStatus.textContent = '';
            }
            populateAssignEmailPreview();
            updateAssignConfirmButton();
            updateAssignStepProgress();
            if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                bootstrap.Modal.getOrCreateInstance(assignEmailModal).show();
            } else {
                document.body.classList.add('assign-email-modal-open');
                assignEmailModal.classList.add('show');
                assignEmailModal.style.display = 'block';
                initAssignEmailModalSelects();
                if (clientId && assignClientSelect) {
                    assignClientSelect.value = clientId;
                    loadAssignMattersForClient(clientId);
                }
            }
        });

        assignEmailModal.addEventListener('shown.bs.modal', function () {
            document.body.classList.add('assign-email-modal-open');
            initAssignEmailModalSelects();
            if (clientId && assignClientSelect) {
                const ts = assignClientSelect.tomselect;
                if (ts) {
                    ts.setValue(clientId, true);
                } else {
                    assignClientSelect.value = clientId;
                }
                loadAssignMattersForClient(clientId);
            }
            updateAssignStepProgress();
        });

        assignEmailModal.addEventListener('hidden.bs.modal', function () {
            document.body.classList.remove('assign-email-modal-open');
            destroyAssignEmailModalSelects();
            clearAssignMatterSelection();
            if (assignMatterPlaceholder) {
                assignMatterPlaceholder.innerHTML = '<i class="fa-solid fa-arrow-up" aria-hidden="true"></i><span>Choose a client first to see their matters.</span>';
            }
            if (assignEmailConfirmBtn) {
                assignEmailConfirmBtn.disabled = true;
            }
        });
    }

    if (assignEmailConfirmBtn) {
        assignEmailConfirmBtn.addEventListener('click', async function () {
            const emailLogId = assignEmailLogIdInput ? assignEmailLogIdInput.value : '';
            const clientTs = assignClientSelect && assignClientSelect.tomselect ? assignClientSelect.tomselect : null;
            const selectedClientRaw = assignClientSelect ? assignClientSelect.value : '';
            const selectedClientId = extractClientIdFromTomSelectValue(selectedClientRaw, clientTs);
            const selectedMatter = assignMatterHiddenInput ? assignMatterHiddenInput.value : '';

            if (!emailLogId || !selectedClientId || !selectedMatter) {
                crmToast('Please select both client and matter.', 'warning');
                return;
            }

            assignEmailConfirmBtn.disabled = true;
            try {
                const response = await fetch(assignEmailUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': getCsrfToken(),
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        email_log_id: parseInt(emailLogId, 10),
                        client_id: selectedClientId,
                        client_matter_id: parseInt(selectedMatter, 10)
                    })
                });
                const data = await response.json();
                if (response.ok && data.success) {
                    if (typeof bootstrap !== 'undefined' && bootstrap.Modal && assignEmailModal) {
                        bootstrap.Modal.getOrCreateInstance(assignEmailModal).hide();
                    }
                    if (btnAssignToClient) {
                        btnAssignToClient.hidden = true;
                    }
                    crmToast(data.message || 'Email assigned to client successfully.', 'success', 'Assigned');
                    if (! unassignedOnly) {
                        switchToFolder('inbox');
                    }
                    loadEmails();
                    return;
                }
                crmToast(data.message || 'Could not assign email.', 'error');
            } catch (error) {
                crmToast('Could not assign email: ' + (error.message || 'Unknown error'), 'error');
            } finally {
                assignEmailConfirmBtn.disabled = false;
            }
        });
    }

    function buildInboxSyncResultMessage(data, rangeLabel) {
        const detail = data.message
            || ('Imported: ' + (data.total_imported || 0)
                + ', Skipped: ' + (data.total_skipped || 0)
                + ', Failed: ' + (data.total_failed || 0));
        const prefix = rangeLabel ? ('Sync complete (' + rangeLabel + '). ') : 'Sync complete. ';
        return prefix + detail;
    }

    function setSyncUiBusy(isBusy, originalHtml) {
        if (btnSyncInbox) {
            btnSyncInbox.disabled = isBusy;
            if (isBusy) {
                btnSyncInbox.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i><span>Syncing...</span>';
            } else if (originalHtml) {
                btnSyncInbox.innerHTML = originalHtml;
            }
        }
        if (syncRangeFilter) {
            syncRangeFilter.disabled = isBusy;
        }
        if (syncMailboxFilter) {
            syncMailboxFilter.disabled = isBusy;
        }
    }

    function resolveManualSyncEmail() {
        if (canSelectSyncMailbox && syncMailboxFilter) {
            return syncMailboxFilter.value.trim();
        }

        const staffSyncMailboxes = outlookContainer
            ? JSON.parse(outlookContainer.getAttribute('data-staff-sync-mailboxes') || '[]')
            : [];
        if (staffSyncMailboxes.length) {
            return staffSyncMailboxes[0];
        }

        const mailboxAddresses = outlookContainer
            ? JSON.parse(outlookContainer.getAttribute('data-mailbox-addresses') || '[]')
            : [];

        return mailboxAddresses.length ? mailboxAddresses[0] : '';
    }

    function pollInboxSyncStatus(syncId, rangeLabel, startedAt, originalHtml) {
        const pollIntervalMs = 2000;
        const backgroundAfterMs = 10000;
        let backgroundNotified = false;
        let pollTimer = null;

        function finishSyncUi() {
            if (pollTimer) {
                clearInterval(pollTimer);
                pollTimer = null;
            }
            setSyncUiBusy(false, originalHtml);
        }

        async function checkStatus() {
            if (!syncStatusUrlBase || !syncId) {
                finishSyncUi();
                crmToast('Could not check sync status.', 'error');
                return;
            }

            try {
                const response = await fetch(syncStatusUrlBase + '/' + encodeURIComponent(syncId), {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin'
                });

                let data = {};
                try {
                    data = await response.json();
                } catch (parseError) {
                    throw new Error('Unexpected server response while checking sync status.');
                }

                if (!response.ok || data.success === false) {
                    finishSyncUi();
                    crmToast(data.message || ('Sync status failed (HTTP ' + response.status + ').'), 'error');
                    return;
                }

                const status = String(data.status || 'pending');
                const elapsed = Date.now() - startedAt;

                if (elapsed >= backgroundAfterMs && !backgroundNotified && (status === 'pending' || status === 'running')) {
                    backgroundNotified = true;
                    setSyncUiBusy(false, originalHtml);
                    crmToast('Sync is continuing in the background. You will be notified when it finishes.', 'info');
                }

                if (status === 'completed') {
                    finishSyncUi();
                    loadEmails();
                    crmToast(buildInboxSyncResultMessage(data, rangeLabel), 'success');
                    return;
                }

                if (status === 'failed') {
                    finishSyncUi();
                    crmToast(data.message || 'Inbox sync failed.', 'error');
                    return;
                }
            } catch (error) {
                finishSyncUi();
                crmToast('Sync failed: ' + (error.message || 'Unknown error'), 'error');
            }
        }

        checkStatus();
        pollTimer = setInterval(checkStatus, pollIntervalMs);
    }

    let fullSyncConfirmResolver = null;

    function closeFullSyncConfirmModal(result) {
        const modal = document.getElementById('fullSyncConfirmModal');
        if (modal) {
            modal.classList.remove('active');
            modal.setAttribute('aria-hidden', 'true');
        }
        if (fullSyncConfirmResolver) {
            const resolve = fullSyncConfirmResolver;
            fullSyncConfirmResolver = null;
            resolve(!!result);
        }
    }

    function confirmFullSync() {
        const modal = document.getElementById('fullSyncConfirmModal');
        if (!modal) {
            return Promise.resolve(window.confirm(
                'Full sync resets mailbox tracking and re-imports recent mail. Messages already in the CRM will be skipped. Continue?'
            ));
        }

        return new Promise(function(resolve) {
            fullSyncConfirmResolver = resolve;
            modal.classList.add('active');
            modal.setAttribute('aria-hidden', 'false');
            const proceedBtn = document.getElementById('fullSyncConfirmProceed');
            if (proceedBtn) {
                proceedBtn.focus();
            }
        });
    }

    (function initFullSyncConfirmModal() {
        const modal = document.getElementById('fullSyncConfirmModal');
        if (!modal || modal.dataset.bound === '1') {
            return;
        }
        modal.dataset.bound = '1';

        const cancelBtn = document.getElementById('fullSyncConfirmCancel');
        const proceedBtn = document.getElementById('fullSyncConfirmProceed');

        if (cancelBtn) {
            cancelBtn.addEventListener('click', function() {
                closeFullSyncConfirmModal(false);
            });
        }
        if (proceedBtn) {
            proceedBtn.addEventListener('click', function() {
                closeFullSyncConfirmModal(true);
            });
        }

        modal.addEventListener('click', function(event) {
            if (event.target === modal) {
                closeFullSyncConfirmModal(false);
            }
        });

        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape' && modal.classList.contains('active')) {
                closeFullSyncConfirmModal(false);
            }
        });
    })();

    if (btnSyncInbox && syncInboxUrl && canSyncInbox) {
        btnSyncInbox.addEventListener('click', async function () {
            const syncRange = syncRangeFilter ? syncRangeFilter.value : 'today';
            if (syncRange === 'full') {
                const proceed = await confirmFullSync();
                if (! proceed) {
                    return;
                }
            }

            const originalHtml = btnSyncInbox.innerHTML;
            const startedAt = Date.now();
            const syncEmail = resolveManualSyncEmail();

            if (canSelectSyncMailbox && !syncEmail) {
                crmToast('Please select a mailbox to sync.', 'warning');
                return;
            }

            setSyncUiBusy(true);

            try {
                const params = new URLSearchParams({ sync_range: syncRange });
                if (syncEmail) {
                    params.append('email', syncEmail);
                }

                const response = await fetch(syncInboxUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': getCsrfToken(),
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: params.toString()
                });

                let data = {};
                try {
                    data = await response.json();
                } catch (parseError) {
                    throw new Error('Unexpected server response. The sync request may have been blocked.');
                }

                if (!response.ok || data.success === false) {
                    setSyncUiBusy(false, originalHtml);
                    crmToast(data.message || ('Sync failed (HTTP ' + response.status + ').'), 'error');
                    return;
                }

                const rangeLabel = syncRangeFilter
                    ? syncRangeFilter.options[syncRangeFilter.selectedIndex].text
                    : syncRange;

                if (data.background && data.sync_id) {
                    pollInboxSyncStatus(data.sync_id, rangeLabel, startedAt, originalHtml);
                    return;
                }

                setSyncUiBusy(false, originalHtml);
                loadEmails();
                crmToast(buildInboxSyncResultMessage(data, rangeLabel), (data.total_failed || 0) > 0 ? 'error' : 'success');
            } catch (error) {
                setSyncUiBusy(false, originalHtml);
                crmToast('Sync failed: ' + (error.message || 'Unknown error'), 'error');
            }
        });
    }
});
