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

    function formatEventDate(iso, tz) {
        if (!iso) return '';
        var date = new Date(iso);
        if (isNaN(date.getTime())) return '';
        return date.toLocaleDateString('en-AU', {
            timeZone: tz || 'Australia/Melbourne',
            weekday: 'short',
            day: 'numeric',
            month: 'short',
            year: 'numeric',
        });
    }

    function renderUpcomingList(events, tz) {
        var listEl = document.getElementById('dashboardUpcomingList');
        var countEl = document.getElementById('dashboardUpcomingCount');
        if (!listEl) return;

        var rows = (events || []).slice().sort(function (a, b) {
            return String(a.start || '').localeCompare(String(b.start || ''));
        });

        if (countEl) countEl.textContent = String(rows.length);

        if (!rows.length) {
            listEl.innerHTML = '<div class="dashboard-upcoming-empty">No upcoming hearings or events.</div>';
            return;
        }

        var html = '<table class="dashboard-upcoming-table"><thead><tr>' +
            '<th>Date</th><th>Time</th><th>Type</th><th>Title</th>' +
            '</tr></thead><tbody>';

        rows.forEach(function (event, index) {
            var props = event.extendedProps || {};
            var typeKey = eventTypeKey(props);
            var title = event.title || props.title || 'Event';
            var start = event.start || props.starts_at || props.appointment_datetime;
            var tip = title;
            var email = String(props.client_email || '').trim();
            if (email) tip += '\n<' + email + '>';
            var when = formatEventTime(start, tz, event.allDay || props.is_all_day);
            if (when) tip += '\n' + when;
            html += '<tr class="dashboard-upcoming-row" data-upcoming-index="' + index + '" title="' + escapeHtml(tip).replace(/\n/g, ' — ') + '">' +
                '<td>' + escapeHtml(formatEventDate(start, tz)) + '</td>' +
                '<td>' + escapeHtml(formatEventTime(start, tz, event.allDay || props.is_all_day)) + '</td>' +
                '<td><span class="dashboard-upcoming-type dashboard-upcoming-type--' + typeKey + '">' +
                escapeHtml(eventTypeLabel(props)) + '</span></td>' +
                '<td class="dashboard-upcoming-title">' + escapeHtml(title) + '</td>' +
                '</tr>';
        });

        html += '</tbody></table>';
        listEl.innerHTML = html;

        listEl.querySelectorAll('.dashboard-upcoming-row').forEach(function (row) {
            row.addEventListener('click', function () {
                var event = rows[Number(row.getAttribute('data-upcoming-index'))];
                if (!event) return;
                var props = Object.assign({ title: event.title }, event.extendedProps || {});
                showEventDetail(props);
            });
        });
    }

    async function loadUpcomingList(tz) {
        var listEl = document.getElementById('dashboardUpcomingList');
        if (!listEl) return;

        try {
            var start = todayDateStr(tz) + 'T00:00:00';
            var endDate = new Date();
            endDate.setMonth(endDate.getMonth() + 12);
            var url = new URL(BOOKING_EVENTS_API, window.location.origin);
            url.searchParams.set('start', start);
            url.searchParams.set('end', endDate.toISOString());

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
            updateStats(payload.stats);
            renderUpcomingList(payload.data || [], tz);
        } catch (err) {
            console.error('Dashboard upcoming list error:', err);
            listEl.innerHTML = '<div class="dashboard-upcoming-empty">Could not load the upcoming schedule.</div>';
        }
    }

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
            clientLink += '<a href="' + props.action_url + '" class="btn btn-outline-primary btn-sm"><i class="fa-solid fa-list-check"></i> View Actions</a> ';
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
        var addBtn = document.getElementById('btnAddPersonalEvent');
        var saveBtn = document.getElementById('personalEventSaveBtn');
        var allDayEl = document.getElementById('personalEventAllDay');
        var modalEl = document.getElementById('personalEventModal');

        if (!addBtn || !saveBtn || !modalEl) return;

        addBtn.addEventListener('click', function () {
            var today = todayDateStr(calendarElTz());
            var dateInput = document.getElementById('personalEventDate');
            document.getElementById('personalEventTitle').value = '';
            document.getElementById('personalEventType').value = 'meeting';
            dateInput.value = today;
            dateInput.min = today;
            document.getElementById('personalEventStartTime').value = '09:00';
            document.getElementById('personalEventEndTime').value = '10:00';
            document.getElementById('personalEventAllDay').checked = false;
            document.getElementById('personalEventLocation').value = '';
            document.getElementById('personalEventNotes').value = '';
            document.getElementById('personalEventError').classList.add('d-none');

            if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                bootstrap.Modal.getOrCreateInstance(modalEl).show();
            } else if (typeof $ !== 'undefined') {
                $(modalEl).modal('show');
            }
        });

        if (allDayEl) {
            allDayEl.addEventListener('change', function () {
                var disabled = allDayEl.checked;
                document.getElementById('personalEventStartTime').disabled = disabled;
                document.getElementById('personalEventEndTime').disabled = disabled;
            });
        }

        saveBtn.addEventListener('click', async function () {
            var title = document.getElementById('personalEventTitle').value.trim();
            var date = document.getElementById('personalEventDate').value;
            var errorEl = document.getElementById('personalEventError');
            var allDay = document.getElementById('personalEventAllDay').checked;
            var startTime = document.getElementById('personalEventStartTime').value || '09:00';
            var endTime = document.getElementById('personalEventEndTime').value || '10:00';

            if (!title || !date) {
                errorEl.textContent = 'Title and date are required.';
                errorEl.classList.remove('d-none');
                return;
            }

            if (isPastDateStr(date, calendarElTz())) {
                errorEl.textContent = 'Please choose today or a future date.';
                errorEl.classList.remove('d-none');
                return;
            }

            var startsAt = allDay ? date + 'T00:00:00' : date + 'T' + startTime + ':00';
            var endsAt = allDay ? date + 'T23:59:59' : date + 'T' + endTime + ':00';

            saveBtn.disabled = true;
            saveBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving…';

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
                        event_type: document.getElementById('personalEventType').value,
                        starts_at: startsAt,
                        ends_at: endsAt,
                        is_all_day: allDay,
                        location: document.getElementById('personalEventLocation').value.trim() || null,
                        notes: document.getElementById('personalEventNotes').value.trim() || null,
                        calendar_type: null,
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
                loadUpcomingList(calendarElTz());

                if (typeof window.showToast === 'function') {
                    window.showToast('Event saved to your calendar.', 'success');
                }
            } catch (err) {
                errorEl.textContent = err.message || 'Could not save event.';
                errorEl.classList.remove('d-none');
            } finally {
                saveBtn.disabled = false;
                saveBtn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Save Event';
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
                    today: 'today',
                    month: 'month',
                    week: 'week',
                    day: 'day',
                    list: 'list',
                },
                height: 'auto',
                contentHeight: 620,
                timeZone: tz,
                firstDay: 1,
                nowIndicator: true,
                navLinks: true,
                eventDisplay: 'block',
                displayEventTime: true,
                displayEventEnd: false,
                dayMaxEvents: true,
                moreLinkClick: 'popover',
                eventTimeFormat: {
                    hour: 'numeric',
                    minute: '2-digit',
                    meridiem: 'short',
                },
                validRange: function (nowDate) {
                    var start = new Date(nowDate.valueOf());
                    start.setDate(1);
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
                    showEventDetail(Object.assign({ title: info.event.title }, props));
                },
                dateClick: function (info) {
                    if (isPastDateStr(info.dateStr, tz)) {
                        return;
                    }
                    var addBtn = document.getElementById('btnAddPersonalEvent');
                    if (addBtn) {
                        addBtn.click();
                        document.getElementById('personalEventDate').value = info.dateStr;
                    }
                },
                datesSet: function () {
                    hideEventTooltip();
                },
            });

            calendar.render();
            initPersonalEventModal(calendar);
            loadUpcomingList(tz);
            window.staffDashboardCalendar = calendar;
            document.addEventListener('scroll', hideEventTooltip, true);
            window.addEventListener('resize', hideEventTooltip);

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
