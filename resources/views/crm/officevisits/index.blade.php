@extends('layouts.crm_client_detail')
@section('title', 'Office Check In')
@section('content')

@php
	$baseUrl = '/office-visits/' . $activeTab;
	$officeVisitQuerySuffix = request()->except('page', 't');
	$officeVisitQuerySuffix = ! empty($officeVisitQuerySuffix) ? '?' . http_build_query($officeVisitQuerySuffix) : '';
@endphp

<style>
/* Office visits — Powder Blue & Soft Gold (docs/theme.md); vars from public/css/crm-theme.css */
/* Count badge on inactive pills: navy chip on white */
.office-visits-page .countAction {
	background: var(--navy);
	padding: 0 6px;
	border-radius: 999px;
	color: #fff;
	margin-left: 6px;
	font-size: 0.75em;
	font-weight: 600;
	min-width: 1.35em;
	text-align: center;
	display: inline-block;
	line-height: 1.4;
}
/* Active pill: light chip so counts stay readable on --sidebar-active */
.office-visits-page .nav-pills .nav-link.active .countAction {
	background: rgba(255, 255, 255, 0.22);
	color: #fff;
	border: 1px solid rgba(255, 255, 255, 0.35);
}
.office-visits-page .card .card-body table.table {
	--bs-table-color: var(--text-dark) !important;
	--bs-table-striped-color: var(--text-dark) !important;
	--bs-table-active-color: var(--text-dark) !important;
	--bs-table-hover-color: var(--text-dark) !important;
	--bs-table-hover-bg: #ebf3ff;
}
body, html { overflow-x: hidden !important; max-width: 100% !important; }
.office-visits-page .main-content, .office-visits-page .section, .office-visits-page .section-body, .office-visits-page .card, .office-visits-page .card-body { overflow-x: hidden !important; max-width: 100% !important; }
.office-visits-page .table-responsive.common_table { overflow-x: hidden !important; max-width: 100% !important; width: 100% !important; }
.office-visits-page .table.text_wrap { width: 100% !important; max-width: 100% !important; font-size: 0.85em; table-layout: fixed; }
.office-visits-page .table.text_wrap th, .office-visits-page .table.text_wrap td { padding: 8px 4px !important; white-space: normal !important; word-wrap: break-word !important; overflow: hidden !important; text-overflow: ellipsis !important; vertical-align: middle !important; }
.office-visits-page .table.text_wrap th:nth-child(1) { width: 5%; }
.office-visits-page .table.text_wrap th:nth-child(2) { width: 10%; }
.office-visits-page .table.text_wrap th:nth-child(3) { width: 8%; }
.office-visits-page .table.text_wrap th:nth-child(4) { width: 15%; }
.office-visits-page .table.text_wrap th:nth-child(5) { width: 10%; }
.office-visits-page .table.text_wrap th:nth-child(6) { width: 12%; }
.office-visits-page .table.text_wrap th:nth-child(7) { width: 15%; }
.office-visits-page .table.text_wrap th:nth-child(8) { width: 10%; }
.office-visits-page .table.text_wrap th:nth-child(9) { width: 15%; }
.office-visits-page .card .card-body table.table th, .office-visits-page .card .card-body table.table td { color: var(--text-dark) !important; }
.office-visits-page .card .card-body table.table thead th {
	color: var(--text-muted) !important;
	font-weight: 600 !important;
	font-size: 0.72rem !important;
	letter-spacing: 0.05em !important;
	text-transform: uppercase !important;
	background-color: var(--page-bg) !important;
	border-color: var(--border) !important;
	border-bottom: 1px solid var(--border) !important;
}
body.sidebar-mini .office-visits-page .card .card-body table.table thead th {
	background-color: var(--page-bg) !important;
	color: var(--text-muted) !important;
	border-color: var(--border) !important;
}
.office-visits-page .card .card-body table.table tbody td { color: var(--text-dark) !important; }
body.sidebar-mini .office-visits-page .card .card-body table.table tbody td {
	color: var(--text-dark) !important;
	border-color: var(--border) !important;
}
.office-visits-page .card .card-body table.table tbody tr:nth-child(even) td {
	background-color: rgba(221, 234, 248, 0.35) !important;
}
body.sidebar-mini .office-visits-page .card .card-body table.table tbody tr:hover td {
	background-color: #ebf3ff !important;
	color: var(--text-dark) !important;
}
.office-visits-page .card .card-body table.table .text-muted {
	color: var(--text-muted) !important;
}
.office-visits-page .card .card-body table.table tbody td .badge { color: inherit !important; }
.office-visits-page .card .card-body table.table a:not(.btn) { color: var(--sidebar-active) !important; }
.office-visits-page .card .card-body table.table a:not(.btn):hover { color: var(--navy) !important; }
.office-visits-page .pagination { justify-content: center; margin-top: 20px; }
.office-visits-page .pagination .page-link {
	color: var(--navy) !important;
	border-color: var(--border) !important;
	background: var(--card-bg) !important;
	border-radius: 8px !important;
}
.office-visits-page .pagination .page-link:hover {
	background: var(--sidebar-bg) !important;
	color: var(--navy) !important;
	border-color: var(--border) !important;
}
.office-visits-page .pagination .page-item.active .page-link {
	background-color: var(--navy) !important;
	border-color: var(--navy) !important;
	color: #fff !important;
	font-weight: 600 !important;
}
.office-visits-page .dropdown-content {
	display: none;
	position: absolute;
	background-color: var(--card-bg);
	min-width: 160px;
	overflow: auto;
	box-shadow: 0 4px 16px rgba(30, 61, 96, 0.12);
	border: 1px solid var(--border);
	border-radius: 8px;
	z-index: 20;
}
.office-visits-page .dropdown-content.show { display: block; }
.office-visits-page .dropdown-content a {
	color: var(--text-dark);
	padding: 12px 16px;
	text-decoration: none;
	display: block;
}
.office-visits-page .dropdown-content a:hover { background: var(--sidebar-bg); color: var(--navy); }
.office-visits-page .dropbtn {
	border: 1px solid var(--border) !important;
	background: var(--card-bg);
	color: var(--navy);
	border-radius: 8px;
	font-weight: 600;
	padding: 10px;
}
/* theme.md Status Badges → Active: soft tint + --success text (not solid Bootstrap green) */
.office-visits-page .btn-success {
	background-color: rgba(30, 122, 82, 0.12) !important;
	border: 1px solid rgba(30, 122, 82, 0.32) !important;
	color: var(--success) !important;
	background-image: none !important;
}
.office-visits-page .btn-success:hover,
.office-visits-page .btn-success:focus {
	background-color: rgba(30, 122, 82, 0.2) !important;
	border-color: rgba(30, 122, 82, 0.45) !important;
	color: var(--success) !important;
	filter: none !important;
}
.office-visits-page .btn-danger {
	background-color: var(--danger) !important;
	border-color: var(--danger) !important;
	color: #fff !important;
}
.office-visits-page .btn-danger:hover { filter: brightness(0.95); color: #fff !important; }
/*
 * custom.css forces .card .card-body table … td:last-child a/.btn to legacy blue — override for theme.md
 * Pls Send: theme.md Status → Active (soft green + success text). Waiting: solid danger.
 */
body.sidebar-mini .office-visits-page .card .card-body table.table tbody tr td:last-child > a.btn.btn-success,
.office-visits-page .card .card-body table.table tbody tr td:last-child > a.btn.btn-success {
	background-color: rgba(30, 122, 82, 0.12) !important;
	background-image: none !important;
	border: 1px solid rgba(30, 122, 82, 0.32) !important;
	color: var(--success) !important;
	border-radius: 10px !important;
	text-decoration: none !important;
	min-height: 32px;
	display: inline-flex !important;
	align-items: center;
	justify-content: center;
	padding: 6px 14px !important;
	font-size: 0.875rem !important;
	font-weight: 600 !important;
	--bs-btn-color: var(--success);
	--bs-btn-bg: rgba(30, 122, 82, 0.12);
	--bs-btn-border-color: rgba(30, 122, 82, 0.32);
}
body.sidebar-mini .office-visits-page .card .card-body table.table tbody tr td:last-child > a.btn.btn-success:hover,
body.sidebar-mini .office-visits-page .card .card-body table.table tbody tr td:last-child > a.btn.btn-success:focus,
.office-visits-page .card .card-body table.table tbody tr td:last-child > a.btn.btn-success:hover,
.office-visits-page .card .card-body table.table tbody tr td:last-child > a.btn.btn-success:focus {
	background-color: rgba(30, 122, 82, 0.2) !important;
	border-color: rgba(30, 122, 82, 0.45) !important;
	color: var(--success) !important;
	filter: none !important;
}
body.sidebar-mini .office-visits-page .card .card-body table.table tbody tr td:last-child > a.btn.btn-danger,
.office-visits-page .card .card-body table.table tbody tr td:last-child > a.btn.btn-danger {
	background-color: var(--danger) !important;
	background-image: none !important;
	border: 1px solid var(--danger) !important;
	color: #fff !important;
	border-radius: 8px !important;
	text-decoration: none !important;
	min-height: 32px;
	display: inline-flex !important;
	align-items: center;
	justify-content: center;
	padding: 6px 14px !important;
	font-size: 0.875rem !important;
	font-weight: 600 !important;
}
body.sidebar-mini .office-visits-page .card .card-body table.table tbody tr td:last-child > a.btn.btn-danger:hover,
.office-visits-page .card .card-body table.table tbody tr td:last-child > a.btn.btn-danger:hover {
	background-color: var(--danger) !important;
	border-color: var(--danger) !important;
	color: #fff !important;
	filter: brightness(0.94) !important;
}
.office-visits-page .btn { white-space: normal !important; word-wrap: break-word !important; max-width: 100% !important; }
.office-visits-page .nav-pills { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 20px; max-width: 100%; }
.office-visits-page .nav-pills .nav-item { margin: 0; }
.office-visits-page .nav-pills .nav-link {
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
	max-width: 220px;
	color: var(--navy) !important;
	background-color: var(--card-bg) !important;
	border: 1px solid var(--border) !important;
	border-radius: 10px !important;
	font-weight: 600;
	padding: 10px 18px !important;
	box-shadow: none !important;
	text-decoration: none !important;
}
.office-visits-page .nav-pills .nav-link:hover {
	background-color: var(--sidebar-bg) !important;
	border-color: var(--border) !important;
	color: var(--navy) !important;
}
/* theme.md: active = --sidebar-active; gold accent like sidebar’s 3px edge — here as bottom bar only (no yellow ring) */
.office-visits-page .nav-pills .nav-link.active {
	background-color: var(--sidebar-active) !important;
	background-image: none !important;
	color: #fff !important;
	border: 1px solid var(--sidebar-active) !important;
	box-shadow: inset 0 -3px 0 0 var(--accent-gold) !important;
}
.office-visits-page .nav-pills .nav-link.active:hover {
	background-color: var(--sidebar-active) !important;
	color: #fff !important;
	border-color: var(--sidebar-active) !important;
	box-shadow: inset 0 -3px 0 0 var(--accent-gold) !important;
	filter: brightness(1.03);
}
.office-visits-page .card-header-action { max-width: 100%; overflow-x: hidden; }
.office-visits-page .card-header-action .btn { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
/* Navy card header: primary-style navy btn is invisible — use Gold button per theme.md */
.office-visits-page .card .card-header .card-header-action .btn-gold {
	background-color: var(--accent-gold) !important;
	background-image: none !important;
	border: 1px solid var(--accent-gold) !important;
	color: #fff !important;
	padding: 8px 18px !important;
	border-radius: 8px !important;
	font-weight: 600 !important;
	box-shadow: 0 1px 3px rgba(30, 61, 96, 0.2);
}
.office-visits-page .card .card-header .card-header-action .btn-gold:hover,
.office-visits-page .card .card-header .card-header-action .btn-gold:focus {
	background-color: #b88a26 !important;
	border-color: #b88a26 !important;
	color: #fff !important;
}
.office-visits-page .card .card-body table.table tbody tr td:last-child > a.btn.btn-danger:hover { color: #fff !important; }
.office-visits-page .badge.badge-info {
	background-color: rgba(58, 111, 168, 0.18) !important;
	color: var(--sidebar-active) !important;
	border: 1px solid rgba(58, 111, 168, 0.35);
}
.office-visits-page .badge.badge-secondary {
	background-color: rgba(94, 122, 144, 0.12) !important;
	color: var(--text-muted) !important;
	border: 1px solid var(--border);
}
.office-visits-page .card {
	border: 1px solid var(--border);
	border-radius: 10px;
	box-shadow: 0 1px 4px rgba(30, 61, 96, 0.06);
}
.office-visits-page .card .card-header {
	background: var(--navy) !important;
	color: #fff !important;
	border-bottom: 1px solid var(--border);
}
.office-visits-page .card .card-header h4 { color: #fff !important; margin: 0; }
.office-visits-page .card .card-footer { background: var(--page-bg); border-top: 1px solid var(--border); }
/* Compose modal — class is unique to this view (works if modal is moved under body) */
.modal.clientemail .modal-content { border: 1px solid var(--border); border-radius: 10px; }
.modal.clientemail .modal-header { background: var(--navy); color: #fff; border-bottom: 1px solid var(--border); }
.modal.clientemail .modal-header .modal-title { color: #fff; }
.modal.clientemail .modal-header .close { color: #fff; opacity: 0.9; }
.modal.clientemail .btn-primary {
	background-color: var(--navy) !important;
	border-color: var(--navy) !important;
	color: #fff !important;
}
.modal.clientemail .btn-primary:hover {
	background-color: var(--sidebar-active) !important;
	border-color: var(--sidebar-active) !important;
}
.modal.clientemail .btn-secondary {
	background: var(--card-bg) !important;
	border: 1px solid var(--border) !important;
	color: var(--navy) !important;
}
@media (max-width: 1200px) { .office-visits-page .table.text_wrap { font-size: 0.8em; } .office-visits-page .table.text_wrap th, .office-visits-page .table.text_wrap td { padding: 6px 3px !important; } }
@media (max-width: 768px) { .office-visits-page .table.text_wrap { font-size: 0.75em; } .office-visits-page .table.text_wrap th, .office-visits-page .table.text_wrap td { padding: 4px 2px !important; } .office-visits-page .nav-pills { flex-direction: column; gap: 5px; } .office-visits-page .nav-pills .nav-item { width: 100%; } .office-visits-page .nav-pills .nav-link { text-align: center; width: 100%; } }
</style>

<!-- Main Content -->
<div class="office-visits-page">
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
							<h4>In Person</h4>
							<div class="card-header-action">
								<a href="{{ route('front-desk.checkin.index') }}" class="btn btn-gold">Create In Person</a>
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
								  <button type="button" onclick="myFunction()" class="dropbtn">
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
