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
    if ($currentMatterRef === '' && $matterRefInUrl === '' && $clientMattersList->isNotEmpty()) {
        $currentMatterRef = (string) ($clientMattersList->first()->client_unique_matter_no ?? '');
    }

    $clientDetailTab = strtolower((string) ($activeTab ?? 'personaldetails'));
    if ($clientDetailTab === 'overview') {
        $clientDetailTab = 'personaldetails';
    }

    $clientDetailEncodeId = $encodeId ?? base64_encode(convert_uuencode($fetchedData->id));
@endphp

@if($clientMattersList->count() > 1)
<div class="card" id="clientMattersListCard">
    <h3><i class="fa-solid fa-folder-open"></i> Client Matters</h3>

    <p class="text-muted mb-2" style="font-size:13px;">
        Click a matter to switch the active matter for this client.
    </p>

    <div class="client-matter-list-grid client-matter-list-grid--header">
        <span class="client-matter-list-col client-matter-list-col--matter">Matter</span>
        <span class="client-matter-list-col client-matter-list-col--party">Party</span>
        <span class="client-matter-list-col client-matter-list-col--role">Role</span>
    </div>

    @foreach($clientMattersList as $cmRow)
        @php
            $matterNo = trim((string) ($cmRow->client_unique_matter_no ?? ''));
            $natureLabel = \App\Models\Matter::displayTitleFromJoinedRow($cmRow->matter?->title ?? null);
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
