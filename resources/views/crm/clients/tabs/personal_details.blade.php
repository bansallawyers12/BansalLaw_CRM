<div class="tab-pane{{ strtolower((string) ($activeTab ?? 'personaldetails')) === 'personaldetails' ? ' active' : '' }}" id="personaldetails-tab">
                @php
                    $__sch = \Illuminate\Support\Facades\Schema::class;
                    $detailHasMatterTeam = $__sch::hasTable('client_matters')
                        && $__sch::hasColumn('client_matters', 'sel_legal_practitioner');
                    $detailHasClientAddressCols = $__sch::hasTable('client_addresses')
                        && $__sch::hasColumn('client_addresses', 'client_id')
                        && $__sch::hasColumn('client_addresses', 'address');
                    $detailHasDobVerifiedCol = $__sch::hasTable('admins')
                        && $__sch::hasColumn('admins', 'dob_verified_date');
                @endphp
                <div class="content-grid">
                    @if(!empty($fetchedData->is_company))
                        @include('crm.companies.partials.company_overview_unified')
                    @else
                    <div class="card">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <h3><i class="fa-solid fa-user"></i> Personal Information</h3>
                        </div>
                        <div class="field-group">
                            <span class="field-label">Age / Date of Birth</span>
                            <span class="field-value">
                                <?php
                                if ( isset($fetchedData->age) && $fetchedData->age != '') {
                                    $verifiedDobTick = '<i class="fa-regular fa-circle unverified-icon fa-lg"></i>';
                                    if ($detailHasDobVerifiedCol) {
                                        $verifiedDob = \App\Models\Admin::where('id', $fetchedData->id)->whereNotNull('dob_verified_date')->first();
                                        if ($verifiedDob) {
                                            $verifiedDobTick = '<i class="fa-solid fa-circle-check verified-icon fa-lg"></i>';
                                        }
                                    }
                                    
                                    // Format DOB for display
                                    $formattedDob = 'N/A';
                                    if (isset($fetchedData->dob) && $fetchedData->dob != '') {
                                        try {
                                            $dobDate = \Carbon\Carbon::parse($fetchedData->dob);
                                            $formattedDob = $dobDate->format('d M Y'); // e.g., "15 Jan 2001"
                                        } catch (\Exception $e) {
                                            $formattedDob = 'N/A';
                                        }
                                    }
                                    ?>
                                    <span id="ageDobToggle" style="cursor: pointer;" 
                                          data-age="<?php echo htmlspecialchars($fetchedData->age); ?>" 
                                          data-dob="<?php echo htmlspecialchars($formattedDob); ?>">
                                        <span class="display-age"><?php echo $fetchedData->age; ?></span>
                                        <span class="display-dob" style="display: none;"><?php echo $formattedDob; ?></span>
                                        <?php echo $verifiedDobTick; ?>
                                    </span>
                                <?php
                                } else {
                                    echo 'N/A';
                                } ?>
                            </span>
                        </div>

                        <div class="field-group">
                            <span class="field-label">Gender</span>
                            <span class="field-value">
                                <?php
                                if ( isset($fetchedData->gender) && $fetchedData->gender != '') {
                                    echo $fetchedData->gender;
                                } else {
                                    echo 'N/A';
                                } ?>
                            </span>
                        </div>

                        <div class="field-group">
                            <span class="field-label">Marital Status</span>
                            <span class="field-value">
                                <?php
                                if ( isset($fetchedData->marital_status) && $fetchedData->marital_status != '') {
                                    echo $fetchedData->marital_status;
                                } else {
                                    echo 'N/A';
                                } ?>
                            </span>
                        </div>

                        <div class="field-group">
                            <span class="field-label">Client Email</span>
                            <span class="field-value">
                                <?php
                                if( \App\Models\ClientEmail::where('client_id', $fetchedData->id)->exists()) {
                                    $clientEmails = \App\Models\ClientEmail::select('email','email_type','is_verified','verified_at')->where('client_id', $fetchedData->id)->get();
                                } else {
                                    if( \App\Models\Admin::where('id', $fetchedData->id)->exists()){
                                        $clientEmails = \App\Models\Admin::select('email','email_type')->where('id', $fetchedData->id)->get();
                                    } else {
                                        $clientEmails = [];
                                    }
                                } //dd($clientEmails);
                                if( !empty($clientEmails) && count($clientEmails)>0 ){
                                    $emailStr = "";
                                    foreach($clientEmails as $emailKey=>$emailVal){

                                        //Check email is verified or not
                                        $check_verified_email = $emailVal->email_type."".$emailVal->email;
                                        if( isset($emailVal->email_type) && $emailVal->email_type != "" ){
                                            // Show verification status for ALL email types
                                            if ( $emailVal->is_verified ) {
                                                $emailStr .= $emailVal->email.' <i class="fa-solid fa-circle-check verified-icon fa-lg" style="color: #28a745;" title="Verified on ' . ($emailVal->verified_at ? $emailVal->verified_at->format('M j, Y g:i A') : 'Unknown') . '"></i> <br/>';
                                            } else {
                                                $emailStr .= $emailVal->email.' <i class="fa-regular fa-circle unverified-icon fa-lg" style="color: #6c757d;" title="Not verified"></i> <br/>';
                                            }
                                        } else {
                                            // For emails without type, still show verification status if available
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

                        <div class="field-group">
                            <span class="field-label">Client Phone</span>
                            <span class="field-value">
                                <?php
                                if( \App\Models\ClientContact::where('client_id', $fetchedData->id)->exists()) {
                                    $clientContacts = \App\Models\ClientContact::select('phone','country_code','contact_type','is_verified','verified_at')->where('client_id', $fetchedData->id)->where('contact_type', '!=', 'Not In Use')->get();
                                } else {
                                    if( \App\Models\Admin::where('id', $fetchedData->id)->exists()){
                                        $clientContacts = \App\Models\Admin::select('phone','country_code','contact_type')->where('id', $fetchedData->id)->get();
                                    } else {
                                        $clientContacts = [];
                                    }
                                } //dd($clientContacts);
                                if( !empty($clientContacts) && count($clientContacts)>0 ){
                                    $phonenoStr = "";
                                    foreach($clientContacts as $conKey=>$conVal){
                                        //Check phone is verified or not
                                        $check_verified_phoneno = $conVal->country_code."".$conVal->phone;
                                        if( isset($conVal->country_code) && $conVal->country_code != "" ){
                                            $country_code = $conVal->country_code;
                                        } else {
                                            $country_code = "";
                                        }

                                        // Format phone number to Australian standard
                                        $formattedPhone = \App\Helpers\PhoneValidationHelper::formatAustralianPhone($conVal->phone, $country_code);

                                        if( isset($conVal->contact_type) && $conVal->contact_type != "" ){
                                            // Show verification status for ALL contact types
                                            if ( $conVal->is_verified ) {
                                                $phonenoStr .= $formattedPhone.' <i class="fa-solid fa-circle-check verified-icon fa-lg" style="color: #28a745;" title="Verified on ' . ($conVal->verified_at ? $conVal->verified_at->format('M j, Y g:i A') : 'Unknown') . '"></i> <br/>';
                                            } else {
                                                $phonenoStr .= $formattedPhone.' <i class="fa-regular fa-circle unverified-icon fa-lg" style="color: #6c757d;" title="Not verified"></i> <br/>';
                                            }
                                        } else {
                                            // For phones without type, still show verification status if available
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

                        <?php
                        $address_Info = null;
                        if ($detailHasClientAddressCols) {
                            $addressSelectCols = ['address', 'suburb', 'country', 'zip', 'regional_code'];
                            foreach (['address_line_1', 'address_line_2', 'state'] as $addressCol) {
                                if ($__sch::hasColumn('client_addresses', $addressCol)) {
                                    $addressSelectCols[] = $addressCol;
                                }
                            }
                            $address_Info = App\Models\ClientAddress::select($addressSelectCols)->where('client_id', $fetchedData->id)->latest('id')->first();
                        }
                        ?>

                        <div class="field-group">
                            <span class="field-label">Address</span>
                            <span class="field-value">
                                <?php
                                if($address_Info) {
                                    $addressParts = array_filter([
                                        $address_Info->address_line_1 ?? '',
                                        $address_Info->address_line_2 ?? '',
                                        $address_Info->suburb ?? '',
                                        $address_Info->state ?? '',
                                        $address_Info->zip ?? '',
                                        (!empty($address_Info->country) && $address_Info->country !== 'Australia') ? $address_Info->country : '',
                                    ]);

                                    if (!empty($addressParts)) {
                                        echo implode(', ', $addressParts);
                                    } elseif (!empty($address_Info->address)) {
                                        echo $address_Info->address;
                                    } else {
                                        echo 'N/A';
                                    }
                                } else {
                                    echo 'N/A';
                                }
                                ?>
                            </span>
                        </div>

                        <?php if($address_Info && $address_Info->regional_code): ?>
                        <div class="field-group">
                            <span class="field-label">Regional Classification</span>
                            <span class="field-value">
                                <?php echo $address_Info->regional_code; ?>
                            </span>
                        </div>
                        <?php endif; ?>
                        @if(!empty($cdnHeroLastUpdateOn))
                        <div style="margin-top: 10px; padding-top: 8px; border-top: 1px solid #edf2f7; text-align: right;">
                            <span class="text-muted" style="font-size:12px;">Last update on {{ $cdnHeroLastUpdateOn }}</span>
                        </div>
                        @endif
                    </div>


                    @endif

                    <?php
                    $matter_cnt = \App\Models\ClientMatter::select('id')->where('client_id',$fetchedData->id)->where('matter_status',1)->count();
                    //dd($matter_cnt);
                    if($matter_cnt >0)
                    {
                    ?>
                        <?php
                            $overviewClientMatterId = null;
                            $matter_dis_ref_info_arr = null;
                            $matterAssigneeCols = ['id'];
                            if ($detailHasMatterTeam) {
                                $matterAssigneeCols = array_merge($matterAssigneeCols, ['sel_legal_practitioner', 'sel_person_responsible', 'sel_person_assisting', 'office_id']);
                            }
                            if ($__sch::hasColumn('client_matters', 'incidence_type')) {
                                $matterAssigneeCols[] = 'incidence_type';
                            }
                            if ($__sch::hasColumn('client_matters', 'date_of_incidence')) {
                                $matterAssigneeCols[] = 'date_of_incidence';
                            }
                            if ($__sch::hasColumn('client_matters', 'case_detail')) {
                                $matterAssigneeCols[] = 'case_detail';
                            }
                            if ($__sch::hasColumn('client_matters', 'our_party_role')) {
                                $matterAssigneeCols[] = 'our_party_role';
                            }
                            if ($__sch::hasColumn('client_matters', 'sel_matter_id')) {
                                $matterAssigneeCols[] = 'sel_matter_id';
                            }
                            if ($matterAssigneeCols !== []) {
                                if ($id1) {
                                    $matter_dis_ref_info_arr = \App\Models\ClientMatter::select($matterAssigneeCols)
                                        ->where('client_id', $fetchedData->id)
                                        ->where('client_unique_matter_no', $id1)
                                        ->first();
                                } else {
                                    $matter_cnt_inner2 = \App\Models\ClientMatter::select('id')->where('client_id', $fetchedData->id)->where('matter_status', 1)->count();
                                    if ($matter_cnt_inner2 > 0) {
                                        $matter_dis_ref_info_arr = \App\Models\ClientMatter::select($matterAssigneeCols)
                                            ->where('client_id', $fetchedData->id)
                                            ->where('matter_status', 1)
                                            ->orderBy('id', 'desc')
                                            ->first();
                                    }
                                }
                            }
                            if ($matter_dis_ref_info_arr && ! empty($matter_dis_ref_info_arr->id)) {
                                $overviewClientMatterId = (int) $matter_dis_ref_info_arr->id;
                            }
                            ?>
                        <div class="card">
                            <h3><i class="fa-solid fa-user"></i> Matter assignee
                                @if($overviewClientMatterId)
                                <a style="margin-left: 24px;" class="changeMatterAssignee" href="javascript:;" role="button" data-client-matter-id="{{ $overviewClientMatterId }}">Edit details</a>
                                @else
                                <a style="margin-left: 24px;" class="changeMatterAssignee" href="javascript:;" role="button">Edit details</a>
                                @endif
                            </h3>

                            <div class="field-group">
                                <span class="field-label">Principal Solicitor</span>
                                <span class="field-value">
                                    <?php
                                    $lpName = null;
                                    if( isset($matter_dis_ref_info_arr) && !empty($matter_dis_ref_info_arr) && $matter_dis_ref_info_arr->sel_legal_practitioner != '') {
                                        $legal_practitioner_info = \App\Models\Staff::select('first_name','last_name')->where('id', $matter_dis_ref_info_arr->sel_legal_practitioner)->first();
                                        if($legal_practitioner_info){
                                            $lpName = $legal_practitioner_info->first_name.' '.$legal_practitioner_info->last_name;
                                        }
                                    }
                                    echo $lpName ?? 'Ajay Bansal';
                                    ?>
                                </span>
                            </div>
                            <div class="field-group">
                                <span class="field-label">Responsible Solicitor</span>
                                <span class="field-value">
                                    <?php
                                    $prName = null;
                                    if( isset($matter_dis_ref_info_arr) && !empty($matter_dis_ref_info_arr) && $matter_dis_ref_info_arr->sel_person_responsible != ''){
                                        $sel_person_responsible_info_arr = \App\Models\Staff::select('first_name','last_name')->where('id', $matter_dis_ref_info_arr->sel_person_responsible)->first();
                                        if($sel_person_responsible_info_arr){
                                            $prName = $sel_person_responsible_info_arr->first_name.' '.$sel_person_responsible_info_arr->last_name;
                                        }
                                    }
                                    echo $prName ?? 'Michael Saleh';
                                    ?>
                                </span>
                            </div>

                            <div class="field-group">
                                <span class="field-label">Paralegal</span>
                                <span class="field-value">
                                    <?php
                                    $paName = null;
                                    if( isset($matter_dis_ref_info_arr) && !empty($matter_dis_ref_info_arr) && $matter_dis_ref_info_arr->sel_person_assisting != ''){
                                        $sel_person_assisting_info_arr = \App\Models\Staff::select('first_name','last_name')->where('id', $matter_dis_ref_info_arr->sel_person_assisting)->first();
                                        if($sel_person_assisting_info_arr){
                                            $paName = $sel_person_assisting_info_arr->first_name.' '.$sel_person_assisting_info_arr->last_name;
                                        }
                                    }
                                    echo $paName ?? 'Khushi Sangroya';
                                    ?>
                                </span>
                            </div>

                            <div class="field-group">
                                <span class="field-label">Handling Office</span>
                                <span class="field-value">
                                    <?php
                                    if( isset($matter_dis_ref_info_arr) && !empty($matter_dis_ref_info_arr) && $matter_dis_ref_info_arr->office_id != ''){
                                        $office_info = \App\Models\Branch::select('office_name')->where('id', $matter_dis_ref_info_arr->office_id)->first();
                                        if($office_info){
                                            echo $office_info->office_name;
                                        }
                                    } else {
                                        echo 'Melbourne';
                                    } ?>
                                </span>
                            </div>
                        </div>

                        <div class="card">
                            <h3><i class="fa-solid fa-briefcase"></i> Matter Details</h3>
                            @php
                                $mdRows = [];
                                if ($matter_dis_ref_info_arr && $__sch::hasColumn('client_matters', 'our_party_role')) {
                                    $ourRoleVal = trim((string) ($matter_dis_ref_info_arr->our_party_role ?? ''));
                                    if ($ourRoleVal !== '') {
                                        $matterStream = 'general';
                                        if (! empty($matter_dis_ref_info_arr->sel_matter_id)) {
                                            $matterStream = (string) (\App\Models\Matter::query()->whereKey($matter_dis_ref_info_arr->sel_matter_id)->value('stream') ?? 'general');
                                        }
                                        $roleLabels = \App\Support\MatterStreamHelper::partyRolesForStream($matterStream);
                                        $mdRows[] = ['label' => 'Our client\'s role', 'value' => $roleLabels[$ourRoleVal] ?? $ourRoleVal];
                                    }
                                }
                                $linkedOtherParties = collect();
                                if ($matter_dis_ref_info_arr && $__sch::hasTable('client_matter_opposing_parties')) {
                                    $linkedOtherParties = \App\Models\ClientMatterOpposingParty::query()
                                        ->where('client_matter_id', (int) $matter_dis_ref_info_arr->id)
                                        ->orderBy('sort_order')
                                        ->orderBy('id')
                                        ->get();
                                }
                                if ($matter_dis_ref_info_arr && $__sch::hasColumn('client_matters', 'incidence_type')) {
                                    $subtype = trim((string) ($matter_dis_ref_info_arr->incidence_type ?? ''));
                                    if ($subtype !== '') {
                                        $mdRows[] = ['label' => 'Matter subtype', 'value' => $subtype];
                                    }
                                }
                                if ($matter_dis_ref_info_arr && $__sch::hasColumn('client_matters', 'date_of_incidence') && ! empty($matter_dis_ref_info_arr->date_of_incidence)) {
                                    try {
                                        $doiLabel = \Carbon\Carbon::parse($matter_dis_ref_info_arr->date_of_incidence)->format('d/m/Y');
                                    } catch (\Throwable $e) {
                                        $doiLabel = (string) $matter_dis_ref_info_arr->date_of_incidence;
                                    }
                                    $mdRows[] = ['label' => 'Date of incidence', 'value' => $doiLabel];
                                }
                                if ($__sch::hasColumn('client_matters', 'case_detail')) {
                                    $rawCaseDetail = ($matter_dis_ref_info_arr && isset($matter_dis_ref_info_arr->case_detail)) ? trim((string) $matter_dis_ref_info_arr->case_detail) : '';
                                    if ($rawCaseDetail !== '') {
                                        foreach (preg_split('/\r?\n/', $rawCaseDetail) as $cdLine) {
                                            $cdLine = trim($cdLine);
                                            if ($cdLine === '') continue;
                                            if (strpos($cdLine, ':') !== false) {
                                                [$cdLabel, $cdVal] = explode(':', $cdLine, 2);
                                                $mdRows[] = ['label' => trim($cdLabel), 'value' => trim($cdVal)];
                                            } else {
                                                $mdRows[] = ['label' => 'Case detail', 'value' => $cdLine];
                                            }
                                        }
                                    }
                                }
                            @endphp
                            @forelse($mdRows as $mdRow)
                            <div class="field-group">
                                @if($mdRow['label'] !== '')
                                <span class="field-label">{{ $mdRow['label'] }}</span>
                                <span class="field-value">{{ $mdRow['value'] }}</span>
                                @else
                                <span class="field-value">{{ $mdRow['value'] }}</span>
                                @endif
                            </div>
                            @empty
                            <p class="text-muted mb-0" style="font-size:0.9rem;">No matter details recorded yet. Use <strong>Edit details</strong> on Matter assignee to add subtype, dates, or case notes.</p>
                            @endforelse
                            @if($linkedOtherParties->isNotEmpty())
                                <div class="field-group" style="margin-top:0.75rem;">
                                    <span class="field-label">Other parties</span>
                                    <div class="field-value">
                                        <ul class="mb-0 ps-3" style="font-size:0.9rem;">
                                            @foreach($linkedOtherParties as $opp)
                                                @php
                                                    $oppRoleLabel = $opp->party_role;
                                                    if ($matter_dis_ref_info_arr && ! empty($matter_dis_ref_info_arr->sel_matter_id)) {
                                                        $oppStream = (string) (\App\Models\Matter::query()->whereKey($matter_dis_ref_info_arr->sel_matter_id)->value('stream') ?? 'general');
                                                        $oppRoleLabels = \App\Support\MatterStreamHelper::partyRolesForStream($oppStream);
                                                        $oppRoleLabel = $oppRoleLabels[$opp->party_role] ?? $opp->party_role;
                                                    }
                                                    $repParts = array_filter([
                                                        $opp->rep_firm ?? null,
                                                        $opp->rep_name ?? null,
                                                        $opp->rep_email ?? null,
                                                        $opp->rep_phone ?? null,
                                                    ]);
                                                @endphp
                                                <li class="mb-1">
                                                    <strong>{{ $opp->name }}</strong>
                                                    @if($oppRoleLabel)<span class="text-muted"> — {{ $oppRoleLabel }}</span>@endif
                                                    @if($repParts !== [])
                                                        <br><small class="text-muted">Rep: {{ implode(' · ', $repParts) }}</small>
                                                    @endif
                                                    @if(! empty($opp->rep_notes))
                                                        <br><small class="text-muted">{{ $opp->rep_notes }}</small>
                                                    @endif
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                </div>
                            @endif
                        </div>
                    <?php
                    } ?>

                    @include('crm.clients.partials.client-matters-list-card', [
                        'fetchedData' => $fetchedData,
                        'encodeId' => $encodeId ?? null,
                        'selectedClientMatter' => $selectedClientMatter ?? null,
                        'matterRefInUrl' => $id1 ?? null,
                        'activeTab' => $activeTab ?? 'personaldetails',
                        'matterFormForLead' => $matterFormForLead ?? null,
                        'isClosedMatterView' => $isClosedMatterView ?? false,
                    ])

                    <style>
                        .eoi-table{
                            width: 100%;
                            min-width: 600px;
                            border-collapse: collapse;
                            margin-top: 10px;
                            table-layout: fixed;
                        }
                        .eoi-table th, .eoi-table td {
                            padding: 10px;
                            border-bottom: 1px solid #dee2e6;
                            text-align: left;
                            word-break: normal;
                            white-space: normal;
                        }
                        .eoi-table th {
                            background-color: #f8f9fa;
                            font-weight: 600;
                            color: #6c757d !important;
                            white-space: normal;
                            word-wrap: break-word;
                            overflow-wrap: break-word;
                            overflow: visible;
                            text-overflow: clip;
                        }
                        
                        .eoi-table tbody tr:hover {
                            background-color: #f1f5f9;
                        }
                        .eoi-table td {
                            color: #212529;
                        }
                        
                        /* Tag spacing and layout */
                        .ui.label {
                            margin: 5px 5px 5px 0 !important;
                            display: inline-flex !important;
                            vertical-align: top;
                            max-width: 100%;
                            word-wrap: break-word;
                            overflow-wrap: break-word;
                        }
                        
                        .ui.label .col-hr-1 {
                            white-space: normal;
                            word-wrap: break-word;
                            overflow-wrap: break-word;
                            padding: 2px 8px;
                            border-radius: 4px;
                            font-size: 12px;
                            max-width: 100%;
                            box-sizing: border-box;
                        }
                    </style>

                    @if(($fetchedData->type ?? null) === 1 || in_array(trim((string) ($fetchedData->type ?? '')), ['lead', 'l', '1'], true))
                        @include('crm.clients.partials.lead_pipeline_card', [
                            'fetchedData' => $fetchedData,
                            'assignableStaff' => $assignableStaff ?? collect(),
                            'leadStageLabels' => $leadStageLabels ?? [],
                            'activeClientMatterId' => $activeClientMatterId ?? null,
                        ])
                    @endif

                    @include('crm.clients.partials.conflict-parties-card', [
                        'fetchedData'           => $fetchedData,
                        'conflictParties'       => $conflictParties ?? collect(),
                        'latestConflictCheck'   => $latestConflictCheck ?? null,
                        'conflictCheckHistory'  => $conflictCheckHistory ?? collect(),
                        'activeClientMatterId'  => $activeClientMatterId ?? null,
                    ])

                    @unless($suppressPersonalDetailsTagCard ?? false)
                    <div class="card">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <h3><i class="fa-solid fa-address-card"></i> Tag(s):</h3>
                            <div class="d-flex gap-1">
                                <a href="javascript:;" data-id="{{$fetchedData->id}}" class="btn btn-primary opentagspopup btn-sm d-inline-flex align-items-center justify-content-center" style="width:28px;height:28px;min-width:28px;padding:0;" title="Add Tag"><i class="fa-solid fa-plus"></i></a>
                                <a href="javascript:;" data-id="{{$fetchedData->id}}" class="btn btn-danger openredtagspopup btn-sm d-inline-flex align-items-center justify-content-center" style="width:28px;height:28px;min-width:28px;padding:0;" title="Add Tag (hidden by default)"><i class="fa-solid fa-plus"></i></a>
                            </div>
                        </div>
                       

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
                    @endunless

                </div>

                {{-- Court Hearings – full-width below the card grid --}}
                @php
                    $clientHearings = \App\Models\ClientCourtHearing::where('client_id', $fetchedData->id)
                        ->orderByDesc('hearing_date')
                        ->get();
                @endphp
                @if($clientHearings->count() > 0)
                <div class="card" style="margin-top:20px;">
                    <h3><i class="fa-solid fa-gavel"></i> Court Hearings</h3>
                    <div style="overflow-x:auto;">
                        <table style="width:100%;border-collapse:collapse;margin-top:10px;">
                            <thead>
                                <tr style="background:#f8f9fa;border-bottom:2px solid #dee2e6;">
                                    <th style="padding:10px 14px;text-align:left;font-weight:600;color:#495057;">Date</th>
                                    <th style="padding:10px 14px;text-align:left;font-weight:600;color:#495057;">Time</th>
                                    <th style="padding:10px 14px;text-align:left;font-weight:600;color:#495057;">Hearing Type</th>
                                    <th style="padding:10px 14px;text-align:left;font-weight:600;color:#495057;">Court Name</th>
                                    <th style="padding:10px 14px;text-align:left;font-weight:600;color:#495057;">Case Number</th>
                                    <th style="padding:10px 14px;text-align:left;font-weight:600;color:#495057;">Judge</th>
                                    <th style="padding:10px 14px;text-align:left;font-weight:600;color:#495057;">Status</th>
                                    <th style="padding:10px 14px;text-align:left;font-weight:600;color:#495057;">Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($clientHearings as $ch)
                                @php
                                    $hStatusColors = ['Scheduled'=>'#1a73e8','Completed'=>'#188038','Adjourned'=>'#e37400','Cancelled'=>'#c5221f'];
                                    $hsc = $hStatusColors[$ch->status] ?? '#555';
                                @endphp
                                <tr style="border-bottom:1px solid #eee;">
                                    <td style="padding:10px 14px;white-space:nowrap;"><strong>{{ $ch->hearing_date->format('d/m/Y') }}</strong></td>
                                    <td style="padding:10px 14px;white-space:nowrap;">{{ $ch->hearing_time ? \Carbon\Carbon::parse($ch->hearing_time)->format('g:i A') : '—' }}</td>
                                    <td style="padding:10px 14px;">{{ $ch->hearing_type ?: '—' }}</td>
                                    <td style="padding:10px 14px;">{{ $ch->court_name ?: '—' }}</td>
                                    <td style="padding:10px 14px;">{{ $ch->case_number ?: '—' }}</td>
                                    <td style="padding:10px 14px;">{{ $ch->judge_name ?: '—' }}</td>
                                    <td style="padding:10px 14px;white-space:nowrap;"><span style="color:{{ $hsc }};font-weight:600;">{{ $ch->status }}</span></td>
                                    <td style="padding:10px 14px;">{{ $ch->notes ?: '—' }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                @endif

            </div>

            <!-- Age/DOB Toggle JavaScript -->
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                const ageDobToggle = document.getElementById('ageDobToggle');
                if (ageDobToggle) {
                    ageDobToggle.addEventListener('click', function() {
                        const ageSpan = this.querySelector('.display-age');
                        const dobSpan = this.querySelector('.display-dob');
                        
                        if (ageSpan && dobSpan) {
                            if (ageSpan.style.display === 'none') {
                                // Currently showing DOB, switch to Age
                                ageSpan.style.display = 'inline';
                                dobSpan.style.display = 'none';
                            } else {
                                // Currently showing Age, switch to DOB
                                ageSpan.style.display = 'none';
                                dobSpan.style.display = 'inline';
                            }
                        }
                    });
                }
                
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
