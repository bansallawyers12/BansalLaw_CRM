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
                        <small class="text-muted">Upload Outlook .msg files, review suggested client/matter matches, then confirm import.</small>
                    </div>
                    <span id="python-service-status" class="badge badge-secondary">Checking service...</span>
                </div>

                <div class="card-body">
                    <div id="smart-import-upload-panel">
                        <div class="smart-import-dropzone" id="smart-import-dropzone">
                            <input type="file" id="smart-import-files" accept=".msg" multiple>
                            <i class="fas fa-cloud-upload-alt fa-2x mb-2" style="color: var(--navy, #1e3d60);"></i>
                            <p class="mb-1"><strong>Drop .msg files here</strong> or click to browse</p>
                            <small class="text-muted">Up to 10 files, max {{ (int) config('crm.email_upload_max_kb', 30720) / 1024 }}MB each</small>
                        </div>
                        <div class="smart-import-status mt-2" id="smart-import-upload-status"></div>
                        <div class="smart-import-actions">
                            <button type="button" class="btn btn-primary btn-theme-lg" id="smart-import-analyze-btn" disabled>
                                <i class="fas fa-search mr-1"></i> Analyze &amp; Match
                            </button>
                        </div>
                    </div>

                    <div id="smart-import-review-panel" style="display:none;">
                        <div class="smart-import-summary" id="smart-import-summary"></div>

                        <div class="table-responsive">
                            <table class="table table-sm" id="smart-import-table">
                                <thead>
                                    <tr>
                                        <th style="width:28%;">Email</th>
                                        <th style="width:14%;">Type</th>
                                        <th style="width:22%;">Client</th>
                                        <th style="width:18%;">Matter</th>
                                        <th style="width:8%;">Match</th>
                                        <th style="width:10%;"></th>
                                    </tr>
                                </thead>
                                <tbody id="smart-import-table-body"></tbody>
                            </table>
                        </div>

                        <div class="smart-import-actions">
                            <button type="button" class="btn btn-success" id="smart-import-confirm-high-btn">
                                <i class="fas fa-check-double mr-1"></i> Confirm High Confidence
                            </button>
                            <button type="button" class="btn btn-primary btn-theme-lg" id="smart-import-confirm-selected-btn">
                                <i class="fas fa-check mr-1"></i> Confirm Selected
                            </button>
                            <button type="button" class="btn btn-outline-secondary" id="smart-import-reset-btn">
                                <i class="fas fa-redo mr-1"></i> Upload More
                            </button>
                        </div>
                        <div class="smart-import-status mt-2" id="smart-import-confirm-status"></div>
                    </div>
                </div>
            </div>
        </div>
    </section>
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
</script>
<script src="{{ asset('js/smart-email-import.js') }}?v={{ filemtime(public_path('js/smart-email-import.js')) }}"></script>
@endpush
