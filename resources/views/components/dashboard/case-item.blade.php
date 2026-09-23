@props(['case'])

@php
    $client = $case->client;
    $lastUpdated = new DateTime($case->updated_at);
    $today = new DateTime();
    $interval = $today->diff($lastUpdated);
    $daysStalled = $interval->days;
    
    // Safety check for null client
    if (!$client) {
        $client = (object) [
            'id' => null,
            'first_name' => null,
            'last_name' => null,
            'client_id' => null
        ];
    }
    
    if ($daysStalled < 1) {
        $daysStalledText = 'Today';
    } elseif ($daysStalled === 1) {
        $daysStalledText = '1 day ago';
    } else {
        $daysStalledText = $daysStalled . ' days ago';
    }
    
    $daysStalledClass = $daysStalled > 14 ? 'text-danger' : ($daysStalled > 7 ? 'text-warning' : 'text-info');

    $urgencyBorder = $daysStalled > 14 ? 'danger' : ($daysStalled > 7 ? 'warning' : 'info');
    
    $matter = $case->matter ?? null;
    $matter_name = $matter
        ? \App\Models\Matter::displayTitleFromJoinedRow($matter->title)
        : 'NA';

    $upcomingDeadline = $case->upcoming_deadline ?? null;
    $daysUntilDeadline = null;
    if ($upcomingDeadline) {
        $deadlineDate = $upcomingDeadline instanceof \DateTimeInterface
            ? \Carbon\Carbon::instance($upcomingDeadline)->startOfDay()
            : \Carbon\Carbon::parse($upcomingDeadline)->startOfDay();
        $daysUntilDeadline = \Carbon\Carbon::today()->diffInDays($deadlineDate);
    }
    
    // Latest activity is matter-scoped: badge type and date both come from the matter row.
    $latestActivity = $case->latest_activity ?? ['type' => 'default', 'date' => $case->updated_at];
    $activityType = $latestActivity['type'];
    
    $activityConfig = [
        'deadline_approaching' => [
            'label' => 'Deadline Soon',
            'icon' => 'fa-hourglass-half',
            'class' => 'activity-deadline',
            'color' => '#c8992a'
        ],
        'signed' => [
            'label' => 'Document Signed',
            'icon' => 'fa-file-signature',
            'class' => 'activity-signed',
            'color' => '#28a745'
        ],
        'document_uploaded' => [
            'label' => 'Document Uploaded',
            'icon' => 'fa-upload',
            'class' => 'activity-upload',
            'color' => '#3a6fa8'
        ],
        'note_added' => [
            'label' => 'Note Added',
            'icon' => 'fa-note-sticky',
            'class' => 'activity-note',
            'color' => '#ffc107'
        ],
        'email_sent' => [
            'label' => 'Email Sent',
            'icon' => 'fa-envelope',
            'class' => 'activity-email',
            'color' => '#17a2b8'
        ],
        'sms_sent' => [
            'label' => 'SMS Sent',
            'icon' => 'fa-sms',
            'class' => 'activity-sms',
            'color' => '#00bcd4'
        ],
        'status_changed' => [
            'label' => 'Status Changed',
            'icon' => 'fa-right-left',
            'class' => 'activity-status',
            'color' => '#1e3d60'
        ],
        'stage_updated' => [
            'label' => 'Stage Updated',
            'icon' => 'fa-list-check',
            'class' => 'activity-stage',
            'color' => '#fd7e14'
        ],
        'appointment_scheduled' => [
            'label' => 'Appointment Set',
            'icon' => 'fa-calendar-check',
            'class' => 'activity-appointment',
            'color' => '#20c997'
        ],
        'payment_received' => [
            'label' => 'Payment Received',
            'icon' => 'fa-dollar-sign',
            'class' => 'activity-payment',
            'color' => '#28a745'
        ],
        'default' => [
            'label' => 'Recently Updated',
            'icon' => 'fa-clock',
            'class' => 'activity-default',
            'color' => '#6c757d'
        ]
    ];
    
    $activity = $activityConfig[$activityType] ?? $activityConfig['default'];

    if ($daysUntilDeadline !== null) {
        if ($daysUntilDeadline < 1) {
            $contextText = 'Due today';
        } elseif ($daysUntilDeadline === 1) {
            $contextText = 'Due in 1 day';
        } else {
            $contextText = 'Due in ' . $daysUntilDeadline . ' days';
        }

        $contextClass = $daysUntilDeadline <= 1 ? 'text-danger' : ($daysUntilDeadline <= 3 ? 'text-warning' : 'text-info');
        $urgencyBorder = $daysUntilDeadline <= 1 ? 'danger' : ($daysUntilDeadline <= 3 ? 'warning' : 'info');
    } else {
        $contextText = $daysStalledText;
        $contextClass = $daysStalledClass;
    }
@endphp

<li class="case-list-item case-urgency-border--{{ $urgencyBorder }}">
    <div class="case-details">
        <span class="client-name">
            {{ $client->first_name ?: config('constants.empty') }} {{ $client->last_name ?: config('constants.empty') }}
            (<a href="{{ route('clients.detail', [base64_encode(convert_uuencode($client->id)), $case->client_unique_matter_no]) }}">
                {{ $client->client_id ?: config('constants.empty') }}
            </a>)
        </span>
        <span class="case-info">
            <a href="{{ route('clients.detail', [base64_encode(convert_uuencode($client->id)), $case->client_unique_matter_no]) }}">
                {{ $matter_name }} ({{ $case->client_unique_matter_no }})
            </a>
            <span style="display: inline-block;" class="stalled-days {{ $contextClass }}">
                ({{ $contextText }})
            </span>
        </span>
    </div>
    <div class="case-activity-badge {{ $activity['class'] }}">
        <i class="fa-solid {{ $activity['icon'] }}"></i>
        <span class="activity-label">{{ $activity['label'] }}</span>
    </div>
</li>

