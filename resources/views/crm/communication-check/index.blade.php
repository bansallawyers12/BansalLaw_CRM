@extends('layouts.crm_client_detail')
@section('title', 'Communication Check')

@section('styles')
<link rel="stylesheet" href="{{ asset('css/listing-container.css') }}">
<style>
    .cc-page { padding-top: 32px; }
    .cc-dropzone {
        border: 2px dashed #c5d4e8;
        border-radius: 10px;
        padding: 36px 24px;
        text-align: center;
        background: #f8fbff;
        cursor: pointer;
        transition: border-color 0.2s ease, background 0.2s ease;
    }
    .cc-dropzone.dragover,
    .cc-dropzone:hover {
        border-color: var(--navy, #1e3d60);
        background: #eef4fb;
    }
    .cc-dropzone input[type="file"] { display: none; }
    .cc-actions { margin-top: 16px; display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }
    .cc-status { min-height: 24px; font-size: 13px; color: #5a6b7d; margin-top: 8px; }
    .cc-summary { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 16px; }
    .cc-pill {
        padding: 8px 14px;
        border-radius: 999px;
        font-size: 13px;
        font-weight: 600;
        background: #eef2f7;
    }
    .cc-pill.worked { background: #e6f6ec; color: #1b7a3f; }
    .cc-pill.logged { background: #e8f0fe; color: #1e3d60; }
    .cc-pill.gap { background: #fdecea; color: #b42318; }
    .cc-pill.unsupported { background: #f5f5f5; color: #667085; }
    .cc-card {
        border: 1px solid #d9e2ec;
        border-radius: 10px;
        padding: 16px 18px;
        margin-bottom: 14px;
        background: #fff;
    }
    .cc-card h5 { margin: 0 0 8px; font-size: 16px; }
    .cc-meta { font-size: 13px; color: #52606d; margin-bottom: 6px; }
    .cc-verdict {
        display: inline-block;
        font-weight: 700;
        font-size: 13px;
        padding: 4px 10px;
        border-radius: 6px;
        margin-bottom: 10px;
    }
    .cc-verdict.worked { background: #e6f6ec; color: #1b7a3f; }
    .cc-verdict.logged { background: #e8f0fe; color: #1e3d60; }
    .cc-verdict.gap { background: #fdecea; color: #b42318; }
    .cc-verdict.unsupported { background: #f0f0f0; color: #667085; }
    .cc-followups { margin: 8px 0 0; padding-left: 18px; font-size: 13px; }
    .cc-local-banner {
        background: #fff8e6;
        border: 1px solid #f0d78c;
        color: #7a5b00;
        border-radius: 8px;
        padding: 10px 14px;
        margin-bottom: 16px;
        font-size: 13px;
    }
    .cc-loading-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.45);
        z-index: 2000;
        align-items: center;
        justify-content: center;
    }
    .cc-loading-overlay.active { display: flex; }
    .cc-loading-card {
        background: #fff;
        border-radius: 12px;
        padding: 28px 32px;
        max-width: 420px;
        text-align: center;
        box-shadow: 0 12px 40px rgba(0,0,0,.2);
    }
    body.cc-busy { overflow: hidden; }
</style>
@endsection

@section('content')
<div class="listing-container cc-page">
    <section class="listing-section">
        <div class="listing-section-body">
            @include('../Elements/flash-message')

            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="mb-1">Communication Check</h4>
                        <small class="text-muted">
                            Upload email, SMS, or call (Recents) screenshots → extract → match CRM → Logged / Worked / Gap.
                            Calls only match logged Call Actions (no PBX yet). Assistive only; human confirms.
                        </small>
                    </div>
                </div>
                <div class="card-body">
                    <div class="cc-local-banner">
                        <strong>Assistive only.</strong>
                        Super Admin (or staff granted access) can run this tool. Confirm results in CRM before treating as staff accountability fact. Vision may misread dates and numbers.
                    </div>

                    <div id="cc-upload-panel">
                        <div class="cc-dropzone" id="cc-dropzone">
                            <input type="file" id="cc-files" accept="image/jpeg,image/png,image/webp,image/gif" multiple>
                            <i class="fa-solid fa-image fa-2x mb-2" style="color: var(--navy, #1e3d60);"></i>
                            <p class="mb-1"><strong>Drop email, SMS, or call screenshots here</strong> or click to browse</p>
                            <small class="text-muted">Up to {{ $maxFiles }} images, max {{ (int) ($maxFileKb / 1024) }}MB each (jpg/png/webp)</small>
                        </div>

                        <div class="cc-actions">
                            <label class="mb-0 me-2">Look back
                                <select id="cc-lookback" class="form-select form-select-sm d-inline-block" style="width:auto; display:inline-block;">
                                    <option value="7" @selected($lookbackDefault === 7)>7 days</option>
                                    <option value="30" @selected($lookbackDefault === 30)>30 days</option>
                                    <option value="90" @selected($lookbackDefault === 90)>90 days</option>
                                </select>
                            </label>
                            <button type="button" class="btn btn-primary btn-theme-lg" id="cc-analyze-btn" disabled>
                                <i class="fa-solid fa-magnifying-glass-chart me-1"></i> Extract &amp; Match
                            </button>
                            <button type="button" class="btn btn-outline-secondary" id="cc-reset-btn" style="display:none;">
                                <i class="fa-solid fa-arrow-rotate-right me-1"></i> New check
                            </button>
                        </div>
                        <div class="cc-status" id="cc-upload-status"></div>
                    </div>

                    <div id="cc-results-panel" style="display:none;">
                        <div class="cc-summary" id="cc-summary"></div>
                        <p class="text-muted small" id="cc-disclaimer"></p>
                        <div id="cc-results"></div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<div class="cc-loading-overlay" id="ccLoadingOverlay" aria-hidden="true">
    <div class="cc-loading-card" role="status">
        <i class="fa-solid fa-spinner fa-spin fa-2x mb-3" style="color: var(--navy, #1e3d60);"></i>
        <h5>Checking communications…</h5>
        <p class="mb-0 text-muted small">Vision extract, then CRM match. Do not close this page.</p>
    </div>
</div>
@endsection

@push('scripts')
<script>
    window.CommunicationCheckConfig = {
        routes: {
            analyze: @json(route('communication-check.analyze')),
        },
        csrf: @json(csrf_token()),
        maxFiles: {{ (int) $maxFiles }},
        followupHours: {{ (int) $followupHours }},
    };
</script>
<script src="{{ asset('js/communication-check.js') }}?v={{ @filemtime(public_path('js/communication-check.js')) ?: time() }}"></script>
@endpush
