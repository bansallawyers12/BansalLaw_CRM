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
                <div class="content-grid cdn-overview">
                    @if(!empty($fetchedData->is_company))
                        @include('crm.companies.partials.company_overview_unified')
                    @else
                    <article class="card cdn-ov-card">
                        <header class="cdn-ov-card__head">
                            <div class="cdn-ov-card__title">
                                <span class="cdn-ov-card__icon" aria-hidden="true"><i class="fa-solid fa-user"></i></span>
                                <h3>Personal Information</h3>
                            </div>
                        </header>
                        <div class="cdn-ov-card__body cdn-ov-card__body--scroll">
                            {{-- Demographics Tile Row (Age/DOB, Gender, Marital Status) --}}
                            @php
                                $verifiedDobTick = '<span class="cdn-ov-verify-badge is-unverified" title="Date of birth not verified"><i class="fa-regular fa-circle" aria-hidden="true"></i><span class="cdn-ov-verify-text">Unverified</span></span>';
                                if ($detailHasDobVerifiedCol) {
                                    $verifiedDob = \App\Models\Admin::where('id', $fetchedData->id)->whereNotNull('dob_verified_date')->first();
                                    if ($verifiedDob) {
                                        $verifiedDobTick = '<span class="cdn-ov-verify-badge is-verified" title="Date of birth verified"><i class="fa-solid fa-check" aria-hidden="true"></i><span class="cdn-ov-verify-text">Verified</span></span>';
                                    }
                                }

                                $formattedDob = 'N/A';
                                if (isset($fetchedData->dob) && $fetchedData->dob != '') {
                                    try {
                                        $dobDate = \Carbon\Carbon::parse($fetchedData->dob);
                                        $formattedDob = $dobDate->format('d M Y');
                                    } catch (\Exception $e) {
                                        $formattedDob = 'N/A';
                                    }
                                }
                                $ageRaw = trim((string) ($fetchedData->age ?? ''));
                                $preferDob = $formattedDob !== 'N/A' && preg_match('/^0\s+years?\s+0\s+months?$/i', $ageRaw);
                            @endphp

                            <div class="cdn-ov-demographics-row">
                                <div class="cdn-ov-demo-tile">
                                    <span class="cdn-ov-demo-tile__label"><i class="fa-regular fa-calendar"></i> Age / DOB</span>
                                    <span class="cdn-ov-demo-tile__value">
                                        @if($ageRaw !== '')
                                            <span id="ageDobToggle"
                                                  class="cdn-ov-age-toggle"
                                                  title="Click to switch between age and date of birth"
                                                  data-age="{{ htmlspecialchars($ageRaw) }}"
                                                  data-dob="{{ htmlspecialchars($formattedDob) }}">
                                                <span class="display-age"{{ $preferDob ? ' style=display:none;' : '' }}>{{ $ageRaw }}</span>
                                                <span class="display-dob"{{ $preferDob ? '' : ' style=display:none;' }}>{{ $formattedDob }}</span>
                                                <i class="fa-solid fa-right-left cdn-ov-toggle-icon" aria-hidden="true"></i>
                                                {!! $verifiedDobTick !!}
                                            </span>
                                        @else
                                            <span class="cdn-ov-na">N/A</span>
                                        @endif
                                    </span>
                                </div>
                                <div class="cdn-ov-demo-tile">
                                    <span class="cdn-ov-demo-tile__label"><i class="fa-solid fa-venus-mars"></i> Gender</span>
                                    <span class="cdn-ov-demo-tile__value">
                                        {{ !empty($fetchedData->gender) ? $fetchedData->gender : 'N/A' }}
                                    </span>
                                </div>
                                <div class="cdn-ov-demo-tile">
                                    <span class="cdn-ov-demo-tile__label"><i class="fa-solid fa-heart"></i> Marital</span>
                                    <span class="cdn-ov-demo-tile__value">
                                        {{ !empty($fetchedData->marital_status) ? $fetchedData->marital_status : 'N/A' }}
                                    </span>
                                </div>
                            </div>

                            {{-- Email Block --}}
                            @php
                                if (\App\Models\ClientEmail::where('client_id', $fetchedData->id)->exists()) {
                                    $clientEmails = \App\Models\ClientEmail::select('email','email_type','is_verified','verified_at')->where('client_id', $fetchedData->id)->get();
                                } else {
                                    if (\App\Models\Admin::where('id', $fetchedData->id)->exists()){
                                        $clientEmails = \App\Models\Admin::select('email','email_type')->where('id', $fetchedData->id)->get();
                                    } else {
                                        $clientEmails = collect();
                                    }
                                }
                            @endphp
                            <div class="cdn-ov-contact-block">
                                <div class="cdn-ov-contact-block__head">
                                    <span class="cdn-ov-field__label"><i class="fa-regular fa-envelope"></i> Client Email</span>
                                </div>
                                @if(!empty($clientEmails) && count($clientEmails) > 0)
                                    @foreach($clientEmails as $emailVal)
                                        @php
                                            $eAddr = trim((string) $emailVal->email);
                                            $isV = !empty($emailVal->is_verified);
                                            $vTitle = $isV ? ('Verified on ' . ($emailVal->verified_at ? $emailVal->verified_at->format('d/m/Y g:i A') : 'Unknown')) : 'Not verified';
                                        @endphp
                                        <div class="cdn-ov-contact-row">
                                            <a href="mailto:{{ $eAddr }}" class="cdn-ov-contact-link" title="Click to email">
                                                <i class="fa-regular fa-envelope" aria-hidden="true"></i>
                                                <span>{{ $eAddr }}</span>
                                            </a>
                                            <div class="cdn-ov-contact-row__actions">
                                                <button type="button" class="cdn-ov-copy-btn" title="Copy email" onclick="navigator.clipboard.writeText('{{ $eAddr }}'); this.classList.add('is-copied'); setTimeout(() => this.classList.remove('is-copied'), 1500);">
                                                    <i class="fa-regular fa-copy"></i>
                                                </button>
                                                @if($isV)
                                                    <span class="cdn-ov-verify-badge is-verified" title="{{ $vTitle }}"><i class="fa-solid fa-check" aria-hidden="true"></i><span class="cdn-ov-verify-text">Verified</span></span>
                                                @else
                                                    <span class="cdn-ov-verify-badge is-unverified" title="{{ $vTitle }}"><i class="fa-regular fa-circle" aria-hidden="true"></i><span class="cdn-ov-verify-text">Unverified</span></span>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                @else
                                    <div class="cdn-ov-contact-row">
                                        <span class="cdn-ov-na">N/A</span>
                                    </div>
                                @endif
                            </div>

                            {{-- Phone Block --}}
                            @php
                                if (\App\Models\ClientContact::where('client_id', $fetchedData->id)->exists()) {
                                    $clientContacts = \App\Models\ClientContact::select('phone','country_code','contact_type','is_verified','verified_at')->where('client_id', $fetchedData->id)->where('contact_type', '!=', 'Not In Use')->get();
                                } else {
                                    if (\App\Models\Admin::where('id', $fetchedData->id)->exists()){
                                        $clientContacts = \App\Models\Admin::select('phone','country_code','contact_type')->where('id', $fetchedData->id)->get();
                                    } else {
                                        $clientContacts = collect();
                                    }
                                }
                            @endphp
                            <div class="cdn-ov-contact-block">
                                <div class="cdn-ov-contact-block__head">
                                    <span class="cdn-ov-field__label"><i class="fa-solid fa-phone"></i> Client Phone</span>
                                </div>
                                @if(!empty($clientContacts) && count($clientContacts) > 0)
                                    @foreach($clientContacts as $conVal)
                                        @php
                                            $cc = $conVal->country_code ?? '';
                                            $rawPhone = $conVal->phone ?? '';
                                            $formattedPhone = \App\Helpers\PhoneValidationHelper::formatAustralianPhone($rawPhone, $cc);
                                            $isV = !empty($conVal->is_verified);
                                            $vTitle = $isV ? ('Verified on ' . ($conVal->verified_at ? $conVal->verified_at->format('d/m/Y g:i A') : 'Unknown')) : 'Not verified';
                                            $dialNumber = preg_replace('/[^0-9+]/', '', $formattedPhone);
                                        @endphp
                                        <div class="cdn-ov-contact-row">
                                            <a href="tel:{{ $dialNumber }}" class="cdn-ov-contact-link" title="Click to call">
                                                <i class="fa-solid fa-phone" aria-hidden="true"></i>
                                                <span>{{ $formattedPhone }}</span>
                                            </a>
                                            <div class="cdn-ov-contact-row__actions">
                                                <button type="button" class="cdn-ov-copy-btn" title="Copy phone" onclick="navigator.clipboard.writeText('{{ $formattedPhone }}'); this.classList.add('is-copied'); setTimeout(() => this.classList.remove('is-copied'), 1500);">
                                                    <i class="fa-regular fa-copy"></i>
                                                </button>
                                                @if($isV)
                                                    <span class="cdn-ov-verify-badge is-verified" title="{{ $vTitle }}"><i class="fa-solid fa-check" aria-hidden="true"></i><span class="cdn-ov-verify-text">Verified</span></span>
                                                @else
                                                    <span class="cdn-ov-verify-badge is-unverified" title="{{ $vTitle }}"><i class="fa-regular fa-circle" aria-hidden="true"></i><span class="cdn-ov-verify-text">Unverified</span></span>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                @else
                                    <div class="cdn-ov-contact-row">
                                        <span class="cdn-ov-na">N/A</span>
                                    </div>
                                @endif
                            </div>

                            {{-- Address Block --}}
                            @php
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
                                $fullAddressString = 'N/A';
                                if ($address_Info) {
                                    $addressParts = array_filter([
                                        $address_Info->address_line_1 ?? '',
                                        $address_Info->address_line_2 ?? '',
                                        $address_Info->suburb ?? '',
                                        $address_Info->state ?? '',
                                        $address_Info->zip ?? '',
                                        (!empty($address_Info->country) && $address_Info->country !== 'Australia') ? $address_Info->country : '',
                                    ]);
                                    if (!empty($addressParts)) {
                                        $fullAddressString = implode(', ', $addressParts);
                                    } elseif (!empty($address_Info->address)) {
                                        $fullAddressString = $address_Info->address;
                                    }
                                }
                            @endphp
                            <div class="cdn-ov-contact-block">
                                <div class="cdn-ov-contact-block__head">
                                    <span class="cdn-ov-field__label"><i class="fa-solid fa-location-dot"></i> Address</span>
                                </div>
                                <div class="cdn-ov-address-row">
                                    <div class="cdn-ov-address-content">
                                        <i class="fa-solid fa-location-dot cdn-ov-address-icon" aria-hidden="true"></i>
                                        <span class="cdn-ov-address-text">{{ $fullAddressString }}</span>
                                    </div>
                                    @if(!empty($fullAddressString) && $fullAddressString !== 'N/A')
                                        <div class="cdn-ov-contact-row__actions">
                                            <button type="button" class="cdn-ov-copy-btn" title="Copy address" onclick="navigator.clipboard.writeText('{{ addslashes($fullAddressString) }}'); this.classList.add('is-copied'); setTimeout(() => this.classList.remove('is-copied'), 1500);">
                                                <i class="fa-regular fa-copy"></i>
                                            </button>
                                            <a href="https://maps.google.com/?q={{ urlencode($fullAddressString) }}" target="_blank" rel="noopener" class="cdn-ov-map-link" title="Open in Google Maps">
                                                <i class="fa-solid fa-arrow-up-right-from-square"></i>
                                            </a>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            @if($address_Info && !empty($address_Info->regional_code))
                                <div class="cdn-ov-contact-block">
                                    <span class="cdn-ov-field__label">Regional Classification</span>
                                    <span class="cdn-ov-field__value">{{ $address_Info->regional_code }}</span>
                                </div>
                            @endif
                        </div>
                        @if(!empty($cdnHeroLastUpdateOn))
                            <footer class="cdn-ov-card__foot">
                                <i class="fa-regular fa-clock"></i> Last update on {{ $cdnHeroLastUpdateOn }}
                            </footer>
                        @endif
                    </article>


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
                        <article class="card cdn-ov-card cdn-ov-card--matter">
                            <header class="cdn-ov-card__head">
                                <div class="cdn-ov-card__title">
                                    <span class="cdn-ov-card__icon" aria-hidden="true"><i class="fa-solid fa-briefcase"></i></span>
                                    <h3>Matter team &amp; details</h3>
                                </div>
                                @if($overviewClientMatterId)
                                <a class="cdn-ov-card__action changeMatterAssignee" href="javascript:;" role="button" data-client-matter-id="{{ $overviewClientMatterId }}">
                                    <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i> Edit details
                                </a>
                                @else
                                <a class="cdn-ov-card__action changeMatterAssignee" href="javascript:;" role="button">
                                    <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i> Edit details
                                </a>
                                @endif
                            </header>
                            <div class="cdn-ov-card__body cdn-ov-card__body--scroll">
                                @php
                                    $lpName = 'Ajay Bansal';
                                    $lpFirst = 'A';
                                    $lpLast = 'B';
                                    if (isset($matter_dis_ref_info_arr) && !empty($matter_dis_ref_info_arr) && $matter_dis_ref_info_arr->sel_legal_practitioner != '') {
                                        $legal_practitioner_info = \App\Models\Staff::select('first_name','last_name')->where('id', $matter_dis_ref_info_arr->sel_legal_practitioner)->first();
                                        if ($legal_practitioner_info) {
                                            $lpName = $legal_practitioner_info->first_name . ' ' . $legal_practitioner_info->last_name;
                                            $lpFirst = $legal_practitioner_info->first_name;
                                            $lpLast = $legal_practitioner_info->last_name;
                                        }
                                    }

                                    $prName = 'Michael Saleh';
                                    $prFirst = 'M';
                                    $prLast = 'S';
                                    if (isset($matter_dis_ref_info_arr) && !empty($matter_dis_ref_info_arr) && $matter_dis_ref_info_arr->sel_person_responsible != ''){
                                        $sel_person_responsible_info_arr = \App\Models\Staff::select('first_name','last_name')->where('id', $matter_dis_ref_info_arr->sel_person_responsible)->first();
                                        if ($sel_person_responsible_info_arr) {
                                            $prName = $sel_person_responsible_info_arr->first_name . ' ' . $sel_person_responsible_info_arr->last_name;
                                            $prFirst = $sel_person_responsible_info_arr->first_name;
                                            $prLast = $sel_person_responsible_info_arr->last_name;
                                        }
                                    }

                                    $paName = 'Khushi Sangroya';
                                    $paFirst = 'K';
                                    $paLast = 'S';
                                    if (isset($matter_dis_ref_info_arr) && !empty($matter_dis_ref_info_arr) && $matter_dis_ref_info_arr->sel_person_assisting != ''){
                                        $sel_person_assisting_info_arr = \App\Models\Staff::select('first_name','last_name')->where('id', $matter_dis_ref_info_arr->sel_person_assisting)->first();
                                        if ($sel_person_assisting_info_arr) {
                                            $paName = $sel_person_assisting_info_arr->first_name . ' ' . $sel_person_assisting_info_arr->last_name;
                                            $paFirst = $sel_person_assisting_info_arr->first_name;
                                            $paLast = $sel_person_assisting_info_arr->last_name;
                                        }
                                    }

                                    $officeName = 'Melbourne';
                                    if (isset($matter_dis_ref_info_arr) && !empty($matter_dis_ref_info_arr) && $matter_dis_ref_info_arr->office_id != ''){
                                        $office_info = \App\Models\Branch::select('office_name')->where('id', $matter_dis_ref_info_arr->office_id)->first();
                                        if ($office_info) {
                                            $officeName = $office_info->office_name;
                                        }
                                    }
                                @endphp

                                <section class="cdn-ov-block">
                                    <div class="cdn-ov-block__head">
                                        <p class="cdn-ov-section-label"><i class="fa-solid fa-users-gear"></i> Assigned Team</p>
                                    </div>
                                    <div class="cdn-ov-team-list">
                                        {{-- Principal Solicitor --}}
                                        <div class="cdn-ov-member-card">
                                            <div class="cdn-ov-member-avatar cdn-ov-member-avatar--navy" title="Principal Solicitor">
                                                {{ strtoupper(substr($lpFirst, 0, 1) . substr($lpLast, 0, 1)) }}
                                            </div>
                                            <div class="cdn-ov-member-info">
                                                <span class="cdn-ov-member-role">Principal</span>
                                                <strong class="cdn-ov-member-name">{{ $lpName }}</strong>
                                            </div>
                                        </div>

                                        {{-- Responsible Solicitor --}}
                                        <div class="cdn-ov-member-card">
                                            <div class="cdn-ov-member-avatar cdn-ov-member-avatar--blue" title="Responsible Solicitor">
                                                {{ strtoupper(substr($prFirst, 0, 1) . substr($prLast, 0, 1)) }}
                                            </div>
                                            <div class="cdn-ov-member-info">
                                                <span class="cdn-ov-member-role">Responsible</span>
                                                <strong class="cdn-ov-member-name">{{ $prName }}</strong>
                                            </div>
                                        </div>

                                        {{-- Paralegal --}}
                                        <div class="cdn-ov-member-card">
                                            <div class="cdn-ov-member-avatar cdn-ov-member-avatar--teal" title="Paralegal">
                                                {{ strtoupper(substr($paFirst, 0, 1) . substr($paLast, 0, 1)) }}
                                            </div>
                                            <div class="cdn-ov-member-info">
                                                <span class="cdn-ov-member-role">Paralegal</span>
                                                <strong class="cdn-ov-member-name">{{ $paName }}</strong>
                                            </div>
                                        </div>

                                        {{-- Handling Office --}}
                                        <div class="cdn-ov-member-card">
                                            <div class="cdn-ov-member-avatar cdn-ov-member-avatar--slate" title="Handling Office">
                                                <i class="fa-solid fa-building" style="font-size: 0.75rem;"></i>
                                            </div>
                                            <div class="cdn-ov-member-info">
                                                <span class="cdn-ov-member-role">Office</span>
                                                <strong class="cdn-ov-member-name">{{ $officeName }}</strong>
                                            </div>
                                        </div>
                                    </div>
                                </section>

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

                                <section class="cdn-ov-block">
                                    <div class="cdn-ov-block__head">
                                        <p class="cdn-ov-section-label"><i class="fa-solid fa-folder-tree"></i> Case Details</p>
                                    </div>
                                    @if(count($mdRows) > 0)
                                        <div class="cdn-ov-case-grid">
                                            @foreach($mdRows as $mdRow)
                                                <div class="cdn-ov-case-item{{ $mdRow['label'] === 'Case detail' ? ' cdn-ov-case-item--full' : '' }}">
                                                    @if($mdRow['label'] !== '')
                                                        <span class="cdn-ov-case-item__label">{{ $mdRow['label'] }}</span>
                                                    @endif
                                                    @if(strtolower((string)$mdRow['label']) === "our client's role")
                                                        <span class="cdn-ov-role-pill"><i class="fa-solid fa-user-tag"></i> {{ $mdRow['value'] }}</span>
                                                    @else
                                                        <span class="cdn-ov-case-item__value">{{ $mdRow['value'] }}</span>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        <p class="cdn-ov-empty">No case details yet. Use <strong>Edit details</strong> to add role, subtype, dates, or notes.</p>
                                    @endif
                                </section>

                                @if($linkedOtherParties->isNotEmpty())
                                    <section class="cdn-ov-block cdn-ov-block--parties">
                                        <div class="cdn-ov-block__head">
                                            <p class="cdn-ov-section-label"><i class="fa-solid fa-users"></i> Other Parties</p>
                                            <span class="cdn-ov-count">{{ $linkedOtherParties->count() }}</span>
                                        </div>
                                        <div class="cdn-ov-scroll cdn-ov-scroll--parties" tabindex="0" role="region" aria-label="Other parties">
                                            <ul class="cdn-ov-party-list">
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
                                                    <li class="cdn-ov-party-item">
                                                        <div class="cdn-ov-party-item__main">
                                                            <strong class="cdn-ov-party-item__name">{{ $opp->name }}</strong>
                                                            @if($oppRoleLabel)
                                                                <span class="cdn-ov-party-item__role">{{ $oppRoleLabel }}</span>
                                                            @endif
                                                        </div>
                                                        @if($repParts !== [])
                                                            <div class="cdn-ov-party-item__meta">Rep: {{ implode(' · ', $repParts) }}</div>
                                                        @endif
                                                        @if(! empty($opp->rep_notes))
                                                            <div class="cdn-ov-party-item__meta">{{ $opp->rep_notes }}</div>
                                                        @endif
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    </section>
                                @endif
                            </div>
                        </article>
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
                <div class="card cdn-ov-hearings-card">
                    <div class="cdn-ov-hearings-card__header">
                        <div class="cdn-ov-hearings-card__title">
                            <span class="cdn-ov-card__icon cdn-ov-card__icon--gavel"><i class="fa-solid fa-gavel" aria-hidden="true"></i></span>
                            <div>
                                <h3>Court Hearings</h3>
                                <span class="cdn-ov-hearings-subtitle">Scheduled proceedings and judicial listings for this client</span>
                            </div>
                        </div>
                        <div class="cdn-ov-hearings-card__meta">
                            <span class="cdn-ov-hearings-count-pill">
                                <i class="fa-regular fa-calendar-check"></i>
                                {{ $clientHearings->count() }} {{ \Illuminate\Support\Str::plural('Hearing', $clientHearings->count()) }}
                            </span>
                        </div>
                    </div>
                    <div class="cdn-ov-hearings-table-wrap">
                        <table class="cdn-ov-hearings-table">
                            <thead>
                                <tr>
                                    <th><i class="fa-regular fa-calendar me-1"></i> Date</th>
                                    <th><i class="fa-regular fa-clock me-1"></i> Time</th>
                                    <th>Hearing Type</th>
                                    <th><i class="fa-solid fa-landmark me-1"></i> Court Name</th>
                                    <th>Case Number</th>
                                    <th>Judge</th>
                                    <th>Status</th>
                                    <th>Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($clientHearings as $ch)
                                @php
                                    $statusStr = strtolower(trim((string) $ch->status));
                                    $statusClass = match($statusStr) {
                                        'scheduled' => 'is-scheduled',
                                        'completed' => 'is-completed',
                                        'adjourned' => 'is-adjourned',
                                        'cancelled' => 'is-cancelled',
                                        default => 'is-default',
                                    };
                                @endphp
                                <tr>
                                    <td class="cdn-ov-hearing-date">
                                        <strong>{{ $ch->hearing_date->format('d/m/Y') }}</strong>
                                    </td>
                                    <td class="cdn-ov-hearing-time">
                                        @if($ch->hearing_time)
                                            <span class="cdn-ov-time-chip">{{ \Carbon\Carbon::parse($ch->hearing_time)->format('g:i A') }}</span>
                                        @else
                                            <span class="cdn-ov-muted-dash">—</span>
                                        @endif
                                    </td>
                                    <td class="cdn-ov-hearing-type">
                                        {{ $ch->hearing_type ?: '—' }}
                                    </td>
                                    <td class="cdn-ov-hearing-court">
                                        <span>{{ $ch->court_name ?: '—' }}</span>
                                    </td>
                                    <td class="cdn-ov-hearing-caseno">
                                        @if($ch->case_number)
                                            <code class="cdn-ov-case-code">{{ $ch->case_number }}</code>
                                        @else
                                            <span class="cdn-ov-muted-dash">—</span>
                                        @endif
                                    </td>
                                    <td class="cdn-ov-hearing-judge">
                                        {{ $ch->judge_name ?: '—' }}
                                    </td>
                                    <td class="cdn-ov-hearing-status-cell">
                                        <span class="cdn-ov-hearing-status {{ $statusClass }}">
                                            <span class="cdn-ov-status-dot"></span>
                                            {{ $ch->status ?: 'Unknown' }}
                                        </span>
                                    </td>
                                    <td class="cdn-ov-hearing-notes">
                                        {{ $ch->notes ?: '—' }}
                                    </td>
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
