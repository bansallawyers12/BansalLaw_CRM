@extends('layouts.crm_client_detail')
@section('title', 'Branches')

@section('content')

<div class="main-content adminconsole-features adminconsole-offices adminconsole-offices-form">
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
							<h4>All Branches</h4>
							<div class="card-header-action">
								<button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createOfficeModal">
									<i class="fa-solid fa-plus"></i> Create Branch
								</button>
							</div>
						</div>
						<div class="card-body">
							<div class="table-responsive common_table">
								<table class="table text_wrap offices-table">
									<thead>
										<tr>
											<th>Name</th>
											<th>City</th>
											<th>Country</th>
											<th>Mobile</th>
											<th>Phone</th>
											<th>Contact Person</th>
											<th class="text-nowrap">Action</th>
										</tr>
									</thead>
									@if(@$totalData !== 0)
									<tbody class="tdata" id="offices-tbody">
									@foreach (@$lists as $list)
										@include('AdminConsole.system.offices.partials.row', ['list' => $list])
									@endforeach
									</tbody>
									@else
									<tbody id="offices-tbody">
										<tr id="offices-empty-row">
											<td style="text-align:center;" colspan="7">No Record found</td>
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

{{-- Create Branch Modal --}}
<div class="modal fade office-form-modal" id="createOfficeModal" tabindex="-1" aria-labelledby="createOfficeModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-xl modal-dialog-scrollable">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="createOfficeModalLabel">Create Branch</h5>
				<button type="button" class="close" data-bs-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
			</div>
			<form id="create-office-form" autocomplete="off" novalidate>
				@csrf
				<div class="modal-body">
					<div id="create-office-alert" class="alert alert-danger d-none" role="alert"></div>
					@include('AdminConsole.system.offices.partials.form-fields', ['fieldPrefix' => 'create', 'countries' => $countries])
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
					<button type="submit" class="btn btn-primary" id="create-office-submit">
						<span class="submit-label">Save Branch</span>
						<span class="submit-spinner spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
					</button>
				</div>
			</form>
		</div>
	</div>
</div>

{{-- Edit Branch Modal --}}
<div class="modal fade office-form-modal" id="editOfficeModal" tabindex="-1" aria-labelledby="editOfficeModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-xl modal-dialog-scrollable">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="editOfficeModalLabel">Edit Branch</h5>
				<button type="button" class="close" data-bs-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
			</div>
			<form id="edit-office-form" autocomplete="off" novalidate>
				@csrf
				@method('PUT')
				<input type="hidden" id="edit_office_id" name="id" value="">
				<div class="modal-body">
					<div id="edit-office-alert" class="alert alert-danger d-none" role="alert"></div>
					@include('AdminConsole.system.offices.partials.form-fields', ['fieldPrefix' => 'edit', 'countries' => $countries])
				</div>
				<div class="modal-footer">
					<a href="#" id="edit-office-view-link" class="btn btn-outline-primary me-auto"><i class="fa-regular fa-eye"></i> View Branch</a>
					<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
					<button type="submit" class="btn btn-primary" id="edit-office-submit">
						<span class="submit-label">Update Branch</span>
						<span class="submit-spinner spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
					</button>
				</div>
			</form>
		</div>
	</div>
</div>

{{-- Delete Branch Confirmation Modal --}}
<div class="modal fade office-form-modal" id="deleteOfficeModal" tabindex="-1" aria-labelledby="deleteOfficeModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered">
		<div class="modal-content office-delete-modal">
			<div class="modal-header border-0 pb-0">
				<h5 class="modal-title" id="deleteOfficeModalLabel">Delete Branch</h5>
				<button type="button" class="close" data-bs-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
			</div>
			<div class="modal-body pt-2">
				<div class="office-delete-confirm text-center">
					<div class="office-delete-icon mb-3" aria-hidden="true">
						<i class="fa-solid fa-trash-can"></i>
					</div>
					<p class="mb-1">Are you sure you want to delete <strong id="delete-office-name"></strong>?</p>
					<p class="text-muted small mb-0">This action cannot be undone.</p>
				</div>
				<div id="delete-office-error" class="alert alert-danger d-none mt-3 mb-0" role="alert"></div>
			</div>
			<div class="modal-footer border-0 pt-0">
				<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
				<button type="button" class="btn btn-danger" id="confirm-delete-office-btn">
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
	var storeUrl = @json(route('adminconsole.system.offices.store'));
	var updateUrlTemplate = @json(route('adminconsole.system.offices.update', ['id' => '__ID__']));
	var editUrlTemplate = @json(route('adminconsole.system.offices.edit', ['id' => '__ID__']));
	var deleteUrl = @json(url('/delete_action'));
	var emptyLabel = @json(config('constants.empty', '—'));
	var $tbody = $('#offices-tbody');
	var pendingDeleteOfficeId = null;

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
		$('#offices-empty-row').remove();
	}

	function ensureEmptyStateIfNeeded() {
		if ($tbody.find('tr[data-office-id]').length === 0) {
			$tbody.removeClass('tdata').html(
				'<tr id="offices-empty-row"><td style="text-align:center;" colspan="7">No Record found</td></tr>'
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
		var firstField = null;
		$.each(errors, function(field, msgs) {
			var msg = Array.isArray(msgs) ? msgs[0] : msgs;
			messages.push(msg);
			if (!firstField) {
				firstField = field;
			}
			$form.find('.field-error[data-field="' + field + '"]').html('<strong>' + escapeHtml(msg) + '</strong>');
			$form.find('[name="' + field + '"]').addClass('is-invalid');
		});
		if (messages.length) {
			$alert.removeClass('d-none').text(messages[0]);
		}
		if (firstField) {
			expandOfficeSectionForField($form, firstField);
		}
	}

	function getOfficeFormPrefix($form) {
		return $form.attr('id') === 'create-office-form' ? 'create' : 'edit';
	}

	function resetOfficeAccordion(prefix) {
		var $accordion = $('#' + prefix + '_office_accordion');
		if (!$accordion.length) {
			return;
		}

		$accordion.find('.accordion-body').removeClass('show');
		$accordion.find('.accordion-header').addClass('collapsed').attr('aria-expanded', 'false');

		var $primary = $('#' + prefix + '_office_primary');
		var $primaryHeader = $accordion.find('[data-bs-target="#' + prefix + '_office_primary"]');
		$primary.addClass('show');
		$primaryHeader.removeClass('collapsed').attr('aria-expanded', 'true');
	}

	function expandOfficeSectionForField($form, field) {
		var prefix = getOfficeFormPrefix($form);
		var sectionId = prefix + '_office_primary';

		if (['address', 'city', 'state', 'zip', 'country'].indexOf(field) !== -1) {
			sectionId = prefix + '_office_address';
		} else if (['email', 'phone', 'mobile', 'contact_person'].indexOf(field) !== -1) {
			sectionId = prefix + '_office_contact';
		} else if (field === 'choose_admin') {
			sectionId = prefix + '_office_other';
		}

		var $section = $('#' + sectionId);
		var $header = $('[data-bs-target="#' + sectionId + '"]');
		if ($section.length && !$section.hasClass('show')) {
			if (typeof bootstrap !== 'undefined' && bootstrap.Collapse) {
				bootstrap.Collapse.getOrCreateInstance($section[0], { toggle: false }).show();
			} else {
				$section.addClass('show');
				$header.removeClass('collapsed').attr('aria-expanded', 'true');
			}
		}
	}

	function setSubmitting($submit, isSubmitting) {
		$submit.prop('disabled', isSubmitting);
		$submit.find('.submit-label').toggleClass('d-none', isSubmitting);
		$submit.find('.submit-spinner').toggleClass('d-none', !isSubmitting);
	}

	function buildOfficeTomSelectConfig(modalEl) {
		if (typeof buildPlainSingleTomSelectConfig !== 'function') {
			return { maxItems: 1, create: false, allowEmptyOption: true, dropdownParent: modalEl };
		}
		return buildPlainSingleTomSelectConfig({ dropdownParent: modalEl });
	}

	function initOfficeTomSelects(modalEl, prefix) {
		if (typeof initTS !== 'function') {
			return;
		}
		var config = buildOfficeTomSelectConfig(modalEl);
		['country', 'choose_admin'].forEach(function(field) {
			var select = modalEl.querySelector('#' + prefix + '_' + field);
			if (select) {
				initTS(select, config);
			}
		});
	}

	function destroyOfficeTomSelects(modalEl, prefix) {
		if (typeof destroyTS !== 'function') {
			return;
		}
		['country', 'choose_admin'].forEach(function(field) {
			var select = modalEl.querySelector('#' + prefix + '_' + field);
			if (select) {
				destroyTS(select);
			}
		});
	}

	function setOfficeSelectValue(prefix, field, value) {
		var select = document.getElementById(prefix + '_' + field);
		if (!select) {
			return;
		}
		var normalized = value == null ? '' : String(value);
		var ts = typeof getTomSelectInstance === 'function' ? getTomSelectInstance(select) : null;
		if (ts) {
			ts.setValue(normalized, true);
			return;
		}
		$(select).val(normalized);
	}

	function populateEditForm(office) {
		$('#edit_office_id').val(office.id);
		$('#edit_office_name').val(office.office_name || '');
		$('#edit_address').val(office.address || '');
		$('#edit_city').val(office.city || '');
		$('#edit_state').val(office.state || '');
		$('#edit_zip').val(office.zip || '');
		$('#edit_email').val(office.email || '');
		$('#edit_phone').val(office.phone || '');
		$('#edit_mobile').val(office.mobile || '');
		$('#edit_contact_person').val(office.contact_person || '');
		$('#edit-office-view-link').attr('href', office.view_url || '#');
		setOfficeSelectValue('edit', 'country', office.country || '');
		setOfficeSelectValue('edit', 'choose_admin', office.choose_admin || '');
	}

	function buildOfficeRow(office) {
		return '<tr id="id_' + office.id + '"' +
			' data-office-id="' + office.id + '"' +
			' data-office-encoded-id="' + attrEscape(office.encoded_id) + '"' +
			' data-office-name="' + attrEscape(office.office_name) + '">' +
			'<td class="office-name-cell"><a href="' + escapeHtml(office.view_url) + '" class="office-view-link">' + escapeHtml(office.display_name || emptyLabel) + '</a></td>' +
			'<td class="office-city-cell">' + escapeHtml(office.display_city || emptyLabel) + '</td>' +
			'<td class="office-country-cell">' + escapeHtml(office.display_country || emptyLabel) + '</td>' +
			'<td class="office-mobile-cell">' + escapeHtml(office.display_mobile || emptyLabel) + '</td>' +
			'<td class="office-phone-cell">' + escapeHtml(office.display_phone || emptyLabel) + '</td>' +
			'<td class="office-contact-cell">' + escapeHtml(office.display_contact_person || emptyLabel) + '</td>' +
			'<td class="text-nowrap"><div class="dropdown d-inline-block">' +
			'<button class="btn btn-primary dropdown-toggle" type="button" id="officeAction_' + office.id + '" data-bs-toggle="dropdown" data-bs-popper-config=\'{"strategy":"fixed"}\' aria-haspopup="true" aria-expanded="false">Action</button>' +
			'<ul class="dropdown-menu dropdown-menu-end offices-action-menu" aria-labelledby="officeAction_' + office.id + '">' +
			'<li><a class="dropdown-item has-icon" href="' + escapeHtml(office.view_url) + '"><i class="fa-regular fa-eye"></i> View</a></li>' +
			'<li><a class="dropdown-item has-icon edit-office-btn" href="javascript:void(0);"><i class="fa-regular fa-edit"></i> Edit</a></li>' +
			'<li><a class="dropdown-item has-icon delete-office-btn" href="javascript:void(0);"><i class="fa-solid fa-trash"></i> Delete</a></li>' +
			'</ul></div></td></tr>';
	}

	function upsertOfficeRow(office) {
		ensureTableBodyHasDataClass();
		var $existing = $('#id_' + office.id);
		var $row = $(buildOfficeRow(office));
		if ($existing.length) {
			$existing.replaceWith($row);
		} else {
			$tbody.prepend($row);
		}
	}

	$('#createOfficeModal').on('show.bs.modal', function() {
		clearFormErrors($('#create-office-form'), $('#create-office-alert'));
		$('#create-office-form')[0].reset();
		resetOfficeAccordion('create');
		setOfficeSelectValue('create', 'country', '');
		setOfficeSelectValue('create', 'choose_admin', '');
	});

	$('#createOfficeModal').on('shown.bs.modal', function() {
		initOfficeTomSelects(this, 'create');
	});

	$('#createOfficeModal').on('hidden.bs.modal', function() {
		destroyOfficeTomSelects(this, 'create');
	});

	$('#editOfficeModal').on('shown.bs.modal', function() {
		initOfficeTomSelects(this, 'edit');
	});

	$('#editOfficeModal').on('hidden.bs.modal', function() {
		destroyOfficeTomSelects(this, 'edit');
	});

	$('#create-office-form').on('submit', function(e) {
		e.preventDefault();
		clearFormErrors($('#create-office-form'), $('#create-office-alert'));
		setSubmitting($('#create-office-submit'), true);

		$.ajax({
			url: storeUrl,
			method: 'POST',
			data: $('#create-office-form').serialize(),
			headers: {
				'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
				'Accept': 'application/json',
				'X-Requested-With': 'XMLHttpRequest'
			},
			success: function(response) {
				setSubmitting($('#create-office-submit'), false);
				hideModal($('#createOfficeModal'));
				if (response && response.office) {
					upsertOfficeRow(response.office);
				}
				showFlashMessage((response && response.message) ? response.message : 'Branch Added Successfully', 'success');
			},
			error: function(xhr) {
				setSubmitting($('#create-office-submit'), false);
				if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
					showFieldErrors($('#create-office-form'), $('#create-office-alert'), xhr.responseJSON.errors);
					return;
				}
				var message = (xhr.responseJSON && xhr.responseJSON.message)
					? xhr.responseJSON.message
					: 'Unable to create branch. Please try again.';
				$('#create-office-alert').removeClass('d-none').text(message);
			}
		});
	});

	$(document).on('click', '.edit-office-btn', function(e) {
		e.preventDefault();
		var $row = $(this).closest('tr[data-office-id]');
		if (!$row.length) {
			return;
		}

		var encodedId = $row.attr('data-office-encoded-id');
		if (!encodedId) {
			return;
		}

		clearFormErrors($('#edit-office-form'), $('#edit-office-alert'));
		setSubmitting($('#edit-office-submit'), true);

		$.ajax({
			url: editUrlTemplate.replace('__ID__', encodedId),
			method: 'GET',
			headers: {
				'Accept': 'application/json',
				'X-Requested-With': 'XMLHttpRequest'
			},
			success: function(response) {
				setSubmitting($('#edit-office-submit'), false);
				if (!response || !response.office) {
					showFlashMessage('Unable to load branch.', 'error');
					return;
				}

				populateEditForm(response.office);
				resetOfficeAccordion('edit');

				var editModalEl = document.getElementById('editOfficeModal');
				var editModal = bootstrap.Modal.getInstance(editModalEl);
				if (!editModal) {
					editModal = new bootstrap.Modal(editModalEl);
				}
				editModal.show();
			},
			error: function(xhr) {
				setSubmitting($('#edit-office-submit'), false);
				var message = (xhr.responseJSON && xhr.responseJSON.message)
					? xhr.responseJSON.message
					: 'Unable to load branch. Please try again.';
				showFlashMessage(message, 'error');
			}
		});
	});

	$('#edit-office-form').on('submit', function(e) {
		e.preventDefault();
		clearFormErrors($('#edit-office-form'), $('#edit-office-alert'));

		var officeId = $('#edit_office_id').val();
		if (!officeId) {
			return;
		}

		setSubmitting($('#edit-office-submit'), true);

		$.ajax({
			url: updateUrlTemplate.replace('__ID__', officeId),
			method: 'POST',
			data: $('#edit-office-form').serialize(),
			headers: {
				'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
				'Accept': 'application/json',
				'X-Requested-With': 'XMLHttpRequest'
			},
			success: function(response) {
				setSubmitting($('#edit-office-submit'), false);
				hideModal($('#editOfficeModal'));
				if (response && response.office) {
					upsertOfficeRow(response.office);
				}
				showFlashMessage((response && response.message) ? response.message : 'Branch Updated Successfully', 'success');
			},
			error: function(xhr) {
				setSubmitting($('#edit-office-submit'), false);
				if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
					showFieldErrors($('#edit-office-form'), $('#edit-office-alert'), xhr.responseJSON.errors);
					return;
				}
				var message = (xhr.responseJSON && xhr.responseJSON.message)
					? xhr.responseJSON.message
					: 'Unable to update branch. Please try again.';
				$('#edit-office-alert').removeClass('d-none').text(message);
			}
		});
	});

	$(document).on('click', '.delete-office-btn', function(e) {
		e.preventDefault();
		var $row = $(this).closest('tr[data-office-id]');
		if (!$row.length) {
			return;
		}

		pendingDeleteOfficeId = $row.attr('data-office-id');
		$('#delete-office-name').text($row.attr('data-office-name') || 'this branch');
		$('#delete-office-error').addClass('d-none').text('');
		setSubmitting($('#confirm-delete-office-btn'), false);

		var deleteModalEl = document.getElementById('deleteOfficeModal');
		var deleteModal = bootstrap.Modal.getInstance(deleteModalEl);
		if (!deleteModal) {
			deleteModal = new bootstrap.Modal(deleteModalEl);
		}
		deleteModal.show();
	});

	$('#deleteOfficeModal').on('hidden.bs.modal', function() {
		pendingDeleteOfficeId = null;
		setSubmitting($('#confirm-delete-office-btn'), false);
		$('#delete-office-error').addClass('d-none').text('');
	});

	$('#confirm-delete-office-btn').on('click', function() {
		if (!pendingDeleteOfficeId) {
			return;
		}

		setSubmitting($('#confirm-delete-office-btn'), true);
		$('#delete-office-error').addClass('d-none').text('');

		$.ajax({
			url: deleteUrl,
			method: 'POST',
			data: {
				id: pendingDeleteOfficeId,
				table: 'branches'
			},
			headers: {
				'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
			},
			success: function(resp) {
				var obj = typeof resp === 'object' ? resp : $.parseJSON(resp);
				if (obj.status == 1) {
					$('#id_' + pendingDeleteOfficeId).remove();
					ensureEmptyStateIfNeeded();
					hideModal($('#deleteOfficeModal'));
					showFlashMessage(obj.message || 'Record has been deleted successfully.', 'success');
					pendingDeleteOfficeId = null;
					return;
				}

				$('#delete-office-error').removeClass('d-none').text(obj.message || 'Unable to delete branch.');
				setSubmitting($('#confirm-delete-office-btn'), false);
			},
			error: function() {
				$('#delete-office-error').removeClass('d-none').text('Unable to delete branch. Please try again.');
				setSubmitting($('#confirm-delete-office-btn'), false);
			}
		});
	});
});
</script>
@endpush
