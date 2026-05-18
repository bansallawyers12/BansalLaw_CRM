{{-- ========================================
    ALL NOTE-RELATED MODALS
    This file contains all note modals for the client detail page
    ======================================== --}}

{{-- 1. Create Note Modal (Simple) --}}
<!-- Update note Modal -->
<div class="modal fade custom_modal" id="create_note" tabindex="-1" role="dialog" aria-labelledby="create_noteModalLabel" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="appliationModalLabel">Create Note</h5>
				<button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body">
			<form method="post" action="{{URL::to('/create-note')}}" name="notetermform" autocomplete="off" id="notetermform" enctype="multipart/form-data">
			@csrf
			<input type="hidden" name="client_id" id="note_simple_client_id" value="{{$fetchedData->id}}">
				<input type="hidden" name="noteid" value="">
				<input type="hidden" name="mailid" value="0">
				<input type="hidden" name="vtype" value="client">
					<div class="row">
						<div class="col-12 col-md-12 col-lg-12">
							<div class="form-group">
								<label for="task_group">Type <span class="span_req">*</span></label>
								<select name="task_group" class="form-control" data-valid="required" id="noteTypeSimple">
								    <option value="">Please Select Note</option>
								    <option value="Call">Call</option>
								    <option value="Email">Email</option>
								    <option value="In-Person">In-Person</option>
								    <option value="Others">Others</option>
								    <option value="Attention">Attention</option>
								</select>
								<!-- Container for additional inputs -->
						        <div id="additionalFieldsSimple" class="additional-fields-container"></div>
								<span class="custom-error task_group_error" role="alert">
									<strong></strong>
								</span>
							</div>
						</div>
						<div class="col-12 col-md-12 col-lg-12">
							<div class="form-group">
								<label for="description">Description <span class="span_req">*</span></label>
								<textarea  class="tinymce-editor" name="description" data-valid="required"></textarea>
								<span class="custom-error title_error" role="alert">
									<strong></strong>
								</span>
							</div>
						</div>
						<!--<div class="col-12 col-md-12 col-lg-12 is_not_note" style="display:none;">
							
							<div class="form-group">
								<label for="followup_date">Follow-up Date <span class="span_req">*</span></label>
								<input type="date" name="followup_date" class="form-control" data-valid="required">
								<span class="custom-error followup_date_error" role="alert">
									<strong></strong>
								</span>
							</div>
						</div>-->

						<div class="col-12 col-md-12 col-lg-12">
							<button onclick="customValidate('notetermform')" type="button" class="btn btn-primary">Submit</button>
							<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
						</div>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>

{{-- 2. Create Note with Matter Selection --}}
<!-- Enhanced Create note Modal -->
<div class="modal fade custom_modal" id="create_note_d" tabindex="-1" role="dialog" aria-labelledby="create_noteModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-lg">
		<div class="modal-content create-note-modal">
			<div class="modal-header create-note-header">
				<div class="modal-title-section">
					<i class="fas fa-sticky-note create-note-header__icon mr-2" aria-hidden="true"></i>
					<h5 class="modal-title mb-0" id="appliationModalLabel">Create Note</h5>
				</div>
				<div class="modal-actions">
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			</div>
			<div class="modal-body create-note-body">
				<form method="post" action="{{URL::to('/create-note')}}" name="notetermform_n" autocomplete="off" id="notetermform_n" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="client_id" id="client_id" value="{{$fetchedData->id}}">
                    <input type="hidden" name="noteid" value="">
                    <input type="hidden" name="mailid" value="0">
                    <input type="hidden" name="vtype" value="client">
					<div class="row">
                        <div class="col-12 col-md-6">
							<div class="form-group enhanced-form-group">
								<label for="matter_id" class="form-label">
									<i class="fas fa-folder-open text-muted mr-1"></i>
									Select Matter
								</label>
								<div class="input-group">
									<div class="input-group-prepend">
										<span class="input-group-text"><i class="fas fa-list-ul"></i></span>
                                        </div>
									<select name="matter_id" id="matter_id" class="form-control enhanced-select">
								    <option value="">Select Client Matters</option>
                                    <?php
	                                    // Get all active matters for the client (including sel_matter_id=1 as General Matter)
                                    $matter_list_arr = DB::table('client_matters')
                                    ->leftJoin('matters', 'client_matters.sel_matter_id', '=', 'matters.id')
	                                    ->select('client_matters.id','client_matters.client_unique_matter_no','matters.title','client_matters.sel_matter_id')
                                    ->where('client_matters.matter_status',1)
                                    ->where('client_matters.client_id',@$fetchedData->id)
	                                    ->orderBy('client_matters.updated_at', 'desc')
                                    ->get();
                                    ?>
								    @foreach($matter_list_arr as $matterlist)
	                                        @php
	                                            $matterName = \App\Models\Matter::displayTitleFromJoinedRow($matterlist->title ?? null);
	                                            
	                                            // Concatenate matter name with client_unique_matter_no if it exists
	                                            if (!empty($matterlist->client_unique_matter_no)) {
	                                                $matterName .= ' (' . $matterlist->client_unique_matter_no . ')';
	                                            }
	                                        @endphp
	                                        <option value="{{$matterlist->id}}">{{$matterName}}</option>
                                    @endforeach
								</select>
								</div>
								<span class="custom-error matter_id_error" role="alert">
									<strong></strong>
								</span>
							</div>
						</div>

                        <input type="hidden" name="title" value="Matter Discussion">

                        <div class="col-12 col-md-6">
							<div class="form-group enhanced-form-group">
								<label for="task_group" class="form-label">
									<i class="fas fa-tag text-muted mr-1"></i>
									Type <span class="text-danger">*</span>
								</label>
								<div class="input-group">
									<div class="input-group-prepend">
										<span class="input-group-text"><i class="fas fa-list"></i></span>
									</div>
									<select name="task_group" class="form-control enhanced-select" data-valid="required" id="noteTypeEnhanced">
                                    <option value="">Please Select</option>
	                                    <option value="Call">📞 Call</option>
	                                    <option value="Email">📧 Email</option>
	                                    <option value="In-Person">👤 In-Person</option>
	                                    <option value="Others">📝 Others</option>
	                                    <option value="Attention">⚠️ Attention</option>
                                </select>
								</div>
                                <!-- Container for additional inputs -->
						        <div id="additionalFieldsEnhanced" class="additional-fields-container"></div>

								<span class="custom-error title_error" role="alert">
									<strong></strong>
								</span>
							</div>
						</div>

						<div class="col-12">
							<div class="form-group enhanced-form-group">
								<label for="description" class="form-label">
									<i class="fas fa-align-left text-muted mr-1"></i>
									Description <span class="text-danger">*</span>
								</label>
								<div class="rich-text-container">
									<textarea class="tinymce-editor enhanced-textarea" id="note_description" name="description" data-valid="required"></textarea>
								</div>
								<span class="custom-error title_error" role="alert">
									<strong></strong>
								</span>
							</div>
						</div>

                        <div class="col-12">
							<div class="modal-footer-buttons">
								<button type="button" class="btn btn-primary btn-lg btn-create-action" data-container="body" data-role="popover" data-placement="bottom" data-html="true">
									<i class="fas fa-cog mr-2"></i>Create Task
								</button>
								<button onclick="customValidate('notetermform_n')" type="button" class="btn btn-primary btn-lg btn-create-note">
									<i class="fas fa-save mr-2"></i>Create Note
								</button>
								<button type="button" class="btn btn-outline-secondary btn-lg" data-bs-dismiss="modal">
									<i class="fas fa-times mr-2"></i>Cancel
								</button>
							</div>
                        </div>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>

{{-- 3. View Note Modal --}}
<!-- Note & Terms Modal -->
<div class="modal fade custom_modal" id="view_note" tabindex="-1" role="dialog" aria-labelledby="view_noteModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body">
				<div id="note_detail_view"></div>
			</div>
		</div>
	</div>
</div>

{{-- 4. View Matter Note Modal --}}
<div class="modal fade custom_modal" id="view_matter_note" tabindex="-1" role="dialog" aria-labelledby="view_matter_noteModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body">
				<div id="matter_note_detail_view" class="note_content">
					<h5></h5>
					<p></p>
				</div>
			</div>
		</div>
	</div>
</div>

{{-- 5. Create Matter Note Modal --}}
<div class="modal fade custom_modal" id="create_matternote" tabindex="-1" role="dialog" aria-labelledby="create_noteModalLabel" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="appliationModalLabel">Create Matter Note</h5>
				<button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body">
				<form method="post" action="{{URL::to('/create-app-note')}}" name="appnotetermform" autocomplete="off" id="appnotetermform" enctype="multipart/form-data">
				@csrf
				<input type="hidden" name="client_id" value="{{$fetchedData->id}}">
				<input type="hidden" name="noteid" id="noteid" value="">
				<input type="hidden" name="type" id="type" value="">
					<div class="row">
						<div class="col-12 col-md-12 col-lg-12">
							<div class="form-group">
								<label for="title">Title <span class="span_req">*</span></label>
								<input type="text" name="title" class="form-control" data-valid="required" autocomplete="off" placeholder="Enter Title">
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

						<div class="col-12 col-md-12 col-lg-12">
							<button onclick="customValidate('appnotetermform')" type="button" class="btn btn-primary">Submit</button>
							<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
						</div>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>

{{-- Enhanced CSS Styles for Create Note Modal --}}
<style>
/* Enhanced Create Note Modal Styles */
.create-note-modal {
    border-radius: 12px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
    border: none;
    overflow: hidden;
}

.create-note-header {
    background: linear-gradient(135deg, var(--navy) 0%, var(--sidebar-active) 100%);
    color: white;
    border-bottom: none;
    padding: 20px 25px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-title-section {
    display: flex;
    align-items: center;
}

.modal-title-section .modal-title {
    font-weight: 600;
    font-size: 1.4rem;
    /* Theme: header uses navy gradient; title must stay light (overridden by body.sidebar-mini h5 in crm-theme) */
    color: #fff !important;
}

.create-note-header .create-note-header__icon {
    color: rgba(255, 255, 255, 0.95) !important;
    font-size: 1.15rem;
}

.modal-actions {
    display: flex;
    align-items: center;
    gap: 10px;
}

.modal-actions .btn {
    border-radius: 8px;
    font-weight: 500;
    padding: 8px 16px;
}

.modal-actions .close {
    color: white;
    opacity: 0.8;
    font-size: 1.5rem;
    padding: 0;
    margin: 0;
}

.modal-actions .close:hover {
    opacity: 1;
}

.create-note-body {
    padding: 30px 25px;
    background: #fafbfc;
}

.enhanced-form-group {
    margin-bottom: 25px;
}

.form-label {
    font-weight: 600;
    color: #2d3748;
    margin-bottom: 8px;
    font-size: 0.95rem;
}

.form-label i {
    font-size: 0.9rem;
}

.input-group-text {
    background: #f7fafc;
    border-color: #e2e8f0;
    color: #718096;
    border-radius: 8px 0 0 8px;
    padding: 12px 15px;
}

.enhanced-select {
    border-radius: 0 8px 8px 0;
    border-color: #e2e8f0;
    padding: 12px 15px;
    font-size: 0.95rem;
    transition: all 0.3s ease;
    background: white;
}

.enhanced-select:focus {
    border-color: var(--navy);
    box-shadow: 0 0 0 3px rgba(30, 61, 96, 0.1);
    outline: none;
}

.rich-text-container {
    border-radius: 8px;
    overflow: hidden;
    border: 1px solid #e2e8f0;
    background: white;
}

.enhanced-textarea {
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    min-height: 120px;
}

.modal-footer-buttons {
    display: flex;
    justify-content: flex-end;
    gap: 15px;
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid #e2e8f0;
}

.btn-create-note,
.btn-create-action {
    background: linear-gradient(135deg, var(--navy) 0%, var(--sidebar-active) 100%);
    border: none;
    border-radius: 8px;
    padding: 12px 30px;
    font-weight: 600;
    font-size: 1rem;
    transition: all 0.3s ease;
    box-shadow: 0 4px 12px rgba(30, 61, 96, 0.3);
}

.btn-create-note:hover,
.btn-create-action:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(30, 61, 96, 0.4);
}

.btn-outline-secondary {
    border-radius: 8px;
    padding: 12px 30px;
    font-weight: 600;
    font-size: 1rem;
    border-width: 2px;
    transition: all 0.3s ease;
}

.btn-outline-secondary:hover {
    background-color: #6c757d;
    border-color: #6c757d;
    transform: translateY(-1px);
}

/* Custom Error Styling */
.custom-error {
    color: #e53e3e;
    font-size: 0.85rem;
    margin-top: 5px;
    font-weight: 500;
}

/* Responsive Design */
@media (max-width: 768px) {
    .modal-dialog.modal-lg {
        margin: 10px;
        max-width: calc(100% - 20px);
    }
    
    .create-note-header {
        padding: 15px 20px;
    }
    
    .create-note-body {
        padding: 20px 15px;
    }
    
    .modal-footer-buttons {
        flex-direction: column;
        gap: 10px;
    }
    
    .modal-footer-buttons .btn {
        width: 100%;
    }
}

/* Animation for modal appearance */
.modal.fade .modal-dialog {
    transform: scale(0.8) translateY(-50px);
    transition: all 0.3s ease;
}

.modal.show .modal-dialog {
    transform: scale(1) translateY(0);
}

/* Enhanced focus states */
.enhanced-select:focus,
.enhanced-textarea:focus {
    border-color: var(--navy);
    box-shadow: 0 0 0 3px rgba(30, 61, 96, 0.1);
    outline: none;
}

/* Loading state for buttons */
.btn-create-note:disabled,
.btn-create-action:disabled {
    opacity: 0.7;
    cursor: not-allowed;
    transform: none;
}

</style>
