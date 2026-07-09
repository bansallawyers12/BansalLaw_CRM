@php
    $clientLabel = 'Common for all clients';
    if (!empty($list->client_id)) {
        $admin = \App\Models\Admin::select('first_name', 'last_name')->find($list->client_id);
        $clientLabel = $admin ? trim($admin->first_name . ' ' . $admin->last_name) : 'NA';
    }

    $clientMatterLabel = 'Common for all client matters';
    if (!empty($list->client_matter_id)) {
        $clientMatter = \App\Models\ClientMatter::select('sel_matter_id')->find($list->client_matter_id);
        if ($clientMatter) {
            $matter = \App\Models\Matter::select('title', 'nick_name')->find($clientMatter->sel_matter_id);
            $clientMatterLabel = $matter ? ($matter->title . ' (' . $matter->nick_name . ')') : 'NA';
        }
    }
@endphp
<tr id="id_{{ $list->id }}" data-mdt-id="{{ $list->id }}">
    <td>
        <button type="button" class="btn btn-link p-0 text-start mdt-view-btn" data-mdt-id="{{ $list->id }}">
            <strong>{{ $list->title ?: config('constants.empty') }}</strong>
        </button>
    </td>
    <td>{{ $clientLabel }}</td>
    <td>{{ $clientMatterLabel }}</td>
    <td class="text-nowrap">
        <button type="button" class="btn btn-sm btn-outline-primary mdt-view-btn" data-mdt-id="{{ $list->id }}">
            <i class="fa-regular fa-eye"></i> View
        </button>
        <button type="button" class="btn btn-sm btn-primary mdt-edit-btn" data-mdt-id="{{ $list->id }}">
            <i class="fa-solid fa-edit"></i> Edit
        </button>
    </td>
</tr>
