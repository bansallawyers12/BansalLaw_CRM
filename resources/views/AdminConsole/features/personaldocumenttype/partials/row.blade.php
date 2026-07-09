@php
    $clientLabel = 'Common for all clients';
    if (!empty($list->client_id)) {
        $admin = \App\Models\Admin::select('first_name', 'last_name')->find($list->client_id);
        $clientLabel = $admin ? trim($admin->first_name . ' ' . $admin->last_name) : 'NA';
    }
@endphp
<tr id="id_{{ $list->id }}" data-pdt-id="{{ $list->id }}">
    <td>
        <button type="button" class="btn btn-link p-0 text-start pdt-view-btn" data-pdt-id="{{ $list->id }}">
            <strong>{{ $list->title ?: config('constants.empty') }}</strong>
        </button>
    </td>
    <td>{{ $clientLabel }}</td>
    <td class="text-nowrap">
        <button type="button" class="btn btn-sm btn-outline-primary pdt-view-btn" data-pdt-id="{{ $list->id }}">
            <i class="fa-regular fa-eye"></i> View
        </button>
        <button type="button" class="btn btn-sm btn-primary pdt-edit-btn" data-pdt-id="{{ $list->id }}">
            <i class="fa-solid fa-pen-to-square"></i> Edit
        </button>
    </td>
</tr>
