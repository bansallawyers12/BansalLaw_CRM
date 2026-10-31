@php
    $roleName = optional($list->usertype)->name ?: config('constants.empty');
    $tab = $tab ?? 'active';
    $canEdit = Auth::id() != $list->id;
    $staffLabel = trim($list->first_name . ' ' . $list->last_name);
@endphp
<tr id="id_{{ $list->id }}" data-staff-id="{{ $list->id }}">
    <td>
        <button type="button" class="btn btn-link p-0 text-start staff-view-btn" data-staff-id="{{ $list->id }}">
            <strong>{{ $list->first_name }} {{ $list->last_name }}</strong>
        </button>
        <div class="text-muted small">{{ $list->email }}</div>
    </td>
    <td>{{ $roleName }}</td>
    <td>
        @if($tab === 'invited')
            <span class="badge bg-info text-dark">Invited</span>
        @elseif($tab === 'all')
            @if((int) $list->status === 1)
                <span class="badge bg-success">Active</span>
            @elseif((int) $list->status === 2)
                <span class="badge bg-info text-dark">Invited</span>
            @else
                <span class="badge bg-secondary">Inactive</span>
            @endif
        @else
            <div class="custom-switches">
                <label>
                    <input value="1"
                        data-id="{{ $list->id }}"
                        data-status="{{ $list->status }}"
                        data-col="status"
                        data-table="staff"
                        type="checkbox"
                        class="change-status custom-switch-input"
                        {{ (int) $list->status === 1 ? 'checked' : '' }}>
                    <span class="custom-switch-indicator"></span>
                </label>
            </div>
        @endif
    </td>
    <td class="text-nowrap">
        <div class="staff-row-actions">
            <button type="button" class="staff-row-icon staff-view-btn" data-staff-id="{{ $list->id }}" title="View" aria-label="View {{ $staffLabel }}">
                <i class="fa-regular fa-eye" aria-hidden="true"></i>
            </button>
            @if($canEdit)
                <button type="button" class="staff-row-icon staff-edit-btn" data-staff-id="{{ $list->id }}" title="Edit" aria-label="Edit {{ $staffLabel }}">
                    <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                </button>
            @endif
        </div>
    </td>
</tr>
