@php
    $noteTypeClass = '';
    $noteIcon = 'fa-sticky-note';
    if ($activity->activity_type === 'note') {
        $subject = strtolower($activity->subject ?? '');
        if (str_contains($subject, 'call')) {
            $noteTypeClass = 'activity-type-note-call';
            $noteIcon = 'fa-phone';
        } elseif (str_contains($subject, 'email')) {
            $noteTypeClass = 'activity-type-note-email';
            $noteIcon = 'fa-envelope';
        } elseif (str_contains($subject, 'in-person')) {
            $noteTypeClass = 'activity-type-note-in-person';
            $noteIcon = 'fa-user-friends';
        } elseif (str_contains($subject, 'attention')) {
            $noteTypeClass = 'activity-type-note-attention';
            $noteIcon = 'fa-exclamation-triangle';
        } elseif (str_contains($subject, 'others')) {
            $noteTypeClass = 'activity-type-note-others';
            $noteIcon = 'fa-ellipsis-h';
        } else {
            $noteTypeClass = 'activity-type-note';
            $noteIcon = 'fa-sticky-note';
        }
    }

    $subjectOnly = \App\Models\ActivitiesLog::displaySubjectWithoutStaffPrefix($activity->activity_type ?? null, $activity->subject ?? null);
    $displayCreator = $admin ? $admin->activityFeedDisplayName() : 'Staff';
    $headlineText = $subjectOnly
        ? (string) ($activity->subject ?? '')
        : trim($displayCreator . '  ' . (string) ($activity->subject ?? ''));
    $staffName = $displayCreator;
    $compactTime = $activity->created_at ? date('d M Y, g:i A', strtotime($activity->created_at)) : '';
    if ($activity->activity_type === 'stage') {
        $summaryLine = 'Stage' . ' · ' . $staffName . ' · ' . $compactTime;
    } else {
        $summaryLine = $subjectOnly
            ? trim($headlineText . ' · ' . $staffName . ' · ' . $compactTime)
            : trim($headlineText . ' · ' . $compactTime);
    }
    $descDisplay = $activity->description != ''
        ? \App\Support\NoteDescriptionHtml::forDisplay($activity->description)
        : '';
    $bodyPlain = trim(strip_tags($descDisplay));
    $canConvert = str_contains($activity->subject ?? '', 'added a note') || str_contains($activity->subject ?? '', 'updated a note');
    $hasTaskInfo = trim((string) ($activity->task_group ?? '')) !== '' || $activity->followup_date;
    if ($activity->activity_type === 'stage') {
        $isExpandable = $bodyPlain !== '';
    } else {
        $isExpandable = $bodyPlain !== '' || $canConvert || $hasTaskInfo;
    }
    $followupDisplay = '';
    if (! empty($activity->followup_date)) {
        try {
            $followupDisplay = \Carbon\Carbon::parse($activity->followup_date)->format('d M Y, g:i A');
        } catch (\Throwable $e) {
            $followupDisplay = (string) $activity->followup_date;
        }
    }
    $detailId = 'feed-detail-' . (int) $activity->id;
@endphp
<li class="feed-item feed-item--{{ $activity->activity_type === 'stage' ? 'stage' : 'email' }}{{ $isExpandable ? '' : ' feed-item--no-expand' }} activity {{ $activity->activity_type ? 'activity-type-' . $activity->activity_type : '' }} {{ $noteTypeClass ?? '' }}"
    id="activity_{{ $activity->id }}"
    data-created-at="{{ $activity->created_at ? \Carbon\Carbon::parse($activity->created_at)->format('Y-m-d') : '' }}">
    <span
        class="feed-icon {{ $activity->activity_type === 'sms' ? 'feed-icon-sms' : '' }} {{ $activity->activity_type === 'activity' ? 'feed-icon-activity' : '' }} {{ $activity->activity_type === 'stage' ? 'feed-icon-stage' : '' }} {{ $activity->activity_type === 'financial' ? 'feed-icon-accounting' : '' }} {{ $activity->activity_type === 'signature' ? 'feed-icon-signature' : '' }} {{ $activity->activity_type === 'note' ? 'feed-icon-note ' . str_replace('activity-type-', 'feed-icon-', $noteTypeClass) : '' }}">
        @if($activity->activity_type === 'sms')
            <i class="fas fa-sms"></i>
        @elseif($activity->activity_type === 'note')
            <i class="fas {{ $noteIcon }}"></i>
        @elseif($activity->activity_type === 'activity')
            <i class="fas fa-bolt"></i>
        @elseif($activity->activity_type === 'stage')
            <i class="fas fa-tasks" aria-hidden="true"></i>
        @elseif($activity->activity_type === 'financial')
            <i class="fas fa-dollar-sign"></i>
        @elseif($activity->activity_type === 'signature')
            <i class="fas fa-file-signature"></i>
        @elseif($activity->activity_type === 'document')
            <i class="fas fa-file-alt"></i>
        @elseif(str_contains(strtolower($activity->subject ?? ''), "invoice") ||
                str_contains(strtolower($activity->subject ?? ''), "receipt") ||
                str_contains(strtolower($activity->subject ?? ''), "ledger") ||
                str_contains(strtolower($activity->subject ?? ''), "payment") ||
                str_contains(strtolower($activity->subject ?? ''), "account"))
            <i class="fas fa-dollar-sign"></i>
        @elseif(str_contains($activity->subject ?? '', "document"))
            <i class="fas fa-file-alt"></i>
        @else
            <i class="fas fa-sticky-note"></i>
        @endif
    </span>
    <div class="feed-content">
        @if($isExpandable)
            <button type="button" class="feed-item-summary" data-feed-toggle
                aria-expanded="false" aria-controls="{{ $detailId }}"
                aria-label="Show or hide full activity content">
                <span class="feed-item-summary-text">{{ $summaryLine }}</span>
                <span class="feed-item-summary-chevron" aria-hidden="true"><i class="fas fa-chevron-down"></i></span>
            </button>
            <div class="feed-item-detail" id="{{ $detailId }}" hidden>
                @if($activity->activity_type === 'stage')
                    <div class="feed-item-body-outer" data-clampable="1">
                        <div class="feed-item-body-chunk">
                            {!! $descDisplay !!}
                        </div>
                        <button type="button" class="feed-item-body-more btn btn-link btn-sm p-0" hidden>Show more</button>
                    </div>
                @else
                    <p class="feed-item-full-headline mb-0">
                        @if($subjectOnly)
                            <strong>{{ $activity->subject ?? '' }}</strong>
                        @else
                            <strong>{{ $displayCreator }}{{ $activity->subject ? '  ' . $activity->subject : '' }}</strong>
                        @endif
                        @if($canConvert)
                            <i class="fas fa-ellipsis-v convert-activity-to-note" style="margin-left: 5px; cursor: pointer;"
                                title="Convert to Note" data-activity-id="{{ $activity->id }}"
                                data-activity-subject="{{ $activity->subject }}"
                                data-activity-description="{{ $activity->description }}"
                                data-activity-created-by="{{ $activity->created_by }}"
                                data-activity-created-at="{{ $activity->created_at }}"
                                data-client-id="{{ $clientId }}"></i>
                        @endif
                    </p>
                    @if($activity->description != '')
                        <div class="feed-item-body-outer" data-clampable="1">
                            <div class="feed-item-body-chunk">
                                {!! $descDisplay !!}
                            </div>
                            <button type="button" class="feed-item-body-more btn btn-link btn-sm p-0" hidden>Show more</button>
                        </div>
                    @endif
                    @if(trim((string) ($activity->task_group ?? '')) !== '')
                        <p class="mb-0 mt-1 small text-muted feed-item-task-meta">{{ $activity->task_group }}</p>
                    @endif
                    @if($followupDisplay !== '')
                        <p class="mb-0 mt-1 small text-muted feed-item-task-meta">{{ $followupDisplay }}</p>
                    @endif
                @endif
            </div>
        @else
            <div class="feed-item-summary feed-item-summary--static" role="none">
                <span class="feed-item-summary-text">{{ $summaryLine }}</span>
            </div>
        @endif
    </div>
</li>
