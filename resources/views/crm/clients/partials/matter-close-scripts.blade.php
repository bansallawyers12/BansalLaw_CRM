{{-- Close / discontinue / complete matter modal handlers (detail + workflow) --}}
<script>
(function () {
    if (window.__matterCloseScriptsInit) return;
    window.__matterCloseScriptsInit = true;

    var REASON_COMPLETE = 'Complete';
    var pendingCompletePayload = null;

    function getCsrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function getCurrentTab() {
        var active = document.querySelector('.client-nav-button.active');
        return active ? (active.getAttribute('data-tab') || 'personaldetails') : 'personaldetails';
    }

    function showModal(id) {
        if (typeof window.jQuery !== 'undefined' && window.jQuery.fn && window.jQuery.fn.modal) {
            window.jQuery(id).modal('show');
        }
    }

    function hideModal(id) {
        if (typeof window.jQuery !== 'undefined' && window.jQuery.fn && window.jQuery.fn.modal) {
            window.jQuery(id).modal('hide');
        }
    }

    function updateDiscontinueSubmitLabel() {
        var reasonEl = document.getElementById('discontinue-reason');
        var labelEl = document.getElementById('discontinue-matter-submit-label');
        var submitBtn = document.getElementById('discontinue-matter-submit');
        var iconEl = document.getElementById('discontinue-matter-submit-icon');
        if (!labelEl || !submitBtn) return;
        var isComplete = reasonEl && reasonEl.value === REASON_COMPLETE;
        labelEl.textContent = isComplete ? 'Continue to checklist' : 'Discontinue';
        submitBtn.classList.toggle('btn-success', isComplete);
        submitBtn.classList.toggle('btn-danger', !isComplete);
        if (iconEl) {
            iconEl.className = isComplete ? 'fa-solid fa-list-check' : 'fa-solid fa-ban';
        }
    }

    function resetCompleteChecklistModal() {
        document.querySelectorAll('.complete-matter-check-item').forEach(function (cb) {
            cb.checked = false;
        });
        var desc = document.getElementById('complete-matter-description');
        if (desc) desc.value = '';
        var err = document.querySelector('.complete-matter-checklist-error strong');
        if (err) err.textContent = '';
        updateCompleteSubmitState();
    }

    function allCompleteChecksChecked() {
        var items = document.querySelectorAll('.complete-matter-check-item');
        if (!items.length) return false;
        for (var i = 0; i < items.length; i++) {
            if (!items[i].checked) return false;
        }
        return true;
    }

    function updateCompleteSubmitState() {
        var btn = document.getElementById('complete-matter-submit');
        if (btn) btn.disabled = !allCompleteChecksChecked();
    }

    function collectCompletionChecklist() {
        var payload = {};
        document.querySelectorAll('.complete-matter-check-item').forEach(function (cb) {
            var key = cb.getAttribute('data-check-key');
            if (key) payload[key] = cb.checked;
        });
        return payload;
    }

    function openCompleteChecklistModal(matterId, notes) {
        pendingCompletePayload = {
            matter_id: matterId,
            discontinue_reason: REASON_COMPLETE,
            discontinue_notes: notes || '',
            current_tab: getCurrentTab()
        };
        resetCompleteChecklistModal();
        hideModal('#discontinue-matter-modal');
        showModal('#complete-matter-modal');
    }

    function submitMatterClose(payload, submitBtn, origHtml, onSuccessMessage) {
        if (!submitBtn) return;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Processing...';

        fetch(@json(route('clients.matter.discontinue')), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
                'Accept': 'application/json'
            },
            body: JSON.stringify(payload)
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = origHtml;
                if (data.status) {
                    hideModal('#discontinue-matter-modal');
                    hideModal('#complete-matter-modal');
                    crmAlert(data.message || onSuccessMessage);
                    var clientEncodeId = window.ClientDetailConfig ? window.ClientDetailConfig.encodeId : null;
                    if (data.redirect_url) {
                        window.location.href = data.redirect_url;
                    } else if (clientEncodeId) {
                        window.location.href = '/clients/detail/' + clientEncodeId;
                    } else {
                        window.location.reload();
                    }
                } else {
                    crmAlert(data.message || 'Request failed.');
                }
            })
            .catch(function () {
                submitBtn.disabled = false;
                submitBtn.innerHTML = origHtml;
                crmAlert('An error occurred. Please try again.');
            });
    }

    function handleDiscontinueSubmit() {
        var matterSelect = document.getElementById('discontinue-matter-select');
        var reasonSelect = document.getElementById('discontinue-reason');
        var reason = reasonSelect ? reasonSelect.value : '';
        var matterId = matterSelect ? matterSelect.value : '';
        var notes = document.getElementById('discontinue-notes');
        var notesVal = notes ? notes.value : '';
        var matterErrEl = document.querySelector('.discontinue-matter-error strong');
        var errEl = document.querySelector('.discontinue-reason-error strong');

        if (matterErrEl) matterErrEl.textContent = '';
        if (!matterId || String(matterId).trim() === '') {
            if (matterErrEl) matterErrEl.textContent = 'Please select a matter to discontinue.';
            return;
        }
        if (!reason || String(reason).trim() === '') {
            if (errEl) errEl.textContent = 'Please select a reason for discontinuing.';
            return;
        }
        if (errEl) errEl.textContent = '';

        if (reason === REASON_COMPLETE) {
            openCompleteChecklistModal(matterId, notesVal);
            return;
        }

        var btn = document.getElementById('discontinue-matter-submit');
        var orig = btn ? btn.innerHTML : '';
        submitMatterClose({
            matter_id: matterId,
            discontinue_reason: reason,
            discontinue_notes: notesVal,
            current_tab: getCurrentTab()
        }, btn, orig, 'Matter has been discontinued.');
    }

    function handleCompleteSubmit() {
        if (!pendingCompletePayload) return;
        var errEl = document.querySelector('.complete-matter-checklist-error strong');
        if (!allCompleteChecksChecked()) {
            if (errEl) errEl.textContent = 'Please check all items before completing the matter.';
            return;
        }
        if (errEl) errEl.textContent = '';

        var descEl = document.getElementById('complete-matter-description');
        var description = descEl ? descEl.value.trim() : '';
        var payload = Object.assign({}, pendingCompletePayload, {
            completion_checklist: collectCompletionChecklist(),
            discontinue_notes: description
        });

        var btn = document.getElementById('complete-matter-submit');
        var orig = btn ? btn.innerHTML : '';
        submitMatterClose(payload, btn, orig, 'Matter has been successfully completed.');
    }

    document.addEventListener('change', function (e) {
        if (e.target && e.target.id === 'discontinue-reason') {
            updateDiscontinueSubmitLabel();
        }
        if (e.target && e.target.classList && e.target.classList.contains('complete-matter-check-item')) {
            updateCompleteSubmitState();
            var errEl = document.querySelector('.complete-matter-checklist-error strong');
            if (errEl) errEl.textContent = '';
        }
    });

    document.addEventListener('click', function (e) {
        if (e.target && e.target.closest && e.target.closest('#discontinue-matter-submit')) {
            e.preventDefault();
            handleDiscontinueSubmit();
            return;
        }
        if (e.target && e.target.closest && e.target.closest('#complete-matter-submit')) {
            e.preventDefault();
            handleCompleteSubmit();
            return;
        }
        if (e.target && e.target.closest && e.target.closest('#complete-matter-back-btn')) {
            e.preventDefault();
            hideModal('#complete-matter-modal');
            showModal('#discontinue-matter-modal');
        }
    });

    window.resetMatterCloseModals = function () {
        var reasonEl = document.getElementById('discontinue-reason');
        if (reasonEl) reasonEl.value = '';
        var notesEl = document.getElementById('discontinue-notes');
        if (notesEl) notesEl.value = '';
        var matterErrEl = document.querySelector('.discontinue-matter-error strong');
        if (matterErrEl) matterErrEl.textContent = '';
        var errEl = document.querySelector('.discontinue-reason-error strong');
        if (errEl) errEl.textContent = '';
        pendingCompletePayload = null;
        resetCompleteChecklistModal();
        updateDiscontinueSubmitLabel();
    };

    updateDiscontinueSubmitLabel();
})();
</script>
