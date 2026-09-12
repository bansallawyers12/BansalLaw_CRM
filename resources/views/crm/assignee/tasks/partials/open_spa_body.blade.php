@php
    $filter = $filter ?? 'all';
    $search = $search ?? '';
    $taskGroupCounts = $taskGroupCounts ?? [];
    $assignees = $assignees ?? collect();
    $i = $i ?? 0;
@endphp

<div class="action-toolbar">
    <div class="tabs" id="openTasksFilterTabs">
        <button type="button" class="tab-button open-tasks-spa-filter {{ $filter === 'all' ? 'active' : '' }}" data-filter="all">
            All <span class="badge" data-count-key="all">{{ $taskGroupCounts['all'] ?? 0 }}</span>
        </button>
        <button type="button" class="tab-button open-tasks-spa-filter {{ $filter === 'call' ? 'active' : '' }}" data-filter="call">
            Call <span class="badge" data-count-key="call">{{ $taskGroupCounts['call'] ?? 0 }}</span>
        </button>
        <button type="button" class="tab-button open-tasks-spa-filter {{ $filter === 'checklist' ? 'active' : '' }}" data-filter="checklist">
            Checklist <span class="badge" data-count-key="checklist">{{ $taskGroupCounts['checklist'] ?? 0 }}</span>
        </button>
        <button type="button" class="tab-button open-tasks-spa-filter {{ $filter === 'review' ? 'active' : '' }}" data-filter="review">
            Review <span class="badge" data-count-key="review">{{ $taskGroupCounts['review'] ?? 0 }}</span>
        </button>
        <button type="button" class="tab-button open-tasks-spa-filter {{ $filter === 'query' ? 'active' : '' }}" data-filter="query">
            Query <span class="badge" data-count-key="query">{{ $taskGroupCounts['query'] ?? 0 }}</span>
        </button>
        <button type="button" class="tab-button open-tasks-spa-filter {{ $filter === 'urgent' ? 'active' : '' }}" data-filter="urgent">
            Urgent <span class="badge" data-count-key="urgent">{{ $taskGroupCounts['urgent'] ?? 0 }}</span>
        </button>
        <button type="button" class="tab-button open-tasks-spa-filter {{ $filter === 'personal_action' ? 'active' : '' }}" data-filter="personal_action">
            Personal Task <span class="badge" data-count-key="personal_action">{{ $taskGroupCounts['personal_action'] ?? 0 }}</span>
        </button>
        <button type="button" class="tab-button open-tasks-spa-filter {{ $filter === 'follow_up' ? 'active' : '' }}" data-filter="follow_up">
            Follow up <span class="badge" data-count-key="follow_up">{{ $taskGroupCounts['follow_up'] ?? 0 }}</span>
        </button>
    </div>
    <div class="action-search">
        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
        <input type="text" id="searchInput" name="q" value="{{ $search }}" placeholder="Search tasks..." aria-label="Search tasks">
    </div>
</div>

<div class="table-responsive" id="openTasksTableWrap">
    <table class="table table-bordered open-tasks-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Done</th>
                <th>Assigner</th>
                <th>Client / Matter</th>
                <th class="sort_col">@sortablelink('action_date', 'Date')</th>
                <th class="sort_col">@sortablelink('task_group', 'Type')</th>
                <th>Note</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody id="openTasksTbody"
               data-page="{{ method_exists($assignees, 'currentPage') ? $assignees->currentPage() : 1 }}"
               data-last-page="{{ method_exists($assignees, 'lastPage') ? $assignees->lastPage() : 1 }}"
               data-total="{{ method_exists($assignees, 'total') ? $assignees->total() : 0 }}"
               data-loaded="{{ method_exists($assignees, 'count') ? $assignees->count() : 0 }}"
               data-has-more="{{ method_exists($assignees, 'hasMorePages') && $assignees->hasMorePages() ? '1' : '0' }}"
               data-filter="{{ $filter }}">
            @include('crm.assignee.tasks.partials.open_rows', [
                'assignees' => $assignees,
                'i' => $i,
                'appendOnly' => $appendOnly ?? false,
            ])
        </tbody>
    </table>

    <div id="actionInfiniteLoader" class="action-infinite-loader" hidden aria-live="polite">
        <span class="action-infinite-loader__spinner" aria-hidden="true"></span>
        <span>Loading more tasks...</span>
    </div>
    <div id="actionScrollSentinel" class="action-scroll-sentinel" aria-hidden="true"></div>
    <div id="actionScrollInfo" class="action-scroll-info">
        @if (method_exists($assignees, 'total'))
            Showing {{ $assignees->firstItem() ?: 0 }}–{{ $assignees->lastItem() ?: 0 }}
            of {{ $assignees->total() }} entries
        @else
            Showing 0 of 0 entries
        @endif
    </div>
</div>
