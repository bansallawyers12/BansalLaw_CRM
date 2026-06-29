// Outlook-style Email Interface Logic

document.addEventListener('DOMContentLoaded', function() {
    let currentPage = 1;
    let currentFolder = 'inbox'; // inbox, sent, drafts
    let emails = [];
    let selectedEmailId = null;

    // Elements
    const outlookContainer = document.getElementById('outlookContainer');
    const appTimezone = (outlookContainer && outlookContainer.dataset.appTimezone) || 'Australia/Melbourne';
    const folderItems = document.querySelectorAll('.folder-item');
    const emailListContainer = document.getElementById('emailList');
    const readingPane = document.getElementById('readingPane');
    const emptyState = document.getElementById('emptyState');
    const searchInput = document.getElementById('searchInput');
    const labelFilter = document.getElementById('labelFilter');
    const senderFilter = document.getElementById('senderFilter');
    const pageInfo = document.getElementById('pageInfo');
    const prevBtn = document.getElementById('prevBtn');
    const nextBtn = document.getElementById('nextBtn');
    
    // Compose Modal
    const composeModal = document.getElementById('composeModal');
    const composeTitle = document.getElementById('composeTitle');
    const toInput = document.getElementById('composeTo');
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

    let composeQuoteHtml = '';
    let composeSignatureHtml = '';

    // Initialize Data
    const baseUrl = outlookContainer ? outlookContainer.getAttribute('data-base-url') : '';
    const clientId = outlookContainer ? outlookContainer.getAttribute('data-client-id') : '';
    const matterId = outlookContainer ? outlookContainer.getAttribute('data-matter-id') : '';
    const authEmail = outlookContainer ? outlookContainer.getAttribute('data-auth-email') : '';
    const staffSignatureUrl = outlookContainer ? (outlookContainer.getAttribute('data-staff-signature-url') || '') : '';
    const personalFolders = outlookContainer
        ? JSON.parse(outlookContainer.getAttribute('data-personal-folders') || '[]')
        : [];
    const matterFolders = outlookContainer
        ? JSON.parse(outlookContainer.getAttribute('data-matter-folders') || '[]')
        : [];

    loadEmails();

    // Event Listeners
    folderItems.forEach(item => {
        item.addEventListener('click', (e) => {
            folderItems.forEach(f => {
                f.classList.remove('active');
                f.setAttribute('aria-selected', 'false');
            });
            const target = e.currentTarget;
            target.classList.add('active');
            target.setAttribute('aria-selected', 'true');
            currentFolder = target.dataset.folder;
            currentPage = 1;
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
        composeModal.classList.remove('active');
    });

    document.getElementById('btnDiscard').addEventListener('click', () => {
        composeModal.classList.remove('active');
    });

    // Send Mail
    document.getElementById('btnSend').addEventListener('click', async () => {
        const to = toInput.value.trim();
        const subject = subjectInput.value.trim();
        const message = getComposeMessageHtml();

        if (!to || !subject || !message) {
            alert('Please fill in To, Subject, and Message fields.');
            return;
        }

        const btnSend = document.getElementById('btnSend');
        const originalText = btnSend.textContent;
        btnSend.textContent = 'Sending...';
        btnSend.disabled = true;

        const formData = new FormData();
        if (clientId) formData.append('client_id', clientId);
        if (matterId) formData.append('compose_client_matter_id', matterId);
        formData.append('email_from', authEmail);
        formData.append('email_to', to);
        formData.append('subject', subject);
        formData.append('message', message);
        formData.append('type', 'client');
        formData.append('mail_type', 2);
        formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);

        try {
            const response = await fetch(`${baseUrl}/sendmail`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            });
            const result = await response.json();
            
            if (result.success || response.ok) {
                alert('Email sent successfully!');
                composeModal.classList.remove('active');
                // Refresh sent folder if we are currently in it
                if (currentFolder === 'sent') {
                    loadEmails();
                }
            } else {
                alert(result.message || 'Failed to send email.');
            }
        } catch (error) {
            console.error('Error sending email:', error);
            alert('An error occurred while sending the email.');
        } finally {
            btnSend.textContent = originalText;
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

    if (btnUploadEmail && fileInput) {
        btnUploadEmail.addEventListener('click', () => {
            if (isEmailUploading) return;
            fileInput.click();
        });

        const inlineDropZone = document.getElementById('inlineDropZone');
        if (inlineDropZone) {
            inlineDropZone.addEventListener('click', () => {
                if (isEmailUploading) return;
                fileInput.click();
            });
        }

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

        function showUploadSuccessToast(message) {
            if (typeof crmNotify !== 'undefined') {
                crmNotify.success({
                    title: 'Success',
                    message: message,
                    position: 'topRight',
                    transitionIn: 'fadeInDown',
                    transitionOut: 'fadeOutUp'
                });
            }
        }

        function showUploadFileSuccess(fileName) {
            if (typeof crmNotify !== 'undefined') {
                crmNotify.success({
                    title: 'Uploaded successfully',
                    message: fileName,
                    position: 'topRight',
                    timeout: 5000,
                    transitionIn: 'fadeInDown',
                    transitionOut: 'fadeOutUp'
                });
            }
        }

        function showUploadFileError(fileName, errorMessage) {
            const text = errorMessage || 'Upload failed. Please try again.';
            if (typeof crmNotify !== 'undefined') {
                crmNotify.error({
                    title: fileName,
                    message: text.replace(/\n/g, '<br>'),
                    position: 'topRight',
                    timeout: 8000,
                    transitionIn: 'fadeInDown',
                    transitionOut: 'fadeOutUp'
                });
            } else {
                window.alert(fileName + ': ' + text);
            }
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
            showUploadFileError(file.name, errorMessage);
            updateUploadStatusForFile(file.name, 'error', errorMessage);
        }

        function showUploadErrorAlert(message) {
            const text = message || 'Upload failed. Please try again.';
            if (typeof crmNotify !== 'undefined') {
                crmNotify.error({
                    title: 'Upload Failed',
                    message: text.replace(/\n/g, '<br>'),
                    position: 'topRight',
                    timeout: 10000,
                    transitionIn: 'fadeInDown',
                    transitionOut: 'fadeOutUp'
                });
            } else {
                window.alert(text);
            }
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
                return 'fa-file-archive';
            }
            return 'fa-file-alt';
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
                    <span class="attachment-storage-row__icon"><i class="fas ${icon}"></i></span>
                    <span class="attachment-storage-row__filename" title="${escapeHtml(att.filename)}">${escapeHtml(att.filename)}</span>
                </div>
            `;
        }

        function renderAttachmentRenameCell(att, index) {
            const stem = getAttachmentStem(att.filename);
            const ext = getAttachmentExtension(att.filename);
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
            formData.append('email_files[]', file);
            formData.append('client_id', clientId);
            formData.append('type', 'client');
            formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);

            const response = await fetch(baseUrl + '/preview-email-attachments', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            });

            const result = await response.json();
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
                            file_name: nameInput ? nameInput.value.trim() : getAttachmentStem(att.filename),
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

        function buildOutlookUploadFormData(file, forceUpload, attachmentStorage) {
            const formData = new FormData();
            formData.append('email_files[]', file);
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
            try {
                result = await response.json();
            } catch (parseError) {
                throw new Error('The server returned an invalid response. Please refresh the page and try again.');
            }

            if (!response.ok) {
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
                console.warn('Attachment preview failed, continuing upload:', previewError);
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
                message: result.message || ''
            };
        }

        const handleUploadFiles = async (files) => {
            if (isEmailUploading) return;
            if (files.length === 0) return;

            const msgFiles = [];
            for (let i = 0; i < files.length; i++) {
                if (files[i].name.toLowerCase().endsWith('.msg')) {
                    msgFiles.push(files[i]);
                }
            }

            if (msgFiles.length === 0) {
                showUploadErrorAlert('Please upload .msg files only.');
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
                        if (fileResult.errors && fileResult.errors.length) {
                            allErrors.push.apply(allErrors, fileResult.errors);
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

                const errorDetails = formatUploadErrorDetails(allErrors.filter(function(err) { return !err.rejected; }));

                if (totalUploaded > 0 && totalFailed === 0 && totalRejected === 0) {
                    updateEmailUploadLoading('Upload complete', 'Your email was uploaded successfully.', '', 100);
                    if (uploadStatus) {
                        uploadStatus.style.color = 'green';
                        uploadStatus.textContent = 'Upload complete!';
                    }
                    showUploadSuccessToast('Successfully uploaded ' + totalUploaded + ' email' + (totalUploaded > 1 ? 's' : '') + '.');
                    loadEmails();
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
                        showUploadErrorAlert(errorMsg);
                    }
                    loadEmails();
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
                        errorMsg += '\n\nError details:\n' + errorDetails;
                    }
                    showUploadErrorAlert(errorMsg);
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
                if (isEmailUploading) return;
                dragCounter++;
                dragDropOverlay.style.display = 'flex';
            });

            outlookContainer.addEventListener('dragleave', (e) => {
                e.preventDefault();
                dragCounter--;
                if (dragCounter === 0) {
                    dragDropOverlay.style.display = 'none';
                }
            });

            outlookContainer.addEventListener('dragover', (e) => {
                e.preventDefault(); // necessary to allow dropping
            });

            outlookContainer.addEventListener('drop', (e) => {
                e.preventDefault();
                dragCounter = 0;
                dragDropOverlay.style.display = 'none';

                if (isEmailUploading) return;
                
                if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
                    handleUploadFiles(e.dataTransfer.files);
                }
            });
        }
    }

    // Fetch from backend
    async function loadEmails() {
        try {
            const query = searchInput.value;
            const label = labelFilter ? labelFilter.value : '';
            const sender = senderFilter ? senderFilter.value : '';
            
            // Assuming we will create this new endpoint for fetching ALL emails across all clients
            const url = new URL(`${baseUrl}/clients/outlook/fetch-all`);
            url.searchParams.append('folder', currentFolder);
            url.searchParams.append('page', currentPage);
            url.searchParams.append('search', query);
            url.searchParams.append('label_id', label);
            url.searchParams.append('sender_filter', sender);
            
            if (clientId) url.searchParams.append('client_id', clientId);
            if (matterId) url.searchParams.append('client_matter_id', matterId);

            const response = await fetch(url, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            const data = await response.json();
            
            emails = data.emails || [];
            
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
            emailListContainer.innerHTML = '<div style="padding:16px;text-align:center;color:red;">Error loading emails</div>';
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
        return email.fetch_mail_sent_time_display || email.fetch_mail_sent_time || email.received_date || email.created_at || null;
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
            return 'fa-file-archive';
        }
        if (ext === 'msg') {
            return 'fa-envelope';
        }
        return 'fa-file-alt';
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

    function renderEmailAttachmentListSummary(email) {
        const items = collectEmailAttachmentItems(email);
        if (!items.length) {
            return '';
        }

        const lines = items.slice(0, 3).map(function(item) {
            return '<span class="email-item-attachment-line"><i class="fas fa-file-alt"></i> ' + escapeHtml(item.name) + '</span>';
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
                ? '<a href="' + item.previewUrl + '" target="_blank" rel="noopener" class="email-attachment-btn email-attachment-btn--preview" title="Preview ' + escapeHtml(item.name) + '"><i class="fas fa-eye"></i> Preview</a>'
                : '';

            return ''
                + '<div class="email-attachment-row">'
                + '  <div class="email-attachment-row__icon"><i class="fas ' + item.icon + '"></i></div>'
                + '  <div class="email-attachment-row__info">'
                + '    <div class="email-attachment-row__name" title="' + escapeHtml(item.name) + '">' + escapeHtml(item.name) + '</div>'
                + (sizeLabel ? '    <div class="email-attachment-row__meta">' + escapeHtml(sizeLabel) + '</div>' : '')
                + '  </div>'
                + '  <div class="email-attachment-row__actions">'
                + previewBtn
                + '    <a href="' + item.downloadUrl + '" target="_blank" rel="noopener" class="email-attachment-btn email-attachment-btn--download" title="Download ' + escapeHtml(item.name) + '"><i class="fas fa-download"></i> Download</a>'
                + '  </div>'
                + '</div>';
        }).join('');

        return ''
            + '<div class="email-attachments-panel">'
            + '  <div class="email-attachments-panel__header">'
            + '    <i class="fas fa-paperclip"></i>'
            + '    <span>Attachments (' + items.length + ')</span>'
            + '  </div>'
            + '  <div class="email-attachments-panel__list">' + rows + '</div>'
            + '</div>';
    }

    function renderEmailList() {
        emailListContainer.innerHTML = '';
        
        if (emails.length === 0) {
            emailListContainer.innerHTML = '<div style="padding:16px;text-align:center;color:#666;">No emails found.</div>';
            return;
        }

        emails.forEach(email => {
            const el = document.createElement('div');
            el.className = 'email-item' + (email.is_read ? '' : ' unread');
            if (selectedEmailId === email.id) {
                el.classList.add('active');
            }

            const sender = email.from_mail || 'Unknown';
            const subject = email.subject || '(No Subject)';
            const preview = normalizePreviewText(email.text_preview || '', 80);
            
            const hasAttachment = (email.attachments && email.attachments.length > 0) || email.msg_file_url || email.pdf_file_url;
            const attachmentIcon = hasAttachment ? '<i class="fas fa-paperclip email-list-clip" title="Has attachments"></i>' : '';
            const attachmentSummary = renderEmailAttachmentListSummary(email);

            let dateStr = formatEmailDate(getEmailDate(email));

            el.innerHTML = `
                <div class="email-item-header">
                    <div class="email-sender">${escapeHtml(sender)}${attachmentIcon}</div>
                    <div class="email-date">${dateStr}</div>
                </div>
                <div class="email-subject">${escapeHtml(subject)}</div>
                <div class="email-preview">${escapeHtml(preview)}</div>
                ${attachmentSummary}
            `;

            el.addEventListener('click', () => {
                document.querySelectorAll('.email-item').forEach(i => i.classList.remove('active'));
                el.classList.add('active');
                selectedEmailId = email.id;
                showEmail(email);
            });

            emailListContainer.appendChild(el);
        });
    }

    function showEmail(email) {
        emptyState.style.display = 'none';
        readingPane.classList.add('is-visible');

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
            if (bodyHtml && !bodyHtml.includes('<')) {
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

    async function fetchLoggedInStaffSignature() {
        if (typeof window.crmFetchStaffSignature === 'function') {
            return (await window.crmFetchStaffSignature()).trim();
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
            toInput.value = email.from_mail || '';
            subjectInput.value = formatReplySubject(email.subject);
        } else if (action === 'replyAll') {
            composeTitle.textContent = 'Reply All';
            const toParts = [email.from_mail || '', cleanRecipients(email.to_mail), cleanRecipients(email.cc)]
                .filter(function(part) { return part; });
            toInput.value = toParts.join(', ');
            subjectInput.value = formatReplySubject(email.subject);
        } else if (action === 'forward') {
            composeTitle.textContent = 'Forward';
            toInput.value = '';
            subjectInput.value = formatForwardSubject(email.subject);
        }

        setComposeQuote(buildQuoteHtml(email, action, emailHtml));

        try {
            const signatureHtml = await fetchLoggedInStaffSignature();
            setComposeSignature(signatureHtml);
        } catch (e) {
            setComposeSignature('');
        }

        focusComposeReply();
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
});
