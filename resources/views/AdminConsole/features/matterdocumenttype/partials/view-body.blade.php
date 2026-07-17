@php
    $clientLabel = 'Common for all clients';
    if (!empty($fetchedData->client_id)) {
        $admin = \App\Models\Admin::select('first_name', 'last_name')->find($fetchedData->client_id);
        $clientLabel = $admin ? trim($admin->first_name . ' ' . $admin->last_name) : 'NA';
    }

    $clientMatterLabel = 'Common for all client matters';
    if (!empty($fetchedData->client_matter_id)) {
        $clientMatter = \App\Models\ClientMatter::select('sel_matter_id')->find($fetchedData->client_matter_id);
        if ($clientMatter) {
            $matter = \App\Models\Matter::select('title', 'nick_name')->find($clientMatter->sel_matter_id);
            $clientMatterLabel = $matter ? ($matter->title . ' (' . $matter->nick_name . ')') : 'NA';
        }
    }
@endphp

<div class="mdt-view-section">
    <h6 class="mdt-view-section__title">Folder details</h6>
    <dl class="mdt-view-dl row mb-0">
        <div class="col-md-6">
            <dt>Title</dt>
            <dd>{{ $fetchedData->title ?: '—' }}</dd>
        </div>
        <div class="col-md-6">
            <dt>Status</dt>
            <dd>
                @if((int) ($fetchedData->status ?? 0) === 1)
                    <span class="badge bg-success">Active</span>
                @else
                    <span class="badge bg-secondary">Inactive</span>
                @endif
            </dd>
        </div>
        <div class="col-md-6">
            <dt>Client</dt>
            <dd>{{ $clientLabel }}</dd>
        </div>
        <div class="col-md-6">
            <dt>Client matter</dt>
            <dd>{{ $clientMatterLabel }}</dd>
        </div>
    </dl>
</div>
