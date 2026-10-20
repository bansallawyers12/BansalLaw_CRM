<div class="email-remove-modal-overlay" id="emailRemoveModal" hidden>
    <div class="email-remove-modal" role="dialog" aria-labelledby="emailRemoveModalTitle" aria-modal="true">
        <h3 id="emailRemoveModalTitle">Remove email</h3>
        <p id="emailRemoveModalSubject"></p>
        <p class="email-remove-modal__hint">It leaves the mail lists. The message and its attachments stay on file.</p>
        <div class="email-remove-modal__actions">
            <button type="button" class="email-remove-modal__btn" data-reason="marketing">Marketing</button>
            <button type="button" class="email-remove-modal__btn" data-reason="duplicate">Duplicate</button>
            <button type="button" class="email-remove-modal__btn email-remove-modal__btn--cancel" data-reason="">Cancel</button>
        </div>
    </div>
</div>
<style>
.email-remove-modal-overlay {
    position: fixed;
    inset: 0;
    z-index: 12000;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(15, 23, 42, 0.45);
    padding: 16px;
}
.email-remove-modal-overlay[hidden] { display: none !important; }
.email-remove-modal {
    width: min(420px, 100%);
    background: #fff;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 16px 40px rgba(15, 23, 42, 0.2);
}
.email-remove-modal h3 { margin: 0 0 8px; font-size: 18px; }
.email-remove-modal p { margin: 0 0 8px; color: #334155; }
.email-remove-modal__hint { font-size: 13px; color: #64748b; }
.email-remove-modal__actions { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 16px; }
.email-remove-modal__btn {
    border: 1px solid #cbd5e1;
    background: #fff;
    border-radius: 8px;
    padding: 8px 12px;
    font-weight: 600;
    cursor: pointer;
}
.email-remove-modal__btn--cancel { margin-left: auto; }
</style>
<script>
window.askEmailRemovalReason = function (email) {
    const modal = document.getElementById('emailRemoveModal');
    if (!modal) {
        return Promise.resolve(window.confirm('Remove this email as marketing?') ? 'marketing' : (window.confirm('Remove this email as a duplicate?') ? 'duplicate' : null));
    }
    const subject = document.getElementById('emailRemoveModalSubject');
    if (subject) {
        const text = (email && email.subject) ? email.subject : '(No subject)';
        subject.textContent = text;
    }
    modal.hidden = false;
    return new Promise(function (resolve) {
        function finish(reason) {
            modal.hidden = true;
            modal.removeEventListener('click', onClick);
            document.removeEventListener('keydown', onKey);
            resolve(reason || null);
        }
        function onClick(event) {
            const button = event.target.closest('[data-reason]');
            if (button) {
                finish(button.getAttribute('data-reason') || null);
                return;
            }
            if (event.target === modal) {
                finish(null);
            }
        }
        function onKey(event) {
            if (event.key === 'Escape') {
                finish(null);
            }
        }
        modal.addEventListener('click', onClick);
        document.addEventListener('keydown', onKey);
    });
};
</script>
