@if(is_array($matterFormForLead ?? null))
@php
    if (! empty($fetchedData->is_company)) {
        $__addMatterSubjectName = trim((string) (optional($fetchedData->company)->company_name ?? ''));
        if ($__addMatterSubjectName === '') {
            $__addMatterSubjectName = trim((string) ($fetchedData->client_id ?? 'Company'));
        }
    } else {
        $__addMatterSubjectName = trim(($fetchedData->first_name ?? '') . ' ' . ($fetchedData->last_name ?? ''));
    }
@endphp
<div class="modal fade custom_modal add-matter-modal" id="addMatterModal" tabindex="-1" role="dialog" aria-labelledby="addMatterModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addMatterModalTitle">Add matter</h5>
                <button type="button" class="crm-modal-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted add-matter-modal__intro mb-3">
                    Creates an active matter for {{ $__addMatterSubjectName }}
                    ({{ $__crmEditLeadType ? 'Lead' : 'Client' }} ID: {{ $fetchedData->client_id }}).
                </p>
                <div id="editAddMatterMsg" class="add-matter-modal__msg"></div>

                <div class="row">
                    <div class="col-12 col-md-6">
                        <div class="form-group">
                            <label for="edit_add_matter_matter_id">Matter type <span class="text-danger">*</span></label>
                            <select class="form-control" id="edit_add_matter_matter_id" data-placeholder="Search matter type…">
                                <option value="">Select matter</option>
                                @foreach($matterFormForLead['mattersForAdd'] as $m)
                                    <option value="{{ $m->id }}" data-stream="{{ $m->stream ?? 'general' }}">{{ $m->title }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <div class="form-group">
                            <label for="edit_add_matter_office_id">Handling office</label>
                            <select class="form-control" id="edit_add_matter_office_id">
                                <option value="">Default (your office)</option>
                                @foreach($matterFormForLead['branchOffices'] as $office)
                                    <option value="{{ $office->id }}" @selected(optional(Auth::user())->office_id == $office->id)>{{ $office->office_name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="col-12 col-md-6">
                        <div class="form-group">
                            <label for="edit_add_matter_legal_practitioner">Legal practitioner <span class="text-danger">*</span></label>
                            <select class="form-control" id="edit_add_matter_legal_practitioner" data-placeholder="Search legal practitioner…">
                                <option value="">Select</option>
                                @foreach($matterFormForLead['legalPractitioners'] as $st)
                                    <option value="{{ $st->id }}">{{ $st->first_name }} {{ $st->last_name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <div class="form-group">
                            <label for="edit_add_matter_person_responsible">Person responsible</label>
                            <select class="form-control" id="edit_add_matter_person_responsible">
                                <option value="">—</option>
                                @foreach($matterFormForLead['personResponsibleOptions'] as $st)
                                    <option value="{{ $st->id }}">{{ $st->first_name }} {{ $st->last_name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="col-12 col-md-6">
                        <div class="form-group">
                            <label for="edit_add_matter_person_assisting">Person assisting</label>
                            <select class="form-control" id="edit_add_matter_person_assisting">
                                <option value="">—</option>
                                @foreach($matterFormForLead['personAssistingOptions'] as $st)
                                    <option value="{{ $st->id }}">{{ $st->first_name }} {{ $st->last_name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <div class="form-group">
                            <label for="edit_add_matter_date_of_incidence">Date of incidence <small class="text-muted">(optional)</small></label>
                            <input type="date" class="form-control" id="edit_add_matter_date_of_incidence" name="date_of_incidence" autocomplete="off">
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="form-group">
                            <label for="edit_add_matter_incidence_type">Matter subtype <small class="text-muted">(optional)</small></label>
                            <input type="text" class="form-control" id="edit_add_matter_incidence_type" name="incidence_type" maxlength="255" placeholder="e.g. parenting application, money recovery" autocomplete="off">
                        </div>
                    </div>

                    <div class="col-12 col-md-6">
                        <div class="form-group">
                            <label for="edit_add_matter_our_party_role">Our client&rsquo;s role <span class="text-danger">*</span></label>
                            <select class="form-control" id="edit_add_matter_our_party_role" required>
                                <option value="">— Select role —</option>
                            </select>
                        </div>
                    </div>

                    <div class="col-12">
                        <label>Other parties <small class="text-muted">(optional)</small></label>
                        <p class="text-muted small mb-1"><i class="fa-solid fa-circle-info"></i> Run conflict check on Personal Details before engaging.</p>
                        <div id="quick_add_opposing_parties_wrap" class="mb-2"></div>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="quick_add_opposing_party_btn" onclick="typeof quickAddOpposingPartyRow==='function'&&quickAddOpposingPartyRow(event)">
                            <i class="fa-solid fa-plus"></i> Add other party
                        </button>
                    </div>

                    <div class="col-12">
                        <div class="form-group">
                            <label for="edit_add_matter_case_detail">Case detail <small class="text-muted">(optional)</small></label>
                            <textarea class="form-control" id="edit_add_matter_case_detail" name="case_detail" rows="4" maxlength="5000" placeholder="Brief description, instructions, or context for this matter"></textarea>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="editAddMatterSubmitBtn" onclick="submitLeadMatterFromEdit()">Create matter</button>
            </div>
        </div>
    </div>
</div>
@endif
