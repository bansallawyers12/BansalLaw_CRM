<!-- Emails Interface -->
@php
    // Support both $client and $fetchedData variable names
    $clientData = $client ?? $fetchedData ?? null;
    
    // Get the matter ID from URL or most recent matter
    $matterId = null;
    if (isset($id1) && $id1 != "") {
        $clientMatter = \App\Models\ClientMatter::where('client_id', $clientData->id)
            ->where('client_unique_matter_no', $id1)
            ->first();
        $matterId = $clientMatter ? $clientMatter->id : null;
    } else {
        $clientMatter = \App\Models\ClientMatter::where('client_id', $clientData->id)
            ->where('matter_status', 1)
            ->orderBy('id', 'desc')
            ->first();
        $matterId = $clientMatter ? $clientMatter->id : null;
    }
@endphp
@php
    $canDeleteEmail = Auth::user() && in_array(
        (int) Auth::user()->role,
        config('crm.email_log_delete_role_ids', [1, 12, 16]) ?: [1, 12, 16],
        true
    );
@endphp
<div class="email-interface-container" data-client-id="{{ $clientData->id ?? '' }}" data-matter-id="{{ $matterId ?? '' }}" data-can-delete-email="{{ $canDeleteEmail ? '1' : '0' }}">
    <!-- Top Control Bar (Search & Filters) -->
    <div class="email-control-bar">
        <div class="control-section search-section">
            <label for="emailSearchInput">Search:</label>
            <input type="text" id="emailSearchInput" class="search-input" placeholder="Search emails...">
        </div>
        
        <div class="control-section filter-section">
            <label for="mailTypeFilter">Type:</label>
            <select id="mailTypeFilter" class="filter-select">
                <option value="inbox">Inbox</option>
                <option value="sent">Sent</option>
            </select>
            
            <label for="labelFilter">Label:</label>
            <select id="labelFilter" class="filter-select">
                <option value="">All Labels</option>
                <!-- Populated dynamically -->
            </select>
        </div>
    </div>

    <!-- Main Content Area -->
    <div class="email-main-content">
        <!-- Left Email List Pane with Upload Area -->
        <div class="email-list-pane">
            <!-- Drag & Drop Upload Section -->
            <div class="upload-section-header">
                <span class="upload-title">Upload Emails</span>
            </div>
            <div class="upload-section-container">
                <div id="upload-area" class="drag-drop-zone">
                    <div class="drag-drop-content">
                        <i class="fas fa-cloud-upload-alt drag-drop-icon"></i>
                        <div class="drag-drop-text">Drag & drop .msg files here</div>
                        <div class="drag-drop-subtext">or click to browse</div>
                        <div id="file-count" class="file-count-badge">0</div>
                    </div>
                    <input type="file" id="emailFileInput" class="file-input" accept=".msg" multiple style="display: none;">
                </div>
                <div id="upload-progress" class="upload-progress">
                    <span id="fileStatus">Ready to upload</span>
                </div>
            </div>
            
            <!-- Email List Header -->
            <div class="email-list-header">
                <span class="results-count" id="resultsCount">0 results</span>
                <div class="pagination-controls">
                    <button class="pagination-btn" id="prevBtn">Prev</button>
                    <span class="page-info" id="pageInfo">1/1</span>
                    <button class="pagination-btn" id="nextBtn">Next</button>
                </div>
            </div>
            
            <div class="email-list" id="emailList">
                <!-- Email items will be populated here by JavaScript -->
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <i class="fas fa-inbox"></i>
                    </div>
                    <div class="empty-state-text">
                        <h3>No emails found</h3>
                        <p>Upload .msg files above to get started.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Content Viewing Pane -->
        <div class="email-content-pane">
            <div class="email-content-placeholder" id="emailContentPlaceholder">
                <div class="placeholder-content">
                    <i class="fas fa-envelope-open"></i>
                    <h3>Select an email to view its contents</h3>
                </div>
            </div>
            
            <div class="email-content-view" id="emailContentView" style="display: none;">
                <!-- Email content will be loaded here -->
            </div>
        </div>
    </div>
</div>

<!-- Attachment Preview Modal -->
<div id="attachmentPreviewModal" class="preview-modal" style="display: none;">
    <div class="preview-modal-overlay" id="previewOverlay"></div>
    <div class="preview-modal-content">
        <div class="preview-modal-header">
            <h3 id="previewFileName">Preview</h3>
            <button class="preview-close" id="closePreviewBtn">&times;</button>
        </div>
        <div class="preview-modal-body">
            <iframe id="previewFrame" src=""></iframe>
        </div>
    </div>
</div>

<!-- Email Context Menu -->
<div id="emailContextMenu" class="email-context-menu" style="display: none;">
    <div class="context-menu-item" data-action="apply-label">
        <i class="fas fa-tag"></i>
        <span>Apply Label</span>
        <i class="fas fa-chevron-right context-menu-arrow"></i>
    </div>
    <div class="context-menu-item" data-action="reply">
        <i class="fas fa-reply"></i>
        <span>Reply</span>
    </div>
    <div class="context-menu-item" data-action="forward">
        <i class="fas fa-share"></i>
        <span>Forward</span>
    </div>
    @if($canDeleteEmail)
    <div class="context-menu-separator"></div>
    <div class="context-menu-item" data-action="delete">
        <i class="fas fa-trash"></i>
        <span>Delete</span>
    </div>
    @endif
</div>

<!-- Label Submenu -->
<div id="labelSubmenu" class="email-context-submenu" style="display: none;">
    <div class="submenu-header">
        <i class="fas fa-arrow-left submenu-back"></i>
        <span>Select Label</span>
    </div>
    <div class="submenu-content" id="labelSubmenuContent">
        <!-- Labels will be populated dynamically -->
    </div>
</div>

<!-- Context Menu Overlay (for closing menu on outside click) -->
<div id="contextMenuOverlay" class="context-menu-overlay" style="display: none;"></div>

<!-- Include necessary CSS and JavaScript -->
<link rel="stylesheet" href="{{ asset('css/emails.css') }}">
<script src="{{ asset('js/emails.js') }}"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log('Emails interface loaded');
    
    // Debug: Check if elements exist
    const fileInput = document.getElementById('emailFileInput');
    const uploadArea = document.getElementById('upload-area');
    const fileStatus = document.getElementById('fileStatus');
    
    console.log('File input found:', !!fileInput);
    console.log('Upload area found:', !!uploadArea);
    console.log('File status found:', !!fileStatus);
    
    // If email was just sent, switch to "Sent" tab (flag set before page reload)
    const switchToSent = localStorage.getItem('emailTabSwitchToSent');
    if (switchToSent === '1') {
        localStorage.removeItem('emailTabSwitchToSent');
        const mailTypeFilter = document.getElementById('mailTypeFilter');
        if (mailTypeFilter && typeof window.setEmailMailType === 'function') {
            window.setEmailMailType('sent');
            mailTypeFilter.value = 'sent';
        }
    }
    
    // Debug: Check if modules are available
    console.log('initializeUpload available:', typeof window.initializeUpload);
    console.log('initializeSearch available:', typeof window.initializeSearch);
    console.log('loadEmails available:', typeof window.loadEmails);
    
    // Initialize modules
    if (typeof window.initializeUpload === 'function') {
        console.log('Initializing upload module...');
        window.initializeUpload();
    } else {
        console.error('Upload module not available!');
    }
    
    if (typeof window.initializeSearch === 'function') {
        console.log('Initializing search module...');
        window.initializeSearch();
    } else {
        console.error('Search module not available!');
    }
    
    // Load emails on page load (will use the correct tab based on filter)
    if (typeof window.loadEmails === 'function') {
        console.log('Loading initial emails...');
        // Small delay to ensure filter is set correctly
        setTimeout(function() {
            window.loadEmails();
        }, switchToSent === '1' ? 100 : 0);
    } else {
        console.error('Load emails function not available!');
    }
});
</script> 