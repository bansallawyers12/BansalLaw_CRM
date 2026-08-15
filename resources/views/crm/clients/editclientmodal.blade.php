<!-- REMOVED: Old appointment edit modal - no longer supported -->
<!-- The current appointment system uses BookingAppointment model (synced from public booking website) -->
<!-- and does not have edit functionality through the CRM interface -->

<!-- Note & Terms Modal -->
<div class="modal fade custom_modal" id="edit_note" tabindex="-1" role="dialog" aria-labelledby="create_noteModalLabel" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="appliationModalLabel">Create Note</h5>
				<button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body">
				<form method="post" action="{{URL::to('/create-note')}}" name="editnotetermform" autocomplete="off" id="editnotetermform" enctype="multipart/form-data">
				@csrf 
				<input type="hidden" name="client_id" value="{{$fetchedData->id}}">
				<input type="hidden" name="noteid" value="">
					<div class="row">
						<div class="col-12 col-md-12 col-lg-12">
							<div class="form-group">
								<label for="title">Title <span class="span_req">*</span></label>
								<input type="text" name="title" value="" class="form-control" data-valid="required" autocomplete="off" placeholder="Enter Title">
								<select name="title" class="form-control" data-valid="required">
								    <option value="">Please Select Note</option>
								    <option value="Call" <?php if($fetchedData->title = 'Call') { echo 'selected'; } ?>>Call</option>
								    <option value="Email" <?php if($fetchedData->title = 'Email') { echo 'selected'; } ?>>Email</option>
								    <option value="In-Person" <?php if($fetchedData->title = 'In-Person') { echo 'selected'; } ?>>In-Person</option>
								    <option value="Others" <?php if($fetchedData->title = 'Others') { echo 'selected'; } ?>>Others</option>
								    <option value="Attention" <?php if($fetchedData->title = 'Attention') { echo 'selected'; } ?>>Attention</option>
								</select>
								
								<span class="custom-error title_error" role="alert">
									<strong></strong>
								</span> 
							</div>
						</div>
						<div class="col-12 col-md-12 col-lg-12">
							<div class="form-group">
								<label for="description">Description <span class="span_req">*</span></label>
								<textarea class="tinymce-editor" name="description" data-valid="required"></textarea>
								<span class="custom-error title_error" role="alert">
									<strong></strong>
								</span> 
							</div>
						</div>
						<!--<div class="col-12 col-md-12 col-lg-12 is_not_note" style="display:none;">
							<div class="form-group"> 
								<label class="d-block" for="related_to">Related To</label> 
								<div class="form-check form-check-inline">
									<input class="form-check-input" type="radio" id="contact" value="Contact" name="related_to" checked>
									<label class="form-check-label" for="contact">Contact</label>
								</div>
								<div class="form-check form-check-inline">
									<input class="form-check-input" type="radio" id="partner" value="Partner" name="related_to">
									<label class="form-check-label" for="partner">Partner</label>
								</div>
								<div class="form-check form-check-inline">
									<input class="form-check-input" type="radio" id="application" value="Application" name="related_to">
									<label class="form-check-label" for="application">Application</label>
								</div>
							
							</div>
						</div>
						<div class="col-12 col-md-12 col-lg-12 is_not_note" style="display:none;">
							<div class="form-group">
								<label for="contact_name">Contact Name <span class="span_req">*</span></label> 	
								<select data-valid="" class="form-control contact_name crm-ts-plain" name="contact_name">
									<option value="">Choose Contact</option>
									<option value="Amit">Amit</option>
								</select>
								<span class="custom-error contact_name_error" role="alert">
									<strong></strong>
								</span> 
							</div>
						</div>-->
						<div class="col-12 col-md-12 col-lg-12">
							<button onclick="customValidate('editnotetermform')" type="button" class="btn btn-primary">Submit</button>
							<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
						</div>
					</div>
				</form> 
			</div>
		</div> 
	</div>
</div>

<!-- Education Modal removed - education system deprecated -->

{{-- Interested Service Edit Modal REMOVED - feature deprecated (no UI triggers) --}}