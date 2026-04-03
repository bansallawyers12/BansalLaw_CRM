@extends('layouts.crm_client_detail')
@section('title', ($config['title'] ?? 'Visa Sheet') . ' - ' . ($tabConfig['title'] ?? 'Ongoing'))

@section('styles')
<link rel="stylesheet" href="{{ asset('css/listing-container.css') }}">
<style>
    /* Remove top blank space */
    .visa-sheet-page.listing-container { 
        margin-top: 0 !important; 
        padding-top: 0 !important; 
    }
    .visa-sheet-page .listing-section {
        padding-top: 0 !important;
    }
    .visa-sheet-page .art-sheet-card {
        margin-top: 0 !important;
    }
    
    /* CRM Color Theme - Using #005792 */
    .visa-sheet-page .art-sheet-sticky-header {
        position: sticky; top: 70px; z-index: 100;
        background: linear-gradient(180deg, #f0f7fa 0%, #e6f2f7 100%);
        box-shadow: 0 2px 8px rgba(0, 87, 146, 0.1);
        border-bottom: 1px solid #b3d9ea;
        margin: 0 -1px 0 -1px;
    }
    .visa-sheet-page .art-sheet-top-bar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 12px;
        padding: 10px 20px 0;
        border-bottom: 1px solid #b3d9ea;
    }
    .visa-sheet-page .art-sheet-title { 
        font-size: 1.2rem; 
        font-weight: 600; 
        color: #005792; 
        margin: 0;
        display: flex;
        align-items: center;
    }
    .visa-sheet-page .art-sheet-title i {
        margin-right: 8px;
        color: #005792;
    }
    .visa-sheet-page .btn-theme,
    .visa-sheet-page .btn-theme-sm {
        background: linear-gradient(135deg, #005792 0%, #004670 100%) !important;
        color: #fff !important;
        border: 1px solid #005792 !important;
        padding: 8px 16px;
        border-radius: 6px;
        font-weight: 500;
        font-size: 14px;
        transition: all 0.2s ease;
    }
    .visa-sheet-page .btn-theme:hover,
    .visa-sheet-page .btn-theme-sm:hover {
        background: linear-gradient(135deg, #004670 0%, #003a58 100%) !important;
        color: #fff !important;
        border-color: #004670 !important;
        box-shadow: 0 2px 8px rgba(0, 87, 146, 0.2);
    }
    .visa-sheet-page .visa-tabs-row {
        display: flex; align-items: center; gap: 12px;
        padding: 12px 20px; background: #f0f7fa; border-bottom: 1px solid #b3d9ea;
    }
    .visa-sheet-page .visa-tabs-label {
        color: #374151 !important;
    }
    .visa-sheet-page .sheet-tabs {
        background: #b3d9ea; padding: 4px; border-radius: 8px;
        display: flex; gap: 2px; flex-wrap: wrap;
    }
    .visa-sheet-page .sheet-tab {
        padding: 10px 18px; color: #005792; text-decoration: none;
        font-weight: 600; font-size: 14px; border-radius: 6px;
        white-space: nowrap; transition: all 0.2s ease;
    }
    .visa-sheet-page .sheet-tab:hover { 
        color: #003a58; 
        background: #cce4f0; 
        text-decoration: none; 
    }
    .visa-sheet-page .sheet-tab.active {
        color: #fff; 
        background: linear-gradient(135deg, #005792 0%, #004670 100%);
        box-shadow: 0 1px 3px rgba(0, 87, 146, 0.3);
    }
    .visa-sheet-page .art-sheet-filter-bar {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
        padding: 10px 20px 12px;
        background: #fff;
        border-bottom: 1px solid #e6f2f7;
    }
    .visa-sheet-page .art-sheet-filter-bar .filter_btn {
        margin: 0;
        background: linear-gradient(135deg, #005792 0%, #004670 100%) !important;
        color: #fff !important;
        border: none !important;
        padding: 8px 16px;
        border-radius: 6px;
        font-weight: 500;
    }
    .visa-sheet-page .art-sheet-filter-bar .filter_btn:hover {
        background: linear-gradient(135deg, #004670 0%, #003a58 100%) !important;
        box-shadow: 0 2px 8px rgba(0, 87, 146, 0.2);
    }
    .visa-sheet-page .art-sheet-filter-bar .clear-filter-btn {
        margin: 0;
        background: #0087c3 !important;
        color: #fff !important;
        border: none !important;
        padding: 8px 16px;
        border-radius: 6px;
        font-weight: 500;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .visa-sheet-page .art-sheet-filter-bar .clear-filter-btn:hover {
        background: #006a9a !important;
        text-decoration: none;
    }
    .visa-sheet-page .active-filters-badge {
        background: #ff6b6b;
        color: white;
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 600;
        margin-left: 4px;
    }
    .visa-sheet-page .comment-cell .sheet-comment-text { 
        max-height: 3.6em; 
        overflow: hidden; 
        text-overflow: ellipsis; 
        white-space: pre-wrap; 
        word-break: break-word; 
    }
    .visa-sheet-page .checklist-status-cell .checklist-status-select { 
        min-width: 140px; 
        max-width: 180px; 
    }
    .visa-sheet-page .reminder-cell { min-width: 120px; }
    .visa-sheet-page .checklist-sent-cell { min-width: 120px; }
    .visa-sheet-page .checklist-not-sent-text { color: #374151; font-weight: 500; }
    .visa-sheet-page .filter_panel { display: none; }
    .visa-sheet-page .filter_panel.show { display: block; }
    .visa-sheet-page .filter_panel label { color: #374151; }
    
    /* Star/Pin Column Styles */
    .visa-sheet-page .pin-cell {
        width: 45px;
        min-width: 45px;
        max-width: 45px;
        text-align: center;
        padding: 8px !important;
    }
    .visa-sheet-page .pin-star {
        font-size: 18px;
        cursor: pointer;
        color: #cbd5e0;
        transition: all 0.2s ease;
    }
    .visa-sheet-page .pin-star:hover {
        color: #f59e0b;
        transform: scale(1.2);
    }
    .visa-sheet-page .pin-star.pinned {
        color: #f59e0b;
        text-shadow: 0 0 8px rgba(245, 158, 11, 0.3);
    }
    .visa-sheet-page .pin-star.pinned:hover {
        color: #cbd5e0;
    }
</style>
@endsection

@section('content')
@php
    $sheetRoute = $config['route'] ?? 'clients.sheets.visa-type';
    $sheetRouteParams = ['visaType' => $visaType];
@endphp
<div class="listing-container visa-sheet-page art-sheet-page">
    <section class="listing-section">
        <div class="listing-section-body">
            <div class="card art-sheet-card">
                <div class="art-sheet-sticky-header">
                    <div class="art-sheet-top-bar">
                        <h4 class="art-sheet-title"><i class="fas fa-clipboard-list"></i> {{ $config['title'] ?? 'Visa Sheet' }}</h4>
                        <a href="{{ route('clients.index') }}" class="btn btn-theme btn-theme-sm">
                            <i class="fas fa-arrow-left"></i> Back to Clients
                        </a>
                    </div>
                    <div class="visa-tabs-row">
                        <span class="visa-tabs-label font-weight-bold" style="font-size: 13px; color: #374151;">Tabs:</span>
                        <div class="sheet-tabs">
                            @foreach(['ongoing' => 'Ongoing', 'lodged' => 'Lodged', 'checklist' => 'Checklist', 'discontinue' => 'Discontinue'] as $t => $label)
                                <a href="{{ route($sheetRoute, array_merge($sheetRouteParams, request()->except('tab'), ['tab' => $t])) }}"
                                   class="sheet-tab {{ $tab === $t ? 'active' : '' }}">
                                    {{ $label }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                    <div class="art-sheet-filter-bar">
                        <button type="button" class="btn btn-theme btn-theme-sm filter_btn">
                            <i class="fas fa-filter"></i> Filters
                            @if($activeFilterCount > 0)
                                <span class="active-filters-badge">{{ $activeFilterCount }}</span>
                            @endif
                        </button>
                        @if($activeFilterCount > 0)
                            <a href="{{ route($sheetRoute, array_merge($sheetRouteParams, ['tab' => $tab, 'clear_filters' => 1])) }}" class="clear-filter-btn">
                                <i class="fas fa-undo"></i> Clear Filters
                            </a>
                        @endif
                        <label class="mb-0 ml-2 mr-2" style="font-size: 13px; color: #374151;">Migration Agent:</label>
                        <select name="assignee" id="visa_assignee" class="form-control" style="max-width: 180px;">
                            <option value="all" {{ request('assignee') === 'all' ? 'selected' : '' }}>All</option>
                            <option value="me" {{ request('assignee') === 'me' ? 'selected' : '' }}>Me</option>
                            @foreach($assignees as $a)
                                <option value="{{ $a->id }}" {{ request('assignee') == $a->id ? 'selected' : '' }}>
                                    {{ trim(($a->first_name ?? '') . ' ' . ($a->last_name ?? '')) ?: '—' }}
                                </option>
                            @endforeach
                        </select>
                        <label class="mb-0 ml-2 mr-1" style="font-size: 13px; color: #374151;">Show:</label>
                        <select name="per_page" id="visa_per_page" class="form-control per-page-select" style="max-width: 100px;">
                            @foreach([10, 25, 50, 100, 200] as $opt)
                                <option value="{{ $opt }}" {{ $perPage == $opt ? 'selected' : '' }}>{{ $opt }}/page</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="filter_panel {{ $activeFilterCount > 0 ? 'show' : '' }}">
                        <form action="{{ route($sheetRoute, $sheetRouteParams) }}" method="get" id="visaFilterForm">
                            <input type="hidden" name="tab" value="{{ $tab }}">
                            <input type="hidden" name="per_page" value="{{ $perPage }}">
                            <input type="hidden" name="assignee" value="{{ request('assignee') }}">
                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <label class="mb-2" style="font-weight: 600; color: #374151;"><i class="fas fa-building"></i> Filter by Branch:</label>
                                    <div class="d-flex flex-wrap gap-3">
                                        @foreach($branches as $b)
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="branch[]" value="{{ $b->id }}" id="branch_{{ $b->id }}"
                                                    {{ in_array($b->id, (array)request('branch', [])) ? 'checked' : '' }}>
                                                <label class="form-check-label" for="branch_{{ $b->id }}" style="cursor: pointer;">{{ $b->office_name }}</label>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-2">
                                    <label>Matter Type</label>
                                    <select name="matter_type" class="form-control">
                                        <option value="">All matter types</option>
                                        @foreach($matterTypes as $val => $lbl)
                                            <option value="{{ $val }}" {{ request('matter_type') == $val ? 'selected' : '' }}>{{ Str::limit($lbl, 40) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label>Current Stage</label>
                                    <select name="current_stage" class="form-control">
                                        <option value="">All stages</option>
                                        @foreach($currentStages as $val => $lbl)
                                            <option value="{{ $val }}" {{ request('current_stage') == $val ? 'selected' : '' }}>{{ $lbl }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label>Visa Expiry From</label>
                                    <input type="text" name="visa_expiry_from" class="form-control datepicker" placeholder="dd/mm/yyyy" value="{{ request('visa_expiry_from') }}" autocomplete="off">
                                </div>
                                <div class="col-md-2">
                                    <label>Visa Expiry To</label>
                                    <input type="text" name="visa_expiry_to" class="form-control datepicker" placeholder="dd/mm/yyyy" value="{{ request('visa_expiry_to') }}" autocomplete="off">
                                </div>
                                <div class="col-md-2">
                                    <label>Deadline From</label>
                                    <input type="text" name="deadline_from" class="form-control datepicker" placeholder="dd/mm/yyyy" value="{{ request('deadline_from') }}" autocomplete="off">
                                </div>
                                <div class="col-md-2">
                                    <label>Deadline To</label>
                                    <input type="text" name="deadline_to" class="form-control datepicker" placeholder="dd/mm/yyyy" value="{{ request('deadline_to') }}" autocomplete="off">
                                </div>
                            </div>
                            <div class="row mt-2">
                                <div class="col-md-4">
                                    <label>Search</label>
                                    <input type="text" name="search" class="form-control" placeholder="Name, CRM Ref, Matter ref..." value="{{ request('search') }}">
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary mr-2">Apply Filters</button>
                                    <a href="{{ route($sheetRoute, array_merge($sheetRouteParams, ['tab' => $tab, 'clear_filters' => 1])) }}" class="btn btn-secondary">Reset</a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card-body">
                    @if($setupRequired ?? false)
                        <div class="alert alert-warning mb-3" role="alert">
                            <i class="fas fa-tools mr-1"></i>
                            <strong>Setup required:</strong> Run <code>php artisan migrate</code> to create <code>{{ $config['reference_table'] ?? 'client_matter_references' }}</code>, <code>{{ $config['checklist_status_column'] ?? 'tr_checklist_status' }}</code> (on client_matters), and <code>{{ $config['reminders_table'] ?? 'matter_reminders' }}</code>. Until then, the 4 tabs and filters are visible for structure review but no data will load.
                        </div>
                    @endif
                    <div class="visa-sheet-scroll-hint px-3 pt-2 mb-2" style="font-size: 13px; color: #64748b;">
                        <i class="fas fa-arrows-alt-h"></i> Scroll horizontally to see all columns.
                    </div>
                    <div class="table-container">
                        <div class="scroll-indicator scroll-indicator-left"></div>
                        <div class="scroll-indicator scroll-indicator-right visible"></div>
                        <div class="table-responsive" id="visa-table-scroll">
                            <table class="table table-bordered table-hover art-table" id="visa-sheet-table">
                                <thead>
                                    <tr>
                                        <th class="pin-cell" title="Click star to pin row to top"><i class="fas fa-star"></i></th>
                                        <th>Matter / Course</th>
                                        @if($tab !== 'checklist')
                                        <th>CRM Ref</th>
                                        @endif
                                        <th>Client Name</th>
                                        <th>DOB</th>
                                        <th>Payment Received</th>
                                        @if($tab !== 'checklist')
                                        <th>Branch</th>
                                        @endif
                                        <th>Migration Agent</th>
                                        <th>Visa Expiry</th>
                                        <th>Deadline</th>
                                        @if($tab !== 'checklist')
                                        <th>Current Stage</th>
                                        @endif
                                        @if($tab === 'discontinue')
                                        <th>Outcome</th>
                                        <th>Decision Note</th>
                                        @endif
                                        <th>Comment</th>
                                        @if($tab === 'checklist')
                                        <th>Status</th>
                                        <th>Checklist sent</th>
                                        <th>Email reminder</th>
                                        <th>SMS reminder</th>
                                        <th>Phone reminder</th>
                                        @endif
                                    </tr>
                                </thead>
                                <tbody>
                                    @if($rows->isEmpty())
                                        <tr>
                                            <td colspan="{{ $tab === 'checklist' ? 15 : ($tab === 'discontinue' ? 15 : 13) }}" class="text-center text-muted py-4">
                                                @if($setupRequired ?? false)
                                                    <i class="fas fa-info-circle"></i> Run migrations to enable data. Add a {{ strtoupper($visaType) }} matter type and assign matters to clients.
                                                @else
                                                    <i class="fas fa-info-circle"></i> No {{ $config['title'] ?? $visaType }} records found for this tab. Add a {{ strtoupper($visaType) }} matter type and assign matters to clients.
                                                @endif
                                            </td>
                                        </tr>
                                    @else
                                        @foreach($rows as $row)
                                            @php
                                                $isLead = !empty($row->is_lead);
                                                if ($isLead) {
                                                    $detailUrl = route('leads.detail', ['id' => $row->client_id]);
                                                    $matterId = '';
                                                    $emailReminderUrl = $detailUrl . '?open_email_reminder=1';
                                                    $smsReminderUrl = $detailUrl . '?open_sms_reminder=1';
                                                } else {
                                                    $encodedId = base64_encode(convert_uuencode($row->client_id));
                                                    $detailUrl = route('clients.detail', ['client_id' => $encodedId, 'client_unique_matter_ref_no' => $row->client_unique_matter_no ?? '']);
                                                    $matterId = $row->matter_internal_id ?? '';
                                                    $emailReminderUrl = $detailUrl . ($matterId ? '?matterId=' . $matterId . '&open_email_reminder=1' : '');
                                                    $smsReminderUrl = $detailUrl . ($matterId ? '?matterId=' . $matterId . '&open_sms_reminder=1' : '');
                                                }
                                            @endphp
                                            <tr style="cursor: pointer;" onclick="window.location.href='{{ $detailUrl }}'">
                                                <td class="pin-cell" onclick="event.stopPropagation();">
                                                    @if(!$isLead)
                                                    <i class="fas fa-star pin-star {{ ($row->is_pinned ?? false) ? 'pinned' : '' }}" 
                                                       data-client-id="{{ $row->client_id }}" 
                                                       data-matter-id="{{ $matterId }}"
                                                       data-visa-type="{{ $visaType }}"
                                                       title="{{ ($row->is_pinned ?? false) ? 'Unpin from top' : 'Pin to top' }}"></i>
                                                    @else
                                                    <span class="text-muted" title="Lead">{{ __('Lead') }}</span>
                                                    @endif
                                                </td>
                                                <td onclick="event.stopPropagation();"><a href="{{ $detailUrl }}" class="art-link">{{ $row->matter_title ?? $row->client_unique_matter_no ?? $row->other_reference ?? '—' }}</a></td>
                                                @if($tab !== 'checklist')
                                                <td onclick="event.stopPropagation();"><a href="{{ $detailUrl }}" class="art-link">{{ $row->crm_ref ?? '—' }}</a></td>
                                                @endif
                                                <td onclick="event.stopPropagation();"><a href="{{ $detailUrl }}" class="art-link">{{ trim(($row->first_name ?? '') . ' ' . ($row->last_name ?? '')) ?: '—' }}</a></td>
                                                <td>{{ $row->dob ? \Carbon\Carbon::parse($row->dob)->format('d/m/Y') : '—' }}</td>
                                                <td>
                                                    @if(($row->total_payment ?? 0) > 0)
                                                        ${{ number_format((float)($row->total_payment ?? 0), 2) }}
                                                    @elseif($row->payment_display_note ?? null)
                                                        {{ $row->payment_display_note }}
                                                    @else
                                                        —
                                                    @endif
                                                </td>
                                                @if($tab !== 'checklist')
                                                <td>{{ $row->branch_name ?? '—' }}</td>
                                                @endif
                                                <td>{{ trim($row->assignee_name ?? '') ?: '—' }}</td>
                                                <td>{{ isset($row->visa_expiry) && $row->visa_expiry && $row->visa_expiry != '0000-00-00' ? \Carbon\Carbon::parse($row->visa_expiry)->format('d/m/Y') : '—' }}</td>
                                                <td>{{ $row->deadline ? \Carbon\Carbon::parse($row->deadline)->format('d/m/Y') : '—' }}</td>
                                                @if($tab !== 'checklist')
                                                <td>{{ $row->application_stage ?? '—' }}</td>
                                                @endif
                                                @if($tab === 'discontinue')
                                                <td>{{ $row->decision_outcome ?? '—' }}</td>
                                                <td class="comment-cell" title="{{ $row->decision_note ?? '' }}">{{ Str::limit($row->decision_note ?? '—', 50) }}</td>
                                                @endif
                                                <td class="art-comments-cell comment-cell" onclick="event.stopPropagation();" title="{{ $row->sheet_comment_text ?? '' }}">
                                                    <span class="sheet-comment-text">{{ Str::limit($row->sheet_comment_text ?? '—', 60) }}</span>
                                                </td>
                                                @if($tab === 'checklist')
                                                <td onclick="event.stopPropagation();" class="checklist-status-cell">
                                                    @if($isLead)
                                                    <span class="badge badge-info">Lead</span>
                                                    @else
                                                    @php
                                                        $currentStatus = $row->tr_checklist_status ?? 'active';
                                                        $statusLabels = ['active' => 'Active', 'convert_to_client' => 'Convert to client', 'discontinue' => 'Discontinue', 'hold' => 'Hold'];
                                                    @endphp
                                                    <select class="form-control form-control-sm checklist-status-select" data-matter-id="{{ $matterId }}" title="Status">
                                                        @foreach($statusLabels as $val => $label)
                                                        <option value="{{ $val }}" {{ $currentStatus === $val ? 'selected' : '' }}>{{ $label }}</option>
                                                        @endforeach
                                                    </select>
                                                    @endif
                                                </td>
                                                <td onclick="event.stopPropagation();" class="checklist-sent-cell">
                                                    @if(!empty($row->checklist_sent_at))
                                                        {{ \Carbon\Carbon::parse($row->checklist_sent_at)->format('d/m/Y') }}
                                                    @else
                                                        <span class="checklist-not-sent-text">Not sent</span>
                                                    @endif
                                                </td>
                                                <td onclick="event.stopPropagation();" class="reminder-cell">
                                                    @if(!empty($row->email_reminder_latest ?? null))
                                                        {{ \Carbon\Carbon::parse($row->email_reminder_latest)->format('d/m/Y') }}@if(($row->email_reminder_count ?? 0) > 0) ({{ $row->email_reminder_count }})@endif
                                                    @else
                                                        —
                                                    @endif
                                                    <br><a href="{{ $emailReminderUrl }}" class="btn btn-sm btn-outline-secondary mt-1 checklist-reminder-link" onclick="event.stopPropagation();" title="Email reminder">Email reminder</a>
                                                </td>
                                                <td onclick="event.stopPropagation();" class="reminder-cell">
                                                    @if(!empty($row->sms_reminder_latest ?? null))
                                                        {{ \Carbon\Carbon::parse($row->sms_reminder_latest)->format('d/m/Y') }}@if(($row->sms_reminder_count ?? 0) > 0) ({{ $row->sms_reminder_count }})@endif
                                                    @else
                                                        —
                                                    @endif
                                                    <br><a href="{{ $smsReminderUrl }}" class="btn btn-sm btn-outline-secondary mt-1 checklist-reminder-link" onclick="event.stopPropagation();" title="SMS reminder">SMS reminder</a>
                                                </td>
                                                <td onclick="event.stopPropagation();" class="reminder-cell">
                                                    @if(!empty($row->phone_reminder_latest ?? null))
                                                        {{ \Carbon\Carbon::parse($row->phone_reminder_latest)->format('d/m/Y') }}@if(($row->phone_reminder_count ?? 0) > 0) ({{ $row->phone_reminder_count }})@endif
                                                    @else
                                                        —
                                                    @endif
                                                    <br><button type="button" class="btn btn-sm btn-outline-secondary mt-1 checklist-phone-reminder-btn" data-matter-id="{{ $matterId }}" onclick="event.stopPropagation();" title="Phone reminder">Phone reminder</button>
                                                </td>
                                                @endif
                                            </tr>
                                        @endforeach
                                    @endif
                                </tbody>
                            </table>
                        </div>
                    </div>

                    @if($rows->hasPages())
                        <div class="card-footer">
                            {!! $rows->appends(array_merge(request()->except('page'), ['tab' => $tab]))->render() !!}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </section>
</div>
@endsection

@push('scripts')
<script>
jQuery(document).ready(function($) {
    var $scroll = $('#visa-table-scroll');
    var $left = $('.scroll-indicator-left');
    var $right = $('.scroll-indicator-right');
    function updateScroll() {
        if (!$scroll.length || !$scroll[0]) return;
        var sl = $scroll.scrollLeft();
        var sw = $scroll[0].scrollWidth;
        var cw = $scroll[0].clientWidth;
        $left.toggleClass('visible', sl > 10);
        $right.toggleClass('visible', sl < sw - cw - 10);
    }
    $scroll.on('scroll resize', updateScroll);
    setTimeout(updateScroll, 100);
    $scroll.on('wheel', function(e) {
        if (e.originalEvent.deltaY && !e.shiftKey && this.scrollWidth > this.clientWidth) {
            e.preventDefault();
            this.scrollLeft += e.originalEvent.deltaY;
        }
    });
    $('#visa_per_page').on('change', function() {
        var u = new URL(window.location.href);
        u.searchParams.set('per_page', $(this).val());
        u.searchParams.delete('page');
        window.location.href = u.toString();
    });
    $('#visa_assignee').on('change', function() {
        var u = new URL(window.location.href);
        u.searchParams.set('assignee', $(this).val());
        u.searchParams.delete('page');
        window.location.href = u.toString();
    });
    $('.filter_btn').on('click', function() {
        $('.filter_panel').toggleClass('show');
    });
    if (typeof flatpickr !== 'undefined') {
        $('.datepicker').each(function() {
            if (!$(this).data('flatpickr')) {
                flatpickr(this, { dateFormat: 'd/m/Y', allowInput: true, locale: { firstDayOfWeek: 1 } });
            }
        });
    }

    // Handle star/pin clicks - use capture phase (true) so we run before td's stopPropagation blocks bubble
    var visaTable = document.getElementById('visa-sheet-table');
    if (visaTable) {
        visaTable.addEventListener('click', function(e) {
        var star = e.target.closest('.pin-star');
        if (!star) return;
        
        e.preventDefault();
        e.stopPropagation();
        
        var $star = $(star);
        var clientId = $star.data('client-id');
        var matterId = $star.data('matter-id');
        var visaType = $star.data('visa-type');
        
        if (!clientId || !matterId) {
            if (typeof iziToast !== 'undefined') {
                iziToast.warning({ title: 'Error', message: 'Cannot pin: missing data', position: 'topRight' });
            }
            return;
        }
        
        // Disable clicking during request
        $star.css('pointer-events', 'none');
        
        $.ajax({
            url: '{{ url("/clients/sheets") }}/' + visaType + '/toggle-pin',
            method: 'POST',
            data: {
                client_id: clientId,
                matter_internal_id: matterId,
                _token: '{{ csrf_token() }}'
            },
            success: function(response) {
                if (response.success) {
                    // Toggle star appearance
                    $star.toggleClass('pinned');
                    $star.attr('title', response.is_pinned ? 'Unpin from top' : 'Pin to top');
                    
                    // Show success message
                    if (typeof iziToast !== 'undefined') {
                        iziToast.success({
                            title: 'Success',
                            message: response.message,
                            position: 'topRight',
                            timeout: 2000
                        });
                    }
                    
                    // Reload page after short delay to show updated order
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
    }, true); // capture phase
    }
});
</script>
@endpush
