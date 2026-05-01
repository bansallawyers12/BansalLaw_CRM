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
@endphp

@push('styles')
<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
  <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
    <link rel="stylesheet" href="{{ asset('css/address-autocomplete.css') }}">
    <link rel="stylesheet" href="{{asset('css/client-forms.css')}}">
    <link rel="stylesheet" href="{{asset('css/clients/edit-client-components.css')}}">
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
                <i class="fas fa-bars"></i>
            </button>

            <!-- Sidebar Navigation -->
            <div class="sidebar-navigation" id="sidebarNav">
                <div class="nav-header">
                    <h3><i class="fas {{ $fetchedData->type == 'client' ? 'fa-id-card' : 'fa-user-edit' }}"></i> {{ $fetchedData->type == 'lead' ? 'Edit Lead' : ($fetchedData->type == 'client' ? 'Client Details Form' : '') }} : {{ $fetchedData->first_name }} {{ $fetchedData->last_name }}</h3>
                    <div class="client-id">
                        {{ $fetchedData->type == 'lead' ? 'Lead ID' : ($fetchedData->type == 'client' ? 'Client ID' : '') }} : {{ $fetchedData->client_id }}
                    </div>
                </div>
                <nav class="nav-menu">
                    <button class="nav-item " >
                        <i class="fas fa-user-circle"></i>
                        <span>Name :   {{ $fetchedData->first_name }} {{ $fetchedData->last_name }}
                    </span>
                    </button>
                    <button class="nav-item" >
                        <i class="fas fa-id-card"></i>
                        <span>Client ID :   {{ $fetchedData->type == 'lead' ? 'Lead ID' : ($fetchedData->type == 'client' ? 'Client ID' : '') }} : {{ $fetchedData->client_id }}
                        </span>
                    </button>
                    <button class="nav-item" >
                        <i class="fas fa fa-calendar"></i>
                        <span>Date of Birth : {{ $fetchedData->dob ? date('d/m/Y', strtotime($fetchedData->dob)) : 'Not set' }}</span>
                    </button>
                    <button class="nav-item" >
                        <i class="fas fa-info-circle"></i>
                        <span>Gender : {{ $fetchedData->gender ?: 'Not set' }}</span>
                    </button>
                    <button class="nav-item" >
                        <i class="fas fa-info-circle"></i>
                        <span>Marital Status : {{ $fetchedData->marital_status ?: 'Not set' }}</span>
                    </button>
                   
                </nav>
                
                <!-- Back Button in Sidebar -->
                <div class="sidebar-actions">
                    <button type="button" class="nav-item summary-nav back-btn" onclick="goBackWithRefresh()">
                        <i class="fas fa-arrow-left"></i>
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
                    searchPartnerRoute: '{{ route("clients.searchPartner") }}',
                    csrfToken: '{{ csrf_token() }}'
                };
                
                // Current client ID for excluding from search results
                window.currentClientId = '{{ $fetchedData->id }}';
                window.currentClientType = @json($fetchedData->type);
                window.latestClientMatterRef = @json($latestMatterRefNo);

               function showTab(tabId){
    // Hide every tab pane in this layout (all are under .main-content-area; avoids stray visible panes / double-stacking)
    $(".main-content-area .tab-pane").hide().removeClass("in active");
    var $pane = $("#" + tabId);
    if ($pane.length) {
        $pane.css("display", "block").show().addClass("in active");
    }
    $(".client-edit-top-pills li").removeClass("active");
    $(".client-edit-top-pills a[href='#" + tabId + "']").closest("li").addClass("active");
               }

            </script>

            <!-- Main Content Area -->
            <div class="main-content-area">

            <ul class="nav nav-pills client-edit-top-pills">
    <li class="active"><a href="#home" onclick="showTab('home'); return false;"><i class="fas fa-user"></i> Client Info</a></li>
    <li><a href="#menu2" onclick="showTab('menu2'); return false;"><i class="fas fa-briefcase"></i> Matter Details</a></li>
    <li><a href="#menu4" onclick="showTab('menu4'); return false;"><i class="fas fa-gavel"></i> Court Dates &amp; Hearings</a></li>
    </ul>
  
  <div class="tab-content">
  <form  id="editClientForm" action="{{ route('clients.update') }}" method="POST">
                    @csrf
                    <input type="hidden" name="id" value="{{ $fetchedData->id }}">
                    <input type="hidden" name="type" value="{{ $fetchedData->type }}">

    <div id="home" class="tab-pane fade in active">
      <h3><i class="fas fa-user"></i> Client Info (Personal)</h3>
     

                <!-- Personal Section -->
                <section id="personalSection" class="content-section">
                    <section class="form-section">
                        <div class="section-header">
                            <h3><i class="fas fa-user-circle"></i> Basic Information</h3>
                            <div class="section-actions">
                                <button type="button" class="edit-section-btn" onclick="toggleEditMode('basicInfo')">
                                    <i class="fas fa-pen"></i>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Summary View -->
                        <div id="basicInfoSummary" class="summary-view">
                            <div class="summary-grid">
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
                                                <i class="far fa-circle" style="color: #6c757d; margin-right: 4px;"></i>
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
                    <section class="form-section">
                        <div class="section-header">
                            <h3><i class="fas fa-mobile-alt"></i> Phone Numbers</h3>
                            <div class="section-actions">
                                <button type="button" class="edit-section-btn" onclick="toggleEditMode('phoneNumbers')">
                                    <i class="fas fa-pen"></i>
                                </button>
                                <button type="button" class="add-section-btn" onclick="addPhoneNumber()" title="Add Phone Number">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Summary View -->
                        <div id="phoneNumbersSummary" class="summary-view">
                            @if($clientContacts->count() > 0)
                                <div class="summary-grid">
                                    @foreach($clientContacts as $index => $contact)
                                        <div class="summary-item">
                                            <span class="summary-label">{{ $contact->contact_type }}:</span>
                                            <span class="summary-value">{{ $contact->country_code }}{{ $contact->phone }}</span>
                                            <!-- Verification Button/Badge -->
                                            @if($contact->canVerify())
                                                @if($contact->is_verified)
                                                    <span class="verified-badge" title="Verified on {{ $contact->verified_at ? $contact->verified_at->format('M j, Y g:i A') : 'Unknown' }}">
                                                        <i class="fas fa-check-circle"></i> Verified
                                                    </span>
                                                @else
                                                    <button type="button" class="btn-verify-phone" onclick="sendOTP({{ $contact->id ?? 'null' }}, '{{ $contact->phone }}', '{{ $contact->country_code }}')" data-contact-id="{{ $contact->id ?? '' }}">
                                                        <i class="fas fa-lock"></i> Verify
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

                            <button type="button" class="add-item-btn" onclick="addPhoneNumber()"><i class="fas fa-plus-circle"></i> Add Phone Number</button>
                            <div class="edit-actions">
                                <button type="button" class="btn btn-primary" onclick="savePhoneNumbers()">Save</button>
                                <button type="button" class="btn btn-secondary" onclick="cancelEdit('phoneNumbers')">Cancel</button>
                            </div>
                        </div>
                    </section>

                    <!-- Email Addresses -->
                    <section class="form-section">
                        <div class="section-header">
                            <h3><i class="fas fa-envelope"></i> Email Addresses</h3>
                            <div class="section-actions">
                                <button type="button" class="edit-section-btn" onclick="toggleEditMode('emailAddresses')">
                                    <i class="fas fa-pen"></i>
                                </button>
                                <button type="button" class="add-section-btn" onclick="addEmailAddress()" title="Add Email Address">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Summary View -->
                        <div id="emailAddressesSummary" class="summary-view">
                            @if($emails->count() > 0)
                                <div class="summary-grid">
                                    @foreach($emails as $index => $email)
                                        <div class="summary-item">
                                            <span class="summary-label">{{ $email->email_type }}:</span>
                                            <span class="summary-value">{{ $email->email }}</span>
                                            <!-- Verification Button/Badge -->
                                            @if($email->is_verified)
                                                <span class="verified-badge" title="Verified on {{ $email->verified_at ? $email->verified_at->format('M j, Y g:i A') : 'Unknown' }}">
                                                    <i class="fas fa-check-circle"></i> Verified
                                                </span>
                                            @else
                                                <button type="button" class="btn-verify-email" onclick="sendEmailVerification({{ $email->id }}, '{{ $email->email }}')" data-email-id="{{ $email->id }}">
                                                    <i class="fas fa-lock"></i> Verify
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

                            <button type="button" class="add-item-btn" onclick="addEmailAddress()"><i class="fas fa-plus-circle"></i> Add Email Address</button>
                            <div class="edit-actions">
                                <button type="button" class="btn btn-primary" onclick="saveEmailAddresses()">Save</button>
                                <button type="button" class="btn btn-secondary" onclick="cancelEdit('emailAddresses')">Cancel</button>
                            </div>
                        </div>
                    </section>
                </section>

                {{-- Lead Source & Assignment Section --}}
                <section class="content-section" style="margin-bottom:1.25rem;">
                    <section class="form-section">
                        <div class="section-header">
                            <h3><i class="fas fa-funnel-dollar"></i> Lead Source &amp; Assignment</h3>
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
    <div id="menu1" class="tab-pane fade">
      <h3>Additional Information</h3>

                <section class="content-section" style="margin-bottom: 1.25rem;">
                    <section class="form-section">
                        <div class="section-header">
                            <h3><i class="fas fa-user-friends"></i> Refer by</h3>
                        </div>
                        <p class="text-muted" style="margin-top: 0;">Who referred this {{ $__crmEditLeadType ? 'lead' : 'client' }} (optional).</p>
                        <div class="form-group">
                            <label for="client_refer_by">Refer by</label>
                            <input type="text" class="form-control" id="client_refer_by" name="refer_by" value="{{ old('refer_by', $fetchedData->refer_by ?? '') }}" maxlength="500" placeholder="e.g. name, staff member, campaign">
                        </div>
                        <button type="button" class="btn btn-primary" onclick="saveReferByInfo()"><i class="fas fa-save"></i> Save</button>
                        <span id="referBySaveMsg" class="text-muted small" style="margin-left: 10px;" role="status"></span>
                    </section>
                </section>

                <!-- Visa, Passport & Citizenship Section -->
                <section id="visaPassportSection" class="content-section" style="display:none">
                    <section class="form-section">
                        <div class="section-header">
                            <h3><i class="fas fa-id-card"></i> Passport Information</h3>
                            <div class="section-actions">
                                <button type="button" class="edit-section-btn" onclick="toggleEditMode('passportInfo')">
                                    <i class="fas fa-pen"></i>
                                </button>
                                <button type="button" class="add-section-btn" onclick="addPassportDetail()" title="Add Passport">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Summary View -->
                        <div id="passportInfoSummary" class="summary-view">
                            @if($clientPassports->count() > 0)
                                <div style="margin-top: 15px;">
                                    @foreach($clientPassports as $index => $passport)
                                        <div class="passport-entry-compact" style="margin-bottom: 12px; padding: 12px; background: #f8f9fa; border-radius: 6px; border-left: 3px solid #007bff;">
                                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; align-items: center;">
                                                <div class="summary-item-inline">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">COUNTRY:</span>
                                                    <span class="summary-value" style="color: #212529; font-weight: 500;">{{ $passport->passport_country ?: 'Not set' }}</span>
                                                </div>
                                                <div class="summary-item-inline">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">PASSPORT #:</span>
                                                    <span class="summary-value" style="color: #212529; font-weight: 500;">{{ $passport->passport ?: 'Not set' }}</span>
                                                </div>
                                                <div class="summary-item-inline">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">ISSUE DATE:</span>
                                                    <span class="summary-value" style="color: #212529;">{{ $passport->passport_issue_date ? date('d/m/Y', strtotime($passport->passport_issue_date)) : 'Not set' }}</span>
                                                </div>
                                                <div class="summary-item-inline">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">EXPIRY DATE:</span>
                                                    <span class="summary-value" style="color: #212529;">{{ $passport->passport_expiry_date ? date('d/m/Y', strtotime($passport->passport_expiry_date)) : 'Not set' }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="empty-state" style="margin-top: 15px;">
                                    <p>No passport details added yet.</p>
                                </div>
                            @endif
                        </div>

                        <!-- Edit View -->
                        <div id="passportInfoEdit" class="edit-view" style="display: none;">
                            <!-- Passport Details -->
                            <div id="passportDetailsContainer">
                                @foreach($clientPassports as $index => $passport)
                                    <x-client-edit.passport-field 
                                        :index="$index" 
                                        :passport="$passport" 
                                        :countries="$countries" 
                                    />
                                @endforeach
                            </div>

                            <button type="button" class="add-item-btn" onclick="addPassportDetail()"><i class="fas fa-plus-circle"></i> Add Passport</button>
                            <div class="edit-actions">
                                <button type="button" class="btn btn-primary" onclick="savePassportInfo()">Save</button>
                                <button type="button" class="btn btn-secondary" onclick="cancelEdit('passportInfo')">Cancel</button>
                            </div>
                        </div>
                    </section>

                    <!-- Visa Information -->
                    <section class="form-section">
                        <div class="section-header">
                            <h3><i class="fas fa-plane-departure"></i> Visa Information</h3>
                            <div class="section-actions">
                                <button type="button" class="edit-section-btn" onclick="toggleEditMode('visaInfo')">
                                    <i class="fas fa-pen"></i>
                                </button>
                                <button type="button" class="add-section-btn" onclick="addVisaDetail()" title="Add Visa Detail">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Summary View -->
                        <div id="visaInfoSummary" class="summary-view">
                            <div class="summary-grid">
                                <div class="summary-item">
                                    <span class="summary-label">Visa Expiry Verified:</span>
                                    <span class="summary-value">{{ $fetchedData->visa_expiry_verified_at ? 'Yes' : 'No' }}</span>
                                </div>
                            </div>
                            @if($visaCountries->count() > 0)
                                <div style="margin-top: 15px;">
                                    @foreach($visaCountries as $index => $visa)
                                        <div class="visa-entry-compact" style="margin-bottom: 12px; padding: 12px; background: #f8f9fa; border-radius: 6px; border-left: 3px solid #28a745;">
                                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; align-items: center;">
                                                <div class="summary-item-inline">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">VISA TYPE:</span>
                                                    <span class="summary-value" style="color: #212529; font-weight: 500;">
                                                        {{ $visa->matter ? $visa->matter->title . ' (' . $visa->matter->nick_name . ')' : 'Not set' }}
                                                    </span>
                                                </div>
                                                <div class="summary-item-inline">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">EXPIRY DATE:</span>
                                                    <span class="summary-value" style="color: #212529;">{{ $visa->visa_expiry_date ? date('d/m/Y', strtotime($visa->visa_expiry_date)) : 'Not set' }}</span>
                                                </div>
                                                <div class="summary-item-inline">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">GRANT DATE:</span>
                                                    <span class="summary-value" style="color: #212529;">{{ $visa->visa_grant_date ? date('d/m/Y', strtotime($visa->visa_grant_date)) : 'Not set' }}</span>
                                                </div>
                                                <div class="summary-item-inline">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">DESCRIPTION:</span>
                                                    <span class="summary-value" style="color: #212529;">{{ $visa->visa_description ?: 'Not set' }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="empty-state" style="margin-top: 15px;">
                                    <p>No visa details added yet.</p>
                                </div>
                            @endif
                        </div>

                        <!-- Edit View -->
                        <div id="visaInfoEdit" class="edit-view" style="display: none;">
                            <!-- Visa Details -->
                            <div id="visaDetailsSection">
                                <div id="visaDetailsContainer">
                                    @foreach($visaCountries as $index => $visa)
                                        <x-client-edit.visa-field 
                                            :index="$index" 
                                            :visa="$visa" 
                                            :visaTypes="$visaTypes" 
                                        />
                                    @endforeach
                                </div>

                                <button type="button" class="add-item-btn" onclick="addVisaDetail()"><i class="fas fa-plus-circle"></i> Add Visa Detail</button>
                            </div>

                            <!-- Visa Expiry Verified -->
                            <div id="visaExpiryVerifiedContainer" class="form-group" style="display: flex; align-items: center; gap: 10px; margin-top: 15px;">
                                <label>Visa Expiry Verified?</label>
                                <label class="switch" style="margin: 0;">
                                    <input type="checkbox" name="visa_expiry_verified" value="1" {{ $fetchedData->visa_expiry_verified_at ? 'checked' : '' }}>
                                    <span class="slider"></span>
                                </label>
                            </div>
                            
                            <div class="edit-actions">
                                <button type="button" class="btn btn-primary" onclick="saveVisaInfo()">Save</button>
                                <button type="button" class="btn btn-secondary" onclick="cancelEdit('visaInfo')">Cancel</button>
                            </div>
                        </div>
                    </section>
                </section>

                <!-- Address & Travel Section -->
                <section id="addressTravelSection" class="content-section">
                    <x-client-edit.address-section 
                        :clientAddresses="$clientAddresses"
                        :searchRoute="route('clients.searchAddressFull')"
                        :detailsRoute="route('clients.getPlaceDetails')"
                        :csrfToken="csrf_token()"
                    />
                    
                    <!-- Travel Information Section -->
                    <section class="form-section" style="display:none">
                        <div class="section-header">
                            <h3><i class="fas fa-plane-departure"></i> Travel Information</h3>
                            <div class="section-actions">
                                <button type="button" class="edit-section-btn" onclick="toggleEditMode('travelInfo')">
                                    <i class="fas fa-pen"></i>
                                </button>
                                <button type="button" class="add-section-btn" onclick="addTravelDetail()" title="Add Travel Detail">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Summary View -->
                        <div id="travelInfoSummary" class="summary-view">
                            @if($clientTravels->count() > 0)
                                <div>
                                    @foreach($clientTravels as $index => $travel)
                                        <div class="address-entry-compact">
                                            <div class="address-compact-grid">
                                                <div class="summary-item-inline">
                                                    <span class="summary-label">COUNTRY VISITED:</span>
                                                    <span class="summary-value">{{ $travel->country_visited ?: 'Not set' }}</span>
                                                </div>
                                                @if($travel->arrival_date)
                                                <div class="summary-item-inline">
                                                    <span class="summary-label">ARRIVAL DATE:</span>
                                                    <span class="summary-value">{{ date('d/m/Y', strtotime($travel->arrival_date)) }}</span>
                                                </div>
                                                @endif
                                                @if($travel->departure_date)
                                                <div class="summary-item-inline">
                                                    <span class="summary-label">DEPARTURE DATE:</span>
                                                    <span class="summary-value">{{ date('d/m/Y', strtotime($travel->departure_date)) }}</span>
                                                </div>
                                                @endif
                                                @if($travel->travel_purpose)
                                                <div class="summary-item-inline">
                                                    <span class="summary-label">TRAVEL PURPOSE:</span>
                                                    <span class="summary-value">{{ $travel->travel_purpose }}</span>
                                                </div>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="empty-state">
                                    <p>No travel details added yet.</p>
                                </div>
                            @endif
                        </div>

                        <!-- Edit View -->
                        <div id="travelInfoEdit" class="edit-view" style="display: none;">
                            <div id="travelDetailsContainer">
                                @foreach($clientTravels as $index => $travel)
                                    <x-client-edit.travel-field 
                                        :index="$index" 
                                        :travel="$travel" 
                                        :countries="$countries->pluck('name')->toArray()"
                                    />
                                @endforeach
                            </div>

                            <button type="button" class="add-item-btn" onclick="addTravelDetail()"><i class="fas fa-plus-circle"></i> Add Travel Detail</button>
                            <div class="edit-actions">
                                <button type="button" class="btn btn-primary" onclick="saveTravelInfo()">Save</button>
                                <button type="button" class="btn btn-secondary" onclick="cancelEdit('travelInfo')">Cancel</button>
                            </div>
                        </div>
                    </section>
                </section>

                <!-- Skills & Education Section -->
                <section id="skillsEducationSection" class="content-section" style="display:none">
                    <section class="form-section">
                        <div class="section-header">
                            <h3><i class="fas fa-graduation-cap"></i> Educational Qualifications</h3>
                            <div class="section-actions">
                                <button type="button" class="edit-section-btn" onclick="toggleEditMode('qualificationsInfo')">
                                    <i class="fas fa-pen"></i>
                                </button>
                                <button type="button" class="add-section-btn" onclick="addQualification()" title="Add Qualification">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Summary View -->
                        <div id="qualificationsInfoSummary" class="summary-view">
                            @if($qualifications->count() > 0)
                                <div style="margin-top: 15px;">
                                    @foreach($qualifications as $index => $qualification)
                                        <div class="passport-entry-compact" style="margin-bottom: 12px; padding: 12px; background: #f8f9fa; border-radius: 6px; border-left: 3px solid #6f42c1;">
                                            <div style="display: grid; grid-template-columns: 180px 1fr auto auto auto auto auto auto; gap: 15px; align-items: start;">
                                                @if($qualification->level)
                                                <div class="summary-item-inline" style="grid-column: 1;">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">LEVEL:</span>
                                                    <span class="summary-value" style="color: #212529; font-weight: 500;">{{ $qualification->level }}</span>
                                                </div>
                                                @endif
                                                @if($qualification->name)
                                                <div class="summary-item-inline" style="grid-column: 2;">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">NAME:</span>
                                                    <span class="summary-value" style="color: #212529; font-weight: 500;">{{ $qualification->name }}</span>
                                                </div>
                                                @endif
                                                @if($qualification->qual_college_name)
                                                <div class="summary-item-inline" style="grid-column: 3;">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">INSTITUTION:</span>
                                                    <span class="summary-value" style="color: #212529;">{{ $qualification->qual_college_name }}</span>
                                                </div>
                                                @endif
                                                @if($qualification->qual_campus)
                                                <div class="summary-item-inline" style="grid-column: 4;">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">CAMPUS/ADDRESS:</span>
                                                    <span class="summary-value" style="color: #212529;">{{ $qualification->qual_campus }}</span>
                                                </div>
                                                @endif
                                                @if($qualification->country)
                                                <div class="summary-item-inline" style="grid-column: 5;">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">COUNTRY:</span>
                                                    <span class="summary-value" style="color: #212529;">{{ $qualification->country }}</span>
                                                </div>
                                                @endif
                                                @if($qualification->qual_state)
                                                <div class="summary-item-inline" style="grid-column: 6;">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">STATUS:</span>
                                                    <span class="summary-value" style="color: #212529;">{{ $qualification->qual_state }}</span>
                                                </div>
                                                @endif
                                                @if($qualification->start_date)
                                                <div class="summary-item-inline" style="grid-column: 7;">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">START DATE:</span>
                                                    <span class="summary-value" style="color: #212529;">{{ date('d/m/Y', strtotime($qualification->start_date)) }}</span>
                                                </div>
                                                @endif
                                                @if($qualification->finish_date)
                                                <div class="summary-item-inline" style="grid-column: 8;">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">FINISH DATE:</span>
                                                    <span class="summary-value" style="color: #212529;">{{ date('d/m/Y', strtotime($qualification->finish_date)) }}</span>
                                                </div>
                                                @endif
                                                @if($qualification->relevant_qualification)
                                                <div class="summary-item-inline" style="grid-column: 1 / -1;">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">RELEVANT:</span>
                                                    <span class="summary-value" style="color: #28a745; font-weight: 500;">
                                                        <i class="fas fa-check-circle"></i> Yes
                                                    </span>
                                                </div>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="empty-state">
                                    <p>No qualifications added yet.</p>
                                </div>
                            @endif
                        </div>

                        <!-- Edit View -->
                        <div id="qualificationsInfoEdit" class="edit-view" style="display: none;">
                            <div id="qualificationsContainer">
                                @foreach($qualifications as $index => $qualification)
                                    <x-client-edit.qualification-field 
                                        :index="$index" 
                                        :qualification="$qualification" 
                                        :countries="$countries"
                                    />
                                @endforeach
                            </div>

                            <button type="button" class="add-item-btn" onclick="addQualification()"><i class="fas fa-plus-circle"></i> Add Qualification</button>
                            <div class="edit-actions">
                                <button type="button" class="btn btn-primary" onclick="saveQualificationsInfo()">
                                    <i class="fas fa-save"></i> Save
                                </button>
                                <button type="button" class="btn btn-secondary" onclick="cancelEdit('qualificationsInfo')">
                                    <i class="fas fa-times"></i> Cancel
                                </button>
                            </div>
                        </div>
                    </section>

                    <!-- Work Experience Section -->
                    <section class="form-section">
                        <div class="section-header">
                            <h3><i class="fas fa-briefcase"></i> Work Experience</h3>
                            <div class="section-actions">
                                <button type="button" class="edit-section-btn" onclick="toggleEditMode('experienceInfo')">
                                    <i class="fas fa-pen"></i>
                                </button>
                                <button type="button" class="add-section-btn" onclick="addExperience()" title="Add Experience">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Summary View -->
                        <div id="experienceInfoSummary" class="summary-view">
                            @if($experiences->count() > 0)
                                <div style="margin-top: 15px;">
                                    @foreach($experiences as $index => $experience)
                                        <div class="experience-entry-compact" style="margin-bottom: 12px; padding: 12px; background: #f8f9fa; border-radius: 6px; border-left: 3px solid #007bff;">
                                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; align-items: center;">
                                                <div class="summary-item-inline">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">JOB TITLE:</span>
                                                    <span class="summary-value" style="color: #212529; font-weight: 500;">{{ $experience->job_title ?: 'Not set' }}</span>
                                                </div>
                                                <div class="summary-item-inline">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">JOB CODE:</span>
                                                    <span class="summary-value" style="color: #212529; font-weight: 500;">{{ $experience->job_code ?: 'Not set' }}</span>
                                                </div>
                                                <div class="summary-item-inline">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">EMPLOYER NAME:</span>
                                                    <span class="summary-value" style="color: #212529; font-weight: 500;">{{ $experience->job_emp_name ?: 'Not set' }}</span>
                                                </div>
                                                <div class="summary-item-inline">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">COUNTRY:</span>
                                                    <span class="summary-value" style="color: #212529;">{{ $experience->job_country ?: 'Not set' }}</span>
                                                </div>
                                                <div class="summary-item-inline">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">ADDRESS:</span>
                                                    <span class="summary-value" style="color: #212529;">{{ $experience->job_state ?: 'Not set' }}</span>
                                                </div>
                                                <div class="summary-item-inline">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">JOB TYPE:</span>
                                                    <span class="summary-value" style="color: #212529;">{{ $experience->job_type ?: 'Not set' }}</span>
                                                </div>
                                                <div class="summary-item-inline">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">START DATE:</span>
                                                    <span class="summary-value" style="color: #212529;">{{ $experience->job_start_date ? date('d/m/Y', strtotime($experience->job_start_date)) : 'Not set' }}</span>
                                                </div>
                                                <div class="summary-item-inline">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">FINISH DATE:</span>
                                                    <span class="summary-value" style="color: #212529;">{{ $experience->job_finish_date ? date('d/m/Y', strtotime($experience->job_finish_date)) : 'Not set' }}</span>
                                                </div>
                                                <div class="summary-item-inline">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">RELEVANT:</span>
                                                    <span class="summary-value" style="color: #212529;">{{ $experience->relevant_experience ? 'Yes' : 'No' }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="empty-state">
                                    <p>No work experience added yet.</p>
                                </div>
                            @endif
                        </div>

                        <!-- Edit View -->
                        <div id="experienceInfoEdit" class="edit-view" style="display: none;">
                            <div id="experienceContainer">
                                @foreach($experiences as $index => $experience)
                                    <x-client-edit.work-experience-field 
                                        :index="$index" 
                                        :experience="$experience" 
                                        :countries="$countries->pluck('name')->toArray()"
                                    />
                                @endforeach
                            </div>

                            <button type="button" class="add-item-btn" onclick="addExperience()"><i class="fas fa-plus-circle"></i> Add Experience</button>
                            <div class="edit-actions">
                                <button type="button" class="btn btn-primary" onclick="saveExperienceInfo()">Save</button>
                                <button type="button" class="btn btn-secondary" onclick="cancelEdit('experienceInfo')">Cancel</button>
                            </div>
                        </div>
                    </section>

                    <!-- Occupation & Skills Section -->
                    <section class="form-section">
                        <div class="section-header">
                            <h3><i class="fas fa-cogs"></i> Occupation & Skills</h3>
                            <div class="section-actions">
                                <button type="button" class="edit-section-btn" onclick="toggleEditMode('occupationInfo')">
                                    <i class="fas fa-pen"></i>
                                </button>
                                <button type="button" class="add-section-btn" onclick="addOccupation()" title="Add Occupation">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Summary View -->
                        <div id="occupationInfoSummary" class="summary-view">
                            @if($clientOccupations->count() > 0)
                                <div style="margin-top: 15px;">
                                    @foreach($clientOccupations as $index => $occupation)
                                        <div class="occupation-entry-compact" style="margin-bottom: 12px; padding: 12px; background: #f8f9fa; border-radius: 6px; border-left: 3px solid #28a745;">
                                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; align-items: center;">
                                                <div class="summary-item-inline">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">SKILL ASSESSMENT:</span>
                                                    <span class="summary-value" style="color: #212529; font-weight: 500;">{{ $occupation->skill_assessment ?: 'Not set' }}</span>
                                                </div>
                                                <div class="summary-item-inline">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">NOMINATED OCCUPATION:</span>
                                                    <span class="summary-value" style="color: #212529; font-weight: 500;">{{ $occupation->nomi_occupation ?: 'Not set' }}</span>
                                                </div>
                                                <div class="summary-item-inline">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">OCCUPATION CODE:</span>
                                                    <span class="summary-value" style="color: #212529; font-weight: 500;">{{ $occupation->occupation_code ?: 'Not set' }}</span>
                                                </div>
                                                <div class="summary-item-inline">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">ASSESSING AUTHORITY:</span>
                                                    <span class="summary-value" style="color: #212529;">{{ $occupation->list ?: 'Not set' }}</span>
                                                </div>
                                                <div class="summary-item-inline">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">VISA SUBCLASS:</span>
                                                    <span class="summary-value" style="color: #212529;">{{ $occupation->visa_subclass ?: 'Not set' }}</span>
                                                </div>
                                                <div class="summary-item-inline">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">ASSESSMENT DATE:</span>
                                                    <span class="summary-value" style="color: #212529;">{{ $occupation->dates ? date('d/m/Y', strtotime($occupation->dates)) : 'Not set' }}</span>
                                                </div>
                                                <div class="summary-item-inline">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">EXPIRY DATE:</span>
                                                    <span class="summary-value" style="color: #212529;">{{ $occupation->expiry_dates ? date('d/m/Y', strtotime($occupation->expiry_dates)) : 'Not set' }}</span>
                                                </div>
                                                <div class="summary-item-inline">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">REFERENCE NO:</span>
                                                    <span class="summary-value" style="color: #212529;">{{ $occupation->occ_reference_no ?: 'Not set' }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="no-data-message">
                                    <p>No occupation information available.</p>
                                </div>
                            @endif
                        </div>

                        <!-- Edit View -->
                        <div id="occupationInfoEdit" class="edit-view" style="display: none;">
                            <div id="occupationContainer">
                                @foreach($clientOccupations as $index => $occupation)
                                    <x-client-edit.occupation-field 
                                        :index="$index" 
                                        :occupation="$occupation" 
                                    />
                                @endforeach
                            </div>

                            <button type="button" class="add-item-btn" onclick="addOccupation()"><i class="fas fa-plus-circle"></i> Add Occupation</button>
                            <div class="edit-actions">
                                <button type="button" class="btn btn-primary" onclick="saveOccupationInfo()">Save</button>
                                <button type="button" class="btn btn-secondary" onclick="cancelEdit('occupationInfo')">Cancel</button>
                            </div>
                        </div>
                    </section>

                    <!-- English Test Scores Section -->
                    <section class="form-section">
                        <div class="section-header">
                            <h3><i class="fas fa-language"></i> English Test Scores</h3>
                            <div class="section-actions">
                                <button type="button" class="edit-section-btn" onclick="toggleEditMode('testScoreInfo')">
                                    <i class="fas fa-pen"></i>
                                </button>
                                <button type="button" class="add-section-btn" onclick="addTestScore()" title="Add Test Score">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Summary View -->
                        <div id="testScoreInfoSummary" class="summary-view">
                            @if($testScores->count() > 0)
                                <div style="margin-top: 15px;">
                                    @foreach($testScores as $index => $testScore)
                                        <div class="test-score-entry-compact" style="margin-bottom: 12px; padding: 12px; background: #f8f9fa; border-radius: 6px; border-left: 3px solid #007bff;">
                                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; align-items: center;">
                                                <div class="summary-item-inline">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">TEST TYPE:</span>
                                                    <span class="summary-value" style="color: #212529; font-weight: 500;">{{ $testScore->test_type ?: 'Not set' }}</span>
                                                </div>
                                                <div class="summary-item-inline">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">LISTENING:</span>
                                                    <span class="summary-value" style="color: #212529; font-weight: 500;">{{ $testScore->listening ?: 'Not set' }}</span>
                                                </div>
                                                <div class="summary-item-inline">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">READING:</span>
                                                    <span class="summary-value" style="color: #212529; font-weight: 500;">{{ $testScore->reading ?: 'Not set' }}</span>
                                                </div>
                                                <div class="summary-item-inline">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">WRITING:</span>
                                                    <span class="summary-value" style="color: #212529; font-weight: 500;">{{ $testScore->writing ?: 'Not set' }}</span>
                                                </div>
                                                <div class="summary-item-inline">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">SPEAKING:</span>
                                                    <span class="summary-value" style="color: #212529; font-weight: 500;">{{ $testScore->speaking ?: 'Not set' }}</span>
                                                </div>
                                                <div class="summary-item-inline">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">OVERALL:</span>
                                                    <span class="summary-value" style="color: #212529; font-weight: 500;">{{ $testScore->overall_score ?: 'Not set' }}</span>
                                                </div>
                                                <div class="summary-item-inline">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">TEST DATE:</span>
                                                    <span class="summary-value" style="color: #212529;">{{ $testScore->test_date ? date('d/m/Y', strtotime($testScore->test_date)) : 'Not set' }}</span>
                                                </div>
                                                <div class="summary-item-inline">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">REFERENCE NO:</span>
                                                    <span class="summary-value" style="color: #212529;">{{ $testScore->test_reference_no ?: 'Not set' }}</span>
                                                </div>
                                                <div class="summary-item-inline">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">PROFICIENCY LEVEL:</span>
                                                    <span id="proficiency-level-{{ $index }}" class="proficiency-level-display" style="font-weight: 700; font-size: 0.9em; padding: 4px 8px; border-radius: 4px; display: inline-block;">
                                                        <i class="fas fa-spinner fa-spin"></i> Calculating...
                                                    </span>
                                                </div>
                                            </div>
                                            
                                            <!-- Hidden data attributes for JavaScript calculation -->
                                            <div class="english-level-calculation-box" 
                                                 data-test-type="{{ $testScore->test_type }}" 
                                                 data-listening="{{ $testScore->listening }}" 
                                                 data-reading="{{ $testScore->reading }}" 
                                                 data-writing="{{ $testScore->writing }}" 
                                                 data-speaking="{{ $testScore->speaking }}" 
                                                 data-overall="{{ $testScore->overall_score }}" 
                                                 data-test-date="{{ $testScore->test_date ? date('d/m/Y', strtotime($testScore->test_date)) : '' }}"
                                                 data-proficiency-level="{{ $testScore->proficiency_level ?? '' }}"
                                                 data-proficiency-points="{{ $testScore->proficiency_points ?? '' }}"
                                                 style="display: none;">
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="empty-state" style="margin-top: 15px;">
                                    <p>No test score information available.</p>
                                </div>
                            @endif
                        </div>

                        <!-- Edit View -->
                        <div id="testScoreInfoEdit" class="edit-view" style="display: none;">
                            <div id="testScoresContainer">
                                @foreach($testScores as $index => $testScore)
                                    <x-client-edit.test-score-field 
                                        :index="$index" 
                                        :testScore="$testScore" 
                                    />
                                @endforeach
                            </div>

                            <button type="button" class="add-item-btn" onclick="addTestScore()"><i class="fas fa-plus-circle"></i> Add Test Score</button>
                            <div class="edit-actions">
                                <button type="button" class="btn btn-primary" onclick="saveTestScoreInfo()">Save</button>
                                <button type="button" class="btn btn-secondary" onclick="cancelEdit('testScoreInfo')">Cancel</button>
                            </div>
                        </div>
                    </section>
                </section>

                <!-- Other Information Section -->
                <section id="otherInformationSection" class="content-section" style="display:none">
                    <section class="form-section">
                        <div class="section-header">
                            <h3><i class="fas fa-info-circle"></i> Additional Information</h3>
                            <div class="section-actions">
                                <button type="button" class="edit-section-btn" onclick="toggleEditMode('additionalInfo')">
                                    <i class="fas fa-pen"></i>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Summary View -->
                        <div id="additionalInfoSummary" class="summary-view">
                            <div class="summary-grid">
                                <div class="summary-item">
                                    <span class="summary-label">NAATI/CCL Test:</span>
                                    <span class="summary-value">{{ $fetchedData->naati_test ? 'Yes' : 'No' }}</span>
                                </div>
                                <div class="summary-item">
                                    <span class="summary-label">NAATI/CCL Date:</span>
                                    <span class="summary-value">{{ $fetchedData->naati_date ? date('d/m/Y', strtotime($fetchedData->naati_date)) : 'Not set' }}</span>
                                </div>
                                <div class="summary-item">
                                    <span class="summary-label">Professional Year (PY):</span>
                                    <span class="summary-value">{{ $fetchedData->py_test ? 'Yes' : 'No' }}</span>
                                </div>
                                <div class="summary-item">
                                    <span class="summary-label">PY Completion Date:</span>
                                    <span class="summary-value">{{ $fetchedData->py_date ? date('d/m/Y', strtotime($fetchedData->py_date)) : 'Not set' }}</span>
                                </div>
                                <div class="summary-item">
                                    <span class="summary-label">Australian Study Requirement:</span>
                                    <span class="summary-value">{{ $fetchedData->australian_study ? 'Yes' : 'No' }}</span>
                                </div>
                                <div class="summary-item">
                                    <span class="summary-label">Australian Study Completion Date:</span>
                                    <span class="summary-value">{{ $fetchedData->australian_study_date ? date('d/m/Y', strtotime($fetchedData->australian_study_date)) : 'Not set' }}</span>
                                </div>
                                <div class="summary-item">
                                    <span class="summary-label">Specialist Education (STEM):</span>
                                    <span class="summary-value">{{ $fetchedData->specialist_education ? 'Yes' : 'No' }}</span>
                                </div>
                                <div class="summary-item">
                                    <span class="summary-label">Specialist Education Completion Date:</span>
                                    <span class="summary-value">{{ $fetchedData->specialist_education_date ? date('d/m/Y', strtotime($fetchedData->specialist_education_date)) : 'Not set' }}</span>
                                </div>
                                <div class="summary-item">
                                    <span class="summary-label">Regional Study:</span>
                                    <span class="summary-value">{{ $fetchedData->regional_study ? 'Yes' : 'No' }}</span>
                                </div>
                                <div class="summary-item">
                                    <span class="summary-label">Regional Study Completion Date:</span>
                                    <span class="summary-value">{{ $fetchedData->regional_study_date ? date('d/m/Y', strtotime($fetchedData->regional_study_date)) : 'Not set' }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Edit View -->
                        <div id="additionalInfoEdit" class="edit-view" style="display: none;">
                            <div class="content-grid">
                                <div class="form-group">
                                    <label for="naatiTest">NAATI/CCL Test <small class="text-muted">(5 pts)</small></label>
                                    <select id="naatiTest" name="naati_test">
                                        <option value="0" {{ !$fetchedData->naati_test ? 'selected' : '' }}>No</option>
                                        <option value="1" {{ $fetchedData->naati_test ? 'selected' : '' }}>Yes</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="naatiDate">NAATI/CCL Date</label>
                                    <input type="text" id="naatiDate" name="naati_date" value="{{ $fetchedData->naati_date ? date('d/m/Y', strtotime($fetchedData->naati_date)) : '' }}" placeholder="dd/mm/yyyy" class="date-picker">
                                </div>
                                <div class="form-group">
                                    <label for="pyTest">Professional Year (PY) <small class="text-muted">(5 pts)</small></label>
                                    <select id="pyTest" name="py_test">
                                        <option value="0" {{ !$fetchedData->py_test ? 'selected' : '' }}>No</option>
                                        <option value="1" {{ $fetchedData->py_test ? 'selected' : '' }}>Yes</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="pyDate">PY Completion Date</label>
                                    <input type="text" id="pyDate" name="py_date" value="{{ $fetchedData->py_date ? date('d/m/Y', strtotime($fetchedData->py_date)) : '' }}" placeholder="dd/mm/yyyy" class="date-picker">
                                </div>
                                <div class="form-group">
                                    <label for="australianStudy">Australian Study Requirement <small class="text-muted">(5 pts - 2+ years in Australia)</small></label>
                                    <select id="australianStudy" name="australian_study">
                                        <option value="0" {{ !$fetchedData->australian_study ? 'selected' : '' }}>No</option>
                                        <option value="1" {{ $fetchedData->australian_study ? 'selected' : '' }}>Yes</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="australianStudyDate">Australian Study Completion Date</label>
                                    <input type="text" id="australianStudyDate" name="australian_study_date" value="{{ $fetchedData->australian_study_date ? date('d/m/Y', strtotime($fetchedData->australian_study_date)) : '' }}" placeholder="dd/mm/yyyy" class="date-picker">
                                </div>
                                <div class="form-group">
                                    <label for="specialistEducation">Specialist Education (STEM) <small class="text-muted">(10 pts - Masters/PhD by research)</small></label>
                                    <select id="specialistEducation" name="specialist_education">
                                        <option value="0" {{ !$fetchedData->specialist_education ? 'selected' : '' }}>No</option>
                                        <option value="1" {{ $fetchedData->specialist_education ? 'selected' : '' }}>Yes</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="specialistEducationDate">Specialist Education Completion Date</label>
                                    <input type="text" id="specialistEducationDate" name="specialist_education_date" value="{{ $fetchedData->specialist_education_date ? date('d/m/Y', strtotime($fetchedData->specialist_education_date)) : '' }}" placeholder="dd/mm/yyyy" class="date-picker">
                                </div>
                                <div class="form-group">
                                    <label for="regionalStudy">Regional Study <small class="text-muted">(5 pts - studied in regional Australia)</small></label>
                                    <select id="regionalStudy" name="regional_study">
                                        <option value="0" {{ !$fetchedData->regional_study ? 'selected' : '' }}>No</option>
                                        <option value="1" {{ $fetchedData->regional_study ? 'selected' : '' }}>Yes</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="regionalStudyDate">Regional Study Completion Date</label>
                                    <input type="text" id="regionalStudyDate" name="regional_study_date" value="{{ $fetchedData->regional_study_date ? date('d/m/Y', strtotime($fetchedData->regional_study_date)) : '' }}" placeholder="dd/mm/yyyy" class="date-picker">
                                </div>
                            </div>
                            
                            <div class="edit-actions">
                                <button type="button" class="btn btn-primary" onclick="saveAdditionalInfo()">Save</button>
                                <button type="button" class="btn btn-secondary" onclick="cancelEdit('additionalInfo')">Cancel</button>
                            </div>
                        </div>
                    </section>

                    <!-- Character Section -->
                    <section class="form-section">
                        <div class="section-header">
                            <h3><i class="fas fa-shield-alt"></i> Character&History</h3>
                            <div class="section-actions">
                                <button type="button" class="edit-section-btn" onclick="toggleEditMode('characterInfo')">
                                    <i class="fas fa-pen"></i>
                                </button>
                                <button type="button" class="add-section-btn" onclick="addCharacterRow('characterContainer', 'character_detail')" title="Add Character&History">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Summary View -->
                        <div id="characterInfoSummary" class="summary-view">
                            @if($clientCharacters->count() > 0)
                                <div style="margin-top: 15px;">
                                    @foreach($clientCharacters as $index => $character)
                                        <div class="passport-entry-compact" style="margin-bottom: 12px; padding: 12px; background: #f8f9fa; border-radius: 6px; border-left: 3px solid #dc3545;">
                                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; align-items: start;">
                                                <div class="summary-item-inline">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">TYPE:</span>
                                                    <span class="summary-value" style="color: #212529; font-weight: 500;">
                                                        @switch($character->type_of_character)
                                                            @case(1) Criminal @break
                                                            @case(2) Military/ Intelligence Work @break
                                                            @case(3) Visa/ Citizenship/ refusal/ cancellation/ deportation @break
                                                            @case(4) Health Declaration @break
                                                            @case(5) Other @break
                                                            @default Not set
                                                        @endswitch
                                                    </span>
                                                </div>
                                                <div class="summary-item-inline">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">CHARACTER DETAIL:</span>
                                                    <span class="summary-value" style="color: #212529; font-weight: 500;">{{ $character->character_detail ?: 'Not set' }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="empty-state" style="margin-top: 15px;">
                                    <p>No character/health declaration added yet.</p>
                                </div>
                            @endif
                        </div>

                        <!-- Edit View -->
                        <div id="characterInfoEdit" class="edit-view" style="display: none;">
                            <div id="characterContainer">
                                @foreach($clientCharacters as $index => $character)
                                    <div class="repeatable-section">
                                        <button type="button" class="remove-item-btn" title="Remove Character" onclick="removeCharacterField(this)"><i class="fas fa-trash"></i></button>
                                        <input type="hidden" name="character_id[{{ $index }}]" value="{{ $character->id }}">
                                        <div class="content-grid">
                                            <div class="form-group">
                                                <label>Type</label>
                                                <select name="type_of_character[{{ $index }}]" required>
                                                    <option value="">Select Type</option>
                                                    <option value="1" {{ $character->type_of_character == 1 ? 'selected' : '' }}>Criminal</option>
                                                    <option value="2" {{ $character->type_of_character == 2 ? 'selected' : '' }}>Military/ Intelligence Work</option>
                                                    <option value="3" {{ $character->type_of_character == 3 ? 'selected' : '' }}>Visa/ Citizenship/ refusal/ cancellation/ deportation</option>
                                                    <option value="4" {{ $character->type_of_character == 4 ? 'selected' : '' }}>Health Declaration</option>
                                                    <option value="5" {{ $character->type_of_character == 5 ? 'selected' : '' }}>Other</option>
                                                </select>
                                            </div>
                                            <div class="form-group">
                                                <label>Character&History Detail</label>
                                                <textarea name="character_detail[{{ $index }}]" rows="3" placeholder="Enter character/health declaration details">{{ $character->character_detail }}</textarea>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            <button type="button" class="add-item-btn" onclick="addCharacterRow('characterContainer', 'character_detail')"><i class="fas fa-plus-circle"></i> Add Character&History</button>
                            <div class="edit-actions">
                                <button type="button" class="btn btn-primary" onclick="saveCharacterInfo()">Save</button>
                                <button type="button" class="btn btn-secondary" onclick="cancelEdit('characterInfo')">Cancel</button>
                            </div>
                        </div>
                    </section>

                    <!-- Related Files Section -->
                    <section class="form-section" style="display:none">
                        <div class="section-header">
                            <h3><i class="fas fa-link"></i> Related Files</h3>
                            <div class="section-actions">
                                <button type="button" class="edit-section-btn" onclick="toggleEditMode('relatedFilesInfo')">
                                    <i class="fas fa-pen"></i>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Summary View -->
                        <div id="relatedFilesInfoSummary" class="summary-view">
                            @if($fetchedData->related_files && $fetchedData->related_files != '')
                                <div style="margin-top: 15px;">
                                    @php
                                        $relatedFileIds = explode(',', $fetchedData->related_files);
                                    @endphp
                                    @foreach($relatedFileIds as $relatedId)
                                        @php
                                            $relatedClient = \App\Models\Admin::find($relatedId);
                                        @endphp
                                        @if($relatedClient)
                                            <div class="related-file-entry-compact" style="margin-bottom: 12px; padding: 12px; background: #f8f9fa; border-radius: 6px; border-left: 3px solid #17a2b8;">
                                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; align-items: center;">
                                                    <div class="summary-item-inline">
                                                        <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">CLIENT NAME:</span>
                                                        <span class="summary-value" style="color: #212529; font-weight: 500;">
                                                            <a href="{{ URL::to('/clients/edit/'.base64_encode(convert_uuencode($relatedClient->id))) }}" target="_blank" style="color: #007bff; text-decoration: none;">
                                                                {{ $relatedClient->first_name }} {{ $relatedClient->last_name }}
                                                            </a>
                                                        </span>
                                                    </div>
                                                    <div class="summary-item-inline">
                                                        <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">CLIENT ID:</span>
                                                        <span class="summary-value" style="color: #212529; font-weight: 500;">{{ $relatedClient->client_id ?: 'N/A' }}</span>
                                                    </div>
                                                    <div class="summary-item-inline">
                                                        <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">EMAIL:</span>
                                                        <span class="summary-value" style="color: #212529; font-weight: 500;">{{ $relatedClient->email ?: 'N/A' }}</span>
                                                    </div>
                                                </div>
                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                            @else
                                <div class="empty-state" style="margin-top: 15px;">
                                    <p>No related files added yet.</p>
                                </div>
                            @endif
                        </div>

                        <!-- Edit View -->
                        <div id="relatedFilesInfoEdit" class="edit-view" style="display: none;">
                            <div class="content-grid">
                                @if($fetchedData->visa_type != "Citizen" && $fetchedData->visa_type != "PR")
                                    <div class="form-group" style="grid-column: 1 / -1;">
                                        <label for="relatedFiles">Similar Related Files</label>
                                        <select multiple class="form-control" id="relatedFiles" name="related_files[]" style="width: 100%;">
                                            @if($fetchedData->related_files && $fetchedData->related_files != '')
                                                @php
                                                    $relatedFileIds = explode(',', $fetchedData->related_files);
                                                @endphp
                                                @foreach($relatedFileIds as $relatedId)
                                                    @php
                                                        $relatedClient = \App\Models\Admin::find($relatedId);
                                                    @endphp
                                                    @if($relatedClient)
                                                        <option value="{{ $relatedClient->id }}" selected>{{ $relatedClient->first_name }} {{ $relatedClient->last_name }} ({{ $relatedClient->client_id }})</option>
                                                    @endif
                                                @endforeach
                                            @endif
                                        </select>
                                        <small class="form-text text-muted">Search and select clients by name or client ID. You can select multiple clients.</small>
                                        @if ($errors->has('related_files'))
                                            <span class="text-danger">
                                                <strong>{{ $errors->first('related_files') }}</strong>
                                            </span>
                                        @endif
                                    </div>
                                @else
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle"></i> Related Files are only available for clients with visa types other than Citizen or PR.
                                    </div>
                                @endif
                            </div>
                            
                            <div class="edit-actions">
                                <button type="button" class="btn btn-primary" onclick="saveRelatedFilesInfo()">Save</button>
                                <button type="button" class="btn btn-secondary" onclick="cancelEdit('relatedFilesInfo')">Cancel</button>
                            </div>
                        </div>
                    </section>
                </section>

               
    </div>
    <div id="menu2" class="tab-pane fade matter-tab-pane">
      @php
          $editMatterList = $clientMatters ?? collect();
          $editDetailBase = isset($fetchedData) ? url('/clients/detail/' . base64_encode(convert_uuencode($fetchedData->id))) : '';
          $matterIconMap = [
              'CIV'   => ['icon' => 'fa-balance-scale', 'color' => '#4a6fa5'],
              'CRM'   => ['icon' => 'fa-gavel',         'color' => '#c0392b'],
              'FAM'   => ['icon' => 'fa-heart',         'color' => '#e67e22'],
              'PROP'  => ['icon' => 'fa-home',          'color' => '#27ae60'],
              'CORP'  => ['icon' => 'fa-building',      'color' => '#8e44ad'],
              'LAB'   => ['icon' => 'fa-briefcase',     'color' => '#2980b9'],
              'CONS'  => ['icon' => 'fa-shopping-cart', 'color' => '#16a085'],
              'BANK'  => ['icon' => 'fa-university',    'color' => '#d35400'],
              'TAX'   => ['icon' => 'fa-calculator',   'color' => '#7f8c8d'],
              'IP'    => ['icon' => 'fa-lightbulb',     'color' => '#f39c12'],
              'CONST' => ['icon' => 'fa-scroll',        'color' => '#1a5276'],
              'REV'   => ['icon' => 'fa-map',           'color' => '#117a65'],
              'MACT'  => ['icon' => 'fa-car-crash',     'color' => '#922b21'],
              'MERITS'=> ['icon' => 'fa-clipboard-list','color' => '#5d6d7e'],
              'JR'    => ['icon' => 'fa-search',        'color' => '#1f618d'],
              'NOICC' => ['icon' => 'fa-bell',          'color' => '#b7950b'],
              'IMM'   => ['icon' => 'fa-passport',      'color' => '#154360'],
          ];
          $defaultIcon = ['icon' => 'fa-folder-open', 'color' => '#555'];
      @endphp

      <h3><i class="fas fa-briefcase"></i> Matter Details</h3>

      {{-- ====== STEP 1: Matter Type Selector ====== --}}
      <section class="content-section" id="matterTypeSelectorSection">
        <section class="form-section">
          <div class="section-header">
            <h3><i class="fas fa-list-alt"></i> Select Matter Type</h3>
            <span class="badge" style="background:#e8f0fe;color:#3b5bdb;font-size:0.8em;padding:4px 10px;border-radius:12px;">Required to add a new matter</span>
          </div>
          <p class="text-muted" style="margin-bottom:1rem;">Select a law matter type from the dropdown to add a new matter for this client.</p>

          @php $allMattersList = $allMatters ?? collect(); @endphp
          <div class="row">
            <div class="col-md-6">
              <div class="form-group matter-type-dropdown-wrap">
                <label for="matterTypeDropdown" style="font-weight:600;">Law Matter Type <span class="text-danger">*</span></label>
                <div style="position:relative;">
                  <select id="matterTypeDropdown" class="form-control matter-type-select" onchange="onMatterDropdownChange(this)"
                    style="height:44px;font-size:0.97em;border:2px solid #d0daf5;border-radius:8px;padding-left:12px;appearance:auto;">
                    <option value="">— Select a matter type —</option>
                    @foreach($allMattersList->sortBy('id') as $mt)
                      <option value="{{ $mt->id }}"
                        data-nick="{{ $mt->nick_name }}"
                        data-title="{{ $mt->title }}"
                        data-stream="{{ $mt->stream ?? '' }}">
                        {{ $loop->iteration }}. {{ $mt->title }}
                      </option>
                    @endforeach
                  </select>
                </div>
              </div>
            </div>
            <div class="col-md-6" style="display:flex;align-items:flex-end;padding-bottom:15px;">
              <div id="matterDropdownPreview" style="display:none;align-items:center;gap:10px;background:#f0f4ff;border-radius:8px;padding:8px 16px;border:1px solid #d0daf5;">
                <i id="matterDropdownIcon" class="fas fa-folder-open" style="font-size:1.5rem;"></i>
                <span id="matterDropdownLabel" style="font-weight:600;color:#3b5bdb;font-size:0.95em;"></span>
              </div>
            </div>
          </div>

          <div id="matterDropdownCTA" style="display:none;margin-top:4px;">
            <button type="button" class="btn btn-primary" onclick="openMatterFormFromDropdown()" style="border-radius:8px;">
              <i class="fas fa-plus-circle"></i> Add this Matter
            </button>
            <button type="button" class="btn btn-default" onclick="resetMatterDropdown()" style="margin-left:8px;border-radius:8px;">
              <i class="fas fa-times"></i> Clear
            </button>
          </div>
        </section>
      </section>

      {{-- ====== STEP 2: Dynamic Matter Form (shown after matter type selected) ====== --}}
      <section class="content-section" id="matterDynamicFormSection" style="display:none;">
        <section class="form-section">
          <div class="section-header" style="align-items:center;">
            <h3 id="matterDynamicFormTitle"><i class="fas fa-folder-plus"></i> New Matter Details</h3>
            <button type="button" class="btn btn-sm btn-secondary" onclick="clearMatterTypeSelection()" style="margin-left:auto;">
              <i class="fas fa-times"></i> Cancel
            </button>
          </div>

          <div id="editAddMatterMsg2" style="margin-bottom:10px;"></div>

          {{-- Selected matter badge --}}
          <div id="selectedMatterBadge" style="margin-bottom:1.2rem;"></div>

          {{-- Dynamic sub-type selector + matter-specific fields (all rendered by JS) --}}
          <div id="matterSpecificFields"></div>

          <div id="dynLegalPartySection" class="form-group" style="margin-top:1rem;padding:1rem;background:#fafbfc;border:1px solid #e9ecef;border-radius:8px;">
            <label for="dyn_our_party_role" style="font-weight:600;">Our client&rsquo;s role <small class="text-muted">(optional)</small></label>
            <select class="form-control" id="dyn_our_party_role" style="max-width:420px;">
              <option value="">—</option>
            </select>
            <label style="margin-top:0.9rem;font-weight:600;">Other parties <small class="text-muted">(optional)</small></label>
            <div id="dyn_opposing_parties_wrap"></div>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="dyn_add_opposing_party_btn" style="margin-top:6px;">
              <i class="fas fa-plus"></i> Add other party
            </button>
          </div>

          {{-- Case detail (common) --}}
          <div class="form-group" style="margin-top:0.5rem;">
            <label>Case Detail / Additional Notes <small class="text-muted">(optional)</small></label>
            <textarea class="form-control" id="dyn_case_detail" rows="5" maxlength="5000" placeholder="Describe the matter, key facts, client's instructions..."></textarea>
          </div>

          <div style="margin-top:1rem;">
            <button type="button" class="btn btn-primary" id="dynSubmitMatterBtn" onclick="submitDynamicMatter()">
              <i class="fas fa-plus-circle"></i> Create Matter
            </button>
            <button type="button" class="btn btn-secondary" onclick="clearMatterTypeSelection()" style="margin-left:8px;">Cancel</button>
          </div>
        </section>
      </section>

      {{-- ====== STEP 3: Existing Matters Table ====== --}}
      <section class="content-section">
        <section class="form-section matter-tab-section__card">
          <div class="section-header matter-tab-section__header">
            <div>
              <h3 class="matter-tab-section__title"><i class="fas fa-folder-open"></i> Existing Matters</h3>
              <p class="matter-tab-section__subtitle text-muted">Active matters for {{ $fetchedData->first_name }} {{ $fetchedData->last_name }} ({{ $__crmEditLeadType ? 'Lead' : 'Client' }} ID: {{ $fetchedData->client_id }})</p>
            </div>
          </div>
          @if($editMatterList->isEmpty())
              <div class="matter-tab-empty">
                  <div class="matter-tab-empty__icon"><i class="fas fa-briefcase"></i></div>
                  <p class="matter-tab-empty__title">No matters yet</p>
                  <p class="matter-tab-empty__hint text-muted">Select a matter type above to create the first matter for this {{ $__crmEditLeadType ? 'lead' : 'client' }}.</p>
              </div>
          @else
              <div class="table-responsive matter-tab-table-wrap">
                  <table class="table table-hover matter-tab-table">
                      <thead>
                          <tr>
                              <th>Matter Ref</th>
                              <th>Type</th>
                              <th>Stage</th>
                              <th>Status</th>
                              <th>Date of Incident</th>
                              <th>Case Detail</th>
                              <th class="text-end">Actions</th>
                          </tr>
                      </thead>
                      <tbody>
                          @foreach($editMatterList as $cmatter)
                              @php
                                  $ref = $cmatter->client_unique_matter_no;
                                  $detailUrl = $ref !== null && $ref !== ''
                                      ? $editDetailBase . '/' . $ref
                                      : $editDetailBase;
                                  $typeLabel = $cmatter->matter
                                      ? $cmatter->matter->title
                                      : '—';
                                  $typeNick  = $cmatter->matter->nick_name ?? '';
                                  $rowIconData = $matterIconMap[$typeNick] ?? $defaultIcon;
                                  $caseSnippet = trim((string) ($cmatter->case_detail ?? ''));
                              @endphp
                              <tr>
                                  <td>
                                      <a href="{{ $detailUrl }}" class="matter-tab-ref-link">{{ $ref !== null && $ref !== '' ? $ref : '—' }}</a>
                                  </td>
                                  <td>
                                    <i class="fas {{ $rowIconData['icon'] }}" style="color:{{ $rowIconData['color'] }};margin-right:5px;"></i>
                                    {{ \Illuminate\Support\Str::limit($typeLabel, 50) }}
                                  </td>
                                  <td>{{ $cmatter->workflowStage->name ?? '—' }}</td>
                                  <td>
                                      @if((int) $cmatter->matter_status === 1)
                                          <span class="label label-success">Active</span>
                                      @else
                                          <span class="label label-default">Closed</span>
                                      @endif
                                  </td>
                                  <td>
                                      @if($cmatter->date_of_incidence)
                                          {{ $cmatter->date_of_incidence->format('d/m/Y') }}
                                      @else
                                          <span class="text-muted">—</span>
                                      @endif
                                  </td>
                                  <td class="matter-tab-case-cell">
                                      @if($caseSnippet !== '')
                                          <span class="matter-tab-case-preview" title="{{ e($caseSnippet) }}">{{ \Illuminate\Support\Str::limit($caseSnippet, 100) }}</span>
                                      @else
                                          <span class="text-muted">—</span>
                                      @endif
                                  </td>
                                  <td class="text-nowrap text-end">
                                      <button type="button" class="btn btn-xs btn-primary changeMatterAssignee" data-client-matter-id="{{ $cmatter->id }}" title="Edit matter details">
                                          <i class="fas fa-pen"></i>
                                      </button>
                                      <a href="{{ $detailUrl }}" class="btn btn-xs btn-default" title="View full matter details">
                                          <i class="fas fa-external-link-alt"></i>
                                      </a>
                                  </td>
                              </tr>
                          @endforeach
                      </tbody>
                  </table>
              </div>
              <p class="matter-tab-footer-link text-muted">
                  <a href="{{ route('clients.clientsmatterslist', array_filter(['client_id' => $fetchedData->client_id])) }}"><i class="fas fa-external-link-alt"></i> Full matter list</a>
                  @if($fetchedData->client_id)
                      <span> (filter: {{ $fetchedData->client_id }})</span>
                  @endif
              </p>
          @endif
        </section>
      </section>
    </div>
    {{-- Family Details tab removed --}}
    <div id="menu3" class="tab-pane fade" style="display:none!important;height:0;overflow:hidden;padding:0;margin:0;"></div>
    <div style="display:none;">
      <section id="familySection" class="content-section">
                    <!-- Partner Section -->
                    <section class="form-section">
                        <div class="section-header">
                            <h3><i class="fas fa-heart"></i> Partner</h3>
                            <div class="section-actions">
                                <button type="button" class="edit-section-btn" onclick="toggleEditMode('partnerInfo')">
                                    <i class="fas fa-pen"></i>
                                </button>
                                <button type="button" class="add-section-btn" onclick="addPartnerRow('partner')" title="Add Partner">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Summary View -->
                        <div id="partnerInfoSummary" class="summary-view">
                            @php
                                $partners = $clientPartners->where('relationship_type', 'Husband')->merge($clientPartners->where('relationship_type', 'Wife'))->merge($clientPartners->where('relationship_type', 'Ex-Wife'))->merge($clientPartners->where('relationship_type', 'Defacto'));
                            @endphp
                            @if($partners->count() > 0)
                                <div style="margin-top: 15px;">
                                    @foreach($partners as $index => $partner)
                                        <div class="partner-entry-compact" style="margin-bottom: 12px; padding: 12px; background: #f8f9fa; border-radius: 6px; border-left: 3px solid #007bff;">
                                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; align-items: center;">
                                                <div class="summary-item-inline">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">DETAILS:</span>
                                                    <span class="summary-value" style="color: #212529; font-weight: 500;">{{ $partner->relatedClient ? $partner->relatedClient->first_name . ' ' . $partner->relatedClient->last_name : $partner->details }}</span>
                                                </div>
                                                <div class="summary-item-inline">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">RELATIONSHIP:</span>
                                                    <span class="summary-value" style="color: #212529; font-weight: 500;">{{ $partner->relationship_type ?: 'Not set' }}</span>
                                                </div>
                                                <div class="summary-item-inline">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">GENDER:</span>
                                                    <span class="summary-value" style="color: #212529; font-weight: 500;">{{ $partner->gender ?: 'Not set' }}</span>
                                                </div>
                                                <div class="summary-item-inline">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">COMPANY TYPE:</span>
                                                    <span class="summary-value" style="color: #212529; font-weight: 500;">{{ $partner->company_type ?: 'Not set' }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="empty-state" style="margin-top: 15px;">
                                    <p>No partner information added yet.</p>
                                </div>
                            @endif
                        </div>

                        <!-- Edit View -->
                        <div id="partnerInfoEdit" class="edit-view" style="display: none;">
                            <div id="partnerContainer">
                                @foreach($partners as $index => $partner)
                                    <x-client-edit.family-member-field 
                                        :index="$index"
                                        :member="$partner"
                                        type="partner"
                                        :relationshipOptions="['Husband', 'Wife', 'Ex-Husband', 'Ex-Wife', 'Defacto']"
                                    />
                                @endforeach
                            </div>

                            <button type="button" class="add-item-btn" onclick="addPartnerRow('partner')"><i class="fas fa-plus-circle"></i> Add Partner</button>
                            <div class="edit-actions">
                                <button type="button" class="btn btn-primary" onclick="savePartnerInfo()">Save</button>
                                <button type="button" class="btn btn-secondary" onclick="cancelEdit('partnerInfo')">Cancel</button>
                            </div>
                        </div>
                    </section>

                    <!-- Children Section -->
                    <section class="form-section">
                        <div class="section-header">
                            <h3><i class="fas fa-child"></i> Children</h3>
                            <div class="section-actions">
                                <button type="button" class="edit-section-btn" onclick="toggleEditMode('childrenInfo')">
                                    <i class="fas fa-pen"></i>
                                </button>
                                <button type="button" class="add-section-btn" onclick="addPartnerRow('children')" title="Add Child">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Summary View -->
                        <div id="childrenInfoSummary" class="summary-view">
                            @php
                                $children = $clientPartners->whereIn('relationship_type', ['Son', 'Daughter', 'Step Son', 'Step Daughter']);
                            @endphp
                            @if($children->count() > 0)
                                <div style="margin-top: 15px;">
                                    @foreach($children as $index => $child)
                                        <div class="children-entry-compact" style="margin-bottom: 12px; padding: 12px; background: #f8f9fa; border-radius: 6px; border-left: 3px solid #007bff;">
                                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; align-items: center;">
                                                <div class="summary-item-inline">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">DETAILS:</span>
                                                    <span class="summary-value" style="color: #212529; font-weight: 500;">
                                                        @if($child->relatedClient && $child->related_client_id && $child->related_client_id != 0)
                                                            {{ $child->relatedClient->first_name . ' ' . $child->relatedClient->last_name }}
                                                        @else
                                                            Not set
                                                        @endif
                                                    </span>
                                                </div>
                                                <div class="summary-item-inline">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">RELATIONSHIP:</span>
                                                    <span class="summary-value" style="color: #212529; font-weight: 500;">{{ $child->relationship_type ?: 'Not set' }}</span>
                                                </div>
                                                <div class="summary-item-inline">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">GENDER:</span>
                                                    <span class="summary-value" style="color: #212529; font-weight: 500;">{{ $child->gender ?: 'Not set' }}</span>
                                                </div>
                                                <div class="summary-item-inline">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">COMPANY TYPE:</span>
                                                    <span class="summary-value" style="color: #212529; font-weight: 500;">{{ $child->company_type ?: 'Not set' }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="empty-state" style="margin-top: 15px;">
                                    <p>No children information added yet.</p>
                                </div>
                            @endif
                        </div>

                        <!-- Edit View -->
                        <div id="childrenInfoEdit" class="edit-view" style="display: none;">
                            <div id="childrenContainer">
                                @foreach($children as $index => $child)
                                    <x-client-edit.family-member-field 
                                        :index="$index"
                                        :member="$child"
                                        type="children"
                                        :relationshipOptions="['Son', 'Daughter', 'Step Son', 'Step Daughter']"
                                    />
                                @endforeach
                            </div>

                            <button type="button" class="add-item-btn" onclick="addPartnerRow('children')"><i class="fas fa-plus-circle"></i> Add Child</button>
                            <div class="edit-actions">
                                <button type="button" class="btn btn-primary" onclick="saveChildrenInfo()">Save</button>
                                <button type="button" class="btn btn-secondary" onclick="cancelEdit('childrenInfo')">Cancel</button>
                            </div>
                        </div>
                    </section>

                    <!-- Parents Section -->
                    <section class="form-section">
                        <div class="section-header">
                            <h3><i class="fas fa-user-friends"></i> Parents</h3>
                            <div class="section-actions">
                                <button type="button" class="edit-section-btn" onclick="toggleEditMode('parentsInfo')">
                                    <i class="fas fa-pen"></i>
                                </button>
                                <button type="button" class="add-section-btn" onclick="addPartnerRow('parent')" title="Add Parent">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Summary View -->
                        <div id="parentsInfoSummary" class="summary-view">
                            @php
                                $parents = $clientPartners->whereIn('relationship_type', ['Father', 'Mother', 'Step Father', 'Step Mother', 'Mother-in-law', 'Father-in-law']);
                            @endphp
                            @if($parents->count() > 0)
                                <div style="margin-top: 15px;">
                                    @foreach($parents as $index => $parent)
                                        <div class="parents-entry-compact" style="margin-bottom: 12px; padding: 12px; background: #f8f9fa; border-radius: 6px; border-left: 3px solid #007bff;">
                                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; align-items: center;">
                                                <div class="summary-item-inline">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">DETAILS:</span>
                                                    <span class="summary-value" style="color: #212529; font-weight: 500;">
                                                        @if($parent->relatedClient && $parent->related_client_id && $parent->related_client_id != 0)
                                                            {{ $parent->relatedClient->first_name . ' ' . $parent->relatedClient->last_name }}
                                                        @else
                                                            @php
                                                                $firstName = trim($parent->first_name ?? '');
                                                                $lastName = trim($parent->last_name ?? '');
                                                                
                                                                if (empty($firstName) && empty($lastName)) {
                                                                    $displayName = $parent->details ?: 'Name not provided';
                                                                } elseif (empty($firstName)) {
                                                                    $displayName = $lastName;
                                                                } elseif (empty($lastName)) {
                                                                    $displayName = $firstName;
                                                                } else {
                                                                    $displayName = $firstName . ' ' . $lastName;
                                                                }
                                                            @endphp
                                                            {{ $displayName }}
                                                        @endif
                                                    </span>
                                                </div>
                                                <div class="summary-item-inline">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">RELATIONSHIP:</span>
                                                    <span class="summary-value" style="color: #212529; font-weight: 500;">{{ $parent->relationship_type ?: 'Not set' }}</span>
                                                </div>
                                                <div class="summary-item-inline">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">GENDER:</span>
                                                    <span class="summary-value" style="color: #212529; font-weight: 500;">{{ $parent->gender ?: 'Not set' }}</span>
                                                </div>
                                                <div class="summary-item-inline">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">COMPANY TYPE:</span>
                                                    <span class="summary-value" style="color: #212529; font-weight: 500;">{{ $parent->company_type ?: 'Not set' }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="empty-state" style="margin-top: 15px;">
                                    <p>No parents information added yet.</p>
                                </div>
                            @endif
                        </div>

                        <!-- Edit View -->
                        <div id="parentsInfoEdit" class="edit-view" style="display: none;">
                            <div id="parentContainer">
                                @foreach($parents as $index => $parent)
                                    <x-client-edit.family-member-field 
                                        :index="$index"
                                        :member="$parent"
                                        type="parent"
                                        :relationshipOptions="['Father', 'Mother', 'Step Father', 'Step Mother', 'Mother-in-law', 'Father-in-law']"
                                    />
                                @endforeach
                            </div>

                            <button type="button" class="add-item-btn" onclick="addPartnerRow('parent')"><i class="fas fa-plus-circle"></i> Add Parent</button>
                            <div class="edit-actions">
                                <button type="button" class="btn btn-primary" onclick="saveParentsInfo()">Save</button>
                                <button type="button" class="btn btn-secondary" onclick="cancelEdit('parentsInfo')">Cancel</button>
                            </div>
                        </div>
                    </section>

                    <!-- Siblings Section -->
                    <section class="form-section">
                        <div class="section-header">
                            <h3><i class="fas fa-users"></i> Siblings</h3>
                            <div class="section-actions">
                                <button type="button" class="edit-section-btn" onclick="toggleEditMode('siblingsInfo')">
                                    <i class="fas fa-pen"></i>
                                </button>
                                <button type="button" class="add-section-btn" onclick="addPartnerRow('siblings')" title="Add Sibling">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Summary View -->
                        <div id="siblingsInfoSummary" class="summary-view">
                            @php
                                $siblings = $clientPartners->whereIn('relationship_type', ['Brother', 'Sister', 'Step Brother', 'Step Sister']);
                            @endphp
                            @if($siblings->count() > 0)
                                <div style="margin-top: 15px;">
                                    @foreach($siblings as $index => $sibling)
                                        <div class="siblings-entry-compact" style="margin-bottom: 12px; padding: 12px; background: #f8f9fa; border-radius: 6px; border-left: 3px solid #007bff;">
                                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; align-items: center;">
                                                <div class="summary-item-inline">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">DETAILS:</span>
                                                    <span class="summary-value" style="color: #212529; font-weight: 500;">
                                                        @if($sibling->relatedClient && $sibling->related_client_id && $sibling->related_client_id != 0)
                                                            {{ $sibling->relatedClient->first_name . ' ' . $sibling->relatedClient->last_name }}
                                                        @else
                                                            @php
                                                                $firstName = trim($sibling->first_name ?? '');
                                                                $lastName = trim($sibling->last_name ?? '');
                                                                
                                                                if (empty($firstName) && empty($lastName)) {
                                                                    $displayName = $sibling->details ?: 'Name not provided';
                                                                } elseif (empty($firstName)) {
                                                                    $displayName = $lastName;
                                                                } elseif (empty($lastName)) {
                                                                    $displayName = $firstName;
                                                                } else {
                                                                    $displayName = $firstName . ' ' . $lastName;
                                                                }
                                                            @endphp
                                                            {{ $displayName }}
                                                        @endif
                                                    </span>
                                                </div>
                                                <div class="summary-item-inline">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">RELATIONSHIP:</span>
                                                    <span class="summary-value" style="color: #212529; font-weight: 500;">{{ $sibling->relationship_type ?: 'Not set' }}</span>
                                                </div>
                                                <div class="summary-item-inline">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">GENDER:</span>
                                                    <span class="summary-value" style="color: #212529; font-weight: 500;">{{ $sibling->gender ?: 'Not set' }}</span>
                                                </div>
                                                <div class="summary-item-inline">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">COMPANY TYPE:</span>
                                                    <span class="summary-value" style="color: #212529; font-weight: 500;">{{ $sibling->company_type ?: 'Not set' }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="empty-state" style="margin-top: 15px;">
                                    <p>No siblings information added yet.</p>
                                </div>
                            @endif
                        </div>

                        <!-- Edit View -->
                        <div id="siblingsInfoEdit" class="edit-view" style="display: none;">
                            <div id="siblingsContainer">
                                @foreach($siblings as $index => $sibling)
                                    <x-client-edit.family-member-field 
                                        :index="$index"
                                        :member="$sibling"
                                        type="siblings"
                                        :relationshipOptions="['Brother', 'Sister', 'Step Brother', 'Step Sister']"
                                    />
                                @endforeach
                            </div>

                            <button type="button" class="add-item-btn" onclick="addPartnerRow('siblings')"><i class="fas fa-plus-circle"></i> Add Sibling</button>
                            <div class="edit-actions">
                                <button type="button" class="btn btn-primary" onclick="saveSiblingsInfo()">Save</button>
                                <button type="button" class="btn btn-secondary" onclick="cancelEdit('siblingsInfo')">Cancel</button>
                            </div>
                        </div>
                    </section>

                    <!-- Others Section -->
                    <section class="form-section">
                        <div class="section-header">
                            <h3><i class="fas fa-users"></i> Others</h3>
                            <div class="section-actions">
                                <button type="button" class="edit-section-btn" onclick="toggleEditMode('othersInfo')">
                                    <i class="fas fa-pen"></i>
                                </button>
                                <button type="button" class="add-section-btn" onclick="addPartnerRow('others')" title="Add Other">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Summary View -->
                        <div id="othersInfoSummary" class="summary-view">
                            @php
                                $others = $clientPartners->whereIn('relationship_type', ['Cousin', 'Friend', 'Uncle', 'Aunt', 'Grandchild', 'Granddaughter', 'Grandparent', 'Niece', 'Nephew', 'Grandfather', 'Son-in-law', 'Daughter-in-law', 'Brother-in-law', 'Sister-in-law']);
                            @endphp
                            @if($others->count() > 0)
                                <div style="margin-top: 15px;">
                                    @foreach($others as $index => $other)
                                        <div class="others-entry-compact" style="margin-bottom: 12px; padding: 12px; background: #f8f9fa; border-radius: 6px; border-left: 3px solid #007bff;">
                                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; align-items: center;">
                                                <div class="summary-item-inline">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">DETAILS:</span>
                                                    <span class="summary-value" style="color: #212529; font-weight: 500;">
                                                        @if($other->relatedClient && $other->related_client_id && $other->related_client_id != 0)
                                                            {{ $other->relatedClient->first_name . ' ' . $other->relatedClient->last_name }}
                                                        @else
                                                            @php
                                                                $firstName = trim($other->first_name ?? '');
                                                                $lastName = trim($other->last_name ?? '');
                                                                
                                                                if (empty($firstName) && empty($lastName)) {
                                                                    $displayName = $other->details ?: 'Name not provided';
                                                                } elseif (empty($firstName)) {
                                                                    $displayName = $lastName;
                                                                } elseif (empty($lastName)) {
                                                                    $displayName = $firstName;
                                                                } else {
                                                                    $displayName = $firstName . ' ' . $lastName;
                                                                }
                                                            @endphp
                                                            {{ $displayName }}
                                                        @endif
                                                    </span>
                                                </div>
                                                <div class="summary-item-inline">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">RELATIONSHIP:</span>
                                                    <span class="summary-value" style="color: #212529; font-weight: 500;">{{ $other->relationship_type ?: 'Not set' }}</span>
                                                </div>
                                                <div class="summary-item-inline">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">GENDER:</span>
                                                    <span class="summary-value" style="color: #212529; font-weight: 500;">{{ $other->gender ?: 'Not set' }}</span>
                                                </div>
                                                <div class="summary-item-inline">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">COMPANY TYPE:</span>
                                                    <span class="summary-value" style="color: #212529; font-weight: 500;">{{ $other->company_type ?: 'Not set' }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="empty-state" style="margin-top: 15px;">
                                    <p>No others information added yet.</p>
                                </div>
                            @endif
                        </div>

                        <!-- Edit View -->
                        <div id="othersInfoEdit" class="edit-view" style="display: none;">
                            <div id="othersContainer">
                                @foreach($others as $index => $other)
                                    <x-client-edit.family-member-field 
                                        :index="$index"
                                        :member="$other"
                                        type="others"
                                        :relationshipOptions="['Cousin', 'Friend', 'Uncle', 'Aunt', 'Grandchild', 'Granddaughter', 'Grandparent', 'Niece', 'Nephew', 'Grandfather', 'Son-in-law', 'Daughter-in-law', 'Brother-in-law', 'Sister-in-law']"
                                    />
                                @endforeach
                            </div>

                            <button type="button" class="add-item-btn" onclick="addPartnerRow('others')"><i class="fas fa-plus-circle"></i> Add Other</button>
                            <div class="edit-actions">
                                <button type="button" class="btn btn-primary" onclick="saveOthersInfo()">Save</button>
                                <button type="button" class="btn btn-secondary" onclick="cancelEdit('othersInfo')">Cancel</button>
                            </div>
                        </div>
                    </section>
                </section>
    </div></div>

    {{-- ====== TAB 4: Court Dates & Hearings ====== --}}
    <div id="menu4" class="tab-pane fade">
      <h3><i class="fas fa-gavel"></i> Court Dates &amp; Hearings</h3>
      <p class="text-muted">Track important court appearances, hearings, and deadlines. All entries are optional.</p>

      {{-- Add Hearing Form --}}
      <section class="content-section">
        <section class="form-section">
          <div class="section-header">
            <h3><i class="fas fa-plus-circle"></i> Add Court Hearing / Date</h3>
            <span class="badge" style="background:#e8f5e9;color:#2e7d32;font-size:0.8em;padding:4px 10px;border-radius:12px;">Optional</span>
          </div>
          <div id="hearingFormMsg" style="margin-bottom:8px;"></div>
          <div class="row">
            <div class="col-md-4">
              <div class="form-group">
                <label>Hearing Date <span class="text-danger">*</span></label>
                <input type="date" class="form-control" id="hearing_date">
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group">
                <label>Hearing Time <small class="text-muted">(optional)</small></label>
                <input type="time" class="form-control" id="hearing_time">
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group">
                <label>Hearing Type <small class="text-muted">(optional)</small></label>
                <select class="form-control" id="hearing_type" style="height:40px;font-size:0.94em;">
                  <option value="">— Select Hearing Type —</option>
                  <option value="First Hearing">First Hearing</option>
                  <option value="Evidence Hearing">Evidence Hearing</option>
                  <option value="Arguments">Arguments</option>
                  <option value="Judgment">Judgment</option>
                  <option value="Bail Hearing">Bail Hearing</option>
                  <option value="Stay Application">Stay Application</option>
                  <option value="Case Management">Case Management</option>
                  <option value="Mediation">Mediation</option>
                  <option value="Mention">Mention</option>
                  <option value="Other">Other</option>
                </select>
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group">
                <label>Court Name <small class="text-muted">(optional)</small></label>
                <input type="text" class="form-control" id="hearing_court_name" maxlength="255" placeholder="e.g. Delhi High Court">
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group">
                <label>Case Number <small class="text-muted">(optional)</small></label>
                <input type="text" class="form-control" id="hearing_case_number" maxlength="100" placeholder="e.g. CS/1234/2024">
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group">
                <label>Judge / Bench <small class="text-muted">(optional)</small></label>
                <input type="text" class="form-control" id="hearing_judge_name" maxlength="150" placeholder="e.g. Hon. Justice Sharma">
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label>Linked Matter <small class="text-muted">(optional)</small></label>
                <select class="form-control dyn-select" id="hearing_matter_id">
                  <option value="">— Not linked to a specific matter —</option>
                  @foreach($clientMatters ?? collect() as $cm)
                    <option value="{{ $cm->id }}">
                      {{ $cm->client_unique_matter_no ?? 'Matter #'.$cm->id }}
                      @if($cm->matter) — {{ $cm->matter->title }} @endif
                    </option>
                  @endforeach
                </select>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label>Status</label>
                <select class="form-control dyn-select" id="hearing_status">
                  <option value="Scheduled">Scheduled</option>
                  <option value="Completed">Completed</option>
                  <option value="Adjourned">Adjourned</option>
                  <option value="Cancelled">Cancelled</option>
                </select>
              </div>
            </div>
            <div class="col-md-12">
              <div class="form-group">
                <label>Notes / Instructions <small class="text-muted">(optional)</small></label>
                <textarea class="form-control" id="hearing_notes" rows="3" maxlength="5000" placeholder="Any important notes, instructions, or context for this hearing..."></textarea>
              </div>
            </div>
          </div>
          <button type="button" class="btn btn-primary" onclick="submitHearing()">
            <i class="fas fa-calendar-plus"></i> Add Hearing
          </button>
        </section>
      </section>

      {{-- Existing Hearings List --}}
      <section class="content-section" style="margin-top:1.5rem;">
        <section class="form-section">
          <div class="section-header">
            <h3><i class="fas fa-calendar-alt"></i> Scheduled &amp; Past Hearings</h3>
          </div>
          <div id="hearingsListContainer">
            @if(($courtHearings ?? collect())->isEmpty())
              <div class="matter-tab-empty" style="padding:2rem;">
                <div class="matter-tab-empty__icon"><i class="fas fa-calendar-times" style="font-size:2.5rem;color:#adb5bd;"></i></div>
                <p class="matter-tab-empty__title">No hearings recorded</p>
                <p class="matter-tab-empty__hint text-muted">Add the first court date using the form above.</p>
              </div>
            @else
              <div class="table-responsive">
                <table class="table table-hover" id="hearingsTable">
                  <thead>
                    <tr>
                      <th>Date</th>
                      <th>Time</th>
                      <th>Type</th>
                      <th>Court</th>
                      <th>Case No.</th>
                      <th>Judge</th>
                      <th>Status</th>
                      <th>Linked Matter</th>
                      <th>Notes</th>
                      <th></th>
                    </tr>
                  </thead>
                  <tbody id="hearingsTableBody">
                    @foreach($courtHearings as $hearing)
                      <tr id="hearing-row-{{ $hearing->id }}" data-hearing-id="{{ $hearing->id }}">
                        <td><strong>{{ $hearing->hearing_date->format('d/m/Y') }}</strong></td>
                        <td>{{ $hearing->hearing_time ? \Carbon\Carbon::parse($hearing->hearing_time)->format('g:i A') : '—' }}</td>
                        <td>{{ $hearing->hearing_type ?: '—' }}</td>
                        <td>{{ $hearing->court_name ?: '—' }}</td>
                        <td>{{ $hearing->case_number ?: '—' }}</td>
                        <td>{{ $hearing->judge_name ?: '—' }}</td>
                        <td>
                          @php
                            $statusColors = ['Scheduled'=>'#1a73e8','Completed'=>'#188038','Adjourned'=>'#e37400','Cancelled'=>'#c5221f'];
                            $sc = $statusColors[$hearing->status] ?? '#555';
                          @endphp
                          <span style="color:{{ $sc }};font-weight:600;">{{ $hearing->status }}</span>
                        </td>
                        <td>
                          @if($hearing->client_matter_id && $hearing->matter)
                            {{ $hearing->matter->client_unique_matter_no ?? '—' }}
                          @else
                            <span class="text-muted">—</span>
                          @endif
                        </td>
                        <td style="max-width:180px;">
                          @if($hearing->notes)
                            <span title="{{ e($hearing->notes) }}">{{ \Illuminate\Support\Str::limit($hearing->notes, 60) }}</span>
                          @else
                            <span class="text-muted">—</span>
                          @endif
                        </td>
                        <td>
                          <button type="button" class="btn btn-xs btn-danger" onclick="deleteHearing({{ $hearing->id }})" title="Delete">
                            <i class="fas fa-trash"></i>
                          </button>
                        </td>
                      </tr>
                    @endforeach
                  </tbody>
                </table>
              </div>
            @endif
          </div>
        </section>
      </section>
    </div>
    {{-- End menu4 --}}

    </form>
  </div>
               
            </div>
        </div>
    </div>

    <!-- Go to Top Button -->
    <button id="goToTopBtn" class="go-to-top-btn" onclick="scrollToTop()" title="Go to Top">
        <i class="fas fa-chevron-up"></i>
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
    <script src="{{ asset('js/crm/clients/matter-assignee-modal.js') }}?v={{ time() }}"></script>
    <script>
        // Pass countries data to JavaScript
        window.countriesData = @json($countries);
        window.storeLeadMatterFromEditUrl = @json(route('clients.storeLeadMatterFromEdit'));
        function openAddMatterModal() {
            var el = document.getElementById('addMatterModal');
            if (!el) return;
            el.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
        function closeAddMatterModal() {
            var el = document.getElementById('addMatterModal');
            if (!el) return;
            el.style.display = 'none';
            document.body.style.overflow = '';
            var msg = document.getElementById('editAddMatterMsg');
            if (msg) msg.innerHTML = '';
            var caseDetail = document.getElementById('edit_add_matter_case_detail');
            if (caseDetail) caseDetail.value = '';
            var doi = document.getElementById('edit_add_matter_date_of_incidence');
            if (doi) doi.value = '';
            var it = document.getElementById('edit_add_matter_incidence_type');
            if (it) it.value = '';
        }
        function addMatterModalBackdropClick(e) {
            if (e.target && e.target.id === 'addMatterModal') {
                closeAddMatterModal();
            }
        }
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                var m = document.getElementById('addMatterModal');
                if (m && m.style.display === 'flex') closeAddMatterModal();
            }
        });
        async function submitLeadMatterFromEdit() {
            var msgEl = document.getElementById('editAddMatterMsg');
            var btn = document.getElementById('editAddMatterSubmitBtn');
            if (!msgEl || !window.storeLeadMatterFromEditUrl || !window.editClientConfig) return;
            msgEl.innerHTML = '';
            var matterId = document.getElementById('edit_add_matter_matter_id');
            var agentId = document.getElementById('edit_add_matter_legal_practitioner');
            if (!matterId.value || !agentId.value) {
                msgEl.innerHTML = '<div class="alert alert-warning">Select a matter type and legal practitioner.</div>';
                return;
            }
            var fd = new FormData();
            fd.append('_token', window.editClientConfig.csrfToken);
            var matterClientPk = (window.currentClientId != null && String(window.currentClientId).trim() !== '')
                ? String(window.currentClientId).trim()
                : String({{ (int) $fetchedData->id }});
            fd.append('client_id', matterClientPk);
            fd.append('matter_id', matterId.value);
            fd.append('legal_practitioner', agentId.value);
            var office = document.getElementById('edit_add_matter_office_id');
            if (office && office.value) fd.append('office_id', office.value);
            var pr = document.getElementById('edit_add_matter_person_responsible');
            if (pr && pr.value) fd.append('person_responsible', pr.value);
            var pa = document.getElementById('edit_add_matter_person_assisting');
            if (pa && pa.value) fd.append('person_assisting', pa.value);
            var caseDetailEl = document.getElementById('edit_add_matter_case_detail');
            if (caseDetailEl && caseDetailEl.value.trim() !== '') {
                fd.append('case_detail', caseDetailEl.value.trim());
            }
            var doiEl = document.getElementById('edit_add_matter_date_of_incidence');
            if (doiEl && doiEl.value) fd.append('date_of_incidence', doiEl.value);
            var itEl = document.getElementById('edit_add_matter_incidence_type');
            if (itEl && itEl.value.trim() !== '') fd.append('incidence_type', itEl.value.trim());
            btn.disabled = true;
            try {
                var res = await fetch(window.storeLeadMatterFromEditUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': window.editClientConfig.csrfToken
                    },
                    body: fd
                });
                var data = await res.json().catch(function () { return {}; });
                if (res.ok && data.success) {
                    msgEl.innerHTML = '<div class="alert alert-success">' + (data.message || 'Matter created.') + '</div>';
                    window.setTimeout(function () {
                        closeAddMatterModal();
                        window.location.reload();
                    }, 600);
                    return;
                }
                var errText = data.message || 'Could not create matter.';
                if (data.errors) {
                    errText += ' ' + Object.values(data.errors).flat().join(' ');
                }
                msgEl.innerHTML = '<div class="alert alert-danger">' + errText + '</div>';
            } catch (e) {
                msgEl.innerHTML = '<div class="alert alert-danger">Network error. Try again.</div>';
            } finally {
                btn.disabled = false;
            }
        }
    </script>
    <script src="{{asset('js/clients/edit-client.js')}}"></script>
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
                    var $link = $('.client-edit-top-pills a[href="#' + targetId + '"]');
                    var $pane = $('#' + targetId);
                    if (!$link.length || !$pane.length) return;
                    $('.client-edit-top-pills li').removeClass('active');
                    $link.closest('li').addClass('active');
                    $('.main-content-area .tab-pane').removeClass('in active').hide();
                    $pane.addClass('in active').css('display', 'block').show();
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
    <script src="{{asset('js/clients/english-proficiency.js')}}"></script>
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
                    msgEl.innerHTML = '<i class="fas fa-check-circle" style="color:#188038;"></i> <span style="color:#188038;">Saved</span>';
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

    // =====================================================
    // Matter Type Selector — Dynamic Form
    // =====================================================
    window.MATTER_PARTY_ROLES_BY_STREAM = @json(config('matter_streams.party_roles_by_stream', []));
    var selectedMatterTypeId = null;
    var selectedMatterTypeNick = null;

    function getDynMatterStream() {
        var sel = document.getElementById('matterTypeDropdown');
        if (!sel) return 'general';
        var opt = sel.options[sel.selectedIndex];
        var s = opt && opt.getAttribute('data-stream');
        return (s && String(s).trim() !== '') ? String(s).trim() : 'general';
    }

    function rebuildDynOurPartyRole() {
        var map = window.MATTER_PARTY_ROLES_BY_STREAM || {};
        var stream = getDynMatterStream();
        var roles = map[stream] || map['general'] || {};
        var pr = document.getElementById('dyn_our_party_role');
        if (!pr) return;
        var cur = pr.value;
        pr.innerHTML = '';
        var o0 = document.createElement('option');
        o0.value = '';
        o0.textContent = '\u2014';
        pr.appendChild(o0);
        Object.keys(roles).forEach(function (k) {
            var o = document.createElement('option');
            o.value = k;
            o.textContent = roles[k];
            pr.appendChild(o);
        });
        if (cur) { pr.value = cur; }
    }

    function dynAppendOpposingRow(name, role) {
        var wrap = document.getElementById('dyn_opposing_parties_wrap');
        if (!wrap) return;
        var row = document.createElement('div');
        row.className = 'row mb-2 dyn-opp-row';
        row.style.alignItems = 'flex-end';
        row.innerHTML =
            '<div class="col-md-5"><label class="small mb-0 d-block">Name</label>' +
            '<input type="text" class="form-control dyn-opp-name" maxlength="500" value=""></div>' +
            '<div class="col-md-5"><label class="small mb-0 d-block">Their role</label>' +
            '<input type="text" class="form-control dyn-opp-role" maxlength="255" placeholder="e.g. co-defendant" value=""></div>' +
            '<div class="col-md-2"><label class="small mb-0 d-block">&nbsp;</label>' +
            '<button type="button" class="btn btn-sm btn-outline-danger w-100 dyn-opp-remove">Remove</button></div>';
        row.querySelector('.dyn-opp-name').value = name || '';
        row.querySelector('.dyn-opp-role').value = role || '';
        row.querySelector('.dyn-opp-remove').addEventListener('click', function () { row.remove(); });
        wrap.appendChild(row);
    }

    document.addEventListener('click', function (e) {
        if (e.target && e.target.id === 'dyn_add_opposing_party_btn') {
            e.preventDefault();
            dynAppendOpposingRow('', '');
        }
    });

    var matterSpecificFieldsConfig = {
        'CIV': {
            label: 'Civil Law',
            subType: { id: 'dyn_sub_type', label: 'Type of Civil Matter', required: true,
                options: ['Money Recovery Suit','Injunction','Specific Performance','Declaratory Suit','Partition Suit','Breach of Contract','Other'] },
            commonFields: [
                { id: 'dyn_date_of_incidence', label: 'Date of Filing / Cause of Action', type: 'date' },
                { id: 'dyn_opposing_party', label: 'Opposing Party / Defendant', type: 'text', placeholder: 'Name of the opposing party' },
                { id: 'dyn_court_name', label: 'Court Name', type: 'text', placeholder: 'e.g. Civil Court, Saket' }
            ],
            subTypeFields: {
                'Money Recovery Suit': [
                    { id: 'dyn_amount', label: 'Amount Claimed (₹)', type: 'number', placeholder: 'e.g. 500000' },
                    { id: 'dyn_basis', label: 'Basis of Claim', type: 'select', options: ['Loan','Agreement','Cheque','Invoice','Other'] },
                    { id: 'dyn_due_date', label: 'Due Date', type: 'date' }
                ],
                'Injunction': [
                    { id: 'dyn_injunction_type', label: 'Nature of Injunction', type: 'select', options: ['Temporary','Permanent','Mandatory','Prohibitory'] },
                    { id: 'dyn_subject', label: 'Subject Property / Matter', type: 'text', placeholder: 'Details of subject matter' }
                ],
                'Specific Performance': [
                    { id: 'dyn_agreement_date', label: 'Agreement Date', type: 'date' },
                    { id: 'dyn_subject', label: 'Subject of Agreement', type: 'text', placeholder: 'e.g. Sale of property at...' }
                ],
                'Declaratory Suit': [
                    { id: 'dyn_declaration', label: 'Declaration Sought', type: 'text', placeholder: 'Nature of declaration sought' }
                ],
                'Partition Suit': [
                    { id: 'dyn_property_detail', label: 'Property Details', type: 'text', placeholder: 'Address / description' },
                    { id: 'dyn_co_owners', label: 'Total Co-owners', type: 'number', placeholder: 'No. of co-owners' },
                    { id: 'dyn_share', label: 'Share Claimed', type: 'text', placeholder: 'e.g. 1/4th' }
                ],
                'Breach of Contract': [
                    { id: 'dyn_contract_date', label: 'Contract Date', type: 'date' },
                    { id: 'dyn_breach_date', label: 'Date of Breach', type: 'date' },
                    { id: 'dyn_damages', label: 'Damages Claimed (₹)', type: 'number', placeholder: 'e.g. 1000000' }
                ]
            }
        },
        'CRM': {
            label: 'Criminal Law',
            subType: { id: 'dyn_sub_type', label: 'Type of Criminal Matter', required: true,
                options: ['Bail Application','Anticipatory Bail','Quashing Petition','Trial Defence','Private Complaint','Criminal Appeal','Revision Petition','Other'] },
            commonFields: [
                { id: 'dyn_date_of_incidence', label: 'Date of Incident / FIR', type: 'date' },
                { id: 'dyn_fir_no', label: 'FIR / Case Number', type: 'text', placeholder: 'e.g. FIR 123/2024' },
                { id: 'dyn_police_station', label: 'Police Station', type: 'text', placeholder: 'Name of police station' },
                { id: 'dyn_court_name', label: 'Court', type: 'text', placeholder: 'e.g. Sessions Court, Delhi' }
            ],
            subTypeFields: {
                'Bail Application': [
                    { id: 'dyn_charges', label: 'Charges / Sections', type: 'text', placeholder: 'e.g. IPC 302, 420' },
                    { id: 'dyn_arrest_date', label: 'Date of Arrest', type: 'date' },
                    { id: 'dyn_jail', label: 'Jail / Detention Place', type: 'text', placeholder: 'Name of jail' }
                ],
                'Anticipatory Bail': [
                    { id: 'dyn_charges', label: 'Apprehended Charges', type: 'text', placeholder: 'e.g. IPC 406, 420' },
                    { id: 'dyn_reason', label: 'Reason for Apprehension', type: 'text', placeholder: 'Why arrest is feared' }
                ],
                'Quashing Petition': [
                    { id: 'dyn_charges', label: 'Sections Challenged', type: 'text', placeholder: 'e.g. IPC 498A' },
                    { id: 'dyn_grounds', label: 'Grounds for Quashing', type: 'text', placeholder: 'Brief grounds' }
                ],
                'Trial Defence': [
                    { id: 'dyn_charges', label: 'Charges / Sections', type: 'text', placeholder: 'e.g. IPC 302, 34' },
                    { id: 'dyn_trial_stage', label: 'Stage of Trial', type: 'select', options: ['Pre-charge','Charge Framed','Prosecution Evidence','Defence Evidence','Final Arguments','Other'] }
                ],
                'Private Complaint': [
                    { id: 'dyn_accused', label: 'Accused Name', type: 'text', placeholder: 'Name of accused' },
                    { id: 'dyn_offence', label: 'Nature of Offence', type: 'text', placeholder: 'Brief description' }
                ],
                'Criminal Appeal': [
                    { id: 'dyn_original_case', label: 'Original Case Number', type: 'text', placeholder: 'Lower court case no.' },
                    { id: 'dyn_conviction_date', label: 'Conviction Date', type: 'date' },
                    { id: 'dyn_sentence', label: 'Sentence Details', type: 'text', placeholder: 'e.g. 5 years RI' }
                ],
                'Revision Petition': [
                    { id: 'dyn_order_date', label: 'Original Order Date', type: 'date' },
                    { id: 'dyn_lower_court', label: 'Lower Court', type: 'text', placeholder: 'Court whose order is challenged' }
                ]
            }
        },
        'FAM': {
            label: 'Family Law',
            subType: { id: 'dyn_sub_type', label: 'Type of Family Matter', required: true,
                options: ['Divorce','Child Custody','Maintenance / Alimony','Domestic Violence','Adoption','Guardianship','Restitution of Conjugal Rights','Other'] },
            commonFields: [
                { id: 'dyn_opposing_party', label: 'Opposing Party', type: 'text', placeholder: 'Name of the other party' },
                { id: 'dyn_court_name', label: 'Court / Family Court', type: 'text', placeholder: 'e.g. Family Court, Rohini' }
            ],
            subTypeFields: {
                'Divorce': [
                    { id: 'dyn_marriage_date', label: 'Date of Marriage', type: 'date' },
                    { id: 'dyn_separation_date', label: 'Date of Separation', type: 'date' },
                    { id: 'dyn_grounds', label: 'Grounds for Divorce', type: 'select', options: ['Mutual Consent','Cruelty','Desertion','Adultery','Mental Disorder','Conversion','Presumption of Death','Other'] },
                    { id: 'dyn_children', label: 'Children', type: 'select', options: ['No Children','1 Child','2 Children','3+ Children'] }
                ],
                'Child Custody': [
                    { id: 'dyn_child_name', label: 'Child Name(s)', type: 'text', placeholder: 'Name(s) of child(ren)' },
                    { id: 'dyn_child_age', label: 'Child Age(s)', type: 'text', placeholder: 'e.g. 5 yrs, 8 yrs' },
                    { id: 'dyn_custody_type', label: 'Custody Sought', type: 'select', options: ['Sole Custody','Joint Custody','Visitation Rights'] },
                    { id: 'dyn_current_custodian', label: 'Current Custodian', type: 'text', placeholder: 'Who currently has custody' }
                ],
                'Maintenance / Alimony': [
                    { id: 'dyn_marriage_date', label: 'Date of Marriage', type: 'date' },
                    { id: 'dyn_pet_income', label: "Petitioner's Monthly Income (₹)", type: 'number', placeholder: 'e.g. 50000' },
                    { id: 'dyn_resp_income', label: "Respondent's Monthly Income (₹)", type: 'number', placeholder: 'e.g. 80000' },
                    { id: 'dyn_amount_sought', label: 'Maintenance Sought (₹/month)', type: 'number', placeholder: 'e.g. 25000' }
                ],
                'Domestic Violence': [
                    { id: 'dyn_date_of_incidence', label: 'Date of Incident', type: 'date' },
                    { id: 'dyn_violence_type', label: 'Nature of Violence', type: 'select', options: ['Physical','Emotional / Mental','Economic','Sexual','Verbal','Multiple'] },
                    { id: 'dyn_protection_order', label: 'Protection Order Sought', type: 'select', options: ['Yes','No'] },
                    { id: 'dyn_relationship', label: 'Relationship with Respondent', type: 'select', options: ['Husband','In-Laws','Live-in Partner','Other'] }
                ],
                'Adoption': [
                    { id: 'dyn_child_name', label: 'Child Name', type: 'text', placeholder: 'Name of child' },
                    { id: 'dyn_child_age', label: 'Child Age', type: 'text', placeholder: 'Age of child' },
                    { id: 'dyn_adoption_type', label: 'Adoption Type', type: 'select', options: ['Domestic','Inter-country'] },
                    { id: 'dyn_agency', label: 'Agency / CARA Reg. No.', type: 'text', placeholder: 'Agency name or registration no.' }
                ],
                'Guardianship': [
                    { id: 'dyn_ward_name', label: 'Ward Name', type: 'text', placeholder: 'Name of person' },
                    { id: 'dyn_ward_age', label: 'Ward Age', type: 'text', placeholder: 'Age' },
                    { id: 'dyn_relation', label: 'Relationship with Ward', type: 'text', placeholder: 'e.g. Uncle, Grandparent' }
                ],
                'Restitution of Conjugal Rights': [
                    { id: 'dyn_marriage_date', label: 'Date of Marriage', type: 'date' },
                    { id: 'dyn_desertion_date', label: 'Date of Desertion', type: 'date' }
                ]
            }
        },
        'PROP': {
            label: 'Property & Real Estate',
            subType: { id: 'dyn_sub_type', label: 'Type of Property Matter', required: true,
                options: ['Title Dispute','Possession Dispute','Encroachment','Sale / Purchase Dispute','Tenancy / Eviction','Builder / RERA','Land Acquisition','Other'] },
            commonFields: [
                { id: 'dyn_property_address', label: 'Property Address', type: 'text', placeholder: 'Full address of the property' },
                { id: 'dyn_opposing_party', label: 'Opposing Party', type: 'text', placeholder: 'Name of other party' },
                { id: 'dyn_court_name', label: 'Court / Forum', type: 'text', placeholder: 'e.g. District Court, Gurugram' }
            ],
            subTypeFields: {
                'Title Dispute': [
                    { id: 'dyn_survey_no', label: 'Khasra / Survey No.', type: 'text', placeholder: 'Property identification no.' },
                    { id: 'dyn_area', label: 'Area (sq. ft / bigha)', type: 'text', placeholder: 'e.g. 500 sq ft' },
                    { id: 'dyn_docs', label: 'Documents Available', type: 'text', placeholder: 'e.g. Sale Deed, Registry' }
                ],
                'Possession Dispute': [
                    { id: 'dyn_occupied_by', label: 'Occupied By', type: 'text', placeholder: 'Name of occupant' },
                    { id: 'dyn_since_when', label: 'Occupation Since', type: 'text', placeholder: 'e.g. Jan 2020' },
                    { id: 'dyn_basis', label: 'Basis of Possession', type: 'text', placeholder: 'e.g. Lease, Trespass' }
                ],
                'Encroachment': [
                    { id: 'dyn_encroached_area', label: 'Area Encroached', type: 'text', placeholder: 'e.g. 200 sq ft' },
                    { id: 'dyn_encroacher', label: 'Encroacher Details', type: 'text', placeholder: 'Name / details' }
                ],
                'Sale / Purchase Dispute': [
                    { id: 'dyn_agreement_date', label: 'Agreement Date', type: 'date' },
                    { id: 'dyn_sale_amount', label: 'Sale Consideration (₹)', type: 'number', placeholder: 'e.g. 5000000' },
                    { id: 'dyn_seller', label: 'Seller / Builder Name', type: 'text', placeholder: 'Name of seller or builder' }
                ],
                'Tenancy / Eviction': [
                    { id: 'dyn_rent', label: 'Monthly Rent (₹)', type: 'number', placeholder: 'e.g. 15000' },
                    { id: 'dyn_tenancy_since', label: 'Tenancy Since', type: 'date' },
                    { id: 'dyn_rent_agreement', label: 'Rent Agreement Exists', type: 'select', options: ['Yes','No'] }
                ],
                'Builder / RERA': [
                    { id: 'dyn_builder', label: 'Builder Name', type: 'text', placeholder: 'Name of builder / developer' },
                    { id: 'dyn_project', label: 'Project Name', type: 'text', placeholder: 'Project / society name' },
                    { id: 'dyn_unit_no', label: 'Unit / Flat No.', type: 'text', placeholder: 'e.g. A-1204' },
                    { id: 'dyn_booking_date', label: 'Booking Date', type: 'date' },
                    { id: 'dyn_amount_paid', label: 'Amount Paid (₹)', type: 'number', placeholder: 'e.g. 3000000' },
                    { id: 'dyn_possession_due', label: 'Possession Due Date', type: 'date' }
                ],
                'Land Acquisition': [
                    { id: 'dyn_khasra', label: 'Khasra / Plot No.', type: 'text', placeholder: 'Land identification' },
                    { id: 'dyn_village', label: 'Village / Tehsil / District', type: 'text', placeholder: 'Location details' },
                    { id: 'dyn_land_area', label: 'Land Area', type: 'text', placeholder: 'e.g. 2 bigha' },
                    { id: 'dyn_compensation', label: 'Compensation Offered (₹)', type: 'number', placeholder: 'Amount offered' },
                    { id: 'dyn_award_date', label: 'Award Date', type: 'date' }
                ]
            }
        },
        'CORP': {
            label: 'Corporate & Business Law',
            subType: { id: 'dyn_sub_type', label: 'Type of Corporate Matter', required: true,
                options: ['Contract / Commercial Dispute','Shareholder / Director Dispute','Insolvency (IBC / NCLT)','Merger & Acquisition','Partnership Dispute','Regulatory / Compliance','Other'] },
            commonFields: [
                { id: 'dyn_company_name', label: 'Company / Entity Name', type: 'text', placeholder: 'Name of the company involved' },
                { id: 'dyn_opposing_party', label: 'Opposing Party', type: 'text', placeholder: 'Name of other party / company' }
            ],
            subTypeFields: {
                'Contract / Commercial Dispute': [
                    { id: 'dyn_contract_date', label: 'Contract Date', type: 'date' },
                    { id: 'dyn_dispute_nature', label: 'Nature of Dispute', type: 'text', placeholder: 'Brief description' },
                    { id: 'dyn_amount', label: 'Claim Amount (₹)', type: 'number', placeholder: 'e.g. 1000000' }
                ],
                'Shareholder / Director Dispute': [
                    { id: 'dyn_shareholding', label: 'Shareholding %', type: 'text', placeholder: 'e.g. 25%' },
                    { id: 'dyn_position', label: 'Position Held', type: 'text', placeholder: 'e.g. Director, Shareholder' },
                    { id: 'dyn_dispute_nature', label: 'Nature of Dispute', type: 'text', placeholder: 'Brief description' }
                ],
                'Insolvency (IBC / NCLT)': [
                    { id: 'dyn_nclt_bench', label: 'NCLT Bench', type: 'text', placeholder: 'e.g. New Delhi' },
                    { id: 'dyn_nature', label: 'Nature', type: 'select', options: ['CIRP','Liquidation','Pre-IBC Settlement','Other'] },
                    { id: 'dyn_amount', label: 'Claim / Default Amount (₹)', type: 'number', placeholder: 'Amount' }
                ],
                'Merger & Acquisition': [
                    { id: 'dyn_target', label: 'Target / Acquirer Company', type: 'text', placeholder: 'Company name' },
                    { id: 'dyn_txn_type', label: 'Transaction Type', type: 'select', options: ['Merger','Acquisition','Demerger','Amalgamation'] },
                    { id: 'dyn_value', label: 'Estimated Value (₹)', type: 'number', placeholder: 'Transaction value' }
                ],
                'Partnership Dispute': [
                    { id: 'dyn_firm_name', label: 'Firm Name', type: 'text', placeholder: 'Name of partnership firm' },
                    { id: 'dyn_partners', label: 'Partner Names', type: 'text', placeholder: 'Names of partners' },
                    { id: 'dyn_since', label: 'Partnership Since', type: 'date' }
                ],
                'Regulatory / Compliance': [
                    { id: 'dyn_regulator', label: 'Regulator', type: 'select', options: ['SEBI','RBI','MCA','CCI','Other'] },
                    { id: 'dyn_proceedings', label: 'Nature of Proceedings', type: 'text', placeholder: 'Brief description' }
                ]
            }
        },
        'LAB': {
            label: 'Labour & Employment',
            subType: { id: 'dyn_sub_type', label: 'Type of Labour Matter', required: true,
                options: ['Wrongful Termination','Unpaid Salary / Dues','Sexual Harassment (POSH)','PF / ESI Dispute','Industrial Dispute','Discrimination','Other'] },
            commonFields: [
                { id: 'dyn_employer', label: 'Employer / Company Name', type: 'text', placeholder: 'Name of employer' },
                { id: 'dyn_court_name', label: 'Labour Court / Tribunal', type: 'text', placeholder: 'e.g. Labour Court, Faridabad' }
            ],
            subTypeFields: {
                'Wrongful Termination': [
                    { id: 'dyn_joining_date', label: 'Date of Joining', type: 'date' },
                    { id: 'dyn_termination_date', label: 'Date of Termination', type: 'date' },
                    { id: 'dyn_designation', label: 'Last Designation', type: 'text', placeholder: 'e.g. Senior Manager' },
                    { id: 'dyn_salary', label: 'Last Salary (₹/month)', type: 'number', placeholder: 'e.g. 80000' }
                ],
                'Unpaid Salary / Dues': [
                    { id: 'dyn_amount', label: 'Outstanding Amount (₹)', type: 'number', placeholder: 'e.g. 200000' },
                    { id: 'dyn_period', label: 'Period Unpaid', type: 'text', placeholder: 'e.g. Jan 2024 – Mar 2024' },
                    { id: 'dyn_last_working', label: 'Last Working Date', type: 'date' }
                ],
                'Sexual Harassment (POSH)': [
                    { id: 'dyn_icc', label: 'ICC Constituted', type: 'select', options: ['Yes','No','Unknown'] },
                    { id: 'dyn_complaint_date', label: 'Complaint Date', type: 'date' }
                ],
                'PF / ESI Dispute': [
                    { id: 'dyn_uan', label: 'UAN / PF Number', type: 'text', placeholder: 'e.g. 100123456789' },
                    { id: 'dyn_amount', label: 'Disputed Amount (₹)', type: 'number', placeholder: 'Amount' },
                    { id: 'dyn_issue', label: 'Issue', type: 'text', placeholder: 'e.g. Non-deposit, Wrong calculation' }
                ],
                'Industrial Dispute': [
                    { id: 'dyn_union', label: 'Union Name', type: 'text', placeholder: 'Trade union name (if any)' },
                    { id: 'dyn_dispute_nature', label: 'Nature of Dispute', type: 'text', placeholder: 'Brief description' },
                    { id: 'dyn_workmen', label: 'Workmen Count', type: 'number', placeholder: 'Number affected' }
                ],
                'Discrimination': [
                    { id: 'dyn_ground', label: 'Ground', type: 'select', options: ['Gender','Caste','Religion','Disability','Age','Other'] },
                    { id: 'dyn_details', label: 'Details', type: 'text', placeholder: 'Brief description of discrimination' }
                ]
            }
        },
        'CONS': {
            label: 'Consumer Law',
            subType: { id: 'dyn_sub_type', label: 'Type of Consumer Matter', required: true,
                options: ['Defective Product','Deficient Service','Unfair Trade Practice','E-Commerce Dispute','Insurance Claim Rejection','Medical Negligence','Other'] },
            commonFields: [
                { id: 'dyn_date_of_incidence', label: 'Date of Complaint / Incident', type: 'date' },
                { id: 'dyn_forum', label: 'Consumer Forum / Commission', type: 'text', placeholder: 'e.g. District Consumer Forum, Delhi' }
            ],
            subTypeFields: {
                'Defective Product': [
                    { id: 'dyn_product', label: 'Product Name', type: 'text', placeholder: 'Name of the product' },
                    { id: 'dyn_seller', label: 'Manufacturer / Seller', type: 'text', placeholder: 'Company name' },
                    { id: 'dyn_purchase_date', label: 'Purchase Date', type: 'date' },
                    { id: 'dyn_amount', label: 'Invoice Amount (₹)', type: 'number', placeholder: 'Amount paid' },
                    { id: 'dyn_defect', label: 'Defect Description', type: 'text', placeholder: 'What is wrong with the product' }
                ],
                'Deficient Service': [
                    { id: 'dyn_provider', label: 'Service Provider', type: 'text', placeholder: 'Company / person name' },
                    { id: 'dyn_service_type', label: 'Service Type', type: 'text', placeholder: 'e.g. Telecom, Banking, Education' },
                    { id: 'dyn_amount', label: 'Amount Paid (₹)', type: 'number', placeholder: 'Amount' }
                ],
                'Unfair Trade Practice': [
                    { id: 'dyn_company', label: 'Company Name', type: 'text', placeholder: 'Name of company' },
                    { id: 'dyn_practice', label: 'Nature of Practice', type: 'text', placeholder: 'Description of unfair practice' },
                    { id: 'dyn_loss', label: 'Loss Incurred (₹)', type: 'number', placeholder: 'Amount' }
                ],
                'E-Commerce Dispute': [
                    { id: 'dyn_platform', label: 'Platform Name', type: 'text', placeholder: 'e.g. Amazon, Flipkart' },
                    { id: 'dyn_order_no', label: 'Order Number', type: 'text', placeholder: 'Order ID' },
                    { id: 'dyn_amount', label: 'Order Amount (₹)', type: 'number', placeholder: 'Amount' },
                    { id: 'dyn_issue', label: 'Issue', type: 'text', placeholder: 'e.g. Non-delivery, Wrong product' }
                ],
                'Insurance Claim Rejection': [
                    { id: 'dyn_insurer', label: 'Insurance Company', type: 'text', placeholder: 'Name of insurer' },
                    { id: 'dyn_policy_no', label: 'Policy Number', type: 'text', placeholder: 'Policy no.' },
                    { id: 'dyn_claim_amount', label: 'Claim Amount (₹)', type: 'number', placeholder: 'Amount claimed' },
                    { id: 'dyn_rejection_date', label: 'Rejection Date', type: 'date' },
                    { id: 'dyn_reason', label: 'Reason for Rejection', type: 'text', placeholder: 'As stated by insurer' }
                ],
                'Medical Negligence': [
                    { id: 'dyn_hospital', label: 'Hospital / Doctor Name', type: 'text', placeholder: 'Name of hospital or doctor' },
                    { id: 'dyn_treatment_date', label: 'Treatment Date', type: 'date' },
                    { id: 'dyn_negligence', label: 'Nature of Negligence', type: 'text', placeholder: 'Brief description' },
                    { id: 'dyn_damages', label: 'Compensation Claimed (₹)', type: 'number', placeholder: 'Amount' }
                ]
            }
        },
        'BANK': {
            label: 'Banking & Finance',
            subType: { id: 'dyn_sub_type', label: 'Type of Banking Matter', required: true,
                options: ['Cheque Bounce (S.138 NI Act)','Loan Recovery / Default','SARFAESI Proceedings','Banking Fraud','Credit / Debit Card Dispute','DRT Proceedings','Other'] },
            commonFields: [
                { id: 'dyn_bank_name', label: 'Bank / NBFC Name', type: 'text', placeholder: 'Name of bank or financial institution' },
                { id: 'dyn_account_no', label: 'Account / Loan Number', type: 'text', placeholder: 'Account or loan number' }
            ],
            subTypeFields: {
                'Cheque Bounce (S.138 NI Act)': [
                    { id: 'dyn_cheque_no', label: 'Cheque Number', type: 'text', placeholder: 'Cheque no.' },
                    { id: 'dyn_cheque_date', label: 'Cheque Date', type: 'date' },
                    { id: 'dyn_amount', label: 'Cheque Amount (₹)', type: 'number', placeholder: 'Amount' },
                    { id: 'dyn_dishonour_date', label: 'Dishonour Date', type: 'date' },
                    { id: 'dyn_notice_date', label: 'Legal Notice Sent Date', type: 'date' }
                ],
                'Loan Recovery / Default': [
                    { id: 'dyn_loan_type', label: 'Loan Type', type: 'select', options: ['Home Loan','Personal Loan','Business Loan','Vehicle Loan','Gold Loan','Other'] },
                    { id: 'dyn_outstanding', label: 'Outstanding Amount (₹)', type: 'number', placeholder: 'Amount due' },
                    { id: 'dyn_last_emi', label: 'Last EMI Date', type: 'date' }
                ],
                'SARFAESI Proceedings': [
                    { id: 'dyn_notice_date', label: 'S.13(2) Notice Date', type: 'date' },
                    { id: 'dyn_property', label: 'Property Under Auction', type: 'text', placeholder: 'Property details' },
                    { id: 'dyn_outstanding', label: 'Outstanding Amount (₹)', type: 'number', placeholder: 'Amount' }
                ],
                'Banking Fraud': [
                    { id: 'dyn_fraud_amount', label: 'Fraud Amount (₹)', type: 'number', placeholder: 'Amount defrauded' },
                    { id: 'dyn_fraud_type', label: 'Fraud Type', type: 'select', options: ['Online Fraud','Identity Theft','Phishing','Unauthorised Transaction','Other'] },
                    { id: 'dyn_fir', label: 'FIR Filed', type: 'select', options: ['Yes','No'] }
                ],
                'Credit / Debit Card Dispute': [
                    { id: 'dyn_card_last4', label: 'Card Last 4 Digits', type: 'text', placeholder: 'e.g. 4567' },
                    { id: 'dyn_amount', label: 'Disputed Amount (₹)', type: 'number', placeholder: 'Amount' },
                    { id: 'dyn_txn_date', label: 'Transaction Date', type: 'date' }
                ],
                'DRT Proceedings': [
                    { id: 'dyn_drt_case', label: 'DRT Case Number', type: 'text', placeholder: 'Case no.' },
                    { id: 'dyn_drt_location', label: 'DRT Location', type: 'text', placeholder: 'e.g. DRT-I, Delhi' },
                    { id: 'dyn_amount', label: 'Amount (₹)', type: 'number', placeholder: 'Claim amount' }
                ]
            }
        },
        'TAX': {
            label: 'Taxation',
            subType: { id: 'dyn_sub_type', label: 'Type of Tax Matter', required: true,
                options: ['Income Tax Assessment / Appeal','GST Dispute','Property Tax','Customs & Excise','Tax Evasion Defence','TDS / Refund Issue','Other'] },
            commonFields: [
                { id: 'dyn_pan_gstin', label: 'PAN / GSTIN', type: 'text', placeholder: 'PAN or GSTIN number' },
                { id: 'dyn_amount', label: 'Disputed Amount (₹)', type: 'number', placeholder: 'Tax amount in dispute' }
            ],
            subTypeFields: {
                'Income Tax Assessment / Appeal': [
                    { id: 'dyn_ay', label: 'Assessment Year', type: 'text', placeholder: 'e.g. 2024-25' },
                    { id: 'dyn_section', label: 'Section', type: 'select', options: ['143(1)','143(3)','144','147','148','263','Other'] },
                    { id: 'dyn_authority', label: 'Authority / Forum', type: 'select', options: ['CIT(A)','ITAT','High Court','Other'] }
                ],
                'GST Dispute': [
                    { id: 'dyn_period', label: 'Tax Period', type: 'text', placeholder: 'e.g. Apr 2024 – Mar 2025' },
                    { id: 'dyn_nature', label: 'Nature', type: 'select', options: ['Input Credit Denial','Classification','Penalty','Demand','Refund','Other'] }
                ],
                'Property Tax': [
                    { id: 'dyn_property_address', label: 'Property Address', type: 'text', placeholder: 'Address of property' },
                    { id: 'dyn_authority', label: 'Municipal Authority', type: 'text', placeholder: 'e.g. MCD, Noida Authority' },
                    { id: 'dyn_period', label: 'Period', type: 'text', placeholder: 'e.g. 2020-2024' }
                ],
                'Customs & Excise': [
                    { id: 'dyn_ie_code', label: 'IE Code / CHA', type: 'text', placeholder: 'Import-Export code' },
                    { id: 'dyn_bill_no', label: 'Bill of Entry / Shipping Bill', type: 'text', placeholder: 'Document no.' }
                ],
                'Tax Evasion Defence': [
                    { id: 'dyn_charges', label: 'Prosecution Section', type: 'text', placeholder: 'e.g. Section 276C' },
                    { id: 'dyn_authority', label: 'Authority', type: 'text', placeholder: 'Investigating authority' }
                ],
                'TDS / Refund Issue': [
                    { id: 'dyn_ay', label: 'Assessment Year', type: 'text', placeholder: 'e.g. 2024-25' },
                    { id: 'dyn_refund_amount', label: 'Refund Amount (₹)', type: 'number', placeholder: 'Expected refund' },
                    { id: 'dyn_status', label: 'Current Status', type: 'text', placeholder: 'e.g. Pending, Partially processed' }
                ]
            }
        },
        'IP': {
            label: 'Intellectual Property',
            subType: { id: 'dyn_sub_type', label: 'Type of IP Matter', required: true,
                options: ['Trademark Infringement','Copyright Infringement','Patent Filing / Dispute','Design Registration','Trade Secret','Domain Name Dispute','Other'] },
            commonFields: [
                { id: 'dyn_date_of_incidence', label: 'Date of Filing / Infringement', type: 'date' },
                { id: 'dyn_opposing_party', label: 'Opposing / Infringing Party', type: 'text', placeholder: 'Name of other party' }
            ],
            subTypeFields: {
                'Trademark Infringement': [
                    { id: 'dyn_tm_name', label: 'Trademark Name / No.', type: 'text', placeholder: 'Trademark or registration no.' },
                    { id: 'dyn_class', label: 'Trademark Class', type: 'text', placeholder: 'e.g. Class 25' },
                    { id: 'dyn_infringement', label: 'Nature of Infringement', type: 'text', placeholder: 'Brief description' }
                ],
                'Copyright Infringement': [
                    { id: 'dyn_work_title', label: 'Work Title', type: 'text', placeholder: 'Title of copyrighted work' },
                    { id: 'dyn_reg_no', label: 'Registration Number', type: 'text', placeholder: 'Copyright reg. no.' },
                    { id: 'dyn_author', label: 'Author / Owner', type: 'text', placeholder: 'Name of author' }
                ],
                'Patent Filing / Dispute': [
                    { id: 'dyn_patent_no', label: 'Patent / Application No.', type: 'text', placeholder: 'Patent number' },
                    { id: 'dyn_invention', label: 'Invention Title', type: 'text', placeholder: 'Title of invention' }
                ],
                'Design Registration': [
                    { id: 'dyn_design_name', label: 'Design Name', type: 'text', placeholder: 'Name of design' },
                    { id: 'dyn_reg_no', label: 'Registration Number', type: 'text', placeholder: 'Design reg. no.' },
                    { id: 'dyn_class', label: 'Design Class', type: 'text', placeholder: 'Locarno class' }
                ],
                'Trade Secret': [
                    { id: 'dyn_secret_nature', label: 'Nature of Trade Secret', type: 'text', placeholder: 'Brief description' },
                    { id: 'dyn_nda', label: 'NDA / Agreement Exists', type: 'select', options: ['Yes','No'] }
                ],
                'Domain Name Dispute': [
                    { id: 'dyn_domain', label: 'Domain Name', type: 'text', placeholder: 'e.g. example.com' },
                    { id: 'dyn_registrant', label: 'Registrant', type: 'text', placeholder: 'Domain registrant name' },
                    { id: 'dyn_dp_forum', label: 'Forum', type: 'select', options: ['INDRP','WIPO','ICANN','Other'] }
                ]
            }
        },
        'CONST': {
            label: 'Constitutional & Writ',
            subType: { id: 'dyn_sub_type', label: 'Type of Writ / Constitutional Matter', required: true,
                options: ['Habeas Corpus','Mandamus','Certiorari','Prohibition','Quo Warranto','Fundamental Rights Violation','PIL','Other'] },
            commonFields: [
                { id: 'dyn_date_of_incidence', label: 'Date of Filing / Violation', type: 'date' },
                { id: 'dyn_authority', label: 'Authority / Respondent', type: 'text', placeholder: 'Govt. body / authority name' },
                { id: 'dyn_court_name', label: 'Court', type: 'text', placeholder: 'e.g. High Court Delhi' }
            ],
            subTypeFields: {
                'Habeas Corpus': [
                    { id: 'dyn_detained', label: 'Detained Person Name', type: 'text', placeholder: 'Name of detained person' },
                    { id: 'dyn_detaining_auth', label: 'Detaining Authority', type: 'text', placeholder: 'Authority / place of detention' },
                    { id: 'dyn_detention_date', label: 'Date of Detention', type: 'date' }
                ],
                'Mandamus': [
                    { id: 'dyn_duty', label: 'Duty to be Performed', type: 'text', placeholder: 'What should the authority do' },
                    { id: 'dyn_relief', label: 'Relief Sought', type: 'text', placeholder: 'Specific relief requested' }
                ],
                'Certiorari': [
                    { id: 'dyn_tribunal', label: 'Court / Tribunal', type: 'text', placeholder: 'Whose order is challenged' },
                    { id: 'dyn_order_date', label: 'Order Date', type: 'date' }
                ],
                'Prohibition': [
                    { id: 'dyn_tribunal', label: 'Court / Tribunal', type: 'text', placeholder: 'Proceedings to be restrained' }
                ],
                'Fundamental Rights Violation': [
                    { id: 'dyn_right', label: 'Fundamental Right', type: 'select', options: ['Article 14 (Equality)','Article 19 (Freedom)','Article 21 (Life & Liberty)','Article 25 (Religion)','Other'] },
                    { id: 'dyn_violation', label: 'Nature of Violation', type: 'text', placeholder: 'How the right was violated' }
                ],
                'PIL': [
                    { id: 'dyn_pi_issue', label: 'Issue / Cause', type: 'text', placeholder: 'Public interest issue' },
                    { id: 'dyn_affected', label: 'Affected Class', type: 'text', placeholder: 'Who is affected' },
                    { id: 'dyn_relief', label: 'Relief Sought', type: 'text', placeholder: 'Specific relief requested' }
                ]
            }
        },
        'REV': {
            label: 'Revenue & Land',
            subType: { id: 'dyn_sub_type', label: 'Type of Revenue Matter', required: true,
                options: ['Mutation / Inheritance','Land Acquisition Compensation','Revenue Record Correction','Consolidation Dispute','Other'] },
            commonFields: [
                { id: 'dyn_khasra', label: 'Khasra / Khata / Plot No.', type: 'text', placeholder: 'Land identification number' },
                { id: 'dyn_village', label: 'Village', type: 'text', placeholder: 'Village name' },
                { id: 'dyn_tehsil', label: 'Tehsil / District', type: 'text', placeholder: 'Tehsil and district' }
            ],
            subTypeFields: {
                'Mutation / Inheritance': [
                    { id: 'dyn_prev_owner', label: 'Previous Owner', type: 'text', placeholder: 'Name of previous owner' },
                    { id: 'dyn_basis', label: 'Basis of Claim', type: 'select', options: ['Sale Deed','Will','Inheritance','Gift','Other'] }
                ],
                'Land Acquisition Compensation': [
                    { id: 'dyn_award_no', label: 'Award Number', type: 'text', placeholder: 'Compensation award no.' },
                    { id: 'dyn_land_area', label: 'Land Area', type: 'text', placeholder: 'e.g. 2 bigha' },
                    { id: 'dyn_comp_offered', label: 'Compensation Offered (₹)', type: 'number', placeholder: 'Amount offered' },
                    { id: 'dyn_market_value', label: 'Claimed Market Value (₹)', type: 'number', placeholder: 'Value claimed' }
                ],
                'Revenue Record Correction': [
                    { id: 'dyn_doc_type', label: 'Document Type', type: 'text', placeholder: 'e.g. Jamabandi, Girdawari' },
                    { id: 'dyn_error', label: 'Error Details', type: 'text', placeholder: 'What needs correction' }
                ],
                'Consolidation Dispute': [
                    { id: 'dyn_plot_no', label: 'Plot Number', type: 'text', placeholder: 'Consolidated plot no.' },
                    { id: 'dyn_claimant', label: 'Claimant Details', type: 'text', placeholder: 'Name of other claimant' }
                ]
            }
        },
        'MACT': {
            label: 'Motor Accident (MACT)',
            subType: { id: 'dyn_sub_type', label: 'Type of Claim', required: true,
                options: ['Fatal Accident','Permanent Disability','Temporary Disability','Property Damage','Hit and Run','Other'] },
            commonFields: [
                { id: 'dyn_date_of_incidence', label: 'Date of Accident', type: 'date' },
                { id: 'dyn_accident_location', label: 'Accident Location', type: 'text', placeholder: 'Where the accident occurred' },
                { id: 'dyn_vehicle_no', label: 'Offending Vehicle Number', type: 'text', placeholder: 'e.g. DL 01 AB 1234' },
                { id: 'dyn_insurance', label: 'Insurance Company', type: 'text', placeholder: 'Name of insurer' }
            ],
            subTypeFields: {
                'Fatal Accident': [
                    { id: 'dyn_deceased', label: 'Deceased Name', type: 'text', placeholder: 'Name of deceased' },
                    { id: 'dyn_age', label: 'Age at Death', type: 'number', placeholder: 'Age' },
                    { id: 'dyn_occupation', label: 'Occupation', type: 'text', placeholder: 'e.g. Business, Govt. service' },
                    { id: 'dyn_income', label: 'Monthly Income (₹)', type: 'number', placeholder: 'e.g. 40000' },
                    { id: 'dyn_dependents', label: 'No. of Dependents', type: 'number', placeholder: 'e.g. 4' }
                ],
                'Permanent Disability': [
                    { id: 'dyn_disability_pct', label: 'Disability %', type: 'number', placeholder: 'e.g. 45' },
                    { id: 'dyn_injury', label: 'Nature of Injury', type: 'text', placeholder: 'e.g. Fracture, Amputation' },
                    { id: 'dyn_hospital', label: 'Treating Hospital', type: 'text', placeholder: 'Hospital name' }
                ],
                'Temporary Disability': [
                    { id: 'dyn_injury', label: 'Nature of Injury', type: 'text', placeholder: 'e.g. Fracture, Soft tissue' },
                    { id: 'dyn_treatment_period', label: 'Treatment Period', type: 'text', placeholder: 'e.g. 3 months' },
                    { id: 'dyn_income_loss', label: 'Loss of Income (₹)', type: 'number', placeholder: 'Amount lost' }
                ],
                'Property Damage': [
                    { id: 'dyn_claimant_vehicle', label: "Claimant's Vehicle No.", type: 'text', placeholder: 'Vehicle number' },
                    { id: 'dyn_damage_amount', label: 'Damage Amount (₹)', type: 'number', placeholder: 'Repair / loss amount' }
                ],
                'Hit and Run': [
                    { id: 'dyn_fir', label: 'FIR Filed', type: 'select', options: ['Yes','No'] },
                    { id: 'dyn_witnesses', label: 'Witnesses Available', type: 'select', options: ['Yes','No'] }
                ]
            }
        },
        'MERITS': {
            label: 'Merits Review',
            subType: { id: 'dyn_sub_type', label: 'Type of Review', required: true,
                options: ['Visa Refusal Review','Permit Cancellation Review','Deportation Order Review','Other'] },
            commonFields: [
                { id: 'dyn_date_of_incidence', label: 'Decision Date', type: 'date' },
                { id: 'dyn_application_no', label: 'Application / File Number', type: 'text', placeholder: 'Reference number' },
                { id: 'dyn_review_body', label: 'Review Body', type: 'text', placeholder: 'e.g. AAT, ITAT' }
            ],
            subTypeFields: {
                'Visa Refusal Review': [
                    { id: 'dyn_visa_type', label: 'Visa Type', type: 'text', placeholder: 'e.g. Student, Work' },
                    { id: 'dyn_grounds', label: 'Grounds of Refusal', type: 'text', placeholder: 'Reason stated' }
                ],
                'Permit Cancellation Review': [
                    { id: 'dyn_permit_type', label: 'Permit Type', type: 'text', placeholder: 'Type of permit' },
                    { id: 'dyn_cancel_date', label: 'Cancellation Date', type: 'date' }
                ],
                'Deportation Order Review': [
                    { id: 'dyn_order_date', label: 'Order Date', type: 'date' },
                    { id: 'dyn_grounds', label: 'Grounds', type: 'text', placeholder: 'Basis of deportation' },
                    { id: 'dyn_deadline', label: 'Review Deadline', type: 'date' }
                ]
            }
        },
        'JR': {
            label: 'Judicial Review',
            subType: { id: 'dyn_sub_type', label: 'Type of Judicial Review', required: true,
                options: ['Administrative Decision','Tribunal Order','Statutory Body Decision','Other'] },
            commonFields: [
                { id: 'dyn_date_of_incidence', label: 'Decision / Order Date', type: 'date' },
                { id: 'dyn_authority', label: 'Authority / Body', type: 'text', placeholder: 'Whose decision is challenged' },
                { id: 'dyn_court_name', label: 'Court', type: 'text', placeholder: 'e.g. High Court' }
            ],
            subTypeFields: {
                'Administrative Decision': [
                    { id: 'dyn_decision', label: 'Decision Challenged', type: 'text', placeholder: 'Brief description' },
                    { id: 'dyn_grounds', label: 'Grounds for Review', type: 'text', placeholder: 'e.g. Violation of natural justice' }
                ],
                'Tribunal Order': [
                    { id: 'dyn_tribunal', label: 'Tribunal Name', type: 'text', placeholder: 'Name of tribunal' },
                    { id: 'dyn_order_no', label: 'Order Number', type: 'text', placeholder: 'Order reference' },
                    { id: 'dyn_grounds', label: 'Grounds for Review', type: 'text', placeholder: 'Brief grounds' }
                ],
                'Statutory Body Decision': [
                    { id: 'dyn_body', label: 'Statutory Body', type: 'text', placeholder: 'Name of body' },
                    { id: 'dyn_section', label: 'Section / Act', type: 'text', placeholder: 'Relevant section' },
                    { id: 'dyn_relief', label: 'Relief Sought', type: 'text', placeholder: 'What relief is requested' }
                ]
            }
        },
        'NOICC': {
            label: 'Notice of Intention to Consider Cancellation',
            subType: { id: 'dyn_sub_type', label: 'Type of NOICC', required: true,
                options: ['Visa Cancellation','Character / Conduct Grounds','Compliance Failure','Other'] },
            commonFields: [
                { id: 'dyn_notice_date', label: 'Notice Date', type: 'date' },
                { id: 'dyn_response_deadline', label: 'Response Deadline', type: 'date' },
                { id: 'dyn_authority', label: 'Issuing Authority', type: 'text', placeholder: 'Authority name' }
            ],
            subTypeFields: {
                'Visa Cancellation': [
                    { id: 'dyn_visa_type', label: 'Visa Type', type: 'text', placeholder: 'Type of visa' },
                    { id: 'dyn_grounds', label: 'Grounds', type: 'text', placeholder: 'Stated grounds for cancellation' }
                ],
                'Character / Conduct Grounds': [
                    { id: 'dyn_specific_grounds', label: 'Specific Grounds', type: 'text', placeholder: 'Details of character grounds' }
                ],
                'Compliance Failure': [
                    { id: 'dyn_condition', label: 'Condition Breached', type: 'text', placeholder: 'Which condition was breached' }
                ]
            }
        },
        'IMM': {
            label: 'Immigration Matter',
            subType: { id: 'dyn_sub_type', label: 'Type of Immigration Matter', required: true,
                options: ['Work Permit / Employment Visa','Permanent Residency','Student Visa','Visa Refusal / Appeal','Deportation / Removal','Citizenship / Naturalisation','Other'] },
            commonFields: [
                { id: 'dyn_country', label: 'Country', type: 'text', placeholder: 'Destination country' },
                { id: 'dyn_application_no', label: 'Application / File Number', type: 'text', placeholder: 'e.g. IMM/2024/00123' },
                { id: 'dyn_authority', label: 'Authority / Department', type: 'text', placeholder: 'e.g. FRRO, Embassy' }
            ],
            subTypeFields: {
                'Work Permit / Employment Visa': [
                    { id: 'dyn_employer', label: 'Employer Name', type: 'text', placeholder: 'Employer in destination country' },
                    { id: 'dyn_visa_type', label: 'Visa Type', type: 'text', placeholder: 'Specific visa category' },
                    { id: 'dyn_application_date', label: 'Application Date', type: 'date' }
                ],
                'Permanent Residency': [
                    { id: 'dyn_stream', label: 'Stream / Program', type: 'text', placeholder: 'e.g. Express Entry, PNP' },
                    { id: 'dyn_application_date', label: 'Application Date', type: 'date' },
                    { id: 'dyn_residence_period', label: 'Period of Residence', type: 'text', placeholder: 'e.g. 3 years' }
                ],
                'Student Visa': [
                    { id: 'dyn_institution', label: 'Institution Name', type: 'text', placeholder: 'University / college name' },
                    { id: 'dyn_course', label: 'Course / Program', type: 'text', placeholder: 'e.g. MBA, B.Tech' },
                    { id: 'dyn_application_date', label: 'Application Date', type: 'date' }
                ],
                'Visa Refusal / Appeal': [
                    { id: 'dyn_visa_type', label: 'Visa Type', type: 'text', placeholder: 'Type of visa refused' },
                    { id: 'dyn_refusal_date', label: 'Refusal Date', type: 'date' },
                    { id: 'dyn_grounds', label: 'Grounds of Refusal', type: 'text', placeholder: 'Reason given' }
                ],
                'Deportation / Removal': [
                    { id: 'dyn_order_date', label: 'Order Date', type: 'date' },
                    { id: 'dyn_grounds', label: 'Grounds', type: 'text', placeholder: 'Basis of deportation' },
                    { id: 'dyn_appeal_deadline', label: 'Appeal Deadline', type: 'date' }
                ],
                'Citizenship / Naturalisation': [
                    { id: 'dyn_application_date', label: 'Application Date', type: 'date' },
                    { id: 'dyn_residence_period', label: 'Period of Residence', type: 'text', placeholder: 'e.g. 5 years' }
                ]
            }
        }
    };

    var matterIconMapJS = {
        'CIV':   { icon: 'fa-balance-scale', color: '#4a6fa5' },
        'CRM':   { icon: 'fa-gavel',         color: '#c0392b' },
        'FAM':   { icon: 'fa-heart',         color: '#e67e22' },
        'PROP':  { icon: 'fa-home',          color: '#27ae60' },
        'CORP':  { icon: 'fa-building',      color: '#8e44ad' },
        'LAB':   { icon: 'fa-briefcase',     color: '#2980b9' },
        'CONS':  { icon: 'fa-shopping-cart', color: '#16a085' },
        'BANK':  { icon: 'fa-university',    color: '#d35400' },
        'TAX':   { icon: 'fa-calculator',    color: '#7f8c8d' },
        'IP':    { icon: 'fa-lightbulb',     color: '#f39c12' },
        'CONST': { icon: 'fa-scroll',        color: '#1a5276' },
        'REV':   { icon: 'fa-map',           color: '#117a65' },
        'MACT':  { icon: 'fa-car-crash',     color: '#922b21' },
        'MERITS':{ icon: 'fa-clipboard-list',color: '#5d6d7e' },
        'JR':    { icon: 'fa-search',        color: '#1f618d' },
        'NOICC': { icon: 'fa-bell',          color: '#b7950b' },
        'IMM':   { icon: 'fa-passport',      color: '#154360' },
    };

    function onMatterDropdownChange(sel) {
        var opt = sel.options[sel.selectedIndex];
        var matterId  = opt.value;
        var matterNick  = opt.getAttribute('data-nick') || '';
        var matterTitle = opt.getAttribute('data-title') || '';

        var preview = document.getElementById('matterDropdownPreview');
        var cta     = document.getElementById('matterDropdownCTA');

        if (!matterId) {
            preview.style.display = 'none';
            cta.style.display     = 'none';
            return;
        }

        // Show icon preview
        var iconData = matterIconMapJS[matterNick] || { icon: 'fa-folder-open', color: '#555' };
        var iconEl   = document.getElementById('matterDropdownIcon');
        var labelEl  = document.getElementById('matterDropdownLabel');
        iconEl.className = 'fas ' + iconData.icon;
        iconEl.style.color = iconData.color;
        labelEl.textContent = matterTitle;
        preview.style.display = 'flex';
        cta.style.display     = 'block';

        var dynSec = document.getElementById('matterDynamicFormSection');
        if (dynSec && dynSec.style.display !== 'none' && typeof rebuildDynOurPartyRole === 'function') {
            rebuildDynOurPartyRole();
        }
    }

    function openMatterFormFromDropdown() {
        var sel = document.getElementById('matterTypeDropdown');
        var opt = sel.options[sel.selectedIndex];
        var matterId    = opt.value;
        var matterNick  = opt.getAttribute('data-nick') || '';
        var matterTitle = opt.getAttribute('data-title') || '';
        if (!matterId) return;
        selectMatterType(parseInt(matterId, 10), matterTitle, matterNick);
    }

    function resetMatterDropdown() {
        var sel = document.getElementById('matterTypeDropdown');
        sel.value = '';
        document.getElementById('matterDropdownPreview').style.display = 'none';
        document.getElementById('matterDropdownCTA').style.display = 'none';
        clearMatterTypeSelection();
    }

    function selectMatterType(matterId, matterTitle, matterNick) {
        selectedMatterTypeId   = matterId;
        selectedMatterTypeNick = matterNick;

        // Update dynamic form title
        var iconData = matterIconMapJS[matterNick] || { icon: 'fa-folder-plus', color: '#3b5bdb' };
        document.getElementById('matterDynamicFormTitle').innerHTML =
            '<i class="fas ' + iconData.icon + '" style="color:' + iconData.color + ';margin-right:6px;"></i> New Matter: ' + matterTitle;

        // Show selected badge
        var badge = document.getElementById('selectedMatterBadge');
        if (badge) {
            badge.innerHTML = '<span style="display:inline-flex;align-items:center;gap:8px;background:#e8f0fe;color:#3b5bdb;padding:6px 14px;border-radius:20px;font-weight:600;font-size:0.9em;">' +
                '<i class="fas fa-check-circle"></i> ' + matterTitle + '</span>';
        }

        // Build matter-specific fields
        buildMatterSpecificFields(matterNick);

        var wrap = document.getElementById('dyn_opposing_parties_wrap');
        if (wrap) {
            wrap.innerHTML = '';
            dynAppendOpposingRow('', '');
        }
        rebuildDynOurPartyRole();

        // Show the dynamic form
        document.getElementById('matterDynamicFormSection').style.display = '';
        document.getElementById('matterDynamicFormSection').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function buildMatterSpecificFields(matterNick) {
        var container = document.getElementById('matterSpecificFields');
        container.innerHTML = '';
        var config = matterSpecificFieldsConfig[matterNick];

        if (!config) {
            container.innerHTML = '<div class="row"><div class="col-md-6"><div class="form-group">' +
                '<label>Date of Incident</label>' +
                '<input type="date" class="form-control dyn-select" id="dyn_date_of_incidence">' +
                '</div></div></div>';
            return;
        }

        var html = '';

        if (config.subType) {
            html += '<div style="background:#f0f4ff;border:1px solid #c5d4f5;border-radius:8px;padding:1rem 1.2rem;margin-bottom:1rem;">';
            html += '<p style="font-weight:600;color:#3b5bdb;margin-bottom:0.8rem;font-size:0.95em;"><i class="fas fa-info-circle"></i> ' + config.label + ' — Specific Details</p>';
            html += '<div class="form-group" style="margin-bottom:0.5rem;">';
            html += '<label>' + config.subType.label;
            if (config.subType.required) html += ' <span class="dyn-required">*</span>';
            html += '</label>';
            html += '<select class="form-control dyn-select" id="' + config.subType.id + '" onchange="onSubTypeChange(\'' + matterNick + '\')"' + (config.subType.required ? ' required' : '') + '>';
            html += '<option value="">— Select ' + config.subType.label + ' —</option>';
            config.subType.options.forEach(function(opt) {
                html += '<option value="' + opt + '">' + opt + '</option>';
            });
            html += '</select>';
            html += '</div></div>';
        }

        if (config.commonFields && config.commonFields.length > 0) {
            html += '<div class="row">';
            config.commonFields.forEach(function(f) {
                html += '<div class="col-md-6"><div class="form-group"><label>' + f.label + '</label>';
                if (f.type === 'select') {
                    html += '<select class="form-control dyn-select" id="' + f.id + '"><option value="">— Select —</option>';
                    (f.options || []).forEach(function(opt) { html += '<option value="' + opt + '">' + opt + '</option>'; });
                    html += '</select>';
                } else {
                    html += '<input type="' + f.type + '" class="form-control dyn-select" id="' + f.id + '" maxlength="255" placeholder="' + (f.placeholder || '') + '">';
                }
                html += '</div></div>';
            });
            html += '</div>';
        }

        html += '<div id="subTypeFieldsContainer"></div>';
        container.innerHTML = html;
    }

    function onSubTypeChange(matterNick) {
        var container = document.getElementById('subTypeFieldsContainer');
        if (!container) return;
        container.innerHTML = '';

        var config = matterSpecificFieldsConfig[matterNick];
        if (!config || !config.subTypeFields) return;

        var subTypeEl = document.getElementById('dyn_sub_type');
        if (!subTypeEl || !subTypeEl.value) return;

        var fields = config.subTypeFields[subTypeEl.value];
        if (!fields || fields.length === 0) return;

        var html = '<div style="background:#fff9f0;border:1px solid #f0d9b5;border-radius:8px;padding:1rem 1.2rem;margin-top:0.5rem;margin-bottom:0.5rem;">';
        html += '<p style="font-weight:600;color:#e67e22;margin-bottom:0.8rem;font-size:0.9em;"><i class="fas fa-clipboard-list"></i> ' + subTypeEl.value + ' — Details</p>';
        html += '<div class="row">';
        fields.forEach(function(f) {
            html += '<div class="col-md-6"><div class="form-group"><label>' + f.label + '</label>';
            if (f.type === 'select') {
                html += '<select class="form-control dyn-select" id="' + f.id + '"><option value="">— Select —</option>';
                (f.options || []).forEach(function(opt) { html += '<option value="' + opt + '">' + opt + '</option>'; });
                html += '</select>';
            } else {
                html += '<input type="' + f.type + '" class="form-control dyn-select" id="' + f.id + '" maxlength="255" placeholder="' + (f.placeholder || '') + '">';
            }
            html += '</div></div>';
        });
        html += '</div></div>';
        container.innerHTML = html;
    }

    function clearMatterTypeSelection() {
        selectedMatterTypeId   = null;
        selectedMatterTypeNick = null;
        document.getElementById('matterDynamicFormSection').style.display = 'none';
        document.getElementById('matterSpecificFields').innerHTML = '';
        document.getElementById('editAddMatterMsg2').innerHTML = '';
        var wrap = document.getElementById('dyn_opposing_parties_wrap');
        if (wrap) wrap.innerHTML = '';
        var pr = document.getElementById('dyn_our_party_role');
        if (pr) { pr.innerHTML = '<option value="">\u2014</option>'; }
    }

    async function submitDynamicMatter() {
        var msgEl = document.getElementById('editAddMatterMsg2');
        var btn = document.getElementById('dynSubmitMatterBtn');
        msgEl.innerHTML = '';

        if (!selectedMatterTypeId) {
            msgEl.innerHTML = '<div class="alert alert-warning">Please select a matter type first.</div>';
            return;
        }

        var config = matterSpecificFieldsConfig[selectedMatterTypeNick || ''];
        var subTypeEl = document.getElementById('dyn_sub_type');

        if (config && config.subType && config.subType.required) {
            if (!subTypeEl || !subTypeEl.value) {
                msgEl.innerHTML = '<div class="alert alert-warning">Please select the <strong>' + config.subType.label + '</strong> (required).</div>';
                if (subTypeEl) { subTypeEl.focus(); subTypeEl.style.borderColor = '#c0392b'; }
                return;
            }
        }

        var lpEl = document.getElementById('dyn_legal_practitioner');

        var baseCaseDetail = document.getElementById('dyn_case_detail') ? document.getElementById('dyn_case_detail').value.trim() : '';

        var oppRows = [];
        document.querySelectorAll('#dyn_opposing_parties_wrap .dyn-opp-row').forEach(function (row) {
            var n = row.querySelector('.dyn-opp-name');
            var r = row.querySelector('.dyn-opp-role');
            var name = n ? n.value.trim() : '';
            var prole = r ? r.value.trim() : '';
            if (name !== '') oppRows.push({ name: name, party_role: prole });
        });

        var fd = new FormData();
        fd.append('_token', window.editClientConfig.csrfToken);
        var clientPk = String(window.currentClientId || '{{ $fetchedData->id }}').trim();
        fd.append('client_id', clientPk);
        fd.append('matter_id', selectedMatterTypeId);
        if (lpEl && lpEl.value) fd.append('legal_practitioner', lpEl.value);
        var doi = document.getElementById('dyn_date_of_incidence');
        if (doi && doi.value) fd.append('date_of_incidence', doi.value);
        if (subTypeEl && subTypeEl.value) fd.append('incidence_type', subTypeEl.value);
        if (baseCaseDetail) fd.append('case_detail', baseCaseDetail);
        var opr = document.getElementById('dyn_our_party_role');
        if (opr && opr.value) fd.append('our_party_role', opr.value);
        fd.append('opposing_parties_json', JSON.stringify(oppRows));

        btn.disabled = true;
        try {
            var res = await fetch(window.storeLeadMatterFromEditUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': window.editClientConfig.csrfToken },
                body: fd
            });
            var data = await res.json().catch(function() { return {}; });
            if (res.ok && data.success) {
                msgEl.innerHTML = '<div class="alert alert-success">' + (data.message || 'Matter created successfully.') + '</div>';
                setTimeout(function() { window.location.reload(); }, 800);
                return;
            }
            var errText = data.message || 'Could not create matter.';
            if (data.errors) errText += ' ' + Object.values(data.errors).flat().join(' ');
            msgEl.innerHTML = '<div class="alert alert-danger">' + errText + '</div>';
        } catch (e) {
            msgEl.innerHTML = '<div class="alert alert-danger">Network error. Please try again.</div>';
        } finally {
            btn.disabled = false;
        }
    }

    // =====================================================
    // Court Hearings CRUD
    // =====================================================
    var hearingStoreUrl = @json(route('clients.courtHearings.store'));
    var hearingClientId = @json($fetchedData->id);

    async function submitHearing() {
        var msgEl = document.getElementById('hearingFormMsg');
        msgEl.innerHTML = '';
        var dateEl = document.getElementById('hearing_date');
        if (!dateEl || !dateEl.value) {
            msgEl.innerHTML = '<div class="alert alert-warning">Hearing Date is required.</div>';
            return;
        }
        var fd = new FormData();
        fd.append('_token', window.editClientConfig.csrfToken);
        fd.append('client_id', hearingClientId);
        fd.append('hearing_date', dateEl.value);
        var timeEl = document.getElementById('hearing_time');
        if (timeEl && timeEl.value) fd.append('hearing_time', timeEl.value);
        var typeEl = document.getElementById('hearing_type');
        if (typeEl && typeEl.value) fd.append('hearing_type', typeEl.value);
        var courtEl = document.getElementById('hearing_court_name');
        if (courtEl && courtEl.value.trim()) fd.append('court_name', courtEl.value.trim());
        var caseEl = document.getElementById('hearing_case_number');
        if (caseEl && caseEl.value.trim()) fd.append('case_number', caseEl.value.trim());
        var judgeEl = document.getElementById('hearing_judge_name');
        if (judgeEl && judgeEl.value.trim()) fd.append('judge_name', judgeEl.value.trim());
        var matterEl = document.getElementById('hearing_matter_id');
        if (matterEl && matterEl.value) fd.append('client_matter_id', matterEl.value);
        var statusEl = document.getElementById('hearing_status');
        if (statusEl && statusEl.value) fd.append('status', statusEl.value);
        var notesEl = document.getElementById('hearing_notes');
        if (notesEl && notesEl.value.trim()) fd.append('notes', notesEl.value.trim());

        try {
            var res = await fetch(hearingStoreUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': window.editClientConfig.csrfToken },
                body: fd
            });
            var data = await res.json().catch(function() { return {}; });
            if (res.ok && data.success) {
                msgEl.innerHTML = '<div class="alert alert-success">Hearing added successfully!</div>';
                setTimeout(function() { window.location.reload(); }, 700);
                return;
            }
            var errText = (data.message || 'Could not save hearing.');
            if (data.errors) errText += ' ' + Object.values(data.errors).flat().join(' ');
            msgEl.innerHTML = '<div class="alert alert-danger">' + errText + '</div>';
        } catch (e) {
            msgEl.innerHTML = '<div class="alert alert-danger">Network error. Please try again.</div>';
        }
    }

    async function deleteHearing(hearingId) {
        if (!confirm('Delete this court hearing record?')) return;
        var deleteUrl = window.editClientConfig.rootUrl + '/clients/court-hearings/' + hearingId + '/delete';
        try {
            var res = await fetch(deleteUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': window.editClientConfig.csrfToken }
            });
            var data = await res.json().catch(function() { return {}; });
            if (res.ok && data.success) {
                var row = document.getElementById('hearing-row-' + hearingId);
                if (row) row.remove();
                return;
            }
            alert('Could not delete hearing. ' + (data.message || ''));
        } catch (e) {
            alert('Network error. Please try again.');
        }
    }
    </script>
    @endpush
@endsection
