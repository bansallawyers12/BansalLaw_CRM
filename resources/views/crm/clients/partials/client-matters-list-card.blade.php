@php
    $__sch = \Illuminate\Support\Facades\Schema::class;
    $clientMattersList = collect();
    if ($__sch::hasTable('client_matters')) {
        $clientMattersList = \App\Models\ClientMatter::query()
            ->where('client_id', $fetchedData->id)
            ->where('matter_status', 1)
            ->whereNotNull('sel_matter_id')
            ->with(['matter', 'opposingParties'])
            ->orderByDesc('id')
            ->get();
    }

    $currentMatterRef = (string) (($selectedClientMatter ?? null)?->client_unique_matter_no ?? '');
    if ($currentMatterRef === '' && $clientMattersList->isNotEmpty()) {
        $currentMatterRef = (string) ($clientMattersList->first()->client_unique_matter_no ?? '');
    }

    $encodedClientId = base64_encode(convert_uuencode($fetchedData->id));
    $activeTab = strtolower((string) ($activeTab ?? 'personaldetails'));
    if ($activeTab === 'overview') {
        $activeTab = 'personaldetails';
    }
@endphp

@if($clientMattersList->count() > 1)
<div class="card" id="clientMattersListCard">
    <h3><i class="fa-solid fa-folder-open"></i> Client Matters</h3>

    <p class="text-muted mb-2" style="font-size:13px;">
        Click a matter to switch the active matter for this client.
    </p>

    @foreach($clientMattersList as $cmRow)
        @php
            $matterNo = trim((string) ($cmRow->client_unique_matter_no ?? ''));
            $natureLabel = \App\Models\Matter::displayTitleFromJoinedRow($cmRow->matter->title ?? null);
            $otherPartyNames = $cmRow->opposingParties
                ->map(fn ($p) => trim((string) ($p->name ?? '')))
                ->filter()
                ->values();
            $otherPartyLabel = $otherPartyNames->isNotEmpty()
                ? $otherPartyNames->implode(', ')
                : '—';
            $isCurrent = $matterNo !== '' && $matterNo === $currentMatterRef;
            $matterUrl = $matterNo !== ''
                ? url('/clients/detail/' . $encodedClientId . '/' . rawurlencode($matterNo) . '/' . $activeTab)
                : null;
        @endphp
        <div class="field-group client-matter-list-row{{ $isCurrent ? ' client-matter-list-row--current' : '' }}"
             @if($matterUrl && ! $isCurrent)
             role="button"
             tabindex="0"
             data-matter-url="{{ $matterUrl }}"
             style="cursor:pointer;"
             @endif>
            <span class="field-label">{{ $natureLabel }}</span>
            <span class="field-value">
                {{ $otherPartyLabel }}
                @if($isCurrent)
                    <span class="badge badge-light ml-1" style="font-size:11px;font-weight:600;color:#1a73e8;">Current</span>
                @endif
            </span>
        </div>
    @endforeach
</div>

<style>
    #clientMattersListCard .client-matter-list-row--current {
        background: #f0f7ff;
        border-radius: 6px;
        padding: 4px 8px;
        margin: 0 -8px;
    }
    #clientMattersListCard .client-matter-list-row[data-matter-url]:hover {
        background: #f8f9fa;
        border-radius: 6px;
        padding: 4px 8px;
        margin: 0 -8px;
    }
</style>

<script>
(function () {
    function switchClientMatter(url) {
        if (!url) {
            return;
        }
        if (window.confirm('Do you want to change the matter?')) {
            window.location.href = url;
        }
    }

    document.querySelectorAll('#clientMattersListCard .client-matter-list-row[data-matter-url]').forEach(function (row) {
        row.addEventListener('click', function () {
            switchClientMatter(row.getAttribute('data-matter-url'));
        });
        row.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                switchClientMatter(row.getAttribute('data-matter-url'));
            }
        });
    });
})();
</script>
@endif
