@props([
    'notesData' => [],
    'countNoteDeadline' => 0,
])

@if(count($notesData) > 0)
    <div class="todo-filter-tabs" role="toolbar" aria-label="Filter my tasks">
        <button type="button" class="todo-filter-tab is-active" data-todo-filter="all">All</button>
        <button type="button" class="todo-filter-tab" data-todo-filter="overdue">Overdue</button>
        <button type="button" class="todo-filter-tab" data-todo-filter="today">Due today</button>
        <button type="button" class="todo-filter-tab" data-todo-filter="upcoming">Upcoming</button>
        <button type="button" class="todo-filter-tab" data-todo-filter="no-deadline">No deadline</button>
    </div>
    <ul class="todo-task-list" id="todo-task-list">
        @foreach($notesData as $note)
            <x-dashboard.task-item :note="$note" />
        @endforeach
    </ul>
    <div class="todo-infinite-loader" id="todoInfiniteLoader" hidden>
        <i class="fa-solid fa-spinner fa-spin"></i> Loading more…
    </div>
@else
    <div class="todo-empty-state">
        <div class="todo-empty-icon">
            <i class="fa-solid fa-circle-check"></i>
        </div>
        <h4>All caught up!</h4>
        <p>You have no tasks at the moment.</p>
        <button class="todo-empty-add-btn add_my_task" data-container="body" data-placement="bottom-start" data-html="true" data-content-id="add-task-popover-template" title="Add New Task">
            <i class="fa-solid fa-plus"></i>
            Add a task
        </button>
    </div>
@endif
