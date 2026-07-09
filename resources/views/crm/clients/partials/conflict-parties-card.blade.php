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
        return [
            'party_type' => $p->party_type ?? 'individual',
            'party_role' => $p->party_role,
            'first_name' => $p->first_name,
            'last_name' => $p->last_name,
            'aliases' => $p->aliases ?? [],
            'dob' => $p->dob ? $p->dob->format('d/m/Y') : '',
            'company_name' => $p->company_name,
            'trading_name' => $p->trading_name,
            'abn' => $p->abn,
            'acn' => $p->acn,
            'address' => $p->address,
            'suburb' => $p->suburb,
            'state' => $p->state,
            'postcode' => $p->postcode,
            'country' => $p->country ?? 'Australia',
            'rep_firm_name' => $p->rep_firm_name,
            'rep_name' => $p->rep_name,
            'rep_email' => $p->rep_email,
            'rep_phone' => $p->rep_phone,
            'rep_country_code' => $p->rep_country_code ?? '+61',
            'notes' => $p->notes,
            'phones' => $p->phones->map(fn ($ph) => [
                'contact_type' => $ph->contact_type,
                'country_code' => $ph->country_code ?? '+61',
                'phone' => $ph->phone,
            ])->values()->all(),
            'emails' => $p->emails->map(fn ($em) => [
                'email_type' => $em->email_type,
                'email' => $em->email,
            ])->values()->all(),
        ];
    })->values()->all();
    $latestOutcome = $latestConflictCheck?->outcome;
    $latestOutcomeLabel = $latestOutcome ? ($outcomeLabels[$latestOutcome] ?? ucfirst($latestOutcome)) : null;
    $latestCheckedAt = ($latestConflictCheck && $latestConflictCheck->checked_at)
        ? $latestConflictCheck->checked_at->format('d M Y H:i')
        : null;
@endphp

<link rel="stylesheet" href="{{ asset('css/address-autocomplete.css') }}">

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
    #conflictPartiesCard .cp-party-row {
        border: 1px solid #dee2e6;
        border-radius: 6px;
        padding: 12px;
        margin-bottom: 12px;
        background: #fafbfc;
    }
    #conflictPartiesCard .cp-party-row-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
    }
    #conflictPartiesCard .cp-subsection { margin-top: 10px; padding-top: 10px; border-top: 1px dashed #dee2e6; }
    #conflictPartiesCard .cp-subsection-title { font-weight: 600; font-size: 0.9rem; margin-bottom: 8px; color: #495057; }
    #conflictPartiesCard .cp-fields-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
    @media (max-width: 768px) { #conflictPartiesCard .cp-fields-grid { grid-template-columns: 1fr; } }
    #conflictPartiesCard .cp-type-toggle { display: flex; gap: 6px; flex-wrap: wrap; }
    #conflictPartiesCard .cp-type-btn.active { background: #1a73e8; color: #fff; border-color: #1a73e8; }
    #conflictPartiesCard .cp-outcome-badge {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 4px;
        font-size: 12px;
        font-weight: 600;
        color: #fff;
    }
    #conflictPartiesCard .cp-repeat-item { position: relative; padding-right: 36px; margin-bottom: 8px; }
    #conflictPartiesCard .cp-remove-mini {
        position: absolute; right: 0; top: 4px;
        border: none; background: transparent; color: #c5221f; cursor: pointer;
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

        <div id="addressInfoEdit"
             style="display:none;"
             data-search-route="{{ route('clients.searchAddressFull') }}"
             data-details-route="{{ route('clients.getPlaceDetails') }}"
             data-csrf-token="{{ csrf_token() }}"
             data-address-count="0"></div>

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
<script src="{{ asset('js/address-autocomplete.js') }}"></script>
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

    var countryCodes = ['+61','+64','+91','+1','+44'];

    function toast(msg, ok) {
        if (typeof iziToast !== 'undefined' && iziToast.show) {
            iziToast.show({ message: msg, color: ok ? 'green' : 'red', position: 'topRight', timeout: 4000 });
        } else { alert(msg); }
    }

    function setEditMode(open) {
        if (!viewEl || !editEl) return;
        if (open) {
            viewEl.style.display = 'none';
            viewEl.setAttribute('aria-hidden', 'true');
            editEl.style.display = '';
            editEl.setAttribute('aria-hidden', 'false');
            openBtns.forEach(function (b) { b.setAttribute('aria-expanded', 'true'); });
            if (container && !container.children.length) {
                if (initialParties.length) {
                    initialParties.forEach(function (p) { addPartyRow(p); });
                } else {
                    addPartyRow();
                }
            }
            if (typeof window.initAddressAutocomplete === 'function') {
                window.initAddressAutocomplete();
            }
        } else {
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

    function esc(s) {
        if (s == null) return '';
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/"/g,'&quot;');
    }

    function roleOptions(selected) {
        var html = '<option value="">— Select role —</option>';
        Object.keys(partyRoles).forEach(function (k) {
            html += '<option value="' + k + '"' + (selected === k ? ' selected' : '') + '>' + esc(partyRoles[k]) + '</option>';
        });
        return html;
    }

    function phoneRow(data) {
        data = data || {};
        var cc = data.country_code || '+61';
        var ccOpts = countryCodes.map(function (c) {
            return '<option value="' + c + '"' + (cc === c ? ' selected' : '') + '>' + c + '</option>';
        }).join('');
        return '<div class="cp-repeat-item cp-phone-item">' +
            '<button type="button" class="cp-remove-mini cp-remove-phone" title="Remove phone"><i class="fa-solid fa-xmark"></i></button>' +
            '<div class="cp-fields-grid">' +
            '<div><label class="small">Type</label><select class="form-control form-control-sm cp-ph-type">' +
            '<option' + (data.contact_type === 'Mobile' ? ' selected' : '') + '>Mobile</option>' +
            '<option' + (data.contact_type === 'Personal' ? ' selected' : '') + '>Personal</option>' +
            '<option' + (data.contact_type === 'Work' ? ' selected' : '') + '>Work</option>' +
            '<option' + (data.contact_type === 'Other' ? ' selected' : '') + '>Other</option>' +
            '</select></div>' +
            '<div><label class="small">Number</label><div class="d-flex gap-1">' +
            '<select class="form-control form-control-sm cp-ph-cc" style="max-width:90px;">' + ccOpts + '</select>' +
            '<input type="text" class="form-control form-control-sm cp-ph-num" value="' + esc(data.phone || '') + '">' +
            '</div></div></div></div>';
    }

    function emailRow(data) {
        data = data || {};
        return '<div class="cp-repeat-item cp-email-item">' +
            '<button type="button" class="cp-remove-mini cp-remove-email" title="Remove email"><i class="fa-solid fa-xmark"></i></button>' +
            '<div class="cp-fields-grid">' +
            '<div><label class="small">Type</label><select class="form-control form-control-sm cp-em-type">' +
            '<option' + (data.email_type === 'Personal' ? ' selected' : '') + '>Personal</option>' +
            '<option' + (data.email_type === 'Work' ? ' selected' : '') + '>Work</option>' +
            '<option' + (data.email_type === 'Other' ? ' selected' : '') + '>Other</option>' +
            '</select></div>' +
            '<div><label class="small">Email</label><input type="email" class="form-control form-control-sm cp-em-addr" value="' + esc(data.email || '') + '"></div>' +
            '</div></div>';
    }

    function aliasInput(val) {
        return '<div class="cp-repeat-item cp-alias-item d-flex gap-1 align-items-center">' +
            '<input type="text" class="form-control form-control-sm cp-alias-val flex-grow-1" value="' + esc(val || '') + '" placeholder="Former / maiden name">' +
            '<button type="button" class="btn btn-link btn-sm text-danger p-0 cp-remove-alias" title="Remove"><i class="fa-solid fa-xmark"></i></button>' +
            '</div>';
    }

    function addPartyRow(data) {
        data = data || {};
        var type = data.party_type || 'individual';
        var idx = container.children.length;
        var row = document.createElement('div');
        row.className = 'cp-party-row';
        row.setAttribute('data-party-index', String(idx));

        var searchVal = data.address || '';
        if (data.suburb) searchVal = [data.address, data.suburb, data.state, data.postcode].filter(Boolean).join(', ');

        row.innerHTML =
            '<div class="cp-party-row-header">' +
            '<strong>Party ' + (idx + 1) + '</strong>' +
            '<button type="button" class="btn btn-link btn-sm text-danger p-0 cp-remove-party">Remove</button>' +
            '</div>' +
            '<div class="form-group mb-2">' +
            '<label class="small">Party type</label>' +
            '<div class="cp-type-toggle">' +
            '<button type="button" class="btn btn-sm btn-outline-secondary cp-type-btn' + (type === 'individual' ? ' active' : '') + '" data-type="individual">Individual</button>' +
            '<button type="button" class="btn btn-sm btn-outline-secondary cp-type-btn' + (type === 'company' ? ' active' : '') + '" data-type="company">Company</button>' +
            '<button type="button" class="btn btn-sm btn-outline-secondary cp-type-btn' + (type === 'trust' ? ' active' : '') + '" data-type="trust">Trust</button>' +
            '<button type="button" class="btn btn-sm btn-outline-secondary cp-type-btn' + (type === 'government' ? ' active' : '') + '" data-type="government">Government</button>' +
            '</div><input type="hidden" class="cp-party-type" value="' + esc(type) + '">' +
            '</div>' +
            '<div class="form-group mb-2"><label class="small">Role</label>' +
            '<select class="form-control form-control-sm cp-party-role">' + roleOptions(data.party_role || '') + '</select></div>' +
            '<div class="cp-individual-fields"' + (type !== 'individual' && type !== 'trust' ? ' style="display:none;"' : '') + '>' +
            '<div class="cp-fields-grid mb-2">' +
            '<div><label class="small">First name</label><input type="text" class="form-control form-control-sm cp-first-name" value="' + esc(data.first_name || '') + '"></div>' +
            '<div><label class="small">Last name</label><input type="text" class="form-control form-control-sm cp-last-name" value="' + esc(data.last_name || '') + '"></div>' +
            '</div>' +
            '<div class="form-group mb-2"><label class="small">Date of birth</label>' +
            '<input type="text" class="form-control form-control-sm cp-dob date-picker" placeholder="dd/mm/yyyy" value="' + esc(data.dob || '') + '"></div>' +
            '<div class="cp-subsection"><div class="cp-subsection-title">Aliases</div><div class="cp-aliases-list"></div>' +
            '<button type="button" class="btn btn-link btn-sm p-0 cp-add-alias">+ Add alias</button></div>' +
            '</div>' +
            '<div class="cp-company-fields"' + (type === 'company' || type === 'government' ? '' : ' style="display:none;"') + '>' +
            '<div class="cp-fields-grid mb-2">' +
            '<div><label class="small">Company / entity name</label><input type="text" class="form-control form-control-sm cp-company-name" value="' + esc(data.company_name || '') + '"></div>' +
            '<div><label class="small">Trading name</label><input type="text" class="form-control form-control-sm cp-trading-name" value="' + esc(data.trading_name || '') + '"></div>' +
            '</div>' +
            '<div class="cp-fields-grid mb-2">' +
            '<div><label class="small">ABN</label><input type="text" class="form-control form-control-sm cp-abn" value="' + esc(data.abn || '') + '"></div>' +
            '<div><label class="small">ACN</label><input type="text" class="form-control form-control-sm cp-acn" value="' + esc(data.acn || '') + '"></div>' +
            '</div></div>' +
            '<div class="cp-subsection address-entry-wrapper">' +
            '<div class="cp-subsection-title">Address</div>' +
            '<div class="form-group address-search-container mb-2">' +
            '<label class="small">Search address</label>' +
            '<input type="text" class="form-control form-control-sm address-search-input" placeholder="Start typing an address..." autocomplete="off" value="' + esc(searchVal) + '">' +
            '</div>' +
            '<div class="form-group mb-2"><label class="small">Street address</label>' +
            '<input type="text" name="address_line_1[]" class="form-control form-control-sm cp-street" value="' + esc(data.address || '') + '"></div>' +
            '<div class="cp-fields-grid">' +
            '<div><label class="small">Suburb</label><input type="text" name="suburb[]" class="form-control form-control-sm cp-suburb" value="' + esc(data.suburb || '') + '"></div>' +
            '<div><label class="small">State</label><input type="text" name="state[]" class="form-control form-control-sm cp-state" value="' + esc(data.state || '') + '"></div>' +
            '<div><label class="small">Postcode</label><input type="text" name="zip[]" class="form-control form-control-sm cp-postcode" value="' + esc(data.postcode || '') + '"></div>' +
            '<div><label class="small">Country</label><input type="text" name="country[]" class="form-control form-control-sm cp-country" value="' + esc(data.country || 'Australia') + '"></div>' +
            '</div></div>' +
            '<div class="cp-subsection"><div class="cp-subsection-title">Phones</div><div class="cp-phones-list"></div>' +
            '<button type="button" class="btn btn-link btn-sm p-0 cp-add-phone">+ Add phone</button></div>' +
            '<div class="cp-subsection"><div class="cp-subsection-title">Emails</div><div class="cp-emails-list"></div>' +
            '<button type="button" class="btn btn-link btn-sm p-0 cp-add-email">+ Add email</button></div>' +
            '<div class="cp-subsection"><div class="cp-subsection-title">Opposing solicitor</div>' +
            '<div class="cp-fields-grid mb-2">' +
            '<div><label class="small">Firm name</label><input type="text" class="form-control form-control-sm cp-rep-firm" value="' + esc(data.rep_firm_name || '') + '"></div>' +
            '<div><label class="small">Solicitor name</label><input type="text" class="form-control form-control-sm cp-rep-name" value="' + esc(data.rep_name || '') + '"></div>' +
            '</div>' +
            '<div class="cp-fields-grid mb-2">' +
            '<div><label class="small">Phone</label><div class="d-flex gap-1">' +
            '<select class="form-control form-control-sm cp-rep-cc" style="max-width:90px;">' +
            countryCodes.map(function (c) {
                return '<option value="' + c + '"' + ((data.rep_country_code || '+61') === c ? ' selected' : '') + '>' + c + '</option>';
            }).join('') +
            '</select><input type="text" class="form-control form-control-sm cp-rep-phone" value="' + esc(data.rep_phone || '') + '"></div></div>' +
            '<div><label class="small">Email</label><input type="email" class="form-control form-control-sm cp-rep-email" value="' + esc(data.rep_email || '') + '"></div>' +
            '</div></div>' +
            '<div class="form-group mb-0"><label class="small">Notes</label>' +
            '<textarea class="form-control form-control-sm cp-notes" rows="2">' + esc(data.notes || '') + '</textarea></div>';

        container.appendChild(row);

        var aliasesList = row.querySelector('.cp-aliases-list');
        var phonesList = row.querySelector('.cp-phones-list');
        var emailsList = row.querySelector('.cp-emails-list');
        (data.aliases || []).forEach(function (a) {
            if (a) aliasesList.insertAdjacentHTML('beforeend', aliasInput(a));
        });
        (data.phones && data.phones.length ? data.phones : [{}]).forEach(function (ph) {
            phonesList.insertAdjacentHTML('beforeend', phoneRow(ph));
        });
        (data.emails && data.emails.length ? data.emails : [{}]).forEach(function (em) {
            emailsList.insertAdjacentHTML('beforeend', emailRow(em));
        });

        bindPartyRow(row);
        if (typeof window.initAddressAutocompleteForNewField === 'function' && typeof jQuery !== 'undefined') {
            window.initAddressAutocompleteForNewField(jQuery(row));
        }
    }

    function bindPartyRow(row) {
        row.querySelectorAll('.cp-type-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var t = btn.getAttribute('data-type');
                row.querySelector('.cp-party-type').value = t;
                row.querySelectorAll('.cp-type-btn').forEach(function (b) { b.classList.remove('active'); });
                btn.classList.add('active');
                var ind = row.querySelector('.cp-individual-fields');
                var co = row.querySelector('.cp-company-fields');
                if (t === 'company' || t === 'government') {
                    if (ind) ind.style.display = 'none';
                    if (co) co.style.display = '';
                } else if (t === 'trust') {
                    if (ind) ind.style.display = '';
                    if (co) co.style.display = 'none';
                } else {
                    if (ind) ind.style.display = '';
                    if (co) co.style.display = 'none';
                }
            });
        });
        row.querySelector('.cp-remove-party').addEventListener('click', function () {
            row.remove();
            renumberParties();
        });
        row.querySelector('.cp-add-phone').addEventListener('click', function () {
            row.querySelector('.cp-phones-list').insertAdjacentHTML('beforeend', phoneRow({}));
        });
        row.querySelector('.cp-add-email').addEventListener('click', function () {
            row.querySelector('.cp-emails-list').insertAdjacentHTML('beforeend', emailRow({}));
        });
        row.querySelector('.cp-add-alias').addEventListener('click', function () {
            row.querySelector('.cp-aliases-list').insertAdjacentHTML('beforeend', aliasInput(''));
        });
        row.addEventListener('click', function (e) {
            if (e.target.closest('.cp-remove-phone')) e.target.closest('.cp-phone-item').remove();
            if (e.target.closest('.cp-remove-email')) e.target.closest('.cp-email-item').remove();
            if (e.target.closest('.cp-remove-alias')) e.target.closest('.cp-alias-item').remove();
        });
        row.querySelector('.address-search-input').addEventListener('change', function () {
            var street = row.querySelector('.cp-street');
            if (street && !street.value) street.value = this.value;
        });
    }

    function renumberParties() {
        container.querySelectorAll('.cp-party-row').forEach(function (r, i) {
            r.setAttribute('data-party-index', String(i));
            var h = r.querySelector('.cp-party-row-header strong');
            if (h) h.textContent = 'Party ' + (i + 1);
        });
    }

    if (addPartyBtn) {
        addPartyBtn.addEventListener('click', function () { addPartyRow(); });
    }

    function collectParties() {
        var parties = [];
        container.querySelectorAll('.cp-party-row').forEach(function (row) {
            var street = (row.querySelector('.cp-street') || {}).value || '';
            var line2 = '';
            var address = street;
            if (line2) address = [street, line2].filter(Boolean).join(', ');
            var aliases = [];
            row.querySelectorAll('.cp-alias-val').forEach(function (inp) {
                var v = (inp.value || '').trim();
                if (v) aliases.push(v);
            });
            var phones = [];
            row.querySelectorAll('.cp-phone-item').forEach(function (ph) {
                var num = (ph.querySelector('.cp-ph-num') || {}).value || '';
                if (num.trim()) {
                    phones.push({
                        contact_type: (ph.querySelector('.cp-ph-type') || {}).value || 'Mobile',
                        country_code: (ph.querySelector('.cp-ph-cc') || {}).value || '+61',
                        phone: num.trim()
                    });
                }
            });
            var emails = [];
            row.querySelectorAll('.cp-email-item').forEach(function (em) {
                var addr = (em.querySelector('.cp-em-addr') || {}).value || '';
                if (addr.trim()) {
                    emails.push({
                        email_type: (em.querySelector('.cp-em-type') || {}).value || 'Personal',
                        email: addr.trim()
                    });
                }
            });
            parties.push({
                party_type: (row.querySelector('.cp-party-type') || {}).value || 'individual',
                party_role: (row.querySelector('.cp-party-role') || {}).value || null,
                first_name: (row.querySelector('.cp-first-name') || {}).value || '',
                last_name: (row.querySelector('.cp-last-name') || {}).value || '',
                aliases: aliases,
                dob: (row.querySelector('.cp-dob') || {}).value || '',
                company_name: (row.querySelector('.cp-company-name') || {}).value || '',
                trading_name: (row.querySelector('.cp-trading-name') || {}).value || '',
                abn: (row.querySelector('.cp-abn') || {}).value || '',
                acn: (row.querySelector('.cp-acn') || {}).value || '',
                address: address.trim(),
                suburb: (row.querySelector('.cp-suburb') || {}).value || '',
                state: (row.querySelector('.cp-state') || {}).value || '',
                postcode: (row.querySelector('.cp-postcode') || {}).value || '',
                country: (row.querySelector('.cp-country') || {}).value || 'Australia',
                rep_firm_name: (row.querySelector('.cp-rep-firm') || {}).value || '',
                rep_name: (row.querySelector('.cp-rep-name') || {}).value || '',
                rep_email: (row.querySelector('.cp-rep-email') || {}).value || '',
                rep_phone: (row.querySelector('.cp-rep-phone') || {}).value || '',
                rep_country_code: (row.querySelector('.cp-rep-cc') || {}).value || '+61',
                notes: (row.querySelector('.cp-notes') || {}).value || '',
                phones: phones,
                emails: emails
            });
        });
        return parties;
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
            var name;
            if (p.party_type === 'company' || p.party_type === 'government') {
                name = (p.company_name || '').trim() || 'Unnamed company';
            } else {
                name = ((p.first_name || '') + ' ' + (p.last_name || '')).trim() || 'Unnamed';
            }
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
            var id = card.getAttribute('data-client-id');
            var token = document.querySelector('meta[name="csrf-token"]');
            var parties = collectParties();
            var fd = new FormData();
            fd.append('_token', token ? token.getAttribute('content') : '');
            fd.append('id', id);
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
            var id = card.getAttribute('data-client-id');
            var token = document.querySelector('meta[name="csrf-token"]');
            var fd = new FormData();
            fd.append('_token', token ? token.getAttribute('content') : '');
            fd.append('id', id);
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
