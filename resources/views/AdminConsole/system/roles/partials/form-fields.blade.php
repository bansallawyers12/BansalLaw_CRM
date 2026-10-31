@php
    $mode = $mode ?? 'create';
    $isEdit = $mode === 'edit';
    $fieldPrefix = $fieldPrefix ?? ($isEdit ? 'edit_role' : 'create_role');
    $fetchedData = $fetchedData ?? null;
@endphp

<div class="row g-3" id="{{ $fieldPrefix }}_details">
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
