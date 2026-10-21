@if($canGrantPersonalCalendar && \Illuminate\Support\Facades\Schema::hasColumn('staff', 'staff_calendar_visibility'))
@php
    $visibility = old('staff_calendar_visibility', $isEdit ? ($fetchedData->staffCalendarVisibility() ?? 'own') : 'own');
    $excludeStaffId = $isEdit && $fetchedData ? (int) $fetchedData->id : 0;
    $pickerStaff = \App\Models\Staff::query()
        ->where('status', 1)
        ->where('role', '!=', 1)
        ->when($excludeStaffId > 0, fn ($q) => $q->where('id', '!=', $excludeStaffId))
        ->orderBy('first_name')
        ->orderBy('last_name')
        ->get(['id', 'first_name', 'last_name']);
    $grantedStaffIds = old('calendar_view_staff_ids');
    $grantedTypes = old('calendar_view_booking_types');
    if ($grantedStaffIds === null && $isEdit && $fetchedData && \Illuminate\Support\Facades\Schema::hasTable('staff_calendar_view_grants')) {
        $grantedStaffIds = $fetchedData->calendarViewGrants()
            ->whereNotNull('visible_staff_id')
            ->pluck('visible_staff_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
    if ($grantedTypes === null && $isEdit && $fetchedData && \Illuminate\Support\Facades\Schema::hasTable('staff_calendar_view_grants')) {
        $grantedTypes = $fetchedData->calendarViewGrants()
            ->whereNotNull('booking_calendar_type')
            ->pluck('booking_calendar_type')
            ->all();
    }
    $grantedStaffIds = is_array($grantedStaffIds) ? array_map('intval', $grantedStaffIds) : [];
    $grantedTypes = is_array($grantedTypes) ? $grantedTypes : [];
    $calendarTypeLabels = \App\Services\StaffPersonalCalendarFeedService::CALENDAR_TYPES;
    $pickerId = $fieldPrefix . '_calendar_visibility_block';
@endphp
<div class="form-group calendar-visibility-block" id="{{ $pickerId }}">
    <label class="d-block mb-2">Other calendars visibility</label>
    <small class="text-muted d-block mb-3">Controls which calendars appear under <strong>Other calendars</strong> on the booking calendar and dashboard. Super Admin always sees every calendar.</small>
    <div class="calendar-visibility-mode mb-3">
        <label class="staff-checkbox-row d-block mb-2">
            <input type="radio" name="staff_calendar_visibility" value="own"
                @checked($visibility === 'own')>
            <span>Own calendar only</span>
        </label>
        <label class="staff-checkbox-row d-block mb-2">
            <input type="radio" name="staff_calendar_visibility" value="all"
                @checked($visibility === 'all')>
            <span>All staff &amp; website booking calendars</span>
        </label>
        <label class="staff-checkbox-row d-block mb-0">
            <input type="radio" name="staff_calendar_visibility" value="assigned"
                @checked($visibility === 'assigned')>
            <span>Selected calendars only</span>
        </label>
    </div>
    <div class="calendar-visibility-assigned" data-calendar-assigned-panel @if($visibility !== 'assigned') hidden @endif>
        <p class="calendar-visibility-summary text-muted small mb-3" data-calendar-summary aria-live="polite"></p>
        <div class="calendar-visibility-grid">
            <div class="calendar-visibility-panel">
                <div class="calendar-visibility-panel-head">
                    <span class="calendar-visibility-panel-title">Staff personal calendars</span>
                    <span class="calendar-visibility-count badge bg-secondary" data-staff-count>0</span>
                </div>
                <div class="calendar-visibility-toolbar">
                    <input type="search"
                           class="form-control form-control-sm"
                           placeholder="Search staff…"
                           autocomplete="off"
                           data-calendar-staff-search
                           aria-label="Search staff to assign">
                    <div class="calendar-visibility-toolbar-actions">
                        <button type="button" class="btn btn-link btn-sm p-0" data-calendar-staff-select-visible>Select visible</button>
                        <span class="text-muted" aria-hidden="true">·</span>
                        <button type="button" class="btn btn-link btn-sm p-0" data-calendar-staff-clear>Clear all</button>
                    </div>
                </div>
                <div class="calendar-visibility-list" data-calendar-staff-list role="group" aria-label="Staff personal calendars">
                    @forelse($pickerStaff as $row)
                        @php
                            $label = trim(($row->first_name ?? '') . ' ' . ($row->last_name ?? '')) ?: ('Staff #' . $row->id);
                            $checked = in_array((int) $row->id, $grantedStaffIds, true);
                        @endphp
                        <label class="calendar-visibility-item staff-checkbox-row"
                               data-calendar-staff-item
                               data-search-text="{{ strtolower($label) }}">
                            <input type="checkbox"
                                   name="calendar_view_staff_ids[]"
                                   value="{{ $row->id }}"
                                   data-calendar-staff-cb
                                   @checked($checked)>
                            <span>{{ $label }}</span>
                        </label>
                    @empty
                        <p class="text-muted small mb-0 px-2 py-1">No other active staff available.</p>
                    @endforelse
                </div>
                <p class="calendar-visibility-empty text-muted small mb-0 mt-2" data-calendar-staff-empty hidden>No staff match your search.</p>
            </div>
            <div class="calendar-visibility-panel">
                <div class="calendar-visibility-panel-head">
                    <span class="calendar-visibility-panel-title">Website booking calendars</span>
                    <span class="calendar-visibility-count badge bg-secondary" data-website-count>0</span>
                </div>
                <div class="calendar-visibility-list calendar-visibility-list--compact" role="group" aria-label="Website booking calendars">
                    @foreach($calendarTypeLabels as $typeKey => $typeLabel)
                        <label class="calendar-visibility-item staff-checkbox-row">
                            <input type="checkbox"
                                   name="calendar_view_booking_types[]"
                                   value="{{ $typeKey }}"
                                   data-calendar-website-cb
                                   @checked(in_array($typeKey, $grantedTypes, true))>
                            <span>{{ $typeLabel }}</span>
                        </label>
                    @endforeach
                </div>
                <small class="text-muted d-block mt-2">Tick any combination of staff and website calendars this user may open.</small>
            </div>
        </div>
    </div>
</div>
<script>
(function () {
    var block = document.getElementById(@json($pickerId));
    if (!block) return;

    var panel = block.querySelector('[data-calendar-assigned-panel]');
    var radios = block.querySelectorAll('input[name="staff_calendar_visibility"]');
    var search = block.querySelector('[data-calendar-staff-search]');
    var staffList = block.querySelector('[data-calendar-staff-list]');
    var staffEmpty = block.querySelector('[data-calendar-staff-empty]');
    var summary = block.querySelector('[data-calendar-summary]');
    var staffCountBadge = block.querySelector('[data-staff-count]');
    var websiteCountBadge = block.querySelector('[data-website-count]');
    var btnSelectVisible = block.querySelector('[data-calendar-staff-select-visible]');
    var btnClearStaff = block.querySelector('[data-calendar-staff-clear]');

    function currentMode() {
        var mode = 'own';
        radios.forEach(function (r) { if (r.checked) mode = r.value; });
        return mode;
    }

    function assignedInputs() {
        return block.querySelectorAll('[data-calendar-staff-cb], [data-calendar-website-cb]');
    }

    function syncAssignedInputsDisabled() {
        var enabled = currentMode() === 'assigned';
        assignedInputs().forEach(function (el) {
            el.disabled = !enabled;
        });
        if (search) search.disabled = !enabled;
        if (btnSelectVisible) btnSelectVisible.disabled = !enabled;
        if (btnClearStaff) btnClearStaff.disabled = !enabled;
    }

    function syncPanel() {
        var assigned = currentMode() === 'assigned';
        if (panel) {
            if (assigned) {
                panel.removeAttribute('hidden');
            } else {
                panel.setAttribute('hidden', 'hidden');
            }
        }
        syncAssignedInputsDisabled();
        updateCounts();
    }

    function visibleStaffItems() {
        if (!staffList) return [];
        return Array.prototype.slice.call(staffList.querySelectorAll('[data-calendar-staff-item]'))
            .filter(function (row) { return !row.hidden && row.style.display !== 'none'; });
    }

    function filterStaff() {
        if (!search || !staffList) return;
        var q = (search.value || '').trim().toLowerCase();
        var items = staffList.querySelectorAll('[data-calendar-staff-item]');
        var visible = 0;
        items.forEach(function (row) {
            var text = row.getAttribute('data-search-text') || '';
            var show = q === '' || text.indexOf(q) !== -1;
            row.style.display = show ? '' : 'none';
            if (show) visible++;
        });
        if (staffEmpty) {
            staffEmpty.hidden = visible > 0 || items.length === 0;
        }
    }

    function updateCounts() {
        var staffChecked = block.querySelectorAll('[data-calendar-staff-cb]:checked').length;
        var webChecked = block.querySelectorAll('[data-calendar-website-cb]:checked').length;
        if (staffCountBadge) staffCountBadge.textContent = String(staffChecked);
        if (websiteCountBadge) websiteCountBadge.textContent = String(webChecked);
        if (summary) {
            if (currentMode() !== 'assigned') {
                summary.textContent = '';
            } else if (staffChecked === 0 && webChecked === 0) {
                summary.textContent = 'Choose at least one staff or website calendar, or switch back to “Own calendar only”.';
            } else {
                var parts = [];
                if (staffChecked) parts.push(staffChecked + ' staff');
                if (webChecked) parts.push(webChecked + ' website');
                summary.textContent = 'Selected: ' + parts.join(' and ') + ' calendar' + (parts.length > 1 ? 's' : '') + '.';
            }
        }
    }

    radios.forEach(function (r) { r.addEventListener('change', syncPanel); });

    block.addEventListener('change', function (e) {
        if (e.target && (e.target.matches('[data-calendar-staff-cb]') || e.target.matches('[data-calendar-website-cb]'))) {
            updateCounts();
        }
    });

    if (search) {
        search.addEventListener('input', filterStaff);
    }

    if (btnSelectVisible) {
        btnSelectVisible.addEventListener('click', function () {
            visibleStaffItems().forEach(function (row) {
                var cb = row.querySelector('[data-calendar-staff-cb]');
                if (cb && !cb.disabled) cb.checked = true;
            });
            updateCounts();
        });
    }

    if (btnClearStaff) {
        btnClearStaff.addEventListener('click', function () {
            block.querySelectorAll('[data-calendar-staff-cb]').forEach(function (cb) {
                if (!cb.disabled) cb.checked = false;
            });
            updateCounts();
        });
    }

    filterStaff();
    syncPanel();
})();
</script>
@endif
