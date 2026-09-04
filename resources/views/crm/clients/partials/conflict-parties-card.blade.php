@php
    $partyRoles = [
        'opposing_party' => 'Opposing Party',
        'respondent' => 'Respondent',
        'co_respondent' => 'Co-respondent',
        'defendant' => 'Defendant',
        'plaintiff' => 'Plaintiff',
        'witness' => 'Witness',
        'insurer' => 'Insurer',
        'guarantor' => 'Guarantor',
        'creditor' => 'Creditor',
        'director' => 'Director / Officer',
        'shareholder' => 'Shareholder',
        'related_entity' => 'Related Entity',
        'other' => 'Other',
    ];
    $outcomeLabels = [
        'pending' => 'Pending',
        'clear' => 'Clear',
        'conflict_found' => 'Conflict found',
        'waived' => 'Waived with consent',
    ];
    $outcomeBadgeColors = [
        'pending' => '#e37400',
        'clear' => '#188038',
        'conflict_found' => '#c5221f',
        'waived' => '#8e24aa',
    ];
    $initialParties = $conflictParties->map(function ($p) {
        if (($p->party_type ?? 'individual') === 'company') {
            $name = trim($p->company_name ?? '') ?: 'Unnamed company';
        } else {
            $name = trim(($p->first_name ?? '') . ' ' . ($p->last_name ?? '')) ?: 'Unnamed';
        }

        return [
            'opposing_lead_id' => $p->opposing_lead_id ?? null,
            'name' => $name,
            'party_role' => $p->party_role,
            'rep_firm' => $p->rep_firm_name,
            'rep_name' => $p->rep_name,
            'rep_email' => $p->rep_email,
            'rep_phone' => $p->rep_phone,
            'rep_notes' => $p->notes,
        ];
    })->values()->all();
    $latestOutcome = $latestConflictCheck?->outcome;
    $latestOutcomeLabel = $latestOutcome ? ($outcomeLabels[$latestOutcome] ?? ucfirst($latestOutcome)) : null;
    $latestCheckedAt = ($latestConflictCheck && $latestConflictCheck->checked_at)
        ? $latestConflictCheck->checked_at->format('d M Y H:i')
        : null;
    $conflictCheckHistory = $conflictCheckHistory ?? collect();
    $conflictCheckStaleness = $conflictCheckStaleness ?? ['is_stale' => false, 'reason' => null];
    $partiesUpdatedAtIso = isset($partiesUpdatedAt) && $partiesUpdatedAt
        ? $partiesUpdatedAt->toIso8601String()
        : '';
@endphp

<style>
    #conflictPartiesCard .cp-card-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        margin-bottom: 12px;
        padding-bottom: 10px;
        border-bottom: 1px solid #e9ecef;
    }
    #conflictPartiesCard .cp-card-header h3 { margin: 0; font-size: 1.05rem; font-weight: 600; flex: 1; }
    #conflictPartiesCard .cp-outcome-badge,
    #conflictPartiesEditModal .cp-outcome-badge {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 4px;
        font-size: 12px;
        font-weight: 600;
        color: #fff;
    }
    #conflictPartiesEditModal .cp-match-list {
        max-height: 220px;
        overflow-y: auto;
        border: 1px solid #e9ecef;
        border-radius: 6px;
        background: #fafbfc;
        padding: 8px;
        margin-bottom: 10px;
    }
    #conflictPartiesEditModal .cp-match-item {
        padding: 6px 0;
        border-bottom: 1px solid #eee;
        font-size: 12px;
    }
    #conflictPartiesEditModal .cp-match-item:last-child { border-bottom: 0; }
    #conflictPartiesEditModal .cp-match-name { font-weight: 600; color: #212529; }
    #conflictPartiesEditModal .cp-match-meta { color: #666; }
    #conflictPartiesEditModal .cp-match-item.cp-match-informational {
        background: #f4f6f8;
        border-radius: 4px;
        padding: 6px 8px;
        margin-bottom: 4px;
        border-bottom: 0;
    }
    #conflictPartiesEditModal .cp-match-item.cp-match-informational .cp-match-name { color: #5f6368; font-weight: 500; }
    #conflictPartiesEditModal .cp-match-item.cp-match-informational .cp-match-meta { color: #80868b; }
    #conflictPartiesEditModal .cp-info-badge {
        display: inline-block;
        font-size: 11px;
        font-weight: 600;
        color: #5f6368;
        background: #e8eaed;
        border-radius: 3px;
        padding: 1px 6px;
        margin-left: 6px;
    }
    #conflictPartiesEditModal .cp-info-panel {
        margin-bottom: 10px;
        padding: 8px;
        border: 1px dashed #dadce0;
        border-radius: 6px;
        background: #fafafa;
    }
    #conflictPartiesEditModal .cp-info-panel-title {
        font-size: 12px;
        font-weight: 600;
        color: #5f6368;
        margin-bottom: 6px;
    }
    #conflictPartiesEditModal .cp-history-list { font-size: 12px; color: #555; margin-top: 8px; }
    #conflictPartiesEditModal .cp-history-item { padding: 3px 0; }
    #conflictPartiesEditModal .cp-history-row {
        cursor: pointer;
        border-radius: 4px;
        padding: 4px 6px;
        margin: 0 -6px;
    }
    #conflictPartiesEditModal .cp-history-row:hover { background: #f1f3f4; }
    #conflictPartiesEditModal .cp-force-clear-panel {
        border: 1px solid #f5c6cb;
        background: #fff5f5;
        border-radius: 6px;
        padding: 8px 10px;
        margin-bottom: 10px;
        font-size: 12px;
    }
    #conflictPartiesEditModal .cp-force-clear-panel summary {
        cursor: pointer;
        font-weight: 600;
        color: #c5221f;
    }
    #conflictPartiesEditModal .cp-stale-hint {
        font-size: 12px;
        color: #856404;
        background: #fff3cd;
        border: 1px solid #ffeeba;
        border-radius: 6px;
        padding: 6px 8px;
        margin-bottom: 8px;
    }
    #conflictPartiesEditModal .cp-access-locked {
        color: #856404;
        font-size: 11px;
        margin-left: 4px;
    }
    #cpHistoryDetailModal .modal-body { font-size: 13px; }
    #conflictPartiesEditModal .cp-modal-section {
        margin-bottom: 8px;
    }
    #conflictPartiesEditModal .cp-modal-section--outcome {
        margin-top: 20px;
        padding-top: 18px;
        border-top: 1px solid #e9ecef;
    }
    #conflictPartiesEditModal .cp-modal-section__title {
        margin: 0 0 4px;
        font-size: 1rem;
        font-weight: 700;
        color: #1f2937;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    #conflictPartiesEditModal .cp-modal-section__head {
        margin-bottom: 12px;
    }
    #conflictPartiesEditModal #cpPartiesContainer {
        margin-bottom: 8px;
        min-width: 0;
        overflow: visible;
    }
    #conflictPartiesEditModal .opp-party-row {
        min-width: 0;
        overflow: visible;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 14px 16px;
        margin-bottom: 10px;
    }
</style>
<link rel="stylesheet" href="{{ asset('css/crm/other-party-picker.css') }}?v={{ @filemtime(public_path('css/crm/other-party-picker.css')) ?: time() }}">

<div class="card" id="conflictPartiesCard"
     data-client-id="{{ $fetchedData->id }}"
     data-client-matter-id="{{ $activeClientMatterId ?? '' }}"
     data-parties-updated-at="{{ $partiesUpdatedAtIso }}">
    <div id="conflictPartiesView" class="cp-view" role="region" aria-label="Other parties summary">
        <div class="cp-card-header">
            <h3><i class="fa-solid fa-user-shield"></i> Other Parties &amp; Conflict Check</h3>
            <button type="button"
                    class="btn btn-primary btn-sm cp-open-edit cp-open-edit--icon"
                    title="Edit other parties"
                    aria-label="Edit other parties"
                    aria-expanded="false"
                    aria-controls="conflictPartiesEditModal">
                <i class="fa-solid fa-pen" aria-hidden="true"></i>
            </button>
        </div>

        @if(! empty($activeClientMatterId))
        <p class="text-muted mb-2" style="font-size:13px;">
            Other parties for the active matter only. Each matter can have its own defendants.
        </p>
        @endif

        <div class="field-group">
            <span class="field-label">Recorded parties</span>
            <span class="field-value" id="cpPartiesCountDisplay">{{ $conflictParties->count() }}</span>
        </div>

        <div id="cpPartiesSummaryList">
            @forelse($conflictParties as $party)
                @php
                    $roleLabel = $party->party_role ? ($partyRoles[$party->party_role] ?? $party->party_role) : '—';
                    if (($party->party_type ?? 'individual') === 'company') {
                        $displayName = trim($party->company_name ?? '') ?: 'Unnamed company';
                    } else {
                        $displayName = trim(($party->first_name ?? '') . ' ' . ($party->last_name ?? '')) ?: 'Unnamed';
                    }
                @endphp
                <div class="field-group cp-summary-party">
                    <span class="field-value" style="font-weight:600;">{{ $displayName }}</span>
                    <span class="field-value" style="font-size:12px;color:#666;">{{ $roleLabel }}</span>
                </div>
            @empty
                <p class="text-muted mb-2" style="font-size:13px;">No other parties recorded yet.</p>
            @endforelse
        </div>

        <div class="field-group" style="margin-top:10px;padding-top:10px;border-top:1px solid #e9ecef;">
            <span class="field-label">Conflict check</span>
            <span class="field-value" id="cpOutcomeSummaryDisplay">
                @if($latestCheckedAt && $latestOutcomeLabel)
                    {{ $latestCheckedAt }} —
                    <span class="cp-outcome-badge" style="background:{{ $outcomeBadgeColors[$latestOutcome] ?? '#555' }};">{{ $latestOutcomeLabel }}</span>
                @else
                    Not checked yet
                @endif
            </span>
        </div>
    </div>
</div>

{{-- Large editor modal (opened from pencil) --}}
<div class="modal fade custom_modal" id="conflictPartiesEditModal" tabindex="-1" role="dialog"
     aria-labelledby="conflictPartiesEditModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="conflictPartiesEditModalTitle">Other parties &amp; conflict check</h5>
                <button type="button" class="crm-modal-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="conflictPartiesEdit">
                <div class="cp-modal-section" id="cpOtherPartiesSection">
                    <div class="cp-modal-section__head">
                        <h6 class="cp-modal-section__title">Other parties</h6>
                        <p class="text-muted small mb-0">Search and link parties for this matter. Save before running a conflict check.</p>
                    </div>

                    <div class="d-flex flex-wrap gap-2 mb-3 align-items-center">
                        <button type="button" class="btn btn-outline-primary btn-sm" id="cpCreateOtherPartyBtn">
                            <i class="fa-solid fa-user-plus"></i> New other party
                        </button>
                        <a href="{{ route('leads.create', ['other_party' => 1]) }}" target="_blank" rel="noopener" class="small">Full create form</a>
                    </div>

                    <div id="cpMiniCreateOtherParty" class="cp-mini-create" style="display:none;">
                        <p class="small fw-semibold mb-1">Quick create other party</p>
                        <p class="text-muted small mb-2">First &amp; last name required. Phone or email required.</p>
                        <div class="row g-2">
                            <div class="col-12 col-md-3"><input type="text" class="form-control" id="cpMiniOpFirst" placeholder="First name *" autocomplete="off"></div>
                            <div class="col-12 col-md-3"><input type="text" class="form-control" id="cpMiniOpLast" placeholder="Last name *" autocomplete="off"></div>
                            <div class="col-12 col-md-3"><input type="text" class="form-control" id="cpMiniOpPhone" placeholder="Phone" autocomplete="off"></div>
                            <div class="col-12 col-md-3"><input type="email" class="form-control" id="cpMiniOpEmail" placeholder="Email" autocomplete="off"></div>
                        </div>
                        <div class="mt-2 d-flex gap-2 flex-wrap">
                            <button type="button" class="btn btn-sm btn-primary" id="cpMiniOpSave">Save &amp; select</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="cpMiniOpCancel">Cancel</button>
                        </div>
                        <div id="cpMiniOpMessage" class="mt-2" style="display:none;"></div>
                    </div>

                    <div id="cpPartiesContainer" class="mb-3"></div>

                    <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-1">
                        <button type="button" class="btn btn-outline-primary btn-sm" id="cpAddPartyBtn">
                            <i class="fa-solid fa-plus"></i> Add party
                        </button>
                        <button type="button" class="btn btn-primary btn-sm" id="cpSavePartiesBtn">Save parties</button>
                    </div>
                </div>

                <div class="cp-modal-section cp-modal-section--outcome" id="cpOutcomeSection">
                    <div class="cp-modal-section__head">
                        <h6 class="cp-modal-section__title"><i class="fa-solid fa-clipboard-check"></i> Conflict check</h6>
                        <p class="text-muted small mb-0" id="cpLastCheckHint">
                            @if($latestCheckedAt && $latestOutcomeLabel)
                                Last check: {{ $latestCheckedAt }} — {{ $latestOutcomeLabel }}
                            @else
                                Last check: Not checked yet
                            @endif
                        </p>
                    </div>

                    <div class="cp-stale-hint" id="cpStaleHint" @if(empty($conflictCheckStaleness['is_stale'])) style="display:none;" @endif>
                        {{ $conflictCheckStaleness['reason'] ?? 'Parties were updated after the last Clear/Waived check. Re-run the conflict search before saving a new outcome.' }}
                    </div>

                    <div class="d-flex gap-2 align-items-center mb-2 flex-wrap">
                        <button type="button" class="btn btn-outline-success btn-sm" id="cpRunCheckBtn">
                            <i class="fa-solid fa-magnifying-glass"></i> Run conflict check
                        </button>
                        <span class="text-muted small" id="cpRunCheckStatus"></span>
                    </div>
                    <p class="text-muted small mb-2">
                        Searches saved client details and saved other parties. If you added parties above, click <strong>Save parties</strong> first.
                    </p>
                    <div id="cpRunCheckWarnings" class="small text-warning mb-2" style="display:none;"></div>

                    <div id="cpMatchesPanel" style="display:none;">
                        <div class="small fw-semibold mb-1" id="cpMatchesHeading">Matches</div>
                        <div class="cp-match-list" id="cpMatchesList"></div>
                        <div id="cpInformationalPanel" class="cp-info-panel" style="display:none;">
                            <div class="cp-info-panel-title"><i class="fa-solid fa-circle-info"></i> Same other-party elsewhere</div>
                            <div class="cp-match-list" id="cpInformationalList"></div>
                        </div>
                    </div>

                    <details class="cp-force-clear-panel" id="cpForceClearPanel" style="display:none;">
                        <summary>Override — clear despite matches</summary>
                        <div class="mt-2">
                            <label class="d-flex align-items-start gap-2 mb-1">
                                <input type="checkbox" id="cpForceClear" value="1">
                                <span>I have reviewed the listed matches and document why Clear is appropriate despite potential conflicts.</span>
                            </label>
                            <p class="text-muted small mb-0">Requires detailed notes (at least 20 characters) in the Notes field.</p>
                        </div>
                    </details>

                    <div class="row">
                        <div class="col-12 col-md-6">
                            <div class="form-group mb-2">
                                <label for="cpOutcomeSelect">Outcome</label>
                                <select id="cpOutcomeSelect" class="form-control">
                                    @foreach($outcomeLabels as $val => $lbl)
                                        <option value="{{ $val }}" @selected($latestOutcome === $val)>{{ $lbl }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <div class="form-group mb-2">
                                <label for="cpConsentNotes">Consent notes</label>
                                <input type="text" id="cpConsentNotes" class="form-control"
                                       placeholder="Who gave consent, form used, etc."
                                       value="{{ $latestConflictCheck?->consent_notes ?? '' }}">
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-group mb-2">
                                <label for="cpOutcomeNotes">Notes</label>
                                <textarea id="cpOutcomeNotes" class="form-control" rows="3">{{ $latestConflictCheck?->outcome_notes ?? '' }}</textarea>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-group mb-2">
                                <label class="d-flex align-items-center gap-2 mb-0">
                                    <input type="checkbox" id="cpConsentObtained" @checked($latestConflictCheck?->consent_obtained ?? false)> Consent obtained
                                </label>
                            </div>
                        </div>
                    </div>

                    <button type="button" class="btn btn-success btn-sm" id="cpSaveOutcomeBtn">Save outcome</button>

                    @if($conflictCheckHistory->isNotEmpty())
                        <div class="cp-history-list" id="cpHistoryList">
                            <div class="fw-semibold mb-1" style="margin-top:12px;">Recent checks</div>
                            @foreach($conflictCheckHistory as $hist)
                                @php
                                    $hLabel = $outcomeLabels[$hist->outcome] ?? $hist->outcome;
                                    $hAt = $hist->checked_at ? $hist->checked_at->format('d M Y H:i') : '—';
                                    $hMatches = (int) ($hist->match_count ?? (is_array($hist->matches) ? count($hist->matches) : 0));
                                    $hInfo = (int) ($hist->informational_count ?? 0);
                                    $hMatter = $hist->clientMatter?->client_unique_matter_no;
                                @endphp
                                <div class="cp-history-item cp-history-row" role="button" tabindex="0" data-check-id="{{ $hist->id }}">
                                    {{ $hAt }} —
                                    <span class="cp-outcome-badge" style="background:{{ $outcomeBadgeColors[$hist->outcome] ?? '#555' }};">{{ $hLabel }}</span>
                                    @if($hMatter)
                                        <span class="text-muted">· {{ $hMatter }}</span>
                                    @endif
                                    @if($hMatches > 0)
                                        <span class="text-muted">({{ $hMatches }} conflict{{ $hMatches === 1 ? '' : 's' }})</span>
                                    @elseif($hInfo > 0)
                                        <span class="text-muted">(0 conflicts · {{ $hInfo }} informational)</span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="cp-history-list" id="cpHistoryList" style="display:none;"></div>
                    @endif
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

@include('crm.clients.partials.conflict-check-history-detail')

@push('scripts')
<script>
(function () {
    var card = document.getElementById('conflictPartiesCard');
    if (!card) return;

    var viewEl = document.getElementById('conflictPartiesView');
    var editModal = document.getElementById('conflictPartiesEditModal');
    var editEl = document.getElementById('conflictPartiesEdit');
    var container = document.getElementById('cpPartiesContainer');
    var openBtns = card.querySelectorAll('.cp-open-edit');
    var savePartiesBtn = document.getElementById('cpSavePartiesBtn');
    var addPartyBtn = document.getElementById('cpAddPartyBtn');
    var saveOutcomeBtn = document.getElementById('cpSaveOutcomeBtn');
    var runCheckBtn = document.getElementById('cpRunCheckBtn');
    var editorOpen = false;

    var partyRoles = @json($partyRoles);
    var initialParties = @json($initialParties);
    var outcomeLabels = @json($outcomeLabels);
    var outcomeBadgeColors = @json($outcomeBadgeColors);
    var searchUrl = window.OTHER_PARTY_SEARCH_URL || @json(route('api.search.other.party'));
    var solicitorSearchUrl = window.CONTACT_PERSON_SEARCH_URL || @json(route('api.search.contact.person'));
    var runCheckUrl = @json(route('clients.conflictCheck.run'));
    var conflictCheckDetailUrl = @json(route('clients.conflictCheck.detail', ['checkId' => '__ID__']));
    var clientId = card.getAttribute('data-client-id');
    var clientMatterId = card.getAttribute('data-client-matter-id') || '';
    var latestOutcome = @json($latestOutcome);
    var lastSearchTerms = null;
    var lastSearchHash = null;
    var lastMatches = null;
    var lastInformationalMatches = null;
    var lastHardMatchCount = 0;
    var searchWasRun = false;
    var conflictCheckIsStale = @json(! empty($conflictCheckStaleness['is_stale']));

    function showStaleHint(message) {
        var stale = document.getElementById('cpStaleHint');
        if (!stale) return;
        if (message) stale.textContent = message;
        stale.style.display = 'block';
        conflictCheckIsStale = true;
        updateOutcomeSaveState();
    }

    function hideStaleHint() {
        var stale = document.getElementById('cpStaleHint');
        if (stale) stale.style.display = 'none';
        conflictCheckIsStale = false;
        updateOutcomeSaveState();
    }

    function updateOutcomeSaveState() {
        if (!saveOutcomeBtn) return;
        var outcome = (document.getElementById('cpOutcomeSelect') || {}).value || 'pending';
        var blockClearWaived = conflictCheckIsStale
            && !searchWasRun
            && (outcome === 'clear' || outcome === 'waived');
        saveOutcomeBtn.disabled = blockClearWaived;
        saveOutcomeBtn.title = blockClearWaived
            ? 'Run conflict search again after party or client detail changes before saving Clear or Waived.'
            : '';
    }

    function toast(msg, ok) {
        if (typeof iziToast !== 'undefined' && iziToast.show) {
            iziToast.show({ message: msg, color: ok ? 'green' : 'red', position: 'topRight', timeout: 4000 });
        } else { crmAlert(msg); }
    }

    function esc(s) {
        if (s == null) return '';
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/"/g,'&quot;');
    }

    function addPartyRow(data) {
        if (!window.OtherPartyPicker) {
            toast('Other party picker is not loaded.', false);
            return null;
        }
        return window.OtherPartyPicker.appendRow(container, {
            rowClass: 'cp-party-row opp-party-row',
            searchUrl: searchUrl,
            solicitorSearchUrl: solicitorSearchUrl,
            excludeId: clientId,
            roles: partyRoles,
            data: data || {}
        });
    }

    function selectPartyOnRow(row, party) {
        if (!row || !party || !window.OtherPartyPicker) return;
        window.OtherPartyPicker.selectParty(row, party);
    }

    function saveMiniOtherParty() {
        var storeUrl = window.STORE_OTHER_PARTY_MINI_URL;
        var msgBox = document.getElementById('cpMiniOpMessage');
        var fn = document.getElementById('cpMiniOpFirst');
        var ln = document.getElementById('cpMiniOpLast');
        var ph = document.getElementById('cpMiniOpPhone');
        var em = document.getElementById('cpMiniOpEmail');
        if (!storeUrl || !fn || !ln) {
            toast('Other party create is not configured.', false);
            return;
        }
        if (!fn.value.trim() || !ln.value.trim()) {
            if (msgBox) {
                msgBox.style.display = 'block';
                msgBox.className = 'mt-2 alert alert-warning';
                msgBox.textContent = 'First and last name are required.';
            }
            return;
        }
        if (!(ph && ph.value.trim()) && !(em && em.value.trim())) {
            if (msgBox) {
                msgBox.style.display = 'block';
                msgBox.className = 'mt-2 alert alert-warning';
                msgBox.textContent = 'Phone or email is required.';
            }
            return;
        }
        var token = document.querySelector('meta[name="csrf-token"]');
        var fd = new FormData();
        fd.append('_token', token ? token.getAttribute('content') : '');
        fd.append('first_name', fn.value.trim());
        fd.append('last_name', ln.value.trim());
        if (ph && ph.value.trim()) fd.append('phone', ph.value.trim());
        if (em && em.value.trim()) fd.append('email', em.value.trim());
        var saveBtn = document.getElementById('cpMiniOpSave');
        if (saveBtn) saveBtn.disabled = true;
        fetch(storeUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: fd,
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (r) {
                return r.text().then(function (text) {
                    try { return { ok: r.ok, data: text ? JSON.parse(text) : {} }; }
                    catch (e) { return { ok: false, data: { message: 'Invalid server response' } }; }
                });
            })
            .then(function (res) {
                if (saveBtn) saveBtn.disabled = false;
                if (res.ok && res.data.success && res.data.party) {
                    var targetRow = container && container.querySelector('.cp-party-row:last-child');
                    if (!targetRow || (targetRow.querySelector('.opp-party-lead-id') || {}).value) {
                        targetRow = addPartyRow({});
                    }
                    selectPartyOnRow(targetRow, {
                        id: res.data.party.id,
                        text: res.data.party.text,
                        first_name: res.data.party.first_name,
                        last_name: res.data.party.last_name,
                        email: res.data.party.email,
                        phone: res.data.party.phone,
                        client_id: res.data.party.client_id
                    });
                    fn.value = '';
                    ln.value = '';
                    if (ph) ph.value = '';
                    if (em) em.value = '';
                    var box = document.getElementById('cpMiniCreateOtherParty');
                    if (box) box.style.display = 'none';
                    if (msgBox) msgBox.style.display = 'none';
                    toast('Other party created and selected.', true);
                } else {
                    var err = (res.data && res.data.message) || 'Could not create other party.';
                    if (msgBox) {
                        msgBox.style.display = 'block';
                        msgBox.className = 'mt-2 alert alert-danger';
                        msgBox.textContent = err;
                    } else {
                        toast(err, false);
                    }
                }
            })
            .catch(function () {
                if (saveBtn) saveBtn.disabled = false;
                toast('Network error while creating other party.', false);
            });
    }

    function destroyEditorRows() {
        if (!container) return;
        container.querySelectorAll('.opp-party-lead-select').forEach(function (sel) {
            if (typeof window.destroyTS === 'function') window.destroyTS(sel);
        });
        container.innerHTML = '';
    }

    function initEditorRows() {
        if (!container) return;
        destroyEditorRows();
        if (initialParties.length) {
            initialParties.forEach(function (p) { addPartyRow(p); });
        } else {
            addPartyRow();
        }
    }

    function prepareConflictPartiesModalShell() {
        if (!editModal) return null;
        if (editModal.parentElement !== document.body) {
            document.body.appendChild(editModal);
        }
        return editModal;
    }

    function setEditMode(open) {
        if (!editModal) return;
        if (open) {
            prepareConflictPartiesModalShell();
            editorOpen = true;
            openBtns.forEach(function (b) { b.setAttribute('aria-expanded', 'true'); });
            initEditorRows();
            if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                bootstrap.Modal.getOrCreateInstance(editModal, { focus: false }).show();
            } else if (window.jQuery) {
                window.jQuery(editModal).modal('show');
            } else {
                editModal.style.display = 'block';
                editModal.classList.add('show');
                document.body.classList.add('modal-open');
            }
        } else {
            if (!editorOpen) return;
            editorOpen = false;
            destroyEditorRows();
            openBtns.forEach(function (b) { b.setAttribute('aria-expanded', 'false'); });
            if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                var inst = bootstrap.Modal.getInstance(editModal);
                if (inst) inst.hide();
            } else if (window.jQuery) {
                window.jQuery(editModal).modal('hide');
            } else {
                editModal.style.display = 'none';
                editModal.classList.remove('show');
                document.body.classList.remove('modal-open');
            }
        }
    }

    openBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            setEditMode(true);
        });
    });

    if (editModal) {
        editModal.addEventListener('hidden.bs.modal', function () {
            editorOpen = false;
            destroyEditorRows();
            openBtns.forEach(function (b) { b.setAttribute('aria-expanded', 'false'); });
        });
    }

    if (addPartyBtn) {
        addPartyBtn.addEventListener('click', function () { addPartyRow(); });
    }

    var createOtherPartyBtn = document.getElementById('cpCreateOtherPartyBtn');
    var miniCreateBox = document.getElementById('cpMiniCreateOtherParty');
    if (createOtherPartyBtn && miniCreateBox) {
        createOtherPartyBtn.addEventListener('click', function () {
            miniCreateBox.style.display = miniCreateBox.style.display === 'none' ? 'block' : 'none';
        });
    }
    var miniCancelBtn = document.getElementById('cpMiniOpCancel');
    if (miniCancelBtn && miniCreateBox) {
        miniCancelBtn.addEventListener('click', function () {
            miniCreateBox.style.display = 'none';
        });
    }
    var miniSaveBtn = document.getElementById('cpMiniOpSave');
    if (miniSaveBtn) {
        miniSaveBtn.addEventListener('click', saveMiniOtherParty);
    }

    function collectParties() {
        if (!window.OtherPartyPicker) return [];
        return window.OtherPartyPicker.collectRows(container);
    }

    function refreshViewSummary(parties, count) {
        var countEl = document.getElementById('cpPartiesCountDisplay');
        if (countEl) countEl.textContent = String(count != null ? count : parties.length);
        var list = document.getElementById('cpPartiesSummaryList');
        if (!list) return;
        list.innerHTML = '';
        if (!parties.length) {
            list.innerHTML = '<p class="text-muted mb-2" style="font-size:13px;">No other parties recorded yet.</p>';
            return;
        }
        parties.forEach(function (p) {
            var roleLabel = p.party_role && partyRoles[p.party_role] ? partyRoles[p.party_role] : (p.party_role || '—');
            var name = (p.name || '').trim() || 'Unnamed';
            var div = document.createElement('div');
            div.className = 'field-group cp-summary-party';
            div.innerHTML = '<span class="field-value" style="font-weight:600;">' + esc(name) + '</span>' +
                '<span class="field-value" style="font-size:12px;color:#666;">' + esc(roleLabel) + '</span>';
            list.appendChild(div);
        });
    }

    function updateOutcomeDisplay(outcome, checkedAt) {
        var el = document.getElementById('cpOutcomeSummaryDisplay');
        var hint = document.getElementById('cpLastCheckHint');
        var label = outcomeLabels[outcome] || outcome;
        var color = outcomeBadgeColors[outcome] || '#555';
        if (el) {
            el.innerHTML = esc(checkedAt) + ' — <span class="cp-outcome-badge" style="background:' + color + ';">' + esc(label) + '</span>';
        }
        if (hint) hint.textContent = 'Last check: ' + checkedAt + ' — ' + label;
        latestOutcome = outcome;
        hideStaleHint();
        searchWasRun = false;
        lastSearchHash = null;
        updateOutcomeSaveState();
    }

    function updateForceClearPanel(hardCount) {
        lastHardMatchCount = hardCount || 0;
        var panel = document.getElementById('cpForceClearPanel');
        var checkbox = document.getElementById('cpForceClear');
        if (!panel) return;
        if (lastHardMatchCount > 0) {
            panel.style.display = 'block';
        } else {
            panel.style.display = 'none';
            if (checkbox) checkbox.checked = false;
        }
    }

    function prependHistory(outcome, checkedAt, matchCount, informationalCount, matterLabel, checkId) {
        var list = document.getElementById('cpHistoryList');
        if (!list) return;
        list.style.display = '';
        var label = outcomeLabels[outcome] || outcome;
        var color = outcomeBadgeColors[outcome] || '#555';
        var item = document.createElement('div');
        item.className = 'cp-history-item cp-history-row';
        item.setAttribute('role', 'button');
        item.setAttribute('tabindex', '0');
        if (checkId) {
            item.setAttribute('data-check-id', String(checkId));
        }
        var matterPart = matterLabel ? ' <span class="text-muted">· ' + esc(matterLabel) + '</span>' : '';
        var countPart = '';
        if ((matchCount || 0) > 0) {
            countPart = ' <span class="text-muted">(' + matchCount + ' conflict' + (matchCount === 1 ? '' : 's') + ')</span>';
        } else if ((informationalCount || 0) > 0) {
            countPart = ' <span class="text-muted">(0 conflicts · ' + informationalCount + ' informational)</span>';
        }
        item.innerHTML = esc(checkedAt) + ' — <span class="cp-outcome-badge" style="background:' + color + ';">' + esc(label) + '</span>'
            + matterPart + countPart;
        var title = list.querySelector('.fw-semibold');
        if (!title) {
            title = document.createElement('div');
            title.className = 'fw-semibold mb-1';
            title.style.marginTop = '10px';
            title.textContent = 'Recent checks';
            list.appendChild(title);
        }
        if (title.nextSibling) {
            list.insertBefore(item, title.nextSibling);
        } else {
            list.appendChild(item);
        }
        bindHistoryRow(item);
    }

    function renderMatchRow(m, opts) {
        opts = opts || {};
        var informational = opts.informational || m.severity === 'informational' || m.is_known_party;
        var div = document.createElement('div');
        div.className = 'cp-match-item' + (informational ? ' cp-match-informational' : '');
        var link = '';
        if (!informational) {
            if (m.detail_url) {
                link = ' <a href="' + esc(m.detail_url) + '" target="_blank" rel="noopener">Open</a>';
            } else if (m.access_locked) {
                link = ' <span class="cp-access-locked" title="You do not have access to this record">'
                    + '<i class="fa-solid fa-lock"></i> No access to this record</span>';
            }
        }
        var reason = m.informational_reason
            ? '<div class="cp-match-meta"><i class="fa-solid fa-circle-info"></i> ' + esc(m.informational_reason) + '</div>'
            : '';
        var badge = informational ? '<span class="cp-info-badge">Informational</span>' : '';
        div.innerHTML =
            '<div class="cp-match-name">' + esc(m.name || 'Unknown') + badge + link + '</div>' +
            '<div class="cp-match-meta">' + esc(m.context || '') +
            (m.matched_on ? ' · Matched on ' + esc(m.matched_on) : '') +
            (m.party_role ? ' · ' + esc(m.party_role) : '') +
            '</div>' +
            reason;
        return div;
    }

    function renderMatches(matches, informationalMatches) {
        var panel = document.getElementById('cpMatchesPanel');
        var list = document.getElementById('cpMatchesList');
        var heading = document.getElementById('cpMatchesHeading');
        var infoPanel = document.getElementById('cpInformationalPanel');
        var infoList = document.getElementById('cpInformationalList');
        if (!panel || !list) return;

        matches = matches || [];
        informationalMatches = informationalMatches || [];

        list.innerHTML = '';
        if (infoList) infoList.innerHTML = '';

        panel.style.display = '';

        if (!matches.length && !informationalMatches.length) {
            if (heading) heading.textContent = 'No conflicts found';
            list.innerHTML = '<div class="text-muted">Automated search found no potential conflicts in the CRM.</div>';
            if (infoPanel) infoPanel.style.display = 'none';
            return;
        }

        if (heading) {
            if (matches.length) {
                heading.textContent = matches.length + ' potential conflict' + (matches.length === 1 ? '' : 's');
            } else {
                heading.textContent = 'No potential conflicts';
                list.innerHTML = '<div class="text-muted">No hard conflicts found.</div>';
            }
        }

        matches.forEach(function (m) {
            list.appendChild(renderMatchRow(m, { informational: false }));
        });

        if (infoPanel && infoList) {
            if (informationalMatches.length) {
                infoPanel.style.display = '';
                informationalMatches.forEach(function (m) {
                    infoList.appendChild(renderMatchRow(m, { informational: true }));
                });
            } else {
                infoPanel.style.display = 'none';
            }
        }
    }

    function renderHistoryDetailMatches(matches, informationalMatches) {
        var wrap = document.createElement('div');
        var hardList = document.createElement('div');
        hardList.className = 'cp-match-list';
        (matches || []).forEach(function (m) {
            hardList.appendChild(renderMatchRow(m, { informational: false }));
        });
        if (!matches || !matches.length) {
            hardList.innerHTML = '<div class="text-muted">No hard conflicts stored.</div>';
        }
        wrap.appendChild(hardList);

        if (informationalMatches && informationalMatches.length) {
            var infoTitle = document.createElement('div');
            infoTitle.className = 'cp-info-panel-title mt-2';
            infoTitle.innerHTML = '<i class="fa-solid fa-circle-info"></i> Informational notes';
            wrap.appendChild(infoTitle);
            var infoList = document.createElement('div');
            infoList.className = 'cp-match-list';
            informationalMatches.forEach(function (m) {
                infoList.appendChild(renderMatchRow(m, { informational: true }));
            });
            wrap.appendChild(infoList);
        }

        return wrap;
    }

    function openHistoryDetail(checkId) {
        if (!checkId) return;
        var modalEl = document.getElementById('cpHistoryDetailModal');
        var bodyEl = document.getElementById('cpHistoryDetailBody');
        if (!modalEl || !bodyEl) return;

        if (modalEl.parentElement !== document.body) {
            document.body.appendChild(modalEl);
        }

        bodyEl.innerHTML = '<div class="text-muted">Loading…</div>';
        var url = conflictCheckDetailUrl.replace('__ID__', encodeURIComponent(checkId))
            + '?client_id=' + encodeURIComponent(clientId);

        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            bootstrap.Modal.getOrCreateInstance(modalEl, { focus: false }).show();
        } else {
            modalEl.style.display = 'block';
        }

        fetch(url, {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        })
            .then(function (r) {
                return r.json().then(function (data) {
                    return { ok: r.ok, status: r.status, data: data };
                });
            })
            .then(function (res) {
                if (!res.ok || !res.data.success || !res.data.check) {
                    bodyEl.innerHTML = '<div class="text-danger">' + esc((res.data && res.data.message) || 'Could not load check detail.') + '</div>';
                    return;
                }
                var c = res.data.check;
                var meta = document.createElement('div');
                meta.className = 'mb-2 text-muted small';
                meta.innerHTML = esc(c.checked_at || '')
                    + (c.checked_by ? ' · ' + esc(c.checked_by) : '')
                    + (c.matter_label ? ' · Matter ' + esc(c.matter_label) : '')
                    + (c.search_hash ? ' · Hash ' + esc(c.search_hash) : '');
                bodyEl.innerHTML = '';
                bodyEl.appendChild(meta);
                if (c.outcome_notes) {
                    var notes = document.createElement('div');
                    notes.className = 'small mb-2';
                    notes.innerHTML = '<strong>Notes:</strong> ' + esc(c.outcome_notes);
                    bodyEl.appendChild(notes);
                }
                bodyEl.appendChild(renderHistoryDetailMatches(c.matches, c.informational_matches));
            })
            .catch(function () {
                bodyEl.innerHTML = '<div class="text-danger">Network error loading check detail.</div>';
            });
    }

    function bindHistoryRow(el) {
        if (!el || el.getAttribute('data-bound') === '1') return;
        el.setAttribute('data-bound', '1');
        var checkId = el.getAttribute('data-check-id');
        if (!checkId) return;
        el.addEventListener('click', function () { openHistoryDetail(checkId); });
        el.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                openHistoryDetail(checkId);
            }
        });
    }

    document.querySelectorAll('#cpHistoryList .cp-history-row').forEach(bindHistoryRow);

    if (savePartiesBtn) {
        savePartiesBtn.addEventListener('click', function () {
            if (!window.OtherPartyPicker) {
                toast('Other party picker is not loaded. Refresh the page and try again.', false);
                return;
            }
            var issues = window.OtherPartyPicker.validateRows
                ? window.OtherPartyPicker.validateRows(container)
                : [];
            if (issues.length) {
                toast(issues[0], false);
                return;
            }
            var parties = collectParties();
            if (!parties.length) {
                var rowCount = container
                    ? container.querySelectorAll('.opp-party-row, .cp-party-row').length
                    : 0;
                if (rowCount > 0) {
                    toast('Each party needs a selected other party and a role.', false);
                    return;
                }
                if (!window.confirm('Remove all other parties from this matter?')) {
                    return;
                }
            }
            if (!clientMatterId) {
                toast('No active matter selected. Open a matter, then save other parties.', false);
                return;
            }
            var token = document.querySelector('meta[name="csrf-token"]');
            var fd = new FormData();
            fd.append('_token', token ? token.getAttribute('content') : '');
            fd.append('id', clientId);
            fd.append('section', 'conflictParties');
            fd.append('conflict_parties_json', JSON.stringify(parties));
            fd.append('client_matter_id', clientMatterId);
            savePartiesBtn.disabled = true;
            fetch('{{ url('/clients/save-section') }}', {
                method: 'POST',
                body: fd,
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
            })
                .then(function (r) {
                    return r.text().then(function (text) {
                        try { return { ok: r.ok, data: text ? JSON.parse(text) : {} }; }
                        catch (e) { return { ok: false, data: { message: 'Invalid server response' } }; }
                    });
                })
                .then(function (res) {
                    savePartiesBtn.disabled = false;
                    if (res.ok && res.data.success) {
                        toast(res.data.message || 'Saved', true);
                        // Reload so Client Matters list and this tile stay in sync for the active matter.
                        window.setTimeout(function () { window.location.reload(); }, 400);
                    } else {
                        toast((res.data && res.data.message) || 'Save failed', false);
                    }
                })
                .catch(function () {
                    savePartiesBtn.disabled = false;
                    toast('Network error', false);
                });
        });
    }

    function renderWarnings(warnings) {
        var el = document.getElementById('cpRunCheckWarnings');
        if (!el) return;
        if (!warnings || !warnings.length) {
            el.style.display = 'none';
            el.innerHTML = '';
            return;
        }
        el.style.display = '';
        el.innerHTML = warnings.map(function (w) {
            return '<div><i class="fa-solid fa-triangle-exclamation"></i> ' + esc(w) + '</div>';
        }).join('');
    }

    function showRunCheckError(message, errorType) {
        var statusEl = document.getElementById('cpRunCheckStatus');
        if (statusEl) statusEl.textContent = '';
        renderWarnings([]);
        var display = message || 'Search failed';
        if (errorType === 'validation') {
            display = message;
        }
        toast(display, false);
    }

    if (runCheckBtn) {
        runCheckBtn.addEventListener('click', function () {
            var token = document.querySelector('meta[name="csrf-token"]');
            var statusEl = document.getElementById('cpRunCheckStatus');
            var fd = new FormData();
            fd.append('_token', token ? token.getAttribute('content') : '');
            fd.append('id', clientId);
            if (clientMatterId) {
                fd.append('client_matter_id', clientMatterId);
            }
            runCheckBtn.disabled = true;
            renderWarnings([]);
            if (statusEl) statusEl.textContent = 'Searching…';
            fetch(runCheckUrl, {
                method: 'POST',
                body: fd,
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
            })
                .then(function (r) {
                    return r.text().then(function (text) {
                        try { return { ok: r.ok, status: r.status, data: text ? JSON.parse(text) : {} }; }
                        catch (e) { return { ok: false, status: r.status, data: { message: 'Invalid server response' } }; }
                    });
                })
                .then(function (res) {
                    runCheckBtn.disabled = false;
                    if (res.ok && res.data.success) {
                        searchWasRun = true;
                        lastSearchTerms = res.data.search_terms || null;
                        lastSearchHash = res.data.search_hash || null;
                        lastMatches = res.data.matches || [];
                        lastInformationalMatches = res.data.informational_matches || [];
                        hideStaleHint();
                        updateOutcomeSaveState();
                        renderMatches(lastMatches, lastInformationalMatches);
                        renderWarnings(res.data.warnings || []);
                        var hardCount = res.data.match_count || 0;
                        updateForceClearPanel(hardCount);
                        var infoCount = res.data.informational_count || 0;
                        if (statusEl) {
                            var statusText = hardCount + ' conflict' + (hardCount === 1 ? '' : 's');
                            if (infoCount > 0) {
                                statusText += ' · ' + infoCount + ' informational note' + (infoCount === 1 ? '' : 's');
                            }
                            if ((res.data.party_count || 0) === 0) {
                                statusText += ' · subject only';
                            }
                            statusEl.textContent = statusText;
                        }
                        var select = document.getElementById('cpOutcomeSelect');
                        if (select && res.data.suggested_outcome) {
                            if (hardCount > 0) {
                                if (!select.value || select.value === 'pending') {
                                    select.value = res.data.suggested_outcome;
                                }
                            } else if (res.data.suggested_outcome === 'clear' && (!select.value || select.value === 'pending')) {
                                select.value = 'clear';
                            }
                        }
                        var notes = document.getElementById('cpOutcomeNotes');
                        if (notes && !(notes.value || '').trim()) {
                            if (hardCount === 0 && infoCount === 0) {
                                notes.value = 'Automated CRM search completed — no matches found.';
                            } else if (hardCount === 0) {
                                notes.value = 'Automated CRM search completed — no conflicts; ' + infoCount + ' informational note(s) for awareness.';
                            } else {
                                notes.value = 'Automated CRM search found ' + hardCount + ' potential conflict(s). Review listed matches before deciding.';
                            }
                        }
                        toast(res.data.message || 'Search complete', true);
                    } else {
                        showRunCheckError(
                            (res.data && res.data.message) || 'Search failed',
                            res.data && res.data.error_type
                        );
                    }
                })
                .catch(function () {
                    runCheckBtn.disabled = false;
                    showRunCheckError('Network error. Check your connection and try again.', 'network');
                });
        });
    }

    if (saveOutcomeBtn) {
        var outcomeSelect = document.getElementById('cpOutcomeSelect');
        if (outcomeSelect) {
            outcomeSelect.addEventListener('change', updateOutcomeSaveState);
        }
        updateOutcomeSaveState();

        saveOutcomeBtn.addEventListener('click', function () {
            var outcome = (document.getElementById('cpOutcomeSelect') || {}).value || 'pending';
            var notes = ((document.getElementById('cpOutcomeNotes') || {}).value || '').trim();
            var consent = !!(document.getElementById('cpConsentObtained') || {}).checked;
            var consentNotes = ((document.getElementById('cpConsentNotes') || {}).value || '').trim();

            if (outcome === 'waived') {
                if (!consent) {
                    toast('Tick Consent obtained when outcome is Waived with consent.', false);
                    return;
                }
                if (!consentNotes) {
                    toast('Consent notes are required for Waived with consent.', false);
                    return;
                }
            }
            if (outcome === 'conflict_found' && !notes) {
                toast('Notes are required when recording Conflict found.', false);
                return;
            }
            var forceClearEl = document.getElementById('cpForceClear');
            var forceClear = !!(forceClearEl && forceClearEl.checked);
            if (outcome === 'clear' && lastHardMatchCount > 0 && !forceClear) {
                toast('Potential conflicts exist. Record Conflict found, or use the override with detailed notes (20+ characters).', false);
                return;
            }
            if (outcome === 'clear' && forceClear && notes.length < 20) {
                toast('Override requires detailed notes of at least 20 characters.', false);
                return;
            }

            var token = document.querySelector('meta[name="csrf-token"]');
            var fd = new FormData();
            fd.append('_token', token ? token.getAttribute('content') : '');
            fd.append('id', clientId);
            fd.append('section', 'conflictCheckOutcome');
            fd.append('outcome', outcome);
            fd.append('outcome_notes', notes);
            fd.append('consent_obtained', consent ? '1' : '0');
            fd.append('consent_notes', consentNotes);
            if (clientMatterId) {
                fd.append('client_matter_id', clientMatterId);
            }
            if (searchWasRun && lastSearchHash) {
                fd.append('acknowledged_search_hash', lastSearchHash);
            }
            if (forceClear) {
                fd.append('force_clear', '1');
            }
            saveOutcomeBtn.disabled = true;
            fetch('{{ url('/clients/save-section') }}', {
                method: 'POST',
                body: fd,
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
            })
                .then(function (r) {
                    return r.text().then(function (text) {
                        try { return { ok: r.ok, data: text ? JSON.parse(text) : {} }; }
                        catch (e) { return { ok: false, data: {} }; }
                    });
                })
                .then(function (res) {
                    saveOutcomeBtn.disabled = false;
                    updateOutcomeSaveState();
                    if (res.ok && res.data.success) {
                        toast(res.data.message || 'Outcome saved', true);
                        if (res.data.outcome && res.data.checked_at) {
                            updateOutcomeDisplay(res.data.outcome, res.data.checked_at);
                            prependHistory(
                                res.data.outcome,
                                res.data.checked_at,
                                res.data.match_count || 0,
                                res.data.informational_count || 0,
                                res.data.matter_label || '',
                                res.data.check_id || null
                            );
                        }
                        if (forceClearEl) {
                            forceClearEl.checked = false;
                        }
                    } else {
                        if (res.data && res.data.error_type === 'stale') {
                            showStaleHint(
                                (res.data.staleness && res.data.staleness.reason)
                                    || res.data.message
                                    || 'Parties or client details changed. Re-run the conflict search.'
                            );
                        }
                        var errMsg = (res.data && res.data.message) || 'Save failed';
                        if (res.data && res.data.error_type === 'validation' && res.data.match_count > 0) {
                            errMsg += ' (' + res.data.match_count + ' potential conflict'
                                + (res.data.match_count === 1 ? '' : 's') + ')';
                        }
                        toast(errMsg, false);
                    }
                })
                .catch(function () {
                    saveOutcomeBtn.disabled = false;
                    updateOutcomeSaveState();
                    toast('Network error', false);
                });
        });
    }
})();
</script>
@endpush
