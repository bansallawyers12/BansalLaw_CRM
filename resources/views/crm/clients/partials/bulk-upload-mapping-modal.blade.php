{{-- Shared by Personal documents + Matter documents bulk upload (must exist even when only one tab is SSR'd). --}}
<style>
    .bulk-upload-mapping-modal {
        display: none;
        position: fixed;
        z-index: 10000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(30, 61, 96, 0.35);
    }

    .bulk-upload-mapping-content {
        background-color: var(--card-bg, #fff);
        margin: 5% auto;
        padding: 20px;
        border: 1px solid var(--border, #c8dcef);
        border-radius: 10px;
        width: 90%;
        max-width: 900px;
        max-height: 80vh;
        overflow-y: auto;
        box-shadow: 0 4px 24px rgba(30, 61, 96, 0.12);
    }

    .bulk-upload-mapping-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 1px solid var(--border, #c8dcef);
    }

    .bulk-upload-mapping-header h3 {
        margin: 0;
        color: var(--navy, #1e3d60);
        font-weight: 700;
    }

    .close-mapping-modal {
        color: var(--text-muted, #5e7a90);
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
    }

    .close-mapping-modal:hover {
        color: var(--navy, #1e3d60);
    }

    .bulk-upload-actions {
        margin-top: 20px;
        padding-top: 15px;
        border-top: 1px solid var(--border, #c8dcef);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .bulk-upload-progress {
        display: none;
        margin-top: 15px;
    }

    .bulk-upload-mapping-modal .progress-bar-container {
        width: 100%;
        height: 25px;
        background-color: var(--page-bg, #f0f6ff);
        border-radius: 6px;
        overflow: hidden;
        border: 1px solid var(--border, #c8dcef);
    }

    .bulk-upload-mapping-modal .progress-bar {
        height: 100%;
        background-color: var(--sidebar-active, #3a6fa8);
        width: 0%;
        transition: width 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        font-size: 12px;
    }

    /* Shared upload progress overlay (personal + matter bulk / video uploads) */
    .personal-video-upload-overlay {
        display: none;
        position: fixed;
        inset: 0;
        z-index: 10050;
        background: rgba(15, 32, 52, 0.55);
        backdrop-filter: blur(4px);
        align-items: center;
        justify-content: center;
        padding: 20px;
    }

    .personal-video-upload-overlay.is-visible {
        display: flex;
    }

    .personal-video-upload-panel {
        width: 100%;
        max-width: 440px;
        background: #fff;
        border-radius: 14px;
        box-shadow: 0 20px 50px rgba(15, 32, 52, 0.25);
        padding: 28px 26px 24px;
        text-align: center;
        animation: pvuPanelIn 0.35s ease;
    }

    @keyframes pvuPanelIn {
        from { opacity: 0; transform: translateY(12px) scale(0.98); }
        to { opacity: 1; transform: translateY(0) scale(1); }
    }

    .pvu-icon-wrap {
        position: relative;
        width: 64px;
        height: 64px;
        margin: 0 auto 14px;
    }

    .pvu-main-icon {
        font-size: 28px;
        color: var(--sidebar-active, #3a6fa8);
        line-height: 64px;
    }

    .pvu-spinner-ring {
        position: absolute;
        inset: 0;
        border: 3px solid rgba(58, 111, 168, 0.15);
        border-top-color: var(--sidebar-active, #3a6fa8);
        border-radius: 50%;
        animation: pvuSpin 1s linear infinite;
    }

    @keyframes pvuSpin {
        to { transform: rotate(360deg); }
    }

    .pvu-title {
        margin: 0 0 6px;
        font-size: 1.15rem;
        font-weight: 600;
        color: var(--navy, #1e3d60);
    }

    .pvu-filename {
        margin: 0 0 4px;
        font-size: 14px;
        font-weight: 600;
        color: var(--text-dark, #1a2c40);
        word-break: break-word;
    }

    .pvu-meta {
        margin: 0 0 12px;
        font-size: 12px;
        color: var(--text-muted, #5e7a90);
    }

    .pvu-status {
        margin: 0 0 16px;
        font-size: 13px;
        color: var(--text-muted, #5e7a90);
        min-height: 20px;
    }

    .pvu-progress-wrap {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 22px;
    }

    .pvu-progress-track {
        flex: 1;
        height: 10px;
        background: var(--page-bg, #eef4fb);
        border-radius: 999px;
        overflow: hidden;
        border: 1px solid var(--border, #c8dcef);
    }

    .pvu-progress-bar {
        height: 100%;
        width: 0%;
        border-radius: 999px;
        background: linear-gradient(90deg, #3a6fa8 0%, #5a9fd4 100%);
        transition: width 0.35s ease;
    }

    .pvu-percent {
        font-size: 13px;
        font-weight: 700;
        color: var(--sidebar-active, #3a6fa8);
        min-width: 38px;
        text-align: right;
    }

    .pvu-timeline {
        list-style: none;
        margin: 0;
        padding: 0;
        display: flex;
        justify-content: space-between;
        position: relative;
    }

    .pvu-timeline::before {
        content: '';
        position: absolute;
        top: 18px;
        left: 12%;
        right: 12%;
        height: 3px;
        background: var(--border, #c8dcef);
        z-index: 0;
    }

    .pvu-step {
        position: relative;
        z-index: 1;
        flex: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 8px;
    }

    .pvu-step-marker {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: #fff;
        border: 2px solid var(--border, #c8dcef);
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--text-muted, #5e7a90);
        font-size: 14px;
        transition: all 0.3s ease;
    }

    .pvu-step-label {
        font-size: 11px;
        font-weight: 600;
        color: var(--text-muted, #5e7a90);
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }

    .pvu-step.active .pvu-step-marker {
        border-color: var(--sidebar-active, #3a6fa8);
        background: rgba(58, 111, 168, 0.1);
        color: var(--sidebar-active, #3a6fa8);
        box-shadow: 0 0 0 4px rgba(58, 111, 168, 0.12);
    }

    .pvu-step.active .pvu-step-label {
        color: var(--sidebar-active, #3a6fa8);
    }

    .pvu-step.done .pvu-step-marker {
        border-color: var(--success, #1e7a52);
        background: var(--success, #1e7a52);
        color: #fff;
    }

    .pvu-step.done .pvu-step-label {
        color: var(--success, #1e7a52);
    }

    .pvu-step.error .pvu-step-marker {
        border-color: #dc3545;
        background: #dc3545;
        color: #fff;
    }

    .pvu-step.error .pvu-step-label {
        color: #dc3545;
    }

    .pvu-step.active[data-step="processing"] .pvu-step-marker i {
        animation: pvuSpin 1.2s linear infinite;
    }

    .personal-video-upload-panel.is-success .pvu-spinner-ring {
        border-top-color: var(--success, #1e7a52);
        animation: none;
        border-color: rgba(30, 122, 82, 0.25);
    }

    .personal-video-upload-panel.is-error .pvu-spinner-ring {
        display: none;
    }
</style>

<div id="bulk-upload-mapping-modal" class="bulk-upload-mapping-modal">
    <div class="bulk-upload-mapping-content">
        <div class="bulk-upload-mapping-header">
            <h3><i class="fa-solid fa-link"></i> Map Files to Checklists</h3>
            <span class="close-mapping-modal" role="button" tabindex="0" aria-label="Close">&times;</span>
        </div>
        <div id="bulk-upload-mapping-table"></div>
        <div class="bulk-upload-actions">
            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                <input type="checkbox" id="auto-create-unmatched" checked>
                <span>Auto-create checklist for unmatched files</span>
            </label>
            <div>
                <button type="button" class="btn btn-secondary" id="cancel-bulk-upload">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirm-bulk-upload">Upload All</button>
            </div>
        </div>
        <div class="bulk-upload-progress" id="bulk-upload-progress">
            <p>Uploading files...</p>
            <div class="progress-bar-container">
                <div class="progress-bar" id="bulk-upload-progress-bar">0%</div>
            </div>
        </div>
    </div>
</div>

{{-- Always in DOM so matter-only lazy tabs still get a bulk-upload loader --}}
<div id="personalVideoUploadOverlay" class="personal-video-upload-overlay" aria-hidden="true">
    <div class="personal-video-upload-panel" role="dialog" aria-labelledby="pvuTitle" aria-live="polite">
        <div class="pvu-icon-wrap">
            <i class="fa-solid fa-cloud-arrow-up pvu-main-icon" id="pvuMainIcon"></i>
            <span class="pvu-spinner-ring"></span>
        </div>
        <h4 id="pvuTitle" class="pvu-title">Uploading Files</h4>
        <p class="pvu-filename" id="pvuFilename">file.pdf</p>
        <p class="pvu-meta" id="pvuMeta"></p>
        <p class="pvu-status" id="pvuStatusMessage">Preparing upload…</p>
        <div class="pvu-progress-wrap">
            <div class="pvu-progress-track">
                <div class="pvu-progress-bar" id="pvuProgressBar"></div>
            </div>
            <span class="pvu-percent" id="pvuPercent">0%</span>
        </div>
        <ol class="pvu-timeline">
            <li class="pvu-step" data-step="upload">
                <span class="pvu-step-marker"><i class="fa-solid fa-cloud-arrow-up"></i></span>
                <span class="pvu-step-label">Upload</span>
            </li>
            <li class="pvu-step" data-step="queued">
                <span class="pvu-step-marker"><i class="fa-solid fa-layer-group"></i></span>
                <span class="pvu-step-label">Queued</span>
            </li>
            <li class="pvu-step" data-step="processing">
                <span class="pvu-step-marker"><i class="fa-solid fa-gear"></i></span>
                <span class="pvu-step-label">Processing</span>
            </li>
            <li class="pvu-step" data-step="complete">
                <span class="pvu-step-marker"><i class="fa-solid fa-check"></i></span>
                <span class="pvu-step-label">Done</span>
            </li>
        </ol>
    </div>
</div>

<script>
(function ($) {
    if (!$) {
        return;
    }

    function hideBulkUploadModal() {
        $('#bulk-upload-mapping-modal').hide();
        $('#bulk-upload-progress').hide();
        $('#bulk-upload-mapping-table').empty();
        $('#confirm-bulk-upload').prop('disabled', false);
        window._bulkUploadConfirmFn = null;
        window._bulkUploadOnCancel = null;
    }

    window.hideBulkUploadModal = hideBulkUploadModal;

    /** Decode HTML entities from data attributes (handles legacy double-encoding). */
    window.decodeBulkUploadLabel = function (value) {
        var text = String(value == null ? '' : value);
        if (!text) {
            return '';
        }
        var textarea = document.createElement('textarea');
        textarea.innerHTML = text;
        var once = textarea.value;
        if (once.indexOf('&') !== -1 && /&(amp|quot|#0?39|#x27|apos);/i.test(once)) {
            textarea.innerHTML = once;
            return String(textarea.value || '').trim();
        }
        return String(once || '').trim();
    };

    $(function () {
        $('#confirm-bulk-upload').off('click.bulkUploadShared').on('click.bulkUploadShared', function () {
            if (typeof window._bulkUploadConfirmFn === 'function') {
                window._bulkUploadConfirmFn();
            } else {
                crmAlert('Please select files to upload first.');
            }
        });

        $(document).off('click.bulkUploadModal', '.close-mapping-modal, #cancel-bulk-upload').on('click.bulkUploadModal', '.close-mapping-modal, #cancel-bulk-upload', function () {
            if (typeof window._bulkUploadOnCancel === 'function') {
                window._bulkUploadOnCancel();
            }
            hideBulkUploadModal();
        });
    });
})(window.jQuery);
</script>
