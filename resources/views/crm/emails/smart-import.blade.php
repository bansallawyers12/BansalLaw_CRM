@extends('layouts.crm_client_detail')
@section('title', 'Smart Email Import')

@section('styles')
<link rel="stylesheet" href="{{ asset('css/listing-container.css') }}">
<style>
    .smart-import-page { padding-top: 32px; }
    .smart-import-dropzone {
        border: 2px dashed #c5d4e8;
        border-radius: 10px;
        padding: 36px 24px;
        text-align: center;
        background: #f8fbff;
        cursor: pointer;
        transition: border-color 0.2s ease, background 0.2s ease;
    }
    .smart-import-dropzone.dragover,
    .smart-import-dropzone:hover {
        border-color: var(--navy, #1e3d60);
        background: #eef4fb;
    }
    .smart-import-dropzone input[type="file"] { display: none; }
    .smart-import-summary {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
        margin-bottom: 16px;
    }
    .smart-import-summary .badge-pill {
        padding: 8px 14px;
        border-radius: 999px;
        font-size: 13px;
        font-weight: 600;
    }
    .confidence-high { color: #1b7a3f; font-weight: 700; }
    .confidence-medium { color: #b7791f; font-weight: 700; }
    .confidence-low { color: #9aa5b1; font-weight: 700; }
    .smart-import-preview {
        display: none;
        margin-top: 10px;
        padding: 12px;
        background: #f7f9fc;
        border-radius: 8px;
        font-size: 13px;
        line-height: 1.5;
    }
    .smart-import-preview.show { display: block; }
    .smart-import-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        margin-top: 16px;
    }
    .row-unmatched { background: #fff8f0 !important; }
    .row-high-confidence { background: #f2fff6 !important; }
    .smart-import-client-select { min-width: 220px; }
    .smart-import-matter-select { min-width: 180px; }
    .smart-import-status { min-height: 24px; font-size: 13px; color: #5a6b7d; }
    .smart-import-suggestion-pick {
        font-size: 12px;
        margin-bottom: 6px;
    }
    .smart-import-field-label {
        display: block;
        font-size: 11px;
        color: #6b7c93;
        margin-bottom: 3px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }
    .smart-import-review-hint {
        font-size: 13px;
        color: #5a6b7d;
        margin-bottom: 12px;
    }

    /* Upload / import blocking loader */
    body.smart-import-busy {
        overflow: hidden;
    }

    body.smart-import-busy .smart-import-dropzone,
    body.smart-import-busy .smart-import-actions button,
    body.smart-import-busy #smart-import-table {
        pointer-events: none;
    }

    .smart-import-loading-overlay {
        display: none;
        position: fixed;
        inset: 0;
        z-index: 10050;
        background: rgba(30, 61, 96, 0.45);
        backdrop-filter: blur(3px);
        align-items: center;
        justify-content: center;
        padding: 24px;
    }

    .smart-import-loading-overlay.active {
        display: flex;
    }

    .smart-import-loading-card {
        width: 100%;
        max-width: 420px;
        background: #fff;
        border-radius: 14px;
        box-shadow: 0 20px 50px rgba(0, 0, 0, 0.22);
        padding: 32px 28px 26px;
        text-align: center;
        animation: smartImportLoadingIn 0.22s ease-out;
    }

    @keyframes smartImportLoadingIn {
        from { opacity: 0; transform: translateY(12px) scale(0.98); }
        to { opacity: 1; transform: translateY(0) scale(1); }
    }

    .smart-import-loading-icon {
        position: relative;
        width: 64px;
        height: 64px;
        margin: 0 auto 18px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--navy, #1e3d60);
        font-size: 28px;
    }

    .smart-import-loading-spinner {
        position: absolute;
        inset: 0;
        border: 3px solid #dbe4ef;
        border-top-color: var(--navy, #1e3d60);
        border-radius: 50%;
        animation: smartImportSpin 0.85s linear infinite;
    }

    @keyframes smartImportSpin {
        to { transform: rotate(360deg); }
    }

    .smart-import-loading-title {
        margin: 0 0 8px;
        font-size: 18px;
        font-weight: 600;
        color: #1e293b;
    }

    .smart-import-loading-message {
        margin: 0 0 10px;
        font-size: 14px;
        color: #5a6b7d;
        line-height: 1.45;
    }

    .smart-import-loading-files {
        margin: 0 0 14px;
        font-size: 12px;
        color: #64748b;
        line-height: 1.4;
        max-height: 72px;
        overflow: hidden;
        word-break: break-word;
    }

    .smart-import-loading-progress {
        height: 6px;
        background: #e8eef5;
        border-radius: 999px;
        overflow: hidden;
        margin-bottom: 12px;
    }

    .smart-import-loading-progress-bar {
        height: 100%;
        width: 0%;
        background: linear-gradient(90deg, var(--navy, #1e3d60), #3b82f6);
        border-radius: 999px;
        transition: width 0.35s ease;
    }

    .smart-import-loading-hint {
        margin: 0;
        font-size: 12px;
        color: #94a3b8;
    }
</style>
@endsection

@section('content')
<div class="listing-container smart-import-page">
    <section class="listing-section">
        <div class="listing-section-body">
            @include('../Elements/flash-message')

            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="mb-1">Smart Email Import</h4>
@php
    $crmEmailUploadAccept = implode(',', array_map(static fn ($ext) => '.' . ltrim((string) $ext, '.'), config('crm.email_upload_allowed_extensions', ['msg', 'eml'])));
    $crmEmailUploadLabel = implode(', ', array_map(static fn ($ext) => '.' . ltrim((string) $ext, '.'), config('crm.email_upload_allowed_extensions', ['msg', 'eml'])));
@endphp
                        <small class="text-muted">Upload Outlook email files ({{ $crmEmailUploadLabel }}), review suggestions, then manually pick client/matter or confirm matches before import.</small>
                    </div>
                    <span id="python-service-status" class="badge badge-secondary">Checking service...</span>
                </div>

                <div class="card-body">
                    <div id="smart-import-upload-panel">
                        <div class="smart-import-dropzone" id="smart-import-dropzone">
                            <input type="file" id="smart-import-files" accept="{{ $crmEmailUploadAccept }}" multiple>
                            <i class="fas fa-cloud-upload-alt fa-2x mb-2" style="color: var(--navy, #1e3d60);"></i>
                            <p class="mb-1"><strong>Drop Outlook email files ({{ $crmEmailUploadLabel }}) here</strong> or click to browse</p>
                            <small class="text-muted">Up to 10 files, max {{ (int) config('crm.email_upload_max_kb', 30720) / 1024 }}MB each</small>
                        </div>
                        <div class="smart-import-status mt-2" id="smart-import-upload-status"></div>
                        <div class="smart-import-actions">
                            <button type="button" class="btn btn-primary btn-theme-lg" id="smart-import-analyze-btn" disabled>
                                <i class="fas fa-search me-1"></i> Analyze &amp; Match
                            </button>
                        </div>
                    </div>

                    <div id="smart-import-review-panel" style="display:none;">
                        <div class="smart-import-summary" id="smart-import-summary"></div>
                        <p class="smart-import-review-hint">
                            Suggestions are pre-filled but editable. Search for any client, pick a matter, tick <strong>Import</strong>, then use <strong>Confirm row</strong> or <strong>Confirm Selected</strong>.
                        </p>

                        <div class="table-responsive">
                            <table class="table table-sm" id="smart-import-table">
                                <thead>
                                    <tr>
                                        <th style="width:24%;">Email</th>
                                        <th style="width:10%;">Type</th>
                                        <th style="width:24%;">Client</th>
                                        <th style="width:18%;">Matter</th>
                                        <th style="width:8%;">Match</th>
                                        <th style="width:8%;" class="text-center">
                                            <label class="mb-0" style="font-weight:600;cursor:pointer;">
                                                <input type="checkbox" id="smart-import-select-all" title="Select all"> Import
                                            </label>
                                        </th>
                                        <th style="width:8%;"></th>
                                    </tr>
                                </thead>
                                <tbody id="smart-import-table-body"></tbody>
                            </table>
                        </div>

                        <div class="smart-import-actions">
                            <button type="button" class="btn btn-success" id="smart-import-confirm-high-btn">
                                <i class="fas fa-check-double me-1"></i> Confirm High Confidence
                            </button>
                            <button type="button" class="btn btn-primary btn-theme-lg" id="smart-import-confirm-selected-btn">
                                <i class="fas fa-check me-1"></i> Confirm Selected
                            </button>
                            <button type="button" class="btn btn-outline-secondary" id="smart-import-reset-btn">
                                <i class="fas fa-redo me-1"></i> Upload More
                            </button>
                        </div>
                        <div class="smart-import-status mt-2" id="smart-import-confirm-status"></div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<div class="smart-import-loading-overlay" id="smartImportLoadingOverlay" aria-hidden="true" aria-live="polite" aria-busy="false">
    <div class="smart-import-loading-card" role="status">
        <div class="smart-import-loading-icon" aria-hidden="true">
            <i class="fas fa-envelope-open-text"></i>
            <span class="smart-import-loading-spinner"></span>
        </div>
        <h3 class="smart-import-loading-title" id="smartImportLoadingTitle">Processing emails</h3>
        <p class="smart-import-loading-message" id="smartImportLoadingMessage">Please wait while your emails are being processed…</p>
        <p class="smart-import-loading-files" id="smartImportLoadingFiles"></p>
        <div class="smart-import-loading-progress" aria-hidden="true">
            <div class="smart-import-loading-progress-bar" id="smartImportLoadingProgressBar"></div>
        </div>
        <p class="smart-import-loading-hint">Do not close or refresh this page</p>
    </div>
</div>
@endsection

@push('scripts')
<script>
    window.SmartEmailImportConfig = {
        urls: {
            analyze: @json(route('emails.smart-import.analyze')),
            confirm: @json(route('emails.smart-import.confirm')),
            checkService: @json(route('email.check.service')),
            getAllClients: @json(route('clients.getallclients')),
            listMatters: @json(route('clients.listAllMattersWRTSelClient')),
            clientDetail: @json(url('/clients/detail')),
        },
        csrfToken: @json(csrf_token()),
        maxFiles: 10,
    };
    window.__CRM_EMAIL_ALLOWED_EXTENSIONS__ = @json(config('crm.email_upload_allowed_extensions', ['msg', 'eml']));
</script>
<script src="{{ asset('js/email-upload-filename.js') }}?v={{ filemtime(public_path('js/email-upload-filename.js')) }}"></script>
<script src="{{ asset('js/smart-email-import.js') }}?v={{ filemtime(public_path('js/smart-email-import.js')) }}"></script>
@endpush
