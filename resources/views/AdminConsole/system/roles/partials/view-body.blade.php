@php
    $moduleAccess = [];
    if (!empty($fetchedData->module_access)) {
        $decoded = json_decode($fetchedData->module_access, true);
        $moduleAccess = is_array($decoded) ? $decoded : (array) $decoded;
    }

    $permissionGroups = [
        'Office & teams' => [1, 2, 3, 4, 5, 6],
        'Workflows' => [81],
        'Partners' => [7, 8, 9, 10, 11],
        'Products' => [12, 13, 14],
        'Clients' => [20, 21, 22, 23, 24, 25, 26],
        'Interested services' => [30, 31],
        'Matters' => [34, 35, 40, 41, 45],
        'Accounts' => [46, 47, 48, 49, 50, 51, 52, 53],
        'Quotations' => [54, 55, 56, 57, 58, 59, 60, 61],
        'Reports' => [62, 63, 64, 67, 68, 69],
        'Appointments' => [70],
        'Tasks' => [82],
        'Office check-in' => [71, 72, 73, 74, 75, 76],
        'Document checklist' => [77, 78, 79, 80],
        'View on dashboard' => [83],
    ];

    $hasPerm = function ($key) use ($moduleAccess) {
        return array_key_exists((string) $key, $moduleAccess) || array_key_exists($key, $moduleAccess);
    };
@endphp

<div class="roles-view-section">
    <h6 class="roles-view-section__title">Role details</h6>
    <dl class="roles-view-dl row mb-0">
        <div class="col-md-6">
            <dt>Name</dt>
            <dd>{{ $fetchedData->name ?: '—' }}</dd>
        </div>
        <div class="col-md-6">
            <dt>Permissions enabled</dt>
            <dd><span class="badge bg-primary">{{ count($moduleAccess) }}</span></dd>
        </div>
        <div class="col-12">
            <dt>Description</dt>
            <dd>{{ $fetchedData->description ?: '—' }}</dd>
        </div>
    </dl>
</div>

<div class="roles-view-section mt-3">
    <h6 class="roles-view-section__title">Module access</h6>
    @php $anyGroup = false; @endphp
    @foreach ($permissionGroups as $groupName => $keys)
        @php
            $enabled = array_values(array_filter($keys, $hasPerm));
        @endphp
        @if(count($enabled) > 0)
            @php $anyGroup = true; @endphp
            <div class="roles-view-perm-group">
                <strong>{{ $groupName }}</strong>
                <span class="badge bg-light text-dark border ms-1">{{ count($enabled) }}</span>
            </div>
        @endif
    @endforeach
    @if(!$anyGroup)
        <p class="text-muted mb-0">No module permissions assigned to this role.</p>
    @endif
</div>
