@extends('layouts.crm_client_detail')
@section('title', 'Workflows')

@section('content')
<div class="main-content adminconsole-features adminconsole-workflow-index">
	<section class="section">
		<div class="section-body">
			<div class="server-error">
				@include('../Elements/flash-message')
			</div>
			<div class="row">
				<div class="col-3 col-md-3 col-lg-3">
					@include('../Elements/CRM/setting')
				</div>
				<div class="col-9 col-md-9 col-lg-9">
					<div class="card">
						<div class="card-header">
							<h4>Workflows</h4>
							<div class="card-header-action">
								<a href="{{ route('adminconsole.features.workflow.create') }}" class="btn btn-primary">Add Workflow</a>
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
									<tbody>
									@foreach ($lists as $wf)
									<tr>
										<td>{{ $wf->name }}</td>
										<td>{{ $wf->matter ? $wf->matter->title : '—' }}</td>
										<td>{{ $wf->stages->count() }}</td>
										<td class="text-nowrap">
											<div class="workflows-index-actions">
												<a class="btn btn-sm btn-primary" href="{{ route('adminconsole.features.workflow.stages', base64_encode(convert_uuencode($wf->id))) }}"><i class="fas fa-list"></i> Manage Stages</a>
												<a class="btn btn-sm btn-secondary" href="{{ route('adminconsole.features.workflow.editWorkflow', base64_encode(convert_uuencode($wf->id))) }}"><i class="far fa-edit"></i> Edit Workflow</a>
											</div>
										</td>
									</tr>
									@endforeach
									</tbody>
									@else
									<tbody>
										<tr><td colspan="4" class="text-center">No workflows found. <a href="{{ route('adminconsole.features.workflow.create') }}">Create one</a>.</td></tr>
									</tbody>
									@endif
								</table>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</section>
</div>
@endsection
