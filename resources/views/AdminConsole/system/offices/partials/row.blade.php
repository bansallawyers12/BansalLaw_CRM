@php
    $encodedId = base64_encode(convert_uuencode($list->id));
    $empty = config('constants.empty', '—');
    $countryDisplay = $list->country ?: $empty;
    if ($list->country) {
        $countryRow = \App\Models\Country::where('sortname', $list->country)->first();
        if ($countryRow) {
            $countryDisplay = $countryRow->name;
        }
    }
    $displayName = $list->office_name ? Str::limit($list->office_name, 50, '...') : $empty;
    $displayCity = $list->city ? Str::limit($list->city, 50, '...') : $empty;
    $displayCountry = Str::limit($countryDisplay, 50, '...');
    $displayMobile = $list->mobile ? Str::limit($list->mobile, 50, '...') : $empty;
    $displayPhone = $list->phone ? Str::limit($list->phone, 50, '...') : $empty;
    $displayContact = $list->contact_person ? Str::limit($list->contact_person, 50, '...') : $empty;
    $viewUrl = route('adminconsole.system.offices.view', $list->id);
@endphp
<tr id="id_{{ $list->id }}"
    data-office-id="{{ (int) $list->id }}"
    data-office-encoded-id="{{ $encodedId }}"
    data-office-name="{{ e($list->office_name) }}">
    <td class="office-name-cell">
        <a href="{{ $viewUrl }}" class="office-view-link">{{ $displayName }}</a>
    </td>
    <td class="office-city-cell">{{ $displayCity }}</td>
    <td class="office-country-cell">{{ $displayCountry }}</td>
    <td class="office-mobile-cell">{{ $displayMobile }}</td>
    <td class="office-phone-cell">{{ $displayPhone }}</td>
    <td class="office-contact-cell">{{ $displayContact }}</td>
    <td class="text-nowrap">
        <div class="dropdown d-inline-block">
            <button class="btn btn-primary dropdown-toggle" type="button" id="officeAction_{{ $list->id }}"
                data-bs-toggle="dropdown"
                data-bs-popper-config='{"strategy":"fixed"}'
                aria-haspopup="true"
                aria-expanded="false">Action</button>
            <ul class="dropdown-menu dropdown-menu-end offices-action-menu" aria-labelledby="officeAction_{{ $list->id }}">
                <li><a class="dropdown-item has-icon" href="{{ $viewUrl }}"><i class="far fa-eye"></i> View</a></li>
                <li><a class="dropdown-item has-icon edit-office-btn" href="javascript:void(0);"><i class="far fa-edit"></i> Edit</a></li>
                <li><a class="dropdown-item has-icon delete-office-btn" href="javascript:void(0);"><i class="fas fa-trash"></i> Delete</a></li>
            </ul>
        </div>
    </td>
</tr>
