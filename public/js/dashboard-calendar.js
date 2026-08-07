/**
 * Staff personal calendar widget on the CRM dashboard.
 * Requires window.FullCalendar, window.FullCalendarPlugins (from Vite app.js).
 */
(function () {
    'use strict';

    const CALENDAR_EL_ID = 'staffDashboardCalendar';
    const BOOKING_EVENTS_API = window.dashboardRoutes?.calendarEvents || '/dashboard/calendar-events';
    const STORE_EVENT_API = window.dashboardRoutes?.storeCalendarEvent || '/booking/api/calendar-events';

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

    function showEventDetail(props) {
        var titleEl = document.getElementById('personalEventDetailTitle');
        var bodyEl = document.getElementById('personalEventDetailBody');
        var footerEl = document.getElementById('personalEventDetailFooter');
        if (!titleEl || !bodyEl || !footerEl) return;

        titleEl.textContent = props.title || 'Event Details';

        var rows = [
            ['Type', props.status_label || props.event_type],
            ['Client', props.client_name],
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
            document.getElementById('personalEventTitle').value = '';
            document.getElementById('personalEventType').value = 'meeting';
            document.getElementById('personalEventDate').value = new Date().toISOString().slice(0, 10);
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
                    right: 'dayGridMonth,timeGridWeek,listWeek',
                },
                height: 'auto',
                contentHeight: 420,
                timeZone: tz,
                firstDay: 1,
                nowIndicator: true,
                navLinks: true,
                dayMaxEvents: 3,
                moreLinkClick: 'popover',
                eventDisplay: 'block',
                eventTimeFormat: {
                    hour: 'numeric',
                    minute: '2-digit',
                    meridiem: 'short',
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
                eventClick: function (info) {
                    info.jsEvent.preventDefault();
                    var props = info.event.extendedProps || {};
                    showEventDetail(Object.assign({ title: info.event.title }, props));
                },
                dateClick: function (info) {
                    var addBtn = document.getElementById('btnAddPersonalEvent');
                    if (addBtn) {
                        addBtn.click();
                        document.getElementById('personalEventDate').value = info.dateStr;
                    }
                },
            });

            calendar.render();
            initPersonalEventModal(calendar);
            window.staffDashboardCalendar = calendar;
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initDashboardCalendar);
    } else {
        initDashboardCalendar();
    }
})();
