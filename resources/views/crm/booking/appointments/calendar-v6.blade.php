@extends('layouts.crm_client_detail')
@section('title', ucfirst($type) . ' Calendar - Website Bookings')

@section('styles')
{{-- FullCalendar v6 only: themed overrides scoped to .booking-calendar-page. Globals window.FullCalendar + FullCalendarPlugins come from layout @vite(app.js) — do not load a second FullCalendar script. --}}
@vite(['resources/css/fullcalendar-v6.css'])
@endsection

@section('content')

<div class="section-body">
    <div class="booking-calendar-page">
    <div class="row">
        <div class="col-12">
            <!-- Back and Calendar Type Navigation -->
            <div class="mb-3">
                <a href="{{ route('booking.appointments.index') }}" class="btn btn-sm btn-secondary">
                    <i class="fa-solid fa-arrow-left"></i> Back to List
                </a>
                <div class="btn-group ms-2" role="group">
                    <a href="{{ route('booking.appointments.calendar', ['type' => 'ajay']) }}" 
                       class="btn btn-sm {{ $type === 'ajay' ? 'btn-primary' : 'btn-outline-primary' }}">
                        <i class="fa-solid fa-calendar-days"></i> Ajay
                    </a>
                    <a href="{{ route('booking.appointments.calendar', ['type' => 'kunal']) }}" 
                       class="btn btn-sm {{ $type === 'kunal' ? 'btn-primary' : 'btn-outline-primary' }}">
                        <i class="fa-solid fa-calendar-days"></i> Michael
                    </a>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h4>
                        <i class="fa-solid fa-calendar-days me-2"></i>
                        {{ $calendarTitle }}
                        <small class="text-muted">(Website Bookings - v6)</small>
                    </h4>
                    <div class="card-header-action">
                        <button type="button" class="btn btn-sm btn-success me-2" id="btnAddImportantEvent">
                            <i class="fa-solid fa-plus"></i> Add Important Event
                        </button>
                        <button type="button" onclick="location.reload()" class="btn btn-sm btn-primary booking-calendar-page__refresh">
                            <i class="fa-solid fa-rotate"></i> Refresh
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Stats -->
                    <div class="calendar-stats">
                        <div class="stat-box stat-box--this-month" data-calendar-stat="this_month">
                            <div class="stat-box__icon"><i class="fa-solid fa-calendar"></i></div>
                            <h3>{{ $stats['this_month'] ?? 0 }}</h3>
                            <p>This Month</p>
                        </div>
                        <div class="stat-box stat-box--today" data-calendar-stat="today">
                            <div class="stat-box__icon"><i class="fa-solid fa-sun"></i></div>
                            <h3>{{ $stats['today'] ?? 0 }}</h3>
                            <p>Today</p>
                        </div>
                        <div class="stat-box stat-box--upcoming" data-calendar-stat="upcoming">
                            <div class="stat-box__icon"><i class="fa-solid fa-clock"></i></div>
                            <h3>{{ $stats['upcoming'] ?? 0 }}</h3>
                            <p>Upcoming</p>
                        </div>
                        <div class="stat-box stat-box--pending" data-calendar-stat="pending">
                            <div class="stat-box__icon"><i class="fa-solid fa-hourglass-half"></i></div>
                            <h3>{{ $stats['pending'] ?? 0 }}</h3>
                            <p>Payment Pending</p>
                        </div>
                        <div class="stat-box stat-box--paid" data-calendar-stat="paid">
                            <div class="stat-box__icon"><i class="fa-solid fa-circle-check"></i></div>
                            <h3>{{ $stats['paid'] ?? 0 }}</h3>
                            <p>Paid</p>
                        </div>
                        <div class="stat-box stat-box--no-show" data-calendar-stat="no_show">
                            <div class="stat-box__icon"><i class="fa-solid fa-user-slash"></i></div>
                            <h3>{{ $stats['no_show'] ?? 0 }}</h3>
                            <p>No Show</p>
                        </div>
                    </div>

                    <!-- Legend -->
                    <div class="calendar-legend-panel">
                        <div class="calendar-legend-group">
                            <span class="calendar-legend-group__label">Bookings</span>
                            <div class="calendar-legend">
                                <div class="legend-item">
                                    <div class="legend-color event-pending"></div>
                                    <span>Payment Pending</span>
                                </div>
                                <div class="legend-item">
                                    <div class="legend-color event-paid"></div>
                                    <span>Paid</span>
                                </div>
                                <div class="legend-item">
                                    <div class="legend-color event-confirmed"></div>
                                    <span>Confirmed</span>
                                </div>
                                <div class="legend-item">
                                    <div class="legend-color event-completed"></div>
                                    <span>Completed</span>
                                </div>
                                <div class="legend-item">
                                    <div class="legend-color event-cancelled"></div>
                                    <span>Cancelled</span>
                                </div>
                                <div class="legend-item">
                                    <div class="legend-color event-no-show"></div>
                                    <span>No Show</span>
                                </div>
                            </div>
                        </div>
                        <div class="calendar-legend-group">
                            <span class="calendar-legend-group__label">Important Events</span>
                            <div class="calendar-legend">
                                <div class="legend-item">
                                    <div class="legend-color event-court"></div>
                                    <span>Court / Hearing</span>
                                </div>
                                <div class="legend-item">
                                    <div class="legend-color event-meeting"></div>
                                    <span>Meeting</span>
                                </div>
                                <div class="legend-item">
                                    <div class="legend-color event-deadline"></div>
                                    <span>Deadline</span>
                                </div>
                                <div class="legend-item">
                                    <div class="legend-color event-reminder"></div>
                                    <span>Reminder</span>
                                </div>
                                <div class="legend-item">
                                    <div class="legend-color event-other"></div>
                                    <span>Other</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Calendar -->
                    <div class="calendar-v6-wrapper">
                        <div id="calendar" class="calendar-v6-container"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </div>
</div>

<!-- Event Detail Modal (scoped styles: .booking-calendar-modal — portaled next to body) -->
<div class="modal fade booking-calendar-modal appointment-detail-modal" id="eventModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable" id="eventModalDialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <div class="appointment-detail-modal__heading">
                    <span class="appointment-detail-modal__icon" id="eventModalIcon" aria-hidden="true">
                        <i class="fa-solid fa-calendar-check"></i>
                    </span>
                    <div>
                        <h5 class="modal-title mb-0" id="eventModalTitle">Appointment Details</h5>
                        <p class="appointment-detail-modal__subtitle mb-0 d-none" id="eventModalSubtitle"></p>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="eventModalBody">
                <!-- Content will be loaded dynamically -->
            </div>
            <div class="modal-footer appointment-detail-modal__footer">
                <button type="button" id="courtHearingEditBtn" class="btn btn-outline-primary d-none">
                    <i class="fa-solid fa-pen-to-square"></i> Edit Appointment
                </button>
                <div class="appointment-detail-modal__footer-actions ms-auto">
                    <button type="button" id="courtHearingCancelEditBtn" class="btn btn-secondary d-none">Cancel</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" id="courtHearingSaveBtn" class="btn btn-primary d-none">
                        <i class="fa-solid fa-floppy-disk"></i> Save Changes
                    </button>
                    <a href="#" id="viewFullDetails" class="btn btn-primary d-none" target="_blank">
                        <i class="fa-solid fa-user"></i> Open Client
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Important event create / edit -->
<div class="modal fade booking-calendar-modal important-event-modal" id="importantEventModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <div class="important-event-modal__heading">
                    <span class="important-event-modal__icon" id="importantEventTypeSwatch" aria-hidden="true">
                        <i class="fa-solid fa-calendar-plus"></i>
                    </span>
                    <div>
                        <h5 class="modal-title mb-0" id="importantEventModalTitle">Add Important Event</h5>
                        <p class="important-event-modal__subtitle mb-0">Court dates, meetings, deadlines &amp; reminders</p>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="importantEventId" value="">

                <section class="important-event-section">
                    <h6 class="important-event-section__title">
                        <i class="fa-solid fa-pen-to-square"></i> Event details
                    </h6>
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label" for="importantEventTitle">Title <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="importantEventTitle" maxlength="255"
                                   placeholder="e.g. Federal Court mention, team meeting">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="importantEventType">Type <span class="text-danger">*</span></label>
                            <select class="form-select" id="importantEventType">
                                <option value="court">Court / Hearing</option>
                                <option value="meeting">Meeting</option>
                                <option value="deadline">Deadline</option>
                                <option value="reminder">Reminder</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                    </div>
                </section>

                <section class="important-event-section">
                    <h6 class="important-event-section__title">
                        <i class="fa-solid fa-clock"></i> Schedule
                    </h6>
                    <div class="row g-3 align-items-end">
                        <div class="col-sm-6 col-lg-4">
                            <label class="form-label" for="importantEventDate">Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="importantEventDate">
                        </div>
                        <div class="col-6 col-lg-3">
                            <label class="form-label" for="importantEventStartTime">Start time</label>
                            <input type="time" class="form-control" id="importantEventStartTime">
                        </div>
                        <div class="col-6 col-lg-3">
                            <label class="form-label" for="importantEventEndTime">End time</label>
                            <input type="time" class="form-control" id="importantEventEndTime">
                        </div>
                        <div class="col-sm-12 col-lg-2">
                            <div class="form-check important-event-all-day">
                                <input type="checkbox" class="form-check-input" id="importantEventAllDay">
                                <label class="form-check-label" for="importantEventAllDay">All day</label>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="important-event-section">
                    <h6 class="important-event-section__title">
                        <i class="fa-solid fa-location-dot"></i> People &amp; place
                    </h6>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label" for="importantEventClientSelect">
                                Link to client <span class="text-muted fw-normal">(optional)</span>
                            </label>
                            <input type="hidden" id="importantEventClientId" value="">
                            <input type="hidden" id="importantEventClientEncoded" value="">
                            <select id="importantEventClientSelect" class="form-control important-event-client-select"></select>
                            <div class="form-text">Type name, email, phone or ref to search</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="importantEventLocation">Location</label>
                            <input type="text" class="form-control" id="importantEventLocation" maxlength="255"
                                   placeholder="Court name, room, or address">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="importantEventCalendarScope">Show on calendar</label>
                            <select class="form-select" id="importantEventCalendarScope">
                                <option value="{{ $type }}">This calendar only ({{ $calendarTitle }})</option>
                                <option value="">Both Ajay &amp; Michael calendars</option>
                            </select>
                        </div>
                    </div>
                </section>

                <section class="important-event-section important-event-section--last">
                    <h6 class="important-event-section__title">
                        <i class="fa-solid fa-bell"></i> Reminder &amp; notes
                    </h6>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="importantEventReminder">Pop-up reminder</label>
                            <select class="form-select" id="importantEventReminder">
                                <option value="">No reminder</option>
                                <option value="10">10 minutes before</option>
                                <option value="15">15 minutes before</option>
                                <option value="30">30 minutes before</option>
                                <option value="60">1 hour before</option>
                                <option value="120">2 hours before</option>
                                <option value="1440">1 day before</option>
                                <option value="2880">2 days before</option>
                                <option value="10080">1 week before</option>
                            </select>
                            <div class="form-text">A banner will pop up on screen when the reminder is due</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="importantEventNotes">Notes</label>
                            <textarea class="form-control" id="importantEventNotes" rows="3" maxlength="5000"
                                      placeholder="Optional details"></textarea>
                        </div>
                    </div>
                </section>

                <div class="important-event-tip">
                    <i class="fa-solid fa-circle-info"></i>
                    <span>Court dates added on a <strong>client profile → Court Dates tab</strong> also appear here automatically.</span>
                </div>
            </div>
            <div class="modal-footer important-event-modal__footer">
                <button type="button" class="btn btn-danger d-none" id="importantEventDeleteBtn">
                    <i class="fa-solid fa-trash"></i> Delete
                </button>
                <div class="important-event-modal__footer-actions ms-auto">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="importantEventSaveBtn">
                        <i class="fa-solid fa-floppy-disk"></i> Save Event
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Cancellation Confirmation Modal (shown after Appointment Details closes — Bootstrap 5 does not stack modals) -->
<div class="modal fade booking-calendar-modal" id="cancellationConfirmModal" tabindex="-1" role="dialog" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Cancellation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-3">Are you sure you want to change the status to <strong>cancelled</strong>?</p>
                <div class="mb-3">
                    <label class="form-label" for="cancelReasonInput">Cancellation reason <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="cancelReasonInput" placeholder="Enter cancellation reason" required>
                    <div class="text-danger small d-none" id="cancelReasonError">Cancellation reason is required.</div>
                </div>
                <div class="form-check mb-0">
                    <input type="checkbox" class="form-check-input" id="sendCancellationEmailCheck" checked>
                    <label class="form-check-label" for="sendCancellationEmailCheck">Send cancellation confirmation to client</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmCancelBtn">
                    <i class="fa-solid fa-xmark"></i> Confirm Cancellation
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Globals from layout @@vite(['resources/js/app.js']). Deferred modules run before DOMContentLoaded,
// so on this page init (inside DOMContentLoaded) FullCalendar is usually already defined.
// Poll anyway: missing manifest/build, slow networks, or future layout/script order changes.
function waitForFullCalendar(callback, maxAttempts = 100) {
    function fullCalendarReady() {
        return typeof FullCalendar !== 'undefined' && FullCalendar.Calendar &&
            typeof FullCalendarPlugins !== 'undefined';
    }
    if (fullCalendarReady()) {
        callback();
        return;
    }
    let attempts = 0;
    const checkInterval = setInterval(() => {
        attempts++;
        if (fullCalendarReady()) {
            clearInterval(checkInterval);
            callback();
        } else if (attempts >= maxAttempts) {
            clearInterval(checkInterval);
            console.error('❌ FullCalendar v6 not loaded after waiting. Please rebuild assets: npm run build');
            const calendarEl = document.getElementById('calendar');
            if (calendarEl) {
                calendarEl.innerHTML = '<div class="alert alert-danger">FullCalendar v6 failed to load. Please refresh the page or rebuild assets.</div>';
            }
        }
    }, 100);
}

// Make consultants available to JavaScript
@php
// Ensure unique consultants by ID using groupBy and take first of each group
$consultantsArray = $consultants->groupBy('id')->map(function($group) {
    $consultant = $group->first();
    return [
        'id' => $consultant->id,
        'name' => $consultant->name,
        'calendar_type' => $consultant->calendar_type,
    ];
})->values()->toArray();
@endphp
const consultantsData = @json($consultantsArray);
/** Base URL for /booking routes (works when app lives in a subdirectory, e.g. /BansalLaw_CRM/public). */
const BOOKING_WEB_BASE = @json(rtrim(url('/booking'), '/'));

function sleepMs(ms) {
    return new Promise(function (resolve) { setTimeout(resolve, ms); });
}

/**
 * FullCalendar feed: retry transient failures; accept rows when API sets success:false but still returns data[].
 */
async function fetchBookingCalendarEvents(fetchInfo) {
    const url = '{{ route("booking.api.appointments") }}?' + new URLSearchParams({
        type: '{{ $type }}',
        start: fetchInfo.startStr,
        end: fetchInfo.endStr,
        format: 'calendar'
    });
    const maxAttempts = 5;
    let lastError = null;

    for (let attempt = 1; attempt <= maxAttempts; attempt++) {
        try {
            const response = await fetch(url, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin',
                cache: 'no-store'
            });

            if (!response.ok) {
                lastError = new Error('HTTP ' + response.status);
                if (attempt < maxAttempts) {
                    await sleepMs(350 * attempt);
                }
                continue;
            }

            const data = await response.json();
            const rows = Array.isArray(data.data) ? data.data : [];
            const explicitFailure = data.success === false || data.success === 0
                || data.success === '0' || data.success === 'false';

            if (rows.length === 0 && explicitFailure) {
                lastError = new Error(data.message || data.error || 'Calendar API reported an error');
                if (attempt < maxAttempts) {
                    await sleepMs(400 * attempt);
                }
                continue;
            }

            return rows;
        } catch (err) {
            lastError = err instanceof Error ? err : new Error(String(err));
            if (attempt < maxAttempts) {
                await sleepMs(400 * attempt);
            }
        }
    }

    throw lastError || new Error('Failed to load appointments');
}

const BOOKING_CALENDAR_STAT_KEYS = ['this_month', 'today', 'upcoming', 'pending', 'paid', 'no_show'];
const BOOKING_CALENDAR_TYPE = @json($type);

const IMPORTANT_EVENT_COLORS = {
    court: { bg: '#5c3d8f', border: '#5c3d8f', text: '#fff', className: 'event-court' },
    meeting: { bg: '#0d6efd', border: '#0d6efd', text: '#fff', className: 'event-meeting' },
    deadline: { bg: '#c0392b', border: '#c0392b', text: '#fff', className: 'event-deadline' },
    reminder: { bg: '#d97706', border: '#d97706', text: '#1A2C40', className: 'event-reminder' },
    other: { bg: '#5E7A90', border: '#5E7A90', text: '#fff', className: 'event-other' }
};

function getImportantEventStyle(eventType) {
    return IMPORTANT_EVENT_COLORS[eventType] || IMPORTANT_EVENT_COLORS.other;
}

function mapImportantCalendarRow(apt) {
    const style = getImportantEventStyle(apt.event_type || 'other');
    const endTime = apt.ends_at || apt.appointment_datetime;
    const readOnly = apt.event_kind === 'court_hearing' || apt.read_only === true;

    return {
        id: String(apt.id),
        title: apt.title || 'Event',
        start: apt.appointment_datetime || apt.starts_at,
        end: endTime,
        allDay: !!apt.is_all_day,
        backgroundColor: style.bg,
        borderColor: style.border,
        textColor: style.text,
        classNames: [style.className, 'event-important'],
        extendedProps: Object.assign({}, apt, {
            event_kind: apt.event_kind,
            read_only: readOnly
        })
    };
}

/**
 * Re-fetch header KPIs after SSR (cold appointment API / auth cache) with retries.
 */
async function refreshBookingCalendarStats() {
    const url = '{{ route("booking.api.calendar-stats", ["type" => $type]) }}' + '?_=' + Date.now();
    const maxAttempts = 5;
    for (let attempt = 1; attempt <= maxAttempts; attempt++) {
        try {
            const response = await fetch(url, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin',
                cache: 'no-store'
            });
            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }
            const data = await response.json();
            if (!data.success || !data.data || typeof data.data !== 'object') {
                throw new Error(data.message || 'Invalid stats response');
            }
            BOOKING_CALENDAR_STAT_KEYS.forEach(function (key) {
                const box = document.querySelector('[data-calendar-stat="' + key + '"] h3');
                if (box) {
                    box.textContent = String(data.data[key] != null ? data.data[key] : 0);
                }
            });
            return;
        } catch (e) {
            if (attempt === maxAttempts) {
                console.warn('Booking calendar stats refresh failed', e);
                return;
            }
            await sleepMs(350 * attempt);
        }
    }
}

/** Avoid showing the literal text "null" / "undefined" in the modal for optional API fields */
function formatCalendarDetail(value) {
    if (value === undefined || value === null) {
        return 'N/A';
    }
    const s = String(value).trim();
    if (s === '' || s.toLowerCase() === 'null' || s.toLowerCase() === 'undefined') {
        return 'N/A';
    }
    return s;
}

function escapeHtml(value) {
    if (value === undefined || value === null) {
        return '';
    }
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

const COURT_HEARING_API_BASE = @json(url('/clients/court-hearings'));
const CLIENT_MATTERS_API_BASE = @json(url('/get-client-matters'));

document.addEventListener('DOMContentLoaded', function() {
    void refreshBookingCalendarStats();

    const calendarEl = document.getElementById('calendar');
    if (!calendarEl) {
        console.error('Calendar element not found!');
        return;
    }
    
    // Wait for FullCalendar to be available before initializing
    waitForFullCalendar(function() {
        let bookingCalFirstLoadDone = false;
        let bookingCalDidEmptyRefetch = false;

        // Initialize FullCalendar v6
        const calendar = new FullCalendar.Calendar(calendarEl, {
        plugins: [
            FullCalendarPlugins.dayGridPlugin,
            FullCalendarPlugins.timeGridPlugin,
            FullCalendarPlugins.interactionPlugin,
            FullCalendarPlugins.listPlugin
        ],
        
        // Initial view and header
        initialView: 'dayGridMonth',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
        },
        
        // Calendar settings
        height: 'auto',
        timeZone: 'Australia/Melbourne',
        firstDay: 1, // Monday
        
        // Event display
        eventDisplay: 'block',
        displayEventTime: true,
        displayEventEnd: false,
        eventMaxStack: 3,
        dayMaxEvents: true,
        moreLinkClick: 'popover',
        
        // Navigation
        navLinks: true,
        nowIndicator: true,
        
        // Time format
        eventTimeFormat: {
            hour: 'numeric',
            minute: '2-digit',
            meridiem: 'short'
        },
        
        // Business hours (optional)
        businessHours: {
            daysOfWeek: [1, 2, 3, 4, 5], // Monday - Friday
            startTime: '09:00',
            endTime: '17:00',
        },
        
        // Event source - fetch from API
        events: async function(fetchInfo, successCallback, failureCallback) {
            
            try {
                const rows = await fetchBookingCalendarEvents(fetchInfo);

                // Transform appointments to FullCalendar v6 event format
                const events = rows.map(apt => {
                    if (apt.event_kind === 'staff_event' || apt.event_kind === 'court_hearing') {
                        return mapImportantCalendarRow(apt);
                    }

                    const _aptDate = apt.appointment_datetime ? new Date(apt.appointment_datetime) : null;
                    const endTime = (_aptDate && !isNaN(_aptDate))
                        ? new Date(_aptDate.getTime() + (apt.duration_minutes || 15) * 60_000).toISOString()
                        : apt.appointment_datetime;
                    
                    // Format meeting_type for display (e.g., 'in_person' -> 'In Person')
                    const meetingTypeDisplay = apt.meeting_type 
                        ? apt.meeting_type.split('_').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ')
                        : 'N/A';
                    
                    // Determine color based on status column only
                    // If status = 'paid' → blue color, if status = 'pending' → pending color, etc.
                    const backgroundColor = getStatusColor(apt.status);
                    const borderColor = getStatusColor(apt.status);
                    const textColor = getStatusTextColor(apt.status);
                    
                    const eventId = apt.read_only
                        ? ('ext-' + (apt.bansal_appointment_id ?? apt.id))
                        : String(apt.id);

                    return {
                        id: eventId,
                        title: `${apt.client_name} (${meetingTypeDisplay})`,
                        start: apt.appointment_datetime,
                        end: endTime,
                        backgroundColor: backgroundColor,
                        borderColor: borderColor,
                        textColor: textColor,
                        classNames: ['event-' + apt.status, apt.status === 'paid' ? 'event-paid' : ''],
                        extendedProps: {
                            client_id: apt.client_id,
                            client_id_encoded: apt.client_id_encoded,
                            client_name: apt.client_name,
                            client_email: apt.client_email,
                            client_phone: apt.client_phone,
                            service_type: apt.service_type,
                            status: apt.status,
                            status_label: apt.status_label || '',
                            payment_type: apt.payment_type || '',
                            location: apt.location,
                            meeting_type: apt.meeting_type,
                            preferred_language: apt.preferred_language || 'English',
                            consultant: apt.consultant?.name || 'Not Assigned',
                            is_paid: apt.is_paid,
                            payment_status: (apt.payment_status != null && String(apt.payment_status).trim() !== '')
                                ? apt.payment_status
                                : (apt.is_paid ? 'Paid' : 'Free'),
                            final_amount: apt.final_amount,
                            duration_minutes: apt.duration_minutes || 15,
                            appointment_datetime: apt.appointment_datetime,
                            read_only: !!apt.read_only,
                            crm_appointment_id: apt.crm_appointment_id,
                            bansal_appointment_id: apt.bansal_appointment_id,
                            ...(apt.status === 'paid' && { 'data-paid': 'true' })
                        }
                    };
                });
                
                successCallback(events);
                void refreshBookingCalendarStats();
                
            } catch (error) {
                console.error('Error loading events:', error);
                failureCallback(error);
                if (typeof iziToast !== 'undefined' && iziToast.error) {
                    iziToast.error({
                        title: 'Calendar',
                        message: 'Could not load appointments. ' + (error && error.message ? error.message : 'Please try Refresh.'),
                        position: 'topRight',
                        timeout: 8000
                    });
                } else {
                    alert('Failed to load appointments: ' + (error && error.message ? error.message : 'Unknown error'));
                }
            }
        },
        
        // Event click handler
        eventClick: function(info) {
            
            const event = info.event;
            const props = event.extendedProps;
            const eventKind = props.event_kind || 'booking';

            if (eventKind === 'court_hearing') {
                showCourtHearingEventModal(event, props);
                return;
            }

            if (eventKind === 'staff_event') {
                openImportantEventModalForEdit(props);
                return;
            }
            
            // Format date/time in Australia/Melbourne timezone
            // The ISO datetime string from API is in UTC, we need to convert to Melbourne time
            // Melbourne is UTC+10 (AEST) or UTC+11 (AEDT) - we'll use a fixed offset approach
            const originalDateTime = props.appointment_datetime || event.startStr;
            // Parse the ISO string and create a Date object (JavaScript Date parses ISO as UTC)
            const utcDate = new Date(originalDateTime);
            // Melbourne timezone offset: UTC+11 (AEDT) or UTC+10 (AEST)
            // For simplicity, we'll use the browser's Intl API which handles DST automatically
            const formattedDate = utcDate.toLocaleString('en-AU', {
                timeZone: 'Australia/Melbourne',
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: 'numeric',
                minute: '2-digit',
                hour12: true
            });
            
            // Calculate duration - use end time if available, otherwise use duration_minutes
            let duration = props.duration_minutes || 15;
            if (event.end) {
                const startTime = event.start.getTime();
                const endTime = event.end.getTime();
                const diffMinutes = Math.round((endTime - startTime) / (1000 * 60));
                if (diffMinutes > 0 && diffMinutes < 1440) { // Valid duration (less than 24 hours)
                    duration = diffMinutes;
                }
            }
            
            // Generate client profile URL if client_id exists
            let clientNameDisplay = props.client_name;
            if (props.client_id_encoded) {
                const clientProfileUrl = `/clients/detail/${props.client_id_encoded}`;
                clientNameDisplay = `<a href="${clientProfileUrl}" target="_blank" class="booking-calendar-link">${props.client_name}</a>`;
            }
            
            // Format meeting type for display
            const meetingTypeDisplay = props.meeting_type 
                ? props.meeting_type.split('_').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ')
                : 'N/A';
            
            const readOnly = props.read_only === true;
            const manageId = props.crm_appointment_id;
            const canManage = !readOnly && manageId != null;
            const slotKey = String(event.id).replace(/[^a-zA-Z0-9_-]/g, '_');
            const meetingTypeRaw = (props.meeting_type || 'in_person').replace(/'/g, "\\'");
            
            // Format date and time for input fields in Melbourne timezone
            const melbourneDate = utcDate.toLocaleDateString('en-CA', {
                timeZone: 'Australia/Melbourne'
            });
            const melbourneTime = utcDate.toLocaleTimeString('en-US', {
                timeZone: 'Australia/Melbourne',
                hour12: false,
                hour: '2-digit',
                minute: '2-digit'
            });

            const managementSection = canManage ? `
                    <section class="appt-detail-section appt-detail-section--actions">
                        <h6 class="appt-detail-section__title"><i class="fa-solid fa-calendar-days"></i> Reschedule</h6>
                        <div class="row g-3 align-items-end">
                            <div class="col-md-4">
                                <label class="form-label" for="rescheduleDate-${slotKey}">Appointment date</label>
                                <input type="date" class="form-control" id="rescheduleDate-${slotKey}"
                                       value="${melbourneDate}"
                                       data-original-date="${melbourneDate}"
                                       onchange="validateWeekendDate(this, '${slotKey}')">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="rescheduleTime-${slotKey}">Appointment time</label>
                                <input type="time" class="form-control" id="rescheduleTime-${slotKey}"
                                       value="${melbourneTime}"
                                       data-original-time="${melbourneTime}">
                            </div>
                            <div class="col-md-4">
                                <button type="button" class="btn btn-primary w-100" onclick="rescheduleAppointmentDateTime('${slotKey}', ${manageId}, '${props.meeting_type || 'in_person'}', '${props.preferred_language || 'English'}')">
                                    <i class="fa-solid fa-floppy-disk"></i> Update Date &amp; Time
                                </button>
                            </div>
                        </div>
                        <div class="form-text"><i class="fa-solid fa-circle-info"></i> Changes sync with the public booking website when linked.</div>
                    </section>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <section class="appt-detail-section appt-detail-section--actions h-100">
                                <h6 class="appt-detail-section__title"><i class="fa-solid fa-pen-to-square"></i> Change status</h6>
                                <div class="appt-action-buttons">
                                    <button type="button" class="btn btn-sm btn-outline-success" onclick="updateAppointmentStatus(${manageId}, 'confirmed', this)">
                                        <i class="fa-solid fa-check"></i> Confirmed
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="updateAppointmentStatus(${manageId}, 'completed', this)">
                                        <i class="fa-solid fa-circle-check"></i> Complete
                                    </button>
                                    ${props.final_amount && parseFloat(props.final_amount) > 0 ? `
                                    <button type="button" class="btn btn-sm btn-outline-info" onclick="updateAppointmentStatus(${manageId}, 'paid', this)">
                                        <i class="fa-solid fa-dollar-sign"></i> Payment done
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-warning" onclick="updateAppointmentStatus(${manageId}, 'pending', this)">
                                        <i class="fa-solid fa-clock"></i> Payment pending
                                    </button>
                                    ` : ''}
                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="updateAppointmentStatus(${manageId}, 'cancelled', this)">
                                        <i class="fa-solid fa-xmark"></i> Cancelled
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="updateAppointmentStatus(${manageId}, 'no_show', this)">
                                        <i class="fa-solid fa-user-times"></i> No show
                                    </button>
                                </div>
                            </section>
                        </div>
                        <div class="col-md-6">
                            <section class="appt-detail-section appt-detail-section--actions h-100">
                                <h6 class="appt-detail-section__title"><i class="fa-solid fa-right-left"></i> Change calendar</h6>
                                <label class="form-label" for="consultantSelect-${slotKey}">Consultant</label>
                                <select class="form-select" id="consultantSelect-${slotKey}" onchange="updateAppointmentConsultant(${manageId}, '${slotKey}', this.value)">
                                    <option value="">Select consultant…</option>
                                    ${(() => {
                                        const uniqueConsultants = [];
                                        const seenIds = new Set();
                                        if (Array.isArray(consultantsData)) {
                                            consultantsData.forEach(consultant => {
                                                if (consultant && consultant.id && !seenIds.has(consultant.id)) {
                                                    seenIds.add(consultant.id);
                                                    uniqueConsultants.push(consultant);
                                                }
                                            });
                                        }
                                        return uniqueConsultants.map(consultant => {
                                            const isSelected = props.consultant && props.consultant.includes(consultant.name);
                                            return `<option value="${consultant.id}" ${isSelected ? 'selected' : ''}>${consultant.name} (${consultant.calendar_type})</option>`;
                                        }).join('');
                                    })()}
                                </select>
                                <div class="form-text"><i class="fa-solid fa-circle-info"></i> Moves this appointment to the selected calendar.</div>
                            </section>
                        </div>
                    </div>` : `
                    <div class="appt-detail-tip">
                        <i class="fa-solid fa-circle-info"></i>
                        <span>This appointment is from the public booking API. CRM actions appear after it syncs to BansalLaw CRM.</span>
                    </div>`;
            
            const modalBody = `
                <div class="appt-detail-view">
                    <div class="appt-detail-hero appt-detail-hero--booking">
                        <div class="appt-detail-hero__main">
                            <div class="appt-detail-hero__client">${clientNameDisplay}</div>
                            <div class="appt-detail-hero__when">
                                <i class="fa-solid fa-clock"></i>
                                ${escapeHtml(formattedDate)} · ${duration} min
                            </div>
                        </div>
                        <div class="appt-detail-hero__meta">
                            ${renderApptStatusPill(props.status, formatCalendarDetail(props.status_label) !== 'N/A' ? props.status_label : (props.status || '').toString().toUpperCase(), 'statusBadge')}
                            <span class="appt-status-pill appt-status-pill--payment appt-status-pill--${props.is_paid ? 'paid' : 'unpaid'}">${escapeHtml(formatCalendarDetail(props.payment_status))}</span>
                        </div>
                    </div>

                    <div class="appt-detail-grid">
                        ${renderApptDetailItem('fa-envelope', 'Email', escapeHtml(formatCalendarDetail(props.client_email)))}
                        ${renderApptDetailItem('fa-phone', 'Phone', escapeHtml(formatCalendarDetail(props.client_phone)))}
                        ${renderApptDetailItem('fa-briefcase', 'Service', escapeHtml(formatCalendarDetail(props.service_type)))}
                        ${renderApptDetailItem('fa-location-dot', 'Location', escapeHtml(props.location ? props.location.charAt(0).toUpperCase() + props.location.slice(1) : 'N/A'))}
                        ${canManage
                            ? `<div class="appt-detail-item appt-detail-item--editable">
                                <div class="appt-detail-item__icon"><i class="fa-solid fa-video"></i></div>
                                <div class="appt-detail-item__content">
                                    <span class="appt-detail-item__label">Meeting type</span>
                                    <div class="appt-detail-item__value">
                                        <span id="meetingTypeDisplay-${slotKey}" class="booking-calendar-link booking-calendar-link--action" onclick="showMeetingTypeDropdown('${slotKey}', '${meetingTypeRaw}')" title="Click to change meeting type">
                                            ${escapeHtml(meetingTypeDisplay)}
                                            <i class="fa-solid fa-pen-to-square ms-1" style="font-size: 0.8em;"></i>
                                        </span>
                                        <select id="meetingTypeSelect-${slotKey}" class="form-select form-select-sm d-none mt-1" style="max-width: 220px;" onchange="updateAppointmentMeetingType(${manageId}, '${slotKey}', this.value)" data-is-paid="${props.is_paid}">
                                            <option value="in_person" ${props.meeting_type === 'in_person' ? 'selected' : ''}>In Person</option>
                                            <option value="phone" ${props.meeting_type === 'phone' ? 'selected' : ''}>Phone</option>
                                            ${props.is_paid ? `<option value="video" ${props.meeting_type === 'video' ? 'selected' : ''}>Video</option>` : ''}
                                        </select>
                                    </div>
                                </div>
                            </div>`
                            : renderApptDetailItem('fa-video', 'Meeting type', escapeHtml(meetingTypeDisplay))}
                        ${renderApptDetailItem('fa-language', 'Language', escapeHtml(props.preferred_language ? props.preferred_language.charAt(0).toUpperCase() + props.preferred_language.slice(1).toLowerCase() : 'English'))}
                        ${renderApptDetailItem('fa-user-tie', 'Consultant', escapeHtml(props.consultant))}
                        ${props.is_paid ? renderApptDetailItem('fa-dollar-sign', 'Amount', '$' + (props.final_amount ? parseFloat(props.final_amount).toFixed(2) : '0.00')) : ''}
                    </div>

                    ${managementSection}
                </div>
            `;
            
            document.getElementById('eventModalBody').innerHTML = modalBody;
            setEventModalHeader({
                title: 'Appointment Details',
                subtitle: formatCalendarDetail(props.service_type) !== 'N/A' ? formatCalendarDetail(props.service_type) : props.client_name,
                iconHtml: '<i class="fa-solid fa-calendar-check"></i>',
                iconBg: '#1e3d60',
                iconColor: '#fff'
            });
            const vfd = document.getElementById('viewFullDetails');
            setEventModalCourtHearingFooter('hidden');
            vfd.innerHTML = '<i class="fa-solid fa-arrow-up-right-from-square"></i> View Full Details';
            if (canManage) {
                vfd.classList.remove('d-none');
                vfd.href = BOOKING_WEB_BASE + '/appointments/' + manageId;
            } else {
                vfd.classList.add('d-none');
            }
            $('#eventModal').modal('show');
        },
        
        // Date click — add important event on selected day
        dateClick: function(info) {
            const d = info.date;
            const dateStr = d.toLocaleDateString('en-CA', { timeZone: 'Australia/Melbourne' });
            const timeStr = d.toLocaleTimeString('en-US', {
                timeZone: 'Australia/Melbourne',
                hour12: false,
                hour: '2-digit',
                minute: '2-digit'
            });
            openImportantEventModalForCreate(dateStr, timeStr);
        },
        
        // Loading indicator — one automatic refetch if the first completed load has no events (flaky API / cold auth)
        loading: function(isLoading) {
            if (isLoading) {
                return;
            }
            if (!bookingCalFirstLoadDone && !bookingCalDidEmptyRefetch) {
                bookingCalFirstLoadDone = true;
                setTimeout(function() {
                    try {
                        if (calendar.getEvents().length === 0) {
                            bookingCalDidEmptyRefetch = true;
                            calendar.refetchEvents();
                        }
                    } catch (e) {
                        console.warn('Booking calendar empty refetch check failed', e);
                    }
                }, 400);
            }
        },
        
        // Error handler
        eventDidMount: function(info) {
            // Add tooltip - format time in Australia/Melbourne timezone
            // Use the original ISO datetime string from extendedProps
            const props = info.event.extendedProps;
            const originalDateTime = props.appointment_datetime || info.event.startStr;
            // Parse the ISO string as UTC and convert to Melbourne timezone
            const utcDate = new Date(originalDateTime);
            const formattedTime = utcDate.toLocaleString('en-AU', {
                timeZone: 'Australia/Melbourne',
                hour: 'numeric',
                minute: '2-digit',
                hour12: true
            });
            $(info.el).tooltip({
                title: info.event.title + ' - ' + formattedTime,
                placement: 'top',
                trigger: 'hover',
                container: 'body'
            });
            
            if ((props.event_kind || 'booking') === 'booking' && props.status === 'paid') {
                info.el.style.setProperty('background-color', 'var(--navy)', 'important');
                info.el.style.setProperty('border-color', 'var(--navy)', 'important');
                info.el.style.setProperty('color', '#fff', 'important');
            }
        }
    });
    
        // Render the calendar
        calendar.render();
        
        // Helper functions
    /* docs/theme.md — hex fallbacks if :root vars unavailable to FullCalendar internals */
    function getStatusColor(status) {
        const colors = {
            'pending': '#D4A84A',
            'paid': '#1E3D60',
            'confirmed': '#1E7A52',
            'completed': '#3A6FA8',
            'cancelled': '#A83020',
            'no_show': '#5E7A90',
            'rescheduled': '#1E3D60'
        };
        return colors[status] || '#5E7A90';
    }

    function getStatusTextColor(status) {
        return status === 'pending' ? '#1A2C40' : '#fff';
    }
    
    function getStatusClass(status) {
        const classes = {
            'pending': 'warning text-dark',
            'paid': 'info text-dark',
            'confirmed': 'success',
            'completed': 'info text-dark',
            'cancelled': 'danger',
            'no_show': 'secondary',
            'rescheduled': 'primary'
        };
        return classes[status] || 'secondary';
    }

    function bookingCalendarCsrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function melbourneIsoFromDateAndTime(dateStr, timeStr, allDay) {
        if (allDay || !timeStr) {
            return dateStr + 'T09:00:00';
        }
        return dateStr + 'T' + timeStr + ':00';
    }

    function setEventModalCourtHearingFooter(mode) {
        const editBtn = document.getElementById('courtHearingEditBtn');
        const saveBtn = document.getElementById('courtHearingSaveBtn');
        const cancelBtn = document.getElementById('courtHearingCancelEditBtn');
        const vfd = document.getElementById('viewFullDetails');
        if (!editBtn || !saveBtn || !cancelBtn) {
            return;
        }
        if (mode === 'view') {
            editBtn.classList.remove('d-none');
            saveBtn.classList.add('d-none');
            cancelBtn.classList.add('d-none');
        } else if (mode === 'edit') {
            editBtn.classList.add('d-none');
            saveBtn.classList.remove('d-none');
            cancelBtn.classList.remove('d-none');
            if (vfd) {
                vfd.classList.add('d-none');
            }
        } else {
            editBtn.classList.add('d-none');
            saveBtn.classList.add('d-none');
            cancelBtn.classList.add('d-none');
        }
    }

    let _activeCourtHearingState = null;
    let _courtHearingReminderSaving = false;

    const COURT_HEARING_TYPE_OPTIONS = [
        'First Hearing', 'Evidence Hearing', 'Arguments', 'Judgment', 'Bail Hearing',
        'Stay Application', 'Case Management', 'Mediation', 'Mention', 'Other'
    ];

    const COURT_HEARING_STATUS_OPTIONS = ['Scheduled', 'Completed', 'Adjourned', 'Cancelled'];

    const COURT_HEARING_REMINDER_OPTIONS = [
        { value: '', label: 'No reminder' },
        { value: '60', label: '1 hour' },
        { value: '1440', label: '1 day' },
        { value: '10080', label: '1 week' }
    ];

    function courtHearingReminderLabel(mins) {
        if (mins == null || mins === '' || Number(mins) <= 0) {
            return 'None';
        }
        const value = String(mins);
        const match = COURT_HEARING_REMINDER_OPTIONS.find(function (opt) { return opt.value === value; });
        if (match) {
            return match.label;
        }
        return typeof bookingCalReminderMinutesLabel === 'function'
            ? bookingCalReminderMinutesLabel(Number(mins))
            : 'Custom';
    }

    function normalizeCourtHearingReminderMinutes(value) {
        if (value == null || value === '') {
            return '';
        }
        const parsed = parseInt(String(value), 10);
        return Number.isFinite(parsed) && parsed > 0 ? String(parsed) : '';
    }

    function cloneCourtHearingProps(props) {
        return Object.assign({}, props || {});
    }

    function refreshCourtHearingViewReminderSelect(props) {
        const sel = document.getElementById('courtHearingViewReminder');
        if (!sel || !props) {
            return;
        }
        const value = normalizeCourtHearingReminderMinutes(props.reminder_minutes);
        sel.value = value;
        Array.prototype.forEach.call(sel.options, function (opt) {
            opt.selected = opt.value === value;
        });
    }

    function buildCourtHearingReminderOptions(selected) {
        const current = normalizeCourtHearingReminderMinutes(selected);
        return COURT_HEARING_REMINDER_OPTIONS.map(function (opt) {
            return '<option value="' + escapeHtml(opt.value) + '"' +
                (current === opt.value ? ' selected' : '') + '>' +
                escapeHtml(opt.label) + '</option>';
        }).join('');
    }

    function getCourtHearingId(props) {
        if (props.court_hearing_id != null && props.court_hearing_id !== '') {
            return parseInt(props.court_hearing_id, 10);
        }
        const rawId = props.id || '';
        const match = String(rawId).match(/^court-(\d+)$/);
        return match ? parseInt(match[1], 10) : null;
    }

    function getCourtHearingMelbourneParts(props) {
        const utcDate = new Date(props.appointment_datetime || props.starts_at);
        const allDay = !!props.is_all_day;
        return {
            date: utcDate.toLocaleDateString('en-CA', { timeZone: 'Australia/Melbourne' }),
            time: allDay ? '' : utcDate.toLocaleTimeString('en-US', {
                timeZone: 'Australia/Melbourne',
                hour12: false,
                hour: '2-digit',
                minute: '2-digit'
            }),
            formattedDate: utcDate.toLocaleString('en-AU', {
                timeZone: 'Australia/Melbourne',
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: 'numeric',
                minute: '2-digit',
                hour12: true
            }),
            allDay: allDay
        };
    }

    function buildCourtHearingClientLink(props) {
        let clientLink = formatCalendarDetail(props.client_name);
        if (props.client_id_encoded) {
            clientLink = '<a href="/clients/detail/' + escapeHtml(props.client_id_encoded) + '" target="_blank" class="booking-calendar-link">' +
                escapeHtml(formatCalendarDetail(props.client_name)) + '</a>';
        }
        return clientLink;
    }

    function formatCourtHearingReminderSentAt(iso) {
        if (!iso) {
            return '';
        }
        try {
            return new Date(iso).toLocaleString('en-AU', {
                timeZone: 'Australia/Melbourne',
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: 'numeric',
                minute: '2-digit',
                hour12: true
            });
        } catch (e) {
            return '';
        }
    }

    function calendarDetailHasValue(val) {
        if (val == null || val === '') {
            return false;
        }
        const s = String(val).trim();
        return s !== '' && s.toLowerCase() !== 'n/a' && s.toLowerCase() !== 'null' && s.toLowerCase() !== 'undefined';
    }

    function setEventModalHeader(opts) {
        const titleEl = document.getElementById('eventModalTitle');
        const subtitleEl = document.getElementById('eventModalSubtitle');
        const iconEl = document.getElementById('eventModalIcon');
        if (titleEl && opts.title != null) {
            titleEl.textContent = opts.title;
        }
        if (subtitleEl) {
            subtitleEl.textContent = opts.subtitle || '';
            subtitleEl.classList.toggle('d-none', !opts.subtitle);
        }
        if (iconEl) {
            iconEl.innerHTML = opts.iconHtml || '<i class="fa-solid fa-calendar-check"></i>';
            iconEl.style.backgroundColor = opts.iconBg || '#1e3d60';
            iconEl.style.color = opts.iconColor || '#fff';
        }
    }

    function renderApptDetailItem(icon, label, valueHtml) {
        return `
            <div class="appt-detail-item">
                <div class="appt-detail-item__icon"><i class="fa-solid ${icon}"></i></div>
                <div class="appt-detail-item__content">
                    <span class="appt-detail-item__label">${escapeHtml(label)}</span>
                    <div class="appt-detail-item__value">${valueHtml}</div>
                </div>
            </div>
        `;
    }

    function renderApptStatusPill(status, label, elementId) {
        const key = String(status || 'default').toLowerCase().replace(/[^a-z0-9]+/g, '_');
        const text = label || status || 'Unknown';
        const idAttr = elementId ? ' id="' + escapeHtml(elementId) + '"' : '';
        return `<span class="appt-status-pill appt-status-pill--${escapeHtml(key)}"${idAttr}>${escapeHtml(text)}</span>`;
    }

    function renderCourtHearingStatusPill(status) {
        const text = formatCalendarDetail(status);
        const key = String(status || 'scheduled').toLowerCase().replace(/[^a-z0-9]+/g, '_');
        return `<span class="appt-status-pill appt-status-pill--hearing appt-status-pill--${escapeHtml(key)}">${escapeHtml(text)}</span>`;
    }

    function renderCourtHearingViewBody(props) {
        const parts = getCourtHearingMelbourneParts(props);
        const clientLink = buildCourtHearingClientLink(props);
        const hearingType = formatCalendarDetail(props.hearing_type);
        const status = props.hearing_status || props.status_label;
        const smsSentHint = props.reminder_sms_sent_at
            ? '<div class="appt-detail-reminder-sent"><i class="fa-solid fa-circle-check"></i> SMS sent ' +
                escapeHtml(formatCourtHearingReminderSentAt(props.reminder_sms_sent_at)) + '</div>'
            : '';

        const detailItems = [];
        if (calendarDetailHasValue(props.court_name)) {
            detailItems.push(renderApptDetailItem('fa-landmark', 'Court', escapeHtml(formatCalendarDetail(props.court_name))));
        }
        if (calendarDetailHasValue(props.case_number)) {
            detailItems.push(renderApptDetailItem('fa-hashtag', 'Case number', escapeHtml(formatCalendarDetail(props.case_number))));
        }
        if (calendarDetailHasValue(props.judge_name)) {
            detailItems.push(renderApptDetailItem('fa-user-tie', 'Judge', escapeHtml(formatCalendarDetail(props.judge_name))));
        }

        const detailsGrid = detailItems.length
            ? `<div class="appt-detail-grid appt-detail-grid--compact">${detailItems.join('')}</div>`
            : '';

        const notesBlock = calendarDetailHasValue(props.notes)
            ? `
                <section class="appt-detail-section">
                    <h6 class="appt-detail-section__title"><i class="fa-solid fa-note-sticky"></i> Notes</h6>
                    <div class="appt-detail-notes">${escapeHtml(formatCalendarDetail(props.notes))}</div>
                </section>
            `
            : '';

        return `
            <div class="appt-detail-view">
                <div class="appt-detail-hero appt-detail-hero--court">
                    <div class="appt-detail-hero__main">
                        <div class="appt-detail-hero__client">${clientLink}</div>
                        <div class="appt-detail-hero__when">
                            <i class="fa-solid fa-clock"></i>
                            ${escapeHtml(parts.formattedDate)}
                        </div>
                    </div>
                    <div class="appt-detail-hero__meta">
                        <span class="appt-type-pill appt-type-pill--court">${escapeHtml(hearingType)}</span>
                        ${renderCourtHearingStatusPill(status)}
                    </div>
                </div>

                ${detailsGrid}

                <section class="appt-detail-section">
                    <h6 class="appt-detail-section__title"><i class="fa-solid fa-bell"></i> Reminder</h6>
                    <div class="appt-detail-reminder">
                        <label class="form-label" for="courtHearingViewReminder">Reminder before</label>
                        <select class="form-select" id="courtHearingViewReminder">${buildCourtHearingReminderOptions(props.reminder_minutes)}</select>
                        <div class="form-text">SMS reminder is sent to the client at their phone number on file.</div>
                        <div id="courtHearingViewReminderStatus" class="appt-detail-reminder-status d-none"></div>
                        ${smsSentHint}
                    </div>
                </section>

                ${notesBlock}
            </div>
        `;
    }

    function buildCourtHearingTypeOptions(selected) {
        const current = selected || '';
        let html = '<option value="">— Select Hearing Type —</option>';
        COURT_HEARING_TYPE_OPTIONS.forEach(function (type) {
            html += '<option value="' + escapeHtml(type) + '"' + (current === type ? ' selected' : '') + '>' +
                escapeHtml(type) + '</option>';
        });
        if (current && COURT_HEARING_TYPE_OPTIONS.indexOf(current) === -1) {
            html += '<option value="' + escapeHtml(current) + '" selected>' + escapeHtml(current) + '</option>';
        }
        return html;
    }

    function buildCourtHearingStatusOptions(selected) {
        const current = selected || 'Scheduled';
        return COURT_HEARING_STATUS_OPTIONS.map(function (status) {
            return '<option value="' + status + '"' + (current === status ? ' selected' : '') + '>' + status + '</option>';
        }).join('');
    }

    function renderCourtHearingEditBody(props, matterOptionsHtml) {
        const parts = getCourtHearingMelbourneParts(props);
        const clientLink = buildCourtHearingClientLink(props);
        return `
            <div class="appt-detail-edit">
                <div id="courtHearingEditError" class="alert alert-danger d-none"></div>

                <div class="appt-detail-hero appt-detail-hero--court appt-detail-hero--compact">
                    <div class="appt-detail-hero__main">
                        <div class="appt-detail-hero__client">${clientLink}</div>
                        <div class="appt-detail-hero__when"><i class="fa-solid fa-pen-to-square"></i> Editing appointment</div>
                    </div>
                </div>

                <section class="appt-detail-section">
                    <h6 class="appt-detail-section__title"><i class="fa-solid fa-clock"></i> Schedule</h6>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="courtHearingEditDate">Hearing date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="courtHearingEditDate" value="${escapeHtml(parts.date)}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="courtHearingEditTime">Hearing time <span class="text-muted fw-normal">(optional)</span></label>
                            <input type="time" class="form-control" id="courtHearingEditTime" value="${escapeHtml(parts.time)}">
                        </div>
                    </div>
                </section>

                <section class="appt-detail-section">
                    <h6 class="appt-detail-section__title"><i class="fa-solid fa-gavel"></i> Hearing details</h6>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="courtHearingEditType">Hearing type</label>
                            <select class="form-select" id="courtHearingEditType">${buildCourtHearingTypeOptions(props.hearing_type)}</select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="courtHearingEditStatus">Status</label>
                            <select class="form-select" id="courtHearingEditStatus">${buildCourtHearingStatusOptions(props.hearing_status || props.status_label)}</select>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="courtHearingEditCourt">Court</label>
                            <input type="text" class="form-control" id="courtHearingEditCourt" maxlength="255"
                                   placeholder="Court name or location"
                                   value="${escapeHtml(formatCalendarDetail(props.court_name) === 'N/A' ? '' : props.court_name)}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="courtHearingEditCaseNumber">Case number</label>
                            <input type="text" class="form-control" id="courtHearingEditCaseNumber" maxlength="100"
                                   value="${escapeHtml(formatCalendarDetail(props.case_number) === 'N/A' ? '' : props.case_number)}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="courtHearingEditJudge">Judge / bench</label>
                            <input type="text" class="form-control" id="courtHearingEditJudge" maxlength="150"
                                   value="${escapeHtml(formatCalendarDetail(props.judge_name) === 'N/A' ? '' : props.judge_name)}">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="courtHearingEditMatter">Linked matter <span class="text-muted fw-normal">(optional)</span></label>
                            <select class="form-select" id="courtHearingEditMatter">
                                <option value="">— Not linked to a specific matter —</option>
                                ${matterOptionsHtml || ''}
                            </select>
                        </div>
                    </div>
                </section>

                <section class="appt-detail-section">
                    <h6 class="appt-detail-section__title"><i class="fa-solid fa-bell"></i> Reminder &amp; notes</h6>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="courtHearingEditReminder">Reminder before</label>
                            <select class="form-select" id="courtHearingEditReminder">${buildCourtHearingReminderOptions(props.reminder_minutes)}</select>
                            <div class="form-text">SMS reminder is sent to the client at their phone number on file.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="courtHearingEditNotes">Notes</label>
                            <textarea class="form-control" id="courtHearingEditNotes" rows="3" maxlength="5000"
                                      placeholder="Optional details">${escapeHtml(formatCalendarDetail(props.notes) === 'N/A' ? '' : props.notes)}</textarea>
                        </div>
                    </div>
                </section>
            </div>
        `;
    }

    async function fetchCourtHearingMatterOptions(clientId, selectedMatterId) {
        if (!clientId) {
            return '';
        }
        try {
            const response = await fetch(CLIENT_MATTERS_API_BASE + '/' + encodeURIComponent(clientId), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
            });
            const data = await response.json();
            if (!response.ok || !data.success || !Array.isArray(data.matters)) {
                return '';
            }
            const selected = selectedMatterId != null ? String(selectedMatterId) : '';
            return data.matters.map(function (matter) {
                const id = String(matter.id);
                const label = matter.display_name || matter.client_unique_matter_no || ('Matter #' + id);
                return '<option value="' + escapeHtml(id) + '"' + (selected === id ? ' selected' : '') + '>' +
                    escapeHtml(label) + '</option>';
            }).join('');
        } catch (e) {
            console.warn('Could not load client matters for court hearing edit', e);
            return '';
        }
    }

    function showCourtHearingViewMode() {
        if (!_activeCourtHearingState) {
            return;
        }
        const props = _activeCourtHearingState.props;
        document.getElementById('eventModalBody').innerHTML = renderCourtHearingViewBody(props);
        setEventModalHeader({
            title: 'Appointment Details',
            subtitle: calendarDetailHasValue(props.hearing_type) ? formatCalendarDetail(props.hearing_type) : 'Court hearing',
            iconHtml: '<i class="fa-solid fa-gavel"></i>',
            iconBg: '#5c3d8f',
            iconColor: '#fff'
        });
        const vfd = document.getElementById('viewFullDetails');
        if (props.client_id_encoded) {
            vfd.classList.remove('d-none');
            vfd.href = '/clients/detail/' + props.client_id_encoded;
            vfd.innerHTML = '<i class="fa-solid fa-user"></i> Open Client';
        } else {
            vfd.classList.add('d-none');
        }
        setEventModalCourtHearingFooter(getCourtHearingId(props) ? 'view' : 'hidden');
        _activeCourtHearingState.editMode = false;
    }

    async function enterCourtHearingEditMode() {
        if (!_activeCourtHearingState || !getCourtHearingId(_activeCourtHearingState.props)) {
            return;
        }
        const props = _activeCourtHearingState.props;
        const matterOptionsHtml = await fetchCourtHearingMatterOptions(props.client_id, props.client_matter_id);
        document.getElementById('eventModalBody').innerHTML = renderCourtHearingEditBody(props, matterOptionsHtml);
        setEventModalHeader({
            title: 'Edit Appointment',
            subtitle: formatCalendarDetail(props.client_name),
            iconHtml: '<i class="fa-solid fa-pen-to-square"></i>',
            iconBg: '#5c3d8f',
            iconColor: '#fff'
        });
        setEventModalCourtHearingFooter('edit');
        _activeCourtHearingState.editMode = true;
    }

    function cancelCourtHearingEditMode() {
        showCourtHearingViewMode();
    }

    function syncCourtHearingPropsToEvent(props) {
        if (!_activeCourtHearingState || !_activeCourtHearingState.event || !props) {
            return;
        }
        const event = _activeCourtHearingState.event;
        if (typeof event.setExtendedProp === 'function') {
            event.setExtendedProp('reminder_minutes', props.reminder_minutes);
            event.setExtendedProp('reminder_sms_sent_at', props.reminder_sms_sent_at || null);
        }
    }

    function applySavedHearingToProps(props, hearing) {
        props.hearing_type = hearing.hearing_type || null;
        props.court_name = hearing.court_name || null;
        props.case_number = hearing.case_number || null;
        props.judge_name = hearing.judge_name || null;
        props.hearing_status = hearing.status || 'Scheduled';
        props.status_label = hearing.status || 'Scheduled';
        props.notes = hearing.notes || null;
        props.client_matter_id = hearing.client_matter_id || null;
        props.location = hearing.court_name || null;
        if (Object.prototype.hasOwnProperty.call(hearing, 'reminder_minutes')) {
            const mins = hearing.reminder_minutes;
            if (mins != null && mins !== '') {
                const parsed = parseInt(String(mins), 10);
                if (Number.isFinite(parsed) && parsed > 0) {
                    props.reminder_minutes = parsed;
                }
            } else if (mins === null || mins === '') {
                props.reminder_minutes = null;
            }
        }
        if (Object.prototype.hasOwnProperty.call(hearing, 'reminder_sms_sent_at')) {
            props.reminder_sms_sent_at = hearing.reminder_sms_sent_at || null;
        }

        const datePart = String(hearing.hearing_date || '').slice(0, 10);
        const timeRaw = hearing.hearing_time ? String(hearing.hearing_time).slice(0, 5) : '';
        props.is_all_day = !timeRaw;
        props.appointment_datetime = timeRaw ? (datePart + 'T' + timeRaw + ':00') : (datePart + 'T09:00:00');
        props.starts_at = props.appointment_datetime;
    }

    async function saveCourtHearingReminderFromView(reminderEl) {
        if (!_activeCourtHearingState || _activeCourtHearingState.editMode || _courtHearingReminderSaving) {
            return;
        }
        const hearingId = getCourtHearingId(_activeCourtHearingState.props);
        if (!hearingId || !reminderEl) {
            return;
        }

        const props = _activeCourtHearingState.props;
        const parts = getCourtHearingMelbourneParts(props);
        const previousValue = normalizeCourtHearingReminderMinutes(props.reminder_minutes);
        const newValue = reminderEl.value || '';

        if (newValue === previousValue) {
            return;
        }

        _courtHearingReminderSaving = true;
        const statusEl = document.getElementById('courtHearingViewReminderStatus');
        reminderEl.disabled = true;
        if (statusEl) {
            statusEl.textContent = 'Saving…';
            statusEl.classList.remove('d-none', 'text-danger', 'text-success');
            statusEl.classList.add('text-muted');
        }

        const fd = new FormData();
        fd.append('_token', bookingCalendarCsrfToken());
        fd.append('hearing_date', parts.date);
        if (parts.time) {
            fd.append('hearing_time', parts.time);
        }
        fd.append('status', props.hearing_status || props.status_label || 'Scheduled');
        fd.append('reminder_minutes', newValue);

        try {
            const response = await fetch(COURT_HEARING_API_BASE + '/' + hearingId + '/update', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': bookingCalendarCsrfToken()
                },
                body: fd
            });
            const data = await response.json().catch(function () { return {}; });
            if (!response.ok || !data.success) {
                let errText = data.message || 'Could not save reminder.';
                if (data.errors) {
                    errText += ' ' + Object.values(data.errors).flat().join(' ');
                }
                throw new Error(errText);
            }

            const savedReminderMinutes = newValue === '' ? null : parseInt(newValue, 10);
            if (data.hearing) {
                applySavedHearingToProps(props, data.hearing);
            }
            props.reminder_minutes = Number.isFinite(savedReminderMinutes) && savedReminderMinutes > 0
                ? savedReminderMinutes
                : null;
            syncCourtHearingPropsToEvent(props);
            if (typeof iziToast !== 'undefined' && iziToast.success) {
                iziToast.success({
                    title: 'Saved',
                    message: 'Reminder preference updated.',
                    position: 'topRight'
                });
            }
            showCourtHearingViewMode();
            refreshCourtHearingViewReminderSelect(props);
        } catch (err) {
            reminderEl.value = previousValue;
            const message = err && err.message ? err.message : 'Could not save reminder.';
            if (statusEl) {
                statusEl.textContent = message;
                statusEl.classList.remove('text-muted', 'text-success');
                statusEl.classList.add('text-danger');
            } else if (typeof iziToast !== 'undefined' && iziToast.error) {
                iziToast.error({ title: 'Error', message: message, position: 'topRight' });
            } else {
                alert(message);
            }
        } finally {
            reminderEl.disabled = false;
            _courtHearingReminderSaving = false;
        }
    }

    async function saveCourtHearingFromModal() {
        if (!_activeCourtHearingState) {
            return;
        }
        const hearingId = getCourtHearingId(_activeCourtHearingState.props);
        if (!hearingId) {
            return;
        }

        const errorEl = document.getElementById('courtHearingEditError');
        const dateEl = document.getElementById('courtHearingEditDate');
        const timeEl = document.getElementById('courtHearingEditTime');
        const typeEl = document.getElementById('courtHearingEditType');
        const statusEl = document.getElementById('courtHearingEditStatus');
        const courtEl = document.getElementById('courtHearingEditCourt');
        const caseEl = document.getElementById('courtHearingEditCaseNumber');
        const judgeEl = document.getElementById('courtHearingEditJudge');
        const matterEl = document.getElementById('courtHearingEditMatter');
        const notesEl = document.getElementById('courtHearingEditNotes');
        const reminderEl = document.getElementById('courtHearingEditReminder');
        const saveBtn = document.getElementById('courtHearingSaveBtn');

        if (errorEl) {
            errorEl.classList.add('d-none');
            errorEl.textContent = '';
        }

        if (!dateEl || !dateEl.value) {
            if (errorEl) {
                errorEl.textContent = 'Hearing date is required.';
                errorEl.classList.remove('d-none');
            }
            return;
        }

        const fd = new FormData();
        fd.append('_token', bookingCalendarCsrfToken());
        fd.append('hearing_date', dateEl.value);
        if (timeEl && timeEl.value) {
            fd.append('hearing_time', timeEl.value);
        }
        if (typeEl && typeEl.value) {
            fd.append('hearing_type', typeEl.value);
        }
        if (courtEl && courtEl.value.trim()) {
            fd.append('court_name', courtEl.value.trim());
        }
        if (caseEl && caseEl.value.trim()) {
            fd.append('case_number', caseEl.value.trim());
        }
        if (judgeEl && judgeEl.value.trim()) {
            fd.append('judge_name', judgeEl.value.trim());
        }
        if (matterEl && matterEl.value) {
            fd.append('client_matter_id', matterEl.value);
        }
        if (statusEl && statusEl.value) {
            fd.append('status', statusEl.value);
        }
        if (notesEl && notesEl.value.trim()) {
            fd.append('notes', notesEl.value.trim());
        }
        if (reminderEl) {
            fd.append('reminder_minutes', reminderEl.value || '');
        }

        const originalHtml = saveBtn ? saveBtn.innerHTML : '';
        if (saveBtn) {
            saveBtn.disabled = true;
            saveBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving…';
        }

        try {
            const response = await fetch(COURT_HEARING_API_BASE + '/' + hearingId + '/update', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': bookingCalendarCsrfToken()
                },
                body: fd
            });
            const data = await response.json().catch(function () { return {}; });
            if (!response.ok || !data.success) {
                let errText = data.message || 'Could not save appointment.';
                if (data.errors) {
                    errText += ' ' + Object.values(data.errors).flat().join(' ');
                }
                throw new Error(errText);
            }

            if (data.hearing) {
                applySavedHearingToProps(_activeCourtHearingState.props, data.hearing);
            }
            syncCourtHearingPropsToEvent(_activeCourtHearingState.props);
            showCourtHearingViewMode();
            calendar.refetchEvents();
            if (typeof iziToast !== 'undefined' && iziToast.success) {
                iziToast.success({ title: 'Saved', message: 'Appointment updated.', position: 'topRight' });
            }
        } catch (err) {
            const message = err && err.message ? err.message : 'Could not save appointment.';
            if (errorEl) {
                errorEl.textContent = message;
                errorEl.classList.remove('d-none');
            } else if (typeof iziToast !== 'undefined' && iziToast.error) {
                iziToast.error({ title: 'Error', message: message, position: 'topRight' });
            } else {
                alert(message);
            }
        } finally {
            if (saveBtn) {
                saveBtn.disabled = false;
                saveBtn.innerHTML = originalHtml;
            }
        }
    }

    function showCourtHearingEventModal(event, props) {
        _activeCourtHearingState = { event: event, props: cloneCourtHearingProps(props), editMode: false };
        document.getElementById('eventModalBody').innerHTML = renderCourtHearingViewBody(_activeCourtHearingState.props);
        setEventModalHeader({
            title: 'Appointment Details',
            subtitle: calendarDetailHasValue(props.hearing_type) ? formatCalendarDetail(props.hearing_type) : 'Court hearing',
            iconHtml: '<i class="fa-solid fa-gavel"></i>',
            iconBg: '#5c3d8f',
            iconColor: '#fff'
        });
        const vfd = document.getElementById('viewFullDetails');
        if (props.client_id_encoded) {
            vfd.classList.remove('d-none');
            vfd.href = '/clients/detail/' + props.client_id_encoded;
            vfd.innerHTML = '<i class="fa-solid fa-user"></i> Open Client';
        } else {
            vfd.classList.add('d-none');
        }
        setEventModalCourtHearingFooter(getCourtHearingId(props) ? 'view' : 'hidden');
        $('#eventModal').modal('show');
    }

    /* ─── Important-Event Modal: TomSelect client picker ─────────────────── */
    const IMP_CLIENT_URL = @json(url('/clients/get-allclients'));
    let _impEventTsInstance = null;

    function impEventInitClientTs() {
        const el = document.getElementById('importantEventClientSelect');
        if (!el || typeof initTS !== 'function' || typeof buildGetAllClientsTomSelectConfig !== 'function') {
            return;
        }
        if (typeof destroyTS === 'function') destroyTS(el);
        _impEventTsInstance = initTS(el, buildGetAllClientsTomSelectConfig({
            url: IMP_CLIENT_URL,
            dropdownParent: 'body',
            placeholder: 'Search client by name, email or ref…',
            onChange: function (value) {
                if (!value) {
                    document.getElementById('importantEventClientId').value = '';
                    document.getElementById('importantEventClientEncoded').value = '';
                    return;
                }
                const ts = el.tomselect;
                const item = ts ? ts.options[value] : null;
                const cid = item && item.cid ? item.cid : '';
                document.getElementById('importantEventClientId').value = cid;
                const encoded = cid ? btoa(String.fromCharCode.apply(null,
                    new TextEncoder().encode(String(cid)))) : '';
                document.getElementById('importantEventClientEncoded').value = encoded;
            }
        }));
        if (_impEventTsInstance && _impEventTsInstance.wrapper) {
            _impEventTsInstance.wrapper.style.width = '100%';
        }
    }

    function impEventDestroyClientTs() {
        const el = document.getElementById('importantEventClientSelect');
        if (el && typeof destroyTS === 'function') destroyTS(el);
        _impEventTsInstance = null;
    }

    function impEventSetClientValue(cid, name) {
        const el = document.getElementById('importantEventClientSelect');
        if (!el || !cid) return;
        setTimeout(function () {
            const ts = el.tomselect;
            if (!ts) return;
            const strId = String(cid);
            if (!ts.options[strId]) {
                ts.addOption({ id: strId, cid: cid, name: name || ('Client #' + cid) });
            }
            ts.setValue(strId, true);
        }, 150);
    }

    /* ─── Form helpers ─────────────────────────────────────────────────── */
    function resetImportantEventForm() {
        document.getElementById('importantEventId').value = '';
        document.getElementById('importantEventTitle').value = '';
        document.getElementById('importantEventType').value = 'meeting';
        document.getElementById('importantEventDate').value = '';
        document.getElementById('importantEventStartTime').value = '09:00';
        document.getElementById('importantEventEndTime').value = '10:00';
        document.getElementById('importantEventAllDay').checked = false;
        document.getElementById('importantEventStartTime').disabled = false;
        document.getElementById('importantEventEndTime').disabled = false;
        document.getElementById('importantEventClientId').value = '';
        document.getElementById('importantEventClientEncoded').value = '';
        document.getElementById('importantEventLocation').value = '';
        document.getElementById('importantEventCalendarScope').value = BOOKING_CALENDAR_TYPE;
        document.getElementById('importantEventReminder').value = '';
        document.getElementById('importantEventNotes').value = '';
        document.getElementById('importantEventDeleteBtn').classList.add('d-none');
        document.getElementById('importantEventModalTitle').textContent = 'Add Important Event';
        impEventDestroyClientTs();
    }

    function openImportantEventModalForCreate(dateStr, timeStr) {
        resetImportantEventForm();
        if (dateStr) document.getElementById('importantEventDate').value = dateStr;
        if (timeStr && timeStr !== '00:00') {
            document.getElementById('importantEventStartTime').value = timeStr;
            const parts = timeStr.split(':');
            let h = parseInt(parts[0], 10) + 1;
            if (h > 23) h = 23;
            document.getElementById('importantEventEndTime').value =
                String(h).padStart(2, '0') + ':' + (parts[1] || '00');
        }
        $('#importantEventModal').modal('show');
    }

    function openImportantEventModalForEdit(props) {
        resetImportantEventForm();
        const id = props.staff_calendar_event_id;
        if (!id) return;
        const start = new Date(props.starts_at || props.appointment_datetime);
        document.getElementById('importantEventId').value = String(id);
        document.getElementById('importantEventModalTitle').textContent = 'Edit Important Event';
        document.getElementById('importantEventTitle').value = props.title || '';
        document.getElementById('importantEventType').value = props.event_type || 'other';
        document.getElementById('importantEventDate').value =
            start.toLocaleDateString('en-CA', { timeZone: 'Australia/Melbourne' });
        const allDay = !!props.is_all_day;
        document.getElementById('importantEventAllDay').checked = allDay;
        document.getElementById('importantEventStartTime').disabled = allDay;
        document.getElementById('importantEventEndTime').disabled = allDay;
        if (!allDay) {
            document.getElementById('importantEventStartTime').value = start.toLocaleTimeString('en-US', {
                timeZone: 'Australia/Melbourne', hour12: false, hour: '2-digit', minute: '2-digit'
            });
            const end = new Date(props.ends_at || props.appointment_datetime);
            document.getElementById('importantEventEndTime').value = end.toLocaleTimeString('en-US', {
                timeZone: 'Australia/Melbourne', hour12: false, hour: '2-digit', minute: '2-digit'
            });
        }
        document.getElementById('importantEventLocation').value = props.location || '';
        document.getElementById('importantEventNotes').value = props.notes || '';
        document.getElementById('importantEventCalendarScope').value = props.calendar_type || '';
        document.getElementById('importantEventReminder').value =
            props.reminder_minutes != null ? String(props.reminder_minutes) : '';
        if (props.client_id) {
            document.getElementById('importantEventClientId').value = String(props.client_id);
            impEventSetClientValue(props.client_id, props.client_name);
        }
        document.getElementById('importantEventDeleteBtn').classList.remove('d-none');
        $('#importantEventModal').modal('show');
    }

    /* init TomSelect when modal becomes visible */
    document.getElementById('importantEventModal').addEventListener('shown.bs.modal', function () {
        impEventInitClientTs();
    });
    document.getElementById('importantEventModal').addEventListener('hidden.bs.modal', function () {
        impEventDestroyClientTs();
    });

    /* ─── Save / Delete ─────────────────────────────────────────────────── */
    async function saveImportantEvent() {
        const title   = (document.getElementById('importantEventTitle').value || '').trim();
        const dateStr = document.getElementById('importantEventDate').value;
        if (!title || !dateStr) {
            if (typeof iziToast !== 'undefined') {
                iziToast.warning({ title: 'Required', message: 'Title and date are required.', position: 'topRight' });
            } else {
                alert('Title and date are required.');
            }
            return;
        }
        const allDay   = document.getElementById('importantEventAllDay').checked;
        const startTime = document.getElementById('importantEventStartTime').value;
        const endTime  = document.getElementById('importantEventEndTime').value;
        const startsAt = melbourneIsoFromDateAndTime(dateStr, startTime, allDay);
        const endsAt   = (!allDay && endTime)
            ? melbourneIsoFromDateAndTime(dateStr, endTime, false)
            : null;
        const scope    = document.getElementById('importantEventCalendarScope').value;
        const clientId = document.getElementById('importantEventClientId').value;
        const reminder = document.getElementById('importantEventReminder').value;

        const payload = {
            title:            title,
            event_type:       document.getElementById('importantEventType').value,
            starts_at:        startsAt,
            ends_at:          endsAt,
            is_all_day:       allDay,
            calendar_type:    scope === '' ? null : scope,
            client_id:        clientId ? parseInt(clientId, 10) : null,
            location:         (document.getElementById('importantEventLocation').value || '').trim() || null,
            notes:            (document.getElementById('importantEventNotes').value || '').trim() || null,
            reminder_minutes: reminder ? parseInt(reminder, 10) : null,
        };

        const eventId = document.getElementById('importantEventId').value;
        const isEdit  = eventId !== '';
        const url     = isEdit
            ? BOOKING_WEB_BASE + '/api/calendar-events/' + eventId
            : BOOKING_WEB_BASE + '/api/calendar-events';
        const btn = document.getElementById('importantEventSaveBtn');
        btn.disabled = true;
        try {
            const response = await fetch(url, {
                method: isEdit ? 'PUT' : 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': bookingCalendarCsrfToken()
                },
                credentials: 'same-origin',
                body: JSON.stringify(payload)
            });
            const data = await response.json();
            if (!response.ok || !data.success) throw new Error(data.message || 'Save failed');
            $('#importantEventModal').modal('hide');
            calendar.refetchEvents();
            void refreshBookingCalendarStats();
            if (typeof iziToast !== 'undefined' && iziToast.success) {
                iziToast.success({ title: 'Saved', message: 'Important event saved.', position: 'topRight' });
            }
            /* reset dismissed-reminder cache so new reminder fires correctly */
            bookingCalReminderClearCache(isEdit ? parseInt(eventId, 10) : null);
        } catch (err) {
            if (typeof iziToast !== 'undefined') {
                iziToast.error({ title: 'Error', message: err.message || 'Could not save event.', position: 'topRight' });
            } else {
                alert(err.message || 'Could not save event.');
            }
        } finally {
            btn.disabled = false;
        }
    }

    async function deleteImportantEvent() {
        const eventId = document.getElementById('importantEventId').value;
        if (!eventId || !confirm('Delete this important event?')) return;
        try {
            const response = await fetch(BOOKING_WEB_BASE + '/api/calendar-events/' + eventId, {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': bookingCalendarCsrfToken()
                },
                credentials: 'same-origin'
            });
            const data = await response.json();
            if (!response.ok || !data.success) throw new Error(data.message || 'Delete failed');
            $('#importantEventModal').modal('hide');
            calendar.refetchEvents();
            bookingCalReminderClearCache(parseInt(eventId, 10));
            if (typeof iziToast !== 'undefined' && iziToast.success) {
                iziToast.success({ title: 'Deleted', message: 'Event removed.', position: 'topRight' });
            }
        } catch (err) {
            if (typeof iziToast !== 'undefined') {
                iziToast.error({ title: 'Error', message: err.message || 'Could not delete event.', position: 'topRight' });
            } else {
                alert(err.message || 'Could not delete event.');
            }
        }
    }

    /* ─── Reminder pop-up system ─────────────────────────────────────────
     * Polls /booking/api/calendar-events/reminders every 60 s.
     * Events are dismissed per-session via sessionStorage so the banner
     * only fires once per page load.
     * ─────────────────────────────────────────────────────────────────── */
    const BOOKING_CAL_REMINDER_KEY = 'bookingCalDismissedReminders';
    const BOOKING_CAL_REMINDER_POLL_MS = 60_000;  // every 60 s

    function bookingCalReminderGetDismissed() {
        try {
            return JSON.parse(sessionStorage.getItem(BOOKING_CAL_REMINDER_KEY) || '[]');
        } catch (e) { return []; }
    }

    function bookingCalReminderDismiss(id) {
        const list = bookingCalReminderGetDismissed();
        if (!list.includes(id)) {
            list.push(id);
            sessionStorage.setItem(BOOKING_CAL_REMINDER_KEY, JSON.stringify(list));
        }
    }

    function bookingCalReminderClearCache(id) {
        if (id == null) return;
        const list = bookingCalReminderGetDismissed().filter(function (x) { return x !== id; });
        sessionStorage.setItem(BOOKING_CAL_REMINDER_KEY, JSON.stringify(list));
    }

    function bookingCalReminderMinutesLabel(mins) {
        if (!mins || mins <= 0) return '';
        if (mins < 60) return mins + ' min';
        if (mins === 60) return '1 hr';
        if (mins < 1440) return Math.round(mins / 60) + ' hrs';
        if (mins === 1440) return '1 day';
        return Math.round(mins / 1440) + ' days';
    }

    function bookingCalShowReminderToast(evt) {
        if (typeof iziToast === 'undefined') return;
        const startsAt = new Date(evt.starts_at);
        const timeStr  = startsAt.toLocaleString('en-AU', {
            timeZone: 'Australia/Melbourne',
            weekday: 'short', month: 'short', day: 'numeric',
            hour: 'numeric', minute: '2-digit', hour12: true
        });
        const typeIcon = {
            court: '⚖️', meeting: '📅', deadline: '🔴', reminder: '🔔', other: '📌'
        }[evt.event_type] || '🔔';
        const label = bookingCalReminderMinutesLabel(evt.reminder_minutes);
        const subtitle = (label ? 'In ' + label + ' · ' : '') + timeStr
            + (evt.location ? ' @ ' + evt.location : '');

        iziToast.show({
            title: typeIcon + ' ' + (evt.title || 'Upcoming event'),
            message: subtitle,
            color: evt.event_type === 'deadline' ? 'red'
                 : evt.event_type === 'court' ? 'dark'
                 : 'yellow',
            position: 'topRight',
            timeout: 12000,
            progressBar: true,
            closeOnClick: false,
            buttons: [[
                '<button><i class="fa-solid fa-circle-xmark"></i> Dismiss</button>',
                function (instance, toast) {
                    bookingCalReminderDismiss(evt.id);
                    instance.hide({ transitionOut: 'fadeOut' }, toast);
                }
            ]],
        });
        bookingCalReminderDismiss(evt.id);
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
            data.data.forEach(function (evt) {
                if (!dismissed.includes(evt.id)) {
                    bookingCalShowReminderToast(evt);
                }
            });
        } catch (e) { /* silent – never break the page */ }
    }

    /* First check after 3 s (catches events set for "right now"), then every 60 s */
    setTimeout(bookingCalPollReminders, 3000);
    setInterval(bookingCalPollReminders, BOOKING_CAL_REMINDER_POLL_MS);

    /* ─── Button / checkbox wiring ───────────────────────────────────────── */
    document.getElementById('btnAddImportantEvent').addEventListener('click', function () {
        openImportantEventModalForCreate('', '');
    });
    document.getElementById('importantEventSaveBtn').addEventListener('click', saveImportantEvent);
    document.getElementById('importantEventDeleteBtn').addEventListener('click', deleteImportantEvent);
    document.getElementById('importantEventAllDay').addEventListener('change', function () {
        const disabled = this.checked;
        document.getElementById('importantEventStartTime').disabled = disabled;
        document.getElementById('importantEventEndTime').disabled = disabled;
    });

    function updateImportantEventTypeSwatch() {
        const swatch = document.getElementById('importantEventTypeSwatch');
        const typeEl = document.getElementById('importantEventType');
        if (!swatch || !typeEl) return;
        const style = getImportantEventStyle(typeEl.value);
        swatch.style.backgroundColor = style.bg;
        swatch.style.color = style.text;
        swatch.style.borderColor = style.border;
    }

    document.getElementById('importantEventType').addEventListener('change', updateImportantEventTypeSwatch);
    document.getElementById('importantEventModal').addEventListener('shown.bs.modal', updateImportantEventTypeSwatch);

    document.getElementById('eventModalBody').addEventListener('change', function (e) {
        if (e.target && e.target.id === 'courtHearingViewReminder') {
            saveCourtHearingReminderFromView(e.target);
        }
    });

    document.getElementById('courtHearingEditBtn').addEventListener('click', function () {
        enterCourtHearingEditMode();
    });
    document.getElementById('courtHearingSaveBtn').addEventListener('click', function () {
        saveCourtHearingFromModal();
    });
    document.getElementById('courtHearingCancelEditBtn').addEventListener('click', function () {
        cancelCourtHearingEditMode();
    });
    // Pending cancellation data (used when showing cancellation modal)
    let pendingCancellationData = null;

    document.getElementById('eventModal').addEventListener('hidden.bs.modal', function () {
        if (pendingCancellationData) {
            return;
        }
        _activeCourtHearingState = null;
        setEventModalCourtHearingFooter('hidden');
        setEventModalHeader({
            title: 'Appointment Details',
            subtitle: '',
            iconHtml: '<i class="fa-solid fa-calendar-check"></i>',
            iconBg: '#1e3d60',
            iconColor: '#fff'
        });
        const vfd = document.getElementById('viewFullDetails');
        if (vfd) {
            vfd.innerHTML = '<i class="fa-solid fa-user"></i> Open Client';
            vfd.classList.add('d-none');
        }
    });
    
    // Global functions for modal actions
    function showBootstrapModal(el) {
        if (!el) return;
        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            bootstrap.Modal.getOrCreateInstance(el).show();
        } else {
            $(el).modal('show');
        }
    }

    function hideBootstrapModal(el) {
        if (!el) return;
        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            const instance = bootstrap.Modal.getInstance(el) || bootstrap.Modal.getOrCreateInstance(el);
            instance.hide();
        } else {
            $(el).modal('hide');
        }
    }

    window.updateAppointmentStatus = function(appointmentId, newStatus, triggerBtn) {
        const buttonEl = triggerBtn || (typeof event !== 'undefined' ? event.target : null);
        // For cancellation, close details first then show reason modal (Bootstrap 5 cannot stack modals)
        if (newStatus === 'cancelled') {
            pendingCancellationData = { appointmentId, button: buttonEl };
            document.getElementById('cancelReasonInput').value = '';
            document.getElementById('cancelReasonError').classList.add('d-none');
            document.getElementById('sendCancellationEmailCheck').checked = true;

            const eventModalEl = document.getElementById('eventModal');
            const cancelModalEl = document.getElementById('cancellationConfirmModal');
            const openCancelModal = function() {
                setTimeout(function() {
                    showBootstrapModal(cancelModalEl);
                }, 50);
            };

            if (eventModalEl && eventModalEl.classList.contains('show')) {
                $(eventModalEl).one('hidden.bs.modal', openCancelModal);
                hideBootstrapModal(eventModalEl);
            } else {
                openCancelModal();
            }
            return;
        }

        if (!confirm(`Are you sure you want to change the status to "${newStatus}"?`)) {
            return;
        }

        performStatusUpdate(appointmentId, newStatus, null, false, buttonEl);
    };

    // Handler for Confirm Cancellation button in modal (attach when script runs - DOM is ready)
    (function attachCancelConfirmHandler() {
        const confirmBtn = document.getElementById('confirmCancelBtn');
        const cancelModalEl = document.getElementById('cancellationConfirmModal');
        if (confirmBtn) {
            confirmBtn.addEventListener('click', function() {
                if (!pendingCancellationData) return;
                const reason = document.getElementById('cancelReasonInput').value.trim();
                if (!reason) {
                    document.getElementById('cancelReasonError').classList.remove('d-none');
                    return;
                }
                document.getElementById('cancelReasonError').classList.add('d-none');
                const sendEmail = document.getElementById('sendCancellationEmailCheck').checked;
                const payload = pendingCancellationData;
                pendingCancellationData = null;
                hideBootstrapModal(cancelModalEl);
                performStatusUpdate(payload.appointmentId, 'cancelled', reason, sendEmail, payload.button);
            });
        }
        if (cancelModalEl) {
            cancelModalEl.addEventListener('hidden.bs.modal', function() {
                if (!pendingCancellationData) return;
                pendingCancellationData = null;
                showBootstrapModal(document.getElementById('eventModal'));
            });
        }
    })();

    function performStatusUpdate(appointmentId, newStatus, cancellationReason, sendCancellationConfirmation, buttonEl) {
        // Show loading state
        const button = buttonEl || event?.target;
        const originalText = button ? button.innerHTML : '';
        if (button) {
            button.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Updating...';
            button.disabled = true;
        }

        const requestData = {
            status: newStatus
        };

        if (cancellationReason) {
            requestData.cancellation_reason = cancellationReason;
        }
        if (newStatus === 'cancelled' && sendCancellationConfirmation) {
            requestData.send_cancellation_confirmation = true;
        }

        fetch(`${BOOKING_WEB_BASE}/appointments/${appointmentId}/update-status`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            credentials: 'same-origin',
            body: JSON.stringify(requestData)
        })
        .then(async function(response) {
            var ct = response.headers.get('content-type') || '';
            var data = {};
            if (ct.indexOf('application/json') !== -1) {
                try { data = await response.json(); } catch (e) { data = {}; }
            }
            if (!response.ok) {
                var msg = data.message || data.error || ('Request failed (HTTP ' + response.status + ')');
                showAlert('danger', 'Failed to update status: ' + msg);
                return;
            }
            if (data.success === true || data.status === true) {
                var statusBadge = document.getElementById('statusBadge');
                if (statusBadge) {
                    statusBadge.textContent = newStatus.toUpperCase();
                    statusBadge.className = 'appt-status-pill appt-status-pill--' +
                        String(newStatus).toLowerCase().replace(/[^a-z0-9]+/g, '_');
                }
                hideBootstrapModal(document.getElementById('eventModal'));
                calendar.refetchEvents();
                var okMsg = data.message || 'Status updated successfully!';
                showAlert(data.sync_error ? 'warning' : 'success', okMsg);
            } else {
                showAlert('danger', 'Failed to update status: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(function(error) {
            console.error('Error updating status:', error);
            showAlert('danger', 'Failed to update status. Please try again.');
        })
        .finally(() => {
            // Restore button state
            if (button) {
                button.innerHTML = originalText;
                button.disabled = false;
            }
        });
    }
    
    window.updateAppointmentConsultant = function(crmAppointmentId, slotKey, consultantId) {
        if (!consultantId) {
            return;
        }
        
        if (!confirm('Are you sure you want to change the consultant? This will move the appointment to a different calendar.')) {
            const select = document.getElementById('consultantSelect-' + slotKey);
            if (select) select.value = '';
            return;
        }
        
        const select = document.getElementById('consultantSelect-' + slotKey);
        if (!select) return;
        const originalValue = select.value;
        select.disabled = true;
        
        fetch(`${BOOKING_WEB_BASE}/appointments/${crmAppointmentId}/update-consultant`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                consultant_id: consultantId
            })
        })
        .then(response => {
            // Check if response is OK
            if (!response.ok) {
                // Try to parse JSON error response
                return response.json().then(errorData => {
                    throw { status: response.status, data: errorData };
                }).catch(() => {
                    // If not JSON, throw with status
                    throw { status: response.status, message: 'Server error occurred' };
                });
            }
            
            // Check content type before parsing
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                throw new Error('Response is not JSON');
            }
            
            return response.json();
        })
        .then(data => {
            if (data.success) {
                // Close the modal and refresh calendar
                $('#eventModal').modal('hide');
                calendar.refetchEvents();
                
                // Show success message
                showAlert('success', 'Consultant updated successfully! The appointment has been moved to the new calendar.');
            } else {
                showAlert('danger', 'Failed to update consultant: ' + (data.message || 'Unknown error'));
                if (select) select.value = originalValue;
            }
        })
        .catch(error => {
            console.error('Error updating consultant:', error);
            
            // Handle different error types
            if (error.status === 422) {
                // Validation error
                const errorMsg = error.data?.message || 'Validation failed';
                const errors = error.data?.errors || {};
                showAlert('danger', errorMsg);
                if (Object.keys(errors).length > 0) {
                    console.error('Validation errors:', errors);
                }
            } else if (error.status === 404) {
                showAlert('danger', 'Appointment not found');
            } else if (error.status === 500) {
                const errorMsg = error.data?.message || 'Server error occurred';
                showAlert('danger', errorMsg + ' Please try again later.');
            } else if (error instanceof SyntaxError && error.message.includes('JSON')) {
                // JSON parsing error - server returned non-JSON response
                showAlert('danger', 'Server returned invalid response. Please check server logs or try again.');
                console.error('Server returned non-JSON response. Check network tab.');
            } else {
                showAlert('danger', 'Failed to update consultant. Please try again.');
            }
            
            if (select) select.value = originalValue;
        })
        .finally(() => {
            if (select) select.disabled = false;
        });
    };
    
    // Validate weekend date function
    window.validateWeekendDate = function(dateInput, appointmentId) {
        if (!dateInput.value) {
            return;
        }
        
        const selectedDate = new Date(dateInput.value);
        const dayOfWeek = selectedDate.getDay(); // 0 = Sunday, 6 = Saturday
        
        if (dayOfWeek === 0 || dayOfWeek === 6) {
            // Weekend selected - reset to original date
            const originalDate = dateInput.getAttribute('data-original-date');
            dateInput.value = originalDate;
            
            showAlert('warning', 'Weekends (Saturday and Sunday) are not available for appointments. Please select a weekday.');
            return false;
        }
        
        return true;
    };
    
    // Reschedule Date & Time function
    window.rescheduleAppointmentDateTime = function(slotKey, crmAppointmentId, meetingType, preferredLanguage) {
        const dateInput = document.getElementById(`rescheduleDate-${slotKey}`);
        const timeInput = document.getElementById(`rescheduleTime-${slotKey}`);
        
        if (!dateInput || !timeInput) {
            showAlert('danger', 'Date and time inputs not found.');
            return;
        }
        
        const newDate = dateInput.value;
        const newTime = timeInput.value;
        const originalDate = dateInput.getAttribute('data-original-date');
        const originalTime = timeInput.getAttribute('data-original-time');
        
        // Check if date or time has changed
        if (newDate === originalDate && newTime === originalTime) {
            showAlert('info', 'No changes detected. Date and time remain the same.');
            return;
        }
        
        if (!newDate || !newTime) {
            showAlert('danger', 'Please select both date and time.');
            return;
        }
        
        // Validate that the selected date is not a weekend
        const selectedDate = new Date(newDate);
        const dayOfWeek = selectedDate.getDay(); // 0 = Sunday, 6 = Saturday
        
        if (dayOfWeek === 0 || dayOfWeek === 6) {
            showAlert('warning', 'Weekends (Saturday and Sunday) are not available for appointments. Please select a weekday.');
            // Reset to original date
            dateInput.value = originalDate;
            return;
        }
        
        if (!confirm(`Are you sure you want to reschedule this appointment to ${newDate} at ${newTime}?`)) {
            // Reset to original values
            dateInput.value = originalDate;
            timeInput.value = originalTime;
            return;
        }
        
        // Show loading state
        const button = event.target;
        const originalButtonHtml = button.innerHTML;
        button.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Updating...';
        button.disabled = true;
        dateInput.disabled = true;
        timeInput.disabled = true;
        
        // Prepare form data (using FormData for PUT request with _method)
        const formData = new FormData();
        formData.append('_method', 'PUT');
        formData.append('appointment_date', newDate);
        formData.append('appointment_time', newTime);
        formData.append('meeting_type', meetingType);
        formData.append('preferred_language', preferredLanguage);
        
        fetch(`${BOOKING_WEB_BASE}/appointments/${crmAppointmentId}`, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            credentials: 'same-origin',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                return response.json().then(
                    function(errorData) {
                        throw { status: response.status, data: errorData };
                    },
                    function() {
                        throw { status: response.status, message: 'Server error occurred' };
                    }
                );
            }
            
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                // If response is not JSON (e.g., redirect), consider it success
                return { success: true, message: 'Appointment updated successfully' };
            }
            
            return response.json();
        })
        .then(data => {
            if (data.success !== false) {
                // Update original values
                dateInput.setAttribute('data-original-date', newDate);
                dateInput.setAttribute('data-original-time', newTime);
                
                // Close the modal and refresh calendar
                $('#eventModal').modal('hide');
                calendar.refetchEvents();
                
                // Show success message
                const message = data.message || 'Appointment date and time updated successfully!';
                showAlert('success', message);
            } else {
                showAlert('danger', 'Failed to update appointment: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Error rescheduling appointment:', error);
            
            // Handle different error types
            if (error.status === 422) {
                // Validation error
                const errorMsg = error.data?.message || 'Validation failed';
                const errors = error.data?.errors || {};
                let errorDetails = errorMsg;
                
                if (Object.keys(errors).length > 0) {
                    const errorList = Object.values(errors).flat().join(', ');
                    errorDetails = errorMsg + ': ' + errorList;
                }
                
                showAlert('danger', errorDetails);
            } else if (error.status === 404) {
                showAlert('danger', 'Appointment not found');
            } else if (error.status === 500) {
                const errorMsg = error.data?.message || 'Server error occurred';
                showAlert('danger', errorMsg + ' Please try again later.');
            } else if (error instanceof SyntaxError && error.message.includes('JSON')) {
                showAlert('danger', 'Server returned invalid response. Please check server logs or try again.');
                console.error('Server returned non-JSON response. Check network tab.');
            } else {
                showAlert('danger', 'Failed to reschedule appointment. Please try again.');
            }
            
            // Reset to original values on error
            dateInput.value = originalDate;
            timeInput.value = originalTime;
        })
        .finally(() => {
            // Restore button and input states
            button.innerHTML = originalButtonHtml;
            button.disabled = false;
            dateInput.disabled = false;
            timeInput.disabled = false;
        });
    };
    
    // Meeting Type functions
    window.showMeetingTypeDropdown = function(slotKey, currentMeetingType) {
        const display = document.getElementById(`meetingTypeDisplay-${slotKey}`);
        const select = document.getElementById(`meetingTypeSelect-${slotKey}`);
        
        if (display && select) {
            display.classList.add('d-none');
            select.classList.remove('d-none');
            select.focus();
            
            // Store original value for potential cancellation
            select.setAttribute('data-original-value', currentMeetingType);
            
            // Add click outside handler to close dropdown if user clicks elsewhere
            setTimeout(() => {
                const clickOutsideHandler = function(e) {
                    if (!select.contains(e.target) && !display.contains(e.target)) {
                        // Only close if value hasn't changed (user clicked away without selecting)
                        if (select.value === currentMeetingType) {
                            display.classList.remove('d-none');
                            select.classList.add('d-none');
                        }
                        document.removeEventListener('click', clickOutsideHandler);
                    }
                };
                // Use setTimeout to avoid immediate trigger
                setTimeout(() => {
                    document.addEventListener('click', clickOutsideHandler);
                }, 100);
            }, 10);
        }
    };
    
    window.updateAppointmentMeetingType = function(crmAppointmentId, slotKey, newMeetingType) {
        if (!newMeetingType) {
            return;
        }
        
        const select = document.getElementById(`meetingTypeSelect-${slotKey}`);
        const display = document.getElementById(`meetingTypeDisplay-${slotKey}`);
        const originalValue = select.getAttribute('data-original-value') || select.value;
        
        // Disable select and show loading
        select.disabled = true;
        const originalSelectHtml = select.innerHTML;
        select.innerHTML = '<option>Updating...</option>';
        
        fetch(`${BOOKING_WEB_BASE}/appointments/${crmAppointmentId}/update-meeting-type`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                meeting_type: newMeetingType
            })
        })
        .then(response => {
            if (!response.ok) {
                return response.json().then(errorData => {
                    throw { status: response.status, data: errorData };
                }).catch(() => {
                    throw { status: response.status, message: 'Server error occurred' };
                });
            }
            
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                throw new Error('Response is not JSON');
            }
            
            return response.json();
        })
        .then(data => {
            if (data.success) {
                // Update the display text
                if (display && select) {
                    // Format the new meeting type for display
                    const newDisplay = newMeetingType.split('_').map(word => 
                        word.charAt(0).toUpperCase() + word.slice(1)
                    ).join(' ');
                    
                    // Update display text
                    display.innerHTML = `${newDisplay} <i class="fa-solid fa-pen-to-square ms-1" style="font-size: 0.8em;"></i>`;
                    
                    // Update select value
                    select.value = newMeetingType;
                    select.setAttribute('data-original-value', newMeetingType);
                    
                    // Hide dropdown and show display
                    display.classList.remove('d-none');
                    select.classList.add('d-none');
                }
                
                // Refresh calendar to update event display
                calendar.refetchEvents();
                
                // Show success message
                showAlert('success', 'Meeting type updated successfully!');
            } else {
                showAlert('danger', 'Failed to update meeting type: ' + (data.message || 'Unknown error'));
                select.value = originalValue;
            }
        })
        .catch(error => {
            console.error('Error updating meeting type:', error);
            
            if (error.status === 422) {
                const errorMsg = error.data?.message || 'Validation failed';
                showAlert('danger', errorMsg);
            } else if (error.status === 404) {
                showAlert('danger', 'Appointment not found');
            } else if (error.status === 500) {
                const errorMsg = error.data?.message || 'Server error occurred';
                showAlert('danger', errorMsg + ' Please try again later.');
            } else {
                showAlert('danger', 'Failed to update meeting type. Please try again.');
            }
            
            // Restore original select options and value
            select.innerHTML = originalSelectHtml;
            select.value = originalValue;
        })
        .finally(() => {
            select.disabled = false;
        });
    };
    
    function showAlert(type, message) {
        // Create alert element
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="close" data-bs-dismiss="alert">
                <span>&times;</span>
            </button>
        `;
        
        // Insert at the top of the page
        const container = document.querySelector('.section-body');
        if (container) {
            container.insertBefore(alertDiv, container.firstChild);
            
            // Auto-dismiss after 5 seconds
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.remove();
                }
            }, 5000);
        }
    }
    }); // Close waitForFullCalendar callback
}); // Close DOMContentLoaded
</script>

<style>
/* Booking calendar — docs/theme.md (Powder Blue & Soft Gold); beats client-detail.css .card-header-action */
.booking-calendar-page {
    background: var(--page-bg);
    border-radius: 10px;
    padding: 4px 0 8px;
    color: var(--text-dark);
}

.booking-calendar-page .card {
    border: 1px solid var(--border);
    border-radius: 10px;
    box-shadow: 0 1px 4px rgba(30, 61, 96, 0.06);
    overflow: hidden;
}

/* Card title row — theme.md: Top Bar / page title — navy 18px 700 on --header-bg */
.booking-calendar-page .card-header {
    display: flex !important;
    align-items: center !important;
    justify-content: space-between !important;
    flex-wrap: wrap !important;
    gap: 12px !important;
    background: var(--header-bg) !important;
    background-image: none !important;
    color: var(--navy) !important;
    border-bottom: 1px solid var(--border) !important;
    padding: 16px 20px !important;
}

.booking-calendar-page .card-header h4 {
    flex: 1 1 auto !important;
    min-width: 0 !important;
    margin: 0 !important;
    font-size: 18px !important;
    font-weight: 700 !important;
    line-height: 1.35 !important;
    color: var(--navy) !important;
}

.booking-calendar-page .card-header .text-muted {
    color: var(--text-muted) !important;
    font-weight: 600 !important;
    font-size: 13px !important;
}

.booking-calendar-page .card-header .card-header-action {
    display: flex !important;
    align-items: center !important;
    margin-bottom: 0 !important;
    flex-shrink: 0 !important;
}

.booking-calendar-page .card-body {
    color: var(--text-dark) !important;
    background: var(--card-bg) !important;
}

/* theme.md Buttons — primary: navy; outline: border --border, hover --sidebar-bg */
.booking-calendar-page .btn-primary,
.booking-calendar-page .booking-calendar-page__refresh {
    background-color: var(--navy) !important;
    border: 1px solid var(--navy) !important;
    color: #fff !important;
    font-weight: 600 !important;
}

.booking-calendar-page .btn-primary:hover,
.booking-calendar-page .btn-primary:focus,
.booking-calendar-page .booking-calendar-page__refresh:hover,
.booking-calendar-page .booking-calendar-page__refresh:focus {
    background-color: var(--sidebar-active) !important;
    border-color: var(--sidebar-active) !important;
    color: #fff !important;
}

.booking-calendar-page .btn-secondary {
    background: var(--card-bg) !important;
    border: 1px solid var(--border) !important;
    color: var(--navy) !important;
    font-weight: 600 !important;
}

.booking-calendar-page .btn-secondary:hover,
.booking-calendar-page .btn-secondary:focus {
    background: var(--sidebar-bg) !important;
    border-color: var(--border) !important;
    color: var(--navy) !important;
}

.booking-calendar-page .btn-outline-primary {
    color: var(--navy) !important;
    border: 1px solid var(--border) !important;
    background-color: transparent !important;
    font-weight: 600 !important;
}

.booking-calendar-page .btn-outline-primary:hover,
.booking-calendar-page .btn-outline-primary:focus,
.booking-calendar-page .btn-group .btn-outline-primary:hover {
    background-color: var(--sidebar-bg) !important;
    border-color: var(--border) !important;
    color: var(--navy) !important;
}

.booking-calendar-page .btn-group .btn-primary {
    background-color: var(--navy) !important;
    border-color: var(--navy) !important;
    color: #fff !important;
}

.booking-calendar-page .btn-group .btn-primary:hover,
.booking-calendar-page .btn-group .btn-primary:focus {
    background-color: var(--sidebar-active) !important;
    border-color: var(--sidebar-active) !important;
}

.booking-calendar-page .btn-success {
    background-color: var(--success) !important;
    border-color: var(--success) !important;
    color: #fff !important;
    font-weight: 600 !important;
}

.booking-calendar-page .btn-success:hover,
.booking-calendar-page .btn-success:focus {
    filter: brightness(0.95);
    color: #fff !important;
}

.booking-calendar-link {
    color: var(--sidebar-active) !important;
    text-decoration: underline;
}

.booking-calendar-link--action {
    cursor: pointer;
}

/* theme.md KPI Cards */
.calendar-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 12px;
    margin-bottom: 20px;
}

.stat-box {
    position: relative;
    text-align: center;
    padding: 16px 12px 14px;
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: 10px;
    box-shadow: 0 1px 4px rgba(30, 61, 96, 0.06);
    transition: transform 0.15s ease, box-shadow 0.15s ease;
}

.stat-box:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(30, 61, 96, 0.1);
}

.stat-box__icon {
    width: 32px;
    height: 32px;
    margin: 0 auto 8px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
}

.stat-box--this-month .stat-box__icon { background: rgba(30, 61, 96, 0.1); color: var(--navy); }
.stat-box--today .stat-box__icon { background: rgba(40, 167, 69, 0.12); color: var(--success); }
.stat-box--upcoming .stat-box__icon { background: rgba(58, 111, 168, 0.12); color: var(--sidebar-active); }
.stat-box--pending .stat-box__icon { background: rgba(200, 153, 42, 0.15); color: var(--accent-gold, #c8992a); }
.stat-box--paid .stat-box__icon { background: rgba(30, 61, 96, 0.1); color: var(--navy); }
.stat-box--no-show .stat-box__icon { background: rgba(94, 122, 144, 0.12); color: var(--text-muted); }

.stat-box h3 {
    margin: 0;
    font-size: 26px;
    font-weight: 700;
    color: var(--text-dark);
    line-height: 1.1;
}

.stat-box p {
    margin: 6px 0 0;
    color: var(--text-muted);
    font-weight: 600;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.6px;
}

.calendar-legend-panel {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin-bottom: 20px;
    padding: 14px 16px;
    background: var(--page-bg);
    border: 1px solid var(--border);
    border-radius: 10px;
}

.calendar-legend-group {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 10px 16px;
}

.calendar-legend-group__label {
    flex: 0 0 auto;
    min-width: 110px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.7px;
    color: var(--text-muted);
}

.calendar-legend {
    display: flex;
    flex-wrap: wrap;
    gap: 8px 14px;
    flex: 1 1 auto;
    color: var(--text-dark);
}

.legend-item {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 12.5px;
    font-weight: 500;
    padding: 4px 10px 4px 6px;
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: 999px;
}

.legend-color {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    flex-shrink: 0;
}

.legend-color.event-pending {
    background-color: rgba(200, 153, 42, 0.55);
    border: 1px solid var(--accent-gold);
}

.legend-color.event-paid {
    background-color: var(--navy);
}

.legend-color.event-confirmed {
    background-color: var(--success);
}

.legend-color.event-completed {
    background-color: var(--sidebar-active);
}

.legend-color.event-cancelled {
    background-color: var(--danger);
}

.legend-color.event-no-show {
    background-color: var(--text-muted);
}

.legend-color.event-court {
    background-color: #5c3d8f;
}

.legend-color.event-meeting {
    background-color: #0d6efd;
}

.legend-color.event-deadline {
    background-color: #c0392b;
}

.legend-color.event-reminder {
    background-color: #d97706;
}

.legend-color.event-other {
    background-color: #5e7a90;
}

.calendar-v6-wrapper {
    padding: 4px;
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: 10px;
    overflow: hidden;
}

.calendar-v6-wrapper .calendar-v6-container {
    padding: 8px 12px 12px;
}

/* Modals — same title treatment as page header (theme.md Top Bar) */
#cancellationConfirmModal.modal {
    z-index: 1065;
}

.booking-calendar-modal .modal-content {
    border: 1px solid var(--border);
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 8px 32px rgba(30, 61, 96, 0.14);
}

.booking-calendar-modal .modal-header {
    background: var(--header-bg) !important;
    color: var(--navy) !important;
    border-bottom: 1px solid var(--border) !important;
    padding: 16px 20px !important;
    align-items: flex-start;
}

.booking-calendar-modal .modal-title {
    font-size: 18px !important;
    font-weight: 700 !important;
    color: var(--navy) !important;
    margin: 0 !important;
}

.booking-calendar-modal .modal-header .close {
    color: var(--text-muted) !important;
    opacity: 1 !important;
    text-shadow: none !important;
    font-size: 1.5rem !important;
    font-weight: 400 !important;
    line-height: 1 !important;
}

.booking-calendar-modal .modal-header .close:hover,
.booking-calendar-modal .modal-header .close:focus {
    color: var(--navy) !important;
}

.booking-calendar-modal .modal-header .btn-close {
    opacity: 0.55;
    margin-top: 2px;
}

.booking-calendar-modal .modal-header .btn-close:hover,
.booking-calendar-modal .modal-header .btn-close:focus {
    opacity: 1;
}

.booking-calendar-modal .modal-body {
    color: var(--text-dark);
    background: var(--card-bg);
    padding: 20px 22px;
}

.booking-calendar-modal .form-label {
    font-size: 13px;
    font-weight: 600;
    color: var(--navy);
    margin-bottom: 6px;
}

.booking-calendar-modal .form-control,
.booking-calendar-modal .form-select {
    border-radius: 8px;
    border: 1px solid var(--border);
    padding: 0.5rem 0.75rem;
    font-size: 0.9375rem;
    color: var(--text-dark);
    background-color: var(--card-bg);
}

.booking-calendar-modal .form-control:focus,
.booking-calendar-modal .form-select:focus {
    border-color: var(--sidebar-active);
    box-shadow: 0 0 0 3px rgba(58, 111, 168, 0.15);
}

.booking-calendar-modal .form-text {
    font-size: 12px;
    color: var(--text-muted);
    margin-top: 4px;
}

.booking-calendar-modal .modal-footer {
    background: var(--page-bg);
    border-top: 1px solid var(--border);
    padding: 14px 20px;
}

/* Important event modal */
.important-event-modal__heading {
    display: flex;
    align-items: flex-start;
    gap: 14px;
}

.important-event-modal__icon {
    flex-shrink: 0;
    width: 44px;
    height: 44px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    background: #0d6efd;
    color: #fff;
    border: 2px solid transparent;
    transition: background-color 0.2s ease, color 0.2s ease;
}

.important-event-modal__subtitle {
    font-size: 13px;
    color: var(--text-muted);
    margin-top: 2px;
    font-weight: 500;
}

.important-event-section {
    margin-bottom: 20px;
    padding-bottom: 20px;
    border-bottom: 1px solid var(--border);
}

.important-event-section--last {
    margin-bottom: 16px;
    padding-bottom: 0;
    border-bottom: none;
}

.important-event-section__title {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    color: var(--text-muted);
    margin-bottom: 12px;
}

.important-event-section__title i {
    color: var(--sidebar-active);
    font-size: 13px;
}

.important-event-all-day {
    min-height: 38px;
    display: flex;
    align-items: center;
    padding: 0 4px;
    margin-bottom: 0;
}

.important-event-all-day .form-check-label {
    font-size: 13px;
    font-weight: 600;
    color: var(--text-dark);
}

.important-event-tip {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 12px 14px;
    background: rgba(58, 111, 168, 0.08);
    border: 1px solid rgba(58, 111, 168, 0.18);
    border-radius: 8px;
    font-size: 13px;
    color: var(--text-dark);
    line-height: 1.45;
}

.important-event-tip i {
    color: var(--sidebar-active);
    margin-top: 2px;
    flex-shrink: 0;
}

.important-event-modal__footer {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
}

.important-event-modal__footer-actions {
    display: flex;
    gap: 8px;
}

.important-event-client-select + .ts-wrapper,
.important-event-client-select + .ts-wrapper .ts-control {
    width: 100% !important;
}

@media (max-width: 576px) {
    .calendar-legend-group {
        flex-direction: column;
        align-items: flex-start;
    }

    .calendar-legend-group__label {
        min-width: 0;
    }

    .important-event-modal__footer-actions {
        width: 100%;
        justify-content: stretch;
    }

    .important-event-modal__footer-actions .btn {
        flex: 1 1 auto;
    }
}

.booking-calendar-modal .modal-footer .btn-primary {
    background: var(--navy) !important;
    border: 1px solid var(--navy) !important;
    color: #fff !important;
    font-weight: 600 !important;
}

.booking-calendar-modal .modal-footer .btn-primary:hover,
.booking-calendar-modal .modal-footer .btn-primary:focus {
    background: var(--sidebar-active) !important;
    border-color: var(--sidebar-active) !important;
    color: #fff !important;
}

.booking-calendar-modal .modal-footer .btn-danger {
    background: var(--danger) !important;
    border: 1px solid var(--danger) !important;
    color: #fff !important;
    font-weight: 600 !important;
}

.booking-calendar-modal .modal-footer .btn-danger:hover,
.booking-calendar-modal .modal-footer .btn-danger:focus {
    filter: brightness(0.95);
    color: #fff !important;
}

.booking-calendar-modal .modal-footer .btn-secondary {
    background: var(--card-bg) !important;
    border: 1px solid var(--border) !important;
    color: var(--navy) !important;
    font-weight: 600 !important;
}

.booking-calendar-modal .modal-footer .btn-secondary:hover,
.booking-calendar-modal .modal-footer .btn-secondary:focus {
    background: var(--sidebar-bg) !important;
    color: var(--navy) !important;
}

/* Appointment detail modal */
.appointment-detail-modal__heading {
    display: flex;
    align-items: flex-start;
    gap: 14px;
}

.appointment-detail-modal__icon {
    flex-shrink: 0;
    width: 44px;
    height: 44px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    background: var(--navy);
    color: #fff;
    transition: background-color 0.2s ease, color 0.2s ease;
}

.appointment-detail-modal__subtitle {
    font-size: 13px;
    color: var(--text-muted);
    margin-top: 2px;
    font-weight: 500;
}

.appointment-detail-modal__footer {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
}

.appointment-detail-modal__footer-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.appt-detail-hero {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px 16px;
    padding: 16px 18px;
    border-radius: 10px;
    margin-bottom: 18px;
    border: 1px solid var(--border);
}

.appt-detail-hero--court {
    background: linear-gradient(135deg, rgba(92, 61, 143, 0.1) 0%, rgba(92, 61, 143, 0.04) 100%);
    border-color: rgba(92, 61, 143, 0.2);
}

.appt-detail-hero--booking {
    background: linear-gradient(135deg, rgba(30, 61, 96, 0.08) 0%, rgba(58, 111, 168, 0.05) 100%);
    border-color: rgba(30, 61, 96, 0.15);
}

.appt-detail-hero--compact {
    margin-bottom: 16px;
    padding: 14px 16px;
}

.appt-detail-hero__client {
    font-size: 1.15rem;
    font-weight: 700;
    color: var(--navy);
    line-height: 1.3;
}

.appt-detail-hero__client a {
    color: var(--sidebar-active);
    text-decoration: none;
    font-weight: 700;
}

.appt-detail-hero__client a:hover {
    text-decoration: underline;
}

.appt-detail-hero__when {
    margin-top: 6px;
    font-size: 14px;
    color: var(--text-muted);
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 8px;
}

.appt-detail-hero__when i {
    color: var(--sidebar-active);
    font-size: 13px;
}

.appt-detail-hero__meta {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    align-items: center;
}

.appt-type-pill {
    display: inline-flex;
    align-items: center;
    padding: 4px 10px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0.02em;
}

.appt-type-pill--court {
    background: rgba(92, 61, 143, 0.14);
    color: #5c3d8f;
    border: 1px solid rgba(92, 61, 143, 0.25);
}

.appt-status-pill {
    display: inline-flex;
    align-items: center;
    padding: 4px 10px;
    border-radius: 999px;
    font-size: 11.5px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    background: var(--page-bg);
    color: var(--text-muted);
    border: 1px solid var(--border);
}

.appt-status-pill--pending { background: rgba(200, 153, 42, 0.15); color: #8a6918; border-color: rgba(200, 153, 42, 0.35); }
.appt-status-pill--paid,
.appt-status-pill--payment.appt-status-pill--paid { background: rgba(30, 61, 96, 0.12); color: var(--navy); border-color: rgba(30, 61, 96, 0.25); }
.appt-status-pill--confirmed { background: rgba(30, 122, 82, 0.12); color: #1e7a52; border-color: rgba(30, 122, 82, 0.28); }
.appt-status-pill--completed { background: rgba(58, 111, 168, 0.12); color: var(--sidebar-active); border-color: rgba(58, 111, 168, 0.28); }
.appt-status-pill--cancelled { background: rgba(168, 48, 32, 0.1); color: var(--danger); border-color: rgba(168, 48, 32, 0.25); }
.appt-status-pill--no_show { background: rgba(94, 122, 144, 0.12); color: var(--text-muted); border-color: var(--border); }
.appt-status-pill--scheduled { background: rgba(58, 111, 168, 0.12); color: var(--sidebar-active); border-color: rgba(58, 111, 168, 0.28); }
.appt-status-pill--adjourned { background: rgba(200, 153, 42, 0.12); color: #8a6918; border-color: rgba(200, 153, 42, 0.28); }
.appt-status-pill--payment.appt-status-pill--unpaid { background: rgba(94, 122, 144, 0.1); color: var(--text-muted); border-color: var(--border); }

.appt-detail-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
    margin-bottom: 18px;
}

.appt-detail-grid--compact {
    margin-bottom: 16px;
}

.appt-detail-item {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 12px 14px;
    background: var(--page-bg);
    border: 1px solid var(--border);
    border-radius: 8px;
    min-height: 100%;
}

.appt-detail-item__icon {
    flex-shrink: 0;
    width: 32px;
    height: 32px;
    border-radius: 8px;
    background: var(--card-bg);
    border: 1px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--sidebar-active);
    font-size: 13px;
}

.appt-detail-item__label {
    display: block;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--text-muted);
    margin-bottom: 3px;
}

.appt-detail-item__value {
    font-size: 14px;
    font-weight: 600;
    color: var(--text-dark);
    line-height: 1.35;
    word-break: break-word;
}

.appt-detail-section {
    margin-bottom: 16px;
    padding-top: 4px;
}

.appt-detail-section--actions {
    padding: 14px 16px;
    background: var(--page-bg);
    border: 1px solid var(--border);
    border-radius: 10px;
    margin-bottom: 12px;
}

.appt-detail-section__title {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    color: var(--text-muted);
    margin-bottom: 12px;
}

.appt-detail-section__title i {
    color: var(--sidebar-active);
    font-size: 13px;
}

.appt-detail-reminder {
    padding: 14px 16px;
    background: rgba(217, 119, 6, 0.06);
    border: 1px solid rgba(217, 119, 6, 0.18);
    border-radius: 8px;
}

.appt-detail-reminder-sent {
    margin-top: 8px;
    font-size: 12.5px;
    font-weight: 600;
    color: var(--success);
}

.appt-detail-reminder-status {
    margin-top: 6px;
    font-size: 12.5px;
    font-weight: 600;
}

.appt-detail-notes {
    padding: 12px 14px;
    background: var(--page-bg);
    border: 1px solid var(--border);
    border-radius: 8px;
    font-size: 14px;
    line-height: 1.5;
    color: var(--text-dark);
    white-space: pre-wrap;
}

.appt-detail-tip {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 12px 14px;
    background: rgba(58, 111, 168, 0.08);
    border: 1px solid rgba(58, 111, 168, 0.18);
    border-radius: 8px;
    font-size: 13px;
    color: var(--text-dark);
    line-height: 1.45;
    margin-top: 8px;
}

.appt-detail-tip i {
    color: var(--sidebar-active);
    margin-top: 2px;
    flex-shrink: 0;
}

.appt-action-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.appt-action-buttons .btn {
    font-weight: 600;
}

@media (max-width: 768px) {
    .appt-detail-grid {
        grid-template-columns: 1fr;
    }

    .appointment-detail-modal__footer-actions {
        width: 100%;
    }

    .appointment-detail-modal__footer-actions .btn {
        flex: 1 1 auto;
    }
}
</style>

@endsection

