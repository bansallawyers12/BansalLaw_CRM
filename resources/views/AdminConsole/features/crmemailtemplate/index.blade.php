@extends('layouts.crm_client_detail')
@include('components.require-tinymce')
@section('title', 'Crm Email Template')

@section('content')

<div class="main-content adminconsole-features adminconsole-crm-email-template adminconsole-crm-email-template-form">
	<section class="section">
		<div class="section-body">
			<div class="server-error">
				@include('../Elements/flash-message')
			</div>
			<div class="custom-error-msg"></div>
			<div class="row">
				<div class="col-3 col-md-3 col-lg-3">
					@include('../Elements/CRM/setting')
				</div>
				<div class="col-9 col-md-9 col-lg-9">
					<div class="card">
						<div class="card-header">
							<h4>Crm Email Template</h4>
							<div class="card-header-action">
								<button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createCrmEmailTemplateModal">
									<i class="fa-solid fa-plus"></i> Create Email Template
								</button>
							</div>
						</div>
						<div class="card-body">
							<div class="table-responsive common_table">
								<table class="table text_wrap">
									<thead>
										<tr>
											<th>Name</th>
											<th>Subject</th>
											<th class="text-nowrap">Action</th>
										</tr>
									</thead>
									@if(@$totalData !== 0)
									<tbody class="tdata" id="crm-email-templates-tbody">
									@foreach (@$lists as $list)
										@include('AdminConsole.features.crmemailtemplate.partials.row', ['list' => $list])
									@endforeach
									</tbody>
									@else
									<tbody id="crm-email-templates-tbody">
										<tr id="crm-email-templates-empty-row">
											<td style="text-align:center;" colspan="3">No Record found</td>
										</tr>
									</tbody>
									@endif
								</table>
							</div>
						</div>
						<div class="card-footer">
							{{ @$lists->links() }}
						</div>
					</div>
				</div>
			</div>
		</div>
	</section>
</div>

{{-- Create CRM Email Template Modal --}}
<div class="modal fade" id="createCrmEmailTemplateModal" tabindex="-1" aria-labelledby="createCrmEmailTemplateModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-lg modal-dialog-scrollable">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="createCrmEmailTemplateModalLabel">Create Email Template</h5>
				<button type="button" class="close" data-bs-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
			</div>
			<form id="create-crm-email-template-form" autocomplete="off" novalidate>
				@csrf
				<div class="modal-body">
					<div id="create-crm-email-template-alert" class="alert alert-danger d-none" role="alert"></div>
					<div class="form-group">
						<label for="create_crm_template_name">Name <span class="span_req">*</span></label>
						<input type="text" id="create_crm_template_name" name="name" class="form-control" maxlength="255" required>
						<span class="custom-error field-error" data-field="name" role="alert"></span>
					</div>
					<div class="form-group">
						<label for="create_crm_template_subject">Subject</label>
						<input type="text" id="create_crm_template_subject" name="subject" class="form-control" maxlength="255">
						<span class="custom-error field-error" data-field="subject" role="alert"></span>
					</div>
					<div class="form-group mb-0">
						<label for="create_crm_template_description">Description</label>
						<textarea id="create_crm_template_description" class="form-control tinymce-editor" name="description"></textarea>
						<span class="custom-error field-error" data-field="description" role="alert"></span>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
					<button type="submit" class="btn btn-primary" id="create-crm-email-template-submit">
						<span class="submit-label">Save</span>
						<span class="submit-spinner spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
					</button>
				</div>
			</form>
		</div>
	</div>
</div>

{{-- Edit CRM Email Template Modal --}}
<div class="modal fade" id="editCrmEmailTemplateModal" tabindex="-1" aria-labelledby="editCrmEmailTemplateModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-lg modal-dialog-scrollable">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="editCrmEmailTemplateModalLabel">Edit Email Template</h5>
				<button type="button" class="close" data-bs-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
			</div>
			<form id="edit-crm-email-template-form" autocomplete="off" novalidate>
				@csrf
				@method('PUT')
				<input type="hidden" id="edit_crm_template_id" name="id" value="">
				<div class="modal-body">
					<div id="edit-crm-email-template-alert" class="alert alert-danger d-none" role="alert"></div>
					<div class="form-group">
						<label for="edit_crm_template_name">Name <span class="span_req">*</span></label>
						<input type="text" id="edit_crm_template_name" name="name" class="form-control" maxlength="255" required>
						<span class="custom-error field-error" data-field="name" role="alert"></span>
					</div>
					<div class="form-group">
						<label for="edit_crm_template_subject">Subject</label>
						<input type="text" id="edit_crm_template_subject" name="subject" class="form-control" maxlength="255">
						<span class="custom-error field-error" data-field="subject" role="alert"></span>
					</div>
					<div class="form-group mb-0">
						<label for="edit_crm_template_description">Description</label>
						<textarea id="edit_crm_template_description" class="form-control tinymce-editor" name="description"></textarea>
						<span class="custom-error field-error" data-field="description" role="alert"></span>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
					<button type="submit" class="btn btn-primary" id="edit-crm-email-template-submit">
						<span class="submit-label">Update</span>
						<span class="submit-spinner spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
					</button>
				</div>
			</form>
		</div>
	</div>
</div>

{{-- Delete CRM Email Template Confirmation Modal --}}
<div class="modal fade" id="deleteCrmEmailTemplateModal" tabindex="-1" aria-labelledby="deleteCrmEmailTemplateModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered">
		<div class="modal-content crm-email-template-delete-modal">
			<div class="modal-header border-0 pb-0">
				<h5 class="modal-title" id="deleteCrmEmailTemplateModalLabel">Delete Email Template</h5>
				<button type="button" class="close" data-bs-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
			</div>
			<div class="modal-body pt-2">
				<div class="crm-email-template-delete-confirm text-center">
					<div class="crm-email-template-delete-icon mb-3" aria-hidden="true">
						<i class="fa-solid fa-trash-can"></i>
					</div>
					<p class="mb-1">Are you sure you want to delete <strong id="delete-crm-email-template-name"></strong>?</p>
					<p class="text-muted small mb-0">This action cannot be undone.</p>
				</div>
				<div id="delete-crm-email-template-error" class="alert alert-danger d-none mt-3 mb-0" role="alert"></div>
			</div>
			<div class="modal-footer border-0 pt-0">
				<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
				<button type="button" class="btn btn-danger" id="confirm-delete-crm-email-template-btn">
					<span class="submit-label">Delete</span>
					<span class="submit-spinner spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
				</button>
			</div>
		</div>
	</div>
</div>

@endsection

@push('scripts')
<script>
jQuery(document).ready(function($) {
	var storeUrl = @json(route('adminconsole.features.crmemailtemplate.store'));
	var updateUrlTemplate = @json(route('adminconsole.features.crmemailtemplate.update', ['id' => '__ID__']));
	var editUrlTemplate = @json(route('adminconsole.features.crmemailtemplate.edit', ['id' => '__ID__']));
	var deleteUrl = @json(url('/delete_action'));
	var emptyLabel = @json(config('constants.empty', '—'));
	var $tbody = $('#crm-email-templates-tbody');
	var pendingDeleteTemplateId = null;

	var crmTplEditorConfig = {
		license_key: 'gpl',
		height: 200,
		min_height: 150,
		max_height: 400,
		menubar: false,
		statusbar: true,
		resize: true,
		plugins: ['lists', 'link', 'autolink', 'autoresize', 'wordcount', 'searchreplace'],
		toolbar: 'undo redo | bold italic underline strikethrough | forecolor backcolor | bullist numlist | link | removeformat',
		content_style: 'body { font-family: -apple-system, BlinkMacSystemFont, San Francisco, Segoe UI, Roboto, Helvetica Neue, sans-serif; font-size: 14px; padding: 12px 15px; }',
		branding: false,
		promotion: false,
		setup: function(editor) {
			editor.on('change', function() {
				editor.save();
			});
		}
	};

	function escapeHtml(value) {
		return $('<div/>').text(value == null ? '' : String(value)).html();
	}

	function attrEscape(value) {
		return String(value == null ? '' : value)
			.replace(/&/g, '&amp;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#39;')
			.replace(/</g, '&lt;');
	}

	function showFlashMessage(message, type) {
		var alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
		$('.server-error').html(
			'<div class="alert ' + alertClass + ' alert-dismissible fade show" role="alert">' +
			escapeHtml(message) +
			'<button type="button" class="close" data-bs-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>'
		);
	}

	function hideModal($modal) {
		var modalInstance = bootstrap.Modal.getInstance($modal[0]);
		if (modalInstance) {
			modalInstance.hide();
		}
	}

	function ensureTableBodyHasDataClass() {
		if (!$tbody.hasClass('tdata')) {
			$tbody.addClass('tdata');
		}
		$('#crm-email-templates-empty-row').remove();
	}

	function ensureEmptyStateIfNeeded() {
		if ($tbody.find('tr[data-template-id]').length === 0) {
			$tbody.removeClass('tdata').html(
				'<tr id="crm-email-templates-empty-row"><td style="text-align:center;" colspan="3">No Record found</td></tr>'
			);
		}
	}

	function clearFormErrors($form, $alert) {
		$alert.addClass('d-none').text('');
		$form.find('.field-error').text('');
		$form.find('.is-invalid').removeClass('is-invalid');
	}

	function showFieldErrors($form, $alert, errors) {
		clearFormErrors($form, $alert);
		var messages = [];
		$.each(errors, function(field, msgs) {
			var msg = Array.isArray(msgs) ? msgs[0] : msgs;
			messages.push(msg);
			$form.find('.field-error[data-field="' + field + '"]').html('<strong>' + escapeHtml(msg) + '</strong>');
			$form.find('[name="' + field + '"]').addClass('is-invalid');
		});
		if (messages.length) {
			$alert.removeClass('d-none').text(messages[0]);
		}
	}

	function setSubmitting($submit, isSubmitting) {
		$submit.prop('disabled', isSubmitting);
		$submit.find('.submit-label').toggleClass('d-none', isSubmitting);
		$submit.find('.submit-spinner').toggleClass('d-none', !isSubmitting);
	}

	function initCrmTemplateEditor(selector) {
		if (typeof tinymce === 'undefined') {
			return;
		}
		var $el = $(selector);
		if (!$el.length) {
			return;
		}
		var editorId = $el.attr('id');
		if (!editorId || tinymce.get(editorId)) {
			return;
		}
		tinymce.init($.extend({}, crmTplEditorConfig, { selector: '#' + editorId }));
	}

	function destroyCrmTemplateEditor(selector) {
		if (typeof tinymce === 'undefined') {
			return;
		}
		var editorId = $(selector).attr('id');
		if (editorId && tinymce.get(editorId)) {
			tinymce.get(editorId).remove();
		}
	}

	function getCrmTemplateEditorContent(selector) {
		var editorId = $(selector).attr('id');
		if (editorId && typeof tinymce !== 'undefined' && tinymce.get(editorId)) {
			tinymce.get(editorId).save();
			return tinymce.get(editorId).getContent();
		}
		return $(selector).val() || '';
	}

	function setCrmTemplateEditorContent(selector, content) {
		var editorId = $(selector).attr('id');
		if (editorId && typeof tinymce !== 'undefined' && tinymce.get(editorId)) {
			tinymce.get(editorId).setContent(content || '');
		} else {
			$(selector).val(content || '');
		}
	}

	function buildCrmEmailTemplateRow(template) {
		return '<tr id="id_' + template.id + '"' +
			' data-template-id="' + template.id + '"' +
			' data-template-encoded-id="' + attrEscape(template.encoded_id) + '"' +
			' data-template-name="' + attrEscape(template.name) + '"' +
			' data-template-subject="' + attrEscape(template.subject || '') + '">' +
			'<td class="crm-email-template-name-cell">' + escapeHtml(template.display_name || emptyLabel) + '</td>' +
			'<td class="crm-email-template-subject-cell">' + escapeHtml(template.display_subject || emptyLabel) + '</td>' +
			'<td class="text-nowrap"><div class="dropdown d-inline-block">' +
			'<button class="btn btn-primary dropdown-toggle" type="button" id="crmTplAction_' + template.id + '" data-bs-toggle="dropdown" data-bs-popper-config=\'{"strategy":"fixed"}\' aria-haspopup="true" aria-expanded="false">Action</button>' +
			'<ul class="dropdown-menu dropdown-menu-end crm-email-template-action-menu" aria-labelledby="crmTplAction_' + template.id + '">' +
			'<li><a class="dropdown-item has-icon edit-crm-email-template-btn" href="javascript:void(0);"><i class="fa-regular fa-pen-to-square"></i> Edit</a></li>' +
			'<li><a class="dropdown-item has-icon delete-crm-email-template-btn" href="javascript:void(0);"><i class="fa-solid fa-trash"></i> Delete</a></li>' +
			'</ul></div></td></tr>';
	}

	function upsertCrmEmailTemplateRow(template) {
		ensureTableBodyHasDataClass();
		var $existing = $('#id_' + template.id);
		var $row = $(buildCrmEmailTemplateRow(template));
		if ($existing.length) {
			$existing.replaceWith($row);
		} else {
			$tbody.prepend($row);
		}
	}

	$('#createCrmEmailTemplateModal').on('shown.bs.modal', function() {
		clearFormErrors($('#create-crm-email-template-form'), $('#create-crm-email-template-alert'));
		$('#create-crm-email-template-form')[0].reset();
		$('#create_crm_template_description').val('');
		initCrmTemplateEditor('#create_crm_template_description');
		setCrmTemplateEditorContent('#create_crm_template_description', '');
	});

	$('#createCrmEmailTemplateModal').on('hidden.bs.modal', function() {
		destroyCrmTemplateEditor('#create_crm_template_description');
	});

	$('#editCrmEmailTemplateModal').on('hidden.bs.modal', function() {
		destroyCrmTemplateEditor('#edit_crm_template_description');
	});

	$('#create-crm-email-template-form').on('submit', function(e) {
		e.preventDefault();
		clearFormErrors($('#create-crm-email-template-form'), $('#create-crm-email-template-alert'));
		setSubmitting($('#create-crm-email-template-submit'), true);

		var formData = $('#create-crm-email-template-form').serializeArray();
		formData.push({ name: 'description', value: getCrmTemplateEditorContent('#create_crm_template_description') });

		$.ajax({
			url: storeUrl,
			method: 'POST',
			data: $.param(formData),
			headers: {
				'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
				'Accept': 'application/json',
				'X-Requested-With': 'XMLHttpRequest'
			},
			success: function(response) {
				setSubmitting($('#create-crm-email-template-submit'), false);
				hideModal($('#createCrmEmailTemplateModal'));
				if (response && response.template) {
					upsertCrmEmailTemplateRow(response.template);
				}
				showFlashMessage((response && response.message) ? response.message : 'Crm Email Template Added Successfully', 'success');
			},
			error: function(xhr) {
				setSubmitting($('#create-crm-email-template-submit'), false);
				if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
					showFieldErrors($('#create-crm-email-template-form'), $('#create-crm-email-template-alert'), xhr.responseJSON.errors);
					return;
				}
				var message = (xhr.responseJSON && xhr.responseJSON.message)
					? xhr.responseJSON.message
					: 'Unable to create email template. Please try again.';
				$('#create-crm-email-template-alert').removeClass('d-none').text(message);
			}
		});
	});

	$(document).on('click', '.edit-crm-email-template-btn', function(e) {
		e.preventDefault();
		var $row = $(this).closest('tr[data-template-id]');
		if (!$row.length) {
			return;
		}

		var encodedId = $row.attr('data-template-encoded-id');
		if (!encodedId) {
			return;
		}

		clearFormErrors($('#edit-crm-email-template-form'), $('#edit-crm-email-template-alert'));
		setSubmitting($('#edit-crm-email-template-submit'), true);

		$.ajax({
			url: editUrlTemplate.replace('__ID__', encodedId),
			method: 'GET',
			headers: {
				'Accept': 'application/json',
				'X-Requested-With': 'XMLHttpRequest'
			},
			success: function(response) {
				setSubmitting($('#edit-crm-email-template-submit'), false);
				if (!response || !response.template) {
					showFlashMessage('Unable to load email template.', 'error');
					return;
				}

				var template = response.template;
				$('#edit_crm_template_id').val(template.id);
				$('#edit_crm_template_name').val(template.name || '');
				$('#edit_crm_template_subject').val(template.subject || '');
				$('#edit_crm_template_description').val(template.description || '');

				var editModalEl = document.getElementById('editCrmEmailTemplateModal');
				var editModal = bootstrap.Modal.getInstance(editModalEl);
				if (!editModal) {
					editModal = new bootstrap.Modal(editModalEl);
				}
				editModal.show();

				$('#editCrmEmailTemplateModal').one('shown.bs.modal', function() {
					initCrmTemplateEditor('#edit_crm_template_description');
					setCrmTemplateEditorContent('#edit_crm_template_description', template.description || '');
				});
			},
			error: function(xhr) {
				setSubmitting($('#edit-crm-email-template-submit'), false);
				var message = (xhr.responseJSON && xhr.responseJSON.message)
					? xhr.responseJSON.message
					: 'Unable to load email template. Please try again.';
				showFlashMessage(message, 'error');
			}
		});
	});

	$('#edit-crm-email-template-form').on('submit', function(e) {
		e.preventDefault();
		clearFormErrors($('#edit-crm-email-template-form'), $('#edit-crm-email-template-alert'));

		var templateId = $('#edit_crm_template_id').val();
		if (!templateId) {
			return;
		}

		setSubmitting($('#edit-crm-email-template-submit'), true);

		var formData = $('#edit-crm-email-template-form').serializeArray();
		formData.push({ name: 'description', value: getCrmTemplateEditorContent('#edit_crm_template_description') });

		$.ajax({
			url: updateUrlTemplate.replace('__ID__', templateId),
			method: 'POST',
			data: $.param(formData),
			headers: {
				'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
				'Accept': 'application/json',
				'X-Requested-With': 'XMLHttpRequest'
			},
			success: function(response) {
				setSubmitting($('#edit-crm-email-template-submit'), false);
				hideModal($('#editCrmEmailTemplateModal'));
				if (response && response.template) {
					upsertCrmEmailTemplateRow(response.template);
				}
				showFlashMessage((response && response.message) ? response.message : 'Crm Email Template Updated Successfully', 'success');
			},
			error: function(xhr) {
				setSubmitting($('#edit-crm-email-template-submit'), false);
				if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
					showFieldErrors($('#edit-crm-email-template-form'), $('#edit-crm-email-template-alert'), xhr.responseJSON.errors);
					return;
				}
				var message = (xhr.responseJSON && xhr.responseJSON.message)
					? xhr.responseJSON.message
					: 'Unable to update email template. Please try again.';
				$('#edit-crm-email-template-alert').removeClass('d-none').text(message);
			}
		});
	});

	$(document).on('click', '.delete-crm-email-template-btn', function(e) {
		e.preventDefault();
		var $row = $(this).closest('tr[data-template-id]');
		if (!$row.length) {
			return;
		}

		pendingDeleteTemplateId = $row.attr('data-template-id');
		$('#delete-crm-email-template-name').text($row.attr('data-template-name') || 'this template');
		$('#delete-crm-email-template-error').addClass('d-none').text('');
		setSubmitting($('#confirm-delete-crm-email-template-btn'), false);

		var deleteModalEl = document.getElementById('deleteCrmEmailTemplateModal');
		var deleteModal = bootstrap.Modal.getInstance(deleteModalEl);
		if (!deleteModal) {
			deleteModal = new bootstrap.Modal(deleteModalEl);
		}
		deleteModal.show();
	});

	$('#deleteCrmEmailTemplateModal').on('hidden.bs.modal', function() {
		pendingDeleteTemplateId = null;
		setSubmitting($('#confirm-delete-crm-email-template-btn'), false);
		$('#delete-crm-email-template-error').addClass('d-none').text('');
	});

	$('#confirm-delete-crm-email-template-btn').on('click', function() {
		if (!pendingDeleteTemplateId) {
			return;
		}

		setSubmitting($('#confirm-delete-crm-email-template-btn'), true);
		$('#delete-crm-email-template-error').addClass('d-none').text('');

		$.ajax({
			url: deleteUrl,
			method: 'POST',
			data: {
				id: pendingDeleteTemplateId,
				table: 'email_templates'
			},
			headers: {
				'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
			},
			success: function(resp) {
				var obj = typeof resp === 'object' ? resp : $.parseJSON(resp);
				if (obj.status == 1) {
					$('#id_' + pendingDeleteTemplateId).remove();
					ensureEmptyStateIfNeeded();
					hideModal($('#deleteCrmEmailTemplateModal'));
					showFlashMessage(obj.message || 'Record has been deleted successfully.', 'success');
					pendingDeleteTemplateId = null;
					return;
				}

				$('#delete-crm-email-template-error').removeClass('d-none').text(obj.message || 'Unable to delete email template.');
				setSubmitting($('#confirm-delete-crm-email-template-btn'), false);
			},
			error: function() {
				$('#delete-crm-email-template-error').removeClass('d-none').text('Unable to delete email template. Please try again.');
				setSubmitting($('#confirm-delete-crm-email-template-btn'), false);
			}
		});
	});
});
</script>
@endpush
