@props([
    'stats' => ['today' => 0, 'this_week' => 0, 'overdue_actions' => 0],
    'timezone' => config('app.timezone'),
])

<section class="dashboard-calendar-section" id="myCalendarSection" aria-label="My calendar">
    <div class="dashboard-calendar-card">
        <div class="dashboard-calendar-header">
            <div class="dashboard-calendar-header-left">
                <h2>
                    <i class="fa-solid fa-calendar-days"></i>
                    My Calendar
                </h2>
                <p class="dashboard-calendar-subtitle">Your actions, hearings, deadlines &amp; events</p>
            </div>
            <div class="dashboard-calendar-header-right">
                <div class="dashboard-calendar-stats">
                    <div class="dashboard-cal-stat dashboard-cal-stat--today" title="Events today">
                        <span class="dashboard-cal-stat-value" id="calStatToday">{{ $stats['today'] ?? 0 }}</span>
                        <span class="dashboard-cal-stat-label">Today</span>
                    </div>
                    <div class="dashboard-cal-stat dashboard-cal-stat--week" title="Events this week">
                        <span class="dashboard-cal-stat-value" id="calStatWeek">{{ $stats['this_week'] ?? 0 }}</span>
                        <span class="dashboard-cal-stat-label">This week</span>
                    </div>
                    <div class="dashboard-cal-stat dashboard-cal-stat--overdue" title="Overdue actions">
                        <span class="dashboard-cal-stat-value" id="calStatOverdue">{{ $stats['overdue_actions'] ?? 0 }}</span>
                        <span class="dashboard-cal-stat-label">Overdue</span>
                    </div>
                </div>
                <button type="button" class="action-btn action-btn-outline action-btn-sm" id="btnAddPersonalEvent" title="Add personal event">
                    <i class="fa-solid fa-plus"></i> Add Event
                </button>
            </div>
        </div>

        <div class="dashboard-calendar-legend">
            <span class="dashboard-cal-legend-item"><span class="dashboard-cal-dot dashboard-cal-dot--action"></span> My Actions</span>
            <span class="dashboard-cal-legend-item"><span class="dashboard-cal-dot dashboard-cal-dot--court"></span> Court / Hearing</span>
            <span class="dashboard-cal-legend-item"><span class="dashboard-cal-dot dashboard-cal-dot--deadline"></span> Deadlines</span>
            <span class="dashboard-cal-legend-item"><span class="dashboard-cal-dot dashboard-cal-dot--meeting"></span> Meetings &amp; Events</span>
        </div>

        <div class="dashboard-calendar-wrapper">
            <div id="staffDashboardCalendar" class="dashboard-calendar-container" data-timezone="{{ $timezone }}"></div>
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
                        <input type="date" class="form-control" id="personalEventDate">
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
