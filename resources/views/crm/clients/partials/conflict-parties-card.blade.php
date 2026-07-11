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
</style>

<div class="card" id="conflictPartiesCard" data-client-id="{{ $fetchedData->id }}">
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
            <i class="fa-solid fa-circle-info"></i>
            <strong>Other party</strong> — search and select from records created as Other Party only.
            <strong>Opposing solicitor</strong> — enter manually below each party.
            <a href="{{ route('leads.create', ['other_party' => 1]) }}" target="_blank" rel="noopener">Create other party</a>
        </p>

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
                <textarea id="cpOutcomeNotes" class="form-control form-control-sm" rows="2"></textarea>
            </div>
            <div class="form-group mb-2">
                <label class="d-flex align-items-center gap-2">
                    <input type="checkbox" id="cpConsentObtained"> Consent obtained
                </label>
            </div>
            <div class="form-group mb-2">
                <label for="cpConsentNotes">Consent notes</label>
                <input type="text" id="cpConsentNotes" class="form-control form-control-sm" placeholder="Who gave consent, form used, etc.">
            </div>
            <button type="button" class="btn btn-success btn-sm" id="cpSaveOutcomeBtn">Save outcome</button>
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

    var partyRoles = @json($partyRoles);
    var initialParties = @json($initialParties);
    var outcomeLabels = @json($outcomeLabels);
    var outcomeBadgeColors = @json($outcomeBadgeColors);
    var searchUrl = window.OTHER_PARTY_SEARCH_URL || @json(route('api.search.other.party'));
    var clientId = card.getAttribute('data-client-id');

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
            return;
        }
        window.OtherPartyPicker.appendRow(container, {
            rowClass: 'cp-party-row',
            searchUrl: searchUrl,
            excludeId: clientId,
            roles: partyRoles,
            repSectionTitle: 'Opposing solicitor (enter manually)',
            data: data || {}
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
                        initialParties = parties;
                        refreshViewSummary(parties, res.data.count);
                        setEditMode(false);
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

    if (saveOutcomeBtn) {
        saveOutcomeBtn.addEventListener('click', function () {
            var token = document.querySelector('meta[name="csrf-token"]');
            var fd = new FormData();
            fd.append('_token', token ? token.getAttribute('content') : '');
            fd.append('id', clientId);
            fd.append('section', 'conflictCheckOutcome');
            fd.append('outcome', (document.getElementById('cpOutcomeSelect') || {}).value || 'pending');
            fd.append('outcome_notes', (document.getElementById('cpOutcomeNotes') || {}).value || '');
            fd.append('consent_obtained', (document.getElementById('cpConsentObtained') || {}).checked ? '1' : '0');
            fd.append('consent_notes', (document.getElementById('cpConsentNotes') || {}).value || '');
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
