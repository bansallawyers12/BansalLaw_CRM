@extends('layouts.crm_client_detail')
@section('title', 'Email Labels')

@section('content')

<!-- Main Content -->
<div class="main-content adminconsole-features adminconsole-email-labels">
	<section class="section">
		<div class="section-body">
			<div class="server-error">
				@include('../Elements/flash-message')
			</div>
			<div class="custom-error-msg">
			</div>
			<div class="row">
				<div class="col-3 col-md-3 col-lg-3">
			        @include('../Elements/CRM/setting')
		        </div>
				<div class="col-9 col-md-9 col-lg-9">
					<div class="card">
						<div class="card-header">
							<h4>Email Labels</h4>
							<div class="card-header-action">
								<button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createEmailLabelModal">
									<i class="fa fa-plus"></i> Create Email Label
								</button>
							</div>
						</div>
						<div class="card-body">
							<div class="table-responsive common_table">
								<table class="table text_wrap">
								<thead>
									<tr>
										<th>Label</th>
										<th>Name</th>
										<th>Type</th>
										<th>Created By</th>
										<th>Status</th>
										<th>Last Updated</th>
										<th>Action</th>
									</tr>
								</thead>
								@if(@$totalData !== 0)
								<tbody class="tdata" id="email-labels-tbody">
								@foreach (@$lists as $list)
									@include('AdminConsole.features.emaillabels.partials.row', ['list' => $list])
								@endforeach
								</tbody>
								@else
								<tbody id="email-labels-tbody">
									<tr id="email-labels-empty-row">
										<td style="text-align:center;" colspan="7">
											No Record found
										</td>
									</tr>
								</tbody>
								@endif
							</table>
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

{{-- Create Email Label Modal --}}
<div class="modal fade adminconsole-email-labels-form" id="createEmailLabelModal" tabindex="-1" aria-labelledby="createEmailLabelModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-lg modal-dialog-scrollable">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="createEmailLabelModalLabel">Create Email Label</h5>
				<button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<form id="create-email-label-form" name="add-email-label" autocomplete="off" novalidate>
				@csrf
				<div class="modal-body">
					<div id="create-email-label-alert" class="alert alert-danger d-none" role="alert"></div>
					<div class="row g-3">
						<div class="col-12 col-md-6">
							<div class="form-group mb-0">
								<label for="create_label_name">Label Name <span class="span_req">*</span></label>
								<input type="text" id="create_label_name" name="name" class="form-control" autocomplete="off" placeholder="Enter label name" required>
								<span class="custom-error field-error" data-field="name" role="alert"></span>
							</div>
						</div>
						<div class="col-12 col-md-6">
							<div class="form-group mb-0">
								<label for="create_label_type">Type <span class="span_req">*</span></label>
								<select id="create_label_type" name="type" class="form-control" required>
									<option value="custom" selected>Custom</option>
									<option value="system">System</option>
								</select>
								<span class="custom-error field-error" data-field="type" role="alert"></span>
							</div>
						</div>
						<div class="col-12 col-md-6">
							<div class="form-group mb-0">
								<label for="create_colorPicker">Color <span class="span_req">*</span></label>
								<div class="input-group">
									<input type="color" name="color" class="form-control" id="create_colorPicker" value="#3A6FA8" style="max-width: 56px;" required>
									<input type="text" class="form-control" id="create_colorHex" value="#3A6FA8" placeholder="#3A6FA8" pattern="^#[0-9A-Fa-f]{6}$" required aria-label="Color hex value">
								</div>
								<small class="form-text text-muted">Select a color or enter hex (e.g. <code class="text-muted">#3A6FA8</code>)</small>
								<span class="custom-error field-error" data-field="color" role="alert"></span>
							</div>
						</div>
						<div class="col-12 col-md-6">
							<div class="form-group mb-0">
								<label for="create_label_icon">Icon</label>
								<input type="text" id="create_label_icon" name="icon" class="form-control" autocomplete="off" placeholder="fas fa-tag" value="fas fa-tag">
								<small class="form-text text-muted">Font Awesome class (e.g. fas fa-tag, fas fa-star)</small>
								<span class="custom-error field-error" data-field="icon" role="alert"></span>
							</div>
						</div>
						<div class="col-12">
							<div class="form-group mb-0">
								<label for="create_label_description">Description</label>
								<textarea id="create_label_description" name="description" class="form-control" rows="3" placeholder="Optional description"></textarea>
								<span class="custom-error field-error" data-field="description" role="alert"></span>
							</div>
						</div>
						<div class="col-12">
							<div class="email-label-preview-wrap">
								<span class="text-muted small d-block mb-1">Preview</span>
								<span class="badge email-label-badge" id="create_label_preview" style="background-color: #3A6FA820; border: 1px solid #3A6FA8; color: #3A6FA8;">
									<i class="fas fa-tag"></i> <span class="preview-name">Label name</span>
								</span>
							</div>
						</div>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
					<button type="submit" class="btn btn-primary" id="create-email-label-submit">
						<span class="submit-label">Save</span>
						<span class="submit-spinner spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
					</button>
				</div>
			</form>
		</div>
	</div>
</div>

{{-- Edit Email Label Modal --}}
<div class="modal fade adminconsole-email-labels-form" id="editEmailLabelModal" tabindex="-1" aria-labelledby="editEmailLabelModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-lg modal-dialog-scrollable">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="editEmailLabelModalLabel">Edit Email Label</h5>
				<button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<form id="edit-email-label-form" name="edit-email-label" autocomplete="off" novalidate>
				@csrf
				@method('PUT')
				<input type="hidden" id="edit_label_id" name="id" value="">
				<div class="modal-body">
					<div id="edit-email-label-alert" class="alert alert-danger d-none" role="alert"></div>
					<div class="row g-3">
						<div class="col-12 col-md-6">
							<div class="form-group mb-0">
								<label for="edit_label_name">Label Name <span class="span_req">*</span></label>
								<input type="text" id="edit_label_name" name="name" class="form-control" autocomplete="off" placeholder="Enter label name" required>
								<span class="custom-error field-error" data-field="name" role="alert"></span>
							</div>
						</div>
						<div class="col-12 col-md-6">
							<div class="form-group mb-0">
								<label for="edit_label_type_display">Type</label>
								<input type="text" id="edit_label_type_display" class="form-control" value="Custom" disabled>
								<small class="form-text text-muted">Label type cannot be changed</small>
							</div>
						</div>
						<div class="col-12 col-md-6">
							<div class="form-group mb-0">
								<label for="edit_colorPicker">Color <span class="span_req">*</span></label>
								<div class="input-group">
									<input type="color" name="color" class="form-control" id="edit_colorPicker" value="#3A6FA8" style="max-width: 56px;" required>
									<input type="text" class="form-control" id="edit_colorHex" value="#3A6FA8" placeholder="#3A6FA8" pattern="^#[0-9A-Fa-f]{6}$" required aria-label="Color hex value">
								</div>
								<small class="form-text text-muted">Select a color or enter hex (e.g. <code class="text-muted">#3A6FA8</code>)</small>
								<span class="custom-error field-error" data-field="color" role="alert"></span>
							</div>
						</div>
						<div class="col-12 col-md-6">
							<div class="form-group mb-0">
								<label for="edit_label_icon">Icon</label>
								<input type="text" id="edit_label_icon" name="icon" class="form-control" autocomplete="off" placeholder="fas fa-tag" value="fas fa-tag">
								<small class="form-text text-muted">Font Awesome class (e.g. fas fa-tag, fas fa-star)</small>
								<span class="custom-error field-error" data-field="icon" role="alert"></span>
							</div>
						</div>
						<div class="col-12 col-md-6">
							<div class="form-group mb-0">
								<label for="edit_label_status">Status</label>
								<select id="edit_label_status" name="is_active" class="form-control">
									<option value="1">Active</option>
									<option value="0">Inactive</option>
								</select>
								<span class="custom-error field-error" data-field="is_active" role="alert"></span>
							</div>
						</div>
						<div class="col-12">
							<div class="form-group mb-0">
								<label for="edit_label_description">Description</label>
								<textarea id="edit_label_description" name="description" class="form-control" rows="3" placeholder="Optional description"></textarea>
								<span class="custom-error field-error" data-field="description" role="alert"></span>
							</div>
						</div>
						<div class="col-12">
							<div class="email-label-preview-wrap">
								<span class="text-muted small d-block mb-1">Preview</span>
								<span class="badge email-label-badge" id="edit_label_preview" style="background-color: #3A6FA820; border: 1px solid #3A6FA8; color: #3A6FA8;">
									<i class="fas fa-tag"></i> <span class="preview-name">Label name</span>
								</span>
							</div>
						</div>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
					<button type="submit" class="btn btn-primary" id="edit-email-label-submit">
						<span class="submit-label">Update</span>
						<span class="submit-spinner spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
					</button>
				</div>
			</form>
		</div>
	</div>
</div>

{{-- Delete Email Label Confirmation Modal --}}
<div class="modal fade adminconsole-email-labels-form" id="deleteEmailLabelModal" tabindex="-1" aria-labelledby="deleteEmailLabelModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered">
		<div class="modal-content email-label-delete-modal">
			<div class="modal-header border-0 pb-0">
				<h5 class="modal-title" id="deleteEmailLabelModalLabel">Delete Email Label</h5>
				<button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body pt-2">
				<div class="email-label-delete-confirm text-center">
					<div class="email-label-delete-icon mb-3" aria-hidden="true">
						<i class="fas fa-trash-alt"></i>
					</div>
					<p class="mb-1">Are you sure you want to delete <strong id="delete-email-label-name"></strong>?</p>
					<p class="text-muted small mb-0">This action cannot be undone.</p>
				</div>
				<div id="delete-email-label-error" class="alert alert-danger d-none mt-3 mb-0" role="alert"></div>
			</div>
			<div class="modal-footer border-0 pt-0">
				<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
				<button type="button" class="btn btn-danger" id="confirm-delete-email-label-btn">
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
	var storeUrl = @json(route('adminconsole.features.emaillabels.store'));
	var updateUrlTemplate = @json(route('adminconsole.features.emaillabels.update', ['id' => '__ID__']));
	var deleteUrl = @json(url('/delete_action'));
	var pendingDeleteLabelId = null;
	var emptyLabel = @json(config('constants.empty'));
	var defaultColor = '#3A6FA8';
	var $tbody = $('#email-labels-tbody');

	function escapeHtml(value) {
		return $('<div/>').text(value == null ? '' : String(value)).html();
	}

	function truncateName(name, limit) {
		if (!name) {
			return emptyLabel;
		}
		return name.length > limit ? name.substring(0, limit) + '...' : name;
	}

	function showFlashMessage(message, type) {
		var alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
		$('.server-error').html(
			'<div class="alert ' + alertClass + ' alert-dismissible fade show" role="alert">' +
			escapeHtml(message) +
			'<button type="button" class="close" data-bs-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>'
		);
	}

	function ensureTableBodyHasDataClass() {
		if (!$tbody.hasClass('tdata')) {
			$tbody.addClass('tdata');
		}
		$('#email-labels-empty-row').remove();
	}

	function ensureEmptyStateIfNeeded() {
		if ($tbody.find('tr[data-label-id]').length === 0) {
			$tbody.removeClass('tdata').html(
				'<tr id="email-labels-empty-row"><td style="text-align:center;" colspan="7">No Record found</td></tr>'
			);
		}
	}

	function setDeleteSubmitting(isSubmitting) {
		var $btn = $('#confirm-delete-email-label-btn');
		$btn.prop('disabled', isSubmitting);
		$btn.find('.submit-label').toggleClass('d-none', isSubmitting);
		$btn.find('.submit-spinner').toggleClass('d-none', !isSubmitting);
	}

	function attrEscape(value) {
		return String(value == null ? '' : value)
			.replace(/&/g, '&amp;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#39;')
			.replace(/</g, '&lt;');
	}

	function buildEmailLabelRow(label) {
		var color = label.color || defaultColor;
		var icon = label.icon || 'fas fa-tag';
		var typeBadge = label.type === 'system'
			? '<span class="badge badge-info">System</span>'
			: '<span class="badge badge-secondary">Custom</span>';
		var statusBadge = label.is_active
			? '<span class="badge badge-success">Active</span>'
			: '<span class="badge badge-danger">Inactive</span>';
		var actionMenu = label.type === 'system'
			? '<li><span class="dropdown-item-text text-muted small px-3 py-2 d-block"><i class="far fa-edit me-2"></i>System labels cannot be edited</span></li>' +
			  '<li><span class="dropdown-item-text text-muted small px-3 py-2 d-block"><i class="fas fa-trash me-2"></i>System labels cannot be deleted</span></li>'
			: '<li><a class="dropdown-item has-icon edit-email-label-btn" href="javascript:void(0);"><i class="far fa-edit"></i> Edit</a></li>' +
			  '<li><a class="dropdown-item has-icon delete-email-label-btn" href="javascript:void(0);"><i class="fas fa-trash"></i> Delete</a></li>';

		return '<tr id="id_' + label.id + '"' +
			' data-label-id="' + label.id + '"' +
			' data-label-name="' + attrEscape(label.name) + '"' +
			' data-label-color="' + attrEscape(color) + '"' +
			' data-label-icon="' + attrEscape(icon) + '"' +
			' data-label-type="' + attrEscape(label.type) + '"' +
			' data-label-description="' + attrEscape(label.description || '') + '"' +
			' data-label-is-active="' + (label.is_active ? '1' : '0') + '">' +
			'<td><span class="badge email-label-badge" style="background-color: ' + color + '20; border: 1px solid ' + color + '; color: ' + color + ';">' +
			'<i class="' + escapeHtml(icon) + '"></i> ' + escapeHtml(label.name) + '</span></td>' +
			'<td class="email-label-name-cell">' + escapeHtml(truncateName(label.name, 50)) + '</td>' +
			'<td class="email-label-type-cell">' + typeBadge + '</td>' +
			'<td class="email-label-created-by-cell">' + escapeHtml(label.created_by || 'System') + '</td>' +
			'<td class="email-label-status-cell">' + statusBadge + '</td>' +
			'<td class="email-label-updated-cell">' + escapeHtml(label.updated_at || '-') + '</td>' +
			'<td class="text-nowrap"><div class="dropdown d-inline-block">' +
			'<button class="btn btn-primary dropdown-toggle" type="button" id="actionBtn_' + label.id + '" data-bs-toggle="dropdown" data-bs-popper-config=\'{"strategy":"fixed"}\' aria-expanded="false" aria-haspopup="true">Action</button>' +
			'<ul class="dropdown-menu dropdown-menu-end email-labels-action-menu" aria-labelledby="actionBtn_' + label.id + '">' + actionMenu + '</ul>' +
			'</div></td></tr>';
	}

	function upsertEmailLabelRow(label) {
		ensureTableBodyHasDataClass();
		var $existing = $('#id_' + label.id);
		var $row = $(buildEmailLabelRow(label));
		if ($existing.length) {
			$existing.replaceWith($row);
		} else {
			$tbody.append($row);
		}
	}

	function hideModal($modal) {
		var modalInstance = bootstrap.Modal.getInstance($modal[0]);
		if (modalInstance) {
			modalInstance.hide();
		}
	}

	function setupModalForm(options) {
		var $modal = options.modal;
		var $form = options.form;
		var $alert = options.alert;
		var $submit = options.submit;
		var colorPicker = options.colorPicker;
		var colorHex = options.colorHex;
		var nameInput = options.nameInput;
		var iconInput = options.iconInput;
		var preview = options.preview;

		function clearFormErrors() {
			$alert.addClass('d-none').text('');
			$form.find('.field-error').text('');
			$form.find('.is-invalid').removeClass('is-invalid');
		}

		function showFieldErrors(errors) {
			clearFormErrors();
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

		function updatePreview() {
			var name = $.trim($(nameInput).val()) || 'Label name';
			var color = $(colorHex).val();
			if (!/^#[0-9A-Fa-f]{6}$/.test(color)) {
				color = defaultColor;
			}
			var icon = $.trim($(iconInput).val()) || 'fas fa-tag';
			$(preview).css({
				backgroundColor: color + '20',
				borderColor: color,
				color: color
			});
			$(preview).find('i').attr('class', icon);
			$(preview).find('.preview-name').text(name);
		}

		function setSubmitting(isSubmitting) {
			$submit.prop('disabled', isSubmitting);
			$submit.find('.submit-label').toggleClass('d-none', isSubmitting);
			$submit.find('.submit-spinner').toggleClass('d-none', !isSubmitting);
		}

		$(colorPicker).on('input change', function() {
			$(colorHex).val($(colorPicker).val());
			updatePreview();
		});
		$(colorHex).on('input', function() {
			var hex = $(colorHex).val();
			if (/^#[0-9A-Fa-f]{6}$/.test(hex)) {
				$(colorPicker).val(hex);
				updatePreview();
			}
		});
		$(nameInput + ', ' + iconInput).on('input', updatePreview);

		return {
			clearFormErrors: clearFormErrors,
			showFieldErrors: showFieldErrors,
			updatePreview: updatePreview,
			setSubmitting: setSubmitting,
			validateColor: function() {
				var hexValue = $(colorHex).val();
				if (!/^#[0-9A-Fa-f]{6}$/.test(hexValue)) {
					showFieldErrors({ color: ['Please enter a valid hex color (e.g. #3A6FA8).'] });
					return false;
				}
				$(colorPicker).val(hexValue);
				return true;
			}
		};
	}

	var createUi = setupModalForm({
		modal: $('#createEmailLabelModal'),
		form: $('#create-email-label-form'),
		alert: $('#create-email-label-alert'),
		submit: $('#create-email-label-submit'),
		colorPicker: '#create_colorPicker',
		colorHex: '#create_colorHex',
		nameInput: '#create_label_name',
		iconInput: '#create_label_icon',
		preview: '#create_label_preview'
	});

	var editUi = setupModalForm({
		modal: $('#editEmailLabelModal'),
		form: $('#edit-email-label-form'),
		alert: $('#edit-email-label-alert'),
		submit: $('#edit-email-label-submit'),
		colorPicker: '#edit_colorPicker',
		colorHex: '#edit_colorHex',
		nameInput: '#edit_label_name',
		iconInput: '#edit_label_icon',
		preview: '#edit_label_preview'
	});

	$('#createEmailLabelModal').on('show.bs.modal', function() {
		createUi.clearFormErrors();
		$('#create-email-label-form')[0].reset();
		$('#create_label_type').val('custom');
		$('#create_label_icon').val('fas fa-tag');
		$('#create_colorPicker').val(defaultColor);
		$('#create_colorHex').val(defaultColor);
		createUi.updatePreview();
	});

	$('#create-email-label-form').on('submit', function(e) {
		e.preventDefault();
		createUi.clearFormErrors();
		if (!createUi.validateColor()) {
			return;
		}
		createUi.setSubmitting(true);

		$.ajax({
			url: storeUrl,
			method: 'POST',
			data: $('#create-email-label-form').serialize(),
			headers: {
				'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
				'Accept': 'application/json',
				'X-Requested-With': 'XMLHttpRequest'
			},
			success: function(response) {
				createUi.setSubmitting(false);
				hideModal($('#createEmailLabelModal'));
				if (response && response.label) {
					upsertEmailLabelRow(response.label);
				}
				showFlashMessage((response && response.message) ? response.message : 'Email Label Created Successfully', 'success');
			},
			error: function(xhr) {
				createUi.setSubmitting(false);
				if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
					createUi.showFieldErrors(xhr.responseJSON.errors);
					return;
				}
				var message = (xhr.responseJSON && xhr.responseJSON.message)
					? xhr.responseJSON.message
					: 'Unable to create email label. Please try again.';
				$('#create-email-label-alert').removeClass('d-none').text(message);
			}
		});
	});

	$(document).on('click', '.edit-email-label-btn', function(e) {
		e.preventDefault();
		var $row = $(this).closest('tr[data-label-id]');
		if (!$row.length) {
			return;
		}

		editUi.clearFormErrors();
		var labelId = $row.attr('data-label-id');
		var labelType = $row.attr('data-label-type') || 'custom';
		var labelColor = $row.attr('data-label-color') || defaultColor;
		var labelIcon = $row.attr('data-label-icon') || 'fas fa-tag';
		var labelName = $row.attr('data-label-name') || '';
		var labelDescription = $row.attr('data-label-description') || '';
		var isActive = $row.attr('data-label-is-active') === '1' ? '1' : '0';

		$('#edit_label_id').val(labelId);
		$('#edit_label_name').val(labelName);
		$('#edit_label_type_display').val(labelType === 'system' ? 'System' : 'Custom');
		$('#edit_colorPicker').val(labelColor);
		$('#edit_colorHex').val(labelColor);
		$('#edit_label_icon').val(labelIcon);
		$('#edit_label_description').val(labelDescription);
		$('#edit_label_status').val(isActive);
		editUi.updatePreview();

		var editModalEl = document.getElementById('editEmailLabelModal');
		var editModal = bootstrap.Modal.getInstance(editModalEl);
		if (!editModal) {
			editModal = new bootstrap.Modal(editModalEl);
		}
		editModal.show();
	});

	$('#edit-email-label-form').on('submit', function(e) {
		e.preventDefault();
		editUi.clearFormErrors();
		if (!editUi.validateColor()) {
			return;
		}

		var labelId = $('#edit_label_id').val();
		if (!labelId) {
			return;
		}

		editUi.setSubmitting(true);

		$.ajax({
			url: updateUrlTemplate.replace('__ID__', labelId),
			method: 'POST',
			data: $('#edit-email-label-form').serialize(),
			headers: {
				'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
				'Accept': 'application/json',
				'X-Requested-With': 'XMLHttpRequest'
			},
			success: function(response) {
				editUi.setSubmitting(false);
				hideModal($('#editEmailLabelModal'));
				if (response && response.label) {
					upsertEmailLabelRow(response.label);
				}
				showFlashMessage((response && response.message) ? response.message : 'Email Label Updated Successfully', 'success');
			},
			error: function(xhr) {
				editUi.setSubmitting(false);
				if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
					editUi.showFieldErrors(xhr.responseJSON.errors);
					return;
				}
				var message = (xhr.responseJSON && xhr.responseJSON.message)
					? xhr.responseJSON.message
					: 'Unable to update email label. Please try again.';
				$('#edit-email-label-alert').removeClass('d-none').text(message);
			}
		});
	});

	$(document).on('click', '.delete-email-label-btn', function(e) {
		e.preventDefault();
		var $row = $(this).closest('tr[data-label-id]');
		if (!$row.length) {
			return;
		}

		pendingDeleteLabelId = $row.attr('data-label-id');
		$('#delete-email-label-name').text($row.attr('data-label-name') || 'this label');
		$('#delete-email-label-error').addClass('d-none').text('');
		setDeleteSubmitting(false);

		var deleteModalEl = document.getElementById('deleteEmailLabelModal');
		var deleteModal = bootstrap.Modal.getInstance(deleteModalEl);
		if (!deleteModal) {
			deleteModal = new bootstrap.Modal(deleteModalEl);
		}
		deleteModal.show();
	});

	$('#deleteEmailLabelModal').on('hidden.bs.modal', function() {
		pendingDeleteLabelId = null;
		setDeleteSubmitting(false);
		$('#delete-email-label-error').addClass('d-none').text('');
	});

	$('#confirm-delete-email-label-btn').on('click', function() {
		if (!pendingDeleteLabelId) {
			return;
		}

		setDeleteSubmitting(true);
		$('#delete-email-label-error').addClass('d-none').text('');

		$.ajax({
			url: deleteUrl,
			method: 'POST',
			data: {
				id: pendingDeleteLabelId,
				table: 'email_labels'
			},
			headers: {
				'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
			},
			success: function(resp) {
				var obj = typeof resp === 'object' ? resp : $.parseJSON(resp);
				if (obj.status == 1) {
					$('#id_' + pendingDeleteLabelId).remove();
					ensureEmptyStateIfNeeded();
					hideModal($('#deleteEmailLabelModal'));
					showFlashMessage(obj.message || 'Record has been deleted successfully.', 'success');
					pendingDeleteLabelId = null;
					return;
				}

				$('#delete-email-label-error').removeClass('d-none').text(obj.message || 'Unable to delete email label.');
				setDeleteSubmitting(false);
			},
			error: function() {
				$('#delete-email-label-error').removeClass('d-none').text('Unable to delete email label. Please try again.');
				setDeleteSubmitting(false);
			}
		});
	});
});
</script>
@endpush
