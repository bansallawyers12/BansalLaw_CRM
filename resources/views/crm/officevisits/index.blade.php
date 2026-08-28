@extends('layouts.crm_client_detail')
@include('components.require-tinymce')
@section('title', 'Office Check In')
@section('content')

@php
	$tabCounts = $tabCounts ?? [
		'waiting' => $InPersonCount_waiting_type ?? 0,
		'attending' => $InPersonCount_attending_type ?? 0,
		'completed' => $InPersonCount_completed_type ?? 0,
	];
	$officeId = $officeId ?? null;
	$selectedOfficeName = $selectedOfficeName ?? 'All Branches';
@endphp

<style>
/* In Person — SPA page only (docs/theme.md) */
.office-visits-page {
	overflow-x: hidden;
	max-width: 100%;
}
.office-visits-page .ov-shell > .card {
	border: 1px solid var(--border);
	border-radius: 12px;
	box-shadow: 0 2px 10px rgba(30, 61, 96, 0.06);
	overflow: hidden;
}
.office-visits-page .ov-shell > .card > .card-header {
	background: var(--card-bg) !important;
	border-bottom: 1px solid var(--border) !important;
	padding: 1rem 1.25rem !important;
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 12px;
	flex-wrap: wrap;
}
.office-visits-page .ov-shell > .card > .card-header h4 {
	color: var(--navy) !important;
	font-weight: 700;
	font-size: 1.15rem;
	margin: 0;
}
.office-visits-page .ov-shell > .card > .card-body {
	padding: 1.25rem !important;
	background: var(--card-bg) !important;
}
.office-visits-page .ov-create-btn {
	background: var(--navy) !important;
	border: 1px solid var(--navy) !important;
	color: #fff !important;
	border-radius: 8px !important;
	font-weight: 600;
	padding: 0.45rem 1rem;
	text-decoration: none !important;
}
.office-visits-page .ov-create-btn:hover {
	background: var(--sidebar-active) !important;
	border-color: var(--sidebar-active) !important;
	color: #fff !important;
}

/* KPI strip */
.office-visits-page .ov-kpi-row {
	display: grid;
	grid-template-columns: repeat(3, minmax(0, 1fr));
	gap: 12px;
	margin-bottom: 1rem;
}
@media (max-width: 767px) {
	.office-visits-page .ov-kpi-row { grid-template-columns: 1fr; }
}
.office-visits-page .ov-kpi {
	display: flex;
	align-items: center;
	gap: 12px;
	padding: 12px 14px;
	border: 1px solid var(--border);
	border-radius: 10px;
	background: var(--card-bg);
	cursor: pointer;
	text-align: left;
	width: 100%;
	transition: border-color 0.15s ease, background-color 0.15s ease;
}
.office-visits-page .ov-kpi:hover {
	border-color: var(--sidebar-active);
	background: var(--page-bg);
}
.office-visits-page .ov-kpi.is-active {
	border-color: var(--sidebar-active);
	background: rgba(58, 111, 168, 0.08);
	box-shadow: inset 0 -3px 0 0 var(--accent-gold);
}
.office-visits-page .ov-kpi__icon {
	width: 36px;
	height: 36px;
	border-radius: 9px;
	display: flex;
	align-items: center;
	justify-content: center;
	font-size: 14px;
	flex-shrink: 0;
}
.office-visits-page .ov-kpi__icon--wait { background: rgba(168, 48, 32, 0.12); color: var(--danger); }
.office-visits-page .ov-kpi__icon--attend { background: rgba(58, 111, 168, 0.12); color: var(--sidebar-active); }
.office-visits-page .ov-kpi__icon--done { background: rgba(30, 122, 82, 0.12); color: var(--success); }
.office-visits-page .ov-kpi__label {
	font-size: 12px;
	font-weight: 600;
	color: var(--text-muted);
	display: block;
}
.office-visits-page .ov-kpi__value {
	font-size: 1.35rem;
	font-weight: 700;
	color: var(--navy);
	line-height: 1.2;
}

/* Toolbar */
.office-visits-page .ov-toolbar {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 12px;
	flex-wrap: wrap;
	margin-bottom: 1rem;
}
.office-visits-page .ov-tabs {
	display: inline-flex;
	gap: 6px;
	padding: 4px;
	background: var(--page-bg);
	border: 1px solid var(--border);
	border-radius: 10px;
}
.office-visits-page .ov-tab {
	appearance: none;
	border: 0;
	background: transparent;
	color: var(--navy);
	font-weight: 600;
	font-size: 0.875rem;
	padding: 8px 14px;
	border-radius: 8px;
	cursor: pointer;
	display: inline-flex;
	align-items: center;
	gap: 6px;
}
.office-visits-page .ov-tab:hover {
	background: var(--sidebar-bg);
}
.office-visits-page .ov-tab.is-active {
	background: var(--sidebar-active);
	color: #fff;
}
.office-visits-page .ov-tab__count {
	font-size: 0.72rem;
	font-weight: 700;
	min-width: 1.4em;
	padding: 2px 6px;
	border-radius: 999px;
	background: rgba(30, 61, 96, 0.1);
	color: inherit;
}
.office-visits-page .ov-tab.is-active .ov-tab__count {
	background: rgba(255, 255, 255, 0.22);
}
.office-visits-page .ov-branch-select {
	min-width: 180px;
	max-width: 240px;
	min-height: 38px;
	border-radius: 8px;
	border: 1px solid var(--border);
	color: var(--text-dark);
	background: var(--card-bg);
	font-size: 0.875rem;
	font-weight: 600;
	padding: 0.35rem 2rem 0.35rem 0.75rem;
}

/* SPA panel */
.office-visits-page .ov-spa-panel {
	position: relative;
	min-height: 120px;
}
.office-visits-page .ov-spa-panel.is-loading {
	opacity: 0.55;
	pointer-events: none;
}
.office-visits-page .ov-spa-loader {
	display: none;
	position: absolute;
	inset: 0;
	align-items: center;
	justify-content: center;
	z-index: 2;
	background: rgba(255, 255, 255, 0.65);
	border-radius: 10px;
}
.office-visits-page .ov-spa-panel.is-loading .ov-spa-loader {
	display: flex;
}
.office-visits-page .ov-spa-loader__text {
	font-size: 13px;
	font-weight: 600;
	color: var(--text-muted);
}

/* Table */
.office-visits-page .ov-table-panel {
	border: 1px solid var(--border);
	border-radius: 10px;
	overflow: hidden;
	background: var(--card-bg);
}
.office-visits-page .ov-table-wrap { overflow-x: auto; }
.office-visits-page .ov-table {
	width: 100%;
	margin: 0;
	font-size: 0.875rem;
	--bs-table-hover-bg: #ebf3ff;
}
.office-visits-page .ov-table thead th {
	background: var(--page-bg) !important;
	color: var(--text-muted) !important;
	font-size: 0.72rem;
	font-weight: 600;
	letter-spacing: 0.05em;
	text-transform: uppercase;
	border-color: var(--border) !important;
	padding: 10px 12px !important;
	white-space: nowrap;
}
.office-visits-page .ov-table tbody td {
	color: var(--text-dark) !important;
	border-color: var(--border) !important;
	padding: 10px 12px !important;
	vertical-align: middle;
}
.office-visits-page .ov-table tbody tr:nth-child(even) td {
	background: rgba(221, 234, 248, 0.35);
}
.office-visits-page .ov-table tbody tr:hover td {
	background: #ebf3ff !important;
}
.office-visits-page .ov-id-link {
	font-weight: 700;
	color: var(--sidebar-active) !important;
	text-decoration: none !important;
}
.office-visits-page .ov-id-link:hover { color: var(--navy) !important; }
.office-visits-page .ov-date-day {
	display: block;
	font-weight: 600;
	color: var(--navy);
	font-size: 0.82rem;
}
.office-visits-page .ov-date-val {
	display: block;
	font-size: 0.8rem;
	color: var(--text-muted);
}
.office-visits-page .ov-contact-name {
	display: block;
	font-weight: 600;
	color: var(--sidebar-active) !important;
	text-decoration: none !important;
}
.office-visits-page .ov-contact-name:hover { color: var(--navy) !important; }
.office-visits-page .ov-contact-sub {
	display: block;
	font-size: 0.78rem;
	color: var(--text-muted);
	margin-top: 2px;
	word-break: break-word;
}
.office-visits-page .ov-wait-timer {
	font-variant-numeric: tabular-nums;
	font-weight: 600;
	color: var(--navy);
}
.office-visits-page .ov-btn-send {
	background: rgba(30, 122, 82, 0.12) !important;
	border: 1px solid rgba(30, 122, 82, 0.32) !important;
	color: var(--success) !important;
	border-radius: 8px !important;
	font-weight: 600 !important;
}
.office-visits-page .ov-btn-send:hover {
	background: rgba(30, 122, 82, 0.2) !important;
	color: var(--success) !important;
}
.office-visits-page .ov-btn-wait {
	background: var(--danger) !important;
	border: 1px solid var(--danger) !important;
	color: #fff !important;
	border-radius: 8px !important;
	font-weight: 600 !important;
}
.office-visits-page .ov-status-pill {
	display: inline-block;
	font-size: 0.78rem;
	font-weight: 700;
	padding: 4px 10px;
	border-radius: 999px;
}
.office-visits-page .ov-status-pill--attending {
	background: rgba(58, 111, 168, 0.15);
	color: var(--sidebar-active);
	border: 1px solid rgba(58, 111, 168, 0.3);
}
.office-visits-page .ov-status-pill--completed {
	background: rgba(94, 122, 144, 0.12);
	color: var(--text-muted);
	border: 1px solid var(--border);
}
.office-visits-page .ov-empty {
	text-align: center;
	padding: 2.5rem 1rem !important;
	color: var(--text-muted);
	font-weight: 500;
}
.office-visits-page .ov-pagination {
	padding: 12px 14px;
	border-top: 1px solid var(--border);
	background: var(--page-bg);
}
.office-visits-page .ov-pagination .pagination {
	justify-content: center;
	margin: 0;
	gap: 4px;
}
.office-visits-page .ov-pagination .page-link {
	color: var(--navy) !important;
	border-color: var(--border) !important;
	background: var(--card-bg) !important;
	border-radius: 8px !important;
}
.office-visits-page .ov-pagination .page-item.active .page-link {
	background: var(--navy) !important;
	border-color: var(--navy) !important;
	color: #fff !important;
}

/* Compose email modal (unchanged scope) */
.modal.clientemail .modal-content { border: 1px solid var(--border); border-radius: 10px; }
.modal.clientemail .modal-header { background: var(--navy); color: #fff; border-bottom: 1px solid var(--border); }
.modal.clientemail .modal-header .modal-title { color: #fff; }
.modal.clientemail .modal-header .close { color: #fff; opacity: 0.9; }
.modal.clientemail .btn-primary { background: var(--navy) !important; border-color: var(--navy) !important; color: #fff !important; }
.modal.clientemail .btn-secondary { background: var(--card-bg) !important; border: 1px solid var(--border) !important; color: var(--navy) !important; }
</style>

<div class="office-visits-page" id="officeVisitsSpa"
	data-active-tab="{{ $activeTab }}"
	data-office-id="{{ $officeId ?? '' }}"
	data-office-name="{{ $selectedOfficeName }}">
<div class="main-content">
	<section class="section" style="margin-top: 56px;">
		<div class="section-body">
			@include('../Elements/flash-message')
			<div class="custom-error-msg"></div>
			<div class="row">
				<div class="col-12">
					<div class="card ov-shell">
						<div class="card-header">
							<h4>In Person</h4>
							<div class="card-header-action">
								<a href="{{ route('front-desk.checkin.index') }}" class="btn ov-create-btn">Create In Person</a>
							</div>
						</div>
						<div class="card-body">
							<div class="ov-kpi-row" role="tablist" aria-label="Visit status">
								<button type="button" class="ov-kpi ov-kpi-trigger {{ $activeTab === 'waiting' ? 'is-active' : '' }}" data-tab="waiting">
									<span class="ov-kpi__icon ov-kpi__icon--wait"><i class="fa-solid fa-hourglass-half"></i></span>
									<span>
										<span class="ov-kpi__label">Waiting</span>
										<span class="ov-kpi__value" id="ovCountWaiting">{{ $tabCounts['waiting'] }}</span>
									</span>
								</button>
								<button type="button" class="ov-kpi ov-kpi-trigger {{ $activeTab === 'attending' ? 'is-active' : '' }}" data-tab="attending">
									<span class="ov-kpi__icon ov-kpi__icon--attend"><i class="fa-solid fa-user-check"></i></span>
									<span>
										<span class="ov-kpi__label">Attending</span>
										<span class="ov-kpi__value" id="ovCountAttending">{{ $tabCounts['attending'] }}</span>
									</span>
								</button>
								<button type="button" class="ov-kpi ov-kpi-trigger {{ $activeTab === 'completed' ? 'is-active' : '' }}" data-tab="completed">
									<span class="ov-kpi__icon ov-kpi__icon--done"><i class="fa-solid fa-circle-check"></i></span>
									<span>
										<span class="ov-kpi__label">Completed</span>
										<span class="ov-kpi__value" id="ovCountCompleted">{{ $tabCounts['completed'] }}</span>
									</span>
								</button>
							</div>

							<div class="ov-toolbar">
								<div class="ov-tabs" role="tablist">
									<button type="button" class="ov-tab ov-tab-trigger {{ $activeTab === 'waiting' ? 'is-active' : '' }}" data-tab="waiting">
										Waiting <span class="ov-tab__count" data-count-key="waiting">{{ $tabCounts['waiting'] }}</span>
									</button>
									<button type="button" class="ov-tab ov-tab-trigger {{ $activeTab === 'attending' ? 'is-active' : '' }}" data-tab="attending">
										Attending <span class="ov-tab__count" data-count-key="attending">{{ $tabCounts['attending'] }}</span>
									</button>
									<button type="button" class="ov-tab ov-tab-trigger {{ $activeTab === 'completed' ? 'is-active' : '' }}" data-tab="completed">
										Completed <span class="ov-tab__count" data-count-key="completed">{{ $tabCounts['completed'] }}</span>
									</button>
								</div>
								<select id="ovBranchSelect" class="ov-branch-select form-select" aria-label="Filter by branch">
									<option value="" data-name="All Branches" {{ empty($officeId) ? 'selected' : '' }}>All Branches</option>
									@foreach($branches ?? [] as $branch)
										<option value="{{ $branch->id }}" data-name="{{ $branch->office_name }}" {{ (string) $officeId === (string) $branch->id ? 'selected' : '' }}>{{ $branch->office_name }}</option>
									@endforeach
								</select>
							</div>

							<div class="ov-spa-panel" id="ovSpaPanel">
								<div class="ov-spa-loader" aria-hidden="true">
									<span class="ov-spa-loader__text">Loading…</span>
								</div>
								<div id="ovSpaContent">
									@include('crm.officevisits._list')
								</div>
							</div>
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
						<div class="col-md-6">
							<div class="form-group">
								<label for="email_from">From <span class="span_req">*</span></label>
								<input type="text" name="email_from" class="form-control" data-valid="required" autocomplete="off" placeholder="Enter From">
							</div>
						</div>
						<div class="col-md-6">
							<div class="form-group">
								<label for="email_to">To <span class="span_req">*</span></label>
								<input type="text" name="email_to" class="form-control" data-valid="required" autocomplete="off" placeholder="Enter To">
							</div>
						</div>
						<div class="col-md-6">
							<div class="form-group">
								<label for="subject">Subject <span class="span_req">*</span></label>
								<input type="text" name="subject" class="form-control" data-valid="required" autocomplete="off" placeholder="Enter Subject">
							</div>
						</div>
						<div class="col-12">
							<div class="form-group">
								<label for="message">Message <span class="span_req">*</span></label>
								<textarea class="tinymce-editor" name="message"></textarea>
							</div>
						</div>
						<div class="col-12">
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
(function () {
	'use strict';

	var TAB_URLS = {
		waiting: @json(url('/office-visits/waiting')),
		attending: @json(url('/office-visits/attending')),
		completed: @json(url('/office-visits/completed'))
	};

	var root = document.getElementById('officeVisitsSpa');
	if (!root) return;

	var panel = document.getElementById('ovSpaPanel');
	var contentEl = document.getElementById('ovSpaContent');
	var branchSelect = document.getElementById('ovBranchSelect');
	var waitTimerIds = {};
	var loadSeq = 0;
	var state = {
		tab: root.getAttribute('data-active-tab') || 'waiting',
		officeId: root.getAttribute('data-office-id') || '',
		officeName: root.getAttribute('data-office-name') || 'All Branches'
	};

	function prettyTime(n) {
		n = Math.max(0, Math.floor(n));
		return (n < 10 ? '0' : '') + n;
	}

	function clearWaitTimers() {
		Object.keys(waitTimerIds).forEach(function (key) {
			clearInterval(waitTimerIds[key]);
		});
		waitTimerIds = {};
	}

	function initWaitTimers() {
		clearWaitTimers();
		if (typeof jQuery === 'undefined') return;
		jQuery('.checindata tr').each(function () {
			var $row = jQuery(this);
			var status = parseInt($row.attr('data-status'), 10);
			if (status === 1) return;
			var did = $row.attr('did');
			var time = $row.find('#count' + did).attr('data-checkintime');
			if (!time) return;
			var start = new Date(time.replace(/-/g, '/'));
			if (isNaN(start.getTime())) return;
			waitTimerIds[did] = setInterval(function () {
				var total = Math.max(0, (new Date() - start) / 1000);
				var hours = Math.floor(total / 3600); total %= 3600;
				var minutes = Math.floor(total / 60);
				var seconds = Math.floor(total % 60);
				var str = prettyTime(hours) + 'h:' + prettyTime(minutes) + 'm:' + prettyTime(seconds) + 's';
				jQuery('#count' + did).find('.ov-wait-timer, #waitcount').first().text(str);
				jQuery('#lwaitcountdata' + did).val(str);
			}, 1000);
		});
	}

	function setActiveTab(tab) {
		state.tab = tab;
		root.setAttribute('data-active-tab', tab);
		document.querySelectorAll('.ov-tab-trigger, .ov-kpi-trigger').forEach(function (el) {
			el.classList.toggle('is-active', el.getAttribute('data-tab') === tab);
		});
	}

	function updateCounts(counts) {
		if (!counts) return;
		var map = {
			waiting: document.getElementById('ovCountWaiting'),
			attending: document.getElementById('ovCountAttending'),
			completed: document.getElementById('ovCountCompleted')
		};
		Object.keys(map).forEach(function (key) {
			if (map[key]) map[key].textContent = counts[key] != null ? counts[key] : '0';
		});
		document.querySelectorAll('[data-count-key]').forEach(function (el) {
			var key = el.getAttribute('data-count-key');
			if (counts[key] != null) el.textContent = counts[key];
		});
	}

	function buildFetchUrl(tab, page) {
		var base = TAB_URLS[tab] || TAB_URLS.waiting;
		var params = new URLSearchParams();
		params.set('spa', '1');
		if (page) params.set('page', String(page));
		if (state.officeId) {
			params.set('office', state.officeId);
			params.set('office_name', state.officeName);
		}
		return base + '?' + params.toString();
	}

	function buildHistoryUrl(tab, page) {
		var base = TAB_URLS[tab] || TAB_URLS.waiting;
		var params = new URLSearchParams(window.location.search);
		params.delete('spa');
		params.delete('page');
		params.delete('t');
		if (page && page > 1) params.set('page', String(page));
		if (state.officeId) {
			params.set('office', state.officeId);
			params.set('office_name', state.officeName);
		} else {
			params.delete('office');
			params.delete('office_name');
		}
		var qs = params.toString();
		return qs ? base + '?' + qs : base;
	}

	function loadTab(tab, page, pushHistory) {
		if (!TAB_URLS[tab]) return;
		var seq = ++loadSeq;
		setActiveTab(tab);
		panel.classList.add('is-loading');

		fetch(buildFetchUrl(tab, page), {
			headers: {
				'X-Requested-With': 'XMLHttpRequest',
				'Accept': 'application/json'
			},
			credentials: 'same-origin'
		})
			.then(function (res) {
				if (!res.ok) throw new Error('Failed to load visits');
				return res.json();
			})
			.then(function (data) {
				if (seq !== loadSeq) return;
				contentEl.innerHTML = data.html || '';
				updateCounts(data.counts);
				initWaitTimers();
				if (pushHistory !== false) {
					window.history.pushState({ ovTab: tab, ovPage: page || 1 }, '', buildHistoryUrl(tab, page));
				}
			})
			.catch(function () {
				if (seq !== loadSeq) return;
				contentEl.innerHTML = '<div class="ov-empty p-4 text-danger">Could not load visits. Please refresh.</div>';
			})
			.finally(function () {
				if (seq === loadSeq) panel.classList.remove('is-loading');
			});
	}

	function refreshCurrentTab() {
		var page = 1;
		var match = window.location.search.match(/[?&]page=(\d+)/);
		if (match) page = parseInt(match[1], 10) || 1;
		loadTab(state.tab, page, false);
	}

	document.querySelectorAll('.ov-tab-trigger, .ov-kpi-trigger').forEach(function (btn) {
		btn.addEventListener('click', function () {
			var tab = btn.getAttribute('data-tab');
			if (!tab || tab === state.tab) return;
			loadTab(tab, 1, true);
		});
	});

	if (branchSelect) {
		branchSelect.addEventListener('change', function () {
			var opt = branchSelect.options[branchSelect.selectedIndex];
			state.officeId = branchSelect.value || '';
			state.officeName = opt ? (opt.getAttribute('data-name') || opt.textContent) : 'All Branches';
			root.setAttribute('data-office-id', state.officeId);
			root.setAttribute('data-office-name', state.officeName);
			loadTab(state.tab, 1, true);
		});
	}

	contentEl.addEventListener('click', function (e) {
		var link = e.target.closest('.ov-pagination a, .pagination a');
		if (!link || !contentEl.contains(link)) return;
		var href = link.getAttribute('href');
		if (!href || href === '#') return;
		e.preventDefault();
		try {
			var url = new URL(href, window.location.origin);
			var page = parseInt(url.searchParams.get('page') || '1', 10) || 1;
			loadTab(state.tab, page, true);
		} catch (err) { /* ignore */ }
	});

	window.addEventListener('popstate', function (ev) {
		syncFromUrl();
		var tab = (ev.state && ev.state.ovTab) ? ev.state.ovTab : inferTabFromPath();
		var page = (ev.state && ev.state.ovPage) ? ev.state.ovPage : (function () {
			try {
				var url = new URL(window.location.href);
				return parseInt(url.searchParams.get('page') || '1', 10) || 1;
			} catch (e) { return 1; }
		})();
		loadTab(tab, page, false);
	});

	function syncFromUrl() {
		try {
			var url = new URL(window.location.href);
			var office = url.searchParams.get('office') || '';
			var officeName = url.searchParams.get('office_name') || 'All Branches';
			state.officeId = office;
			state.officeName = officeName;
			root.setAttribute('data-office-id', office);
			root.setAttribute('data-office-name', officeName);
			if (branchSelect) {
				branchSelect.value = office;
			}
		} catch (e) { /* ignore */ }
	}

	function inferTabFromPath() {
		var path = window.location.pathname;
		if (path.indexOf('/attending') !== -1) return 'attending';
		if (path.indexOf('/completed') !== -1) return 'completed';
		return 'waiting';
	}

	initWaitTimers();

	(function initHistory() {
		var page = 1;
		try {
			var url = new URL(window.location.href);
			page = parseInt(url.searchParams.get('page') || '1', 10) || 1;
		} catch (e) { /* ignore */ }
		window.history.replaceState({ ovTab: state.tab, ovPage: page }, '', window.location.href);
	})();

	if (typeof jQuery !== 'undefined') {
		jQuery(document).ready(function ($) {
			$.ajaxSetup({ headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') } });

			$(document).on('click', '.attendsessionforclient', function () {
				var waitingtype = $(this).attr('data-waitingtype');
				var appliid = $(this).attr('data-id');
				$('.popuploader').show();
				$.ajax({
					url: site_url + '/attend_session',
					type: 'POST',
					data: { id: appliid, waitcountdata: $('#waitcountdata').val(), waitingtype: waitingtype },
					success: function (response) {
						var obj = $.parseJSON(response);
						if (obj.status) {
							refreshCurrentTab();
						} else {
							alert(obj.message);
						}
					},
					complete: function () { $('.popuploader').hide(); }
				});
			});

			$(document).on('click', '.openassignee', function () { $('.assignee').show(); });
			$(document).on('click', '.closeassignee', function () { $('.assignee').hide(); });
			$(document).on('click', '.saveassignee', function () {
				var appliid = $(this).attr('data-id');
				$('.popuploader').show();
				$.ajax({
					url: site_url + '/office-visits/change_assignee',
					type: 'GET',
					data: { id: appliid, assinee: $('#changeassignee').val() },
					success: function (response) {
						var obj = $.parseJSON(response);
						if (obj.status) {
							alert(obj.message);
							refreshCurrentTab();
						} else {
							alert(obj.message);
						}
					},
					complete: function () { $('.popuploader').hide(); }
				});
			});
		});
	}
})();
</script>
@endpush
