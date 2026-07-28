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
    $matterCount = $clientMattersList->count();
    $showClientMattersListCard = $matterCount > 1
        || ($canAddClientMatter && $matterCount >= 1);
@endphp

@if($showClientMattersListCard)
<div class="card" id="clientMattersListCard">
    <div class="client-matter-list-card-header">
        <h3><i class="fa-solid fa-folder-open"></i> Client Matters</h3>
        @if($canAddClientMatter)
            <button type="button"
                    class="client-matter-list-add-btn"
                    title="Add a new matter for this client"
                    aria-label="Add a new matter for this client"
                    onclick="if (typeof window.openAddMatterModal === 'function') { window.openAddMatterModal(); }">+</button>
        @endif
    </div>

    @if($matterCount > 1)
    <p class="text-muted mb-2" style="font-size:13px;">
        Click a matter to switch the active matter for this client.
    </p>
    @endif

    <div class="client-matter-list-grid client-matter-list-grid--header">
        <span class="client-matter-list-col client-matter-list-col--matter">Matter</span>
        <span class="client-matter-list-col client-matter-list-col--party">Party</span>
        <span class="client-matter-list-col client-matter-list-col--role">Role</span>
    </div>

    @foreach($clientMattersList as $cmRow)
        @php
            $matterNo = trim((string) ($cmRow->client_unique_matter_no ?? ''));
            $natureLabel = \App\Models\Matter::displayTitleFromJoinedRow($cmRow->matter?->title ?? null);
            if ($natureLabel === '' || $natureLabel === '—') {
                $natureLabel = $matterNo !== '' ? $matterNo : ('Matter #' . (int) ($cmRow->id ?? 0));
            }
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
                            'role' => $roleRaw !== '' ? ($roleLabels[$roleRaw] ?? $roleRaw) : '—',
                        ];
                    })
                    ->values()
                : collect();

            if ($parties->isEmpty()) {
                $parties = collect([['name' => '—', 'role' => '—']]);
            }

            $isCurrent = $matterNo !== '' && $matterNo === $currentMatterRef;
            $matterUrl = $matterNo !== ''
                ? route('clients.detail', [$clientDetailEncodeId, $matterNo, $clientDetailTab])
                : null;
        @endphp

        @foreach($parties as $party)
            <div class="client-matter-list-grid client-matter-list-row{{ $isCurrent ? ' client-matter-list-row--current' : '' }}{{ ! $loop->parent->first && $loop->first ? ' client-matter-list-row--matter-start' : '' }}"
                 @if($matterUrl && ! $isCurrent)
                 role="button"
                 tabindex="0"
                 data-matter-url="{{ $matterUrl }}"
                 @endif>
                <span class="client-matter-list-col client-matter-list-col--matter">
                    @if($loop->first)
                        {{ $natureLabel }}
                        @if($isCurrent)
                            <span class="badge badge-light ml-1" style="font-size:11px;font-weight:600;color:#1a73e8;">Current</span>
                        @endif
                    @endif
                </span>
                <span class="client-matter-list-col client-matter-list-col--party">{{ $party['name'] }}</span>
                <span class="client-matter-list-col client-matter-list-col--role">{{ $party['role'] }}</span>
            </div>
        @endforeach
    @endforeach
</div>

<style>
    #clientMattersListCard .client-matter-list-card-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        margin-bottom: 12px;
    }
    #clientMattersListCard .client-matter-list-card-header h3 {
        margin: 0;
        flex: 1;
    }
    #clientMattersListCard .client-matter-list-add-btn {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        border: none;
        font-weight: 700;
        font-size: 1.05rem;
        line-height: 1;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        padding: 0;
        background: #dbeafe;
        color: #1d4ed8;
        transition: transform 0.12s ease, box-shadow 0.12s ease, background 0.12s ease;
    }
    #clientMattersListCard .client-matter-list-add-btn:hover {
        background: #bfdbfe;
        color: #1e3a8a;
        transform: scale(1.05);
    }
    #clientMattersListCard .client-matter-list-add-btn:focus-visible {
        outline: 2px solid #2563eb;
        outline-offset: 2px;
    }
    #clientMattersListCard .client-matter-list-grid {
        display: grid;
        grid-template-columns: minmax(0, 1.1fr) minmax(0, 1.4fr) minmax(0, 0.9fr);
        gap: 4px 12px;
        align-items: start;
        padding: 4px 8px;
        margin: 0 -8px;
    }
    #clientMattersListCard .client-matter-list-grid--header {
        margin-bottom: 4px;
        padding-bottom: 6px;
        border-bottom: 1px solid #e9ecef;
        font-size: 12px;
        font-weight: 600;
        color: #6c757d;
    }
    #clientMattersListCard .client-matter-list-col--party {
        font-weight: 600;
    }
    #clientMattersListCard .client-matter-list-col--role {
        font-size: 12px;
        color: #666;
    }
    #clientMattersListCard .client-matter-list-row--matter-start {
        margin-top: 6px;
        padding-top: 8px;
        border-top: 1px dashed #e9ecef;
    }
    #clientMattersListCard .client-matter-list-row--current {
        background: #f0f7ff;
        border-radius: 6px;
    }
    #clientMattersListCard .client-matter-list-row[data-matter-url] {
        cursor: pointer;
    }
    #clientMattersListCard .client-matter-list-row[data-matter-url]:hover {
        background: #f8f9fa;
        border-radius: 6px;
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
        if (event.target.closest('.client-matter-list-add-btn')) {
            return;
        }
        var row = event.target.closest('.client-matter-list-row[data-matter-url]');
        if (!row) {
            return;
        }
        switchClientMatter(row.getAttribute('data-matter-url'));
    });

    card.addEventListener('keydown', function (event) {
        if (event.key !== 'Enter' && event.key !== ' ') {
            return;
        }
        var row = event.target.closest('.client-matter-list-row[data-matter-url]');
        if (!row) {
            return;
        }
        event.preventDefault();
        switchClientMatter(row.getAttribute('data-matter-url'));
    });
})();
</script>
@endif
