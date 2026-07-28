@extends('layouts.crm_client_detail')
@section('title', 'SMS Template Studio & Library')

@section('content')
<style>
/* Modern SPA Styling for SMS Templates Studio */
.sms-template-spa {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    color: #334155;
}

/* Glassmorphism Header */
.template-header-card {
    background: linear-gradient(135deg, #1e1b4b 0%, #312e81 50%, #4338ca 100%);
    border-radius: 16px;
    padding: 24px 30px;
    color: #ffffff;
    box-shadow: 0 10px 25px -5px rgba(67, 56, 202, 0.3);
    margin-bottom: 24px;
    position: relative;
    overflow: hidden;
}
.template-header-card::after {
    content: '';
    position: absolute;
    top: -50%;
    right: -10%;
    width: 300px;
    height: 300px;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0) 70%);
    pointer-events: none;
}

/* Nav Pills */
.template-nav-pills {
    display: flex;
    gap: 8px;
    background: #f1f5f9;
    padding: 6px;
    border-radius: 12px;
    margin-bottom: 24px;
    overflow-x: auto;
}
.template-nav-link {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 18px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.9rem;
    color: #64748b;
    text-decoration: none !important;
    transition: all 0.2s ease;
    border: none;
    background: transparent;
    cursor: pointer;
    white-space: nowrap;
}
.template-nav-link:hover {
    color: #1e293b;
    background: rgba(255,255,255,0.6);
}
.template-nav-link.active {
    background: #ffffff;
    color: #4338ca;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}

/* Template Cards */
.template-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 20px;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    height: 100%;
    display: flex;
    flex-direction: column;
}
.template-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.06);
}

/* Variable Chips */
.var-chip {
    display: inline-flex;
    align-items: center;
    background: #e0e7ff;
    color: #3730a3;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 0.78rem;
    font-weight: 600;
    cursor: pointer;
    user-select: none;
    transition: background 0.15s ease;
    margin-right: 4px;
    margin-bottom: 4px;
}
.var-chip:hover {
    background: #c7d2fe;
    color: #1e1b4b;
}

/* Interactive Variable Tag Buttons */
.var-btn {
    transition: all 0.15s ease;
    border-radius: 8px;
    font-size: 0.82rem;
    background: #ffffff;
    border: 1px solid #cbd5e1;
    color: #334155;
}
.var-btn:hover {
    background: #4338ca;
    color: #ffffff;
    border-color: #4338ca;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(67, 56, 202, 0.2);
}

/* Mobile Phone Preview Screen */
.phone-mockup {
    width: 100%;
    max-width: 310px;
    height: 400px;
    background: #0f172a;
    border-radius: 36px;
    padding: 16px 12px;
    box-shadow: 0 20px 30px -10px rgba(15, 23, 42, 0.4);
    border: 4px solid #334155;
    margin: 0 auto;
    display: flex;
    flex-direction: column;
}
.phone-notch {
    width: 100px;
    height: 16px;
    background: #334155;
    border-radius: 0 0 10px 10px;
    margin: 0 auto 10px auto;
}
.phone-screen {
    background: #f8fafc;
    border-radius: 20px;
    flex: 1;
    padding: 12px;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    justify-content: flex-end;
}
.sms-bubble {
    background: #4338ca;
    color: #ffffff;
    border-radius: 16px 16px 4px 16px;
    padding: 10px 14px;
    font-size: 0.85rem;
    max-width: 88%;
    align-self: flex-end;
    margin-bottom: 8px;
    line-height: 1.4;
    word-break: break-word;
    box-shadow: 0 2px 6px rgba(67, 56, 202, 0.25);
}

/* Floating Toast Notification */
#tmplToast {
    position: fixed;
    top: 24px;
    right: 24px;
    z-index: 9999;
    min-width: 300px;
    display: none;
    box-shadow: 0 10px 25px rgba(0,0,0,0.15);
    border-radius: 10px;
}
</style>

<!-- Floating Toast -->
<div id="tmplToast" class="alert alert-dismissible fade show" role="alert">
    <div class="d-flex align-items-center gap-2">
        <i id="tmplToastIcon" class="fa-solid fa-circle-check fs-5"></i>
        <strong id="tmplToastTitle" class="me-auto">Notification</strong>
    </div>
    <div id="tmplToastBody" class="mt-1 small"></div>
    <button type="button" class="btn-close" onclick="$('#tmplToast').fadeOut()"></button>
</div>

<!-- Main SPA Content -->
<div class="main-content adminconsole-features adminconsole-sms-templates sms-template-spa">
    <section class="section">
        <div class="section-body">
            <div class="server-error">
                @include('../Elements/flash-message')
            </div>

            <div class="row">
                <!-- Left Sidebar Settings -->
                <div class="col-3 col-md-3 col-lg-3">
                    @include('../Elements/CRM/setting')
                </div>

                <!-- Main SPA Panel -->
                <div class="col-9 col-md-9 col-lg-9">

                    <!-- Header Card -->
                    <div class="template-header-card d-flex justify-content-between align-items-center flex-wrap gap-3">
                        <div>
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <h3 class="m-0 text-white font-weight-bold"><i class="fa-solid fa-file-lines me-2"></i>SMS Template Studio</h3>
                                <span class="badge bg-success text-white px-2 py-1 align-middle rounded-pill">
                                    <i class="fa-solid fa-bolt me-1" style="font-size: 8px;"></i>SPA Console
                                </span>
                            </div>
                            <p class="text-white-50 m-0 small">Create, edit, and test reusable SMS templates with dynamic merge variables</p>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="{{ route('adminconsole.features.sms.dashboard') }}" class="btn btn-light btn-sm font-weight-bold text-indigo shadow-sm">
                                <i class="fa-solid fa-gauge me-1"></i> SMS Dashboard
                            </a>
                            <button onclick="openBuilderTab()" class="btn btn-warning btn-sm font-weight-bold shadow-sm">
                                <i class="fa-solid fa-plus me-1"></i> New Template
                            </button>
                        </div>
                    </div>

                    <!-- Navigation Pills -->
                    <div class="template-nav-pills">
                        <button class="template-nav-link active" data-tab="library" onclick="switchTmplTab('library')">
                            <i class="fa-solid fa-layer-group"></i> Template Library
                        </button>
                        <button class="template-nav-link" data-tab="builder" onclick="switchTmplTab('builder')">
                            <i class="fa-solid fa-pen-ruler"></i> Template Builder & Editor
                        </button>
                        <button class="template-nav-link" data-tab="test" onclick="switchTmplTab('test')">
                            <i class="fa-solid fa-vial-circle-check"></i> Quick Test Dispatch
                        </button>
                        <button class="template-nav-link" data-tab="categories" onclick="switchTmplTab('categories')">
                            <i class="fa-solid fa-tags"></i> Categories & Usage
                        </button>
                    </div>

                    <!-- TAB 1: TEMPLATE LIBRARY -->
                    <div id="tmpl-tab-library" class="tmpl-tab-content">
                        <div class="card border-0 shadow-sm rounded-4">
                            <div class="card-header bg-white border-bottom-0 pt-4 px-4">
                                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                    <h5 class="m-0 font-weight-bold text-dark"><i class="fa-solid fa-folder-open text-indigo me-2"></i>Active SMS Templates</h5>

                                    <!-- Search & Category Filters -->
                                    <div class="d-flex gap-2 flex-wrap">
                                        <input type="text" class="form-control form-control-sm" id="tmplSearchInput" placeholder="Search templates..." style="width: 220px;" onkeyup="debounceSearchTemplates()">
                                        <select class="form-select form-control form-control-sm" id="tmplCategoryFilter" onchange="loadTemplatesGrid(1)" style="width: 140px;">
                                            <option value="">All Categories</option>
                                            <option value="notification">Notification</option>
                                            <option value="reminder">Reminder</option>
                                            <option value="verification">Verification</option>
                                            <option value="manual">Manual</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body px-4 pb-4">
                                <!-- Templates Cards Grid -->
                                <div class="row g-3" id="templatesCardsContainer">
                                    <div class="col-12 text-center py-5">
                                        <i class="fa-solid fa-spinner fa-spin fa-2x text-indigo mb-2"></i>
                                        <p class="text-muted m-0">Loading SMS Templates...</p>
                                    </div>
                                </div>

                                <!-- Pagination Bar -->
                                <div class="d-flex justify-content-between align-items-center mt-4" id="templatesPaginationBar"></div>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 2: TEMPLATE BUILDER & EDITOR -->
                    <div id="tmpl-tab-builder" class="tmpl-tab-content" style="display: none;">
                        <div class="row g-4">
                            <!-- Builder Form -->
                            <div class="col-lg-7">
                                <div class="card border-0 shadow-sm rounded-4 h-100">
                                    <div class="card-header bg-white border-bottom-0 pt-4 px-4 d-flex justify-content-between align-items-center">
                                        <h5 class="m-0 font-weight-bold text-dark" id="builderFormHeader">
                                            <i class="fa-solid fa-pen-to-square text-indigo me-2"></i>Create New Template
                                        </h5>
                                        <button type="button" onclick="clearBuilderForm()" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                                            Reset Form
                                        </button>
                                    </div>
                                    <div class="card-body px-4 pb-4">
                                        <form id="spaTemplateForm">
                                            @csrf
                                            <input type="hidden" id="builderTemplateId" name="template_id" value="">

                                            <div class="row g-3 mb-3">
                                                <div class="col-md-6">
                                                    <label class="form-label font-weight-bold">Template Title <span class="text-danger">*</span></label>
                                                    <input type="text" class="form-control" id="builderTitle" name="title" required placeholder="e.g. Court Hearing Reminder">
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label font-weight-bold">Category</label>
                                                    <select class="form-select form-control" id="builderCategory" name="category">
                                                        <option value="notification">Notification</option>
                                                        <option value="reminder">Reminder</option>
                                                        <option value="verification">Verification</option>
                                                        <option value="manual">Manual</option>
                                                    </select>
                                                </div>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label font-weight-bold">Description (Optional)</label>
                                                <input type="text" class="form-control" id="builderDescription" name="description" placeholder="Brief note on when this template is used">
                                            </div>

                                            <!-- Clickable Merge Tags -->
                                            <div class="mb-3">
                                                <label class="form-label font-weight-bold d-block">Click to Insert Variables</label>
                                                <div class="d-flex flex-wrap gap-1">
                                                    <span class="var-chip" onclick="insertVariableTag('{client_name}')">+ {client_name}</span>
                                                    <span class="var-chip" onclick="insertVariableTag('{appointment_date}')">+ {appointment_date}</span>
                                                    <span class="var-chip" onclick="insertVariableTag('{appointment_time}')">+ {appointment_time}</span>
                                                    <span class="var-chip" onclick="insertVariableTag('{company_name}')">+ {company_name}</span>
                                                    <span class="var-chip" onclick="insertVariableTag('{reference_no}')">+ {reference_no}</span>
                                                    <span class="var-chip" onclick="insertVariableTag('{amount}')">+ {amount}</span>
                                                </div>
                                            </div>

                                            <div class="mb-3">
                                                <div class="d-flex justify-content-between align-items-center mb-1">
                                                    <label class="form-label font-weight-bold m-0">Message Content <span class="text-danger">*</span></label>
                                                    <span class="badge bg-light text-dark border small" id="builderEncodingBadge">GSM 7-bit</span>
                                                </div>
                                                <textarea class="form-control" id="builderMessage" name="message" rows="5" required placeholder="Type template body..." oninput="updateBuilderCounters()"></textarea>

                                                <div class="d-flex justify-content-between align-items-center mt-2 small text-muted">
                                                    <div>
                                                        <span id="builderCharCount">0</span> characters
                                                    </div>
                                                    <div>
                                                        Segments: <strong id="builderSegmentCount" class="text-indigo">0</strong> SMS
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label font-weight-bold">Defined Variables (JSON or CSV)</label>
                                                <input type="text" class="form-control" id="builderVariables" name="variables" placeholder="client_name, appointment_date, company_name">
                                            </div>

                                            <div class="form-check form-switch mb-4">
                                                <input class="form-check-input" type="checkbox" id="builderIsActive" name="is_active" value="1" checked>
                                                <label class="form-check-label font-weight-bold" for="builderIsActive">Enable Template for Dispatch</label>
                                            </div>

                                            <div class="d-flex justify-content-end gap-2">
                                                <button type="submit" id="saveTemplateBtn" class="btn btn-indigo text-white font-weight-bold px-4 shadow-sm" style="background-color: #4338ca;">
                                                    <i class="fa-solid fa-floppy-disk me-1"></i> Save Template
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <!-- Mobile Screen Simulator -->
                            <div class="col-lg-5">
                                <div class="card border-0 shadow-sm rounded-4 text-center p-3 h-100 bg-white">
                                    <h6 class="font-weight-bold text-muted mb-3"><i class="fa-solid fa-mobile-screen me-2"></i>Live Device Screen Preview</h6>

                                    <div class="phone-mockup">
                                        <div class="phone-notch"></div>
                                        <div class="phone-screen">
                                            <div class="sms-bubble" id="builderPhoneBubble">
                                                Template preview will render here in real-time...
                                            </div>
                                        </div>
                                    </div>
                                    <small class="text-muted mt-3">Variables like {client_name} will be dynamically replaced when sending.</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 3: QUICK TEST DISPATCH -->
                    <div id="tmpl-tab-test" class="tmpl-tab-content" style="display: none;">
                        <div class="card border-0 shadow-sm rounded-4">
                            <div class="card-header bg-white border-bottom-0 pt-4 px-4">
                                <h5 class="m-0 font-weight-bold text-dark"><i class="fa-solid fa-vial-circle-check text-indigo me-2"></i>Test Dispatch Template</h5>
                            </div>
                            <div class="card-body px-4 pb-4">
                                <form id="testDispatchForm">
                                    @csrf
                                    <div class="row g-3 mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label font-weight-bold">Select Template to Test</label>
                                            <select class="form-select form-control" id="testTemplateSelect" onchange="loadTestTemplatePreview(this.value)">
                                                <option value="">-- Choose Template --</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label font-weight-bold">Test Phone Number</label>
                                            <input type="text" class="form-control" id="testPhone" name="phone" placeholder="+61412345678" required>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label font-weight-bold">Template Preview (with Merged Variables)</label>
                                        <textarea class="form-control font-monospace" id="testMergedPreview" name="message" rows="4" required></textarea>
                                    </div>

                                    <div class="d-flex justify-content-end">
                                        <button type="submit" id="sendTestBtn" class="btn btn-indigo text-white font-weight-bold px-4" style="background-color: #4338ca;">
                                            <i class="fa-solid fa-paper-plane me-1"></i> Send Test SMS
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 4: CATEGORIES & USAGE -->
                    <div id="tmpl-tab-categories" class="tmpl-tab-content" style="display: none;">
                        <div class="row g-4" id="categoriesStatsGrid">
                            <div class="col-12 text-center py-5">
                                <i class="fa-solid fa-spinner fa-spin fa-2x text-indigo"></i>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </section>
</div>

<!-- MODAL: Simple & Highly Interactive Quick Edit Modal -->
<div class="modal fade" id="tmplQuickEditModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 rounded-4 shadow-lg overflow-hidden">
            <!-- Clean High-Contrast Header -->
            <div class="modal-header border-0 px-4 py-3 bg-white border-bottom d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-3 p-2 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; background: #e0e7ff; color: #4338ca;">
                        <i class="fa-solid fa-pen-to-square fs-5"></i>
                    </div>
                    <div>
                        <h5 class="modal-title font-weight-bold text-dark m-0">Edit SMS Template</h5>
                        <small class="text-muted">Compose reusable message templates with instant live preview</small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" onclick="$('#tmplQuickEditModal').modal('hide')"></button>
            </div>
            
            <!-- Modal Body -->
            <div class="modal-body p-4 bg-white">
                <form id="quickEditForm">
                    @csrf
                    <input type="hidden" id="quickEditId" value="">

                    <!-- Row 1: Title & Category -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-7">
                            <label class="form-label font-weight-bold text-dark mb-1">Template Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="quickEditTitle" required placeholder="e.g. Appointment Reminder">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label font-weight-bold text-dark mb-1">Category</label>
                            <select class="form-select form-control" id="quickEditCategory">
                                <option value="reminder">Reminder</option>
                                <option value="notification">Notification</option>
                                <option value="verification">Verification</option>
                                <option value="manual">Manual</option>
                            </select>
                        </div>
                    </div>

                    <!-- Row 2: Variable Insert Buttons -->
                    <div class="mb-3">
                        <label class="form-label font-weight-bold text-dark mb-1 d-flex justify-content-between">
                            <span><i class="fa-solid fa-hand-pointer text-indigo me-1"></i> Click to insert merge variable:</span>
                            <span class="badge bg-light text-dark border font-monospace small" id="quickEncodingBadge">GSM 7-bit</span>
                        </label>

                        <div class="p-2 bg-light rounded-3 border d-flex flex-wrap gap-1 align-items-center">
                            <button type="button" class="btn btn-sm var-btn" onclick="insertQuickVariable('{first_name}')">
                                👤 {first_name}
                            </button>
                            <button type="button" class="btn btn-sm var-btn" onclick="insertQuickVariable('{appointment_date}')">
                                📅 {appointment_date}
                            </button>
                            <button type="button" class="btn btn-sm var-btn" onclick="insertQuickVariable('{appointment_time}')">
                                ⏰ {appointment_time}
                            </button>
                            <button type="button" class="btn btn-sm var-btn" onclick="insertQuickVariable('{office_phone}')">
                                📞 {office_phone}
                            </button>
                            <button type="button" class="btn btn-sm var-btn" onclick="insertQuickVariable('{location}')">
                                📍 {location}
                            </button>
                            <button type="button" class="btn btn-sm var-btn" onclick="insertQuickVariable('{company_name}')">
                                🏢 {company_name}
                            </button>
                        </div>
                    </div>

                    <!-- Row 3: Message Textarea -->
                    <div class="mb-3">
                        <label class="form-label font-weight-bold text-dark mb-1">Message Body <span class="text-danger">*</span></label>
                        <textarea class="form-control font-monospace fs-6" id="quickEditMessage" rows="4" required placeholder="Type message body..." oninput="updateQuickCounters()"></textarea>

                        <div class="d-flex justify-content-between align-items-center mt-1 small text-muted">
                            <div><span id="quickCharCount">0</span> characters</div>
                            <div>Segments: <strong id="quickSegmentCount" class="text-indigo">0</strong> SMS</div>
                        </div>
                    </div>

                    <!-- Row 4: Live Interactive Customer SMS Preview -->
                    <div class="mb-3 p-3 rounded-3" style="background: #f0f4ff; border: 1px solid #c7d2fe;">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="small font-weight-bold text-indigo"><i class="fa-solid fa-eye me-1"></i> Live Customer Output Preview:</span>
                            <span class="badge bg-indigo text-white" style="font-size: 10px; background-color: #4338ca;">Real-time Render</span>
                        </div>
                        <div class="p-2 bg-white rounded border font-monospace text-dark small" id="quickLiveSamplePreview" style="white-space: pre-wrap; min-height: 42px;">
                            Sample output will appear here as you type...
                        </div>
                    </div>

                    <!-- Footer Row: Switch & Actions -->
                    <div class="d-flex align-items-center justify-content-between pt-2 border-top">
                        <div class="form-check form-switch m-0 d-flex align-items-center gap-2">
                            <input class="form-check-input m-0" type="checkbox" id="quickEditIsActive" value="1" style="width: 2.2em; height: 1.2em; cursor: pointer;">
                            <label class="form-check-label font-weight-bold text-dark small cursor-pointer m-0" for="quickEditIsActive">Active Template</label>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-light rounded-3 px-4 font-weight-bold text-muted" onclick="$('#tmplQuickEditModal').modal('hide')">Cancel</button>
                            <button type="submit" id="saveQuickEditBtn" class="btn btn-indigo text-white rounded-3 px-4 font-weight-bold shadow-sm" style="background-color: #4338ca;">
                                <i class="fa-solid fa-floppy-disk me-1"></i> Save Changes
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
let currentTmplTab = 'library';
let templateCache = [];
let searchTimer = null;

$(document).ready(function() {
    // Check URL hash
    const hash = window.location.hash.replace('#', '');
    if (['library', 'builder', 'test', 'categories'].includes(hash)) {
        switchTmplTab(hash, false);
    } else {
        switchTmplTab('library', false);
    }

    // Live mobile preview binding
    $('#builderMessage').on('input', function() {
        const val = $(this).val();
        $('#builderPhoneBubble').text(val.trim() ? val : 'Template preview will render here in real-time...');
        updateBuilderCounters();
    });
});

/* SPA Tab Switcher */
function switchTmplTab(tabName, updateHash = true) {
    currentTmplTab = tabName;
    if (updateHash) {
        window.location.hash = tabName;
    }

    $('.template-nav-link').removeClass('active');
    $(`.template-nav-link[data-tab="${tabName}"]`).addClass('active');

    $('.tmpl-tab-content').hide();
    $(`#tmpl-tab-${tabName}`).fadeIn(200);

    if (tabName === 'library') {
        loadTemplatesGrid(1);
    } else if (tabName === 'test') {
        populateTestDropdown();
    } else if (tabName === 'categories') {
        loadCategoriesStats();
    }
}

function openBuilderTab() {
    clearBuilderForm();
    switchTmplTab('builder');
}

/* Toast Notification Popup */
function showTmplToast(title, message, type = 'success') {
    const toast = $('#tmplToast');
    const icon = $('#tmplToastIcon');
    toast.removeClass('alert-success alert-danger alert-warning alert-info');
    icon.removeClass('fa-circle-check fa-circle-xmark fa-triangle-exclamation');

    if (type === 'success') {
        toast.addClass('alert-success');
        icon.addClass('fa-circle-check');
    } else if (type === 'danger') {
        toast.addClass('alert-danger');
        icon.addClass('fa-circle-xmark');
    } else {
        toast.addClass('alert-warning');
        icon.addClass('fa-triangle-exclamation');
    }

    $('#tmplToastTitle').text(title);
    $('#tmplToastBody').text(message);
    toast.stop(true, true).fadeIn(200).delay(4000).fadeOut(300);
}

/* Character & GSM/Unicode Counter */
function updateBuilderCounters() {
    const text = $('#builderMessage').val() || '';
    const charCount = text.length;
    const gsm7BitRegex = /^[\A-Za-z0-9 \r\n@£$¥èéùìòÇ\Øø\ÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ!"#¤%&'()*+,\-./:;<=>?¡ÄÖÑÜ§àäöñüà]*$/;
    const isUnicode = !gsm7BitRegex.test(text);

    $('#builderEncodingBadge').text(isUnicode ? 'Unicode (UTF-16)' : 'GSM 7-bit');

    let segments = 0;
    if (charCount > 0) {
        if (isUnicode) {
            segments = charCount <= 70 ? 1 : Math.ceil(charCount / 67);
        } else {
            segments = charCount <= 160 ? 1 : Math.ceil(charCount / 153);
        }
    }

    $('#builderCharCount').text(charCount);
    $('#builderSegmentCount').text(segments);
}

function updateQuickCounters() {
    const text = $('#quickEditMessage').val() || '';
    const charCount = text.length;
    const gsm7BitRegex = /^[\A-Za-z0-9 \r\n@£$¥èéùìòÇ\Øø\ÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ!"#¤%&'()*+,\-./:;<=>?¡ÄÖÑÜ§àäöñüà]*$/;
    const isUnicode = !gsm7BitRegex.test(text);

    $('#quickEncodingBadge').text(isUnicode ? 'Unicode (UTF-16)' : 'GSM 7-bit');

    let segments = 0;
    if (charCount > 0) {
        if (isUnicode) {
            segments = charCount <= 70 ? 1 : Math.ceil(charCount / 67);
        } else {
            segments = charCount <= 160 ? 1 : Math.ceil(charCount / 153);
        }
    }

    $('#quickCharCount').text(charCount);
    $('#quickSegmentCount').text(segments);

    // Live Customer Output Preview inside quick edit modal
    if (charCount > 0) {
        let sample = text;
        sample = sample.replace(/\{first_name\}/g, 'John');
        sample = sample.replace(/\{client_name\}/g, 'John Doe');
        sample = sample.replace(/\{appointment_date\}/g, '29 July 2026');
        sample = sample.replace(/\{appointment_time\}/g, '10:30 AM');
        sample = sample.replace(/\{office_phone\}/g, '03 9876 5432');
        sample = sample.replace(/\{location\}/g, 'Melbourne CBD');
        sample = sample.replace(/\{company_name\}/g, 'Bansal Lawyers');
        $('#quickLiveSamplePreview').text(sample);
    } else {
        $('#quickLiveSamplePreview').text('Sample output will appear here as you type...');
    }
}

function insertQuickVariable(tag) {
    const textarea = document.getElementById('quickEditMessage');
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const text = textarea.value;

    textarea.value = text.substring(0, start) + tag + text.substring(end);
    textarea.selectionStart = textarea.selectionEnd = start + tag.length;
    textarea.focus();
    $(textarea).trigger('input');
}

/* Variable Insertion Tag Helper */
function insertVariableTag(tag) {
    const textarea = document.getElementById('builderMessage');
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const text = textarea.value;

    textarea.value = text.substring(0, start) + tag + text.substring(end);
    textarea.selectionStart = textarea.selectionEnd = start + tag.length;
    textarea.focus();
    $(textarea).trigger('input');
}

/* Clear Form */
function clearBuilderForm() {
    $('#spaTemplateForm')[0].reset();
    $('#builderTemplateId').val('');
    $('#builderFormHeader').html('<i class="fa-solid fa-pen-to-square text-indigo me-2"></i>Create New Template');
    $('#builderPhoneBubble').text('Template preview will render here in real-time...');
    updateBuilderCounters();
}

/* Load Grid Data via AJAX */
function debounceSearchTemplates() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => loadTemplatesGrid(1), 300);
}

function loadTemplatesGrid(page = 1) {
    const search = $('#tmplSearchInput').val();
    const category = $('#tmplCategoryFilter').val();

    $('#templatesCardsContainer').html(`
        <div class="col-12 text-center py-5">
            <i class="fa-solid fa-spinner fa-spin fa-2x text-indigo mb-2"></i>
            <p class="text-muted m-0">Loading SMS Templates...</p>
        </div>
    `);

    $.ajax({
        url: `/adminconsole/features/sms/templates?json=1&page=${page}&search=${encodeURIComponent(search)}&category=${category}`,
        method: 'GET',
        success: function(res) {
            if (res.success && res.data.data.length > 0) {
                templateCache = res.data.data;
                let html = '';
                res.data.data.forEach(tmpl => {
                    html += `
                    <div class="col-md-6 col-lg-4">
                        <div class="template-card">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h6 class="font-weight-bold m-0 text-dark">${tmpl.title}</h6>
                                <span class="badge bg-${tmpl.is_active ? 'success' : 'secondary'} rounded-pill">${tmpl.is_active ? 'Active' : 'Inactive'}</span>
                            </div>
                            ${tmpl.description ? `<small class="text-muted mb-2 d-block">${tmpl.description}</small>` : ''}
                            <p class="small text-muted font-monospace bg-light p-2 rounded-3 mb-3 flex-grow-1" style="white-space: pre-wrap; max-height: 100px; overflow-y: auto;">${tmpl.message}</p>

                            <div class="d-flex justify-content-between align-items-center mt-auto pt-2 border-top">
                                <span class="badge bg-indigo text-white text-uppercase" style="background-color: #4338ca;">${tmpl.category || 'general'}</span>
                                <div class="btn-group">
                                    <button onclick="openQuickEditModal(${tmpl.id})" class="btn btn-outline-primary btn-sm py-1 px-2" title="Edit Template">
                                        <i class="fa-solid fa-pen"></i> Edit
                                    </button>
                                    <button onclick="quickTestTemplate(${tmpl.id})" class="btn btn-outline-success btn-sm py-1 px-2" title="Quick Test">
                                        <i class="fa-solid fa-paper-plane"></i>
                                    </button>
                                    <button onclick="deleteTemplateAjax(${tmpl.id})" class="btn btn-outline-danger btn-sm py-1 px-2" title="Delete">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>`;
                });
                $('#templatesCardsContainer').html(html);

                // Pagination
                let pagHtml = `<span class="small text-muted">Showing page ${res.data.current_page} of ${res.data.last_page} (${res.data.total} templates)</span><div class="btn-group">`;
                if (res.data.prev_page_url) {
                    pagHtml += `<button onclick="loadTemplatesGrid(${res.data.current_page - 1})" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-chevron-left"></i> Prev</button>`;
                }
                if (res.data.next_page_url) {
                    pagHtml += `<button onclick="loadTemplatesGrid(${res.data.current_page + 1})" class="btn btn-outline-secondary btn-sm">Next <i class="fa-solid fa-chevron-right"></i></button>`;
                }
                pagHtml += `</div>`;
                $('#templatesPaginationBar').html(pagHtml);
            } else {
                $('#templatesCardsContainer').html(`
                    <div class="col-12 text-center py-5 text-muted">
                        <i class="fa-solid fa-folder-open fa-3x mb-3 text-muted"></i>
                        <h5>No SMS Templates Found</h5>
                        <p class="text-muted">Create a new template to start sending automated SMS messages.</p>
                        <button onclick="openBuilderTab()" class="btn btn-indigo text-white font-weight-bold" style="background-color: #4338ca;">
                            <i class="fa-solid fa-plus me-1"></i> Create First Template
                        </button>
                    </div>
                `);
                $('#templatesPaginationBar').html('');
            }
        }
    });
}

function openQuickEditModal(id) {
    $.ajax({
        url: `/adminconsole/features/sms/templates/${id}`,
        method: 'GET',
        success: function(res) {
            if (res.success) {
                const t = res.data;
                $('#quickEditId').val(t.id);
                $('#quickEditTitle').val(t.title);
                $('#quickEditCategory').val(t.category || 'reminder');
                $('#quickEditMessage').val(t.message);
                $('#quickEditIsActive').prop('checked', t.is_active);
                updateQuickCounters();
                $('#tmplQuickEditModal').modal('show');
            }
        }
    });
}

$('#quickEditForm').on('submit', function(e) {
    e.preventDefault();
    const id = $('#quickEditId').val();
    const btn = $('#saveQuickEditBtn');
    btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin me-1"></i> Saving...');

    $.ajax({
        url: `/adminconsole/features/sms/templates/${id}`,
        method: 'PUT',
        data: {
            title: $('#quickEditTitle').val(),
            category: $('#quickEditCategory').val(),
            message: $('#quickEditMessage').val(),
            is_active: $('#quickEditIsActive').is(':checked') ? 1 : 0,
            _token: '{{ csrf_token() }}'
        },
        success: function(res) {
            if (res.success) {
                showTmplToast('Updated', 'Template updated successfully', 'success');
                $('#tmplQuickEditModal').modal('hide');
                loadTemplatesGrid(1);
            } else {
                showTmplToast('Error', res.message, 'danger');
            }
        },
        error: function(xhr) {
            const res = xhr.responseJSON;
            showTmplToast('Error', (res && res.message) ? res.message : 'Error updating template', 'danger');
        },
        complete: function() {
            btn.prop('disabled', false).html('<i class="fa-solid fa-floppy-disk me-1"></i> Save Changes');
        }
    });
});

/* Load Template into Builder Tab for Editing */
function loadTemplateToBuilder(id) {
    $.ajax({
        url: `/adminconsole/features/sms/templates/${id}`,
        method: 'GET',
        success: function(res) {
            if (res.success) {
                const t = res.data;
                switchTmplTab('builder');
                $('#builderTemplateId').val(t.id);
                $('#builderTitle').val(t.title);
                $('#builderCategory').val(t.category || 'notification');
                $('#builderDescription').val(t.description || '');
                $('#builderMessage').val(t.message).trigger('input');
                $('#builderVariables').val(t.variables || '');
                $('#builderIsActive').prop('checked', t.is_active);
                $('#builderFormHeader').html('<i class="fa-solid fa-pen-to-square text-indigo me-2"></i>Edit Template: ' + t.title);
                showTmplToast('Editing Template', `Loaded ${t.title} in Builder workspace`, 'info');
            }
        }
    });
}

/* AJAX Template Store/Update */
$('#spaTemplateForm').on('submit', function(e) {
    e.preventDefault();
    const id = $('#builderTemplateId').val();
    const url = id ? `/adminconsole/features/sms/templates/${id}` : '/adminconsole/features/sms/templates';
    const method = id ? 'PUT' : 'POST';

    const btn = $('#saveTemplateBtn');
    btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin me-1"></i> Saving...');

    $.ajax({
        url: url,
        method: method,
        data: $(this).serialize(),
        success: function(res) {
            if (res.success) {
                showTmplToast('Success', res.message || 'Template saved successfully', 'success');
                switchTmplTab('library');
            } else {
                showTmplToast('Error', res.message || 'Failed to save template', 'danger');
            }
        },
        error: function(xhr) {
            const res = xhr.responseJSON;
            showTmplToast('Validation Error', (res && res.message) ? res.message : 'Check form fields', 'danger');
        },
        complete: function() {
            btn.prop('disabled', false).html('<i class="fa-solid fa-floppy-disk me-1"></i> Save Template');
        }
    });
});

/* Delete Template via AJAX */
function deleteTemplateAjax(id) {
    if (!confirm('Are you sure you want to delete this SMS template?')) return;

    $.ajax({
        url: `/adminconsole/features/sms/templates/${id}`,
        method: 'DELETE',
        data: { _token: '{{ csrf_token() }}' },
        success: function(res) {
            if (res.success) {
                showTmplToast('Deleted', 'Template removed', 'success');
                loadTemplatesGrid(1);
            } else {
                showTmplToast('Cannot Delete', res.message, 'danger');
            }
        }
    });
}

/* Quick Test Dispatch */
function quickTestTemplate(id) {
    switchTmplTab('test');
    populateTestDropdown(id);
}

function populateTestDropdown(selectedId = null) {
    $.ajax({
        url: '{{ route("adminconsole.features.sms.templates.active") }}',
        method: 'GET',
        success: function(res) {
            if (res.success) {
                let options = '<option value="">-- Choose Template --</option>';
                res.data.forEach(t => {
                    options += `<option value="${t.id}" ${t.id == selectedId ? 'selected' : ''}>${t.title}</option>`;
                });
                $('#testTemplateSelect').html(options);
                if (selectedId) {
                    loadTestTemplatePreview(selectedId);
                }
            }
        }
    });
}

function loadTestTemplatePreview(id) {
    if (!id) {
        $('#testMergedPreview').val('');
        return;
    }
    $.ajax({
        url: `/adminconsole/features/sms/templates/${id}`,
        method: 'GET',
        success: function(res) {
            if (res.success) {
                // Pre-fill sample values for variables
                let msg = res.data.message;
                msg = msg.replace(/\{first_name\}/g, 'John');
                msg = msg.replace(/\{appointment_date\}/g, 'Tomorrow at 10:00 AM');
                msg = msg.replace(/\{company_name\}/g, 'Bansal Lawyers');
                msg = msg.replace(/\{reference_no\}/g, 'REF-9921');
                msg = msg.replace(/\{amount\}/g, '$150.00');
                $('#testMergedPreview').val(msg);
            }
        }
    });
}

$('#testDispatchForm').on('submit', function(e) {
    e.preventDefault();
    const btn = $('#sendTestBtn');
    btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin me-1"></i> Dispatching...');

    $.ajax({
        url: '{{ route("adminconsole.features.sms.send") }}',
        method: 'POST',
        data: $(this).serialize(),
        success: function(res) {
            if (res.success) {
                showTmplToast('Test Dispatched', 'Test SMS successfully sent!', 'success');
            } else {
                showTmplToast('Dispatch Failed', res.message, 'danger');
            }
        },
        error: function(xhr) {
            const res = xhr.responseJSON;
            showTmplToast('Error', (res && res.message) ? res.message : 'Error sending test SMS', 'danger');
        },
        complete: function() {
            btn.prop('disabled', false).html('<i class="fa-solid fa-paper-plane me-1"></i> Send Test SMS');
        }
    });
});

/* Categories Stats Loader */
function loadCategoriesStats() {
    $.ajax({
        url: '/adminconsole/features/sms/templates?json=1',
        method: 'GET',
        success: function(res) {
            if (res.success) {
                const total = res.data.total || 0;
                let cats = { notification: 0, reminder: 0, verification: 0, manual: 0 };
                res.data.data.forEach(t => {
                    const c = t.category || 'notification';
                    if (cats[c] !== undefined) cats[c]++;
                    else cats[c] = 1;
                });

                let html = '';
                const colors = { notification: 'indigo', reminder: 'warning', verification: 'success', manual: 'info' };
                Object.keys(cats).forEach(cat => {
                    const count = cats[cat];
                    const pct = total > 0 ? Math.round((count / total) * 100) : 0;
                    html += `
                    <div class="col-md-6 col-lg-3">
                        <div class="card border-0 shadow-sm rounded-4 p-4 text-center">
                            <h6 class="text-uppercase font-weight-bold text-muted small">${cat}</h6>
                            <h2 class="display-6 font-weight-bold text-dark my-2">${count}</h2>
                            <div class="progress my-2" style="height: 8px;">
                                <div class="progress-bar bg-${colors[cat] || 'indigo'}" style="width: ${pct}%;"></div>
                            </div>
                            <small class="text-muted">${pct}% of total library</small>
                        </div>
                    </div>`;
                });
                $('#categoriesStatsGrid').html(html);
            }
        }
    });
}
</script>
@endsection
