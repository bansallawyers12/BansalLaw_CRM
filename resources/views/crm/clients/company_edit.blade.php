@extends('layouts.crm_client_detail_dashboard')

@php
    $latestMatterRefNo = null;
    if (isset($fetchedData) && $fetchedData->type === 'client') {
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
    $isTrusteeBusinessType = $company && $company->isTrusteeBusiness();
    $companyTypeForForm = old('company_type', $company ? $company->company_type : '');
    $showTrusteeFieldsInitial = \App\Models\Company::isTrusteeBusinessType($companyTypeForForm);
@endphp

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/address-autocomplete.css') }}">
    <link rel="stylesheet" href="{{asset('css/client-forms.css')}}">
    <link rel="stylesheet" href="{{asset('css/clients/edit-client-components.css')}}">
    {{-- Select2 CSS for contact person search --}}
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
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
                    <h3><i class="fas fa-building"></i> {{ $fetchedData->type == 'lead' ? 'Edit Company Lead' : 'Edit Company Client' }} : {{ $company ? $company->company_name : 'Unnamed Company' }}</h3>
                    <div class="client-id">
                        {{ $fetchedData->type == 'lead' ? 'Lead ID' : ($fetchedData->type == 'client' ? 'Client ID' : '') }} : {{ $fetchedData->client_id }}
                    </div>
                </div>
                <nav class="nav-menu">
                    <button class="nav-item active" onclick="scrollToSection('companySection')">
                        <i class="fas fa-building"></i>
                        <span>Company Information</span>
                    </button>
                    <button class="nav-item" onclick="scrollToSection('contactPersonSection')">
                        <i class="fas fa-user-tie"></i>
                        <span>Contact Person</span>
                    </button>
                    <button class="nav-item" onclick="scrollToSection('sponsorshipSection')">
                        <i class="fas fa-file-contract"></i>
                        <span>Sponsorship</span>
                    </button>
                    <button class="nav-item" onclick="scrollToSection('directorsSection')">
                        <i class="fas fa-users-cog"></i>
                        <span>Directors</span>
                    </button>
                    <button class="nav-item" onclick="scrollToSection('financialSection')">
                        <i class="fas fa-dollar-sign"></i>
                        <span>Financial</span>
                    </button>
                    <button class="nav-item" onclick="scrollToSection('workforceSection')">
                        <i class="fas fa-users"></i>
                        <span>Workforce</span>
                    </button>
                    <button class="nav-item" onclick="scrollToSection('operationsSection')">
                        <i class="fas fa-briefcase"></i>
                        <span>Operations</span>
                    </button>
                    <button class="nav-item" onclick="scrollToSection('lmtSection')">
                        <i class="fas fa-clipboard-check"></i>
                        <span>LMT</span>
                    </button>
                    <button class="nav-item" onclick="scrollToSection('trainingSection')">
                        <i class="fas fa-graduation-cap"></i>
                        <span>Training</span>
                    </button>
                    <button class="nav-item" onclick="scrollToSection('nominationsSection')">
                        <i class="fas fa-user-check"></i>
                        <span>Nominations</span>
                    </button>
                    <button class="nav-item" onclick="scrollToSection('addressSection')">
                        <i class="fas fa-map-marker-alt"></i>
                        <span>Business Address</span>
                    </button>
                    <button class="nav-item" onclick="scrollToSection('contactsSection')">
                        <i class="fas fa-phone"></i>
                        <span>Contacts</span>
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
                    searchContactPersonRoute: '{{ route("api.search.contact.person") }}',
                    searchAddressRoute: '{{ route("clients.searchAddressFull") }}',
                    getPlaceDetailsRoute: '{{ route("clients.getPlaceDetails") }}',
                    csrfToken: '{{ csrf_token() }}'
                };
                
                // Current client ID for excluding from search results
                window.currentClientId = '{{ $fetchedData->id }}';
                window.currentClientType = @json($fetchedData->type);
                window.latestClientMatterRef = @json($latestMatterRefNo);
            </script>

            <!-- Main Content Area -->
            <div class="main-content-area">
                <form id="editCompanyForm" action="{{ route('clients.update') }}" method="POST">
                    @csrf
                    <input type="hidden" name="id" value="{{ $fetchedData->id }}">
                    <input type="hidden" name="type" value="{{ $fetchedData->type }}">

                <!-- Company Information Section -->
                <section id="companySection" class="content-section">
                    <section class="form-section">
                        <div class="section-header">
                            <h3><i class="fas fa-building"></i> Company Information</h3>
                            <div class="section-actions">
                                <button type="button" class="edit-section-btn" onclick="toggleEditMode('companyInfo')">
                                    <i class="fas fa-pen"></i>
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
                                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeTradingName(this)"><i class="fas fa-times"></i></button>
                                        </div>
                                        @endforeach
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="addTradingName()"><i class="fas fa-plus"></i> Add another</button>
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
                            <h3><i class="fas fa-user-tie"></i> Primary Contact Person</h3>
                            <div class="section-actions">
                                <button type="button" class="edit-section-btn" onclick="toggleEditMode('contactPersonInfo')">
                                    <i class="fas fa-pen"></i>
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
                                            class="form-control select2-contact-person" 
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

                <!-- Sponsorship Section -->
                <section id="sponsorshipSection" class="content-section">
                    <section class="form-section">
                        <div class="section-header">
                            <h3><i class="fas fa-file-contract"></i> Sponsorship</h3>
                            <div class="section-actions">
                                <button type="button" class="edit-section-btn" onclick="toggleEditMode('sponsorship')">
                                    <i class="fas fa-pen"></i>
                                </button>
                                <button type="button" class="add-section-btn" onclick="addSponsorshipRow()" title="Add Sponsorship"><i class="fas fa-plus"></i></button>
                            </div>
                        </div>
                        <div id="sponsorshipSummary" class="summary-view">
                            @if($company && $company->sponsorships->isNotEmpty())
                            <div class="summary-grid">
                                @foreach($company->sponsorships as $s)
                                <div class="summary-item full-width" style="border-bottom:1px solid #e9ecef;padding-bottom:12px;margin-bottom:12px;">
                                    @if($company->sponsorships->count() > 1)
                                    <p style="margin:0 0 8px 0;font-weight:600;color:#495057;">Sponsorship {{ $loop->iteration }}</p>
                                    @endif
                                    <div class="summary-grid">
                                        @if($s->sponsorship_type)<div class="summary-item"><span class="summary-label">Type:</span><span class="summary-value">{{ $s->sponsorship_type }}</span></div>@endif
                                        @if($s->sponsorship_status)<div class="summary-item"><span class="summary-label">Status:</span><span class="summary-value">{{ $s->sponsorship_status }}</span></div>@endif
                                        @if($s->trn)<div class="summary-item"><span class="summary-label">TRN:</span><span class="summary-value">{{ $s->trn }}</span></div>@endif
                                        @if($s->sponsorship_start_date)<div class="summary-item"><span class="summary-label">Start:</span><span class="summary-value">{{ $s->sponsorship_start_date->format('d/m/Y') }}</span></div>@endif
                                        @if($s->sponsorship_end_date)<div class="summary-item"><span class="summary-label">End:</span><span class="summary-value">{{ $s->sponsorship_end_date->format('d/m/Y') }}</span></div>@endif
                                        @if($s->regional_sponsorship)<div class="summary-item"><span class="summary-label">Regional:</span><span class="summary-value">Yes</span></div>@endif
                                        @if($s->adverse_information)<div class="summary-item"><span class="summary-label">Adverse information:</span><span class="summary-value">Yes</span></div>@endif
                                        @if($s->previous_sponsorship_notes)<div class="summary-item full-width"><span class="summary-label">Notes:</span><span class="summary-value">{{ $s->previous_sponsorship_notes }}</span></div>@endif
                                    </div>
                                </div>
                                @endforeach
                            </div>
                            @elseif($company && ($company->sponsorship_type || $company->sponsorship_status || $company->trn))
                            <div class="summary-grid">
                                @if($company->sponsorship_type)<div class="summary-item"><span class="summary-label">Type:</span><span class="summary-value">{{ $company->sponsorship_type }}</span></div>@endif
                                @if($company->sponsorship_status)<div class="summary-item"><span class="summary-label">Status:</span><span class="summary-value">{{ $company->sponsorship_status }}</span></div>@endif
                                @if($company->trn)<div class="summary-item"><span class="summary-label">TRN:</span><span class="summary-value">{{ $company->trn }}</span></div>@endif
                                @if($company->sponsorship_start_date)<div class="summary-item"><span class="summary-label">Start:</span><span class="summary-value">{{ $company->sponsorship_start_date?->format('d/m/Y') }}</span></div>@endif
                                @if($company->sponsorship_end_date)<div class="summary-item"><span class="summary-label">End:</span><span class="summary-value">{{ $company->sponsorship_end_date?->format('d/m/Y') }}</span></div>@endif
                            </div>
                            @else
                            <div class="empty-state"><p>No sponsorship details added yet.</p></div>
                            @endif
                        </div>
                        <div id="sponsorshipEdit" class="edit-view hidden">
                            <div id="sponsorshipsContainer">
                                @php
                                    if ($company && $company->sponsorships->isNotEmpty()) {
                                        $sponsorshipRows = $company->sponsorships;
                                    } else {
                                        $sponsorshipRows = collect([(object) [
                                            'sponsorship_type' => optional($company)->sponsorship_type,
                                            'sponsorship_status' => optional($company)->sponsorship_status,
                                            'trn' => optional($company)->trn,
                                            'sponsorship_start_date' => optional($company)->sponsorship_start_date,
                                            'sponsorship_end_date' => optional($company)->sponsorship_end_date,
                                            'regional_sponsorship' => optional($company)->regional_sponsorship,
                                            'adverse_information' => optional($company)->adverse_information,
                                            'previous_sponsorship_notes' => optional($company)->previous_sponsorship_notes,
                                        ]]);
                                    }
                                @endphp
                                @php $sponsorshipTotal = $sponsorshipRows->count(); @endphp
                                @foreach($sponsorshipRows->values() as $idx => $s)
                                <div class="sponsorship-row repeatable-section" style="border:1px solid #dee2e6;padding:15px;margin-bottom:15px;border-radius:6px;">
                                    <p class="sponsorship-row-label" style="margin:0 0 10px 0;font-weight:600;color:#495057;{{ $sponsorshipTotal > 1 ? '' : 'display:none;' }}">Sponsorship {{ $idx + 1 }}</p>
                                    <div class="content-grid">
                                        <div class="form-group"><label>Sponsorship Type</label><input type="text" name="sponsorship_types[]" value="{{ $s->sponsorship_type ?? '' }}" placeholder="e.g. 482, 494"></div>
                                        <div class="form-group"><label>Status</label><input type="text" name="sponsorship_statuses[]" value="{{ $s->sponsorship_status ?? '' }}" placeholder="Status"></div>
                                        <div class="form-group"><label>TRN</label><input type="text" name="sponsorship_trns[]" value="{{ $s->trn ?? '' }}" placeholder="Training Reference Number"></div>
                                        <div class="form-group"><label>Start Date</label><input type="date" name="sponsorship_start_dates[]" value="{{ ($s->sponsorship_start_date ?? null) ? \Carbon\Carbon::parse($s->sponsorship_start_date)->format('Y-m-d') : '' }}"></div>
                                        <div class="form-group"><label>End Date</label><input type="date" name="sponsorship_end_dates[]" value="{{ ($s->sponsorship_end_date ?? null) ? \Carbon\Carbon::parse($s->sponsorship_end_date)->format('Y-m-d') : '' }}"></div>
                                        <div class="form-group"><label><input type="checkbox" class="sponsorship-regional-cb" name="sponsorship_regional[{{ $idx }}]" value="1" {{ !empty($s->regional_sponsorship) ? 'checked' : '' }}> Regional Sponsorship</label></div>
                                        <div class="form-group"><label><input type="checkbox" class="sponsorship-adverse-cb" name="sponsorship_adverse[{{ $idx }}]" value="1" {{ !empty($s->adverse_information) ? 'checked' : '' }}> Adverse Information</label></div>
                                        <div class="form-group full-width"><label>Previous Sponsorship Notes</label><textarea name="sponsorship_previous_notes[]" rows="2">{{ $s->previous_sponsorship_notes ?? '' }}</textarea></div>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeSponsorshipRow(this)"><i class="fas fa-times"></i> Remove</button>
                                </div>
                                @endforeach
                            </div>
                            <div class="edit-actions">
                                <button type="button" class="btn btn-primary" onclick="saveSponsorshipInfo()">Save</button>
                                <button type="button" class="btn btn-secondary" onclick="cancelEdit('sponsorship')">Cancel</button>
                            </div>
                        </div>
                    </section>
                </section>

                <!-- Directors Section -->
                <section id="directorsSection" class="content-section">
                    <section class="form-section">
                        <div class="section-header">
                            <h3><i class="fas fa-users-cog"></i> Directors</h3>
                            <div class="section-actions">
                                <button type="button" class="edit-section-btn" onclick="toggleEditMode('directors')">
                                    <i class="fas fa-pen"></i>
                                </button>
                                <button type="button" class="add-section-btn" onclick="addDirectorRow()" title="Add Director"><i class="fas fa-plus"></i></button>
                            </div>
                        </div>
                        <div id="directorsSummary" class="summary-view">
                            @if($company && $company->directors->isNotEmpty())
                            <div class="summary-grid">
                                @foreach($company->directors as $dir)
                                <div class="summary-item"><span class="summary-label">{{ $dir->directorClient ? ($dir->directorClient->first_name.' '.$dir->directorClient->last_name) : ($dir->director_name ?? '') }}</span><span class="summary-value">{{ $dir->director_role ?? '' }}@if($dir->director_dob) (DOB: {{ $dir->director_dob->format('d/m/Y') }})@endif</span></div>
                                @endforeach
                            </div>
                            @else
                            <div class="empty-state"><p>No directors added yet.</p></div>
                            @endif
                        </div>
                        <div id="directorsEdit" class="edit-view hidden">
                            <div class="form-group" style="margin-bottom:15px;">
                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="addContactPersonAsDirector()">
                                    <i class="fas fa-user-plus"></i> Add contact person as director
                                </button>
                                <small class="form-text text-muted">Quick-add the selected contact person (from Contact Person section) to the directors list.</small>
                            </div>
                            <div id="directorsContainer">
                                @php
                                    $directorsData = (optional($company)->directors?->isNotEmpty()) ? $company->directors : collect([(object)['id'=>null,'director_client_id'=>null,'directorClient'=>null,'director_name'=>'','director_dob'=>null,'director_role'=>'','is_primary'=>true]]);
                                @endphp
                                @foreach($directorsData as $idx => $dir)
                                <div class="director-row repeatable-section" style="border:1px solid #dee2e6;padding:15px;margin-bottom:15px;border-radius:6px;">
                                    <input type="hidden" name="director_ids[]" value="{{ $dir->id ?? '' }}">
                                    <div class="form-group" style="margin-bottom:10px;">
                                        <label>Director</label>
                                        <div style="display:flex;gap:15px;align-items:center;flex-wrap:wrap;">
                                            <div style="flex:1;min-width:200px;">
                                                <select name="director_client_ids[]" class="director-person-select form-control" data-placeholder="Search client/lead..." style="width:100%;">
                                                    @if($dir->director_client_id && ($dir->directorClient ?? null))
                                                    <option value="{{ $dir->director_client_id }}" selected>{{ $dir->directorClient->first_name }} {{ $dir->directorClient->last_name }}</option>
                                                    @endif
                                                </select>
                                            </div>
                                            <span>OR</span>
                                            <div style="flex:1;min-width:200px;">
                                                <input type="text" name="director_names[]" value="{{ $dir->director_name ?? '' }}" placeholder="Not in system - enter name">
                                            </div>
                                        </div>
                                    </div>
                                    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                                        <input type="date" name="director_dobs[]" value="{{ isset($dir->director_dob) && $dir->director_dob ? $dir->director_dob->format('Y-m-d') : '' }}" placeholder="DOB" title="DOB">
                                        <input type="text" name="director_roles[]" value="{{ $dir->director_role ?? '' }}" placeholder="Role" style="width:120px;">
                                        <label><input type="radio" name="director_primary" value="{{ $idx }}" {{ ($dir->is_primary ?? ($idx===0)) ? 'checked' : '' }}> Primary</label>
                                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeDirectorRow(this)"><i class="fas fa-times"></i> Remove</button>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                            <button type="button" class="add-item-btn" onclick="addDirectorRow()"><i class="fas fa-plus-circle"></i> Add Director</button>
                            <div class="edit-actions">
                                <button type="button" class="btn btn-primary" onclick="saveDirectorsInfo()">Save</button>
                                <button type="button" class="btn btn-secondary" onclick="cancelEdit('directors')">Cancel</button>
                            </div>
                        </div>
                    </section>
                </section>

                <!-- Financial Section -->
                <section id="financialSection" class="content-section">
                    <section class="form-section">
                        <div class="section-header">
                            <h3><i class="fas fa-dollar-sign"></i> Financial</h3>
                            <div class="section-actions">
                                <button type="button" class="edit-section-btn" onclick="toggleEditMode('financial')">
                                    <i class="fas fa-pen"></i>
                                </button>
                            </div>
                        </div>
                        <div id="financialSummary" class="summary-view">
                            <div class="summary-grid">
                                @if($company && ($company->annual_turnover || $company->wages_expenditure))
                                @if($company->annual_turnover)<div class="summary-item"><span class="summary-label">Annual Turnover:</span><span class="summary-value">${{ number_format($company->annual_turnover, 2) }}</span></div>@endif
                                @if($company->wages_expenditure)<div class="summary-item"><span class="summary-label">Wages Expenditure:</span><span class="summary-value">${{ number_format($company->wages_expenditure, 2) }}</span></div>@endif
                                @else
                                <div class="empty-state"><p>No financial details added yet.</p></div>
                                @endif
                            </div>
                        </div>
                        <div id="financialEdit" class="edit-view hidden">
                            <div class="content-grid">
                                <div class="form-group"><label>Annual Turnover</label><input type="number" name="annual_turnover" value="{{ optional($company)->annual_turnover ?? '' }}" placeholder="0" step="0.01"></div>
                                <div class="form-group"><label>Wages Expenditure</label><input type="number" name="wages_expenditure" value="{{ optional($company)->wages_expenditure ?? '' }}" placeholder="0" step="0.01"></div>
                            </div>
                            <div class="edit-actions">
                                <button type="button" class="btn btn-primary" onclick="saveFinancialInfo()">Save</button>
                                <button type="button" class="btn btn-secondary" onclick="cancelEdit('financial')">Cancel</button>
                            </div>
                        </div>
                    </section>
                </section>

                <!-- Workforce Section -->
                <section id="workforceSection" class="content-section">
                    <section class="form-section">
                        <div class="section-header">
                            <h3><i class="fas fa-users"></i> Workforce</h3>
                            <div class="section-actions">
                                <button type="button" class="edit-section-btn" onclick="toggleEditMode('workforce')">
                                    <i class="fas fa-pen"></i>
                                </button>
                            </div>
                        </div>
                        <div id="workforceSummary" class="summary-view">
                            <div class="summary-grid">
                                @php $hasWorkforce = $company && ($company->workforce_australian_citizens !== null || $company->workforce_permanent_residents !== null || $company->workforce_temp_visa_holders !== null || $company->workforce_total !== null || $company->workforce_foreign_494 !== null || $company->workforce_foreign_other_temp_activity !== null || $company->workforce_foreign_overseas_students !== null || $company->workforce_foreign_working_holiday !== null || $company->workforce_foreign_other !== null); @endphp
                                @if($hasWorkforce)
                                @if($company->workforce_australian_citizens !== null)<div class="summary-item"><span class="summary-label">workforce_aus_professionals:</span><span class="summary-value">{{ $company->workforce_australian_citizens }}</span></div>@endif
                                @if($company->workforce_permanent_residents !== null)<div class="summary-item"><span class="summary-label">workforce_aus_tradespersons:</span><span class="summary-value">{{ $company->workforce_permanent_residents }}</span></div>@endif
                                @if($company->workforce_temp_visa_holders !== null)<div class="summary-item"><span class="summary-label">workforce_aus_employment_other:</span><span class="summary-value">{{ $company->workforce_temp_visa_holders }}</span></div>@endif
                                @if($company->workforce_total !== null)<div class="summary-item"><span class="summary-label">workforce_foreign_482_457:</span><span class="summary-value">{{ $company->workforce_total }}</span></div>@endif
                                @if($company->workforce_foreign_494 !== null)<div class="summary-item"><span class="summary-label">workforce_foreign_494:</span><span class="summary-value">{{ $company->workforce_foreign_494 }}</span></div>@endif
                                @if($company->workforce_foreign_other_temp_activity !== null)<div class="summary-item"><span class="summary-label">workforce_foreign_other_temp_activity:</span><span class="summary-value">{{ $company->workforce_foreign_other_temp_activity }}</span></div>@endif
                                @if($company->workforce_foreign_overseas_students !== null)<div class="summary-item"><span class="summary-label">workforce_foreign_overseas_students:</span><span class="summary-value">{{ $company->workforce_foreign_overseas_students }}</span></div>@endif
                                @if($company->workforce_foreign_working_holiday !== null)<div class="summary-item"><span class="summary-label">workforce_foreign_working_holiday:</span><span class="summary-value">{{ $company->workforce_foreign_working_holiday }}</span></div>@endif
                                @if($company->workforce_foreign_other !== null)<div class="summary-item"><span class="summary-label">workforce_foreign_other:</span><span class="summary-value">{{ $company->workforce_foreign_other }}</span></div>@endif
                                @else
                                <div class="empty-state"><p>No workforce details added yet.</p></div>
                                @endif
                            </div>
                        </div>
                        <div id="workforceEdit" class="edit-view hidden">
                            <div class="content-grid">
                                <div class="form-group"><label>workforce_aus_professionals</label><input type="number" name="workforce_australian_citizens" value="{{ optional($company)->workforce_australian_citizens ?? '' }}" min="0"></div>
                                <div class="form-group"><label>workforce_aus_tradespersons</label><input type="number" name="workforce_permanent_residents" value="{{ optional($company)->workforce_permanent_residents ?? '' }}" min="0"></div>
                                <div class="form-group"><label>workforce_aus_employment_other</label><input type="number" name="workforce_temp_visa_holders" value="{{ optional($company)->workforce_temp_visa_holders ?? '' }}" min="0"></div>
                                <div class="form-group"><label>workforce_foreign_482_457</label><input type="number" name="workforce_total" value="{{ optional($company)->workforce_total ?? '' }}" min="0"></div>
                                <div class="form-group"><label>workforce_foreign_494</label><input type="number" name="workforce_foreign_494" value="{{ optional($company)->workforce_foreign_494 ?? '' }}" min="0"></div>
                                <div class="form-group"><label>workforce_foreign_other_temp_activity</label><input type="number" name="workforce_foreign_other_temp_activity" value="{{ optional($company)->workforce_foreign_other_temp_activity ?? '' }}" min="0"></div>
                                <div class="form-group"><label>workforce_foreign_overseas_students</label><input type="number" name="workforce_foreign_overseas_students" value="{{ optional($company)->workforce_foreign_overseas_students ?? '' }}" min="0"></div>
                                <div class="form-group"><label>workforce_foreign_working_holiday</label><input type="number" name="workforce_foreign_working_holiday" value="{{ optional($company)->workforce_foreign_working_holiday ?? '' }}" min="0"></div>
                                <div class="form-group"><label>workforce_foreign_other</label><input type="number" name="workforce_foreign_other" value="{{ optional($company)->workforce_foreign_other ?? '' }}" min="0"></div>
                            </div>
                            <div class="edit-actions">
                                <button type="button" class="btn btn-primary" onclick="saveWorkforceInfo()">Save</button>
                                <button type="button" class="btn btn-secondary" onclick="cancelEdit('workforce')">Cancel</button>
                            </div>
                        </div>
                    </section>
                </section>

                <!-- Operations Section -->
                <section id="operationsSection" class="content-section">
                    <section class="form-section">
                        <div class="section-header">
                            <h3><i class="fas fa-briefcase"></i> Operations</h3>
                            <div class="section-actions">
                                <button type="button" class="edit-section-btn" onclick="toggleEditMode('operations')">
                                    <i class="fas fa-pen"></i>
                                </button>
                            </div>
                        </div>
                        <div id="operationsSummary" class="summary-view">
                            <div class="summary-grid">
                                @if($company && ($company->business_operating_since || $company->main_business_activity))
                                @if($company->business_operating_since)<div class="summary-item"><span class="summary-label">Operating Since:</span><span class="summary-value">{{ $company->business_operating_since->format('d/m/Y') }}</span></div>@endif
                                @if($company->main_business_activity)<div class="summary-item full-width"><span class="summary-label">Main Activity:</span><span class="summary-value">{{ $company->main_business_activity }}</span></div>@endif
                                @else
                                <div class="empty-state"><p>No operations details added yet.</p></div>
                                @endif
                            </div>
                        </div>
                        <div id="operationsEdit" class="edit-view hidden">
                            <div class="content-grid">
                                <div class="form-group"><label>Business Operating Since</label><input type="date" name="business_operating_since" value="{{ optional($company)->business_operating_since?->format('Y-m-d') ?? '' }}"></div>
                                <div class="form-group full-width"><label>Main Business Activity</label><input type="text" name="main_business_activity" value="{{ optional($company)->main_business_activity ?? '' }}" placeholder="Primary business activity"></div>
                            </div>
                            <div class="edit-actions">
                                <button type="button" class="btn btn-primary" onclick="saveOperationsInfo()">Save</button>
                                <button type="button" class="btn btn-secondary" onclick="cancelEdit('operations')">Cancel</button>
                            </div>
                        </div>
                    </section>
                </section>

                <!-- LMT Section -->
                <section id="lmtSection" class="content-section">
                    <section class="form-section">
                        <div class="section-header">
                            <h3><i class="fas fa-clipboard-check"></i> Labour Market Testing (LMT)</h3>
                            <div class="section-actions">
                                <button type="button" class="edit-section-btn" onclick="toggleEditMode('lmt')">
                                    <i class="fas fa-pen"></i>
                                </button>
                            </div>
                        </div>
                        <div id="lmtSummary" class="summary-view">
                            <div class="summary-grid">
                                @if($company && ($company->lmt_required !== null || $company->lmt_start_date || $company->lmt_notes))
                                @if($company->lmt_required !== null)<div class="summary-item"><span class="summary-label">LMT Required:</span><span class="summary-value">{{ $company->lmt_required ? 'Yes' : 'No' }}</span></div>@endif
                                @if($company->lmt_start_date)<div class="summary-item"><span class="summary-label">Start:</span><span class="summary-value">{{ $company->lmt_start_date->format('d/m/Y') }}</span></div>@endif
                                @if($company->lmt_end_date)<div class="summary-item"><span class="summary-label">End:</span><span class="summary-value">{{ $company->lmt_end_date->format('d/m/Y') }}</span></div>@endif
                                @if($company->lmt_notes)<div class="summary-item full-width"><span class="summary-label">Notes:</span><span class="summary-value">{{ $company->lmt_notes }}</span></div>@endif
                                @else
                                <div class="empty-state"><p>No LMT details added yet.</p></div>
                                @endif
                            </div>
                        </div>
                        <div id="lmtEdit" class="edit-view hidden">
                            <div class="content-grid">
                                <div class="form-group"><label><input type="checkbox" name="lmt_required" value="1" {{ optional($company)->lmt_required ? 'checked' : '' }}> LMT Required</label></div>
                                <div class="form-group"><label>LMT Start Date</label><input type="date" name="lmt_start_date" value="{{ optional($company)->lmt_start_date?->format('Y-m-d') ?? '' }}"></div>
                                <div class="form-group"><label>LMT End Date</label><input type="date" name="lmt_end_date" value="{{ optional($company)->lmt_end_date?->format('Y-m-d') ?? '' }}"></div>
                                <div class="form-group full-width"><label>LMT Notes</label><textarea name="lmt_notes" rows="2">{{ optional($company)->lmt_notes ?? '' }}</textarea></div>
                            </div>
                            <div class="edit-actions">
                                <button type="button" class="btn btn-primary" onclick="saveLmtInfo()">Save</button>
                                <button type="button" class="btn btn-secondary" onclick="cancelEdit('lmt')">Cancel</button>
                            </div>
                        </div>
                    </section>
                </section>

                <!-- Training Section -->
                <section id="trainingSection" class="content-section">
                    <section class="form-section">
                        <div class="section-header">
                            <h3><i class="fas fa-graduation-cap"></i> Training</h3>
                            <div class="section-actions">
                                <button type="button" class="edit-section-btn" onclick="toggleEditMode('training')">
                                    <i class="fas fa-pen"></i>
                                </button>
                            </div>
                        </div>
                        <div id="trainingSummary" class="summary-view">
                            <div class="summary-grid">
                                @if($company && ($company->training_position_title || $company->trainer_name))
                                @if($company->training_position_title)<div class="summary-item"><span class="summary-label">Position Title:</span><span class="summary-value">{{ $company->training_position_title }}</span></div>@endif
                                @if($company->trainer_name)<div class="summary-item"><span class="summary-label">Trainer Name:</span><span class="summary-value">{{ $company->trainer_name }}</span></div>@endif
                                @else
                                <div class="empty-state"><p>No training details added yet.</p></div>
                                @endif
                            </div>
                        </div>
                        <div id="trainingEdit" class="edit-view hidden">
                            <div class="content-grid">
                                <div class="form-group"><label>Training Position Title</label><input type="text" name="training_position_title" value="{{ optional($company)->training_position_title ?? '' }}" placeholder="Position title"></div>
                                <div class="form-group"><label>Trainer Name</label><input type="text" name="trainer_name" value="{{ optional($company)->trainer_name ?? '' }}" placeholder="Trainer name"></div>
                            </div>
                            <div class="edit-actions">
                                <button type="button" class="btn btn-primary" onclick="saveTrainingInfo()">Save</button>
                                <button type="button" class="btn btn-secondary" onclick="cancelEdit('training')">Cancel</button>
                            </div>
                        </div>
                    </section>
                </section>

                <!-- Nominations Section -->
                <section id="nominationsSection" class="content-section">
                    <section class="form-section">
                        <div class="section-header">
                            <h3><i class="fas fa-user-check"></i> Nominations</h3>
                            <div class="section-actions">
                                <button type="button" class="edit-section-btn" onclick="toggleEditMode('nominations')">
                                    <i class="fas fa-pen"></i>
                                </button>
                                <button type="button" class="add-section-btn" onclick="addNominationRow()" title="Add Nomination"><i class="fas fa-plus"></i></button>
                            </div>
                        </div>
                        <div id="nominationsSummary" class="summary-view">
                            @if($company && $company->nominations->isNotEmpty())
                            <div class="summary-grid">
                                @foreach($company->nominations as $nom)
                                <div class="summary-item"><span class="summary-label">{{ $nom->position_title ?? 'Position' }}:</span><span class="summary-value">{{ $nom->nominatedClient ? $nom->nominatedClient->first_name.' '.$nom->nominatedClient->last_name : ($nom->nominated_person_name ?? 'N/A') }}@if($nom->trn) (TRN: {{ $nom->trn }})@endif</span></div>
                                @endforeach
                            </div>
                            @else
                            <div class="empty-state"><p>No nominations added yet.</p></div>
                            @endif
                        </div>
                        <div id="nominationsEdit" class="edit-view hidden">
                            <div id="nominationsContainer">
                                @php
                                    $nominationsData = (optional($company)->nominations?->isNotEmpty()) ? $company->nominations : collect([(object)['id'=>null,'position_title'=>'','anzsco_code'=>'','position_description'=>'','salary'=>null,'duration'=>'','nominated_client_id'=>null,'nominated_person_name'=>'','trn'=>'','status'=>'','nomination_date'=>null,'expiry_date'=>null]]);
                                @endphp
                                @foreach($nominationsData as $idx => $nom)
                                <div class="nomination-row repeatable-section" style="border:1px solid #dee2e6;padding:15px;margin-bottom:15px;border-radius:6px;">
                                    <input type="hidden" name="nomination_ids[]" value="{{ $nom->id ?? '' }}">
                                    <div class="content-grid" style="margin-bottom:10px;">
                                        <div class="form-group"><label>Position Title</label><input type="text" name="nomination_position_titles[]" value="{{ $nom->position_title ?? '' }}" placeholder="Position title"></div>
                                        <div class="form-group"><label>ANZSCO Code</label><input type="text" name="nomination_anzsco_codes[]" value="{{ $nom->anzsco_code ?? '' }}" placeholder="e.g. 261312"></div>
                                        <div class="form-group full-width"><label>Description</label><textarea name="nomination_descriptions[]" rows="2" placeholder="Position description">{{ $nom->position_description ?? '' }}</textarea></div>
                                        <div class="form-group"><label>Salary</label><input type="number" name="nomination_salaries[]" value="{{ $nom->salary ?? '' }}" step="0.01" placeholder="0"></div>
                                        <div class="form-group"><label>Duration</label><input type="text" name="nomination_durations[]" value="{{ $nom->duration ?? '' }}" placeholder="e.g. 2 years"></div>
                                    </div>
                                    <div class="form-group" style="margin-bottom:10px;">
                                        <label>Nominated Person (Visa Applicant)</label>
                                        <div style="display:flex;gap:15px;align-items:center;flex-wrap:wrap;">
                                            <div style="flex:1;min-width:200px;">
                                                <select name="nomination_nominated_client_ids[]" class="nomination-person-select form-control" data-placeholder="Search client/lead..." style="width:100%;">
                                                    @if($nom->nominated_client_id && $nom->nominatedClient)
                                                    <option value="{{ $nom->nominated_client_id }}" selected>{{ $nom->nominatedClient->first_name }} {{ $nom->nominatedClient->last_name }}</option>
                                                    @endif
                                                </select>
                                            </div>
                                            <span>OR</span>
                                            <div style="flex:1;min-width:200px;">
                                                <input type="text" name="nomination_person_names[]" value="{{ $nom->nominated_person_name ?? '' }}" placeholder="Not in system - enter name only">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="content-grid">
                                        <div class="form-group"><label>TRN</label><input type="text" name="nomination_trns[]" value="{{ $nom->trn ?? '' }}" placeholder="TRN"></div>
                                        <div class="form-group"><label>Status</label><input type="text" name="nomination_statuses[]" value="{{ $nom->status ?? '' }}" placeholder="Status"></div>
                                        <div class="form-group"><label>Nomination Date</label><input type="date" name="nomination_dates[]" value="{{ $nom->nomination_date?->format('Y-m-d') ?? '' }}"></div>
                                        <div class="form-group"><label>Expiry Date</label><input type="date" name="nomination_expiries[]" value="{{ $nom->expiry_date?->format('Y-m-d') ?? '' }}"></div>
                                        <div class="form-group" style="align-self:end;"><button type="button" class="btn btn-sm btn-outline-danger" onclick="removeNominationRow(this)"><i class="fas fa-times"></i> Remove</button></div>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                            <button type="button" class="add-item-btn" onclick="addNominationRow()"><i class="fas fa-plus-circle"></i> Add Nomination</button>
                            <div class="edit-actions">
                                <button type="button" class="btn btn-primary" onclick="saveNominationsInfo()">Save</button>
                                <button type="button" class="btn btn-secondary" onclick="cancelEdit('nominations')">Cancel</button>
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

                </form>
            </div>
        </div>
    </div>

    @push('scripts')
    {{-- Select2 JS for contact person search --}}
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="{{asset('js/clients/edit-client.js')}}"></script>
    <script src="{{asset('js/address-autocomplete.js')}}"></script>
    <script src="{{asset('js/clients/address-regional-codes.js')}}"></script>
    
    <script>
    $(document).ready(function() {
        // Initialize Select2 for contact person search
        $('#contactPersonSearch').select2({
            ajax: {
                url: window.editClientConfig.searchContactPersonRoute,
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return {
                        q: params.term, // search term
                        exclude_id: window.currentClientId // Exclude current company being edited
                    };
                },
                processResults: function (data) {
                    return {
                        results: data.results.map(function(item) {
                            return {
                                id: item.id,
                                text: item.text,
                                first_name: item.first_name,
                                last_name: item.last_name,
                                email: item.email,
                                phone: item.phone,
                                client_id: item.client_id
                            };
                        })
                    };
                },
                cache: true
            },
            minimumInputLength: 2,
            placeholder: 'Type phone, email, name, or client ID to search...',
            allowClear: true
        });
        
        // Auto-fill contact person details when selected
        $('#contactPersonSearch').on('select2:select', function (e) {
            const data = e.params.data;
            
            // Auto-fill fields
            $('#contactPersonFirstName').val(data.first_name);
            $('#contactPersonLastName').val(data.last_name);
            $('#contactPersonPhone').val(data.phone || '');
            $('#contactPersonEmailDisplay').val(data.email);
            
            // Add visual indicator
            $('.contact-person-field').addClass('field-auto-filled');
        });
        
        // Clear fields when selection is cleared
        $('#contactPersonSearch').on('select2:clear', function (e) {
            $('#contactPersonFirstName').val('');
            $('#contactPersonLastName').val('');
            $('#contactPersonPhone').val('');
            $('#contactPersonEmailDisplay').val('');
            $('.contact-person-field').removeClass('field-auto-filled');
        });
        
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

        // Init Select2 for nomination person search (existing rows)
        $('.nomination-person-select').each(function() {
            if (!$(this).hasClass('select2-hidden-accessible')) {
                $(this).select2({
                    ajax: { url: window.editClientConfig.searchContactPersonRoute, dataType: 'json', delay: 250, data: function(p) { return { q: p.term, exclude_id: window.currentClientId }; }, processResults: function(d) { return { results: d.results || [] }; } },
                    minimumInputLength: 2, allowClear: true, placeholder: 'Search client/lead...'
                });
            }
        });
        // Init Select2 for director person search (existing rows)
        $('.director-person-select').each(function() {
            if (!$(this).hasClass('select2-hidden-accessible')) {
                $(this).select2({
                    ajax: { url: window.editClientConfig.searchContactPersonRoute, dataType: 'json', delay: 250, data: function(p) { return { q: p.term, exclude_id: window.currentClientId }; }, processResults: function(d) { return { results: d.results || [] }; } },
                    minimumInputLength: 2, allowClear: true, placeholder: 'Search client/lead...'
                });
            }
        });
    });

    function addTradingName() {
        const container = $('#tradingNamesList');
        const idx = container.find('.trading-name-row').length;
        const row = $('<div class="trading-name-row" style="display: flex; gap: 10px; margin-bottom: 8px; align-items: center;">' +
            '<input type="text" name="trading_names[]" placeholder="Trading name" style="flex: 1;">' +
            '<label><input type="radio" name="trading_name_primary" value="' + idx + '"> Primary</label>' +
            '<button type="button" class="btn btn-sm btn-outline-danger" onclick="removeTradingName(this)"><i class="fas fa-times"></i></button>' +
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

    function addDirectorRow(prefillClientId, prefillName) {
        const container = $('#directorsContainer');
        const idx = container.find('.director-row').length;
        let selectOpt = '';
        if (prefillClientId && prefillName) {
            const escaped = String(prefillName).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
            selectOpt = '<option value="' + String(prefillClientId).replace(/"/g,'&quot;') + '" selected>' + escaped + '</option>';
        }
        const row = '<div class="director-row repeatable-section" style="border:1px solid #dee2e6;padding:15px;margin-bottom:15px;border-radius:6px;">' +
            '<input type="hidden" name="director_ids[]" value="">' +
            '<div class="form-group" style="margin-bottom:10px;"><label>Director</label>' +
            '<div style="display:flex;gap:15px;align-items:center;flex-wrap:wrap;">' +
            '<div style="flex:1;min-width:200px;"><select name="director_client_ids[]" class="director-person-select form-control" data-placeholder="Search client/lead..." style="width:100%;">' + selectOpt + '</select></div>' +
            '<span>OR</span><div style="flex:1;min-width:200px;"><input type="text" name="director_names[]" placeholder="Not in system - enter name"></div></div></div>' +
            '<div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">' +
            '<input type="date" name="director_dobs[]" placeholder="DOB" title="DOB">' +
            '<input type="text" name="director_roles[]" placeholder="Role" style="width:120px;">' +
            '<label><input type="radio" name="director_primary" value="' + idx + '"> Primary</label>' +
            '<button type="button" class="btn btn-sm btn-outline-danger" onclick="removeDirectorRow(this)"><i class="fas fa-times"></i> Remove</button></div></div>';
        container.append(row);
        container.find('.director-row').each(function(i) { $(this).find('input[name="director_primary"]').val(i); });
        if (typeof $().select2 === 'function') {
            container.find('.director-person-select').last().select2({
                ajax: { url: window.editClientConfig.searchContactPersonRoute, dataType: 'json', delay: 250, data: function(p) { return { q: p.term, exclude_id: window.currentClientId }; }, processResults: function(d) { return { results: d.results || [] }; } },
                minimumInputLength: 2, allowClear: true, placeholder: 'Search client/lead...'
            });
        }
    }
    function addContactPersonAsDirector() {
        const clientId = $('#contactPersonSearch').val();
        if (!clientId) {
            if (typeof showNotification === 'function') showNotification('Please select a contact person first (Contact Person section).', 'error');
            else alert('Please select a contact person first (Contact Person section).');
            return;
        }
        const displayName = $('#contactPersonSearch').find('option:selected').text() || '';
        addDirectorRow(clientId, displayName);
        if (typeof showNotification === 'function') showNotification('Contact person added to directors list.', 'success');
    }
    function removeDirectorRow(btn) {
        const container = $('#directorsContainer');
        if (container.find('.director-row').length <= 1) return;
        $(btn).closest('.director-row').remove();
        $('#directorsContainer .director-row').each(function(i) { $(this).find('input[name="director_primary"]').val(i); });
    }

    function addNominationRow() {
        const container = $('#nominationsContainer');
        const row = '<div class="nomination-row repeatable-section" style="border:1px solid #dee2e6;padding:15px;margin-bottom:15px;border-radius:6px;">' +
            '<input type="hidden" name="nomination_ids[]" value="">' +
            '<div class="content-grid" style="margin-bottom:10px;">' +
            '<div class="form-group"><label>Position Title</label><input type="text" name="nomination_position_titles[]" placeholder="Position title"></div>' +
            '<div class="form-group"><label>ANZSCO Code</label><input type="text" name="nomination_anzsco_codes[]" placeholder="e.g. 261312"></div>' +
            '<div class="form-group full-width"><label>Description</label><textarea name="nomination_descriptions[]" rows="2" placeholder="Position description"></textarea></div>' +
            '<div class="form-group"><label>Salary</label><input type="number" name="nomination_salaries[]" step="0.01" placeholder="0"></div>' +
            '<div class="form-group"><label>Duration</label><input type="text" name="nomination_durations[]" placeholder="e.g. 2 years"></div></div>' +
            '<div class="form-group" style="margin-bottom:10px;"><label>Nominated Person (Visa Applicant)</label>' +
            '<div style="display:flex;gap:15px;align-items:center;flex-wrap:wrap;">' +
            '<div style="flex:1;min-width:200px;"><select name="nomination_nominated_client_ids[]" class="nomination-person-select form-control" data-placeholder="Search client/lead..." style="width:100%;"></select></div>' +
            '<span>OR</span><div style="flex:1;min-width:200px;"><input type="text" name="nomination_person_names[]" placeholder="Not in system - enter name only"></div></div></div>' +
            '<div class="content-grid"><div class="form-group"><label>TRN</label><input type="text" name="nomination_trns[]" placeholder="TRN"></div>' +
            '<div class="form-group"><label>Status</label><input type="text" name="nomination_statuses[]" placeholder="Status"></div>' +
            '<div class="form-group"><label>Nomination Date</label><input type="date" name="nomination_dates[]"></div>' +
            '<div class="form-group"><label>Expiry Date</label><input type="date" name="nomination_expiries[]"></div>' +
            '<div class="form-group" style="align-self:end;"><button type="button" class="btn btn-sm btn-outline-danger" onclick="removeNominationRow(this)"><i class="fas fa-times"></i> Remove</button></div></div></div>';
        container.append(row);
        if (typeof $().select2 === 'function') {
            container.find('.nomination-person-select').last().select2({
                ajax: { url: window.editClientConfig.searchContactPersonRoute, dataType: 'json', delay: 250, data: function(p) { return { q: p.term, exclude_id: window.currentClientId }; }, processResults: function(d) { return { results: d.results || [] }; } },
                minimumInputLength: 2, allowClear: true, placeholder: 'Search client/lead...'
            });
        }
    }
    function removeNominationRow(btn) {
        const container = $('#nominationsContainer');
        if (container.find('.nomination-row').length <= 1) return;
        $(btn).closest('.nomination-row').remove();
    }

    function reindexSponsorshipRows() {
        const rows = $('#sponsorshipsContainer .sponsorship-row');
        const showLabels = rows.length > 1;
        rows.each(function(i) {
            $(this).find('.sponsorship-regional-cb').attr('name', 'sponsorship_regional[' + i + ']');
            $(this).find('.sponsorship-adverse-cb').attr('name', 'sponsorship_adverse[' + i + ']');
            const label = $(this).find('.sponsorship-row-label');
            if (showLabels) {
                label.text('Sponsorship ' + (i + 1)).show();
            } else {
                label.hide();
            }
        });
    }

    function addSponsorshipRow() {
        // Open edit mode if currently showing summary
        const editView = document.getElementById('sponsorshipEdit');
        if (editView && (editView.style.display === 'none' || editView.classList.contains('hidden'))) {
            if (typeof toggleEditMode === 'function') toggleEditMode('sponsorship');
        }
        const container = $('#sponsorshipsContainer');
        const idx = container.find('.sponsorship-row').length;
        const row = '<div class="sponsorship-row repeatable-section" style="border:1px solid #dee2e6;padding:15px;margin-bottom:15px;border-radius:6px;">' +
            '<p class="sponsorship-row-label" style="margin:0 0 10px 0;font-weight:600;color:#495057;display:none;"></p>' +
            '<div class="content-grid">' +
            '<div class="form-group"><label>Sponsorship Type</label><input type="text" name="sponsorship_types[]" value="" placeholder="e.g. 482, 494"></div>' +
            '<div class="form-group"><label>Status</label><input type="text" name="sponsorship_statuses[]" value="" placeholder="Status"></div>' +
            '<div class="form-group"><label>TRN</label><input type="text" name="sponsorship_trns[]" value="" placeholder="Training Reference Number"></div>' +
            '<div class="form-group"><label>Start Date</label><input type="date" name="sponsorship_start_dates[]" value=""></div>' +
            '<div class="form-group"><label>End Date</label><input type="date" name="sponsorship_end_dates[]" value=""></div>' +
            '<div class="form-group"><label><input type="checkbox" class="sponsorship-regional-cb" name="sponsorship_regional[' + idx + ']" value="1"> Regional Sponsorship</label></div>' +
            '<div class="form-group"><label><input type="checkbox" class="sponsorship-adverse-cb" name="sponsorship_adverse[' + idx + ']" value="1"> Adverse Information</label></div>' +
            '<div class="form-group full-width"><label>Previous Sponsorship Notes</label><textarea name="sponsorship_previous_notes[]" rows="2"></textarea></div>' +
            '</div>' +
            '<button type="button" class="btn btn-sm btn-outline-danger" onclick="removeSponsorshipRow(this)"><i class="fas fa-times"></i> Remove</button>' +
            '</div>';
        container.append(row);
        reindexSponsorshipRows();
    }

    function removeSponsorshipRow(btn) {
        const container = $('#sponsorshipsContainer');
        if (container.find('.sponsorship-row').length <= 1) return;
        $(btn).closest('.sponsorship-row').remove();
        reindexSponsorshipRows();
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
    function saveSponsorshipInfo() { saveSection('sponsorship', function() { toggleEditMode('sponsorship'); }); }
    function saveDirectorsInfo() { saveSection('directors', function() { toggleEditMode('directors'); }); }
    function saveFinancialInfo() { saveSection('financial', function() { toggleEditMode('financial'); }); }
    function saveWorkforceInfo() { saveSection('workforce', function() { toggleEditMode('workforce'); }); }
    function saveOperationsInfo() { saveSection('operations', function() { toggleEditMode('operations'); }); }
    function saveLmtInfo() { saveSection('lmt', function() { toggleEditMode('lmt'); }); }
    function saveTrainingInfo() { saveSection('training', function() { toggleEditMode('training'); }); }
    function saveNominationsInfo() { saveSection('nominations', function() { toggleEditMode('nominations'); }); }
    </script>
    @endpush
@endsection
