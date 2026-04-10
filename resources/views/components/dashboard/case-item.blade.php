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
    } else {
        $daysStalledText = $daysStalled . ' days ago';
    }
    
    $daysStalledClass = $daysStalled > 14 ? 'text-danger' : ($daysStalled > 7 ? 'text-warning' : 'text-info');
    
    // Get matter name
    if ($case->sel_matter_id == 1) {
        $matter_name = 'General matter';
    } else {
        $matter = $case->matter ?? null;
        $matter_name = $matter ? $matter->title : 'NA';
    }
    
    // Get latest activity information
    $latestActivity = $case->latest_activity ?? ['type' => 'default', 'date' => $case->updated_at];
    $activityType = $latestActivity['type'];
    
    $activityConfig = [
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
            'icon' => 'fa-sticky-note',
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
            'icon' => 'fa-exchange-alt',
            'class' => 'activity-status',
            'color' => '#1e3d60'
        ],
        'stage_updated' => [
            'label' => 'Stage Updated',
            'icon' => 'fa-tasks',
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
@endphp

<li>
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
            <span style="display: inline-block;" class="stalled-days {{ $daysStalledClass }}">
                ({{ $daysStalledText }})
            </span>
        </span>
    </div>
    <div class="case-activity-badge {{ $activity['class'] }}">
        <i class="fas {{ $activity['icon'] }}"></i>
        <span class="activity-label">{{ $activity['label'] }}</span>
    </div>
</li>

<style>
.case-activity-badge {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 0.8em;
    font-weight: 600;
    white-space: nowrap;
    transition: all 0.2s ease;
    border: 2px solid;
}

.case-activity-badge i {
    font-size: 1em;
}

/* Activity Type Colors — theme.md soft tints */
.activity-signed {
    background: rgba(30, 122, 82, 0.12);
    color: #1e7a52;
    border-color: rgba(30, 122, 82, 0.35);
}

.activity-upload {
    background: rgba(58, 111, 168, 0.12);
    color: #1e3d60;
    border-color: rgba(58, 111, 168, 0.35);
}

.activity-note {
    background: rgba(200, 153, 42, 0.15);
    color: #7a5800;
    border-color: rgba(200, 153, 42, 0.4);
}

.activity-email {
    background: rgba(58, 111, 168, 0.1);
    color: #1e3d60;
    border-color: #c8dcef;
}

.activity-status {
    background: rgba(30, 61, 96, 0.08);
    color: #1e3d60;
    border-color: rgba(30, 61, 96, 0.25);
}

.activity-stage {
    background: rgba(200, 153, 42, 0.12);
    color: #7a5800;
    border-color: rgba(200, 153, 42, 0.35);
}

.activity-appointment {
    background: rgba(30, 122, 82, 0.1);
    color: #1e7a52;
    border-color: rgba(30, 122, 82, 0.3);
}

.activity-payment {
    background: rgba(30, 122, 82, 0.12);
    color: #1e7a52;
    border-color: rgba(30, 122, 82, 0.35);
}

.activity-sms {
    background: rgba(58, 111, 168, 0.1);
    color: #1e3d60;
    border-color: #c8dcef;
}

.activity-default {
    background: rgba(94, 122, 144, 0.12);
    color: #1a2c40;
    border-color: #c8dcef;
}

/* Hover effect */
.case-activity-badge:hover {
    transform: scale(1.05);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
}

/* Mobile responsive */
@media (max-width: 768px) {
    .case-activity-badge {
        font-size: 0.75em;
        padding: 4px 8px;
    }
    
    .activity-label {
        display: none;
    }
    
    .case-activity-badge i {
        font-size: 1.2em;
    }
}
</style>
