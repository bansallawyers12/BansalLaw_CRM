@extends('layouts.crm_client_detail')
@section('title', 'Clients Closed Matters')

@section('styles')
<link rel="stylesheet" href="{{ asset('css/listing-container.css') }}">
<link rel="stylesheet" href="{{ asset('css/listing-flatpickr.css') }}">
<link rel="stylesheet" href="{{ asset('css/matters-list.css') }}">
<style>
    .matters-listing .badge-closed { background: #6b7280; color: white; }
    .matters-listing .badge-discontinued { background: #a83020; color: white; }
    .matters-listing .badge-complete { background: #1e7a52; color: white; }
    .matters-listing .closed-matter-checklist-summary {
        display: block;
        margin-top: 4px;
        font-size: 11px;
        color: var(--success, #1e7a52);
        font-weight: 600;
    }
    .matters-listing .closed-matter-reopen {
        display: inline-flex !important;
        align-items: center;
        gap: 6px;
        padding: 6px 12px !important;
        font-size: 0.8125rem !important;
        font-weight: 600 !important;
        border-radius: 8px !important;
        background: var(--navy, #1e3d60) !important;
        border: 1px solid var(--navy, #1e3d60) !important;
        color: #fff !important;
        min-width: auto !important;
        width: auto !important;
        box-shadow: none !important;
    }
    .matters-listing .closed-matter-reopen:hover {
        background: var(--sidebar-active, #3a6fa8) !important;
        border-color: var(--sidebar-active, #3a6fa8) !important;
    }
</style>
@include('crm.clients.partials.enhanced-date-filter-styles')
@endsection

@section('content')
@php
    $_cmViewer = Auth::user();
    $_cmEffectiveSa = $_cmViewer instanceof \App\Models\Staff && $_cmViewer->hasEffectiveSuperAdminPrivileges();
    $_cmCanReopen = $_cmEffectiveSa || ($_cmViewer instanceof \App\Models\Staff && $_cmViewer->hasCrmModule('45'));
    $_cmInsightsBtn = $_cmViewer && ($_cmEffectiveSa || in_array((int) ($_cmViewer->role ?? 0), [1, 12], true));
    $matterFilters = collect([
        'sel_matter_id' => request('sel_matter_id'),
        'client_id' => request('client_id'),
        'name' => request('name'),
        'sel_legal_practitioner' => request('sel_legal_practitioner'),
        'sel_person_responsible' => request('sel_person_responsible'),
        'sel_person_assisting' => request('sel_person_assisting'),
        'closure_status' => request('closure_status'),
        'quick_date_range' => request('quick_date_range'),
        'from_date' => request('from_date'),
        'to_date' => request('to_date'),
        'date_filter_field' => request('date_filter_field') !== 'created_at' ? request('date_filter_field') : null,
    ]);
    $activeMatterFilters = $matterFilters->filter(fn($v) => $v !== null && $v !== '')->count();
    $totalCount = method_exists($lists, 'total') ? $lists->total() : (int) ($totalData ?? 0);
    $currentPage = method_exists($lists, 'currentPage') ? $lists->currentPage() : 1;
    $lastPage = method_exists($lists, 'lastPage') ? $lists->lastPage() : 1;
    $loadedCount = method_exists($lists, 'count') ? $lists->count() : 0;

    $staffName = function ($staff) {
        if (! $staff) {
            return '—';
        }
        $name = trim(($staff->first_name ?? '') . ' ' . ($staff->last_name ?? ''));
        return $name !== '' ? $name : '—';
    };

    $staffIds = collect($lists->items())->flatMap(function ($row) {
        return [
            $row->sel_legal_practitioner ?? null,
            $row->sel_person_responsible ?? null,
            $row->sel_person_assisting ?? null,
            $row->closed_by ?? null,
        ];
    })->filter()->unique()->values()->all();
    $staffById = $staffIds
        ? \App\Models\Staff::query()->whereIn('id', $staffIds)->get(['id', 'first_name', 'last_name'])->keyBy('id')
        : collect();
    $officeIds = collect($lists->items())->pluck('office_id')->filter()->unique()->values()->all();
    $officesById = $officeIds
        ? \App\Models\Branch::query()->whereIn('id', $officeIds)->get()->keyBy('id')
        : collect();
@endphp
<div id="matters-listing-root"
     class="listing-container matters-listing"
     data-infinite-scroll="1"
     data-current-page="{{ $currentPage }}"
     data-last-page="{{ $lastPage }}"
     data-per-page="20">
    <section class="listing-section">
        <div class="listing-section-body">
            @include('../Elements/flash-message')

            <div class="card">
                <div class="custom-error-msg"></div>
                <div class="card-header">
                    <div class="matters-page-header">
                        <div class="matters-page-header__title">
                            <span class="matters-page-header__icon" aria-hidden="true">
                                <i class="fa-solid fa-box-archive"></i>
                            </span>
                            <div>
                                <h4>Closed Matters</h4>
                                <p class="matters-page-header__subtitle">
                                    {{ number_format($totalCount) }} closed {{ Str::plural('matter', $totalCount) }}
                                    &middot; Review history and reopen when needed
                                </p>
                            </div>
                        </div>
                        <div class="card-header-actions">
                            @if($_cmInsightsBtn)
                            <a href="{{ route('clients.insights', ['section' => 'matters']) }}" class="btn btn-theme btn-theme-sm" title="Matter Insights">
                                <i class="fa-solid fa-chart-line"></i> Insights
                            </a>
                            @endif
                            <a href="javascript:;" class="btn btn-theme btn-theme-sm filter_btn{{ $activeMatterFilters > 0 ? ' filter_btn--active' : '' }}" id="filterToggleBtn">
                                <i class="fa-solid fa-filter"></i> Filter
                                @if($activeMatterFilters > 0)
                                    <span class="filter-count-badge">{{ $activeMatterFilters }}</span>
                                @endif
                            </a>
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    <div class="matters-toolbar">
                        <ul class="matters-tabs nav" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('clients.clientsmatterslist') }}">Active Matters</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link active" href="{{ route('clients.closedmatterslist') }}">Closed Matters</a>
                            </li>
                        </ul>
                    </div>

                    @php
                        // $matterFilters / $activeMatterFilters defined above
                    @endphp
                    <div class="filter_panel{{ $activeMatterFilters > 0 ? ' is-open' : '' }}" id="matterFilterPanel">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                            <h4 class="mb-0">
                                Filter closed matters
                                @if($activeMatterFilters > 0)
                                    <span class="active-filters-badge"><i class="fa-solid fa-filter"></i> {{ $activeMatterFilters }} active</span>
                                @endif
                            </h4>
                            @if($activeMatterFilters > 0)
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="clearMatterFilters"><i class="fa-solid fa-rotate-left"></i> Clear filters</button>
                            @endif
                        </div>
                        <form action="{{ route('clients.closedmatterslist') }}" method="get" id="matterFilterForm">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="sel_matter_id" class="col-form-label" style="color:#4a5568 !important;">Matter</label>
                                        <select class="form-control" name="sel_matter_id" id="sel_matter_id">
                                            <option value="">Select Matter</option>
                                            @foreach(\App\Models\Matter::orderBy('title', 'asc')->get() as $matter)
                                                <option value="{{ $matter->id }}" {{ request('sel_matter_id') == $matter->id ? 'selected' : '' }}>{{ $matter->title }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="client_id" class="col-form-label" style="color:#4a5568 !important;">Client ID</label>
                                        <input type="text" name="client_id" value="{{ request('client_id') }}" class="form-control" autocomplete="off" placeholder="Client ID" id="client_id">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="name" class="col-form-label" style="color:#4a5568 !important;">Client Name</label>
                                        <input type="text" name="name" value="{{ request('name') }}" class="form-control" autocomplete="off" placeholder="Name" id="name">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="sel_legal_practitioner" class="col-form-label" style="color:#4a5568 !important;">Legal Practitioner</label>
                                        <select class="form-control" name="sel_legal_practitioner" id="sel_legal_practitioner">
                                            <option value="">All Agents</option>
                                            @foreach(($teamMembers ?? collect()) as $member)
                                                <option value="{{ $member->id }}" {{ request('sel_legal_practitioner') == $member->id ? 'selected' : '' }}>{{ $member->first_name }} {{ $member->last_name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="sel_person_responsible" class="col-form-label" style="color:#4a5568 !important;">Person Responsible</label>
                                        <select class="form-control" name="sel_person_responsible" id="sel_person_responsible">
                                            <option value="">All</option>
                                            @foreach(($teamMembers ?? collect()) as $member)
                                                <option value="{{ $member->id }}" {{ request('sel_person_responsible') == $member->id ? 'selected' : '' }}>{{ $member->first_name }} {{ $member->last_name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="sel_person_assisting" class="col-form-label" style="color:#4a5568 !important;">Person Assisting</label>
                                        <select class="form-control" name="sel_person_assisting" id="sel_person_assisting">
                                            <option value="">All</option>
                                            @foreach(($teamMembers ?? collect()) as $member)
                                                <option value="{{ $member->id }}" {{ request('sel_person_assisting') == $member->id ? 'selected' : '' }}>{{ $member->first_name }} {{ $member->last_name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="closure_status" class="col-form-label" style="color:#4a5568 !important;">Closure Status</label>
                                        <select class="form-control" name="closure_status" id="closure_status">
                                            <option value="">All closed matters</option>
                                            <option value="complete" {{ request('closure_status') === 'complete' ? 'selected' : '' }}>Complete</option>
                                            <option value="discontinued" {{ request('closure_status') === 'discontinued' ? 'selected' : '' }}>Discontinued</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="date-filter-section mt-3">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="date_filter_field" class="col-form-label" style="color:#4a5568 !important;">Date Field</label>
                                            <select name="date_filter_field" id="date_filter_field" class="form-control">
                                                <option value="created_at" {{ request('date_filter_field', 'created_at') === 'created_at' ? 'selected' : '' }}>Created Date</option>
                                                <option value="updated_at" {{ request('date_filter_field') === 'updated_at' ? 'selected' : '' }}>Closed / Last Updated</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <input type="hidden" name="quick_date_range" id="matter_quick_date_range" value="{{ request('quick_date_range') }}">
                                @php
                                    $quickFilters = ['today'=>'Today','this_week'=>'This Week','this_month'=>'This Month','last_month'=>'Last Month','last_30_days'=>'Last 30 Days','last_90_days'=>'Last 90 Days','this_year'=>'This Year','last_year'=>'Last Year'];
                                @endphp
                                <div class="quick-filters">
                                    @foreach($quickFilters as $key => $label)
                                        <span class="quick-filter-chip matter-quick-filter {{ request('quick_date_range') === $key ? 'active' : '' }}" data-filter="{{ $key }}"><i class="fa-solid fa-calendar"></i> {{ $label }}</span>
                                    @endforeach
                                </div>
                                <div class="divider-text">Or Custom Range</div>
                                <div class="date-range-wrapper">
                                    <div class="form-group">
                                        <label for="from_date" class="col-form-label" style="color:#4a5568 !important;">From Date</label>
                                        <input type="date" name="from_date" id="from_date" value="{{ request('from_date') }}" class="form-control">
                                    </div>
                                    <span class="date-range-arrow">&rarr;</span>
                                    <div class="form-group">
                                        <label for="to_date" class="col-form-label" style="color:#4a5568 !important;">To Date</label>
                                        <input type="date" name="to_date" id="to_date" value="{{ request('to_date') }}" class="form-control">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-12 text-center">
                                    <button type="submit" class="btn btn-primary btn-theme-lg me-3">Search</button>
                                    <a class="btn btn-info" href="{{ route('clients.closedmatterslist') }}">Reset</a>
                                </div>
                            </div>
                        </form>
                    </div>
                    @php
                        $currentSort = request('sort', 'cm.id');
                        $currentDirection = request('direction', 'desc');
                        $nextDirection = fn($col) => ($currentSort === $col && $currentDirection === 'asc') ? 'desc' : 'asc';
                        $buildSortUrl = function($column) use ($nextDirection) {
                            $q = request()->except('page');
                            $q['sort'] = $column;
                            $q['direction'] = $nextDirection($column);
                            return request()->url() . '?' . http_build_query($q);
                        };
                        $sortIcon = function($column) use ($currentSort, $currentDirection) {
                            if ($currentSort !== $column) return '<i class="fa-solid fa-sort text-muted"></i>';
                            return $currentDirection === 'asc' ? '<i class="fa-solid fa-sort-up"></i>' : '<i class="fa-solid fa-sort-down"></i>';
                        };
                    @endphp
                    @if($totalCount > 0)
                    <div class="matters-results-bar">
                        <span>
                            Loaded <strong data-loaded-count>{{ number_format($loadedCount) }}</strong>
                            of <strong>{{ number_format($totalCount) }}</strong> matters
                        </span>
                        @if($activeMatterFilters > 0)
                            <span class="matters-results-bar__filtered"><i class="fa-solid fa-filter"></i> Filtered</span>
                        @endif
                        @if($lastPage > 1)
                            <span class="matters-results-bar__hint"><i class="fa-solid fa-arrow-down"></i> Scroll for more</span>
                        @endif
                    </div>
                    @endif
                    <div class="table-responsive matters-table-wrap">
                        <table class="table matters-table">
                            <thead>
                                <tr>
                                    <th class="sortable-header"><a href="{{ $buildSortUrl('ma.title') }}">Matter {!! $sortIcon('ma.title') !!}</a></th>
                                    <th class="sortable-header"><a href="{{ $buildSortUrl('ad.first_name') }}">Client {!! $sortIcon('ad.first_name') !!}</a></th>
                                    <th>Team</th>
                                    <th>Status</th>
                                    <th class="sortable-header"><a href="{{ $buildSortUrl('cm.updated_at') }}">Closed {!! $sortIcon('cm.updated_at') !!}</a></th>
                                    <th>Reason</th>
                                    <th>Office</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="tdata">
                                @if($totalCount !== 0)
                                    @foreach($lists as $list)
                                        @php
                                            $legal_practitioner_info = $staffById->get($list->sel_legal_practitioner);
                                            $person_responsible = $staffById->get($list->sel_person_responsible);
                                            $person_assisting = $staffById->get($list->sel_person_assisting);
                                            $closed_by_info = $list->closed_by ? $staffById->get($list->closed_by) : null;
                                            $matter_office = $list->office_id ? $officesById->get($list->office_id) : null;
                                            $statusLabel = \App\Support\MatterCompletionChecklist::closureStatusLabel($list);
                                            $statusClass = \App\Support\MatterCompletionChecklist::closureStatusBadgeClass($list);
                                            $isDiscontinued = ($list->matter_status ?? 1) == 0;
                                            $isComplete = $isDiscontinued && \App\Support\MatterCompletionChecklist::isCompleteReason($list->discontinue_reason ?? null);
                                            $checklist = \App\Support\MatterCompletionChecklist::parseStored($list->matter_completion_checklist ?? null);
                                            $checklistChecked = \App\Support\MatterCompletionChecklist::checkedLabels($checklist);
                                            $checklistTotal = \App\Support\MatterCompletionChecklist::totalCount();
                                            $displayReason = \App\Support\MatterCompletionChecklist::displayReason($list);
                                            $closedAt = ($isDiscontinued && ! empty($list->updated_at))
                                                ? date('d/m/Y H:i', strtotime($list->updated_at))
                                                : '—';
                                            $closedByName = $closed_by_info
                                                ? $staffName($closed_by_info)
                                                : ($isDiscontinued ? 'Unknown' : '—');
                                            $clientDetailUrl = URL::to('/clients/detail/' . base64_encode(convert_uuencode($list->client_id)));
                                            $matterDetailUrl = URL::to('/clients/detail/' . base64_encode(convert_uuencode($list->client_id)) . '/' . $list->client_unique_matter_no);
                                            $clientName = trim(($list->first_name ?? '') . ' ' . ($list->last_name ?? ''));
                                            if ($clientName === '') {
                                                $clientName = '—';
                                            }
                                        @endphp
                                        <tr class="matter-data-row" id="id_{{ $list->id }}">
                                            <td>
                                                <a class="matter-cell-primary" href="{{ $matterDetailUrl }}" title="Open matter">
                                                    {{ $list->title ?: 'Untitled matter' }}
                                                </a>
                                                <span class="matter-cell-meta matter-cell-meta--chip">{{ $list->client_unique_matter_no ?: 'No matter no.' }}</span>
                                            </td>
                                            <td>
                                                <a class="matter-cell-primary" href="{{ $clientDetailUrl }}" title="Open client">
                                                    {{ $clientName }}
                                                </a>
                                                <span class="matter-cell-meta">{{ $list->client_unique_id ?: ('#' . $list->client_id) }}</span>
                                            </td>
                                            <td>
                                                <div class="matter-team">
                                                    <div class="matter-team-row">
                                                        <span class="role" title="Legal practitioner">LP</span>
                                                        <span class="name">{{ $staffName($legal_practitioner_info) }}</span>
                                                    </div>
                                                    <div class="matter-team-row">
                                                        <span class="role" title="Person responsible">PR</span>
                                                        <span class="name">{{ $staffName($person_responsible) }}</span>
                                                    </div>
                                                    <div class="matter-team-row">
                                                        <span class="role" title="Person assisting">PA</span>
                                                        <span class="name">{{ $staffName($person_assisting) }}</span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge {{ $statusClass }}">{{ $statusLabel }}</span>
                                            </td>
                                            <td>
                                                <span class="matter-date">{{ $closedAt }}</span>
                                                <span class="matter-cell-meta">{{ $closedByName }}</span>
                                            </td>
                                            <td>
                                                <span class="matter-cell-primary" style="font-weight:600;">{{ $displayReason }}</span>
                                                @if(! empty($list->discontinue_notes))
                                                    <span class="matter-cell-meta" title="{{ $list->discontinue_notes }}">{{ Str::limit($list->discontinue_notes, 80) }}</span>
                                                @endif
                                                @if($isComplete && count($checklistChecked) > 0)
                                                    <span class="closed-matter-checklist-summary" title="{{ e(implode(', ', $checklistChecked)) }}">
                                                        <i class="fa-solid fa-circle-check"></i>
                                                        Checklist {{ count($checklistChecked) }}/{{ $checklistTotal }}
                                                    </span>
                                                @elseif($isComplete)
                                                    <span class="matter-cell-meta">Checklist not recorded</span>
                                                @endif
                                            </td>
                                            <td>
                                                <div class="matter-office">
                                                    @if($matter_office)
                                                        <span class="matter-office-badge is-set">
                                                            <i class="fa-solid fa-building"></i> {{ $matter_office->office_name }}
                                                        </span>
                                                    @else
                                                        <span class="matter-office-badge is-unset">
                                                            <i class="fa-solid fa-circle-exclamation"></i> Not assigned
                                                        </span>
                                                    @endif
                                                </div>
                                            </td>
                                            <td>
                                                <div class="matter-actions">
                                                    <a href="{{ $matterDetailUrl }}" class="matter-action-btn" title="Open matter">
                                                        <i class="fa-solid fa-arrow-up-right-from-square"></i>
                                                    </a>
                                                    @if($isDiscontinued)
                                                        @if($_cmCanReopen)
                                                            @if($list->reopen_requested_by)
                                                                <span class="badge bg-warning text-dark me-1" title="A team member requested reopen">
                                                                    <i class="fa-solid fa-clock"></i> Requested
                                                                </span>
                                                            @endif
                                                            <button class="btn btn-primary btn-sm closed-matter-reopen" type="button" data-matter-id="{{ $list->id }}">
                                                                <i class="fa-solid fa-arrow-rotate-right"></i> Reopen
                                                            </button>
                                                        @elseif($list->reopen_requested_by)
                                                            <button class="btn btn-secondary btn-sm" disabled type="button" title="Reopen Requested">
                                                                <i class="fa-solid fa-clock"></i> Requested
                                                            </button>
                                                            @if((int) $list->reopen_requested_by === (int) ($_cmViewer->id ?? 0))
                                                                <button class="btn btn-outline-danger btn-sm closed-matter-cancel-reopen-request" type="button" data-matter-id="{{ $list->id }}" title="Cancel reopen request">
                                                                    <i class="fa-solid fa-xmark"></i> Cancel
                                                                </button>
                                                            @endif
                                                        @else
                                                            <button class="btn btn-warning btn-sm closed-matter-request-reopen" type="button" data-matter-id="{{ $list->id }}" title="Request Admin to Reopen Matter">
                                                                <i class="fa-solid fa-hand-paper"></i> Request
                                                            </button>
                                                        @endif
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                @else
                                    <tr>
                                        <td colspan="8">
                                            <div class="matters-empty">
                                                <i class="fa-regular fa-folder-open"></i>
                                                <p>No closed matters found</p>
                                                <span>Try clearing filters or searching with a different client name.</span>
                                                @if($activeMatterFilters > 0)
                                                    <div class="mt-3">
                                                        <a href="{{ route('clients.closedmatterslist') }}" class="btn btn-theme btn-theme-sm">Clear filters</a>
                                                    </div>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                    <div class="matters-infinite-loader" id="mattersInfiniteLoader" hidden aria-live="polite">
                        <span class="matters-infinite-loader__spinner" aria-hidden="true"></span>
                        <span>Loading more matters...</span>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/crm/clients/matters-listing-infinite.js') }}?v={{ filemtime(public_path('js/crm/clients/matters-listing-infinite.js')) }}"></script>
<script>
jQuery(document).ready(function($){
    $('.matter-quick-filter').on('click', function(){
        var filter = $(this).data('filter');
        $('#matter_quick_date_range').val(filter);
        $('#from_date, #to_date').val('');
        $('#matterFilterForm').submit();
    });
    $('#from_date, #to_date').on('change', function(){ $('#matter_quick_date_range').val(''); });
    $('#clearMatterFilters').on('click', function(){
        window.location.href = "{{ route('clients.closedmatterslist') }}";
    });
    $('.listing-container .filter_btn, #filterToggleBtn').on('click', function(){
        $('#matterFilterPanel').toggleClass('is-open');
    });

    $(document).on('click', '.closed-matter-reopen', function(e){
        e.preventDefault();
        var matterId = $(this).data('matter-id');
        if (!matterId) return;
        if (!confirm('Reopen this matter? It will be moved back to active matters.')) return;
        var $btn = $(this);
        $btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> Reopening...');
        $.ajax({
            url: '{{ route("clients.matter.reopen") }}',
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'), 'Accept': 'application/json' },
            data: JSON.stringify({ matter_id: matterId, source: 'matter_list' }),
            success: function(resp){
                if (resp.status && resp.redirect_url) {
                    window.location.href = resp.redirect_url;
                } else if (resp.status) {
                    window.location.reload();
                } else {
                    crmAlert(resp.message || 'Failed to reopen matter.');
                    $btn.prop('disabled', false).html('<i class="fa-solid fa-arrow-rotate-right"></i> Reopen');
                }
            },
            error: function(){
                crmAlert('An error occurred. Please try again.');
                $btn.prop('disabled', false).html('<i class="fa-solid fa-arrow-rotate-right"></i> Reopen');
            }
        });
    });

    $(document).on('click', '.closed-matter-request-reopen', function(e){
        e.preventDefault();
        var matterId = $(this).data('matter-id');
        if (!matterId) return;
        if (!confirm('Send a request to the admin to reopen this matter?')) return;
        var $btn = $(this);
        $btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> Requesting...');
        $.ajax({
            url: '{{ route("clients.matter.request-reopen") }}',
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'), 'Accept': 'application/json' },
            data: JSON.stringify({ matter_id: matterId, source: 'matter_list' }),
            success: function(resp){
                if (resp.status) {
                    crmAlert(resp.message || 'Reopen request has been sent to admins.');
                    window.location.reload();
                } else {
                    crmAlert(resp.message || 'Failed to request reopen.');
                    $btn.prop('disabled', false).html('<i class="fa-solid fa-hand-paper"></i> Request');
                }
            },
            error: function(){
                crmAlert('An error occurred. Please try again.');
                $btn.prop('disabled', false).html('<i class="fa-solid fa-hand-paper"></i> Request');
            }
        });
    });

    $(document).on('click', '.closed-matter-cancel-reopen-request', function(e){
        e.preventDefault();
        var matterId = $(this).data('matter-id');
        if (!matterId) return;
        if (!confirm('Cancel this reopen request?')) return;
        var $btn = $(this);
        $btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> Cancelling...');
        $.ajax({
            url: '{{ route("clients.matter.cancel-reopen-request") }}',
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'), 'Accept': 'application/json' },
            data: JSON.stringify({ matter_id: matterId, source: 'matter_list' }),
            success: function(resp){
                if (resp.status) {
                    crmAlert(resp.message || 'Reopen request has been cancelled.');
                    window.location.reload();
                } else {
                    crmAlert(resp.message || 'Failed to cancel reopen request.');
                    $btn.prop('disabled', false).html('<i class="fa-solid fa-xmark"></i> Cancel');
                }
            },
            error: function(){
                crmAlert('An error occurred. Please try again.');
                $btn.prop('disabled', false).html('<i class="fa-solid fa-xmark"></i> Cancel');
            }
        });
    });
});
</script>
@endpush

