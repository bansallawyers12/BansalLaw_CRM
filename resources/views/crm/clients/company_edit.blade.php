@extends('layouts.crm_client_detail_dashboard')

@php
    $latestMatterRefNo = null;
    $__companyEditIsLead = isset($fetchedData)
        && (($fetchedData->type ?? null) === 1
            || in_array(trim((string) ($fetchedData->type ?? '')), ['lead', 'l', '1'], true));
    if (isset($fetchedData) && ($fetchedData->type === 'client' || $__companyEditIsLead)) {
        $latestMatter = \App\Models\ClientMatter::where('client_id', $fetchedData->id)
            ->where('matter_status', 1)
            ->orderByDesc('id')
            ->first();

        if ($latestMatter) {
            $latestMatterRefNo = $latestMatter->client_unique_matter_no;
        }
    }
    
    // Get company data
    $company = $fetchedData->company;
    $contactPerson = $company && $company->contact_person_id ? \App\Models\Admin::find($company->contact_person_id) : null;
    $directorEmailService = app(\App\Services\CompanyDirectorEmailService::class);
    $companyPrimaryEmail = $directorEmailService->resolveCompanyPrimaryEmail($fetchedData);
    $companyDirectors = $company ? $company->directors->sortBy('sort_order')->values() : collect();
    $initialCompanyDirectors = $companyDirectors->map(function ($dir) use ($directorEmailService, $fetchedData) {
        if ($dir->directorClient) {
            $emailMeta = $directorEmailService->resolveDirectorDisplayEmail($dir->directorClient, (int) $fetchedData->id);

            return [
                'mode' => 'link',
                'director_client_id' => $dir->director_client_id,
                'first_name' => $dir->directorClient->first_name,
                'last_name' => $dir->directorClient->last_name,
                'director_name' => trim($dir->directorClient->first_name . ' ' . $dir->directorClient->last_name),
                'email' => $emailMeta['email'] ?? null,
                'is_shared' => $emailMeta['is_shared'] ?? false,
                'director_dob' => $dir->director_dob ? $dir->director_dob->format('Y-m-d') : null,
                'director_role' => $dir->director_role,
                'is_primary' => (bool) $dir->is_primary,
            ];
        }

        return [
            'mode' => 'name_only',
            'director_client_id' => null,
            'first_name' => '',
            'last_name' => '',
            'director_name' => $dir->director_name,
            'email' => null,
            'is_shared' => false,
            'director_dob' => $dir->director_dob ? $dir->director_dob->format('Y-m-d') : null,
            'director_role' => $dir->director_role,
            'is_primary' => (bool) $dir->is_primary,
        ];
    })->values()->all();
    $isTrusteeBusinessType = $company && $company->isTrusteeBusiness();
    $companyTypeForForm = old('company_type', $company ? $company->company_type : '');
    $showTrusteeFieldsInitial = \App\Models\Company::isTrusteeBusinessType($companyTypeForForm);
@endphp

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/address-autocomplete.css') }}">
    <link rel="stylesheet" href="{{asset('css/client-forms.css')}}">
    <link rel="stylesheet" href="{{asset('css/clients/edit-client-components.css')}}">
    <style>
        .tab-content { display: block !important; }
        tr.matter-tab-row-highlight td {
            background-color: #ebf3ff !important;
            transition: background-color 0.35s ease;
        }
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
        .dyn-select {
            border: 1.5px solid var(--border-color, #c8dcef) !important;
            border-radius: 6px !important;
            height: 40px;
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
    {{-- Tom Select: layout loads tom-select + ts-init.js --}}
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
                    <h3><i class="fa-solid fa-building"></i> {{ $fetchedData->type == 'lead' ? 'Edit Company Lead' : 'Edit Company Client' }} : {{ $company ? $company->company_name : 'Unnamed Company' }}</h3>
                    <div class="client-id">
                        {{ $fetchedData->type == 'lead' ? 'Lead ID' : ($fetchedData->type == 'client' ? 'Client ID' : '') }} : {{ $fetchedData->client_id }}
                    </div>
                </div>
                <nav class="nav-menu">
                    <button class="nav-item active" onclick="scrollToSection('companySection')">
                        <i class="fa-solid fa-building"></i>
                        <span>Company Information</span>
                    </button>
                    <button class="nav-item" onclick="scrollToSection('contactPersonSection')">
                        <i class="fa-solid fa-user-tie"></i>
                        <span>Contact Person</span>
                    </button>
                    <button class="nav-item" onclick="scrollToSection('directorsSection')">
                        <i class="fa-solid fa-users-cog"></i>
                        <span>Directors</span>
                    </button>
                    <button class="nav-item" onclick="scrollToSection('addressSection')">
                        <i class="fa-solid fa-location-dot"></i>
                        <span>Business Address</span>
                    </button>
                    <button class="nav-item" onclick="scrollToSection('contactsSection')">
                        <i class="fa-solid fa-phone"></i>
                        <span>Contacts</span>
                    </button>
                </nav>
                
                <!-- Back Button in Sidebar -->
                <div class="sidebar-actions">
                    <button type="button" class="nav-item summary-nav back-btn" onclick="goBackWithRefresh()">
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
                    searchContactPersonRoute: '{{ route("api.search.contact.person") }}',
                    searchAddressRoute: '{{ route("clients.searchAddressFull") }}',
                    getPlaceDetailsRoute: '{{ route("clients.getPlaceDetails") }}',
                    csrfToken: '{{ csrf_token() }}'
                };
                
                // Current client ID for excluding from search results
                window.currentClientId = '{{ $fetchedData->id }}';
                window.currentClientType = @json($fetchedData->type);
                window.latestClientMatterRef = @json($latestMatterRefNo);
                window.companyPrimaryEmail = @json($companyPrimaryEmail);
                window.initialCompanyDirectors = @json($initialCompanyDirectors);

                function showCompanyMatterTab(tabId) {
                    if (!window.jQuery) return;
                    $('.main-content-area .tab-pane').removeClass('show active').css('display', '');
                    var $pane = $('#' + tabId);
                    if ($pane.length) {
                        $pane.addClass('show active');
                    }
                    $('.client-edit-top-pills .nav-link').removeClass('active');
                    $('.client-edit-top-pills a[href="#' + tabId + '"]').addClass('active');
                }
            </script>

            <!-- Main Content Area -->
            <div class="main-content-area">
                <ul class="nav nav-pills client-edit-top-pills" role="tablist">
                    <li class="nav-item" role="presentation"><a class="nav-link active" href="#companyEditHome" role="tab" onclick="showCompanyMatterTab('companyEditHome'); return false;"><i class="fa-solid fa-building"></i> Company Profile</a></li>
                    <li class="nav-item" role="presentation"><a class="nav-link" href="#menu2" role="tab" onclick="showCompanyMatterTab('menu2'); return false;"><i class="fa-solid fa-briefcase"></i> Matter Details</a></li>
                    <li class="nav-item" role="presentation"><a class="nav-link" href="#menu4" role="tab" onclick="showCompanyMatterTab('menu4'); return false;"><i class="fa-solid fa-gavel"></i> Court Dates &amp; Hearings</a></li>
                </ul>
                <div class="tab-content">
                <form id="editCompanyForm" action="{{ route('clients.update') }}" method="POST">
                    @csrf
                    <input type="hidden" name="id" value="{{ $fetchedData->id }}">
                    <input type="hidden" name="type" value="{{ $fetchedData->type }}">

                <div id="companyEditHome" class="tab-pane fade show active" role="tabpanel">
                <!-- Company Information Section -->
                <section id="companySection" class="content-section">
                    <section class="form-section">
                        <div class="section-header">
                            <h3><i class="fa-solid fa-building"></i> Company Information</h3>
                            <div class="section-actions">
                                <button type="button" class="edit-section-btn" onclick="toggleEditMode('companyInfo')">
                                    <i class="fa-solid fa-pen"></i>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Summary View -->
                        <div id="companyInfoSummary" class="summary-view">
                            <div class="summary-grid">
                                <div class="summary-item">
                                    <span class="summary-label">Company Name:</span>
                                    <span class="summary-value">{{ $company ? $company->company_name : 'Not set' }}</span>
                                </div>
                                @php
                                    $tradingNamesDisplay = $company && $company->tradingNames->isNotEmpty()
                                        ? $company->tradingNames->pluck('trading_name')->join(', ')
                                        : ($company && $company->trading_name ? $company->trading_name : null);
                                @endphp
                                @if($tradingNamesDisplay)
                                <div class="summary-item">
                                    <span class="summary-label">Trading Name(s):</span>
                                    <span class="summary-value">{{ $tradingNamesDisplay }}</span>
                                </div>
                                @endif
                                @if($company && $company->ABN_number)
                                <div class="summary-item">
                                    <span class="summary-label">ABN:</span>
                                    <span class="summary-value">{{ $company->ABN_number }}</span>
                                </div>
                                @endif
                                @if($company && $company->ACN)
                                <div class="summary-item">
                                    <span class="summary-label">ACN:</span>
                                    <span class="summary-value">{{ $company->ACN }}</span>
                                </div>
                                @endif
                                @if($company && $company->company_type)
                                <div class="summary-item">
                                    <span class="summary-label">Business Type:</span>
                                    <span class="summary-value">{{ \App\Models\Company::businessTypeLabel($company->company_type) }}</span>
                                </div>
                                @endif
                                @if($isTrusteeBusinessType && $company->trust_name)
                                <div class="summary-item">
                                    <span class="summary-label">Trust Name:</span>
                                    <span class="summary-value">{{ $company->trust_name }}</span>
                                </div>
                                @endif
                                @if($isTrusteeBusinessType && $company->trust_abn)
                                <div class="summary-item">
                                    <span class="summary-label">ABN/ACN (trust):</span>
                                    <span class="summary-value">{{ $company->trust_abn }}</span>
                                </div>
                                @endif
                                @if($company && $company->company_website)
                                <div class="summary-item">
                                    <span class="summary-label">Website:</span>
                                    <span class="summary-value">
                                        <a href="{{ $company->company_website }}" target="_blank">{{ $company->company_website }}</a>
                                    </span>
                                </div>
                                @endif
                            </div>
                        </div>

                        <!-- Edit View -->
                        <div id="companyInfoEdit" class="edit-view hidden">
                            @php
                                $defaultHasTrading = $company && ($company->has_trading_name || $company->trading_name || ($company->tradingNames?->isNotEmpty() ?? false)) ? 1 : 0;
                            @endphp
                            <div class="content-grid">
                                <div class="form-group">
                                    <label for="companyName">Company Name <span class="text-danger">*</span></label>
                                    <input type="text" id="companyName" name="company_name" 
                                           value="{{ old('company_name', $company ? $company->company_name : '') }}" 
                                           required>
                                    @error('company_name')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                                
                                <div class="form-group">
                                    <label>Does this company have a trading name?</label>
                                    <div style="display: flex; gap: 20px; margin-top: 5px;">
                                        <label><input type="radio" name="has_trading_name" value="1" {{ old('has_trading_name', $defaultHasTrading) ? 'checked' : '' }}> Yes</label>
                                        <label><input type="radio" name="has_trading_name" value="0" {{ !old('has_trading_name', $defaultHasTrading) ? 'checked' : '' }}> No</label>
                                    </div>
                                </div>
                                <div id="tradingNamesContainer" class="form-group full-width" style="{{ !old('has_trading_name', $defaultHasTrading) ? 'display:none;' : '' }}">
                                    <label>Trading Names</label>
                                    <div id="tradingNamesList">
                                        @php
                                            $tradingNames = ($company && ($company->tradingNames?->isNotEmpty() ?? false)) ? $company->tradingNames : collect();
                                            if ($tradingNames->isEmpty() && $company && $company->trading_name) {
                                                $tradingNames = collect([(object)['trading_name' => $company->trading_name, 'is_primary' => true]]);
                                            }
                                            if ($tradingNames->isEmpty()) { $tradingNames = collect([(object)['trading_name' => '', 'is_primary' => false]]); }
                                        @endphp
                                        @foreach($tradingNames as $idx => $tn)
                                        <div class="trading-name-row" style="display: flex; gap: 10px; margin-bottom: 8px; align-items: center;">
                                            <input type="text" name="trading_names[]" value="{{ old("trading_names.{$idx}", is_object($tn) ? $tn->trading_name : $tn) }}" placeholder="Trading name" style="flex: 1;">
                                            <label><input type="radio" name="trading_name_primary" value="{{ $idx }}" {{ ($tn->is_primary ?? ($idx === 0)) ? 'checked' : '' }}> Primary</label>
                                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeTradingName(this)"><i class="fa-solid fa-xmark"></i></button>
                                        </div>
                                        @endforeach
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="addTradingName()"><i class="fa-solid fa-plus"></i> Add another</button>
                                </div>
                                
                                <div class="form-group">
                                    <label for="abn">ABN</label>
                                    <input type="text" id="abn" name="ABN_number" 
                                           value="{{ old('ABN_number', $company ? $company->ABN_number : '') }}" 
                                           placeholder="12 345 678 901"
                                           maxlength="11">
                                    <small class="form-text text-muted">11 digits (spaces will be removed automatically)</small>
                                    @error('ABN_number')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                                
                                <div class="form-group">
                                    <label for="acn">ACN</label>
                                    <input type="text" id="acn" name="ACN" 
                                           value="{{ old('ACN', $company ? $company->ACN : '') }}" 
                                           placeholder="123 456 789"
                                           maxlength="9">
                                    <small class="form-text text-muted">9 digits (spaces will be removed automatically)</small>
                                    @error('ACN')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                                
                                <div class="form-group">
                                    <label for="companyType">Business Type</label>
                                    <select id="companyType" name="company_type">
                                        <option value="">Select Business Type</option>
                                        <option value="Sole Trader" {{ old('company_type', $company ? $company->company_type : '') == 'Sole Trader' ? 'selected' : '' }}>Sole Trader</option>
                                        <option value="Partnership" {{ old('company_type', $company ? $company->company_type : '') == 'Partnership' ? 'selected' : '' }}>Partnership</option>
                                        <option value="Proprietary Company" {{ old('company_type', $company ? $company->company_type : '') == 'Proprietary Company' ? 'selected' : '' }}>Proprietary Company (Pty Ltd)</option>
                                        <option value="Public Company" {{ old('company_type', $company ? $company->company_type : '') == 'Public Company' ? 'selected' : '' }}>Public Company</option>
                                        <option value="Not-for-Profit" {{ old('company_type', $company ? $company->company_type : '') == 'Not-for-Profit' ? 'selected' : '' }}>Not-for-Profit Organization</option>
                                        <option value="Trustee" {{ \App\Models\Company::isTrusteeBusinessType(old('company_type', $company ? $company->company_type : '')) ? 'selected' : '' }}>Trustee</option>
                                        <option value="Other" {{ old('company_type', $company ? $company->company_type : '') == 'Other' ? 'selected' : '' }}>Other</option>
                                    </select>
                                </div>

                                <div id="trusteeInlineFields" class="trustee-inline-fields" style="grid-column: 1 / -1; {{ $showTrusteeFieldsInitial ? '' : 'display: none;' }}">
                                    <div class="content-grid">
                                        <div class="form-group">
                                            <label for="companyTrustName">Trust Name</label>
                                            <input type="text" id="companyTrustName" name="trust_name"
                                                   value="{{ old('trust_name', $company ? $company->trust_name : '') }}"
                                                   placeholder="Name of the trust"
                                                   @if(!$showTrusteeFieldsInitial) disabled @endif>
                                        </div>
                                        <div class="form-group">
                                            <label for="companyTrustAbnAcn">ABN/ACN</label>
                                            <input type="text" id="companyTrustAbnAcn" name="trust_abn"
                                                   value="{{ old('trust_abn', $company ? $company->trust_abn : '') }}"
                                                   placeholder="Trust ABN or ACN"
                                                   maxlength="64"
                                                   @if(!$showTrusteeFieldsInitial) disabled @endif>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="companyWebsite">Company Website</label>
                                    <input type="url" id="companyWebsite" name="company_website" 
                                           value="{{ old('company_website', $company ? $company->company_website : '') }}" 
                                           placeholder="https://www.example.com">
                                    @error('company_website')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                            <div class="edit-actions">
                                <button type="button" class="btn btn-primary" onclick="saveCompanyInfo()">Save</button>
                                <button type="button" class="btn btn-secondary" onclick="cancelEdit('companyInfo')">Cancel</button>
                            </div>
                        </div>
                    </section>
                </section>

                <!-- Primary Contact Person Section -->
                <section id="contactPersonSection" class="content-section">
                    <section class="form-section">
                        <div class="section-header">
                            <h3><i class="fa-solid fa-user-tie"></i> Primary Contact Person</h3>
                            <div class="section-actions">
                                <button type="button" class="edit-section-btn" onclick="toggleEditMode('contactPersonInfo')">
                                    <i class="fa-solid fa-pen"></i>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Summary View -->
                        <div id="contactPersonInfoSummary" class="summary-view">
                            @if($contactPerson)
                                <div class="summary-grid">
                                    <div class="summary-item">
                                        <span class="summary-label">Name:</span>
                                        <span class="summary-value">
                                            <a href="{{ route('clients.detail', base64_encode(convert_uuencode($contactPerson->id))) }}">
                                                {{ $contactPerson->first_name }} {{ $contactPerson->last_name }}
                                            </a>
                                        </span>
                                    </div>
                                    @if($company && $company->contact_person_position)
                                    <div class="summary-item">
                                        <span class="summary-label">Position:</span>
                                        <span class="summary-value">{{ $company->contact_person_position }}</span>
                                    </div>
                                    @endif
                                    @if($contactPerson->email)
                                    <div class="summary-item">
                                        <span class="summary-label">Email:</span>
                                        <span class="summary-value">
                                            <a href="mailto:{{ $contactPerson->email }}">{{ $contactPerson->email }}</a>
                                        </span>
                                    </div>
                                    @endif
                                    @if($contactPerson->phone)
                                    <div class="summary-item">
                                        <span class="summary-label">Phone:</span>
                                        <span class="summary-value">{{ $contactPerson->phone }}</span>
                                    </div>
                                    @endif
                                    @if($contactPerson->client_id)
                                    <div class="summary-item">
                                        <span class="summary-label">Client ID:</span>
                                        <span class="summary-value">{{ $contactPerson->client_id }}</span>
                                    </div>
                                    @endif
                                </div>
                            @else
                                <div class="empty-state">
                                    <p>No contact person assigned yet.</p>
                                </div>
                            @endif
                        </div>

                        <!-- Edit View -->
                        <div id="contactPersonInfoEdit" class="edit-view hidden">
                            <div class="content-grid">
                                <div class="form-group full-width">
                                    <label for="contactPersonSearch">Search Contact Person <span class="text-danger">*</span></label>
                                    <select id="contactPersonSearch" name="contact_person_id" 
                                            class="form-control crm-ts-contact-person" 
                                            data-placeholder="Type phone, email, name, or client ID to search..."
                                            style="width: 100%;">
                                        @if($contactPerson)
                                            <option value="{{ $contactPerson->id }}" selected>
                                                {{ $contactPerson->first_name }} {{ $contactPerson->last_name }} 
                                                ({{ $contactPerson->email }}) - {{ $contactPerson->phone }}
                                            </option>
                                        @endif
                                    </select>
                                    <small class="form-text text-muted">
                                        Search existing clients/leads by phone, email, name, or client ID. Selected person's details will auto-fill below.
                                    </small>
                                    @error('contact_person_id')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                                
                                <div class="form-group">
                                    <label for="contactPersonFirstName">First Name</label>
                                    <input type="text" id="contactPersonFirstName" name="contact_person_first_name" 
                                           value="{{ old('contact_person_first_name', $contactPerson ? $contactPerson->first_name : '') }}" 
                                           class="contact-person-field" readonly>
                                    <small class="form-text text-muted">Auto-filled from selected contact person</small>
                                </div>
                                
                                <div class="form-group">
                                    <label for="contactPersonLastName">Last Name</label>
                                    <input type="text" id="contactPersonLastName" name="contact_person_last_name" 
                                           value="{{ old('contact_person_last_name', $contactPerson ? $contactPerson->last_name : '') }}" 
                                           class="contact-person-field" readonly>
                                </div>
                                
                                <div class="form-group">
                                    <label for="contactPersonPosition">Position/Title</label>
                                    <input type="text" id="contactPersonPosition" name="contact_person_position" 
                                           value="{{ old('contact_person_position', $company ? $company->contact_person_position : '') }}" 
                                           placeholder="e.g., HR Manager, Director">
                                </div>
                                
                                <div class="form-group">
                                    <label for="contactPersonPhone">Phone</label>
                                    <input type="text" id="contactPersonPhone" name="contact_person_phone" 
                                           value="{{ old('contact_person_phone', $contactPerson ? $contactPerson->phone : '') }}" 
                                           class="contact-person-field" readonly>
                                    <small class="form-text text-muted">Auto-filled from selected contact person</small>
                                </div>
                                
                                <div class="form-group">
                                    <label for="contactPersonEmailDisplay">Email</label>
                                    <input type="email" id="contactPersonEmailDisplay" 
                                           value="{{ old('contact_person_email_display', $contactPerson ? $contactPerson->email : '') }}" 
                                           class="contact-person-field" readonly>
                                    <small class="form-text text-muted">Auto-filled from selected contact person</small>
                                </div>
                            </div>
                            <div class="edit-actions">
                                <button type="button" class="btn btn-primary" onclick="saveContactPersonInfo()">Save</button>
                                <button type="button" class="btn btn-secondary" onclick="cancelEdit('contactPersonInfo')">Cancel</button>
                            </div>
                        </div>
                    </section>
                </section>

                <!-- Directors Section -->
                <section id="directorsSection" class="content-section">
                    <section class="form-section">
                        <div class="section-header">
                            <h3><i class="fa-solid fa-users-cog"></i> Directors</h3>
                            <div class="section-actions">
                                <button type="button" class="edit-section-btn" onclick="toggleEditMode('directorsInfo')">
                                    <i class="fa-solid fa-pen"></i>
                                </button>
                                <button type="button" class="add-section-btn" onclick="openDirectorsEditorAndAddRow()" title="Add Director">
                                    <i class="fa-solid fa-plus"></i>
                                </button>
                            </div>
                        </div>

                        <div id="directorsInfoSummary" class="summary-view">
                            @if($companyDirectors->isNotEmpty())
                                <div class="summary-grid">
                                    @foreach($companyDirectors as $dir)
                                        @php
                                            $dirName = $dir->directorClient
                                                ? trim($dir->directorClient->first_name . ' ' . $dir->directorClient->last_name)
                                                : ($dir->director_name ?? '');
                                            $dirEmailMeta = $dir->directorClient
                                                ? $directorEmailService->resolveDirectorDisplayEmail($dir->directorClient, (int) $fetchedData->id)
                                                : null;
                                        @endphp
                                        <div class="summary-item" style="grid-column: 1 / -1;">
                                            <span class="summary-label">{{ $dirName ?: 'Unnamed director' }}</span>
                                            <span class="summary-value">
                                                {{ $dir->director_role ?: 'Director' }}
                                                @if($dir->director_dob)
                                                    — DOB: {{ $dir->director_dob->format('d/m/Y') }}
                                                @endif
                                                @if($dir->is_primary)
                                                    <span class="badge bg-primary" style="margin-left:6px;">Primary</span>
                                                @endif
                                                @if($dirEmailMeta)
                                                    — {{ $dirEmailMeta['email'] }}
                                                    @if($dirEmailMeta['is_shared'])
                                                        <span class="badge bg-secondary" style="margin-left:4px;">Company email</span>
                                                    @endif
                                                @elseif($dir->directorClient)
                                                    <span class="text-muted"> — No email on file</span>
                                                @else
                                                    <span class="text-muted"> — Name only</span>
                                                @endif
                                            </span>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="empty-state">
                                    <p>No directors recorded yet.</p>
                                </div>
                            @endif
                        </div>

                        <div id="directorsInfoEdit" class="edit-view hidden">
                            <p class="text-muted small mb-2">
                                <i class="fa-solid fa-circle-info"></i>
                                When you do not have a director's personal email, choose <strong>Use company email</strong>.
                                Use <strong>Name only</strong> for ASIC/conflict details without creating a contact record.
                            </p>
                            <div id="directorsContainer"></div>
                            <button type="button" class="add-item-btn" onclick="addDirectorRow()"><i class="fa-solid fa-circle-plus"></i> Add Director</button>
                            <div class="edit-actions">
                                <button type="button" class="btn btn-primary" onclick="saveDirectorsInfo()">Save</button>
                                <button type="button" class="btn btn-secondary" onclick="cancelEdit('directorsInfo')">Cancel</button>
                            </div>
                        </div>
                    </section>
                </section>

                <!-- Business Address Section -->
                <section id="addressSection" class="content-section">
                    <x-client-edit.address-section 
                        :clientAddresses="$clientAddresses"
                        :searchRoute="route('clients.searchAddressFull')"
                        :detailsRoute="route('clients.getPlaceDetails')"
                        :csrfToken="csrf_token()"
                    />
                </section>

                <!-- Contacts Section (Phone & Email) -->
                <section id="contactsSection" class="content-section">
                    <!-- Phone Numbers -->
                    <section class="form-section">
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
                                <div class="summary-grid">
                                    @foreach($clientContacts as $index => $contact)
                                        <div class="summary-item">
                                            <span class="summary-label">{{ $contact->contact_type }}:</span>
                                            <span class="summary-value">{{ $contact->country_code }}{{ $contact->phone }}</span>
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
                    <section class="form-section">
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
                                <div class="summary-grid">
                                    @foreach($emails as $index => $email)
                                        <div class="summary-item">
                                            <span class="summary-label">{{ $email->email_type }}:</span>
                                            <span class="summary-value">{{ $email->email }}</span>
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
                </section>

                </div>

                @include('crm.clients.partials.client-edit-matter-tab-pane')
                @include('crm.clients.partials.client-edit-court-tab-pane')

                </form>
                </div>
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
    </script>
    <script src="{{ asset('js/crm/clients/matter-assignee-modal.js') }}?v={{ time() }}"></script>
    <script>
        window.countriesData = @json($countries ?? []);
        window.storeLeadMatterFromEditUrl = @json(route('clients.storeLeadMatterFromEdit'));
    </script>
    <script src="{{ asset('js/clients/edit-client.js') }}?v={{ file_exists(public_path('js/clients/edit-client.js')) ? filemtime(public_path('js/clients/edit-client.js')) : 1 }}"></script>
    <script src="{{ asset('js/clients/address-delete.js') }}?v={{ file_exists(public_path('js/clients/address-delete.js')) ? filemtime(public_path('js/clients/address-delete.js')) : 1 }}"></script>
    <script src="{{asset('js/address-autocomplete.js')}}"></script>
    <script src="{{asset('js/clients/address-regional-codes.js')}}"></script>
    <script>
        @include('crm.clients.partials.client-edit-matter-court-scripts')
    </script>
    
    <script>
    $(document).ready(function() {
        function fillCompanyEditContactPerson(item) {
            if (!item) return;
            $('#contactPersonFirstName').val(item.first_name || '');
            $('#contactPersonLastName').val(item.last_name || '');
            $('#contactPersonPhone').val(item.phone || '');
            $('#contactPersonEmailDisplay').val(item.email || '');
            $('.contact-person-field').addClass('field-auto-filled');
        }
        function clearCompanyEditContactPerson() {
            $('#contactPersonFirstName').val('');
            $('#contactPersonLastName').val('');
            $('#contactPersonPhone').val('');
            $('#contactPersonEmailDisplay').val('');
            $('.contact-person-field').removeClass('field-auto-filled');
        }

        var cpEl = document.getElementById('contactPersonSearch');
        if (
            cpEl &&
            !cpEl.tomselect &&
            typeof TomSelect !== 'undefined' &&
            typeof initTS === 'function' &&
            typeof buildContactPersonSearchTomSelectConfig === 'function' &&
            window.editClientConfig &&
            window.editClientConfig.searchContactPersonRoute
        ) {
            var cpCfg = buildContactPersonSearchTomSelectConfig({
                url: window.editClientConfig.searchContactPersonRoute,
                excludeId: window.currentClientId,
                dropdownParent: 'body',
                placeholder: 'Type phone, email, name, or client ID to search...',
                minQueryLength: 2
            });
            cpCfg.onItemAdd = function (value) {
                var item = this.options[value] || this.options[String(value)];
                fillCompanyEditContactPerson(item);
            };
            cpCfg.onClear = function () {
                clearCompanyEditContactPerson();
            };
            initTS(cpEl, cpCfg);
        } else if (cpEl && !cpEl.tomselect && !window.editClientConfig) {
            console.warn('Contact person Tom Select: window.editClientConfig missing.');
        }

        // Format ABN/ACN input (strip non-digits)
        $('#abn, #acn').on('input', function() {
            this.value = this.value.replace(/\D/g, '');
        });
        // Has trading name toggle
        $('input[name="has_trading_name"]').on('change', function() {
            $('#tradingNamesContainer').toggle($(this).val() === '1');
        });

        function toggleTrusteeCompanyFields() {
            var v = $('#companyType').val();
            var show = (v === 'Trustee' || v === 'Trust');
            var el = $('#trusteeInlineFields');
            el.toggle(show);
            el.find('input').prop('disabled', !show);
        }
        $('#companyType').on('change', toggleTrusteeCompanyFields);
        toggleTrusteeCompanyFields();
    });

    function addTradingName() {
        const container = $('#tradingNamesList');
        const idx = container.find('.trading-name-row').length;
        const row = $('<div class="trading-name-row" style="display: flex; gap: 10px; margin-bottom: 8px; align-items: center;">' +
            '<input type="text" name="trading_names[]" placeholder="Trading name" style="flex: 1;">' +
            '<label><input type="radio" name="trading_name_primary" value="' + idx + '"> Primary</label>' +
            '<button type="button" class="btn btn-sm btn-outline-danger" onclick="removeTradingName(this)"><i class="fa-solid fa-xmark"></i></button>' +
            '</div>');
        container.append(row);
        // Update primary radio values
        container.find('.trading-name-row').each(function(i) {
            $(this).find('input[name="trading_name_primary"]').val(i);
        });
    }

    function removeTradingName(btn) {
        const container = $('#tradingNamesList');
        if (container.find('.trading-name-row').length <= 1) return;
        $(btn).closest('.trading-name-row').remove();
        $('#tradingNamesList .trading-name-row').each(function(i) {
            $(this).find('input[name="trading_name_primary"]').val(i);
        });
    }

    function saveSection(sectionName, callback) {
        const form = document.getElementById('editCompanyForm');
        const formData = new FormData(form);
        saveSectionData(sectionName, formData, function() { (callback || function(){})(); window.location.reload(); });
    }
    
    // Save functions - use saveSectionData for AJAX save (fixes broken form.submit to clients.update)
    function saveCompanyInfo() {
        const form = document.getElementById('editCompanyForm');
        const formData = new FormData(form);
        saveSectionData('companyInfo', formData, function() {
            toggleEditMode('companyInfo');
            window.location.reload();
        });
    }
    
    function saveContactPersonInfo() {
        const form = document.getElementById('editCompanyForm');
        const formData = new FormData(form);
        saveSectionData('contactPersonInfo', formData, function() {
            toggleEditMode('contactPersonInfo');
            window.location.reload();
        });
    }

    var directorRowCounter = 0;

    function escDirectorHtml(s) {
        if (s == null) return '';
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
    }

    function destroyDirectorTomSelects(container) {
        if (!container) return;
        container.querySelectorAll('.director-link-select').forEach(function (sel) {
            if (typeof window.destroyTS === 'function') window.destroyTS(sel);
        });
    }

    function initDirectorLinkSelect(selectEl, prefill) {
        if (!selectEl || typeof window.initTS !== 'function' || typeof window.buildContactPersonSearchTomSelectConfig !== 'function') {
            return;
        }
        if (typeof window.destroyTS === 'function') window.destroyTS(selectEl);
        var cfg = window.buildContactPersonSearchTomSelectConfig({
            url: window.editClientConfig.searchContactPersonRoute,
            excludeId: window.currentClientId,
            dropdownParent: 'body',
            placeholder: 'Search director by phone, email, name, or ID...'
        });
        window.initTS(selectEl, cfg);
        if (prefill && prefill.director_client_id) {
            var ts = selectEl.tomselect;
            var label = prefill.director_name || ('Director #' + prefill.director_client_id);
            if (ts) {
                ts.addOption({ id: prefill.director_client_id, text: label });
                ts.setValue(String(prefill.director_client_id), true);
            }
        }
    }

    function toggleDirectorRowMode(row, mode) {
        var linkWrap = row.querySelector('.director-link-wrap');
        var newWrap = row.querySelector('.director-new-wrap');
        var nameOnlyWrap = row.querySelector('.director-name-only-wrap');
        var emailWrap = row.querySelector('.director-email-wrap');
        var modeInput = row.querySelector('.director-mode-input');
        if (modeInput) modeInput.value = mode;

        if (linkWrap) linkWrap.style.display = mode === 'link' ? '' : 'none';
        if (newWrap) newWrap.style.display = (mode === 'company_email' || mode === 'personal') ? '' : 'none';
        if (nameOnlyWrap) nameOnlyWrap.style.display = mode === 'name_only' ? '' : 'none';
        if (emailWrap) emailWrap.style.display = mode === 'personal' ? '' : 'none';

        var companyEmailDisplay = row.querySelector('.director-company-email-display');
        if (companyEmailDisplay) {
            companyEmailDisplay.style.display = mode === 'company_email' ? '' : 'none';
        }
    }

    function addDirectorRow(prefill) {
        prefill = prefill || {};
        var container = document.getElementById('directorsContainer');
        if (!container) return;

        var idx = directorRowCounter++;
        var row = document.createElement('div');
        row.className = 'director-row border rounded p-3 mb-3';
        row.style.background = '#fff';
        row.dataset.rowIndex = String(idx);

        var companyEmail = window.companyPrimaryEmail || '';
        var defaultMode = prefill.mode || (companyEmail ? 'company_email' : 'name_only');

        row.innerHTML =
            '<div class="d-flex justify-content-between align-items-center mb-2">' +
                '<strong>Director</strong>' +
                '<button type="button" class="btn btn-sm btn-outline-danger director-remove-btn">Remove</button>' +
            '</div>' +
            '<input type="hidden" class="director-mode-input" name="director_modes[]" value="' + escDirectorHtml(defaultMode) + '">' +
            '<div class="row g-2 mb-2">' +
                '<div class="col-md-4">' +
                    '<label class="small mb-1">How to add</label>' +
                    '<select class="form-control form-control-sm director-type-select">' +
                        '<option value="link"' + (defaultMode === 'link' ? ' selected' : '') + '>Link existing person</option>' +
                        '<option value="company_email"' + (defaultMode === 'company_email' ? ' selected' : '') + '>Add new — use company email</option>' +
                        '<option value="personal"' + (defaultMode === 'personal' ? ' selected' : '') + '>Add new — personal email</option>' +
                        '<option value="name_only"' + (defaultMode === 'name_only' ? ' selected' : '') + '>Name only (no record)</option>' +
                    '</select>' +
                '</div>' +
                '<div class="col-md-3">' +
                    '<label class="small mb-1">Role</label>' +
                    '<input type="text" class="form-control form-control-sm director-role-input" maxlength="100" placeholder="Director" value="' + escDirectorHtml(prefill.director_role || '') + '">' +
                '</div>' +
                '<div class="col-md-3">' +
                    '<label class="small mb-1">Date of birth</label>' +
                    '<input type="date" class="form-control form-control-sm director-dob-input" value="' + escDirectorHtml(prefill.director_dob || '') + '">' +
                '</div>' +
                '<div class="col-md-2 d-flex align-items-end">' +
                    '<label class="small mb-1 d-flex align-items-center gap-1">' +
                        '<input type="radio" name="director_primary" value="' + idx + '"' + (prefill.is_primary ? ' checked' : '') + '> Primary' +
                    '</label>' +
                '</div>' +
            '</div>' +
            '<div class="director-link-wrap" style="display:none;">' +
                '<label class="small mb-1">Search person</label>' +
                '<select class="form-control director-link-select" data-placeholder="Search director..."></select>' +
                '<input type="hidden" name="director_client_ids[]" class="director-client-id-input" value="' + escDirectorHtml(prefill.director_client_id || '') + '">' +
            '</div>' +
            '<div class="director-new-wrap" style="display:none;">' +
                '<div class="row g-2">' +
                    '<div class="col-md-6">' +
                        '<label class="small mb-1">First name</label>' +
                        '<input type="text" class="form-control form-control-sm director-first-name-input" maxlength="255" value="' + escDirectorHtml(prefill.first_name || '') + '">' +
                    '</div>' +
                    '<div class="col-md-6">' +
                        '<label class="small mb-1">Last name</label>' +
                        '<input type="text" class="form-control form-control-sm director-last-name-input" maxlength="255" value="' + escDirectorHtml(prefill.last_name || '') + '">' +
                    '</div>' +
                '</div>' +
                '<div class="director-company-email-display mt-2" style="display:none;">' +
                    '<label class="small mb-1">Company email</label>' +
                    '<input type="text" class="form-control form-control-sm" readonly value="' + escDirectorHtml(companyEmail) + '">' +
                    (companyEmail ? '' : '<small class="text-danger">Add a company email in Contacts first.</small>') +
                '</div>' +
            '</div>' +
            '<div class="director-email-wrap mt-2" style="display:none;">' +
                '<label class="small mb-1">Personal email</label>' +
                '<input type="email" class="form-control form-control-sm director-personal-email-input" maxlength="255" value="' + escDirectorHtml((defaultMode === 'personal' ? (prefill.email || '') : '')) + '">' +
            '</div>' +
            '<div class="director-name-only-wrap" style="display:none;">' +
                '<label class="small mb-1">Full name</label>' +
                '<input type="text" class="form-control form-control-sm director-name-only-input" maxlength="255" value="' + escDirectorHtml(prefill.director_name || '') + '">' +
            '</div>';

        container.appendChild(row);

        var typeSelect = row.querySelector('.director-type-select');
        var linkSelect = row.querySelector('.director-link-select');
        var clientIdInput = row.querySelector('.director-client-id-input');

        typeSelect.value = defaultMode;
        toggleDirectorRowMode(row, defaultMode);

        typeSelect.addEventListener('change', function () {
            var mode = typeSelect.value;
            toggleDirectorRowMode(row, mode);
            if (row.querySelector('.director-mode-input')) {
                row.querySelector('.director-mode-input').value = mode;
            }
            if (mode === 'link') {
                var linkSelectEl = row.querySelector('.director-link-select');
                if (linkSelectEl && !linkSelectEl.tomselect) {
                    initDirectorLinkSelect(linkSelectEl, {});
                    linkSelectEl.addEventListener('change', function () {
                        var ts = linkSelectEl.tomselect;
                        var val = ts ? ts.getValue() : linkSelectEl.value;
                        if (clientIdInput) clientIdInput.value = val || '';
                    });
                }
            }
        });

        row.querySelector('.director-remove-btn').addEventListener('click', function () {
            if (linkSelect && typeof window.destroyTS === 'function') window.destroyTS(linkSelect);
            row.remove();
            reindexDirectorPrimaryRadios();
        });

        if (defaultMode === 'link') {
            initDirectorLinkSelect(linkSelect, prefill);
            if (linkSelect) {
                linkSelect.addEventListener('change', function () {
                    var ts = linkSelect.tomselect;
                    var val = ts ? ts.getValue() : linkSelect.value;
                    if (clientIdInput) clientIdInput.value = val || '';
                });
            }
        }

        return row;
    }

    function openDirectorsEditorAndAddRow() {
        var editEl = document.getElementById('directorsInfoEdit');
        if (!editEl || editEl.classList.contains('hidden')) {
            toggleEditMode('directorsInfo');
        } else if (!document.querySelector('#directorsContainer .director-row')) {
            initDirectorsEditor();
        }
        addDirectorRow();
    }

    function reindexDirectorPrimaryRadios() {
        var rows = document.querySelectorAll('#directorsContainer .director-row');
        rows.forEach(function (row, i) {
            var radio = row.querySelector('input[name="director_primary"]');
            if (radio) radio.value = String(i);
        });
    }

    function initDirectorsEditor() {
        var container = document.getElementById('directorsContainer');
        if (!container) return;
        destroyDirectorTomSelects(container);
        container.innerHTML = '';
        directorRowCounter = 0;
        var rows = window.initialCompanyDirectors || [];
        if (rows.length) {
            rows.forEach(function (row) { addDirectorRow(row); });
        }
    }

    function collectDirectorsFormData() {
        var formData = new FormData();
        var form = document.getElementById('editCompanyForm');
        if (!form) return formData;

        formData.append('id', form.querySelector('input[name="id"]')?.value || '');
        formData.append('type', form.querySelector('input[name="type"]')?.value || '');
        formData.append('section', 'directors');

        document.querySelectorAll('#directorsContainer .director-row').forEach(function (row) {
            var mode = row.querySelector('.director-type-select')?.value
                || row.querySelector('.director-mode-input')?.value
                || 'name_only';
            if (row.querySelector('.director-mode-input')) {
                row.querySelector('.director-mode-input').value = mode;
            }
            formData.append('director_modes[]', mode);

            var role = row.querySelector('.director-role-input');
            var dob = row.querySelector('.director-dob-input');
            formData.append('director_roles[]', role ? role.value : '');
            formData.append('director_dobs[]', dob ? dob.value : '');

            if (mode === 'link') {
                var clientId = row.querySelector('.director-client-id-input')?.value
                    || (row.querySelector('.director-link-select')?.tomselect?.getValue() || '');
                formData.append('director_client_ids[]', clientId);
                formData.append('director_names[]', '');
                formData.append('director_first_names[]', '');
                formData.append('director_last_names[]', '');
                formData.append('director_emails[]', '');
            } else if (mode === 'name_only') {
                formData.append('director_client_ids[]', '');
                formData.append('director_names[]', row.querySelector('.director-name-only-input')?.value || '');
                formData.append('director_first_names[]', '');
                formData.append('director_last_names[]', '');
                formData.append('director_emails[]', '');
            } else {
                formData.append('director_client_ids[]', '');
                formData.append('director_names[]', '');
                formData.append('director_first_names[]', row.querySelector('.director-first-name-input')?.value || '');
                formData.append('director_last_names[]', row.querySelector('.director-last-name-input')?.value || '');
                formData.append('director_emails[]', mode === 'personal' ? (row.querySelector('.director-personal-email-input')?.value || '') : '');
            }
        });

        var primary = document.querySelector('#directorsContainer input[name="director_primary"]:checked');
        formData.append('director_primary', primary ? primary.value : '0');

        return formData;
    }

    function saveDirectorsInfo() {
        var invalidMessage = '';
        document.querySelectorAll('#directorsContainer .director-row').forEach(function (row, index) {
            if (invalidMessage) return;
            var mode = row.querySelector('.director-type-select')?.value || '';
            if (mode === 'link') {
                var clientId = row.querySelector('.director-client-id-input')?.value
                    || (row.querySelector('.director-link-select')?.tomselect?.getValue() || '');
                if (!clientId) invalidMessage = 'Director ' + (index + 1) + ': select an existing person or remove the row.';
            } else if (mode === 'name_only') {
                if (!(row.querySelector('.director-name-only-input')?.value || '').trim()) {
                    invalidMessage = 'Director ' + (index + 1) + ': enter a name or remove the row.';
                }
            } else if (mode === 'company_email' || mode === 'personal') {
                var fn = (row.querySelector('.director-first-name-input')?.value || '').trim();
                var ln = (row.querySelector('.director-last-name-input')?.value || '').trim();
                if (!fn && !ln) invalidMessage = 'Director ' + (index + 1) + ': first or last name is required.';
                if (!invalidMessage && mode === 'personal' && !(row.querySelector('.director-personal-email-input')?.value || '').trim()) {
                    invalidMessage = 'Director ' + (index + 1) + ': personal email is required.';
                }
                if (!invalidMessage && mode === 'company_email' && !window.companyPrimaryEmail) {
                    invalidMessage = 'Director ' + (index + 1) + ': add a company email under Contacts first, or choose another option.';
                }
            }
        });
        if (invalidMessage) {
            if (typeof showNotification === 'function') showNotification(invalidMessage, 'error');
            else alert(invalidMessage);
            return;
        }

        var formData = collectDirectorsFormData();
        saveSectionData('directors', formData, function () {
            toggleEditMode('directorsInfo');
            window.location.reload();
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var originalToggle = window.toggleEditMode;
        if (typeof originalToggle === 'function') {
            window.toggleEditMode = function (sectionKey) {
                if (sectionKey === 'directorsInfo') {
                    var editEl = document.getElementById('directorsInfoEdit');
                    var isOpening = editEl && editEl.classList.contains('hidden');
                    originalToggle(sectionKey);
                    if (isOpening) initDirectorsEditor();
                    return;
                }
                originalToggle(sectionKey);
            };
        }
    });

    (function () {
        var tabMap = { matter_case: 'menu2', hearings: 'menu4', court: 'menu4' };
        function activateCompanyEditTabFromUrl() {
            try {
                var qs = new URLSearchParams(window.location.search || '');
                var editTab = qs.get('edit_tab') || '';
                var hash = (window.location.hash || '').replace('#', '');
                var targetId = tabMap[editTab] || hash || '';
                if (!targetId || !['companyEditHome', 'menu2', 'menu4'].includes(targetId)) return;
                if (typeof showCompanyMatterTab === 'function') showCompanyMatterTab(targetId);
            } catch (e) { /* ignore */ }
        }
        if (window.jQuery) {
            jQuery(function () {
                activateCompanyEditTabFromUrl();
                window.setTimeout(activateCompanyEditTabFromUrl, 100);
            });
        }
    })();
    </script>
    @endpush
@endsection
