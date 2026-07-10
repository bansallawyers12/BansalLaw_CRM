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
<div class="modal fade" id="create_note_d" tabindex="-1" role="dialog" aria-labelledby="create_noteModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-lg">
		<div class="modal-content create-note-modal">
			<div class="modal-header create-note-header">
				<div class="modal-title-section">
					<div class="icon-wrapper">
						<i class="fa-solid fa-note-sticky create-note-header__icon" aria-hidden="true"></i>
					</div>
					<h5 class="modal-title mb-0" id="appliationModalLabel">Create Note</h5>
				</div>
				<div class="modal-actions">
					<button type="button" class="btn-close-modern" data-bs-dismiss="modal" aria-label="Close">
						<i class="fa-solid fa-xmark"></i>
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
									Select Matter
								</label>
								<div class="input-wrapper">
									<i class="fa-solid fa-folder-open text-muted input-icon"></i>
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
									Type <span class="text-danger">*</span>
								</label>
								<div class="input-wrapper">
									<i class="fa-solid fa-tag text-muted input-icon"></i>
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
									<i class="fa-solid fa-gear me-2"></i>Create Task
								</button>
								<button onclick="customValidate('notetermform_n')" type="button" class="btn btn-primary btn-lg btn-create-note">
									<i class="fa-solid fa-floppy-disk me-2"></i>Create Note
								</button>
								<button type="button" class="btn btn-outline-secondary btn-lg" data-bs-dismiss="modal">
									<i class="fa-solid fa-xmark me-2"></i>Cancel
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
/* Premium Create Note Modal Styles */
.create-note-modal {
    border-radius: 24px;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25), 0 0 0 1px rgba(0,0,0,0.05);
    border: none;
    overflow: hidden;
    background: #ffffff;
}

.create-note-header {
    background: #ffffff !important;
    border: none !important;
    border-bottom: none !important;
    padding: 32px 32px 16px 32px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-title-section {
    display: flex;
    align-items: center;
    gap: 16px;
}

.icon-wrapper {
    width: 48px;
    height: 48px;
    background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #2563eb;
    box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.1);
}

.icon-wrapper i {
    font-size: 1.25rem;
}

.modal-title-section .modal-title {
    font-weight: 700;
    font-size: 1.5rem;
    color: #0f172a !important;
    letter-spacing: -0.025em;
}

.modal-actions {
    display: flex;
    align-items: center;
}

.btn-close-modern {
    background: transparent;
    border: none;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #94a3b8;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    font-size: 1.2rem;
}

.btn-close-modern:hover {
    background: #f1f5f9;
    color: #ef4444;
    transform: rotate(90deg);
}

.create-note-body {
    padding: 16px 32px 32px 32px;
    background: #ffffff;
}

.enhanced-form-group {
    margin-bottom: 28px;
}

.form-label {
    font-weight: 600;
    color: #334155;
    margin-bottom: 10px;
    font-size: 0.95rem;
    letter-spacing: 0.01em;
}

.form-label i {
    font-size: 0.9rem;
}

.input-wrapper {
    position: relative;
    display: flex;
    align-items: center;
}

.input-icon {
    position: absolute;
    left: 18px;
    color: #94a3b8;
    z-index: 10;
    font-size: 1.1rem;
    transition: color 0.3s ease;
}

.enhanced-select {
    padding: 12px 16px 12px 48px !important;
    height: auto !important;
    min-height: 48px;
    border-radius: 14px;
    border: 2px solid transparent;
    background: #f8fafc;
    font-size: 1rem;
    font-weight: 500;
    color: #1e293b;
    transition: all 0.3s ease;
    width: 100%;
    appearance: none;
}

.enhanced-select:hover {
    background: #f1f5f9;
}

.input-wrapper:focus-within .input-icon {
    color: #2563eb;
}

.enhanced-select:focus {
    background: #ffffff;
    border-color: #3b82f6;
    box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.15);
    outline: none;
}

.rich-text-container {
    border-radius: 14px;
    overflow: hidden;
    border: 2px solid #e2e8f0;
    background: #f8fafc;
    transition: all 0.3s ease;
}

.rich-text-container:focus-within {
    background: #ffffff;
    border-color: #3b82f6;
    box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.15);
}

.rich-text-container .tox-tinymce {
    border: none !important;
    border-radius: 12px !important;
}

.enhanced-textarea {
    border: none;
    min-height: 120px;
    width: 100%;
}

.modal-footer-buttons {
    display: flex;
    justify-content: flex-end;
    gap: 16px;
    margin-top: 36px;
    padding-top: 24px;
    border-top: 1px solid #f1f5f9;
}

.btn-create-note,
.btn-create-action {
    background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%) !important;
    color: white !important;
    border: none !important;
    border-radius: 999px !important;
    padding: 12px 28px !important;
    font-weight: 600;
    font-size: 0.95rem;
    letter-spacing: 0.02em;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 10px 15px -3px rgba(37, 99, 235, 0.3);
}

.btn-create-note:hover,
.btn-create-action:hover {
    transform: translateY(-2px);
    box-shadow: 0 20px 25px -5px rgba(37, 99, 235, 0.4);
    color: white;
}

.btn-outline-secondary {
    border-radius: 999px !important;
    padding: 12px 28px !important;
    font-weight: 600;
    font-size: 0.95rem;
    border: 2px solid #e2e8f0 !important;
    color: #475569 !important;
    background: transparent !important;
    transition: all 0.3s ease;
}

.btn-outline-secondary:hover {
    background-color: #f8fafc;
    border-color: #cbd5e1;
    color: #0f172a;
}

/* Custom Error Styling */
.custom-error {
    color: #ef4444;
    font-size: 0.85rem;
    margin-top: 6px;
    font-weight: 500;
    display: block;
}

/* Responsive Design */
@media (max-width: 768px) {
    .modal-dialog.modal-lg {
        margin: 16px;
        max-width: calc(100% - 32px);
    }
    
    .create-note-header {
        padding: 24px 24px 12px 24px;
    }
    
    .create-note-body {
        padding: 12px 24px 24px 24px;
    }
    
    .modal-footer-buttons {
        flex-direction: column;
        gap: 12px;
    }
    
    .modal-footer-buttons .btn {
        width: 100%;
    }
}

/* Animation for modal appearance */
.modal.fade .modal-dialog {
    transform: scale(0.95) translateY(-20px);
    opacity: 0;
    transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
}

.modal.show .modal-dialog {
    transform: scale(1) translateY(0);
    opacity: 1;
}

/* Loading state for buttons */
.btn-create-note:disabled,
.btn-create-action:disabled {
    opacity: 0.7;
    cursor: not-allowed;
    transform: none;
}
</style>
