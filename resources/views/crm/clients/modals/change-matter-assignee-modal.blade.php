<!-- Change Matter Assignee / matter details modal -->
@php
    $__crmSolicitorStaff = \App\Services\ClientEditService::staffSelectableForSolicitorRole();
    $__crmPersonResponsibleStaff = \App\Services\ClientEditService::staffSelectableForPersonResponsibleRole();
    $__crmPersonAssistingStaff = \App\Services\ClientEditService::staffSelectableForPersonAssistingRole();
    $__crmOffices = \App\Models\Branch::orderBy('office_name')->get(['id', 'office_name']);
    $__crmStaffOptionLabel = static function ($staff): string {
        return trim(($staff->first_name ?? '') . ' ' . ($staff->last_name ?? ''));
    };
@endphp
<div class="modal fade custom_modal" id="changeMatterAssigneeModal" tabindex="-1" role="dialog" aria-labelledby="change_MatterModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-xl modal-dialog-scrollable">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="change_MatterModalLabel">Edit matter details</h5>
				<button type="button" class="crm-modal-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body">
                <div id="change_matter_assignee_loading" class="change-matter-modal__loading" hidden>
                    <div class="change-matter-modal__loading-inner">
                        <i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i>
                        <span>Loading matter details…</span>
                    </div>
                </div>
                <form method="post" action="{{URL::to('/clients/updateClientMatterAssignee')}}" name="change_matter_assignee" autocomplete="off" id="change_matter_assignee">
				    @csrf
                    <input type="hidden" name="client_id" value="{{$fetchedData->id}}">
                    <input type="hidden" name="user_id" value="{{@Auth::user()->id}}">
                    <input type="hidden" name="selectedMatterLM" id="selectedMatterLM" value="">
                    <input type="hidden" id="change_matter_initial_sel_matter_id" value="">
                    <input type="hidden" name="opposing_parties_json" id="change_matter_opposing_parties_json" value="[]">

                    <div class="row">
                        <div class="col-12">
                            <div class="form-group">
                                <label for="change_sel_legal_practitioner_id">Legal Practitioner <span class="span_req">*</span></label>
                                <select data-valid="required" class="form-control" name="legal_practitioner" id="change_sel_legal_practitioner_id">
                                    <option value="">Select responsible solicitor</option>
                                    @foreach($__crmSolicitorStaff as $migAgntlist)
                                        <option value="{{$migAgntlist->id}}">{{ $__crmStaffOptionLabel($migAgntlist) }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="form-group">
                                <label for="change_sel_person_responsible_id">Person Responsible <span class="span_req">*</span></label>
                                <select data-valid="required" class="form-control" name="person_responsible" id="change_sel_person_responsible_id">
                                    <option value="">Select Person Responsible</option>
                                    @foreach($__crmPersonResponsibleStaff as $perreslist)
                                        <option value="{{$perreslist->id}}">{{ $__crmStaffOptionLabel($perreslist) }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="form-group">
                                <label for="change_sel_person_assisting_id">Person Assisting <span class="span_req">*</span></label>
                                <select data-valid="required" class="form-control" name="person_assisting" id="change_sel_person_assisting_id">
                                    <option value="">Select Person Assisting</option>
                                    @foreach($__crmPersonAssistingStaff as $perassislist)
                                        <option value="{{$perassislist->id}}">{{ $__crmStaffOptionLabel($perassislist) }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="form-group">
                                <label for="change_office_id">Handling Office</label>
                                <select class="form-control" name="office_id" id="change_office_id">
                                    <option value="">Select Office</option>
                                    @foreach($__crmOffices as $office)
                                        <option value="{{$office->id}}">{{$office->office_name}}</option>
                                    @endforeach
                                </select>
                                <small class="form-text text-muted">
                                    <i class="fa-solid fa-building"></i> Optional - Leave blank to keep current office
                                </small>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="form-group">
                                <label for="change_sel_matter_id">Law matter type</label>
                                <select class="form-control" name="sel_matter_id" id="change_sel_matter_id">
                                    <option value="">— Loading —</option>
                                </select>
                                <small class="form-text text-muted">Changing type may not match the matter reference prefix; update details if needed.</small>
                            </div>
                        </div>

                        @if(\Illuminate\Support\Facades\Schema::hasColumn('client_matters', 'our_party_role'))
                        <div class="col-12">
                            <div class="form-group">
                                <label for="change_matter_our_party_role">Our client&rsquo;s role <span class="text-danger">*</span></label>
                                <select class="form-control" name="our_party_role" id="change_matter_our_party_role" data-valid="required">
                                    <option value="">— Select role —</option>
                                </select>
                            </div>
                        </div>
                        @endif

                        <div class="col-12">
                            <label>Other parties <small class="text-muted">(optional)</small></label>
                            <p class="text-muted small mb-1"><i class="fa-solid fa-circle-info"></i> Run conflict check on Personal Details before engaging.</p>
                            <div id="change_matter_opposing_parties_container" class="mb-2"></div>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="change_matter_add_opposing_btn">
                                <i class="fa-solid fa-plus"></i> Add other party
                            </button>
                        </div>

                        @if(\Illuminate\Support\Facades\Schema::hasColumn('client_matters', 'incidence_type'))
                        <div class="col-12">
                            <div class="form-group">
                                <label for="change_matter_incidence_type">Matter subtype</label>
                                <input type="text" class="form-control" name="incidence_type" id="change_matter_incidence_type" maxlength="255" placeholder="e.g. Money recovery, parenting application" autocomplete="off">
                            </div>
                        </div>
                        @endif
                        @if(\Illuminate\Support\Facades\Schema::hasColumn('client_matters', 'date_of_incidence'))
                        <div class="col-12 col-md-6">
                            <div class="form-group">
                                <label for="change_matter_date_of_incidence">Date of incidence</label>
                                <input type="date" class="form-control" name="date_of_incidence" id="change_matter_date_of_incidence" autocomplete="off">
                            </div>
                        </div>
                        @endif
                        @if(\Illuminate\Support\Facades\Schema::hasColumn('client_matters', 'case_detail'))
                        <div class="col-12">
                            <div class="form-group">
                                <label for="change_matter_case_detail">Case detail</label>
                                <textarea class="form-control" name="case_detail" id="change_matter_case_detail" rows="4" maxlength="5000" placeholder="Brief description or context for this matter"></textarea>
                            </div>
                        </div>
                        @endif

                        <div class="col-12 text-right mt-2">
                            <button type="button" class="btn btn-primary" id="change_matter_assignee_save_btn" onclick="if(typeof window.prepareChangeMatterAssigneeSubmit === 'function') { window.prepareChangeMatterAssigneeSubmit(); } else { customValidate('change_matter_assignee'); }">Save</button>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
				</form>
			</div>
		</div>
	</div>
</div>

<script>
window.MATTER_PARTY_ROLES_BY_STREAM = @json(config('matter_streams.party_roles_by_stream', []));
window.OTHER_PARTY_SEARCH_URL = @json(route('api.search.other.party'));
window.CONTACT_PERSON_SEARCH_URL = @json(route('api.search.contact.person'));
</script>
