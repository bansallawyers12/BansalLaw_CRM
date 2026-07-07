@php
    $mode = $mode ?? 'create';
    $isEdit = $mode === 'edit';
    $fieldPrefix = $fieldPrefix ?? ($isEdit ? 'edit_dcl' : 'create_dcl');
    $fetchedData = $fetchedData ?? null;
    $accordionId = $fieldPrefix . '_accordion';
    $selectedDocType = old('doc_type', $isEdit ? ($fetchedData->doc_type ?? '') : '');
@endphp

<div class="dcl-modal-accordion" id="{{ $accordionId }}">
    <div class="accordion">
        <div class="accordion-header" role="button" data-bs-toggle="collapse"
            data-bs-target="#{{ $fieldPrefix }}_info" aria-expanded="true">
            <h4>Checklist information</h4>
        </div>
        <div class="accordion-body collapse show" id="{{ $fieldPrefix }}_info" data-bs-parent="#{{ $accordionId }}">
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="form-group mb-0">
                        <label for="{{ $fieldPrefix }}_name">Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="{{ $fieldPrefix }}_name"
                            value="{{ old('name', $isEdit ? $fetchedData->name : '') }}"
                            class="form-control" placeholder="Enter checklist name" required autocomplete="off">
                        <span class="field-error text-danger small" data-field="name"></span>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group mb-0">
                        <label for="{{ $fieldPrefix }}_doc_type">Document type <span class="text-danger">*</span></label>
                        <select name="doc_type" id="{{ $fieldPrefix }}_doc_type" class="form-control" required>
                            <option value="">Select document type</option>
                            <option value="1" @selected((string) $selectedDocType === '1')>Personal</option>
                            <option value="2" @selected((string) $selectedDocType === '2')>Visa</option>
                            <option value="3" @selected((string) $selectedDocType === '3')>Nomination</option>
                        </select>
                        <span class="field-error text-danger small" data-field="doc_type"></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
