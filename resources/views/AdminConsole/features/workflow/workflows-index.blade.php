@extends('layouts.crm_client_detail')
@section('title', 'Workflows')

@section('content')
<div class="main-content adminconsole-features adminconsole-workflow-index adminconsole-workflow-form">
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
							<h4>Workflows</h4>
							<div class="card-header-action">
								<button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createWorkflowModal">
									<i class="fa-solid fa-plus"></i> Add Workflow
								</button>
							</div>
						</div>
						<div class="card-body">
							<div class="table-responsive common_table">
								<table class="table text_wrap">
									<thead>
										<tr>
											<th>Workflow Name</th>
											<th>Linked Matter</th>
											<th>Stages</th>
											<th class="text-nowrap">Actions</th>
										</tr>
									</thead>
									@if($lists->count() > 0)
									<tbody class="tdata" id="workflows-tbody">
									@foreach ($lists as $wf)
										@include('AdminConsole.features.workflow.partials.workflow-row', ['wf' => $wf])
									@endforeach
									</tbody>
									@else
									<tbody id="workflows-tbody">
										<tr id="workflows-empty-row">
											<td colspan="4" class="text-center">No workflows found.</td>
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

{{-- Create Workflow Modal --}}
<div class="modal fade" id="createWorkflowModal" tabindex="-1" aria-labelledby="createWorkflowModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-lg modal-dialog-scrollable">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="createWorkflowModalLabel">Create Workflow</h5>
				<button type="button" class="close" data-bs-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
			</div>
			<form id="create-workflow-form" autocomplete="off" novalidate>
				@csrf
				<div class="modal-body">
					<div id="create-workflow-alert" class="alert alert-danger d-none" role="alert"></div>
					<div class="form-group">
						<label for="create_workflow_name">Workflow Name <span class="span_req">*</span></label>
						<input type="text" id="create_workflow_name" name="name" class="form-control" maxlength="255" placeholder="e.g. PR Visa Workflow" required>
						<span class="custom-error field-error" data-field="name" role="alert"></span>
					</div>
					<div class="form-group mb-0">
						<label for="create_workflow_matter_id">Link to Matter Type (optional)</label>
						<select name="matter_id" id="create_workflow_matter_id" class="form-control">
							<option value="">— None (use as General/custom) —</option>
							@foreach($matters as $m)
							<option value="{{ $m->id }}">{{ $m->title }} ({{ $m->nick_name }})</option>
							@endforeach
						</select>
						<small class="form-text text-muted">When set, new client matters of this type will default to this workflow.</small>
						<span class="custom-error field-error" data-field="matter_id" role="alert"></span>
					</div>
					<div class="alert alert-info mb-0 mt-3" role="alert">
						<i class="fa-solid fa-circle-info"></i>
						The new workflow will be pre-populated with all stages from the <strong>General</strong> workflow.
						Use <strong>Manage Stages</strong> after creation to add, rename, or remove non-protected stages.
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
					<button type="submit" class="btn btn-primary" id="create-workflow-submit">
						<span class="submit-label">Create Workflow</span>
						<span class="submit-spinner spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
					</button>
				</div>
			</form>
		</div>
	</div>
</div>

{{-- Edit Workflow Modal --}}
<div class="modal fade" id="editWorkflowModal" tabindex="-1" aria-labelledby="editWorkflowModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-lg modal-dialog-scrollable">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="editWorkflowModalLabel">Edit Workflow</h5>
				<button type="button" class="close" data-bs-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
			</div>
			<form id="edit-workflow-form" autocomplete="off" novalidate>
				@csrf
				@method('PUT')
				<input type="hidden" id="edit_workflow_id" name="id" value="">
				<div class="modal-body">
					<div id="edit-workflow-alert" class="alert alert-danger d-none" role="alert"></div>
					<div class="form-group">
						<label for="edit_workflow_name">Workflow Name <span class="span_req">*</span></label>
						<input type="text" id="edit_workflow_name" name="name" class="form-control" maxlength="255" required>
						<span class="custom-error field-error" data-field="name" role="alert"></span>
					</div>
					<div class="form-group mb-0">
						<label for="edit_workflow_matter_id">Link to Matter Type (optional)</label>
						<select name="matter_id" id="edit_workflow_matter_id" class="form-control">
							<option value="">— None (use as General/custom) —</option>
							@foreach($matters as $m)
							<option value="{{ $m->id }}">{{ $m->title }} ({{ $m->nick_name }})</option>
							@endforeach
						</select>
						<span class="custom-error field-error" data-field="matter_id" role="alert"></span>
					</div>
				</div>
				<div class="modal-footer">
					<a href="#" id="edit-workflow-stages-link" class="btn btn-outline-primary me-auto"><i class="fa-solid fa-list"></i> Manage Stages</a>
					<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
					<button type="submit" class="btn btn-primary" id="edit-workflow-submit">
						<span class="submit-label">Update Workflow</span>
						<span class="submit-spinner spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
					</button>
				</div>
			</form>
		</div>
	</div>
</div>
@endsection

@push('scripts')
<script>
jQuery(document).ready(function($) {
	var storeUrl = @json(route('adminconsole.features.workflow.storeWorkflow'));
	var updateUrlTemplate = @json(route('adminconsole.features.workflow.updateWorkflow', ['id' => '__ID__']));
	var $tbody = $('#workflows-tbody');

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
		$('#workflows-empty-row').remove();
	}

	function buildWorkflowRow(workflow) {
		var matterLabel = workflow.matter_title || '—';
		var stagesUrl = workflow.stages_url || '#';
		var matterId = workflow.matter_id ? String(workflow.matter_id) : '';
		var encodedId = workflow.encoded_id || '';

		return '<tr id="id_' + workflow.id + '"' +
			' data-workflow-id="' + workflow.id + '"' +
			' data-workflow-name="' + attrEscape(workflow.name) + '"' +
			' data-workflow-matter-id="' + attrEscape(matterId) + '"' +
			' data-workflow-encoded-id="' + attrEscape(encodedId) + '"' +
			' data-workflow-stages-url="' + attrEscape(stagesUrl) + '">' +
			'<td class="workflow-name-cell">' + escapeHtml(workflow.name) + '</td>' +
			'<td class="workflow-matter-cell">' + escapeHtml(matterLabel) + '</td>' +
			'<td class="workflow-stages-count-cell">' + (workflow.stages_count || 0) + '</td>' +
			'<td class="text-nowrap"><div class="workflows-index-actions">' +
			'<a class="btn btn-sm btn-primary" href="' + escapeHtml(stagesUrl) + '"><i class="fa-solid fa-list"></i> Manage Stages</a> ' +
			'<button type="button" class="btn btn-sm btn-secondary edit-workflow-btn"><i class="fa-regular fa-pen-to-square"></i> Edit Workflow</button>' +
			'</div></td></tr>';
	}

	function upsertWorkflowRow(workflow) {
		ensureTableBodyHasDataClass();
		var $existing = $('#id_' + workflow.id);
		var $row = $(buildWorkflowRow(workflow));
		if ($existing.length) {
			$existing.replaceWith($row);
		} else {
			$tbody.append($row);
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

	$('#createWorkflowModal').on('show.bs.modal', function() {
		clearFormErrors($('#create-workflow-form'), $('#create-workflow-alert'));
		$('#create-workflow-form')[0].reset();
	});

	$('#create-workflow-form').on('submit', function(e) {
		e.preventDefault();
		clearFormErrors($('#create-workflow-form'), $('#create-workflow-alert'));
		setSubmitting($('#create-workflow-submit'), true);

		$.ajax({
			url: storeUrl,
			method: 'POST',
			data: $('#create-workflow-form').serialize(),
			headers: {
				'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
				'Accept': 'application/json',
				'X-Requested-With': 'XMLHttpRequest'
			},
			success: function(response) {
				setSubmitting($('#create-workflow-submit'), false);
				hideModal($('#createWorkflowModal'));
				if (response && response.workflow) {
					upsertWorkflowRow(response.workflow);
				}
				showFlashMessage((response && response.message) ? response.message : 'Workflow created successfully.', 'success');
			},
			error: function(xhr) {
				setSubmitting($('#create-workflow-submit'), false);
				if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
					showFieldErrors($('#create-workflow-form'), $('#create-workflow-alert'), xhr.responseJSON.errors);
					return;
				}
				var message = (xhr.responseJSON && xhr.responseJSON.message)
					? xhr.responseJSON.message
					: 'Unable to create workflow. Please try again.';
				$('#create-workflow-alert').removeClass('d-none').text(message);
			}
		});
	});

	$(document).on('click', '.edit-workflow-btn', function(e) {
		e.preventDefault();
		var $row = $(this).closest('tr[data-workflow-id]');
		if (!$row.length) {
			return;
		}

		clearFormErrors($('#edit-workflow-form'), $('#edit-workflow-alert'));
		$('#edit_workflow_id').val($row.attr('data-workflow-id'));
		$('#edit_workflow_name').val($row.attr('data-workflow-name') || '');
		$('#edit_workflow_matter_id').val($row.attr('data-workflow-matter-id') || '');
		$('#edit-workflow-stages-link').attr('href', $row.attr('data-workflow-stages-url') || '#');
		$('#edit-workflow-form').data('encoded-id', $row.attr('data-workflow-encoded-id') || '');

		var editModalEl = document.getElementById('editWorkflowModal');
		var editModal = bootstrap.Modal.getInstance(editModalEl);
		if (!editModal) {
			editModal = new bootstrap.Modal(editModalEl);
		}
		editModal.show();
	});

	$('#edit-workflow-form').on('submit', function(e) {
		e.preventDefault();
		clearFormErrors($('#edit-workflow-form'), $('#edit-workflow-alert'));

		var workflowId = $('#edit_workflow_id').val();
		var encodedId = $('#edit-workflow-form').data('encoded-id');
		if (!workflowId || !encodedId) {
			return;
		}

		setSubmitting($('#edit-workflow-submit'), true);

		$.ajax({
			url: updateUrlTemplate.replace('__ID__', encodedId),
			method: 'POST',
			data: $('#edit-workflow-form').serialize(),
			headers: {
				'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
				'Accept': 'application/json',
				'X-Requested-With': 'XMLHttpRequest'
			},
			success: function(response) {
				setSubmitting($('#edit-workflow-submit'), false);
				hideModal($('#editWorkflowModal'));
				if (response && response.workflow) {
					upsertWorkflowRow(response.workflow);
				}
				showFlashMessage((response && response.message) ? response.message : 'Workflow updated successfully.', 'success');
			},
			error: function(xhr) {
				setSubmitting($('#edit-workflow-submit'), false);
				if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
					showFieldErrors($('#edit-workflow-form'), $('#edit-workflow-alert'), xhr.responseJSON.errors);
					return;
				}
				var message = (xhr.responseJSON && xhr.responseJSON.message)
					? xhr.responseJSON.message
					: 'Unable to update workflow. Please try again.';
				$('#edit-workflow-alert').removeClass('d-none').text(message);
			}
		});
	});
});
</script>
@endpush
