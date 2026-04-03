@php
    // Determine note type for specific styling
    $noteTypeClass = '';
    $noteIcon = 'fa-sticky-note';
    
    if($activity->activity_type === 'note') {
        $subject = strtolower($activity->subject ?? '');
        
        if(str_contains($subject, 'call')) {
            $noteTypeClass = 'activity-type-note-call';
            $noteIcon = 'fa-phone';
        } elseif(str_contains($subject, 'email')) {
            $noteTypeClass = 'activity-type-note-email';
            $noteIcon = 'fa-envelope';
        } elseif(str_contains($subject, 'in-person')) {
            $noteTypeClass = 'activity-type-note-in-person';
            $noteIcon = 'fa-user-friends';
        } elseif(str_contains($subject, 'attention')) {
            $noteTypeClass = 'activity-type-note-attention';
            $noteIcon = 'fa-exclamation-triangle';
        } elseif(str_contains($subject, 'others')) {
            $noteTypeClass = 'activity-type-note-others';
            $noteIcon = 'fa-ellipsis-h';
        } else {
            $noteTypeClass = 'activity-type-note';
            $noteIcon = 'fa-sticky-note';
        }
    }
@endphp

<li class="feed-item feed-item--{{ $activity->activity_type === 'stage' ? 'stage' : 'email' }} activity {{ $activity->activity_type ? 'activity-type-' . $activity->activity_type : '' }} {{ $noteTypeClass }}" id="activity_{{ $activity->id }}" data-created-at="{{ $activity->created_at ? \Carbon\Carbon::parse($activity->created_at)->format('Y-m-d') : '' }}">
    <span class="feed-icon {{ $activity->activity_type === 'sms' ? 'feed-icon-sms' : '' }} {{ $activity->activity_type === 'activity' ? 'feed-icon-activity' : '' }} {{ $activity->activity_type === 'stage' ? 'feed-icon-stage' : '' }} {{ $activity->activity_type === 'financial' ? 'feed-icon-accounting' : '' }} {{ $activity->activity_type === 'signature' ? 'feed-icon-signature' : '' }} {{ $activity->activity_type === 'note' ? 'feed-icon-note ' . str_replace('activity-type-', 'feed-icon-', $noteTypeClass) : '' }}">
        @if($activity->activity_type === 'sms')
            <i class="fas fa-sms"></i>
        @elseif($activity->activity_type === 'note')
            <i class="fas {{ $noteIcon }}"></i>
        @elseif($activity->activity_type === 'activity')
            <i class="fas fa-bolt"></i>
        @elseif($activity->activity_type === 'stage')
            <i class="fas fa-route"></i>
        @elseif($activity->activity_type === 'financial')
            <i class="fas fa-dollar-sign"></i>
        @elseif(str_contains(strtolower($activity->subject ?? ''), "invoice") || 
                str_contains(strtolower($activity->subject ?? ''), "receipt") || 
                str_contains(strtolower($activity->subject ?? ''), "ledger") || 
                str_contains(strtolower($activity->subject ?? ''), "payment") ||
                str_contains(strtolower($activity->subject ?? ''), "account"))
            <i class="fas fa-dollar-sign"></i>
        @elseif($activity->activity_type === 'signature')
            <i class="fas fa-file-signature"></i>
        @elseif(str_contains($activity->subject ?? '', "document"))
            <i class="fas fa-file-alt"></i>
        @else
            <i class="fas fa-sticky-note"></i>
        @endif
    </span>
    <div class="feed-content">
        @if($activity->activity_type === 'stage')
            <div class="feed-item-stage">
                <div class="feed-item-stage-header">
                    <span class="feed-item-staff">{{ $admin->first_name ?? 'NA' }}{{ isset($admin->last_name) ? ' ' . $admin->last_name : '' }}</span>
                    <span class="feed-timestamp">{{ date('d M Y, H:i', strtotime($activity->created_at)) }}</span>
                </div>
                <div class="feed-item-stage-body">
                    {!! \App\Support\NoteDescriptionHtml::forDisplay($activity->description ?? '') !!}
                </div>
            </div>
        @else
            <p>
                @if(\App\Models\ActivitiesLog::displaySubjectWithoutStaffPrefix($activity->activity_type ?? null, $activity->subject ?? null))
                <strong>{{ $activity->subject ?? '' }}</strong>
                @else
                <strong>{{ $admin->first_name ?? 'NA' }}  {{ $activity->subject ?? '' }}</strong>
                @endif
                @if(str_contains($activity->subject ?? '', 'added a note') || str_contains($activity->subject ?? '', 'updated a note'))
                    <i class="fas fa-ellipsis-v convert-activity-to-note" 
                       style="margin-left: 5px; cursor: pointer;" 
                       title="Convert to Note"
                       data-activity-id="{{ $activity->id }}"
                       data-activity-subject="{{ $activity->subject }}"
                       data-activity-description="{{ $activity->description }}"
                       data-activity-created-by="{{ $activity->created_by }}"
                       data-activity-created-at="{{ $activity->created_at }}"
                       data-client-id="{{ $clientId }}"></i>
                @endif
                -
                @if($activity->description != '')
                    {!! \App\Support\NoteDescriptionHtml::forDisplay($activity->description) !!}
                @endif
            </p>
            <span class="feed-timestamp">{{ date('d M Y, H:i A', strtotime($activity->created_at)) }}</span>
        @endif
    </div>
</li>

