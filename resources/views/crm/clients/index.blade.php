@extends('layouts.crm_client_detail')
@include('components.require-tinymce')
@section('title', 'Clients')

@section('styles')
<link rel="stylesheet" href="{{ asset('css/listing-pagination.css') }}">
<link rel="stylesheet" href="{{ asset('css/listing-container.css') }}">
<link rel="stylesheet" href="{{ asset('css/listing-flatpickr.css') }}">
<link rel="stylesheet" href="{{ asset('css/clients-index.css') }}">
<style>
    .clients-listing .filter_panel {
        background: var(--page-bg, #f0f6ff);
        border-radius: 12px;
        padding: 24px;
        margin-bottom: 24px;
        display: none;
        border: 1px solid var(--border, #c8dcef);
    }

    .clients-listing .filter_panel h4 {
        color: var(--navy, #1e3d60);
        font-size: 18px;
        font-weight: 700;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .clients-listing .active-filters-badge {
        background: rgba(30, 122, 82, 0.15);
        color: var(--success, #1e7a52);
        border: 1px solid rgba(30, 122, 82, 0.3);
        border-radius: 999px;
        padding: 4px 12px;
        font-size: 12px;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .clients-listing .form-group label {
        color: var(--text-muted, #5e7a90) !important;
        font-weight: 600;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        margin-bottom: 8px;
    }

    .clients-listing .per-page-select {
        border: 1px solid var(--border, #c8dcef) !important;
        border-radius: 8px !important;
        background: var(--card-bg, #ffffff) !important;
        color: var(--navy, #1e3d60) !important;
        font-weight: 600 !important;
        padding: 8px 12px !important;
        min-width: 90px;
        width: auto !important;
        flex: 0 0 auto;
    }

    .clients-listing .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 5px 12px;
        border-radius: 999px;
        font-weight: 600;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }

    .clients-listing .status-badge.active {
        background: rgba(30, 122, 82, 0.12);
        color: var(--success, #1e7a52);
        border: 1px solid rgba(30, 122, 82, 0.3);
    }

    .clients-listing .status-badge.inactive {
        background: rgba(168, 48, 32, 0.1);
        color: var(--danger, #a83020);
        border: 1px solid rgba(168, 48, 32, 0.25);
    }

    .clients-listing .sortable-header {
        cursor: pointer;
        user-select: none;
        white-space: nowrap;
    }

    .clients-listing .sortable-header a {
        color: inherit;
        text-decoration: none;
    }

    /* SweetAlert2 — clients listing dialogs */
    .clients-swal-popup {
        border-radius: 14px !important;
        border: 1px solid var(--border, #c8dcef) !important;
        box-shadow: 0 16px 48px rgba(30, 61, 96, 0.18) !important;
        padding: 0.5rem 0 1.5rem !important;
    }

    .clients-swal-popup .swal2-title {
        color: var(--navy, #1e3d60) !important;
        font-size: 1.2rem !important;
        font-weight: 700 !important;
    }

    .clients-swal-popup .swal2-html-container {
        color: var(--text-muted, #5e7a90) !important;
        font-size: 0.9375rem !important;
        line-height: 1.5 !important;
    }

    .clients-swal-popup .swal2-actions {
        gap: 10px !important;
    }

    .clients-swal-popup .swal2-styled.swal2-confirm,
    .clients-swal-popup .swal2-styled.swal2-cancel {
        border-radius: 8px !important;
        font-weight: 600 !important;
        padding: 0.55rem 1.25rem !important;
        box-shadow: none !important;
    }

    .clients-swal-popup .swal2-styled.swal2-confirm:focus,
    .clients-swal-popup .swal2-styled.swal2-cancel:focus {
        box-shadow: 0 0 0 3px rgba(58, 111, 168, 0.25) !important;
    }
</style>
@include('crm.clients.partials.enhanced-date-filter-styles')
@endsection

@section('content')
@php
    $trackedFilters = collect([
        'client_id' => request('client_id'),
        'name' => request('name'),
        'email' => request('email'),
        'phone' => request('phone'),
        'type' => request('type'),
        'status' => request('status'),
        'quick_date_range' => request('quick_date_range'),
        'from_date' => request('from_date'),
        'to_date' => request('to_date'),
        'date_filter_field' => request('date_filter_field') !== 'created_at' ? request('date_filter_field') : null,
    ]);
    $activeFilterCount = $trackedFilters->filter(function ($value) {
        return $value !== null && $value !== '';
    })->count();
@endphp
<div id="clients-listing-spa-root"
     class="listing-container clients-listing"
     data-spa-root="1"
     data-infinite-scroll="1"
     data-current-page="{{ $lists->currentPage() }}"
     data-last-page="{{ $lists->lastPage() }}"
     data-per-page="20">
    <section class="listing-section">
        <div class="listing-section-body" id="clients-listing-spa-inner">
            @include('../Elements/flash-message')

            <div class="card">
                <div class="custom-error-msg">
                </div>
                <div class="card-header">
                    <div class="clients-page-header">
                        <div class="clients-page-header__title">
                            <span class="clients-page-header__icon" aria-hidden="true">
                                <i class="fa-solid fa-users"></i>
                            </span>
                            <div>
                                <h4>All Clients</h4>
                                <p class="clients-page-header__subtitle">
                                    {{ number_format($lists->total()) }} {{ Str::plural('client', $lists->total()) }} &middot; Manage and search your client records
                                </p>
                            </div>
                        </div>

                        <div class="card-header-actions">
                            @if(Auth::user() && in_array(Auth::user()->role, [1, 12]))
                            <a href="{{ route('clients.insights', ['section' => 'clients']) }}" class="btn btn-theme btn-theme-sm" title="View Insights">
                                <i class="fa-solid fa-chart-line"></i> Insights
                            </a>
                            @endif
                            <a href="javascript:;" class="btn btn-theme btn-theme-sm filter_btn{{ $activeFilterCount > 0 ? ' filter_btn--active' : '' }}" id="filterToggleBtn">
                                <i class="fa-solid fa-filter"></i> Filter
                                @if($activeFilterCount > 0)
                                    <span class="filter-count-badge">{{ $activeFilterCount }}</span>
                                @endif
                            </a>
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    <div class="clients-toolbar">
                        <ul class="clients-tabs nav" id="client_tabs" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active" id="clients-tab" href="{{ URL::to('/clients') }}">Clients</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="archived-tab" href="{{ URL::to('/archived') }}">Archived</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="lead-tab" href="{{ URL::to('/leads') }}">Leads</a>
                            </li>
                        </ul>
                        <span class="clients-select-help"><i class="fa-regular fa-square-check"></i> Select clients with the checkboxes on the left</span>
                    </div>

                    <div class="clients-bulk-bar" id="clientsBulkBar" aria-live="polite">
                        <span class="clients-bulk-bar__count">
                            <i class="fa-solid fa-check-double"></i>
                            <span id="selectedCount">0</span> selected
                        </span>
                        <div class="clients-bulk-bar__actions">
                            <a class="btn btn-primary btn-sm is_checked_client emailmodal" href="javascript:;" style="display:none;">
                                <i class="fa-regular fa-envelope"></i> Send Mail
                            </a>
                            <a class="btn btn-primary btn-sm is_checked_client" href="javascript:;" style="display:none;">
                                <i class="fa-solid fa-user-pen"></i> Change Assignee
                            </a>
                            <a class="btn btn-primary btn-sm is_checked_client_merge" href="javascript:;" style="display:none;">
                                <i class="fa-solid fa-code-merge"></i> Merge
                            </a>
                        </div>
                    </div>

                    <div class="filter_panel">
                        <div class="d-flex justify-content-between align-items-center flex-wrap">
                            <h4>
                                Search By Details
                                @if($activeFilterCount > 0)
                                    <span class="active-filters-badge">
                                        <i class="fa-solid fa-filter"></i> {{ $activeFilterCount }} Active
                                    </span>
                                @endif
                            </h4>
                            @if($activeFilterCount > 0)
                                <button type="button" class="clear-filter-btn" id="clearFilters">
                                    <i class="fa-solid fa-undo"></i> Clear Filters
                                </button>
                            @endif
                        </div>
                        <form action="{{URL::to('/clients')}}" method="get" id="filterForm">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="client_id">Client ID</label>
                                        <input type="text" name="client_id" value="{{ request('client_id') }}" class="form-control" autocomplete="off" placeholder="Client ID" id="client_id">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="name">Name</label>
                                        <input type="text" name="name" value="{{ request('name') }}" class="form-control agent_company_name" autocomplete="off" placeholder="Name" id="name">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="email">Email</label>
                                        <input type="text" name="email" value="{{ request('email') }}" class="form-control" autocomplete="off" placeholder="Email" id="email">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="phone">Phone</label>
                                        <input type="text" name="phone" value="{{ request('phone') }}" class="form-control" autocomplete="off" placeholder="Phone" id="phone">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="type">Record Type</label>
                                        <select class="form-control" name="type" id="type">
                                            <option value="">All Types</option>
                                            <option value="client" {{ request('type') == 'client' ? 'selected' : '' }}>Client</option>
                                            <option value="lead" {{ request('type') == 'lead' ? 'selected' : '' }}>Lead</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="status">Status</label>
                                        <select class="form-control" name="status" id="status">
                                            <option value="">Any</option>
                                            <option value="1" {{ request('status') === '1' ? 'selected' : '' }}>Active</option>
                                            <option value="0" {{ request('status') === '0' ? 'selected' : '' }}>Inactive</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="date-filter-section mt-3">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="date_filter_field"><i class="fa-solid fa-calendar-days"></i> Date Field</label>
                                            <select name="date_filter_field" id="date_filter_field" class="form-control">
                                                <option value="created_at" {{ request('date_filter_field', 'created_at') === 'created_at' ? 'selected' : '' }}>Created Date</option>
                                                <option value="updated_at" {{ request('date_filter_field') === 'updated_at' ? 'selected' : '' }}>Last Updated</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <input type="hidden" name="quick_date_range" id="quick_date_range" value="{{ request('quick_date_range') }}">
                                <div class="quick-filters">
                                    @php
                                        $quickFilters = [
                                            'today' => 'Today',
                                            'this_week' => 'This Week',
                                            'this_month' => 'This Month',
                                            'last_month' => 'Last Month',
                                            'last_30_days' => 'Last 30 Days',
                                            'last_90_days' => 'Last 90 Days',
                                            'this_year' => 'This Year',
                                            'last_year' => 'Last Year',
                                        ];
                                    @endphp
                                    @foreach($quickFilters as $key => $label)
                                        <span class="quick-filter-chip {{ request('quick_date_range') === $key ? 'active' : '' }}" data-filter="{{ $key }}">
                                            <i class="fa-solid fa-calendar"></i> {{ $label }}
                                        </span>
                                    @endforeach
                                </div>

                                <div class="divider-text">Or Custom Range</div>
                                <div class="date-range-wrapper">
                                    <div class="form-group">
                                        <label for="from_date">From Date</label>
                                        <input type="date" name="from_date" id="from_date" value="{{ request('from_date') }}" class="form-control" placeholder="Start date">
                                    </div>
                                    <span class="date-range-arrow">&rarr;</span>
                                    <div class="form-group">
                                        <label for="to_date">To Date</label>
                                        <input type="date" name="to_date" id="to_date" value="{{ request('to_date') }}" class="form-control" placeholder="End date">
                                    </div>
                                </div>
                            </div>

                            <div class="row mt-4">
                                <div class="col-md-12 text-center">
                                    <div class="filter-buttons-container">
                                        <button type="submit" class="btn btn-primary btn-theme-lg me-3">Apply Filters</button>
                                        <a class="btn btn-info" href="{{URL::to('/clients')}}">Reset</a>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>

                    @if($lists->total() > 0)
                    <div class="clients-results-bar">
                        Showing {{ number_format($lists->firstItem()) }}&ndash;{{ number_format($lists->lastItem()) }} of {{ number_format($lists->total()) }} clients
                        @if($activeFilterCount > 0)
                            <span class="clients-results-bar__filtered"><i class="fa-solid fa-filter"></i> Filtered</span>
                        @endif
                    </div>
                    @endif

                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th class="client-select-cell">
                                        <label class="client-row-checkbox client-row-checkbox--header" for="checkbox-all" title="Select all on this page">
                                            <input type="checkbox" data-checkboxes="mygroup" data-checkbox-role="dad" class="cb-select-all" id="checkbox-all">
                                            <span class="client-row-checkbox__box" aria-hidden="true"><i class="fa-solid fa-check"></i></span>
                                        </label>
                                    </th>
                                    <th class="sortable-header">@sortablelink('first_name', 'Name')</th>
                                    <th class="sortable-header">@sortablelink('client_id', 'Client ID')</th>
                                    <th class="sortable-header">@sortablelink('status', 'Status')</th>
                                    <th class="sortable-header">@sortablelink('updated_at', 'Last Updated')</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="tdata">
                                @if($lists->count() > 0)
                                    @foreach (@$lists as $list)
                                    <tr id="id_{{@$list->id}}" class="client-data-row">
                                            <td class="client-select-cell">
                                                <label class="client-row-checkbox" for="checkbox-{{ @$list->id }}" title="Select client">
                                                    <input data-id="{{@$list->id}}" data-email="{{@$list->email}}" data-name="{{@$list->first_name}} {{@$list->last_name}}" data-clientid="{{@$list->client_id}}" data-unread="{{ (int) ($unreadEmailCounts[$list->id] ?? 0) }}" type="checkbox" data-checkboxes="mygroup" class="cb-element your-checkbox" id="checkbox-{{ @$list->id }}">
                                                    <span class="client-row-checkbox__box" aria-hidden="true"><i class="fa-solid fa-check"></i></span>
                                                </label>
                                            </td>
                                            <?php
                                            // Check if active matter exists
                                            $latestMatter = \DB::table('client_matters')
                                                ->where('client_id', $list->id)
                                                ->where('matter_status', 1)
                                                ->orderByDesc('id')
                                                ->first();
                                            $encodedId = base64_encode(convert_uuencode(@$list->id));
                                            $clientDetailUrl = $latestMatter
                                                ? URL::to('/clients/detail/'.$encodedId.'/'.$latestMatter->client_unique_matter_no )
                                                : URL::to('/clients/detail/'.$encodedId);
                                            $firstName = trim((string) @$list->first_name);
                                            $lastName = trim((string) @$list->last_name);
                                            $displayName = trim($firstName . ' ' . $lastName);
                                            if ($displayName === '') {
                                                $displayName = config('constants.empty');
                                            }
                                            $initials = strtoupper(substr($firstName !== '' ? $firstName : $lastName, 0, 1) . substr($lastName !== '' ? $lastName : $firstName, 0, 1));
                                            if ($initials === '') {
                                                $initials = '?';
                                            }
                                            $unreadMailCount = (int) ($unreadEmailCounts[$list->id] ?? 0);
                                            $unreadMatterRef = $unreadEmailMatterRefs[$list->id] ?? null;
                                            if ($unreadMailCount > 0 && ! empty($unreadMatterRef)) {
                                                $clientEmailsUrl = URL::to('/clients/detail/' . $encodedId . '/' . $unreadMatterRef . '/emails');
                                            } else {
                                                $clientEmailsUrl = rtrim($clientDetailUrl, '/') . '/emails';
                                            }
                                            ?>
                                            <td>
                                                <div class="client-name-cell">
                                                    <span class="client-avatar" aria-hidden="true">{{ $initials }}</span>
                                                    <div class="client-name-meta">
                                                        <a href="{{ $clientDetailUrl }}" class="client-name-link" title="View client profile">{{ Str::limit($displayName, 50, '...') }}</a>
                                                        @if($unreadMailCount > 0)
                                                            <a href="{{ $clientEmailsUrl }}" class="client-unread-mail-badge" title="{{ $unreadMailCount }} unread {{ Str::plural('email', $unreadMailCount) }} — open Emails tab">
                                                                <i class="fa-solid fa-envelope" aria-hidden="true"></i>{{ $unreadMailCount }}
                                                            </a>
                                                        @endif
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                @if(!empty($list->client_id))
                                                    <span class="client-id-chip">{{ Str::limit($list->client_id, 50, '...') }}</span>
                                                @else
                                                    {{ config('constants.empty') }}
                                                @endif
                                            </td>
                                            <td>
                                                @php
                                                    $isActiveClient = (string) @$list->status === '1';
                                                @endphp
                                                <span class="status-badge {{ $isActiveClient ? 'active' : 'inactive' }}">
                                                    <i class="fa-solid fa-circle"></i> {{ $isActiveClient ? 'Active' : 'Inactive' }}
                                                </span>
                                            </td>
                                            <td>
                                                @if(!empty($list->updated_at))
                                                    {{ \Carbon\Carbon::parse($list->updated_at)->format('d/m/Y') }}
                                                @else
                                                    {{ config('constants.empty') }}
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                <div class="action-buttons">
                                                    <a class="btn-action-icon btn-action-email clientemail"
                                                       data-id="{{ @$list->id }}"
                                                       data-email="{{ @$list->email }}"
                                                       data-name="{{ @$list->first_name }} {{ @$list->last_name }}"
                                                       href="javascript:;"
                                                       title="Send email">
                                                        <i class="fa-regular fa-envelope"></i>
                                                    </a>
                                                    <a class="btn-action-icon btn-action-edit"
                                                       href="{{ URL::to('/clients/edit/'.base64_encode(convert_uuencode(@$list->id))) }}"
                                                       title="Edit client">
                                                        <i class="fa-regular fa-pen-to-square"></i>
                                                    </a>
                                                    <a class="btn-action-icon btn-action-export"
                                                       href="{{ URL::to('/clients/export/'.base64_encode(convert_uuencode(@$list->id))) }}"
                                                       title="Export client data">
                                                        <i class="fa-solid fa-download"></i>
                                                    </a>
                                                    <form action="{{ route('clients.archive', base64_encode(convert_uuencode(@$list->id))) }}" method="POST" class="archive-client-form" style="display: inline-block;">
                                                        @csrf
                                                        <button type="button"
                                                                class="btn-action-icon btn-action-archive"
                                                                title="Archive client"
                                                                onclick="archiveClientAction(event, '{{ @$list->first_name }} {{ @$list->last_name }}')">
                                                            <i class="fa-solid fa-box-archive"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                @else
                                    <tr>
                                        <td colspan="6">
                                            <div class="clients-empty-state">
                                                <div class="clients-empty-state__icon" aria-hidden="true">
                                                    <i class="fa-solid fa-users-slash"></i>
                                                </div>
                                                <h5>No clients found</h5>
                                                <p>
                                                    @if($activeFilterCount > 0)
                                                        No records match your current filters. Try adjusting or clearing them.
                                                    @else
                                                        There are no client records to display yet.
                                                    @endif
                                                </p>
                                                @if($activeFilterCount > 0)
                                                    <a href="{{ URL::to('/clients') }}" class="btn btn-theme btn-theme-sm">
                                                        <i class="fa-solid fa-undo"></i> Clear Filters
                                                    </a>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>

                    <div class="clients-infinite-loader" id="clientsInfiniteLoader" hidden aria-live="polite">
                        <span class="clients-infinite-loader__spinner" aria-hidden="true"></span>
                        <span>Loading more clients...</span>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<div id="emailmodal"  data-backdrop="static" data-keyboard="false" class="modal fade custom_modal" tabindex="-1" role="dialog" aria-labelledby="clientModalLabel" aria-hidden="true" data-staff-signature="{{ auth()->user()->email_signature ?? '' }}" data-signature-prefill="allow">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="clientModalLabel">Compose Email</h5>
				<button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body">
				<form method="post" name="sendmail" action="{{URL::to('/sendmail')}}" autocomplete="off" enctype="multipart/form-data">
				@csrf
					<div class="row">
						<div class="col-12 col-md-6 col-lg-6">
							<div class="form-group">
								<label for="email_from">From <span class="span_req">*</span></label>
								@include('partials.email-from-compose')
								@if ($errors->has('email_from'))
									<span class="custom-error" role="alert">
										<strong>{{ @$errors->first('email_from') }}</strong>
									</span>
								@endif
							</div>
						</div>
						<div class="col-12 col-md-6 col-lg-6">
							<div class="form-group">
								<label for="email_to">To <span class="span_req">*</span></label>
								<select multiple data-valid="required" class="js-data-example-ajax" name="email_to[]"></select>

								@if ($errors->has('email_to'))
									<span class="custom-error" role="alert">
										<strong>{{ @$errors->first('email_to') }}</strong>
									</span>
								@endif
							</div>
						</div>
						<div class="col-12 col-md-6 col-lg-6">
							<div class="form-group">
								<label for="email_cc">CC </label>
								<select multiple data-valid="" class="js-data-example-ajaxcc" name="email_cc[]"></select>

								@if ($errors->has('email_cc'))
									<span class="custom-error" role="alert">
										<strong>{{ @$errors->first('email_cc') }}</strong>
									</span>
								@endif
							</div>
						</div>

						<div class="col-12 col-md-6 col-lg-6">
							<div class="form-group">
								<label for="template">Templates </label>
								<select data-valid="" class="form-control crm-ts-plain selecttemplate" name="template">
									<option value="">Select</option>
									@foreach(\App\Models\EmailTemplate::crm()->orderBy('id', 'desc')->get() as $list)
										<option value="{{$list->id}}">{{$list->name}}</option>
									@endforeach
								</select>

							</div>
						</div>
						<div class="col-12 col-md-12 col-lg-12">
							<div class="form-group">
								<label for="subject">Subject <span class="span_req">*</span></label>
								<input type="text" name="subject" value="{{ old('subject', '') }}" class="form-control selectedsubject" data-valid="required" autocomplete="off" placeholder="Enter Subject">
								@if ($errors->has('subject'))
									<span class="custom-error" role="alert">
										<strong>{{ @$errors->first('subject') }}</strong>
									</span>
								@endif
							</div>
						</div>
						<div class="col-12 col-md-12 col-lg-12">
							<div class="form-group">
								<label for="message">Message <span class="span_req">*</span></label>
								<textarea class="tinymce-editor selectedmessage" name="message"></textarea>
								@if ($errors->has('message'))
									<span class="custom-error" role="alert">
										<strong>{{ @$errors->first('message') }}</strong>
									</span>
								@endif
							</div>
						</div>
						<div class="col-12 col-md-12 col-lg-12">
							<button onclick="customValidate('sendmail')" type="button" class="btn btn-primary">Send</button>
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
<script src="{{ asset('js/crm/clients/clients-listing-spa.js') }}"></script>
<script>
window.ClientsListingSpaConfig = {
    csrfToken: document.querySelector('meta[name="csrf-token"]') ? document.querySelector('meta[name="csrf-token"]').getAttribute('content') : '',
    routes: {
        clientsIndex: @json(url('/clients')),
        leadsIndex: @json(url('/leads')),
        archivedIndex: @json(url('/archived')),
        mergeRecords: @json(url('/merge_records')),
        getRecipients: @json(url('/clients/get-recipients')),
        getTemplates: @json(url('/get-templates'))
    }
};
jQuery(function () {
    if (window.ClientsListingSpa) {
        ClientsListingSpa.init();
    }
});
</script>
@endpush


