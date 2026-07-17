@php
    $clientLabel = 'Common for all clients';
    if (!empty($fetchedData->client_id)) {
        $admin = \App\Models\Admin::select('first_name', 'last_name')->find($fetchedData->client_id);
        $clientLabel = $admin ? trim($admin->first_name . ' ' . $admin->last_name) : 'NA';
    }
@endphp

<div class="pdt-view-section">
    <h6 class="pdt-view-section__title">Folder details</h6>
    <dl class="pdt-view-dl row mb-0">
        <div class="col-md-6">
            <dt>Title</dt>
            <dd>{{ $fetchedData->title ?: '—' }}</dd>
        </div>
        <div class="col-md-6">
            <dt>Client</dt>
            <dd>{{ $clientLabel }}</dd>
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
    </dl>
</div>
