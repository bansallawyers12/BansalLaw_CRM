@extends('layouts.crm_client_detail')
@section('title', 'Emails')

@section('content')

<!-- Main Content -->
<div class="main-content adminconsole-features adminconsole-emails-index">
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
							<h4>All Emails</h4>
							<div class="card-header-action">
								<form method="POST" action="{{ route('adminconsole.features.emails.sync-now') }}" class="d-inline">
									@csrf
									<button type="submit" class="btn btn-primary btn-sm">
										<i class="fa-solid fa-rotate"></i> Sync All Inboxes Now
									</button>
								</form>
							</div>
						</div>
						<div class="card-body">
							<div class="table-responsive common_table">
								<table class="table text_wrap">
								<thead>
									<tr>
										<th>Name</th>
										<th>Display Name</th>
										<th>Email Signature</th>
										<th>User Sharing</th>
										<th>Status</th>
										<th>Inbox Sync</th>
										<th>Last Synced</th>
									</tr>
								</thead>
								@if(@$totalData !== 0)
								<tbody class="tdata">
								@foreach (@$lists as $list)
									<tr id="id_{{ md5(@$list->email) }}">
										<td>{{ @$list->email == "" ? config('constants.empty') : Str::limit(@$list->email, '50', '...') }}</td>
										<td>{{ @$list->display_name == "" ? config('constants.empty') : Str::limit(@$list->display_name, '50', '...') }}</td>
										<td>{!! @$list->email_signature == "" ? config('constants.empty') : Str::limit(strip_tags(@$list->email_signature), '80', '...') !!}</td>
										<td>{{ @$list->user_sharing == "" ? config('constants.empty') : Str::limit(@$list->user_sharing, '50', '...') }}</td>
										<td>
										@if($list->status == 1)
											<span class="text-success">Active</span>
										@else
											<span class="text-danger">Inactive</span>
										@endif
										</td>
										<td>
										@if(($list->sync_enabled ?? 1) == 1)
											<span class="text-success">On</span>
										@else
											<span class="text-muted">Off</span>
										@endif
										</td>
										<td>
											{{ @$list->last_synced_at }}
											@if(!empty($list->last_sync_error))
												<br><small class="text-danger" title="{{ $list->last_sync_error }}">Sync error</small>
											@endif
										</td>
									</tr>
								@endforeach
								</tbody>
								@else
								<tbody>
									<tr>
										<td class="text-center" colspan="7">
											No Record found
										</td>
									</tr>
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
