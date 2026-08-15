@extends('layouts.crm_client_detail_dashboard')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/address-autocomplete.css') }}">
    <link rel="stylesheet" href="{{asset('css/client-forms.css')}}">
    <link rel="stylesheet" href="{{asset('css/clients/edit-client-components.css')}}">
    <link rel="stylesheet" href="{{asset('css/leads/lead-form.css')}}">
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
                    <h3><i class="fa-solid fa-user-edit"></i> Edit Lead : {{ $fetchedData->first_name }} {{ $fetchedData->last_name }}</h3>
                    <div class="client-id">
                        Lead ID : {{ $fetchedData->client_id }}
                    </div>
                </div>
                <nav class="nav-menu">
                    <button class="nav-item active" onclick="scrollToSection('personalSection')">
                        <i class="fa-solid fa-user-circle"></i>
                        <span>Personal</span>
                    </button>
                </nav>
                
                <!-- Back Button in Sidebar -->
                <div class="sidebar-actions">
                    <button type="button" class="btn btn-primary" style="width: 100%; margin-bottom: 10px;" onclick="document.getElementById('editLeadForm').submit();">
                        <i class="fa-solid fa-floppy-disk"></i>
                        <span>Save Lead</span>
                    </button>
                    <a href="{{route('leads.index')}}" class="nav-item summary-nav back-btn">
                        <i class="fa-solid fa-arrow-left"></i>
                        <span>Back to Leads</span>
                    </a>
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
            </script>

            <!-- Main Content Area -->
            <div class="main-content-area">
                <form id="editLeadForm" action="{{ route('leads.update', base64_encode(convert_uuencode($fetchedData->id))) }}" method="POST" enctype="multipart/form-data">
				@csrf
                    @method('PUT')
                    <input type="hidden" name="id" value="{{ $fetchedData->id }}">
                    <input type="hidden" name="type" value="{{ $fetchedData->type }}">

                <!-- Personal Section -->
                <section id="personalSection" class="content-section">
                    <section class="form-section">
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
                                @if($fetchedData->type === 'lead')
                                    @php
                                        $sumStage = $fetchedData->lead_status ?: 'new';
                                        $sumStageLabel = ($leadStageLabels[$sumStage] ?? ucfirst(str_replace('_', ' ', $sumStage)));
                                    @endphp
                                    <div class="summary-item">
                                        <span class="summary-label">Lead stage:</span>
                                        <span class="summary-value">{{ $sumStageLabel }}</span>
                                    </div>
                                    <div class="summary-item">
                                        <span class="summary-label">Follow-up date:</span>
                                        <span class="summary-value">
                                            @if($fetchedData->lead_status === 'follow_up' && $fetchedData->followup_date)
                                                {{ $fetchedData->followup_date instanceof \Carbon\Carbon ? $fetchedData->followup_date->format('d/m/Y') : \Carbon\Carbon::parse($fetchedData->followup_date)->format('d/m/Y') }}
                                            @else
                                                —
                                            @endif
                                        </span>
                                    </div>
                                    <div class="summary-item">
                                        <span class="summary-label">Assigned to:</span>
                                        <span class="summary-value">
                                            @if($fetchedData->assignedTo)
                                                {{ $fetchedData->assignedTo->first_name }} {{ $fetchedData->assignedTo->last_name }}
                                            @else
                                                —
                                            @endif
                                        </span>
                                    </div>
                                @endif
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
                                    <input type="text" id="dob" name="dob" value="{{ $fetchedData->dob ? date('d/m/Y', strtotime($fetchedData->dob)) : '' }}" placeholder="dd/mm/yyyy" autocomplete="off">
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
                                @if($fetchedData->type === 'lead')
                                    @php
                                        $editStage = old('lead_status', $fetchedData->lead_status ?: 'new');
                                        $editFu = old('followup_date');
                                        if ($editFu === null && $fetchedData->followup_date) {
                                            $editFu = $fetchedData->followup_date instanceof \Carbon\Carbon
                                                ? $fetchedData->followup_date->format('Y-m-d')
                                                : \Carbon\Carbon::parse($fetchedData->followup_date)->format('Y-m-d');
                                        }
                                    @endphp
                                    <div class="form-group">
                                        <label for="lead_pipeline_status_edit">Lead stage</label>
                                        <select id="lead_pipeline_status_edit" name="lead_status" class="form-control">
                                            @foreach(($leadStageLabels ?? []) as $val => $lbl)
                                                <option value="{{ $val }}" {{ $editStage === $val ? 'selected' : '' }}>{{ $lbl }}</option>
                                            @endforeach
                                        </select>
                                        @error('lead_status')
                                            <span class="text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>
                                    <div class="form-group" id="lead_followup_date_wrap_edit" style="display: none;">
                                        <label for="lead_followup_date_edit">Follow-up date <span class="text-muted">(optional)</span></label>
                                        <input type="date" id="lead_followup_date_edit" name="followup_date" class="form-control" value="{{ $editFu }}">
                                        @error('followup_date')
                                            <span class="text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>
                                    <div class="form-group">
                                        <label for="assigned_staff_id_edit">Assigned to</label>
                                        <select id="assigned_staff_id_edit" name="assigned_staff_id" class="form-control">
                                            @foreach(($assignableStaff ?? collect()) as $st)
                                                <option value="{{ $st->id }}" {{ (string) old('assigned_staff_id', $fetchedData->user_id) === (string) $st->id ? 'selected' : '' }}>
                                                    {{ $st->first_name }} {{ $st->last_name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('assigned_staff_id')
                                            <span class="text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>
                                    <div class="form-group">
                                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                            <input type="checkbox" name="is_other_party" value="1"
                                                {{ old('is_other_party', $fetchedData->is_other_party ?? false) ? 'checked' : '' }}>
                                            <span>Other party <small class="text-muted">(appears in Other Parties list, not sales pipeline)</small></span>
                                        </label>
                                    </div>
                                @endif
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
                                            <!-- Verification Button/Badge -->
                                            @if($contact->canVerify())
                                                @if($contact->is_verified)
                                                    <span class="verified-badge" title="Verified on {{ $contact->verified_at ? $contact->verified_at->format('M j, Y g:i A') : 'Unknown' }}">
                                                        <i class="fa-solid fa-circle-check"></i> Verified
														</span> 
                                                @else
                                                    <button type="button" class="btn-verify-phone" onclick="sendOTP({{ $contact->id }}, '{{ $contact->phone }}', '{{ $contact->country_code }}')" data-contact-id="{{ $contact->id }}">
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
                </section>

                <!-- Current Address Section -->
                <section id="addressSection" class="content-section">
                    <x-client-edit.address-section 
                        :clientAddresses="$clientAddresses"
                        :searchRoute="route('clients.searchAddressFull')"
                        :detailsRoute="route('clients.getPlaceDetails')"
                        :csrfToken="csrf_token()"
                    />
                </section>
                </form>
            </div>
        </div>
    </div>

    <!-- Go to Top Button -->
    <button id="goToTopBtn" class="go-to-top-btn" onclick="scrollToTop()" title="Go to Top">
        <i class="fa-solid fa-chevron-up"></i>
    </button>


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
        window.countriesData = @json($countries ?? []);

        // Lead edit uses Summary/Edit sections like client edit, but historically missed the JS helpers.
        // Provide minimal, safe helpers so the pencil buttons work.
        window.toggleEditMode = window.toggleEditMode || function(sectionType) {
            const summaryView = document.getElementById(sectionType + 'Summary');
            const editView = document.getElementById(sectionType + 'Edit');
            if (!summaryView || !editView) return;

            summaryView.style.display = 'none';
            summaryView.classList.add('hidden');

            editView.style.display = 'block';
            editView.classList.remove('hidden');
        };

        window.cancelEdit = window.cancelEdit || function(sectionType) {
            const summaryView = document.getElementById(sectionType + 'Summary');
            const editView = document.getElementById(sectionType + 'Edit');
            if (!summaryView || !editView) return;

            editView.style.display = 'none';
            editView.classList.add('hidden');

            summaryView.style.display = 'block';
            summaryView.classList.remove('hidden');
        };

        (function () {
            function syncLeadFollowupDateEdit() {
                var sel = document.getElementById('lead_pipeline_status_edit');
                var wrap = document.getElementById('lead_followup_date_wrap_edit');
                if (!sel || !wrap) return;
                wrap.style.display = sel.value === 'follow_up' ? 'block' : 'none';
            }
            document.addEventListener('DOMContentLoaded', function () {
                var sel = document.getElementById('lead_pipeline_status_edit');
                if (sel) {
                    sel.addEventListener('change', syncLeadFollowupDateEdit);
                    syncLeadFollowupDateEdit();
                }
            });
        })();
    </script>
    <script src="{{asset('js/address-autocomplete.js')}}"></script>
    <script src="{{asset('js/clients/address-regional-codes.js')}}"></script>
    <script src="{{asset('js/leads/lead-form-navigation.js')}}"></script>
    {{-- Google Maps library removed - using backend proxy for address autocomplete --}}
    @endpush
@endsection