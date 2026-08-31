@extends('layouts.crm_client_detail_dashboard')

@section('content')
    @php
        $staffUser = auth('admin')->user();
        $staffFirstName = ($staffUser && ! empty($staffUser->first_name)) ? $staffUser->first_name : 'there';
        $dashboardTz = config('app.timezone');
        $dashboardNow = now()->timezone($dashboardTz);
        $hour = (int) $dashboardNow->format('G');
        $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
    @endphp
    <main class="main-content" id="dashboardRoot"
          data-clients-url="{{ url('/clients/get-allclients') }}"
          data-personal-task-url="{{ route('clients.tasks.personal.store') }}">
        <header class="dashboard-welcome-banner">
            <div class="dashboard-welcome-content">
                <div class="dashboard-welcome-text">
                    <p class="dashboard-welcome-label">Dashboard</p>
                    <div class="dashboard-welcome-heading">
                        <h1 id="dashboardGreeting" data-first-name="{{ $staffFirstName }}">{{ $greeting }}, {{ $staffFirstName }}</h1>
                        <p class="dashboard-header-meta">
                            <time id="dashboardDateTime" datetime="{{ $dashboardNow->toIso8601String() }}" data-timezone="{{ $dashboardTz }}">
                                {{ $dashboardNow->format('l, j F Y') }} · {{ $dashboardNow->format('g:i A') }}
                            </time>
                        </p>
                    </div>
                </div>
                <div class="header-actions">
                    <a href="{{ route('adminconsole.system.clients.createclient') }}" class="action-btn action-btn-primary">
                        <i class="fa-solid fa-user-plus"></i> New client
                    </a>
                    <button type="button" class="action-btn action-btn-secondary" id="refreshDashboard" title="Refresh Dashboard (Alt+R)">
                        <i class="fa-solid fa-rotate"></i> Refresh
                    </button>
                </div>
            </div>
        </header>

        {{-- KPI Cards Section --}}
        <section class="kpi-cards">
            <x-dashboard.kpi-card 
                :title="'Active Matters'" 
                :count="$count_active_matter" 
                :route="route('clients.clientsmatterslist')"
                subtitle="Open matters — go to full list"
                icon="fa-solid fa-briefcase"
                icon-class="icon-active"
                data-kpi="active_matters"
            />

            <x-dashboard.kpi-card 
                :title="'Closed Matters'" 
                :count="$count_closed_matter" 
                :route="route('clients.closedmatterslist')"
                subtitle="Closed matters — go to full list"
                icon="fa-solid fa-box-archive"
                icon-class="icon-closed"
                data-kpi="closed_matters"
            />
            
            <x-dashboard.kpi-card 
                :title="'Urgent Notes Deadlines'" 
                :count="$count_note_deadline"
                :route="route('assignee.tasks')"
                :subtitle="count($notesData) . ' shown below'"
                icon="fa-solid fa-hourglass-half"
                icon-class="icon-pending"
                data-kpi="note_deadline"
            />
            
            <x-dashboard.kpi-card 
                :title="'Recent Matter Activity'" 
                :count="$count_cases_requiring_attention_data"
                :route="route('dashboard') . '#recent-matter-activity'"
                subtitle="Active in the last 90 days or due within 7 days"
                icon="fa-solid fa-clock-rotate-left"
                icon-class="icon-pending"
                data-kpi="recent_activity"
            />
        </section>

        <x-dashboard.staff-calendar
            :stats="$calendarStats ?? ['today' => 0, 'this_week' => 0, 'overdue_actions' => 0]"
            :timezone="$dashboardTz"
            :booking-calendar-type="$bookingCalendarType ?? null"
        />

        @include('crm.partials.access-approvals-dashboard')

        {{-- Priority Focus Section --}}
        <section class="priority-focus">
            {{-- My Tasks (Microsoft To Do Style) --}}
            <div class="focus-container todo-container">
                <div class="todo-header">
                    <div class="todo-header-left">
                        <h3>
                            <i class="fa-solid fa-list-check dashboard-theme-icon-primary"></i> 
                            My Tasks
                        </h3>
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

            {{-- Recent Matter Activity --}}
            <div class="focus-container dashboard-scroll-anchor" id="recent-matter-activity">
                <div class="focus-header">
                    <h3>
                        <i class="fa-solid fa-clock-rotate-left dashboard-theme-icon-primary"></i> 
                        Recent Matter Activity
                    </h3>
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
        </section>
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
<script src="{{URL::to('/')}}/js/components/dropdown-multi-select.js"></script>
<script src="{{ asset('js/components/task-description-mentions.js') }}?v={{ @filemtime(public_path('js/components/task-description-mentions.js')) ?: time() }}"></script>
<script>
    window.dashboardRoutes = {
        dashboard: "{{ route('dashboard') }}",
        dashboardSummary: "{{ route('dashboard.summary') }}",
        calendarEvents: "{{ route('dashboard.calendar-events') }}",
        storeCalendarEvent: "{{ route('booking.api.calendar-events.store') }}",
        extendDeadline: "{{ route('dashboard.extend-deadline') }}",
        updateTaskCompleted: "{{ route('dashboard.tasks.complete') }}",
        dashboardTasks: "{{ route('dashboard.tasks') }}",
        dashboardCases: "{{ route('dashboard.cases-requiring-attention') }}",
        assigneeAction: "{{ route('assignee.tasks') }}"
    };

    window.dashboardData = {};
</script>
<script src="{{ asset('js/dashboard.js') }}?v={{ @filemtime(public_path('js/dashboard.js')) ?: time() }}"></script>
<script src="{{ asset('js/crm/dashboard/add-task-popover.js') }}?v={{ @filemtime(public_path('js/crm/dashboard/add-task-popover.js')) ?: time() }}"></script>
<script src="{{ asset('js/crm/dashboard/dashboard-page.js') }}?v={{ @filemtime(public_path('js/crm/dashboard/dashboard-page.js')) ?: time() }}"></script>
<script src="{{ asset('js/dashboard-calendar.js') }}?v={{ @filemtime(public_path('js/dashboard-calendar.js')) ?: time() }}"></script>
@endonce
@endpush
