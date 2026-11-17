@php
    $__sch = \Illuminate\Support\Facades\Schema::class;
    $clientMattersList = collect();
    $closedMattersList = collect();
    if ($__sch::hasTable('client_matters')) {
        $with = ['matter'];
        if ($__sch::hasTable('client_matter_opposing_parties')) {
            $with[] = 'opposingParties';
        }

        $clientIdForMatters = (int) $fetchedData->id;

        $clientMattersList = \App\Models\ClientMatter::query()
            ->activeForClientPicker($clientIdForMatters)
            ->with($with)
            ->orderByDesc('client_matters.id')
            ->get();

        $clientMattersList = \App\Support\MatterScopedAccess::filterMattersForViewer($clientMattersList);

        if ($clientMattersList->isEmpty()) {
            $closedMattersList = \App\Models\ClientMatter::query()
                ->closedForClientPicker($clientIdForMatters)
                ->with($with)
                ->orderByDesc('client_matters.id')
                ->get();
            $closedMattersList = \App\Support\MatterScopedAccess::filterMattersForViewer($closedMattersList);
        }
    }

    $displayingClosedMatters = $clientMattersList->isEmpty() && $closedMattersList->isNotEmpty();
    $activeMatterCount = $clientMattersList->count();
    $closedMatterCount = $closedMattersList->count();

    $matterRefInUrl = trim((string) ($matterRefInUrl ?? ''));
    $recordScopeInUrl = empty($isClosedMatterView) && $matterRefInUrl === '';

    $currentMatterRef = (string) (($selectedClientMatter ?? null)?->client_unique_matter_no ?? '');
    if ($currentMatterRef === '' && $matterRefInUrl !== '' && ! empty($isClosedMatterView)) {
        $currentMatterRef = $matterRefInUrl;
    } elseif ($currentMatterRef === '' && ! $recordScopeInUrl && $clientMattersList->isNotEmpty()) {
        $currentMatterRef = (string) ($clientMattersList->first()->client_unique_matter_no ?? '');
    }

    $sortMatterRows = function ($list) use ($currentMatterRef) {
        return $list->sort(function ($a, $b) use ($currentMatterRef) {
            $aNo = trim((string) ($a->client_unique_matter_no ?? ''));
            $bNo = trim((string) ($b->client_unique_matter_no ?? ''));
            $aCur = $aNo !== '' && $aNo === $currentMatterRef;
            $bCur = $bNo !== '' && $bNo === $currentMatterRef;
            if ($aCur !== $bCur) {
                return $aCur ? -1 : 1;
            }

            return strnatcasecmp($bNo, $aNo);
        })->values();
    };
    $clientMattersList = $sortMatterRows($clientMattersList);
    $closedMattersList = $sortMatterRows($closedMattersList);

    $clientDetailTab = strtolower((string) ($activeTab ?? 'personaldetails'));
    if ($clientDetailTab === 'overview') {
        $clientDetailTab = 'personaldetails';
    }

    $clientDetailEncodeId = $encodeId ?? base64_encode(convert_uuencode($fetchedData->id));

    $typeRaw = $fetchedData->type ?? null;
    $typeStr = $typeRaw === null ? '' : trim((string) $typeRaw);
    $isLeadRecord = ($typeRaw === 1)
        || in_array(strtolower($typeStr), ['lead', 'l', '1'], true);
    $recordScopeLabel = $isLeadRecord ? 'Lead' : 'Client';
    $recordScopeRef = trim((string) ($fetchedData->client_id ?? ''));
    if ($recordScopeRef === '') {
        $recordScopeRef = '#' . (int) $fetchedData->id;
    }
    $recordScopeUrl = route('clients.detail', [$clientDetailEncodeId, $clientDetailTab]);
    $showRecordScopeRow = $activeMatterCount === 0;

    $canAddClientMatter = is_array($matterFormForLead ?? null);

    $viewer = \Illuminate\Support\Facades\Auth::guard('admin')->user();
    $canCloseClientMatter = empty($isClosedMatterView)
        && ($viewer instanceof \App\Models\Staff && $viewer->canCloseDiscontinueMatter());

    $listRowCount = $activeMatterCount + ($showRecordScopeRow ? 1 : 0) + ($displayingClosedMatters ? $closedMatterCount : 0);
@endphp

<div class="card cdn-ov-card" id="clientMattersListCard">
    <header class="cdn-ov-card__head client-matter-list-card-header">
        <div class="cdn-ov-card__title">
            <span class="cdn-ov-card__icon" aria-hidden="true"><i class="fa-solid fa-folder-open"></i></span>
            <h3>Client Matters</h3>
            @if($listRowCount > 0)
                <span class="cdn-ov-count" title="Entries in this list">{{ $listRowCount }}</span>
            @endif
        </div>
        @if($canAddClientMatter)
            <button type="button"
                    class="cdn-ov-card__action client-matter-list-add-btn crm-closed-matter-allow"
                    title="Add a new matter"
                    aria-label="Add a new matter"
                    onclick="event.stopPropagation(); if (typeof window.openAddMatterModal === 'function') { window.openAddMatterModal(); }">
                <i class="fa-solid fa-plus" aria-hidden="true"></i> Add
            </button>
        @endif
    </header>

    <div class="cdn-ov-card__body cdn-ov-card__body--scroll">
    @if($listRowCount === 0)
        <div class="cdn-ov-matters-empty-state">
            <p class="cdn-ov-matters-empty-state__text">No matters on this {{ strtolower($recordScopeLabel) }}.</p>
            @if($canAddClientMatter)
                <p class="cdn-ov-matters-empty-state__hint">Use <strong>Add</strong> to open a new matter.</p>
            @endif
        </div>
    @else
        <div class="cdn-ov-scroll cdn-ov-scroll--matters" tabindex="0" role="region" aria-label="Client matters">
        <ul class="cdn-ov-matter-list" role="list">
        @if($showRecordScopeRow)
            <li class="cdn-ov-matter-row cdn-ov-matter-row--record-scope{{ $recordScopeInUrl ? ' is-current' : '' }}{{ $recordScopeInUrl ? '' : ' is-switchable' }}"
                @if($recordScopeInUrl) aria-current="true" @endif
                @if(! $recordScopeInUrl)
                role="button"
                tabindex="0"
                data-matter-url="{{ $recordScopeUrl }}"
                data-skip-confirm="1"
                @endif>
                <div class="cdn-ov-matter-card-inner">
                    <span class="cdn-ov-matter-code">{{ $recordScopeRef }}</span>
                    @if($recordScopeInUrl)
                        <span class="cdn-ov-active-matter-pill">Current</span>
                    @else
                        <span class="cdn-ov-matter-scope-pill">{{ $recordScopeLabel }}</span>
                    @endif
                </div>
            </li>
        @endif

        @php
            $rowsToRender = $displayingClosedMatters ? $closedMattersList : $clientMattersList;
        @endphp
        @foreach($rowsToRender as $cmRow)
            @php
                $matterNo = trim((string) ($cmRow->client_unique_matter_no ?? ''));
                $matterRefLabel = $matterNo !== '' ? $matterNo : ('Matter #' . (int) ($cmRow->id ?? 0));

                $partyName = ($__sch::hasTable('client_matter_opposing_parties') && $cmRow->relationLoaded('opposingParties'))
                    ? $cmRow->opposingParties
                        ->sortBy([
                            fn ($party) => (int) ($party->sort_order ?? 0),
                            fn ($party) => (int) ($party->id ?? 0),
                        ])
                        ->map(function ($party) {
                            return trim((string) ($party->name ?? ''));
                        })
                        ->filter()
                        ->values()
                        ->implode(' · ')
                    : '';

                $isCurrent = $matterNo !== '' && $matterNo === $currentMatterRef;
                $matterUrl = $matterNo !== ''
                    ? route('clients.detail', [$clientDetailEncodeId, $matterNo, $clientDetailTab])
                    : null;
            @endphp

            <li class="cdn-ov-matter-row{{ $isCurrent ? ' is-current' : '' }}{{ ($matterUrl && ! $isCurrent) ? ' is-switchable' : '' }}"
                @if($isCurrent) aria-current="true" @endif
                @if($matterUrl && ! $isCurrent)
                role="button"
                tabindex="0"
                data-matter-url="{{ $matterUrl }}"
                @endif>
                <div class="cdn-ov-matter-card-inner">
                    <span class="cdn-ov-matter-code">{{ $matterRefLabel }}</span>
                    @if($displayingClosedMatters)
                        <span class="cdn-ov-matter-closed-pill"><i class="fa-solid fa-box-archive"></i> Closed</span>
                    @elseif($isCurrent)
                        <span class="cdn-ov-active-matter-pill">Current</span>
                    @endif
                    @if($partyName !== '')
                        <span class="cdn-ov-party-summary-line" title="{{ $partyName }}">{{ $partyName }}</span>
                    @else
                        <span class="cdn-ov-party-summary-line"></span>
                    @endif
                    @if($isCurrent && $canCloseClientMatter && ! $displayingClosedMatters)
                        <button type="button"
                                class="client-matter-list-close-btn cdn-ov-matter-close-btn"
                                title="Close this matter"
                                aria-label="Close this matter"
                                data-matter-id="{{ $cmRow->id }}"
                                onclick="event.stopPropagation(); if (typeof window.openCloseMatterModal === 'function') { window.openCloseMatterModal(this); }">
                            <i class="fa-solid fa-ban" aria-hidden="true"></i>
                        </button>
                    @endif
                </div>
            </li>
        @endforeach
        </ul>
        </div>
    @endif
    </div>
</div>

<style>
    #clientMattersListCard .client-matter-list-card-header {
        margin-bottom: 0;
    }
    #clientMattersListCard .client-matter-list-add-btn {
        cursor: pointer;
    }
    #clientMattersListCard .client-matter-list-close-btn {
        margin-left: 0;
        padding: 0 0.25rem;
        border: 0;
        border-radius: 4px;
        background: transparent;
        color: #b91c1c;
        font-size: 0.72rem;
        font-weight: 700;
        line-height: 1;
        cursor: pointer;
        text-decoration: none;
        flex: 0 0 auto;
    }
    #clientMattersListCard .client-matter-list-close-btn:hover {
        color: #991b1b;
        background: rgba(211, 47, 47, 0.06);
        text-decoration: none;
    }
    #clientMattersListCard .client-matter-list-close-btn:focus-visible {
        outline: 2px solid #dc2626;
        outline-offset: 2px;
    }
    #clientMattersListCard .cdn-ov-matters-empty-state {
        padding: 0.75rem 0.25rem 0.5rem;
        color: #64748b;
        font-size: 0.875rem;
        line-height: 1.45;
    }
    #clientMattersListCard .cdn-ov-matters-empty-state__text {
        margin: 0 0 0.35rem;
        font-weight: 600;
        color: #334155;
    }
    #clientMattersListCard .cdn-ov-matters-empty-state__hint {
        margin: 0;
    }
    #clientMattersListCard .cdn-ov-matter-closed-pill {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        flex: 0 0 auto;
        white-space: nowrap;
        font-size: 0.7rem;
        font-weight: 700;
        color: #64748b;
        background: #f1f5f9;
        border-radius: 999px;
        padding: 0.1rem 0.4rem;
    }
    #clientMattersListCard .cdn-ov-matter-scope-pill {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        flex: 0 0 auto;
        white-space: nowrap;
        font-size: 0.7rem;
        font-weight: 700;
        color: #1d4ed8;
        background: #eff6ff;
        border-radius: 999px;
        padding: 0.1rem 0.4rem;
    }
    #clientMattersListCard .cdn-ov-matter-row--record-scope .cdn-ov-matter-card-inner {
        border-color: #c7d7fa;
        background: linear-gradient(180deg, #f8fbff 0%, #fff 100%);
    }
</style>

<script>
(function () {
    var card = document.getElementById('clientMattersListCard');
    if (!card) {
        return;
    }

    function switchClientMatter(url) {
        if (!url) {
            return;
        }
        window.location.href = url;
    }

    card.addEventListener('click', function (event) {
        if (event.target.closest('.client-matter-list-add-btn, .client-matter-list-close-btn')) {
            return;
        }
        var row = event.target.closest('.cdn-ov-matter-row[data-matter-url]');
        if (!row) {
            return;
        }
        switchClientMatter(row.getAttribute('data-matter-url'));
    });

    card.addEventListener('keydown', function (event) {
        if (event.key !== 'Enter' && event.key !== ' ') {
            return;
        }
        if (event.target.closest('.client-matter-list-add-btn, .client-matter-list-close-btn')) {
            return;
        }
        var row = event.target.closest('.cdn-ov-matter-row[data-matter-url]');
        if (!row) {
            return;
        }
        event.preventDefault();
        switchClientMatter(row.getAttribute('data-matter-url'));
    });
})();
</script>
