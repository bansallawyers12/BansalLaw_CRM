@php
    $__sch = \Illuminate\Support\Facades\Schema::class;
    $clientMattersList = collect();
    if ($__sch::hasTable('client_matters')) {
        $with = ['matter'];
        if ($__sch::hasTable('client_matter_opposing_parties')) {
            $with[] = 'opposingParties';
        }

        $clientMattersList = \App\Models\ClientMatter::query()
            ->where('client_id', $fetchedData->id)
            ->where('matter_status', 1)
            ->with($with)
            ->orderByDesc('id')
            ->get();
    }

    $matterRefInUrl = trim((string) ($matterRefInUrl ?? ''));
    $currentMatterRef = (string) (($selectedClientMatter ?? null)?->client_unique_matter_no ?? '');
    if ($currentMatterRef === '' && $matterRefInUrl !== '') {
        $currentMatterRef = $matterRefInUrl;
    } elseif ($currentMatterRef === '' && $clientMattersList->isNotEmpty()) {
        $currentMatterRef = (string) ($clientMattersList->first()->client_unique_matter_no ?? '');
    }

    $clientDetailTab = strtolower((string) ($activeTab ?? 'personaldetails'));
    if ($clientDetailTab === 'overview') {
        $clientDetailTab = 'personaldetails';
    }

    $clientDetailEncodeId = $encodeId ?? base64_encode(convert_uuencode($fetchedData->id));

    $canAddClientMatter = is_array($matterFormForLead ?? null) && empty($isClosedMatterView);

    $viewer = \Illuminate\Support\Facades\Auth::guard('admin')->user();
    $canCloseClientMatter = empty($isClosedMatterView)
        && ($viewer instanceof \App\Models\Staff && $viewer->canCloseDiscontinueMatter());

    $matterCount = $clientMattersList->count();
@endphp

<div class="card cdn-ov-card" id="clientMattersListCard">
    <header class="cdn-ov-card__head client-matter-list-card-header">
        <div class="cdn-ov-card__title">
            <span class="cdn-ov-card__icon" aria-hidden="true"><i class="fa-solid fa-folder-open"></i></span>
            <h3>Client Matters</h3>
            @if($matterCount > 0)
                <span class="cdn-ov-count" title="Active matters">{{ $matterCount }}</span>
            @endif
        </div>
        @if($canAddClientMatter)
            <button type="button"
                    class="cdn-ov-card__action client-matter-list-add-btn"
                    title="Add a new matter"
                    aria-label="Add a new matter"
                    onclick="event.stopPropagation(); if (typeof window.openAddMatterModal === 'function') { window.openAddMatterModal(); }">
                <i class="fa-solid fa-plus" aria-hidden="true"></i> Add
            </button>
        @endif
    </header>

    <div class="cdn-ov-card__body cdn-ov-card__body--scroll">
    @if($matterCount === 0)
    <p class="cdn-ov-empty">
        @if($canAddClientMatter)
            No active matters yet. Use <strong>Add</strong> to create one.
        @else
            No active matters yet.
        @endif
    </p>
    @else
    @if($matterCount > 1)
    <p class="cdn-ov-matters-hint">Select a row to switch the active matter.</p>
    @endif

    <div class="cdn-ov-scroll cdn-ov-scroll--matters" tabindex="0" role="region" aria-label="Client matters">
    <ul class="cdn-ov-matter-list" role="list">
    @foreach($clientMattersList as $cmRow)
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
            <div class="cdn-ov-matter-row__main">
                <div class="cdn-ov-matter-row__identity">
                    <span class="cdn-ov-matter-row__ref">{{ $matterRefLabel }}</span>
                    @if($ourRole !== '')
                        <span class="cdn-ov-matter-row__role">{{ $ourRole }}</span>
                    @endif
                </div>
                <div class="cdn-ov-matter-row__actions">
                    @if($isCurrent)
                        <span class="cdn-ov-matter-status">Current</span>
                        @if($canCloseClientMatter)
                            <button type="button"
                                    class="client-matter-list-close-btn"
                                    title="Close this matter"
                                    aria-label="Close this matter"
                                    data-matter-id="{{ $cmRow->id }}"
                                    onclick="event.stopPropagation(); if (typeof window.openCloseMatterModal === 'function') { window.openCloseMatterModal(this); }">Close</button>
                        @endif
                    @else
                        <span class="cdn-ov-matter-status is-muted">Switch</span>
                    @endif
                </div>
            </div>
            @if($partySummary->isNotEmpty())
                <p class="cdn-ov-matter-row__parties">{{ $partySummary->implode(' · ') }}</p>
            @else
                <p class="cdn-ov-matter-row__parties is-empty">No other parties linked</p>
            @endif
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
        if (window.confirm('Do you want to change the matter?')) {
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
