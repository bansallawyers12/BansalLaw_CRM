<!-- Legacy create_checklist modal removed - functionality moved to adminconsole DocumentChecklist -->
@php
    $__documentChecklistsOk = \Illuminate\Support\Facades\Schema::hasTable('document_checklists');
@endphp

<!-- Add Personal Checklist Modal -->
<div class="modal fade create_education_docs custom_modal" id="openeducationdocsmodal" tabindex="-1" role="dialog" aria-labelledby="taskModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="taskModalLabel">Add Personal Checklist</h5>
				<button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body">
                @if (! $__documentChecklistsOk)
                    <div class="alert alert-warning">Document checklist options require the <code>document_checklists</code> table. Run <code>php artisan migrate</code>, then add items under Admin → Document checklist.</div>
                @endif
				<form method="post" action="{{URL::to('/documents/add-edu-checklist')}}" name="edu_upload_form" id="edu_upload_form" autocomplete="off"  enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="clientid" value="{{$fetchedData->id}}">
                    <input type="hidden" name="type" value="client">
                    <input type="hidden" name="doctype" value="personal">
                    <input type="hidden" name="doccategory" id="doccategory" value="">
                    <input type="hidden" name="folder_name" id="folder_name" value="">

                    <div class="row">
                        <div class="col-6 col-md-6 col-lg-6">
							<div class="form-group">
								<label for="checklist">Select Checklist<span class="span_req">*</span></label>
								<select data-valid="required" class="form-control crm-ts-plain" name="checklist[]" id="checklist" multiple placeholder="Type or select checklist name...">
									<?php
									$eduChkList = $__documentChecklistsOk
                                        ? \App\Models\DocumentChecklist::where('status', 1)->where('doc_type', 1)->get()
                                        : collect();
									foreach($eduChkList as $edulist){
									?>
										<option value="{{$edulist->name}}">{{$edulist->name}}</option>
									<?php
									}
									?>
								</select>
								<small class="text-muted d-block mt-1">Type a checklist name, then click Create (or press Enter to add multiple names).</small>
								<span class="custom-error checklist_name_error" role="alert">
									<strong></strong>
								</span>
							</div>
						</div>
                    </div>
					<div class="row">
						<div class="col-12 col-md-12 col-lg-12">
							<button onclick="customValidate('edu_upload_form')" type="button" class="btn btn-primary" style="margin: 0px !important;">Create</button>
							<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
						</div>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>

<!-- Add matter document checklist modal (doc_type visa / matter documents tab) -->
<div class="modal fade create_migration_docs custom_modal" id="openmigrationdocsmodal" tabindex="-1" role="dialog" aria-labelledby="taskModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="taskModalLabel">Add Matter Document Checklist</h5>
				<button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body">
                @if (! $__documentChecklistsOk)
                    <div class="alert alert-warning">Document checklist options require the <code>document_checklists</code> table. Run <code>php artisan migrate</code>.</div>
                @endif
				<form method="post" action="{{ route('clients.documents.addMatterDocChecklist') }}" name="mig_upload_form" id="mig_upload_form" autocomplete="off"  enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="clientid" value="{{$fetchedData->id}}">
                    <input type="hidden" name="type" value="client">
                    <input type="hidden" name="doctype" value="matter">
                    <input type="hidden" name="client_matter_id" id="hidden_client_matter_id" value="">
                    <input type="hidden" name="folder_name" id="visa_folder_name" value="">

					<div class="row">
                        <div class="col-6 col-md-6 col-lg-6">
							<div class="form-group">
								<label for="visa_checklist">Select Checklist<span class="span_req">*</span></label>
								<select data-valid="required" class="form-control crm-ts-plain" name="visa_checklist[]" id="visa_checklist" multiple placeholder="Type or select checklist name...">
									<?php
									$visaChkList = $__documentChecklistsOk
                                        ? \App\Models\DocumentChecklist::where('status', 1)->where('doc_type', 2)->get()
                                        : collect();
									foreach($visaChkList as $visalist){
									?>
										<option value="{{$visalist->name}}">{{$visalist->name}}</option>
									<?php
									}
									?>
								</select>
								<small class="text-muted d-block mt-1">Type a checklist name, then click Create (or press Enter to add multiple names).</small>
								<span class="custom-error visa_checklist_error" role="alert">
									<strong></strong>
								</span>
							</div>
						</div>
                    </div>

                    <div class="row">
						<div class="col-12 col-md-12 col-lg-12">
							<button onclick="customValidate('mig_upload_form')" type="button" class="btn btn-primary" style="margin: 0px !important;">Create</button>
							<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
						</div>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>

{{-- create_checklist modal REMOVED - workflow checklist unused --}}

<!-- Inline Signature Placement Modal (Checklist Agreements) -->
<div class="modal fade" id="signaturePlacementModal" tabindex="-1" role="dialog" aria-labelledby="signaturePlacementModalLabel" aria-hidden="true" data-backdrop="static">
	<div class="modal-dialog modal-xl modal-dialog-scrollable" role="document" style="max-width: 95%; max-height: 90vh;">
		<div class="modal-content" style="max-height: 90vh;">
			<div class="modal-header bg-warning text-dark">
				<h5 class="modal-title" id="signaturePlacementModalLabel">
					<i class="fa-solid fa-pen-nib me-2"></i>Place Signature Fields
				</h5>
				<button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body p-4" style="max-height: calc(90vh - 150px); overflow-y: auto;">
				<div id="signature-placement-loading" class="text-center py-5">
					<div class="spinner-border text-primary" role="status"><span class="sr-only">Loading...</span></div>
					<p class="mt-2" style="color: #374151;">Loading document...</p>
				</div>
				<div id="signature-placement-content" style="display: none;">
					<div class="row">
						<div class="col-lg-8">
							<div class="mb-2">
								<small style="color: #4b5563;">Document Preview — Click on the document to add signature fields, then drag to position them.</small>
							</div>
							<div id="signature-page-nav" class="mb-3" style="display: none;">
								<div class="btn-group btn-group-sm">
									<button type="button" class="btn btn-outline-secondary" id="sig-prev-page">&larr; Prev</button>
									<span class="px-3 align-self-center" id="sig-page-info">Page 1</span>
									<button type="button" class="btn btn-outline-secondary" id="sig-next-page">Next &rarr;</button>
								</div>
							</div>
							<div class="position-relative d-inline-block border rounded overflow-hidden" id="sig-preview-container" style="min-height: 400px;">
								<img id="sig-preview-image" src="" alt="PDF Preview" style="max-width: 100%; height: auto; display: block;">
								<div id="sig-fields-preview"></div>
							</div>
						</div>
						<div class="col-lg-4">
							<h6 class="font-weight-bold mb-2">Signature Fields</h6>
							<div id="sig-fields-container" class="mb-3" style="max-height: 280px; overflow-y: auto;"></div>
							<button type="button" class="btn btn-outline-primary btn-sm btn-block mb-3" id="sig-add-field">
								<i class="fa-solid fa-plus me-1"></i>Add Signature Field
							</button>
						</div>
					</div>
				</div>
				<div id="signature-placement-error" class="alert alert-danger" style="display: none;"></div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
				<button type="button" class="btn btn-success" id="sig-save-btn">
					<i class="fa-solid fa-floppy-disk me-1"></i>Save Signature Locations
				</button>
			</div>
		</div>
	</div>
</div>

<style>
    #sig-preview-container { position: relative; }
    #sig-fields-preview {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        pointer-events: none;
    }
    .sig-field-preview {
        position: absolute;
        border: 2px dashed #3b82f6;
        background: rgba(59, 130, 246, 0.15);
        cursor: move;
        pointer-events: auto;
        user-select: none;
        touch-action: none;
    }
    .sig-field-preview:hover { background: rgba(59, 130, 246, 0.25); }
    .sig-field-preview.dragging { border-color: #1d4ed8; box-shadow: 0 2px 8px rgba(59, 130, 246, 0.4); }
    .sig-field-preview.sig-field-preview-selected { border-color: #1d4ed8; box-shadow: 0 0 0 2px rgba(29, 78, 216, 0.5); background: rgba(59, 130, 246, 0.25); }
    .sig-field-row-selected { background-color: rgba(59, 130, 246, 0.08); border-color: #3b82f6; }
    .sig-field-label {
        position: absolute;
        top: -18px;
        left: 0;
        background: #3b82f6;
        color: #fff;
        padding: 2px 6px;
        font-size: 10px;
        border-radius: 3px;
        white-space: nowrap;
    }
    .sig-field-preview.sig-field-preview-flash {
        animation: sigFieldPulse 1.1s ease-out 1;
    }

    @keyframes sigFieldPulse {
        0% { box-shadow: 0 0 0 0 rgba(29, 78, 216, 0.85); }
        70% { box-shadow: 0 0 0 12px rgba(29, 78, 216, 0); }
        100% { box-shadow: 0 0 0 0 rgba(29, 78, 216, 0); }
    }
</style>

@push('scripts')
<script>
    (function ($) {
        'use strict';
        // Inline signature placement modal controller.
        // This script is intentionally placed in the shared modal partial so it works
        // from multiple tabs (Matter Documents / Checklists) on the client detail page.

        if (!$('#signaturePlacementModal').length) {
            return;
        }

        var sigState = {
            documentId: null,
            pdfPages: 1,
            pagesDimensions: {},
            pdfWidthMM: 210,
            pdfHeightMM: 297,
            currentPage: 1,
            signatureFields: [],
            selectedFieldIndex: -1,
            isDragging: false,
            dragFieldIndex: -1,
            dragOffsetX: 0,
            dragOffsetY: 0
        };

        function openSignaturePlacementModal(docId) {
            if (!docId) return;
            sigState.documentId = docId;
            sigState.signatureFields = [];
            sigState.currentPage = 1;
            sigState.selectedFieldIndex = -1;

            var $modal = $('#signaturePlacementModal');
            if ($modal.length && !$modal.parent().is('body')) {
                $modal.appendTo('body');
            }
            if (typeof $modal.modal === 'function') {
                $modal.modal('show');
            } else if (window.bootstrap && $modal[0]) {
                bootstrap.Modal.getOrCreateInstance($modal[0]).show();
            }

            $('#signature-placement-loading').show();
            $('#signature-placement-content').hide();
            $('#signature-placement-error').hide();

            $.ajax({
                url: '/documents/' + docId + '/signature-placement-data',
                method: 'GET',
                headers: { 'Accept': 'application/json' }
            }).done(function (data) {
                if (!data.success) {
                    $('#signature-placement-loading').hide();
                    $('#signature-placement-error').text(data.message || 'Failed to load document.').show();
                    return;
                }

                sigState.pdfPages = data.pdfPages || 1;
                sigState.pagesDimensions = data.pagesDimensions || {};
                sigState.pdfWidthMM = data.pdfWidthMM || 210;
                sigState.pdfHeightMM = data.pdfHeightMM || 297;

                sigState.signatureFields = (data.existingFields || []).map(function (f, i) {
                    var toDecimal = function (v) { return (v > 1 ? v / 100 : v) || 0; };
                    return {
                        id: Date.now() + i,
                        page_number: f.page_number,
                        x_percent: toDecimal(f.x_percent),
                        y_percent: toDecimal(f.y_percent),
                        w_percent: Math.max(0.05, toDecimal(f.w_percent)),
                        h_percent: Math.max(0.03, toDecimal(f.h_percent))
                    };
                });

                $('#signature-placement-loading').hide();
                $('#signature-placement-content').show();
                $('#sig-preview-image').attr('src', '/debug-pdf-page/' + docId + '/1');

                if (sigState.pdfPages > 1) {
                    $('#signature-page-nav').show();
                    $('#sig-prev-page').prop('disabled', true);
                    $('#sig-next-page').prop('disabled', sigState.pdfPages <= 1);
                } else {
                    $('#signature-page-nav').hide();
                }

                sigState.currentPage = 1;
                updateSigPageInfo();
                updateSigForm();
                updateSigPreview();
                bindSigEvents();
            }).fail(function (xhr) {
                $('#signature-placement-loading').hide();
                var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Failed to load document.';
                $('#signature-placement-error').text(msg).show();
            });
        }

        // Exposed via document event so other tabs can trigger it.
        $(document).on('openSignaturePlacementModal', function (e, data) {
            if (data && data.documentId) openSignaturePlacementModal(data.documentId);
        });

        $(document).on('click', '.btn-place-signature-fields', function () {
            openSignaturePlacementModal($(this).data('document-id'));
        });

        function updateSigPageInfo() {
            $('#sig-page-info').text('Page ' + sigState.currentPage + ' of ' + sigState.pdfPages);
            $('#sig-prev-page').prop('disabled', sigState.currentPage <= 1);
            $('#sig-next-page').prop('disabled', sigState.currentPage >= sigState.pdfPages);
        }

        function getSigDisplayDims() {
            var $img = $('#sig-preview-image');
            return { width: $img.length ? $img[0].clientWidth : 0, height: $img.length ? $img[0].clientHeight : 0 };
        }

        function sigSwitchPage(p) {
            if (p < 1 || p > sigState.pdfPages) return;
            sigState.currentPage = p;
            $('#sig-preview-image').attr('src', '/debug-pdf-page/' + sigState.documentId + '/' + p);
            updateSigPageInfo();
            updateSigPreview();
        }

        function sigAddField(page, x, y) {
            var dims = getSigDisplayDims();
            var w = 150, h = 75;
            var xP = dims.width ? x / dims.width : 0;
            var yP = dims.height ? y / dims.height : 0;
            var wP = dims.width ? w / dims.width : 0.2;
            var hP = dims.height ? h / dims.height : 0.1;

            sigState.signatureFields.push({
                id: Date.now(),
                page_number: page,
                x_percent: xP,
                y_percent: yP,
                w_percent: wP,
                h_percent: hP
            });
            updateSigForm();
            updateSigPreview();
            sigState.selectedFieldIndex = sigState.signatureFields.length - 1;
        }

        function updateSigForm() {
            var html = '';
            sigState.signatureFields.forEach(function (f, i) {
                var rowClass = 'd-flex justify-content-between align-items-center mb-2 p-2 border rounded sig-field-row';
                if (i === sigState.selectedFieldIndex) rowClass += ' sig-field-row-selected';
                var editBtnClass = (i === sigState.selectedFieldIndex) ? 'btn btn-primary btn-sm sig-edit-field me-1' : 'btn btn-outline-secondary btn-sm sig-edit-field me-1';
                html += '<div class="' + rowClass + '" data-index="' + i + '">';
                html += '<span class="small">Signature ' + (i + 1) + ' (Pg ' + f.page_number + ')</span>';
                html += '<div><button type="button" class="' + editBtnClass + '" data-index="' + i + '">Focus</button>';
                html += '<button type="button" class="btn btn-outline-danger btn-sm sig-delete-field" data-index="' + i + '">Delete</button></div></div>';
            });
            html += '<small class="text-muted d-block mt-2">Use Focus, then drag the blue box on the preview to reposition it.</small>';
            $('#sig-fields-container').html(html || '<small class="text-muted">No fields. Click on the document or Add Signature Field.</small>');
        }

        function updateSigPreview() {
            var $container = $('#sig-fields-preview');
            $container.empty();
            var dims = getSigDisplayDims();
            sigState.signatureFields.forEach(function (f, i) {
                if (f.page_number !== sigState.currentPage) return;
                var previewClass = 'sig-field-preview';
                if (i === sigState.selectedFieldIndex) previewClass += ' sig-field-preview-selected';
                var $el = $('<div class="' + previewClass + '" data-index="' + i + '"></div>');
                $el.css({
                    left: (f.x_percent * dims.width) + 'px',
                    top: (f.y_percent * dims.height) + 'px',
                    width: (f.w_percent * dims.width) + 'px',
                    height: (f.h_percent * dims.height) + 'px'
                });
                $el.html('<span class="sig-field-label">Signature ' + (i + 1) + '</span>');
                $container.append($el);
            });
        }

        function bindSigEvents() {
            $('#sig-preview-container').off('click.sig').on('click.sig', function (e) {
                if ($(e.target).is('#sig-preview-image')) {
                    var rect = e.target.getBoundingClientRect();
                    sigAddField(sigState.currentPage, e.clientX - rect.left, e.clientY - rect.top);
                }
            });

            $('#sig-add-field').off('click.sig').on('click.sig', function () {
                var dims = getSigDisplayDims();
                sigAddField(sigState.currentPage, dims.width / 2, dims.height / 2);
            });

            $('#sig-prev-page').off('click.sig').on('click.sig', function () { sigSwitchPage(sigState.currentPage - 1); });
            $('#sig-next-page').off('click.sig').on('click.sig', function () { sigSwitchPage(sigState.currentPage + 1); });

            $(document).off('click.sig', '.sig-delete-field').on('click.sig', '.sig-delete-field', function (e) {
                e.preventDefault();
                var i = parseInt($(this).data('index'), 10);
                if (!isNaN(i) && confirm('Delete this signature field?')) {
                    sigState.signatureFields.splice(i, 1);
                    sigState.selectedFieldIndex = -1;
                    updateSigForm();
                    updateSigPreview();
                }
            });

            $(document).off('click.sig', '.sig-edit-field').on('click.sig', '.sig-edit-field', function (e) {
                e.preventDefault();
                var i = parseInt($(this).data('index'), 10);
                if (!isNaN(i) && sigState.signatureFields[i]) {
                    sigState.selectedFieldIndex = i;
                    if (sigState.signatureFields[i].page_number !== sigState.currentPage) {
                        sigSwitchPage(sigState.signatureFields[i].page_number);
                    }
                    updateSigForm();
                    updateSigPreview();
                    var previewContainer = $('#sig-preview-container')[0];
                    if (previewContainer && typeof previewContainer.scrollIntoView === 'function') {
                        previewContainer.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                    setTimeout(function () {
                        var $target = $('#sig-fields-preview .sig-field-preview[data-index="' + i + '"]');
                        if ($target.length) {
                            $target.addClass('sig-field-preview-flash');
                            setTimeout(function () {
                                $target.removeClass('sig-field-preview-flash');
                            }, 1200);
                        }
                    }, 60);
                }
            });

            $('#sig-preview-image').off('load.sig').on('load.sig', function () { updateSigPreview(); });

            // Drag to reposition signature fields (mouse + touch)
            function getSigPointerCoords(e) {
                var clientX = e.clientX, clientY = e.clientY, offsetX = e.offsetX, offsetY = e.offsetY;
                if (e.originalEvent && e.originalEvent.touches && e.originalEvent.touches.length) {
                    var t = e.originalEvent.touches[0];
                    clientX = t.clientX;
                    clientY = t.clientY;
                    var target = e.target;
                    var tr = target.getBoundingClientRect();
                    offsetX = t.clientX - tr.left;
                    offsetY = t.clientY - tr.top;
                }
                return { clientX: clientX, clientY: clientY, offsetX: offsetX, offsetY: offsetY };
            }

            $(document).off('mousedown.sig touchstart.sig', '.sig-field-preview').on('mousedown.sig touchstart.sig', '.sig-field-preview', function (e) {
                e.preventDefault();
                var coords = (e.type === 'touchstart') ? getSigPointerCoords(e) : { clientX: e.clientX, clientY: e.clientY, offsetX: e.offsetX, offsetY: e.offsetY };
                var i = parseInt($(this).data('index'), 10);
                if (isNaN(i) || !sigState.signatureFields[i]) return;
                sigState.isDragging = true;
                sigState.dragFieldIndex = i;
                sigState.dragOffsetX = coords.offsetX;
                sigState.dragOffsetY = coords.offsetY;
                $(this).addClass('dragging');
            });

            $(document).off('mousemove.sig touchmove.sig').on('mousemove.sig touchmove.sig', function (e) {
                if (!sigState.isDragging || sigState.dragFieldIndex < 0) return;
                if (e.type === 'touchmove') e.preventDefault();

                var coords = (e.type === 'touchmove') ? getSigPointerCoords(e) : { clientX: e.clientX, clientY: e.clientY };
                var f = sigState.signatureFields[sigState.dragFieldIndex];
                if (!f || f.page_number !== sigState.currentPage) return;

                var $img = $('#sig-preview-image');
                if (!$img.length) return;
                var rect = $img[0].getBoundingClientRect();
                var w = rect.width, h = rect.height;
                if (!w || !h) return;

                var localX = coords.clientX - rect.left - sigState.dragOffsetX;
                var localY = coords.clientY - rect.top - sigState.dragOffsetY;

                var maxX = w * (1 - f.w_percent);
                var maxY = h * (1 - f.h_percent);
                localX = Math.max(0, Math.min(localX, maxX));
                localY = Math.max(0, Math.min(localY, maxY));

                f.x_percent = localX / w;
                f.y_percent = localY / h;

                var $el = $('#sig-fields-preview .sig-field-preview[data-index="' + sigState.dragFieldIndex + '"]');
                if ($el.length) {
                    $el.css({
                        left: (f.x_percent * w) + 'px',
                        top: (f.y_percent * h) + 'px'
                    }).addClass('dragging');
                } else {
                    updateSigPreview();
                    $('#sig-fields-preview .sig-field-preview[data-index="' + sigState.dragFieldIndex + '"]').addClass('dragging');
                }
            });

            $(document).off('mouseup.sig touchend.sig touchcancel.sig').on('mouseup.sig touchend.sig touchcancel.sig', function () {
                if (sigState.isDragging) {
                    sigState.isDragging = false;
                    sigState.dragFieldIndex = -1;
                    $('.sig-field-preview').removeClass('dragging');
                }
            });

            $('#sig-save-btn').off('click.sig').on('click.sig', function () {
                if (sigState.signatureFields.length === 0) {
                    crmAlert('Please add at least one signature field.');
                    return;
                }

                var signatures = sigState.signatureFields.map(function (f) {
                    return {
                        page_number: parseInt(f.page_number, 10),
                        x_percent: parseFloat((f.x_percent * 100).toFixed(2)),
                        y_percent: parseFloat((f.y_percent * 100).toFixed(2)),
                        w_percent: parseFloat((f.w_percent * 100).toFixed(2)),
                        h_percent: parseFloat((f.h_percent * 100).toFixed(2))
                    };
                });

                var $btn = $(this);
                $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Saving...');
                var postData = {
                    _method: 'PATCH',
                    _token: ($('meta[name="csrf-token"]').attr('content') || $('input[name="_token"]').val()),
                    signatures: signatures
                };

                $.ajax({
                    url: '/documents/' + sigState.documentId,
                    method: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify(postData),
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                }).done(function (resp) {
                    if (resp && resp.source) $('#signaturePlacementModal').data('lastSaveSource', resp.source);
                    else $('#signaturePlacementModal').removeData('lastSaveSource');

                    $('#signaturePlacementModal').modal('hide');
                    if (resp && resp.success) {
                        crmAlert(resp.message || 'Signature fields saved. The signing link is now available.');
                        if (resp.redirect_url) window.location.href = resp.redirect_url;
                    } else {
                        crmAlert((resp && resp.message) ? resp.message : 'An error occurred.');
                    }
                }).fail(function (xhr) {
                    var msg = 'Failed to save signature fields.';
                    if (xhr.responseJSON) {
                        if (xhr.responseJSON.message) msg = xhr.responseJSON.message;
                        else if (xhr.responseJSON.errors) msg = Object.values(xhr.responseJSON.errors).flat().join(' ');
                    } else if (xhr.status === 419) msg = 'Session expired. Please refresh the page and try again.';
                    else if (xhr.responseText && xhr.responseText.length < 200) msg = xhr.responseText;
                    crmAlert(msg);
                }).always(function () {
                    $btn.prop('disabled', false).html('<i class="fa-solid fa-floppy-disk me-1"></i>Save Signature Locations');
                });
            });
        }

        $('#signaturePlacementModal').on('hidden.bs.modal', function () {
            sigState.isDragging = false;
            sigState.dragFieldIndex = -1;
            $('.sig-field-preview').removeClass('dragging');
            $('#sig-preview-image').attr('src', '');
            var source = $('#signaturePlacementModal').data('lastSaveSource');
            if (source === 'matter_documents' || source === 'visa_documents') {
                localStorage.setItem('activeTab', 'matterdocuments');
            } else {
                localStorage.setItem('activeTab', 'checklists');
            }
            location.reload();
        });
    })(jQuery);
</script>
@endpush

