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
@endphp

<!-- Outlook CSS -->
<link rel="stylesheet" href="{{ asset('css/outlook_emails.css') }}?v={{ time() }}">

<div class="outlook-container" id="outlookContainer" data-base-url="{{ url('/') }}" data-client-id="{{ $clientData->id ?? '' }}" data-matter-id="{{ $matterId ?? '' }}">
    
    <!-- Drag & Drop Overlay -->
    <div id="dragDropOverlay" class="drag-drop-overlay" style="display: none;">
        <div class="drag-drop-content">
            <i class="fas fa-cloud-upload-alt" style="font-size: 48px; margin-bottom: 15px;"></i>
            <h3>Drop .msg files here to upload</h3>
        </div>
    </div>
    
    <!-- Sidebar Pane -->
    <div class="outlook-sidebar">
        <div class="sidebar-header" style="display: flex; justify-content: space-between; align-items: center;">
            <span>Mail</span>
            <button class="action-btn" id="btnUploadEmail" title="Upload .msg file" style="padding: 4px 8px;">
                <i class="fas fa-upload"></i>
            </button>
            <input type="file" id="outlookEmailFileInput" accept=".msg" multiple style="display: none;">
        </div>
        <ul class="folder-list">
            <li class="folder-item active" data-folder="inbox">
                <i class="fas fa-inbox"></i> Inbox
            </li>
            <li class="folder-item" data-folder="sent">
                <i class="fas fa-paper-plane"></i> Sent Items
            </li>
            <li class="folder-item" data-folder="drafts">
                <i class="fas fa-file-alt"></i> Drafts
            </li>
            <!--
            <li class="folder-item" data-folder="deleted">
                <i class="fas fa-trash"></i> Deleted Items
            </li>
            -->
        </ul>
        <div id="uploadStatus" style="padding: 10px; font-size: 12px; color: var(--outlook-blue); display: none;"></div>
    </div>

    <!-- Email List Pane -->
    <div class="outlook-list-pane">
        <div id="inlineDropZone" class="inline-drop-zone">
            <i class="fas fa-cloud-upload-alt"></i>
            <span>Drag & drop .msg files here or <b>browse</b> to upload</span>
        </div>
        <div class="list-header">
            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                <button id="toggleSidebar" class="action-btn" style="padding: 4px 8px; margin: 0;" title="Toggle Sidebar">
                    <i class="fas fa-bars" style="margin: 0;"></i>
                </button>
                <div class="search-box" style="flex-grow: 1;">
                    <input type="text" id="searchInput" placeholder="Search emails...">
                </div>
            </div>
            <div style="display: flex; gap: 10px;">
                <select id="labelFilter" style="flex-grow: 1; padding: 4px; border: 1px solid var(--outlook-border); border-radius: 4px; font-size: 12px;">
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
                <select id="senderFilter" style="flex-grow: 1; padding: 4px; border: 1px solid var(--outlook-border); border-radius: 4px; font-size: 12px;">
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
        <div style="display: none; flex-direction: column; height: 100%;" id="readingPane">
            <div class="reading-header">
                <!-- Action buttons currently disabled
                <div class="action-bar">
                    <button class="action-btn" id="btnReply"><i class="fas fa-reply"></i> Reply</button>
                    <button class="action-btn" id="btnReplyAll"><i class="fas fa-reply-all"></i> Reply All</button>
                    <button class="action-btn" id="btnForward"><i class="fas fa-share"></i> Forward</button>
                    <button class="action-btn"><i class="fas fa-trash"></i> Delete</button>
                </div>
                -->
                
                <h2 class="email-full-subject" id="readSubject">Loading...</h2>
                
                <div class="email-meta">
                    <div class="sender-avatar" id="readAvatar">?</div>
                    <div class="meta-details">
                        <div class="meta-sender" id="readSender">Loading...</div>
                        <div class="meta-recipients" id="readTo">Loading...</div>
                    </div>
                    <div class="meta-date" id="readDate"></div>
                </div>

                <div id="attachmentsContainer" style="margin-top: 15px; display: none; gap: 10px; flex-wrap: wrap;">
                    <!-- Attachments injected here via JS -->
                </div>
            </div>
            
            <div class="reading-body">
                <iframe id="readBody"></iframe>
            </div>
        </div>
    </div>
</div>

<!-- Compose Modal (Mock) -->
<div class="compose-modal" id="composeModal">
    <div class="compose-header">
        <span class="compose-title" id="composeTitle">New Message</span>
        <button class="compose-close" id="closeModal"><i class="fas fa-times"></i></button>
    </div>
    <div class="compose-body">
        <div class="compose-field">
            <label>To</label>
            <input type="text" id="composeTo">
        </div>
        <div class="compose-field">
            <label>Subject</label>
            <input type="text" id="composeSubject">
        </div>
        <textarea class="compose-editor" id="composeEditor" placeholder="Type your message here..."></textarea>
    </div>
    <div class="compose-footer">
        <button class="btn-send" id="btnSend">Send</button>
        <button class="btn-discard" id="btnDiscard">Discard</button>
    </div>
</div>

<script src="{{ asset('js/outlook_emails.js') }}?v={{ time() }}"></script>
