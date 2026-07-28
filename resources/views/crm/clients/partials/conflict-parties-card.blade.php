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
    #conflictPartiesCard .cp-subsection { margin-top: 10px; padding-top: 10px; border-top: 1px dashed #dee2e6; }
    #conflictPartiesCard .cp-subsection-title { font-weight: 600; font-size: 0.9rem; margin-bottom: 8px; color: #495057; }
    #conflictPartiesCard .cp-outcome-badge {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 4px;
        font-size: 12px;
        font-weight: 600;
        color: #fff;
    }
    #conflictPartiesCard .cp-match-list {
        max-height: 220px;
        overflow-y: auto;
        border: 1px solid #e9ecef;
        border-radius: 4px;
        background: #fafbfc;
        padding: 8px;
        margin-bottom: 10px;
    }
    #conflictPartiesCard .cp-match-item {
        padding: 6px 0;
        border-bottom: 1px solid #eee;
        font-size: 12px;
    }
    #conflictPartiesCard .cp-match-item:last-child { border-bottom: 0; }
    #conflictPartiesCard .cp-match-name { font-weight: 600; color: #212529; }
    #conflictPartiesCard .cp-match-meta { color: #666; }
    #conflictPartiesCard .cp-history-list { font-size: 12px; color: #555; margin-top: 8px; }
    #conflictPartiesCard .cp-history-item { padding: 3px 0; }
    #conflictPartiesCard .cp-stale-hint {
        display: none;
        font-size: 12px;
        color: #856404;
        background: #fff3cd;
        border: 1px solid #ffeeba;
        border-radius: 4px;
        padding: 6px 8px;
        margin-bottom: 8px;
    }
#conflictPartiesCard #cpPartiesContainer {
        margin-bottom: 8px;
        min-width: 0;
        overflow: visible;
    }

    #conflictPartiesCard .cp-edit,
    #conflictPartiesCard .opp-party-row {
        min-width: 0;
        overflow: visible;
    }
</style>
<link rel="stylesheet" href="{{ asset('css/crm/other-party-picker.css') }}?v={{ @filemtime(public_path('css/crm/other-party-picker.css')) ?: time() }}">

<div class="card" id="conflictPartiesCard"
     data-client-id="{{ $fetchedData->id }}"
     data-client-matter-id="{{ $activeClientMatterId ?? '' }}">
    <div id="conflictPartiesView" class="cp-view" role="region" aria-label="Other parties summary">
        <div class="cp-card-header">
            <h3><i class="fa-solid fa-user-shield"></i> Other Parties &amp; Conflict Check</h3>
            <button type="button"
                    class="btn btn-primary btn-sm cp-open-edit cp-open-edit--icon"
                    title="Edit other parties"
                    aria-label="Edit other parties"
                    aria-expanded="false"
                    aria-controls="conflictPartiesEdit">
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

    <div id="conflictPartiesEdit"
         class="cp-edit"
         style="display:none;"
         role="region"
         aria-label="Edit other parties"
         aria-hidden="true">
        <div class="cp-card-header">
            <span><i class="fa-solid fa-pen-to-square text-primary me-1"></i>Editing other parties</span>
            <button type="button" class="btn btn-link btn-sm p-0 cp-open-edit" aria-label="Close editor">Close</button>
        </div>

        <p class="text-muted small mb-2">
            Search and select an <strong>other party</strong>, choose their <strong>role</strong>, and optionally add the <strong>opposing solicitor</strong> (search existing or enter manually).
        </p>

        <div class="d-flex flex-wrap gap-2 mb-2 align-items-center">
            <button type="button" class="btn btn-outline-primary btn-sm" id="cpCreateOtherPartyBtn">
                <i class="fa-solid fa-user-plus"></i> New other party
            </button>
            <a href="{{ route('leads.create', ['other_party' => 1]) }}" target="_blank" rel="noopener" class="small">Full create form</a>
        </div>

        <div id="cpMiniCreateOtherParty" class="cp-mini-create" style="display:none;">
            <p class="small fw-semibold mb-2 mb-0">Quick create other party</p>
            <p class="text-muted small mb-2">First &amp; last name required. Phone or email required.</p>
            <div class="row g-2">
                <div class="col-sm-6 col-md-3"><input type="text" class="form-control form-control-sm" id="cpMiniOpFirst" placeholder="First name *"></div>
                <div class="col-sm-6 col-md-3"><input type="text" class="form-control form-control-sm" id="cpMiniOpLast" placeholder="Last name *"></div>
                <div class="col-sm-6 col-md-3"><input type="text" class="form-control form-control-sm" id="cpMiniOpPhone" placeholder="Phone"></div>
                <div class="col-sm-6 col-md-3"><input type="email" class="form-control form-control-sm" id="cpMiniOpEmail" placeholder="Email"></div>
            </div>
            <div class="mt-2 d-flex gap-2 flex-wrap">
                <button type="button" class="btn btn-sm btn-primary" id="cpMiniOpSave">Save &amp; select</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="cpMiniOpCancel">Cancel</button>
            </div>
            <div id="cpMiniOpMessage" class="mt-2" style="display:none;"></div>
        </div>

        <div id="cpPartiesContainer"></div>

        <button type="button" class="btn btn-outline-primary btn-sm mb-3" id="cpAddPartyBtn">
            <i class="fa-solid fa-plus"></i> Add party
        </button>

        <div class="d-flex gap-2 mb-4">
            <button type="button" class="btn btn-primary btn-sm" id="cpSavePartiesBtn">Save parties</button>
            <button type="button" class="btn btn-secondary btn-sm cp-open-edit">Cancel</button>
        </div>

        <div class="cp-subsection" id="cpOutcomeSection">
            <div class="cp-subsection-title"><i class="fa-solid fa-clipboard-check"></i> Conflict check outcome</div>
            <p class="text-muted small mb-2" id="cpLastCheckHint">
                @if($latestCheckedAt && $latestOutcomeLabel)
                    Last check: {{ $latestCheckedAt }} — {{ $latestOutcomeLabel }}
                @else
                    Last check: Not checked yet
                @endif
            </p>

            <div class="cp-stale-hint" id="cpStaleHint">
                Parties were updated after the last Clear/Waived check. Re-run the conflict search before saving a new outcome.
            </div>

            <div class="d-flex gap-2 align-items-center mb-2 flex-wrap">
                <button type="button" class="btn btn-outline-success btn-sm" id="cpRunCheckBtn">
                    <i class="fa-solid fa-magnifying-glass"></i> Run conflict check
                </button>
                <span class="text-muted small" id="cpRunCheckStatus"></span>
            </div>
            <p class="text-muted small mb-2">
                Searches saved client details and saved other parties. If you have added parties above, click <strong>Save parties</strong> before running the check.
            </p>
            <div id="cpRunCheckWarnings" class="small text-warning mb-2" style="display:none;"></div>

            <div id="cpMatchesPanel" style="display:none;">
                <div class="small fw-semibold mb-1" id="cpMatchesHeading">Matches</div>
                <div class="cp-match-list" id="cpMatchesList"></div>
            </div>

            <div class="form-group mb-2">
                <label for="cpOutcomeSelect">Outcome</label>
                <select id="cpOutcomeSelect" class="form-control form-control-sm">
                    @foreach($outcomeLabels as $val => $lbl)
                        <option value="{{ $val }}" @selected($latestOutcome === $val)>{{ $lbl }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group mb-2">
                <label for="cpOutcomeNotes">Notes</label>
                <textarea id="cpOutcomeNotes" class="form-control form-control-sm" rows="2">{{ $latestConflictCheck?->outcome_notes ?? '' }}</textarea>
            </div>
            <div class="form-group mb-2">
                <label class="d-flex align-items-center gap-2">
                    <input type="checkbox" id="cpConsentObtained" @checked($latestConflictCheck?->consent_obtained ?? false)> Consent obtained
                </label>
            </div>
            <div class="form-group mb-2">
                <label for="cpConsentNotes">Consent notes</label>
                <input type="text" id="cpConsentNotes" class="form-control form-control-sm"
                       placeholder="Who gave consent, form used, etc."
                       value="{{ $latestConflictCheck?->consent_notes ?? '' }}">
            </div>
            <button type="button" class="btn btn-success btn-sm" id="cpSaveOutcomeBtn">Save outcome</button>

            @if($conflictCheckHistory->isNotEmpty())
                <div class="cp-history-list" id="cpHistoryList">
                    <div class="fw-semibold mb-1" style="margin-top:10px;">Recent checks</div>
                    @foreach($conflictCheckHistory as $hist)
                        @php
                            $hLabel = $outcomeLabels[$hist->outcome] ?? $hist->outcome;
                            $hAt = $hist->checked_at ? $hist->checked_at->format('d M Y H:i') : '—';
                            $hMatches = is_array($hist->matches) ? count($hist->matches) : 0;
                        @endphp
                        <div class="cp-history-item">
                            {{ $hAt }} —
                            <span class="cp-outcome-badge" style="background:{{ $outcomeBadgeColors[$hist->outcome] ?? '#555' }};">{{ $hLabel }}</span>
                            @if($hMatches > 0)
                                <span class="text-muted">({{ $hMatches }} match{{ $hMatches === 1 ? '' : 'es' }})</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            @else
                <div class="cp-history-list" id="cpHistoryList" style="display:none;"></div>
            @endif
        </div>
    </div>
</div>

@push('scripts')
<script>
(function () {
    var card = document.getElementById('conflictPartiesCard');
    if (!card) return;

    var viewEl = document.getElementById('conflictPartiesView');
    var editEl = document.getElementById('conflictPartiesEdit');
    var container = document.getElementById('cpPartiesContainer');
    var openBtns = card.querySelectorAll('.cp-open-edit');
    var savePartiesBtn = document.getElementById('cpSavePartiesBtn');
    var addPartyBtn = document.getElementById('cpAddPartyBtn');
    var saveOutcomeBtn = document.getElementById('cpSaveOutcomeBtn');
    var runCheckBtn = document.getElementById('cpRunCheckBtn');

    var partyRoles = @json($partyRoles);
    var initialParties = @json($initialParties);
    var outcomeLabels = @json($outcomeLabels);
    var outcomeBadgeColors = @json($outcomeBadgeColors);
    var searchUrl = window.OTHER_PARTY_SEARCH_URL || @json(route('api.search.other.party'));
    var solicitorSearchUrl = window.CONTACT_PERSON_SEARCH_URL || @json(route('api.search.contact.person'));
    var runCheckUrl = @json(route('clients.conflictCheck.run'));
    var clientId = card.getAttribute('data-client-id');
    var clientMatterId = card.getAttribute('data-client-matter-id') || '';
    var latestOutcome = @json($latestOutcome);
    var lastSearchTerms = null;
    var lastMatches = null;
    var searchWasRun = false;

    function toast(msg, ok) {
        if (typeof iziToast !== 'undefined' && iziToast.show) {
            iziToast.show({ message: msg, color: ok ? 'green' : 'red', position: 'topRight', timeout: 4000 });
        } else { alert(msg); }
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
            rowClass: 'cp-party-row',
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

    function setEditMode(open) {
        if (!viewEl || !editEl) return;
        if (open) {
            viewEl.style.display = 'none';
            viewEl.setAttribute('aria-hidden', 'true');
            editEl.style.display = '';
            editEl.setAttribute('aria-hidden', 'false');
            openBtns.forEach(function (b) { b.setAttribute('aria-expanded', 'true'); });
            initEditorRows();
        } else {
            destroyEditorRows();
            viewEl.style.display = '';
            viewEl.setAttribute('aria-hidden', 'false');
            editEl.style.display = 'none';
            editEl.setAttribute('aria-hidden', 'true');
            openBtns.forEach(function (b) { b.setAttribute('aria-expanded', 'false'); });
        }
    }

    openBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            var open = editEl && editEl.style.display !== 'none';
            setEditMode(!open);
        });
    });

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
        var stale = document.getElementById('cpStaleHint');
        if (stale) stale.style.display = 'none';
    }

    function prependHistory(outcome, checkedAt, matchCount) {
        var list = document.getElementById('cpHistoryList');
        if (!list) return;
        list.style.display = '';
        var label = outcomeLabels[outcome] || outcome;
        var color = outcomeBadgeColors[outcome] || '#555';
        var item = document.createElement('div');
        item.className = 'cp-history-item';
        item.innerHTML = esc(checkedAt) + ' — <span class="cp-outcome-badge" style="background:' + color + ';">' + esc(label) + '</span>' +
            (matchCount > 0 ? ' <span class="text-muted">(' + matchCount + ' match' + (matchCount === 1 ? '' : 'es') + ')</span>' : '');
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
    }

    function renderMatches(matches) {
        var panel = document.getElementById('cpMatchesPanel');
        var list = document.getElementById('cpMatchesList');
        var heading = document.getElementById('cpMatchesHeading');
        if (!panel || !list) return;

        list.innerHTML = '';
        if (!matches || !matches.length) {
            panel.style.display = '';
            if (heading) heading.textContent = 'No matches found';
            list.innerHTML = '<div class="text-muted">Automated search found no potential conflicts in the CRM.</div>';
            return;
        }

        panel.style.display = '';
        if (heading) heading.textContent = matches.length + ' potential match' + (matches.length === 1 ? '' : 'es');
        matches.forEach(function (m) {
            var div = document.createElement('div');
            div.className = 'cp-match-item';
            var link = '';
            if (m.detail_url) {
                link = ' <a href="' + esc(m.detail_url) + '" target="_blank" rel="noopener">Open</a>';
            } else if (m.client_id) {
                link = ' <a href="/clients/detail/' + encodeURIComponent(m.client_id) + '" target="_blank" rel="noopener">Open</a>';
            }
            div.innerHTML =
                '<div class="cp-match-name">' + esc(m.name || 'Unknown') + link + '</div>' +
                '<div class="cp-match-meta">' + esc(m.context || '') +
                (m.matched_on ? ' · Matched on ' + esc(m.matched_on) : '') +
                (m.party_role ? ' · ' + esc(m.party_role) : '') +
                '</div>';
            list.appendChild(div);
        });
    }

    if (savePartiesBtn) {
        savePartiesBtn.addEventListener('click', function () {
            var parties = collectParties();
            var token = document.querySelector('meta[name="csrf-token"]');
            var fd = new FormData();
            fd.append('_token', token ? token.getAttribute('content') : '');
            fd.append('id', clientId);
            fd.append('section', 'conflictParties');
            fd.append('conflict_parties_json', JSON.stringify(parties));
            if (clientMatterId) {
                fd.append('client_matter_id', clientMatterId);
            }
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
                        lastMatches = res.data.matches || [];
                        renderMatches(lastMatches);
                        renderWarnings(res.data.warnings || []);
                        if (statusEl) {
                            var statusText = (res.data.match_count || 0) + ' match(es)';
                            if ((res.data.party_count || 0) === 0) {
                                statusText += ' · subject only';
                            }
                            statusEl.textContent = statusText;
                        }
                        var select = document.getElementById('cpOutcomeSelect');
                        if (select && res.data.suggested_outcome && (!select.value || select.value === 'pending')) {
                            select.value = res.data.suggested_outcome;
                        }
                        var notes = document.getElementById('cpOutcomeNotes');
                        if (notes && !(notes.value || '').trim()) {
                            var count = res.data.match_count || 0;
                            notes.value = count === 0
                                ? 'Automated CRM search completed — no matches found.'
                                : 'Automated CRM search found ' + count + ' potential match(es). Review listed matches before deciding.';
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
            if (outcome === 'clear' && lastMatches && lastMatches.length > 0) {
                if (!window.confirm('Automated search found ' + lastMatches.length + ' potential match(es). Save outcome as Clear anyway?')) {
                    return;
                }
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
            if (searchWasRun) {
                fd.append('search_terms', JSON.stringify(lastSearchTerms || {}));
                fd.append('matches', JSON.stringify(lastMatches || []));
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
                    if (res.ok && res.data.success) {
                        toast(res.data.message || 'Outcome saved', true);
                        if (res.data.outcome && res.data.checked_at) {
                            updateOutcomeDisplay(res.data.outcome, res.data.checked_at);
                            prependHistory(res.data.outcome, res.data.checked_at, res.data.match_count || 0);
                        }
                    } else {
                        toast((res.data && res.data.message) || 'Save failed', false);
                    }
                })
                .catch(function () {
                    saveOutcomeBtn.disabled = false;
                    toast('Network error', false);
                });
        });
    }
})();
</script>
@endpush
