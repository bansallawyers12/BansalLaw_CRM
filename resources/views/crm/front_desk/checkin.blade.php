@extends('layouts.crm_client_detail')
@section('title', 'Front-Desk Check-In')

@section('content')
<style>
/* Front-desk check-in — Powder Blue & Soft Gold (docs/theme.md); vars from public/css/crm-theme.css */
.front-desk-checkin-page[x-cloak],
.front-desk-checkin-page [x-cloak] { display: none !important; }
.front-desk-checkin-page .fd-wizard-wrapper {
    max-width: 700px;
    margin: 0 auto;
    padding: 90px 15px 40px;
}
.front-desk-checkin-page .fd-card {
    background: var(--card-bg);
    border-radius: 10px;
    box-shadow: 0 1px 4px rgba(30, 61, 96, 0.06);
    border: 1px solid var(--border);
    overflow: hidden;
}
.front-desk-checkin-page .fd-card-header {
    background: linear-gradient(135deg, var(--navy) 0%, var(--sidebar-active) 100%);
    color: #fff;
    padding: 22px 28px;
    border-bottom: 1px solid var(--border);
}
.front-desk-checkin-page .fd-card-header h4 {
    margin: 0;
    font-size: 1.2rem;
    font-weight: 600;
    color: #fff !important; /* beats body.sidebar-mini h4 { color: var(--navy) !important } in crm-theme.css */
}
.front-desk-checkin-page .fd-card-header h4 i {
    color: inherit !important;
}
.front-desk-checkin-page .fd-card-header p {
    margin: 4px 0 0;
    font-size: 0.85rem;
    opacity: 0.9;
    color: rgba(255, 255, 255, 0.92) !important;
}
.front-desk-checkin-page .fd-card-body {
    padding: 28px;
    background: var(--card-bg);
}

.front-desk-checkin-page .fd-stepper {
    display: flex;
    align-items: center;
    margin-bottom: 28px;
    gap: 0;
}
.front-desk-checkin-page .fd-step {
    display: flex;
    flex-direction: column;
    align-items: center;
    flex: 1;
    position: relative;
}
.front-desk-checkin-page .fd-step:not(:last-child)::after {
    content: '';
    position: absolute;
    top: 14px;
    left: 55%;
    right: -45%;
    height: 2px;
    background: var(--border);
    z-index: 0;
}
.front-desk-checkin-page .fd-step.done:not(:last-child)::after {
    background: var(--navy);
}
.front-desk-checkin-page .fd-step-circle {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background: var(--sidebar-bg);
    color: var(--text-muted);
    border: 1px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.8rem;
    position: relative;
    z-index: 1;
    transition: background 0.2s, color 0.2s, border-color 0.2s;
}
.front-desk-checkin-page .fd-step.done .fd-step-circle {
    background: var(--success);
    border-color: var(--success);
    color: #fff;
}
.front-desk-checkin-page .fd-step.active .fd-step-circle {
    background: var(--navy);
    border-color: var(--navy);
    color: #fff;
}
.front-desk-checkin-page .fd-step-label {
    margin-top: 6px;
    font-size: 0.72rem;
    color: var(--text-muted);
    text-align: center;
    white-space: nowrap;
}
.front-desk-checkin-page .fd-step.active .fd-step-label {
    color: var(--navy);
    font-weight: 600;
}
.front-desk-checkin-page .fd-step.done .fd-step-label {
    color: var(--success);
}

.front-desk-checkin-page .fd-match-card {
    border: 2px solid var(--border);
    border-radius: 8px;
    padding: 14px 16px;
    cursor: pointer;
    transition: border-color 0.15s, background 0.15s;
    margin-bottom: 10px;
    background: var(--card-bg);
}
.front-desk-checkin-page .fd-match-card:hover {
    border-color: var(--sidebar-active);
    background: var(--page-bg);
}
.front-desk-checkin-page .fd-match-card.selected {
    border-color: var(--navy);
    background: rgba(221, 234, 248, 0.55);
    box-shadow: 0 0 0 1px var(--accent-gold);
}
.front-desk-checkin-page .fd-match-card .badge { font-size: 0.72rem; }

.front-desk-checkin-page .fd-summary-row {
    display: flex;
    gap: 8px;
    padding: 8px 0;
    border-bottom: 1px solid var(--border);
    font-size: 0.9rem;
}
.front-desk-checkin-page .fd-summary-row:last-child { border-bottom: none; }
.front-desk-checkin-page .fd-summary-label {
    color: var(--text-muted);
    min-width: 130px;
    font-weight: 500;
}
.front-desk-checkin-page .fd-summary-value {
    color: var(--text-dark);
    font-weight: 600;
}

.front-desk-checkin-page .fd-wizard-wrapper .fd-step-title {
    color: var(--navy);
    font-weight: 700;
    letter-spacing: 0.03em;
}
.front-desk-checkin-page .fd-wizard-wrapper .fd-lead-question {
    color: var(--text-dark);
    font-weight: 600;
    font-size: 1.05rem;
    line-height: 1.45;
}

.front-desk-checkin-page .fd-appt-choices {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
}
.front-desk-checkin-page .fd-choice-btn {
    flex: 1 1 220px;
    min-width: min(100%, 200px);
    padding: 14px 18px;
    font-weight: 600;
    font-size: 1rem;
    line-height: 1.35;
    border-radius: 10px;
    border: 2px solid;
    cursor: pointer;
    transition: background 0.15s ease, color 0.15s ease, border-color 0.15s ease, box-shadow 0.15s ease;
    text-align: center;
    background-image: none !important;
    font-family: inherit;
}
.front-desk-checkin-page .fd-choice-btn i { color: inherit !important; }
.front-desk-checkin-page .fd-choice-btn:focus-visible {
    outline: 3px solid rgba(58, 111, 168, 0.4);
    outline-offset: 2px;
}
.front-desk-checkin-page .fd-choice-yes {
    background: var(--page-bg);
    border-color: var(--sidebar-active);
    color: var(--navy);
}
.front-desk-checkin-page .fd-choice-yes:hover {
    background: var(--sidebar-bg);
    border-color: var(--navy);
    color: var(--navy);
}
.front-desk-checkin-page .fd-choice-yes.fd-choice--selected {
    background: var(--navy);
    border-color: var(--navy);
    color: #fff;
    box-shadow: 0 3px 10px rgba(30, 61, 96, 0.2);
}
.front-desk-checkin-page .fd-choice-yes.fd-choice--selected:hover {
    background: var(--sidebar-active);
    border-color: var(--sidebar-active);
    color: #fff;
}
.front-desk-checkin-page .fd-choice-no {
    background: var(--card-bg);
    border-color: var(--border);
    color: var(--text-dark);
}
.front-desk-checkin-page .fd-choice-no:hover {
    background: var(--sidebar-bg);
    border-color: var(--text-muted);
    color: var(--navy);
}
.front-desk-checkin-page .fd-choice-no.fd-choice--selected {
    background: var(--text-muted);
    border-color: var(--text-muted);
    color: #fff;
    box-shadow: 0 3px 10px rgba(94, 122, 144, 0.25);
}
.front-desk-checkin-page .fd-choice-no.fd-choice--selected:hover {
    background: var(--navy);
    border-color: var(--navy);
    color: #fff;
}

/*
 * Primary CTAs — theme.md "Buttons": primary = --navy, text #fff; hover uses --sidebar-active.
 * Applies to Look Up / Continue / Submit Check-In / Details Correct / Start Over (not status green).
 */
body.sidebar-mini .front-desk-checkin-page .btn.fd-btn-action,
body.sidebar-mini .front-desk-checkin-page .btn.fd-btn-confirm,
.front-desk-checkin-page .btn.fd-btn-action,
.front-desk-checkin-page .btn.fd-btn-confirm {
    background-color: var(--navy) !important;
    background-image: none !important;
    border: 2px solid var(--navy) !important;
    color: #fff !important;
    font-weight: 600;
    border-radius: 8px;
    box-shadow: none !important;
}
body.sidebar-mini .front-desk-checkin-page .btn.fd-btn-action:hover:not(:disabled),
body.sidebar-mini .front-desk-checkin-page .btn.fd-btn-action:focus:not(:disabled),
body.sidebar-mini .front-desk-checkin-page .btn.fd-btn-confirm:hover:not(:disabled),
body.sidebar-mini .front-desk-checkin-page .btn.fd-btn-confirm:focus:not(:disabled),
.front-desk-checkin-page .btn.fd-btn-action:hover:not(:disabled),
.front-desk-checkin-page .btn.fd-btn-action:focus:not(:disabled),
.front-desk-checkin-page .btn.fd-btn-confirm:hover:not(:disabled),
.front-desk-checkin-page .btn.fd-btn-confirm:focus:not(:disabled) {
    background-color: var(--sidebar-active) !important;
    border-color: var(--sidebar-active) !important;
    color: #fff !important;
    filter: none !important;
}
.front-desk-checkin-page .btn.fd-btn-action i,
.front-desk-checkin-page .btn.fd-btn-confirm i { color: #fff !important; }
.front-desk-checkin-page .btn.fd-btn-action:disabled,
.front-desk-checkin-page .btn.fd-btn-confirm:disabled {
    opacity: 0.55;
    cursor: not-allowed;
}

/* Walk-in — theme.md: outline + gold accent when active */
body.sidebar-mini .front-desk-checkin-page .btn.fd-btn-walkin,
.front-desk-checkin-page .btn.fd-btn-walkin {
    background-color: var(--card-bg) !important;
    background-image: none !important;
    border: 2px solid var(--border) !important;
    color: var(--navy) !important;
    font-weight: 600;
    border-radius: 8px;
}
body.sidebar-mini .front-desk-checkin-page .btn.fd-btn-walkin:hover,
.front-desk-checkin-page .btn.fd-btn-walkin:hover {
    background-color: var(--sidebar-bg) !important;
    border-color: var(--sidebar-active) !important;
    color: var(--navy) !important;
}
.front-desk-checkin-page .btn.fd-btn-walkin.active {
    background-color: var(--accent-light) !important;
    border-color: var(--accent-gold) !important;
    color: #7a5800 !important;
    box-shadow: 0 0 0 2px rgba(200, 153, 42, 0.35) !important;
}
.front-desk-checkin-page .btn.fd-btn-walkin i { color: inherit !important; }

.front-desk-checkin-page .fd-appt-card {
    border: 2px solid var(--border);
    border-radius: 8px;
    padding: 12px 16px;
    cursor: pointer;
    transition: border-color 0.15s, background 0.15s;
    margin-bottom: 8px;
    background: var(--card-bg);
}
.front-desk-checkin-page .fd-appt-card:hover {
    border-color: var(--sidebar-active);
    background: var(--page-bg);
}
.front-desk-checkin-page .fd-appt-card.selected {
    border-color: var(--navy);
    background: rgba(221, 234, 248, 0.55);
    box-shadow: 0 0 0 1px var(--accent-gold);
}

.front-desk-checkin-page .fd-success { text-align: center; padding: 40px 20px; }
.front-desk-checkin-page .fd-success i { font-size: 3.5rem; color: var(--success); margin-bottom: 16px; }
.front-desk-checkin-page .fd-success h5 {
    font-size: 1.4rem;
    font-weight: 700;
    color: var(--text-dark);
    margin-bottom: 8px;
}
.front-desk-checkin-page .fd-success p { color: var(--text-muted); }
.front-desk-checkin-page .fd-step-not-client .fd-success { padding: 30px 20px; }
.front-desk-checkin-page .fd-step-not-client .fd-success > i.fa-hand-paper {
    font-size: 3rem;
    color: var(--text-muted);
    margin-bottom: 16px;
}
.front-desk-checkin-page .fd-step-not-client .fd-success h5 { color: var(--text-dark); }

.front-desk-checkin-page .fd-spinner { text-align: center; padding: 20px; }

.front-desk-checkin-page .form-control {
    border-color: var(--border);
}
.front-desk-checkin-page .form-control:focus {
    border-color: var(--sidebar-active);
    box-shadow: 0 0 0 0.2rem rgba(58, 111, 168, 0.2);
}
/* Back — theme.md: outline (neutral) */
body.sidebar-mini .front-desk-checkin-page .btn.btn-light,
.front-desk-checkin-page .btn.btn-light {
    background-color: var(--card-bg) !important;
    background-image: none !important;
    border: 1px solid var(--border) !important;
    color: var(--navy) !important;
}
body.sidebar-mini .front-desk-checkin-page .btn.btn-light:hover,
body.sidebar-mini .front-desk-checkin-page .btn.btn-light:focus,
.front-desk-checkin-page .btn.btn-light:hover,
.front-desk-checkin-page .btn.btn-light:focus {
    background-color: var(--sidebar-bg) !important;
    border-color: var(--border) !important;
    color: var(--navy) !important;
}
.front-desk-checkin-page .btn.btn-light i { color: var(--navy) !important; }

/* Secondary outline — theme.md: border --border, text --navy, hover --sidebar-bg */
body.sidebar-mini .front-desk-checkin-page .btn.btn-outline-secondary,
.front-desk-checkin-page .btn.btn-outline-secondary {
    --bs-btn-color: var(--navy);
    --bs-btn-border-color: var(--border);
    --bs-btn-hover-color: var(--navy);
    --bs-btn-hover-bg: var(--sidebar-bg);
    --bs-btn-hover-border-color: var(--navy);
    --bs-btn-active-color: var(--navy);
    --bs-btn-active-bg: var(--sidebar-bg);
    --bs-btn-active-border-color: var(--navy);
    color: var(--navy) !important;
    border-color: var(--border) !important;
    background-color: var(--card-bg) !important;
    background-image: none !important;
    font-weight: 600;
}
body.sidebar-mini .front-desk-checkin-page .btn.btn-outline-secondary:hover,
body.sidebar-mini .front-desk-checkin-page .btn.btn-outline-secondary:focus,
.front-desk-checkin-page .btn.btn-outline-secondary:hover,
.front-desk-checkin-page .btn.btn-outline-secondary:focus {
    color: var(--navy) !important;
    background-color: var(--sidebar-bg) !important;
    border-color: var(--navy) !important;
}
.front-desk-checkin-page .btn.btn-outline-secondary i { color: var(--navy) !important; }
.front-desk-checkin-page .text-primary { color: var(--sidebar-active) !important; }
.front-desk-checkin-page .text-info { color: var(--sidebar-active) !important; }
.front-desk-checkin-page .text-success { color: var(--success) !important; }
.front-desk-checkin-page .border-top { border-color: var(--border) !important; }

.front-desk-checkin-page .badge.badge-success {
    background-color: rgba(30, 122, 82, 0.15) !important;
    color: var(--success) !important;
    border: 1px solid rgba(30, 122, 82, 0.35);
}
.front-desk-checkin-page .badge.badge-warning {
    background-color: rgba(200, 153, 42, 0.15) !important;
    color: #7a5800 !important;
    border: 1px solid rgba(200, 153, 42, 0.4);
}
.front-desk-checkin-page .badge.badge-info {
    background-color: rgba(58, 111, 168, 0.18) !important;
    color: var(--sidebar-active) !important;
    border: 1px solid rgba(58, 111, 168, 0.35);
}
.front-desk-checkin-page .badge.badge-secondary {
    background-color: rgba(94, 122, 144, 0.12) !important;
    color: var(--text-muted) !important;
    border: 1px solid var(--border);
}

.front-desk-checkin-page .alert-danger {
    background: rgba(168, 48, 32, 0.08);
    border-color: var(--danger);
    color: var(--danger);
}
</style>

<div
    class="front-desk-checkin-page"
    x-data="frontDeskCheckIn({
        baseUrl: @js(url('/front-desk/checkin')),
        csrf: @js(csrf_token()),
        waitingUrl: @js(route('officevisits.waiting')),
    })"
    x-cloak
>
<div class="fd-wizard-wrapper">
    <div class="fd-card">
        <div class="fd-card-header">
            <h4><i class="fa-solid fa-clipboard-check me-2"></i>Front-Desk Check-In</h4>
            <p>Record a client or walk-in arrival at the front desk</p>
        </div>
        <div class="fd-card-body">

            {{-- Stepper --}}
            <div class="fd-stepper" x-show="!isOffPath" x-cloak>
                <template x-for="n in 5" :key="n">
                    <div
                        class="fd-step"
                        :class="{
                            active: typeof step === 'number' && step === n,
                            done: typeof step === 'number' && step > n
                        }"
                    >
                        <div class="fd-step-circle" x-text="n"></div>
                        <div class="fd-step-label" x-text="stepLabels[n - 1]"></div>
                    </div>
                </template>
            </div>

            <div
                class="alert alert-danger"
                role="alert"
                x-show="alert"
                x-text="alert"
                x-cloak
                x-ref="alertBox"
            ></div>

            {{-- STEP 1: Phone + Email --}}
            <div class="fd-wizard-step" x-show="step === 1" x-cloak>
                <h6 class="fd-step-title mb-3 text-uppercase small">Step 1 — Contact Details</h6>
                <div class="form-group">
                    <label for="fdPhone" class="font-weight-600">Phone <span class="text-danger">*</span></label>
                    <input
                        type="tel"
                        class="form-control form-control-lg"
                        id="fdPhone"
                        placeholder="e.g. 0412 345 678"
                        maxlength="20"
                        autocomplete="off"
                        x-model.trim="phone"
                        :class="{ 'is-invalid': errors.phone }"
                        @keydown.enter.prevent="lookup()"
                    >
                    <div class="invalid-feedback" x-text="errors.phone"></div>
                </div>
                <div class="form-group">
                    <label for="fdEmail" class="font-weight-600">Email <span class="text-muted">(optional — narrows results)</span></label>
                    <input
                        type="email"
                        class="form-control"
                        id="fdEmail"
                        placeholder="e.g. john@example.com"
                        autocomplete="off"
                        x-model.trim="email"
                    >
                </div>
                <div class="text-right">
                    <button type="button" class="btn btn-lg px-5 fd-btn-action" @click="lookup()" :disabled="lookingUp">
                        <i class="fa-solid fa-search me-2"></i>Look Up
                    </button>
                </div>
                <div class="fd-spinner mt-3" x-show="lookingUp" x-cloak>
                    <div class="spinner-border text-primary" role="status"><span class="sr-only">Searching…</span></div>
                    <p class="mt-2 text-muted">Searching CRM…</p>
                </div>
            </div>

            {{-- STEP 2: Match selection --}}
            <div class="fd-wizard-step" x-show="step === 2" x-cloak>
                <h6 class="fd-step-title mb-1 text-uppercase small">Step 2 — Select Match</h6>
                <p class="text-muted small mb-3" x-text="matchSubtitle"></p>

                <template x-for="m in matches" :key="m.type + '-' + m.id">
                    <div
                        class="fd-match-card"
                        :class="{ selected: selectedMatchKey === matchKey(m) }"
                        @click="selectMatch(m)"
                    >
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <strong x-text="m.name || 'Unknown'"></strong>
                                <span class="badge bg-success" x-show="m.type === 'client'">Client</span>
                                <span class="badge bg-warning text-dark" x-show="m.type !== 'client'">Lead</span>
                                <span class="text-muted small ms-1" x-show="m.is_company && m.company_name" x-text="'(' + m.company_name + ')'"></span>
                                <br>
                                <small class="text-muted">
                                    <span x-text="m.email || '—'"></span> &bull; <span x-text="m.phone || '—'"></span>
                                </small>
                            </div>
                            <i class="fa-solid fa-circle-check text-primary mt-1" x-show="selectedMatchKey === matchKey(m)" x-cloak></i>
                        </div>
                    </div>
                </template>

                <div class="border-top pt-3 mt-2">
                    <p class="text-muted small mb-2">Not in the list?</p>
                    <button
                        type="button"
                        class="btn btn-sm fd-btn-walkin"
                        :class="{ active: walkInSelected }"
                        @click="selectWalkIn()"
                    >
                        <i class="fa-solid fa-user-slash me-1"></i>Continue as Walk-In (no CRM record)
                    </button>
                </div>

                <div class="text-right mt-4">
                    <button type="button" class="btn btn-light me-2" @click="setStep(1)"><i class="fa-solid fa-arrow-left me-1"></i>Back</button>
                    <button type="button" class="btn fd-btn-action" @click="goConfirm()" :disabled="!canConfirmMatch">
                        Confirm Selection <i class="fa-solid fa-arrow-right ms-1"></i>
                    </button>
                </div>
            </div>

            {{-- STEP 2b: New client? --}}
            <div class="fd-wizard-step" x-show="step === 'new-client'" x-cloak>
                <h6 class="fd-step-title mb-2 text-uppercase small">Not Found in Our System</h6>
                <p class="fd-lead-question mb-4">Are you visiting as a new client today?</p>

                <div class="fd-appt-choices mb-4">
                    <button type="button" class="fd-choice-btn fd-choice-yes" @click="startLeadForm()">
                        <i class="fa-solid fa-user-plus me-2" aria-hidden="true"></i>Yes — I'm a new client
                    </button>
                    <button type="button" class="fd-choice-btn fd-choice-no" @click="path = 'not_client'; setStep('not-client')">
                        <i class="fa-solid fa-user-check me-2" aria-hidden="true"></i>No — I already have a file
                    </button>
                </div>

                <div class="text-right mt-2">
                    <button type="button" class="btn btn-light" @click="path = null; setStep(1)"><i class="fa-solid fa-arrow-left me-1"></i>Back</button>
                </div>
            </div>

            {{-- STEP 2c: Lead form --}}
            <div class="fd-wizard-step" x-show="step === 'lead-form'" x-cloak>
                <h6 class="fd-step-title mb-3 text-uppercase small">Your Details</h6>

                <div class="row">
                    <div class="col-sm-6">
                        <div class="form-group">
                            <label class="font-weight-600">First Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" maxlength="100" autocomplete="off" x-model.trim="lead.firstName" :class="{ 'is-invalid': errors.leadFirstName }">
                            <div class="invalid-feedback" x-text="errors.leadFirstName"></div>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="form-group">
                            <label class="font-weight-600">Last Name</label>
                            <input type="text" class="form-control" maxlength="100" autocomplete="off" x-model.trim="lead.lastName">
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-sm-6">
                        <div class="form-group">
                            <label class="font-weight-600">Phone</label>
                            <input type="text" class="form-control" :value="phone" readonly>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="form-group">
                            <label class="font-weight-600">Email</label>
                            <input type="text" class="form-control" :value="email || '—'" readonly>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="font-weight-600">Reason for Visit <span class="text-danger">*</span></label>
                    <select class="form-control" x-model="lead.visitReason" :class="{ 'is-invalid': errors.leadVisitReason }">
                        <option value="">— Select reason —</option>
                        @foreach($visitReasons as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <div class="invalid-feedback" x-text="errors.leadVisitReason"></div>
                </div>

                <div class="form-group">
                    <label class="font-weight-600">
                        Notes
                        <span class="text-danger" x-show="lead.visitReason === 'other'" x-cloak>*</span>
                    </label>
                    <textarea class="form-control" rows="3" placeholder="Additional notes…" maxlength="2000" x-model.trim="lead.visitNotes" :class="{ 'is-invalid': errors.leadVisitNotes }"></textarea>
                    <div class="invalid-feedback" x-text="errors.leadVisitNotes"></div>
                </div>

                <div class="text-right mt-4">
                    <button type="button" class="btn btn-light me-2" @click="setStep('new-client')"><i class="fa-solid fa-arrow-left me-1"></i>Back</button>
                    <button type="button" class="btn btn-lg px-5 fd-btn-confirm" @click="submitLead()" :disabled="submittingLead">
                        <i class="fa-solid fa-paper-plane me-2"></i>Submit Check-In
                    </button>
                </div>
                <div class="fd-spinner mt-3" x-show="submittingLead" x-cloak>
                    <div class="spinner-border text-success" role="status"><span class="sr-only">Saving…</span></div>
                    <p class="mt-2 text-muted">Creating record and saving check-in…</p>
                </div>
            </div>

            {{-- STEP 2d: Not a client --}}
            <div class="fd-wizard-step fd-step-not-client" x-show="step === 'not-client'" x-cloak>
                <div class="fd-success">
                    <i class="fa-solid fa-hand-paper" aria-hidden="true"></i>
                    <h5>Please speak with our receptionist</h5>
                    <p class="text-muted mb-4">Our front-desk team will be happy to help you locate your file.</p>
                    <button type="button" class="btn fd-btn-action" @click="resetWizard()">
                        <i class="fa-solid fa-arrow-rotate-right me-2"></i>Start Over
                    </button>
                </div>
            </div>

            {{-- STEP 3: Confirm --}}
            <div class="fd-wizard-step" x-show="step === 3" x-cloak>
                <h6 class="fd-step-title mb-3 text-uppercase small">Step 3 — Confirm Details</h6>
                <template x-for="row in confirmRows" :key="row[0]">
                    <div class="fd-summary-row">
                        <span class="fd-summary-label" x-text="row[0]"></span>
                        <span class="fd-summary-value" x-text="row[1]"></span>
                    </div>
                </template>
                <div class="text-right mt-4">
                    <button type="button" class="btn btn-light me-2" @click="setStep(2)"><i class="fa-solid fa-arrow-left me-1"></i>Back</button>
                    <button type="button" class="btn fd-btn-confirm" @click="goAppointments()">
                        Details Correct <i class="fa-solid fa-check ms-1"></i>
                    </button>
                </div>
            </div>

            {{-- STEP 4: Appointment --}}
            <div class="fd-wizard-step" x-show="step === 4" x-cloak>
                <h6 class="fd-step-title mb-3 text-uppercase small">Step 4 — Appointment</h6>
                <p class="fd-lead-question mb-4">Does the visitor have a scheduled appointment today?</p>

                <div class="fd-appt-choices mb-4">
                    <button
                        type="button"
                        class="fd-choice-btn fd-choice-yes"
                        :class="{ 'fd-choice--selected': claimedAppointment === true }"
                        @click="chooseHasAppointment()"
                    >
                        <i class="fa-solid fa-calendar-check me-2" aria-hidden="true"></i>Yes, has appointment
                    </button>
                    <button
                        type="button"
                        class="fd-choice-btn fd-choice-no"
                        :class="{ 'fd-choice--selected': claimedAppointment === false }"
                        @click="chooseNoAppointment()"
                    >
                        <i class="fa-solid fa-calendar-xmark me-2" aria-hidden="true"></i>No appointment
                    </button>
                </div>

                <div x-show="showApptSection" x-cloak>
                    <div class="fd-spinner" x-show="loadingAppointments" x-cloak>
                        <div class="spinner-border text-info" role="status"><span class="sr-only">Loading…</span></div>
                    </div>

                    <div x-show="!loadingAppointments && walkInApptNote" x-cloak>
                        <p class="text-muted">
                            <i class="fa-solid fa-circle-info me-1"></i>
                            Walk-in visitor — no CRM record to match an appointment against.
                            The visit will still be recorded.
                        </p>
                    </div>

                    <div x-show="!loadingAppointments && !walkInApptNote && appointments.length" x-cloak>
                        <p class="font-weight-600 mb-2">Today's appointments:</p>
                        <template x-for="a in appointments" :key="a.id">
                            <div
                                class="fd-appt-card"
                                :class="{ selected: appointmentId === parseInt(a.id, 10) }"
                                @click="selectAppointment(a)"
                            >
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong x-text="a.datetime || '—'"></strong>
                                        <span class="badge" :class="'bg-' + apptStatusBadge(a.status)" x-text="a.status"></span>
                                        <br>
                                        <small class="text-muted">
                                            Consultant: <span x-text="a.consultant || '—'"></span>
                                            &bull; <span x-text="a.location || '—'"></span>
                                        </small>
                                    </div>
                                    <i class="fa-solid fa-circle-check text-info" x-show="appointmentId === parseInt(a.id, 10)" x-cloak></i>
                                </div>
                            </div>
                        </template>
                        <p class="text-muted small mt-2">
                            Select an appointment above or click <strong>Continue</strong> to proceed without linking one.
                        </p>
                    </div>

                    <p class="text-muted small mt-2" x-show="!loadingAppointments && !walkInApptNote && !appointments.length && apptNoneMsg" x-cloak>
                        No appointments found for this visitor today. You can still continue.
                    </p>
                </div>

                <div class="text-right mt-4">
                    <button type="button" class="btn btn-light me-2" @click="appointmentId = null; claimedAppointment = null; setStep(3)">
                        <i class="fa-solid fa-arrow-left me-1"></i>Back
                    </button>
                    <button type="button" class="btn fd-btn-action" @click="setStep(5)" :disabled="!canContinueAppt">
                        Continue <i class="fa-solid fa-arrow-right ms-1"></i>
                    </button>
                </div>
            </div>

            {{-- STEP 5: Reason --}}
            <div class="fd-wizard-step" x-show="step === 5" x-cloak>
                <h6 class="fd-step-title mb-3 text-uppercase small">Step 5 — Visit Reason</h6>

                <div class="form-group">
                    <label class="font-weight-600">Reason for Visit <span class="text-muted">(optional)</span></label>
                    <select class="form-control" x-model="visitReason">
                        <option value="">— Select reason —</option>
                        @foreach($visitReasons as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label class="font-weight-600">
                        Notes
                        <span class="text-danger" x-show="visitReason === 'other'" x-cloak>*</span>
                    </label>
                    <textarea class="form-control" rows="3" placeholder="Additional notes…" maxlength="2000" x-model.trim="visitNotes" :class="{ 'is-invalid': errors.visitNotes }"></textarea>
                    <div class="invalid-feedback" x-text="errors.visitNotes"></div>
                </div>

                <div class="text-right mt-4">
                    <button type="button" class="btn btn-light me-2" @click="setStep(4)"><i class="fa-solid fa-arrow-left me-1"></i>Back</button>
                    <button type="button" class="btn btn-lg px-5 fd-btn-confirm" @click="submitCheckIn()" :disabled="submitting">
                        <i class="fa-solid fa-paper-plane me-2"></i>Submit Check-In
                    </button>
                </div>
                <div class="fd-spinner mt-3" x-show="submitting" x-cloak>
                    <div class="spinner-border text-success" role="status"><span class="sr-only">Saving…</span></div>
                    <p class="mt-2 text-muted">Saving check-in…</p>
                </div>
            </div>

            {{-- SUCCESS --}}
            <div class="fd-wizard-step" x-show="step === 'success'" x-cloak>
                <div class="fd-success">
                    <i class="fa-solid fa-circle-check"></i>
                    <h5>Check-In Recorded!</h5>
                    <p class="mb-4" x-text="successMsg"></p>
                    <button type="button" class="btn fd-btn-action" @click="resetWizard()">
                        <i class="fa-solid fa-arrow-rotate-right me-2"></i>New Check-In
                    </button>
                    <a :href="waitingUrl" class="btn btn-outline-secondary ms-2">
                        <i class="fa-solid fa-list me-2"></i>Office Visits
                    </a>
                </div>
            </div>

        </div>
    </div>
</div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('alpine:init', function () {
    Alpine.data('frontDeskCheckIn', function (config) {
        var OFF_PATH = ['new-client', 'lead-form', 'not-client'];

        return {
            baseUrl: config.baseUrl,
            csrf: config.csrf,
            waitingUrl: config.waitingUrl,
            stepLabels: ['Contact', 'Match', 'Confirm', 'Appointment', 'Reason'],

            step: 1,
            phone: '',
            email: '',
            path: null,
            alert: '',
            successMsg: '',

            matches: [],
            selectedMatchKey: null,
            walkInSelected: false,
            adminId: null,
            adminType: null,
            adminName: '',
            adminEmail: '',
            adminPhone: '',

            appointmentId: null,
            claimedAppointment: null,
            appointments: [],
            showApptSection: false,
            loadingAppointments: false,
            walkInApptNote: false,
            apptNoneMsg: false,
            canContinueAppt: false,

            visitReason: '',
            visitNotes: '',

            lead: { firstName: '', lastName: '', visitReason: '', visitNotes: '' },

            lookingUp: false,
            submitting: false,
            submittingLead: false,

            errors: {
                phone: '',
                leadFirstName: '',
                leadVisitReason: '',
                leadVisitNotes: '',
                visitNotes: '',
            },

            get isOffPath() {
                return OFF_PATH.indexOf(this.step) !== -1;
            },

            get canConfirmMatch() {
                return this.walkInSelected || this.selectedMatchKey !== null;
            },

            get matchSubtitle() {
                var n = this.matches.length;
                if (!n) return '';
                return n + ' record' + (n > 1 ? 's' : '') + ' found — select one or continue as walk-in.';
            },

            get confirmRows() {
                var rows = [
                    ['Phone entered', this.phone],
                    ['Email entered', this.email || '—'],
                ];
                if (this.adminId) {
                    rows.push(['CRM Match', this.adminName + ' (' + this.adminType + ')']);
                    rows.push(['CRM Email', this.adminEmail || '—']);
                    rows.push(['CRM Phone', this.adminPhone || '—']);
                } else {
                    rows.push(['CRM Match', 'Walk-in (no record)']);
                }
                return rows;
            },

            matchKey(m) {
                return (m.type || '') + '-' + m.id;
            },

            setStep(n) {
                this.step = n;
                this.alert = '';
            },

            showAlert(msg) {
                this.alert = msg || '';
                this.$nextTick(() => {
                    if (this.$refs.alertBox) {
                        this.$refs.alertBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    }
                });
            },

            post(url, data) {
                return fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify(data),
                }).then((r) => r.text().then((text) => {
                    var j = {};
                    try {
                        j = text ? JSON.parse(text) : {};
                    } catch (e) {
                        return Promise.reject(new Error('Invalid server response.'));
                    }
                    if (!r.ok) {
                        var msg = j.message || j.error;
                        if (!msg && j.errors) {
                            msg = Object.values(j.errors).flat().join(' ');
                        }
                        if (!msg) {
                            msg = 'Request failed (' + r.status + ').';
                        }
                        return Promise.reject(new Error(msg));
                    }
                    return j;
                }));
            },

            lookup() {
                this.errors.phone = '';
                if (!this.phone || this.phone.length < 6) {
                    this.errors.phone = 'Please enter a valid phone number.';
                    return;
                }

                this.lookingUp = true;
                this.post(this.baseUrl + '/lookup', {
                    phone: this.phone,
                    email: this.email || null,
                }).then((data) => {
                    this.lookingUp = false;
                    var matches = data.matches || [];
                    if (matches.length === 0) {
                        this.matches = [];
                        this.setStep('new-client');
                        return;
                    }
                    this.matches = matches;
                    this.selectedMatchKey = null;
                    this.walkInSelected = false;
                    this.adminId = null;
                    this.adminType = null;
                    this.adminName = '';
                    this.adminEmail = '';
                    this.adminPhone = '';
                    this.path = null;
                    this.setStep(2);
                }).catch((err) => {
                    this.lookingUp = false;
                    this.showAlert(err && err.message ? err.message : 'Lookup failed. Please try again.');
                });
            },

            selectMatch(m) {
                this.selectedMatchKey = this.matchKey(m);
                this.walkInSelected = false;
                this.adminId = parseInt(m.id, 10);
                this.adminType = m.type;
                this.adminName = m.name || '';
                this.adminEmail = m.email || '';
                this.adminPhone = m.phone || '';
                this.path = 'existing_match';
            },

            selectWalkIn() {
                this.selectedMatchKey = null;
                this.walkInSelected = !this.walkInSelected;
                if (this.walkInSelected) {
                    this.adminId = null;
                    this.adminType = null;
                    this.adminName = 'Walk-in';
                    this.adminEmail = '';
                    this.adminPhone = '';
                    this.path = 'existing_match';
                } else {
                    this.adminName = '';
                    this.path = null;
                }
            },

            goConfirm() {
                if (!this.canConfirmMatch) return;
                this.setStep(3);
            },

            startLeadForm() {
                this.path = 'new_lead';
                this.lead = { firstName: '', lastName: '', visitReason: '', visitNotes: '' };
                this.errors.leadFirstName = '';
                this.errors.leadVisitReason = '';
                this.errors.leadVisitNotes = '';
                this.setStep('lead-form');
            },

            submitLead() {
                this.errors.leadFirstName = '';
                this.errors.leadVisitReason = '';
                this.errors.leadVisitNotes = '';
                var valid = true;

                if (!this.lead.firstName) {
                    this.errors.leadFirstName = 'First name is required.';
                    valid = false;
                }
                if (!this.lead.visitReason) {
                    this.errors.leadVisitReason = 'Please select a reason for the visit.';
                    valid = false;
                }
                if (this.lead.visitReason === 'other' && !this.lead.visitNotes) {
                    this.errors.leadVisitNotes = 'Notes are required when selecting "Other".';
                    valid = false;
                }
                if (!valid) return;

                this.submittingLead = true;
                this.post(this.baseUrl + '/create-lead', {
                    first_name: this.lead.firstName,
                    last_name: this.lead.lastName || null,
                    phone: this.phone,
                    email: this.email || null,
                    visit_reason: this.lead.visitReason || null,
                    visit_notes: this.lead.visitNotes || null,
                }).then((data) => {
                    this.submittingLead = false;
                    if (data.success) {
                        var msg = 'Check-in #' + data.check_in_id + ' saved for ' + (data.lead_name || 'new lead') + '.';
                        if (data.notified_staff) {
                            msg += ' Notification sent to ' + data.notified_staff + '.';
                        }
                        this.successMsg = msg;
                        this.setStep('success');
                    } else {
                        this.showAlert(data.message || 'Could not save check-in. Please try again.');
                    }
                }).catch((err) => {
                    this.submittingLead = false;
                    this.showAlert(err && err.message ? err.message : 'Network error — please try again.');
                });
            },

            goAppointments() {
                this.showApptSection = false;
                this.canContinueAppt = false;
                this.appointments = [];
                this.walkInApptNote = false;
                this.apptNoneMsg = false;
                this.appointmentId = null;
                this.claimedAppointment = null;
                this.setStep(4);
            },

            chooseHasAppointment() {
                this.claimedAppointment = true;
                this.showApptSection = true;
                this.appointmentId = null;
                this.appointments = [];
                this.apptNoneMsg = false;

                if (!this.adminId) {
                    this.walkInApptNote = true;
                    this.loadingAppointments = false;
                    this.canContinueAppt = true;
                    return;
                }

                this.walkInApptNote = false;
                this.loadingAppointments = true;
                this.canContinueAppt = false;

                this.post(this.baseUrl + '/appointments', { admin_id: this.adminId })
                    .then((data) => {
                        this.loadingAppointments = false;
                        var appts = data.appointments || [];
                        if (appts.length === 0) {
                            this.apptNoneMsg = true;
                            this.canContinueAppt = true;
                            return;
                        }
                        this.appointments = appts;
                        this.canContinueAppt = true;
                    })
                    .catch((err) => {
                        this.loadingAppointments = false;
                        this.showAlert(err && err.message ? err.message : 'Could not load appointments. You may continue.');
                        this.canContinueAppt = true;
                    });
            },

            chooseNoAppointment() {
                this.claimedAppointment = false;
                this.appointmentId = null;
                this.showApptSection = false;
                this.canContinueAppt = true;
            },

            selectAppointment(a) {
                this.appointmentId = parseInt(a.id, 10);
                this.canContinueAppt = true;
            },

            apptStatusBadge(status) {
                return ({ confirmed: 'success', pending: 'warning', completed: 'info' })[status] || 'secondary';
            },

            submitCheckIn() {
                this.errors.visitNotes = '';
                if (this.visitReason === 'other' && !this.visitNotes) {
                    this.errors.visitNotes = 'Notes are required when selecting "Other".';
                    return;
                }

                this.submitting = true;
                this.post(this.baseUrl + '/submit', {
                    phone: this.phone,
                    email: this.email || null,
                    admin_id: this.adminId,
                    admin_type: this.adminType,
                    appointment_id: this.appointmentId,
                    claimed_appointment: this.claimedAppointment,
                    visit_reason: this.visitReason || null,
                    visit_notes: this.visitNotes || null,
                }).then((data) => {
                    this.submitting = false;
                    if (data.success) {
                        var msg = 'Check-in #' + data.check_in_id + ' saved.';
                        if (data.notified_staff) {
                            msg += ' Notification sent to ' + data.notified_staff + '.';
                        }
                        this.successMsg = msg;
                        this.setStep('success');
                    } else {
                        this.showAlert(data.message || 'Could not save check-in. Please try again.');
                    }
                }).catch((err) => {
                    this.submitting = false;
                    this.showAlert(err && err.message ? err.message : 'Network error — please try again.');
                });
            },

            resetWizard() {
                this.step = 1;
                this.phone = '';
                this.email = '';
                this.path = null;
                this.alert = '';
                this.successMsg = '';
                this.matches = [];
                this.selectedMatchKey = null;
                this.walkInSelected = false;
                this.adminId = null;
                this.adminType = null;
                this.adminName = '';
                this.adminEmail = '';
                this.adminPhone = '';
                this.appointmentId = null;
                this.claimedAppointment = null;
                this.appointments = [];
                this.showApptSection = false;
                this.loadingAppointments = false;
                this.walkInApptNote = false;
                this.apptNoneMsg = false;
                this.canContinueAppt = false;
                this.visitReason = '';
                this.visitNotes = '';
                this.lead = { firstName: '', lastName: '', visitReason: '', visitNotes: '' };
                this.lookingUp = false;
                this.submitting = false;
                this.submittingLead = false;
                this.errors = {
                    phone: '',
                    leadFirstName: '',
                    leadVisitReason: '',
                    leadVisitNotes: '',
                    visitNotes: '',
                };
            },
        };
    });
});
</script>
@endpush
