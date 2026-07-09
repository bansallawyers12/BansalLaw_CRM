@extends('layouts.crm_client_detail')
@section('title', 'Workflow Stages: ' . ($workflow->name ?? ''))

@section('content')
<div class="main-content adminconsole-features adminconsole-workflow-stages adminconsole-workflow-form">
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
							<h4>Workflow Stages: {{ $workflow->name }}</h4>
							<div class="card-header-action">
								<a href="{{ route('adminconsole.features.workflow.index') }}" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> Back to Workflows</a>
								<button type="button" class="btn btn-primary" id="open-create-stage-modal-btn"><i class="fa-solid fa-plus"></i> Add Stage</button>
							</div>
						</div>
						<div class="card-body">
							<p class="small mb-3 text-muted"><strong>Manage stages</strong> for this workflow below. Use <strong>Add Stage</strong> (top right) to add rows. Stages marked <span class="badge badge-secondary">Protected</span> cannot be renamed or removed — <strong>Edit</strong> still opens the stage (read-only); <strong>Delete</strong> is disabled.</p>
							<div class="table-responsive common_table">
								<table class="table text_wrap workflow-stages-table">
									<thead>
										<tr>
											<th>Stage</th>
											<th>Total Matters</th>
											<th class="text-nowrap">Actions</th>
										</tr>
									</thead>
									@if($lists->count() > 0)
									<tbody class="tdata" id="workflow-stages-tbody">
									@foreach ($lists as $list)
										@include('AdminConsole.features.workflow.partials.stage-row', ['list' => $list, 'matterCounts' => $matterCounts])
									@endforeach
									</tbody>
									@else
									<tbody id="workflow-stages-tbody">
										<tr id="workflow-stages-empty-row">
											<td colspan="3" class="text-center">No stages.</td>
										</tr>
									</tbody>
									@endif
								</table>
							</div>
						</div>
						<div class="card-footer">
							{{ $lists->links() }}
						</div>
					</div>
				</div>
			</div>
		</div>
	</section>
</div>

{{-- Create Stage Modal --}}
<div class="modal fade" id="createWorkflowStageModal" tabindex="-1" aria-labelledby="createWorkflowStageModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-lg modal-dialog-scrollable">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="createWorkflowStageModalLabel">Add Workflow Stage</h5>
				<button type="button" class="close" data-bs-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
			</div>
			<form id="create-workflow-stage-form" autocomplete="off" novalidate>
				@csrf
				<input type="hidden" name="workflow_id" value="{{ $workflow->id }}">
				<input type="hidden" name="after_stage_id" id="create_after_stage_id" value="">
				<div class="modal-body">
					<div id="create-workflow-stage-alert" class="alert alert-danger d-none" role="alert"></div>
					<div id="create-stage-insert-notice" class="alert alert-info d-none mb-3" role="alert"></div>
					<div class="workflow_stges">
						<table class="table mb-2">
							<tbody id="create-stage-name-rows">
								<tr class="create-stage-name-row">
									<td>
										<input type="text" name="stage_name[]" placeholder="Stage Name" class="form-control" required maxlength="255">
									</td>
									<td class="text-end" style="width: 48px;"></td>
								</tr>
							</tbody>
						</table>
					</div>
					<button type="button" class="btn btn-info btn-sm add-create-stage-row-btn"><i class="fa-solid fa-plus"></i> Add Another Stage</button>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
					<button type="submit" class="btn btn-primary" id="create-workflow-stage-submit">
						<span class="submit-label">Save Stage(s)</span>
						<span class="submit-spinner spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
					</button>
				</div>
			</form>
		</div>
	</div>
</div>

{{-- Edit Stage Modal --}}
<div class="modal fade" id="editWorkflowStageModal" tabindex="-1" aria-labelledby="editWorkflowStageModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-dialog-scrollable">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="editWorkflowStageModalLabel">Edit Workflow Stage</h5>
				<button type="button" class="close" data-bs-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
			</div>
			<form id="edit-workflow-stage-form" autocomplete="off" novalidate>
				@csrf
				@method('PUT')
				<input type="hidden" id="edit_stage_id" name="id" value="">
				<div class="modal-body">
					<div id="edit-workflow-stage-alert" class="alert alert-danger d-none" role="alert"></div>
					<div id="edit-stage-frozen-notice" class="alert alert-warning d-none" role="alert">
						<strong>Protected stage.</strong> This stage cannot be renamed or deleted.
					</div>
					<div class="form-group mb-0">
						<label for="edit_stage_name">Stage Name <span class="span_req">*</span></label>
						<input type="text" id="edit_stage_name" name="stage_name[]" class="form-control" maxlength="255" required>
						<span class="custom-error field-error" data-field="stage_name.0" role="alert"></span>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-info add-after-from-edit-btn d-none"><i class="fa-solid fa-plus"></i> Add After</button>
					<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
					<button type="submit" class="btn btn-primary" id="edit-workflow-stage-submit">
						<span class="submit-label">Save Stage</span>
						<span class="submit-spinner spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
					</button>
				</div>
			</form>
		</div>
	</div>
</div>

{{-- Delete Stage Confirmation Modal --}}
<div class="modal fade" id="deleteWorkflowStageModal" tabindex="-1" aria-labelledby="deleteWorkflowStageModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered">
		<div class="modal-content workflow-delete-modal">
			<div class="modal-header border-0 pb-0">
				<h5 class="modal-title" id="deleteWorkflowStageModalLabel">Delete Workflow Stage</h5>
				<button type="button" class="close" data-bs-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
			</div>
			<div class="modal-body pt-2">
				<div class="workflow-delete-confirm text-center">
					<div class="workflow-delete-icon mb-3" aria-hidden="true">
						<i class="fa-solid fa-trash-can"></i>
					</div>
					<p class="mb-1">Are you sure you want to delete <strong id="delete-workflow-stage-name"></strong>?</p>
					<p class="text-muted small mb-0">This action cannot be undone.</p>
				</div>
				<div id="delete-workflow-stage-error" class="alert alert-danger d-none mt-3 mb-0" role="alert"></div>
			</div>
			<div class="modal-footer border-0 pt-0">
				<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
				<button type="button" class="btn btn-danger" id="confirm-delete-workflow-stage-btn">
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
	var storeUrl = @json(route('adminconsole.features.workflow.store'));
	var updateUrlTemplate = @json(route('adminconsole.features.workflow.update', ['id' => '__ID__']));
	var deleteUrl = @json(url('/delete_action'));
	var emptyLabel = @json(config('constants.empty', '—'));
	var $tbody = $('#workflow-stages-tbody');
	var pendingDeleteStageId = null;
	var pendingAddAfterStageId = null;
	var pendingAddAfterStageName = null;

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
		$('#workflow-stages-empty-row').remove();
	}

	function ensureEmptyStateIfNeeded() {
		if ($tbody.find('tr[data-stage-id]').length === 0) {
			$tbody.removeClass('tdata').html(
				'<tr id="workflow-stages-empty-row"><td colspan="3" class="text-center">No stages.</td></tr>'
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
			var simpleField = field.split('.')[0];
			$form.find('[name="' + simpleField + '[]"], [name="' + simpleField + '"]').first().addClass('is-invalid');
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

	function stageDisplayName(name) {
		return name ? name : emptyLabel;
	}

	function buildStageRow(stage) {
		var isFrozen = !!stage.is_frozen;
		var displayName = stageDisplayName(stage.name);
		var encodedId = stage.encoded_id || '';
		var protectedBadge = isFrozen
			? ' <span class="badge badge-secondary ms-1 align-middle stage-protected-badge" title="This stage cannot be renamed or deleted">Protected</span>'
			: '';
		var deleteBtn = isFrozen
			? '<button type="button" class="btn btn-sm btn-outline-secondary" disabled title="Protected stages cannot be deleted"><i class="fa-solid fa-trash"></i> Delete</button>'
			: '<button type="button" class="btn btn-sm btn-outline-danger delete-workflow-stage-btn"><i class="fa-solid fa-trash"></i> Delete</button>';

		return '<tr id="id_' + stage.id + '"' +
			' data-stage-id="' + stage.id + '"' +
			' data-stage-encoded-id="' + attrEscape(encodedId) + '"' +
			' data-stage-name="' + attrEscape(stage.name) + '"' +
			' data-stage-frozen="' + (isFrozen ? '1' : '0') + '"' +
			' data-stage-matter-count="' + (stage.matter_count || 0) + '">' +
			'<td class="workflow-stage-name-cell"><span class="stage-name-text">' + escapeHtml(displayName) + '</span>' + protectedBadge + '</td>' +
			'<td class="workflow-stage-matter-count-cell">' + (stage.matter_count || 0) + '</td>' +
			'<td class="workflow-stage-actions-col"><div class="workflow-stage-cell-actions">' +
			'<button type="button" class="btn btn-sm btn-primary edit-workflow-stage-btn" title="' + (isFrozen ? 'View (protected — name cannot be changed)' : 'Edit stage name') + '"><i class="fa-regular fa-pen-to-square"></i> Edit</button> ' +
			'<button type="button" class="btn btn-sm btn-info add-after-workflow-stage-btn" data-after-stage-id="' + stage.id + '" title="Insert a new stage immediately after this one"><i class="fa-solid fa-plus"></i> Add After</button> ' +
			deleteBtn +
			'</div></td></tr>';
	}

	function insertStageRows(stages, afterStageId) {
		if (!stages || !stages.length) {
			return;
		}

		ensureTableBodyHasDataClass();
		var $insertAfter = afterStageId ? $('#id_' + afterStageId) : null;

		$.each(stages, function(_, stage) {
			var $row = $(buildStageRow(stage));
			if ($insertAfter && $insertAfter.length) {
				$insertAfter.after($row);
				$insertAfter = $row;
			} else {
				$tbody.append($row);
				$insertAfter = $row;
			}
		});
	}

	function upsertStageRow(stage) {
		ensureTableBodyHasDataClass();
		var $existing = $('#id_' + stage.id);
		var $row = $(buildStageRow(stage));
		if ($existing.length) {
			$existing.replaceWith($row);
		} else {
			$tbody.append($row);
		}
	}

	function resetCreateStageForm() {
		clearFormErrors($('#create-workflow-stage-form'), $('#create-workflow-stage-alert'));
		$('#create_after_stage_id').val(pendingAddAfterStageId || '');
		$('#create-stage-name-rows').html(
			'<tr class="create-stage-name-row"><td><input type="text" name="stage_name[]" placeholder="Stage Name" class="form-control" required maxlength="255"></td><td class="text-end" style="width: 48px;"></td></tr>'
		);
		if (pendingAddAfterStageId && pendingAddAfterStageName) {
			$('#create-stage-insert-notice').removeClass('d-none').html(
				'<strong>Insert position:</strong> new stage(s) will be placed <strong>immediately after</strong> <em>' + escapeHtml(pendingAddAfterStageName) + '</em>.'
			);
			$('#createWorkflowStageModalLabel').text('Add Stage After');
		} else {
			$('#create-stage-insert-notice').addClass('d-none').text('');
			$('#createWorkflowStageModalLabel').text('Add Workflow Stage');
		}
	}

	function openCreateStageModal(afterStageId, afterStageName) {
		pendingAddAfterStageId = afterStageId || null;
		pendingAddAfterStageName = afterStageName || null;
		resetCreateStageForm();

		var modalEl = document.getElementById('createWorkflowStageModal');
		var modal = bootstrap.Modal.getInstance(modalEl);
		if (!modal) {
			modal = new bootstrap.Modal(modalEl);
		}
		modal.show();
	}

	$('#open-create-stage-modal-btn').on('click', function() {
		openCreateStageModal(null, null);
	});

	$('#createWorkflowStageModal').on('hidden.bs.modal', function() {
		pendingAddAfterStageId = null;
		pendingAddAfterStageName = null;
	});

	$(document).on('click', '.add-after-workflow-stage-btn', function(e) {
		e.preventDefault();
		var afterId = $(this).attr('data-after-stage-id');
		var afterName = $(this).closest('tr[data-stage-id]').attr('data-stage-name') || '';
		openCreateStageModal(afterId, afterName);
	});

	$('.add-create-stage-row-btn').on('click', function() {
		$('#create-stage-name-rows').append(
			'<tr class="create-stage-name-row"><td><input type="text" name="stage_name[]" placeholder="Stage Name" class="form-control" required maxlength="255"></td>' +
			'<td class="text-end" style="width: 48px;"><a href="javascript:void(0);" class="remove-create-stage-row text-danger" title="Remove"><i class="fa-solid fa-trash"></i></a></td></tr>'
		);
	});

	$(document).on('click', '.remove-create-stage-row', function(e) {
		e.preventDefault();
		if ($('#create-stage-name-rows .create-stage-name-row').length <= 1) {
			return;
		}
		$(this).closest('tr').remove();
	});

	$('#create-workflow-stage-form').on('submit', function(e) {
		e.preventDefault();
		clearFormErrors($('#create-workflow-stage-form'), $('#create-workflow-stage-alert'));
		setSubmitting($('#create-workflow-stage-submit'), true);

		$.ajax({
			url: storeUrl,
			method: 'POST',
			data: $('#create-workflow-stage-form').serialize(),
			headers: {
				'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
				'Accept': 'application/json',
				'X-Requested-With': 'XMLHttpRequest'
			},
			success: function(response) {
				setSubmitting($('#create-workflow-stage-submit'), false);
				hideModal($('#createWorkflowStageModal'));
				if (response && response.stages) {
					insertStageRows(response.stages, response.after_stage_id || pendingAddAfterStageId);
				}
				showFlashMessage((response && response.message) ? response.message : 'Workflow stage(s) added successfully.', 'success');
			},
			error: function(xhr) {
				setSubmitting($('#create-workflow-stage-submit'), false);
				if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
					showFieldErrors($('#create-workflow-stage-form'), $('#create-workflow-stage-alert'), xhr.responseJSON.errors);
					return;
				}
				var message = (xhr.responseJSON && xhr.responseJSON.message)
					? xhr.responseJSON.message
					: 'Unable to add workflow stage. Please try again.';
				$('#create-workflow-stage-alert').removeClass('d-none').text(message);
			}
		});
	});

	$(document).on('click', '.edit-workflow-stage-btn', function(e) {
		e.preventDefault();
		var $row = $(this).closest('tr[data-stage-id]');
		if (!$row.length) {
			return;
		}

		var isFrozen = $row.attr('data-stage-frozen') === '1';
		clearFormErrors($('#edit-workflow-stage-form'), $('#edit-workflow-stage-alert'));
		$('#edit_stage_id').val($row.attr('data-stage-id'));
		$('#edit-workflow-stage-form').data('encoded-id', $row.attr('data-stage-encoded-id') || '');
		$('#edit_stage_name').val($row.attr('data-stage-name') || '').prop('readonly', isFrozen);
		$('#edit-stage-frozen-notice').toggleClass('d-none', !isFrozen);
		$('#edit-workflow-stage-submit').toggleClass('d-none', isFrozen);
		$('.add-after-from-edit-btn').removeClass('d-none').data('after-stage-id', $row.attr('data-stage-id')).data('after-stage-name', $row.attr('data-stage-name') || '');

		var editModalEl = document.getElementById('editWorkflowStageModal');
		var editModal = bootstrap.Modal.getInstance(editModalEl);
		if (!editModal) {
			editModal = new bootstrap.Modal(editModalEl);
		}
		editModal.show();
	});

	$('.add-after-from-edit-btn').on('click', function() {
		var afterId = $(this).data('after-stage-id');
		var afterName = $(this).data('after-stage-name');
		hideModal($('#editWorkflowStageModal'));
		openCreateStageModal(afterId, afterName);
	});

	$('#editWorkflowStageModal').on('hidden.bs.modal', function() {
		$('#edit_stage_name').prop('readonly', false);
		$('#edit-workflow-stage-submit').removeClass('d-none');
	});

	$('#edit-workflow-stage-form').on('submit', function(e) {
		e.preventDefault();
		clearFormErrors($('#edit-workflow-stage-form'), $('#edit-workflow-stage-alert'));

		var stageId = $('#edit_stage_id').val();
		var encodedId = $('#edit-workflow-stage-form').data('encoded-id');
		if (!stageId || !encodedId) {
			return;
		}

		setSubmitting($('#edit-workflow-stage-submit'), true);

		$.ajax({
			url: updateUrlTemplate.replace('__ID__', encodedId),
			method: 'POST',
			data: $('#edit-workflow-stage-form').serialize(),
			headers: {
				'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
				'Accept': 'application/json',
				'X-Requested-With': 'XMLHttpRequest'
			},
			success: function(response) {
				setSubmitting($('#edit-workflow-stage-submit'), false);
				hideModal($('#editWorkflowStageModal'));
				if (response && response.stage) {
					upsertStageRow(response.stage);
				}
				showFlashMessage((response && response.message) ? response.message : 'Workflow stage updated successfully.', 'success');
			},
			error: function(xhr) {
				setSubmitting($('#edit-workflow-stage-submit'), false);
				if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
					showFieldErrors($('#edit-workflow-stage-form'), $('#edit-workflow-stage-alert'), xhr.responseJSON.errors);
					return;
				}
				var message = (xhr.responseJSON && xhr.responseJSON.message)
					? xhr.responseJSON.message
					: 'Unable to update workflow stage. Please try again.';
				$('#edit-workflow-stage-alert').removeClass('d-none').text(message);
			}
		});
	});

	$(document).on('click', '.delete-workflow-stage-btn', function(e) {
		e.preventDefault();
		var $row = $(this).closest('tr[data-stage-id]');
		if (!$row.length) {
			return;
		}

		pendingDeleteStageId = $row.attr('data-stage-id');
		$('#delete-workflow-stage-name').text($row.attr('data-stage-name') || 'this stage');
		$('#delete-workflow-stage-error').addClass('d-none').text('');
		setSubmitting($('#confirm-delete-workflow-stage-btn'), false);

		var deleteModalEl = document.getElementById('deleteWorkflowStageModal');
		var deleteModal = bootstrap.Modal.getInstance(deleteModalEl);
		if (!deleteModal) {
			deleteModal = new bootstrap.Modal(deleteModalEl);
		}
		deleteModal.show();
	});

	$('#deleteWorkflowStageModal').on('hidden.bs.modal', function() {
		pendingDeleteStageId = null;
		setSubmitting($('#confirm-delete-workflow-stage-btn'), false);
		$('#delete-workflow-stage-error').addClass('d-none').text('');
	});

	$('#confirm-delete-workflow-stage-btn').on('click', function() {
		if (!pendingDeleteStageId) {
			return;
		}

		setSubmitting($('#confirm-delete-workflow-stage-btn'), true);
		$('#delete-workflow-stage-error').addClass('d-none').text('');

		$.ajax({
			url: deleteUrl,
			method: 'POST',
			data: {
				id: pendingDeleteStageId,
				table: 'workflow_stages'
			},
			headers: {
				'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
			},
			success: function(resp) {
				var obj = typeof resp === 'object' ? resp : $.parseJSON(resp);
				if (obj.status == 1) {
					$('#id_' + pendingDeleteStageId).remove();
					ensureEmptyStateIfNeeded();
					hideModal($('#deleteWorkflowStageModal'));
					showFlashMessage(obj.message || 'Record has been deleted successfully.', 'success');
					pendingDeleteStageId = null;
					return;
				}

				$('#delete-workflow-stage-error').removeClass('d-none').text(obj.message || 'Unable to delete workflow stage.');
				setSubmitting($('#confirm-delete-workflow-stage-btn'), false);
			},
			error: function() {
				$('#delete-workflow-stage-error').removeClass('d-none').text('Unable to delete workflow stage. Please try again.');
				setSubmitting($('#confirm-delete-workflow-stage-btn'), false);
			}
		});
	});
});
</script>
@endpush
