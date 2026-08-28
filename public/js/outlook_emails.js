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
    let currentFolder = 'inbox'; // inbox, sent, outbox, unassigned, assigned, review
    let emails = [];
    let selectedEmailId = null;

    // Elements
    const outlookContainer = document.getElementById('outlookContainer');
    const appTimezone = (outlookContainer && outlookContainer.dataset.appTimezone) || 'Australia/Melbourne';
    const unassignedOnly = !!(outlookContainer && outlookContainer.getAttribute('data-unassigned-only') === '1');
    const compactPagination = !!(outlookContainer && outlookContainer.getAttribute('data-compact-pagination') === '1');
    const useEmailInfiniteScroll = unassignedOnly || compactPagination;
    let defaultFolder = (outlookContainer && outlookContainer.getAttribute('data-default-folder')) || 'inbox';
    // Client matter Emails tab only has Inbox/Sent — never start on synced-mail folders.
    if (!unassignedOnly && (defaultFolder === 'unassigned' || defaultFolder === 'assigned' || defaultFolder === 'review')) {
        defaultFolder = 'inbox';
    }
    const folderItems = outlookContainer
        ? outlookContainer.querySelectorAll('.folder-item')
        : document.querySelectorAll('.folder-item');
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
    const pageSummary = document.getElementById('pageSummary');
    const listTotalCount = document.getElementById('listTotalCount');
    const prevBtn = document.getElementById('prevBtn');
    const nextBtn = document.getElementById('nextBtn');
    const perPageSelect = document.getElementById('perPageSelect');
    const PER_PAGE_OPTIONS = [10, 20, 50, 100, 200, 500];
    const PER_PAGE_STORAGE_KEY = 'outlook_emails_per_page';

    function readStoredPerPage() {
        try {
            const stored = parseInt(localStorage.getItem(PER_PAGE_STORAGE_KEY), 10);
            if (PER_PAGE_OPTIONS.indexOf(stored) !== -1) {
                return stored;
            }
        } catch (error) {
            // Ignore storage failures.
        }
        return 20;
    }

    let perPage = useEmailInfiniteScroll ? 20 : readStoredPerPage();
    if (perPageSelect) {
        perPageSelect.value = String(perPage);
    }
    
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
    const composeCcField = document.getElementById('composeCcField');
    const composeBccField = document.getElementById('composeBccField');
    const composeShowCc = document.getElementById('composeShowCc');
    const composeShowBcc = document.getElementById('composeShowBcc');
    const composeFormatBar = document.getElementById('composeFormatBar');
    const btnSendEl = document.getElementById('btnSend');
    const btnResend = document.getElementById('btnResend');
    const btnAssignToClient = document.getElementById('btnAssignToClient');
    const btnUnlinkFromClient = document.getElementById('btnUnlinkFromClient');
    const assignmentReviewBanner = document.getElementById('assignmentReviewBanner');
    const btnSyncInbox = document.getElementById('btnSyncInbox');
    const btnAssignBySubject = document.getElementById('btnAssignBySubject');
    const assignBySubjectModal = document.getElementById('assignBySubjectModal');
    const assignBySubjectModalBody = document.getElementById('assignBySubjectModalBody');
    const assignBySubjectModalSubtitle = document.getElementById('assignBySubjectModalSubtitle');
    const assignBySubjectConfirmBtn = document.getElementById('assignBySubjectConfirmBtn');
    const assignBySubjectUrl = outlookContainer ? outlookContainer.getAttribute('data-assign-by-subject-url') : '';
    const assignBySubjectConfirmUrl = outlookContainer ? outlookContainer.getAttribute('data-assign-by-subject-confirm-url') : '';
    const syncRangeFilter = document.getElementById('syncRangeFilter');
    const syncMailboxFilter = document.getElementById('syncMailboxFilter');
    const listMailboxFilter = document.getElementById('listMailboxFilter');
    const assignEmailModal = document.getElementById('assignSyncedEmailModal');
    const assignEmailModalTitle = document.getElementById('assignSyncedEmailModalLabel');
    const assignEmailModalSubtitle = document.getElementById('assignSyncedEmailModalSubtitle');
    const assignEmailModalIcon = document.getElementById('assignSyncedEmailModalIcon');
    const unlinkEmailDestination = document.getElementById('unlinkEmailDestination');
    const unlinkDestinationButtons = document.querySelectorAll('[data-unlink-destination]');
    const assignEmailFields = document.getElementById('assignEmailFields');
    const assignClientSelect = document.getElementById('assignClientId');
    const assignClientPicker = document.getElementById('assignClientPicker');
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
    const assignClientHint = document.getElementById('assignClientHint');
    const assignMatterField = document.getElementById('assignMatterField');
    const assignClientField = document.getElementById('assignClientField');
    const assignSenderHint = document.getElementById('assignSenderHint');
    const assignSearchSenderBtn = document.getElementById('assignSearchSenderBtn');
    const assignSelectedClient = document.getElementById('assignSelectedClient');
    const assignSelectedClientName = document.getElementById('assignSelectedClientName');
    const assignSelectedClientMeta = document.getElementById('assignSelectedClientMeta');
    const assignChangeClientBtn = document.getElementById('assignChangeClientBtn');
    const assignEmailUrl = outlookContainer ? outlookContainer.getAttribute('data-assign-email-url') : '';
    const unlinkEmailUrl = outlookContainer ? outlookContainer.getAttribute('data-unlink-email-url') : '';
    const syncInboxUrl = outlookContainer ? outlookContainer.getAttribute('data-sync-inbox-url') : '';
    const syncStatusUrlBase = outlookContainer ? outlookContainer.getAttribute('data-sync-status-url') : '';
    const unassignedCountUrl = outlookContainer ? (outlookContainer.getAttribute('data-unassigned-count-url') || '') : '';
    const canSyncInbox = !!(outlookContainer && outlookContainer.getAttribute('data-can-sync-inbox') === '1');
    const canUnlinkSyncedEmail = !!(outlookContainer
        && outlookContainer.getAttribute('data-can-unlink-synced-email') === '1');
    const canViewSyncedInbox = !!(outlookContainer && outlookContainer.getAttribute('data-can-view-synced-inbox') === '1');
    const canDeleteEmail = !!(outlookContainer && outlookContainer.dataset.canDeleteEmail === '1');
    const canSelectSyncMailbox = !!(outlookContainer && outlookContainer.getAttribute('data-can-select-sync-mailbox') === '1');
    const mattersUrl = outlookContainer ? outlookContainer.getAttribute('data-matters-url') : '';
    const btnGmailBack = document.getElementById('btnGmailBack');
    const gmailReadingToolbar = document.getElementById('gmailReadingToolbar');
    const gmailReadingFooter = document.getElementById('gmailReadingFooter');
    const gmailFolderChip = document.getElementById('gmailFolderChip');
    const gmailReadPosition = document.getElementById('gmailReadPosition');
    const gmailReadPrev = document.getElementById('gmailReadPrev');
    const gmailReadNext = document.getElementById('gmailReadNext');
    const gmailReadMoreMenu = document.getElementById('gmailReadMoreMenu');
    const gmailIconDelete = document.getElementById('gmailIconDelete');
    const gmailIconAssign = document.getElementById('gmailIconAssign');
    const gmailIconMore = document.getElementById('gmailIconMore');
    const gmailMenuResend = document.getElementById('gmailMenuResend');
    const GMAIL_FOLDER_LABELS = {
        inbox: 'Inbox',
        sent: 'Sent',
        outbox: 'Outbox',
        unassigned: 'Unassigned',
        assigned: 'Assigned',
        review: 'Needs Review'
    };
    let listTotal = 0;
    let listFrom = 0;
    let listLastPage = 1;
    let emailListLoading = false;
    let emailListLoadingMore = false;
    const emailInfiniteLoader = document.getElementById('emailInfiniteLoader');
    const emailUiModeSwitch = document.getElementById('emailUiModeSwitch');
    const outlookListPane = document.querySelector('.outlook-list-pane');
    const EMAIL_UI_MODE_STORAGE_KEY = 'crm_email_ui_mode';
    let emailUiMode = 'outlook';
    const syncedDateSummaryEl = document.getElementById('syncedDateSummary');
    let syncedDateSummary = null;
    let selectedEmail = null;
    let assignmentModalMode = 'assign';
    let unlinkDestinationMode = 'unassigned';
    let isAssignSubmitting = false;

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
    if (unassignedOnly && sortOrder && defaultFolder === 'review') {
        sortOrder.value = 'review';
    }
    updateUnassignedFolderChrome();
    setEmailUiMode('outlook', false);
    loadEmails();
    updateOutboxFiltersVisibility();

    /*
    if (emailUiModeSwitch) {
        emailUiModeSwitch.addEventListener('click', function (event) {
            const button = event.target.closest('.email-ui-mode-btn');
            if (!button) {
                return;
            }

            const nextMode = button.getAttribute('data-ui-mode');
            if (!nextMode || nextMode === emailUiMode) {
                return;
            }

            resetReadingPane();
            document.querySelectorAll('.email-item').forEach(function (item) {
                item.classList.remove('active');
            });
            setEmailUiMode(nextMode, true);
        });
    }
    */

    if (btnGmailBack) {
        btnGmailBack.addEventListener('click', function () {
            resetReadingPane();
            document.querySelectorAll('.email-item').forEach(function (item) {
                item.classList.remove('active');
            });
        });
    }

    if (gmailReadPrev) {
        gmailReadPrev.addEventListener('click', function () {
            navigateGmailEmail('prev');
        });
    }

    if (gmailReadNext) {
        gmailReadNext.addEventListener('click', function () {
            navigateGmailEmail('next');
        });
    }

    function initListFiltersToggle(buttonEl, drawerEl, focusInput) {
        if (!buttonEl || !drawerEl) {
            return;
        }
        buttonEl.addEventListener('click', function () {
            const isOpen = drawerEl.classList.toggle('is-open');
            buttonEl.classList.toggle('is-open', isOpen);
            buttonEl.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            buttonEl.title = isOpen ? 'Hide search and filters' : 'Show search and filters';
            if (isOpen && focusInput) {
                window.setTimeout(function () {
                    focusInput.focus();
                }, 220);
            }
        });
    }

    function clientFiltersAreActive() {
        const hasSearch = !!(searchInput && String(searchInput.value || '').trim());
        const hasLabel = !!(labelFilter && String(labelFilter.value || '').trim());
        const hasSender = !!(senderFilter && String(senderFilter.value || '').trim() && !senderFilter.hidden);
        const hasSort = !!(sortOrder && sortOrder.value === 'asc');
        const hasSendStatus = !!(sendStatusFilter && String(sendStatusFilter.value || '').trim() && !sendStatusFilter.hidden);
        const hasDateFrom = !!(dateFromFilter && String(dateFromFilter.value || '').trim() && !dateFromFilter.hidden);
        const hasDateTo = !!(dateToFilter && String(dateToFilter.value || '').trim() && !dateToFilter.hidden);
        return hasSearch || hasLabel || hasSender || hasSort || hasSendStatus || hasDateFrom || hasDateTo;
    }

    function updateClientFilterToggleState() {
        const btnToggleClientListFilters = document.getElementById('btnToggleClientListFilters');
        if (!btnToggleClientListFilters) {
            return;
        }
        btnToggleClientListFilters.classList.toggle('has-active-filters', clientFiltersAreActive());
    }

    const btnToggleListFilters = document.getElementById('btnToggleListFilters');
    const unassignedListFilters = document.getElementById('unassignedListFilters');
    initListFiltersToggle(btnToggleListFilters, unassignedListFilters, searchInput);

    const btnToggleClientListFilters = document.getElementById('btnToggleClientListFilters');
    const clientListFilters = document.getElementById('clientListFilters');
    initListFiltersToggle(btnToggleClientListFilters, clientListFilters, searchInput);
    updateClientFilterToggleState();

    if (gmailIconDelete) {
        gmailIconDelete.addEventListener('click', function () {
            proxyClickButton(document.getElementById('btnDeleteEmail'));
        });
    }

    if (gmailIconAssign && btnAssignToClient) {
        gmailIconAssign.addEventListener('click', function () {
            proxyClickButton(btnAssignToClient);
        });
    }

    if (gmailIconMore) {
        gmailIconMore.addEventListener('click', function (event) {
            event.stopPropagation();
            toggleGmailReadMoreMenu();
        });
    }

    document.addEventListener('click', function (event) {
        if (!gmailReadMoreMenu || gmailReadMoreMenu.hidden) {
            return;
        }
        if (event.target.closest('.gmail-read-more-wrap')) {
            return;
        }
        closeGmailReadMoreMenu();
    });

    window.addEventListener('resize', function () {
        const iframe = document.getElementById('readBody');
        if (iframe) {
            resetReadBodyIframeSizing(iframe);
        }
    });

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
        updateClientFilterToggleState();
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
        
        if (mergedCount > 0) {
            // Calendar event icon
            return '<span class="email-list-calendar email-list-calendar--event" title="' + escapeHtml(mergedCount + ' calendar event(s) added') + '" aria-label="Calendar event">'
                + '<i class="fa-solid fa-calendar-check" aria-hidden="true"></i>'
                + (totalCount > 1 ? '<span class="email-calendar-count">' + totalCount + '</span>' : '')
                + '</span>';
        }

        // Calendar schedule icon
        const scheduleTitle = (pendingCount > 0)
            ? 'Calendar schedule detected'
            : 'Calendar invite (.ics)';
        return '<span class="email-list-calendar email-list-calendar--schedule" title="' + escapeHtml(scheduleTitle) + '" aria-label="Calendar schedule">'
            + '<i class="fa-solid fa-calendar-days" aria-hidden="true"></i>'
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
            bannerEl.classList.remove('email-calendar-banner--expanded', 'email-calendar-banner--compact');
            bannerEl.innerHTML = '';
            return;
        }

        const events = Array.isArray(calendar.events) ? calendar.events : [];
        const mergedCount = Number(calendar.merged_count || 0);
        const pendingCount = Number(calendar.pending_count || 0);
        const totalCount = Number(calendar.count || events.length || 0) || events.length;

        const summaryBits = [];
        if (mergedCount > 0) {
            summaryBits.push(mergedCount + ' added to calendar');
        }
        if (pendingCount > 0) {
            summaryBits.push(pendingCount + ' awaiting assignment');
        }
        if (summaryBits.length === 0 && totalCount > 0) {
            summaryBits.push(totalCount + ' event' + (totalCount === 1 ? '' : 's'));
        }
        if (summaryBits.length === 0) {
            summaryBits.push('Calendar invite detected');
        }

        // Compact summary keeps the reading pane focused on the email body.
        // Event details are available on demand so long lists cannot fill the preview.
        let html = ''
            + '<div class="email-calendar-banner__summary">'
            + '  <div class="email-calendar-banner__summary-main">'
            + '    <span class="email-calendar-banner__icon" aria-hidden="true"><i class="fa-solid fa-calendar-check"></i></span>'
            + '    <div class="email-calendar-banner__summary-text">'
            + '      <strong>Calendar</strong>'
            + '      <span class="email-calendar-banner__summary-meta">' + escapeHtml(summaryBits.join(' · ')) + '</span>'
            + '    </div>'
            + '  </div>';

        if (events.length > 0) {
            html += ''
                + '  <button type="button" class="email-calendar-banner__toggle" aria-expanded="false" aria-controls="readCalendarBannerDetails">'
                + '    <span class="email-calendar-banner__toggle-label">Show ' + events.length + ' event' + (events.length === 1 ? '' : 's') + '</span>'
                + '    <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>'
                + '  </button>';
        }

        html += '</div>';

        if (events.length > 0) {
            html += '<div class="email-calendar-banner__details" id="readCalendarBannerDetails" hidden>';
            html += '<ul class="email-calendar-banner__list">';
            events.forEach(function (ev) {
                const title = (ev.event_title || ev.title || formatCalendarEventType(ev.event_type) || 'Event').trim();
                const when = String(ev.starts_at || '').trim();
                const where = String(ev.location || '').trim();
                const status = String(ev.status || '').toLowerCase();
                const statusLabel = status === 'merged'
                    ? 'Added'
                    : (status === 'pending' ? 'Pending' : (status || ''));
                const statusClass = status === 'merged'
                    ? ' email-calendar-banner__status--merged'
                    : (status === 'pending' ? ' email-calendar-banner__status--pending' : '');

                html += '<li class="email-calendar-banner__item">';
                html += '  <div class="email-calendar-banner__item-main">';
                html += '    <div class="email-calendar-banner__title">' + escapeHtml(title) + '</div>';
                if (when) {
                    html += '    <div class="email-calendar-banner__when"><i class="fa-solid fa-clock" aria-hidden="true"></i> ' + escapeHtml(when) + '</div>';
                }
                if (where) {
                    html += '    <div class="email-calendar-banner__where"><i class="fa-solid fa-location-dot" aria-hidden="true"></i> ' + escapeHtml(where) + '</div>';
                }
                html += '  </div>';
                if (statusLabel) {
                    html += '  <span class="email-calendar-banner__status' + statusClass + '">' + escapeHtml(statusLabel) + '</span>';
                }
                html += '</li>';
            });
            html += '</ul></div>';
        }

        bannerEl.hidden = false;
        bannerEl.classList.add('email-calendar-banner--compact');
        bannerEl.classList.remove('email-calendar-banner--expanded');
        bannerEl.innerHTML = html;

        const toggle = bannerEl.querySelector('.email-calendar-banner__toggle');
        const details = bannerEl.querySelector('.email-calendar-banner__details');
        if (toggle && details) {
            toggle.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                const opening = details.hidden;
                details.hidden = !opening;
                toggle.setAttribute('aria-expanded', opening ? 'true' : 'false');
                const label = toggle.querySelector('.email-calendar-banner__toggle-label');
                if (label) {
                    label.textContent = opening
                        ? 'Hide events'
                        : ('Show ' + events.length + ' event' + (events.length === 1 ? '' : 's'));
                }
                const icon = toggle.querySelector('i');
                if (icon) {
                    icon.classList.toggle('fa-chevron-down', !opening);
                    icon.classList.toggle('fa-chevron-up', opening);
                }
                bannerEl.classList.toggle('email-calendar-banner--expanded', opening);
            });
        }
    }

    function renderSyncSourceBadge(email) {
        // Sync source badge (Manual sync / Cron / Compose) hidden from mail UI.
        return '';

        /*
        const source = (email.sync_source || '').trim();
        const label = (email.sync_source_label || '').trim();
        if (! source || ! label) {
            return '';
        }

        const icon = source === 'manual'
            ? 'fa-hand-pointer'
            : (source === 'cron' ? 'fa-clock-rotate-left' : 'fa-paper-plane');
        const modifier = source === 'manual'
            ? 'manual'
            : (source === 'cron' ? 'cron' : 'compose');

        return '<span class="email-sync-source-badge email-sync-source-badge--' + modifier + '" title="' + escapeHtml(label) + '">'
            + '<i class="fa-solid ' + icon + '" aria-hidden="true"></i> '
            + escapeHtml(label)
            + '</span>';
        */
    }

    function resolveEmailOrigin(email) {
        if (!email) {
            return null;
        }

        const status = String(email.sync_assignment_status || '').trim();
        if (status === 'auto_assigned') {
            return 'auto_assigned';
        }
        if (status === 'manual_assigned') {
            return 'manual_assigned';
        }

        // Uploaded .msg/.eml filed onto a client (not Zoho-synced).
        // Do not treat empty assignment status as sync-origin for pure uploads.
        if (isManualFileUploadEmail(email)) {
            return 'manual_upload';
        }

        return null;
    }

    /**
     * True when the email was dragged/uploaded as .msg/.eml on a client matter,
     * not imported via IMAP inbox sync.
     */
    function isManualFileUploadEmail(email) {
        if (!email) {
            return false;
        }

        const mailType = parseInt(email.mail_type, 10);
        if (mailType !== 1) {
            return false;
        }

        if (!email.client_id) {
            return false;
        }

        // Explicit source set on file upload path (new records).
        if (String(email.sync_source || '').trim() === 'upload') {
            return true;
        }

        // Any positive synced-mailbox id means inbox sync origin.
        if (hasSyncedMailboxOrigin(email)) {
            return false;
        }

        const status = String(email.sync_assignment_status || '').trim();
        if (status === 'auto_assigned' || status === 'manual_assigned' || status === 'unassigned') {
            return false;
        }

        // IMAP-synced rows usually also set mailbox_email; pure uploads leave it empty.
        if (String(email.mailbox_email || '').trim()) {
            return false;
        }

        return true;
    }

    function hasSyncedMailboxOrigin(email) {
        if (!email) {
            return false;
        }
        const syncedId = Number(email.synced_email_id);
        return Number.isFinite(syncedId) && syncedId > 0;
    }

    /**
     * Reassign Client is available for any email already linked to a client
     * (synced or file upload). Pure CRM compose (mail_type 2) is left alone.
     * Move-to-Unassigned is gated separately via selectedEmailCanReturnToUnassigned().
     */
    function canShowReassignClient(email) {
        if (!email || !email.client_id) {
            return false;
        }
        if (email.can_unlink_synced_email === false) {
            return false;
        }
        // CRM-sent compose rows are not reassigned via the synced-mail flow.
        if (parseInt(email.mail_type, 10) === 2) {
            return false;
        }
        const status = String(email.sync_assignment_status || '').trim();
        // Allow auto/manual assigned, pure uploads (empty status), and legacy rows.
        return status === 'auto_assigned'
            || status === 'manual_assigned'
            || status === ''
            || status === 'unassigned'
            || isManualFileUploadEmail(email)
            || hasSyncedMailboxOrigin(email);
    }

    function renderSyncedClientBadge(email) {
        if (!email) {
            return '';
        }

        const clientLabel = (email.client_name || '').trim()
            || (email.client_ref || '').trim()
            || '';
        const origin = resolveEmailOrigin(email);
        const clientUrl = email.client_url || '';

        if (!origin && !clientLabel && !email.client_id) {
            if (unassignedOnly) {
                return '<span class="email-unassigned-badge" title="Unassigned mail">'
                    + '<i class="fa-solid fa-user-clock" aria-hidden="true"></i> Unassigned'
                    + '</span>';
            }
            return '';
        }

        let labelStr = clientLabel;
        let iconClass = 'fa-user-check';
        let title = clientLabel ? ('Open client: ' + clientLabel) : 'Open client detail';
        let modifier = '';

        if (origin === 'auto_assigned') {
            iconClass = 'fa-wand-magic-sparkles';
            modifier = ' email-client-badge--auto';
            title = 'Auto assigned from synced inbox'
                + (clientLabel ? (' · ' + clientLabel) : '')
                + (clientUrl ? ' — click to open client detail' : '');
            labelStr = unassignedOnly && clientLabel ? clientLabel : 'Auto assigned';
        } else if (origin === 'manual_assigned') {
            iconClass = 'fa-user-check';
            modifier = ' email-client-badge--manual-assigned';
            title = 'Manually assigned to client'
                + (clientLabel ? (' · ' + clientLabel) : '')
                + (clientUrl ? ' — click to open client detail' : '');
            labelStr = unassignedOnly && clientLabel ? clientLabel : 'Manually assigned';
        } else if (origin === 'manual_upload') {
            iconClass = 'fa-cloud-arrow-up';
            modifier = ' email-client-badge--manual-upload';
            title = 'Uploaded manually (.msg / .eml)'
                + (clientLabel ? (' · ' + clientLabel) : '')
                + (clientUrl ? ' — click to open client detail' : '');
            labelStr = unassignedOnly && clientLabel ? clientLabel : 'Manual upload';
        } else if (!labelStr && email.client_id) {
            labelStr = 'Client #' + email.client_id;
        }

        const badgeClass = (unassignedOnly
            ? 'email-client-badge email-client-badge--list email-client-badge--clickable'
            : 'email-client-badge email-client-badge--clickable')
            + modifier;

        if (clientUrl) {
            return '<a href="' + escapeHtml(clientUrl) + '" class="' + badgeClass + '" title="'
                + escapeHtml(title) + '" onclick="event.stopPropagation();">'
                + '<i class="fa-solid ' + iconClass + '" aria-hidden="true"></i> '
                + escapeHtml(labelStr)
                + '</a>';
        }

        return '<span class="' + badgeClass.replace(' email-client-badge--clickable', '') + '" title="'
            + escapeHtml(title) + '">'
            + '<i class="fa-solid ' + iconClass + '" aria-hidden="true"></i> '
            + escapeHtml(labelStr)
            + '</span>';
    }

    function renderAssignmentReviewBadge(email) {
        const review = email && email.assignment_review;
        if (!review || !review.reason) {
            return '';
        }

        return '<span class="email-assignment-review-badge" title="' + escapeHtml(review.reason) + '"'
            + ' role="img" aria-label="Needs review: ' + escapeHtml(review.reason) + '">'
            + '<i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>'
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
        selectedEmail = null;
        if (readingPane) {
            readingPane.classList.remove('is-visible');
            readingPane.hidden = false;
        }
        if (emptyState) {
            emptyState.style.display = 'flex';
            emptyState.hidden = false;
        }
        closeGmailReadingView();
        const calendarBanner = document.getElementById('readCalendarBanner');
        if (calendarBanner) {
            calendarBanner.hidden = true;
            calendarBanner.innerHTML = '';
        }
    }

    function isGmailUiMode() {
        return emailUiMode === 'gmail';
    }

    function getStoredEmailUiMode() {
        try {
            const stored = localStorage.getItem(EMAIL_UI_MODE_STORAGE_KEY);
            return stored === 'gmail' ? 'gmail' : 'outlook';
        } catch (error) {
            return 'outlook';
        }
    }

    function updateEmailUiModeButtons() {
        if (!emailUiModeSwitch) {
            return;
        }

        emailUiModeSwitch.querySelectorAll('.email-ui-mode-btn').forEach(function (button) {
            const active = button.getAttribute('data-ui-mode') === emailUiMode;
            button.classList.toggle('active', active);
            button.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
    }

    function closeGmailReadMoreMenu() {
        if (!gmailReadMoreMenu) {
            return;
        }
        gmailReadMoreMenu.hidden = true;
        if (gmailIconMore) {
            gmailIconMore.setAttribute('aria-expanded', 'false');
        }
    }

    function toggleGmailReadMoreMenu() {
        if (!gmailReadMoreMenu || !gmailIconMore) {
            return;
        }
        const open = gmailReadMoreMenu.hidden;
        gmailReadMoreMenu.hidden = !open;
        gmailIconMore.setAttribute('aria-expanded', open ? 'true' : 'false');
    }

    function proxyClickButton(button) {
        if (button && !button.hidden && !button.disabled) {
            button.click();
        }
    }

    function getCurrentFolderLabel() {
        return GMAIL_FOLDER_LABELS[currentFolder] || 'Inbox';
    }

    function updateGmailReadNav() {
        if (!isGmailUiMode() || !gmailReadPosition) {
            return;
        }

        const idx = emails.findIndex(function (item) {
            return item.id === selectedEmailId;
        });

        if (idx === -1 || listTotal <= 0) {
            gmailReadPosition.textContent = '';
            if (gmailReadPrev) {
                gmailReadPrev.disabled = true;
            }
            if (gmailReadNext) {
                gmailReadNext.disabled = true;
            }
            return;
        }

        const position = Math.max(1, listFrom + idx);
        gmailReadPosition.textContent = position + ' of ' + listTotal;

        if (gmailReadPrev) {
            gmailReadPrev.disabled = idx <= 0 && currentPage <= 1;
        }
        if (gmailReadNext) {
            gmailReadNext.disabled = idx >= emails.length - 1 && currentPage >= listLastPage;
        }
    }

    function updateGmailReadingChrome(email) {
        if (!isGmailUiMode()) {
            return;
        }

        if (gmailFolderChip) {
            gmailFolderChip.textContent = getCurrentFolderLabel();
            gmailFolderChip.hidden = false;
        }

        if (gmailIconDelete) {
            gmailIconDelete.hidden = !canDeleteEmail;
        }
        if (gmailIconAssign && btnAssignToClient) {
            gmailIconAssign.hidden = btnAssignToClient.hidden;
        }
        if (gmailMenuResend && btnResend) {
            gmailMenuResend.hidden = btnResend.hidden;
        }

        updateGmailReadNav();
        closeGmailReadMoreMenu();
    }

    function navigateGmailEmail(direction) {
        if (!selectedEmailId || !emails.length) {
            return;
        }

        const idx = emails.findIndex(function (item) {
            return item.id === selectedEmailId;
        });
        if (idx === -1) {
            return;
        }

        if (direction === 'prev') {
            if (idx > 0) {
                const prevEmail = emails[idx - 1];
                const el = emailListContainer.querySelector('[data-email-id="' + prevEmail.id + '"]');
                showEmail(prevEmail, el);
                return;
            }
            if (currentPage > 1) {
                currentPage -= 1;
                loadEmails().then(function () {
                    if (emails.length) {
                        const lastEmail = emails[emails.length - 1];
                        const el = emailListContainer.querySelector('[data-email-id="' + lastEmail.id + '"]');
                        showEmail(lastEmail, el);
                    }
                });
            }
            return;
        }

        if (idx < emails.length - 1) {
            const nextEmail = emails[idx + 1];
            const el = emailListContainer.querySelector('[data-email-id="' + nextEmail.id + '"]');
            showEmail(nextEmail, el);
            return;
        }

        if (currentPage < listLastPage) {
            const previousCount = emails.length;
            currentPage += 1;
            loadEmails(useEmailInfiniteScroll ? { append: true } : undefined).then(function () {
                if (!emails.length) {
                    return;
                }
                const nextEmail = useEmailInfiniteScroll && emails.length > previousCount
                    ? emails[previousCount]
                    : emails[0];
                if (!nextEmail) {
                    return;
                }
                const el = emailListContainer.querySelector('[data-email-id="' + nextEmail.id + '"]');
                showEmail(nextEmail, el);
            });
        }
    }

    function formatGmailReadDate(email) {
        const dateValue = getEmailDate(email);
        const formatted = formatEmailDate(dateValue);
        if (!formatted) {
            return '';
        }

        try {
            const date = new Date(dateValue);
            if (isNaN(date.getTime())) {
                return formatted;
            }

            const now = new Date();
            const diffMs = now.getTime() - date.getTime();
            const diffMins = Math.floor(diffMs / 60000);
            if (diffMins >= 0 && diffMins < 60 * 24) {
                if (diffMins < 1) {
                    return formatted + ' (just now)';
                }
                if (diffMins < 60) {
                    return formatted + ' (' + diffMins + ' min ago)';
                }
                const diffHours = Math.floor(diffMins / 60);
                return formatted + ' (' + diffHours + ' hr ago)';
            }
        } catch (error) {
            // Fall back to plain formatted date.
        }

        return formatted;
    }

    function closeGmailReadingView() {
        if (!outlookContainer) {
            return;
        }

        outlookContainer.classList.remove('gmail-reading-open');
        document.body.classList.remove('gmail-email-reading-open');
        if (gmailReadingToolbar) {
            gmailReadingToolbar.hidden = true;
        }
        if (gmailReadingFooter) {
            gmailReadingFooter.hidden = true;
        }
        closeGmailReadMoreMenu();
        clearGmailReadingBodySizing();
    }

    function openGmailReadingView() {
        if (!isGmailUiMode() || !outlookContainer) {
            return;
        }

        outlookContainer.classList.add('gmail-reading-open');
        document.body.classList.add('gmail-email-reading-open');
        if (gmailReadingToolbar) {
            gmailReadingToolbar.hidden = false;
        }
        if (gmailReadingFooter) {
            gmailReadingFooter.hidden = false;
        }
    }

    function setEmailUiMode(mode, persist) {
        emailUiMode = mode === 'gmail' ? 'gmail' : 'outlook';

        if (outlookContainer) {
            outlookContainer.classList.toggle('ui-mode-gmail', emailUiMode === 'gmail');
            outlookContainer.classList.toggle('ui-mode-outlook', emailUiMode === 'outlook');
        }

        if (persist !== false) {
            try {
                localStorage.setItem(EMAIL_UI_MODE_STORAGE_KEY, emailUiMode);
            } catch (error) {
                // Ignore storage failures.
            }
        }

        updateEmailUiModeButtons();

        if (emailUiMode === 'outlook') {
            closeGmailReadingView();
        } else if (!selectedEmailId) {
            closeGmailReadingView();
            readingPane.classList.remove('is-visible');
            emptyState.style.display = 'none';
        }
    }

    function formatMailTotalLabel(total) {
        const safeTotal = Math.max(0, Number(total) || 0);
        return safeTotal === 1 ? 'Total: 1 email' : 'Total: ' + safeTotal + ' emails';
    }

    function updatePaginationDisplay(total, lastPage, from, to) {
        const safeLastPage = Math.max(1, Number(lastPage) || 1);
        const safeTotal = Math.max(0, Number(total) || 0);
        listTotal = safeTotal;
        listFrom = Math.max(0, Number(from) || 0);
        listLastPage = safeLastPage;

        if (compactPagination) {
            if (listTotalCount) {
                listTotalCount.textContent = formatMailTotalLabel(safeTotal);
            }

            if (pageSummary) {
                pageSummary.textContent = 'Page ' + currentPage + ' of ' + safeLastPage;
            }
        } else {
            if (listTotalCount) {
                listTotalCount.textContent = formatMailTotalLabel(safeTotal);
            }

            if (pageSummary) {
                pageSummary.textContent = 'Page ' + currentPage + ' of ' + safeLastPage;
            }

            if (pageInfo) {
                if (safeTotal > 0) {
                    pageInfo.textContent = 'Showing ' + from + '-' + to + ' of ' + safeTotal;
                } else {
                    pageInfo.textContent = 'No emails found';
                }
            }
        }

        if (prevBtn) {
            prevBtn.disabled = currentPage <= 1;
        }
        if (nextBtn) {
            nextBtn.disabled = currentPage >= safeLastPage || safeTotal === 0;
        }

        updateGmailReadNav();
    }

    function clearGmailReadingBodySizing() {
        const readingBody = document.querySelector('#readingPane .reading-body');
        const iframe = document.getElementById('readBody');

        if (readingBody) {
            readingBody.style.minHeight = '';
            readingBody.style.maxHeight = '';
            readingBody.style.height = '';
        }
        if (iframe) {
            iframe.style.height = '';
            iframe.style.minHeight = '';
            setReadingBodyMode(iframe, '');
        }
    }

    function setReadingBodyMode(iframe, mode) {
        const readingBody = iframe && iframe.closest ? iframe.closest('.reading-body') : null;
        const isPdf = mode === 'pdf';
        const isPhoto = mode === 'photo';
        if (readingBody) {
            readingBody.classList.toggle('reading-body--pdf', isPdf);
            readingBody.classList.toggle('reading-body--photo', isPhoto);
        }
        if (iframe) {
            iframe.classList.toggle('read-body--pdf', isPdf);
            iframe.classList.toggle('read-body--photo', isPhoto);
        }
    }

    function getReadingScrollFillHeight(iframe, contentHeight) {
        const readingScroll = iframe && iframe.closest ? iframe.closest('.reading-scroll') : document.querySelector('#readingPane .reading-scroll');
        const minContent = Math.max(Number(contentHeight) || 0, 180);
        if (!readingScroll) {
            return minContent;
        }

        const attachments = readingScroll.querySelector('#attachmentsContainer');
        const footer = readingScroll.querySelector('#gmailReadingFooter, .gmail-read-footer');
        const readingBody = iframe && iframe.closest ? iframe.closest('.reading-body') : readingScroll.querySelector('.reading-body');
        let reserved = 0;
        if (attachments && !attachments.hidden) {
            reserved += attachments.offsetHeight;
        }
        if (footer && !footer.hidden) {
            reserved += footer.offsetHeight;
        }

        const scrollStyle = window.getComputedStyle(readingScroll);
        reserved += (parseFloat(scrollStyle.paddingTop) || 0) + (parseFloat(scrollStyle.paddingBottom) || 0);
        if (readingBody) {
            const bodyStyle = window.getComputedStyle(readingBody);
            reserved += (parseFloat(bodyStyle.paddingTop) || 0) + (parseFloat(bodyStyle.paddingBottom) || 0);
        }

        const available = readingScroll.clientHeight - reserved;
        return Math.max(minContent, available);
    }

    function resetReadBodyIframeSizing(iframe) {
        if (!iframe) {
            return;
        }

        try {
            if (iframe.classList.contains('read-body--pdf') && iframe.src && !iframe.src.startsWith('about:blank')) {
                const fillH = getReadingScrollFillHeight(iframe, 720);
                iframe.style.height = Math.max(fillH, Math.round(window.innerHeight * 0.75)) + 'px';
                iframe.style.minHeight = '720px';
                iframe.style.maxHeight = 'none';
                iframe.style.overflow = 'auto';
                return;
            }
            const doc = iframe.contentDocument || iframe.contentWindow.document;
            if (doc && doc.body) {
                const scrollH = Math.max(doc.body.scrollHeight, doc.documentElement.scrollHeight, 180);
                if (iframe.classList.contains('read-body--photo')) {
                    const cap = 560;
                    iframe.style.height = Math.min(scrollH + 16, cap) + 'px';
                    iframe.style.minHeight = '240px';
                    iframe.style.maxHeight = cap + 'px';
                    iframe.style.overflow = 'auto';
                    return;
                }
                const contentH = scrollH + 24;
                const fillH = getReadingScrollFillHeight(iframe, contentH);
                iframe.style.height = fillH + 'px';
                iframe.style.minHeight = contentH + 'px';
                iframe.style.maxHeight = 'none';
                iframe.style.overflow = 'hidden';
            }
        } catch(e) {
            iframe.style.height = getReadingScrollFillHeight(iframe, 280) + 'px';
            iframe.style.minHeight = '180px';
        }
    }

    function isSyncedInboxFolder(folder) {
        if (unassignedOnly) {
            return folder === 'inbox' || folder === 'unassigned' || folder === 'assigned' || folder === 'review';
        }

        return folder === 'unassigned' || folder === 'assigned' || folder === 'review';
    }

    function renderSyncedDateSummaryBar(summary) {
        syncedDateSummary = summary || null;
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

    // Event Listeners
    folderItems.forEach(item => {
        item.addEventListener('click', (e) => {
            const target = e.currentTarget;
            const folder = target.dataset.folder || 'inbox';
            // Permission gate applies only on the global synced-mail page, not client Inbox/Sent.
            if (unassignedOnly && (folder === 'inbox' || folder === 'unassigned' || folder === 'assigned') && !canViewSyncedInbox) {
                return;
            }
            switchToFolder(folder);
            loadEmails();
        });
    });

    const folderSelect = document.getElementById('folderSelect');
    if (folderSelect) {
        folderSelect.addEventListener('change', (e) => {
            const folder = e.target.value || 'inbox';
            if (unassignedOnly && (folder === 'inbox' || folder === 'unassigned' || folder === 'assigned') && !canViewSyncedInbox) {
                return;
            }
            switchToFolder(folder);
            loadEmails();
        });
    }

    if (searchInput) {
        searchInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                currentPage = 1;
                loadEmails();
            }
        });
        searchInput.addEventListener('input', updateClientFilterToggleState);
    }

    if (labelFilter) {
        labelFilter.addEventListener('change', () => {
            currentPage = 1;
            loadEmails();
            updateClientFilterToggleState();
        });
    }

    if (senderFilter) {
        senderFilter.addEventListener('change', () => {
            currentPage = 1;
            loadEmails();
            updateClientFilterToggleState();
        });
    }

    if (listMailboxFilter) {
        listMailboxFilter.addEventListener('change', () => {
            currentPage = 1;
            loadEmails();
        });
    }

    function updateUnassignedFolderChrome() {
        if (!unassignedOnly) {
            return;
        }

        const isReview = currentFolder === 'review';
        const titleEl = document.getElementById('unassignedFolderTitle');
        if (titleEl) {
            const icon = titleEl.querySelector('i');
            const label = titleEl.querySelector('span');
            if (icon) {
                icon.className = 'fa-solid ' + (isReview ? 'fa-triangle-exclamation' : 'fa-inbox');
            }
            if (label) {
                label.textContent = isReview ? 'Needs Review' : 'Inbox';
            }
        }

        const panelTitle = document.getElementById('unassignedPanelTitle');
        if (panelTitle) {
            panelTitle.textContent = isReview ? 'Review filters' : 'Refine list';
        }

        // Keep Unassigned / Assigned tab highlight in sync (review uses the sort filter).
        folderItems.forEach(function (tab) {
            const folder = tab.dataset.folder || '';
            if (folder !== 'unassigned' && folder !== 'assigned') {
                return;
            }
            const isActive = !isReview && folder === currentFolder;
            tab.classList.toggle('active', isActive);
            tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
        });

        if (btnAssignBySubject) {
            btnAssignBySubject.hidden = currentFolder !== 'unassigned';
        }
    }

    function applyUnassignedListModeFromSort() {
        if (!unassignedOnly || !sortOrder) {
            return;
        }

        const value = sortOrder.value;
        if (value === 'review') {
            currentFolder = 'review';
        } else if (defaultFolder === 'review') {
            // Dedicated review page: Newest/Oldest only change sort direction.
            currentFolder = 'review';
        } else if (currentFolder === 'review') {
            currentFolder = 'unassigned';
        }

        updateUnassignedFolderChrome();
    }

    if (sortOrder) {
        sortOrder.addEventListener('change', () => {
            applyUnassignedListModeFromSort();
            currentPage = 1;
            resetReadingPane();
            loadEmails();
            updateClientFilterToggleState();
        });
    }

    if (sendStatusFilter) {
        sendStatusFilter.addEventListener('change', () => {
            currentPage = 1;
            loadEmails();
            updateClientFilterToggleState();
        });
    }

    if (dateFromFilter) {
        dateFromFilter.addEventListener('change', () => {
            currentPage = 1;
            loadEmails();
            updateClientFilterToggleState();
        });
    }

    if (dateToFilter) {
        dateToFilter.addEventListener('change', () => {
            currentPage = 1;
            loadEmails();
            updateClientFilterToggleState();
        });
    }

    if (prevBtn) {
        prevBtn.addEventListener('click', () => {
            if (currentPage > 1) {
                currentPage--;
                loadEmails();
            }
        });
    }

    if (nextBtn) {
        nextBtn.addEventListener('click', () => {
            currentPage++;
            loadEmails();
        });
    }

    if (perPageSelect) {
        perPageSelect.addEventListener('change', function () {
            const nextPerPage = parseInt(perPageSelect.value, 10);
            perPage = PER_PAGE_OPTIONS.indexOf(nextPerPage) !== -1 ? nextPerPage : 20;
            perPageSelect.value = String(perPage);
            try {
                localStorage.setItem(PER_PAGE_STORAGE_KEY, String(perPage));
            } catch (error) {
                // Ignore storage failures.
            }
            currentPage = 1;
            loadEmails();
        });
    }

    if (composeFormatBar && composeReplyInput) {
        composeFormatBar.addEventListener('click', (event) => {
            const btn = event.target.closest('.compose-format-btn');
            if (!btn) return;
            event.preventDefault();
            composeReplyInput.focus();
            const cmd = btn.getAttribute('data-cmd');
            if (cmd === 'insertTable') {
                insertComposeTable();
                return;
            }
            if (cmd) {
                document.execCommand(cmd, false, null);
            }
        });
    }

    if (composeQuoteToggle && composeQuoteWrap) {
        composeQuoteToggle.addEventListener('click', () => {
            const collapsed = composeQuoteWrap.classList.toggle('is-collapsed');
            composeQuoteToggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
            if (!collapsed && composeQuoteFrame) {
                sizeComposeContentIframe(composeQuoteFrame);
            }
        });
    }

    if (composeShowCc) {
        composeShowCc.addEventListener('click', () => {
            showComposeOptionalField('cc');
            if (ccInput) {
                ccInput.focus();
            }
        });
    }

    if (composeShowBcc) {
        composeShowBcc.addEventListener('click', () => {
            showComposeOptionalField('bcc');
            if (bccInput) {
                bccInput.focus();
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
        if (folder === 'unread') {
            folder = 'inbox';
        }
        // Client Emails tab has no synced folders — coerce stray values back to inbox.
        if (!unassignedOnly && (folder === 'unassigned' || folder === 'assigned' || folder === 'review')) {
            folder = 'inbox';
        }
        // On the synced-mail page, clicking Unassigned/Assigned leaves Needs Review mode.
        if (unassignedOnly && sortOrder && (folder === 'unassigned' || folder === 'assigned')
            && sortOrder.value === 'review') {
            sortOrder.value = 'desc';
        }
        folderItems.forEach(function (f) {
            const isActive = f.dataset.folder === folder;
            f.classList.toggle('active', isActive);
            f.setAttribute('aria-selected', isActive ? 'true' : 'false');
        });
        const fSelect = document.getElementById('folderSelect');
        if (fSelect && fSelect.value !== folder) {
            fSelect.value = folder;
        }
        // Sender lists differ by folder; keep a Sent-only sender from emptying Inbox.
        if (senderFilter && currentFolder !== folder) {
            senderFilter.value = '';
        }
        currentFolder = folder;
        currentPage = 1;
        resetReadingPane();
        updateOutboxFiltersVisibility();
        updateUnassignedFolderChrome();
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

    const btnDeleteEmail = document.getElementById('btnDeleteEmail');
    if (btnDeleteEmail && canDeleteEmail) {
        btnDeleteEmail.addEventListener('click', handleDeleteEmail);
    }

    const gmailMenuReply = document.getElementById('gmailMenuReply');
    const gmailMenuReplyAll = document.getElementById('gmailMenuReplyAll');
    const gmailMenuForward = document.getElementById('gmailMenuForward');
    const gmailMetaReply = document.getElementById('gmailMetaReply');
    const gmailMetaMore = document.getElementById('gmailMetaMore');
    const gmailFooterReply = document.getElementById('gmailFooterReply');
    const gmailFooterForward = document.getElementById('gmailFooterForward');

    if (gmailMenuReply) {
        gmailMenuReply.addEventListener('click', function () {
            closeGmailReadMoreMenu();
            proxyClickButton(btnReply);
        });
    }
    if (gmailMenuReplyAll) {
        gmailMenuReplyAll.addEventListener('click', function () {
            closeGmailReadMoreMenu();
            proxyClickButton(btnReplyAll);
        });
    }
    if (gmailMenuForward) {
        gmailMenuForward.addEventListener('click', function () {
            closeGmailReadMoreMenu();
            proxyClickButton(btnForward);
        });
    }
    if (gmailMenuResend && btnResend) {
        gmailMenuResend.addEventListener('click', function () {
            closeGmailReadMoreMenu();
            proxyClickButton(btnResend);
        });
    }
    if (gmailMetaReply) {
        gmailMetaReply.addEventListener('click', function () {
            proxyClickButton(btnReply);
        });
    }
    if (gmailMetaMore) {
        gmailMetaMore.addEventListener('click', function (event) {
            event.stopPropagation();
            toggleGmailReadMoreMenu();
        });
    }
    if (gmailFooterReply) {
        gmailFooterReply.addEventListener('click', function () {
            proxyClickButton(btnReply);
        });
    }
    if (gmailFooterForward) {
        gmailFooterForward.addEventListener('click', function () {
            proxyClickButton(btnForward);
        });
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
                notices: result.notices || [],
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
            const allNotices = [];
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
                        if (fileResult.notices && fileResult.notices.length) {
                            allNotices.push.apply(allNotices, fileResult.notices);
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
                const noticesText = (typeof window.crmFormatEmailUploadInfoNotices === 'function')
                    ? window.crmFormatEmailUploadInfoNotices(allNotices, allWarnings)
                    : ((typeof window.crmFormatEmailUploadNotices === 'function')
                        ? window.crmFormatEmailUploadNotices(allNotices)
                        : '');
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
                        let warningMsg = 'Successfully uploaded ' + totalUploaded + ' email' + (totalUploaded > 1 ? 's' : '')
                            + ', but some items could not be fully saved:\n\n' + warningsText;
                        if (noticesText) {
                            warningMsg += '\n\nNotes:\n' + noticesText;
                        }
                        showUploadResultModal(
                            'warning',
                            warningMsg,
                            'Uploaded With Warnings'
                        );
                    } else {
                        let successMsg = 'Successfully uploaded ' + totalUploaded + ' email' + (totalUploaded > 1 ? 's' : '') + '.';
                        if (noticesText) {
                            successMsg += '\n\n' + noticesText;
                        }
                        showUploadSuccessToast(successMsg);
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
                        if (noticesText) {
                            errorMsg += '\n\nNotes:\n' + noticesText;
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
    function setEmailInfiniteLoader(visible) {
        if (!emailInfiniteLoader) {
            return;
        }
        emailInfiniteLoader.hidden = !visible;
    }

    function hasMoreInfiniteEmails() {
        return useEmailInfiniteScroll && listTotal > emails.length && currentPage < listLastPage;
    }

    async function loadMoreInfiniteEmails() {
        if (!useEmailInfiniteScroll || emailListLoading || emailListLoadingMore) {
            return;
        }
        if (currentPage >= listLastPage || emails.length >= listTotal) {
            return;
        }
        currentPage += 1;
        await loadEmails({ append: true });
    }

    function maybeLoadMoreInfiniteEmails() {
        if (!useEmailInfiniteScroll || !emailListContainer || emailListLoading || emailListLoadingMore) {
            return;
        }
        if (currentPage >= listLastPage || emails.length >= listTotal) {
            return;
        }
        const remaining = emailListContainer.scrollHeight - emailListContainer.scrollTop - emailListContainer.clientHeight;
        if (remaining <= 240) {
            loadMoreInfiniteEmails();
        }
    }

    if (useEmailInfiniteScroll && emailListContainer) {
        emailListContainer.addEventListener('scroll', function () {
            maybeLoadMoreInfiniteEmails();
        }, { passive: true });
    }

    async function loadEmails(options) {
        const append = !!(options && options.append && useEmailInfiniteScroll);

        if (unassignedOnly && isSyncedInboxFolder(currentFolder) && !canViewSyncedInbox) {
            currentFolder = 'unassigned';
            switchToFolder(currentFolder);
        }

        if (append) {
            if (emailListLoading || emailListLoadingMore) {
                return;
            }
            emailListLoadingMore = true;
            setEmailInfiniteLoader(true);
        } else {
            emailListLoading = true;
            emailListLoadingMore = false;
            setEmailInfiniteLoader(false);
            emailListContainer.innerHTML = '<div class="email-list-loading">Loading emails...</div>';
        }

        try {
            const query = searchInput.value;
            const label = labelFilter ? labelFilter.value : '';
            const sender = senderFilter ? senderFilter.value : '';
            const folderToFetch = currentFolder;
            const pageToFetch = Math.max(1, currentPage || 1);
            
            const url = new URL(`${baseUrl}/clients/outlook/fetch-all`);
            url.searchParams.append('folder', folderToFetch);
            url.searchParams.append('page', pageToFetch);
            url.searchParams.append('per_page', useEmailInfiniteScroll ? 20 : perPage);
            url.searchParams.append('search', query);
            url.searchParams.append('label_id', label);
            url.searchParams.append('sender_filter', sender);
            const sortValue = sortOrder && sortOrder.value === 'review'
                ? 'desc'
                : (sortOrder ? sortOrder.value : 'desc');
            url.searchParams.append('sort_order', sortValue);
            if (isSyncedInboxFolder(folderToFetch) && listMailboxFilter && listMailboxFilter.value) {
                url.searchParams.append('mailbox_filter', listMailboxFilter.value);
            }
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
            
            const fetchedEmails = Array.isArray(data.emails) ? data.emails : [];
            if (append) {
                const existingIds = {};
                emails.forEach(function (email) {
                    if (email && email.id != null) {
                        existingIds[String(email.id)] = true;
                    }
                });
                const uniqueEmails = fetchedEmails.filter(function (email) {
                    if (!email || email.id == null) {
                        return false;
                    }
                    const key = String(email.id);
                    if (existingIds[key]) {
                        return false;
                    }
                    existingIds[key] = true;
                    return true;
                });
                emails = emails.concat(uniqueEmails);
            } else {
                emails = fetchedEmails;
            }

            if (data.date_summary) {
                syncedDateSummary = data.date_summary;
                renderSyncedDateSummaryBar(syncedDateSummary);
            } else if (unassignedOnly) {
                syncedDateSummary = null;
                renderSyncedDateSummaryBar(null);
            }
            
            // Pagination
            const total = data.total || 0;
            const lastPage = data.last_page || 1;
            const from = append ? (listFrom || 1) : (data.from || 0);
            const to = append
                ? Math.min(total, emails.length)
                : (data.to || 0);
            if (total > 0) {
                updatePaginationDisplay(total, lastPage, from, to);
            } else {
                updatePaginationDisplay(0, 1, 0, 0);
            }

            // Update sender filter dropdown
            if (senderFilter && data.senders) {
                const currentSelection = senderFilter.value;
                let optionsHtml = '<option value="">All Senders</option>';
                data.senders.forEach(s => {
                    optionsHtml += `<option value="${s}" ${s === currentSelection ? 'selected' : ''}>${s}</option>`;
                });
                senderFilter.innerHTML = optionsHtml;
            }

            if (append) {
                const scrollTop = emailListContainer.scrollTop;
                renderEmailList();
                emailListContainer.scrollTop = scrollTop;
            } else {
                renderEmailList();
                refreshSelectedEmailAfterReload();
            }

            if (useEmailInfiniteScroll) {
                window.requestAnimationFrame(maybeLoadMoreInfiniteEmails);
            }
        } catch (error) {
            console.error('Failed to fetch emails', error);
            if (append) {
                currentPage = Math.max(1, currentPage - 1);
            } else {
                emails = [];
                emailListContainer.innerHTML = '<div style="padding:16px;text-align:center;color:red;">'
                    + escapeHtml(error.message || 'Error loading emails')
                    + '</div>';
                updatePaginationDisplay(0, 1, 0, 0);
            }
        } finally {
            emailListLoading = false;
            emailListLoadingMore = false;
            setEmailInfiniteLoader(false);
        }
    }

    function refreshSelectedEmailAfterReload() {
        if (!selectedEmailId) {
            return;
        }

        const refreshed = emails.find(function (email) {
            return email.id === selectedEmailId;
        });

        if (!refreshed) {
            resetReadingPane();
            return;
        }

        const activeItem = emailListContainer
            ? emailListContainer.querySelector('.email-item.active')
            : null;
        showEmail(refreshed, activeItem);
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
        // Prefer pre-formatted Melbourne display from API; then original mail time;
        // sent_at only for CRM-composed outbound (no fetch_mail_sent_time).
        return email.fetch_mail_sent_time_display
            || email.fetch_mail_sent_time
            || email.sent_at
            || email.failed_at
            || email.received_date
            || email.created_at
            || null;
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
                previewUrl: email.is_calendar_invite
                    ? null
                    : (email.pdf_preview_url || email.pdf_file_url),
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
                if (/undisclosed[- ]recipients/i.test(r)) return false;
                if (r.includes('<extract_msg.') || r.includes('object at 0x')) return false;
                if (r.includes('Recipient') && r.includes('0x')) return false;
                return true;
            })
            .map(function(r) {
                const angle = r.match(/<([^>]+)>/);
                if (angle && angle[1]) {
                    return angle[1].trim();
                }
                return r.replace(/^<|>$/g, '').trim();
            })
            .filter(function(r) {
                return r !== '';
            });

        return validRecipients.length > 0 ? validRecipients.join(', ') : '';
    }

    function resolveToDisplay(email) {
        const cleanedTo = cleanRecipients(email && email.to_mail);
        if (cleanedTo) {
            return cleanedTo;
        }
        // Zoho/IMAP BCC and some list mail omit To; show the receiving mailbox instead of Unknown.
        const mailbox = String((email && email.mailbox_email) || '').trim();
        return mailbox || 'Unknown';
    }

    const READ_BODY_SANDBOX = 'allow-same-origin allow-popups allow-popups-to-escape-sandbox';

    function isCalendarPayload(str) {
        if (!str) return false;
        const text = String(str).replace(/^\uFEFF/, '').trim();
        if (!text) return false;
        const upper = text.toUpperCase();
        if (upper.indexOf('BEGIN:VCALENDAR') === 0) {
            return true;
        }
        const head = upper.slice(0, 800);
        return head.indexOf('BEGIN:VCALENDAR') !== -1 && upper.indexOf('BEGIN:VEVENT') !== -1;
    }

    function icsPropertyValue(icsText, field) {
        if (!icsText || !field) return '';
        const unfolded = String(icsText).replace(/\r?\n[ \t]/g, '');
        const re = new RegExp('^' + field.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '(?:;[^:]*)?:(.+)$', 'im');
        const match = unfolded.match(re);
        if (!match) return '';
        return String(match[1] || '')
            .replace(/\\n/g, '\n')
            .replace(/\\,/g, ',')
            .replace(/\\;/g, ';')
            .replace(/\\\\/g, '\\')
            .trim();
    }

    function summarizeCalendarPayload(str) {
        if (!isCalendarPayload(str)) {
            return '';
        }
        const summary = icsPropertyValue(str, 'SUMMARY') || 'Calendar invitation';
        const location = icsPropertyValue(str, 'LOCATION');
        const description = icsPropertyValue(str, 'DESCRIPTION');
        const dtStart = icsPropertyValue(str, 'DTSTART');
        const dtEnd = icsPropertyValue(str, 'DTEND');
        const lines = [summary, '', 'This message is a calendar invitation.'];
        if (dtStart) {
            lines.push('When: ' + dtStart + (dtEnd ? ' – ' + dtEnd : ''));
        }
        if (location) {
            lines.push('Where: ' + location);
        }
        if (description) {
            let desc = description.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
            if (desc) {
                if (desc.length > 600) {
                    desc = desc.slice(0, 600).replace(/\s+\S*$/, '') + '…';
                }
                lines.push('', desc);
            }
        }
        return lines.join('\n').trim();
    }

    function buildCalendarInviteBodyHtml(rawCalendar, email) {
        const events = email && email.calendar && Array.isArray(email.calendar.events)
            ? email.calendar.events
            : [];
        const firstEvent = events.length ? events[0] : null;
        const source = isCalendarPayload(rawCalendar) ? rawCalendar : '';
        const summary = (firstEvent && firstEvent.title)
            || (source && icsPropertyValue(source, 'SUMMARY'))
            || (email && email.subject)
            || 'Calendar invitation';
        const location = (firstEvent && firstEvent.location)
            || (source ? icsPropertyValue(source, 'LOCATION') : '');
        const when = (firstEvent && (firstEvent.when || firstEvent.starts_at))
            || (source ? icsPropertyValue(source, 'DTSTART') : '');
        const dtEnd = source ? icsPropertyValue(source, 'DTEND') : '';
        const description = (firstEvent && firstEvent.description)
            || (source ? icsPropertyValue(source, 'DESCRIPTION') : '');

        let descHtml = '';
        if (description && !isCalendarPayload(description)) {
            const plain = String(description).replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
            if (plain && plain.toUpperCase().indexOf('BEGIN:VCALENDAR') === -1) {
                descHtml = '<p style="margin:16px 0 0;color:#334155;line-height:1.55;">'
                    + escapeHtml(plain.length > 900 ? (plain.slice(0, 900) + '…') : plain)
                    + '</p>';
            }
        }

        return ''
            + '<div style="max-width:560px;margin:8px auto;padding:20px 22px;border:1px solid #e2e8f0;border-radius:12px;background:#f8fafc;">'
            + '  <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px;color:#0f172a;">'
            + '    <span style="width:36px;height:36px;border-radius:10px;background:#eff6ff;color:#1d4ed8;display:inline-flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;letter-spacing:.04em;">CAL</span>'
            + '    <div>'
            + '      <div style="font-size:12px;font-weight:600;letter-spacing:.04em;text-transform:uppercase;color:#64748b;">Calendar invitation</div>'
            + '      <div style="font-size:16px;font-weight:600;margin-top:2px;">' + escapeHtml(summary) + '</div>'
            + '    </div>'
            + '  </div>'
            + (when ? ('<div style="font-size:13px;color:#475569;margin:6px 0;"><strong>When:</strong> '
                + escapeHtml(when + (dtEnd && !firstEvent ? ' – ' + dtEnd : '')) + '</div>') : '')
            + (location ? ('<div style="font-size:13px;color:#475569;margin:6px 0;"><strong>Where:</strong> '
                + escapeHtml(location) + '</div>') : '')
            + descHtml
            + '</div>';
    }

    function emailHtmlHasImages(html) {
        if (!html) return false;
        const stripped = String(html).replace(/<(script|style|head|noscript)\b[^>]*>[\s\S]*?<\/\1>/gi, ' ');
        return /<img\b/i.test(stripped);
    }

    function emailHtmlHasVisibleContent(html) {
        return emailHtmlHasVisibleText(html) || emailHtmlHasImages(html);
    }

    function attachmentLooksLikeImage(att) {
        if (!att) return false;
        const type = String(att.content_type || att.mime_type || '').toLowerCase();
        const name = resolveAttachmentDisplayName(att).toLowerCase();
        return type.startsWith('image/') || /\.(png|jpe?g|gif|webp|bmp)$/.test(name);
    }

    function collectReadingPaneImages(email) {
        const seen = {};
        const images = [];
        (email && email.attachments ? email.attachments : []).forEach(function (att) {
            if (!attachmentLooksLikeImage(att)) {
                return;
            }
            const url = getAttachmentPreviewUrl(att);
            if (!url || url === '#') {
                return;
            }
            if (seen[url]) {
                return;
            }
            seen[url] = true;
            images.push({
                url: url,
                name: resolveAttachmentDisplayName(att)
            });
        });
        return images;
    }

    function buildImageAttachmentBodyHtml(email) {
        const images = collectReadingPaneImages(email);
        if (!images.length) {
            return '';
        }
        const cards = images.map(function (img) {
            return '<figure class="email-photo-card">'
                + '<a href="' + escapeHtml(img.url) + '" target="_blank" rel="noopener" title="Open full size">'
                + '<img src="' + escapeHtml(img.url) + '" alt="' + escapeHtml(img.name) + '">'
                + '</a>'
                + '<figcaption>' + escapeHtml(img.name) + '</figcaption>'
                + '</figure>';
        }).join('');
        const heading = images.length === 1 ? 'Photo' : (images.length + ' photos');
        return '<div class="email-photo-body">'
            + '<div class="email-photo-body__label">' + heading + '</div>'
            + '<div class="email-photo-body__grid">' + cards + '</div>'
            + '</div>';
    }

    function emailHtmlHasVisibleText(html) {
        if (!html) return false;
        if (isCalendarPayload(html)) return false;
        let text = String(html)
            .replace(/<(script|style|head|noscript)\b[^>]*>[\s\S]*?<\/\1>/gi, ' ')
            .replace(/<[^>]+>/g, ' ');
        // Outlook often stores an "empty" body as &nbsp; / &#160; only.
        text = text
            .replace(/&nbsp;/gi, ' ')
            .replace(/&#160;/gi, ' ')
            .replace(/&#x0*a0;/gi, ' ')
            .replace(/[\u00a0\u200b\u200c\u200d\ufeff]/g, ' ');
        if (typeof document !== 'undefined') {
            const textarea = document.createElement('textarea');
            textarea.innerHTML = text;
            text = textarea.value;
        }
        text = text.replace(/\s+/g, ' ').trim();
        return text.length > 0;
    }

    // True HTML markup — not Outlook plain-text URL markers like <https://example.com/>.
    function emailContentLooksLikeHtml(content) {
        if (!content) {
            return false;
        }
        return /<(?:!DOCTYPE|html|head|body|div|p|br|span|table|tr|td|th|ul|ol|li|a\s|img|font|center|h[1-6]|blockquote|pre|hr|style|meta|strong|em|b\b|i\b)\b/i.test(String(content));
    }

    function formatPlainTextEmailBody(text) {
        let escaped = escapeHtml(String(text || ''))
            .replace(/\r\n/g, '\n')
            .replace(/\r/g, '\n');
        // Turn Outlook-style <https://...> markers into clickable links.
        escaped = escaped.replace(/&lt;(https?:\/\/[^&]+)&gt;/gi, function (_match, url) {
            return '<a href="' + url + '" rel="noopener noreferrer">' + url + '</a>';
        });
        escaped = escaped.replace(/\n/g, '<br>');
        return '<div class="email-plain-body" style="line-height:1.55;max-width:42em;">' + escaped + '</div>';
    }

    function extractRenderableEmailHtml(html) {
        if (!html) return '';
        const raw = String(html);
        const bodyMatch = raw.match(/<body[^>]*>([\s\S]*?)<\/body>/i);
        if (bodyMatch && emailHtmlHasVisibleContent(bodyMatch[1])) {
            return bodyMatch[1];
        }
        return raw;
    }

    function formatRecipientLine(label, value) {
        const cleaned = cleanRecipients(value);
        if (!cleaned) return '';
        return label + ': ' + cleaned;
    }

    function renderGmailReadingAttachments(items) {
        return renderReadingPaneAttachmentFooter(items);
    }

    function isStoredEmailCopyAttachment(item) {
        const name = String((item && item.name) || '').toLowerCase();
        return name === 'original email.msg' || name === 'parsed email.pdf';
    }

    function renderAttachmentActionButtons(item) {
        const previewBtn = item.previewUrl
            ? '<a href="' + item.previewUrl + '" target="_blank" rel="noopener" class="email-att-footer-card__btn" title="Preview">'
                + '<i class="fa-solid fa-eye" aria-hidden="true"></i><span>Preview</span></a>'
            : '';
        const downloadBtn = item.downloadUrl
            ? '<a href="' + item.downloadUrl + '" target="_blank" rel="noopener" class="email-att-footer-card__btn email-att-footer-card__btn--primary" title="Download">'
                + '<i class="fa-solid fa-download" aria-hidden="true"></i><span>Download</span></a>'
            : '';
        return previewBtn + downloadBtn;
    }

    function renderStoredEmailCopyCard(item) {
        const primaryHref = item.previewUrl || item.downloadUrl;
        const previewBtn = item.previewUrl
            ? '<a href="' + item.previewUrl + '" target="_blank" rel="noopener" class="email-att-copy-chip__btn" title="Preview">'
                + '<i class="fa-solid fa-eye" aria-hidden="true"></i></a>'
            : '';
        const downloadBtn = item.downloadUrl
            ? '<a href="' + item.downloadUrl + '" target="_blank" rel="noopener" class="email-att-copy-chip__btn email-att-copy-chip__btn--dl" title="Download">'
                + '<i class="fa-solid fa-download" aria-hidden="true"></i></a>'
            : '';

        return ''
            + '<div class="email-att-copy-chip">'
            + '  <a class="email-att-copy-chip__main" href="' + primaryHref + '" target="_blank" rel="noopener" title="' + escapeHtml(item.name) + '">'
            + '    <i class="fa-solid ' + item.icon + '" aria-hidden="true"></i>'
            + '    <span>' + escapeHtml(item.name) + '</span>'
            + '  </a>'
            + '  <div class="email-att-copy-chip__actions">' + previewBtn + downloadBtn + '</div>'
            + '</div>';
    }

    function getAttachmentGridTone(item) {
        const name = String((item && item.name) || '').toLowerCase();
        const icon = String((item && item.icon) || '').toLowerCase();
        if (name.endsWith('.pdf') || icon.indexOf('pdf') !== -1) {
            return 'pdf';
        }
        if (/\.(png|jpe?g|gif|webp|bmp)$/.test(name) || icon.indexOf('image') !== -1) {
            return 'image';
        }
        if (/\.(doc|docx)$/.test(name) || icon.indexOf('word') !== -1) {
            return 'word';
        }
        if (/\.(xls|xlsx|csv)$/.test(name) || icon.indexOf('excel') !== -1) {
            return 'excel';
        }
        return 'file';
    }

    function renderUserAttachmentGridCard(item) {
        const sizeLabel = formatFileSize(item.size);
        const primaryHref = item.previewUrl || item.downloadUrl || '#';
        const tone = getAttachmentGridTone(item);
        // CSS background can't render PDF pages; only use image previews as thumbnails.
        const nameLower = String(item.name || '').toLowerCase();
        const showThumb = !!(item.previewUrl && /\.(png|jpe?g|gif|webp|bmp)$/.test(nameLower));
        const shortName = String(item.name || 'file');
        const displayName = shortName.length > 22 ? (shortName.slice(0, 19) + '...') : shortName;

        const previewInner = showThumb
            ? '<div class="email-att-grid-card__preview email-att-grid-card__preview--thumb" style="background-image:url(\'' + String(item.previewUrl).replace(/'/g, '%27') + '\')"></div>'
            : '<div class="email-att-grid-card__preview email-att-grid-card__preview--empty">'
                + '<i class="fa-solid ' + item.icon + '" aria-hidden="true"></i>'
                + '</div>';

        const hoverActions = []
            .concat(item.previewUrl
                ? ['<a href="' + item.previewUrl + '" target="_blank" rel="noopener" class="email-att-grid-card__action" title="Preview" onclick="event.stopPropagation()"><i class="fa-solid fa-eye" aria-hidden="true"></i></a>']
                : [])
            .concat(item.downloadUrl
                ? ['<a href="' + item.downloadUrl + '" target="_blank" rel="noopener" class="email-att-grid-card__action" title="Download" onclick="event.stopPropagation()"><i class="fa-solid fa-download" aria-hidden="true"></i></a>']
                : [])
            .join('');

        return ''
            + '<div class="email-att-grid-card email-att-grid-card--' + tone + '">'
            + '  <a class="email-att-grid-card__hit" href="' + primaryHref + '" target="_blank" rel="noopener" aria-label="' + escapeHtml(item.name) + '"></a>'
            + previewInner
            + '  <div class="email-att-grid-card__hover">'
            + '    <div class="email-att-grid-card__hover-meta">'
            + '      <span class="email-att-grid-card__hover-name">' + escapeHtml(item.name) + '</span>'
            + (sizeLabel ? '      <span class="email-att-grid-card__hover-size">' + escapeHtml(sizeLabel) + '</span>' : '')
            + '    </div>'
            + (hoverActions ? '    <div class="email-att-grid-card__hover-actions">' + hoverActions + '</div>' : '')
            + '  </div>'
            + '  <div class="email-att-grid-card__bar">'
            + '    <span class="email-att-grid-card__bar-icon" aria-hidden="true"><i class="fa-solid ' + item.icon + '"></i></span>'
            + '    <span class="email-att-grid-card__bar-name" title="' + escapeHtml(item.name) + '">' + escapeHtml(displayName) + '</span>'
            + '    <span class="email-att-grid-card__fold" aria-hidden="true"></span>'
            + '  </div>'
            + '</div>';
    }

    function renderReadingPaneAttachmentFooter(items) {
        const storedCopies = [];
        const userFiles = [];
        items.forEach(function (item) {
            if (isStoredEmailCopyAttachment(item)) {
                storedCopies.push(item);
            } else {
                userFiles.push(item);
            }
        });

        // Count only real attachments — exclude Original email.msg / Parsed email.pdf.
        const totalCount = userFiles.length;
        let html = '<div class="email-att-footer">';

        if (totalCount > 0) {
            html += '  <div class="email-att-footer__title">'
                + '    <i class="fa-solid fa-paperclip" aria-hidden="true"></i>'
                + '    <span>' + totalCount + ' attachment' + (totalCount === 1 ? '' : 's') + '</span>'
                + '  </div>';
        }

        if (storedCopies.length) {
            html += '<div class="email-att-footer__copies">'
                + storedCopies.map(renderStoredEmailCopyCard).join('')
                + '</div>';
        }

        if (storedCopies.length && userFiles.length) {
            html += '<hr class="email-att-footer__divider" />';
        }

        if (userFiles.length) {
            html += '<div class="email-att-grid">'
                + '<div class="email-att-grid__header">'
                + '<span>' + userFiles.length + ' file' + (userFiles.length === 1 ? '' : 's') + '</span>'
                + '</div>'
                + '<div class="email-att-grid__list">'
                + userFiles.map(renderUserAttachmentGridCard).join('')
                + '</div>'
                + '</div>';
        }

        html += '</div>';
        return html;
    }

    function renderReadingPaneAttachments(email) {
        const items = collectEmailAttachmentItems(email);
        if (!items.length) {
            return '';
        }

        return renderReadingPaneAttachmentFooter(items);
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

            if (currentFolder === 'inbox' && unassignedOnly) {
                emptyMsg = 'No synced inbox emails';
                emptyHint = 'Use Sync now to fetch mail from Zoho.';
                emptyIcon = 'fa-inbox';
            } else if (currentFolder === 'unassigned') {
                emptyMsg = 'No unassigned emails';
                emptyHint = 'All synced mail is linked to clients. Use Sync now to fetch new mail.';
                emptyIcon = 'fa-user-clock';
            } else if (currentFolder === 'assigned') {
                emptyMsg = 'No assigned emails yet';
                emptyHint = 'Emails you assign from the Unassigned tab will appear here.';
                emptyIcon = 'fa-user-check';
            } else if (currentFolder === 'review') {
                emptyMsg = 'No auto-assigned emails need review';
                emptyHint = 'The current auto-assignments have a unique supporting client email or reference.';
                emptyIcon = 'fa-circle-check';
            }

            emailListContainer.innerHTML = renderListEmptyState(emptyMsg, emptyHint, emptyIcon);
            return;
        }

        const showDateGroups = isSyncedInboxFolder(currentFolder);
        let lastDateGroup = null;

        emails.forEach(function(email) {
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

            const el = document.createElement('div');
            const unread = isEmailUnread(email);
            el.className = 'email-item' + (unread ? ' unread' : '');
            if (selectedEmailId === email.id) {
                el.classList.add('active');
            }

            const sender = email.from_mail || 'Unknown';
            const subject = email.subject || '(No Subject)';
            const preview = (unassignedOnly && isSyncedInboxFolder(currentFolder))
                ? normalizePreviewText(email.text_preview || '', 90)
                : normalizePreviewText(email.text_preview || '', 55);
            
            const hasAttachment = getUserEmailAttachments(email).length > 0;
            const attachmentIcon = hasAttachment ? '<i class="fa-solid fa-paperclip email-list-clip" title="Has attachments"></i>' : '';
            const attachmentSummary = renderEmailAttachmentListSummary(email);

            let dateStr;
            if (unassignedOnly && isSyncedInboxFolder(currentFolder)) {
                dateStr = formatEmailDate(getEmailDate(email));
            } else {
                dateStr = isSyncedInboxFolder(currentFolder)
                    ? formatListEmailDate(email)
                    : formatEmailDate(getEmailDate(email));
            }
            const statusBadge = renderSendStatusBadge(email);
            const clientBadge = renderSyncedClientBadge(email);
            const syncSourceBadge = renderSyncSourceBadge(email);
            const calendarIndicator = renderCalendarListIndicator(email);

            if (unassignedOnly && isSyncedInboxFolder(currentFolder)) {
                const senderEmail = escapeHtml(sender);
                const reviewBadge = renderAssignmentReviewBadge(email);
                const tagHtml = [reviewBadge, clientBadge, attachmentIcon, calendarIndicator].filter(Boolean).join('');
                const tagsBlock = tagHtml
                    ? '<div class="email-item-synced-list__tags">' + tagHtml + '</div>'
                    : '';
                const previewHtml = preview
                    ? '<div class="email-item-synced-list__preview">' + escapeHtml(preview) + '</div>'
                    : '';

                el.classList.add('email-item--synced', 'email-item--synced-list');
                el.innerHTML = ''
                    + '<div class="email-item-synced-list">'
                    + '  <div class="email-item-synced-list__head">'
                    + '    <span class="email-item-synced-list__sender">' + senderEmail + '</span>'
                    +      tagsBlock
                    + '  </div>'
                    + '  <div class="email-item-synced-list__subject">' + escapeHtml(subject) + '</div>'
                    +      previewHtml
                    + '  <div class="email-item-synced-list__footer">'
                    + '    <span class="email-item-synced-list__date">' + dateStr + '</span>'
                    + '  </div>'
                    + '</div>';
            } else if (isSyncedInboxFolder(currentFolder)) {
                const senderInitial = escapeHtml((sender.charAt(0) || '?').toUpperCase());
                const senderName = escapeHtml(extractSenderName(sender));
                const badgeRow = attachmentIcon + calendarIndicator + statusBadge + syncSourceBadge + clientBadge;
                const previewHtml = preview
                    ? '<div class="email-preview">' + escapeHtml(preview) + '</div>'
                    : '';
                el.classList.add('email-item--synced', 'email-item--synced-compact');
                el.innerHTML = ''
                    + '<div class="email-item-synced email-item-synced--compact">'
                    + '  <div class="email-item-avatar-wrap">'
                    + '    <div class="email-item-avatar" aria-hidden="true">' + senderInitial + '</div>'
                    + '  </div>'
                    + '  <div class="email-item-body">'
                    + '    <div class="email-item-line-main">'
                    + '      <span class="email-sender-name">' + senderName + '</span>'
                    + '      <span class="email-item-line-sep">·</span>'
                    + '      <span class="email-subject">' + escapeHtml(subject) + '</span>'
                    + '    </div>'
                    +      previewHtml
                    + (badgeRow ? '    <div class="email-item-badges">' + badgeRow + '</div>' : '')
                    + '  </div>'
                    + '  <div class="email-item-side">'
                    + '    <div class="email-date">' + dateStr + '</div>'
                    + '  </div>'
                    + '</div>';
            } else {
                el.innerHTML = `
                <div class="email-item-header">
                    <div class="email-sender">${escapeHtml(sender)}${attachmentIcon}${calendarIndicator}${statusBadge}${syncSourceBadge}${clientBadge}</div>
                </div>
                <div class="email-subject">${escapeHtml(subject)}</div>
                <div class="email-preview">${escapeHtml(preview)}</div>
                <div class="email-item-footer">
                    ${attachmentSummary}
                    <div class="email-date">${dateStr}</div>
                </div>
            `;
            }

            el.addEventListener('click', () => {
                document.querySelectorAll('.email-item').forEach(i => i.classList.remove('active'));
                el.classList.add('active');
                selectedEmailId = email.id;
                showEmail(email, el);
            });

            emailListContainer.appendChild(el);
        });
    }

    function showEmail(email, listElement) {
        if (emptyState) {
            emptyState.style.display = 'none';
            emptyState.hidden = true;
        }
        if (readingPane) {
            readingPane.hidden = false;
            readingPane.classList.add('is-visible');
        }
        openGmailReadingView();

        selectedEmailId = email.id;
        if (listElement) {
            document.querySelectorAll('.email-item, .email-item--synced, .email-item--synced-compact, .email-item--synced-list').forEach(function (item) {
                item.classList.remove('active');
            });
            listElement.classList.add('active');
        }

        const readingScroll = document.querySelector('#readingPane .reading-scroll');
        if (readingScroll) {
            readingScroll.scrollTop = 0;
        } else if (readingPane) {
            readingPane.scrollTop = 0;
        }

        selectedEmail = email;

        renderReadingPaneCalendarBanner(email);

        document.getElementById('readSubject').textContent = email.subject || '(No Subject)';
        document.getElementById('readSender').textContent = email.from_mail || 'Unknown Sender';

        const readToEl = document.getElementById('readTo');
        const readCcEl = document.getElementById('readCc');
        const readBccEl = document.getElementById('readBcc');
        const cleanedTo = resolveToDisplay(email);
        const toLine = 'To: ' + cleanedTo;
        const ccLine = formatRecipientLine('Cc', email.cc);
        const bccLine = formatRecipientLine('Bcc', email.bcc);

        readToEl.hidden = false;
        if (isGmailUiMode()) {
            // Show actual To addresses (with caret for tooltip of full header lines).
            readToEl.innerHTML = '<span class="gmail-to-me">to ' + escapeHtml(cleanedTo)
                + ' <i class="fa-solid fa-caret-down" aria-hidden="true"></i></span>';
            const tooltipParts = [toLine];
            if (ccLine) {
                tooltipParts.push(ccLine);
            }
            if (bccLine) {
                tooltipParts.push(bccLine);
            }
            readToEl.title = tooltipParts.join('\n');
        } else {
            readToEl.textContent = toLine;
            readToEl.removeAttribute('title');
        }

        if (readCcEl) {
            if (ccLine) {
                readCcEl.textContent = ccLine;
                readCcEl.hidden = false;
            } else {
                readCcEl.textContent = '';
                readCcEl.hidden = true;
            }
        }

        // Bcc only when present (privacy-friendly: hidden when empty / unknown).
        if (readBccEl) {
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
            // Unassigned Mail page uses folder=inbox; show Assign by email state, not folder name.
            const needsAssign = !email.client_id
                && (
                    email.sync_assignment_status === 'unassigned'
                    || email.sync_assignment_status === 'unlinked'
                    || !!email.synced_email_id
                    || !!email.mailbox_email
                );
            btnAssignToClient.hidden = !canSyncInbox || !needsAssign;
        }

        if (btnUnlinkFromClient) {
            btnUnlinkFromClient.hidden = !canUnlinkSyncedEmail || !canShowReassignClient(email);
        }

        if (assignmentReviewBanner) {
            const review = email.assignment_review;
            if (review && review.reason) {
                assignmentReviewBanner.hidden = false;
                assignmentReviewBanner.innerHTML = '<i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>'
                    + '<div><strong>Why this email needs review</strong><span>'
                    + escapeHtml(review.reason)
                    + '</span></div>';
            } else {
                assignmentReviewBanner.hidden = true;
                assignmentReviewBanner.innerHTML = '';
            }
        }

        const readDateEl = document.getElementById('readDate');
        if (readDateEl) {
            readDateEl.textContent = isGmailUiMode()
                ? formatGmailReadDate(email)
                : formatEmailDate(getEmailDate(email));
        }

        const readSyncSourceEl = document.getElementById('readSyncSource');
        if (readSyncSourceEl) {
            const syncBadge = renderSyncSourceBadge(email);
            if (syncBadge) {
                readSyncSourceEl.hidden = false;
                readSyncSourceEl.innerHTML = syncBadge;
            } else {
                readSyncSourceEl.hidden = true;
                readSyncSourceEl.innerHTML = '';
            }
        }
        
        const initial = (email.from_mail || '?').charAt(0).toUpperCase();
        document.getElementById('readAvatar').textContent = initial;

        updateGmailReadingChrome(email);

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
        if ((email.body_deferred || (!email.message && email.id)) && !email._bodyLoaded) {
            if (!email._bodyFetchStarted) {
                email._bodyFetchStarted = true;
                if (iframe) {
                    iframe.srcdoc = '<!DOCTYPE html><html><body style="font-family:sans-serif;padding:16px;color:#5f6368;">Loading email…</body></html>';
                }
                fetch('/email-logs/' + encodeURIComponent(email.id) + '/body', {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin'
                })
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        if (data && data.success) {
                            email.message = data.message || '';
                            if (data.text_preview) {
                                email.text_preview = data.text_preview;
                            }
                        }
                        email.body_deferred = false;
                        email._bodyLoaded = true;
                        if (selectedEmailId === email.id) {
                            showEmail(email, listElement);
                        }
                    })
                    .catch(function() {
                        email.body_deferred = false;
                        email._bodyLoaded = true;
                        if (selectedEmailId === email.id) {
                            showEmail(email, listElement);
                        }
                    });
            }
            return;
        }

        let contentStr = (email.message || email.html_content || email.text_content || '').trim();
        const isCalendarInvite = !!email.is_calendar_invite;
        const calendarSource = isCalendarPayload(contentStr)
            ? contentStr
            : (isCalendarPayload(email.text_preview) ? String(email.text_preview || '') : '');

        if (isCalendarPayload(contentStr)) {
            contentStr = summarizeCalendarPayload(contentStr);
        }
        contentStr = extractRenderableEmailHtml(contentStr);
        let hasVisibleBody = emailHtmlHasVisibleContent(contentStr);
        // When the stored HTML/text body is empty but text_preview has readable content
        // (common for older manual uploads), use the preview instead of an image gallery.
        if (!hasVisibleBody) {
            const previewFallback = String(email.text_preview || '').trim();
            if (previewFallback && !isCalendarPayload(previewFallback) && emailHtmlHasVisibleText(previewFallback)) {
                contentStr = previewFallback;
                hasVisibleBody = true;
            }
        }
        const isHtmlBody = hasVisibleBody && emailContentLooksLikeHtml(contentStr);
        const hasCalendarInviteBody = isCalendarInvite || (!!calendarSource && !hasVisibleBody);
        const imageBodyHtml = (!hasVisibleBody && !hasCalendarInviteBody)
            ? buildImageAttachmentBodyHtml(email)
            : '';

        // Prefer the rendered PDF when we only have plain text (no real HTML body) —
        // that matches a normal email view. Never auto-embed PDF for calendar invites
        // or image-only mail (those PDFs are often empty/black).
        let pdfToPreview = null;
        if (!hasCalendarInviteBody && !imageBodyHtml) {
            if (!isHtmlBody && email.pdf_preview_url) {
                pdfToPreview = email.pdf_preview_url;
            } else if (!hasVisibleBody) {
                if (email.pdf_preview_url) {
                    pdfToPreview = email.pdf_preview_url;
                } else if (email.attachments && email.attachments.length > 0) {
                    const pdfAtt = email.attachments.find(function(a) {
                        const name = resolveAttachmentDisplayName(a).toLowerCase();
                        return name.endsWith('.pdf') && name !== 'parsed email.pdf';
                    });
                    if (pdfAtt) {
                        pdfToPreview = getAttachmentPreviewUrl(pdfAtt);
                    }
                }
            }
        }

        if (pdfToPreview) {
            // PDF.js / Chrome viewer needs scripts; the HTML sandbox blocks them and
            // leaves a blank reading pane when the stored body is empty.
            // Also: if Parsed email.pdf is missing from S3, /documents/preview returns
            // Laravel "404 | Not Found" inside this iframe — detect and fall back.
            function buildUnavailableEmailBodyHtml(reason) {
                let html = '<div style="font-family:system-ui,-apple-system,sans-serif;padding:16px;color:#374151;line-height:1.45;">'
                    + '<p style="margin:0 0 8px;font-weight:600;">Email content could not be previewed.</p>';
                if (reason === 'pdf_missing') {
                    html += '<p style="margin:0 0 8px;">The stored PDF preview is missing from storage. '
                        + 'Use <strong>Original email.msg</strong> or <strong>Parsed email.pdf</strong> under attachments if available.</p>';
                } else {
                    html += '<p style="margin:0 0 8px;">No content available.</p>';
                }
                const preview = String(contentStr || email.message || email.text_preview || '').trim();
                if (preview && !isCalendarPayload(preview)) {
                    html += formatPlainTextEmailBody(preview);
                }
                html += '</div>';
                return html;
            }

            function fallbackReadingPaneFromFailedPdf() {
                iframe.onload = null;
                iframe.removeAttribute('src');
                iframe.classList.remove('read-body--pdf');
                setReadingBodyMode(iframe, '');
                iframe.setAttribute('sandbox', READ_BODY_SANDBOX);
                renderHtmlIframe(iframe, buildUnavailableEmailBodyHtml('pdf_missing'));
                resetReadBodyIframeSizing(iframe);
            }

            function isEmbeddedPreviewHttpErrorPage(doc) {
                try {
                    const text = ((doc && doc.body) ? doc.body.innerText : '') || '';
                    const title = ((doc && doc.title) ? doc.title : '') || '';
                    const combined = (title + '\n' + text).replace(/\s+/g, ' ').trim();
                    if (!combined) {
                        return false;
                    }
                    if (/403\s*\|\s*Forbidden/i.test(combined) || /404\s*\|\s*Not Found/i.test(combined)) {
                        return true;
                    }
                    if (/^\s*40[34]\b/.test(combined)) {
                        return true;
                    }
                    if (/File not found in S3/i.test(combined) || /Error loading preview/i.test(combined)) {
                        return true;
                    }
                    return false;
                } catch (e) {
                    return false;
                }
            }

            iframe.classList.add('read-body--pdf');
            setReadingBodyMode(iframe, 'pdf');
            iframe.onload = function () {
                try {
                    const doc = iframe.contentDocument || iframe.contentWindow.document;
                    if (isEmbeddedPreviewHttpErrorPage(doc)) {
                        fallbackReadingPaneFromFailedPdf();
                        return;
                    }
                } catch (e) {
                    // Cross-origin PDF viewers are fine.
                }
                resetReadBodyIframeSizing(iframe);
            };
            iframe.removeAttribute('srcdoc');
            iframe.removeAttribute('sandbox');
            iframe.src = pdfToPreview + (pdfToPreview.indexOf('#') === -1 ? '#view=FitH' : '');
            resetReadBodyIframeSizing(iframe);
        } else {
            iframe.onload = null;
            setReadingBodyMode(iframe, imageBodyHtml ? 'photo' : '');
            iframe.removeAttribute('src');
            iframe.removeAttribute('srcdoc');
            iframe.setAttribute('sandbox', READ_BODY_SANDBOX);
            let bodyHtml = hasVisibleBody ? contentStr : '';
            if (hasCalendarInviteBody) {
                bodyHtml = buildCalendarInviteBodyHtml(calendarSource || contentStr, email);
            } else if (imageBodyHtml) {
                bodyHtml = imageBodyHtml;
            } else if (bodyHtml && emailContentLooksLikeHtml(bodyHtml)) {
                bodyHtml = replaceCidReferencesInHtml(bodyHtml, email.attachments || []);
            } else if (bodyHtml) {
                bodyHtml = formatPlainTextEmailBody(bodyHtml);
            }
            renderHtmlIframe(iframe, bodyHtml || '<p>No content available.</p>');
            resetReadBodyIframeSizing(iframe);
        }
    }

    function normalizeSignatureHtml(html) {
        if (typeof window.crmNormalizeSignatureHtml === 'function') {
            return window.crmNormalizeSignatureHtml(html || '');
        }
        return html || '';
    }

    function sizeComposeContentIframe(iframe) {
        if (!iframe) {
            return;
        }
        try {
            const doc = iframe.contentDocument || iframe.contentWindow.document;
            if (doc && doc.body) {
                const height = Math.max(doc.body.scrollHeight, doc.documentElement.scrollHeight, 280);
                iframe.style.height = (height + 24) + 'px';
                iframe.style.minHeight = '280px';
            }
        } catch (e) {
            iframe.style.height = '280px';
            iframe.style.minHeight = '280px';
        }
    }

    function sizeCompactHtmlIframe(iframe) {
        if (!iframe) {
            return;
        }
        try {
            const doc = iframe.contentDocument || iframe.contentWindow.document;
            if (doc && doc.body) {
                const height = Math.max(doc.body.scrollHeight, doc.documentElement.scrollHeight, 48);
                iframe.style.height = (height + 16) + 'px';
                iframe.style.minHeight = '48px';
            }
        } catch (e) {
            iframe.style.height = '80px';
            iframe.style.minHeight = '48px';
        }
    }

    function renderHtmlIframe(iframe, html, options) {
        if (!iframe) return;
        const opts = options || {};
        const doc = iframe.contentDocument || iframe.contentWindow.document;
        let bodyHtml = html || '';
        if (opts.normalizeSignature) {
            bodyHtml = normalizeSignatureHtml(bodyHtml);
        }
        doc.open();
        doc.write('<!DOCTYPE html><html><head><meta charset="utf-8"><base target="_blank"><style>' +
            'html,body{margin:0;padding:0;box-sizing:border-box;max-width:100%;width:100%;}' +
            'body{font-family:"Segoe UI",-apple-system,BlinkMacSystemFont,sans-serif;font-size:14px;line-height:1.6;color:#242424;word-wrap:break-word;overflow-wrap:break-word;padding:16px 20px;overflow-x:hidden;}' +
            'img{max-width:100%!important;height:auto!important;width:auto!important;object-fit:contain;display:block;}' +
            'table{max-width:100%;}' +
            'a{color:#0078d4;}' +
            'blockquote{margin:0;padding-left:12px;border-left:3px solid #edebe9;color:#605e5c;}' +
            'p{margin:0 0 0.75em;}' +
            '.email-photo-body{max-width:100%;}' +
            '.email-photo-body__label{font-size:12px;font-weight:600;color:#64748b;letter-spacing:.04em;text-transform:uppercase;margin:0 0 10px;}' +
            '.email-photo-body__grid{display:flex;flex-wrap:wrap;gap:12px;align-items:flex-start;}' +
            '.email-photo-card{margin:0;max-width:min(100%,360px);}' +
            '.email-photo-card a{display:block;line-height:0;}' +
            '.email-photo-card img{max-width:100%!important;max-height:420px!important;width:auto!important;height:auto!important;object-fit:contain;border:1px solid #e2e8f0;border-radius:10px;background:#f8fafc;}' +
            '.email-photo-card figcaption{margin-top:8px;font-size:12px;color:#64748b;word-break:break-all;}' +
            '</style></head><body>' + bodyHtml + '</body></html>');
        doc.close();

        const resize = opts.composeContent
            ? sizeComposeContentIframe
            : (opts.compact ? sizeCompactHtmlIframe : resetReadBodyIframeSizing);
        try {
            const imgs = doc.images ? Array.prototype.slice.call(doc.images) : [];
            imgs.forEach(function (img) {
                if (img.complete) {
                    return;
                }
                img.addEventListener('load', function () {
                    resize(iframe);
                });
                img.addEventListener('error', function () {
                    resize(iframe);
                });
            });
        } catch (e) {
            // Ignore cross-document access issues.
        }
        setTimeout(function() {
            resize(iframe);
        }, 50);
        setTimeout(function() {
            resize(iframe);
        }, 300);
    }

    function hideComposeOptionalFields() {
        if (composeCcField) {
            composeCcField.hidden = true;
        }
        if (composeBccField) {
            composeBccField.hidden = true;
        }
        if (composeShowCc) {
            composeShowCc.hidden = false;
        }
        if (composeShowBcc) {
            composeShowBcc.hidden = false;
        }
    }

    function showComposeOptionalField(which) {
        if (which === 'cc' && composeCcField) {
            composeCcField.hidden = false;
            if (composeShowCc) {
                composeShowCc.hidden = true;
            }
        }
        if (which === 'bcc' && composeBccField) {
            composeBccField.hidden = false;
            if (composeShowBcc) {
                composeShowBcc.hidden = true;
            }
        }
    }

    function syncComposeOptionalFields() {
        const ccValue = ccInput ? String(ccInput.value || '').trim() : '';
        const bccValue = bccInput ? String(bccInput.value || '').trim() : '';
        if (ccValue) {
            showComposeOptionalField('cc');
        }
        if (bccValue) {
            showComposeOptionalField('bcc');
        }
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
            composeQuoteToggleLabel.textContent = 'Original email';
        }
        if (composeQuoteFrame) {
            composeQuoteFrame.style.height = '280px';
            renderHtmlIframe(composeQuoteFrame, '', { composeContent: true });
        }
        if (composeSignatureWrap) {
            composeSignatureWrap.hidden = true;
        }
        if (composeSignatureFrame) {
            composeSignatureFrame.style.height = '48px';
            renderHtmlIframe(composeSignatureFrame, '', { compact: true, normalizeSignature: true });
        }
        hideComposeOptionalFields();
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
            composeQuoteToggleLabel.textContent = 'Original email';
        }
        renderHtmlIframe(composeQuoteFrame, composeQuoteHtml, { composeContent: true });
    }

    function setComposeSignature(signatureHtml) {
        composeSignatureHtml = normalizeSignatureHtml(signatureHtml || '').trim();
        if (!composeSignatureWrap || !composeSignatureFrame) {
            return;
        }
        if (!composeSignatureHtml) {
            composeSignatureWrap.hidden = true;
            return;
        }
        composeSignatureWrap.hidden = false;
        renderHtmlIframe(composeSignatureFrame, composeSignatureHtml, {
            compact: true,
            normalizeSignature: true
        });
    }

    function insertComposeTable() {
        const rowsRaw = window.prompt('Number of rows', '2');
        if (rowsRaw === null) {
            return;
        }
        const colsRaw = window.prompt('Number of columns', '2');
        if (colsRaw === null) {
            return;
        }
        const rows = Math.min(Math.max(parseInt(rowsRaw, 10) || 0, 1), 10);
        const cols = Math.min(Math.max(parseInt(colsRaw, 10) || 0, 1), 8);
        let tableHtml = '<table border="1" cellpadding="6" cellspacing="0" style="border-collapse:collapse;width:100%;">';
        for (let r = 0; r < rows; r++) {
            tableHtml += '<tr>';
            for (let c = 0; c < cols; c++) {
                tableHtml += '<td>&nbsp;</td>';
            }
            tableHtml += '</tr>';
        }
        tableHtml += '</table><p><br></p>';
        if (composeReplyInput) {
            composeReplyInput.focus();
        }
        document.execCommand('insertHTML', false, tableHtml);
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
        const bccLine = cleanRecipients(email.bcc);
        if (action === 'forward') {
            let header = '---------- Forwarded message ---------<br>From: <strong>' +
                escapeHtml(email.from_mail) + '</strong><br>Date: ' + escapeHtml(emailDate) +
                '<br>Subject: ' + escapeHtml(email.subject);
            if (ccLine) {
                header += '<br>Cc: ' + escapeHtml(ccLine);
            }
            if (bccLine) {
                header += '<br>Bcc: ' + escapeHtml(bccLine);
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
        if (bccLine) {
            header += '<br><b>Bcc:</b> ' + escapeHtml(bccLine);
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
            return normalizeSignatureHtml(await window.crmFetchStaffSignature(fromEmail || '')).trim();
        }
        if (window.__crmCurrentUserSignature) {
            return normalizeSignatureHtml(window.__crmCurrentUserSignature).trim();
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
            return normalizeSignatureHtml((data && data.signature) ? String(data.signature) : '').trim();
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
        } else if (email.message && emailContentLooksLikeHtml(email.message)) {
            emailHtml = email.message;
        } else if (email.text_content) {
            emailHtml = formatPlainTextEmailBody(email.text_content);
        } else if (email.message) {
            emailHtml = formatPlainTextEmailBody(email.message);
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
        syncComposeOptionalFields();

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
        syncComposeOptionalFields();

        setComposeQuote('');
        setComposeSignature('');

        const messageHtml = email.message || email.html_content || email.text_content || '';
        if (composeReplyInput) {
            composeReplyInput.innerHTML = emailContentLooksLikeHtml(messageHtml)
                ? messageHtml
                : formatPlainTextEmailBody(messageHtml);
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

        const attachmentCount = getUserEmailAttachments(email).length;
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
        if (isCalendarPayload(text)) {
            const summary = icsPropertyValue(text, 'SUMMARY') || 'Calendar invitation';
            return summary.substring(0, maxLen || 80);
        }
        let html = String(text)
            .replace(/<(script|style|head|noscript)\b[^>]*>[\s\S]*?<\/\1>/gi, ' ')
            .replace(/<[^>]+>/g, ' ')
            .replace(/[.#]?[\w-]+\s*\{[^}]*\}/g, ' ');
        const textarea = document.createElement('textarea');
        textarea.innerHTML = html;
        const decoded = textarea.value.replace(/\s+/g, ' ').trim();
        if (isCalendarPayload(decoded)) {
            const summary = icsPropertyValue(decoded, 'SUMMARY') || 'Calendar invitation';
            return summary.substring(0, maxLen || 80);
        }
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
        if (assignmentModalMode === 'unlink' && unlinkDestinationMode === 'unassigned') {
            assignEmailConfirmBtn.disabled = !selectedEmailId;
            return;
        }
        const clientTs = assignClientSelect && assignClientSelect.tomselect ? assignClientSelect.tomselect : null;
        const typingQuery = clientTs && clientTs.control_input
            ? String(clientTs.control_input.value || '').trim()
            : '';
        const clientIdValue = (!typingQuery && assignClientSelect)
            ? extractClientIdFromTomSelectValue(assignClientSelect.value, clientTs)
            : null;
        const hasClient = !!clientIdValue;
        const hasMatter = assignMatterHiddenInput && assignMatterHiddenInput.value !== '';
        assignEmailConfirmBtn.disabled = !(hasClient && hasMatter);
    }

    function setAssignModalBusy(isBusy, busyLabel) {
        if (!assignEmailConfirmBtn) {
            return;
        }

        if (isBusy) {
            if (typeof assignEmailConfirmBtn.dataset.idleHtml !== 'string') {
                assignEmailConfirmBtn.dataset.idleHtml = assignEmailConfirmBtn.innerHTML;
            }
            assignEmailConfirmBtn.disabled = true;
            assignEmailConfirmBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i> '
                + (busyLabel || 'Working...');
        } else {
            if (typeof assignEmailConfirmBtn.dataset.idleHtml === 'string') {
                assignEmailConfirmBtn.innerHTML = assignEmailConfirmBtn.dataset.idleHtml;
                delete assignEmailConfirmBtn.dataset.idleHtml;
            }
        }

        assignEmailConfirmBtn.classList.toggle('is-loading', isBusy);

        if (assignEmailModal) {
            assignEmailModal.classList.toggle('assign-email-modal--busy', isBusy);
            assignEmailModal.querySelectorAll('.assign-email-modal__close, .assign-email-modal__btn--cancel')
                .forEach(function (button) {
                    button.disabled = isBusy;
                });
        }

        unlinkDestinationButtons.forEach(function (button) {
            button.disabled = isBusy;
        });
    }

    function clearAssignSelectedClientCard() {
        if (assignSelectedClient) {
            assignSelectedClient.hidden = true;
        }
        if (assignSelectedClientName) {
            assignSelectedClientName.textContent = '';
        }
        if (assignSelectedClientMeta) {
            assignSelectedClientMeta.textContent = '';
        }
        if (assignClientField) {
            assignClientField.classList.remove('assign-email-field--client-picked');
        }
        if (assignClientPicker) {
            assignClientPicker.hidden = false;
        }
    }

    function showAssignSelectedClientCard(option) {
        if (!assignSelectedClient || !assignSelectedClientName) {
            return;
        }
        const opt = option || {};
        const name = String(opt.name || opt.text || '').trim() || 'Selected client';
        const metaParts = [];
        if (opt.email) {
            metaParts.push(String(opt.email).trim());
        }
        if (opt.phones) {
            metaParts.push(String(opt.phones).trim());
        }
        const refLabel = opt.search_label || opt.client_id || opt.matter_ref || '';
        if (refLabel) {
            metaParts.push(String(refLabel).trim());
        } else if (opt.status) {
            metaParts.push(String(opt.status).trim());
        }

        assignSelectedClientName.textContent = name;
        if (assignSelectedClientMeta) {
            assignSelectedClientMeta.textContent = metaParts.filter(Boolean).join(' · ');
            assignSelectedClientMeta.hidden = metaParts.length === 0;
        }
        assignSelectedClient.hidden = false;
        if (assignClientField) {
            assignClientField.classList.add('assign-email-field--client-picked');
        }
        // Hide the cramped Tom Select value row; the card shows the full name.
        if (assignClientPicker) {
            assignClientPicker.hidden = true;
        }
        if (assignSenderHint) {
            assignSenderHint.hidden = true;
        }
    }

    function beginAssignClientReselect() {
        clearAssignSelectedClientCard();
        clearAssignClientSelectionForSearch();
        updateAssignSenderHint();
        const clientTs = assignClientSelect && assignClientSelect.tomselect
            ? assignClientSelect.tomselect
            : null;
        if (clientTs) {
            setTimeout(function () {
                clientTs.focus();
                clientTs.open();
            }, 20);
        }
    }

    function updateAssignStepProgress() {
        const clientTs = assignClientSelect && assignClientSelect.tomselect ? assignClientSelect.tomselect : null;
        const typingQuery = clientTs && clientTs.control_input
            ? String(clientTs.control_input.value || '').trim()
            : '';
        // While the user is typing a new query, do not treat a stale selection as final.
        const hasClient = !typingQuery && !!extractClientIdFromTomSelectValue(
            assignClientSelect ? assignClientSelect.value : '',
            clientTs
        );

        if (assignClientField) {
            assignClientField.classList.toggle('assign-email-field--done', hasClient);
            assignClientField.classList.toggle('assign-email-field--searching', !!typingQuery);
        }
        if (assignMatterField) {
            assignMatterField.classList.toggle('assign-email-field--disabled', !hasClient);
            assignMatterField.classList.toggle('assign-email-field--ready-step', hasClient);
        }
        if (assignClientHint) {
            if (typingQuery) {
                assignClientHint.textContent = 'Pick a client from the results below.';
            } else if (hasClient) {
                assignClientHint.textContent = 'Client selected. Choose a matter below, or tap Change to search again.';
            } else {
                assignClientHint.textContent = 'Type a name, email, phone, or client ref — then pick a result.';
            }
        }

        if (!hasClient) {
            clearAssignSelectedClientCard();
            updateAssignSenderHint();
        }
    }

    function clearAssignClientSelectionForSearch() {
        const clientTs = assignClientSelect && assignClientSelect.tomselect
            ? assignClientSelect.tomselect
            : null;
        if (clientTs && clientTs.items && clientTs.items.length) {
            clientTs.clear(true);
        }
        clearAssignSelectedClientCard();
        clearAssignMatterSelection();
        updateAssignConfirmButton();
        updateAssignStepProgress();
    }

    function getSelectedEmailSenderAddress() {
        if (!selectedEmail || !selectedEmail.from_mail) {
            return '';
        }
        const parsed = parseRecipientField(selectedEmail.from_mail);
        if (parsed.length) {
            return parsed[0];
        }
        const raw = String(selectedEmail.from_mail).trim();
        return raw.includes('@') ? raw : '';
    }

    function updateAssignSenderHint() {
        const sender = getSelectedEmailSenderAddress();
        if (!assignSenderHint || !assignSearchSenderBtn) {
            return;
        }
        if (!sender) {
            assignSenderHint.hidden = true;
            assignSearchSenderBtn.textContent = '';
            return;
        }
        assignSenderHint.hidden = false;
        assignSearchSenderBtn.textContent = sender;
    }

    function runAssignClientSearch(query) {
        if (!assignClientSelect) {
            return;
        }
        const clientTs = assignClientSelect.tomselect;
        const q = String(query || '').trim();
        if (!clientTs) {
            return;
        }
        clearAssignClientSelectionForSearch();
        clientTs.focus();
        if (!q) {
            clientTs.open();
            return;
        }
        if (typeof clientTs.setTextboxValue === 'function') {
            clientTs.setTextboxValue(q);
        }
        if (typeof clientTs.load === 'function') {
            clientTs.load(q);
        }
        clientTs.open();
        updateAssignStepProgress();
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

    // Only synced mail has an Unassigned Mail queue to fall back to; uploaded mail
    // would disappear from every list if it were left without a client.
    function selectedEmailCanReturnToUnassigned() {
        return !!(selectedEmail && selectedEmail.synced_email_id);
    }

    function configureAssignmentModal(mode, destination) {
        assignmentModalMode = mode === 'unlink' ? 'unlink' : 'assign';
        const canReturnToUnassigned = selectedEmailCanReturnToUnassigned();
        unlinkDestinationMode = (destination === 'client' || !canReturnToUnassigned) ? 'client' : 'unassigned';
        const isUnlink = assignmentModalMode === 'unlink';
        const selectingClient = !isUnlink || unlinkDestinationMode === 'client';

        if (assignEmailModalTitle) {
            assignEmailModalTitle.textContent = isUnlink ? 'Reassign Email' : 'Assign Email to Client';
        }
        if (assignEmailModalSubtitle) {
            if (!isUnlink) {
                assignEmailModalSubtitle.textContent = 'Pick a client, then a matter.';
            } else if (canReturnToUnassigned) {
                assignEmailModalSubtitle.textContent = 'Move to Unassigned Mail, or pick another client and matter.';
            } else {
                assignEmailModalSubtitle.textContent = 'This uploaded email can only be reassigned to another client.';
            }
        }
        if (assignEmailModalIcon) {
            assignEmailModalIcon.innerHTML = '<i class="fa-solid '
                + (isUnlink ? 'fa-arrow-right-arrow-left' : 'fa-user-plus')
                + '"></i>';
        }
        if (unlinkEmailDestination) {
            unlinkEmailDestination.hidden = !isUnlink;
        }
        if (assignEmailFields) {
            assignEmailFields.hidden = !selectingClient;
        }
        if (assignSenderHint && !selectingClient) {
            assignSenderHint.hidden = true;
        }

        unlinkDestinationButtons.forEach(function (button) {
            const isUnassignedOption = button.dataset.unlinkDestination !== 'client';
            const unavailable = isUnassignedOption && !canReturnToUnassigned;
            button.classList.toggle('active', button.dataset.unlinkDestination === unlinkDestinationMode);
            button.classList.toggle('disabled', unavailable);
            button.disabled = unavailable;
            button.title = unavailable ? 'Uploaded emails cannot be moved to Unassigned Mail.' : '';
        });

        if (assignEmailConfirmBtn) {
            delete assignEmailConfirmBtn.dataset.idleHtml;
            if (isUnlink && unlinkDestinationMode === 'unassigned') {
                assignEmailConfirmBtn.innerHTML = '<i class="fa-solid fa-link-slash" aria-hidden="true"></i> Move to Unassigned';
            } else if (isUnlink) {
                assignEmailConfirmBtn.innerHTML = '<i class="fa-solid fa-arrow-right-arrow-left" aria-hidden="true"></i> Reassign Email';
            } else {
                assignEmailConfirmBtn.innerHTML = '<i class="fa-solid fa-link" aria-hidden="true"></i> Assign Email';
            }
        }

        updateAssignConfirmButton();
        updateAssignStepProgress();
    }

    function openAssignmentModal(mode) {
        if (!selectedEmailId || !assignEmailModal) {
            crmToast('Select an email first.', 'warning');
            return;
        }

        // Move modal to body root to avoid parent container CSS stacking context trapping
        // (which causes .modal-backdrop z-index 1050 to sit above the modal).
        if (assignEmailModal.parentNode && assignEmailModal.parentNode !== document.body) {
            document.body.appendChild(assignEmailModal);
        }

        if (assignEmailLogIdInput) {
            assignEmailLogIdInput.value = selectedEmailId;
        }
        if (assignEmailStatus) {
            assignEmailStatus.hidden = true;
            assignEmailStatus.textContent = '';
        }

        configureAssignmentModal(mode, 'unassigned');
        populateAssignEmailPreview();
        updateAssignSenderHint();

        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            bootstrap.Modal.getOrCreateInstance(assignEmailModal, { focus: false }).show();
        } else {
            document.body.classList.add('assign-email-modal-open');
            assignEmailModal.classList.add('show');
            assignEmailModal.style.display = 'block';
            initAssignEmailModalSelects();
        }
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
        if (assignMatterPlaceholder) {
            assignMatterPlaceholder.innerHTML = '<span>Select a client above to load matters.</span>';
        }
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

        // Keep dropdown inside the control wrapper so it sits under the search box.
        // Modal overflow is set to visible for this field (see outlook_emails.css).
        const dropdownParent = null;
        const clientsUrl = baseUrl
            ? baseUrl.replace(/\/$/, '') + '/clients/get-allclients'
            : '';

        if (assignClientSelect) {
            let clientTs = null;
            if (typeof buildGetAllClientsTomSelectConfig === 'function' && clientsUrl) {
                const clientTsConfig = buildGetAllClientsTomSelectConfig({
                    url: clientsUrl,
                    dropdownParent: dropdownParent,
                    placeholder: 'Type name, email, phone, or client ref…',
                    minQueryLength: 1,
                    loadThrottle: 200,
                    onChange: function (value) {
                        clearAssignMatterSelection();
                        if (!value) {
                            clearAssignSelectedClientCard();
                            updateAssignConfirmButton();
                            updateAssignStepProgress();
                            return;
                        }
                        const option = this.options[value] || {};
                        if (option.locked) {
                            this.clear(true);
                            clearAssignSelectedClientCard();
                            if (typeof window.openCrmAccessModal === 'function') {
                                window.openCrmAccessModal(option);
                            }
                            updateAssignConfirmButton();
                            updateAssignStepProgress();
                            return;
                        }
                        const resolvedClientId = extractClientIdFromTomSelectValue(value, this);
                        if (!resolvedClientId) {
                            this.clear(true);
                            clearAssignSelectedClientCard();
                            crmToast('Could not read that client. Try searching again.', 'warning');
                            updateAssignConfirmButton();
                            updateAssignStepProgress();
                            return;
                        }
                        // Clear any leftover search text so the UI shows the chosen client.
                        if (typeof this.setTextboxValue === 'function') {
                            this.setTextboxValue('');
                        }
                        showAssignSelectedClientCard(option);
                        const matterRef = extractMatterRefFromTomSelectValue(value);
                        loadAssignMattersForClient(resolvedClientId, matterRef);
                        updateAssignConfirmButton();
                        updateAssignStepProgress();
                    }
                });
                clientTsConfig.allowEmptyOption = false;
                clientTsConfig.openOnFocus = true;
                clientTsConfig.closeAfterSelect = true;
                clientTsConfig.maxOptions = 50;
                // Remote results are already filtered by the server — show all of them.
                clientTsConfig.score = function () {
                    return function () { return 1; };
                };
                if (!clientTsConfig.render) {
                    clientTsConfig.render = {};
                }
                clientTsConfig.render.no_results = function (_data, escape) {
                    const q = escape(String((_data && _data.input) || '').trim());
                    return '<div class="no-results assign-email-ts-empty">No clients found'
                        + (q ? ' for “' + q + '”' : '')
                        + '. Try another name, email, or ref.</div>';
                };
                clientTsConfig.render.loading = function () {
                    return '<div class="assign-email-ts-loading">Searching…</div>';
                };
                // Selected value is shown in the full-name card, not inside the cramped input.
                clientTsConfig.render.item = function (item, escape) {
                    return '<div class="assign-email-ts-item">' + escape(item.name || item.text || '') + '</div>';
                };

                // Clear stale selection when the user starts a new search, and keep the menu open.
                clientTsConfig.onType = function () {
                    if (this.items && this.items.length) {
                        this.clear(true);
                        clearAssignSelectedClientCard();
                        clearAssignMatterSelection();
                        updateAssignConfirmButton();
                    }
                    if (assignClientPicker) {
                        assignClientPicker.hidden = false;
                    }
                    updateAssignStepProgress();
                    this.open();
                };
                clientTsConfig.onFocus = function () {
                    updateAssignStepProgress();
                    if (!(this.items && this.items.length)) {
                        this.open();
                    }
                };
                clientTsConfig.onBlur = function () {
                    updateAssignStepProgress();
                };
                clientTsConfig.onDropdownOpen = function () {
                    const self = this;
                    // Reposition after layout so results sit under the search box.
                    requestAnimationFrame(function () {
                        if (typeof self.positionDropdown === 'function') {
                            self.positionDropdown();
                        }
                    });
                };

                const remoteLoad = clientTsConfig.load;
                clientTsConfig.load = function (query, callback) {
                    const self = this;
                    if (typeof remoteLoad !== 'function') {
                        callback([]);
                        return;
                    }
                    remoteLoad.call(self, query, function (items) {
                        const list = Array.isArray(items) ? items : [];
                        callback(list);
                        setTimeout(function () {
                            try {
                                if (typeof self.refreshOptions === 'function') {
                                    self.refreshOptions(true);
                                } else {
                                    self.open();
                                }
                                if (typeof self.positionDropdown === 'function') {
                                    self.positionDropdown();
                                }
                            } catch (e) { /* ignore */ }
                            updateAssignStepProgress();
                        }, 0);
                    });
                };

                clientTs = initTS(assignClientSelect, clientTsConfig);
                if (clientTs && clientTs.wrapper) {
                    clientTs.wrapper.classList.add('assign-email-ts');
                    clientTs.wrapper.style.width = '100%';
                }
                if (clientTs && clientTs.dropdown) {
                    clientTs.dropdown.classList.add('assign-email-ts-dropdown');
                }
                // Ensure preselected page client also carries cid for extractClientId…
                if (clientTs && clientId) {
                    const existing = clientTs.options[String(clientId)];
                    if (existing && (existing.cid == null || existing.cid === '')) {
                        clientTs.updateOption(String(clientId), Object.assign({}, existing, {
                            cid: parseInt(clientId, 10)
                        }));
                    } else if (!existing) {
                        const label = assignClientSelect.options.length
                            ? (assignClientSelect.options[0].textContent || '').trim()
                            : ('Client #' + clientId);
                        clientTs.addOption({
                            id: String(clientId),
                            name: label,
                            text: label,
                            cid: parseInt(clientId, 10)
                        });
                        clientTs.setValue(String(clientId), true);
                    }
                }
            } else {
                clientTs = initTS(assignClientSelect, {
                    create: false,
                    dropdownParent: dropdownParent,
                    placeholder: 'Type name, email, phone, or client ref…',
                    openOnFocus: true
                });
            }

            // Auto-load matters if a client is preselected in the dropdown or page context
            const initVal = assignClientSelect ? assignClientSelect.value : '';
            const initClientId = extractClientIdFromTomSelectValue(initVal, clientTs) || (clientId ? parseInt(clientId, 10) : null);
            const isUnlinkMode = assignmentModalMode === 'unlink';
            const isSelectingClientMode = !isUnlinkMode || unlinkDestinationMode === 'client';
            if (initClientId && isSelectingClientMode) {
                const rawOption = clientTs && clientTs.options
                    ? (clientTs.options[initVal] || clientTs.options[String(initClientId)] || null)
                    : null;
                const initOption = Object.assign({}, rawOption || {});
                if (!initOption.name && assignClientSelect && assignClientSelect.options.length) {
                    initOption.name = (assignClientSelect.options[0].textContent || '').trim();
                }
                showAssignSelectedClientCard(initOption);
                loadAssignMattersForClient(initClientId, extractMatterRefFromTomSelectValue(initVal));
            } else {
                clearAssignSelectedClientCard();
            }
        }

        updateAssignConfirmButton();
        updateAssignStepProgress();
    }

    function loadAssignMattersForClient(selectedClientId, preferredMatterRef) {
        if (!mattersUrl || !selectedClientId) {
            clearAssignMatterSelection();
            if (assignMatterPlaceholder) {
                assignMatterPlaceholder.innerHTML = '<span>Select a client above to load matters.</span>';
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
                            ? '1 matter found — selected automatically. Change if needed.'
                            : matterCount + ' matters — tap the one this email belongs to.',
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
            const ts = assignClientSelect.tomselect;
            const resolved = extractClientIdFromTomSelectValue(assignClientSelect.value, ts);
            loadAssignMattersForClient(resolved);
            updateAssignConfirmButton();
            updateAssignStepProgress();
        });
    }

    if (assignEmailModal) {
        if (btnAssignToClient) {
            btnAssignToClient.addEventListener('click', function () {
                openAssignmentModal('assign');
            });
        }

        assignEmailModal.addEventListener('shown.bs.modal', function () {
            document.body.classList.add('assign-email-modal-open');
            initAssignEmailModalSelects();
            updateAssignSenderHint();

            const isUnlink = assignmentModalMode === 'unlink';
            const isSelectingClient = !isUnlink || unlinkDestinationMode === 'client';

            if (isSelectingClient && assignClientSelect) {
                const clientTs = assignClientSelect.tomselect;
                let activeVal = assignClientSelect ? assignClientSelect.value : '';

                if (!activeVal && clientId) {
                    activeVal = String(clientId);
                    if (clientTs) {
                        if (!clientTs.options[activeVal]) {
                            clientTs.addOption({
                                id: activeVal,
                                name: 'Client #' + activeVal,
                                text: 'Client #' + activeVal,
                                cid: parseInt(activeVal, 10)
                            });
                        }
                        clientTs.setValue(activeVal, true);
                    } else {
                        assignClientSelect.value = activeVal;
                    }
                }

                const activeClientId = extractClientIdFromTomSelectValue(activeVal, clientTs);
                if (activeClientId) {
                    const activeMatterRef = extractMatterRefFromTomSelectValue(activeVal);
                    loadAssignMattersForClient(activeClientId, activeMatterRef);
                } else if (clientTs) {
                    // Focus search immediately so typing works without an extra click.
                    setTimeout(function () {
                        clientTs.focus();
                        clientTs.open();
                    }, 30);
                }
            }

            configureAssignmentModal(assignmentModalMode, unlinkDestinationMode);
            updateAssignStepProgress();
        });

        assignEmailModal.addEventListener('hidden.bs.modal', function () {
            document.body.classList.remove('assign-email-modal-open');
            destroyAssignEmailModalSelects();
            clearAssignMatterSelection();
            if (assignMatterPlaceholder) {
                assignMatterPlaceholder.innerHTML = '<span>Select a client above to load matters.</span>';
            }
            if (assignEmailConfirmBtn) {
                assignEmailConfirmBtn.disabled = true;
            }
            if (assignSenderHint) {
                assignSenderHint.hidden = true;
            }
            if (assignSelectedClient) {
                assignSelectedClient.hidden = true;
            }
            clearAssignSelectedClientCard();
            configureAssignmentModal('assign', 'unassigned');
        });
    }

    unlinkDestinationButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            const destination = button.dataset.unlinkDestination === 'client' ? 'client' : 'unassigned';
            configureAssignmentModal('unlink', destination);

            if (destination === 'client' && assignClientSelect) {
                updateAssignSenderHint();
                const clientTs = assignClientSelect.tomselect;
                let activeVal = assignClientSelect ? assignClientSelect.value : '';
                if (!activeVal && clientId) {
                    activeVal = String(clientId);
                    if (clientTs) {
                        if (!clientTs.options[activeVal]) {
                            clientTs.addOption({
                                id: activeVal,
                                name: 'Client #' + activeVal,
                                text: 'Client #' + activeVal,
                                cid: parseInt(activeVal, 10)
                            });
                        }
                        clientTs.setValue(activeVal, true);
                    } else {
                        assignClientSelect.value = activeVal;
                    }
                }
                const activeClientId = extractClientIdFromTomSelectValue(activeVal, clientTs);
                if (activeClientId) {
                    const activeMatterRef = extractMatterRefFromTomSelectValue(activeVal);
                    loadAssignMattersForClient(activeClientId, activeMatterRef);
                } else {
                    clearAssignMatterSelection();
                    if (clientTs) {
                        clientTs.clear(true);
                        setTimeout(function () {
                            clientTs.focus();
                            clientTs.open();
                        }, 30);
                    } else {
                        assignClientSelect.value = '';
                        assignClientSelect.focus();
                    }
                }
            }
        });
    });

    if (assignSearchSenderBtn) {
        assignSearchSenderBtn.addEventListener('click', function () {
            const sender = getSelectedEmailSenderAddress() || assignSearchSenderBtn.textContent.trim();
            if (!sender) {
                return;
            }
            clearAssignSelectedClientCard();
            if (assignClientPicker) {
                assignClientPicker.hidden = false;
            }
            runAssignClientSearch(sender);
        });
    }

    if (assignChangeClientBtn) {
        assignChangeClientBtn.addEventListener('click', function () {
            beginAssignClientReselect();
        });
    }

    if (assignClientPicker) {
        assignClientPicker.addEventListener('click', function (event) {
            if (event.target.closest('.clear-button')) {
                return;
            }
            const clientTs = assignClientSelect && assignClientSelect.tomselect
                ? assignClientSelect.tomselect
                : null;
            if (!clientTs) {
                return;
            }
            clientTs.focus();
            // Always allow opening results — clear stale selection first if user is reseaching.
            if (clientTs.items && clientTs.items.length && clientTs.control_input
                && String(clientTs.control_input.value || '').trim() !== '') {
                clearAssignClientSelectionForSearch();
            }
            clientTs.open();
        });
    }

    if (assignEmailConfirmBtn) {
        assignEmailConfirmBtn.addEventListener('click', async function () {
            if (isAssignSubmitting) {
                return;
            }
            const emailLogId = assignEmailLogIdInput ? assignEmailLogIdInput.value : '';
            const clientTs = assignClientSelect && assignClientSelect.tomselect ? assignClientSelect.tomselect : null;
            const selectedClientRaw = assignClientSelect ? assignClientSelect.value : '';
            const selectedClientId = extractClientIdFromTomSelectValue(selectedClientRaw, clientTs);
            const selectedMatter = assignMatterHiddenInput ? assignMatterHiddenInput.value : '';
            const isUnlinkFlow = assignmentModalMode === 'unlink';
            const moveToClient = isUnlinkFlow && unlinkDestinationMode === 'client';
            const needsClientSelection = !isUnlinkFlow || moveToClient;

            if (!emailLogId) {
                crmToast('Select an email first.', 'warning');
                return;
            }
            if (needsClientSelection && (!selectedClientId || !selectedMatter)) {
                crmToast('Please select both a client and a matter.', 'warning');
                if (!selectedClientId && assignClientSelect && assignClientSelect.tomselect) {
                    assignClientSelect.tomselect.focus();
                    assignClientSelect.tomselect.open();
                }
                return;
            }

            isAssignSubmitting = true;
            setAssignModalBusy(true, isUnlinkFlow
                ? (moveToClient ? 'Reassigning...' : 'Moving...')
                : 'Assigning...');
            try {
                const endpoint = isUnlinkFlow ? unlinkEmailUrl : assignEmailUrl;
                if (!endpoint) {
                    crmToast('Assign action is not available for your account.', 'error');
                    return;
                }
                const payload = {
                    email_log_id: parseInt(emailLogId, 10)
                };
                if (isUnlinkFlow) {
                    payload.action = moveToClient ? 'client' : 'unassigned';
                }
                if (needsClientSelection) {
                    payload.client_id = selectedClientId;
                    payload.client_matter_id = parseInt(selectedMatter, 10);
                }

                const assignController = typeof AbortController === 'function'
                    ? new AbortController()
                    : null;
                const assignTimeoutMs = 25000;
                const assignTimeoutId = assignController
                    ? setTimeout(function () {
                        assignController.abort();
                    }, assignTimeoutMs)
                    : null;
                const fetchOptions = {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': getCsrfToken(),
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify(payload)
                };
                if (assignController) {
                    fetchOptions.signal = assignController.signal;
                }
                let response;
                try {
                    response = await fetch(endpoint, fetchOptions);
                } finally {
                    if (assignTimeoutId) {
                        clearTimeout(assignTimeoutId);
                    }
                }
                const data = await response.json().catch(function () {
                    return {};
                });
                if (response.ok && data.success) {
                    if (typeof bootstrap !== 'undefined' && bootstrap.Modal && assignEmailModal) {
                        bootstrap.Modal.getOrCreateInstance(assignEmailModal).hide();
                    }
                    const successTitle = isUnlinkFlow
                        ? (moveToClient ? 'Reassigned' : 'Moved to Unassigned')
                        : 'Assigned';
                    crmToast(data.message || 'Email updated successfully.', 'success', successTitle);

                    if (!isUnlinkFlow && !unassignedOnly) {
                        switchToFolder('inbox');
                    } else {
                        // The message leaves the list it was opened from, so the
                        // reading pane would otherwise keep showing stale details.
                        resetReadingPane();
                        if (currentPage > 1 && emails.length <= 1) {
                            currentPage -= 1;
                        }
                    }

                    loadEmails();
                    refreshUnassignedNavCount();
                    return;
                }
                crmToast(data.message || 'Could not update email.', 'error');
            } catch (error) {
                const aborted = error && (error.name === 'AbortError' || /aborted/i.test(String(error.message || '')));
                crmToast(
                    aborted
                        ? 'Assign timed out. If the email still appears here, try again; otherwise refresh the list.'
                        : ('Could not update email: ' + (error.message || 'Unknown error')),
                    'error'
                );
            } finally {
                isAssignSubmitting = false;
                setAssignModalBusy(false);
                updateAssignConfirmButton();
            }
        });
    }

    if (btnUnlinkFromClient) {
        btnUnlinkFromClient.addEventListener('click', function () {
            if (!selectedEmailId || !selectedEmail) {
                crmToast('Select an assigned email to reassign.', 'warning');
                return;
            }
            openAssignmentModal('unlink');
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

    async function refreshUnassignedNavCount() {
        if (!unassignedCountUrl) {
            return;
        }

        try {
            const response = await fetch(unassignedCountUrl, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            });
            const data = await response.json().catch(function () {
                return {};
            });
            if (!response.ok || data.success === false) {
                return;
            }

            const link = document.getElementById('crmNavUnassignedMail');
            if (!link) {
                return;
            }

            const isAdmin = link.getAttribute('data-is-admin') === '1';
            const count = Math.max(0, Number(data.count) || 0);
            let badge = link.querySelector('.crm-nav-unassigned-badge');

            if (count > 0) {
                link.style.setProperty('display', '', '');
                if (!badge) {
                    badge = document.createElement('span');
                    badge.className = 'badge bg-danger crm-nav-unassigned-badge';
                    badge.style.position = 'absolute';
                    badge.style.top = '-5px';
                    badge.style.right = '-5px';
                    badge.style.fontSize = '10px';
                    badge.style.padding = '2px 5px';
                    badge.style.borderRadius = '10px';
                    link.appendChild(badge);
                }
                badge.textContent = String(count);
            } else {
                if (badge) {
                    badge.remove();
                }
                if (!isAdmin) {
                    link.style.setProperty('display', 'none', 'important');
                }
            }
        } catch (error) {
            console.error('Failed to refresh unassigned mail count', error);
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
                    refreshUnassignedNavCount();
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
                refreshUnassignedNavCount();
                const toastType = (data.total_failed || 0) > 0 ? 'error' : 'success';
                crmToast(buildInboxSyncResultMessage(data, rangeLabel), toastType);
            } catch (error) {
                setSyncUiBusy(false, originalHtml);
                crmToast('Sync failed: ' + (error.message || 'Unknown error'), 'error');
            }
        });
    }

    function showAssignBySubjectModal() {
        if (!assignBySubjectModal) {
            return;
        }
        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            bootstrap.Modal.getOrCreateInstance(assignBySubjectModal).show();
        }
    }

    function hideAssignBySubjectModal() {
        if (!assignBySubjectModal) {
            return;
        }
        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            bootstrap.Modal.getOrCreateInstance(assignBySubjectModal).hide();
        }
    }

    function renderAssignedEmailRows(items) {
        if (!items || !items.length) {
            return '';
        }
        return '<ul class="assign-subject-results">'
            + items.map(function (row) {
                const client = escapeHtml((row.client_name || row.client_ref || 'Client') + '')
                    + (row.client_ref ? ' <span class="assign-subject-ref">' + escapeHtml(row.client_ref) + '</span>' : '');
                const matter = escapeHtml((row.matter_no || '') + (row.matter_title ? ' · ' + row.matter_title : ''));
                return '<li>'
                    + '<div class="assign-subject-results__subject">' + escapeHtml(row.subject || '(No subject)') + '</div>'
                    + '<div class="assign-subject-results__meta">' + client
                    + (matter ? ' · ' + matter : '')
                    + '</div></li>';
            }).join('')
            + '</ul>';
    }

    function renderReadyPairRows(items) {
        if (!items || !items.length) {
            return '';
        }
        return '<section class="assign-subject-group assign-subject-group--ready" data-group-type="ready">'
            + '<div class="assign-subject-group__header">'
            + '<strong>Ready to assign</strong>'
            + '<div class="assign-subject-group__reason">Matter is already known (subject pair, only one matter, or only one active matter). Select the emails you want to assign.</div>'
            + '</div>'
            + '<label class="assign-subject-select-all">'
            + '<input type="checkbox" class="assign-subject-select-all__input" checked>'
            + '<span>Select all</span>'
            + '</label>'
            + '<ul class="assign-subject-results assign-subject-results--pending">'
            + items.map(function (row) {
                const matterId = row.client_matter_id || row.matter_id || '';
                const clientMeta = escapeHtml((row.client_name || row.client_ref || 'Client') + '')
                    + (row.client_ref ? ' <span class="assign-subject-ref">' + escapeHtml(row.client_ref) + '</span>' : '');
                const matterMeta = escapeHtml((row.matter_no || '') + (row.matter_title ? ' · ' + row.matter_title : ''));
                return '<li class="assign-subject-row">'
                    + '<label class="assign-subject-row__label">'
                    + '<input type="checkbox" class="assign-subject-email-check" checked'
                    + ' data-email-log-id="' + escapeHtml(String(row.email_log_id)) + '"'
                    + ' data-client-id="' + escapeHtml(String(row.client_id)) + '"'
                    + ' data-client-matter-id="' + escapeHtml(String(matterId)) + '">'
                    + '<span class="assign-subject-row__body">'
                    + '<span class="assign-subject-results__subject">' + escapeHtml(row.subject || '(No subject)') + '</span>'
                    + '<span class="assign-subject-results__meta">' + clientMeta
                    + (matterMeta ? ' · ' + matterMeta : '')
                    + '</span></span></label></li>';
            }).join('')
            + '</ul></section>';
    }

    function renderNeedsMatterGroups(groups) {
        if (!groups || !groups.length) {
            return '';
        }
        return groups.map(function (group, groupIndex) {
            const emails = group.emails || [];
            const matters = group.matters || [];
            const reason = group.matched_by === 'client_name'
                ? 'Matched by client name — choose a matter, then select emails to assign.'
                : 'Client ID found — choose a matter, then select emails to assign.';
            const options = matters.map(function (matter) {
                const label = (matter.matter_no || ('Matter #' + matter.id))
                    + (matter.matter_title ? ' — ' + matter.matter_title : '')
                    + (matter.matter_active ? '' : ' (inactive)');
                return '<option value="' + escapeHtml(String(matter.id)) + '">' + escapeHtml(label) + '</option>';
            }).join('');
            const emailList = emails.map(function (email) {
                return '<li class="assign-subject-row">'
                    + '<label class="assign-subject-row__label">'
                    + '<input type="checkbox" class="assign-subject-email-check" checked'
                    + ' data-email-log-id="' + escapeHtml(String(email.email_log_id)) + '">'
                    + '<span class="assign-subject-row__body">'
                    + '<span class="assign-subject-results__subject">' + escapeHtml(email.subject || '(No subject)') + '</span>'
                    + '<span class="assign-subject-results__meta">' + escapeHtml(email.from_mail || '') + '</span>'
                    + '</span></label></li>';
            }).join('');
            return '<section class="assign-subject-group" data-group-index="' + groupIndex + '" data-group-type="needs" data-client-id="' + escapeHtml(String(group.client_id)) + '">'
                + '<div class="assign-subject-group__header">'
                + '<strong>' + escapeHtml(group.client_name || group.client_ref || 'Client') + '</strong>'
                + (group.client_ref ? ' <span class="assign-subject-ref">' + escapeHtml(group.client_ref) + '</span>' : '')
                + '<div class="assign-subject-group__reason">' + escapeHtml(reason) + '</div>'
                + '</div>'
                + '<label class="assign-subject-group__matter-label">Matter</label>'
                + '<select class="list-filter-select assign-subject-group__matter" aria-label="Choose matter">'
                + '<option value="">Select matter</option>'
                + options
                + '</select>'
                + '<label class="assign-subject-select-all">'
                + '<input type="checkbox" class="assign-subject-select-all__input" checked>'
                + '<span>Select all</span>'
                + '</label>'
                + '<ul class="assign-subject-results assign-subject-results--pending">' + emailList + '</ul>'
                + '</section>';
        }).join('');
    }

    function bindAssignBySubjectSelectAll() {
        if (!assignBySubjectModalBody) {
            return;
        }
        assignBySubjectModalBody.querySelectorAll('.assign-subject-select-all__input').forEach(function (master) {
            master.addEventListener('change', function () {
                const group = master.closest('.assign-subject-group');
                if (!group) {
                    return;
                }
                group.querySelectorAll('.assign-subject-email-check').forEach(function (box) {
                    box.checked = master.checked;
                });
            });
        });
    }

    function collectMatterChoiceAssignments() {
        const assignments = [];
        if (!assignBySubjectModalBody) {
            return assignments;
        }

        assignBySubjectModalBody.querySelectorAll('.assign-subject-group--ready .assign-subject-email-check:checked').forEach(function (box) {
            const emailLogId = parseInt(box.getAttribute('data-email-log-id') || '0', 10);
            const clientId = parseInt(box.getAttribute('data-client-id') || '0', 10);
            const matterId = parseInt(box.getAttribute('data-client-matter-id') || '0', 10);
            if (emailLogId && clientId && matterId) {
                assignments.push({
                    email_log_id: emailLogId,
                    client_id: clientId,
                    client_matter_id: matterId
                });
            }
        });

        assignBySubjectModalBody.querySelectorAll('.assign-subject-group[data-group-type="needs"]').forEach(function (group) {
            const select = group.querySelector('.assign-subject-group__matter');
            const matterId = select ? parseInt(select.value, 10) : 0;
            const clientId = parseInt(group.getAttribute('data-client-id') || '0', 10);
            const checked = group.querySelectorAll('.assign-subject-email-check:checked');
            if (!matterId || !clientId || !checked.length) {
                return;
            }
            checked.forEach(function (box) {
                const emailLogId = parseInt(box.getAttribute('data-email-log-id') || '0', 10);
                if (emailLogId) {
                    assignments.push({
                        email_log_id: emailLogId,
                        client_id: clientId,
                        client_matter_id: matterId
                    });
                }
            });
        });
        return assignments;
    }

    if (btnAssignBySubject && assignBySubjectUrl && canSyncInbox) {
        btnAssignBySubject.addEventListener('click', async function () {
            const originalHtml = btnAssignBySubject.innerHTML;
            btnAssignBySubject.disabled = true;
            btnAssignBySubject.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i><span>Matching...</span>';
            try {
                const response = await fetch(assignBySubjectUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': getCsrfToken(),
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: '{}'
                });
                const data = await response.json();
                if (!response.ok || !data.success) {
                    throw new Error(data.message || 'Could not match emails by subject.');
                }

                if (assignBySubjectModalSubtitle) {
                    assignBySubjectModalSubtitle.textContent = data.message || '';
                }
                if (assignBySubjectModalBody) {
                    const ready = data.ready_pairs || [];
                    const needs = data.needs_matter || [];
                    let html = '';
                    if (!ready.length && !needs.length) {
                        html = '<p class="assign-subject-summary">No matching unassigned emails found.</p>';
                    } else {
                        html = '<p class="assign-subject-summary assign-subject-summary--alert">'
                            + 'Select emails to assign. For name/ID-only matches, choose a matter first.</p>';
                        html += renderReadyPairRows(ready);
                        html += renderNeedsMatterGroups(needs);
                    }
                    assignBySubjectModalBody.innerHTML = html;
                    bindAssignBySubjectSelectAll();
                    if (assignBySubjectConfirmBtn) {
                        assignBySubjectConfirmBtn.hidden = !(ready.length || needs.length);
                    }
                }
                showAssignBySubjectModal();
                if (typeof crmToast === 'function') {
                    crmToast(data.message, ((data.ready_pairs || []).length || (data.needs_matter || []).length) ? 'info' : 'info');
                }
            } catch (error) {
                crmToast(error.message || 'Could not match emails by subject.', 'error');
            } finally {
                btnAssignBySubject.disabled = false;
                btnAssignBySubject.innerHTML = originalHtml;
            }
        });
    }

    if (assignBySubjectConfirmBtn && assignBySubjectConfirmUrl) {
        assignBySubjectConfirmBtn.addEventListener('click', async function () {
            const needsGroups = assignBySubjectModalBody
                ? assignBySubjectModalBody.querySelectorAll('.assign-subject-group[data-group-type="needs"]')
                : [];
            let missingMatter = false;
            needsGroups.forEach(function (group) {
                const checked = group.querySelectorAll('.assign-subject-email-check:checked');
                const select = group.querySelector('.assign-subject-group__matter');
                if (checked.length && select && !select.value) {
                    missingMatter = true;
                }
            });
            if (missingMatter) {
                crmToast('Choose a matter for each client with selected emails.', 'warning');
                return;
            }

            const assignments = collectMatterChoiceAssignments();
            if (!assignments.length) {
                crmToast('Select at least one email to assign.', 'warning');
                return;
            }
            const originalHtml = assignBySubjectConfirmBtn.innerHTML;
            assignBySubjectConfirmBtn.disabled = true;
            assignBySubjectConfirmBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Assigning...';
            try {
                const response = await fetch(assignBySubjectConfirmUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': getCsrfToken(),
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ assignments: assignments })
                });
                const data = await response.json();
                if (!response.ok && !(data.assigned_count > 0)) {
                    throw new Error(data.message || 'Could not assign the selected emails.');
                }
                if (assignBySubjectModalBody) {
                    const assignedIds = {};
                    (data.assigned || []).forEach(function (row) {
                        assignedIds[String(row.email_log_id)] = true;
                    });
                    assignBySubjectModalBody.querySelectorAll('.assign-subject-email-check:checked').forEach(function (box) {
                        const id = box.getAttribute('data-email-log-id');
                        if (assignedIds[id]) {
                            const row = box.closest('.assign-subject-row');
                            if (row) {
                                row.remove();
                            }
                        }
                    });
                    assignBySubjectModalBody.querySelectorAll('.assign-subject-group').forEach(function (group) {
                        if (!group.querySelector('.assign-subject-email-check')) {
                            group.remove();
                        }
                    });
                    const remaining = assignBySubjectModalBody.querySelectorAll('.assign-subject-email-check').length;
                    assignBySubjectModalBody.insertAdjacentHTML(
                        'afterbegin',
                        '<p class="assign-subject-summary"><strong>'
                            + (data.assigned_count || 0)
                            + '</strong> email'
                            + ((data.assigned_count || 0) === 1 ? '' : 's')
                            + ' assigned.</p>'
                            + renderAssignedEmailRows(data.assigned || [])
                    );
                    if (!remaining && assignBySubjectConfirmBtn) {
                        assignBySubjectConfirmBtn.hidden = true;
                    }
                }
                if (assignBySubjectModalSubtitle) {
                    assignBySubjectModalSubtitle.textContent = data.message || 'Assigned.';
                }
                loadEmails();
                refreshUnassignedNavCount();
                crmToast(data.message || 'Emails assigned.', 'success');
            } catch (error) {
                crmToast(error.message || 'Could not assign the selected emails.', 'error');
            } finally {
                assignBySubjectConfirmBtn.disabled = false;
                assignBySubjectConfirmBtn.innerHTML = originalHtml;
            }
        });
    }
});
