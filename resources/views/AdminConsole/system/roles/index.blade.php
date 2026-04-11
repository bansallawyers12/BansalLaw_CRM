@extends('layouts.crm_client_detail')
@section('title', 'Roles and Permissions')

@section('content')

<div class="main-content adminconsole-features">
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
							<h4>Roles and permissions</h4>
							<div class="card-header-action">
								<a href="{{route('adminconsole.system.roles.create')}}" class="btn btn-primary"><i class="fa fa-plus"></i> Add</a>
							</div>
						</div>
						<div class="card-body">
							<div class="table-responsive common_table">
								<table class="table text_wrap">
									<thead>
										<tr>
											<th>Name</th>
											<th>Description</th>
											<th>No. of permission</th>
											<th class="text-nowrap">Action</th>
										</tr>
									</thead>
									<tbody class="tdata">
										@if(@$totalData !== 0)
											@foreach (@$lists as $list)
												<?php
												$newarray = json_decode($list->module_access);
												$module_access = (array) $newarray;
												?>
												<tr id="id_{{@$list->id}}">
													<td>{{ @$list->name == "" ? config('constants.empty') : Str::limit(@$list->name, '50', '...') }}</td>
													<td>{{ @$list->description == "" ? config('constants.empty') : Str::limit(@$list->description, '50', '...') }}</td>
													<td>{{ count($module_access) }}</td>
													<td class="text-nowrap">
														<div class="dropdown d-inline-block">
															<button class="btn btn-primary dropdown-toggle" type="button" id="roleAction_{{ $list->id }}"
																data-bs-toggle="dropdown"
																data-bs-popper-config='{"strategy":"fixed"}'
																aria-haspopup="true"
																aria-expanded="false">Action</button>
															<ul class="dropdown-menu dropdown-menu-end" aria-labelledby="roleAction_{{ $list->id }}">
																<li><a class="dropdown-item has-icon" href="{{route('adminconsole.system.roles.edit', base64_encode(convert_uuencode(@$list->id)))}}"><i class="far fa-edit"></i> Edit</a></li>
															</ul>
														</div>
													</td>
												</tr>
											@endforeach
										@else
											<tr>
												<td class="text-center" colspan="4">No records found</td>
											</tr>
										@endif
									</tbody>
								</table>
							</div>
						</div>
						@if(@$totalData !== 0)
							<div class="card-footer">
								{!! $lists->appends(\Request::except('page'))->render() !!}
							</div>
						@endif
					</div>
				</div>
			</div>
		</div>
	</section>
</div>

@endsection
