<div class="modal-header" style="border-bottom: 1px solid #e9ecef; background-color: #f8f9fa;">
    <h5 class="modal-title" style="font-weight: 600;">
        <i class="fa-solid fa-list-check me-2"></i> Task Details #{{ $task->id }}
    </h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
<div class="modal-body" style="padding: 20px;">
    <div class="row mb-3">
        <div class="col-md-6">
            <label class="form-label text-muted small mb-1">Client</label>
            <div>
                <strong>
                    @if($task->noteClient)
                        {{ $task->noteClient->first_name }} {{ $task->noteClient->last_name }}
                        <span class="text-muted">({{ $task->noteClient->client_id }})</span>
                    @else
                        N/A
                    @endif
                </strong>
            </div>
        </div>
        <div class="col-md-6">
            <label class="form-label text-muted small mb-1">Assigned Staff</label>
            <div>
                <strong>
                    @if($task->assigned_staff)
                        {{ $task->assigned_staff->first_name }} {{ $task->assigned_staff->last_name }}
                    @else
                        Unassigned
                    @endif
                </strong>
            </div>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-md-4">
            <label class="form-label text-muted small mb-1">Group / Priority</label>
            <div>
                <span class="badge bg-secondary">{{ $task->task_group ?? 'General' }}</span>
            </div>
        </div>
        <div class="col-md-4">
            <label class="form-label text-muted small mb-1">Status</label>
            <div>
                @if($task->status == '1')
                    <span class="badge bg-success">Completed</span>
                @elseif($task->status == '2')
                    <span class="badge bg-primary">In-Progress</span>
                @else
                    <span class="badge bg-warning">Pending</span>
                @endif
            </div>
        </div>
        <div class="col-md-4">
            <label class="form-label text-muted small mb-1">Due Date</label>
            <div>
                {{ $task->action_date ? date('d/m/Y', strtotime($task->action_date)) : 'No deadline' }}
            </div>
        </div>
    </div>

    <div class="mb-3">
        <label class="form-label text-muted small mb-1">Description</label>
        <div class="p-2 border rounded bg-light">
            {{ $task->description ?: 'No description provided.' }}
        </div>
    </div>

    @if(!empty($activities) && count($activities) > 0)
        <div class="mt-4">
            <h6 class="text-muted small mb-2"><i class="fa-solid fa-clock-rotate-left me-1"></i> Recent Activity / Comments</h6>
            <div class="list-group list-group-flush border-top border-bottom" style="max-height: 200px; overflow-y: auto;">
                @foreach($activities as $act)
                    <div class="list-group-item px-1 py-2">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="small font-weight-bold">{{ $act->subject }}</span>
                            <span class="text-muted" style="font-size: 0.75rem;">{{ $act->created_at ? $act->created_at->format('d M Y, h:i A') : '' }}</span>
                        </div>
                        @if($act->description)
                            <div class="small text-muted mt-1">{!! strip_tags($act->description, '<p><br><b><strong>') !!}</div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
<div class="modal-footer" style="background-color: #f8f9fa;">
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
</div>
