@php $comp = $fetchedData->company ?? null; @endphp
{{-- Company Information Card --}}
<div class="card" style="margin-bottom: 20px;">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <h3><i class="fas fa-building"></i> Company Information</h3>
        <a href="{{ route('clients.edit', base64_encode(convert_uuencode($fetchedData->id))) }}"
           class="btn btn-sm btn-primary">
            <i class="fas fa-edit"></i> Edit
        </a>
    </div>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin-top: 15px;">
        <div class="field-group">
            <span class="field-label">Company Name:</span>
            <span class="field-value">{{ optional($fetchedData->company)->company_name ?? 'N/A' }}</span>
        </div>
        @php
            $tradingNamesDisplay = $comp && ($comp->tradingNames?->isNotEmpty() ?? false)
                ? $comp->tradingNames->pluck('trading_name')->join(', ')
                : ($comp->trading_name ?? null);
        @endphp
        @if($tradingNamesDisplay)
        <div class="field-group">
            <span class="field-label">Trading Name(s):</span>
            <span class="field-value">{{ $tradingNamesDisplay }}</span>
        </div>
        @endif
        @if(optional($fetchedData->company)->ABN_number)
        <div class="field-group">
            <span class="field-label">ABN:</span>
            <span class="field-value">{{ $fetchedData->company->ABN_number }}</span>
        </div>
        @endif
        @if(optional($fetchedData->company)->ACN)
        <div class="field-group">
            <span class="field-label">ACN:</span>
            <span class="field-value">{{ $fetchedData->company->ACN }}</span>
        </div>
        @endif
        @if(optional($fetchedData->company)->company_type)
        <div class="field-group">
            <span class="field-label">Business Type:</span>
            <span class="field-value">{{ \App\Models\Company::businessTypeLabel($fetchedData->company->company_type) }}</span>
        </div>
        @endif
        @if(optional($fetchedData->company)->company_website)
        <div class="field-group">
            <span class="field-label">Website:</span>
            <span class="field-value">
                <a href="{{ $fetchedData->company->company_website }}" target="_blank" rel="noopener noreferrer">
                    {{ $fetchedData->company->company_website }}
                </a>
            </span>
        </div>
        @endif
        @if($comp && $comp->isTrusteeBusiness() && ($comp->trust_name || $comp->trust_abn || $comp->trustee_name || $comp->trustee_details))
        <div class="field-group" style="grid-column: 1 / -1;">
            <span class="field-label">Trust details:</span>
            <span class="field-value">
                @if($comp->trust_name) Trust name: {{ $comp->trust_name }}@endif
                @if($comp->trust_abn) @if($comp->trust_name) | @endif ABN/ACN: {{ $comp->trust_abn }}@endif
                @if($comp->trustee_name) @if($comp->trust_name || $comp->trust_abn) | @endif Trustee: {{ $comp->trustee_name }}@endif
                @if($comp->trustee_details) | {{ $comp->trustee_details }}@endif
            </span>
        </div>
        @endif
    </div>
</div>

{{-- Company Phone & Email Card --}}
<div class="card" style="margin-bottom: 20px;">
    <h3><i class="fas fa-phone"></i> Contact Information</h3>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin-top: 15px;">
        <div class="field-group">
            <span class="field-label">Phone:</span>
            <span class="field-value">
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

                        if( isset($conVal->contact_type) && $conVal->contact_type != "" ){
                            if ( $conVal->is_verified ) {
                                $phonenoStr .= $formattedPhone.' <i class="fas fa-check-circle verified-icon fa-lg" style="color: #28a745;" title="Verified on ' . ($conVal->verified_at ? $conVal->verified_at->format('M j, Y g:i A') : 'Unknown') . '"></i> <br/>';
                            } else {
                                $phonenoStr .= $formattedPhone.' <i class="far fa-circle unverified-icon fa-lg" style="color: #6c757d;" title="Not verified"></i> <br/>';
                            }
                        } else {
                            if ( isset($conVal->is_verified) && $conVal->is_verified ) {
                                $phonenoStr .= $formattedPhone.' <i class="fas fa-check-circle verified-icon fa-lg" style="color: #28a745;" title="Verified on ' . ($conVal->verified_at ? $conVal->verified_at->format('M j, Y g:i A') : 'Unknown') . '"></i> <br/>';
                            } else {
                                $phonenoStr .= $formattedPhone.' <i class="far fa-circle unverified-icon fa-lg" style="color: #6c757d;" title="Not verified"></i> <br/>';
                            }
                        }
                    }
                    echo $phonenoStr;
                } else {
                    echo "N/A";
                }?>
            </span>
        </div>

        <div class="field-group">
            <span class="field-label">Email:</span>
            <span class="field-value">
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
                        if( isset($emailVal->email_type) && $emailVal->email_type != "" ){
                            if ( $emailVal->is_verified ) {
                                $emailStr .= $emailVal->email.' <i class="fas fa-check-circle verified-icon fa-lg" style="color: #28a745;" title="Verified on ' . ($emailVal->verified_at ? $emailVal->verified_at->format('M j, Y g:i A') : 'Unknown') . '"></i> <br/>';
                            } else {
                                $emailStr .= $emailVal->email.' <i class="far fa-circle unverified-icon fa-lg" style="color: #6c757d;" title="Not verified"></i> <br/>';
                            }
                        } else {
                            if ( isset($emailVal->is_verified) && $emailVal->is_verified ) {
                                $emailStr .= $emailVal->email.' <i class="fas fa-check-circle verified-icon fa-lg" style="color: #28a745;" title="Verified on ' . ($emailVal->verified_at ? $emailVal->verified_at->format('M j, Y g:i A') : 'Unknown') . '"></i> <br/>';
                            } else {
                                $emailStr .= $emailVal->email.' <i class="far fa-circle unverified-icon fa-lg" style="color: #6c757d;" title="Not verified"></i> <br/>';
                            }
                        }
                    }
                    echo $emailStr;
                } else {
                    echo "N/A";
                }?>
            </span>
        </div>
    </div>
</div>

@php $contactPerson = optional($fetchedData->company)->contactPerson; @endphp
@if($contactPerson)
    <div class="card" style="margin-bottom: 20px;">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <h3><i class="fas fa-user-tie"></i> Primary Contact Person</h3>
            <a href="{{ route('clients.detail', base64_encode(convert_uuencode($contactPerson->id))) }}"
               class="btn btn-sm btn-outline-primary">
                <i class="fas fa-external-link-alt"></i> View Profile
            </a>
        </div>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin-top: 15px;">
            <div class="field-group">
                <span class="field-label">Name:</span>
                <span class="field-value">
                    <a href="{{ route('clients.detail', base64_encode(convert_uuencode($contactPerson->id))) }}"
                       style="color: #007bff; text-decoration: none;">
                        {{ $contactPerson->first_name }} {{ $contactPerson->last_name }}
                    </a>
                </span>
            </div>
            @if(optional($fetchedData->company)->contact_person_position)
            <div class="field-group">
                <span class="field-label">Position:</span>
                <span class="field-value">{{ $fetchedData->company->contact_person_position }}</span>
            </div>
            @endif
            @if($contactPerson->email)
            <div class="field-group">
                <span class="field-label">Email:</span>
                <span class="field-value">
                    <a href="mailto:{{ $contactPerson->email }}" style="color: #007bff; text-decoration: none;">
                        {{ $contactPerson->email }}
                    </a>
                </span>
            </div>
            @endif
            @if($contactPerson->phone)
            <div class="field-group">
                <span class="field-label">Phone:</span>
                <span class="field-value">{{ $contactPerson->phone }}</span>
            </div>
            @endif
            @if($contactPerson->client_id)
            <div class="field-group">
                <span class="field-label">Client ID:</span>
                <span class="field-value">{{ $contactPerson->client_id }}</span>
            </div>
            @endif
        </div>
    </div>
@endif
