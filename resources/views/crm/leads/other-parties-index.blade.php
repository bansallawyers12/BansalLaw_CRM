@extends('layouts.crm_client_detail')
@section('title', 'Other Parties')

@section('styles')
<link rel="stylesheet" href="{{ asset('css/listing-pagination.css') }}">
<link rel="stylesheet" href="{{ asset('css/listing-container.css') }}">
<link rel="stylesheet" href="{{ asset('css/other-parties-list.css') }}?v={{ @filemtime(public_path('css/other-parties-list.css')) ?: time() }}">
@endsection

@section('content')
@php
    $opFilters = collect([
        'client_id' => request('client_id'),
        'name' => request('name'),
        'email' => request('email'),
        'phone' => request('phone'),
    ]);
    $activeOpFilters = $opFilters->filter(fn ($value) => $value !== null && $value !== '')->count();
    $totalCount = method_exists($lists, 'total') ? $lists->total() : (int) ($totalData ?? 0);
    $shownCount = method_exists($lists, 'count') ? $lists->count() : (is_countable($lists) ? count($lists) : 0);
@endphp

<div class="listing-container other-parties-listing"
     id="otherPartiesListingRoot"
     data-infinite-scroll="1"
     data-current-page="{{ method_exists($lists, 'currentPage') ? $lists->currentPage() : 1 }}"
     data-last-page="{{ method_exists($lists, 'lastPage') ? $lists->lastPage() : 1 }}"
     data-total="{{ $totalCount }}"
     data-per-page="20">
    <section class="listing-section">
        <div class="listing-section-body">
            @include('../Elements/flash-message')

            <div class="card">
                <div class="card-header">
                    <div class="op-page-header">
                        <div class="op-page-header__title">
                            <span class="op-page-header__icon" aria-hidden="true">
                                <i class="fa-solid fa-user-tag"></i>
                            </span>
                            <div>
                                <h4>Other Parties</h4>
                                <p class="op-page-header__subtitle">
                                    {{ number_format($totalCount) }} {{ Str::plural('party', $totalCount) }}
                                    &middot; Conflicts, opposing parties and related contacts
                                </p>
                            </div>
                        </div>

                        <div class="card-header-actions">
                            <a href="javascript:;" class="btn btn-theme btn-theme-sm filter_btn{{ $activeOpFilters > 0 ? ' filter_btn--active' : '' }}" id="filterToggleBtn">
                                <i class="fa-solid fa-filter"></i> Filter
                                @if($activeOpFilters > 0)
                                    <span class="filter-count-badge">{{ $activeOpFilters }}</span>
                                @endif
                            </a>
                            <a href="{{ route('leads.create', ['other_party' => 1]) }}" class="btn btn-theme btn-theme-sm">
                                <i class="fa-solid fa-user-plus"></i> Create Other Party
                            </a>
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    <div class="op-toolbar">
                        <ul class="op-tabs nav" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link" href="{{ URL::to('/clients') }}">Clients</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="{{ URL::to('/leads') }}">Leads</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link active" href="{{ route('leads.other_parties.index') }}" aria-current="page">Other Parties</a>
                            </li>
                        </ul>
                    </div>

                    <div class="filter_panel{{ $activeOpFilters > 0 ? ' is-open' : '' }}" id="opFilterPanel">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                            <h4 class="mb-0">
                                Filter other parties
                                @if($activeOpFilters > 0)
                                    <span class="active-filters-badge">
                                        <i class="fa-solid fa-filter"></i> {{ $activeOpFilters }} active
                                    </span>
                                @endif
                            </h4>
                            @if($activeOpFilters > 0)
                                <a href="{{ route('leads.other_parties.index') }}" class="btn btn-sm btn-outline-secondary">
                                    <i class="fa-solid fa-rotate-left"></i> Clear filters
                                </a>
                            @endif
                        </div>

                        <form action="{{ route('leads.other_parties.index') }}" method="get" id="opFilterForm">
                            <div class="row g-3">
                                <div class="col-md-3 col-sm-6">
                                    <div class="form-group mb-0">
                                        <label for="op_client_id">ID ref</label>
                                        <input type="text" name="client_id" id="op_client_id" class="form-control" placeholder="e.g. RANS2600117" value="{{ request('client_id') }}">
                                    </div>
                                </div>
                                <div class="col-md-3 col-sm-6">
                                    <div class="form-group mb-0">
                                        <label for="op_name">Name</label>
                                        <input type="text" name="name" id="op_name" class="form-control" placeholder="First or last name" value="{{ request('name') }}">
                                    </div>
                                </div>
                                <div class="col-md-3 col-sm-6">
                                    <div class="form-group mb-0">
                                        <label for="op_email">Email</label>
                                        <input type="text" name="email" id="op_email" class="form-control" placeholder="email@example.com" value="{{ request('email') }}">
                                    </div>
                                </div>
                                <div class="col-md-3 col-sm-6">
                                    <div class="form-group mb-0">
                                        <label for="op_phone">Phone</label>
                                        <input type="text" name="phone" id="op_phone" class="form-control" placeholder="Phone number" value="{{ request('phone') }}">
                                    </div>
                                </div>
                            </div>
                            <div class="filter-actions mt-3">
                                <button type="submit" class="btn btn-theme btn-theme-sm">
                                    <i class="fa-solid fa-magnifying-glass"></i> Apply filters
                                </button>
                                <a href="{{ route('leads.other_parties.index') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
                            </div>
                        </form>
                    </div>

                    <div class="op-results-bar" id="opResultsBar">
                        <span>
                            Showing <strong data-loaded-count>{{ number_format($shownCount) }}</strong>
                            of <strong data-total-count>{{ number_format($totalCount) }}</strong>
                        </span>
                        @if($activeOpFilters > 0)
                            <span class="op-results-bar__filtered">
                                <i class="fa-solid fa-filter"></i> Filtered
                            </span>
                        @endif
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th class="sortable-header">@sortablelink('first_name', 'Name')</th>
                                    <th>Ref</th>
                                    <th>Phone</th>
                                    <th>Email</th>
                                    <th class="sortable-header">@sortablelink('created_at', 'Created')</th>
                                    <th style="width: 72px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="op-tdata">
                                @forelse($lists as $list)
                                    @php
                                        $fullName = trim(($list->first_name ?? '') . ' ' . ($list->last_name ?? ''));
                                        $detailUrl = route('clients.detail', base64_encode(convert_uuencode($list->id)));
                                        $editUrl = route('clients.edit', base64_encode(convert_uuencode($list->id)));
                                    @endphp
                                    <tr id="op_id_{{ $list->id }}" class="op-data-row" data-op-id="{{ $list->id }}">
                                        <td>
                                            <a href="{{ $detailUrl }}" class="op-name-link" title="Open {{ $fullName }}">
                                                {{ $fullName !== '' ? $fullName : '—' }}
                                            </a>
                                        </td>
                                        <td>
                                            @if(!empty($list->client_id))
                                                <span class="op-ref">{{ $list->client_id }}</span>
                                            @else
                                                <span class="op-contact__muted">—</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if(!empty($list->phone))
                                                <span class="op-contact">{{ $list->phone }}</span>
                                            @else
                                                <span class="op-contact__muted">—</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if(!empty($list->email))
                                                <a href="mailto:{{ $list->email }}" class="op-name-link" style="font-weight: 500;">{{ $list->email }}</a>
                                            @else
                                                <span class="op-contact__muted">—</span>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="op-date">{{ $list->created_at ? $list->created_at->format('d/m/Y') : '—' }}</span>
                                        </td>
                                        <td>
                                            <div class="op-actions">
                                                <a href="{{ $editUrl }}" class="op-action-btn" title="Edit">
                                                    <i class="fa-solid fa-pen"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr class="op-empty-row">
                                        <td colspan="6" class="op-empty">
                                            <div class="op-empty__inner">
                                                <i class="fa-solid fa-user-tag" aria-hidden="true"></i>
                                                <strong>No other parties found</strong>
                                                <span>
                                                    @if($activeOpFilters > 0)
                                                        Try clearing filters or adjust your search.
                                                    @else
                                                        Create an other party to use in conflict checks and matter roles.
                                                    @endif
                                                </span>
                                                @if($activeOpFilters === 0)
                                                    <a href="{{ route('leads.create', ['other_party' => 1]) }}" class="btn btn-theme btn-theme-sm mt-2">
                                                        <i class="fa-solid fa-user-plus"></i> Create Other Party
                                                    </a>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="clients-infinite-loader op-infinite-loader" id="opInfiniteLoader" hidden aria-live="polite">
                        <span class="clients-infinite-loader__spinner" aria-hidden="true"></span>
                        <span>Loading more parties...</span>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
@endsection

@section('scripts')
<script>
(function () {
    var root = document.getElementById('otherPartiesListingRoot');
    var loader = document.getElementById('opInfiniteLoader');
    var loadingMore = false;

    var toggleBtn = document.getElementById('filterToggleBtn');
    var panel = document.getElementById('opFilterPanel');
    if (toggleBtn && panel) {
        toggleBtn.addEventListener('click', function (e) {
            e.preventDefault();
            panel.classList.toggle('is-open');
        });
    }

    if (!root || root.getAttribute('data-infinite-scroll') !== '1') {
        return;
    }

    function setLoader(visible) {
        if (loader) {
            loader.hidden = !visible;
        }
    }

    function hasMore() {
        var current = parseInt(root.getAttribute('data-current-page'), 10) || 1;
        var last = parseInt(root.getAttribute('data-last-page'), 10) || 1;
        return current < last;
    }

    function updateResultsBar() {
        var loadedEl = root.querySelector('[data-loaded-count]');
        var tbody = root.querySelector('tbody.op-tdata');
        if (!loadedEl || !tbody) {
            return;
        }
        loadedEl.textContent = String(tbody.querySelectorAll('tr.op-data-row').length);
    }

    function loadMore() {
        if (loadingMore || !hasMore()) {
            return;
        }

        var current = parseInt(root.getAttribute('data-current-page'), 10) || 1;
        var nextPage = current + 1;
        var url = new URL(window.location.href);
        url.searchParams.set('page', String(nextPage));
        url.searchParams.set('per_page', '20');

        loadingMore = true;
        setLoader(true);

        fetch(url.href, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'text/html'
            }
        }).then(function (response) {
            if (!response.ok) {
                throw new Error('Failed to load more parties');
            }
            return response.text();
        }).then(function (html) {
            var parser = new DOMParser();
            var doc = parser.parseFromString(html, 'text/html');
            var newRoot = doc.getElementById('otherPartiesListingRoot');
            if (!newRoot) {
                return;
            }

            var lastPage = parseInt(newRoot.getAttribute('data-last-page'), 10) || nextPage;
            var tbody = root.querySelector('tbody.op-tdata');
            var newRows = newRoot.querySelectorAll('tbody.op-tdata tr.op-data-row');

            newRows.forEach(function (row) {
                var rowId = row.id;
                if (rowId && tbody.querySelector('#' + rowId)) {
                    return;
                }
                tbody.appendChild(document.importNode(row, true));
            });

            root.setAttribute('data-current-page', String(nextPage));
            root.setAttribute('data-last-page', String(lastPage));
            updateResultsBar();
        }).catch(function () {
            // Keep current page so scroll can retry.
        }).finally(function () {
            loadingMore = false;
            setLoader(false);
            window.requestAnimationFrame(maybeLoadMore);
        });
    }

    function maybeLoadMore() {
        if (loadingMore || !hasMore()) {
            return;
        }
        var scrollBottom = window.innerHeight + window.scrollY;
        var triggerLine = document.documentElement.scrollHeight - 280;
        if (scrollBottom >= triggerLine) {
            loadMore();
        }
    }

    window.addEventListener('scroll', maybeLoadMore, { passive: true });
    window.addEventListener('resize', maybeLoadMore);
    window.requestAnimationFrame(maybeLoadMore);
})();
</script>
@endsection
