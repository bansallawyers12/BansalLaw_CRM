{{-- openfileuploadmodal removed with unused workflow checklist upload flow --}}

<!-- Add Personal Document Folder Modal -->
<div class="modal fade addpersonaldoccatmodel custom_modal" id="addpersonaldoccatmodel" tabindex="-1" role="dialog" aria-labelledby="addPersDocCatModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="addPersDocCatModalLabel">Add Personal Document Folder</h5>
				<button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body">
				<form method="post" action="{{URL::to('/documents/add-personal-category')}}" name="add_pers_doc_cat_form" id="add_pers_doc_cat_form" autocomplete="off"  enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="clientid" value="{{$fetchedData->id}}">

					<div class="row">
						<div class="col-6 col-md-6 col-lg-6">
							<div class="form-group">
								<label for="personal_doc_category">Folder name<span class="span_req">*</span></label>
								<input type="text" class="form-control" name="personal_doc_category" id="personal_doc_category" data-valid="required">

								<span class="custom-error personal_doc_category_error" role="alert">
									<strong></strong>
								</span>
							</div>
						</div>
					</div>

					<div class="row">
						<div class="col-12 col-md-12 col-lg-12">
							<button onclick="customValidate('add_pers_doc_cat_form')" type="button" class="btn btn-primary" style="margin: 0px !important;">Create</button>
							<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
						</div>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>

<!-- Add matter document folder modal (visa_document_types) -->
<div class="modal fade addvisadoccatmodel custom_modal" id="addvisadoccatmodel" tabindex="-1" role="dialog" aria-labelledby="addVisaDocCatModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="addVisaDocCatModalLabel">Add Matter Document Folder</h5>
				<button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body">
				<form method="post" action="{{URL::to('/documents/add-visa-category')}}" name="add_visa_doc_cat_form" id="add_visa_doc_cat_form" autocomplete="off"  enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="clientid" value="{{$fetchedData->id}}">
					<input type="hidden" name="clientmatterid" id="visaclientmatterid" value="">

					<div class="row">
						<div class="col-6 col-md-6 col-lg-6">
							<div class="form-group">
								<label for="visa_doc_category">Folder name<span class="span_req">*</span></label>
								<input type="text" class="form-control" name="visa_doc_category" id="visa_doc_category" data-valid="required">

								<span class="custom-error visa_doc_category_error" role="alert">
									<strong></strong>
								</span>
							</div>
						</div>
					</div>

					<div class="row">
						<div class="col-12 col-md-12 col-lg-12">
							<button onclick="customValidate('add_visa_doc_cat_form')" type="button" class="btn btn-primary" style="margin: 0px !important;">Create</button>
							<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
						</div>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>

<!-- Rename checklist modal (personal + matter documents) -->
<div class="modal fade custom_modal" id="renameChecklistModal" tabindex="-1" role="dialog" aria-labelledby="renameChecklistModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="renameChecklistModalLabel">Rename Checklist</h5>
				<button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body">
				<input type="hidden" id="renameChecklistDocId" value="">
				<div class="form-group mb-0">
					<label for="renameChecklistName">Checklist name<span class="span_req">*</span></label>
					<input type="text" class="form-control" id="renameChecklistName" autocomplete="off">
					<div class="invalid-feedback d-block" id="renameChecklistError" style="display: none;"></div>
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
				<button type="button" class="btn btn-primary" id="renameChecklistSaveBtn">Save</button>
			</div>
		</div>
	</div>
</div>

<!-- Rename file modal (personal + matter documents) -->
<div class="modal fade custom_modal" id="renameFileModal" tabindex="-1" role="dialog" aria-labelledby="renameFileModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="renameFileModalLabel">Rename File Name</h5>
				<button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body">
				<input type="hidden" id="renameFileDocId" value="">
				<div class="form-group mb-0">
					<label for="renameFileName">File name<span class="span_req">*</span></label>
					<input type="text" class="form-control" id="renameFileName" autocomplete="off">
					<small class="form-text text-muted">Enter the name without the file extension.</small>
					<div class="invalid-feedback d-block" id="renameFileError" style="display: none;"></div>
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
				<button type="button" class="btn btn-primary" id="renameFileSaveBtn">Save</button>
			</div>
		</div>
	</div>
</div>

