{{-- ========================================
    ALL NOTE-RELATED MODALS
    This file contains all note modals for the client detail page
    ======================================== --}}

{{-- 1. Create Note Modal (Simple) --}}
<!-- Update note Modal -->
<div class="modal fade custom_modal" id="create_note" tabindex="-1" role="dialog" aria-labelledby="create_noteModalLabel" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content note-modal-with-drop">
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
								<label for="spend_mins_simple">Spend Mins</label>
								<input type="number"
								       name="spend_mins"
								       id="spend_mins_simple"
								       class="form-control"
								       min="0"
								       max="99999"
								       step="1"
								       placeholder="0">
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
						<div class="col-12 col-md-12 col-lg-12">
							@include('crm.clients.modals.partials.note-attachments')
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
			<div class="note-page-drop-overlay" aria-hidden="true">
				<div class="note-page-drop-overlay__inner">
					<i class="fa-solid fa-cloud-arrow-up" aria-hidden="true"></i>
					<p>Drop files to attach</p>
				</div>
			</div>
		</div>
	</div>
</div>

{{-- 2. Create Note with Matter Selection --}}
<div class="modal fade custom_modal sa-note-modal" id="create_note_d" tabindex="-1" role="dialog" aria-labelledby="create_noteModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-xl modal-dialog-centered">
		<div class="modal-content note-modal-with-drop">
			<div class="modal-header sa-note-modal__header">
				<h5 class="modal-title mb-0" id="appliationModalLabel">Create Note</h5>
				<x-crm.modal-close />
			</div>
			<form method="post" action="{{URL::to('/create-note')}}" name="notetermform_n" autocomplete="off" id="notetermform_n" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="client_id" id="client_id" value="{{$fetchedData->id}}">
                    <input type="hidden" name="noteid" value="">
                    <input type="hidden" name="mailid" value="0">
                    <input type="hidden" name="vtype" value="client">
                    <input type="hidden" name="title" value="Matter Discussion">
				<div class="modal-body sa-note-modal__body">
					<div class="row g-3">
                        <div class="col-12 col-lg-5">
							<div class="form-group mb-0">
								<label for="matter_id" class="sa-label">Select Matter</label>
								<select name="matter_id" id="matter_id" class="form-control">
								    <option value="">Select Client Matters</option>
                                    <?php
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
	                                            if (!empty($matterlist->client_unique_matter_no)) {
	                                                $matterName .= ' (' . $matterlist->client_unique_matter_no . ')';
	                                            }
	                                        @endphp
	                                        <option value="{{$matterlist->id}}">{{$matterName}}</option>
                                    @endforeach
								</select>
								<span class="custom-error matter_id_error" role="alert"><strong></strong></span>
							</div>
						</div>

                        <div class="col-12 col-lg-4">
							<div class="form-group mb-0">
								<label for="noteTypeEnhanced" class="sa-label">Type <span class="span_req">*</span></label>
								<select name="task_group" class="form-control" data-valid="required" id="noteTypeEnhanced">
                                    <option value="">Please Select</option>
	                                    <option value="Call">Call</option>
	                                    <option value="Email">Email</option>
	                                    <option value="In-Person">In-Person</option>
	                                    <option value="Others">Others</option>
	                                    <option value="Attention">Attention</option>
                                </select>
						        <div id="additionalFieldsEnhanced" class="additional-fields-container"></div>
								<span class="custom-error title_error" role="alert"><strong></strong></span>
							</div>
						</div>

						<div class="col-12 col-lg-3">
							<div class="form-group mb-0">
								<label for="spend_mins" class="sa-label">Spend Mins</label>
								<input type="number"
								       name="spend_mins"
								       id="spend_mins"
								       class="form-control note-spend-mins-input"
								       min="0"
								       max="99999"
								       step="1"
								       placeholder="0">
							</div>
						</div>

						<div class="col-12">
							<div class="form-group mb-0">
								<label for="note_description" class="sa-label">Description <span class="span_req">*</span></label>
								<div class="sa-note-modal__editor">
									<textarea class="tinymce-editor tinymce-editor-note-lg" id="note_description" name="description" data-valid="required"></textarea>
								</div>
								<span class="custom-error title_error" role="alert"><strong></strong></span>
							</div>
						</div>

						<div class="col-12">
							@include('crm.clients.modals.partials.note-attachments')
						</div>
					</div>
				</div>
				<div class="modal-footer sa-note-modal__footer">
					<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
					<button type="button" class="btn btn-primary btn-create-action" data-container="body" data-role="popover" data-placement="bottom" data-html="true">
						<i class="fa-solid fa-gear me-1"></i>Create Task
					</button>
					<button onclick="customValidate('notetermform_n')" type="button" class="btn btn-primary btn-create-note">
						<i class="fa-solid fa-floppy-disk me-1"></i>Create Note
					</button>
				</div>
			</form>
			<div class="note-page-drop-overlay" aria-hidden="true">
				<div class="note-page-drop-overlay__inner">
					<i class="fa-solid fa-cloud-arrow-up" aria-hidden="true"></i>
					<p>Drop files to attach</p>
				</div>
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
/* Create Note modal — aligned with Schedule Appointment (sa-appoint-modal) */
#create_note_d.sa-note-modal .modal-dialog {
    max-width: min(960px, 96vw);
    margin: 1rem auto;
}

#create_note_d.sa-note-modal .modal-content {
    font-family: 'Segoe UI', sans-serif;
    border: 1px solid var(--border, #c8dcef);
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 12px 40px rgba(30, 61, 96, 0.16);
}

#create_note_d.sa-note-modal .sa-note-modal__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    background: linear-gradient(135deg, var(--navy, #1e3d60) 0%, var(--sidebar-active, #3a6fa8) 100%) !important;
    background-image: linear-gradient(135deg, var(--navy, #1e3d60) 0%, var(--sidebar-active, #3a6fa8) 100%) !important;
    border-bottom: 3px solid var(--accent-gold, #c8992a) !important;
    padding: 16px 20px !important;
    color: #fff !important;
}

#create_note_d.sa-note-modal .modal-title,
#create_note_d.sa-note-modal .modal-header h5 {
    color: #fff !important;
    -webkit-text-fill-color: #fff !important;
    font-size: 1.125rem !important;
    font-weight: 700 !important;
    margin: 0 !important;
    letter-spacing: -0.01em !important;
    flex: 1;
    min-width: 0;
}

#create_note_d.sa-note-modal .sa-note-modal__header .crm-modal-close {
    border: 1px solid rgba(255, 255, 255, 0.35) !important;
    background: rgba(255, 255, 255, 0.12) !important;
    color: #fff !important;
    opacity: 1 !important;
}

#create_note_d.sa-note-modal .sa-note-modal__header .crm-modal-close:hover,
#create_note_d.sa-note-modal .sa-note-modal__header .crm-modal-close:focus {
    background: rgba(255, 255, 255, 0.22) !important;
    border-color: rgba(255, 255, 255, 0.55) !important;
    color: #fff !important;
    opacity: 1 !important;
}

#create_note_d.sa-note-modal .sa-note-modal__body {
    padding: 16px 18px !important;
    background: var(--card-bg, #fff) !important;
    overflow: visible !important;
    max-height: none !important;
}

#create_note_d.sa-note-modal .sa-label,
#create_note_d.sa-note-modal .note-attachments-field > .form-label {
    display: block;
    font-size: 12px;
    font-weight: 600;
    color: var(--text-muted, #5e7a90);
    margin-bottom: 4px;
}

#create_note_d.sa-note-modal .span_req {
    color: var(--danger, #a83020);
    font-weight: 700;
}

#create_note_d.sa-note-modal .form-control,
#create_note_d.sa-note-modal .form-select {
    border: 1px solid var(--border, #c8dcef);
    border-radius: 8px;
    min-height: 38px;
    font-size: 0.9375rem;
    color: var(--text-dark, #1e293b);
    background: #fff;
}

#create_note_d.sa-note-modal .form-control:focus,
#create_note_d.sa-note-modal .form-select:focus {
    border-color: var(--sidebar-active, #3a6fa8);
    box-shadow: 0 0 0 3px rgba(58, 111, 168, 0.15);
}

#create_note_d.sa-note-modal .note-spend-mins-input {
    text-align: right;
    font-variant-numeric: tabular-nums;
}

#create_note_d.sa-note-modal .sa-note-modal__editor {
    border: 1px solid var(--border, #c8dcef);
    border-radius: 8px;
    overflow: hidden;
    background: #fff;
}

#create_note_d.sa-note-modal .sa-note-modal__editor:focus-within {
    border-color: var(--sidebar-active, #3a6fa8);
    box-shadow: 0 0 0 3px rgba(58, 111, 168, 0.15);
}

#create_note_d.sa-note-modal .sa-note-modal__editor .tox-tinymce {
    border: none !important;
    border-radius: 0 !important;
}

#create_note_d.sa-note-modal .sa-note-modal__footer {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    align-items: center;
    gap: 10px;
    padding: 12px 18px !important;
    background: var(--sidebar-bg, #f4f8fc) !important;
    border-top: 1px solid var(--border, #c8dcef) !important;
}

#create_note_d.sa-note-modal .sa-note-modal__footer .btn {
    min-height: 38px;
    padding: 8px 18px;
    font-weight: 600;
    font-size: 0.875rem;
    border-radius: 8px;
}

#create_note_d.sa-note-modal .btn-create-note,
#create_note_d.sa-note-modal .btn-create-action {
    background: var(--navy, #1e3d60) !important;
    border-color: var(--navy, #1e3d60) !important;
    color: #fff !important;
}

#create_note_d.sa-note-modal .btn-create-note:hover,
#create_note_d.sa-note-modal .btn-create-action:hover {
    filter: brightness(1.08);
    color: #fff !important;
}

#create_note_d.sa-note-modal .custom-error {
    color: #ef4444;
    font-size: 0.85rem;
    margin-top: 6px;
    font-weight: 500;
    display: block;
}

@media (max-width: 768px) {
    #create_note_d.sa-note-modal .modal-dialog {
        margin: 12px auto;
        max-width: calc(100% - 24px);
    }

    #create_note_d.sa-note-modal .sa-note-modal__footer {
        flex-direction: column-reverse;
        align-items: stretch;
    }

    #create_note_d.sa-note-modal .sa-note-modal__footer .btn {
        width: 100%;
    }
}

.note-modal-with-drop {
    position: relative;
}

.note-dropzone {
    position: relative;
    display: block;
    margin: 0;
    border: 2px dashed #cbd5e1;
    border-radius: 12px;
    background: #f8fafc;
    padding: 10px 14px;
    transition: border-color 0.2s ease, background 0.2s ease;
    cursor: pointer;
}
.note-dropzone--compact .note-dropzone-inner {
    display: flex;
    align-items: center;
    gap: 10px;
    text-align: left;
}
.note-dropzone--compact .note-dropzone-inner i {
    font-size: 1.1rem;
    margin-bottom: 0;
    flex-shrink: 0;
}
.note-dropzone--compact .note-dropzone-inner p {
    margin: 0;
    font-size: 0.875rem;
    font-weight: 500;
    line-height: 1.3;
}
.note-dropzone--compact .note-dropzone-inner small {
    display: block;
    font-size: 0.75rem;
    margin-top: 2px;
}
.note-attachments-optional {
    font-weight: 400;
    font-size: 0.85rem;
}
.note-page-drop-overlay {
    position: absolute;
    inset: 0;
    z-index: 30;
    display: none;
    align-items: center;
    justify-content: center;
    background: rgba(59, 130, 246, 0.12);
    border: 3px dashed #3b82f6;
    border-radius: inherit;
    pointer-events: none;
}
.note-page-drop-overlay.is-active {
    display: flex;
}
.note-page-drop-overlay__inner {
    text-align: center;
    color: #1d4ed8;
    background: rgba(255, 255, 255, 0.95);
    padding: 28px 40px;
    border-radius: 16px;
    box-shadow: 0 10px 40px rgba(37, 99, 235, 0.2);
}
.note-page-drop-overlay__inner i {
    font-size: 2.5rem;
    margin-bottom: 8px;
}
.note-page-drop-overlay__inner p {
    margin: 0;
    font-weight: 700;
    font-size: 1.1rem;
}
.create-note-modal.note-modal-drag-active,
.note-modal-with-drop.note-modal-drag-active {
    outline: 2px solid #3b82f6;
    outline-offset: -2px;
}
.note-spend-mins-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: #eff6ff;
    color: #1d4ed8;
    border-radius: 999px;
    padding: 4px 10px;
    font-size: 0.8rem;
    font-weight: 600;
    margin-bottom: 10px;
}
.note-dropzone.is-dragover {
    border-color: #3b82f6;
    background: #eff6ff;
}
.note-dropzone .note-attachments-input {
    position: absolute;
    width: 1px;
    height: 1px;
    opacity: 0;
    overflow: hidden;
    pointer-events: none;
}
.note-dropzone-inner i {
    font-size: 1.35rem;
    color: #2563eb;
    margin-bottom: 6px;
}
.note-dropzone-inner p {
    margin: 4px 0;
    font-weight: 600;
    color: #334155;
}
.note-dropzone-inner p span {
    color: #2563eb;
    text-decoration: underline;
}
.note-dropzone-inner small {
    color: #64748b;
}
.note-selected-files,
.note-existing-attachments {
    list-style: none;
    padding: 0;
    margin: 10px 0 0 0;
}
.note-file-chip,
.note-existing-chip {
    display: flex;
    align-items: center;
    gap: 8px;
    background: #f1f5f9;
    border-radius: 10px;
    padding: 8px 12px;
    margin-bottom: 6px;
    font-size: 0.9rem;
    color: #1e293b;
}
.note-file-chip button,
.note-existing-chip button {
    margin-left: auto;
    border: none;
    background: transparent;
    color: #94a3b8;
    cursor: pointer;
}
.note-file-chip button:hover,
.note-existing-chip button:hover {
    color: #ef4444;
}
.note-attachments-list {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 14px;
}
.note-attachment-thumb {
    display: flex;
    flex-direction: column;
    width: 110px;
    text-decoration: none;
    color: #334155;
    font-size: 0.75rem;
}
.note-attachment-thumb img {
    width: 110px;
    height: 80px;
    object-fit: cover;
    border-radius: 10px;
    border: 1px solid #e2e8f0;
    background: #f8fafc;
}
.note-attachment-thumb span,
.note-attachment-name {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    margin-top: 4px;
}
.note-attachment-file {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 8px 12px;
    text-decoration: none;
    color: #1e293b;
    font-size: 0.85rem;
    max-width: 100%;
}
.note-attachment-file:hover {
    border-color: #93c5fd;
    color: #1d4ed8;
}
.note-attachment-size {
    color: #64748b;
    font-size: 0.75rem;
}
</style>
