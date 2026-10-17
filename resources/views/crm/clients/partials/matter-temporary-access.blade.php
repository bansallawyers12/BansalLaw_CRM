@php
    $canGrantMatterAccess = \App\Support\MatterScopedAccess::canManageTemporaryGrants();
    $matterId = (int) ($matterTeamOverview['client_matter_id'] ?? 0);
    $durationOptions = config('crm_access.matter_temp_duration_minutes', []);
    $activeMatterGrants = $matterId > 0
        ? \App\Support\MatterScopedAccess::activeTemporaryGrantsForMatter($matterId)
        : collect();
    $appTz = config('app.timezone', 'UTC');
    $activeGrantCount = $activeMatterGrants->count();
    $matterRoleLabels = \App\Support\MatterScopedAccess::matterTeamRoleLabels();
    $teamByKey = collect($matterTeamOverview['team'] ?? [])->keyBy('key');
@endphp
@if($canGrantMatterAccess && $matterId > 0 && $durationOptions !== [])
<section class="cdn-ov-block cdn-ov-matter-temp" id="matterTemporaryAccessSection">
    <div class="cdn-ov-block__head">
        <p class="cdn-ov-section-label mb-0"><i class="fa-solid fa-clock" aria-hidden="true"></i> Temporary matter access</p>
        @if($activeGrantCount > 0)
            <span class="cdn-ov-count" title="Active temporary grants">{{ $activeGrantCount }}</span>
        @endif
    </div>

    <p class="cdn-ov-matter-temp__lede">
        Super Admin assigns a position and staff member. That user can open this client file and work on the selected matter for the duration you choose.
        <span class="cdn-ov-matter-temp__badge">Super Admin only</span>
    </p>

    <div class="cdn-ov-matter-temp__panel">
        @if($activeMatterGrants->isNotEmpty())
            <ul class="cdn-ov-matter-temp__list" id="matterTemporaryAccessList">
                @foreach($activeMatterGrants as $grant)
                    @php
                        $staffMember = $grant->staff;
                        $staffLabel = $staffMember
                            ? trim(($staffMember->first_name ?? '') . ' ' . ($staffMember->last_name ?? ''))
                            : 'Staff #' . $grant->staff_id;
                        $endsLocal = $grant->ends_at
                            ? $grant->ends_at->copy()->timezone($appTz)->format('d/m/Y H:i')
                            : '—';
                        $initials = '';
                        if ($staffMember) {
                            $fn = trim((string) ($staffMember->first_name ?? ''));
                            $ln = trim((string) ($staffMember->last_name ?? ''));
                            $initials = mb_strtoupper(mb_substr($fn, 0, 1) . mb_substr($ln, 0, 1));
                        }
                    @endphp
                    <li class="cdn-ov-matter-temp__item" data-grant-id="{{ $grant->id }}">
                        <span class="cdn-ov-matter-temp__avatar" aria-hidden="true">{{ $initials !== '' ? $initials : '?' }}</span>
                        <div class="cdn-ov-matter-temp__item-body">
                            <span class="cdn-ov-matter-temp__item-name">{{ $staffLabel }}</span>
                            <span class="cdn-ov-matter-temp__item-meta">
                                {{ \App\Support\MatterScopedAccess::matterRoleLabel(\App\Support\MatterScopedAccess::parseRoleFromGrantNote($grant->requester_note)) }}
                                · {{ \App\Support\MatterScopedAccess::durationLabel($grant->quick_reason_code) }}
                                · Expires {{ $endsLocal }}
                            </span>
                        </div>
                        <button type="button"
                                class="cdn-ov-matter-temp__item-remove matter-temp-revoke-btn"
                                data-grant-id="{{ $grant->id }}"
                                data-staff-name="{{ e($staffLabel) }}"
                                title="Remove temporary access">
                            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                            <span>Remove</span>
                        </button>
                    </li>
                @endforeach
            </ul>
        @else
            <div class="cdn-ov-matter-temp__empty" id="matterTemporaryAccessEmpty">
                <i class="fa-regular fa-circle-check" aria-hidden="true"></i>
                <span>No active temporary access</span>
            </div>
        @endif

        <form id="matterTemporaryAccessForm" class="cdn-ov-matter-temp__form">
            @csrf
            <input type="hidden" name="client_matter_id" value="{{ $matterId }}">

            <div class="cdn-ov-matter-temp__field">
                <span class="cdn-ov-matter-temp__label">Position</span>
                <div class="cdn-ov-matter-temp__roles" role="radiogroup" aria-label="Matter position">
                    @foreach($matterRoleLabels as $roleKey => $roleLabel)
                        @php
                            $slot = $teamByKey->get($roleKey);
                            $variant = \App\Support\MatterScopedAccess::matterRoleAvatarVariant($roleKey);
                            $currentName = ($slot && ($slot['status'] ?? '') === 'assigned' && ! empty($slot['name']))
                                ? $slot['name']
                                : null;
                        @endphp
                        <label class="cdn-ov-matter-temp__role cdn-ov-matter-temp__role--{{ $variant }}">
                            <input type="radio"
                                   name="matter_role"
                                   value="{{ $roleKey }}"
                                   class="cdn-ov-matter-temp__role-input"
                                   {{ $loop->first ? 'required' : '' }}
                                   @if($loop->first) checked @endif>
                            <span class="cdn-ov-matter-temp__role-body">
                                <span class="cdn-ov-matter-temp__role-title">{{ $roleLabel }}</span>
                                @if($currentName)
                                    <span class="cdn-ov-matter-temp__role-current">{{ $currentName }}</span>
                                @else
                                    <span class="cdn-ov-matter-temp__role-current cdn-ov-matter-temp__role-current--empty">Unassigned</span>
                                @endif
                            </span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="cdn-ov-matter-temp__field">
                <label class="cdn-ov-matter-temp__label" for="matter_temp_staff_id">Staff member</label>
                <select class="cdn-ov-matter-temp__control" id="matter_temp_staff_id" name="staff_id" required>
                    <option value="">Select staff…</option>
                    @foreach(\App\Models\Staff::query()->where('status', 1)->orderBy('first_name')->orderBy('last_name')->limit(300)->get(['id','first_name','last_name','email']) as $staffRow)
                        <option value="{{ $staffRow->id }}">{{ trim($staffRow->first_name . ' ' . $staffRow->last_name) }}@if($staffRow->email) ({{ $staffRow->email }})@endif</option>
                    @endforeach
                </select>
            </div>

            <div class="cdn-ov-matter-temp__grant-row">
                <div class="cdn-ov-matter-temp__field cdn-ov-matter-temp__field--grow">
                    <label class="cdn-ov-matter-temp__label" for="matter_temp_duration">Duration</label>
                    <select class="cdn-ov-matter-temp__control" id="matter_temp_duration" name="duration" required>
                        @foreach($durationOptions as $durationKey => $minutes)
                            <option value="{{ $durationKey }}">{{ \App\Support\MatterScopedAccess::durationLabel($durationKey) }}</option>
                        @endforeach
                        <option value="permanent">Permanent</option>
                    </select>
                </div>
                <button type="submit" class="cdn-ov-matter-temp__btn cdn-ov-matter-temp__btn--grant" id="matterTemporaryAccessSubmit">
                    <i class="fa-solid fa-key" aria-hidden="true"></i>
                    <span>Grant</span>
                </button>
            </div>

            <div class="cdn-ov-matter-temp__revoke">
                <div class="cdn-ov-matter-temp__field">
                    <label class="cdn-ov-matter-temp__label" for="matter_temp_removal_mode">Removal type</label>
                    <select class="cdn-ov-matter-temp__control" id="matter_temp_removal_mode" name="removal_mode">
                        <option value="temporary">End temporary access</option>
                        <option value="permanent">Remove from position (permanent)</option>
                    </select>
                </div>
                <button type="button" class="cdn-ov-matter-temp__btn cdn-ov-matter-temp__btn--revoke" id="matterTemporaryAccessRevokeStaff">
                    <i class="fa-solid fa-ban" aria-hidden="true"></i>
                    <span id="matterTemporaryAccessRevokeLabel">Remove access</span>
                </button>
                <p class="cdn-ov-matter-temp__hint" id="matterTemporaryAccessRevokeHint">Uses the position and staff selected above.</p>
            </div>
        </form>
    </div>
</section>
@push('scripts')
<script>
(function () {
    var revokeUrl = @json(route('crm.access.matter-temporary.revoke'));
    var revokeStaffUrl = @json(route('crm.access.matter-temporary.revoke-staff'));
    var grantUrl = @json(route('crm.access.matter-temporary'));
    var matterId = @json($matterId);

    function csrfToken() {
        var token = document.querySelector('meta[name="csrf-token"]');
        return token ? token.getAttribute('content') : '';
    }

    function notify(msg) {
        if (typeof crmAlert === 'function') {
            crmAlert(msg);
        } else {
            alert(msg);
        }
    }

    function selectedMatterRole() {
        var checked = document.querySelector('#matterTemporaryAccessForm input[name="matter_role"]:checked');
        return checked ? checked.value : '';
    }

    function selectedRemovalMode() {
        var el = document.getElementById('matter_temp_removal_mode');
        return el ? el.value : 'temporary';
    }

    function syncRemovalUi() {
        var permanent = selectedRemovalMode() === 'permanent';
        var label = document.getElementById('matterTemporaryAccessRevokeLabel');
        var hint = document.getElementById('matterTemporaryAccessRevokeHint');
        if (label) {
            label.textContent = permanent ? 'Remove from position' : 'End temporary access';
        }
        if (hint) {
            hint.textContent = permanent
                ? 'Clears this position for the selected staff member and ends any temporary grant for that role.'
                : 'Revokes temporary access only; permanent team assignments stay until you remove them.';
        }
    }

    var removalModeEl = document.getElementById('matter_temp_removal_mode');
    if (removalModeEl) {
        removalModeEl.addEventListener('change', syncRemovalUi);
        syncRemovalUi();
    }

    function confirmRevoke(staffName) {
        var permanent = selectedRemovalMode() === 'permanent';
        var text = permanent
            ? 'Permanently remove ' + staffName + ' from the selected position on this matter?'
            : 'End temporary access for ' + staffName + '?';
        if (typeof window.crmConfirm === 'function') {
            return window.crmConfirm({
                title: permanent ? 'Remove from position?' : 'End temporary access?',
                text: text,
                icon: 'warning',
                confirmText: permanent ? 'Remove permanently' : 'End access',
                cancelText: 'Cancel',
                confirmColor: '#dc3545'
            });
        }
        return Promise.resolve(window.confirm(text));
    }

    document.querySelectorAll('.matter-temp-revoke-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var grantId = btn.getAttribute('data-grant-id');
            var staffName = btn.getAttribute('data-staff-name') || 'this user';
            if (!grantId) return;

            confirmRevoke(staffName).then(function (ok) {
                if (!ok) return;
                btn.disabled = true;
                var body = new FormData();
                body.append('grant_id', grantId);
                body.append('_token', csrfToken());

                fetch(revokeUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken(),
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: body
                }).then(function (r) {
                    return r.json().then(function (j) { return { ok: r.ok, j: j }; });
                }).then(function (res) {
                    notify(res.j.message || (res.ok ? 'Access removed.' : 'Failed.'));
                    if (res.ok) {
                        window.location.reload();
                    } else {
                        btn.disabled = false;
                    }
                }).catch(function () {
                    notify('Request failed.');
                    btn.disabled = false;
                });
            });
        });
    });

    var revokeStaffBtn = document.getElementById('matterTemporaryAccessRevokeStaff');
    if (revokeStaffBtn) {
        revokeStaffBtn.addEventListener('click', function () {
            var staffSelect = document.getElementById('matter_temp_staff_id');
            var staffId = staffSelect ? staffSelect.value : '';
            var matterRole = selectedMatterRole();
            if (!matterRole) {
                notify('Select a position first.');
                return;
            }
            if (!staffId) {
                notify('Select a staff member first.');
                return;
            }
            var staffName = staffSelect && staffSelect.selectedIndex >= 0
                ? staffSelect.options[staffSelect.selectedIndex].text
                : 'this user';

            confirmRevoke(staffName).then(function (ok) {
                if (!ok) return;
                revokeStaffBtn.disabled = true;
                var body = new FormData();
                body.append('client_matter_id', String(matterId));
                body.append('staff_id', staffId);
                body.append('matter_role', matterRole);
                body.append('removal_mode', selectedRemovalMode());
                body.append('_token', csrfToken());

                fetch(revokeStaffUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken(),
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: body
                }).then(function (r) {
                    return r.json().then(function (j) { return { ok: r.ok, j: j }; });
                }).then(function (res) {
                    notify(res.j.message || (res.ok ? 'Access removed.' : 'Failed.'));
                    if (res.ok) {
                        window.location.reload();
                    } else {
                        revokeStaffBtn.disabled = false;
                    }
                }).catch(function () {
                    notify('Request failed.');
                    revokeStaffBtn.disabled = false;
                });
            });
        });
    }

    var form = document.getElementById('matterTemporaryAccessForm');
    if (!form) return;
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var btn = document.getElementById('matterTemporaryAccessSubmit');
        if (btn) btn.disabled = true;
        var body = new FormData(form);
        fetch(grantUrl, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken(),
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: body
        }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
        .then(function (res) {
            notify(res.j.message || (res.ok ? 'Access granted.' : 'Failed.'));
            if (res.ok) {
                window.location.reload();
            }
        }).catch(function () {
            notify('Request failed.');
        }).finally(function () {
            if (btn) btn.disabled = false;
        });
    });
})();
</script>
@endpush
@endif
