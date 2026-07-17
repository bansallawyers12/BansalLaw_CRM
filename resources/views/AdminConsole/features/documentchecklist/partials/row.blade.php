@php
    $docTypeLabel = '—';
    if (isset($list->doc_type) && $list->doc_type !== '') {
        if ((int) $list->doc_type === 1) {
            $docTypeLabel = 'Personal';
        } elseif ((int) $list->doc_type === 2) {
            $docTypeLabel = 'Visa';
        } elseif ((int) $list->doc_type === 3) {
            $docTypeLabel = 'Nomination';
        }
    }
@endphp
<tr id="id_{{ $list->id }}" data-dcl-id="{{ $list->id }}">
    <td>
        <button type="button" class="btn btn-link p-0 text-start dcl-view-btn" data-dcl-id="{{ $list->id }}">
            <strong>{{ $list->name ?: config('constants.empty') }}</strong>
        </button>
    </td>
    <td><span class="badge bg-light text-dark border">{{ $docTypeLabel }}</span></td>
    <td class="text-nowrap">
        <button type="button" class="btn btn-sm btn-outline-primary dcl-view-btn" data-dcl-id="{{ $list->id }}">
            <i class="fa-regular fa-eye"></i> View
        </button>
        <button type="button" class="btn btn-sm btn-primary dcl-edit-btn" data-dcl-id="{{ $list->id }}">
            <i class="fa-solid fa-pen-to-square"></i> Edit
        </button>
        <button type="button" class="btn btn-sm btn-outline-danger dcl-delete-btn" data-dcl-id="{{ $list->id }}">
            <i class="fa-solid fa-trash"></i> Delete
        </button>
    </td>
</tr>
