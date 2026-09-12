@props([
    'stats' => ['today' => 0, 'this_week' => 0, 'overdue_actions' => 0],
    'timezone' => config('app.timezone'),
    'bookingCalendarType' => null,
    'canFilterCalendarStaff' => false,
    'calendarStaffOptions' => [],
])

<section class="dashboard-calendar-section" id="myCalendarSection" aria-label="Calendar">
    <div class="dashboard-calendar-card">
        <div class="dashboard-calendar-header">
            <div class="dashboard-calendar-header-left">
                <h2>My Calendar</h2>
                <p class="dashboard-calendar-subtitle" id="dashboardCalendarSubtitle">
                    @if(!empty($canFilterCalendarStaff))
                        All staff appointments, hearings and deadlines
                    @else
                        Your appointments, hearings and deadlines
                    @endif
                </p>
            </div>
            <div class="dashboard-calendar-header-right">
                @if(!empty($canFilterCalendarStaff))
                    <div class="dashboard-calendar-staff-filter">
                        <label class="dashboard-calendar-staff-label" for="dashboardCalendarStaffView">View</label>
                        <select id="dashboardCalendarStaffView" class="dashboard-calendar-staff-select" title="View calendar for">
                            <option value="all" selected>All staff</option>
                            <option value="self">My calendar</option>
                            @if(!empty($calendarStaffOptions))
                                <optgroup label="Individual staff">
                                    @foreach($calendarStaffOptions as $opt)
                                        <option value="{{ (int) ($opt['id'] ?? 0) }}">{{ $opt['name'] ?? ('Staff #' . ($opt['id'] ?? '')) }}</option>
                                    @endforeach
                                </optgroup>
                            @endif
                        </select>
                    </div>
                @endif
                <div class="dashboard-calendar-stats" aria-label="Calendar summary">
                    <div class="dashboard-cal-stat dashboard-cal-stat--today" title="Upcoming items today">
                        <span class="dashboard-cal-stat-value" id="calStatToday">{{ is_array($stats) ? ($stats['today'] ?? 0) : '—' }}</span>
                        <span class="dashboard-cal-stat-label">Today</span>
                    </div>
                    <div class="dashboard-cal-stat dashboard-cal-stat--week" title="Upcoming items this week">
                        <span class="dashboard-cal-stat-value" id="calStatWeek">{{ is_array($stats) ? ($stats['this_week'] ?? 0) : '—' }}</span>
                        <span class="dashboard-cal-stat-label">Week</span>
                    </div>
                    <div class="dashboard-cal-stat dashboard-cal-stat--overdue" title="Overdue tasks">
                        <span class="dashboard-cal-stat-value" id="calStatOverdue">{{ is_array($stats) ? ($stats['overdue_actions'] ?? 0) : '—' }}</span>
                        <span class="dashboard-cal-stat-label">Overdue</span>
                    </div>
                </div>
                <button type="button" class="dashboard-cal-add-btn" id="btnAddPersonalEvent" title="Add a personal event">
                    <i class="fa-solid fa-plus" aria-hidden="true"></i>
                    <span>Add event</span>
                </button>
            </div>
        </div>

        <div class="dashboard-calendar-body">
            <div class="dashboard-calendar-wrapper">
                <div class="dashboard-calendar-surface">
                    <div id="staffDashboardCalendar" class="dashboard-calendar-container" data-timezone="{{ $timezone }}" data-booking-calendar-type="{{ $bookingCalendarType }}"></div>
                </div>
            </div>

            <div class="dashboard-upcoming-panel" id="dashboardUpcomingPanel">
                <div class="dashboard-upcoming-surface">
                    <div class="dashboard-upcoming-header">
                        <h3>Upcoming</h3>
                        <span class="dashboard-upcoming-count" id="dashboardUpcomingCount">0</span>
                    </div>
                    <p class="dashboard-upcoming-help visually-hidden">Events from today onwards, grouped by date</p>
                    <div class="dashboard-upcoming-list" id="dashboardUpcomingList" aria-live="polite">
                        <div class="dashboard-upcoming-empty">Loading upcoming items…</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Personal event modal -->
<div class="modal fade pe-modal" id="personalEventModal" tabindex="-1" aria-labelledby="personalEventModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md pe-modal__dialog">
        <div class="modal-content pe-modal__content">
            <div class="modal-header pe-modal__header">
                <div class="pe-modal__heading">
                    <span class="pe-modal__badge" aria-hidden="true"><i class="fa-solid fa-calendar-plus"></i></span>
                    <div>
                        <h5 class="modal-title pe-modal__title" id="personalEventModalLabel">New event</h5>
                        <p class="pe-modal__subtitle">Business hours · 9:00 AM – 6:00 PM</p>
                    </div>
                </div>
                <button type="button" class="btn-close pe-modal__close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pe-modal__body">
                <div id="personalEventError" class="pe-modal__error d-none" role="alert"></div>

                <div class="pe-modal__summary" id="personalEventSummary" aria-live="polite">
                    <i class="fa-regular fa-clock" aria-hidden="true"></i>
                    <span id="personalEventSummaryText">Choose a date and time</span>
                </div>

                <div class="pe-modal__field">
                    <label for="personalEventTitle" class="pe-modal__label">Title</label>
                    <input type="text" class="form-control pe-modal__input" id="personalEventTitle" maxlength="255" placeholder="What is this event?" autocomplete="off">
                </div>

                <div class="pe-modal__field">
                    <span class="pe-modal__label" id="personalEventTypeLabel">Type</span>
                    <div class="pe-modal__chips" role="group" aria-labelledby="personalEventTypeLabel" id="personalEventTypeChips">
                        <button type="button" class="pe-modal__chip is-active" data-type="meeting">Meeting</button>
                        <button type="button" class="pe-modal__chip" data-type="court">Court</button>
                        <button type="button" class="pe-modal__chip" data-type="deadline">Deadline</button>
                    </div>
                    <input type="hidden" id="personalEventType" value="meeting">
                    <p class="form-text mb-0 mt-1">Reminders, follow-ups and other personal items are added on the booking calendars (Ajay / Michael).</p>
                </div>

                <div class="pe-modal__row">
                    <div class="pe-modal__field pe-modal__field--grow">
                        <label for="personalEventDate" class="pe-modal__label">Date</label>
                        <input type="date" class="form-control pe-modal__input" id="personalEventDate" min="{{ now()->toDateString() }}">
                    </div>
                    <div class="pe-modal__field pe-modal__field--grow">
                        <span class="pe-modal__label">Duration</span>
                        <div class="pe-modal__chips pe-modal__chips--compact" id="personalEventDurationChips" role="group" aria-label="Quick duration">
                            <button type="button" class="pe-modal__chip" data-minutes="30">30m</button>
                            <button type="button" class="pe-modal__chip is-active" data-minutes="60">1h</button>
                            <button type="button" class="pe-modal__chip" data-minutes="120">2h</button>
                        </div>
                    </div>
                </div>

                <div class="pe-modal__row pe-modal__row--times" id="personalEventTimeRow">
                    <div class="pe-modal__field">
                        <label for="personalEventStartTime" class="pe-modal__label">Start</label>
                        <input type="time" class="form-control pe-modal__input" id="personalEventStartTime" value="09:00" min="09:00" max="17:45" step="900">
                    </div>
                    <div class="pe-modal__field">
                        <label for="personalEventEndTime" class="pe-modal__label">End</label>
                        <input type="time" class="form-control pe-modal__input" id="personalEventEndTime" value="10:00" min="09:15" max="18:00" step="900">
                    </div>
                </div>

                <label class="pe-modal__switch" for="personalEventAllDay">
                    <input type="checkbox" id="personalEventAllDay">
                    <span class="pe-modal__switch-ui" aria-hidden="true"></span>
                    <span class="pe-modal__switch-copy">
                        <strong>All day</strong>
                        <em>Books the full 9:00 AM – 6:00 PM window</em>
                    </span>
                </label>

                <div class="pe-modal__row">
                    <div class="pe-modal__field pe-modal__field--grow">
                        <label for="personalEventLocation" class="pe-modal__label">Location <span class="pe-modal__optional">optional</span></label>
                        <input type="text" class="form-control pe-modal__input" id="personalEventLocation" maxlength="255" placeholder="Office, court, Zoom…">
                    </div>
                </div>

                <div class="pe-modal__field pe-modal__field--last">
                    <label for="personalEventNotes" class="pe-modal__label">Notes <span class="pe-modal__optional">optional</span></label>
                    <textarea class="form-control pe-modal__input" id="personalEventNotes" rows="3" maxlength="5000" placeholder="Add context for yourself…"></textarea>
                </div>
            </div>
            <div class="modal-footer pe-modal__footer">
                <button type="button" class="pe-modal__btn pe-modal__btn--ghost" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="pe-modal__btn pe-modal__btn--primary" id="personalEventSaveBtn">
                    <i class="fa-solid fa-check" aria-hidden="true"></i> Save event
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Event detail modal (follow-ups, hearings, deadlines, bookings, staff events) -->
<div class="modal fade booking-calendar-modal appointment-detail-modal personal-calendar-detail-modal personal-event-detail-modal" id="personalEventDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div class="appointment-detail-modal__heading">
                    <span class="appointment-detail-modal__icon" id="personalEventDetailIcon" aria-hidden="true">
                        <i class="fa-solid fa-calendar-day" id="personalEventDetailIconGlyph"></i>
                    </span>
                    <div>
                        <h5 class="modal-title mb-0" id="personalEventDetailTitle">Event Details</h5>
                        <p class="appointment-detail-modal__subtitle mb-0" id="personalEventDetailSubtitle"></p>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="personalEventDetailBody"></div>
            <div class="modal-footer appointment-detail-modal__footer" id="personalEventDetailFooter">
                <div class="appointment-detail-modal__footer-actions ms-auto">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Reminder details — appointment-details layout (personal calendar only) --}}
<div class="modal fade booking-calendar-modal appointment-detail-modal personal-calendar-detail-modal personal-reminder-detail-modal" id="personalReminderDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div class="appointment-detail-modal__heading">
                    <span class="appointment-detail-modal__icon appointment-detail-modal__icon--reminder" aria-hidden="true">
                        <i class="fa-regular fa-bell"></i>
                    </span>
                    <div>
                        <h5 class="modal-title mb-0" id="personalReminderDetailTitle">Reminder Details</h5>
                        <p class="appointment-detail-modal__subtitle mb-0" id="personalReminderDetailSubtitle"></p>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="personalReminderDetailBody"></div>
            <div class="modal-footer appointment-detail-modal__footer">
                <button type="button" class="btn btn-outline-danger d-none" id="personalReminderDeleteBtn">
                    <i class="fa-solid fa-trash"></i> Delete Reminder
                </button>
                <div class="appointment-detail-modal__footer-actions ms-auto">
                    <a href="#" class="btn btn-primary d-none" id="personalReminderOpenClient" target="_blank">
                        <i class="fa-solid fa-user"></i> Open Client
                    </a>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
</div>
