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
								<a href="{{ route('adminconsole.features.emaillabels.create') }}" class="btn btn-primary">Create Email Label</a>
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
								<tbody class="tdata">
								@foreach (@$lists as $list)
									<tr id="id_{{ $list->id }}">
										<td>
											<span class="badge email-label-badge" style="background-color: {{ $list->color }}20; border: 1px solid {{ $list->color }}; color: {{ $list->color }};">
												<i class="{{ $list->icon ?? 'fas fa-tag' }}"></i> {{ $list->name }}
											</span>
										</td>
										<td>{{ $list->name == '' ? config('constants.empty') : Str::limit($list->name, 50, '...') }}</td>
										<td>
											@if($list->type == 'system')
												<span class="badge badge-info">System</span>
											@else
												<span class="badge badge-secondary">Custom</span>
											@endif
										</td>
										<td>{{ $list->user ? $list->user->first_name . ' ' . $list->user->last_name : 'System' }}</td>
										<td>
											@if($list->is_active)
												<span class="badge badge-success">Active</span>
											@else
												<span class="badge badge-danger">Inactive</span>
											@endif
										</td>
										<td>@if($list->updated_at != '') {{ date('Y-m-d H:i', strtotime($list->updated_at)) }} @else - @endif</td>

										<td class="text-nowrap">
											<div class="dropdown d-inline-block">
												<button class="btn btn-primary dropdown-toggle" type="button" id="actionBtn_{{ $list->id }}"
													data-bs-toggle="dropdown"
													data-bs-popper-config='{"strategy":"fixed"}'
													aria-expanded="false"
													aria-haspopup="true">Action</button>
												<ul class="dropdown-menu dropdown-menu-end email-labels-action-menu" aria-labelledby="actionBtn_{{ $list->id }}">
													@if($list->type == 'system')
														<li><span class="dropdown-item-text text-muted small px-3 py-2 d-block"><i class="far fa-edit me-2"></i>System labels cannot be edited</span></li>
														<li><span class="dropdown-item-text text-muted small px-3 py-2 d-block"><i class="fas fa-trash me-2"></i>System labels cannot be deleted</span></li>
													@else
														<li><a class="dropdown-item has-icon" href="{{ route('adminconsole.features.emaillabels.edit', base64_encode(convert_uuencode($list->id))) }}"><i class="far fa-edit"></i> Edit</a></li>
														<li><a class="dropdown-item has-icon" href="javascript:void(0);" onclick="deleteAction({{ (int) $list->id }}, 'email_labels')"><i class="fas fa-trash"></i> Delete</a></li>
													@endif
												</ul>
											</div>
										</td>
									</tr>
								@endforeach
								</tbody>
								@else
								<tbody>
									<tr>
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

@endsection
