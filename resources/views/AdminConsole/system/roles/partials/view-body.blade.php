<div class="roles-view-section">
    <h6 class="roles-view-section__title">Role details</h6>
    <dl class="roles-view-dl row mb-0">
        <div class="col-md-6">
            <dt>Name</dt>
            <dd>{{ $fetchedData->name ?: '—' }}</dd>
        </div>
        <div class="col-md-6">
            <dt>Status</dt>
            <dd>{{ ($fetchedData->is_active ?? true) !== false ? 'Active' : 'Inactive' }}</dd>
        </div>
        <div class="col-12">
            <dt>Description</dt>
            <dd class="mb-0">{{ $fetchedData->description ?: '—' }}</dd>
        </div>
    </dl>
</div>
