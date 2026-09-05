@php
    $assignees = $assignees ?? $assignees_notCompleted ?? collect();
    $listStatus = ($listStatus ?? 'incomplete') === 'completed' ? 'completed' : 'incomplete';
    $isCompleted = $listStatus === 'completed';
@endphp
@if (count($assignees) > 0)
    @foreach ($assignees as $list)
        @php
            $admin = \App\Models\Staff::where('id', $list->assigned_to)->first();
            $full_name = $admin ? ($admin->first_name ?? 'N/A') . ' ' . ($admin->last_name ?? 'N/A') : 'N/P';
            $client_name = $list->noteClient ? trim($list->noteClient->company_name_or_personal_name) : 'N/P';
            if ($list->noteClient && $client_name === '') {
                $client_name = trim($list->noteClient->first_name . ' ' . $list->noteClient->last_name) ?: 'N/P';
            }
            $rowIndex = isset($i) ? ++$i : ($assignees->firstItem() + $loop->index);
        @endphp
        <tr data-note-id="{{ $list->id }}">
            <td style="text-align: center;">{{ $rowIndex }}</td>
            <td style="text-align: center;">
                @if ($isCompleted)
                    <button type="button"
                        class="action-done-btn action-done-btn--done not_complete_task"
                        data-id="{{ $list->id }}"
                        data-unique_group_id="{{ $list->unique_group_id }}"
                        data-bs-toggle="tooltip"
                        title="Mark incomplete"
                        aria-label="Mark incomplete">
                        <i class="fa-solid fa-check" aria-hidden="true"></i>
                    </button>
                @else
                    <button type="button"
                        class="action-done-btn complete_task"
                        data-id="{{ $list->id }}"
                        data-unique_group_id="{{ $list->unique_group_id }}"
                        data-bs-toggle="tooltip"
                        title="Mark complete"
                        aria-label="Mark complete">
                        <i class="fa-solid fa-check" aria-hidden="true"></i>
                    </button>
                @endif
            </td>
            <td>{{ $full_name }}</td>
            <td>
                {{ $client_name }}
                <br>
                @if ($list->noteClient)
                    @php
                        $matterRef = $list->matterReference();
                        $detailUrl = $list->clientDetailUrl() ?: URL::to('/clients/detail/' . base64_encode(convert_uuencode($list->client_id)));
                        $linkLabel = $matterRef ?: ($list->noteClient->client_id ?? 'Open');
                    @endphp
                    <a href="{{ $detailUrl }}" target="_blank">{{ $linkLabel }}</a>
                    @if ($matterRef && !empty($list->noteClient->client_id))
                        <br><small class="text-muted">{{ $list->noteClient->client_id }}</small>
                    @endif
                @endif
            </td>
            <td>{{ $list->action_date ? date('d/m/Y', strtotime($list->action_date)) : 'N/P' }}</td>
            <td>{{ $list->task_group ?? 'N/P' }}</td>
            <td>
                @if (isset($list->description) && $list->description != "")
                    @if (mb_strlen($list->description) > 190)
                        {{ mb_substr($list->description, 0, 190) }}
                        <button type="button" class="btn btn-link" data-bs-toggle="popover" title="" data-content="{{ htmlspecialchars($list->description, ENT_QUOTES, 'UTF-8') }}">Read more</button>
                    @else
                        {{ $list->description }}
                    @endif
                @else
                    N/P
                @endif
            </td>
            <td>
                @if ($list->noteClient)
                    @php
                        $openMatterUrl = $list->clientDetailUrl() ?: URL::to('/clients/detail/' . base64_encode(convert_uuencode($list->client_id)));
                        $matterRef = $list->matterReference() ?: '';
                        $clientLabel = $client_name !== 'N/P' ? $client_name : '';
                    @endphp
                    <a href="{{ $openMatterUrl }}" target="_blank" class="btn btn-info btn-sm" data-bs-toggle="tooltip" title="Open matter">
                        <i class="fa-solid fa-folder-open" aria-hidden="true"></i>
                    </a>
                @else
                    @php
                        $openMatterUrl = '';
                        $matterRef = '';
                        $clientLabel = '';
                    @endphp
                @endif
                @unless ($isCompleted)
                    <button
                        type="button"
                        class="btn btn-primary btn-sm update_task"
                        title="Update Task"
                        data-assignedto="{{ $list->assigned_to }}"
                        data-noteid="{{ e($list->description ?? '') }}"
                        data-taskid="{{ $list->id }}"
                        data-taskgroupid="{{ e($list->task_group ?? '') }}"
                        data-actiondate="{{ $list->action_date ? date('Y-m-d', strtotime($list->action_date)) : date('Y-m-d') }}"
                        data-clientid="{{ $list->client_id ? base64_encode(convert_uuencode($list->client_id)) : '' }}"
                        data-matterref="{{ e($matterRef) }}"
                        data-matterurl="{{ e($openMatterUrl) }}"
                        data-clientlabel="{{ e($clientLabel) }}"
                    >
                        <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                    </button>
                @endunless
                <button class="btn btn-danger btn-sm deleteNote" data-remote="/destroy_activity/{{ $list->id }}" data-bs-toggle="tooltip" title="Delete Task">
                    <i class="fa-solid fa-trash" aria-hidden="true"></i>
                </button>
            </td>
        </tr>
    @endforeach
@elseif (empty($appendOnly))
    <tr class="assigned-by-me-empty-row">
        <td colspan="8" style="text-align: center; padding: 20px;">
            {{ $isCompleted ? 'No completed tasks assigned by me.' : 'No tasks assigned by me.' }}
        </td>
    </tr>
@endif
