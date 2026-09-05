@php $comp = $fetchedData->company ?? null; @endphp
{{-- Company Information Card --}}
<article class="card cdn-ov-card">
    <header class="cdn-ov-card__head">
        <div class="cdn-ov-card__title">
            <span class="cdn-ov-card__icon" aria-hidden="true"><i class="fa-solid fa-building"></i></span>
            <h3>Company Information</h3>
        </div>
        @if(empty($isClosedMatterView))
        <a href="{{ route('clients.edit', base64_encode(convert_uuencode($fetchedData->id))) }}"
           class="cdn-ov-card__action">
            <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i> Edit
        </a>
        @endif
    </header>
    <div class="cdn-ov-card__body cdn-ov-card__body--grid">
        <div class="cdn-ov-field">
            <span class="cdn-ov-field__label">Company Name</span>
            <span class="cdn-ov-field__value">{{ optional($fetchedData->company)->company_name ?? 'N/A' }}</span>
        </div>
        @php
            $tradingNamesDisplay = $comp && ($comp->tradingNames?->isNotEmpty() ?? false)
                ? $comp->tradingNames->pluck('trading_name')->join(', ')
                : ($comp->trading_name ?? null);
        @endphp
        @if($tradingNamesDisplay)
        <div class="cdn-ov-field">
            <span class="cdn-ov-field__label">Trading Name(s)</span>
            <span class="cdn-ov-field__value">{{ $tradingNamesDisplay }}</span>
        </div>
        @endif
        @if(optional($fetchedData->company)->ABN_number)
        <div class="cdn-ov-field">
            <span class="cdn-ov-field__label">ABN</span>
            <span class="cdn-ov-field__value">{{ $fetchedData->company->ABN_number }}</span>
        </div>
        @endif
        @if(optional($fetchedData->company)->ACN)
        <div class="cdn-ov-field">
            <span class="cdn-ov-field__label">ACN</span>
            <span class="cdn-ov-field__value">{{ $fetchedData->company->ACN }}</span>
        </div>
        @endif
        @if(optional($fetchedData->company)->company_type)
        <div class="cdn-ov-field">
            <span class="cdn-ov-field__label">Business Type</span>
            <span class="cdn-ov-field__value">{{ \App\Models\Company::businessTypeLabel($fetchedData->company->company_type) }}</span>
        </div>
        @endif
        @if(optional($fetchedData->company)->company_website)
        <div class="cdn-ov-field">
            <span class="cdn-ov-field__label">Website</span>
            <span class="cdn-ov-field__value">
                <a href="{{ $fetchedData->company->company_website }}" target="_blank" rel="noopener noreferrer">
                    {{ $fetchedData->company->company_website }}
                </a>
            </span>
        </div>
        @endif
        @if($comp && $comp->isTrusteeBusiness() && ($comp->trust_name || $comp->trust_abn || $comp->trustee_name || $comp->trustee_details))
        <div class="cdn-ov-field cdn-ov-field--full">
            <span class="cdn-ov-field__label">Trust details</span>
            <span class="cdn-ov-field__value">
                @if($comp->trust_name) Trust name: {{ $comp->trust_name }}@endif
                @if($comp->trust_abn) @if($comp->trust_name) · @endif ABN/ACN: {{ $comp->trust_abn }}@endif
                @if($comp->trustee_name) @if($comp->trust_name || $comp->trust_abn) · @endif Trustee: {{ $comp->trustee_name }}@endif
                @if($comp->trustee_details) · {{ $comp->trustee_details }}@endif
            </span>
        </div>
        @endif
    </div>
    @if(!empty($cdnHeroLastUpdateOn))
    <footer class="cdn-ov-card__foot">
        Last update on {{ $cdnHeroLastUpdateOn }}
    </footer>
    @endif
</article>

{{-- Company Phone & Email Card --}}
<article class="card cdn-ov-card">
    <header class="cdn-ov-card__head">
        <div class="cdn-ov-card__title">
            <span class="cdn-ov-card__icon" aria-hidden="true"><i class="fa-solid fa-phone"></i></span>
            <h3>Contact Information</h3>
        </div>
    </header>
    <div class="cdn-ov-card__body cdn-ov-card__body--grid">
        <div class="cdn-ov-field">
            <span class="cdn-ov-field__label">Phone</span>
            <span class="cdn-ov-field__value">
                <?php
                if( \App\Models\ClientContact::where('client_id', $fetchedData->id)->exists()) {
                    $companyContacts = \App\Models\ClientContact::select('phone','country_code','contact_type','is_verified','verified_at')
                        ->where('client_id', $fetchedData->id)
                        ->where('contact_type', '!=', 'Not In Use')
                        ->get();
                } else {
                    if( \App\Models\Admin::where('id', $fetchedData->id)->exists()){
                        $companyContacts = \App\Models\Admin::select('phone','country_code','contact_type')
                            ->where('id', $fetchedData->id)
                            ->get();
                    } else {
                        $companyContacts = [];
                    }
                }
                if( !empty($companyContacts) && count($companyContacts)>0 ){
                    $phonenoStr = "";
                    foreach($companyContacts as $conKey=>$conVal){
                        if( isset($conVal->country_code) && $conVal->country_code != "" ){
                            $country_code = $conVal->country_code;
                        } else {
                            $country_code = "";
                        }

                        $formattedPhone = \App\Helpers\PhoneValidationHelper::formatAustralianPhone($conVal->phone, $country_code);

                        if ( isset($conVal->is_verified) && $conVal->is_verified ) {
                            $phonenoStr .= '<span class="cdn-ov-contact-line">'.$formattedPhone.' <i class="fa-solid fa-circle-check cdn-ov-verified" title="Verified on ' . ($conVal->verified_at ? $conVal->verified_at->format('d/m/Y g:i A') : 'Unknown') . '"></i></span>';
                        } else {
                            $phonenoStr .= '<span class="cdn-ov-contact-line">'.$formattedPhone.' <i class="fa-regular fa-circle cdn-ov-unverified" title="Not verified"></i></span>';
                        }
                    }
                    echo $phonenoStr;
                } else {
                    echo "N/A";
                }?>
            </span>
        </div>

        <div class="cdn-ov-field">
            <span class="cdn-ov-field__label">Email</span>
            <span class="cdn-ov-field__value">
                <?php
                if( \App\Models\ClientEmail::where('client_id', $fetchedData->id)->exists()) {
                    $companyEmails = \App\Models\ClientEmail::select('email','email_type','is_verified','verified_at')
                        ->where('client_id', $fetchedData->id)
                        ->get();
                } else {
                    if( \App\Models\Admin::where('id', $fetchedData->id)->exists()){
                        $companyEmails = \App\Models\Admin::select('email','email_type')
                            ->where('id', $fetchedData->id)
                            ->get();
                    } else {
                        $companyEmails = [];
                    }
                }
                if( !empty($companyEmails) && count($companyEmails)>0 ){
                    $emailStr = "";
                    foreach($companyEmails as $emailKey=>$emailVal){
                        if ( isset($emailVal->is_verified) && $emailVal->is_verified ) {
                            $emailStr .= '<span class="cdn-ov-contact-line"><a href="mailto:'.e($emailVal->email).'">'.e($emailVal->email).'</a> <i class="fa-solid fa-circle-check cdn-ov-verified" title="Verified on ' . ($emailVal->verified_at ? $emailVal->verified_at->format('d/m/Y g:i A') : 'Unknown') . '"></i></span>';
                        } else {
                            $emailStr .= '<span class="cdn-ov-contact-line"><a href="mailto:'.e($emailVal->email).'">'.e($emailVal->email).'</a> <i class="fa-regular fa-circle cdn-ov-unverified" title="Not verified"></i></span>';
                        }
                    }
                    echo $emailStr;
                } else {
                    echo "N/A";
                }?>
            </span>
        </div>
    </div>
</article>

@php $contactPerson = optional($fetchedData->company)->contactPerson; @endphp
@if($contactPerson)
    <article class="card cdn-ov-card">
        <header class="cdn-ov-card__head">
            <div class="cdn-ov-card__title">
                <span class="cdn-ov-card__icon" aria-hidden="true"><i class="fa-solid fa-user-tie"></i></span>
                <h3>Primary Contact Person</h3>
            </div>
            <a href="{{ route('clients.detail', base64_encode(convert_uuencode($contactPerson->id))) }}"
               class="cdn-ov-card__action">
                <i class="fa-solid fa-up-right-from-square" aria-hidden="true"></i> View Profile
            </a>
        </header>
        <div class="cdn-ov-card__body cdn-ov-card__body--grid">
            <div class="cdn-ov-field">
                <span class="cdn-ov-field__label">Name</span>
                <span class="cdn-ov-field__value">
                    <a href="{{ route('clients.detail', base64_encode(convert_uuencode($contactPerson->id))) }}">
                        {{ $contactPerson->first_name }} {{ $contactPerson->last_name }}
                    </a>
                </span>
            </div>
            @if(optional($fetchedData->company)->contact_person_position)
            <div class="cdn-ov-field">
                <span class="cdn-ov-field__label">Position</span>
                <span class="cdn-ov-field__value">{{ $fetchedData->company->contact_person_position }}</span>
            </div>
            @endif
            @if($contactPerson->email)
            <div class="cdn-ov-field">
                <span class="cdn-ov-field__label">Email</span>
                <span class="cdn-ov-field__value">
                    <a href="mailto:{{ $contactPerson->email }}">{{ $contactPerson->email }}</a>
                </span>
            </div>
            @endif
            @if($contactPerson->phone)
            <div class="cdn-ov-field">
                <span class="cdn-ov-field__label">Phone</span>
                <span class="cdn-ov-field__value">{{ $contactPerson->phone }}</span>
            </div>
            @endif
            @if($contactPerson->client_id)
            <div class="cdn-ov-field">
                <span class="cdn-ov-field__label">Client ID</span>
                <span class="cdn-ov-field__value">{{ $contactPerson->client_id }}</span>
            </div>
            @endif
        </div>
    </article>
@endif
