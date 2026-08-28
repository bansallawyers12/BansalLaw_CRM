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
    $canAssignBySubject = $authStaff instanceof \App\Models\Staff
        && $authStaff->canAssignEmailsBySubject();
    $canShowInboxSync = $canSyncInbox
        && $authStaff instanceof \App\Models\Staff
        && $authStaff->canViewAllSyncedInboxMail();
    $canViewSyncedInbox = $authStaff instanceof \App\Models\Staff
        && $authStaff->canViewSyncedInboxMail();
    $unassignedOnly = ! empty($unassignedOnly);
    $assignmentReviewOnly = ! empty($assignmentReviewOnly);
    $compactPagination = ! $unassignedOnly && ! empty($compactPagination);
    $canUnlinkSyncedEmail = $authStaff instanceof \App\Models\Staff
        && (
            $canSyncInbox
            || $assignmentReviewOnly
            || $unassignedOnly
            || (
                $clientData
                && \App\Support\StaffClientVisibility::canAccessClientOrLead((int) $clientData->id, $authStaff)
            )
        );
    $canSelectSyncMailbox = $canShowInboxSync;
    $syncMailboxOptions = $canSelectSyncMailbox
        ? \App\Services\EmailSync\IncomingEmailSyncService::syncableMailboxAddresses()
        : [];
    $staffSyncMailboxAddresses = ($authStaff instanceof \App\Models\Staff && $canSyncInbox && ! $canSelectSyncMailbox)
        ? \App\Services\EmailSync\IncomingEmailSyncService::allowedSyncMailboxAddressesForStaff($authStaff)
        : [];
    $unassignedSyncRangeOptions = ($authStaff instanceof \App\Models\Staff && $unassignedOnly)
        ? \App\Services\EmailSync\IncomingEmailSyncService::syncRangeOptionsForUnassignedTab($authStaff)
        : [];
    $defaultUnassignedSyncRange = 'today';
    $listMailboxFilterOptions = ($unassignedOnly && $canSelectSyncMailbox)
        ? \App\Services\EmailSync\IncomingEmailSyncService::syncableMailboxAddresses()
        : [];
    $crmMailboxAddresses = \App\Models\Email::where('status', true)
        ->orderBy('email')
        ->pluck('email')
        ->values()
        ->all();
@endphp

<!-- Outlook CSS -->
<link rel="stylesheet" href="{{ asset('css/outlook_emails.css') }}?v={{ time() }}">

<div class="outlook-container{{ $unassignedOnly ? ' outlook-container--unassigned' : '' }}{{ $compactPagination ? ' outlook-container--compact-pagination' : '' }}" id="outlookContainer"
    data-base-url="{{ url('/') }}"
    data-app-timezone="{{ config('app.timezone', 'Australia/Melbourne') }}"
    data-client-id="{{ $clientData->id ?? '' }}"
    data-matter-id="{{ $matterId ?? '' }}"
    data-auth-email="{{ auth()->user()->email ?? '' }}"
    data-mailbox-addresses='@json($crmMailboxAddresses)'
    data-staff-sync-mailboxes='@json($staffSyncMailboxAddresses)'
    @if($canSyncInbox)
    data-assign-email-url="{{ url('/clients/synced-emails/assign') }}"
    data-assign-by-subject-url="{{ url('/clients/synced-emails/assign-by-subject') }}"
    data-assign-by-subject-confirm-url="{{ url('/clients/synced-emails/assign-by-subject/confirm') }}"
    @endif
    @if($canShowInboxSync)
    data-sync-inbox-url="{{ url('/clients/synced-emails/sync-now') }}"
    data-sync-status-url="{{ url('/clients/synced-emails/sync-status') }}"
    @endif
    @if($canUnlinkSyncedEmail)
    data-unlink-email-url="{{ route('clients.synced-emails.unlink') }}"
    @endif
    @if($canViewSyncedInbox)
    data-unassigned-count-url="{{ route('clients.synced-emails.unassigned-count') }}"
    @endif
    data-can-sync-inbox="{{ $canSyncInbox ? '1' : '0' }}"
    data-can-unlink-synced-email="{{ $canUnlinkSyncedEmail ? '1' : '0' }}"
    data-can-view-synced-inbox="{{ $canViewSyncedInbox ? '1' : '0' }}"
    data-can-select-sync-mailbox="{{ $canSelectSyncMailbox ? '1' : '0' }}"
    data-unassigned-only="{{ $unassignedOnly ? '1' : '0' }}"
    data-compact-pagination="{{ $compactPagination ? '1' : '0' }}"
    data-default-folder="{{ $assignmentReviewOnly ? 'review' : ($unassignedOnly ? 'unassigned' : 'inbox') }}"
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
            @if(! $unassignedOnly)
            <div class="list-toolbar__actions">
                {{-- Outlook/Gmail layout switch hidden — Outlook split view is the only UI for email + unassigned tabs.
                <div class="email-ui-mode-switch" id="emailUiModeSwitch" role="group" aria-label="Choose email layout">
                    <button type="button" class="email-ui-mode-btn" data-ui-mode="outlook" title="Outlook split view — list and preview side by side">
                        <i class="fa-solid fa-table-columns" aria-hidden="true"></i>
                        <span>Outlook</span>
                    </button>
                    <button type="button" class="email-ui-mode-btn" data-ui-mode="gmail" title="Gmail view — full list, open email in full page">
                        <i class="fa-solid fa-list" aria-hidden="true"></i>
                        <span>Gmail</span>
                    </button>
                </div>
                --}}
                <button type="button" class="action-btn action-btn--upload" id="btnUploadEmail" title="Upload Outlook email ({{ $crmEmailUploadLabel }})" hidden>
                    <i class="fa-solid fa-upload"></i> Upload
                </button>
            </div>
            @endif
            <div class="{{ $unassignedOnly ? 'list-toolbar__folder-row' : '' }}">
                @if($unassignedOnly)
                    @if($assignmentReviewOnly)
                    <div class="folder-inbox-title" id="unassignedFolderTitle">
                        <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                        <span>Needs Review</span>
                    </div>
                    @else
                    <div class="folder-tabs" role="tablist" aria-label="Synced mail folders">
                        <button type="button" class="folder-item active" data-folder="unassigned" role="tab" aria-selected="true">
                            <i class="fa-solid fa-user-clock" aria-hidden="true"></i>
                            Unassigned
                        </button>
                        <button type="button" class="folder-item" data-folder="assigned" role="tab" aria-selected="false">
                            <i class="fa-solid fa-user-check" aria-hidden="true"></i>
                            Assigned
                        </button>
                    </div>
                    @endif
                <div class="list-toolbar__side-actions">
                    @if($canSyncInbox && $canAssignBySubject && ! $assignmentReviewOnly)
                    <button type="button" class="list-toolbar__assign-subject" id="btnAssignBySubject" title="Assign unassigned emails whose subject has a matching client ID and matter">
                        <i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i>
                        <span class="list-toolbar__assign-subject-text">Assign by subject</span>
                    </button>
                    @endif
                    <button type="button" class="list-filter-toggle" id="btnToggleListFilters" aria-expanded="false" aria-controls="unassignedListFilters" title="Show filters">
                        <i class="fa-solid fa-filter" aria-hidden="true"></i>
                        <span class="list-filter-toggle__text">Filters</span>
                    </button>
                </div>
                @else
                <div class="{{ $compactPagination ? 'list-toolbar__folder-row' : '' }}">
                    <div class="folder-tabs" role="tablist" aria-label="Mail folders">
                        <button type="button" class="folder-item active" data-folder="inbox" role="tab" aria-selected="true">
                            <i class="fa-solid fa-inbox"></i> Inbox
                        </button>
                        <button type="button" class="folder-item" data-folder="sent" role="tab" aria-selected="false">
                            <i class="fa-solid fa-paper-plane"></i> Sent
                        </button>
                    </div>
                    @if($compactPagination)
                    <div class="list-toolbar__side-actions">
                        <button type="button" class="list-filter-toggle" id="btnToggleClientListFilters" aria-expanded="false" aria-controls="clientListFilters" title="Show search and filters">
                            <i class="fa-solid fa-filter" aria-hidden="true"></i>
                            <span class="list-filter-toggle__text">Filters</span>
                        </button>
                    </div>
                    @endif
                </div>
                @endif
            </div>
            <input type="file" id="outlookEmailFileInput" accept="{{ $crmEmailUploadAccept }}" multiple hidden>
        </div>

        @if($unassignedOnly)
        <div class="list-filters-drawer" id="unassignedListFilters">
            <div class="list-filters-drawer__inner">
                <div class="search-box search-box--compact list-toolbar__search">
                    <i class="fa-solid fa-search search-box-icon" aria-hidden="true"></i>
                    <input type="text" id="searchInput" placeholder="Search emails...">
                </div>
        @endif

        @if($canShowInboxSync && $unassignedOnly && ! $assignmentReviewOnly)
        <div class="sync-inbox-panel sync-inbox-panel--redesign{{ $canSelectSyncMailbox ? ' sync-inbox-panel--admin' : '' }}">
            <div class="sync-inbox-panel__section sync-inbox-panel__section--sync">
                <div class="sync-inbox-panel__section-label">Fetch mail</div>
                <div class="sync-inbox-panel__sync-row">
                    @if($canSelectSyncMailbox)
                    <div class="sync-inbox-panel__field sync-inbox-panel__field--grow">
                        <select id="syncMailboxFilter" class="list-filter-select sync-mailbox-select" aria-label="Select mailbox to sync" required>
                            <option value="">Select mailbox</option>
                            @foreach($syncMailboxOptions as $mailboxAddress)
                                <option value="{{ $mailboxAddress }}">{{ $mailboxAddress }}</option>
                            @endforeach
                        </select>
                    </div>
                    @endif
                    <div class="sync-inbox-panel__field sync-inbox-panel__field--range">
                        <select id="syncRangeFilter" class="list-filter-select sync-range-select" aria-label="Sync date range">
                            @foreach($unassignedSyncRangeOptions as $rangeValue => $rangeLabel)
                                <option value="{{ $rangeValue }}" @selected($rangeValue === $defaultUnassignedSyncRange)>{{ $rangeLabel }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="button" class="sync-inbox-panel__btn" id="btnSyncInbox" title="{{ $canSelectSyncMailbox ? 'Fetch mail from Zoho for the selected mailbox and range' : 'Fetch mail from Zoho for the selected range' }}">
                        <i class="fa-solid fa-rotate"></i>
                        <span>Sync now</span>
                    </button>
                </div>
            </div>
            <div class="sync-inbox-panel__section sync-inbox-panel__section--filters">
                <div class="sync-inbox-panel__section-label">Refine list</div>
                <div class="sync-inbox-panel__list-tools">
                    <select id="senderFilter" class="list-filter-select list-filter-select--compact" aria-label="Filter by sender">
                        <option value="">All senders</option>
                    </select>
                    @if(! empty($listMailboxFilterOptions))
                    <select id="listMailboxFilter" class="list-filter-select list-filter-select--compact" aria-label="Filter by mailbox">
                        <option value="">All mailboxes</option>
                        @foreach($listMailboxFilterOptions as $mailboxAddress)
                            <option value="{{ $mailboxAddress }}">{{ $mailboxAddress }}</option>
                        @endforeach
                    </select>
                    @endif
                    <select id="sortOrder" class="list-filter-select list-filter-select--compact" aria-label="Sort or filter">
                        <option value="desc" @selected(! $assignmentReviewOnly)>Newest</option>
                        <option value="asc">Oldest</option>
                        <option value="review" @selected($assignmentReviewOnly)>Needs Review</option>
                    </select>
                </div>
            </div>
        </div>
        @elseif($unassignedOnly)
        <div class="sync-inbox-panel sync-inbox-panel--redesign sync-inbox-panel--tools-only">
            <div class="sync-inbox-panel__section sync-inbox-panel__section--filters">
                <div class="sync-inbox-panel__section-label" id="unassignedPanelTitle">{{ $assignmentReviewOnly ? 'Review filters' : 'Refine list' }}</div>
                <div class="sync-inbox-panel__list-tools">
                    <select id="senderFilter" class="list-filter-select list-filter-select--compact" aria-label="Filter by sender">
                        <option value="">All senders</option>
                    </select>
                    @if(! empty($listMailboxFilterOptions))
                    <select id="listMailboxFilter" class="list-filter-select list-filter-select--compact" aria-label="Filter by mailbox">
                        <option value="">All mailboxes</option>
                        @foreach($listMailboxFilterOptions as $mailboxAddress)
                            <option value="{{ $mailboxAddress }}">{{ $mailboxAddress }}</option>
                        @endforeach
                    </select>
                    @endif
                    <select id="sortOrder" class="list-filter-select list-filter-select--compact" aria-label="Sort or filter">
                        <option value="desc" @selected(! $assignmentReviewOnly)>Newest</option>
                        <option value="asc">Oldest</option>
                        <option value="review" @selected($assignmentReviewOnly)>Needs Review</option>
                    </select>
                </div>
            </div>
        </div>
        @endif

        @if($unassignedOnly)
            </div>
        </div>
        @endif

        <div id="uploadStatus" class="upload-status" hidden></div>

        @if(! $unassignedOnly)
        <div id="inlineDropZone" class="inline-drop-zone">
            <i class="fa-solid fa-cloud-arrow-up"></i>
            <span>Drag & drop saved Outlook email files ({{ $crmEmailUploadLabel }}) here </span>
        </div>
        @endif

        @if(! $unassignedOnly)
        @if($compactPagination)
        <div class="list-filters-drawer" id="clientListFilters">
            <div class="list-filters-drawer__inner">
                <div class="list-header list-header--client-modern">
                    <div class="list-header-row">
                        <div class="search-box search-box--compact list-toolbar__search">
                            <i class="fa-solid fa-search search-box-icon" aria-hidden="true"></i>
                            <input type="search" id="searchInput" placeholder="Search emails..." autocomplete="off" aria-label="Search emails">
                        </div>
                    </div>
                    <div class="list-header-filters list-header-filters--modern">
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
            </div>
        </div>
        @else
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
        @endif
        @endif

        @if($unassignedOnly)
        <div class="synced-mail-list-body">
            <div class="email-list email-list--synced" id="emailList">
                <div class="email-list-loading">Loading emails...</div>
            </div>
            <div class="email-infinite-loader" id="emailInfiniteLoader" hidden aria-live="polite">
                <span class="email-infinite-loader__spinner" aria-hidden="true"></span>
                <span>Loading more emails...</span>
            </div>
        </div>
        @else
        <div class="email-list" id="emailList">
            <div style="padding:16px;text-align:center;color:#666;">Loading emails...</div>
        </div>
        @if($compactPagination)
        <div class="email-infinite-loader" id="emailInfiniteLoader" hidden aria-live="polite">
            <span class="email-infinite-loader__spinner" aria-hidden="true"></span>
            <span>Loading more emails...</span>
        </div>
        @else
        <div class="pagination-bar" id="emailPaginationBar">
            <div class="pagination-bar__summary">
                <span class="pagination-bar__page" id="pageSummary">Page 1 of 1</span>
                <span class="pagination-bar__count" id="pageInfo">0 emails</span>
            </div>
            <div class="pagination-controls">
                <label class="pagination-per-page">
                    <span>Show</span>
                    <select id="perPageSelect" aria-label="Emails per page">
                        <option value="10">10</option>
                        <option value="20" selected>20</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                        <option value="200">200</option>
                        <option value="500">500</option>
                    </select>
                    <span>per page</span>
                </label>
                <button type="button" class="pagination-btn" id="prevBtn" disabled aria-label="Previous page">
                    <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
                    <span>Previous</span>
                </button>
                <button type="button" class="pagination-btn" id="nextBtn" disabled aria-label="Next page">
                    <span>Next</span>
                    <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
                </button>
            </div>
        </div>
        @endif
        @endif
    </div>

    <!-- Reading Pane -->
    <div class="outlook-reading-pane">
        <!-- Empty State -->
        <div class="empty-state" id="emptyState">
            <i class="fa-solid fa-inbox" aria-hidden="true"></i>
            <p>Select an item to read</p>
        </div>

        <!-- Email Content (Hidden by default) -->
        <div class="reading-pane-content" id="readingPane">
            <div class="reading-header">
                <div class="gmail-read-topbar" id="gmailReadingToolbar" hidden>
                    <div class="gmail-read-topbar__left">
                        <button type="button" class="gmail-icon-btn" id="btnGmailBack" aria-label="Back to email list">
                            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                        </button>
                        <div class="gmail-read-topbar__actions">
                            @if($canDeleteEmail)
                            <button type="button" class="gmail-icon-btn" id="gmailIconDelete" title="Delete" hidden>
                                <i class="fa-solid fa-trash" aria-hidden="true"></i>
                            </button>
                            @endif
                            @if($canSyncInbox)
                            <button type="button" class="gmail-icon-btn" id="gmailIconAssign" title="Assign to client" hidden>
                                <i class="fa-solid fa-user-plus" aria-hidden="true"></i>
                            </button>
                            @endif
                            <div class="gmail-read-more-wrap">
                                <button type="button" class="gmail-icon-btn" id="gmailIconMore" title="More actions" aria-expanded="false" aria-haspopup="true">
                                    <i class="fa-solid fa-ellipsis-vertical" aria-hidden="true"></i>
                                </button>
                                <div class="gmail-read-more-menu" id="gmailReadMoreMenu" hidden role="menu">
                                    <button type="button" class="gmail-read-more-item" id="gmailMenuReply" role="menuitem"><i class="fa-solid fa-reply"></i> Reply</button>
                                    <button type="button" class="gmail-read-more-item" id="gmailMenuReplyAll" role="menuitem"><i class="fa-solid fa-reply-all"></i> Reply all</button>
                                    <button type="button" class="gmail-read-more-item" id="gmailMenuForward" role="menuitem"><i class="fa-solid fa-share"></i> Forward</button>
                                    <button type="button" class="gmail-read-more-item" id="gmailMenuResend" role="menuitem" hidden><i class="fa-solid fa-rotate-right"></i> Resend</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="gmail-read-topbar__right">
                        <span class="gmail-read-position" id="gmailReadPosition"></span>
                        <button type="button" class="gmail-icon-btn" id="gmailReadPrev" aria-label="Previous email">
                            <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
                        </button>
                        <button type="button" class="gmail-icon-btn" id="gmailReadNext" aria-label="Next email">
                            <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>
                <div class="action-bar action-bar--reading action-bar--outlook">
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
                    @if($canUnlinkSyncedEmail)
                    <button type="button" class="action-btn action-btn--warning" id="btnUnlinkFromClient" hidden title="Reassign this email to another client or move it to Unassigned Mail">
                        <i class="fa-solid fa-arrow-right-arrow-left"></i> Reassign Client
                    </button>
                    @endif
                    @if($canDeleteEmail)
                    <button type="button" class="action-btn action-btn--danger" id="btnDeleteEmail" title="Delete email and attachments">
                        <i class="fa-solid fa-trash"></i> Delete
                    </button>
                    @endif
                </div>
                <div class="email-assignment-review-banner" id="assignmentReviewBanner" hidden></div>

                <div class="gmail-read-subject-row">
                    <h2 class="email-full-subject" id="readSubject">Loading...</h2>
                    <span class="gmail-folder-chip" id="gmailFolderChip" hidden></span>
                </div>

                <div class="email-meta">
                    <div class="sender-avatar" id="readAvatar">?</div>
                    <div class="meta-details">
                        <div class="meta-sender" id="readSender">Loading...</div>
                        <div class="meta-recipients" id="readTo">Loading...</div>
                        <div class="meta-recipients meta-cc" id="readCc" hidden></div>
                        <div class="meta-recipients meta-bcc" id="readBcc" hidden></div>
                    </div>
                    <div class="gmail-read-meta__right">
                        <div class="meta-date" id="readDate"></div>
        {{-- Sync source (Manual sync / Cron) hidden from reading pane.
        <div class="meta-sync-source" id="readSyncSource" hidden></div>
        --}}
                        <div class="gmail-read-meta__icons">
                            <button type="button" class="gmail-icon-btn gmail-icon-btn--sm" id="gmailMetaReply" title="Reply">
                                <i class="fa-solid fa-reply" aria-hidden="true"></i>
                            </button>
                            <button type="button" class="gmail-icon-btn gmail-icon-btn--sm" id="gmailMetaMore" title="More actions" aria-expanded="false">
                                <i class="fa-solid fa-ellipsis-vertical" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="email-send-error" id="readSendError" hidden></div>
                <div class="email-calendar-banner" id="readCalendarBanner" hidden></div>
            </div>

            <div class="reading-scroll">
                <div id="attachmentsContainer" class="email-attachments-container reading-attachments reading-attachments--footer" hidden></div>

                <div class="reading-body">
                    <iframe id="readBody" sandbox="allow-same-origin allow-popups allow-popups-to-escape-sandbox" title="Email body"></iframe>
                </div>

                <div class="gmail-read-footer" id="gmailReadingFooter" hidden>
                    <button type="button" class="gmail-pill-btn" id="gmailFooterReply">
                        <i class="fa-solid fa-reply" aria-hidden="true"></i> Reply
                    </button>
                    <button type="button" class="gmail-pill-btn" id="gmailFooterForward">
                        <i class="fa-solid fa-share" aria-hidden="true"></i> Forward
                    </button>
                </div>
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
            <input type="text" id="composeTo" autocomplete="off" placeholder="Add recipients">
            <div class="compose-field-extras">
                <button type="button" class="compose-extra-toggle" id="composeShowCc">Cc</button>
                <button type="button" class="compose-extra-toggle" id="composeShowBcc">Bcc</button>
            </div>
        </div>
        <div class="compose-field compose-field--optional" id="composeCcField" hidden>
            <label for="composeCc">Cc</label>
            <input type="text" id="composeCc" autocomplete="off" placeholder="Optional — separate with commas">
        </div>
        <div class="compose-field compose-field--optional" id="composeBccField" hidden>
            <label for="composeBcc">Bcc</label>
            <input type="text" id="composeBcc" autocomplete="off" placeholder="Optional — separate with commas">
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
                <button type="button" class="compose-format-btn" data-cmd="insertTable" title="Insert table"><i class="fa-solid fa-table"></i></button>
                <span class="compose-format-sep" aria-hidden="true"></span>
                <button type="button" class="compose-format-btn" data-cmd="removeFormat" title="Clear formatting"><i class="fa-solid fa-eraser"></i></button>
            </div>
            <div id="composeReplyInput" class="compose-reply-input" contenteditable="true" role="textbox" aria-multiline="true" data-placeholder="Type your message..."></div>
            <div id="composeQuoteWrap" class="compose-quote-wrap" hidden>
                <button type="button" class="compose-quote-toggle" id="composeQuoteToggle" aria-expanded="true">
                    <i class="fa-solid fa-chevron-down compose-quote-chevron" aria-hidden="true"></i>
                    <span class="compose-quote-toggle-label" id="composeQuoteToggleLabel">Original email</span>
                </button>
                <div id="composeQuotePanel" class="compose-quote-panel">
                    <iframe id="composeQuoteFrame" class="compose-quote-frame" title="Original email" tabindex="-1"></iframe>
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

@if($canShowInboxSync && $unassignedOnly)
<div class="full-sync-confirm-overlay outlook-modal-overlay" id="fullSyncConfirmModal" aria-hidden="true">
    <div class="full-sync-confirm-modal outlook-ui-modal outlook-ui-modal--sm" role="dialog" aria-labelledby="fullSyncConfirmTitle" aria-modal="true">
        <div class="outlook-ui-modal__header outlook-ui-modal__header--warn">
            <div class="outlook-ui-modal__header-main">
                <div class="outlook-ui-modal__header-icon" aria-hidden="true">
                    <i class="fa-solid fa-arrows-rotate"></i>
                </div>
                <div class="outlook-ui-modal__header-text">
                    <h3 class="outlook-ui-modal__title" id="fullSyncConfirmTitle">Run full sync?</h3>
                    <p class="outlook-ui-modal__subtitle">This resets mailbox tracking and re-imports recent mail.</p>
                </div>
            </div>
        </div>
        <div class="outlook-ui-modal__body">
            <p class="full-sync-confirm-modal__message">
                Messages already stored in the CRM will be skipped. Use this only when you need a deeper backfill.
            </p>
        </div>
        <div class="outlook-ui-modal__footer">
            <button type="button" class="outlook-ui-modal__btn outlook-ui-modal__btn--cancel" id="fullSyncConfirmCancel">Cancel</button>
            <button type="button" class="outlook-ui-modal__btn outlook-ui-modal__btn--confirm" id="fullSyncConfirmProceed">
                <i class="fa-solid fa-rotate" aria-hidden="true"></i>
                Continue sync
            </button>
        </div>
    </div>
</div>
@endif

@if($canSyncInbox || $canUnlinkSyncedEmail)
{{-- data-bs-focus=false: Tom Select results render on body; focus-trap would steal clicks from options --}}
<div class="modal fade assign-email-modal outlook-ui-modal-wrapper" id="assignSyncedEmailModal" tabindex="-1" role="dialog" aria-labelledby="assignSyncedEmailModalLabel" aria-hidden="true" data-bs-focus="false">
    <div class="modal-dialog modal-dialog-centered assign-email-modal__dialog" role="document">
        <div class="modal-content assign-email-modal__content outlook-ui-modal">
            <div class="modal-header assign-email-modal__header outlook-ui-modal__header">
                <div class="assign-email-modal__header-main outlook-ui-modal__header-main">
                    <div class="assign-email-modal__icon outlook-ui-modal__header-icon" id="assignSyncedEmailModalIcon" aria-hidden="true">
                        <i class="fa-solid fa-user-plus"></i>
                    </div>
                    <div class="assign-email-modal__titles outlook-ui-modal__header-text">
                        <h5 class="modal-title outlook-ui-modal__title" id="assignSyncedEmailModalLabel">Assign Email to Client</h5>
                        <p class="assign-email-modal__subtitle outlook-ui-modal__subtitle" id="assignSyncedEmailModalSubtitle">Pick a client, then a matter.</p>
                    </div>
                </div>
                <button type="button" class="assign-email-modal__close outlook-ui-modal__close" data-bs-dismiss="modal" aria-label="Close">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </div>
            <div class="modal-body assign-email-modal__body outlook-ui-modal__body">
                <input type="hidden" id="assignEmailLogId" value="">
                <input type="hidden" id="assignClientMatterId" value="">

                <div class="assign-email-preview" id="assignEmailPreview">
                    <div class="assign-email-preview__content">
                        <div class="assign-email-preview__subject" id="assignEmailPreviewSubject">—</div>
                        <div class="assign-email-preview__meta" id="assignEmailPreviewMeta"></div>
                    </div>
                </div>

                <div class="unlink-email-destination" id="unlinkEmailDestination" hidden>
                    <div class="unlink-email-destination__label">Where should this email go?</div>
                    <div class="unlink-email-destination__options" role="group" aria-label="Choose email destination">
                        <button type="button" class="unlink-email-destination__option active" data-unlink-destination="unassigned">
                            <i class="fa-solid fa-user-clock" aria-hidden="true"></i>
                            <span>
                                <strong>Unassigned Mail</strong>
                                <small>Remove the current client link for later review.</small>
                            </span>
                        </button>
                        <button type="button" class="unlink-email-destination__option" data-unlink-destination="client">
                            <i class="fa-solid fa-arrow-right-arrow-left" aria-hidden="true"></i>
                            <span>
                                <strong>Another Client</strong>
                                <small>Reassign to the correct client and matter now.</small>
                            </span>
                        </button>
                    </div>
                </div>

                <div class="assign-email-fields" id="assignEmailFields">
                    <div class="assign-email-field assign-email-field--client" id="assignClientField">
                        <label class="assign-email-field__label" for="assignClientId-ts-control">
                            <span class="assign-email-field__label-text">
                                <span class="assign-email-field__step">1</span>
                                Search client
                            </span>
                        </label>
                        <div class="assign-email-picker assign-email-picker--client" id="assignClientPicker">
                            <i class="fa-solid fa-magnifying-glass assign-email-picker__icon" aria-hidden="true"></i>
                            <div class="assign-email-picker__control">
                                <select id="assignClientId" class="form-control crm-ts-plain assign-email-field__select" autocomplete="off">
                                    @if(!empty($clientData))
                                        <option value="{{ $clientData->id }}" selected>
                                            {{ $clientData->first_name }} {{ $clientData->last_name }} ({{ $clientData->client_id }})
                                        </option>
                                    @endif
                                </select>
                            </div>
                        </div>
                        <div class="assign-selected-client" id="assignSelectedClient" hidden>
                            <div class="assign-selected-client__main">
                                <div class="assign-selected-client__icon" aria-hidden="true">
                                    <i class="fa-solid fa-user-check"></i>
                                </div>
                                <div class="assign-selected-client__text">
                                    <div class="assign-selected-client__name" id="assignSelectedClientName"></div>
                                    <div class="assign-selected-client__meta" id="assignSelectedClientMeta"></div>
                                </div>
                            </div>
                            <button type="button" class="assign-selected-client__change" id="assignChangeClientBtn">
                                Change
                            </button>
                        </div>
                        <div class="assign-email-sender-hint" id="assignSenderHint" hidden>
                            <span class="assign-email-sender-hint__label">From</span>
                            <button type="button" class="assign-email-sender-hint__btn" id="assignSearchSenderBtn" title="Search using this sender address"></button>
                        </div>
                        <p class="assign-email-field__hint" id="assignClientHint">Type a name, email, phone, or client ref — then pick a result.</p>
                    </div>

                    <div class="assign-email-field assign-email-field--matter assign-email-field--disabled" id="assignMatterField">
                        <label class="assign-email-field__label">
                            <span class="assign-email-field__label-text">
                                <span class="assign-email-field__step" id="assignMatterStepBadge">2</span>
                                Choose matter
                            </span>
                        </label>
                        <div class="assign-matter-picker" id="assignMatterPicker">
                            <div class="assign-matter-picker__placeholder" id="assignMatterPlaceholder">
                                <span>Select a client above to load matters.</span>
                            </div>
                            <div class="assign-matter-picker__list" id="assignMatterList" hidden></div>
                            <div class="assign-matter-picker__loading" id="assignMatterLoading" hidden>
                                <i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i>
                                <span>Loading matters…</span>
                            </div>
                        </div>
                        <p class="assign-email-field__hint" id="assignMatterHint" hidden></p>
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

@if($unassignedOnly && $canSyncInbox && $canAssignBySubject && empty($assignmentReviewOnly))
<div class="modal fade assign-subject-modal outlook-ui-modal-wrapper" id="assignBySubjectModal" tabindex="-1" role="dialog" aria-labelledby="assignBySubjectModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered assign-subject-modal__dialog" role="document">
        <div class="modal-content outlook-ui-modal">
            <div class="modal-header outlook-ui-modal__header">
                <div class="outlook-ui-modal__header-main">
                    <div class="outlook-ui-modal__header-icon" aria-hidden="true">
                        <i class="fa-solid fa-user-check"></i>
                    </div>
                    <div class="outlook-ui-modal__header-text">
                        <h5 class="modal-title outlook-ui-modal__title" id="assignBySubjectModalLabel">Assign by subject</h5>
                        <p class="outlook-ui-modal__subtitle" id="assignBySubjectModalSubtitle">Matching unassigned emails to clients.</p>
                    </div>
                </div>
                <button type="button" class="outlook-ui-modal__close" data-bs-dismiss="modal" aria-label="Close">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </div>
            <div class="modal-body outlook-ui-modal__body assign-subject-modal__body" id="assignBySubjectModalBody"></div>
            <div class="modal-footer outlook-ui-modal__footer">
                <button type="button" class="btn outlook-ui-modal__btn outlook-ui-modal__btn--cancel" data-bs-dismiss="modal" id="assignBySubjectCloseBtn">Close</button>
                <button type="button" class="btn outlook-ui-modal__btn outlook-ui-modal__btn--confirm" id="assignBySubjectConfirmBtn" hidden>
                    <i class="fa-solid fa-link" aria-hidden="true"></i>
                    Assign selected
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
