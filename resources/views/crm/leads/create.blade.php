@extends('layouts.crm_client_detail_dashboard')

@push('styles')
    <link rel="stylesheet" href="{{asset('css/client-forms.css')}}">
    <link rel="stylesheet" href="{{asset('css/clients/edit-client-components.css')}}">
    <link rel="stylesheet" href="{{asset('css/leads/lead-form.css')}}">
    <style>
        /* Compact Error Display Styles */
        .form-validation-errors {
            margin: 20px 0;
            max-width: 800px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .error-container {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            border-radius: 8px;
            padding: 15px 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .error-container h4 {
            color: #721c24;
            margin: 0 0 10px 0;
            font-size: 14px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .error-container ul {
            margin: 0;
            padding-left: 20px;
            list-style-type: disc;
        }
        
        .error-container li {
            color: #721c24;
            font-size: 13px;
            margin-bottom: 5px;
            line-height: 1.4;
        }
        
        .error-container li:last-child {
            margin-bottom: 0;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .form-validation-errors {
                margin: 15px 10px;
            }
            
            .error-container {
                padding: 12px 15px;
            }
            
            .error-container h4 {
                font-size: 13px;
            }
            
            .error-container li {
                font-size: 12px;
            }
        }
        
        /* Company fields styling */
        .company-lead-fields {
            animation: fadeIn 0.3s ease-in;
        }
        
        .personal-lead-fields {
            animation: fadeIn 0.3s ease-in;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .contact-person-field {
            background-color: #f8f9fa;
            cursor: not-allowed;
        }
        
        .contact-person-field.field-auto-filled {
            background-color: #e7f3ff;
            border-color: #0d6efd;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        .crm-ts-contact-person {
            width: 100% !important;
        }
    </style>
@endpush

@section('content')
    <div class="crm-container">
        <div class="main-content">


            <!-- Mobile Sidebar Toggle -->
            <button class="sidebar-toggle" onclick="toggleSidebar()">
                <i class="fa-solid fa-bars"></i>
            </button>

            <!-- Sidebar Navigation -->
            <div class="sidebar-navigation" id="sidebarNav">
                <div class="nav-header">
                    <h3><i class="fa-solid fa-user-plus"></i> {{ request('other_party') || old('is_other_party') ? 'Create Other Party' : 'Create New Lead' }}</h3>
                </div>
                <nav class="nav-menu">
                    <button class="nav-item active" onclick="scrollToSection('personalSection')">
                        <i class="fa-solid fa-user-circle"></i>
                        <span>Personal</span>
                    </button>
                </nav>
                
                <!-- Actions in Sidebar -->
                <div class="sidebar-actions">
                    <button class="nav-item back-btn" onclick="window.location.href='{{ route('dashboard') }}'">
                        <i class="fa-solid fa-arrow-left"></i>
                        <span>Back</span>
                    </button>
                    <button type="submit" form="createLeadForm" class="nav-item save-btn">
                        <i class="fa-solid fa-floppy-disk"></i>
                        <span>{{ request('other_party') || old('is_other_party') ? 'Save Other Party' : 'Save Lead' }}</span>
                    </button>
                </div>
            </div>

            <!-- Main Content Area -->
            <div class="main-content-area">
                
                {{-- Error Display Section --}}
                @if($errors->any())
                    <div class="alert alert-danger" style="margin: 20px 0; padding: 15px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 8px;">
                        <h4 style="margin: 0 0 10px 0; color: #721c24; font-size: 16px;">
                            <i class="fa-solid fa-triangle-exclamation"></i> Please fix the following errors:
                        </h4>
                        <ul style="margin: 0; padding-left: 20px;">
                            @foreach($errors->all() as $error)
                                <li style="color: #721c24; margin-bottom: 5px;">{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                
                @if(session('error'))
                    <div class="alert alert-danger" style="margin: 20px 0; padding: 15px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 8px;">
                        <h4 style="margin: 0; color: #721c24; font-size: 16px;">
                            <i class="fa-solid fa-circle-exclamation"></i> {{ session('error') }}
                        </h4>
                    </div>
                @endif
                
                @if(session('success'))
                    <div class="alert alert-success" style="margin: 20px 0; padding: 15px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 8px;">
                        <h4 style="margin: 0; color: #155724; font-size: 16px;">
                            <i class="fa-solid fa-circle-check"></i> {{ session('success') }}
                        </h4>
                    </div>
                @endif
                
                <form id="createLeadForm" action="{{ route('leads.store') }}" method="POST" enctype="multipart/form-data" novalidate>
                    @csrf


                    {{-- ==================== PERSONAL SECTION ==================== --}}
                    <section id="personalSection" class="content-section">
                        <!-- Lead Type Toggle -->
                        <section class="form-section" style="margin-bottom: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                            <div class="section-header">
                                <h3><i class="fa-solid fa-building"></i> Lead Type</h3>
                            </div>
                            
                            <div class="content-grid">
                                <div class="form-group full-width">
                                    <label style="display: block; margin-bottom: 10px; font-weight: 600;">
                                        Is this new lead a company?
                                    </label>
                                    <div style="display: flex; gap: 20px; align-items: center;">
                                        <label style="display: flex; align-items: center; cursor: pointer;">
                                            <input type="radio" name="is_company" value="no" id="is_company_no" 
                                                   {{ old('is_company', 'no') == 'no' ? 'checked' : '' }} 
                                                   onchange="toggleCompanyFields(false)" style="margin-right: 8px;">
                                            <span>No (Personal Lead)</span>
                                        </label>
                                        <label style="display: flex; align-items: center; cursor: pointer;">
                                            <input type="radio" name="is_company" value="yes" id="is_company_yes" 
                                                   {{ old('is_company') == 'yes' ? 'checked' : '' }} 
                                                   onchange="toggleCompanyFields(true)" style="margin-right: 8px;">
                                            <span>Yes (Company Lead)</span>
                                        </label>
                                    </div>
                                    @error('is_company')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                                <div class="form-group full-width">
                                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-weight: 600;">
                                        <input type="checkbox" name="is_other_party" id="isOtherPartyCheckbox" value="1"
                                            {{ old('is_other_party', request('other_party')) ? 'checked' : '' }}>
                                        <span>Other party <small class="text-muted" style="font-weight: normal;">(not a sales lead — appears in Other Parties list)</small></span>
                                    </label>
                                </div>
                            </div>
                        </section>

                        <!-- Basic Information -->
                        <section class="form-section">
                            <div class="section-header">
                                <h3><i class="fa-solid fa-user-circle"></i> Basic Information</h3>
                            </div>
                            
                            {{-- Personal Information Fields (shown when is_company = no) --}}
                            <div id="personalFields" class="personal-lead-fields">
                                <div class="content-grid">
                                    <div class="form-group">
                                        <label for="firstName">First Name <span class="text-danger">*</span></label>
                                        <input type="text" id="firstName" name="first_name" value="{{ old('first_name') }}" required>
                                        @error('first_name')
                                            <span class="text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>

                                    <div class="form-group">
                                        <label for="lastName">Last Name <span class="text-danger">*</span></label>
                                        <input type="text" id="lastName" name="last_name" value="{{ old('last_name') }}" required>
                                        @error('last_name')
                                            <span class="text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>

                                    <div class="form-group">
                                        <label for="dob">Date of Birth <span class="text-danger other-party-optional-marker">*</span></label>
                                        <input type="text" id="dob" name="dob" value="{{ old('dob') }}" class="date-picker other-party-relaxed-field" placeholder="dd/mm/yyyy" required>
                                        @error('dob')
                                            <span class="text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>

                                    <div class="form-group">
                                        <label for="age">Age</label>
                                        <input type="text" id="age" name="age" value="{{ old('age') }}" readonly>
                                    </div>

                                    <div class="form-group">
                                        <label for="gender">Gender <span class="text-danger other-party-optional-marker">*</span></label>
                                        <select id="gender" name="gender" class="other-party-relaxed-field" required>
                                            <option value="">Select Gender</option>
                                            <option value="Male" {{ old('gender') == 'Male' ? 'selected' : '' }}>Male</option>
                                            <option value="Female" {{ old('gender') == 'Female' ? 'selected' : '' }}>Female</option>
                                            <option value="Other" {{ old('gender') == 'Other' ? 'selected' : '' }}>Other</option>
                                        </select>
                                        @error('gender')
                                            <span class="text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>

                                    <div class="form-group">
                                        <label for="maritalStatus">Marital Status</label>
                                        <select id="maritalStatus" name="marital_status">
                                            <option value="">Select Marital Status</option>
                                            <option value="Never Married" {{ (old('marital_status') == 'Never Married' || old('marital_status') == 'Single') ? 'selected' : '' }}>Never Married</option>
                                            <option value="Engaged" {{ old('marital_status') == 'Engaged' ? 'selected' : '' }}>Engaged</option>
                                            <option value="Married" {{ old('marital_status') == 'Married' ? 'selected' : '' }}>Married</option>
                                            <option value="De Facto" {{ (old('marital_status') == 'Defacto' || old('marital_status') == 'De Facto') ? 'selected' : '' }}>De Facto</option>
                                            <option value="Separated" {{ old('marital_status') == 'Separated' ? 'selected' : '' }}>Separated</option>
                                            <option value="Divorced" {{ old('marital_status') == 'Divorced' ? 'selected' : '' }}>Divorced</option>
                                            <option value="Widowed" {{ old('marital_status') == 'Widowed' ? 'selected' : '' }}>Widowed</option>
                                        </select>
                                        @error('marital_status')
                                            <span class="text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            {{-- Company Information Fields (shown when is_company = yes) --}}
                            <div id="companyFields" class="company-lead-fields" style="display: none;">
                                <div class="content-grid">
                                    <div class="form-group">
                                        <label for="companyName">Company Name <span class="text-danger">*</span></label>
                                        <input type="text" id="companyName" name="company_name" 
                                               value="{{ old('company_name') }}" 
                                               class="company-field company-required">
                                        @error('company_name')
                                            <span class="text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>Does this company have a trading name?</label>
                                        <div style="display: flex; gap: 20px; margin-top: 5px;">
                                            <label><input type="radio" name="has_trading_name" value="1" {{ old('has_trading_name', 0) ? 'checked' : '' }} class="company-field"> Yes</label>
                                            <label><input type="radio" name="has_trading_name" value="0" {{ !old('has_trading_name', 0) ? 'checked' : '' }} class="company-field"> No</label>
                                        </div>
                                    </div>
                                    <div id="leadTradingNamesContainer" class="form-group" style="{{ !old('has_trading_name', 0) ? 'display:none;' : '' }}">
                                        <label>Trading Names</label>
                                        <div id="leadTradingNamesList">
                                            @php $leadTradingNames = old('trading_names', ['']); $leadTradingNames = is_array($leadTradingNames) ? $leadTradingNames : ['']; @endphp
                                            @foreach($leadTradingNames as $idx => $tn)
                                            <div class="trading-name-row" style="display: flex; gap: 10px; margin-bottom: 8px; align-items: center;">
                                                <input type="text" name="trading_names[]" value="{{ is_string($tn) ? $tn : '' }}" placeholder="Trading name" class="company-field" style="flex: 1;">
                                                <label><input type="radio" name="trading_name_primary" value="{{ $idx }}" {{ $idx === 0 ? 'checked' : '' }}> Primary</label>
                                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeLeadTradingName(this)"><i class="fa-solid fa-xmark"></i></button>
                                            </div>
                                            @endforeach
                                        </div>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="addLeadTradingName()"><i class="fa-solid fa-plus"></i> Add another</button>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="abn">ABN</label>
                                        <input type="text" id="abn" name="ABN_number" 
                                               value="{{ old('ABN_number') }}" 
                                               class="company-field" 
                                               placeholder="12 345 678 901"
                                               maxlength="14">
                                        <small class="form-text text-muted">11 digits (spaces optional)</small>
                                        @error('ABN_number')
                                            <span class="text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="acn">ACN</label>
                                        <input type="text" id="acn" name="ACN" 
                                               value="{{ old('ACN') }}" 
                                               class="company-field" 
                                               placeholder="123 456 789"
                                               maxlength="11">
                                        <small class="form-text text-muted">9 digits (spaces optional)</small>
                                        @error('ACN')
                                            <span class="text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="companyType">Business Type</label>
                                        <select id="companyType" name="company_type" class="company-field">
                                            <option value="">Select Business Type</option>
                                            <option value="Sole Trader" {{ old('company_type') == 'Sole Trader' ? 'selected' : '' }}>
                                                Sole Trader
                                            </option>
                                            <option value="Partnership" {{ old('company_type') == 'Partnership' ? 'selected' : '' }}>
                                                Partnership
                                            </option>
                                            <option value="Proprietary Company" {{ old('company_type') == 'Proprietary Company' ? 'selected' : '' }}>
                                                Proprietary Company (Pty Ltd)
                                            </option>
                                            <option value="Public Company" {{ old('company_type') == 'Public Company' ? 'selected' : '' }}>
                                                Public Company
                                            </option>
                                            <option value="Not-for-Profit" {{ old('company_type') == 'Not-for-Profit' ? 'selected' : '' }}>
                                                Not-for-Profit Organization
                                            </option>
                                            <option value="Trustee" {{ \App\Models\Company::isTrusteeBusinessType(old('company_type')) ? 'selected' : '' }}>
                                                Trustee
                                            </option>
                                            <option value="Other" {{ old('company_type') == 'Other' ? 'selected' : '' }}>
                                                Other
                                            </option>
                                        </select>
                                        @error('company_type')
                                            <span class="text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="companyWebsite">Company Website</label>
                                        <input type="url" id="companyWebsite" name="company_website" 
                                               value="{{ old('company_website') }}" 
                                               class="company-field" 
                                               placeholder="https://www.example.com">
                                        @error('company_website')
                                            <span class="text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>
                                
                                {{-- Primary Contact Person Section --}}
                                <div style="margin-top: 30px; padding-top: 20px; border-top: 2px solid #e0e0e0;">
                                    <h4 style="margin-bottom: 15px;">
                                        <i class="fa-solid fa-user-tie"></i> Primary Contact Person
                                    </h4>

                                    <input type="hidden" name="contact_person_manual" id="contactPersonManualFlag"
                                           value="{{ old('contact_person_manual', '0') }}">

                                    <div class="content-grid" style="margin-bottom: 16px;">
                                        <div class="form-group full-width">
                                            <label style="display: block; margin-bottom: 10px; font-weight: 600;">How would you like to add the contact person?</label>
                                            <div style="display: flex; gap: 20px; align-items: center; flex-wrap: wrap;">
                                                <label style="display: flex; align-items: center; cursor: pointer;">
                                                    <input type="radio" name="contact_person_mode" value="search" id="contactPersonModeSearch"
                                                           {{ old('contact_person_manual') ? '' : 'checked' }}
                                                           style="margin-right: 8px;">
                                                    <span>Search existing</span>
                                                </label>
                                                <label style="display: flex; align-items: center; cursor: pointer;">
                                                    <input type="radio" name="contact_person_mode" value="manual" id="contactPersonModeManual"
                                                           {{ old('contact_person_manual') ? 'checked' : '' }}
                                                           style="margin-right: 8px;">
                                                    <span>Add manually</span>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="content-grid">
                                        <div class="form-group full-width" id="contactPersonSearchWrap">
                                            <label for="contactPersonEmail">Search Contact Person <span class="text-danger contact-person-search-required">*</span></label>
                                            <select id="contactPersonEmail" name="contact_person_id" 
                                                    class="form-control crm-ts-contact-person" 
                                                    data-placeholder="Type phone, email, name, or client ID to search..."
                                                    style="width: 100%;">
                                                @if(old('contact_person_id'))
                                                    @php
                                                        $oldContactPerson = \App\Models\Admin::find(old('contact_person_id'));
                                                    @endphp
                                                    @if($oldContactPerson)
                                                        <option value="{{ $oldContactPerson->id }}" selected>
                                                            {{ $oldContactPerson->first_name }} {{ $oldContactPerson->last_name }} 
                                                            ({{ $oldContactPerson->email }})
                                                        </option>
                                                    @endif
                                                @endif
                                            </select>
                                            <small class="form-text text-muted">
                                                Search existing clients/leads by email, name, phone, or client ID. Selected person's details will auto-fill below. Or enter phone/email below — if they match an existing person, they will be auto-associated.
                                            </small>
                                            <div id="associatedPersonAlert" class="alert alert-info mt-2" style="display: none;">
                                                <i class="fa-solid fa-link"></i> <strong>Associated:</strong> This phone/email belongs to <span id="associatedPersonName"></span>. They will be set as the contact person.
                                            </div>
                                            @error('contact_person_id')
                                                <span class="text-danger">{{ $message }}</span>
                                            @enderror
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="contactPersonFirstName">First Name <span class="text-danger contact-person-manual-required">*</span></label>
                                            <input type="text" id="contactPersonFirstName" name="contact_person_first_name" 
                                                   value="{{ old('contact_person_first_name') }}" 
                                                   class="company-field contact-person-field">
                                            <small class="form-text text-muted contact-person-search-hint">Auto-filled from selected contact person</small>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="contactPersonLastName">Last Name <span class="text-danger contact-person-manual-required">*</span></label>
                                            <input type="text" id="contactPersonLastName" name="contact_person_last_name" 
                                                   value="{{ old('contact_person_last_name') }}" 
                                                   class="company-field contact-person-field">
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="contactPersonPosition">Position/Title</label>
                                            <input type="text" id="contactPersonPosition" name="contact_person_position" 
                                                   value="{{ old('contact_person_position') }}" 
                                                   class="company-field" 
                                                   placeholder="e.g., HR Manager, Director">
                                            @error('contact_person_position')
                                                <span class="text-danger">{{ $message }}</span>
                                            @enderror
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="contactPersonPhone">Phone</label>
                                            <input type="text" id="contactPersonPhone" name="contact_person_phone" 
                                                   value="{{ old('contact_person_phone') }}" 
                                                   class="company-field contact-person-field">
                                            <small class="form-text text-muted contact-person-search-hint">Auto-filled from selected contact person</small>
                                            @error('contact_person_phone')
                                                <span class="text-danger">{{ $message }}</span>
                                            @enderror
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="contactPersonEmailDisplay">Email </label>
                                            <input type="email" id="contactPersonEmailDisplay" name="contact_person_email"
                                                   value="{{ old('contact_person_email', old('contact_person_email_display')) }}" 
                                                   class="company-field contact-person-field">
                                            <small class="form-text text-muted contact-person-search-hint">Auto-filled from selected contact person</small>
                                            @error('contact_person_email')
                                                <span class="text-danger">{{ $message }}</span>
                                            @enderror
                                        </div>

                                        <div class="form-group full-width" id="contactPersonManualActions" style="display:none;">
                                            <button type="button" class="btn btn-outline-primary btn-sm" id="saveManualContactPersonBtn">
                                                <i class="fa-solid fa-user-plus"></i> Save contact person &amp; use
                                            </button>
                                            <small class="form-text text-muted d-block mt-1">
                                                Saves this person as a new lead so they can be searched next time. You can also save the whole company lead below without clicking this button.
                                            </small>
                                            <div id="manualContactPersonMessage" class="mt-2" style="display:none;"></div>
                                        </div>
                                    </div>
                                </div>

                                {{-- Solicitor Section --}}
                                <div style="margin-top: 30px; padding-top: 20px; border-top: 2px solid #e0e0e0;">
                                    <h4 style="margin-bottom: 15px;">
                                        <i class="fa-solid fa-scale-balanced"></i> Solicitor
                                    </h4>

                                    <input type="hidden" name="solicitor_manual" id="solicitorManualFlag"
                                           value="{{ old('solicitor_manual', '0') }}">

                                    <div class="content-grid" style="margin-bottom: 16px;">
                                        <div class="form-group full-width">
                                            <label style="display: block; margin-bottom: 10px; font-weight: 600;">How would you like to add the solicitor?</label>
                                            <div style="display: flex; gap: 20px; align-items: center; flex-wrap: wrap;">
                                                <label style="display: flex; align-items: center; cursor: pointer;">
                                                    <input type="radio" name="solicitor_mode" value="search" id="solicitorModeSearch"
                                                           {{ old('solicitor_manual') ? '' : 'checked' }}
                                                           style="margin-right: 8px;">
                                                    <span>Search existing</span>
                                                </label>
                                                <label style="display: flex; align-items: center; cursor: pointer;">
                                                    <input type="radio" name="solicitor_mode" value="manual" id="solicitorModeManual"
                                                           {{ old('solicitor_manual') ? 'checked' : '' }}
                                                           style="margin-right: 8px;">
                                                    <span>Add manually</span>
                                                </label>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="content-grid">
                                        <div class="form-group full-width" id="solicitorSearchWrap">
                                            <label for="solicitorSearch">Search Solicitor</label>
                                            <select id="solicitorSearch" name="solicitor_id"
                                                    class="form-control crm-ts-solicitor company-field"
                                                    data-placeholder="Type phone, email, name, or client ID to search..."
                                                    style="width: 100%;">
                                                @if(old('solicitor_id'))
                                                    @php
                                                        $oldSolicitor = \App\Models\Admin::find(old('solicitor_id'));
                                                    @endphp
                                                    @if($oldSolicitor)
                                                        <option value="{{ $oldSolicitor->id }}" selected>
                                                            {{ $oldSolicitor->first_name }} {{ $oldSolicitor->last_name }}
                                                            ({{ $oldSolicitor->email }})
                                                        </option>
                                                    @endif
                                                @endif
                                            </select>
                                            <small class="form-text text-muted">
                                                Search existing clients/leads by email, name, phone, or client ID. Selected person's details will auto-fill below.
                                            </small>
                                            @error('solicitor_id')
                                                <span class="text-danger">{{ $message }}</span>
                                            @enderror
                                        </div>

                                        <div class="form-group">
                                            <label for="solicitorFirstName">First Name <span class="text-danger solicitor-manual-required">*</span></label>
                                            <input type="text" id="solicitorFirstName" name="solicitor_first_name"
                                                   value="{{ old('solicitor_first_name') }}"
                                                   class="company-field solicitor-field">
                                            <small class="form-text text-muted solicitor-search-hint">Auto-filled from selected solicitor</small>
                                        </div>

                                        <div class="form-group">
                                            <label for="solicitorLastName">Last Name <span class="text-danger solicitor-manual-required">*</span></label>
                                            <input type="text" id="solicitorLastName" name="solicitor_last_name"
                                                   value="{{ old('solicitor_last_name') }}"
                                                   class="company-field solicitor-field">
                                        </div>

                                        <div class="form-group">
                                            <label for="solicitorPosition">Position/Title</label>
                                            <input type="text" id="solicitorPosition" name="solicitor_position"
                                                   value="{{ old('solicitor_position') }}"
                                                   class="company-field">
                                            @error('solicitor_position')
                                                <span class="text-danger">{{ $message }}</span>
                                            @enderror
                                        </div>

                                        <div class="form-group">
                                            <label for="solicitorPhone">Phone <span class="text-danger solicitor-manual-contact-required" style="display:none;">*</span></label>
                                            <input type="text" id="solicitorPhone" name="solicitor_phone"
                                                   value="{{ old('solicitor_phone') }}"
                                                   class="company-field solicitor-field">
                                            <small class="form-text text-muted solicitor-search-hint">Auto-filled from selected solicitor</small>
                                            @error('solicitor_phone')
                                                <span class="text-danger">{{ $message }}</span>
                                            @enderror
                                        </div>

                                        <div class="form-group">
                                            <label for="solicitorEmailDisplay">Email <span class="text-danger solicitor-manual-contact-required" style="display:none;">*</span></label>
                                            <input type="email" id="solicitorEmailDisplay" name="solicitor_email"
                                                   value="{{ old('solicitor_email') }}"
                                                   class="company-field solicitor-field">
                                            <small class="form-text text-muted solicitor-search-hint">Auto-filled from selected solicitor</small>
                                            @error('solicitor_email')
                                                <span class="text-danger">{{ $message }}</span>
                                            @enderror
                                        </div>

                                        <div class="form-group full-width" id="solicitorManualActions" style="display:none;">
                                            <button type="button" class="btn btn-outline-primary btn-sm" id="saveManualSolicitorBtn">
                                                <i class="fa-solid fa-user-plus"></i> Save solicitor &amp; use
                                            </button>
                                            <small class="form-text text-muted d-block mt-1">
                                                Saves this person as a new lead so they can be searched next time. You can also save the whole company lead below without clicking this button.
                                            </small>
                                            <div id="manualSolicitorMessage" class="mt-2" style="display:none;"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <!-- Phone Numbers -->
                        <section class="form-section">
                            <div class="section-header">
                                <h3><i class="fa-solid fa-phone"></i> Phone Number <span class="text-danger">*</span></h3>
                            </div>
                            
                            <div class="repeatable-section">
                                <div class="content-grid">
                                    <div class="form-group">
                                        <label>Type</label>
                                        <select name="contact_type_hidden[0]" class="contact-type-selector">
                                            <option value="Personal">Personal</option>
                                            <option value="Work">Work</option>
                                            <option value="Mobile">Mobile</option>
                                            <option value="Business">Business</option>
                                            <option value="Secondary">Secondary</option>
                                            <option value="Father">Father</option>
                                            <option value="Mother">Mother</option>
                                            <option value="Brother">Brother</option>
                                            <option value="Sister">Sister</option>
                                            <option value="Uncle">Uncle</option>
                                            <option value="Aunt">Aunt</option>
                                            <option value="Cousin">Cousin</option>
                                            <option value="Partner">Partner</option>
                                            <option value="Others">Others</option>
                                            <option value="Not In Use">Not In Use</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Country Code</label>
                                        <select name="country_code[0]" class="country-code-selector">
                                            <option value="+61" selected>🇦🇺 +61</option>
                                            <option value="+91">🇮🇳 +91</option>
                                            <option value="+1">🇺🇸 +1</option>
                                            <option value="+44">🇬🇧 +44</option>
                                            <option value="+49">🇩🇪 +49</option>
                                            <option value="+33">🇫🇷 +33</option>
                                            <option value="+86">🇨🇳 +86</option>
                                            <option value="+81">🇯🇵 +81</option>
                                            <option value="+82">🇰🇷 +82</option>
                                            <option value="+65">🇸🇬 +65</option>
                                            <option value="+60">🇲🇾 +60</option>
                                            <option value="+66">🇹🇭 +66</option>
                                            <option value="+63">🇵🇭 +63</option>
                                            <option value="+84">🇻🇳 +84</option>
                                            <option value="+62">🇮🇩 +62</option>
                                            <option value="+39">🇮🇹 +39</option>
                                            <option value="+34">🇪🇸 +34</option>
                                            <option value="+7">🇷🇺 +7</option>
                                            <option value="+55">🇧🇷 +55</option>
                                            <option value="+52">🇲🇽 +52</option>
                                            <option value="+54">🇦🇷 +54</option>
                                            <option value="+56">🇨🇱 +56</option>
                                            <option value="+57">🇨🇴 +57</option>
                                            <option value="+51">🇵🇪 +51</option>
                                            <option value="+58">🇻🇪 +58</option>
                                            <option value="+27">🇿🇦 +27</option>
                                            <option value="+20">🇪🇬 +20</option>
                                            <option value="+234">🇳🇬 +234</option>
                                            <option value="+254">🇰🇪 +254</option>
                                            <option value="+233">🇬🇭 +233</option>
                                            <option value="+212">🇲🇦 +212</option>
                                            <option value="+213">🇩🇿 +213</option>
                                            <option value="+216">🇹🇳 +216</option>
                                            <option value="+218">🇱🇾 +218</option>
                                            <option value="+220">🇬🇲 +220</option>
                                            <option value="+221">🇸🇳 +221</option>
                                            <option value="+222">🇲🇷 +222</option>
                                            <option value="+223">🇲🇱 +223</option>
                                            <option value="+224">🇬🇳 +224</option>
                                            <option value="+225">🇨🇮 +225</option>
                                            <option value="+226">🇧🇫 +226</option>
                                            <option value="+227">🇳🇪 +227</option>
                                            <option value="+228">🇹🇬 +228</option>
                                            <option value="+229">🇧🇯 +229</option>
                                            <option value="+230">🇲🇺 +230</option>
                                            <option value="+231">🇱🇷 +231</option>
                                            <option value="+232">🇸🇱 +232</option>
                                            <option value="+235">🇹🇩 +235</option>
                                            <option value="+236">🇨🇫 +236</option>
                                            <option value="+237">🇨🇲 +237</option>
                                            <option value="+238">🇨🇻 +238</option>
                                            <option value="+239">🇸🇹 +239</option>
                                            <option value="+240">🇬🇶 +240</option>
                                            <option value="+241">🇬🇦 +241</option>
                                            <option value="+242">🇨🇬 +242</option>
                                            <option value="+243">🇨🇩 +243</option>
                                            <option value="+244">🇦🇴 +244</option>
                                            <option value="+245">🇬🇼 +245</option>
                                            <option value="+246">🇮🇴 +246</option>
                                            <option value="+247">🇦🇨 +247</option>
                                            <option value="+248">🇸🇨 +248</option>
                                            <option value="+249">🇸🇩 +249</option>
                                            <option value="+250">🇷🇼 +250</option>
                                            <option value="+251">🇪🇹 +251</option>
                                            <option value="+252">🇸🇴 +252</option>
                                            <option value="+253">🇩🇯 +253</option>
                                            <option value="+255">🇹🇿 +255</option>
                                            <option value="+256">🇺🇬 +256</option>
                                            <option value="+257">🇧🇮 +257</option>
                                            <option value="+258">🇲🇿 +258</option>
                                            <option value="+260">🇿🇲 +260</option>
                                            <option value="+261">🇲🇬 +261</option>
                                            <option value="+262">🇷🇪 +262</option>
                                            <option value="+263">🇿🇼 +263</option>
                                            <option value="+264">🇳🇦 +264</option>
                                            <option value="+265">🇲🇼 +265</option>
                                            <option value="+266">🇱🇸 +266</option>
                                            <option value="+267">🇧🇼 +267</option>
                                            <option value="+268">🇸🇿 +268</option>
                                            <option value="+269">🇰🇲 +269</option>
                                            <option value="+290">🇸🇭 +290</option>
                                            <option value="+291">🇪🇷 +291</option>
                                            <option value="+297">🇦🇼 +297</option>
                                            <option value="+298">🇫🇴 +298</option>
                                            <option value="+299">🇬🇱 +299</option>
                                            <option value="+30">🇬🇷 +30</option>
                                            <option value="+31">🇳🇱 +31</option>
                                            <option value="+32">🇧🇪 +32</option>
                                            <option value="+351">🇵🇹 +351</option>
                                            <option value="+352">🇱🇺 +352</option>
                                            <option value="+353">🇮🇪 +353</option>
                                            <option value="+354">🇮🇸 +354</option>
                                            <option value="+355">🇦🇱 +355</option>
                                            <option value="+356">🇲🇹 +356</option>
                                            <option value="+357">🇨🇾 +357</option>
                                            <option value="+358">🇫🇮 +358</option>
                                            <option value="+359">🇧🇬 +359</option>
                                            <option value="+36">🇭🇺 +36</option>
                                            <option value="+370">🇱🇹 +370</option>
                                            <option value="+371">🇱🇻 +371</option>
                                            <option value="+372">🇪🇪 +372</option>
                                            <option value="+373">🇲🇩 +373</option>
                                            <option value="+374">🇦🇲 +374</option>
                                            <option value="+375">🇧🇾 +375</option>
                                            <option value="+376">🇦🇩 +376</option>
                                            <option value="+377">🇲🇨 +377</option>
                                            <option value="+378">🇸🇲 +378</option>
                                            <option value="+380">🇺🇦 +380</option>
                                            <option value="+381">🇷🇸 +381</option>
                                            <option value="+382">🇲🇪 +382</option>
                                            <option value="+383">🇽🇰 +383</option>
                                            <option value="+385">🇭🇷 +385</option>
                                            <option value="+386">🇸🇮 +386</option>
                                            <option value="+387">🇧🇦 +387</option>
                                            <option value="+389">🇲🇰 +389</option>
                                            <option value="+40">🇷🇴 +40</option>
                                            <option value="+41">🇨🇭 +41</option>
                                            <option value="+42">🇨🇿 +42</option>
                                            <option value="+43">🇦🇹 +43</option>
                                            <option value="+45">🇩🇰 +45</option>
                                            <option value="+46">🇸🇪 +46</option>
                                            <option value="+47">🇳🇴 +47</option>
                                            <option value="+48">🇵🇱 +48</option>
                                            <option value="+90">🇹🇷 +90</option>
                                            <option value="+92">🇵🇰 +92</option>
                                            <option value="+93">🇦🇫 +93</option>
                                            <option value="+94">🇱🇰 +94</option>
                                            <option value="+95">🇲🇲 +95</option>
                                            <option value="+960">🇲🇻 +960</option>
                                            <option value="+961">🇱🇧 +961</option>
                                            <option value="+962">🇯🇴 +962</option>
                                            <option value="+963">🇸🇾 +963</option>
                                            <option value="+964">🇮🇶 +964</option>
                                            <option value="+965">🇰🇼 +965</option>
                                            <option value="+966">🇸🇦 +966</option>
                                            <option value="+967">🇾🇪 +967</option>
                                            <option value="+968">🇴🇲 +968</option>
                                            <option value="+970">🇵🇸 +970</option>
                                            <option value="+971">🇦🇪 +971</option>
                                            <option value="+972">🇮🇱 +972</option>
                                            <option value="+973">🇧🇭 +973</option>
                                            <option value="+974">🇶🇦 +974</option>
                                            <option value="+975">🇧🇹 +975</option>
                                            <option value="+976">🇲🇳 +976</option>
                                            <option value="+977">🇳🇵 +977</option>
                                            <option value="+992">🇹🇯 +992</option>
                                            <option value="+993">🇹🇲 +993</option>
                                            <option value="+994">🇦🇿 +994</option>
                                            <option value="+995">🇬🇪 +995</option>
                                            <option value="+996">🇰🇬 +996</option>
                                            <option value="+998">🇺🇿 +998</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Phone Number <span class="text-danger">*</span></label>
                                        <input type="text" id="primaryPhoneInput" name="phone[0]" class="form-control other-party-relaxed-field" placeholder="Enter phone number" value="{{ old('phone.0') }}" required>
                                        @error('phone.0')
                                            <span class="text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                            
                        </section>

                        <!-- Email Addresses -->
                        <section class="form-section">
                            <div class="section-header">
                                <h3><i class="fa-solid fa-envelope"></i> Email Address <span class="text-danger">*</span></h3>
                            </div>
                            
                            <div class="repeatable-section">
                            <div class="content-grid">
                                <div class="form-group">
                                        <label>Type</label>
                                        <select name="email_type_hidden[0]" class="email-type-selector">
                                            <option value="Personal">Personal</option>
                                            <option value="Work">Work</option>
                                            <option value="Business">Business</option>
                                            <option value="Secondary">Secondary</option>
                                            <option value="Father">Father</option>
                                            <option value="Mother">Mother</option>
                                            <option value="Brother">Brother</option>
                                            <option value="Sister">Sister</option>
                                            <option value="Uncle">Uncle</option>
                                            <option value="Aunt">Aunt</option>
                                            <option value="Cousin">Cousin</option>
                                            <option value="Partner">Partner</option>
                                            <option value="Others">Others</option>
                                            <option value="Not In Use">Not In Use</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                        <label>Email Address <span class="text-danger">*</span></label>
                                        <input type="email" id="primaryEmailInput" name="email[0]" class="form-control other-party-relaxed-field" placeholder="Enter email address" value="{{ old('email.0') }}" required>
                                        @error('email.0')
                                            <span class="text-danger">{{ $message }}</span>
                                        @enderror
                                </div>
                                <div class="form-group">
                                    <label for="lead_pipeline_status">Lead stage</label>
                                    <select id="lead_pipeline_status" name="lead_status" class="form-control">
                                        @foreach(($leadStageLabels ?? []) as $val => $lbl)
                                            <option value="{{ $val }}" {{ old('lead_status', 'new') === $val ? 'selected' : '' }}>{{ $lbl }}</option>
                                        @endforeach
                                    </select>
                                    @error('lead_status')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                                <div class="form-group" id="lead_followup_date_wrap" style="display: none;">
                                    <label for="lead_followup_date">Follow-up date <span class="text-muted">(optional)</span></label>
                                    <input type="date" id="lead_followup_date" name="followup_date" class="form-control" value="{{ old('followup_date') }}">
                                    @error('followup_date')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                                <div class="form-group">
                                    <label for="assigned_staff_id">Assigned to</label>
                                    <select id="assigned_staff_id" name="assigned_staff_id" class="form-control">
                                        @foreach(($assignableStaff ?? collect()) as $st)
                                            <option value="{{ $st->id }}" {{ (string) old('assigned_staff_id', Auth::id()) === (string) $st->id ? 'selected' : '' }}>
                                                {{ $st->first_name }} {{ $st->last_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('assigned_staff_id')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                                </div>
                            </div>
                            
                        </section>

                        <!-- Address -->
                        <section class="form-section">
                            <div class="section-header">
                                <h3><i class="fa-solid fa-location-dot"></i> Address <small class="text-muted" style="font-weight:400;font-size:0.75em;">(optional)</small></h3>
                            </div>

                            <div class="repeatable-section">
                                <div class="repeatable-item">
                                    <div class="content-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                                        <div class="form-group" style="grid-column:1/-1;">
                                            <label>Address Line 1</label>
                                            <input type="text" name="address_line_1" class="form-control" placeholder="Street address, house no." value="{{ old('address_line_1') }}">
                                        </div>
                                        <div class="form-group" style="grid-column:1/-1;">
                                            <label>Address Line 2 <small class="text-muted">(optional)</small></label>
                                            <input type="text" name="address_line_2" class="form-control" placeholder="Apartment, suite, floor, etc." value="{{ old('address_line_2') }}">
                                        </div>
                                        <div class="form-group">
                                            <label>City / Suburb</label>
                                            <input type="text" name="suburb" class="form-control" placeholder="e.g. Rohini, Gurgaon" value="{{ old('suburb') }}">
                                        </div>
                                        <div class="form-group">
                                            <label>State / Province</label>
                                            <input type="text" name="state" class="form-control" placeholder="e.g. Delhi, Haryana" value="{{ old('state') }}">
                                        </div>
                                        <div class="form-group">
                                            <label>PIN / Postal Code</label>
                                            <input type="text" name="zip" class="form-control" placeholder="e.g. 110085" value="{{ old('zip') }}" maxlength="20">
                                        </div>
                                        <div class="form-group">
                                            <label>Country</label>
                                            <input type="text" name="country" class="form-control" placeholder="e.g. India" value="{{ old('country', 'India') }}">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>

                    </section>

                    <!-- Form Actions (Hidden for floating button) -->
                    <div class="form-actions" style="margin-top: 30px; padding: 20px; background: white; border-radius: 8px; display: flex; gap: 15px; justify-content: flex-end; visibility: hidden;">
                        <button type="button" class="btn btn-secondary" onclick="window.history.back()">
                            <i class="fa-solid fa-xmark"></i> Cancel
                        </button>
                        <button type="submit" class="btn btn-primary" id="hiddenSubmitBtn">
                            <i class="fa-solid fa-floppy-disk"></i> Save Lead
                        </button>
                    </div>
                    
                </form>
            </div>
        </div>
    </div>

    <!-- Floating Save Button -->
    <div class="floating-save-container">
        <div class="floating-save-buttons">
            <button type="button" class="btn btn-floating btn-cancel" onclick="window.history.back()">
                <i class="fa-solid fa-xmark"></i>
                <span>Cancel</span>
            </button>
            <button type="button" class="btn btn-floating btn-save" id="floatingSaveBtn">
                <i class="fa-solid fa-floppy-disk"></i>
                <span>Save Lead</span>
            </button>
        </div>
    </div>

@endsection

@push('scripts')
    <script src="{{ asset('js/leads/lead-form-navigation.js') }}"></script>
    <script src="{{ asset('js/leads/lead-form.js') }}"></script>
    <script>
        function clearStuckGlobalLoaderForLeadCreate() {
            const loader = document.querySelector('.loader');
            if (!loader) return;

            const styles = window.getComputedStyle(loader);
            const isVisible = styles.display !== 'none' && styles.visibility !== 'hidden' && styles.opacity !== '0';
            if (!isVisible) return;

            // Safety net for this page only: a stuck global loader can block all form inputs.
            loader.style.display = 'none';
            loader.style.opacity = '0';
            loader.style.visibility = 'hidden';
            loader.style.pointerEvents = 'none';
        }

        document.addEventListener('DOMContentLoaded', function() {
            clearStuckGlobalLoaderForLeadCreate();
            setTimeout(clearStuckGlobalLoaderForLeadCreate, 300);

            
            const floatingSaveBtn = document.getElementById('floatingSaveBtn');
            const hiddenSubmitBtn = document.getElementById('hiddenSubmitBtn');
            const form = document.getElementById('createLeadForm');
            const floatingContainer = document.querySelector('.floating-save-container');
            
            
            // Add form submit event listener for debugging
            form.addEventListener('submit', function(e) {
                
                // Check CSRF token
                const csrfToken = document.querySelector('input[name="_token"]');
                if (csrfToken) {
                }
            });
            
            // Add invalid event listener to show validation errors clearly
            form.addEventListener('invalid', function(e) {
                
                // Scroll to the first invalid field
                e.target.scrollIntoView({ behavior: 'smooth', block: 'center' });
                
                // Highlight the invalid field
                e.target.focus();
            }, true);
            
            // Handle floating save button click
            floatingSaveBtn.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Check form data
                const formData = new FormData(form);
                for (let [key, value] of formData.entries()) {
                }
                
                // Use requestSubmit() to trigger HTML5 validation and show error messages
                try {
                    // Try modern requestSubmit (triggers validation)
                    if (form.requestSubmit) {
                        form.requestSubmit();
                    } else {
                        // Fallback: trigger click on hidden submit button
                        hiddenSubmitBtn.click();
                    }
                } catch (error) {
                    console.error('Form submission error:', error);
                    // Last resort fallback
                    hiddenSubmitBtn.click();
                }
            });
            
            // Add scroll-based visibility control
            let lastScrollTop = 0;
            let ticking = false;
            
            function updateFloatingButton() {
                const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
                const windowHeight = window.innerHeight;
                const documentHeight = document.documentElement.scrollHeight;
                
                // Show button when not at the very top or bottom
                if (scrollTop > 100 && scrollTop < documentHeight - windowHeight - 100) {
                    floatingContainer.classList.remove('hidden');
                    floatingContainer.classList.add('visible');
                } else if (scrollTop <= 100) {
                    floatingContainer.classList.add('hidden');
                    floatingContainer.classList.remove('visible');
                }
                
                lastScrollTop = scrollTop;
                ticking = false;
            }
            
            function requestTick() {
                if (!ticking) {
                    requestAnimationFrame(updateFloatingButton);
                    ticking = true;
                }
            }
            
            window.addEventListener('scroll', requestTick);
            
            // Initialize button state
            updateFloatingButton();
            
            // Add keyboard shortcut for save (Ctrl+S or Cmd+S)
            document.addEventListener('keydown', function(e) {
                if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                    e.preventDefault();
                    // Trigger the floating save button click (which now properly validates)
                    floatingSaveBtn.click();
                }
            });
            
            // Add visual feedback for form changes
            const formInputs = form.querySelectorAll('input, select, textarea');
            let formChanged = false;
            
            formInputs.forEach(input => {
                input.addEventListener('change', function() {
                    formChanged = true;
                    updateSaveButtonState();
                });
                
                input.addEventListener('input', function() {
                    formChanged = true;
                    updateSaveButtonState();
                });
            });
            
            function updateSaveButtonState() {
                if (formChanged) {
                    floatingSaveBtn.style.background = 'linear-gradient(135deg, #dc3545 0%, #fd7e14 100%)';
                    floatingSaveBtn.querySelector('span').textContent = 'Save Changes';
                } else {
                    floatingSaveBtn.style.background = 'linear-gradient(135deg, #1e3d60 0%, #1e7a52 100%)';
                    floatingSaveBtn.querySelector('span').textContent = 'Save Lead';
                }
            }
        });

        window.addEventListener('load', clearStuckGlobalLoaderForLeadCreate);
    </script>
    
    <script>
    // Initialize form with at least one field in each required sections
    document.addEventListener('DOMContentLoaded', function() {
        // Add initial phone and email fields
        // Phone and email fields are now static HTML, no need to initialize dynamically
        
        // Display validation errors for phone and email fields
        displayFieldErrors();
        
        // Add real-time error clearing for phone and email fields
        setupErrorClearing();
        
        // DOB to Age calculation (same as client edit page)
        const dobField = document.getElementById('dob');
        const ageField = document.getElementById('age');
        if (dobField && ageField) {
            // Initialize age if DOB exists
            if (dobField.value) {
                ageField.value = calculateAge(dobField.value);
            }

            // Handle manual input changes (e.g., typing or pasting)
            dobField.addEventListener('input', function() {
                ageField.value = calculateAge(this.value);
            });
        }
    });
    
    // Initialize Flatpickr after all scripts are loaded
    $(document).ready(function() {
        // Wait a bit for all scripts to load
        setTimeout(function() {
            initDatePicker();
        }, 500);
        
        // Initialize company toggle functionality
        initCompanyToggle();
        initOtherPartyToggle();
        initContactPersonModeToggle();
        initSolicitorModeToggle();
        
        // Initialize contact person search and phone/email match check if company fields are visible
        @if(old('is_company') == 'yes')
            initContactPersonSearch();
            initSolicitorSearch();
            initContactMatchCheck();
        @endif

        // Has trading name toggle (company leads)
        $('input[name="has_trading_name"]').on('change', function() {
            $('#leadTradingNamesContainer').toggle($(this).val() === '1');
        });
    });

    function addLeadTradingName() {
        const container = $('#leadTradingNamesList');
        const idx = container.find('.trading-name-row').length;
        container.append('<div class="trading-name-row" style="display: flex; gap: 10px; margin-bottom: 8px; align-items: center;">' +
            '<input type="text" name="trading_names[]" placeholder="Trading name" class="company-field" style="flex: 1;">' +
            '<label><input type="radio" name="trading_name_primary" value="' + idx + '"> Primary</label>' +
            '<button type="button" class="btn btn-sm btn-outline-danger" onclick="removeLeadTradingName(this)"><i class="fa-solid fa-xmark"></i></button></div>');
        container.find('.trading-name-row').each(function(i) { $(this).find('input[name="trading_name_primary"]').val(i); });
    }
    function removeLeadTradingName(btn) {
        const container = $('#leadTradingNamesList');
        if (container.find('.trading-name-row').length <= 1) return;
        $(btn).closest('.trading-name-row').remove();
        $('#leadTradingNamesList .trading-name-row').each(function(i) { $(this).find('input[name="trading_name_primary"]').val(i); });
    }
    
    // Toggle between personal and company fields
    function toggleCompanyFields(isCompany) {
        const personalFields = document.getElementById('personalFields');
        const companyFields = document.getElementById('companyFields');
        
        // Get all fields that should be required for personal leads
        const personalRequiredFields = personalFields ? personalFields.querySelectorAll('[required]') : [];
        
        // Get all company required fields by class selector (more reliable than [required])
        const companyRequiredFields = companyFields ? companyFields.querySelectorAll('.company-required') : [];
        
        if (isCompany) {
            // Show company fields, hide personal fields
            if (personalFields) personalFields.style.display = 'none';
            if (companyFields) companyFields.style.display = 'block';
            
            // Remove required from personal fields
            personalRequiredFields.forEach(field => {
                field.removeAttribute('required');
            });
            
            // Add required to company fields
            companyRequiredFields.forEach(field => {
                field.setAttribute('required', 'required');
            });
            
            // Clear personal field values (optional)
            if (personalFields) {
                personalFields.querySelectorAll('input, select').forEach(field => {
                    if (field.type !== 'hidden' && field.id !== 'age') {
                        field.value = '';
                    }
                });
            }
            
            // Initialize contact person search when company fields are shown
            setTimeout(function() {
                initContactPersonSearch();
                initSolicitorSearch();
                initContactMatchCheck();
                initContactPersonModeToggle();
                initSolicitorModeToggle();
            }, 100);
        } else {
            // Show personal fields, hide company fields
            if (personalFields) personalFields.style.display = 'block';
            if (companyFields) companyFields.style.display = 'none';
            $('#associatedPersonAlert').hide();
            
            // Remove required from company fields
            companyRequiredFields.forEach(field => {
                field.removeAttribute('required');
            });
            
            // Add required to personal fields (respect other-party relaxed mode)
            if (!isOtherPartyMode()) {
                personalRequiredFields.forEach(field => {
                    field.setAttribute('required', 'required');
                });
            } else {
                toggleOtherPartyFields(true);
            }
            
            // Clear company field values (optional, but preserve contact person selection)
            if (companyFields) {
                companyFields.querySelectorAll('input, select').forEach(field => {
                    if (field.type !== 'hidden' && field.id !== 'contactPersonEmail') {
                        if (field.classList.contains('contact-person-field')) {
                            field.value = '';
                        } else {
                            field.value = '';
                        }
                    }
                });
            }
        }
    }
    
    // Initialize company toggle on page load
    function initCompanyToggle() {
        const isCompanyRadio = document.querySelector('input[name="is_company"][value="yes"]');
        const isPersonalRadio = document.querySelector('input[name="is_company"][value="no"]');
        
        // Set initial state based on old input or default
        const isCompany = @json(old('is_company') == 'yes');
        toggleCompanyFields(isCompany);
        
        // Add event listeners
        if (isCompanyRadio) {
            isCompanyRadio.addEventListener('change', function() {
                if (this.checked) {
                    toggleCompanyFields(true);
                }
            });
        }
        
        if (isPersonalRadio) {
            isPersonalRadio.addEventListener('change', function() {
                if (this.checked) {
                    toggleCompanyFields(false);
                }
            });
        }
    }

    function isOtherPartyMode() {
        const checkbox = document.getElementById('isOtherPartyCheckbox');
        return !!(checkbox && checkbox.checked);
    }

    function toggleOtherPartyFields(isOtherParty) {
        const relaxedFields = document.querySelectorAll('.other-party-relaxed-field');
        const optionalMarkers = document.querySelectorAll('.other-party-optional-marker');

        relaxedFields.forEach(field => {
            if (isOtherParty) {
                field.removeAttribute('required');
            } else if (!document.querySelector('input[name="is_company"][value="yes"]:checked')) {
                if (field.id === 'primaryPhoneInput' || field.id === 'primaryEmailInput' || field.id === 'dob' || field.id === 'gender') {
                    field.setAttribute('required', 'required');
                }
            }
        });

        optionalMarkers.forEach(marker => {
            marker.style.display = isOtherParty ? 'none' : '';
        });
    }

    function initOtherPartyToggle() {
        const checkbox = document.getElementById('isOtherPartyCheckbox');
        if (!checkbox) {
            return;
        }

        toggleOtherPartyFields(checkbox.checked);
        checkbox.addEventListener('change', function () {
            toggleOtherPartyFields(this.checked);
        });
    }
    
    function isContactPersonManualMode() {
        return document.getElementById('contactPersonModeManual')?.checked === true;
    }

    function setContactPersonMode(mode) {
        const isManual = mode === 'manual';
        const searchRadio = document.getElementById('contactPersonModeSearch');
        const manualRadio = document.getElementById('contactPersonModeManual');
        if (searchRadio) searchRadio.checked = !isManual;
        if (manualRadio) manualRadio.checked = isManual;
        toggleContactPersonMode(isManual);
    }

    function toggleContactPersonMode(isManual) {
        const searchWrap = document.getElementById('contactPersonSearchWrap');
        const manualActions = document.getElementById('contactPersonManualActions');
        const manualFlag = document.getElementById('contactPersonManualFlag');
        const searchSelect = document.getElementById('contactPersonEmail');
        const firstName = document.getElementById('contactPersonFirstName');
        const lastName = document.getElementById('contactPersonLastName');
        const phone = document.getElementById('contactPersonPhone');
        const email = document.getElementById('contactPersonEmailDisplay');
        const searchHints = document.querySelectorAll('.contact-person-search-hint');
        const manualRequiredMarkers = document.querySelectorAll('.contact-person-manual-required');
        const manualContactRequiredMarkers = document.querySelectorAll('.contact-person-manual-contact-required');
        const searchRequiredMarkers = document.querySelectorAll('.contact-person-search-required');

        if (manualFlag) {
            manualFlag.value = isManual ? '1' : '0';
        }
        if (searchWrap) {
            searchWrap.style.display = isManual ? 'none' : 'block';
        }
        if (manualActions) {
            manualActions.style.display = isManual ? 'block' : 'none';
        }

        [firstName, lastName, phone, email].forEach(function (field) {
            if (!field) return;
            if (isManual) {
                field.removeAttribute('readonly');
                field.classList.remove('field-auto-filled');
            } else {
                field.setAttribute('readonly', 'readonly');
            }
        });

        if (searchSelect) {
            if (isManual) {
                searchSelect.removeAttribute('required');
                searchSelect.classList.remove('company-required');
                if (searchSelect.tomselect) {
                    searchSelect.tomselect.disable();
                }
            } else {
                searchSelect.removeAttribute('required');
                searchSelect.classList.remove('company-required');
                if (searchSelect.tomselect) {
                    searchSelect.tomselect.enable();
                }
            }
        }

        if (firstName) {
            if (isManual) firstName.setAttribute('required', 'required');
            else firstName.removeAttribute('required');
        }
        if (lastName) {
            if (isManual) lastName.setAttribute('required', 'required');
            else lastName.removeAttribute('required');
        }
        if (phone) phone.removeAttribute('required');
        if (email) email.removeAttribute('required');

        searchHints.forEach(function (el) {
            el.style.display = isManual ? 'none' : '';
        });
        manualRequiredMarkers.forEach(function (el) {
            el.style.display = isManual ? '' : 'none';
        });
        manualContactRequiredMarkers.forEach(function (el) {
            el.style.display = isManual ? '' : 'none';
        });
        searchRequiredMarkers.forEach(function (el) {
            el.style.display = isManual ? 'none' : '';
        });

        if (isManual) {
            $('#associatedPersonAlert').hide();
        }
    }

    function initContactPersonModeToggle() {
        const searchRadio = document.getElementById('contactPersonModeSearch');
        const manualRadio = document.getElementById('contactPersonModeManual');
        if (!searchRadio || !manualRadio) {
            return;
        }

        toggleContactPersonMode(isContactPersonManualMode());

        searchRadio.addEventListener('change', function () {
            if (this.checked) {
                toggleContactPersonMode(false);
            }
        });
        manualRadio.addEventListener('change', function () {
            if (this.checked) {
                toggleContactPersonMode(true);
                clearLeadContactPersonFields(false);
            }
        });

        $('#saveManualContactPersonBtn').off('click.manualContact').on('click.manualContact', saveManualContactPerson);
    }

    function selectCreatedContactPerson(person) {
        var selEl = document.getElementById('contactPersonEmail');
        if (!selEl) return;

        if (selEl.tomselect) {
            var ts = selEl.tomselect;
            ts.enable();
            ts.clear(true);
            ts.addOption({
                id: person.id,
                text: person.text,
                first_name: person.first_name || '',
                last_name: person.last_name || '',
                email: person.email || '',
                phone: person.phone || '',
                client_id: person.client_id != null && person.client_id !== '' ? person.client_id : ''
            });
            ts.setValue(String(person.id), true);
            fillLeadContactPersonFields(ts.options[person.id] || ts.options[String(person.id)] || person);
        } else {
            var $select = $('#contactPersonEmail');
            $select.find('option').not('[value=""]').remove();
            var option = new Option(person.text, person.id, true, true);
            $select.append(option).trigger('change');
            fillLeadContactPersonFields(person);
        }
    }

    function showManualContactPersonMessage(type, message) {
        var box = document.getElementById('manualContactPersonMessage');
        if (!box) return;
        box.style.display = 'block';
        box.className = 'mt-2 alert alert-' + (type === 'success' ? 'success' : 'danger');
        box.textContent = message;
    }

    function saveManualContactPerson() {
        var payload = {
            _token: '{{ csrf_token() }}',
            first_name: ($('#contactPersonFirstName').val() || '').trim(),
            last_name: ($('#contactPersonLastName').val() || '').trim(),
            phone: ($('#contactPersonPhone').val() || '').trim(),
            email: ($('#contactPersonEmailDisplay').val() || '').trim()
        };

        if (!payload.first_name || !payload.last_name) {
            showManualContactPersonMessage('error', 'First name and last name are required.');
            return;
        }
        if (!payload.phone && !payload.email) {
            showManualContactPersonMessage('error', 'Phone or email is required.');
            return;
        }

        var btn = document.getElementById('saveManualContactPersonBtn');
        if (btn) btn.disabled = true;

        $.ajax({
            url: '{{ route("leads.store.contact_person.mini") }}',
            method: 'POST',
            data: payload,
            success: function (res) {
                if (res.success && res.person) {
                    selectCreatedContactPerson(res.person);
                    setContactPersonMode('search');
                    showManualContactPersonMessage('success', 'Contact person saved. They can now be searched next time.');
                } else {
                    showManualContactPersonMessage('error', res.message || 'Could not save contact person.');
                }
            },
            error: function (xhr) {
                var msg = 'Could not save contact person.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                }
                showManualContactPersonMessage('error', msg);
            },
            complete: function () {
                if (btn) btn.disabled = false;
            }
        });
    }
    
    function fillLeadContactPersonFields(item) {
        if (!item) return;
        $('#contactPersonFirstName').val(item.first_name || '');
        $('#contactPersonLastName').val(item.last_name || '');
        $('#contactPersonPhone').val(item.phone || '');
        $('#contactPersonEmailDisplay').val(item.email || '');
        $('.contact-person-field').addClass('field-auto-filled');
    }

    function clearLeadContactPersonFields(clearSearch) {
        if (clearSearch !== false) {
            var selEl = document.getElementById('contactPersonEmail');
            if (selEl && selEl.tomselect) {
                selEl.tomselect.clear(true);
            } else {
                $('#contactPersonEmail').val('').trigger('change');
            }
        }
        $('#contactPersonFirstName').val('');
        $('#contactPersonLastName').val('');
        $('#contactPersonPhone').val('');
        $('#contactPersonEmailDisplay').val('');
        $('.contact-person-field').removeClass('field-auto-filled');
        $('#associatedPersonAlert').hide();
    }

    /** Tom Select AJAX for company lead contact person (#contactPersonEmail). */
    function initContactPersonSearch() {
        var el = document.getElementById('contactPersonEmail');
        if (!el) return;
        if (typeof TomSelect === 'undefined' || typeof initTS !== 'function' || typeof buildContactPersonSearchTomSelectConfig !== 'function') {
            console.warn('Tom Select helpers not loaded. Contact person search will not work.');
            return;
        }
        if (el.tomselect) {
            return;
        }
        var cfg = buildContactPersonSearchTomSelectConfig({
            url: '{{ route("api.search.contact.person") }}',
            dropdownParent: 'body',
            placeholder: 'Type phone, email, name, or client ID to search...',
            minQueryLength: 2
        });
        cfg.onItemAdd = function (value) {
            var item = this.options[value] || this.options[String(value)];
            fillLeadContactPersonFields(item);
        };
        cfg.onClear = function () {
            clearLeadContactPersonFields();
        };
        initTS(el, cfg);
    }

    function isSolicitorManualMode() {
        return document.getElementById('solicitorModeManual')?.checked === true;
    }

    function setSolicitorMode(mode) {
        const isManual = mode === 'manual';
        const searchRadio = document.getElementById('solicitorModeSearch');
        const manualRadio = document.getElementById('solicitorModeManual');
        if (searchRadio) searchRadio.checked = !isManual;
        if (manualRadio) manualRadio.checked = isManual;
        toggleSolicitorMode(isManual);
    }

    function toggleSolicitorMode(isManual) {
        const searchWrap = document.getElementById('solicitorSearchWrap');
        const manualActions = document.getElementById('solicitorManualActions');
        const manualFlag = document.getElementById('solicitorManualFlag');
        const searchSelect = document.getElementById('solicitorSearch');
        const firstName = document.getElementById('solicitorFirstName');
        const lastName = document.getElementById('solicitorLastName');
        const phone = document.getElementById('solicitorPhone');
        const email = document.getElementById('solicitorEmailDisplay');
        const searchHints = document.querySelectorAll('.solicitor-search-hint');
        const manualRequiredMarkers = document.querySelectorAll('.solicitor-manual-required');
        const manualContactRequiredMarkers = document.querySelectorAll('.solicitor-manual-contact-required');

        if (manualFlag) {
            manualFlag.value = isManual ? '1' : '0';
        }
        if (searchWrap) {
            searchWrap.style.display = isManual ? 'none' : 'block';
        }
        if (manualActions) {
            manualActions.style.display = isManual ? 'block' : 'none';
        }

        [firstName, lastName, phone, email].forEach(function (field) {
            if (!field) return;
            if (isManual) {
                field.removeAttribute('readonly');
                field.classList.remove('field-auto-filled');
            } else {
                field.setAttribute('readonly', 'readonly');
            }
        });

        if (searchSelect && searchSelect.tomselect) {
            if (isManual) {
                searchSelect.tomselect.disable();
            } else {
                searchSelect.tomselect.enable();
            }
        }

        if (firstName) {
            if (isManual) firstName.setAttribute('required', 'required');
            else firstName.removeAttribute('required');
        }
        if (lastName) {
            if (isManual) lastName.setAttribute('required', 'required');
            else lastName.removeAttribute('required');
        }
        if (phone) phone.removeAttribute('required');
        if (email) email.removeAttribute('required');

        searchHints.forEach(function (el) {
            el.style.display = isManual ? 'none' : '';
        });
        manualRequiredMarkers.forEach(function (el) {
            el.style.display = isManual ? '' : 'none';
        });
        manualContactRequiredMarkers.forEach(function (el) {
            el.style.display = isManual ? '' : 'none';
        });
    }

    function initSolicitorModeToggle() {
        const searchRadio = document.getElementById('solicitorModeSearch');
        const manualRadio = document.getElementById('solicitorModeManual');
        if (!searchRadio || !manualRadio) {
            return;
        }

        toggleSolicitorMode(isSolicitorManualMode());

        searchRadio.addEventListener('change', function () {
            if (this.checked) {
                toggleSolicitorMode(false);
            }
        });
        manualRadio.addEventListener('change', function () {
            if (this.checked) {
                toggleSolicitorMode(true);
                clearSolicitorFields(false);
            }
        });

        $('#saveManualSolicitorBtn').off('click.manualSolicitor').on('click.manualSolicitor', saveManualSolicitor);
    }

    function fillSolicitorFields(item) {
        if (!item) return;
        $('#solicitorFirstName').val(item.first_name || '');
        $('#solicitorLastName').val(item.last_name || '');
        $('#solicitorPhone').val(item.phone || '');
        $('#solicitorEmailDisplay').val(item.email || '');
        $('.solicitor-field').addClass('field-auto-filled');
    }

    function clearSolicitorFields(clearSearch) {
        if (clearSearch !== false) {
            var selEl = document.getElementById('solicitorSearch');
            if (selEl && selEl.tomselect) {
                selEl.tomselect.clear(true);
            } else {
                $('#solicitorSearch').val('').trigger('change');
            }
        }
        $('#solicitorFirstName').val('');
        $('#solicitorLastName').val('');
        $('#solicitorPhone').val('');
        $('#solicitorEmailDisplay').val('');
        $('.solicitor-field').removeClass('field-auto-filled');
    }

    function selectCreatedSolicitor(person) {
        var selEl = document.getElementById('solicitorSearch');
        if (!selEl) return;

        if (selEl.tomselect) {
            var ts = selEl.tomselect;
            ts.enable();
            ts.clear(true);
            ts.addOption({
                id: person.id,
                text: person.text,
                first_name: person.first_name || '',
                last_name: person.last_name || '',
                email: person.email || '',
                phone: person.phone || '',
                client_id: person.client_id != null && person.client_id !== '' ? person.client_id : ''
            });
            ts.setValue(String(person.id), true);
            fillSolicitorFields(ts.options[person.id] || ts.options[String(person.id)] || person);
        } else {
            var $select = $('#solicitorSearch');
            $select.find('option').not('[value=""]').remove();
            var option = new Option(person.text, person.id, true, true);
            $select.append(option).trigger('change');
            fillSolicitorFields(person);
        }
    }

    function showManualSolicitorMessage(type, message) {
        var box = document.getElementById('manualSolicitorMessage');
        if (!box) return;
        box.style.display = 'block';
        box.className = 'mt-2 alert alert-' + (type === 'success' ? 'success' : 'danger');
        box.textContent = message;
    }

    function saveManualSolicitor() {
        var payload = {
            _token: '{{ csrf_token() }}',
            first_name: ($('#solicitorFirstName').val() || '').trim(),
            last_name: ($('#solicitorLastName').val() || '').trim(),
            phone: ($('#solicitorPhone').val() || '').trim(),
            email: ($('#solicitorEmailDisplay').val() || '').trim()
        };

        if (!payload.first_name || !payload.last_name) {
            showManualSolicitorMessage('error', 'First name and last name are required.');
            return;
        }
        if (!payload.phone && !payload.email) {
            showManualSolicitorMessage('error', 'Phone or email is required.');
            return;
        }

        var btn = document.getElementById('saveManualSolicitorBtn');
        if (btn) btn.disabled = true;

        $.ajax({
            url: '{{ route("leads.store.contact_person.mini") }}',
            method: 'POST',
            data: payload,
            success: function (res) {
                if (res.success && res.person) {
                    selectCreatedSolicitor(res.person);
                    setSolicitorMode('search');
                    showManualSolicitorMessage('success', 'Solicitor saved. They can now be searched next time.');
                } else {
                    showManualSolicitorMessage('error', res.message || 'Could not save solicitor.');
                }
            },
            error: function (xhr) {
                var msg = 'Could not save solicitor.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                }
                showManualSolicitorMessage('error', msg);
            },
            complete: function () {
                if (btn) btn.disabled = false;
            }
        });
    }

    function initSolicitorSearch() {
        var el = document.getElementById('solicitorSearch');
        if (!el) return;
        if (typeof TomSelect === 'undefined' || typeof initTS !== 'function' || typeof buildContactPersonSearchTomSelectConfig !== 'function') {
            return;
        }
        if (el.tomselect) {
            return;
        }
        var cfg = buildContactPersonSearchTomSelectConfig({
            url: '{{ route("api.search.contact.person") }}',
            dropdownParent: 'body',
            placeholder: 'Type phone, email, name, or client ID to search...',
            minQueryLength: 2
        });
        cfg.onItemAdd = function (value) {
            var item = this.options[value] || this.options[String(value)];
            fillSolicitorFields(item);
        };
        cfg.onClear = function () {
            clearSolicitorFields();
        };
        initTS(el, cfg);
    }
    
    // Check phone/email for matching contact person (company leads only)
    var contactMatchTimeout = null;
    function initContactMatchCheck() {
        const phoneInput = document.getElementById('primaryPhoneInput');
        const emailInput = document.getElementById('primaryEmailInput');
        if (!phoneInput || !emailInput) return;
        
        function checkContactMatch() {
            const isCompany = document.querySelector('input[name="is_company"][value="yes"]')?.checked;
            if (!isCompany) {
                $('#associatedPersonAlert').hide();
                return;
            }
            const phone = (phoneInput.value || '').trim();
            const email = (emailInput.value || '').trim();
            if (!phone && !email) {
                $('#associatedPersonAlert').hide();
                return;
            }
            $.ajax({
                url: '{{ route("leads.check.contact.match") }}',
                method: 'GET',
                data: { phone: phone, email: email },
                success: function(res) {
                    if (res.found && res.person) {
                        $('#associatedPersonName').text(res.person.first_name + ' ' + res.person.last_name + (res.person.client_id ? ' (' + res.person.client_id + ')' : ''));
                        $('#associatedPersonAlert').show();
                        var selEl = document.getElementById('contactPersonEmail');
                        if (selEl && selEl.tomselect) {
                            var ts = selEl.tomselect;
                            var p = res.person;
                            ts.clear(true);
                            ts.addOption({
                                id: p.id,
                                text: p.text,
                                first_name: p.first_name || '',
                                last_name: p.last_name || '',
                                email: p.email || '',
                                phone: p.phone || '',
                                client_id: p.client_id != null && p.client_id !== '' ? p.client_id : ''
                            });
                            ts.setValue(String(p.id), true);
                            var opt = ts.options[p.id] || ts.options[String(p.id)];
                            fillLeadContactPersonFields(opt || p);
                        } else {
                            const $select = $('#contactPersonEmail');
                            const existingOpt = $select.find('option[value="' + res.person.id + '"]');
                            if (existingOpt.length) {
                                $select.val(res.person.id).trigger('change');
                            } else {
                                const option = new Option(res.person.text, res.person.id, true, true);
                                $select.append(option).trigger('change');
                            }
                            $('#contactPersonFirstName').val(res.person.first_name || '');
                            $('#contactPersonLastName').val(res.person.last_name || '');
                            $('#contactPersonPhone').val(res.person.phone || '');
                            $('#contactPersonEmailDisplay').val(res.person.email || '');
                            $('.contact-person-field').addClass('field-auto-filled');
                        }
                    } else {
                        $('#associatedPersonAlert').hide();
                    }
                }
            });
        }
        
        function debouncedCheck() {
            clearTimeout(contactMatchTimeout);
            contactMatchTimeout = setTimeout(checkContactMatch, 400);
        }
        
        $(phoneInput).off('blur.contactMatch input.contactMatch').on('blur.contactMatch input.contactMatch', debouncedCheck);
        $(emailInput).off('blur.contactMatch input.contactMatch').on('blur.contactMatch input.contactMatch', debouncedCheck);
    }
    
    // Function to display validation errors for each field
    function displayFieldErrors() {
        // Get all error messages from Laravel
        const errors = @json($errors->all());
        const errorBag = @json($errors->getMessageBag()->toArray());
        
        // Clear any existing error messages first
        document.querySelectorAll('.phone-error, .email-error, .field-error').forEach(el => {
            el.textContent = '';
            el.style.display = 'none';
        });
        
        // Check if we have field-specific errors
        let hasFieldSpecificErrors = false;
        
        // Display phone errors
        Object.keys(errorBag).forEach(key => {
            if (key.startsWith('phone.')) {
                hasFieldSpecificErrors = true;
                const index = key.split('.')[1];
                const errorElement = document.querySelector(`.phone-error-${index}`);
                if (errorElement) {
                    errorElement.textContent = errorBag[key][0];
                    errorElement.style.display = 'block';
                    errorElement.style.color = '#dc3545';
                    errorElement.style.fontSize = '12px';
                    errorElement.style.marginTop = '5px';
                }
            } else if (key === 'phone') {
                // General phone error - show in the section
                const phoneContainer = document.getElementById('phoneNumbersContainer');
                if (phoneContainer) {
                    // Remove existing general error
                    const existingError = phoneContainer.querySelector('.general-phone-error');
                    if (existingError) {
                        existingError.remove();
                    }
                    
                    const errorDiv = document.createElement('div');
                    errorDiv.className = 'general-phone-error text-danger';
                    errorDiv.style.marginTop = '10px';
                    errorDiv.style.fontSize = '12px';
                    errorDiv.textContent = errorBag[key][0];
                    phoneContainer.appendChild(errorDiv);
                }
            }
        });
        
        // Display email errors
        Object.keys(errorBag).forEach(key => {
            if (key.startsWith('email.')) {
                hasFieldSpecificErrors = true;
                const index = key.split('.')[1];
                const errorElement = document.querySelector(`.email-error-${index}`);
                if (errorElement) {
                    errorElement.textContent = errorBag[key][0];
                    errorElement.style.display = 'block';
                    errorElement.style.color = '#dc3545';
                    errorElement.style.fontSize = '12px';
                    errorElement.style.marginTop = '5px';
                }
            } else if (key === 'email') {
                // General email error - show in the section
                const emailContainer = document.getElementById('emailAddressesContainer');
                if (emailContainer) {
                    // Remove existing general error
                    const existingError = emailContainer.querySelector('.general-email-error');
                    if (existingError) {
                        existingError.remove();
                    }
                    
                    const errorDiv = document.createElement('div');
                    errorDiv.className = 'general-email-error text-danger';
                    errorDiv.style.marginTop = '10px';
                    errorDiv.style.fontSize = '12px';
                    errorDiv.textContent = errorBag[key][0];
                    emailContainer.appendChild(errorDiv);
                }
            }
        });
        
        // Hide general error container if we have field-specific errors
        const generalErrorContainer = document.querySelector('.form-validation-errors');
        if (generalErrorContainer) {
            if (hasFieldSpecificErrors) {
                generalErrorContainer.style.display = 'none';
            } else {
                generalErrorContainer.style.display = 'block';
            }
        }
    }
    
    // Function to setup error clearing when user types
    function setupErrorClearing() {
        // Clear phone errors when user types
        document.addEventListener('input', function(e) {
            if (e.target.classList.contains('phone-number-input')) {
                const index = e.target.name.match(/\[(\d+)\]/)[1];
                const errorElement = document.querySelector(`.phone-error-${index}`);
                if (errorElement) {
                    errorElement.style.display = 'none';
                    errorElement.textContent = '';
                }
            }
            
            if (e.target.classList.contains('email-input')) {
                const index = e.target.name.match(/\[(\d+)\]/)[1];
                const errorElement = document.querySelector(`.email-error-${index}`);
                if (errorElement) {
                    errorElement.style.display = 'none';
                    errorElement.textContent = '';
                }
            }
        });
    }
    
    // Function to initialize Flatpickr for DOB field
    function initDatePicker() {
        try {
            // Check if Flatpickr is available
            if (typeof flatpickr !== 'undefined') {
                const dobInput = document.getElementById('dob');
                const ageInput = document.getElementById('age');
                
                if (dobInput && ageInput) {
                    // Check if already initialized
                    if ($(dobInput).data('flatpickr')) {
                        return;
                    }
                    
                    // Initialize Flatpickr for DOB field
                    flatpickr(dobInput, {
                        dateFormat: 'd/m/Y',
                        allowInput: true,
                        clickOpens: true,
                        defaultDate: dobInput.value || null,
                        maxDate: 'today', // DOB cannot be in the future
                        minDate: '01/01/1000',
                        locale: {
                            firstDayOfWeek: 1 // Monday
                        },
                        onChange: function(selectedDates, dateStr, instance) {
                            // Update age when date is selected
                            dobInput.value = dateStr;
                            ageInput.value = calculateAge(dateStr);
                        }
                    });
                    
                }
            } else {
                console.warn('⚠️ Flatpickr not available');
            }
        } catch(e) {
            console.error('❌ Flatpickr initialization failed:', e.message);
        }
    }
    
    // Age calculation function (same as client edit page)
    function calculateAge(dob) {
        if (!dob || !/^\d{2}\/\d{2}\/\d{4}$/.test(dob)) return '';

        try {
            const [day, month, year] = dob.split('/').map(Number);
            const dobDate = new Date(year, month - 1, day);
            if (isNaN(dobDate.getTime())) return ''; // Invalid date

            const today = new Date();
            let years = today.getFullYear() - dobDate.getFullYear();
            let months = today.getMonth() - dobDate.getMonth();

            if (months < 0) {
                years--;
                months += 12;
            }

            if (today.getDate() < dobDate.getDate()) {
                months--;
                if (months < 0) {
                    years--;
                    months += 12;
                }
            }

            return years + ' years ' + months + ' months';
        } catch (e) {
            return '';
        }
    }
    
    </script>
    <script>
        (function () {
            function syncLeadFollowupDateVisibility() {
                var sel = document.getElementById('lead_pipeline_status');
                var wrap = document.getElementById('lead_followup_date_wrap');
                if (!sel || !wrap) return;
                wrap.style.display = sel.value === 'follow_up' ? 'block' : 'none';
            }
            document.addEventListener('DOMContentLoaded', function () {
                var sel = document.getElementById('lead_pipeline_status');
                if (sel) {
                    sel.addEventListener('change', syncLeadFollowupDateVisibility);
                    syncLeadFollowupDateVisibility();
                }
            });
        })();
    </script>
@endpush

