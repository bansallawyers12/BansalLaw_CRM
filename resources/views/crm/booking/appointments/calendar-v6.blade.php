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
                    <i class="fas fa-arrow-left"></i> Back to List
                </a>
                <div class="btn-group ml-2" role="group">
                    <a href="{{ route('booking.appointments.calendar', ['type' => 'ajay']) }}" 
                       class="btn btn-sm {{ $type === 'ajay' ? 'btn-primary' : 'btn-outline-primary' }}">
                        <i class="fas fa-calendar-alt"></i> Ajay Calendar
                    </a>
                    <a href="{{ route('booking.appointments.calendar', ['type' => 'kunal']) }}" 
                       class="btn btn-sm {{ $type === 'kunal' ? 'btn-primary' : 'btn-outline-primary' }}">
                        <i class="fas fa-calendar-alt"></i> Michael
                    </a>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h4>
                        <i class="fas fa-calendar-alt mr-2"></i>
                        {{ $calendarTitle }}
                        <small class="text-muted">(Website Bookings - v6)</small>
                    </h4>
                    <div class="card-header-action">
                        <button type="button" class="btn btn-sm btn-success mr-2" id="btnAddImportantEvent">
                            <i class="fas fa-plus"></i> Add Important Event
                        </button>
                        <button type="button" onclick="location.reload()" class="btn btn-sm btn-primary booking-calendar-page__refresh">
                            <i class="fas fa-sync"></i> Refresh
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Stats -->
                    <div class="calendar-stats">
                        <div class="stat-box" data-calendar-stat="this_month">
                            <h3>{{ $stats['this_month'] ?? 0 }}</h3>
                            <p>This Month</p>
                        </div>
                        <div class="stat-box" data-calendar-stat="today">
                            <h3>{{ $stats['today'] ?? 0 }}</h3>
                            <p>Today</p>
                        </div>
                        <div class="stat-box" data-calendar-stat="upcoming">
                            <h3>{{ $stats['upcoming'] ?? 0 }}</h3>
                            <p>Upcoming</p>
                        </div>
                        <div class="stat-box" data-calendar-stat="pending">
                            <h3>{{ $stats['pending'] ?? 0 }}</h3>
                            <p>Payment Pending</p>
                        </div>
                        <div class="stat-box" data-calendar-stat="paid">
                            <h3>{{ $stats['paid'] ?? 0 }}</h3>
                            <p>Paid</p>
                        </div>
                        <div class="stat-box" data-calendar-stat="no_show">
                            <h3>{{ $stats['no_show'] ?? 0 }}</h3>
                            <p>No Show</p>
                        </div>
                    </div>

                    <!-- Legend -->
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
                        <div class="legend-item legend-item--divider"></div>
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

                    <!-- Calendar -->
                    <div id="calendar" class="calendar-v6-container"></div>
                </div>
            </div>
        </div>
    </div>
    </div>
</div>

<!-- Event Detail Modal (scoped styles: .booking-calendar-modal — portaled next to body) -->
<div class="modal fade booking-calendar-modal" id="eventModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Appointment Details</h5>
                <button type="button" class="close" data-bs-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body" id="eventModalBody">
                <!-- Content will be loaded dynamically -->
            </div>
            <div class="modal-footer">
                <a href="#" id="viewFullDetails" class="btn btn-primary" target="_blank">View Full Details</a>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Important event create / edit -->
<div class="modal fade booking-calendar-modal" id="importantEventModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="importantEventModalTitle">Add Important Event</h5>
                <button type="button" class="close" data-bs-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="importantEventId" value="">
                {{-- row 1: title + type --}}
                <div class="form-row">
                    <div class="form-group col-md-8">
                        <label for="importantEventTitle">Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="importantEventTitle" maxlength="255"
                               placeholder="e.g. Federal Court mention, team meeting">
                    </div>
                    <div class="form-group col-md-4">
                        <label for="importantEventType">Type <span class="text-danger">*</span></label>
                        <select class="form-control" id="importantEventType">
                            <option value="court">Court / Hearing</option>
                            <option value="meeting">Meeting</option>
                            <option value="deadline">Deadline</option>
                            <option value="reminder">Reminder</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                </div>
                {{-- row 2: date / times --}}
                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label for="importantEventDate">Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="importantEventDate">
                    </div>
                    <div class="form-group col-md-4">
                        <label for="importantEventStartTime">Start time</label>
                        <input type="time" class="form-control" id="importantEventStartTime">
                    </div>
                    <div class="form-group col-md-4">
                        <label for="importantEventEndTime">End time</label>
                        <input type="time" class="form-control" id="importantEventEndTime">
                    </div>
                </div>
                <div class="form-group">
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input" id="importantEventAllDay">
                        <label class="custom-control-label" for="importantEventAllDay">All day</label>
                    </div>
                </div>
                {{-- row 3: client search --}}
                <div class="form-group">
                    <label for="importantEventClientSelect">
                        <i class="fas fa-user mr-1"></i>Link to client
                        <small class="text-muted">(optional)</small>
                    </label>
                    <input type="hidden" id="importantEventClientId" value="">
                    <input type="hidden" id="importantEventClientEncoded" value="">
                    <select id="importantEventClientSelect" class="form-control" style="width:100%"></select>
                    <small class="text-muted">Type name, email, phone or ref to search</small>
                </div>
                {{-- row 4: location + calendar scope --}}
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="importantEventLocation">Location</label>
                        <input type="text" class="form-control" id="importantEventLocation" maxlength="255"
                               placeholder="Court name, room, or address">
                    </div>
                    <div class="form-group col-md-6">
                        <label for="importantEventCalendarScope">Show on calendar</label>
                        <select class="form-control" id="importantEventCalendarScope">
                            <option value="{{ $type }}">This calendar only ({{ $calendarTitle }})</option>
                            <option value="">Both Ajay &amp; Michael calendars</option>
                        </select>
                    </div>
                </div>
                {{-- row 5: reminder --}}
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="importantEventReminder">
                            <i class="fas fa-bell mr-1 text-warning"></i>Pop-up reminder
                        </label>
                        <select class="form-control" id="importantEventReminder">
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
                        <small class="text-muted">A banner will pop up on screen when the reminder is due</small>
                    </div>
                    <div class="form-group col-md-6">
                        <label for="importantEventNotes">Notes</label>
                        <textarea class="form-control" id="importantEventNotes" rows="2" maxlength="5000"
                                  placeholder="Optional details"></textarea>
                    </div>
                </div>
                <p class="text-muted small mb-0">
                    <i class="fas fa-info-circle"></i>
                    Court dates added on a <strong>client profile → Court Dates tab</strong> also appear here automatically.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger d-none" id="importantEventDeleteBtn">
                    <i class="fas fa-trash"></i> Delete
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="importantEventSaveBtn">
                    <i class="fas fa-save"></i> Save
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Cancellation Confirmation Modal -->
<div class="modal fade booking-calendar-modal" id="cancellationConfirmModal" tabindex="-1" role="dialog" data-backdrop="static">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Cancellation</h5>
                <button type="button" class="close" data-bs-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p class="mb-3">Are you sure you want to change the status to <strong>cancelled</strong>?</p>
                <div class="form-group">
                    <label for="cancelReasonInput">Cancellation reason <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="cancelReasonInput" placeholder="Enter cancellation reason" required>
                    <small class="text-danger d-none" id="cancelReasonError">Cancellation reason is required.</small>
                </div>
                <div class="form-group mb-0">
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input" id="sendCancellationEmailCheck" checked>
                        <label class="custom-control-label" for="sendCancellationEmailCheck">Send cancellation confirmation to client</label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmCancelBtn">
                    <i class="fas fa-times"></i> Confirm Cancellation
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
        console.log('✅ FullCalendar v6 detected, initializing calendar...');
        callback();
        return;
    }
    let attempts = 0;
    const checkInterval = setInterval(() => {
        attempts++;
        if (fullCalendarReady()) {
            clearInterval(checkInterval);
            console.log('✅ FullCalendar v6 detected, initializing calendar...');
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

document.addEventListener('DOMContentLoaded', function() {
    console.log('Waiting for FullCalendar v6 to load...');
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
            console.log('Loading events for v6...', {
                start: fetchInfo.startStr,
                end: fetchInfo.endStr
            });
            
            try {
                const rows = await fetchBookingCalendarEvents(fetchInfo);
                console.log('Calendar rows:', rows.length);

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
                
                console.log('Processed events:', events.length);
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
            console.log('Event clicked:', info.event);
            
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
            
            const meetingTypeRow = canManage ? `
                            <p><strong>Meeting Type:</strong> 
                                <span id="meetingTypeDisplay-${slotKey}" class="booking-calendar-link booking-calendar-link--action" onclick="showMeetingTypeDropdown('${slotKey}', '${meetingTypeRaw}')" title="Click to change meeting type">
                                    ${meetingTypeDisplay}
                                    <i class="fas fa-edit ml-1" style="font-size: 0.8em;"></i>
                                </span>
                                <select id="meetingTypeSelect-${slotKey}" class="form-control form-control-sm d-none" style="max-width: 200px; display: inline-block;" onchange="updateAppointmentMeetingType(${manageId}, '${slotKey}', this.value)" data-is-paid="${props.is_paid}">
                                    <option value="in_person" ${props.meeting_type === 'in_person' ? 'selected' : ''}>In Person</option>
                                    <option value="phone" ${props.meeting_type === 'phone' ? 'selected' : ''}>Phone</option>
                                    ${props.is_paid ? `<option value="video" ${props.meeting_type === 'video' ? 'selected' : ''}>Video</option>` : ''}
                                </select>
                            </p>` : `
                            <p><strong>Meeting Type:</strong> ${meetingTypeDisplay}</p>`;
            
            const managementSection = canManage ? `
                    <hr>
                    <div class="row mb-3">
                        <div class="col-12">
                            <h6><i class="fas fa-calendar-alt"></i> Reschedule Date & Time</h6>
                            <div class="form-row">
                                <div class="form-group col-md-4">
                                    <label for="rescheduleDate-${slotKey}" class="small">Appointment Date</label>
                                    <input type="date" class="form-control form-control-sm" id="rescheduleDate-${slotKey}" 
                                           value="${melbourneDate}" 
                                           data-original-date="${melbourneDate}"
                                           onchange="validateWeekendDate(this, '${slotKey}')">
                                </div>
                                <div class="form-group col-md-4">
                                    <label for="rescheduleTime-${slotKey}" class="small">Appointment Time</label>
                                    <input type="time" class="form-control form-control-sm" id="rescheduleTime-${slotKey}" 
                                           value="${melbourneTime}" 
                                           data-original-time="${melbourneTime}">
                                </div>
                                <div class="form-group col-md-4 d-flex align-items-end">
                                    <button type="button" class="btn btn-sm btn-primary w-100" onclick="rescheduleAppointmentDateTime('${slotKey}', ${manageId}, '${props.meeting_type || 'in_person'}', '${props.preferred_language || 'English'}')">
                                        <i class="fas fa-save"></i> Update Date & Time
                                    </button>
                                </div>
                            </div>
                            <small class="text-muted">
                                <i class="fas fa-info-circle"></i> Changes will sync with the public booking website if the appointment is linked.
                            </small>
                        </div>
                    </div>
                    <hr>
                    <div class="row">
                        <div class="col-md-6">
                            <h6><i class="fas fa-edit"></i> Change Status</h6>
                            <div class="btn-group-vertical w-100" role="group">
                                <button type="button" class="btn btn-sm btn-outline-success" onclick="updateAppointmentStatus(${manageId}, 'confirmed')">
                                    <i class="fas fa-check"></i> Mark as Confirmed
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="updateAppointmentStatus(${manageId}, 'completed')">
                                    <i class="fas fa-check-circle"></i> Mark as Complete
                                </button>
                                ${props.final_amount && parseFloat(props.final_amount) > 0 ? `
                                <button type="button" class="btn btn-sm btn-outline-info" onclick="updateAppointmentStatus(${manageId}, 'paid')">
                                    <i class="fas fa-dollar-sign"></i> Mark As Payment Done
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-warning" onclick="updateAppointmentStatus(${manageId}, 'pending')">
                                    <i class="fas fa-clock"></i> Mark As Payment Pending
                                </button>
                                ` : ''}
                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="updateAppointmentStatus(${manageId}, 'cancelled')">
                                    <i class="fas fa-times"></i> Mark as Cancelled
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="updateAppointmentStatus(${manageId}, 'no_show')">
                                    <i class="fas fa-user-times"></i> Mark as No Show
                                </button>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h6><i class="fas fa-exchange-alt"></i> Change Calendar Type</h6>
                            <div class="form-group">
                                <select class="form-control form-control-sm" id="consultantSelect-${slotKey}" onchange="updateAppointmentConsultant(${manageId}, '${slotKey}', this.value)">
                                    <option value="">Select Consultant...</option>
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
                            </div>
                            <div class="mt-2">
                                <small class="text-muted">
                                    <i class="fas fa-info-circle"></i> Changing consultant will move this appointment to the selected calendar type.
                                </small>
                            </div>
                        </div>
                    </div>` : `
                    <hr>
                    <div class="alert alert-info mb-0">
                        This appointment is shown from the public Bansal Lawyers booking API. CRM actions appear after the booking exists in BansalLaw CRM (synced row).
                    </div>`;
            
            const modalBody = `
                <div class="appointment-details">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Client:</strong> ${clientNameDisplay}</p>
                            <p><strong>Email:</strong> ${formatCalendarDetail(props.client_email)}</p>
                            <p><strong>Phone:</strong> ${formatCalendarDetail(props.client_phone)}</p>
                            <p><strong>Service:</strong> ${formatCalendarDetail(props.service_type)}</p>
                            <p><strong>Date & Time:</strong> ${formattedDate}</p>
                            <p><strong>Duration:</strong> ${duration} minutes</p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Location:</strong> ${props.location ? props.location.charAt(0).toUpperCase() + props.location.slice(1) : 'N/A'}</p>
                            ${meetingTypeRow}
                            <p><strong>Preferred Language:</strong> ${props.preferred_language ? props.preferred_language.charAt(0).toUpperCase() + props.preferred_language.slice(1).toLowerCase() : 'English'}</p>
                            <p><strong>Consultant:</strong> ${props.consultant}</p>
                            <p><strong>Status:</strong> <span class="badge badge-${getStatusClass(props.status)}" id="statusBadge">${formatCalendarDetail(props.status_label) !== 'N/A' ? props.status_label : (props.status || '').toString().toUpperCase()}</span></p>
                            <p><strong>Payment:</strong> <span class="badge badge-${props.is_paid ? 'success' : 'secondary'}">${formatCalendarDetail(props.payment_status)}</span></p>
                            ${props.is_paid ? `<p><strong>Amount:</strong> $${props.final_amount ? parseFloat(props.final_amount).toFixed(2) : '0.00'}</p>` : ''}
                        </div>
                    </div>
                    ${managementSection}
                </div>
            `;
            
            document.getElementById('eventModalBody').innerHTML = modalBody;
            const vfd = document.getElementById('viewFullDetails');
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
                console.log('Loading calendar events...');
                return;
            }
            console.log('Calendar events loaded');
            if (!bookingCalFirstLoadDone && !bookingCalDidEmptyRefetch) {
                bookingCalFirstLoadDone = true;
                setTimeout(function() {
                    try {
                        if (calendar.getEvents().length === 0) {
                            bookingCalDidEmptyRefetch = true;
                            console.log('Booking calendar: refetching events (first load returned none)');
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
        console.log('FullCalendar v6 initialized successfully');
        
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
            'pending': 'warning',
            'paid': 'info',
            'confirmed': 'success',
            'completed': 'info',
            'cancelled': 'danger',
            'no_show': 'dark',
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

    function showCourtHearingEventModal(event, props) {
        const utcDate = new Date(props.appointment_datetime || event.startStr);
        const formattedDate = utcDate.toLocaleString('en-AU', {
            timeZone: 'Australia/Melbourne',
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
            hour12: true
        });
        let clientLink = formatCalendarDetail(props.client_name);
        if (props.client_id_encoded) {
            clientLink = '<a href="/clients/detail/' + props.client_id_encoded + '" target="_blank" class="booking-calendar-link">' +
                formatCalendarDetail(props.client_name) + '</a>';
        }
        const body = `
            <div class="alert alert-info py-2"><i class="fas fa-gavel"></i> From client profile — edit on the client <strong>Court Dates</strong> tab.</div>
            <p><strong>Client:</strong> ${clientLink}</p>
            <p><strong>When:</strong> ${formattedDate}</p>
            <p><strong>Hearing type:</strong> ${formatCalendarDetail(props.hearing_type)}</p>
            <p><strong>Court:</strong> ${formatCalendarDetail(props.court_name)}</p>
            <p><strong>Case number:</strong> ${formatCalendarDetail(props.case_number)}</p>
            <p><strong>Judge:</strong> ${formatCalendarDetail(props.judge_name)}</p>
            <p><strong>Status:</strong> ${formatCalendarDetail(props.hearing_status || props.status_label)}</p>
            <p><strong>Notes:</strong> ${formatCalendarDetail(props.notes)}</p>
        `;
        document.getElementById('eventModalBody').innerHTML = body;
        const vfd = document.getElementById('viewFullDetails');
        if (props.client_id_encoded) {
            vfd.classList.remove('d-none');
            vfd.href = '/clients/detail/' + props.client_id_encoded;
            vfd.textContent = 'Open Client';
        } else {
            vfd.classList.add('d-none');
        }
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
                '<button><i class="fas fa-times-circle"></i> Dismiss</button>',
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
    
    // Global functions for modal actions
    // Pending cancellation data (used when showing cancellation modal)
    let pendingCancellationData = null;

    window.updateAppointmentStatus = function(appointmentId, newStatus) {
        // For cancellation, show custom modal with reason and email checkbox
        if (newStatus === 'cancelled') {
            pendingCancellationData = { appointmentId, button: event.target };
            document.getElementById('cancelReasonInput').value = '';
            document.getElementById('cancelReasonError').classList.add('d-none');
            document.getElementById('sendCancellationEmailCheck').checked = true;
            $('#cancellationConfirmModal').modal('show');
            return;
        }

        if (!confirm(`Are you sure you want to change the status to "${newStatus}"?`)) {
            return;
        }

        performStatusUpdate(appointmentId, newStatus, null, false, event.target);
    };

    // Handler for Confirm Cancellation button in modal (attach when script runs - DOM is ready)
    (function attachCancelConfirmHandler() {
        const confirmBtn = document.getElementById('confirmCancelBtn');
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
                $('#cancellationConfirmModal').modal('hide');
                performStatusUpdate(pendingCancellationData.appointmentId, 'cancelled', reason, sendEmail, pendingCancellationData.button);
                pendingCancellationData = null;
            });
        }
    })();

    function performStatusUpdate(appointmentId, newStatus, cancellationReason, sendCancellationConfirmation, buttonEl) {
        // Show loading state
        const button = buttonEl || event?.target;
        const originalText = button ? button.innerHTML : '';
        if (button) {
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
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
                // Update the status badge in the modal
                var statusBadge = document.getElementById('statusBadge');
                if (statusBadge) {
                    statusBadge.textContent = newStatus.toUpperCase();
                    statusBadge.className = 'badge badge-' + getStatusClass(newStatus);
                }
                $('#eventModal').modal('hide');
                calendar.refetchEvents();
                showAlert('success', 'Status updated successfully!');
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
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
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
                return response.json().then(errorData => {
                    throw { status: response.status, data: errorData };
                }).catch(() => {
                    throw { status: response.status, message: 'Server error occurred' };
                });
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
                    display.innerHTML = `${newDisplay} <i class="fas fa-edit ml-1" style="font-size: 0.8em;"></i>`;
                    
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

.booking-calendar-link {
    color: var(--sidebar-active) !important;
    text-decoration: underline;
}

.booking-calendar-link--action {
    cursor: pointer;
}

/* theme.md KPI Cards */
.calendar-stats {
    display: flex;
    justify-content: space-around;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 10px;
}

.stat-box {
    text-align: center;
    padding: 15px;
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: 10px;
    min-width: 120px;
    box-shadow: 0 1px 4px rgba(30, 61, 96, 0.06);
}

.stat-box h3 {
    margin: 0;
    font-size: 28px;
    font-weight: 700;
    color: var(--text-dark);
}

.stat-box p {
    margin: 5px 0 0 0;
    color: var(--text-muted);
    font-weight: 600;
    font-size: 11.5px;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.calendar-legend {
    display: flex;
    justify-content: center;
    gap: 20px;
    margin-bottom: 20px;
    flex-wrap: wrap;
    color: var(--text-dark);
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 13px;
}

.legend-color {
    width: 20px;
    height: 20px;
    border-radius: 4px;
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

.legend-item--divider {
    width: 1px;
    min-width: 1px;
    height: 24px;
    background: var(--border);
    margin: 0 4px;
    padding: 0;
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

/* Modals — same title treatment as page header (theme.md Top Bar) */
.booking-calendar-modal .modal-content {
    border: 1px solid var(--border);
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 1px 4px rgba(30, 61, 96, 0.06);
}

.booking-calendar-modal .modal-header {
    background: var(--header-bg) !important;
    color: var(--navy) !important;
    border-bottom: 1px solid var(--border) !important;
    padding: 14px 18px !important;
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

.booking-calendar-modal .modal-body {
    color: var(--text-dark);
    background: var(--card-bg);
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
</style>

@endsection

