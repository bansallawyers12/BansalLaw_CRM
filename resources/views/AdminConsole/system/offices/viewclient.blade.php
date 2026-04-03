@extends('layouts.crm_client_detail')
@section('title', 'Offices')

@section('content')

<!-- Main Content -->
<div class="main-content">
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
							<h4>Offices</h4>
							<div class="card-header-action">
								<a href="{{route('adminconsole.system.offices.index')}}" class="btn btn-primary">Office List</a>
							</div>
						</div>
						<div class="card-body">
							<div class="row">
								<div class="col-md-12">
									<div class="row">
										<div class="col-md-4"></div>
										<div class="col-md-2">
											<h5>Overview</h5>
										</div>
										<div class="col-md-3">
											<h5>TOTAL USERS : {{\App\Models\Staff::where('role', 1)->where('office_id',$fetchedData->id)->count()}}</h5>
										</div>
										<div class="col-md-3">
											<h5>TOTAL CLIENTS : {{\App\Models\Admin::whereIn('type', ['client', 'lead'])->whereHas('clientMatters', fn($q) => $q->where('office_id', $fetchedData->id))->count()}}</h5>
										</div>
									</div>
									
								</div>
							</div>
							
						</div>
					</div>
					<div class="card">
						<div class="card-header">
							<h4>Office Information</h4>
							<div class="card-header-action">
								<a href="{{route('adminconsole.system.offices.edit', base64_encode(convert_uuencode(@$fetchedData->id)))}}" class="btn btn-primary">Edit</a>
							</div>
						</div>
						<div class="card-body">
							<div class="row">
								<div class="col-md-6">
									<h4>{{$fetchedData->office_name}} <span class="btn btn-warning">Active</span></h4>
								</div>
							</div>
							<div class="row">
								<div class="col-md-6">
									<table class="table">
										<tr>
											<td><b>Email:</b></td>
											<td>{{$fetchedData->email}}</td>
										</tr>
										<tr>
											<td><b>Mobile:</b></td>
											<td>{{$fetchedData->mobile}}</td>
										</tr>
										<tr>
											<td><b>Phone:</b></td>
											<td>{{$fetchedData->phone}}</td>
										</tr>
										<tr>
											<td><b>Person to Contact:</b></td>
											<td>{{$fetchedData->contact_person}}</td>
										</tr>
									</table>
								</div>
								<div class="col-md-6">
									<table class="table">
										<tr>
											<td><b>Street:</b></td>
											<td>{{$fetchedData->address}}</td>
										</tr>
										<tr>
											<td><b>City:</b></td>
											<td>{{$fetchedData->city}}</td>
										</tr>
										<tr>
											<td><b>State:</b></td>
											<td>{{$fetchedData->state}}</td>
										</tr>
										<tr>
											<td><b>Zip/Post Code:</b></td>
											<td>{{$fetchedData->zip}}</td>
										</tr>
										<tr>
											<td><b>Country:</b></td>
											<td>{{$fetchedData->country}}</td>
										</tr>
									</table>
								</div>
							</div>
						</div>
					</div>
					<div class="card">
						
						<div class="card-body">
							<ul class="nav nav-pills" id="client_tabs" role="tablist">
								<li class="nav-item">
									<a class="nav-link "  id="clients-tab" href="{{route('adminconsole.system.offices.view', $fetchedData->id)}}" role="tab" >User List</a>
								</li>
								<li class="nav-item">
									<a class="nav-link active"  id="date-tab" href="{{route('adminconsole.system.offices.viewclient', $fetchedData->id)}}" role="tab" >Client List</a>
								</li>
								
							</ul> 
							<div class="tab-content" id="clientContent" style="padding-top:15px;">
								<div class="tab-pane fade show active" id="date" role="tabpanel" aria-labelledby="date-tab">
																		
									<div class="table-responsive"> 
										<table class="table text_wrap table-2">
											<thead>
												<tr>
													<th>Name</th>
													<th>DOB</th>
													<th>Email</th>
													<th>Workflow</th>
													<th>Added By</th>
													<th>Office</th>
													
												</tr> 
											</thead>
											<tbody class="applicationtdata">
											<?php
											$lists = \App\Models\Admin::whereIn('type', ['client', 'lead'])->whereHas('clientMatters', fn($q) => $q->where('office_id', $fetchedData->id))->with(['usertype'])->paginate(10);
											foreach($lists as $alist){
												?>
												<tr id="id_{{$alist->id}}">
													<td><a class="" data-id="{{$alist->id}}" href="{{URL::to('/clients/detail/'.base64_encode(convert_uuencode($alist->id)))}}" style="display:block;">{{$alist->first_name}}</a> </td> 
													<td>{{$alist->dob}}</td>
													<td>{{$alist->email}}</td>
													<td></td>
													
													<td></td>
													
													<td>{{$fetchedData->office_name}}</td> 
													
												</tr>
												<?php
											}
											?>											
												
											</tbody>
											<!--<tbody>
												<tr>
													<td style="text-align:center;" colspan="10">
														No Record found
													</td>
												</tr>
											</tbody>-->
										</table> 
									</div>
									{!! $lists->appends(\Request::except('page'))->render() !!}
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</section>
</div>

@endsection