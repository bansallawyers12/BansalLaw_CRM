@php
    $assignees = $assignees ?? $assignees_notCompleted ?? collect();
    $listStatus = ($listStatus ?? 'incomplete') === 'completed' ? 'completed' : 'incomplete';
    $i = $i ?? 0;
@endphp
<div class="table-responsive" id="assignedByMeTableWrap">
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
        <tbody id="assignedByMeTbody"
               data-page="{{ method_exists($assignees, 'currentPage') ? $assignees->currentPage() : 1 }}"
               data-last-page="{{ method_exists($assignees, 'lastPage') ? $assignees->lastPage() : 1 }}"
               data-total="{{ method_exists($assignees, 'total') ? $assignees->total() : 0 }}"
               data-loaded="{{ method_exists($assignees, 'count') ? $assignees->count() : 0 }}"
               data-has-more="{{ method_exists($assignees, 'hasMorePages') && $assignees->hasMorePages() ? '1' : '0' }}"
               data-status="{{ $listStatus }}">
            @include('crm.assignee.partials.assigned_by_me_rows', [
                'assignees' => $assignees,
                'assignees_notCompleted' => $assignees,
                'i' => $i,
                'listStatus' => $listStatus,
                'appendOnly' => $appendOnly ?? false,
            ])
        </tbody>
    </table>

    <div id="assignedByMeInfiniteLoader" class="assigned-by-me-infinite-loader" hidden aria-live="polite">
        <span class="assigned-by-me-infinite-loader__spinner" aria-hidden="true"></span>
        <span>Loading more tasks...</span>
    </div>
    <div id="assignedByMeScrollSentinel" class="assigned-by-me-scroll-sentinel" aria-hidden="true"></div>
    <div id="assignedByMeScrollInfo" class="assigned-by-me-scroll-info">
        @if (method_exists($assignees, 'total'))
            Showing {{ $assignees->firstItem() ?: 0 }}–{{ $assignees->lastItem() ?: 0 }}
            of {{ $assignees->total() }} entries
        @else
            Showing 0 of 0 entries
        @endif
    </div>
</div>
