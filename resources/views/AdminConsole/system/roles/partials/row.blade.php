@php
    $moduleAccess = [];
    if (!empty($list->module_access)) {
        $decoded = json_decode($list->module_access, true);
        $moduleAccess = is_array($decoded) ? $decoded : (array) $decoded;
    }
    $permCount = count($moduleAccess);
@endphp
<tr id="id_{{ $list->id }}" data-role-id="{{ $list->id }}">
    <td>
        <button type="button" class="btn btn-link p-0 text-start roles-view-btn" data-role-id="{{ $list->id }}">
            <strong>{{ $list->name ?: config('constants.empty') }}</strong>
        </button>
    </td>
    <td>{{ $list->description ? Str::limit($list->description, 80, '...') : config('constants.empty') }}</td>
    <td><span class="badge badge-primary">{{ $permCount }}</span></td>
    <td class="text-nowrap">
        <button type="button" class="btn btn-sm btn-outline-primary roles-view-btn" data-role-id="{{ $list->id }}">
            <i class="fa-regular fa-eye"></i> View
        </button>
        <button type="button" class="btn btn-sm btn-primary roles-edit-btn" data-role-id="{{ $list->id }}">
            <i class="fa-solid fa-edit"></i> Edit
        </button>
    </td>
</tr>
