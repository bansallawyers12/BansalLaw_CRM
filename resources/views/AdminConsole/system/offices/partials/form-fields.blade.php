@php
    $fieldPrefix = $fieldPrefix ?? 'create';
    $office = $office ?? null;
    $accordionId = $fieldPrefix . '_office_accordion';
@endphp
<div class="office-modal-accordion" id="{{ $accordionId }}">
    <div class="accordion">
        <div class="accordion-header" role="button" data-bs-toggle="collapse" data-bs-target="#{{ $fieldPrefix }}_office_primary" aria-expanded="true" aria-controls="{{ $fieldPrefix }}_office_primary">
            <h4>Primary Information</h4>
        </div>
        <div class="accordion-body collapse show" id="{{ $fieldPrefix }}_office_primary" data-bs-parent="#{{ $accordionId }}">
            <div class="row g-3">
                <div class="col-12">
                    <div class="form-group mb-0">
                        <label for="{{ $fieldPrefix }}_office_name">Office Name <span class="span_req">*</span></label>
                        <input type="text" id="{{ $fieldPrefix }}_office_name" name="office_name" class="form-control" maxlength="255" placeholder="Enter office name" value="{{ old('office_name', optional($office)->office_name) }}" required>
                        <span class="custom-error field-error" data-field="office_name" role="alert"></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="accordion">
        <div class="accordion-header collapsed" role="button" data-bs-toggle="collapse" data-bs-target="#{{ $fieldPrefix }}_office_address" aria-expanded="false" aria-controls="{{ $fieldPrefix }}_office_address">
            <h4>Address</h4>
        </div>
        <div class="accordion-body collapse" id="{{ $fieldPrefix }}_office_address" data-bs-parent="#{{ $accordionId }}">
            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <div class="form-group mb-0">
                        <label for="{{ $fieldPrefix }}_address">Address</label>
                        <input type="text" id="{{ $fieldPrefix }}_address" name="address" class="form-control" maxlength="255" placeholder="Enter address" value="{{ old('address', optional($office)->address) }}">
                        <span class="custom-error field-error" data-field="address" role="alert"></span>
                    </div>
                </div>
                <div class="col-12 col-md-6">
                    <div class="form-group mb-0">
                        <label for="{{ $fieldPrefix }}_city">City</label>
                        <input type="text" id="{{ $fieldPrefix }}_city" name="city" class="form-control" maxlength="255" placeholder="Enter city" value="{{ old('city', optional($office)->city) }}">
                        <span class="custom-error field-error" data-field="city" role="alert"></span>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="form-group mb-0">
                        <label for="{{ $fieldPrefix }}_state">State</label>
                        <input type="text" id="{{ $fieldPrefix }}_state" name="state" class="form-control" maxlength="255" placeholder="Enter state" value="{{ old('state', optional($office)->state) }}">
                        <span class="custom-error field-error" data-field="state" role="alert"></span>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="form-group mb-0">
                        <label for="{{ $fieldPrefix }}_zip">Zip / Post Code</label>
                        <input type="text" id="{{ $fieldPrefix }}_zip" name="zip" class="form-control" maxlength="255" placeholder="Enter zip / post code" value="{{ old('zip', optional($office)->zip) }}">
                        <span class="custom-error field-error" data-field="zip" role="alert"></span>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="form-group mb-0">
                        <label for="{{ $fieldPrefix }}_country">Country <span class="span_req">*</span></label>
                        <select id="{{ $fieldPrefix }}_country" name="country" class="form-control crm-ts-plain office-ts-select" required>
                            <option value="">Select country</option>
                            @foreach($countries as $country)
                            <option value="{{ $country->sortname }}" @selected(old('country', optional($office)->country) == $country->sortname)>{{ $country->name }}</option>
                            @endforeach
                        </select>
                        <span class="custom-error field-error" data-field="country" role="alert"></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="accordion">
        <div class="accordion-header collapsed" role="button" data-bs-toggle="collapse" data-bs-target="#{{ $fieldPrefix }}_office_contact" aria-expanded="false" aria-controls="{{ $fieldPrefix }}_office_contact">
            <h4>Contact Details</h4>
        </div>
        <div class="accordion-body collapse" id="{{ $fieldPrefix }}_office_contact" data-bs-parent="#{{ $accordionId }}">
            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <div class="form-group mb-0">
                        <label for="{{ $fieldPrefix }}_email">Email <span class="span_req">*</span></label>
                        <input type="email" id="{{ $fieldPrefix }}_email" name="email" class="form-control" maxlength="255" placeholder="Enter email" value="{{ old('email', optional($office)->email) }}" required>
                        <span class="custom-error field-error" data-field="email" role="alert"></span>
                    </div>
                </div>
                <div class="col-12 col-md-6">
                    <div class="form-group mb-0">
                        <label for="{{ $fieldPrefix }}_phone">Phone Number</label>
                        <input type="tel" id="{{ $fieldPrefix }}_phone" name="phone" class="form-control" maxlength="255" placeholder="Enter phone" value="{{ old('phone', optional($office)->phone) }}">
                        <span class="custom-error field-error" data-field="phone" role="alert"></span>
                    </div>
                </div>
                <div class="col-12 col-md-6">
                    <div class="form-group mb-0">
                        <label for="{{ $fieldPrefix }}_mobile">Mobile</label>
                        <input type="tel" id="{{ $fieldPrefix }}_mobile" name="mobile" class="form-control" maxlength="255" placeholder="Enter mobile" value="{{ old('mobile', optional($office)->mobile) }}">
                        <span class="custom-error field-error" data-field="mobile" role="alert"></span>
                    </div>
                </div>
                <div class="col-12 col-md-6">
                    <div class="form-group mb-0">
                        <label for="{{ $fieldPrefix }}_contact_person">Contact Person</label>
                        <input type="text" id="{{ $fieldPrefix }}_contact_person" name="contact_person" class="form-control" maxlength="255" placeholder="Enter contact person" value="{{ old('contact_person', optional($office)->contact_person) }}">
                        <span class="custom-error field-error" data-field="contact_person" role="alert"></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="accordion mb-0">
        <div class="accordion-header collapsed" role="button" data-bs-toggle="collapse" data-bs-target="#{{ $fieldPrefix }}_office_other" aria-expanded="false" aria-controls="{{ $fieldPrefix }}_office_other">
            <h4>Other Information</h4>
        </div>
        <div class="accordion-body collapse" id="{{ $fieldPrefix }}_office_other" data-bs-parent="#{{ $accordionId }}">
            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <div class="form-group mb-0">
                        <label for="{{ $fieldPrefix }}_choose_admin">Choose Admin</label>
                        <select id="{{ $fieldPrefix }}_choose_admin" name="choose_admin" class="form-control crm-ts-plain office-ts-select">
                            <option value="">-- Choose Admin --</option>
                        </select>
                        <span class="custom-error field-error" data-field="choose_admin" role="alert"></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
