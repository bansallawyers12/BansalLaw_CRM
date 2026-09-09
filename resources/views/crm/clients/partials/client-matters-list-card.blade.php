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

    <div class="cdn-ov-card__body">
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
    <p class="cdn-ov-matters-hint">Click a matter to switch the active matter for this client.</p>
    @endif

    <div class="cdn-ov-matter-list">
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
        @endphp

        <div class="cdn-ov-matter-item{{ $isCurrent ? ' is-current' : '' }}"
             @if($matterUrl && ! $isCurrent)
             role="button"
             tabindex="0"
             data-matter-url="{{ $matterUrl }}"
             @endif>
            <div class="cdn-ov-matter-item__top">
                <div class="cdn-ov-matter-item__ref">{{ $matterRefLabel }}</div>
                <div class="cdn-ov-matter-item__actions">
                    @if($isCurrent)
                        <span class="cdn-ov-matter-chip is-current">Current</span>
                        @if($canCloseClientMatter)
                            <button type="button"
                                    class="client-matter-list-close-btn"
                                    title="Close this matter"
                                    aria-label="Close this matter"
                                    data-matter-id="{{ $cmRow->id }}"
                                    onclick="event.stopPropagation(); if (typeof window.openCloseMatterModal === 'function') { window.openCloseMatterModal(this); }">Close</button>
                        @endif
                    @else
                        <span class="cdn-ov-matter-chip">Switch</span>
                    @endif
                </div>
            </div>
            @if($ourRole !== '')
                <div class="cdn-ov-matter-item__meta"><span>Our role</span> {{ $ourRole }}</div>
            @endif
            @if($parties->isNotEmpty())
                <ul class="cdn-ov-matter-item__parties">
                    @foreach($parties as $party)
                        <li>
                            <strong>{{ $party['name'] }}</strong>
                            @if(($party['role'] ?? '') !== '')
                                <span>{{ $party['role'] }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="cdn-ov-matter-item__empty-parties">No other parties linked</p>
            @endif
        </div>
    @endforeach
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
        padding: 0.2rem 0.55rem;
        border: 1px solid rgba(211, 47, 47, 0.25);
        border-radius: 999px;
        background: rgba(211, 47, 47, 0.08);
        color: #b91c1c;
        font-size: 0.7rem;
        font-weight: 700;
        line-height: 1.3;
        cursor: pointer;
        vertical-align: middle;
    }
    #clientMattersListCard .client-matter-list-close-btn:hover {
        background: rgba(211, 47, 47, 0.14);
        color: #991b1b;
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
        var row = event.target.closest('.cdn-ov-matter-item[data-matter-url]');
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
        var row = event.target.closest('.cdn-ov-matter-item[data-matter-url]');
        if (!row) {
            return;
        }
        event.preventDefault();
        switchClientMatter(row.getAttribute('data-matter-url'));
    });
})();
</script>
