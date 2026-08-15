@extends('layouts.crm_client_detail_dashboard')

@php
    $latestMatterRefNo = null;
    $__crmEditLeadType = isset($fetchedData)
        && (($fetchedData->type ?? null) === 1
            || in_array(trim((string) ($fetchedData->type ?? '')), ['lead', 'l', '1'], true));
    if (isset($fetchedData) && (($fetchedData->type ?? '') === 'client' || $__crmEditLeadType)) {
        $latestMatter = \App\Models\ClientMatter::where('client_id', $fetchedData->id)
            ->where('matter_status', 1)
            ->orderByDesc('id')
            ->first();

        if ($latestMatter) {
            $latestMatterRefNo = $latestMatter->client_unique_matter_no;
        }
    }

    $editClientInitials = '';
    if (isset($fetchedData)) {
        $editFn = trim((string) ($fetchedData->first_name ?? ''));
        $editLn = trim((string) ($fetchedData->last_name ?? ''));
        $editClientInitials = strtoupper(mb_substr($editFn, 0, 1) . mb_substr($editLn, 0, 1));
        if ($editClientInitials === '') {
            $editCid = (string) ($fetchedData->client_id ?? '');
            $editClientInitials = strtoupper(mb_substr($editCid !== '' ? $editCid : 'C', 0, 2));
        }
    }
    $editIdLabel = ($fetchedData->type ?? '') === 'lead' ? 'Lead ID' : 'Client ID';
@endphp

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/address-autocomplete.css') }}">
    <link rel="stylesheet" href="{{asset('css/client-forms.css')}}?v={{ time() }}">
    <link rel="stylesheet" href="{{asset('css/clients/edit-client-components.css')}}?v={{ time() }}">
    <style>
        .tab-content{
            display:block !important
        }
        tr.matter-tab-row-highlight td {
            background-color: #ebf3ff !important;
            transition: background-color 0.35s ease;
        }

        /* ---- Matter type dropdown ---- */
        .matter-type-select {
            border: 2px solid var(--border-color, #c8dcef) !important;
            border-radius: 8px !important;
            font-size: 0.97em !important;
            transition: border-color 0.15s, box-shadow 0.15s;
        }
        .matter-type-select:focus {
            border-color: var(--secondary-color, #3a6fa8) !important;
            box-shadow: 0 0 0 3px rgba(200, 153, 42, 0.2) !important;
            outline: none !important;
        }
        /* ---- Dynamic matter form selects ---- */
        .dyn-select {
            border: 1.5px solid var(--border-color, #c8dcef) !important;
            border-radius: 6px !important;
            height: 40px !important;
            font-size: 0.94em !important;
            color: var(--text-color, #1a2c40) !important;
            padding: 6px 10px !important;
        }
        .dyn-select:focus {
            border-color: var(--secondary-color, #3a6fa8) !important;
            box-shadow: 0 0 0 2px rgba(200, 153, 42, 0.15) !important;
        }
        #matterSpecificFields .form-control {
            border: 1.5px solid var(--border-color, #c8dcef);
            border-radius: 6px;
            height: 40px;
            font-size: 0.93em;
            color: var(--text-color, #1a2c40);
        }
        #matterSpecificFields select.form-control { height: 40px; }
        .dyn-required { color: #c0392b; font-weight: bold; margin-left: 2px; }
        @keyframes dynFadeIn { from { opacity: 0; transform: translateY(-6px); } to { opacity: 1; transform: translateY(0); } }
        #subTypeFieldsContainer > div { animation: dynFadeIn 0.3s ease; }

        </style>
@endpush

@section('content')
    <div class="crm-container">
        <div class="main-content">

            <!-- Display General Errors -->
            @if ($errors->any())
                <div class="alert alert-danger">
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <!-- Mobile Sidebar Toggle -->
            <button class="sidebar-toggle" onclick="toggleSidebar()">
                <i class="fa-solid fa-bars"></i>
            </button>

            <!-- Sidebar Navigation -->
            <div class="sidebar-navigation" id="sidebarNav">
                <div class="nav-header">
                    <h3><i class="fa-solid {{ $fetchedData->type == 'client' ? 'fa-id-card' : 'fa-user-edit' }}"></i> {{ $fetchedData->type == 'lead' ? 'Edit Lead' : 'Client Details Form' }}</h3>
                </div>

                <div class="sidebar-identity">
                    <div class="sidebar-identity__avatar" aria-hidden="true">{{ $editClientInitials }}</div>
                    <div class="sidebar-identity__body">
                        <div class="sidebar-identity__name">{{ $fetchedData->first_name }} {{ $fetchedData->last_name }}</div>
                        <div class="sidebar-identity__id">{{ $editIdLabel }}: {{ $fetchedData->client_id }}</div>
                        @if($latestMatterRefNo)
                            <div class="sidebar-identity__matter">Active matter: {{ $latestMatterRefNo }}</div>
                        @endif
                    </div>
                </div>

                <nav class="sidebar-primary-nav" aria-label="Client record sections">
                    <div class="sidebar-primary-nav__label">Sections</div>
                    <button type="button" class="sidebar-primary-tab active" data-tab-id="home" onclick="showTab('home')">
                        <i class="fa-solid fa-user"></i><span>Client Info</span>
                    </button>
                    <button type="button" class="sidebar-primary-tab" data-tab-id="menu2" onclick="showTab('menu2')">
                        <i class="fa-solid fa-briefcase"></i><span>Matter Details</span>
                    </button>
                    <button type="button" class="sidebar-primary-tab" data-tab-id="menu4" onclick="showTab('menu4')">
                        <i class="fa-solid fa-gavel"></i><span>Court Dates &amp; Hearings</span>
                    </button>
                </nav>

                <div class="sidebar-actions">
                    <button type="button" class="sidebar-back-btn" onclick="goBackWithRefresh()">
                        <i class="fa-solid fa-arrow-left"></i>
                        <span>Back</span>
                    </button>
                </div>
            </div>
            
            <!-- Configuration for external JavaScript -->
            <script>
                // Configuration object for edit-client.js
                window.editClientConfig = {
                    rootUrl: @json(rtrim(url('/'), '/')),
                    visaTypesRoute: '{{ route("getVisaTypes") }}',
                    countriesRoute: '{{ route("getCountries") }}',
                    csrfToken: '{{ csrf_token() }}'
                };
                
                // Current client ID for excluding from search results
                window.currentClientId = '{{ $fetchedData->id }}';
                window.currentClientType = @json($fetchedData->type);
                window.latestClientMatterRef = @json($latestMatterRefNo);

               function showTab(tabId, options){
    options = options || {};
    var shouldScroll = options.scroll !== false;

    $(".main-content-area .tab-pane").removeClass("show active");
    var $pane = $("#" + tabId);
    if ($pane.length) {
        $pane.addClass("show active");
    }

    var menu3 = document.getElementById('menu3');
    if (menu3) {
        menu3.style.setProperty('display', 'none', 'important');
    }

    document.querySelectorAll('.sidebar-primary-tab').forEach(function (btn) {
        btn.classList.toggle('active', btn.getAttribute('data-tab-id') === tabId);
    });

    if (shouldScroll) {
        var mainArea = document.querySelector('.main-content-area');
        if (mainArea) {
            var top = mainArea.getBoundingClientRect().top + window.scrollY - 88;
            window.scrollTo({ top: Math.max(top, 0), behavior: 'smooth' });
        }
    }

    var sidebar = document.getElementById('sidebarNav');
    if (sidebar && window.innerWidth <= 1024) {
        sidebar.classList.remove('open');
    }
               }
               window.showTab = showTab;

            </script>

            <!-- Main Content Area -->
            <div class="main-content-area">

  <div class="tab-content">
  <form  id="editClientForm" action="{{ route('clients.update') }}" method="POST">
                    @csrf
                    <input type="hidden" name="id" value="{{ $fetchedData->id }}">
                    <input type="hidden" name="type" value="{{ $fetchedData->type }}">

    <div id="home" class="tab-pane fade show active" role="tabpanel">
                <!-- Personal Section -->
                <section id="personalSection" class="content-section">
                    <section id="section-basic-info" class="form-section">
                        <div class="section-header">
                            <h3><i class="fa-solid fa-user-circle"></i> Basic Information</h3>
                            <div class="section-actions">
                                <button type="button" class="edit-section-btn" onclick="toggleEditMode('basicInfo')">
                                    <i class="fa-solid fa-pen"></i>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Summary View -->
                        <div id="basicInfoSummary" class="summary-view">
                            <div class="summary-grid summary-grid--basic-info">
                                <div class="summary-item">
                                    <span class="summary-label">Name:</span>
                                    <span class="summary-value">{{ $fetchedData->first_name }} {{ $fetchedData->last_name }}</span>
                                </div>
                                <div class="summary-item">
                                    <span class="summary-label">{{ $fetchedData->type == 'lead' ? 'Lead ID' : 'Client ID' }}:</span>
                                    <span class="summary-value">{{ $fetchedData->client_id }}</span>
                                </div>
                                <div class="summary-item">
                                    <span class="summary-label">Date of Birth:</span>
                                    <span class="summary-value">{{ $fetchedData->dob ? date('d/m/Y', strtotime($fetchedData->dob)) : 'Not set' }}</span>
                                </div>
                                <div class="summary-item">
                                    <span class="summary-label">Age:</span>
                                    <span class="summary-value">{{ $fetchedData->age ?: 'Not calculated' }}</span>
                                </div>
                                <div class="summary-item">
                                    <span class="summary-label">Gender:</span>
                                    <span class="summary-value">{{ $fetchedData->gender ?: 'Not set' }}</span>
                                </div>
                                <div class="summary-item">
                                    <span class="summary-label">Marital Status:</span>
                                    <span class="summary-value">{{ $fetchedData->marital_status ?: 'Not set' }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Edit View -->
                        <div id="basicInfoEdit" class="edit-view hidden">
                            <div class="content-grid">
                                <div class="form-group">
                                    <label for="firstName">First Name</label>
                                    <input type="text" id="firstName" name="first_name" value="{{ $fetchedData->first_name }}" required>
                                    @error('first_name')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                                <div class="form-group">
                                    <label for="lastName">Last Name</label>
                                    <input type="text" id="lastName" name="last_name" value="{{ $fetchedData->last_name }}">
                                    @error('last_name')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>

                                <div class="form-group">
                                    <label for="clientId">{{ $fetchedData->type == 'lead' ? 'Lead ID' : ($fetchedData->type == 'client' ? 'Client ID' : '') }}</label>
                                    <input type="text" id="clientId" name="client_id" value="{{ $fetchedData->client_id }}" readonly>
                                    @error('client_id')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>

                                <div class="form-group">
                                    <label for="dob">Date of Birth</label>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <input type="text" id="dob" name="dob" value="{{ $fetchedData->dob ? date('d/m/Y', strtotime($fetchedData->dob)) : '' }}" placeholder="dd/mm/yyyy" autocomplete="off" style="flex: 1;">
                                        @if($fetchedData->updated_at)
                                            <span class="last-updated-badge" style="font-size: 0.85em; color: #6c757d; white-space: nowrap;" title="Last updated: {{ $fetchedData->updated_at->format('M j, Y g:i A') }}">
                                                <i class="fa-regular fa-circle" style="color: #6c757d; margin-right: 4px;"></i>
                                                Updated: {{ $fetchedData->updated_at->format('d/m/Y') }}
                                            </span>
                                        @endif
                                    </div>
                                    @error('dob')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>

                                <div class="form-group">
                                    <label for="age">Age</label>
                                    <input type="text" id="age" name="age" value="{{ $fetchedData->age }}" readonly>
                                    @error('age')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>

                                <div class="form-group">
                                    <label for="gender">Gender <span class="text-danger">*</span></label>
                                    <select id="gender" name="gender" required>
                                        <option value="">Select Gender</option>
                                        <option value="Male" {{ $fetchedData->gender == 'Male' ? 'selected' : '' }}>Male</option>
                                        <option value="Female" {{ $fetchedData->gender == 'Female' ? 'selected' : '' }}>Female</option>
                                        <option value="Other" {{ $fetchedData->gender == 'Other' ? 'selected' : '' }}>Other</option>
                                    </select>
                                    @error('gender')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>

                                <div class="form-group">
                                    <label for="maritalStatus">Marital Status</label>
                                    <select id="maritalStatus" name="marital_status">
                                        <option value="">Select Marital Status</option>
                                        <option value="Never Married" {{ ($fetchedData->marital_status == 'Never Married' || $fetchedData->marital_status == 'Single') ? 'selected' : '' }}>Never Married</option>
                                        <option value="Engaged" {{ $fetchedData->marital_status == 'Engaged' ? 'selected' : '' }}>Engaged</option>
                                        <option value="Married" {{ $fetchedData->marital_status == 'Married' ? 'selected' : '' }}>Married</option>
                                        <option value="De Facto" {{ ($fetchedData->marital_status == 'Defacto' || $fetchedData->marital_status == 'De Facto') ? 'selected' : '' }}>De Facto</option>
                                        <option value="Separated" {{ $fetchedData->marital_status == 'Separated' ? 'selected' : '' }}>Separated</option>
                                        <option value="Divorced" {{ $fetchedData->marital_status == 'Divorced' ? 'selected' : '' }}>Divorced</option>
                                        <option value="Widowed" {{ $fetchedData->marital_status == 'Widowed' ? 'selected' : '' }}>Widowed</option>
                                    </select>
                                    @error('marital_status')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                            <div class="edit-actions">
                                <button type="button" class="btn btn-primary" onclick="saveBasicInfo()">Save</button>
                                <button type="button" class="btn btn-secondary" onclick="cancelEdit('basicInfo')">Cancel</button>
                            </div>
                        </div>
                    </section>

                    <!-- Contact Information -->
                    <section id="section-phone-numbers" class="form-section">
                        <div class="section-header">
                            <h3><i class="fa-solid fa-mobile-screen-button"></i> Phone Numbers</h3>
                            <div class="section-actions">
                                <button type="button" class="edit-section-btn" onclick="toggleEditMode('phoneNumbers')">
                                    <i class="fa-solid fa-pen"></i>
                                </button>
                                <button type="button" class="add-section-btn" onclick="addPhoneNumber()" title="Add Phone Number">
                                    <i class="fa-solid fa-plus"></i>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Summary View -->
                        <div id="phoneNumbersSummary" class="summary-view">
                            @if($clientContacts->count() > 0)
                                <div class="contact-row-list">
                                    @foreach($clientContacts as $index => $contact)
                                        <div class="summary-item contact-row">
                                            <span class="summary-label">{{ $contact->contact_type }}:</span>
                                            <span class="summary-value">{{ $contact->country_code }}{{ $contact->phone }}</span>
                                            <!-- Verification Button/Badge -->
                                            @if($contact->canVerify())
                                                @if($contact->is_verified)
                                                    <span class="verified-badge" title="Verified on {{ $contact->verified_at ? $contact->verified_at->format('M j, Y g:i A') : 'Unknown' }}">
                                                        <i class="fa-solid fa-circle-check"></i> Verified
                                                    </span>
                                                @else
                                                    <button type="button" class="btn-verify-phone" onclick="sendOTP({{ $contact->id ?? 'null' }}, '{{ $contact->phone }}', '{{ $contact->country_code }}')" data-contact-id="{{ $contact->id ?? '' }}">
                                                        <i class="fa-solid fa-lock"></i> Verify
                                                    </button>
                                                @endif
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="empty-state">
                                    <p>No phone numbers added yet.</p>
                                </div>
                            @endif
                        </div>

                        <!-- Edit View -->
                        <div id="phoneNumbersEdit" class="edit-view hidden">
                            <div id="phoneNumbersContainer">
                                @foreach($clientContacts as $index => $contact)
                                    <x-client-edit.phone-number-field :index="$index" :contact="$contact" />
                                @endforeach
                            </div>

                            <button type="button" class="add-item-btn" onclick="addPhoneNumber()"><i class="fa-solid fa-circle-plus"></i> Add Phone Number</button>
                            <div class="edit-actions">
                                <button type="button" class="btn btn-primary" onclick="savePhoneNumbers()">Save</button>
                                <button type="button" class="btn btn-secondary" onclick="cancelEdit('phoneNumbers')">Cancel</button>
                            </div>
                        </div>
                    </section>

                    <!-- Email Addresses -->
                    <section id="section-email-addresses" class="form-section">
                        <div class="section-header">
                            <h3><i class="fa-solid fa-envelope"></i> Email Addresses</h3>
                            <div class="section-actions">
                                <button type="button" class="edit-section-btn" onclick="toggleEditMode('emailAddresses')">
                                    <i class="fa-solid fa-pen"></i>
                                </button>
                                <button type="button" class="add-section-btn" onclick="addEmailAddress()" title="Add Email Address">
                                    <i class="fa-solid fa-plus"></i>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Summary View -->
                        <div id="emailAddressesSummary" class="summary-view">
                            @if($emails->count() > 0)
                                <div class="contact-row-list">
                                    @foreach($emails as $index => $email)
                                        <div class="summary-item contact-row">
                                            <span class="summary-label">{{ $email->email_type }}:</span>
                                            <span class="summary-value">{{ $email->email }}</span>
                                            <!-- Verification Button/Badge -->
                                            @if($email->is_verified)
                                                <span class="verified-badge" title="Verified on {{ $email->verified_at ? $email->verified_at->format('M j, Y g:i A') : 'Unknown' }}">
                                                    <i class="fa-solid fa-circle-check"></i> Verified
                                                </span>
                                            @else
                                                <button type="button" class="btn-verify-email" onclick="sendEmailVerification({{ $email->id }}, '{{ $email->email }}')" data-email-id="{{ $email->id }}">
                                                    <i class="fa-solid fa-lock"></i> Verify
                                                </button>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="empty-state">
                                    <p>No email addresses added yet.</p>
                                </div>
                            @endif
                        </div>

                        <!-- Edit View -->
                        <div id="emailAddressesEdit" class="edit-view hidden">
                            <div id="emailAddressesContainer">
                                @foreach($emails as $index => $email)
                                    <x-client-edit.email-field :index="$index" :email="$email" />
                                @endforeach
                            </div>

                            <button type="button" class="add-item-btn" onclick="addEmailAddress()"><i class="fa-solid fa-circle-plus"></i> Add Email Address</button>
                            <div class="edit-actions">
                                <button type="button" class="btn btn-primary" onclick="saveEmailAddresses()">Save</button>
                                <button type="button" class="btn btn-secondary" onclick="cancelEdit('emailAddresses')">Cancel</button>
                            </div>
                        </div>
                    </section>

                    <x-client-edit.address-section
                        :clientAddresses="$clientAddresses"
                        :searchRoute="route('clients.searchAddressFull')"
                        :detailsRoute="route('clients.getPlaceDetails')"
                        :csrfToken="csrf_token()"
                    />
                </section>

                {{-- Lead Source & Assignment Section --}}
                <section id="section-lead-source" class="content-section" style="margin-bottom:1.25rem;">
                    <section class="form-section">
                        <div class="section-header">
                            <h3><i class="fa-solid fa-funnel-dollar"></i> Lead Source &amp; Assignment</h3>
                        </div>
                        <p class="text-muted" style="margin-top:0;margin-bottom:1rem;">Where did this {{ $__crmEditLeadType ? 'lead' : 'client' }} come from?</p>

                        <div class="row">
                            <div class="col-md-5">
                                <div class="form-group">
                                    <label for="client_lead_source" style="font-weight:600;">Lead Source</label>
                                    <select class="form-control" id="client_lead_source" name="lead_source"
                                        onchange="saveLeadSourceInfo()"
                                        style="border:1.5px solid #d0daf5;border-radius:8px;height:42px;font-size:0.96em;">
                                        <option value="">— Select Source —</option>
                                        @php
                                            $leadSources = [
                                                'Online Enquiry','Walk-in','Phone Call','Email',
                                                'Referral','Word of Mouth','Social Media','Facebook',
                                                'Instagram','LinkedIn','Google','Google Ads',
                                                'Sub Agent','Legal Aid','Court Referral','Other',
                                            ];
                                            $currentSource = $fetchedData->source ?? $fetchedData->lead_source ?? '';
                                        @endphp
                                        @foreach($leadSources as $src)
                                            <option value="{{ $src }}" {{ $currentSource === $src ? 'selected' : '' }}>
                                                {{ $src }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-5">
                                <div class="form-group">
                                    <label for="client_refer_by_inline" style="font-weight:600;">Referred by <small class="text-muted">(optional)</small></label>
                                    <input type="text" class="form-control" id="client_refer_by_inline" name="refer_by"
                                        value="{{ old('refer_by', $fetchedData->refer_by ?? '') }}"
                                        maxlength="500" placeholder="e.g. name, staff member, campaign"
                                        onblur="saveLeadSourceInfo()"
                                        style="border:1.5px solid #d0daf5;border-radius:8px;height:42px;">
                                </div>
                            </div>
                            <div class="col-md-2" style="display:flex;align-items:flex-end;padding-bottom:15px;">
                                <span id="leadSourceSaveMsg" class="small" role="status" style="font-size:0.85em;"></span>
                            </div>
                        </div>
                    </section>
                </section>

               
    </div>
    @include('crm.clients.partials.client-edit-matter-tab-pane')


    @include('crm.clients.partials.client-edit-court-tab-pane')

    {{-- End menu4 --}}

    </form>
  </div>
               
            </div>
        </div>
    </div>

    <!-- Go to Top Button -->
    <button id="goToTopBtn" class="go-to-top-btn" onclick="scrollToTop()" title="Go to Top">
        <i class="fa-solid fa-chevron-up"></i>
    </button>

    @include('crm.clients.partials.add-matter-modal')

    <!-- OTP Verification Modal -->
    <div id="otpVerificationModal" class="modal" style="display: none;">
        <div class="modal-content otp-modal">
            <div class="modal-header">
                <h3>Verify Phone Number</h3>
                <button type="button" class="close-btn" onclick="closeOTPModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="otp-info">
                    <p>We've sent a 6-digit verification code to:</p>
                    <p class="phone-display" id="otpPhoneDisplay"></p>
                    <p class="otp-timer" id="otpTimer">Code expires in <span id="timerCountdown">5:00</span></p>
                    <div class="otp-instruction">
                        <p><strong>Please ask the client to provide the verification code they received via SMS.</strong></p>
                    </div>
                </div>
                
                <div class="otp-input-container">
                    <input type="text" maxlength="1" class="otp-digit" data-index="0" autocomplete="off">
                    <input type="text" maxlength="1" class="otp-digit" data-index="1" autocomplete="off">
                    <input type="text" maxlength="1" class="otp-digit" data-index="2" autocomplete="off">
                    <input type="text" maxlength="1" class="otp-digit" data-index="3" autocomplete="off">
                    <input type="text" maxlength="1" class="otp-digit" data-index="4" autocomplete="off">
                    <input type="text" maxlength="1" class="otp-digit" data-index="5" autocomplete="off">
                </div>
                
                <div class="otp-actions">
                    <button type="button" class="btn-resend-otp" id="resendOTPBtn" onclick="resendOTP()" disabled>
                        Resend Code
                    </button>
                    <span class="resend-timer" id="resendTimer" style="display: none;">Resend available in <span id="resendCountdown">30</span>s</span>
                </div>
                
                <div class="otp-messages">
                    <div id="otpErrorMessage" class="error-message" style="display: none;"></div>
                    <div id="otpSuccessMessage" class="success-message" style="display: none;"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeOTPModal()">Cancel</button>
                <button type="button" class="btn btn-primary" id="verifyOTPBtn" onclick="verifyOTP()">Verify</button>
            </div>
        </div>
    </div>

    @include('crm.clients.modals.change-matter-assignee-modal')

    @include('crm.clients.partials.matter-required-before-convert-modal')

    @push('scripts')
    <script>
        window.ClientDetailConfig = window.ClientDetailConfig || {};
        window.ClientDetailConfig.urls = window.ClientDetailConfig.urls || {};
        window.ClientDetailConfig.urls.fetchClientMatterAssignee = @json(url('/clients/fetchClientMatterAssignee'));
    </script>
    <link rel="stylesheet" href="{{ asset('css/crm/other-party-picker.css') }}?v={{ @filemtime(public_path('css/crm/other-party-picker.css')) ?: time() }}">
    <script src="{{ asset('js/crm/clients/other-party-picker.js') }}?v={{ time() }}"></script>
    <script>
        window.MATTER_PARTY_ROLES_BY_STREAM = @json(config('matter_streams.party_roles_by_stream', []));
        window.OTHER_PARTY_SEARCH_URL = @json(route('api.search.other.party'));
        window.CONTACT_PERSON_SEARCH_URL = @json(route('api.search.contact.person'));
        window.countriesData = @json($countries);
        window.storeLeadMatterFromEditUrl = @json(route('clients.storeLeadMatterFromEdit'));
        window.currentClientId = @json((string) ($fetchedData->id ?? ''));
    </script>
    <script src="{{ asset('js/crm/clients/matter-assignee-modal.js') }}?v={{ time() }}"></script>
    @if(is_array($matterFormForLead ?? null))
        @include('crm.clients.partials.quick-add-matter-scripts')
    @endif
    <script src="{{ asset('js/clients/edit-client.js') }}?v={{ file_exists(public_path('js/clients/edit-client.js')) ? filemtime(public_path('js/clients/edit-client.js')) : 1 }}"></script>
    <script src="{{ asset('js/clients/address-delete.js') }}?v={{ file_exists(public_path('js/clients/address-delete.js')) ? filemtime(public_path('js/clients/address-delete.js')) : 1 }}"></script>
    <script>
        (function () {
            var tabMap = {
                'matter_case': 'menu2',
                'family': 'menu3',
                'hearings': 'menu4',
                'court': 'menu4',
            };
            function activateTabFromUrl() {
                try {
                    var qs = new URLSearchParams(window.location.search || '');
                    var editTab = qs.get('edit_tab') || '';
                    var hash = (window.location.hash || '').replace('#', '');
                    var targetId = tabMap[editTab] || hash || '';
                    if (!targetId || !['home','menu1','menu2','menu3','menu4'].includes(targetId)) {
                        return;
                    }
                    if (typeof window.showTab === 'function') {
                        window.showTab(targetId, { scroll: false });
                    } else {
                        var $pane = $('#' + targetId);
                        if (!$pane.length) return;
                        document.querySelectorAll('.sidebar-primary-tab').forEach(function (btn) {
                            btn.classList.toggle('active', btn.getAttribute('data-tab-id') === targetId);
                        });
                        $('.main-content-area .tab-pane').removeClass('show active');
                        $pane.addClass('show active');
                        var menu3 = document.getElementById('menu3');
                        if (menu3) {
                            menu3.style.setProperty('display', 'none', 'important');
                        }
                    }
                    // Highlight matter row if ref given
                    if (targetId === 'menu2') {
                        var ref = qs.get('matter_ref');
                        if (ref) {
                            window.setTimeout(function () {
                                var decoded = decodeURIComponent(String(ref).replace(/\+/g, ' '));
                                var $rowLink = $('.matter-tab-ref-link').filter(function () {
                                    return $(this).text().trim() === decoded;
                                });
                                if ($rowLink.length) {
                                    var $tr = $rowLink.closest('tr');
                                    var top = $tr.offset() ? $tr.offset().top : 0;
                                    $('html, body').animate({ scrollTop: Math.max(top - 100, 0) }, 350);
                                    $tr.addClass('matter-tab-row-highlight');
                                    window.setTimeout(function () { $tr.removeClass('matter-tab-row-highlight'); }, 5000);
                                }
                            }, 250);
                        }
                    }
                } catch (e) { /* ignore */ }
            }
            if (window.jQuery) {
                jQuery(function () {
                    activateTabFromUrl();
                    window.setTimeout(activateTabFromUrl, 100);
                });
            } else {
                document.addEventListener('DOMContentLoaded', function () {
                    window.setTimeout(activateTabFromUrl, 0);
                    window.setTimeout(activateTabFromUrl, 200);
                });
            }
        })();
    </script>
    <script src="{{asset('js/address-autocomplete.js')}}"></script>
    <script src="{{asset('js/clients/address-regional-codes.js')}}"></script>
    {{-- Google Maps library removed - using backend proxy for address autocomplete --}}

    <script>
    // =====================================================
    // Lead Source & Assignment save
    // =====================================================
    window.saveLeadSourceInfo = async function() {
        var msgEl  = document.getElementById('leadSourceSaveMsg');
        var srcEl  = document.getElementById('client_lead_source');
        var refEl  = document.getElementById('client_refer_by_inline');
        if (msgEl) msgEl.textContent = '';

        var fd = new FormData();
        fd.append('_token', window.editClientConfig.csrfToken);
        fd.append('id', String(window.currentClientId || '{{ $fetchedData->id }}').trim());
        fd.append('section', 'leadSource');
        fd.append('lead_source', srcEl ? srcEl.value : '');
        fd.append('refer_by',   refEl ? refEl.value.trim() : '');

        try {
            var res = await fetch(@json(route('clients.saveSection')), {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': window.editClientConfig.csrfToken },
                body: fd
            });
            var data = await res.json().catch(function(){ return {}; });
            if (res.ok && data.success) {
                if (msgEl) {
                    msgEl.innerHTML = '<i class="fa-solid fa-circle-check" style="color:#188038;"></i> <span style="color:#188038;">Saved</span>';
                    setTimeout(function(){ msgEl.innerHTML = ''; }, 2500);
                }
                return;
            }
            var err = data.message || 'Could not save.';
            if (data.errors) err += ' ' + Object.values(data.errors).flat().join(' ');
            if (msgEl) { msgEl.innerHTML = '<span style="color:#c5221f;">' + err + '</span>'; }
        } catch(e) {
            if (msgEl) { msgEl.innerHTML = '<span style="color:#c5221f;">Network error. Try again.</span>'; }
        }
    };

    @include('crm.clients.partials.client-edit-matter-court-scripts')
    </script>
    @endpush
@endsection
