@extends('layouts.crm_client_detail')
@section('title', 'Office Check In')
@section('content')

@php
	$baseUrl = '/office-visits/' . $activeTab;
	$officeVisitQuerySuffix = request()->except('page', 't');
	$officeVisitQuerySuffix = ! empty($officeVisitQuerySuffix) ? '?' . http_build_query($officeVisitQuerySuffix) : '';
@endphp

<style>
.countAction {background: #1f1655;padding: 0px 5px;border-radius: 50%;color: #fff;margin-left: 5px;}
.card .card-body table.table { --bs-table-color: #000 !important; --bs-table-striped-color: #666 !important; --bs-table-active-color: #666 !important; --bs-table-hover-color: #666 !important; }
body, html { overflow-x: hidden !important; max-width: 100% !important; }
.main-content, .section, .section-body, .card, .card-body { overflow-x: hidden !important; max-width: 100% !important; }
.table-responsive.common_table { overflow-x: hidden !important; max-width: 100% !important; width: 100% !important; }
.table.text_wrap { width: 100% !important; max-width: 100% !important; font-size: 0.85em; table-layout: fixed; }
.table.text_wrap th, .table.text_wrap td { padding: 8px 4px !important; white-space: normal !important; word-wrap: break-word !important; overflow: hidden !important; text-overflow: ellipsis !important; vertical-align: middle !important; }
.table.text_wrap th:nth-child(1) { width: 5%; }
.table.text_wrap th:nth-child(2) { width: 10%; }
.table.text_wrap th:nth-child(3) { width: 8%; }
.table.text_wrap th:nth-child(4) { width: 15%; }
.table.text_wrap th:nth-child(5) { width: 10%; }
.table.text_wrap th:nth-child(6) { width: 12%; }
.table.text_wrap th:nth-child(7) { width: 15%; }
.table.text_wrap th:nth-child(8) { width: 10%; }
.table.text_wrap th:nth-child(9) { width: 15%; }
.card .card-body table.table th, .card .card-body table.table td { color: #666 !important; }
.card .card-body table.table thead th { color: #666 !important; font-weight: bold; }
.card .card-body table.table tbody td { color: #666 !important; }
.card .card-body table.table tbody td .badge { color: #666 !important; }
.pagination { justify-content: center; margin-top: 20px; }
.pagination .page-link { color: #007bff; }
.pagination .page-item.active .page-link { background-color: #007bff; border-color: #007bff; }
.dropdown-content { display: none; position: absolute; background-color: #fff; min-width: 160px; overflow: auto; box-shadow: 0px 8px 16px 0px rgba(0, 0, 0, 0.2); z-index: 1; }
.show { display: block; }
.dropdown-content a { color: #666; padding: 12px 16px; text-decoration: none; display: block; }
@media (max-width: 1200px) { .table.text_wrap { font-size: 0.8em; } .table.text_wrap th, .table.text_wrap td { padding: 6px 3px !important; } }
@media (max-width: 768px) { .table.text_wrap { font-size: 0.75em; } .table.text_wrap th, .table.text_wrap td { padding: 4px 2px !important; } .nav-pills { flex-direction: column; gap: 5px; } .nav-pills .nav-item { width: 100%; } .nav-pills .nav-link { text-align: center; width: 100%; } }
.btn { white-space: normal !important; word-wrap: break-word !important; max-width: 100% !important; }
.nav-pills { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 20px; max-width: 100%; }
.nav-pills .nav-link { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 200px; }
.card-header-action { max-width: 100%; overflow-x: hidden; }
.card-header-action .btn { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.card .card-body table.table tbody tr td:last-child a:hover, .card .card-body table.table tbody tr td:last-child .btn:hover, .card .card-body table.table tbody tr td.last_td a:hover {
	color: #ffffff !important;
}
</style>

<!-- Main Content -->
<div class="main-content">
	<section class="section" style="margin-top: 56px;">
		<div class="section-body">
			<div class="server-error">
				@include('../Elements/flash-message')
			</div>
			<div class="custom-error-msg">
			</div>
			<div class="row">
				<div class="col-12 col-md-12 col-lg-12">
					<div class="card">
						<div class="card-header">
							<h4 style="color:#666;">In Person</h4>
							<div class="card-header-action">
								<a href="{{ route('front-desk.checkin.index') }}" class="btn btn-primary">Create In Person</a>
							</div>
						</div>
						<div class="card-body">
							<ul class="nav nav-pills" id="checkin_tabs" role="tablist">
								<li class="nav-item">
									<a class="nav-link {{ $activeTab === 'waiting' ? 'active' : '' }}" id="waiting-tab" href="{{ URL::to('/office-visits/waiting') }}{{ $officeVisitQuerySuffix }}">Waiting <span class="countAction">{{ $InPersonCount_waiting_type }}</span></a>
								</li>
								<li class="nav-item">
									<a class="nav-link {{ $activeTab === 'attending' ? 'active' : '' }}" id="attending-tab" href="{{ URL::to('/office-visits/attending') }}{{ $officeVisitQuerySuffix }}">Attending <span class="countAction">{{ $InPersonCount_attending_type }}</span></a>
								</li>
								<li class="nav-item">
									<a class="nav-link {{ $activeTab === 'completed' ? 'active' : '' }}" id="completed-tab" href="{{ URL::to('/office-visits/completed') }}{{ $officeVisitQuerySuffix }}">Completed <span class="countAction">{{ $InPersonCount_completed_type }}</span></a>
								</li>
							</ul>
							<div class="tab-content" id="checkinContent">
								<div class="mydropdown" style="margin-top:10px;">
								  <button style="border: 1px solid #FFFFFF;padding: 10px 10px 10px 10px;" onclick="myFunction()" class="dropbtn">
								  <?php echo isset($_GET['office_name']) ? $_GET['office_name'] : 'All Branches'; ?>
								   <i style="font-size: 10px;" class="fa fa-arrow-down"></i></button>
								  <div id="myDropdown" class="dropdown-content">
								  <a href="{{ URL::to($baseUrl) }}">All Branches</a>
								  <?php $branchs = \App\Models\Branch::all(); foreach($branchs as $branch){ ?>
									<a href="{{ URL::to($baseUrl) }}?office={{ $branch->id }}&office_name={{ urlencode($branch->office_name) }}">{{ $branch->office_name }}</a>
								  <?php } ?>
								  </div>
								</div>
								<div class="tab-pane fade show active" id="active" role="tabpanel" aria-labelledby="active-tab">
									<div class="table-responsive common_table" style="overflow-x: hidden; max-width: 100%;">
										<table class="table text_wrap">
											<thead>
												<tr>
													<th>ID</th>
													<th>Date</th>
													<th>Start</th>
													<th>Contact Name</th>
													<th>Contact Type</th>
													<th>Visit Purpose</th>
													<th>Assignee</th>
													<th>Wait Time</th>
													<th>Action</th>
												</tr>
											</thead>
											<tbody class="tdata checindata">
												@if(@$totalData !== 0)
												@foreach (@$lists as $list)
												<tr did="{{@$list->id}}" id="id_{{@$list->id}}" data-status="{{ (int) $list->status }}">
													<td style="white-space: initial;"><a id="{{@$list->id}}" class="opencheckindetail" href="javascript:;">#{{$list->id}}</a></td>
													<td style="white-space: initial;"><a href="javascript:;">{{date('l',strtotime($list->created_at))}}</a><br>{{date('d/m/Y',strtotime($list->created_at))}}</td>
													<td style="white-space: initial;"><?php if($list->sesion_start != ''){ echo date('h:i A',strtotime($list->sesion_start)); }else{ echo '-'; } ?></td>
													<td style="white-space: initial;">
														@php
															$isWalkIn = ($list->contact_type === 'Walk-in') || empty($list->client_id);
															$ovContact = $isWalkIn ? null : $list->resolveCrmContact();
															$ovName = $ovContact ? trim(($ovContact->first_name ?? '') . ' ' . ($ovContact->last_name ?? '')) : '';
														@endphp
														@if($isWalkIn)
															<span class="text-muted">Walk-in</span>
															@if(!empty($list->walk_in_phone))<br>{{ $list->walk_in_phone }}@endif
															@if(!empty($list->walk_in_email))<br>{{ $list->walk_in_email }}@endif
														@elseif($ovContact)
															<a target="_blank" href="{{ URL::to('/clients/detail/'.base64_encode(convert_uuencode($ovContact->id))) }}">{{ \App\Models\CheckinLog::labelForCrmContact($ovContact) }}</a>
															@if($ovName !== '' && !empty($ovContact->email))
																<br>{{ $ovContact->email }}
															@endif
														@else
															<span class="text-muted">—</span>
														@endif
													</td>
													<td style="white-space: initial;">{{$list->contact_type}}</td>
													<td style="white-space: initial;">{{$list->visit_purpose}}</td>
													<td style="white-space: initial;">
														<?php
														$admin = \App\Models\Staff::find($list->user_id);
														// Staff IDs preserved - use admin->id for staff.view (no mapping table)
														?>
														@if($admin)
															<a href="{{ route('adminconsole.staff.view', $admin->id) }}">{{$admin->first_name}} {{$admin->last_name}}</a><br>{{$admin->email}}
														@else
															<span class="text-muted">Not Assigned</span>
														@endif
													</td>
													<td id="count{{$list->id}}" data-checkintime="{{date('Y-m-d H:i:s',strtotime($list->created_at))}}"><?php if($list->status == 0){ ?><span id="waitcount"> 00h:00m:00s</span><?php }else if($list->status == 2){ echo '<span>'.$list->wait_time.'</span>'; }else if($list->status == 1){ echo '<span>'.($list->wait_time ?? '-').'</span>'; }else{ echo '<span>-</span>'; } ?></td>
													<td style="white-space: initial;">
														<?php
														if ($list->status == 0) {
															// Waiting: also check wait_type
															if ($list->wait_type == 1) { ?>
																<a href="javascript:;" data-id="{{@$list->id}}" data-waitingtype="{{@$list->wait_type}}" class="btn btn-success attendsessionforclient" title="Pls Send">Pls Send</a>
															<?php } else { ?>
																<a href="javascript:;" data-id="{{@$list->id}}" data-waitingtype="{{@$list->wait_type}}" class="btn btn-danger attendsessionforclient">Waiting</a>
															<?php }
														} elseif ($list->status == 2) { ?>
															<span class="badge badge-info" style="font-size: 0.9em;">Attending</span>
														<?php } elseif ($list->status == 1) { ?>
															<span class="badge badge-secondary" style="font-size: 0.9em;">Completed</span>
														<?php } ?>
														<input type="hidden" value="0-6h:0-24m:0-7s" id="lwaitcountdata{{@$list->id}}">
													</td>
												</tr>
												@endforeach
											</tbody>
											@else
											<tbody>
												<tr>
													<td style="text-align:center;" colspan="10">No Record found</td>
												</tr>
											</tbody>
											@endif
										</table>
									</div>
								</div>
							</div>
						</div>
						<div class="card-footer">
							{!! $lists->appends(\Request::except('page'))->render() !!}
						</div>
					</div>
				</div>
			</div>
		</div>
	</section>
</div>

<div class="modal fade clientemail custom_modal" tabindex="-1" role="dialog" aria-labelledby="clientModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="clientModalLabel">Compose Email</h5>
				<button type="button" class="close" data-bs-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
			</div>
			<div class="modal-body">
				<form method="post" autocomplete="off" enctype="multipart/form-data">
					<div class="row">
						<div class="col-12 col-md-6 col-lg-6">
							<div class="form-group">
								<label for="email_from">From <span class="span_req">*</span></label>
								<input type="text" name="email_from" class="form-control" data-valid="required" autocomplete="off" placeholder="Enter From">
								@if ($errors->has('email_from'))<span class="custom-error" role="alert"><strong>{{ @$errors->first('email_from') }}</strong></span>@endif
							</div>
						</div>
						<div class="col-12 col-md-6 col-lg-6">
							<div class="form-group">
								<label for="email_to">To <span class="span_req">*</span></label>
								<input type="text" name="email_to" class="form-control" data-valid="required" autocomplete="off" placeholder="Enter To">
								@if ($errors->has('email_to'))<span class="custom-error" role="alert"><strong>{{ @$errors->first('email_to') }}</strong></span>@endif
							</div>
						</div>
						<div class="col-12 col-md-6 col-lg-6">
							<div class="form-group">
								<label for="subject">Subject <span class="span_req">*</span></label>
								<input type="text" name="subject" class="form-control" data-valid="required" autocomplete="off" placeholder="Enter Subject">
								@if ($errors->has('subject'))<span class="custom-error" role="alert"><strong>{{ @$errors->first('subject') }}</strong></span>@endif
							</div>
						</div>
						<div class="col-12 col-md-12 col-lg-12">
							<div class="form-group">
								<label for="message">Message <span class="span_req">*</span></label>
								<textarea class="summernote-simple" name="message"></textarea>
								@if ($errors->has('message'))<span class="custom-error" role="alert"><strong>{{ @$errors->first('message') }}</strong></span>@endif
							</div>
						</div>
						<div class="col-12 col-md-12 col-lg-12">
							<button type="submit" class="btn btn-primary">Save</button>
							<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
						</div>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>
@endsection
@push('scripts')
<script>
jQuery(document).ready(function($){
	$.ajaxSetup({ headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') } });
	$(document).delegate('.attendsessionforclient', 'click', function(){
		var waitingtype = $(this).attr('data-waitingtype');
		var appliid = $(this).attr('data-id');
		$('.popuploader').show();
		$.ajax({
			url: site_url+'/attend_session',
			type:'POST',
			data:{id: appliid,waitcountdata: $('#waitcountdata').val(),waitingtype: waitingtype},
			success: function(response){
				var obj = $.parseJSON(response);
				if(obj.status){ location.reload(); }else{ alert(obj.message); }
			}
		});
	});
	$(document).delegate('.openassignee', 'click', function(){ $('.assignee').show(); });
	$(document).delegate('.closeassignee', 'click', function(){ $('.assignee').hide(); });
	$(document).delegate('.saveassignee', 'click', function(){
		var appliid = $(this).attr('data-id');
		$('.popuploader').show();
		$.ajax({
			url: site_url+'/office-visits/change_assignee',
			type:'GET',
			data:{id: appliid,assinee: $('#changeassignee').val()},
			success: function(response){
				var obj = $.parseJSON(response);
				if(obj.status){ alert(obj.message); location.reload(); } else { alert(obj.message); }
			}
		});
	});
});
function pretty_time_string(num) { return ( num < 10 ? "0" : "" ) + num; }
$('.checindata tr').each(function(){
	var $row = $(this);
	var status = parseInt($row.attr('data-status'), 10);
	// Completed (status=1): do not run running timer — wait time is total from server
	if (status === 1) return;
	var did = $row.attr('did');
	var time = $row.find('#count'+did).attr('data-checkintime');
	var start = new Date(time);
	setInterval(function() {
		var total_seconds = (new Date - start) / 1000;
		var hours = Math.floor(total_seconds / 3600); total_seconds = total_seconds % 3600;
		var minutes = Math.floor(total_seconds / 60); total_seconds = total_seconds % 60;
		var seconds = Math.floor(total_seconds);
		var currentTimeString = pretty_time_string(hours) + "h:" + pretty_time_string(minutes) + "m:" + pretty_time_string(seconds)+'s';
		$('#count'+did).text(currentTimeString);
		$('#lwaitcountdata'+did).val(currentTimeString);
	}, 1000);
});
function myFunction() { document.getElementById("myDropdown").classList.toggle("show"); }
window.onclick = function(event) {
	if (!event.target.matches('.dropbtn')) {
		var dropdowns = document.getElementsByClassName("dropdown-content");
		for (var i = 0; i < dropdowns.length; i++) {
			if (dropdowns[i].classList.contains('show')) dropdowns[i].classList.remove('show');
		}
	}
};
</script>
@endpush
