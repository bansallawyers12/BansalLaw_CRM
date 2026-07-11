<!-- Change Matter Assignee / matter details modal -->
@php
    $__crmSolicitorStaff = \App\Services\ClientEditService::staffSelectableForSolicitorRole();
    $__crmPersonResponsibleStaff = \App\Services\ClientEditService::staffSelectableForPersonResponsibleRole();
    $__crmPersonAssistingStaff = \App\Services\ClientEditService::staffSelectableForPersonAssistingRole();
@endphp
<div class="modal fade custom_modal" id="changeMatterAssigneeModal" tabindex="-1" role="dialog" aria-labelledby="change_MatterModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="change_MatterModalLabel">Edit matter details</h5>
				<button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body">
                <form method="post" action="{{URL::to('/clients/updateClientMatterAssignee')}}" name="change_matter_assignee" autocomplete="off" id="change_matter_assignee">
				    @csrf
                    <div class="row">
                        <input type="hidden" name="client_id" value="{{$fetchedData->id}}">
                        <input type="hidden" name="user_id" value="{{@Auth::user()->id}}">
                        <input type="hidden" name="selectedMatterLM" id="selectedMatterLM" value="">
                        <div class="col-12 col-md-12 col-lg-12">
                            <div class="form-group">
                                <label for="change_sel_legal_practitioner_id">Legal Practitioner <span class="span_req">*</span></label>
                                <select data-valid="required" class="form-control crm-ts-plain" name="legal_practitioner" id="change_sel_legal_practitioner_id">
                                    <option value="">Select responsible solicitor</option>
                                    @foreach($__crmSolicitorStaff as $migAgntlist)
                                        <option value="{{$migAgntlist->id}}">{{@$migAgntlist->first_name}} {{@$migAgntlist->last_name}}@if(!empty($migAgntlist->email)) ({{@$migAgntlist->email}})@endif</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-12 col-md-12 col-lg-12">
                            <div class="form-group">
                                <label for="person_responsible">Person Responsible <span class="span_req">*</span></label>
                                <select data-valid="required" class="form-control crm-ts-plain" name="person_responsible" id="change_sel_person_responsible_id">
                                    <option value="">Select Person Responsible</option>
                                    @foreach($__crmPersonResponsibleStaff as $perreslist)
                                        <option value="{{$perreslist->id}}">{{@$perreslist->first_name}} {{@$perreslist->last_name}}@if(!empty($perreslist->email)) ({{@$perreslist->email}})@endif</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-12 col-md-12 col-lg-12">
                            <div class="form-group">
                                <label for="person_assisting">Person Assisting <span class="span_req">*</span></label>
                                <select data-valid="required" class="form-control crm-ts-plain" name="person_assisting" id="change_sel_person_assisting_id">
                                    <option value="">Select Person Assisting</option>
                                    @foreach($__crmPersonAssistingStaff as $perassislist)
                                        <option value="{{$perassislist->id}}">{{@$perassislist->first_name}} {{@$perassislist->last_name}}@if(!empty($perassislist->email)) ({{@$perassislist->email}})@endif</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-12 col-md-12 col-lg-12">
                            <div class="form-group">
                                <label for="office_id">Handling Office</label>
                                <select class="form-control crm-ts-plain" name="office_id" id="change_office_id">
                                    <option value="">Select Office</option>
                                    @foreach(\App\Models\Branch::orderBy('office_name')->get() as $office)
                                        <option value="{{$office->id}}">{{$office->office_name}}</option>
                                    @endforeach
                                </select>
                                <small class="form-text text-muted">
                                    <i class="fa-solid fa-building"></i> Optional - Leave blank to keep current office
                                </small>
                            </div>
                        </div>

                        <div class="col-12 col-md-12 col-lg-12">
                            <div class="form-group">
                                <label for="change_sel_matter_id">Law matter type</label>
                                <select class="form-select" name="sel_matter_id" id="change_sel_matter_id">
                                    <option value="">— Loading —</option>
                                </select>
                                <small class="form-text text-muted">Changing type may not match the matter reference prefix; update details if needed.</small>
                            </div>
                        </div>
                        <input type="hidden" id="change_matter_initial_sel_matter_id" value="">

                        @if(\Illuminate\Support\Facades\Schema::hasColumn('client_matters', 'our_party_role'))
                        <div class="col-12 col-md-12 col-lg-12">
                            <div class="form-group">
                                <label for="change_matter_our_party_role">Our client&rsquo;s role <span class="text-danger">*</span></label>
                                <select class="form-select" name="our_party_role" id="change_matter_our_party_role" data-valid="required">
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
                            <input type="hidden" name="opposing_parties_json" id="change_matter_opposing_parties_json" value="[]">
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

                        <div class="col-9 col-md-9 col-lg-9 text-right">
                            <button onclick="if(typeof window.prepareChangeMatterAssigneeSubmit === 'function') { window.prepareChangeMatterAssigneeSubmit(); } else { customValidate('change_matter_assignee'); }" type="button" class="btn btn-primary">Save</button>
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
</script>
