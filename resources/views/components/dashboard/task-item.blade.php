@props(['note'])

@php
    $client = $note->client;
    
    // Handle tasks with and without deadlines (date-only: due today is not overdue)
    if ($note->note_deadline) {
        $deadline = \Carbon\Carbon::parse($note->note_deadline)->startOfDay();
        $today = \Carbon\Carbon::today();
        $isOverdue = $deadline->lt($today);
        
        if ($isOverdue) {
            $daysOverdue = (int) $deadline->diffInDays($today);
            $daysLeftText = $daysOverdue . ' day' . ($daysOverdue != 1 ? 's' : '') . ' overdue';
            $urgencyClass = 'overdue';
        } else {
            $daysLeft = (int) $today->diffInDays($deadline);
            
            if ($daysLeft == 0) {
                $daysLeftText = 'Today';
                $urgencyClass = 'today';
            } elseif ($daysLeft == 1) {
                $daysLeftText = 'Tomorrow';
                $urgencyClass = 'tomorrow';
            } elseif ($daysLeft <= 7) {
                $daysLeftText = $deadline->format('D');
                $urgencyClass = 'this-week';
            } else {
                $daysLeftText = $deadline->format('M d');
                $urgencyClass = 'upcoming';
            }
        }
        $deadlineFormatted = $deadline->format('Y-m-d');
    } else {
        // Task without deadline
        $daysLeftText = 'No deadline';
        $urgencyClass = 'no-deadline';
        $isOverdue = false;
        $deadlineFormatted = '';
    }
    
    $assignedUser = $note->assignedUser ?? null;
    $assignedName = $assignedUser ? $assignedUser->first_name . ' ' . $assignedUser->last_name : 'Unassigned';

    // Client display: Personal Tasks have null client; companies use company name from accessor
    $clientName = $client ? trim($client->company_name_or_personal_name) : 'Personal Task';
    if ($client && $clientName === '') {
        $clientName = trim($client->first_name . ' ' . $client->last_name) ?: 'Client';
    }
    $clientCode = $client && $client->client_id ? $client->client_id : '';
    $clientId = $client ? (string) $client->id : '';

    // Safely encode data for attributes (single Blade escape; collapse newlines)
    $descriptionForAttr = preg_replace("/\r\n|\r|\n/", ' ', (string) ($note->description ?? '')) ?? '';
    $uniqueGroupIdSafe = $note->unique_group_id ? json_encode($note->unique_group_id) : 'null';
    $clientDetailUrl = $client ? route('clients.detail', base64_encode(convert_uuencode($client->id))) : '';
@endphp

<li class="todo-task-item"
    data-task-id="{{ $note->id }}"
    data-unique-group-id="{{ $note->unique_group_id ?? '' }}"
    data-client-id="{{ $clientId }}"
    data-client-detail-url="{{ $clientDetailUrl }}"
    data-client-name="{{ e($clientName) }}"
    data-client-code="{{ e($clientCode) }}"
    data-description="{{ $descriptionForAttr }}"
    data-deadline="{{ $note->note_deadline ?? '' }}"
    data-deadline-formatted="{{ $deadlineFormatted }}"
    data-assigned-to="{{ e($assignedName) }}"
    data-urgency="{{ $urgencyClass }}">
    
    <div class="todo-task-checkbox">
        <input type="checkbox"
               id="task-{{ $note->id }}"
               class="task-complete-checkbox"
               aria-label="Mark task complete"
               onclick="event.stopPropagation(); this.checked = false; handleTaskComplete({{ $note->id }}, {{ $uniqueGroupIdSafe }})">
        <label for="task-{{ $note->id }}" class="visually-hidden">Mark task complete</label>
    </div>
    
    <div class="todo-task-content"
         role="button"
         tabindex="0"
         aria-label="View task details: {{ e(Str::limit(strip_tags($note->description), 80)) }}"
         data-task-id="{{ $note->id }}"
         onclick="openTaskDetail({{ $note->id }})">
        <div class="todo-task-title">
            {{ Str::limit(strip_tags($note->description), 60) }}
        </div>
        <div class="todo-task-meta">
            <span class="task-client-info">
                <i class="fa-solid fa-user" aria-hidden="true"></i>
                {{ $clientName }}
                @if($clientCode)
                    <span class="task-client-code">({{ $clientCode }})</span>
                @endif
            </span>
        </div>
    </div>
    
    <div class="todo-task-actions">
        <span class="todo-task-due {{ $urgencyClass }}">
            @if($note->note_deadline)
                @if($isOverdue)
                    <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
                @else
                    <i class="fa-regular fa-calendar" aria-hidden="true"></i>
                @endif
            @else
                <i class="fa-solid fa-infinity" aria-hidden="true"></i>
            @endif
            {{ $daysLeftText }}
        </span>
        <div class="todo-task-hover-actions">
            @if($note->note_deadline)
                <button type="button"
                        class="todo-action-btn"
                        onclick="event.stopPropagation(); openExtendModal({{ $note->id }})"
                        title="Extend Deadline"
                        aria-label="Extend deadline">
                    <i class="fa-solid fa-calendar-plus" aria-hidden="true"></i>
                </button>
            @else
                <button type="button"
                        class="todo-action-btn"
                        onclick="event.stopPropagation(); openAddDeadlineModal({{ $note->id }})"
                        title="Add Deadline"
                        aria-label="Add deadline">
                    <i class="fa-solid fa-calendar-plus" aria-hidden="true"></i>
                </button>
            @endif
        </div>
    </div>
</li>
