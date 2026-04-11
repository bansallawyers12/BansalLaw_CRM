@extends('layouts.crm_client_detail')
@section('title', 'Notifications')

@section('styles')
<style>
    /*
     * All notifications — docs/theme.md (Powder Blue & Soft Gold).
     * body.sidebar-mini prefix beats layout inline .table rules; #crm beats generic .badge.
     */
    body.sidebar-mini #crm-all-notifications.card {
        border: 1px solid var(--border) !important;
        border-radius: 10px !important;
        box-shadow: 0 1px 4px rgba(30, 61, 96, 0.06) !important;
        background: var(--card-bg) !important;
    }

    body.sidebar-mini #crm-all-notifications .card-header {
        background: var(--navy) !important;
        color: #fff !important;
        border-bottom: 1px solid var(--border) !important;
    }

    body.sidebar-mini #crm-all-notifications .card-header h4 {
        color: #fff !important;
    }

    /* Total count — KPI-style pill (theme.md status / labels) */
    body.sidebar-mini #crm-all-notifications .card-header-action .badge,
    body.sidebar-mini #crm-all-notifications .card-header-action .badge.badge-primary,
    body.sidebar-mini #crm-all-notifications .card-header-action .badge.bg-primary {
        background: rgba(255, 255, 255, 0.22) !important;
        color: #fff !important;
        border: 1px solid rgba(255, 255, 255, 0.45) !important;
        font-weight: 700 !important;
        font-size: 0.8125rem !important;
        padding: 0.35em 0.75em !important;
        border-radius: 8px !important;
    }

    /* Table — theme.md Tables */
    body.sidebar-mini #crm-all-notifications .table {
        border-color: var(--border) !important;
        color: var(--text-dark) !important;
    }

    body.sidebar-mini #crm-all-notifications .table thead th {
        background-color: var(--page-bg) !important;
        color: var(--text-muted) !important;
        border-color: var(--border) !important;
        border-bottom: 1px solid var(--border) !important;
        font-weight: 600 !important;
        text-transform: uppercase !important;
        font-size: 0.72rem !important;
        letter-spacing: 0.06em !important;
        padding: 12px 10px !important;
    }

    body.sidebar-mini #crm-all-notifications .table tbody td {
        color: var(--text-dark) !important;
        font-weight: 500 !important;
        border-color: var(--border) !important;
        border-bottom: 1px solid var(--border) !important;
        padding: 12px 10px !important;
        vertical-align: middle !important;
    }

    body.sidebar-mini #crm-all-notifications .table tbody tr:nth-child(even) td {
        background-color: rgba(221, 234, 248, 0.35) !important;
    }

    body.sidebar-mini #crm-all-notifications .table tbody tr:hover td {
        background-color: #ebf3ff !important;
        color: var(--text-dark) !important;
    }

    body.sidebar-mini #crm-all-notifications .table tbody tr.crm-notification-row-unread td {
        background-color: rgba(254, 250, 232, 0.65) !important;
        font-weight: 600 !important;
    }

    body.sidebar-mini #crm-all-notifications .table tbody tr.crm-notification-row-unread td:first-child {
        border-left: 3px solid var(--accent-gold) !important;
    }

    body.sidebar-mini #crm-all-notifications .table tbody tr.crm-notification-row-unread:hover td {
        background-color: rgba(254, 250, 232, 0.85) !important;
    }

    body.sidebar-mini #crm-all-notifications .table a {
        color: var(--sidebar-active) !important;
        font-weight: 600 !important;
    }

    body.sidebar-mini #crm-all-notifications .table a:hover {
        color: var(--navy) !important;
    }

    /* Status dots — theme.md icon/status accents */
    #crm-all-notifications .notification-status-read,
    #crm-all-notifications .notification-status-unread {
        display: inline-block;
        width: 11px;
        height: 11px;
        border-radius: 50%;
        vertical-align: middle;
        box-sizing: border-box;
    }

    #crm-all-notifications .notification-status-read {
        background-color: rgba(94, 122, 144, 0.35) !important;
        border: 2px solid var(--text-muted) !important;
    }

    #crm-all-notifications .notification-status-unread {
        background-color: var(--accent-gold) !important;
        border: 2px solid rgba(200, 153, 42, 0.55) !important;
        box-shadow: 0 0 0 2px rgba(200, 153, 42, 0.2);
    }

    body.sidebar-mini #crm-all-notifications .table td.text-center {
        color: var(--text-muted) !important;
    }

    body.sidebar-mini #crm-all-notifications .table td:last-child {
        color: var(--text-muted) !important;
        font-weight: 500 !important;
        text-align: right !important;
        white-space: nowrap;
    }

    body.sidebar-mini #crm-all-notifications .crm-notifications-empty {
        padding: 40px;
    }

    body.sidebar-mini #crm-all-notifications .crm-notifications-empty i {
        font-size: 48px;
        color: var(--sidebar-bg) !important;
    }

    body.sidebar-mini #crm-all-notifications .crm-notifications-empty h5 {
        color: var(--navy) !important;
        font-weight: 700 !important;
    }

    body.sidebar-mini #crm-all-notifications .crm-notifications-empty .text-muted {
        color: var(--text-muted) !important;
    }

    body.sidebar-mini #crm-all-notifications .card-footer {
        background: var(--page-bg) !important;
        border-top: 1px solid var(--border) !important;
    }

    body.sidebar-mini #crm-all-notifications .card-footer .pagination li a,
    body.sidebar-mini #crm-all-notifications .card-footer .pagination li span {
        color: var(--navy) !important;
        background: var(--card-bg) !important;
        border: 1px solid var(--border) !important;
        border-radius: 8px !important;
    }

    body.sidebar-mini #crm-all-notifications .card-footer .pagination li.active span {
        background: var(--navy) !important;
        border-color: var(--navy) !important;
        color: #fff !important;
        font-weight: 600 !important;
    }

    body.sidebar-mini #crm-all-notifications .card-footer .pagination li a:hover {
        background: var(--sidebar-bg) !important;
        color: var(--navy) !important;
        border-color: var(--border) !important;
    }
</style>
@endsection

@section('content')
<div class="main-content">
	<section class="section">
		<div class="section-body">
			<div class="server-error">
				@include('../Elements/flash-message')
			</div>
			<div class="custom-error-msg">
			</div>
			<div class="row">
				<div class="col-12 col-md-12 col-lg-12">
					<div class="card" id="crm-all-notifications">
						<div class="card-header">
							<h4>Notifications</h4>
							<div class="card-header-action">
								<span class="badge badge-primary">{{ $lists->total() }} Total</span>
							</div>
						</div>
						<div class="card-body">
							@if($lists->count() > 0)
							<div class="table-responsive">
								<table class="table">
									<thead>
										<tr>
										  <th width="50">Status</th>
										  <th>Message</th>
										  <th width="200">Date</th>
										</tr>
									</thead>
									<tbody class="tdata">
										@foreach ($lists as $list)
										<tr id="id_{{@$list->id}}" class="{{ ($list->receiver_status ?? 0) == 0 ? 'crm-notification-row-unread' : '' }}">
											<td class="text-center">
												@if(($list->receiver_status ?? 0) == 1)
													<span class="notification-status-read" data-bs-toggle="tooltip" title="Read" aria-label="Read"></span>
												@else
													<span class="notification-status-unread" data-bs-toggle="tooltip" title="Unread" aria-label="Unread"></span>
												@endif
											</td>
											<td>
												<a href="{{$list->url}}?t={{$list->id}}">
													{{$list->message}}
												</a>
											</td>
											<td>
												{{date('d/m/Y h:i A', strtotime($list->created_at))}}
											</td>
										</tr>
										@endforeach
									</tbody>
								</table>
							</div>
							@else
							<div class="text-center crm-notifications-empty">
								<i class="fas fa-bell" aria-hidden="true"></i>
								<h5 class="mt-3">No Notifications</h5>
								<p class="text-muted">You don't have any notifications yet.</p>
							</div>
							@endif
						</div>
						@if($lists->count() > 0)
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
