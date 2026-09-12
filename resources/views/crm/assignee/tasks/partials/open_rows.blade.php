@php
    use App\Helpers\Utf8Helper;

    $assignees = $assignees ?? collect();
    $appendOnly = $appendOnly ?? false;
@endphp
@if (count($assignees) > 0)
    @foreach ($assignees as $list)
        @php
            $rowIndex = isset($i) ? ++$i : ($assignees->firstItem() + $loop->index);

            // Assigner (Query portal submissions show client name)
            $assignerName = 'N/P';
            if (isset($list->task_group) && (string) $list->task_group === 'Query'
                && (int) $list->user_id === (int) $list->client_id && $list->noteClient) {
                $portalLabel = Utf8Helper::safeSanitize(trim($list->noteClient->company_name_or_personal_name ?? ''));
                $assignerName = $portalLabel !== '' ? $portalLabel : 'N/P';
            } elseif ($list->noteStaff) {
                $firstName = Utf8Helper::safeSanitize($list->noteStaff->first_name ?? '');
                $lastName = Utf8Helper::safeSanitize($list->noteStaff->last_name ?? '');
                $assignerName = trim($firstName.' '.$lastName) ?: 'N/P';
            }

            $group = $list->task_group ? Utf8Helper::safeSanitize($list->task_group) : 'N/P';
            $groupSlug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $group), '-'));
            $dateLabel = $list->action_date ? date('d/m/Y', strtotime($list->action_date)) : 'N/P';
            $currentDate = $list->action_date ?: date('Y-m-d');

            $safeDescription = htmlspecialchars(Utf8Helper::safeSanitize($list->description ?? ''), ENT_QUOTES, 'UTF-8');
            $safeTaskGroup = htmlspecialchars(Utf8Helper::safeSanitize($list->task_group ?? ''), ENT_QUOTES, 'UTF-8');
            $encodedClientId = $list->client_id ? base64_encode(convert_uuencode($list->client_id)) : '';
            $detailUrl = $list->clientDetailUrl();
            $matterRef = htmlspecialchars($list->matterReference() ?? '', ENT_QUOTES, 'UTF-8');
            $matterUrl = htmlspecialchars($detailUrl ?? '', ENT_QUOTES, 'UTF-8');
            $clientLabelRaw = '';
            if ($list->noteClient) {
                $clientLabelRaw = trim($list->noteClient->company_name_or_personal_name ?? '');
                if ($clientLabelRaw === '') {
                    $clientLabelRaw = trim(($list->noteClient->first_name ?? '').' '.($list->noteClient->last_name ?? ''));
                }
            }
            $clientLabel = htmlspecialchars(Utf8Helper::safeSanitize($clientLabelRaw), ENT_QUOTES, 'UTF-8');
        @endphp
        <tr data-note-id="{{ $list->id }}">
            <td>{{ $rowIndex }}</td>
            <td>
                <button type="button" class="action-done-btn complete_task"
                    data-id="{{ $list->id }}"
                    data-unique_group_id="{{ e((string) ($list->unique_group_id ?? '')) }}"
                    data-bs-toggle="tooltip" title="Mark complete" aria-label="Mark complete">
                    <i class="fa-solid fa-check" aria-hidden="true"></i>
                </button>
            </td>
            <td><span class="action-assigner">{{ $assignerName }}</span></td>
            <td>
                @if ($list->noteClient && $list->client_id)
                    @php
                        $clientId = Utf8Helper::safeSanitize($list->noteClient->client_id ?? '');
                        $label = Utf8Helper::safeSanitize(trim($list->noteClient->company_name_or_personal_name ?? ''));
                        if ($label === '') {
                            $label = trim(
                                Utf8Helper::safeSanitize($list->noteClient->first_name ?? '')
                                .' '.Utf8Helper::safeSanitize($list->noteClient->last_name ?? '')
                            );
                        }
                        $matterRefPlain = Utf8Helper::safeSanitize($list->matterReference() ?? '');
                        $rowDetailUrl = $detailUrl ?: url('/clients/detail/'.base64_encode(convert_uuencode($list->client_id)));
                        $linkLabel = $matterRefPlain !== '' ? $matterRefPlain : $clientId;
                    @endphp
                    <div class="action-client-cell">
                        <span class="action-client-name">{{ $label }}</span>
                        <a class="action-client-matter" href="{{ $rowDetailUrl }}" target="_blank">{{ $linkLabel }}</a>
                        @if ($matterRefPlain !== '' && $clientId !== '')
                            <span class="action-client-id">{{ $clientId }}</span>
                        @endif
                    </div>
                @else
                    <span class="action-badge-personal">Personal Task</span>
                @endif
            </td>
            <td><span class="action-date">{{ $dateLabel }}</span></td>
            <td>
                <span class="action-type-badge action-type-{{ $groupSlug }}">{{ $group }}</span>
            </td>
            <td>
                @if (isset($list->description) && $list->description != '')
                    @php
                        $sanitizedDescription = Utf8Helper::safeSanitize($list->description);
                    @endphp
                    @if (mb_strlen($sanitizedDescription, 'UTF-8') > 190)
                        <div class="action-note">{{ Utf8Helper::safeTruncate($sanitizedDescription, 190, '') }}
                            <button type="button" class="btn btn-link btn_readmore"
                                data-toggle="popover" data-trigger="click" data-html="true"
                                data-full-content="{{ $sanitizedDescription }}"
                                data-placement="top">Read more</button>
                        </div>
                    @else
                        <div class="action-note">{{ $sanitizedDescription }}</div>
                    @endif
                @else
                    N/P
                @endif
            </td>
            <td>
                <div class="action-row-btns">
                    @if ($detailUrl)
                        <a href="{{ $detailUrl }}" target="_blank" class="btn btn-sm btn-info" title="Open matter">
                            <i class="fa-solid fa-folder-open" aria-hidden="true"></i>
                        </a>
                    @endif
                    <button type="button"
                        data-assignedto="{{ $list->assigned_to }}"
                        data-noteid="{{ $safeDescription }}"
                        data-taskid="{{ $list->id }}"
                        data-taskgroupid="{{ $safeTaskGroup }}"
                        data-actiondate="{{ $currentDate }}"
                        data-clientid="{{ $encodedClientId }}"
                        data-matterref="{{ $matterRef }}"
                        data-matterurl="{{ $matterUrl }}"
                        data-clientlabel="{{ $clientLabel }}"
                        class="btn btn-sm btn-primary update_task"
                        data-role="popover"
                        title="Update task">
                        <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                    </button>
                </div>
            </td>
        </tr>
    @endforeach
@elseif (empty($appendOnly))
    <tr class="open-tasks-empty-row">
        <td colspan="8" style="text-align: center; padding: 20px;">
            No open tasks found
        </td>
    </tr>
@endif
