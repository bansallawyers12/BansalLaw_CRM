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
    <td class="text-nowrap text-end offices-actions-cell">
        <button type="button" class="btn btn-sm btn-outline-primary office-view-btn" data-office-view-url="{{ $viewUrl }}">
            <i class="fa-regular fa-eye"></i> View
        </button>
        <button type="button" class="btn btn-sm btn-primary edit-office-btn">
            <i class="fa-solid fa-pen-to-square"></i> Edit
        </button>
        <div class="dropdown d-inline-block">
            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button"
                id="officeAction_{{ $list->id }}"
                data-bs-toggle="dropdown"
                data-bs-popper-config='{"strategy":"fixed"}'
                aria-expanded="false">More</button>
            <ul class="dropdown-menu dropdown-menu-end offices-action-menu" aria-labelledby="officeAction_{{ $list->id }}">
                <li>
                    <button type="button" class="dropdown-item text-danger delete-office-btn">
                        <i class="fa-solid fa-trash"></i> Delete
                    </button>
                </li>
            </ul>
        </div>
    </td>
</tr>
