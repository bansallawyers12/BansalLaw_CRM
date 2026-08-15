<div class="tab-pane active" id="companydetails-tab">
    @php $comp = $fetchedData->company ?? null; @endphp
    <div class="content-grid">
        {{-- Company Information Card --}}
        <div class="card" style="margin-bottom: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <h3><i class="fa-solid fa-building"></i> Company Information</h3>
                <a href="{{ route('clients.edit', base64_encode(convert_uuencode($fetchedData->id))) }}" 
                   class="btn btn-sm btn-primary">
                    <i class="fa-solid fa-pen-to-square"></i> Edit
                </a>
            </div>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin-top: 15px;">
                <div class="field-group">
                    <span class="field-label">Company Name:</span>
                    <span class="field-value">{{ optional($fetchedData->company)->company_name ?? 'N/A' }}</span>
                </div>
                @php
                    $comp = $fetchedData->company ?? null;
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

        {{-- Directors Card --}}
        @if($comp && $comp->directors->isNotEmpty())
        <div class="card" style="margin-bottom: 20px;">
            <h3><i class="fa-solid fa-users-cog"></i> Directors</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin-top: 15px;">
                @foreach($comp->directors as $dir)
                @php
                    $dirName = $dir->directorClient ? trim($dir->directorClient->first_name.' '.$dir->directorClient->last_name) : ($dir->director_name ?? '');
                    $dirEmailMeta = $dir->directorClient
                        ? app(\App\Services\CompanyDirectorEmailService::class)->resolveDirectorDisplayEmail($dir->directorClient, (int) $fetchedData->id)
                        : null;
                @endphp
                <div class="field-group">
                    <span class="field-label">{{ $dirName }}</span>
                    <span class="field-value">
                        {{ $dir->director_role ?? '' }}
                        @if($dir->director_dob) (DOB: {{ $dir->director_dob->format('d/m/Y') }})@endif
                        @if($dirEmailMeta)
                            — {{ $dirEmailMeta['email'] }}
                            @if($dirEmailMeta['is_shared'])
                                <span class="badge bg-secondary">Company email</span>
                            @endif
                        @elseif($dir->directorClient)
                            <span class="text-muted"> — No email</span>
                        @else
                            <span class="text-muted"> — Name only</span>
                        @endif
                    </span>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- Company Phone & Email Card --}}
        <div class="card" style="margin-bottom: 20px;">
            <h3><i class="fa-solid fa-phone"></i> Contact Information</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin-top: 15px;">
                {{-- Company Phone Number --}}
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
                                $check_verified_phoneno = $conVal->country_code."".$conVal->phone;
                                if( isset($conVal->country_code) && $conVal->country_code != "" ){
                                    $country_code = $conVal->country_code;
                                } else {
                                    $country_code = "";
                                }

                                // Format phone number to Australian standard
                                $formattedPhone = \App\Helpers\PhoneValidationHelper::formatAustralianPhone($conVal->phone, $country_code);

                                if( isset($conVal->contact_type) && $conVal->contact_type != "" ){
                                    if ( $conVal->is_verified ) {
                                        $phonenoStr .= $formattedPhone.' <i class="fa-solid fa-circle-check verified-icon fa-lg" style="color: #28a745;" title="Verified on ' . ($conVal->verified_at ? $conVal->verified_at->format('M j, Y g:i A') : 'Unknown') . '"></i> <br/>';
                                    } else {
                                        $phonenoStr .= $formattedPhone.' <i class="fa-regular fa-circle unverified-icon fa-lg" style="color: #6c757d;" title="Not verified"></i> <br/>';
                                    }
                                } else {
                                    if ( isset($conVal->is_verified) && $conVal->is_verified ) {
                                        $phonenoStr .= $formattedPhone.' <i class="fa-solid fa-circle-check verified-icon fa-lg" style="color: #28a745;" title="Verified on ' . ($conVal->verified_at ? $conVal->verified_at->format('M j, Y g:i A') : 'Unknown') . '"></i> <br/>';
                                    } else {
                                        $phonenoStr .= $formattedPhone.' <i class="fa-regular fa-circle unverified-icon fa-lg" style="color: #6c757d;" title="Not verified"></i> <br/>';
                                    }
                                }
                            }
                            echo $phonenoStr;
                        } else {
                            echo "N/A";
                        }?>
                    </span>
                </div>

                {{-- Company Email Address --}}
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
                                $check_verified_email = $emailVal->email_type."".$emailVal->email;
                                if( isset($emailVal->email_type) && $emailVal->email_type != "" ){
                                    if ( $emailVal->is_verified ) {
                                        $emailStr .= $emailVal->email.' <i class="fa-solid fa-circle-check verified-icon fa-lg" style="color: #28a745;" title="Verified on ' . ($emailVal->verified_at ? $emailVal->verified_at->format('M j, Y g:i A') : 'Unknown') . '"></i> <br/>';
                                    } else {
                                        $emailStr .= $emailVal->email.' <i class="fa-regular fa-circle unverified-icon fa-lg" style="color: #6c757d;" title="Not verified"></i> <br/>';
                                    }
                                } else {
                                    if ( isset($emailVal->is_verified) && $emailVal->is_verified ) {
                                        $emailStr .= $emailVal->email.' <i class="fa-solid fa-circle-check verified-icon fa-lg" style="color: #28a745;" title="Verified on ' . ($emailVal->verified_at ? $emailVal->verified_at->format('M j, Y g:i A') : 'Unknown') . '"></i> <br/>';
                                    } else {
                                        $emailStr .= $emailVal->email.' <i class="fa-regular fa-circle unverified-icon fa-lg" style="color: #6c757d;" title="Not verified"></i> <br/>';
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
        
        {{-- Primary Contact Person Card --}}
        @if($fetchedData->company->contactPerson)
            <div class="card" style="margin-bottom: 20px;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <h3><i class="fa-solid fa-user-tie"></i> Primary Contact Person</h3>
                    <a href="{{ route('clients.detail', base64_encode(convert_uuencode($fetchedData->company->contactPerson->id))) }}" 
                       class="btn btn-sm btn-outline-primary">
                        <i class="fa-solid fa-up-right-from-square"></i> View Profile
                    </a>
                </div>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin-top: 15px;">
                    <div class="field-group">
                        <span class="field-label">Name:</span>
                        <span class="field-value">
                            <a href="{{ route('clients.detail', base64_encode(convert_uuencode($fetchedData->company->contactPerson->id))) }}" 
                               style="color: #007bff; text-decoration: none;">
                                {{ $fetchedData->company->contactPerson->first_name }} {{ $fetchedData->company->contactPerson->last_name }}
                            </a>
                        </span>
                    </div>
                    @if($fetchedData->company->contact_person_position)
                    <div class="field-group">
                        <span class="field-label">Position:</span>
                        <span class="field-value">{{ $fetchedData->company->contact_person_position }}</span>
                    </div>
                    @endif
                    @if($fetchedData->company->contactPerson->email)
                    <div class="field-group">
                        <span class="field-label">Email:</span>
                        <span class="field-value">
                            <a href="mailto:{{ $fetchedData->company->contactPerson->email }}" style="color: #007bff; text-decoration: none;">
                                {{ $fetchedData->company->contactPerson->email }}
                            </a>
                        </span>
                    </div>
                    @endif
                    @if($fetchedData->company->contactPerson->phone)
                    <div class="field-group">
                        <span class="field-label">Phone:</span>
                        <span class="field-value">{{ $fetchedData->company->contactPerson->phone }}</span>
                    </div>
                    @endif
                    @if($fetchedData->company->contactPerson->client_id)
                    <div class="field-group">
                        <span class="field-label">Client ID:</span>
                        <span class="field-value">{{ $fetchedData->company->contactPerson->client_id }}</span>
                    </div>
                    @endif
                </div>
            </div>
        @endif

        {{-- Tags Section --}}
        <div class="card">
            <h3><i class="fa-solid fa-address-card"></i> Tag(s):   
                <span class="float-right text-muted" style="margin-left:180px;">
                <a href="javascript:;" data-id="{{$fetchedData->id}}" class="btn btn-primary opentagspopup btn-sm">Add Tag</a>
                <a href="javascript:;" data-id="{{$fetchedData->id}}" class="btn btn-outline-danger openredtagspopup btn-sm ms-1" title="Add Tag (hidden by default)">
                    <i class="fa-solid fa-triangle-exclamation"></i> Add Tag
                </a>
                </span>
            </h3>
           

            <div class="" style="overflow-wrap: break-word; word-wrap: break-word; max-width: 100%;">
                @php
                    [$normalTags, $redTags] = \App\Support\ClientTagStorage::decode($fetchedData->tagname ?? '');
                    $redTagCount = count($redTags);
                @endphp
                @foreach($normalTags as $tagName)
                    <span class="ui label tag-normal ag-flex ag-align-center ag-space-between" style="display: inline-flex; margin: 5px 5px 5px 0;">
                        <span class="col-hr-1" style="font-size: 12px;">{{ $tagName }}</span>
                    </span>
                @endforeach
                
                @if($redTagCount > 0)
                    <div class="red-tags-section" style="display: none; margin-top: 10px;">
                        <div style="margin-bottom: 5px; font-size: 11px; color: #dc3545; font-weight: bold;">
                            <i class="fa-solid fa-triangle-exclamation"></i> Red Tags:
                        </div>
                        @foreach($redTags as $tagName)
                            <span class="ui label tag-red ag-flex ag-align-center ag-space-between" style="display: inline-flex; margin: 5px 5px 5px 0; background-color: #dc3545; border: 1px solid #c82333;">
                                <span class="col-hr-1" style="font-size: 12px;">{{ $tagName }}</span>
                            </span>
                        @endforeach
                    </div>
                    
                    <div style="margin-top: 10px;">
                        <a href="javascript:;" id="toggleRedTags" class="btn btn-sm btn-outline-danger" data-client-id="{{$fetchedData->id}}" title="Show Red Tags">
                            <i class="fa-solid fa-eye"></i>
                        </a>
                    </div>
                @endif
            </div>
        </div>
        <style>
            .ui.label:first-child {
                margin-left: 0;
            }
            .ui.label {
                display: inline-block;
                line-height: 1;
                vertical-align: baseline;
                margin: 0 0.14285714em;
                background-color: var(--navy);
                background-image: none;
                padding: 0.5833em 0.833em;
                color: #fff;
                text-transform: none;
                font-weight: 700;
                border: 0 solid transparent;
                border-radius: 0.28571429rem;
                -webkit-transition: background .1s ease;
                transition: background .1s ease;
            }
            .ui.label.tag-red {
                background-color: #dc3545 !important;
                border: 1px solid #c82333 !important;
                color: #fff !important;
            }
            .ui.label.tag-normal {
                background-color: var(--navy);
            }
            .ag-align-center {
                align-items: center;
            }
            .ag-space-between {
                justify-content: space-between;
            }
            .col-hr-1 {
                margin-right: 5px !important;
            }
            .red-tags-section {
                padding: 10px;
                background-color: #fff5f5;
                border-left: 3px solid #dc3545;
                border-radius: 4px;
                margin-top: 10px;
            }
            #toggleRedTags {
                transition: all 0.3s ease;
            }
            #toggleRedTags:hover {
                transform: translateY(-1px);
                box-shadow: 0 2px 4px rgba(220, 53, 69, 0.3);
            }
        </style>
    </div>
</div>

<!-- Red Tags Toggle JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Red Tags Toggle Functionality
    const toggleRedTagsBtn = document.getElementById('toggleRedTags');
    const redTagsSection = document.querySelector('.red-tags-section');
    
    if (toggleRedTagsBtn && redTagsSection) {
        // Store toggle state in sessionStorage
        const storageKey = 'redTagsVisible_' + toggleRedTagsBtn.getAttribute('data-client-id');
        const isVisible = sessionStorage.getItem(storageKey) === 'true';
        
        // Set initial state
        if (isVisible) {
            redTagsSection.style.display = 'block';
            toggleRedTagsBtn.innerHTML = '<i class="fa-solid fa-eye-slash"></i>';
            toggleRedTagsBtn.classList.remove('btn-outline-danger');
            toggleRedTagsBtn.classList.add('btn-danger');
            toggleRedTagsBtn.title = 'Hide Red Tags';
        }
        
        toggleRedTagsBtn.addEventListener('click', function() {
            const isCurrentlyVisible = redTagsSection.style.display !== 'none';
            
            if (isCurrentlyVisible) {
                // Hide red tags
                redTagsSection.style.display = 'none';
                this.innerHTML = '<i class="fa-solid fa-eye"></i>';
                this.classList.remove('btn-danger');
                this.classList.add('btn-outline-danger');
                this.title = 'Show Red Tags';
                sessionStorage.setItem(storageKey, 'false');
            } else {
                // Show red tags
                redTagsSection.style.display = 'block';
                this.innerHTML = '<i class="fa-solid fa-eye-slash"></i>';
                this.classList.remove('btn-outline-danger');
                this.classList.add('btn-danger');
                this.title = 'Hide Red Tags';
                sessionStorage.setItem(storageKey, 'true');
            }
        });
    }
});
</script>
