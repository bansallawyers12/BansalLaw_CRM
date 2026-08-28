@php
    $listTab = $listTab ?? 'active';
    $isClosed = $listTab === 'closed';
    $activeUrl = $activeUrl ?? route('clients.clientsmatterslist');
    $closedUrl = $closedUrl ?? route('clients.closedmatterslist');
    $listBaseUrl = $isClosed ? $closedUrl : $activeUrl;

    $_cmViewer = Auth::user();
    $_cmEffectiveSa = $_cmViewer instanceof \App\Models\Staff && $_cmViewer->hasEffectiveSuperAdminPrivileges();
    $_cmCanReopen = $_cmEffectiveSa || ($_cmViewer instanceof \App\Models\Staff && $_cmViewer->hasCrmModule('45'));

    $matterFilters = collect([
        'sel_matter_id' => request('sel_matter_id'),
        'client_id' => request('client_id'),
        'name' => request('name'),
        'sel_legal_practitioner' => request('sel_legal_practitioner'),
        'sel_person_responsible' => request('sel_person_responsible'),
        'sel_person_assisting' => request('sel_person_assisting'),
        'closure_status' => $isClosed ? request('closure_status') : null,
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
    $buildSortUrl = function ($column) use ($nextDirection, $listBaseUrl) {
        $query = request()->except('page');
        $query['sort'] = $column;
        $query['direction'] = $nextDirection($column);
        return $listBaseUrl . '?' . http_build_query($query);
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

    $staffIds = collect($lists->items())->flatMap(function ($row) use ($isClosed) {
        $ids = [
            $row->sel_legal_practitioner ?? null,
            $row->sel_person_responsible ?? null,
            $row->sel_person_assisting ?? null,
        ];
        if ($isClosed) {
            $ids[] = $row->closed_by ?? null;
        }
        return $ids;
    })->filter()->unique()->values()->all();
    $staffById = $staffIds
        ? \App\Models\Staff::query()->whereIn('id', $staffIds)->get(['id', 'first_name', 'last_name'])->keyBy('id')
        : collect();
    $officeIds = collect($lists->items())->pluck('office_id')->filter()->unique()->values()->all();
    $officesById = $officeIds
        ? \App\Models\Branch::query()->whereIn('id', $officeIds)->get()->keyBy('id')
        : collect();
@endphp

<div class="filter_panel{{ $activeMatterFilters > 0 ? ' is-open' : '' }}" id="matterFilterPanel">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
        <h4 class="mb-0">
            {{ $isClosed ? 'Filter closed matters' : 'Filter matters' }}
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

    <form action="{{ $listBaseUrl }}" method="get" id="matterFilterForm">
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
            <div class="col-md-4 matters-filter-closed-only{{ $isClosed ? '' : ' d-none' }}">
                <div class="form-group mb-0">
                    <label for="closure_status">Closure status</label>
                    <select class="form-control" name="closure_status" id="closure_status" @if(! $isClosed) disabled @endif>
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
                        <label for="date_filter_field"><i class="fa-solid fa-calendar-days"></i> Date field</label>
                        <select name="date_filter_field" id="date_filter_field" class="form-control">
                            <option value="created_at" {{ request('date_filter_field', 'created_at') === 'created_at' ? 'selected' : '' }}>Created date</option>
                            <option value="updated_at" {{ request('date_filter_field') === 'updated_at' ? 'selected' : '' }}>
                                {{ $isClosed ? 'Closed / Last Updated' : 'Last updated' }}
                            </option>
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
                <a class="btn btn-outline-secondary matters-spa-reset" href="{{ $listBaseUrl }}">Reset</a>
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
        <span class="matters-results-bar__hint" data-scroll-more-hint><i class="fa-solid fa-arrow-down"></i> Scroll for more</span>
    @endif
</div>
@endif

<div class="table-responsive matters-table-wrap">
    <table class="table matters-table">
        <thead>
            @if($isClosed)
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
            @else
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
            @endif
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
                        $clientName = trim(($list->first_name ?? '') . ' ' . ($list->last_name ?? ''));
                        if ($clientName === '') {
                            $clientName = '—';
                        }
                    @endphp
                    @if($isClosed)
                        @php
                            $closed_by_info = $list->closed_by ? $staffById->get($list->closed_by) : null;
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
                                            <button class="btn btn-primary btn-sm closed-matter-reopen" type="button" data-matter-id="{{ $list->id }}">
                                                <i class="fa-solid fa-arrow-rotate-right"></i> Reopen
                                            </button>
                                        @elseif($list->reopen_requested_by)
                                            <button class="btn btn-secondary btn-sm" disabled type="button" title="Reopen Requested">
                                                <i class="fa-solid fa-clock"></i> Requested
                                            </button>
                                        @else
                                            <button class="btn btn-warning btn-sm closed-matter-request-reopen" type="button" data-matter-id="{{ $list->id }}" title="Request Admin to Reopen Matter">
                                                <i class="fa-solid fa-hand-paper"></i> Request
                                            </button>
                                        @endif
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @else
                        @php
                            $dobDisplay = ! empty($list->dob) && strtotime($list->dob)
                                ? date('d/m/Y', strtotime($list->dob))
                                : '—';
                            $createdDisplay = ! empty($list->created_at)
                                ? date('d/m/Y', strtotime($list->created_at))
                                : '—';
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
                    @endif
                @endforeach
            @else
                <tr>
                    <td colspan="{{ $isClosed ? 8 : 7 }}">
                        <div class="matters-empty">
                            <i class="fa-regular fa-folder-open"></i>
                            <p>{{ $isClosed ? 'No closed matters found' : 'No matters found' }}</p>
                            <span>Try clearing filters or searching with a different client name.</span>
                            @if($activeMatterFilters > 0)
                                <div class="mt-3">
                                    <a href="{{ $listBaseUrl }}" class="btn btn-theme btn-theme-sm matters-spa-reset">Clear filters</a>
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
