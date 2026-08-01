@extends('layouts.crm_client_detail')
@include('components.require-tinymce')
@section('title', 'Assigned by Me')

@section('styles')
<link rel="stylesheet" href="{{ asset('css/listing-pagination.css') }}">
<link rel="stylesheet" href="{{ asset('css/listing-container.css') }}">
<link rel="stylesheet" href="{{ asset('css/listing-flatpickr.css') }}">
<style>
    /* Assigned by me â€” docs/theme.md (tokens from crm-theme.css :root; shared listing-*.css for table/cards) */
    .listing-container .client-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
        padding-bottom: 20px;
        border-bottom: 1px solid var(--border, #c8dcef);
        flex-wrap: wrap;
        gap: 15px;
    }

    .listing-container .client-header h1,
    .listing-container .client-header h4 {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--navy, #1e3d60) !important;
        margin: 0;
        word-wrap: break-word;
    }

    .listing-container .client-status {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }

    .listing-container .nav-pills .nav-item .nav-link {
        margin-left: 8px;
    }

    .listing-container .nav-pills .status-badge.nav-link {
        color: var(--text-dark, #1a2c40);
        border: 1px solid var(--border, #c8dcef);
        border-radius: 8px;
        font-weight: 600;
    }

    .listing-container .nav-pills .status-badge.nav-link:hover {
        background: var(--sidebar-hover, #c8dcef);
        color: var(--navy, #1e3d60);
    }

    .listing-container .nav-pills .status-badge.nav-link.active {
        background: var(--navy, #1e3d60) !important;
        color: #fff !important;
        border-color: var(--navy, #1e3d60);
    }

    .listing-container .sort_col a {
        color: var(--sidebar-active, #3a6fa8) !important;
        text-decoration: none;
        font-weight: 600;
    }

    .listing-container .sort_col a:hover {
        color: var(--navy, #1e3d60) !important;
        text-decoration: underline;
    }

    .listing-container .countAction {
        background: var(--navy, #1e3d60);
        padding: 2px 8px;
        border-radius: 999px;
        color: #fff;
        font-size: 0.8em;
        margin-left: 5px;
    }

    .listing-container .complete_task {
        cursor: pointer;
    }

    .listing-container .btn-sm {
        padding: 5px 10px;
        font-size: 0.85em;
    }

    /* Update-task popover: theme tokens (popover mounts under body; outside .listing-container) */
    .popover .popover-body h4 {
        color: var(--navy, #1e3d60);
        font-weight: 700;
        font-size: 1.1rem;
        margin-bottom: 1rem;
    }

    .popover .popover-body .btn-info {
        background: var(--navy, #1e3d60) !important;
        border-color: var(--navy, #1e3d60) !important;
        color: #fff !important;
    }

    .popover .popover-body .btn-info:hover {
        background: var(--sidebar-active, #3a6fa8) !important;
        border-color: var(--sidebar-active, #3a6fa8) !important;
        color: #fff !important;
    }

    body > .ts-dropdown {
        z-index: 10700 !important;
    }

    .popover .ts-wrapper {
        width: 100% !important;
        max-width: 100% !important;
    }

    .listing-container .btn-link {
        color: var(--sidebar-active, #3a6fa8) !important;
    }

    .listing-container .btn-link:hover {
        color: var(--navy, #1e3d60) !important;
    }

    .listing-container .btn-info {
        background: var(--sidebar-active, #3a6fa8) !important;
        border-color: var(--sidebar-active, #3a6fa8) !important;
        color: #fff !important;
    }

    .listing-container .btn-info:hover {
        filter: brightness(1.06);
        color: #fff !important;
    }

    /* Assign / task modals render outside .listing-container */
    #openassigneview .modal-content,
    .custom_modal .modal-content {
        border-radius: 10px;
        border: 1px solid var(--border, #c8dcef);
        box-shadow: 0 1px 4px rgba(30, 61, 96, 0.08);
    }

    #openassigneview .modal-header,
    .custom_modal .modal-header {
        background: var(--page-bg, #f0f6ff) !important;
        border-bottom: 1px solid var(--border, #c8dcef) !important;
        color: var(--navy, #1e3d60) !important;
    }

    #openassigneview .modal-body,
    .custom_modal .modal-body {
        padding: 20px;
    }

    @media (max-width: 768px) {
        .listing-container .table th,
        .listing-container .table td {
            font-size: 0.85em;
            padding: 8px;
        }

        .listing-container .btn-sm {
            padding: 4px 8px;
        }

        .listing-container .table .btn.btn-sm.btn-primary,
        .listing-container .table .btn.btn-sm.btn-danger {
            padding: 0 !important;
        }
    }
</style>
@endsection

@section('content')
<div class="listing-container">
    <section class="listing-section" style="padding-top: 80px;">
        <div class="listing-section-body">
            @include('../Elements/flash-message')
            
            <div class="client-header">
                <h4>Assigned by Me</h4>
                <div class="client-status">
                    <ul class="nav nav-pills" id="client_tabs" role="tablist">
                        <li class="nav-item">
                            <a class="status-badge nav-link active" href="{{ URL::to('/action') }}">Incomplete</a>
                        </li>
                        <li class="nav-item">
                            <a class="status-badge nav-link" href="{{ URL::to('/action_completed') }}">Completed</a>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <form action="{{ route('assignee.assigned_by_me') }}" method="get" class="mb-4">
                        <div class="row">
                            <div class="col-md-12 group_type_section">
                                <!-- Add filters if needed -->
                            </div>
                        </div>
                    </form>

                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="active_quotation" role="tabpanel">
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th width="5%" style="text-align: center;">Sno</th>
                                            <th width="5%" style="text-align: center;">Done</th>
                                            <th width="15%">Assignee Name</th>
                                            <th width="15%">Client / Matter</th>
                                            <th width="15%" class="sort_col">@sortablelink('action_date', 'Assign Date')</th>
                                            <th width="10%" class="sort_col">@sortablelink('task_group', 'Type')</th>
                                            <th>Note</th>
                                            <th width="15%">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @if (count($assignees_notCompleted) > 0)
                                            @foreach ($assignees_notCompleted as $list)
                                                @php
                                                    $admin = \App\Models\Staff::where('id', $list->assigned_to)->first();
                                                    $full_name = $admin ? ($admin->first_name ?? 'N/A') . ' ' . ($admin->last_name ?? 'N/A') : 'N/P';
                                                    $client_name = $list->noteClient ? trim($list->noteClient->company_name_or_personal_name) : 'N/P';
                                                    if ($list->noteClient && $client_name === '') {
                                                        $client_name = trim($list->noteClient->first_name . ' ' . $list->noteClient->last_name) ?: 'N/P';
                                                    }
                                                @endphp
                                                <tr>
                                                    <td style="text-align: center;">{{ ++$i }}</td>
                                                    <td style="text-align: center;">
                                                        <input type="radio" class="complete_task" data-bs-toggle="tooltip" title="Mark Complete!" data-id="{{ $list->id }}" data-unique_group_id="{{ $list->unique_group_id }}">
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
                                                            @if (strlen($list->description) > 190)
                                                                {!! substr($list->description, 0, 190) !!}
                                                                <button type="button" class="btn btn-link" data-bs-toggle="popover" title="" data-content="{{ $list->description }}">Read more</button>
                                                            @else
                                                                {!! $list->description !!}
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
                                                        <button class="btn btn-danger btn-sm deleteNote" data-remote="/destroy_activity/{{ $list->id }}" data-bs-toggle="tooltip" title="Delete Task">
                                                            <i class="fa-solid fa-trash" aria-hidden="true"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        @else
                                            <tr>
                                                <td colspan="8" style="text-align: center; padding: 20px;">
                                                    No actions assigned by me.
                                                </td>
                                            </tr>
                                        @endif
                                    </tbody>
                                </table>
                                
                                <!-- Pagination -->
                                <div class="card-footer">
                                    {!! $assignees_notCompleted->appends($_GET)->links() !!}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<!-- Assign Modal -->
<div class="modal fade custom_modal" id="openassigneview" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content taskview">
            <!-- Modal content will be loaded dynamically -->
        </div>
    </div>
</div>

<!-- Task Completion Notes Modal â€” markup + tokens match action page (public/css/crm-theme.css) -->
<div class="modal fade" id="completionNotesModal" tabindex="-1" role="dialog" aria-labelledby="completionNotesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content completion-notes-modal-content">
            <div class="modal-header completion-notes-modal-header">
                <h5 class="modal-title" id="completionNotesModalLabel">
                    <i class="fa-solid fa-check completion-task-modal-header-icon" aria-hidden="true"></i> Complete Task
                </h5>
                <button type="button" class="close completion-notes-modal-close" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body completion-notes-modal-body">
                <div class="form-group mb-0">
                    <label for="completionNotes" class="completion-notes-label">
                        <i class="fa-solid fa-comment"></i> Completion Notes/Feedback
                    </label>
                    <textarea
                        class="form-control completion-notes-textarea"
                        id="completionNotes"
                        rows="5"
                        placeholder="Enter any notes or feedback about completing this task..."
                    ></textarea>
                    <small class="form-text completion-notes-hint">
                        <i class="fa-solid fa-circle-info"></i> These notes will be saved in the activity log.
                    </small>
                </div>
            </div>
            <div class="modal-footer completion-notes-modal-footer">
                <button type="button" class="btn btn-cancel-complete" data-bs-dismiss="modal">
                    <i class="fa-solid fa-xmark"></i> Cancel
                </button>
                <button type="button" class="btn btn-complete-task-primary" id="confirmTaskCompletion">
                    <i class="fa-solid fa-check"></i> Complete Task
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<link rel="stylesheet" href="{{URL::to('/')}}/css/task-popover-modern.css">
{{-- $.fn.popover: public/js/bootstrap5-jquery-compat.js (layout) --}}
<style>
    /* Reuse Action page Update Task styles on Assigned by Me */
    .popover.update-task-popover {
        max-width: 440px !important;
        width: min(440px, 94vw) !important;
        overflow: visible !important;
        border-radius: 12px !important;
        border: 1px solid var(--border, #c8dcef) !important;
        box-shadow: 0 16px 40px rgba(30, 61, 96, 0.16) !important;
    }
    .popover.update-task-popover .popover-header {
        background: #fff !important;
        color: var(--navy, #1e3d60) !important;
        border-bottom: 1px solid var(--border, #c8dcef) !important;
        font-size: 1.05rem !important;
        font-weight: 700 !important;
        padding: 14px 18px !important;
    }
    .popover.update-task-popover .popover-body {
        background: #fff !important;
        padding: 16px 18px 18px !important;
    }
    .popover.update-task-popover .popover-arrow { display: none !important; }
    .popover .modern-popover-content.update-task-layout {
        display: flex !important;
        flex-direction: column !important;
        gap: 14px !important;
        padding: 0 !important;
    }
    .popover.update-task-popover .control-label {
        margin-bottom: 6px !important;
        font-size: 12px !important;
        font-weight: 600 !important;
        color: var(--text-muted, #5e7a90) !important;
    }
    .popover.update-task-popover .form-control,
    .popover.update-task-popover select.form-control,
    .popover.update-task-popover textarea.form-control {
        border: 1px solid var(--border, #c8dcef) !important;
        border-radius: 8px !important;
        padding: 9px 12px !important;
        font-size: 14px !important;
        min-height: 40px !important;
        background: #fff !important;
        box-shadow: none !important;
    }
    .popover.update-task-popover select.form-control {
        appearance: auto !important;
        -webkit-appearance: menulist !important;
        cursor: pointer;
    }
    .popover.update-task-popover textarea.form-control {
        min-height: 96px !important;
        resize: vertical !important;
    }
    .popover.update-task-popover .update-task-matter-box {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        padding: 10px 12px;
        border: 1px solid var(--border, #c8dcef);
        border-radius: 8px;
        background: var(--page-bg, #f0f6ff);
    }
    .popover.update-task-popover .update-task-matter-box .matter-meta {
        min-width: 0;
        flex: 1 1 auto;
        color: var(--navy, #1e3d60);
        font-weight: 700;
        font-size: 14px;
        line-height: 1.3;
    }
    .popover.update-task-popover .update-task-matter-box .matter-meta small {
        display: block;
        margin-top: 2px;
        font-weight: 500;
        font-size: 12px;
        color: var(--text-muted, #5e7a90);
    }
    .popover.update-task-popover .update-task-matter-box .btn-open-matter {
        flex: 0 0 auto;
        padding: 7px 12px !important;
        font-size: 12px !important;
        font-weight: 600 !important;
        letter-spacing: 0 !important;
        text-transform: none !important;
    }
    .popover.update-task-popover .update-task-actions {
        display: flex;
        justify-content: flex-end;
        gap: 8px;
        padding-top: 12px;
        border-top: 1px solid var(--border, #c8dcef);
    }
    .popover.update-task-popover .update-task-actions .btn {
        padding: 9px 16px !important;
        font-size: 13px !important;
        font-weight: 600 !important;
        letter-spacing: 0 !important;
        text-transform: none !important;
    }
    .popover.update-task-popover .update-task-actions .btn-secondary {
        background: #fff !important;
        border: 1px solid var(--border, #c8dcef) !important;
        color: var(--text-dark, #1a2c40) !important;
    }
    .popover.update-task-popover .error-message:empty {
        display: none !important;
        min-height: 0 !important;
        margin: 0 !important;
    }
    .popover-backdrop {
        position: fixed;
        inset: 0;
        background: rgba(26, 44, 64, 0.35);
        z-index: 1055;
        display: none;
    }
    .popover-backdrop.show { display: block; }
</style>
<script>
    jQuery(document).ready(function($) {
        function escapeHtml(text) {
            if (!text) return '';
            return String(text).replace(/[&<>"']/g, function(m) {
                return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[m];
            });
        }

        function getUpdateTaskContent(assignedTo, noteId, taskId, taskGroup, followupDate, clientId, matterRef, matterUrl, clientLabel) {
            assignedTo = String(assignedTo || '');
            noteId = escapeHtml(noteId || '');
            taskId = escapeHtml(taskId || '');
            taskGroup = String(taskGroup || '');
            clientId = escapeHtml(clientId || '');
            matterRef = escapeHtml(matterRef || '');
            matterUrl = escapeHtml(matterUrl || '');
            clientLabel = escapeHtml(clientLabel || '');
            followupDate = escapeHtml(followupDate || '');

            var matterBlock = '';
            if (matterUrl) {
                var matterTitle = matterRef || clientLabel || 'Open client';
                var matterSub = (matterRef && clientLabel) ? '<small>' + clientLabel + '</small>' : '';
                matterBlock = `
                    <div class="form-group form-group-full-width">
                        <label class="control-label">Matter</label>
                        <div class="update-task-matter-box">
                            <div class="matter-meta">${matterTitle}${matterSub}</div>
                            <a href="${matterUrl}" target="_blank" class="btn btn-primary btn-open-matter">Open</a>
                        </div>
                    </div>`;
            }

            var staffOptions = `
                @foreach (\App\Models\Staff::where('status', 1)->orderBy('first_name', 'ASC')->get() as $admin)
                    @php $branchname = \App\Models\Branch::where('id', $admin->office_id)->first(); @endphp
                    <option value="{{ $admin->id }}" ${assignedTo == '{{ $admin->id }}' ? 'selected' : ''}>
                        {{ $admin->first_name }} {{ $admin->last_name }} ({{ $branchname->office_name ?? 'N/A' }})
                    </option>
                @endforeach
            `;

            return `
                <div id="popover-content" class="modern-popover-content update-task-layout">
                    ${matterBlock}
                    <div class="form-group">
                        <label class="control-label" for="update_task_rem_cat">Assignee</label>
                        <select class="form-control" id="update_task_rem_cat" name="rem_cat">${staffOptions}</select>
                        <div id="assignee-error" class="error-message"></div>
                    </div>
                    <div class="form-group">
                        <label class="control-label" for="update_task_task_group">Group</label>
                        <select class="form-control" id="update_task_task_group" name="task_group">
                            <option value="Call" ${taskGroup == 'Call' ? 'selected' : ''}>Call</option>
                            <option value="Checklist" ${taskGroup == 'Checklist' ? 'selected' : ''}>Checklist</option>
                            <option value="Review" ${taskGroup == 'Review' ? 'selected' : ''}>Review</option>
                            <option value="Query" ${taskGroup == 'Query' ? 'selected' : ''}>Query</option>
                            <option value="Urgent" ${taskGroup == 'Urgent' ? 'selected' : ''}>Urgent</option>
                            <option value="Personal Action" ${taskGroup == 'Personal Action' ? 'selected' : ''}>Personal Action</option>
                            <option value="Follow up" ${taskGroup == 'Follow up' || taskGroup == 'follow_up' ? 'selected' : ''}>Follow up</option>
                        </select>
                        <div id="task-group-error" class="error-message"></div>
                    </div>
                    <div class="form-group form-group-full-width">
                        <label class="control-label" for="update_task_assignnote">Description</label>
                        <textarea id="update_task_assignnote" class="form-control" rows="4">${noteId}</textarea>
                        <div id="note-error" class="error-message"></div>
                    </div>
                    <div class="form-group">
                        <label class="control-label" for="update_task_date">Date</label>
                        <input type="date" class="form-control" id="update_task_date" value="${followupDate}">
                    </div>
                    <input id="assign_note_id" type="hidden" value="${taskId}">
                    <input id="update_task_client_id" type="hidden" value="${clientId}">
                    <div class="update-task-actions">
                        <button type="button" class="btn btn-secondary" id="updateTaskCancel">Cancel</button>
                        <button type="button" class="btn btn-primary" id="updateTask">Save</button>
                    </div>
                </div>`;
        }

        $(document).on('click', '.listing-container .update_task', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var $button = $(this);
            var assignedTo = $button.attr('data-assignedto') || '';
            var noteId = $button.attr('data-noteid') || '';
            var taskId = $button.attr('data-taskid') || '';
            var taskGroup = $button.attr('data-taskgroupid') || '';
            var followupDate = $button.attr('data-actiondate') || '';
            var clientId = $button.attr('data-clientid') || '';
            var matterRef = $button.attr('data-matterref') || '';
            var matterUrl = $button.attr('data-matterurl') || '';
            var clientLabel = $button.attr('data-clientlabel') || '';

            $('.update_task').popover('hide');
            $button.popover('dispose');
            $button.popover({
                html: true,
                sanitize: false,
                title: 'Update Task',
                content: getUpdateTaskContent(assignedTo, noteId, taskId, taskGroup, followupDate, clientId, matterRef, matterUrl, clientLabel),
                trigger: 'manual',
                placement: 'auto',
                boundary: 'viewport',
                customClass: 'update-task-popover',
                template: '<div class="popover" role="tooltip"><div class="popover-header"></div><div class="popover-body"></div></div>',
                container: 'body'
            }).popover('show');
        });

        $(document).on('shown.bs.popover', '.listing-container .update_task', function() {
            var $shell = $('.popover.show').filter(function() {
                return $(this).find('.update-task-layout').length > 0;
            }).last();
            if (!$shell.length) {
                $shell = $('.popover.show').last();
            }
            $shell.addClass('update-task-popover');
            $shell.css({
                position: 'fixed',
                left: '50%',
                top: '50%',
                transform: 'translate(-50%, -50%)',
                margin: '0',
                zIndex: '1060'
            });
            if (!$('.popover-backdrop').length) {
                $('body').append('<div class="popover-backdrop"></div>');
            }
            $('.popover-backdrop').addClass('show').off('click.updateTask').on('click.updateTask', function() {
                $('.update_task').popover('hide');
            });
        });

        $(document).on('click', '#updateTaskCancel', function() {
            $('.update_task').popover('hide');
        });

        $(document).on('hide.bs.popover', '.listing-container .update_task', function() {
            $('.popover-backdrop').removeClass('show').off('click.updateTask');
        });

        $(document).on('click', function(e) {
            if (!$(e.target).closest('.popover').length && !$(e.target).closest('.update_task').length) {
                $('.update_task').popover('hide');
            }
        });

        // Mark task as not complete
        $(document).on('click', '.listing-container .not_complete_task', function() {
            var row_id = $(this).attr('data-id');
            var row_unique_group_id = $(this).attr('data-unique_group_id');
            if (row_id != "") {
                $.ajax({
                    type: 'post',
                    url: "{{ URL::to('/') }}/update-action-not-completed",
                    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                    data: { id: row_id, unique_group_id: row_unique_group_id },
                    success: function() {
                        location.reload();
                    }
                });
            }
        });

        var currentTaskId = null;
        var currentTaskGroupId = null;

        $(document).on('click', '.listing-container .complete_task', function() {
            var row_id = $(this).attr('data-id');
            var row_unique_group_id = $(this).attr('data-unique_group_id');
            if (row_id != "") {
                currentTaskId = row_id;
                currentTaskGroupId = row_unique_group_id;
                $('#completionNotes').val('');
                $('#completionNotesModal').modal('show');
            }
        });

        $(document).on('click', '#confirmTaskCompletion', function() {
            var completionNotes = $('#completionNotes').val().trim();
            if (!currentTaskId) {
                return;
            }
            var $button = $(this);
            $button.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> Completing...');
            $.ajax({
                type: 'post',
                url: "{{ URL::to('/') }}/update-action-completed",
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                data: {
                    id: currentTaskId,
                    unique_group_id: currentTaskGroupId,
                    completion_notes: completionNotes
                },
                success: function() {
                    $('#completionNotesModal').modal('hide');
                    $button.prop('disabled', false).html('<i class="fa-solid fa-check"></i> Complete Task');
                    currentTaskId = null;
                    currentTaskGroupId = null;
                    location.reload();
                },
                error: function(xhr) {
                    console.error('Error completing task:', xhr.responseText);
                    alert('An error occurred while completing the task.');
                    $button.prop('disabled', false).html('<i class="fa-solid fa-check"></i> Complete Task');
                }
            });
        });

        $(document).on('click', '#updateTask', function() {
            var $root = $(this).closest('.popover-body');
            if (!$root.length) {
                $root = $('.popover.show .popover-body');
            }

            $(".popuploader").show();
            var flag = true;
            $root.find(".custom-error, .error-message").text('');

            if (!$root.find('#update_task_rem_cat').val()) {
                $('.popuploader').hide();
                $root.find('#assignee-error').text('Please select an assignee.');
                flag = false;
            }
            if (!$root.find('#update_task_assignnote').val()) {
                $('.popuploader').hide();
                $root.find('#note-error').text('Please enter a description.');
                flag = false;
            }
            if (!$root.find('#update_task_task_group').val()) {
                $('.popuploader').hide();
                $root.find('#task-group-error').text('Please select a group.');
                flag = false;
            }

            if (!flag) {
                return;
            }

            $.ajax({
                type: 'post',
                url: "{{ URL::to('/') }}/clients/action/update",
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                data: {
                    note_id: $root.find('#assign_note_id').val(),
                    note_type: 'follow_up',
                    description: $root.find('#update_task_assignnote').val(),
                    client_id: $root.find('#update_task_client_id').val(),
                    followup_datetime: $root.find('#update_task_date').val(),
                    assignee_name: $root.find('#update_task_rem_cat :selected').text(),
                    rem_cat: $root.find('#update_task_rem_cat').val(),
                    task_group: $root.find('#update_task_task_group').val()
                },
                success: function(response) {
                    $('.popuploader').hide();
                    var obj = (typeof response === 'string') ? $.parseJSON(response) : response;
                    if (obj && obj.success) {
                        $('.update_task').popover('hide');
                        location.reload();
                    } else {
                        alert((obj && obj.message) ? obj.message : 'Could not update task.');
                    }
                },
                error: function(xhr) {
                    $('.popuploader').hide();
                    console.error('Error updating task:', xhr.responseText);
                    alert('An error occurred while updating the task. Please try again.');
                }
            });
        });

        $(document).on('click', '.listing-container .deleteNote', function(e) {
            e.preventDefault();
            var url = $(this).data('remote');
            if (confirm('Are you sure you want to delete this task?')) {
                $.ajax({
                    type: 'DELETE',
                    url: url,
                    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert('Error deleting task: ' + response.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        alert('Error deleting task. Please try again.');
                        console.error('Delete error:', error);
                    }
                });
            }
        });
    });
</script>
@endpush

