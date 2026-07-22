@php
    // Support both $client and $fetchedData variable names
    $clientData = $client ?? $fetchedData ?? null;
    
    // Get the matter ID from URL or most recent matter
    $matterId = null;
    if (isset($id1) && $id1 != "") {
        $clientMatter = \App\Models\ClientMatter::where('client_id', $clientData->id ?? 0)
            ->where('client_unique_matter_no', $id1)
            ->first();
        $matterId = $clientMatter ? $clientMatter->id : null;
    } elseif ($clientData) {
        $clientMatter = \App\Models\ClientMatter::where('client_id', $clientData->id)
            ->where('matter_status', 1)
            ->orderBy('id', 'desc')
            ->first();
        $matterId = $clientMatter ? $clientMatter->id : null;
    }

    $emailUploadPersonalFolders = [];
    if ($clientData && \Illuminate\Support\Facades\Schema::hasTable('personal_document_types')) {
        $emailUploadPersonalFolders = \App\Models\PersonalDocumentType::select('id', 'title')
            ->where('status', 1)
            ->where(function ($query) use ($clientData) {
                $query->whereNull('client_id')
                    ->orWhere('client_id', $clientData->id);
            })
            ->orderBy('id', 'ASC')
            ->get()
            ->map(fn ($row) => ['id' => (string) $row->id, 'title' => $row->title])
            ->values()
            ->all();
    }

    $emailUploadMatterFolders = [];
    if ($clientData && $matterId && \Illuminate\Support\Facades\Schema::hasTable('visa_document_types')) {
        $emailUploadMatterFolders = \App\Models\VisaDocumentType::select('id', 'title')
            ->where('status', 1)
            ->where(function ($query) use ($clientData, $matterId) {
                $query->where(function ($q) {
                    $q->whereNull('client_id')->whereNull('client_matter_id');
                })->orWhere(function ($q) use ($clientData) {
                    $q->where('client_id', $clientData->id)->whereNull('client_matter_id');
                })->orWhere(function ($q) use ($clientData, $matterId) {
                    $q->where('client_id', $clientData->id)->where('client_matter_id', $matterId);
                });
            })
            ->orderBy('id', 'ASC')
            ->get()
            ->map(fn ($row) => ['id' => (string) $row->id, 'title' => $row->title])
            ->values()
            ->all();
    }
@endphp
@php
    $crmEmailUploadAccept = implode(',', array_map(static fn ($ext) => '.' . ltrim((string) $ext, '.'), config('crm.email_upload_allowed_extensions', ['msg', 'eml'])));
    $crmEmailUploadLabel = implode(', ', array_map(static fn ($ext) => '.' . ltrim((string) $ext, '.'), config('crm.email_upload_allowed_extensions', ['msg', 'eml'])));
@endphp
@php
    $authStaff = auth()->guard('admin')->user();
    $canDeleteEmail = $authStaff instanceof \App\Models\Staff
        && $authStaff->canDeleteEmailWithAttachments();
    $canSyncInbox = $authStaff instanceof \App\Models\Staff
        && $authStaff->canSyncInboxEmails();
    $canViewSyncedInbox = $authStaff instanceof \App\Models\Staff
        && $authStaff->canViewSyncedInboxMail();
    $canSelectSyncMailbox = $canSyncInbox
        && $authStaff instanceof \App\Models\Staff
        && $authStaff->canViewAllSyncedInboxMail();
    $syncMailboxOptions = $canSelectSyncMailbox
        ? \App\Services\EmailSync\IncomingEmailSyncService::syncableMailboxAddresses()
        : [];
    $unassignedOnly = ! empty($unassignedOnly);
    $crmMailboxAddresses = \App\Models\Email::where('status', true)
        ->orderBy('email')
        ->pluck('email')
        ->values()
        ->all();
@endphp

<!-- Outlook CSS -->
<link rel="stylesheet" href="{{ asset('css/outlook_emails.css') }}?v={{ time() }}">

<div class="outlook-container" id="outlookContainer"
    data-base-url="{{ url('/') }}"
    data-app-timezone="{{ config('app.timezone', 'Australia/Melbourne') }}"
    data-client-id="{{ $clientData->id ?? '' }}"
    data-matter-id="{{ $matterId ?? '' }}"
    data-auth-email="{{ auth()->user()->email ?? '' }}"
    data-mailbox-addresses='@json($crmMailboxAddresses)'
    @if($canSyncInbox)
    data-assign-email-url="{{ url('/clients/synced-emails/assign') }}"
    data-sync-inbox-url="{{ url('/clients/synced-emails/sync-now') }}"
    data-sync-status-url="{{ url('/clients/synced-emails/sync-status') }}"
    @endif
    data-can-sync-inbox="{{ $canSyncInbox ? '1' : '0' }}"
    data-can-view-synced-inbox="{{ $canViewSyncedInbox ? '1' : '0' }}"
    data-can-select-sync-mailbox="{{ $canSelectSyncMailbox ? '1' : '0' }}"
    data-unassigned-only="{{ $unassignedOnly ? '1' : '0' }}"
    data-default-folder="{{ $unassignedOnly ? 'unassigned' : 'inbox' }}"
    data-matters-url="{{ route('clients.listAllMattersWRTSelClient') }}"
    data-staff-signature-url="{{ route('crm.staff.email-signature') }}"
    data-staff-id="{{ auth()->id() }}"
    data-can-delete-email="{{ $canDeleteEmail ? '1' : '0' }}"
    data-personal-folders='@json($emailUploadPersonalFolders)'
    data-matter-folders='@json($emailUploadMatterFolders)'>
    
    <!-- Drag & Drop Overlay -->
    @if(! $unassignedOnly)
    <div id="dragDropOverlay" class="drag-drop-overlay" style="display: none;">
        <div class="drag-drop-content">
            <i class="fa-solid fa-cloud-arrow-up" style="font-size: 48px; margin-bottom: 15px;"></i>
            <h3>Drop Outlook email files here ({{ $crmEmailUploadLabel }})</h3>
        </div>
    </div>
    @endif
    
    <!-- Email List Pane -->
    <div class="outlook-list-pane">
        <div class="list-toolbar{{ $unassignedOnly ? ' list-toolbar--sync-inbox' : '' }}">
            <div class="folder-tabs" role="tablist" aria-label="Mail folders">
                @if($unassignedOnly)
                <button type="button" class="folder-item active" data-folder="unassigned" role="tab" aria-selected="true">
                    <i class="fa-solid fa-user-clock"></i> Unassigned
                </button>
                <button type="button" class="folder-item" data-folder="assigned" role="tab" aria-selected="false">
                    <i class="fa-solid fa-user-check"></i> Assigned
                </button>
                @else
                <button type="button" class="folder-item active" data-folder="inbox" role="tab" aria-selected="true">
                    <i class="fa-solid fa-inbox"></i> Inbox
                </button>
                <button type="button" class="folder-item" data-folder="sent" role="tab" aria-selected="false">
                    <i class="fa-solid fa-paper-plane"></i> Sent Items
                </button>
                <button type="button" class="folder-item" data-folder="outbox" role="tab" aria-selected="false">
                    <i class="fa-solid fa-clock-rotate-left"></i> Email Log
                </button>
                @endif
            </div>
            <button type="button" class="action-btn action-btn--upload" id="btnUploadEmail" title="Upload Outlook email ({{ $crmEmailUploadLabel }})" hidden>
                <i class="fa-solid fa-upload"></i> Upload
            </button>
            <input type="file" id="outlookEmailFileInput" accept="{{ $crmEmailUploadAccept }}" multiple hidden>
        </div>

        @if($canSyncInbox && $unassignedOnly)
        <div class="sync-range-bar{{ $canSelectSyncMailbox ? ' sync-range-bar--admin' : '' }}">
            @if($canSelectSyncMailbox)
            <label class="sync-range-bar__label" for="syncMailboxFilter">Mailbox</label>
            <select id="syncMailboxFilter" class="list-filter-select sync-mailbox-select" aria-label="Select mailbox to sync" required>
                <option value="">Select mailbox</option>
                @foreach($syncMailboxOptions as $mailboxAddress)
                    <option value="{{ $mailboxAddress }}">{{ $mailboxAddress }}</option>
                @endforeach
            </select>
            @endif
            <label class="sync-range-bar__label" for="syncRangeFilter">Sync range</label>
            <select id="syncRangeFilter" class="list-filter-select sync-range-select" aria-label="Sync date range">
                @foreach(\App\Services\EmailSync\IncomingEmailSyncService::syncRangeOptions() as $rangeValue => $rangeLabel)
                    <option value="{{ $rangeValue }}" @selected($rangeValue === 'today')>{{ $rangeLabel }}</option>
                @endforeach
            </select>
            <button type="button" class="action-btn action-btn--upload" id="btnSyncInbox" title="{{ $canSelectSyncMailbox ? 'Fetch mail from Zoho for the selected mailbox and range' : 'Fetch mail from Zoho for the selected range' }}">
                <i class="fa-solid fa-rotate"></i> Sync
            </button>
        </div>
        @endif

        <div id="uploadStatus" class="upload-status" hidden></div>

        @if(! $unassignedOnly)
        <div id="inlineDropZone" class="inline-drop-zone">
            <i class="fa-solid fa-cloud-arrow-up"></i>
            <span>Drag & drop saved Outlook email files ({{ $crmEmailUploadLabel }}) here </span>
        </div>
        @endif

        <div class="list-header">
            <div class="list-header-row">
                <div class="search-box">
                    <i class="fa-solid fa-search search-box-icon" aria-hidden="true"></i>
                    <input type="text" id="searchInput" placeholder="Search emails...">
                </div>
            </div>
            <div class="list-header-filters">
                <select id="labelFilter" class="list-filter-select" aria-label="Filter by label">
                    <option value="">All Labels</option>
                    @if(isset($clientData) && isset($matterId))
                        @php
                            $labels = \App\Models\EmailLabel::where(function($query) {
                                    $query->where('user_id', \Illuminate\Support\Facades\Auth::id())
                                          ->orWhereNull('user_id');
                                })
                                ->where('is_active', true)
                                ->orderBy('type', 'desc')
                                ->orderBy('name')
                                ->get();
                        @endphp
                        @foreach($labels as $label)
                            <option value="{{ $label->id }}">{{ $label->name }}</option>
                        @endforeach
                    @endif
                </select>
                <select id="senderFilter" class="list-filter-select" aria-label="Filter by sender">
                    <option value="">All Senders</option>
                    @if(isset($clientData) && isset($matterId))
                        @php
                            // Fetch distinct senders for this client/matter (only received emails, mail_type = 1)
                            $senders = \App\Models\EmailLog::where('client_id', $clientData->id)
                                ->where('client_matter_id', $matterId)
                                ->where('mail_type', 1)
                                ->whereNotNull('from_mail')
                                ->where('from_mail', '!=', '')
                                ->distinct()
                                ->pluck('from_mail');
                        @endphp
                        @foreach($senders as $sender)
                            <option value="{{ $sender }}">{{ $sender }}</option>
                        @endforeach
                    @endif
                </select>
                <select id="sortOrder" class="list-filter-select" aria-label="Sort order">
                    <option value="desc" selected>Newest First</option>
                    <option value="asc">Oldest First</option>
                </select>
                <select id="sendStatusFilter" class="list-filter-select list-filter-outbox" aria-label="Filter by send status" hidden>
                    <option value="">All Status</option>
                    <option value="sent">Sent</option>
                    <option value="failed">Failed</option>
                </select>
                <input type="date" id="dateFromFilter" class="list-filter-date list-filter-outbox" aria-label="From date" title="From date" hidden>
                <input type="date" id="dateToFilter" class="list-filter-date list-filter-outbox" aria-label="To date" title="To date" hidden>
            </div>
        </div>
        
        <div class="email-list" id="emailList">
            <div style="padding:16px;text-align:center;color:#666;">Loading emails...</div>
        </div>

        <div class="pagination-bar">
            <span id="pageInfo">Loading...</span>
            <div class="pagination-controls">
                <button id="prevBtn" disabled><i class="fa-solid fa-chevron-left"></i></button>
                <button id="nextBtn" disabled><i class="fa-solid fa-chevron-right"></i></button>
            </div>
        </div>
    </div>

    <!-- Reading Pane -->
    <div class="outlook-reading-pane">
        <!-- Empty State -->
        <div class="empty-state" id="emptyState">
            <i class="fa-regular fa-envelope-open"></i>
            <p>Select an item to read</p>
        </div>

        <!-- Email Content (Hidden by default) -->
        <div class="reading-pane-content" id="readingPane">
            <div class="reading-header">
                <div class="action-bar">
                    <button class="action-btn" id="btnReply"><i class="fa-solid fa-reply"></i> Reply</button>
                    <button class="action-btn" id="btnReplyAll"><i class="fa-solid fa-reply-all"></i> Reply All</button>
                    <button class="action-btn" id="btnForward"><i class="fa-solid fa-share"></i> Forward</button>
                    <button type="button" class="action-btn action-btn--warning" id="btnResend" hidden>
                        <i class="fa-solid fa-rotate-right"></i> Resend
                    </button>
                    @if($canSyncInbox)
                    <button type="button" class="action-btn action-btn--primary" id="btnAssignToClient" hidden title="Assign this email to a client matter">
                        <i class="fa-solid fa-user-plus"></i> Assign to Client
                    </button>
                    @endif
                    @if($canDeleteEmail)
                    <button type="button" class="action-btn action-btn--danger" id="btnDeleteEmail" title="Delete email and attachments">
                        <i class="fa-solid fa-trash"></i> Delete
                    </button>
                    @endif
                </div>
                
                <h2 class="email-full-subject" id="readSubject">Loading...</h2>
                
                <div class="email-meta">
                    <div class="sender-avatar" id="readAvatar">?</div>
                    <div class="meta-details">
                        <div class="meta-sender" id="readSender">Loading...</div>
                        <div class="meta-recipients" id="readTo">Loading...</div>
                        <div class="meta-recipients meta-cc" id="readCc" hidden></div>
                        <div class="meta-recipients meta-bcc" id="readBcc" hidden></div>
                    </div>
                    <div class="meta-date" id="readDate"></div>
                </div>
                <div class="email-send-error" id="readSendError" hidden></div>
            </div>

            <div id="attachmentsContainer" class="email-attachments-container reading-attachments" hidden></div>
            
            <div class="reading-body">
                <iframe id="readBody"></iframe>
            </div>
        </div>
    </div>
</div>

<!-- Compose Modal -->
<div class="compose-modal" id="composeModal">
    <div class="compose-header">
        <div class="compose-header-main">
            <i class="fa-solid fa-envelope compose-header-icon" aria-hidden="true"></i>
            <span class="compose-title" id="composeTitle">New Message</span>
        </div>
        <button type="button" class="compose-close" id="closeModal" aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="compose-body">
        <div class="compose-field">
            <label for="composeFrom">From</label>
            <input type="email" id="composeFrom" autocomplete="off" placeholder="Sender email address">
        </div>
        <div class="compose-field">
            <label for="composeTo">To</label>
            <input type="text" id="composeTo" autocomplete="off">
        </div>
        <div class="compose-field">
            <label for="composeCc">Cc</label>
            <input type="text" id="composeCc" autocomplete="off" placeholder="Optional — separate multiple with commas">
        </div>
        <div class="compose-field">
            <label for="composeBcc">Bcc</label>
            <input type="text" id="composeBcc" autocomplete="off" placeholder="Optional — separate multiple with commas">
        </div>
        <div class="compose-field">
            <label for="composeSubject">Subject</label>
            <input type="text" id="composeSubject" autocomplete="off">
        </div>
        <div class="compose-message-stack">
            <div class="compose-format-bar" id="composeFormatBar" role="toolbar" aria-label="Formatting">
                <button type="button" class="compose-format-btn" data-cmd="bold" title="Bold"><i class="fa-solid fa-bold"></i></button>
                <button type="button" class="compose-format-btn" data-cmd="italic" title="Italic"><i class="fa-solid fa-italic"></i></button>
                <button type="button" class="compose-format-btn" data-cmd="underline" title="Underline"><i class="fa-solid fa-underline"></i></button>
                <span class="compose-format-sep" aria-hidden="true"></span>
                <button type="button" class="compose-format-btn" data-cmd="insertUnorderedList" title="Bullet list"><i class="fa-solid fa-list-ul"></i></button>
                <button type="button" class="compose-format-btn" data-cmd="insertOrderedList" title="Numbered list"><i class="fa-solid fa-list-ol"></i></button>
                <span class="compose-format-sep" aria-hidden="true"></span>
                <button type="button" class="compose-format-btn" data-cmd="removeFormat" title="Clear formatting"><i class="fa-solid fa-eraser"></i></button>
            </div>
            <div id="composeReplyInput" class="compose-reply-input" contenteditable="true" role="textbox" aria-multiline="true" data-placeholder="Type your message..."></div>
            <div id="composeQuoteWrap" class="compose-quote-wrap" hidden>
                <button type="button" class="compose-quote-toggle" id="composeQuoteToggle" aria-expanded="true">
                    <span class="compose-quote-toggle-dots" aria-hidden="true">•••</span>
                    <span class="compose-quote-toggle-label" id="composeQuoteToggleLabel">Hide quoted message</span>
                </button>
                <div id="composeQuotePanel" class="compose-quote-panel">
                    <iframe id="composeQuoteFrame" class="compose-quote-frame" title="Quoted message" tabindex="-1"></iframe>
                </div>
            </div>
            <div id="composeSignatureWrap" class="compose-signature-wrap" hidden>
                <iframe id="composeSignatureFrame" class="compose-signature-frame" title="Email signature" tabindex="-1"></iframe>
            </div>
        </div>
    </div>
    <div class="compose-footer">
        <button type="button" class="btn-send" id="btnSend"><i class="fa-solid fa-paper-plane" aria-hidden="true"></i> Send</button>
        <button type="button" class="btn-discard" id="btnDiscard">Discard</button>
    </div>
</div>

<!-- Email upload loading overlay -->
<div class="email-upload-loading-overlay outlook-modal-overlay" id="emailUploadLoadingOverlay" aria-hidden="true" aria-live="polite" aria-busy="false">
    <div class="email-upload-loading-card outlook-ui-modal outlook-ui-modal--xs" role="status">
        <div class="email-upload-loading-card__header">
            <div class="outlook-ui-modal__header-icon" aria-hidden="true">
                <i class="fa-solid fa-cloud-arrow-up"></i>
            </div>
        </div>
        <div class="email-upload-loading-card__body">
            <div class="email-upload-loading-icon" aria-hidden="true">
                <i class="fa-solid fa-envelope"></i>
                <span class="email-upload-loading-spinner"></span>
            </div>
            <h3 class="email-upload-loading-title" id="emailUploadLoadingTitle">Uploading email</h3>
            <p class="email-upload-loading-message" id="emailUploadLoadingMessage">Please wait while your email is being processed…</p>
            <p class="email-upload-loading-filename" id="emailUploadLoadingFilename"></p>
            <div class="email-upload-loading-progress" aria-hidden="true">
                <div class="email-upload-loading-progress-bar" id="emailUploadLoadingProgressBar"></div>
            </div>
            <p class="email-upload-loading-hint">Do not close or refresh this page</p>
        </div>
    </div>
</div>

<!-- Duplicate email confirmation -->
<div class="duplicate-email-modal-overlay outlook-modal-overlay" id="duplicateEmailModal" aria-hidden="true">
    <div class="duplicate-email-modal outlook-ui-modal outlook-ui-modal--sm" role="dialog" aria-labelledby="duplicateEmailModalTitle" aria-modal="true">
        <div class="outlook-ui-modal__header outlook-ui-modal__header--warn">
            <div class="outlook-ui-modal__header-main">
                <div class="outlook-ui-modal__header-icon" aria-hidden="true">
                    <i class="fa-solid fa-envelope-open-text"></i>
                </div>
                <div class="outlook-ui-modal__header-text">
                    <h3 class="outlook-ui-modal__title" id="duplicateEmailModalTitle">Duplicate Email</h3>
                    <p class="outlook-ui-modal__subtitle">This email already exists in the CRM.</p>
                </div>
            </div>
        </div>
        <div class="outlook-ui-modal__body">
            <p class="duplicate-email-modal__message">A matching message was found for this upload.</p>
            <div class="outlook-ui-modal__preview-card">
                <div>
                    <div class="outlook-ui-modal__preview-label">File</div>
                    <div class="outlook-ui-modal__preview-value" id="duplicateEmailFileName">—</div>
                </div>
            </div>
            <p class="outlook-ui-modal__hint">Do you want to upload it anyway?</p>
        </div>
        <div class="outlook-ui-modal__footer">
            <button type="button" class="outlook-ui-modal__btn outlook-ui-modal__btn--cancel" id="duplicateEmailReject">Reject</button>
            <button type="button" class="outlook-ui-modal__btn outlook-ui-modal__btn--confirm" id="duplicateEmailAccept">Upload anyway</button>
        </div>
    </div>
</div>

@if($canDeleteEmail)
@include('crm.partials.email_delete_confirm_modal')
@endif

<!-- Attachment storage modal -->
<div class="attachment-storage-modal-overlay outlook-modal-overlay" id="attachmentStorageModal" aria-hidden="true">
    <div class="attachment-storage-modal outlook-ui-modal outlook-ui-modal--lg" role="dialog" aria-labelledby="attachmentStorageModalTitle" aria-modal="true">
        <div class="outlook-ui-modal__header">
            <div class="outlook-ui-modal__header-main">
                <div class="outlook-ui-modal__header-icon" aria-hidden="true">
                    <i class="fa-solid fa-paperclip"></i>
                </div>
                <div class="outlook-ui-modal__header-text">
                    <h3 class="outlook-ui-modal__title" id="attachmentStorageModalTitle">Save Attachments to Documents</h3>
                    <p class="outlook-ui-modal__subtitle" id="attachmentStorageSubtitle">Choose where files are stored and rename them before saving.</p>
                </div>
            </div>
            <div class="outlook-ui-modal__header-actions">
                <span class="attachment-storage-modal__count" id="attachmentStorageCount" aria-live="polite"></span>
                <button type="button" class="outlook-ui-modal__close" id="attachmentStorageClose" aria-label="Close">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </div>
        </div>

        <div class="outlook-ui-modal__body outlook-ui-modal__body--scroll attachment-storage-modal__body">
        <div class="attachment-storage-mode" id="attachmentStorageMode" hidden>
            <span class="attachment-storage-mode__label">How do you want to save these files?</span>
            <div class="attachment-storage-mode__toggle" role="group" aria-label="Attachment save mode">
                <button type="button" class="attachment-mode-btn active" data-mode="bulk" id="attachmentModeBulk">
                    <i class="fa-solid fa-layer-group" aria-hidden="true"></i>
                    Same folder for all
                </button>
                <button type="button" class="attachment-mode-btn" data-mode="individual" id="attachmentModeIndividual">
                    <i class="fa-solid fa-sliders-h" aria-hidden="true"></i>
                    Different per file
                </button>
            </div>
        </div>

        <div class="attachment-storage-destination" id="attachmentStorageDestination">
            <div class="attachment-storage-destination__head">
                <span class="attachment-storage-destination__label" id="attachmentDestinationLabel">Save all files to</span>
                <span class="attachment-storage-destination__summary" id="attachmentDestinationSummary" aria-live="polite"></span>
            </div>
            <div class="attachment-storage-destination__controls">
                <div class="attachment-location-tabs attachment-location-tabs--destination" id="attachmentBulkLocationTabs" role="group" aria-label="Document location for all attachments">
                    <button type="button" class="attachment-loc-btn active" data-type="email">Email only</button>
                    <button type="button" class="attachment-loc-btn" data-type="personal">Personal</button>
                    <button type="button" class="attachment-loc-btn" data-type="matter">Matter</button>
                </div>
                <div class="attachment-storage-destination__folder" id="attachmentBulkFolderWrap" hidden>
                    <label for="attachmentBulkFolder">Folder</label>
                    <select id="attachmentBulkFolder" class="attachment-storage-select attachment-storage-select--folder" aria-label="Folder for all attachments">
                        <option value="">Select folder</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="attachment-storage-table-wrap" id="attachmentStorageTableWrap">
            <table class="attachment-storage-table" id="attachmentStorageTable">
                <thead id="attachmentStorageTableHead">
                    <tr>
                        <th scope="col" class="attachment-storage-table__col-file">File</th>
                        <th scope="col" class="attachment-storage-table__col-size">Size</th>
                        <th scope="col" class="attachment-storage-table__col-name">Save as</th>
                    </tr>
                </thead>
                <tbody id="attachmentStorageModalBody"></tbody>
            </table>
        </div>
        </div>
        <div class="outlook-ui-modal__footer attachment-storage-modal__actions">
            <button type="button" class="outlook-ui-modal__btn outlook-ui-modal__btn--cancel attachment-storage-modal__btn attachment-storage-modal__btn--cancel" id="attachmentStorageCancel">Cancel upload</button>
            <button type="button" class="outlook-ui-modal__btn outlook-ui-modal__btn--confirm attachment-storage-modal__btn attachment-storage-modal__btn--confirm" id="attachmentStorageConfirm">
                <i class="fa-solid fa-upload" aria-hidden="true"></i> Continue upload
            </button>
        </div>
    </div>
</div>

@if($canSyncInbox)
<div class="modal fade assign-email-modal outlook-ui-modal-wrapper" id="assignSyncedEmailModal" tabindex="-1" role="dialog" aria-labelledby="assignSyncedEmailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered assign-email-modal__dialog" role="document">
        <div class="modal-content assign-email-modal__content outlook-ui-modal">
            <div class="modal-header assign-email-modal__header outlook-ui-modal__header">
                <div class="assign-email-modal__header-main outlook-ui-modal__header-main">
                    <div class="assign-email-modal__icon outlook-ui-modal__header-icon" aria-hidden="true">
                        <i class="fa-solid fa-user-plus"></i>
                    </div>
                    <div class="assign-email-modal__titles outlook-ui-modal__header-text">
                        <h5 class="modal-title outlook-ui-modal__title" id="assignSyncedEmailModalLabel">Assign Email to Client</h5>
                        <p class="assign-email-modal__subtitle outlook-ui-modal__subtitle">Link this synced email to a client and matter.</p>
                    </div>
                </div>
                <button type="button" class="assign-email-modal__close outlook-ui-modal__close" data-bs-dismiss="modal" aria-label="Close">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </div>
            <div class="modal-body assign-email-modal__body outlook-ui-modal__body">
                <input type="hidden" id="assignEmailLogId" value="">

                <div class="assign-email-preview" id="assignEmailPreview">
                    <div class="assign-email-preview__icon" aria-hidden="true">
                        <i class="fa-solid fa-envelope"></i>
                    </div>
                    <div class="assign-email-preview__content">
                        <div class="assign-email-preview__label">Selected email</div>
                        <div class="assign-email-preview__subject" id="assignEmailPreviewSubject">—</div>
                        <div class="assign-email-preview__meta" id="assignEmailPreviewMeta"></div>
                    </div>
                </div>

                <div class="assign-email-fields">
                    <div class="assign-email-field assign-email-field--client">
                        <label class="assign-email-field__label" for="assignClientId">
                            <span class="assign-email-field__step">1</span>
                            <span class="assign-email-field__label-text">
                                <i class="fa-solid fa-user" aria-hidden="true"></i>
                                Client
                            </span>
                        </label>
                        <div class="assign-email-field__control">
                            <select id="assignClientId" class="form-control crm-ts-plain assign-email-field__select">
                                <option value="">Search and select client...</option>
                                @if(!empty($clientData))
                                    <option value="{{ $clientData->id }}" selected>
                                        {{ $clientData->first_name }} {{ $clientData->last_name }} ({{ $clientData->client_id }})
                                    </option>
                                @endif
                            </select>
                        </div>
                        <p class="assign-email-field__hint">Search by name, email, or client reference.</p>
                        <div class="assign-email-selected-client" id="assignSelectedClientChip" hidden>
                            <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                            <span id="assignSelectedClientLabel"></span>
                        </div>
                    </div>

                    <div class="assign-email-field assign-email-field--matter" id="assignMatterField">
                        <label class="assign-email-field__label" for="assignClientMatterId">
                            <span class="assign-email-field__step">2</span>
                            <span class="assign-email-field__label-text">
                                <i class="fa-solid fa-briefcase" aria-hidden="true"></i>
                                Matter
                            </span>
                        </label>
                        <div class="assign-email-field__control">
                            <select id="assignClientMatterId" class="form-control crm-ts-plain assign-email-field__select" disabled>
                                <option value="">Select matter</option>
                            </select>
                        </div>
                        <p class="assign-email-field__hint" id="assignMatterHint">
                            <i class="fa-solid fa-arrow-up assign-email-field__hint-icon" aria-hidden="true"></i>
                            Choose a client first to load their matters.
                        </p>
                    </div>
                </div>

                <div id="assignEmailStatus" class="assign-email-status" hidden role="alert"></div>
            </div>
            <div class="modal-footer assign-email-modal__footer outlook-ui-modal__footer">
                <button type="button" class="btn outlook-ui-modal__btn outlook-ui-modal__btn--cancel assign-email-modal__btn assign-email-modal__btn--cancel" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn outlook-ui-modal__btn outlook-ui-modal__btn--confirm assign-email-modal__btn assign-email-modal__btn--confirm" id="assignEmailConfirmBtn" disabled>
                    <i class="fa-solid fa-link" aria-hidden="true"></i>
                    Assign Email
                </button>
            </div>
        </div>
    </div>
</div>
@endif

@include('partials.staff-signature-script')
<script>
window.__CRM_EMAIL_ALLOWED_EXTENSIONS__ = @json(config('crm.email_upload_allowed_extensions', ['msg', 'eml']));
</script>
<script src="{{ asset('js/email-upload-filename.js') }}?v={{ file_exists(public_path('js/email-upload-filename.js')) ? filemtime(public_path('js/email-upload-filename.js')) : 1 }}"></script>
@if($canDeleteEmail)
<link rel="stylesheet" href="{{ asset('css/email-delete-confirm.css') }}?v={{ file_exists(public_path('css/email-delete-confirm.css')) ? filemtime(public_path('css/email-delete-confirm.css')) : 1 }}">
<script src="{{ asset('js/email-delete-confirm.js') }}?v={{ file_exists(public_path('js/email-delete-confirm.js')) ? filemtime(public_path('js/email-delete-confirm.js')) : 1 }}"></script>
@endif
<script src="{{ asset('js/outlook_emails.js') }}?v={{ time() }}"></script>
