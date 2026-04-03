@extends('layouts.crm_client_detail')
@section('title', 'EOI/ROI Sheet')

@section('styles')
<link rel="stylesheet" href="{{ asset('css/listing-container.css') }}">
<link rel="stylesheet" href="{{ asset('css/listing-pagination.css') }}">
<style>
    /* Sheet tabs styling */
    .sheet-tabs {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        padding: 0;
        margin: 0 -20px 20px -20px;
        display: flex;
        gap: 0;
        border-radius: 8px 8px 0 0;
    }
    
    .sheet-tab {
        flex: 1;
        padding: 15px 20px;
        text-align: center;
        color: rgba(255, 255, 255, 0.7);
        text-decoration: none;
        font-weight: 600;
        font-size: 15px;
        transition: all 0.3s ease;
        border-bottom: 3px solid transparent;
        position: relative;
    }
    
    .sheet-tab:hover {
        color: #ffffff;
        background: rgba(255, 255, 255, 0.1);
        text-decoration: none;
    }
    
    .sheet-tab.active {
        color: #ffffff;
        background: rgba(255, 255, 255, 0.15);
        border-bottom-color: #ffffff;
    }
    
    .sheet-tab i {
        margin-right: 8px;
    }

    /* Table styling */
    .eoi-roi-table {
        font-size: 13px;
    }
    
    .eoi-roi-table th {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        font-weight: 600;
        white-space: nowrap;
        padding: 12px 8px;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #495057;
        border-bottom: 2px solid #667eea;
    }

    /* First column (EOI ID) – fit content (e.g. E0039757088). Override listing-container.css 40px rule. */
    .listing-container table.eoi-roi-table th:first-child,
    .listing-container table.eoi-roi-table td:first-child {
        width: 140px !important;
        min-width: 140px !important;
        max-width: none !important;
        white-space: nowrap;
        overflow: visible !important;
        text-overflow: clip !important;
    }
    
    .eoi-roi-table td {
        padding: 10px 8px;
        vertical-align: middle;
    }
    
    .eoi-roi-table tbody tr:hover {
        background-color: #f8f9ff;
    }
    
    .eoi-link {
        color: #667eea;
        font-weight: 600;
        text-decoration: none;
    }
    
.eoi-link:hover {
    color: #764ba2;
    text-decoration: underline;
}

/* Comments cell – warnings text */
.eoi-comments-cell {
    min-width: 300px;
    max-width: 400px;
    font-size: 12px;
    line-height: 1.4;
    word-wrap: break-word;
    white-space: normal;
}

.eoi-comments-cell .warning-text {
    color: #dc3545;
    font-weight: 500;
}

/* Highlight rows with warnings */
.eoi-roi-table tbody tr.has-warning {
    background-color: #fff5f5 !important;
    border-left: 3px solid #dc3545;
}

.eoi-roi-table tbody tr.has-warning:hover {
    background-color: #ffe5e5 !important;
}

/* Filters panel */
    .filter_panel {
        display: none;
        background: #f8f9fa;
        padding: 20px;
        border-radius: 8px;
        margin-bottom: 20px;
    }
    
    .filter_panel.show {
        display: block;
    }
    
    .active-filters-badge {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 600;
        margin-left: 10px;
    }
    
    .clear-filter-btn {
        background: #6c757d;
        color: white;
        border: none;
        padding: 8px 16px;
        border-radius: 6px;
        font-size: 14px;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .clear-filter-btn:hover {
        background: #5a6268;
    }

    /* Per page select */
    .per-page-select {
        width: auto;
        display: inline-block;
        margin-left: 10px;
    }
    
    /* Sort icons */
    .sortable {
        cursor: pointer;
        user-select: none;
        position: relative;
        padding-right: 20px !important;
    }
    
    .sortable:hover {
        background: rgba(102, 126, 234, 0.1);
    }
    
    .sortable::after {
        content: '\f0dc';
        font-family: 'Font Awesome 5 Free';
        font-weight: 900;
        position: absolute;
        right: 8px;
        opacity: 0.3;
    }
    
    .sortable.asc::after {
        content: '\f0de';
        opacity: 1;
        color: #667eea;
    }
    
    .sortable.desc::after {
        content: '\f0dd';
        opacity: 1;
        color: #667eea;
    }

    /* Action buttons */
    .btn-group-vertical .btn {
        margin-bottom: 5px;
    }

    .verify-btn, .send-email-btn, .view-notes-btn {
        white-space: nowrap;
    }

    /* Improved horizontal scroll */
    .table-responsive {
        position: relative;
        overflow-x: auto;
        overflow-y: visible;
        -webkit-overflow-scrolling: touch;
    }

    /* Custom scrollbar styling */
    .table-responsive::-webkit-scrollbar {
        height: 12px;
    }

    .table-responsive::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 10px;
    }

    .table-responsive::-webkit-scrollbar-thumb {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 10px;
    }

    .table-responsive::-webkit-scrollbar-thumb:hover {
        background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
    }

    /* Scroll shadows to indicate more content */
    .table-container {
        position: relative;
    }

    .scroll-indicator {
        position: absolute;
        top: 0;
        bottom: 20px;
        width: 40px;
        pointer-events: none;
        z-index: 10;
        transition: opacity 0.3s ease;
    }

    .scroll-indicator-left {
        left: 0;
        background: linear-gradient(to right, rgba(255,255,255,0.95), transparent);
        opacity: 0;
    }

    .scroll-indicator-right {
        right: 0;
        background: linear-gradient(to left, rgba(255,255,255,0.95), transparent);
    }

    .scroll-indicator-left.visible,
    .scroll-indicator-right.visible {
        opacity: 1;
    }

    /* Pin star styles */
    .listing-container .pin-cell { width: 40px; text-align: center; }
    .listing-container .pin-star {
        font-size: 18px;
        cursor: pointer;
        color: #cbd5e0;
        transition: all 0.2s ease;
    }
    .listing-container .pin-star:hover {
        color: #f59e0b;
        transform: scale(1.2);
    }
    .listing-container .pin-star.pinned {
        color: #f59e0b;
        text-shadow: 0 0 8px rgba(245, 158, 11, 0.3);
    }
    .listing-container .pin-star.pinned:hover {
        color: #cbd5e0;
    }

    /* Scroll hint */
    .scroll-hint {
        text-align: center;
        padding: 10px;
        background: #e7f3ff;
        border-radius: 5px;
        margin-bottom: 10px;
        font-size: 13px;
        color: #0c5460;
    }

    .scroll-hint i {
        animation: slideHint 1.5s ease-in-out infinite;
    }

    @keyframes slideHint {
        0%, 100% { transform: translateX(-5px); }
        50% { transform: translateX(5px); }
    }
</style>
@endsection

@section('content')
<div class="listing-container">
    <section class="listing-section" style="padding-top: 40px;">
        <div class="listing-section-body">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
                    <h4><i class="fas fa-passport"></i> EOI/ROI Sheet</h4>
                    <div class="card-header-actions">
                        <a href="{{ route('clients.index') }}" class="btn btn-theme btn-theme-sm" title="Back to Clients">
                            <i class="fas fa-arrow-left"></i> Back to Clients
                        </a>
                    </div>
                </div>

                {{-- Tabs --}}
                <div class="sheet-tabs">
                    <a href="{{ route('clients.sheets.eoi-roi') }}" class="sheet-tab active">
                        <i class="fas fa-list"></i> List
                    </a>
                    @if(Auth::user() && in_array(Auth::user()->role, [1, 12]))
                    <a href="{{ route('clients.sheets.eoi-roi.insights') }}" class="sheet-tab">
                        <i class="fas fa-chart-bar"></i> Insights
                    </a>
                    @endif
                </div>

                <div class="card-body">
                    {{-- Filter section --}}
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <button type="button" class="btn btn-theme btn-theme-sm filter_btn">
                                <i class="fas fa-filter"></i> Filters
                                @if($activeFilterCount > 0)
                                    <span class="active-filters-badge">{{ $activeFilterCount }}</span>
                                @endif
                            </button>
                            @if($activeFilterCount > 0)
                                <a href="{{ route('clients.sheets.eoi-roi') }}" class="clear-filter-btn">
                                    <i class="fas fa-undo"></i> Clear Filters
                                </a>
                            @endif
                        </div>
                        <div>
                            <label for="per_page" style="display: inline; margin-right: 5px;">Show:</label>
                            <select name="per_page" id="per_page" class="form-control per-page-select">
                                @foreach([10, 25, 50, 100, 200] as $option)
                                    <option value="{{ $option }}" {{ $perPage == $option ? 'selected' : '' }}>
                                        {{ $option }} / page
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    {{-- Office Filter (Always Visible) --}}
                    <div class="office-filter-section mb-3 p-3" style="background: #f8f9fa; border-radius: 5px; border: 1px solid #e3e6f0;">
                        <form action="{{ route('clients.sheets.eoi-roi') }}" method="get" id="officeFilterForm">
                            <input type="hidden" name="per_page" value="{{ $perPage }}">
                            @foreach(request()->except(['office', 'page', 'per_page']) as $key => $value)
                                @if(is_array($value))
                                    @foreach($value as $item)
                                        <input type="hidden" name="{{ $key }}[]" value="{{ $item }}">
                                    @endforeach
                                @else
                                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                                @endif
                            @endforeach
                            
                            <div class="d-flex align-items-center flex-wrap">
                                <label class="mb-0 mr-3" style="font-weight: 600; color: #6c757d;">
                                    <i class="fas fa-building"></i> Filter by Office:
                                </label>
                                @foreach(\App\Models\Branch::orderBy('office_name')->get() as $office)
                                    <div class="form-check form-check-inline mr-3">
                                        <input class="form-check-input office-filter-checkbox" 
                                               type="checkbox" 
                                               name="office[]" 
                                               value="{{ $office->id }}" 
                                               id="office_{{ $office->id }}"
                                               {{ is_array(request('office')) && in_array($office->id, request('office')) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="office_{{ $office->id }}" style="cursor: pointer;">
                                            {{ $office->office_name }}
                                        </label>
                                    </div>
                                @endforeach
                                @if(request('office'))
                                    <a href="{{ route('clients.sheets.eoi-roi', array_merge(request()->except(['office', 'page']), ['per_page' => $perPage])) }}" 
                                       class="btn btn-sm btn-secondary ml-2">
                                        <i class="fas fa-times"></i> Clear Office Filter
                                    </a>
                                @endif
                            </div>
                        </form>
                    </div>

                    {{-- Filters panel --}}
                    <div class="filter_panel {{ $activeFilterCount > 0 ? 'show' : '' }}">
                        <form action="{{ route('clients.sheets.eoi-roi') }}" method="get" id="filterForm">
                            <div class="row">
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="search">Search</label>
                                        <input type="text" name="search" value="{{ request('search') }}" class="form-control" placeholder="Client name or EOI number" id="search">
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="occupation">Occupation</label>
                                        <input type="text" name="occupation" value="{{ request('occupation') }}" class="form-control" placeholder="Nominated occupation" id="occupation">
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="eoi_status">EOI Status</label>
                                        <select name="eoi_status" id="eoi_status" class="form-control">
                                            <option value="">All Statuses</option>
                                            <option value="draft" {{ request('eoi_status') == 'draft' ? 'selected' : '' }}>Draft</option>
                                            <option value="submitted" {{ request('eoi_status') == 'submitted' ? 'selected' : '' }}>Submitted</option>
                                            <option value="invited" {{ request('eoi_status') == 'invited' ? 'selected' : '' }}>Invited</option>
                                            <option value="nominated" {{ request('eoi_status') == 'nominated' ? 'selected' : '' }}>Nominated</option>
                                            <option value="rejected" {{ request('eoi_status') == 'rejected' ? 'selected' : '' }}>Rejected</option>
                                            <option value="withdrawn" {{ request('eoi_status') == 'withdrawn' ? 'selected' : '' }}>Withdrawn</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="from_date">From Date</label>
                                        <input type="text" name="from_date" value="{{ request('from_date') }}" class="form-control datepicker" placeholder="dd/mm/yyyy" id="from_date" autocomplete="off">
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="to_date">To Date</label>
                                        <input type="text" name="to_date" value="{{ request('to_date') }}" class="form-control datepicker" placeholder="dd/mm/yyyy" id="to_date" autocomplete="off">
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label>Subclass</label>
                                        <div class="d-flex gap-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="subclass[]" value="189" id="subclass_189" {{ is_array(request('subclass')) && in_array('189', request('subclass')) ? 'checked' : '' }}>
                                                <label class="form-check-label" for="subclass_189">189</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="subclass[]" value="190" id="subclass_190" {{ is_array(request('subclass')) && in_array('190', request('subclass')) ? 'checked' : '' }}>
                                                <label class="form-check-label" for="subclass_190">190</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="subclass[]" value="491" id="subclass_491" {{ is_array(request('subclass')) && in_array('491', request('subclass')) ? 'checked' : '' }}>
                                                <label class="form-check-label" for="subclass_491">491</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="subclass[]" value="491-Family" id="subclass_491_family" {{ is_array(request('subclass')) && in_array('491-Family', request('subclass')) ? 'checked' : '' }}>
                                                <label class="form-check-label" for="subclass_491_family">491-Family</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label>State</label>
                                        <div class="d-flex gap-3 flex-wrap">
                                            @foreach(['ACT', 'NSW', 'NT', 'QLD', 'SA', 'TAS', 'VIC', 'WA', 'FED'] as $state)
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="state[]" value="{{ $state }}" id="state_{{ $state }}" {{ is_array(request('state')) && in_array($state, request('state')) ? 'checked' : '' }}>
                                                    <label class="form-check-label" for="state_{{ $state }}">{{ $state }}</label>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-12">
                                    <button type="submit" class="btn btn-primary mr-2">Apply Filters</button>
                                    <a href="{{ route('clients.sheets.eoi-roi') }}" class="btn btn-secondary">Reset</a>
                                </div>
                            </div>
                        </form>
                    </div>

                    {{-- Scroll hint --}}
                    <div class="scroll-hint">
                        <i class="fas fa-arrows-alt-h"></i> Scroll horizontally to see all columns.
                    </div>

                    {{-- Table --}}
                    <div class="table-container">
                        <div class="scroll-indicator scroll-indicator-left"></div>
                        <div class="scroll-indicator scroll-indicator-right visible"></div>
                        <div class="table-responsive" id="table-scroll-container">
                            <table class="table table-bordered table-hover eoi-roi-table" id="eoi-roi-sheet-table">
                            <thead>
                                <tr>
                                    <th class="pin-cell" title="Click star to pin row to top"><i class="fas fa-star"></i></th>
                                    <th class="sortable {{ request('sort') == 'eoi_number' ? (request('direction') == 'asc' ? 'asc' : 'desc') : '' }}" data-sort="eoi_number">EOI ID</th>
                                    <th class="sortable {{ request('sort') == 'client_name' ? (request('direction') == 'asc' ? 'asc' : 'desc') : '' }}" data-sort="client_name">Client Name</th>
                                    <th class="sortable {{ request('sort') == 'occupation' ? (request('direction') == 'asc' ? 'asc' : 'desc') : '' }}" data-sort="occupation">Nominated Occupation</th>
                                    <th>Current Job</th>
                                    <th class="sortable {{ request('sort') == 'individual_points' ? (request('direction') == 'asc' ? 'asc' : 'desc') : '' }}" data-sort="individual_points">Individual Points</th>
                                    <th class="sortable {{ request('sort') == 'marital_status' ? (request('direction') == 'asc' ? 'asc' : 'desc') : '' }}" data-sort="marital_status">Marital Status</th>
                                    <th>Partner Points</th>
                                    <th>State</th>
                                    <th>ROI Status</th>
                                    <th class="sortable {{ request('sort') == 'deadline' ? (request('direction') == 'asc' ? 'asc' : 'desc') : '' }}" data-sort="deadline">Deadline</th>
                                    <th>Comments</th>
                                    <th class="sortable {{ request('sort') == 'submission_date' ? (request('direction') == 'asc' ? 'asc' : 'desc') : '' }}" data-sort="submission_date">Last EOI/ROI Sent</th>
                                    <th>Verification Date</th>
                                    <th>Verified By</th>
                                    <th>Workflow Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @if($rows->isEmpty())
                                    <tr>
                                        <td colspan="15" class="text-center text-muted py-4">
                                            <i class="fas fa-info-circle"></i> No EOI/ROI records found matching your criteria.
                                        </td>
                                    </tr>
                                @else
                                    @foreach($rows as $row)
                                        @php
                                            $encodedClientId = base64_encode(convert_uuencode($row->client_id));
                                            $eoiPageUrl = route('clients.detail', [
                                                'client_id' => $encodedClientId,
                                                'client_unique_matter_ref_no' => $row->matter_id,
                                                'tab' => 'eoiroi'
                                            ]);
                                            $subclasses = json_decode($row->eoi_subclasses, true) ?? [];
                                            // eoi_states: may be JSON string (from query) or already array; fallback to legacy EOI_state
                                            $states = is_array($row->eoi_states ?? null) ? $row->eoi_states : (json_decode($row->eoi_states ?? '[]', true) ?: []);
                                            if (empty($states) && !empty($row->EOI_state ?? null)) {
                                                $states = [ $row->EOI_state ];
                                            }
                                            // Fetch full EOI record for confirmation workflow data
                                            $eoiRecord = \App\Models\ClientEoiReference::find($row->eoi_id);
                                        @endphp
                                        <tr class="{{ !empty($row->warnings_text) ? 'has-warning' : '' }}">
                                            <td class="pin-cell">
                                                <i class="fas fa-star pin-star {{ ($row->is_pinned ?? false) ? 'pinned' : '' }}"
                                                   data-eoi-id="{{ $row->eoi_id }}"
                                                   title="{{ ($row->is_pinned ?? false) ? 'Unpin from top' : 'Pin to top' }}"></i>
                                            </td>
                                            <td><a href="{{ $eoiPageUrl }}" class="eoi-link">{{ $row->EOI_number ?? '—' }}</a></td>
                                            <td><a href="{{ $eoiPageUrl }}" class="eoi-link">{{ $row->first_name }} {{ $row->last_name }}</a></td>
                                            <td>{{ $row->EOI_occupation ?? '—' }}</td>
                                            <td class="text-muted">—</td>
                                            <td>{{ $row->individual_points ?? '—' }}</td>
                                            <td>{{ $row->marital_status ?? '—' }}</td>
                                            <td>{{ $row->partner_points ?? '—' }}</td>
                                            <td>{{ !empty($states) ? implode(', ', $states) : '—' }}</td>
                                            <td>
                                                @php
                                                    $roi = data_get($row, 'EOI_ROI') ?? data_get($row, 'eoi_roi');
                                                    $status = data_get($row, 'eoi_status');
                                                @endphp
                                                @if($roi)
                                                    {{ $roi }}
                                                @endif
                                                @if($status)
                                                    <span class="badge badge-{{ $status == 'invited' ? 'success' : ($status == 'submitted' ? 'primary' : 'secondary') }}">
                                                        {{ ucfirst($status) }}
                                                    </span>
                                                @endif
                                                @if(!$roi && !$status)
                                                    —
                                                @endif
                                            </td>
                                            <td>{{ $row->deadline ? \Carbon\Carbon::parse($row->deadline)->format('d/m/Y') : '—' }}</td>
                                            <td class="eoi-comments-cell" title="{{ $row->warnings_text ?? '' }}">
                                                @if(!empty($row->warnings_text))
                                                    <span class="warning-text">{{ $row->warnings_text }}</span>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($eoiRecord && $eoiRecord->confirmation_email_sent_at)
                                                    {{ $eoiRecord->confirmation_email_sent_at->format('d/m/Y H:i') }}
                                                    <br><small class="text-muted">Email sent</small>
                                                @elseif($row->EOI_submission_date)
                                                    {{ \Carbon\Carbon::parse($row->EOI_submission_date)->format('d/m/Y') }}
                                                    <br><small class="text-muted">EOI submitted</small>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($eoiRecord && $eoiRecord->confirmation_date)
                                                    <div style="line-height: 1.6;">
                                                        <strong>{{ $eoiRecord->confirmation_date->format('d/m/Y H:i') }}</strong>
                                                        <br><small class="text-muted"><i class="fas fa-user-shield"></i> Staff</small>
                                                    </div>
                                                    @if($eoiRecord->client_last_confirmation)
                                                        <div style="line-height: 1.6; margin-top: 8px; padding-top: 8px; border-top: 1px solid #e0e0e0;">
                                                            <strong>{{ $eoiRecord->client_last_confirmation->format('d/m/Y H:i') }}</strong>
                                                            <br><small class="text-success"><i class="fas fa-user-check"></i> Client</small>
                                                        </div>
                                                    @endif
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($eoiRecord && $eoiRecord->verifier)
                                                    <div style="line-height: 1.6;">
                                                        <strong>{{ $eoiRecord->verifier->first_name }} {{ $eoiRecord->verifier->last_name }}</strong>
                                                        <br><small class="text-muted"><i class="fas fa-user-shield"></i> Staff</small>
                                                    </div>
                                                    @if($eoiRecord->client_confirmation_status === 'confirmed')
                                                        <div style="line-height: 1.6; margin-top: 8px; padding-top: 8px; border-top: 1px solid #e0e0e0;">
                                                            <strong class="text-success">Client Confirmed</strong>
                                                            <br><small class="text-success"><i class="fas fa-user-check"></i> Client</small>
                                                        </div>
                                                    @elseif($eoiRecord->client_confirmation_status === 'amendment_requested')
                                                        <div style="line-height: 1.6; margin-top: 8px; padding-top: 8px; border-top: 1px solid #e0e0e0;">
                                                            <strong class="text-warning">Amendment Requested</strong>
                                                            <br><small class="text-warning"><i class="fas fa-exclamation-triangle"></i> Client</small>
                                                        </div>
                                                    @endif
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td>
                                                @php
                                                    // Determine workflow status
                                                    if (!$eoiRecord) {
                                                        $workflowStatus = 'draft';
                                                        $statusBadge = '<span class="badge badge-secondary"><i class="fas fa-file"></i> Draft</span>';
                                                    } elseif (!$eoiRecord->staff_verified) {
                                                        $workflowStatus = 'pending_verification';
                                                        $statusBadge = '<span class="badge badge-warning"><i class="fas fa-hourglass-half"></i> Pending Verification</span>';
                                                    } elseif ($eoiRecord->staff_verified && !$eoiRecord->confirmation_email_sent_at) {
                                                        $workflowStatus = 'verified';
                                                        $statusBadge = '<span class="badge badge-info"><i class="fas fa-check-circle"></i> Verified - Ready to Send</span>';
                                                    } elseif ($eoiRecord->client_confirmation_status === 'confirmed') {
                                                        $workflowStatus = 'completed';
                                                        $statusBadge = '<span class="badge badge-success"><i class="fas fa-check-double"></i> Client Confirmed</span>';
                                                    } elseif ($eoiRecord->client_confirmation_status === 'amendment_requested') {
                                                        $workflowStatus = 'amendment';
                                                        $statusBadge = '<span class="badge badge-danger"><i class="fas fa-exclamation-triangle"></i> Amendment Requested</span>';
                                                    } elseif ($eoiRecord->client_confirmation_status === 'pending') {
                                                        $workflowStatus = 'awaiting_client';
                                                        $statusBadge = '<span class="badge badge-primary"><i class="fas fa-clock"></i> Awaiting Client Response</span>';
                                                    } else {
                                                        $workflowStatus = 'unknown';
                                                        $statusBadge = '<span class="badge badge-secondary">—</span>';
                                                    }
                                                @endphp
                                                {!! $statusBadge !!}
                                            </td>
                                        </tr>
                                    @endforeach
                                @endif
                            </tbody>
                        </table>
                        </div>
                    </div>

                    {{-- Pagination --}}
                    @if($rows->hasPages())
                        <div class="card-footer">
                            {!! $rows->appends(\Request::except('page'))->render() !!}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </section>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
jQuery(document).ready(function($) {
    // Horizontal scroll indicators
    var $scrollContainer = $('#table-scroll-container');
    var $leftIndicator = $('.scroll-indicator-left');
    var $rightIndicator = $('.scroll-indicator-right');

    function updateScrollIndicators() {
        var scrollLeft = $scrollContainer.scrollLeft();
        var scrollWidth = $scrollContainer[0].scrollWidth;
        var clientWidth = $scrollContainer[0].clientWidth;
        var maxScroll = scrollWidth - clientWidth;

        // Show/hide left indicator
        if (scrollLeft > 10) {
            $leftIndicator.addClass('visible');
        } else {
            $leftIndicator.removeClass('visible');
        }

        // Show/hide right indicator
        if (scrollLeft < maxScroll - 10) {
            $rightIndicator.addClass('visible');
        } else {
            $rightIndicator.removeClass('visible');
        }
    }

    // Update on scroll
    $scrollContainer.on('scroll', updateScrollIndicators);
    
    // Update on window resize
    $(window).on('resize', updateScrollIndicators);
    
    // Initial check
    setTimeout(updateScrollIndicators, 100);

    // Smooth scroll with mouse wheel horizontal
    $scrollContainer.on('wheel', function(e) {
        if (e.originalEvent.deltaY !== 0 && !e.shiftKey) {
            e.preventDefault();
            this.scrollLeft += e.originalEvent.deltaY;
        }
    });

    // Per page change
    $('#per_page').on('change', function() {
        var currentUrl = new URL(window.location.href);
        currentUrl.searchParams.set('per_page', $(this).val());
        currentUrl.searchParams.delete('page');
        window.location.href = currentUrl.toString();
    });

    // Filter panel toggle
    $('.filter_btn').on('click', function() {
        $('.filter_panel').toggleClass('show');
    });

    // Datepicker - Flatpickr (loaded in layout)
    if (typeof flatpickr !== 'undefined') {
        $('.datepicker').each(function() {
            var $this = $(this);
            if (!$this.data('flatpickr')) {
                flatpickr(this, {
                    dateFormat: 'd/m/Y',
                    allowInput: true,
                    clickOpens: true,
                    locale: { firstDayOfWeek: 1 },
                    onChange: function(selectedDates, dateStr) {
                        $this.val(dateStr);
                        $this.trigger('change');
                    }
                });
            }
        });
    }

    // Sortable columns
    $('.sortable').on('click', function() {
        var sortField = $(this).data('sort');
        var currentSort = '{{ request("sort") }}';
        var currentDirection = '{{ request("direction", "desc") }}';
        
        var newDirection = 'desc';
        if (currentSort === sortField && currentDirection === 'desc') {
            newDirection = 'asc';
        }
        
        var currentUrl = new URL(window.location.href);
        currentUrl.searchParams.set('sort', sortField);
        currentUrl.searchParams.set('direction', newDirection);
        currentUrl.searchParams.delete('page');
        window.location.href = currentUrl.toString();
    });

    // Per page selector
    $('#per_page').on('change', function() {
        var perPage = $(this).val();
        var currentUrl = new URL(window.location.href);
        currentUrl.searchParams.set('per_page', perPage);
        currentUrl.searchParams.delete('page'); // Reset to page 1
        window.location.href = currentUrl.toString();
    });

    // Office filter auto-submit
    $('.office-filter-checkbox').on('change', function() {
        $('#officeFilterForm').submit();
    });

    // Handle star/pin clicks
    var eoiTable = document.getElementById('eoi-roi-sheet-table');
    if (eoiTable) {
        eoiTable.addEventListener('click', function(e) {
            var star = e.target.closest('.pin-star');
            if (!star) return;

            e.preventDefault();
            e.stopPropagation();

            var $star = $(star);
            var eoiId = $star.data('eoi-id');

            if (!eoiId) {
                if (typeof iziToast !== 'undefined') {
                    iziToast.warning({ title: 'Error', message: 'Cannot pin: missing data', position: 'topRight' });
                }
                return;
            }

            $star.css('pointer-events', 'none');

            $.ajax({
                url: '{{ url("/clients/sheets/eoi-roi") }}/' + eoiId + '/toggle-pin',
                method: 'POST',
                data: {
                    _token: '{{ csrf_token() }}'
                },
                success: function(response) {
                    if (response.success) {
                        $star.toggleClass('pinned');
                        $star.attr('title', response.is_pinned ? 'Unpin from top' : 'Pin to top');
                        if (typeof iziToast !== 'undefined') {
                            iziToast.success({
                                title: 'Success',
                                message: response.message,
                                position: 'topRight',
                                timeout: 2000
                            });
                        }
                        setTimeout(function() {
                            window.location.reload();
                        }, 500);
                    } else {
                        if (typeof iziToast !== 'undefined') {
                            iziToast.error({
                                title: 'Error',
                                message: response.message || 'Failed to update pin status',
                                position: 'topRight'
                            });
                        }
                        $star.css('pointer-events', 'auto');
                    }
                },
                error: function(xhr) {
                    if (typeof iziToast !== 'undefined') {
                        iziToast.error({
                            title: 'Error',
                            message: 'Failed to update pin status. Please try again.',
                            position: 'topRight'
                        });
                    }
                    $star.css('pointer-events', 'auto');
                }
            });
        }, true);
    }
});
</script>
@endpush
