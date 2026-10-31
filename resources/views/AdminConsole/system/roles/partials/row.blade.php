@php
    $isActive = ($list->is_active ?? true) !== false;
@endphp
<tr id="id_{{ $list->id }}" data-role-id="{{ $list->id }}">
    <td>
        <button type="button" class="btn btn-link p-0 text-start roles-view-btn" data-role-id="{{ $list->id }}">
            <strong>{{ $list->name ?: config('constants.empty') }}</strong>
        </button>
    </td>
    <td>{{ $list->description ? Str::limit($list->description, 80, '...') : config('constants.empty') }}</td>
    <td>
        <div class="roles-status-cell">
            <label class="custom-switch mb-0">
                <input
                    type="checkbox"
                    class="custom-switch-input roles-status-toggle"
                    data-role-id="{{ $list->id }}"
                    @checked($isActive)
                    aria-label="{{ $isActive ? 'Active' : 'Inactive' }}"
                >
                <span class="custom-switch-indicator"></span>
            </label>
            <span class="roles-status-label">{{ $isActive ? 'Active' : 'Inactive' }}</span>
        </div>
    </td>
    <td class="text-nowrap">
        <div class="roles-row-actions">
            <button type="button" class="roles-row-icon roles-view-btn" data-role-id="{{ $list->id }}" title="View" aria-label="View {{ $list->name }}">
                <i class="fa-regular fa-eye" aria-hidden="true"></i>
            </button>
            <button type="button" class="roles-row-icon roles-edit-btn" data-role-id="{{ $list->id }}" title="Edit" aria-label="Edit {{ $list->name }}">
                <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
            </button>
        </div>
    </td>
</tr>
