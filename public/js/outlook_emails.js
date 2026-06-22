// Outlook-style Email Interface Logic

document.addEventListener('DOMContentLoaded', function() {
    let currentPage = 1;
    let currentFolder = 'inbox'; // inbox, sent, drafts
    let emails = [];
    let selectedEmailId = null;

    // Elements
    const outlookContainer = document.getElementById('outlookContainer');
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
    const composeEditor = document.getElementById('composeEditor');

    // Sidebar Toggle
    const toggleSidebarBtn = document.getElementById('toggleSidebar');
    const sidebar = document.querySelector('.outlook-sidebar');

    if (toggleSidebarBtn) {
        toggleSidebarBtn.addEventListener('click', () => {
            sidebar.classList.toggle('collapsed');
        });
    }

    // Initialize Data
    const baseUrl = outlookContainer ? outlookContainer.getAttribute('data-base-url') : '';
    const clientId = outlookContainer ? outlookContainer.getAttribute('data-client-id') : '';
    const matterId = outlookContainer ? outlookContainer.getAttribute('data-matter-id') : '';
    const authEmail = outlookContainer ? outlookContainer.getAttribute('data-auth-email') : '';

    loadEmails();

    // Event Listeners
    folderItems.forEach(item => {
        item.addEventListener('click', (e) => {
            folderItems.forEach(f => f.classList.remove('active'));
            const target = e.currentTarget;
            target.classList.add('active');
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
        let message = composeEditor.value.trim();
        if (typeof tinymce !== 'undefined' && tinymce.get('composeEditor')) {
            message = tinymce.get('composeEditor').getContent().trim();
        }

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

    if (btnUploadEmail && fileInput) {
        btnUploadEmail.addEventListener('click', () => {
            fileInput.click();
        });

        const inlineDropZone = document.getElementById('inlineDropZone');
        if (inlineDropZone) {
            inlineDropZone.addEventListener('click', () => {
                fileInput.click();
            });
        }

        const handleUploadFiles = async (files) => {
            if (files.length === 0) return;

            const formData = new FormData();
            let msgFilesCount = 0;
            for (let i = 0; i < files.length; i++) {
                if (files[i].name.toLowerCase().endsWith('.msg')) {
                    formData.append('email_files[]', files[i]);
                    msgFilesCount++;
                }
            }

            if (msgFilesCount === 0) {
                alert('Please upload .msg files only.');
                return;
            }

            formData.append('client_id', clientId);
            formData.append('type', 'client');
            if (currentFolder === 'sent') {
                formData.append('upload_sent_mail_client_matter_id', matterId);
            } else {
                formData.append('upload_inbox_mail_client_matter_id', matterId);
            }
            formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);

            const uploadUrl = currentFolder === 'sent' 
                ? `${baseUrl}/upload-sent-fetch-mail` 
                : `${baseUrl}/upload-fetch-mail`;

            uploadStatus.style.display = 'block';
            uploadStatus.style.color = 'var(--outlook-blue)';
            uploadStatus.textContent = `Uploading ${msgFilesCount} file(s)...`;

            try {
                const response = await fetch(uploadUrl, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                });
                const result = await response.json();
                
                if (result.status) {
                    uploadStatus.style.color = 'green';
                    uploadStatus.textContent = 'Upload complete!';
                    loadEmails(); // Refresh list
                } else {
                    uploadStatus.style.color = 'red';
                    uploadStatus.textContent = result.message || 'Upload failed.';
                }
            } catch (error) {
                uploadStatus.style.color = 'red';
                uploadStatus.textContent = 'Upload error. See console.';
                console.error(error);
            }

            setTimeout(() => { uploadStatus.style.display = 'none'; }, 5000);
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
            const preview = (email.text_preview || '').substring(0, 50);
            
            const hasAttachment = (email.attachments && email.attachments.length > 0) || email.msg_file_url || email.pdf_file_url;
            const attachmentIcon = hasAttachment ? '<i class="fas fa-paperclip" title="Has attachments" style="color: #666; margin-left: 5px;"></i>' : '';

            let dateStr = '';
            if (email.created_at) {
                const d = new Date(email.created_at);
                dateStr = d.toLocaleDateString() + ' ' + d.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
            }

            el.innerHTML = `
                <div class="email-date">${dateStr}</div>
                <div class="email-sender">${escapeHtml(sender)}${attachmentIcon}</div>
                <div class="email-subject">${escapeHtml(subject)}</div>
                <div class="email-preview">${escapeHtml(preview)}</div>
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
        readingPane.style.display = 'flex';

        document.getElementById('readSubject').textContent = email.subject || '(No Subject)';
        document.getElementById('readSender').textContent = email.from_mail || 'Unknown Sender';
        document.getElementById('readTo').textContent = 'To: ' + (email.to_mail || 'Unknown');
        
        let dateStr = '';
        if (email.created_at) {
            const d = new Date(email.created_at);
            dateStr = d.toLocaleDateString() + ' ' + d.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
        }
        document.getElementById('readDate').textContent = dateStr;

        const initial = (email.from_mail || '?').charAt(0).toUpperCase();
        document.getElementById('readAvatar').textContent = initial;

        // Render Attachments if any exist
        const attachmentsContainer = document.getElementById('attachmentsContainer');
        const hasAttachments = (email.attachments && email.attachments.length > 0) || email.msg_file_url || email.pdf_file_url;

        if (hasAttachments) {
            attachmentsContainer.style.display = 'flex';
            attachmentsContainer.innerHTML = ''; // clear
            
            if (email.msg_file_url) {
                const msgBadge = document.createElement('a');
                msgBadge.href = email.msg_file_url;
                msgBadge.target = '_blank';
                msgBadge.className = 'attachment-badge';
                msgBadge.style.cssText = 'padding: 4px 8px; background: #e1dfdd; border: 1px solid #c8c6c4; border-radius: 4px; font-size: 12px; display: inline-flex; align-items: center; gap: 5px; color: #0078d4; text-decoration: none; font-weight: 600;';
                msgBadge.innerHTML = `<i class="fas fa-download"></i> Download Original .msg`;
                msgBadge.download = '';
                attachmentsContainer.appendChild(msgBadge);
            }

            if (email.pdf_file_url) {
                const pdfBadge = document.createElement('a');
                pdfBadge.href = email.pdf_file_url;
                pdfBadge.target = '_blank';
                pdfBadge.className = 'attachment-badge';
                pdfBadge.style.cssText = 'padding: 4px 8px; background: #fdf8f6; border: 1px solid #f2cfc7; border-radius: 4px; font-size: 12px; display: inline-flex; align-items: center; gap: 5px; color: #d83b01; text-decoration: none; font-weight: 600;';
                pdfBadge.innerHTML = `<i class="fas fa-file-pdf"></i> Download Parsed PDF`;
                pdfBadge.download = '';
                attachmentsContainer.appendChild(pdfBadge);
            }

            if (email.attachments && email.attachments.length > 0) {
                email.attachments.forEach(att => {
                    const badge = document.createElement('a');
                    badge.href = att.file_path || '#';
                    badge.target = '_blank';
                    badge.className = 'attachment-badge';
                    badge.style.cssText = 'padding: 4px 8px; background: #f3f2f1; border: 1px solid #edebe9; border-radius: 4px; font-size: 12px; display: inline-flex; align-items: center; gap: 5px; color: #323130; text-decoration: none;';
                    
                    let iconClass = 'fa-paperclip';
                    if (att.file_name && att.file_name.toLowerCase().endsWith('.pdf')) iconClass = 'fa-file-pdf';
                    else if (att.file_name && (att.file_name.toLowerCase().endsWith('.jpg') || att.file_name.toLowerCase().endsWith('.png'))) iconClass = 'fa-file-image';

                    badge.innerHTML = `<i class="fas ${iconClass}"></i> ${escapeHtml(att.file_name)}`;
                    attachmentsContainer.appendChild(badge);
                });
            }
        } else {
            attachmentsContainer.style.display = 'none';
        }

        const iframe = document.getElementById('readBody');
        let contentStr = (email.message || email.html_content || email.text_content || '').trim();
        
        let pdfToPreview = null;
        if (!contentStr) {
            if (email.pdf_file_url) {
                pdfToPreview = email.pdf_file_url;
            } else if (email.attachments && email.attachments.length > 0) {
                const pdfAtt = email.attachments.find(a => a.file_name && a.file_name.toLowerCase().endsWith('.pdf'));
                if (pdfAtt) {
                    pdfToPreview = pdfAtt.file_path;
                }
            }
        }

        if (pdfToPreview) {
            iframe.onload = null;
            iframe.removeAttribute('srcdoc');
            iframe.src = pdfToPreview;
            iframe.style.height = '800px';
        } else {
            iframe.removeAttribute('src');
            const finalContent = contentStr || '<p>No content available.</p>';
            iframe.srcdoc = `
                <html>
                    <head>
                        <style>
                            body { font-family: 'Segoe UI', sans-serif; font-size: 14px; color: #333; line-height: 1.6; padding: 10px; margin: 0; }
                            a { color: #0078d4; text-decoration: none; }
                            a:hover { text-decoration: underline; }
                            img { max-width: 100%; height: auto; }
                        </style>
                    </head>
                    <body>${finalContent}</body>
                </html>
            `;

            iframe.onload = function() {
                try {
                    const body = iframe.contentWindow.document.body;
                    const html = iframe.contentWindow.document.documentElement;
                    const height = Math.max(body.scrollHeight, body.offsetHeight, html.clientHeight, html.scrollHeight, html.offsetHeight);
                    iframe.style.height = (height + 20) + 'px';
                } catch (e) {
                    console.error("Could not resize iframe", e);
                }
            };
        }
    }

    function openCompose(action) {
        if (!selectedEmailId) return;
        const email = emails.find(e => e.id === selectedEmailId);
        if (!email) return;

        composeModal.classList.add('active');

        let isHtml = false;
        let emailHtml = '';
        if (email.html_content) {
            isHtml = true;
            emailHtml = email.html_content;
        } else if (email.message && email.message.includes('<')) {
            isHtml = true;
            emailHtml = email.message;
        } else if (email.text_content) {
            emailHtml = escapeHtml(email.text_content).replace(/\n/g, '<br>');
        } else if (email.message) {
            emailHtml = escapeHtml(email.message).replace(/\n/g, '<br>');
        }

        let content = '';

        if (action === 'reply') {
            composeTitle.textContent = 'Reply';
            toInput.value = email.from_mail || '';
            subjectInput.value = 'Re: ' + (email.subject || '');
            content = `<br><br><blockquote style="margin:0px 0px 0px 0.8ex;border-left:1px solid rgb(204,204,204);padding-left:1ex"><b>From:</b> ${escapeHtml(email.from_mail)}<br><b>Sent:</b> ${escapeHtml(email.created_at)}<br><b>Subject:</b> ${escapeHtml(email.subject)}<br><br>${emailHtml}</blockquote>`;
        } else if (action === 'replyAll') {
            composeTitle.textContent = 'Reply All';
            const cc = email.cc ? `, ${email.cc}` : '';
            toInput.value = (email.from_mail || '') + cc;
            subjectInput.value = 'Re: ' + (email.subject || '');
            content = `<br><br><blockquote style="margin:0px 0px 0px 0.8ex;border-left:1px solid rgb(204,204,204);padding-left:1ex"><b>From:</b> ${escapeHtml(email.from_mail)}<br><b>Sent:</b> ${escapeHtml(email.created_at)}<br><b>Subject:</b> ${escapeHtml(email.subject)}<br><br>${emailHtml}</blockquote>`;
        } else if (action === 'forward') {
            composeTitle.textContent = 'Forward';
            toInput.value = '';
            subjectInput.value = 'Fwd: ' + (email.subject || '');
            content = `<br><br><div dir="ltr">---------- Forwarded message ---------<br>From: <strong>${escapeHtml(email.from_mail)}</strong><br>Date: ${escapeHtml(email.created_at)}<br>Subject: ${escapeHtml(email.subject)}<br></div><br><blockquote style="margin:0px 0px 0px 0.8ex;border-left:1px solid rgb(204,204,204);padding-left:1ex">${emailHtml}</blockquote>`;
        }

        if (typeof tinymce !== 'undefined') {
            let editor = tinymce.get('composeEditor');
            if (!editor) {
                const config = typeof tinymceFullConfig !== 'undefined' ? tinymceFullConfig : {
                    height: 300,
                    menubar: false,
                    plugins: ['advlist autolink lists link image charmap print preview anchor', 'searchreplace visualblocks code fullscreen', 'insertdatetime media table paste code help wordcount'],
                    toolbar: 'undo redo | formatselect | bold italic backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat | help'
                };
                tinymce.init({
                    ...config,
                    selector: '#composeEditor',
                    init_instance_callback: function (inst) {
                        inst.setContent(content);
                    }
                });
            } else {
                editor.setContent(content);
            }
        } else {
            composeEditor.value = content;
        }
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
