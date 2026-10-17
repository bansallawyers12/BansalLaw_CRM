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

        if ($clientMattersList->isEmpty()) {
            $closedMattersList = \App\Models\ClientMatter::query()
                ->closedForClientPicker($clientIdForMatters)
                ->with($with)
                ->orderByDesc('client_matters.id')
                ->get();
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
        @if($showRecordScopeRow && $displayingClosedMatters)
            <p class="cdn-ov-matters-hint">Select <strong>{{ $recordScopeLabel }}</strong> to add a new matter, or a <strong>closed</strong> row to view read-only.</p>
        @elseif($activeMatterCount > 1)
            <p class="cdn-ov-matters-hint">Select a row to switch the active matter.</p>
        @endif

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
                    <div class="cdn-ov-matter-card-top">
                        <div class="cdn-ov-matter-badge-group">
                            <span class="cdn-ov-matter-code">{{ $recordScopeRef }}</span>
                            @if($recordScopeInUrl)
                                <span class="cdn-ov-active-matter-pill"><span class="cdn-ov-pulse-dot"></span> Current</span>
                            @else
                                <span class="cdn-ov-matter-scope-pill"><i class="fa-solid fa-user"></i> {{ $recordScopeLabel }}</span>
                            @endif
                        </div>
                    </div>
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
                $stream = (string) ($cmRow->matter?->stream ?? 'general');
                if ($stream === '') {
                    $stream = 'general';
                }
                $roleLabels = \App\Support\MatterStreamHelper::partyRolesForStream($stream);

                $parties = ($__sch::hasTable('client_matter_opposing_parties') && $cmRow->relationLoaded('opposingParties'))
                    ? $cmRow->opposingParties
                        ->sortBy([
                            fn ($party) => (int) ($party->sort_order ?? 0),
                            fn ($party) => (int) ($party->id ?? 0),
                        ])
                        ->map(function ($party) use ($roleLabels) {
                            $name = trim((string) ($party->name ?? ''));
                            $roleRaw = trim((string) ($party->party_role ?? ''));

                            return [
                                'name' => $name !== '' ? $name : 'Unnamed party',
                                'role' => $roleRaw !== '' ? ($roleLabels[$roleRaw] ?? $roleRaw) : '',
                            ];
                        })
                        ->values()
                    : collect();

                $isCurrent = $matterNo !== '' && $matterNo === $currentMatterRef;
                $matterUrl = $matterNo !== ''
                    ? route('clients.detail', [$clientDetailEncodeId, $matterNo, $clientDetailTab])
                    : null;
                $ourRole = '';
                if ($__sch::hasColumn('client_matters', 'our_party_role')) {
                    $ourRoleRaw = trim((string) ($cmRow->our_party_role ?? ''));
                    if ($ourRoleRaw !== '') {
                        $ourRole = $roleLabels[$ourRoleRaw] ?? $ourRoleRaw;
                    }
                }
                $partySummary = $parties->map(function ($party) {
                    $label = (string) ($party['name'] ?? '');
                    $role = trim((string) ($party['role'] ?? ''));
                    if ($role !== '') {
                        $label .= ' · ' . $role;
                    }

                    return $label;
                })->filter()->values();
            @endphp

            <li class="cdn-ov-matter-row{{ $isCurrent ? ' is-current' : '' }}{{ ($matterUrl && ! $isCurrent) ? ' is-switchable' : '' }}"
                @if($isCurrent) aria-current="true" @endif
                @if($matterUrl && ! $isCurrent)
                role="button"
                tabindex="0"
                data-matter-url="{{ $matterUrl }}"
                @endif>
                <div class="cdn-ov-matter-card-inner">
                    <div class="cdn-ov-matter-card-top">
                        <div class="cdn-ov-matter-badge-group">
                            <span class="cdn-ov-matter-code">{{ $matterRefLabel }}</span>
                            @if($displayingClosedMatters)
                                <span class="cdn-ov-matter-closed-pill"><i class="fa-solid fa-box-archive"></i> Closed</span>
                            @elseif($isCurrent)
                                <span class="cdn-ov-active-matter-pill"><span class="cdn-ov-pulse-dot"></span> Current</span>
                            @else
                                <span class="cdn-ov-matter-switch-pill"><i class="fa-solid fa-arrow-right-arrow-left"></i> Switch</span>
                            @endif
                        </div>
                        <div class="cdn-ov-matter-actions">
                            @if($isCurrent && $canCloseClientMatter && ! $displayingClosedMatters)
                                <button type="button"
                                        class="client-matter-list-close-btn cdn-ov-matter-close-btn"
                                        title="Close this matter"
                                        aria-label="Close this matter"
                                        data-matter-id="{{ $cmRow->id }}"
                                        onclick="event.stopPropagation(); if (typeof window.openCloseMatterModal === 'function') { window.openCloseMatterModal(this); }">
                                    <i class="fa-solid fa-ban"></i> Close
                                </button>
                            @endif
                        </div>
                    </div>

                    <div class="cdn-ov-matter-card-meta">
                        @if($ourRole !== '')
                            <span class="cdn-ov-matter-role-tag"><i class="fa-solid fa-user-shield"></i> {{ $ourRole }}</span>
                        @endif
                        <span class="cdn-ov-matter-stream-tag"><i class="fa-solid fa-scale-balanced"></i> {{ ucfirst($stream) }}</span>
                    </div>

                    <div class="cdn-ov-matter-card-parties">
                        @if($partySummary->isNotEmpty())
                            <div class="cdn-ov-party-summary-line">
                                <i class="fa-solid fa-users"></i>
                                <span>{{ $partySummary->implode(' · ') }}</span>
                            </div>
                        @else
                            <div class="cdn-ov-party-summary-line is-empty">
                                <i class="fa-regular fa-circle-dot"></i>
                                <span>No other parties linked</span>
                            </div>
                        @endif
                    </div>
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
        padding: 0.15rem 0.5rem;
        border: 0;
        border-radius: 6px;
        background: transparent;
        color: #b91c1c;
        font-size: 0.72rem;
        font-weight: 700;
        line-height: 1.3;
        cursor: pointer;
        text-decoration: underline;
        text-underline-offset: 2px;
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
        font-size: 0.7rem;
        font-weight: 700;
        color: #64748b;
        background: #f1f5f9;
        border-radius: 999px;
        padding: 0.15rem 0.5rem;
    }
    #clientMattersListCard .cdn-ov-matter-scope-pill {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        font-size: 0.7rem;
        font-weight: 700;
        color: #1d4ed8;
        background: #eff6ff;
        border-radius: 999px;
        padding: 0.15rem 0.5rem;
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

    function switchClientMatter(url, skipConfirm) {
        if (!url) {
            return;
        }
        if (skipConfirm || window.confirm('Do you want to change the matter?')) {
            window.location.href = url;
        }
    }

    card.addEventListener('click', function (event) {
        if (event.target.closest('.client-matter-list-add-btn, .client-matter-list-close-btn')) {
            return;
        }
        var row = event.target.closest('.cdn-ov-matter-row[data-matter-url]');
        if (!row) {
            return;
        }
        switchClientMatter(row.getAttribute('data-matter-url'), row.getAttribute('data-skip-confirm') === '1');
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
        switchClientMatter(row.getAttribute('data-matter-url'), row.getAttribute('data-skip-confirm') === '1');
    });
})();
</script>
