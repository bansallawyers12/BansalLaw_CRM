@extends('layouts.crm_client_detail')
@section('title', 'Notifications')

@section('styles')
<style>
    /* All notifications — docs/theme.md (tokens from crm-theme.css :root) */
    #crm-all-notifications.card {
        border: 1px solid var(--border, #c8dcef);
        border-radius: 10px;
        box-shadow: 0 1px 4px rgba(30, 61, 96, 0.06);
    }

    #crm-all-notifications .card-header-action .badge-primary {
        background: rgba(30, 61, 96, 0.1) !important;
        color: var(--navy, #1e3d60) !important;
        border: 1px solid rgba(30, 61, 96, 0.25);
        font-weight: 700;
        font-size: 0.8125rem;
        padding: 0.35em 0.65em;
    }

    #crm-all-notifications .table thead th {
        background: var(--page-bg, #f0f6ff) !important;
        color: var(--navy, #1e3d60) !important;
        border-color: var(--border, #c8dcef) !important;
        font-weight: 600;
        text-transform: uppercase;
        font-size: 0.75rem;
        letter-spacing: 0.04em;
    }

    #crm-all-notifications .table td {
        border-color: var(--border, #c8dcef) !important;
        color: var(--text-dark, #1a2c40);
    }

    #crm-all-notifications .table tbody tr:hover td {
        background-color: #ebf3ff !important;
    }

    #crm-all-notifications .table tbody tr.crm-notification-row-unread td {
        background-color: var(--page-bg, #f0f6ff) !important;
        font-weight: 600;
    }

    #crm-all-notifications .table a {
        color: var(--sidebar-active, #3a6fa8);
        font-weight: 500;
    }

    #crm-all-notifications .table a:hover {
        color: var(--navy, #1e3d60);
    }

    .notification-status-read,
    .notification-status-unread {
        display: inline-block;
        width: 12px;
        height: 12px;
        border-radius: 50%;
        vertical-align: middle;
    }

    .notification-status-read {
        background-color: var(--success, #1e7a52);
    }

    .notification-status-unread {
        background-color: var(--sidebar-active, #3a6fa8);
    }

    #crm-all-notifications .crm-notifications-empty {
        padding: 40px;
    }

    #crm-all-notifications .crm-notifications-empty i {
        font-size: 48px;
        color: var(--border, #c8dcef);
    }

    #crm-all-notifications .crm-notifications-empty h5 {
        color: var(--navy, #1e3d60);
        font-weight: 700;
    }

    #crm-all-notifications .crm-notifications-empty .text-muted {
        color: var(--text-muted, #5e7a90) !important;
    }

    /* Pagination in card footer (listing-pagination.css targets .listing-container) */
    #crm-all-notifications .card-footer .pagination li a,
    #crm-all-notifications .card-footer .pagination li span {
        color: var(--text-muted, #5e7a90);
        background: var(--card-bg, #ffffff);
        border: 1px solid var(--border, #c8dcef);
        border-radius: 6px;
    }

    #crm-all-notifications .card-footer .pagination li.active span {
        background: var(--navy, #1e3d60);
        border-color: var(--navy, #1e3d60);
        color: #fff;
        font-weight: 600;
    }

    #crm-all-notifications .card-footer .pagination li a:hover {
        background: var(--sidebar-hover, #c8dcef);
        color: var(--navy, #1e3d60);
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
