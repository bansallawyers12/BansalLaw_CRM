@php
    $_staffTop = Auth::user();
    $_crmTopAdminish = $_staffTop instanceof \App\Models\Staff && $_staffTop->canAccessAdminConsole();
    $_inboxSyncMasterOn = \App\Services\EmailSync\InboxSyncMasterControl::isEnabled();
    $_canSyncInboxNav = $_inboxSyncMasterOn && $_staffTop instanceof \App\Models\Staff && $_staffTop->canSyncInboxEmails();
    $_canViewSyncedInboxNav = $_inboxSyncMasterOn && $_staffTop instanceof \App\Models\Staff && $_staffTop->canViewSyncedInboxMail();
    $_canViewAllSyncedInbox = $_staffTop instanceof \App\Models\Staff && $_staffTop->canViewAllSyncedInboxMail();
    $_unassignedMailCount = 0;
    if ($_canViewSyncedInboxNav && $_staffTop instanceof \App\Models\Staff) {
        $_unassignedMailCount = \App\Services\EmailSync\IncomingEmailSyncService::countUnassignedSyncedInboxMail($_staffTop);
    }
    $_showUnassignedNavOption = $_inboxSyncMasterOn && $_canViewSyncedInboxNav && ($_canViewAllSyncedInbox || $_unassignedMailCount > 0);
    $_pendingTaskCount = 0;
    if ($_staffTop instanceof \App\Models\Staff) {
        $_pendingTaskCount = app(\App\Services\DashboardService::class)->getPendingOpenTaskCount($_staffTop);
    }

    $_routeName = Route::currentRouteName() ?? '';
    $_navAccounts = in_array($_routeName, [
        'clients.invoicelist',
        'clients.clientreceiptlist',
        'clients.officereceiptlist',
        'clients.journalreceiptlist',
        'clients.analytics-dashboard',
    ], true);
    $_navUnassigned = $_routeName === 'clients.unassigned-emails';
    $_navActive = [
        'dashboard' => $_routeName === 'dashboard' || str_starts_with($_routeName, 'dashboard.'),
        'signatures' => str_starts_with($_routeName, 'signatures.'),
        'booking' => str_starts_with($_routeName, 'booking.'),
        'officevisits' => str_starts_with($_routeName, 'officevisits.'),
        'frontdesk' => str_starts_with($_routeName, 'front-desk.checkin'),
        'tasks' => $_routeName === 'assignee.tasks' || str_starts_with($_routeName, 'assignee.tasks.'),
        'unassigned' => $_navUnassigned,
        'accounts' => $_navAccounts,
        'clients' => (
            str_starts_with($_routeName, 'clients.')
            || str_starts_with($_routeName, 'leads.')
            || str_starts_with($_routeName, 'emails.smart-import')
            || str_starts_with($_routeName, 'communication-check.')
        ) && !$_navUnassigned && !$_navAccounts,
    ];
@endphp
<nav class="main-topbar">
    <button class="topbar-toggle" title="Show menu" aria-label="Toggle topbar">
        <i class="fa-solid fa-ellipsis"></i>
    </button>
    <div class="topbar-left">
        <div class="icon-group">
            <a href="{{route('dashboard')}}" class="icon-btn{{ $_navActive['dashboard'] ? ' active' : '' }}" title="Dashboard"@if($_navActive['dashboard']) aria-current="page"@endif><i class="fa-solid fa-tachometer-alt"></i></a>
            <a href="{{ route('dashboard') }}#myCalendarSection" class="icon-btn" title="My Calendar"><i class="fa-solid fa-calendar-days"></i></a>
            <a href="{{ route('signatures.index') }}" class="icon-btn{{ $_navActive['signatures'] ? ' active' : '' }}" title="Signature Dashboard"@if($_navActive['signatures']) aria-current="page"@endif><i class="fa-solid fa-pen"></i></a>
            <div class="icon-dropdown js-dropdown">
                <a href="{{ route('booking.appointments.index') }}" class="icon-btn{{ $_navActive['booking'] ? ' active' : '' }}" title="Website Bookings" style="position: relative;"@if($_navActive['booking']) aria-current="page"@endif>
                    <i class="fa-solid fa-globe"></i>
                    @php
                        $pendingCount = \App\Models\BookingAppointment::where('status', 'pending')->where('is_paid', 1)->count();
                    @endphp
                    @if($pendingCount > 0)
                        <span class="badge bg-danger" style="position: absolute; top: -5px; right: -5px; font-size: 10px; padding: 2px 5px; border-radius: 10px;">{{ $pendingCount }}</span>
                    @endif
                </a>
                <div class="icon-dropdown-menu">
                    <a class="dropdown-item" href="{{ route('booking.appointments.index') }}">
                        <i class="fa-solid fa-list me-2"></i> All Bookings
                    </a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item" href="{{ route('booking.appointments.calendar', ['type' => 'ajay']) }}">
                        <i class="fa-solid fa-calendar-days me-2"></i> Ajay
                    </a>
                    <a class="dropdown-item" href="{{ route('booking.appointments.calendar', ['type' => 'kunal']) }}">
                        <i class="fa-solid fa-calendar-days me-2"></i> Michael
                    </a>
                    @if($_crmTopAdminish)
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item" href="{{ route('booking.sync.dashboard') }}">
                        <i class="fa-solid fa-rotate me-2"></i> Sync Status
                    </a>
                    @endif
                </div>
            </div>
            <a href="{{route('officevisits.waiting')}}" class="icon-btn{{ $_navActive['officevisits'] ? ' active' : '' }}" title="In Person"@if($_navActive['officevisits']) aria-current="page"@endif><i class="fa-solid fa-user-check"></i></a>
            @if(Auth::user() instanceof \App\Models\Staff && Auth::user()->canAccessFrontDeskCheckIn())
            <a href="{{ route('front-desk.checkin.index') }}" class="icon-btn{{ $_navActive['frontdesk'] ? ' active' : '' }}" title="Front-Desk Check-In"@if($_navActive['frontdesk']) aria-current="page"@endif><i class="fa-solid fa-clipboard-check"></i></a>
            @endif
            <a href="{{ route('assignee.tasks') }}" id="crmNavPendingTasks" class="icon-btn{{ $_navActive['tasks'] ? ' active' : '' }}" title="Tasks" style="position: relative;"@if($_navActive['tasks']) aria-current="page"@endif>
                <i class="fa-solid fa-list-check"></i>
                @if($_pendingTaskCount > 0)
                    <span class="badge bg-danger crm-nav-pending-tasks-badge" style="position: absolute; top: -5px; right: -5px; font-size: 10px; padding: 2px 5px; border-radius: 10px;">{{ $_pendingTaskCount }}</span>
                @endif
            </a>
            <div class="icon-dropdown js-dropdown">
                <a href="{{route('clients.index')}}" class="icon-btn{{ $_navActive['clients'] ? ' active' : '' }}" title="Clients"@if($_navActive['clients']) aria-current="page"@endif><i class="fa-solid fa-users"></i></a>
                <div class="icon-dropdown-menu">
                    <a class="dropdown-item" href="{{route('clients.index')}}"><i class="fa-solid fa-list me-2"></i> Client List</a>
                    <a class="dropdown-item" href="{{route('clients.clientsmatterslist')}}"><i class="fa-solid fa-folder-open me-2"></i> Matter List</a>
                    <a class="dropdown-item" href="{{ route('emails.smart-import.index') }}"><i class="fa-solid fa-file-import me-2"></i> Smart Email Import</a>
                    @php
                        $_ccStaff = auth()->guard('admin')->user();
                        $_canCommunicationCheck = config('crm.communication_check.enabled')
                            && $_ccStaff instanceof \App\Models\Staff
                            && $_ccStaff->canUseCommunicationCheck();
                    @endphp
                    @if($_canCommunicationCheck)
                    <a class="dropdown-item" href="{{ route('communication-check.index') }}"><i class="fa-solid fa-shield-halved me-2"></i> Communication Check</a>
                    @endif
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item" href="{{route('leads.index')}}"><i class="fa-solid fa-list-alt me-2"></i> Lead List</a>
                    <a class="dropdown-item" href="{{route('leads.other_parties.index')}}"><i class="fa-solid fa-user-tag me-2"></i> Other Parties</a>
                    <a class="dropdown-item" href="{{route('leads.create')}}"><i class="fa-solid fa-circle-plus me-2"></i> Add Lead</a>
                    <a class="dropdown-item" href="{{route('leads.create', ['other_party' => 1])}}"><i class="fa-solid fa-user-plus me-2"></i> Add Other Party</a>
                </div>
            </div>
            @if($_canViewSyncedInboxNav)
            <a href="{{ route('clients.unassigned-emails') }}" id="crmNavUnassignedMail" class="icon-btn{{ $_navActive['unassigned'] ? ' active' : '' }}" title="Unassigned Mail" data-is-admin="{{ $_canViewAllSyncedInbox ? '1' : '0' }}" style="position: relative; {{ $_showUnassignedNavOption ? '' : 'display: none !important;' }}"@if($_navActive['unassigned']) aria-current="page"@endif>
                <i class="fa-solid fa-inbox"></i>
                @if($_unassignedMailCount > 0)
                    <span class="badge bg-danger crm-nav-unassigned-badge" style="position: absolute; top: -5px; right: -5px; font-size: 10px; padding: 2px 5px; border-radius: 10px;">{{ $_unassignedMailCount }}</span>
                @endif
            </a>
            @endif
            <div class="icon-dropdown js-dropdown">
                <a href="{{route('clients.invoicelist')}}" class="icon-btn{{ $_navActive['accounts'] ? ' active' : '' }}" title="Accounts"@if($_navActive['accounts']) aria-current="page"@endif><i class="fa-solid fa-briefcase"></i></a>
                <div class="icon-dropdown-menu">
                    @if($_crmTopAdminish)
                    <a class="dropdown-item" href="{{route('clients.analytics-dashboard')}}" style="background: linear-gradient(135deg, var(--navy)15 0%, var(--sidebar-active)15 100%); font-weight: 600;"><i class="fa-solid fa-chart-line me-2" style="color: var(--navy);"></i> Analytics Dashboard</a>
                    <div class="dropdown-divider"></div>
                    @endif
                    <a class="dropdown-item" href="{{route('clients.clientreceiptlist')}}"><i class="fa-solid fa-receipt me-2"></i> Client Receipts</a>
                    <a class="dropdown-item" href="{{route('clients.invoicelist')}}"><i class="fa-solid fa-file-invoice-dollar me-2"></i> Invoice Lists</a>
                    <a class="dropdown-item" href="{{route('clients.officereceiptlist')}}"><i class="fa-solid fa-building me-2"></i> Office Receipts</a>
                    <a class="dropdown-item" href="{{route('clients.journalreceiptlist')}}"><i class="fa-solid fa-book me-2"></i> Journal Receipts</a>
                    <div class="dropdown-divider"></div>
                </div>
            </div>
        </div>
    </div>
    <div class="topbar-center">
        <form class="topbar-search" action="#" method="get" onsubmit="return false;">
            <div class="topbar-search__inner">
                <span class="topbar-search__icon" aria-hidden="true"><i class="fa-solid fa-search"></i></span>
                <select class="form-control js-data-example-ajaxccsearch" type="search" placeholder="Search" aria-label="Search" data-width="320"></select>
            </div>
        </form>
    </div>
    <div class="topbar-right">
        @if($_staffTop instanceof \App\Models\Staff && $_staffTop->canToggleSuperAdminElevation())
            @php
                $_saElevated = $_staffTop->hasEffectiveSuperAdminPrivileges();
            @endphp
            <form action="{{ route('crm.session.super-admin-mode') }}" method="post" class="d-inline align-middle me-1" style="vertical-align: middle;">
                @csrf
                <input type="hidden" name="elevated" value="{{ $_saElevated ? '0' : '1' }}">
                <button type="submit" class="icon-btn {{ $_saElevated ? 'text-primary' : '' }}" title="{{ $_saElevated ? 'Using Super Admin access — click to return to normal role' : 'Switch to Super Admin access (full privileges)' }}" style="white-space: nowrap;">
                    <i class="fa-solid fa-user-shield"></i>
                    <span class="d-none d-xl-inline ms-1" style="font-size: 12px; font-weight: 600;">{{ $_saElevated ? 'Super Admin' : 'Normal' }}</span>
                </button>
            </form>
        @endif
        <a href="javascript:;" title="Add Office Check-In" class="icon-btn opencheckin"><i class="fa-solid fa-person-booth"></i></a>
        @if(Auth::user())
            @php
                $notifUnread = \App\Models\Notification::where('receiver_id', Auth::user()->id)->where('receiver_status', 0)->count();
                $recentNotifs = \App\Models\Notification::where('receiver_id', Auth::user()->id)
                                    ->orderBy('id', 'DESC')
                                    ->take(5)
                                    ->get();
            @endphp
            <div class="dropdown d-inline-block align-middle me-1">
                <a href="javascript:;" class="icon-btn notification-toggle" title="Notifications" data-bs-toggle="dropdown" aria-expanded="false" style="position: relative;">
                    <span class="notification-bell-inner">
                        <i class="fa-solid fa-bell" aria-hidden="true"></i>
                        @if($notifUnread > 0)
                            <span class="countbell" id="countbell_notification" aria-live="polite">{{ $notifUnread }}</span>
                        @endif
                    </span>
                </a>
                <div class="dropdown-menu dropdown-menu-end shadow-sm" style="width: 350px; padding: 0; border: 1px solid #e2e8f0; border-radius: 12px; margin-top: 8px; z-index: 1050;">
                    <div class="dropdown-header d-flex justify-content-between align-items-center" style="background: #f8fafc; padding: 12px 16px; border-bottom: 1px solid #e2e8f0; border-radius: 12px 12px 0 0;">
                        <h6 class="m-0" style="font-weight: 600; color: #1e293b;">Notifications</h6>
                        @if($notifUnread > 0)
                            <span class="badge" style="background: #ef4444; color: white; border-radius: 999px;">{{ $notifUnread }} New</span>
                        @endif
                    </div>
                    <div style="max-height: 360px; overflow-y: auto;">
                        @if($recentNotifs->count() > 0)
                            @foreach($recentNotifs as $notif)
                                <a href="{{ $notif->url }}?t={{ $notif->id }}" class="dropdown-item d-flex align-items-start py-3 px-3" style="border-bottom: 1px solid #f1f5f9; white-space: normal; background: {{ ($notif->receiver_status ?? 0) == 0 ? '#eff6ff' : 'transparent' }};">
                                    <div class="me-3 mt-1">
                                        @if(($notif->receiver_status ?? 0) == 0)
                                            <div style="width: 8px; height: 8px; background: #3b82f6; border-radius: 50%; box-shadow: 0 0 0 2px #bfdbfe;"></div>
                                        @else
                                            <div style="width: 8px; height: 8px; background: #cbd5e1; border-radius: 50%;"></div>
                                        @endif
                                    </div>
                                    <div class="flex-grow-1">
                                        <p class="m-0" style="font-size: 0.85rem; color: #334155; line-height: 1.4; text-wrap: wrap;">{{ $notif->message }}</p>
                                        <small style="font-size: 0.75rem; color: #64748b; margin-top: 4px; display: block;">{{ date('d M Y, h:i A', strtotime($notif->created_at)) }}</small>
                                    </div>
                                </a>
                            @endforeach
                        @else
                            <div class="text-center py-5">
                                <i class="fa-regular fa-bell" style="font-size: 2rem; color: #cbd5e1; margin-bottom: 12px;"></i>
                                <p class="m-0" style="color: #64748b; font-size: 0.9rem;">No recent notifications</p>
                            </div>
                        @endif
                    </div>
                    <div class="text-center" style="background: #f8fafc; border-top: 1px solid #e2e8f0; border-radius: 0 0 12px 12px;">
                        <a href="{{ route('crm.all-notifications') }}" class="d-block py-2" style="font-size: 0.85rem; font-weight: 600; color: #3b82f6; text-decoration: none;">View All Notifications</a>
                    </div>
                </div>
            </div>
        @endif
        <div class="profile-dropdown js-dropdown-right">
            <a href="#" class="profile-trigger" id="profile-trigger">
                <img alt="{{ Auth::user() ? Str::limit(Auth::user()->first_name.' '.Auth::user()->last_name, 150, '...') : 'Staff' }}" src="{{ Auth::user() ? Auth::user()->profile_img : asset('img/avatar.png') }}" class="user-img-radious-style"/>
            </a>
            <div class="profile-menu" id="profile-menu">
                <a href="{{route('my_profile')}}">
                    <i class="fa-regular fa-user"></i> 
                    <span>Profile</span>
                </a>
                @if($_crmTopAdminish)
                <a href="{{route('adminconsole.features.matter.index')}}">
                    <i class="fa-solid fa-gears"></i> 
                    <span>Admin Console</span>
                </a>
                @endif
                <div class="dropdown-divider"></div>
                <a href="javascript:void(0)" class="text-danger dropdown-item" data-logout="all">
                    <i class="fa-solid fa-right-from-bracket"></i>
                    <span>Log out everywhere</span>
                </a>
            </div>
        </div>
    </div>
</nav>

<form id="crm-logout-form" action="{{ route('crm.logout') }}" method="POST" style="display: none;">
    @csrf
    <input type="hidden" name="id" value="{{ Auth::user() ? Auth::user()->id : '' }}">
</form>
<script>
(function () {
    var countsUrl = @json(route('tasks.counts'));

    function applyPendingTaskBadge(count) {
        var link = document.getElementById('crmNavPendingTasks');
        if (!link) return;
        count = Math.max(0, Number(count) || 0);
        var badge = link.querySelector('.crm-nav-pending-tasks-badge');
        if (count > 0) {
            if (!badge) {
                badge = document.createElement('span');
                badge.className = 'badge bg-danger crm-nav-pending-tasks-badge';
                badge.style.position = 'absolute';
                badge.style.top = '-5px';
                badge.style.right = '-5px';
                badge.style.fontSize = '10px';
                badge.style.padding = '2px 5px';
                badge.style.borderRadius = '10px';
                link.appendChild(badge);
            }
            badge.textContent = String(count);
        } else if (badge) {
            badge.remove();
        }
    }

    window.refreshCrmNavPendingTaskCount = function () {
        if (!countsUrl) return Promise.resolve();
        return fetch(countsUrl, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json'
            },
            credentials: 'same-origin'
        })
            .then(function (res) { return res.ok ? res.json() : null; })
            .then(function (data) {
                if (data && typeof data.all !== 'undefined') {
                    applyPendingTaskBadge(data.all);
                }
            })
            .catch(function () { /* ignore */ });
    };
})();
</script>
