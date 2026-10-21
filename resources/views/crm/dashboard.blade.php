@extends('layouts.crm_client_detail_dashboard')

@section('content')
    @php
        $staffUser = auth('admin')->user();
        $staffFirstName = ($staffUser && ! empty($staffUser->first_name)) ? $staffUser->first_name : 'there';
        $dashboardTz = config('app.timezone');
        $dashboardNow = now()->timezone($dashboardTz);
        $hour = (int) $dashboardNow->format('G');
        $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
        $dw = $dashboardWidgets ?? [
            'restricted' => false,
            'shell_only' => false,
            'active_matters' => true,
            'closed_matters' => true,
            'tasks' => true,
            'recent_activity' => true,
            'calendar' => true,
            'access_approvals' => true,
            'new_client' => true,
        ];
        $showKpis = !empty($dw['active_matters']) || !empty($dw['closed_matters']) || !empty($dw['tasks']) || !empty($dw['recent_activity']);
        $showPriority = !empty($dw['tasks']) || !empty($dw['recent_activity']);
    @endphp
    <main class="main-content" id="dashboardRoot"
          data-clients-url="{{ url('/clients/get-allclients') }}"
          data-personal-task-url="{{ route('clients.tasks.personal.store') }}"
          data-matter-reminder-url="{{ route('clients.matterTask.store') }}"
          data-personal-calendar-enabled="{{ !empty($canAccessPersonalCalendar) ? '1' : '0' }}"
          data-dashboard-shell-only="{{ !empty($dw['shell_only']) ? '1' : '0' }}">
        <header class="dashboard-welcome-banner">
            <div class="dashboard-welcome-content">
                <div class="dashboard-welcome-text">
                    <div class="dashboard-welcome-heading">
                        <h1 id="dashboardGreeting" data-first-name="{{ $staffFirstName }}">{{ $greeting }}, {{ $staffFirstName }}</h1>
                        <div class="dashboard-header-meta">
                            <span class="dashboard-clock-icon"><i class="fa-regular fa-clock"></i></span>
                            <time id="dashboardDateTime" datetime="{{ $dashboardNow->toIso8601String() }}" data-timezone="{{ $dashboardTz }}">
                                {{ $dashboardNow->format('l, j F Y') }} · {{ $dashboardNow->format('g:i A') }}
                            </time>
                        </div>
                    </div>
                </div>
                <div class="header-actions">
                    @if(!empty($dw['new_client']))
                    <a href="{{ route('adminconsole.system.clients.createclient') }}" class="action-btn action-btn-primary">
                        <i class="fa-solid fa-user-plus"></i> New client
                    </a>
                    @endif
                    @if(empty($dw['shell_only']))
                    <button type="button" class="action-btn action-btn-secondary" id="refreshDashboard" title="Refresh Dashboard (Alt+R)">
                        <i class="fa-solid fa-rotate"></i> Refresh
                    </button>
                    @endif
                </div>
            </div>
        </header>

        @if(!empty($dw['shell_only']))
        <section class="dashboard-restricted-shell" style="max-width: 40rem; margin: 2rem auto; text-align: center; padding: 2rem 1.5rem;">
            <div style="font-size: 2rem; color: #94a3b8; margin-bottom: 0.75rem;" aria-hidden="true">
                <i class="fa-regular fa-folder-open"></i>
            </div>
            <h2 style="font-size: 1.15rem; font-weight: 600; margin-bottom: 0.5rem;">No modules assigned yet</h2>
            <p style="color: #64748b; margin: 0; line-height: 1.5;">
                You can use your profile from the menu. Ask a Super Admin to grant access to specific clients, leads, matters, or dashboard widgets.
            </p>
        </section>
        @endif

        @if($showKpis)
        {{-- KPI Cards Section --}}
        <section class="kpi-cards">
            @if(!empty($dw['active_matters']))
            <x-dashboard.kpi-card 
                :title="'Active Matters'" 
                :count="$count_active_matter" 
                :route="route('clients.clientsmatterslist')"
                subtitle="Open matters — go to full list"
                icon="fa-solid fa-briefcase"
                icon-class="icon-active"
                data-kpi="active_matters"
            />
            @endif

            @if(!empty($dw['closed_matters']))
            @php
                $_closedListViewer = Auth::user();
                $_canBrowseClosedMatters = $_closedListViewer instanceof \App\Models\Staff
                    && \App\Support\StaffClientVisibility::seesAllClientMatters($_closedListViewer);
            @endphp
            <x-dashboard.kpi-card 
                :title="'Closed Matters'" 
                :count="$count_closed_matter" 
                :route="$_canBrowseClosedMatters ? route('clients.closedmatterslist') : null"
                :subtitle="$_canBrowseClosedMatters ? 'Closed matters — go to full list' : 'Closed matters on your files'"
                icon="fa-solid fa-box-archive"
                icon-class="icon-closed"
                data-kpi="closed_matters"
            />
            @endif
            
            @if(!empty($dw['tasks']))
            <x-dashboard.kpi-card 
                :title="'Urgent Notes Deadlines'" 
                :count="$count_note_deadline"
                :route="route('assignee.tasks')"
                :subtitle="count($notesData) . ' shown below'"
                icon="fa-solid fa-hourglass-half"
                icon-class="icon-pending"
                data-kpi="note_deadline"
            />
            @endif
            
            @if(!empty($dw['recent_activity']))
            <x-dashboard.kpi-card 
                :title="'Recent Matter Activity'" 
                :count="$count_cases_requiring_attention_data"
                :route="route('dashboard') . '#recent-matter-activity'"
                subtitle="Active in the last 90 days or due within 7 days"
                icon="fa-solid fa-clock-rotate-left"
                icon-class="icon-pending"
                data-kpi="recent_activity"
            />
            @endif
        </section>
        @endif

        @if(!empty($canAccessPersonalCalendar) && !empty($dw['calendar']))
        <x-dashboard.staff-calendar
            :stats="$calendarStats ?? ['today' => 0, 'this_week' => 0, 'overdue_actions' => 0]"
            :timezone="$dashboardTz"
            :booking-calendar-type="$bookingCalendarType ?? null"
            :calendar-personal-label="$calendarPersonalLabel ?? 'My calendar'"
            :calendar-type-options="$calendarTypeOptions ?? []"
            :can-filter-calendar-staff="$canFilterCalendarStaff ?? false"
            :calendar-staff-options="$calendarStaffOptions ?? []"
        />
        @include('crm.booking.appointments.partials.event-modals')
        @endif

        @if(!empty($dw['access_approvals']))
        @include('crm.partials.access-approvals-dashboard')
        @endif

        @if($showPriority)
        {{-- Priority Focus Section --}}
        <section class="priority-focus">
            @if(!empty($dw['tasks']))
            {{-- My Tasks (Microsoft To Do Style) --}}
            <div class="focus-container todo-container">
                <div class="todo-header">
                    <div class="todo-header-left">
                        <span class="dashboard-section-icon-badge dashboard-section-icon-badge--tasks" aria-hidden="true"><i class="fa-solid fa-list-check"></i></span>
                        <h3>My Tasks</h3>
                        <span class="todo-count-badge">{{ $count_note_deadline }}</span>
                    </div>
                    {{-- Add Task popover template (outside attribute to avoid unescaped & in JS) --}}
                    <div id="add-task-popover-template" style="display:none;">
                        @include('components.add-task-form', [
                            'clientSelectId' => 'assign_client_id',
                            'clientErrorId' => 'client-error',
                            'noteTextareaId' => 'assignnote',
                            'noteErrorId' => 'note-error',
                            'taskGroupId' => 'task_group',
                            'submitBtnId' => 'add_my_task',
                            'submitLabel' => 'Add My Task',
                            'selectAllId' => 'select-all',
                            'hiddenSelectId' => 'rem_cat',
                            'assigneesErrorId' => 'assignees-error',
                            'staffMembers' => $dashboardAssignableStaff ?? collect(),
                        ])
                    </div>
                    <button class="todo-add-btn add_my_task" data-container="body" data-placement="bottom-start" data-html="true" data-content-id="add-task-popover-template" title="Add New Task">
                        <i class="fa-solid fa-plus"></i>
                    </button>
                </div>
                
                <div class="todo-task-list-container"
                     id="todo-task-list-root"
                     data-infinite-scroll="1"
                     data-tasks-url="{{ route('dashboard.tasks') }}"
                     data-current-page="{{ $notes_current_page ?? 1 }}"
                     data-last-page="{{ $notes_last_page ?? 1 }}"
                     data-per-page="{{ $notes_per_page ?? 10 }}"
                     data-total="{{ $count_note_deadline }}">
                    <x-dashboard.todo-list-content :notes-data="$notesData" :count-note-deadline="$count_note_deadline" />
                </div>
            </div>
            @endif

            @if(!empty($dw['recent_activity']))
            {{-- Recent Matter Activity --}}
            <div class="focus-container dashboard-scroll-anchor" id="recent-matter-activity">
                <div class="focus-header">
                    <div class="focus-header-left">
                        <span class="dashboard-section-icon-badge dashboard-section-icon-badge--activity" aria-hidden="true"><i class="fa-solid fa-clock-rotate-left"></i></span>
                        <h3>Recent Matter Activity</h3>
                    </div>
                    <span class="badge-count" id="cases-attention-badge">{{ $count_cases_requiring_attention_data }}</span>
                </div>
                <div class="case-list-container"
                     id="cases-attention-list-root"
                     data-infinite-scroll="1"
                     data-cases-url="{{ route('dashboard.cases-requiring-attention') }}"
                     data-current-page="{{ $cases_current_page ?? 1 }}"
                     data-last-page="{{ $cases_last_page ?? 1 }}"
                     data-per-page="{{ $cases_per_page ?? 10 }}"
                     data-total="{{ $count_cases_requiring_attention_data }}">
                    <x-dashboard.cases-list-content :cases-data="$cases_requiring_attention_data" />
                </div>
            </div>
            @endif
        </section>
        @endif

        <div id="responsible-matters-mount">
            <x-dashboard.responsible-matters :matters="$personResponsibleMatters ?? collect()" />
        </div>
    </main>

    {{-- Loading Overlay --}}
    <div class="loading-overlay" id="loadingOverlay" style="display: none;">
        <div class="spinner-container">
            <div class="spinner"></div>
            <p>Loading...</p>
        </div>
    </div>

    {{-- Toast Notification Container --}}
    <div class="toast-container" id="toastContainer"></div>

    {{-- Task Detail Panel --}}
    <x-dashboard.task-detail-panel />

    {{-- Modals --}}
    @include('components.dashboard.modals')
@endsection

@push('styles')
@once
@vite(['resources/css/fullcalendar-v6.css'])
<link rel="stylesheet" href="{{ asset('css/dashboard.css') }}?v={{ @filemtime(public_path('css/dashboard.css')) ?: time() }}">
<link rel="stylesheet" href="{{ asset('css/task-popover-modern.css') }}?v={{ @filemtime(public_path('css/task-popover-modern.css')) ?: time() }}">
@endonce
@endpush

@push('scripts')
@once
@if(empty(($dashboardWidgets ?? [])['shell_only']))
<script src="{{URL::to('/')}}/js/components/dropdown-multi-select.js"></script>
<script src="{{ asset('js/components/task-description-mentions.js') }}?v={{ @filemtime(public_path('js/components/task-description-mentions.js')) ?: time() }}"></script>
<script>
    // dashboard.js = task complete / extend / detail panel
    // dashboard-page.js = summary refresh, infinite scroll, filters, toasts
    window.dashboardRoutes = {
        dashboard: "{{ route('dashboard') }}",
        dashboardSummary: "{{ route('dashboard.summary') }}",
        calendarEvents: "{{ route('dashboard.calendar-events') }}",
        storeCalendarEvent: "{{ route('booking.api.calendar-events.store') }}",
        updateCalendarEvent: "{{ url('/booking/api/calendar-events') }}",
        destroyCalendarEvent: "{{ url('/booking/api/calendar-events') }}",
        extendDeadline: "{{ route('dashboard.extend-deadline') }}",
        updateTaskCompleted: "{{ route('dashboard.tasks.complete') }}",
        dashboardTasks: "{{ route('dashboard.tasks') }}",
        dashboardCases: "{{ route('dashboard.cases-requiring-attention') }}",
        assigneeAction: "{{ route('assignee.tasks') }}",
        storeTask: "{{ route('clients.tasks.personal.store') }}",
        storePersonalTask: "{{ route('clients.tasks.personal.store') }}"
    };

    window.dashboardData = {};
    window.consultantsData = @json($bookingConsultants ?? []);
    window.BOOKING_WEB_BASE = @json(rtrim(url('/booking'), '/'));
    window.DASHBOARD_CALENDAR_TYPES = @json($calendarTypeOptions ?? \App\Services\StaffPersonalCalendarFeedService::CALENDAR_TYPES);
</script>
<script src="{{ asset('js/dashboard.js') }}?v={{ @filemtime(public_path('js/dashboard.js')) ?: time() }}"></script>
<script src="{{ asset('js/crm/dashboard/add-task-popover.js') }}?v={{ @filemtime(public_path('js/crm/dashboard/add-task-popover.js')) ?: time() }}"></script>
<script src="{{ asset('js/crm/dashboard/dashboard-page.js') }}?v={{ @filemtime(public_path('js/crm/dashboard/dashboard-page.js')) ?: time() }}"></script>
@if(!empty($canAccessPersonalCalendar))
<script src="{{ asset('js/booking-appointment-modal.js') }}?v={{ @filemtime(public_path('js/booking-appointment-modal.js')) ?: time() }}"></script>
<script src="{{ asset('js/dashboard-calendar.js') }}?v={{ @filemtime(public_path('js/dashboard-calendar.js')) ?: time() }}"></script>
@endif
@else
<script>
    (function () {
        var el = document.getElementById('dashboardDateTime');
        if (!el) return;
        var tz = el.getAttribute('data-timezone') || undefined;
        function tick() {
            try {
                var now = new Date();
                var opts = { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric', timeZone: tz };
                var datePart = now.toLocaleDateString(undefined, opts);
                var timePart = now.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit', timeZone: tz });
                el.textContent = datePart + ' · ' + timePart;
            } catch (e) {}
        }
        tick();
        setInterval(tick, 30000);
    })();
</script>
@endif
@endonce
@endpush
