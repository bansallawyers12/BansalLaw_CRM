/**
 * Staff personal calendar widget on the CRM dashboard.
 * Requires window.FullCalendar, window.FullCalendarPlugins (from Vite app.js).
 */
(function () {
    'use strict';

    const CALENDAR_EL_ID = 'staffDashboardCalendar';
    const BOOKING_EVENTS_API = window.dashboardRoutes?.calendarEvents || '/dashboard/calendar-events';
    const STORE_EVENT_API = window.dashboardRoutes?.storeCalendarEvent || '/booking/api/calendar-events';

    function calendarElTz() {
        var el = document.getElementById(CALENDAR_EL_ID);
        return (el && el.getAttribute('data-timezone')) || 'Australia/Melbourne';
    }

    function calendarElTzBookingType() {
        var el = document.getElementById(CALENDAR_EL_ID);
        var type = el && el.getAttribute('data-booking-calendar-type');
        return type === 'ajay' || type === 'kunal' ? type : null;
    }

    function csrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function waitForFullCalendar(callback, maxAttempts) {
        maxAttempts = maxAttempts || 100;
        function ready() {
            return typeof FullCalendar !== 'undefined' && FullCalendar.Calendar &&
                typeof FullCalendarPlugins !== 'undefined';
        }
        if (ready()) {
            callback();
            return;
        }
        var attempts = 0;
        var interval = setInterval(function () {
            attempts++;
            if (ready()) {
                clearInterval(interval);
                callback();
            } else if (attempts >= maxAttempts) {
                clearInterval(interval);
                var el = document.getElementById(CALENDAR_EL_ID);
                if (el) {
                    el.innerHTML = '<div class="alert alert-warning mb-0">Calendar could not load. Please refresh the page.</div>';
                }
            }
        }, 100);
    }

    function formatDetail(value) {
        if (value === null || value === undefined || value === '') return '—';
        return String(value);
    }

    function todayDateStr(tz) {
        try {
            return new Date().toLocaleDateString('en-CA', { timeZone: tz || 'Australia/Melbourne' });
        } catch (e) {
            return new Date().toISOString().slice(0, 10);
        }
    }

    function isPastDateStr(dateStr, tz) {
        if (!dateStr) return false;
        return String(dateStr).slice(0, 10) < todayDateStr(tz);
    }

    function formatEventTime(iso, tz, allDay) {
        if (allDay) return 'All day';
        if (!iso) return '';
        var date = new Date(iso);
        if (isNaN(date.getTime())) return '';
        return date.toLocaleString('en-AU', {
            timeZone: tz || 'Australia/Melbourne',
            hour: 'numeric',
            minute: '2-digit',
            hour12: true,
        });
    }

    function eventTypeKey(props) {
        var kind = String((props && props.event_kind) || '');
        if (kind === 'court_hearing') return 'court';
        if (kind === 'action' || kind === 'matter_deadline') return 'deadline';
        var type = String((props && props.event_type) || 'other');
        if (kind === 'website_booking') return 'meeting';
        if (type === 'court' || type === 'meeting' || type === 'deadline' || type === 'reminder') {
            return type;
        }
        return 'other';
    }

    function eventTypeLabel(props) {
        switch (eventTypeKey(props)) {
            case 'court': return 'Court / Hearing';
            case 'meeting': return 'Meeting';
            case 'deadline': return 'Deadline';
            case 'reminder': return 'Reminder';
            default: return 'Other';
        }
    }

    function escapeHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function buildEventTooltipText(event, tz) {
        var props = event.extendedProps || {};
        var title = event.title || props.title || 'Event';
        var lines = [title];
        var email = String(props.client_email || '').trim();
        if (email) {
            lines.push('<' + email + '>');
        }
        var location = String(props.location || props.court_name || '').trim();
        if (location && title.indexOf(location) === -1) {
            lines.push(location);
        }
        var time = formatEventTime(
            props.appointment_datetime || props.starts_at || event.startStr || event.start,
            tz,
            event.allDay || props.is_all_day
        );
        if (time) {
            lines.push(time);
        }
        return lines.join('\n');
    }

    function getCalendarTooltip() {
        var tip = document.getElementById('dashboardCalTooltip');
        if (tip) return tip;
        tip = document.createElement('div');
        tip.id = 'dashboardCalTooltip';
        tip.className = 'dashboard-cal-tooltip';
        tip.setAttribute('role', 'tooltip');
        document.body.appendChild(tip);
        return tip;
    }

    function showEventTooltip(el, text) {
        var tip = getCalendarTooltip();
        tip.textContent = text;
        tip.style.left = '-9999px';
        tip.style.top = '0px';
        tip.classList.add('is-visible');
        void tip.offsetWidth;

        var rect = el.getBoundingClientRect();
        var tipRect = tip.getBoundingClientRect();
        var left = rect.left + (rect.width / 2) - (tipRect.width / 2);
        var top = rect.top - tipRect.height - 10;
        left = Math.max(8, Math.min(left, window.innerWidth - tipRect.width - 8));
        if (top < 8) {
            top = rect.bottom + 10;
            tip.classList.add('is-below');
        } else {
            tip.classList.remove('is-below');
        }
        tip.style.left = left + 'px';
        tip.style.top = top + 'px';
    }

    function hideEventTooltip() {
        var tip = document.getElementById('dashboardCalTooltip');
        if (tip) {
            tip.classList.remove('is-visible');
            tip.textContent = '';
        }
    }

    function bindEventHoverTitle(el, text) {
        if (!el || !text) return;

        el.setAttribute('data-event-tip', text);
        el.setAttribute('aria-label', text.replace(/\n/g, ', '));
        el.setAttribute('title', text);
    }

    function eventDateKey(iso, tz) {
        if (!iso) return '';
        var date = new Date(iso);
        if (isNaN(date.getTime())) return '';
        try {
            return date.toLocaleDateString('en-CA', { timeZone: tz || 'Australia/Melbourne' });
        } catch (e) {
            return date.toISOString().slice(0, 10);
        }
    }

    function tomorrowDateStr(tz) {
        var parts = todayDateStr(tz).split('-');
        var d = new Date(Date.UTC(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2])));
        d.setUTCDate(d.getUTCDate() + 1);
        return d.toISOString().slice(0, 10);
    }

    function formatDayGroupLabel(dateKey, tz) {
        if (!dateKey) return '';
        if (dateKey === todayDateStr(tz)) return 'Today';
        if (dateKey === tomorrowDateStr(tz)) return 'Tomorrow';
        var d = new Date(dateKey + 'T12:00:00');
        if (isNaN(d.getTime())) return dateKey;
        return d.toLocaleDateString('en-AU', {
            weekday: 'short',
            day: 'numeric',
            month: 'short',
            year: 'numeric',
        });
    }

    function focusUpcomingDate(dateStr) {
        var listEl = document.getElementById('dashboardUpcomingList');
        if (!listEl || !dateStr) return;

        var key = String(dateStr).slice(0, 10);
        listEl.querySelectorAll('.dashboard-upcoming-day.is-focused').forEach(function (el) {
            el.classList.remove('is-focused');
        });

        var dayEl = listEl.querySelector('.dashboard-upcoming-day[data-date="' + key.replace(/"/g, '') + '"]');
        if (!dayEl) {
            ensureUpcomingCoversDate(key);
            return;
        }

        dayEl.classList.add('is-focused');
        listEl.scrollTop += dayEl.getBoundingClientRect().top - listEl.getBoundingClientRect().top;
    }

    var upcomingLazy = {
        tz: null,
        loading: false,
        started: false,
        nextStart: null,
        horizonEnd: null,
        events: [],
        scrollBound: false,
        ensureDate: null,
    };

    function addMonthsToDateStr(dateStr, months) {
        var parts = String(dateStr).split('-');
        var d = new Date(Date.UTC(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2])));
        d.setUTCMonth(d.getUTCMonth() + months);
        return d.toISOString().slice(0, 10);
    }

    function setUpcomingStatus(message, isError) {
        var listEl = document.getElementById('dashboardUpcomingList');
        if (!listEl) return;
        listEl.innerHTML = '<div class="dashboard-upcoming-empty' + (isError ? ' is-error' : '') + '">' +
            escapeHtml(message) + '</div>';
    }

    function setUpcomingLoadingFooter(visible) {
        var listEl = document.getElementById('dashboardUpcomingList');
        if (!listEl) return;
        var existing = listEl.querySelector('.dashboard-upcoming-lazy-footer');
        if (!visible) {
            if (existing) existing.remove();
            return;
        }
        if (existing) return;
        var footer = document.createElement('div');
        footer.className = 'dashboard-upcoming-lazy-footer';
        footer.innerHTML = '<span class="dashboard-upcoming-lazy-spinner" aria-hidden="true"></span> Loading more…';
        listEl.appendChild(footer);
    }

    function updateUpcomingCount() {
        var countEl = document.getElementById('dashboardUpcomingCount');
        if (!countEl) return;
        var loaded = upcomingLazy.events.length;
        var hasMore = !!(upcomingLazy.nextStart && upcomingLazy.horizonEnd &&
            upcomingLazy.nextStart < upcomingLazy.horizonEnd);
        countEl.textContent = hasMore ? (loaded + '+') : String(loaded);
        countEl.title = hasMore
            ? 'Loaded so far — scroll the schedule to load more'
            : 'Total upcoming items';
    }

    function agendaDisplayKey(event, tz) {
        var props = event.extendedProps || {};
        var start = event.start || props.starts_at || props.appointment_datetime;
        var allDay = !!(event.allDay || props.is_all_day);
        var timeKey = allDay ? 'all-day' : formatEventTime(start, tz, false);
        var typeKey = eventTypeKey(props);
        var title = String(event.title || props.title || 'Event').trim().toLowerCase();

        return typeKey + '|' + timeKey + '|' + title;
    }

    function clusterAgendaEntries(items, tz) {
        var clusters = [];
        var map = {};

        items.forEach(function (entry) {
            var key = agendaDisplayKey(entry.event, tz);
            if (!map[key]) {
                map[key] = { entries: [] };
                clusters.push(map[key]);
            }
            map[key].entries.push(entry);
        });

        return clusters;
    }

    function renderUpcomingList(events, tz) {
        var listEl = document.getElementById('dashboardUpcomingList');
        if (!listEl) return;

        var rows = (events || []).slice().sort(function (a, b) {
            return String(a.start || '').localeCompare(String(b.start || ''));
        });

        updateUpcomingCount();

        if (!rows.length) {
            var waiting = !!(upcomingLazy.nextStart && upcomingLazy.horizonEnd &&
                upcomingLazy.nextStart < upcomingLazy.horizonEnd);
            listEl.innerHTML = waiting
                ? '<div class="dashboard-upcoming-empty">No items in this period. Scroll or wait for more dates…</div>'
                : '<div class="dashboard-upcoming-empty">No upcoming hearings or events.</div>';
            return;
        }

        var groups = {};
        var groupOrder = [];
        var flatIndex = 0;

        rows.forEach(function (event) {
            var props = event.extendedProps || {};
            var start = event.start || props.starts_at || props.appointment_datetime;
            var dateKey = eventDateKey(start, tz) || 'unknown';
            if (!groups[dateKey]) {
                groups[dateKey] = [];
                groupOrder.push(dateKey);
            }
            groups[dateKey].push({ event: event, index: flatIndex++ });
        });

        var html = '<div class="dashboard-upcoming-agenda">';
        groupOrder.forEach(function (dateKey) {
            var items = groups[dateKey];
            var clusters = clusterAgendaEntries(items, tz);
            html += '<div class="dashboard-upcoming-day" data-date="' + escapeHtml(dateKey) + '" id="upcoming-day-' + escapeHtml(dateKey) + '">' +
                '<div class="dashboard-upcoming-day-header">' +
                '<span>' + escapeHtml(formatDayGroupLabel(dateKey, tz)) + '</span>' +
                '<span class="dashboard-upcoming-day-count">' + clusters.length + '</span>' +
                '</div><ul class="dashboard-upcoming-day-items">';

            clusters.forEach(function (cluster) {
                var entry = cluster.entries[0];
                var event = entry.event;
                var props = event.extendedProps || {};
                var typeKey = eventTypeKey(props);
                var title = event.title || props.title || 'Event';
                var start = event.start || props.starts_at || props.appointment_datetime;
                var groupCount = cluster.entries.length;
                var tip = title;
                var email = String(props.client_email || '').trim();
                if (email) tip += '\n<' + email + '>';
                var when = formatEventTime(start, tz, event.allDay || props.is_all_day);
                if (when) tip += '\n' + when;
                if (groupCount > 1) {
                    tip += '\n' + groupCount + ' similar items';
                }

                html += '<li class="dashboard-upcoming-item' + (groupCount > 1 ? ' is-grouped' : '') +
                    '" data-upcoming-index="' + entry.index + '" title="' +
                    escapeHtml(tip).replace(/\n/g, ' — ') + '">' +
                    '<span class="dashboard-upcoming-item-time">' +
                    escapeHtml(formatEventTime(start, tz, event.allDay || props.is_all_day)) +
                    '</span>' +
                    '<div class="dashboard-upcoming-item-body">' +
                    '<div class="dashboard-upcoming-item-meta">' +
                    '<span class="dashboard-upcoming-type dashboard-upcoming-type--' + typeKey + '">' +
                    escapeHtml(eventTypeLabel(props)) + '</span>' +
                    (groupCount > 1
                        ? '<span class="dashboard-upcoming-group-count" title="' + groupCount + ' similar items">' +
                            escapeHtml(String(groupCount)) + '+</span>'
                        : '') +
                    '</div>' +
                    '<div class="dashboard-upcoming-title">' + escapeHtml(title) + '</div>' +
                    '</div></li>';
            });

            html += '</ul></div>';
        });
        html += '</div>';
        listEl.innerHTML = html;

        listEl.querySelectorAll('.dashboard-upcoming-item').forEach(function (row) {
            row.addEventListener('click', function () {
                var event = rows[Number(row.getAttribute('data-upcoming-index'))];
                if (!event) return;
                var props = Object.assign({
                    title: event.title,
                    starts_at: event.start,
                    is_all_day: event.allDay,
                }, event.extendedProps || {});
                showEventDetail(props);
            });
        });

        if (upcomingLazy.ensureDate) {
            var target = upcomingLazy.ensureDate;
            upcomingLazy.ensureDate = null;
            window.requestAnimationFrame(function () {
                focusUpcomingDate(target);
            });
        }
    }

    function eventDedupKey(event) {
        if (!event) return '';
        var props = event.extendedProps || {};
        if (props.booking_appointment_id) {
            return 'booking:' + props.booking_appointment_id;
        }
        if (props.staff_calendar_event_id) {
            return 'staff:' + props.staff_calendar_event_id;
        }
        if (props.court_hearing_id) {
            return 'court:' + props.court_hearing_id;
        }
        if (event.id !== undefined && event.id !== null && event.id !== '') {
            return String(event.id);
        }
        return [
            event.title || props.title || '',
            event.start || props.starts_at || props.appointment_datetime || '',
            props.event_kind || props.event_type || '',
        ].join('|');
    }

    function mergeUpcomingEvents(existing, incoming) {
        var map = {};
        existing.forEach(function (event) {
            map[eventDedupKey(event)] = event;
        });
        incoming.forEach(function (event) {
            map[eventDedupKey(event)] = event;
        });
        return Object.keys(map).map(function (key) { return map[key]; });
    }

    function hasMoreUpcomingChunks() {
        return !!(upcomingLazy.nextStart && upcomingLazy.horizonEnd &&
            upcomingLazy.nextStart < upcomingLazy.horizonEnd);
    }

    async function loadUpcomingChunk() {
        if (upcomingLazy.loading || !hasMoreUpcomingChunks()) {
            setUpcomingLoadingFooter(false);
            return;
        }

        var tz = upcomingLazy.tz || calendarElTz();
        var listEl = document.getElementById('dashboardUpcomingList');
        var chunkStart = upcomingLazy.nextStart;
        var chunkEnd = addMonthsToDateStr(chunkStart, 1);
        if (chunkEnd > upcomingLazy.horizonEnd) {
            chunkEnd = upcomingLazy.horizonEnd;
        }
        if (chunkStart >= chunkEnd) {
            upcomingLazy.nextStart = upcomingLazy.horizonEnd;
            updateUpcomingCount();
            setUpcomingLoadingFooter(false);
            return;
        }

        upcomingLazy.loading = true;
        setUpcomingLoadingFooter(true);

        try {
            var url = new URL(BOOKING_EVENTS_API, window.location.origin);
            url.searchParams.set('start', chunkStart + 'T00:00:00');
            url.searchParams.set('end', chunkEnd + 'T00:00:00');
            url.searchParams.set('include_stats', '0');

            var response = await fetch(url.toString(), {
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            var payload = await response.json();
            if (!response.ok || !payload.success) {
                throw new Error(payload.message || 'Failed to load upcoming items');
            }

            var incoming = (payload.data || []).filter(function (event) {
                var start = event.start || (event.extendedProps && (event.extendedProps.starts_at || event.extendedProps.appointment_datetime));
                var dateKey = eventDateKey(start, tz);
                return dateKey && dateKey >= chunkStart && dateKey < chunkEnd;
            });

            upcomingLazy.events = mergeUpcomingEvents(upcomingLazy.events, incoming);
            upcomingLazy.nextStart = chunkEnd;
            renderUpcomingList(upcomingLazy.events, tz);

            if (upcomingLazy.ensureDate && upcomingLazy.ensureDate >= chunkEnd && hasMoreUpcomingChunks()) {
                upcomingLazy.loading = false;
                setUpcomingLoadingFooter(false);
                return loadUpcomingChunk();
            }
        } catch (err) {
            console.error('Dashboard upcoming list error:', err);
            if (!upcomingLazy.events.length) {
                setUpcomingStatus('Could not load the upcoming schedule.', true);
            }
        } finally {
            upcomingLazy.loading = false;
            setUpcomingLoadingFooter(false);
            updateUpcomingCount();
        }
    }

    function onUpcomingListScroll() {
        var listEl = document.getElementById('dashboardUpcomingList');
        if (!listEl || upcomingLazy.loading || !hasMoreUpcomingChunks()) return;
        if (listEl.scrollTop + listEl.clientHeight >= listEl.scrollHeight - 140) {
            loadUpcomingChunk();
        }
    }

    function ensureUpcomingCoversDate(dateKey) {
        if (!dateKey || dateKey === 'unknown') return;
        // Already fetched through this date — no day group means nothing scheduled then.
        if (upcomingLazy.nextStart && dateKey < upcomingLazy.nextStart) {
            return;
        }
        upcomingLazy.ensureDate = dateKey;
        if (!upcomingLazy.started) {
            initUpcomingLazyLoad(upcomingLazy.tz || calendarElTz(), true);
            return;
        }
        if (!upcomingLazy.loading && hasMoreUpcomingChunks()) {
            loadUpcomingChunk();
        }
    }

    function resetUpcomingLazyState(tz) {
        var horizon = new Date();
        horizon.setMonth(horizon.getMonth() + 12);
        upcomingLazy.tz = tz || calendarElTz();
        upcomingLazy.loading = false;
        upcomingLazy.started = true;
        upcomingLazy.nextStart = todayDateStr(upcomingLazy.tz);
        upcomingLazy.horizonEnd = horizon.toISOString().slice(0, 10);
        upcomingLazy.events = [];
    }

    function bindUpcomingLazyScroll() {
        var listEl = document.getElementById('dashboardUpcomingList');
        if (!listEl || upcomingLazy.scrollBound) return;
        listEl.addEventListener('scroll', onUpcomingListScroll, { passive: true });
        upcomingLazy.scrollBound = true;
    }

    function initUpcomingLazyLoad(tz, immediate) {
        resetUpcomingLazyState(tz);
        bindUpcomingLazyScroll();
        setUpcomingStatus('Loading schedule…');
        updateUpcomingCount();

        var start = function () {
            loadUpcomingChunk();
        };

        if (immediate) {
            start();
            return;
        }

        // Let the calendar request win first so schedule never blocks dashboard paint.
        if (typeof window.requestIdleCallback === 'function') {
            window.requestIdleCallback(start, { timeout: 1800 });
        } else {
            window.setTimeout(start, 350);
        }
    }

    function refreshUpcomingList(tz) {
        initUpcomingLazyLoad(tz || calendarElTz(), true);
    }
    window.refreshUpcomingList = refreshUpcomingList;

    function showEventDetail(props) {
        var titleEl = document.getElementById('personalEventDetailTitle');
        var bodyEl = document.getElementById('personalEventDetailBody');
        var footerEl = document.getElementById('personalEventDetailFooter');
        if (!titleEl || !bodyEl || !footerEl) return;

        titleEl.textContent = props.title || 'Event Details';

            var rows = [
            ['Type', props.status_label || props.event_type],
            ['Client', props.client_name],
            ['Email', props.client_email],
            ['When', formatEventTime(props.appointment_datetime || props.starts_at, calendarElTz(), props.is_all_day)],
            ['Location', props.location],
            ['Notes', props.notes],
        ];

        var html = '<dl class="dashboard-event-detail-list">';
        rows.forEach(function (row) {
            if (row[1]) {
                html += '<dt>' + row[0] + '</dt><dd>' + formatDetail(row[1]) + '</dd>';
            }
        });
        html += '</dl>';

        bodyEl.innerHTML = html;

        var clientLink = '';
        if (props.client_id_encoded) {
            clientLink = '<a href="/clients/detail/' + props.client_id_encoded + '" class="btn btn-outline-primary btn-sm"><i class="fa-solid fa-user"></i> Open Client</a> ';
        }
        if (props.action_url) {
            clientLink += '<a href="' + props.action_url + '" class="btn btn-outline-primary btn-sm"><i class="fa-solid fa-list-check"></i> View Tasks</a> ';
        }

        footerEl.innerHTML = clientLink +
            '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>';

        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            bootstrap.Modal.getOrCreateInstance(document.getElementById('personalEventDetailModal')).show();
        } else if (typeof $ !== 'undefined') {
            $('#personalEventDetailModal').modal('show');
        }
    }

    function updateStats(stats) {
        if (!stats) return;
        var today = document.getElementById('calStatToday');
        var week = document.getElementById('calStatWeek');
        var overdue = document.getElementById('calStatOverdue');
        if (today) today.textContent = stats.today ?? 0;
        if (week) week.textContent = stats.this_week ?? 0;
        if (overdue) overdue.textContent = stats.overdue_actions ?? 0;
    }

    function initPersonalEventModal(calendar) {
        var BUSINESS_START = '09:00';
        var BUSINESS_END = '18:00';
        var addBtn = document.getElementById('btnAddPersonalEvent');
        var saveBtn = document.getElementById('personalEventSaveBtn');
        var allDayEl = document.getElementById('personalEventAllDay');
        var modalEl = document.getElementById('personalEventModal');
        var startTimeEl = document.getElementById('personalEventStartTime');
        var endTimeEl = document.getElementById('personalEventEndTime');
        var timeRow = document.getElementById('personalEventTimeRow');
        var typeInput = document.getElementById('personalEventType');
        var typeChips = document.getElementById('personalEventTypeChips');
        var durationChips = document.getElementById('personalEventDurationChips');
        var dateInput = document.getElementById('personalEventDate');
        var titleInput = document.getElementById('personalEventTitle');
        var summaryEl = document.getElementById('personalEventSummary');
        var summaryTextEl = document.getElementById('personalEventSummaryText');
        var selectedDurationMinutes = 60;

        if (!addBtn || !saveBtn || !modalEl) return;

        function timeToMinutes(value) {
            var parts = String(value || '').split(':');
            var h = parseInt(parts[0], 10);
            var m = parseInt(parts[1], 10);
            if (isNaN(h) || isNaN(m)) {
                return null;
            }
            return (h * 60) + m;
        }

        function minutesToTime(total) {
            var h = Math.floor(total / 60);
            var m = total % 60;
            return String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0');
        }

        function formatDisplayTime(value) {
            var mins = timeToMinutes(value);
            if (mins === null) {
                return value;
            }
            var h = Math.floor(mins / 60);
            var m = mins % 60;
            var suffix = h >= 12 ? 'PM' : 'AM';
            var hour12 = h % 12;
            if (hour12 === 0) hour12 = 12;
            return hour12 + ':' + String(m).padStart(2, '0') + ' ' + suffix;
        }

        function formatDisplayDate(value) {
            if (!value) {
                return 'Pick a date';
            }
            try {
                var d = new Date(value + 'T12:00:00');
                return d.toLocaleDateString(undefined, {
                    weekday: 'short',
                    day: 'numeric',
                    month: 'short',
                    year: 'numeric',
                });
            } catch (e) {
                return value;
            }
        }

        function clampTime(value, minValue, maxValue) {
            var mins = timeToMinutes(value);
            var minMins = timeToMinutes(minValue);
            var maxMins = timeToMinutes(maxValue);
            if (mins === null) {
                return minValue;
            }
            if (mins < minMins) {
                return minValue;
            }
            if (mins > maxMins) {
                return maxValue;
            }
            return value.length === 5 ? value : value.slice(0, 5);
        }

        function setActiveChip(group, attr, value) {
            if (!group) {
                return;
            }
            group.querySelectorAll('.pe-modal__chip').forEach(function (chip) {
                chip.classList.toggle('is-active', chip.getAttribute(attr) === String(value));
            });
        }

        function applyDurationFromStart(minutes) {
            if (!startTimeEl || !endTimeEl || (allDayEl && allDayEl.checked)) {
                return;
            }
            selectedDurationMinutes = minutes;
            setActiveChip(durationChips, 'data-minutes', minutes);
            var startMins = timeToMinutes(clampTime(startTimeEl.value || BUSINESS_START, BUSINESS_START, '17:45'));
            if (startMins === null) {
                return;
            }
            var endMins = Math.min(startMins + minutes, 18 * 60);
            if (endMins <= startMins) {
                endMins = Math.min(startMins + 15, 18 * 60);
            }
            startTimeEl.value = minutesToTime(startMins);
            endTimeEl.value = minutesToTime(endMins);
            syncTimeBounds(false);
            updateSummary();
        }

        function syncTimeBounds(updateDurationChip) {
            if (!startTimeEl || !endTimeEl) {
                return;
            }
            startTimeEl.value = clampTime(startTimeEl.value || BUSINESS_START, BUSINESS_START, '17:45');
            var startMins = timeToMinutes(startTimeEl.value);
            var minEndMins = Math.min((startMins || (9 * 60)) + 15, 18 * 60);
            var minEnd = minutesToTime(minEndMins);
            endTimeEl.min = minEnd;
            endTimeEl.value = clampTime(endTimeEl.value || '10:00', minEnd, BUSINESS_END);

            if (updateDurationChip !== false && durationChips) {
                var endMins = timeToMinutes(endTimeEl.value);
                var duration = (endMins || 0) - (startMins || 0);
                selectedDurationMinutes = duration;
                var matched = false;
                durationChips.querySelectorAll('.pe-modal__chip').forEach(function (chip) {
                    var mins = parseInt(chip.getAttribute('data-minutes'), 10);
                    var active = mins === duration;
                    chip.classList.toggle('is-active', active);
                    if (active) matched = true;
                });
                if (!matched) {
                    durationChips.querySelectorAll('.pe-modal__chip').forEach(function (chip) {
                        chip.classList.remove('is-active');
                    });
                }
            }
            updateSummary();
        }

        function setTimeInputsEnabled(enabled) {
            if (startTimeEl) startTimeEl.disabled = !enabled;
            if (endTimeEl) endTimeEl.disabled = !enabled;
            if (timeRow) timeRow.classList.toggle('is-disabled', !enabled);
            if (durationChips) durationChips.classList.toggle('is-disabled', !enabled);
            updateSummary();
        }

        function updateSummary() {
            if (!summaryTextEl) {
                return;
            }
            var dateLabel = formatDisplayDate(dateInput ? dateInput.value : '');
            var typeLabel = (typeInput && typeInput.value)
                ? typeInput.value.charAt(0).toUpperCase() + typeInput.value.slice(1)
                : 'Meeting';
            var text;

            if (allDayEl && allDayEl.checked) {
                text = dateLabel + ' · All day · ' + typeLabel;
            } else {
                var start = startTimeEl ? startTimeEl.value : BUSINESS_START;
                var end = endTimeEl ? endTimeEl.value : '10:00';
                var startMins = timeToMinutes(start);
                var endMins = timeToMinutes(end);
                var durationMins = (startMins !== null && endMins !== null) ? Math.max(endMins - startMins, 0) : 0;
                var durationLabel = durationMins >= 60
                    ? ((durationMins % 60 === 0)
                        ? (durationMins / 60) + 'h'
                        : Math.floor(durationMins / 60) + 'h ' + (durationMins % 60) + 'm')
                    : durationMins + 'm';
                text = dateLabel + ' · ' + formatDisplayTime(start) + ' – ' + formatDisplayTime(end) + ' · ' + durationLabel + ' · ' + typeLabel;
            }

            summaryTextEl.textContent = text;
            if (summaryEl) {
                summaryEl.classList.toggle('is-ready', !!(dateInput && dateInput.value));
            }
        }

        function showError(message) {
            var errorEl = document.getElementById('personalEventError');
            if (!errorEl) {
                return;
            }
            errorEl.textContent = message;
            errorEl.classList.remove('d-none');
            if (titleInput && !titleInput.value.trim()) {
                titleInput.classList.add('is-invalid');
            }
            if (dateInput && !dateInput.value) {
                dateInput.classList.add('is-invalid');
            }
        }

        function clearError() {
            var errorEl = document.getElementById('personalEventError');
            if (!errorEl) {
                return;
            }
            errorEl.textContent = '';
            errorEl.classList.add('d-none');
            if (titleInput) titleInput.classList.remove('is-invalid');
            if (dateInput) dateInput.classList.remove('is-invalid');
        }

        function openModal() {
            if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                bootstrap.Modal.getOrCreateInstance(modalEl).show();
            } else if (typeof $ !== 'undefined') {
                $(modalEl).modal('show');
            }
            window.setTimeout(function () {
                if (titleInput) titleInput.focus();
            }, 180);
        }

        function resetForm(preferredDate) {
            var today = todayDateStr(calendarElTz());
            if (titleInput) titleInput.value = '';
            if (typeInput) typeInput.value = 'meeting';
            setActiveChip(typeChips, 'data-type', 'meeting');
            if (dateInput) {
                dateInput.value = preferredDate || today;
                dateInput.min = today;
            }
            if (startTimeEl) startTimeEl.value = BUSINESS_START;
            if (endTimeEl) endTimeEl.value = '10:00';
            if (allDayEl) allDayEl.checked = false;
            document.getElementById('personalEventLocation').value = '';
            document.getElementById('personalEventNotes').value = '';
            selectedDurationMinutes = 60;
            setActiveChip(durationChips, 'data-minutes', 60);
            clearError();
            setTimeInputsEnabled(true);
            syncTimeBounds();
            updateSummary();
        }

        addBtn.addEventListener('click', function () {
            resetForm();
            openModal();
        });

        if (typeChips) {
            typeChips.addEventListener('click', function (e) {
                var chip = e.target.closest('.pe-modal__chip');
                if (!chip) return;
                var type = chip.getAttribute('data-type');
                if (!type || !typeInput) return;
                typeInput.value = type;
                setActiveChip(typeChips, 'data-type', type);
                updateSummary();
            });
        }

        if (durationChips) {
            durationChips.addEventListener('click', function (e) {
                var chip = e.target.closest('.pe-modal__chip');
                if (!chip || (allDayEl && allDayEl.checked)) return;
                var minutes = parseInt(chip.getAttribute('data-minutes'), 10);
                if (!minutes) return;
                applyDurationFromStart(minutes);
            });
        }

        if (startTimeEl) {
            startTimeEl.addEventListener('change', function () {
                syncTimeBounds();
                if (selectedDurationMinutes) {
                    applyDurationFromStart(selectedDurationMinutes);
                }
            });
        }
        if (endTimeEl) {
            endTimeEl.addEventListener('change', function () {
                syncTimeBounds();
            });
        }
        if (dateInput) {
            dateInput.addEventListener('change', updateSummary);
        }
        if (titleInput) {
            titleInput.addEventListener('input', function () {
                titleInput.classList.remove('is-invalid');
            });
        }

        if (allDayEl) {
            allDayEl.addEventListener('change', function () {
                setTimeInputsEnabled(!allDayEl.checked);
            });
        }

        modalEl.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && e.target && e.target.tagName !== 'TEXTAREA') {
                e.preventDefault();
                saveBtn.click();
            }
        });

        saveBtn.addEventListener('click', async function () {
            var title = titleInput ? titleInput.value.trim() : '';
            var date = dateInput ? dateInput.value : '';
            var allDay = allDayEl && allDayEl.checked;
            var startTime = BUSINESS_START;
            var endTime = BUSINESS_END;

            clearError();

            if (!title || !date) {
                showError('Title and date are required.');
                return;
            }

            if (isPastDateStr(date, calendarElTz())) {
                showError('Please choose today or a future date.');
                return;
            }

            if (!allDay) {
                syncTimeBounds();
                startTime = clampTime(startTimeEl.value || BUSINESS_START, BUSINESS_START, '17:45');
                endTime = clampTime(endTimeEl.value || '10:00', '09:15', BUSINESS_END);
                var startMins = timeToMinutes(startTime);
                var endMins = timeToMinutes(endTime);

                if (startMins === null || endMins === null) {
                    showError('Please choose a valid start and end time.');
                    return;
                }
                if (startMins < (9 * 60) || endMins > (18 * 60)) {
                    showError('Events can only be booked between 9:00 AM and 6:00 PM.');
                    return;
                }
                if (endMins <= startMins) {
                    showError('End time must be after start time.');
                    return;
                }
            }

            var startsAt = date + 'T' + startTime + ':00';
            var endsAt = date + 'T' + endTime + ':00';

            saveBtn.disabled = true;
            saveBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i> Saving…';

            try {
                var response = await fetch(STORE_EVENT_API, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({
                        title: title,
                        event_type: typeInput ? typeInput.value : 'meeting',
                        starts_at: startsAt,
                        ends_at: endsAt,
                        is_all_day: allDay,
                        location: document.getElementById('personalEventLocation').value.trim() || null,
                        notes: document.getElementById('personalEventNotes').value.trim() || null,
                        calendar_type: calendarElTzBookingType(),
                    }),
                });

                var data = await response.json().catch(function () { return {}; });
                if (!response.ok || !data.success) {
                    throw new Error(data.message || 'Could not save event.');
                }

                if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                    bootstrap.Modal.getOrCreateInstance(modalEl).hide();
                } else if (typeof $ !== 'undefined') {
                    $(modalEl).modal('hide');
                }

                calendar.refetchEvents();
                refreshUpcomingList(calendarElTz());

                if (typeof window.showToast === 'function') {
                    window.showToast('Event saved to your calendar.', 'success');
                }
            } catch (err) {
                showError(err.message || 'Could not save event.');
            } finally {
                saveBtn.disabled = false;
                saveBtn.innerHTML = '<i class="fa-solid fa-check" aria-hidden="true"></i> Save event';
            }
        });
    }

    function initDashboardCalendar() {
        var calendarEl = document.getElementById(CALENDAR_EL_ID);
        if (!calendarEl) return;

        var tz = calendarEl.getAttribute('data-timezone') || 'Australia/Melbourne';

        waitForFullCalendar(function () {
            var calendar = new FullCalendar.Calendar(calendarEl, {
                plugins: [
                    FullCalendarPlugins.dayGridPlugin,
                    FullCalendarPlugins.timeGridPlugin,
                    FullCalendarPlugins.interactionPlugin,
                    FullCalendarPlugins.listPlugin,
                ],
                initialView: 'dayGridMonth',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek',
                },
                buttonText: {
                    today: 'Today',
                    month: 'Month',
                    week: 'Week',
                    day: 'Day',
                    list: 'List',
                },
                height: '100%',
                expandRows: true,
                timeZone: tz,
                firstDay: 1,
                slotMinTime: '09:00:00',
                slotMaxTime: '18:00:00',
                businessHours: {
                    daysOfWeek: [1, 2, 3, 4, 5],
                    startTime: '09:00',
                    endTime: '18:00',
                },
                nowIndicator: true,
                navLinks: true,
                eventDisplay: 'block',
                displayEventTime: true,
                displayEventEnd: false,
                dayMaxEvents: 3,
                moreLinkClick: 'popover',
                eventTimeFormat: {
                    hour: 'numeric',
                    minute: '2-digit',
                    meridiem: 'short',
                },
                validRange: function (nowDate) {
                    var start = new Date(nowDate.valueOf());
                    start.setHours(0, 0, 0, 0);
                    return { start: start };
                },
                dayCellClassNames: function (arg) {
                    if (arg.isPast) {
                        return ['dashboard-cal-day-past'];
                    }
                    return [];
                },
                events: async function (fetchInfo, successCallback, failureCallback) {
                    try {
                        var url = new URL(BOOKING_EVENTS_API, window.location.origin);
                        url.searchParams.set('start', fetchInfo.startStr);
                        url.searchParams.set('end', fetchInfo.endStr);
                        url.searchParams.set('include_stats', '1');

                        var response = await fetch(url.toString(), {
                            credentials: 'same-origin',
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                        });

                        var payload = await response.json();
                        if (!response.ok || !payload.success) {
                            throw new Error(payload.message || 'Failed to load events');
                        }

                        updateStats(payload.stats);
                        successCallback(payload.data || []);
                    } catch (err) {
                        console.error('Dashboard calendar feed error:', err);
                        failureCallback(err);
                    }
                },
                eventDidMount: function (info) {
                    bindEventHoverTitle(info.el, buildEventTooltipText(info.event, tz));
                },
                eventClick: function (info) {
                    info.jsEvent.preventDefault();
                    hideEventTooltip();
                    var props = info.event.extendedProps || {};
                    var startStr = info.event.startStr || (info.event.start ? info.event.start.toISOString() : '');
                    focusUpcomingDate(startStr);
                    showEventDetail(Object.assign({
                        title: info.event.title,
                        starts_at: startStr,
                        is_all_day: info.event.allDay,
                    }, props));
                },
                dateClick: function (info) {
                    focusUpcomingDate(info.dateStr);
                    if (isPastDateStr(info.dateStr, tz)) {
                        return;
                    }
                    var dateInput = document.getElementById('personalEventDate');
                    if (dateInput) {
                        dateInput.value = info.dateStr;
                    }
                },
                datesSet: function () {
                    hideEventTooltip();
                },
            });

            calendar.render();
            initPersonalEventModal(calendar);
            initUpcomingLazyLoad(tz);
            window.staffDashboardCalendar = calendar;
            window.requestAnimationFrame(function () {
                calendar.updateSize();
            });
            document.addEventListener('scroll', hideEventTooltip, true);
            window.addEventListener('resize', function () {
                hideEventTooltip();
                calendar.updateSize();
            });

            calendarEl.addEventListener('mouseover', function (e) {
                var eventEl = e.target.closest('.fc-event');
                if (!eventEl || !calendarEl.contains(eventEl)) return;
                var text = eventEl.getAttribute('data-event-tip') || eventEl.getAttribute('title');
                if (!text) return;
                eventEl.removeAttribute('title');
                showEventTooltip(eventEl, text);
            });
            calendarEl.addEventListener('mouseout', function (e) {
                var eventEl = e.target.closest('.fc-event');
                if (!eventEl) return;
                if (e.relatedTarget && eventEl.contains(e.relatedTarget)) return;
                var text = eventEl.getAttribute('data-event-tip');
                if (text && !eventEl.getAttribute('title')) {
                    eventEl.setAttribute('title', text);
                }
                hideEventTooltip();
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initDashboardCalendar);
    } else {
        initDashboardCalendar();
    }
})();
