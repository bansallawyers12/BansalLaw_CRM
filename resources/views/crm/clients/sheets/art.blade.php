@extends('layouts.crm_client_detail')
@section('title', 'ART Submission and Hearing Files')

@section('styles')
<link rel="stylesheet" href="{{ asset('css/listing-container.css') }}">
<link rel="stylesheet" href="{{ asset('css/listing-pagination.css') }}">
<style>
    /* Remove top blank space: no margin/padding above ART content */
    .art-sheet-page.listing-container {
        margin-top: 0 !important;
        padding-top: 0 !important;
    }
    .art-sheet-page .listing-section {
        padding-top: 0 !important;
    }
    .art-sheet-page .art-sheet-card {
        margin-top: 0 !important;
    }
    /* Header colour theme: slate blue */
    .art-sheet-sticky-header {
        position: sticky;
        top: 70px; /* below main-navbar */
        z-index: 100;
        background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
        box-shadow: 0 2px 8px rgba(15, 23, 42, 0.06);
        margin: 0 -1px 0 -1px;
        border-bottom: 1px solid #e2e8f0;
    }
    .art-sheet-top-bar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 12px;
        padding: 10px 20px 0;
        border-bottom: 1px solid #e2e8f0;
    }
    .art-sheet-top-bar .art-sheet-title {
        font-size: 1.2rem;
        font-weight: 600;
        color: #1e293b;
        margin: 0;
        display: flex;
        align-items: center;
    }
    .art-sheet-top-bar .art-sheet-title i { margin-right: 8px; color: #475569; }
    .art-sheet-top-bar-right {
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
    }
    .art-sheet-top-bar .btn-theme,
    .art-sheet-top-bar .btn-theme-sm {
        background: #475569 !important;
        color: #fff !important;
        border: 1px solid #64748b !important;
    }
    .art-sheet-top-bar .btn-theme:hover,
    .art-sheet-top-bar .btn-theme-sm:hover {
        background: #334155 !important;
        color: #fff !important;
        border-color: #475569 !important;
    }
    .sheet-tabs {
        background: #e2e8f0;
        padding: 0;
        margin: 0;
        display: flex;
        gap: 2px;
        border-radius: 8px;
        flex: 0 0 auto;
        padding: 3px;
    }
    .sheet-tab {
        padding: 8px 14px;
        text-align: center;
        color: #64748b;
        text-decoration: none;
        font-weight: 600;
        font-size: 13px;
        transition: all 0.2s ease;
        border-radius: 6px;
        white-space: nowrap;
    }
    .sheet-tab:hover { color: #334155; background: #cbd5e1; text-decoration: none; }
    .sheet-tab.active {
        color: #fff;
        background: linear-gradient(135deg, #475569 0%, #334155 100%);
        box-shadow: 0 1px 3px rgba(0,0,0,0.12);
    }
    .sheet-tab i { margin-right: 6px; }
    .art-sheet-filter-bar {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
        padding: 10px 20px 12px;
        background: #fff;
        border-bottom: 1px solid #e2e8f0;
    }
    .art-sheet-filter-bar .filter_btn {
        margin: 0;
        background: linear-gradient(135deg, #475569 0%, #334155 100%) !important;
        color: #fff !important;
        border: none !important;
    }
    .art-sheet-filter-bar .filter_btn:hover {
        background: linear-gradient(135deg, #334155 0%, #1e293b 100%) !important;
        color: #fff !important;
    }
    .art-sheet-filter-bar .clear-filter-btn {
        margin: 0;
        background: #64748b !important;
        color: #fff !important;
        border: none !important;
    }
    .art-sheet-filter-bar .clear-filter-btn:hover {
        background: #475569 !important;
        color: #fff !important;
    }
    .art-sheet-filter-bar .active-filters-badge {
        background: #334155 !important;
        color: #fff !important;
    }
    .art-sheet-filter-bar .office-filter-section {
        flex: 1;
        min-width: 200px;
        margin: 0;
        padding: 6px 12px !important;
        background: #f8fafc !important;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
    }
    .art-sheet-filter-bar .office-filter-section label.mb-0,
    .art-sheet-filter-bar .office-filter-section .form-check-label {
        color: #475569 !important;
        font-size: 13px;
        margin-right: 8px !important;
    }
    .art-sheet-filter-bar .office-filter-section .form-check-inline { margin-right: 12px !important; }
    .art-sheet-filter-bar .office-filter-section .form-check-input:checked {
        background-color: #475569;
        border-color: #475569;
    }
    .art-sheet-filter-bar label[for="per_page"] { color: #475569 !important; }
    .art-sheet-filter-bar .per-page-select {
        margin-left: 4px;
        padding: 4px 8px;
        font-size: 13px;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        color: #334155;
    }
    .art-table { font-size: 13px; }
    /* Override global sticky table headers */
    .art-sheet-page .art-table th,
    .art-sheet-page .art-table thead th,
    .listing-container.art-sheet-page .table thead th,
    #art-sheet-table thead th,
    table#art-sheet-table thead th {
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%) !important;
        font-weight: 600;
        white-space: nowrap;
        padding: 12px 8px;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #475569;
        border-bottom: 2px solid #94a3b8;
        position: static !important;
        top: auto !important;
        z-index: auto !important;
    }
    .art-table td { padding: 10px 8px; vertical-align: middle; }
    .art-table tbody tr:hover { background-color: #f8fafc; }
    .art-link { color: #475569; font-weight: 600; text-decoration: none; }
    .art-link:hover { color: #334155; text-decoration: underline; }
    .art-comments-cell { max-width: 280px; font-size: 12px; line-height: 1.4; word-wrap: break-word; white-space: normal; }
    .filter_panel { display: none; background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
    .filter_panel.show { display: block; }
    .active-filters-badge { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600; margin-left: 10px; }
    .clear-filter-btn { background: #6c757d; color: white; border: none; padding: 8px 16px; border-radius: 6px; font-size: 14px; cursor: pointer; }
    .clear-filter-btn:hover { background: #5a6268; }
    .per-page-select { width: auto; display: inline-block; margin-left: 10px; }
    .sortable { cursor: pointer; user-select: none; position: relative; padding-right: 20px !important; }
    .sortable:hover { background: rgba(71, 85, 105, 0.08); }
    .sortable::after { content: '\f0dc'; font-family: 'Font Awesome 5 Free'; font-weight: 900; position: absolute; right: 8px; opacity: 0.3; }
    .sortable.asc::after { content: '\f0de'; opacity: 1; color: #475569; }
    .sortable.desc::after { content: '\f0dd'; opacity: 1; color: #475569; }
    .table-responsive { position: relative; overflow-x: auto; -webkit-overflow-scrolling: touch; }
    .table-container { position: relative; }
    .scroll-indicator { position: absolute; top: 0; bottom: 20px; width: 40px; pointer-events: none; z-index: 10; transition: opacity 0.3s; }
    .scroll-indicator-left { left: 0; background: linear-gradient(to right, rgba(255,255,255,0.95), transparent); opacity: 0; }
    .scroll-indicator-right { right: 0; background: linear-gradient(to left, rgba(255,255,255,0.95), transparent); }
    .scroll-indicator-left.visible, .scroll-indicator-right.visible { opacity: 1; }
    /* Pin star styles */
    .art-sheet-page .pin-cell { width: 40px; text-align: center; }
    .art-sheet-page .pin-star {
        font-size: 18px;
        cursor: pointer;
        color: #cbd5e0;
        transition: all 0.2s ease;
    }
    .art-sheet-page .pin-star:hover {
        color: #f59e0b;
        transform: scale(1.2);
    }
    .art-sheet-page .pin-star.pinned {
        color: #f59e0b;
        text-shadow: 0 0 8px rgba(245, 158, 11, 0.3);
    }
    .art-sheet-page .pin-star.pinned:hover {
        color: #cbd5e0;
    }
</style>
@endsection

@section('content')
<div class="listing-container art-sheet-page">
    <section class="listing-section">
        <div class="listing-section-body">
            <div class="card art-sheet-card">
                {{-- Sticky: title + tabs + filter bar --}}
                <div class="art-sheet-sticky-header">
                    <div class="art-sheet-top-bar">
                        <h4 class="art-sheet-title"><i class="fas fa-gavel"></i> ART Submission and Hearing Files</h4>
                        <div class="art-sheet-top-bar-right">
                            <a href="{{ route('clients.index') }}" class="btn btn-theme btn-theme-sm" title="Back to Clients">
                                <i class="fas fa-arrow-left"></i> Back to Clients
                            </a>
                            <div class="sheet-tabs">
                                <a href="{{ route('clients.sheets.art') }}" class="sheet-tab active">
                                    <i class="fas fa-list"></i> List
                                </a>
                                @if(Auth::user() && in_array(Auth::user()->role, [1, 12]))
                                <a href="{{ route('clients.sheets.art.insights') }}" class="sheet-tab">
                                    <i class="fas fa-chart-bar"></i> Insights
                                </a>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="art-sheet-filter-bar">
                        <button type="button" class="btn btn-theme btn-theme-sm filter_btn">
                            <i class="fas fa-filter"></i> Filters
                            @if($activeFilterCount > 0)
                                <span class="active-filters-badge">{{ $activeFilterCount }}</span>
                            @endif
                        </button>
                        @if($activeFilterCount > 0)
                            <a href="{{ route('clients.sheets.art') }}" class="clear-filter-btn">
                                <i class="fas fa-undo"></i> Clear Filters
                            </a>
                        @endif
                        <div class="office-filter-section" style="background: #f8f9fa; border-radius: 5px; border: 1px solid #e3e6f0;">
                            <form action="{{ route('clients.sheets.art') }}" method="get" id="officeFilterForm">
                                <input type="hidden" name="per_page" value="{{ $perPage }}">
                                @foreach(request()->except(['office', 'page', 'per_page']) as $key => $value)
                                    @if(is_array($value))
                                        @foreach($value as $item)
                                            <input type="hidden" name="{{ $key }}[]" value="{{ $item }}">
                                        @endforeach
                                    @else
                                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                                    @endif
                                @endforeach
                                <div class="d-flex align-items-center flex-wrap">
                                    <label class="mb-0 mr-2" style="font-weight: 600; color: #6c757d; font-size: 13px;">
                                        <i class="fas fa-building"></i> Office:
                                    </label>
                                    @foreach(\App\Models\Branch::orderBy('office_name')->get() as $office)
                                        <div class="form-check form-check-inline mr-2 mb-0">
                                            <input class="form-check-input office-filter-checkbox" 
                                                   type="checkbox" 
                                                   name="office[]" 
                                                   value="{{ $office->id }}" 
                                                   id="office_{{ $office->id }}"
                                                   {{ is_array(request('office')) && in_array($office->id, request('office')) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="office_{{ $office->id }}" style="cursor: pointer; font-size: 13px;">
                                                {{ $office->office_name }}
                                            </label>
                                        </div>
                                    @endforeach
                                    @if(request('office'))
                                        <a href="{{ route('clients.sheets.art', array_merge(request()->except(['office', 'page']), ['per_page' => $perPage])) }}" 
                                           class="btn btn-sm btn-secondary ml-1">
                                            <i class="fas fa-times"></i> Clear
                                        </a>
                                    @endif
                                </div>
                            </form>
                        </div>
                        <label for="per_page" class="mb-0" style="font-size: 13px; margin-right: 4px;">Show:</label>
                        <select name="per_page" id="per_page" class="form-control per-page-select">
                            @foreach([10, 25, 50, 100, 200] as $option)
                                <option value="{{ $option }}" {{ $perPage == $option ? 'selected' : '' }}>{{ $option }}/page</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="card-body">

                    <div class="filter_panel {{ $activeFilterCount > 0 ? 'show' : '' }}">
                        <form action="{{ route('clients.sheets.art') }}" method="get" id="filterForm">
                            <input type="hidden" name="per_page" value="{{ $perPage }}">
                            <div class="row">
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="search">Search</label>
                                        <input type="text" name="search" value="{{ request('search') }}" class="form-control" placeholder="Name, CRM Ref, Other Ref" id="search">
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="status">Status</label>
                                        <select name="status" id="status" class="form-control">
                                            <option value="">All Statuses</option>
                                            @foreach($statusOptions as $value => $label)
                                                <option value="{{ $value }}" {{ request('status') == $value ? 'selected' : '' }}>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="agent">Agent</label>
                                        <select name="agent" id="agent" class="form-control">
                                            <option value="">All Agents</option>
                                            <option value="me" {{ request('agent') === 'me' ? 'selected' : '' }}>Me</option>
                                            @foreach($agents as $agent)
                                                <option value="{{ $agent->id }}" {{ request('agent') == $agent->id ? 'selected' : '' }}>{{ $agent->first_name }} {{ $agent->last_name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="from_date">From Date</label>
                                        <input type="text" name="from_date" value="{{ request('from_date') }}" class="form-control datepicker" placeholder="dd/mm/yyyy" id="from_date" autocomplete="off">
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="to_date">To Date</label>
                                        <input type="text" name="to_date" value="{{ request('to_date') }}" class="form-control datepicker" placeholder="dd/mm/yyyy" id="to_date" autocomplete="off">
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label>&nbsp;</label>
                                        <button type="submit" class="btn btn-primary btn-block">Apply</button>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-12">
                                    <a href="{{ route('clients.sheets.art') }}" class="btn btn-secondary">Reset</a>
                                </div>
                            </div>
                        </form>
                    </div>

                    <div class="table-container">
                        <div class="scroll-indicator scroll-indicator-left"></div>
                        <div class="scroll-indicator scroll-indicator-right visible"></div>
                        <div class="table-responsive" id="table-scroll-container">
                            <table class="table table-bordered table-hover art-table" id="art-sheet-table">
                                <thead style="position: static !important;">
                                    <tr>
                                        <th class="pin-cell" title="Click star to pin row to top"><i class="fas fa-star"></i></th>
                                        <th class="sortable {{ request('sort') == 'crm_ref' ? (request('direction') == 'asc' ? 'asc' : 'desc') : '' }}" data-sort="crm_ref">CRM Ref</th>
                                        <th class="sortable {{ request('sort') == 'other_reference' ? (request('direction') == 'asc' ? 'asc' : 'desc') : '' }}" data-sort="other_reference">Other Reference</th>
                                        <th class="sortable {{ request('sort') == 'client_name' ? (request('direction') == 'asc' ? 'asc' : 'desc') : '' }}" data-sort="client_name">Name</th>
                                        <th>Total Payment</th>
                                        <th>Pending Payment</th>
                                        <th class="sortable {{ request('sort') == 'deadline' ? (request('direction') == 'asc' ? 'asc' : 'desc') : '' }}" data-sort="deadline">Deadline</th>
                                        <th class="sortable {{ request('sort') == 'submission_date' ? (request('direction') == 'asc' ? 'asc' : 'desc') : '' }}" data-sort="submission_date">Submission Last Date</th>
                                        <th class="sortable {{ request('sort') == 'agent_name' ? (request('direction') == 'asc' ? 'asc' : 'desc') : '' }}" data-sort="agent_name">Agent Name</th>
                                        <th class="sortable {{ request('sort') == 'status' ? (request('direction') == 'asc' ? 'asc' : 'desc') : '' }}" data-sort="status">Status of the File</th>
                                        <th>Time</th>
                                        <th>Member Name</th>
                                        <th>Outcome</th>
                                        <th>Comments</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @if($rows->isEmpty())
                                        <tr>
                                            <td colspan="15" class="text-center text-muted py-4">
                                                <i class="fas fa-info-circle"></i> No ART records found. Add an ART matter type and assign matters to clients to see data here.
                                            </td>
                                        </tr>
                                    @else
                                        @foreach($rows as $row)
                                            @php
                                                $encodedClientId = base64_encode(convert_uuencode($row->client_id));
                                                $clientDetailUrl = route('clients.detail', [
                                                    'client_id' => $encodedClientId,
                                                    'client_unique_matter_ref_no' => $row->matter_id ?? '',
                                                ]);
                                                $otherRef = $row->other_reference ?? $row->department_reference ?? '—';
                                                $matterInternalId = $row->matter_internal_id ?? '';
                                            @endphp
                                            <tr style="cursor: pointer;" onclick="window.location.href='{{ $clientDetailUrl }}'">
                                                <td class="pin-cell" onclick="event.stopPropagation();">
                                                    <i class="fas fa-star pin-star {{ ($row->is_pinned ?? false) ? 'pinned' : '' }}"
                                                       data-client-id="{{ $row->client_id }}"
                                                       data-matter-internal-id="{{ $matterInternalId }}"
                                                       title="{{ ($row->is_pinned ?? false) ? 'Unpin from top' : 'Pin to top' }}"></i>
                                                </td>
                                                <td onclick="event.stopPropagation();"><a href="{{ $clientDetailUrl }}" class="art-link">{{ $row->crm_ref ?? '—' }}</a></td>
                                                <td onclick="event.stopPropagation();">{{ $otherRef }}</td>
                                                <td onclick="event.stopPropagation();"><a href="{{ $clientDetailUrl }}" class="art-link">{{ trim(($row->first_name ?? '') . ' ' . ($row->last_name ?? '')) ?: '—' }}</a></td>
                                                <td onclick="event.stopPropagation();">${{ $row->total_payment ?? '0.00' }}</td>
                                                <td onclick="event.stopPropagation();">${{ $row->pending_payment ?? '0.00' }}</td>
                                                <td onclick="event.stopPropagation();">{{ $row->deadline ? \Carbon\Carbon::parse($row->deadline)->format('d/m/Y') : '—' }}</td>
                                                <td onclick="event.stopPropagation();">{{ $row->submission_last_date ? \Carbon\Carbon::parse($row->submission_last_date)->format('d/m/Y') : '—' }}</td>
                                                <td onclick="event.stopPropagation();">{{ trim($row->agent_name ?? '') ?: '—' }}</td>
                                                <td onclick="event.stopPropagation();">
                                                    @if($row->status_of_file)
                                                        @php
                                                            $statusLabels = [
                                                                'submission_pending' => 'Submission Pending',
                                                                'submission_done' => 'Submission Done',
                                                                'hearing_invitation_sent' => 'Hearing Invitation Sent',
                                                                'waiting_for_hearing' => 'Waiting for Hearing',
                                                                'hearing' => 'Hearing',
                                                                'decided' => 'Decided',
                                                                'withdrawn' => 'Withdrawn',
                                                            ];
                                                            $label = $statusLabels[$row->status_of_file] ?? ucfirst(str_replace('_', ' ', $row->status_of_file));
                                                        @endphp
                                                        <span class="badge badge-{{ $row->status_of_file === 'submission_done' ? 'success' : ($row->status_of_file === 'hearing' ? 'primary' : 'secondary') }}">{{ $label }}</span>
                                                    @else
                                                        —
                                                    @endif
                                                </td>
                                                <td onclick="event.stopPropagation();">{{ $row->hearing_time ?? '—' }}</td>
                                                <td onclick="event.stopPropagation();">{{ $row->member_name ?? '—' }}</td>
                                                <td onclick="event.stopPropagation();">{{ $row->outcome ?? '—' }}</td>
                                                <td class="art-comments-cell" onclick="event.stopPropagation();" title="{{ $row->comments ?? '' }}">{{ Str::limit($row->comments ?? '—', 80) }}</td>
                                            </tr>
                                        @endforeach
                                    @endif
                                </tbody>
                            </table>
                        </div>
                    </div>

                    @if($rows->hasPages())
                        <div class="card-footer">
                            {!! $rows->appends(Request::except('page'))->render() !!}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </section>
</div>
@endsection

@push('scripts')
<script>
jQuery(document).ready(function($) {
    var $scrollContainer = $('#table-scroll-container');
    var $leftIndicator = $('.scroll-indicator-left');
    var $rightIndicator = $('.scroll-indicator-right');
    function updateScrollIndicators() {
        var scrollLeft = $scrollContainer.scrollLeft();
        var scrollWidth = $scrollContainer[0].scrollWidth;
        var clientWidth = $scrollContainer[0].clientWidth;
        var maxScroll = scrollWidth - clientWidth;
        $leftIndicator.toggleClass('visible', scrollLeft > 10);
        $rightIndicator.toggleClass('visible', scrollLeft < maxScroll - 10);
    }
    $scrollContainer.on('scroll', updateScrollIndicators);
    $(window).on('resize', updateScrollIndicators);
    setTimeout(updateScrollIndicators, 100);
    $scrollContainer.on('wheel', function(e) {
        if (e.originalEvent.deltaY !== 0 && !e.shiftKey) {
            e.preventDefault();
            this.scrollLeft += e.originalEvent.deltaY;
        }
    });
    $('#per_page').on('change', function() {
        var url = new URL(window.location.href);
        url.searchParams.set('per_page', $(this).val());
        url.searchParams.delete('page');
        window.location.href = url.toString();
    });
    $('.filter_btn').on('click', function() {
        $('.filter_panel').toggleClass('show');
    });
    if (typeof flatpickr !== 'undefined') {
        $('.datepicker').each(function() {
            if (!$(this).data('flatpickr')) {
                flatpickr(this, { dateFormat: 'd/m/Y', allowInput: true, locale: { firstDayOfWeek: 1 } });
            }
        });
    }
    $('.sortable').on('click', function() {
        var sortField = $(this).data('sort');
        var currentSort = '{{ request("sort") }}';
        var currentDirection = '{{ request("direction", "desc") }}';
        var newDirection = (currentSort === sortField && currentDirection === 'desc') ? 'asc' : 'desc';
        var url = new URL(window.location.href);
        url.searchParams.set('sort', sortField);
        url.searchParams.set('direction', newDirection);
        url.searchParams.delete('page');
        window.location.href = url.toString();
    });

    // Office filter auto-submit
    $('.office-filter-checkbox').on('change', function() {
        $('#officeFilterForm').submit();
    });

    // Force remove sticky positioning from table headers (override global CSS)
    $('#art-sheet-table thead th').css({
        'position': 'static',
        'top': 'auto',
        'z-index': 'auto'
    });

    // Handle star/pin clicks
    var artTable = document.getElementById('art-sheet-table');
    if (artTable) {
        artTable.addEventListener('click', function(e) {
            var star = e.target.closest('.pin-star');
            if (!star) return;

            e.preventDefault();
            e.stopPropagation();

            var $star = $(star);
            var clientId = $star.data('client-id');
            var matterInternalId = $star.data('matter-internal-id');

            if (!clientId || !matterInternalId) {
                if (typeof iziToast !== 'undefined') {
                    iziToast.warning({ title: 'Error', message: 'Cannot pin: missing data', position: 'topRight' });
                }
                return;
            }

            $star.css('pointer-events', 'none');

            $.ajax({
                url: '{{ route("clients.sheets.art.toggle-pin") }}',
                method: 'POST',
                data: {
                    client_id: clientId,
                    matter_internal_id: matterInternalId,
                    _token: '{{ csrf_token() }}'
                },
                success: function(response) {
                    if (response.success) {
                        $star.toggleClass('pinned');
                        $star.attr('title', response.is_pinned ? 'Unpin from top' : 'Pin to top');
                        if (typeof iziToast !== 'undefined') {
                            iziToast.success({
                                title: 'Success',
                                message: response.message,
                                position: 'topRight',
                                timeout: 2000
                            });
                        }
                        setTimeout(function() {
                            window.location.reload();
                        }, 500);
                    } else {
                        if (typeof iziToast !== 'undefined') {
                            iziToast.error({
                                title: 'Error',
                                message: response.message || 'Failed to update pin status',
                                position: 'topRight'
                            });
                        }
                        $star.css('pointer-events', 'auto');
                    }
                },
                error: function(xhr) {
                    if (typeof iziToast !== 'undefined') {
                        iziToast.error({
                            title: 'Error',
                            message: 'Failed to update pin status. Please try again.',
                            position: 'topRight'
                        });
                    }
                    $star.css('pointer-events', 'auto');
                }
            });
        }, true);
    }
});
</script>
@endpush
