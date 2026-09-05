@php
    $assignees_completed = $assignees_completed ?? collect();
    $appendOnly = $appendOnly ?? false;
@endphp
@if (count($assignees_completed) > 0)
    @foreach ($assignees_completed as $list)
        @php
            $staff = $list->noteStaff;
            $full_name = $staff ? ($staff->first_name ?? 'N/A') . ' ' . ($staff->last_name ?? 'N/A') : 'N/A';
            $client_name = $list->noteClient ? trim($list->noteClient->company_name_or_personal_name) : 'N/P';
            if ($list->noteClient && $client_name === '') {
                $client_name = trim($list->noteClient->first_name . ' ' . $list->noteClient->last_name) ?: 'N/P';
            }
            $rowIndex = isset($i) ? ++$i : ($assignees_completed->firstItem() + $loop->index);
        @endphp
        <tr data-note-id="{{ $list->id }}">
            <td style="text-align: center;">{{ $rowIndex }}</td>
            <td style="text-align: center;">
                <button type="button"
                    class="action-done-btn action-done-btn--done not_complete_task"
                    data-id="{{ $list->id }}"
                    data-unique_group_id="{{ $list->unique_group_id }}"
                    data-bs-toggle="tooltip"
                    title="Mark incomplete"
                    aria-label="Mark incomplete">
                    <i class="fa-solid fa-check" aria-hidden="true"></i>
                </button>
            </td>
            <td>{{ $full_name }}</td>
            <td>
                {{ $client_name }}<br>
                @if ($list->noteClient)
                    <a href="{{ URL::to('/clients/detail/' . base64_encode(convert_uuencode($list->client_id))) }}" target="_blank">{{ $list->noteClient->client_id }}</a>
                @endif
            </td>
            <td>{{ $list->action_date ? date('d/m/Y', strtotime($list->action_date)) : 'N/P' }}</td>
            <td>{{ $list->task_group ?? 'N/P' }}</td>
            <td>
                @if (isset($list->description) && $list->description != "")
                    @if (strlen($list->description) > 190)
                        {{ substr($list->description, 0, 190) }}
                        <button type="button" class="btn btn-link" data-bs-toggle="popover" title="" data-content="{{ htmlspecialchars($list->description, ENT_QUOTES, 'UTF-8') }}">Read more</button>
                    @else
                        {{ $list->description }}
                    @endif
                @else
                    N/P
                @endif
            </td>
            <td>
                <div class="action-buttons">
                    @if ($list->task_group != 'Personal Task' && $list->task_group != 'Personal Action')
                        <button type="button"
                            data-noteid="{{ e($list->description ?? '') }}"
                            data-taskid="{{ $list->id }}"
                            data-taskgroupid="{{ e($list->task_group ?? '') }}"
                            data-actiondate="{{ $list->action_date }}"
                            data-assignedto="{{ $list->assigned_to }}"
                            data-clientid="{{ $list->client_id ? base64_encode(convert_uuencode($list->client_id)) : '' }}"
                            data-bs-toggle="tooltip"
                            title="Update Task"
                            class="btn btn-primary btn-sm update_task completed-update-task">
                            <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                        </button>
                    @endif

                    <button type="button"
                        class="btn btn-danger btn-sm deleteCompletedNote"
                        data-remote="{{ route('assignee.destroy_complete_activity', $list->id) }}"
                        data-bs-toggle="tooltip"
                        title="Delete">
                        <i class="fa-solid fa-trash" aria-hidden="true"></i>
                    </button>
                </div>
            </td>
        </tr>
    @endforeach
@elseif (empty($appendOnly))
    <tr class="completed-tasks-empty-row">
        <td colspan="8" style="text-align: center; padding: 20px;">
            There are no completed tasks.
        </td>
    </tr>
@endif
