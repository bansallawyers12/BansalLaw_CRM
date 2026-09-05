@extends('layouts.crm_client_detail')
@section('title', 'Notifications')

@section('styles')
<style>
    /*
     * All notifications — modern feed (docs/theme.md Powder Blue & Soft Gold).
     * Scoped to #crm-all-notifications only.
     */
    body.sidebar-mini #crm-all-notifications.crm-notif-shell {
        border: 1px solid var(--border) !important;
        border-radius: 12px !important;
        box-shadow: 0 1px 4px rgba(30, 61, 96, 0.06) !important;
        background: var(--card-bg) !important;
        overflow: hidden;
    }

    body.sidebar-mini #crm-all-notifications .crm-notif-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 16px 20px !important;
        margin: 0 !important;
        background: linear-gradient(135deg, var(--navy) 0%, var(--sidebar-active) 100%) !important;
        border-bottom: 3px solid var(--accent-gold) !important;
        color: #fff !important;
    }

    body.sidebar-mini #crm-all-notifications .crm-notif-header h4,
    body.sidebar-mini #crm-all-notifications .crm-notif-header .crm-notif-title {
        margin: 0 !important;
        padding: 0 !important;
        color: #fff !important;
        font-size: 1.125rem !important;
        font-weight: 700 !important;
        line-height: 1.3 !important;
        letter-spacing: 0.01em;
    }

    body.sidebar-mini #crm-all-notifications .crm-notif-count,
    body.sidebar-mini #crm-all-notifications #notificationsTotalBadge {
        background: rgba(255, 255, 255, 0.18) !important;
        color: #fff !important;
        border: 1px solid rgba(255, 255, 255, 0.4) !important;
        font-weight: 600 !important;
        font-size: 0.75rem !important;
        padding: 0.3em 0.7em !important;
        border-radius: 999px !important;
        letter-spacing: 0.02em;
        white-space: nowrap;
    }

    #crm-all-notifications .crm-notif-body-wrap {
        padding: 0;
        background: var(--card-bg);
    }

    #crm-all-notifications .crm-notif-list {
        display: flex;
        flex-direction: column;
        margin: 0;
        padding: 6px 0;
        list-style: none;
    }

    #crm-all-notifications .crm-notif-item {
        display: flex;
        align-items: flex-start;
        gap: 14px;
        padding: 14px 20px;
        margin: 0 8px;
        border-radius: 10px;
        text-decoration: none !important;
        color: inherit !important;
        border-left: 3px solid transparent;
        transition: background-color 0.15s ease, border-color 0.15s ease;
        position: relative;
    }

    #crm-all-notifications .crm-notif-item + .crm-notif-item {
        margin-top: 2px;
    }

    #crm-all-notifications .crm-notif-item:hover,
    #crm-all-notifications .crm-notif-item:focus-visible {
        background: #ebf3ff !important;
        outline: none;
    }

    #crm-all-notifications .crm-notif-item--unread {
        background: rgba(254, 250, 232, 0.55);
        border-left-color: var(--accent-gold);
    }

    #crm-all-notifications .crm-notif-item--unread:hover,
    #crm-all-notifications .crm-notif-item--unread:focus-visible {
        background: rgba(254, 250, 232, 0.9) !important;
    }

    #crm-all-notifications .crm-notif-item--reopen {
        background: #fef2f2;
        border-left-color: #dc2626;
    }

    #crm-all-notifications .crm-notif-item--reopen:hover,
    #crm-all-notifications .crm-notif-item--reopen:focus-visible {
        background: #fee2e2 !important;
    }

    #crm-all-notifications .crm-notif-icon {
        flex: 0 0 40px;
        width: 40px;
        height: 40px;
        border-radius: 10px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 0.95rem;
        margin-top: 1px;
    }

    #crm-all-notifications .crm-notif-item--read .crm-notif-icon {
        background: rgba(94, 122, 144, 0.12);
        color: var(--text-muted);
    }

    #crm-all-notifications .crm-notif-item--unread:not(.crm-notif-item--reopen) .crm-notif-icon {
        background: rgba(200, 153, 42, 0.16);
        color: #9a7418;
    }

    #crm-all-notifications .crm-notif-item--reopen .crm-notif-icon {
        background: rgba(220, 38, 38, 0.12);
        color: #b91c1c;
    }

    #crm-all-notifications .crm-notif-body {
        flex: 1 1 auto;
        min-width: 0;
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    body.sidebar-mini #crm-all-notifications .crm-notif-message {
        color: var(--text-dark) !important;
        font-size: 0.9375rem !important;
        font-weight: 500 !important;
        line-height: 1.45 !important;
        word-break: break-word;
    }

    body.sidebar-mini #crm-all-notifications .crm-notif-item--unread .crm-notif-message {
        font-weight: 600 !important;
    }

    body.sidebar-mini #crm-all-notifications .crm-notif-item--reopen .crm-notif-message {
        color: #991b1b !important;
        font-weight: 700 !important;
    }

    #crm-all-notifications .crm-reopen-action-badge {
        display: inline-block;
        margin-right: 8px;
        margin-bottom: 2px;
        padding: 2px 8px;
        font-size: 0.65rem;
        font-weight: 700;
        letter-spacing: 0.03em;
        text-transform: uppercase;
        color: #fff;
        background: #dc2626;
        border-radius: 6px;
        vertical-align: middle;
    }

    body.sidebar-mini #crm-all-notifications .crm-notif-meta {
        color: var(--text-muted) !important;
        font-size: 0.8125rem !important;
        font-weight: 500 !important;
        line-height: 1.3 !important;
    }

    #crm-all-notifications .crm-notif-chevron {
        flex: 0 0 auto;
        color: var(--border);
        font-size: 0.75rem;
        margin-top: 12px;
        opacity: 0.85;
        transition: color 0.15s ease, transform 0.15s ease;
    }

    #crm-all-notifications .crm-notif-item:hover .crm-notif-chevron {
        color: var(--sidebar-active);
        transform: translateX(2px);
    }

    body.sidebar-mini #crm-all-notifications .crm-notifications-empty {
        padding: 56px 24px;
        text-align: center;
    }

    body.sidebar-mini #crm-all-notifications .crm-notifications-empty .crm-notif-empty-icon {
        width: 64px;
        height: 64px;
        margin: 0 auto 16px;
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: var(--page-bg);
        color: var(--sidebar-active);
        font-size: 1.5rem;
    }

    body.sidebar-mini #crm-all-notifications .crm-notifications-empty h5 {
        color: var(--navy) !important;
        font-weight: 700 !important;
        margin-bottom: 6px !important;
    }

    body.sidebar-mini #crm-all-notifications .crm-notifications-empty .text-muted {
        color: var(--text-muted) !important;
        margin: 0 !important;
    }

    #crm-all-notifications .notifications-infinite-loader {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        padding: 16px 8px 8px;
        color: var(--navy, #1e3d60);
        font-size: 0.8125rem;
        font-weight: 600;
    }

    #crm-all-notifications .notifications-infinite-loader[hidden] {
        display: none !important;
    }

    #crm-all-notifications .notifications-infinite-loader__spinner {
        width: 16px;
        height: 16px;
        border: 2px solid var(--border, #c8dcef);
        border-top-color: var(--navy, #1e3d60);
        border-radius: 50%;
        animation: notificationsSpin 0.7s linear infinite;
    }

    @keyframes notificationsSpin {
        to { transform: rotate(360deg); }
    }

    #crm-all-notifications .notifications-scroll-info {
        text-align: center;
        padding: 4px 12px 18px;
        color: var(--text-muted, #5e7a90);
        font-size: 0.75rem;
    }

    #crm-all-notifications .notifications-scroll-sentinel {
        height: 1px;
        width: 100%;
    }

    @media (max-width: 576px) {
        #crm-all-notifications .crm-notif-item {
            margin: 0 4px;
            padding: 12px 12px;
            gap: 12px;
        }

        #crm-all-notifications .crm-notif-icon {
            flex-basis: 36px;
            width: 36px;
            height: 36px;
            border-radius: 9px;
        }

        #crm-all-notifications .crm-notif-chevron {
            display: none;
        }
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
					<div class="card crm-notif-shell" id="crm-all-notifications" data-base-url="{{ route('crm.all-notifications') }}">
						<div class="crm-notif-header">
							<h4 class="crm-notif-title">Notifications</h4>
							<span class="crm-notif-count" id="notificationsTotalBadge">{{ $lists->total() }} Total</span>
						</div>
						<div class="crm-notif-body-wrap">
							@if($lists->count() > 0)
							<div id="notificationsList"
								class="crm-notif-list"
								data-page="{{ $lists->currentPage() }}"
								data-last-page="{{ $lists->lastPage() }}"
								data-total="{{ $lists->total() }}"
								data-loaded="{{ $lists->count() }}"
								data-has-more="{{ $lists->hasMorePages() ? '1' : '0' }}">
								@include('crm.notifications.partials.notification_rows', ['lists' => $lists])
							</div>
							<div id="notificationsInfiniteLoader" class="notifications-infinite-loader" hidden aria-live="polite">
								<span class="notifications-infinite-loader__spinner" aria-hidden="true"></span>
								<span>Loading more…</span>
							</div>
							<div id="notificationsScrollSentinel" class="notifications-scroll-sentinel" aria-hidden="true"></div>
							<div id="notificationsScrollInfo" class="notifications-scroll-info">
								Showing {{ $lists->firstItem() ?: 0 }}–{{ $lists->lastItem() ?: 0 }}
								of {{ $lists->total() }}
							</div>
							@else
							<div class="crm-notifications-empty">
								<div class="crm-notif-empty-icon" aria-hidden="true">
									<i class="fa-regular fa-bell"></i>
								</div>
								<h5>No notifications</h5>
								<p class="text-muted">You’re all caught up.</p>
							</div>
							@endif
						</div>
					</div>
				</div>
			</div>
		</div>
	</section>
</div>

@endsection

@push('scripts')
<script src="{{ asset('js/crm/notifications-infinite.js') }}?v={{ @filemtime(public_path('js/crm/notifications-infinite.js')) ?: time() }}"></script>
@endpush
