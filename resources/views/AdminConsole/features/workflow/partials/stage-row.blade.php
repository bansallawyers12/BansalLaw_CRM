@php
    $countmatters = $matterCounts[$list->id] ?? 0;
    $stageFrozen = $list->isFrozen();
    $displayName = $list->name ?: config('constants.empty', '—');
    $encodedStageId = base64_encode(convert_uuencode($list->id));
@endphp
<tr id="id_{{ $list->id }}"
    data-stage-id="{{ (int) $list->id }}"
    data-stage-encoded-id="{{ $encodedStageId }}"
    data-stage-name="{{ e($list->name) }}"
    data-stage-frozen="{{ $stageFrozen ? '1' : '0' }}"
    data-stage-matter-count="{{ (int) $countmatters }}">
    <td class="workflow-stage-name-cell">
        <span class="stage-name-text">{{ $displayName }}</span>
        @if($stageFrozen)
        <span class="badge badge-secondary ms-1 align-middle stage-protected-badge" title="This stage cannot be renamed or deleted">Protected</span>
        @endif
    </td>
    <td class="workflow-stage-matter-count-cell">{{ $countmatters }}</td>
    <td class="workflow-stage-actions-col">
        <div class="workflow-stage-cell-actions">
            <button type="button" class="btn btn-sm btn-primary edit-workflow-stage-btn" title="{{ $stageFrozen ? 'View (protected — name cannot be changed)' : 'Edit stage name' }}"><i class="fa-regular fa-edit"></i> Edit</button>
            <button type="button" class="btn btn-sm btn-info add-after-workflow-stage-btn" data-after-stage-id="{{ (int) $list->id }}" title="Insert a new stage immediately after this one"><i class="fa-solid fa-plus"></i> Add After</button>
            @if($stageFrozen)
            <button type="button" class="btn btn-sm btn-outline-secondary" disabled title="Protected stages cannot be deleted"><i class="fa-solid fa-trash"></i> Delete</button>
            @else
            <button type="button" class="btn btn-sm btn-outline-danger delete-workflow-stage-btn"><i class="fa-solid fa-trash"></i> Delete</button>
            @endif
        </div>
    </td>
</tr>
