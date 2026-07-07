@php
    $mode = $mode ?? 'create';
    $isEdit = $mode === 'edit';
    $fieldPrefix = $fieldPrefix ?? ($isEdit ? 'edit_mat' : 'create_mat');
    $fetchedData = $fetchedData ?? null;
    $workflows = $workflows ?? \App\Models\Workflow::orderBy('name')->get();
    $accordionId = $fieldPrefix . '_accordion';
    $hasStream = \Illuminate\Support\Facades\Schema::hasColumn('matters', 'stream');
    $hasBlockFees = \Illuminate\Support\Facades\Schema::hasColumn('matters', 'Block_1_Description');
    $hasAdditionalFee = \Illuminate\Support\Facades\Schema::hasColumn('matters', 'additional_fee_1');
@endphp

<div class="mat-modal-accordion" id="{{ $accordionId }}">
    <div class="accordion">
        <div class="accordion-header" role="button" data-bs-toggle="collapse"
            data-bs-target="#{{ $fieldPrefix }}_info" aria-expanded="true">
            <h4>Matter information</h4>
        </div>
        <div class="accordion-body collapse show" id="{{ $fieldPrefix }}_info" data-bs-parent="#{{ $accordionId }}">
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="form-group mb-0">
                        <label for="{{ $fieldPrefix }}_title">Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" id="{{ $fieldPrefix }}_title"
                            value="{{ old('title', $isEdit ? $fetchedData->title : '') }}"
                            class="form-control" placeholder="Enter title" required autocomplete="off">
                        <span class="field-error text-danger small" data-field="title"></span>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group mb-0">
                        <label for="{{ $fieldPrefix }}_nick_name">Nick name <span class="text-danger">*</span></label>
                        <input type="text" name="nick_name" id="{{ $fieldPrefix }}_nick_name"
                            value="{{ old('nick_name', $isEdit ? $fetchedData->nick_name : '') }}"
                            class="form-control" pattern="[a-zA-Z0-9 ]+" title="Only letters, numbers, and spaces are allowed"
                            placeholder="Enter nick name" required autocomplete="off">
                        <span class="field-error text-danger small" data-field="nick_name"></span>
                    </div>
                </div>
                @if($hasStream)
                <div class="col-md-6">
                    <div class="form-group mb-0">
                        <label for="{{ $fieldPrefix }}_stream">Matter stream / forum</label>
                        <select name="stream" id="{{ $fieldPrefix }}_stream" class="form-control">
                            <option value="">— Not set —</option>
                            @foreach(config('matter_streams.streams', []) as $key => $label)
                                <option value="{{ $key }}" @selected(old('stream', $isEdit ? ($fetchedData->stream ?? '') : '') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <small class="form-text text-muted">Drives default party-role options for client matters.</small>
                        <span class="field-error text-danger small" data-field="stream"></span>
                    </div>
                </div>
                @endif
                <div class="col-md-6">
                    <div class="form-group mb-0">
                        <label for="{{ $fieldPrefix }}_workflow_id">Default workflow</label>
                        <select name="workflow_id" id="{{ $fieldPrefix }}_workflow_id" class="form-control">
                            <option value="">— Use General —</option>
                            @foreach($workflows as $w)
                                <option value="{{ $w->id }}" @selected(old('workflow_id', $isEdit ? ($fetchedData->workflow_id ?? '') : '') == $w->id)>
                                    {{ $w->name }}{{ $w->matter ? ' (' . $w->matter->title . ')' : '' }}
                                </option>
                            @endforeach
                        </select>
                        <small class="form-text text-muted">New client matters of this type will use this workflow by default.</small>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group mb-0">
                        <label for="{{ $fieldPrefix }}_is_for_company">Is this matter for companies?</label>
                        <select name="is_for_company" id="{{ $fieldPrefix }}_is_for_company" class="form-control">
                            <option value="0" @selected(old('is_for_company', $isEdit ? (($fetchedData->is_for_company ?? false) ? '1' : '0') : '0') == '0')>No (for personal clients)</option>
                            <option value="1" @selected(old('is_for_company', $isEdit ? (($fetchedData->is_for_company ?? false) ? '1' : '0') : '0') == '1')>Yes (for company clients only)</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if($hasBlockFees)
    <div class="accordion">
        <div class="accordion-header collapsed" role="button" data-bs-toggle="collapse"
            data-bs-target="#{{ $fieldPrefix }}_block_fees" aria-expanded="false">
            <h4>Block fees</h4>
        </div>
        <div class="accordion-body collapse" id="{{ $fieldPrefix }}_block_fees" data-bs-parent="#{{ $accordionId }}">
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="form-group mb-0">
                        <label for="{{ $fieldPrefix }}_block_1_desc">Block 1 description</label>
                        <input type="text" name="Block_1_Description" id="{{ $fieldPrefix }}_block_1_desc"
                            value="{{ old('Block_1_Description', $isEdit ? ($fetchedData->Block_1_Description ?? '') : '') }}"
                            class="form-control" placeholder="Block 1 description" autocomplete="off">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group mb-0">
                        <label for="{{ $fieldPrefix }}_block_1_tax">Block 1 incl. GST</label>
                        <input type="text" name="Block_1_Ex_Tax" id="{{ $fieldPrefix }}_block_1_tax"
                            value="{{ old('Block_1_Ex_Tax', $isEdit ? ($fetchedData->Block_1_Ex_Tax ?? '') : '') }}"
                            class="form-control" placeholder="Block 1 incl. GST" autocomplete="off">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group mb-0">
                        <label for="{{ $fieldPrefix }}_block_2_desc">Block 2 description</label>
                        <input type="text" name="Block_2_Description" id="{{ $fieldPrefix }}_block_2_desc"
                            value="{{ old('Block_2_Description', $isEdit ? ($fetchedData->Block_2_Description ?? '') : '') }}"
                            class="form-control" placeholder="Block 2 description" autocomplete="off">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group mb-0">
                        <label for="{{ $fieldPrefix }}_block_2_tax">Block 2 incl. GST</label>
                        <input type="text" name="Block_2_Ex_Tax" id="{{ $fieldPrefix }}_block_2_tax"
                            value="{{ old('Block_2_Ex_Tax', $isEdit ? ($fetchedData->Block_2_Ex_Tax ?? '') : '') }}"
                            class="form-control" placeholder="Block 2 incl. GST" autocomplete="off">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group mb-0">
                        <label for="{{ $fieldPrefix }}_block_3_desc">Block 3 description</label>
                        <input type="text" name="Block_3_Description" id="{{ $fieldPrefix }}_block_3_desc"
                            value="{{ old('Block_3_Description', $isEdit ? ($fetchedData->Block_3_Description ?? '') : '') }}"
                            class="form-control" placeholder="Block 3 description" autocomplete="off">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group mb-0">
                        <label for="{{ $fieldPrefix }}_block_3_tax">Block 3 incl. GST</label>
                        <input type="text" name="Block_3_Ex_Tax" id="{{ $fieldPrefix }}_block_3_tax"
                            value="{{ old('Block_3_Ex_Tax', $isEdit ? ($fetchedData->Block_3_Ex_Tax ?? '') : '') }}"
                            class="form-control" placeholder="Block 3 incl. GST" autocomplete="off">
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    @if($hasAdditionalFee)
    <div class="accordion mb-0">
        <div class="accordion-header collapsed" role="button" data-bs-toggle="collapse"
            data-bs-target="#{{ $fieldPrefix }}_additional_fee" aria-expanded="false">
            <h4>Additional fee</h4>
        </div>
        <div class="accordion-body collapse" id="{{ $fieldPrefix }}_additional_fee" data-bs-parent="#{{ $accordionId }}">
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="form-group mb-0">
                        <label for="{{ $fieldPrefix }}_additional_fee_1">Additional fee</label>
                        <input type="text" name="additional_fee_1" id="{{ $fieldPrefix }}_additional_fee_1"
                            value="{{ old('additional_fee_1', $isEdit ? ($fetchedData->additional_fee_1 ?? '') : '') }}"
                            class="form-control" placeholder="Enter additional fee" autocomplete="off">
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>

<p class="mat-modal-scroll-hint text-muted small mb-0 mt-2">
    <i class="fas fa-arrows-alt-v"></i> Expand each section or scroll to see all fields.
</p>
