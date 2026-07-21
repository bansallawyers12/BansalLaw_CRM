<!-- Two-step email delete confirmation (shared by Outlook + legacy email UI) -->
<div class="email-delete-modal-overlay outlook-modal-overlay" id="emailDeleteConfirmModal" aria-hidden="true">
    <div class="email-delete-modal outlook-ui-modal outlook-ui-modal--md" role="dialog" aria-labelledby="emailDeleteModalTitle" aria-modal="true">
        <div class="email-delete-modal__step email-delete-modal__step--1" data-step="1">
            <div class="outlook-ui-modal__header outlook-ui-modal__header--warn">
                <div class="outlook-ui-modal__header-main">
                    <div class="outlook-ui-modal__header-icon" aria-hidden="true">
                        <i class="fa-solid fa-trash-can"></i>
                    </div>
                    <div class="outlook-ui-modal__header-text">
                        <p class="email-delete-modal__step-label">Step 1 of 2</p>
                        <h3 class="outlook-ui-modal__title" id="emailDeleteModalTitle">Delete this email?</h3>
                        <p class="outlook-ui-modal__subtitle">This will remove the email from the CRM.</p>
                    </div>
                </div>
            </div>
            <div class="outlook-ui-modal__body">
                <p class="email-delete-modal__lead">Linked attachments will be deleted as well.</p>
                <div class="outlook-ui-modal__preview-card email-delete-modal__preview" id="emailDeletePreviewStep1">
                    <div class="email-delete-modal__preview-row">
                        <span class="outlook-ui-modal__preview-label">Subject</span>
                        <span class="email-delete-modal__preview-value" id="emailDeletePreviewSubject1">—</span>
                    </div>
                    <div class="email-delete-modal__preview-row">
                        <span class="outlook-ui-modal__preview-label">From</span>
                        <span class="email-delete-modal__preview-value" id="emailDeletePreviewFrom1">—</span>
                    </div>
                    <div class="email-delete-modal__preview-row" id="emailDeletePreviewAttachmentsRow1" hidden>
                        <span class="outlook-ui-modal__preview-label">Attachments</span>
                        <span class="email-delete-modal__preview-value" id="emailDeletePreviewAttachments1">—</span>
                    </div>
                </div>
            </div>
            <div class="outlook-ui-modal__footer">
                <button type="button" class="outlook-ui-modal__btn outlook-ui-modal__btn--cancel" id="emailDeleteCancelBtn">Cancel</button>
                <button type="button" class="outlook-ui-modal__btn outlook-ui-modal__btn--confirm" id="emailDeleteContinueBtn">
                    Continue <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                </button>
            </div>
        </div>

        <div class="email-delete-modal__step email-delete-modal__step--2" data-step="2" hidden>
            <div class="outlook-ui-modal__header outlook-ui-modal__header--danger">
                <div class="outlook-ui-modal__header-main">
                    <div class="outlook-ui-modal__header-icon" aria-hidden="true">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                    </div>
                    <div class="outlook-ui-modal__header-text">
                        <p class="email-delete-modal__step-label email-delete-modal__step-label--danger">Step 2 of 2 — Final confirmation</p>
                        <h3 class="outlook-ui-modal__title">Permanently delete this email?</h3>
                        <p class="outlook-ui-modal__subtitle">This action cannot be undone.</p>
                    </div>
                </div>
            </div>
            <div class="outlook-ui-modal__body">
                <p class="email-delete-modal__lead email-delete-modal__lead--danger">The email and all attachments will be removed from the system.</p>
                <div class="outlook-ui-modal__preview-card email-delete-modal__preview email-delete-modal__preview--danger" id="emailDeletePreviewStep2">
                    <div class="email-delete-modal__preview-row">
                        <span class="outlook-ui-modal__preview-label">Subject</span>
                        <span class="email-delete-modal__preview-value" id="emailDeletePreviewSubject2">—</span>
                    </div>
                    <div class="email-delete-modal__preview-row">
                        <span class="outlook-ui-modal__preview-label">From</span>
                        <span class="email-delete-modal__preview-value" id="emailDeletePreviewFrom2">—</span>
                    </div>
                </div>
            </div>
            <div class="outlook-ui-modal__footer">
                <button type="button" class="outlook-ui-modal__btn outlook-ui-modal__btn--cancel" id="emailDeleteBackBtn">
                    <i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Go back
                </button>
                <button type="button" class="outlook-ui-modal__btn outlook-ui-modal__btn--danger" id="emailDeleteConfirmBtn">
                    <i class="fa-solid fa-trash-can" aria-hidden="true"></i> Delete permanently
                </button>
            </div>
        </div>
    </div>
</div>
