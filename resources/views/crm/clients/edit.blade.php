@extends('layouts.crm_client_detail_dashboard')

@php
    $latestMatterRefNo = null;
    if (isset($fetchedData) && in_array($fetchedData->type, ['client', 'lead'], true)) {
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
            background-color: #e8ecff !important;
            transition: background-color 0.35s ease;
        }
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
                    <button class="nav-item summary-nav back-btn" onclick="goBackWithRefresh()">
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

    // 1. Hide all tab panes
    $(".tab-pane").hide();
    
    // 2. Show the one corresponding to the clicked tab (using its href ID)
    var target = $(this).attr("href");
    $(target).show();
               }

            </script>

            <!-- Main Content Area -->
            <div class="main-content-area">

            <ul class="nav nav-pills client-edit-top-pills">
    <li class="active"><a data-toggle="pill" href="#home" onclick="showTab('home')">Basic Information</a></li>
    <li><a data-toggle="pill" href="#menu1" onclick="showTab('menu1')">Additional Info</a></li>
    <li><a data-toggle="pill" href="#menu2" onclick="showTab('menu2')">Matter and case details</a></li>
    <li><a data-toggle="pill" href="#menu3" onclick="showTab('menu3')">Family details</a></li>
    </ul>
  
  <div class="tab-content">
  <form  id="editClientForm" action="{{ route('clients.update') }}" method="POST">
                    @csrf
                    <input type="hidden" name="id" value="{{ $fetchedData->id }}">
                    <input type="hidden" name="type" value="{{ $fetchedData->type }}">

    <div id="home" class="tab-pane fade in active">
      <h3>Basic Information</h3>
     

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

               
    </div>
    <div id="menu1" class="tab-pane fade">
      <h3>Additional Information</h3>

                <section class="content-section" style="margin-bottom: 1.25rem;">
                    <section class="form-section">
                        <div class="section-header">
                            <h3><i class="fas fa-user-friends"></i> Refer by</h3>
                        </div>
                        <p class="text-muted" style="margin-top: 0;">Who referred this {{ $fetchedData->type === 'lead' ? 'lead' : 'client' }} (optional).</p>
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
      @endphp
      <section id="matterAndCaseSection" class="content-section matter-tab-section">
      <section class="form-section matter-tab-section__card">
          <div class="section-header matter-tab-section__header">
              <div>
                  <h3 class="matter-tab-section__title"><i class="fas fa-folder-open"></i> Matter and case details</h3>
                  <p class="matter-tab-section__subtitle text-muted">Active matters for {{ $fetchedData->first_name }} {{ $fetchedData->last_name }} ({{ $fetchedData->type === 'lead' ? 'Lead' : 'Client' }} ID: {{ $fetchedData->client_id }})</p>
              </div>
              @if(is_array($matterFormForLead ?? null))
                  <button type="button" class="btn btn-primary matter-tab-section__add-btn" onclick="openAddMatterModal()">
                      <i class="fas fa-plus"></i> Add matter
                  </button>
              @endif
          </div>
          @if($editMatterList->isEmpty())
              <div class="matter-tab-empty">
                  <div class="matter-tab-empty__icon"><i class="fas fa-briefcase"></i></div>
                  <p class="matter-tab-empty__title">No matters yet</p>
                  <p class="matter-tab-empty__hint text-muted">Create a matter to track workflow, documents, and references for this {{ $fetchedData->type === 'lead' ? 'lead' : 'client' }}.</p>
                  @if(is_array($matterFormForLead ?? null))
                      <button type="button" class="btn btn-primary matter-tab-empty__cta" onclick="openAddMatterModal()">
                          <i class="fas fa-plus"></i> Add your first matter
                      </button>
                  @endif
                  <p class="matter-tab-empty__link text-muted">
                      <a href="{{ route('clients.clientsmatterslist', array_filter(['client_id' => $fetchedData->client_id])) }}">Open full matter list</a>
                      @if($fetchedData->client_id)
                          <span> — filter: <strong>{{ $fetchedData->client_id }}</strong></span>
                      @endif
                  </p>
              </div>
          @else
              <div class="table-responsive matter-tab-table-wrap">
                  <table class="table table-hover matter-tab-table">
                      <thead>
                          <tr>
                              <th>Matter ref</th>
                              <th>Type</th>
                              <th>Stage</th>
                              <th>Status</th>
                              <th>Date of incidence</th>
                              <th>Incidence type</th>
                              <th>Case detail</th>
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
                                      ? ($cmatter->matter->title . ($cmatter->matter->nick_name ? ' (' . $cmatter->matter->nick_name . ')' : ''))
                                      : '—';
                                  $caseSnippet = trim((string) ($cmatter->case_detail ?? ''));
                                  $incidenceTypeStr = trim((string) ($cmatter->incidence_type ?? ''));
                              @endphp
                              <tr>
                                  <td>
                                      <a href="{{ $detailUrl }}" class="matter-tab-ref-link">{{ $ref !== null && $ref !== '' ? $ref : '—' }}</a>
                                  </td>
                                  <td>{{ \Illuminate\Support\Str::limit($typeLabel, 80) }}</td>
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
                                  <td>
                                      @if($incidenceTypeStr !== '')
                                          {{ \Illuminate\Support\Str::limit($incidenceTypeStr, 60) }}
                                      @else
                                          <span class="text-muted">—</span>
                                      @endif
                                  </td>
                                  <td class="matter-tab-case-cell">
                                      @if($caseSnippet !== '')
                                          <span class="matter-tab-case-preview" title="{{ e($caseSnippet) }}">{{ \Illuminate\Support\Str::limit($caseSnippet, 120) }}</span>
                                      @else
                                          <span class="text-muted">—</span>
                                      @endif
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
    <div id="menu3" class="tab-pane fade">
      <h3>Family Information</h3>
      <!-- Family Information Section -->
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
    </div>
    </form>
  </div>
               
            </div>
        </div>
    </div>

    <!-- Go to Top Button -->
    <button id="goToTopBtn" class="go-to-top-btn" onclick="scrollToTop()" title="Go to Top">
        <i class="fas fa-chevron-up"></i>
    </button>

    @if(is_array($matterFormForLead ?? null))
    <div id="addMatterModal" class="modal add-matter-modal" style="display: none; z-index: 9998;" role="dialog" aria-modal="true" aria-labelledby="addMatterModalTitle" onclick="addMatterModalBackdropClick(event)">
        <div class="modal-content add-matter-modal__content" style="max-width: 720px;" onclick="event.stopPropagation()">
            <div class="modal-header add-matter-modal__header">
                <h3 id="addMatterModalTitle" class="add-matter-modal__title">Add matter</h3>
                <button type="button" class="close-btn add-matter-modal__close" onclick="closeAddMatterModal()" aria-label="Close">&times;</button>
            </div>
            <div class="modal-body add-matter-modal__body">
                <p class="text-muted add-matter-modal__intro">Creates an active matter for {{ $fetchedData->first_name }} {{ $fetchedData->last_name }} ({{ $fetchedData->type === 'lead' ? 'Lead' : 'Client' }} ID: {{ $fetchedData->client_id }}).</p>
                <div id="editAddMatterMsg" class="add-matter-modal__msg"></div>
                <div class="row add-matter-modal__grid">
                    <div class="col-md-6 add-matter-modal__field">
                        <div class="form-group">
                            <label for="edit_add_matter_matter_id">Matter type <span class="text-danger">*</span></label>
                            <select class="form-control" id="edit_add_matter_matter_id">
                                <option value="">Select matter</option>
                                @foreach($matterFormForLead['mattersForAdd'] as $m)
                                    <option value="{{ $m->id }}">{{ $m->title }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6 add-matter-modal__field">
                        <div class="form-group">
                            <label for="edit_add_matter_office_id">Handling office</label>
                            <select class="form-control" id="edit_add_matter_office_id">
                                <option value="">Default (your office)</option>
                                @foreach($matterFormForLead['branchOffices'] as $office)
                                    <option value="{{ $office->id }}" @selected(optional(Auth::user())->office_id == $office->id)>{{ $office->office_name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6 add-matter-modal__field">
                        <div class="form-group">
                            <label for="edit_add_matter_legal_practitioner">Legal practitioner <span class="text-danger">*</span></label>
                            <select class="form-control" id="edit_add_matter_legal_practitioner">
                                <option value="">Select</option>
                                @foreach($matterFormForLead['legalPractitioners'] as $st)
                                    <option value="{{ $st->id }}">{{ $st->first_name }} {{ $st->last_name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6 add-matter-modal__field">
                        <div class="form-group">
                            <label for="edit_add_matter_person_responsible">Person responsible</label>
                            <select class="form-control" id="edit_add_matter_person_responsible">
                                <option value="">—</option>
                                @foreach($matterFormForLead['personResponsibleOptions'] as $st)
                                    <option value="{{ $st->id }}">{{ $st->first_name }} {{ $st->last_name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6 add-matter-modal__field">
                        <div class="form-group">
                            <label for="edit_add_matter_person_assisting">Person assisting</label>
                            <select class="form-control" id="edit_add_matter_person_assisting">
                                <option value="">—</option>
                                @foreach($matterFormForLead['personAssistingOptions'] as $st)
                                    <option value="{{ $st->id }}">{{ $st->first_name }} {{ $st->last_name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6 add-matter-modal__field">
                        <div class="form-group">
                            <label for="edit_add_matter_date_of_incidence">Date of incidence <small class="text-muted">(optional)</small></label>
                            <input type="date" class="form-control" id="edit_add_matter_date_of_incidence" name="date_of_incidence" autocomplete="off">
                        </div>
                    </div>
                    <div class="col-md-6 add-matter-modal__field">
                        <div class="form-group">
                            <label for="edit_add_matter_incidence_type">Incidence type <small class="text-muted">(optional)</small></label>
                            <input type="text" class="form-control" id="edit_add_matter_incidence_type" name="incidence_type" maxlength="255" placeholder="e.g. workplace, refusal, relationship breakdown">
                        </div>
                    </div>
                </div>
                <div class="row add-matter-modal__grid add-matter-modal__case-row">
                    <div class="col-md-12 add-matter-modal__field">
                        <div class="form-group">
                            <label for="edit_add_matter_case_detail">Case detail <small class="text-muted">(optional)</small></label>
                            <textarea class="form-control add-matter-modal__textarea" id="edit_add_matter_case_detail" name="case_detail" rows="4" maxlength="5000" placeholder="Brief description, instructions, or context for this matter"></textarea>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer add-matter-modal__footer">
                <button type="button" class="btn btn-secondary" onclick="closeAddMatterModal()">Cancel</button>
                <button type="button" class="btn btn-primary" id="editAddMatterSubmitBtn" onclick="submitLeadMatterFromEdit()">Create matter</button>
            </div>
        </div>
    </div>
    @endif

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

    @push('scripts')
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
            function activateMatterAndCaseDetailsTab() {
                try {
                    var qs = new URLSearchParams(window.location.search || '');
                    var wantMatter = qs.get('edit_tab') === 'matter_case' || window.location.hash === '#menu2';
                    if (!wantMatter) {
                        return;
                    }
                    var $link = $('.client-edit-top-pills a[href="#menu2"]');
                    var $pane = $('#menu2');
                    if (!$link.length || !$pane.length) {
                        return;
                    }
                    $('.client-edit-top-pills li').removeClass('active');
                    $link.closest('li').addClass('active');
                    // Panes live inside #editClientForm, not direct children of .tab-content — do not use .tab-content > .tab-pane
                    $('#editClientForm > .tab-pane').removeClass('in active').hide();
                    $pane.addClass('in active').css('display', 'block').show();
                    if (typeof $link.tab === 'function') {
                        try {
                            $link.tab('show');
                        } catch (e2) { /* BS5 / missing plugin */ }
                    }
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
                                window.setTimeout(function () {
                                    $tr.removeClass('matter-tab-row-highlight');
                                }, 5000);
                            }
                        }, 250);
                    }
                } catch (e) { /* ignore */ }
            }
            if (window.jQuery) {
                jQuery(function () {
                    activateMatterAndCaseDetailsTab();
                    window.setTimeout(activateMatterAndCaseDetailsTab, 100);
                });
            } else {
                document.addEventListener('DOMContentLoaded', function () {
                    window.setTimeout(activateMatterAndCaseDetailsTab, 0);
                    window.setTimeout(activateMatterAndCaseDetailsTab, 200);
                });
            }
        })();
    </script>
    <script src="{{asset('js/clients/english-proficiency.js')}}"></script>
    <script src="{{asset('js/address-autocomplete.js')}}"></script>
    <script src="{{asset('js/clients/address-regional-codes.js')}}"></script>
    {{-- Google Maps library removed - using backend proxy for address autocomplete --}}
    @endpush
@endsection
