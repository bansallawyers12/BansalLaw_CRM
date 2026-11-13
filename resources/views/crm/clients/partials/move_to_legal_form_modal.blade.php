<div class="modal fade" id="moveToLegalFormModal" tabindex="-1" aria-labelledby="moveToLegalFormModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="moveToLegalFormModalLabel">Move to Legal Forms</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2" id="moveToLegalFormFileName" style="font-weight: 600;"></p>
                <p class="text-muted" style="font-size: 13px;">The file leaves the documents list and appears on this client's Legal Forms tab.</p>
                <div class="form-group">
                    <label for="moveToLegalFormType">Form type</label>
                    <select id="moveToLegalFormType" class="form-control">
                        <option value="">Select a form type</option>
                        <option value="short_costs_disclosure">Short Costs Disclosure</option>
                        <option value="cost_agreement">Long Costs Disclosure</option>
                        <option value="authority_to_act">Authority to Act</option>
                    </select>
                </div>
                <div id="moveToLegalFormError" class="alert alert-danger" style="display: none; margin-top: 10px;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirmMoveToLegalForm">Move to Legal Forms</button>
            </div>
        </div>
    </div>
</div>
<script>
(function () {
    if (window.openMoveToLegalFormModal) {
        return;
    }

    var currentDocumentId = null;
    var previewRequestId = 0;
    var previewUrl = @json(route('clients.documents.legalFormMovePreview'));
    var moveUrl = @json(route('clients.documents.moveToLegalForm'));
    var csrf = @json(csrf_token());

    function modalEl() {
        return document.getElementById('moveToLegalFormModal');
    }

    function showError(message) {
        var box = document.getElementById('moveToLegalFormError');
        if (!box) {
            return;
        }
        if (!message) {
            box.style.display = 'none';
            box.textContent = '';
            return;
        }
        box.textContent = message;
        box.style.display = 'block';
    }

    window.openMoveToLegalFormModal = function (documentId) {
        currentDocumentId = documentId;
        var requestId = ++previewRequestId;
        var modal = modalEl();
        if (!modal || typeof $ === 'undefined') {
            return;
        }
        if (modal.parentElement !== document.body) {
            document.body.appendChild(modal);
        }
        showError('');
        document.getElementById('moveToLegalFormFileName').textContent = 'Loading…';
        document.getElementById('moveToLegalFormType').value = '';
        document.getElementById('confirmMoveToLegalForm').disabled = true;
        $('#moveToLegalFormModal').modal('show');

        $.get(previewUrl, { document_id: documentId })
            .done(function (res) {
                if (requestId !== previewRequestId) {
                    return;
                }
                document.getElementById('moveToLegalFormFileName').textContent = (res && res.file_name) ? res.file_name : 'Document';
                if (res && res.suggested_form_type) {
                    document.getElementById('moveToLegalFormType').value = res.suggested_form_type;
                }
                if (!res || res.allowed === false) {
                    showError((res && res.message) || 'This document cannot be moved to Legal Forms.');
                    document.getElementById('confirmMoveToLegalForm').disabled = true;
                    return;
                }
                document.getElementById('confirmMoveToLegalForm').disabled = false;
            })
            .fail(function (xhr) {
                if (requestId !== previewRequestId) {
                    return;
                }
                var message = 'This document cannot be moved to Legal Forms.';
                if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
                    message = xhr.responseJSON.message;
                }
                document.getElementById('moveToLegalFormFileName').textContent = '';
                showError(message);
            });
    };

    document.getElementById('confirmMoveToLegalForm').addEventListener('click', function () {
        var formType = document.getElementById('moveToLegalFormType').value;
        if (!currentDocumentId) {
            return;
        }
        if (!formType) {
            showError('Choose Short Costs Disclosure, Long Costs Disclosure, or Authority to Act.');
            return;
        }
        var button = this;
        button.disabled = true;
        showError('');
        $.ajax({
            url: moveUrl,
            method: 'POST',
            data: {
                _token: csrf,
                document_id: currentDocumentId,
                form_type: formType
            }
        }).done(function (res) {
            $('#moveToLegalFormModal').modal('hide');
            var message = (res && res.message) || 'Moved to Legal Forms.';
            if (typeof iziToast !== 'undefined' && typeof iziToast.success === 'function') {
                iziToast.success({ message: message, position: 'topRight' });
            } else if (typeof crmAlert === 'function') {
                crmAlert(message);
            }
            var row = document.getElementById('id_' + currentDocumentId);
            if (row) {
                row.remove();
            }
            if (typeof window.loadLegalForms === 'function') {
                window.loadLegalForms();
            }
            if (document.body && document.body.getAttribute('data-reload-after-legal-form-move') === '1') {
                window.location.reload();
            }
        }).fail(function (xhr) {
            var message = 'The document could not be moved to Legal Forms.';
            if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
                message = xhr.responseJSON.message;
            }
            showError(message);
            button.disabled = false;
        });
    });
})();
</script>
