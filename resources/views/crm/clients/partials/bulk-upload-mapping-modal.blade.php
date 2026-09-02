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
