@extends('layouts.crm_client_detail')
@section('title', 'Clients Matters List')

@section('styles')
<link rel="stylesheet" href="{{ asset('css/listing-container.css') }}">
<link rel="stylesheet" href="{{ asset('css/listing-flatpickr.css') }}">
<link rel="stylesheet" href="{{ asset('css/matters-list.css') }}">
@include('crm.clients.partials.enhanced-date-filter-styles')
@endsection

@section('content')
@php
    $_cmViewer = Auth::user();
    $_cmEffectiveSa = $_cmViewer instanceof \App\Models\Staff && $_cmViewer->hasEffectiveSuperAdminPrivileges();
    $_cmInsightsBtn = $_cmViewer && ($_cmEffectiveSa || in_array((int) ($_cmViewer->role ?? 0), [1, 12], true));

    $matterFilters = collect([
        'sel_matter_id' => request('sel_matter_id'),
        'client_id' => request('client_id'),
        'name' => request('name'),
        'sel_legal_practitioner' => request('sel_legal_practitioner'),
        'sel_person_responsible' => request('sel_person_responsible'),
        'sel_person_assisting' => request('sel_person_assisting'),
        'quick_date_range' => request('quick_date_range'),
        'from_date' => request('from_date'),
        'to_date' => request('to_date'),
        'date_filter_field' => request('date_filter_field') !== 'created_at' ? request('date_filter_field') : null,
    ]);
    $activeMatterFilters = $matterFilters->filter(fn ($value) => $value !== null && $value !== '')->count();

    $currentSort = request('sort', 'cm.id');
    $currentDirection = request('direction', 'desc');
    $nextDirection = function ($column) use ($currentSort, $currentDirection) {
        return ($currentSort === $column && $currentDirection === 'asc') ? 'desc' : 'asc';
    };
    $buildSortUrl = function ($column) use ($nextDirection) {
        $query = request()->except('page');
        $query['sort'] = $column;
        $query['direction'] = $nextDirection($column);
        return request()->url() . '?' . http_build_query($query);
    };
    $sortIcon = function ($column) use ($currentSort, $currentDirection) {
        if ($currentSort !== $column) {
            return '<i class="fa-solid fa-sort text-muted"></i>';
        }
        return $currentDirection === 'asc'
            ? '<i class="fa-solid fa-sort-up"></i>'
            : '<i class="fa-solid fa-sort-down"></i>';
    };

    $staffName = function ($staff) {
        if (! $staff) {
            return '—';
        }
        $name = trim(($staff->first_name ?? '') . ' ' . ($staff->last_name ?? ''));
        return $name !== '' ? $name : '—';
    };

    $totalCount = method_exists($lists, 'total') ? $lists->total() : (int) ($totalData ?? 0);
    $currentPage = method_exists($lists, 'currentPage') ? $lists->currentPage() : 1;
    $lastPage = method_exists($lists, 'lastPage') ? $lists->lastPage() : 1;
    $loadedCount = method_exists($lists, 'count') ? $lists->count() : 0;

    $staffIds = collect($lists->items())->flatMap(function ($row) {
        return [
            $row->sel_legal_practitioner ?? null,
            $row->sel_person_responsible ?? null,
            $row->sel_person_assisting ?? null,
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
                                <i class="fa-solid fa-folder-open"></i>
                            </span>
                            <div>
                                <h4>Client Matters</h4>
                                <p class="matters-page-header__subtitle">
                                    {{ number_format($totalCount) }} active {{ Str::plural('matter', $totalCount) }}
                                    &middot; Search, filter and open matter files
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
                                <a class="nav-link active" href="{{ route('clients.clientsmatterslist') }}">Active Matters</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('clients.closedmatterslist') }}">Closed Matters</a>
                            </li>
                        </ul>
                    </div>

                    <div class="filter_panel{{ $activeMatterFilters > 0 ? ' is-open' : '' }}" id="matterFilterPanel">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                            <h4 class="mb-0">
                                Filter matters
                                @if($activeMatterFilters > 0)
                                    <span class="active-filters-badge">
                                        <i class="fa-solid fa-filter"></i> {{ $activeMatterFilters }} active
                                    </span>
                                @endif
                            </h4>
                            @if($activeMatterFilters > 0)
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="clearMatterFilters">
                                    <i class="fa-solid fa-rotate-left"></i> Clear filters
                                </button>
                            @endif
                        </div>

                        <form action="{{ route('clients.clientsmatterslist') }}" method="get" id="matterFilterForm">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <div class="form-group mb-0">
                                        <label for="sel_matter_id">Matter type</label>
                                        <select class="form-control" name="sel_matter_id" id="sel_matter_id">
                                            <option value="">All matter types</option>
                                            @foreach(\App\Models\Matter::orderBy('title', 'asc')->get() as $matter)
                                                <option value="{{ $matter->id }}" {{ request('sel_matter_id') == $matter->id ? 'selected' : '' }}>{{ $matter->title }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group mb-0">
                                        <label for="client_id">Client ID</label>
                                        <input type="text" name="client_id" value="{{ request('client_id') }}" class="form-control" autocomplete="off" placeholder="e.g. TEST2600026" id="client_id">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group mb-0">
                                        <label for="name">Client name</label>
                                        <input type="text" name="name" value="{{ request('name') }}" class="form-control" autocomplete="off" placeholder="Search by name" id="name">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group mb-0">
                                        <label for="sel_legal_practitioner">Legal practitioner</label>
                                        <select class="form-control" name="sel_legal_practitioner" id="sel_legal_practitioner">
                                            <option value="">Anyone</option>
                                            @foreach(($teamMembers ?? collect()) as $member)
                                                <option value="{{ $member->id }}" {{ request('sel_legal_practitioner') == $member->id ? 'selected' : '' }}>
                                                    {{ $member->first_name }} {{ $member->last_name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group mb-0">
                                        <label for="sel_person_responsible">Person responsible</label>
                                        <select class="form-control" name="sel_person_responsible" id="sel_person_responsible">
                                            <option value="">Anyone</option>
                                            @foreach(($teamMembers ?? collect()) as $member)
                                                <option value="{{ $member->id }}" {{ request('sel_person_responsible') == $member->id ? 'selected' : '' }}>
                                                    {{ $member->first_name }} {{ $member->last_name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group mb-0">
                                        <label for="sel_person_assisting">Person assisting</label>
                                        <select class="form-control" name="sel_person_assisting" id="sel_person_assisting">
                                            <option value="">Anyone</option>
                                            @foreach(($teamMembers ?? collect()) as $member)
                                                <option value="{{ $member->id }}" {{ request('sel_person_assisting') == $member->id ? 'selected' : '' }}>
                                                    {{ $member->first_name }} {{ $member->last_name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="date-filter-section mt-3">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="date_filter_field"><i class="fa-solid fa-calendar-days"></i> Date field</label>
                                            <select name="date_filter_field" id="date_filter_field" class="form-control">
                                                <option value="created_at" {{ request('date_filter_field', 'created_at') === 'created_at' ? 'selected' : '' }}>Created date</option>
                                                <option value="updated_at" {{ request('date_filter_field') === 'updated_at' ? 'selected' : '' }}>Last updated</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <input type="hidden" name="quick_date_range" id="matter_quick_date_range" value="{{ request('quick_date_range') }}">
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
                                <div class="quick-filters">
                                    @foreach($quickFilters as $key => $label)
                                        <span class="quick-filter-chip matter-quick-filter {{ request('quick_date_range') === $key ? 'active' : '' }}" data-filter="{{ $key }}">
                                            <i class="fa-solid fa-calendar"></i> {{ $label }}
                                        </span>
                                    @endforeach
                                </div>
                                <div class="divider-text">Or custom range</div>
                                <div class="date-range-wrapper">
                                    <div class="form-group">
                                        <label for="from_date">From</label>
                                        <input type="date" name="from_date" id="from_date" value="{{ request('from_date') }}" class="form-control">
                                    </div>
                                    <span class="date-range-arrow" aria-hidden="true">&rarr;</span>
                                    <div class="form-group">
                                        <label for="to_date">To</label>
                                        <input type="date" name="to_date" id="to_date" value="{{ request('to_date') }}" class="form-control">
                                    </div>
                                </div>
                            </div>

                            <div class="text-center mt-3">
                                <div class="filter-buttons-container">
                                    <button type="submit" class="btn btn-primary btn-theme-lg me-2">
                                        <i class="fa-solid fa-magnifying-glass"></i> Apply filters
                                    </button>
                                    <a class="btn btn-outline-secondary" href="{{ route('clients.clientsmatterslist') }}">Reset</a>
                                </div>
                            </div>
                        </form>
                    </div>

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
                                    <th class="sortable-header">
                                        <a href="{{ $buildSortUrl('ma.title') }}">Matter {!! $sortIcon('ma.title') !!}</a>
                                    </th>
                                    <th class="sortable-header">
                                        <a href="{{ $buildSortUrl('ad.first_name') }}">Client {!! $sortIcon('ad.first_name') !!}</a>
                                    </th>
                                    <th class="sortable-header">
                                        <a href="{{ $buildSortUrl('ad.dob') }}">DOB {!! $sortIcon('ad.dob') !!}</a>
                                    </th>
                                    <th>Team</th>
                                    <th class="sortable-header">
                                        <a href="{{ $buildSortUrl('cm.created_at') }}">Created {!! $sortIcon('cm.created_at') !!}</a>
                                    </th>
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
                                            $matter_office = $list->office_id ? $officesById->get($list->office_id) : null;
                                            $clientDetailUrl = URL::to('/clients/detail/' . base64_encode(convert_uuencode($list->client_id)));
                                            $matterDetailUrl = URL::to('/clients/detail/' . base64_encode(convert_uuencode($list->client_id)) . '/' . $list->client_unique_matter_no);
                                            $dobDisplay = ! empty($list->dob) && strtotime($list->dob)
                                                ? date('d/m/Y', strtotime($list->dob))
                                                : '—';
                                            $createdDisplay = ! empty($list->created_at)
                                                ? date('d/m/Y', strtotime($list->created_at))
                                                : '—';
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
                                            <td><span class="matter-date">{{ $dobDisplay }}</span></td>
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
                                            <td><span class="matter-date">{{ $createdDisplay }}</span></td>
                                            <td>
                                                <div class="matter-office">
                                                    @if($matter_office)
                                                        <span class="matter-office-badge is-set">
                                                            <i class="fa-solid fa-building"></i> {{ $matter_office->office_name }}
                                                        </span>
                                                        <button type="button"
                                                            class="btn btn-sm btn-outline-primary matter-office-btn edit-office-btn"
                                                            data-matter-id="{{ $list->id }}"
                                                            data-matter-no="{{ $list->client_unique_matter_no }}"
                                                            data-matter-title="{{ $list->title }}"
                                                            data-office-id="{{ $list->office_id }}"
                                                            title="Change office">
                                                            <i class="fa-solid fa-pen-to-square"></i> Change
                                                        </button>
                                                    @else
                                                        <span class="matter-office-badge is-unset">
                                                            <i class="fa-solid fa-circle-exclamation"></i> Not assigned
                                                        </span>
                                                        <button type="button"
                                                            class="btn btn-sm btn-success matter-office-btn assign-office-btn"
                                                            data-matter-id="{{ $list->id }}"
                                                            data-matter-no="{{ $list->client_unique_matter_no }}"
                                                            data-matter-title="{{ $list->title }}"
                                                            title="Assign office">
                                                            <i class="fa-solid fa-plus"></i> Assign
                                                        </button>
                                                    @endif
                                                </div>
                                            </td>
                                            <td>
                                                <div class="matter-actions">
                                                    <a href="{{ $matterDetailUrl }}" class="matter-action-btn" title="Open matter">
                                                        <i class="fa-solid fa-arrow-up-right-from-square"></i>
                                                    </a>
                                                    @if($_cmEffectiveSa)
                                                    <div class="dropdown">
                                                        <button class="matter-action-btn btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}' aria-expanded="false" title="More actions">
                                                            <i class="fa-solid fa-ellipsis"></i>
                                                        </button>
                                                        <ul class="dropdown-menu dropdown-menu-end">
                                                            <li>
                                                                <a class="dropdown-item text-danger" href="javascript:;" onclick="deleteAction({{ $list->id }}, 'client_matters')">
                                                                    <i class="fa-solid fa-trash"></i> Delete matter
                                                                </a>
                                                            </li>
                                                        </ul>
                                                    </div>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                @else
                                    <tr>
                                        <td colspan="7">
                                            <div class="matters-empty">
                                                <i class="fa-regular fa-folder-open"></i>
                                                <p>No matters found</p>
                                                <span>Try clearing filters or searching with a different client name.</span>
                                                @if($activeMatterFilters > 0)
                                                    <div class="mt-3">
                                                        <a href="{{ route('clients.clientsmatterslist') }}" class="btn btn-theme btn-theme-sm">Clear filters</a>
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

@include('crm.clients.modals.edit-matter-office')
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

    $('#from_date, #to_date').on('change', function(){
        $('#matter_quick_date_range').val('');
    });

    $('#clearMatterFilters').on('click', function(){
        window.location.href = "{{ route('clients.clientsmatterslist') }}";
    });

    $('#filterToggleBtn, .matters-listing .filter_btn').on('click', function(){
        $('#matterFilterPanel').toggleClass('is-open');
    });

    $(document).on('click', '.assign-office-btn, .edit-office-btn', function(e) {
        e.preventDefault();
        var matterId = $(this).data('matter-id');
        var matterNo = $(this).data('matter-no');
        var matterTitle = $(this).data('matter-title');
        var officeId = $(this).data('office-id') || '';

        $('#edit_matter_id').val(matterId);
        $('#modal_matter_number').text(matterNo);
        $('#modal_matter_title').text(matterTitle || 'N/A');
        $('#edit_office_id').val(officeId).trigger('change');
        $('#editMatterOfficeModal').modal('show');
    });

    $('#editMatterOfficeForm').on('submit', function(e) {
        e.preventDefault();
        var formData = $(this).serialize();
        var submitBtn = $(this).find('button[type="submit"]');
        var originalText = submitBtn.html();
        submitBtn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> Saving...');

        $.ajax({
            url: '{{ route("matters.update-office") }}',
            method: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    if (typeof iziToast !== 'undefined') {
                        iziToast.success({ title: 'Success', message: response.message, position: 'topRight' });
                    }
                    setTimeout(function() { location.reload(); }, 900);
                } else {
                    if (typeof iziToast !== 'undefined') {
                        iziToast.error({ title: 'Error', message: response.message || 'Failed to update office', position: 'topRight' });
                    }
                    submitBtn.prop('disabled', false).html(originalText);
                }
            },
            error: function(xhr) {
                var errorMsg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'An error occurred. Please try again.';
                if (typeof iziToast !== 'undefined') {
                    iziToast.error({ title: 'Error', message: errorMsg, position: 'topRight' });
                }
                submitBtn.prop('disabled', false).html(originalText);
            }
        });
    });

    $('#editMatterOfficeModal').on('hidden.bs.modal', function() {
        $('#editMatterOfficeForm')[0].reset();
        $('#edit_office_id').val('').trigger('change');
    });
});
</script>
@endpush
