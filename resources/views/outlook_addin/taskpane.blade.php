<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BansalLaw CRM — Outlook Add-in</title>
    <!-- Microsoft Office.js -->
    <script src="https://appsforoffice.microsoft.com/lib/1/hosted/office.js"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #0078d4;
            --primary-hover: #106ebe;
            --primary-light: #eff6fc;
            --success: #107c10;
            --success-light: #e7f6ec;
            --warning: #d97706;
            --warning-light: #fff4e5;
            --danger: #d13438;
            --danger-light: #fde7e9;
            --text-main: #201f1e;
            --text-secondary: #605e5c;
            --border: #edebe9;
            --bg-subtle: #faf9f8;
            --card-bg: #ffffff;
            --radius-md: 8px;
            --radius-lg: 12px;
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.06);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #f8fafc;
            color: var(--text-main);
            font-size: 13px;
            line-height: 1.45;
            padding: 12px;
            overflow-x: hidden;
        }

        /* Top Brand Header */
        .brand-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--border);
            margin-bottom: 12px;
        }

        .brand-logo {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .brand-logo img {
            height: 24px;
            max-width: 120px;
            object-fit: contain;
        }

        .brand-logo span {
            font-weight: 700;
            font-size: 13px;
            color: #0f172a;
            letter-spacing: -0.2px;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 3px 8px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 600;
            background: var(--success-light);
            color: var(--success);
        }

        .status-badge.test-mode {
            background: var(--warning-light);
            color: var(--warning);
        }

        .status-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: currentColor;
        }

        /* Test Mode Banner */
        .test-banner {
            display: none;
            background: var(--warning-light);
            border: 1px solid #fcd34d;
            color: #92400e;
            padding: 8px 10px;
            border-radius: var(--radius-md);
            font-size: 11.5px;
            margin-bottom: 12px;
            line-height: 1.35;
        }

        .test-banner.active {
            display: block;
        }

        /* Cards */
        .crm-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 12px;
            margin-bottom: 10px;
            box-shadow: var(--shadow-sm);
        }

        .card-title {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-secondary);
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .badge-count {
            background: #f1f5f9;
            color: var(--text-secondary);
            padding: 1px 6px;
            border-radius: 999px;
            font-size: 10.5px;
            font-weight: 600;
        }

        /* Email Info Preview */
        .email-subject {
            font-size: 13px;
            font-weight: 600;
            color: #0f172a;
            word-break: break-word;
            margin-bottom: 6px;
        }

        .email-meta-row {
            display: flex;
            align-items: center;
            gap: 6px;
            color: var(--text-secondary);
            font-size: 11.5px;
            margin-bottom: 4px;
            word-break: break-all;
        }

        .email-meta-row i {
            width: 14px;
            color: #94a3b8;
            text-align: center;
        }

        /* Match Box & Assignment Badges (Same as CRM Email List) */
        .email-client-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 2.5px 8px;
            border-radius: 9999px;
            font-size: 11px;
            font-weight: 600;
            line-height: 1.4;
            white-space: nowrap;
        }

        .email-client-badge--auto {
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            color: #047857;
        }

        .email-client-badge--auto i {
            color: #059669;
        }

        .email-client-badge--manual-upload {
            background: #fff7ed;
            border: 1px solid #fed7aa;
            color: #9a3412;
        }

        .email-client-badge--manual-upload i {
            color: #ea580c;
        }

        .email-client-badge--outlook-addin {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            color: #1d4ed8;
        }

        .email-client-badge--outlook-addin i {
            color: #2563eb;
        }

        .email-client-badge__logo {
            width: 14px;
            height: 14px;
            object-fit: contain;
            flex-shrink: 0;
            vertical-align: -2px;
            margin-right: 4px;
            border-radius: 2px;
        }

        .success-modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.45);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            padding: 16px;
        }

        .success-modal-overlay.active {
            display: flex;
        }

        .success-modal {
            background: #fff;
            border-radius: 12px;
            padding: 20px 18px 16px;
            max-width: 320px;
            width: 100%;
            box-shadow: 0 12px 40px rgba(15, 23, 42, 0.25);
            text-align: center;
        }

        .success-modal__icon {
            width: 48px;
            height: 48px;
            border-radius: 9999px;
            background: #e7f6ec;
            color: #107c10;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            margin-bottom: 10px;
        }

        .success-modal__title {
            font-size: 16px;
            font-weight: 700;
            color: #0f172a;
            margin: 0 0 6px;
        }

        .success-modal__body {
            font-size: 13px;
            color: #475569;
            line-height: 1.45;
            margin: 0 0 14px;
        }

        .success-modal__btn {
            border: none;
            background: #0078d4;
            color: #fff;
            font-weight: 600;
            font-size: 13px;
            border-radius: 8px;
            padding: 8px 16px;
            cursor: pointer;
            width: 100%;
        }

        .success-modal__btn:hover {
            background: #106ebe;
        }

        .success-modal__icon--warn {
            background: #fff7ed;
            color: #c2410c;
        }

        .success-modal__btn--warn {
            background: #c2410c;
        }

        .success-modal__btn--warn:hover {
            background: #9a3412;
        }

        .success-modal__link {
            display: inline-block;
            margin-top: 8px;
            color: #0078d4;
            font-weight: 600;
            text-decoration: underline;
            font-size: 12px;
        }

        .match-badge-wrap {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 8px;
        }

        .confidence-score {
            font-size: 10px;
            font-weight: 600;
            color: #64748b;
            background: #f1f5f9;
            padding: 1px 6px;
            border-radius: 4px;
        }

        .match-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 11px;
            font-weight: 600;
            padding: 2px 7px;
            border-radius: 4px;
            background: var(--success-light);
            color: var(--success);
            margin-bottom: 8px;
        }

        .matter-target-box {
            padding: 10px;
            border-radius: var(--radius-md);
            background: var(--primary-light);
            border: 1px solid #c7e0f4;
            margin-bottom: 8px;
        }

        .matter-target-title {
            font-weight: 700;
            font-size: 13px;
            color: #004578;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .matter-target-sub {
            font-size: 11.5px;
            color: #005a9e;
            margin-top: 3px;
        }

        .toggle-change-btn {
            background: none;
            border: none;
            color: var(--primary);
            font-size: 11.5px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: underline;
            padding: 2px 0;
        }

        .toggle-change-btn:hover {
            color: var(--primary-hover);
        }

        /* Attachments Section */
        .attachments-list {
            margin-bottom: 8px;
        }

        .attachment-folder-toolbar {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: var(--radius-md);
            padding: 8px 10px;
            margin-bottom: 8px;
        }

        .toolbar-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 5px;
        }

        .toolbar-label {
            font-size: 11px;
            font-weight: 700;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .folder-picker-select {
            width: 100%;
            padding: 6px 8px;
            font-size: 12px;
            font-weight: 500;
            color: #0f172a;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            background: #fff;
            outline: none;
            cursor: pointer;
            transition: border-color .15s, box-shadow .15s;
        }

        .folder-picker-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(0, 120, 212, 0.15);
        }

        .attachment-item {
            display: flex;
            flex-direction: column;
            gap: 6px;
            padding: 7px 9px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 11.5px;
            margin-bottom: 6px;
        }

        .attachment-row-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 6px;
        }

        .attachment-left {
            display: flex;
            align-items: center;
            gap: 6px;
            overflow: hidden;
            flex: 1;
        }

        .attachment-left i {
            color: var(--primary);
            font-size: 13px;
            flex-shrink: 0;
        }

        .attachment-filename {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 170px;
        }

        .attachment-badge {
            font-size: 10.5px;
            font-weight: 600;
            padding: 2px 7px;
            border-radius: 4px;
            background: #e0f2fe;
            color: #0369a1;
            white-space: nowrap;
            flex-shrink: 0;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .attachment-row-bottom {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 6px;
            padding-top: 5px;
            border-top: 1px dashed #e2e8f0;
            font-size: 11px;
            color: #64748b;
        }

        .single-folder-select {
            padding: 3px 6px;
            font-size: 11px;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            background: #fff;
            color: #334155;
            outline: none;
            max-width: 180px;
        }

        .no-attachments-note {
            font-size: 11.5px;
            color: var(--text-secondary);
            font-style: italic;
            padding: 4px 0 6px;
        }

        /* Destination Callout Box */
        .destination-callout {
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            border-radius: var(--radius-md);
            padding: 8px 10px;
            font-size: 11.5px;
            color: #334155;
            margin-top: 6px;
        }

        .dest-row {
            display: flex;
            align-items: flex-start;
            gap: 6px;
            margin-bottom: 4px;
        }

        .dest-row:last-child {
            margin-bottom: 0;
        }

        .dest-row i {
            width: 14px;
            color: #64748b;
            margin-top: 2px;
            text-align: center;
        }

        .dest-highlight {
            font-weight: 700;
            color: #0f172a;
        }

        /* Search Section */
        .matter-search-drawer {
            display: none;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px dashed var(--border);
        }

        .matter-search-drawer.open {
            display: block;
        }

        .input-group {
            position: relative;
            margin-bottom: 8px;
        }

        .input-group i {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 12px;
        }

        .search-input {
            width: 100%;
            padding: 7px 10px 7px 30px;
            border: 1px solid #cbd5e1;
            border-radius: var(--radius-md);
            font-size: 12px;
            outline: none;
            transition: border-color .15s;
        }

        .search-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(0, 120, 212, 0.15);
        }

        .matter-results-list {
            max-height: 140px;
            overflow-y: auto;
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            background: #fff;
        }

        .matter-result-item {
            padding: 7px 10px;
            font-size: 12px;
            border-bottom: 1px solid #f1f5f9;
            cursor: pointer;
            transition: background .12s;
        }

        .matter-result-item:last-child {
            border-bottom: none;
        }

        .matter-result-item:hover {
            background: var(--primary-light);
        }

        .matter-result-item .m-title {
            font-weight: 600;
            color: #0f172a;
        }

        .matter-result-item .m-sub {
            font-size: 11px;
            color: var(--text-secondary);
        }

        /* Options row */
        .form-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 12px;
        }

        .folder-segmented {
            display: flex;
            border: 1px solid #cbd5e1;
            border-radius: var(--radius-md);
            overflow: hidden;
            background: #f1f5f9;
        }

        .folder-segmented button {
            background: transparent;
            border: none;
            padding: 5px 12px;
            font-size: 11.5px;
            font-weight: 600;
            color: var(--text-secondary);
            cursor: pointer;
            transition: all .15s;
        }

        .folder-segmented button.active {
            background: #fff;
            color: var(--primary);
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .staff-select {
            width: 100%;
            padding: 6px 8px;
            border: 1px solid #cbd5e1;
            border-radius: var(--radius-md);
            font-size: 11.5px;
            background: #fff;
            outline: none;
        }

        /* Primary Action Button */
        .btn-save {
            width: 100%;
            padding: 10px 16px;
            background: var(--primary);
            color: #fff;
            border: none;
            border-radius: var(--radius-md);
            font-size: 13.5px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all .15s ease;
            box-shadow: 0 2px 6px rgba(0, 120, 212, 0.25);
            margin-top: 4px;
        }

        .btn-save:hover:not(:disabled) {
            background: var(--primary-hover);
            transform: translateY(-1px);
        }

        .btn-save:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        /* Alerts and Results */
        .alert-box {
            padding: 10px 12px;
            border-radius: var(--radius-md);
            font-size: 12px;
            margin-top: 10px;
            display: none;
        }

        .alert-box.active {
            display: block;
            animation: fadeIn .2s ease-out;
        }

        .alert-box.success {
            background: var(--success-light);
            border: 1px solid #a3e635;
            color: #14532d;
        }

        .alert-box.error {
            background: var(--danger-light);
            border: 1px solid #fecaca;
            color: #7f1d1d;
        }

        .alert-box a {
            color: var(--primary);
            font-weight: 600;
            text-decoration: underline;
            margin-top: 5px;
            display: inline-block;
        }

        /* Test Controls in Test Mode */
        .test-input-card {
            display: none;
            margin-bottom: 12px;
        }

        .test-input-card.active {
            display: block;
        }

        .test-input-card input {
            width: 100%;
            padding: 6px 10px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 12px;
            margin-bottom: 6px;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(4px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>

    <!-- Header -->
    <div class="brand-header">
        <div class="brand-logo">
            <img src="{{ asset('img/logo_new.png') }}" alt="Bansal Lawyers Logo" onerror="this.style.display='none'">
            <span>BansalLaw CRM</span>
        </div>
        <div class="status-badge" id="appStatusBadge">
            <span class="status-dot"></span>
            <span id="appStatusText">Ready</span>
        </div>
    </div>

    <!-- Test Mode Banner (When opened in standard browser outside Outlook) -->
    <div class="test-banner" id="testBanner">
        <i class="fa-solid fa-flask" style="margin-right: 4px;"></i>
        <strong>Local Test & Preview Mode:</strong> Office.js was not detected in this window. You can simulate and test matter matching and saving below.
    </div>

    <!-- Test Input Simulator (Only visible in standalone browser mode) -->
    <div class="crm-card test-input-card" id="testInputCard">
        <div class="card-title">Simulate Email Subject</div>
        <input type="text" id="simSubject" value="Re: GURM2600071 / FAM_1 | Response from client" placeholder="e.g. GURM2600071 / FAM_1">
        <input type="text" id="simSender" value="sgurmanpreet@gmail.com" placeholder="Sender email (optional)">
        <button type="button" class="btn-save" style="padding: 6px; font-size: 12px; margin-top: 4px;" onclick="runSimulation()">
            <i class="fa-solid fa-wand-magic-sparkles"></i> Test Auto-Match
        </button>
    </div>

    <!-- Current Email Card -->
    <div class="crm-card">
        <div class="card-title">
            <span>Current Email</span>
            <span id="emailDate" style="font-weight: 500; font-size: 10.5px; color: #94a3b8;">Today</span>
        </div>
        <div class="email-subject" id="emailSubjectTitle">Loading email...</div>
        <div class="email-meta-row">
            <i class="fa-solid fa-user"></i>
            <span id="emailSender">--</span>
        </div>
    </div>

    <!-- Matter Match Card -->
    <div class="crm-card">
        <div class="card-title">
            <span>Matter Allocation</span>
            <button type="button" class="toggle-change-btn" id="toggleChangeMatterBtn" onclick="toggleSearchDrawer()">Change Client / Matter</button>
        </div>

        <div id="matchingSpinner" style="display: none; padding: 10px; text-align: center; color: var(--text-secondary); font-size: 12px;">
            <i class="fa-solid fa-circle-notch fa-spin"></i> Finding matching matter...
        </div>

        <div class="matter-target-box" id="matterTargetBox" style="display: none;">
            <div class="match-badge-wrap">
                <span id="assignmentBadgeContainer">
                    <span class="email-client-badge email-client-badge--auto" id="assignmentBadge">
                        <i class="fa-solid fa-wand-magic-sparkles"></i> Auto assigned
                    </span>
                </span>
                <span class="confidence-score" id="matchConfidenceSub" style="display: none;">98% match</span>
            </div>
            <div class="matter-target-title" id="matchedMatterNo">FAM_1</div>
            <div class="matter-target-sub" id="matchedClientInfo">GURM2600071 — Gurmanpreet Singh</div>
        </div>

        <div id="noMatchBox" style="display: none; padding: 10px; background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: var(--radius-md); text-align: center; color: var(--text-secondary); font-size: 12px;">
            <i class="fa-solid fa-triangle-exclamation" style="color: var(--warning); margin-bottom: 4px;"></i><br>
            No matching client matter found automatically.<br>
            <button type="button" class="toggle-change-btn" style="margin-top: 4px;" onclick="toggleSearchDrawer(true)">Search & Select Client / Matter</button>
        </div>

        <!-- Search Drawer for Manual Assignment -->
        <div class="matter-search-drawer" id="searchDrawer">
            <div class="input-group">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" class="search-input" id="matterSearchInput" placeholder="Search client ID, lead, name, or matter #..." oninput="handleMatterSearch(this.value)">
            </div>
            <div class="matter-results-list" id="matterResultsList">
                <div style="padding: 12px; text-align: center; color: #94a3b8; font-size: 11.5px;">Type to search matters...</div>
            </div>
        </div>
    </div>

    <!-- Attachments & Destination Card -->
    <div class="crm-card" id="attachmentsCard">
        <div class="card-title">
            <span>Attachments</span>
            <span class="badge-count" id="attachmentCountBadge">0 files</span>
        </div>

        <!-- Folder Selection Toolbar (visible when attachments exist) -->
        <div class="attachment-folder-toolbar" id="attachmentFolderToolbar" style="display: none;">
            <div class="toolbar-header">
                <span class="toolbar-label">
                    <i class="fa-solid fa-folder-tree" style="color: var(--primary);"></i>
                    <span>Save to Folder:</span>
                </span>
                <span id="folderKindBadge" style="font-size: 10px; font-weight: 600; color: #0284c7; background: #e0f2fe; padding: 1px 6px; border-radius: 4px;">Matter Docs</span>
            </div>
            <select class="folder-picker-select" id="globalFolderSelect" onchange="handleGlobalFolderChange(this.value)">
                <option value="">Loading folders...</option>
            </select>
        </div>

        <div id="attachmentsContainer">
            <div class="no-attachments-note" id="noAttachmentsNote">No attachments detected in this email.</div>
            <div class="attachments-list" id="attachmentsList" style="display: none;"></div>
        </div>

        <!-- Destination breakdown callout -->
        <div class="destination-callout">
            <div class="dest-row">
                <i class="fa-solid fa-envelope"></i>
                <div><strong>Email:</strong> Saved to <span class="dest-highlight" id="destMatterEmail">Selected Matter</span> &rarr; <em>Emails Tab</em></div>
            </div>
            <div class="dest-row">
                <i class="fa-solid fa-folder-open"></i>
                <div><strong>Attachments:</strong> Saved to <span class="dest-highlight" id="destMatterDocs">Selected Matter &rarr; Matter Documents</span></div>
            </div>
        </div>
    </div>

    <!-- Save Options -->
    <div class="crm-card">
        <div class="form-row">
            <span style="font-weight: 600;">Save To Folder</span>
            <div class="folder-segmented" role="group">
                <button type="button" class="active" id="btnFolderInbox" onclick="setMailType('inbox')">
                    <i class="fa-solid fa-inbox" style="margin-right: 3px;"></i> Inbox
                </button>
                <button type="button" id="btnFolderSent" onclick="setMailType('sent')">
                    <i class="fa-solid fa-paper-plane" style="margin-right: 3px;"></i> Sent
                </button>
            </div>
        </div>

        <div class="form-row" style="margin-bottom: 0;">
            <span style="font-weight: 600; width: 80px;">Assigned By</span>
            <select class="staff-select" id="staffSelect">
                @foreach($staffMembers as $staff)
                    <option value="{{ $staff->id }}" {{ $currentStaff && $currentStaff->id == $staff->id ? 'selected' : '' }}>
                        {{ $staff->first_name }} {{ $staff->last_name }} ({{ $staff->email }})
                    </option>
                @endforeach
            </select>
        </div>
    </div>

    <!-- Action Button -->
    <button type="button" class="btn-save" id="btnSaveToCrm" onclick="saveEmailToCrm()" disabled>
        <i class="fa-solid fa-cloud-arrow-up"></i>
        <span id="saveBtnText">Save to CRM Matter</span>
    </button>

    <!-- Feedback Message Box -->
    <div class="alert-box" id="feedbackAlert"></div>

    <!-- Prominent success / duplicate alert after save -->
    <div class="success-modal-overlay" id="successModalOverlay" role="dialog" aria-modal="true" aria-labelledby="successModalTitle">
        <div class="success-modal">
            <div class="success-modal__icon" id="successModalIcon"><i class="fa-solid fa-circle-check"></i></div>
            <h3 class="success-modal__title" id="successModalTitle">Saved to CRM</h3>
            <p class="success-modal__body" id="successModalBody">Email moved successfully.</p>
            <a class="success-modal__link" id="successModalLink" href="#" target="_blank" rel="noopener" style="display:none;">Open in CRM</a>
            <button type="button" class="success-modal__btn" id="successModalOkBtn" onclick="hideSuccessModal()">OK</button>
        </div>
    </div>

    <script>
        const BASE_URL = window.location.origin;
        const OUTLOOK_ADDIN_LOGO_URL = @json(asset('img/logo_new.png'));

        function outlookAddinBadgeHtml(label, extraStyle) {
            const text = label || 'Outlook add-in';
            const styleAttr = extraStyle ? ` style="${extraStyle}"` : '';
            return `<span class="email-client-badge email-client-badge--outlook-addin"${styleAttr}>`
                + `<img src="${OUTLOOK_ADDIN_LOGO_URL}" alt="" class="email-client-badge__logo" width="14" height="14" onerror="this.style.display='none'"> `
                + `${text}</span>`;
        }

        let currentItem = null;
        let selectedClientId = null;
        let selectedMatterId = null;
        let selectedMatterName = 'Selected Matter';
        let selectedMailType = 'inbox';
        let isOfficeInitialized = false;
        let detectedAttachments = [];
        let matterFoldersList = [];
        let personalFoldersList = [];
        let selectedDefaultFolder = 'matter:1';
        let singleAttachmentFolders = {};
        let isAutoMatched = false;
        // Initialize staff preference from localStorage
        try {
            const savedStaffId = localStorage.getItem('bansal_crm_staff_id');
            const staffSelectEl = document.getElementById('staffSelect');
            if (savedStaffId && staffSelectEl) {
                const opt = staffSelectEl.querySelector(`option[value="${savedStaffId}"]`);
                if (opt) staffSelectEl.value = savedStaffId;
            }
            if (staffSelectEl) {
                staffSelectEl.addEventListener('change', function() {
                    localStorage.setItem('bansal_crm_staff_id', this.value);
                });
            }
        } catch (e) {
            // localStorage not accessible in strict sandboxed iframes
        }

        // Initialize Office.js
        Office.onReady(function (info) {
            if (info && info.host === Office.HostType.Outlook) {
                isOfficeInitialized = true;
                document.getElementById('appStatusBadge').classList.remove('test-mode');
                document.getElementById('appStatusText').textContent = 'Connected';
                loadCurrentOutlookEmail();
            } else {
                // Browser standalone test mode
                document.getElementById('testBanner').classList.add('active');
                document.getElementById('testInputCard').classList.add('active');
                document.getElementById('appStatusBadge').classList.add('test-mode');
                document.getElementById('appStatusText').textContent = 'Test Mode';
                runSimulation();
            }
        });

        // Fallback for standalone browsers if Office.onReady doesn't fire host
        setTimeout(function() {
            if (!isOfficeInitialized && typeof Office === 'undefined') {
                document.getElementById('testBanner').classList.add('active');
                document.getElementById('testInputCard').classList.add('active');
                runSimulation();
            }
        }, 1500);

        function loadCurrentOutlookEmail() {
            try {
                currentItem = Office.context.mailbox.item;
                if (!currentItem) {
                    showError('No active email detected in Outlook.');
                    return;
                }

                const subject = currentItem.subject || '(No Subject)';
                const senderObj = currentItem.from || {};
                const senderEmail = senderObj.emailAddress || '';
                const senderName = senderObj.displayName || senderEmail;
                const dateObj = currentItem.dateTimeCreated ? new Date(currentItem.dateTimeCreated) : new Date();

                document.getElementById('emailSubjectTitle').textContent = subject;
                document.getElementById('emailSender').textContent = senderName ? `${senderName} (${senderEmail})` : 'Unknown Sender';
                document.getElementById('emailDate').textContent = dateObj.toLocaleDateString();

                // Inspect and render attachments
                detectedAttachments = Array.isArray(currentItem.attachments) ? currentItem.attachments : [];
                renderAttachments(detectedAttachments);

                // Auto match in CRM
                fetchMatterMatch(subject, senderEmail, senderName);
            } catch (err) {
                console.error('Error reading email from Outlook:', err);
                showError('Could not read email details from Outlook: ' + err.message);
            }
        }

        function renderAttachments(attachments) {
            const countEl = document.getElementById('attachmentCountBadge');
            const listEl = document.getElementById('attachmentsList');
            const noteEl = document.getElementById('noAttachmentsNote');
            const toolbarEl = document.getElementById('attachmentFolderToolbar');

            if (!attachments || attachments.length === 0) {
                countEl.textContent = '0 files';
                listEl.style.display = 'none';
                noteEl.style.display = 'block';
                if (toolbarEl) toolbarEl.style.display = 'none';
                updateDestinationCallout();
                return;
            }

            countEl.textContent = `${attachments.length} file${attachments.length > 1 ? 's' : ''}`;
            noteEl.style.display = 'none';
            listEl.style.display = 'block';
            if (toolbarEl) toolbarEl.style.display = 'block';

            listEl.innerHTML = attachments.map((att, idx) => {
                const name = att.name || 'attachment';
                const sizeKb = att.size ? Math.round(att.size / 1024) : 0;
                const sizeStr = sizeKb > 1024 ? (sizeKb / 1024).toFixed(1) + ' MB' : sizeKb + ' KB';
                const iconClass = getFileIcon(name);
                const fileChoice = singleAttachmentFolders[idx] || selectedDefaultFolder;
                const info = getFolderInfo(fileChoice);
                const showOverride = attachments.length > 1;

                return `
                    <div class="attachment-item">
                        <div class="attachment-row-top">
                            <div class="attachment-left">
                                <i class="${iconClass}"></i>
                                <span class="attachment-filename" title="${escapeHtml(name)}">${escapeHtml(name)} <small style="color: #64748b;">(${sizeStr})</small></span>
                            </div>
                            <span class="attachment-badge" id="attFolderBadge_${idx}" style="background: ${info.badgeBg}; color: ${info.badgeColor};">
                                <i class="fa-solid ${info.storageType === 'email' ? 'fa-envelope' : 'fa-folder'}"></i>
                                <span id="attFolderText_${idx}">${escapeHtml(info.folderTitle)}</span>
                            </span>
                        </div>
                        ${showOverride ? `
                            <div class="attachment-row-bottom">
                                <span>Change folder for this file:</span>
                                <select class="single-folder-select" data-index="${idx}" onchange="handleSingleFolderChange(${idx}, this.value)">
                                    ${buildFolderSelectOptionsHtml(fileChoice)}
                                </select>
                            </div>
                        ` : ''}
                    </div>
                `;
            }).join('');

            populateFolderDropdowns(selectedDefaultFolder);
        }

        function buildFolderSelectOptionsHtml(selectedVal) {
            let html = '';

            // Optgroup 1: Matter Documents
            if (matterFoldersList && matterFoldersList.length > 0) {
                html += `<optgroup label="Matter Documents (${escapeHtml(selectedMatterName)})">`;
                matterFoldersList.forEach(f => {
                    const val = `matter:${f.id}`;
                    const sel = (val === selectedVal) ? 'selected' : '';
                    html += `<option value="${escapeHtml(val)}" ${sel}>📁 ${escapeHtml(f.title)}</option>`;
                });
                html += `</optgroup>`;
            } else {
                html += `<optgroup label="Matter Documents (${escapeHtml(selectedMatterName)})">`;
                const sel = (selectedVal === 'matter:1' || !selectedVal) ? 'selected' : '';
                html += `<option value="matter:1" ${sel}>📁 General</option>`;
                html += `</optgroup>`;
            }

            // Optgroup 2: Personal Documents
            if (personalFoldersList && personalFoldersList.length > 0) {
                html += `<optgroup label="Personal Documents">`;
                personalFoldersList.forEach(f => {
                    const val = `personal:${f.id}`;
                    const sel = (val === selectedVal) ? 'selected' : '';
                    html += `<option value="${escapeHtml(val)}" ${sel}>👤 ${escapeHtml(f.title)}</option>`;
                });
                html += `</optgroup>`;
            }

            // Optgroup 3: Email Only
            const emailSel = (selectedVal === 'email:0' || selectedVal === 'email') ? 'selected' : '';
            html += `<optgroup label="Other">`;
            html += `<option value="email:0" ${emailSel}>✉️ Email attachments only (skip documents)</option>`;
            html += `</optgroup>`;

            return html;
        }

        function populateFolderDropdowns(selectedVal) {
            const globalSelect = document.getElementById('globalFolderSelect');
            if (!globalSelect) return;

            if (!selectedVal) {
                selectedVal = selectedDefaultFolder;
                if (!selectedVal || selectedVal === '') {
                    selectedVal = (matterFoldersList.length > 0) ? `matter:${matterFoldersList[0].id}` : 'matter:1';
                }
            }
            selectedDefaultFolder = selectedVal;

            globalSelect.innerHTML = buildFolderSelectOptionsHtml(selectedDefaultFolder);
            globalSelect.value = selectedDefaultFolder;

            document.querySelectorAll('.single-folder-select').forEach(sel => {
                const idx = sel.getAttribute('data-index');
                const fileChoice = singleAttachmentFolders[idx] || selectedDefaultFolder;
                sel.innerHTML = buildFolderSelectOptionsHtml(fileChoice);
                sel.value = fileChoice;
            });

            updateFolderDisplayState();
        }

        function getFolderInfo(val) {
            if (!val || val === 'email' || val === 'email:0' || val.startsWith('email:')) {
                return {
                    storageType: 'email',
                    folderId: '0',
                    folderTitle: 'Email only',
                    kindLabel: 'Email Only',
                    badgeBg: '#f1f5f9',
                    badgeColor: '#475569'
                };
            }
            if (val.startsWith('personal:')) {
                const id = val.split(':')[1];
                const match = personalFoldersList.find(f => String(f.id) === String(id));
                const title = match ? match.title : 'General';
                return {
                    storageType: 'personal',
                    folderId: id,
                    folderTitle: title,
                    kindLabel: 'Personal Docs',
                    badgeBg: '#fef3c7',
                    badgeColor: '#92400e'
                };
            }
            // Matter folder
            const id = val.includes(':') ? val.split(':')[1] : val;
            const match = matterFoldersList.find(f => String(f.id) === String(id));
            const title = match ? match.title : (id === '1' ? 'General' : `Folder #${id}`);
            return {
                storageType: 'matter',
                folderId: id,
                folderTitle: title,
                kindLabel: 'Matter Docs',
                badgeBg: '#e0f2fe',
                badgeColor: '#0369a1'
            };
        }

        function handleGlobalFolderChange(val) {
            selectedDefaultFolder = val;
            if (Array.isArray(detectedAttachments)) {
                detectedAttachments.forEach((_, idx) => {
                    singleAttachmentFolders[idx] = val;
                    const singleSel = document.querySelector(`.single-folder-select[data-index="${idx}"]`);
                    if (singleSel) singleSel.value = val;
                });
            }
            updateFolderDisplayState();
        }

        function handleSingleFolderChange(idx, val) {
            singleAttachmentFolders[idx] = val;
            updateFolderDisplayState();
        }

        function updateFolderDisplayState() {
            const kindBadge = document.getElementById('folderKindBadge');
            const info = getFolderInfo(selectedDefaultFolder);
            if (kindBadge) {
                kindBadge.textContent = info.kindLabel;
                kindBadge.style.background = info.badgeBg;
                kindBadge.style.color = info.badgeColor;
            }

            if (Array.isArray(detectedAttachments)) {
                detectedAttachments.forEach((_, idx) => {
                    const fileChoice = singleAttachmentFolders[idx] || selectedDefaultFolder;
                    const fileInfo = getFolderInfo(fileChoice);
                    const badgeEl = document.getElementById(`attFolderBadge_${idx}`);
                    const textEl = document.getElementById(`attFolderText_${idx}`);
                    if (badgeEl && textEl) {
                        textEl.textContent = fileInfo.folderTitle;
                        badgeEl.style.background = fileInfo.badgeBg;
                        badgeEl.style.color = fileInfo.badgeColor;
                        const iconEl = badgeEl.querySelector('i');
                        if (iconEl) {
                            iconEl.className = `fa-solid ${fileInfo.storageType === 'email' ? 'fa-envelope' : 'fa-folder'}`;
                        }
                    }
                });
            }

            updateDestinationCallout();
        }

        function updateDestinationCallout() {
            const destEmail = document.getElementById('destMatterEmail');
            const destDocs = document.getElementById('destMatterDocs');
            if (destEmail) destEmail.textContent = selectedMatterName;

            if (destDocs) {
                if (!detectedAttachments || detectedAttachments.length === 0) {
                    destDocs.innerHTML = `${escapeHtml(selectedMatterName)} &rarr; <em>Matter Documents Tab</em>`;
                    return;
                }

                const info = getFolderInfo(selectedDefaultFolder);
                if (info.storageType === 'email') {
                    destDocs.innerHTML = `<em>Email attachments only (not saved to documents tab)</em>`;
                } else if (info.storageType === 'personal') {
                    destDocs.innerHTML = `<strong>Personal Documents</strong> &rarr; <em>${escapeHtml(info.folderTitle)}</em>`;
                } else {
                    destDocs.innerHTML = `${escapeHtml(selectedMatterName)} &rarr; <strong>Matter Documents</strong> &rarr; <em>${escapeHtml(info.folderTitle)}</em>`;
                }
            }
        }

        function getFileIcon(filename) {
            const ext = (filename || '').split('.').pop().toLowerCase();
            if (['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg'].includes(ext)) return 'fa-solid fa-file-image';
            if (ext === 'pdf') return 'fa-solid fa-file-pdf';
            if (['doc', 'docx'].includes(ext)) return 'fa-solid fa-file-word';
            if (['xls', 'xlsx', 'csv'].includes(ext)) return 'fa-solid fa-file-excel';
            if (['zip', 'rar', '7z'].includes(ext)) return 'fa-solid fa-file-zipper';
            return 'fa-solid fa-paperclip';
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function updateDestinationLabels(matterName) {
            updateDestinationCallout();
        }

        function runSimulation() {
            const subject = document.getElementById('simSubject').value;
            const sender = document.getElementById('simSender').value;
            document.getElementById('emailSubjectTitle').textContent = subject;
            document.getElementById('emailSender').textContent = sender || 'simulated_sender@example.com';
            document.getElementById('emailDate').textContent = new Date().toLocaleDateString();

            matterFoldersList = [
                { id: '1', title: 'General' },
                { id: '2', title: 'Authority to Act' },
                { id: '6', title: 'Employment' },
                { id: '8', title: 'Criminal Charge' }
            ];
            personalFoldersList = [
                { id: '1', title: 'General' },
                { id: '51', title: 'ACCOUNTS' },
                { id: '52', title: 'AUTHORITY TO ACT' }
            ];

            detectedAttachments = [
                { id: 'mock_1', name: 'Contract_Document.pdf', size: 145000, isInline: false, contentType: 'application/pdf' }
            ];
            renderAttachments(detectedAttachments);

            fetchMatterMatch(subject, sender, 'Simulated Sender');
        }

        async function fetchMatterMatch(subject, senderEmail, senderName) {
            document.getElementById('matchingSpinner').style.display = 'block';
            document.getElementById('matterTargetBox').style.display = 'none';
            document.getElementById('noMatchBox').style.display = 'none';
            document.getElementById('btnSaveToCrm').disabled = true;

            try {
                const res = await fetch(`${BASE_URL}/outlook-addin/match`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        subject: subject,
                        from_email: senderEmail,
                        from_name: senderName
                    })
                });

                const data = await res.json();
                document.getElementById('matchingSpinner').style.display = 'none';

                if (data.success && data.matched && data.best) {
                    applySelectedMatter(data.best, true);
                } else {
                    document.getElementById('noMatchBox').style.display = 'block';
                }
            } catch (e) {
                document.getElementById('matchingSpinner').style.display = 'none';
                document.getElementById('noMatchBox').style.display = 'block';
                console.warn('Match fetch error:', e);
            }
        }

        function applySelectedMatter(matter, isAuto = false) {
            isAutoMatched = !!isAuto;
            selectedClientId = matter.client_id;
            selectedMatterId = matter.client_matter_id || 0;
            const isPersonOnly = !selectedMatterId;
            const isLeadTarget = matter.record_type === 'lead';
            const personLabel = isLeadTarget ? 'Lead' : 'Client';
            selectedMatterName = matter.matter_no
                || (selectedMatterId ? ('Matter #' + selectedMatterId) : personLabel);

            document.getElementById('matchedMatterNo').textContent = selectedMatterName;
            document.getElementById('matchedClientInfo').textContent = isPersonOnly
                ? `${matter.client_ref} — ${matter.client_name} (${personLabel})`
                : `${matter.client_ref} — ${matter.client_name}`;

            const destEmail = document.getElementById('destMatterEmail');
            const destDocs = document.getElementById('destMatterDocs');
            const saveBtnTextEl = document.getElementById('saveBtnText');
            if (destEmail) {
                destEmail.textContent = isPersonOnly ? `Selected ${personLabel}` : 'Selected Matter';
            }
            if (destDocs) {
                destDocs.textContent = isPersonOnly
                    ? `Selected ${personLabel} → Personal Documents`
                    : 'Selected Matter → Matter Documents';
            }
            if (saveBtnTextEl && !saveBtnTextEl.textContent.includes('...')) {
                saveBtnTextEl.textContent = isPersonOnly
                    ? `Save to CRM ${personLabel}`
                    : 'Save to CRM Matter';
            }

            const badgeContainer = document.getElementById('assignmentBadgeContainer');
            const confidenceSub = document.getElementById('matchConfidenceSub');

            if (isAutoMatched) {
                if (badgeContainer) {
                    badgeContainer.innerHTML = `
                        <span class="email-client-badge email-client-badge--auto">
                            <i class="fa-solid fa-wand-magic-sparkles"></i> Auto assigned
                        </span>
                    `;
                }
                if (confidenceSub) {
                    if (matter.confidence) {
                        confidenceSub.textContent = `${matter.confidence}% match`;
                        confidenceSub.style.display = 'inline-block';
                    } else {
                        confidenceSub.style.display = 'none';
                    }
                }
            } else {
                if (badgeContainer) {
                    badgeContainer.innerHTML = outlookAddinBadgeHtml('Outlook add-in');
                }
                if (confidenceSub) {
                    confidenceSub.style.display = 'none';
                }
            }

            // Load folders
            if (Array.isArray(matter.matter_folders) && matter.matter_folders.length > 0) {
                matterFoldersList = matter.matter_folders;
                personalFoldersList = Array.isArray(matter.personal_folders) ? matter.personal_folders : [];
                selectedDefaultFolder = `matter:${matterFoldersList[0].id}`;
                populateFolderDropdowns(selectedDefaultFolder);
            } else if (isPersonOnly && Array.isArray(matter.personal_folders) && matter.personal_folders.length > 0) {
                matterFoldersList = [];
                personalFoldersList = matter.personal_folders;
                selectedDefaultFolder = `personal:${personalFoldersList[0].id}`;
                populateFolderDropdowns(selectedDefaultFolder);
            } else {
                fetchMatterFolders(selectedClientId, selectedMatterId);
            }

            document.getElementById('matterTargetBox').style.display = 'block';
            document.getElementById('noMatchBox').style.display = 'none';
            document.getElementById('btnSaveToCrm').disabled = false;

            toggleSearchDrawer(false);
        }

        async function fetchMatterFolders(clientId, matterId) {
            try {
                const res = await fetch(`${BASE_URL}/outlook-addin/folders?client_id=${clientId}&client_matter_id=${matterId}`);
                const data = await res.json();
                if (data.success) {
                    matterFoldersList = data.matter_folders || [];
                    personalFoldersList = data.personal_folders || [];
                    let defaultVal = 'email:0';
                    if (matterFoldersList.length > 0 && Number(matterId) > 0) {
                        defaultVal = `matter:${matterFoldersList[0].id}`;
                    } else if (personalFoldersList.length > 0) {
                        defaultVal = `personal:${personalFoldersList[0].id}`;
                    } else if (Number(matterId) > 0) {
                        defaultVal = 'matter:1';
                    }
                    populateFolderDropdowns(defaultVal);
                }
            } catch (e) {
                console.warn('Folder fetch error:', e);
            }
        }

        function toggleSearchDrawer(forceOpen) {
            const drawer = document.getElementById('searchDrawer');
            if (forceOpen === true) {
                drawer.classList.add('open');
                document.getElementById('matterSearchInput').focus();
            } else if (forceOpen === false) {
                drawer.classList.remove('open');
            } else {
                drawer.classList.toggle('open');
                if (drawer.classList.contains('open')) {
                    document.getElementById('matterSearchInput').focus();
                }
            }
        }

        let searchDebounceTimer = null;
        function handleMatterSearch(query) {
            clearTimeout(searchDebounceTimer);
            searchDebounceTimer = setTimeout(async () => {
                const resultsEl = document.getElementById('matterResultsList');
                const q = String(query || '').trim();
                if (q.length < 2) {
                    resultsEl.innerHTML = '<div style="padding: 10px; text-align: center; color: #94a3b8;">Type at least 2 characters to search clients, leads, or matters.</div>';
                    return;
                }

                resultsEl.innerHTML = '<div style="padding: 10px; text-align: center; color: #94a3b8;"><i class="fa-solid fa-spinner fa-spin"></i> Searching...</div>';

                try {
                    const res = await fetch(`${BASE_URL}/outlook-addin/matters?q=${encodeURIComponent(q)}`);
                    const data = await res.json();

                    if (!data.matters || !data.matters.length) {
                        resultsEl.innerHTML = '<div style="padding: 10px; text-align: center; color: #94a3b8;">No clients, leads, or matters found.</div>';
                        return;
                    }

                    resultsEl.innerHTML = data.matters.map(m => {
                        const isPersonOnly = !m.client_matter_id;
                        const personLabel = m.record_type === 'lead' ? 'Lead' : 'Client';
                        const title = m.matter_no
                            ? `${m.client_ref} / ${m.matter_no}`
                            : `${m.client_ref} — ${m.client_name}`;
                        const sub = isPersonOnly
                            ? `${m.client_name} • ${personLabel}${m.matter_title ? ' • ' + m.matter_title : ''}`
                            : `${m.client_name} • ${m.matter_title || 'Matter'}`;
                        return `
                        <div class="matter-result-item" onclick='applySelectedMatter(${JSON.stringify(m)}, false)'>
                            <div class="m-title">${title}</div>
                            <div class="m-sub">${sub}</div>
                        </div>`;
                    }).join('');
                } catch (e) {
                    resultsEl.innerHTML = '<div style="padding: 10px; text-align: center; color: #ef4444;">Search failed.</div>';
                }
            }, 250);
        }

        function setMailType(type) {
            selectedMailType = type;
            document.getElementById('btnFolderInbox').classList.toggle('active', type === 'inbox');
            document.getElementById('btnFolderSent').classList.toggle('active', type === 'sent');
        }

        // Helper: Promisified body fetch with timeout
        function getEmailBodyAsync(item) {
            return new Promise((resolve) => {
                if (!item || !item.body || typeof item.body.getAsync !== 'function') {
                    resolve('<p>(No email body)</p>');
                    return;
                }

                const timer = setTimeout(() => {
                    console.warn('Body fetch timed out, falling back.');
                    resolve('<p>(Body extraction timed out)</p>');
                }, 4000);

                try {
                    item.body.getAsync(Office.CoercionType.Html, function (result) {
                        clearTimeout(timer);
                        if (result && result.status === Office.AsyncResultStatus.Succeeded) {
                            resolve(result.value || '<p>(No content)</p>');
                        } else {
                            resolve('<p>(No content)</p>');
                        }
                    });
                } catch (e) {
                    clearTimeout(timer);
                    resolve('<p>(Error reading body)</p>');
                }
            });
        }

        // Helper: Extract all attachments base64 in parallel with timeout
        async function extractAllAttachmentsAsync(item) {
            if (!item || !Array.isArray(item.attachments) || item.attachments.length === 0) {
                return [];
            }

            const extracted = [];
            for (let i = 0; i < item.attachments.length; i++) {
                const att = item.attachments[i];
                try {
                    const base64Content = await getSingleAttachmentContentAsync(item, att.id);
                    if (base64Content) {
                        extracted.push({
                            name: att.name || `attachment_${i + 1}`,
                            content_type: att.contentType || 'application/octet-stream',
                            size: att.size || 0,
                            is_inline: !!att.isInline,
                            content_base64: base64Content
                        });
                    }
                } catch (attErr) {
                    console.warn(`Could not read attachment ${att.name}:`, attErr);
                }
            }
            return extracted;
        }

        function getSingleAttachmentContentAsync(item, attachmentId) {
            return new Promise((resolve) => {
                if (!item || typeof item.getAttachmentContentAsync !== 'function') {
                    resolve(null);
                    return;
                }

                const timer = setTimeout(() => {
                    console.warn(`Attachment ${attachmentId} fetch timed out.`);
                    resolve(null);
                }, 4000);

                try {
                    item.getAttachmentContentAsync(attachmentId, function (result) {
                        clearTimeout(timer);
                        if (result && result.status === Office.AsyncResultStatus.Succeeded && result.value) {
                            resolve(result.value.content || null);
                        } else {
                            resolve(null);
                        }
                    });
                } catch (e) {
                    clearTimeout(timer);
                    resolve(null);
                }
            });
        }

        // Main Save Function
        async function saveEmailToCrm() {
            if (!selectedClientId) {
                showError('Please select a client or matter first.');
                return;
            }

            const saveBtn = document.getElementById('btnSaveToCrm');
            const saveBtnText = document.getElementById('saveBtnText');
            saveBtn.disabled = true;
            saveBtnText.textContent = 'Reading email...';
            hideAlert();

            const staffId = document.getElementById('staffSelect').value;

            try {
                let bodyHtml = '';
                let attachmentsPayload = [];
                let subject = document.getElementById('emailSubjectTitle').textContent;
                let fromEmail = '';
                let fromName = '';
                let toRecipients = '';
                let dateStr = new Date().toISOString();
                let internetMessageId = '';

                if (isOfficeInitialized && currentItem) {
                    // Step 1: Read Body
                    bodyHtml = await getEmailBodyAsync(currentItem);

                    // Step 2: Read Attachments
                    if (Array.isArray(currentItem.attachments) && currentItem.attachments.length > 0) {
                        saveBtnText.textContent = `Reading attachments (${currentItem.attachments.length})...`;
                        attachmentsPayload = await extractAllAttachmentsAsync(currentItem);
                    }

                    // Metadata
                    subject = currentItem.subject || subject;
                    if (currentItem.from) {
                        fromEmail = currentItem.from.emailAddress || '';
                        fromName = currentItem.from.displayName || fromEmail;
                    }
                    if (Array.isArray(currentItem.to)) {
                        toRecipients = currentItem.to.map(r => r.emailAddress || '').filter(Boolean).join('; ');
                    }
                    if (currentItem.dateTimeCreated) {
                        dateStr = new Date(currentItem.dateTimeCreated).toISOString();
                    }
                    internetMessageId = currentItem.internetMessageId || '';
                } else {
                    // Standalone test simulation
                    bodyHtml = '<p>This is a simulated email body imported from BansalLaw CRM Outlook Add-in local test.</p>';
                    fromEmail = document.getElementById('emailSender').textContent;
                    fromName = 'Test Sender';
                    toRecipients = 'office@bansallawyers.com.au';
                    if (detectedAttachments.length > 0) {
                        // Mock 1 sample base64 attachment for test
                        attachmentsPayload = [
                            {
                                name: 'Test_Document.pdf',
                                content_type: 'application/pdf',
                                size: 1024,
                                is_inline: false,
                                content_base64: 'JVBERi0xLjQKJcTl8uXrp/Og...=='
                            }
                        ];
                    }
                }

                // Build attachment storage mapping for each file
                const attachmentStoragePayload = detectedAttachments.map((att, idx) => {
                    const choice = singleAttachmentFolders[idx] || selectedDefaultFolder || 'matter:1';
                    const info = getFolderInfo(choice);
                    return {
                        original_filename: att.name || 'attachment',
                        filename: att.name || 'attachment',
                        storage_type: info.storageType,
                        folder_id: info.folderId
                    };
                });

                // Step 3: Send to CRM
                saveBtnText.textContent = 'Saving to CRM...';

                const payload = {
                    client_id: selectedClientId,
                    client_matter_id: selectedMatterId,
                    mail_type: selectedMailType,
                    staff_id: staffId,
                    is_auto_matched: isAutoMatched,
                    assignment_type: isAutoMatched ? 'auto' : 'manual',
                    assignment_tag: isAutoMatched ? 'Auto assigned' : 'Manual upload',
                    subject: subject,
                    body_html: bodyHtml,
                    from_email: fromEmail,
                    from_name: fromName,
                    to_recipients: toRecipients,
                    date: dateStr,
                    internet_message_id: internetMessageId,
                    attachments: attachmentsPayload,
                    attachment_storage: attachmentStoragePayload,
                    default_attachment_folder: selectedDefaultFolder
                };

                const res = await fetch(`${BASE_URL}/outlook-addin/save`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });

                const data = await res.json();

                saveBtn.disabled = false;
                saveBtnText.textContent = 'Save to CRM Matter';

                if (data.success) {
                    const countMsg = data.attachment_count > 0 ? ` with ${data.attachment_count} attachment(s)` : '';
                    const linkHtml = data.detail_url ? `<br><a href="${data.detail_url}" target="_blank"><i class="fa-solid fa-arrow-up-right-from-square"></i> Open Matter Emails in CRM</a>` : '';
                    const tagLabel = data.assignment_tag || 'Outlook add-in';
                    const assignedTagHtml = outlookAddinBadgeHtml(tagLabel, 'margin-left: 4px; vertical-align: middle;');

                    const badgeContainer = document.getElementById('assignmentBadgeContainer');
                    if (badgeContainer) {
                        badgeContainer.innerHTML = outlookAddinBadgeHtml(tagLabel);
                    }

                    showSuccess(`<strong>Saved!</strong> Email${countMsg} successfully saved to matter <strong>${selectedMatterName}</strong> as ${assignedTagHtml}.${linkHtml}`);
                    showSuccessModal(
                        'Moved successfully',
                        `Email saved to <strong>${selectedMatterName}</strong> and tagged as <strong>${tagLabel}</strong> in CRM.`,
                        data.detail_url || ''
                    );
                    // Brief success flash, then close the Outlook add-in pane.
                    setTimeout(function () {
                        closeOutlookTaskpane();
                    }, 1400);
                } else if (data.already_exists || data.error_code === 'existing_email' || data.error_code === 'unassigned_match') {
                    const dupMsg = data.error || 'Already exists — this email is already in CRM and was not uploaded again.';
                    showError(dupMsg);
                    showAlreadyExistsModal(dupMsg, data.detail_url || '');
                } else {
                    showError(data.error || 'Failed to save email to CRM.');
                }

            } catch (err) {
                console.error('Save error:', err);
                saveBtn.disabled = false;
                saveBtnText.textContent = 'Save to CRM Matter';
                showError('Save failed: ' + (err.message || 'Network error'));
                try {
                    fetch(`${BASE_URL}/outlook-addin/log`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            level: 'error',
                            action: 'Client Taskpane Save Error',
                            context: {
                                error: err.message || String(err),
                                client_id: selectedClientId,
                                client_matter_id: selectedMatterId,
                                staff_id: document.getElementById('staffSelect') ? document.getElementById('staffSelect').value : null,
                            }
                        })
                    }).catch(() => {});
                } catch(e) {}
            }
        }

        function showSuccess(msg) {
            const el = document.getElementById('feedbackAlert');
            el.className = 'alert-box success active';
            el.innerHTML = `<i class="fa-solid fa-circle-check" style="margin-right: 5px;"></i> ${msg}`;
        }

        function showError(msg) {
            const el = document.getElementById('feedbackAlert');
            el.className = 'alert-box error active';
            el.innerHTML = `<i class="fa-solid fa-circle-exclamation" style="margin-right: 5px;"></i> ${msg}`;
        }

        function hideAlert() {
            const el = document.getElementById('feedbackAlert');
            el.className = 'alert-box';
            el.innerHTML = '';
        }

        function showSuccessModal(title, bodyHtml, detailUrl) {
            const overlay = document.getElementById('successModalOverlay');
            const titleEl = document.getElementById('successModalTitle');
            const bodyEl = document.getElementById('successModalBody');
            const iconEl = document.getElementById('successModalIcon');
            const btnEl = document.getElementById('successModalOkBtn');
            const linkEl = document.getElementById('successModalLink');
            if (!overlay || !titleEl || !bodyEl) {
                return;
            }
            titleEl.textContent = title || 'Saved to CRM';
            bodyEl.innerHTML = bodyHtml || 'Email moved successfully.';
            if (iconEl) {
                iconEl.className = 'success-modal__icon';
                iconEl.innerHTML = '<i class="fa-solid fa-circle-check"></i>';
            }
            if (btnEl) {
                btnEl.className = 'success-modal__btn';
                btnEl.textContent = 'OK';
                btnEl.onclick = function () { closeOutlookTaskpane(); };
            }
            if (linkEl) {
                if (detailUrl) {
                    linkEl.href = detailUrl;
                    linkEl.style.display = 'inline-block';
                    linkEl.textContent = 'Open Matter Emails in CRM';
                } else {
                    linkEl.style.display = 'none';
                }
            }
            overlay.classList.add('active');
        }

        function showAlreadyExistsModal(message, detailUrl) {
            const overlay = document.getElementById('successModalOverlay');
            const titleEl = document.getElementById('successModalTitle');
            const bodyEl = document.getElementById('successModalBody');
            const iconEl = document.getElementById('successModalIcon');
            const btnEl = document.getElementById('successModalOkBtn');
            const linkEl = document.getElementById('successModalLink');
            if (!overlay || !titleEl || !bodyEl) {
                return;
            }
            titleEl.textContent = 'Already exists';
            bodyEl.textContent = message || 'This email is already in CRM and was not uploaded again.';
            if (iconEl) {
                iconEl.className = 'success-modal__icon success-modal__icon--warn';
                iconEl.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i>';
            }
            if (btnEl) {
                btnEl.className = 'success-modal__btn success-modal__btn--warn';
                btnEl.textContent = 'OK';
                btnEl.onclick = function () { hideSuccessModal(); };
            }
            if (linkEl) {
                if (detailUrl) {
                    linkEl.href = detailUrl;
                    linkEl.style.display = 'inline-block';
                    linkEl.textContent = 'Open existing email in CRM';
                } else {
                    linkEl.style.display = 'none';
                }
            }
            overlay.classList.add('active');
        }

        function hideSuccessModal() {
            const overlay = document.getElementById('successModalOverlay');
            if (overlay) {
                overlay.classList.remove('active');
            }
        }

        function closeOutlookTaskpane() {
            hideSuccessModal();
            try {
                if (typeof Office !== 'undefined'
                    && Office.context
                    && Office.context.ui
                    && typeof Office.context.ui.closeContainer === 'function') {
                    Office.context.ui.closeContainer();
                    return;
                }
            } catch (e) {
                console.warn('closeContainer failed:', e);
            }
            // Fallback for hosts that do not support closeContainer.
            try {
                window.close();
            } catch (e2) {}
        }
    </script>
</body>
</html>
