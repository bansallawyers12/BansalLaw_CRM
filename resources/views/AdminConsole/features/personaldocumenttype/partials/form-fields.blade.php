@php
    $mode = $mode ?? 'create';
    $isEdit = $mode === 'edit';
    $fieldPrefix = $fieldPrefix ?? ($isEdit ? 'edit_pdt' : 'create_pdt');
    $fetchedData = $fetchedData ?? null;
    $accordionId = $fieldPrefix . '_accordion';
@endphp

<div class="pdt-modal-accordion" id="{{ $accordionId }}">
    <div class="accordion">
        <div class="accordion-header" role="button" data-bs-toggle="collapse"
            data-bs-target="#{{ $fieldPrefix }}_primary" aria-expanded="true">
            <h4>Primary information</h4>
        </div>
        <div class="accordion-body collapse show" id="{{ $fieldPrefix }}_primary" data-bs-parent="#{{ $accordionId }}">
            <div class="row g-3">
                <div class="col-md-8">
                    <div class="form-group mb-0">
                        <label for="{{ $fieldPrefix }}_title">Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" id="{{ $fieldPrefix }}_title"
                            value="{{ old('title', $isEdit ? $fetchedData->title : '') }}"
                            class="form-control" placeholder="Enter folder title" required autocomplete="off">
                        <span class="field-error text-danger small" data-field="title"></span>
                    </div>
                </div>
                <div class="col-12">
                    <p class="text-muted small mb-0">Folders without a specific client are available to all clients.</p>
                </div>
            </div>
        </div>
    </div>
</div>
