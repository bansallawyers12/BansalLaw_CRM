@php
    $office = $list->office;
    $roleName = optional($list->usertype)->name ?: config('constants.empty');
    $tab = $tab ?? 'active';
    $canEdit = Auth::id() != $list->id;
@endphp
<tr id="id_{{ $list->id }}" data-staff-id="{{ $list->id }}">
    <td>
        <button type="button" class="btn btn-link p-0 text-start staff-view-btn" data-staff-id="{{ $list->id }}">
            <strong>{{ $list->first_name }} {{ $list->last_name }}</strong>
        </button>
        <div class="text-muted small">{{ $list->email }}</div>
    </td>
    <td>{{ $list->position ?: '—' }}</td>
    <td>
        @if($office && $office->id)
            <a href="{{ route('adminconsole.system.offices.view', $office->id) }}" target="_blank" rel="noopener">{{ $office->office_name }}</a>
        @else
            <span class="text-muted">No Office Assigned</span>
        @endif
    </td>
    <td>{{ $roleName }}</td>
    <td>
        @if($tab === 'invited')
            @if((int) $list->status === 1)
                <span class="badge bg-success">Active</span>
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
        <button type="button" class="btn btn-sm btn-outline-primary staff-view-btn" data-staff-id="{{ $list->id }}">
            <i class="fa-regular fa-eye"></i> View
        </button>
        @if($canEdit)
            <button type="button" class="btn btn-sm btn-primary staff-edit-btn" data-staff-id="{{ $list->id }}">
                <i class="fa-solid fa-pen-to-square"></i> Edit
            </button>
        @endif
    </td>
</tr>
