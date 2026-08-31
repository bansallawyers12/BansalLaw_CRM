@php
    $encodedId = base64_encode(convert_uuencode($wf->id));
    $stagesUrl = route('adminconsole.features.workflow.stages', $encodedId);
    $matterLabel = $wf->matter ? $wf->matter->title : '—';
@endphp
<tr id="id_{{ $wf->id }}"
    data-workflow-id="{{ (int) $wf->id }}"
    data-workflow-name="{{ e($wf->name) }}"
    data-workflow-matter-id="{{ $wf->matter_id ? (int) $wf->matter_id : '' }}"
    data-workflow-encoded-id="{{ $encodedId }}"
    data-workflow-stages-url="{{ $stagesUrl }}">
    <td class="workflow-name-cell">{{ $wf->name }}</td>
    <td class="workflow-matter-cell">{{ $matterLabel }}</td>
    <td class="workflow-stages-count-cell">{{ $wf->stages_count ?? $wf->stages->count() }}</td>
    <td class="text-nowrap">
        <div class="workflows-index-actions">
            <a class="btn btn-sm btn-primary" href="{{ $stagesUrl }}"><i class="fa-solid fa-list"></i> Manage Stages</a>
            <button type="button" class="btn btn-sm btn-secondary edit-workflow-btn"><i class="fa-regular fa-pen-to-square"></i> Edit Workflow</button>
        </div>
    </td>
</tr>
