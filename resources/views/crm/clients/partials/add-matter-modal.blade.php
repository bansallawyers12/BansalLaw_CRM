@if(is_array($matterFormForLead ?? null))
    <div id="addMatterModal" class="modal add-matter-modal" style="display: none; z-index: 9998;" role="dialog" aria-modal="true" aria-labelledby="addMatterModalTitle" onclick="addMatterModalBackdropClick(event)">
        <div class="modal-content add-matter-modal__content" style="max-width: 720px;" onclick="event.stopPropagation()">
            <div class="modal-header add-matter-modal__header">
                <h3 id="addMatterModalTitle" class="add-matter-modal__title">Add matter</h3>
                <button type="button" class="close-btn add-matter-modal__close" onclick="closeAddMatterModal()" aria-label="Close">&times;</button>
            </div>
            <div class="modal-body add-matter-modal__body">
                <p class="text-muted add-matter-modal__intro">Creates an active matter for {{ $fetchedData->first_name }} {{ $fetchedData->last_name }} ({{ $__crmEditLeadType ? 'Lead' : 'Client' }} ID: {{ $fetchedData->client_id }}).</p>
                <div id="editAddMatterMsg" class="add-matter-modal__msg"></div>
                <div class="row add-matter-modal__grid">
                    <div class="col-md-6 add-matter-modal__field">
                        <div class="form-group">
                            <label for="edit_add_matter_matter_id">Matter type <span class="text-danger">*</span></label>
                            <select class="form-control" id="edit_add_matter_matter_id">
                                <option value="">Select matter</option>
                                @foreach($matterFormForLead['mattersForAdd'] as $m)
                                    <option value="{{ $m->id }}">{{ $m->title }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6 add-matter-modal__field">
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
                    <div class="col-md-6 add-matter-modal__field">
                        <div class="form-group">
                            <label for="edit_add_matter_legal_practitioner">Legal practitioner <span class="text-danger">*</span></label>
                            <select class="form-control" id="edit_add_matter_legal_practitioner">
                                <option value="">Select</option>
                                @foreach($matterFormForLead['legalPractitioners'] as $st)
                                    <option value="{{ $st->id }}">{{ $st->first_name }} {{ $st->last_name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6 add-matter-modal__field">
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
                    <div class="col-md-6 add-matter-modal__field">
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
                    <div class="col-md-6 add-matter-modal__field">
                        <div class="form-group">
                            <label for="edit_add_matter_date_of_incidence">Date of Incident <small class="text-muted">(optional)</small></label>
                            <input type="date" class="form-control" id="edit_add_matter_date_of_incidence" name="date_of_incidence" autocomplete="off">
                        </div>
                    </div>
                    <div class="col-md-6 add-matter-modal__field">
                        <div class="form-group">
                            <label for="edit_add_matter_incidence_type">Matter subtype <small class="text-muted">(optional)</small></label>
                            <input type="text" class="form-control" id="edit_add_matter_incidence_type" name="incidence_type" maxlength="255" placeholder="e.g. parenting application, money recovery">
                        </div>
                    </div>
                </div>
                <div class="row add-matter-modal__grid add-matter-modal__case-row">
                    <div class="col-md-12 add-matter-modal__field">
                        <div class="form-group">
                            <label for="edit_add_matter_case_detail">Case detail <small class="text-muted">(optional)</small></label>
                            <textarea class="form-control add-matter-modal__textarea" id="edit_add_matter_case_detail" name="case_detail" rows="4" maxlength="5000" placeholder="Brief description, instructions, or context for this matter"></textarea>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer add-matter-modal__footer">
                <button type="button" class="btn btn-secondary" onclick="closeAddMatterModal()">Cancel</button>
                <button type="button" class="btn btn-primary" id="editAddMatterSubmitBtn" onclick="submitLeadMatterFromEdit()">Create matter</button>
            </div>
        </div>
    </div>
@endif
