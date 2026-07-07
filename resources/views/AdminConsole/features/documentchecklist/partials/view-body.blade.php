@php
    $docTypeLabel = '—';
    if (isset($fetchedData->doc_type) && $fetchedData->doc_type !== '') {
        if ((int) $fetchedData->doc_type === 1) {
            $docTypeLabel = 'Personal';
        } elseif ((int) $fetchedData->doc_type === 2) {
            $docTypeLabel = 'Visa';
        } elseif ((int) $fetchedData->doc_type === 3) {
            $docTypeLabel = 'Nomination';
        }
    }
@endphp

<div class="dcl-view-section">
    <h6 class="dcl-view-section__title">Checklist details</h6>
    <dl class="dcl-view-dl row mb-0">
        <div class="col-md-6">
            <dt>Name</dt>
            <dd>{{ $fetchedData->name ?: '—' }}</dd>
        </div>
        <div class="col-md-6">
            <dt>Document type</dt>
            <dd><span class="badge badge-light border">{{ $docTypeLabel }}</span></dd>
        </div>
        <div class="col-md-6">
            <dt>Status</dt>
            <dd>
                @if((int) ($fetchedData->status ?? 0) === 1)
                    <span class="badge badge-success">Active</span>
                @else
                    <span class="badge badge-secondary">Inactive</span>
                @endif
            </dd>
        </div>
    </dl>
</div>
