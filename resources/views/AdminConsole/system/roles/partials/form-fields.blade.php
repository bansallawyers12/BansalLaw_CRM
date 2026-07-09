@php
    $mode = $mode ?? 'create';
    $isEdit = $mode === 'edit';
    $fieldPrefix = $fieldPrefix ?? ($isEdit ? 'edit_role' : 'create_role');
    $fetchedData = $fetchedData ?? null;
    $accordionId = $fieldPrefix . '_permissions_accordion';

    $moduleAccess = [];
    if ($isEdit && $fetchedData && !empty($fetchedData->module_access)) {
        $decoded = json_decode($fetchedData->module_access, true);
        $moduleAccess = is_array($decoded) ? $decoded : (array) $decoded;
    }

    $permChecked = function ($key) use ($moduleAccess, $isEdit) {
        $oldAccess = old('module_access');
        if (is_array($oldAccess) && array_key_exists($key, $oldAccess)) {
            return true;
        }
        if (old('module_access.' . $key) !== null) {
            return (bool) old('module_access.' . $key);
        }
        return $isEdit && (array_key_exists((string) $key, $moduleAccess) || array_key_exists($key, $moduleAccess));
    };
@endphp

<div class="roles-modal-accordion" id="{{ $fieldPrefix }}_role_accordion">
    <div class="accordion">
        <div class="accordion-header" role="button" data-bs-toggle="collapse"
            data-bs-target="#{{ $fieldPrefix }}_details" aria-expanded="true">
            <h4>Role details</h4>
        </div>
        <div class="accordion-body collapse show" id="{{ $fieldPrefix }}_details" data-bs-parent="#{{ $fieldPrefix }}_role_accordion">
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="form-group mb-0">
                        <label for="{{ $fieldPrefix }}_name">Name</label>
                        <input type="text" id="{{ $fieldPrefix }}_name" name="name"
                            value="{{ old('name', $isEdit ? $fetchedData->name : '') }}"
                            class="form-control" placeholder="Role name" required>
                        <span class="field-error text-danger small" data-field="name"></span>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group mb-0">
                        <label for="{{ $fieldPrefix }}_description">Description</label>
                        <textarea class="form-control" id="{{ $fieldPrefix }}_description" name="description" rows="3" placeholder="Description">{{ old('description', $isEdit ? $fetchedData->description : '') }}</textarea>
                        <span class="field-error text-danger small" data-field="description"></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="accordion mb-0">
        <div class="accordion-header collapsed" role="button" data-bs-toggle="collapse"
            data-bs-target="#{{ $fieldPrefix }}_permissions_wrap" aria-expanded="false">
            <h4>Module permissions</h4>
        </div>
        <div class="accordion-body collapse" id="{{ $fieldPrefix }}_permissions_wrap" data-bs-parent="#{{ $fieldPrefix }}_role_accordion">
            @include('AdminConsole.system.roles.partials.permissions-accordion', [
                'fieldPrefix' => $fieldPrefix,
                'isEdit' => $isEdit,
                'permChecked' => $permChecked,
            ])
        </div>
    </div>
</div>

<p class="roles-modal-scroll-hint text-muted small mb-0 mt-2">
    <i class="fa-solid fa-up-down-left-right-v"></i> Expand each section or scroll to configure all permissions.
</p>
