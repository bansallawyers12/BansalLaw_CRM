@extends('layouts.crm_client_detail')
@section('title', 'Teams')

@section('content')

<div class="crm-container">
	<div class="main-content adminconsole-features adminconsole-teams-form">
		<section class="section">
			<div class="section-body">
				<div class="server-error">
					@include('../Elements/flash-message')
				</div>
				<div class="custom-error-msg"></div>

				@if(isset($fetchedData))
					<form action="{{ route('adminconsole.system.teams.update', $fetchedData->id) }}" method="POST" name="team-form" autocomplete="off" enctype="multipart/form-data">
						@csrf
						@method('PUT')
				@else
					<form action="{{ route('adminconsole.system.teams.store') }}" method="POST" name="team-form" autocomplete="off" enctype="multipart/form-data">
						@csrf
				@endif
					<div class="row">
						<div class="col-12 col-md-12 col-lg-12">
							<div class="card">
								<div class="card-header">
									<h4>{{ isset($fetchedData) ? 'Edit team' : 'Teams' }}</h4>
									<div class="card-header-action">
										<a href="{{ route('adminconsole.system.teams.index') }}" class="btn btn-outline-primary"><i class="fa fa-arrow-left"></i> Back</a>
									</div>
								</div>
							</div>
						</div>
						<div class="col-3 col-md-3 col-lg-3">
							@include('../Elements/CRM/setting')
						</div>
						<div class="col-9 col-md-9 col-lg-9">
							<div class="card">
								<div class="card-body">
									<div id="teams-accordion">
										<div class="accordion">
											<div class="accordion-header" role="button" data-bs-toggle="collapse" data-bs-target="#teams_primary_info" aria-expanded="true">
												<h4>Primary information</h4>
											</div>
											<div class="accordion-body collapse show" id="teams_primary_info" data-bs-parent="#teams-accordion">
												<div class="row">
													<div class="col-12 col-md-4 col-lg-4">
														<div class="form-group">
															<label for="name">Name <span class="span_req">*</span></label>
															<input type="text" name="name" id="name" value="{{ old('name', isset($fetchedData) ? $fetchedData->name : '') }}"
																class="form-control" data-valid="required" autocomplete="off" placeholder="Enter name">
															@if ($errors->has('name'))
																<span class="custom-error" role="alert">
																	<strong>{{ @$errors->first('name') }}</strong>
																</span>
															@endif
														</div>
													</div>
													<div class="col-12 col-md-4 col-lg-4">
														<div class="form-group">
															<label for="color">Color <span class="span_req">*</span></label>
															<input id="color" data-valid="required" type="color" name="color" value="{{ old('color', isset($fetchedData) ? ($fetchedData->color ?: '#3A6FA8') : '#3A6FA8') }}"
																class="form-control form-control-color" title="Team color">
														</div>
													</div>
												</div>
												<div class="form-group text-end mb-0">
													<button type="submit" class="btn btn-primary"><i class="far fa-save"></i> Save</button>
												</div>
											</div>
										</div>
									</div>
								</div>
							</div>

							<div class="card">
								<div class="card-body">
									<div id="teams-list-accordion">
										<div class="accordion">
											<div class="accordion-header" role="button" data-bs-toggle="collapse" data-bs-target="#teams_list_panel" aria-expanded="true">
												<h4>All teams</h4>
											</div>
											<div class="accordion-body collapse show" id="teams_list_panel" data-bs-parent="#teams-list-accordion">
												<div class="table-responsive common_table">
													<table class="table text_wrap">
														<thead>
															<tr>
																<th>Name</th>
																<th>Color</th>
																<th class="text-nowrap">Action</th>
															</tr>
														</thead>
														@if(@$totalData !== 0)
															<tbody class="tdata">
																@foreach (@$lists as $list)
																	<tr id="id_{{@$list->id}}">
																		<td>{{ @$list->name == "" ? config('constants.empty') : Str::limit(@$list->name, '50', '...') }}</td>
																		<td>
																			<span class="teams-color-swatch" style="--team-color: {{ $list->color ?? '#ccc' }};" title="{{ $list->color ?? '' }}"></span>
																		</td>
																		<td class="text-nowrap">
																			<a class="btn btn-sm btn-primary" href="{{ route('adminconsole.system.teams.edit', $list->id) }}"><i class="far fa-edit"></i> Edit</a>
																		</td>
																	</tr>
																@endforeach
															</tbody>
														@else
															<tbody>
																<tr>
																	<td class="text-center" colspan="3">No records found</td>
																</tr>
															</tbody>
														@endif
													</table>
												</div>
												@if(@$totalData !== 0 && isset($lists) && method_exists($lists, 'hasPages') && $lists->hasPages())
													<div class="card-footer border-0 pt-3">
														{!! $lists->appends(\Request::except('page'))->render() !!}
													</div>
												@endif
											</div>
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>
				</form>
			</div>
		</section>
	</div>
</div>

@endsection
