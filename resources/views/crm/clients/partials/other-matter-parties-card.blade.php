@if(($otherMatterParties ?? collect())->isNotEmpty())
<div class="card" id="otherMatterPartiesCard">
    <div class="omp-card-header" style="display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:12px;padding-bottom:10px;border-bottom:1px solid #e9ecef;">
        <h3 style="margin:0;font-size:1.05rem;font-weight:600;flex:1;">
            <i class="fa-solid fa-users"></i> Other parties (other matters)
        </h3>
    </div>

    <p class="text-muted mb-2" style="font-size:13px;">
        Other parties recorded on this client's other matters. Click a name to open that matter.
    </p>

    @foreach($otherMatterParties as $party)
        @php
            $matter = $party->clientMatter;
            $matterNo = $matter ? trim((string) ($matter->client_unique_matter_no ?? '')) : '';
            $partyName = trim((string) ($party->name ?? ''));
            if ($partyName === '') {
                $partyName = 'Unnamed party';
            }

            $stream = (string) ($matter?->matter?->stream ?? 'general');
            if ($stream === '') {
                $stream = 'general';
            }

            $roleLabels = \App\Support\MatterStreamHelper::partyRolesForStream($stream);
            $roleRaw = trim((string) ($party->party_role ?? ''));
            $roleLabel = $roleRaw !== ''
                ? ($roleLabels[$roleRaw] ?? $roleRaw)
                : '—';

            $matterUrl = null;
            if ($matter && $matterNo !== '') {
                $matterUrl = route('clients.detail', [
                    base64_encode(convert_uuencode($fetchedData->id)),
                    $matterNo,
                ]);
            }
        @endphp
        @if($matter)
        <div class="field-group omp-party-row">
            <span class="field-value" style="font-weight:600;">
                @if($matterUrl)
                    <a href="{{ $matterUrl }}"
                       title="Open matter {{ $matterNo }}"
                       style="color:#1a73e8;text-decoration:none;">{{ $partyName }}</a>
                @else
                    {{ $partyName }}
                @endif
            </span>
            <span class="field-value" style="font-size:12px;color:#666;">{{ $roleLabel }}</span>
        </div>
        @endif
    @endforeach
</div>
@endif
