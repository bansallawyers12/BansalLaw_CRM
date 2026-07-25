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
        <div class="legal-forms-split-layout">
            <div class="checklist-table-container legal-forms-list-panel">
                <div class="legal-forms-list-toolbar">
                    <div class="legal-forms-list-toolbar__title">
                        <h3><i class="fa-solid fa-file-signature"></i> Saved Forms</h3>
                        <span id="legal-forms-count" class="legal-forms-count">0</span>
                    </div>
                    <div class="legal-forms-list-toolbar__actions legal-forms-toolbar-actions">
                        <button class="btn btn-sm btn-outline-primary legal-forms-upload-btn" type="button" onclick="openLegalFormUploadModal()" title="Upload an existing document">
                            <i class="fa-solid fa-file-arrow-up"></i> Upload
                        </button>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-primary dropdown-toggle" type="button" id="createLegalFormBtn" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fa-solid fa-plus"></i> Create
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end legal-forms-create-menu" aria-labelledby="createLegalFormBtn">
                                <li class="dropdown-header">Generate new form</li>
                                <li><a class="dropdown-item" href="javascript:;" onclick="openLegalFormModal('short_costs_disclosure')">
                                    <span class="lf-menu-icon lf-menu-icon--blue"><i class="fa-solid fa-file-invoice-dollar"></i></span>
                                    <span class="lf-menu-text">
                                        <strong>Short Costs Disclosure</strong>
                                        <small>Quick cost estimate form</small>
                                    </span>
                                </a></li>
                                <li><a class="dropdown-item" href="javascript:;" onclick="openLegalFormModal('cost_agreement')">
                                    <span class="lf-menu-icon lf-menu-icon--purple"><i class="fa-solid fa-file-contract"></i></span>
                                    <span class="lf-menu-text">
                                        <strong>Long Cost Disclosure</strong>
                                        <small>Detailed cost agreement</small>
                                    </span>
                                </a></li>
                                <li><a class="dropdown-item" href="javascript:;" onclick="openLegalFormModal('authority_to_act')">
                                    <span class="lf-menu-icon lf-menu-icon--green"><i class="fa-solid fa-stamp"></i></span>
                                    <span class="lf-menu-text">
                                        <strong>Authority to Act</strong>
                                        <small>Client authorisation form</small>
                                    </span>
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
            <div class="preview-pane file-preview-container preview-container-legal-forms client-doc-preview-pane">
                <div class="client-doc-preview-empty">
                    <i class="fa-solid fa-file-lines client-doc-preview-empty-icon" aria-hidden="true"></i>
                    <p class="preview-placeholder-text"><strong>Form Preview</strong></p>
                    <p class="preview-placeholder-text">Select a form from the list to preview it here</p>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Upload Legal Form Modal --}}
<div class="modal fade legal-form-modal" id="legalFormUploadModal" tabindex="-1" aria-labelledby="legalFormUploadModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered legal-form-upload-dialog">
        <div class="modal-content legal-form-modal-content">
            <div class="modal-header legal-form-modal-header">
                <div>
                    <h5 class="modal-title" id="legalFormUploadModalLabel">Upload Form</h5>
                    <p class="legal-form-upload-subtitle mb-0">Add an existing document to this client's legal forms</p>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body legal-form-upload-body">
                <form id="legalFormUploadForm" autocomplete="off">
                    @csrf
                    <input type="hidden" name="client_id" value="{{ $fetchedData->id }}">
                    <input type="hidden" name="client_matter_id" id="lf_upload_client_matter_id" value="">
                    <input type="hidden" name="form_type" id="lf_upload_form_type" value="">

                    <div class="legal-form-upload-section">
                        <label class="legal-form-upload-label">What type of form is this?</label>
                        <div class="legal-form-type-cards" role="group" aria-label="Form type">
                            <button type="button" class="legal-form-type-card" data-form-type="short_costs_disclosure" onclick="selectLegalFormUploadType('short_costs_disclosure')">
                                <span class="legal-form-type-card__icon legal-form-type-card__icon--blue"><i class="fa-solid fa-file-invoice-dollar"></i></span>
                                <span class="legal-form-type-card__label">Short Costs Disclosure</span>
                            </button>
                            <button type="button" class="legal-form-type-card" data-form-type="cost_agreement" onclick="selectLegalFormUploadType('cost_agreement')">
                                <span class="legal-form-type-card__icon legal-form-type-card__icon--purple"><i class="fa-solid fa-file-contract"></i></span>
                                <span class="legal-form-type-card__label">Long Cost Disclosure</span>
                            </button>
                            <button type="button" class="legal-form-type-card" data-form-type="authority_to_act" onclick="selectLegalFormUploadType('authority_to_act')">
                                <span class="legal-form-type-card__icon legal-form-type-card__icon--green"><i class="fa-solid fa-stamp"></i></span>
                                <span class="legal-form-type-card__label">Authority to Act</span>
                            </button>
                        </div>
                    </div>

                    <div class="legal-form-upload-section">
                        <label class="legal-form-upload-label" for="lf_upload_dropzone">Choose your file</label>
                        <div class="legal-form-upload-dropzone" id="lf_upload_dropzone" tabindex="0" role="button" aria-label="Click or drag a document file here">
                            <input type="file"
                                   name="file"
                                   id="lf_upload_file"
                                   class="legal-form-upload-file-input"
                                   accept=".pdf,.doc,.docx,.txt,.rtf,.odt,.xls,.xlsx,.ppt,.pptx,.csv">
                            <div class="legal-form-upload-dropzone__empty" id="lf_upload_dropzone_empty">
                                <span class="legal-form-upload-dropzone__icon"><i class="fa-solid fa-cloud-arrow-up"></i></span>
                                <span class="legal-form-upload-dropzone__title">Drag & drop your file here</span>
                                <span class="legal-form-upload-dropzone__hint">or click to browse</span>
                                <span class="legal-form-upload-dropzone__types">PDF, Word, Excel, PowerPoint, text</span>
                            </div>
                            <div class="legal-form-upload-dropzone__selected d-none" id="lf_upload_dropzone_selected">
                                <span class="legal-form-upload-dropzone__file-icon"><i class="fa-solid fa-file-lines"></i></span>
                                <span class="legal-form-upload-dropzone__file-name" id="lf_upload_file_name"></span>
                                <button type="button" class="legal-form-upload-clear-btn" onclick="clearLegalFormUploadFile(event)" title="Remove file">
                                    <i class="fa-solid fa-xmark"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="legal-form-upload-fields">
                        <div class="legal-form-upload-field">
                            <label class="legal-form-upload-label" for="lf_upload_matter_reference">Matter</label>
                            @if($lfClientMatters->isEmpty())
                                <select id="lf_upload_matter_reference" name="matter_reference" class="form-control" disabled>
                                    <option value="">No matters for this client</option>
                                </select>
                            @else
                                <select id="lf_upload_matter_reference" name="matter_reference" class="form-control" required>
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
                        <div class="legal-form-upload-field legal-form-upload-field--date">
                            <label class="legal-form-upload-label" for="lf_upload_form_date">Date</label>
                            <input type="date" name="form_date" id="lf_upload_form_date" class="form-control" value="{{ date('Y-m-d') }}">
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer legal-form-modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveLegalFormUploadBtn" onclick="saveLegalFormUpload()">
                    <i class="fa-solid fa-file-arrow-up"></i> Upload
                </button>
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
                <form id="legalFormForm" autocomplete="off">
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

    const FORM_TYPE_LABELS = @json(\App\Models\ClientLegalForm::FORM_TYPES);

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
        ['legalFormModal', 'legalFormUploadModal'].forEach(function(modalId) {
            var modalEl = document.getElementById(modalId);
            if (modalEl && modalEl.parentElement !== document.body) {
                document.body.appendChild(modalEl);
            }
        });
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

    var lfMatterRefSelect = document.getElementById('lf_matter_reference');
    if (lfMatterRefSelect) {
        lfMatterRefSelect.addEventListener('change', syncLegalFormMatterFromSelect);
    }

    syncLegalFormMatterFromSelect();

    function syncLegalFormUploadMatterFromSelect() {
        var matterRefSelect = document.getElementById('lf_upload_matter_reference');
        var matterIdInput = document.getElementById('lf_upload_client_matter_id');
        if (!matterRefSelect || !matterIdInput || matterRefSelect.disabled) {
            return;
        }
        var opt = matterRefSelect.options[matterRefSelect.selectedIndex];
        matterIdInput.value = (opt && opt.getAttribute('data-matter-id')) ? opt.getAttribute('data-matter-id') : '';
    }

    function selectLegalFormUploadMatterByRef(matterRef) {
        var matterRefSelect = document.getElementById('lf_upload_matter_reference');
        if (!matterRefSelect || matterRefSelect.disabled || !matterRef) {
            syncLegalFormUploadMatterFromSelect();
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
        syncLegalFormUploadMatterFromSelect();
    }

    var lfUploadMatterRefSelect = document.getElementById('lf_upload_matter_reference');
    if (lfUploadMatterRefSelect) {
        lfUploadMatterRefSelect.addEventListener('change', syncLegalFormUploadMatterFromSelect);
    }
    syncLegalFormUploadMatterFromSelect();

    var LF_UPLOAD_BLOCKED_EXTENSIONS = ['jpg','jpeg','png','gif','webp','bmp','svg','ico','exe','bat','cmd','sh','js','php','py','html','htm'];

    function updateLegalFormUploadFileDisplay() {
        var fileInput = document.getElementById('lf_upload_file');
        var emptyState = document.getElementById('lf_upload_dropzone_empty');
        var selectedState = document.getElementById('lf_upload_dropzone_selected');
        var fileNameEl = document.getElementById('lf_upload_file_name');
        var dropzone = document.getElementById('lf_upload_dropzone');
        if (!fileInput || !emptyState || !selectedState || !fileNameEl || !dropzone) {
            return;
        }

        if (fileInput.files && fileInput.files.length) {
            fileNameEl.textContent = fileInput.files[0].name;
            emptyState.classList.add('d-none');
            selectedState.classList.remove('d-none');
            dropzone.classList.add('has-file');
        } else {
            emptyState.classList.remove('d-none');
            selectedState.classList.add('d-none');
            fileNameEl.textContent = '';
            dropzone.classList.remove('has-file');
        }
    }

    window.clearLegalFormUploadFile = function(event) {
        if (event) {
            event.preventDefault();
            event.stopPropagation();
        }
        var fileInput = document.getElementById('lf_upload_file');
        if (fileInput) {
            fileInput.value = '';
        }
        updateLegalFormUploadFileDisplay();
    };

    window.selectLegalFormUploadType = function(formType) {
        var hiddenInput = document.getElementById('lf_upload_form_type');
        if (hiddenInput) {
            hiddenInput.value = formType || '';
        }
        document.querySelectorAll('.legal-form-type-card').forEach(function(card) {
            card.classList.toggle('is-selected', card.getAttribute('data-form-type') === formType);
        });
    };

    function initLegalFormUploadDropzone() {
        var dropzone = document.getElementById('lf_upload_dropzone');
        var fileInput = document.getElementById('lf_upload_file');
        if (!dropzone || !fileInput) {
            return;
        }

        dropzone.addEventListener('click', function(e) {
            if (e.target.closest('.legal-form-upload-clear-btn')) {
                return;
            }
            fileInput.click();
        });

        dropzone.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                fileInput.click();
            }
        });

        fileInput.addEventListener('change', updateLegalFormUploadFileDisplay);

        ['dragenter', 'dragover'].forEach(function(eventName) {
            dropzone.addEventListener(eventName, function(e) {
                e.preventDefault();
                e.stopPropagation();
                dropzone.classList.add('is-dragover');
            });
        });

        ['dragleave', 'drop'].forEach(function(eventName) {
            dropzone.addEventListener(eventName, function(e) {
                e.preventDefault();
                e.stopPropagation();
                dropzone.classList.remove('is-dragover');
            });
        });

        dropzone.addEventListener('drop', function(e) {
            var files = e.dataTransfer && e.dataTransfer.files;
            if (!files || !files.length) {
                return;
            }
            fileInput.files = files;
            updateLegalFormUploadFileDisplay();
        });
    }

    initLegalFormUploadDropzone();

    window.openLegalFormUploadModal = function() {
        document.getElementById('legalFormUploadForm').reset();
        document.getElementById('lf_upload_form_date').value = new Date().toISOString().split('T')[0];
        selectLegalFormUploadType('');
        clearLegalFormUploadFile();

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
        selectLegalFormUploadMatterByRef(sidebarMatterRef);

        var modal = new bootstrap.Modal(document.getElementById('legalFormUploadModal'));
        modal.show();
    };

    window.saveLegalFormUpload = function() {
        var form = document.getElementById('legalFormUploadForm');
        syncLegalFormUploadMatterFromSelect();

        var formTypeInput = document.getElementById('lf_upload_form_type');
        if (!formTypeInput || !formTypeInput.value) {
            if (typeof iziToast !== 'undefined' && typeof iziToast.error === 'function') {
                iziToast.error({ message: 'Please choose a form type.', position: 'topRight' });
            } else {
                alert('Please choose a form type.');
            }
            return;
        }

        var fileInput = document.getElementById('lf_upload_file');
        if (!fileInput || !fileInput.files || !fileInput.files.length) {
            if (typeof iziToast !== 'undefined' && typeof iziToast.error === 'function') {
                iziToast.error({ message: 'Please select a document file to upload.', position: 'topRight' });
            } else {
                alert('Please select a document file to upload.');
            }
            return;
        }

        var matterRefSelect = document.getElementById('lf_upload_matter_reference');
        if (matterRefSelect && !matterRefSelect.disabled && !matterRefSelect.value) {
            if (typeof iziToast !== 'undefined' && typeof iziToast.error === 'function') {
                iziToast.error({ message: 'Please select a matter reference.', position: 'topRight' });
            } else {
                alert('Please select a matter reference.');
            }
            return;
        }

        var blockedExtensions = LF_UPLOAD_BLOCKED_EXTENSIONS;
        var fileName = fileInput.files[0].name.toLowerCase();
        var fileExt = fileName.includes('.') ? fileName.split('.').pop() : '';
        if (blockedExtensions.indexOf(fileExt) !== -1) {
            if (typeof iziToast !== 'undefined' && typeof iziToast.error === 'function') {
                iziToast.error({ message: 'This file type is not allowed. Please upload a document file only.', position: 'topRight' });
            } else {
                alert('This file type is not allowed. Please upload a document file only.');
            }
            return;
        }

        var formData = new FormData(form);
        var btn = document.getElementById('saveLegalFormUploadBtn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Uploading...';

        $.ajax({
            url: LF_BASE + '/upload',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            success: function(response) {
                if (response.success) {
                    bootstrap.Modal.getInstance(document.getElementById('legalFormUploadModal')).hide();
                    loadLegalForms();
                    if (typeof iziToast !== 'undefined' && typeof iziToast.success === 'function') {
                        iziToast.success({ message: response.message || 'Form uploaded successfully!', position: 'topRight' });
                    } else {
                        alert(response.message || 'Form uploaded successfully!');
                    }
                } else {
                    if (typeof iziToast !== 'undefined' && typeof iziToast.error === 'function') {
                        iziToast.error({ message: response.message || 'Failed to upload form.', position: 'topRight' });
                    } else {
                        alert(response.message || 'Failed to upload form.');
                    }
                }
            },
            error: function(xhr) {
                var msg = 'Failed to upload form.';
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
                btn.innerHTML = '<i class="fa-solid fa-file-arrow-up"></i> Upload';
            }
        });
    };

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
            modalTitle.textContent = 'Create Long Cost Disclosure';
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

    function resetLegalFormPreviewPane() {
        var pane = document.querySelector('.preview-container-legal-forms');
        if (!pane) {
            return;
        }
        pane.innerHTML = ''
            + '<div class="client-doc-preview-empty">'
            + '<i class="fa-solid fa-file-lines client-doc-preview-empty-icon" aria-hidden="true"></i>'
            + '<p class="preview-placeholder-text"><strong>Form Preview</strong></p>'
            + '<p class="preview-placeholder-text">Select a form from the list to preview it here</p>'
            + '</div>';
    }

    window.previewLegalForm = function(formId, formLabel, previewType) {
        if (!formId) {
            return;
        }

        var previewUrl = LF_BASE + '/' + formId + '/preview';
        var downloadUrl = LF_BASE + '/' + formId + '/download';
        var fileType = previewType || 'docx';
        var rows = document.querySelectorAll('.legal-form-row');
        rows.forEach(function(row) {
            row.classList.remove('is-preview-active');
        });
        var activeRow = document.getElementById('legal-form-row-' + formId);
        if (activeRow) {
            activeRow.classList.add('is-preview-active');
        }

        if (typeof window.previewFile === 'function') {
            window.previewFile(fileType, previewUrl, 'preview-container-legal-forms', formLabel || 'Legal Form');
            window.setTimeout(function() {
                var pane = document.querySelector('.preview-container-legal-forms');
                if (!pane) {
                    return;
                }
                var downloadBtn = pane.querySelector('.client-doc-preview-download-btn');
                if (downloadBtn) {
                    downloadBtn.href = downloadUrl;
                }
                var openBtn = pane.querySelector('.client-doc-preview-open-btn');
                if (openBtn) {
                    openBtn.href = downloadUrl;
                }
                var officeDownload = pane.querySelector('.client-doc-preview-office-bar a');
                if (officeDownload) {
                    officeDownload.href = downloadUrl;
                }
            }, 50);
        } else {
            window.open(previewUrl, '_blank', 'noopener');
        }
    };

    function formatLegalFormListAmount(form) {
        var amount = null;

        if (form.estimated_total != null && parseFloat(form.estimated_total) > 0) {
            amount = parseFloat(form.estimated_total);
        } else if (form.fixed_fee_amount != null && parseFloat(form.fixed_fee_amount) > 0) {
            amount = parseFloat(form.fixed_fee_amount);
        } else if (form.retainer_amount != null && parseFloat(form.retainer_amount) > 0) {
            amount = parseFloat(form.retainer_amount);
        } else {
            var fees = parseFloat(form.estimated_legal_fees) || 0;
            var disb = parseFloat(form.estimated_disbursements) || 0;
            var barr = parseFloat(form.estimated_barrister_fees) || 0;
            var gst = parseFloat(form.gst_amount);
            if (isNaN(gst)) {
                gst = fees * 0.10;
            }
            var total = fees + disb + barr + gst;
            if (total > 0) {
                amount = total;
            }
        }

        if (amount === null || isNaN(amount)) {
            return null;
        }

        return '$' + amount.toLocaleString('en-AU', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function renderLegalFormsList(forms) {
        var listEl = document.getElementById('legal-forms-list');
        var countEl = document.getElementById('legal-forms-count');
        resetLegalFormPreviewPane();

        if (countEl) {
            countEl.textContent = forms && forms.length ? String(forms.length) : '0';
        }

        if (!forms || forms.length === 0) {
            listEl.innerHTML = '<div class="legal-forms-empty"><div class="legal-forms-empty-icon"><i class="fa-solid fa-file-signature"></i></div><p>No legal forms yet</p><p class="text-muted">Use <strong>Create</strong> to generate a form or <strong>Upload</strong> to add an existing document.</p></div>';
            return;
        }

        var html = '<div class="legal-forms-card-list">';

        forms.forEach(function(form) {
            var label = FORM_TYPE_LABELS[form.form_type] || form.form_type;
            var color = FORM_TYPE_COLORS[form.form_type] || '#6b7280';
            var date = form.form_date ? new Date(form.form_date).toLocaleDateString('en-AU') : new Date(form.created_at).toLocaleDateString('en-AU');
            var matterRef = form.matter_reference || (form.matter ? form.matter.client_unique_matter_no : '');
            var previewLabel = label + (matterRef ? ' (' + matterRef + ')' : '');
            var safePreviewLabel = previewLabel.replace(/\\/g, '\\\\').replace(/'/g, "\\'");
            var amountText = formatLegalFormListAmount(form);
            var attachTitle = form.attachment_original_name ? String(form.attachment_original_name).replace(/"/g, '&quot;') : '';
            var attachName = form.attachment_original_name || '';
            attachName = attachName.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
            if (attachName.length > 18) {
                attachName = attachName.substring(0, 15) + '...';
            }
            var previewType = 'docx';
            if (form.is_uploaded && form.attachment_original_name) {
                var uploadedExt = String(form.attachment_original_name).split('.').pop().toLowerCase();
                if (uploadedExt === 'pdf') {
                    previewType = 'pdf';
                } else if (['doc', 'docx'].indexOf(uploadedExt) !== -1) {
                    previewType = 'docx';
                } else {
                    previewType = uploadedExt || 'docx';
                }
            }
            var downloadTitle = form.is_uploaded ? 'Download file' : 'Download Word';

            html += '<div class="legal-form-list-item legal-form-row" id="legal-form-row-' + form.id + '" data-form-id="' + form.id + '" data-preview-label="' + previewLabel.replace(/"/g, '&quot;') + '" data-preview-type="' + previewType + '" style="--lf-accent:' + color + ';" role="button" tabindex="0">';
            html += '<div class="legal-form-list-item__main" onclick="previewLegalForm(' + form.id + ', \'' + safePreviewLabel + '\', \'' + previewType + '\')">';
            html += '<div class="legal-form-list-item__title">' + label + (form.is_uploaded ? ' <span class="legal-form-uploaded-badge">Uploaded</span>' : '') + '</div>';
            html += '<dl class="legal-form-list-item__meta">';
            if (amountText) {
                html += '<div class="legal-form-meta-item legal-form-meta-item--amount"><dt>Amount</dt><dd>' + amountText + '</dd></div>';
            }
            html += '<div class="legal-form-meta-item"><dt>Date</dt><dd>' + date + '</dd></div>';
            if (matterRef) {
                html += '<div class="legal-form-meta-item"><dt>Matter</dt><dd>' + matterRef + '</dd></div>';
            }
            if (form.attachment_path && attachName) {
                html += '<div class="legal-form-meta-item legal-form-meta-item--file"><dt>File</dt><dd><a href="' + LF_BASE + '/' + form.id + '/attachment" title="' + attachTitle + '" onclick="event.stopPropagation();">' + attachName + '</a></dd></div>';
            }
            html += '</dl></div>';
            html += '<div class="legal-form-list-item__actions" onclick="event.stopPropagation();">';
            html += '<a href="' + LF_BASE + '/' + form.id + '/download" class="legal-form-action-btn" title="' + downloadTitle + '"><i class="fa-solid fa-download"></i></a>';
            html += '<button type="button" class="legal-form-action-btn legal-form-action-btn--danger" onclick="deleteLegalForm(' + form.id + ')" title="Delete"><i class="fa-solid fa-trash"></i></button>';
            html += '</div></div>';
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

    $(document).on('keydown', '.legal-form-row', function(e) {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            var id = $(this).data('form-id');
            var label = $(this).data('preview-label') || '';
            var previewType = $(this).data('preview-type') || 'docx';
            previewLegalForm(id, label, previewType);
        }
    });

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
