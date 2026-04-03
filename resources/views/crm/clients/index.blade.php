@extends('layouts.crm_client_detail')
@section('title', 'Clients')

@section('styles')
<link rel="stylesheet" href="{{ asset('css/listing-pagination.css') }}">
<link rel="stylesheet" href="{{ asset('css/listing-container.css') }}">
<link rel="stylesheet" href="{{ asset('css/listing-datepicker.css') }}">
<style>
    /* Page-specific styles for clients index page */
    /* Fix dropdown menu display for action buttons */
    .listing-container .dropdown-menu {
        position: absolute !important;
        top: 100% !important;
        right: 0 !important;
        left: auto !important;
        float: none !important;
        min-width: 180px;
        padding: 8px 0;
        margin: 4px 0 0;
        font-size: 14px;
        text-align: left;
        background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
        border: 1px solid rgba(102, 126, 234, 0.2);
        border-radius: 8px;
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        background-clip: padding-box;
        z-index: 9999 !important;
        transform: none !important;
        max-height: none !important;
        overflow: visible !important;
        backdrop-filter: blur(10px);
    }
    
    .listing-container .dropdown-menu.show {
        display: block !important;
        opacity: 1 !important;
        visibility: visible !important;
    }
    
    .listing-container .table td .dropdown {
        position: relative;
        display: inline-block;
    }
    
    .listing-container .table td .dropdown-toggle {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
        border: 1px solid #667eea !important;
        min-width: 80px;
        max-width: 90px;
        padding: 6px 12px;
        font-size: 13px;
        font-weight: 500;
        color: white !important;
        border-radius: 6px;
        box-shadow: 0 2px 4px rgba(102, 126, 234, 0.2);
        position: relative;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 4px;
    }
    
    .listing-container .table td .dropdown-toggle:hover {
        background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%) !important;
        border-color: #5a6fd8 !important;
        box-shadow: 0 4px 8px rgba(102, 126, 234, 0.3);
    }
    
    .listing-container .table td .dropdown-toggle:focus {
        outline: none;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.25);
    }
    
    .listing-container .table td .dropdown-toggle::after {
        content: '';
        display: inline-block;
        margin-left: 4px;
        vertical-align: middle;
        border-top: 4px solid;
        border-right: 4px solid transparent;
        border-bottom: 0;
        border-left: 4px solid transparent;
    }
    
    .listing-container .table td .dropdown-toggle.show::after {
        transform: rotate(180deg);
    }
    
    .listing-container .dropdown-item {
        display: block;
        width: 100%;
        padding: 10px 20px;
        clear: both;
        font-weight: 500;
        color: #495057;
        text-align: inherit;
        white-space: nowrap;
        background-color: transparent;
        border: 0;
        text-decoration: none;
        border-radius: 4px;
        margin: 2px 8px;
        width: calc(100% - 16px);
    }
    
    .listing-container .dropdown-item:hover {
        color: #667eea;
        text-decoration: none;
        background: linear-gradient(135deg, #f8f9ff 0%, #e8ecff 100%);
        box-shadow: 0 2px 8px rgba(102, 126, 234, 0.15);
    }
    
    .listing-container .dropdown-item:active {
        background: linear-gradient(135deg, #e8ecff 0%, #d8e0ff 100%);
    }
    
    .listing-container .dropdown-item.has-icon {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .listing-container .dropdown-item.has-icon i {
        width: 16px;
        text-align: center;
    }
    
    /* Ensure all dropdown items are visible */
    .listing-container .dropdown-menu .dropdown-item {
        display: block !important;
        visibility: visible !important;
        opacity: 1 !important;
        height: auto !important;
        min-height: 32px !important;
        line-height: 1.5 !important;
    }
    
    /* Remove any potential overflow restrictions */
    .listing-container .table td {
        overflow: visible !important;
    }
    
    .listing-container .table td .dropdown {
        overflow: visible !important;
    }
    
    /* Ensure dropdown container doesn't clip content */
    .listing-container .dropdown {
        overflow: visible !important;
    }

    .listing-container .card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 16px;
    }

    .listing-container .card-header-actions {
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
    }

    .listing-container .per-page-select {
        border: 1px solid white !important;
        border-radius: 8px !important;
        background: white !important;
        color: #667eea !important;
        font-weight: 600 !important;
        padding: 8px 16px !important;
        min-width: 110px;
        width: auto !important;
        flex: 0 0 auto;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .listing-container .per-page-select:focus {
        outline: none;
        border-color: #667eea !important;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.2);
    }

    .listing-container .per-page-select option {
        background: white;
        color: #667eea;
    }

    .listing-container .filter_panel {
        background: #f8fafc;
        border-radius: 12px;
        padding: 24px;
        margin-bottom: 24px;
        display: none;
        border: 1px solid #e2e8f0;
    }

    .listing-container .filter_panel h4 {
        color: #1e293b;
        font-size: 18px;
        font-weight: 700;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .active-filters-badge {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: white;
        border-radius: 12px;
        padding: 4px 12px;
        font-size: 12px;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .clear-filter-btn {
        background: transparent;
        border: 2px solid #ef4444;
        color: #ef4444;
        padding: 6px 14px;
        border-radius: 8px;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .clear-filter-btn:hover {
        background: #ef4444;
        color: white;
    }

    .listing-container .form-group label {
        color: #475569 !important;
        font-weight: 600;
        font-size: 13px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 8px;
    }

    .listing-container .form-control {
        border: 2px solid #e2e8f0 !important;
        border-radius: 10px !important;
        padding: 10px 16px !important;
        font-size: 14px !important;
        background: white !important;
        height: auto !important;
    }

    .listing-container .form-control:focus {
        border-color: #667eea !important;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1) !important;
    }

    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 14px;
        border-radius: 20px;
        font-weight: 600;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .status-badge.active {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: white;
        box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
    }

    .status-badge.inactive {
        background: linear-gradient(135deg, #f87171 0%, #ef4444 100%);
        color: white;
        box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3);
    }

    .sortable-header {
        cursor: pointer;
        user-select: none;
        white-space: nowrap;
    }

    .sortable-header i {
        margin-left: 6px;
    }
</style>
@include('crm.clients.partials.enhanced-date-filter-styles')
@endsection

@section('content')
<div class="listing-container">
    <section class="listing-section" style="padding-top: 40px;">
        <div class="listing-section-body">
            @include('../Elements/flash-message')

            <div class="card">
                <div class="custom-error-msg">
                </div>
                <div class="card-header">
                    <h4>All Clients</h4>

                    <div class="card-header-actions">
                        @if(Auth::user() && in_array(Auth::user()->role, [1, 12]))
                        <a href="{{ route('clients.insights', ['section' => 'clients']) }}" class="btn btn-theme btn-theme-sm" title="View Insights">
                            <i class="fas fa-chart-line"></i> Insights
                        </a>
                        @endif
                        <select name="per_page" id="per_page" class="form-control per-page-select">
                            @foreach([10, 20, 50, 100, 200] as $option)
                                <option value="{{ $option }}" {{ ($perPage ?? 20) == $option ? 'selected' : '' }}>
                                    {{ $option }} / page
                                </option>
                            @endforeach
                        </select>
                        <a href="javascript:;" class="btn btn-theme btn-theme-sm filter_btn">
                            <i class="fas fa-filter"></i> Filter
                        </a>
                    </div>
                </div>

                <div class="card-body">
                    <ul class="nav nav-pills" id="client_tabs" role="tablist">
                        <li class="nav-item is_checked_client" style="display:none;">
                            <a class="btn btn-primary emailmodal" href="javascript:;">Send Mail</a>
                        </li>
                        <li class="nav-item is_checked_client" style="display:none;">
                            <a class="btn btn-primary" href="javascript:;">Change Assignee</a>
                        </li>

                        <li class="nav-item is_checked_client_merge" style="display:none;">
                            <a class="btn btn-primary" href="javascript:;">Merge</a>
                        </li>

                        <li class="nav-item is_checked_clientn">
                            <a class="nav-link active" id="clients-tab"  href="{{URL::to('/clients')}}" >Clients</a>
                        </li>
                        <li class="nav-item is_checked_clientn">
                            <a class="nav-link" id="archived-tab"  href="{{URL::to('/archived')}}" >Archived</a>
                        </li>

                        <li class="nav-item is_checked_clientn">
                            <a class="nav-link" id="lead-tab"  href="{{URL::to('/leads')}}" >Leads</a>
                        </li>
                    </ul>

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
                    <div class="filter_panel">
                        <div class="d-flex justify-content-between align-items-center flex-wrap">
                            <h4>
                                Search By Details
                                @if($activeFilterCount > 0)
                                    <span class="active-filters-badge">
                                        <i class="fas fa-filter"></i> {{ $activeFilterCount }} Active
                                    </span>
                                @endif
                            </h4>
                            @if($activeFilterCount > 0)
                                <button type="button" class="clear-filter-btn" id="clearFilters">
                                    <i class="fas fa-undo"></i> Clear Filters
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
                                            <label for="date_filter_field"><i class="fas fa-calendar-alt"></i> Date Field</label>
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
                                            <i class="fas fa-calendar"></i> {{ $label }}
                                        </span>
                                    @endforeach
                                </div>

                                <div class="divider-text">Or Custom Range</div>
                                <div class="date-range-wrapper">
                                    <div class="form-group">
                                        <label for="from_date">From Date</label>
                                        <input type="date" name="from_date" id="from_date" value="{{ request('from_date') }}" class="form-control" placeholder="Start date">
                                    </div>
                                    <span class="date-range-arrow">→</span>
                                    <div class="form-group">
                                        <label for="to_date">To Date</label>
                                        <input type="date" name="to_date" id="to_date" value="{{ request('to_date') }}" class="form-control" placeholder="End date">
                                    </div>
                                </div>
                            </div>

                            <div class="row mt-4">
                                <div class="col-md-12 text-center">
                                    <div class="filter-buttons-container">
                                        <button type="submit" class="btn btn-primary btn-theme-lg mr-3">Apply Filters</button>
                                        <a class="btn btn-info" href="{{URL::to('/clients')}}">Reset</a>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>

                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th class="text-center">
                                        <div class="custom-checkbox custom-checkbox-table custom-control">
                                            <input type="checkbox" data-checkboxes="mygroup" data-checkbox-role="dad" class="custom-control-input" id="checkbox-all">
                                            <label for="checkbox-all" class="custom-control-label">&nbsp;</label>
                                        </div>
                                    </th>
                                    <th class="sortable-header">@sortablelink('first_name', 'Name')</th>
                                    <th class="sortable-header">@sortablelink('client_id', 'Client ID')</th>
                                    <th class="sortable-header">@sortablelink('status', 'Status')</th>
                                    <th class="sortable-header">@sortablelink('updated_at', 'Last Updated')</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody class="tdata">
                                @if(@$totalData !== 0)
                                <?php $i=0; ?>
                                    @foreach (@$lists as $list)
                                    <tr id="id_{{@$list->id}}">
                                            <td style="white-space: initial;" class="text-center">
                                                <div class="custom-checkbox custom-control">
                                                    <input data-id="{{@$list->id}}" data-email="{{@$list->email}}" data-name="{{@$list->first_name}} {{@$list->last_name}}" data-clientid="{{@$list->client_id}}" type="checkbox" data-checkboxes="mygroup" class="cb-element custom-control-input  your-checkbox" id="checkbox-{{$i}}">
                                                    <label for="checkbox-{{$i}}" class="custom-control-label">&nbsp;</label>
                                                </div>
                                            </td>
                                            <?php
                                            // Check if active matter exists
                                            $latestMatter = \DB::table('client_matters')
                                                ->where('client_id', $list->id)
                                                ->where('matter_status', 1)
                                                ->orderByDesc('id') // or use created_at if preferred
                                                ->first();
                                            $encodedId = base64_encode(convert_uuencode(@$list->id));
                                            $clientDetailUrl = $latestMatter
                                                ? URL::to('/clients/detail/'.$encodedId.'/'.$latestMatter->client_unique_matter_no )
                                                : URL::to('/clients/detail/'.$encodedId);
                                            ?>
                                            <td style="white-space: initial;"><a href="{{ $clientDetailUrl }}">{{ @$list->first_name == "" ? config('constants.empty') : Str::limit(@$list->first_name, '50', '...') }} {{ @$list->last_name == "" ? config('constants.empty') : Str::limit(@$list->last_name, '50', '...') }} </a><br/></td>
                                            <td style="white-space: initial;">{{ @$list->client_id == "" ? config('constants.empty') : Str::limit(@$list->client_id, '50', '...') }}</td>
                                            <td>
                                                @php
                                                    $isActiveClient = (string) @$list->status === '1';
                                                @endphp
                                                <span class="status-badge {{ $isActiveClient ? 'active' : 'inactive' }}">
                                                    <i class="fas fa-circle"></i> {{ $isActiveClient ? 'Active' : 'Inactive' }}
                                                </span>
                                            </td>
                                            <td style="white-space: initial;">
                                                @if(!empty($list->updated_at))
                                                    {{ \Carbon\Carbon::parse($list->updated_at)->format('d/m/Y') }}
                                                @else
                                                    {{ config('constants.empty') }}
                                                @endif
                                            </td>
                                            <td style="white-space: initial;">
                                                <div class="dropdown d-inline">
                                                    <button class="btn btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                        Action
                                                    </button>
                                                    <div class="dropdown-menu">
                                                        <a class="dropdown-item has-icon clientemail" data-id="{{@$list->id}}" data-email="{{@$list->email}}" data-name="{{@$list->first_name}} {{@$list->last_name}}" href="javascript:;">
                                                            <i class="far fa-envelope"></i> Email
                                                        </a>
                                                        <a class="dropdown-item has-icon" href="{{URL::to('/clients/edit/'.base64_encode(convert_uuencode(@$list->id)))}}">
                                                            <i class="far fa-edit"></i> Edit
                                                        </a>
                                                        <a class="dropdown-item has-icon" href="{{URL::to('/clients/export/'.base64_encode(convert_uuencode(@$list->id)))}}" title="Export Client Data">
                                                            <i class="fas fa-download"></i> Export
                                                        </a>
                                                        <form action="{{ route('clients.archive', base64_encode(convert_uuencode(@$list->id))) }}" method="POST" class="archive-client-form" style="display: inline-block;">
                                                            @csrf
                                                            <a class="dropdown-item has-icon" href="javascript:;" onclick="archiveClientAction(event, '{{ @$list->first_name }} {{ @$list->last_name }}')">
                                                                <i class="fas fa-archive"></i> Archive
                                                            </a>
                                                        </form>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
										<?php $i++; ?>
                                    @endforeach
                                @else
                                    <tr>
                                        <td colspan="7" style="text-align: center; padding: 20px;">
                                            No Record Found
                                        </td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="card-footer">
                    {!! $lists->appends(\Request::except('page'))->render() !!}
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<div id="emailmodal"  data-backdrop="static" data-keyboard="false" class="modal fade custom_modal" tabindex="-1" role="dialog" aria-labelledby="clientModalLabel" aria-hidden="true">
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
								@include('partials.email-from-sendgrid')
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
								<select data-valid="required" class="js-data-example-ajax" name="email_to[]"></select>

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
								<select data-valid="" class="js-data-example-ajaxcc" name="email_cc[]"></select>

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
								<select data-valid="" class="form-control select2 selecttemplate" name="template">
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
								<textarea class="summernote-simple selectedmessage" name="message"></textarea>
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
<script>
jQuery(document).ready(function($){
    $('#per_page').on('change', function(){
        var currentUrl = new URL(window.location.href);
        currentUrl.searchParams.set('per_page', $(this).val());
        currentUrl.searchParams.delete('page');
        window.location.href = currentUrl.toString();
    });

    $('.quick-filter-chip').on('click', function(){
        var filter = $(this).data('filter');
        $('#quick_date_range').val(filter);
        $('#from_date, #to_date').val('');
        $('#filterForm').submit();
    });

    $('#from_date, #to_date').on('change', function(){
        $('#quick_date_range').val('');
    });

    $('#clearFilters').on('click', function(){
        window.location.href = "{{ URL::to('/clients') }}";
    });

	$('.listing-container .filter_btn').on('click', function(){
		$('.listing-container .filter_panel').toggle();
	});

	$('.listing-container [data-checkboxes]').each(function () {
        var me = $(this),
        group = me.data('checkboxes'),
        role = me.data('checkbox-role');

        me.change(function () {
            var all = $('.listing-container [data-checkboxes="' + group + '"]:not([data-checkbox-role="dad"])'),
            checked = $('.listing-container [data-checkboxes="' + group + '"]:not([data-checkbox-role="dad"]):checked'),
            dad = $('.listing-container [data-checkboxes="' + group + '"][data-checkbox-role="dad"]'),
            total = all.length,
            checked_length = checked.length;
            //alert('total='+total);
            //alert('checked_length='+checked_length);
            //alert('role='+role);
            if (role == 'dad') {
                if (me.is(':checked')) {
                    all.prop('checked', true);
                    $('.listing-container .is_checked_client').show();
                    $('.listing-container .is_checked_clientn').hide();
                } else {
                    all.prop('checked', false);
                    $('.listing-container .is_checked_client').hide();
                    $('.listing-container .is_checked_clientn').show();
                }
            } else {
                if (checked_length >= total) {
                    dad.prop('checked', true);
                    $('.listing-container .is_checked_client').show();
                    $('.listing-container .is_checked_clientn').hide();
                } else {
                    dad.prop('checked', false);
                    $('.listing-container .is_checked_client').hide();
                    $('.listing-container .is_checked_clientn').show();
                }
            }
            //alert(checked_length);
            if(checked_length == 2){
                $('.listing-container .is_checked_client_merge').show();
            } else {
                $('.listing-container .is_checked_client_merge').hide();
            }
        });
    });


    var clickedOrder = [];
    var clickedIds = [];
    $(document).delegate('.listing-container .your-checkbox', 'click', function(){
        var clicked_id = $(this).data('id');
        var nameStr = $(this).attr('data-name');
        var clientidStr = $(this).attr('data-clientid');
        var finalStr = nameStr+'('+clientidStr+')'; //console.log('finalStr='+finalStr);
        if ($(this).is(':checked')) {
            clickedOrder.push(finalStr);
            clickedIds.push(clicked_id);
        } else {
            var index = clickedOrder.indexOf(finalStr);
            if (index !== -1) {
                clickedOrder.splice(index, 1);
            }
            var index1 = clickedIds.indexOf(clicked_id);
            if (index1 !== -1) {
                clickedIds.splice(index1, 1);
            }
        }
    });

    //merge task
    $(document).delegate('.listing-container .is_checked_client_merge', 'click', function(){
        if ( clickedOrder.length > 0 && clickedOrder.length == 2 )
        {
            var mergeStr = "Are you sure want to merge "+clickedOrder[0]+" record into this "+clickedOrder[1]+" record?";
            if (confirm(mergeStr)) {
                $.ajax({
                    type:'post',
                    url:"{{URL::to('/')}}/merge_records",
                    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
                    data: {merge_from:clickedIds[0],merge_into:clickedIds[1]},
                    success: function(response){
                        var obj = $.parseJSON(response);
                        //console.log(obj.message);
                        location.reload(true);
                    }
                });
                //return false;
            }
        }
    });


    $('.listing-container .cb-element').change(function () {

        if ($('.listing-container .cb-element:checked').length == $('.listing-container .cb-element').length){
            $('.listing-container #checkbox-all').prop('checked',true);
        }
        else {
            $('.listing-container #checkbox-all').prop('checked',false);
        }

        if ($('.listing-container .cb-element:checked').length > 0){
            $('.listing-container .is_checked_client').show();
            $('.listing-container .is_checked_clientn').hide();
        }else{
            $('.listing-container .is_checked_client').hide();
            $('.listing-container .is_checked_clientn').show();
        }
    });


    $(document).delegate('.listing-container .emailmodal', 'click', function(){
        $('#emailmodal').modal('show');
        var array = [];
        var data = [];
	    $('.listing-container .cb-element:checked').each(function(){
            var id = $(this).attr('data-id');
			array.push(id);
			var email = $(this).attr('data-email');
			var name = $(this).attr('data-name');
			var status = 'Client';

			data.push({
				id: id,
                text: name,
                html:  "<div  class='select2-result-repository ag-flex ag-space-between ag-align-center'>" +

                    "<div  class='ag-flex ag-align-start'>" +
                        "<div  class='ag-flex ag-flex-column col-hr-1'><div class='ag-flex'><span  class='select2-result-repository__title text-semi-bold'>"+name+"</span>&nbsp;</div>" +
                        "<div class='ag-flex ag-align-center'><small class='select2-result-repository__description'>"+email+"</small ></div>" +

                    "</div>" +
                    "</div>" +
                    "<div class='ag-flex ag-flex-column ag-align-end'>" +

                        "<span class='ui label yellow select2-result-repository__statistics'>"+ status +

                        "</span>" +
                    "</div>" +
                    "</div>",
                title: name
			});
        });

        $(".js-data-example-ajax").select2({
            data: data,
            escapeMarkup: function(markup) {
                return markup;
            },
            templateResult: function(data) {
                return data.html;
            },
            templateSelection: function(data) {
                return data.text;
            }
        })

	    $('.js-data-example-ajax').val(array);
		$('.js-data-example-ajax').trigger('change');

    });

    $(document).delegate('.listing-container .clientemail', 'click', function(){
        $('#emailmodal').modal('show');
        var array = [];
        var data = [];

        var id = $(this).attr('data-id');
        array.push(id);
        var email = $(this).attr('data-email');
        var name = $(this).attr('data-name');
        var status = 'Client';

		data.push({
			id: id,
            text: name,
            html:  "<div  class='select2-result-repository ag-flex ag-space-between ag-align-center'>" +

                "<div  class='ag-flex ag-align-start'>" +
                    "<div  class='ag-flex ag-flex-column col-hr-1'><div class='ag-flex'><span  class='select2-result-repository__title text-semi-bold'>"+name+"</span>&nbsp;</div>" +
                    "<div class='ag-flex ag-align-center'><small class='select2-result-repository__description'>"+email+"</small ></div>" +

                "</div>" +
                "</div>" +
                "<div class='ag-flex ag-flex-column ag-align-end'>" +

                    "<span class='ui label yellow select2-result-repository__statistics'>"+ status +

                    "</span>" +
                "</div>" +
                "</div>",
            title: name
		});

        $(".js-data-example-ajax").select2({
            data: data,
            escapeMarkup: function(markup) {
                return markup;
            },
            templateResult: function(data) {
                return data.html;
            },
            templateSelection: function(data) {
                return data.text;
            }
        })

	    $('.js-data-example-ajax').val(array);
		$('.js-data-example-ajax').trigger('change');

    });

    $(document).delegate('.listing-container .selecttemplate', 'change', function(){
        var v = $(this).val();
        $.ajax({
            url: '{{URL::to('/get-templates')}}',
            type:'GET',
            datatype:'json',
            data:{id:v},
            success: function(response){
                var res = JSON.parse(response);
                $('.selectedsubject').val(res.subject);
                // Clear and set TinyMCE editor content
                $(".summernote-simple").each(function() {
                    var editorId = $(this).attr('id');
                    if (editorId && typeof tinymce !== 'undefined' && tinymce.get(editorId)) {
                        tinymce.get(editorId).setContent(res.description || '');
                    } else {
                        $(this).val(res.description || '');
                    }
                });
            }
        });
    });

	$('.js-data-example-ajax').select2({
		multiple: true,
		closeOnSelect: false,
		dropdownParent: $('#emailmodal'),
		ajax: {
			url: '{{URL::to('/clients/get-recipients')}}',
			dataType: 'json',
			processResults: function (data) {
			  // Transforms the top-level key of the response object from 'items' to 'results'
			    return {
				    results: data.items
			    };
            },
			cache: true
        },
	    templateResult: formatRepo,
	    templateSelection: formatRepoSelection
    });

    $('.js-data-example-ajaxcc').select2({
		multiple: true,
		closeOnSelect: false,
		dropdownParent: $('#emailmodal'),
		ajax: {
			url: '{{URL::to('/clients/get-recipients')}}',
			dataType: 'json',
			processResults: function (data) {
			    // Transforms the top-level key of the response object from 'items' to 'results'
			    return {
				    results: data.items
			    };
            },
			cache: true
        },
	    templateResult: formatRepo,
	    templateSelection: formatRepoSelection
    });

    function formatRepo (repo) {
        if (repo.loading) {
            return repo.text;
        }

        var $container = $(
            "<div  class='select2-result-repository ag-flex ag-space-between ag-align-center'>" +

            "<div  class='ag-flex ag-align-start'>" +
                "<div  class='ag-flex ag-flex-column col-hr-1'><div class='ag-flex'><span  class='select2-result-repository__title text-semi-bold'></span>&nbsp;</div>" +
                "<div class='ag-flex ag-align-center'><small class='select2-result-repository__description'></small ></div>" +

            "</div>" +
            "</div>" +
            "<div class='ag-flex ag-flex-column ag-align-end'>" +

                "<span class='ui label yellow select2-result-repository__statistics'>" +

                "</span>" +
            "</div>" +
            "</div>"
        );

        $container.find(".select2-result-repository__title").text(repo.name);
        $container.find(".select2-result-repository__description").text(repo.email);
        $container.find(".select2-result-repository__statistics").append(repo.status);

        return $container;
    }

    function formatRepoSelection (repo) {
        return repo.name || repo.text;
    }

    // Archive client confirmation function - Global scope
    function archiveClientAction(event, clientName) {
        event.preventDefault();
        
        // Get the form element - traverse up from the clicked link
        var form;
        if (event.target.tagName.toLowerCase() === 'a') {
            // Clicked on the link itself
            form = jQuery(event.target).closest('.archive-client-form')[0];
        } else if (event.target.tagName.toLowerCase() === 'i') {
            // Clicked on the icon inside the link
            form = jQuery(event.target).closest('.archive-client-form')[0];
        }
        
        if (!form) {
            console.error('Archive form not found');
            return false;
        }
        
        var confirmMessage = 'Are you sure you want to archive the client "' + (clientName || 'this client') + '"?\n\nThis will move the client to the archived list.';
        
        if (confirm(confirmMessage)) {
            form.submit();
        }
        
        return false;
    }
});
</script>
@endpush

