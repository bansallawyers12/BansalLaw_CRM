{{-- Global personal-calendar reminder alert: polls on every CRM page (same UX as calendar-v6). --}}
@php
    $_calReminderStaff = auth('admin')->user();
    $_calReminderEnabled = $_calReminderStaff instanceof \App\Models\Staff
        && $_calReminderStaff->canAccessPersonalCalendar();
    $_calReminderCalendarUrl = $_calReminderEnabled
        ? route('booking.appointments.calendar.staff', ['staff' => $_calReminderStaff->id])
        : null;
@endphp

@if($_calReminderEnabled)
{{-- Due calendar reminder alert (same pattern as matter reopen urgent modal + Remind me snooze) --}}
<div class="modal fade" id="bookingCalReminderModal" tabindex="-1" aria-labelledby="bookingCalReminderModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content booking-cal-reminder-modal">
            <div class="modal-header">
                <h5 class="modal-title" id="bookingCalReminderModalLabel">
                    <i class="fa-solid fa-bell me-2" aria-hidden="true"></i>
                    Upcoming reminders
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close for this page"></button>
            </div>
            <div class="modal-body">
                <p class="booking-cal-reminder-modal__hint">
                    These pop up when a reminder window opens, or when an event from <strong>today or yesterday</strong> is still not marked complete.
                    Use <strong>Remind me</strong> on each item to hide that alert for a while — it will return when that time ends.
                </p>
                <ul class="booking-cal-reminder-list" id="bookingCalReminderList"></ul>
            </div>
            <div class="modal-footer booking-cal-reminder-modal__footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    if (window.__bookingCalReminderGlobalInit) {
        return;
    }
    window.__bookingCalReminderGlobalInit = true;

    const BOOKING_WEB_BASE = @json(rtrim(url('/booking'), '/'));
    const BOOKING_CAL_REMINDER_CALENDAR_URL = @json($_calReminderCalendarUrl);
    const BOOKING_CAL_REMINDER_KEY = 'bookingCalDismissedReminders';
    const BOOKING_CAL_REMINDER_SNOOZE_KEY = 'bookingCalReminderSnooze';
    const BOOKING_CAL_REMINDER_POLL_MS = 15_000;

    let _bookingCalReminderActive = [];
    let _bookingCalReminderSnoozeTimer = null;
    let _bookingCalReminderModalBound = false;
    let _bookingCalReminderClosedUntil = 0;
    let _bookingCalReminderSkipCloseSoftHide = false;

    function escapeHtmlLocal(value) {
        if (typeof escapeHtml === 'function') {
            return escapeHtml(value);
        }
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function bookingCalReminderGetDismissed() {
        try {
            return JSON.parse(sessionStorage.getItem(BOOKING_CAL_REMINDER_KEY) || '[]');
        } catch (e) { return []; }
    }

    function bookingCalReminderDismiss(id) {
        const list = bookingCalReminderGetDismissed();
        const numId = Number(id);
        if (!list.includes(numId) && !list.includes(id)) {
            list.push(numId);
            sessionStorage.setItem(BOOKING_CAL_REMINDER_KEY, JSON.stringify(list));
        }
    }

    function bookingCalReminderClearCache(id) {
        if (id == null) return;
        const list = bookingCalReminderGetDismissed().filter(function (x) {
            return Number(x) !== Number(id);
        });
        sessionStorage.setItem(BOOKING_CAL_REMINDER_KEY, JSON.stringify(list));
        bookingCalReminderClearSnooze(id);
    }
    window.bookingCalReminderClearCache = bookingCalReminderClearCache;

    function bookingCalReminderGetSnoozeMap() {
        try {
            return JSON.parse(localStorage.getItem(BOOKING_CAL_REMINDER_SNOOZE_KEY) || '{}') || {};
        } catch (e) {
            return {};
        }
    }

    function bookingCalReminderSaveSnoozeMap(map) {
        try {
            localStorage.setItem(BOOKING_CAL_REMINDER_SNOOZE_KEY, JSON.stringify(map));
        } catch (e) { /* ignore quota */ }
    }

    function bookingCalReminderClearSnooze(id) {
        const map = bookingCalReminderGetSnoozeMap();
        const key = String(id);
        if (!(key in map)) return;
        delete map[key];
        bookingCalReminderSaveSnoozeMap(map);
    }

    function bookingCalReminderIsSnoozed(id) {
        const map = bookingCalReminderGetSnoozeMap();
        const key = String(id);
        const until = Number(map[key] || 0);
        if (!until) return false;
        if (Date.now() >= until) {
            delete map[key];
            bookingCalReminderSaveSnoozeMap(map);
            return false;
        }
        return true;
    }

    function bookingCalReminderScheduleSnoozeWake() {
        if (_bookingCalReminderSnoozeTimer) {
            clearTimeout(_bookingCalReminderSnoozeTimer);
            _bookingCalReminderSnoozeTimer = null;
        }
        const map = bookingCalReminderGetSnoozeMap();
        const now = Date.now();
        let soonest = null;
        Object.keys(map).forEach(function (key) {
            const until = Number(map[key] || 0);
            if (!until) return;
            if (until <= now) {
                delete map[key];
                return;
            }
            if (soonest == null || until < soonest) soonest = until;
        });
        bookingCalReminderSaveSnoozeMap(map);
        if (soonest == null) return;
        const wait = Math.min(Math.max(soonest - now, 250), 2147483647);
        _bookingCalReminderSnoozeTimer = setTimeout(function () {
            bookingCalPollReminders();
            bookingCalReminderScheduleSnoozeWake();
        }, wait);
    }

    function bookingCalReminderSnoozeIds(ids, minutes) {
        const mins = Math.max(1, Number(minutes) || 5);
        const until = Date.now() + mins * 60 * 1000;
        const map = bookingCalReminderGetSnoozeMap();
        (ids || []).forEach(function (id) {
            map[String(id)] = until;
        });
        bookingCalReminderSaveSnoozeMap(map);
        bookingCalReminderScheduleSnoozeWake();
        return mins;
    }

    function bookingCalReminderMinutesLabel(mins) {
        if (!mins || mins <= 0) return '';
        if (mins < 60) return mins + ' min';
        if (mins === 60) return '1 hr';
        if (mins < 1440) return Math.round(mins / 60) + ' hrs';
        if (mins === 1440) return '1 day';
        return Math.round(mins / 1440) + ' days';
    }
    window.bookingCalReminderMinutesLabel = bookingCalReminderMinutesLabel;

    function bookingCalReminderTypeLabel(type) {
        const map = {
            court: 'Court',
            meeting: 'Meeting',
            deadline: 'Deadline',
            reminder: 'Reminder',
            other: 'Other',
        };
        return map[String(type || '').toLowerCase()] || 'Event';
    }

    function bookingCalReminderHideModal() {
        const el = document.getElementById('bookingCalReminderModal');
        if (!el || typeof bootstrap === 'undefined' || !bootstrap.Modal) return;
        const instance = bootstrap.Modal.getInstance(el);
        if (instance) instance.hide();
    }

    function bookingCalReminderOpenEvent(eventId) {
        bookingCalReminderHideModal();
        const fcId = 'staff-cal-' + String(eventId);
        const fcEvent = typeof calendar !== 'undefined' && calendar && calendar.getEventById
            ? calendar.getEventById(fcId)
            : null;
        if (fcEvent && typeof showStaffImportantEventModal === 'function') {
            showStaffImportantEventModal(fcEvent, fcEvent.extendedProps || {});
            return;
        }
        // Off calendar pages: open personal calendar focused on this event.
        if (BOOKING_CAL_REMINDER_CALENDAR_URL) {
            const url = BOOKING_CAL_REMINDER_CALENDAR_URL +
                (BOOKING_CAL_REMINDER_CALENDAR_URL.indexOf('?') >= 0 ? '&' : '?') +
                'open_event=' + encodeURIComponent(String(eventId));
            window.location.href = url;
            return;
        }
        if (typeof iziToast !== 'undefined') {
            iziToast.info({
                title: 'Event',
                message: 'Could not open that event. Open My Calendar to view it.',
                position: 'topRight',
                timeout: 4000,
            });
        }
    }

    function bookingCalReminderBindModalOnce() {
        if (_bookingCalReminderModalBound) return;
        _bookingCalReminderModalBound = true;
        const modalEl = document.getElementById('bookingCalReminderModal');
        if (!modalEl) return;

        modalEl.addEventListener('hidden.bs.modal', function () {
            if (_bookingCalReminderSkipCloseSoftHide) {
                _bookingCalReminderSkipCloseSoftHide = false;
                return;
            }
            if ((_bookingCalReminderActive || []).length) {
                _bookingCalReminderClosedUntil = Date.now() + 2 * 60 * 1000;
            }
        });

        modalEl.addEventListener('click', function (e) {
            const viewBtn = e.target.closest('[data-booking-cal-reminder-view]');
            if (viewBtn) {
                e.preventDefault();
                _bookingCalReminderSkipCloseSoftHide = true;
                const id = parseInt(viewBtn.getAttribute('data-booking-cal-reminder-view'), 10);
                if (id) bookingCalReminderOpenEvent(id);
                return;
            }
            const dismissBtn = e.target.closest('[data-booking-cal-reminder-dismiss]');
            if (dismissBtn) {
                e.preventDefault();
                const id = parseInt(dismissBtn.getAttribute('data-booking-cal-reminder-dismiss'), 10);
                if (!id) return;
                bookingCalReminderDismiss(id);
                _bookingCalReminderActive = _bookingCalReminderActive.filter(function (evt) {
                    return Number(evt.id) !== id;
                });
                if (!_bookingCalReminderActive.length) {
                    _bookingCalReminderSkipCloseSoftHide = true;
                    bookingCalReminderHideModal();
                } else {
                    bookingCalReminderRenderList(_bookingCalReminderActive);
                }
            }
        });

        document.addEventListener('click', function (e) {
            const opt = e.target.closest('[data-booking-cal-reminder-snooze]');
            if (!opt) return;
            e.preventDefault();
            e.stopPropagation();
            const minutes = parseInt(opt.getAttribute('data-booking-cal-reminder-snooze'), 10);
            if (!minutes || minutes < 1) return;

            const singleId = parseInt(opt.getAttribute('data-booking-cal-reminder-id') || '', 10);
            let ids = [];
            if (Number.isFinite(singleId) && singleId > 0) {
                ids = [singleId];
            } else {
                ids = (_bookingCalReminderActive || []).map(function (evt) { return evt.id; });
            }
            if (!ids.length) return;

            const mins = bookingCalReminderSnoozeIds(ids, minutes);
            _bookingCalReminderActive = (_bookingCalReminderActive || []).filter(function (evt) {
                return ids.indexOf(Number(evt.id)) === -1 && ids.indexOf(evt.id) === -1;
            });

            if (!_bookingCalReminderActive.length) {
                _bookingCalReminderSkipCloseSoftHide = true;
                bookingCalReminderHideModal();
            } else {
                bookingCalReminderRenderList(_bookingCalReminderActive);
            }

            const label = mins === 60 ? '1 hour' : (String(mins) + ' minutes');
            if (typeof iziToast !== 'undefined') {
                iziToast.info({
                    title: 'Remind me later',
                    message: (ids.length === 1 ? 'This reminder' : 'Selected reminders') +
                        ' will return in ' + label + '.',
                    position: 'topRight',
                    timeout: 3500,
                });
            }
        }, true);
    }

    function bookingCalReminderSnoozeMenuHtml(eventId) {
        const id = Number(eventId);
        const options = [
            [5, '5 minutes'],
            [10, '10 minutes'],
            [20, '20 minutes'],
            [30, '30 minutes'],
            [60, '1 hour'],
        ];
        const items = options.map(function (pair) {
            return (
                '<li><button type="button" class="dropdown-item" data-booking-cal-reminder-snooze="' +
                pair[0] +
                '" data-booking-cal-reminder-id="' +
                id +
                '">' +
                pair[1] +
                '</button></li>'
            );
        }).join('');

        return (
            '<div class="dropdown booking-cal-reminder-snooze">' +
                '<button type="button" class="btn btn-outline-warning btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">' +
                    '<i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i> Remind me' +
                '</button>' +
                '<ul class="dropdown-menu dropdown-menu-end booking-cal-reminder-snooze__menu">' +
                    items +
                '</ul>' +
            '</div>'
        );
    }

    function bookingCalReminderRenderList(events) {
        const listEl = document.getElementById('bookingCalReminderList');
        if (!listEl) return;
        listEl.innerHTML = (events || []).map(function (evt) {
            const startsAt = new Date(evt.starts_at);
            const timeStr = startsAt.toLocaleString('en-AU', {
                timeZone: 'Australia/Melbourne',
                weekday: 'short',
                month: 'short',
                day: 'numeric',
                hour: 'numeric',
                minute: '2-digit',
                hour12: true,
            });
            const isOverdue = !!evt.is_overdue || evt.alert_kind === 'overdue' || startsAt.getTime() <= Date.now();
            const beforeLabel = bookingCalReminderMinutesLabel(evt.reminder_minutes);
            const typeLabel = bookingCalReminderTypeLabel(evt.event_type);
            const whenLine = isOverdue
                ? ('Overdue · ' + timeStr + ' — mark complete if attended, or remind me later')
                : ((beforeLabel ? 'In ' + beforeLabel + ' · ' : '') + timeStr);
            const badgeClass = isOverdue
                ? 'booking-cal-reminder-list__badge booking-cal-reminder-list__badge--overdue'
                : 'booking-cal-reminder-list__badge';
            const badgeText = isOverdue ? 'Not attended' : typeLabel;
            const loc = evt.location
                ? '<span class="booking-cal-reminder-list__meta">' + escapeHtmlLocal(String(evt.location)) + '</span>'
                : '';
            return (
                '<li>' +
                    '<div class="booking-cal-reminder-list__card' + (isOverdue ? ' is-overdue' : '') + '">' +
                        '<span class="' + badgeClass + '">' + escapeHtmlLocal(badgeText) + '</span>' +
                        '<span class="booking-cal-reminder-list__msg">' + escapeHtmlLocal(evt.title || 'Upcoming event') + '</span>' +
                        '<span class="booking-cal-reminder-list__when">' + escapeHtmlLocal(whenLine) + '</span>' +
                        loc +
                        '<div class="booking-cal-reminder-list__actions">' +
                            '<button type="button" class="btn btn-outline-secondary btn-sm" data-booking-cal-reminder-view="' + Number(evt.id) + '">' +
                                '<i class="fa-solid fa-calendar-day"></i> View event' +
                            '</button>' +
                            '<button type="button" class="btn btn-outline-secondary btn-sm" data-booking-cal-reminder-dismiss="' + Number(evt.id) + '">' +
                                'Dismiss' +
                            '</button>' +
                            bookingCalReminderSnoozeMenuHtml(evt.id) +
                        '</div>' +
                    '</div>' +
                '</li>'
            );
        }).join('');
    }

    function bookingCalShowReminderModal(events) {
        bookingCalReminderBindModalOnce();
        const el = document.getElementById('bookingCalReminderModal');
        if (!el || typeof bootstrap === 'undefined' || !bootstrap.Modal) {
            (events || []).forEach(function (evt) {
                if (typeof iziToast !== 'undefined') {
                    const label = bookingCalReminderMinutesLabel(evt.reminder_minutes);
                    iziToast.show({
                        title: evt.title || 'Upcoming event',
                        message: (label ? 'In ' + label : 'Reminder due'),
                        color: 'yellow',
                        position: 'topRight',
                        timeout: 12000,
                    });
                }
                bookingCalReminderDismiss(evt.id);
            });
            return;
        }
        _bookingCalReminderActive = (events || []).slice();
        bookingCalReminderRenderList(_bookingCalReminderActive);
        const titleEl = document.getElementById('bookingCalReminderModalLabel');
        if (titleEl) {
            const n = _bookingCalReminderActive.length;
            titleEl.innerHTML =
                '<i class="fa-solid fa-bell me-2" aria-hidden="true"></i>' +
                (n === 1 ? 'Upcoming reminder' : (n + ' upcoming reminders'));
        }
        bootstrap.Modal.getOrCreateInstance(el).show();
    }

    async function bookingCalPollReminders() {
        try {
            const response = await fetch(
                BOOKING_WEB_BASE + '/api/calendar-events/reminders?_=' + Date.now(),
                {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                    cache: 'no-store'
                }
            );
            if (!response.ok) return;
            const data = await response.json();
            if (!data.success || !Array.isArray(data.data)) return;
            const dismissed = bookingCalReminderGetDismissed();
            const due = data.data.filter(function (evt) {
                const id = Number(evt.id);
                if (dismissed.includes(id) || dismissed.includes(evt.id)) return false;
                if (bookingCalReminderIsSnoozed(id)) return false;
                return true;
            });
            if (!due.length) return;

            const modalEl = document.getElementById('bookingCalReminderModal');
            const isOpen = modalEl && modalEl.classList.contains('show');
            if (isOpen) {
                const seen = {};
                _bookingCalReminderActive.forEach(function (e) { seen[Number(e.id)] = true; });
                let added = false;
                due.forEach(function (evt) {
                    if (!seen[Number(evt.id)]) {
                        _bookingCalReminderActive.push(evt);
                        added = true;
                    }
                });
                if (added) bookingCalReminderRenderList(_bookingCalReminderActive);
                return;
            }

            if (Date.now() < _bookingCalReminderClosedUntil) {
                const known = {};
                (_bookingCalReminderActive || []).forEach(function (e) { known[Number(e.id)] = true; });
                const hasNew = due.some(function (evt) { return !known[Number(evt.id)]; });
                const hasOverdue = due.some(function (evt) {
                    return !!evt.is_overdue || evt.alert_kind === 'overdue'
                        || (evt.starts_at && new Date(evt.starts_at).getTime() <= Date.now());
                });
                if (!hasNew && !hasOverdue) return;
            }

            bookingCalShowReminderModal(due);
        } catch (e) { /* silent – never break the page */ }
    }

    function bootBookingCalReminders() {
        bookingCalReminderScheduleSnoozeWake();
        setTimeout(bookingCalPollReminders, 1000);
        setInterval(bookingCalPollReminders, BOOKING_CAL_REMINDER_POLL_MS);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootBookingCalReminders);
    } else {
        bootBookingCalReminders();
    }
})();
</script>
@endif
