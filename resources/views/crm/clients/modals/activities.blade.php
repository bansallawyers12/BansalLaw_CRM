<!-- Basic Appointment Modal removed - now using updated booking appointment modal (create_appoint) -->

<!-- Edit Date & Time Modal -->
<div class="modal fade custom_modal" id="edit_datetime_modal" tabindex="-1" role="dialog" aria-labelledby="editDatetimeModalLabel" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="editDatetimeModalLabel">Edit Date & Time</h5>
				<button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body">
				<form method="post" action="{{URL::to('/update-note-datetime')}}" name="edit_datetime_form" id="edit_datetime_form" autocomplete="off" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="note_id" id="edit_note_id" value="">
					<div class="row">
						<div class="col-12 col-md-12 col-lg-12">
							<div class="form-group">
								<label for="edit_datetime">Date & Time <span class="span_req">*</span></label>
								<input type="text" class="form-control" id="edit_datetime" name="datetime" data-valid="required" readonly>
								<span class="custom-error datetime_error" role="alert">
									<strong></strong>
								</span>
							</div>
						</div>
						<div class="col-12 col-md-12 col-lg-12">
							<button type="button" id="save_datetime_btn" class="btn btn-primary">Save</button>
							<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>

<!-- Send Text Message Confirmation Modal -->
<div class="modal fade" id="notPickedCallModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Are you sure want to send text message to this user?</h5>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
            </div>
            <div class="modal-body">
                <textarea class="form-control" id="messageText" rows="10" style="height: 130px !important;"></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary sendMessage">Send</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Convert Activity to Note Modal -->
<div id="convertActivityToNoteModal" class="modal fade custom_modal" tabindex="-1" role="dialog" aria-labelledby="convertActivityToNoteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="convertActivityToNoteModalLabel">Convert Activity Into Note</h5>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form method="post" name="convertActivityToNoteForm" id="convertActivityToNoteForm" action="{{URL::to('/convert-activity-to-note')}}" autocomplete="off" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="activity_id" id="convert_activity_id" value="">
                    <input type="hidden" name="client_id" id="convert_client_id" value="">
                    
                    <div class="row">
                        <div class="col-12 col-md-12 col-lg-12">
                            <div class="form-group">
                                <label for="client_matter_id">Select Client Matter <span class="span_req">*</span></label>
                                <select class="form-control" name="client_matter_id" id="convert_client_matter_id" data-valid="required">
                                    <option value="">Select Client Matter</option>
                                    <!-- Client matters will be populated dynamically via JavaScript -->
                                </select>
                                @if ($errors->has('client_matter_id'))
                                    <span class="custom-error" role="alert">
                                        <strong>{{ @$errors->first('client_matter_id') }}</strong>
                                    </span>
                                @endif
                            </div>
                        </div>
                        
                        <div class="col-12 col-md-12 col-lg-12">
                            <div class="form-group">
                                <label for="note_type">Type <span class="span_req">*</span></label>
                                <select class="form-control" name="note_type" id="convert_note_type" data-valid="required">
                                    <option value="">Please Select</option>
                                    <option value="Call">Call</option>
                                    <option value="Email">Email</option>
                                    <option value="In-Person">In-Person</option>
                                    <option value="Others">Others</option>
                                    <option value="Attention">Attention</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-12 col-md-12 col-lg-12">
                            <div class="form-group">
                                <label for="note_description">Note Description</label>
                                <textarea class="form-control summernote-simple" name="note_description" id="convert_note_description" rows="4" readonly></textarea>
                            </div>
                        </div>
                        
                        <div class="col-12 col-md-12 col-lg-12">
                            <button type="submit" class="btn btn-primary">Convert to Note</button>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

