<div class="tab-pane{{ strtolower((string) ($activeTab ?? '')) === 'legalforms' ? ' active' : '' }}" id="legalforms-tab">
    @php
        $lfClientMatters = \App\Models\ClientMatter::query()
            ->with('matter:id,title')
            ->where('client_id', $fetchedData->id)
            ->where(function ($q) {
                $q->where('matter_status', 1)->orWhere('matter_status', '1');
            })
            ->whereNotNull('sel_matter_id')
            ->orderByDesc('id')
            ->get();
        $lfDefaultMatterRef = '';
        if (! empty($id1) && is_string($id1)) {
            $lfDefaultMatterRef = $id1;
        }
    @endphp
    <div class="legal-forms-container">
        <div class="legal-forms-header">
            <h4><i class="fa-solid fa-file-signature"></i> Legal Forms & Agreements</h4>
            <div class="legal-forms-actions">
                <div class="dropdown">
                    <button class="btn btn-primary dropdown-toggle" type="button" id="createLegalFormBtn" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fa-solid fa-plus"></i> Create Form
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="createLegalFormBtn" style="z-index: 1050; min-width: 240px;">
                        <li><a class="dropdown-item" href="javascript:;" onclick="openLegalFormModal('short_costs_disclosure')">
                            <i class="fa-solid fa-file-invoice-dollar text-primary"></i> Short Costs Disclosure
                        </a></li>
                        <li><a class="dropdown-item" href="javascript:;" onclick="openLegalFormModal('cost_agreement')">
                            <i class="fa-solid fa-file-contract text-purple"></i> Cost Agreement
                        </a></li>
                        <li><a class="dropdown-item" href="javascript:;" onclick="openLegalFormModal('authority_to_act')">
                            <i class="fa-solid fa-stamp text-success"></i> Authority to Act
                        </a></li>
                    </ul>
                </div>
            </div>
        </div>

        <div id="legal-forms-list" class="legal-forms-list">
            <div class="text-center py-4" id="legal-forms-loading">
                <i class="fa-solid fa-spinner fa-spin"></i> Loading forms...
            </div>
        </div>
    </div>
</div>

{{-- Create/Edit Legal Form Modal --}}
<div class="modal fade legal-form-modal" id="legalFormModal" tabindex="-1" aria-labelledby="legalFormModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered legal-form-modal-dialog">
        <div class="modal-content legal-form-modal-content">
            <div class="modal-header legal-form-modal-header">
                <h5 class="modal-title" id="legalFormModalLabel">Create Legal Form</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="legalFormForm" autocomplete="off" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="client_id" value="{{ $fetchedData->id }}">
                    <input type="hidden" name="form_type" id="lf_form_type" value="">
                    <input type="hidden" name="client_matter_id" id="lf_client_matter_id" value="">

                    {{-- Common Fields --}}
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Form Date</label>
                            <input type="date" name="form_date" id="lf_form_date" class="form-control" value="{{ date('Y-m-d') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold" for="lf_matter_reference">Matter Reference</label>
                            @if($lfClientMatters->isEmpty())
                                <select id="lf_matter_reference" name="matter_reference" class="form-control" disabled>
                                    <option value="">No matters for this client</option>
                                </select>
                            @else
                                <select id="lf_matter_reference" name="matter_reference" class="form-control" required>
                                    <option value="">Select matter</option>
                                    @foreach($lfClientMatters as $lfMatter)
                                        @php
                                            $lfMatterTitle = $lfMatter->matter
                                                ? \App\Models\Matter::displayTitleFromJoinedRow($lfMatter->matter->title ?? null)
                                                : 'Matter';
                                            $lfMatterLabel = trim($lfMatterTitle) . ' (' . ($lfMatter->client_unique_matter_no ?? '') . ')';
                                        @endphp
                                        <option value="{{ $lfMatter->client_unique_matter_no }}"
                                                data-matter-id="{{ $lfMatter->id }}"
                                                @selected($lfDefaultMatterRef !== '' && $lfDefaultMatterRef === (string) $lfMatter->client_unique_matter_no)>
                                            {{ $lfMatterLabel }}
                                        </option>
                                    @endforeach
                                </select>
                            @endif
                        </div>
                    </div>

                    {{-- Optional attachment (images / documents) --}}
                    <div class="mb-3">
                        <label class="form-label fw-bold" for="lf_attachment">Attachment <span class="text-muted fw-normal">(optional)</span></label>
                        <input type="file"
                               name="attachment"
                               id="lf_attachment"
                               class="form-control"
                               accept="image/jpeg,image/png,image/gif,image/webp,image/bmp,.pdf,.doc,.docx,.txt,.xls,.xlsx">
                        <small class="text-muted d-block mt-1">Images or documents only (JPG, PNG, PDF, Word, Excel, text). Max 10 MB.</small>
                        <div id="lf_attachment_preview" class="legal-form-attachment-preview mt-2" style="display:none;"></div>
                    </div>

                    {{-- Firm Details Section --}}
                    <div class="card mb-3">
                        <div class="card-header" style="background: #f0f4f8; cursor: pointer;" data-bs-toggle="collapse" data-bs-target="#firmDetailsCollapse">
                            <strong><i class="fa-solid fa-building"></i> Firm Details</strong>
                            <i class="fa-solid fa-chevron-down float-end mt-1"></i>
                        </div>
                        <div class="collapse" id="firmDetailsCollapse">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6 mb-2">
                                        <label class="form-label">Firm Name</label>
                                        <input type="text" name="firm_name" class="form-control" value="Bansal Lawyers">
                                    </div>
                                    <div class="col-md-6 mb-2">
                                        <label class="form-label">Contact Person</label>
                                        <input type="text" name="firm_contact" class="form-control" value="">
                                    </div>
                                    <div class="col-md-12 mb-2">
                                        <label class="form-label">Address</label>
                                        <input type="text" name="firm_address" class="form-control" value="Level 8, 278 Collins Street, Melbourne VIC 3000">
                                    </div>
                                    <div class="col-md-4 mb-2">
                                        <label class="form-label">Phone</label>
                                        <input type="text" name="firm_phone" class="form-control" value="0422 905 860">
                                    </div>
                                    <div class="col-md-4 mb-2">
                                        <label class="form-label">Email</label>
                                        <input type="text" name="firm_email" class="form-control" value="info@bansallawyers.com.au">
                                    </div>
                                    <div class="col-md-2 mb-2">
                                        <label class="form-label">State</label>
                                        <input type="text" name="firm_state" class="form-control" value="VIC">
                                    </div>
                                    <div class="col-md-2 mb-2">
                                        <label class="form-label">Postcode</label>
                                        <input type="text" name="firm_postcode" class="form-control" value="3000">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Person Responsible (Cost Agreement & Short Costs) --}}
                    <div id="lf_person_responsible_section" class="row mb-3" style="display:none;">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Person Responsible</label>
                            <input type="text" name="person_responsible" id="lf_person_responsible" class="form-control" placeholder="e.g. Ajay Bansal">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Person Responsible Email</label>
                            <input type="text" name="person_responsible_email" id="lf_person_responsible_email" class="form-control" placeholder="e.g. ajay@bansallawyers.com.au">
                        </div>
                    </div>

                    {{-- Scope of Work --}}
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="form-label fw-bold mb-0" id="lf_scope_label">Scope of Work</label>
                            <button type="button" class="btn btn-sm btn-outline-primary ai-generate-btn" onclick="generateWithAI('scope_of_work')" title="Auto-generate with Claude using client, matter, and CRM notes linked to this matter">
                                <i class="fa-solid fa-magic"></i> AI Generate
                            </button>
                        </div>
                        <textarea name="scope_of_work" id="lf_scope_of_work" class="form-control lf-modal-textarea" rows="4" placeholder="Describe the work to be undertaken..."></textarea>
                    </div>

                    {{-- Authority to Act specific --}}
                    <div id="lf_authority_section" style="display:none;">
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="form-label fw-bold mb-0">Authority Scope</label>
                                <button type="button" class="btn btn-sm btn-outline-primary ai-generate-btn" onclick="generateWithAI('authority_scope')" title="Auto-generate with Claude using client, matter, and CRM notes">
                                    <i class="fa-solid fa-magic"></i> AI Generate
                                </button>
                            </div>
                            <textarea name="authority_scope" id="lf_authority_scope" class="form-control lf-modal-textarea" rows="4" placeholder="Describe what you are authorising the firm to do on your behalf..."></textarea>
                            <small class="text-muted">If left blank, the Scope of Work text above will be used.</small>
                        </div>
                    </div>

                    {{-- Cost Fields (Short Costs & Cost Agreement) --}}
                    <div id="lf_costs_section" style="display:none;">
                        <div class="card mb-3">
                            <div class="card-header" style="background: #f0f4f8;">
                                <strong><i class="fa-solid fa-calculator"></i> Cost Estimates</strong>
                            </div>
                            <div class="card-body">
                                <div id="lf_fee_type_section" class="mb-3" style="display:none;">
                                    <label class="form-label fw-bold">Fee Type</label>
                                    <select name="fee_type" id="lf_fee_type" class="form-control">
                                        <option value="fixed">Fixed Fee</option>
                                        <option value="hourly">Hourly Rate</option>
                                    </select>
                                </div>
                                <div id="lf_fixed_fee_section" class="mb-3" style="display:none;">
                                    <label class="form-label">Fixed Fee Amount (excl. GST)</label>
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="number" name="fixed_fee_amount" id="lf_fixed_fee_amount" class="form-control" step="0.01" min="0" value="0">
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-4 mb-2">
                                        <label class="form-label">Legal Fees (excl. GST)</label>
                                        <div class="input-group">
                                            <span class="input-group-text">$</span>
                                            <input type="number" name="estimated_legal_fees" id="lf_estimated_legal_fees" class="form-control" step="0.01" min="0" value="0" oninput="calculateLegalFormTotals()">
                                        </div>
                                    </div>
                                    <div class="col-md-4 mb-2">
                                        <label class="form-label">Disbursements (excl. GST)</label>
                                        <div class="input-group">
                                            <span class="input-group-text">$</span>
                                            <input type="number" name="estimated_disbursements" id="lf_estimated_disbursements" class="form-control" step="0.01" min="0" value="0" oninput="calculateLegalFormTotals()">
                                        </div>
                                    </div>
                                    <div class="col-md-4 mb-2">
                                        <label class="form-label">Barrister Fees (excl. GST)</label>
                                        <div class="input-group">
                                            <span class="input-group-text">$</span>
                                            <input type="number" name="estimated_barrister_fees" id="lf_estimated_barrister_fees" class="form-control" step="0.01" min="0" value="0" oninput="calculateLegalFormTotals()">
                                        </div>
                                    </div>
                                </div>
                                <div class="row mt-2">
                                    <div class="col-md-6">
                                        <label class="form-label">GST (auto-calculated)</label>
                                        <div class="input-group">
                                            <span class="input-group-text">$</span>
                                            <input type="text" id="lf_gst_display" class="form-control" readonly value="0.00" style="background: #f8f9fa;">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Estimated Total (incl. GST)</label>
                                        <div class="input-group">
                                            <span class="input-group-text">$</span>
                                            <input type="text" id="lf_total_display" class="form-control fw-bold" readonly value="0.00" style="background: #e8f4e8;">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Cost Agreement extra fields --}}
                        <div id="lf_cost_agreement_extra" style="display:none;">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Cost Estimate Breakdown</label>
                                <textarea name="cost_estimate_breakdown" id="lf_cost_estimate_breakdown" class="form-control lf-modal-textarea" rows="3" placeholder="Detailed breakdown of costs (optional - if blank, the estimates above will be used in a table)..."></textarea>
                            </div>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <label class="form-label fw-bold mb-0">Variables That Might Affect Costs</label>
                                    <button type="button" class="btn btn-sm btn-outline-primary ai-generate-btn" onclick="generateWithAI('variables_affecting_costs')" title="Auto-generate with Claude using client, matter, and CRM notes">
                                        <i class="fa-solid fa-magic"></i> AI Generate
                                    </button>
                                </div>
                                <textarea name="variables_affecting_costs" id="lf_variables_affecting_costs" class="form-control lf-modal-textarea" rows="3" placeholder="e.g. Amount of correspondence required, complexity of legal issues, whether spouse consents..."></textarea>
                            </div>
                        </div>

                        {{-- Payment Arrangements --}}
                        <div class="card mb-3">
                            <div class="card-header" style="background: #f0f4f8; cursor: pointer;" data-bs-toggle="collapse" data-bs-target="#paymentCollapse">
                                <strong><i class="fa-solid fa-building-columns"></i> Payment Arrangements</strong>
                                <i class="fa-solid fa-chevron-down float-end mt-1"></i>
                            </div>
                            <div class="collapse" id="paymentCollapse">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6 mb-2">
                                            <label class="form-label">Retainer Amount</label>
                                            <div class="input-group">
                                                <span class="input-group-text">$</span>
                                                <input type="number" name="retainer_amount" class="form-control" step="0.01" min="0" value="0">
                                            </div>
                                        </div>
                                        <div class="col-md-6 mb-2">
                                            <label class="form-label">Payment Reference</label>
                                            <input type="text" name="payment_reference" class="form-control" placeholder="e.g. Matter reference number">
                                        </div>
                                    </div>
                                    <hr>
                                    <p class="text-muted mb-2"><small>Trust Account Details (pre-filled)</small></p>
                                    <div class="row">
                                        <div class="col-md-6 mb-2">
                                            <label class="form-label">Account Name</label>
                                            <input type="text" name="trust_account_name" class="form-control" value="BANSAL Lawyers">
                                        </div>
                                        <div class="col-md-6 mb-2">
                                            <label class="form-label">Institution</label>
                                            <input type="text" name="trust_account_institution" class="form-control" value="NAB">
                                        </div>
                                        <div class="col-md-6 mb-2">
                                            <label class="form-label">BSB</label>
                                            <input type="text" name="trust_account_bsb" class="form-control" value="083419">
                                        </div>
                                        <div class="col-md-6 mb-2">
                                            <label class="form-label">Account Number</label>
                                            <input type="text" name="trust_account_number" class="form-control" value="787266100">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </form>
            </div>
            <div class="modal-footer legal-form-modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveLegalFormBtn" onclick="saveLegalForm()">
                    <i class="fa-solid fa-floppy-disk"></i> Create Form & Generate Word Document
                </button>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    'use strict';

    /** Path only (same-origin); works with subdirectory installs and avoids APP_URL host mismatches */
    var LF_BASE = @json(rtrim(parse_url(url('/legal-forms'), PHP_URL_PATH) ?: '/legal-forms', '/'));

    const FORM_TYPE_LABELS = {
        'short_costs_disclosure': 'Short Costs Disclosure',
        'cost_agreement': 'Cost Agreement',
        'authority_to_act': 'Authority to Act'
    };

    const FORM_TYPE_ICONS = {
        'short_costs_disclosure': 'fa-solid fa-file-invoice-dollar',
        'cost_agreement': 'fa-solid fa-file-contract',
        'authority_to_act': 'fa-solid fa-stamp'
    };

    const FORM_TYPE_COLORS = {
        'short_costs_disclosure': '#3b82f6',
        'cost_agreement': '#8b5cf6',
        'authority_to_act': '#10b981'
    };

    // Keep modal out of .tab-content overflow/stacking contexts (footer was overlapping body).
    (function ensureLegalFormModalOnBody() {
        var modalEl = document.getElementById('legalFormModal');
        if (modalEl && modalEl.parentElement !== document.body) {
            document.body.appendChild(modalEl);
        }
    })();

    function syncLegalFormMatterFromSelect() {
        var matterRefSelect = document.getElementById('lf_matter_reference');
        var matterIdInput = document.getElementById('lf_client_matter_id');
        if (!matterRefSelect || !matterIdInput || matterRefSelect.disabled) {
            return;
        }
        var opt = matterRefSelect.options[matterRefSelect.selectedIndex];
        matterIdInput.value = (opt && opt.getAttribute('data-matter-id')) ? opt.getAttribute('data-matter-id') : '';
    }

    function selectLegalFormMatterByRef(matterRef) {
        var matterRefSelect = document.getElementById('lf_matter_reference');
        if (!matterRefSelect || matterRefSelect.disabled || !matterRef) {
            syncLegalFormMatterFromSelect();
            return;
        }
        var matched = false;
        for (var i = 0; i < matterRefSelect.options.length; i++) {
            if (matterRefSelect.options[i].value === matterRef) {
                matterRefSelect.selectedIndex = i;
                matched = true;
                break;
            }
        }
        if (!matched && matterRefSelect.options.length > 1 && !matterRefSelect.value) {
            matterRefSelect.selectedIndex = 1;
        }
        syncLegalFormMatterFromSelect();
    }

    function updateLegalFormAttachmentPreview() {
        var input = document.getElementById('lf_attachment');
        var preview = document.getElementById('lf_attachment_preview');
        if (!input || !preview) {
            return;
        }
        if (!input.files || !input.files.length) {
            preview.style.display = 'none';
            preview.innerHTML = '';
            return;
        }
        var file = input.files[0];
        var icon = file.type.indexOf('image/') === 0 ? 'fa-file-image' : 'fa-file';
        preview.style.display = 'block';
        preview.innerHTML = '<i class="fa-solid ' + icon + '"></i> ' + file.name + ' (' + Math.max(1, Math.round(file.size / 1024)) + ' KB)';
    }

    var lfMatterRefSelect = document.getElementById('lf_matter_reference');
    if (lfMatterRefSelect) {
        lfMatterRefSelect.addEventListener('change', syncLegalFormMatterFromSelect);
    }
    var lfAttachmentInput = document.getElementById('lf_attachment');
    if (lfAttachmentInput) {
        lfAttachmentInput.addEventListener('change', updateLegalFormAttachmentPreview);
    }

    syncLegalFormMatterFromSelect();

    window.openLegalFormModal = function(formType) {
        // Reset form
        document.getElementById('legalFormForm').reset();
        document.getElementById('lf_form_type').value = formType;
        document.getElementById('lf_form_date').value = new Date().toISOString().split('T')[0];

        // Pre-select matter from sidebar dropdown or URL matter ref
        var sidebarMatterRef = '';
        var matterSelect = document.getElementById('sel_matter_id_client_detail');
        if (matterSelect && matterSelect.value) {
            var selectedOption = matterSelect.options[matterSelect.selectedIndex];
            if (selectedOption) {
                sidebarMatterRef = selectedOption.getAttribute('data-clientuniquematterno') || '';
            }
        }
        if (!sidebarMatterRef && window.ClientDetailShared && typeof window.ClientDetailShared.parseClientDetailMatterRefFromUrl === 'function') {
            sidebarMatterRef = window.ClientDetailShared.parseClientDetailMatterRefFromUrl() || '';
        }
        selectLegalFormMatterByRef(sidebarMatterRef);
        updateLegalFormAttachmentPreview();

        // Show/hide sections based on form type
        var costsSection = document.getElementById('lf_costs_section');
        var authoritySection = document.getElementById('lf_authority_section');
        var personSection = document.getElementById('lf_person_responsible_section');
        var feeTypeSection = document.getElementById('lf_fee_type_section');
        var fixedFeeSection = document.getElementById('lf_fixed_fee_section');
        var costAgreementExtra = document.getElementById('lf_cost_agreement_extra');

        costsSection.style.display = 'none';
        authoritySection.style.display = 'none';
        personSection.style.display = 'none';
        feeTypeSection.style.display = 'none';
        fixedFeeSection.style.display = 'none';
        costAgreementExtra.style.display = 'none';

        var modalTitle = document.getElementById('legalFormModalLabel');

        if (formType === 'short_costs_disclosure') {
            modalTitle.textContent = 'Create Short Costs Disclosure';
            costsSection.style.display = 'block';
            personSection.style.display = 'flex';
        } else if (formType === 'cost_agreement') {
            modalTitle.textContent = 'Create Cost Agreement';
            costsSection.style.display = 'block';
            personSection.style.display = 'flex';
            feeTypeSection.style.display = 'block';
            fixedFeeSection.style.display = 'block';
            costAgreementExtra.style.display = 'block';
        } else if (formType === 'authority_to_act') {
            modalTitle.textContent = 'Create Authority to Act';
            authoritySection.style.display = 'block';
        }

        // Re-set default firm values
        document.querySelector('[name="firm_name"]').value = 'Bansal Lawyers';
        document.querySelector('[name="firm_address"]').value = 'Level 8, 278 Collins Street, Melbourne VIC 3000';
        document.querySelector('[name="firm_phone"]').value = '0422 905 860';
        document.querySelector('[name="firm_email"]').value = 'info@bansallawyers.com.au';

        var modal = new bootstrap.Modal(document.getElementById('legalFormModal'));
        modal.show();
    };

    window.calculateLegalFormTotals = function() {
        var fees = parseFloat(document.getElementById('lf_estimated_legal_fees').value) || 0;
        var disb = parseFloat(document.getElementById('lf_estimated_disbursements').value) || 0;
        var barr = parseFloat(document.getElementById('lf_estimated_barrister_fees').value) || 0;
        var gst = fees * 0.10;
        var total = fees + disb + barr + gst;
        document.getElementById('lf_gst_display').value = gst.toFixed(2);
        document.getElementById('lf_total_display').value = total.toFixed(2);
    };

    window.saveLegalForm = function() {
        var form = document.getElementById('legalFormForm');
        syncLegalFormMatterFromSelect();

        var matterRefSelect = document.getElementById('lf_matter_reference');
        if (matterRefSelect && !matterRefSelect.disabled && !matterRefSelect.value) {
            if (typeof iziToast !== 'undefined' && typeof iziToast.error === 'function') {
                iziToast.error({ message: 'Please select a matter reference.', position: 'topRight' });
            } else {
                alert('Please select a matter reference.');
            }
            return;
        }

        var formData = new FormData(form);
        var btn = document.getElementById('saveLegalFormBtn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Generating...';

        $.ajax({
            url: LF_BASE,
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            success: function(response) {
                if (response.success) {
                    bootstrap.Modal.getInstance(document.getElementById('legalFormModal')).hide();
                    loadLegalForms();
                    if (typeof iziToast !== 'undefined' && typeof iziToast.success === 'function') {
                        iziToast.success({ message: response.message || 'Form created successfully!', position: 'topRight' });
                    } else {
                        alert(response.message || 'Form created successfully!');
                    }
                } else {
                    if (typeof iziToast !== 'undefined' && typeof iziToast.error === 'function') {
                        iziToast.error({ message: response.message || 'Failed to create form.', position: 'topRight' });
                    } else {
                        alert(response.message || 'Failed to create form.');
                    }
                }
            },
            error: function(xhr) {
                var msg = 'Failed to create form.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                } else if (xhr.responseJSON && xhr.responseJSON.errors) {
                    msg = Object.values(xhr.responseJSON.errors).flat().join('\n');
                }
                if (typeof iziToast !== 'undefined' && typeof iziToast.error === 'function') {
                    iziToast.error({ message: msg, position: 'topRight' });
                } else {
                    alert(msg);
                }
            },
            complete: function() {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Create Form & Generate Word Document';
            }
        });
    };

    window.loadLegalForms = function() {
        var clientId = {{ $fetchedData->id }};
        var matterId = '';
        var matterSelect = document.getElementById('sel_matter_id_client_detail');
        if (matterSelect) matterId = matterSelect.value || '';

        var listEl = document.getElementById('legal-forms-list');
        listEl.innerHTML = '<div class="text-center py-4"><i class="fa-solid fa-spinner fa-spin"></i> Loading forms...</div>';

        $.ajax({
            url: LF_BASE + '/client-forms',
            method: 'GET',
            data: { client_id: clientId, matter_id: matterId },
            success: function(response) {
                if (response.success && response.forms) {
                    renderLegalFormsList(response.forms);
                } else {
                    listEl.innerHTML = '<div class="text-center py-4 text-muted"><i class="fa-solid fa-file-lines"></i><br>No forms found.</div>';
                }
            },
            error: function() {
                listEl.innerHTML = '<div class="text-center py-4 text-danger">Failed to load forms.</div>';
            }
        });
    };

    function renderLegalFormsList(forms) {
        var listEl = document.getElementById('legal-forms-list');

        if (!forms || forms.length === 0) {
            listEl.innerHTML = '<div class="legal-forms-empty"><div class="legal-forms-empty-icon"><i class="fa-solid fa-file-signature"></i></div><p>No legal forms created yet.</p><p class="text-muted">Click "Create Form" to generate a Short Costs Disclosure, Cost Agreement, or Authority to Act.</p></div>';
            return;
        }

        var html = '<div class="legal-forms-grid">';
        forms.forEach(function(form) {
            var label = FORM_TYPE_LABELS[form.form_type] || form.form_type;
            var icon = FORM_TYPE_ICONS[form.form_type] || 'fa-solid fa-file';
            var color = FORM_TYPE_COLORS[form.form_type] || '#6b7280';
            var date = form.form_date ? new Date(form.form_date).toLocaleDateString('en-AU') : new Date(form.created_at).toLocaleDateString('en-AU');
            var creator = form.creator ? (form.creator.first_name || '') + ' ' + (form.creator.last_name || '') : '';
            var matterRef = form.matter_reference || (form.matter ? form.matter.client_unique_matter_no : '');

            html += '<div class="legal-form-card">';
            html += '<div class="legal-form-card-header" style="border-left: 4px solid ' + color + ';">';
            html += '<div class="legal-form-card-icon" style="color: ' + color + ';"><i class="' + icon + '"></i></div>';
            html += '<div class="legal-form-card-info">';
            html += '<h5>' + label + '</h5>';
            html += '<span class="legal-form-card-date">' + date + '</span>';
            if (matterRef) html += '<span class="legal-form-card-matter"> &bull; Ref: ' + matterRef + '</span>';
            html += '</div>';
            html += '</div>';
            html += '<div class="legal-form-card-actions">';
            html += '<a href="' + LF_BASE + '/' + form.id + '/download" class="btn btn-sm btn-outline-success" title="Download Word Document"><i class="fa-solid fa-file-word"></i> Download</a>';
            if (form.attachment_path) {
                html += '<a href="' + LF_BASE + '/' + form.id + '/attachment" class="btn btn-sm btn-outline-primary" title="Download attachment"><i class="fa-solid fa-paperclip"></i> Attachment</a>';
            }
            html += '<button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteLegalForm(' + form.id + ')" title="Delete"><i class="fa-solid fa-trash"></i></button>';
            html += '</div>';
            html += '</div>';
        });
        html += '</div>';
        listEl.innerHTML = html;
    }

    window.generateWithAI = function(fieldName) {
        var clientId = {{ $fetchedData->id }};
        var formType = document.getElementById('lf_form_type').value;
        var matterId = document.getElementById('lf_client_matter_id').value;

        var fieldMap = {
            'scope_of_work': 'lf_scope_of_work',
            'authority_scope': 'lf_authority_scope',
            'variables_affecting_costs': 'lf_variables_affecting_costs'
        };

        var textareaId = fieldMap[fieldName];
        if (!textareaId) return;

        var textarea = document.getElementById(textareaId);
        var btn = event.currentTarget;
        var originalHtml = btn.innerHTML;

        if (textarea.value.trim() !== '') {
            if (!confirm('This will replace the current text. Continue?')) return;
        }

        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Generating...';
        textarea.style.opacity = '0.5';

        $.ajax({
            url: LF_BASE + '/generate-scope-ai',
            method: 'POST',
            data: {
                client_id: clientId,
                client_matter_id: matterId || null,
                matter_reference: (document.getElementById('lf_matter_reference') && document.getElementById('lf_matter_reference').value) || '',
                form_type: formType,
                field: fieldName
            },
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            success: function(response) {
                if (response.success && response.text) {
                    textarea.value = response.text;
                    textarea.style.opacity = '1';
                    textarea.style.borderColor = '#10b981';
                    setTimeout(function() { textarea.style.borderColor = ''; }, 2000);
                    if (typeof iziToast !== 'undefined' && typeof iziToast.success === 'function') {
                        iziToast.success({ message: 'AI text generated successfully!', position: 'topRight' });
                    }
                } else {
                    textarea.style.opacity = '1';
                    if (typeof iziToast !== 'undefined' && typeof iziToast.error === 'function') {
                        iziToast.error({ message: response.message || 'Failed to generate text.', position: 'topRight' });
                    } else {
                        alert(response.message || 'Failed to generate text.');
                    }
                }
            },
            error: function(xhr) {
                textarea.style.opacity = '1';
                var msg = 'AI generation failed.';
                if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
                if (typeof iziToast !== 'undefined' && typeof iziToast.error === 'function') {
                    iziToast.error({ message: msg, position: 'topRight' });
                } else {
                    alert(msg);
                }
            },
            complete: function() {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
        });
    };

    window.deleteLegalForm = function(formId) {
        if (!confirm('Are you sure you want to delete this form? This action cannot be undone.')) return;

        $.ajax({
            url: LF_BASE + '/' + formId + '/delete',
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            success: function(response) {
                if (response.success) {
                    loadLegalForms();
                    if (typeof iziToast !== 'undefined' && typeof iziToast.success === 'function') {
                        iziToast.success({ message: response.message || 'Form deleted.', position: 'topRight' });
                    } else {
                        alert(response.message || 'Form deleted.');
                    }
                } else {
                    var failMsg = (response && response.message) ? response.message : 'Failed to delete form.';
                    if (typeof iziToast !== 'undefined' && typeof iziToast.error === 'function') {
                        iziToast.error({ message: failMsg, position: 'topRight' });
                    } else {
                        alert(failMsg);
                    }
                }
            },
            error: function(xhr) {
                var msg = 'Failed to delete form.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                }
                if (typeof iziToast !== 'undefined' && typeof iziToast.error === 'function') {
                    iziToast.error({ message: msg, position: 'topRight' });
                } else {
                    alert(msg);
                }
            }
        });
    };

    // Load forms when the tab pane becomes visible (gains 'active' class)
    var legalFormsTabPane = document.getElementById('legalforms-tab');
    var legalFormsLoaded = false;

    function checkAndLoadForms() {
        if (legalFormsTabPane && legalFormsTabPane.classList.contains('active')) {
            if (!legalFormsLoaded) {
                legalFormsLoaded = true;
                loadLegalForms();
            }
        } else {
            legalFormsLoaded = false;
        }
    }

    // Observe class changes on the tab pane to detect activation
    if (legalFormsTabPane) {
        var observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.attributeName === 'class') {
                    checkAndLoadForms();
                }
            });
        });
        observer.observe(legalFormsTabPane, { attributes: true });
    }

    // Also check on DOM ready in case tab is already active
    $(document).ready(function() {
        checkAndLoadForms();
    });

    // Also load on matter change
    $(document).on('change', '#sel_matter_id_client_detail', function() {
        if (legalFormsTabPane && legalFormsTabPane.classList.contains('active')) {
            legalFormsLoaded = false;
            loadLegalForms();
        }
    });
})();
</script>
