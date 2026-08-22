@props([
    'stats' => ['today' => 0, 'this_week' => 0, 'overdue_actions' => 0],
    'timezone' => config('app.timezone'),
    'bookingCalendarType' => null,
])

<section class="dashboard-calendar-section" id="myCalendarSection" aria-label="Calendar">
    <div class="dashboard-calendar-card">
        <div class="dashboard-calendar-header">
            <div class="dashboard-calendar-header-left">
                <h2>
                    <i class="fa-solid fa-calendar-days"></i>
                    Calendar
                </h2>
                <p class="dashboard-calendar-subtitle">Click a day to jump to that date in the agenda. Hover a coloured bar for full details.</p>
            </div>
            <div class="dashboard-calendar-header-right">
                <div class="dashboard-calendar-stats">
                    <div class="dashboard-cal-stat dashboard-cal-stat--today" title="Upcoming items today">
                        <span class="dashboard-cal-stat-value" id="calStatToday">{{ $stats['today'] ?? 0 }}</span>
                        <span class="dashboard-cal-stat-label">Today</span>
                    </div>
                    <div class="dashboard-cal-stat dashboard-cal-stat--week" title="Upcoming items this week">
                        <span class="dashboard-cal-stat-value" id="calStatWeek">{{ $stats['this_week'] ?? 0 }}</span>
                        <span class="dashboard-cal-stat-label">This week</span>
                    </div>
                    <div class="dashboard-cal-stat dashboard-cal-stat--overdue" title="Overdue tasks">
                        <span class="dashboard-cal-stat-value" id="calStatOverdue">{{ $stats['overdue_actions'] ?? 0 }}</span>
                        <span class="dashboard-cal-stat-label">Overdue</span>
                    </div>
                </div>
                <button type="button" class="action-btn action-btn-outline action-btn-sm" id="btnAddPersonalEvent" title="Add a personal event">
                    <i class="fa-solid fa-plus"></i> Add Event
                </button>
            </div>
        </div>

        <div class="dashboard-calendar-legend" aria-label="Event colours">
            <span class="dashboard-cal-legend-group-label">Bookings</span>
            <span class="dashboard-cal-legend-item"><span class="dashboard-cal-dot dashboard-cal-dot--pending"></span> Pending</span>
            <span class="dashboard-cal-legend-item"><span class="dashboard-cal-dot dashboard-cal-dot--paid"></span> Paid</span>
            <span class="dashboard-cal-legend-item"><span class="dashboard-cal-dot dashboard-cal-dot--confirmed"></span> Confirmed</span>
            <span class="dashboard-cal-legend-group-label">Important Events</span>
            <span class="dashboard-cal-legend-item"><span class="dashboard-cal-dot dashboard-cal-dot--court"></span> Court / Hearing</span>
            <span class="dashboard-cal-legend-item"><span class="dashboard-cal-dot dashboard-cal-dot--meeting"></span> Meeting</span>
            <span class="dashboard-cal-legend-item"><span class="dashboard-cal-dot dashboard-cal-dot--deadline"></span> Deadline</span>
            <span class="dashboard-cal-legend-item"><span class="dashboard-cal-dot dashboard-cal-dot--reminder"></span> Reminder</span>
            <span class="dashboard-cal-legend-item"><span class="dashboard-cal-dot dashboard-cal-dot--other"></span> Other</span>
        </div>

        <div class="dashboard-calendar-body">
            <div class="dashboard-calendar-wrapper">
                <div id="staffDashboardCalendar" class="dashboard-calendar-container" data-timezone="{{ $timezone }}" data-booking-calendar-type="{{ $bookingCalendarType }}"></div>
            </div>

            <div class="dashboard-upcoming-panel" id="dashboardUpcomingPanel">
                <div class="dashboard-upcoming-header">
                    <h3>
                        <i class="fa-solid fa-list"></i>
                        Schedule by date
                    </h3>
                    <span class="dashboard-upcoming-count" id="dashboardUpcomingCount">0</span>
                </div>
                <p class="dashboard-upcoming-help">Hearings, meetings, deadlines and events from today onwards, grouped by date.</p>
                <div class="dashboard-upcoming-list" id="dashboardUpcomingList" aria-live="polite">
                    <div class="dashboard-upcoming-empty">Loading upcoming items…</div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Personal event modal -->
<div class="modal fade" id="personalEventModal" tabindex="-1" aria-labelledby="personalEventModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="personalEventModalLabel">
                    <i class="fa-solid fa-calendar-plus"></i> Add Personal Event
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="personalEventError" class="alert alert-danger d-none" role="alert"></div>
                <div class="mb-3">
                    <label for="personalEventTitle" class="form-label">Title <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="personalEventTitle" maxlength="255" placeholder="Event title">
                </div>
                <div class="mb-3">
                    <label for="personalEventType" class="form-label">Type</label>
                    <select class="form-select" id="personalEventType">
                        <option value="meeting">Meeting</option>
                        <option value="court">Court</option>
                        <option value="deadline">Deadline</option>
                        <option value="reminder">Reminder</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label for="personalEventDate" class="form-label">Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="personalEventDate" min="{{ now()->toDateString() }}">
                    </div>
                    <div class="col-md-3">
                        <label for="personalEventStartTime" class="form-label">Start</label>
                        <input type="time" class="form-control" id="personalEventStartTime" value="09:00">
                    </div>
                    <div class="col-md-3">
                        <label for="personalEventEndTime" class="form-label">End</label>
                        <input type="time" class="form-control" id="personalEventEndTime" value="10:00">
                    </div>
                </div>
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="personalEventAllDay">
                    <label class="form-check-label" for="personalEventAllDay">All day</label>
                </div>
                <div class="mb-3">
                    <label for="personalEventLocation" class="form-label">Location</label>
                    <input type="text" class="form-control" id="personalEventLocation" maxlength="255" placeholder="Optional">
                </div>
                <div class="mb-0">
                    <label for="personalEventNotes" class="form-label">Notes</label>
                    <textarea class="form-control" id="personalEventNotes" rows="2" maxlength="5000" placeholder="Optional notes"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="personalEventSaveBtn">
                    <i class="fa-solid fa-floppy-disk"></i> Save Event
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Event detail modal -->
<div class="modal fade" id="personalEventDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="personalEventDetailTitle">Event Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="personalEventDetailBody"></div>
            <div class="modal-footer" id="personalEventDetailFooter">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
