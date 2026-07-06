@php
    $encodedId = base64_encode(convert_uuencode($list->id));
    $displayName = $list->name == '' ? config('constants.empty') : Str::limit($list->name, 50, '...');
    $displaySubject = $list->subject == '' ? config('constants.empty') : Str::limit($list->subject, 50, '...');
@endphp
<tr id="id_{{ $list->id }}"
    data-template-id="{{ (int) $list->id }}"
    data-template-encoded-id="{{ $encodedId }}"
    data-template-name="{{ e($list->name) }}"
    data-template-subject="{{ e($list->subject ?? '') }}">
    <td class="crm-email-template-name-cell">{{ $displayName }}</td>
    <td class="crm-email-template-subject-cell">{{ $displaySubject }}</td>
    <td class="text-nowrap">
        <div class="dropdown d-inline-block">
            <button class="btn btn-primary dropdown-toggle" type="button" id="crmTplAction_{{ $list->id }}"
                data-bs-toggle="dropdown"
                data-bs-popper-config='{"strategy":"fixed"}'
                aria-haspopup="true"
                aria-expanded="false">Action</button>
            <ul class="dropdown-menu dropdown-menu-end crm-email-template-action-menu" aria-labelledby="crmTplAction_{{ $list->id }}">
                <li><a class="dropdown-item has-icon edit-crm-email-template-btn" href="javascript:void(0);"><i class="far fa-edit"></i> Edit</a></li>
                <li><a class="dropdown-item has-icon delete-crm-email-template-btn" href="javascript:void(0);"><i class="fas fa-trash"></i> Delete</a></li>
            </ul>
        </div>
    </td>
</tr>
