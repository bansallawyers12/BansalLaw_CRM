@extends('layouts.crm_client_detail')
@php
    $listTab = $listTab ?? 'active';
    $isClosed = $listTab === 'closed';
    $activeUrl = $activeUrl ?? route('clients.clientsmatterslist');
    $closedUrl = $closedUrl ?? route('clients.closedmatterslist');

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
        'closure_status' => $isClosed ? request('closure_status') : null,
        'quick_date_range' => request('quick_date_range'),
        'from_date' => request('from_date'),
        'to_date' => request('to_date'),
        'date_filter_field' => request('date_filter_field') !== 'created_at' ? request('date_filter_field') : null,
    ]);
    $activeMatterFilters = $matterFilters->filter(fn ($value) => $value !== null && $value !== '')->count();

    $totalCount = method_exists($lists, 'total') ? $lists->total() : (int) ($totalData ?? 0);
    $currentPage = method_exists($lists, 'currentPage') ? $lists->currentPage() : 1;
    $lastPage = method_exists($lists, 'lastPage') ? $lists->lastPage() : 1;
@endphp
@section('title', $isClosed ? 'Clients Closed Matters' : 'Clients Matters List')

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
    #mattersSpaLoading {
        position: absolute;
        inset: 0;
        z-index: 5;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(255, 255, 255, 0.72);
        backdrop-filter: blur(1px);
    }
    #mattersSpaLoading:not(.d-none) {
        display: flex;
    }
    .matters-listing-spa .card-body {
        position: relative;
    }
</style>
@include('crm.clients.partials.enhanced-date-filter-styles')
@endsection

@section('content')
<div id="matters-listing-root"
     class="listing-container matters-listing matters-listing-spa"
     data-infinite-scroll="1"
     data-current-page="{{ $currentPage }}"
     data-last-page="{{ $lastPage }}"
     data-per-page="20"
     data-list-tab="{{ $listTab }}"
     data-active-url="{{ $activeUrl }}"
     data-closed-url="{{ $closedUrl }}">
    <section class="listing-section">
        <div class="listing-section-body">
            @include('../Elements/flash-message')

            <div class="card">
                <div class="custom-error-msg"></div>

                <div class="card-header">
                    <div class="matters-page-header">
                        <div class="matters-page-header__title">
                            <span class="matters-page-header__icon" id="mattersSpaIcon" aria-hidden="true">
                                <i class="fa-solid {{ $isClosed ? 'fa-box-archive' : 'fa-folder-open' }}"></i>
                            </span>
                            <div>
                                <h4 id="mattersSpaTitle">{{ $isClosed ? 'Closed Matters' : 'Client Matters' }}</h4>
                                <p class="matters-page-header__subtitle" id="mattersSpaSubtitle">
                                    @if($isClosed)
                                        {{ number_format($totalCount) }} closed {{ Str::plural('matter', $totalCount) }}
                                        &middot; Review history and reopen when needed
                                    @else
                                        {{ number_format($totalCount) }} active {{ Str::plural('matter', $totalCount) }}
                                        &middot; Search, filter and open matter files
                                    @endif
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
                        <div class="matters-tabs" role="tablist" aria-label="Matter lists">
                            <button type="button"
                                    class="matters-tab matters-spa-tab {{ $listTab === 'active' ? 'is-active active' : '' }}"
                                    data-tab="active"
                                    onclick="if(window.MattersListSpa){window.MattersListSpa.loadTab('active',{},true);}return false;">Active Matters</button>
                            <button type="button"
                                    class="matters-tab matters-spa-tab {{ $listTab === 'closed' ? 'is-active active' : '' }}"
                                    data-tab="closed"
                                    onclick="if(window.MattersListSpa){window.MattersListSpa.loadTab('closed',{},true);}return false;">Closed Matters</button>
                        </div>
                    </div>

                    <div id="matters-spa-content">
                        @include('crm.clients.partials.matters-list-spa-body')
                    </div>

                    <div id="mattersSpaLoading" class="d-none" aria-live="polite" aria-busy="true">
                        <span class="matters-infinite-loader__spinner" aria-hidden="true"></span>
                        <span class="ms-2">Loading matters...</span>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

@include('crm.clients.modals.edit-matter-office')

{{-- Inline SPA bootstrap immediately after markup so tab clicks work without waiting on stacked scripts --}}
<script>
window.mattersReopenUrl = @json(route('clients.matter.reopen'));
window.mattersRequestReopenUrl = @json(route('clients.matter.request-reopen'));
window.mattersUpdateOfficeUrl = @json(route('matters.update-office'));
window.MattersListSpaConfig = {
    activeUrl: @json($activeUrl),
    closedUrl: @json($closedUrl),
    listTab: @json($listTab)
};
</script>
@endsection

@push('scripts')
<script>
(function () {
    'use strict';
    if (window.MattersListSpa) {
        return;
    }

    var cfg = window.MattersListSpaConfig || {};
    var root = document.getElementById('matters-listing-root');
    var contentEl = document.getElementById('matters-spa-content');
    var loadingEl = document.getElementById('mattersSpaLoading');
    var loadSeq = 0;
    var state = { tab: (root && root.getAttribute('data-list-tab')) || cfg.listTab || 'active', loading: false };

    if (!root || !contentEl) {
        return;
    }

    function tabUrl(tab) {
        if (tab === 'closed') {
            return root.getAttribute('data-closed-url') || cfg.closedUrl || '';
        }
        return root.getAttribute('data-active-url') || cfg.activeUrl || '';
    }

    function setLoading(on) {
        state.loading = !!on;
        if (!loadingEl) {
            return;
        }
        loadingEl.classList.toggle('d-none', !on);
        loadingEl.setAttribute('aria-busy', on ? 'true' : 'false');
    }

    function setActiveTab(tab) {
        state.tab = tab === 'closed' ? 'closed' : 'active';
        root.setAttribute('data-list-tab', state.tab);
        root.querySelectorAll('.matters-spa-tab').forEach(function (btn) {
            var on = btn.getAttribute('data-tab') === state.tab;
            btn.classList.toggle('is-active', on);
            btn.classList.toggle('active', on);
        });
        var icon = document.querySelector('#mattersSpaIcon i');
        if (icon) {
            icon.className = 'fa-solid ' + (state.tab === 'closed' ? 'fa-box-archive' : 'fa-folder-open');
        }
    }

    function buildFetchUrl(tab, params) {
        var base = tabUrl(tab);
        if (!base) {
            return '';
        }
        var url = new URL(base, window.location.origin);
        Object.keys(params || {}).forEach(function (key) {
            if (params[key] !== '' && params[key] != null) {
                url.searchParams.set(key, String(params[key]));
            }
        });
        url.searchParams.set('spa', '1');
        url.searchParams.delete('page');
        return url.href;
    }

    function buildHistoryUrl(tab, params) {
        var base = tabUrl(tab);
        var url = new URL(base, window.location.origin);
        Object.keys(params || {}).forEach(function (key) {
            if (key === 'spa' || key === 'page') {
                return;
            }
            if (params[key] !== '' && params[key] != null) {
                url.searchParams.set(key, String(params[key]));
            }
        });
        url.searchParams.delete('spa');
        url.searchParams.delete('page');
        var qs = url.searchParams.toString();
        return url.pathname + (qs ? '?' + qs : '');
    }

    function applyResponse(data, pushUrl) {
        if (!data || typeof data.html !== 'string') {
            return;
        }
        contentEl.innerHTML = data.html;
        setActiveTab(data.listTab || state.tab);
        root.setAttribute('data-current-page', String(data.currentPage || 1));
        root.setAttribute('data-last-page', String(data.lastPage || 1));

        var titleEl = document.getElementById('mattersSpaTitle');
        var subtitleEl = document.getElementById('mattersSpaSubtitle');
        if (titleEl && data.title) {
            titleEl.textContent = data.title;
        }
        if (subtitleEl && data.subtitle) {
            subtitleEl.innerHTML = String(data.subtitle).replace(/ · /g, ' &middot; ');
        }

        if (pushUrl && window.history && window.history.pushState) {
            window.history.pushState({ mattersSpa: true, tab: state.tab }, '', pushUrl);
        }

        if (typeof window.MattersListAfterSpaSwap === 'function') {
            window.MattersListAfterSpaSwap();
        }
    }

    function loadTab(tab, params, pushHistory) {
        tab = tab === 'closed' ? 'closed' : 'active';
        params = params || {};
        var href = buildFetchUrl(tab, params);
        if (!href) {
            return;
        }

        var seq = ++loadSeq;
        setActiveTab(tab);
        setLoading(true);

        fetch(href, {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
            .then(function (res) {
                if (!res.ok) {
                    throw new Error('Failed to load matters');
                }
                return res.json();
            })
            .then(function (data) {
                if (seq !== loadSeq) {
                    return;
                }
                applyResponse(data, pushHistory === false ? null : buildHistoryUrl(tab, params));
            })
            .catch(function () {
                if (seq !== loadSeq) {
                    return;
                }
                if (typeof iziToast !== 'undefined') {
                    iziToast.error({ title: 'Error', message: 'Could not load matters list.', position: 'topRight' });
                } else {
                    window.alert('Could not load matters list.');
                }
            })
            .finally(function () {
                if (seq === loadSeq) {
                    setLoading(false);
                }
            });
    }

    function onTabClick(e) {
        e.preventDefault();
        e.stopPropagation();
        var btn = e.currentTarget;
        var tab = btn.getAttribute('data-tab') === 'closed' ? 'closed' : 'active';
        if (tab === state.tab && !state.loading) {
            return;
        }
        loadTab(tab, {}, true);
    }

    root.querySelectorAll('.matters-spa-tab').forEach(function (btn) {
        btn.addEventListener('click', onTabClick);
    });

    window.addEventListener('popstate', function () {
        var path = window.location.pathname || '';
        var closedPath = '';
        try {
            closedPath = new URL(tabUrl('closed'), window.location.origin).pathname;
        } catch (err) { /* ignore */ }
        var tab = (closedPath && path.indexOf(closedPath) !== -1) ? 'closed' : 'active';
        var params = {};
        new URLSearchParams(window.location.search).forEach(function (value, key) {
            if (key !== 'spa') {
                params[key] = value;
            }
        });
        loadTab(tab, params, false);
    });

    window.MattersListSpa = {
        loadTab: loadTab,
        getTab: function () { return state.tab; },
        isLoading: function () { return state.loading; }
    };
})();
</script>
<script src="{{ asset('js/crm/clients/matters-listing-infinite.js') }}?v={{ filemtime(public_path('js/crm/clients/matters-listing-infinite.js')) }}"></script>
@endpush
