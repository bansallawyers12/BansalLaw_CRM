@php
    $task_group = $task_group ?? 'All';
    $taskGroupCounts = $taskGroupCounts ?? [];
    $assignees_completed = $assignees_completed ?? collect();
    $i = $i ?? 0;
@endphp

<form action="{{ route('assignee.tasks.completed') }}" method="get" class="action-completed-filter-form" id="completedTasksFilterForm">
    <div class="row mb-3">
        <div class="col-md-12 filter-buttons">
            <a href="{{ route('assignee.tasks.completed', ['group_type' => 'All']) }}"
               data-group-type="All"
               class="group_type completed-tasks-spa-filter {{ $task_group == 'All' ? 'active' : '' }}">
                All <span class="countAction" data-count-key="All">{{ $taskGroupCounts['All'] ?? 0 }}</span>
            </a>
            <button type="button">
                <a href="{{ route('assignee.tasks.completed', ['group_type' => 'Call']) }}"
                   data-group-type="Call"
                   class="group_type completed-tasks-spa-filter {{ $task_group == 'Call' ? 'active' : '' }}">
                    <i class="fa-solid fa-phone" aria-hidden="true"></i> Call
                    <span class="countAction" data-count-key="Call">{{ $taskGroupCounts['Call'] ?? 0 }}</span>
                </a>
            </button>
            <button type="button">
                <a href="{{ route('assignee.tasks.completed', ['group_type' => 'Checklist']) }}"
                   data-group-type="Checklist"
                   class="group_type completed-tasks-spa-filter {{ $task_group == 'Checklist' ? 'active' : '' }}">
                    <i class="fa-solid fa-bars" aria-hidden="true"></i> Checklist
                    <span class="countAction" data-count-key="Checklist">{{ $taskGroupCounts['Checklist'] ?? 0 }}</span>
                </a>
            </button>
            <button type="button">
                <a href="{{ route('assignee.tasks.completed', ['group_type' => 'Review']) }}"
                   data-group-type="Review"
                   class="group_type completed-tasks-spa-filter {{ $task_group == 'Review' ? 'active' : '' }}">
                    <i class="fa-solid fa-check" aria-hidden="true"></i> Review
                    <span class="countAction" data-count-key="Review">{{ $taskGroupCounts['Review'] ?? 0 }}</span>
                </a>
            </button>
            <button type="button">
                <a href="{{ route('assignee.tasks.completed', ['group_type' => 'Query']) }}"
                   data-group-type="Query"
                   class="group_type completed-tasks-spa-filter {{ $task_group == 'Query' ? 'active' : '' }}">
                    <i class="fa-solid fa-question" aria-hidden="true"></i> Query
                    <span class="countAction" data-count-key="Query">{{ $taskGroupCounts['Query'] ?? 0 }}</span>
                </a>
            </button>
            <button type="button">
                <a href="{{ route('assignee.tasks.completed', ['group_type' => 'Urgent']) }}"
                   data-group-type="Urgent"
                   class="group_type completed-tasks-spa-filter {{ $task_group == 'Urgent' ? 'active' : '' }}">
                    <i class="fa-solid fa-flag" aria-hidden="true"></i> Urgent
                    <span class="countAction" data-count-key="Urgent">{{ $taskGroupCounts['Urgent'] ?? 0 }}</span>
                </a>
            </button>
            <button type="button">
                <a href="{{ route('assignee.tasks.completed', ['group_type' => 'Personal Task']) }}"
                   data-group-type="Personal Task"
                   class="group_type completed-tasks-spa-filter {{ $task_group == 'Personal Task' || $task_group == 'Personal Action' ? 'active' : '' }}">
                    <i class="fa-solid fa-list-check" aria-hidden="true"></i> Personal Task
                    <span class="countAction" data-count-key="Personal Task">{{ $taskGroupCounts['Personal Task'] ?? 0 }}</span>
                </a>
            </button>
            <button type="button">
                <a href="{{ route('assignee.tasks.completed', ['group_type' => 'Follow Up']) }}"
                   data-group-type="Follow Up"
                   class="group_type completed-tasks-spa-filter {{ $task_group == 'Follow Up' || $task_group == 'Follow up' ? 'active' : '' }}">
                    <i class="fa-solid fa-calendar-check-o" aria-hidden="true"></i> Follow up
                    <span class="countAction" data-count-key="Follow Up">{{ $taskGroupCounts['Follow Up'] ?? 0 }}</span>
                </a>
            </button>
        </div>
    </div>
</form>

<div class="table-responsive" id="completedTasksTableWrap">
    <table class="table table-bordered">
        <thead>
            <tr>
                <th style="text-align: center;">Sno</th>
                <th style="text-align: center;">Done</th>
                <th>Assigner Name</th>
                <th>Client Reference</th>
                <th class="sort_col">@sortablelink('action_date', 'Assign Date')</th>
                <th class="sort_col">@sortablelink('task_group', 'Type')</th>
                <th>Note</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody id="completedTasksTbody"
               data-page="{{ method_exists($assignees_completed, 'currentPage') ? $assignees_completed->currentPage() : 1 }}"
               data-last-page="{{ method_exists($assignees_completed, 'lastPage') ? $assignees_completed->lastPage() : 1 }}"
               data-total="{{ method_exists($assignees_completed, 'total') ? $assignees_completed->total() : 0 }}"
               data-loaded="{{ method_exists($assignees_completed, 'count') ? $assignees_completed->count() : 0 }}"
               data-has-more="{{ method_exists($assignees_completed, 'hasMorePages') && $assignees_completed->hasMorePages() ? '1' : '0' }}"
               data-group-type="{{ $task_group }}">
            @include('crm.assignee.tasks.partials.completed_rows', [
                'assignees_completed' => $assignees_completed,
                'i' => $i,
                'appendOnly' => $appendOnly ?? false,
            ])
        </tbody>
    </table>

    <div id="completedTasksInfiniteLoader" class="completed-tasks-infinite-loader" hidden aria-live="polite">
        <span class="completed-tasks-infinite-loader__spinner" aria-hidden="true"></span>
        <span>Loading more tasks...</span>
    </div>
    <div id="completedTasksScrollSentinel" class="completed-tasks-scroll-sentinel" aria-hidden="true"></div>
    <div id="completedTasksScrollInfo" class="completed-tasks-scroll-info">
        @if (method_exists($assignees_completed, 'total'))
            Showing {{ $assignees_completed->firstItem() ?: 0 }}–{{ $assignees_completed->lastItem() ?: 0 }}
            of {{ $assignees_completed->total() }} entries
        @else
            Showing 0 of 0 entries
        @endif
    </div>
</div>
