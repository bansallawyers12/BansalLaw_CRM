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

<!-- Outlook CSS -->
<link rel="stylesheet" href="{{ asset('css/outlook_emails.css') }}?v={{ time() }}">

<div class="outlook-container" id="outlookContainer"
    data-base-url="{{ url('/') }}"
    data-app-timezone="{{ config('app.timezone', 'Australia/Melbourne') }}"
    data-client-id="{{ $clientData->id ?? '' }}"
    data-matter-id="{{ $matterId ?? '' }}"
    data-auth-email="{{ auth()->user()->email ?? '' }}"
    data-staff-signature-url="{{ route('crm.staff.email-signature') }}"
    data-staff-id="{{ auth()->id() }}"
    data-personal-folders='@json($emailUploadPersonalFolders)'
    data-matter-folders='@json($emailUploadMatterFolders)'>
    
    <!-- Drag & Drop Overlay -->
    <div id="dragDropOverlay" class="drag-drop-overlay" style="display: none;">
        <div class="drag-drop-content">
            <i class="fas fa-cloud-upload-alt" style="font-size: 48px; margin-bottom: 15px;"></i>
            <h3>Drop .msg files here to upload</h3>
        </div>
    </div>
    
    <!-- Email List Pane -->
    <div class="outlook-list-pane">
        <div class="list-toolbar">
            <div class="folder-tabs" role="tablist" aria-label="Mail folders">
                <button type="button" class="folder-item active" data-folder="inbox" role="tab" aria-selected="true">
                    <i class="fas fa-inbox"></i> Inbox
                </button>
                <button type="button" class="folder-item" data-folder="sent" role="tab" aria-selected="false">
                    <i class="fas fa-paper-plane"></i> Sent Items
                </button>
                <button type="button" class="folder-item" data-folder="drafts" role="tab" aria-selected="false">
                    <i class="fas fa-file-alt"></i> Drafts
                </button>
            </div>
            <button type="button" class="action-btn action-btn--upload" id="btnUploadEmail" title="Upload .msg file" hidden>
                <i class="fas fa-upload"></i> Upload
            </button>
            <input type="file" id="outlookEmailFileInput" accept=".msg" multiple hidden>
        </div>
        <div id="uploadStatus" class="upload-status" hidden></div>

        <div id="inlineDropZone" class="inline-drop-zone">
            <i class="fas fa-cloud-upload-alt"></i>
            <span>Drag & drop .msg files here or <b>browse</b> to upload</span>
        </div>

        <div class="list-header">
            <div class="list-header-row">
                <div class="search-box">
                    <i class="fas fa-search search-box-icon" aria-hidden="true"></i>
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
            </div>
        </div>
        
        <div class="email-list" id="emailList">
            <div style="padding:16px;text-align:center;color:#666;">Loading emails...</div>
        </div>

        <div class="pagination-bar">
            <span id="pageInfo">Loading...</span>
            <div class="pagination-controls">
                <button id="prevBtn" disabled><i class="fas fa-chevron-left"></i></button>
                <button id="nextBtn" disabled><i class="fas fa-chevron-right"></i></button>
            </div>
        </div>
    </div>

    <!-- Reading Pane -->
    <div class="outlook-reading-pane">
        <!-- Empty State -->
        <div class="empty-state" id="emptyState">
            <i class="far fa-envelope-open"></i>
            <p>Select an item to read</p>
        </div>

        <!-- Email Content (Hidden by default) -->
        <div class="reading-pane-content" id="readingPane">
            <div class="reading-header">
                <div class="action-bar">
                    <button class="action-btn" id="btnReply"><i class="fas fa-reply"></i> Reply</button>
                    <button class="action-btn" id="btnReplyAll"><i class="fas fa-reply-all"></i> Reply All</button>
                    <button class="action-btn" id="btnForward"><i class="fas fa-share"></i> Forward</button>
                </div>
                
                <h2 class="email-full-subject" id="readSubject">Loading...</h2>
                
                <div class="email-meta">
                    <div class="sender-avatar" id="readAvatar">?</div>
                    <div class="meta-details">
                        <div class="meta-sender" id="readSender">Loading...</div>
                        <div class="meta-recipients" id="readTo">Loading...</div>
                        <div class="meta-recipients meta-cc" id="readCc" hidden></div>
                    </div>
                    <div class="meta-date" id="readDate"></div>
                </div>
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
            <i class="fas fa-envelope compose-header-icon" aria-hidden="true"></i>
            <span class="compose-title" id="composeTitle">New Message</span>
        </div>
        <button type="button" class="compose-close" id="closeModal" aria-label="Close"><i class="fas fa-times"></i></button>
    </div>
    <div class="compose-body">
        <div class="compose-field">
            <label for="composeTo">To</label>
            <input type="text" id="composeTo" autocomplete="off">
        </div>
        <div class="compose-field">
            <label for="composeSubject">Subject</label>
            <input type="text" id="composeSubject" autocomplete="off">
        </div>
        <div class="compose-message-stack">
            <div class="compose-format-bar" id="composeFormatBar" role="toolbar" aria-label="Formatting">
                <button type="button" class="compose-format-btn" data-cmd="bold" title="Bold"><i class="fas fa-bold"></i></button>
                <button type="button" class="compose-format-btn" data-cmd="italic" title="Italic"><i class="fas fa-italic"></i></button>
                <button type="button" class="compose-format-btn" data-cmd="underline" title="Underline"><i class="fas fa-underline"></i></button>
                <span class="compose-format-sep" aria-hidden="true"></span>
                <button type="button" class="compose-format-btn" data-cmd="insertUnorderedList" title="Bullet list"><i class="fas fa-list-ul"></i></button>
                <button type="button" class="compose-format-btn" data-cmd="insertOrderedList" title="Numbered list"><i class="fas fa-list-ol"></i></button>
                <span class="compose-format-sep" aria-hidden="true"></span>
                <button type="button" class="compose-format-btn" data-cmd="removeFormat" title="Clear formatting"><i class="fas fa-eraser"></i></button>
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
        <button type="button" class="btn-send" id="btnSend"><i class="fas fa-paper-plane" aria-hidden="true"></i> Send</button>
        <button type="button" class="btn-discard" id="btnDiscard">Discard</button>
    </div>
</div>

<!-- Email upload loading overlay -->
<div class="email-upload-loading-overlay" id="emailUploadLoadingOverlay" aria-hidden="true" aria-live="polite" aria-busy="false">
    <div class="email-upload-loading-card" role="status">
        <div class="email-upload-loading-icon" aria-hidden="true">
            <i class="fas fa-envelope"></i>
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

<!-- Duplicate email confirmation -->
<div class="duplicate-email-modal-overlay" id="duplicateEmailModal" aria-hidden="true">
    <div class="duplicate-email-modal" role="dialog" aria-labelledby="duplicateEmailModalTitle" aria-modal="true">
        <div class="duplicate-email-modal__icon" aria-hidden="true">
            <i class="fas fa-envelope-open-text"></i>
        </div>
        <h3 class="duplicate-email-modal__title" id="duplicateEmailModalTitle">Duplicate Email</h3>
        <p class="duplicate-email-modal__message">This email already exists.</p>
        <p class="duplicate-email-modal__filename" id="duplicateEmailFileName"></p>
        <p class="duplicate-email-modal__question">Do you want to upload it anyway?</p>
        <div class="duplicate-email-modal__actions">
            <button type="button" class="duplicate-email-modal__btn duplicate-email-modal__btn--reject" id="duplicateEmailReject">Reject</button>
            <button type="button" class="duplicate-email-modal__btn duplicate-email-modal__btn--accept" id="duplicateEmailAccept">Accept</button>
        </div>
    </div>
</div>

<!-- Attachment storage modal -->
<div class="attachment-storage-modal-overlay" id="attachmentStorageModal" aria-hidden="true">
    <div class="attachment-storage-modal" role="dialog" aria-labelledby="attachmentStorageModalTitle" aria-modal="true">
        <div class="attachment-storage-modal__header">
            <div class="attachment-storage-modal__header-main">
                <div class="attachment-storage-modal__icon" aria-hidden="true">
                    <i class="fas fa-paperclip"></i>
                </div>
                <div>
                    <h3 id="attachmentStorageModalTitle">Save Attachments to Documents</h3>
                    <p class="attachment-storage-modal__subtitle" id="attachmentStorageSubtitle">Choose where files are stored and rename them before saving.</p>
                </div>
            </div>
            <span class="attachment-storage-modal__count" id="attachmentStorageCount" aria-live="polite"></span>
        </div>

        <div class="attachment-storage-mode" id="attachmentStorageMode" hidden>
            <span class="attachment-storage-mode__label">How do you want to save these files?</span>
            <div class="attachment-storage-mode__toggle" role="group" aria-label="Attachment save mode">
                <button type="button" class="attachment-mode-btn active" data-mode="bulk" id="attachmentModeBulk">
                    <i class="fas fa-layer-group" aria-hidden="true"></i>
                    Same folder for all
                </button>
                <button type="button" class="attachment-mode-btn" data-mode="individual" id="attachmentModeIndividual">
                    <i class="fas fa-sliders-h" aria-hidden="true"></i>
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
        <div class="attachment-storage-modal__actions">
            <button type="button" class="attachment-storage-modal__btn attachment-storage-modal__btn--cancel" id="attachmentStorageCancel">Cancel upload</button>
            <button type="button" class="attachment-storage-modal__btn attachment-storage-modal__btn--confirm" id="attachmentStorageConfirm">
                <i class="fas fa-upload" aria-hidden="true"></i> Continue upload
            </button>
        </div>
    </div>
</div>

@include('partials.staff-signature-script')
<script src="{{ asset('js/outlook_emails.js') }}?v={{ time() }}"></script>
