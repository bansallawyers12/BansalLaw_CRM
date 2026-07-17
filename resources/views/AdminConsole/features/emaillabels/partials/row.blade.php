@php
    $labelColor = $list->color ?: '#3A6FA8';
    $labelIcon = $list->icon ?? 'fa-solid fa-tag';
    $createdBy = $list->user ? trim($list->user->first_name . ' ' . $list->user->last_name) : 'System';
    $updatedAt = $list->updated_at ? date('Y-m-d H:i', strtotime($list->updated_at)) : '-';
    $displayName = $list->name == '' ? config('constants.empty') : Str::limit($list->name, 50, '...');
@endphp
<tr id="id_{{ $list->id }}"
    data-label-id="{{ (int) $list->id }}"
    data-label-name="{{ e($list->name) }}"
    data-label-color="{{ e($labelColor) }}"
    data-label-icon="{{ e($labelIcon) }}"
    data-label-type="{{ e($list->type) }}"
    data-label-description="{{ e($list->description ?? '') }}"
    data-label-is-active="{{ $list->is_active ? '1' : '0' }}">
    <td>
        <span class="badge email-label-badge" style="background-color: {{ $labelColor }}20; border: 1px solid {{ $labelColor }}; color: {{ $labelColor }};">
            <i class="{{ $labelIcon }}"></i> {{ $list->name }}
        </span>
    </td>
    <td class="email-label-name-cell">{{ $displayName }}</td>
    <td class="email-label-type-cell">
        @if($list->type == 'system')
            <span class="badge bg-info text-dark">System</span>
        @else
            <span class="badge bg-secondary">Custom</span>
        @endif
    </td>
    <td class="email-label-created-by-cell">{{ $createdBy }}</td>
    <td class="email-label-status-cell">
        @if($list->is_active)
            <span class="badge bg-success">Active</span>
        @else
            <span class="badge bg-danger">Inactive</span>
        @endif
    </td>
    <td class="email-label-updated-cell">{{ $updatedAt }}</td>
    <td class="text-nowrap">
        <div class="dropdown d-inline-block">
            <button class="btn btn-primary dropdown-toggle" type="button" id="actionBtn_{{ $list->id }}"
                data-bs-toggle="dropdown"
                data-bs-popper-config='{"strategy":"fixed"}'
                aria-expanded="false"
                aria-haspopup="true">Action</button>
            <ul class="dropdown-menu dropdown-menu-end email-labels-action-menu" aria-labelledby="actionBtn_{{ $list->id }}">
                @if($list->type == 'system')
                    <li><span class="dropdown-item-text text-muted small px-3 py-2 d-block"><i class="fa-regular fa-pen-to-square me-2"></i>System labels cannot be edited</span></li>
                    <li><span class="dropdown-item-text text-muted small px-3 py-2 d-block"><i class="fa-solid fa-trash me-2"></i>System labels cannot be deleted</span></li>
                @else
                    <li><a class="dropdown-item has-icon edit-email-label-btn" href="javascript:void(0);"><i class="fa-regular fa-pen-to-square"></i> Edit</a></li>
                    <li><a class="dropdown-item has-icon delete-email-label-btn" href="javascript:void(0);"><i class="fa-solid fa-trash"></i> Delete</a></li>
                @endif
            </ul>
        </div>
    </td>
</tr>
